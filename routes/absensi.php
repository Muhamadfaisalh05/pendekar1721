<?php

use App\Modules\Absensi\Http\Controllers\ApiController;
use App\Modules\Absensi\Http\Controllers\PageController;
use App\Modules\Absensi\Http\Middleware\EnsureAbsensiAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Modul Absensi (SEMARAK)
|--------------------------------------------------------------------------
|
| Semua rute berada di bawah prefix `/absensi`, memakai session + CSRF dari
| grup `web`, dan hanya dapat diakses admin yang sudah login.
|
*/

Route::middleware(['auth', EnsureAbsensiAdmin::class])->group(function (): void {
    Route::get('/', [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('input', [PageController::class, 'input'])->name('input');
    Route::get('anak', [PageController::class, 'anak'])->name('anak');
    Route::get('summary', [PageController::class, 'summary'])->name('summary');
    Route::get('rapor', [PageController::class, 'rapor'])->name('rapor');
    Route::get('pengaturan', [PageController::class, 'pengaturan'])->name('pengaturan');

    // Kontrak native: satu endpoint `?action=` dengan method GET/POST saja
    // (bukan Route::any) agar method lain otomatis 405 oleh router.
    Route::get('api', ApiController::class)
        ->middleware('throttle:absensi-api')
        ->name('api');

    Route::post('api', ApiController::class)
        ->middleware('throttle:absensi-writes')
        ->name('api.write');
});

