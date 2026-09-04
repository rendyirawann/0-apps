<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memisahkan "berapa nilainya" dari "berapa yang sudah dibayar".
 *
 * Sebelumnya sebuah pengeluaran hanya punya `nominal`, dan seluruhnya dianggap
 * sudah keluar. Dengan adanya metode Hutang, keduanya bisa berbeda: upah
 * Rp10.000.000 bisa baru dibayar Rp4.000.000.
 *
 * Yang masuk hitungan Biaya Pelaksanaan Real adalah `dibayar`, bukan
 * `nominal`. Sisanya ditampilkan sebagai catatan terhutang.
 *
 * Status lunas SENGAJA tidak disimpan sebagai kolom sendiri. Ia selalu bisa
 * diturunkan dari `dibayar >= nominal`, dan menyimpannya terpisah hanya
 * membuka peluang kedua nilai itu berbeda -- baris yang bertanda "lunas"
 * padahal `dibayar` masih nol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_flows', function (Blueprint $table): void {
            $table->decimal('dibayar', 18, 0)->default(0)->after('nominal');
        });

        // Baris lama tidak mengenal hutang, jadi seluruhnya sudah lunas.
        // Tanpa ini, semua pengeluaran lama mendadak dianggap belum dibayar
        // dan Biaya Pelaksanaan Real setiap kegiatan jatuh ke nol.
        DB::table('cash_flows')->update(['dibayar' => DB::raw('nominal')]);
    }

    public function down(): void
    {
        Schema::table('cash_flows', function (Blueprint $table): void {
            $table->dropColumn('dibayar');
        });
    }
};
