<?php

declare(strict_types=1);

namespace App\Enums;

enum JenisKas: string
{
    case Masuk = 'masuk';
    case Keluar = 'keluar';

    public function label(): string
    {
        return match ($this) {
            self::Masuk => 'Kas Masuk',
            self::Keluar => 'Kas Keluar',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
