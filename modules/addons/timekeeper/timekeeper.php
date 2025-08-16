<?php

use WHMCS\Config\Setting;

/**
 * Build a public URL for an asset under modules/addons/timekeeper/.
 * Uses SystemURL to ensure correct base, works from admin.
 */
function tk_asset_url(string $relativePath): string
{
    $base = rtrim(Setting::get('SystemURL'), '/'); // e.g., https://example.com
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

    // If exporting from reports, include only the report page and stop
    if ($page === 'reports' && isset($_GET['export']) && $_GET['export'] === '1') {
        include __DIR__ . '/pages/reports.php';
        return;
    }

    // Include navigation (but not during CSV export)
    if (!isset($_GET['export']) || $_GET['export'] !== '1') {
        include __DIR__ . '/includes/navigation.php';
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

