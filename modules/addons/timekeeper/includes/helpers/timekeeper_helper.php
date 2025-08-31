<?php
// modules/addons/timekeeper/includes/helpers/timekeeper_helper.php
declare(strict_types=1);

namespace Timekeeper\Helpers;

use WHMCS\Config\Setting;
use WHMCS\Database\Capsule;

final class TimekeeperHelper
{
    /* ===================== RBAC / Role ===================== */

    /** Current admin's role id (0 on failure) */
    public static function getAdminRoleId(int $adminId): int
    {
        try { return (int) Capsule::table('tbladmins')->where('id', $adminId)->value('roleid'); }
        catch (\Throwable $e) { return 0; }
    }

    /**
     * Hidden pages map loader (JSON). Primary: tbladdonmodules(setting='hide_tabs_roles').
     * Shape: ["1" => [], "2" => ["settings","reports"], ...]
     */
    public static function getHiddenPagesByRole(): array
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'timekeeper')
                ->where('setting', 'hide_tabs_roles')
                ->value('value');
            $map = $val ? json_decode($val, true) : [];
            return is_array($map) ? $map : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Is role allowed to view normalized page key? */
    public static function isPageAllowedForRole(int $roleId, string $pageKey): bool
    {
        // Full Admin safety
        if ($roleId === 1) return true;

        $page = \tk_normalize_page($pageKey);
        $hidden = self::getHiddenPagesByRole();
        $rk = (string)$roleId;

        if (!isset($hidden[$rk]) || !is_array($hidden[$rk])) return true;
        return !in_array($page, $hidden[$rk], true);
    }

    /** First safe landing page for a role */
    public static function firstAllowedPageForRole(int $roleId, array $candidates = []): string
    {
        $order = $candidates ?: ['dashboard','reports','approval','timesheet','cron','hide_tabs','settings'];
        foreach ($order as $p) {
            if (self::isPageAllowedForRole($roleId, $p)) return $p;
        }
        return 'dashboard';
    }

    /* ===================== Assets ===================== */

    /** Public URL for asset under modules/addons/timekeeper/… */
    public static function assetUrl(string $rel): string
    {
        $ssl  = Setting::getValue('SystemSSLURL');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $base = ($ssl && $https) ? $ssl : Setting::getValue('SystemURL');

        if (!$base) {
            $scheme = $https ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base   = $scheme . '://' . $host;
        }
        return rtrim($base, '/') . '/modules/addons/timekeeper/' . ltrim($rel, '/');
    }

    /** File mtime as version (fallback static) */
    public static function assetVer(string $rel, string $fallback = '1.0.0'): string
    {
        $abs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        return is_file($abs) ? (string) filemtime($abs) : $fallback;
    }

    /** Check asset file exists (relative to module root) */
    public static function assetExists(string $rel): bool
    {
        $abs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        return is_file($abs);
    }

    /**
     * Emit link/script tags if files exist.
     * $assets = ['css' => ['timekeeper.css', ...], 'js' => ['timekeeper.js', ...]]
     */
    public static function loadAssetsIfExists(array $assets): void
    {
        if (!empty($assets['css'])) {
            foreach ($assets['css'] as $css) {
                $rel = 'css/' . ltrim($css, '/');
                if (!self::assetExists($rel)) continue;
                $href = self::assetUrl($rel) . '?v=' . self::assetVer($rel);
                echo '<link rel="stylesheet" href="' . \Timekeeper\Helpers\CoreHelper::e($href) . '">' . PHP_EOL;
            }
        }
        if (!empty($assets['js'])) {
            foreach ($assets['js'] as $js) {
                $rel = 'js/' . ltrim($js, '/');
                if (!self::assetExists($rel)) continue;
                $src = self::assetUrl($rel) . '?v=' . self::assetVer($rel);
                echo '<script defer src="' . \Timekeeper\Helpers\CoreHelper::e($src) . '"></script>' . PHP_EOL;
            }
        }
    }
}
