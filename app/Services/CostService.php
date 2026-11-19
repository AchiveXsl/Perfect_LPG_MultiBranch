<?php
namespace App\Services;

use Config\Database;

/**
 * Cost price of what is sold (FIFO, LIFO or Average) and the profit that follows.
 *
 * Purchases and opening stock create cost layers. Posting a sale consumes layers with the method chosen in
 * Shop Settings and stores the resulting cost on the sale lines, so changing the method later never alters
 * a sale that is already posted. Voiding a sale puts the consumed quantity back into the layers.
 *
 * Gas layers are in KG (all cylinder types together). Cylinder layers are in cylinders, per cylinder type.
 * A filled-cylinder purchase is costed entirely as gas, an empty-cylinder purchase as a cylinder.
 */
class CostService
{
    public const METHODS = ['fifo' => 'FIFO (First In, First Out)', 'lifo' => 'LIFO (Last In, First Out)', 'average' => 'Average (weighted)'];

    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function method(int $locationId): string
    {
        $row = $this->db->table('shop_settings')->select('cost_method')->where('location_id', $locationId)->get()->getRowArray();
        $m = (string) ($row['cost_method'] ?? 'average');
        return isset(self::METHODS[$m]) ? $m : 'average';
    }

    /**
     * One-time start for a location: builds layers from the purchases and opening stock that already exist.
     * Quantity left is set so that it matches the stock on hand now (newest lots are assumed to be the ones still in stock).
     * Call before a sale, purchase or opening entry changes stock.
     */
    public function ensureReady(int $locationId): void
    {
        if ($this->db->table('cost_layer_state')->where('location_id', $locationId)->countAllResults() > 0) {
            return;
        }
        $this->db->transBegin();
        try {
            foreach ($this->db->table('purchases')->select('id')->where(['location_id' => $locationId, 'status' => 'posted'])->get()->getResultArray() as $p) {
                $this->addPurchaseLayers((int) $p['id'], false);
            }
            foreach ($this->db->table('inventory_opening_balances')->select('id')->where('location_id', $locationId)->get()->getResultArray() as $o) {
                $this->syncOpening((int) $o['id'], $locationId, false);
            }
            // Quantity left = stock on hand now, newest lots first.
            $gasNow = (float) ($this->db->query("SELECT COALESCE(SUM(gas_weight_kg),0) kg FROM cylinder_units WHERE location_id=? AND status='filled'", [$locationId])->getRowArray()['kg'] ?? 0);
            $this->fillRemaining($locationId, 'gas', null, $gasNow);
            foreach ($this->db->query("SELECT cylinder_type_id, COUNT(*) n FROM cylinder_units WHERE location_id=? AND status IN ('filled','empty','custody') GROUP BY cylinder_type_id", [$locationId])->getResultArray() as $r) {
                $this->fillRemaining($locationId, 'cylinder', (int) $r['cylinder_type_id'], (float) $r['n']);
            }
            $this->db->query('INSERT INTO cost_layer_state(location_id,ready_at) VALUES(?,?)', [$locationId, date('Y-m-d H:i:s')]);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    protected function fillRemaining(int $locationId, string $kind, ?int $typeId, float $stock): void
    {
        $q = $this->db->table('cost_layers')->where(['location_id' => $locationId, 'kind' => $kind, 'status' => 'active']);
        $typeId === null ? $q->where('cylinder_type_id IS NULL', null, false) : $q->where('cylinder_type_id', $typeId);
        $left = $stock;
        foreach ($q->orderBy('received_at', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray() as $l) {
            $keep = max(0.0, min((float) $l['qty_in'], $left));
            $left -= $keep;
            $this->db->table('cost_layers')->where('id', (int) $l['id'])->update(['qty_left' => $keep]);
        }
    }

    /** Layers for the lines of one purchase. Safe to call again. */
    public function addPurchaseLayers(int $purchaseId, bool $fresh = true): void
    {
        $p = $this->db->table('purchases')->where('id', $purchaseId)->get()->getRowArray();
        if (!$p) return;
        foreach ($this->db->table('purchase_items')->where('purchase_id', $purchaseId)->get()->getResultArray() as $it) {
            $type = (string) $it['line_type'];
            $qty = (float) $it['quantity'];
            $total = (float) $it['line_total'];
            if ($qty <= 0) continue;
            if ($type === 'empty_cylinder') {
                $this->insertLayer((int) $p['location_id'], 'cylinder', (int) $it['cylinder_type_id'], 'purchase', $purchaseId, (int) $it['id'], (string) $p['transaction_at'], $qty, $total / $qty, $fresh);
            } else {
                $kg = $type === 'gas_kg' ? $qty : $qty * (float) $it['actual_gas_weight_kg'];
                if ($kg > 0) $this->insertLayer((int) $p['location_id'], 'gas', null, 'purchase', $purchaseId, (int) $it['id'], (string) $p['transaction_at'], $kg, $total / $kg, $fresh);
            }
        }
    }

    protected function insertLayer(int $loc, string $kind, ?int $typeId, string $srcType, int $srcId, int $lineId, string $at, float $qty, float $unitCost, bool $fresh): void
    {
        $exists = $this->db->table('cost_layers')->where(['source_type' => $srcType, 'source_id' => $srcId, 'source_line_id' => $lineId, 'kind' => $kind])->countAllResults();
        if ($exists) return;
        $this->db->table('cost_layers')->insert([
            'location_id' => $loc, 'kind' => $kind, 'cylinder_type_id' => $typeId, 'source_type' => $srcType, 'source_id' => $srcId, 'source_line_id' => $lineId,
            'received_at' => $at, 'qty_in' => $qty, 'qty_left' => $fresh ? $qty : 0, 'unit_cost' => $unitCost, 'status' => 'active',
        ]);
    }

    /** A purchase was voided: its lots leave the pool. */
    public function voidPurchaseLayers(int $purchaseId): void
    {
        $this->db->table('cost_layers')->where(['source_type' => 'purchase', 'source_id' => $purchaseId])->update(['status' => 'voided', 'qty_left' => 0]);
    }

    /** Layers for one opening inventory row (gas of filled cylinders, and the cylinders). Cost is 0 when not entered. */
    public function syncOpening(int $openingId, int $locationId, bool $fresh = true): void
    {
        $o = $this->db->table('inventory_opening_balances')->where('id', $openingId)->get()->getRowArray();
        if (!$o) {
            $this->db->table('cost_layers')->where(['source_type' => 'opening', 'source_id' => $openingId])->update(['status' => 'voided', 'qty_left' => 0]);
            return;
        }
        $type = (string) $o['inventory_type'];
        $qty = (float) $o['quantity'];
        $at = $o['inventory_date'] . ' 00:00:00';
        if ($type === 'gas_kg') {
            $this->upsertOpening($locationId, 'gas', null, $openingId, $at, $qty, (float) ($o['gas_cost_per_kg'] ?? 0), $fresh);
            return;
        }
        $typeId = (int) $o['cylinder_type_id'];
        if ($type === 'filled_cylinder') {
            $unitGas = (float) ($this->db->query("SELECT COALESCE(AVG(gas_weight_kg),0) w FROM cylinder_units WHERE location_id=? AND source_type='opening' AND source_id=?", [$locationId, $openingId])->getRowArray()['w'] ?? 0);
            if ($unitGas <= 0) $unitGas = (float) ($this->db->table('cylinder_types')->select('capacity_kg')->where('id', $typeId)->get()->getRowArray()['capacity_kg'] ?? 0);
            if ($unitGas * $qty > 0) $this->upsertOpening($locationId, 'gas', null, $openingId, $at, $unitGas * $qty, (float) ($o['gas_cost_per_kg'] ?? 0), $fresh);
        }
        if ($qty > 0) $this->upsertOpening($locationId, 'cylinder', $typeId, $openingId, $at, $qty, (float) ($o['cylinder_cost'] ?? 0), $fresh);
    }

    protected function upsertOpening(int $loc, string $kind, ?int $typeId, int $openingId, string $at, float $qty, float $unitCost, bool $fresh): void
    {
        $row = $this->db->table('cost_layers')->where(['source_type' => 'opening', 'source_id' => $openingId, 'source_line_id' => 0, 'kind' => $kind])->get()->getRowArray();
        if ($row) {
            $used = (float) $row['qty_in'] - (float) $row['qty_left'];
            if ($used > 0.0005) {
                $this->db->table('cost_layers')->where('id', (int) $row['id'])->update(['unit_cost' => $unitCost]);
                return;
            }
            $this->db->table('cost_layers')->where('id', (int) $row['id'])->update(['received_at' => $at, 'qty_in' => $qty, 'qty_left' => $qty, 'unit_cost' => $unitCost, 'status' => 'active', 'cylinder_type_id' => $typeId]);
            return;
        }
        $this->insertLayer($loc, $kind, $typeId, 'opening', $openingId, 0, $at, $qty, $unitCost, $fresh);
    }

    /**
     * Consume qty from the layers and return the parts as [layer_id, qty, unit_cost].
     * FIFO takes the oldest lot first, LIFO the newest, Average takes a share of every lot and costs it at the weighted average.
     * Quantity beyond the layers (stock override) is costed at the latest known unit cost.
     */
    protected function consume(int $locationId, string $kind, ?int $typeId, float $qty, string $method): array
    {
        if ($qty <= 0.00001) return [];
        $q = $this->db->table('cost_layers')->where(['location_id' => $locationId, 'kind' => $kind, 'status' => 'active', 'qty_left >' => 0]);
        $typeId === null ? $q->where('cylinder_type_id IS NULL', null, false) : $q->where('cylinder_type_id', $typeId);
        $dir = $method === 'lifo' ? 'DESC' : 'ASC';
        $layers = $q->orderBy('received_at', $dir)->orderBy('id', $dir)->get()->getResultArray();
        $parts = [];
        $left = $qty;
        if ($method === 'average') {
            $totQty = array_sum(array_map(static fn($l) => (float) $l['qty_left'], $layers));
            $totVal = array_sum(array_map(static fn($l) => (float) $l['qty_left'] * (float) $l['unit_cost'], $layers));
            $avg = $totQty > 0 ? $totVal / $totQty : $this->lastUnitCost($locationId, $kind, $typeId);
            $take = min($qty, $totQty);
            foreach ($layers as $l) {
                $share = $totQty > 0 ? $take * ((float) $l['qty_left'] / $totQty) : 0;
                if ($share <= 0) continue;
                $this->db->query('UPDATE cost_layers SET qty_left=GREATEST(qty_left-?,0) WHERE id=?', [$share, (int) $l['id']]);
                $parts[] = ['layer_id' => (int) $l['id'], 'qty' => $share, 'unit_cost' => $avg];
            }
            $left = $qty - $take;
            if ($left > 0.00001) $parts[] = ['layer_id' => null, 'qty' => $left, 'unit_cost' => $avg];
            return $parts;
        }
        foreach ($layers as $l) {
            if ($left <= 0.00001) break;
            $use = min($left, (float) $l['qty_left']);
            $this->db->query('UPDATE cost_layers SET qty_left=GREATEST(qty_left-?,0) WHERE id=?', [$use, (int) $l['id']]);
            $parts[] = ['layer_id' => (int) $l['id'], 'qty' => $use, 'unit_cost' => (float) $l['unit_cost']];
            $left -= $use;
        }
        if ($left > 0.00001) $parts[] = ['layer_id' => null, 'qty' => $left, 'unit_cost' => $this->lastUnitCost($locationId, $kind, $typeId)];
        return $parts;
    }

    protected function lastUnitCost(int $locationId, string $kind, ?int $typeId): float
    {
        $q = $this->db->table('cost_layers')->select('unit_cost')->where(['location_id' => $locationId, 'kind' => $kind, 'status' => 'active']);
        $typeId === null ? $q->where('cylinder_type_id IS NULL', null, false) : $q->where('cylinder_type_id', $typeId);
        return (float) ($q->orderBy('received_at', 'DESC')->orderBy('id', 'DESC')->get(1)->getRowArray()['unit_cost'] ?? 0);
    }

    /** Costs a posted sale and stores the cost on its lines. Call inside the posting transaction, after the lines exist. */
    public function costSale(int $saleId, int $locationId): void
    {
        $sale = $this->db->table('sales')->select('transaction_type')->where('id', $saleId)->get()->getRowArray();
        if (!$sale || !in_array($sale['transaction_type'], ['gas_sale', 'cylinder_sale', 'security_deposit'], true)) return;
        if ($this->db->table('cost_layer_usage')->where('sale_id', $saleId)->countAllResults() > 0) return;
        $method = $this->method($locationId);
        foreach ($this->db->table('sale_items')->where('sale_id', $saleId)->orderBy('line_no')->get()->getResultArray() as $it) {
            $gasParts = $this->consume($locationId, 'gas', null, (float) $it['gas_weight_kg'], $method);
            // The cylinder stays company property on a Security Deposit, and only Cylinder Sale sells it.
            $cylParts = $sale['transaction_type'] === 'cylinder_sale' && !empty($it['cylinder_type_id'])
                ? $this->consume($locationId, 'cylinder', (int) $it['cylinder_type_id'], (float) $it['quantity'], $method) : [];
            $gasCost = 0.0;
            $cylCost = 0.0;
            foreach ([['gas', $gasParts], ['cylinder', $cylParts]] as [$kind, $parts]) {
                foreach ($parts as $p) {
                    $amount = $p['qty'] * $p['unit_cost'];
                    $kind === 'gas' ? $gasCost += $amount : $cylCost += $amount;
                    $this->db->table('cost_layer_usage')->insert(['sale_id' => $saleId, 'sale_item_id' => (int) $it['id'], 'layer_id' => $p['layer_id'], 'kind' => $kind, 'qty' => $p['qty'], 'unit_cost' => $p['unit_cost'], 'amount' => $amount]);
                }
            }
            $this->db->table('sale_items')->where('id', (int) $it['id'])->update([
                'cost_gas' => round($gasCost, 2), 'cost_cylinder' => round($cylCost, 2), 'cost_amount' => round($gasCost + $cylCost, 2), 'cost_method' => $method,
            ]);
        }
    }

    /** A sale was voided: the quantity it consumed goes back into the layers. */
    public function reverseSale(int $saleId): void
    {
        foreach ($this->db->table('cost_layer_usage')->where(['sale_id' => $saleId, 'reversed' => 0])->get()->getResultArray() as $u) {
            if ($u['layer_id']) {
                $this->db->query("UPDATE cost_layers SET qty_left=LEAST(qty_left+?,qty_in) WHERE id=? AND status='active'", [(float) $u['qty'], (int) $u['layer_id']]);
            }
            $this->db->table('cost_layer_usage')->where('id', (int) $u['id'])->update(['reversed' => 1]);
        }
    }

    /** Weighted-average purchase cost of everything bought so far: used only to estimate sales posted before cost was stored. */
    public function legacyAverages(int $locationId): array
    {
        $gas = $this->db->table('purchase_items pi')
            ->select("COALESCE(SUM(pi.line_total),0) cost,COALESCE(SUM(CASE WHEN pi.line_type='gas_kg' THEN pi.quantity ELSE pi.quantity*pi.actual_gas_weight_kg END),0) kg", false)
            ->join('purchases p', 'p.id=pi.purchase_id')
            ->where(['p.location_id' => $locationId, 'p.status' => 'posted'])->whereIn('pi.line_type', ['gas_kg', 'filled_cylinder', 'refill_empty'])->get()->getRowArray();
        $perKg = (float) $gas['kg'] > 0 ? (float) $gas['cost'] / (float) $gas['kg'] : 0.0;
        $cyl = [];
        foreach ($this->db->table('purchase_items pi')->select('pi.cylinder_type_id,SUM(pi.line_total) cost,SUM(pi.quantity) qty')->join('purchases p', 'p.id=pi.purchase_id')
            ->where(['p.location_id' => $locationId, 'p.status' => 'posted', 'pi.line_type' => 'empty_cylinder'])->groupBy('pi.cylinder_type_id')->get()->getResultArray() as $r) {
            if ((float) $r['qty'] > 0) $cyl[(int) $r['cylinder_type_id']] = (float) $r['cost'] / (float) $r['qty'];
        }
        return ['gas' => $perKg, 'cyl' => $cyl];
    }

    /**
     * Customer-wise sales and profit for a date range.
     * @return array{rows:array,total:array}
     */
    public function customerProfit(int $locationId, string $from, string $toExclusive): array
    {
        $sales = $this->saleRows($locationId, $from, $toExclusive, null);
        $by = [];
        foreach ($sales as $s) {
            $k = (int) ($s['customer_id'] ?? 0);
            $by[$k] ??= ['customer_id' => $k, 'name' => $k ? (string) $s['customer_name'] : 'Walk-in / Cash', 'sales' => 0, 'count' => 0, 'cost' => 0.0, 'profit' => 0.0, 'estimated' => false];
            $by[$k]['sales'] += $s['revenue'];
            $by[$k]['count']++;
            $by[$k]['cost'] += $s['cost'];
            $by[$k]['profit'] += $s['profit'];
            $by[$k]['estimated'] = $by[$k]['estimated'] || $s['estimated'];
        }
        $rows = array_values($by);
        usort($rows, static fn($a, $b) => $b['sales'] <=> $a['sales']);
        return ['rows' => $rows, 'total' => [
            'sales' => array_sum(array_column($rows, 'sales')), 'cost' => array_sum(array_column($rows, 'cost')), 'profit' => array_sum(array_column($rows, 'profit')),
            'count' => array_sum(array_column($rows, 'count')), 'estimated' => (bool) array_filter(array_column($rows, 'estimated')),
        ]];
    }

    /** Sales of one customer (or all when $customerId is null). customerId 0 = walk-in. */
    public function saleRows(int $locationId, string $from, string $toExclusive, ?int $customerId): array
    {
        $b = $this->db->table('sales s')
            ->select('s.id,s.sale_no,s.transaction_at,s.transaction_type,s.total_amount,s.customer_id,c.name customer_name')
            ->join('customers c', 'c.id=s.customer_id', 'left')
            ->where(['s.location_id' => $locationId, 's.status' => 'posted'])
            ->where(SalesService::revenueSaleCondition('s'), null, false)
            ->where('s.transaction_at >=', $from . ' 00:00:00')->where('s.transaction_at <', $toExclusive . ' 00:00:00')
            ->orderBy('s.transaction_at', 'DESC')->orderBy('s.id', 'DESC');
        if ($customerId !== null) {
            $customerId === 0 ? $b->where('s.customer_id IS NULL', null, false) : $b->where('s.customer_id', $customerId);
        }
        $sales = $b->get()->getResultArray();
        if (!$sales) return [];
        $ids = array_map(static fn($s) => (int) $s['id'], $sales);
        $items = [];
        foreach ($this->db->table('sale_items')->whereIn('sale_id', $ids)->get()->getResultArray() as $it) $items[(int) $it['sale_id']][] = $it;
        $legacy = null;
        $out = [];
        foreach ($sales as $s) {
            $cost = 0.0;
            $est = false;
            foreach ($items[(int) $s['id']] ?? [] as $it) {
                if ($it['cost_amount'] !== null) {
                    $cost += (float) $it['cost_amount'];
                } else {
                    $legacy ??= $this->legacyAverages($locationId);
                    $est = true;
                    $cost += $this->legacyItemCost($it, (string) $s['transaction_type'], $legacy);
                }
            }
            $rev = (float) $s['total_amount'];
            $out[] = $s + ['revenue' => $rev, 'cost' => $cost, 'profit' => $rev - $cost, 'estimated' => $est];
        }
        return $out;
    }

    public function legacyItemCost(array $it, string $saleType, array $legacy): float
    {
        $c = (float) $it['gas_weight_kg'] * $legacy['gas'];
        if ($saleType === 'cylinder_sale') $c += (float) $it['quantity'] * ($legacy['cyl'][(int) $it['cylinder_type_id']] ?? 0.0);
        return $c;
    }

    /** One sale with its lines, the cost applied to each and the lots the cost came from. */
    public function saleDetail(int $locationId, int $saleId): ?array
    {
        $s = $this->db->table('sales s')->select('s.*,c.name customer_name')->join('customers c', 'c.id=s.customer_id', 'left')->where(['s.id' => $saleId, 's.location_id' => $locationId])->get()->getRowArray();
        if (!$s) return null;
        $legacy = null;
        $lines = [];
        foreach ($this->db->table('sale_items si')->select('si.*,ct.name type_name')->join('cylinder_types ct', 'ct.id=si.cylinder_type_id', 'left')->where('si.sale_id', $saleId)->orderBy('si.line_no')->get()->getResultArray() as $it) {
            $est = $it['cost_amount'] === null;
            if ($est) {
                $legacy ??= $this->legacyAverages($locationId);
                $cost = $this->legacyItemCost($it, (string) $s['transaction_type'], $legacy);
                $lots = [];
            } else {
                $cost = (float) $it['cost_amount'];
                $lots = [];
                foreach ($this->db->query("SELECT u.kind,u.qty,u.unit_cost,u.amount,l.source_type,l.source_id,l.received_at,p.purchase_no FROM cost_layer_usage u LEFT JOIN cost_layers l ON l.id=u.layer_id LEFT JOIN purchases p ON l.source_type='purchase' AND p.id=l.source_id WHERE u.sale_item_id=? ORDER BY u.id", [(int) $it['id']])->getResultArray() as $u) {
                    $lots[] = ['kind' => $u['kind'], 'qty' => (float) $u['qty'], 'unit_cost' => (float) $u['unit_cost'], 'amount' => (float) $u['amount'],
                        'source' => $u['source_type'] === 'purchase' ? 'Purchase ' . $u['purchase_no'] : ($u['source_type'] === 'opening' ? 'Opening stock' : 'Latest known cost'), 'at' => $u['received_at']];
                }
            }
            $kindLabel = ['refill_kg' => 'Gas', 'filled_cylinder' => 'Filled cylinder', 'empty_cylinder' => 'Empty cylinder'][$it['line_type']] ?? $it['line_type'];
            $lines[] = [
                'item' => $kindLabel . ($it['type_name'] ? ' · ' . $it['type_name'] : ''), 'quantity' => (float) $it['quantity'], 'gas_kg' => (float) $it['gas_weight_kg'],
                'rate' => (float) $it['applied_rate'], 'line_total' => (float) $it['line_total'], 'cost' => $cost, 'profit' => (float) $it['line_total'] - $cost,
                'cost_gas' => $est ? null : (float) $it['cost_gas'], 'cost_cylinder' => $est ? null : (float) $it['cost_cylinder'], 'method' => $it['cost_method'], 'estimated' => $est, 'lots' => $lots,
            ];
        }
        $cost = array_sum(array_column($lines, 'cost'));
        return ['sale' => ['id' => (int) $s['id'], 'sale_no' => $s['sale_no'], 'at' => $s['transaction_at'], 'type' => $s['transaction_type'], 'customer' => $s['customer_name'] ?: 'Walk-in / Cash', 'total' => (float) $s['total_amount']],
            'lines' => $lines, 'cost' => $cost, 'profit' => (float) $s['total_amount'] - $cost];
    }
}
