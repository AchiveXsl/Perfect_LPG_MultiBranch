<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $kg = static fn($v) => number_format((float) $v, 3); ?>
<div class="mb-3">
    <h4 class="mb-1">Gas Decanting</h4>
    <div class="text-muted small">Move gas from one filled cylinder into empty or part-filled cylinders, e.g. from a 45 kg cylinder into 2 × 10 kg cylinders. Total gas stock does not change.</div>
</div>

<?php if (!$sources): ?>
    <div class="alert alert-info">There is no filled cylinder in this branch's stock to take gas from.</div>
<?php else: ?>
<form method="post" action="<?= site_url('inventory/decant/save') ?>" id="decantForm">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>1. Take gas from</strong></div>
                <div class="card-body">
                    <label class="form-label">Source cylinder (filled)</label>
                    <select name="source_unit_id" id="decantSource" class="form-select" required>
                        <option value="">Select cylinder</option>
                        <?php foreach ($sources as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" data-gas="<?= esc((string) (float) $s['gas_weight_kg'], 'attr') ?>" <?= (int) old('source_unit_id') === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= esc($s['unit_code']) ?> · <?= esc($s['type_name']) ?> · <?= $kg($s['gas_weight_kg']) ?> KG
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="row g-2 mt-3 text-center">
                        <div class="col-4"><div class="small text-muted">Holds</div><div class="fs-5 fw-semibold" id="decantHolds">0.000</div></div>
                        <div class="col-4"><div class="small text-muted">Moving</div><div class="fs-5 fw-semibold text-primary" id="decantMoving">0.000</div></div>
                        <div class="col-4"><div class="small text-muted">Left after</div><div class="fs-5 fw-semibold" id="decantLeft">0.000</div></div>
                    </div>
                    <div class="alert alert-danger py-2 mt-3 d-none" id="decantOver">More gas entered than the source cylinder holds.</div>
                    <label class="form-label mt-3">Notes</label>
                    <input name="notes" class="form-control" maxlength="500" placeholder="e.g. 2 × 10 kg for customer Ali" value="<?= esc(old('notes') ?? '') ?>">
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>2. Put gas into</strong><div class="small text-muted">Tick the cylinders to fill. The KG defaults to each cylinder's free room; change it for a part fill.</div></div>
                <div class="card-body table-responsive" style="max-height:420px;overflow:auto">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th style="width:36px"></th><th>Cylinder</th><th>Type</th><th class="text-end">Now KG</th><th class="text-end">Room KG</th><th style="width:130px" class="text-end">KG to put in</th></tr></thead>
                        <tbody>
                        <?php foreach ($targets as $t): ?>
                            <tr data-id="<?= (int) $t['id'] ?>">
                                <td><input type="checkbox" class="form-check-input decant-pick"></td>
                                <td><?= esc($t['unit_code']) ?></td>
                                <td><?= esc($t['type_name']) ?></td>
                                <td class="text-end"><?= $kg($t['gas_weight_kg']) ?></td>
                                <td class="text-end"><?= $kg($t['room_kg']) ?></td>
                                <td><input type="number" class="form-control form-control-sm text-end decant-kg" name="targets[<?= (int) $t['id'] ?>]" min="0" max="<?= esc((string) $t['room_kg'], 'attr') ?>" step="0.001" data-room="<?= esc((string) $t['room_kg'], 'attr') ?>" disabled></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$targets): ?><tr><td colspan="6" class="text-muted text-center py-3">No empty or part-filled cylinder in stock.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end mt-3">
        <button class="btn btn-primary" type="submit" id="decantSave"><i class="bi bi-arrow-down-up me-1"></i>Move Gas</button>
    </div>
</form>
<?php endif; ?>

<div class="card shadow-sm mt-4">
    <div class="card-header"><strong>Recent decanting</strong></div>
    <div class="card-body table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>No.</th><th>Date</th><th>From</th><th class="text-end">KG moved</th><th class="text-end">Source after</th><th>Into</th><th>By</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($r['decant_no']) ?></td>
                    <td class="small"><?= esc(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
                    <td><?= esc($r['source_code']) ?> <span class="text-muted small">(<?= $kg($r['source_before_kg']) ?>)</span></td>
                    <td class="text-end"><?= $kg($r['total_kg']) ?></td>
                    <td class="text-end"><?= $kg($r['source_after_kg']) ?></td>
                    <td class="small"><?php foreach ($r['items'] as $it): ?><div><?= esc($it['unit_code']) ?>: +<?= $kg($it['kg']) ?> → <?= $kg($it['after_kg']) ?></div><?php endforeach; ?></td>
                    <td class="small"><?= esc($r['created_by_name'] ?? '') ?></td>
                    <td class="small"><?= esc($r['notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="8" class="text-muted text-center py-3">No decanting yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('decantForm');
    if (!form) return;
    const src = document.getElementById('decantSource');
    const f = v => Number(v || 0).toFixed(3);
    const refresh = () => {
        const holds = Number(src.selectedOptions[0]?.dataset.gas || 0);
        let moving = 0;
        form.querySelectorAll('tbody tr[data-id]').forEach(tr => {
            const pick = tr.querySelector('.decant-pick'), kg = tr.querySelector('.decant-kg');
            // The source cannot also be a target.
            const isSource = tr.dataset.id === src.value;
            pick.disabled = isSource; if (isSource) pick.checked = false;
            kg.disabled = !pick.checked;
            if (pick.checked) moving += Number(kg.value || 0);
        });
        document.getElementById('decantHolds').textContent = f(holds);
        document.getElementById('decantMoving').textContent = f(moving);
        document.getElementById('decantLeft').textContent = f(Math.max(0, holds - moving));
        const over = moving > holds + 0.0005;
        document.getElementById('decantOver').classList.toggle('d-none', !over);
        document.getElementById('decantSave').disabled = over || moving <= 0 || !src.value;
    };
    form.querySelectorAll('.decant-pick').forEach(pick => pick.addEventListener('change', () => {
        const kg = pick.closest('tr').querySelector('.decant-kg');
        if (pick.checked && !Number(kg.value)) {
            // Default: fill to free room, limited by the gas still available in the source.
            const holds = Number(src.selectedOptions[0]?.dataset.gas || 0);
            let used = 0; form.querySelectorAll('.decant-pick:checked').forEach(p => { if (p !== pick) used += Number(p.closest('tr').querySelector('.decant-kg').value || 0); });
            kg.value = Math.max(0, Math.min(Number(kg.dataset.room), holds - used)).toFixed(3);
        }
        if (!pick.checked) kg.value = '';
        refresh();
    }));
    form.addEventListener('input', refresh);
    src.addEventListener('change', refresh);
    form.addEventListener('submit', e => { if (!confirm('Move ' + document.getElementById('decantMoving').textContent + ' KG of gas?')) e.preventDefault(); });
    refresh();
});
</script>
<?= $this->endSection() ?>
