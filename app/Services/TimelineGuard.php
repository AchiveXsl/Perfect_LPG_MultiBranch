<?php

namespace App\Services;

use App\Exceptions\SystemDateConflict;
use Config\Database;

/**
 * Keeps history consistent when transactions are dated with a System Date (owner decision 2026-11-26).
 *
 * A posting may not touch a physical cylinder at a date earlier than a movement already recorded for
 * that cylinder: the stock, gas and custody of every later day would no longer add up.
 *
 * Use inside the posting transaction:
 *     $mark = TimelineGuard::mark();
 *     ... insert inventory_movements ...
 *     TimelineGuard::check($mark);   // before transCommit
 * check() looks only at movements inserted after mark() and throws SystemDateConflict with the
 * later transactions and what the user can do.
 */
class TimelineGuard
{
    public static function mark(): int
    {
        $row = Database::connect()->query('SELECT COALESCE(MAX(id),0) m FROM inventory_movements')->getRowArray();
        return (int) ($row['m'] ?? 0);
    }

    /**
     * SQL condition: the cylinder of alias $unitAlias has no movement after $at, so a posting dated $at may use it.
     * Pick lists and automatic allocation use it, so a back-dated screen only offers cylinders free on that date.
     */
    public static function freeAtSql(string $unitAlias, string $at): string
    {
        $db = Database::connect();
        return ' AND NOT EXISTS (SELECT 1 FROM inventory_movements tg_m WHERE tg_m.cylinder_unit_id = ' . $unitAlias . '.id AND tg_m.movement_at > ' . $db->escape($at) . ')';
    }

    /** @param string $doing what is being posted, e.g. "this sale" */
    public static function check(int $mark, string $doing = 'this transaction'): void
    {
        $db = Database::connect();
        $new = $db->query(
            'SELECT cylinder_unit_id, MIN(movement_at) at FROM inventory_movements WHERE id > ? AND cylinder_unit_id IS NOT NULL GROUP BY cylinder_unit_id',
            [$mark]
        )->getResultArray();
        if (!$new) {
            return;
        }
        $later = [];
        foreach ($new as $n) {
            $rows = $db->query(
                'SELECT m.cylinder_unit_id, m.movement_at, m.source_type, m.source_id, m.location_id, u.unit_code
                 FROM inventory_movements m LEFT JOIN cylinder_units u ON u.id = m.cylinder_unit_id
                 WHERE m.cylinder_unit_id = ? AND m.id <= ? AND m.movement_at > ?
                 ORDER BY m.movement_at DESC LIMIT 1',
                [(int) $n['cylinder_unit_id'], $mark, $n['at']]
            )->getResultArray();
            foreach ($rows as $r) {
                $r['posting_at'] = $n['at'];
                $later[] = $r;
            }
        }
        if (!$later) {
            return;
        }
        throw self::conflict($later, $doing);
    }

    /** @param array<int,array> $later one row per cylinder: its latest movement after the posting date */
    protected static function conflict(array $later, string $doing): SystemDateConflict
    {
        $postingAt = min(array_column($later, 'posting_at'));
        $latest = max(array_column($later, 'movement_at'));
        $items = [];
        $docs = [];
        foreach ($later as $r) {
            $doc = self::describe((string) $r['source_type'], (int) $r['source_id']);
            $docs[$doc['label']] = $doc;
            $items[] = [
                'label' => 'Cylinder ' . ($r['unit_code'] ?: '#' . $r['cylinder_unit_id']) . ': ' . $doc['label'],
                'date' => date('d-M-Y H:i', strtotime((string) $r['movement_at'])),
                'detail' => $doc['detail'] ?? '',
                'url' => $doc['url'] ?? '',
            ];
        }
        $postingDay = date('d-M-Y', strtotime($postingAt));
        $latestDay = date('Y-m-d', strtotime($latest));
        $count = count($later);
        $why = ($count === 1 ? 'A cylinder in ' . $doing . ' was' : $count . ' cylinders in ' . $doing . ' were')
            . ' already used after ' . $postingDay . '. Posting it on ' . $postingDay
            . ' would put this movement before the later ones, so the stock, gas and customer cylinders of every day after '
            . $postingDay . ' would no longer add up.';

        $solutions = [];
        $solutions[] = ['text' => 'Use another cylinder that had no activity after ' . $postingDay . '. Cylinders listed on the screen for this System Date are free to use.'];
        $solutions[] = [
            'text' => 'Post it on a later date: change the System Date to ' . date('d-M-Y', strtotime($latestDay)) . ' or later.',
            'action' => ['type' => 'set_date', 'date' => $latestDay, 'label' => 'Change System Date to ' . date('d-M-Y', strtotime($latestDay))],
        ];
        $voidable = array_filter($docs, static fn ($d) => !empty($d['voidable']));
        if ($voidable) {
            $names = implode(', ', array_keys($voidable));
            $solutions[] = ['text' => 'Correct the history: void ' . $names . ' first (with a reason), post this transaction, then enter ' . $names . ' again on its own date.'];
        }
        $solutions[] = ['text' => 'If the later transaction itself is wrong, ask an Admin to review it before posting this one.'];

        return new SystemDateConflict(
            'Cannot post ' . $doing . ' on ' . $postingDay,
            $why,
            $items,
            $solutions
        );
    }

    /** A readable name, detail and link for the transaction that wrote a movement. */
    public static function describe(string $sourceType, int $sourceId): array
    {
        $db = Database::connect();
        $one = static function (string $sql) use ($db, $sourceId): array {
            try {
                return $db->query($sql, [$sourceId])->getRowArray() ?: [];
            } catch (\Throwable $e) {
                return [];
            }
        };
        switch ($sourceType) {
            case 'sale':
            case 'sale_void':
            case 'security_deposit':
            case 'cylinder_return':
                $s = $one('SELECT s.sale_no, s.status, s.transaction_type, c.name customer FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.id = ?');
                $label = ($sourceType === 'sale_void' ? 'Void of sale ' : 'Sale ') . ($s['sale_no'] ?? '#' . $sourceId);
                return ['label' => $label, 'detail' => trim(ucwords(str_replace('_', ' ', (string) ($s['transaction_type'] ?? ''))) . (isset($s['customer']) && $s['customer'] ? ', customer ' . $s['customer'] : ', walk-in')),
                    'url' => site_url('sales/receipt/' . $sourceId), 'voidable' => $sourceType !== 'sale_void' && ($s['status'] ?? '') !== 'voided'];
            case 'purchase':
            case 'purchase_void':
                $p = $one('SELECT purchase_no, status FROM purchases WHERE id = ?');
                return ['label' => ($sourceType === 'purchase_void' ? 'Void of purchase ' : 'Purchase ') . ($p['purchase_no'] ?? '#' . $sourceId),
                    'url' => site_url('purchases/receipt/' . $sourceId), 'voidable' => $sourceType === 'purchase' && ($p['status'] ?? '') !== 'voided'];
            case 'transfer_out':
            case 'transfer_in':
            case 'transfer_back':
                $t = $one('SELECT transfer_no FROM stock_transfers WHERE id = ?');
                $what = ['transfer_out' => 'Dispatch of transfer ', 'transfer_in' => 'Receipt of transfer ', 'transfer_back' => 'Return of transfer '][$sourceType];
                return ['label' => $what . ($t['transfer_no'] ?? '#' . $sourceId), 'url' => site_url('stock-transfers/' . $sourceId)];
            case 'gas_decant':
                $d = $one('SELECT decant_no FROM gas_decants WHERE id = ?');
                return ['label' => 'Gas decanting ' . ($d['decant_no'] ?? '#' . $sourceId), 'url' => site_url('inventory/decant')];
            case 'cylinder_fill':
                return ['label' => 'Fill / empty cylinders #' . $sourceId, 'url' => site_url('inventory/fill-empty')];
            case 'adjustment':
                return ['label' => 'Stock adjustment #' . $sourceId, 'url' => site_url('inventory/adjustments/' . $sourceId)];
            case 'wastage':
                return ['label' => 'Gas wastage entry'];
            case 'old_issue':
                return ['label' => 'Old issued cylinder entry #' . $sourceId, 'url' => site_url('inventory/old-issued')];
            case 'opening':
            case 'opening_cylinder':
                return ['label' => 'Opening inventory'];
        }
        return ['label' => ucwords(str_replace('_', ' ', $sourceType)) . ' #' . $sourceId];
    }
}
