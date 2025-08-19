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

        <!-- BEGIN: Matrix replacement for table -->
<div class="tk-matrix">

  <!-- Header row -->
  <div class="tk-mx-row tk-mx-header">
    <div class="tk-mx-cell tk-mx-tabcol">Tab</div>
    <?php foreach ($roles as $role): ?>
      <div class="tk-mx-cell tk-mx-rolecol">
        <div class="tk-mx-rolehead">
          <span><?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?></span>
          <!-- Optional: role toggle-all (client-side only) -->
          <label class="tk-mx-head-toggle">
            <input type="checkbox" class="js-role-toggle-all" data-role-id="<?= (int)$role->id ?>">
            <span>All</span>
          </label>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Rows -->
  <?php foreach ($tabs as $tabKey => $tabLabel): ?>
    <div class="tk-mx-row">
      <div class="tk-mx-cell tk-mx-tabcol">
        <span class="tk-mx-tablabel"><?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <?php foreach ($roles as $role):
        $rid = (int) $role->id;
        $isHidden = isset($hiddenTabsByRole[$rid]) && in_array($tabKey, $hiddenTabsByRole[$rid], true);
      ?>
        <div class="tk-mx-cell tk-mx-rolecol">
          <label class="tk-mx-toggle">
            <input
              type="checkbox"
              name="hide_tabs[<?= $rid ?>][]"
              value="<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') ?>"
              <?= $isHidden ? 'checked' : '' ?>
              data-role-id="<?= $rid ?>">
            <span class="tk-mx-toggle-fx" aria-hidden="true"></span>
          </label>
        </div>
      <?php endforeach; ?>

    </div>
  <?php endforeach; ?>

</div>

<style>
  .tk-matrix { display:grid; gap:.5rem; }
  .tk-mx-row { display:grid; grid-template-columns: 1.2fr repeat(var(--tk-role-cols,3), 1fr); align-items:center; }
  .tk-mx-cell { padding:.6rem .75rem; background:#fff; border:1px solid #e5e7eb; }
  .tk-mx-header { font-weight:600; color:#0f172a; }
  .tk-mx-tabcol { border-radius:.6rem 0 0 .6rem; }
  .tk-mx-row > .tk-mx-cell:last-child { border-radius:0 .6rem .6rem 0; }
  .tk-mx-rolehead { display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
  .tk-mx-head-toggle { display:flex; align-items:center; gap:.35rem; font-weight:500; }
  .tk-mx-tablabel { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; }
  .tk-mx-toggle { display:flex; justify-content:center; align-items:center; width:100%; }
  .tk-mx-toggle input { width:18px; height:18px; }
  @media (max-width: 900px){
    .tk-mx-row { grid-template-columns: 1fr 1fr; }
    .tk-mx-header { display:none; }
    .tk-mx-tabcol { border-radius:.6rem; }
  }
</style>

<script>
  (function(){
    // reflect number of roles in the CSS grid
    const roleCount = <?= count($roles) ?>;
    document.querySelectorAll('.tk-matrix').forEach(m => m.style.setProperty('--tk-role-cols', roleCount));

    // role "toggle all" (client-side only)
    document.querySelectorAll('.js-role-toggle-all').forEach(headToggle=>{
      headToggle.addEventListener('change', function(){
        const rid = this.getAttribute('data-role-id');
        const boxes = document.querySelectorAll('input[type="checkbox"][data-role-id="'+rid+'"]');
        boxes.forEach(b => { b.checked = headToggle.checked; });
      });
    });
  })();
</script>
<!-- END: Matrix replacement -->


        <div class="tk-actions">
            <button type="submit" class="btn btn-primary">Save Tab Visibility</button>
        </div>
    </form>
</div>
