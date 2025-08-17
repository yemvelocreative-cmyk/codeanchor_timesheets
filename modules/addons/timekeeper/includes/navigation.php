<?php
use WHMCS\Database\Capsule;

$navAdminId = (int) ($_SESSION['adminid'] ?? 0);

// 1) Handle dismiss ASAP (session + cookie)
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

// 2) Compute rejected count for current admin
$navRejectedCount = Capsule::table('mod_timekeeper_timesheets')
    ->where('admin_id', $navAdminId)
    ->where('status', 'rejected')
    ->count();

// 3) Decide if we should show the banner
$hideViaSession = !empty($_SESSION['timekeeper_hide_rejected_banner']);
$hideViaCookie  = !empty($_COOKIE['timekeeper_hide_rejected_banner']);
$showRejectedBanner = ($navRejectedCount > 0) && !($hideViaSession || $hideViaCookie);

// 4) Build a dismiss URL that keeps current params
$qs = $_GET;
$qs['dismiss_rejected_banner'] = '1';
$dismissUrl = 'addonmodules.php?' . http_build_query($qs);
?>

<nav class="timekeeper-nav">
  <?php if ($showRejectedBanner): ?>
    <div class="timekeeper-banner">
      <div class="message">
        <strong>Action needed:</strong>
        You have <?= (int)$navRejectedCount ?> rejected timesheet<?= $navRejectedCount > 1 ? 's' : '' ?>.
      </div>
      <div class="actions">
        <a class="btn btn-sm btn-warning"
           href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&status=rejected">
          Review now
        </a>
        <a class="btn btn-sm btn-default" href="<?= htmlspecialchars($dismissUrl, ENT_QUOTES, 'UTF-8') ?>">
          Dismiss
        </a>
      </div>
    </div>
  <?php endif; ?>

  <ul class="timekeeper-tabs">
    <?php
    // Get current admin role ID
    $currentAdminRoleId = Capsule::table('tbladmins')
        ->where('id', $_SESSION['adminid'])
        ->value('roleid');

    // Get hidden tabs for this role
    $hiddenTabs = Capsule::table('mod_timekeeper_hidden_tabs')
        ->where('role_id', $currentAdminRoleId)
        ->pluck('tab_name')
        ->toArray();

    $menuItems = [
        'dashboard' => 'Dashboard',
        'timesheet' => 'Timesheets',
        'pending_timesheets' => 'Pending Timesheets',
        'approved_timesheets' => 'Approved Timesheets',
        'departments' => 'Departments',
        'task_categories' => 'Task Categories',
        'reports' => 'Reports',
        'settings' => 'Settings'
    ];

    // Remove hidden tabs
    foreach ($hiddenTabs as $hiddenTab) {
        unset($menuItems[$hiddenTab]);
    }

    $page = $_GET['timekeeperpage'] ?? '';
    foreach ($menuItems as $key => $label) {
        $activeClass = ($page === $key) ? 'active' : '';
        echo "<li><a href='addonmodules.php?module=timekeeper&timekeeperpage={$key}' class='{$activeClass}'>{$label}</a></li>";
    }
    ?>
  </ul>
</nav>
