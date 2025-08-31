<?php
if (!defined("WHMCS")) { die("Access Denied"); }

// --- Load helpers (supports helpers/ or includes/helpers/) ---
$base = dirname(__DIR__); // -> /modules/addons/timekeeper
$try = function (string $relA, string $relB) use ($base) {
    $a = $base . $relA; $b = $base . $relB;
    if (is_file($a)) { require_once $a; return; }
    if (is_file($b)) { require_once $b; return; }
    throw new \RuntimeException("Missing helper: tried {$a} and {$b}");
};
$try('/helpers/core_helper.php', '/includes/helpers/core_helper.php');
$try('/helpers/reports_helper.php', '/includes/helpers/reports_helper.php');

use Timekeeper\Helpers\CoreHelper as CoreH;
use Timekeeper\Helpers\ReportsHelper as RepH;

// Catalog + active key
$availableReports = RepH::availableReports();
$reportKey        = RepH::resolveReportKey($_GET['r'] ?? null, $availableReports);

// URLs
$baseLink = 'addonmodules.php?module=timekeeper&timekeeperpage=reports';

// Build menu & content
$menuHtml    = RepH::buildMenu($availableReports, $reportKey, $baseLink);
$reportMeta  = $availableReports[$reportKey];
$contentHtml = RepH::renderReport($reportMeta);
?>
<div class="timekeeper-fullwidth timekeeper-root">
  <link rel="stylesheet" href="/modules/addons/timekeeper/css/reports.css">

  <h2>Reports</h2>

  <div class="timekeeper-report-container">
    <!-- Left Menu -->
    <?= $menuHtml ?>

    <!-- Report Content -->
    <div class="timekeeper-report-content">
      <?= $contentHtml ?>
    </div>
  </div>
</div>

<!-- Page JS (root-relative so it works regardless of nesting) -->
<script src="/modules/addons/timekeeper/js/reports.js"></script>
