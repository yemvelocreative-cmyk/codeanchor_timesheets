<?php
// modules/addons/timekeeper/includes/helpers/dashboard_helper.php
declare(strict_types=1);

namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

final class DashboardHelper
{
    /** Resolve permissions for current admin */
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

    /** KPI cards */
    public static function kpis(string $from, string $to, int $currentAdminId, bool $canViewAll, string $today): array
    {
        $baseTs = Capsule::table('mod_timekeeper_timesheets')->whereBetween('timesheet_date', [$from, $to]);
        if (!$canViewAll) $baseTs->where('admin_id', $currentAdminId);

        $kpi = [];
        $kpi['pending']  = (clone $baseTs)->where('status', 'pending')->count();
        $kpi['approved'] = (clone $baseTs)->where('status', 'approved')->count();
        $kpi['rejected'] = (clone $baseTs)->where('status', 'rejected')->count();

        $totalsQ = Capsule::table('mod_timekeeper_timesheet_entries AS e')
            ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
            ->whereBetween('t.timesheet_date', [$from, $to]);

        if (!$canViewAll) $totalsQ->where('t.admin_id', $currentAdminId);

        $totals = $totalsQ->selectRaw(
            'ROUND(SUM(e.time_spent),2) AS total, ROUND(SUM(e.billable_time),2) AS billable, ROUND(SUM(e.sla_time),2) AS sla'
        )->first();

        $kpi['total_hours']    = (float)($totals->total ?? 0);
        $kpi['billable_hours'] = (float)($totals->billable ?? 0);
        $kpi['sla_hours']      = (float)($totals->sla ?? 0);

        // Missing Today (team-wide)
        $kpi['missing_today'] = 0;
        if ($canViewAll) {
            $assignedAdminIds = Capsule::table('mod_timekeeper_permissions')
                ->where('setting_key','cron_assigned_users_json')
                ->value('setting_value');
            $assigned = $assignedAdminIds ? (json_decode($assignedAdminIds, true) ?: []) : [];
            $assigned = array_map('intval', $assigned);

            $haveTsToday = Capsule::table('mod_timekeeper_timesheets')
                ->where('timesheet_date', $today)
                ->pluck('admin_id')->toArray();
            $haveTsToday = array_map('intval', $haveTsToday);

            if (!empty($assigned)) {
                $kpi['missing_today'] = max(0, count(array_diff($assigned, $haveTsToday)));
            }
        }

        return $kpi;
    }

    /** My Today snapshot (status + totals) */
    public static function myToday(int $currentAdminId, string $today): array
    {
        $ts = Capsule::table('mod_timekeeper_timesheets')
            ->where('admin_id', $currentAdminId)
            ->where('timesheet_date', $today)
            ->first();

        $status = $ts->status ?? 'missing';

        $tot = ['total'=>0.0,'billable'=>0.0,'sla'=>0.0];
        if ($ts) {
            $row = Capsule::table('mod_timekeeper_timesheet_entries')
                ->where('timesheet_id', $ts->id)
                ->selectRaw('ROUND(SUM(time_spent),2) AS total, ROUND(SUM(billable_time),2) AS billable, ROUND(SUM(sla_time),2) AS sla')
                ->first();
            $tot['total']    = (float)($row->total ?? 0);
            $tot['billable'] = (float)($row->billable ?? 0);
            $tot['sla']      = (float)($row->sla ?? 0);
        }

        return [$status, $tot];
    }

    /** Approvals queue (paged) */
    public static function approvalsQueue(int $page, int $perPage, ?int $ageMinDays = null): array
    {
        $q = Capsule::table('mod_timekeeper_timesheets AS t')
            ->join('tbladmins AS a','t.admin_id','=','a.id')
            ->where('t.status','pending');

        if ($ageMinDays !== null && $ageMinDays > 0) {
            $q->whereRaw('DATEDIFF(CURDATE(), t.timesheet_date) >= ?', [$ageMinDays]);
        }

        $total = (clone $q)->count();
        $rows = $q->orderBy('t.timesheet_date','desc')
            ->offset(max(0, ($page-1)*$perPage))
            ->limit($perPage)
            ->get(['t.id','t.timesheet_date','t.status',
                   Capsule::raw("CONCAT(a.firstname,' ',a.lastname) AS admin_name")]);

        $pendingTotals = [];
        if (count($rows)) {
            $ids = array_map(fn($r)=>$r->id, $rows->toArray());
            $agg = Capsule::table('mod_timekeeper_timesheet_entries')
                ->whereIn('timesheet_id',$ids)
                ->selectRaw('timesheet_id, ROUND(SUM(time_spent),2) AS total, ROUND(SUM(billable_time),2) AS billable, ROUND(SUM(sla_time),2) AS sla')
                ->groupBy('timesheet_id')->get();
            foreach ($agg as $r) { $pendingTotals[$r->timesheet_id] = $r; }
        }

        return [$rows, $pendingTotals, $total];
    }

    /** Aging buckets for pendings (e.g., >2d, >5d) */
    public static function agingBuckets(array $thresholds = [2,5]): array
    {
        $data = [];
        foreach ($thresholds as $d) {
            $c = Capsule::table('mod_timekeeper_timesheets')
                ->where('status', 'pending')
                ->whereRaw('DATEDIFF(CURDATE(), timesheet_date) >= ?', [$d])
                ->count();
            $data[(int)$d] = (int)$c;
        }
        return $data;
    }

    /** Time by Department */
    public static function timeByDept(string $from, string $to, int $currentAdminId, bool $canViewAll)
    {
        $q = Capsule::table('mod_timekeeper_timesheet_entries AS e')
            ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
            ->join('mod_timekeeper_departments AS d','e.department_id','=','d.id')
            ->whereBetween('t.timesheet_date', [$from, $to]);

        if (!$canViewAll) $q->where('t.admin_id', $currentAdminId);

        return $q->groupBy('d.id','d.name')
                 ->orderBy('d.name')
                 ->selectRaw("d.name AS dept, ROUND(SUM(e.time_spent),2) AS total, ROUND(SUM(e.billable_time),2) AS billable, ROUND(SUM(e.sla_time),2) AS sla")
                 ->get();
    }

    /** Top Clients/Projects (auto-detect column presence) */
    public static function topClientsProjects(string $from, string $to, int $currentAdminId, bool $canViewAll, int $limit = 10)
    {
        // detect columns (works even if schema varies)
        $hasClient = \Timekeeper\Helpers\CoreHelper::hasCol('mod_timekeeper_timesheet_entries','client_id');
        $hasProj   = \Timekeeper\Helpers\CoreHelper::hasCol('mod_timekeeper_timesheet_entries','project_id');

        // prefer client; fallback to project; else aggregate by admin
        if ($hasClient) {
            $q = Capsule::table('mod_timekeeper_timesheet_entries AS e')
                ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
                ->leftJoin('tblclients AS c', 'e.client_id', '=', 'c.id')
                ->whereBetween('t.timesheet_date', [$from, $to]);
            if (!$canViewAll) $q->where('t.admin_id', $currentAdminId);

            return $q->groupBy('c.id','c.companyname')
                     ->orderByRaw('SUM(e.time_spent) DESC')
                     ->limit($limit)
                     ->selectRaw("COALESCE(c.companyname,'Unassigned') AS label,
                                  ROUND(SUM(e.time_spent),2) AS total,
                                  ROUND(SUM(e.billable_time),2) AS billable,
                                  ROUND(SUM(e.sla_time),2) AS sla")
                     ->get();
        }

        if ($hasProj) {
            $q = Capsule::table('mod_timekeeper_timesheet_entries AS e')
                ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
                ->whereBetween('t.timesheet_date', [$from, $to]);
            if (!$canViewAll) $q->where('t.admin_id', $currentAdminId);

            return $q->groupBy('e.project_id')
                     ->orderByRaw('SUM(e.time_spent) DESC')
                     ->limit($limit)
                     ->selectRaw("CONCAT('Project #', e.project_id) AS label,
                                  ROUND(SUM(e.time_spent),2) AS total,
                                  ROUND(SUM(e.billable_time),2) AS billable,
                                  ROUND(SUM(e.sla_time),2) AS sla")
                     ->get();
        }

        // fallback by admin (who we're working for most)
        $q = Capsule::table('mod_timekeeper_timesheet_entries AS e')
            ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
            ->join('tbladmins AS a','t.admin_id','=','a.id')
            ->whereBetween('t.timesheet_date', [$from, $to]);
        if (!$canViewAll) $q->where('t.admin_id', $currentAdminId);

        return $q->groupBy('t.admin_id','a.firstname','a.lastname')
                 ->orderByRaw('SUM(e.time_spent) DESC')
                 ->limit($limit)
                 ->selectRaw("CONCAT(a.firstname,' ',a.lastname) AS label,
                              ROUND(SUM(e.time_spent),2) AS total,
                              ROUND(SUM(e.billable_time),2) AS billable,
                              ROUND(SUM(e.sla_time),2) AS sla")
                 ->get();
    }

    /** Recent activity */
    public static function recentActivity(int $limit, int $currentAdminId, bool $canViewAll)
    {
        $q = Capsule::table('mod_timekeeper_timesheet_entries AS e')
            ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
            ->join('tbladmins AS a','t.admin_id','=','a.id')
            ->orderBy('e.updated_at','desc')
            ->limit($limit);
        if (!$canViewAll) $q->where('t.admin_id', $currentAdminId);

        return $q->get([
            't.timesheet_date',
            Capsule::raw("CONCAT(a.firstname,' ',a.lastname) AS admin_name"),
            'e.description', 'e.updated_at'
        ]);
    }

    /** Alerts: rejected in range, entries under minimum task time (if configured) */
    public static function alerts(string $from, string $to, int $currentAdminId, bool $canViewAll): array
    {
        // Rejected sheets in range
        $rejQ = Capsule::table('mod_timekeeper_timesheets')
            ->whereBetween('timesheet_date', [$from, $to])
            ->where('status','rejected');
        if (!$canViewAll) $rejQ->where('admin_id', $currentAdminId);
        $rejectedCount = (int)$rejQ->count();

        // --- Minimum task time: read from settings OR fallback to permissions ---
        $minVal = 0.0;

        try {
            $schema = Capsule::schema();

            // Prefer mod_timekeeper_settings if the table exists
            if ($schema->hasTable('mod_timekeeper_settings')) {
                $raw = Capsule::table('mod_timekeeper_settings')
                    ->where('setting_key','unbilled_time_validate_min')
                    ->value('setting_value');
                if (is_numeric($raw)) {
                    $minVal = (float)$raw;
                }
            }
            // Fallback: mod_timekeeper_permissions (keeps older installs working)
            elseif ($schema->hasTable('mod_timekeeper_permissions')) {
                $raw = Capsule::table('mod_timekeeper_permissions')
                    ->where('setting_key','unbilled_time_validate_min')
                    ->value('setting_value');
                if (is_numeric($raw)) {
                    $minVal = (float)$raw;
                }
            }
        } catch (\Throwable $e) {
            // If anything goes wrong, leave $minVal = 0.0 (disabled) and continue
        }

        // Count entries under minimum, only if a min value is actually configured
        $underMin = 0;
        if ($minVal > 0) {
            $umQ = Capsule::table('mod_timekeeper_timesheet_entries AS e')
                ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
                ->whereBetween('t.timesheet_date', [$from, $to])
                ->where('e.time_spent','>',0)
                ->where('e.time_spent','<',$minVal);
            if (!$canViewAll) $umQ->where('t.admin_id', $currentAdminId);
            $underMin = (int)$umQ->count();
        }

        return [$rejectedCount, $minVal, $underMin];
    }

    /** Tiny trend series: billable/sla by day; pending count by day */
    public static function trendSeries(string $from, string $to, int $currentAdminId, bool $canViewAll): array
    {
        // Range days
        $days = [];
        $d = strtotime($from);
        $end = strtotime($to);
        while ($d <= $end) {
            $days[date('Y-m-d',$d)] = ['billable'=>0.0,'sla'=>0.0,'pending'=>0];
            $d = strtotime('+1 day', $d);
        }

        // Billable/SLA by day
        $q = Capsule::table('mod_timekeeper_timesheet_entries AS e')
            ->join('mod_timekeeper_timesheets AS t','e.timesheet_id','=','t.id')
            ->whereBetween('t.timesheet_date', [$from, $to]);
        if (!$canViewAll) $q->where('t.admin_id', $currentAdminId);

        $agg = $q->groupBy('t.timesheet_date')
                 ->selectRaw("t.timesheet_date AS d, ROUND(SUM(e.billable_time),2) AS billable, ROUND(SUM(e.sla_time),2) AS sla")
                 ->get();
        foreach ($agg as $r) {
            $key = $r->d;
            if (isset($days[$key])) {
                $days[$key]['billable'] = (float)$r->billable;
                $days[$key]['sla']      = (float)$r->sla;
            }
        }

        // Pending by day
        $p = Capsule::table('mod_timekeeper_timesheets')
            ->whereBetween('timesheet_date', [$from, $to])
            ->where('status','pending');
        if (!$canViewAll) $p->where('admin_id', $currentAdminId);

        $pend = $p->groupBy('timesheet_date')
                  ->selectRaw('timesheet_date AS d, COUNT(*) AS c')->get();
        foreach ($pend as $r) {
            $key = $r->d;
            if (isset($days[$key])) $days[$key]['pending'] = (int)$r->c;
        }

        // Flatten series in order
        $labels = array_keys($days);
        $billable = array_map(fn($k)=>$days[$k]['billable'], $labels);
        $sla      = array_map(fn($k)=>$days[$k]['sla'], $labels);
        $pending  = array_map(fn($k)=>$days[$k]['pending'], $labels);

        return [$labels, $billable, $sla, $pending];
    }
}
