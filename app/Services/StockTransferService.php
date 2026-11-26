<?php

namespace App\Services;

use Config\Database;
use RuntimeException;

/**
 * Moving physical cylinders between branches.
 *
 * Dispatch (sending branch): the chosen filled / empty cylinders leave the sender's stock and become in_transit.
 *   OUT movements are written at the sender and the cylinders' cost is taken out of the sender's cost layers.
 * Receive (receiving branch): each cylinder is confirmed as received or missing.
 *   - received: the cylinder joins the receiver's stock (location changes) with the gas weight actually found,
 *     IN movements are written at the receiver and the carried cost becomes a cost layer there.
 *     Gas lost on the way (received KG < sent KG) is recorded on the transfer line; its cost stays in the carried value.
 *   - missing: the cylinder goes back into the sender's stock so nothing disappears. The sender investigates and,
 *     if it is really lost, writes it off with a stock adjustment.
 * Cancel (sending branch, before it is received): every cylinder goes back to the sender.
 *
 * A transfer is never deleted. Each step writes audit entries.
 */
class StockTransferService
{
    protected $db;
    protected CostService $cost;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->cost = new CostService();
    }

    /**
     * @param array<int,array{filled?:int,empty?:int}> $byType cylinder type id => how many filled / empty to send
     * @param int[] $unitIds specific filled or empty cylinders to send (used in addition to $byType)
     */
    /**
     * Earliest date a branch may give a transfer (Shop Settings transfer_backdate_days; blank = start of this month).
     */
    public function earliestDate(int $locationId): string
    {
        $days = null;
        try {
            $row = $this->db->table('shop_settings')->select('transfer_backdate_days')->where('location_id', $locationId)->get()->getRowArray();
            if ($row && $row['transfer_backdate_days'] !== null && $row['transfer_backdate_days'] !== '') $days = max(0, (int) $row['transfer_backdate_days']);
        } catch (\Throwable $e) {
            // column not migrated yet
        }
        return $days === null ? substr(\App\Services\BranchContext::today(),0,8).'01' : date('Y-m-d', strtotime(\App\Services\BranchContext::today() . ' -' . $days . ' days'));
    }

    /**
     * The date and time a transfer step is recorded at: now when blank, otherwise the entered date (and time),
     * never in the future, never before the branch's earliest allowed date and never before $notBefore.
     */
    public function resolveDate(?string $input, int $locationId, ?string $notBefore = null): string
    {
        $input = trim((string) $input);
        $now = \App\Services\BranchContext::now();
        if ($input === '') return $now;
        $ts = strtotime(str_replace('T', ' ', $input));
        if ($ts === false) throw new RuntimeException('Invalid transfer date.');
        $at = date('Y-m-d H:i:s', $ts);
        if (date('Y-m-d', $ts) === \App\Services\BranchContext::today() && $at > $now) $at = $now; // today's date with a later clock time: now
        if ($at > $now) throw new RuntimeException('The transfer date cannot be in the future.');
        $earliest = $this->earliestDate($locationId);
        if ($at < $earliest . ' 00:00:00') throw new RuntimeException('The transfer date cannot be before ' . date('d M Y', strtotime($earliest)) . ' (Shop Settings).');
        if ($notBefore !== null && $at < $notBefore) throw new RuntimeException('The date cannot be before ' . date('d M Y H:i', strtotime($notBefore)) . '.');
        return $at;
    }

    public function dispatch(int $fromId, int $toId, array $byType, array $unitIds, string $notes, int $userId, ?string $date = null): array
    {
        if ($fromId <= 0 || $toId <= 0) throw new RuntimeException('Select the receiving branch.');
        if ($fromId === $toId) throw new RuntimeException('The receiving branch must be different from this branch.');
        $to = $this->db->table('locations')->where(['id' => $toId, 'is_active' => 1])->get()->getRowArray();
        if (!$to) throw new RuntimeException('The receiving branch is not active.');
        $notes = trim($notes);
        if (mb_strlen($notes) > 500) throw new RuntimeException('Notes are too long.');

        $this->cost->ensureReady($fromId);
        $this->db->transBegin();
        $tgMark = \App\Services\TimelineGuard::mark();
        try {
            $units = [];
            // Specific cylinders first.
            $unitIds = array_values(array_unique(array_filter(array_map('intval', $unitIds))));
            if ($unitIds) {
                $rows = $this->db->query(
                    'SELECT * FROM cylinder_units WHERE id IN (' . implode(',', array_fill(0, count($unitIds), '?')) . ') FOR UPDATE',
                    $unitIds
                )->getResultArray();
                foreach ($rows as $u) {
                    $this->assertSendable($u, $fromId);
                    $units[(int) $u['id']] = $u;
                }
                if (count($units) !== count($unitIds)) throw new RuntimeException('One or more selected cylinders could not be found. Refresh and try again.');
            }
            // Then counts per cylinder type: full cylinders first for filled stock, oldest first for empties.
            foreach ($byType as $typeId => $want) {
                $typeId = (int) $typeId;
                foreach (['filled' => (int) ($want['filled'] ?? 0), 'empty' => (int) ($want['empty'] ?? 0)] as $status => $count) {
                    if ($count < 0) throw new RuntimeException('Quantities cannot be negative.');
                    if ($count === 0) continue;
                    $order = $status === 'filled' ? 'gas_weight_kg DESC, id ASC' : 'id ASC';
                    $exclude = $units ? ' AND id NOT IN (' . implode(',', array_map('intval', array_keys($units))) . ')' : '';
                    $rows = $this->db->query(
                        "SELECT * FROM cylinder_units WHERE location_id=? AND cylinder_type_id=? AND status=? AND custody_customer_id IS NULL{$exclude} ORDER BY {$order} LIMIT {$count} FOR UPDATE",
                        [$fromId, $typeId, $status]
                    )->getResultArray();
                    if (count($rows) < $count) {
                        $type = $this->db->table('cylinder_types')->select('name')->where('id', $typeId)->get()->getRowArray();
                        throw new RuntimeException('Only ' . count($rows) . ' ' . $status . ' ' . ($type['name'] ?? 'cylinder') . ' cylinder(s) are in stock; ' . $count . ' requested.');
                    }
                    foreach ($rows as $u) $units[(int) $u['id']] = $u;
                }
            }
            if (!$units) throw new RuntimeException('Choose at least one cylinder to send.');

            $no = 'TR' . date('YmdHis') . '-' . random_int(100, 999);
            // A cylinder cannot be sent before its last stock movement (e.g. before it was bought or returned).
            $lastMove = $this->db->table('inventory_movements')->selectMax('movement_at', 'm')
                ->whereIn('cylinder_unit_id', array_keys($units))->get()->getRowArray()['m'] ?? null;
            $now = $this->resolveDate($date, $fromId, $lastMove ?: null);
            $this->db->table('stock_transfers')->insert([
                'transfer_no' => $no, 'from_location_id' => $fromId, 'to_location_id' => $toId, 'status' => 'in_transit',
                'dispatched_at' => $now, 'dispatched_by' => $userId, 'notes' => $notes !== '' ? $notes : null,
            ]);
            $transferId = (int) $this->db->insertID();

            $totals = ['filled' => 0, 'empty' => 0, 'gas_kg' => 0.0];
            foreach ($units as $u) {
                $unitId = (int) $u['id'];
                $typeId = (int) $u['cylinder_type_id'];
                $status = (string) $u['status'];
                $gas = $status === 'filled' ? (float) $u['gas_weight_kg'] : 0.0;
                $gasCost = $gas > 0 ? $this->cost->transferOut($fromId, 'gas', null, $gas) : 0.0;
                $cylCost = $this->cost->transferOut($fromId, 'cylinder', $typeId, 1);
                $this->db->table('stock_transfer_items')->insert([
                    'transfer_id' => $transferId, 'cylinder_unit_id' => $unitId, 'cylinder_type_id' => $typeId,
                    'sent_status' => $status, 'sent_gas_weight_kg' => $gas, 'gas_unit_cost' => $gasCost, 'cylinder_unit_cost' => $cylCost,
                ]);
                $itemId = (int) $this->db->insertID();
                $this->db->table('cylinder_units')->where('id', $unitId)->update(['status' => 'in_transit']);
                $this->movements($fromId, 'out', $u, $gas, 'transfer_out', $transferId, $itemId, $userId, $now, 'Sent to ' . $to['name'] . ' on transfer ' . $no);
                $totals[$status]++;
                $totals['gas_kg'] += $gas;
            }
            if (!$this->db->transStatus()) throw new RuntimeException('Transfer could not be posted.');
            \App\Services\TimelineGuard::check($tgMark, 'this transfer dispatch');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        AuditService::log('DISPATCH', 'stock_transfer', $transferId, null, ['transfer_no' => $no, 'to_location_id' => $toId] + $totals, $userId, $fromId);
        return ['id' => $transferId, 'transfer_no' => $no] + $totals;
    }

    /**
     * @param array<int,array{received?:mixed,gas?:mixed}> $lines transfer item id => what arrived. An item not ticked as received is missing.
     */
    public function receive(int $transferId, int $atLocationId, array $lines, string $notes, int $userId, ?string $date = null): array
    {
        $this->cost->ensureReady($atLocationId);
        $this->db->transBegin();
        $tgMark = \App\Services\TimelineGuard::mark();
        try {
            $t = $this->db->query('SELECT * FROM stock_transfers WHERE id=? FOR UPDATE', [$transferId])->getRowArray();
            if (!$t || (int) $t['to_location_id'] !== $atLocationId) throw new RuntimeException('Transfer not found at this branch.');
            if ($t['status'] !== 'in_transit') throw new RuntimeException('Transfer ' . $t['transfer_no'] . ' is already ' . str_replace('_', ' ', $t['status']) . '.');
            $items = $this->db->table('stock_transfer_items')->where('transfer_id', $transferId)->get()->getResultArray();
            $now = $this->resolveDate($date, $atLocationId, (string) $t['dispatched_at']);
            $summary = ['received' => 0, 'missing' => 0, 'gas_sent_kg' => 0.0, 'gas_received_kg' => 0.0];
            foreach ($items as $it) {
                $itemId = (int) $it['id'];
                $u = $this->db->query('SELECT * FROM cylinder_units WHERE id=? FOR UPDATE', [(int) $it['cylinder_unit_id']])->getRowArray();
                if (!$u || $u['status'] !== 'in_transit') throw new RuntimeException('Cylinder ' . ($u['unit_code'] ?? $it['cylinder_unit_id']) . ' is no longer in transit.');
                $line = $lines[$itemId] ?? [];
                $sentGas = (float) $it['sent_gas_weight_kg'];
                $summary['gas_sent_kg'] += $sentGas;
                if (empty($line['received'])) {
                    $this->returnToSender($t, $it, $u, $userId, $now, 'missing');
                    $summary['missing']++;
                    continue;
                }
                $gas = 0.0;
                if ($it['sent_status'] === 'filled') {
                    $raw = $line['gas'] ?? '';
                    $gas = $raw === '' || $raw === null ? $sentGas : (float) $raw;
                    if ($gas < 0 || $gas > $sentGas + 0.0005) throw new RuntimeException('Cylinder ' . $u['unit_code'] . ': received gas must be between 0 and the ' . number_format($sentGas, 3) . ' KG sent.');
                }
                $status = $it['sent_status'] === 'filled' && $gas > 0.0005 ? 'filled' : 'empty';
                $this->db->table('cylinder_units')->where('id', (int) $u['id'])->update(['location_id' => $atLocationId, 'status' => $status, 'gas_weight_kg' => $status === 'filled' ? $gas : 0]);
                $u['status'] = $status;
                $this->movements($atLocationId, 'in', $u, $status === 'filled' ? $gas : 0.0, 'transfer_in', $transferId, $itemId, $userId, $now, 'Received from transfer ' . $t['transfer_no']);
                // The carried value stays whole: gas lost on the way raises the unit cost of what arrived.
                if ($gas > 0.0005) $this->cost->transferIn($atLocationId, 'gas', null, 'transfer', $transferId, $itemId, $gas, (float) $it['gas_unit_cost'] * $sentGas / $gas);
                $this->cost->transferIn($atLocationId, 'cylinder', (int) $it['cylinder_type_id'], 'transfer', $transferId, $itemId, 1, (float) $it['cylinder_unit_cost']);
                $this->db->table('stock_transfer_items')->where('id', $itemId)->update(['receive_status' => 'received', 'received_gas_weight_kg' => $gas]);
                $summary['received']++;
                $summary['gas_received_kg'] += $gas;
            }
            $notes = trim($notes);
            $this->db->table('stock_transfers')->where('id', $transferId)->update([
                'status' => 'received', 'received_at' => $now, 'received_by' => $userId, 'receive_notes' => $notes !== '' ? mb_substr($notes, 0, 500) : null,
            ]);
            if (!$this->db->transStatus()) throw new RuntimeException('Transfer could not be received.');
            \App\Services\TimelineGuard::check($tgMark, 'this transfer receipt');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        AuditService::log('RECEIVE', 'stock_transfer', $transferId, ['status' => 'in_transit'], ['status' => 'received'] + $summary, $userId, $atLocationId);
        return ['transfer_no' => $t['transfer_no']] + $summary;
    }

    public function cancel(int $transferId, int $atLocationId, string $reason, int $userId): string
    {
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException('A reason is required to cancel a transfer.');
        $this->cost->ensureReady($atLocationId);
        $this->db->transBegin();
        $tgMark = \App\Services\TimelineGuard::mark();
        try {
            $t = $this->db->query('SELECT * FROM stock_transfers WHERE id=? FOR UPDATE', [$transferId])->getRowArray();
            if (!$t || (int) $t['from_location_id'] !== $atLocationId) throw new RuntimeException('Only the sending branch can cancel this transfer.');
            if ($t['status'] !== 'in_transit') throw new RuntimeException('Transfer ' . $t['transfer_no'] . ' is already ' . str_replace('_', ' ', $t['status']) . '.');
            $now = \App\Services\BranchContext::now();
            foreach ($this->db->table('stock_transfer_items')->where('transfer_id', $transferId)->get()->getResultArray() as $it) {
                $u = $this->db->query('SELECT * FROM cylinder_units WHERE id=? FOR UPDATE', [(int) $it['cylinder_unit_id']])->getRowArray();
                if (!$u || $u['status'] !== 'in_transit') throw new RuntimeException('Cylinder ' . ($u['unit_code'] ?? $it['cylinder_unit_id']) . ' is no longer in transit.');
                $this->returnToSender($t, $it, $u, $userId, $now, 'returned');
            }
            $this->db->table('stock_transfers')->where('id', $transferId)->update([
                'status' => 'cancelled', 'cancelled_at' => $now, 'cancelled_by' => $userId, 'cancel_reason' => mb_substr($reason, 0, 255),
            ]);
            if (!$this->db->transStatus()) throw new RuntimeException('Transfer could not be cancelled.');
            \App\Services\TimelineGuard::check($tgMark, 'this transfer cancellation');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        AuditService::log('CANCEL', 'stock_transfer', $transferId, ['status' => 'in_transit'], ['status' => 'cancelled', 'reason' => $reason], $userId, $atLocationId);
        return (string) $t['transfer_no'];
    }

    /** A cylinder that did not arrive (or a cancelled transfer) goes back into the sender's stock as it left. */
    protected function returnToSender(array $t, array $it, array $u, int $userId, string $now, string $itemStatus): void
    {
        $fromId = (int) $t['from_location_id'];
        $gas = (float) $it['sent_gas_weight_kg'];
        $status = (string) $it['sent_status'];
        $this->db->table('cylinder_units')->where('id', (int) $u['id'])->update(['location_id' => $fromId, 'status' => $status, 'gas_weight_kg' => $status === 'filled' ? $gas : 0]);
        $u['status'] = $status;
        $note = $itemStatus === 'missing'
            ? 'Not received on transfer ' . $t['transfer_no'] . ' - back in stock here until it is found or written off'
            : 'Transfer ' . $t['transfer_no'] . ' cancelled';
        $this->movements($fromId, 'in', $u, $gas, 'transfer_back', (int) $t['id'], (int) $it['id'], $userId, $now, $note);
        // The sender's cost layers were set up when it dispatched, so the value can go straight back.
        if ($gas > 0) $this->cost->transferIn($fromId, 'gas', null, 'transfer_back', (int) $t['id'], (int) $it['id'], $gas, (float) $it['gas_unit_cost']);
        $this->cost->transferIn($fromId, 'cylinder', (int) $it['cylinder_type_id'], 'transfer_back', (int) $t['id'], (int) $it['id'], 1, (float) $it['cylinder_unit_cost']);
        $this->db->table('stock_transfer_items')->where('id', (int) $it['id'])->update(['receive_status' => $itemStatus, 'received_gas_weight_kg' => $itemStatus === 'missing' ? 0 : null]);
    }

    protected function assertSendable(array $u, int $fromId): void
    {
        if ((int) $u['location_id'] !== $fromId) throw new RuntimeException('Cylinder ' . $u['unit_code'] . ' is not in this branch.');
        if (!in_array($u['status'], ['filled', 'empty'], true) || $u['custody_customer_id'] !== null) {
            throw new RuntimeException('Cylinder ' . $u['unit_code'] . ' is ' . str_replace('_', ' ', $u['status']) . ' and cannot be sent.');
        }
    }

    /** Cylinder movement (filled / empty) plus the gas inside a filled cylinder. */
    protected function movements(int $locationId, string $direction, array $u, float $gas, string $sourceType, int $transferId, int $itemId, int $userId, string $at, string $note): void
    {
        $base = [
            'location_id' => $locationId, 'cylinder_type_id' => (int) $u['cylinder_type_id'], 'direction' => $direction, 'movement_at' => $at,
            'source_type' => $sourceType, 'source_id' => $transferId, 'source_line_id' => $itemId, 'cylinder_unit_id' => (int) $u['id'],
            'created_by' => $userId, 'notes' => mb_substr($note, 0, 255),
        ];
        $this->db->table('inventory_movements')->insert($base + ['inventory_type' => $u['status'] === 'filled' ? 'filled_cylinder' : 'empty_cylinder', 'quantity' => 1]);
        if ($u['status'] === 'filled' && $gas > 0) {
            $this->db->table('inventory_movements')->insert(array_merge($base, ['inventory_type' => 'gas_kg', 'cylinder_type_id' => null, 'quantity' => $gas]));
        }
    }

    /** Transfers in or out of a branch, newest first. */
    /**
     * Transfers sent or received by a branch. With $from / $to (Y-m-d) only those dispatched in that period,
     * plus every transfer still in transit (so nothing waiting to be received is ever hidden).
     */
    public function listFor(int $locationId, int $limit = 200, ?string $from = null, ?string $to = null): array
    {
        $q = $this->db->table('stock_transfers t');
        if ($from !== null && $to !== null) {
            $q->groupStart()
                ->groupStart()->where('t.dispatched_at >=', $from . ' 00:00:00')->where('t.dispatched_at <=', $to . ' 23:59:59')->groupEnd()
                ->orWhere('t.status', 'in_transit')
            ->groupEnd();
        }
        return $q
            ->select("t.*, lf.name from_name, lt.name to_name, ud.full_name dispatched_by_name, ur.full_name received_by_name,
                (SELECT COUNT(*) FROM stock_transfer_items i WHERE i.transfer_id=t.id AND i.sent_status='filled') filled_count,
                (SELECT COUNT(*) FROM stock_transfer_items i WHERE i.transfer_id=t.id AND i.sent_status='empty') empty_count,
                (SELECT COALESCE(SUM(i.sent_gas_weight_kg),0) FROM stock_transfer_items i WHERE i.transfer_id=t.id) gas_kg,
                (SELECT COUNT(*) FROM stock_transfer_items i WHERE i.transfer_id=t.id AND i.receive_status='missing') missing_count", false)
            ->join('locations lf', 'lf.id=t.from_location_id')
            ->join('locations lt', 'lt.id=t.to_location_id')
            ->join('users ud', 'ud.id=t.dispatched_by', 'left')
            ->join('users ur', 'ur.id=t.received_by', 'left')
            ->groupStart()->where('t.from_location_id', $locationId)->orWhere('t.to_location_id', $locationId)->groupEnd()
            ->orderBy("t.status='in_transit'", 'DESC', false)->orderBy('t.id', 'DESC')
            ->limit($limit)->get()->getResultArray();
    }

    public function find(int $transferId): ?array
    {
        $t = $this->db->table('stock_transfers t')
            ->select('t.*, lf.name from_name, lt.name to_name, ud.full_name dispatched_by_name, ur.full_name received_by_name, uc.full_name cancelled_by_name')
            ->join('locations lf', 'lf.id=t.from_location_id')
            ->join('locations lt', 'lt.id=t.to_location_id')
            ->join('users ud', 'ud.id=t.dispatched_by', 'left')
            ->join('users ur', 'ur.id=t.received_by', 'left')
            ->join('users uc', 'uc.id=t.cancelled_by', 'left')
            ->where('t.id', $transferId)->get()->getRowArray();
        if (!$t) return null;
        $t['items'] = $this->db->table('stock_transfer_items i')
            ->select('i.*, cu.unit_code, ct.code type_code, ct.name type_name, ct.capacity_kg')
            ->join('cylinder_units cu', 'cu.id=i.cylinder_unit_id')
            ->join('cylinder_types ct', 'ct.id=i.cylinder_type_id')
            ->where('i.transfer_id', $transferId)->orderBy('ct.sort_order')->orderBy('cu.unit_code')->get()->getResultArray();
        return $t;
    }
}
