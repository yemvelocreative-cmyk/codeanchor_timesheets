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

      <!-- Header row -->
      <div class="pt-row pt-row-head">
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
    <div class="tk-section-title">
        Add New Entry to Timesheet for <?= htmlspecialchars($editAdminName) ?> — <?= htmlspecialchars($editTimesheetDate) ?>
      </div>
    <div class="pt-detail">
    <!-- Intentionally no visible header: we’ll show section headings below -->
    <header aria-hidden="true"></header>
    <div class="body">

      <!-- Section: Add -->
      

        <!-- Rejection note (if applicable) -->
        <?php if (isset($timesheet) && $timesheet->status === 'rejected' && !empty($timesheet->admin_rejection_note)): ?>
          <div class="alert alert-danger pt-mb-16">
            <strong>Reason for rejection:</strong><br>
            <?= nl2br(htmlspecialchars($timesheet->admin_rejection_note)) ?>
            <?php if (!empty($timesheet->rejected_at) || !empty($timesheet->rejected_by)): ?>
              <br>
              <span class="pt-note">
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
        <div class="tk-row tk-card tk-row--table tk-row--header">
          <div class="tk-row-grid">
            <div class="hdr">Client</div>
            <div class="hdr">Department</div>
            <div class="hdr">Task Category</div>
            <div class="hdr">Description</div>
            <div class="hdr">Time</div>
            <div class="hdr">Flags</div>
            <div class="hdr">Actions</div>
          </div>
        </div>

        <div class="tk-row tk-card tk-row--table">
          <form method="post"
                id="pt-add-form"
                class="tk-row-grid tk-row-edit"
                action="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets">
            <?php if (!empty($tkCsrf)): ?>
              <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf) ?>">
            <?php endif; ?>
            <input type="hidden" name="add_new_entry" value="1">
            <input type="hidden" name="admin_id" value="<?= (int)$editAdminId ?>">
            <input type="hidden" name="timesheet_date" value="<?= htmlspecialchars($editTimesheetDate) ?>">

            <!-- Client -->
            <div class="cell cell-client">
              <select name="client_id" class="tk-row-select" required>
                <option value="">Select…</option>
                <?php foreach ($clientMap as $id => $label): ?>
                  <option value="<?= (int)$id ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Department -->
            <div class="cell cell-dept">
              <select name="department_id" id="pending-add-department" class="tk-row-select" required>
                <option value="">Select…</option>
                <?php foreach ($departmentMap as $id => $label): ?>
                  <option value="<?= (int)$id ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Task Category -->
            <div class="cell cell-cat">
              <select name="task_category_id" id="pending-add-task-category" class="tk-row-select" required>
                <option value="">Select…</option>
                <?php foreach ($taskCategories as $cat): ?>
                  <option value="<?= (int)$cat->id ?>" data-dept="<?= (int)$cat->department_id ?>">
                    <?= htmlspecialchars($cat->name) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Description -->
            <div class="cell cell-desc">
              <input type="text" name="description" class="tk-row-input" placeholder="">
            </div>

            <!-- Time -->
            <div class="cell cell-times tk-inline">
              <input type="time" name="start_time" required>
              <input type="time" name="end_time" required>
              <!-- Keep a visible readonly input so your JS shows the computed decimal -->
              <input type="text" name="time_spent" value="" readonly class="tk-inline-time-input" placeholder="0.00">
            </div>

            <!-- Flags -->
            <div class="cell cell-flags cell-flags--grid">
              <!-- Ticket -->
              <div class="flag-item">
                <label class="tk-flag-label">Ticket</label>
                <input type="text" name="ticket_id" class="tk-row-input" placeholder="Ticket #">
              </div>

              <!-- Billable -->
              <div class="flag-item">
                <label class="tk-flag-label">
                  <span class="checkbox-inline tk-inline-check">
                    <input type="checkbox" name="billable" value="1">
                    <span>Billable</span>
                  </span>
                </label>
                <input type="text" name="billable_time"
                      class="tk-inline-time-input col-hidden"
                      placeholder="0.00">
              </div>

              <!-- SLA -->
              <div class="flag-item">
                <label class="tk-flag-label">
                  <span class="checkbox-inline tk-inline-check">
                    <input type="checkbox" name="sla" value="1" id="sla-checkbox">
                    <span>SLA</span>
                  </span>
                </label>
                <input type="text" name="sla_time"
                      class="tk-inline-time-input col-hidden"
                      placeholder="0.00">
              </div>
            </div>

            <!-- Actions -->
            <div class="cell cell-actions">
              <button type="submit" class="btn btn-sm btn-success">Add</button>
            </div>
          </form>
        </div>


        <!-- Existing entries -->
            <!-- Section: View/Edit -->
        <div class="tk-section-title">View/Edit Timesheet Entries</div>
        <?php if (empty($editTimesheetEntries)): ?>
          <div class="alert alert-warning">No entries found for this timesheet.</div>
        <?php else: ?>
          <?php
            $totalTime = 0.0;
            $totalBillableTime = 0.0;
            $totalSlaTime = 0.0;
            foreach ($editTimesheetEntries as $entry) {
              $totalTime         += (float)$entry->time_spent;
              $totalBillableTime += (float)$entry->billable_time;
              $totalSlaTime      += (float)$entry->sla_time;
            }
          ?>

          <!-- Header row to match Timesheet module -->
          <div class="tk-row tk-card tk-row--table tk-row--header">
            <div class="tk-row-grid">
              <div class="hdr">Client</div>
              <div class="hdr">Department</div>
              <div class="hdr">Task Category</div>
              <div class="hdr">Description</div>
              <div class="hdr">Time</div>
              <div class="hdr">Flags</div>
              <div class="hdr">Actions</div>
            </div>
          </div>

          <div class="tk-saved-list">
            <?php foreach ($editTimesheetEntries as $entry): ?>
              <?php $isEditing = ((int)$editingEntryId === (int)$entry->id); ?>

              <div class="tk-row tk-card tk-row--table">
                <?php if ($isEditing): ?>
                  <!-- Inline EDIT row (Timesheet layout) -->
                  <form method="post"
                        class="tk-row-grid tk-row-edit"
                        action="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets">
                    <?php if (!empty($tkCsrf)): ?>
                      <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="save_id" value="<?= (int)$entry->id ?>">
                    <input type="hidden" name="admin_id" value="<?= (int)$editAdminId ?>">
                    <input type="hidden" name="timesheet_date" value="<?= htmlspecialchars($editTimesheetDate) ?>">
                    <!-- Hidden time_spent so JS can update it (bindTimeCalc) and backend receives it -->
                    <input type="hidden" name="time_spent" value="<?= number_format((float)$entry->time_spent, 2) ?>">

                    <!-- Client -->
                    <div class="cell cell-client">
                      <select name="client_id" class="tk-row-select">
                        <?php foreach ($clientMap as $id => $label): ?>
                          <option value="<?= (int)$id ?>" <?= ((int)$entry->client_id === (int)$id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <!-- Department -->
                    <div class="cell cell-dept">
                      <select name="department_id" class="tk-row-select pending-edit-department edit-department">
                        <?php foreach ($departmentMap as $id => $label): ?>
                          <option value="<?= (int)$id ?>" <?= ((int)$entry->department_id === (int)$id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <!-- Task Category -->
                    <div class="cell cell-cat">
                      <select name="task_category_id" class="tk-row-select pending-edit-task-category edit-task-category">
                        <?php foreach ($taskCategories as $cat): ?>
                          <option value="<?= (int)$cat->id ?>"
                                  data-dept="<?= (int)$cat->department_id ?>"
                                  <?= ((int)$entry->task_category_id === (int)$cat->id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat->name) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <!-- Description -->
                    <div class="cell cell-desc">
                      <input type="text" name="description" class="tk-row-input"
                            value="<?= htmlspecialchars($entry->description) ?>">
                    </div>

                    <!-- Time -->
                    <div class="cell cell-times tk-inline">
                      <input type="time" name="start_time" value="<?= htmlspecialchars($entry->start_time) ?>">
                      <input type="time" name="end_time"   value="<?= htmlspecialchars($entry->end_time) ?>">
                      <span class="sep">•</span>
                      <span><strong><?= number_format((float)$entry->time_spent, 2) ?></strong> hrs</span>
                    </div>

                    <!-- Flags mini-grid -->
                    <div class="cell cell-flags cell-flags--grid">
                      <!-- Ticket -->
                      <div class="flag-item">
                        <label class="tk-flag-label">Ticket</label>
                        <input type="text"
                              name="ticket_id"
                              class="tk-row-input tk-ticket-input"
                              placeholder="Ticket #"
                              value="<?= htmlspecialchars($entry->ticket_id) ?>">
                      </div>

                      <!-- Billable -->
                      <div class="flag-item">
                        <label class="tk-flag-label">
                          <span class="checkbox-inline tk-inline-check">
                            <input type="checkbox" name="billable" value="1" <?= $entry->billable ? 'checked' : '' ?>>
                            <span>Billable</span>
                          </span>
                        </label>
                        <input type="text" name="billable_time"
                              class="tk-inline-time-input <?= $entry->billable ? 'col-show' : 'col-hidden' ?>"
                              placeholder="0.00"
                              value="<?= number_format((float)$entry->billable_time, 2) ?>">
                      </div>

                      <!-- SLA -->
                      <div class="flag-item">
                        <label class="tk-flag-label">
                          <span class="checkbox-inline tk-inline-check">
                            <input type="checkbox" name="sla" value="1" <?= $entry->sla ? 'checked' : '' ?>>
                            <span>SLA</span>
                          </span>
                        </label>
                        <input type="text" name="sla_time"
                              class="tk-inline-time-input <?= $entry->sla ? 'col-show' : 'col-hidden' ?>"
                              placeholder="0.00"
                              value="<?= number_format((float)$entry->sla_time, 2) ?>">
                      </div>
                    </div>

                    <!-- Actions -->
                    <div class="cell cell-actions">
                      <button type="submit" class="btn btn-sm btn-success">Save</button>
                      <a href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id=<?= (int)$editAdminId ?>&date=<?= htmlspecialchars($editTimesheetDate) ?>"
                        class="btn btn-sm btn-default">Cancel</a>
                    </div>
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
                    <div class="ts-alert ts-alert-warning">
                      <label class="d-block">
                        <input type="checkbox" form="approve-form" name="verify_unbilled_<?= (int)$entry->id ?>" value="1" required>
                        Verify entry — <?= htmlspecialchars($entry->description ?: 'No description') ?>
                        (<?= number_format((float)$entry->time_spent, 2) ?>h)
                      </label>
                    </div>
                  <?php endif; ?>

                <?php else: ?>
                  <!-- READ-ONLY row (Timesheet layout) -->
                  <div class="tk-row-grid">
                    <div class="cell cell-client">
                      <strong><?= htmlspecialchars($clientMap[$entry->client_id] ?? 'N/A') ?></strong>
                    </div>
                    <div class="cell cell-dept"><?= htmlspecialchars($departmentMap[$entry->department_id] ?? 'N/A') ?></div>
                    <div class="cell cell-cat"><?= htmlspecialchars($taskMap[$entry->task_category_id] ?? 'N/A') ?></div>
                    <div class="cell cell-desc"><?= htmlspecialchars($entry->description) ?></div>

                    <div class="cell cell-times">
                      <span><?= htmlspecialchars($entry->start_time) ?>–<?= htmlspecialchars($entry->end_time) ?></span>
                      <span class="sep">•</span>
                      <span><strong><?= number_format((float)$entry->time_spent, 2) ?></strong> hrs</span>
                    </div>

                    <div class="cell cell-flags">
                      <div class="tk-badges">
                        <?php if (!empty($entry->ticket_id)): ?>
                          <span class="tk-badge tk-badge--success">Ticket <?= htmlspecialchars($entry->ticket_id) ?></span>
                        <?php else: ?>
                          <span class="tk-badge">No ticket</span>
                        <?php endif; ?>
                        <?php if ((float)$entry->billable_time > 0): ?>
                          <span class="tk-badge">Billable <?= number_format((float)$entry->billable_time, 2) ?>h</span>
                        <?php endif; ?>
                        <?php if ((float)$entry->sla_time > 0): ?>
                          <span class="tk-badge">SLA <?= number_format((float)$entry->sla_time, 2) ?>h</span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="cell cell-actions">
                      <a class="btn btn-sm btn-outline-primary"
                        href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id=<?= (int)$editAdminId ?>&date=<?= htmlspecialchars($editTimesheetDate) ?>&edit_id=<?= (int)$entry->id ?>">
                        Edit
                      </a>
                    </div>
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
                    <div class="ts-alert ts-alert-warning">
                      <label class="d-block">
                        <input type="checkbox" form="approve-form" name="verify_unbilled_<?= (int)$entry->id ?>" value="1" required>
                        Verify entry — <?= htmlspecialchars($entry->description ?: 'No description') ?>
                        (<?= number_format((float)$entry->time_spent, 2) ?>h)
                      </label>
                    </div>
                  <?php endif; ?>

                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Totals (you can keep your existing block or use the Timesheet totals bar) -->
          <div class="tk-totals-wrap">
            <div class="tk-totals-bar">
              <span class="lbl">Total</span> <strong class="val"><?= number_format($totalTime, 2) ?></strong><span class="unit">hrs</span>
              <span class="sep">•</span>
              <span class="lbl">Billable</span> <strong class="val"><?= number_format($totalBillableTime, 2) ?></strong><span class="unit">hrs</span>
              <span class="sep">•</span>
              <span class="lbl">SLA</span> <strong class="val"><?= number_format($totalSlaTime, 2) ?></strong><span class="unit">hrs</span>
            </div>
          </div>

          <!-- Approve / Reject (unchanged, below this block in your file) -->
        <?php endif; ?>

        <!-- Resubmit (owner of rejected sheet) -->
        <?php if (isset($timesheet) && $timesheet->status === 'rejected' && (int)$editAdminId === (int)$_SESSION['adminid']): ?>
          <form method="post"
                class="pt-resubmit-form pt-mt-12"
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
