<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Melengkapi data akun sendiri.
 *
 * Yang TIDAK bisa diubah dari sini: peran dan status aktif. Keduanya urusan
 * superadmin, bukan pemilik akun -- kalau tidak, petugas bisa menaikkan
 * perannya sendiri.
 */
class ProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Nomor HP',
            'jabatan' => 'Jabatan',
            'alamat' => 'Alamat',
        ];
    }
}
