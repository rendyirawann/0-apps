<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BahanBakuController;
use App\Http\Controllers\Api\CashFlowController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\KegiatanController;
use App\Http\Controllers\Api\LampiranController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Dokumentasi Swagger: http://127.0.0.1:8000/api/documentation
| Spesifikasi JSON:    http://127.0.0.1:8000/docs
*/

Route::get('/health', HealthController::class)->name('api.health');

// ---------------------------------------------------------------------
// Publik (throttle ketat: endpoint ini menerima kredensial)
// ---------------------------------------------------------------------
Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/biometric/login', [AuthController::class, 'biometricLogin'])->name('api.auth.biometric.login');
});

// ---------------------------------------------------------------------
// Terproteksi
// ---------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function (): void {

    Route::prefix('auth')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::put('/profil', [AuthController::class, 'updateProfil'])->name('api.auth.profil.update');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('api.auth.change-password');
        Route::post('/biometric/enable', [AuthController::class, 'enableBiometric'])->name('api.auth.biometric.enable');
        Route::delete('/biometric', [AuthController::class, 'disableBiometric'])->name('api.auth.biometric.disable');
    });

    // ---- Referensi & default rate ----
    Route::prefix('referensi')->group(function (): void {
        Route::get('/', [ReferenceController::class, 'index'])->name('api.referensi.index');
        Route::get('/default-rates', [ReferenceController::class, 'defaultRates'])->name('api.referensi.rates');
        Route::put('/default-rates', [ReferenceController::class, 'updateDefaultRates'])->name('api.referensi.rates.update');
    });

    // ---- Kegiatan ----
    // `preview` didaftarkan lebih dulu agar tidak tertangkap {kegiatan}.
    Route::post('/kegiatan/preview', [KegiatanController::class, 'preview'])->name('api.kegiatan.preview');

    Route::apiResource('kegiatan', KegiatanController::class)
        ->parameters(['kegiatan' => 'kegiatan'])
        ->names('api.kegiatan');

    // ---- Arus kas milik satu kegiatan ----
    Route::prefix('kegiatan/{kegiatan}/cash-flows')->group(function (): void {
        Route::get('/rekap', [CashFlowController::class, 'rekap'])->name('api.kegiatan.cash-flows.rekap');
        Route::get('/', [CashFlowController::class, 'index'])->name('api.kegiatan.cash-flows.index');
        Route::post('/', [CashFlowController::class, 'store'])->name('api.kegiatan.cash-flows.store');
    });

    // ---- Arus kas lintas kegiatan ----
    Route::prefix('cash-flows')->group(function (): void {
        Route::get('/', [CashFlowController::class, 'all'])->name('api.cash-flows.index');
        Route::get('/{cashFlow}', [CashFlowController::class, 'show'])->name('api.cash-flows.show');
        Route::put('/{cashFlow}', [CashFlowController::class, 'update'])->name('api.cash-flows.update');
        Route::delete('/{cashFlow}', [CashFlowController::class, 'destroy'])->name('api.cash-flows.destroy');
    });

    // ---- Rincian bahan baku (sumber angka "Bahan Baku") ----
    Route::prefix('kegiatan/{kegiatan}/bahan-baku')->group(function (): void {
        Route::get('/', [BahanBakuController::class, 'index'])->name('api.bahan-baku.index');
        Route::post('/', [BahanBakuController::class, 'store'])->name('api.bahan-baku.store');
    });

    Route::prefix('bahan-baku')->group(function (): void {
        Route::put('/{bahanBaku}', [BahanBakuController::class, 'update'])->name('api.bahan-baku.update');
        Route::delete('/{bahanBaku}', [BahanBakuController::class, 'destroy'])->name('api.bahan-baku.destroy');
    });

    // ---- Lampiran bukti (foto struk belanja) ----
    Route::prefix('kegiatan/{kegiatan}/lampiran')->group(function (): void {
        Route::get('/', [LampiranController::class, 'index'])->name('api.lampiran.index');
        Route::post('/', [LampiranController::class, 'store'])->name('api.lampiran.store');
    });

    Route::prefix('lampiran')->group(function (): void {
        Route::get('/{lampiran}/berkas', [LampiranController::class, 'berkas'])->name('api.lampiran.berkas');
        Route::delete('/{lampiran}', [LampiranController::class, 'destroy'])->name('api.lampiran.destroy');
    });

    // ---- Akun petugas (khusus superadmin) ----
    Route::apiResource('pengguna', UserController::class)
        ->parameters(['pengguna' => 'user'])
        ->names('api.pengguna');

    // ---- Jejak aktivitas ----
    // "saya" didaftarkan lebih dulu agar tidak tertangkap rute lain, dan
    // dipisah dari "/" supaya petugas tidak punya jalan untuk melihat
    // aktivitas akun lain.
    Route::prefix('aktivitas')->group(function (): void {
        Route::get('/saya', [ActivityLogController::class, 'saya'])->name('api.aktivitas.saya');
        Route::get('/ringkasan', [ActivityLogController::class, 'ringkasan'])->name('api.aktivitas.ringkasan');
        Route::get('/', [ActivityLogController::class, 'index'])->name('api.aktivitas.index');
    });

    // ---- Laporan ----
    Route::prefix('laporan')->group(function (): void {
        Route::get('/ringkasan', [ReportController::class, 'ringkasan'])->name('api.laporan.ringkasan');
        Route::get('/rekap-kegiatan', [ReportController::class, 'rekapKegiatan'])->name('api.laporan.rekap');
        Route::get('/rekap-kegiatan/pdf', [ReportController::class, 'rekapPdf'])->name('api.laporan.rekap.pdf');
        Route::get('/arus-kas/pdf', [ReportController::class, 'arusKasPdf'])->name('api.laporan.arus-kas.pdf');
        Route::get('/kegiatan/{kegiatan}/pdf', [ReportController::class, 'kegiatanPdf'])->name('api.laporan.kegiatan.pdf');
    });
});

// ---------------------------------------------------------------------
// Fallback: URL API yang tidak dikenal tetap membalas JSON, bukan HTML
// ---------------------------------------------------------------------
Route::fallback(fn () => ApiResponse::error('Endpoint tidak ditemukan.', 404, code: 'NOT_FOUND'));
