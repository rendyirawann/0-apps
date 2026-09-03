<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lampiran bukti: foto struk belanja atau berkas pendukung lain.
     *
     * Dilekatkan ke KEGIATAN, bukan ke tiap baris item, karena satu struk
     * biasanya memuat banyak item sekaligus. Kolom `konteks` disiapkan agar
     * nanti bisa dipakai untuk bagian lain tanpa mengubah skema.
     */
    public function up(): void
    {
        Schema::create('lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();

            // biaya_pelaksanaan | administrasi | lain
            $table->string('konteks', 30)->default('biaya_pelaksanaan');

            // Jalur relatif terhadap disk 'lampiran' (storage/app/lampiran),
            // disimpan tanpa awalan agar tidak terikat lokasi absolut mesin.
            $table->string('path');

            $table->string('nama_asli', 200);
            $table->string('mime', 100);
            $table->unsignedBigInteger('ukuran')->default(0);

            // Ringkasan isi berkas; dipakai untuk menolak unggahan ganda.
            $table->string('hash', 64)->nullable()->index();

            $table->string('keterangan', 200)->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kegiatan_id', 'konteks']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran');
    }
};
