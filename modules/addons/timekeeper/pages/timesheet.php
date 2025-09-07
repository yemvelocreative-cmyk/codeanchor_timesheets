<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Access Denied');
}

$base = dirname(__DIR__); // /modules/addons/timekeeper
require_once $base . '/includes/helpers/core_helper.php';
require_once $base . '/includes/helpers/timesheet_helper.php';

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\TimesheetHelper as TSH;

// ---- Auth / session ----
$adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
if ($adminId <= 0) {
    echo "Admin session not found.";
    exit;
}

// ---- Date context (today) ----
$today = date('Y-m-d');

// Helper: ensure today's timesheet exists and return [id,status,date]
function tk_load_or_create_today_timesheet(int $adminId, string $today): array
{
    $ts = Capsule::table('mod_timekeeper_timesheets')
        ->where('admin_id', $adminId)
        ->where('timesheet_date', $today)
        ->first();

    if ($ts) {
        return [
            'id'     => (int) $ts->id,
            'status' => (string) $ts->status,
            'date'   => $ts->timesheet_date ?: $today,
        ];
    }

    $now = date('Y-m-d H:i:s');
    $newId = (int) Capsule::table('mod_timekeeper_timesheets')->insertGetId([
        'admin_id'       => $adminId,
        'timesheet_date' => $today,
        'status'         => 'pending',
        'created_at'     => $now,
        'updated_at'     => $now,
    ]);

    return [
        'id'     => $newId,
        'status' => 'pending',
        'date'   => $today,
    ];
}

// Helper: load today's timesheet if it exists
function tk_load_today_timesheet(int $adminId, string $today): ?array
{
    $ts = Capsule::table('mod_timekeeper_timesheets')
        ->where('admin_id', $adminId)
        ->where('timesheet_date', $today)
        ->first();

    if ($ts) {
        return [
            'id'     => (int) $ts->id,
            'status' => (string) $ts->status,
            'date'   => $ts->timesheet_date ?: $today,
        ];
    }
    return null;
}

// ======================================================================
// POST HANDLERS FIRST (avoid headers already sent issues)
// ======================================================================

// DELETE entry (guarded: must belong to today's timesheet for this admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];

    $entry = Capsule::table('mod_timekeeper_timesheet_entries as e')
        ->join('mod_timekeeper_timesheets as t', 't.id', '=', 'e.timesheet_id')
        ->where('e.id', $deleteId)
        ->where('t.admin_id', $adminId)
        ->where('t.timesheet_date', $today)
        ->first();

    if ($entry) {
        Capsule::table('mod_timekeeper_timesheet_entries')->where('id', $deleteId)->delete();
    }

    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=timesheet");
    exit;
}

// ADD / UPDATE entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    // Ensure today's timesheet exists to attach entries to
    $tsMeta = TSH::loadOrCreateTodayTimesheet($adminId, $today);
    $timesheetId = $tsMeta['id'];

    $editId        = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
    $clientId      = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
    $departmentId  = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;
    $subtaskId     = isset($_POST['task_category_id']) ? (int) $_POST['task_category_id'] : 0;

    $ticketId      = isset($_POST['ticket_id']) ? trim((string) $_POST['ticket_id']) : '';
    $description   = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
    $startTime     = isset($_POST['start_time']) ? (string) $_POST['start_time'] : '';
    $endTime       = isset($_POST['end_time']) ? (string) $_POST['end_time'] : '';

    $timeSpent     = isset($_POST['time_spent']) ? (float) $_POST['time_spent'] : 0.0;
    $billable      = isset($_POST['billable']) ? 1 : 0;
    $billableTime  = isset($_POST['billable_time']) ? (float) $_POST['billable_time'] : 0.0;
    $sla           = isset($_POST['sla']) ? 1 : 0;
    $slaTime       = isset($_POST['sla_time']) ? (float) $_POST['sla_time'] : 0.0;

    // --- Server-side defaults (JS mirrors this, but enforce here too)
    if ($billable === 1 && $billableTime <= 0 && $timeSpent > 0) {
        $billableTime = $timeSpent;
    }
    if ($sla === 1 && $slaTime <= 0 && $timeSpent > 0) {
        $slaTime = $timeSpent;
    }

    // Normalize dependent fields if checkboxes off
    if ($billable !== 1) { $billableTime = 0.0; }
    if ($sla !== 1)      { $slaTime      = 0.0; }

    // --- Referential checks (lightweight)
    $clientOk = $clientId > 0 && Capsule::table('tblclients')->where('id', $clientId)->exists();
    $deptOk   = $departmentId > 0 && Capsule::table('mod_timekeeper_departments')
                    ->where('id', $departmentId)->where('status', 'active')->exists();
    $tcOk     = $subtaskId > 0 && Capsule::table('mod_timekeeper_task_categories')
                    ->where('id', $subtaskId)
                    ->where('department_id', $departmentId)
                    ->where('status', 'active')
                    ->exists();

    if (!$clientOk || !$deptOk || !$tcOk) {
        header("Location: addonmodules.php?module=timekeeper&timekeeperpage=timesheet&error=invalid_refs");
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $data = [
        'client_id'        => $clientId,
        'department_id'    => $departmentId,
        'task_category_id' => $subtaskId,
        'ticket_id'        => $ticketId,
        'description'      => $description,
        'start_time'       => $startTime,
        'end_time'         => $endTime,
        'time_spent'       => $timeSpent,
        'billable'         => $billable,
        'billable_time'    => $billableTime,
        'sla'              => $sla,
        'sla_time'         => $slaTime,
        'updated_at'       => $now,
    ];

    if ($editId > 0) {
        // Guard: ensure entry belongs to this admin & today's timesheet
        $owned = Capsule::table('mod_timekeeper_timesheet_entries as e')
            ->join('mod_timekeeper_timesheets as t', 't.id', '=', 'e.timesheet_id')
            ->where('e.id', $editId)
            ->where('t.admin_id', $adminId)
            ->where('t.timesheet_date', $today)
            ->exists();

        if ($owned) {
            Capsule::table('mod_timekeeper_timesheet_entries')->where('id', $editId)->update($data);
        }
    } else {
        // Insert
        $data['timesheet_id'] = $timesheetId;
        $data['created_at']   = $now;
        Capsule::table('mod_timekeeper_timesheet_entries')->insert($data);
    }

    // Prevent resubmission
    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=timesheet");
    exit;
}

// ======================================================================
// GET RENDER PATH
// ======================================================================

// Load-only for GET (do NOT create on visit)
$tsMeta = TSH::loadTodayTimesheet($adminId, $today);
if ($tsMeta) {
    $timesheetId     = $tsMeta['id'];
    $timesheetDate   = $tsMeta['date'];
    $timesheetStatus = $tsMeta['status'];

    $existingTasks = Capsule::table('mod_timekeeper_timesheet_entries')
        ->where('timesheet_id', $timesheetId)
        ->orderBy('start_time', 'asc')
        ->orderBy('end_time', 'asc')
        ->get();
} else {
    // No timesheet exists yet for today
    $timesheetId     = 0;
    $timesheetDate   = $today;
    $timesheetStatus = 'not_assigned'; // template supports this
    $existingTasks   = collect([]);    // empty collection
}

// Dropdown data
$clients        = Capsule::table('tblclients')->orderBy('companyname')->get();
$departments    = Capsule::table('mod_timekeeper_departments')->where('status', 'active')->orderBy('name')->get();
$taskCategories = Capsule::table('mod_timekeeper_task_categories')->where('status', 'active')->orderBy('name')->get();
$adminUser      = Capsule::table('tbladmins')->find($adminId);
$adminName      = $adminUser ? ($adminUser->firstname . ' ' . $adminUser->lastname) : 'Unknown';

// Render template
ob_start();
include __DIR__ . '/../templates/admin/timesheet.tpl';
$content = ob_get_clean();
echo $content;
