<?php

namespace App\Services;

use Config\Database;
use RuntimeException;

/**
 * Loans taken by a branch from lenders (people who are neither customers nor suppliers).
 * Each branch keeps its own lenders and loans and repays them itself. No interest.
 * A loan is a liability: never sales, profit or expense. Cash entries move the counter cash.
 * Posted entries are voided with a reason, never deleted.
 */
class LoanService
{
    public const MODES = ['cash', 'cheque', 'online'];

    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /** What the branch still owes the lender: opening + received - repaid (posted entries only). */
    public function balance(int $lenderId): float
    {
        $lender = $this->db->table('lenders')->select('opening_balance')->where('id', $lenderId)->get()->getRowArray();
        $row = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN entry_type='received' THEN amount ELSE -amount END),0) net
             FROM loan_entries WHERE lender_id=? AND status='posted'",
            [$lenderId]
        )->getRowArray();
        return round((float) ($lender['opening_balance'] ?? 0) + (float) ($row['net'] ?? 0), 2);
    }

    /** Balances for many lenders at once: [lender_id => balance]. */
    public function balances(array $lenders): array
    {
        $ids = array_map(static fn($l) => (int) $l['id'], $lenders);
        if (!$ids) return [];
        $net = [];
        foreach ($this->db->table('loan_entries')
            ->select("lender_id, SUM(CASE WHEN entry_type='received' THEN amount ELSE -amount END) net", false)
            ->whereIn('lender_id', $ids)->where('status', 'posted')->groupBy('lender_id')->get()->getResultArray() as $r) {
            $net[(int) $r['lender_id']] = (float) $r['net'];
        }
        $out = [];
        foreach ($lenders as $l) {
            $out[(int) $l['id']] = round((float) $l['opening_balance'] + ($net[(int) $l['id']] ?? 0), 2);
        }
        return $out;
    }

    /** Lender of this branch, or an exception. */
    public function lenderAt(int $lenderId, int $locationId, bool $lock = false): array
    {
        $sql = 'SELECT * FROM lenders WHERE id=? AND location_id=?' . ($lock ? ' FOR UPDATE' : '');
        $lender = $this->db->query($sql, [$lenderId, $locationId])->getRowArray();
        if (!$lender) throw new RuntimeException('Lender not found in this branch.');
        return $lender;
    }

    /**
     * Post a loan received or a repayment. Cash entries need an open counter session.
     * A repayment cannot exceed what the branch still owes the lender.
     */
    public function post(array $in, int $userId, int $locationId): array
    {
        $type = (string) ($in['entry_type'] ?? '');
        $amount = round((float) ($in['amount'] ?? 0), 2);
        $mode = (string) ($in['payment_mode'] ?? 'cash');
        if (!in_array($type, ['received', 'repayment'], true)) throw new RuntimeException('Choose Loan Received or Repayment.');
        if ($amount <= 0) throw new RuntimeException('Amount must be more than zero.');
        if (!in_array($mode, self::MODES, true)) throw new RuntimeException('Invalid payment mode.');
        $due = trim((string) ($in['due_date'] ?? ''));
        if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) throw new RuntimeException('Invalid due date.');

        $this->db->transBegin();
        try {
            $lender = $this->lenderAt((int) ($in['lender_id'] ?? 0), $locationId, true);
            if ((int) $lender['is_active'] !== 1) throw new RuntimeException('Lender is inactive.');
            if ($type === 'repayment') {
                $owed = $this->balance((int) $lender['id']);
                if ($amount > $owed + 0.004) {
                    throw new RuntimeException('Repayment ' . number_format($amount, 2) . ' is more than the ' . number_format(max(0, $owed), 2) . ' still owed to ' . $lender['name'] . '.');
                }
            }
            $no = ($type === 'received' ? 'LN' : 'LR') . date('YmdHis') . '-' . random_int(100, 999);
            $this->db->table('loan_entries')->insert([
                'entry_no' => $no, 'location_id' => $locationId, 'lender_id' => (int) $lender['id'], 'entry_type' => $type,
                'amount' => $amount, 'payment_mode' => $mode, 'reference_no' => trim((string) ($in['reference_no'] ?? '')) ?: null,
                'entry_at' => \App\Services\BranchContext::now(), 'due_date' => ($type === 'received' && $due !== '') ? $due : null,
                'notes' => trim((string) ($in['notes'] ?? '')) ?: null, 'status' => 'posted', 'created_by' => $userId,
            ]);
            $id = (int) $this->db->insertID();

            if ($mode === 'cash') {
                $cash = new CashService();
                $session = $cash->openSessionForLocation($locationId);
                if (!$session) throw new RuntimeException('Open the counter cash session before a cash loan entry.');
                $cash->postGeneric(
                    (int) $session['id'],
                    $type === 'received' ? 'loan_received' : 'loan_repayment',
                    $type === 'received' ? 'in' : 'out',
                    $amount, 'loan_entry', $id, $userId,
                    ($type === 'received' ? 'Loan received from ' : 'Loan repaid to ') . $lender['name'] . ' (' . $no . ')'
                );
            }
            if (!$this->db->transStatus()) throw new RuntimeException('Loan entry could not be saved.');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        AuditService::log('CREATE', 'loan_entry', $id, null, ['entry_no' => $no, 'type' => $type, 'amount' => $amount, 'lender_id' => (int) $lender['id']], $userId, $locationId);
        return ['id' => $id, 'entry_no' => $no, 'lender' => $lender];
    }

    /**
     * Void an entry: the counter cash is reversed in the session it was posted in.
     * A loan received cannot be voided if that would leave repayments above what was borrowed.
     */
    public function void(int $entryId, string $reason, int $userId, int $locationId): array
    {
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException('A reason is required to void.');
        $this->db->transBegin();
        try {
            $entry = $this->db->query('SELECT * FROM loan_entries WHERE id=? AND location_id=? FOR UPDATE', [$entryId, $locationId])->getRowArray();
            if (!$entry) throw new RuntimeException('Loan entry not found in this branch.');
            if ($entry['status'] !== 'posted') throw new RuntimeException('This entry is already voided.');
            $this->lenderAt((int) $entry['lender_id'], $locationId, true);
            if ($entry['entry_type'] === 'received' && $this->balance((int) $entry['lender_id']) - (float) $entry['amount'] < -0.004) {
                throw new RuntimeException('Cannot void: part of this loan is already repaid. Void the repayment first.');
            }
            $this->db->table('loan_entries')->where('id', $entryId)->update([
                'status' => 'voided', 'void_reason' => $reason, 'voided_by' => $userId, 'voided_at' => \App\Services\BranchContext::now(),
            ]);
            if ($entry['payment_mode'] === 'cash') {
                foreach ($this->db->table('cash_transactions')->where(['reference_type' => 'loan_entry', 'reference_id' => $entryId])->get()->getResultArray() as $cash) {
                    $this->db->table('cash_transactions')->insert([
                        'cash_session_id' => $cash['cash_session_id'], 'transaction_type' => $cash['transaction_type'],
                        'direction' => $cash['direction'] === 'in' ? 'out' : 'in', 'amount' => $cash['amount'],
                        'transaction_at' => \App\Services\BranchContext::now(), 'reference_type' => 'loan_entry_void', 'reference_id' => $entryId,
                        'created_by' => $userId, 'notes' => 'Cash reversal of voided loan entry ' . $entry['entry_no'],
                    ]);
                }
            }
            if (!$this->db->transStatus()) throw new RuntimeException('Void failed.');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        AuditService::log('VOID', 'loan_entry', $entryId, ['status' => 'posted'], ['status' => 'voided', 'reason' => $reason], $userId, $locationId);
        return $entry;
    }
}
