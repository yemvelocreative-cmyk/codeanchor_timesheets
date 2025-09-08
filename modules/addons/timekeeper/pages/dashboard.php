<?php
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('Access Denied'); }

// Helper loader (supports helpers/ or includes/helpers/)
$base = dirname(__DIR__);
$try = function (string $relA, string $relB) use ($base) {
    $a = $base . $relA; $b = $base . $relB;
    if (is_file($a)) { require_once $a; return; }
    if (is_file($b)) { require_once $b; return; }
    throw new \RuntimeException("Missing helper: tried {$a} and {$b}");
};
$try('/helpers/core_helper.php', '/includes/helpers/core_helper.php');
$try('/helpers/dashboard_helper.php', '/includes/helpers/dashboard_helper.php');

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\DashboardHelper as DashH;

// ---- Dynamic base URL + asset helper (polyfill if not in core_helper yet) ----
// Preferred (if added to core_helper.php):
//   \Timekeeper\Helpers\timekeeperBaseUrl(): string
//   \Timekeeper\Helpers\timekeeperAsset(string $relPath): string
if (!function_exists('\\Timekeeper\\Helpers\\timekeeperBaseUrl') || !function_exists('\\Timekeeper\\Helpers\\timekeeperAsset')) {
    // Local polyfill (scoped to this file)
    $tkSystemUrl = (function (): string {
        try {
            $ssl = (string) \WHMCS\Config\Setting::getValue('SystemSSLURL');
            $url = $ssl !== '' ? $ssl : (string) \WHMCS\Config\Setting::getValue('SystemURL');
            return rtrim($url, '/');
        } catch (\Throwable $e) {
            return '';
        }
    })();

    $tkBase = ($tkSystemUrl !== '' ? $tkSystemUrl : '') . '/modules/addons/timekeeper';
    $tkBase = rtrim($tkBase, '/');

    // Callable for cache-busted assets, e.g. $tkAsset('css/dashboard.css')
    $tkAsset = function (string $relPath) use ($tkBase, $base): string {
        $rel = ltrim($relPath, '/');
        $url = $tkBase . '/' . $rel;

        $file = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (@is_file($file)) {
            $ver = @filemtime($file);
            if ($ver) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . 'v=' . $ver;
            }
        }
        return $url;
    };
} else {
    // Use canonical helpers if present
    $tkBase  = \Timekeeper\Helpers\timekeeperBaseUrl();
    $tkAsset = '\Timekeeper\Helpers\timekeeperAsset'; // callable
}

$today = date('Y-m-d');
$rawFrom = $_GET['from'] ?? null;
$rawTo   = $_GET['to']   ?? null;
$from = (CoreH::isValidDate($rawFrom) ? $rawFrom : date('Y-m-01'));
$to   = (CoreH::isValidDate($rawTo)   ? $rawTo   : date('Y-m-d'));

$currentAdminId = (int) ($_SESSION['adminid'] ?? 0);
[$canViewAll, $canApprove] = DashH::resolvePermissions($currentAdminId);

$kpi = DashH::kpis($from, $to, $currentAdminId, $canViewAll, $today);
[$myTodayStatus, $myTodayTotals] = DashH::myToday($currentAdminId, $today);
$aging = $canApprove ? DashH::agingBuckets([2,5]) : [];

$perPage = max(5, min(100, (int)($_GET['per'] ?? 10)));
$page    = max(1, (int)($_GET['p'] ?? 1));
$ageMin  = isset($_GET['age']) ? max(0, (int)($_GET['age'])) : null;

$pending = $pendingTotals = [];
$totalPending = 0;
if ($canApprove) {
    [$pending, $pendingTotals, $totalPending] = DashH::approvalsQueue($page, $perPage, $ageMin);
}

$byDept = DashH::timeByDept($from, $to, $currentAdminId, $canViewAll);
$topMix = DashH::topClientsProjects($from, $to, $currentAdminId, $canViewAll, 10);
$recent = DashH::recentActivity(10, $currentAdminId, $canViewAll);
[$alertsRejected, $minTaskTime, $alertsUnderMin] = DashH::alerts($from, $to, $currentAdminId, $canViewAll);
[$trendLabels, $trendBillable, $trendSla, $trendPending] = DashH::trendSeries($from, $to, $currentAdminId, $canViewAll);

// Derived percentages
$totalHours = max(0.0001, (float)$kpi['total_hours']); // avoid /0
$billablePct = min(100, max(0, ($kpi['billable_hours'] / $totalHours) * 100));
$slaPct      = min(100, max(0, ($kpi['sla_hours'] / $totalHours) * 100));

$baseUrl = 'addonmodules.php?module=timekeeper&timekeeperpage=';
?>
<div class="timekeeper-fullwidth dashboard-root">
  <div class="dash-topbar">
    <div class="title">
      <h2>Dashboard</h2>
      <div class="role-badges">
        <?php if ($canViewAll): ?><span class="tk-badge tk-badge-info">View All</span><?php endif; ?>
        <?php if ($canApprove): ?><span class="tk-badge tk-badge-success">Approver</span><?php endif; ?>
      </div>
    </div>
    <form method="get" class="dash-filters" action="addonmodules.php">
      <input type="hidden" name="module" value="timekeeper">
      <input type="hidden" name="timekeeperpage" value="dashboard">
      <div class="row">
        <div class="item">
          <label>From</label>
          <input type="date" name="from" value="<?= CoreH::e($from) ?>">
        </div>
        <div class="item">
          <label>To</label>
          <input type="date" name="to" value="<?= CoreH::e($to) ?>">
        </div>
        <div class="actions">
          <div class="preset-group">
            <button type="button" class="btn btn-default js-preset" data-range="today">Today</button>
            <button type="button" class="btn btn-default js-preset" data-range="week">This Week</button>
            <button type="button" class="btn btn-default js-preset" data-range="month">This Month</button>
            <button type="button" class="btn btn-default js-preset" data-range="lastmonth">Last Month</button>
          </div>
          <button type="submit" class="btn btn-primary">Apply</button>
          <a class="btn btn-default" href="addonmodules.php?module=timekeeper&timekeeperpage=dashboard">Reset</a>
        </div>
      </div>
    </form>
  </div>

  <?php if (!$canViewAll): ?>
    <div class="tk-alert tk-alert-warning">
      You are viewing <strong>only your own timesheet data</strong>.
    </div>
  <?php endif; ?>

  <!-- Priority Strip -->
  <div class="priority-strip">
    <div class="prio-card">
      <div class="prio-head">
        <span>My Today</span>
        <span class="tk-badge <?= $myTodayStatus==='approved'?'tk-badge-success':($myTodayStatus==='pending'?'tk-badge-warning':'tk-badge-danger') ?>">
          <?= CoreH::e(ucfirst($myTodayStatus)) ?>
        </span>
      </div>
      <div class="prio-totals tk-totalsbar">
        <div><span>Total</span><strong><?= number_format($myTodayTotals['total'],2) ?></strong></div>
        <div><span>Billable</span><strong><?= number_format($myTodayTotals['billable'],2) ?></strong></div>
        <div><span>SLA</span><strong><?= number_format($myTodayTotals['sla'],2) ?></strong></div>
      </div>
      <div class="prio-actions">
        <a class="btn btn-default btn-xs" href="<?= $baseUrl ?>timesheet">Open Timesheet</a>
        <a class="btn btn-primary btn-xs" href="<?= $baseUrl ?>timesheet#add">Add Entry</a>
      </div>
    </div>

    <?php if ($canApprove): ?>
      <div class="prio-card">
        <div class="prio-head"><span>Aging Pendings</span></div>
        <div class="prio-badges">
          <a class="tk-badge tk-badge-warning" href="<?= $baseUrl ?>dashboard&age=2&from=<?= CoreH::e($from) ?>&to=<?= CoreH::e($to) ?>">≥2d: <?= (int)($aging[2]??0) ?></a>
          <a class="tk-badge tk-badge-danger"  href="<?= $baseUrl ?>dashboard&age=5&from=<?= CoreH::e($from) ?>&to=<?= CoreH::e($to) ?>">≥5d: <?= (int)($aging[5]??0) ?></a>
        </div>
        <div class="prio-actions">
          <a class="btn btn-default btn-xs" href="<?= $baseUrl ?>pending_timesheets">Go to Approvals</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($canViewAll): ?>
      <div class="prio-card">
        <div class="prio-head"><span>Missing Today</span></div>
        <div class="prio-big"><?= (int)$kpi['missing_today'] ?></div>
        <div class="prio-actions">
          <a class="btn btn-default btn-xs" href="<?= $baseUrl ?>reports&report=missing_today">View List</a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- KPI cards (with conversion) -->
  <div class="dash-grid">
    <a class="kpi" href="<?= $baseUrl ?>pending_timesheets">
      <h4>Pending</h4><div class="num"><?= (int)$kpi['pending'] ?></div>
    </a>
    <a class="kpi" href="<?= $baseUrl ?>approved_timesheets">
      <h4>Approved</h4><div class="num"><?= (int)$kpi['approved'] ?></div>
    </a>
    <a class="kpi" href="<?= $baseUrl ?>reports&status=rejected&from=<?= CoreH::e($from) ?>&to=<?= CoreH::e($to) ?>">
      <h4>Rejected</h4><div class="num"><?= (int)$kpi['rejected'] ?></div>
    </a>
    <div class="kpi"><h4>Billable (hrs)</h4><div class="num"><?= number_format($kpi['billable_hours'],2) ?></div></div>
    <div class="kpi"><h4>SLA (hrs)</h4><div class="num"><?= number_format($kpi['sla_hours'],2) ?></div></div>
    <div class="kpi"><h4>Billable %</h4><div class="num"><?= number_format($billablePct,0) ?>%</div></div>
  </div>

  <!-- Workload Mix -->
  <div class="row-wrap">
    <div class="col">
      <header>Time by Department (<?= CoreH::e($from) ?> → <?= CoreH::e($to) ?>)</header>
      <div class="body">
        <?php if (!count($byDept)): ?>
          <div class="tk-alert tk-alert-success">No data.</div>
        <?php else: ?>
          <table class="table table-bordered compact">
            <thead>
              <tr>
                <th>Department</th><th>Total</th><th>Billable</th><th>Billable %</th><th>SLA</th><th>SLA %</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($byDept as $r):
                $bp = ($r->total>0)? min(100,max(0,($r->billable/$r->total*100))) : 0;
                $sp = ($r->total>0)? min(100,max(0,($r->sla     /$r->total*100))) : 0;
              ?>
              <tr>
                <td><?= CoreH::e($r->dept) ?></td>
                <td><?= number_format((float)$r->total,2) ?></td>
                <td><?= number_format((float)$r->billable,2) ?></td>
                <td>
                  <?= number_format($bp,0) ?>%
                  <div class="progress-wrap"><div class="progress-bar" data-width="<?= $bp ?>"></div></div>
                </td>
                <td><?= number_format((float)$r->sla,2) ?></td>
                <td>
                  <?= number_format($sp,0) ?>%
                  <div class="progress-wrap"><div class="progress-bar" data-width="<?= $sp ?>"></div></div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="col">
      <header>Top Clients/Projects (<?= CoreH::e($from) ?> → <?= CoreH::e($to) ?>)</header>
      <div class="body">
        <?php if (!count($topMix)): ?>
          <div class="tk-alert tk-alert-success">No data.</div>
        <?php else: ?>
          <table class="table table-bordered compact">
            <thead>
              <tr><th>Client / Project</th><th>Total</th><th>Billable</th><th>Billable %</th><th>SLA</th><th>SLA %</th></tr>
            </thead>
            <tbody>
              <?php foreach ($topMix as $row):
                $bp = ($row->total>0)? min(100,max(0,($row->billable/$row->total*100))) : 0;
                $sp = ($row->total>0)? min(100,max(0,($row->sla     /$row->total*100))) : 0;
              ?>
              <tr>
                <td><?= CoreH::e($row->label) ?></td>
                <td><?= number_format((float)$row->total,2) ?></td>
                <td><?= number_format((float)$row->billable,2) ?></td>
                <td>
                  <?= number_format($bp,0) ?>%
                  <div class="progress-wrap"><div class="progress-bar" data-width="<?= $bp ?>"></div></div>
                </td>
                <td><?= number_format((float)$row->sla,2) ?></td>
                <td>
                  <?= number_format($sp,0) ?>%
                  <div class="progress-wrap"><div class="progress-bar" data-width="<?= $sp ?>"></div></div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Approvals Queue (with pagination & totals bar) -->
  <?php if ($canApprove): ?>
    <div class="row-wrap">
      <div class="col col-wide">
        <header>
          <div class="flex-head">
            <span>Approvals Queue</span>
            <div class="mini-filters">
              <form method="get" action="addonmodules.php" class="inline">
                <input type="hidden" name="module" value="timekeeper">
                <input type="hidden" name="timekeeperpage" value="dashboard">
                <input type="hidden" name="from" value="<?= CoreH::e($from) ?>">
                <input type="hidden" name="to" value="<?= CoreH::e($to) ?>">
                <label>Older than</label>
                <select name="age" onchange="this.form.submit()">
                  <option value="">Any</option>
                  <option value="2" <?= ($ageMin===2)?'selected':'' ?>>≥2 days</option>
                  <option value="5" <?= ($ageMin===5)?'selected':'' ?>>≥5 days</option>
                </select>
                <label>Page size</label>
                <select name="per" onchange="this.form.submit()">
                  <?php foreach ([10,25,50,100] as $opt): ?>
                  <option value="<?= $opt ?>" <?= ($perPage===$opt)?'selected':'' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>
          </div>
        </header>
        <div class="body">
          <?php if (!count($pending)): ?>
            <div class="tk-alert tk-alert-success">No pending timesheets.</div>
          <?php else: ?>
            <table class="table table-bordered compact tk-approvals">
              <thead><tr><th>Date</th><th>Admin</th><th>Totals</th><th>Age (d)</th><th>Actions</th></tr></thead>
              <tbody>
              <?php foreach ($pending as $p):
                $pt = $pendingTotals[$p->id] ?? (object)['total'=>0,'billable'=>0,'sla'=>0];
                $age = max(0,(strtotime(date('Y-m-d'))-strtotime($p->timesheet_date))/86400);
              ?>
                <tr>
                  <td><?= CoreH::e($p->timesheet_date) ?></td>
                  <td><?= CoreH::e($p->admin_name) ?></td>
                  <td>
                    <div class="tk-totalsbar">
                      <div><span>Total</span><strong><?= number_format((float)$pt->total,2) ?></strong></div>
                      <div><span>Billable</span><strong><?= number_format((float)$pt->billable,2) ?></strong></div>
                      <div><span>SLA</span><strong><?= number_format((float)$pt->sla,2) ?></strong></div>
                    </div>
                  </td>
                  <td><?= (int)$age ?></td>
                  <td class="nowrap">
                    <a class="btn btn-xs btn-success" href="<?= $baseUrl ?>pending_timesheets&approve_id=<?= (int)$p->id ?>">Approve</a>
                    <a class="btn btn-xs btn-danger"  href="<?= $baseUrl ?>pending_timesheets&reject_id=<?= (int)$p->id ?>">Reject</a>
                    <a class="btn btn-xs btn-default" href="<?= $baseUrl ?>pending_timesheets&view_id=<?= (int)$p->id ?>">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>

            <?php
              $pages = max(1, (int)ceil($totalPending / $perPage));
              if ($pages > 1):
                $mk = function ($p) use ($from,$to,$perPage,$ageMin) {
                  $q = http_build_query([
                    'module'=>'timekeeper','timekeeperpage'=>'dashboard',
                    'from'=>$from,'to'=>$to,'per'=>$perPage,'p'=>$p
                  ] + ($ageMin ? ['age'=>$ageMin] : []));
                  return 'addonmodules.php?' . $q;
                };
            ?>
            <div class="tk-pager">
              <?php if ($page>1): ?><a class="btn btn-default btn-xs" href="<?= $mk($page-1) ?>">Prev</a><?php endif; ?>
              <span class="page-num">Page <?= (int)$page ?> / <?= (int)$pages ?></span>
              <?php if ($page<$pages): ?><a class="btn btn-default btn-xs" href="<?= $mk($page+1) ?>">Next</a><?php endif; ?>
            </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Trends -->
  <div class="row-wrap">
    <div class="col">
      <header>Trend: Billable vs SLA</header>
      <div class="body">
        <canvas class="tk-spark" width="600" height="140"
          data-labels='<?= CoreH::e(json_encode($trendLabels)) ?>'
          data-series='<?= CoreH::e(json_encode([$trendBillable,$trendSla])) ?>'>
        </canvas>
      </div>
    </div>
    <div class="col">
      <header>Trend: Pending Count</header>
      <div class="body">
        <canvas class="tk-spark" width="600" height="140"
          data-labels='<?= CoreH::e(json_encode($trendLabels)) ?>'
          data-series='<?= CoreH::e(json_encode([$trendPending])) ?>'>
        </canvas>
      </div>
    </div>
  </div>

  <!-- Recent + Alerts -->
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
                <td><?= CoreH::e($ev->timesheet_date) ?></td>
                <td><?= CoreH::e($ev->admin_name) ?></td>
                <td><?= CoreH::e(mb_strimwidth($ev->description ?? '',0,60,'…','UTF-8')) ?></td>
                <td><?= CoreH::e($ev->updated_at) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div class="mt-6">
            <a class="btn btn-default btn-xs" href="<?= $baseUrl ?>reports&report=activity&from=<?= CoreH::e($from) ?>&to=<?= CoreH::e($to) ?>">Show more</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col">
      <header>Alerts</header>
      <div class="body">
        <ul class="tk-alertlist">
          <li>
            <span class="tk-badge <?= $alertsRejected>0 ? 'tk-badge-danger' : 'tk-badge-muted' ?>">Rejected</span>
            <strong><?= (int)$alertsRejected ?></strong> timesheet(s) in range
          </li>
          <li>
            <span class="tk-badge <?= ($minTaskTime>0 && $alertsUnderMin>0) ? 'tk-badge-warning' : 'tk-badge-muted' ?>">Under Min Task</span>
            <?php if ($minTaskTime>0): ?>
              <strong><?= (int)$alertsUnderMin ?></strong> entries &lt; <?= number_format($minTaskTime,1) ?>h
            <?php else: ?>
              <em>Validation disabled</em>
            <?php endif; ?>
          </li>
        </ul>
        <div class="mt-6">
          <a class="btn btn-default btn-xs" href="<?= $baseUrl ?>settings#approvals">Adjust validation</a>
        </div>
      </div>
    </div>
  </div>
</div>
