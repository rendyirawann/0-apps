<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai default persentase yang dipakai saat form kegiatan baru dibuka.
     * Rate final tetap disimpan per-kegiatan, sehingga mengubah default di sini
     * TIDAK akan mengubah angka kegiatan yang sudah tersimpan.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|percent|money|int|bool
            $table->string('label');
            $table->string('group', 40)->default('umum');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
