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
    <div class="tk-top-scroller" role="tablist">
      <?php foreach ($menuItems as $pageKey => $label):
        if (!tk_isPageAllowedForRole($roleId, $pageKey)) continue;
        $isActive = ($current === $pageKey) ? 'active' : '';
        $href = 'addonmodules.php?module=timekeeper&timekeeperpage=' . urlencode($pageKey);
      ?>
        <a role="tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>"
           class="tk-top-link <?= $isActive ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <style>
    .tk-nav--top { margin-bottom: 12px; }
    .tk-banner{display:flex;justify-content:space-between;gap:.5rem;align-items:center;background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:.5rem .75rem;margin-bottom:.5rem}
    .tk-banner .msg{font-size:14px}
    .tk-top-wrap{position:relative}
    .tk-top-scroller{display:flex;gap:.25rem;overflow:auto;padding:.25rem;border-bottom:1px solid #E5E7EB}
    .tk-top-link{position:relative;display:inline-flex;align-items:center;padding:.5rem .75rem;font-size:14px;color:#374151;text-decoration:none;border-radius:8px}
    .tk-top-link:hover{background:#F3F4F6}
    .tk-top-link.active{color:#111827;font-weight:600}
    .tk-top-link.active::after{content:"";position:absolute;left:.5rem;right:.5rem;bottom:-1px;height:2px;background:#2563EB;border-radius:2px}
  </style>
</nav>

