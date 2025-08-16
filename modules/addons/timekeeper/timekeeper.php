<?php

use WHMCS\Config\Setting;

/**
 * Build a public URL for an asset under modules/addons/timekeeper/.
 * Uses SystemURL to ensure correct base, works from admin.
 */
function tk_asset_url(string $relativePath): stringfunction tk_asset_url(string $relativePath): string
{
    // Prefer SSL URL if we're on HTTPS and it's configured, else fallback to SystemURL
    $ssl = \WHMCS\Config\Setting::getValue('SystemSSLURL');
    $base = $ssl && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? $ssl
        : \WHMCS\Config\Setting::getValue('SystemURL');

    // Final fallback to current host if config is missing (very rare)
    if (!$base) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }

    $base = rtrim($base, '/'); // normalize
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
 * Echo <link>/<script> tags for page-specific and/or global assets.
 * $assets = [
 *   'css' => ['timekeeper.css', 'departments.css'],
 *   'js'  => ['timekeeper.js', 'departments.js']
 * ];
 */
function tk_load_assets(array $assets): void
{
    // CSS
    if (!empty($assets['css'])) {
        foreach ($assets['css'] as $css) {
            $rel = 'css/' . ltrim($css, '/');
            $href = tk_asset_url($rel) . '?v=' . tk_asset_ver($rel);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
    }
    // JS (defer to avoid blocking)
    if (!empty($assets['js'])) {
        foreach ($assets['js'] as $js) {
            $rel = 'js/' . ltrim($js, '/');
            $src = tk_asset_url($rel) . '?v=' . tk_asset_ver($rel);
            echo '<script defer src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        }
    }
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
 * Accepts same shape as tk_load_assets().
 */
function tk_load_assets_if_exists(array $assets): void
{
    // CSS
    if (!empty($assets['css'])) {
        foreach ($assets['css'] as $css) {
            $rel = 'css/' . ltrim($css, '/');
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

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly.");
}
use WHMCS\Database\Capsule;

function timekeeper_config() {
    return [
        'name'        => 'Timekeeper',
        'description' => 'WHMCS admin time tracking module with built-in dashboard',
        'version'     => '1.0',
        'author'      => 'CodeAnchorPro',
        'fields'      => []
    ];
}

function timekeeper_output($vars) {

    // Default landing: Dashboard
    if (empty($_GET['timekeeperpage'])) {
        $url = 'addonmodules.php?module=timekeeper&timekeeperpage=dashboard';
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
        // Fallback if headers already sent
        echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
        return;
    }

    $page = $_GET['timekeeperpage'];

    // Load global + page-specific assets (skip for CSV export)

    if (!($page === 'reports' && isset($_GET['export']) && $_GET['export'] === '1')) {
        $tkPageAssets = [
            'dashboard'           => ['css' => ['timekeeper.css', 'dashboard.css'],           'js' => ['timekeeper.js', 'dashboard.js']],
            'timesheet'           => ['css' => ['timekeeper.css', 'timesheet.css'],           'js' => ['timekeeper.js', 'timesheet.js']],
            'pending_timesheets'  => ['css' => ['timekeeper.css', 'pending_timesheets.css'],  'js' => ['timekeeper.js', 'pending_timesheets.js']],
            'approved_timesheets' => ['css' => ['timekeeper.css', 'approved_timesheets.css'], 'js' => ['timekeeper.js', 'approved_timesheets.js']],
            'departments'         => ['css' => ['timekeeper.css', 'departments.css'],         'js' => ['timekeeper.js', 'departments.js']],
            'task_categories'     => ['css' => ['timekeeper.css', 'task_categories.css'],     'js' => ['timekeeper.js', 'task_categories.js']],
            'reports'             => ['css' => ['timekeeper.css', 'reports.css'],             'js' => ['timekeeper.js', 'reports.js']],
            'settings'            => ['css' => ['timekeeper.css', 'settings.css'],            'js' => ['timekeeper.js', 'settings.js']],
        ];

        // Base page bundle
        if (isset($tkPageAssets[$page])) {
            tk_load_assets_if_exists($tkPageAssets[$page]);
        } else {
            tk_load_assets_if_exists(['css' => ['timekeeper.css'], 'js' => ['timekeeper.js']]);
        }

        // ---- Subpage auto-loader conventions ----
        // Generic: ?sub=foo  -> loads <page>_foo.css/js if present
        $genericSub = isset($_GET['sub']) ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['sub']) : null;

        // Settings-specific: ?settings_sub=tab|hide_tabs -> loads settings_tab.css/js etc.
        $settingsSub = ($page === 'settings' && isset($_GET['settings_sub']))
            ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['settings_sub'])
            : null;

        // Reports-specific: ?report_sub=output -> loads reports_output.css/js etc.
        $reportSub = ($page === 'reports' && isset($_GET['report_sub']))
            ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['report_sub'])
            : null;

        // Build a list of candidate sub-suffixes to try in order (no duplicates)
        $subSuffixes = [];
        foreach ([$genericSub, $settingsSub, $reportSub] as $s) {
            if ($s && !in_array($s, $subSuffixes, true)) $subSuffixes[] = $s;
        }

        if (!empty($subSuffixes)) {
            foreach ($subSuffixes as $suf) {
                $css = "{$page}_{$suf}.css"; // e.g., settings_tab.css / reports_output.css
                $js  = "{$page}_{$suf}.js";  // e.g., settings_tab.js  / reports_output.js
                tk_load_assets_if_exists(['css' => [$css], 'js' => [$js]]);
            }
        }
    }


    // Include navigation (but not during CSV export)
    if (!isset($_GET['export']) || $_GET['export'] !== '1') {
        include __DIR__ . '/includes/navigation.php';
        echo '<div class="timekeeper-root">'; // scope all page markup
    }

    switch ($page) {
        case 'dashboard':
            include __DIR__ . '/pages/dashboard.php';
            break;
        case 'timesheet':
            ob_start();
            include __DIR__ . '/pages/timesheet.php';
            $vars['timesheetContent'] = ob_get_clean();
            echo $vars['timesheetContent'];
            break;
        case 'pending_timesheets':
            ob_start();
            include __DIR__ . '/pages/pending_timesheets.php';
            $vars['pendingTimesheetsContent'] = ob_get_clean();
            echo $vars['pendingTimesheetsContent'];
            break;
        case 'approved_timesheets':
            ob_start();
            include __DIR__ . '/pages/approved_timesheets.php';
            $vars['approvedTimesheetsContent'] = ob_get_clean();
            echo $vars['approvedTimesheetsContent'];
            break;
        case 'departments':
            ob_start();
            include __DIR__ . '/pages/departments.php';
            $vars['departmentsContent'] = ob_get_clean();
            echo $vars['departmentsContent'];
            break;
        case 'task_categories':
            ob_start();
            include __DIR__ . '/pages/task_categories.php';
            $vars['taskCategoriesContent'] = ob_get_clean();
            echo $vars['taskCategoriesContent'];
            break;
        case 'reports':
            ob_start();
            include __DIR__ . '/pages/reports.php';
            $vars['reportsContent'] = ob_get_clean();
            echo $vars['reportsContent'];
            break;
        case 'settings':
            ob_start();
            include __DIR__ . '/pages/settings.php';
            $vars['settingsContent'] = ob_get_clean();
            echo $vars['settingsContent'];
            break;
       default:
            // Fall back to dashboard, not an error message
            include __DIR__ . '/pages/dashboard.php';
            break;
    }
    // Close scope wrapper unless we were exporting (no nav means no wrapper)
    if (!isset($_GET['export']) || $_GET['export'] !== '1') {
        echo '</div>';
        }
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

