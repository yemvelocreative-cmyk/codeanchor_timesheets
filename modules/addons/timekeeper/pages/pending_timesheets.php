<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Access Denied');
}

require_once __DIR__ . '/../includes/helpers/core_helper.php';
require_once __DIR__ . '/../includes/helpers/pending_timesheet_helper.php';

use Timekeeper\Helpers\CoreHelper as Core;
use Timekeeper\Helpers\PendingTimesheetHelper as PendingH;

// ---- Session / admin ----
$adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
if ($adminId <= 0) {
    echo "Admin session not found.";
    exit;
}

// ---- Admin + role ----
$admin = Capsule::table('tbladmins')->where('id', $adminId)->first();
$adminRoleId = $admin ? (int) $admin->roleid : 0;

// ---- Permissions ----
$allowedViewCsv = Capsule::table('mod_timekeeper_permissions')
    ->where('setting_key', 'permission_pending_timesheets_view_all')
    ->value('setting_value');
$allowedViewRoles    = PendingH::viewAllRoles();

$allowedApproveCsv = Capsule::table('mod_timekeeper_permissions')
    ->where('setting_key', 'permission_pending_timesheets_approve')
    ->value('setting_value');
$allowedApprovalRoles = PendingH::approveRoles();

$canApprove = in_array($adminRoleId, $allowedApprovalRoles, true);

// Optional validation threshold for unbilled time
$unbilledTimeValidateMin = Capsule::table('mod_timekeeper_permissions')
    ->where('setting_key', 'unbilled_time_validate_min')
    ->value('setting_value');
$unbilledTimeValidateMin = ($unbilledTimeValidateMin === '' || $unbilledTimeValidateMin === null)
    ? null
    : (float) $unbilledTimeValidateMin;

// ======================================================================
// POST HANDLERS (before any output)
// ======================================================================

// Re-Submit a rejected timesheet -> pending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resubmit_timesheet_id'])) {
    $resubmitId = (int) $_POST['resubmit_timesheet_id'];

    $upd = ['status' => 'pending'];
    if (Core::hasCol('mod_timekeeper_timesheets', 'updated_at')) {
        $upd['updated_at'] = date('Y-m-d H:i:s');
    }
    Capsule::table('mod_timekeeper_timesheets')
        ->where('id', $resubmitId)
        ->where('status', 'rejected')
        ->update($upd);

    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&resubmitted=1&timesheet_id={$resubmitId}");
    exit;
}

// Save (update) an entry from the detail view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_id'])) {
    $entryId = (int) $_POST['save_id'];

    // Normalize booleans/times
    $billable     = !empty($_POST['billable']) ? 1 : 0;
    $billableTime = ($billable && $_POST['billable_time'] !== '') ? (float) $_POST['billable_time'] : 0.0;
    $sla          = !empty($_POST['sla']) ? 1 : 0;
    $slaTime      = ($sla && $_POST['sla_time'] !== '') ? (float) $_POST['sla_time'] : 0.0;

    Capsule::table('mod_timekeeper_timesheet_entries')
        ->where('id', $entryId)
        ->update([
            'client_id'        => (int) ($_POST['client_id'] ?? 0),
            'department_id'    => (int) ($_POST['department_id'] ?? 0),
            'task_category_id' => (int) ($_POST['task_category_id'] ?? 0),
            'ticket_id'        => trim((string) ($_POST['ticket_id'] ?? '')), // now stores TID or empty
            'description'      => trim((string) ($_POST['description'] ?? '')),
            'start_time'       => (string) ($_POST['start_time'] ?? ''),
            'end_time'         => (string) ($_POST['end_time'] ?? ''),
            'time_spent'       => (float) ($_POST['time_spent'] ?? 0),
            'billable'         => $billable,
            'billable_time'    => $billableTime,
            'sla'              => $sla,
            'sla_time'         => $slaTime,
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

    // Preserve context
    $ctxAdmin = (int) ($_POST['admin_id'] ?? 0);
    $ctxDate  = urlencode((string) ($_POST['timesheet_date'] ?? ''));
    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id={$ctxAdmin}&date={$ctxDate}&saved=1");
    exit;
}

// Add a new line to a specific pending/rejected timesheet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_entry'])) {
    $targetAdminId = (int) ($_POST['admin_id'] ?? 0);
    $targetDate    = (string) ($_POST['timesheet_date'] ?? '');

    $ts = Capsule::table('mod_timekeeper_timesheets')
        ->where('admin_id', $targetAdminId)
        ->where('timesheet_date', $targetDate)
        ->whereIn('status', ['pending', 'rejected'])
        ->first();

    if (!$ts) {
        header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id={$targetAdminId}&date={$targetDate}&add_error=1");
        exit;
    }

    $clientId     = (int) ($_POST['client_id'] ?? 0);
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $subtaskId    = (int) ($_POST['task_category_id'] ?? 0);
    $ticketTid    = trim((string) ($_POST['ticket_id'] ?? '')); // store TID (public ticket code) or empty
    $description  = trim((string) ($_POST['description'] ?? ''));
    $startTime    = (string) ($_POST['start_time'] ?? '');
    $endTime      = (string) ($_POST['end_time'] ?? '');
    $billable     = !empty($_POST['billable']) ? 1 : 0;
    $billableTime = ($billable && $_POST['billable_time'] !== '') ? (float) $_POST['billable_time'] : 0.0;
    $sla          = !empty($_POST['sla']) ? 1 : 0;
    $slaTime      = ($sla && $_POST['sla_time'] !== '') ? (float) $_POST['sla_time'] : 0.0;

    // Compute hours.decimal (basic)
    $timeSpent = 0.0;
    if ($startTime !== '' && $endTime !== '') {
        $st = strtotime($startTime);
        $et = strtotime($endTime);
        if ($st !== false && $et !== false && $et > $st) {
            $timeSpent = round((($et - $st) / 60) / 60, 2);
        } else {
            header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id={$targetAdminId}&date={$targetDate}&add_error=1");
            exit;
        }
    }

    $now = date('Y-m-d H:i:s');
    Capsule::table('mod_timekeeper_timesheet_entries')->insert([
        'timesheet_id'      => (int) $ts->id,
        'client_id'         => $clientId,
        'department_id'     => $departmentId,
        'task_category_id'  => $subtaskId,
        'ticket_id'         => $ticketTid, // store TID string for consistency with Timesheet
        'description'       => $description,
        'start_time'        => $startTime,
        'end_time'          => $endTime,
        'time_spent'        => $timeSpent,
        'billable'          => $billable,
        'billable_time'     => $billableTime,
        'sla'               => $sla,
        'sla_time'          => $slaTime,
        'created_at'        => $now,
        'updated_at'        => $now,
    ]);

    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id={$targetAdminId}&date={$targetDate}&added=1");
    exit;
}

// Approve (only if role allowed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_timesheet_id']) && $canApprove) {
    $timesheetId = (int) $_POST['approve_timesheet_id'];

    $now = date('Y-m-d H:i:s');
    $update = [
        'status' => 'approved',
        'approved_at' => $now,
        'approved_by' => $adminId,
    ];
    if (Core::hasCol('mod_timekeeper_timesheets', 'updated_at')) {
        $update['updated_at'] = $now;
    }
    Capsule::table('mod_timekeeper_timesheets')
        ->where('id', $timesheetId)
        ->update($update);

    // Update per-entry "no_billing_verified"
    $entries = Capsule::table('mod_timekeeper_timesheet_entries')
        ->where('timesheet_id', $timesheetId)
        ->get();

    foreach ($entries as $entry) {
        $verifyKey = 'verify_unbilled_' . (int) $entry->id;
        $checked = !empty($_POST[$verifyKey]) && $_POST[$verifyKey] === '1';
        Capsule::table('mod_timekeeper_timesheet_entries')
            ->where('id', (int) $entry->id)
            ->update([
                'no_billing_verified'    => $checked ? 1 : 0,
                'no_billing_verified_at' => $checked ? date('Y-m-d H:i:s') : null,
                'no_billing_verified_by' => $checked ? $adminId : null,
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);
    }

    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&approved=1");
    exit;
}

// Reject (only if role allowed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_timesheet_id']) && $canApprove) {
    $timesheetId = (int) $_POST['reject_timesheet_id'];
    $adminRejectionNote = trim((string) ($_POST['admin_rejection_note'] ?? ''));

    $now = date('Y-m-d H:i:s');
    $update = [
        'status'               => 'rejected',
        'admin_rejection_note' => $adminRejectionNote,
        'rejected_at'          => $now,
        'rejected_by'          => $adminId
    ];
    if (Core::hasCol('mod_timekeeper_timesheets', 'updated_at')) {
        $update['updated_at'] = $now;
    }

    Capsule::table('mod_timekeeper_timesheets')
        ->where('id', $timesheetId)
        ->update($update);

    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&rejected=1");
    exit;
}

// ======================================================================
// GET: list + optional detail context
// ======================================================================

$pendingTimesheets = PendingH::baseQuery($adminId, $adminRoleId)
    ->orderBy('timesheet_date', 'desc')
    ->get();

// Admin names map (Firstname Lastname)
$admins = Capsule::table('tbladmins')->get(['id','firstname','lastname']);
$adminMap = [];
foreach ($admins as $a) {
    $adminMap[(int)$a->id] = trim(($a->firstname ?? '') . ' ' . ($a->lastname ?? '')) ?: 'Admin #' . (int)$a->id;
}

// Lookup maps
$clients = Capsule::table('tblclients')->get(['id','companyname','firstname','lastname']);
$clientMap = [];
$clientIds = [];
foreach ($clients as $c) {
    $name = $c->companyname ?: trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? ''));
    $clientMap[(int)$c->id] = $name;
    $clientIds[] = (int)$c->id;
}

$departments = Capsule::table('mod_timekeeper_departments')->orderBy('name')->get(['id','name']);
$departmentMap = [];
foreach ($departments as $d) { $departmentMap[(int)$d->id] = (string)$d->name; }

$taskCategories = Capsule::table('mod_timekeeper_task_categories')->orderBy('name')->get(['id','name','department_id']);
$taskMap = [];
foreach ($taskCategories as $t) { $taskMap[(int)$t->id] = (string)$t->name; }

// Detail view (admin_id + date)
$editMode             = false;
$editTimesheetEntries = [];
$editAdminId          = null;
$editTimesheetDate    = '';
$editAdminName        = '';
$editingEntryId       = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : null;
$ticketIdMap = []; 

if (!empty($_GET['admin_id']) && !empty($_GET['date'])) {
    $editMode          = true;
    $editAdminId       = (int) $_GET['admin_id'];
    $editTimesheetDate = (string) $_GET['date'];
    $editAdminName     = $adminMap[$editAdminId] ?? 'Unknown';

    $timesheet = Capsule::table('mod_timekeeper_timesheets')
        ->where('admin_id', $editAdminId)
        ->where('timesheet_date', $editTimesheetDate)
        ->first();

    if ($timesheet) {
        $editTimesheetEntries = PendingH::entriesSorted((int)$timesheet->id);

        // --- NEW: map non-numeric ticket TIDs to admin ticket IDs for linking ---
        $ticketIdMap = [];
        if (!empty($editTimesheetEntries)) {
            $tids = [];
            foreach ($editTimesheetEntries as $e) {
                $val = trim((string)($e->ticket_id ?? ''));
                if ($val !== '' && !ctype_digit($val)) {
                    $tids[$val] = true; // unique TIDs only
                }
            }
            if (!empty($tids)) {
                $rows = Capsule::table('tbltickets')
                    ->whereIn('tid', array_keys($tids))
                    ->get(['id', 'tid']);
                foreach ($rows as $r) {
                    $ticketIdMap[(string)$r->tid] = (int)$r->id;
                }
            }
        }
    } else {
        $ticketIdMap = []; // keep defined
    }
}

// ---------- Tickets by client (for client-scoped select) ----------
$ticketsByClient = [];
if (!empty($clientIds)) {
    // Pull tickets for known clients; prefer newest first
    // Columns: id (internal), tid (public/code), userid (client id), title
    $tickets = Capsule::table('tbltickets')
        ->whereIn('userid', $clientIds)
        ->orderBy('lastreply', 'desc')
        ->orderBy('date', 'desc')
        ->get(['id','tid','userid','title']);

    // Group + soft-limit per client to avoid massive payloads
    foreach ($tickets as $t) {
        $uid = (int)$t->userid;
        $tid = (string)($t->tid ?: $t->id); // prefer public TID; fallback to internal id
        $title = trim((string)$t->title ?: '');
        $ticketsByClient[$uid] = $ticketsByClient[$uid] ?? [];
        if (count($ticketsByClient[$uid]) < 50) { // keep top 50 per client
            $ticketsByClient[$uid][] = [
                'tid'   => $tid,
                'id'    => (int)$t->id,
                'title' => $title,
            ];
        }
    }
}

// Export variables to template
$vars = compact(
    'pendingTimesheets',
    'adminMap',
    'clientMap',
    'departmentMap',
    'taskMap',
    'taskCategories',
    'editMode',
    'editTimesheetEntries',
    'editAdminId',
    'editTimesheetDate',
    'editAdminName',
    'editingEntryId',
    'canApprove',
    'unbilledTimeValidateMin',
    'ticketsByClient',
    'ticketIdMap'
);

extract($vars);

// Render template
include __DIR__ . '/../templates/admin/pending_timesheets.tpl';
