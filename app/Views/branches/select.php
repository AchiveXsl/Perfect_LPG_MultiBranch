<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Select Branch — Perfect LPG</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--brand:#06382F;--accent:#E3A01A;--page:#f4f6f9;--text:#172033;--muted:#64748b;--border:#e2e8f0}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--page);color:var(--text);min-height:100vh}
.top{background:var(--brand);color:#fff;padding:1rem 1.25rem}
.top .bi-fire{color:var(--accent)}
.wrap{max-width:1040px;margin:0 auto;padding:2rem 1rem 3rem}
.branch-card{display:block;width:100%;text-align:left;background:#fff;border:1px solid var(--border);border-radius:.8rem;padding:1.25rem;height:100%;transition:border-color .15s,box-shadow .15s,transform .15s;color:inherit}
.branch-card:hover,.branch-card:focus-visible{border-color:var(--accent);box-shadow:0 8px 24px rgba(15,23,42,.08);transform:translateY(-2px);outline:none}
.branch-card .icon{width:44px;height:44px;border-radius:.6rem;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--brand) 10%,#fff);color:var(--brand);font-size:1.3rem}
.branch-card.is-current{border-color:var(--brand);box-shadow:inset 0 0 0 1px var(--brand)}
.branch-card .name{font-weight:700;font-size:1.1rem}
.branch-card .meta{color:var(--muted);font-size:.875rem}
.badge-ho{background:color-mix(in srgb,var(--accent) 18%,#fff);color:#7a5200;border:1px solid color-mix(in srgb,var(--accent) 40%,#fff)}
</style>
</head>
<body>
<div class="top d-flex align-items-center justify-content-between">
  <div class="d-flex align-items-center gap-2"><i class="bi bi-fire fs-4"></i><strong>Perfect LPG</strong></div>
  <div class="d-flex align-items-center gap-3"><span class="small"><?= esc($fullName) ?></span><a class="btn btn-sm btn-outline-light" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></div>
</div>
<main class="wrap">
  <h1 class="h4 mb-1">Select a branch</h1>
  <p class="text-muted mb-4">You have access to <?= count($branches) ?> branches. The dashboard, stock, POS and cash will show the branch you pick. You can switch later from the top bar.</p>
  <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
  <?php if (!$branches): ?>
    <div class="alert alert-warning">No active branch is assigned to your account. Contact an administrator.</div>
  <?php endif; ?>
  <div class="row g-3">
    <?php foreach ($branches as $b): ?>
    <div class="col-sm-6 col-lg-4">
      <form method="post" action="<?= site_url('branches/enter') ?>" class="h-100">
        <?= csrf_field() ?>
        <input type="hidden" name="location_id" value="<?= (int) $b['id'] ?>">
        <button type="submit" class="branch-card <?= (int) $b['id'] === (int) $currentId ? 'is-current' : '' ?>" data-testid="branch-card-<?= esc($b['code'], 'attr') ?>">
          <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
            <div class="icon"><i class="bi <?= (int) $b['is_head_office'] === 1 ? 'bi-building' : 'bi-shop' ?>"></i></div>
            <div class="d-flex flex-column align-items-end gap-1">
              <?php if ((int) $b['is_head_office'] === 1): ?><span class="badge badge-ho">Head Office</span><?php endif; ?>
              <?php if ((int) $b['id'] === (int) $currentId): ?><span class="badge text-bg-success">Current</span><?php elseif ((int) ($b['is_default'] ?? 0) === 1): ?><span class="badge text-bg-light border">Default</span><?php endif; ?>
            </div>
          </div>
          <div class="name"><?= esc($b['name']) ?></div>
          <div class="meta mb-2"><?= esc($b['code']) ?><?= trim((string) ($b['city'] ?? '')) !== '' ? ' · ' . esc($b['city']) : '' ?></div>
          <?php if (trim((string) ($b['address'] ?? '')) !== ''): ?><div class="meta"><i class="bi bi-geo-alt me-1"></i><?= esc($b['address']) ?></div><?php endif; ?>
          <div class="meta mt-2"><i class="bi bi-person-badge me-1"></i>Your role: <strong><?= esc($b['role_name'] ?: $b['role_code']) ?></strong></div>
        </button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
