<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Access Denied');
}

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

// ======================================================================
// POST HANDLERS FIRST (avoid headers already sent issues)
// ======================================================================

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

// Delete entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];

    Capsule::table('mod_timekeeper_timesheet_entries')
        ->where('id', $deleteId)
        ->delete();

    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=timesheet");
    exit;
}

// Add / Update entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    // Ensure today's timesheet exists to attach entries to
    $tsMeta = tk_load_or_create_today_timesheet($adminId, $today);
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

    // Normalize dependent fields
    if ($billable !== 1) { $billableTime = 0.0; }
    if ($sla !== 1)      { $slaTime      = 0.0; }

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
        // Update
        Capsule::table('mod_timekeeper_timesheet_entries')
            ->where('id', $editId)
            ->update($data);
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
$tsMeta = tk_load_today_timesheet($adminId, $today);

if ($tsMeta) {
    $timesheetId     = $tsMeta['id'];
    $timesheetDate   = $tsMeta['date'];
    $timesheetStatus = $tsMeta['status'];

    $existingTasks = Capsule::table('mod_timekeeper_timesheet_entries')
        ->where('timesheet_id', $timesheetId)
        ->get();
} else {
    // No timesheet exists yet for today
    $timesheetId     = 0;
    $timesheetDate   = $today;
    $timesheetStatus = 'not_assigned'; // your template already supports this state
    $existingTasks   = collect([]);    // empty Laravel collection (keeps foreach happy)
}

$totalTime = 0.0;
$totalBillableTime = 0.0;
$totalSlaTime = 0.0;

foreach ($existingTasks as $entry) {
    $totalTime += (float) $entry->time_spent;
    if ((int) $entry->billable === 1) {
        $totalBillableTime += (float) $entry->billable_time;
    }
    if ((int) $entry->sla === 1) {
        $totalSlaTime += (float) $entry->sla_time;
    }
}

// Dropdown data
$clients        = Capsule::table('tblclients')->orderBy('companyname')->get();
$departments    = Capsule::table('mod_timekeeper_departments')->where('status', 'active')->orderBy('name')->get();
$taskCategories = Capsule::table('mod_timekeeper_task_categories')->where('status', 'active')->orderBy('name')->get();
$adminUser      = Capsule::table('tbladmins')->find($adminId);
$adminName      = $adminUser ? ($adminUser->firstname . ' ' . $adminUser->lastname) : 'Unknown';

// Format totals for display
$totalTime         = number_format($totalTime, 2);
$totalBillableTime = number_format($totalBillableTime, 2);
$totalSlaTime      = number_format($totalSlaTime, 2);

// Render template (we're using it as a PHP partial)
ob_start();
include __DIR__ . '/../templates/admin/timesheet.tpl';
$content = ob_get_clean();
echo $content;
