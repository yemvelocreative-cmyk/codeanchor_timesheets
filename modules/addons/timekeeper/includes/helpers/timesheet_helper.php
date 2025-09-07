<?php
// modules/addons/timekeeper/includes/helpers/timesheet_helper.php
declare(strict_types=1);

namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

final class TimesheetHelper
{
    /**
     * Ensure today's timesheet exists for the given admin.
     * Creates a pending one if not found.
     */
    public static function loadOrCreateTodayTimesheet(int $adminId, string $today): array
    {
        $ts = Capsule::table('mod_timekeeper_timesheets')
            ->where('admin_id', $adminId)
            ->where('timesheet_date', $today)
            ->first();

        if ($ts) {
            return [
                'id'     => (int) $ts->id,
                'status' => (string) $ts->status,
                'date'   => $ts->timesheet_date ?: $today,
            ];
        }

        $now = date('Y-m-d H:i:s');
        $newId = (int) Capsule::table('mod_timekeeper_timesheets')->insertGetId([
            'admin_id'       => $adminId,
            'timesheet_date' => $today,
            'status'         => 'pending',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return [
            'id'     => $newId,
            'status' => 'pending',
            'date'   => $today,
        ];
    }

    /**
     * Load today's timesheet without creating one.
     */
    public static function loadTodayTimesheet(int $adminId, string $today): ?array
    {
        $ts = Capsule::table('mod_timekeeper_timesheets')
            ->where('admin_id', $adminId)
            ->where('timesheet_date', $today)
            ->first();

        if ($ts) {
            return [
                'id'     => (int) $ts->id,
                'status' => (string) $ts->status,
                'date'   => $ts->timesheet_date ?: $today,
            ];
        }
        return null;
    }
}
