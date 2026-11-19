<?php
/**
 * Date range + rows-per-page + pagination for a ledger Details table.
 * Usage (inside the tab pane, above the table): <?= view('partials/ledger_details_tools', ['paneId' => 'customerDetails']) ?>
 * Rows are the `tr.sl-row` of the pane's `.sl-details` table. Rows outside the date range get data-f="1"
 * so the export / share code can leave them out; rows outside the current page are only hidden.
 */
$paneId = $paneId ?? '';
?>
<div class="d-flex flex-wrap gap-2 align-items-end mb-2 sl-tools" data-pane="<?= esc($paneId, 'attr') ?>">
  <div><label class="form-label small mb-0">Date range</label>
    <select class="form-select form-select-sm sl-preset">
      <option value="all">All time</option><option value="today">Today</option><option value="yesterday">Yesterday</option>
      <option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="month">Current month</option>
      <option value="lastmonth">Last month</option><option value="year">Current year</option><option value="custom">Custom</option>
    </select></div>
  <div><label class="form-label small mb-0">From</label><input type="date" class="form-control form-control-sm sl-from"></div>
  <div><label class="form-label small mb-0">To</label><input type="date" class="form-control form-control-sm sl-to"></div>
  <div><label class="form-label small mb-0">Rows per page</label>
    <select class="form-select form-select-sm sl-size"><option>10</option><option>25</option><option>50</option><option>100</option></select></div>
  <div class="small text-muted ms-auto sl-info"></div>
</div>
<script>
(function(){
  var tools=document.currentScript.previousElementSibling;
  function init(){
  var pane=document.getElementById(tools.dataset.pane);if(!pane)return;
  var preset=tools.querySelector('.sl-preset'),from=tools.querySelector('.sl-from'),to=tools.querySelector('.sl-to'),size=tools.querySelector('.sl-size'),info=tools.querySelector('.sl-info');
  var table=pane.querySelector('.sl-details'),tbody=table.tBodies[0];
  var rows=[].slice.call(tbody.querySelectorAll('tr.sl-row'));
  var page=1;
  var pager=document.createElement('div');pager.className='d-flex justify-content-end align-items-center gap-1 mt-2 flex-wrap';
  table.closest('.table-responsive').insertAdjacentElement('afterend',pager);
  var none=document.createElement('tr');none.innerHTML='<td colspan="9" class="text-center text-muted py-4">No rows in this date range.</td>';none.style.display='none';tbody.appendChild(none);
  function iso(d){var m=d.getMonth()+1,day=d.getDate();return d.getFullYear()+'-'+(m<10?'0':'')+m+'-'+(day<10?'0':'')+day;}
  function applyPreset(){
    var v=preset.value,now=new Date(),t=iso(now),d;
    if(v==='all'){from.value='';to.value='';}
    else if(v==='today'){from.value=t;to.value=t;}
    else if(v==='yesterday'){d=new Date(now);d.setDate(d.getDate()-1);from.value=to.value=iso(d);}
    else if(v==='7'||v==='30'){d=new Date(now);d.setDate(d.getDate()-(+v-1));from.value=iso(d);to.value=t;}
    else if(v==='month'){from.value=iso(new Date(now.getFullYear(),now.getMonth(),1));to.value=t;}
    else if(v==='lastmonth'){from.value=iso(new Date(now.getFullYear(),now.getMonth()-1,1));to.value=iso(new Date(now.getFullYear(),now.getMonth(),0));}
    else if(v==='year'){from.value=iso(new Date(now.getFullYear(),0,1));to.value=t;}
  }
  function render(){
    var f=from.value,t=to.value,n=+size.value,list=[];
    rows.forEach(function(r){
      var day=r.cells[0].textContent.trim().slice(0,10),ok=(!f||day>=f)&&(!t||day<=t);
      r.dataset.f=ok?'0':'1';if(ok)list.push(r);else r.style.display='none';
    });
    var pages=Math.max(1,Math.ceil(list.length/n));if(page>pages)page=pages;
    list.forEach(function(r,i){r.style.display=(i>=(page-1)*n&&i<page*n)?'':'none';});
    none.style.display=(rows.length&&!list.length)?'':'none';
    var a=list.length?(page-1)*n+1:0,b=Math.min(list.length,page*n);
    info.textContent='Showing '+a+'-'+b+' of '+list.length+' entries'+(list.length!==rows.length?' (filtered from '+rows.length+')':'');
    pager.innerHTML='';
    function btn(label,p,dis,act){var b=document.createElement('button');b.type='button';b.className='btn btn-sm '+(act?'btn-primary':'btn-outline-secondary');b.textContent=label;b.disabled=!!dis;b.onclick=function(){page=p;render();};pager.appendChild(b);}
    if(list.length<=n)return;
    btn('Previous',page-1,page<=1);
    var s=Math.max(1,page-2),e=Math.min(pages,s+4);s=Math.max(1,e-4);
    for(var p=s;p<=e;p++)btn(String(p),p,false,p===page);
    btn('Next',page+1,page>=pages);
  }
  preset.onchange=function(){if(preset.value!=='custom')applyPreset();page=1;render();};
  from.onchange=to.onchange=function(){preset.value='custom';page=1;render();};
  size.onchange=function(){page=1;render();};
  render();
  }
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init);else init();
})();
</script>
