<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\BranchContext;
use App\Services\LoanService;
use App\Services\PermissionService;
use CodeIgniter\Controller;
use Config\Database;

/** Loans taken by the current branch from lenders. Posting and voids live in LoanService. */
class Loans extends Controller
{
    private function guard()
    {
        return PermissionService::allows('LOAN_MANAGE') ? null : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    private static function validDate($v): bool
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $loc = BranchContext::currentId();
        $from = self::validDate($this->request->getGet('from')) ? $this->request->getGet('from') : date('Y-m-01');
        $to = self::validDate($this->request->getGet('to')) ? $this->request->getGet('to') : date('Y-m-d');
        if ($from > $to) [$from, $to] = [$to, $from];

        $lenders = $db->table('lenders')->where('location_id', $loc)->orderBy('name')->get()->getResultArray();
        $editId = (int) $this->request->getGet('edit');
        return view('loans/index', [
            'title' => 'Loans',
            'lenders' => $lenders,
            'balances' => (new LoanService())->balances($lenders),
            'edit' => $editId ? $db->table('lenders')->where(['id' => $editId, 'location_id' => $loc])->get()->getRowArray() : null,
            'entries' => $db->table('loan_entries e')->select('e.*, l.name lender_name, u.full_name created_by_name')
                ->join('lenders l', 'l.id = e.lender_id')->join('users u', 'u.id = e.created_by', 'left')
                ->where('e.location_id', $loc)->where('e.entry_at >=', $from . ' 00:00:00')->where('e.entry_at <=', $to . ' 23:59:59')
                ->orderBy('e.entry_at', 'DESC')->orderBy('e.id', 'DESC')->limit(5000)->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
            'cashOpen' => (bool) (new \App\Services\CashService())->openSessionForLocation($loc),
        ]);
    }

    public function saveLender()
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $loc = BranchContext::currentId();
        $id = (int) $this->request->getPost('id');
        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'cnic' => trim((string) $this->request->getPost('cnic')) ?: null,
            'address' => trim((string) $this->request->getPost('address')) ?: null,
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
        if ($data['name'] === '') return redirect()->back()->withInput()->with('error', 'Lender name is required.');
        $opening = round((float) $this->request->getPost('opening_balance'), 2);
        if ($opening < 0) return redirect()->back()->withInput()->with('error', 'Opening balance cannot be negative.');
        $userId = (int) session()->get('user_id');

        if ($id) {
            $old = $db->table('lenders')->where(['id' => $id, 'location_id' => $loc])->get()->getRowArray();
            if (!$old) return redirect()->to('/loans')->with('error', 'Lender not found in this branch.');
            // The opening balance can change only while the lender has no posted entries.
            $hasEntries = $db->table('loan_entries')->where(['lender_id' => $id, 'status' => 'posted'])->countAllResults() > 0;
            if ($hasEntries && abs($opening - (float) $old['opening_balance']) > 0.004) {
                return redirect()->back()->withInput()->with('error', 'Opening balance cannot change after loan entries are posted.');
            }
            $data['opening_balance'] = $opening;
            $db->table('lenders')->where('id', $id)->update($data);
            AuditService::log('UPDATE', 'lender', $id, $old, $data, $userId, $loc);
            return redirect()->to('/loans')->with('success', 'Lender updated.');
        }
        $data += ['location_id' => $loc, 'opening_balance' => $opening, 'created_by' => $userId];
        $db->table('lenders')->insert($data);
        $id = (int) $db->insertID();
        AuditService::log('CREATE', 'lender', $id, null, $data, $userId, $loc);
        return redirect()->to('/loans')->with('success', 'Lender ' . $data['name'] . ' added.');
    }

    public function save()
    {
        if ($r = $this->guard()) return $r;
        try {
            $res = (new LoanService())->post($this->request->getPost(), (int) session()->get('user_id'), BranchContext::currentId());
            $label = $this->request->getPost('entry_type') === 'received' ? 'Loan received' : 'Repayment';
            return redirect()->to('/loans')->with('success', $label . ' ' . $res['entry_no'] . ' posted for ' . $res['lender']['name'] . '.');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function void(int $id)
    {
        if ($r = $this->guard()) return $r;
        try {
            $entry = (new LoanService())->void($id, (string) $this->request->getPost('void_reason'), (int) session()->get('user_id'), BranchContext::currentId());
            return redirect()->back()->with('success', 'Entry ' . $entry['entry_no'] . ' voided. Any cash was reversed.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function ledger(int $id)
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $loc = BranchContext::currentId();
        $lender = $db->table('lenders')->where(['id' => $id, 'location_id' => $loc])->get()->getRowArray();
        if (!$lender) return redirect()->to('/loans')->with('error', 'Lender not found in this branch.');
        return view('loans/ledger', [
            'title' => 'Lender Ledger — ' . $lender['name'],
            'lender' => $lender,
            'entries' => $db->table('loan_entries e')->select('e.*, u.full_name created_by_name')->join('users u', 'u.id = e.created_by', 'left')
                ->where('e.lender_id', $id)->orderBy('e.entry_at')->orderBy('e.id')->get()->getResultArray(),
            'balance' => (new LoanService())->balance($id),
        ]);
    }
}
