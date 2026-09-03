<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BahanBakuItem;
use App\Models\Kegiatan;
use App\Models\Lampiran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menguji tiga hal yang saling terkait:
 *   1. Batas hak akses superadmin vs petugas.
 *   2. Rincian bahan baku sebagai sumber angka Biaya Pelaksanaan Real.
 *   3. Jejak aktivitas yang terisi otomatis.
 */
class PeranDanBahanBakuTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create([
            'name' => 'Bayu Apriansah',
            'email' => 'super@taksasi.test',
            'password' => 'password123',
            'role' => User::SUPERADMIN,
            'is_active' => true,
        ]);
    }

    private function petugas(string $email = 'petugas@taksasi.test'): User
    {
        return User::factory()->create([
            'name' => 'Sinta Pratiwi',
            'email' => $email,
            'password' => 'password123',
            'role' => User::PETUGAS,
            'is_active' => true,
        ]);
    }

    private function kegiatan(array $override = []): Kegiatan
    {
        $k = Kegiatan::create(array_replace([
            'nama' => 'A',
            'kode' => 'KG-TEST-1',
            'pagu' => 400_000_000,
            'status' => 'berjalan',
            'rate_ppn' => 11,
            'rate_pph' => 1.75,
            'rate_rencana' => 60,
            'rate_kewajiban' => 12,
            'rate_administrasi' => 1,
            'rate_perusahaan' => 1.5,
            'rate_investor' => 50,
            'jml_owner' => 3,
        ], $override));

        $k->recalculate();

        return $k->fresh();
    }

    // ------------------------------------------------- membuat kegiatan ringkas

    #[Test]
    public function kegiatan_bisa_dibuat_hanya_dengan_nama_dan_pagu(): void
    {
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/kegiatan', [
            'nama' => 'Pembangunan Drainase Blok A',
            'pagu' => 400_000_000,
        ]);

        $response->assertCreated();

        $kegiatan = Kegiatan::firstWhere('nama', 'Pembangunan Drainase Blok A');

        $this->assertNotNull($kegiatan);

        // Persentase TIDAK diisi apa pun: kegiatan baru benar-benar kosong,
        // dan pengguna yang menentukannya sendiri di halaman detail.
        $this->assertEquals(0.0, (float) $kegiatan->rate_ppn);
        $this->assertEquals(0.0, (float) $kegiatan->rate_pph);
        $this->assertEquals(0.0, (float) $kegiatan->rate_rencana);
        $this->assertEquals(0.0, (float) $kegiatan->rate_investor);

        // Penanda inilah yang dipakai aplikasi untuk menyembunyikan bagian
        // taksasi selama persentasenya belum ditentukan.
        $this->assertFalse($kegiatan->rateTerisi());

        // Pagu tersimpan, dan tanpa pajak netto memang sama dengan pagu.
        $this->assertSame(400_000_000, (int) $kegiatan->pagu);
        $this->assertSame(400_000_000, (int) $kegiatan->netto);

        // Yang penting: tidak ada belanja yang dikarang. Dulu nilainya
        // diproyeksikan dari Rencana sehingga kegiatan baru terbaca seolah
        // belanjanya sudah berjalan. Diambil dari hitung(), bukan dari kolom
        // -- pelaksanaan_real di tabel adalah nilai MANUAL (null bila kosong),
        // sedangkan yang dipakai perhitungan adalah hasil turunannya.
        $this->assertNull($kegiatan->pelaksanaan_real, 'tidak ada nilai manual');
        $this->assertSame(0, $kegiatan->hitung()->pelaksanaan_real);
    }

    #[Test]
    public function rate_yang_dikirim_tersimpan_sisanya_tetap_nol(): void
    {
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->postJson('/api/kegiatan', [
            'nama' => 'Rate Khusus',
            'pagu' => 400_000_000,
            'rate_pph' => 2.65,
            'jml_owner' => 2,
        ])->assertCreated();

        $kegiatan = Kegiatan::firstWhere('nama', 'Rate Khusus');

        $this->assertEquals(2.65, (float) $kegiatan->rate_pph);
        $this->assertSame(2, (int) $kegiatan->jml_owner);

        // Yang tidak dikirim tetap nol -- tidak ada yang menambahkannya
        // diam-diam di belakang pengguna.
        $this->assertEquals(0.0, (float) $kegiatan->rate_ppn);
        $this->assertEquals(0.0, (float) $kegiatan->rate_rencana);

        // Satu rate saja sudah cukup membuat taksasi dianggap terisi.
        $this->assertTrue($kegiatan->rateTerisi());
    }

    #[Test]
    public function mengisi_persentase_memunculkan_angka_sesuai_excel(): void
    {
        $superadmin = $this->superadmin();

        $buat = $this->actingAs($superadmin)->postJson('/api/kegiatan', [
            'nama' => 'Alur Excel',
            'pagu' => 400_000_000,
        ])->assertCreated();

        $id = $buat->json('data.id');

        // Persentase diisi belakangan, persis alur di aplikasi.
        $this->actingAs($superadmin)->patchJson("/api/kegiatan/{$id}", [
            'rate_ppn' => 11,
            'rate_pph' => 1.75,
            'rate_rencana' => 60,
            'rate_kewajiban' => 12,
            'rate_administrasi' => 1,
            'rate_perusahaan' => 1.5,
            'rate_investor' => 50,
            'jml_owner' => 3,
            'pelaksanaan_real' => 209_400_000,
        ])->assertOk();

        $kegiatan = Kegiatan::find($id);
        $hasil = $kegiatan->hitung();

        // Angka-angka dari sheet "Taksasi Pekerjaan".
        $this->assertSame(44_000_000, $hasil->ppn);
        $this->assertSame(7_000_000, $hasil->pph);
        $this->assertSame(349_000_000, $hasil->netto);
        $this->assertSame(88_995_000, $hasil->profit_kotor);
        $this->assertSame(14_832_500, $hasil->hasil_bersih_per_owner);
        $this->assertTrue($kegiatan->rateTerisi());
    }

    #[Test]
    public function sisa_data_kegiatan_bisa_dilengkapi_lewat_patch(): void
    {
        $superadmin = $this->superadmin();
        $kegiatan = $this->kegiatan();

        // Hanya sebagian bidang yang dikirim -- ini yang dipakai halaman
        // detail untuk melengkapi data setelah kegiatan dibuat ringkas.
        $this->actingAs($superadmin)
            ->patchJson("/api/kegiatan/{$kegiatan->id}", [
                'lokasi' => 'Kelurahan Sidorejo',
                'sumber_dana' => 'APBD 2026',
                'tanggal_mulai' => '2026-09-01',
            ])
            ->assertOk();

        $kegiatan->refresh();

        $this->assertSame('Kelurahan Sidorejo', $kegiatan->lokasi);
        $this->assertSame('APBD 2026', $kegiatan->sumber_dana);
        // Nama dan pagu tidak ikut berubah walau tidak dikirim.
        $this->assertSame('A', $kegiatan->nama);
        $this->assertSame(400_000_000, (int) $kegiatan->pagu);
    }

    // ------------------------------------------------------------------ peran

    #[Test]
    public function petugas_tidak_boleh_mengelola_kegiatan_pengaturan_dan_akun(): void
    {
        $petugas = $this->petugas();
        $kegiatan = $this->kegiatan();

        $this->actingAs($petugas)
            ->postJson('/api/kegiatan', ['nama' => 'Baru', 'pagu' => 1_000_000])
            ->assertStatus(403);

        $this->actingAs($petugas)
            ->putJson("/api/kegiatan/{$kegiatan->id}", ['nama' => 'Diubah'])
            ->assertStatus(403);

        $this->actingAs($petugas)
            ->deleteJson("/api/kegiatan/{$kegiatan->id}")
            ->assertStatus(403);

        // Endpoint default-rates sudah dicabut. Pengaturan yang kini dijaga
        // superadmin adalah data master.
        $this->actingAs($petugas)
            ->postJson('/api/master/satuan', ['nama' => 'coba'])
            ->assertStatus(403);

        $this->actingAs($petugas)->getJson('/api/pengguna')->assertStatus(403);
        $this->actingAs($petugas)->getJson('/api/aktivitas')->assertStatus(403);
    }

    #[Test]
    public function petugas_hanya_boleh_kas_upah_dan_administrasi(): void
    {
        $petugas = $this->petugas();
        $kegiatan = $this->kegiatan();

        foreach (['upah', 'administrasi'] as $boleh) {
            $this->actingAs($petugas)
                ->postJson("/api/kegiatan/{$kegiatan->id}/cash-flows", [
                    'tanggal' => '2026-09-03',
                    'kategori' => $boleh,
                    'nominal' => 1_000_000,
                    'uraian' => 'Uji '.$boleh,
                ])
                ->assertStatus(201);
        }

        foreach (['termin', 'kewajiban', 'ppn', 'bagi_hasil_investor'] as $terlarang) {
            $this->actingAs($petugas)
                ->postJson("/api/kegiatan/{$kegiatan->id}/cash-flows", [
                    'tanggal' => '2026-09-03',
                    'kategori' => $terlarang,
                    'nominal' => 1_000_000,
                    'uraian' => 'Uji '.$terlarang,
                ])
                ->assertStatus(403);
        }
    }

    #[Test]
    public function superadmin_boleh_semua_kategori_kas(): void
    {
        $super = $this->superadmin();
        $kegiatan = $this->kegiatan();

        foreach (['termin', 'kewajiban', 'ppn', 'upah', 'administrasi'] as $kategori) {
            $this->actingAs($super)
                ->postJson("/api/kegiatan/{$kegiatan->id}/cash-flows", [
                    'tanggal' => '2026-09-03',
                    'kategori' => $kategori,
                    'nominal' => 1_000_000,
                    'uraian' => 'Uji '.$kategori,
                ])
                ->assertStatus(201);
        }
    }

    #[Test]
    public function izin_yang_dikirim_ke_aplikasi_cocok_dengan_yang_ditegakkan(): void
    {
        $this->actingAs($this->petugas())->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.izin.kelola_kegiatan', false)
            ->assertJsonPath('data.izin.kelola_pengguna', false)
            ->assertJsonPath('data.izin.lihat_aktivitas_semua', false)
            ->assertJsonPath('data.izin.kelola_bahan_baku', true)
            ->assertJsonPath('data.izin.kategori_kas', ['upah', 'administrasi']);

        $this->actingAs($this->superadmin())->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.izin.kelola_kegiatan', true)
            ->assertJsonPath('data.izin.kelola_pengguna', true)
            ->assertJsonPath('data.izin.lihat_aktivitas_semua', true);
    }

    // ------------------------------------------------------------ akun petugas

    #[Test]
    public function superadmin_membuat_akun_petugas_dan_perannya_selalu_petugas(): void
    {
        $super = $this->superadmin();

        // role sengaja dikirim "superadmin" untuk memastikan diabaikan.
        $this->actingAs($super)->postJson('/api/pengguna', [
            'name' => 'Rudi Hartono',
            'email' => 'rudi@taksasi.test',
            'password' => 'RahasiaKu9',
            'role' => User::SUPERADMIN,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.role', User::PETUGAS);

        $this->assertSame(1, User::query()->where('role', User::SUPERADMIN)->count());
    }

    #[Test]
    public function akun_superadmin_tidak_bisa_diubah_lewat_endpoint_pengguna(): void
    {
        $super = $this->superadmin();

        $this->actingAs($super)
            ->putJson("/api/pengguna/{$super->id}", ['name' => 'Diubah'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'SUPERADMIN_TERKUNCI');

        $this->actingAs($super)
            ->deleteJson("/api/pengguna/{$super->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function menonaktifkan_petugas_mencabut_seluruh_sesinya(): void
    {
        $super = $this->superadmin();
        $petugas = $this->petugas();

        $token = $this->postJson('/api/auth/login', [
            'email' => $petugas->email,
            'password' => 'password123',
        ])->assertOk()->json('data.access_token');

        $this->actingAs($super)->deleteJson("/api/pengguna/{$petugas->id}")->assertOk();

        $this->app['auth']->forgetGuards();

        $this->assertFalse($petugas->fresh()->is_active);
        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401);
    }

    // ------------------------------------------------------------- profil

    #[Test]
    public function pengguna_bisa_melengkapi_profilnya_tetapi_tidak_perannya(): void
    {
        $petugas = $this->petugas();

        $this->actingAs($petugas)->putJson('/api/auth/profil', [
            'name' => 'Sinta Pratiwi, S.T.',
            'phone' => '081234567890',
            'jabatan' => 'Pelaksana Lapangan',
            'alamat' => 'Jl. Merdeka 10',
            'role' => User::SUPERADMIN,   // harus diabaikan
        ])
            ->assertOk()
            ->assertJsonPath('data.jabatan', 'Pelaksana Lapangan')
            ->assertJsonPath('data.role', User::PETUGAS);

        $this->assertSame(User::PETUGAS, $petugas->fresh()->role);
    }

    // -------------------------------------------------------- bahan baku

    #[Test]
    public function total_bahan_baku_dijumlahkan_dari_item_bukan_diketik(): void
    {
        $petugas = $this->petugas();
        $kegiatan = $this->kegiatan();

        $items = [
            ['nama' => 'Besi beton 12mm', 'satuan' => 'batang', 'qty' => 250, 'harga_satuan' => 145_000],
            ['nama' => 'Semen PCC 50kg', 'satuan' => 'sak', 'qty' => 420, 'harga_satuan' => 72_000],
        ];

        foreach ($items as $item) {
            $this->actingAs($petugas)
                ->postJson("/api/kegiatan/{$kegiatan->id}/bahan-baku", $item)
                ->assertStatus(201);
        }

        // 250 x 145.000 = 36.250.000 ; 420 x 72.000 = 30.240.000
        $this->assertSame(66_490_000, $kegiatan->fresh()->totalBahanBaku());

        $this->actingAs($petugas)
            ->getJson("/api/kegiatan/{$kegiatan->id}/bahan-baku")
            ->assertOk()
            ->assertJsonPath('data.total_bahan_baku', 66_490_000)
            ->assertJsonPath('data.jumlah_item', 2);
    }

    #[Test]
    public function subtotal_dihitung_server_dan_qty_pecahan_didukung(): void
    {
        $petugas = $this->petugas();
        $kegiatan = $this->kegiatan();

        // Pecahan: 4,5 m3 pasir. Subtotal tidak pernah dikirim klien.
        $this->actingAs($petugas)->postJson("/api/kegiatan/{$kegiatan->id}/bahan-baku", [
            'nama' => 'Pasir beton',
            'satuan' => 'm3',
            'qty' => 4.5,
            'harga_satuan' => 320_000,
            'subtotal' => 999_999_999,   // harus diabaikan
        ])->assertStatus(201);

        $item = BahanBakuItem::query()->firstOrFail();

        $this->assertSame(1_440_000, (int) $item->subtotal);
    }

    #[Test]
    public function biaya_pelaksanaan_real_adalah_bahan_baku_plus_upah(): void
    {
        $petugas = $this->petugas();
        $kegiatan = $this->kegiatan();

        // Bahan baku 142.400.000 lewat satu item.
        $this->actingAs($petugas)->postJson("/api/kegiatan/{$kegiatan->id}/bahan-baku", [
            'nama' => 'Paket material',
            'qty' => 1,
            'harga_satuan' => 142_400_000,
        ])->assertStatus(201);

        // Upah 67.000.000 lewat kas.
        $this->actingAs($petugas)->postJson("/api/kegiatan/{$kegiatan->id}/cash-flows", [
            'tanggal' => '2026-08-15',
            'kategori' => 'upah',
            'nominal' => 67_000_000,
            'uraian' => 'Upah pekerja',
        ])->assertStatus(201);

        $segar = $kegiatan->fresh();

        $this->assertSame(142_400_000, $segar->totalBahanBaku());
        $this->assertSame(67_000_000, $segar->totalUpah());
        $this->assertSame(209_400_000, $segar->realisasiPelaksanaan());
        $this->assertSame('realisasi', $segar->sumberPelaksanaanReal());

        // Angka Excel harus utuh: 60% dari netto -> per owner 14.832.500
        $hasil = $segar->hitung();

        $this->assertSame(209_400_000, $hasil->pelaksanaan_real);
        $this->assertSame(88_995_000, $hasil->profit_kotor);
        $this->assertSame(14_832_500, $hasil->hasil_bersih_per_owner);
    }

    #[Test]
    public function menghapus_item_menghitung_ulang_profit(): void
    {
        $petugas = $this->petugas();
        $kegiatan = $this->kegiatan();

        $this->actingAs($petugas)->postJson("/api/kegiatan/{$kegiatan->id}/bahan-baku", [
            'nama' => 'Material', 'qty' => 1, 'harga_satuan' => 100_000_000,
        ])->assertStatus(201);

        $item = BahanBakuItem::query()->firstOrFail();
        $profitSebelum = $kegiatan->fresh()->profit_kotor;

        $this->actingAs($petugas)->deleteJson("/api/bahan-baku/{$item->id}")->assertOk();

        $segar = $kegiatan->fresh();

        $this->assertSame(0, $segar->totalBahanBaku());
        $this->assertNotSame($profitSebelum, $segar->profit_kotor);
        // Tanpa realisasi apa pun, kembali memakai proyeksi rencana.
        $this->assertSame('realisasi', $segar->sumberPelaksanaanReal());
    }

    // --------------------------------------------------------- lampiran

    #[Test]
    public function foto_struk_bisa_diunggah_dan_berkas_identik_ditolak(): void
    {
        Storage::fake(Lampiran::DISK);

        $petugas = $this->petugas();
        $kegiatan = $this->kegiatan();

        $foto = UploadedFile::fake()->image('struk.jpg', 900, 1400);

        $this->actingAs($petugas)
            ->postJson("/api/kegiatan/{$kegiatan->id}/lampiran", [
                'berkas' => $foto,
                'konteks' => 'biaya_pelaksanaan',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_gambar', true);

        $lampiran = Lampiran::query()->firstOrFail();

        Storage::disk(Lampiran::DISK)->assertExists($lampiran->path);

        // Berkas dengan isi identik ditolak agar foto struk tidak dobel.
        $this->actingAs($petugas)
            ->postJson("/api/kegiatan/{$kegiatan->id}/lampiran", [
                'berkas' => UploadedFile::fake()->createWithContent(
                    'struk.jpg',
                    Storage::disk(Lampiran::DISK)->get($lampiran->path),
                ),
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'LAMPIRAN_DUPLIKAT');
    }

    #[Test]
    public function berkas_bukan_gambar_atau_pdf_ditolak(): void
    {
        Storage::fake(Lampiran::DISK);

        $kegiatan = $this->kegiatan();

        $this->actingAs($this->petugas())
            ->postJson("/api/kegiatan/{$kegiatan->id}/lampiran", [
                'berkas' => UploadedFile::fake()->create('virus.exe', 10),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('berkas');
    }

    // ------------------------------------------------------- jejak aktivitas

    #[Test]
    public function setiap_perubahan_tercatat_di_jejak_aktivitas(): void
    {
        $petugas = $this->petugas();
        $kegiatan = $this->kegiatan();

        ActivityLog::query()->delete();

        $this->actingAs($petugas)->postJson("/api/kegiatan/{$kegiatan->id}/bahan-baku", [
            'nama' => 'Besi beton', 'qty' => 10, 'harga_satuan' => 145_000,
        ])->assertStatus(201);

        $log = ActivityLog::query()->latest('id')->firstOrFail();

        $this->assertSame($petugas->id, $log->user_id);
        $this->assertSame('Sinta Pratiwi', $log->user_nama);
        $this->assertSame(User::PETUGAS, $log->user_role);
        $this->assertSame('bahan_baku', $log->modul);
        $this->assertSame('Menambah item bahan baku', $log->aksi);
        $this->assertTrue($log->berhasil);
        $this->assertSame('Besi beton', $log->subjek_label);
    }

    #[Test]
    public function aksi_yang_ditolak_juga_tercatat(): void
    {
        $petugas = $this->petugas();

        ActivityLog::query()->delete();

        $this->actingAs($petugas)
            ->postJson('/api/kegiatan', ['nama' => 'Terlarang', 'pagu' => 1_000_000])
            ->assertStatus(403);

        $log = ActivityLog::query()->latest('id')->firstOrFail();

        // Percobaan yang gagal justru yang penting untuk audit.
        $this->assertFalse($log->berhasil);
        $this->assertSame(403, $log->status);
        $this->assertSame('Menambah kegiatan', $log->aksi);
    }

    #[Test]
    public function permintaan_baca_tidak_ikut_membanjiri_jejak(): void
    {
        $petugas = $this->petugas();

        ActivityLog::query()->delete();

        $this->actingAs($petugas)->getJson('/api/kegiatan')->assertOk();
        $this->actingAs($petugas)->getJson('/api/auth/me')->assertOk();

        $this->assertSame(0, ActivityLog::query()->count());
    }

    #[Test]
    public function password_tidak_pernah_masuk_ke_jejak(): void
    {
        $petugas = $this->petugas();

        ActivityLog::query()->delete();

        $this->postJson('/api/auth/login', [
            'email' => $petugas->email,
            'password' => 'password123',
        ])->assertOk();

        $log = ActivityLog::query()->latest('id')->firstOrFail();

        $json = json_encode($log->payload) ?: '';

        $this->assertStringNotContainsString('password123', $json);
        $this->assertArrayNotHasKey('password', (array) $log->payload);

        // Login tetap terhubung ke pemiliknya walau saat itu belum terautentikasi.
        $this->assertSame($petugas->id, $log->user_id);
    }

    #[Test]
    public function petugas_hanya_melihat_jejaknya_sendiri(): void
    {
        $super = $this->superadmin();
        $petugas = $this->petugas();
        $lain = $this->petugas('lain@taksasi.test');

        ActivityLog::query()->delete();

        $kegiatan = $this->kegiatan();

        $this->actingAs($petugas)->postJson("/api/kegiatan/{$kegiatan->id}/bahan-baku", [
            'nama' => 'Punya Sinta', 'qty' => 1, 'harga_satuan' => 1_000,
        ])->assertStatus(201);

        $this->actingAs($lain)->postJson("/api/kegiatan/{$kegiatan->id}/bahan-baku", [
            'nama' => 'Punya orang lain', 'qty' => 1, 'harga_satuan' => 2_000,
        ])->assertStatus(201);

        // Endpoint "saya" tidak punya cara menampilkan milik orang lain.
        $milikSendiri = $this->actingAs($petugas)->getJson('/api/aktivitas/saya')
            ->assertOk()
            ->json('data');

        foreach ($milikSendiri as $baris) {
            $this->assertSame($petugas->id, $baris['user_id']);
        }

        // Superadmin melihat keduanya.
        $semua = $this->actingAs($super)->getJson('/api/aktivitas')->assertOk()->json('data');

        $pelaku = array_unique(array_column($semua, 'user_id'));

        $this->assertContains($petugas->id, $pelaku);
        $this->assertContains($lain->id, $pelaku);
    }

    #[Test]
    public function ringkasan_aktivitas_hanya_untuk_superadmin(): void
    {
        $this->actingAs($this->petugas())
            ->getJson('/api/aktivitas/ringkasan')
            ->assertStatus(403);

        $this->actingAs($this->superadmin())
            ->getJson('/api/aktivitas/ringkasan')
            ->assertOk()
            ->assertJsonStructure(['data' => ['total', 'gagal', 'per_modul', 'per_pengguna', 'daftar_pengguna']]);
    }
}
