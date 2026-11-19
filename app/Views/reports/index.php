<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php $multi = !empty($scope['multi']); $qs = static fn(array $extra = []): string => http_build_query(array_filter(['branches' => $scope['requested']] + $extra, static fn($v) => $v !== null && $v !== '')); ?>
<h4>Sales & Daily Reports <small class="text-muted"><?= esc($from) ?> to <?= esc($to) ?></small></h4>
<div class="text-muted small mb-2"><i class="bi bi-diagram-3 me-1"></i><?= esc($scope['label']) ?></div>
<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-md-2"><label class="form-label">From Date</label><input type="date" name="from" class="form-control" value="<?= esc($from) ?>"></div>
    <div class="col-md-2"><label class="form-label">To Date</label><input type="date" name="to" class="form-control" value="<?= esc($to) ?>"></div>
    <?= view('reports/_branch_scope', ['scope' => $scope]) ?>
    <div class="col-md-auto"><button class="btn btn-primary">Run Report</button></div>
    <div class="col-md-auto"><a class="btn btn-outline-secondary" href="<?= site_url('reports') ?>">Today</a></div>
</form>
<div class="row g-3">
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Sales</div><h3>Rs. <?= number_format((float) ($sales['total'] ?? 0), 2) ?></h3><div><?= (int) ($sales['count'] ?? 0) ?> posted transactions</div><div>Credit Rs. <?= number_format((float) ($sales['credit'] ?? 0), 2) ?></div></div></div>
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Purchases</div><h3>Rs. <?= number_format((float) ($purchases['total'] ?? 0), 2) ?></h3><div><?= (int) ($purchases['count'] ?? 0) ?> posted transactions</div><div>Credit Rs. <?= number_format((float) ($purchases['credit'] ?? 0), 2) ?></div><div class="small text-muted mt-1">Entered centrally at the Head Office</div></div></div>
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Sales by Payment Mode</div><?php foreach ($payments as $p): ?><div class="d-flex justify-content-between"><span><?= esc($p['payment_mode']) ?></span><strong>Rs. <?= number_format((float) $p['amount'], 2) ?></strong></div><?php endforeach; ?></div></div>
<div class="col-md-3"><div class="card card-body border-warning"><div class="text-muted">Security Deposits Held</div><h3>Rs. <?= number_format((float) $depositBalance, 2) ?></h3><div>Received in period: Rs. <?= number_format((float) ($deposits['held'] ?? 0), 2) ?></div><div>Refunded in period: Rs. <?= number_format((float) ($deposits['refunded'] ?? 0), 2) ?></div><div class="small text-muted mt-1">Refundable liability — not revenue</div></div></div>
</div>

<?php if ($multi): ?>
<div class="card mt-3"><div class="card-body"><h5 class="mb-3">By Branch</h5><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
<thead><tr><th>Branch</th><th class="text-end">Sales</th><th class="text-end">Transactions</th><th class="text-end">Received</th><th class="text-end">Credit / OS</th><th class="text-end">Expenses</th><th class="text-end" title="Purchase value of stock received at the branch">Stock received</th><th class="text-end">Deposits held</th></tr></thead>
<tbody>
<?php foreach ($byBranch as $b): ?>
<tr><td class="fw-semibold"><?= esc($b['name']) ?></td><td class="text-end">Rs. <?= number_format($b['sales'], 2) ?></td><td class="text-end"><?= (int) $b['sales_count'] ?></td><td class="text-end">Rs. <?= number_format($b['received'], 2) ?></td><td class="text-end">Rs. <?= number_format($b['credit'], 2) ?></td><td class="text-end">Rs. <?= number_format($b['expenses'], 2) ?></td><td class="text-end">Rs. <?= number_format($b['stock_received'], 2) ?></td><td class="text-end">Rs. <?= number_format($b['deposit_balance'], 2) ?></td></tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr class="fw-bold"><td>Total</td>
<?php foreach (['sales', 'sales_count', 'received', 'credit', 'expenses', 'stock_received', 'deposit_balance'] as $k): $v = array_sum(array_column($byBranch, $k)); ?>
<td class="text-end"><?= $k === 'sales_count' ? (int) $v : 'Rs. ' . number_format($v, 2) ?></td>
<?php endforeach; ?>
</tr></tfoot>
</table></div></div></div>
<?php endif; ?>

<div class="card mt-3"><div class="card-body"><h5 class="mb-3">Sales Detail</h5><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Sale No.</th><th>Date / Time</th><?php if ($multi): ?><th>Branch</th><?php endif; ?><th>Customer</th><th>Type</th><th class="text-end">Net Amount</th><th class="text-end">Received</th><th class="text-end">Credit / OS</th><th>User</th></tr></thead><tbody><?php if (empty($saleRows)): ?><tr><td colspan="<?= $multi ? 9 : 8 ?>" class="text-center text-muted py-4">No posted sales found for this period.</td></tr><?php else: foreach ($saleRows as $s): $received = max(0, (float) $s['total_amount'] - (float) $s['credit_amount']); ?><tr><td class="fw-semibold"><?= esc($s['sale_no']) ?></td><td><?= esc($s['transaction_at']) ?></td><?php if ($multi): ?><td><?= esc($s['branch_name']) ?></td><?php endif; ?><td><?= esc($s['customer_name'] ?? 'Walk-in / Cash') ?></td><td><?= esc($s['transaction_type']) ?></td><td class="text-end">Rs. <?= number_format((float) $s['total_amount'], 2) ?></td><td class="text-end">Rs. <?= number_format($received, 2) ?></td><td class="text-end">Rs. <?= number_format((float) $s['credit_amount'], 2) ?></td><td><?= esc($s['created_by_name'] ?? '-') ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div>
<div class="mt-3 d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary" href="<?= site_url('inventory') ?>">Daily Stock</a><a class="btn btn-outline-success" href="<?= site_url('reports/inventory-detail?' . $qs()) ?>">Inventory Detail Report</a><a class="btn btn-outline-warning" href="<?= site_url('reports/custody?' . $qs()) ?>">Cylinder Custody & Deposits</a><a class="btn btn-outline-info" href="<?= site_url('reports/customer-balances?' . $qs()) ?>">Customer Balances by Branch</a></div>
<?= $this->endSection() ?>
