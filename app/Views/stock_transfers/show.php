<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$status = [
    'in_transit' => '<span class="badge text-bg-warning">In transit</span>',
    'received' => '<span class="badge text-bg-success">Received</span>',
    'cancelled' => '<span class="badge text-bg-secondary">Cancelled</span>',
][$t['status']] ?? esc($t['status']);
$itemStatus = static fn(string $s): string => [
    'pending' => '<span class="badge text-bg-warning">On the way</span>',
    'received' => '<span class="badge text-bg-success">Received</span>',
    'missing' => '<span class="badge text-bg-danger">Missing — back at sender</span>',
    'returned' => '<span class="badge text-bg-secondary">Returned to sender</span>',
][$s] ?? esc($s);
$fmtAt = static fn(?string $at): string => $at ? date('d M Y H:i', strtotime($at)) : '—';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Transfer <?= esc($t['transfer_no']) ?> <?= $status ?></h4>
        <div class="text-muted"><?= esc($t['from_name']) ?> <i class="bi bi-arrow-right"></i> <?= esc($t['to_name']) ?></div>
    </div>
    <a class="btn btn-outline-secondary" href="<?= site_url('stock-transfers') ?>"><i class="bi bi-arrow-left me-1"></i>Transfers</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Dispatched</div><div class="fw-semibold"><?= esc($fmtAt($t['dispatched_at'])) ?></div><div class="small"><?= esc($t['dispatched_by_name'] ?? '') ?></div><?php if ($t['notes']): ?><div class="small text-muted mt-1"><?= esc($t['notes']) ?></div><?php endif; ?></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Received</div><div class="fw-semibold"><?= esc($fmtAt($t['received_at'])) ?></div><div class="small"><?= esc($t['received_by_name'] ?? '') ?></div><?php if ($t['receive_notes']): ?><div class="small text-muted mt-1"><?= esc($t['receive_notes']) ?></div><?php endif; ?></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Cancelled</div><div class="fw-semibold"><?= esc($fmtAt($t['cancelled_at'])) ?></div><div class="small"><?= esc($t['cancelled_by_name'] ?? '') ?></div><?php if ($t['cancel_reason']): ?><div class="small text-muted mt-1"><?= esc($t['cancel_reason']) ?></div><?php endif; ?></div></div></div>
</div>

<form method="post" action="<?= site_url('stock-transfers/receive/' . $t['id']) ?>" id="receiveForm">
    <?= csrf_field() ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header">
            <strong>Cylinders</strong>
            <?php if ($canReceive): ?><div class="small text-muted">Untick any cylinder that did not arrive: it goes back to <?= esc($t['from_name']) ?>'s stock. For filled cylinders, enter the gas actually found if it is less than what was sent.</div><?php endif; ?>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><?php if ($canReceive): ?><th style="width:40px">Arrived</th><?php endif; ?><th>Cylinder</th><th>Type</th><th>Sent as</th><th class="text-end">Gas sent (KG)</th><th class="text-end" style="width:170px">Gas received (KG)</th><th>Status</th></tr></thead>
                <tbody>
                <?php $sentKg = 0.0; $recvKg = 0.0; foreach ($t['items'] as $it): $sentKg += (float) $it['sent_gas_weight_kg']; $recvKg += (float) ($it['received_gas_weight_kg'] ?? 0); ?>
                    <tr>
                        <?php if ($canReceive): ?><td><input type="checkbox" class="form-check-input arrived" name="lines[<?= (int) $it['id'] ?>][received]" value="1" checked></td><?php endif; ?>
                        <td><?= esc($it['unit_code']) ?></td>
                        <td><?= esc($it['type_code'] . ' ' . $it['type_name']) ?></td>
                        <td><?= $it['sent_status'] === 'filled' ? 'Filled' : 'Empty' ?></td>
                        <td class="text-end"><?= $it['sent_status'] === 'filled' ? number_format((float) $it['sent_gas_weight_kg'], 3) : '—' ?></td>
                        <td class="text-end">
                            <?php if ($canReceive && $it['sent_status'] === 'filled'): ?>
                                <input type="number" class="form-control form-control-sm text-end gas-in" name="lines[<?= (int) $it['id'] ?>][gas]" min="0" max="<?= esc((string) $it['sent_gas_weight_kg'], 'attr') ?>" step="0.001" value="<?= esc((string) (float) $it['sent_gas_weight_kg'], 'attr') ?>">
                            <?php elseif ($it['received_gas_weight_kg'] !== null && $it['sent_status'] === 'filled'): ?>
                                <?= number_format((float) $it['received_gas_weight_kg'], 3) ?>
                                <?php $short = (float) $it['sent_gas_weight_kg'] - (float) $it['received_gas_weight_kg']; if ($it['receive_status'] === 'received' && $short > 0.0005): ?><div class="small text-danger">short <?= number_format($short, 3) ?></div><?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= $itemStatus($it['receive_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-semibold"><td colspan="<?= $canReceive ? 4 : 3 ?>"><?= count($t['items']) ?> cylinder(s)</td><td class="text-end"><?= number_format($sentKg, 3) ?></td><td class="text-end"><?= $t['status'] === 'received' ? number_format($recvKg, 3) : '' ?></td><td></td></tr></tfoot>
            </table>
        </div>
    </div>
    <?php if ($canReceive): ?>
    <div class="card shadow-sm mb-3"><div class="card-body row g-2 align-items-end">
        <?php $minReceive = max($earliestDate . 'T00:00', date('Y-m-d\TH:i', strtotime($t['dispatched_at']))); ?>
        <div class="col-md-3"><label class="form-label">Receive date</label><input type="datetime-local" name="receive_date" id="receiveDate" class="form-control" min="<?= esc($minReceive) ?>" max="<?= \App\Services\BranchContext::nowLocal() ?>" value="<?= \App\Services\BranchContext::nowLocal() ?>"><div class="form-text">Not before dispatch or <?= esc(date('d M Y', strtotime($earliestDate))) ?>.</div></div>
        <div class="col-md-5"><label class="form-label">Receiving notes</label><input name="receive_notes" class="form-control" maxlength="500"></div>
        <div class="col-md-4 text-end"><button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-in-down me-1"></i>Confirm Receipt</button></div>
    </div></div>
    <?php endif; ?>
</form>

<?php if ($canCancel): ?>
<form method="post" action="<?= site_url('stock-transfers/cancel/' . $t['id']) ?>" class="card shadow-sm border-danger-subtle" onsubmit="return confirm('Cancel this transfer? All cylinders return to this branch\'s stock.');">
    <?= csrf_field() ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-8"><label class="form-label">Cancel transfer — reason</label><input name="reason" class="form-control" maxlength="255" required></div>
        <div class="col-md-4 text-end"><button class="btn btn-outline-danger" type="submit"><i class="bi bi-x-circle me-1"></i>Cancel Transfer</button></div>
    </div>
    <div class="card-footer small text-muted">Only possible until <?= esc($t['to_name']) ?> receives it.</div>
</form>
<?php endif; ?>

<?php if ($canReceive): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#receiveForm tr').forEach(row => {
        const box = row.querySelector('.arrived'), gas = row.querySelector('.gas-in');
        if (box && gas) box.addEventListener('change', () => { gas.disabled = !box.checked; });
    });
    document.getElementById('receiveForm').addEventListener('submit', e => {
        const missing = document.querySelectorAll('#receiveForm .arrived:not(:checked)').length;
        const msg = missing ? missing + ' cylinder(s) are not ticked and will be returned to the sending branch as missing. Continue?' : 'Confirm that all cylinders arrived?';
        if (!confirm(msg)) e.preventDefault();
    });
});
</script>
<?php endif; ?>
<?= $this->endSection() ?>
