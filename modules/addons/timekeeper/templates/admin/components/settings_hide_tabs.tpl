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

        <!-- BEGIN: Role-centric (with Hide All, Count, Search) -->
        <div class="tk-roles-stack">
        <?php foreach ($roles as $role): ?>
            <?php $rid = (int) $role->id; ?>
            <div class="tk-role-card" data-role-id="<?= $rid ?>">
            <div class="tk-role-head">
                <div class="tk-role-head-left">
                <h5 class="tk-role-title"><?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?></h5>
                <span class="tk-role-sub">Tick to <strong>hide</strong> tabs for this role</span>
                </div>
                <div class="tk-role-head-right">
                <label class="tk-role-toggle-all">
                    <input type="checkbox" class="js-role-toggle-all" data-role-id="<?= $rid ?>">
                    <span>Hide all</span>
                </label>
                <span class="tk-role-count" data-role-id="<?= $rid ?>">Hidden: 0 / 0</span>
                </div>
            </div>

            <!-- Per-role search -->
            <div class="tk-role-search">
                <input type="text" class="form-control tk-role-search-input" placeholder="Search tabs…">
            </div>

            <div class="tk-tabs-list">
                <?php foreach ($tabs as $tabKey => $tabLabel):
                $isHidden = isset($hiddenTabsByRole[$rid]) && in_array($tabKey, $hiddenTabsByRole[$rid], true);
                ?>
                <label class="tk-tab-toggle" data-label="<?= htmlspecialchars(mb_strtolower($tabLabel), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="tk-tab-name"><?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <input
                    type="checkbox"
                    name="hide_tabs[<?= $rid ?>][]"
                    value="<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $isHidden ? 'checked' : '' ?>
                    class="js-role-tab"
                    data-role-id="<?= $rid ?>">
                </label>
                <?php endforeach; ?>
            </div>
            </div>
        <?php endforeach; ?>
        </div>
        <!-- END: Role-centric (with Hide All, Count, Search) -->
        <div class="tk-actions">
            <button type="submit" class="btn btn-primary">Save Tab Visibility</button>
        </div>
    </form>
</div>
