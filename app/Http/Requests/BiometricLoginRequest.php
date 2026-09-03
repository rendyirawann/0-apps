<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiometricLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'biometric_token' => ['required', 'string', 'min:32', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
