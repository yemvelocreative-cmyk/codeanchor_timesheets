<?php
namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

class ApprovedTimesheetsHelper
{
    /**
     * Collect role IDs from mod_timekeeper_permissions for a given key.
     * Supports:
     *  - multiple rows (role_id populated per row)
     *  - setting_value as a single number, CSV, or JSON array
     */
    protected static function roleIdsFromPermissions(string $settingKey): array
    {
        $ids = [];

        try {
            $rows = Capsule::table('mod_timekeeper_permissions')
                ->where('setting_key', $settingKey)
                ->get();

            foreach ($rows as $row) {
                // Source A: dedicated role_id column (nullable)
                if (isset($row->role_id) && $row->role_id !== null) {
                    $ids[] = (int) $row->role_id;
                }

                // Source B: setting_value may be single, CSV, or JSON
                $val = (string) ($row->setting_value ?? '');
                if ($val !== '') {
                    // Try JSON first
                    $decoded = json_decode($val, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $v) {
                            if (is_numeric($v)) { $ids[] = (int) $v; }
                        }
                    } else {
                        // CSV or single
                        $ids = array_merge($ids, CoreHelper::parseIdList($val));
                        if (empty($ids) && is_numeric($val)) {
                            $ids[] = (int) $val;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through with whatever we collected
        }

        // Normalise
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($n) => $n > 0)));
        return $ids;
    }

    /** Roles that can view ALL approved timesheets (from Settings) */
    public static function viewAllRoleIds(): array
    {
        // Per your spec, use the *pending* key for approved view-all
        return self::roleIdsFromPermissions('permission_pending_timesheets_view_all');
    }

    /** Roles that can Approve / Unapprove timesheets (from Settings) */
    public static function canUnapproveRoles(): array
    {
        // Per your spec, use this exact key
        return self::roleIdsFromPermissions('permission_pending_timesheets_approve');
    }

    /** Check if a given role can unapprove */
    public static function canUnapprove(int $roleId): bool
    {
        return in_array($roleId, self::canUnapproveRoles(), true);
    }

    /** Admin map: id => "Firstname Lastname" */
    public static function adminMap(): array
    {
        $rows = Capsule::table('tbladmins')->select([
'id', 'firstname', 'lastname', Capsule::raw("COALESCE(tse.notes, tse.description, '') AS notes") ]
])->get();
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->id] = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')) ?: ('Admin ' . (int)$r->id);
        }
        return $map;
    }

    /** Client map: id => company or fullname */
    public static function clientMap(): array
    {
        $rows = Capsule::table('tblclients')->select([
'id','companyname','firstname','lastname', Capsule::raw("COALESCE(tse.notes, tse.description, '') AS notes") ]
])->get();
        $map = [];
        foreach ($rows as $r) {
            $name = $r->companyname ?: trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
            $map[(int)$r->id] = $name ?: ('Client ' . (int)$r->id);
        }
        return $map;
    }

    /** Department map (canonical table) */
    public static function departmentMap(): array
    {
        $map = [];
        try {
            $rows = Capsule::table('mod_timekeeper_departments')
                ->select([
'id', 'name', Capsule::raw("COALESCE(tse.notes, tse.description, '') AS notes") ]
])
                ->orderBy('name', 'asc')
                ->get();

            foreach ($rows as $r) {
                $map[(int)$r->id] = (string) ($r->name ?? ('Department ' . (int)$r->id));
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $map;
    }

    /** Task category map (canonical table) */
    public static function taskMap(): array
    {
        $map = [];
        try {
            $rows = Capsule::table('mod_timekeeper_task_categories')
                ->select([
'id', 'name', Capsule::raw("COALESCE(tse.notes, tse.description, '') AS notes") ]
])
                ->orderBy('name', 'asc')
                ->get();

            foreach ($rows as $r) {
                $map[(int)$r->id] = (string) ($r->name ?? ('Task ' . (int)$r->id));
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $map;
    }

    /** List approved timesheets visible to the current admin/role */
    public static function listVisibleApproved(int $viewerAdminId, int $viewerRoleId, array $viewAllRoleIds)
    {
        $q = Capsule::table('mod_timekeeper_timesheets')
            ->where('status', 'approved')
            ->orderBy('timesheet_date', 'desc')
            ->orderBy('admin_id', 'asc');

        if (!in_array($viewerRoleId, $viewAllRoleIds, true)) {
            $q->where('admin_id', $viewerAdminId);
        }
        return $q->get();
    }

    /** Fetch a single approved timesheet if visible to current admin/role */
    public static function getApprovedTimesheet(
        int $tsAdminId,
        string $date,
        int $viewerAdminId,
        int $viewerRoleId,
        array $viewAllRoleIds
    ) {
        // If viewer is NOT view-all and is not the owner, deny
        if (!in_array($viewerRoleId, $viewAllRoleIds, true) && $tsAdminId !== $viewerAdminId) {
            return null;
        }

        return Capsule::table('mod_timekeeper_timesheets')
            ->where('admin_id', $tsAdminId)
            ->where('timesheet_date', $date)
            ->where('status', 'approved')
            ->first();
    }

    /** Entries for a given timesheet */
    public static function getTimesheetEntries(int $timesheetId)
    {
        return Capsule::table('mod_timekeeper_timesheet_entries')
            ->where('timesheet_id', $timesheetId)
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /** Sum a numeric column from an iterable of row objects */
    public static function sumColumn($entries, string $column): float
    {
        $sum = 0.0;
        if (is_iterable($entries)) {
            foreach ($entries as $e) {
                $sum += (float) ($e->{$column} ?? 0);
            }
        }
        return $sum;
    }

    /** Navigation/menu count for Approved (respects view-all) */
    public static function menuCount(int $adminId, int $roleId): int
    {
        $viewAllRoleIds = self::viewAllRoleIds();
        $q = Capsule::table('mod_timekeeper_timesheets')->where('status', 'approved');
        if (!in_array($roleId, $viewAllRoleIds, true)) {
            $q->where('admin_id', $adminId);
        }
        return (int) $q->count();
    }
}
