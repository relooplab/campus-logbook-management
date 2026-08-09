<?php

namespace App\Support;

/**
 * Versi rilis perangkat lunak.
 *
 * Dinamis: membaca versi terbaru dari CHANGELOG.md (## [x.y.z]) sehingga
 * selalu mengikuti rilis terakhir. Bisa di-override via config/app.version
 * (env APP_VERSION) bila perlu.
 */
class ReleaseVersion
{
    public static function get(): string
    {
        $override = config('app.version');
        if ($override) {
            return $override;
        }

        $changelog = base_path('CHANGELOG.md');
        if (is_file($changelog)) {
            $content = (string) file_get_contents($changelog);
            if (preg_match('/##\s*\[\s*v?(\d+\.\d+\.\d+)\s*\]/', $content, $m)) {
                return $m[1];
            }
        }

        return '0.0.0';
    }
}
