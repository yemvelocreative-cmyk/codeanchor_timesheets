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
<nav class="tk-nav tk-nav--seg">
  <?php if ($showRejectedBanner): ?>
    <div class="tk-banner">
      <div class="msg"><strong>Action needed:</strong> You have <?= (int)$navRejectedCount ?> rejected timesheet<?= $navRejectedCount > 1 ? 's' : '' ?>.</div>
      <div class="act">
        <a class="btn btn-sm btn-warning" href="addonmodules.php?module=timekeeper&amp;timekeeperpage=pending_timesheets&amp;status=rejected">Review now</a>
        <a class="btn btn-sm btn-default" href="<?= htmlspecialchars($dismissUrl, ENT_QUOTES, 'UTF-8') ?>">Dismiss</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Mobile: compact select -->
  <div class="tk-seg-mobile">
    <select id="tkSegSelect" class="form-control input-sm">
      <?php foreach ($menuItems as $pageKey => $label):
        if (!tk_isPageAllowedForRole($roleId, $pageKey)) continue;
        $href = 'addonmodules.php?module=timekeeper&timekeeperpage=' . urlencode($pageKey);
      ?>
        <option value="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" <?= $current === $pageKey ? 'selected' : '' ?>>
          <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Desktop: segmented pills -->
  <div class="tk-seg-desktop" role="tablist" aria-label="Timekeeper sections">
    <?php foreach ($menuItems as $pageKey => $label):
      if (!tk_isPageAllowedForRole($roleId, $pageKey)) continue;
      $isActive = ($current === $pageKey) ? 'active' : '';
      $href = 'addonmodules.php?module=timekeeper&timekeeperpage=' . urlencode($pageKey);
    ?>
      <a role="tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>"
         class="tk-pill <?= $isActive ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </div>

  <style>
    .tk-nav--seg { margin-bottom: 12px; }
    .tk-banner{display:flex;justify-content:space-between;gap:.5rem;align-items:center;background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:.5rem .75rem;margin-bottom:.5rem}
    .tk-seg-mobile{display:none}
    @media (max-width: 700px){ .tk-seg-mobile{display:block;margin-bottom:.5rem} }
    .tk-seg-desktop{display:flex;gap:.35rem;flex-wrap:wrap}
    @media (max-width:700px){ .tk-seg-desktop{display:none} }
    .tk-pill{display:inline-flex;align-items:center;gap:.5rem;padding:.45rem .75rem;border:1px solid #E5E7EB;border-radius:999px;text-decoration:none;color:#374151;background:#FFF;font-size:13px}
    .tk-pill:hover{background:#F9FAFB}
    .tk-pill.active{border-color:#2563EB;background:#2563EB;color:#FFF;font-weight:600}
  </style>

  <script>
    (function(){
      var sel=document.getElementById('tkSegSelect');
      if(sel){ sel.addEventListener('change', function(){ window.location.href = this.value; }); }
    })();
  </script>
</nav>

