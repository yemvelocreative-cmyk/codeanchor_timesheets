<?php
if (!defined('WHMCS')) { die('Access Denied'); }

use WHMCS\Database\Capsule;

/** Current admin + role **/
$navAdminId = (int)($_SESSION['adminid'] ?? 0);
$roleId     = tk_getAdminRoleId($navAdminId);

/** 1) Dismiss banner (session + cookie) **/
if (isset($_GET['dismiss_rejected_banner']) && $_GET['dismiss_rejected_banner'] === '1') {
    $_SESSION['timekeeper_hide_rejected_banner'] = '1';
    $expires = strtotime('tomorrow 00:00:00');
    setcookie('timekeeper_hide_rejected_banner', '1', $expires, '/', '', false, true);

    if (!headers_sent()) {
        $qs = $_GET;
        unset($qs['dismiss_rejected_banner']);
        $url = 'addonmodules.php?' . http_build_query($qs);
        header('Location: ' . $url);
        exit;
    }
}

/** 2) Rejected count for current admin **/
$navRejectedCount = Capsule::table('mod_timekeeper_timesheets')
    ->where('admin_id', $navAdminId)
    ->where('status', 'rejected')
    ->count();

// Counts to show badges in nav
$navPendingCount = Capsule::table('mod_timekeeper_timesheets')
  ->where('admin_id', $navAdminId)
  ->where('status', 'pending')
  ->count();

$navApprovedCount = Capsule::table('mod_timekeeper_timesheets')
  ->where('admin_id', $navAdminId)
  ->where('status', 'approved')
  ->count();

/** 3) Should we show the banner? **/
$hideViaSession = !empty($_SESSION['timekeeper_hide_rejected_banner']);
$hideViaCookie  = !empty($_COOKIE['timekeeper_hide_rejected_banner']);
$showRejectedBanner = ($navRejectedCount > 0) && !($hideViaSession || $hideViaCookie);

/** 4) Dismiss URL preserving current params **/
$qs = $_GET;
$qs['dismiss_rejected_banner'] = '1';
$dismissUrl = 'addonmodules.php?' . http_build_query($qs);

/** Active page (normalized) **/
$current = isset($_GET['timekeeperpage']) ? tk_normalize_page((string)$_GET['timekeeperpage']) : 'dashboard';

/** Menu items (keys must match router page keys) **/
$menuItems = [
    'dashboard'           => 'Dashboard',
    'timesheet'           => 'Timesheets',
    'pending_timesheets'  => 'Pending Timesheets',
    'approved_timesheets' => 'Approved Timesheets',
    'departments'         => 'Departments',
    'task_categories'     => 'Task Categories',
    'reports'             => 'Reports',
    'settings'            => 'Settings',
];
?>
<nav class="tk-nav tk-nav--top">
  <?php if ($showRejectedBanner): ?>
    <div class="tk-banner">
      <div class="msg"><strong>Action needed:</strong> You have <?= (int)$navRejectedCount ?> rejected timesheet<?= $navRejectedCount > 1 ? 's' : '' ?>.</div>
      <div class="act">
        <a class="btn btn-sm btn-warning" href="addonmodules.php?module=timekeeper&amp;timekeeperpage=pending_timesheets&amp;status=rejected">Review now</a>
        <a class="btn btn-sm btn-default" href="<?= htmlspecialchars($dismissUrl, ENT_QUOTES, 'UTF-8') ?>">Dismiss</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="tk-top-wrap">
    <div class="tk-top-scroller" role="tablist" aria-label="Timekeeper navigation">
      <?php foreach ($menuItems as $pageKey => $label):
        if (!tk_isPageAllowedForRole($roleId, $pageKey)) continue;
        $isActive = ($current === $pageKey) ? 'active' : '';
        $href = 'addonmodules.php?module=timekeeper&timekeeperpage=' . urlencode($pageKey);

        // Optional: inject a small count badge next to selected pages
        $badge = '';
        if ($pageKey === 'pending_timesheets' && !empty($navPendingCount)) {
          $badge = '<span class="tk-count">'.(int)$navPendingCount.'</span>';
        } elseif ($pageKey === 'approved_timesheets' && !empty($navApprovedCount)) {
          $badge = '<span class="tk-count">'.(int)$navApprovedCount.'</span>';
        }
      ?>
        <a role="tab"
           aria-selected="<?= $isActive ? 'true' : 'false' ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>
           class="tk-top-link <?= $isActive ?>"
           href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
          <span class="txt"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
          <?= $badge ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</nav>
