<?php $uiLang = \App\Libraries\UiLanguage::fromRequest(); $uiDir = \App\Libraries\UiLanguage::meta($uiLang)['dir'];
$typeLabels = ['gas_kg'=>'Gas (KG)','filled_cylinder'=>'Filled Cylinder','empty_cylinder'=>'Empty Cylinder','refill_empty'=>'Refill of Empty Cylinder'];
$trim = static fn(float $v, int $d = 2): string => rtrim(rtrim(number_format($v, $d, '.', ','), '0'), '.');
$paid = 0.0; foreach ($payments as $p) { $paid += (float) $p['amount']; }
$total = (float) $purchase['total_amount'];
?>
<!doctype html>
<html lang="<?= esc($uiLang, 'attr') ?>" dir="<?= esc($uiDir, 'attr') ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Purchase Receipt <?= esc($purchase['purchase_no']) ?></title>
<style>
*{box-sizing:border-box}html,body{margin:0;padding:0}body{width:80mm;max-width:80mm;margin:0 auto;padding:4mm 3mm;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:1.25;color:#000;background:#fff}
.header{text-align:center}.shop-name{font-size:15px;font-weight:700;margin-bottom:2px}.receipt-title{font-size:11px;font-weight:700;margin-top:2px}.small{font-size:9px}.rule{border-top:1px dashed #000;margin:5px 0}.row{display:flex;justify-content:space-between;gap:6px}.row span:first-child{flex:1}.row span:last-child{text-align:right}
table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{padding:2px 0;vertical-align:top;border-bottom:1px dotted #888;word-wrap:break-word}th{font-weight:700}.item{width:34%}.qty{width:10%;text-align:right}.gas{width:16%;text-align:right}.rate{width:19%;text-align:right}.amount{width:21%;text-align:right}
.total{font-size:13px;font-weight:700;margin-top:5px}.footer{text-align:center;margin-top:6px}.actions{text-align:center;margin-top:10px}button{font-size:12px;padding:6px 12px;margin:0 3px}.void{text-align:center;font-weight:700;font-size:13px;margin:4px 0}
@media print{@page{size:80mm auto;margin:0}html,body{width:80mm;max-width:80mm}body{padding:3mm 2.5mm}.actions{display:none}}
</style><?= view('partials/i18n_head', ['uiLang' => $uiLang]) ?></head><body>
<div class="header">
  <div class="shop-name"><?= esc($location['name'] ?? 'Perfect LPG') ?></div>
  <?php if (!empty($location['address'])): ?><div class="small"><?= esc($location['address']) ?><?= !empty($location['city']) ? ', ' . esc($location['city']) : '' ?></div><?php endif; ?>
  <div class="receipt-title">PURCHASE RECEIPT</div>
</div>
<div class="rule"></div>
<?php if (service('request')->getGet('reprint')): ?><div style="text-align:center;font-weight:700;letter-spacing:1px">*** REPRINT ***</div><?php endif; ?>
<?php if ($purchase['status'] === 'voided'): ?><div class="void">*** VOIDED ***</div><?php endif; ?>
<div class="row"><span>No.</span><span><?= esc($purchase['purchase_no']) ?></span></div>
<div class="row"><span>Date</span><span><?= esc($purchase['transaction_at']) ?></span></div>
<div class="row"><span>Supplier</span><span><?= esc($purchase['supplier_name']) ?></span></div>
<?php if (!empty($purchase['plant_name'])): ?><div class="row"><span>Plant</span><span><?= esc($purchase['plant_name']) ?></span></div><?php endif; ?>
<?php if (!empty($purchase['created_by_name'])): ?><div class="row"><span>User</span><span><?= esc($purchase['created_by_name']) ?></span></div><?php endif; ?>
<div class="rule"></div>
<table><thead><tr><th class="item">Item</th><th class="qty">Qty</th><th class="gas">Gas</th><th class="rate">Rate</th><th class="amount">Amount</th></tr></thead><tbody>
<?php foreach ($items as $i): $isCyl = in_array($i['line_type'], ['filled_cylinder','empty_cylinder','refill_empty'], true); ?>
<tr>
  <td class="item"><?= esc($typeLabels[$i['line_type']] ?? $i['line_type']) ?><?php if (!empty($i['cylinder_code'])): ?><br><span class="small"><?= esc($i['cylinder_code']) ?><?= !empty($i['capacity_kg']) ? ' · ' . $trim((float) $i['capacity_kg']) . ' KG' : '' ?></span><?php endif; ?><?php if (!empty($i['receive_location_name'])): ?><br><span class="small">Receive at: <?= esc($i['receive_location_name']) ?></span><?php endif; ?></td>
  <td class="qty"><?= $isCyl ? number_format((float) $i['quantity'], 0) : $trim((float) $i['quantity']) ?></td>
  <td class="gas"><?php
    $gasKg = $i['line_type'] === 'gas_kg' ? (float) $i['quantity'] : ((float) $i['actual_gas_weight_kg'] * ($isCyl ? (float) $i['quantity'] : 1));
    echo $gasKg > 0 ? $trim($gasKg) . ' KG' : '—'; ?></td>
  <td class="rate"><?= number_format((float) $i['unit_rate'], 2) ?></td>
  <td class="amount"><?= number_format((float) $i['line_total'], 2) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<div class="rule"></div>
<div class="row"><span>Subtotal</span><span><?= number_format((float) $purchase['subtotal'], 2) ?></span></div>
<div class="row"><span>Discount</span><span><?= number_format((float) $purchase['discount_amount'], 2) ?></span></div>
<div class="row total"><span>Total Amount</span><span>Rs. <?= number_format($total, 2) ?></span></div>
<div class="rule"></div>
<?php foreach ($payments as $p): ?><div class="row"><span>Paid — <?= esc(\App\Services\PaymentModes::label($p['payment_mode'])) ?></span><span>Rs. <?= number_format((float) $p['amount'], 2) ?></span></div><?php endforeach; ?>
<div class="row total"><span>Total Paid</span><span>Rs. <?= number_format($paid, 2) ?></span></div>
<div class="row total"><span>Remaining (Payable)</span><span>Rs. <?= number_format(max(0, $total - $paid), 2) ?></span></div>
<?php if ($supplierBalance !== null): ?><div class="row"><span>Supplier Total Payable (now)</span><span>Rs. <?= number_format((float) $supplierBalance, 2) ?></span></div><?php endif; ?>
<?php if (!empty($purchase['notes'])): ?><div class="rule"></div><div class="small">Notes: <?= esc($purchase['notes']) ?></div><?php endif; ?>
<div class="rule"></div><div class="footer">Thank you</div>
<div class="actions"><button type="button" onclick="window.print()">Print</button><button type="button" id="savePdfBtn">Save as PDF</button><button type="button" id="shareWaBtn" style="background:#25d366;color:#fff;border:1px solid #1da851">WhatsApp</button><button type="button" id="closeBtn" onclick="window.close()">Close</button></div>
<script>if(window.self!==window.top){var cb=document.getElementById("closeBtn");if(cb)cb.style.display="none";}</script>
<script>(function(){var b=document.getElementById("savePdfBtn");if(!b)return;var name=<?= json_encode('Purchase-' . ($purchase['purchase_no'] ?? '')) ?>,old=document.title;b.onclick=function(){document.title=name;window.onafterprint=function(){document.title=old;window.onafterprint=null;};window.print();};})();</script>
<script src="<?= base_url('lib/cdnjs/') ?>html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
(function(){
  var btn=document.getElementById("shareWaBtn");if(!btn)return;
  var no=<?= json_encode((string) ($purchase["purchase_no"] ?? "")) ?>,supplier=<?= json_encode((string) ($purchase["supplier_name"] ?? "")) ?>;
  var phone=(<?= json_encode((string) ($purchase["supplier_phone"] ?? "")) ?>).replace(/\D+/g,"");
  if(phone.indexOf("00")===0)phone=phone.slice(2);else if(phone.charAt(0)==="0")phone="92"+phone.slice(1);
  var fname="Purchase-"+no.replace(/[^A-Za-z0-9]+/g,"-")+".png",text="Purchase receipt "+no+" - "+supplier;
  function download(blob){var a=document.createElement("a");a.href=URL.createObjectURL(blob);a.download=fname;document.body.appendChild(a);a.click();a.remove();}
  btn.onclick=function(){
    if(!window.html2canvas){alert("Image sharing needs an internet connection to load. Try again.");return;}
    var actions=document.querySelector(".actions"),oldMargin=document.body.style.margin;actions.style.visibility="hidden";document.body.style.margin="0";window.scrollTo(0,0);
    html2canvas(document.body,{scale:3,backgroundColor:"#ffffff",width:document.body.offsetWidth,height:actions.offsetTop,x:0,y:0,scrollX:0,scrollY:0,windowWidth:document.body.offsetWidth+40}).then(function(cv){
      actions.style.visibility="";document.body.style.margin=oldMargin;
      cv.toBlob(function(blob){
        if(!blob){alert("Could not create the image.");return;}
        var file=new File([blob],fname,{type:"image/png"});
        if(navigator.canShare&&navigator.canShare({files:[file]})){navigator.share({files:[file],title:text}).catch(function(){});return;}
        window.open("https://wa.me/"+phone+"?text="+encodeURIComponent(text),"_blank");
        if(navigator.clipboard&&window.ClipboardItem){
          navigator.clipboard.write([new ClipboardItem({"image/png":blob})]).then(function(){alert("The receipt image is copied. In the WhatsApp chat, press Ctrl+V to paste it, then Send.");}).catch(function(){download(blob);alert("The image was saved as "+fname+". Attach it in the WhatsApp chat.");});
        }else{download(blob);alert("The image was saved as "+fname+". Attach it in the WhatsApp chat.");}
      },"image/png");
    }).catch(function(){actions.style.visibility="";document.body.style.margin=oldMargin;alert("Could not create the image.");});
  };
})();
</script>
<?= view('partials/i18n_foot', ['uiLang' => $uiLang]) ?></body></html>
