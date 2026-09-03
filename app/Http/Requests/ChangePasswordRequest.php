<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required', 'string', 'confirmed', 'different:current_password',
                Password::min(8)->letters()->numbers(),
            ],
            // Cabut semua token perangkat lain setelah ganti password.
            'logout_other_devices' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ];
    }
}
