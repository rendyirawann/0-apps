<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KategoriKas;
use App\Models\CashFlow;
use App\Models\Kegiatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashFlow>
 */
class CashFlowFactory extends Factory
{
    protected $model = CashFlow::class;

    public function definition(): array
    {
        $kategori = fake()->randomElement(KategoriKas::dapatDipilih());

        return [
            'kegiatan_id' => Kegiatan::factory(),

            'tanggal' => now()->subDays(fake()->numberBetween(1, 60))->toDateString(),

            // jenis diturunkan dari kategori, bukan diacak sendiri: kombinasi
            // keduanya divalidasi server, jadi factory tidak boleh bisa
            // menghasilkan baris yang lewat validasi controller pun tidak.
            'jenis' => $kategori->jenis()->value,
            'kategori' => $kategori->value,

            'nominal' => fake()->numberBetween(1, 200) * 100_000,
            'uraian' => fake()->sentence(4),
            'keterangan' => null,
            'metode' => 'kas',
            'no_bukti' => 'BK/'.fake()->numerify('####'),
        ];
    }

    public function untuk(Kegiatan $kegiatan): static
    {
        return $this->state(['kegiatan_id' => $kegiatan->id]);
    }

    public function kategori(KategoriKas|string $kategori): static
    {
        $k = $kategori instanceof KategoriKas ? $kategori : KategoriKas::from($kategori);

        return $this->state([
            'kategori' => $k->value,
            'jenis' => $k->jenis()->value,
        ]);
    }

    public function nominal(int $rupiah): static
    {
        return $this->state(['nominal' => $rupiah]);
    }

    /** Upah adalah satu-satunya kategori kas yang menambah Biaya Pelaksanaan Real. */
    public function upah(int $rupiah): static
    {
        return $this->kategori(KategoriKas::Upah)->nominal($rupiah);
    }
}
