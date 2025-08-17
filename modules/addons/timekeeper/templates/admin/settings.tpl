<?php if (!defined('WHMCS')) { die('Access Denied'); } ?>
<div class="timekeeper-root">
    <div class="container mt-4">
        <h2>Timekeeper Settings</h2>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">Settings saved successfully.</div>
        <?php endif; ?>

        <?php if (!empty($approval_success)): ?>
            <div class="alert alert-success">Approval settings saved.</div>
        <?php endif; ?>

        <?php if (!empty($tab_visibility)): ?>
            <div class="alert alert-success">Tab visibility updated.</div>
        <?php endif; ?>

        <?php
        // Whitelist & fallback (redundant with controller, but safe)
        $validTabs = ['cron', 'approval', 'hide_tabs'];
        $tab = (isset($activeTab) && in_array($activeTab, $validTabs, true)) ? $activeTab : 'cron';

        switch ($tab) {
            case 'approval':
                include __DIR__ . '/components/settings_approvals.tpl';
                break;

            case 'hide_tabs':
                include __DIR__ . '/components/settings_hide_tabs.tpl';
                break;

            case 'cron':
            default:
                include __DIR__ . '/components/settings_cron.tpl';
                break;
        }
        ?>
    </div>
</div>
