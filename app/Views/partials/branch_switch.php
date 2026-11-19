<?php
/**
 * Top-bar branch indicator. With more than one branch it is a dropdown that switches the session branch.
 *
 * @var array $userBranches     branches the user may enter, keyed by id
 * @var int   $currentBranchId
 * @var string $branchName
 */
$userBranches = $userBranches ?? [];
$current = $userBranches[$currentBranchId] ?? null;
$label = $current['name'] ?? $branchName;
?>
<?php if (count($userBranches) > 1): ?>
<div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="branchSwitch" title="Switch branch">
        <i class="bi <?= (int) ($current['is_head_office'] ?? 0) === 1 ? 'bi-building' : 'bi-shop' ?>"></i>
        <span class="text-truncate" style="max-width:180px"><?= esc($label) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="branchSwitch" style="min-width:260px">
        <li><h6 class="dropdown-header">Switch branch</h6></li>
        <?php foreach ($userBranches as $b): $isCurrent = (int) $b['id'] === (int) $currentBranchId; ?>
        <li>
            <form method="post" action="<?= site_url('branches/enter') ?>" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="location_id" value="<?= (int) $b['id'] ?>">
                <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center gap-2 <?= $isCurrent ? 'active' : '' ?>" <?= $isCurrent ? 'disabled aria-current="true"' : '' ?>>
                    <span><?= esc($b['name']) ?><?php if ((int) $b['is_head_office'] === 1): ?> <span class="badge text-bg-warning ms-1">HO</span><?php endif; ?></span>
                    <span class="small <?= $isCurrent ? '' : 'text-muted' ?>"><?= esc($b['role_code']) ?></span>
                </button>
            </form>
        </li>
        <?php endforeach; ?>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item small" href="<?= site_url('branches/select') ?>"><i class="bi bi-grid me-1"></i>All branch cards</a></li>
    </ul>
</div>
<?php else: ?>
<span class="small"><?= esc($label) ?></span>
<?php endif; ?>
