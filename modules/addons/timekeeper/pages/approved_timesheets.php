<?php

if (!defined("WHMCS")) { die("Access Denied"); }

// --- Load helpers ---
require_once __DIR__ . '/../helpers/core_helper.php';
require_once __DIR__ . '/../helpers/approved_timesheets_helper.php';

use WHMCS\Database\Capsule;
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
