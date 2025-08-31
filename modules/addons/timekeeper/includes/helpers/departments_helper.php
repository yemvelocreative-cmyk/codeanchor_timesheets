<?php
// modules/addons/timekeeper/includes/helpers/departments_helper.php
declare(strict_types=1);

namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

final class DepartmentsHelper
{
    /** Flash message HTML */
    public static function flash(string $type, string $text): string
    {
        $cls = ($type === 'error') ? 'alert-danger' : 'alert-success';
        return '<div class="alert ' . $cls . '">' . \Timekeeper\Helpers\CoreHelper::e($text) . '</div>';
    }

    /** Safe redirect (header or JS fallback) */
    public static function redirect(string $url): void
    {
        if (!headers_sent()) {
            header("Location: {$url}");
            exit;
        }
        echo '<script>location.href=' . json_encode($url) . ';</script>';
        exit;
    }

    /** Add a department (throws on DB errors) */
    public static function add(string $name): void
    {
        Capsule::table('mod_timekeeper_departments')->insert([
            'name'       => $name,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Edit department name by id (throws on DB errors) */
    public static function edit(int $id, string $name): void
    {
        Capsule::table('mod_timekeeper_departments')
            ->where('id', $id)
            ->update(['name' => $name]);
    }

    /** Soft-delete department (set inactive) */
    public static function softDelete(int $id): void
    {
        Capsule::table('mod_timekeeper_departments')
            ->where('id', $id)
            ->update(['status' => 'inactive']);
    }

    /** Check for linked task categories that block delete */
    public static function hasLinkedTaskCategories(int $deptId): bool
    {
        return Capsule::table('mod_timekeeper_task_categories')
            ->where('department_id', $deptId)
            ->exists();
    }

    /** Load both active and inactive lists */
    public static function fetchLists(): array
    {
        $active = Capsule::table('mod_timekeeper_departments')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $inactive = Capsule::table('mod_timekeeper_departments')
            ->where('status', 'inactive')
            ->orderBy('name')
            ->get();

        return [$active, $inactive];
    }

    /** Build one editable row (keeps existing markup & classes) */
    public static function buildRow(object $dept, string $modulelink): string
    {
        $id   = (int)$dept->id;
        $name = \Timekeeper\Helpers\CoreHelper::e((string)$dept->name);

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
    }

    /** Map GET flags to a flash message */
    public static function buildMessageFromQuery(array $get): string
    {
        if (isset($get['success']))  return self::flash('success', 'Department added successfully.');
        if (isset($get['updated']))  return self::flash('success', 'Department updated successfully.');
        if (isset($get['deleted']))  return self::flash('success', 'Department deleted successfully.');
        if (isset($get['error'])) {
            switch ($get['error']) {
                case 'has_subtasks': return self::flash('error', 'Cannot delete department: Task categories are still linked.');
                case 'duplicate':    return self::flash('error', 'A department with that name already exists.');
                case 'empty':        return self::flash('error', 'Please provide a department name.');
                default:             return self::flash('error', 'An error occurred. Please try again.');
            }
        }
        return '';
    }
}
