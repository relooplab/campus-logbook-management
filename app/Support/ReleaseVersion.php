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

        // 1) File VERSION di root proyek (dikomit, selalu tersedia).
        $versionFile = base_path('VERSION');
        if (is_file($versionFile)) {
            $v = trim((string) file_get_contents($versionFile));
            if ($v !== '') {
                return ltrim($v, 'v');
            }
        }

        // 2) composer.json field "version".
        $composerJson = base_path('composer.json');
        if (is_file($composerJson)) {
            $data = json_decode((string) file_get_contents($composerJson), true);
            if (is_array($data) && !empty($data['version'])) {
                return ltrim((string) $data['version'], 'v');
            }
        }

        // 3) CHANGELOG.md — versi terbaru "## [x.y.z]".
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
