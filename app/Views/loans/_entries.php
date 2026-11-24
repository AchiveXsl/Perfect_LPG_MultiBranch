<?php
// Loan entries table with void. $entries, $showLender, optional $running (ledger: running balance from $opening).
$m = static fn($v) => number_format((float) $v, 2);
$running = $running ?? false;
$bal = (float) ($opening ?? 0);
?>
<table class="table table-sm align-middle mb-0 loan-entries">
    <thead><tr><th>No.</th><th>Date</th><?php if ($showLender): ?><th>Lender</th><?php endif; ?><th>Type</th><th>Mode</th><th>Reference / Notes</th><th>By</th><th class="text-end">Received</th><th class="text-end">Repaid</th><?php if ($running): ?><th class="text-end">Balance</th><?php endif; ?><th></th></tr></thead>
    <tbody>
    <?php if ($running): ?><tr class="text-muted"><td colspan="<?= $showLender ? 9 : 8 ?>">Opening balance</td><td class="text-end"><?= $m($bal) ?></td><td></td></tr><?php endif; ?>
    <?php foreach ($entries as $e): $voided = $e['status'] === 'voided'; $in = $e['entry_type'] === 'received';
        if ($running && !$voided) $bal += $in ? (float) $e['amount'] : -(float) $e['amount']; ?>
        <tr class="<?= $voided ? 'text-muted text-decoration-line-through' : '' ?>">
            <td class="text-nowrap"><?= esc($e['entry_no']) ?></td>
            <td class="text-nowrap small"><?= esc(date('d M Y H:i', strtotime($e['entry_at']))) ?><?php if (!empty($e['due_date'])): ?><div class="text-muted">Due <?= esc(date('d M Y', strtotime($e['due_date']))) ?></div><?php endif; ?></td>
            <?php if ($showLender): ?><td><?= esc($e['lender_name']) ?></td><?php endif; ?>
            <td><span class="badge <?= $in ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $in ? 'Loan received' : 'Repayment' ?></span><?php if ($voided): ?> <span class="badge text-bg-secondary" title="<?= esc($e['void_reason'] ?? '', 'attr') ?>">Voided</span><?php endif; ?></td>
            <td><?= esc($e['payment_mode']) ?></td>
            <td class="small"><?= esc(trim(($e['reference_no'] ?? '') . ' ' . ($e['notes'] ?? ''))) ?></td>
            <td class="small"><?= esc($e['created_by_name'] ?? '') ?></td>
            <td class="text-end"><?= $in ? $m($e['amount']) : '' ?></td>
            <td class="text-end"><?= $in ? '' : $m($e['amount']) ?></td>
            <?php if ($running): ?><td class="text-end fw-semibold"><?= $m($bal) ?></td><?php endif; ?>
            <td class="text-end">
                <?php if (!$voided): ?>
                <form method="post" action="<?= site_url('loans/void/' . (int) $e['id']) ?>" class="d-inline loan-void-form">
                    <?= csrf_field() ?><input type="hidden" name="void_reason">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Void</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$entries): ?><tr><td colspan="<?= ($showLender ? 10 : 9) + ($running ? 1 : 0) ?>" class="text-center text-muted py-3">No loan entries in this period.</td></tr><?php endif; ?>
    </tbody>
</table>
<script>
document.querySelectorAll('.loan-void-form').forEach(f => f.addEventListener('submit', e => {
    const reason = prompt('Reason for voiding this entry? Any cash will be reversed.');
    if (!reason || !reason.trim()) { e.preventDefault(); return; }
    f.querySelector('input[name="void_reason"]').value = reason.trim();
}));
</script>
