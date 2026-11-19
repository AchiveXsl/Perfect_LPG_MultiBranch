<?php
namespace App\Controllers;

use App\Models\CustomerModel;
use App\Services\BranchContext;
use App\Services\PermissionService;
use App\Services\SalesService;
use CodeIgniter\Controller;
use Config\Database;

/**
 * Reports for the current branch, or (with REPORT_ALL_BRANCHES) for several branches combined.
 * Every transaction row carries its branch (location_id), so a combined report is the same query over a list
 * of branches, with a per-branch breakdown next to the total.
 */
class Reports extends Controller
{
    private function guard()
    {
        return PermissionService::allows('REPORT_VIEW') ? null : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    /**
     * Branches the report covers, from ?branches=current|all or ?branches[]=id.
     * @return array{ids:int[],names:array<int,string>,label:string,multi:bool,requested:string|array,canAll:bool,branches:array}
     */
    private function scope(): array
    {
        $requested = $this->request->getGet('branches') ?? 'current';
        $ids = BranchContext::reportScope($requested);
        $all = BranchContext::branchesFor((int) session()->get('user_id'));
        $names = [];
        foreach ($ids as $id) $names[$id] = $all[$id]['name'] ?? ('Branch ' . $id);
        $label = count($ids) === 1 ? reset($names) : (count($ids) === count($all) ? 'All branches' : implode(', ', $names));
        return [
            'ids' => $ids ?: [0],
            'names' => $names,
            'label' => (string) $label,
            'multi' => count($ids) > 1,
            'requested' => $requested,
            'canAll' => PermissionService::allows('REPORT_ALL_BRANCHES') && count($all) > 1,
            'branches' => array_values($all),
        ];
    }

    private function dateRange(): array
    {
        $today = date('Y-m-d');
        $from = trim((string) $this->request->getGet('from')) ?: $today;
        $to = trim((string) $this->request->getGet('to')) ?: $today;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $today;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = $today;
        if ($from > $to) [$from, $to] = [$to, $from];
        return [$today, $from, $to];
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $scope = $this->scope();
        $locs = $scope['ids'];
        [$today, $from, $to] = $this->dateRange();
        $start = $from . ' 00:00:00';
        $end = $to . ' 23:59:59';
        $revenue = SalesService::revenueSaleCondition();

        // Per-branch figures; the totals are their sums.
        $byBranch = [];
        foreach ($locs as $id) {
            $byBranch[$id] = ['name' => $scope['names'][$id] ?? '', 'sales' => 0.0, 'sales_count' => 0, 'credit' => 0.0, 'received' => 0.0, 'purchases' => 0.0, 'stock_received' => 0.0, 'deposit_held' => 0.0, 'deposit_refunded' => 0.0, 'deposit_balance' => 0.0, 'expenses' => 0.0];
        }
        foreach ($db->table('sales')->select('location_id, COUNT(*) n, SUM(total_amount) total, SUM(credit_amount) credit')
            ->whereIn('location_id', $locs)->where('status', 'posted')->where($revenue, null, false)
            ->where('transaction_at >=', $start)->where('transaction_at <=', $end)->groupBy('location_id')->get()->getResultArray() as $row) {
            $id = (int) $row['location_id'];
            $byBranch[$id]['sales'] = (float) $row['total'];
            $byBranch[$id]['sales_count'] = (int) $row['n'];
            $byBranch[$id]['credit'] = (float) $row['credit'];
            $byBranch[$id]['received'] = (float) $row['total'] - (float) $row['credit'];
        }
        foreach ($db->table('purchases')->select('location_id, SUM(total_amount) total')
            ->whereIn('location_id', $locs)->where('status', 'posted')
            ->where('transaction_at >=', $start)->where('transaction_at <=', $end)->groupBy('location_id')->get()->getResultArray() as $row) {
            $byBranch[(int) $row['location_id']]['purchases'] = (float) $row['total'];
        }
        // Purchase value of the stock each branch received (purchases are entered centrally at the Head Office).
        foreach ($db->query(
            "SELECT COALESCE(pi.receive_location_id,p.location_id) loc, SUM(pi.line_total) total
             FROM purchase_items pi JOIN purchases p ON p.id=pi.purchase_id
             WHERE p.status='posted' AND p.transaction_at BETWEEN ? AND ? AND COALESCE(pi.receive_location_id,p.location_id) IN (" . implode(',', array_map('intval', $locs)) . ")
             GROUP BY loc",
            [$start, $end]
        )->getResultArray() as $row) {
            if (isset($byBranch[(int) $row['loc']])) $byBranch[(int) $row['loc']]['stock_received'] = (float) $row['total'];
        }
        foreach ($db->table('customer_security_deposits')
            ->select("location_id, SUM(CASE WHEN entry_type='hold' AND transaction_at BETWEEN '{$start}' AND '{$end}' THEN amount ELSE 0 END) held, SUM(CASE WHEN entry_type='refund' AND transaction_at BETWEEN '{$start}' AND '{$end}' THEN amount ELSE 0 END) refunded, SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END) balance", false)
            ->whereIn('location_id', $locs)->groupBy('location_id')->get()->getResultArray() as $row) {
            $id = (int) $row['location_id'];
            $byBranch[$id]['deposit_held'] = (float) $row['held'];
            $byBranch[$id]['deposit_refunded'] = (float) $row['refunded'];
            $byBranch[$id]['deposit_balance'] = (float) $row['balance'];
        }
        foreach ($db->table('expenses')->select('location_id, SUM(amount) total')
            ->whereIn('location_id', $locs)->where('expense_at >=', $start)->where('expense_at <=', $end)
            ->groupBy('location_id')->get()->getResultArray() as $row) {
            $byBranch[(int) $row['location_id']]['expenses'] = (float) $row['total'];
        }
        $sum = static fn(string $k): float => array_sum(array_column($byBranch, $k));

        $saleRows = $db->table('sales s')
            ->select('s.id,s.sale_no,s.transaction_at,s.transaction_type,s.total_amount,s.credit_amount,s.location_id,l.name branch_name,c.name customer_name,u.full_name created_by_name')
            ->join('locations l', 'l.id=s.location_id')
            ->join('customers c', 'c.id=s.customer_id', 'left')->join('users u', 'u.id=s.created_by', 'left')
            ->whereIn('s.location_id', $locs)->where('s.status', 'posted')->where(SalesService::revenueSaleCondition('s'), null, false)
            ->where('s.transaction_at >=', $start)->where('s.transaction_at <=', $end)
            ->orderBy('s.transaction_at', 'DESC')->orderBy('s.id', 'DESC')->get()->getResultArray();
        $purchaseCredit = (float) ($db->table('purchases')->selectSum('credit_amount', 'credit')->whereIn('location_id', $locs)->where('status', 'posted')
            ->where('transaction_at >=', $start)->where('transaction_at <=', $end)->get()->getRowArray()['credit'] ?? 0);
        $purchaseCount = (int) $db->table('purchases')->whereIn('location_id', $locs)->where('status', 'posted')
            ->where('transaction_at >=', $start)->where('transaction_at <=', $end)->countAllResults();
        $payments = $db->table('sale_payments sp')->select('payment_mode,SUM(amount) amount')->join('sales s', 's.id=sp.sale_id')
            ->whereIn('s.location_id', $locs)->where('s.status', 'posted')->where('sp.payment_type', 'sale')->where(SalesService::revenueSaleCondition('s'), null, false)
            ->where('sp.payment_at >=', $start)->where('sp.payment_at <=', $end)->groupBy('payment_mode')->get()->getResultArray();

        return view('reports/index', [
            'title' => 'Reports',
            'scope' => $scope,
            'byBranch' => $byBranch,
            'sales' => ['total' => $sum('sales'), 'count' => (int) $sum('sales_count'), 'credit' => $sum('credit')],
            'saleRows' => $saleRows,
            'purchases' => ['total' => $sum('purchases'), 'count' => $purchaseCount, 'credit' => $purchaseCredit],
            'payments' => $payments,
            'today' => $today, 'from' => $from, 'to' => $to,
            'deposits' => ['held' => $sum('deposit_held'), 'refunded' => $sum('deposit_refunded')],
            'depositBalance' => $sum('deposit_balance'),
        ]);
    }

    public function custody()
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $scope = $this->scope();
        $rows = $db->table('cylinder_custody cc')
            ->select('cc.*,l.name branch_name,c.code customer_code,c.name customer_name,c.phone customer_phone,cu.unit_code,cu.gas_weight_kg,ct.code cylinder_code,ct.name cylinder_name')
            ->join('locations l', 'l.id=cc.location_id')
            ->join('customers c', 'c.id=cc.customer_id')->join('cylinder_units cu', 'cu.id=cc.cylinder_unit_id')->join('cylinder_types ct', 'ct.id=cu.cylinder_type_id')
            ->whereIn('cc.location_id', $scope['ids'])->where('cc.status', 'issued')
            ->orderBy('c.name')->orderBy('cu.unit_code')->get()->getResultArray();
        $totals = $db->table('customer_security_deposits')->select("SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END) balance")
            ->whereIn('location_id', $scope['ids'])->get()->getRowArray();
        return view('reports/custody', ['title' => 'Cylinder Custody & Security Deposits', 'scope' => $scope, 'rows' => $rows, 'depositBalance' => (float) ($totals['balance'] ?? 0)]);
    }

    public function inventoryDetail()
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $scope = $this->scope();
        $locs = $scope['ids'];
        $types = $db->table('cylinder_types')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray();
        $units = $db->table('cylinder_units')->select('id,location_id,cylinder_type_id,unit_code,status,gas_weight_kg,created_at,updated_at')
            ->whereIn('location_id', $locs)->whereIn('status', ['filled', 'empty'])->get()->getResultArray();

        $summary = [];
        $typeById = [];
        foreach ($types as $t) {
            $typeById[(int) $t['id']] = $t;
            $summary[(int) $t['id']] = ['id' => (int) $t['id'], 'code' => $t['code'], 'name' => $t['name'], 'capacity_kg' => (float) $t['capacity_kg'], 'filled_cylinders' => 0, 'empty_cylinders' => 0, 'filled_gas_kg' => 0.0, 'partial_cylinders' => 0];
        }
        $branchStock = [];
        foreach ($locs as $id) $branchStock[$id] = ['name' => $scope['names'][$id] ?? '', 'gas_kg' => 0.0, 'filled' => 0, 'empty' => 0];
        $partials = [];
        foreach ($units as $u) {
            $tid = (int) $u['cylinder_type_id'];
            if (!isset($summary[$tid])) continue;
            $loc = (int) $u['location_id'];
            if ($u['status'] === 'empty') {
                $summary[$tid]['empty_cylinders']++;
                $branchStock[$loc]['empty']++;
                continue;
            }
            $gas = (float) $u['gas_weight_kg'];
            $cap = (float) $typeById[$tid]['capacity_kg'];
            $summary[$tid]['filled_cylinders']++;
            $summary[$tid]['filled_gas_kg'] += $gas;
            $branchStock[$loc]['filled']++;
            $branchStock[$loc]['gas_kg'] += $gas;
            if ($gas > 0.00001 && $gas < $cap - 0.00001) {
                $summary[$tid]['partial_cylinders']++;
                $partials[] = ['unit_code' => $u['unit_code'], 'branch_name' => $scope['names'][$loc] ?? '', 'type_code' => $typeById[$tid]['code'], 'type_name' => $typeById[$tid]['name'], 'capacity_kg' => $cap, 'gas_weight_kg' => $gas, 'used_kg' => max(0, $cap - $gas), 'used_percent' => $cap > 0 ? max(0, min(100, (($cap - $gas) / $cap) * 100)) : 0, 'updated_at' => $u['updated_at'] ?? $u['created_at']];
            }
        }
        usort($partials, static fn($a, $b) => strcmp($a['type_code'] . '|' . $a['unit_code'], $b['type_code'] . '|' . $b['unit_code']));

        // Cylinders on the way between branches: owned by the company, in no branch's stock until received.
        $locList = implode(',', array_map('intval', $locs));
        $transit = $db->query(
            "SELECT COUNT(*) n, COALESCE(SUM(i.sent_gas_weight_kg),0) kg, SUM(i.sent_status='filled') filled, SUM(i.sent_status='empty') empty_n
             FROM stock_transfer_items i JOIN stock_transfers t ON t.id=i.transfer_id
             WHERE t.status='in_transit' AND i.receive_status='pending' AND (t.from_location_id IN ($locList) OR t.to_location_id IN ($locList))"
        )->getRowArray();

        return view('reports/inventory_detail', [
            'title' => 'Inventory Detail Report',
            'scope' => $scope,
            'summary' => array_values($summary),
            'partials' => $partials,
            'branchStock' => $branchStock,
            'transit' => $transit,
            'companyTotalGas' => array_sum(array_column($branchStock, 'gas_kg')),
            'companyFilled' => array_sum(array_column($branchStock, 'filled')),
            'companyEmpty' => array_sum(array_column($branchStock, 'empty')),
        ]);
    }

    /** Customer ledger for one branch, or combined across branches with the branch on every line. */
    public function ledger(int $customerId)
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $scope = $this->scope();
        $locs = $scope['ids'];
        $c = $db->table('customers')->where('id', $customerId)->get()->getRowArray();
        if (!$c || !array_filter($locs, static fn($id) => CustomerModel::isUsableAt($c, $id))) return $this->response->setStatusCode(404)->setBody('Customer not found');
        $sales = $db->table('sales s')->select('s.*, l.name branch_name')->join('locations l', 'l.id=s.location_id')
            ->where(['s.customer_id' => $customerId, 's.status' => 'posted'])->whereIn('s.location_id', $locs)->orderBy('s.transaction_at')->get()->getResultArray();
        $receipts = $db->table('customer_receipts r')->select('r.*, l.name branch_name')->join('locations l', 'l.id=r.location_id')
            ->where(['r.customer_id' => $customerId, 'r.status' => 'posted'])->whereIn('r.location_id', $locs)->orderBy('r.receipt_at')->get()->getResultArray();
        $events = [];
        foreach ($sales as $s) $events[] = ['date' => $s['transaction_at'], 'branch' => $s['branch_name'], 'type' => 'Sale OS', 'reference' => $s['sale_no'], 'debit' => (float) $s['credit_amount'], 'credit' => 0.0];
        foreach ($receipts as $rr) $events[] = ['date' => $rr['receipt_at'], 'branch' => $rr['branch_name'], 'type' => 'Receipt / OS Settlement', 'reference' => $rr['receipt_no'], 'debit' => 0.0, 'credit' => (float) $rr['amount']];
        usort($events, static fn($a, $b) => strcmp($a['date'] . '|' . $a['reference'], $b['date'] . '|' . $b['reference']));
        // The opening balance belongs to the home branch: it is included when the home branch is in the report.
        $opening = in_array(CustomerModel::homeOf($c), $locs, true) ? (float) $c['opening_balance'] : 0.0;
        $running = $opening;
        foreach ($events as &$e) {
            $running += (float) $e['debit'] - (float) $e['credit'];
            $e['running_balance'] = $running;
        }
        unset($e);
        $settings = (new \App\Models\ShopSettingsModel())->forLocation(BranchContext::currentId());
        $mode = $settings['credit_limit_validation_mode'] ?? 'none';
        $limit = $mode === 'shop' ? (float) ($settings['shop_credit_limit'] ?? 0) : (float) $c['credit_limit'];
        return view('reports/ledger', ['title' => 'Customer Ledger', 'scope' => $scope, 'customer' => $c, 'events' => $events, 'openingBalance' => $opening, 'creditLimitMode' => $mode, 'effectiveCreditLimit' => $limit, 'currentBalance' => $running]);
    }

    /**
     * Customer outstanding per branch and combined: one row per customer, one column per branch.
     * Opening balances count at each customer's home branch.
     */
    public function customerBalances()
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $scope = $this->scope();
        $locs = $scope['ids'];
        $balances = [];
        $add = static function (array &$balances, int $cid, int $loc, float $amount): void {
            $balances[$cid][$loc] = ($balances[$cid][$loc] ?? 0.0) + $amount;
        };
        foreach ($db->table('sales')->select('customer_id, location_id, SUM(credit_amount)+SUM(return_gas_ledger_amount) amt')
            ->whereIn('location_id', $locs)->where('status', 'posted')->where('customer_id IS NOT NULL', null, false)
            ->groupBy(['customer_id', 'location_id'])->get()->getResultArray() as $row) {
            $add($balances, (int) $row['customer_id'], (int) $row['location_id'], (float) $row['amt']);
        }
        foreach ($db->table('customer_receipts')->select('customer_id, location_id, SUM(amount) amt')
            ->whereIn('location_id', $locs)->where('status', 'posted')
            ->groupBy(['customer_id', 'location_id'])->get()->getResultArray() as $row) {
            $add($balances, (int) $row['customer_id'], (int) $row['location_id'], -(float) $row['amt']);
        }
        $customers = $db->table('customers')->orderBy('name')->get()->getResultArray();
        $rows = [];
        foreach ($customers as $c) {
            $cid = (int) $c['id'];
            $home = CustomerModel::homeOf($c);
            if ((float) $c['opening_balance'] != 0.0 && in_array($home, $locs, true)) $add($balances, $cid, $home, (float) $c['opening_balance']);
            $per = $balances[$cid] ?? [];
            $total = array_sum($per);
            if (abs($total) < 0.005 && !array_filter($per, static fn($v) => abs($v) >= 0.005)) continue;
            $rows[] = ['id' => $cid, 'code' => $c['code'], 'name' => $c['name'], 'phone' => $c['phone'], 'branch_scope' => $c['branch_scope'] ?? 'shared', 'credit_scope' => $c['credit_scope'] ?? 'branch', 'credit_limit' => (float) $c['credit_limit'], 'per' => $per, 'total' => $total];
        }
        usort($rows, static fn($a, $b) => $b['total'] <=> $a['total']);
        return view('reports/customer_balances', ['title' => 'Customer Balances by Branch', 'scope' => $scope, 'rows' => $rows]);
    }

    public function stock()
    {
        if ($r = $this->guard()) return $r;
        return redirect()->to('/inventory');
    }
}
