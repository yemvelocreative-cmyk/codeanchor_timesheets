<?php
// expects: $currentCronDays, $allAdmins, $cronUsers, $tkCsrf
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>
<form method="post" data-tk action="">
  <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf, ENT_QUOTES, 'UTF-8'); ?>">

  <div class="timekeeper-root tk-page">
    <!-- Page Header -->
    <div class="tk-page-header">
      <div class="tk-page-title">
        <h2 class="tk-h2">Cron: Daily Timesheet Setup</h2>
        <p class="tk-subtitle">Choose workdays and assign which admin users should get an automatic timesheet each day.</p>
      </div>
    </div>

    <!-- Dual Cards -->
    <div class="tk-block-grid-2 tk-gap-16 tk-stack-sm">
      <!-- Card 1: Cron Days -->
      <div class="tk-card">
        <div class="tk-card-header">
          <h3 class="tk-card-title">Cron Schedule</h3>
          <p class="tk-card-subtitle">Pick the workdays to auto-create timesheets.</p>
        </div>
        <div class="tk-card-body">
          <div class="mb-3">
            <label class="d-block mb-1"><strong>Cron Command</strong></label>
            <p class="text-muted mb-1">Run this daily on your server (adjust the path for your install):</p>
            <code class="tk-code">php -q /path-to-your-whmcs/modules/addons/timekeeper/cron/cron.php</code>
          </div>

          <div class="mb-2">
            <label class="d-block mb-1"><strong>Workdays</strong></label>
            <p class="text-muted">Select the days you want timesheets created for:</p>
            <div class="tk-checkbox-grid">
              <?php foreach ($daysOfWeek as $day): ?>
                <label class="tk-checkbox">
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
        </div>
      </div>

      <!-- Card 2: Assigned Users -->
      <div class="tk-card">
        <div class="tk-card-header">
          <h3 class="tk-card-title">Assign Users</h3>
          <p class="tk-card-subtitle">Only users in the “Assigned Users” box will be saved.</p>
        </div>
        <div class="tk-card-body">
          <p class="text-muted small mb-2">
            Use the controls to move users between lists. Hold <kbd>Ctrl</kbd>/<kbd>Cmd</kbd> to multi‑select.
          </p>

          <div class="dual-select within-card">
            <!-- Available -->
            <div class="user-select-block">
              <label for="availableUsers" class="user-select-label">Active Users</label>
              <select multiple class="form-control user-select-box" id="availableUsers" aria-label="Active Users">
                <?php foreach ($allAdmins as $admin): ?>
                  <?php if (!in_array($admin->id, $cronUsers, true)): ?>
                    <option value="<?= (int)$admin->id ?>">
                      <?= htmlspecialchars($admin->firstname . ' ' . $admin->lastname, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Controls -->
            <div class="dual-select-controls" aria-hidden="false">
              <button type="button" id="addUser" class="btn btn-secondary" aria-label="Add selected user(s)">➡️</button>
              <button type="button" id="removeUser" class="btn btn-secondary" aria-label="Remove selected user(s)">⬅️</button>
            </div>

            <!-- Assigned -->
            <div class="user-select-block">
              <label for="assignedUsers" class="user-select-label">Assigned Users</label>
              <select multiple class="form-control user-select-box" name="cron_users[]" id="assignedUsers" aria-label="Assigned Users">
                <?php foreach ($allAdmins as $admin): ?>
                  <?php if (in_array($admin->id, $cronUsers, true)): ?>
                    <option value="<?= (int)$admin->id ?>">
                      <?= htmlspecialchars($admin->firstname . ' ' . $admin->lastname, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Single Submit -->
    <div class="tk-actions mt-3">
      <button type="submit" id="saveSettingsButton" class="btn btn-primary">Save Settings</button>
    </div>
  </div>
</form>
