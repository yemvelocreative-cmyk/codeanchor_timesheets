<?php
use WHMCS\Database\Capsule;

/**
 * Create daily timesheets for assigned users if today is an active cron day.
 * Reads configuration from:
 *  - mod_timekeeper_permissions: setting_key = cron_Monday..cron_Sunday with 'active'/'inactive' (role_id = 0)
 *  - mod_timekeeper_assigned_users: list of admin_id
 *
 * Returns: ['status' => string, 'day' => string, 'created' => int, 'skipped' => int]
 * Possible statuses: ok, disabled_day, no_users, no_active_users, locked, error
 */
function timekeeperRunTimesheetCron(): array
{
    $today   = date('Y-m-d');
    $dayLong = date('l'); // Monday..Sunday

    // Prevent overlapping runs (advisory lock)
    $lockName = 'timekeeper:daily:' . $today;
    $locked   = false;

    try {
        // Acquire lock (0s wait; bail if already running)
        $res = Capsule::select('SELECT GET_LOCK(?, 0) AS l', [$lockName]);
        $locked = $res && isset($res[0]->l) && (int) $res[0]->l === 1;
        if (!$locked) {
            return ['status' => 'locked', 'day' => $dayLong, 'created' => 0, 'skipped' => 0];
        }

        // 1) Is today enabled for cron? (role_id = 0)
        $status = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', 'cron_' . $dayLong)
            ->where('role_id', 0)
            ->value('setting_value');

        if ($status !== 'active') {
            return ['status' => 'disabled_day', 'day' => $dayLong, 'created' => 0, 'skipped' => 0];
        }

        // 2) Assigned users (filter to currently active admins)
        $assignedIds = Capsule::table('mod_timekeeper_assigned_users')->pluck('admin_id')->toArray();
        $assignedIds = array_map('intval', $assignedIds);
        if (empty($assignedIds)) {
            return ['status' => 'no_users', 'day' => $dayLong, 'created' => 0, 'skipped' => 0];
        }

        $activeAdminIds = Capsule::table('tbladmins')
            ->whereIn('id', $assignedIds)
            ->where('disabled', 0)
            ->pluck('id')
            ->toArray();
        $activeAdminIds = array_map('intval', $activeAdminIds);

        if (empty($activeAdminIds)) {
            return ['status' => 'no_active_users', 'day' => $dayLong, 'created' => 0, 'skipped' => 0];
        }

        // 3) Bulk check existing rows for today (avoid N+1)
        $existing = Capsule::table('mod_timekeeper_timesheets')
            ->where('timesheet_date', $today)
            ->whereIn('admin_id', $activeAdminIds)
            ->pluck('admin_id')
            ->toArray();
        $existing = array_map('intval', $existing);

        $existingMap = array_flip($existing);
        $toCreateIds = array_values(array_filter($activeAdminIds, function ($id) use ($existingMap) {
            return !isset($existingMap[$id]);
        }));

        // 4) Bulk insert only missing rows
        $rows = [];
        $now  = date('Y-m-d H:i:s');
        foreach ($toCreateIds as $id) {
            $rows[] = [
                'admin_id'       => (int) $id,
                'timesheet_date' => $today,
                'status'         => 'pending',
                'created_at'     => $now,
            ];
        }
        if (!empty($rows)) {
            Capsule::table('mod_timekeeper_timesheets')->insert($rows);
        }

        return [
            'status'  => 'ok',
            'day'     => $dayLong,
            'created' => count($rows),
            'skipped' => count($existing),
        ];
    } catch (\Throwable $e) {
        // Best-effort activity log entry
        try {
            Capsule::table('tblactivitylog')->insert([
                'date'        => date('Y-m-d H:i:s'),
                'user'        => 0,
                'ipaddr'      => 'CLI',
                'description' => 'Timekeeper cron error: ' . $e->getMessage(),
            ]);
        } catch (\Throwable $ignore) {}

        return ['status' => 'error', 'day' => $dayLong, 'created' => 0, 'skipped' => 0];
    } finally {
        // Always release advisory lock if we acquired it
        if ($locked) {
            try {
                Capsule::select('SELECT RELEASE_LOCK(?)', [$lockName]);
            } catch (\Throwable $ignore) {}
        }
    }
}
