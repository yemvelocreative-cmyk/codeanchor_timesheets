<?php
// File: templates/admin/components/settings_approvals.tpl
// Expects: $roles (Capsule Collection|array), $allowedRoles, $allowedApprovalRoles, $unbilledTimeValidateMin, $tkCsrf
if (!defined('WHMCS')) { die('Access Denied'); }

$allowedRoles            = (isset($allowedRoles) && is_array($allowedRoles)) ? $allowedRoles : [];
$allowedApprovalRoles    = (isset($allowedApprovalRoles) && is_array($allowedApprovalRoles)) ? $allowedApprovalRoles : [];
$unbilledTimeValidateMin = isset($unbilledTimeValidateMin) ? $unbilledTimeValidateMin : '';
$tkCsrf                  = isset($tkCsrf) ? (string)$tkCsrf : '';

if (isset($roles) && $roles instanceof \Illuminate\Support\Collection) {
    $roles = $roles->all();
}
$roles   = is_array($roles) ? $roles : [];
$noRoles = empty($roles);
?>

<h4>Assign Admin Roles that can view all Pending &amp; Approved Timesheets</h4>
<p class="text-muted mb-3">
    Use the boxes below to assign or unassign <strong>Admin Roles</strong> with permission to view all pending and approved timesheets.<br>
    ➤ Only roles in the <strong>“Assigned Admin Roles”</strong> box will be saved.<br>
    ➤ To select multiple admin roles, hold down <kbd>Ctrl</kbd> (or <kbd>Cmd</kbd> on Mac) while clicking.
</p>

<?php if ($noRoles): ?>
    <div class="alert alert-warning tk-alert-narrow" role="alert">
        No admin roles detected. You can still save, but please verify roles exist under WHMCS &raquo; Setup &raquo; Admin Roles.
    </div>
<?php endif; ?>

<form method="post" data-tk>
    <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="tk_action" value="save_view_permissions">

    <div class="dual-select-controls-wrapper">
        <!-- Active Admin Roles -->
        <div class="user-select-block">
            <label for="availableRoles" class="user-select-label">Active Admin Roles</label>
            <select multiple class="form-control user-select-box" id="availableRoles" size="8" aria-label="Available roles">
                <?php foreach ($roles as $role): ?>
                    <?php
                    $rid   = (int)($role->id ?? $role['id'] ?? 0);
                    $rname = (string)($role->name ?? $role['name'] ?? '');
                    if (!in_array($rid, $allowedRoles, true)):
                    ?>
                        <option value="<?= $rid ?>"><?= htmlspecialchars($rname, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Button Controls -->
        <div class="dual-select-controls" aria-label="Move selected roles">
            <button type="button" id="addRole" class="btn btn-secondary" aria-label="Add selected role(s)">➡️</button>
            <button type="button" id="removeRole" class="btn btn-secondary" aria-label="Remove selected role(s)">⬅️</button>
        </div>

        <!-- Assigned Admin Roles -->
        <div class="user-select-block">
            <label for="assignedRoles" class="user-select-label">Assigned Admin Roles</label>
            <select multiple class="form-control user-select-box" name="pending_timesheets_roles[]" id="assignedRoles" size="8" aria-label="Assigned roles">
                <?php foreach ($roles as $role): ?>
                    <?php
                    $rid   = (int)($role->id ?? $role['id'] ?? 0);
                    $rname = (string)($role->name ?? $role['name'] ?? '');
                    if (in_array($rid, $allowedRoles, true)):
                    ?>
                        <option value="<?= $rid ?>"><?= htmlspecialchars($rname, ENT_QUOTES, 'UTF-8') ?></option>
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

<h4>Assign Admin Roles that can approve / unapprove Timesheets</h4>
<p class="text-muted mb-3">
    Use the boxes below to assign or unassign <strong>Admin Roles</strong> that can approve and unapprove pending timesheets.<br>
    ➤ Only roles in the <strong>“Assigned Admin Roles”</strong> box will be saved.
    ➤ To select multiple admin roles, hold down <kbd>Ctrl</kbd> (or <kbd>Cmd</kbd> on Mac) while clicking.
</p>

<form method="post" data-tk>
    <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="tk_action" value="save_approval_permissions">

    <div class="dual-select-controls-wrapper">
        <!-- Active Admin Roles -->
        <div class="user-select-block">
            <label for="availableRolesApprove" class="user-select-label">Active Admin Roles</label>
            <select multiple class="form-control user-select-box" id="availableRolesApprove" size="8" aria-label="Available roles for approval">
                <?php foreach ($roles as $role): ?>
                    <?php
                    $rid   = (int)($role->id ?? $role['id'] ?? 0);
                    $rname = (string)($role->name ?? $role['name'] ?? '');
                    if (!in_array($rid, $allowedApprovalRoles, true)):
                    ?>
                        <option value="<?= $rid ?>"><?= htmlspecialchars($rname, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Button Controls -->
        <div class="dual-select-controls" aria-label="Move selected roles for approval">
            <button type="button" id="addRoleApprove" class="btn btn-secondary" aria-label="Add selected role(s)">➡️</button>
            <button type="button" id="removeRoleApprove" class="btn btn-secondary" aria-label="Remove selected role(s)">⬅️</button>
        </div>

        <!-- Assigned Admin Roles -->
        <div class="user-select-block">
            <label for="assignedRolesApprove" class="user-select-label">Assigned Admin Roles</label>
            <select multiple class="form-control user-select-box" name="pending_timesheets_approval_roles[]" id="assignedRolesApprove" size="8" aria-label="Assigned roles for approval">
                <?php foreach ($roles as $role): ?>
                    <?php
                    $rid   = (int)($role->id ?? $role['id'] ?? 0);
                    $rname = (string)($role->name ?? $role['name'] ?? '');
                    if (in_array($rid, $allowedApprovalRoles, true)):
                    ?>
                        <option value="<?= $rid ?>"><?= htmlspecialchars($rname, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mt-3">
        <h4>Validate Minimum Task Time</h4>
        <small id="unbilledHelp" class="text-muted">Set the minimum hours for tasks marked as Not Billable. If the time entered meets or exceeds this value, the approver must confirm whether the task should be Billable or assigned to SLA (e.g., 0.5 = 30 minutes).</small>
        <div class="d-flex align-items-center gap-2 tk-validate-row">
            <input
                type="number"
                step="0.1"
                min="0"
                name="unbilled_time_validate_min"
                id="unbilled_time_validate_min"
                class="form-control d-inline-block tk-input-w-80"
                value="<?= htmlspecialchars((string)$unbilledTimeValidateMin, ENT_QUOTES, 'UTF-8') ?>"
                aria-describedby="unbilledHelp"
            >            
        </div>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save Approval Permissions</button>
    </div>
</form>
