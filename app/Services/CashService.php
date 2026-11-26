<?php
namespace App\Services;
use Config\Database;
use RuntimeException;
use App\Exceptions\SystemDateConflict;
class CashService {
 protected $db;
 public function __construct(){ $this->db=Database::connect(); }
 public function openSession(int $userId,int $locationId,float $openingCash,string $notes=''): int {
  if($openingCash<0) throw new RuntimeException('Opening cash cannot be negative.');
  $this->db->transBegin();
  try {
   $register=$this->db->query("SELECT * FROM cash_registers WHERE location_id=? AND is_active=1 ORDER BY id LIMIT 1 FOR UPDATE",[$locationId])->getRowArray();
   if(!$register) throw new RuntimeException('No active cash register exists for this location.');
   $existing=$this->db->table('cash_sessions')->where('register_id',$register['id'])->where('status','open')->get()->getRowArray();
   if($existing) throw self::otherDateConflict($existing,BranchContext::today($locationId),'open a new counter session');
   // One counter session per System Date: the session carries the branch's working date.
   $openedAt=BranchContext::now($locationId);
   $this->db->table('cash_sessions')->insert(['register_id'=>$register['id'],'opened_by'=>$userId,'opened_at'=>$openedAt,'opening_cash'=>$openingCash,'notes'=>trim($notes)?:null]);
   $id=(int)$this->db->insertID();
   if($openingCash>0) $this->db->table('cash_transactions')->insert(['cash_session_id'=>$id,'transaction_type'=>'opening_float','direction'=>'in','amount'=>$openingCash,'transaction_at'=>$openedAt,'created_by'=>$userId,'notes'=>'Opening float']);
   if(!$this->db->transStatus()) throw new RuntimeException('Cash session could not be opened.');
   $this->db->transCommit(); return $id;
  } catch(\Throwable $e){$this->db->transRollback();throw $e;}
 }
 /**
  * The open counter session of a branch. Each session belongs to one date (owner decision 2026-11-26).
  * When posting ($forPosting, default: on POST requests) a session opened on another date than the
  * System Date is refused with an explanation of what to do.
  */
 public function openSessionForLocation(int $locationId, ?bool $forPosting=null): ?array {
  $session=$this->findOpenSession($locationId);
  if($forPosting===null) $forPosting=!is_cli() && strtoupper((string)service('request')->getMethod())==='POST';
  if($session && $forPosting && substr((string)$session['opened_at'],0,10)!==BranchContext::today($locationId) && !self::overnightShift($session,$locationId)) {
   throw self::otherDateConflict($session,BranchContext::today($locationId),'post cash');
  }
  return $session;
 }
 /**
  * A branch that follows today's date (no System Date set) may keep yesterday's session open past
  * midnight, as a normal overnight shift. The one-session-per-date rule protects back-dated and
  * future work, where the branch has a System Date set.
  */
 public static function overnightShift(array $session, int $locationId): bool {
  return BranchContext::storedSystemDate($locationId)===null
   && substr((string)$session['opened_at'],0,10)===date('Y-m-d',strtotime(BranchContext::today($locationId).' -1 day'));
 }
 /** True when the open session belongs to a date other than the System Date. */
 public static function sessionDateMismatch(?array $session, int $locationId): bool {
  return $session && substr((string)$session['opened_at'],0,10)!==BranchContext::today($locationId);
 }
 protected static function otherDateConflict(array $session,string $systemDate,string $doing): SystemDateConflict {
  $sessionDate=substr((string)$session['opened_at'],0,10);
  $sd=date('d-M-Y',strtotime($sessionDate)); $sys=date('d-M-Y',strtotime($systemDate));
  $item=[['label'=>'Counter session '.($session['register_code']??'').' opened','date'=>date('d-M-Y H:i',strtotime((string)$session['opened_at'])),'url'=>site_url('cash')]];
  if($sessionDate===$systemDate) {
   return new SystemDateConflict('A counter session is already open for '.$sd,
    'Only one counter session can be open at a time, and the session for '.$sd.' is open already.',
    $item,
    [['text'=>'Keep working in the open session. Cash entries of '.$sd.' go into it.','action'=>['type'=>'link','url'=>site_url('cash'),'label'=>'Open Counter Cash']]]);
  }
  return new SystemDateConflict('Cannot '.$doing.' on '.$sys,
   'Each counter session belongs to one date. The open counter session is for '.$sd.', but the System Date is '.$sys.'. Cash of '.$sys.' cannot go into the session of '.$sd.', otherwise the cash count of both days would be wrong.',
   $item,
   [
    ['text'=>'Finish '.$sd.' first: count the cash and close that session on Counter Cash, then open a session for '.$sys.'.','action'=>['type'=>'link','url'=>site_url('cash'),'label'=>'Go to Counter Cash']],
    ['text'=>'Or change the System Date back to '.$sd.' and post this there.','action'=>['type'=>'set_date','date'=>$sessionDate,'label'=>'Change System Date to '.$sd]],
    ['text'=>'Or post it as cheque / online (or credit for a named customer), which does not use the counter.'],
   ]);
 }
 protected function findOpenSession(int $locationId): ?array {
  return $this->db->table('cash_sessions cs')->select('cs.*,cr.code register_code,cr.name register_name')->join('cash_registers cr','cr.id=cs.register_id')->where('cr.location_id',$locationId)->where('cs.status','open')->orderBy('cs.id','DESC')->get()->getRowArray() ?: null;
 }
 public function postSaleCash(int $sessionId,int $saleId,float $amount,int $userId,string $at): void {
  if($amount<=0) return;
  $session=$this->db->query("SELECT * FROM cash_sessions WHERE id=? FOR UPDATE",[$sessionId])->getRowArray();
  if(!$session || $session['status']!=='open') throw new RuntimeException('No open cash session is available for this cash sale.');
  $this->db->table('cash_transactions')->insert(['cash_session_id'=>$sessionId,'transaction_type'=>'sale_cash','direction'=>'in','amount'=>$amount,'transaction_at'=>$at,'reference_type'=>'sale','reference_id'=>$saleId,'created_by'=>$userId]);
  if(!$this->db->transStatus()) throw new RuntimeException('Cash transaction could not be posted.');
 }
 public function postGeneric(int $sessionId,string $type,string $direction,float $amount,string $referenceType,int $referenceId,int $userId,string $notes='',?string $at=null): void {
  if($amount<=0) throw new RuntimeException('Cash amount must be positive.');
  if(!in_array($direction,['in','out'],true)) throw new RuntimeException('Invalid cash direction.');
  $session=$this->db->query("SELECT * FROM cash_sessions WHERE id=? FOR UPDATE",[$sessionId])->getRowArray();
  if(!$session || $session['status']!=='open') throw new RuntimeException('Cash session is closed or unavailable.');
  $this->db->table('cash_transactions')->insert(['cash_session_id'=>$sessionId,'transaction_type'=>$type,'direction'=>$direction,'amount'=>$amount,'transaction_at'=>$at?:BranchContext::now(),'reference_type'=>$referenceType,'reference_id'=>$referenceId,'created_by'=>$userId,'notes'=>$notes?:null]);
  if(!$this->db->transStatus()) throw new RuntimeException('Cash transaction could not be posted.');
 }
 public function summary(int $sessionId): array {
  $session=$this->db->table('cash_sessions')->where('id',$sessionId)->get()->getRowArray();
  if(!$session) throw new RuntimeException('Cash session not found.');
  $row=$this->db->table('cash_transactions')->select("SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) cash_in,SUM(CASE WHEN direction='out' THEN amount ELSE 0 END) cash_out")->where('cash_session_id',$sessionId)->get()->getRowArray();
  return ['session'=>$session,'cash_in'=>(float)($row['cash_in']??0),'cash_out'=>(float)($row['cash_out']??0),'expected'=>(float)($row['cash_in']??0)-(float)($row['cash_out']??0)];
 }
 public function history(int $locationId, ?string $from=null, ?string $to=null, ?int $registerId=null): array {
  $from=$from?:date('Y-m-d',strtotime(BranchContext::today().' -30 days')); $to=$to?:BranchContext::today();
  $q=$this->db->table('cash_transactions ct')->select('ct.*,cs.opened_by,cs.opened_at,cs.closed_at,cs.counted_cash,cr.code register_code,cr.name register_name')->join('cash_sessions cs','cs.id=ct.cash_session_id')->join('cash_registers cr','cr.id=cs.register_id')->where('cr.location_id',$locationId)->where('ct.transaction_at >=',$from.' 00:00:00')->where('ct.transaction_at <=',$to.' 23:59:59');
  if($registerId) $q->where('cr.id',$registerId);
  return $q->orderBy('ct.transaction_at','DESC')->orderBy('ct.id','DESC')->get()->getResultArray();
 }
 public function closeSession(int $sessionId,int $userId,float $countedCash,string $notes=''): array {
  if($countedCash<0) throw new RuntimeException('Counted cash cannot be negative.');
  $this->db->transBegin();
  try {
   $session=$this->db->query("SELECT * FROM cash_sessions WHERE id=? FOR UPDATE",[$sessionId])->getRowArray();
   if(!$session) throw new RuntimeException('Cash session not found.');
   if($session['status']!=='open') throw new RuntimeException('Cash session is already closed.');
   $summary=$this->summary($sessionId);
   // A session closes on its own date: when the System Date has moved on, it closes at the end of that day.
   $sessionDate=substr((string)$session['opened_at'],0,10);
   $closedAt=($sessionDate===BranchContext::today() || self::overnightShift($session,BranchContext::currentId()))?BranchContext::now():$sessionDate.' 23:59:59';
   if($closedAt<$session['opened_at']) $closedAt=$session['opened_at'];
   $this->db->table('cash_sessions')->where('id',$sessionId)->update(['closed_by'=>$userId,'closed_at'=>$closedAt,'counted_cash'=>$countedCash,'status'=>'closed','notes'=>trim($notes)?:$summary['session']['notes']]);
   if(!$this->db->transStatus()) throw new RuntimeException('Cash session could not be closed.');
   $this->db->transCommit(); return ['expected'=>$summary['expected'],'counted'=>$countedCash,'difference'=>$countedCash-$summary['expected']];
  } catch(\Throwable $e){$this->db->transRollback();throw $e;}
 }
}
