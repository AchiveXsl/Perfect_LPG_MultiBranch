<?php
namespace App\Controllers;
use App\Services\CashService;
use App\Services\AuditService;
use App\Services\PaymentModes;
use CodeIgniter\Controller;
use Config\Database;
class Receipts extends Controller{
 private function guard(){return \App\Services\PermissionService::allows('POS_SALE')?null:$this->response->setStatusCode(403)->setBody('Forbidden');}
 public function index(){if($r=$this->guard())return $r;$db=Database::connect();$locationId=(int)session()->get('location_id');$customersQ=$db->table('customers')->where('is_active',1);\App\Models\CustomerModel::applyVisibleAt($customersQ,$locationId);$customers=$customersQ->orderBy('name')->get()->getResultArray();$creditRows=$db->table('sales')->select('customer_id,SUM(credit_amount) AS credit,SUM(return_gas_ledger_amount) AS return_ledger')->where('status','posted')->where('location_id',$locationId)->where('customer_id IS NOT NULL',null,false)->groupBy('customer_id')->get()->getResultArray();$paidRows=$db->table('customer_receipts')->select('customer_id,SUM(amount) AS paid')->where('status','posted')->where('location_id',$locationId)->where('customer_id IS NOT NULL',null,false)->groupBy('customer_id')->get()->getResultArray();$creditByCustomer=[];$returnLedgerByCustomer=[];$paidByCustomer=[];foreach($creditRows as $row){$creditByCustomer[(int)$row['customer_id']]=(float)$row['credit'];$returnLedgerByCustomer[(int)$row['customer_id']]=(float)$row['return_ledger'];}foreach($paidRows as $row){$paidByCustomer[(int)$row['customer_id']]=(float)$row['paid'];}$customerBalances=[];foreach($customers as $customer){$id=(int)$customer['id'];$customerBalances[$id]=\App\Models\CustomerModel::openingAt($customer,$locationId)+($creditByCustomer[$id]??0)+($returnLedgerByCustomer[$id]??0)-($paidByCustomer[$id]??0);}return view('receipts/index',['title'=>'Customer Receipts','cashOpenedAt'=>(new CashService())->openSessionForLocation($locationId)['opened_at']??null,'customers'=>$customers,'customerBalances'=>$customerBalances,'receipts'=>$db->query("SELECT * FROM (
 SELECT cr.id AS receipt_id,NULL AS sale_id,cr.receipt_at,cr.receipt_no,c.name AS customer_name,cr.amount,cr.payment_mode,cr.reference_no,CASE WHEN cr.backdate_reason IS NULL THEN cr.notes ELSE CONCAT_WS(' | ',cr.notes,CONCAT('Back-dated: ',cr.backdate_reason)) END AS notes,cr.status,
        CASE WHEN cr.notes LIKE 'OS settlement collected with sale %' THEN 'POS — OS Settlement' ELSE 'Customer Receipts screen' END AS source
 FROM customer_receipts cr
 LEFT JOIN customers c ON c.id=cr.customer_id
 WHERE cr.location_id=?
 UNION ALL
 SELECT NULL AS receipt_id,s.id AS sale_id,sp.payment_at AS receipt_at,
        CONCAT(s.sale_no,CASE sp.payment_type WHEN 'security_deposit' THEN ' / Deposit' WHEN 'security_deposit_refund' THEN ' / Deposit Refund' ELSE ' / POS' END) AS receipt_no,
        c.name AS customer_name,sp.amount,sp.payment_mode,sp.reference_no,
        CASE sp.payment_type WHEN 'security_deposit' THEN CONCAT('POS security deposit payment for ',s.sale_no) WHEN 'security_deposit_refund' THEN CONCAT('POS security deposit refund for ',s.sale_no) ELSE CONCAT('POS sale payment for ',s.sale_no) END AS notes,
        s.status,
        CASE sp.payment_type WHEN 'security_deposit' THEN 'POS — Security Deposit' WHEN 'security_deposit_refund' THEN 'POS — Security Deposit Refund' ELSE CONCAT('POS — ',REPLACE(s.transaction_type,'_',' '),' / Sale Payment') END AS source
 FROM sale_payments sp
 JOIN sales s ON s.id=sp.sale_id
 LEFT JOIN customers c ON c.id=s.customer_id
 WHERE s.location_id=? AND sp.payment_mode <> 'credit'
) AS receipt_history
ORDER BY receipt_at DESC,receipt_no DESC
LIMIT 100",[$locationId,$locationId])->getResultArray()]);}
 /** Printable receipt of one customer receipt posted at the current branch. */
 public function receipt(int $id){
  if($r=$this->guard())return $r;
  $db=Database::connect();$locationId=(int)session()->get('location_id');
  $receipt=$db->table('customer_receipts cr')->select('cr.*,c.name AS customer_name,c.phone AS customer_phone,u.full_name AS created_by_name')
   ->join('customers c','c.id=cr.customer_id')->join('users u','u.id=cr.created_by','left')
   ->where('cr.id',$id)->where('cr.location_id',$locationId)->get()->getRowArray();
  if(!$receipt)return $this->response->setStatusCode(404)->setBody('Receipt not found');
  $customer=$db->table('customers')->where('id',(int)$receipt['customer_id'])->get()->getRowArray();
  $sales=$db->table('sales')->selectSum('credit_amount','credit')->selectSum('return_gas_ledger_amount','return_ledger')->where(['customer_id'=>$receipt['customer_id'],'status'=>'posted','location_id'=>$locationId])->get()->getRowArray();
  $paid=$db->table('customer_receipts')->selectSum('amount','paid')->where(['customer_id'=>$receipt['customer_id'],'status'=>'posted','location_id'=>$locationId])->get()->getRowArray();
  $osBalance=round(\App\Models\CustomerModel::openingAt($customer,$locationId)+(float)($sales['credit']??0)+(float)($sales['return_ledger']??0)-(float)($paid['paid']??0),2);
  $location=$db->table('locations')->where('id',$locationId)->get()->getRowArray()?:[];
  return view('receipts/receipt',compact('receipt','location','osBalance'));
 }
 /** The chosen receive date with the current time of day. Blank means today. Future dates are rejected. */
 private function receiveDateTime(string $input): string{
  $input=trim($input);if($input==='')return \App\Services\BranchContext::now();
  $d=\DateTime::createFromFormat('!Y-m-d',$input);
  if(!$d||$d->format('Y-m-d')!==$input)throw new \RuntimeException('Invalid receive date.');
  if($input>\App\Services\BranchContext::today())throw new \RuntimeException('Receive date cannot be in the future.');
  return $input.' '.date('H:i:s');
 }
 /** A receipt dated before today needs a reason. Today needs none. */
 private function backdateReason(string $receiptAt,string $input): ?string{
  if(substr($receiptAt,0,10)>=\App\Services\BranchContext::today())return null;
  $reason=trim($input);
  if($reason==='')throw new \RuntimeException('Enter the reason for the back-dated receipt.');
  if(mb_strlen($reason)>255)throw new \RuntimeException('Back-date reason cannot exceed 255 characters.');
  return $reason;
 }
 /** Cash joins the open counter session, so it cannot be dated before that session opened. */
 private function fitToCashSession(string $receiptAt,int $locationId): string{
  $session=(new CashService())->openSessionForLocation($locationId);
  if(!$session)throw new \RuntimeException('Open the counter cash session before a cash receipt.');
  $openedAt=(string)$session['opened_at'];
  if(substr($receiptAt,0,10)<substr($openedAt,0,10))throw new \RuntimeException('A cash receipt cannot be dated before the open counter session, which opened on '.date('d M Y',strtotime($openedAt)).'.');
  return max($receiptAt,$openedAt); // on the opening day, never earlier than the opening time
 }
 public function save(){if($r=$this->guard())return $r;try{$amount=(float)$this->request->getPost('amount');$mode=(string)$this->request->getPost('payment_mode');$customer=(int)$this->request->getPost('customer_id');if($amount<=0||!$customer||!in_array($mode,PaymentModes::values(),true))throw new \RuntimeException('Invalid receipt.');$receiptAt=$this->receiveDateTime((string)$this->request->getPost('receive_date'));$backdateReason=$this->backdateReason($receiptAt,(string)$this->request->getPost('backdate_reason'));$db=Database::connect();$db->transBegin();$customerRow=$db->query('SELECT * FROM customers WHERE id=? AND is_active=1 FOR UPDATE',[$customer])->getRowArray();if(!$customerRow)throw new \RuntimeException('Customer is invalid or inactive.');$locationId=(int)session()->get('location_id');\App\Models\CustomerModel::assertUsableAt($customerRow,$locationId);if($mode==='cash')$receiptAt=$this->fitToCashSession($receiptAt,$locationId);$creditRow=$db->table('sales')->selectSum('credit_amount','credit')->selectSum('return_gas_ledger_amount','return_ledger')->where(['customer_id'=>$customer,'status'=>'posted','location_id'=>$locationId])->get()->getRowArray();$paidRow=$db->table('customer_receipts')->selectSum('amount','paid')->where(['customer_id'=>$customer,'status'=>'posted','location_id'=>$locationId])->get()->getRowArray();$outstanding=\App\Models\CustomerModel::openingAt($customerRow,$locationId)+(float)($creditRow['credit']??0)+(float)($creditRow['return_ledger']??0)-(float)($paidRow['paid']??0);$balanceAfter=$outstanding-$amount;$no='R'.date('YmdHis').'-'.random_int(100,999);$db->table('customer_receipts')->insert(['receipt_no'=>$no,'location_id'=>(int)session()->get('location_id'),'customer_id'=>$customer,'status'=>'posted','amount'=>$amount,'payment_mode'=>$mode,'receipt_at'=>$receiptAt,'backdate_reason'=>$backdateReason,'reference_no'=>trim((string)$this->request->getPost('reference_no'))?:null,'notes'=>trim((string)$this->request->getPost('notes'))?:null,'created_by'=>(int)session()->get('user_id')]);$id=(int)$db->insertID();if($mode==='cash'){$s=(new CashService())->openSessionForLocation((int)session()->get('location_id'));if(!$s)throw new \RuntimeException('Open the counter cash session before a cash receipt.');(new CashService())->postGeneric((int)$s['id'],'customer_receipt','in',$amount,'customer_receipt',$id,(int)session()->get('user_id'),'Customer cash receipt',$receiptAt);}if(!$db->transStatus())throw new \RuntimeException('Receipt posting failed.');$db->transCommit();AuditService::log('CREATE','customer_receipt',$id,null,['receipt_no'=>$no,'amount'=>$amount,'receipt_at'=>$receiptAt,'backdate_reason'=>$backdateReason],(int)session()->get('user_id'),(int)session()->get('location_id'));return redirect()->to('/receipts')->with('receipt_url',site_url('receipts/receipt/'.$id))->with('success','Receipt '.$no.' posted. Current OS: Rs. '.number_format($outstanding,2).'; Received: Rs. '.number_format($amount,2).'; Balance after receipt: Rs. '.number_format($balanceAfter,2).'.');}catch(\Throwable $e){if(isset($db)&&$db->transStatus()!==false)$db->transRollback();return redirect()->back()->withInput()->with('error',$e->getMessage());}}
}