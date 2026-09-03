<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Nilai awal persentase untuk form kegiatan baru.
 * Urutan prioritas: tabel `settings` -> config/taksasi.php.
 */
final class RateDefaults
{
    public const KEYS = [
        'rate_ppn', 'rate_pph', 'rate_rencana', 'rate_kewajiban',
        'rate_administrasi', 'rate_perusahaan', 'rate_investor', 'jml_owner',
    ];

    /** @return array<string, float|int> */
    public static function all(): array
    {
        $config = config('taksasi.default_rates', []);
        $out = [];

        foreach (self::KEYS as $key) {
            $fromDb = Setting::get($key);
            $value = $fromDb ?? ($config[$key] ?? 0);
            $out[$key] = $key === 'jml_owner' ? (int) $value : (float) $value;
        }

        return $out;
    }
}
