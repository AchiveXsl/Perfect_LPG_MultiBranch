<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Services\BranchContext;
use App\Services\PermissionService;
use App\Services\ExcelInventoryService;
use App\Services\AuditService;
use CodeIgniter\Controller;

class Customers extends Controller
{
    protected CustomerModel $model;
    public function __construct(){ $this->model=new CustomerModel(); }

    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if(!PermissionService::allows('CUSTOMER_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden');
        return null;
    }

    public function downloadTemplate()
    {
        if($r=$this->guard()) return $r;
        $path=WRITEPATH.'uploads/customer_template.xlsx';
        try { (new ExcelInventoryService())->createCustomerTemplate($path); return $this->response->download($path,null)->setFileName('customer_template.xlsx'); }
        catch(\Throwable $e){ return $this->response->setStatusCode(500)->setBody('Unable to generate customer template: '.esc($e->getMessage())); }
    }

    public function importExcel()
    {
        if($r=$this->guard()) return $r;
        $file=$this->request->getFile('customer_excel');
        if(!$file || !$file->isValid()) return redirect()->back()->with('error','Please select a valid .xlsx customer file.');
        if(strtolower($file->getExtension())!=='xlsx') return redirect()->back()->with('error','Only .xlsx Excel files are supported.');
        $path=$file->getTempName(); $db=$this->model->db;
        try {
            $rows=(new ExcelInventoryService())->readCustomers($path); $db->transBegin(); $created=0; $seenCodes=[]; $userId=(int)(session()->get('user_id')??0);
            foreach($rows as $row){
                $code=$row['code']; $name=$row['name'];
                if($name==='') throw new \RuntimeException('Row '.$row['excel_row'].': Customer Name is required.');
                if($code!=='' && !preg_match('/^[A-Za-z0-9_-]{1,30}$/',$code)) throw new \RuntimeException('Row '.$row['excel_row'].': Code may contain only letters, numbers, underscore and dash, maximum 30 characters.');
                if($code!=='') { $key=strtoupper($code); if(isset($seenCodes[$key])) throw new \RuntimeException('Row '.$row['excel_row'].': duplicate customer code '.$code.' in the uploaded file.'); $seenCodes[$key]=true; if($this->model->where('code',$code)->first()) throw new \RuntimeException('Row '.$row['excel_row'].': customer code '.$code.' already exists. Existing customers are not overwritten.'); }
                foreach([['credit_limit','Credit Limit'],['opening_balance','Opening Balance']] as [$field,$label]){ if($row[$field]===''||!is_numeric($row[$field])||(float)$row[$field]<0) throw new \RuntimeException('Row '.$row['excel_row'].': '.$label.' must be a number greater than or equal to 0.'); }
                foreach([['allow_credit_sale','Allow Credit Sale'],['is_active','Active']] as [$field,$label]) if(!in_array($row[$field],['0','1'],true)) throw new \RuntimeException('Row '.$row['excel_row'].': '.$label.' must be 0 or 1.');
                $data=['code'=>$code?:null,'name'=>$name,'phone'=>$row['phone']?:null,'city'=>$row['city']?:null,'address'=>$row['address']?:null,'vehicle_no'=>$row['vehicle_no']?:null,'credit_limit'=>(float)$row['credit_limit'],'allow_credit_sale'=>(int)$row['allow_credit_sale'],'opening_balance'=>(float)$row['opening_balance'],'is_active'=>(int)$row['is_active'],'branch_scope'=>CustomerModel::branchOnly()?'branch':'shared','credit_scope'=>'branch','home_location_id'=>BranchContext::currentId()];
                $id=$this->model->insert($data,true); if(!$id) throw new \RuntimeException('Row '.$row['excel_row'].': customer could not be created.');
                AuditService::log('excel_import','customer',(int)$id,null,$data,$userId,(int)(session()->get('location_id')??0)); $created++;
            }
            if(!$db->transStatus()) throw new \RuntimeException('Customer Excel import failed.'); $db->transCommit();
            return redirect()->to('/customers')->with('success',$created.' customer(s) imported successfully.');
        } catch(\Throwable $e) { if($db->transStatus()!==false) $db->transRollback(); return redirect()->back()->with('error','Customer Excel import failed: '.$e->getMessage()); }
    }

    public function index()
    {
        if($r=$this->guard()) return $r;
        $editId=(int)$this->request->getGet('edit');
        $edit=$editId?$this->model->find($editId):null;
        if($edit && !CustomerModel::isUsableAt($edit,BranchContext::currentId())) $edit=null;
        return view('customers/index',[
            'title'=>'Customers / Parties',
            'customers'=>$this->model->visibleAt()->orderBy('name')->findAll(),
            'edit'=>$edit,
            'branches'=>BranchContext::allActive(),
            'currentBranchId'=>BranchContext::currentId(),
        ]);
    }

    public function ledger(int $id)
    {
        if($r=$this->guard()) return $r;
        $locationId=(int)session()->get('location_id');
        $customer=$this->model->withLedgerTotals($id,$locationId);
        if(!$customer || !CustomerModel::isUsableAt($customer,$locationId)) return $this->response->setStatusCode(404)->setBody('Customer not found');
        $db=$this->model->db;
        $sales=$db->table('sales')->select('transaction_at,sale_no,transaction_type,total_amount,credit_amount,return_gas_ledger_amount,security_deposit_amount,security_deposit_refund_amount,status')->where('customer_id',$id)->where('location_id',$locationId)->orderBy('transaction_at','DESC')->get()->getResultArray();
        $receipts=$db->query(
            "SELECT receipt_at,receipt_no,amount,payment_mode,status,receipt_type,source,reference_no,details
             FROM (
                 SELECT cr.receipt_at,cr.receipt_no,cr.amount,cr.payment_mode,cr.status,
                        CASE WHEN cr.notes LIKE 'OS settlement collected with sale %' THEN 'POS OS Settlement' ELSE 'Customer Receipt' END AS receipt_type,
                        CASE WHEN cr.notes LIKE 'OS settlement collected with sale %' THEN 'POS — OS Settlement' ELSE 'Customer Receipts screen' END AS source,
                        cr.reference_no,cr.notes AS details
                 FROM customer_receipts cr
                 WHERE cr.customer_id=? AND cr.location_id=?
                 UNION ALL
                 SELECT sp.payment_at AS receipt_at,
                        CONCAT(s.sale_no, CASE sp.payment_type
                            WHEN 'security_deposit' THEN ' / Deposit'
                            WHEN 'security_deposit_refund' THEN ' / Deposit Refund'
                            ELSE ' / POS'
                        END) AS receipt_no,
                        sp.amount,sp.payment_mode,s.status,
                        CASE sp.payment_type
                            WHEN 'security_deposit' THEN 'POS Security Deposit'
                            WHEN 'security_deposit_refund' THEN 'POS Security Deposit Refund'
                            ELSE 'POS Sale Receipt'
                        END AS receipt_type,
                        CASE sp.payment_type
                            WHEN 'security_deposit' THEN 'POS — Security Deposit'
                            WHEN 'security_deposit_refund' THEN 'POS — Security Deposit Refund'
                            ELSE CONCAT('POS — ',REPLACE(s.transaction_type,'_',' '),' / Sale Payment')
                        END AS source,
                        sp.reference_no,
                        CASE sp.payment_type
                            WHEN 'security_deposit' THEN CONCAT('POS security deposit payment for ',s.sale_no)
                            WHEN 'security_deposit_refund' THEN CONCAT('POS security deposit refund for ',s.sale_no)
                            ELSE CONCAT('POS sale payment for ',s.sale_no)
                        END AS details
                 FROM sale_payments sp
                 JOIN sales s ON s.id=sp.sale_id
                 WHERE s.customer_id=? AND s.location_id=? AND sp.payment_mode <> 'credit'
             ) AS all_receipts
             ORDER BY receipt_at DESC,receipt_no DESC",
            [$id,$locationId,$id,$locationId]
        )->getResultArray();
        $deposits=$db->query(
            "SELECT transaction_at,entry_type,amount,sale_id,custody_id,notes
             FROM customer_security_deposits
             WHERE customer_id=? AND location_id=?
             UNION ALL
             SELECT s.transaction_at,
                    CASE sp.payment_type WHEN 'security_deposit' THEN 'hold' ELSE 'refund' END AS entry_type,
                    SUM(sp.amount) AS amount,
                    s.id AS sale_id,
                    NULL AS custody_id,
                    CASE sp.payment_type
                        WHEN 'security_deposit' THEN CONCAT('Recovered from POS deposit payment history — ',s.sale_no)
                        ELSE CONCAT('Recovered from POS deposit refund payment history — ',s.sale_no)
                    END AS notes
             FROM sale_payments sp
             JOIN sales s ON s.id=sp.sale_id
             WHERE s.customer_id=? AND s.location_id=? AND s.status='posted'
               AND sp.payment_type IN ('security_deposit','security_deposit_refund')
               AND NOT EXISTS (
                   SELECT 1 FROM customer_security_deposits csd
                   WHERE csd.sale_id=s.id
                     AND csd.entry_type=CASE sp.payment_type WHEN 'security_deposit' THEN 'hold' ELSE 'refund' END
               )
             GROUP BY s.id,s.sale_no,s.transaction_at,sp.payment_type",
            [$id,$locationId,$id,$locationId]
        )->getResultArray();
        usort($deposits,static fn($a,$b)=>strcmp((string)$b['transaction_at'],(string)$a['transaction_at']));
        $depositHeld=0.0;
        foreach($deposits as $depositRow){
            $depositHeld+=(($depositRow['entry_type']??'')==='hold'?1:-1)*(float)($depositRow['amount']??0);
        }
        $customer['security_deposit_held']=max(0,$depositHeld);
        $custody=$db->table('cylinder_custody cc')->select('cc.*,cu.unit_code,cu.gas_weight_kg,ct.code cylinder_code,ct.name cylinder_name')->join('cylinder_units cu','cu.id=cc.cylinder_unit_id')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')->where(['cc.customer_id'=>$id,'cc.status'=>'issued'])->orderBy('cc.id')->get()->getResultArray();
        // Details tab: one running ledger of sales and customer receipts, oldest first.
        $typeNames=['gas_sale'=>'Gas Sale','cylinder_sale'=>'Cylinder Sale','security_deposit'=>'Security Deposit','cylinder_return'=>'Cylinder Return'];
        $events=[];
        $saleDetail=$db->query(
            "SELECT s.id,s.sale_no,s.transaction_at,s.transaction_type,s.total_amount,s.credit_amount,s.return_gas_ledger_amount,s.security_deposit_amount,s.security_deposit_refund_amount,s.status,
                    (SELECT GROUP_CONCAT(DISTINCT sp.payment_mode ORDER BY sp.payment_mode SEPARATOR ',') FROM sale_payments sp WHERE sp.sale_id=s.id AND sp.payment_type='sale' AND sp.payment_mode<>'credit' AND sp.amount>0) modes
             FROM sales s WHERE s.customer_id=? AND s.location_id=?",[$id,$locationId]
        )->getResultArray();
        // Cylinder movement per sale, for the Timeline tab.
        $fmtCount=static function(array $c):string{$o=[];foreach($c as $n=>$q)$o[]=$q.' × '.$n;return implode(', ',$o);};
        $itemsBySale=[];$issuedBySale=[];$returnedBySale=[];$cylRun=0;
        $saleIds=array_map('intval',array_column($saleDetail,'id'));
        if($saleIds){
            foreach($db->table('sale_items si')->select('si.sale_id,si.line_type,si.quantity,si.gas_weight_kg,ct.name tname')->join('cylinder_types ct','ct.id=si.cylinder_type_id','left')->whereIn('si.sale_id',$saleIds)->get()->getResultArray() as $i) $itemsBySale[(int)$i['sale_id']][]=$i;
        }
        foreach($db->table('cylinder_custody cc')->select('cc.issue_sale_id,cc.return_sale_id,ct.name tname')->join('cylinder_units cu','cu.id=cc.cylinder_unit_id')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')->where(['cc.customer_id'=>$id,'cc.location_id'=>$locationId])->where('cc.status !=','voided')->get()->getResultArray() as $cc){
            if($cc['issue_sale_id']===null){$cylRun++;}else{$k=(int)$cc['issue_sale_id'];$issuedBySale[$k][$cc['tname']]=($issuedBySale[$k][$cc['tname']]??0)+1;}
            if($cc['return_sale_id']!==null){$k=(int)$cc['return_sale_id'];$returnedBySale[$k][$cc['tname']]=($returnedBySale[$k][$cc['tname']]??0)+1;}
        }
        foreach($saleDetail as $s){
            $total=(float)$s['total_amount'];$credit=(float)$s['credit_amount'];$added=$credit+(float)($s['return_gas_ledger_amount']??0);
            $paid=max(0,$total-$credit);
            $label=$typeNames[$s['transaction_type']]??ucwords(str_replace('_',' ',(string)$s['transaction_type']));
            $modes=implode(' + ',array_map('ucfirst',array_filter(explode(',',(string)$s['modes']))));
            $events[]=['at'=>$s['transaction_at'],'ref'=>$s['sale_no'],'type'=>$label.($modes!==''?' · '.$modes:'').($credit>0.005?($modes!==''?' + Due':' · Due'):''),'amount'=>$total,'paid'=>$paid,'added'=>$added,'made'=>null,'status'=>$s['status'],'kind'=>'sale','id'=>(int)$s['id'],'voidable'=>false,
                'txn'=>$s['transaction_type'],'dep'=>(float)$s['security_deposit_amount'],'refund'=>(float)$s['security_deposit_refund_amount'],
                'issued_n'=>array_sum($issuedBySale[(int)$s['id']]??[]),'issued_txt'=>$fmtCount($issuedBySale[(int)$s['id']]??[]),
                'returned_n'=>array_sum($returnedBySale[(int)$s['id']]??[]),'returned_txt'=>$fmtCount($returnedBySale[(int)$s['id']]??[]),
                'sold_n'=>(function() use($itemsBySale,$s){$n=0;foreach($itemsBySale[(int)$s['id']]??[] as $i){if($s['transaction_type']==='cylinder_sale'&&in_array($i['line_type'],['filled_cylinder','empty_cylinder'],true))$n+=(float)$i['quantity'];}return $n;})(),
                'kg'=>(function() use($itemsBySale,$s){$n=0;foreach($itemsBySale[(int)$s['id']]??[] as $i){$n+=(float)$i['gas_weight_kg'];}return $n;})()];
        }
        $receiptDetail=$db->table('customer_receipts')->where(['customer_id'=>$id,'location_id'=>$locationId])->get()->getResultArray();
        // Where a cash receipt's void can put its cash reversal: its own session or the one open now.
        $receiptSessions=(new \App\Services\SalesService())->receiptCashSessions(array_column($receiptDetail,'id'));
        $openSession=(new \App\Services\CashService())->openSessionForLocation($locationId,false);
        $sessionLabel=static fn(array $s)=>'Session #'.(int)$s['id'].' '.($s['register_code']??'').' opened '.date('d-M-Y H:i',strtotime((string)$s['opened_at']));
        foreach($receiptDetail as $r){
            $insideSale=str_starts_with((string)($r['notes']??''),'OS settlement collected with sale ');
            $cashChoice=null;
            if((string)$r['payment_mode']==='cash' && isset($receiptSessions[(int)$r['id']])){
                $orig=$receiptSessions[(int)$r['id']];
                $cashChoice=[
                    'same'=>$openSession && (int)$openSession['id']===(int)$orig['id'],
                    'orig'=>$sessionLabel($orig),
                    'origClosed'=>$orig['status']==='closed' ? date('d-M-Y H:i',strtotime((string)$orig['closed_at'])) : '',
                    'cur'=>$openSession ? $sessionLabel($openSession) : '',
                    'amount'=>number_format((float)$r['amount'],2),
                ];
            }
            $events[]=['at'=>$r['receipt_at'],'ref'=>$r['receipt_no'],'type'=>ucfirst((string)$r['payment_mode']).(!empty($r['reference_no'])?' '.$r['reference_no']:''),'amount'=>null,'paid'=>null,'added'=>null,'made'=>(float)$r['amount'],'status'=>$r['status'],'kind'=>'receipt','id'=>(int)$r['id'],'voidable'=>!$insideSale,'cash_choice'=>$cashChoice];
        }
        usort($events,static fn($x,$y)=>[$x['at'],$x['kind']==='sale'?0:1,$x['id']]<=>[$y['at'],$y['kind']==='sale'?0:1,$y['id']]);
        $running=CustomerModel::openingAt($customer,$locationId);$seq=0;
        foreach($events as &$e){
            $e['seq']=++$seq;$e['cyl_before']=$cylRun;
            if($e['status']==='posted'){$running+=(float)($e['added']??0)-(float)($e['made']??0);$cylRun+=(int)($e['issued_n']??0)-(int)($e['returned_n']??0);}
            $e['balance']=$running;$e['cyl_after']=$cylRun;
        }
        unset($e);
        $canVoid=PermissionService::allows('POS_VOID');
        return view('customers/ledger',['title'=>'Customer Ledger — '.$customer['name'],'customer'=>$customer,'sales'=>$sales,'receipts'=>$receipts,'deposits'=>$deposits,'custody'=>$custody,'details'=>$events,'canVoid'=>$canVoid]);
    }

    public function voidReceipt(int $receiptId)
    {
        if($r=$this->guard()) return $r;
        try{
            if(!PermissionService::allows('POS_VOID')) throw new \RuntimeException('You are not authorized to void receipts.');
            $reason=trim((string)$this->request->getPost('void_reason'));
            $cashTarget=(string)$this->request->getPost('cash_target');
            if(!in_array($cashTarget,['original','current'],true)) $cashTarget='';
            $customerId=(new \App\Services\SalesService())->voidCustomerReceipt($receiptId,(int)session()->get('user_id'),(int)session()->get('location_id'),$reason,$cashTarget);
            return redirect()->to('/customers/ledger/'.$customerId.'#customerDetails')->with('success','Customer receipt voided. The customer balance and any cash were reversed.');
        }catch(\Throwable $e){
            $back=(string)$this->request->getPost('return_to');
            return redirect()->to(preg_match('#^/customers/ledger/\d+$#',$back)?$back.'#customerDetails':'/customers')->with('error',$e->getMessage());
        }
    }

    public function save()
    {
        if($r=$this->guard()) return $r;
        $id=(int)$this->request->getPost('id');
        $data=[
            'code'=>trim((string)$this->request->getPost('code')) ?: null,
            'name'=>trim((string)$this->request->getPost('name')),
            'customer_type'=>in_array($this->request->getPost('customer_type'),['retail','wholesale'],true) ? (string)$this->request->getPost('customer_type') : 'shop_default',
            'phone'=>trim((string)$this->request->getPost('phone')) ?: null,
            'city'=>trim((string)$this->request->getPost('city')) ?: null,
            'address'=>trim((string)$this->request->getPost('address')) ?: null,
            'vehicle_no'=>trim((string)$this->request->getPost('vehicle_no')) ?: null,
            'credit_limit'=>(float)$this->request->getPost('credit_limit'),
            'allow_credit_sale'=>$this->request->getPost('allow_credit_sale') ? 1 : 0,
            'opening_balance'=>(float)$this->request->getPost('opening_balance'),
            'is_active'=>$this->request->getPost('is_active') ? 1 : 0,
            'branch_scope'=>$this->request->getPost('branch_scope')==='branch' ? 'branch' : 'shared',
            'credit_scope'=>$this->request->getPost('credit_scope')==='combined' ? 'combined' : 'branch',
            'home_location_id'=>(int)$this->request->getPost('home_location_id') ?: BranchContext::currentId(),
        ];
        if(CustomerModel::branchOnly()){
            // Split purchasing: the customer belongs to one branch, its home branch (current branch for a new one).
            $existing=$id ? $this->model->find($id) : null;
            $data['branch_scope']='branch'; $data['credit_scope']='branch';
            $data['home_location_id']=$existing ? CustomerModel::homeOf($existing) : BranchContext::currentId();
        }
        if($data['name']==='') return redirect()->back()->withInput()->with('error','Customer name is required.');
        if($data['credit_limit']<0||$data['opening_balance']<0) return redirect()->back()->withInput()->with('error','Credit limit and opening balance cannot be negative.');
        if(!in_array($data['home_location_id'],array_map('intval',array_column(BranchContext::allActive(),'id')),true)) return redirect()->back()->withInput()->with('error','Select an active home branch.');
        if($id){
            $old=$this->model->find($id);
            if(!$old || !CustomerModel::isUsableAt($old,BranchContext::currentId())) return redirect()->to('/customers')->with('error','Customer not found at this branch.');
        }
        if($data['branch_scope']==='branch' && $data['home_location_id']!==BranchContext::currentId() && !PermissionService::allows('BRANCH_MANAGE')){
            return redirect()->back()->withInput()->with('error','Only a user who manages branches can make a customer belong to another branch.');
        }
        try { if($id) $this->model->update($id,$data); else $this->model->insert($data); } catch (\Throwable $e) { return redirect()->back()->withInput()->with('error','Customer could not be saved. Check the code for duplicates.'); }
        return redirect()->to('/customers')->with('success',$id?'Customer updated.':'Customer created.');
    }
}