<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\JenisMaster;
use App\Models\MasterData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Daftar acuan: satuan, toko, sumber dana.
 *
 * Aturannya asimetris dan itu disengaja: semua peran BOLEH membaca, karena
 * pilihannya dibutuhkan saat mengisi bahan baku; hanya superadmin yang boleh
 * menambah, supaya daftar tidak lekas penuh varian ejaan yang sama.
 */
class DataMasterTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => User::SUPERADMIN, 'is_active' => true]);
    }

    private function petugas(): User
    {
        return User::factory()->create(['role' => User::PETUGAS, 'is_active' => true]);
    }

    #[Test]
    public function petugas_boleh_membaca_daftar(): void
    {
        MasterData::factory()->create(['nama' => 'sak']);

        $this->actingAs($this->petugas())
            ->getJson('/api/master/satuan')
            ->assertOk()
            ->assertJsonPath('data.jenis', 'satuan')
            ->assertJsonPath('data.items.0.nama', 'sak')
            // Penanda ini yang membuat aplikasi menyembunyikan tombol tambah.
            ->assertJsonPath('data.boleh_kelola', false);
    }

    #[Test]
    public function superadmin_melihat_penanda_boleh_kelola(): void
    {
        $this->actingAs($this->superadmin())
            ->getJson('/api/master/satuan')
            ->assertOk()
            ->assertJsonPath('data.boleh_kelola', true);
    }

    #[Test]
    public function petugas_tidak_boleh_menambah_mengubah_atau_menghapus(): void
    {
        $item = MasterData::factory()->create(['nama' => 'sak']);
        $petugas = $this->petugas();

        $this->actingAs($petugas)
            ->postJson('/api/master/satuan', ['nama' => 'zak'])
            ->assertForbidden();

        $this->actingAs($petugas)
            ->putJson("/api/master/{$item->id}", ['nama' => 'diubah'])
            ->assertForbidden();

        $this->actingAs($petugas)
            ->deleteJson("/api/master/{$item->id}")
            ->assertForbidden();

        $this->assertSame('sak', $item->fresh()->nama);
    }

    #[Test]
    public function superadmin_bisa_menambah_ke_setiap_jenis(): void
    {
        $superadmin = $this->superadmin();

        foreach (JenisMaster::cases() as $jenis) {
            $this->actingAs($superadmin)
                ->postJson("/api/master/{$jenis->value}", ['nama' => 'Uji '.$jenis->value])
                ->assertCreated()
                ->assertJsonPath('data.jenis', $jenis->value);
        }

        $this->assertSame(3, MasterData::query()->count());
    }

    #[Test]
    public function nama_ganda_dalam_satu_jenis_ditolak(): void
    {
        $superadmin = $this->superadmin();

        MasterData::factory()->create(['jenis' => 'satuan', 'nama' => 'sak']);

        $this->actingAs($superadmin)
            ->postJson('/api/master/satuan', ['nama' => 'sak'])
            ->assertStatus(422)
            ->assertJsonPath('errors.nama.0', 'Nama itu sudah ada di daftar ini.');
    }

    #[Test]
    public function nama_sama_di_jenis_berbeda_diizinkan(): void
    {
        $superadmin = $this->superadmin();

        MasterData::factory()->create(['jenis' => 'satuan', 'nama' => 'Karya']);

        // "Karya" wajar ada sebagai nama toko sekaligus satuan di tempat lain;
        // keunikannya hanya berlaku dalam satu daftar.
        $this->actingAs($superadmin)
            ->postJson('/api/master/toko', ['nama' => 'Karya'])
            ->assertCreated();
    }

    #[Test]
    public function jenis_yang_tidak_dikenal_dijawab_404(): void
    {
        $this->actingAs($this->superadmin())
            ->getJson('/api/master/entah-apa')
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    #[Test]
    public function pilihan_nonaktif_tidak_muncul_di_daftar_untuk_input(): void
    {
        MasterData::factory()->create(['nama' => 'aktif-terus']);
        MasterData::factory()->nonaktif()->create(['nama' => 'sudah-mati']);

        // Tanpa ?semua, daftar hanya berisi yang aktif -- inilah yang dipakai
        // form input, dan pilihan mati tidak boleh muncul di sana.
        $this->actingAs($this->petugas())
            ->getJson('/api/master/satuan')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', 'aktif-terus');
    }

    #[Test]
    public function superadmin_bisa_melihat_yang_nonaktif_dengan_parameter_semua(): void
    {
        MasterData::factory()->create(['nama' => 'aktif-terus']);
        MasterData::factory()->nonaktif()->create(['nama' => 'sudah-mati']);

        $this->actingAs($this->superadmin())
            ->getJson('/api/master/satuan?semua=1')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    #[Test]
    public function petugas_tidak_bisa_memaksa_melihat_yang_nonaktif(): void
    {
        MasterData::factory()->create(['nama' => 'aktif-terus']);
        MasterData::factory()->nonaktif()->create(['nama' => 'sudah-mati']);

        // Parameter ?semua diabaikan bila pemanggilnya tidak boleh mengelola.
        $this->actingAs($this->petugas())
            ->getJson('/api/master/satuan?semua=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function satu_panggilan_mengembalikan_semua_jenis(): void
    {
        MasterData::factory()->jenis(JenisMaster::Satuan)->create(['nama' => 'sak']);
        MasterData::factory()->jenis(JenisMaster::Toko)->create(['nama' => 'TB Jaya']);
        MasterData::factory()->jenis(JenisMaster::SumberDana)->create(['nama' => 'APBD']);

        $this->actingAs($this->petugas())
            ->getJson('/api/master')
            ->assertOk()
            ->assertJsonPath('data.data.satuan.0.nama', 'sak')
            ->assertJsonPath('data.data.toko.0.nama', 'TB Jaya')
            ->assertJsonPath('data.data.sumber_dana.0.nama', 'APBD')
            ->assertJsonCount(3, 'data.jenis');
    }

    #[Test]
    public function menghapus_pilihan_tidak_mengubah_data_lama(): void
    {
        $superadmin = $this->superadmin();
        $item = MasterData::factory()->create(['nama' => 'sak']);

        $this->actingAs($superadmin)
            ->deleteJson("/api/master/{$item->id}")
            ->assertOk();

        // Soft delete: barisnya hilang dari daftar, tetapi bahan baku
        // menyimpan TEKS satuannya, bukan id -- jadi data lama tetap terbaca.
        $this->assertSoftDeleted('master_data', ['id' => $item->id]);

        $this->actingAs($superadmin)
            ->getJson('/api/master/satuan')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    #[Test]
    public function urutan_menentukan_susunan_tampil(): void
    {
        MasterData::factory()->create(['nama' => 'zzz', 'urutan' => 0]);
        MasterData::factory()->create(['nama' => 'aaa', 'urutan' => 5]);

        // urutan menang atas alfabet: satuan yang paling sering dipakai bisa
        // ditaruh di depan tanpa mengubah namanya.
        $this->actingAs($this->petugas())
            ->getJson('/api/master/satuan')
            ->assertOk()
            ->assertJsonPath('data.items.0.nama', 'zzz')
            ->assertJsonPath('data.items.1.nama', 'aaa');
    }

    #[Test]
    public function izin_kelola_master_terkirim_pada_profil(): void
    {
        $this->actingAs($this->superadmin())
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.izin.kelola_master', true);

        $this->actingAs($this->petugas())
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.izin.kelola_master', false);
    }
}
