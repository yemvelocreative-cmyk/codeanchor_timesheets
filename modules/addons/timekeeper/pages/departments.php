
<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Access denied');
}

session_start();

$modulelink = "addonmodules.php?module=timekeeper&timekeeperpage=departments";

// --- Handle Add Department ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    // check_token(); // CSRF token disabled

    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        Capsule::table('mod_timekeeper_departments')->insert([
            'name' => $name,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        header("Location: {$modulelink}&success=1");
        exit;
    }
}

// --- Handle Edit Department ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    // check_token(); // CSRF token disabled

    $id = (int) $_POST['id'];
    $name = trim($_POST['name'] ?? '');
    if ($id && $name !== '') {
        Capsule::table('mod_timekeeper_departments')
            ->where('id', $id)
            ->update(['name' => $name]);
        header("Location: {$modulelink}&updated=1");
        exit;
    }
}


if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $hasLinked = Capsule::table('mod_timekeeper_task_categories')
        ->where('department_id', $id)
        ->exists();

    if ($hasLinked) {
        header("Location: $modulelink&error=has_subtasks");
    } else {
        Capsule::table('mod_timekeeper_departments')
            ->where('id', $id)
            ->update(['status' => 'inactive']);
        header("Location: $modulelink&deleted=1");
    }
    exit;
}
// --- Handle Delete Department ---
if (isset($_GET['delete']) && isset($_GET['token'])) {
    // check_token(); // CSRF token disabled

    $id = (int) $_GET['delete'];
    $hasSubtasks = Capsule::table('mod_timekeeper_subtasks')->where('department_id', $id)->exists();
    if (!$hasSubtasks) {
        Capsule::table('mod_timekeeper_departments')->where('id', $id)->delete();
        header("Location: {$modulelink}&deleted=1");
        exit;
    } else {
        header("Location: {$modulelink}&error=has_subtasks");
        exit;
    }
}

// --- Load Departments ---
$departments = Capsule::table('mod_timekeeper_departments')
    ->where('status', 'active')
    ->orderBy('name')
    ->get();

include __DIR__ . '/../templates/includes/timesheet_menu.tpl';

ob_start();
include __DIR__ . '/../templates/admin/departments.tpl';
$content = ob_get_clean();

// Build the DEPARTMENT_ROWS rows from departments.tpl
$rows = '';
$confirmJS = 'return confirm(\'Are you sure you want to delete this department?\');';
foreach ($departments as $dept) {
    $rows .= '<form method="post" class="border p-3 mb-3 rounded bg-light" style="padding-bottom: 5px;">';
    $rows .= '<div class="row align-items-center" style="width: 50%;">';
    $rows .= '<div class="col-md-6 mb-2">';
    $rows .= '<input type="text" name="name" value="' . htmlspecialchars($dept->name) . '" class="form-control" required>';
    $rows .= '</div>';
    $rows .= '<div class="col-md-6 d-flex gap-2 flex-wrap">';
    $rows .= '<input type="hidden" name="id" value="' . $dept->id . '">';
    $rows .= '<input type="hidden" name="action" value="edit">';
    $rows .= '<button type="submit" class="btn btn-success">Save</button>';
    $rows .= '<a href="' . $modulelink . '&delete=' . $dept->id . '" class="btn btn-danger" style="margin-left:5px;" onclick="' . $confirmJS . '">Delete</a>';
    $rows .= '</div>';
    $rows .= '</div>';
    $rows .= '</form>';
}

$message = '';
if (isset($_GET['success'])) {
    $message = '<div class="alert alert-success">Department added successfully.</div>';
} elseif (isset($_GET['updated'])) {
    $message = '<div class="alert alert-success">Department updated successfully.</div>';
} elseif (isset($_GET['deleted'])) {
    $message = '<div class="alert alert-success">Department deleted successfully.</div>';
} elseif (isset($_GET['error']) && $_GET['error'] === 'has_subtasks') {
    $message = '<div class="alert alert-danger">Cannot delete department: Subtasks are still linked.</div>';
}

$content = str_replace('<!--MESSAGE-->', $message, $content);
$content = str_replace('<!--DEPARTMENT_ROWS-->', $rows, $content);

echo $content;
