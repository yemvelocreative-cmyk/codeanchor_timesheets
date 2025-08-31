<?php

/**
 * Timekeeper Addon
 * Router + per-role page RBAC (driven by Hide Menu Tabs),
 * asset loader, normalized routing, and safe defaults.
 */

if (!defined('WHMCS')) { die('This file cannot be accessed directly.'); }

use WHMCS\Database\Capsule;

// ---- Load helpers (supports helpers/ or includes/helpers/) ----
$base = __DIR__; // /modules/addons/timekeeper
$try = function (string $relA, string $relB) use ($base) {
    $a = $base . $relA; $b = $base . $relB;
    if (is_file($a)) { require_once $a; return; }
    if (is_file($b)) { require_once $b; return; }
    throw new \RuntimeException("Missing helper: tried {$a} and {$b}");
};
$try('/helpers/core_helper.php', '/includes/helpers/core_helper.php');
$try('/helpers/settings_helper.php', '/includes/helpers/settings_helper.php');
$try('/helpers/timekeeper_helper.php', '/includes/helpers/timekeeper_helper.php');

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\SettingsHelper as SetH;
use Timekeeper\Helpers\TimekeeperHelper as TkH;

/* ============================================================
 * WHMCS Addon Entry Points
 * ============================================================ */

function timekeeper_config()
{
    return [
        'name'        => 'Timekeeper',
        'description' => 'WHMCS admin time tracking module with built-in dashboard',
        'version'     => '1.0.0',
        'author'      => 'CodeAnchorPro',
        'fields'      => [],
    ];
}

function timekeeper_activate()
{
    try {
        $schema = Capsule::schema();

        $needsInstall =
            !$schema->hasTable('mod_timekeeper_assigned_users') ||
            !$schema->hasTable('mod_timekeeper_departments') ||
            !$schema->hasTable('mod_timekeeper_hidden_tabs') ||
            !$schema->hasTable('mod_timekeeper_permissions') ||
            !$schema->hasTable('mod_timekeeper_task_categories') ||
            !$schema->hasTable('mod_timekeeper_timesheets') ||
            !$schema->hasTable('mod_timekeeper_timesheet_entries');

        if ($needsInstall) {
            $sqlPath = __DIR__ . '/db/install.sql';
            if (!is_readable($sqlPath)) {
                throw new \RuntimeException('Install SQL not found or not readable at: ' . $sqlPath);
            }
            $sql = file_get_contents($sqlPath);

            // Split on semicolon + newline(s). Safe for this file.
            $statements = array_filter(array_map('trim', preg_split('/;[\r\n]+/u', $sql)));
            foreach ($statements as $stmt) {
                if ($stmt !== '') {
                    Capsule::unprepared($stmt . ';');
                }
            }
        }

        // Optional: one-time migration from legacy hidden tab table into JSON setting
        // tk_migrateHiddenTabsLegacyToSetting(); // uncomment if needed

        return ['status' => 'success', 'description' => 'Timekeeper activated. Database verified/installed.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
    }
}

function timekeeper_deactivate()
{
    return ['status' => 'success', 'description' => 'Timekeeper deactivated. No data removed.'];
}

function timekeeper_upgrade($vars)
{
    // Place guarded ALTERs here as you iterate versions
    return ['status' => 'success', 'description' => 'Upgrade checks complete.'];
}

/**
 * Main admin output (router).
 */
function timekeeper_output($vars)
{
    $adminId = (int)($vars['adminid'] ?? ($_SESSION['adminid'] ?? 0));
    $roleId  = TkH::getAdminRoleId($adminId);

    // Determine requested page and normalize (core normalize + alias resolver)
    $rawPage = isset($_GET['timekeeperpage']) ? (string)$_GET['timekeeperpage'] : 'dashboard';
    $page    = tk_normalize_page($rawPage);
    $page    = TkH::resolvePageAlias($page);

    // Human-friendly labels (used for title & breadcrumb)
    $labels = [
        'dashboard'           => 'Dashboard',
        'timesheet'           => 'Timesheet',
        'pending_timesheets'  => 'Pending Timesheets',
        'approved_timesheets' => 'Approved Timesheets',
        'departments'         => 'Departments',
        'task_categories'     => 'Task Categories',
        'reports'             => 'Reports',
        'settings'            => 'Settings',
        'approval'            => 'Timesheet Settings',
        'cron'                => 'Daily Cron Setup',
        'hide_tabs'           => 'Hide Menu Tabs',
    ];
    $label  = $labels[$page] ?? ucwords(str_replace('_',' ', $page));
    $title  = "Timekeeper — {$label}";

    // Set admin page globals (Mixpanel expects a string page title)
    $GLOBALS['pagetitle'] = $title;
    $GLOBALS['helplink']  = '';

    // Breadcrumb: show Settings > Subtab when inside settings area
    $settingsChildren = ['approval','cron','hide_tabs'];
    if ($page === 'settings' || in_array($page, $settingsChildren, true)) {
        $parent  = 'Settings';
        $current = ($page === 'settings') ? 'Settings' : $label;
        $GLOBALS['breadcrumbnav'] = 'Home > ' . $parent . ' > ' . $current;
    } else {
        $GLOBALS['breadcrumbnav'] = 'Home > ' . $label;
    }

    // Enforce page-level RBAC based on Hide Tabs config (shared with settings)
    if (!TkH::isPageAllowedForRole($roleId, $page)) {
        echo '<div class="tk-alert tk-alert--error tk-mt-8">'
           . 'Access Denied: Your role does not have permission to view this page.'
           . '</div>';

        $fallback = TkH::firstAllowedPageForRole($roleId);
        $_GET['timekeeperpage'] = $fallback; // keep existing routers/templates happy
        $page = $fallback;
    }

    // If no page specified at all, land on dashboard
    if (empty($_GET['timekeeperpage'])) {
        $url = 'addonmodules.php?module=timekeeper&timekeeperpage=dashboard';
        TkH::redirect($url);
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
            'approval'            => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']], // settings child
            'cron'                => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']], // settings child
            'hide_tabs'           => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']], // settings child
        ];

        if (isset($tkPageAssets[$page])) {
            TkH::loadAssetsIfExists($tkPageAssets[$page]);
        } else {
            TkH::loadAssetsIfExists(['css' => ['timekeeper.css'], 'js' => ['timekeeper.js']]);
        }

        // Always include navigation assets if they exist
        TkH::loadAssetsIfExists(['css' => ['navigation.css'], 'js' => ['navigation.js']]);

        // ---- Subpage auto-loader conventions ----
        $genericSub  = isset($_GET['sub'])      ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['sub'])      : null;
        $settingsSub = ($page === 'settings' && isset($_GET['subtab']))
                     ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['subtab'])
                     : null;
        $reportSub   = ($page === 'reports' && isset($_GET['report_sub']))
                     ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['report_sub'])
                     : null;

        $subSuffixes = [];
        foreach ([$genericSub, $settingsSub, $reportSub] as $s) {
            if ($s && !in_array($s, $subSuffixes, true)) $subSuffixes[] = $s;
        }
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

    /* ----------------- Page Router ----------------- */
    switch ($page) {
        case 'dashboard':          include __DIR__ . '/pages/dashboard.php'; break;
        case 'timesheet':          include __DIR__ . '/pages/timesheet.php'; break;
        case 'pending_timesheets': include __DIR__ . '/pages/pending_timesheets.php'; break;
        case 'approved_timesheets':include __DIR__ . '/pages/approved_timesheets.php'; break;
        case 'departments':        include __DIR__ . '/pages/departments.php'; break;
        case 'task_categories':    include __DIR__ . '/pages/task_categories.php'; break;
        case 'reports':            include __DIR__ . '/pages/reports.php'; break;
        case 'settings':
        case 'approval':
        case 'cron':
        case 'hide_tabs':
            include __DIR__ . '/pages/settings.php';
            break;
        default:
            include __DIR__ . '/pages/dashboard.php';
    }

    if (!$isExport) {
        echo '</div>'; // .timekeeper-root
    }
}
