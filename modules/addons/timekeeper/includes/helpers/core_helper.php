<?php
// modules/addons/timekeeper/includes/helpers/Core.php
namespace Timekeeper\Helpers;

use WHMCS\Database\Capsule;

final class Core
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
        $csv = Capsule::table('mod_timekeeper_permissions')
            ->where('setting_key', $settingKey)
            ->value('setting_value');
        return self::parseIdList($csv);
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
}
