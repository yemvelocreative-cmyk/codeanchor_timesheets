<?php
namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

class ApprovedTimesheetsHelper
{
    /** Roles that can view ALL approved timesheets */
    public static function viewAllRoleIds(): array
    {
        // Try canonical keys; fall back for older installs.
        $keys = [
            'permission_timesheets_view_all',
            'permission_approved_timesheets_view_all',
            'permission_view_all_timesheets',
        ];
        foreach ($keys as $k) {
            $ids = CoreHelper::rolesFromSetting($k);
            if (!empty($ids)) return $ids;
        }
        return [];
    }

    public static function canUnapprove(int $roleId): bool
    {
        return in_array($roleId, self::canUnapproveRoles(), true);
    }

    public static function adminMap(): array
    {
        $rows = Capsule::table('tbladmins')->select(['id', 'firstname', 'lastname'])->get();
        $map = [];
        foreach ($rows as $r) { $map[(int)$r->id] = trim($r->firstname.' '.$r->lastname); }
        return $map;
    }

    public static function clientMap(): array
    {
        $rows = Capsule::table('tblclients')->select(['id','companyname','firstname','lastname'])->get();
        $map = [];
        foreach ($rows as $r) {
            $name = $r->companyname ?: trim($r->firstname.' '.$r->lastname);
            $map[(int)$r->id] = $name ?: 'N/A';
        }
        return $map;
    }

    public static function departmentMap(): array
    {
        $map = [];
        try {
            $rows = \WHMCS\Database\Capsule::table('mod_timekeeper_departments')
                ->select(['id', 'name'])
                ->orderBy('name', 'asc')
                ->get();

            foreach ($rows as $r) {
                $map[(int) $r->id] = (string) ($r->name ?? ('Department ' . (int) $r->id));
            }
        } catch (\Throwable $e) {
            // Graceful: return empty map if table missing/misconfigured.
            return [];
        }
        return $map;
    }

    public static function taskMap(): array
    {
        $map = [];
        try {
            $rows = \WHMCS\Database\Capsule::table('mod_timekeeper_task_categories')
                ->select(['id', 'name'])
                ->orderBy('name', 'asc')
                ->get();

            foreach ($rows as $r) {
                $map[(int) $r->id] = (string) ($r->name ?? ('Task ' . (int) $r->id));
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

    public static function getTimesheetEntries(int $timesheetId)
    {
        return Capsule::table('mod_timekeeper_timesheet_entries')
            ->where('timesheet_id', $timesheetId)
            ->orderBy('start_time', 'asc')
            ->get();
    }

    public static function sumColumn($entries, string $column): float
    {
        $sum = 0.0;
        foreach ($entries as $e) { $sum += (float)($e->{$column} ?? 0); }
        return $sum;
    }

    /** Navigation/menu count for Approved (fixes “5 but there is 8”) */
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
