<?php $uiLang = \App\Libraries\UiLanguage::fromRequest(); $uiDir = \App\Libraries\UiLanguage::meta($uiLang)['dir'];
$amount = (float) $receipt['amount'];
$isReprint = (bool) service('request')->getGet('reprint');
$isVoided = $receipt['status'] !== 'posted';
?>
<!doctype html>
<html lang="<?= esc($uiLang, 'attr') ?>" dir="<?= esc($uiDir, 'attr') ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Customer Receipt <?= esc($receipt['receipt_no']) ?></title>
<style>
*{box-sizing:border-box}html,body{margin:0;padding:0}body{width:80mm;max-width:80mm;margin:0 auto;padding:4mm 3mm;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:1.25;color:#000;background:#fff}
.header{text-align:center}.shop-name{font-size:15px;font-weight:700;margin-bottom:2px}.receipt-title{font-size:11px;font-weight:700;margin-top:2px}.small{font-size:9px}.rule{border-top:1px dashed #000;margin:5px 0}.row{display:flex;justify-content:space-between;gap:6px}.row span:first-child{flex:1}.row span:last-child{text-align:right}
.total{font-size:13px;font-weight:700;margin-top:5px}.footer{text-align:center;margin-top:6px}.actions{text-align:center;margin-top:10px}button{font-size:12px;padding:6px 12px;margin:0 3px}.void{text-align:center;font-weight:700;font-size:13px;margin:4px 0}
@media print{@page{size:80mm auto;margin:0}html,body{width:80mm;max-width:80mm}body{padding:3mm 2.5mm}.actions{display:none}}
</style><?= view('partials/i18n_head', ['uiLang' => $uiLang]) ?></head><body>
<div class="header">
  <div class="shop-name"><?= esc($location['name'] ?? 'Perfect LPG') ?></div>
  <?php if (!empty($location['address'])): ?><div class="small"><?= esc($location['address']) ?><?= !empty($location['city']) ? ', ' . esc($location['city']) : '' ?></div><?php endif; ?>
  <div class="receipt-title">CUSTOMER RECEIPT</div>
</div>
<div class="rule"></div>
<?php if ($isReprint): ?><div style="text-align:center;font-weight:700;letter-spacing:1px">*** REPRINT ***</div><?php endif; ?>
<?php if ($isVoided): ?><div class="void">*** VOIDED ***</div><?php endif; ?>
<div class="row"><span>No.</span><span><?= esc($receipt['receipt_no']) ?></span></div>
<div class="row"><span>Receive Date</span><span><?= esc(date('d M Y', strtotime($receipt['receipt_at']))) ?></span></div>
<div class="row"><span>Customer</span><span><?= esc($receipt['customer_name']) ?></span></div>
<?php if (!empty($receipt['created_by_name'])): ?><div class="row"><span>Cashier</span><span><?= esc($receipt['created_by_name']) ?></span></div><?php endif; ?>
<div class="rule"></div>
<div class="row"><span>Payment Mode</span><span><?= esc(\App\Services\PaymentModes::label($receipt['payment_mode'])) ?></span></div>
<?php if (!empty($receipt['reference_no'])): ?><div class="row"><span>Reference</span><span><?= esc($receipt['reference_no']) ?></span></div><?php endif; ?>
<div class="rule"></div>
<?php if (!$isReprint && !$isVoided): ?>
<div class="row"><span>Previous OS Balance</span><span>Rs. <?= number_format($osBalance + $amount, 2) ?></span></div>
<?php endif; ?>
<div class="row total"><span>Receipt Amount</span><span>Rs. <?= number_format($amount, 2) ?></span></div>
<div class="row total"><span><?= $isReprint || $isVoided ? 'OS Balance (now)' : 'OS Balance' ?></span><span>Rs. <?= number_format($osBalance, 2) ?></span></div>
<?php if (!empty($receipt['notes'])): ?><div class="rule"></div><div class="small">Notes: <?= esc($receipt['notes']) ?></div><?php endif; ?>
<div class="rule"></div><div class="footer">Thank you</div>
<div class="actions"><button type="button" onclick="window.print()">Print</button><button type="button" id="savePdfBtn" title="Opens the print dialog. Choose Save as PDF as the destination.">Save as PDF</button><button type="button" id="shareWaBtn" style="background:#25d366;color:#fff;border:1px solid #1da851">WhatsApp</button><button type="button" id="closeBtn" onclick="window.close()">Close</button></div>
<script>if(window.self!==window.top){var cb=document.getElementById("closeBtn");if(cb)cb.style.display="none";}</script>
<script>(function(){var b=document.getElementById("savePdfBtn");if(!b)return;var name=<?= json_encode('Receipt-' . ($receipt['receipt_no'] ?? '')) ?>,old=document.title;b.onclick=function(){document.title=name;window.onafterprint=function(){document.title=old;window.onafterprint=null;};window.print();};})();</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
(function(){
  var btn=document.getElementById("shareWaBtn");if(!btn)return;
  var no=<?= json_encode((string) ($receipt["receipt_no"] ?? "")) ?>,customer=<?= json_encode((string) ($receipt["customer_name"] ?? ""), JSON_HEX_TAG) ?>;
  var phone=(<?= json_encode((string) ($receipt["customer_phone"] ?? ""), JSON_HEX_TAG) ?>).replace(/\D+/g,"");
  if(phone.indexOf("00")===0)phone=phone.slice(2);else if(phone.charAt(0)==="0")phone="92"+phone.slice(1);
  var fname="Receipt-"+no.replace(/[^A-Za-z0-9]+/g,"-")+".png",text="Receipt "+no+" - "+customer;
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
