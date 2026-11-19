<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$names = $scope['names'];
$total = array_sum(array_column($rows, 'total'));
$branchTotals = [];
foreach ($rows as $r) foreach ($r['per'] as $loc => $v) $branchTotals[$loc] = ($branchTotals[$loc] ?? 0) + $v;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Customer Balances by Branch</h4>
        <div class="text-muted small">Outstanding (OS) per customer at each branch, and combined · <?= esc($scope['label']) ?>. Opening balances count at the customer's home branch. Security deposits are not included.</div>
    </div>
    <a class="btn btn-outline-secondary" href="<?= site_url('reports') ?>">Reports</a>
</div>
<?php if (!empty($scope['canAll'])): ?><form method="get" class="row g-2 align-items-end mb-3"><?= view('reports/_branch_scope', ['scope' => $scope]) ?><div class="col-md-auto"><button class="btn btn-primary">Show</button></div></form><?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card card-body"><div class="text-muted">Total customer OS</div><h3>Rs. <?= number_format($total, 2) ?></h3><div class="small"><?= count($rows) ?> customer(s) with a balance</div></div></div>
</div>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle datatable">
            <thead><tr><th>Customer</th><th>Phone</th><th>Scope</th><th class="text-end">Credit limit</th>
                <?php foreach ($names as $name): ?><th class="text-end"><?= esc($name) ?></th><?php endforeach; ?>
                <?php if (count($names) > 1): ?><th class="text-end">Combined</th><?php endif; ?><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $over = $r['credit_limit'] > 0 && $r['total'] > $r['credit_limit'] + 0.01; ?>
                <tr>
                    <td><strong><?= esc($r['name']) ?></strong><?= $r['code'] ? ' <span class="text-muted small">' . esc($r['code']) . '</span>' : '' ?></td>
                    <td><?= esc($r['phone'] ?? '') ?></td>
                    <td><?= $r['branch_scope'] === 'branch' ? '<span class="badge text-bg-light border">One branch</span>' : '<span class="badge text-bg-info">Shared</span>' ?><?= $r['credit_scope'] === 'combined' ? ' <span class="badge text-bg-warning">All-branch credit</span>' : '' ?></td>
                    <td class="text-end"><?= $r['credit_limit'] > 0 ? number_format($r['credit_limit'], 2) : '—' ?></td>
                    <?php foreach (array_keys($names) as $loc): $v = (float) ($r['per'][$loc] ?? 0); ?>
                        <td class="text-end <?= $v < -0.005 ? 'text-success' : '' ?>"><?= abs($v) < 0.005 ? '—' : number_format($v, 2) ?></td>
                    <?php endforeach; ?>
                    <?php if (count($names) > 1): ?><td class="text-end fw-semibold <?= $over ? 'text-danger' : '' ?>"><?= number_format($r['total'], 2) ?></td><?php endif; ?>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('reports/ledger/' . $r['id'] . '?' . http_build_query(['branches' => $scope['requested']])) ?>">Ledger</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold"><td colspan="4">Total</td>
                <?php foreach (array_keys($names) as $loc): ?><td class="text-end"><?= number_format((float) ($branchTotals[$loc] ?? 0), 2) ?></td><?php endforeach; ?>
                <?php if (count($names) > 1): ?><td class="text-end"><?= number_format($total, 2) ?></td><?php endif; ?><td></td></tr></tfoot>
        </table>
        <div class="small text-muted mt-2">Negative amounts (green) are advances paid by the customer.</div>
    </div>
</div>
<?= $this->endSection() ?>
