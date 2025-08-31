<?php
// modules/addons/timekeeper/includes/helpers/dashboard_helper.php
declare(strict_types=1);

namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

final class DashboardHelper
{
    /**
     * Determine permissions for the current admin.
     * - If view_all_admin_ids_json is empty, default allow-all (keeps legacy behavior sane).
     * - Approvals allowed only if approval_admin_ids_json contains current admin.
     */
    public static function resolvePermissions(int $currentAdminId): array
    {
        $perm = Capsule::table('mod_timekeeper_permissions')
            ->whereIn('setting_key', ['view_all_admin_ids_json', 'approval_admin_ids_json'])
            ->pluck('setting_value', 'setting_key');

        $viewAllIds = [];
        $approveIds = [];

        if (isset($perm['view_all_admin_ids_json'])) {
            $decoded = json_decode($perm['view_all_admin_ids_json'], true);
            if (is_array($decoded)) $viewAllIds = array_map('intval', $decoded);
        }
        if (isset($perm['approval_admin_ids_json'])) {
            $decoded = json_decode($perm['approval_admin_ids_json'], true);
            if (is_array($decoded)) $approveIds = array_map('intval', $decoded);
        }

        $canViewAll = empty($viewAllIds) ? true : in_array($currentAdminId, $viewAllIds, true);
        $canApprove = !empty($approveIds) && in_array($currentAdminId, $approveIds, true);

        return [$canViewAll, $canApprove];
    }

    /** KPIs for the header cards. Respects view permissions. */
    public static function kpis(string $from, string $to, int $currentAdminId, bool $canViewAll, string $today): array
    {
        $baseTs = Capsule::table('mod_timekeeper_timesheets')->whereBetween('timesheet_date', [$from, $to]);
        if (!$canViewAll) {
            $baseTs->where('admin_id', $currentAdminId);
        }

        $kpi = [];
        $kpi['pending']  = (clone $baseTs)->where('status', 'pending')->count();
        $kpi['approved'] = (clone $baseTs)->where('status', 'approved')->count();
        $kpi['rejected'] = (clone $baseTs)->where('status', 'rejected')->count();

        $totalsQ = Capsule::table('mod_timekeeper_timesheet_entries AS e')
            ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
            ->whereBetween('t.timesheet_date', [$from, $to]);

        if (!$canViewAll) {
            $totalsQ->where('t.admin_id', $currentAdminId);
        }

        $totals = $totalsQ->selectRaw('ROUND(SUM(e.billable_time),2) AS billable, ROUND(SUM(e.sla_time),2) AS sla')->first();
        $kpi['billable_hours'] = (float) ($totals->billable ?? 0);
        $kpi['sla_hours']      = (float) ($totals->sla ?? 0);

        // Missing Today (team-wide) only for view-all users.
        $kpi['missing_today'] = 0;
        if ($canViewAll) {
            $assignedAdminIds = Capsule::table('mod_timekeeper_permissions')
                ->where('setting_key','cron_assigned_users_json')
                ->value('setting_value');
            $assigned = $assignedAdminIds ? (json_decode($assignedAdminIds, true) ?: []) : [];
            $assigned = array_map('intval', $assigned);

            $haveTsToday = Capsule::table('mod_timekeeper_timesheets')
                ->where('timesheet_date', $today)
                ->pluck('admin_id')
                ->toArray();
            $haveTsToday = array_map('intval', $haveTsToday);

            if (!empty($assigned)) {
                $kpi['missing_today'] = max(0, count(array_diff($assigned, $haveTsToday)));
            }
        }

        return $kpi;
    }

    /** Approvals queue for approvers (latest 10). */
    public static function approvalsQueue(): array
    {
        $pending = Capsule::table('mod_timekeeper_timesheets AS t')
            ->join('tbladmins AS a','t.admin_id','=','a.id')
            ->where('t.status','pending')
            ->orderBy('t.timesheet_date','desc')
            ->limit(10)
            ->get(['t.id','t.timesheet_date','t.status', Capsule::raw("CONCAT(a.firstname,' ',a.lastname) AS admin_name")]);

        $pendingTotals = [];
        if (count($pending)) {
            $ids = array_map(fn($r)=>$r->id, $pending->toArray());
            $rows = Capsule::table('mod_timekeeper_timesheet_entries')
                ->whereIn('timesheet_id',$ids)
                ->selectRaw('timesheet_id, ROUND(SUM(time_spent),2) AS total, ROUND(SUM(billable_time),2) AS billable, ROUND(SUM(sla_time),2) AS sla')
                ->groupBy('timesheet_id')->get();
            foreach ($rows as $r) { $pendingTotals[$r->timesheet_id] = $r; }
        }

        return [$pending, $pendingTotals];
    }

    /** Time by Department (respects view perms). */
    public static function timeByDept(string $from, string $to, int $currentAdminId, bool $canViewAll)
    {
        $q = Capsule::table('mod_timekeeper_timesheet_entries AS e')
            ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
            ->join('mod_timekeeper_departments AS d','e.department_id','=','d.id')
            ->whereBetween('t.timesheet_date', [$from, $to]);

        if (!$canViewAll) {
            $q->where('t.admin_id', $currentAdminId);
        }

        return $q->groupBy('d.id','d.name')
                 ->orderBy('d.name')
                 ->selectRaw("d.name AS dept, ROUND(SUM(e.time_spent),2) AS total, ROUND(SUM(e.billable_time),2) AS billable, ROUND(SUM(e.sla_time),2) AS sla")
                 ->get();
    }

    /** Recent activity (latest 10, respects view perms). */
    public static function recentActivity(int $limit, int $currentAdminId, bool $canViewAll)
    {
        $q = Capsule::table('mod_timekeeper_timesheet_entries AS e')
            ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
            ->join('tbladmins AS a','t.admin_id','=','a.id')
            ->orderBy('e.updated_at','desc')
            ->limit($limit);

        if (!$canViewAll) {
            $q->where('t.admin_id', $currentAdminId);
        }

        return $q->get([
            't.timesheet_date',
            Capsule::raw("CONCAT(a.firstname,' ',a.lastname) AS admin_name"),
            'e.description', 'e.updated_at'
        ]);
    }
}
