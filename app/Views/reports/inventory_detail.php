<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h4 class="mb-1">Inventory Detail Report</h4><div class="text-muted small">Current stock by cylinder type and physical cylinders that are partially used · <?= esc($scope['label']) ?></div></div>
  <a class="btn btn-outline-secondary" href="<?=site_url('reports')?>">Reports</a>
</div>
<?php if (!empty($scope['canAll'])): ?><form method="get" class="row g-2 align-items-end mb-3"><?= view('reports/_branch_scope', ['scope' => $scope]) ?><div class="col-md-auto"><button class="btn btn-primary">Show</button></div></form><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card card-body"><div class="text-muted">Gas Stock</div><h3><?=number_format((float)$companyTotalGas,2)?> KG</h3></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="text-muted">Filled Cylinders</div><h3><?=number_format((int)$companyFilled)?></h3></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="text-muted">Empty Cylinders</div><h3><?=number_format((int)$companyEmpty)?></h3></div></div>
  <div class="col-md-3"><div class="card card-body border-info"><div class="text-muted">In Transit Between Branches</div><h3><?= (int) ($transit['n'] ?? 0) ?> <small class="fs-6 text-muted">cyl.</small></h3><div class="small"><?= (int) ($transit['filled'] ?? 0) ?> filled · <?= (int) ($transit['empty_n'] ?? 0) ?> empty · <?= number_format((float) ($transit['kg'] ?? 0), 2) ?> KG</div><div class="small text-muted">Not in any branch's stock until received</div></div></div>
</div>

<?php if (!empty($scope['multi'])): ?>
<div class="card shadow-sm mb-4">
  <div class="card-header bg-white"><strong>Stock by Branch</strong></div>
  <div class="card-body table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Branch</th><th class="text-end">Gas KG</th><th class="text-end">Filled Cylinders</th><th class="text-end">Empty Cylinders</th></tr></thead>
      <tbody>
      <?php foreach ($branchStock as $b): ?>
        <tr><td class="fw-semibold"><?= esc($b['name']) ?></td><td class="text-end"><?= number_format((float) $b['gas_kg'], 2) ?></td><td class="text-end"><?= (int) $b['filled'] ?></td><td class="text-end"><?= (int) $b['empty'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr class="fw-bold"><td>Total</td><td class="text-end"><?= number_format((float) $companyTotalGas, 2) ?></td><td class="text-end"><?= (int) $companyFilled ?></td><td class="text-end"><?= (int) $companyEmpty ?></td></tr></tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white"><strong>Stock by Cylinder Type</strong></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle datatable">
        <thead><tr><th>Cylinder Type</th><th>Capacity KG</th><th>Filled Cylinders</th><th>Available Gas KG</th><th>Empty Cylinders</th><th>Partially Used</th></tr></thead>
        <tbody>
        <?php foreach($summary as $r):?>
          <tr>
            <td><strong><?=esc($r['code'])?></strong> — <?=esc($r['name'])?></td>
            <td><?=number_format((float)$r['capacity_kg'],2)?></td>
            <td><?=number_format((int)$r['filled_cylinders'])?></td>
            <td><?=number_format((float)$r['filled_gas_kg'],2)?></td>
            <td><?=number_format((int)$r['empty_cylinders'])?></td>
            <td><?=number_format((int)$r['partial_cylinders'])?></td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong>Partially Used Filled Cylinders — Currently in Company Stock</strong>
    <span class="badge text-bg-warning"><?=count($partials)?> cylinder<?=count($partials)===1?'':'s'?></span>
  </div>
  <div class="card-body">
    <?php if(!$partials):?>
      <div class="alert alert-light border mb-0">No partially used filled cylinders are currently in company stock.</div>
    <?php else:?>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle datatable">
          <thead><tr><th>Cylinder</th><?php if (!empty($scope['multi'])): ?><th>Branch</th><?php endif; ?><th>Type</th><th>Capacity KG</th><th>Remaining Gas KG</th><th>Gas Used KG</th><th>Used %</th><th>Last Updated</th></tr></thead>
          <tbody>
          <?php foreach($partials as $r):?>
            <tr>
              <td class="fw-semibold"><?=esc($r['unit_code'])?></td>
              <?php if (!empty($scope['multi'])): ?><td><?= esc($r['branch_name']) ?></td><?php endif; ?>
              <td><?=esc($r['type_code'].' — '.$r['type_name'])?></td>
              <td><?=number_format((float)$r['capacity_kg'],2)?></td>
              <td class="fw-semibold text-success"><?=number_format((float)$r['gas_weight_kg'],2)?></td>
              <td><?=number_format((float)$r['used_kg'],2)?></td>
              <td><?=number_format((float)$r['used_percent'],1)?>%</td>
              <td><?=esc($r['updated_at'])?></td>
            </tr>
          <?php endforeach;?>
          </tbody>
        </table>
      </div>
      <div class="small text-muted mt-2">A cylinder is considered partially used when it contains more than 0 KG but less than its full capacity.</div>
    <?php endif;?>
  </div>
</div>
<?= $this->endSection() ?>
