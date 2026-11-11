<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Front;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', Front\HomeController::class)->name('home');

/*
|--------------------------------------------------------------------------
| Login terpadu (satu halaman untuk semua role)
|--------------------------------------------------------------------------
| Redirect setelah login ditentukan oleh App\Models\User::dashboardUrl().
| Rate limit `throttle:login` (lihat AppServiceProvider): 5 percobaan/menit
| per email+IP, 10/menit per email, 20/menit per IP.
*/
Route::get('login', [LoginController::class, 'show'])->name('login');

Route::post('login', [LoginController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login.store');

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/profile/{slug}', Front\UserProfileController::class)->name('front.user.detail');
Route::get('/program/menjahit', Front\SewingProgramController::class)->name('front.program.sewing');
Route::get('/program/bimbingan-sosial', Front\SocialGuidanceProgramController::class)->name('front.program.social-guidance');
Route::get('/program/{program}', Front\ProgramDetailController::class)
    ->whereIn('program', ['montir-motor', 'montir-mobil', 'tata-boga'])
    ->name('front.program.detail');
