<?php
// modules/addons/timekeeper/includes/helpers/task_categories_helper.php
declare(strict_types=1);

namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

final class TaskCategoriesHelper
{
    /** Safe redirect with optional query params */
    public static function redirect(string $url, array $params = []): void
    {
        if (!empty($params)) {
            $glue = (strpos($url, '?') === false) ? '?' : '&';
            $url .= $glue . http_build_query($params);
        }
        if (!headers_sent()) {
            header("Location: {$url}");
            exit;
        }
        echo '<script>location.href=' . json_encode($url) . ';</script>';
        exit;
    }

    /** Trimmed string + int helpers (explicit) */
    public static function s($v): string { return trim((string)$v); }
    public static function i($v): int    { return (int)$v; }

    /** Validate active department exists */
    public static function departmentIsActive(int $departmentId): bool
    {
        return Capsule::table('mod_timekeeper_departments')
            ->where('id', $departmentId)
            ->where('status', 'active')
            ->exists();
    }

    /** Duplicate guard: name+department active (optionally exclude id) */
    public static function isDuplicate(string $name, int $departmentId, ?int $excludeId = null): bool
    {
        $q = Capsule::table('mod_timekeeper_task_categories')
            ->where('department_id', $departmentId)
            ->where('name', $name)
            ->where('status', 'active');

        if ($excludeId !== null) {
            $q->where('id', '!=', $excludeId);
        }
        return $q->exists();
    }

    /** Create task category */
    public static function create(string $name, int $departmentId): void
    {
        Capsule::table('mod_timekeeper_task_categories')->insert([
            'name'          => $name,
            'department_id' => $departmentId,
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /** Update task category */
    public static function update(int $id, string $name, int $departmentId): void
    {
        Capsule::table('mod_timekeeper_task_categories')
            ->where('id', $id)
            ->update([
                'name'          => $name,
                'department_id' => $departmentId,
            ]);
    }

    /** Is task category referenced by any timesheet entries? */
    public static function isInUse(int $taskCategoryId): bool
    {
        return Capsule::table('mod_timekeeper_timesheet_entries')
            ->where('task_category_id', $taskCategoryId)
            ->exists();
    }

    /** Soft delete (set inactive) */
    public static function softDelete(int $id): void
    {
        Capsule::table('mod_timekeeper_task_categories')
            ->where('id', $id)
            ->update(['status' => 'inactive']);
    }

    /** Fetch active departments keyed by id */
    public static function fetchDepartmentsKeyed()
    {
        return Capsule::table('mod_timekeeper_departments')
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->keyBy('id');
    }

    /** Fetch active task categories ordered by name */
    public static function fetchActiveTaskCategories()
    {
        return Capsule::table('mod_timekeeper_task_categories')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /** Build <option> list for add form */
    public static function buildDepartmentOptions($departments): string
    {
        $out = '<option value="" disabled selected>Please select a department</option>';
        foreach ($departments as $dept) {
            $out .= '<option value="' . (int)$dept->id . '">'
                 . \Timekeeper\Helpers\CoreHelper::e((string)$dept->name)
                 . '</option>';
        }
        return $out;
    }

    /** Group categories per department (skips orphaned) */
    public static function groupByDepartment($departments, $taskCategories): array
    {
        $grouped = [];
        foreach ($taskCategories as $cat) {
            $deptId = (int)$cat->department_id;
            if (!isset($departments[$deptId])) continue;
            $grouped[$deptId][] = $cat;
        }
        return $grouped;
    }

    /** Build grouped HTML rows for template injection */
    public static function buildGroupedRows($departments, array $grouped, string $modulelink): string
    {
        $rows = '';

        foreach ($departments as $deptId => $dept) {
            if (empty($grouped[$deptId])) continue;

            $count = count($grouped[$deptId]);

            $rows .= '<div class="tk-card">';
            $rows .= '  <div class="tk-card-header">';
            $rows .= '    <h4 class="tk-card-title">'
                  . \Timekeeper\Helpers\CoreHelper::e((string)$dept->name)
                  . ' — <span class="tk-muted-count">'
                  . $count . ' categor' . ($count === 1 ? 'y' : 'ies')
                  . '</span></h4>';
            $rows .= '  </div>';

            foreach ($grouped[$deptId] as $cat) {
                $rows .= self::buildEditRow($cat, $departments, $modulelink);
            }

            $rows .= '</div>'; // .tk-card
        }

        return $rows;
    }

    /** Build one editable row form */
    public static function buildEditRow(object $cat, $departments, string $modulelink): string
    {
        $name = \Timekeeper\Helpers\CoreHelper::e((string)$cat->name);
        $id   = (int)$cat->id;

        $row  = '<form method="post" class="tc-row">';
        $row .= '  <div class="row align-items-center">';
        $row .= '    <div class="col-md-5 mb-2">';
        $row .= '      <input type="text" name="name" value="' . $name . '" class="form-control" required>';
        $row .= '    </div>';
        $row .= '    <div class="col-md-4 mb-2">';
        $row .= '      <select name="department_id" class="form-control" required>';
        foreach ($departments as $d2) {
            $selected = ((int)$d2->id === (int)$cat->department_id) ? ' selected' : '';
            $row .= '        <option value="' . (int)$d2->id . '"' . $selected . '>'
                 . \Timekeeper\Helpers\CoreHelper::e((string)$d2->name)
                 . '</option>';
        }
        $row .= '      </select>';
        $row .= '    </div>';
        $row .= '    <div class="col-md-3 d-flex gap-2 tc-actions">';
        $row .= '      <input type="hidden" name="id" value="' . $id . '">';
        $row .= '      <input type="hidden" name="action" value="edit">';
        $row .= '      <button type="submit" class="btn btn-success">Save</button>';
        $row .= '      <a href="' . $modulelink . '&delete=' . $id . '" class="btn btn-danger">Delete</a>';
        $row .= '    </div>';
        $row .= '  </div>';
        $row .= '</form>';

        return $row;
    }

    /** Build flash message HTML from $_GET flags */
    public static function buildMessageFromQuery(array $get): string
    {
        if (isset($get['success'])) return '<div class="alert alert-success">Task category added successfully.</div>';
        if (isset($get['updated'])) return '<div class="alert alert-success">Task category updated successfully.</div>';
        if (isset($get['deleted'])) return '<div class="alert alert-success">Task category deleted successfully.</div>';

        $err = $get['error'] ?? '';
        if ($err === 'duplicate')          return '<div class="alert alert-danger">A task category with that name already exists in the selected department.</div>';
        if ($err === 'missing')            return '<div class="alert alert-danger">Please fill in all required fields.</div>';
        if ($err === 'invalid_department') return '<div class="alert alert-danger">Please select a valid, active department.</div>';
        if ($err === 'has_entries')        return '<div class="alert alert-danger">This task category is used by timesheet entries and cannot be deleted.</div>';

        return '';
    }
}
