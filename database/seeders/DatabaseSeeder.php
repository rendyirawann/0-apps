<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\KategoriKas;
use App\Models\BahanBakuItem;
use App\Models\CashFlow;
use App\Models\Kegiatan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedSettings();
        $this->seedKegiatan();
    }

    /**
     * Satu superadmin, beberapa petugas.
     *
     * Superadmin sengaja hanya dibuat di sini — tidak ada endpoint yang bisa
     * menciptakan superadmin kedua.
     */
    private function seedUsers(): void
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

        $this->command->info("Superadmin : superadmin@taksasi.test / password123 (id {$super->id})");
        $this->command->info('Petugas    : sinta@taksasi.test / password123');
        $this->command->info('Petugas    : rudi@taksasi.test  / password123');
    }

    /** Default rate awal = angka pada sheet Excel. */
    private function seedSettings(): void
    {
        $rows = [
            ['rate_ppn', '11', 'percent', 'Default PPN (%)'],
            ['rate_pph', '1.75', 'percent', 'Default PPh (%)'],
            ['rate_rencana', '60', 'percent', 'Default Rencana Pelaksanaan (%)'],
            ['rate_kewajiban', '12', 'percent', 'Default Biaya Kewajiban (%)'],
            ['rate_administrasi', '1', 'percent', 'Default Administrasi (%)'],
            ['rate_perusahaan', '1.5', 'percent', 'Default Biaya Perusahaan (%)'],
            ['rate_investor', '50', 'percent', 'Default Bagi Hasil Investor (%)'],
            ['jml_owner', '3', 'int', 'Default jumlah owner'],
        ];

        foreach ($rows as [$key, $value, $type, $label]) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'label' => $label, 'group' => 'taksasi'],
            );
        }
    }

    private function seedKegiatan(): void
    {
        $petugasId = User::query()->where('email', 'sinta@taksasi.test')->value('id');
        $superId = User::query()->where('email', 'superadmin@taksasi.test')->value('id');

        // ------------------------------------------------------------------
        // 1. Kegiatan "A" -- persis contoh di Excel.
        //
        //    Bahan baku dari rincian item : 142.400.000
        //    Upah pekerja dari kas        :  67.000.000
        //    Biaya Pelaksanaan Real       : 209.400.000  (= 60% dari netto)
        //    -> hasil bersih per owner    :  14.832.500
        // ------------------------------------------------------------------
        $a = Kegiatan::query()->updateOrCreate(
            ['kode' => 'KG-2026-001'],
            [
                'nama' => 'A',
                'keterangan' => 'Contoh baris pertama pada sheet "Taksasi Pekerjaan".',
                'lokasi' => 'Kec. Cibitung',
                'sumber_dana' => 'APBD 2026',
                'tanggal_mulai' => Carbon::parse('2026-07-01'),
                'tanggal_selesai' => Carbon::parse('2026-10-31'),
                'status' => 'berjalan',
                'pagu' => 400_000_000,
                'pelaksanaan_real' => null, // otomatis dari rincian + kas upah
                'rate_ppn' => 11,
                'rate_pph' => 1.75,
                'rate_rencana' => 60,
                'rate_kewajiban' => 12,
                'rate_administrasi' => 1,
                'rate_perusahaan' => 1.5,
                'rate_investor' => 50,
                'jml_owner' => 3,
                'created_by' => $superId,
                'updated_by' => $superId,
            ],
        );

        $a->cashFlows()->forceDelete();
        $a->bahanBakuItems()->forceDelete();

        $this->seedKas($a, $superId, [
            ['2026-07-05', 'termin', 160_000_000, 'Termin I (40%)', 'transfer', 'SP2D/2026/0705'],
            ['2026-07-08', 'ppn', 44_000_000, 'Setoran PPN 11%', 'transfer', 'NTPN-PPN-0708'],
            ['2026-07-08', 'pph', 7_000_000, 'Setoran PPh final 1,75%', 'transfer', 'NTPN-PPH-0708'],
            ['2026-07-20', 'upah', 24_000_000, 'Upah pekerja minggu 1-2', 'kas', null],
            ['2026-08-15', 'upah', 27_000_000, 'Upah pekerja minggu 3-6', 'kas', null],
            ['2026-08-18', 'kewajiban', 41_880_000, 'Pembayaran biaya kewajiban 12%', 'transfer', 'KW/2026/08'],
            ['2026-08-25', 'administrasi', 3_490_000, 'Biaya administrasi & perizinan', 'kas', null],
            ['2026-09-01', 'termin', 240_000_000, 'Termin II (60%)', 'transfer', 'SP2D/2026/0901'],
            ['2026-09-02', 'upah', 16_000_000, 'Upah pekerja finishing', 'kas', null],
            ['2026-09-02', 'biaya_perusahaan', 5_235_000, 'Biaya operasional perusahaan 1,5%', 'transfer', null],
        ]);

        // qty x harga; totalnya tepat 142.400.000
        $this->seedBahanBaku($a, $petugasId, [
            ['Besi beton 12mm', 'batang', 250, 145_000, '2026-07-12', 'INV/BJ/1207', 'TB Sumber Jaya'],
            ['Semen PCC 50kg', 'sak', 420, 72_000, '2026-07-12', 'INV/BJ/1207', 'TB Sumber Jaya'],
            ['Pasir beton', 'm3', 40, 320_000, '2026-07-18', 'INV/BJ/1807', 'TB Sumber Jaya'],
            ['Split / koral', 'm3', 30, 385_000, '2026-07-18', 'INV/BJ/1807', 'TB Sumber Jaya'],
            ['Batu bata merah', 'buah', 12_000, 1_150, '2026-08-02', 'INV/BJ/0208', 'TB Karya Mandiri'],
            ['Besi beton 8mm', 'batang', 180, 78_000, '2026-08-02', 'INV/BJ/0208', 'TB Karya Mandiri'],
            ['Kawat beton', 'kg', 150, 24_000, '2026-08-02', 'INV/BJ/0208', 'TB Karya Mandiri'],
            ['Multiplek bekisting 12mm', 'lembar', 60, 185_000, '2026-09-01', 'INV/BJ/0109', 'TB Karya Mandiri'],
            ['Cat & bahan finishing', 'lot', 1, 9_020_000, '2026-09-01', 'INV/BJ/0109', 'TB Karya Mandiri'],
        ]);

        $a->refresh()->recalculate();

        // ------------------------------------------------------------------
        // 2. Kegiatan dengan rate BERBEDA -- bukti rate disimpan per kegiatan.
        //    Pelaksanaan real diisi MANUAL, jadi rincian item di sini hanya
        //    dokumentasi dan tidak memengaruhi profit.
        // ------------------------------------------------------------------
        $b = Kegiatan::query()->updateOrCreate(
            ['kode' => 'KG-2026-002'],
            [
                'nama' => 'Rehabilitasi Saluran Irigasi Blok C',
                'keterangan' => 'Contoh rate berbeda: PPh 2,65% (non-kualifikasi kecil), rencana 65%, 2 owner.',
                'lokasi' => 'Kec. Tambun Selatan',
                'sumber_dana' => 'Dana Desa 2026',
                'tanggal_mulai' => Carbon::parse('2026-08-01'),
                'tanggal_selesai' => Carbon::parse('2026-11-30'),
                'status' => 'berjalan',
                'pagu' => 275_000_000,
                'pelaksanaan_real' => 172_000_000, // input manual (override)
                'rate_ppn' => 11,
                'rate_pph' => 2.65,
                'rate_rencana' => 65,
                'rate_kewajiban' => 10,
                'rate_administrasi' => 1.5,
                'rate_perusahaan' => 2,
                'rate_investor' => 40,
                'jml_owner' => 2,
                'created_by' => $superId,
                'updated_by' => $superId,
            ],
        );

        $b->cashFlows()->forceDelete();
        $b->bahanBakuItems()->forceDelete();

        $this->seedKas($b, $superId, [
            ['2026-08-05', 'termin', 137_500_000, 'Termin I (50%)', 'transfer', 'SP2D/2026/0805'],
            ['2026-08-10', 'modal_investor', 60_000_000, 'Setoran modal investor', 'transfer', 'MOD/2026/01'],
            ['2026-08-28', 'upah', 41_000_000, 'Upah pekerja Agustus', 'kas', null],
        ]);

        // total 131.000.000
        $this->seedBahanBaku($b, $petugasId, [
            ['Beton pracetak saluran U-30', 'unit', 220, 425_000, '2026-08-14', 'INV/IR/1408', 'PT Beton Nusantara'],
            ['Semen PCC 50kg', 'sak', 200, 72_000, '2026-08-14', 'INV/IR/1408', 'PT Beton Nusantara'],
            ['Pasir beton', 'm3', 35, 320_000, '2026-09-01', 'INV/IR/0109', 'TB Sumber Jaya'],
            ['Besi beton 10mm', 'batang', 100, 119_000, '2026-09-01', 'INV/IR/0109', 'TB Sumber Jaya'],
        ]);

        $b->refresh()->recalculate();

        // ------------------------------------------------------------------
        // 3. Kegiatan draft -- belum ada realisasi apa pun.
        // ------------------------------------------------------------------
        $c = Kegiatan::query()->updateOrCreate(
            ['kode' => 'KG-2026-003'],
            [
                'nama' => 'Pembangunan Turap Sungai Segmen 2',
                'keterangan' => 'Masih draft: belum ada rincian bahan baku maupun kas, angka memakai proyeksi rencana 60%.',
                'lokasi' => 'Kec. Babelan',
                'sumber_dana' => 'APBD 2026',
                'status' => 'draft',
                'pagu' => 950_000_000,
                'pelaksanaan_real' => null,
                'rate_ppn' => 11,
                'rate_pph' => 1.75,
                'rate_rencana' => 60,
                'rate_kewajiban' => 12,
                'rate_administrasi' => 1,
                'rate_perusahaan' => 1.5,
                'rate_investor' => 50,
                'jml_owner' => 3,
                'created_by' => $superId,
                'updated_by' => $superId,
            ],
        );

        $c->cashFlows()->forceDelete();
        $c->bahanBakuItems()->forceDelete();
        $c->refresh()->recalculate();

        $this->command->info('Kegiatan   : 3 baris contoh (KG-2026-001 s.d. 003)');
        $this->command->info(sprintf(
            'Kegiatan A : bahan baku %s + upah %s = %s',
            number_format($a->totalBahanBaku(), 0, ',', '.'),
            number_format($a->totalUpah(), 0, ',', '.'),
            number_format($a->realisasiPelaksanaan(), 0, ',', '.'),
        ));
    }

    /** @param  array<int, array{0:string,1:string,2:int,3:string,4:string,5:?string}>  $rows */
    private function seedKas(Kegiatan $kegiatan, ?int $userId, array $rows): void
    {
        foreach ($rows as [$tanggal, $kategori, $nominal, $uraian, $metode, $bukti]) {
            CashFlow::withoutEvents(function () use ($kegiatan, $userId, $tanggal, $kategori, $nominal, $uraian, $metode, $bukti): void {
                $kegiatan->cashFlows()->create([
                    'tanggal' => Carbon::parse($tanggal),
                    'kategori' => $kategori,
                    'jenis' => KategoriKas::from($kategori)->jenis()->value,
                    'nominal' => $nominal,
                    'uraian' => $uraian,
                    'metode' => $metode,
                    'no_bukti' => $bukti,
                    'created_by' => $userId,
                ]);
            });
        }
    }

    /**
     * @param  array<int, array{0:string,1:string,2:float|int,3:int,4:string,5:string,6:string}>  $rows
     */
    private function seedBahanBaku(Kegiatan $kegiatan, ?int $userId, array $rows): void
    {
        foreach ($rows as $i => [$nama, $satuan, $qty, $harga, $tanggal, $struk, $toko]) {
            // withoutEvents: recalculate() dipanggil sekali di akhir, bukan
            // setiap baris, agar seeding tidak menghitung ulang berkali-kali.
            BahanBakuItem::withoutEvents(function () use ($kegiatan, $userId, $nama, $satuan, $qty, $harga, $tanggal, $struk, $toko, $i): void {
                $kegiatan->bahanBakuItems()->create([
                    'nama' => $nama,
                    'satuan' => $satuan,
                    'qty' => $qty,
                    'harga_satuan' => $harga,
                    // subtotal dihitung model lewat event saving(); karena event
                    // dimatikan di sini, dihitung manual dengan rumus yang sama.
                    'subtotal' => (int) round((float) $qty * (float) $harga),
                    'tanggal_beli' => Carbon::parse($tanggal),
                    'no_struk' => $struk,
                    'toko' => $toko,
                    'urutan' => $i + 1,
                    'created_by' => $userId,
                ]);
            });
        }
    }
}
