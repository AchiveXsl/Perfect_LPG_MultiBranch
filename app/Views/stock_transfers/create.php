<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">New Stock Transfer</h4>
        <div class="text-muted small">From <strong><?= esc($from['name'] ?? '') ?></strong>. The cylinders leave this branch's stock now and join the receiving branch when it confirms them.</div>
    </div>
    <a class="btn btn-outline-secondary" href="<?= site_url('stock-transfers') ?>"><i class="bi bi-arrow-left me-1"></i>Transfers</a>
</div>

<?php if (!$destinations): ?>
<div class="alert alert-info">There is no other active branch to send stock to. Create a branch first in Configuration → Branches.</div>
<?php else: ?>
<form method="post" action="<?= site_url('stock-transfers/dispatch') ?>" id="transferForm">
    <?= csrf_field() ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label">Send to branch</label>
                <select name="to_location_id" class="form-select" required>
                    <option value="">Select branch</option>
                    <?php foreach ($destinations as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= (int) old('to_location_id') === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['name']) ?><?= (int) $d['is_head_office'] === 1 ? ' (Head Office)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-7">
                <label class="form-label">Notes</label>
                <input name="notes" class="form-control" maxlength="500" placeholder="Vehicle, driver, reason…" value="<?= esc(old('notes') ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Cylinders to send</strong><div class="small text-muted">Enter how many filled and empty cylinders of each type to send. Full cylinders are sent first. To choose exact filled cylinders (for example part-used ones), open the list and tick them.</div></div>
        <div class="card-body table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Cylinder type</th><th class="text-end">Filled in stock</th><th class="text-end">Gas in stock (KG)</th><th style="width:140px">Send filled</th><th class="text-end">Empty in stock</th><th style="width:140px">Send empty</th></tr></thead>
                <tbody>
                <?php foreach ($types as $t):
                    $tid = (int) $t['id'];
                    $filled = $unitsByType[$tid]['filled'] ?? [];
                    $empty = $unitsByType[$tid]['empty'] ?? [];
                    if (!$filled && !$empty) continue;
                    $gas = array_sum(array_map(static fn($u) => (float) $u['gas_weight_kg'], $filled));
                ?>
                    <tr>
                        <td><strong><?= esc($t['code']) ?></strong> <?= esc($t['name']) ?>
                            <?php if ($filled): ?><div><a class="small" data-bs-toggle="collapse" href="#units<?= $tid ?>">Choose exact filled cylinders</a></div><?php endif; ?>
                        </td>
                        <td class="text-end"><?= count($filled) ?></td>
                        <td class="text-end"><?= number_format($gas, 3) ?></td>
                        <td><input type="number" class="form-control text-end qty-filled" name="qty[<?= $tid ?>][filled]" min="0" max="<?= count($filled) ?>" step="1" value="0" data-type="<?= $tid ?>"></td>
                        <td class="text-end"><?= count($empty) ?></td>
                        <td><input type="number" class="form-control text-end" name="qty[<?= $tid ?>][empty]" min="0" max="<?= count($empty) ?>" step="1" value="0"></td>
                    </tr>
                    <?php if ($filled): ?>
                    <tr class="collapse" id="units<?= $tid ?>"><td colspan="6" class="bg-light">
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($filled as $u): $partial = (float) $u['gas_weight_kg'] < (float) $t['capacity_kg'] - 0.0005; ?>
                                <label class="border rounded px-2 py-1 bg-white small">
                                    <input type="checkbox" class="form-check-input me-1 pick-unit" name="unit_ids[]" value="<?= (int) $u['id'] ?>" data-type="<?= $tid ?>">
                                    <?= esc($u['unit_code']) ?> · <?= number_format((float) $u['gas_weight_kg'], 3) ?> KG<?= $partial ? ' <span class="badge text-bg-info">part</span>' : '' ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="small text-muted mt-1">Ticked cylinders replace the filled quantity for this type.</div>
                    </td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$unitsByType): ?><div class="text-muted text-center py-3">This branch has no filled or empty cylinders in stock.</div><?php endif; ?>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2">
        <span class="align-self-center text-muted" id="transferSummary"></span>
        <button class="btn btn-primary" type="submit"><i class="bi bi-truck me-1"></i>Dispatch Transfer</button>
    </div>
</form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('transferForm');
    if (!form) return;
    const summary = document.getElementById('transferSummary');
    const refresh = () => {
        let filled = 0, empty = 0;
        form.querySelectorAll('.qty-filled').forEach(input => {
            const picked = form.querySelectorAll('.pick-unit[data-type="' + input.dataset.type + '"]:checked').length;
            // Ticked cylinders replace the quantity for that type, so they are never sent twice.
            input.disabled = picked > 0;
            if (picked > 0) input.value = picked;
            filled += Number(input.value || 0);
        });
        form.querySelectorAll('input[name$="[empty]"]').forEach(input => { empty += Number(input.value || 0); });
        summary.textContent = filled + ' filled, ' + empty + ' empty';
    };
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('submit', e => {
        refresh();
        if (summary.textContent === '0 filled, 0 empty') { e.preventDefault(); alert('Choose at least one cylinder to send.'); return; }
        if (!confirm('Dispatch ' + summary.textContent + ' cylinder(s)? They leave this branch\'s stock now.')) e.preventDefault();
    });
    refresh();
});
</script>
<?= $this->endSection() ?>
