<?php
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('Access Denied'); }

// Base dir and helpers
$base = dirname(__DIR__); // /modules/addons/timekeeper
$try = function (string $relA, string $relB) use ($base) {
    $a = $base . $relA;
    $b = $base . $relB;
    if (is_file($a)) { require_once $a; return; }
    if (is_file($b)) { require_once $b; return; }
    throw new \RuntimeException("Missing helper: tried {$a} and {$b}");
};
$try('/helpers/core_helper.php', '/includes/helpers/core_helper.php');
$try('/helpers/dashboard_helper.php', '/includes/helpers/dashboard_helper.php');

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\DashboardHelper as DashH;

/**
 * timekeeper Dashboard (permissions-aware)
 * Housekeeping:
 *  - No inline CSS (progress widths set via data attributes + JS)
 *  - No inline scripts (logic moved to dashboard.js)
 *  - Dashboard-specific queries moved to DashboardHelper
 */

$today = date('Y-m-d');

// Inputs
$rawFrom = isset($_GET['from']) ? $_GET['from'] : null;
$rawTo   = isset($_GET['to'])   ? $_GET['to']   : null;

$from = (CoreH::isValidDate($rawFrom) ? $rawFrom : date('Y-m-01'));
$to   = (CoreH::isValidDate($rawTo)   ? $rawTo   : date('Y-m-d'));

// Current admin
$currentAdminId = (int) ($_SESSION['adminid'] ?? 0);

// Permissions
[$canViewAll, $canApprove] = DashH::resolvePermissions($currentAdminId);

// KPIs
$kpi = DashH::kpis($from, $to, $currentAdminId, $canViewAll, $today);

// Approvals queue
$pending = [];
$pendingTotals = [];
if ($canApprove) {
    [$pending, $pendingTotals] = DashH::approvalsQueue();
}

// Time by Department
$byDept = DashH::timeByDept($from, $to, $currentAdminId, $canViewAll);

// Recent Activity
$recent = DashH::recentActivity(10, $currentAdminId, $canViewAll);
?>
<div class="timekeeper-fullwidth dashboard-root">
  <h2>Dashboard</h2>

  <!-- Date range filters -->
  <form method="get" class="dash-filters" action="addonmodules.php">
    <input type="hidden" name="module" value="timekeeper">
    <input type="hidden" name="timekeeperpage" value="dashboard">

    <div class="row">
      <div class="item">
        <label>From</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="item">
        <label>To</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        <a class="btn btn-default" href="addonmodules.php?module=timekeeper&timekeeperpage=dashboard">Reset</a>
      </div>
    </div>
  </form>

  <?php if (!$canViewAll): ?>
    <div class="tk-alert tk-alert-warning">
      You are viewing <strong>only your own timesheet data</strong>.
    </div>
  <?php endif; ?>

  <!-- KPI cards -->
  <div class="dash-grid">
    <div class="kpi"><h4>Pending</h4><div class="num"><?= (int)$kpi['pending'] ?></div></div>
    <div class="kpi"><h4>Approved</h4><div class="num"><?= (int)$kpi['approved'] ?></div></div>
    <div class="kpi"><h4>Rejected</h4><div class="num"><?= (int)$kpi['rejected'] ?></div></div>
    <div class="kpi"><h4>Billable (hrs)</h4><div class="num"><?= number_format((float)$kpi['billable_hours'],2) ?></div></div>
    <div class="kpi"><h4>SLA (hrs)</h4><div class="num"><?= number_format((float)$kpi['sla_hours'],2) ?></div></div>

    <?php if ($canViewAll): ?>
      <div class="kpi"><h4>Missing Today</h4><div class="num"><?= (int)$kpi['missing_today'] ?></div></div>
    <?php else: ?>
      <div class="kpi"><h4>&nbsp;</h4><div class="num">&nbsp;</div></div>
    <?php endif; ?>
  </div>

  <div class="row-wrap">
    <!-- Approvals Queue (only for approvers) -->
    <?php if ($canApprove): ?>
      <div class="col col-wide">
        <header>Approvals Queue</header>
        <div class="body">
          <?php if (!count($pending)): ?>
            <div class="tk-alert tk-alert-success">No pending timesheets.</div>
          <?php else: ?>
            <table class="table table-bordered compact">
              <thead><tr><th>Date</th><th>Admin</th><th>Total</th><th>Age (d)</th><th>Actions</th></tr></thead>
              <tbody>
              <?php foreach ($pending as $p):
                $pt = $pendingTotals[$p->id] ?? (object)['total'=>0,'billable'=>0,'sla'=>0];
                $age = max(0,(strtotime(date('Y-m-d'))-strtotime($p->timesheet_date))/86400);
              ?>
                <tr>
                  <td><?= htmlspecialchars($p->timesheet_date) ?></td>
                  <td><?= htmlspecialchars($p->admin_name) ?></td>
                  <td><?= number_format((float)$pt->total,2) ?></td>
                  <td><?= (int)$age ?></td>
                  <td>
                    <a class="btn btn-xs btn-success" href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&approve_id=<?= (int)$p->id ?>">Approve</a>
                    <a class="btn btn-xs btn-danger"  href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&reject_id=<?= (int)$p->id ?>">Reject</a>
                    <a class="btn btn-xs btn-default" href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&view_id=<?= (int)$p->id ?>">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Time by Department -->
    <div class="col">
      <header>Time by Department (<?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?>)</header>
      <div class="body">
        <?php if (!count($byDept)): ?>
          <div class="tk-alert tk-alert-success">No data.</div>
        <?php else: ?>
          <table class="table table-bordered compact">
            <thead>
              <tr>
                <th>Department</th>
                <th>Total</th>
                <th>Billable</th>
                <th>Billable %</th>
                <th>SLA</th>
                <th>SLA %</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($byDept as $r):
                $billablePct = ($r->total > 0) ? ($r->billable / $r->total * 100) : 0;
                $slaPct      = ($r->total > 0) ? ($r->sla      / $r->total * 100) : 0;
                $billablePct = (float) max(0, min(100, $billablePct));
                $slaPct      = (float) max(0, min(100, $slaPct));
              ?>
              <tr>
                <td><?= htmlspecialchars($r->dept) ?></td>
                <td><?= number_format((float)$r->total,2) ?></td>
                <td><?= number_format((float)$r->billable,2) ?></td>
                <td>
                  <?= number_format($billablePct,0) ?>%
                  <div class="progress-wrap">
                    <div class="progress-bar" data-width="<?= $billablePct ?>"></div>
                  </div>
                </td>
                <td><?= number_format((float)$r->sla,2) ?></td>
                <td>
                  <?= number_format($slaPct,0) ?>%
                  <div class="progress-wrap">
                    <div class="progress-bar" data-width="<?= $slaPct ?>"></div>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="row-wrap">
    <div class="col">
      <header>Recent Activity</header>
      <div class="body">
        <?php if (!count($recent)): ?>
          <div class="tk-alert tk-alert-success">No recent changes.</div>
        <?php else: ?>
          <table class="table table-bordered compact">
            <thead><tr><th>Date</th><th>Admin</th><th>Description</th><th>Updated</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $ev): ?>
                <tr>
                  <td><?= htmlspecialchars($ev->timesheet_date) ?></td>
                  <td><?= htmlspecialchars($ev->admin_name) ?></td>
                  <td><?= htmlspecialchars(mb_strimwidth($ev->description ?? '',0,60,'…','UTF-8')) ?></td>
                  <td><?= htmlspecialchars($ev->updated_at) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
