<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\JenisKas;
use App\Enums\KategoriKas;
use App\Enums\MetodeBayar;
use App\Support\Izin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CashFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Petugas hanya boleh menyentuh kategori Upah dan Administrasi;
        // kategori lain (termin, kewajiban, pajak, bagi hasil) khusus superadmin.
        $kategori = $this->input('kategori')
            ?? $this->route('cashFlow')?->kategori;

        return Izin::kelolaKas($this->user(), $kategori);
    }

    /** `jenis` diturunkan dari kategori, `dibayar` diturunkan dari status. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('kategori') && ! $this->filled('jenis')) {
            $kategori = KategoriKas::tryFrom((string) $this->input('kategori'));

            if ($kategori !== null) {
                $this->merge(['jenis' => $kategori->jenis()->value]);
            }
        }

        $this->merge(['dibayar' => $this->dibayarEfektif()]);
    }

    /**
     * Berapa yang benar-benar sudah dibayar.
     *
     * Klien mengirim `status_bayar` (lunas | belum) karena itu yang dipilih
     * pengguna di layar; jumlah rupiahnya diturunkan di sini supaya keduanya
     * tidak mungkin berselisih. Yang disimpan hanya angkanya.
     *
     * Pada PATCH, `nominal` boleh tidak ikut dikirim -- diambil dari baris
     * yang sedang diubah, bukan dianggap nol.
     */
    private function dibayarEfektif(): int
    {
        $lama = $this->route('cashFlow');

        $nominal = (int) round((float) ($this->input('nominal') ?? $lama?->nominal ?? 0));
        $status = $this->input('status_bayar');
        $metode = MetodeBayar::tryFrom((string) $this->input('metode', $lama?->metode?->value ?? 'kas'));

        if ($status === 'lunas') {
            return $nominal;
        }

        if ($status === 'belum') {
            return (int) round((float) $this->input('dibayar', 0));
        }

        // Tanpa status yang disebut: hutang dianggap belum dibayar sama
        // sekali, metode lain dianggap lunas. Ini yang membuat klien lama --
        // dan seluruh data sebelum kolom ini ada -- tetap berperilaku sama.
        if ($this->filled('dibayar')) {
            return (int) round((float) $this->input('dibayar'));
        }

        return $metode === MetodeBayar::Hutang ? 0 : $nominal;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'tanggal' => [$required, 'date'],
            'kategori' => [$required, Rule::in(Izin::kategoriKasYangBoleh($this->user()))],
            'jenis' => [$required, Rule::in(JenisKas::values())],
            'nominal' => [$required, 'numeric', 'min:1', 'max:999999999999999'],
            'uraian' => [$required, 'string', 'max:200'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'metode' => ['nullable', Rule::in(MetodeBayar::values())],
            'status_bayar' => ['nullable', Rule::in(['lunas', 'belum'])],
            'dibayar' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'no_bukti' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function after(): array
    {
        return [
            // Kategori dan jenis harus konsisten (mis. 'bahan' bukan 'masuk').
            function (Validator $validator): void {
                $kategori = KategoriKas::tryFrom((string) $this->input('kategori'));
                $jenis = (string) $this->input('jenis');

                if ($kategori !== null && $jenis !== '' && $kategori->jenis()->value !== $jenis) {
                    $validator->errors()->add('jenis', sprintf(
                        'Kategori "%s" hanya berlaku untuk jenis "%s".',
                        $kategori->label(),
                        $kategori->jenis()->value
                    ));
                }
            },

            // Yang dibayar tidak boleh melebihi nilainya sendiri.
            function (Validator $validator): void {
                $lama = $this->route('cashFlow');
                $nominal = (int) round((float) ($this->input('nominal') ?? $lama?->nominal ?? 0));
                $dibayar = (int) round((float) $this->input('dibayar', 0));

                if ($nominal > 0 && $dibayar > $nominal) {
                    $validator->errors()->add(
                        'dibayar',
                        'Jumlah yang sudah dibayar tidak boleh melebihi nominalnya.',
                    );

                    return;
                }

                // "Belum lunas" tetapi terbayar penuh adalah dua pernyataan
                // yang bertentangan. Ditolak, bukan diam-diam diubah, supaya
                // pengguna tahu pilihannya tidak tersimpan seperti dikira.
                if ($this->input('status_bayar') === 'belum' && $nominal > 0 && $dibayar >= $nominal) {
                    $validator->errors()->add(
                        'dibayar',
                        'Jumlahnya sudah menutup seluruh nominal. Pilih status Lunas.',
                    );
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'tanggal' => 'tanggal',
            'kategori' => 'kategori',
            'jenis' => 'jenis transaksi',
            'nominal' => 'nominal',
            'uraian' => 'uraian',
            'dibayar' => 'jumlah yang sudah dibayar',
            'status_bayar' => 'status pembayaran',
        ];
    }
}
