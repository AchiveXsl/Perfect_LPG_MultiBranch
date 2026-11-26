<?php
/**
 * Top-bar System Date of the current branch (owner decision 2026-11-26), shown after the branch switch.
 * Every transaction of the branch takes this date (with the clock time). Amber when it is not today.
 * Users with SYSTEM_DATE_CHANGE can change it; it stays until changed again.
 */
$locationId = \App\Services\BranchContext::currentId();
if ($locationId <= 0) {
    return;
}
$sysDate = \App\Services\BranchContext::today($locationId);
$stored = \App\Services\BranchContext::storedSystemDate($locationId);
$realToday = date('Y-m-d');
$state = $sysDate === $realToday ? 'today' : ($sysDate < $realToday ? 'past' : 'future');
$canChange = \App\Services\PermissionService::allows('SYSTEM_DATE_CHANGE');
$label = date('d-M-Y', strtotime($sysDate));
$btnClass = $state === 'today' ? 'btn-outline-secondary' : 'btn-warning';
$badge = $state === 'past' ? 'Back-dated' : ($state === 'future' ? 'Future' : '');
?>
<div class="dropdown" id="systemDateBox" data-state="<?= esc($state, 'attr') ?>">
    <button class="btn btn-sm <?= $btnClass ?> d-flex align-items-center gap-1 <?= $canChange ? 'dropdown-toggle' : '' ?>" type="button"
        <?= $canChange ? 'data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"' : 'disabled' ?>
        id="systemDateButton" title="System Date: every transaction of this branch is dated <?= esc($label, 'attr') ?>">
        <i class="bi bi-calendar-event"></i>
        <span id="systemDateLabel"><?= esc($label) ?></span>
        <?php if ($badge !== ''): ?><span class="badge text-bg-danger" id="systemDateBadge"><?= esc($badge) ?></span><?php endif; ?>
    </button>
    <?php if ($canChange): ?>
    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:290px" aria-labelledby="systemDateButton">
        <form method="post" action="<?= site_url('system-date/save') ?>" id="systemDateForm" class="m-0">
            <?= csrf_field() ?>
            <label class="form-label small mb-1" for="systemDateInput">System Date of this branch</label>
            <input type="date" class="form-control form-control-sm mb-2" name="system_date" id="systemDateInput" value="<?= esc($sysDate, 'attr') ?>" required>
            <div class="form-check small mb-2">
                <input class="form-check-input" type="checkbox" value="1" name="follow_today" id="systemDateFollow" <?= $stored === null ? 'checked' : '' ?>>
                <label class="form-check-label" for="systemDateFollow">Follow today's date automatically</label>
            </div>
            <div class="form-text mb-2">New sales, purchases, cash, stock and other entries of this branch are dated with this date and the current time. Screens and reports open on this date. It stays until changed.</div>
            <button type="submit" class="btn btn-sm btn-primary w-100" id="systemDateSave"><i class="bi bi-check2 me-1"></i>Save System Date</button>
        </form>
    </div>
    <?php endif; ?>
</div>
