<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kolom tambahan agar pengguna bisa melengkapi data akunnya sendiri. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('jabatan', 100)->nullable()->after('phone');
            $table->string('alamat', 255)->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['jabatan', 'alamat']);
        });
    }
};
