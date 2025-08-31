<?php
// modules/addons/timekeeper/includes/helpers/reports_helper.php
declare(strict_types=1);

namespace Timekeeper\Helpers;

final class ReportsHelper
{
    /**
     * List of available reports.
     * Add new reports here (or expose a hook if you want this to be dynamic later).
     */
    public static function availableReports(): array
    {
        $base = dirname(__DIR__, 2); // /modules/addons/timekeeper
        return [
            'timesheet_audit' => [
                'label' => 'Detailed Audit Report',
                'php'   => $base . '/components/report_timesheet_audit.php',
            ],
            'summary' => [
                'label' => 'Summary Report',
                'php'   => $base . '/components/report_summary.php',
            ],
            // Add future reports here...
        ];
    }

    /** Normalize incoming key and ensure it exists in the catalog; fall back to default. */
    public static function resolveReportKey(?string $key, array $available, string $fallback = 'timesheet_audit'): string
    {
        $norm = is_string($key) ? preg_replace('/[^a-z0-9_]/i', '', $key) : '';
        if (!$norm || !array_key_exists($norm, $available)) {
            return $fallback;
        }
        return $norm;
    }

    /** Build left menu HTML for the reports list (keeps existing classes & structure). */
    public static function buildMenu(array $available, string $activeKey, string $baseLink): string
    {
        $out = '<div class="timekeeper-report-menu">' .
               '<div class="timekeeper-report-menu-header">Available Reports</div><ul>';
        foreach ($available as $key => $meta) {
            $active = ($key === $activeKey) ? 'active' : '';
            $url = $baseLink . '&r=' . rawurlencode($key);
            $out .= '<li><a href="' . \Timekeeper\Helpers\CoreHelper::e($url) . '" class="' . $active . '">'
                 . \Timekeeper\Helpers\CoreHelper::e((string)$meta['label'])
                 . '</a></li>';
        }
        $out .= '</ul></div>';
        return $out;
    }

    /** Render the selected report component or an error alert if the file is missing. */
    public static function renderReport(array $reportMeta): string
    {
        $file = (string)($reportMeta['php'] ?? '');
        if (!$file || !is_file($file)) {
            $safe = \Timekeeper\Helpers\CoreHelper::e($file);
            return '<div class="tk-alert tk-alert--error">Report component not found: <code>' . $safe . '</code></div>';
        }

        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $file;
        return (string)ob_get_clean();
    }
}
