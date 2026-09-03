<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Kegiatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kegiatan>
 */
class KegiatanFactory extends Factory
{
    protected $model = Kegiatan::class;

    /**
     * Rate bawaannya sengaja sama dengan contoh di Excel (Pagu 400jt ->
     * hasil bersih per owner Rp14.832.500), sehingga test yang tidak peduli
     * pada angka tetap memakai kombinasi yang sudah terbukti benar.
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->randomElement(['A', 'B', 'C']).' '.fake()->numerify('##'),
            'kode' => 'KG-'.fake()->unique()->numerify('####-###'),
            'keterangan' => null,
            'lokasi' => fake()->city(),
            'sumber_dana' => 'APBD',
            'tanggal_mulai' => now()->subMonths(2)->toDateString(),
            'tanggal_selesai' => null,
            'status' => 'berjalan',

            'pagu' => 400_000_000,
            'pelaksanaan_real' => null,

            'rate_ppn' => 11,
            'rate_pph' => 1.75,
            'rate_rencana' => 60,
            'rate_kewajiban' => 12,
            'rate_administrasi' => 1,
            'rate_perusahaan' => 1.5,
            'rate_investor' => 50,
            'jml_owner' => 3,
        ];
    }

    /**
     * Kolom hasil (netto, profit, dst) tidak fillable dan hanya terisi lewat
     * recalculate(). Dijalankan otomatis di sini supaya kegiatan hasil factory
     * langsung punya snapshot yang benar, seperti lewat controller.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (Kegiatan $kegiatan) => $kegiatan->recalculate());
    }

    public function pagu(int $rupiah): static
    {
        return $this->state(['pagu' => $rupiah]);
    }

    /** Biaya pelaksanaan diisi manual, bukan diturunkan dari realisasi. */
    public function pelaksanaanManual(int $rupiah): static
    {
        return $this->state(['pelaksanaan_real' => $rupiah]);
    }

    public function selesai(): static
    {
        return $this->state([
            'status' => 'selesai',
            'tanggal_selesai' => now()->toDateString(),
        ]);
    }
}
