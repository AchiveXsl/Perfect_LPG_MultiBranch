<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\BranchContext;
use App\Services\PermissionService;

/**
 * Saves the branch System Date from the top bar (owner decision 2026-11-26).
 * The date stays until a user with SYSTEM_DATE_CHANGE changes it. Past and future dates are allowed.
 * "Follow today" clears it, so the branch works on the real date again.
 */
class SystemDate extends BaseController
{
    public function save()
    {
        $ajax = $this->request->isAJAX();
        $fail = function (string $message, int $code = 422) use ($ajax) {
            return $ajax
                ? $this->response->setStatusCode($code)->setJSON(['success' => false, 'message' => $message])
                : redirect()->back()->with('error', $message);
        };
        if (!PermissionService::allows('SYSTEM_DATE_CHANGE')) {
            return $fail('You do not have permission to change the System Date.', 403);
        }
        $locationId = BranchContext::requireId();
        $follow = (string) $this->request->getPost('follow_today') === '1';
        $date = trim((string) $this->request->getPost('system_date'));
        if (!$follow) {
            $d = \DateTime::createFromFormat('!Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date || $date < '2000-01-01' || $date > '2099-12-31') {
                return $fail('Enter a valid System Date.');
            }
        }
        $old = BranchContext::storedSystemDate($locationId);
        $new = $follow ? null : $date;
        if ($old !== $new) {
            BranchContext::setSystemDate($locationId, $new);
            AuditService::log('SYSTEM_DATE_CHANGE', 'location', $locationId,
                ['system_date' => $old ?? 'follow today'], ['system_date' => $new ?? 'follow today'],
                (int) session()->get('user_id'), $locationId);
        }
        $label = $new === null ? 'today (' . date('d-M-Y') . ')' : date('d-M-Y', strtotime($new));
        $message = 'System Date set to ' . $label . '. New transactions of this branch use this date.';
        if ($ajax) {
            return $this->response->setJSON(['success' => true, 'message' => $message, 'system_date' => BranchContext::today($locationId)]);
        }
        return redirect()->back()->with('success', $message);
    }
}
