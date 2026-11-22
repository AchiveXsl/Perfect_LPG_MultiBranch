<?php
namespace App\Models;
use App\Services\BranchContext;
use CodeIgniter\Model;
use RuntimeException;

/**
 * Customers are defined once and are either shared by all branches or belong to one branch:
 * - branch_scope = 'shared': usable at every branch.
 * - branch_scope = 'branch': usable only at home_location_id.
 * The customer's opening balance belongs to the home branch. Sales, receipts and deposits carry their own branch,
 * so a customer's balance is always known per branch and can be added up across branches.
 * credit_scope picks which balance the credit limit is checked against: this branch ('branch') or all branches ('combined').
 */
class CustomerModel extends Model
{
    protected $table='customers'; protected $primaryKey='id'; protected $returnType='array';
    protected $useTimestamps=true; protected $createdField='created_at'; protected $updatedField='updated_at';
    protected $allowedFields=['code','name','customer_type','branch_scope','home_location_id','credit_scope','phone','city','address','vehicle_no','credit_limit','allow_credit_sale','opening_balance','is_active'];

    /** Home branch of a customer row (Head Office for rows saved before branches existed). */
    public static function homeOf(array $customer): int
    {
        $home=(int)($customer['home_location_id'] ?? 0);
        return $home>0 ? $home : BranchContext::headOfficeId();
    }

    /** The opening balance counts at the home branch only. */
    public static function openingAt(array $customer, int $locationId): float
    {
        return self::homeOf($customer)===$locationId ? (float)($customer['opening_balance'] ?? 0) : 0.0;
    }

    /**
     * Split purchasing (each branch buys its own stock): every customer belongs to its home branch only,
     * with its credit limit checked against that branch. Owner decision 2026-11-24.
     */
    public static function branchOnly(): bool
    {
        return BranchContext::purchaseMode()==='split';
    }

    public static function isUsableAt(array $customer, int $locationId): bool
    {
        if(self::branchOnly()) return self::homeOf($customer)===$locationId;
        return ($customer['branch_scope'] ?? 'shared')!=='branch' || self::homeOf($customer)===$locationId;
    }

    public static function assertUsableAt(?array $customer, int $locationId): void
    {
        if($customer && !self::isUsableAt($customer,$locationId)){
            throw new RuntimeException('Customer '.$customer['name'].' belongs to another branch and cannot be used here.');
        }
    }

    /**
     * Limit a query on customers to those usable at a branch.
     * $builder is a query builder on customers (optionally aliased), $alias the table alias.
     */
    public static function applyVisibleAt($builder, int $locationId, string $alias='customers')
    {
        $headOffice=BranchContext::headOfficeId();
        $builder->groupStart();
        if(self::branchOnly()) $builder->where($alias.'.home_location_id',$locationId);
        else $builder->where($alias.'.branch_scope','shared')->orWhere($alias.'.home_location_id',$locationId);
        if($locationId===$headOffice) $builder->orWhere($alias.'.home_location_id IS NULL',null,false);
        return $builder->groupEnd();
    }

    /** Customers usable at a branch (default: the current branch). */
    public function visibleAt(?int $locationId=null): self
    {
        self::applyVisibleAt($this,$locationId ?? BranchContext::currentId(),'customers');
        return $this;
    }

    public function withLedgerTotals(int $customerId, ?int $locationId=null): ?array
    {
        $customer=$this->find($customerId); if(!$customer) return null;
        $locationId=$locationId ?? BranchContext::currentId();
        $s=$this->db->table('sales')->selectSum('total_amount','total_purchased')->selectSum('credit_amount','total_credit')->selectSum('return_gas_ledger_amount','return_ledger')->selectSum('total_kg','total_kg')->where('customer_id',$customerId)->where('status','posted');
        if($locationId>0) $s->where('location_id',$locationId);
        $s=$s->get()->getRowArray();
        $p=$this->db->table('customer_receipts')->selectSum('amount','total_paid')->where('customer_id',$customerId)->where('status','posted');
        if($locationId>0) $p->where('location_id',$locationId);
        $p=$p->get()->getRowArray();
        $opening=$locationId>0 ? self::openingAt($customer,$locationId) : (float)$customer['opening_balance'];
        $customer['branch_opening_balance']=$opening;
        $customer['total_purchased']=(float)($s['total_purchased']??0); $customer['total_gas_kg']=(float)($s['total_kg']??0);
        $customer['credit_due']=$opening+(float)($s['total_credit']??0)+(float)($s['return_ledger']??0)-(float)($p['total_paid']??0);
        $d=$this->db->table('customer_security_deposits')->select("SUM(CASE WHEN entry_type='hold' THEN amount ELSE 0 END) deposit_held,SUM(CASE WHEN entry_type='refund' THEN amount ELSE 0 END) deposit_refunded")->where(['customer_id'=>$customerId]);
        if($locationId>0) $d->where('location_id',$locationId);
        $d=$d->get()->getRowArray();
        $customer['security_deposit_held']=(float)($d['deposit_held']??0)-(float)($d['deposit_refunded']??0);
        return $customer;
    }

    /** Active customers usable at the current branch. */
    public function activeDirectory(): array { return $this->where('is_active',1)->visibleAt()->orderBy('name')->findAll(); }
}
