<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis daftar acuan yang dikelola superadmin.
 *
 * Sengaja enum, bukan baris di database: yang berubah-ubah adalah ISI tiap
 * daftar, bukan daftar apa saja yang ada. Menambah jenis baru selalu berarti
 * menambah pemakainya di form juga, jadi tidak ada gunanya bisa diubah tanpa
 * menyentuh kode.
 */
enum JenisMaster: string
{
    case Satuan = 'satuan';
    case Toko = 'toko';
    case SumberDana = 'sumber_dana';

    public function label(): string
    {
        return match ($this) {
            self::Satuan => 'Satuan',
            self::Toko => 'Toko / Supplier',
            self::SumberDana => 'Sumber Dana',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Satuan => 'Satuan bahan baku: sak, m3, batang, dan sejenisnya',
            self::Toko => 'Tempat belanja bahan baku',
            self::SumberDana => 'Asal anggaran kegiatan',
        };
    }

    /** Contoh isi awal, dipakai seeder. */
    public function contoh(): array
    {
        return match ($this) {
            self::Satuan => [
                'sak', 'batang', 'm3', 'm2', 'm', 'kg', 'lembar',
                'buah', 'unit', 'rit', 'ls', 'set', 'roll', 'kaleng',
            ],
            self::Toko => [
                'TB Sumber Jaya', 'TB Maju Bersama', 'UD Karya Mandiri',
            ],
            self::SumberDana => [
                'APBD', 'APBN', 'Dana Desa', 'DAK', 'Swakelola',
            ],
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $j) => $j->value, self::cases());
    }

    /** @return array<int, array{value:string,label:string,keterangan:string}> */
    public static function opsi(): array
    {
        return array_map(fn (self $j) => [
            'value' => $j->value,
            'label' => $j->label(),
            'keterangan' => $j->keterangan(),
        ], self::cases());
    }
}
