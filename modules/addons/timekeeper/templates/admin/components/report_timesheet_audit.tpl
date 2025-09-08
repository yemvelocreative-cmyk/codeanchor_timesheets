<?php if (!defined('WHMCS')) { die('Access Denied'); } ?>
<?php
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$baseUrl = 'addonmodules.php?module=timekeeper&timekeeperpage=reports&r=timesheet_audit';
?>
<?php if(isset($__tkAsset)): ?><link rel="stylesheet" href="<?= $__tkAsset('css/report_output.css'); ?>"><?php endif; ?>

<div id="ts-audit" class="timekeeper-report">
  <h3><?= $h($reportTitle ?? 'Timesheet Audit Report') ?></h3>

  <form method="get" action="<?= $h($baseUrl) ?>" class="timekeeper-report-filters">
    <input type="hidden" name="module" value="timekeeper">
    <input type="hidden" name="timekeeperpage" value="reports">
    <input type="hidden" name="r" value="timesheet_audit">

    <div class="filter-row">
      <div class="filter-item tk-min-180">
        <label>From</label>
        <input type="date" name="from" value="<?= $h($from) ?>">
      </div>
      <div class="filter-item tk-min-180">
        <label>To</label>
        <input type="date" name="to" value="<?= $h($to) ?>">
      </div>

      <div class="filter-item tk-min-220">
        <label>Admin</label>
        <select name="admin_id">
          <option value="0">All</option>
          <?php foreach (($adminMap ?? []) as $id => $name): ?>
            <option value="<?= (int)$id ?>" <?= (!empty($filterAdminId) && (int)$filterAdminId === (int)$id) ? 'selected' : '' ?>>
              <?= $h($name) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-item tk-min-220">
        <label>Client</label>
        <select name="client_id" class="js-client-select">
          <option value="0">All</option>
          <?php foreach (($clientMap ?? []) as $id => $name): ?>
            <option value="<?= (int)$id ?>" <?= (!empty($filterClientId) && (int)$filterClientId === (int)$id) ? 'selected' : '' ?>>
              <?= $h($name) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-item tk-min-160">
        <label>Status</label>
        <select name="status">
          <?php
            $statuses = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
            $selStatus = $filterStatus ?? 'all';
          ?>
          <?php foreach ($statuses as $val => $label): ?>
            <option value="<?= $h($val) ?>" <?= ($selStatus === $val) ? 'selected' : '' ?>><?= $h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-item tk-min-160">
        <label>Group By</label>
        <select name="group_by">
          <?php
            $groupsAllowed = ['none' => 'None', 'client' => 'Client', 'admin' => 'Admin'];
            $selGroup = $groupBy ?? 'none';
          ?>
          <?php foreach ($groupsAllowed as $val => $label): ?>
            <option value="<?= $h($val) ?>" <?= ($selGroup === $val) ? 'selected' : '' ?>><?= $h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a class="btn btn-secondary" href="<?= $h($baseUrl) ?>">Clear</a>
        <!-- Triggers client-side Blob download (see report_timesheet_audit.php) -->
        <a class="btn btn-success"
           href="<?= $h($baseUrl
               . '&from=' . urlencode($from)
               . '&to=' . urlencode($to)
               . '&admin_id=' . (int)($filterAdminId ?? 0)
               . '&client_id=' . (int)($filterClientId ?? 0)
               . '&status=' . urlencode($selStatus)
               . '&group_by=' . urlencode($selGroup)
               . '&export=csv'
            ) ?>">
          Export CSV
        </a>
      </div>
    </div>
  </form>

  <?php if (($groupBy ?? 'none') === 'none'): ?>
    <!-- Flat table -->
    <div class="table-responsive">
      <table class="table table-bordered timekeeper-report-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Status</th>
            <th>Admin</th>
            <th>Client</th>
            <th>Department</th>
            <th>Task Category</th>
            <th>Ticket ID</th>
            <th>Description</th>
            <th>Start</th>
            <th>End</th>
            <th>Time Spent (hrs)</th>
            <th>Billable</th>
            <th>Billable Time (hrs)</th>
            <th>SLA</th>
            <th>SLA Time (hrs)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="15">
                <div class="timekeeper-report-noresults">
                  No entries for the selected filters.
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= $h($r['timesheet_date']) ?></td>
                <td><?= $h(ucfirst($r['timesheet_status'])) ?></td>
                <td><?= $h($r['admin_name']) ?></td>
                <td><?= $h($r['client_name']) ?></td>
                <td><?= $h($r['department']) ?></td>
                <td><?= $h($r['task_category']) ?></td>
                <td><?= $h($r['ticket_id']) ?></td>
                <td class="description"><?= $h($r['description']) ?></td>
                <td><?= $h($r['start_time']) ?></td>
                <td><?= $h($r['end_time']) ?></td>
                <td class="num"><?= $h($r['time_spent']) ?></td>
                <td><?= !empty($r['billable']) ? 'Yes' : 'No' ?></td>
                <td class="num"><?= $h($r['billable_time']) ?></td>
                <td><?= !empty($r['sla']) ? 'Yes' : 'No' ?></td>
                <td class="num"><?= $h($r['sla_time']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="10" class="tk-text-right">Grand Totals</th>
            <th class="num"><?= $h($totals['spent_hhmm'] ?? '') ?> (<?= $h($totals['spent_hhmm'] ?? '') ?>)</th>
            <th></th>
            <th class="num"><?= $h($totals['billable_hhmm'] ?? '') ?> (<?= $h($totals['billable_hhmm'] ?? '') ?>)</th>
            <th></th>
            <th class="num"><?= $h($totals['sla_hhmm'] ?? '') ?> (<?= $h($totals['sla_hhmm'] ?? '') ?>)</th>
          </tr>
        </tfoot>
      </table>
    </div>

  <?php else: ?>
    <!-- Grouped view -->
    <?php
      $groupLabel = ($groupBy === 'client') ? 'Client' : 'Admin';
      $prefix = ($groupBy === 'client') ? 'Client: ' : 'Admin: ';
    ?>
    <?php if (empty($groups)): ?>
      <div class="timekeeper-report-noresults">
        No entries for the selected filters.
      </div>
    <?php else: ?>
      <?php foreach ($groups as $g): ?>
        <h4 class="tk-group-header"><?= $h($prefix . ($g['label'] ?? '—')) ?></h4>
        <div class="table-responsive">
          <table class="table table-bordered timekeeper-report-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Status</th>
                <th>Admin</th>
                <th>Client</th>
                <th>Department</th>
                <th>Task Category</th>
                <th>Ticket ID</th>
                <th>Description</th>
                <th>Start</th>
                <th>End</th>
                <th>Time Spent (hrs)</th>
                <th>Billable</th>
                <th>Billable Time (hrs)</th>
                <th>SLA</th>
                <th>SLA Time (hrs)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (($g['rows'] ?? []) as $r): ?>
                <tr>
                  <td><?= $h($r['timesheet_date']) ?></td>
                  <td><?= $h(ucfirst($r['timesheet_status'])) ?></td>
                  <td><?= $h($r['admin_name']) ?></td>
                  <td><?= $h($r['client_name']) ?></td>
                  <td><?= $h($r['department']) ?></td>
                  <td><?= $h($r['task_category']) ?></td>
                  <td><?= $h($r['ticket_id']) ?></td>
                  <td class="description"><?= $h($r['description']) ?></td>
                  <td><?= $h($r['start_time']) ?></td>
                  <td><?= $h($r['end_time']) ?></td>
                  <td class="num"><?= $h($r['time_spent']) ?></td>
                  <td><?= !empty($r['billable']) ? 'Yes' : 'No' ?></td>
                  <td class="num"><?= $h($r['billable_time']) ?></td>
                  <td><?= !empty($r['sla']) ? 'Yes' : 'No' ?></td>
                  <td class="num"><?= $h($r['sla_time']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="10" class="tk-text-right"><?= $h($groupLabel) ?> Subtotal</th>
                <th class="num"><?= $h($g['totals_fmt']['spent'] ?? '') ?></th>
                <th></th>
                <th class="num"><?= $h($g['totals_fmt']['billable'] ?? '') ?></th>
                <th></th>
                <th class="num"><?= $h($g['totals_fmt']['sla'] ?? '') ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endforeach; ?>

      <!-- Grand totals footer for grouped view -->
      <div class="table-responsive">
        <table class="table table-bordered timekeeper-report-table">
          <tfoot>
            <tr>
              <th colspan="10" class="tk-text-right">Grand Totals</th>
              <th class="num"><?= $h($totals['spent_hhmm'] ?? '') ?></th>
              <th></th>
              <th class="num"><?= $h($totals['billable_hhmm'] ?? '') ?></th>
              <th></th>
              <th class="num"><?= $h($totals['sla_hhmm'] ?? '') ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
