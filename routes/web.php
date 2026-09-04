<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute peramban
|--------------------------------------------------------------------------
| Aplikasi ini khusus API; satu-satunya halaman yang dilayani adalah penanda
| bahwa servernya hidup. Halaman sambutan bawaan Laravel sengaja dibuang:
| ia menyebut nama dan versi kerangka kerjanya, memuat gaya dari CDN luar,
| dan memberi tautan ke dokumentasinya -- semuanya tidak berguna di sini dan
| justru memberi tahu siapa pun teknologi apa yang dipakai.
|
| Alamat dokumentasi TIDAK ditautkan dari sini. Halaman Swagger memang bisa
| dibuka, tetapi tidak perlu diiklankan di halaman depan.
*/
Route::get('/', fn () => view('status'))->name('status');
