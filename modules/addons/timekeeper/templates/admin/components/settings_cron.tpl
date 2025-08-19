<?php
if (!defined('WHMCS')) { die('Access Denied'); }
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<form method="post" data-tk action="">
  <input type="hidden" name="tk_csrf" value="<?= $h($tkCsrf) ?>">

  <div class="timekeeper-root tk-page">
    <div class="tk-block-grid-2 tk-gap-16 tk-stack-sm">
      <!-- Card 1 -->
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
                  <input type="checkbox" name="cron_days[]" value="<?= $h($day) ?>" <?= ($currentCronDays['cron_' . $day] ?? '') === 'active' ? 'checked' : '' ?>>
                  <span><?= $h($day) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 2 -->
      <div class="tk-card" data-scope="cron-users">
        <div class="tk-card-header">
          <h3 class="tk-card-title">Assign Users</h3>
          <p class="tk-card-subtitle">Only checked users will receive a daily timesheet.</p>
        </div>
        <div class="tk-card-body">
          <div class="tk-cronusers-actions">
            <label class="tk-cronusers-toggleall">
              <input type="checkbox" class="js-cronusers-toggleall">
              <span>Select all</span>
            </label>
            <span class="tk-cronusers-count js-cronusers-count">Selected: 0</span>
          </div>

          <div class="tk-usergrid">
            <?php foreach ($allAdmins as $admin):
              $aid = (int)$admin->id;
              $aname = trim(($admin->firstname ?? '') . ' ' . ($admin->lastname ?? ''));
              $checked = in_array($aid, $cronUsers ?? [], true);
            ?>
              <label class="tk-userchip">
                <input type="checkbox" name="cron_users[]" value="<?= $aid ?>" class="js-cronuser" <?= $checked ? 'checked' : '' ?>>
                <span><?= $h($aname ?: ('Admin #' . $aid)) ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <?php if (empty($allAdmins)): ?>
            <div class="alert alert-warning tk-alert-narrow">No admin users found.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="tk-actions tk-actions--cron">
      <button type="submit" id="saveSettingsButton" class="btn btn-primary">Save Settings</button>
    </div>
  </div>
</form>
