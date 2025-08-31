<?php
use WHMCS\Database\Capsule;

// --- Load helpers (supports either helpers/ or includes/helpers/) ---
$base = dirname(__DIR__); // -> /modules/addons/timekeeper

$try = function(string $relA, string $relB) use ($base) {
    $a = $base . $relA;
    $b = $base . $relB;
    if (is_file($a)) { require_once $a; return; }
    if (is_file($b)) { require_once $b; return; }
    throw new \RuntimeException("Missing helper: tried {$a} and {$b}");
};

$try('/helpers/core_helper.php', '/includes/helpers/core_helper.php');
$try('/helpers/approved_timesheets_helper.php', '/includes/helpers/approved_timesheets_helper.php');

// If you also need Pending on this page in the future, add:
// $try('/helpers/pending_timesheet_helper.php', '/includes/helpers/pending_timesheet_helper.php');

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\ApprovedTimesheetsHelper as ApprovedH;

// Context admin + role
$adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
$admin   = Capsule::table('tbladmins')->where('id', $adminId)->first();
$roleId  = $admin ? (int) $admin->roleid : 0;

// Permission: roles that may view ALL approved timesheets
$viewAllRoleIds = ApprovedH::viewAllRoleIds();

// Build map data used by the template
$adminMap      = ApprovedH::adminMap();
$clientMap     = ApprovedH::clientMap();
$departmentMap = ApprovedH::departmentMap();
$taskMap       = ApprovedH::taskMap();

// Listing vs viewing a specific timesheet
$reqAdminId = CoreH::get('admin_id', null);
$reqDate    = CoreH::get('date', null);

$approvedTimesheets = [];
$timesheet          = null;
$timesheetEntries   = [];
$totalTime          = 0.0;

// If a specific timesheet is requested
if ($reqAdminId && $reqDate) {
    $timesheet = ApprovedH::getApprovedTimesheet((int)$reqAdminId, $reqDate, $adminId, $roleId, $viewAllRoleIds);
    if ($timesheet) {
        $timesheetEntries = ApprovedH::getTimesheetEntries((int)$timesheet->id);
        $totalTime        = ApprovedH::sumColumn($timesheetEntries, 'time_spent');
    }
} else {
    // Otherwise list the approved timesheets visible to this admin
    $approvedTimesheets = ApprovedH::listVisibleApproved($adminId, $roleId, $viewAllRoleIds);
}

// Pass to template
$vars = compact(
    'approvedTimesheets',
    'adminMap',
    'clientMap',
    'departmentMap',
    'taskMap',
    'timesheet',
    'timesheetEntries',
    'totalTime'
);

extract($vars);

include __DIR__ . '/../templates/admin/approved_timesheets.tpl';
