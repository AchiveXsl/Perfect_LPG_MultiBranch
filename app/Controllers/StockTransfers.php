<?php

namespace App\Controllers;

use App\Services\BranchContext;
use App\Services\PermissionService;
use App\Services\StockTransferService;
use CodeIgniter\Controller;
use Config\Database;

/**
 * Stock transfers between branches: the sending branch dispatches cylinders, the receiving branch confirms them.
 */
class StockTransfers extends Controller
{
    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if (!PermissionService::allows('STOCK_TRANSFER')) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }
        return null;
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;
        $loc = BranchContext::currentId();
        // Date filter on the dispatch date, default the current month. In-transit transfers always show.
        $valid = static fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
        $from = $valid($this->request->getGet('from')) ? $this->request->getGet('from') : date('Y-m-01');
        $to = $valid($this->request->getGet('to')) ? $this->request->getGet('to') : date('Y-m-d');
        if ($from > $to) [$from, $to] = [$to, $from];
        $rows = (new StockTransferService())->listFor($loc, 2000, $from, $to);
        return view('stock_transfers/index', [
            'title' => 'Stock Transfers',
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'currentId' => $loc,
            'incoming' => array_values(array_filter($rows, static fn($t) => $t['status'] === 'in_transit' && (int) $t['to_location_id'] === $loc)),
        ]);
    }

    public function create()
    {
        if ($r = $this->guard()) return $r;
        $loc = BranchContext::currentId();
        $db = Database::connect();
        $types = $db->table('cylinder_types')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray();
        $units = $db->table('cylinder_units')
            ->select('id, cylinder_type_id, unit_code, status, gas_weight_kg')
            ->where('location_id', $loc)->whereIn('status', ['filled', 'empty'])->where('custody_customer_id IS NULL', null, false)
            ->orderBy('cylinder_type_id')->orderBy('status')->orderBy('gas_weight_kg', 'DESC')->orderBy('id')
            ->get()->getResultArray();
        $byType = [];
        foreach ($units as $u) {
            $byType[(int) $u['cylinder_type_id']][$u['status']][] = $u;
        }
        $destinations = array_values(array_filter(BranchContext::allActive(), static fn($b) => (int) $b['id'] !== $loc));
        return view('stock_transfers/create', [
            'title' => 'New Stock Transfer',
            'types' => $types,
            'unitsByType' => $byType,
            'destinations' => $destinations,
            'from' => BranchContext::current(),
            'earliestDate' => (new StockTransferService())->earliestDate($loc),
        ]);
    }

    public function dispatch()
    {
        if ($r = $this->guard()) return $r;
        try {
            $byType = [];
            foreach ((array) $this->request->getPost('qty') as $typeId => $q) {
                $byType[(int) $typeId] = ['filled' => (int) ($q['filled'] ?? 0), 'empty' => (int) ($q['empty'] ?? 0)];
            }
            $x = (new StockTransferService())->dispatch(
                BranchContext::currentId(),
                (int) $this->request->getPost('to_location_id'),
                $byType,
                (array) $this->request->getPost('unit_ids'),
                (string) $this->request->getPost('notes'),
                (int) session()->get('user_id'),
                (string) $this->request->getPost('transfer_date')
            );
            return redirect()->to('/stock-transfers/' . $x['id'])->with('success', 'Transfer ' . $x['transfer_no'] . ' dispatched: ' . $x['filled'] . ' filled and ' . $x['empty'] . ' empty cylinder(s), ' . number_format($x['gas_kg'], 3) . ' KG gas. The receiving branch must confirm it.');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id)
    {
        if ($r = $this->guard()) return $r;
        $loc = BranchContext::currentId();
        $t = (new StockTransferService())->find($id);
        if (!$t || ((int) $t['from_location_id'] !== $loc && (int) $t['to_location_id'] !== $loc)) {
            return $this->response->setStatusCode(404)->setBody('Transfer not found at this branch.');
        }
        return view('stock_transfers/show', [
            'title' => 'Stock Transfer ' . $t['transfer_no'],
            't' => $t,
            'canReceive' => $t['status'] === 'in_transit' && (int) $t['to_location_id'] === $loc,
            'earliestDate' => (new StockTransferService())->earliestDate($loc),
            'canCancel' => $t['status'] === 'in_transit' && (int) $t['from_location_id'] === $loc,
        ]);
    }

    public function receive(int $id)
    {
        if ($r = $this->guard()) return $r;
        try {
            $x = (new StockTransferService())->receive(
                $id,
                BranchContext::currentId(),
                (array) $this->request->getPost('lines'),
                (string) $this->request->getPost('receive_notes'),
                (int) session()->get('user_id'),
                (string) $this->request->getPost('receive_date')
            );
            $msg = 'Transfer ' . $x['transfer_no'] . ' received: ' . $x['received'] . ' cylinder(s) added to stock.';
            if ($x['missing'] > 0) $msg .= ' ' . $x['missing'] . ' cylinder(s) marked missing went back to the sending branch.';
            $loss = $x['gas_sent_kg'] - $x['gas_received_kg'];
            if ($loss > 0.0005) $msg .= ' Gas short on arrival: ' . number_format($loss, 3) . ' KG.';
            return redirect()->to('/stock-transfers/' . $id)->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cancel(int $id)
    {
        if ($r = $this->guard()) return $r;
        try {
            $no = (new StockTransferService())->cancel($id, BranchContext::currentId(), (string) $this->request->getPost('reason'), (int) session()->get('user_id'));
            return redirect()->to('/stock-transfers/' . $id)->with('success', 'Transfer ' . $no . ' cancelled. The cylinders are back in this branch\'s stock.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
