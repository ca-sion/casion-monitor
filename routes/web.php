<?php

use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Appearance;
use Illuminate\Support\Facades\Route;
use App\Livewire\AthleteDailyMetricForm;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\TrainerController;
use App\Http\Middleware\AthleteHashProtect;
use App\Http\Middleware\TrainerHashProtect;
use App\Http\Controllers\AthleteMetricController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware([TrainerHashProtect::class])->group(function () {
    Route::get('/t/{hash}', [TrainerController::class, 'dashboard'])->name('trainers.dashboard');
    Route::get('/t/{hash}/athletes/{athlete}', [TrainerController::class, 'athlete'])->name('trainers.athlete');
});

Route::middleware([AthleteHashProtect::class])->group(function () {
    Route::get('/a/{hash}', [AthleteController::class, 'dashboard'])->name('athletes.dashboard');
    Route::get('/a/{hash}/metrics/daily/form', AthleteDailyMetricForm::class)->name('athletes.metrics.daily.form');
});

/*
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});
*/

require __DIR__.'/auth.php';

Route::prefix('api')->group(function () {
    Route::prefix('athletes')->group(function () {
        // Route pour obtenir les statistiques d'UNE seule métrique pour un athlète
        // Exemple : /api/athletes/1/metrics/morning_hrv/statistics?period=last_30_days
        Route::get('{athlete}/metrics/{metricTypeValue}/statistics', [AthleteMetricController::class, 'showSingleMetricStatistics']);

        // Route pour obtenir les statistiques de PLUSIEURS métriques pour un athlète (pour un graphique multi-séries)
        // Exemple : /api/athletes/1/metrics/multi-statistics?metric_types[]=post_session_subjective_fatigue&metric_types[]=morning_hrv&period=last_30_days
        Route::get('{athlete}/metrics/multi-statistics', [AthleteMetricController::class, 'showMultipleMetricStatistics']);
    });
    // Route pour obtenir la liste de tous les MetricTypes disponibles
    // Exemple : /api/metrics/available-types
    Route::get('metrics/available-types', [AthleteMetricController::class, 'getAvailableMetricTypes']);
});
