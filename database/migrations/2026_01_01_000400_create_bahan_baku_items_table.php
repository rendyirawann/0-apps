<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rincian bahan baku per item untuk satu kegiatan.
     *
     * Tabel ini adalah SUMBER kolom "Bahan Baku" pada Biaya Pelaksanaan Real:
     * totalnya dijumlahkan otomatis dari subtotal seluruh baris di sini,
     * sehingga angka bahan baku tidak pernah diketik manual dan tidak mungkin
     * berbeda dari rinciannya.
     *
     *   Biaya Pelaksanaan Real = SUM(bahan_baku_items) + SUM(kas kategori upah)
     */
    public function up(): void
    {
        Schema::create('bahan_baku_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();

            $table->string('nama', 150);
            $table->string('satuan', 30)->default('unit');

            // Kuantitas boleh pecahan: 4,5 m3 pasir, 2,75 ton besi.
            $table->decimal('qty', 14, 3)->default(1);

            // Uang tetap rupiah bulat, sama seperti kolom uang lainnya.
            $table->decimal('harga_satuan', 18, 0)->default(0);

            // Disimpan (bukan dihitung saat query) agar penjumlahan di database
            // memakai angka yang persis sama dengan yang dilihat pengguna,
            // termasuk hasil pembulatannya.
            $table->decimal('subtotal', 18, 0)->default(0);

            $table->date('tanggal_beli')->nullable();
            $table->string('no_struk', 60)->nullable();
            $table->string('toko', 120)->nullable();
            $table->text('keterangan')->nullable();

            $table->unsignedInteger('urutan')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kegiatan_id', 'urutan']);
            $table->index(['kegiatan_id', 'tanggal_beli']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahan_baku_items');
    }
};
