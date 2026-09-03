<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // owner  = penerima bagi hasil (kolom "Hasil Bersih")
            // admin  = input kegiatan & kas
            // viewer = hanya baca laporan
            // superadmin = akses penuh; hanya SATU akun, dialah yang membuat
            //              akun petugas
            // petugas    = boleh melihat semuanya, tetapi hanya boleh mengisi
            //              Biaya Pelaksanaan (bahan baku + upah) dan Administrasi
            $table->string('role', 20)->default('petugas');
            $table->boolean('is_active')->default(true);
            $table->string('phone', 30)->nullable();

            // Fingerprint / biometric login.
            // Password TIDAK pernah disimpan di HP; server menerbitkan token
            // panjang yang disimpan di Android Keystore lewat secure storage.
            $table->string('biometric_token_hash', 64)->nullable()->index();
            $table->timestamp('biometric_enrolled_at')->nullable();
            $table->timestamp('biometric_expires_at')->nullable();
            $table->string('biometric_device_name')->nullable();

            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index(['is_active', 'role']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
