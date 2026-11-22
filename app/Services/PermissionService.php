<?php

namespace App\Services;

use Config\Database;

class PermissionService
{
    protected static ?array $permissionCache = null;
    protected static ?int $permissionCacheUserId = null;
    protected static ?int $permissionCacheLocationId = null;

    /** Forget the cached permissions, e.g. after the branch changed. */
    public static function reset(): void
    {
        self::$permissionCache = null;
        self::$permissionCacheUserId = null;
        self::$permissionCacheLocationId = null;
    }

    /**
     * Keep standard role permissions usable on older installations.
     * Custom roles are not changed.
     */
    protected static function syncStandardRolePermissions(): void
    {
        static $synced = false;
        if ($synced) {
            return;
        }
        $synced = true;

        $db = Database::connect();

        $permissions = [
            'DASHBOARD_VIEW' => 'View dashboard',
            'POS_SALE' => 'Create POS sales',
            'POS_HISTORY' => 'View sale history',
            'POS_VOID' => 'Void posted sales',
            'CUSTOMER_MANAGE' => 'Manage customers',
            'SUPPLIER_MANAGE' => 'Manage suppliers',
            'RATE_MANAGE' => 'Manage LPG rates',
            'INVENTORY_MANAGE' => 'Manage inventory',
            'INVENTORY_CONTROLS_MANAGE' => 'Manage inventory controls',
            'GAS_WASTAGE_MANAGE' => 'Manage gas wastage',
            'PURCHASE_MANAGE' => 'Manage purchases',
            'CASH_MANAGE' => 'Manage counter cash',
            'EXPENSE_MANAGE' => 'Manage expenses',
            'REPORT_VIEW' => 'View reports',
            'USER_MANAGE' => 'Manage users',
            'AUDIT_VIEW' => 'View audit log',
            'BACKUP_MANAGE' => 'Create and restore database backups',
            'BRANCH_MANAGE' => 'Manage branches',
            'STOCK_TRANSFER' => 'Transfer stock between branches',
            'REPORT_ALL_BRANCHES' => 'View reports for all branches combined',
            'GAS_DECANT' => 'Move gas between cylinders (decanting)',
            'LOAN_MANAGE' => 'Manage loans taken from lenders',
        ];

        foreach ($permissions as $code => $name) {
            $db->table('permissions')->ignore(true)->insert([
                'code' => $code,
                'name' => $name,
            ]);
        }

        $roleDefaults = [
            'ADMIN' => array_keys($permissions),
            'MANAGER' => array_values(array_diff(array_keys($permissions), ['USER_MANAGE', 'BRANCH_MANAGE', 'REPORT_ALL_BRANCHES'])),
            'CASHIER' => ['DASHBOARD_VIEW', 'POS_SALE', 'POS_HISTORY', 'CUSTOMER_MANAGE', 'REPORT_VIEW'],
        ];

        foreach ($roleDefaults as $roleCode => $permissionCodes) {
            $role = $db->table('roles')->select('id')->where('code', $roleCode)->get()->getRowArray();
            if (!$role) {
                continue;
            }

            foreach ($permissionCodes as $permissionCode) {
                $permission = $db->table('permissions')
                    ->select('id')
                    ->where('code', $permissionCode)
                    ->get()
                    ->getRowArray();

                if (!$permission) {
                    continue;
                }

                $db->table('role_permissions')->ignore(true)->insert([
                    'role_id' => (int) $role['id'],
                    'permission_id' => (int) $permission['id'],
                ]);
            }
        }
    }

    /**
     * Permissions of the role the user holds at the current branch.
     * Before a branch is chosen there are none (the branch picker needs no permission).
     */
    public static function all(): array
    {
        $userId=(int)(session()->get('user_id') ?? 0);
        $locationId=BranchContext::currentId();

        if (self::$permissionCache !== null && self::$permissionCacheUserId === $userId && self::$permissionCacheLocationId === $locationId) {
            return self::$permissionCache;
        }

        self::$permissionCacheUserId = $userId;
        self::$permissionCacheLocationId = $locationId;
        if($userId<=0 || $locationId<=0) {
            return self::$permissionCache=[];
        }

        $roleId=BranchContext::roleIdFor($userId,$locationId);
        if($roleId<=0) {
            return self::$permissionCache=[];
        }

        self::syncStandardRolePermissions();

        self::$permissionCache = array_values(array_unique(array_map(
            'strval',
            array_column(
                Database::connect()->table('role_permissions rp')
                    ->select('permissions.code')
                    ->join('permissions','permissions.id=rp.permission_id')
                    ->where('rp.role_id',$roleId)
                    ->get()
                    ->getResultArray(),
                'code'
            )
        )));

        return self::$permissionCache;
    }

    public static function current(): array
    {
        return array_fill_keys(self::all(), true);
    }

    public static function allows(string $permission): bool
    {
        return isset(self::current()[$permission]);
    }
}
