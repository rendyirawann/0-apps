<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StatusKegiatan;
use App\Support\Izin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class KegiatanRequest extends FormRequest
{
    /** Hanya superadmin yang boleh membuat/mengubah kegiatan dan persentasenya. */
    public function authorize(): bool
    {
        return Izin::kelolaKegiatan($this->user());
    }

    /*
     * Tidak ada prepareForValidation() yang mengisi rate dari default.
     *
     * Semula method itu menyuntikkan RateDefaults ke setiap permintaan POST,
     * sehingga kegiatan yang dibuat hanya dengan nama dan pagu tetap keluar
     * dengan PPN 11%, PPh 1,75%, dan seterusnya. Akibatnya halaman detail
     * menampilkan netto sampai profit per owner seolah sudah ditentukan
     * seseorang. Persentase sekarang diisi pengguna sendiri.
     */

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $id = $this->route('kegiatan')?->id;

        return [
            'nama' => [$required, 'string', 'max:150'],
            'kode' => ['nullable', 'string', 'max:40', Rule::unique('kegiatan', 'kode')->ignore($id)->whereNull('deleted_at')],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'lokasi' => ['nullable', 'string', 'max:150'],
            'sumber_dana' => ['nullable', 'string', 'max:100'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'status' => ['nullable', Rule::in(StatusKegiatan::values())],

            'pagu' => [$required, 'numeric', 'min:0', 'max:999999999999999'],

            // null = ambil dari total kas (bahan + upah)
            'pelaksanaan_real' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],

            // Persentase TIDAK wajib, bahkan saat membuat: kegiatan baru
            // cukup diisi nama dan pagu, lalu dilengkapi di halaman detail.
            // Yang tidak dikirim diisi controller dari default pengaturan,
            // sehingga transaksi tetap punya angka yang masuk akal sejak awal.
            'rate_ppn' => ['nullable', 'numeric', 'between:0,100'],
            'rate_pph' => ['nullable', 'numeric', 'between:0,100'],
            'rate_rencana' => ['nullable', 'numeric', 'between:0,100'],
            'rate_kewajiban' => ['nullable', 'numeric', 'between:0,100'],
            'rate_administrasi' => ['nullable', 'numeric', 'between:0,100'],
            'rate_perusahaan' => ['nullable', 'numeric', 'between:0,100'],
            'rate_investor' => ['nullable', 'numeric', 'between:0,100'],
            'jml_owner' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    /**
     * Total persen beban tidak boleh melebihi 100% dari netto, karena
     * sisanya adalah Profit Kotor -- kalau > 100% profitnya pasti minus.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $keys = ['rate_rencana', 'rate_kewajiban', 'rate_administrasi', 'rate_perusahaan'];

                $model = $this->route('kegiatan');
                $total = 0.0;

                foreach ($keys as $key) {
                    $total += (float) ($this->input($key) ?? $model?->{$key} ?? 0);
                }

                if ($total > 100.0) {
                    $validator->errors()->add(
                        'rate_rencana',
                        sprintf(
                            'Total persentase beban (Rencana + Kewajiban + Administrasi + Biaya Perusahaan) = %s%%, melebihi 100%%. Profit kotor akan minus.',
                            rtrim(rtrim(number_format($total, 3, ',', '.'), '0'), ',')
                        )
                    );
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'pagu' => 'Pagu',
            'rate_ppn' => 'persentase PPN',
            'rate_pph' => 'persentase PPh',
            'rate_rencana' => 'persentase Rencana Pelaksanaan',
            'rate_kewajiban' => 'persentase Biaya Kewajiban',
            'rate_administrasi' => 'persentase Administrasi',
            'rate_perusahaan' => 'persentase Biaya Perusahaan',
            'rate_investor' => 'persentase Bagi Hasil Investor',
            'jml_owner' => 'jumlah owner',
            'pelaksanaan_real' => 'Biaya Pelaksanaan Real',
        ];
    }
}
