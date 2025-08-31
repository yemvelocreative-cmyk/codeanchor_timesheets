<?php
// File: templates/admin/components/settings_approvals.tpl (Option 2: Comparison Matrix)
if (!defined('WHMCS')) { die('Access Denied'); }

$allowedRoles            = (isset($allowedRoles) && is_array($allowedRoles)) ? $allowedRoles : [];
$allowedApprovalRoles    = (isset($allowedApprovalRoles) && is_array($allowedApprovalRoles)) ? $allowedApprovalRoles : [];
$unbilledTimeValidateMin = isset($unbilledTimeValidateMin) ? $unbilledTimeValidateMin : '';
$paginationValue         = isset($paginationValue) ? $paginationValue : '';
$tkCsrf                  = isset($tkCsrf) ? (string)$tkCsrf : '';

if (isset($roles) && $roles instanceof \Illuminate\Support\Collection) { $roles = $roles->all(); }
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

  <form method="post" data-tk class="tk-approvals-card-form">
    <input type="hidden" name="tk_csrf" value="<?= $h($tkCsrf) ?>">
    <input type="hidden" name="tk_action" value="save_approvals">

    <div class="tk-card tk-card--padded">
      <!-- Matrix -->
      <div class="tk-matrix">
        <!-- Header row -->
        <div class="tk-mx-row tk-mx-header">
          <div class="tk-mx-cell tk-mx-rolecol">
            <span class="tk-h3">Role</span>
          </div>

          <div class="tk-mx-cell">
            <div class="tk-mx-head">
              <span>View All Timesheets</span>
              <label class="tk-mx-toggleall">
                <input type="checkbox" class="js-approvals-viewall-toggleall">
                <span>All</span>
              </label>
              <span class="tk-mx-count js-approvals-viewall-count">Selected: 0</span>
            </div>
          </div>

          <div class="tk-mx-cell">
            <div class="tk-mx-head">
              <span>Approve / Unapprove</span>
              <label class="tk-mx-toggleall">
                <input type="checkbox" class="js-approvals-approve-toggleall">
                <span>All</span>
              </label>
              <span class="tk-mx-count js-approvals-approve-count">Selected: 0</span>
            </div>
          </div>
        </div>

        <!-- Data rows -->
        <?php foreach ($roles as $r): $rid = (int)($r->id ?? $r['id'] ?? 0); $rname = (string)($r->name ?? $r['name'] ?? ''); ?>
          <div class="tk-mx-row">
            <div class="tk-mx-cell tk-mx-rolecol">
              <span class="tk-role-name"><?= $h($rname) ?></span>
            </div>

            <div class="tk-mx-cell tk-mx-toggle">
              <input type="checkbox"
                     class="js-approvals-viewall"
                     name="pending_timesheets_roles[]"
                     value="<?= $rid ?>"
                     <?= in_array($rid, $allowedRoles, true) ? 'checked' : '' ?>
                     aria-label="View All for role <?= $h($rname) ?>">
            </div>

            <div class="tk-mx-cell tk-mx-toggle">
              <input type="checkbox"
                     class="js-approvals-approve"
                     name="pending_timesheets_approval_roles[]"
                     value="<?= $rid ?>"
                     <?= in_array($rid, $allowedApprovalRoles, true) ? 'checked' : '' ?>
                     aria-label="Approve/Unapprove for role <?= $h($rname) ?>">
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Footer bar: Display & Validation controls -->
      <div class="tk-approvals-footer">
        <div class="tk-approvals-footer__fields">
          <div class="tk-field">
            <label for="pagination_value" class="form-label">Pagination</label>
            <small id="paginationHelp" class="text-muted d-block">
              Set the number of Pending/Approved Timesheets to list per page. Leave blank to use the system default.
            </small>
            <input
              type="number"
              step="1"
              min="1"
              name="pagination_value"
              id="pagination_value"
              class="form-control tk-input-w-80"
              value="<?= $h($paginationValue) ?>"
              aria-describedby="paginationHelp"
              inputmode="numeric"
              pattern="[0-9]*">
          </div>

          <div class="tk-field">
            <label for="unbilled_time_validate_min" class="form-label">Minimum Task Time (hrs)</label>
            <small id="unbilledHelp" class="text-muted d-block">
              Threshold for tasks marked Not Billable. If time meets/exceeds this, approver must confirm Billable or SLA (e.g., 0.5 = 30 min).
            </small>
            <input
              type="number"
              step="0.1"
              min="0"
              name="unbilled_time_validate_min"
              id="unbilled_time_validate_min"
              class="form-control tk-input-w-80"
              value="<?= $h((string)$unbilledTimeValidateMin) ?>"
              aria-describedby="unbilledHelp">
          </div>
        </div>

        <div class="tk-actions">
          <button type="submit" class="btn btn-primary">Save Timesheet Settings</button>
        </div>
      </div>
    </div>
  </form>
</div>
