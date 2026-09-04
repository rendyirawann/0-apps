<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor struk dihapus dari rincian bahan baku.
 *
 * Bukti belanjanya sudah dilampirkan sebagai foto pada kegiatan, jadi
 * mengetik ulang nomornya per item hanya pekerjaan ganda yang tidak dibaca
 * siapa pun. Kolomnya ikut dihapus, bukan sekadar disembunyikan dari form,
 * supaya tidak ada kolom mati yang membingungkan penulis berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_baku_items', function (Blueprint $table): void {
            $table->dropColumn('no_struk');
        });
    }

    public function down(): void
    {
        Schema::table('bahan_baku_items', function (Blueprint $table): void {
            $table->string('no_struk', 60)->nullable()->after('tanggal_beli');
        });
    }
};
