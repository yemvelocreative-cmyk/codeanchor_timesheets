<?php
// expects: $roles (Capsule collection), $allowedRoles, $allowedApprovalRoles, $unbilledTimeValidateMin, $tkCsrf
if (!isset($allowedRoles) || !is_array($allowedRoles)) $allowedRoles = [];
if (!isset($allowedApprovalRoles) || !is_array($allowedApprovalRoles)) $allowedApprovalRoles = [];
?>
<h4>Assign Admin Roles that can view all Pending &amp; Approved Timesheets</h4>
<p class="text-muted" style="margin-bottom:16px;">
    Use the boxes below to assign or unassign <strong>Admin Roles</strong> with permission to view all pending and approved timesheets.<br>
    ➤ Only roles in the <strong>“Assigned Admin Roles”</strong> box will be saved.<br>
    ➤ To select multiple admin roles, hold down <kbd>Ctrl</kbd> (or <kbd>Cmd</kbd> on Mac) while clicking.
</p>

<form method="post" data-tk>
    <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="dual-select-controls-wrapper">
        <!-- Active Admin Roles -->
        <div class="user-select-block">
            <label for="availableRoles" class="user-select-label">Active Admin Roles</label>
            <select multiple class="form-control user-select-box" id="availableRoles">
                <?php foreach ($roles as $role): ?>
                    <?php if (!in_array((int)$role->id, $allowedRoles, true)): ?>
                        <option value="<?= (int)$role->id ?>"><?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Button Controls -->
        <div class="dual-select-controls">
            <button type="button" id="addRole" class="btn btn-secondary" aria-label="Add selected role(s)">➡️</button>
            <button type="button" id="removeRole" class="btn btn-secondary" aria-label="Remove selected role(s)">⬅️</button>
        </div>

        <!-- Assigned Admin Roles -->
        <div class="user-select-block">
            <label for="assignedRoles" class="user-select-label">Assigned Admin Roles</label>
            <select multiple class="form-control user-select-box" name="pending_timesheets_roles[]" id="assignedRoles">
                <?php foreach ($roles as $role): ?>
                    <?php if (in_array((int)$role->id, $allowedRoles, true)): ?>
                        <option value="<?= (int)$role->id ?>"><?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save View Permissions</button>
    </div>
</form>

<hr>

<h4>Assign Admin Roles that can approve / unapprove Pending Timesheets</h4>
<p class="text-muted" style="margin-bottom:16px;">
    Use the boxes below to assign or unassign <strong>Admin Roles</strong> that can approve and unapprove pending timesheets.<br>
    ➤ Only roles in the <strong>“Assigned Admin Roles”</strong> box will be saved.
</p>

<form method="post" data-tk>
    <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="dual-select-controls-wrapper">
        <!-- Active Admin Roles -->
        <div class="user-select-block">
            <label for="availableRolesApprove" class="user-select-label">Active Admin Roles</label>
            <select multiple class="form-control user-select-box" id="availableRolesApprove">
                <?php foreach ($roles as $role): ?>
                    <?php if (!in_array((int)$role->id, $allowedApprovalRoles, true)): ?>
                        <option value="<?= (int)$role->id ?>"><?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Button Controls -->
        <div class="dual-select-controls">
            <button type="button" id="addRoleApprove" class="btn btn-secondary" aria-label="Add selected role(s)">➡️</button>
            <button type="button" id="removeRoleApprove" class="btn btn-secondary" aria-label="Remove selected role(s)">⬅️</button>
        </div>

        <!-- Assigned Admin Roles -->
        <div class="user-select-block">
            <label for="assignedRolesApprove" class="user-select-label">Assigned Admin Roles</label>
            <select multiple class="form-control user-select-box" name="pending_timesheets_approval_roles[]" id="assignedRolesApprove">
                <?php foreach ($roles as $role): ?>
                    <?php if (in_array((int)$role->id, $allowedApprovalRoles, true)): ?>
                        <option value="<?= (int)$role->id ?>"><?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <h4 class="mt-3">Validate Time Spent</h4>
    <p class="text-muted" style="margin-bottom:16px;">
        <label for="unbilled_time_validate_min">
            Set the minimum time (in hours) to validate a task that is not marked as Billable or SLA:
            <input
                type="number"
                step="0.1"
                min="0"
                name="unbilled_time_validate_min"
                id="unbilled_time_validate_min"
                value="<?= htmlspecialchars((string)($unbilledTimeValidateMin ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                style="width:80px;"
            >
        </label>
        <small>Example: Enter 0.5 for 30 minutes.</small>
    </p>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save Approval Permissions</button>
    </div>
</form>
