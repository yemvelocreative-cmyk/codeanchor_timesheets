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
<nav class="tk-nav tk-nav--side" x-data>
  <?php if ($showRejectedBanner): ?>
    <div class="tk-banner">
      <div class="msg"><strong>Action needed:</strong> You have <?= (int)$navRejectedCount ?> rejected timesheet<?= $navRejectedCount > 1 ? 's' : '' ?>.</div>
      <div class="act">
        <a class="btn btn-sm btn-warning" href="addonmodules.php?module=timekeeper&amp;timekeeperpage=pending_timesheets&amp;status=rejected">Review now</a>
        <a class="btn btn-sm btn-default" href="<?= htmlspecialchars($dismissUrl, ENT_QUOTES, 'UTF-8') ?>">Dismiss</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="tk-side-layout">
    <aside class="tk-side">
      <button type="button" class="tk-collapse" data-tk-toggle="side">☰</button>
      <ul class="tk-side-list" id="tkSideList">
        <?php
        // Simple inline SVG icon map (no external deps)
        $icons = [
          'dashboard' => '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>',
          'timesheet' => '<svg width="16" height="16" viewBox="0 0 24 24"><path d="M7 2h10v2H7V2zm12 0h2v2h-2V2zM3 4h2V2H3v2zM3 6h18v16H3V6zm2 2v12h14V8H5zm2 2h10v2H7v-2zm0 4h10v2H7v-2z"/></svg>',
          'pending_timesheets' => '<svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 8v5l4 2 .9-1.8-3.1-1.6V8zM12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>',
          'approved_timesheets' => '<svg width="16" height="16" viewBox="0 0 24 24"><path d="M9 16.2l-3.5-3.5L4 14.2 9 19l11-11-1.5-1.5z"/></svg>',
          'departments' => '<svg width="16" height="16" viewBox="0 0 24 24"><path d="M3 3h8v8H3V3zm10 0h8v5h-8V3zM3 13h8v8H3v-8zm10 7h8v-8h-8v8z"/></svg>',
          'task_categories' => '<svg width="16" height="16" viewBox="0 0 24 24"><path d="M3 5h18v2H3V5zm0 6h12v2H3v-2zm0 6h18v2H3v-2z"/></svg>',
          'reports' => '<svg width="16" height="16" viewBox="0 0 24 24"><path d="M3 3h18v18H3V3zm4 12h2v2H7v-2zm0-4h2v3H7v-3zm4 4h2v2h-2v-2zm0-7h2v7h-2V8zm4 5h2v2h-2v-2z"/></svg>',
          'settings' => '<svg width="16" height="16" viewBox="0 0 24 24"><path d="M19.4 12.9a7.6 7.6 0 000-1.8l2-1.6-2-3.5-2.4.9a6.5 6.5 0 00-1.5-.9l-.4-2.5h-4l-.4 2.5c-.5.2-1 .5-1.5.9l-2.4-.9-2 3.5 2 1.6a7.6 7.6 0 000 1.8l-2 1.6 2 3.5 2.4-.9c.5.4 1 .7 1.5.9l.4 2.5h4l.4-2.5c.5-.2 1-.5 1.5-.9l2.4.9 2-3.5-2-1.6zM12 15.5A3.5 3.5 0 1112 8a3.5 3.5 0 010 7.5z"/></svg>',
        ];
        foreach ($menuItems as $pageKey => $label):
          if (!tk_isPageAllowedForRole($roleId, $pageKey)) continue;
          $isActive = ($current === $pageKey) ? 'active' : '';
          $href = 'addonmodules.php?module=timekeeper&timekeeperpage=' . urlencode($pageKey);
        ?>
        <li>
          <a class="tk-side-link <?= $isActive ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
            <span class="ico"><?= $icons[$pageKey] ?? '' ?></span>
            <span class="txt"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </aside>
  </div>

  <style>
    .tk-nav--side { margin-bottom: 12px; }
    .tk-banner{display:flex;justify-content:space-between;gap:.5rem;align-items:center;background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:.5rem .75rem;margin-bottom:.5rem}
    .tk-side-layout{display:grid;grid-template-columns:220px 1fr;gap:1rem}
    @media (max-width: 1024px){.tk-side-layout{grid-template-columns:1fr}}
    .tk-side{position:sticky;top:0;align-self:start}
    .tk-collapse{display:none;margin:0 0 .5rem 0;padding:.35rem .6rem;border:1px solid #E5E7EB;border-radius:6px;background:#FFF}
    @media (max-width:1024px){.tk-collapse{display:inline-block}}
    .tk-side-list{list-style:none;margin:0;padding:0;background:#FFF;border:1px solid #E5E7EB;border-radius:10px;overflow:hidden}
    .tk-side-link{display:flex;gap:.5rem;align-items:center;padding:.6rem .75rem;color:#374151;text-decoration:none;border-left:3px solid transparent}
    .tk-side-link:hover{background:#F9FAFB}
    .tk-side-link.active{background:#EEF2FF;border-left-color:#6366F1;color:#111827;font-weight:600}
    .tk-side-link .ico{display:inline-flex}
    .tk-side-link .txt{white-space:nowrap}
  </style>

  <script>
    (function(){
      var btn = document.querySelector('.tk-collapse');
      var list = document.getElementById('tkSideList');
      if(btn && list){
        btn.addEventListener('click', function(){
          var open = list.getAttribute('data-open') === '1';
          list.style.display = open ? '' : 'block';
          list.setAttribute('data-open', open ? '0' : '1');
        });
        if (window.matchMedia('(max-width:1024px)').matches) {
          list.style.display = 'none';
          list.setAttribute('data-open','0');
        }
      }
    })();
  </script>
</nav>

