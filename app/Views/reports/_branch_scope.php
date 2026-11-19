<?php
/**
 * Branch picker for reports. Shown only to users with REPORT_ALL_BRANCHES and more than one branch.
 * Put it inside the report's GET form; it submits branches=current|all or branches[]=id.
 *
 * @var array $scope from Reports::scope()
 */
if (empty($scope['canAll'])) return;
$req = $scope['requested'];
$mode = is_array($req) ? 'pick' : ($req === 'all' ? 'all' : 'current');
$picked = is_array($req) ? array_map('intval', $req) : [];
$uid = 'bs' . substr(md5((string) mt_rand()), 0, 6);
?>
<div class="col-md-auto">
    <label class="form-label">Branches</label>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <select class="form-select" id="<?= $uid ?>Mode" style="min-width:170px">
            <option value="current" <?= $mode === 'current' ? 'selected' : '' ?>>This branch</option>
            <option value="all" <?= $mode === 'all' ? 'selected' : '' ?>>All branches</option>
            <option value="pick" <?= $mode === 'pick' ? 'selected' : '' ?>>Choose branches…</option>
        </select>
        <input type="hidden" name="branches" id="<?= $uid ?>Hidden" value="<?= $mode === 'pick' ? '' : esc($mode, 'attr') ?>" <?= $mode === 'pick' ? 'disabled' : '' ?>>
        <div id="<?= $uid ?>Pick" class="d-flex gap-2 flex-wrap <?= $mode === 'pick' ? '' : 'd-none' ?>">
            <?php foreach ($scope['branches'] as $b): ?>
                <label class="border rounded px-2 py-1 small bg-white"><input type="checkbox" class="form-check-input me-1" name="branches[]" value="<?= (int) $b['id'] ?>" <?= in_array((int) $b['id'], $picked, true) ? 'checked' : '' ?> <?= $mode === 'pick' ? '' : 'disabled' ?>><?= esc($b['name']) ?></label>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
(() => {
    const mode = document.getElementById('<?= $uid ?>Mode'), hidden = document.getElementById('<?= $uid ?>Hidden'), pick = document.getElementById('<?= $uid ?>Pick');
    mode.addEventListener('change', () => {
        const isPick = mode.value === 'pick';
        pick.classList.toggle('d-none', !isPick);
        pick.querySelectorAll('input').forEach(i => { i.disabled = !isPick; });
        hidden.disabled = isPick;
        hidden.value = isPick ? '' : mode.value;
    });
})();
</script>
