<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\UrlSubfolder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Menjaga prefiks subfolder pada URL yang dihasilkan Laravel; tidak
        // melakukan apa pun bila APP_URL tanpa subfolder. Lihat UrlSubfolder
        // untuk alasannya.
        UrlSubfolder::terapkan(config('app.url'));
    }
}
