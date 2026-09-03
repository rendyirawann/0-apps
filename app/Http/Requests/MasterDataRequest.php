<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\JenisMaster;
use App\Support\Izin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Izin::kelolaMaster($this->user());
    }

    public function rules(): array
    {
        $tambah = $this->isMethod('POST');
        $id = $this->route('master')?->id;

        // Jenis diambil dari jalur saat menambah, dan tidak bisa diubah saat
        // menyunting -- memindahkan "sak" dari Satuan ke Toko tidak masuk akal.
        $jenis = $tambah
            ? $this->route('jenis')
            : $this->route('master')?->jenis?->value;

        return [
            'nama' => [
                $tambah ? 'required' : 'sometimes',
                'string',
                'max:100',
                Rule::unique('master_data', 'nama')
                    ->where(fn ($q) => $q->where('jenis', $jenis))
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'keterangan' => ['nullable', 'string', 'max:200'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['nama' => 'Nama', 'keterangan' => 'Keterangan', 'urutan' => 'Urutan'];
    }

    public function messages(): array
    {
        return ['nama.unique' => 'Nama itu sudah ada di daftar ini.'];
    }

    public function jenisTerpilih(): JenisMaster
    {
        return JenisMaster::from((string) $this->route('jenis'));
    }
}
