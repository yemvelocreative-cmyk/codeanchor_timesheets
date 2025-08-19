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

    <?php
      // Build tab map from controller (fallback if not set)
      $settingsTabs = isset($settingsTabs) && is_array($settingsTabs)
        ? $settingsTabs
        : [
            'cron'      => 'Daily Cron Setup',
            'approval'  => 'Timesheet Settings',
            'hide_tabs' => 'Hide Menu Tabs',
          ];
      $validTabs = array_keys($settingsTabs);
      $tab = (isset($activeTab) && in_array($activeTab, $validTabs, true)) ? $activeTab : 'cron';
    ?>

    <!-- Sidebar rail layout -->
    <div class="tk-rail">
      <!-- Left nav -->
      <aside class="tk-rail__nav" aria-label="Settings sub-navigation">
        <?php foreach ($settingsTabs as $key => $label): ?>
          <a
            class="tk-rail__link <?= $tab === $key ? 'is-active' : '' ?>"
            href="addonmodules.php?module=timekeeper&timekeeperpage=settings&subtab=<?= $key ?>"
            aria-current="<?= $tab === $key ? 'page' : 'false' ?>"
          ><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </aside>

      <!-- Right content -->
      <div class="tk-rail__content tk-card tk-card--padded">
        <?php
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
</div>
