<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Kegiatan;
use App\Models\Lampiran;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Lampiran>
 */
class LampiranFactory extends Factory
{
    protected $model = Lampiran::class;

    public function definition(): array
    {
        $nama = 'struk-'.fake()->unique()->numerify('#####').'.jpg';
        $isi = 'berkas-uji-'.fake()->uuid();

        return [
            'kegiatan_id' => Kegiatan::factory(),

            'konteks' => 'biaya_pelaksanaan',
            'path' => 'kegiatan/uji/'.$nama,
            'nama_asli' => $nama,
            'mime' => 'image/jpeg',
            'ukuran' => strlen($isi),

            // Hash dari isi yang sama dengan yang benar-benar ditulis, supaya
            // penolakan duplikat bisa diuji tanpa menebak-nebak nilainya.
            'hash' => hash('sha256', $isi),
            'keterangan' => null,
        ];
    }

    /**
     * Berkas fisiknya ikut ditulis ke disk (fake maupun nyata). Tanpa ini,
     * endpoint pengambilan berkas akan membalas 404 padahal barisnya ada --
     * kondisi yang tidak pernah terjadi di produksi.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Lampiran $lampiran): void {
            Storage::disk(Lampiran::DISK)->put(
                $lampiran->path,
                'berkas-uji-'.$lampiran->hash,
            );
        });
    }

    public function untuk(Kegiatan $kegiatan): static
    {
        return $this->state(['kegiatan_id' => $kegiatan->id]);
    }

    public function pdf(): static
    {
        $nama = 'struk-'.fake()->unique()->numerify('#####').'.pdf';

        return $this->state([
            'nama_asli' => $nama,
            'path' => 'kegiatan/uji/'.$nama,
            'mime' => 'application/pdf',
        ]);
    }

    public function konteks(string $konteks): static
    {
        return $this->state(['konteks' => $konteks]);
    }
}
