<?php


// ---- Dynamic base URL + asset helper (polyfill if not in core_helper yet) ----
// Preferred helpers (if present in core_helper.php):
//   \Timekeeper\Helpers\timekeeperBaseUrl(): string
//   \Timekeeper\Helpers\timekeeperAsset(string $relPath): string
if (!function_exists('Timekeeper\\Helpers\\timekeeperBaseUrl') || !function_exists('Timekeeper\\Helpers\\timekeeperAsset')) {
    // Local polyfill
    $tkSystemUrl = (function (): string {
        try {
            $ssl = (string) \WHMCS\Config\Setting::getValue('SystemSSLURL');
            $url = $ssl !== '' ? $ssl : (string) \WHMCS\Config\Setting::getValue('SystemURL');
            return rtrim($url, '/');
        } catch (\Throwable $e) {
            return '';
        }
    })();

    $tkBase = ($tkSystemUrl !== '' ? $tkSystemUrl : '') . '/modules/addons/timekeeper';
    $tkBase = rtrim($tkBase, '/');

    // Callable for cache-busted assets, e.g. $tkAsset('css/page.css')
    $tkAsset = function (string $relPath) use ($tkBase, $base): string {
        $rel = ltrim($relPath, '/');
        $url = $tkBase . '/' . $rel;

        $file = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (@is_file($file)) {
            $ver = @filemtime($file);
            if ($ver) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . 'v=' . $ver;
            }
        }
        return $url;
    };
} else {
    // Use canonical helpers if available
    $tkBase  = \Timekeeper\Helpers\timekeeperBaseUrl();
    $tkAsset = '\Timekeeper\Helpers\timekeeperAsset'; // callable
}

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
 * CSV export mirrors the on-screen table.
 */

/* ----------------------------
 * Filters (defaults to current month)
 * ---------------------------- */
$sanitizeDate = function ($s) {
    $s = preg_replace('/[^0-9\-]/', '', (string)$s);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return null;
    }
    return $s;
};
$from = isset($_GET['from']) ? $sanitizeDate($_GET['from']) : date('Y-m-01');
$to   = isset($_GET['to'])   ? $sanitizeDate($_GET['to'])   : date('Y-m-d');
if (!$from) { $from = date('Y-m-01'); }
if (!$to)   { $to   = date('Y-m-d');  }
if (strtotime($from) > strtotime($to)) {
    $tmp = $from; $from = $to; $to = $tmp;
}

$groupBy = (isset($_GET['group_by']) && $_GET['group_by'] === 'task') ? 'task' : 'department';

// Only include admins who have a "completed" timesheet in range
$completedStatuses = ['pending', 'approved'];
if (!is_array($completedStatuses) || empty($completedStatuses)) {
    $completedStatuses = ['approved'];
}

/* ----------------------------
 * Helper: check if a column exists
 * ---------------------------- */
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

/* ----------------------------
 * Determine task linkage + lookup tables
 * ---------------------------- */
$entriesTable = 'mod_timekeeper_timesheet_entries';
$taskLinkCol = null;

if ($columnExists($entriesTable, 'task_category_id')) {
    $taskLinkCol = 'task_category_id';
} elseif ($columnExists($entriesTable, 'task_category')) {
    $taskLinkCol = 'task_category';
} elseif ($columnExists($entriesTable, 'task_id')) {
    $taskLinkCol = 'task_id';
}

if (!$taskLinkCol) {
    echo '<div class="tk-alert tk-alert--error">
            Summary Report error: No suitable task link column found on <code>' . htmlspecialchars($entriesTable, ENT_QUOTES, 'UTF-8') . '</code>.
          </div>';
    return;
}

// Prefer categories table, fall back to tasks/subtasks if needed
$taskTable = null;
$taskNameCol = 'name';
$taskIdCol   = 'id';

$schema = Capsule::schema();
if ($schema->hasTable('mod_timekeeper_task_categories')) {
    $taskTable = 'mod_timekeeper_task_categories';
} elseif ($schema->hasTable('mod_timekeeper_tasks')) {
    $taskTable = 'mod_timekeeper_tasks';
} elseif ($schema->hasTable('mod_timekeeper_subtasks')) {
    $taskTable = 'mod_timekeeper_subtasks';
}

if (!$taskTable) {
    echo '<div class="tk-alert tk-alert--error">
            Summary Report error: No task lookup table found (<code>mod_timekeeper_task_categories</code>, <code>mod_timekeeper_tasks</code> or <code>mod_timekeeper_subtasks</code>).
          </div>';
    return;
}

/* ----------------------------
 * Build and run the query (get rows first)
 * ---------------------------- */
$query = Capsule::table('mod_timekeeper_timesheet_entries AS e')
    ->join('mod_timekeeper_timesheets AS t', 'e.timesheet_id', '=', 't.id')
    ->join('mod_timekeeper_departments AS d', 'e.department_id', '=', 'd.id')
    ->join($taskTable . ' AS tc', 'e.' . $taskLinkCol, '=', 'tc.' . $taskIdCol)
    ->join('tbladmins AS a', 't.admin_id', '=', 'a.id')
    ->select(
        'd.id AS dept_id',
        'd.name AS department',
        Capsule::raw('tc.' . $taskIdCol . ' AS task_id'),
        Capsule::raw('tc.' . $taskNameCol . ' AS task_category'),
        'a.id AS admin_id',
        Capsule::raw("TRIM(CONCAT(COALESCE(a.firstname,''),' ',COALESCE(a.lastname,''))) AS admin_name"),
        Capsule::raw('ROUND(SUM(COALESCE(e.time_spent,0)), 2) AS total_time'),
        Capsule::raw('ROUND(SUM(COALESCE(e.billable_time,0)), 2) AS billable_time'),
        Capsule::raw('ROUND(SUM(COALESCE(e.sla_time,0)), 2) AS sla_time')
    )
    ->whereBetween('t.timesheet_date', [$from, $to]);

if (!empty($completedStatuses)) {
    $query->whereIn('t.status', $completedStatuses);
}

$query->groupBy('d.id', 'tc.' . $taskIdCol, 'a.id');

if ($groupBy === 'task') {
    $query->orderBy('tc.' . $taskNameCol)->orderBy('d.name');
} else {
    $query->orderBy('d.name')->orderBy('tc.' . $taskNameCol);
}

$rows = $query->get();

/* ----------------------------
 * Derive admin list from the result set (only admins present)
 * ---------------------------- */
$adminIds = [];
$adminNameMap = [];
foreach ($rows as $r) {
    $adminIds[(int)$r->admin_id] = true;
    $adminNameMap[(int)$r->admin_id] = $r->admin_name ?: ('Admin #' . (int)$r->admin_id);
}
$admins = [];
foreach (array_keys($adminIds) as $aid) {
    $admins[] = (object)[
        'id'   => (int)$aid,
        'name' => $adminNameMap[$aid],
    ];
}
usort($admins, function ($a, $b) {
    return strcasecmp($a->name, $b->name);
});

/* ----------------------------
 * Pivot
 * ---------------------------- */
$data = [];
foreach ($rows as $row) {
    $deptKey = (string)$row->department;
    $taskKey = (string)$row->task_category;

    if (!isset($data[$deptKey])) {
        $data[$deptKey] = [];
    }
    if (!isset($data[$deptKey][$taskKey])) {
        $data[$deptKey][$taskKey] = [];
    }
    $data[$deptKey][$taskKey][(int)$row->admin_id] = [
        'total'    => (float)$row->total_time,
        'billable' => (float)$row->billable_time,
        'sla'      => (float)$row->sla_time,
    ];
}

/* ----------------------------
 * Column totals per admin + overall grand totals
 * ---------------------------- */
$adminIdsList = array_map(function ($a) { return (int)$a->id; }, $admins);
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

/* ----------------------------
 * CSV Export (headered download)
 * ---------------------------- */
$baseUrl = 'addonmodules.php?module=timekeeper&timekeeperpage=reports';

$csvSanitize = function ($v) {
    $s = (string)$v;
    if ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) {
        $s = "'" . $s;
    }
    return $s;
};

if (isset($_GET['export']) && $_GET['export'] === '1') {
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=summary_report.csv');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    $firstCol  = ($groupBy === 'task') ? 'Task Category' : 'Department';
    $secondCol = ($groupBy === 'task') ? 'Department'    : 'Task Category';

    $header1 = [$csvSanitize($firstCol), $csvSanitize($secondCol)];
    foreach ($admins as $admin) {
        $header1[] = $csvSanitize($admin->name);
        $header1[] = '';
        $header1[] = '';
    }
    $header1[] = 'Row Totals';
    $header1[] = '';
    $header1[] = '';
    fputcsv($out, $header1);

    $header2 = ['', ''];
    foreach ($admins as $admin) {
        $header2[] = 'Total';
        $header2[] = 'Billable';
        $header2[] = 'SLA';
    }
    $header2[] = 'Total';
    $header2[] = 'Billable';
    $header2[] = 'SLA';
    fputcsv($out, $header2);

    foreach ($data as $dept => $tasks) {
        foreach ($tasks as $task => $adminData) {
            $rowArr = ($groupBy === 'task') ? [$csvSanitize($task), $csvSanitize($dept)] : [$csvSanitize($dept), $csvSanitize($task)];

            $rowTotalSum = 0.0;
            $rowBillableSum = 0.0;
            $rowSlaSum = 0.0;

            foreach ($admins as $admin) {
                if (isset($adminData[$admin->id])) {
                    $td = $adminData[$admin->id];
                    $t  = (float)$td['total'];
                    $b  = (float)$td['billable'];
                    $s  = (float)$td['sla'];
                    $rowTotalSum    += $t;
                    $rowBillableSum += $b;
                    $rowSlaSum      += $s;
                    $rowArr[] = number_format($t, 2);
                    $rowArr[] = number_format($b, 2);
                    $rowArr[] = number_format($s, 2);
                } else {
                    $rowArr[] = '0.00';
                    $rowArr[] = '0.00';
                    $rowArr[] = '0.00';
                }
            }

            $rowArr[] = number_format($rowTotalSum, 2);
            $rowArr[] = number_format($rowBillableSum, 2);
            $rowArr[] = number_format($rowSlaSum, 2);

            fputcsv($out, $rowArr);
        }
    }

    $footer = ['Column Totals', ''];
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

<form method="get" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" class="timekeeper-report-filters">
    <input type="hidden" name="module" value="timekeeper">
    <input type="hidden" name="timekeeperpage" value="reports">
    <input type="hidden" name="r" value="summary">

    <div class="filter-row">
        <div class="filter-item tk-min-180">
            <label>From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filter-item tk-min-180">
            <label>To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filter-item tk-min-180">
            <label>Group By</label>
            <select name="group_by">
                <option value="department" <?= $groupBy === 'department' ? 'selected' : '' ?>>Department</option>
                <option value="task" <?= $groupBy === 'task' ? 'selected' : '' ?>>Task</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a class="btn btn-success"
               href="<?= htmlspecialchars(
                   $baseUrl
                   . '&r=summary'
                   . '&from=' . urlencode($from)
                   . '&to=' . urlencode($to)
                   . '&group_by=' . urlencode($groupBy)
                   . '&export=1',
                   ENT_QUOTES,
                   'UTF-8'
               ) ?>">
                Export CSV
            </a>
        </div>
    </div>
</form>

<table class="table table-bordered timekeeper-report-table" style="margin-top:16px;">
    <thead>
        <tr>
            <th rowspan="2"><?= ($groupBy === 'task') ? 'Task Category' : 'Department' ?></th>
            <th rowspan="2"><?= ($groupBy === 'task') ? 'Department' : 'Task Category' ?></th>
            <?php foreach ($admins as $admin): ?>
                <th colspan="3" class="admin-group">
                    <?= htmlspecialchars($admin->name, ENT_QUOTES, 'UTF-8') ?>
                </th>
            <?php endforeach; ?>
            <th colspan="3" class="admin-group">Row Totals</th>
        </tr>
        <?php if (!empty($admins)): ?>
        <tr>
            <?php foreach ($admins as $admin): ?>
                <th class="num">Total</th>
                <th class="num">Billable</th>
                <th class="num">SLA</th>
            <?php endforeach; ?>
            <th class="num">Total</th>
            <th class="num">Billable</th>
            <th class="num">SLA</th>
        </tr>
        <?php endif; ?>
    </thead>

    <tbody>
        <?php if (empty($data)): ?>
            <tr>
                <td colspan="<?= 2 + (count($admins) * 3) + 3 ?>">
                    <div class="timekeeper-report-noresults">
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
                            $rowTotalSum = 0.0;
                            $rowBillableSum = 0.0;
                            $rowSlaSum = 0.0;

                            foreach ($admins as $admin):
                                if (isset($adminData[$admin->id])) {
                                    $td = $adminData[$admin->id];
                                    $totalVal    = (float)$td['total'];
                                    $billableVal = (float)$td['billable'];
                                    $slaVal      = (float)$td['sla'];

                                    $rowTotalSum    += $totalVal;
                                    $rowBillableSum += $billableVal;
                                    $rowSlaSum      += $slaVal;

                                    echo '<td class="num">' . number_format($totalVal, 2) . '</td>';
                                    echo '<td class="num">' . number_format($billableVal, 2) . '</td>';
                                    echo '<td class="num">' . number_format($slaVal, 2) . '</td>';
                                } else {
                                    echo '<td class="num">0.00</td><td class="num">0.00</td><td class="num">0.00</td>';
                                }
                            endforeach;

                            echo '<td class="num">' . number_format($rowTotalSum, 2) . '</td>';
                            echo '<td class="num">' . number_format($rowBillableSum, 2) . '</td>';
                            echo '<td class="num">' . number_format($rowSlaSum, 2) . '</td>';
                        ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>

    <tfoot>
        <tr>
            <th colspan="2" class="tk-text-right">Column Totals</th>
            <?php foreach ($admins as $admin): ?>
                <th class="num"><?= number_format($adminColTotals[$admin->id]['total'], 2) ?></th>
                <th class="num"><?= number_format($adminColTotals[$admin->id]['billable'], 2) ?></th>
                <th class="num"><?= number_format($adminColTotals[$admin->id]['sla'], 2) ?></th>
            <?php endforeach; ?>
            <th class="num"><?= number_format($grandTotals['total'], 2) ?></th>
            <th class="num"><?= number_format($grandTotals['billable'], 2) ?></th>
            <th class="num"><?= number_format($grandTotals['sla'], 2) ?></th>
        </tr>
    </tfoot>
</table>
