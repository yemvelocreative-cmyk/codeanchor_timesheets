<?php

if (!defined('WHMCS')) { die('This file cannot be accessed directly.'); }

use WHMCS\Database\Capsule;

// --- Load helpers (supports helpers/ or includes/helpers/) ---
$base = __DIR__; // /modules/addons/timekeeper
$try = function (string $relA, string $relB) use ($base) {
    $a = $base . $relA; $b = $base . $relB;
    if (is_file($a)) { require_once $a; return; }
    if (is_file($b)) { require_once $b; return; }
    throw new \RuntimeException("Missing helper: tried {$a} and {$b}");
};
$try('/helpers/core_helper.php', '/includes/helpers/core_helper.php');
$try('/helpers/timekeeper_helper.php', '/includes/helpers/timekeeper_helper.php');

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\TimekeeperHelper as TkH;

/* ============================================================
 * WHMCS Addon Entry Points
 * (config/activate/deactivate/upgrade are unchanged)
 * ============================================================ */

function timekeeper_config() { /* ... keep as-is ... */ }
function timekeeper_activate() { /* ... keep as-is ... */ }
function timekeeper_deactivate() { /* ... keep as-is ... */ }
function timekeeper_upgrade($vars) { /* ... keep as-is ... */ }

/**
 * Main admin output (router).
 */
function timekeeper_output($vars)
{
    $adminId = (int)($vars['adminid'] ?? ($_SESSION['adminid'] ?? 0));
    $roleId  = TkH::getAdminRoleId($adminId);

    // Normalize requested page using the global helper from core_helper.php
    $rawPage = isset($_GET['timekeeperpage']) ? (string)$_GET['timekeeperpage'] : 'dashboard';
    $page    = tk_normalize_page($rawPage);

    // RBAC via Hide Tabs config
    if (!TkH::isPageAllowedForRole($roleId, $page)) {
        echo '<div class="alert alert-danger" style="margin-top:8px">'
           . 'Access Denied: Your role does not have permission to view this page.'
           . '</div>';

        $fallback = TkH::firstAllowedPageForRole($roleId);
        $_GET['timekeeperpage'] = $fallback;
        $page = $fallback;
    }

    // Default landing
    if (empty($_GET['timekeeperpage'])) {
        $url = 'addonmodules.php?module=timekeeper&timekeeperpage=dashboard';
        if (!headers_sent()) { header('Location: ' . $url); exit; }
        echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
        return;
    }

    /* ----------------- Assets (skip during CSV export) ----------------- */
    $isExport = ($page === 'reports' && isset($_GET['export']) && $_GET['export'] === '1');

    if (!$isExport) {
        $tkPageAssets = [
            'dashboard'           => ['css' => ['timekeeper.css', 'dashboard.css'],           'js' => ['timekeeper.js', 'dashboard.js']],
            'timesheet'           => ['css' => ['timekeeper.css', 'timesheet.css'],           'js' => ['timekeeper.js', 'timesheet.js']],
            'pending_timesheets'  => ['css' => ['timekeeper.css', 'pending_timesheets.css'],  'js' => ['timekeeper.js', 'pending_timesheets.js']],
            'approved_timesheets' => ['css' => ['timekeeper.css', 'approved_timesheets.css'], 'js' => ['timekeeper.js', 'approved_timesheets.js']],
            'departments'         => ['css' => ['timekeeper.css', 'departments.css'],         'js' => ['timekeeper.js', 'departments.js']],
            'task_categories'     => ['css' => ['timekeeper.css', 'task_categories.css'],     'js' => ['timekeeper.js', 'task_categories.js']],
            'reports'             => ['css' => ['timekeeper.css', 'reports.css'],             'js' => ['timekeeper.js', 'reports.js']],
            'settings'            => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']],
            'approval'            => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']],
            'cron'                => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']],
            'hide_tabs'           => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']],
        ];

        if (isset($tkPageAssets[$page])) {
            TkH::loadAssetsIfExists($tkPageAssets[$page]);
        } else {
            TkH::loadAssetsIfExists(['css' => ['timekeeper.css'], 'js' => ['timekeeper.js']]);
        }

        // Optional navigation assets
        TkH::loadAssetsIfExists(['css' => ['navigation.css'], 'js' => ['navigation.js']]);

        // subpage suffix assets (kept as-is)
        $genericSub  = isset($_GET['sub']) ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['sub']) : null;
        $settingsSub = ($page === 'settings' && isset($_GET['subtab']))
            ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['subtab'])
            : null;
        $reportSub   = ($page === 'reports' && isset($_GET['report_sub']))
            ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['report_sub'])
            : null;

        $subSuffixes = array_values(array_filter([$genericSub, $settingsSub, $reportSub]));
        if (!empty($subSuffixes)) {
            foreach ($subSuffixes as $suf) {
                TkH::loadAssetsIfExists(['css' => ["{$page}_{$suf}.css"], 'js' => ["{$page}_{$suf}.js"]]);
            }
        }
    }

    /* ----------------- Wrapper + Navigation ----------------- */
    if (!$isExport) {
        echo '<div class="timekeeper-root">';
        $nav = __DIR__ . '/includes/navigation.php';
        if (is_readable($nav)) { include $nav; }
    }

    /* ----------------- Page Router (unchanged) ----------------- */
    switch ($page) {
        case 'dashboard':          include __DIR__ . '/pages/dashboard.php'; break;
        case 'timesheet':          include __DIR__ . '/pages/timesheet.php'; break;
        case 'pending_timesheets': include __DIR__ . '/pages/pending_timesheets.php'; break;
        case 'approved_timesheets':include __DIR__ . '/pages/approved_timesheets.php'; break;
        case 'departments':        include __DIR__ . '/pages/departments.php'; break;
        case 'task_categories':    include __DIR__ . '/pages/task_categories.php'; break;
        case 'reports':            include __DIR__ . '/pages/reports.php'; break;
        case 'settings':           include __DIR__ . '/pages/settings.php'; break;
        case 'approval':
        case 'cron':
        case 'hide_tabs':          include __DIR__ . '/pages/settings.php'; break;
        default:                   include __DIR__ . '/pages/dashboard.php'; break;
    }

    if (!$isExport) { echo '</div>'; }
}
