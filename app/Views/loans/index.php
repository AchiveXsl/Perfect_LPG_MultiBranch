<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $m = static fn($v) => number_format((float) $v, 2); $owedTotal = array_sum(array_filter($balances, static fn($b) => $b > 0)); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Loans</h4>
        <div class="text-muted small">Money this branch borrowed from people who are not customers or suppliers. A loan is not sales, profit or expense. Cash entries move the counter cash.</div>
    </div>
    <div class="text-end"><div class="small text-muted">Total owed by this branch</div><div class="fs-4 fw-bold text-danger" id="loanOwedTotal"><?= $m($owedTotal) ?></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <form method="post" action="<?= site_url('loans/save') ?>" class="card shadow-sm" id="loanEntryForm">
            <?= csrf_field() ?>
            <div class="card-header"><strong>Loan Received / Repayment</strong></div>
            <div class="card-body">
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="entry_type" id="etReceived" value="received" <?= old('entry_type', 'received') === 'received' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-success" for="etReceived"><i class="bi bi-box-arrow-in-down me-1"></i>Loan Received</label>
                    <input type="radio" class="btn-check" name="entry_type" id="etRepayment" value="repayment" <?= old('entry_type') === 'repayment' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-danger" for="etRepayment"><i class="bi bi-box-arrow-up me-1"></i>Repayment</label>
                </div>
                <label class="form-label">Lender</label>
                <select name="lender_id" id="loanLender" class="form-select" required>
                    <option value="">Select lender</option>
                    <?php foreach ($lenders as $l): if ((int) $l['is_active'] !== 1) continue; ?>
                        <option value="<?= (int) $l['id'] ?>" data-owed="<?= esc((string) $balances[(int) $l['id']], 'attr') ?>" <?= (int) old('lender_id') === (int) $l['id'] ? 'selected' : '' ?>><?= esc($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="small mt-1">Owed now: <strong id="loanOwed">0.00</strong></div>
                <div class="row g-2 mt-1">
                    <div class="col-6"><label class="form-label">Amount</label><input name="amount" id="loanAmount" type="number" min=".01" step=".01" class="form-control" required value="<?= esc(old('amount') ?? '') ?>"></div>
                    <div class="col-6"><label class="form-label">Mode</label><select name="payment_mode" id="loanMode" class="form-select"><?php foreach (['cash' => 'Cash (counter)', 'cheque' => 'Cheque', 'online' => 'Online / Bank'] as $v => $t): ?><option value="<?= $v ?>" <?= old('payment_mode') === $v ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
                    <div class="col-6" id="dueWrap"><label class="form-label">Due date <span class="text-muted small">(optional)</span></label><input name="due_date" type="date" class="form-control" value="<?= esc(old('due_date') ?? '') ?>"></div>
                    <div class="col-6"><label class="form-label">Reference</label><input name="reference_no" class="form-control" maxlength="100" value="<?= esc(old('reference_no') ?? '') ?>"></div>
                    <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="500" value="<?= esc(old('notes') ?? '') ?>"></div>
                </div>
                <?php if (!$cashOpen): ?><div class="alert alert-warning py-2 mt-3 mb-0 small">The counter cash session is closed: open it before a cash entry, or use cheque / online.</div><?php endif; ?>
                <button class="btn btn-primary w-100 mt-3" id="loanPost"><i class="bi bi-check2-circle me-1"></i>Post</button>
            </div>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Lenders</strong>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#lenderForm"><i class="bi bi-person-plus me-1"></i>Add Lender</button>
            </div>
            <div class="collapse <?= $edit || old('name') ? 'show' : '' ?>" id="lenderForm">
                <form method="post" action="<?= site_url('loans/lenders/save') ?>" class="card-body border-bottom bg-light">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required maxlength="150" value="<?= esc(old('name') ?? ($edit['name'] ?? '')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="40" value="<?= esc(old('phone') ?? ($edit['phone'] ?? '')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">CNIC</label><input name="cnic" class="form-control" maxlength="40" value="<?= esc(old('cnic') ?? ($edit['cnic'] ?? '')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Opening balance owed</label><input name="opening_balance" type="number" min="0" step=".01" class="form-control" value="<?= esc(old('opening_balance') ?? ($edit['opening_balance'] ?? '0')) ?>"><div class="form-text">Loan taken before using the system. Fixed once entries exist.</div></div>
                        <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" maxlength="255" value="<?= esc(old('address') ?? ($edit['address'] ?? '')) ?>"></div>
                        <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="500" value="<?= esc(old('notes') ?? ($edit['notes'] ?? '')) ?>"></div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="lenderActive" <?= (int) ($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="lenderActive">Active</label></div>
                            <div><?php if ($edit): ?><a href="<?= site_url('loans') ?>" class="btn btn-sm btn-light">Cancel</a><?php endif; ?> <button class="btn btn-sm btn-primary"><?= $edit ? 'Update Lender' : 'Save Lender' ?></button></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0" id="lenderTable">
                    <thead><tr><th>Lender</th><th>Phone</th><th class="text-end">Owed</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($lenders as $l): $b = $balances[(int) $l['id']]; ?>
                        <tr>
                            <td><?= esc($l['name']) ?><?php if ((int) $l['is_active'] !== 1): ?> <span class="badge text-bg-secondary">Inactive</span><?php endif; ?></td>
                            <td><?= esc($l['phone'] ?? '') ?></td>
                            <td class="text-end fw-semibold <?= $b > 0 ? 'text-danger' : 'text-muted' ?>"><?= $m($b) ?></td>
                            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('loans/ledger/' . (int) $l['id']) ?>">Ledger</a> <a class="btn btn-sm btn-outline-primary" href="<?= site_url('loans?edit=' . (int) $l['id']) ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$lenders): ?><tr><td colspan="4" class="text-center text-muted py-3">No lenders yet. Add one first.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-end gap-2">
        <strong>Loan History</strong>
        <form method="get" action="<?= site_url('loans') ?>" class="d-flex flex-wrap align-items-end gap-2">
            <div><label class="form-label small mb-0">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>"></div>
            <div><label class="form-label small mb-0">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>"></div>
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        </form>
    </div>
    <div class="card-body table-responsive">
        <?= view('loans/_entries', ['entries' => $entries, 'showLender' => true]) ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const lender = document.getElementById('loanLender'), owed = document.getElementById('loanOwed');
    const amount = document.getElementById('loanAmount'), due = document.getElementById('dueWrap');
    const refresh = () => {
        const o = Number(lender.selectedOptions[0]?.dataset.owed || 0);
        owed.textContent = o.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const repay = document.getElementById('etRepayment').checked;
        due.style.display = repay ? 'none' : '';
        // A repayment cannot be more than what is owed (checked again on the server).
        amount.max = repay ? Math.max(0, o).toFixed(2) : '';
    };
    lender.addEventListener('change', refresh);
    document.querySelectorAll('input[name="entry_type"]').forEach(r => r.addEventListener('change', refresh));
    refresh();
});
</script>
<?= $this->endSection() ?>
