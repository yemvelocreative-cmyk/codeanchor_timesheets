<?php
// TEMP DEV TOOL — delete after use!
// Run via browser while logged into WHMCS admin.
// URL: /modules/addons/timekeeper/dev/opcache_flush.php

ini_set('display_errors', '1');
error_reporting(E_ALL);

@session_start();

// --- Resolve paths robustly ---
$devDir     = __DIR__;                          // .../modules/addons/timekeeper/dev
$moduleDir  = dirname($devDir);                 // .../modules/addons/timekeeper
$whmcsRoot  = dirname($moduleDir, 3);           // .../ (WHMCS root)
$initFile   = $whmcsRoot . '/init.php';

if (!file_exists($initFile)) {
    http_response_code(500);
    exit("init.php not found at: {$initFile}\nCheck directory depth.");
}

// Require WHMCS bootstrap (for admin auth)
require_once $initFile;

// Must be an authenticated WHMCS admin
if (empty($_SESSION['adminid'])) {
    http_response_code(403);
    exit("Forbidden: login to WHMCS admin first.\n");
}

// --- OPcache availability checks ---
$hasReset      = function_exists('opcache_reset');
$hasInvalidate = function_exists('opcache_invalidate');

if (!$hasReset && !$hasInvalidate) {
    exit("OPcache functions are not available. (opcache_reset/opcache_invalidate)\n");
}

// If opcache.restrict_api is set, this script must live in that directory
$restrictApi = ini_get('opcache.restrict_api');
if (!empty($restrictApi)) {
    $realSelf   = realpath(__FILE__);
    $realRestr  = realpath($restrictApi);
    if ($realRestr && strpos($realSelf, $realRestr) !== 0) {
        exit("Blocked by opcache.restrict_api. Move this script under: {$realRestr}\n");
    }
}

// --- Invalidate specific module files first (safer than global reset) ---
$files = array_filter([
    realpath($moduleDir . '/pages/settings.php'),
    realpath($moduleDir . '/templates/settings.tpl'),
    realpath($moduleDir . '/templates/components/settings_cron.tpl'),
    realpath($moduleDir . '/templates/components/settings_approvals.tpl'),
    realpath($moduleDir . '/templates/components/settings_hide_tabs.tpl'),
    realpath($moduleDir . '/components/cron_daily_timesheet.php'),
    realpath($moduleDir . '/cron/cron.php'),
    realpath($moduleDir . '/css/settings.css'),
    realpath($moduleDir . '/css/settings_tabs.css'),
    realpath($moduleDir . '/css/settings_hide_tabs.css'),
    realpath($moduleDir . '/js/settings.js'),
]);

$invalidated = 0;
$failed      = [];
foreach ($files as $f) {
    if ($hasInvalidate) {
        $ok = @opcache_invalidate($f, /*force*/true);
        if ($ok) { $invalidated++; } else { $failed[] = $f; }
    }
}

// Fallback: full reset if nothing invalidated (or if you want to force it)
if ($invalidated === 0 && $hasReset) {
    @opcache_reset();
    echo "OPcache reset (global).\n";
} else {
    echo "Invalidated {$invalidated} file(s).\n";
    if (!empty($failed)) {
        echo "Failed to invalidate:\n - " . implode("\n - ", $failed) . "\n";
    }
}

// Quick env info (helps debug)
echo "restrict_api: " . ($restrict_
