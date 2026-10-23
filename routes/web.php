<?php

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
Route::get('/profile/{slug}', Front\UserProfileController::class)->name('front.user.detail');
Route::get('/program/menjahit', Front\SewingProgramController::class)->name('front.program.sewing');
Route::get('/program/bimbingan-sosial', Front\SocialGuidanceProgramController::class)->name('front.program.social-guidance');
Route::get('/program/{program}', Front\ProgramDetailController::class)
    ->whereIn('program', ['montir-motor', 'montir-mobil', 'tata-boga'])
    ->name('front.program.detail');
