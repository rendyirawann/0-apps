<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Satu superadmin, dua petugas.
 *
 * Superadmin sengaja hanya dibuat di sini — tidak ada endpoint yang bisa
 * menciptakan superadmin kedua (lihat `UserRequest::dataPetugas()`).
 *
 * Memakai updateOrCreate, jadi menjalankannya ulang mengembalikan password ke
 * `password123`. Itu memudahkan saat lupa password di komputer sendiri, tetapi
 * berarti perintah ini TIDAK boleh dijalankan sembarangan di server.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $super = User::query()->updateOrCreate(
            ['email' => 'superadmin@taksasi.test'],
            [
                'name' => 'Dormansyah',
                'password' => 'password123',
                'role' => User::SUPERADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $petugas = [
            ['sinta@taksasi.test', 'Sinta Pratiwi'],
            ['rudi@taksasi.test', 'Rudi Hartono'],
        ];

        foreach ($petugas as [$email, $nama]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $nama,
                    'password' => 'password123',
                    'role' => User::PETUGAS,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        $this->command?->info("Superadmin : superadmin@taksasi.test / password123 (id {$super->id})");
        $this->command?->info('Petugas    : sinta@taksasi.test / password123');
        $this->command?->info('Petugas    : rudi@taksasi.test  / password123');
        $this->command?->warn('Ganti password ini sebelum dipakai di server.');
    }
}
