<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php $receiptUrl = (string) (session()->getFlashdata('receipt_url') ?? ''); ?>
<style>
#customerReceiptOverlay{position:fixed;inset:0;z-index:2000;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.55);padding:1rem}
#customerReceiptOverlay.is-visible{display:flex}
#customerReceiptDialog{width:min(340px,96vw);height:min(700px,92vh);background:#fff;border-radius:.65rem;box-shadow:0 18px 50px rgba(0,0,0,.25);display:flex;flex-direction:column;overflow:hidden}
#customerReceiptDialog .hd{display:flex;align-items:center;justify-content:space-between;padding:.6rem .75rem;border-bottom:1px solid #e5e7eb;font-weight:700}
#customerReceiptFrame{flex:1;width:100%;border:0;background:#fff}
</style>
<div id="customerReceiptOverlay" aria-hidden="true">
  <div id="customerReceiptDialog" role="dialog" aria-modal="true" aria-label="Customer Receipt">
    <div class="hd"><span id="customerReceiptTitle">Customer Receipt</span><button type="button" class="btn btn-sm btn-outline-secondary" id="customerReceiptClose">Close</button></div>
    <iframe id="customerReceiptFrame" title="Customer receipt"></iframe>
  </div>
</div>
<script>
window.openCustomerReceipt = function (url, title) {
  document.getElementById('customerReceiptTitle').textContent = title || 'Customer Receipt';
  document.getElementById('customerReceiptFrame').src = url;
  document.getElementById('customerReceiptOverlay').classList.add('is-visible');
};
document.addEventListener('DOMContentLoaded', function () {
  var overlay = document.getElementById('customerReceiptOverlay');
  var close = function () { overlay.classList.remove('is-visible'); };
  document.getElementById('customerReceiptClose').onclick = close;
  overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  <?php if ($receiptUrl !== ''): ?>window.openCustomerReceipt(<?= json_encode($receiptUrl) ?>);<?php endif; ?>
});
</script>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h4 class="mb-1">Customer Receipts</h4><div class="text-muted small">Receive customer payments against outstanding balances. A receipt may exceed the current OS, which will result in a negative balance/credit.</div></div>
</div>
<?php if(session()->getFlashdata('success')):?><div class="alert alert-success"><?=esc(session()->getFlashdata('success'))?></div><?php endif;?>
<?php if(session()->getFlashdata('error')):?><div class="alert alert-danger"><?=esc(session()->getFlashdata('error'))?></div><?php endif;?>

<form method="post" action="<?=site_url('receipts/save')?>" class="card shadow-sm card-body">
  <?=csrf_field()?>
  <div class="row g-3">
    <div class="col-md-3">
      <label class="form-label fw-semibold">Customer</label>
      <select name="customer_id" id="receiptCustomer" class="form-select" required>
        <option value="">Select customer</option>
        <?php foreach($customers as $c):?>
          <option value="<?=$c['id']?>"><?=esc($c['name'])?></option>
        <?php endforeach;?>
      </select>
      <?= view('partials/searchable_select', ['selectId' => 'receiptCustomer', 'placeholder' => 'Type to search customer...']) ?>
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold">Receive Amount</label>
      <div class="input-group">
        <span class="input-group-text">Rs.</span>
        <input name="amount" id="receiptAmount" type="number" min=".01" step=".01" class="form-control" placeholder="0.00" required>
      </div>
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold">Receive Date</label>
      <input name="receive_date" id="receiveDate" type="date" class="form-control" value="<?= esc(old('receive_date') ?: \App\Services\BranchContext::today(), 'attr') ?>" max="<?= \App\Services\BranchContext::today() ?>" required>
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold">Payment Mode</label>
      <select name="payment_mode" id="receiptMode" class="form-select"><?= \App\Services\PaymentModes::options() ?></select>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Reference</label>
      <input name="reference_no" class="form-control" placeholder="Transaction / reference no.">
    </div>
    <div class="col-12 d-none" id="backdateReasonBox">
      <label class="form-label fw-semibold">Reason for back-dating <span class="text-danger">*</span></label>
      <input name="backdate_reason" id="backdateReason" class="form-control" maxlength="255" value="<?= esc(old('backdate_reason') ?? '', 'attr') ?>" placeholder="Why is this receipt dated earlier than today?">
    </div>
    <div class="col-12">
      <input name="notes" class="form-control" placeholder="Notes">
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-md-4">
      <div class="border rounded p-3 h-100 bg-light-subtle">
        <div class="small text-muted">Current OS Balance</div>
        <div id="currentOs" class="fs-4 fw-bold">Rs. 0.00</div>
        <div class="small text-muted">Before this receipt</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="border rounded p-3 h-100 bg-light-subtle">
        <div class="small text-muted">Receive Amount</div>
        <div id="receivePreview" class="fs-4 fw-bold">Rs. 0.00</div>
        <div class="small text-muted">Amount being posted</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="border rounded p-3 h-100" id="balanceAfterBox">
        <div class="small text-muted">Balance After Receipt</div>
        <div id="balanceAfter" class="fs-4 fw-bold">Rs. 0.00</div>
        <div id="balanceAfterHint" class="small text-muted">Current OS minus received amount</div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <div id="creditNotice" class="small text-muted">Select a customer to see the current OS balance.</div>
    <button class="btn btn-primary px-4"><i class="bi bi-cash-coin me-1"></i>Receive</button>
  </div>
</form>

<div class="card shadow-sm mt-4">
  <div class="card-header fw-semibold">Receipt History <span class="text-muted fw-normal small">— customer receipts and POS payment records</span></div>
  <div class="card-body table-responsive">
    <table class="table table-sm table-striped align-middle datatable">
      <thead><tr><th>Date</th><th>Receipt / Transaction</th><th>Customer</th><th>Amount</th><th>Mode</th><th>Source</th><th>Status</th><th>Reference</th><th>Details</th><th>Receipt</th></tr></thead>
      <tbody>
      <?php foreach($receipts as $r):?>
        <tr>
          <td><?=esc($r['receipt_at'])?></td>
          <td><?=esc($r['receipt_no'])?></td>
          <td><?=esc($r['customer_name']??'Walk-in / Cash')?></td>
          <td>Rs. <?=number_format((float)$r['amount'],2)?></td>
          <td><?=esc(\App\Services\PaymentModes::label($r['payment_mode']))?></td>
          <td><?=esc($r['source']??'Unknown')?></td>
          <td><span class="badge text-bg-<?=($r['status']??'')==='posted'?'success':'secondary'?>"><?=esc(strtoupper($r['status']??'UNKNOWN'))?></span></td>
          <td><?=esc($r['reference_no']??'')?></td>
          <td><?=esc($r['notes']??'')?></td>
          <td class="text-nowrap">
            <?php $isPos = empty($r['receipt_id']);
              $printUrl = site_url(($isPos ? 'sales/receipt/' . (int) $r['sale_id'] : 'receipts/receipt/' . (int) $r['receipt_id']) . '?reprint=1'); ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" title="Reprint receipt"
              onclick="openCustomerReceipt(<?= esc(json_encode($printUrl), 'attr') ?>, <?= esc(json_encode($isPos ? 'Sale Receipt' : 'Customer Receipt'), 'attr') ?>)">
              <i class="bi bi-printer me-1"></i>Reprint
            </button>
          </td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>

<script>
const customerBalances=<?=json_encode($customerBalances??[],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const receiptCustomer=document.getElementById('receiptCustomer');
const receiptAmount=document.getElementById('receiptAmount');
const currentOs=document.getElementById('currentOs');
const receivePreview=document.getElementById('receivePreview');
const balanceAfter=document.getElementById('balanceAfter');
const balanceAfterBox=document.getElementById('balanceAfterBox');
const balanceAfterHint=document.getElementById('balanceAfterHint');
const creditNotice=document.getElementById('creditNotice');

function money(value){return 'Rs. '+Number(value||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}
function refreshReceiptPreview(){
  const customerId=receiptCustomer.value;
  const os=customerId!==''?Number(customerBalances[customerId]??0):0;
  const received=Math.max(0,Number(receiptAmount.value||0));
  const after=os-received;
  currentOs.textContent=money(os);
  receivePreview.textContent=money(received);
  balanceAfter.textContent=money(after);
  balanceAfterBox.classList.toggle('border-danger',after<0);
  balanceAfterBox.classList.toggle('bg-danger-subtle',after<0);
  balanceAfterBox.classList.toggle('border-success',after>=0);
  balanceAfterHint.textContent=after<0?'Negative balance means the customer has a credit/advance of '+money(Math.abs(after))+'.':'Current OS minus received amount';
  creditNotice.textContent=customerId===''?'Select a customer to see the current OS balance.':(after<0?'Receipt exceeds current OS by '+money(Math.abs(after))+'; the resulting customer balance will be negative.':'Balance after receipt will be '+money(after)+'.');
}
// Back-dating: a reason is required before today, and cash cannot go before the open counter session.
const receiveDate=document.getElementById('receiveDate');
const receiptMode=document.getElementById('receiptMode');
const cashOpenedDate=<?= json_encode(substr((string) ($cashOpenedAt ?? ''), 0, 10)) ?>;
function refreshBackdate(){
  receiveDate.min=receiptMode.value==='cash'?cashOpenedDate:'';
  const back=receiveDate.value!==''&&receiveDate.value<receiveDate.max;
  document.getElementById('backdateReasonBox').classList.toggle('d-none',!back);
  document.getElementById('backdateReason').required=back;
}
receiveDate.addEventListener('change',refreshBackdate);
receiveDate.addEventListener('input',refreshBackdate);
receiptMode.addEventListener('change',refreshBackdate);
refreshBackdate();
receiptCustomer?.addEventListener('change',refreshReceiptPreview);
receiptAmount?.addEventListener('input',refreshReceiptPreview);
refreshReceiptPreview();
</script>
<?= $this->endSection() ?>