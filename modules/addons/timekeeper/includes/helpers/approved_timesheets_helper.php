<?php
namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

class ApprovedTimesheetsHelper
{
    /** Roles that can view ALL approved timesheets */
    public static function viewAllRoleIds(): array
    {
        // Setting key dedicated to APPROVED visibility
        $saved = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', 'permission_approved_timesheets_view_all')
            ->value('setting_value');

        if (!$saved) return [];
        return array_values(array_filter(array_map('intval', explode(',', $saved))));
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
        $rows = Capsule::table('mod_timekeeper_task_departments')->select(['id','name'])->get();
        $map = [];
        foreach ($rows as $r) { $map[(int)$r->id] = (string)$r->name; }
        return $map;
    }

    public static function taskMap(): array
    {
        $rows = Capsule::table('mod_timekeeper_task_categories')->select(['id','name'])->get();
        $map = [];
        foreach ($rows as $r) { $map[(int)$r->id] = (string)$r->name; }
        return $map;
    }

    /** List approved timesheets visible to the current admin/role */
    public static function listVisibleApproved(int $adminId, int $roleId, array $viewAllRoleIds)
    {
        $q = Capsule::table('mod_timekeeper_timesheets')
            ->where('status', 'approved')
            ->orderBy('timesheet_date', 'desc')
            ->orderBy('admin_id', 'asc');

        if (!in_array($roleId, $viewAllRoleIds, true)) {
            $q->where('admin_id', $adminId);
        }
        return $q->get();
    }

    /** Fetch a single approved timesheet if visible to current admin/role */
    public static function getApprovedTimesheet(int $tsAdminId, string $date, int $viewerAdminId, int $viewerRoleId, array $viewAllRoleIds)
    {
        $q = Capsule::table('mod_timekeeper_timesheets')
            ->where('admin_id', $tsAdminId)
            ->where('timesheet_date', $date)
            ->where('status', 'approved');

        if (!in_array($viewerRoleId, $viewAllRoleIds, true)) {
            $q->where('admin_id', $viewerAdminId);
        }
        return $q->first();
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
