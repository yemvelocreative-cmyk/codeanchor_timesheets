<?php
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('Access denied'); }

// --- Load helpers (supports either helpers/ or includes/helpers/) ---
$base = dirname(__DIR__); // -> /modules/addons/timekeeper
$try = function (string $relA, string $relB) use ($base) {
    $a = $base . $relA;
    $b = $base . $relB;
    if (is_file($a)) { require_once $a; return; }
    if (is_file($b)) { require_once $b; return; }
    throw new \RuntimeException("Missing helper: tried {$a} and {$b}");
};
$try('/helpers/core_helper.php', '/includes/helpers/core_helper.php');
$try('/helpers/departments_helper.php', '/includes/helpers/departments_helper.php');

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\DepartmentsHelper as DeptH;

// ---- Dynamic base URL + asset helper (polyfill if not in core_helper yet) ----
// Preferred helpers (if present in core_helper.php):
//   \Timekeeper\Helpers\timekeeperBaseUrl(): string
//   \Timekeeper\Helpers\timekeeperAsset(string $relPath): string
if (!function_exists('\\Timekeeper\\Helpers\\timekeeperBaseUrl') || !function_exists('\\Timekeeper\\Helpers\\timekeeperAsset')) {
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

    // Callable for cache-busted assets, e.g. $tkAsset('css/departments.css')
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
    // Use canonical helpers if available
    $tkBase  = \Timekeeper\Helpers\timekeeperBaseUrl();
    $tkAsset = '\Timekeeper\Helpers\timekeeperAsset'; // callable
}

$modulelink = "addonmodules.php?module=timekeeper&timekeeperpage=departments";

// ==============================
// POST: Add / Edit
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            DeptH::redirect($modulelink . "&error=empty");
        }

        // duplicate guard
        $exists = Capsule::table('mod_timekeeper_departments')
            ->where('name', $name)
            ->where('status', 'active')
            ->exists();
        if ($exists) {
            DeptH::redirect($modulelink . "&error=duplicate");
        }

        try {
            DeptH::add($name);
            DeptH::redirect($modulelink . "&success=1");
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'uq_department_name') !== false) {
                DeptH::redirect($modulelink . "&error=duplicate");
            }
            DeptH::redirect($modulelink . "&error=1");
        }
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));

        if ($id <= 0 || $name === '') {
            DeptH::redirect($modulelink . "&error=empty");
        }

        // duplicate guard (exclude self)
        $exists = Capsule::table('mod_timekeeper_departments')
            ->where('name', $name)
            ->where('status', 'active')
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            DeptH::redirect($modulelink . "&error=duplicate");
        }

        try {
            DeptH::edit($id, $name);
            DeptH::redirect($modulelink . "&updated=1");
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'uq_department_name') !== false) {
                DeptH::redirect($modulelink . "&error=duplicate");
            }
            DeptH::redirect($modulelink . "&error=1");
        }
    }
}

// ==============================
// GET: Delete (soft)
// ==============================
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    if (DeptH::hasLinkedTaskCategories($id)) {
        DeptH::redirect($modulelink . "&error=has_subtasks");
    }

    DeptH::softDelete($id);
    DeptH::redirect($modulelink . "&deleted=1");
}

// ==============================
// Load departments (active + inactive)
// ==============================
[$active, $inactive] = DeptH::fetchLists();

// ==============================
// Build message
// ==============================
$message = DeptH::buildMessageFromQuery($_GET);

// ==============================
// Render template & inject rows
// ==============================
// Make dynamic helpers available to the template scope (in case it references $tkAsset/$tkBase)
$__tkBase  = $tkBase;
$__tkAsset = $tkAsset;

ob_start();
include __DIR__ . '/../templates/admin/departments.tpl';
$content = ob_get_clean();

// Build row HTML using helper
$rowsActive = '';
foreach ($active as $dept) { $rowsActive .= DeptH::buildRow($dept, $modulelink); }

$rowsInactive = '';
foreach ($inactive as $dept) { $rowsInactive .= DeptH::buildRow($dept, $modulelink); }

// Inject
$content = str_replace('<!--MESSAGE-->', $message, $content);
$content = str_replace('<!--DEPT_ROWS_ACTIVE-->', $rowsActive, $content);
$content = str_replace('<!--DEPT_ROWS_INACTIVE-->', $rowsInactive, $content);

// Optionally, if your template uses placeholders for asset URLs, you can replace them here:
// $content = str_replace('<!--TK_CSS_DEPARTMENTS-->', '<link rel="stylesheet" href="'. $__tkAsset('css/departments.css') .'">', $content);
// $content = str_replace('<!--TK_JS_DEPARTMENTS-->',  '<script src="'. $__tkAsset('js/departments.js') .'" defer></script>', $content);

echo $content;
