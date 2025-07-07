<?php

use App\Livewire\Actions\Logout;
use App\Livewire\AthleteMonthlyForm;
use App\Livewire\TrainerFeedbackForm;
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

Route::post('logout', Logout::class)->name('logout');

Route::middleware([TrainerHashProtect::class])->group(function () {
    Route::get('/t/{hash}', [TrainerController::class, 'dashboard'])->name('trainers.dashboard');
    Route::get('/t/{hash}/athletes/{athlete}', [TrainerController::class, 'athlete'])->name('trainers.athlete');
    Route::get('/t/{hash}/feedbacks', [TrainerController::class, 'feedbacks'])->name('trainers.feedbacks');
    Route::get('/t/{hash}/feedbacks/form', TrainerFeedbackForm::class)->name('trainers.feedbacks.form');
});

Route::middleware([AthleteHashProtect::class])->group(function () {
    Route::get('/a/{hash}', [AthleteController::class, 'dashboard'])->name('athletes.dashboard');
    Route::get('/a/{hash}/metrics/daily/form', AthleteDailyMetricForm::class)->name('athletes.metrics.daily.form');
    Route::get('/a/{hash}/metrics/monthly/form', AthleteMonthlyForm::class)->name('athletes.metrics.monthly.form');
    Route::get('/a/{hash}/feedbacks', [AthleteController::class, 'feedbacks'])->name('athletes.feedbacks');
});

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
