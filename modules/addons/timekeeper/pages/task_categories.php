<?php
use WHMCS\Database\Capsule;
if (!defined('WHMCS')) {
    die('Access denied');
}
session_start();
$modulelink = "addonmodules.php?module=timekeeper&timekeeperpage=task_categories";
// --- Handle Add Task Category ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $departmentId = (int) $_POST['department_id'];
    if ($name !== '' && $departmentId) {
        Capsule::table('mod_timekeeper_task_categories')->insert([
            'name' => $name,
            'department_id' => $departmentId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        header("Location: {$modulelink}&success=1");
        exit;
    }
}

// --- Handle Edit Task Category ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    $id = (int) $_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $departmentId = (int) $_POST['department_id'];

    if ($id && $name !== '' && $departmentId) {
        Capsule::table('mod_timekeeper_task_categories')
            ->where('id', $id)
            ->update([
                'name' => $name,
                'department_id' => $departmentId
            ]);
        header("Location: {$modulelink}&updated=1");
        exit;
    }
}

// --- Handle Delete Task Category ---
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    // Optional: Add check if task category is linked elsewhere before deleting

    Capsule::table('mod_timekeeper_task_categories')
        ->where('id', $id)
        ->update(['status' => 'inactive']);
    header("Location: {$modulelink}&deleted=1");
    exit;
}

// --- Load Departments and Task Categories ---
$departments = Capsule::table('mod_timekeeper_departments')
    ->where('status', 'active')
    ->orderBy('name')
    ->get()
    ->keyBy('id');
$taskCategories = Capsule::table('mod_timekeeper_task_categories')
    ->where('status', 'active')
    ->orderBy('name')
    ->get();

include __DIR__ . '/../templates/includes/timesheet_menu.tpl';


ob_start();
include __DIR__ . '/../templates/admin/task_categories.tpl';
$content = ob_get_clean();

$deptOptions = '<option value="" disabled selected>Please select a department</option>';
foreach ($departments as $dept) {
    $deptOptions .= '<option value="' . $dept->id . '">' . htmlspecialchars($dept->name) . '</option>';
}
$content = str_replace('<!--DEPARTMENT_OPTIONS-->', $deptOptions, $content);


// Build rows
$rows = '';
foreach ($taskCategories as $cat) {
    $rows .= '<form method="post" class="border p-3 mb-3 rounded bg-light" style="padding-bottom: 5px;">';
    $rows .= '<div class="row align-items-center" style="width: 50%;">';
    $rows .= '<div class="col-md-5 mb-2">';
    $rows .= '<input type="text" name="name" value="' . htmlspecialchars($cat->name) . '" class="form-control" required>';
    $rows .= '</div>';
    $rows .= '<div class="col-md-4 mb-2">';
    $rows .= '<select name="department_id" class="form-control" required>';
    foreach ($departments as $dept) {
        $selected = $dept->id == $cat->department_id ? 'selected' : '';
        $rows .= '<option value="' . $dept->id . '" ' . $selected . '>' . htmlspecialchars($dept->name) . '</option>';
    }
    $rows .= '</select>';
    $rows .= '</div>';
    $rows .= '<div class="col-md-3 d-flex gap-2 flex-wrap">';
    $rows .= '<input type="hidden" name="id" value="' . $cat->id . '">';
    $rows .= '<input type="hidden" name="action" value="edit">';
    $rows .= '<button type="submit" class="btn btn-success">Save</button>';
    $rows .= "<a href='" . $modulelink . "&delete=" . $cat->id . "' class='btn btn-danger' style='margin-left: 5px;' onclick=\"return confirm('Are you sure you want to delete this task category?');\">Delete</a>";
    $rows .= '</div>';
    $rows .= '</div>';
    $rows .= '</form>';
}

$message = '';
if (isset($_GET['success'])) {
    $message = '<div class="alert alert-success">Task category added successfully.</div>';
} elseif (isset($_GET['updated'])) {
    $message = '<div class="alert alert-success">Task category updated successfully.</div>';
} elseif (isset($_GET['deleted'])) {
    $message = '<div class="alert alert-success">Task category deleted successfully.</div>';
}

$content = str_replace('<!--MESSAGE-->', $message, $content);
$content = str_replace('<!--TASK_CATEGORY_ROWS-->', $rows, $content);

echo $content;