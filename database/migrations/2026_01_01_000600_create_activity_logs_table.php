<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak aktivitas seluruh pengguna.
     *
     * Diisi otomatis oleh middleware CatatAktivitas untuk SETIAP permintaan API
     * yang mengubah data (POST/PUT/PATCH/DELETE), apa pun endpoint-nya. Dengan
     * begitu tidak ada endpoint yang lupa dicatat hanya karena penulisnya lupa
     * memanggil pencatat.
     *
     * Permintaan GET tidak dicatat: jumlahnya sangat banyak dan tidak mengubah
     * apa pun, sehingga hanya akan menenggelamkan jejak yang penting.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // nullOnDelete: jejak tetap ada walau akunnya dihapus, sehingga
            // riwayat tidak berlubang.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Disalin saat kejadian, bukan di-join saat dibaca: kalau nama atau
            // peran pengguna berubah nanti, jejak lama tetap menunjukkan siapa
            // dan berperan sebagai apa dia SAAT itu.
            $table->string('user_nama', 255)->nullable();
            $table->string('user_role', 20)->nullable();

            // Nama aksi yang terbaca manusia, mis. "Tambah item bahan baku".
            $table->string('aksi', 120);

            // Kelompok agar mudah difilter: kegiatan, bahan_baku, kas,
            // lampiran, pengguna, auth, pengaturan, lain.
            $table->string('modul', 40)->index();

            // Sasaran aksi, bila ada.
            $table->string('subjek_tipe', 60)->nullable();
            $table->unsignedBigInteger('subjek_id')->nullable();
            $table->string('subjek_label', 200)->nullable();

            $table->string('metode', 10);
            $table->string('path', 300);
            $table->string('route_name', 120)->nullable();
            $table->unsignedSmallInteger('status');
            $table->boolean('berhasil')->default(true);

            // Isi permintaan setelah disaring: password, token, dan berkas
            // TIDAK pernah ikut tersimpan.
            $table->json('payload')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->unsignedInteger('durasi_ms')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['modul', 'created_at']);
            $table->index(['subjek_tipe', 'subjek_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
