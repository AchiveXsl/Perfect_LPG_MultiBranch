<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\BranchContext;
use App\Services\PermissionService;
use CodeIgniter\Controller;
use Config\Database;

/**
 * Branch picker after sign-in, the top-bar branch switch, and branch management.
 */
class Branches extends Controller
{
    /** Cards of the branches the user may enter. */
    public function select()
    {
        $userId = (int) session()->get('user_id');
        $branches = BranchContext::branchesFor($userId);
        if (count($branches) === 1) {
            BranchContext::enter((int) array_key_first($branches));
            return redirect()->to(site_url('dashboard'));
        }
        return view('branches/select', [
            'title' => 'Select Branch',
            'branches' => $branches,
            'currentId' => BranchContext::currentId(),
            'fullName' => (string) session()->get('full_name'),
        ]);
    }

    /** Enter a branch, from the picker cards or the top-bar switch. */
    public function enter()
    {
        $locationId = (int) $this->request->getPost('location_id');
        $previous = BranchContext::currentId();
        if (!BranchContext::enter($locationId)) {
            return redirect()->to(site_url('branches/select'))->with('error', 'You do not have access to that branch.');
        }
        $branch = BranchContext::current();
        if ($previous > 0 && $previous !== $locationId) {
            AuditService::log('BRANCH_SWITCH', 'location', $locationId, ['location_id' => $previous], ['location_id' => $locationId], (int) session()->get('user_id'), $locationId);
        }
        return redirect()->to(site_url('dashboard'))->with('success', 'You are now working in ' . ($branch['name'] ?? 'the selected branch') . '.');
    }

    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if (!PermissionService::allows('BRANCH_MANAGE')) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }
        return null;
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $branches = $db->table('locations l')
            ->select('l.*, (SELECT COUNT(*) FROM user_branches ub WHERE ub.location_id=l.id) user_count')
            ->orderBy('l.is_head_office', 'DESC')->orderBy('l.name')->get()->getResultArray();
        $editId = (int) $this->request->getGet('edit');
        return view('branches/index', [
            'title' => 'Branches',
            'branches' => $branches,
            'edit' => $editId ? $db->table('locations')->where('id', $editId)->get()->getRowArray() : null,
        ]);
    }

    public function save()
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $id = (int) $this->request->getPost('id');
        $data = [
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'address' => trim((string) $this->request->getPost('address')) ?: null,
            'city' => trim((string) $this->request->getPost('city')) ?: null,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
        $makeHeadOffice = (bool) $this->request->getPost('is_head_office');
        if ($data['code'] === '' || $data['name'] === '') {
            return redirect()->back()->withInput()->with('error', 'Branch code and name are required.');
        }
        if (!preg_match('/^[A-Z0-9_-]{1,30}$/', $data['code'])) {
            return redirect()->back()->withInput()->with('error', 'Branch code may use letters, digits, - and _ only (up to 30).');
        }
        $old = $id ? $db->table('locations')->where('id', $id)->get()->getRowArray() : null;
        if ($id && !$old) {
            return redirect()->to('/branches')->with('error', 'Branch not found.');
        }
        if ($old && (int) $old['is_head_office'] === 1) {
            if (!$data['is_active']) {
                return redirect()->back()->withInput()->with('error', 'The Head Office cannot be deactivated. Make another branch the Head Office first.');
            }
        }
        if ($old && !$data['is_active'] && $id === BranchContext::currentId()) {
            return redirect()->back()->withInput()->with('error', 'You cannot deactivate the branch you are working in. Switch to another branch first.');
        }
        if ($makeHeadOffice && !$data['is_active']) {
            return redirect()->back()->withInput()->with('error', 'The Head Office must be active.');
        }

        $db->transBegin();
        try {
            if ($id) {
                $db->table('locations')->where('id', $id)->update($data);
            } else {
                $db->table('locations')->insert($data + ['is_head_office' => 0]);
                $id = (int) $db->insertID();
                $this->setUpNewBranch($id);
            }
            if ($makeHeadOffice) {
                $db->query('UPDATE locations SET is_head_office = IF(id = ?, 1, 0)', [$id]);
            }
            if (!$db->transStatus()) {
                throw new \RuntimeException('Branch could not be saved.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $message = str_contains(strtolower($e->getMessage()), 'duplicate') ? 'Branch code ' . $data['code'] . ' is already used.' : $e->getMessage();
            return redirect()->back()->withInput()->with('error', $message);
        }
        BranchContext::reset();
        AuditService::log($old ? 'UPDATE' : 'CREATE', 'location', $id, $old, $data + ['is_head_office' => $makeHeadOffice ? 1 : (int) ($old['is_head_office'] ?? 0)], (int) session()->get('user_id'), BranchContext::currentId());
        return redirect()->to('/branches')->with('success', $old ? 'Branch updated.' : 'Branch ' . $data['name'] . ' created. Assign users to it in Users / Roles. Owners and admins can already enter it.');
    }

    /**
     * A new branch starts with the Head Office settings (POS rules, credit rules, theme), a cash register
     * and empty stock. Rates are not copied: each branch sets its own prices in LPG Rates.
     */
    protected function setUpNewBranch(int $locationId): void
    {
        $db = Database::connect();
        $headOffice = BranchContext::headOfficeId();
        $settings = $headOffice ? $db->table('shop_settings')->where('location_id', $headOffice)->get()->getRowArray() : null;
        if ($settings) {
            unset($settings['id'], $settings['created_at'], $settings['updated_at']);
            $settings['location_id'] = $locationId;
            $db->table('shop_settings')->insert($settings);
        }
        $db->table('cash_registers')->insert(['location_id' => $locationId, 'code' => 'REG-01', 'name' => 'Main Counter', 'is_active' => 1]);
        $policies = $headOffice ? $db->table('inventory_policies')->where('location_id', $headOffice)->get()->getResultArray() : [];
        foreach ($policies as $p) {
            unset($p['id'], $p['created_at'], $p['updated_at']);
            $p['location_id'] = $locationId;
            $db->table('inventory_policies')->ignore(true)->insert($p);
        }
    }
}
