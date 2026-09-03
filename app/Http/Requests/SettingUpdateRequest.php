<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Izin;
use Illuminate\Foundation\Http\FormRequest;

class SettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Izin::kelolaPengaturan($this->user());
    }

    public function rules(): array
    {
        return [
            'rate_ppn' => ['sometimes', 'numeric', 'between:0,100'],
            'rate_pph' => ['sometimes', 'numeric', 'between:0,100'],
            'rate_rencana' => ['sometimes', 'numeric', 'between:0,100'],
            'rate_kewajiban' => ['sometimes', 'numeric', 'between:0,100'],
            'rate_administrasi' => ['sometimes', 'numeric', 'between:0,100'],
            'rate_perusahaan' => ['sometimes', 'numeric', 'between:0,100'],
            'rate_investor' => ['sometimes', 'numeric', 'between:0,100'],
            'jml_owner' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
