<?php
/**
 * Customer branch settings, part of the customer form.
 *
 * @var array|null $edit
 * @var array      $branches
 * @var int        $currentBranchId
 */
$scope = $edit['branch_scope'] ?? 'shared';
$creditScope = $edit['credit_scope'] ?? 'branch';
$home = (int) ($edit['home_location_id'] ?? 0) ?: (int) $currentBranchId;
$canPickBranch = \App\Services\PermissionService::allows('BRANCH_MANAGE');
?>
<div class="col-12"><hr class="my-1"><div class="fw-semibold">Branches</div></div>
<div class="col-md-4">
    <label class="form-label">Used at</label>
    <select name="branch_scope" class="form-select" id="customerBranchScope">
        <option value="shared" <?= $scope === 'shared' ? 'selected' : '' ?>>All branches (shared)</option>
        <option value="branch" <?= $scope === 'branch' ? 'selected' : '' ?>>Home branch only</option>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">Home branch</label>
    <select name="home_location_id" class="form-select" <?= $canPickBranch ? '' : 'disabled' ?>>
        <?php foreach ($branches as $b): ?>
            <option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === $home ? 'selected' : '' ?>><?= esc($b['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (!$canPickBranch): ?><input type="hidden" name="home_location_id" value="<?= $home ?>"><?php endif; ?>
    <div class="form-text">The opening balance belongs to this branch.</div>
</div>
<div class="col-md-4">
    <label class="form-label">Credit limit checked against</label>
    <select name="credit_scope" class="form-select">
        <option value="branch" <?= $creditScope === 'branch' ? 'selected' : '' ?>>This branch's balance</option>
        <option value="combined" <?= $creditScope === 'combined' ? 'selected' : '' ?>>All branches combined</option>
    </select>
    <div class="form-text">Combined: OS at other branches also uses the limit.</div>
</div>
