<?php

namespace App\Services;

use Config\Database;
use RuntimeException;

/**
 * Gas decanting: move gas from one filled shop cylinder (source) into empty / part-filled shop cylinders (targets)
 * of the same branch, e.g. 20 KG from a 45 kg cylinder into 2 x 10 kg cylinders for a customer.
 *
 * Total gas never changes: the source loses exactly what the targets gain. Targets become filled; a source left
 * with 0 KG becomes empty. Gas cost layers are per branch in KG for all cylinder types together, so moving gas
 * between cylinders of one branch leaves them unchanged. Only stock changes: no sale, purchase, cash or customer.
 */
class GasDecantService
{
    public const SOURCE_TYPE = 'gas_decant';

    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /** Filled shop cylinders that can give gas, and empty / part-filled shop cylinders that can take gas. */
    public function candidates(int $locationId): array
    {
        $rows = $this->db->table('cylinder_units cu')
            ->select('cu.id, cu.unit_code, cu.status, cu.gas_weight_kg, cu.cylinder_type_id, ct.code type_code, ct.name type_name, ct.capacity_kg')
            ->join('cylinder_types ct', 'ct.id = cu.cylinder_type_id')
            ->where('cu.location_id', $locationId)->whereIn('cu.status', ['filled', 'empty'])->where('cu.custody_customer_id IS NULL', null, false)
            ->orderBy('ct.sort_order')->orderBy('cu.gas_weight_kg', 'DESC')->orderBy('cu.unit_code')
            ->get()->getResultArray();
        $sources = $targets = [];
        foreach ($rows as $r) {
            $gas = (float) $r['gas_weight_kg'];
            $cap = (float) $r['capacity_kg'];
            if ($r['status'] === 'filled' && $gas > 0.0005) $sources[] = $r;
            if ($cap - $gas > 0.0005) $targets[] = $r + ['room_kg' => round($cap - $gas, 3)];
        }
        return ['sources' => $sources, 'targets' => $targets];
    }

    /**
     * @param array<int,float|string> $targets target cylinder id => KG to put in
     * @return array{id:int,decant_no:string,total_kg:float,source_after_kg:float}
     */
    public function decant(int $locationId, int $sourceId, array $targets, string $notes, int $userId): array
    {
        $notes = trim($notes);
        if (mb_strlen($notes) > 500) throw new RuntimeException('Notes are too long.');
        $plan = [];
        foreach ($targets as $id => $kg) {
            $id = (int) $id;
            $kg = round((float) $kg, 3);
            if ($id <= 0 || $kg <= 0) continue;
            if ($id === $sourceId) throw new RuntimeException('A cylinder cannot receive gas from itself.');
            $plan[$id] = $kg;
        }
        if ($sourceId <= 0) throw new RuntimeException('Select the cylinder to take gas from.');
        if (!$plan) throw new RuntimeException('Enter the KG to put into at least one cylinder.');

        $this->db->transBegin();
        try {
            $ids = array_merge([$sourceId], array_keys($plan));
            sort($ids); // lock in a fixed order
            $units = [];
            foreach ($this->db->query(
                'SELECT cu.*, ct.capacity_kg, ct.code type_code FROM cylinder_units cu JOIN cylinder_types ct ON ct.id = cu.cylinder_type_id
                 WHERE cu.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY cu.id FOR UPDATE',
                $ids
            )->getResultArray() as $u) {
                $units[(int) $u['id']] = $u;
            }
            $src = $units[$sourceId] ?? null;
            if (!$src || (int) $src['location_id'] !== $locationId || $src['status'] !== 'filled' || $src['custody_customer_id'] !== null) {
                throw new RuntimeException('The source cylinder is not a filled cylinder in this branch\'s stock.');
            }
            $total = round(array_sum($plan), 3);
            $srcBefore = round((float) $src['gas_weight_kg'], 3);
            if ($total > $srcBefore + 0.0005) {
                throw new RuntimeException('Cylinder ' . $src['unit_code'] . ' holds only ' . number_format($srcBefore, 3) . ' KG; ' . number_format($total, 3) . ' KG requested.');
            }
            foreach ($plan as $id => $kg) {
                $t = $units[$id] ?? null;
                if (!$t || (int) $t['location_id'] !== $locationId || !in_array($t['status'], ['filled', 'empty'], true) || $t['custody_customer_id'] !== null) {
                    throw new RuntimeException('Target cylinder ' . ($t['unit_code'] ?? $id) . ' is not an empty or filled cylinder in this branch\'s stock.');
                }
                $room = (float) $t['capacity_kg'] - (float) $t['gas_weight_kg'];
                if ($kg > $room + 0.0005) {
                    throw new RuntimeException('Cylinder ' . $t['unit_code'] . ' has room for only ' . number_format(max(0, $room), 3) . ' KG; ' . number_format($kg, 3) . ' KG entered.');
                }
            }

            $now = date('Y-m-d H:i:s');
            $no = 'DC' . date('YmdHis') . '-' . random_int(100, 999);
            $srcAfter = round(max(0, $srcBefore - $total), 3);
            $this->db->table('gas_decants')->insert([
                'decant_no' => $no, 'location_id' => $locationId, 'source_unit_id' => $sourceId,
                'source_before_kg' => $srcBefore, 'source_after_kg' => $srcAfter, 'total_kg' => $total,
                'notes' => $notes !== '' ? $notes : null, 'created_by' => $userId, 'created_at' => $now,
            ]);
            $decantId = (int) $this->db->insertID();
            $move = function (array $u, string $type, string $dir, float $qty, string $note) use ($locationId, $decantId, $userId, $now): void {
                $this->db->table('inventory_movements')->insert([
                    'location_id' => $locationId, 'inventory_type' => $type, 'cylinder_type_id' => $type === 'gas_kg' ? null : (int) $u['cylinder_type_id'],
                    'quantity' => $qty, 'direction' => $dir, 'movement_at' => $now, 'source_type' => self::SOURCE_TYPE, 'source_id' => $decantId,
                    'cylinder_unit_id' => (int) $u['id'], 'created_by' => $userId, 'notes' => mb_substr($note, 0, 255),
                ]);
            };

            // Source: gas out; a source left empty becomes an empty cylinder.
            $move($src, 'gas_kg', 'out', $total, 'Gas moved out by decanting ' . $no);
            if ($srcAfter <= 0.0005) {
                $this->db->table('cylinder_units')->where('id', $sourceId)->update(['status' => 'empty', 'gas_weight_kg' => 0]);
                $move($src, 'filled_cylinder', 'out', 1, 'Emptied by decanting ' . $no);
                $move($src, 'empty_cylinder', 'in', 1, 'Emptied by decanting ' . $no);
            } else {
                $this->db->table('cylinder_units')->where('id', $sourceId)->update(['gas_weight_kg' => $srcAfter]);
            }
            // Targets: gas in; an empty target becomes a filled cylinder.
            foreach ($plan as $id => $kg) {
                $t = $units[$id];
                $before = round((float) $t['gas_weight_kg'], 3);
                $after = round($before + $kg, 3);
                $this->db->table('gas_decant_items')->insert(['decant_id' => $decantId, 'target_unit_id' => $id, 'kg' => $kg, 'before_kg' => $before, 'after_kg' => $after]);
                if ($t['status'] === 'empty') {
                    $move($t, 'empty_cylinder', 'out', 1, 'Filled by decanting ' . $no);
                    $move($t, 'filled_cylinder', 'in', 1, 'Filled by decanting ' . $no);
                }
                $move($t, 'gas_kg', 'in', $kg, 'Gas received from ' . $src['unit_code'] . ' by decanting ' . $no);
                $this->db->table('cylinder_units')->where('id', $id)->update(['status' => 'filled', 'gas_weight_kg' => $after]);
            }
            if (!$this->db->transStatus()) throw new RuntimeException('Gas decanting could not be saved.');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        AuditService::log('DECANT', 'gas_decant', $decantId, null, ['decant_no' => $no, 'source_unit_id' => $sourceId, 'total_kg' => $total, 'targets' => $plan], $userId, $locationId);
        return ['id' => $decantId, 'decant_no' => $no, 'total_kg' => $total, 'source_after_kg' => $srcAfter];
    }

    /** Recent decantings of a branch with their lines. */
    public function recent(int $locationId, int $limit = 50): array
    {
        $rows = $this->db->table('gas_decants d')
            ->select('d.*, cu.unit_code source_code, u.full_name created_by_name')
            ->join('cylinder_units cu', 'cu.id = d.source_unit_id')
            ->join('users u', 'u.id = d.created_by', 'left')
            ->where('d.location_id', $locationId)->orderBy('d.id', 'DESC')->limit($limit)->get()->getResultArray();
        if (!$rows) return [];
        $items = [];
        foreach ($this->db->table('gas_decant_items i')->select('i.*, cu.unit_code')->join('cylinder_units cu', 'cu.id = i.target_unit_id')
            ->whereIn('i.decant_id', array_column($rows, 'id'))->get()->getResultArray() as $it) {
            $items[(int) $it['decant_id']][] = $it;
        }
        foreach ($rows as &$r) $r['items'] = $items[(int) $r['id']] ?? [];
        return $rows;
    }
}
