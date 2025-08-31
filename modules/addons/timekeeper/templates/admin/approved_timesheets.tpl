<?php if (!defined('WHMCS')) { die('Access Denied'); } ?>
<?php $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<link rel="stylesheet" href="../modules/addons/timekeeper/css/approved_timesheets.css?v=10" />
<script defer src="../modules/addons/timekeeper/js/approved_timesheets.js?v=10"></script>

<div class="timekeeper-root approved-timesheets">
  <div class="tk-page-header">
    <div class="tk-page-title">
      <h2 class="tk-h2">Approved Timesheets</h2>
      <p class="tk-subtitle">Timesheets that have been approved.</p>
    </div>
  </div>

  <?php $isListing = empty($timesheet); ?>

  <?php if ($isListing): ?>
    <!-- Filters -->
    <form method="get" action="addonmodules.php" class="tk-filters" role="search" aria-label="Filter approved timesheets">
      <input type="hidden" name="module" value="timekeeper" />
      <input type="hidden" name="timekeeperpage" value="approved_timesheets" />

      <div class="tk-field">
        <label for="flt-start">Start date</label>
        <input type="date" id="flt-start" name="start_date" value="<?= $h($filters['start_date'] ?? '') ?>" />
      </div>

      <div class="tk-field">
        <label for="flt-end">End date</label>
        <input type="date" id="flt-end" name="end_date" value="<?= $h($filters['end_date'] ?? '') ?>" />
      </div>

      <div class="tk-field">
        <label for="flt-admin">User</label>
        <select id="flt-admin" name="filter_admin_id" <?= empty($canUseAdminFilter) ? 'disabled' : '' ?>>
          <option value=""><?= $h($canUseAdminFilter ? 'All users' : 'Your timesheets') ?></option>
          <?php foreach ($adminMap as $aid => $aname): ?>
            <option value="<?= (int)$aid ?>" <?= (!empty($filters['filter_admin_id']) && (int)$filters['filter_admin_id'] === (int)$aid) ? 'selected' : '' ?>>
              <?= $h($aname) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="tk-actions">
        <button type="submit" class="tk-btn tk-btn-rounded">Apply</button>
        <a class="tk-btn tk-btn-outline tk-btn-rounded" href="addonmodules.php?module=timekeeper&amp;timekeeperpage=approved_timesheets">Clear</a>
      </div>
    </form>

    <?php
      // Pager helpers (provided by PHP controller)
      $from = (int)($pager['from']  ?? 0);
      $to   = (int)($pager['to']    ?? 0);
      $tot  = (int)($pager['total'] ?? 0);
      $pg   = (int)($pager['page']  ?? 1);
      $pgs  = (int)($pager['pages'] ?? 1);
      $prevUrl = (string)($pager['prevUrl'] ?? '');
      $nextUrl = (string)($pager['nextUrl'] ?? '');
    ?>

    <?php if (!empty($approvedTimesheets)): ?>
      <!-- Pager (top) -->
      <div class="tk-pager">
        <div class="tk-pager-info">
          <span class="tk-muted">Showing</span>
          <strong><?= $from ?></strong><span class="tk-muted">–</span><strong><?= $to ?></strong>
          <span class="tk-muted">of</span>
          <strong><?= $tot ?></strong>
          <span class="tk-muted">(Page <?= $pg ?> of <?= $pgs ?>)</span>
        </div>
        <div class="tk-pager-controls">
          <?php if ($prevUrl): ?>
            <a class="tk-btn tk-btn-outline tk-btn-sm tk-btn-rounded" href="<?= $h($prevUrl) ?>" aria-label="Previous page">Prev</a>
          <?php else: ?>
            <span class="tk-btn tk-btn-outline tk-btn-sm tk-btn-rounded" aria-disabled="true">Prev</span>
          <?php endif; ?>

          <?php if ($nextUrl): ?>
            <a class="tk-btn tk-btn-outline tk-btn-sm tk-btn-rounded" href="<?= $h($nextUrl) ?>" aria-label="Next page">Next</a>
          <?php else: ?>
            <span class="tk-btn tk-btn-outline tk-btn-sm tk-btn-rounded" aria-disabled="true">Next</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

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

      <!-- Pager (bottom) -->
      <div class="tk-pager tk-mt-12">
        <div class="tk-pager-info">
          <span class="tk-muted">Showing</span>
          <strong><?= $from ?></strong><span class="tk-muted">–</span><strong><?= $to ?></strong>
          <span class="tk-muted">of</span>
          <strong><?= $tot ?></strong>
          <span class="tk-muted">(Page <?= $pg ?> of <?= $pgs ?>)</span>
        </div>
        <div class="tk-pager-controls">
          <?php if ($prevUrl): ?>
            <a class="tk-btn tk-btn-outline tk-btn-sm tk-btn-rounded" href="<?= $h($prevUrl) ?>" aria-label="Previous page">Prev</a>
          <?php else: ?>
            <span class="tk-btn tk-btn-outline tk-btn-sm tk-btn-rounded" aria-disabled="true">Prev</span>
          <?php endif; ?>

          <?php if ($nextUrl): ?>
            <a class="tk-btn tk-btn-outline tk-btn-sm tk-btn-rounded" href="<?= $h($nextUrl) ?>" aria-label="Next page">Next</a>
          <?php else: ?>
            <span class="tk-btn tk-btn-outline tk-btn-sm tk-btn-rounded" aria-disabled="true">Next</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
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
        <h4>Saved Entries</h4>
        <div class="tk-totals-wrap">
          <div class="tk-totals-bar" role="status" aria-label="Daily totals">
            <span class="lbl">Total</span>
            <strong class="val"><?= number_format((float)$totalTime, 2) ?></strong><span class="unit">hrs</span>
            <span class="sep">•</span>
            <span class="lbl">Billable</span>
            <strong class="val"><?= number_format((float)$totalBillable, 2) ?></strong><span class="unit">hrs</span>
            <span class="sep">•</span>
            <span class="lbl">SLA</span>
            <strong class="val"><?= number_format((float)$totalSla, 2) ?></strong><span class="unit">hrs</span>
          </div>
        </div>

        <!-- Header row styled as a full card; no Actions column -->
        <div class="tk-row tk-card tk-row--table tk-row--header">
          <div class="tk-row-grid">
            <div class="hdr">Client</div>
            <div class="hdr">Department</div>
            <div class="hdr">Task Category</div>
            <div class="hdr">Description</div>
            <div class="hdr">Time</div>
            <div class="hdr">Flags</div>
          </div>
        </div>

        <!-- Data rows -->
        <div class="tk-saved-list">
          <?php foreach ($timesheetEntries as $entry): ?>
            <div class="tk-row tk-card tk-row--table">
              <div class="tk-row-grid">
                <div class="cell cell-client"><?= $h($clientMap[$entry->client_id] ?? 'N/A') ?></div>
                <div class="cell cell-dept"><?= $h($departmentMap[$entry->department_id] ?? 'N/A') ?></div>
                <div class="cell cell-task"><?= $h($taskMap[$entry->task_category_id] ?? 'N/A') ?></div>

                <div class="cell cell-desc"><?= $h(($entry->notes ?? '') !== '' ? $entry->notes : ($entry->description ?? '')) ?></div>

                <div class="cell cell-times">
                  <?php
                    $start = isset($entry->start_time) ? (string)$entry->start_time : '';
                    $end   = isset($entry->end_time)   ? (string)$entry->end_time   : '';
                  ?>
                  <?php if ($start && $end): ?>
                    <span><?= $h($start) ?>–<?= $h($end) ?></span>
                    <span class="sep">•</span>
                  <?php endif; ?>
                  <span><strong><?= number_format((float)$entry->time_spent, 2) ?></strong> hrs</span>
                </div>

                <div class="cell cell-flags">
                  <?php if ((float)$entry->billable_time > 0): ?>
                    <span class="tk-badge">Billable <?= number_format((float)$entry->billable_time, 2) ?>h</span>
                  <?php else: ?>
                    <span class="tk-badge">Non-billable</span>
                  <?php endif; ?>
                  <?php if ((float)$entry->sla_time > 0): ?>
                    <span class="tk-badge">SLA <?= number_format((float)$entry->sla_time, 2) ?>h</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
