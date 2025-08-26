<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Access denied');
}

$modulelink = "addonmodules.php?module=timekeeper&timekeeperpage=task_categories";

/** Helpers **/
function tk_redirect(string $url, array $params = []): void {
    if ($params) {
        $glue = (strpos($url, '?') === false) ? '?' : '&';
        $url .= $glue . http_build_query($params);
    }
    header("Location: {$url}");
    exit;
}
function tk_str(string $v): string { return trim((string)$v); }
function tk_int($v): int { return (int)$v; }

/** POST: Add **/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name         = tk_str($_POST['name'] ?? '');
    $departmentId = tk_int($_POST['department_id'] ?? 0);

    if ($name === '' || $departmentId <= 0) {
        tk_redirect($modulelink, ['error' => 'missing']);
    }

    // Ensure department exists & active
    $deptExists = Capsule::table('mod_timekeeper_departments')
        ->where('id', $departmentId)
        ->where('status', 'active')
        ->exists();
    if (!$deptExists) {
        tk_redirect($modulelink, ['error' => 'invalid_department']);
    }

    // Duplicate guard
    $dup = Capsule::table('mod_timekeeper_task_categories')
        ->where('department_id', $departmentId)
        ->where('name', $name)
        ->where('status', 'active')
        ->exists();
    if ($dup) {
        tk_redirect($modulelink, ['error' => 'duplicate']);
    }

    Capsule::table('mod_timekeeper_task_categories')->insert([
        'name'          => $name,
        'department_id' => $departmentId,
        'status'        => 'active',
        'created_at'    => date('Y-m-d H:i:s'),
    ]);

    tk_redirect($modulelink, ['success' => 1]);
}

/** POST: Edit **/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id           = tk_int($_POST['id'] ?? 0);
    $name         = tk_str($_POST['name'] ?? '');
    $departmentId = tk_int($_POST['department_id'] ?? 0);

    if ($id <= 0 || $name === '' || $departmentId <= 0) {
        tk_redirect($modulelink, ['error' => 'missing']);
    }

    // Ensure department exists & active
    $deptExists = Capsule::table('mod_timekeeper_departments')
        ->where('id', $departmentId)
        ->where('status', 'active')
        ->exists();
    if (!$deptExists) {
        tk_redirect($modulelink, ['error' => 'invalid_department']);
    }

    // Duplicate guard (exclude self)
    $dup = Capsule::table('mod_timekeeper_task_categories')
        ->where('department_id', $departmentId)
        ->where('name', $name)
        ->where('status', 'active')
        ->where('id', '!=', $id)
        ->exists();
    if ($dup) {
        tk_redirect($modulelink, ['error' => 'duplicate']);
    }

    Capsule::table('mod_timekeeper_task_categories')
        ->where('id', $id)
        ->update([
            'name'          => $name,
            'department_id' => $departmentId,
        ]);

    tk_redirect($modulelink, ['updated' => 1]);
}

/** GET: Delete (soft—set inactive). Block if used by timesheet entries. **/
if (isset($_GET['delete'])) {
    $id = tk_int($_GET['delete']);

    // If linked in any timesheet entries, don’t allow delete
    $inUse = Capsule::table('mod_timekeeper_timesheet_entries')
        ->where('task_category_id', $id)
        ->exists();
    if ($inUse) {
        tk_redirect($modulelink, ['error' => 'has_entries']);
    }

    Capsule::table('mod_timekeeper_task_categories')
        ->where('id', $id)
        ->update(['status' => 'inactive']);

    tk_redirect($modulelink, ['deleted' => 1]);
}

/** Load data **/
$departments = Capsule::table('mod_timekeeper_departments')
    ->where('status', 'active')
    ->orderBy('name')
    ->get()
    ->keyBy('id');

$taskCategories = Capsule::table('mod_timekeeper_task_categories')
    ->where('status', 'active')
    ->orderBy('name')
    ->get();

/** Render TPL (plain PHP include) **/
ob_start();
include __DIR__ . '/../templates/admin/task_categories.tpl';
$content = ob_get_clean();

/** Inject department <option>s for the add form **/
$deptOptions = '<option value="" disabled selected>Please select a department</option>';
foreach ($departments as $dept) {
    $deptOptions .= '<option value="' . (int)$dept->id . '">'
                  . htmlspecialchars($dept->name, ENT_QUOTES, 'UTF-8')
                  . '</option>';
}
$content = str_replace('<!--DEPARTMENT_OPTIONS-->', $deptOptions, $content);

/** Group categories by department (sectioned cards) **/
$grouped = [];
foreach ($taskCategories as $cat) {
    $deptId = (int)$cat->department_id;
    if (!isset($departments[$deptId])) continue; // skip orphaned
    $grouped[$deptId][] = $cat;
}

/** Build grouped sections **/
$rows = '';
foreach ($departments as $deptId => $dept) {
    if (empty($grouped[$deptId])) continue;

    $rows .= '<div class="tk-card">';
    $rows .= '  <div class="tk-card-header">';
    $rows .= '    <h4 class="tk-card-title">'
           . htmlspecialchars($dept->name, ENT_QUOTES, 'UTF-8')
           . ' — <span style="font-weight:400;color:#6b7280;">'
           . count($grouped[$deptId]) . ' categor' . (count($grouped[$deptId]) === 1 ? 'y' : 'ies')
           . '</span></h4>';
    $rows .= '  </div>';

    foreach ($grouped[$deptId] as $cat) {
        $rows .= '<form method="post" class="tc-row">';
        $rows .= '  <div class="row align-items-center">';
        $rows .= '    <div class="col-md-5 mb-2">';
        $rows .= '      <input type="text" name="name" value="' . htmlspecialchars($cat->name, ENT_QUOTES, 'UTF-8') . '" class="form-control" required>';
        $rows .= '    </div>';
        $rows .= '    <div class="col-md-4 mb-2">';
        $rows .= '      <select name="department_id" class="form-control" required>';
        foreach ($departments as $d2) {
            $selected = ((int)$d2->id === (int)$cat->department_id) ? ' selected' : '';
            $rows .= '        <option value="' . (int)$d2->id . '"' . $selected . '>'
                  . htmlspecialchars($d2->name, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $rows .= '      </select>';
        $rows .= '    </div>';
        $rows .= '    <div class="col-md-3 d-flex gap-2 tc-actions">';
        $rows .= '      <input type="hidden" name="id" value="' . (int)$cat->id . '">';
        $rows .= '      <input type="hidden" name="action" value="edit">';
        $rows .= '      <button type="submit" class="btn btn-success">Save</button>';
        $rows .= '      <a href="' . $modulelink . '&delete=' . (int)$cat->id . '" class="btn btn-danger">Delete</a>';
        $rows .= '    </div>';
        $rows .= '  </div>';
        $rows .= '</form>';
    }

    $rows .= '</div>'; // .tk-card
}

/** Messages **/
$msg = '';
if (isset($_GET['success']))           $msg = '<div class="alert alert-success">Task category added successfully.</div>';
elseif (isset($_GET['updated']))       $msg = '<div class="alert alert-success">Task category updated successfully.</div>';
elseif (isset($_GET['deleted']))       $msg = '<div class="alert alert-success">Task category deleted successfully.</div>';
elseif (($_GET['error'] ?? '') === 'duplicate')          $msg = '<div class="alert alert-danger">A task category with that name already exists in the selected department.</div>';
elseif (($_GET['error'] ?? '') === 'missing')            $msg = '<div class="alert alert-danger">Please fill in all required fields.</div>';
elseif (($_GET['error'] ?? '') === 'invalid_department') $msg = '<div class="alert alert-danger">Please select a valid, active department.</div>';
elseif (($_GET['error'] ?? '') === 'has_entries')        $msg = '<div class="alert alert-danger">This task category is used by timesheet entries and cannot be deleted.</div>';

$content = str_replace('<!--MESSAGE-->', $msg, $content);
$content = str_replace('<!--TASK_CATEGORY_ROWS-->', '<div class="tc-rows">'.$rows.'</div>', $content);

echo $content;
