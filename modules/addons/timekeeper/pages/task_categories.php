<?php
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('Access denied'); }

// --- Load helpers (supports helpers/ or includes/helpers/) ---
$base = dirname(__DIR__); // -> /modules/addons/timekeeper
$try = function (string $relA, string $relB) use ($base) {
    $a = $base . $relA;
    $b = $base . $relB;
    if (is_file($a)) { require_once $a; return; }
    if (is_file($b)) { require_once $b; return; }
    throw new \RuntimeException("Missing helper: tried {$a} and {$b}");
};
$try('/helpers/core_helper.php', '/includes/helpers/core_helper.php');
$try('/helpers/task_categories_helper.php', '/includes/helpers/task_categories_helper.php');

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\TaskCategoriesHelper as TCH;

$modulelink = "addonmodules.php?module=timekeeper&timekeeperpage=task_categories";

// ------------------------------
// POST: Add
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'add')) {
    $name         = TCH::s($_POST['name'] ?? '');
    $departmentId = TCH::i($_POST['department_id'] ?? 0);

    if ($name === '' || $departmentId <= 0) {
        TCH::redirect($modulelink, ['error' => 'missing']);
    }

    if (!TCH::departmentIsActive($departmentId)) {
        TCH::redirect($modulelink, ['error' => 'invalid_department']);
    }

    if (TCH::isDuplicate($name, $departmentId)) {
        TCH::redirect($modulelink, ['error' => 'duplicate']);
    }

    TCH::create($name, $departmentId);
    TCH::redirect($modulelink, ['success' => 1]);
}

// ------------------------------
// POST: Edit
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'edit')) {
    $id           = TCH::i($_POST['id'] ?? 0);
    $name         = TCH::s($_POST['name'] ?? '');
    $departmentId = TCH::i($_POST['department_id'] ?? 0);

    if ($id <= 0 || $name === '' || $departmentId <= 0) {
        TCH::redirect($modulelink, ['error' => 'missing']);
    }

    if (!TCH::departmentIsActive($departmentId)) {
        TCH::redirect($modulelink, ['error' => 'invalid_department']);
    }

    if (TCH::isDuplicate($name, $departmentId, $id)) {
        TCH::redirect($modulelink, ['error' => 'duplicate']);
    }

    TCH::update($id, $name, $departmentId);
    TCH::redirect($modulelink, ['updated' => 1]);
}

// ------------------------------
// GET: Delete (soft; blocked if in use)
// ------------------------------
if (isset($_GET['delete'])) {
    $id = TCH::i($_GET['delete']);

    if (TCH::isInUse($id)) {
        TCH::redirect($modulelink, ['error' => 'has_entries']);
    }

    TCH::softDelete($id);
    TCH::redirect($modulelink, ['deleted' => 1]);
}

// ------------------------------
// Load data
// ------------------------------
$departments    = TCH::fetchDepartmentsKeyed();
$taskCategories = TCH::fetchActiveTaskCategories();

// ------------------------------
// Render template
// ------------------------------
ob_start();
include __DIR__ . '/../templates/admin/task_categories.tpl';
$content = ob_get_clean();

// Department <option>s for the add form
$deptOptions = TCH::buildDepartmentOptions($departments);
$content = str_replace('<!--DEPARTMENT_OPTIONS-->', $deptOptions, $content);

// Group categories by department and build sections
$grouped = TCH::groupByDepartment($departments, $taskCategories);
$rows    = TCH::buildGroupedRows($departments, $grouped, $modulelink);

// Flash messages
$msg = TCH::buildMessageFromQuery($_GET);

// Inject placeholders
$content = str_replace('<!--MESSAGE-->', $msg, $content);
$content = str_replace('<!--TASK_CATEGORY_ROWS-->', '<div class="tc-rows">'.$rows.'</div>', $content);

echo $content;
