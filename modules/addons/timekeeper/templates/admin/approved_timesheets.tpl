<?php if (!defined('WHMCS')) { die('Access Denied'); } ?>
<?php $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<link rel="stylesheet" href="../modules/addons/timekeeper/css/approved_timesheets.css?v=5" />
<script defer src="../modules/addons/timekeeper/js/approved_timesheets.js?v=5"></script>

<div class="timekeeper-root approved-timesheets">
  <div class="tk-page-header">
    <div class="tk-page-title">
      <h2 class="tk-h2">Approved Timesheets</h2>
      <p class="tk-subtitle">Timesheets that have been approved.</p>
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
              <form method="post" class="tk-unapprove-form">
                <input type="hidden" name="tk_csrf" value="<?= $h($tkCsrf) ?>">
                <input type="hidden" name="tk_action" value="unapprove">
                <input type="hidden" name="ts_id" value="<?= (int)$ts->id ?>">
                <button type="submit" class="tk-btn tk-btn-sm tk-btn-rounded tk-btn-warning">Unapprove</button>
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
          <p class="tk-muted">
            Admin: <?= $h($adminMap[$timesheet->admin_id] ?? 'Unknown') ?>
            · Date: <?= $h($timesheet->timesheet_date) ?>
          </p>
        </div>
        <div class="tk-card-actions">
          <a class="tk-btn tk-btn-rounded tk-btn-outline"
             href="addonmodules.php?module=timekeeper&timekeeperpage=approved_timesheets">
            Back
          </a>
          <?php if (!empty($canUnapprove)): ?>
            <form method="post" class="tk-unapprove-form">
              <input type="hidden" name="tk_csrf" value="<?= $h($tkCsrf) ?>">
              <input type="hidden" name="tk_action" value="unapprove">
              <input type="hidden" name="ts_id" value="<?= (int)$timesheet->id ?>">
              <button type="submit" class="tk-btn tk-btn-rounded tk-btn-warning">Unapprove</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($timesheetEntries)): ?>
        <div class="tk-alert tk-alert-info">No entries on this timesheet.</div>
      <?php else: ?>
        <!-- Saved Entries — SAME STRUCTURE AS timesheet.tpl -->
        <!-- Header row -->
        <div class="tk-row tk-row--table tk-row--header">
          <div class="tk-row-grid">
            <div class="hdr">Client</div>
            <div class="hdr">Department</div>
            <div class="hdr">Task</div>
            <div class="hdr">Description</div>
            <div class="hdr">Time</div>
            <div class="hdr">Flags</div>
            <div class="hdr" style="text-align:right;">&nbsp;</div>
          </div>
        </div>

        <!-- Data rows -->
        <?php foreach ($timesheetEntries as $entry): ?>
          <div class="tk-row tk-row--table">
            <div class="tk-row-grid">
              <div><?= $h($clientMap[$entry->client_id] ?? 'N/A') ?></div>
              <div><?= $h($departmentMap[$entry->department_id] ?? 'N/A') ?></div>
              <div><?= $h($taskMap[$entry->task_category_id] ?? 'N/A') ?></div>

              <div class="cell-desc"><?= $h($entry->notes) ?></div>

              <div class="cell-times">
                <?php
                $start = isset($entry->start_time) ? (string)$entry->start_time : '';
                $end   = isset($entry->end_time)   ? (string)$entry->end_time   : '';
                ?>
                <?php if ($start && $end): ?>
                  <span><?= $h($start) ?>–<?= $h($end) ?></span>
                <?php endif; ?>
                <strong><?= number_format((float)$entry->time_spent, 2) ?> hrs</strong>
              </div>

              <div class="cell-flags">
                <?php if (!empty($entry->billable)): ?>
                  <span class="tk-badge tk-badge--success">Billable: <?= number_format((float)$entry->billable_time, 2) ?> hrs</span>
                <?php else: ?>
                  <span class="tk-badge">Non-billable</span>
                <?php endif; ?>

                <?php if (!empty($entry->sla)): ?>
                  <span class="tk-badge">SLA: <?= number_format((float)$entry->sla_time, 2) ?> hrs</span>
                <?php endif; ?>
              </div>

              <div class="cell-actions">&nbsp;</div>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Totals (compact bar to match timesheet.css) -->
        <div class="tk-totals-wrap">
          <div class="tk-totals-bar">
            <span class="lbl">Total</span>
            <span class="sep">•</span>
            <span class="val"><?= number_format((float)$totalTime, 2) ?></span>
            <span class="unit">hrs</span>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
