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

  <div class="tk-approvals-grid">
    <!-- =========================
         Card A: View All
         ========================= -->
    <form method="post" data-tk class="tk-approvals-card-form">
      <input type="hidden" name="tk_csrf" value="<?= $h($tkCsrf) ?>">
      <input type="hidden" name="tk_action" value="save_view_permissions">

      <div class="tk-approvals-card" data-scope="viewall">
        <div class="tk-approvals-head">
          <h5 class="tk-approvals-title">Roles that can View All Timesheets</h5>
          <div class="tk-approvals-actions">
            <label class="tk-approvals-toggleall">
              <input type="checkbox" class="js-approvals-viewall-toggleall">
              <span>Select all</span>
            </label>
            <span class="tk-approvals-count js-approvals-viewall-count">Selected: 0</span>
          </div>
        </div>

        <div class="tk-approvals-body">
          <div class="tk-approvals-rolegrid">
            <?php foreach ($roles as $r):
              $rid   = (int)($r->id ?? $r['id'] ?? 0);
              $rname = (string)($r->name ?? $r['name'] ?? '');
              $isSel = in_array($rid, $allowedRoles, true);
            ?>
              <label class="tk-approvals-chip">
                <input
                  type="checkbox"
                  name="pending_timesheets_roles[]"
                  value="<?= $rid ?>"
                  <?= $isSel ? 'checked' : '' ?>
                  class="js-approvals-viewall">
                <span><?= $h($rname) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="mt-3" style="padding: 0 1rem 1rem 1rem;">
          <button type="submit" class="btn btn-primary">Save View Permissions</button>
        </div>
      </div>
    </form>

    <!-- =========================
         Card B: Approve / Unapprove
         ========================= -->
    <form method="post" data-tk class="tk-approvals-card-form">
      <input type="hidden" name="tk_csrf" value="<?= $h($tkCsrf) ?>">
      <input type="hidden" name="tk_action" value="save_approval_permissions">

      <div class="tk-approvals-card" data-scope="approve">
        <div class="tk-approvals-head">
          <h5 class="tk-approvals-title">Roles that can Approve / Unapprove</h5>
          <div class="tk-approvals-actions">
            <label class="tk-approvals-toggleall">
              <input type="checkbox" class="js-approvals-approve-toggleall">
              <span>Select all</span>
            </label>
            <span class="tk-approvals-count js-approvals-approve-count">Selected: 0</span>
          </div>
        </div>

        <div class="tk-approvals-body">
          <div class="tk-approvals-rolegrid">
            <?php foreach ($roles as $r):
              $rid   = (int)($r->id ?? $r['id'] ?? 0);
              $rname = (string)($r->name ?? $r['name'] ?? '');
              $isSel = in_array($rid, $allowedApprovalRoles, true);
            ?>
              <label class="tk-approvals-chip">
                <input
                  type="checkbox"
                  name="pending_timesheets_approval_roles[]"
                  value="<?= $rid ?>"
                  <?= $isSel ? 'checked' : '' ?>
                  class="js-approvals-approve">
                <span><?= $h($rname) ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <!-- Validate Minimum Task Time -->
          <div class="mt-3">
            <h6 class="mb-1" style="font-weight:600;">Validate Minimum Task Time</h6>
            <small id="unbilledHelp" class="text-muted">
              Set the minimum hours for tasks marked as Not Billable. If the time entered meets
              or exceeds this value, the approver must confirm whether the task should be Billable
              or assigned to SLA (e.g., 0.5 = 30 minutes).
            </small>
            <div class="d-flex align-items-center gap-2 tk-validate-row" style="margin-top:.4rem;">
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
        </div>

        <div class="mt-3" style="padding: 0 1rem 1rem 1rem;">
          <button type="submit" class="btn btn-primary">Save Approval Permissions</button>
        </div>
      </div>
    </form>
  </div>
</div>
