<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusKegiatan: string
{
    case Draft = 'draft';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Berjalan => 'Berjalan',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
