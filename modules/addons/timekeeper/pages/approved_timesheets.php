<?php
// /modules/addons/timekeeper/pages/approved_timesheets.php
use WHMCS\Database\Capsule;

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
$try('/helpers/approved_timesheets_helper.php', '/includes/helpers/approved_timesheets_helper.php');

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\ApprovedTimesheetsHelper as ApprovedH;

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

$isValidDate = function ($s) { return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); };
$fltStart = $isValidDate($fltStart) ? $fltStart : '';
$fltEnd   = $isValidDate($fltEnd)   ? $fltEnd   : '';
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
    'pager' // <--- NEW
);

extract($vars);

// Template is plural per your filenames:
include __DIR__ . '/../templates/admin/approved_timesheets.tpl';
