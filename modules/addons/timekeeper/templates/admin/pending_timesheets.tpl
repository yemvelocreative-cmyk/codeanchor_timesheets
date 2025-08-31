<div class="pending-root">

  <h2>Pending Timesheets</h2>

  <?php if (isset($_GET['approved']) && $_GET['approved'] == 1): ?>
    <div class="alert alert-success">Timesheet approved successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET['rejected']) && $_GET['rejected'] == 1): ?>
    <div class="alert alert-danger">Timesheet rejected.</div>
  <?php endif; ?>

  <?php if (isset($_GET['resubmitted']) && $_GET['resubmitted'] == 1): ?>
    <div class="alert alert-success">Timesheet re-submitted for approval.</div>
  <?php endif; ?>

  <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
    <div class="alert alert-success">New line added successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET['add_error']) && $_GET['add_error'] == 1): ?>
    <div class="alert alert-danger">The End Time must be later than the Start Time.</div>
  <?php endif; ?>

  <?php if (empty($pendingTimesheets)): ?>
    <div class="alert alert-info">No pending timesheets found.</div>
  <?php else: ?>
    <div class="pt-list">
      <header>Awaiting Action</header>

      <!-- Header row (5 cols per CSS grid: Admin | Date | Status | (empty) | Actions) -->
      <div class="pt-row" style="font-weight:600;">
        <div>Admin</div>
        <div>Date</div>
        <div>Status</div>
        <div></div>
        <div class="pt-actions">Actions</div>
      </div>

      <?php foreach ($pendingTimesheets as $ts): ?>
        <div class="pt-row">
          <div><?= htmlspecialchars($adminMap[$ts->admin_id] ?? 'Unknown') ?></div>
          <div class="muted"><?= htmlspecialchars($ts->timesheet_date) ?></div>
          <div>
            <span class="pt-badge <?= htmlspecialchars($ts->status) ?>">
              <?= ucfirst($ts->status) ?>
            </span>
          </div>
          <div></div>
          <div class="pt-actions">
            <a class="btn btn-sm btn-primary"
               href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id=<?= (int)$ts->admin_id ?>&date=<?= htmlspecialchars($ts->timesheet_date) ?>">
              View Timesheet
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($editMode): ?>
    <div class="pt-detail">
      <header>
        <?php if ($editingEntryId): ?>
          Editing Timesheet: <?= htmlspecialchars($editAdminName) ?> — <?= htmlspecialchars($editTimesheetDate) ?>
        <?php else: ?>
          Viewing Timesheet: <?= htmlspecialchars($editAdminName) ?> — <?= htmlspecialchars($editTimesheetDate) ?>
        <?php endif; ?>
      </header>
      <div class="body">

        <!-- Rejection note (if applicable) -->
        <?php if (isset($timesheet) && $timesheet->status === 'rejected' && !empty($timesheet->admin_rejection_note)): ?>
          <div class="alert alert-danger" style="margin-bottom:14px;">
            <strong>Reason for rejection:</strong><br>
            <?= nl2br(htmlspecialchars($timesheet->admin_rejection_note)) ?>
            <?php if (!empty($timesheet->rejected_at) || !empty($timesheet->rejected_by)): ?>
              <br>
              <span style="font-size:90%;">
                <?php if (!empty($timesheet->rejected_at)): ?>
                  <strong>Rejected on:</strong> <?= htmlspecialchars($timesheet->rejected_at) ?>
                <?php endif; ?>
                <?php if (!empty($timesheet->rejected_by) && isset($adminMap[$timesheet->rejected_by])): ?>
                  &nbsp;<strong>by</strong> <?= htmlspecialchars($adminMap[$timesheet->rejected_by]) ?>
                <?php endif; ?>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Add New Line -->
        <div class="pt-list" style="margin-bottom:16px;">
          <header>Add New Line</header>
          <div class="body">
            <!-- Header labels (match grid), with billable/SLA headers toggle targets -->
            <div class="pt-entry-row" style="font-weight:600;">
              <div>Client</div>
              <div>Department</div>
              <div>Task Category</div>
              <div>Ticket ID</div>
              <div>Description</div>
              <div>Start</div>
              <div>End</div>
              <div>Time Spent</div>
              <div>Billable</div>
              <div id="pt-billable-header" class="col-hidden">Billable Time</div>
              <div>SLA</div>
              <div id="pt-sla-header" class="col-hidden">SLA Time</div>
              <div></div>
              <div></div>
            </div>

            <form method="post"
                  id="pt-add-form"
                  class="pt-entry-row"
                  action="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets">
              <?php if (!empty($tkCsrf)): ?>
                <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf) ?>">
              <?php endif; ?>
              <input type="hidden" name="add_new_entry" value="1">
              <input type="hidden" name="admin_id" value="<?= (int)$editAdminId ?>">
              <input type="hidden" name="timesheet_date" value="<?= htmlspecialchars($editTimesheetDate) ?>">

              <!-- Client -->
              <select name="client_id" required>
                <option value="">Select…</option>
                <?php foreach ($clientMap as $id => $label): ?>
                  <option value="<?= (int)$id ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>

              <!-- Department -->
              <select name="department_id" id="pending-add-department" required>
                <option value="">Select…</option>
                <?php foreach ($departmentMap as $id => $label): ?>
                  <option value="<?= (int)$id ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>

              <!-- Task Category (filtered by department) -->
              <select name="task_category_id" id="pending-add-task-category" required>
                <option value="">Select…</option>
                <?php foreach ($taskCategories as $cat): ?>
                  <option value="<?= (int)$cat->id ?>" data-dept="<?= (int)$cat->department_id ?>">
                    <?= htmlspecialchars($cat->name) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <!-- Ticket & description -->
              <input type="text" name="ticket_id" placeholder="">
              <input type="text" name="description" placeholder="">

              <!-- Times -->
              <input type="time" name="start_time" required>
              <input type="time" name="end_time" required>
              <input type="text" name="time_spent" readonly>

              <!-- Billable / Time -->
              <input type="checkbox" name="billable" value="1">
              <input type="text" name="billable_time" class="col-hidden" placeholder="">

              <!-- SLA / Time -->
              <input type="checkbox" name="sla" value="1" id="sla-checkbox">
              <input type="text" name="sla_time" class="col-hidden" placeholder="">

              <!-- Actions -->
              <button type="submit" class="btn btn-sm btn-success">Add</button>
              <div></div>
            </form>
          </div>
        </div>

        <!-- Existing entries -->
        <?php if (empty($editTimesheetEntries)): ?>
          <div class="alert alert-warning">No entries found for this timesheet.</div>
        <?php else: ?>
          <?php
            $totalTime = 0.0;
            $totalBillableTime = 0.0;
            $totalSlaTime = 0.0;
            foreach ($editTimesheetEntries as $entry) {
              $totalTime += (float)$entry->time_spent;
              $totalBillableTime += (float)$entry->billable_time;
              $totalSlaTime += (float)$entry->sla_time;
            }
          ?>

          <!-- Labels -->
          <div class="pt-entry-row" style="font-weight:600;">
            <div>Client</div>
            <div>Department</div>
            <div>Task Category</div>
            <div>Ticket ID</div>
            <div>Description</div>
            <div>Start</div>
            <div>End</div>
            <div>Time Spent</div>
            <div>Billable</div>
            <div>Billable Time</div>
            <div>SLA</div>
            <div>SLA Time</div>
            <div></div>
            <div></div>
          </div>

          <?php foreach ($editTimesheetEntries as $entry): ?>
            <?php $isEditing = ($editingEntryId == $entry->id); ?>

            <?php if ($isEditing): ?>
              <form method="post"
                    class="pt-entry-row"
                    action="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets">
                <?php if (!empty($tkCsrf)): ?>
                  <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf) ?>">
                <?php endif; ?>
                <input type="hidden" name="save_id" value="<?= (int)$entry->id ?>">
                <input type="hidden" name="admin_id" value="<?= (int)$editAdminId ?>">
                <input type="hidden" name="timesheet_date" value="<?= htmlspecialchars($editTimesheetDate) ?>">

                <select name="client_id">
                  <?php foreach ($clientMap as $id => $label): ?>
                    <option value="<?= (int)$id ?>" <?= ((int)$entry->client_id === (int)$id) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <select name="department_id" class="pending-edit-department">
                  <?php foreach ($departmentMap as $id => $label): ?>
                    <option value="<?= (int)$id ?>" <?= ((int)$entry->department_id === (int)$id) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <select name="task_category_id" class="pending-edit-task-category">
                  <?php foreach ($taskCategories as $cat): ?>
                    <option value="<?= (int)$cat->id ?>"
                            data-dept="<?= (int)$cat->department_id ?>"
                            <?= ((int)$entry->task_category_id === (int)$cat->id) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($cat->name) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <input type="text" name="ticket_id" value="<?= htmlspecialchars($entry->ticket_id) ?>">
                <input type="text" name="description" value="<?= htmlspecialchars($entry->description) ?>">
                <input type="time" name="start_time" value="<?= htmlspecialchars($entry->start_time) ?>">
                <input type="time" name="end_time" value="<?= htmlspecialchars($entry->end_time) ?>">
                <input type="text" name="time_spent" value="<?= number_format((float)$entry->time_spent, 2) ?>" readonly>

                <input type="checkbox" name="billable" value="1" <?= $entry->billable ? 'checked' : '' ?>>
                <input type="text" name="billable_time" value="<?= number_format((float)$entry->billable_time, 2) ?>">

                <input type="checkbox" name="sla" value="1" <?= $entry->sla ? 'checked' : '' ?>>
                <input type="text" name="sla_time" value="<?= number_format((float)$entry->sla_time, 2) ?>">

                <button type="submit" class="btn btn-sm btn-success">Save</button>
                <a href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id=<?= (int)$editAdminId ?>&date=<?= htmlspecialchars($editTimesheetDate) ?>" class="btn btn-sm btn-secondary">Cancel</a>
              </form>

              <?php
                $needsVerify = (
                  isset($unbilledTimeValidateMin) && $unbilledTimeValidateMin !== '' && $unbilledTimeValidateMin !== null
                  && (float)$entry->time_spent >= (float)$unbilledTimeValidateMin
                  && (int)$entry->billable === 0
                  && (int)$entry->sla === 0
                );
                if ($needsVerify):
              ?>
                <div class="pt-inline-verify alert alert-warning" style="margin:8px 0;">
                  <label class="d-block">
                    <input type="checkbox" name="verify_unbilled_<?= (int)$entry->id ?>" value="1" required>
                    Verify entry — <?= htmlspecialchars($entry->description ?: 'No description') ?>
                    (<?= number_format((float)$entry->time_spent, 2) ?>h)
                  </label>
                </div>
              <?php endif; ?>

            <?php else: ?>
              <div class="pt-entry-row">
                <div><?= htmlspecialchars($clientMap[$entry->client_id] ?? 'N/A') ?></div>
                <div><?= htmlspecialchars($departmentMap[$entry->department_id] ?? 'N/A') ?></div>
                <div><?= htmlspecialchars($taskMap[$entry->task_category_id] ?? 'N/A') ?></div>
                <div><?= htmlspecialchars($entry->ticket_id) ?></div>
                <div><?= htmlspecialchars($entry->description) ?></div>
                <div><?= htmlspecialchars($entry->start_time) ?></div>
                <div><?= htmlspecialchars($entry->end_time) ?></div>
                <div><?= number_format((float)$entry->time_spent, 2) ?> hrs</div>
                <div><?= $entry->billable ? 'Yes' : 'No' ?></div>
                <div><?= number_format((float)$entry->billable_time, 2) ?> hrs</div>
                <div><?= $entry->sla ? 'Yes' : 'No' ?></div>
                <div><?= number_format((float)$entry->sla_time, 2) ?> hrs</div>
                <div>
                  <a class="btn btn-sm btn-outline-primary"
                     href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id=<?= (int)$editAdminId ?>&date=<?= htmlspecialchars($editTimesheetDate) ?>&edit_id=<?= (int)$entry->id ?>">
                    Edit
                  </a>
                </div>
                <div></div>
              </div>

              <?php
                $needsVerify = (
                  isset($unbilledTimeValidateMin) && $unbilledTimeValidateMin !== '' && $unbilledTimeValidateMin !== null
                  && (float)$entry->time_spent >= (float)$unbilledTimeValidateMin
                  && (int)$entry->billable === 0
                  && (int)$entry->sla === 0
                );
                if ($needsVerify):
              ?>
                <div class="pt-inline-verify alert alert-warning" style="margin:8px 0;">
                  <label class="d-block">
                    <input type="checkbox" name="verify_unbilled_<?= (int)$entry->id ?>" value="1" required>
                    Verify entry — <?= htmlspecialchars($entry->description ?: 'No description') ?>
                    (<?= number_format((float)$entry->time_spent, 2) ?>h)
                  </label>
                </div>
              <?php endif; ?>
            <?php endif; ?>

          <?php endforeach; ?>

          <!-- Totals -->
          <div class="pt-totals">
            <div><strong>Total Time:</strong> <?= number_format($totalTime, 2) ?> hrs</div>
            <div><strong>Total Billable Time:</strong> <?= number_format($totalBillableTime, 2) ?> hrs</div>
            <div><strong>Total SLA Time:</strong> <?= number_format($totalSlaTime, 2) ?> hrs</div>
          </div>

          <!-- Approve / Reject -->
          <?php if ($canApprove && isset($timesheet)): ?>
            <form method="post"
                  id="approve-form"
                  class="pt-approve-form"
                  style="margin-top: 12px;"
                  action="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets">
              <?php if (!empty($tkCsrf)): ?>
                <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf) ?>">
              <?php endif; ?>
              <input type="hidden" name="approve_timesheet_id" value="<?= (int)$timesheet->id ?>">

              <div class="alert alert-info" style="margin-bottom: 10px;">
                Please ensure all flagged entries above are ticked “Verified” before approving.
              </div>

              <button type="submit" class="btn btn-success">Approve Timesheet</button>
            </form>

            <form method="post"
                  class="pt-reject-form"
                  style="margin-top: 12px;"
                  action="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets">
              <?php if (!empty($tkCsrf)): ?>
                <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf) ?>">
              <?php endif; ?>
              <input type="hidden" name="reject_timesheet_id" value="<?= (int)$timesheet->id ?>">
              <textarea name="admin_rejection_note" class="form-control" placeholder="Rejection Note (required)" style="max-width:420px; height:60px; margin:8px 0;"></textarea>
              <button type="submit" class="btn btn-danger">Reject Timesheet</button>
            </form>
          <?php endif; ?>

        <?php endif; ?>

        <!-- Resubmit (owner of rejected sheet) -->
        <?php if (isset($timesheet) && $timesheet->status === 'rejected' && (int)$editAdminId === (int)$_SESSION['adminid']): ?>
          <form method="post"
                class="pt-resubmit-form"
                style="margin-top: 16px;"
                action="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets">
            <?php if (!empty($tkCsrf)): ?>
              <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf) ?>">
            <?php endif; ?>
            <input type="hidden" name="resubmit_timesheet_id" value="<?= (int)$timesheet->id ?>">
            <button type="submit" class="btn btn-primary">Re-Submit</button>
          </form>
        <?php endif; ?>

      </div>
    </div>
  <?php endif; ?>

</div>
