<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Izin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LampiranRequest extends FormRequest
{
    /** Batas ukuran per berkas, dalam kilobyte. */
    public const MAKS_KB = 8192;

    public function authorize(): bool
    {
        return Izin::kelolaLampiran($this->user());
    }

    public function rules(): array
    {
        return [
            // Foto dari kamera HP maupun berkas hasil pindai.
            'berkas' => [
                'required',
                'file',
                'max:'.self::MAKS_KB,
                'mimes:jpg,jpeg,png,webp,heic,heif,pdf',
            ],
            'konteks' => ['nullable', Rule::in(['biaya_pelaksanaan', 'administrasi', 'lain'])],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'berkas.required' => 'Pilih berkas atau ambil foto struk terlebih dahulu.',
            'berkas.max' => 'Ukuran berkas maksimal 8 MB.',
            'berkas.mimes' => 'Berkas harus berupa gambar (JPG, PNG, WEBP, HEIC) atau PDF.',
        ];
    }

    public function attributes(): array
    {
        return ['berkas' => 'Berkas lampiran'];
    }
}
