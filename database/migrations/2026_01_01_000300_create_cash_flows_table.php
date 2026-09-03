<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pencatatan arus kas per kegiatan.
     *
     * kategori 'bahan' + 'upah' pada jenis 'keluar' adalah sumber otomatis
     * untuk kolom "Biaya Pelaksanaan Real" bila kegiatan.pelaksanaan_real NULL.
     */
    public function up(): void
    {
        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();

            $table->date('tanggal')->index();
            $table->string('jenis', 10);      // masuk | keluar
            $table->string('kategori', 30);   // lihat App\Enums\KategoriKas
            $table->decimal('nominal', 18, 0);
            $table->string('uraian', 200);
            $table->text('keterangan')->nullable();
            $table->string('metode', 20)->default('kas'); // kas | transfer
            $table->string('no_bukti', 60)->nullable();
            $table->string('lampiran_path')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kegiatan_id', 'tanggal']);
            $table->index(['kegiatan_id', 'jenis', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flows');
    }
};
