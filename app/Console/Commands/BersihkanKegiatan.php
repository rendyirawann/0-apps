<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\BahanBakuItem;
use App\Models\CashFlow;
use App\Models\Kegiatan;
use App\Models\Lampiran;
use Database\Seeders\KegiatanContohSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mengosongkan data kegiatan beserta seluruh turunannya.
 *
 * SENGAJA berupa perintah artisan, bukan migrasi. Migrasi dijalankan otomatis
 * pada setiap deploy (`deploy.sh` memanggil `migrate --force`), jadi migrasi
 * yang menghapus data akan mengosongkan kegiatan berulang kali setiap rilis --
 * kehilangan data yang tidak bisa dipulihkan. Pembersihan data harus selalu
 * merupakan tindakan yang diminta secara sadar.
 *
 * Yang DIHAPUS: kegiatan, arus kas, rincian bahan baku, lampiran (baris
 * beserta berkas fisiknya).
 *
 * Yang DIPERTAHANKAN: akun pengguna dan data master. Keduanya tidak
 * bergantung pada kegiatan, dan menghapusnya berarti kehilangan akses masuk.
 *
 * Jejak aktivitas dipertahankan kecuali diminta dengan --aktivitas: ia catatan
 * audit, bukan data kegiatan, dan justru berguna untuk menjawab "siapa yang
 * mengosongkan datanya".
 */
class BersihkanKegiatan extends Command
{
    use ConfirmableTrait;

    protected $signature = 'kegiatan:bersihkan
        {--aktivitas : Ikut menghapus jejak aktivitas modul kegiatan, kas, bahan baku, dan lampiran}
        {--contoh : Isi ulang 3 kegiatan contoh setelah dibersihkan}
        {--force : Jalankan tanpa konfirmasi (untuk skrip)}';

    protected $description = 'Mengosongkan seluruh data kegiatan; akun dan data master dipertahankan';

    /**
     * Urutannya penting: tabel anak lebih dulu, meskipun `cascadeOnDelete`
     * sudah menangani -- supaya jumlah yang dilaporkan akurat dan tidak
     * bergantung pada perilaku cascade database tertentu.
     */
    private const TABEL = ['lampiran', 'bahan_baku_items', 'cash_flows', 'kegiatan'];

    public function handle(): int
    {
        $sebelum = $this->hitung();

        if (array_sum($sebelum) === 0) {
            $this->components->info('Tidak ada data kegiatan. Tidak ada yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Akan DIHAPUS</>', '');

        foreach ($sebelum as $nama => $jumlah) {
            $this->components->twoColumnDetail("  {$nama}", (string) $jumlah);
        }

        $berkas = $this->daftarBerkas();
        $this->components->twoColumnDetail('  berkas lampiran di disk', (string) count($berkas));

        if ($this->option('aktivitas')) {
            $this->components->twoColumnDetail(
                '  jejak aktivitas (kegiatan/kas/bahan baku/lampiran)',
                (string) $this->kueriAktivitas()->count(),
            );
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>Dipertahankan</>', '');
        $this->components->twoColumnDetail('  akun pengguna', (string) DB::table('users')->count());
        $this->components->twoColumnDetail('  data master', (string) DB::table('master_data')->count());

        if (! $this->option('aktivitas')) {
            $this->components->twoColumnDetail('  jejak aktivitas', (string) ActivityLog::query()->count());
        }

        $this->newLine();

        // confirmToProceed hanya bertanya saat APP_ENV=production, jadi di
        // lokal perintahnya tetap enak dipakai tanpa --force.
        if (! $this->confirmToProceed('Data kegiatan akan dihapus permanen dan tidak bisa dipulihkan.')) {
            return self::FAILURE;
        }

        // Berkas dihapus SEBELUM barisnya, selagi jalurnya masih terbaca dari
        // database. Kalau barisnya lebih dulu hilang, tidak ada lagi yang tahu
        // berkas mana yang harus dibuang dan disk-nya menyimpan bukti belanja
        // yang tidak bisa diakses siapa pun.
        $terhapus = $this->hapusBerkas($berkas);

        DB::transaction(function (): void {
            if ($this->option('aktivitas')) {
                $this->kueriAktivitas()->delete();
            }

            // TRUNCATE ... RESTART IDENTITY: sekalian mengembalikan urutan id
            // ke 1, supaya kegiatan pertama setelah ini tidak bernomor 7.
            // CASCADE dibutuhkan karena ketiga tabel anak memakai foreign key.
            DB::statement(sprintf(
                'TRUNCATE TABLE %s RESTART IDENTITY CASCADE',
                implode(', ', array_map(fn (string $t) => '"'.$t.'"', self::TABEL)),
            ));
        });

        $this->hapusFolderKosong();

        $this->newLine();
        $this->components->info(sprintf(
            'Data kegiatan dibersihkan. %d berkas lampiran dihapus dari disk.',
            $terhapus,
        ));

        if ($this->option('contoh')) {
            $this->newLine();
            $this->components->info('Mengisi ulang kegiatan contoh...');
            $this->call('db:seed', [
                '--class' => KegiatanContohSeeder::class,
                '--force' => true,
            ]);
        }

        $sesudah = $this->hitung();

        $this->newLine();
        foreach ($sesudah as $nama => $jumlah) {
            $this->components->twoColumnDetail($nama, (string) $jumlah);
        }

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function hitung(): array
    {
        return [
            'kegiatan' => Kegiatan::withTrashed()->count(),
            'arus kas' => CashFlow::withTrashed()->count(),
            'rincian bahan baku' => BahanBakuItem::withTrashed()->count(),
            'lampiran' => Lampiran::withTrashed()->count(),
        ];
    }

    /**
     * Jalur berkas dibaca dari database, bukan dari isi folder.
     *
     * Menghapus seluruh isi folder memang lebih singkat, tetapi berarti
     * menghapus apa pun yang ada di sana -- termasuk berkas yang mungkin
     * ditaruh proses lain. Yang dihapus di sini hanya yang benar-benar milik
     * lampiran yang tercatat.
     *
     * @return array<int, string>
     */
    private function daftarBerkas(): array
    {
        return Lampiran::withTrashed()->pluck('path')->filter()->unique()->values()->all();
    }

    /** @param array<int, string> $berkas */
    private function hapusBerkas(array $berkas): int
    {
        $disk = Storage::disk(Lampiran::DISK);
        $n = 0;

        foreach ($berkas as $path) {
            if ($disk->exists($path) && $disk->delete($path)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Folder yang jadi kosong ikut dibuang agar disk tetap rapi.
     *
     * Ditelusuri dari yang terdalam. Jalur lampiran bisa bersarang
     * (mis. "kegiatan/uji/berkas.jpg"), dan folder induk baru menjadi kosong
     * SETELAH anaknya dihapus -- memeriksa tingkat pertama saja menyisakan
     * rangkaian folder kosong.
     */
    private function hapusFolderKosong(): void
    {
        $disk = Storage::disk(Lampiran::DISK);

        // allDirectories() mengembalikan dari luar ke dalam; dibalik supaya
        // yang terdalam diperiksa lebih dulu.
        $folder = array_reverse($disk->allDirectories());

        foreach ($folder as $f) {
            if ($disk->files($f) === [] && $disk->directories($f) === []) {
                $disk->deleteDirectory($f);
            }
        }
    }

    /** @return Builder<ActivityLog> */
    private function kueriAktivitas()
    {
        return ActivityLog::query()->whereIn('modul', [
            'kegiatan', 'kas', 'bahan_baku', 'lampiran',
        ]);
    }
}
