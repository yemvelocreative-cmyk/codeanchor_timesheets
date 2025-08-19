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

        <!-- BEGIN: Accordion replacement for table -->
<div class="tk-hide-acc-wrapper">

  <!-- Search -->
  <div class="tk-acc-search mb-3">
    <input type="text" class="form-control" id="tk-acc-search" placeholder="Search tabs…">
  </div>

        <!-- Accordion -->
        <div class="tk-acc" id="tk-acc">
            <?php foreach ($tabs as $tabKey => $tabLabel): ?>
            <div class="tk-acc-item" data-label="<?= htmlspecialchars(mb_strtolower($tabLabel), ENT_QUOTES, 'UTF-8') ?>">
                <button type="button" class="tk-acc-toggle" aria-expanded="false">
                <span class="tk-acc-title"><?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="tk-acc-icon">▸</span>
                </button>
                <div class="tk-acc-panel" hidden>
                <div class="tk-acc-grid">
                    <?php foreach ($roles as $role): 
                    $rid = (int) $role->id;
                    $isHidden = isset($hiddenTabsByRole[$rid]) && in_array($tabKey, $hiddenTabsByRole[$rid], true);
                    ?>
                    <label class="tk-acc-toggle-row">
                        <input
                        type="checkbox"
                        name="hide_tabs[<?= $rid ?>][]"
                        value="<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $isHidden ? 'checked' : '' ?>>
                        <span class="tk-acc-role"><?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="tk-acc-state" aria-hidden="true"><?= $isHidden ? 'Hidden' : 'Visible' ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        </div>

        <style>
        .tk-acc { display:grid; gap:.75rem; }
        .tk-acc-item { border:1px solid #e5e7eb; border-radius:.75rem; background:#fff; }
        .tk-acc-toggle { width:100%; border:0; background:transparent; padding:.9rem 1rem;
            display:flex; justify-content:space-between; align-items:center; font-weight:600; cursor:pointer; }
        .tk-acc-icon { transition: transform .2s ease; }
        .tk-acc-item[aria-expanded="true"] .tk-acc-icon { transform:rotate(90deg); }
        .tk-acc-panel { border-top:1px solid #f1f5f9; padding:.75rem 1rem 1rem; }
        .tk-acc-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:.5rem .75rem; }
        .tk-acc-toggle-row { display:flex; align-items:center; gap:.5rem; justify-content:space-between; padding:.5rem .6rem; border:1px solid #eef2f7; border-radius:.5rem; }
        .tk-acc-role { flex:1; margin-left:.25rem; }
        .tk-acc-state { color:#64748b; font-size:.875rem; }
        </style>

        <script>
        (function(){
            const acc = document.getElementById('tk-acc');
            acc?.addEventListener('click', function(e){
            const btn = e.target.closest('.tk-acc-toggle');
            if(!btn) return;
            const item = btn.parentElement;
            const panel = item.querySelector('.tk-acc-panel');
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', String(!expanded));
            item.setAttribute('aria-expanded', String(!expanded));
            panel.hidden = expanded;
            });

            const search = document.getElementById('tk-acc-search');
            search?.addEventListener('input', function(){
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('.tk-acc-item').forEach(item=>{
                const label = item.getAttribute('data-label') || '';
                item.style.display = label.includes(q) ? '' : 'none';
            });
            });
        })();
        </script>
        <!-- END: Accordion replacement -->

        <div class="tk-actions">
            <button type="submit" class="btn btn-primary">Save Tab Visibility</button>
        </div>
    </form>
</div>
