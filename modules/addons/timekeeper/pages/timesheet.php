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

/**
 * AJAX: return tickets for a given client_id
 * GET: ?ajax=tickets&client_id=NN
 * Returns: [{id, tid, text}]
 */
// ---- AJAX: return tickets for a given client_id ----
if (($_GET['ajax'] ?? '') === 'tickets') {
    header('Content-Type: application/json; charset=UTF-8');

    $clientId = (int)($_GET['client_id'] ?? 0);
    if ($clientId <= 0) {
        echo json_encode([]);
        exit;
    }

    // Gather client + contacts emails (for email-only tickets)
    $client = Capsule::table('tblclients')->where('id', $clientId)->first(['id','email']);
    $emails = [];
    if ($client && !empty($client->email)) {
        $emails[] = strtolower(trim((string)$client->email));
    }
    $contacts = Capsule::table('tblcontacts')->where('userid', $clientId)->get(['email','id']);
    $contactIds = [];
    foreach ($contacts as $ct) {
        if (!empty($ct->email)) {
            $emails[] = strtolower(trim((string)$ct->email));
        }
        if (!empty($ct->id)) {
            $contactIds[] = (int)$ct->id;
        }
    }
    $emails = array_values(array_unique(array_filter($emails)));

    // Base query: recent tickets linked by userid
    $q = Capsule::table('tbltickets')
        ->where(function ($q) use ($clientId, $contactIds, $emails) {
            // Case 1: tickets with matching userid
            $q->where('userid', $clientId);

            // Case 2: OR tickets linked to any of client's contacts
            if (!empty($contactIds)) {
                $q->orWhereIn('contactid', $contactIds);
            }

            // Case 3: OR email-only tickets where email matches client/contacts
            if (!empty($emails)) {
                $q->orWhere(function ($q2) use ($emails) {
                    // Normalize comparison by lowercasing
                    // Note: WHMCS stores raw email; this comparison is case-insensitive in MySQL by default,
                    // but we still lower both sides to be safe if collation differs.
                    $q2->whereIn(Capsule::raw('LOWER(email)'), $emails);
                });
            }
        })
        ->orderBy('lastreply', 'desc')
        ->orderBy('id', 'desc')
        ->limit(100);

    $tickets = $q->get(['id','tid','title','status','lastreply']);

    $out = [];
    foreach ($tickets as $t) {
        $tid   = (string)($t->tid ?? '');
        $title = (string)($t->title ?? '');
        $label = $tid !== '' ? "Ticket #{$tid}" : "Ticket ID {$t->id}";
        if ($title !== '') {
            // trim long titles for display
            if (function_exists('mb_strimwidth')) {
                $label .= " — " . mb_strimwidth($title, 0, 60, '…', 'UTF-8');
            } else {
                $label .= " — " . (strlen($title) > 60 ? substr($title, 0, 57) . '…' : $title);
            }
        }
        $out[] = [
            'id'   => (int)$t->id,   // store numeric PK
            'tid'  => $tid,          // display number when available
            'text' => $label,
        ];
    }

    echo json_encode($out);
    exit;
}

// ---- Helpers (local) ----

// Ensure today's timesheet exists and return [id,status,date]
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

// Load today's timesheet if it exists
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
// POST HANDLERS FIRST
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
    $tsMeta = tk_load_or_create_today_timesheet($adminId, $today);
    $timesheetId = $tsMeta['id'];

    $editId        = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
    $clientId      = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
    $departmentId  = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;
    $subtaskId     = isset($_POST['task_category_id']) ? (int) $_POST['task_category_id'] : 0;

    // ticket_id will be the numeric tbltickets.id (or empty)
    $ticketId      = isset($_POST['ticket_id']) && $_POST['ticket_id'] !== '' ? (int) $_POST['ticket_id'] : null;

    $description   = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
    $startTime     = isset($_POST['start_time']) ? (string) $_POST['start_time'] : '';
    $endTime       = isset($_POST['end_time']) ? (string) $_POST['end_time'] : '';

    $timeSpent     = isset($_POST['time_spent']) ? (float) $_POST['time_spent'] : 0.0;
    $billable      = isset($_POST['billable']) ? 1 : 0;
    $billableTime  = isset($_POST['billable_time']) ? (float) $_POST['billable_time'] : 0.0;
    $sla           = isset($_POST['sla']) ? 1 : 0;
    $slaTime       = isset($_POST['sla_time']) ? (float) $_POST['sla_time'] : 0.0;

    // Server-side defaults
    if ($billable === 1 && $billableTime <= 0 && $timeSpent > 0) {
        $billableTime = $timeSpent;
    }
    if ($sla === 1 && $slaTime <= 0 && $timeSpent > 0) {
        $slaTime = $timeSpent;
    }
    if ($billable !== 1) { $billableTime = 0.0; }
    if ($sla !== 1)      { $slaTime      = 0.0; }

    // Referential checks
    $clientOk = $clientId > 0 && Capsule::table('tblclients')->where('id', $clientId)->exists();
    $deptOk   = $departmentId > 0 && Capsule::table('mod_timekeeper_departments')
                    ->where('id', $departmentId)->where('status', 'active')->exists();
    $tcOk     = $subtaskId > 0 && Capsule::table('mod_timekeeper_task_categories')
                    ->where('id', $subtaskId)
                    ->where('department_id', $departmentId)
                    ->where('status', 'active')
                    ->exists();

    // If a ticket is supplied, ensure it belongs to the same client
    $ticketOk = true;
    if ($ticketId !== null) {
        $ticketOk = Capsule::table('tbltickets')
            ->where('id', $ticketId)
            ->where('userid', $clientId)
            ->exists();
    }

    if (!$clientOk || !$deptOk || !$tcOk || !$ticketOk) {
        header("Location: addonmodules.php?module=timekeeper&timekeeperpage=timesheet&error=invalid_refs");
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $data = [
        'client_id'        => $clientId,
        'department_id'    => $departmentId,
        'task_category_id' => $subtaskId,
        'ticket_id'        => $ticketId,   // may be null
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
        $data['timesheet_id'] = $timesheetId;
        $data['created_at']   = $now;
        Capsule::table('mod_timekeeper_timesheet_entries')->insert($data);
    }

    header("Location: addonmodules.php?module=timekeeper&timekeeperpage=timesheet");
    exit;
}

// ======================================================================
// GET RENDER PATH
// ======================================================================

$tsMeta = tk_load_today_timesheet($adminId, $today);

if ($tsMeta) {
    $timesheetId     = $tsMeta['id'];
    $timesheetDate   = $tsMeta['date'];
    $timesheetStatus = $tsMeta['status'];

    // Join tickets to fetch public ticket number (tid) for display
    $existingTasks = Capsule::table('mod_timekeeper_timesheet_entries as e')
        ->leftJoin('tbltickets as ti', 'ti.id', '=', 'e.ticket_id')
        ->where('e.timesheet_id', $timesheetId)
        ->orderBy('e.start_time', 'asc')
        ->orderBy('e.end_time', 'asc')
        ->get([
            'e.*',
            'ti.tid as ticket_tid',
        ]);
} else {
    $timesheetId     = 0;
    $timesheetDate   = $today;
    $timesheetStatus = 'not_assigned';
    $existingTasks   = collect([]);
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
