<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BahanBakuItem;
use App\Models\Kegiatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BahanBakuItem>
 */
class BahanBakuItemFactory extends Factory
{
    protected $model = BahanBakuItem::class;

    /** Bahan yang benar-benar lazim di proyek konstruksi, bukan lorem ipsum. */
    private const BAHAN = [
        ['Semen Tiga Roda', 'sak', 68_500],
        ['Besi beton 12mm', 'batang', 145_000],
        ['Pasir cor', 'm3', 320_000],
        ['Batu split 1/2', 'm3', 385_000],
        ['Bata merah', 'buah', 1_200],
        ['Keramik 40x40', 'm2', 78_000],
        ['Cat tembok interior', 'kaleng', 425_000],
        ['Kayu bekisting', 'lembar', 165_000],
    ];

    public function definition(): array
    {
        [$nama, $satuan, $harga] = fake()->randomElement(self::BAHAN);

        return [
            // Membuat kegiatannya sendiri bila tidak ditentukan, supaya item
            // tidak pernah yatim. Pakai ->untuk($kegiatan) bila item harus
            // menempel pada kegiatan yang sudah ada -- dan itu yang biasanya
            // dimaui, karena totalnya ikut menghitung ulang kegiatan itu.
            'kegiatan_id' => Kegiatan::factory(),

            'nama' => $nama,
            'satuan' => $satuan,
            'qty' => fake()->numberBetween(5, 250),
            'harga_satuan' => $harga,

            'tanggal_beli' => now()->subDays(fake()->numberBetween(1, 60))->toDateString(),
            'toko' => fake()->randomElement(['TB Sumber Jaya', 'TB Maju Bersama', 'UD Karya']),
            'keterangan' => null,
            'urutan' => 0,
        ];
    }

    public function untuk(Kegiatan $kegiatan): static
    {
        return $this->state(['kegiatan_id' => $kegiatan->id]);
    }

    /**
     * Subtotal ditentukan lewat qty x harga, bukan disetel langsung: kolom
     * subtotal selalu dihitung ulang oleh model, jadi menyetelnya akan
     * tertimpa dan menyesatkan pembaca test.
     */
    public function subtotal(int $rupiah): static
    {
        return $this->state(['qty' => 1, 'harga_satuan' => $rupiah]);
    }
}
