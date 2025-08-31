<?php
// modules/addons/timekeeper/includes/helper_pending_timesheets.php
use WHMCS\Database\Capsule;

/** Parse a CSV of ints into an array<int> */
if (!function_exists('tk_parse_id_list')) {
    function tk_parse_id_list(?string $csv): array {
        if (!$csv) return [];
        $out = [];
        foreach (explode(',', $csv) as $p) {
            $v = (int) trim($p);
            if ($v > 0) $out[] = $v;
        }
        return $out;
    }
}

/** Safe “has column” check that works across MySQL modes */
if (!function_exists('tk_has_col')) {
    function tk_has_col(string $table, string $col): bool {
        try {
            return Capsule::schema()->hasColumn($table, $col);
        } catch (\Throwable $e) {
            try {
                $cols = Capsule::select("SHOW COLUMNS FROM `$table`");
                foreach ($cols as $c) {
                    $f = is_object($c) ? ($c->Field ?? null) : ($c['Field'] ?? null);
                    if ($f === $col) return true;
                }
            } catch (\Throwable $e2) {}
            return false;
        }
    }
}

/** Which roles can “view all” pending/rejected? */
if (!function_exists('tk_pending_view_all_roles')) {
    function tk_pending_view_all_roles(): array {
        $allowedViewCsv = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', 'permission_pending_timesheets_view_all')
            ->value('setting_value');
        return tk_parse_id_list($allowedViewCsv);
    }
}

/**
 * Canonical base query for pending-timesheets listing/badge.
 * Matches the page logic: status IN (pending,rejected) AND timesheet_date < today,
 * filtered by role (view-all vs own only).
 */
if (!function_exists('tk_pending_timesheets_base_query')) {
    function tk_pending_timesheets_base_query(int $adminId, int $adminRoleId) {
        $allowedViewRoles = tk_pending_view_all_roles();
        $today = date('Y-m-d');

        $q = Capsule::table('mod_timekeeper_timesheets')
            ->whereIn('status', ['pending', 'rejected'])
            ->where('timesheet_date', '<', $today);

        if (!in_array($adminRoleId, $allowedViewRoles, true)) {
            $q->where('admin_id', $adminId);
        }
        return $q; // Illuminate\Database\Query\Builder
    }
}

/** Badge count that exactly matches the page */
if (!function_exists('tk_menu_pending_count')) {
    function tk_menu_pending_count(int $adminId, int $adminRoleId): int {
        return (int) tk_pending_timesheets_base_query($adminId, $adminRoleId)->count();
    }
}

/** Entries sorted by start_time ASC, then end_time ASC (blanks last), then id ASC */
if (!function_exists('tk_entries_sorted')) {
    function tk_entries_sorted(int $timesheetId) {
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
