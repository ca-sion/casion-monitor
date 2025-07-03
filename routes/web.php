<?php

use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Appearance;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AthleteController;
use App\Http\Middleware\AthleteHashProtect;
use App\Livewire\AthleteMetricForm;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/go/{hash}', [AthleteController::class, 'go'])->name('athletes.go')->middleware(AthleteHashProtect::class);
Route::get('/go/{hash}/metrics/form', AthleteMetricForm::class)->name('athletes.go.metrics.form')->middleware(AthleteHashProtect::class);

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

require __DIR__.'/auth.php';
