<?php
use WHMCS\Database\Capsule;
if (!defined('WHMCS')) {
    die('Access Denied');
}

/**
 * Summary Report (Pivot-style)
 * Columns:
 *  - Col 1: Department (or Task if group_by=task)
 *  - Col 2: Task (or Department if group_by=task)
 *  - Col 3+: One column per Admin => "Total / Billable / SLA"
 *
 * Filters:
 *  - from (date)
 *  - to (date)
 *  - group_by = department|task
 *
 * CSV export:
 *  - Mirrors the on-screen table exactly
 */
// ----------------------------
// Filters (defaults to current month)
// ----------------------------
$from = isset($_GET['from']) ? preg_replace('/[^0-9\-]/', '', $_GET['from']) : date('Y-m-01');
$to = isset($_GET['to']) ? preg_replace('/[^0-9\-]/', '', $_GET['to']) : date('Y-m-d');
$groupBy = (isset($_GET['group_by']) && $_GET['group_by'] === 'task') ? 'task' : 'department';
// Only include admins who have a "completed" timesheet in range

// Adjust as needed (e.g., ['approved'] only)
$completedStatuses = ['pending', 'approved'];
if (!is_array($completedStatuses) || empty($completedStatuses)) {
    $completedStatuses = ['approved'];
}

// ----------------------------
// Helper: check if a column exists
// ----------------------------

$columnExists = function (string $table, string $column): bool {
    try {
        $db = Capsule::connection()->getDatabaseName();
        $res = Capsule::select(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$db, $table, $column]
        );
        return !empty($res);
    } catch (\Throwable $e) {
        return false;
    }
};

// Determine the task link column in entries table: task_category_id OR task_category_id
$entriesTable = 'mod_timekeeper_timesheet_entries';
$taskLinkCol = null;
if ($columnExists($entriesTable, 'task_category_id')) {
    $taskLinkCol = 'task_category_id';
} elseif ($columnExists($entriesTable, 'task_category_id')) {
    $taskLinkCol = 'task_category_id';
} else {
    echo '<div style="background:#ffecec;border:1px solid #f5c2c2;padding:10px;">
            Summary Report error: Neither <code>task_category_id</code> nor <code>task_category_id</code> exists on <code>' . htmlspecialchars($entriesTable, ENT_QUOTES, 'UTF-8') . '</code>.
          </div>';
    return;
}

// ----------------------------
// Build and run the query (get rows first)
// ----------------------------
$query = Capsule::table('mod_timekeeper_timesheet_entries AS e')
    ->join('mod_timekeeper_timesheets AS t', 'e.timesheet_id', '=', 't.id')
    ->join('mod_timekeeper_departments AS d', 'e.department_id', '=', 'd.id')
    ->join('mod_timekeeper_task_categories AS tc', 'e.' . $taskLinkCol, '=', 'tc.id')
    ->join('tbladmins AS a', 't.admin_id', '=', 'a.id')
    ->select(
        'd.id AS dept_id',
        'd.name AS department',
        'tc.id AS task_id',
        'tc.name AS task_category',
        'a.id AS admin_id',
        Capsule::raw("CONCAT(a.firstname, ' ', a.lastname) AS admin_name"),
        Capsule::raw('ROUND(SUM(e.time_spent), 2) AS total_time'),
        Capsule::raw('ROUND(SUM(e.billable_time), 2) AS billable_time'),
        Capsule::raw('ROUND(SUM(e.sla_time), 2) AS sla_time')
    )
    ->whereBetween('t.timesheet_date', [$from, $to]);

// Only add whereIn if we truly have values
if (is_array($completedStatuses) && count($completedStatuses) > 0) {
    $query->whereIn('t.status', $completedStatuses);
}
$query->groupBy('d.id', 'tc.id', 'a.id');
if ($groupBy === 'task') {
    $query->orderBy('tc.name')->orderBy('d.name');
} else {
    $query->orderBy('d.name')->orderBy('tc.name');
}
$rows = $query->get();

// ----------------------------
// Derive admin list from the result set (only admins with completed timesheets in range)
// ----------------------------
$adminIds = [];
$adminNameMap = [];
foreach ($rows as $r) {
    $adminIds[$r->admin_id] = true;
    $adminNameMap[$r->admin_id] = $r->admin_name;
}
$admins = [];
foreach (array_keys($adminIds) as $aid) {
    $admins[] = (object)[
        'id' => $aid,
        'name' => $adminNameMap[$aid] ?? ('Admin #' . $aid),
    ];
}
usort($admins, function ($a, $b) {
    return strcasecmp($a->name, $b->name);
});

// ----------------------------
// Pivot results into 3D array: [$department][$task][$adminId] = [totals...]
// ----------------------------
$data = [];
foreach ($rows as $row) {
    $deptKey = $row->department;
    $taskKey = $row->task_category;
    if (!isset($data[$deptKey])) {
        $data[$deptKey] = [];
    }
    if (!isset($data[$deptKey][$taskKey])) {
        $data[$deptKey][$taskKey] = [];
    }
    $data[$deptKey][$taskKey][$row->admin_id] = [
        'total'    => (float) $row->total_time,
        'billable' => (float) $row->billable_time,
        'sla'      => (float) $row->sla_time,
    ];
}

// ----------------------------
// Column totals per admin + overall grand totals
// ----------------------------

$adminIdsList = array_map(function($a){ return $a->id; }, $admins);
$adminColTotals = [];
foreach ($adminIdsList as $aid) {
    $adminColTotals[$aid] = ['total' => 0.0, 'billable' => 0.0, 'sla' => 0.0];
}
$grandTotals = ['total' => 0.0, 'billable' => 0.0, 'sla' => 0.0];
foreach ($data as $dept => $tasks) {
    foreach ($tasks as $task => $adminData) {
        foreach ($adminIdsList as $aid) {
            $t = isset($adminData[$aid]) ? (float)$adminData[$aid]['total']    : 0.0;
            $b = isset($adminData[$aid]) ? (float)$adminData[$aid]['billable'] : 0.0;
            $s = isset($adminData[$aid]) ? (float)$adminData[$aid]['sla']      : 0.0;
            $adminColTotals[$aid]['total']    += $t;
            $adminColTotals[$aid]['billable'] += $b;
            $adminColTotals[$aid]['sla']      += $s;
            $grandTotals['total']    += $t;
            $grandTotals['billable'] += $b;
            $grandTotals['sla']      += $s;
        }
    }
}

// ----------------------------
// CSV Export
// ----------------------------
$baseUrl = 'addonmodules.php?module=timekeeper&timekeeperpage=reports';

if (isset($_GET['export']) && $_GET['export'] === '1') {
    // Make sure nothing (even buffered) leaks into the CSV
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { ob_end_clean(); }
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=summary_report.csv');
    $out = fopen('php://output', 'w');
    $firstCol = ($groupBy === 'task') ? 'Task Category' : 'Department';
    $secondCol = ($groupBy === 'task') ? 'Department' : 'Task Category';

    // Row 1: Admin name spanning 3 columns (name + 2 blanks) + Row Totals group
    $header1 = [$firstCol, $secondCol];
    foreach ($admins as $admin) {
        $header1[] = $admin->name; // "merged" over next 2 blank cells
        $header1[] = '';
        $header1[] = '';
    }
    $header1[] = 'Row Totals';
    $header1[] = '';
    $header1[] = '';

    // Row 2: Metric labels under each admin (and under Row Totals)
    $header2 = ['', '']; // keep first two columns empty on line 2
    foreach ($admins as $admin) {
        $header2[] = 'Total';
        $header2[] = 'Billable';
        $header2[] = 'SLA';
    }
    $header2[] = 'Total';
    $header2[] = 'Billable';
    $header2[] = 'SLA';

    // Write both header rows
    fputcsv($out, $header1);
    fputcsv($out, $header2);
    foreach ($data as $dept => $tasks) {
    foreach ($tasks as $task => $adminData) {
            $rowArr = ($groupBy === 'task') ? [$task, $dept] : [$dept, $task];
            $rowTotalSum = 0.0;
            $rowBillableSum = 0.0;
            $rowSlaSum = 0.0;
            foreach ($admins as $admin) {
                if (isset($adminData[$admin->id])) {
                    $td = $adminData[$admin->id];
                    $t  = (float) $td['total'];
                    $b  = (float) $td['billable'];
                    $s  = (float) $td['sla'];
                    $rowTotalSum    += $t;
                    $rowBillableSum += $b;
                    $rowSlaSum      += $s;
                    $rowArr[] = number_format($t, 2);
                    $rowArr[] = number_format($b, 2);
                    $rowArr[] = number_format($s, 2);
                } else {
                    // keep column count consistent
                    $rowArr[] = '0.00';
                    $rowArr[] = '0.00';
                    $rowArr[] = '0.00';
                }
            }

            // Append row totals
            $rowArr[] = number_format($rowTotalSum, 2);
            $rowArr[] = number_format($rowBillableSum, 2);
            $rowArr[] = number_format($rowSlaSum, 2);
            fputcsv($out, $rowArr);
        }
    }

    // Append a final "Column Totals" row
    $footer = ['Column Totals', '']; // CSV has no colspan; leave 2nd cell blank
    foreach ($admins as $admin) {
        $footer[] = number_format($adminColTotals[$admin->id]['total'], 2);
        $footer[] = number_format($adminColTotals[$admin->id]['billable'], 2);
        $footer[] = number_format($adminColTotals[$admin->id]['sla'], 2);
    }
    $footer[] = number_format($grandTotals['total'], 2);
    $footer[] = number_format($grandTotals['billable'], 2);
    $footer[] = number_format($grandTotals['sla'], 2);
    fputcsv($out, $footer);
    fclose($out);
    exit;
}
?>
<div class="timekeeper-report-header">
    <h3>Summary Report</h3>
</div>
<form method="get" action="<?= $baseUrl ?>" class="timekeeper-report-filters">
    <input type="hidden" name="module" value="timekeeper">
    <input type="hidden" name="timekeeperpage" value="reports">
    <input type="hidden" name="r" value="summary">
    <div class="filter-row" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px 18px;">
        <div class="filter-item" style="display:flex;flex-direction:column;min-width:180px;">
            <label>From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filter-item" style="display:flex;flex-direction:column;min-width:180px;">
            <label>To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filter-item" style="display:flex;flex-direction:column;min-width:180px;">
            <label>Group By</label>
            <select name="group_by">
                <option value="department" <?= $groupBy === 'department' ? 'selected' : '' ?>>Department</option>
                <option value="task" <?= $groupBy === 'task' ? 'selected' : '' ?>>Task</option>
            </select>
        </div>
        <div class="filter-actions" style="display:flex;gap:10px;margin-left:auto;padding-bottom:2px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a class="btn btn-success"
               href="<?= $baseUrl .
                    '&r=summary' .
                    '&from=' . urlencode($from) .
                    '&to=' . urlencode($to) .
                    '&group_by=' . urlencode($groupBy) .
                    '&export=1' ?>">
                Export CSV
            </a>
        </div>
    </div>
</form>
<table class="table table-bordered timekeeper-report-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th rowspan="2"><?= ($groupBy === 'task') ? 'Task Category' : 'Department' ?></th>
            <th rowspan="2"><?= ($groupBy === 'task') ? 'Department' : 'Task Category' ?></th>
            <?php foreach ($admins as $admin): ?>
                <th colspan="3" class="admin-group">
                    <?= htmlspecialchars($admin->name, ENT_QUOTES, 'UTF-8') ?>
                </th>
                <?php endforeach; ?>
                <th colspan="3" class="admin-group">Row Totals</th>  <!-- new -->
        </tr>
        <?php if (!empty($admins)): ?>
        <tr>
            <?php foreach ($admins as $admin): ?>
                <th>Total</th>
                <th>Billable</th>
                <th>SLA</th>
            <?php endforeach; ?>
            <th>Total</th>    <!-- new -->
            <th>Billable</th> <!-- new -->
            <th>SLA</th>      <!-- new -->
        </tr>
        <?php endif; ?>
    </thead>
    <tbody>
        <?php if (empty($data)): ?>
            <tr>
                <td colspan="<?= 2 + (count($admins) * 3) + 3 ?>">
                    <div style="background:#e2f0d9;border:1px solid #a2d28f;padding:8px;">
                        No data for the selected filters.
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($data as $dept => $tasks): ?>
                <?php foreach ($tasks as $task => $adminData): ?>
                    <tr>
                        <td><?= ($groupBy === 'task')
                                ? htmlspecialchars($task, ENT_QUOTES, 'UTF-8')
                                : htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= ($groupBy === 'task')
                                ? htmlspecialchars($dept, ENT_QUOTES, 'UTF-8')
                                : htmlspecialchars($task, ENT_QUOTES, 'UTF-8') ?></td>
                        <?php 
                            $rowTotalSum = 0;
                            $rowBillableSum = 0;
                            $rowSlaSum = 0;
                            foreach ($admins as $admin):
                                if (isset($adminData[$admin->id])) {
                                    $td = $adminData[$admin->id];
                                    $totalVal    = (float) $td['total'];
                                    $billableVal = (float) $td['billable'];
                                    $slaVal      = (float) $td['sla'];
                                    $rowTotalSum    += $totalVal;
                                    $rowBillableSum += $billableVal;
                                    $rowSlaSum      += $slaVal;
                                    echo '<td>' . number_format($totalVal, 2) . '</td>';
                                    echo '<td>' . number_format($billableVal, 2) . '</td>';
                                    echo '<td>' . number_format($slaVal, 2) . '</td>';
                                } else {
                                    echo '<td>0.00</td><td>0.00</td><td>0.00</td>';
                                }
                            endforeach;

                            // Now print the row totals at the end
                            echo '<td>' . number_format($rowTotalSum, 2) . '</td>';
                            echo '<td>' . number_format($rowBillableSum, 2) . '</td>';
                            echo '<td>' . number_format($rowSlaSum, 2) . '</td>';
                        ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <tfoot>
    <tr>
        <th colspan="2" style="text-align:right;">Column Totals</th>
        <?php foreach ($admins as $admin): ?>
            <th><?= number_format($adminColTotals[$admin->id]['total'], 2) ?></th>
            <th><?= number_format($adminColTotals[$admin->id]['billable'], 2) ?></th>
            <th><?= number_format($adminColTotals[$admin->id]['sla'], 2) ?></th>
        <?php endforeach; ?>
        <th><?= number_format($grandTotals['total'], 2) ?></th>
        <th><?= number_format($grandTotals['billable'], 2) ?></th>
        <th><?= number_format($grandTotals['sla'], 2) ?></th>
    </tr>
    </tfoot>

</table>
