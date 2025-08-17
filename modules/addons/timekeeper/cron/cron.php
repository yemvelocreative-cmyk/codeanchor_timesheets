<?php
// modules/addons/timekeeper/cron/cron.php

// If you plan to run this directly: #!/usr/bin/php -q

// --- Bootstrap WHMCS ---
require_once dirname(__DIR__, 4) . '/init.php'; // .../modules/addons/timekeeper/cron -> up 4 => WHMCS root

// Optional: align to your local timezone if desired
@date_default_timezone_set('Africa/Johannesburg');

// --- Load the cron worker ---
require_once __DIR__ . '/../components/cron_daily_timesheet.php';

// --- Run ---
$res = timekeeperRunTimesheetCron(); // ['status','day','created','skipped']

// --- Output (CLI or verbose) ---
$isCli   = (PHP_SAPI === 'cli');
$argvArr = $_SERVER['argv'] ?? [];
$verbose = $isCli ? in_array('--verbose', $argvArr, true) : isset($_GET['verbose']);

if ($isCli || $verbose) {
    echo "[Timekeeper] {$res['status']} on {$res['day']} - created: {$res['created']}, skipped: {$res['skipped']}\n";
}

// --- Exit code for CI/monitoring (CLI only) ---
if ($isCli) {
    $okStatuses = ['ok', 'disabled_day', 'no_users', 'no_active_users', 'locked'];
    exit(in_array($res['status'], $okStatuses, true) ? 0 : 2);
}
