<?php
// modules/addons/timekeeper/includes/helpers/core_helper.php
declare(strict_types=1);

namespace Timekeeper\Helpers {
    use WHMCS\Database\Capsule;

    final class CoreHelper
    {
        public const VERSION = '1.0.0';

        /** Parse a CSV of ints into array<int> */
        public static function parseIdList(?string $csv): array
        {
            if (!$csv) return [];
            $out = [];
            foreach (explode(',', $csv) as $p) {
                $v = (int) trim($p);
                if ($v > 0) $out[] = $v;
            }
            return $out;
        }

        /** Read role IDs from mod_timekeeper_permissions by setting_key (with simple cache) */
        public static function rolesFromSetting(string $settingKey): array
        {
            static $cache = [];
            if (isset($cache[$settingKey])) {
                return $cache[$settingKey];
            }

            $csv = Capsule::table('mod_timekeeper_permissions')
                ->where('setting_key', $settingKey)
                ->value('setting_value');

            return $cache[$settingKey] = self::parseIdList($csv);
        }

        /** Safe "has column" that works across MySQL modes */
        public static function hasCol(string $table, string $col): bool
        {
            try {
                return Capsule::schema()->hasColumn($table, $col);
            } catch (\Throwable $e) {
                try {
                    $cols = Capsule::select("SHOW COLUMNS FROM `$table`");
                    foreach ($cols as $c) {
                        $f = is_object($c) ? ($c->Field ?? null) : ($c['Field'] ?? null);
                        if ($f === $col) return true;
                    }
                } catch (\Throwable $e2) {}
                return false;
            }
        }

        /** Read from $_GET with default */
        public static function get(string $key, $default = null)
        {
            return isset($_GET[$key]) ? $_GET[$key] : $default;
        }

        /** Read from $_POST with default */
        public static function post(string $key, $default = null)
        {
            return isset($_POST[$key]) ? $_POST[$key] : $default;
        }

        /** HTML-escape */
        public static function e(string $v): string
        {
            return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        }

/** Validate YYYY-MM-DD date strings */
public static function isValidDate(?string $s): bool
{
    if (!is_string($s)) return false;
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

/**
 * Require a helper file from either of two relative paths under $baseDir.
 * Example: CoreHelper::requireEither($base, '/helpers/core_helper.php', '/includes/helpers/core_helper.php');
 */
public static function requireEither(string $baseDir, string $relA, string $relB): void
{
    $a = $baseDir . $relA;
    $b = $baseDir . $relB;
    if (is_file($a)) { require_once $a; return; }
    if (is_file($b)) { require_once $b; return; }
    throw new \RuntimeException("Missing helper: tried {$a} and {$b}");
}

/**
 * Convenience: load a helper by basename (no extension), trying helpers/ and includes/helpers/.
 * e.g. CoreHelper::requireHelper($base, 'approved_timesheets_helper')
 */
public static function requireHelper(string $baseDir, string $basename): void
{
    self::requireEither($baseDir, '/helpers/' . $basename . '.php', '/includes/helpers/' . $basename . '.php');
}
}
}

namespace {
    /**
     * Normalize/whitelist a page key (used in navigation.php)
     */
    if (!function_exists('tk_normalize_page')) {
        function tk_normalize_page(string $s): string {
$s = preg_replace('/[^a-z0-9_]/i', '', strtolower($s));
            return $s !== '' ? $s : 'dashboard';
        }
    }

    /**
     * Role-based visibility: check if a page key is allowed for a role.
     * Data sources (either/or):
     *  - table: mod_timekeeper_tab_visibility {role_id, page_key, visible}
     *  - settings: mod_timekeeper_settings (setting_key='tab_visibility_json') JSON map
     * Default: allow (true) on missing data or errors.
     */
    if (!function_exists('tk_isPageAllowedForRole')) {
        function tk_isPageAllowedForRole(int $roleId, string $pageKey): bool {
if ($roleId <= 0 || $pageKey === '') return true;

            static $cache = [];
            $ck = $roleId . ':' . $pageKey;
            if (isset($cache[$ck])) return $cache[$ck];

            try {
                // Option A: dedicated visibility table
                if (\WHMCS\Database\Capsule::schema()->hasTable('mod_timekeeper_tab_visibility')) {
                    $row = \WHMCS\Database\Capsule::table('mod_timekeeper_tab_visibility')
                        ->where('role_id', $roleId)
                        ->where('page_key', $pageKey)
                        ->first();
                    if ($row !== null) {
                        return $cache[$ck] = (bool)($row->visible ?? true);
                    }
                }

                // Option B: settings JSON map
                if (\WHMCS\Database\Capsule::schema()->hasTable('mod_timekeeper_settings')) {
                    $json = \WHMCS\Database\Capsule::table('mod_timekeeper_settings')
                        ->where('setting_key', 'tab_visibility_json')
                        ->value('setting_value');
                    if ($json) {
                        $map = json_decode((string)$json, true);
                        if (is_array($map) && isset($map[$roleId][$pageKey])) {
                            return $cache[$ck] = (bool)$map[$roleId][$pageKey];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Swallow errors; allow by default
            }
            return $cache[$ck] = true;
        }
    }
}

if (!function_exists('timekeeperSystemBaseUrl')) {
    /**
     * Prefer SSL URL if defined, else fall back to SystemURL.
     * Returns URL without trailing slash.
     */
    function timekeeperSystemBaseUrl(): string
    {
        $ssl = trim((string) Setting::getValue('SystemSSLURL'));
        $url = $ssl !== '' ? $ssl : (string) Setting::getValue('SystemURL');
        return rtrim($url, '/');
    }
}

if (!function_exists('timekeeperBaseUrl')) {
    /**
     * Public URL to this addon, robust to subfolders like /portal.
     * Example: https://example.com/portal/modules/addons/timekeeper
     */
    function timekeeperBaseUrl(): string
    {
        return timekeeperSystemBaseUrl() . '/modules/addons/timekeeper';
    }
}

if (!function_exists('timekeeperBaseDir')) {
    /**
     * Filesystem path to the addon root (no trailing slash).
     */
    function timekeeperBaseDir(): string
    {
        // helpers may live under /includes/helpers or /helpers — normalize to addon root
        // __DIR__ => .../modules/addons/timekeeper/includes/helpers
        return dirname(dirname(__DIR__)); // -> .../modules/addons/timekeeper
    }
}

if (!function_exists('timekeeperAsset')) {
    /**
     * Build a versioned asset URL (adds ?v=<mtime> when file exists).
     * $path is relative to the addon root, e.g. 'css/timesheet.css' or '/js/settings.js'
     */
    function timekeeperAsset(string $path): string
    {
        $path = ltrim($path, '/');
        $url  = timekeeperBaseUrl() . '/' . $path;

        $file = timekeeperBaseDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (@is_file($file)) {
            $ver = @filemtime($file);
            if ($ver) {
                $sep = (strpos($url, '?') === false) ? '?' : '&';
                $url .= $sep . 'v=' . $ver;
            }
        }
        return $url;
    }
}