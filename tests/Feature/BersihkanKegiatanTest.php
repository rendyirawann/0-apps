<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BahanBakuItem;
use App\Models\CashFlow;
use App\Models\Kegiatan;
use App\Models\Lampiran;
use App\Models\MasterData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Perintah `kegiatan:bersihkan`.
 *
 * Yang diuji bukan hanya "barisnya hilang", tetapi tiga hal yang mudah
 * terlewat: berkas fisik lampiran ikut terhapus, akun beserta data master
 * TIDAK ikut terhapus, dan urutan id kembali dari satu.
 */
class BersihkanKegiatanTest extends TestCase
{
    use RefreshDatabase;

    private function kegiatanLengkap(): Kegiatan
    {
        $kegiatan = Kegiatan::factory()->create();

        CashFlow::factory()->untuk($kegiatan)->upah(5_000_000)->create();
        BahanBakuItem::factory()->untuk($kegiatan)->create();
        Lampiran::factory()->untuk($kegiatan)->create();

        return $kegiatan;
    }

    #[Test]
    public function mengosongkan_kegiatan_beserta_seluruh_turunannya(): void
    {
        Storage::fake('lampiran');

        $this->kegiatanLengkap();
        $this->kegiatanLengkap();

        $this->assertSame(2, Kegiatan::query()->count());

        $this->artisan('kegiatan:bersihkan --force')->assertSuccessful();

        $this->assertSame(0, Kegiatan::withTrashed()->count());
        $this->assertSame(0, CashFlow::withTrashed()->count());
        $this->assertSame(0, BahanBakuItem::withTrashed()->count());
        $this->assertSame(0, Lampiran::withTrashed()->count());
    }

    #[Test]
    public function berkas_fisik_lampiran_ikut_terhapus(): void
    {
        Storage::fake('lampiran');

        $kegiatan = Kegiatan::factory()->create();
        $lampiran = Lampiran::factory()->untuk($kegiatan)->create();

        Storage::disk('lampiran')->assertExists($lampiran->path);

        $this->artisan('kegiatan:bersihkan --force')->assertSuccessful();

        // Inilah yang tidak ditangani cascade database: menghapus baris
        // lampiran lewat foreign key TIDAK memicu event Eloquent, sehingga
        // berkas buktinya tertinggal di disk tanpa ada yang bisa membukanya.
        Storage::disk('lampiran')->assertMissing($lampiran->path);
    }

    #[Test]
    public function folder_kosong_bersarang_ikut_dibuang(): void
    {
        Storage::fake('lampiran');

        $kegiatan = Kegiatan::factory()->create();

        Storage::disk('lampiran')->put('kegiatan/uji/struk.jpg', 'isi');

        Lampiran::factory()->untuk($kegiatan)->create([
            'path' => 'kegiatan/uji/struk.jpg',
        ]);

        $this->artisan('kegiatan:bersihkan --force')->assertSuccessful();

        // Folder induk baru kosong setelah anaknya dibuang, jadi menelusuri
        // satu tingkat saja akan menyisakan rangkaian folder kosong.
        $this->assertSame([], Storage::disk('lampiran')->allDirectories());
    }

    #[Test]
    public function akun_dan_data_master_tidak_ikut_terhapus(): void
    {
        Storage::fake('lampiran');

        User::factory()->count(3)->create();
        MasterData::factory()->count(5)->create();
        $this->kegiatanLengkap();

        $this->artisan('kegiatan:bersihkan --force')->assertSuccessful();

        // Menghapus akun berarti kehilangan akses masuk ke aplikasi sendiri.
        $this->assertSame(3, User::query()->count());
        $this->assertSame(5, MasterData::query()->count());
    }

    #[Test]
    public function jejak_aktivitas_dipertahankan_secara_bawaan(): void
    {
        Storage::fake('lampiran');

        $this->kegiatanLengkap();

        ActivityLog::query()->create([
            'aksi' => 'Membuat kegiatan',
            'modul' => 'kegiatan',
            'metode' => 'POST',
            'path' => '/api/kegiatan',
            'status' => 201,
            'berhasil' => true,
        ]);

        $this->artisan('kegiatan:bersihkan --force')->assertSuccessful();

        // Jejak audit bukan data kegiatan: justru dialah yang menjawab
        // "siapa yang mengosongkan datanya".
        $this->assertSame(1, ActivityLog::query()->count());
    }

    #[Test]
    public function jejak_aktivitas_ikut_terhapus_bila_diminta(): void
    {
        Storage::fake('lampiran');

        ActivityLog::query()->create([
            'aksi' => 'Membuat kegiatan',
            'modul' => 'kegiatan',
            'metode' => 'POST',
            'path' => '/api/kegiatan',
            'status' => 201,
            'berhasil' => true,
        ]);

        ActivityLog::query()->create([
            'aksi' => 'Masuk dengan password',
            'modul' => 'auth',
            'metode' => 'POST',
            'path' => '/api/auth/login',
            'status' => 200,
            'berhasil' => true,
        ]);

        $this->kegiatanLengkap();

        $this->artisan('kegiatan:bersihkan --aktivitas --force')->assertSuccessful();

        // Hanya modul yang berkaitan kegiatan; jejak login tetap ada.
        $this->assertSame(1, ActivityLog::query()->count());
        $this->assertSame('auth', ActivityLog::query()->value('modul'));
    }

    #[Test]
    public function urutan_id_kembali_dari_satu(): void
    {
        Storage::fake('lampiran');

        Kegiatan::factory()->count(3)->create();

        $this->artisan('kegiatan:bersihkan --force')->assertSuccessful();

        $baru = Kegiatan::factory()->create();

        // Tanpa RESTART IDENTITY, kegiatan pertama setelah dibersihkan akan
        // bernomor 4 -- membingungkan saat data seharusnya mulai dari nol.
        $this->assertSame(1, $baru->id);
    }

    #[Test]
    public function tidak_melakukan_apa_pun_bila_sudah_kosong(): void
    {
        Storage::fake('lampiran');

        $this->artisan('kegiatan:bersihkan --force')
            ->expectsOutputToContain('Tidak ada data kegiatan')
            ->assertSuccessful();
    }

    #[Test]
    public function bisa_langsung_mengisi_kegiatan_contoh(): void
    {
        Storage::fake('lampiran');

        // Contoh butuh akun sebagai penginput datanya.
        User::factory()->create(['email' => 'superadmin@taksasi.test', 'role' => User::SUPERADMIN]);
        User::factory()->create(['email' => 'sinta@taksasi.test', 'role' => User::PETUGAS]);

        $this->kegiatanLengkap();

        $this->artisan('kegiatan:bersihkan --contoh --force')->assertSuccessful();

        $this->assertSame(3, Kegiatan::query()->count());
    }
}
