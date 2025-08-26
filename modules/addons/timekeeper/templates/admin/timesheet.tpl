<?php
$clientMap = [];
foreach ($clients as $c) {
    $clientMap[$c->id] = $c->companyname ?: ($c->firstname . ' ' . $c->lastname);
}
$departmentMap = [];
foreach ($departments as $d) { $departmentMap[$d->id] = $d->name; }
$taskMap = [];
foreach ($taskCategories as $t) { $taskMap[$t->id] = $t->name; }
?>

<div class="timekeeper-fullwidth timesheet-root">
  <?= "<!-- Timesheet Status: " . htmlspecialchars($timesheetStatus, ENT_QUOTES, 'UTF-8') . " -->"; ?>

  <?php if ($timesheetStatus === 'not_assigned'): ?>
    <div class="ts-alert ts-alert-warning">
      <strong>Notice:</strong> No timesheet available for today. You may not be assigned, or today is not an active day. Please contact your administrator.
    </div>
  <?php endif; ?>

  <?php if ($timesheetStatus !== 'not_assigned'): ?>
    <?php
      $isEditing = isset($task) && isset($task->id);
      $actionUrl = 'addonmodules.php?module=timekeeper&timekeeperpage=timesheet' . ($isEditing ? '&edit_id='.(int)$task->id : '');
    ?>

    <form method="post" id="addTaskForm" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="tk-card ts-entryform">

      <!-- Compact header -->
      <div class="tk-card-header tk-card-header--compact">
        <div class="tk-meta-grid">
          <div><strong>Daily Timesheet</strong></div>
          <div><strong>You are logged in as:</strong> <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong>Timesheet Date:</strong> <?= htmlspecialchars($timesheetDate, ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong>Status:</strong> <?= htmlspecialchars(ucfirst($timesheetStatus), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>

      <!-- Two-column wrapper -->
      <div class="tk-block-grid-2">
        <!-- Block 1: Details -->
        <section class="tk-card">
          <div class="tk-section-title">1) Details</div>
          <div class="tk-grid">
            <div class="tk-field">
              <div class="tk-label">Client</div>
              <div class="tk-input">
                <select name="client_id" id="client_id" required>
                  <option value="">Select Client</option>
                  <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client->id ?>" <?= $isEditing && (int)($task->client_id ?? 0) === (int)$client->id ? 'selected' : '' ?>>
                      <?= htmlspecialchars($client->companyname ?: ($client->firstname . ' ' . $client->lastname), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="tk-field">
              <div class="tk-label">Department</div>
              <div class="tk-input">
                <select name="department_id" id="department_id" class="edit-department" required>
                  <option value="">Select Department</option>
                  <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int) $dept->id ?>" <?= $isEditing && (int)($task->department_id ?? 0) === (int)$dept->id ? 'selected' : '' ?>>
                      <?= htmlspecialchars($dept->name, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="tk-field">
              <div class="tk-label">Task Category</div>
              <div class="tk-input">
                <select name="task_category_id" id="task_category_id" class="edit-task-category" required>
                  <option value="">Select Task Category</option>
                  <?php foreach ($taskCategories as $taskCat): ?>
                    <option value="<?= (int) $taskCat->id ?>"
                            data-dept="<?= (int) $taskCat->department_id ?>"
                            <?= $isEditing && (int)($task->task_category_id ?? 0) === (int)$taskCat->id ? 'selected' : '' ?>>
                      <?= htmlspecialchars($taskCat->name, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="tk-field">
              <div class="tk-label">Description</div>
              <div class="tk-input">
                <input type="text" name="description" placeholder="Description" required
                       value="<?= $isEditing ? htmlspecialchars($task->description ?? '', ENT_QUOTES, 'UTF-8') : '' ?>">
              </div>
            </div>

            <!-- Support Ticket under Description -->
            <div class="tk-field">
              <div class="tk-label">Support Ticket</div>
              <div class="tk-input">
                <input type="text" name="ticket_id" placeholder="e.g. 12345"
                       value="<?= $isEditing ? htmlspecialchars($task->ticket_id ?? '', ENT_QUOTES, 'UTF-8') : '' ?>">
              </div>
            </div>
          </div>
        </section>

        <!-- Block 2: Billing -->
        <section class="tk-card">
          <div class="tk-section-title">2) Billing</div>
          <div class="tk-grid">
            <div class="tk-field">
              <div class="tk-label">Start / End Time</div>
              <div class="tk-input tk-inline tk-inline-times">
                <input type="time" name="start_time" required
                       class="tk-time tk-time-start"
                       value="<?= $isEditing ? htmlspecialchars($task->start_time ?? '', ENT_QUOTES, 'UTF-8') : '' ?>">
                <input type="time" name="end_time" required
                       class="tk-time tk-time-end"
                       value="<?= $isEditing ? htmlspecialchars($task->end_time ?? '', ENT_QUOTES, 'UTF-8') : '' ?>">
              </div>
            </div>

            <div class="tk-field">
              <div class="tk-label">Time Spent (hrs)</div>
              <div class="tk-input">
                <input type="text" name="time_spent" placeholder="0.00" readonly
                       class="align-right tk-half"
                       value="<?= $isEditing ? number_format((float)($task->time_spent ?? 0), 2) : '0.00' ?>">
              </div>
            </div>

            <div class="tk-field">
              <div class="tk-label">Billable</div>
              <div class="tk-input tk-inline tk-inline-nowrap">
                <label class="checkbox-inline tk-inline-check">
                  <input type="checkbox" name="billable" value="1" <?= $isEditing && !empty($task->billable) ? 'checked' : '' ?>>
                  <span>Yes</span>
                </label>
                <input type="text" name="billable_time" placeholder="Billable Time"
                       class="tk-inline-time-input <?= ($isEditing && !empty($task->billable)) ? 'col-show' : 'col-hidden' ?>"
                       value="<?= $isEditing ? number_format((float)($task->billable_time ?? 0), 2) : '' ?>">
              </div>
            </div>

            <div class="tk-field">
              <div class="tk-label">SLA</div>
              <div class="tk-input tk-inline tk-inline-nowrap">
                <label class="checkbox-inline tk-inline-check">
                  <input type="checkbox" name="sla" value="1" class="edit-sla"
                         <?= $isEditing && !empty($task->sla) ? 'checked' : '' ?>>
                  <span>Yes</span>
                </label>
                <input type="text" name="sla_time" placeholder="SLA Time"
                       class="tk-inline-time-input <?= ($isEditing && !empty($task->sla)) ? 'col-show' : 'col-hidden' ?>"
                       value="<?= $isEditing ? number_format((float)($task->sla_time ?? 0), 2) : '' ?>">
              </div>
            </div>
          </div>

          <!-- Actions under the right block -->
          <div class="tk-actions-right">
            <button type="submit" btn btn-primary"><?= $isEditing ? 'Save Changes' : 'Add' ?></button>
            <a href="addonmodules.php?module=timekeeper&timekeeperpage=timesheet" class="btn btn-default">Cancel</a>
          </div>
        </section>
      </div>

      <!-- Hidden fields required by your backend -->
      <input type="hidden" name="timesheet_id" value="<?= (int) $timesheetId ?>">
      <input type="hidden" name="admin_id" value="<?= (int) $adminId ?>">
    </form>

    <!-- =========================
         Saved Entries
         ========================= -->
    <?php if (!empty($existingTasks)): ?>
      <div id="existingTasks" class="ts-existing">
        <?php
          $tk_num = static function ($v): float {
            if ($v === null) return 0.0;
            if (is_numeric($v)) return (float)$v;
            $v = preg_replace('/[^0-9\.\-]/', '', (string)$v);
            $v = str_replace(',', '.', $v);
            return (float)$v;
          };
          $tk_get = static function ($row, array $keys) use ($tk_num): float {
            foreach ($keys as $k) {
              if (is_object($row) && isset($row->$k))   return $tk_num($row->$k);
              if (is_array($row)  && isset($row[$k]))   return $tk_num($row[$k]);
            }
            return 0.0;
          };
          $tk_total_time = 0.0; $tk_total_billable = 0.0; $tk_total_sla = 0.0;
          foreach ($existingTasks as $row) {
            $tk_total_time     += $tk_get($row, ['time_spent', 'time_spent_hours', 'timespent']);
            $tk_total_billable += $tk_get($row, ['billable_time', 'billable_hours', 'billable']);
            $tk_total_sla      += $tk_get($row, ['sla_time', 'sla_hours', 'sla']);
          }
        ?>

        <h4>Saved Entries</h4>
        <div class="tk-totals-wrap">
          <div class="tk-totals-bar" role="status" aria-label="Daily totals">
            <span class="lbl">Total</span>
            <strong class="val"><?= number_format($tk_total_time, 2) ?></strong><span class="unit">hrs</span>
            <span class="sep">•</span>
            <span class="lbl">Billable</span>
            <strong class="val"><?= number_format($tk_total_billable, 2) ?></strong><span class="unit">hrs</span>
            <span class="sep">•</span>
            <span class="lbl">SLA</span>
            <strong class="val"><?= number_format($tk_total_sla, 2) ?></strong><span class="unit">hrs</span>
          </div>
        </div>

        <div class="tk-saved-list">
          <?php foreach ($existingTasks as $task): ?>
            <?php $editing = isset($_GET['edit_id']) && (int) $_GET['edit_id'] === (int) $task->id; ?>

            <div class="tk-row tk-card">
              <?php if ($editing): ?>
                <!-- Inline edit -->
                <form method="post" class="tk-row-edit">
                  <input type="hidden" name="edit_id" value="<?= (int) $task->id ?>">

                  <div class="tk-row-main">
                    <div class="tk-row-left">
                      <div class="tk-row-title">
                        <select name="client_id" class="tk-row-select">
                          <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c->id ?>" <?= ((int)$task->client_id === (int)$c->id) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($c->companyname ?: ($c->firstname . ' ' . $c->lastname), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <span class="sep">·</span>
                        <select name="department_id" class="tk-row-select edit-department">
                          <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int) $dept->id ?>" <?= ((int)$task->department_id === (int)$dept->id) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($dept->name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <span class="sep">·</span>
                        <select name="task_category_id" class="tk-row-select edit-task-category">
                          <?php foreach ($taskCategories as $cat): ?>
                            <option value="<?= (int) $cat->id ?>" data-dept="<?= (int) $cat->department_id ?>" <?= ((int)$task->task_category_id === (int)$cat->id) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($cat->name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="tk-row-desc">
                        <input type="text" name="description" value="<?= htmlspecialchars($task->description, ENT_QUOTES, 'UTF-8') ?>" class="tk-row-input">
                      </div>

                      <div class="tk-row-meta">
                        <div class="tk-inline tk-inline-times">
                          <input type="time" name="start_time" value="<?= htmlspecialchars($task->start_time, ENT_QUOTES, 'UTF-8') ?>" class="tk-time">
                          <input type="time" name="end_time" value="<?= htmlspecialchars($task->end_time, ENT_QUOTES, 'UTF-8') ?>" class="tk-time">
                        </div>
                        <span class="dot">•</span>
                        <span><strong><?= number_format((float)$task->time_spent, 2) ?></strong> hrs</span>
                        <span class="dot">•</span>
                        <label class="checkbox-inline tk-inline-check">
                          <input type="checkbox" name="billable" value="1" <?= $task->billable ? 'checked' : '' ?>><span>Billable</span>
                        </label>
                        <input type="text" name="billable_time" placeholder="0.00"
                               class="tk-inline-time-input <?= $task->billable ? 'col-show' : 'col-hidden' ?>"
                               value="<?= number_format((float)$task->billable_time, 2) ?>">
                        <span class="dot">•</span>
                        <label class="checkbox-inline tk-inline-check">
                          <input type="checkbox" name="sla" value="1" <?= $task->sla ? 'checked' : '' ?>><span>SLA</span>
                        </label>
                        <input type="text" name="sla_time" placeholder="0.00"
                               class="tk-inline-time-input <?= $task->sla ? 'col-show' : 'col-hidden' ?>"
                               value="<?= number_format((float)$task->sla_time, 2) ?>">
                      </div>
                    </div>

                    <div class="tk-row-right">
                      <div class="tk-badges">
                        <?php if (!empty($task->ticket_id)): ?>
                          <span class="tk-badge tk-badge--success">Linked · Ticket #<?= htmlspecialchars($task->ticket_id, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                          <span class="tk-badge">No ticket</span>
                        <?php endif; ?>
                      </div>
                      <div class="tk-actions-right tk-row-actions">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <a href="addonmodules.php?module=timekeeper&timekeeperpage=timesheet" class="btn btn-default">Cancel</a>
                      </div>
                    </div>
                  </div>
                </form>
              <?php else: ?>
                <!-- Read-only row -->
                <div class="tk-row-main">
                  <div class="tk-row-left">
                    <div class="tk-row-title">
                      <strong><?= htmlspecialchars($clientMap[$task->client_id] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></strong>
                      <span class="sep">·</span>
                      <span class="muted"><?= htmlspecialchars($departmentMap[$task->department_id] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="sep">·</span>
                      <span class="muted"><?= htmlspecialchars($taskMap[$task->task_category_id] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="tk-row-desc">
                      <?= htmlspecialchars($task->description, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="tk-row-meta">
                      <span><?= htmlspecialchars($task->start_time, ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($task->end_time, ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="dot">•</span>
                      <span><strong><?= number_format((float)$task->time_spent, 2) ?></strong> hrs</span>
                      <?php if ((float)$task->billable_time > 0): ?>
                        <span class="dot">•</span><span>Billable <?= number_format((float)$task->billable_time, 2) ?> hrs</span>
                      <?php endif; ?>
                      <?php if ((float)$task->sla_time > 0): ?>
                        <span class="dot">•</span><span>SLA <?= number_format((float)$task->sla_time, 2) ?> hrs</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="tk-row-right">
                    <div class="tk-badges">
                      <?php if (!empty($task->ticket_id)): ?>
                        <span class="tk-badge tk-badge--success">Linked · Ticket #<?= htmlspecialchars($task->ticket_id, ENT_QUOTES, 'UTF-8') ?></span>
                      <?php else: ?>
                        <span class="tk-badge">No ticket</span>
                      <?php endif; ?>
                    </div>
                    <div class="tk-actions-right tk-row-actions">
                      <a href="addonmodules.php?module=timekeeper&timekeeperpage=timesheet&edit_id=<?= (int) $task->id ?>" class="btn btn-sm btn-default">Edit</a>
                      <form method="post" class="ts-delete-form" style="display:inline-block;margin:0;">
                        <input type="hidden" name="delete_id" value="<?= (int) $task->id ?>">
                        <button type="submit" class="btn btn-primary">Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?> <!-- timesheetStatus !== 'not_assigned' -->

  <!-- Select2 (kept via CDN for now) -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</div>
