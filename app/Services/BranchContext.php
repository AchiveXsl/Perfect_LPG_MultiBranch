<?php

namespace App\Services;

use Config\Database;
use RuntimeException;

/**
 * The branch the signed-in user is working in, and which branches a user may enter.
 *
 * A user reaches a branch in one of two ways:
 * - users.all_branches = 1 (owner / admin): every active branch, with the user's own role (users.role_id).
 * - a user_branches row: that branch, with the role stored on the row (a user can be Manager at one branch and Cashier at another).
 *
 * The current branch lives in the session as location_id. Every screen and service reads it through currentId(),
 * so a missing or revoked branch is caught in one place.
 */
class BranchContext
{
    /** @var array<int,array>|null */
    protected static ?array $branchCache = null;
    protected static ?int $branchCacheUserId = null;
    protected static ?int $headOfficeId = null;

    public static function currentId(): int
    {
        return (int) (session()->get('location_id') ?? 0);
    }

    /** The current branch id, or an exception when the session has none (no branch chosen yet). */
    public static function requireId(): int
    {
        $id = self::currentId();
        if ($id <= 0) {
            throw new RuntimeException('Select a branch first.');
        }
        return $id;
    }

    public static function current(): ?array
    {
        $id = self::currentId();
        if ($id <= 0) {
            return null;
        }
        return self::find($id);
    }

    public static function find(int $locationId): ?array
    {
        return Database::connect()->table('locations')->where('id', $locationId)->get()->getRowArray() ?: null;
    }

    public static function headOfficeId(): int
    {
        if (self::$headOfficeId !== null) {
            return self::$headOfficeId;
        }
        $db = Database::connect();
        $row = $db->table('locations')->select('id')->where('is_head_office', 1)->orderBy('id')->get(1)->getRowArray()
            ?: $db->table('locations')->select('id')->orderBy('id')->get(1)->getRowArray();
        return self::$headOfficeId = (int) ($row['id'] ?? 0);
    }

    public static function isHeadOffice(?int $locationId = null): bool
    {
        $locationId = $locationId ?? self::currentId();
        return $locationId > 0 && $locationId === self::headOfficeId();
    }

    /**
     * Company purchasing mode, kept in the Head Office shop settings.
     * central: the Head Office purchases for every branch and owns the supplier payables.
     * split:   every branch purchases only its own stock and owes and pays its own suppliers.
     */
    public static function purchaseMode(): string
    {
        try {
            $row = Database::connect()->table('shop_settings')->select('purchase_mode')
                ->where('location_id', self::headOfficeId())->get()->getRowArray();
        } catch (\Throwable $e) {
            return 'central'; // column not migrated yet
        }
        return ($row['purchase_mode'] ?? 'central') === 'split' ? 'split' : 'central';
    }

    /** Can this branch enter purchases and supplier payments? */
    public static function canPurchase(?int $locationId = null): bool
    {
        return self::purchaseMode() === 'split' || self::isHeadOffice($locationId);
    }

    /** Active branches, Head Office first. */
    public static function allActive(): array
    {
        return Database::connect()->table('locations')->where('is_active', 1)
            ->orderBy('is_head_office', 'DESC')->orderBy('name')->get()->getResultArray();
    }

    /**
     * Branches the user may enter, keyed by location id. Each row is the location plus
     * role_id, role_code, role_name and is_default for that user.
     *
     * @return array<int,array>
     */
    public static function branchesFor(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        if (self::$branchCache !== null && self::$branchCacheUserId === $userId) {
            return self::$branchCache;
        }
        $db = Database::connect();
        $user = $db->table('users u')->select('u.id,u.role_id,u.all_branches,u.is_active,r.code role_code,r.name role_name')
            ->join('roles r', 'r.id=u.role_id', 'left')->where('u.id', $userId)->get()->getRowArray();
        $out = [];
        if (!$user || !(int) $user['is_active']) {
            self::$branchCacheUserId = $userId;
            return self::$branchCache = [];
        }
        if ((int) ($user['all_branches'] ?? 0) === 1) {
            $default = (int) ($db->table('user_branches')->select('location_id')->where(['user_id' => $userId, 'is_default' => 1])->get(1)->getRowArray()['location_id'] ?? 0);
            foreach (self::allActive() as $loc) {
                $out[(int) $loc['id']] = $loc + [
                    'role_id' => (int) $user['role_id'],
                    'role_code' => (string) $user['role_code'],
                    'role_name' => (string) $user['role_name'],
                    'is_default' => (int) $loc['id'] === $default ? 1 : 0,
                ];
            }
        } else {
            $rows = $db->table('user_branches ub')
                ->select('l.*,ub.role_id,ub.is_default,r.code role_code,r.name role_name')
                ->join('locations l', 'l.id=ub.location_id')
                ->join('roles r', 'r.id=ub.role_id')
                ->where('ub.user_id', $userId)->where('l.is_active', 1)
                ->orderBy('l.is_head_office', 'DESC')->orderBy('l.name')
                ->get()->getResultArray();
            foreach ($rows as $row) {
                $row['role_id'] = (int) $row['role_id'];
                $out[(int) $row['id']] = $row;
            }
        }
        self::$branchCacheUserId = $userId;
        return self::$branchCache = $out;
    }

    public static function canAccess(int $userId, int $locationId): bool
    {
        return $locationId > 0 && isset(self::branchesFor($userId)[$locationId]);
    }

    /** Role id the user holds at a branch, or 0 when the user cannot enter it. */
    public static function roleIdFor(int $userId, int $locationId): int
    {
        return (int) (self::branchesFor($userId)[$locationId]['role_id'] ?? 0);
    }

    /** Ids of every branch the signed-in user may enter. */
    public static function accessibleIds(): array
    {
        return array_keys(self::branchesFor((int) (session()->get('user_id') ?? 0)));
    }

    /**
     * Switch the session to a branch. Returns false when the user may not enter it.
     * The role shown in the top bar and used for permissions follows the branch.
     */
    public static function enter(int $locationId): bool
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        $branch = self::branchesFor($userId)[$locationId] ?? null;
        if (!$branch) {
            return false;
        }
        session()->set([
            'location_id' => $locationId,
            'role' => $branch['role_code'],
            'role_name' => $branch['role_name'],
        ]);
        PermissionService::reset();
        return true;
    }

    /** Clear per-request caches, e.g. after branch or user assignments change. */
    public static function reset(): void
    {
        self::$branchCache = null;
        self::$branchCacheUserId = null;
        self::$headOfficeId = null;
    }

    /**
     * Branch ids a report may cover. $requested is 'current', 'all' or a list of ids.
     * Anything other than the current branch needs REPORT_ALL_BRANCHES, and only branches the user may enter are kept.
     *
     * @param string|array $requested
     * @return int[]
     */
    public static function reportScope($requested): array
    {
        $current = self::currentId();
        if (!PermissionService::allows('REPORT_ALL_BRANCHES') || $requested === 'current' || $requested === null || $requested === '' || $requested === []) {
            return $current > 0 ? [$current] : [];
        }
        $allowed = self::accessibleIds();
        if ($requested === 'all') {
            return $allowed;
        }
        $ids = array_values(array_intersect(array_map('intval', (array) $requested), $allowed));
        return $ids ?: ($current > 0 ? [$current] : []);
    }
}
