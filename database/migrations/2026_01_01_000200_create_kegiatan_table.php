<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu baris "Taksasi Pekerjaan" di Excel.
     *
     * Semua kolom uang bertipe numeric(18,0) => rupiah bulat, tanpa desimal.
     * JANGAN pakai float/double: 349000000 bisa tersimpan jadi 348999999.99997.
     *
     * Semua rate disimpan di baris ini (bukan konstanta global) karena
     * persentase ditentukan sendiri setiap kali membuat kegiatan.
     */
    public function up(): void
    {
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique()->nullable();
            $table->string('nama', 150);
            $table->text('keterangan')->nullable();

            $table->string('lokasi', 150)->nullable();
            $table->string('sumber_dana', 100)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();

            // draft | berjalan | selesai | batal
            $table->string('status', 20)->default('draft')->index();

            // ---------------- INPUT ----------------
            $table->decimal('pagu', 18, 0)->default(0);

            // Realisasi bahan + upah. Kalau NULL, nilainya diambil dari
            // SUM(cash_flows) kategori 'bahan' + 'upah'. Diisi manual = override.
            $table->decimal('pelaksanaan_real', 18, 0)->nullable();

            // ---------------- RATE (%) per kegiatan ----------------
            $table->decimal('rate_ppn', 6, 3)->default(11);
            $table->decimal('rate_pph', 6, 3)->default(1.75);
            $table->decimal('rate_rencana', 6, 3)->default(60);
            $table->decimal('rate_kewajiban', 6, 3)->default(12);
            $table->decimal('rate_administrasi', 6, 3)->default(1);
            $table->decimal('rate_perusahaan', 6, 3)->default(1.5);
            $table->decimal('rate_investor', 6, 3)->default(50);
            $table->unsignedTinyInteger('jml_owner')->default(3);

            // ---------------- HASIL HITUNG (disimpan, bukan dihitung ulang) ----
            // Di-snapshot supaya laporan periode lampau tetap konsisten
            // walau rumus/rate default berubah di kemudian hari.
            $table->decimal('ppn', 18, 0)->default(0);
            $table->decimal('pph', 18, 0)->default(0);
            $table->decimal('netto', 18, 0)->default(0);
            $table->decimal('rencana_pelaksanaan', 18, 0)->default(0);
            $table->decimal('biaya_kewajiban', 18, 0)->default(0);
            $table->decimal('biaya_administrasi', 18, 0)->default(0);
            $table->decimal('biaya_perusahaan', 18, 0)->default(0);
            $table->decimal('profit_kotor', 18, 0)->default(0);
            $table->decimal('bagi_hasil_investor', 18, 0)->default(0);
            $table->decimal('profit_bersih', 18, 0)->default(0);
            $table->decimal('hasil_bersih_per_owner', 18, 0)->default(0);
            $table->decimal('sisa_pembulatan', 18, 0)->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'tanggal_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan');
    }
};
