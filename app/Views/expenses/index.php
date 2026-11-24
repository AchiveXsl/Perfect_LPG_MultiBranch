<?= $this->extend('layouts/app') ?><?= $this->section('content') ?><h4>Expenses</h4><?php if(session()->getFlashdata('success')):?><div class="alert alert-success"><?=session()->getFlashdata('success')?></div><?php endif;?><?php if(session()->getFlashdata('error')):?><div class="alert alert-danger"><?=esc(session()->getFlashdata('error'))?></div><?php endif;?><form method="post" action="<?=site_url('expenses/save')?>" class="card card-body mb-3"><?=csrf_field()?><div class="row g-2"><div class="col-md-3"><select name="category_id" class="form-select" required><option value="">Category</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>"><?=esc($c['name'])?></option><?php endforeach;?></select></div><div class="col-md-2"><input name="amount" type="number" min=".01" step=".01" class="form-control" placeholder="Amount" required></div><div class="col-md-2"><select name="payment_mode" class="form-select"><option>cash</option><option>cheque</option><option>online</option></select></div><div class="col-md-2"><input name="reference_no" class="form-control" placeholder="Reference"></div><div class="col-md-3"><input name="description" class="form-control" placeholder="Description"></div></div><button class="btn btn-primary mt-2">Post Expense</button></form>

<?php $total = array_sum(array_map(static fn($e) => (float) $e['amount'], $expenses)); ?>
<div class="card shadow-sm">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-end gap-2">
        <strong>Expense History</strong>
        <form method="get" action="<?= site_url('expenses') ?>" class="d-flex flex-wrap align-items-end gap-2" id="expenseFilter">
            <div><label class="form-label small mb-0">From</label><input type="date" name="from" id="expFrom" class="form-control form-control-sm" value="<?= esc($from) ?>"></div>
            <div><label class="form-label small mb-0">To</label><input type="date" name="to" id="expTo" class="form-control form-control-sm" value="<?= esc($to) ?>"></div>
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="expensePdf"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</button>
        </form>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm align-middle mb-0" id="expenseTable">
            <thead><tr><th>No.</th><th>Date</th><th>Category</th><th>Description</th><th>Reference</th><th>Mode</th><th>By</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($expenses as $e): ?>
                <tr><td><?= esc($e['expense_no']) ?></td><td class="text-nowrap"><?= esc(date('d M Y H:i', strtotime($e['expense_at']))) ?></td><td><?= esc($e['category_name']) ?></td><td><?= esc($e['description'] ?? '') ?></td><td><?= esc($e['reference_no'] ?? '') ?></td><td><?= esc($e['payment_mode']) ?></td><td><?= esc($e['created_by_name'] ?? '') ?></td><td class="text-end"><?= number_format((float) $e['amount'], 2) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$expenses): ?><tr><td colspan="8" class="text-center text-muted py-3">No expenses in this period.</td></tr><?php endif; ?>
            </tbody>
            <tfoot><tr class="fw-bold"><td colspan="7" class="text-end"><?= count($expenses) ?> expense(s) · Total</td><td class="text-end"><?= number_format($total, 2) ?></td></tr></tfoot>
        </table>
    </div>
</div>

<script>
document.getElementById('expensePdf').onclick = () => {
    const esc = s => String(s).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    // Export what the page shows: the filtered period that was loaded.
    const from = <?= json_encode($from) ?>, to = <?= json_encode($to) ?>, branch = <?= json_encode($branchName) ?>;
    const table = document.getElementById('expenseTable');
    const rowHtml = tr => '<tr>' + [...tr.cells].map(td => '<td' + (td.classList.contains('text-end') ? ' class="n"' : '') + (td.colSpan > 1 ? ' colspan="' + td.colSpan + '"' : '') + '>' + esc(td.innerText.trim()) + '</td>').join('') + '</tr>';
    const head = '<tr>' + [...table.tHead.rows[0].cells].map(th => '<th' + (th.classList.contains('text-end') ? ' class="n"' : '') + '>' + esc(th.innerText) + '</th>').join('') + '</tr>';
    const title = 'Expense History ' + from + ' to ' + to;
    const w = window.open('', '_blank');
    if (!w) { alert('Allow pop-ups for this site to export the PDF.'); return; }
    w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc(title) + '</title><style>@page{size:A4;margin:10mm}body{font-family:Arial,sans-serif;font-size:10px;color:#111}h2{margin:0 0 2px;font-size:15px}.sub{margin:0 0 8px;color:#555}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cfd6dd;padding:4px 5px;text-align:left;vertical-align:top}th{background:#eef2f6}td.n,th.n{text-align:right;white-space:nowrap}tfoot td{font-weight:700;background:#f6f8fa}</style></head><body><h2>Expense History</h2><p class="sub">' + esc(branch) + ' · ' + esc(from + ' to ' + to) + '</p><table><thead>' + head + '</thead><tbody>' + [...table.tBodies[0].rows].map(rowHtml).join('') + '</tbody><tfoot>' + [...table.tFoot.rows].map(rowHtml).join('') + '</tfoot></table></body></html>');
    w.document.close(); w.focus(); setTimeout(() => w.print(), 250);
};
</script>
<?= $this->endSection() ?>
