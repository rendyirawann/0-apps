<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menjadikan default persentase kegiatan NOL.
 *
 * Semula setiap kolom rate punya default bukan-nol (PPN 11, PPh 1,75,
 * Rencana 60, dan seterusnya). Akibatnya kegiatan yang baru dibuat -- yang
 * hanya diisi nama dan pagu -- langsung punya persentase lengkap, dan halaman
 * detailnya menampilkan netto, biaya, sampai profit per owner seolah semua itu
 * sudah pernah ditentukan seseorang. Padahal belum.
 *
 * Sekarang kegiatan baru benar-benar kosong. Angkanya muncul setelah pengguna
 * mengisi persentasenya sendiri, dan `Kegiatan::rateTerisi()` yang menentukan
 * kapan bagian taksasi layak ditampilkan.
 *
 * Baris yang sudah ada TIDAK diubah: default kolom hanya berlaku untuk baris
 * baru, sehingga kegiatan yang persentasenya sudah diisi tetap utuh.
 */
return new class extends Migration
{
    /** @var array<string, float> */
    private const SEMULA = [
        'rate_ppn' => 11,
        'rate_pph' => 1.75,
        'rate_rencana' => 60,
        'rate_kewajiban' => 12,
        'rate_administrasi' => 1,
        'rate_perusahaan' => 1.5,
        'rate_investor' => 50,
    ];

    public function up(): void
    {
        Schema::table('kegiatan', function (Blueprint $table): void {
            foreach (array_keys(self::SEMULA) as $kolom) {
                $table->decimal($kolom, 6, 3)->default(0)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table): void {
            foreach (self::SEMULA as $kolom => $nilai) {
                $table->decimal($kolom, 6, 3)->default($nilai)->change();
            }
        });
    }
};
