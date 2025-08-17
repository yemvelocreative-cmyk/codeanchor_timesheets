<?php
// expects: $currentCronDays, $allAdmins, $cronUsers, $tkCsrf
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>
<form method="post" data-tk action="">
    <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="timekeeper-settings-cron mt-4">
        <h4>Create a Daily Timesheet via Cron Function</h4>
        <p class="text-muted">
            Use the settings below to configure automatic daily timesheet creation for selected admin users.
        </p>

        <div class="mb-3">
            <label><strong>Cron URL:</strong></label>
            <p class="text-muted">Set up a daily cron job on your hosting server using the following path:</p>
            <code>php -q /path-to-your-whmcs/modules/addons/timekeeper/cron/cron.php</code><br>
            <small class="text-muted">(Replace <code>/path-to-your-whmcs/</code> with your actual WHMCS path.)</small>
        </div>

        <br>

        <div class="mb-4">
            <label><strong>Cron Days</strong></label>
            <p class="text-muted">Please select the workdays you want timesheets to be automatically created for:</p>
            <div class="form-group">
                <?php foreach ($daysOfWeek as $day): ?>
                    <label class="mr-3">
                        <input
                            type="checkbox"
                            name="cron_days[]"
                            value="<?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>"
                            <?= ($currentCronDays['cron_' . $day] ?? '') === 'active' ? 'checked' : '' ?>
                        >
                        <?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-4">
            <label><strong>Assigned Users</strong></label>
            <p class="text-muted">
                Use the boxes below to assign or unassign admin users for daily timesheet creation.<br>
                ➤ <strong>Only users in the “Assigned Users” box will be saved.</strong><br>
                ➤ <strong>To select multiple users, hold down <kbd>Ctrl</kbd> (or <kbd>Cmd</kbd> on Mac) while clicking.</strong>
            </p>

            <div class="dual-select-controls-wrapper">
                <!-- Active Users -->
                <div class="user-select-block">
                    <label for="availableUsers" class="user-select-label">Active Users</label>
                    <select multiple class="form-control user-select-box" id="availableUsers" style="width:200px;height:180px;">
                        <?php foreach ($allAdmins as $admin): ?>
                            <?php if (!in_array($admin->id, $cronUsers, true)): ?>
                                <option value="<?= (int)$admin->id ?>">
                                    <?= htmlspecialchars($admin->firstname . ' ' . $admin->lastname, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Button Controls -->
                <div class="dual-select-controls">
                    <!-- no inline JS; settings.js binds these -->
                    <button type="button" id="addUser" class="btn btn-secondary" aria-label="Add selected user(s)">➡️</button>
                    <button type="button" id="removeUser" class="btn btn-secondary" aria-label="Remove selected user(s)">⬅️</button>
                </div>

                <!-- Assigned Users -->
                <div class="user-select-block">
                    <label for="assignedUsers" class="user-select-label">Assigned Users</label>
                    <select multiple class="form-control" name="cron_users[]" id="assignedUsers" style="width:200px;height:180px;">
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

    <button type="submit" id="saveSettingsButton" class="btn btn-primary">Save Settings</button>
</form>
