<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("Access Denied");
}

// Handle Re-Submit Timesheet action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resubmit_timesheet_id'])) {
    $resubmitId = (int) $_POST['resubmit_timesheet_id'];

    // Update status from 'rejected' to 'pending' using Capsule ORM
    Capsule::table('mod_timekeeper_timesheets')
        ->where('id', $resubmitId)
        ->where('status', 'rejected')
        ->update(['status' => 'pending']);

    // Redirect with feedback
    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&resubmitted=1&timesheet_id=" . $resubmitId);
    exit;
}

// Get current admin info
$adminId = $_SESSION['adminid'];
$admin = Capsule::table('tbladmins')->where('id', $adminId)->first();
$adminRoleId = $admin->roleid;

// Get allowed roles from permissions
$saved = Capsule::table('mod_timekeeper_permissions')
    ->where('setting_key', 'permission_pending_timesheets_view_all')
    ->value('setting_value');
$allowedRoles = $saved ? explode(',', $saved) : [];

// Get allowed approval roles from permissions
$savedApproval = Capsule::table('mod_timekeeper_permissions')
    ->where('setting_key', 'permission_pending_timesheets_approve')
    ->value('setting_value');
$allowedApprovalRoles = $savedApproval ? explode(',', $savedApproval) : [];
if (!is_array($allowedApprovalRoles)) $allowedApprovalRoles = [];

// Get current admin's role ID
$adminRoleId = $admin->roleid;

// Check if admin is allowed to approve
$canApprove = in_array($adminRoleId, $allowedApprovalRoles);

// Validation for minimum time spent required
$unbilledTimeValidateMin = Capsule::table('mod_timekeeper_permissions')
    ->where('setting_key', 'unbilled_time_validate_min')
    ->value('setting_value');
// Do NOT set a fallback default. Let it remain NULL if unset.
if ($unbilledTimeValidateMin !== null && $unbilledTimeValidateMin !== '') {
    $unbilledTimeValidateMin = floatval($unbilledTimeValidateMin);
} else {
    $unbilledTimeValidateMin = null; // Not set
}

// Build pending timesheets query with role-based filter
$pendingTimesheetsQuery = Capsule::table('mod_timekeeper_timesheets')
    ->whereIn('status', ['pending', 'rejected'])
    ->where('timesheet_date', '<', date('Y-m-d'));

if (!in_array($adminRoleId, $allowedRoles)) {
    $pendingTimesheetsQuery->where('admin_id', $adminId);
}

$pendingTimesheets = $pendingTimesheetsQuery->orderBy('timesheet_date', 'desc')->get();

// Fetch admin names
$adminMap = Capsule::table('tbladmins')
    ->pluck('firstname', 'id')
    ->toArray();

// Lookup maps
$clients = Capsule::table('tblclients')->get();
$clientMap = [];
foreach ($clients as $c) {
    $clientMap[$c->id] = $c->companyname ?: ($c->firstname . ' ' . $c->lastname);
}

$departments = Capsule::table('mod_timekeeper_departments')->get();
$departmentMap = [];
foreach ($departments as $d) {
    $departmentMap[$d->id] = $d->name;
}

$taskCategories = Capsule::table('mod_timekeeper_task_categories')->get();
$taskMap = [];
foreach ($taskCategories as $t) {
    $taskMap[$t->id] = $t->name;
}

// Edit mode variables
$editMode = false;
$editTimesheetEntries = [];
$editAdminId = null;
$editTimesheetDate = '';
$editAdminName = '';
$editingEntryId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : null;

// Handle save action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_id'])) {
    $entryId = (int) $_POST['save_id'];

    // Extract and sanitize values
    $billable = isset($_POST['billable']) ? 1 : 0;
    $billableTime = isset($_POST['billable_time']) ? floatval($_POST['billable_time']) : 0;
    $sla = isset($_POST['sla']) ? 1 : 0;
    $slaTime = isset($_POST['sla_time']) ? floatval($_POST['sla_time']) : 0;

    // Force time fields to zero if not checked
    if (!$billable) {
        $billableTime = 0;
    }
    if (!$sla) {
        $slaTime = 0;
    }

    Capsule::table('mod_timekeeper_timesheet_entries')
        ->where('id', $entryId)
        ->update([
            'client_id' => $_POST['client_id'],
            'department_id' => $_POST['department_id'],
            'task_category_id' => $_POST['task_category_id'],
            'ticket_id' => $_POST['ticket_id'],
            'description' => $_POST['description'],
            'start_time' => $_POST['start_time'],
            'end_time' => $_POST['end_time'],
            'time_spent' => $_POST['time_spent'],
            'billable' => $billable,
            'billable_time' => $billableTime,
            'sla' => $sla,
            'sla_time' => $slaTime,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    // Reload context after save
    $_GET['admin_id'] = $_POST['admin_id'];
    $_GET['date'] = $_POST['timesheet_date'];
}

/* ============================ NEW: Add New Line ============================
   Adds a new entry to the selected (pending or rejected) timesheet when
   viewing it via admin_id + date. Computes time_spent in hours.decimal.
   Redirects back with &added=1 or &add_error=1.
--------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_entry'])) {
    $targetAdminId   = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;
    $targetDate      = isset($_POST['timesheet_date']) ? trim($_POST['timesheet_date']) : '';

    // Locate the target timesheet (pending or rejected — matches what you list)
    $ts = Capsule::table('mod_timekeeper_timesheets')
        ->where('admin_id', $targetAdminId)
        ->where('timesheet_date', $targetDate)
        ->whereIn('status', ['pending', 'rejected'])
        ->first();

    if (!$ts) {
        header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id={$targetAdminId}&date={$targetDate}&add_error=1");
        exit;
    }

    // Collect fields (align with your schema)
    $clientId     = isset($_POST['client_id']) ? (int) $_POST['client_id'] : null;
    $departmentId = isset($_POST['department_id']) ? (int) $_POST['department_id'] : null;
    $subtaskId    = isset($_POST['task_category_id']) ? (int) $_POST['task_category_id'] : null;
    $ticketId     = isset($_POST['ticket_id']) ? trim($_POST['ticket_id']) : '';
    $description  = isset($_POST['description']) ? trim($_POST['description']) : '';
    $startTime    = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
    $endTime      = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
    $billable     = !empty($_POST['billable']) ? 1 : 0;
    $billableTime = (isset($_POST['billable_time']) && $_POST['billable_time'] !== '') ? (float) $_POST['billable_time'] : 0.0;
    $sla          = !empty($_POST['sla']) ? 1 : 0;
    $slaTime      = (isset($_POST['sla_time']) && $_POST['sla_time'] !== '') ? (float) $_POST['sla_time'] : 0.0;

    // Compute time_spent in HOURS.decimal (template displays number_format(..., 2) hrs)
    $timeSpent = 0.0;
    if ($startTime !== '' && $endTime !== '') {
        $st = strtotime($startTime);
        $et = strtotime($endTime);
        if ($st !== false && $et !== false && $et > $st) {
            $mins = ($et - $st) / 60;
            $timeSpent = round($mins / 60, 2);
        } else {
            header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id={$targetAdminId}&date={$targetDate}&add_error=1");
            exit;
        }
    }

    Capsule::table('mod_timekeeper_timesheet_entries')->insert([
        'timesheet_id'  => $ts->id,
        'client_id'     => $clientId,
        'department_id' => $departmentId,
        'task_category_id'    => $subtaskId,
        'ticket_id'     => $ticketId,
        'description'   => $description,
        'start_time'    => $startTime,
        'end_time'      => $endTime,
        'time_spent'    => $timeSpent,     // hours.decimal
        'billable'      => $billable,
        'billable_time' => $billableTime,  // hours.decimal
        'sla'           => $sla,
        'sla_time'      => $slaTime,       // hours.decimal
        'created_at'    => date('Y-m-d H:i:s'),
        'updated_at'    => date('Y-m-d H:i:s'),
    ]);

    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id={$targetAdminId}&date={$targetDate}&added=1");
    exit;
}
/* ========================== END NEW: Add New Line ========================= */

// Handle approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_timesheet_id']) && $canApprove) {
    $timesheetId = (int) $_POST['approve_timesheet_id'];
    $adminId = $_SESSION['adminid'];

    // 1. Update the timesheet itself
    Capsule::table('mod_timekeeper_timesheets')
        ->where('id', $timesheetId)
        ->update([
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $adminId
        ]);

    // 2. Fetch all entries for this timesheet
    $entries = Capsule::table('mod_timekeeper_timesheet_entries')
        ->where('timesheet_id', $timesheetId)
        ->get();

    // 3. Update each entry that requires 'no_billing_verified'
    foreach ($entries as $entry) {
        $verifyKey = 'verify_unbilled_' . $entry->id;
        if (
            isset($_POST[$verifyKey])
            && $_POST[$verifyKey] == '1'
        ) {
            Capsule::table('mod_timekeeper_timesheet_entries')
                ->where('id', $entry->id)
                ->update([
                    'no_billing_verified' => 1,
                    'no_billing_verified_at' => date('Y-m-d H:i:s'),
                    'no_billing_verified_by' => $adminId,
                ]);
        } else {
            // If not checked, ensure it's reset to 0
            Capsule::table('mod_timekeeper_timesheet_entries')
                ->where('id', $entry->id)
                ->update([
                    'no_billing_verified' => 0,
                    'no_billing_verified_at' => null,
                    'no_billing_verified_by' => null,
                ]);
        }
    }

    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&approved=1");
    exit;
}

// Handle reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_timesheet_id']) && $canApprove) {
    $timesheetId = (int) $_POST['reject_timesheet_id'];
    $adminRejectionNote = isset($_POST['admin_rejection_note']) ? trim($_POST['admin_rejection_note']) : '';
    Capsule::table('mod_timekeeper_timesheets')
        ->where('id', $timesheetId)
        ->update([
            'status'               => 'rejected',
            'admin_rejection_note' => $adminRejectionNote,
            'rejected_at'          => date('Y-m-d H:i:s'),
            'rejected_by'          => $adminId // current admin's ID
        ]);
    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&rejected=1");
    exit;
}

// Handle loading a specific timesheet
if (!empty($_GET['admin_id']) && !empty($_GET['date'])) {
    $editMode = true;
    $editAdminId = (int) $_GET['admin_id'];
    $editTimesheetDate = $_GET['date'];
    $editAdminName = $adminMap[$editAdminId] ?? 'Unknown';

    $timesheet = Capsule::table('mod_timekeeper_timesheets')
        ->where('admin_id', $editAdminId)
        ->where('timesheet_date', $editTimesheetDate)
        ->first();

    if ($timesheet) {
        $editTimesheetEntries = Capsule::table('mod_timekeeper_timesheet_entries')
            ->where('timesheet_id', $timesheet->id)
            ->get();
    }
}

$editingEntryId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : null;

$vars = compact(
    'pendingTimesheets',
    'adminMap',
    'clientMap',
    'departmentMap',
    'taskMap',
    'editMode',
    'editTimesheetEntries',
    'editAdminId',
    'editTimesheetDate',
    'editAdminName',
    'editingEntryId',
    'canApprove'
);

extract($vars);

include __DIR__ . '/../templates/admin/pending_timesheets.tpl';
