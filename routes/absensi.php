<?php

use App\Modules\Absensi\Http\Controllers\ApiController;
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
    // Kontrak native: satu endpoint `?action=` dengan method GET/POST saja
    // (bukan Route::any) agar method lain otomatis 405 oleh router.
    Route::match(['get', 'post'], 'api', ApiController::class)->name('api');
});
