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


// ---- Dynamic base URL + asset helper (polyfill if not in core_helper yet) ----
// Preferred helpers (if present in core_helper.php):
//   \Timekeeper\Helpers\timekeeperBaseUrl(): string
//   \Timekeeper\Helpers\timekeeperAsset(string $relPath): string
if (!function_exists('Timekeeper\\Helpers\\timekeeperBaseUrl') || !function_exists('Timekeeper\\Helpers\\timekeeperAsset')) {
    // Local polyfill
    $tkSystemUrl = (function (): string {
        try {
            $ssl = (string) \WHMCS\Config\Setting::getValue('SystemSSLURL');
            $url = $ssl !== '' ? $ssl : (string) \WHMCS\Config\Setting::getValue('SystemURL');
            return rtrim($url, '/');
        } catch (\Throwable $e) {
            return '';
        }
    })();

    $tkBase = ($tkSystemUrl !== '' ? $tkSystemUrl : '') . '/modules/addons/timekeeper';
    $tkBase = rtrim($tkBase, '/');

    // Callable for cache-busted assets, e.g. $tkAsset('css/page.css')
    $tkAsset = function (string $relPath) use ($tkBase, $base): string {
        $rel = ltrim($relPath, '/');
        $url = $tkBase . '/' . $rel;

        $file = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (@is_file($file)) {
            $ver = @filemtime($file);
            if ($ver) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . 'v=' . $ver;
            }
        }
        return $url;
    };
} else {
    // Use canonical helpers if available
    $tkBase  = \Timekeeper\Helpers\timekeeperBaseUrl();
    $tkAsset = '\Timekeeper\Helpers\timekeeperAsset'; // callable
}

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
