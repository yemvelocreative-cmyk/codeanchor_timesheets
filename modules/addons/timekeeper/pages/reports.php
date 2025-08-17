<?php
if (!defined("WHMCS")) {
    die("Access Denied");
}

/**
 * Reports Container
 * - Left nav of available reports
 * - Includes selected report component (no Smarty)
 */

$availableReports = [
    'timesheet_audit' => [
        'label' => 'Detailed Audit Report',
        'php'   => __DIR__ . '/../components/report_timesheet_audit.php',
    ],
    'summary' => [
        'label' => 'Summary Report',
        'php'   => __DIR__ . '/../components/report_summary.php',
    ],
    // Add future reports here...
];

$reportKey = isset($_GET['r']) ? preg_replace('/[^a-z0-9_]/i', '', $_GET['r']) : 'timesheet_audit';
if (!array_key_exists($reportKey, $availableReports)) {
    $reportKey = 'timesheet_audit';
}
$reportMeta = $availableReports[$reportKey];
?>
<div class="timekeeper-fullwidth timekeeper-root">
  <link rel="stylesheet" href="/modules/addons/timekeeper/css/reports.css">

  <h2>Reports</h2>

  <div class="timekeeper-report-container">
    <!-- Left Menu -->
    <div class="timekeeper-report-menu">
      <div class="timekeeper-report-menu-header">Available Reports</div>
      <ul>
        <?php foreach ($availableReports as $key => $meta):
          $active = ($key === $reportKey);
          $url = 'addonmodules.php?module=timekeeper&timekeeperpage=reports&r=' . urlencode($key);
        ?>
          <li>
            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" class="<?= $active ? 'active' : '' ?>">
              <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Report Content -->
    <div class="timekeeper-report-content">
      <?php
      if (empty($reportMeta['php']) || !file_exists($reportMeta['php'])) {
          echo '<div style="background:#ffecec;border:1px solid #f5c2c2;padding:10px;">
                  Report component not found: <code>' . htmlspecialchars((string)($reportMeta['php'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code>
                </div>';
      } else {
          include $reportMeta['php'];
      }
      ?>
    </div>
  </div>
</div>

<!-- Page JS (root-relative so it works regardless of nesting) -->
<script src="/modules/addons/timekeeper/js/reports.js"></script>
