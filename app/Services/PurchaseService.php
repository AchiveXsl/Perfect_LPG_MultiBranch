<?php
namespace App\Services;
use Config\Database;
use RuntimeException;
class PurchaseService{
 protected $db; protected $inv; protected $cash; protected $cylinders;
 public function __construct(){ $this->db=Database::connect(); $this->inv=new InventoryService(); $this->cash=new CashService(); $this->cylinders=new CylinderUnitService(); }
 /**
  * Post a purchase. $locationId is the branch entering it (the Head Office): it owns the purchase, the supplier
  * payable and any cash paid. Each line's stock is received at its own receive_location_id (default: $locationId).
  */
 /**
  * The purchase date and time chosen on the screen, and why it is back-dated.
  * Blank, or up to 10 minutes old (the screen fills it in when it opens), means now. The future is rejected.
  * A date before today needs a reason.
  * @return array{0:string,1:?string}
  */
 protected function resolvePurchaseDate(array $p):array{
  $now=\App\Services\BranchContext::nowTs();$input=str_replace('T',' ',trim((string)($p['transaction_at']??'')));
  if($input==='')return [date('Y-m-d H:i:s',$now),null];
  if(strlen($input)===16)$input.=':00';
  $d=\DateTime::createFromFormat('!Y-m-d H:i:s',$input);
  if(!$d||$d->format('Y-m-d H:i:s')!==$input)throw new RuntimeException('Invalid purchase date and time.');
  $ts=$d->getTimestamp();
  if($ts>$now+300)throw new RuntimeException('Purchase date and time cannot be in the future.');
  if($ts>=$now-600)return [date('Y-m-d H:i:s',$now),null];
  if(substr($input,0,10)>=date('Y-m-d',$now))return [$input,null];
  $reason=trim((string)($p['backdate_reason']??''));
  if($reason==='')throw new RuntimeException('Enter the reason for the back-dated purchase.');
  if(mb_strlen($reason)>255)throw new RuntimeException('Back-date reason cannot exceed 255 characters.');
  return [$input,$reason];
 }
 public function post(array $p,int $userId,int $locationId):array{
  $supplierId=(int)($p['supplier_id']??0); $lines=$p['lines']??[]; $payments=$p['payments']??[];
  $activeBranches=array_map('intval',array_column(BranchContext::allActive(),'id'));
  $receiveAt=[];
  foreach((array)$lines as $i=>$l){
   $dest=(int)($l['receive_location_id']??0) ?: $locationId;
   if(!in_array($dest,$activeBranches,true)) throw new RuntimeException('Purchase line '.($i+1).': select an active branch to receive the stock.');
   if($dest!==$locationId && BranchContext::purchaseMode()==='split') throw new RuntimeException('Purchase line '.($i+1).': in split purchasing each branch buys only its own stock.');
   $receiveAt[$i]=$dest;
  }
  foreach(array_unique(array_merge([$locationId],array_values($receiveAt))) as $loc) (new CostService())->ensureReady((int)$loc);
  if(!$supplierId||!$lines) throw new RuntimeException('Supplier and purchase lines are required.');
  $supplier=$this->db->table('suppliers')->where('id',$supplierId)->get()->getRowArray();
  if(!$supplier||(int)$supplier['is_active']!==1) throw new RuntimeException('Supplier is invalid or inactive.');
  $subtotal=0;$prepared=[];$inventory=[];$cash=0;$credit=0;
  foreach($lines as $i=>$l){
   $type=(string)($l['line_type']??'');$qty=(float)($l['quantity']??0);$rate=(float)($l['unit_rate']??0);$ct=isset($l['cylinder_type_id'])&&$l['cylinder_type_id']!==''?(int)$l['cylinder_type_id']:null;
   if($qty<=0||$rate<0) throw new RuntimeException('Invalid purchase line '.($i+1).'.');
   if(!in_array($type,['gas_kg','filled_cylinder','empty_cylinder','refill_empty'],true)) throw new RuntimeException('Invalid purchase type.');
   if(!$ct) throw new RuntimeException('Cylinder type required.');

   $actualRaw=$l['actual_gas_weight_kg']??null;
   $actual=$actualRaw!==null && $actualRaw!==''?(float)$actualRaw:0;
   if($type==='filled_cylinder' || $type==='refill_empty'){
    if(floor($qty)!==$qty) throw new RuntimeException(($type==='refill_empty'?'Refill':'Filled cylinder').' quantity must be a whole number.');
    $ctRow=$this->db->table('cylinder_types')->where('id',$ct)->get()->getRowArray();
    if(!$ctRow || !(int)$ctRow['is_active']) throw new RuntimeException('Invalid or inactive cylinder type.');
    $cap=(float)$ctRow['capacity_kg'];
    $actual=$actualRaw!==null && $actualRaw!==''?$actual:$cap;
    if($actual<=0||$actual>$cap) throw new RuntimeException('Actual gas weight must be greater than zero and cannot exceed cylinder capacity.');
   } else {
    $actual=0;
   }

   $lineTotal=$qty*$rate;$subtotal+=$lineTotal;
   $prepared[]=['line_no'=>$i+1,'receive_location_id'=>$receiveAt[$i],'line_type'=>$type,'cylinder_type_id'=>$ct,'quantity'=>$qty,'actual_gas_weight_kg'=>$actual,'unit_rate'=>$rate,'line_total'=>$lineTotal];
   $inventory[]=['location_id'=>$receiveAt[$i],'inventory_type'=>$type,'cylinder_type_id'=>$ct,'quantity'=>$qty,'actual_gas_weight_kg'=>$actual];
  }
  $discount=max(0,(float)($p['discount_amount']??0));if($discount>$subtotal)throw new RuntimeException('Discount exceeds subtotal.');
  $total=$subtotal-$discount;$paid=0;$normalizedPayments=[];
  foreach($payments as $pay){$mode=(string)($pay['payment_mode']??'');$amount=(float)($pay['amount']??0);if($amount<=0)continue;if(!in_array($mode,PaymentModes::values(),true)||$amount<=0)throw new RuntimeException('Invalid purchase payment.');$paid+=$amount;$normalizedPayments[]=['payment_mode'=>$mode,'amount'=>$amount,'reference_no'=>trim((string)($pay['reference_no']??''))?:null];}
  $currentPayable=$total;$previousPayable=$this->supplierBalance($supplierId,$locationId);
  // Paying more than the payable is allowed: the excess is recorded as an advance
  // supplier payment, which leaves the supplier balance negative.
  $currentPurchasePaid=min($paid,$currentPayable);$credit=max(0,$currentPayable-$currentPurchasePaid);$priorPayableSettlement=max(0,$paid-$currentPurchasePaid);
  $newPayable=$previousPayable-$priorPayableSettlement+$credit;
  $plantId=isset($p['plant_id'])&&$p['plant_id']!==''?(int)$p['plant_id']:null;
  if($plantId!==null){
   $plant=$this->db->table('supplier_plants')->where(['id'=>$plantId,'supplier_id'=>$supplierId,'is_active'=>1])->get()->getRowArray();
   if(!$plant) throw new RuntimeException('Selected plant does not belong to this supplier.');
  }
  if($newPayable>(float)$supplier['credit_limit']+0.01)throw new RuntimeException('Supplier credit limit exceeded. Available credit is Rs. '.number_format(max(0,(float)$supplier['credit_limit']-$previousPayable),2).'.');
  [$at,$backdateReason]=$this->resolvePurchaseDate($p);
  // Cash leaves the open counter session, so a cash purchase cannot be dated before that session opened.
  if(in_array('cash',array_column($normalizedPayments,'payment_mode'),true)){
   $session=$this->cash->openSessionForLocation($locationId);
   if(!$session)throw new RuntimeException('Open the counter cash session before posting a cash purchase.');
   $openedAt=(string)$session['opened_at'];
   if(substr($at,0,16)<substr($openedAt,0,16))throw new RuntimeException('A cash purchase cannot be dated before the open counter session, which opened on '.date('d M Y H:i',strtotime($openedAt)).'.');
   $at=max($at,$openedAt);
  }
  $this->db->transBegin();
  $tgMark = \App\Services\TimelineGuard::mark();
  try{
   $supplier=$this->db->query('SELECT * FROM suppliers WHERE id=? FOR UPDATE',[$supplierId])->getRowArray();
   if($credit>0){$currentCredit=$this->supplierBalance($supplierId,$locationId);if($currentCredit+$credit>(float)$supplier['credit_limit'])throw new RuntimeException('Supplier credit limit exceeded.');}
   foreach($inventory as $m){if($m['inventory_type']!=='refill_empty'&&$this->inv->stock((int)$m['location_id'],$m['inventory_type'],$m['cylinder_type_id'])<0)throw new RuntimeException('Invalid inventory state.');}
   $no='P'.date('YmdHis').'-'.random_int(100,999);
   $this->db->table('purchases')->insert(['purchase_no'=>$no,'location_id'=>$locationId,'supplier_id'=>$supplierId,'plant_id'=>$plantId,'status'=>'posted','transaction_at'=>$at,'backdate_reason'=>$backdateReason,'subtotal'=>$subtotal,'discount_amount'=>$discount,'total_amount'=>$total,'credit_amount'=>$credit,'notes'=>trim((string)($p['notes']??''))?:null,'created_by'=>$userId]);$id=(int)$this->db->insertID();
   $purchaseLineIds=[];
   foreach($prepared as $row){
    $row['purchase_id']=$id;
    $this->db->table('purchase_items')->insert($row);
    $purchaseLineIds[(int)$row['line_no']]=(int)$this->db->insertID();
   }
   foreach($inventory as $index=>$m){
    $this->inv->receivePurchase(
      (int)$m['location_id'],
      $m['inventory_type'],
      $m['cylinder_type_id'],
      (float)$m['quantity'],
      (float)$m['actual_gas_weight_kg'],
      $id,
      $userId,
      $purchaseLineIds[$index+1] ?? null,
      $at
    );
   }
   $remainingCurrent=$currentPayable;$cashPurchase=0;
   foreach($normalizedPayments as $pay){$applyCurrent=min((float)$pay['amount'],$remainingCurrent);$applyPrior=max(0,(float)$pay['amount']-$applyCurrent);
    if($applyCurrent>0){$this->db->table('purchase_payments')->insert(['purchase_id'=>$id,'payment_mode'=>$pay['payment_mode'],'amount'=>$applyCurrent,'reference_no'=>$pay['reference_no'],'payment_at'=>$at,'paid_by'=>$userId]);if($pay['payment_mode']==='cash')$cashPurchase+=$applyCurrent;}
    if($applyPrior>0){$paymentNo='SP'.date('YmdHis').'-'.random_int(100,999);$this->db->table('supplier_payments')->insert(['payment_no'=>$paymentNo,'location_id'=>$locationId,'supplier_id'=>$supplierId,'status'=>'posted','amount'=>$applyPrior,'payment_mode'=>$pay['payment_mode'],'payment_at'=>$at,'reference_no'=>$pay['reference_no'],'notes'=>'Applied to previous supplier payable during purchase '.$no,'created_by'=>$userId]);$supplierPaymentId=(int)$this->db->insertID();if($pay['payment_mode']==='cash'){ $session=$this->cash->openSessionForLocation($locationId);if(!$session)throw new RuntimeException('Open the counter cash session before posting a cash purchase.');$this->cash->postGeneric((int)$session['id'],'supplier_payment','out',$applyPrior,'supplier_payment',$supplierPaymentId,$userId,'Supplier payable settlement during purchase '.$no,$at);}}
    $remainingCurrent=max(0,$remainingCurrent-(float)$pay['amount']);
   }
   if($cashPurchase>0){$session=$this->cash->openSessionForLocation($locationId);if(!$session)throw new RuntimeException('Open the counter cash session before posting a cash purchase.');$this->cash->postGeneric((int)$session['id'],'purchase_cash','out',$cashPurchase,'purchase',$id,$userId,'Purchase cash',$at);}
   if(!$this->db->transStatus())throw new RuntimeException('Purchase posting failed.');
   (new CostService())->addPurchaseLayers($id);
   \App\Services\TimelineGuard::check($tgMark, 'this purchase');
   $this->db->transCommit();AuditService::log('CREATE','purchase',$id,null,['purchase_no'=>$no,'transaction_at'=>$at,'backdate_reason'=>$backdateReason,'total'=>$total,'amount_paid'=>$paid,'current_credit'=>$credit,'previous_payable'=>$previousPayable,'new_payable'=>$newPayable],$userId,$locationId);return ['id'=>$id,'purchase_no'=>$no,'total'=>$total,'previous_payable'=>$previousPayable,'amount_paid'=>$paid,'balance_payable'=>$newPayable];
  }catch(\Throwable $e){$this->db->transRollback();throw $e;}
 }
 /**
  * Supplier payable. Purchasing is central, so a supplier has one account across all branches:
  * every purchase and supplier payment counts, whichever branch recorded it. $locationId is kept for callers
  * written before branches and is ignored.
  */
 public function supplierBalance(int $supplierId, ?int $locationId=null): float
 {
  // credit_amount is already the unpaid portion of each posted purchase,
  // so purchase payments must NOT be subtracted again here.
  // Each branch owes its own suppliers ($locationId). null = all branches combined.
  // The supplier opening balance belongs to the Head Office account.
  $q=$this->db->table('purchases')->selectSum('credit_amount','credit')
   ->where('supplier_id',$supplierId)->where('status','posted');
  if($locationId) $q->where('location_id',$locationId);
  $s=$q->get()->getRowArray();

  // These are only payments made against an older supplier payable.
  $q=$this->db->table('supplier_payments')->selectSum('amount','account_paid')
   ->where('supplier_id',$supplierId)->where('status','posted');
  if($locationId) $q->where('location_id',$locationId);
  $ap=$q->get()->getRowArray();

  $supplier=$this->db->table('suppliers')->where('id',$supplierId)->get()->getRowArray();
  $opening=(!$locationId || BranchContext::isHeadOffice($locationId))?(float)($supplier['opening_balance']??0):0.0;
  return $opening
      +(float)($s['credit']??0)
      -(float)($ap['account_paid']??0);
 }

 /**
  * Stock use of each purchase line, from its cost layers: [item_id => ['sold'=>qty sold, 'moved'=>qty transferred out, 'unit'=>'KG'|'cyl']].
  * Sold quantity is what posted sales consumed (cost_layer_usage); moved is what left by transfer.
  */
 public function lineStockUse(int $purchaseId): array
 {
  $out=[];
  foreach($this->db->table('cost_layers')->where(['source_type'=>'purchase','source_id'=>$purchaseId])->get()->getResultArray() as $l){
   $sold=(float)($this->db->query('SELECT COALESCE(SUM(qty),0) q FROM cost_layer_usage WHERE layer_id=? AND reversed=0',[(int)$l['id']])->getRowArray()['q']??0);
   $used=max(0,(float)$l['qty_in']-(float)$l['qty_left']);
   $out[(int)$l['source_line_id']]=['sold'=>round($sold,3),'moved'=>round(max(0,$used-$sold),3),'unit'=>$l['kind']==='gas'?'KG':'cyl'];
  }
  return $out;
 }

 /**
  * Change the rate of purchase lines after posting (owner decision 2026-11-24). Quantities and stock never change.
  * - Line total, purchase total and the supplier payable change by the difference (money already paid stays).
  * - The cost layer of each line takes the new unit cost, so stock still on hand is costed at the new rate.
  * - If any of that stock was already sold, $applyToSales must be true: the cost and profit of those sales are
  *   restated with the new rate. Otherwise it is refused with a message the screen shows as a question.
  * Stock already transferred to another branch keeps the cost it carried.
  * $rates is [purchase_item_id => new rate].
  */
 public function changeRates(int $purchaseId,array $rates,string $reason,bool $applyToSales,int $userId,int $locationId): array
 {
  $reason=trim($reason);
  if($reason==='') throw new RuntimeException('Enter a reason for the rate change.');
  $this->db->transBegin();
  try{
   $p=$this->db->query('SELECT * FROM purchases WHERE id=? AND location_id=? FOR UPDATE',[$purchaseId,$locationId])->getRowArray();
   if(!$p) throw new RuntimeException('Purchase not found in this branch.');
   if($p['status']!=='posted') throw new RuntimeException('A voided purchase cannot be changed.');
   $items=[];
   foreach($this->db->table('purchase_items')->where('purchase_id',$purchaseId)->get()->getResultArray() as $it) $items[(int)$it['id']]=$it;
   $use=$this->lineStockUse($purchaseId);
   $changed=[];
   foreach($rates as $itemId=>$rate){
    $itemId=(int)$itemId; if(!isset($items[$itemId]) || $rate===null || $rate==='') continue;
    if(!is_numeric($rate) || (float)$rate<0) throw new RuntimeException('Rates must be zero or more.');
    $rate=round((float)$rate,4);
    if(abs($rate-(float)$items[$itemId]['unit_rate'])<0.00005) continue;
    $changed[$itemId]=$rate;
   }
   if(!$changed) throw new RuntimeException('No rate was changed.');
   $soldLines=array_filter(array_keys($changed),static fn($id)=>($use[$id]['sold']??0)>0.0005);
   if($soldLines && !$applyToSales){
    throw new RuntimeException('Stock of this purchase is already sold. Changing the rate may change the profit and loss of those sales. Confirm to apply the new purchase rate to all sales linked with this purchase batch.');
   }

   $delta=0.0; $restated=[];
   foreach($changed as $itemId=>$rate){
    $it=$items[$itemId];
    $oldTotal=(float)$it['line_total']; $newTotal=round((float)$it['quantity']*$rate,2);
    $delta+=$newTotal-$oldTotal;
    $this->db->table('purchase_items')->where('id',$itemId)->update(['unit_rate'=>$rate,'line_total'=>$newTotal]);
    // The line cost layer: same quantity, new unit cost.
    foreach($this->db->table('cost_layers')->where(['source_type'=>'purchase','source_id'=>$purchaseId,'source_line_id'=>$itemId])->get()->getResultArray() as $l){
     $q=(float)$l['qty_in']; if($q<=0) continue;
     $newUnit=$newTotal/$q; $diff=$newUnit-(float)$l['unit_cost'];
     $this->db->table('cost_layers')->where('id',(int)$l['id'])->update(['unit_cost'=>$newUnit]);
     // Sales that consumed this layer: their cost moves by consumed qty x unit cost difference.
     foreach($this->db->table('cost_layer_usage')->where(['layer_id'=>(int)$l['id'],'reversed'=>0])->get()->getResultArray() as $u){
      $amount=(float)$u['amount']+(float)$u['qty']*$diff;
      $this->db->table('cost_layer_usage')->where('id',(int)$u['id'])->update(['amount'=>$amount,'unit_cost'=>(float)$u['qty']>0?$amount/(float)$u['qty']:(float)$u['unit_cost']]);
      $restated[(int)$u['sale_item_id']]=(int)$u['sale_id'];
     }
    }
    $this->db->table('purchase_rate_changes')->insert([
     'purchase_id'=>$purchaseId,'purchase_item_id'=>$itemId,'old_rate'=>(float)$it['unit_rate'],'new_rate'=>$rate,
     'old_line_total'=>$oldTotal,'new_line_total'=>$newTotal,'sold_qty'=>(float)($use[$itemId]['sold']??0),
     'sales_restated'=>($use[$itemId]['sold']??0)>0.0005?1:0,'reason'=>$reason,'changed_by'=>$userId,'changed_at'=>\App\Services\BranchContext::now(),
    ]);
   }
   // Sale lines whose cost moved: totals come back from their usage rows.
   foreach(array_keys($restated) as $saleItemId){
    $r=$this->db->query("SELECT COALESCE(SUM(CASE WHEN kind='gas' THEN amount END),0) g, COALESCE(SUM(CASE WHEN kind='cylinder' THEN amount END),0) c FROM cost_layer_usage WHERE sale_item_id=? AND reversed=0",[$saleItemId])->getRowArray();
    $this->db->table('sale_items')->where('id',$saleItemId)->update(['cost_gas'=>round((float)$r['g'],2),'cost_cylinder'=>round((float)$r['c'],2),'cost_amount'=>round((float)$r['g']+(float)$r['c'],2)]);
   }
   // Purchase totals and the unpaid part (credit). Money already paid stays as paid.
   $subtotal=round((float)$p['subtotal']+$delta,2);
   $discount=(float)$p['discount_amount'];
   if($discount>$subtotal+0.004) throw new RuntimeException('The new rates make the purchase smaller than its discount.');
   $this->db->table('purchases')->where('id',$purchaseId)->update([
    'subtotal'=>$subtotal,'total_amount'=>round($subtotal-$discount,2),'credit_amount'=>round((float)$p['credit_amount']+$delta,2),
   ]);
   if(!$this->db->transStatus()) throw new RuntimeException('Rate change failed.');
   $this->db->transCommit();
  }catch(\Throwable $e){
   $this->db->transRollback();
   throw $e;
  }
  AuditService::log('RATE_CHANGE','purchase',$purchaseId,['total_amount'=>(float)$p['total_amount']],['total_amount'=>round($subtotal-$discount,2),'lines'=>$changed,'sales_restated'=>count(array_unique($restated)),'reason'=>$reason],$userId,$locationId);
  return ['purchase_no'=>$p['purchase_no'],'delta'=>round($delta,2),'sales'=>count(array_unique($restated))];
 }

 public function canVoidPurchase(int $userId,int $locationId): bool
 {
  if($userId<=0 || $locationId<=0) return false;

  try{
   $settings=$this->db->table('shop_settings')
    ->select('purchase_void_enabled')
    ->where('location_id',$locationId)
    ->get()->getRowArray();

   if(!$settings || (int)($settings['purchase_void_enabled']??0)!==1) return false;

   return (int)$this->db->table('shop_purchase_void_users')
    ->where('location_id',$locationId)
    ->where('user_id',$userId)
    ->countAllResults()>0;
  }catch(\Throwable $e){
   return false;
  }
 }

 /**
  * Void a supplier account payment. The supplier payable goes back up, and a cash payment
  * is returned to the counter. Payments that were applied inside a purchase are voided with that purchase.
  * Returns the supplier id so the caller can go back to the ledger.
  */
 public function voidSupplierPayment(int $paymentId,int $userId,int $locationId,string $reason): int
 {
  if(!$this->canVoidPurchase($userId,$locationId)){
   throw new RuntimeException('You are not authorized to void payments. Purchase Void must be enabled in Shop Settings and your user must be assigned to this function.');
  }
  $reason=trim($reason);
  if($reason==='') throw new RuntimeException('Void reason is required.');

  $this->db->transBegin();
  try{
   $pay=$this->db->query('SELECT * FROM supplier_payments WHERE id=? AND location_id=? FOR UPDATE',[$paymentId,$locationId])->getRowArray();
   if(!$pay) throw new RuntimeException('Supplier payment not found.');
   if($pay['status']!=='posted') throw new RuntimeException('Only posted payments can be voided.');
   if(str_starts_with((string)($pay['notes']??''),'Applied to previous supplier payable during purchase')){
    throw new RuntimeException('Payment '.$pay['payment_no'].' was made inside a purchase. Void that purchase instead.');
   }
   $this->db->query('SELECT id FROM suppliers WHERE id=? FOR UPDATE',[(int)$pay['supplier_id']]);

   $this->db->table('supplier_payments')->where('id',$paymentId)->update(['status'=>'voided']);

   if((string)$pay['payment_mode']==='cash'){
    $cashRows=$this->db->table('cash_transactions')->where(['reference_type'=>'supplier_payment','reference_id'=>$paymentId,'transaction_type'=>'supplier_payment','direction'=>'out'])->get()->getResultArray();
    foreach($cashRows as $cash){
     $already=(int)$this->db->table('cash_transactions')->where(['reference_type'=>'supplier_payment_void','reference_id'=>$paymentId,'direction'=>'in','cash_session_id'=>$cash['cash_session_id']])->countAllResults();
     if($already>0) continue;
     $this->db->table('cash_transactions')->insert([
      'cash_session_id'=>$cash['cash_session_id'],'transaction_type'=>'supplier_payment','direction'=>'in',
      'amount'=>$cash['amount'],'transaction_at'=>\App\Services\BranchContext::now(),
      'reference_type'=>'supplier_payment_void','reference_id'=>$paymentId,'created_by'=>$userId,
      'notes'=>'Cash reversal of voided supplier payment '.$pay['payment_no']
     ]);
    }
   }

   if(!$this->db->transStatus()) throw new RuntimeException('Supplier payment void failed.');
   $this->db->transCommit();
   AuditService::log('VOID','supplier_payment',$paymentId,['status'=>'posted','amount'=>$pay['amount']],['status'=>'voided','reason'=>$reason],$userId,$locationId);
   return (int)$pay['supplier_id'];
  }catch(\Throwable $e){
   $this->db->transRollback();
   throw $e;
  }
 }

 public function void(int $purchaseId,int $userId,int $locationId,string $reason): string
 {
  if(!$this->canVoidPurchase($userId,$locationId)){
   throw new RuntimeException('You are not authorized to void purchases. Purchase Void must be enabled in Shop Settings and your user must be assigned to this function.');
  }

  $reason=trim($reason);
  if($reason==='') throw new RuntimeException('Void reason is required.');

  $this->db->transBegin();
  $tgMark = \App\Services\TimelineGuard::mark();
  try{
   $purchase=$this->db->query(
    'SELECT * FROM purchases WHERE id=? AND location_id=? FOR UPDATE',
    [$purchaseId,$locationId]
   )->getRowArray();
   if(!$purchase) throw new RuntimeException('Purchase not found.');
   if($purchase['status']!=='posted') throw new RuntimeException('Only posted purchases can be voided.');

   $supplier=$this->db->query(
    'SELECT * FROM suppliers WHERE id=? FOR UPDATE',
    [(int)$purchase['supplier_id']]
   )->getRowArray();
   if(!$supplier) throw new RuntimeException('Purchase supplier not found.');

   $movements=$this->db->table('inventory_movements')
    ->where('source_type','purchase')->where('source_id',$purchaseId)
    ->orderBy('id','ASC')->get()->getResultArray();

   // Cylinders refilled by this purchase existed before it: voiding restores them to empty.
   $refillLineIds=array_map('intval',array_column(
    $this->db->table('purchase_items')->select('id')->where(['purchase_id'=>$purchaseId,'line_type'=>'refill_empty'])->get()->getResultArray(),'id'
   ));
   $refilledUnits=[];
   $physicalUnits=[];
   foreach($movements as $m){
    $unitId=(int)($m['cylinder_unit_id']??0);
    if($unitId<=0) continue;
    if(in_array((int)($m['source_line_id']??0),$refillLineIds,true)) $refilledUnits[$unitId]=max((int)($refilledUnits[$unitId]??0),(int)$m['id']);
    else $physicalUnits[$unitId]=true;
   }

   foreach($refilledUnits as $unitId=>$lastMovementId){
    // The unit sits at the line's receiving branch, which may differ from the Head Office that entered the purchase.
    // Any later movement (sale, transfer, adjustment) blocks the void below.
    $unit=$this->db->query('SELECT * FROM cylinder_units WHERE id=? FOR UPDATE',[$unitId])->getRowArray();
    if(!$unit) throw new RuntimeException('Refilled physical cylinder '.$unitId.' could not be found.');
    $later=(int)$this->db->table('inventory_movements')->where('cylinder_unit_id',$unitId)->where('id >',$lastMovementId)->countAllResults();
    if($later>0 || $unit['status']!=='filled'){
     throw new RuntimeException('Purchase '.$purchase['purchase_no'].' cannot be voided because refilled cylinder '.$unit['unit_code'].' has already been used in another stock transaction.');
    }
   }

   $units=[];
   foreach(array_keys($physicalUnits) as $unitId){
    $unit=$this->db->query(
     'SELECT cu.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg
      FROM cylinder_units cu
      JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id
      WHERE cu.id=? FOR UPDATE',
     [$unitId]
    )->getRowArray();
    if(!$unit) throw new RuntimeException('Purchased physical cylinder '.$unitId.' could not be found.');

    if((string)$unit['source_type']!=='purchase' || (int)$unit['source_id']!==$purchaseId){
     throw new RuntimeException('Physical cylinder '.$unit['unit_code'].' does not belong exclusively to purchase '.$purchase['purchase_no'].'.');
    }

    $laterMovement=(int)$this->db->table('inventory_movements')
     ->where('cylinder_unit_id',$unitId)
     ->groupStart()
      ->where('source_type !=','purchase')
      ->orWhere('source_id !=',$purchaseId)
     ->groupEnd()
     ->countAllResults();
    if($laterMovement>0){
     throw new RuntimeException('Purchase '.$purchase['purchase_no'].' cannot be voided because physical cylinder '.$unit['unit_code'].' has already been used in another stock transaction.');
    }

    $custodyCount=(int)$this->db->table('cylinder_custody')
     ->where('cylinder_unit_id',$unitId)->countAllResults();
    if($custodyCount>0){
     throw new RuntimeException('Purchase '.$purchase['purchase_no'].' cannot be voided because physical cylinder '.$unit['unit_code'].' has already entered customer custody.');
    }

    $saleReferenceCount=(int)$this->db->table('sale_items')
     ->where('customer_cylinder_unit_id',$unitId)->countAllResults();
    if($saleReferenceCount>0){
     throw new RuntimeException('Purchase '.$purchase['purchase_no'].' cannot be voided because physical cylinder '.$unit['unit_code'].' is already referenced by a sale.');
    }

    $units[$unitId]=$unit;
   }

   foreach($movements as $m){
    $unitId=(int)($m['cylinder_unit_id']??0);
    $note='Reversal of purchase '.$purchase['purchase_no'];
    if($unitId>0 && isset($units[$unitId])) $note.=' — physical cylinder '.$units[$unitId]['unit_code'];
    $reverse=$m['direction']==='in'?'out':'in';

    $this->db->table('inventory_movements')->insert([
     'location_id'=>$m['location_id'],'inventory_type'=>$m['inventory_type'],
     'cylinder_type_id'=>$m['cylinder_type_id'],'quantity'=>$m['quantity'],
     'direction'=>$reverse,'movement_at'=>\App\Services\BranchContext::now(),
     'source_type'=>'purchase_void','source_id'=>$purchaseId,
     'source_line_id'=>$m['source_line_id']??null,
     'cylinder_unit_id'=>$unitId>0?$unitId:null,'created_by'=>$userId,'notes'=>$note
    ]);
   }

   $cashRows=$this->db->table('cash_transactions')
    ->where([
     'reference_type'=>'purchase',
     'reference_id'=>$purchaseId,
     'transaction_type'=>'purchase_cash',
     'direction'=>'out'
    ])->get()->getResultArray();

   foreach($cashRows as $cash){
    $already=(int)$this->db->table('cash_transactions')
     ->where([
      'reference_type'=>'purchase_void',
      'reference_id'=>$purchaseId,
      'transaction_type'=>'purchase_cash',
      'direction'=>'in',
      'cash_session_id'=>$cash['cash_session_id']
     ])->countAllResults();
    if($already>0) continue;

    $this->db->table('cash_transactions')->insert([
     'cash_session_id'=>$cash['cash_session_id'],'transaction_type'=>'purchase_cash','direction'=>'in',
     'amount'=>$cash['amount'],'transaction_at'=>\App\Services\BranchContext::now(),
     'reference_type'=>'purchase_void','reference_id'=>$purchaseId,'created_by'=>$userId,
     'notes'=>'Cash reversal of purchase '.$purchase['purchase_no']
    ]);
   }

   $settlementNote='Applied to previous supplier payable during purchase '.$purchase['purchase_no'];
   $settlements=$this->db->table('supplier_payments')
    ->where([
     'location_id'=>$locationId,
     'supplier_id'=>(int)$purchase['supplier_id'],
     'status'=>'posted',
     'notes'=>$settlementNote
    ])->get()->getResultArray();

   foreach($settlements as $settlement){
    $settlementId=(int)$settlement['id'];
    $this->db->table('supplier_payments')->where('id',$settlementId)->update(['status'=>'voided']);

    if((string)$settlement['payment_mode']==='cash'){
     $cashBuilder=$this->db->table('cash_transactions')
      ->where('transaction_type','supplier_payment')
      ->where('direction','out')
      ->where('reference_type','supplier_payment')
      ->groupStart()
       ->where('reference_id',$settlementId)
       ->orGroupStart()
        ->where('reference_id',0)
        ->where('notes',$settlementNote)
       ->groupEnd()
      ->groupEnd();
     $settlementCashRows=$cashBuilder->get()->getResultArray();

     foreach($settlementCashRows as $cash){
      $already=(int)$this->db->table('cash_transactions')
       ->where([
        'reference_type'=>'purchase_void','reference_id'=>$purchaseId,
        'transaction_type'=>'supplier_payment','direction'=>'in',
        'cash_session_id'=>$cash['cash_session_id']
       ])->where('amount',$cash['amount'])->countAllResults();
      if($already>0) continue;

      $this->db->table('cash_transactions')->insert([
       'cash_session_id'=>$cash['cash_session_id'],'transaction_type'=>'supplier_payment','direction'=>'in',
       'amount'=>$cash['amount'],'transaction_at'=>\App\Services\BranchContext::now(),
       'reference_type'=>'purchase_void','reference_id'=>$purchaseId,'created_by'=>$userId,
       'notes'=>'Cash reversal of supplier payment '.$settlement['payment_no'].' from voided purchase '.$purchase['purchase_no']
      ]);
     }
    }
   }

   $this->db->table('purchases')->where('id',$purchaseId)->update([
    'status'=>'voided','voided_by'=>$userId,'voided_at'=>\App\Services\BranchContext::now(),'void_reason'=>$reason
   ]);

   // A purchased physical cylinder did not exist before this purchase.
   // Keep its record for audit/history, but remove it from current stock exactly as
   // a sold physical cylinder. This also avoids breaking historical references.
   foreach($units as $unitId=>$unit){
    $this->db->table('cylinder_units')->where('id',$unitId)->update([
     'status'=>'sold',
     'custody_customer_id'=>null,
     'gas_weight_kg'=>0
    ]);
   }
   foreach(array_keys($refilledUnits) as $unitId){
    $this->db->table('cylinder_units')->where('id',$unitId)->update(['status'=>'empty','gas_weight_kg'=>0,'filled_purchase_id'=>null]);
   }

   (new CostService())->voidPurchaseLayers($purchaseId);
   if(!$this->db->transStatus()) throw new RuntimeException('Purchase void failed.');
   \App\Services\TimelineGuard::check($tgMark, 'this purchase void');
   $this->db->transCommit();

   AuditService::log('VOID','purchase',$purchaseId,
    ['purchase_no'=>$purchase['purchase_no'],'status'=>'posted'],
    ['purchase_no'=>$purchase['purchase_no'],'status'=>'voided','void_reason'=>$reason],
    $userId,$locationId
   );

   return $purchase['purchase_no'];
  }catch(\Throwable $e){
   $this->db->transRollback();
   throw $e;
  }
 }

}
