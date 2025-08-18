<?php
if (!defined('WHMCS')) { die('Access Denied'); }

/**
 * EXPECTS (from controller):
 *   $timesheetStatus   string one of: 'ok','not_assigned','holiday','weekend','closed'
 *   $date              string (Y-m-d) current working date
 *   $clients           array of objects with ->id, ->companyname, ->firstname, ->lastname
 *   $departments       array of objects with ->id, ->name
 *   $taskCategories    array of objects with ->id, ->name
 *   $form              array with previously submitted values to sticky-fill fields
 * 
 * NOTES:
 * - Support Ticket field has been moved into Block 1 under the Description field.
 * - Former Block 2 has been removed.
 * - Former Block 3 label changed to “2) Billing”.
 */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$clientMap = [];
if (!empty($clients)) {
    foreach ($clients as $c) {
        $label = trim(($c->companyname ?: '')) ?: trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? ''));
        $clientMap[(string)$c->id] = $label !== '' ? $label : ('Client #' . (string)$c->id);
    }
}

$departmentMap = [];
if (!empty($departments)) {
    foreach ($departments as $d) { $departmentMap[(string)$d->id] = (string)$d->name; }
}

$taskMap = [];
if (!empty($taskCategories)) {
    foreach ($taskCategories as $t) { $taskMap[(string)$t->id] = (string)$t->name; }
}

// Sticky helpers
$fv = fn($key, $default='') => isset($form[$key]) ? (string)$form[$key] : (string)$default;
$selected = function ($a, $b) { return (string)$a === (string)$b ? ' selected' : ''; };
$checked  = function ($a, $truthy='1') { return (string)$a === (string)$truthy ? ' checked' : ''; };
?>

<div class="timekeeper-fullwidth timesheet-root">

  <?= "<!-- Timesheet Status: " . $h($timesheetStatus) . " -->"; ?>

  <?php if ($timesheetStatus === 'not_assigned'): ?>
    <div class="ts-alert ts-alert-warning">
      <strong>Notice:</strong> No timesheet available for today. You may not be assigned, or today is outside your schedule.
    </div>
  <?php elseif ($timesheetStatus === 'holiday'): ?>
    <div class="ts-alert ts-alert-info">
      <strong>Heads up:</strong> Today is marked as a holiday.
    </div>
  <?php elseif ($timesheetStatus === 'weekend'): ?>
    <div class="ts-alert ts-alert-info">
      <strong>Heads up:</strong> Weekend entry.
    </div>
  <?php elseif ($timesheetStatus === 'closed'): ?>
    <div class="ts-alert ts-alert-error">
      <strong>Locked:</strong> Timesheets are closed for this day.
    </div>
  <?php endif; ?>

  <form method="post" action="" class="ts-form" id="tk-timesheet-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $h($fv('csrf_token')) ?>">

    <!-- =========================================================
         1) Timesheet Details  (Support Ticket moved under Description)
         ========================================================= -->
    <section class="ts-card ts-section" id="tk-block-1">
      <div class="ts-section-header">
        <h2 class="ts-section-title">1) Timesheet Details</h2>
        <div class="ts-section-subtitle">Capture the work context and description.</div>
      </div>

      <div class="ts-grid">
        <div class="ts-field">
          <label for="tk-date" class="ts-label">Date</label>
          <input type="date" id="tk-date" name="date" class="ts-input" value="<?= $h($fv('date', $date ?? '')) ?>">
        </div>

        <div class="ts-field">
          <label for="tk-client" class="ts-label">Client</label>
          <select id="tk-client" name="client_id" class="ts-select">
            <option value="">Select a client…</option>
            <?php foreach ($clientMap as $id => $label): ?>
              <option value="<?= $h($id) ?>"<?= $selected($fv('client_id'), $id) ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="ts-field">
          <label for="tk-dept" class="ts-label">Department</label>
          <select id="tk-dept" name="department_id" class="ts-select">
            <option value="">Select a department…</option>
            <?php foreach ($departmentMap as $id => $label): ?>
              <option value="<?= $h($id) ?>"<?= $selected($fv('department_id'), $id) ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="ts-field">
          <label for="tk-task" class="ts-label">Task Category</label>
          <select id="tk-task" name="task_category_id" class="ts-select">
            <option value="">Select a task…</option>
            <?php foreach ($taskMap as $id => $label): ?>
              <option value="<?= $h($id) ?>"<?= $selected($fv('task_category_id'), $id) ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Description with Support Ticket moved here -->
      <div class="ts-field">
        <label for="tk-description" class="ts-label">Description</label>
        <textarea id="tk-description" name="description" class="ts-textarea" rows="6" placeholder="Describe the work done, scope, context, outcomes…"><?= $h($fv('description')) ?></textarea>
        <div class="ts-hint">
          If applicable, include the support ticket reference inline. Example:
          <code>Support Ticket: #<?= $h($fv('support_ticket') ?: '12345') ?></code>
        </div>

        <!-- Keep the input so backend continues receiving the value, but visually group it under description -->
        <div class="ts-inline-wrap" style="margin-top:.5rem;">
          <label for="tk-support-ticket" class="ts-label ts-label--inline">Support Ticket</label>
          <input type="text" id="tk-support-ticket" name="support_ticket" class="ts-input ts-input--sm"
                 placeholder="#12345" value="<?= $h($fv('support_ticket')) ?>">
        </div>
      </div>
    </section>

    <!-- ======================
         2) Billing (renamed)
         ====================== -->
    <section class="ts-card ts-section" id="tk-block-2">
      <div class="ts-section-header">
        <h2 class="ts-section-title">2) Billing</h2>
        <div class="ts-section-subtitle">Time and billing details.</div>
      </div>

      <div class="ts-grid">
        <div class="ts-field">
          <label for="tk-hours" class="ts-label">Hours</label>
          <input type="number" step="0.01" min="0" id="tk-hours" name="hours" class="ts-input" value="<?= $h($fv('hours','0')) ?>">
        </div>

        <div class="ts-field">
          <label for="tk-rate" class="ts-label">Rate</label>
          <input type="number" step="0.01" min="0" id="tk-rate" name="rate" class="ts-input" value="<?= $h($fv('rate','0')) ?>">
        </div>

        <div class="ts-field">
          <label for="tk-amount" class="ts-label">Amount</label>
          <input type="number" step="0.01" min="0" id="tk-amount" name="amount" class="ts-input" value="<?= $h($fv('amount','0')) ?>">
          <div class="ts-hint">Auto-calc may apply server-side if hours × rate is enabled.</div>
        </div>

        <div class="ts-field ts-field--checkbox">
          <label class="ts-checkbox">
            <input type="checkbox" name="billable" value="1"<?= $checked($fv('billable','1'), '1') ?>>
            <span>Billable</span>
          </label>
        </div>
      </div>
    </section>

    <!-- Actions -->
    <div class="ts-actions">
      <button type="submit" class="ts-btn ts-btn-primary">Save</button>
      <button type="reset" class="ts-btn ts-btn-secondary">Reset</button>
    </div>
  </form>
</div>
