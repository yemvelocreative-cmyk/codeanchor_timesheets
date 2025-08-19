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

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div class="timekeeper-approvals-settings">
  <?php if ($noRoles): ?>
    <div class="alert alert-warning tk-alert-narrow" role="alert">
      No admin roles detected. Please verify roles exist under <strong>Setup → Admin Roles</strong>.
    </div>
  <?php endif; ?>

  <form method="post" data-tk>
    <input type="hidden" name="tk_csrf" value="<?= $h($tkCsrf) ?>">

    <!-- =========================
         Comparison Matrix
         ========================= -->
    <div class="tk-matrix">
      <!-- Header -->
      <div class="tk-mx-row tk-mx-header">
        <div class="tk-mx-cell tk-mx-rolecol">Role</div>

        <div class="tk-mx-cell">
          <div class="tk-mx-head">
            <span class="tk-mx-head-label">View All</span>
            <label class="tk-mx-toggleall">
              <input type="checkbox" class="js-mx-view-toggle-all">
              <span>All</span>
            </label>
            <span class="tk-mx-count js-mx-view-count">Selected: 0</span>
          </div>
        </div>

        <div class="tk-mx-cell">
          <div class="tk-mx-head">
            <span class="tk-mx-head-label">Approve / Unapprove</span>
            <label class="tk-mx-toggleall">
              <input type="checkbox" class="js-mx-approve-toggle-all">
              <span>All</span>
            </label>
            <span class="tk-mx-count js-mx-approve-count">Selected: 0</span>
          </div>
        </div>
      </div>

      <!-- Rows -->
      <?php foreach ($roles as $r): ?>
        <?php
          $rid   = (int)($r->id ?? $r['id'] ?? 0);
          $rname = (string)($r->name ?? $r['name'] ?? '');
          $canViewAll = in_array($rid, $allowedRoles, true);
          $canApprove = in_array($rid, $allowedApprovalRoles, true);
        ?>
        <div class="tk-mx-row">
          <div class="tk-mx-cell tk-mx-rolecol">
            <span class="tk-role-name"><?= $h($rname) ?></span>
          </div>

          <div class="tk-mx-cell">
            <label class="tk-mx-toggle">
              <input type="checkbox"
                     name="pending_timesheets_roles[]"
                     value="<?= $rid ?>"
                     <?= $canViewAll ? 'checked' : '' ?>
                     class="js-mx-view">
              <span class="tk-mx-toggle-fx" aria-hidden="true"></span>
            </label>
          </div>

          <div class="tk-mx-cell">
            <label class="tk-mx-toggle">
              <input type="checkbox"
                     name="pending_timesheets_approval_roles[]"
                     value="<?= $rid ?>"
                     <?= $canApprove ? 'checked' : '' ?>
                     class="js-mx-approve">
              <span class="tk-mx-toggle-fx" aria-hidden="true"></span>
            </label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Validate Minimum Task Time (applies to approval review) -->
    <div class="mt-3" style="padding: 0 1rem;">
      <h6 class="mb-1" style="font-weight:600;">Validate Minimum Task Time</h6>
      <small id="unbilledHelp" class="text-muted">
        Set the minimum hours for tasks marked as Not Billable. If the time entered meets
        or exceeds this value, the approver must confirm whether the task should be Billable
        or assigned to SLA (e.g., 0.5 = 30 minutes).
      </small>
      <div class="d-flex align-items-center gap-2 tk-validate-row" style="margin-top:.4rem; max-width:280px;">
        <input
          type="number"
          step="0.1"
          min="0"
          name="unbilled_time_validate_min"
          id="unbilled_time_validate_min"
          class="form-control d-inline-block tk-input-w-80"
          value="<?= $h((string)$unbilledTimeValidateMin) ?>"
          aria-describedby="unbilledHelp">
      </div>
    </div>

    <!-- Actions -->
    <div class="tk-actions" style="display:flex; gap:.5rem; justify-content:flex-end; padding: 1rem;">
      <button type="submit" class="btn btn-secondary" name="tk_action" value="save_view_permissions">
        Save View Permissions
      </button>
      <button type="submit" class="btn btn-primary" name="tk_action" value="save_approval_permissions">
        Save Approval Permissions
      </button>
    </div>
  </form>
</div>
