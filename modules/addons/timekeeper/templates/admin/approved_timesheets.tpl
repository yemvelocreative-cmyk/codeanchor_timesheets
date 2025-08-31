<?php if (!defined('WHMCS')) { die('Access Denied'); } ?>
<?php $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<link rel="stylesheet" href="../modules/addons/timekeeper/css/approved_timesheets.css?v=2" />
<script defer src="../modules/addons/timekeeper/js/approved_timesheets.js?v=2"></script>

<div class="timekeeper-root approved-timesheets">
  <div class="tk-page-header">
    <div class="tk-page-title">
      <h2 class="tk-h2">Approved Timesheets</h2>
      <p class="tk-subtitle">Timesheets that have been approved and are ready for view/export.</p>
    </div>
  </div>

  <?php if (empty($approvedTimesheets)): ?>
    <div class="tk-alert tk-alert-success">No approved timesheets found.</div>
  <?php else: ?>
    <div class="tk-table tk-table-grid tk-table-compact">
      <div class="tk-thead tk-row">
        <div class="tk-col tk-w-200">Admin</div>
        <div class="tk-col tk-w-150">Date</div>
        <div class="tk-col tk-w-120">Status</div>
        <div class="tk-col tk-w-220 tk-text-right">Actions</div>
      </div>

      <?php foreach ($approvedTimesheets as $ts): ?>
        <div class="tk-row">
          <div class="tk-col tk-w-200"><?= $h($adminMap[$ts->admin_id] ?? 'Unknown') ?></div>
          <div class="tk-col tk-w-150"><?= $h($ts->timesheet_date) ?></div>
          <div class="tk-col tk-w-120"><span class="tk-pill tk-pill-success">Approved</span></div>

          <div class="tk-col tk-w-220 tk-actions">
            <a class="tk-btn tk-btn-sm tk-btn-rounded tk-btn-outline"
               href="addonmodules.php?module=timekeeper&timekeeperpage=approved_timesheets&admin_id=<?= (int)$ts->admin_id ?>&date=<?= $h($ts->timesheet_date) ?>">
              View
            </a>

            <?php if (!empty($canUnapprove)): ?>
              <form method="post" class="tk-inline-form tk-unapprove-form" style="display:inline;">
                <input type="hidden" name="tk_csrf" value="<?= $h($tkCsrf) ?>">
                <input type="hidden" name="tk_action" value="unapprove">
                <input type="hidden" name="ts_id" value="<?= (int)$ts->id ?>">
                <button type="submit" class="tk-btn tk-btn-sm tk-btn-rounded tk-btn-warning js-unapprove">
                  Unapprove
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($timesheet)): ?>
    <div class="tk-card tk-mt-24">
      <div class="tk-card-header">
        <div>
          <h3 class="tk-h3">Timesheet</h3>
          <p class="tk-muted">Admin: <?= $h($adminMap[$timesheet->admin_id] ?? 'Unknown') ?> · Date: <?= $h($timesheet->timesheet_date) ?></p>
        </div>
        <div class="tk-card-actions">
          <a class="tk-btn tk-btn-rounded"
             href="addonmodules.php?module=timekeeper&timekeeperpage=reports&report=timesheet&admin_id=<?= (int)$timesheet->admin_id ?>&date=<?= $h($timesheet->timesheet_date) ?>">
            Export
          </a>
        </div>
      </div>

      <?php if (empty($timesheetEntries)): ?>
        <div class="tk-alert tk-alert-info">No entries on this timesheet.</div>
      <?php else: ?>
        <div class="tk-table tk-table-grid tk-mt-12">
          <div class="tk-thead tk-row">
            <div class="tk-col tk-w-200">Client</div>
            <div class="tk-col tk-w-180">Department</div>
            <div class="tk-col tk-w-180">Task</div>
            <div class="tk-col tk-w-90 tk-text-right">Time</div>
            <div class="tk-col tk-w-250">Notes</div>
            <div class="tk-col tk-w-90 tk-text-center">Billable</div>
            <div class="tk-col tk-w-90 tk-text-right">Bill Time</div>
            <div class="tk-col tk-w-80 tk-text-center">SLA</div>
            <div class="tk-col tk-w-90 tk-text-right">SLA Time</div>
          </div>

          <?php foreach ($timesheetEntries as $entry): ?>
            <div class="tk-row">
              <div class="tk-col tk-w-200"><?= $h($clientMap[$entry->client_id] ?? 'N/A') ?></div>
              <div class="tk-col tk-w-180"><?= $h($departmentMap[$entry->department_id] ?? 'N/A') ?></div>
              <div class="tk-col tk-w-180"><?= $h($taskMap[$entry->task_category_id] ?? 'N/A') ?></div>
              <div class="tk-col tk-w-90 tk-text-right"><?= number_format((float)$entry->time_spent, 2) ?> hrs</div>
              <div class="tk-col tk-w-250 tk-notes"><?= $h($entry->notes) ?></div>
              <div class="tk-col tk-w-90 tk-text-center"><?= $entry->billable ? 'Yes' : 'No' ?></div>
              <div class="tk-col tk-w-90 tk-text-right"><?= number_format((float)$entry->billable_time, 2) ?> hrs</div>
              <div class="tk-col tk-w-80 tk-text-center"><?= $entry->sla ? 'Yes' : 'No' ?></div>
              <div class="tk-col tk-w-90 tk-text-right"><?= number_format((float)$entry->sla_time, 2) ?> hrs</div>
            </div>
          <?php endforeach; ?>

          <div class="tk-row tk-row-total">
            <div class="tk-col tk-w-200"></div>
            <div class="tk-col tk-w-180"></div>
            <div class="tk-col tk-w-180"></div>
            <div class="tk-col tk-w-90 tk-text-right"><strong><?= number_format((float)$totalTime, 2) ?> hrs</strong></div>
            <div class="tk-col tk-w-250 tk-text-right"><strong>Totals:</strong></div>
            <div class="tk-col tk-w-90"></div>
            <div class="tk-col tk-w-90"></div>
            <div class="tk-col tk-w-80"></div>
            <div class="tk-col tk-w-90"></div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
