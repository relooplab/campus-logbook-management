<?php

namespace App\Support;

/**
 * Versi rilis perangkat lunak.
 *
 * Sumber utama = CHANGELOG.md (senantiasa diperbarui saat rilis).
 * Urutan: APP_VERSION -> CHANGELOG.md -> file VERSION -> composer.json -> 0.0.0.
 * CHANGELOG diprioritaskan di atas file VERSION/composer.json agar tidak
 * menampilkan versi stale (file tsb bisa tertinggal saat rilis).
 */
class ReleaseVersion
{
    public static function get(): string
    {
        // 1) Override eksplisit.
        $override = config('app.version');
        if ($override) {
            return $override;
        }

        // 2) CHANGELOG.md — sumber rilis utama (selalu mewakili versi terbaru).
        $changelog = base_path('CHANGELOG.md');
        if (is_file($changelog)) {
            $content = (string) file_get_contents($changelog);
            if (preg_match('/^#+\s*\[\s*v?(\d+\.\d+\.\d+)\s*\]/m', $content, $m)) {
                return $m[1];
            }
        }

        // 3) File VERSION (fallback bila CHANGELOG tidak ikut di-deploy).
        $versionFile = base_path('VERSION');
        if (is_file($versionFile)) {
            $v = trim((string) file_get_contents($versionFile));
            if ($v !== '') {
                return ltrim($v, 'v');
            }
        }

        // 4) composer.json field "version".
        $composerJson = base_path('composer.json');
        if (is_file($composerJson)) {
            $data = json_decode((string) file_get_contents($composerJson), true);
            if (is_array($data) && !empty($data['version'])) {
                return ltrim((string) $data['version'], 'v');
            }
        }

        return '0.0.0';
    }
}
