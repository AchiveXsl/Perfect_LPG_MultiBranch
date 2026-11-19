<?php
namespace App\Controllers\Filters;

use App\Services\BranchContext;
use App\Services\MigrationRunnerService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    /** Pages that work before a branch is chosen. */
    protected const BRANCHLESS = ['branches/select', 'branches/enter'];

    public function before(RequestInterface $request,$arguments=null)
    {
        if(!session()->get('isLoggedIn')) {
            return redirect()->to(site_url('login'))->with('error','Please sign in to continue.');
        }

        try {
            $migrationGate=(new MigrationRunnerService())->status();
            if(!$migrationGate['ready']) {
                session()->destroy();
                return redirect()->to(site_url('login'))
                    ->with('migration_error',$migrationGate['blocked_message'] ?? 'Database migrations are pending. Execute them before signing in.');
            }
        } catch (\Throwable $e) {
            session()->destroy();
            return redirect()->to(site_url('login'))
                ->with('migration_error','Database migration status could not be checked: '.$e->getMessage());
        }

        $path=trim($request->getUri()->getPath(),'/');
        $branchless=false;
        foreach(self::BRANCHLESS as $route){
            if($path===$route || str_ends_with($path,'/'.$route)) { $branchless=true; break; }
        }

        $userId=(int)session()->get('user_id');
        $locationId=BranchContext::currentId();
        if(!BranchContext::branchesFor($userId)) {
            session()->destroy();
            return redirect()->to(site_url('login'))->with('error','No active branch is assigned to this account. Contact an administrator.');
        }
        if($locationId>0 && !BranchContext::canAccess($userId,$locationId)) {
            // Access to this branch was removed or the branch was deactivated.
            session()->set('location_id',null);
            $locationId=0;
        }
        if($locationId<=0 && !$branchless) {
            if($this->wantsJson($request)) {
                return service('response')->setStatusCode(409)->setJSON(['success'=>false,'message'=>'Select a branch first.','branch_required'=>true]);
            }
            return redirect()->to(site_url('branches/select'));
        }

        // A page opened in one branch must not save into another. The layout sends the branch it was opened in
        // with every form and AJAX call. When the user switched branch in another tab, the two no longer match.
        if(!$branchless && strtoupper($request->getMethod())==='POST') {
            $sent=$request->getHeaderLine('X-LPG-Branch');
            if($sent==='') $sent=(string)($request->getPost('_branch_id') ?? '');
            if($sent!=='' && (int)$sent!==$locationId) {
                $current=BranchContext::current();
                $message='You switched to branch '.($current['name'] ?? '').' in another tab. This page belongs to a different branch and was not saved. Reload the page and try again.';
                if($this->wantsJson($request)) {
                    return service('response')->setStatusCode(409)->setJSON(['success'=>false,'message'=>$message,'branch_changed'=>true]);
                }
                return redirect()->back()->withInput()->with('error',$message);
            }
        }
    }

    protected function wantsJson(RequestInterface $request): bool
    {
        return strtolower($request->getHeaderLine('X-Requested-With'))==='xmlhttprequest'
            || str_contains(strtolower($request->getHeaderLine('Accept')),'application/json');
    }

    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null){}
}
