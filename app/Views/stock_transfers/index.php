<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$badge = static fn(string $s): string => [
    'in_transit' => '<span class="badge text-bg-warning">In transit</span>',
    'received' => '<span class="badge text-bg-success">Received</span>',
    'cancelled' => '<span class="badge text-bg-secondary">Cancelled</span>',
][$s] ?? esc($s);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Stock Transfers</h4>
        <div class="text-muted small">Send filled or empty cylinders to another branch. The receiving branch confirms what arrived.</div>
    </div>
    <a class="btn btn-primary" href="<?= site_url('stock-transfers/new') ?>"><i class="bi bi-truck me-1"></i>New Transfer</a>
</div>

<?php if ($incoming): ?>
<div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-box-arrow-in-down fs-5"></i>
    <div><strong><?= count($incoming) ?> incoming transfer<?= count($incoming) === 1 ? '' : 's' ?></strong> waiting to be received at this branch. Open each one and confirm what arrived.</div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Transfer No.</th><th>Dispatched</th><th>Direction</th><th>From → To</th><th class="text-end">Filled</th><th class="text-end">Empty</th><th class="text-end">Gas KG</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No transfers yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $t): $out = (int) $t['from_location_id'] === (int) $currentId; ?>
                <tr class="<?= $t['status'] === 'in_transit' && !$out ? 'table-warning' : '' ?>">
                    <td><strong><?= esc($t['transfer_no']) ?></strong></td>
                    <td class="small"><?= esc(date('d M Y H:i', strtotime($t['dispatched_at']))) ?><div class="text-muted"><?= esc($t['dispatched_by_name'] ?? '') ?></div></td>
                    <td><?= $out ? '<span class="badge text-bg-light border"><i class="bi bi-arrow-up-right me-1"></i>Outgoing</span>' : '<span class="badge text-bg-light border"><i class="bi bi-arrow-down-left me-1"></i>Incoming</span>' ?></td>
                    <td><?= esc($t['from_name']) ?> → <?= esc($t['to_name']) ?></td>
                    <td class="text-end"><?= (int) $t['filled_count'] ?></td>
                    <td class="text-end"><?= (int) $t['empty_count'] ?></td>
                    <td class="text-end"><?= number_format((float) $t['gas_kg'], 3) ?></td>
                    <td><?= $badge($t['status']) ?><?php if ((int) $t['missing_count'] > 0): ?> <span class="badge text-bg-danger"><?= (int) $t['missing_count'] ?> missing</span><?php endif; ?></td>
                    <td class="text-end"><a class="btn btn-sm <?= $t['status'] === 'in_transit' && !$out ? 'btn-warning' : 'btn-outline-secondary' ?>" href="<?= site_url('stock-transfers/' . $t['id']) ?>"><?= $t['status'] === 'in_transit' && !$out ? 'Receive' : 'View' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
