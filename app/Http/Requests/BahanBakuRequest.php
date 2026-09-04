<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Izin;
use Illuminate\Foundation\Http\FormRequest;

class BahanBakuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Izin::kelolaBahanBaku($this->user());
    }

    public function rules(): array
    {
        $wajib = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'nama' => [$wajib, 'string', 'max:150'],
            'satuan' => ['nullable', 'string', 'max:30'],

            // Pecahan diizinkan: 4,5 m3 pasir.
            'qty' => [$wajib, 'numeric', 'min:0.001', 'max:9999999'],
            'harga_satuan' => [$wajib, 'numeric', 'min:0', 'max:999999999999'],

            'tanggal_beli' => ['nullable', 'date'],
            'toko' => ['nullable', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama' => 'Nama Bahan',
            'satuan' => 'Satuan',
            'qty' => 'Jumlah',
            'harga_satuan' => 'Harga Satuan',
            'tanggal_beli' => 'Tanggal Beli',
            'toko' => 'Toko',
        ];
    }
}
