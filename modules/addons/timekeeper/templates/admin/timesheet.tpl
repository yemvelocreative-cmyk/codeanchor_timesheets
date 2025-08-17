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

    <h2>Daily Timesheet</h2>

    <div class="ts-meta">
      <div><strong>You are logged in as:</strong> <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></div>
      <div><strong>Timesheet Date:</strong> <?= htmlspecialchars($timesheetDate, ENT_QUOTES, 'UTF-8') ?></div>
      <div><strong>Status:</strong> <?= htmlspecialchars(ucfirst($timesheetStatus), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <!-- Field Labels -->
    <div class="ts-row ts-header">
      <div class="w-200">Client</div>
      <div class="w-180">Department</div>
      <div class="w-180">Task Category</div>
      <div class="w-90">Ticket ID</div>
      <div class="w-250">Description</div>
      <div class="w-90">Start Time</div>
      <div class="w-90">End Time</div>
      <div class="w-80">Time Spent</div>
      <div class="w-50">Billable</div>
      <div class="w-90"><span id="billableTimeHeader" class="col-hidden">Time Billed</span></div>
      <div class="w-50">SLA</div>
      <div class="w-90"><span id="slaTimeHeader" class="col-hidden">SLA Time</span></div>
      <div class="w-70">&nbsp;</div>
    </div>

    <!-- Entry Row -->
    <div class="ts-scroll">
      <form method="post" id="addTaskForm" class="ts-row ts-entryform">
        <select name="client_id" id="client_id" required>
          <option value="">Select Client</option>
          <?php foreach ($clients as $client): ?>
            <option value="<?= (int) $client->id ?>">
              <?= htmlspecialchars($client->companyname ?: ($client->firstname . ' ' . $client->lastname), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="department_id" id="department_id" required>
          <option value="">Select Department</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?= (int) $dept->id ?>"><?= htmlspecialchars($dept->name, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>

        <select name="task_category_id" id="task_category_id" required>
          <option value="">Select Task Category</option>
          <?php foreach ($taskCategories as $task): ?>
            <option value="<?= (int) $task->id ?>" data-dept="<?= (int) $task->department_id ?>">
              <?= htmlspecialchars($task->name, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>

        <input type="text" name="ticket_id" placeholder="Ticket ID">
        <input type="text" name="description" placeholder="Description" required>
        <input type="time" name="start_time" required>
        <input type="time" name="end_time" required>
        <input type="text" name="time_spent" placeholder="0.00" readonly class="align-right">
        <input type="checkbox" name="billable">
        <input type="text" name="billable_time" placeholder="Billable Time" class="col-hidden">
        <div class="sla-group">
          <input type="checkbox" name="sla" id="sla-checkbox" value="1">
          <input type="text" name="sla_time" id="sla-time" placeholder="SLA Time" class="col-hidden">
          <div class="visually-hidden">
            <input type="hidden" name="timesheet_id" value="<?= (int) $timesheetId ?>">
            <input type="hidden" name="admin_id" value="<?= (int) $adminId ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-sm btn-primary w-70">Add</button>
      </form>
    </div>

    <!-- Existing Tasks -->
    <?php if (!empty($existingTasks)): ?>
      <div id="existingTasks" class="ts-existing">
        <h4>Saved Entries</h4>

        <!-- Header Row -->
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

        <!-- Entry Rows -->
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

    <!-- Totals from DB -->
    <div class="ts-totals">
      <div><strong>Total Time:</strong> <?= $totalTime ?> hrs</div>
      <div><strong>Total Billable Time:</strong> <?= $totalBillableTime ?> hrs</div>
      <div><strong>Total SLA Time:</strong> <?= $totalSlaTime ?> hrs</div>
    </div>

  <?php endif; ?> <!-- timesheetStatus !== 'not_assigned' -->

  <!-- Select2 (kept via CDN for now; we can vendor locally later if you prefer) -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</div>
