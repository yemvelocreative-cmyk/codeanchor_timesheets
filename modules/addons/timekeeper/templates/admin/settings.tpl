<?php if (!defined('WHMCS')) { die('Access Denied'); } ?>
<div class="timekeeper-root">
  <div class="container mt-4">

    <!-- Page Header -->
    <div class="tk-page-header">
      <div class="tk-page-title">
        <h2 class="tk-h2">Timekeeper Settings</h2>
        <p class="tk-subtitle">Configure cron, permissions, and menu visibility.</p>
      </div>
    </div>

    <!-- Flash messages -->
    <?php if (!empty($success)): ?>
      <div class="alert alert-success">Settings saved successfully.</div>
    <?php endif; ?>

    <?php if (!empty($approval_success)): ?>
      <div class="alert alert-success">Approval settings saved.</div>
    <?php endif; ?>

    <?php if (!empty($tab_visibility)): ?>
      <div class="alert alert-success">Tab visibility updated.</div>
    <?php endif; ?>

    <!-- The tab nav itself is rendered by settings.php above this template -->
    <div class="tk-card tk-card--padded tk-mt">
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
</div>
