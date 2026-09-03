<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar acuan yang dikelola superadmin.
 *
 * Satu tabel untuk beberapa jenis daftar, dibedakan kolom `jenis`. Ketiganya
 * berbentuk sama -- sekadar nama pilihan -- sehingga membuat tiga tabel
 * terpisah hanya menggandakan kode tanpa menambah kejelasan.
 *
 * Yang TIDAK berada di sini: kategori kas dan status kegiatan. Keduanya
 * enum di kode karena terikat rumus -- `KategoriKas::pelaksanaanReal()`
 * dipakai langsung pada query `Kegiatan::totalUpah()`. Kalau daftarnya bisa
 * disunting, seseorang bisa mengubah belanja mana yang dihitung sebagai
 * Biaya Pelaksanaan Real tanpa sadar, dan angka profit ikut bergeser diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data', function (Blueprint $table): void {
            $table->id();

            // satuan | toko | sumber_dana
            $table->string('jenis', 30);

            $table->string('nama', 100);
            $table->string('keterangan', 200)->nullable();

            // Urutan tampil, mis. satuan yang paling sering dipakai di depan.
            $table->unsignedInteger('urutan')->default(0);

            // Dinonaktifkan, bukan dihapus: data lama yang memakainya tetap
            // terbaca, tetapi pilihannya tidak muncul lagi saat input baru.
            $table->boolean('aktif')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jenis', 'aktif', 'urutan']);

            // Nama tidak boleh ganda dalam satu jenis. deleted_at ikut
            // dilibatkan supaya nama yang pernah dihapus bisa dipakai lagi.
            $table->unique(['jenis', 'nama', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data');
    }
};
