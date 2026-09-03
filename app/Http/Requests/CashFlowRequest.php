<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\JenisKas;
use App\Enums\KategoriKas;
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

    /** `jenis` diturunkan dari kategori kalau tidak dikirim. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('kategori') && ! $this->filled('jenis')) {
            $kategori = KategoriKas::tryFrom((string) $this->input('kategori'));

            if ($kategori !== null) {
                $this->merge(['jenis' => $kategori->jenis()->value]);
            }
        }
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
            'metode' => ['nullable', Rule::in(['kas', 'transfer'])],
            'no_bukti' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** Kategori dan jenis harus konsisten (mis. 'bahan' tidak boleh 'masuk'). */
    public function after(): array
    {
        return [
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
        ];
    }
}
