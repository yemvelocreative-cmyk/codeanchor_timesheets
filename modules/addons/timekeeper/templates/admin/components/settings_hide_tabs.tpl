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
<div class="timekeeper-hide-tabs-settings mb-4">
    <h4>Hide Menu Tabs by Admin Role</h4>
    <p class="text-muted mb-3">
        Tick a box to <strong>hide</strong> a tab for that admin role. By default, all tabs are visible.
    </p>

    <form method="post" data-tk>
        <input type="hidden" name="tk_csrf" value="<?= htmlspecialchars($tkCsrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="hidemenutabs_save" value="1">

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Admin Role</th>
                        <?php foreach ($tabs as $tabKey => $tabLabel): ?>
                            <th class="text-center"><?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php foreach ($tabs as $tabKey => $tabLabel): ?>
                                <?php
                                    $rid = (int) $role->id;
                                    $isHidden = isset($hiddenTabsByRole[$rid])
                                        && in_array($tabKey, $hiddenTabsByRole[$rid], true);
                                ?>
                                <td class="text-center align-middle">
                                    <input
                                        type="checkbox"
                                        name="hide_tabs[<?= $rid ?>][]"
                                        value="<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $isHidden ? 'checked' : '' ?>
                                        aria-label="Hide <?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?> for role <?= htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" class="btn btn-primary">Save Tab Visibility</button>
    </form>
</div>
