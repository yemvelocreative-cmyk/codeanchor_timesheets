<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Access denied');
}

$modulelink = "addonmodules.php?module=timekeeper&timekeeperpage=departments";
$message = "";

/**
 * Build a bootstrap-ish alert message (kept simple)
 */
function tk_flash(string $type, string $text): string {
    $cls = $type === 'error' ? 'alert-danger' : 'alert-success';
    return '<div class="alert ' . $cls . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>';
}

/**
 * Redirect helper
 */
function tk_redirect(string $url): void {
    if (!headers_sent()) {
        header("Location: {$url}");
        exit;
    }
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    exit;
}

/* ==============================
   POST: Add / Edit
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // check_token(); // (disabled in dev)
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            tk_redirect($modulelink . "&error=empty");
        }

        try {
            Capsule::table('mod_timekeeper_departments')->insert([
                'name'       => $name,
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            tk_redirect($modulelink . "&success=1");
        } catch (\Throwable $e) {
            // Handle unique constraint violations gracefully
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'uq_department_name') !== false) {
                tk_redirect($modulelink . "&error=duplicate");
            }
            tk_redirect($modulelink . "&error=1");
        }
    }

    if ($action === 'edit') {
        // check_token(); // (disabled in dev)
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));

        if ($id <= 0 || $name === '') {
            tk_redirect($modulelink . "&error=empty");
        }

        try {
            Capsule::table('mod_timekeeper_departments')
                ->where('id', $id)
                ->update(['name' => $name]);
            tk_redirect($modulelink . "&updated=1");
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'uq_department_name') !== false) {
                tk_redirect($modulelink . "&error=duplicate");
            }
            tk_redirect($modulelink . "&error=1");
        }
    }
}

/* ==============================
   GET: Delete (soft delete)
   - Only if no linked task categories exist
============================== */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    // Prevent removing a department that still has task categories
    $hasLinked = Capsule::table('mod_timekeeper_task_categories')
        ->where('department_id', $id)
        ->exists();

    if ($hasLinked) {
        tk_redirect($modulelink . "&error=has_subtasks");
    }

    Capsule::table('mod_timekeeper_departments')
        ->where('id', $id)
        ->update(['status' => 'inactive']);

    tk_redirect($modulelink . "&deleted=1");
}

/* ==============================
   Load departments (active only)
============================== */
$departments = Capsule::table('mod_timekeeper_departments')
    ->where('status', 'active')
    ->orderBy('name')
    ->get();

/* ==============================
   Flash messages (via query flags)
============================== */
if (isset($_GET['success'])) {
    $message = tk_flash('success', 'Department added successfully.');
} elseif (isset($_GET['updated'])) {
    $message = tk_flash('success', 'Department updated successfully.');
} elseif (isset($_GET['deleted'])) {
    $message = tk_flash('success', 'Department deleted successfully.');
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'has_subtasks':
            $message = tk_flash('error', 'Cannot delete department: Task categories are still linked.');
            break;
        case 'duplicate':
            $message = tk_flash('error', 'A department with that name already exists.');
            break;
        case 'empty':
            $message = tk_flash('error', 'Please provide a department name.');
            break;
        default:
            $message = tk_flash('error', 'An error occurred. Please try again.');
    }
}

/* ==============================
   Render template
   - Pass variables directly; no string replacement
============================== */
$modulelink = $modulelink; // keep for template clarity
// The template will use: $message, $modulelink, $departments
include __DIR__ . '/../templates/admin/departments.tpl';
