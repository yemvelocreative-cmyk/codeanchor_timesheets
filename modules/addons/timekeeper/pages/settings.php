<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('Access Denied'); }

/* -------------------------------
   CSRF (module-local, session-based)
-------------------------------- */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['timekeeper_csrf'])) {
    $_SESSION['timekeeper_csrf'] = bin2hex(random_bytes(32));
}
$tkCsrf = $_SESSION['timekeeper_csrf'];

function timekeeper_require_csrf() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $posted = $_POST['tk_csrf'] ?? '';
        $valid  = isset($_SESSION['timekeeper_csrf']) && hash_equals($_SESSION['timekeeper_csrf'], $posted);
        if (!$valid) {
            http_response_code(400);
            die('Invalid request token.');
        }
    }
}

/* -------------------------------
   Assets (prefer central loader in timekeeper.php)
-------------------------------- */
// If you prefer to load here, uncomment the next 3 lines.
// echo '<link rel="stylesheet" href="../modules/addons/timekeeper/css/settings_tabs.css" />';
// echo '<link rel="stylesheet" href="../modules/addons/timekeeper/css/settings.css" />';
// echo '<script src="../modules/addons/timekeeper/js/settings.js" defer></script>';

/* -------------------------------
   Settings sub-tabs
-------------------------------- */
$settingsTabs = [
    'cron'      => 'Daily Cron Setup',
    'approval'  => 'Timesheet Settings',
    'hide_tabs' => 'Hide Menu Tabs',
];

// Selected sub-tab (default cron)
$activeTab = (isset($_GET['subtab']) && array_key_exists($_GET['subtab'], $settingsTabs))
    ? $_GET['subtab']
    : 'cron';

// Success flags consumed by wrapper
$success          = (isset($_GET['success']) && $_GET['success'] == '1');
$approval_success = (isset($_GET['approval_success']) && $_GET['approval_success'] == '1');
$tab_visibility   = (isset($_GET['tab_visibility']) && $_GET['tab_visibility'] == '1');

/* -------------------------------
   Render tab menu
-------------------------------- */
$settingsTabs = [
  'cron'      => 'Daily Cron Setup',
  'approval'  => 'Timesheet Settings',
  'hide_tabs' => 'Hide Menu Tabs',
];
<nav class="tk-subnav tk-subnav--segmented" role="tablist" aria-label="Settings sub-navigation">
  <?php foreach ($settingsTabs as $tabKey => $tabLabel): ?>
    <a
      class="tk-seg <?= $activeTab === $tabKey ? 'is-active' : '' ?>"
      href="addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=<?= $tabKey ?>"
      role="tab"
      aria-selected="<?= $activeTab === $tabKey ? 'true' : 'false' ?>"
      tabindex="<?= $activeTab === $tabKey ? '0' : '-1' ?>"
    ><?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?></a>
  <?php endforeach; ?>
</nav>


/* -------------------------------
   Tab-specific controllers
   (prepare variables; include wrapper ONCE below)
-------------------------------- */
switch ($activeTab) {
    /* ==== CRON TAB ==== */
    case 'cron': {
        $daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            timekeeper_require_csrf();

            // Save Cron Days
            $cronDays = (isset($_POST['cron_days']) && is_array($_POST['cron_days'])) ? $_POST['cron_days'] : [];
            $cronDaysSanitized = array_values(array_intersect($cronDays, $daysOfWeek));
            foreach ($daysOfWeek as $day) {
                $key = 'cron_' . $day;
                $status = in_array($day, $cronDaysSanitized, true) ? 'active' : 'inactive';
                Capsule::table('mod_timekeeper_permissions')->updateOrInsert(
                    ['setting_key' => $key, 'role_id' => 0],
                    ['setting_value' => $status]
                );
            }

            // Save Assigned Users
            if (array_key_exists('cron_users', $_POST)) {
                $postedUsers = is_array($_POST['cron_users']) ? array_map('intval', $_POST['cron_users']) : [];
                $currentAssigned = Capsule::table('mod_timekeeper_assigned_users')->pluck('admin_id')->toArray();
                $currentAssigned = array_map('intval', $currentAssigned);

                $toAdd    = array_diff($postedUsers, $currentAssigned);
                $toRemove = array_diff($currentAssigned, $postedUsers);

                if (!empty($toAdd)) {
                    foreach ($toAdd as $adminId) {
                        Capsule::table('mod_timekeeper_assigned_users')->insert(['admin_id' => (int)$adminId]);
                    }
                }
                if (!empty($toRemove)) {
                    Capsule::table('mod_timekeeper_assigned_users')->whereIn('admin_id', $toRemove)->delete();
                }
            }

            $redir = 'addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=cron&success=1';
            if (!headers_sent()) { header('Location: ' . $redir); exit; }
            echo '<script>window.location.replace(' . json_encode($redir) . ');</script>'; return;
        }

        // Load current day statuses (role_id = 0)
        $cronKeys = array_map(fn($d) => 'cron_' . $d, $daysOfWeek);
        $currentCronRows = Capsule::table('mod_timekeeper_permissions')
            ->where('role_id', 0)
            ->whereIn('setting_key', $cronKeys)
            ->get();

        $currentCronDays = [];
        foreach ($currentCronRows as $row) {
            $currentCronDays[$row->setting_key] = $row->setting_value;
        }

        // Load all active admins
        $allAdmins = Capsule::table('tbladmins')
            ->where('disabled', 0)
            ->orderBy('firstname')
            ->get();

        // Load assigned users
        $cronUsers = Capsule::table('mod_timekeeper_assigned_users')->pluck('admin_id')->toArray();
        $cronUsers = array_map('intval', $cronUsers);

        // Expose CSRF in template
        $tkCsrf = $_SESSION['timekeeper_csrf'];
        break;
    }

    /* ==== TIMESHEET SETTINGS (APPROVAL) TAB ==== */
    case 'approval': {
        $roles = Capsule::table('tbladminroles')->orderBy('name')->get();

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            timekeeper_require_csrf();

            // Validate role IDs
            $validRoleIds = Capsule::table('tbladminroles')->pluck('id')->toArray();
            $validRoleIds = array_map('intval', $validRoleIds);

            // Save "View" permissions
            if (isset($_POST['pending_timesheets_roles'])) {
                $selectedRoles = array_map('intval', (array)$_POST['pending_timesheets_roles']);
                $selectedRoles = array_values(array_intersect($selectedRoles, $validRoleIds));
                $roleList = implode(',', $selectedRoles);

                Capsule::table('mod_timekeeper_permissions')->updateOrInsert(
                    ['setting_key' => 'permission_pending_timesheets_view_all', 'role_id' => 0],
                    ['setting_value' => $roleList]
                );

                $redir = 'addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=approval&success=1';
                if (!headers_sent()) { header('Location: ' . $redir); exit; }
                echo '<script>window.location.replace(' . json_encode($redir) . ');</script>'; return;
            }

            // Save "Approve" permissions and Validate Time Spent
            if (isset($_POST['pending_timesheets_approval_roles']) || array_key_exists('unbilled_time_validate_min', $_POST)) {
                $selectedApprovalRoles = isset($_POST['pending_timesheets_approval_roles'])
                    ? array_map('intval', (array)$_POST['pending_timesheets_approval_roles'])
                    : [];
                $selectedApprovalRoles = array_values(array_intersect($selectedApprovalRoles, $validRoleIds));
                $approvalRoleList = implode(',', $selectedApprovalRoles);

                Capsule::table('mod_timekeeper_permissions')->updateOrInsert(
                    ['setting_key' => 'permission_pending_timesheets_approve', 'role_id' => 0],
                    ['setting_value' => $approvalRoleList]
                );

                if (array_key_exists('unbilled_time_validate_min', $_POST)) {
                    $raw = trim((string)($_POST['unbilled_time_validate_min'] ?? ''));
                    $minTime = ($raw === '') ? 0.0 : (is_numeric($raw) ? max(0, (float)$raw) : 0.0);
                    Capsule::table('mod_timekeeper_permissions')->updateOrInsert(
                        ['setting_key' => 'unbilled_time_validate_min', 'role_id' => 0],
                        ['setting_value' => (string)$minTime] // never NULL
                    );
                }
                $redir = 'addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=approval&approval_success=1';
                if (!headers_sent()) { header('Location: ' . $redir); exit; }
                echo '<script>window.location.replace(' . json_encode($redir) . ');</script>'; return;
            }
        }

        // Fetch saved roles/settings
        $saved = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', 'permission_pending_timesheets_view_all')
            ->where('role_id', 0)
            ->value('setting_value');
        $allowedRoles = ($saved !== null && $saved !== '') ? array_map('intval', explode(',', $saved)) : [];

        $savedApproval = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', 'permission_pending_timesheets_approve')
            ->where('role_id', 0)
            ->value('setting_value');
        $allowedApprovalRoles = ($savedApproval !== null && $savedApproval !== '') ? array_map('intval', explode(',', $savedApproval)) : [];

        $unbilledTimeValidateMin = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', 'unbilled_time_validate_min')
            ->where('role_id', 0)
            ->value('setting_value');

        $tkCsrf = $_SESSION['timekeeper_csrf'];
        break;
    }

    /* ==== HIDE TABS (RBAC) TAB ==== */
    case 'hide_tabs': {
        // Tabs that can be hidden (page keys must match router keys)
        $tabs = [
            'dashboard'           => 'Dashboard',
            'timesheet'           => 'Timesheet',
            'pending_timesheets'  => 'Pending Timesheets',
            'approved_timesheets' => 'Approved Timesheets',
            'departments'         => 'Departments',
            'task_categories'     => 'Task Categories',
            'reports'             => 'Reports',
            'settings'            => 'Settings',
        ];

        // Load roles
        $roles = Capsule::table('tbladminroles')->select('id','name')->orderBy('name','asc')->get();

        // Load current hidden map from settings JSON
        $hiddenMap = tk_getHiddenPagesByRole();                  // ["1" => ["settings",...], ...]
        $hiddenTabsByRole = [];
        foreach ($hiddenMap as $ridStr => $pages) {
            $hiddenTabsByRole[(int)$ridStr] = array_values(array_map('tk_normalize_page', (array)$pages));
        }

        // Save
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['hidemenutabs_save'] ?? '') === '1') {
            timekeeper_require_csrf();

            $incoming = $_POST['hide_tabs'] ?? [];               // hide_tabs[<roleId>][] = <tabKey>
            $normalized = [];

            foreach ($roles as $r) {
                $rid = (int)$r->id;
                $postedForRole = $incoming[$rid] ?? [];
                $clean = [];
                foreach ((array)$postedForRole as $tabKey) {
                    $key = tk_normalize_page((string)$tabKey);
                    if (array_key_exists($key, $tabs) && !in_array($key, $clean, true)) {
                        $clean[] = $key;
                    }
                }
                $normalized[(string)$rid] = $clean;
            }

            $ok = tk_saveHiddenPagesByRole($normalized);
            $tab_visibility = $ok ? '1' : '0';

            $redir = 'addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=hide_tabs';
            if (!headers_sent()) { header('Location: ' . $redir); exit; }
            echo '<script>window.location.replace(' . json_encode($redir) . ');</script>'; return;
        }

        $tkCsrf = $_SESSION['timekeeper_csrf'];
        break;
    }
}

/* -------------------------------
   Include wrapper ONCE
   (The wrapper picks the component via $activeTab)
-------------------------------- */
include __DIR__ . '/../templates/admin/settings.tpl';
