<?php
// modules/addons/timekeeper/helpers/core_helper.php

declare(strict_types=1);

namespace Timekeeper\Helpers;

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

    /** Read role IDs from mod_timekeeper_permissions by setting_key */
    public static function rolesFromSetting(string $settingKey): array
    {
        // Simple in-process cache
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
}

// —— Global helpers used by navigation/routing ————————————————
namespace {
    /**
     * Normalize/whitelist a page key (global function used in navigation.php).
     */
    if (!function_exists('tk_normalize_page')) {
        function tk_normalize_page(string $s): string {
            $s = preg_replace('/[^a-z0-9_]/i', '', strtolower($s));
            return $s !== '' ? $s : 'dashboard';
        }
    }

    /**
     * Check if a page is visible for a given role.
     * Looks for either:
     *  - table: mod_timekeeper_tab_visibility {role_id, page_key, visible}
     *  - settings: mod_timekeeper_settings setting_key='tab_visibility_json' (JSON map)
     * Falls back to TRUE on missing data/errors.
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
                // On any failure, allow by default
            }

            return $cache[$ck] = true;
        }
    }
}
