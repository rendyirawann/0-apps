<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cara sebuah pengeluaran dibayar.
 *
 * `Hutang` bukan sekadar label. Pembayaran upah yang belum lunas TIDAK ikut
 * menambah Biaya Pelaksanaan Real -- yang dihitung hanya bagian yang benar-
 * benar sudah dibayar (kolom `dibayar`). Sisanya muncul sebagai catatan
 * "terhutang" supaya terlihat berapa lagi yang harus dikeluarkan.
 *
 * Karena itu memilih Hutang tanpa mengisi jumlah yang sudah dibayar berarti
 * pengeluaran itu belum menyumbang apa pun ke biaya real.
 */
enum MetodeBayar: string
{
    case Kas = 'kas';
    case Transfer = 'transfer';
    case Hutang = 'hutang';

    public function label(): string
    {
        return match ($this) {
            self::Kas => 'Kas / Tunai',
            self::Transfer => 'Transfer',
            self::Hutang => 'Hutang',
        };
    }

    /** Metode yang secara bawaan berarti uangnya sudah keluar seluruhnya. */
    public function lunasBawaan(): bool
    {
        return $this !== self::Hutang;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $m) => $m->value, self::cases());
    }

    /** @return array<int, array{value:string,label:string}> */
    public static function opsi(): array
    {
        return array_map(fn (self $m) => [
            'value' => $m->value,
            'label' => $m->label(),
        ], self::cases());
    }
}
