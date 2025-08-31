<?php
// Option B: Role-centric cards (mirrors hide-tabs UX)
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

    <!-- Global toolbar (toggle-all + counts) -->
    <div class="tk-card tk-card--padded" style="margin-bottom:12px;">
      <div class="tk-role-head">
        <div class="tk-role-head-left">
          <h5 class="tk-role-title">Timesheet Permissions</h5>
          <span class="tk-role-sub">Apply permissions per role, or use Select All controls</span>
        </div>
        <div class="tk-role-head-right" style="gap:18px;">
          <div>
            <label class="tk-role-toggle-all">
              <input type="checkbox" class="js-approvals-viewall-toggleall">
              <span>View All — Select all</span>
            </label>
            <span class="tk-approvals-count js-approvals-viewall-count">Selected: 0</span>
          </div>
          <div>
            <label class="tk-role-toggle-all">
              <input type="checkbox" class="js-approvals-approve-toggleall">
              <span>Approve — Select all</span>
            </label>
            <span class="tk-approvals-count js-approvals-approve-count">Selected: 0</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Role cards -->
    <div class="tk-roles-stack">
      <?php foreach ($roles as $r): $rid = (int)($r->id ?? $r['id'] ?? 0); $rname = (string)($r->name ?? $r['name'] ?? ''); ?>
        <div class="tk-role-card" data-role-id="<?= $rid ?>">
          <div class="tk-role-head">
            <div class="tk-role-head-left">
              <h5 class="tk-role-title"><?= $h($rname) ?></h5>
              <span class="tk-role-sub">Grant timesheet permissions for this role</span>
            </div>
          </div>

          <div class="tk-tabs-list" role="group" aria-label="Timesheet permissions for <?= $h($rname) ?>">
            <label class="tk-tab-toggle">
              <span class="tk-tab-name">View All Timesheets</span>
              <input
                type="checkbox"
                class="js-approvals-viewall"
                name="pending_timesheets_roles[]"
                value="<?= $rid ?>"
                <?= in_array($rid, $allowedRoles, true) ? 'checked' : '' ?>>
            </label>

            <label class="tk-tab-toggle">
              <span class="tk-tab-name">Approve / Unapprove</span>
              <input
                type="checkbox"
                class="js-approvals-approve"
                name="pending_timesheets_approval_roles[]"
                value="<?= $rid ?>"
                <?= in_array($rid, $allowedApprovalRoles, true) ? 'checked' : '' ?>>
            </label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Display Settings -->
    <div class="tk-approvals-card" style="margin-top:12px;">
      <div class="tk-role-head">
        <div class="tk-role-head-left">
          <h5 class="tk-role-title">Display Settings</h5>
          <span class="tk-role-sub">Applies to Pending/Approved Timesheets listings</span>
        </div>
      </div>
      <div class="tk-approvals-body">
        <div class="tk-acc-grid">
          <div class="tk-field">
            <label for="pagination_value" class="form-label">Pagination</label>
            <small id="paginationHelp" class="text-muted d-block">
              Set the number of timesheets per page. Leave blank for system default.
            </small>
            <input
              type="number" step="1" min="1"
              name="pagination_value" id="pagination_value"
              class="form-control tk-input-w-80"
              value="<?= $h($paginationValue) ?>"
              aria-describedby="paginationHelp"
              inputmode="numeric" pattern="[0-9]*">
          </div>

          <div class="tk-field">
            <label for="unbilled_time_validate_min" class="form-label">Minimum Task Time (hrs)</label>
            <small id="unbilledHelp" class="text-muted d-block">
              For Not Billable tasks. If time meets/exceeds this, approver must confirm Billable or SLA (e.g., 0.5 = 30 min).
            </small>
            <input
              type="number" step="0.1" min="0"
              name="unbilled_time_validate_min" id="unbilled_time_validate_min"
              class="form-control tk-input-w-80"
              value="<?= $h((string)$unbilledTimeValidateMin) ?>"
              aria-describedby="unbilledHelp">
          </div>
        </div>
      </div>
    </div>

    <div class="tk-actions">
      <button type="submit" class="btn btn-primary">Save Timesheet Settings</button>
    </div>
  </form>
</div>
