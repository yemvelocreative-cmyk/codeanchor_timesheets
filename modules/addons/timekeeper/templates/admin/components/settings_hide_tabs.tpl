<?php
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('Access Denied'); }

/**
 * This template can work standalone (fetching its own data) OR
 * with data provided by settings.php. If the variables are already
 * set, we won’t refetch them.
 */

// CSRF fallback if not provided by controller
if (!isset($tkCsrf)) {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (empty($_SESSION['timekeeper_csrf'])) {
        $_SESSION['timekeeper_csrf'] = bin2hex(random_bytes(32));
    }
    $tkCsrf = $_SESSION['timekeeper_csrf'];
}

// Tabs config (single source of truth here if not passed in)
if (!isset($tabs) || !is_array($tabs)) {
    $tabs = [
        'departments'        => 'Departments',
        'task_categories'    => 'Task Categories',
        'timesheets'         => 'Timesheets',
        'pending_timesheets' => 'Pending Timesheets',
        'reports'            => 'Reports',
        'settings'           => 'Settings',
    ];
}

// Roles
if (!isset($roles)) {
    $roles = Capsule::table('tbladminroles')->orderBy('name')->get();
}

// Hidden tabs map: role_id => [tab_name, ...]
if (!isset($hiddenTabsByRole) || !is_array($hiddenTabsByRole)) {
    $hiddenTabsByRole = [];
    $hiddenRows = Capsule::table('mod_timekeeper_hidden_tabs')->get();
    foreach ($hiddenRows as $row) {
        $rid = (int) $row->role_id;
        $tab = (string) $row->tab_name;
        $hiddenTabsByRole[$rid][] = $tab;
    }
}
?>
<div class="timekeeper-hide-tabs-settings">
    <h4 class="tk-section-title">Hide Menu Tabs by Admin Role</h4>
    <p class="text-muted tk-section-subtitle">
        Tick a box to <strong>hide</strong> a tab for that admin role. By default, all tabs are visible.
    </p>

    <form method="post" data-tk>
        <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="hidemenutabs_save" value="1">

        <!-- BEGIN: Role-centric replacement for table -->
<div class="tk-roles-stack">
  <?php foreach ($roles as $role): ?>
    <?php $rid = (int) $role->id; ?>
    <div class="tk-role-card">
      <div class="tk-role-head">
        <h5 class="tk-role-title"><?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?></h5>
        <span class="tk-role-sub">Tick to hide tabs for this role</span>
      </div>

      <div class="tk-tabs-list">
        <?php foreach ($tabs as $tabKey => $tabLabel):
          $isHidden = isset($hiddenTabsByRole[$rid]) && in_array($tabKey, $hiddenTabsByRole[$rid], true);
        ?>
          <label class="tk-tab-toggle">
            <span class="tk-tab-name"><?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <input
              type="checkbox"
              name="hide_tabs[<?= $rid ?>][]"
              value="<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') ?>"
              <?= $isHidden ? 'checked' : '' ?>>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<style>
  .tk-roles-stack { display:grid; gap:1rem; }
  .tk-role-card { background:#fff; border:1px solid #e5e7eb; border-radius:.9rem; }
  .tk-role-head { padding:.9rem 1rem .25rem 1rem; }
  .tk-role-title { margin:0; font-size:1rem; font-weight:700; color:#0f172a; }
  .tk-role-sub { color:#64748b; font-size:.85rem; }
  .tk-tabs-list { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:.5rem; padding:.75rem 1rem 1rem 1rem; }
  .tk-tab-toggle { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.5rem .6rem; border:1px solid #eef2f7; border-radius:.5rem; }
  .tk-tab-name { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
</style>
<!-- END: Role-centric replacement -->


        <div class="tk-actions">
            <button type="submit" class="btn btn-primary">Save Tab Visibility</button>
        </div>
    </form>
</div>
