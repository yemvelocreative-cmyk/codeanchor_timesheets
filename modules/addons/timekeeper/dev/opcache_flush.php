<?php
// modules/addons/timekeeper/dev/opcache_flush.php
// TEMP DEV TOOL — delete after use!

@session_start();

// Require WHMCS admin session for safety
require_once dirname(__DIR__, 3) . '/init.php';
if (empty($_SESSION['adminid'])) {
    http_response_code(403);
    exit('Forbidden');
}

if (!function_exists('opcache_reset')) {
    exit("OPcache not enabled.\n");
}

// If you prefer to invalidate only certain files, list them here:
$files = [
    realpath(dirname(__DIR__) . '/pages/settings.php'),
    realpath(dirname(__DIR__) . '/templates/settings.tpl'),
    realpath(dirname(__DIR__) . '/templates/components/settings_cron.tpl'),
    realpath(dirname(__DIR__) . '/templates/components/settings_approvals.tpl'),
    realpath(dirname(__DIR__) . '/templates/components/settings_hide_tabs.tpl'),
    realpath(dirname(__DIR__) . '/components/cron_daily_timesheet.php'),
    realpath(dirname(__DIR__) . '/cron/cron.php'),
    realpath(dirname(__DIR__) . '/css/settings.css'),
    realpath(dirname(__DIR__) . '/css/settings_tabs.css'),
    realpath(dirname(__DIR__) . '/css/settings_hide_tabs.css'),
    realpath(dirname(__DIR__) . '/js/settings.js'),
];

// Invalidate just these (safer than full reset)
$invalidated = 0;
foreach ($files as $f) {
    if ($f && file_exists($f) && function_exists('opcache_invalidate')) {
        if (opcache_invalidate($f, /*force*/true)) {
            $invalidated++;
        }
    }
}

if ($invalidated === 0) {
    // Fallback: full reset
    opcache_reset();
    echo "OPcache reset.\n";
} else {
    echo "Invalidated {$invalidated} file(s).\n";
}
