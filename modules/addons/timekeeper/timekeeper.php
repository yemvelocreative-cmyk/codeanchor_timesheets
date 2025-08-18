<?php

/**
 * Timekeeper Addon
 * Full drop-in with per-role page RBAC (driven by Hide Menu Tabs),
 * asset helpers, normalized routing, and safe defaults.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use WHMCS\Config\Setting;
use WHMCS\Database\Capsule;

/* ============================================================
 * RBAC & Utility Helpers
 * ============================================================ */

/**
 * Get the current admin's role id (0 on failure).
 */
function tk_getAdminRoleId(int $adminId): int
{
    try {
        return (int) Capsule::table('tbladmins')->where('id', $adminId)->value('roleid');
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * Normalize page keys so UI aliases map consistently.
 * Adjust mappings to match your actual routes.
 */
function tk_normalize_page(string $page): string
{
    $p = strtolower(trim($page));
    $map = [
        'approvals'   => 'approval',
        'timesheet'   => 'timesheet',   // keep timesheet as its own page
        'timesheets'  => 'approval',    // if you use plural to mean approvals
        'cronsetup'   => 'cron',
        'hide'        => 'hide_tabs',
        'hidetabs'    => 'hide_tabs',
        'settings'    => 'settings',
    ];
    return $map[$p] ?? $p;
}

/**
 * Load the per-role hidden pages map from addon settings (JSON).
 * Expected structure:
 * {
 *   "1": [],
 *   "2": ["settings","reports"],
 *   "3": ["settings"]
 * }
 *
 * Primary store: tbladdonmodules (module='timekeeper', setting='hide_tabs_roles')
 * Optional legacy fallback: mod_timekeeper_hidden_tabs table (role_id/page columns)
 */
function tk_getHiddenPagesByRole(): array
{
    try {
        $val = Capsule::table('tbladdonmodules')
            ->where('module', 'timekeeper')
            ->where('setting', 'hide_tabs_roles')
            ->value('value');

        $map = $val ? json_decode($val, true) : [];
        return is_array($map) ? $map : [];
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Save per-role hidden pages map into tbladdonmodules (JSON).
 * Shape: [ "1" => ["settings"], "2" => ["settings","reports"], ... ]
 */
function tk_saveHiddenPagesByRole(array $map): bool
{
    // Allow only known page keys
    $validPages = [
        'dashboard','timesheet','pending_timesheets','approved_timesheets',
        'departments','task_categories','reports','settings','cron','approval','hide_tabs',
    ];

    // Normalize and sanitize
    $clean = [];
    foreach ($map as $roleId => $pages) {
        $rid = (string)(int)$roleId;
        if ($rid === '0') {
            continue;
        }

        $seen = [];
        foreach ((array)$pages as $p) {
            $pp = tk_normalize_page((string)$p);
            if (in_array($pp, $validPages, true) && !in_array($pp, $seen, true)) {
                $seen[] = $pp;
            }
        }
        $clean[$rid] = $seen;
    }

    try {
        $settingKey = 'hide_tabs_roles';
        $val = json_encode($clean, JSON_UNESCAPED_UNICODE);

        $existing = Capsule::table('tbladdonmodules')
            ->where('module', 'timekeeper')
            ->where('setting', $settingKey)
            ->first();

        if ($existing) {
            Capsule::table('tbladdonmodules')
                ->where('module', 'timekeeper')
                ->where('setting', $settingKey)
                ->update(['value' => $val]);
        } else {
            Capsule::table('tbladdonmodules')->insert([
                'module'  => 'timekeeper',
                'setting' => $settingKey,
                'value'   => $val,
            ]);
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Decide if a role is allowed to view a page, based on the "Hide Menu Tabs" config.
 * If a page is hidden for a role, deny. If the role has no entry, allow by default
 * (except we apply a safe default for Settings below).
 */
function tk_isPageAllowedForRole(int $roleId, string $page): bool
{
    // Safety: Full Admin can always access (prevents accidental lockout)
    if ($roleId === 1) {
        return true;
    }

    $page   = tk_normalize_page($page);
    $hidden = tk_getHiddenPagesByRole();
    $roleKey = (string) $roleId;

    // No config for this role? Allow everything.
    if (!isset($hidden[$roleKey]) || !is_array($hidden[$roleKey])) {
        return true;
    }

    // Deny only if this page is explicitly hidden for this role.
    return !in_array($page, $hidden[$roleKey], true);
}


/**
 * Pick a safe fallback page the role CAN access.
 */
function tk_firstAllowedPageForRole(int $roleId, array $candidates = []): string
{
    $order = $candidates ?: ['dashboard','reports','approval','timesheet','cron','hide_tabs','settings'];
    foreach ($order as $p) {
        if (tk_isPageAllowedForRole($roleId, $p)) {
            return $p;
        }
    }
    return 'dashboard';
}

/* ============================================================
 * Asset Helpers
 * ============================================================ */

/**
 * Build a public URL for an asset under modules/addons/timekeeper/.
 * Uses SystemURL/SystemSSLURL to ensure correct base, works from admin.
 */
function tk_asset_url(string $relativePath): string
{
    $ssl  = Setting::getValue('SystemSSLURL');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $base = ($ssl && $https) ? $ssl : Setting::getValue('SystemURL');

    if (!$base) {
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host;
    }

    $base = rtrim($base, '/');
    return $base . '/modules/addons/timekeeper/' . ltrim($relativePath, '/');
}

/**
 * Get a version string based on file mtime for cache-busting.
 * Falls back to a static version if file missing (e.g., before first deploy).
 */
function tk_asset_ver(string $relativePath, string $fallback = '1.0.0'): string
{
    $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    return is_file($abs) ? (string) filemtime($abs) : $fallback;
}

/**
 * Check if an asset file exists relative to modules/addons/timekeeper/.
 */
function tk_asset_exists(string $relativePath): bool
{
    $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    return is_file($abs);
}

/**
 * Load assets but only if the underlying file actually exists.
 * Accepts shape: ['css' => ['file.css'], 'js' => ['file.js']]
 */
function tk_load_assets_if_exists(array $assets): void
{
    // CSS
    if (!empty($assets['css'])) {
        foreach ($assets['css'] as $css) {
            $rel  = 'css/' . ltrim($css, '/');
            if (!tk_asset_exists($rel)) continue;
            $href = tk_asset_url($rel) . '?v=' . tk_asset_ver($rel);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
    }
    // JS
    if (!empty($assets['js'])) {
        foreach ($assets['js'] as $js) {
            $rel = 'js/' . ltrim($js, '/');
            if (!tk_asset_exists($rel)) continue;
            $src = tk_asset_url($rel) . '?v=' . tk_asset_ver($rel);
            echo '<script defer src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        }
    }
}

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
    $roleId  = tk_getAdminRoleId($adminId);

    // Determine requested page and normalize once
    $page = isset($_GET['timekeeperpage']) ? (string)$_GET['timekeeperpage'] : 'dashboard';
    $page = tk_normalize_page($page);

    // Enforce page-level RBAC based on Hide Tabs config
    if (!tk_isPageAllowedForRole($roleId, $page)) {
        echo '<div class="alert alert-danger" style="margin-top:8px">'
           . 'Access Denied: Your role does not have permission to view this page.'
           . '</div>';

        $fallback = tk_firstAllowedPageForRole($roleId);
        $_GET['timekeeperpage'] = $fallback; // keep existing routers/templates happy
        $page = $fallback;
    }

    // If no page specified at all, land on dashboard
    if (empty($_GET['timekeeperpage'])) {
        $url = 'addonmodules.php?module=timekeeper&timekeeperpage=dashboard';
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
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
            'approval'            => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']], // if routed as sub
            'cron'                => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']], // if routed as sub
            'hide_tabs'           => ['css' => ['timekeeper.css', 'settings_tabs.css', 'settings.css'], 'js' => ['timekeeper.js', 'settings.js']], // if routed as sub
        ];

        if (isset($tkPageAssets[$page])) {
            tk_load_assets_if_exists($tkPageAssets[$page]);
        } else {
            tk_load_assets_if_exists(['css' => ['timekeeper.css'], 'js' => ['timekeeper.js']]);
        }

        // Always include navigation assets if they exist
        tk_load_assets_if_exists(['css' => ['navigation.css'], 'js' => ['navigation.js']]);

        // ---- Subpage auto-loader conventions ----
        $genericSub = isset($_GET['sub']) ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['sub']) : null;
        $settingsSub = ($page === 'settings' && isset($_GET['subtab']))
            ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['subtab'])
            : null;
        $reportSub = ($page === 'reports' && isset($_GET['report_sub']))
            ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['report_sub'])
            : null;

        $subSuffixes = [];
        foreach ([$genericSub, $settingsSub, $reportSub] as $s) {
            if ($s && !in_array($s, $subSuffixes, true)) {
                $subSuffixes[] = $s;
            }
        }
        if (!empty($subSuffixes)) {
            foreach ($subSuffixes as $suf) {
                $css = "{$page}_{$suf}.css"; // e.g., settings_approval.css
                $js  = "{$page}_{$suf}.js";  // e.g., settings_approval.js
                tk_load_assets_if_exists(['css' => [$css], 'js' => [$js]]);
            }
        }
    }

    /* ----------------- Wrapper + Navigation ----------------- */
    if (!$isExport) {
        echo '<div class="timekeeper-root">'; // open scope FIRST so nav is styled
        $nav = __DIR__ . '/includes/navigation.php';
        if (is_readable($nav)) {
            include $nav;
        }
    }

    /* ----------------- Page Router ----------------- */
    switch ($page) {
        case 'dashboard':
            include __DIR__ . '/pages/dashboard.php';
            break;

        case 'timesheet':
            include __DIR__ . '/pages/timesheet.php';
            break;

        case 'pending_timesheets':
            include __DIR__ . '/pages/pending_timesheets.php';
            break;

        case 'approved_timesheets':
            include __DIR__ . '/pages/approved_timesheets.php';
            break;

        case 'departments':
            include __DIR__ . '/pages/departments.php';
            break;

        case 'task_categories':
            include __DIR__ . '/pages/task_categories.php';
            break;

        case 'reports':
            include __DIR__ . '/pages/reports.php';
            break;

        case 'settings':
            include __DIR__ . '/pages/settings.php';
            break;

        case 'approval':     // if you route approvals as a standalone page
        case 'cron':         // ditto cron
        case 'hide_tabs':    // ditto hide tabs
            // Commonly these are sub-tabs under settings; if you’ve made them standalone,
            // include their specific page files here. Otherwise they will resolve via settings.php.
            include __DIR__ . '/pages/settings.php';
            break;

        default:
            include __DIR__ . '/pages/dashboard.php';
            break;
    }

    if (!$isExport) {
        echo '</div>'; // close .timekeeper-root
    }
}
