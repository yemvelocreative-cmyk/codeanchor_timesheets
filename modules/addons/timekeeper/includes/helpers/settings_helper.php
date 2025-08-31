<?php
// modules/addons/timekeeper/includes/helpers/settings_helper.php
declare(strict_types=1);

namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

final class SettingsHelper
{
    /** Ensure CSRF token exists; return token string */
    public static function initCsrf(): string
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) { @session_start(); }
        if (empty($_SESSION['timekeeper_csrf'])) {
            $_SESSION['timekeeper_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['timekeeper_csrf'];
    }

    /** Enforce CSRF on POST requests */
    public static function requireCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $posted = (string)($_POST['tk_csrf'] ?? '');
            $valid = isset($_SESSION['timekeeper_csrf']) && hash_equals($_SESSION['timekeeper_csrf'], $posted);
            if (!$valid) {
                http_response_code(400);
                die('Invalid request token.');
            }
        }
    }

    /**
     * Load hidden tabs by role map.
     * Preferred source: mod_timekeeper_settings(setting_key='tab_visibility_json')
     * Fallback: mod_timekeeper_permissions(setting_key='tab_visibility_json', role_id=0)
     * Returns array<string roleId => array<int,string pageKey>>
     */
    public static function getHiddenPagesByRole(): array
    {
        try {
            $schema = Capsule::schema();

            if ($schema->hasTable('mod_timekeeper_settings')) {
                $json = Capsule::table('mod_timekeeper_settings')
                    ->where('setting_key', 'tab_visibility_json')
                    ->value('setting_value');
                $map = json_decode((string)$json, true);
                return is_array($map) ? $map : [];
            }

            if ($schema->hasTable('mod_timekeeper_permissions')) {
                $json = Capsule::table('mod_timekeeper_permissions')
                    ->where('setting_key', 'tab_visibility_json')
                    ->where('role_id', 0)
                    ->value('setting_value');
                $map = json_decode((string)$json, true);
                return is_array($map) ? $map : [];
            }
        } catch (\Throwable $e) {
            // ignore; fall through to empty
        }
        return [];
    }

    /**
     * Save hidden tabs by role map. Returns true on success.
     * Writes to mod_timekeeper_settings if available; otherwise to mod_timekeeper_permissions.
     */
    public static function saveHiddenPagesByRole(array $map): bool
    {
        $json = json_encode($map, JSON_UNESCAPED_SLASHES);

        try {
            $schema = Capsule::schema();

            if ($schema->hasTable('mod_timekeeper_settings')) {
                // upsert by setting_key
                $exists = Capsule::table('mod_timekeeper_settings')
                    ->where('setting_key', 'tab_visibility_json')
                    ->exists();

                if ($exists) {
                    Capsule::table('mod_timekeeper_settings')
                        ->where('setting_key', 'tab_visibility_json')
                        ->update(['setting_value' => $json]);
                } else {
                    Capsule::table('mod_timekeeper_settings')->insert([
                        'setting_key'   => 'tab_visibility_json',
                        'setting_value' => $json,
                    ]);
                }
                return true;
            }

            if ($schema->hasTable('mod_timekeeper_permissions')) {
                // upsert by (setting_key, role_id=0)
                $exists = Capsule::table('mod_timekeeper_permissions')
                    ->where('setting_key', 'tab_visibility_json')
                    ->where('role_id', 0)
                    ->exists();

                if ($exists) {
                    Capsule::table('mod_timekeeper_permissions')
                        ->where('setting_key', 'tab_visibility_json')
                        ->where('role_id', 0)
                        ->update(['setting_value' => $json]);
                } else {
                    Capsule::table('mod_timekeeper_permissions')->insert([
                        'setting_key'   => 'tab_visibility_json',
                        'role_id'       => 0,
                        'setting_value' => $json,
                    ]);
                }
                return true;
            }
        } catch (\Throwable $e) {
            // swallow and report failure
        }
        return false;
    }

    /** Small utility: do a safe redirect (header or JS fallback) */
    public static function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
        echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
        exit;
    }
}
