<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kategori arus kas. Sengaja dipetakan 1:1 dengan kolom "Taksasi Pekerjaan"
 * supaya realisasi kas bisa dibandingkan langsung dengan rencananya.
 */
enum KategoriKas: string
{
    // ---- Kas masuk ----
    case Termin = 'termin';                 // pencairan pagu / termin dari pemberi kerja
    case ModalInvestor = 'modal_investor';  // setoran modal dari investor
    case LainMasuk = 'lain_masuk';

    // ---- Kas keluar ----
    /**
     * @deprecated Bahan baku kini dicatat per item di tabel bahan_baku_items,
     * bukan sebagai satu nominal kas. Case ini dipertahankan agar baris lama
     * yang terlanjur memakai nilai ini tidak menggagalkan cast enum, tetapi
     * sudah dikeluarkan dari daftar pilihan dan dari perhitungan.
     */
    case Bahan = 'bahan';

    case Upah = 'upah';                     // -> Biaya Pelaksanaan Real
    case Kewajiban = 'kewajiban';           // -> Biaya Kewajiban
    case Administrasi = 'administrasi';     // -> Administrasi
    case BiayaPerusahaan = 'biaya_perusahaan';
    case Ppn = 'ppn';
    case Pph = 'pph';
    case BagiHasilInvestor = 'bagi_hasil_investor';
    case ProfitOwner = 'profit_owner';      // pembagian "Hasil Bersih" ke owner
    case LainKeluar = 'lain_keluar';

    public function label(): string
    {
        return match ($this) {
            self::Termin => 'Termin / Pencairan Pagu',
            self::ModalInvestor => 'Setoran Modal Investor',
            self::LainMasuk => 'Penerimaan Lain-lain',
            self::Bahan => 'Belanja Bahan (lama)',
            self::Upah => 'Upah Pekerja',
            self::Kewajiban => 'Biaya Kewajiban',
            self::Administrasi => 'Biaya Administrasi',
            self::BiayaPerusahaan => 'Biaya Perusahaan',
            self::Ppn => 'Setoran PPN',
            self::Pph => 'Setoran PPh',
            self::BagiHasilInvestor => 'Bagi Hasil Investor',
            self::ProfitOwner => 'Pembagian Hasil Owner',
            self::LainKeluar => 'Pengeluaran Lain-lain',
        };
    }

    public function jenis(): JenisKas
    {
        return match ($this) {
            self::Termin, self::ModalInvestor, self::LainMasuk => JenisKas::Masuk,
            default => JenisKas::Keluar,
        };
    }

    /**
     * Kategori kas yang ikut membentuk "Biaya Pelaksanaan Real".
     *
     * Hanya upah pekerja. Porsi bahan baku TIDAK diambil dari kas melainkan
     * dijumlahkan dari rincian per item (tabel bahan_baku_items), sehingga:
     *
     *   Biaya Pelaksanaan Real = SUM(bahan_baku_items) + SUM(kas kategori upah)
     *
     * @return array<int, string>
     */
    public static function pelaksanaanReal(): array
    {
        return [self::Upah->value];
    }

    /** Kategori yang boleh diisi lewat form (tanpa yang sudah usang). */
    public static function dapatDipilih(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $c) => $c !== self::Bahan,
        ));
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, array{value:string,label:string,jenis:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'jenis' => $c->jenis()->value,
        ], self::dapatDipilih());
    }
}
