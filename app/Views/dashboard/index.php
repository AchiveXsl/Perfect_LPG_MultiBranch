<?= $this->extend('layouts/app') ?>

<?= $this->section('styles') ?>
<style>
.dashboard-hero{display:flex;align-items:center;gap:18px;padding:24px 28px;border-radius:24px;background:linear-gradient(120deg,#06382F,#0B6E54);color:#fff;box-shadow:none;overflow:hidden;position:relative}.dashboard-hero:after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;right:-60px;top:-80px;background:#e3a01a30}.dashboard-hero-art{display:flex;align-items:center;gap:.35rem;z-index:1;position:relative}.dashboard-cylinder{width:62px;height:62px;border-radius:18px;background:#fff;display:flex;align-items:center;justify-content:center;color:#0B6E54;font-size:1.7rem;box-shadow:none}.dashboard-flame{font-weight:800;font-size:11px;background:#E3A01A;color:#10261E;border-radius:50%;width:30px;height:30px;display:grid;place-items:center;position:absolute;right:-10px;bottom:-8px;border:2px solid #0B6E54;padding:0;margin:0;align-self:auto}.dashboard-period-pill{margin-left:auto;z-index:1;white-space:nowrap;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);padding:.45rem .7rem;border-radius:999px;font-size:.78rem}.dashboard-summary-grid .col-12{display:flex}.dashboard-card{position:relative;border:1px solid #DDE9E2;border-radius:20px;background:#fff;padding:20px;min-height:148px;box-shadow:none;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}.dashboard-card:hover{transform:translateY(-4px);box-shadow:0 16px 34px #06382f22;border-color:#DDE9E2}.dashboard-card-top{display:flex;justify-content:space-between;align-items:center}.dashboard-icon{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.35rem}.dashboard-icon.gas,.type-color.gas{background:#12a56a26;color:#12A56A}.dashboard-icon.empty,.type-color.empty{background:#e3a01a33;color:#9a6a00}.dashboard-icon.issued,.type-color.issued{background:#7c5cff26;color:#7c5cff}.dashboard-icon.sale{background:#12a56a26;color:#12A56A}.dashboard-icon.cash,.type-color.cash{background:#e3a01a33;color:#9a6a00}.dashboard-icon.receivable,.type-color.receivable{background:#d8453b22;color:#D8453B}.dashboard-icon.expense{background:#e3a01a33;color:#9a6a00}.dashboard-chevron{color:#94a3b8}.dashboard-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#5F7A70;font-weight:600;margin-top:.55rem}.dashboard-value{font-size:1.75rem;font-weight:800;line-height:1.1;color:#0B2A22;margin-top:.2rem}.dashboard-value small{font-size:.75rem;font-weight:700;color:#64748b}.dashboard-sub{font-size:.72rem;color:#64748b;margin-top:.25rem}.dashboard-hint{font-size:.68rem;color:#94a3b8;margin-top:.45rem}.dashboard-payment-mini{display:flex;gap:.25rem;flex-wrap:wrap;margin-top:.4rem}.dashboard-payment-mini span{font-size:.62rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:999px;padding:.18rem .4rem;color:#475569}.dashboard-note{padding:.7rem .9rem;border:1px dashed #cbd5e1;border-radius:.65rem;background:#f8fafc;color:#64748b;font-size:.78rem}.detail-intro{display:flex;align-items:center;gap:.9rem;padding:.85rem 1rem;border-radius:.7rem;margin-bottom:1rem}.detail-intro i{font-size:1.5rem}.detail-intro strong{display:block;font-size:1.15rem}.detail-intro span{display:block;font-size:.78rem;color:#64748b}.gas-bg{background:#eff6ff;color:#1d4ed8}.empty-bg{background:#fff7ed;color:#c2410c}.issued-bg{background:#f5f3ff;color:#6d28d9}.receivable-bg{background:#fef2f2;color:#b91c1c}.dashboard-drill-card{border:1px solid #e2e8f0;border-radius:.65rem;margin-bottom:.65rem;overflow:hidden;background:#fff}.dashboard-drill-card summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:.55rem;padding:.8rem .9rem;font-size:.84rem}.dashboard-drill-card summary::-webkit-details-marker{display:none}.dashboard-drill-card summary:after{content:"\f282";font-family:"bootstrap-icons";margin-left:.55rem;color:#94a3b8}.dashboard-drill-card[open] summary{background:#f8fafc;border-bottom:1px solid #e2e8f0}.type-color{display:inline-flex;width:12px;height:12px;border-radius:50%;flex:0 0 12px}.type-color.gas{background:#3b82f6}.type-color.empty{background:#f97316}.type-color.issued{background:#8b5cf6}.type-color.cash{background:#eab308}.type-color.receivable{background:#ef4444}.detail-kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.65rem;margin-bottom:1rem}.detail-kpi-grid>div{border:1px solid #e2e8f0;border-radius:.65rem;padding:.7rem;background:#f8fafc}.detail-kpi-grid span{display:block;font-size:.7rem;color:#64748b}.detail-kpi-grid strong{display:block;margin-top:.15rem;font-size:.95rem}.expense-category{border:1px solid #e2e8f0;border-radius:.65rem;padding:.65rem;background:#fff}.expense-category span,.expense-category small{display:block;color:#64748b}.expense-category strong{display:block;font-size:.95rem}@media(max-width:767.98px){.dashboard-hero{align-items:flex-start}.dashboard-period-pill{display:none}.dashboard-hero-art{display:none}.dashboard-value{font-size:1.3rem}.detail-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}

.dashboard-drill-card summary strong{min-width:0;overflow-wrap:anywhere}.dashboard-drill-card summary>i{flex:0 0 1.1rem;text-align:center}.dashboard-drill-card summary{flex-wrap:wrap}.dashboard-drill-card summary .summary-meta{margin-left:auto;text-align:right;color:#475569;font-size:.8rem}.purchase-items td{font-size:.85rem}.purchase-items .amount-due{color:#b91c1c;font-weight:600}.purchase-items .amount-clear{color:#64748b}@media(max-width:575.98px){.dashboard-drill-card summary .summary-meta{flex-basis:100%;text-align:left}}.drill-nested{padding:.65rem .65rem .25rem 1.25rem;background:#fcfdfd}.drill-table{table-layout:fixed;width:100%}.drill-table th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;white-space:nowrap}.drill-table td,.drill-table th{vertical-align:top}.drill-table .col-type{width:34%}.drill-table .col-count{width:16%;text-align:right}.drill-table .col-detail{width:50%}.cyl-toggle summary{cursor:pointer;color:#0B6E54;font-size:.8rem;font-weight:600;list-style:none}.cyl-toggle summary::-webkit-details-marker{display:none}.cyl-toggle summary:before{content:"\f4fe";font-family:"bootstrap-icons";margin-right:.35rem}.cyl-toggle[open] summary:before{content:"\f2ea"}.cyl-chips{display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.5rem;max-height:9rem;overflow:auto;padding:.1rem}.cyl-chip{font-size:.72rem;line-height:1;padding:.32rem .5rem;border-radius:999px;background:#eef6f2;border:1px solid #d6e8de;color:#0B3D2E;white-space:nowrap}.purchase-items .item-line{font-size:.78rem;white-space:nowrap}
.dashboard-top-header{padding:.65rem .85rem;background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;box-shadow:0 2px 10px rgba(15,23,42,.045)}.dashboard-top-header h4{font-weight:800;color:#172033}.dashboard-top-header #dashboardFilterToggle{font-weight:650}.dashboard-top-header + #dashboardDateFilter .card{border:1px solid #e2e8f0!important}.dashboard-top-header + #dashboardDateFilter .card-body{padding:.75rem 1rem!important}
.dashboard-hero-top{margin-bottom:.65rem!important;flex-wrap:wrap}.dashboard-hero-top #dashboardFilterToggle{position:relative;z-index:2;flex:0 0 auto;white-space:nowrap}.dashboard-hero-top .dashboard-period-pill{margin-left:auto}@media(max-width:767.98px){.dashboard-hero-top{gap:.75rem}.dashboard-hero-top .flex-grow-1{flex-basis:calc(100% - 1rem)}.dashboard-hero-top .dashboard-period-pill{margin-left:0}.dashboard-hero-top #dashboardFilterToggle{margin-left:auto}}
</style>

<script>
document.addEventListener('DOMContentLoaded',function(){
  // Dashboard auto-refresh is intentionally disabled; refresh manually to reload data.

  // Promote the shop overview hero to the top of the dashboard and place the date filter inside it.
  const hero=document.querySelector('.dashboard-hero');
  const contentWrap=document.querySelector('.content-wrap');
  const filterToggle=document.getElementById('dashboardFilterToggle');
  const dashboardHeader=document.querySelector('.dashboard-top-header');
  const dateFilter=document.getElementById('dashboardDateFilter');
  if(hero && contentWrap){
    contentWrap.insertBefore(hero, contentWrap.firstChild);
    hero.classList.add('dashboard-hero-top');
    if(filterToggle){
      filterToggle.classList.remove('btn-outline-primary');
      filterToggle.classList.add('btn-outline-light');
      hero.appendChild(filterToggle);
    }
    if(dashboardHeader) dashboardHeader.remove();
    if(dateFilter && dateFilter.parentNode!==contentWrap){
      contentWrap.insertBefore(dateFilter, hero.nextSibling);
    }
  }
  const modal=document.getElementById('dashboardDetailModal'); if(!modal)return;
  const body=document.getElementById('dashboardDetailBody'), title=document.getElementById('dashboardDetailTitle');
  const titles={purchases:'Purchases — Company, Plant & Payment Details',filled:'Total Filled Cylinders — Company & Plant',gas:'Available Gas KG — Cylinder Type & Individual Cylinders',empty:'Empty Gas Cylinders in Shop — Type & Individual Cylinders',issued:'Issued Cylinders — Customers & Cylinder Details',sales:'Sales — Payment & Transaction Details',cash:'Cash Counter — Register In / Out Movements',receivable:'Receivable — Amount Due from Customers',payable:'Payable — Amount Due to Suppliers',profit:'Sales & Profit — Customers, Sales and Cost',expenses:'Expenses — Category & Transaction Details'};
  // Sales & Profit card: customers -> that customer's sales -> one sale with price and cost applied.
  modal.addEventListener('shown.bs.modal',function(e){
    if(e.relatedTarget?.getAttribute('data-detail-target')!=='detail-profit')return;
    const box=body.querySelector('#profitDrill'); if(!box)return;
    const qs=box.dataset.qs, TY={gas_sale:'Gas Sale',cylinder_sale:'Cylinder Sale',security_deposit:'Security Deposit / Issue'};
    const M=v=>'Rs. '+Number(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    const H=s=>String(s==null?'':s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    const C=v=>Number(v)>=0?'text-success':'text-danger', EST=e=>e?' <span class="badge text-bg-light border" title="Posted before cost was stored. Cost is estimated with the average purchase cost.">est.</span>':'';
    const N=v=>Number(v||0).toLocaleString('en-US',{maximumFractionDigits:3});
    function load(url,done){box.innerHTML='<div class="text-muted small py-3">Loading…</div>';
      fetch(url+'?'+qs,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(done).catch(()=>{box.innerHTML='<div class="alert alert-danger">Could not load the details.</div>';});}
    const rowStyle='cursor:pointer';
    function customers(){load(box.dataset.urlCustomers,function(d){
      let h='<div class="fw-semibold mb-2">Customer-wise sales &amp; profit <span class="small text-muted fw-normal">· click a customer for the sales</span></div><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Customer</th><th class="text-end">Sales</th><th class="text-end">Cost</th><th class="text-end">Profit</th><th class="text-end">Margin</th></tr></thead><tbody>';
      d.rows.forEach(r=>{const mg=r.sales>0?(r.profit/r.sales*100):0;h+='<tr data-id="'+r.customer_id+'" data-name="'+H(r.name)+'" style="'+rowStyle+'"><td>'+H(r.name)+EST(r.estimated)+'<div class="small text-muted">'+r.count+' sale'+(r.count==1?'':'s')+'</div></td><td class="text-end">'+M(r.sales)+'</td><td class="text-end">'+M(r.cost)+'</td><td class="text-end fw-semibold '+C(r.profit)+'">'+M(r.profit)+'</td><td class="text-end">'+mg.toFixed(1)+'%</td></tr>';});
      if(!d.rows.length)h+='<tr><td colspan="5" class="text-center text-muted py-3">No sales in this period.</td></tr>';
      const t=d.total;h+='</tbody><tfoot><tr class="table-light fw-bold"><td>Total</td><td class="text-end">'+M(t.sales)+'</td><td class="text-end">'+M(t.cost)+'</td><td class="text-end '+C(t.profit)+'">'+M(t.profit)+'</td><td class="text-end">'+(t.sales>0?(t.profit/t.sales*100).toFixed(1):'0.0')+'%</td></tr></tfoot></table></div>';
      box.innerHTML=h;box.querySelectorAll('tbody tr[data-id]').forEach(tr=>tr.onclick=()=>sales(+tr.dataset.id,tr.dataset.name));});}
    function sales(id,name){load(box.dataset.urlSales+'/'+id,function(d){
      let h='<button type="button" class="btn btn-sm btn-outline-secondary mb-2" id="pdBack"><i class="bi bi-arrow-left"></i> All customers</button><div class="fw-semibold mb-2">'+H(name)+' <span class="small text-muted fw-normal">· click a sale to see its price and cost</span></div><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Sale</th><th>Date</th><th>Type</th><th class="text-end">Sale</th><th class="text-end">Cost</th><th class="text-end">Profit</th></tr></thead><tbody>';
      let ts=0,tc=0;d.rows.forEach(r=>{ts+=r.revenue;tc+=r.cost;h+='<tr data-id="'+r.id+'" style="'+rowStyle+'"><td>'+H(r.sale_no)+EST(r.estimated)+'</td><td>'+H(r.transaction_at)+'</td><td>'+H(TY[r.transaction_type]||r.transaction_type)+'</td><td class="text-end">'+M(r.revenue)+'</td><td class="text-end">'+M(r.cost)+'</td><td class="text-end fw-semibold '+C(r.profit)+'">'+M(r.profit)+'</td></tr>';});
      h+='</tbody><tfoot><tr class="table-light fw-bold"><td colspan="3">Total</td><td class="text-end">'+M(ts)+'</td><td class="text-end">'+M(tc)+'</td><td class="text-end '+C(ts-tc)+'">'+M(ts-tc)+'</td></tr></tfoot></table></div>';
      box.innerHTML=h;box.querySelector('#pdBack').onclick=customers;box.querySelectorAll('tbody tr[data-id]').forEach(tr=>tr.onclick=()=>sale(+tr.dataset.id,id,name));});}
    function sale(id,cid,name){load(box.dataset.urlSale+'/'+id,function(d){
      if(d.error){box.innerHTML='<div class="alert alert-danger">'+H(d.error)+'</div>';return;}
      const s=d.sale;
      let h='<button type="button" class="btn btn-sm btn-outline-secondary mb-2" id="pdBack"><i class="bi bi-arrow-left"></i> '+H(name)+'</button><div class="mb-2"><span class="fw-semibold">'+H(s.sale_no)+'</span> <span class="small text-muted">· '+H(s.at)+' · '+H(TY[s.type]||s.type)+' · '+H(s.customer)+'</span></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Gas KG</th><th class="text-end">Sale rate</th><th class="text-end">Sale amount</th><th class="text-end">Cost price</th><th class="text-end">Profit</th></tr></thead><tbody>';
      d.lines.forEach(l=>{
        h+='<tr><td>'+H(l.item)+'</td><td class="text-end">'+N(l.quantity)+'</td><td class="text-end">'+N(l.gas_kg)+'</td><td class="text-end">'+M(l.rate)+'</td><td class="text-end">'+M(l.line_total)+'</td><td class="text-end">'+M(l.cost)+EST(l.estimated)+'</td><td class="text-end fw-semibold '+C(l.profit)+'">'+M(l.profit)+'</td></tr>';
        let sub='';
        if(l.estimated){sub='Estimated with the average purchase cost (posted before cost was stored).';}
        else{sub='<b>'+H(({fifo:'FIFO',lifo:'LIFO',average:'Average'})[l.method]||l.method)+'</b> cost'+(l.cost_cylinder>0?' · gas '+M(l.cost_gas)+' + cylinder '+M(l.cost_cylinder):'')
          +l.lots.map(x=>'<div>'+H(x.source)+' · '+N(x.qty)+(x.kind==='gas'?' KG':' cyl')+' × '+M(x.unit_cost)+' = '+M(x.amount)+'</div>').join('');}
        h+='<tr><td colspan="7" class="small text-muted border-0 pt-0">'+sub+'</td></tr>';
      });
      h+='</tbody><tfoot><tr class="table-light fw-bold"><td colspan="4">Total</td><td class="text-end">'+M(s.total)+'</td><td class="text-end">'+M(d.cost)+'</td><td class="text-end '+C(d.profit)+'">'+M(d.profit)+'</td></tr></tfoot></table></div>';
      box.innerHTML=h;box.querySelector('#pdBack').onclick=()=>sales(cid,name);});}
    customers();
  });
  modal.addEventListener('show.bs.modal',function(e){const target=e.relatedTarget?.getAttribute('data-detail-target');const source=document.getElementById(target);if(!source)return;title.textContent=titles[target.replace('detail-','')]||'Details';body.innerHTML=source.innerHTML;});
});
</script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$money = static fn(float $v): string => 'Rs. ' . number_format($v, 2);
$qty = static fn(float $v): string => number_format($v, 2);
$stockTab = trim((string)(service('request')->getGet('stock_tab') ?? ''));
if (!in_array($stockTab, ['filled', 'empty', 'issued'], true)) {
    $stockTab = '';
}
$displayStockTab = $stockTab !== '' ? $stockTab : 'filled';
?>

<div class="dashboard-top-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <h4 class="mb-0"><i class="bi bi-speedometer2 me-2 text-warning"></i>Dashboard</h4>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="dashboardFilterToggle"
            data-bs-toggle="collapse" data-bs-target="#dashboardDateFilter"
            aria-expanded="false" aria-controls="dashboardDateFilter">
        <i class="bi bi-funnel me-1"></i><span>Show Date Filter</span>
    </button>
</div>

<div class="collapse mb-3" id="dashboardDateFilter">
    <div class="card shadow-sm border-0">
        <div class="card-body py-3">
            <form method="get" action="<?= site_url('dashboard') ?>" class="row g-2 align-items-end">
                <div class="col-sm-5 col-md-3">
                    <label class="form-label small text-muted mb-1">From</label>
                    <input type="date" name="from" class="form-control" value="<?= esc($range['from']) ?>" required>
                </div>
                <div class="col-sm-5 col-md-3">
                    <label class="form-label small text-muted mb-1">To</label>
                    <input type="date" name="to" class="form-control" value="<?= esc($range['to']) ?>" required>
                </div>
                <div class="col-sm-2 col-md-2">
                    <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Apply Filter</button>
                </div>
                <?php if (empty($range['isToday'])): ?>
                <div class="col-sm-3 col-md-auto">
                    <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-secondary w-100" id="dashboardClearFilter"><i class="bi bi-x-circle me-1"></i>Clear Filter</a>
                </div>
                <?php endif; ?>
                <div class="col-12 col-md-auto">
                    <div class="small text-muted mt-1">Current range: <?= esc($range['label']) ?></div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filter = document.getElementById('dashboardDateFilter');
    const toggle = document.getElementById('dashboardFilterToggle');
    if (!filter || !toggle) return;
    filter.addEventListener('show.bs.collapse', function () {
        toggle.querySelector('span').textContent = 'Hide Date Filter';
        toggle.querySelector('i').className = 'bi bi-funnel-fill me-1';
    });
    filter.addEventListener('hide.bs.collapse', function () {
        toggle.querySelector('span').textContent = 'Show Date Filter';
        toggle.querySelector('i').className = 'bi bi-funnel me-1';
    });
});
</script>

<style>
.dash-view-switch{display:inline-flex;align-items:center;gap:.55rem;z-index:2;position:relative;color:#fff;font-size:.8rem;font-weight:650;white-space:nowrap;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);padding:.3rem .7rem;border-radius:999px}
.dash-view-switch .form-check-input{width:2.4em;height:1.25em;margin:0;cursor:pointer;background-color:rgba(255,255,255,.35);border-color:rgba(255,255,255,.5)}
.dash-view-switch .form-check-input:checked{background-color:#E3A01A;border-color:#E3A01A}
.dash-view-switch span{opacity:.65;transition:opacity .15s}.dash-view-switch span.on{opacity:1}
.dash-chart-card{background:#fff;border:1px solid #DDE9E2;border-radius:20px;padding:18px 20px;height:100%}
.dash-chart-card h6{font-weight:800;color:#0B2A22;margin:0}
.dash-chart-card .sub{font-size:.72rem;color:#64748b}
.dash-chart-box{position:relative;height:260px}.dash-chart-box.tall{height:300px}
.dash-empty{display:none;position:absolute;inset:0;align-items:center;justify-content:center;color:#94a3b8;font-size:.85rem;background:#fff}
.dash-seg .btn{font-size:.72rem;padding:.2rem .6rem}
.dash-list{list-style:none;margin:0;padding:0}.dash-list li{display:flex;justify-content:space-between;gap:.75rem;padding:.5rem 0;border-bottom:1px solid #eef2f0;font-size:.82rem}.dash-list li:last-child{border-bottom:0}
.dash-list .sm{display:block;font-size:.7rem;color:#64748b}.dash-list .amt{font-weight:700;white-space:nowrap}
.dash-pill{display:inline-block;font-size:.66rem;font-weight:700;padding:.1rem .5rem;border-radius:999px;background:#eef6f2;color:#0B6E54}
</style>

<div id="dashViewSwitch" class="dash-view-switch" title="Switch between summary cards and graphical charts">
    <span id="dvCards" class="on"><i class="bi bi-grid-1x2 me-1"></i>Cards</span>
    <div class="form-check form-switch m-0 p-0"><input class="form-check-input" type="checkbox" role="switch" id="dashViewToggle" aria-label="Show charts"></div>
    <span id="dvCharts"><i class="bi bi-bar-chart-line me-1"></i>Charts</span>
</div>

<div id="dashboardCharts" style="display:none" class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="small text-muted">Charts for <strong id="dcRange"><?= esc($range['label']) ?></strong>. Use "Show Date Filter" above to change the period.</div>
        <div class="small text-muted" id="dcStatus"></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-8"><div class="dash-chart-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                <div><h6>Sales Overview</h6><div class="sub">Sales amount by period</div></div>
                <div class="btn-group dash-seg" role="group" id="dcBucket">
                    <button type="button" class="btn btn-outline-secondary" data-b="hour">Hours</button>
                    <button type="button" class="btn btn-outline-secondary" data-b="day">Days</button>
                    <button type="button" class="btn btn-outline-secondary" data-b="week">Weeks</button>
                    <button type="button" class="btn btn-outline-secondary" data-b="month">Months</button>
                </div>
            </div>
            <div class="dash-chart-box"><canvas id="chSales"></canvas><div class="dash-empty" id="emSales">Nothing to show yet</div></div>
        </div></div>
        <div class="col-lg-4"><div class="dash-chart-card">
            <h6>Sales by Payment Mode</h6><div class="sub mb-2">Money received with sales</div>
            <div class="dash-chart-box"><canvas id="chPay"></canvas><div class="dash-empty" id="emPay">Nothing to show yet</div></div>
        </div></div>

        <div class="col-lg-4"><div class="dash-chart-card">
            <h6>Recent Transactions</h6><div class="sub mb-2">Latest posted sales</div>
            <ul class="dash-list" id="dcRecent"></ul>
        </div></div>
        <div class="col-lg-4"><div class="dash-chart-card">
            <h6>Top Customers</h6><div class="sub mb-2">Highest sales in the period</div>
            <div class="dash-chart-box tall"><canvas id="chCust"></canvas><div class="dash-empty" id="emCust">Nothing to show yet</div></div>
        </div></div>
        <div class="col-lg-4"><div class="dash-chart-card">
            <h6>Top Selling Items</h6><div class="sub mb-2">By cylinder type, sales amount</div>
            <div class="dash-chart-box tall"><canvas id="chItems"></canvas><div class="dash-empty" id="emItems">Nothing to show yet</div></div>
        </div></div>

        <div class="col-lg-8"><div class="dash-chart-card">
            <h6>Sales vs Purchases vs Expenses</h6><div class="sub mb-2">Per day (per month in the Months view)</div>
            <div class="dash-chart-box"><canvas id="chCmp"></canvas><div class="dash-empty" id="emCmp">Nothing to show yet</div></div>
        </div></div>
        <div class="col-lg-4"><div class="dash-chart-card">
            <h6>Stock by Cylinder Type</h6><div class="sub mb-2">Current filled, empty and issued cylinders</div>
            <div class="dash-chart-box"><canvas id="chStock"></canvas><div class="dash-empty" id="emStock">Nothing to show yet</div></div>
        </div></div>

        <div class="col-lg-6"><div class="dash-chart-card">
            <div class="d-flex justify-content-between align-items-start"><div><h6>Owed by Customers</h6><div class="sub mb-2">Receivable, top 5</div></div><span class="dash-pill" id="dcRecvTotal"></span></div>
            <ul class="dash-list" id="dcRecv"></ul>
        </div></div>
        <div class="col-lg-6"><div class="dash-chart-card">
            <div class="d-flex justify-content-between align-items-start"><div><h6>Owed to Suppliers</h6><div class="sub mb-2">Payable, top 5</div></div><span class="dash-pill" id="dcPayTotal"></span></div>
            <ul class="dash-list" id="dcPay"></ul>
        </div></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('dashViewToggle'), sw = document.getElementById('dashViewSwitch');
    const panel = document.getElementById('dashboardCharts'), tabs = document.getElementById('dashboardTabs'), content = document.getElementById('dashboardTabContent');
    if (!toggle || !panel) return;
    // Sit next to the date-filter button inside the hero (the hero is moved to the top by the script above).
    const hero = document.querySelector('.dashboard-hero'), fbtn = document.getElementById('dashboardFilterToggle');
    if (hero) hero.insertBefore(sw, fbtn || null);
    const from = <?= json_encode($range['from']) ?>, to = <?= json_encode($range['to']) ?>;
    const chartsUrl = <?= json_encode(site_url('dashboard/charts')) ?>;
    const money = v => 'Rs. ' + Number(v || 0).toLocaleString('en-US', {maximumFractionDigits: 0});
    const C = {green: '#0B6E54', amber: '#E3A01A', purple: '#7C5CFF', blue: '#3B82F6', red: '#D8453B', slate: '#64748B', teal: '#14B8A6', orange: '#F97316'};
    const typeNames = {gas_sale: 'Gas Sale', cylinder_sale: 'Cylinder Sale', security_deposit: 'Security Deposit', cylinder_return: 'Cylinder Return'};
    let bucket = from === to ? 'hour' : 'day', loaded = false, loading = false, ChartLib = null; const charts = {};
    const stockTabActive = <?= json_encode($stockTab !== '') ?>;

    function setView(on, remember) {
        toggle.checked = on;
        panel.style.display = on ? '' : 'none';
        if (tabs) tabs.style.display = on ? 'none' : '';
        if (content) content.style.display = on ? 'none' : '';
        document.getElementById('dvCards').classList.toggle('on', !on);
        document.getElementById('dvCharts').classList.toggle('on', on);
        if (remember) { try { localStorage.setItem('lpgDashView', on ? 'charts' : 'cards'); } catch (e) {} }
        if (on) load();
    }
    function loadLib() {
        if (window.Chart) return Promise.resolve();
        return new Promise((res, rej) => { const s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'; s.onload = res; s.onerror = () => rej(new Error('Chart library could not be loaded. Check the internet connection.')); document.head.appendChild(s); });
    }
    function draw(id, cfg, hasData, emptyId) {
        if (charts[id]) charts[id].destroy();
        const em = document.getElementById(emptyId); em.style.display = hasData ? 'none' : 'flex';
        if (!hasData) return;
        charts[id] = new Chart(document.getElementById(id), cfg);
    }
    function lists(el, rows, fmt) {
        el.innerHTML = rows.length ? rows.map(fmt).join('') : '<li class="text-muted">Nothing to show yet</li>';
    }
    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, m => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m])); }

    function render(d) {
        Chart.defaults.font.family = 'inherit'; Chart.defaults.color = '#475569';
        const grid = {color: '#eef2f0'}, yMoney = {beginAtZero: true, grid, ticks: {callback: v => Number(v).toLocaleString('en-US')}};
        const tip = {callbacks: {label: c => ' ' + (c.dataset.label ? c.dataset.label + ': ' : '') + money(c.parsed.y ?? c.parsed.x ?? c.parsed)}};
        const s = d.sales, any = s.amounts.some(v => v > 0);
        draw('chSales', {type: s.labels.length > 40 ? 'line' : 'bar', data: {labels: s.labels, datasets: [{label: 'Sales', data: s.amounts, backgroundColor: C.green, borderColor: C.green, borderRadius: 6, maxBarThickness: 36, tension: .3, pointRadius: 2}]},
            options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {callbacks: {label: c => ' ' + money(c.parsed.y) + ' · ' + s.counts[c.dataIndex] + ' sale(s)'}}}, scales: {y: yMoney, x: {grid: {display: false}}}}}, any, 'emSales');

        const pm = d.payments.filter(x => +x.amount > 0), pmColors = {cash: C.green, online: C.blue, cheque: C.amber, credit: C.red, raast: '#2563eb', jazzcash: '#dc2626', easypaisa: '#0d9488', upaisa: '#ea580c', nayapay: '#7c3aed', sadapay: '#db2777'};
        draw('chPay', {type: 'doughnut', data: {labels: pm.map(x => x.label || (x.mode[0].toUpperCase() + x.mode.slice(1))), datasets: [{data: pm.map(x => +x.amount), backgroundColor: pm.map(x => pmColors[x.mode] || C.slate), borderWidth: 2, borderColor: '#fff'}]},
            options: {responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: {legend: {position: 'bottom'}, tooltip: {callbacks: {label: c => ' ' + c.label + ': ' + money(c.parsed)}}}}}, pm.length > 0, 'emPay');

        lists(document.getElementById('dcRecent'), d.recent, r => '<li><div><strong>' + esc(r.customer) + '</strong><span class="sm">' + esc(r.sale_no) + ' · ' + esc(typeNames[r.transaction_type] || r.transaction_type) + ' · ' + esc(String(r.transaction_at).slice(0, 16)) + '</span></div><span class="amt">' + money(r.total_amount) + '</span></li>');

        const tc = d.topCustomers;
        draw('chCust', {type: 'bar', data: {labels: tc.map(x => x.name), datasets: [{label: 'Sales', data: tc.map(x => +x.amount), backgroundColor: C.purple, borderRadius: 6, maxBarThickness: 22}]},
            options: {indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {callbacks: {label: c => ' ' + money(c.parsed.x) + ' · ' + tc[c.dataIndex].cnt + ' sale(s)'}}}, scales: {x: {beginAtZero: true, grid}, y: {grid: {display: false}}}}}, tc.length > 0, 'emCust');

        const ti = d.topItems;
        draw('chItems', {type: 'bar', data: {labels: ti.map(x => x.name), datasets: [{label: 'Sales', data: ti.map(x => +x.amount), backgroundColor: C.amber, borderRadius: 6, maxBarThickness: 22}]},
            options: {indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {callbacks: {label: c => ' ' + money(c.parsed.x) + ' · ' + Number(ti[c.dataIndex].kg).toFixed(1) + ' KG · ' + Number(ti[c.dataIndex].qty).toFixed(0) + ' qty'}}}, scales: {x: {beginAtZero: true, grid}, y: {grid: {display: false}}}}}, ti.length > 0, 'emItems');

        const cp = d.compare, cAny = [].concat(cp.sales, cp.purchases, cp.expenses).some(v => v > 0);
        draw('chCmp', {type: 'bar', data: {labels: cp.labels, datasets: [
            {label: 'Sales', data: cp.sales, backgroundColor: C.green, borderRadius: 4, maxBarThickness: 18},
            {label: 'Purchases', data: cp.purchases, backgroundColor: C.blue, borderRadius: 4, maxBarThickness: 18},
            {label: 'Expenses', data: cp.expenses, backgroundColor: C.orange, borderRadius: 4, maxBarThickness: 18}]},
            options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}, tooltip: tip}, scales: {y: yMoney, x: {grid: {display: false}}}}}, cAny, 'emCmp');

        const st = d.stock, stAny = st.some(x => +x.filled + +x.empty_n + +x.issued > 0);
        draw('chStock', {type: 'bar', data: {labels: st.map(x => x.name), datasets: [
            {label: 'Filled', data: st.map(x => +x.filled), backgroundColor: C.green, borderRadius: 4},
            {label: 'Empty', data: st.map(x => +x.empty_n), backgroundColor: C.amber, borderRadius: 4},
            {label: 'Issued', data: st.map(x => +x.issued), backgroundColor: C.purple, borderRadius: 4}]},
            options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}, tooltip: {callbacks: {afterBody: i => 'Gas in stock: ' + Number(st[i[0].dataIndex].gas_kg).toFixed(1) + ' KG'}}}, scales: {x: {stacked: true, grid: {display: false}}, y: {stacked: true, beginAtZero: true, grid, ticks: {precision: 0}}}}}, stAny, 'emStock');

        const oRow = r => '<li><strong>' + esc(r.name) + '</strong><span class="amt">' + money(r.amount) + '</span></li>';
        lists(document.getElementById('dcRecv'), d.receivable.rows, oRow); lists(document.getElementById('dcPay'), d.payable.rows, oRow);
        document.getElementById('dcRecvTotal').textContent = 'Total ' + money(d.receivable.total);
        document.getElementById('dcPayTotal').textContent = 'Total ' + money(d.payable.total);
        document.getElementById('dcRange').textContent = d.range;
        document.querySelectorAll('#dcBucket .btn').forEach(b => b.classList.toggle('active', b.dataset.b === d.bucket));
    }
    function load() {
        if (loading) return; loading = true;
        const status = document.getElementById('dcStatus'); status.textContent = 'Loading…';
        Promise.all([loadLib(), fetch(chartsUrl + '?from=' + from + '&to=' + to + '&bucket=' + bucket, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store'}).then(r => { if (!r.ok) throw new Error('Charts could not be loaded (' + r.status + ').'); return r.json(); })])
            .then(([, d]) => { render(d); status.textContent = ''; loaded = true; })
            .catch(e => { status.textContent = e.message; status.className = 'small text-danger'; })
            .finally(() => { loading = false; });
    }
    toggle.addEventListener('change', () => setView(toggle.checked, true));
    document.getElementById('dcBucket').addEventListener('click', e => { const b = e.target.closest('[data-b]'); if (!b) return; bucket = b.dataset.b; load(); });
    let saved = null; try { saved = localStorage.getItem('lpgDashView'); } catch (e) {}
    setView(saved === 'charts' && !stockTabActive, false);
});
</script>

<ul class="nav nav-tabs dashboard-tabs mb-3" id="dashboardTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $stockTab === '' ? 'active' : '' ?> fw-semibold" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary-pane" type="button" role="tab">
            <i class="bi bi-grid-1x2 me-1"></i>Summary
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $stockTab !== '' ? 'active' : '' ?> fw-semibold" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock-pane" type="button" role="tab">
            <i class="bi bi-boxes me-1"></i>Current Stock
        </button>
    </li>
</ul>

<div class="tab-content" id="dashboardTabContent">
    <div class="tab-pane fade <?= $stockTab === '' ? 'show active' : '' ?>" id="summary-pane" role="tabpanel" aria-labelledby="summary-tab">
        <div class="dashboard-hero mb-4">
            <div class="dashboard-hero-art"><div class="dashboard-cylinder"><i class="bi bi-fuel-pump-fill"></i></div><div class="dashboard-flame">KG</div></div>
            <div class="flex-grow-1">
                <div class="small text-uppercase fw-bold opacity-75">Shop overview</div>
                <h3 class="mb-1">LPG Operations Dashboard</h3>
                <div class="small opacity-75">Click any summary card to drill into the underlying cylinders, customers, payments or cash movements.</div>
            </div>
            <div class="dashboard-period-pill"><i class="bi bi-calendar3 me-1"></i><?= esc($range['label']) ?><?php if (empty($range['isToday'])): ?> <a href="<?= site_url('dashboard') ?>" class="text-white ms-1" title="Clear filter and show today" style="text-decoration:none"><i class="bi bi-x-circle-fill"></i></a><?php endif; ?></div>
        </div>

        <div class="row g-2 dashboard-summary-grid">
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card purchase-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-purchases">
                    <div class="dashboard-card-top"><span class="dashboard-icon issued"><i class="bi bi-bag-check-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Purchases</div>
                    <div class="dashboard-value"><?= $money((float)$purchaseSummary['total']) ?></div>
                    <div class="dashboard-sub"><?= number_format((int)$purchaseSummary['count']) ?> purchases · <?= number_format((int)$purchaseSummary['cylinders']) ?> cylinders · <?= $range['isToday'] ? 'Today' : esc($range['label']) ?></div>
                    <div class="dashboard-payment-mini"><span>Paid <?= $money((float)$purchaseSummary['paid']) ?></span><span>Pending <?= $money((float)$purchaseSummary['pending']) ?></span></div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <?php $isProfit=(float)$profitLoss['gross']>=0; $costMethodLabel=['fifo'=>'FIFO','lifo'=>'LIFO','average'=>'Average'][$profitLoss['method']??'average']??'Average'; ?>
                <button type="button" class="dashboard-card profit-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-profit">
                    <div class="dashboard-card-top"><span class="dashboard-icon sale"><i class="bi bi-graph-up-arrow"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Sales &amp; Profit</div>
                    <div class="dashboard-value <?= $isProfit?'text-success':'text-danger' ?>"><?= $money((float)$profitLoss['gross']) ?></div>
                    <div class="dashboard-sub"><?= $isProfit?'Gross profit':'Gross loss' ?> · Sales Rs. <?= number_format((float)$profitLoss['revenue'],2) ?></div>
                    <div class="dashboard-hint"><?= esc($costMethodLabel) ?> cost<?= !empty($profitLoss['estimated'])?' · partly estimated':'' ?> · click for customers</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card payable-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-payable">
                    <div class="dashboard-card-top"><span class="dashboard-icon expense"><i class="bi bi-truck"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Payable</div>
                    <div class="dashboard-value"><?= $money((float)$payableTotal) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($payables)) ?> suppliers to pay</div>
                    <div class="dashboard-hint">Total payable to suppliers, as of today</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card receivable-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-receivable">
                    <div class="dashboard-card-top"><span class="dashboard-icon receivable"><i class="bi bi-person-lines-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Receivable</div>
                    <div class="dashboard-value"><?= $money((float)$receivableTotal) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($receivables)) ?> customers with balance due</div>
                    <div class="dashboard-hint">Total receivable from customers, as of today</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card gas-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-gas">
                    <div class="dashboard-card-top"><span class="dashboard-icon gas"><i class="bi bi-fuel-pump-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Available Gas KG</div>
                    <div class="dashboard-value"><?= number_format((float)$availableGasKg,2) ?> <small>KG</small></div>
                    <div class="dashboard-sub"><?= number_format(count($gasRows)) ?> filled cylinders · <?= number_format(count($gasTypes)) ?> types</div>
                    <div class="dashboard-hint">Cylinder type → individual cylinder</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="dashboard-card total-cylinders-card w-100 text-start">
                    <div class="dashboard-card-top"><span class="dashboard-icon gas"><i class="bi bi-collection-fill"></i></span></div>
                    <div class="dashboard-label">Total Cylinders</div>
                    <div class="dashboard-value"><?= number_format((int)$filledCylinderCount + (int)$emptyCylinderCount + (int)$issuedCylinderCount) ?></div>
                    <div class="dashboard-sub"><?= number_format((int)$filledCylinderCount) ?> filled · <?= number_format((int)$emptyCylinderCount) ?> empty · <?= number_format((int)$issuedCylinderCount) ?> issued</div>
                    <div class="dashboard-hint">Filled + empty in shop + issued to customers</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card filled-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-filled">
                    <div class="dashboard-card-top"><span class="dashboard-icon gas"><i class="bi bi-fuel-pump-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Total Filled Cylinders</div>
                    <div class="dashboard-value"><?= number_format((int)$filledCylinderCount) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($filledCompanies)) ?> source(s) · <?= number_format(count($gasTypes)) ?> types</div>
                    <div class="dashboard-hint">Company → plant → cylinder type</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card empty-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-empty">
                    <div class="dashboard-card-top"><span class="dashboard-icon empty"><i class="bi bi-circle-square"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Empty Gas Cylinders in Shop</div>
                    <div class="dashboard-value"><?= number_format((int)$emptyCylinderCount) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($emptyTypes)) ?> cylinder types available</div>
                    <div class="dashboard-hint">Cylinder type → individual cylinder</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card sale-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-sales">
                    <div class="dashboard-card-top"><span class="dashboard-icon sale"><i class="bi bi-cart-check-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Sales</div>
                    <div class="dashboard-value"><?= $money((float)$saleSummary['total']) ?></div>
                    <div class="dashboard-sub"><?= number_format((int)$saleSummary['count']) ?> sales · <?= $range['isToday'] ? 'Today' : esc($range['label']) ?></div>
                    <div class="dashboard-payment-mini"><span>Cash <?= $money((float)$saleSummary['cash']) ?></span><span>Digital <?= $money((float)$saleSummary['online']) ?></span><span>Credit <?= $money((float)$saleSummary['credit']) ?></span></div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card expense-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-expenses">
                    <div class="dashboard-card-top"><span class="dashboard-icon expense"><i class="bi bi-receipt-cutoff"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Expenses</div>
                    <div class="dashboard-value"><?= $money((float)$expenseTotal) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($expenseRows)) ?> expense entries</div>
                    <div class="dashboard-hint">Category and payment breakdown</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card cash-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-cash">
                    <div class="dashboard-card-top"><span class="dashboard-icon cash"><i class="bi bi-cash-stack"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Cash Counter</div>
                    <div class="dashboard-value"><?= $money((float)$cashIn - (float)$cashOut) ?></div>
                    <div class="dashboard-sub">In <?= $money((float)$cashIn) ?> · Out <?= $money((float)$cashOut) ?></div>
                    <div class="dashboard-hint"><?= number_format(count($cashRegisters)) ?> register(s) in selected period</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card issued-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-issued">
                    <div class="dashboard-card-top"><span class="dashboard-icon issued"><i class="bi bi-person-badge-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Issued Cylinders</div>
                    <div class="dashboard-value"><?= number_format((int)$issuedCylinderCount) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($issuedCustomers)) ?> customers · <?= $money((float)$issuedDepositTotal) ?> deposit</div>
                    <div class="dashboard-hint">Customer → cylinder details</div>
                </button>
            </div>
        </div>

        <div class="dashboard-note mt-3"><i class="bi bi-info-circle me-2"></i>Purchases, Sales, Cash Counter and Expenses follow the selected date range. Receivable (from customers) and Payable (to suppliers) are current balances as of today.</div>

        <div class="d-none">
            <div id="detail-purchases">
                <div class="detail-kpi-grid"><div><span>Total Purchases</span><strong><?= $money((float)$purchaseSummary['total']) ?></strong></div><div><span>Paid</span><strong><?= $money((float)$purchaseSummary['paid']) ?></strong></div><div><span>Pending</span><strong><?= $money((float)$purchaseSummary['pending']) ?></strong></div><div><span>Purchases</span><strong><?= number_format((int)$purchaseSummary['count']) ?></strong></div><div><span>Cylinders</span><strong><?= number_format((int)$purchaseSummary['cylinders']) ?></strong></div></div>
                <?php if(!$purchaseCompanies): ?><div class="alert alert-light border">No purchases found for the selected date range.</div><?php endif; ?>
                <?php foreach($purchaseCompanies as $company): ?>
                    <details class="dashboard-drill-card"><summary><span class="type-color issued"></span><strong><?= esc($company['name']) ?></strong><span class="summary-meta"><?= number_format((int)$company['cylinders']) ?> cylinders · <?= $money((float)$company['total']) ?> · Paid <?= $money((float)$company['paid']) ?> · Pending <?= $money((float)$company['pending']) ?></span></summary>
                        <div class="drill-nested">
                        <?php foreach($company['plants'] as $plant): ?>
                            <details class="dashboard-drill-card"><summary><i class="bi bi-building text-muted"></i><strong><?= esc($plant['name']) ?></strong><span class="summary-meta"><?= number_format((int)$plant['cylinders']) ?> cylinders · <?= $money((float)$plant['total']) ?> · Paid <?= $money((float)$plant['paid']) ?> · Pending <?= $money((float)$plant['pending']) ?></span></summary>
                                <div class="table-responsive"><table class="table table-sm align-middle mb-0 purchase-items"><thead><tr><th>Purchase</th><th>Date</th><th>Items</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Pending</th></tr></thead><tbody>
                                <?php foreach($plant['purchases'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['purchase_no']) ?></td><td class="text-nowrap"><?= esc(date('d M Y, h:i A',strtotime((string)$row['transaction_at']))) ?></td><td><?php foreach($row['items'] as $it): ?><div class="item-line"><?= number_format((int)$it['quantity']) ?> × <?= esc($it['type_code'] ?: 'Gas') ?> <span class="text-muted"><?= esc(ucwords(str_replace('_',' ',$it['line_type']))) ?></span></div><?php endforeach; ?></td><td class="text-end fw-semibold"><?= $money((float)$row['total_amount']) ?></td><td class="text-end"><?= $money((float)$row['paid']) ?></td><td class="text-end <?= (float)$row['credit_amount']>0.005?'amount-due':'amount-clear' ?>"><?= $money((float)$row['credit_amount']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
                            </details>
                        <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div id="detail-filled">
                <div class="detail-intro gas-bg"><i class="bi bi-fuel-pump-fill"></i><div><strong><?= number_format((int)$filledCylinderCount) ?> cylinders</strong><span>filled cylinders in shop, by company and plant</span></div></div>
                <?php if(!$filledCompanies): ?><div class="alert alert-light border">No filled cylinders are currently available.</div><?php endif; ?>
                <?php foreach($filledCompanies as $company): ?>
                    <details class="dashboard-drill-card"><summary><span class="type-color gas"></span><strong><?= esc($company['name']) ?></strong><span class="summary-meta"><?= number_format((int)$company['count']) ?> filled cylinders</span></summary>
                        <div class="drill-nested">
                        <?php foreach($company['plants'] as $plant): ?>
                            <details class="dashboard-drill-card"><summary><i class="bi bi-building text-muted"></i><strong><?= esc($plant['name']) ?></strong><span class="summary-meta"><?= number_format((int)$plant['count']) ?> filled cylinders</span></summary>
                                <div class="table-responsive"><table class="table table-sm align-middle mb-0 drill-table"><thead><tr><th class="col-type">Cylinder Type</th><th class="col-count">Filled</th><th class="col-detail">Cylinders</th></tr></thead><tbody>
                                <?php foreach($plant['types'] as $type): $n=count($type['units']); ?><tr><td class="fw-semibold"><?= esc($type['name']) ?></td><td class="col-count"><span class="badge text-bg-success"><?= number_format($n) ?></span></td><td><details class="cyl-toggle"><summary>View <?= number_format($n) ?> cylinder(s)</summary><div class="cyl-chips"><?php foreach($type['units'] as $code): ?><span class="cyl-chip"><?= esc($code) ?></span><?php endforeach; ?></div></details></td></tr><?php endforeach; ?>
                                </tbody></table></div>
                            </details>
                        <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div id="detail-gas">
                <div class="detail-intro gas-bg"><i class="bi bi-fuel-pump-fill"></i><div><strong><?= number_format((float)$availableGasKg,2) ?> KG</strong><span>available gas in filled cylinders</span></div></div>
                <?php if (!$gasTypes): ?><div class="alert alert-light border">No filled cylinders are currently available.</div><?php endif; ?>
                <?php foreach($gasTypes as $type): ?>
                    <details class="dashboard-drill-card" data-detail-type="gas">
                        <summary><span class="type-color gas"></span><strong><?= esc($type['code'].' — '.$type['name']) ?></strong><span class="ms-auto"><?= number_format((float)$type['gas_kg'],2) ?> KG · <?= number_format((int)$type['cylinder_count']) ?> cylinders</span></summary>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th>Capacity</th><th>Gas KG</th><th>Status</th><th>Updated</th></tr></thead><tbody>
                        <?php foreach($type['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= number_format((float)$row['capacity_kg'],2) ?> KG</td><td class="fw-semibold"><?= number_format((float)$row['gas_weight_kg'],2) ?> KG</td><?php $gasKg=(float)$row['gas_weight_kg']; $capacityKg=(float)$row['capacity_kg']; $cylinderStatus=$gasKg<=0.00001?'Empty':($gasKg<$capacityKg-0.00001?'Partially Filled':'Filled'); $statusClass=$cylinderStatus==='Empty'?'text-bg-warning':($cylinderStatus==='Partially Filled'?'text-bg-info':'text-bg-success'); ?>
                        <td><span class="badge <?= $statusClass ?>"><?= esc($cylinderStatus) ?></span></td><td><?= esc($row['updated_at']) ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div id="detail-empty">
                <div class="detail-intro empty-bg"><i class="bi bi-circle-square"></i><div><strong><?= number_format((int)$emptyCylinderCount) ?> cylinders</strong><span>empty physical cylinders in shop</span></div></div>
                <?php if (!$emptyTypes): ?><div class="alert alert-light border">No empty cylinders are currently in the shop.</div><?php endif; ?>
                <?php foreach($emptyTypes as $type): ?>
                    <details class="dashboard-drill-card"><summary><span class="type-color empty"></span><strong><?= esc($type['code'].' — '.$type['name']) ?></strong><span class="ms-auto"><?= number_format((int)$type['count']) ?> cylinders</span></summary>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th>Capacity</th><th>Gas KG</th><th>Status</th><th>Updated</th></tr></thead><tbody>
                        <?php foreach($type['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= number_format((float)$row['capacity_kg'],2) ?> KG</td><td>0.00 KG</td><td><span class="badge text-bg-warning">Empty</span></td><td><?= esc($row['updated_at']) ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div id="detail-issued">
                <div class="detail-intro issued-bg"><i class="bi bi-person-badge-fill"></i><div><strong><?= number_format((int)$issuedCylinderCount) ?> cylinders</strong><span>currently issued to customers</span></div></div>
                <?php if (!$issuedCustomers): ?><div class="alert alert-light border">No cylinders are currently issued to customers.</div><?php endif; ?>
                <?php foreach($issuedCustomers as $customer): ?>
                    <details class="dashboard-drill-card"><summary><span class="type-color issued"></span><strong><?= esc($customer['name']) ?></strong><span class="small text-muted ms-2"><?= esc($customer['code']) ?></span><span class="ms-auto"><?= number_format((int)$customer['count']) ?> cylinders · <?= $money((float)$customer['deposit']) ?></span></summary>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th>Type</th><th>Condition</th><th>Gas KG</th><th>Issued</th><th>Deposit</th></tr></thead><tbody>
                        <?php foreach($customer['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= esc($row['type_code'].' — '.$row['type_name']) ?></td><td><?= esc(ucfirst($row['issued_condition'])) ?></td><td><?= number_format((float)$row['gas_weight_kg'],2) ?></td><td><?= esc($row['issued_at']) ?></td><td><?= $money((float)$row['deposit_amount']) ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div id="detail-sales">
                <div class="detail-kpi-grid">
                    <div><span>Total</span><strong><?= $money((float)$saleSummary['total']) ?></strong></div><div><span>Cash</span><strong><?= $money((float)$saleSummary['cash']) ?></strong></div><?php foreach($saleSummary['by_mode'] as $bmKey=>$bm): if($bmKey==='cash') continue; ?><div><span><?= esc($bm['label']) ?></span><strong><?= $money((float)$bm['amount']) ?></strong></div><?php endforeach; ?><div><span>Credit</span><strong><?= $money((float)$saleSummary['credit']) ?></strong></div>
                </div>
                <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Sale No.</th><th>Date / Time</th><th>Customer</th><th>Type</th><th class="text-end">Total</th><th class="text-end">Cash</th><th class="text-end">Online</th><th class="text-end">Credit</th></tr></thead><tbody>
                <?php foreach($salesRows as $row): ?><tr><td class="fw-semibold"><?= esc($row['sale_no']) ?></td><td><?= esc($row['transaction_at']) ?></td><td><?= esc($row['customer_name'] ?: 'Walk-in / Cash') ?></td><td><?= esc(ucwords(str_replace('_',' ',$row['transaction_type']))) ?></td><td class="text-end fw-semibold"><?= $money((float)$row['total_amount']) ?></td><td class="text-end"><?= $money((float)$row['cash_amount']) ?></td><td class="text-end"><?= $money((float)$row['online_amount']) ?></td><td class="text-end"><?= $money((float)$row['credit_amount']) ?></td></tr><?php endforeach; ?>
                <?php if(!$salesRows): ?><tr><td colspan="8" class="text-center text-muted py-4">No sales found for the selected date range.</td></tr><?php endif; ?></tbody></table></div>
            </div>

            <div id="detail-cash">
                <div class="detail-kpi-grid"><div><span>Cash In</span><strong><?= $money((float)$cashIn) ?></strong></div><div><span>Cash Out</span><strong><?= $money((float)$cashOut) ?></strong></div><div><span>Net Counter Movement</span><strong><?= $money((float)$cashIn-(float)$cashOut) ?></strong></div></div>
                <?php if(!$cashRegisters): ?><div class="alert alert-light border">No cash movements found for the selected date range.</div><?php endif; ?>
                <?php foreach($cashRegisters as $register): ?><details class="dashboard-drill-card"><summary><span class="type-color cash"></span><strong><?= esc($register['code'].' — '.$register['name']) ?></strong><span class="ms-auto">In <?= $money((float)$register['in']) ?> · Out <?= $money((float)$register['out']) ?></span></summary>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date / Time</th><th>Movement</th><th>Direction</th><th class="text-end">Amount</th><th>Reference</th></tr></thead><tbody>
                    <?php foreach($register['rows'] as $row): ?><tr><td><?= esc($row['transaction_at']) ?></td><td><?= esc(ucwords(str_replace('_',' ',$row['transaction_type']))) ?></td><td><span class="badge <?= $row['direction']==='in'?'text-bg-success':'text-bg-danger' ?>"><?= strtoupper(esc($row['direction'])) ?></span></td><td class="text-end fw-semibold"><?= $money((float)$row['amount']) ?></td><td><?= esc(($row['reference_type']??'').' '.($row['reference_id']??'')) ?></td></tr><?php endforeach; ?></tbody></table></div>
                </details><?php endforeach; ?>
            </div>

            <div id="detail-receivable">
                <div class="detail-intro receivable-bg"><i class="bi bi-person-lines-fill"></i><div><strong><?= $money((float)$receivableTotal) ?></strong><span>current outstanding customer OS as of today</span></div></div>
                <?php if(!$receivables): ?><div class="alert alert-light border">No customer receivables are currently outstanding.</div><?php endif; ?>
                <?php foreach($receivables as $customer): ?><details class="dashboard-drill-card"><summary><span class="type-color receivable"></span><strong><?= esc($customer['name']) ?></strong><span class="small text-muted ms-2"><?= esc($customer['code']) ?></span><span class="ms-auto"><?= $money((float)$customer['balance']) ?> due</span></summary>
                    <div class="p-3 border-top bg-light-subtle"><div class="row g-2 small mb-2"><div class="col-md-4">Opening: <strong><?= $money((float)$customer['opening_balance']) ?></strong></div><div class="col-md-4">Credit sales: <strong><?= $money((float)$customer['credit_sales']) ?></strong></div><div class="col-md-4">Receipts: <strong><?= $money((float)$customer['receipts']) ?></strong></div></div>
                    <?php $ledger=$receivableLedger[$customer['id']]??[]; ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Reference</th><th class="text-end">Credit</th><th class="text-end">Receipt</th></tr></thead><tbody>
                    <?php foreach($ledger as $row): ?><tr><td><?= esc($row['transaction_at']) ?></td><td><?= esc($row['sale_no']??$row['receipt_no']??'') ?></td><td class="text-end"><?= $money((float)$row['credit_amount']) ?></td><td class="text-end"><?= $money((float)$row['receipt_amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
                </details><?php endforeach; ?>
            </div>

            <div id="detail-profit">
                <?php $pl=$profitLoss; $plRow=static fn($label,$note,$val,$neg=false)=>'<tr><td>'.$label.'<div class="small text-muted">'.$note.'</div></td><td class="text-end fw-semibold">'.($neg?'− ':'').'Rs. '.number_format(abs((float)$val),2).'</td></tr>'; ?>
                <div class="detail-intro receivable-bg"><i class="bi bi-graph-up-arrow"></i><div><strong class="<?= (float)$pl['gross']>=0?'text-success':'text-danger' ?>"><?= $money((float)$pl['gross']) ?></strong><span>gross profit on sales of <?= $money((float)$pl['revenue']) ?> for <?= esc($range['label']) ?> · <?= esc($costMethodLabel) ?> cost</span></div></div>
                <div id="profitDrill" class="mb-3" data-range="<?= esc($range['label']) ?>" data-qs="<?= esc('from='.$range['from'].'&to='.$range['to'],'attr') ?>" data-url-customers="<?= site_url('dashboard/profit/customers') ?>" data-url-sales="<?= site_url('dashboard/profit/sales') ?>" data-url-sale="<?= site_url('dashboard/profit/sale') ?>"><div class="text-muted small">Loading…</div></div>
                <details class="mb-2"><summary class="small fw-semibold">Profit &amp; loss summary (after wastage and expenses)</summary>
                <div class="table-responsive"><table class="table table-sm align-middle">
                    <tbody>
                    <?= $plRow('Sales revenue','Gas Sale, Cylinder Sale and Security Deposit gas charges',$pl['revenue']) ?>
                    <?= $plRow('Cost of goods sold',$costMethodLabel.' cost price saved on each sale when it was posted',$pl['cogs'],true) ?>
                    <tr class="table-light"><td class="fw-bold">Gross profit</td><td class="text-end fw-bold"><?= $money((float)$pl['gross']) ?></td></tr>
                    <?= $plRow('Gas wastage',number_format($pl['wastage_kg'],3).' KG at average gas cost',$pl['wastage_cost'],true) ?>
                    <?= $plRow('Expenses','Expenses recorded in the period',$pl['expenses'],true) ?>
                    <tr class="table-light"><td class="fw-bold">Net <?= (float)$pl['net']>=0?'profit':'loss' ?></td><td class="text-end fw-bold <?= (float)$pl['net']>=0?'text-success':'text-danger' ?>"><?= $money((float)$pl['net']) ?></td></tr>
                    </tbody></table></div></details>
                <div class="dashboard-note"><i class="bi bi-info-circle me-2"></i>Cost is saved on each sale when it is posted, using the Cost Price Method in Shop Settings (changing it never alters earlier sales). Sales posted before this was introduced are estimated with the average purchase cost and marked "est.". Security deposits and cylinder returns are not revenue.</div>
            </div>

            <div id="detail-payable">
                <div class="detail-intro receivable-bg"><i class="bi bi-truck"></i><div><strong><?= $money((float)$payableTotal) ?></strong><span>current amount payable to suppliers as of today</span></div></div>
                <?php if(!$payables): ?><div class="alert alert-light border">No supplier payables are currently outstanding.</div><?php endif; ?>
                <?php foreach($payables as $sup): ?><details class="dashboard-drill-card"><summary><span class="type-color receivable"></span><strong><?= esc($sup['name']) ?></strong><span class="small text-muted ms-2"><?= esc($sup['code']) ?></span><span class="ms-auto"><?= $money((float)$sup['balance']) ?> payable</span></summary>
                    <div class="p-3 border-top bg-light-subtle"><div class="row g-2 small mb-2"><div class="col-md-4">Opening: <strong><?= $money((float)$sup['opening_balance']) ?></strong></div><div class="col-md-4">Credit purchases: <strong><?= $money((float)$sup['credit_purchases']) ?></strong></div><div class="col-md-4">Payments: <strong><?= $money((float)$sup['payments']) ?></strong></div></div>
                    <?php $ledger=$payableLedger[$sup['id']]??[]; ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Reference</th><th class="text-end">Credit Purchase</th><th class="text-end">Payment</th></tr></thead><tbody>
                    <?php foreach($ledger as $row): ?><tr><td><?= esc($row['transaction_at']) ?></td><td><?= esc($row['reference']) ?></td><td class="text-end"><?= $money((float)$row['credit_amount']) ?></td><td class="text-end"><?= $money((float)$row['payment_amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
                </details><?php endforeach; ?>
            </div>

            <div id="detail-expenses">
                <div class="detail-kpi-grid"><div><span>Total Expenses</span><strong><?= $money((float)$expenseTotal) ?></strong></div><div><span>Entries</span><strong><?= number_format(count($expenseRows)) ?></strong></div></div>
                <div class="row g-2 mb-3"><?php foreach($expenseCategories as $cat): ?><div class="col-md-4"><div class="expense-category"><span><?= esc($cat['name']) ?></span><strong><?= $money((float)$cat['amount']) ?></strong><small><?= number_format((int)$cat['count']) ?> entries</small></div></div><?php endforeach; ?></div>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Expense No.</th><th>Date</th><th>Category</th><th>Mode</th><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>
                <?php foreach($expenseRows as $row): ?><tr><td class="fw-semibold"><?= esc($row['expense_no']) ?></td><td><?= esc($row['expense_at']) ?></td><td><?= esc($row['category_name']??'Other') ?></td><td><span class="badge text-bg-secondary"><?= esc(ucfirst($row['payment_mode'])) ?></span></td><td><?= esc($row['description']??'') ?></td><td class="text-end fw-semibold"><?= $money((float)$row['amount']) ?></td></tr><?php endforeach; ?>
                <?php if(!$expenseRows): ?><tr><td colspan="6" class="text-center text-muted py-4">No expenses found for the selected date range.</td></tr><?php endif; ?></tbody></table></div>
            </div>
        </div>

        <div class="modal fade" id="dashboardDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="dashboardDetailTitle">Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body" id="dashboardDetailBody"></div>
            </div></div>
        </div>
    </div>

    <div class="tab-pane fade <?= $stockTab !== '' ? 'show active' : '' ?>" id="stock-pane" role="tabpanel" aria-labelledby="stock-tab">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="mb-1">Current Stock Details</h5>
                <div class="text-muted small">Live physical cylinder position in this branch.</div>
            </div>
            <a href="<?= site_url('reports/inventory-detail') ?>" class="btn btn-sm btn-outline-secondary">Inventory Detail Report</a>
        </div>

        <div class="row g-3 mb-4">
            <?php
            $stockCards = [
                'filled' => ['title'=>'Filled Cylinders','icon'=>'bi-fuel-pump-fill','value'=>number_format($stock['filled']['count']),'sub'=>$qty((float)$stock['filled']['gas_kg']).' KG gas'],
                'empty' => ['title'=>'Empty Cylinders','icon'=>'bi-circle-square','value'=>number_format($stock['empty']['count']),'sub'=>'Empty physical cylinders'],
                'issued' => ['title'=>'Issued Temporarily Against Deposit','icon'=>'bi-person-badge','value'=>number_format($stock['issued']['count']),'sub'=>$money((float)$stock['issued']['deposit']).' deposit held'],
            ];
            foreach ($stockCards as $key=>$card):
                $active = $stockTab === $key;
                $url = site_url('dashboard?from='.urlencode($range['from']).'&to='.urlencode($range['to']).'&stock_tab='.$key);
            ?>
            <div class="col-md-4">
                <a href="<?= esc($url) ?>" class="text-decoration-none stock-type-card">
                    <div class="card h-100 shadow-sm <?= $active ? 'border-primary border-2' : 'border-0' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small"><?= esc($card['title']) ?></div>
                                <i class="bi <?= esc($card['icon']) ?> fs-5 text-primary"></i>
                            </div>
                            <div class="display-6 fw-semibold mt-2"><?= esc($card['value']) ?></div>
                            <div class="small text-muted mt-1"><?= esc($card['sub']) ?></div>
                            <div class="small text-primary mt-3"><i class="bi bi-arrow-right-circle me-1"></i>View details</div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <strong><?= esc($stock[$displayStockTab]['title']) ?></strong>
                <span class="badge text-bg-secondary float-end"><?= number_format((int)$stock[$displayStockTab]['count']) ?> record(s)</span>
            </div>

            <?php if ($displayStockTab === 'filled'): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 datatable">
                        <thead>
                            <tr><th>#</th><th>Physical Cylinder</th><th>Type</th><th>Capacity KG</th><th>Current Gas KG</th><th>Fill Status</th><th>Last Updated</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stock['filled']['rows'] as $i=>$row): ?>
                            <?php $isPartial = (float)$row['gas_weight_kg'] < (float)$row['capacity_kg']; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= esc($row['unit_code']) ?></td>
                                <td><?= esc($row['type_code'].' — '.$row['type_name']) ?></td>
                                <td><?= $qty((float)$row['capacity_kg']) ?></td>
                                <td class="fw-semibold"><?= $qty((float)$row['gas_weight_kg']) ?></td>
                                <td><span class="badge <?= $isPartial ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $isPartial ? 'Partially Filled' : 'Full' ?></span></td>
                                <td><?= esc($row['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($displayStockTab === 'empty'): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 datatable">
                        <thead>
                            <tr><th>#</th><th>Physical Cylinder</th><th>Type</th><th>Capacity KG</th><th>Gas KG</th><th>Last Updated</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stock['empty']['rows'] as $i=>$row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= esc($row['unit_code']) ?></td>
                                <td><?= esc($row['type_code'].' — '.$row['type_name']) ?></td>
                                <td><?= $qty((float)$row['capacity_kg']) ?></td>
                                <td>0.00</td>
                                <td><?= esc($row['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 datatable">
                        <thead>
                            <tr><th>#</th><th>Physical Cylinder</th><th>Type</th><th>Capacity KG</th><th>Customer</th><th>Phone</th><th>Deposit</th><th>Issued At</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stock['issued']['rows'] as $i=>$row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= esc($row['unit_code']) ?></td>
                                <td><?= esc($row['type_code'].' — '.$row['type_name']) ?></td>
                                <td><?= $qty((float)$row['capacity_kg']) ?></td>
                                <td><strong><?= esc($row['customer_name']) ?></strong><div class="small text-muted"><?= esc($row['customer_code'] ?? '') ?></div></td>
                                <td><?= esc($row['customer_phone'] ?? '') ?></td>
                                <td class="fw-semibold"><?= $money((float)$row['deposit_amount']) ?></td>
                                <td><?= esc($row['issued_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!$stock[$displayStockTab]['rows']): ?>
                <div class="alert alert-light border rounded-0 mb-0">No <?= esc(strtolower($stock[$displayStockTab]['title'])) ?> found in the current physical stock.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
