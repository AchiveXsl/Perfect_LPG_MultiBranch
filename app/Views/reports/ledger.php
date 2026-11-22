<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">Customer Ledger — <?=esc($customer['name'])?></h4><div class="text-muted small">Credit / OS ledger · <?= esc($scope['label']) ?></div></div><div class="d-flex gap-2"><button type="button" class="btn btn-outline-danger" id="ledgerPdf"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</button><a class="btn btn-outline-secondary" href="<?=site_url('reports')?>">Back</a></div></div>
<?php if (!empty($scope['canAll'])): ?><form method="get" class="row g-2 align-items-end mb-3"><?= view('reports/_branch_scope', ['scope' => $scope]) ?><div class="col-md-auto"><button class="btn btn-primary">Show</button></div></form><?php endif; ?>
<div class="row g-3 mb-3">
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Opening Balance <span class="small">(home branch)</span></div><h5>Rs. <?=number_format((float)$openingBalance,2)?></h5></div></div>
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Current OS</div><h5>Rs. <?=number_format((float)$currentBalance,2)?></h5></div></div>
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Credit Validation</div><h5><?=esc(ucfirst($creditLimitMode))?></h5></div></div>
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Effective Credit Limit</div><h5><?= $creditLimitMode==='none'?'OFF':'Rs. '.number_format((float)$effectiveCreditLimit,2) ?></h5></div></div>
</div>
<div class="card"><div class="card-body table-responsive"><table class="table table-sm align-middle" id="ledgerTable"><thead><tr><th>Date</th><?php if (!empty($scope['multi'])): ?><th>Branch</th><?php endif; ?><th>Type</th><th>Reference</th><th class="text-end">OS Debit</th><th class="text-end">Receipt / Settlement</th><th class="text-end">Running OS</th></tr></thead><tbody>
<?php foreach($events as $e): ?><tr><td><?=esc($e['date'])?></td><?php if (!empty($scope['multi'])): ?><td><?= esc($e['branch']) ?></td><?php endif; ?><td><?=esc($e['type'])?></td><td><?=esc($e['reference'])?></td><td class="text-end">Rs. <?=number_format((float)$e['debit'],2)?></td><td class="text-end">Rs. <?=number_format((float)$e['credit'],2)?></td><td class="text-end fw-semibold">Rs. <?=number_format((float)$e['running_balance'],2)?></td></tr><?php endforeach; ?>
<?php if(!$events): ?><tr><td colspan="6" class="text-muted">No credit transactions or receipts found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<script>
document.getElementById('ledgerPdf').onclick = () => {
    const esc = s => String(s).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const t = document.getElementById('ledgerTable');
    const cell = (td, tag) => '<' + tag + (td.classList.contains('text-end') ? ' class="n"' : '') + (td.colSpan > 1 ? ' colspan="' + td.colSpan + '"' : '') + '>' + esc(td.innerText.trim()) + '</' + tag + '>';
    const head = '<tr>' + [...t.tHead.rows[0].cells].map(c => cell(c, 'th')).join('') + '</tr>';
    const cols = t.tHead.rows[0].cells.length;
    const opening = '<tr><td colspan="' + (cols - 1) + '"><b>Opening Balance</b></td><td class="n"><b>' + esc(<?= json_encode('Rs. ' . number_format((float) $openingBalance, 2)) ?>) + '</b></td></tr>';
    const body = [...t.tBodies[0].rows].map(r => '<tr>' + [...r.cells].map(c => cell(c, 'td')).join('') + '</tr>').join('');
    const name = <?= json_encode($customer['name']) ?>, scopeLabel = <?= json_encode($scope['label']) ?>;
    const sub = scopeLabel + ' · Current OS: ' + <?= json_encode('Rs. ' . number_format((float) $currentBalance, 2)) ?> + ' · Printed ' + new Date().toLocaleString();
    const w = window.open('', '_blank');
    if (!w) { alert('Allow pop-ups for this site to export the PDF.'); return; }
    w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc('Customer Ledger - ' + name) + '</title><style>@page{size:A4;margin:10mm}body{font-family:Arial,sans-serif;font-size:10px;color:#111}h2{margin:0 0 2px;font-size:15px}.sub{margin:0 0 8px;color:#555}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cfd6dd;padding:4px 5px;text-align:left}th{background:#eef2f6}td.n,th.n{text-align:right;white-space:nowrap}</style></head><body><h2>' + esc('Customer Ledger — ' + name) + '</h2><p class="sub">' + esc(sub) + '</p><table><thead>' + head + '</thead><tbody>' + opening + body + '</tbody></table></body></html>');
    w.document.close(); w.focus(); setTimeout(() => w.print(), 250);
};
</script>
<?= $this->endSection() ?>