<?php

namespace App\Controllers;

use App\Services\BranchContext;
use App\Services\GasDecantService;
use App\Services\PermissionService;
use CodeIgniter\Controller;

/** Inventory → Gas Decanting: move gas from one filled cylinder into empty / part-filled cylinders. */
class GasDecant extends Controller
{
    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        return PermissionService::allows('GAS_DECANT') ? null : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;
        $svc = new GasDecantService();
        $loc = BranchContext::currentId();
        return view('inventory/decant', ['title' => 'Gas Decanting'] + $svc->candidates($loc) + ['recent' => $svc->recent($loc)]);
    }

    public function save()
    {
        if ($r = $this->guard()) return $r;
        try {
            $x = (new GasDecantService())->decant(
                BranchContext::currentId(),
                (int) $this->request->getPost('source_unit_id'),
                (array) $this->request->getPost('targets'),
                (string) $this->request->getPost('notes'),
                (int) session()->get('user_id')
            );
            return redirect()->to('/inventory/decant')->with('success', 'Decanting ' . $x['decant_no'] . ' saved: ' . number_format($x['total_kg'], 3) . ' KG moved. Source cylinder now holds ' . number_format($x['source_after_kg'], 3) . ' KG.');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
