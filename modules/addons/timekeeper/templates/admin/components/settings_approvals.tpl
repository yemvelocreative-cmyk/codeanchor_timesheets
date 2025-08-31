<?php
// File: templates/admin/components/settings_approvals.tpl (Option 3: Accordion Sections)
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
      <!-- Section 1: View All -->
      <details class="tk-accordion" open data-scope="viewall">
        <summary class="tk-accordion__head">
          <span class="tk-approvals-title">Roles that can View All Timesheets</span>
          <span class="tk-accordion__tools">
            <label class="tk-approvals-toggleall">
              <input type="checkbox" class="js-approvals-viewall-toggleall">
              <span>Select all</span>
            </label>
            <span class="tk-approvals-count js-approvals-viewall-count">Selected: 0</span>
          </span>
        </summary>

        <div class="tk-accordion__body">
          <div class="tk-approvals-rolegrid">
            <?php foreach ($roles as $r):
              $rid   = (int)($r->id ?? $r['id'] ?? 0);
              $rname = (string)($r->name ?? $r['name'] ?? '');
              $isSel = in_array($rid, $allowedRoles, true);
            ?>
              <label class="tk-approvals-chip">
                <input type="checkbox"
                       class="js-approvals-viewall"
                       name="pending_timesheets_roles[]"
                       value="<?= $rid ?>"
                       <?= $isSel ? 'checked' : '' ?>>
                <span><?= $h($rname) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </details>

      <!-- Section 2: Approve / Unapprove -->
      <details class="tk-accordion" data-scope="approve">
        <summary class="tk-accordion__head">
          <span class="tk-approvals-title">Roles that can Approve / Unapprove</span>
          <span class="tk-accordion__tools">
            <label class="tk-approvals-toggleall">
              <input type="checkbox" class="js-approvals-approve-toggleall">
              <span>Select all</span>
            </label>
            <span class="tk-approvals-count js-approvals-approve-count">Selected: 0</span>
          </span>
        </summary>

        <div class="tk-accordion__body">
          <div class="tk-approvals-rolegrid">
            <?php foreach ($roles as $r):
              $rid   = (int)($r->id ?? $r['id'] ?? 0);
              $rname = (string)($r->name ?? $r['name'] ?? '');
              $isSel = in_array($rid, $allowedApprovalRoles, true);
            ?>
              <label class="tk-approvals-chip">
                <input type="checkbox"
                       class="js-approvals-approve"
                       name="pending_timesheets_approval_roles[]"
                       value="<?= $rid ?>"
                       <?= $isSel ? 'checked' : '' ?>>
                <span><?= $h($rname) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </details>

      <!-- Section 3: Display Settings -->
      <details class="tk-accordion">
        <summary class="tk-accordion__head">
          <span class="tk-approvals-title">Display Settings</span>
        </summary>

        <div class="tk-accordion__body">
          <div class="tk-acc-grid">
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
        </div>
      </details>

      <div class="tk-actions">
        <button type="submit" class="btn btn-primary">Save Timesheet Settings</button>
      </div>
    </div>
  </form>
</div>
