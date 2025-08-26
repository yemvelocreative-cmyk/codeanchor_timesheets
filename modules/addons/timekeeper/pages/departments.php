<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Access denied');
}

$modulelink = "addonmodules.php?module=timekeeper&timekeeperpage=departments";

/** Flash helper **/
function tk_flash(string $type, string $text): string {
    $cls = $type === 'error' ? 'alert-danger' : 'alert-success';
    return '<div class="alert ' . $cls . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>';
}

/** Redirect helper **/
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
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            tk_redirect($modulelink . "&error=empty");
        }

        // Optional explicit duplicate guard (collation usually case-insensitive)
        $exists = Capsule::table('mod_timekeeper_departments')
            ->where('name', $name)
            ->where('status', 'active')
            ->exists();
        if ($exists) {
            tk_redirect($modulelink . "&error=duplicate");
        }

        try {
            Capsule::table('mod_timekeeper_departments')->insert([
                'name'       => $name,
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            tk_redirect($modulelink . "&success=1");
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'uq_department_name') !== false) {
                tk_redirect($modulelink . "&error=duplicate");
            }
            tk_redirect($modulelink . "&error=1");
        }
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));

        if ($id <= 0 || $name === '') {
            tk_redirect($modulelink . "&error=empty");
        }

        // Optional explicit duplicate guard (exclude self)
        $exists = Capsule::table('mod_timekeeper_departments')
            ->where('name', $name)
            ->where('status', 'active')
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            tk_redirect($modulelink . "&error=duplicate");
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
   Load departments (active + inactive)
============================== */
$active = Capsule::table('mod_timekeeper_departments')
    ->where('status', 'active')
    ->orderBy('name')
    ->get();

$inactive = Capsule::table('mod_timekeeper_departments')
    ->where('status', 'inactive')
    ->orderBy('name')
    ->get();

/* ==============================
   Build messages
============================== */
$message = '';
if (isset($_GET['success']))         $message = tk_flash('success', 'Department added successfully.');
elseif (isset($_GET['updated']))     $message = tk_flash('success', 'Department updated successfully.');
elseif (isset($_GET['deleted']))     $message = tk_flash('success', 'Department deleted successfully.');
elseif (isset($_GET['error'])) {
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
   Render template & inject rows
============================== */
ob_start();
include __DIR__ . '/../templates/admin/departments.tpl';
$content = ob_get_clean();

/** Build row HTML helper **/
$buildRow = function ($dept) use ($modulelink) {
    $id   = (int)$dept->id;
    $name = htmlspecialchars($dept->name, ENT_QUOTES, 'UTF-8');

    $row  = '<form method="post" class="dept-row">';
    $row .= '  <div class="row align-items-center">';
    $row .= '    <div class="col-md-8 mb-2">';
    $row .= '      <input type="text" name="name" value="' . $name . '" class="form-control" required>';
    $row .= '    </div>';
    $row .= '    <div class="col-md-4 d-flex gap-2 dept-actions">';
    $row .= '      <input type="hidden" name="id" value="' . $id . '">';
    $row .= '      <input type="hidden" name="action" value="edit">';
    $row .= '      <button type="submit" class="btn btn-success">Save</button>';
    $row .= '      <a href="' . $modulelink . '&delete=' . $id . '" class="btn btn-danger">Delete</a>';
    $row .= '    </div>';
    $row .= '  </div>';
    $row .= '</form>';

    return $row;
};

/** Active rows **/
$rowsActive = '';
foreach ($active as $dept) {
    $rowsActive .= $buildRow($dept);
}

/** Inactive rows **/
$rowsInactive = '';
foreach ($inactive as $dept) {
    $rowsInactive .= $buildRow($dept);
}

/** Message & rows injection **/
$content = str_replace('<!--MESSAGE-->', $message, $content);
$content = str_replace('<!--DEPT_ROWS_ACTIVE-->', $rowsActive, $content);
$content = str_replace('<!--DEPT_ROWS_INACTIVE-->', $rowsInactive, $content);

echo $content;
