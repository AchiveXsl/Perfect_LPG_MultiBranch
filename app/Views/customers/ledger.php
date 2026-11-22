<?= $this->extend('layouts/app') ?><?= $this->section('styles') ?>
<style>
.sl-details th{white-space:nowrap;font-size:.74rem;letter-spacing:.03em;text-transform:uppercase;color:#475569;background:#f1f5f9}
.sl-details td{vertical-align:middle}
.sl-details .num{text-align:right;white-space:nowrap}
.sl-details tr.sl-void td{text-decoration:line-through;color:#8a949e;background:#fafafa}
.sl-details tr.sl-void td.sl-act{text-decoration:none}
.sl-details tr.sl-open td{background:#f8fafc;font-weight:700}
.tl-card{border:1px solid #e3e8ee;border-radius:14px;background:#fff;padding:14px 16px;margin-bottom:14px;scroll-margin-top:90px}
.tl-card.tl-flash{box-shadow:0 0 0 3px #f5b942}
.tl-card.tl-void{opacity:.6}
.tl-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.tl-no{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;border-radius:14px;background:#0f3d33;color:#fff;font-weight:700;font-size:.8rem;margin-right:8px;padding:0 6px}
.tl-title{font-weight:700}.tl-sub{font-size:.78rem;color:#667085}
.tl-pill{font-size:.72rem;font-weight:700;border-radius:999px;padding:3px 10px}
.tl-pill.paid{background:#dcfce7;color:#166534}.tl-pill.part{background:#fef3c7;color:#92400e}.tl-pill.due{background:#fee2e2;color:#991b1b}.tl-pill.void{background:#e5e7eb;color:#4b5563}.tl-pill.recv{background:#dbeafe;color:#1e40af}
.tl-steps{position:relative;margin:0;padding:0;list-style:none}
.tl-step{position:relative;display:flex;gap:12px;padding-bottom:12px}
.tl-step:last-child{padding-bottom:0}
.tl-step:not(:last-child)::before{content:"";position:absolute;left:13px;top:28px;bottom:0;width:2px;background:#cfe3da}
.tl-dot{flex:0 0 28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;background:#e5e7eb;color:#6b7280}
.tl-step.done .tl-dot{background:#0f7a5c;color:#fff}
.tl-step.warn .tl-dot{background:#f59e0b;color:#fff}
.tl-lbl{font-weight:600;font-size:.9rem}.tl-det{font-size:.8rem;color:#667085}
.tl-det b{color:#111827}.tl-num-bad{color:#b42318!important;font-weight:700}.tl-num-ok{color:#166534!important;font-weight:700}
.sl-badge-void{display:inline-block;margin-left:.35rem;font-size:.68rem;font-weight:700;color:#b42318;text-decoration:none!important}
</style>
<?= $this->endSection() ?><?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h4 class="mb-1"><?=esc($customer['name'])?></h4><div class="text-muted small"><?=esc($customer['phone']??'')?><?=!empty($customer['city'])?' · '.esc($customer['city']):''?></div></div>
  <div class="d-flex gap-2"><button type="button" class="btn btn-outline-danger" id="ledgerPdfTop" onclick="document.getElementById('slExportPdf').click()"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</button><a class="btn btn-outline-secondary" href="<?=site_url('customers')?>"><i class="bi bi-arrow-left me-1"></i>Back to Customers</a></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Opening Balance</div><h5 class="mb-0">Rs. <?=number_format((float)$customer['opening_balance'],2)?></h5></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Total Purchased</div><h5 class="mb-0">Rs. <?=number_format((float)$customer['total_purchased'],2)?></h5></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Current Balance</div><h5 class="mb-0 <?=((float)$customer['credit_due'])<0?'text-danger':'text-success'?>">Rs. <?=number_format((float)$customer['credit_due'],2)?></h5><div class="small text-muted"><?=((float)$customer['credit_due'])<0?'Customer credit / advance':'Amount due'?></div></div></div></div>
  <div class="col-md-3"><div class="card border-warning h-100"><div class="card-body"><div class="small text-muted">Security Deposits Held</div><h5 class="mb-0">Rs. <?=number_format((float)($customer['security_deposit_held']??0),2)?></h5><div class="small text-muted">Refundable liability</div></div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white">
    <ul class="nav nav-tabs card-header-tabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#customerOverview" type="button"><i class="bi bi-person-vcard me-1"></i>Overview</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerSales" type="button"><i class="bi bi-receipt me-1"></i>Sales / Transactions <span class="badge text-bg-secondary"><?=count($sales)?></span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerReceipts" type="button"><i class="bi bi-cash-coin me-1"></i>Receipts <span class="badge text-bg-secondary"><?=count($receipts)?></span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerDeposits" type="button"><i class="bi bi-shield-check me-1"></i>Security Deposits <span class="badge text-bg-secondary"><?=count($deposits)?></span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerCustody" type="button"><i class="bi bi-box-seam me-1"></i>Cylinder Custody <span class="badge text-bg-secondary"><?=count($custody)?></span></button></li>
      <li class="nav-item"><button class="nav-link" id="customerTimelineTab" data-bs-toggle="tab" data-bs-target="#customerTimeline" type="button"><i class="bi bi-diagram-3 me-1"></i>Timeline <span class="badge text-bg-secondary"><?=count($details)?></span></button></li>
      <li class="nav-item"><button class="nav-link" id="customerDetailsTab" data-bs-toggle="tab" data-bs-target="#customerDetails" type="button"><i class="bi bi-journal-text me-1"></i>Details</button></li>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">
      <div class="tab-pane fade show active" id="customerOverview">
        <div class="row g-4">
          <div class="col-lg-6"><div class="border rounded p-4 h-100"><h6 class="fw-semibold">Account Summary</h6><div class="d-flex justify-content-between py-2 border-bottom"><span>Opening Balance</span><strong>Rs. <?=number_format((float)$customer['opening_balance'],2)?></strong></div><div class="d-flex justify-content-between py-2 border-bottom"><span>Current Balance</span><strong>Rs. <?=number_format((float)$customer['credit_due'],2)?></strong></div><div class="d-flex justify-content-between py-2"><span>Security Deposit Held</span><strong>Rs. <?=number_format((float)($customer['security_deposit_held']??0),2)?></strong></div></div></div>
          <div class="col-lg-6"><div class="border rounded p-4 h-100"><h6 class="fw-semibold">Customer Details</h6><div class="small text-muted mb-1">Code</div><div class="mb-2"><?=esc($customer['code']??'—')?></div><div class="small text-muted mb-1">Phone</div><div class="mb-2"><?=esc($customer['phone']??'—')?></div><div class="small text-muted mb-1">Vehicle</div><div class="mb-2"><?=esc($customer['vehicle_no']??'—')?></div><div class="small text-muted mb-1">Address</div><div><?=esc($customer['address']??'—')?></div></div></div>
        </div>
      </div>

      <div class="tab-pane fade" id="customerSales">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>No.</th><th>Type</th><th>Total</th><th>Credit</th><th>Gas Return Credit</th><th>Deposit</th><th>Refund</th><th>Status</th></tr></thead><tbody>
        <?php foreach($sales as $r): ?><tr><td><?=esc($r['transaction_at'])?></td><td><?=esc($r['sale_no'])?></td><td><?=esc(ucwords(str_replace('_',' ',$r['transaction_type'])))?></td><td>Rs. <?=number_format((float)$r['total_amount'],2)?></td><td>Rs. <?=number_format((float)$r['credit_amount'],2)?></td><td>Rs. <?=number_format((float)($r['return_gas_ledger_amount']??0),2)?></td><td>Rs. <?=number_format((float)$r['security_deposit_amount'],2)?></td><td>Rs. <?=number_format((float)$r['security_deposit_refund_amount'],2)?></td><td><?=esc(ucfirst($r['status']))?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="tab-pane fade" id="customerReceipts">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>Receipt / Transaction</th><th>Type</th><th>Amount</th><th>Mode</th><th>Source</th><th>Status</th><th>Reference</th><th>Details</th></tr></thead><tbody>
        <?php foreach($receipts as $r): ?><tr><td><?=esc($r['receipt_at'])?></td><td><?=esc($r['receipt_no'])?></td><td><?=esc($r['receipt_type']??'Customer Receipt')?></td><td>Rs. <?=number_format((float)$r['amount'],2)?></td><td><?=esc(strtoupper($r['payment_mode']))?></td><td><?=esc($r['source']??'Unknown')?></td><td><span class="badge text-bg-<?=($r['status']??'')==='posted'?'success':'secondary'?>"><?=esc(strtoupper($r['status']??'UNKNOWN'))?></span></td><td><?=esc($r['reference_no']??'')?></td><td><?=esc($r['details']??'')?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="tab-pane fade" id="customerDeposits">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>Entry</th><th>Amount</th><th>Sale</th><th>Custody</th><th>Note</th></tr></thead><tbody>
        <?php foreach($deposits as $r): ?><tr><td><?=esc($r['transaction_at'])?></td><td><?= $r['entry_type']==='hold'?'<span class="badge text-bg-success">Deposit Held</span>':'<span class="badge text-bg-danger">Deposit Refunded</span>' ?></td><td>Rs. <?=number_format((float)$r['amount'],2)?></td><td><?=esc((string)($r['sale_id']??''))?></td><td><?=esc((string)($r['custody_id']??''))?></td><td><?=esc($r['notes']??'')?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="tab-pane fade" id="customerCustody">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Cylinder</th><th>Type</th><th>Gas KG</th><th>Deposit Held</th><th>Issued</th><th>Status</th></tr></thead><tbody>
        <?php foreach($custody as $r): ?><tr><td><?=esc($r['unit_code'])?></td><td><?=esc($r['cylinder_code'].' — '.$r['cylinder_name'])?></td><td><?=number_format((float)$r['gas_weight_kg'],2)?></td><td>Rs. <?=number_format((float)$r['deposit_amount'],2)?></td><td><?=esc($r['issued_at'])?></td><td><span class="badge text-bg-warning">Issued / Held</span></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="tab-pane fade" id="customerTimeline">
        <?php
          $mny=static fn($v)=>'Rs. '.number_format((float)$v,2);
          $kg=static fn($v)=>rtrim(rtrim(number_format((float)$v,3),'0'),'.');
          $kinds=['gas_sale'=>'Gas Sale','cylinder_sale'=>'Cylinder Sale','security_deposit'=>'Security Deposit / Issue','cylinder_return'=>'Cylinder Return'];
          $tl=array_reverse($details);
          $last=$details?end($details):null;
        ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <div class="small text-muted">Every transaction step by step, newest first. Number 1 is the oldest.</div>
          <div class="small"><span class="me-3">With customer now: <b><?=(int)($last['cyl_after']??0)?></b> cylinder(s)</span><span>Balance: <b class="<?=((float)$customer['credit_due'])>0.005?'tl-num-bad':'tl-num-ok'?>"><?=$mny($customer['credit_due'])?></b></span></div>
        </div>
        <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
          <div><label class="form-label small mb-1">Date range</label><select class="form-select form-select-sm" id="tlPreset"><option value="all">All time</option><option value="today">Today</option><option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="month">This month</option><option value="year">This year</option><option value="custom">Custom</option></select></div>
          <div><label class="form-label small mb-1">From</label><input type="date" class="form-control form-control-sm" id="tlFrom"></div>
          <div><label class="form-label small mb-1">To</label><input type="date" class="form-control form-control-sm" id="tlTo"></div>
          <div class="small text-muted ms-auto" id="tlInfo"></div>
        </div>
        <div class="text-center text-muted py-4" id="tlEmpty" style="display:none">No transactions in this date range.</div>
        <?php if(!$tl): ?><div class="text-center text-muted py-4">No transactions yet.</div><?php endif; ?>
        <?php foreach($tl as $k=>$d): $void=$d['status']==='voided'; $isSale=$d['kind']==='sale'; $remain=$isSale?max(0,(float)$d['amount']-(float)$d['paid']):0; ?>
        <div class="tl-card <?=$void?'tl-void':''?>" id="tl-<?=$d['kind']?>-<?=(int)$d['id']?>" data-d="<?=esc(substr((string)$d['at'],0,10),'attr')?>">
          <div class="tl-head">
            <div><span class="tl-no"><?=(int)$d['seq']?></span><span class="tl-title"><?=$isSale?esc($kinds[$d['txn']]??ucwords(str_replace('_',' ',(string)$d['txn']))):'Payment Received'?></span> <span class="tl-sub">· <?=esc($d['ref'])?> · <?=esc($d['at'])?></span></div>
            <div class="d-flex align-items-center gap-2">
            <?php if($void): ?><span class="tl-pill void">Voided</span>
            <?php elseif(!$isSale): ?><span class="tl-pill recv">Received <?=$mny($d['made'])?></span>
            <?php elseif($remain<=0.005): ?><span class="tl-pill paid">Paid in full</span>
            <?php elseif((float)$d['paid']>0.005): ?><span class="tl-pill part">Part paid · <?=$mny($remain)?> due</span>
            <?php else: ?><span class="tl-pill due">Unpaid · <?=$mny($remain)?> due</span><?php endif; ?>
            <button type="button" class="btn btn-sm btn-success tl-wa" title="Share this block on WhatsApp"><i class="bi bi-whatsapp"></i> Share</button>
            </div>
          </div>
          <ol class="tl-steps">
          <?php if($isSale): ?>
            <li class="tl-step <?=($d['issued_n']>0||$d['sold_n']>0||$d['kg']>0)?'done':''?>"><span class="tl-dot">1</span><div>
              <div class="tl-lbl"><?php if($d['issued_n']>0): ?>Issued <?=(int)$d['issued_n']?> cylinder(s)<?php elseif($d['sold_n']>0): ?>Sold <?=$kg($d['sold_n'])?> cylinder(s)<?php elseif($d['txn']==='cylinder_return'): ?>Cylinder return<?php elseif($d['kg']>0): ?>Gas sold <?=$kg($d['kg'])?> kg<?php else: ?>No cylinders in this transaction<?php endif; ?></div>
              <div class="tl-det"><?=esc($d['at'])?><?=$d['issued_txt']!==''?' · '.esc($d['issued_txt']):''?><?=($d['issued_n']>0&&$d['kg']>0)?' · gas '.$kg($d['kg']).' kg':''?></div></div></li>
            <li class="tl-step <?=$remain>0.005?'warn':'done'?>"><span class="tl-dot">2</span><div>
              <div class="tl-lbl">Billing</div>
              <div class="tl-det">Total <b><?=$mny($d['amount'])?></b> · Paid <b><?=$mny($d['paid'])?></b> · Remaining <b class="<?=$remain>0.005?'tl-num-bad':'tl-num-ok'?>"><?=$mny($remain)?></b><?php if(($d['dep']??0)>0.005): ?> · Deposit held <b><?=$mny($d['dep'])?></b><?php endif; ?><?php if(($d['refund']??0)>0.005): ?> · Deposit refunded <b><?=$mny($d['refund'])?></b><?php endif; ?></div></div></li>
          <?php else: ?>
            <li class="tl-step done"><span class="tl-dot">1</span><div>
              <div class="tl-lbl">Payment received <?=$mny($d['made'])?></div>
              <div class="tl-det"><?=esc($d['at'])?> · <?=esc($d['type'])?> · Receipt <?=esc($d['ref'])?></div></div></li>
          <?php endif; ?>
            <li class="tl-step <?=$d['cyl_before']>0?'warn':''?>"><span class="tl-dot"><?=$isSale?3:2?></span><div>
              <div class="tl-lbl">Already with customer</div>
              <div class="tl-det"><b><?=(int)$d['cyl_before']?></b> cylinder(s) before this transaction</div></div></li>
            <?php if($isSale): ?>
            <li class="tl-step <?=$d['returned_n']>0?'done':''?>"><span class="tl-dot">4</span><div>
              <div class="tl-lbl">Returned</div>
              <div class="tl-det"><?=$d['returned_n']>0?'<b>'.(int)$d['returned_n'].'</b> cylinder(s)'.($d['returned_txt']!==''?' · '.esc($d['returned_txt']):''):'None returned'?></div></div></li>
            <?php endif; ?>
            <li class="tl-step <?=$d['cyl_after']>0?'warn':'done'?>"><span class="tl-dot"><?=$isSale?5:3?></span><div>
              <div class="tl-lbl">Total with customer &amp; balance</div>
              <div class="tl-det"><b><?=(int)$d['cyl_after']?></b> cylinder(s) with customer · Balance <b class="<?=((float)$d['balance'])>0.005?'tl-num-bad':'tl-num-ok'?>"><?=$mny($d['balance'])?></b></div></div></li>
          </ol>
        </div>
        <?php endforeach; ?>
        <div class="text-center"><button type="button" class="btn btn-outline-secondary btn-sm" id="tlMore" style="display:none">Show 5 more</button></div>
      </div>

      <div class="tab-pane fade" id="customerDetails">
        <div class="d-flex flex-wrap gap-2 justify-content-end mb-2">
          <button type="button" class="btn btn-sm btn-outline-success" id="slExportCsv"><i class="bi bi-file-earmark-spreadsheet"></i> Export Excel / CSV</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="slExportPdf"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
          <button type="button" class="btn btn-sm btn-success" id="slWhatsapp" data-phone="<?=esc((string)($customer['phone']??''),'attr')?>"><i class="bi bi-whatsapp"></i> Share on WhatsApp</button>
        </div>
        <?= view('partials/ledger_details_tools', ['paneId' => 'customerDetails']) ?>
        <div class="table-responsive"><table class="table table-sm align-middle sl-details mb-0"><thead><tr>
          <th>Date</th><th>Reference</th><th>Type</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Added to Receivable</th><th class="num">Payment Received</th><th class="num">Balance</th><th></th>
        </tr></thead><tbody>
          <tr class="sl-open"><td colspan="7">Opening Balance</td><td class="num">Rs. <?=number_format((float)$customer['opening_balance'],2)?></td><td></td></tr>
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
            <td class="sl-act text-end"><a href="#" class="small me-2 tl-link" data-tl="tl-<?=$d['kind']?>-<?=(int)$d['id']?>">Timeline</a><?php if(!$void && !empty($canVoid) && $d['voidable']): ?><button type="button" class="btn btn-sm btn-outline-danger sl-void-btn" data-action="<?=site_url('customers/receipts/void/'.$d['id'])?>" data-ref="<?=esc($d['ref'])?>">Void</button><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$details): ?><tr><td colspan="9" class="text-center text-muted py-4">No sales or receipts yet.</td></tr><?php endif; ?>
        </tbody></table></div>
        <div class="small text-muted mt-2">Void is available on payments received only. A sale is voided from Sale History.</div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="slVoidModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" id="slVoidForm">
  <?= csrf_field() ?>
  <input type="hidden" name="return_to" value="/customers/ledger/<?=(int)$customer['id']?>">
  <div class="modal-header"><h5 class="modal-title">Void <span id="slVoidRef"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p class="small text-muted mb-2">The customer balance and any cash effect are reversed. The row stays in the ledger, struck through.</p>
    <label class="form-label">Reason</label><textarea name="void_reason" class="form-control" rows="2" required maxlength="255"></textarea></div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Void</button></div>
</form></div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var form=document.getElementById('slVoidForm'),modalEl=document.getElementById('slVoidModal');
  document.querySelectorAll('.sl-void-btn').forEach(function(b){b.onclick=function(){form.action=b.dataset.action;document.getElementById('slVoidRef').textContent=b.dataset.ref;form.void_reason.value='';bootstrap.Modal.getOrCreateInstance(modalEl).show();};});
  var name=<?=json_encode($customer['name'])?>,openBal=<?=json_encode('Rs. '.number_format((float)$customer['opening_balance'],2))?>,curBal=<?=json_encode('Rs. '.number_format((float)$customer['credit_due'],2))?>;
  var heads=['Date','Reference','Type','Amount','Paid','Added to Receivable','Payment Received','Balance','Status'];
  function slData(){
    var rows=[['','Opening Balance','','','','','',openBal,'']];
    document.querySelectorAll('#customerDetails tr.sl-row').forEach(function(tr){
      if(tr.dataset.f==='1')return;
      var c=[].slice.call(tr.querySelectorAll('td')).slice(0,8).map(function(td){return td.innerText.replace(/\s+/g,' ').replace('(Voided)','').trim();});
      c.push(tr.dataset.void==='1'?'Voided':'Posted');rows.push(c);
    });
    return rows;
  }
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];});}
  var slug=name.replace(/[^A-Za-z0-9]+/g,'-');
  document.getElementById('slExportCsv').onclick=function(){
    var csv=[heads].concat(slData()).map(function(r){return r.map(function(v){return '"'+String(v).replace(/"/g,'""')+'"';}).join(',');}).join('\r\n');
    var a=document.createElement('a');a.href=URL.createObjectURL(new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8;'}));
    a.download='customer-ledger-'+slug+'.csv';document.body.appendChild(a);a.click();a.remove();
  };
  document.getElementById('slExportPdf').onclick=function(){
    var data=slData(),num={3:1,4:1,5:1,6:1,7:1},title='Customer Ledger - '+name;
    var w=window.open('','_blank');if(!w){alert('Allow pop-ups for this site to export the PDF.');return;}
    var body=data.map(function(r){var v=r[8]==='Voided';return '<tr'+(v?' class="v"':'')+'>'+r.map(function(x,i){return '<td'+(num[i]?' class="n"':'')+'>'+esc(x)+'</td>';}).join('')+'</tr>';}).join('');
    w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>'+esc(title)+'</title><style>@page{size:A4 landscape;margin:10mm}body{font-family:Arial,sans-serif;font-size:10px;color:#111}h2{margin:0 0 2px;font-size:15px}.sub{margin:0 0 8px;color:#555}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cfd6dd;padding:4px 5px;text-align:left}th{background:#eef2f6}td.n,th.n{text-align:right;white-space:nowrap}tr.v td{text-decoration:line-through;color:#888}</style></head><body><h2>'+esc(title)+'</h2><p class="sub">Current Balance: '+esc(curBal)+'</p><table><thead><tr>'+heads.map(function(h,i){return '<th'+(num[i]?' class="n"':'')+'>'+h+'</th>';}).join('')+'</tr></thead><tbody>'+body+'</tbody></table></body></html>');
    w.document.close();w.focus();setTimeout(function(){w.print();},250);
  };
  function slCanvas(){
    var data=slData(),S=2,pad=16,rowH=30,titleH=62,foot=40;
    var cols=[{h:'Date',w:150},{h:'Reference',w:215},{h:'Type',w:170},{h:'Amount',w:115,n:1},{h:'Paid',w:105,n:1},{h:'Added to Receivable',w:150,n:1},{h:'Payment Received',w:135,n:1},{h:'Balance',w:125,n:1}];
    var W=cols.reduce(function(a,c){return a+c.w;},0)+pad*2,H=titleH+rowH*(data.length+1)+foot+pad;
    var cv=document.createElement('canvas');cv.width=W*S;cv.height=H*S;var g=cv.getContext('2d');g.scale(S,S);
    g.fillStyle='#fff';g.fillRect(0,0,W,H);
    g.fillStyle='#111';g.font='bold 18px Arial';g.textBaseline='middle';g.textAlign='left';g.fillText('Customer Ledger - '+name,pad,26);
    g.font='12px Arial';g.fillStyle='#666';g.fillText(new Date().toLocaleDateString(),pad,48);
    var y=titleH;g.fillStyle='#eef2f6';g.fillRect(pad,y,W-pad*2,rowH);
    function cells(vals,yy,o){var x=pad;cols.forEach(function(c,i){var v=vals[i]||'';g.fillStyle=o.color;g.font=(o.bold?'bold ':'')+'12px Arial';g.textAlign=c.n?'right':'left';var tx=c.n?x+c.w-8:x+8;g.fillText(v,tx,yy+rowH/2);if(o.strike&&v){var tw=g.measureText(v).width;g.fillRect(c.n?tx-tw:tx,yy+rowH/2,tw,1);}x+=c.w;});}
    cells(cols.map(function(c){return c.h;}),y,{bold:1,color:'#333'});
    data.forEach(function(r,ri){y+=rowH;var v=r[8]==='Voided';g.fillStyle='#e3e8ee';g.fillRect(pad,y+rowH-1,W-pad*2,1);if(ri===0){g.fillStyle='#f7f9fb';g.fillRect(pad,y,W-pad*2,rowH-1);}cells(r.slice(0,8),y,{color:v?'#999':'#111',strike:v,bold:ri===0});});
    y+=rowH+10;g.textAlign='right';g.font='bold 14px Arial';g.fillStyle='#111';g.fillText('Current Balance: '+curBal,W-pad-8,y+10);
    return cv;
  }
  document.getElementById('slWhatsapp').onclick=function(){
    var btn=this,cv=slCanvas(),fname='customer-ledger-'+slug+'.png';
    var p=(btn.dataset.phone||'').replace(/\D+/g,'');
    if(p.indexOf('00')===0)p=p.slice(2);else if(p.charAt(0)==='0')p='92'+p.slice(1);
    var wa='https://wa.me/'+p+'?text='+encodeURIComponent('Customer Ledger - '+name);
    function download(blob){var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=fname;document.body.appendChild(a);a.click();a.remove();}
    cv.toBlob(function(blob){
      if(!blob){alert('Could not create the image.');return;}
      var file=new File([blob],fname,{type:'image/png'});
      if(navigator.canShare&&navigator.canShare({files:[file]})){navigator.share({files:[file],title:'Customer Ledger - '+name}).catch(function(){});return;}
      window.open(wa,'_blank');
      if(navigator.clipboard&&window.ClipboardItem){
        navigator.clipboard.write([new ClipboardItem({'image/png':blob})]).then(function(){alert('The ledger image is copied. In the WhatsApp chat, press Ctrl+V to paste it, then Send.');}).catch(function(){download(blob);alert('The image was saved as '+fname+'. Attach it in the WhatsApp chat.');});
      }else{download(blob);alert('The image was saved as '+fname+'. Attach it in the WhatsApp chat.');}
    },'image/png');
  };
  // Timeline tab: date range, first 5 blocks, "Show 5 more", WhatsApp image per block.
  var tlCards=[].slice.call(document.querySelectorAll('#customerTimeline .tl-card')),tlLimit=5,tlStep=5;
  var tlFromEl=document.getElementById('tlFrom'),tlToEl=document.getElementById('tlTo'),tlPresetEl=document.getElementById('tlPreset'),tlMore=document.getElementById('tlMore');
  function tlFmt(d){return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2);}
  function tlInRange(c){var d=c.dataset.d;return !(tlFromEl.value&&d<tlFromEl.value)&&!(tlToEl.value&&d>tlToEl.value);}
  function tlRender(){
    var n=0,shown=0;
    tlCards.forEach(function(c){var ok=tlInRange(c);if(ok)n++;var show=ok&&(n<=tlLimit||c.dataset.force==='1');c.style.display=show?'':'none';if(show)shown++;});
    var total=n;tlMore.style.display=total>shown?'':'none';
    tlMore.textContent='Show '+Math.min(tlStep,total-shown)+' more';
    document.getElementById('tlInfo').textContent=total?('Showing '+shown+' of '+total+' transactions'):'';
    document.getElementById('tlEmpty').style.display=(total===0&&tlCards.length>0)?'':'none';
  }
  function tlPreset(){
    var p=tlPresetEl.value,t=new Date(),f='',e='';
    if(p==='today'){f=e=tlFmt(t);}
    else if(p==='7'||p==='30'){var s=new Date();s.setDate(s.getDate()-(+p-1));f=tlFmt(s);e=tlFmt(t);}
    else if(p==='month'){f=tlFmt(new Date(t.getFullYear(),t.getMonth(),1));e=tlFmt(t);}
    else if(p==='year'){f=tlFmt(new Date(t.getFullYear(),0,1));e=tlFmt(t);}
    if(p!=='custom'){tlFromEl.value=f;tlToEl.value=e;}
    tlCards.forEach(function(c){delete c.dataset.force;});tlLimit=tlStep;tlRender();
  }
  tlPresetEl.onchange=tlPreset;
  [tlFromEl,tlToEl].forEach(function(el){el.onchange=function(){tlPresetEl.value='custom';tlCards.forEach(function(c){delete c.dataset.force;});tlLimit=tlStep;tlRender();};});
  tlMore.onclick=function(){tlLimit+=tlStep;tlRender();};
  tlRender();
  function tlShare(card,btn){
    var S=2,W=660,pad=18,lines=[],g0=document.createElement('canvas').getContext('2d');
    var title=card.querySelector('.tl-no').textContent+'  '+card.querySelector('.tl-title').textContent,sub=card.querySelector('.tl-sub').textContent.replace(/^\s*·\s*/,''),pill=card.querySelector('.tl-pill').textContent.trim();
    g0.font='12px Arial';
    function wrap(txt,w){var words=txt.split(' '),cur='',out=[];words.forEach(function(x){var t=cur?cur+' '+x:x;if(g0.measureText(t).width>w&&cur){out.push(cur);cur=x;}else cur=t;});if(cur)out.push(cur);return out;}
    var steps=[].slice.call(card.querySelectorAll('.tl-step')).map(function(li){return{n:li.querySelector('.tl-dot').textContent,cls:li.className,l:li.querySelector('.tl-lbl').textContent.trim(),d:wrap(li.querySelector('.tl-det').textContent.replace(/\s+/g,' ').trim(),W-pad*2-50)};});
    var H=pad+70;steps.forEach(function(s){H+=24+s.d.length*17+14;});H+=pad;
    var cv=document.createElement('canvas');cv.width=W*S;cv.height=H*S;var g=cv.getContext('2d');g.scale(S,S);
    g.fillStyle='#fff';g.fillRect(0,0,W,H);g.strokeStyle='#e3e8ee';g.strokeRect(.5,.5,W-1,H-1);
    g.textBaseline='middle';g.textAlign='left';g.fillStyle='#111';g.font='bold 16px Arial';g.fillText(name+' - '+title,pad,pad+10);
    g.font='12px Arial';g.fillStyle='#667085';g.fillText(sub,pad,pad+34);
    g.font='bold 12px Arial';g.fillStyle=/Paid in full|Received/.test(pill)?'#166534':(/Voided/.test(pill)?'#4b5563':'#b42318');g.textAlign='right';g.fillText(pill,W-pad,pad+10);g.textAlign='left';
    var y=pad+66;
    steps.forEach(function(s,i){
      var col=/done/.test(s.cls)?'#0f7a5c':(/warn/.test(s.cls)?'#f59e0b':'#9ca3af');
      if(i<steps.length-1){g.fillStyle='#cfe3da';g.fillRect(pad+13,y+14,2,24+s.d.length*17+4);}
      g.fillStyle=col;g.beginPath();g.arc(pad+14,y+4,13,0,7);g.fill();g.fillStyle='#fff';g.font='bold 12px Arial';g.textAlign='center';g.fillText(s.n,pad+14,y+4);g.textAlign='left';
      g.fillStyle='#111';g.font='bold 14px Arial';g.fillText(s.l,pad+40,y);
      g.fillStyle='#555';g.font='12px Arial';s.d.forEach(function(t,k){g.fillText(t,pad+40,y+20+k*17);});
      y+=24+s.d.length*17+14;
    });
    var fname='customer-'+name.replace(/[^A-Za-z0-9]+/g,'-')+'-'+card.querySelector('.tl-sub').textContent.split('·')[1].trim()+'.png';
    var p=(<?=json_encode((string)($customer['phone']??''))?>).replace(/\D+/g,'');
    if(p.indexOf('00')===0)p=p.slice(2);else if(p.charAt(0)==='0')p='92'+p.slice(1);
    var wa='https://wa.me/'+p+'?text='+encodeURIComponent(name+' - '+title.replace(/^\S+\s+/,'')+' - '+pill);
    function download(blob){var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=fname;document.body.appendChild(a);a.click();a.remove();}
    cv.toBlob(function(blob){
      if(!blob){alert('Could not create the image.');return;}
      var file=new File([blob],fname,{type:'image/png'});
      if(navigator.canShare&&navigator.canShare({files:[file]})){navigator.share({files:[file],title:name}).catch(function(){});return;}
      window.open(wa,'_blank');
      if(navigator.clipboard&&window.ClipboardItem){
        navigator.clipboard.write([new ClipboardItem({'image/png':blob})]).then(function(){alert('The image is copied. In the WhatsApp chat, press Ctrl+V to paste it, then Send.');}).catch(function(){download(blob);alert('The image was saved as '+fname+'. Attach it in the WhatsApp chat.');});
      }else{download(blob);alert('The image was saved as '+fname+'. Attach it in the WhatsApp chat.');}
    },'image/png');
  }
  document.querySelectorAll('.tl-link').forEach(function(a){a.onclick=function(e){e.preventDefault();
    var tab=document.getElementById('customerTimelineTab'),card=document.getElementById(a.dataset.tl);
    if(card){card.dataset.force='1';if(!tlInRange(card)){document.getElementById('tlPreset').value='all';tlPreset();}tlRender();}
    var go=function(){if(!card)return;card.scrollIntoView({behavior:'smooth',block:'center'});card.classList.add('tl-flash');setTimeout(function(){card.classList.remove('tl-flash');},2200);};
    tab.addEventListener('shown.bs.tab',function h(){tab.removeEventListener('shown.bs.tab',h);setTimeout(go,50);});
    bootstrap.Tab.getOrCreateInstance(tab).show();};});
  document.querySelectorAll('#customerTimeline .tl-wa').forEach(function(b){b.onclick=function(){tlShare(b.closest('.tl-card'),b);};});
  if(location.hash==='#customerDetails'){var t=document.getElementById('customerDetailsTab');if(t)bootstrap.Tab.getOrCreateInstance(t).show();}
});
</script>
<?= $this->endSection() ?>