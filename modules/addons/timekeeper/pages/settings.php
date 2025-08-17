<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("Access Denied");
}

// -------------------------------
// CSRF token bootstrap (module-local, session-based)
// -------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
if (empty($_SESSION['timekeeper_csrf'])) {
    $_SESSION['timekeeper_csrf'] = bin2hex(random_bytes(32));
}
$tkCsrf = $_SESSION['timekeeper_csrf'];

// Helper to validate POSTs
function timekeeper_require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted = $_POST['tk_csrf'] ?? '';
        $valid  = isset($_SESSION['timekeeper_csrf']) && hash_equals($_SESSION['timekeeper_csrf'], $posted);
        if (!$valid) {
            http_response_code(400);
            die('Invalid request token.');
        }
    }
}

// -------------------------------
// Assets
// -------------------------------
echo '<link rel="stylesheet" href="../modules/addons/timekeeper/css/settings_tabs.css" />';
echo '<link rel="stylesheet" href="../modules/addons/timekeeper/css/settings.css" />'; // page styles
echo '<script src="../modules/addons/timekeeper/js/settings.js" defer></script>';

// Define settings tabs (keys are used in the URL)
$settingsTabs = [
    'cron'      => 'Daily Cron Setup',
    'approval'  => 'Timesheet Settings',
    'hide_tabs' => 'Hide Menu Tabs',
];

// Detect selected sub-tab (default to 'cron') and restrict to known keys
$activeTab = (isset($_GET['subtab']) && array_key_exists($_GET['subtab'], $settingsTabs))
    ? $_GET['subtab']
    : 'cron';

// Success flags (used by templates/settings.tpl)
$success          = isset($_GET['success']) && $_GET['success'] == '1';
$approval_success = isset($_GET['approval_success']) && $_GET['approval_success'] == '1';
$tab_visibility   = isset($_GET['tab_visibility']) && $_GET['tab_visibility'] == '1';

// --- TAB MENU OUTPUT ---
echo '<ul class="timekeeper-settings-tabs">';
foreach ($settingsTabs as $tabKey => $tabLabel) {
    $isActive = ($tabKey === $activeTab) ? 'active' : '';
    echo "<li class=\"{$isActive}\">
        <a href=\"addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab={$tabKey}\">" . htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') . "</a>
    </li>";
}
echo '</ul>';

// --- TAB-SPECIFIC LOGIC & DATA LOADING ---
switch ($activeTab) {
    case 'cron':
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            timekeeper_require_csrf();

            // Save Cron Days
            $cronDays = isset($_POST['cron_days']) && is_array($_POST['cron_days']) ? $_POST['cron_days'] : [];
            $cronDaysSanitized = array_values(array_intersect($cronDays, $daysOfWeek));

            foreach ($daysOfWeek as $day) {
                $key = 'cron_' . $day;
                $status = in_array($day, $cronDaysSanitized, true) ? 'active' : 'inactive';
                Capsule::table('mod_timekeeper_permissions')->updateOrInsert(
                    ['setting_key' => $key, 'role_id' => 0],   // target role_id=0 explicitly
                    ['setting_value' => $status]
                );
            }

            // Save Assigned Users
            if (array_key_exists('cron_users', $_POST)) {
                $postedUsers = is_array($_POST['cron_users']) ? array_map('intval', $_POST['cron_users']) : [];
                $currentAssigned = Capsule::table('mod_timekeeper_assigned_users')->pluck('admin_id')->toArray();
                $currentAssigned = array_map('intval', $currentAssigned);

                $toAdd = array_diff($postedUsers, $currentAssigned);
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

            header("Location: addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=cron&success=1");
            exit;
        }

        // Load current day statuses (role_id = 0)
        $currentCronRows = Capsule::table('mod_timekeeper_permissions')
            ->where('role_id', 0)
            ->whereIn('setting_key', array_map(fn($d) => 'cron_' . $d, $daysOfWeek))
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

        // Load assigned users (cast to int)
        $cronUsers = Capsule::table('mod_timekeeper_assigned_users')->pluck('admin_id')->toArray();
        $cronUsers = array_map('intval', $cronUsers);

        // CSRF for template scope
        $tkCsrf = $_SESSION['timekeeper_csrf'];

        // Unified template include
        include __DIR__ . '/../templates/settings.tpl';
        break;

    case 'approval':
        $roles = Capsule::table('tbladminroles')->orderBy('name')->get();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            timekeeper_require_csrf();

            // Validate role IDs against DB (prevents junk input)
            $validRoleIds = Capsule::table('tbladminroles')->pluck('id')->toArray();
            $validRoleIds = array_map('intval', $validRoleIds);

            // Save "View" permissions
            if (isset($_POST['pending_timesheets_roles'])) {
                $selectedRoles = array_map('intval', (array) $_POST['pending_timesheets_roles']);
                $selectedRoles = array_values(array_intersect($selectedRoles, $validRoleIds));
                $roleList = implode(',', $selectedRoles);

                Capsule::table('mod_timekeeper_permissions')->updateOrInsert(
                    ['setting_key' => 'permission_pending_timesheets_view_all', 'role_id' => 0],
                    ['setting_value' => $roleList]
                );

                header("Location: addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=approval&success=1");
                exit;
            }

            // Save "Approve" permissions and Validate Time Spent
            if (isset($_POST['pending_timesheets_approval_roles']) || isset($_POST['unbilled_time_validate_min'])) {
                $selectedApprovalRoles = isset($_POST['pending_timesheets_approval_roles'])
                    ? array_map('intval', (array) $_POST['pending_timesheets_approval_roles'])
                    : [];
                $selectedApprovalRoles = array_values(array_intersect($selectedApprovalRoles, $validRoleIds));
                $approvalRoleList = implode(',', $selectedApprovalRoles);

                Capsule::table('mod_timekeeper_permissions')->updateOrInsert(
                    ['setting_key' => 'permission_pending_timesheets_approve', 'role_id' => 0],
                    ['setting_value' => $approvalRoleList]
                );

                // Save minimum unbilled time validate setting
                if (array_key_exists('unbilled_time_validate_min', $_POST)) {
                    $minTime = $_POST['unbilled_time_validate_min'];
                    $minTime = is_numeric($minTime) ? (float) $minTime : null;

                    Capsule::table('mod_timekeeper_permissions')->updateOrInsert(
                        ['setting_key' => 'unbilled_time_validate_min', 'role_id' => 0],
                        ['setting_value' => $minTime]
                    );
                }

                header("Location: addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=approval&approval_success=1");
                exit;
            }
        }

        // Fetch saved roles/settings (cast to int arrays for template)
        $saved = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', 'permission_pending_timesheets_view_all')
            ->where('role_id', 0)
            ->value('setting_value');
        $allowedRoles = $saved !== null && $saved !== '' ? array_map('intval', explode(',', $saved)) : [];

        $savedApproval = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', 'permission_pending_timesheets_approve')
            ->where('role_id', 0)
            ->value('setting_value');
        $allowedApprovalRoles = $savedApproval !== null && $savedApproval !== '' ? array_map('intval', explode(',', $savedApproval)) : [];

        $unbilledTimeValidateMin = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', 'unbilled_time_validate_min')
            ->where('role_id', 0)
            ->value('setting_value');

        $tkCsrf = $_SESSION['timekeeper_csrf'];
        include __DIR__ . '/../templates/settings.tpl';
        break;

    case 'hide_tabs':
        echo '<link rel="stylesheet" href="../modules/addons/timekeeper/css/settings_hide_tabs.css" />';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hidemenutabs_save'])) {
            timekeeper_require_csrf();

            // Define valid tabs here for validation
            $tabs = [
                'departments'        => 'Departments',
                'task_categories'    => 'Task Categories',
                'timesheets'         => 'Timesheets',
                'pending_timesheets' => 'Pending Timesheets',
                'reports'            => 'Reports',
                'settings'           => 'Settings',
            ];
            $validTabs    = array_keys($tabs);
            $validRoleIds = Capsule::table('tbladminroles')->pluck('id')->toArray();
            $validRoleIds = array_map('intval', $validRoleIds);

            Capsule::table('mod_timekeeper_hidden_tabs')->truncate();

            foreach (($_POST['hide_tabs'] ?? []) as $roleId => $tabList) {
                $roleId = (int) $roleId;
                if (!in_array($roleId, $validRoleIds, true) || !is_array($tabList)) {
                    continue;
                }
                $tabList = array_values(array_intersect($tabList, $validTabs));
                foreach ($tabList as $tabKey) {
                    Capsule::table('mod_timekeeper_hidden_tabs')->insert([
                        'role_id'  => $roleId,
                        'tab_name' => $tabKey,
                    ]);
                }
            }

            header("Location: addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=hide_tabs&tab_visibility=1");
            exit;
        }

        $tkCsrf = $_SESSION['timekeeper_csrf'];
        include __DIR__ . '/../templates/settings.tpl';
        break;

    default:
        include __DIR__ . '/../templates/settings.tpl';
        break;
}
