<?php
// expects: $currentCronDays, $allAdmins, $cronUsers, $tkCsrf
$daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
?>
<form method="post" data-tk action="">
  <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf, ENT_QUOTES, 'UTF-8'); ?>">

  <div class="timekeeper-settings-cron">
    <h4>Create a Daily Timesheet via Cron Function</h4>
    <p class="text-muted">
      Use the settings below to configure automatic daily timesheet creation for selected admin users.
    </p>

    <div class="tk-cron-block">
      <label class="tk-label"><strong>Cron URL:</strong></label>
      <p class="text-muted">Set up a daily cron job on your hosting server using the following path:</p>
      <code class="tk-code">php -q /path-to-your-whmcs/modules/addons/timekeeper/cron/cron.php</code><br>
      <small class="text-muted">(Replace <code>/path-to-your-whmcs/</code> with your actual WHMCS path.)</small>
    </div>

    <div class="tk-cron-block">
      <label class="tk-label"><strong>Cron Days</strong></label>
      <p class="text-muted">Please select the workdays you want timesheets to be automatically created for:</p>
      <div class="tk-days">
        <?php foreach ($daysOfWeek as $day): ?>
          <label class="tk-day">
            <input
              type="checkbox"
              name="cron_days[]"
              value="<?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>"
              <?= ($currentCronDays['cron_' . $day] ?? '') === 'active' ? 'checked' : '' ?>
            >
            <span><?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tk-cron-block">
      <label class="tk-label"><strong>Assigned Users</strong></label>
      <p class="text-muted">
        Use the boxes below to assign or unassign admin users for daily timesheet creation.<br>
        ➤ <strong>Only users in the “Assigned Users” box will be saved.</strong><br>
        ➤ <strong>To select multiple users, hold down <kbd>Ctrl</kbd> (or <kbd>Cmd</kbd> on Mac) while clicking.</strong>
      </p>

      <div class="dual-select" role="group" aria-label="Assign users to cron">
        <!-- Active Users -->
        <div class="dual-select__column">
          <label for="availableUsers" class="dual-select__label">Active Users</label>
          <select multiple class="dual-select__box" id="availableUsers" aria-describedby="availableHelp">
            <?php foreach ($allAdmins as $admin): ?>
              <?php if (!in_array($admin->id, $cronUsers, true)): ?>
                <option value="<?= (int)$admin->id ?>">
                  <?= htmlspecialchars($admin->firstname . ' ' . $admin->lastname, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <small id="availableHelp" class="text-muted">Select and use the arrows to move users.</small>
        </div>

        <!-- Button Controls (no inline JS; handled in settings_cron.js) -->
        <div class="dual-select__controls">
          <button type="button" id="addUser" class="btn btn-default" aria-label="Add selected user(s)" title="Add">&rarr;</button>
          <button type="button" id="removeUser" class="btn btn-default" aria-label="Remove selected user(s)" title="Remove">&larr;</button>
        </div>

        <!-- Assigned Users -->
        <div class="dual-select__column">
          <label for="assignedUsers" class="dual-select__label">Assigned Users</label>
          <select multiple class="dual-select__box" name="cron_users[]" id="assignedUsers" aria-describedby="assignedHelp">
            <?php foreach ($allAdmins as $admin): ?>
              <?php if (in_array($admin->id, $cronUsers, true)): ?>
                <option value="<?= (int)$admin->id ?>">
                  <?= htmlspecialchars($admin->firstname . ' ' . $admin->lastname, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <small id="assignedHelp" class="text-muted">These users will receive daily timesheets.</small>
        </div>
      </div>
    </div>
  </div>

  <div class="tk-actions">
    <button type="submit" id="saveSettingsButton" class="btn btn-primary">Save Settings</button>
  </div>
</form>
