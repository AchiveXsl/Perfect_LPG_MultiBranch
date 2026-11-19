<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Branches</h4>
        <div class="text-muted small">Each branch has its own stock, POS, cash, rates and settings. Purchases are entered at the Head Office only.</div>
    </div>
    <a class="btn btn-primary" href="<?= site_url('branches?edit=new') ?>" data-bs-toggle="modal" data-bs-target="#branchForm"><i class="bi bi-plus-lg me-1"></i>Add Branch</a>
</div>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>City</th><th>Phone</th><th class="text-end">Assigned users</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($branches as $b): ?>
                <tr>
                    <td><strong><?= esc($b['code']) ?></strong></td>
                    <td><?= esc($b['name']) ?> <?php if ((int) $b['is_head_office'] === 1): ?><span class="badge text-bg-warning ms-1">Head Office</span><?php endif; ?></td>
                    <td><?= esc($b['city'] ?? '') ?></td>
                    <td><?= esc($b['phone'] ?? '') ?></td>
                    <td class="text-end"><?= (int) $b['user_count'] ?></td>
                    <td><?= (int) $b['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('branches?edit=' . $b['id']) ?>"><i class="bi bi-pencil me-1"></i>Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="small text-muted mt-2">Owners and admins (users with <em>All branches</em>) can enter every active branch, including new ones. Other users need the branch assigned in Users / Roles.</div>

<?php $e = is_array($edit) ? $edit : null; ?>
<div class="modal fade" id="branchForm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= site_url('branches/save') ?>" class="modal-content">
            <?= csrf_field() ?>
            <?php if ($e): ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><?php endif; ?>
            <div class="modal-header"><h5 class="modal-title"><?= $e ? 'Edit Branch' : 'Add Branch' ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase" maxlength="30" value="<?= esc(old('code', $e['code'] ?? '')) ?>" required></div>
                    <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" maxlength="120" value="<?= esc(old('name', $e['name'] ?? '')) ?>" required></div>
                    <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" maxlength="255" value="<?= esc(old('address', $e['address'] ?? '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">City</label><input name="city" class="form-control" maxlength="80" value="<?= esc(old('city', $e['city'] ?? '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="30" value="<?= esc(old('phone', $e['phone'] ?? '')) ?>"></div>
                    <div class="col-12 d-flex gap-4">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="branchActive" <?= !$e || (int) $e['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="branchActive">Active</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_head_office" value="1" id="branchHo" <?= $e && (int) $e['is_head_office'] ? 'checked disabled' : '' ?>><label class="form-check-label" for="branchHo">Head Office</label></div>
                        <?php if ($e && (int) $e['is_head_office']): ?><input type="hidden" name="is_head_office" value="1"><?php endif; ?>
                    </div>
                    <?php if (!$e): ?><div class="col-12 small text-muted">A new branch copies the Head Office shop settings and gets its own cash register. Set its LPG rates and opening stock after creating it.</div><?php endif; ?>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary"><?= $e ? 'Update Branch' : 'Create Branch' ?></button></div>
        </form>
    </div>
</div>
<?php if ($e || session()->getFlashdata('error')): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{const m=document.getElementById('branchForm');if(m)new bootstrap.Modal(m).show();});</script>
<?php endif; ?>

<?= $this->endSection() ?>
