<?php
// modules/addons/timekeeper/includes/helpers/pending_timesheet_helper.php
namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

final class PendingTimesheetHelper
{
    /** Roles allowed to view all pending/rejected timesheets */
    public static function viewAllRoles(): array
    {
        return CoreHelper::rolesFromSetting('permission_pending_timesheets_view_all');
    }

    /** Roles allowed to approve/reject */
    public static function approveRoles(): array
    {
        return CoreHelper::rolesFromSetting('permission_pending_timesheets_approve');
    }

    /**
     * Canonical base query for Pending page & menu badge:
     * status IN (pending, rejected) AND timesheet_date < today, with role filter.
     */
    public static function baseQuery(int $adminId, int $roleId)
    {
        $today = date('Y-m-d');

        $q = Capsule::table('mod_timekeeper_timesheets')
            ->whereIn('status', ['pending', 'rejected'])
            ->where('timesheet_date', '<', $today);

        if (!in_array($roleId, self::viewAllRoles(), true)) {
            $q->where('admin_id', $adminId);
        }
        return $q; // Illuminate\Database\Query\Builder
    }

    /** Badge count identical to the page logic */
    public static function menuCount(int $adminId, int $roleId): int
    {
        return (int) self::baseQuery($adminId, $roleId)->count();
    }

    /** Entries sorted: start ASC, then end ASC, blanks last, stable by id */
    public static function entriesSorted(int $timesheetId)
    {
        return Capsule::table('mod_timekeeper_timesheet_entries')
            ->where('timesheet_id', $timesheetId)
            ->orderByRaw("CASE WHEN start_time IS NULL OR start_time = '' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("STR_TO_DATE(start_time, '%H:%i') ASC")
            ->orderByRaw("CASE WHEN end_time IS NULL OR end_time = '' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("STR_TO_DATE(end_time,  '%H:%i') ASC")
            ->orderBy('id', 'asc')
            ->get();
    }
}
