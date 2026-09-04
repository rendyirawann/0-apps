<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan boleh kosong.
 *
 * Aturan validasinya sudah `nullable` sejak awal, tetapi kolomnya NOT NULL
 * dengan default 'unit'. Default kolom hanya berlaku kalau nilainya TIDAK
 * disebut sama sekali -- sedangkan aplikasi mengirim `satuan: null` secara
 * eksplisit saat kolomnya dikosongkan. Akibatnya menyimpan item tanpa satuan
 * gagal dengan galat 500 dari database, bukan pesan validasi yang bisa
 * dimengerti.
 *
 * Dibuat nullable, bukan diberi default 'unit', karena "tidak disebut
 * satuannya" memang bukan hal yang sama dengan "satuannya unit".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_baku_items', function (Blueprint $table): void {
            $table->string('satuan', 30)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bahan_baku_items', function (Blueprint $table): void {
            $table->string('satuan', 30)->default('unit')->nullable(false)->change();
        });
    }
};
