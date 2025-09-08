<?php
// /modules/addons/timekeeper/pages/approved_timesheets.php
use WHMCS\Database\Capsule;
use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\ApprovedTimesheetsHelper as ApprovedH;

// --- Load helpers (supports either helpers/ or includes/helpers/) ---
$base = dirname(__DIR__); // -> /modules/addons/timekeeper
// Bootstrap: include CoreHelper itself (cannot call CoreHelper before it's loaded)
(function($base){
    $a = $base . '/helpers/core_helper.php';
    $b = $base . '/includes/helpers/core_helper.php';
    if (is_file($a)) { require_once $a; }
    elseif (is_file($b)) { require_once $b; }
    else { throw new \RuntimeException("Missing core_helper.php in helpers/ or includes/helpers/"); }
})($base);
CoreH::requireHelper($base, 'approved_timesheets_helper');

// ---- Dynamic base URL + asset helper (polyfill if not in core_helper yet) ----
/**
 * Preferred approach: use helper functions added to core_helper.php:
 * - \Timekeeper\Helpers\timekeeperBaseUrl(): string
 * - \Timekeeper\Helpers\timekeeperAsset(string $relPath): string
 *
 * Fallback here keeps this page working even before core_helper is updated.
 */
if (!function_exists('\Timekeeper\Helpers\timekeeperBaseUrl') || !function_exists('\Timekeeper\Helpers\timekeeperAsset')) {
    // Local polyfill (scoped)
    // Note: WHMCS Setting is only used here, avoid global imports to keep file clean
    $tkSystemUrl = (function (): string {
        try {
            $ssl = (string) \WHMCS\Config\Setting::getValue('SystemSSLURL');
            $url = $ssl !== '' ? $ssl : (string) \WHMCS\Config\Setting::getValue('SystemURL');
            return rtrim($url, '/');
        } catch (\Throwable $e) {
            return ''; // fallback to relative if settings unavailable
        }
    })();

    $tkBase = ($tkSystemUrl !== '' ? $tkSystemUrl : '') . '/modules/addons/timekeeper';
    $tkBase = rtrim($tkBase, '/');

    // Callable for templates: $tkAsset('css/file.css')
    $tkAsset = function (string $relPath) use ($tkBase, $base): string {
        $rel = ltrim($relPath, '/');
        $url = $tkBase . '/' . $rel;

        // Append mtime if the file exists on disk for cache-busting
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
    // Use helpers from core_helper.php if available
    $tkBase  = \Timekeeper\Helpers\timekeeperBaseUrl();
    $tkAsset = '\Timekeeper\Helpers\timekeeperAsset'; // callable
}

// ---- Context: current admin + role ----
$adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
$admin   = Capsule::table('tbladmins')->where('id', $adminId)->first();
$roleId  = $admin ? (int) $admin->roleid : 0;

// ---- CSRF token for actions (unapprove) ----
if (empty($_SESSION['tk_csrf'])) {
    $_SESSION['tk_csrf'] = bin2hex(random_bytes(16));
}
$tkCsrf = (string) $_SESSION['tk_csrf'];

// ---- Permissions from Settings ----
$viewAllRoleIds    = ApprovedH::viewAllRoleIds();      // roles allowed to view ALL approved timesheets
$canUnapprove      = ApprovedH::canUnapprove($roleId); // role can approve/unapprove?
$canUseAdminFilter = in_array($roleId, $viewAllRoleIds, true);

// ---- Handle POST actions (Unapprove) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) CoreH::post('tk_action', '');
    $csrf   = (string) CoreH::post('tk_csrf', '');
    if (!hash_equals($tkCsrf, $csrf)) {
        header('Location: addonmodules.php?module=timekeeper&timekeeperpage=approved_timesheets');
        exit;
    }

    if ($action === 'unapprove' && $canUnapprove) {
        $tsId = (int) CoreH::post('ts_id', 0);
        if ($tsId > 0) {
            $ts = Capsule::table('mod_timekeeper_timesheets')
                ->where('id', $tsId)
                ->where('status', 'approved')
                ->first();

            if ($ts) {
                $ownerId           = (int) $ts->admin_id;
                $viewerHasViewAll  = in_array($roleId, $viewAllRoleIds, true);
                $viewerCanSeeSheet = $viewerHasViewAll || ($ownerId === $adminId);

                if ($viewerCanSeeSheet) {
                    Capsule::table('mod_timekeeper_timesheets')
                        ->where('id', $tsId)
                        ->update(['status' => 'pending']);
                }
            }
        }
        header('Location: addonmodules.php?module=timekeeper&timekeeperpage=approved_timesheets');
        exit;
    }
}

// ---- Maps for template ----
$adminMap      = ApprovedH::adminMap();
$clientMap     = ApprovedH::clientMap();
$departmentMap = ApprovedH::departmentMap();   // mod_timekeeper_departments
$taskMap       = ApprovedH::taskMap();         // mod_timekeeper_task_categories

// ---- Listing vs. viewing a specific approved timesheet ----
$reqAdminId = CoreH::get('admin_id', null);
$reqDate    = CoreH::get('date', null);

// Filters (listing only)
$fltStart   = CoreH::get('start_date', '');
$fltEnd     = CoreH::get('end_date', '');
$fltAdminId = CoreH::get('filter_admin_id', '');

$fltStart = CoreH::isValidDate($fltStart) ? $fltStart : '';
$fltEnd   = CoreH::isValidDate($fltEnd)   ? $fltEnd   : '';
$fltAdmin = ctype_digit((string)$fltAdminId) ? (int)$fltAdminId : 0;

// Pagination settings (listing only)
$settingPerPage = Capsule::table('mod_timekeeper_permissions')
    ->where('setting_key', 'pagination value')
    ->where('role_id', 0)
    ->value('setting_value');

$perPage = (is_numeric($settingPerPage) && (int)$settingPerPage > 0)
    ? (int)$settingPerPage
    : 25; // system default when not set

$pageParam = (int) CoreH::get('page', 1);
$page      = $pageParam > 0 ? $pageParam : 1;

$approvedTimesheets = [];
$timesheet          = null;
$timesheetEntries   = [];
$totalTime          = 0.0;
$totalBillable      = 0.0; // for single view totals bar
$totalSla           = 0.0;

$pager = [
    'page'    => 1,
    'per'     => $perPage,
    'total'   => 0,
    'pages'   => 1,
    'from'    => 0,
    'to'      => 0,
    'prevUrl' => '',
    'nextUrl' => '',
];

if ($reqAdminId && $reqDate) {
    // ----- SINGLE VIEW -----
    $reqAdminId = (int) $reqAdminId;
    $reqDate    = (string) $reqDate;

    $timesheet = ApprovedH::getApprovedTimesheet(
        $reqAdminId, $reqDate, $adminId, $roleId, $viewAllRoleIds
    );

    if ($timesheet) {
        $timesheetEntries = ApprovedH::getTimesheetEntries((int) $timesheet->id);
        $totalTime        = ApprovedH::sumColumn($timesheetEntries, 'time_spent');
        $totalBillable    = ApprovedH::sumColumn($timesheetEntries, 'billable_time');
        $totalSla         = ApprovedH::sumColumn($timesheetEntries, 'sla_time');
    }
} else {
    // ----- LISTING (with filters + pagination) -----
    $baseQuery = Capsule::table('mod_timekeeper_timesheets')
        ->where('status', 'approved');

    // Admin scope
    if (!$canUseAdminFilter) {
        $baseQuery->where('admin_id', $adminId);
    } elseif ($fltAdmin > 0) {
        $baseQuery->where('admin_id', $fltAdmin);
    }

    // Date range filters
    if ($fltStart !== '') { $baseQuery->where('timesheet_date', '>=', $fltStart); }
    if ($fltEnd   !== '') { $baseQuery->where('timesheet_date', '<=', $fltEnd); }

    // Count total
    $totalCount = (int) (clone $baseQuery)->count();

    // Compute paging numbers
    $pages = max(1, (int)ceil($totalCount / $perPage));
    if ($page > $pages) { $page = $pages; }
    if ($page < 1)      { $page = 1; }
    $offset = ($page - 1) * $perPage;

    // Fetch page
    $approvedTimesheets = $baseQuery
        ->orderBy('timesheet_date', 'desc')
        ->orderBy('admin_id', 'asc')
        ->skip($offset)
        ->take($perPage)
        ->get();

    // Build pager meta + URLs (preserving filters)
    $from = $totalCount ? ($offset + 1) : 0;
    $to   = $totalCount ? min($offset + $perPage, $totalCount) : 0;

    $qs = [
        'module'         => 'timekeeper',
        'timekeeperpage' => 'approved_timesheets',
    ];
    if ($fltStart !== '') $qs['start_date'] = $fltStart;
    if ($fltEnd   !== '') $qs['end_date']   = $fltEnd;
    if ($canUseAdminFilter && $fltAdmin > 0) $qs['filter_admin_id'] = (string)$fltAdmin;

    $prevUrl = '';
    $nextUrl = '';
    if ($page > 1) {
        $p = $qs; $p['page'] = $page - 1;
        $prevUrl = 'addonmodules.php?' . http_build_query($p);
    }
    if ($page < $pages) {
        $n = $qs; $n['page'] = $page + 1;
        $nextUrl = 'addonmodules.php?' . http_build_query($n);
    }

    $pager = [
        'page'    => $page,
        'per'     => $perPage,
        'total'   => $totalCount,
        'pages'   => $pages,
        'from'    => $from,
        'to'      => $to,
        'prevUrl' => $prevUrl,
        'nextUrl' => $nextUrl,
    ];
}

// ---- Pass to template ----
$filters = [
    'start_date'       => $fltStart,
    'end_date'         => $fltEnd,
    'filter_admin_id'  => $fltAdmin ? (string)$fltAdmin : '',
];

$vars = compact(
    'approvedTimesheets',
    'adminMap',
    'clientMap',
    'departmentMap',
    'taskMap',
    'timesheet',
    'timesheetEntries',
    'totalTime',
    'totalBillable',
    'totalSla',
    'tkCsrf',
    'canUnapprove',
    'canUseAdminFilter',
    'filters',
    'pager',
    // NEW: expose dynamic base + asset builder to the template
    'tkBase',
    'tkAsset'
);

extract($vars);

// Template is plural per your filenames:
include __DIR__ . '/../templates/admin/approved_timesheets.tpl';
