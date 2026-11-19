<?= $this->extend('layouts/app') ?><?= $this->section('styles') ?>
<style>
.sl-details th{white-space:nowrap;font-size:.74rem;letter-spacing:.03em;text-transform:uppercase;color:#475569;background:#f1f5f9}
.sl-details td{vertical-align:middle}
.sl-details .num{text-align:right;white-space:nowrap}
.sl-details tr.sl-void td{text-decoration:line-through;color:#8a949e;background:#fafafa}
.sl-details tr.sl-void td.sl-act{text-decoration:none}
.sl-details tr.sl-open td{background:#f8fafc;font-weight:700}
.sl-badge-void{display:inline-block;margin-left:.35rem;font-size:.68rem;font-weight:700;color:#b42318;text-decoration:none!important}
</style>
<?= $this->endSection() ?><?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h4 class="mb-1"><?=esc($supplier['name'])?></h4><div class="text-muted small"><?=esc($supplier['phone']??'')?><?=!empty($supplier['city'])?' · '.esc($supplier['city']):''?></div></div>
  <a class="btn btn-outline-secondary" href="<?=site_url('suppliers')?>"><i class="bi bi-arrow-left me-1"></i>Back to Suppliers</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Opening Balance</div><h5 class="mb-0">Rs. <?=number_format((float)$supplier['opening_balance'],2)?></h5></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Credit Limit</div><h5 class="mb-0">Rs. <?=number_format((float)$supplier['credit_limit'],2)?></h5></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Current Balance Due</div><h5 class="mb-0">Rs. <?=number_format((float)$supplier['credit_due'],2)?></h5></div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white">
    <ul class="nav nav-tabs card-header-tabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#supplierOverview" type="button"><i class="bi bi-person-vcard me-1"></i>Overview</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplierPurchases" type="button"><i class="bi bi-bag-check me-1"></i>Purchases <span class="badge text-bg-secondary"><?=count($purchases)?></span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplierPayments" type="button"><i class="bi bi-cash-coin me-1"></i>Payments <span class="badge text-bg-secondary"><?=count($payments)?></span></button></li>
      <li class="nav-item"><button class="nav-link" id="supplierDetailsTab" data-bs-toggle="tab" data-bs-target="#supplierDetails" type="button"><i class="bi bi-journal-text me-1"></i>Details</button></li>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">
      <div class="tab-pane fade show active" id="supplierOverview">
        <div class="row g-4">
          <div class="col-lg-6"><div class="border rounded p-4 h-100"><h6 class="fw-semibold">Account Summary</h6><div class="d-flex justify-content-between py-2 border-bottom"><span>Opening Balance</span><strong>Rs. <?=number_format((float)$supplier['opening_balance'],2)?></strong></div><div class="d-flex justify-content-between py-2"><span>Current Balance Due</span><strong>Rs. <?=number_format((float)$supplier['credit_due'],2)?></strong></div></div></div>
          <div class="col-lg-6"><div class="border rounded p-4 h-100"><h6 class="fw-semibold">Supplier Details</h6><div class="small text-muted mb-1">Code</div><div class="mb-2"><?=esc($supplier['code']??'—')?></div><div class="small text-muted mb-1">Phone</div><div class="mb-2"><?=esc($supplier['phone']??'—')?></div><div class="small text-muted mb-1">City</div><div class="mb-2"><?=esc($supplier['city']??'—')?></div><div class="small text-muted mb-1">Address</div><div><?=esc($supplier['address']??'—')?></div></div></div>
        </div>
      </div>
      <div class="tab-pane fade" id="supplierPurchases">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>Purchase</th><th>Total</th><th>Paid</th><th>Added to Payable</th><th>Status</th></tr></thead><tbody>
        <?php foreach($purchases as $r): ?><tr><td><?=esc($r['transaction_at'])?></td><td><?=esc($r['purchase_no'])?></td><td>Rs. <?=number_format((float)$r['total_amount'],2)?></td><td>Rs. <?=number_format(max(0,(float)$r['paid_amount']),2)?></td><td>Rs. <?=number_format(max(0,(float)$r['credit_amount']),2)?></td><td><?=esc(ucfirst($r['status']))?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>
      <div class="tab-pane fade" id="supplierPayments">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>Payment</th><th>Amount</th><th>Method</th><th>Applied To</th><th>Status</th></tr></thead><tbody>
        <?php foreach($payments as $r): ?><tr><td><?=esc($r['payment_at'])?></td><td><?=esc($r['payment_no'])?></td><td>Rs. <?=number_format((float)$r['amount'],2)?></td><td><?=esc(ucfirst($r['payment_mode']))?></td><td><?=esc($r['source'])?><?=!empty($r['purchase_no'])?' · '.esc($r['purchase_no']):''?></td><td><?=esc(ucfirst($r['status']))?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>
      <div class="tab-pane fade" id="supplierDetails">
        <div class="d-flex flex-wrap gap-2 justify-content-end mb-2">
          <button type="button" class="btn btn-sm btn-outline-success" id="slExportCsv"><i class="bi bi-file-earmark-spreadsheet"></i> Export Excel / CSV</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="slExportPdf"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
          <button type="button" class="btn btn-sm btn-success" id="slWhatsapp" data-phone="<?=esc((string)($supplier['phone']??''),'attr')?>"><i class="bi bi-whatsapp"></i> Share on WhatsApp</button>
        </div>
        <?= view('partials/ledger_details_tools', ['paneId' => 'supplierDetails']) ?>
        <div class="table-responsive"><table class="table table-sm align-middle sl-details mb-0"><thead><tr>
          <th>Date</th><th>Reference</th><th>Payment Type</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Added to Payable</th><th class="num">Payment Made</th><th class="num">Balance Due</th><th></th>
        </tr></thead><tbody>
          <tr class="sl-open"><td colspan="7">Opening Balance</td><td class="num">Rs. <?=number_format((float)$supplier['opening_balance'],2)?></td><td></td></tr>
          <?php $m=static fn($v)=>$v===null?'':'Rs. '.number_format((float)$v,2); ?>
          <?php foreach($details as $d): $void=$d['status']==='voided'; ?>
          <tr class="sl-row <?=$void?'sl-void':''?>" data-void="<?=$void?1:0?>">
            <td><?=esc($d['at'])?></td>
            <td><?=esc($d['ref'])?><?php if($void): ?><span class="sl-badge-void">(Voided)</span><?php endif; ?></td>
            <td><?=esc($d['type'])?></td>
            <td class="num"><?=$m($d['amount'])?></td>
            <td class="num"><?=$m($d['paid'])?></td>
            <td class="num"><?=$m($d['added'])?></td>
            <td class="num"><?=$m($d['made'])?></td>
            <td class="num">Rs. <?=number_format((float)$d['balance'],2)?></td>
            <td class="sl-act text-end"><?php if(!$void && !empty($canVoid) && $d['voidable']): ?><button type="button" class="btn btn-sm btn-outline-danger sl-void-btn" data-action="<?=site_url($d['kind']==='purchase'?'purchases/void/'.$d['id']:'suppliers/payments/void/'.$d['id'])?>" data-ref="<?=esc($d['ref'])?>">Void</button><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$details): ?><tr><td colspan="9" class="text-center text-muted py-4">No purchases or payments yet.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if(empty($canVoid)): ?><div class="small text-muted mt-2">Void is available to users authorised for it in Shop Settings.</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="slVoidModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" id="slVoidForm">
  <?= csrf_field() ?>
  <input type="hidden" name="return_to" value="/suppliers/ledger/<?=(int)$supplier['id']?>">
  <div class="modal-header"><h5 class="modal-title">Void <span id="slVoidRef"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p class="small text-muted mb-2">The supplier balance and any cash or stock effects are reversed. The row stays in the ledger, struck through.</p>
    <label class="form-label">Reason</label><textarea name="void_reason" class="form-control" rows="2" required maxlength="255"></textarea></div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Void</button></div>
</form></div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var form=document.getElementById('slVoidForm'),modalEl=document.getElementById('slVoidModal');
  document.querySelectorAll('.sl-void-btn').forEach(function(b){b.onclick=function(){form.action=b.dataset.action;document.getElementById('slVoidRef').textContent=b.dataset.ref;form.void_reason.value='';bootstrap.Modal.getOrCreateInstance(modalEl).show();};});
  var supName=<?=json_encode($supplier['name'])?>,heads=['Date','Reference','Payment Type','Amount','Paid','Added to Payable','Payment Made','Balance Due','Status'];
  function slData(){
    var rows=[['','Opening Balance','','','','','',<?=json_encode('Rs. '.number_format((float)$supplier['opening_balance'],2))?>,'']];
    document.querySelectorAll('#supplierDetails tr.sl-row').forEach(function(tr){
      if(tr.dataset.f==='1')return;
      var c=[].slice.call(tr.querySelectorAll('td')).slice(0,8).map(function(td){return td.innerText.replace(/\s+/g,' ').replace('(Voided)','').trim();});
      c.push(tr.dataset.void==='1'?'Voided':'Posted');rows.push(c);
    });
    return rows;
  }
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];});}
  document.getElementById('slExportCsv').onclick=function(){
    var csv=[heads].concat(slData()).map(function(r){return r.map(function(v){return '"'+String(v).replace(/"/g,'""')+'"';}).join(',');}).join('\r\n');
    var a=document.createElement('a');a.href=URL.createObjectURL(new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8;'}));
    a.download='supplier-ledger-'+supName.replace(/[^A-Za-z0-9]+/g,'-')+'.csv';document.body.appendChild(a);a.click();a.remove();
  };
  document.getElementById('slExportPdf').onclick=function(){
    var data=slData(),num={3:1,4:1,5:1,6:1,7:1},title='Supplier Ledger - '+supName;
    var w=window.open('','_blank');if(!w){alert('Allow pop-ups for this site to export the PDF.');return;}
    var body=data.map(function(r,ri){var voided=r[8]==='Voided';return '<tr'+(voided?' class="v"':'')+'>'+r.map(function(v,i){return '<td'+(num[i]?' class="n"':'')+'>'+esc(v)+'</td>';}).join('')+'</tr>';}).join('');
    w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>'+esc(title)+'</title><style>@page{size:A4 landscape;margin:10mm}body{font-family:Arial,sans-serif;font-size:10px;color:#111}h2{margin:0 0 2px;font-size:15px}.sub{margin:0 0 8px;color:#555}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cfd6dd;padding:4px 5px;text-align:left}th{background:#eef2f6}td.n,th.n{text-align:right;white-space:nowrap}tr.v td{text-decoration:line-through;color:#888}</style></head><body><h2>'+esc(title)+'</h2><p class="sub">Current Balance Due: '+esc(<?=json_encode('Rs. '.number_format((float)$supplier['credit_due'],2))?>)+'</p><table><thead><tr>'+heads.map(function(h,i){return '<th'+(num[i]?' class="n"':'')+'>'+h+'</th>';}).join('')+'</tr></thead><tbody>'+body+'</tbody></table></body></html>');
    w.document.close();w.focus();setTimeout(function(){w.print();},250);
  };
  // Draws the ledger table onto a canvas (no external library, works offline) and returns it.
  function slCanvas(){
    var data=slData(),S=2,pad=16,rowH=30,titleH=62,foot=40;
    var cols=[{h:'Date',w:150},{h:'Reference',w:215},{h:'Payment Type',w:120},{h:'Amount',w:115,n:1},{h:'Paid',w:105,n:1},{h:'Added to Payable',w:135,n:1},{h:'Payment Made',w:125,n:1},{h:'Balance Due',w:125,n:1}];
    var W=cols.reduce(function(a,c){return a+c.w;},0)+pad*2,H=titleH+rowH*(data.length+1)+foot+pad;
    var cv=document.createElement('canvas');cv.width=W*S;cv.height=H*S;var g=cv.getContext('2d');g.scale(S,S);
    g.fillStyle='#fff';g.fillRect(0,0,W,H);
    g.fillStyle='#111';g.font='bold 18px Arial';g.textBaseline='middle';g.textAlign='left';g.fillText('Supplier Ledger - '+supName,pad,26);
    g.font='12px Arial';g.fillStyle='#666';g.fillText(new Date().toLocaleDateString(),pad,48);
    var y=titleH;
    g.fillStyle='#eef2f6';g.fillRect(pad,y,W-pad*2,rowH);
    function cells(vals,yy,opt){
      var x=pad;
      cols.forEach(function(c,i){
        var v=vals[i]||'';g.fillStyle=opt.color;g.font=(opt.bold?'bold ':'')+'12px Arial';
        g.textAlign=c.n?'right':'left';var tx=c.n?x+c.w-8:x+8;
        g.fillText(v,tx,yy+rowH/2);
        if(opt.strike&&v){var tw=g.measureText(v).width;g.fillRect(c.n?tx-tw:tx,yy+rowH/2,tw,1);}
        x+=c.w;
      });
    }
    cells(cols.map(function(c){return c.h;}),y,{bold:1,color:'#333'});
    data.forEach(function(r,ri){
      y+=rowH;var voided=r[8]==='Voided';
      g.fillStyle='#e3e8ee';g.fillRect(pad,y+rowH-1,W-pad*2,1);
      if(ri===0){g.fillStyle='#f7f9fb';g.fillRect(pad,y,W-pad*2,rowH-1);}
      cells(r.slice(0,8),y,{color:voided?'#999':'#111',strike:voided,bold:ri===0});
    });
    y+=rowH+10;g.textAlign='right';g.font='bold 14px Arial';g.fillStyle='#111';
    g.fillText('Current Balance Due: '+<?=json_encode('Rs. '.number_format((float)$supplier['credit_due'],2))?>,W-pad-8,y+10);
    return cv;
  }
  document.getElementById('slWhatsapp').onclick=function(){
    var btn=this,cv=slCanvas(),fname='supplier-ledger-'+supName.replace(/[^A-Za-z0-9]+/g,'-')+'.png';
    var p=(btn.dataset.phone||'').replace(/\D+/g,'');
    if(p.indexOf('00')===0)p=p.slice(2);else if(p.charAt(0)==='0')p='92'+p.slice(1);
    var wa='https://wa.me/'+p+'?text='+encodeURIComponent('Supplier Ledger - '+supName);
    function download(blob){var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=fname;document.body.appendChild(a);a.click();a.remove();}
    cv.toBlob(function(blob){
      if(!blob){alert('Could not create the image.');return;}
      var file=new File([blob],fname,{type:'image/png'});
      if(navigator.canShare&&navigator.canShare({files:[file]})){
        navigator.share({files:[file],title:'Supplier Ledger - '+supName}).catch(function(){});return;
      }
      var opened=window.open(wa,'_blank');
      if(navigator.clipboard&&window.ClipboardItem){
        navigator.clipboard.write([new ClipboardItem({'image/png':blob})]).then(function(){
          alert('The ledger image is copied. In the WhatsApp chat, press Ctrl+V to paste it, then Send.');
        }).catch(function(){download(blob);alert('The image was saved as '+fname+'. Attach it in the WhatsApp chat.');});
      }else{download(blob);alert('The image was saved as '+fname+'. Attach it in the WhatsApp chat.');}
    },'image/png');
  };
  if(location.hash==='#supplierDetails'){var t=document.getElementById('supplierDetailsTab');if(t)bootstrap.Tab.getOrCreateInstance(t).show();}
});
</script>
<?= $this->endSection() ?>