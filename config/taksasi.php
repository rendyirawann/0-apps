<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default persentase kegiatan baru
    |--------------------------------------------------------------------------
    | Ini HANYA nilai awal yang mengisi form. Rate final disimpan per-kegiatan
    | di tabel `kegiatan`, jadi mengubah nilai di sini tidak akan mengubah
    | angka kegiatan yang sudah tersimpan.
    |
    | Bisa dioverride lewat tabel `settings` (endpoint GET/PUT /api/settings).
    */
    'default_rates' => [
        'rate_ppn' => 11.0,
        'rate_pph' => 1.75,
        'rate_rencana' => 60.0,
        'rate_kewajiban' => 12.0,
        'rate_administrasi' => 1.0,
        'rate_perusahaan' => 1.5,
        'rate_investor' => 50.0,
        'jml_owner' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Umur token
    |--------------------------------------------------------------------------
    */
    'token_ttl_minutes' => env('SANCTUM_TOKEN_TTL', 1440),
    'biometric_ttl_days' => env('BIOMETRIC_TOKEN_TTL_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Identitas perusahaan (dipakai di header PDF)
    |--------------------------------------------------------------------------
    */
    'perusahaan' => [
        'nama' => env('COMPANY_NAME', 'CV. NAMA PERUSAHAAN'),
        'alamat' => env('COMPANY_ADDRESS', 'Alamat perusahaan, Kota, Provinsi'),
        'telepon' => env('COMPANY_PHONE', '-'),
        'email' => env('COMPANY_EMAIL', '-'),
    ],
];
