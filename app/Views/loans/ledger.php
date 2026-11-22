<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Lender Ledger — <?= esc($lender['name']) ?></h4>
        <div class="text-muted small"><?= esc(trim(($lender['phone'] ?? '') . ' ' . ($lender['cnic'] ? 'CNIC ' . $lender['cnic'] : ''))) ?></div>
    </div>
    <div class="text-end"><div class="small text-muted">Still owed</div><div class="fs-4 fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>" id="ledgerBalance"><?= number_format($balance, 2) ?></div></div>
</div>
<div class="card shadow-sm"><div class="card-body table-responsive">
    <?= view('loans/_entries', ['entries' => $entries, 'showLender' => false, 'running' => true, 'opening' => (float) $lender['opening_balance']]) ?>
</div></div>
<a href="<?= site_url('loans') ?>" class="btn btn-light mt-3"><i class="bi bi-arrow-left me-1"></i>Back to Loans</a>
<?= $this->endSection() ?>
