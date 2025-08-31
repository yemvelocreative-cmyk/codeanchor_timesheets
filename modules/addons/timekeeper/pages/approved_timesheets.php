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

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\ApprovedTimesheetsHelper as ApprovedH;

// ---- Context: current admin + role ----
$adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
$admin   = Capsule::table('tbladmins')->where('id', $adminId)->first();
$roleId  = $admin ? (int) $admin->roleid : 0;

// ---- CSRF token for actions ----
if (empty($_SESSION['tk_csrf'])) {
    $_SESSION['tk_csrf'] = bin2hex(random_bytes(16));
}
$tkCsrf = (string) $_SESSION['tk_csrf'];

// ---- Permissions from Settings ----
$viewAllRoleIds  = ApprovedH::viewAllRoleIds();          // respects Settings “View All Timesheets”
$canUnapprove    = ApprovedH::canUnapprove($roleId);     // respects Settings “Approve / Unapprove” roles

// ---- Handle POST actions (Unapprove) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) CoreH::post('tk_action', '');
    $csrf   = (string) CoreH::post('tk_csrf', '');
    if (!hash_equals($tkCsrf, $csrf)) {
        // Invalid token; ignore silently or handle as needed
        header('Location: addonmodules.php?module=timekeeper&timekeeperpage=approved_timesheets');
        exit;
    }

    if ($action === 'unapprove' && $canUnapprove) {
        $tsId = (int) CoreH::post('ts_id', 0);
        if ($tsId > 0) {
            // Load the timesheet to check visibility/ownership
            $ts = Capsule::table('mod_timekeeper_timesheets')
                ->where('id', $tsId)
                ->where('status', 'approved')
                ->first();

            if ($ts) {
                $ownerId = (int) $ts->admin_id;
                $viewerHasViewAll = in_array($roleId, $viewAllRoleIds, true);
                $viewerCanSee = $viewerHasViewAll || ($ownerId === $adminId);

                if ($viewerCanSee) {
                    Capsule::table('mod_timekeeper_timesheets')
                        ->where('id', $tsId)
                        ->update([
                            'status'     => 'pending',
                            // Optional audit fields if you have them:
                            // 'unapproved_by' => $adminId,
                            // 'unapproved_at' => Capsule::raw('NOW()'),
                        ]);
                }
            }
        }
        // PRG pattern
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

$approvedTimesheets = [];
$timesheet          = null;
$timesheetEntries   = [];
$totalTime          = 0.0;

if ($reqAdminId && $reqDate) {
    // Respect "view all" when opening a specific sheet
    $reqAdminId = (int) $reqAdminId;
    $reqDate    = (string) $reqDate;

    $timesheet = ApprovedH::getApprovedTimesheet(
        $reqAdminId, $reqDate, $adminId, $roleId, $viewAllRoleIds
    );

    if ($timesheet) {
        $timesheetEntries = ApprovedH::getTimesheetEntries((int)$timesheet->id);
        $totalTime        = ApprovedH::sumColumn($timesheetEntries, 'time_spent');
    }
} else {
    // List only the approved timesheets visible to this admin/role
    $approvedTimesheets = ApprovedH::listVisibleApproved(
        $adminId, $roleId, $viewAllRoleIds
    );
}

// ---- Pass to template ----
$vars = compact(
    'approvedTimesheets',
    'adminMap',
    'clientMap',
    'departmentMap',
    'taskMap',
    'timesheet',
    'timesheetEntries',
    'totalTime',
    'tkCsrf',
    'canUnapprove'
);

extract($vars);

// NOTE: template file name is singular per your project: approved_timesheet.tpl
include __DIR__ . '/../templates/admin/approved_timesheet.tpl';
