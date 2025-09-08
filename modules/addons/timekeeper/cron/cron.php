<?php


// ---- Dynamic base URL + asset helper (polyfill if not in core_helper yet) ----
// Preferred helpers (if present in core_helper.php):
//   \Timekeeper\Helpers\timekeeperBaseUrl(): string
//   \Timekeeper\Helpers\timekeeperAsset(string $relPath): string
if (!function_exists('Timekeeper\\Helpers\\timekeeperBaseUrl') || !function_exists('Timekeeper\\Helpers\\timekeeperAsset')) {
    // Local polyfill
    $tkSystemUrl = (function (): string {
        try {
            $ssl = (string) \WHMCS\Config\Setting::getValue('SystemSSLURL');
            $url = $ssl !== '' ? $ssl : (string) \WHMCS\Config\Setting::getValue('SystemURL');
            return rtrim($url, '/');
        } catch (\Throwable $e) {
            return '';
        }
    })();

    $tkBase = ($tkSystemUrl !== '' ? $tkSystemUrl : '') . '/modules/addons/timekeeper';
    $tkBase = rtrim($tkBase, '/');

    // Callable for cache-busted assets
    $tkAsset = function (string $relPath) use ($tkBase, $base): string {
        $rel = ltrim($relPath, '/');
        $url = $tkBase . '/' . $rel;

        $file = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (@is_file($file)) {
            $ver = @filemtime($file);
            if ($ver) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . 'v=' . $ver;
            }
        }
        return $url;
    };
} else {
    $tkBase  = \Timekeeper\Helpers\timekeeperBaseUrl();
    $tkAsset = '\Timekeeper\Helpers\timekeeperAsset';
}

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
