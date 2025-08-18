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

      <!-- Compact header replaces "Add Entry" -->
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
        <!-- Block 1: Details (Support Ticket under Description) -->
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
                <input type="text" name="time_spent" placeholder="0.00" readonly class="align-right"
                       value="<?= $isEditing ? number_format((float)($task->time_spent ?? 0), 2) : '0.00' ?>">
              </div>
            </div>

            <div class="tk-field">
              <div class="tk-label">Billable</div>
              <div class="tk-input tk-inline">
                <label class="checkbox-inline">
                  <input type="checkbox" name="billable" id="billable-checkbox" value="1" <?= $isEditing && !empty($task->billable) ? 'checked' : '' ?>>
                  Yes
                </label>
                <input type="text" name="billable_time" id="billable-time" placeholder="Billable Time"
                       style="<?= ($isEditing && !empty($task->billable)) ? 'display:inline-block;' : 'display:none;' ?>"
                       value="<?= $isEditing ? number_format((float)($task->billable_time ?? 0), 2) : '' ?>">
              </div>
            </div>

            <div class="tk-field">
              <div class="tk-label">SLA</div>
              <div class="tk-input tk-inline">
                <label class="checkbox-inline">
                  <input type="checkbox" name="sla" id="sla-checkbox" value="1" class="edit-sla"
                         <?= $isEditing && !empty($task->sla) ? 'checked' : '' ?>>
                  Yes
                </label>
                <input type="text" name="sla_time" id="sla-time" placeholder="SLA Time"
                       style="<?= ($isEditing && !empty($task->sla)) ? 'display:inline-block;' : 'display:none;' ?>"
                       value="<?= $isEditing ? number_format((float)($task->sla_time ?? 0), 2) : '' ?>">
              </div>
            </div>
          </div>

          <!-- Actions moved below the right block -->
          <div class="tk-actions-right">
            <button type="submit" class="btn btn-sm btn-primary"><?= $isEditing ? 'Save Changes' : 'Add' ?></button>
            <a href="addonmodules.php?module=timekeeper&timekeeperpage=timesheet" class="btn btn-sm btn-default">Cancel</a>
          </div>
        </section>
      </div>

      <!-- Hidden fields required by your backend -->
      <input type="hidden" name="timesheet_id" value="<?= (int) $timesheetId ?>">
      <input type="hidden" name="admin_id" value="<?= (int) $adminId ?>">
    </form>

    <!-- =========================
         Existing Tasks (unchanged)
         ========================= -->
    <?php if (!empty($existingTasks)): ?>
      <div id="existingTasks" class="ts-existing">
        <h4>Saved Entries</h4>

        <div class="ts-row ts-subheader">
          <div class="w-200">Client</div>
          <div class="w-180">Department</div>
          <div class="w-180">Task Category</div>
          <div class="w-90">Ticket ID</div>
          <div class="w-250">Description</div>
          <div class="w-90">Start</div>
          <div class="w-90">End</div>
          <div class="w-80 align-left">Time</div>
          <div class="w-50">Billable</div>
          <div class="w-90">Billable Time</div>
          <div class="w-50">SLA</div>
          <div class="w-90">SLA Time</div>
          <div class="w-70">&nbsp;</div>
          <div class="w-70">&nbsp;</div>
        </div>

        <?php foreach ($existingTasks as $task): ?>
          <?php $editing = isset($_GET['edit_id']) && (int) $_GET['edit_id'] === (int) $task->id; ?>

          <form method="post" class="ts-row ts-item">
            <input type="hidden" name="edit_id" value="<?= (int) $task->id ?>">

            <?php if ($editing): ?>
              <select name="client_id" class="w-200">
                <?php foreach ($clients as $c): ?>
                  <option value="<?= (int) $c->id ?>" <?= ((int)$task->client_id === (int)$c->id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c->companyname ?: ($c->firstname . ' ' . $c->lastname), ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <select name="department_id" class="w-180 edit-department">
                <?php foreach ($departments as $dept): ?>
                  <option value="<?= (int) $dept->id ?>" <?= ((int)$task->department_id === (int)$dept->id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dept->name, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <select name="task_category_id" class="w-180 edit-task-category">
                <?php foreach ($taskCategories as $cat): ?>
                  <option value="<?= (int) $cat->id ?>" data-dept="<?= (int) $cat->department_id ?>" <?= ((int)$task->task_category_id === (int)$cat->id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat->name, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <input type="text" name="ticket_id" value="<?= htmlspecialchars($task->ticket_id, ENT_QUOTES, 'UTF-8') ?>" class="w-90">
              <input type="text" name="description" value="<?= htmlspecialchars($task->description, ENT_QUOTES, 'UTF-8') ?>" class="w-250">
              <input type="time" name="start_time" value="<?= htmlspecialchars($task->start_time, ENT_QUOTES, 'UTF-8') ?>" class="w-90">
              <input type="time" name="end_time" value="<?= htmlspecialchars($task->end_time, ENT_QUOTES, 'UTF-8') ?>" class="w-90">
              <input type="text" name="time_spent" value="<?= number_format((float)$task->time_spent, 2) ?>" class="w-80 align-right" readonly>
              <input type="checkbox" name="billable" value="1" <?= $task->billable ? 'checked' : '' ?> class="w-50">
              <input type="text" name="billable_time" value="<?= number_format((float)$task->billable_time, 2) ?>" class="w-90">
              <input type="checkbox" name="sla" value="1" <?= $task->sla ? 'checked' : '' ?> class="w-50 edit-sla">
              <input type="text" name="sla_time" value="<?= number_format((float)$task->sla_time, 2) ?>" class="w-90">
              <button type="submit" class="btn btn-sm btn-success w-70">Save</button>
              <a href="addonmodules.php?module=timekeeper&timekeeperpage=timesheet" class="btn btn-sm btn-default w-70">Cancel</a>

            <?php else: ?>
              <div class="w-200"><?= htmlspecialchars($clientMap[$task->client_id] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
              <div class="w-180"><?= htmlspecialchars($departmentMap[$task->department_id] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
              <div class="w-180"><?= htmlspecialchars($taskMap[$task->task_category_id] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
              <div class="w-90"><?= htmlspecialchars($task->ticket_id, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="w-250"><?= htmlspecialchars($task->description, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="w-90"><?= htmlspecialchars($task->start_time, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="w-90"><?= htmlspecialchars($task->end_time, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="w-80"><?= number_format((float)$task->time_spent, 2) ?> hrs</div>
              <div class="w-50"><?= $task->billable ? 'Yes' : 'No' ?></div>
              <div class="w-90"><?= number_format((float)$task->billable_time, 2) ?> hrs</div>
              <div class="w-50"><?= $task->sla ? 'Yes' : 'No' ?></div>
              <div class="w-90"><?= number_format((float)$task->sla_time, 2) ?> hrs</div>
              <div class="w-70">
                <a href="addonmodules.php?module=timekeeper&timekeeperpage=timesheet&edit_id=<?= (int) $task->id ?>" class="btn btn-sm btn-default">Edit</a>
              </div>
              <div class="w-70">
                <form method="post" class="ts-delete-form">
                  <input type="hidden" name="delete_id" value="<?= (int) $task->id ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
              </div>
            <?php endif; ?>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?> <!-- timesheetStatus !== 'not_assigned' -->

  <!-- Layout & behaviour helpers -->
  <style>
    /* Force two-column for main blocks, stack on narrow screens */
    .tk-block-grid-2 { display: grid; grid-template-columns: 1fr; gap: 1rem; }
    @media (min-width: 900px) {
      .tk-block-grid-2 { grid-template-columns: 1fr 1fr; }
    }
    .tk-card { height: 100%; }

    /* Compact header layout */
    .tk-card-header--compact { padding: .5rem 1rem; }
    .tk-meta-grid { display: grid; grid-template-columns: 1fr; gap: .25rem; }
    @media (min-width: 900px) {
      .tk-meta-grid { grid-template-columns: auto auto auto auto; align-items: center; gap: 1rem; }
    }

    /* Start/End side-by-side; ~35% width each */
    .tk-inline-times { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .tk-inline-times .tk-time { flex: 0 1 35%; min-width: 140px; }

    /* Actions under right block */
    .tk-actions-right { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1rem; }

    /* Keep inline alignment clean */
    .tk-inline input[type="text"],
    .tk-inline input[type="time"] { vertical-align: middle; }
  </style>

  <script>
    (function () {
      function toggleInlineInput(checkbox, input) {
        if (!checkbox || !input) return;
        function set() { input.style.display = checkbox.checked ? 'inline-block' : 'none'; }
        checkbox.addEventListener('change', set);
        set();
      }
      toggleInlineInput(document.getElementById('billable-checkbox'), document.getElementById('billable-time'));
      toggleInlineInput(document.getElementById('sla-checkbox'), document.getElementById('sla-time'));
    })();
  </script>

  <!-- Select2 (kept via CDN for now) -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</div>
