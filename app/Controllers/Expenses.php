<?php
namespace App\Controllers;
use App\Services\CashService;
use App\Services\AuditService;
use CodeIgniter\Controller;
use Config\Database;
class Expenses extends Controller{
 private function guard(){return \App\Services\PermissionService::allows('EXPENSE_MANAGE')?null:$this->response->setStatusCode(403)->setBody('Forbidden');}
 public function index(){
  if($r=$this->guard())return $r;
  $db=Database::connect();
  $loc=(int)session()->get('location_id');
  // History date filter: default is the current month.
  $valid=static fn($v)=>is_string($v)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)&&strtotime($v)!==false;
  $from=$valid($this->request->getGet('from'))?$this->request->getGet('from'):substr(\App\Services\BranchContext::today(),0,8).'01';
  $to=$valid($this->request->getGet('to'))?$this->request->getGet('to'):\App\Services\BranchContext::today();
  if($from>$to)[$from,$to]=[$to,$from];
  $expenses=$db->table('expenses e')->select('e.*,c.name category_name,u.full_name created_by_name')
   ->join('expense_categories c','c.id=e.category_id')->join('users u','u.id=e.created_by','left')
   ->where('e.location_id',$loc)->where('e.expense_at >=',$from.' 00:00:00')->where('e.expense_at <=',$to.' 23:59:59')
   ->orderBy('e.expense_at','DESC')->orderBy('e.id','DESC')->limit(5000)->get()->getResultArray();
  $branch=$db->table('locations')->select('name')->where('id',$loc)->get()->getRowArray();
  return view('expenses/index',['title'=>'Expenses','categories'=>$db->table('expense_categories')->where('is_active',1)->orderBy('name')->get()->getResultArray(),'expenses'=>$expenses,'from'=>$from,'to'=>$to,'branchName'=>$branch['name']??'','cash'=>(new CashService())->openSessionForLocation($loc)]);
 }
 public function save(){if($r=$this->guard())return $r;try{$amount=(float)$this->request->getPost('amount');$mode=(string)$this->request->getPost('payment_mode');if($amount<=0||!in_array($mode,['cash','cheque','online'],true))throw new \RuntimeException('Invalid expense.');$db=Database::connect();$cash=0;if($mode==='cash')$cash=$amount;$db->transBegin();$no='E'.date('YmdHis').'-'.random_int(100,999);$db->table('expenses')->insert(['expense_no'=>$no,'location_id'=>(int)session()->get('location_id'),'category_id'=>(int)$this->request->getPost('category_id'),'amount'=>$amount,'payment_mode'=>$mode,'expense_at'=>\App\Services\BranchContext::now(),'reference_no'=>trim((string)$this->request->getPost('reference_no'))?:null,'description'=>trim((string)$this->request->getPost('description'))?:null,'created_by'=>(int)session()->get('user_id')]);$id=(int)$db->insertID();if($cash){$s=(new CashService())->openSessionForLocation((int)session()->get('location_id'));if(!$s)throw new \RuntimeException('Open the counter cash session before a cash expense.');(new CashService())->postGeneric((int)$s['id'],'expense','out',$cash,'expense',$id,(int)session()->get('user_id'),'Expense cash');}if(!$db->transStatus())throw new \RuntimeException('Expense posting failed.');$db->transCommit();AuditService::log('CREATE','expense',$id,null,['expense_no'=>$no,'amount'=>$amount],(int)session()->get('user_id'),(int)session()->get('location_id'));return redirect()->to('/expenses')->with('success','Expense '.$no.' posted.');}catch(\Throwable $e){if(isset($db)&&$db->transStatus()!==false)$db->transRollback();return redirect()->back()->withInput()->with('error',$e->getMessage());}}
}