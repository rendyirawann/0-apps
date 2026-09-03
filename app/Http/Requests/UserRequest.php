<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Izin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Izin::kelolaPengguna($this->user());
    }

    public function rules(): array
    {
        $tambah = $this->isMethod('POST');
        $id = $this->route('user')?->id;

        return [
            'name' => [$tambah ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [
                $tambah ? 'required' : 'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'password' => [
                $tambah ? 'required' : 'nullable',
                'string',
                Password::min(8)->letters()->numbers(),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],

            // Peran sengaja TIDAK bisa diubah lewat endpoint ini: superadmin
            // hanya satu dan ditetapkan lewat seeder, sehingga tidak mungkin
            // ada dua superadmin karena salah klik.
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'password' => 'Password',
            'phone' => 'Nomor HP',
        ];
    }

    /** Data yang benar-benar disimpan; peran selalu petugas. */
    public function dataPetugas(): array
    {
        $data = $this->safe()->except(['password']);
        $data['role'] = User::PETUGAS;

        if ($this->filled('password')) {
            $data['password'] = $this->input('password');
        }

        return $data;
    }
}
