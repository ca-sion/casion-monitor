<?php

use App\Livewire\Actions\Logout;
use App\Livewire\AthleteSettings;
use App\Livewire\TrainerSettings;
use App\Livewire\AthleteInjuryForm;
use App\Livewire\AthleteInjuryList;
use App\Livewire\AthleteInjuryShow;
use App\Livewire\AthleteMonthlyForm;
use App\Livewire\AthleteFeedbackForm;
use App\Livewire\TrainerFeedbackForm;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Livewire\AthleteDailyMetricForm;
use App\Livewire\AthleteHealthEventForm;
use App\Livewire\TrainerHealthEventForm;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\TrainerController;
use App\Http\Middleware\AthleteHashProtect;
use App\Http\Middleware\TrainerHashProtect;
use App\Livewire\AthleteMenstrualCycleForm;
use App\Http\Controllers\ManifestController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('logout', Logout::class)->name('logout');

Route::get('/manifest.json', [ManifestController::class, 'generate'])->name('manifest.generate');

Route::middleware([TrainerHashProtect::class])->group(function () {
    Route::get('/t/{hash}', [TrainerController::class, 'dashboard'])->name('trainers.dashboard');
    Route::get('/t/{hash}/settings', TrainerSettings::class)->name('trainers.settings');
    Route::get('/t/{hash}/athletes/{athlete}', [TrainerController::class, 'athlete'])->name('trainers.athlete');
    Route::get('/t/{hash}/feedbacks', [TrainerController::class, 'feedbacks'])->name('trainers.feedbacks');
    Route::get('/t/{hash}/feedbacks/form', TrainerFeedbackForm::class)->name('trainers.feedbacks.form');
    Route::get('/t/{hash}/health-events/create', TrainerHealthEventForm::class)->name('trainers.health-events.create');
    Route::get('/t/{hash}/health-events/{healthEvent}/edit', TrainerHealthEventForm::class)->name('trainers.health-events.edit');
    Route::get('/t/{hash}/injuries/{injury}/health-events/create', TrainerHealthEventForm::class)->name('trainers.injuries.health-events.create');
});

Route::middleware([AthleteHashProtect::class])->group(function () {
    Route::get('/a/{hash}', [AthleteController::class, 'dashboard'])->name('athletes.dashboard');
    Route::get('/a/{hash}/settings', AthleteSettings::class)->name('athletes.settings');
    Route::get('/a/{hash}/journal', [AthleteController::class, 'journal'])->name('athletes.journal');
    Route::get('/a/{hash}/statistics', [AthleteController::class, 'statistics'])->name('athletes.statistics');
    Route::get('/a/{hash}/metrics/daily/form', AthleteDailyMetricForm::class)->name('athletes.metrics.daily.form');
    Route::get('/a/{hash}/metrics/monthly/form', AthleteMonthlyForm::class)->name('athletes.metrics.monthly.form');
    Route::get('/a/{hash}/feedbacks/create', AthleteFeedbackForm::class)->name('athletes.feedbacks.create');
    Route::get('/a/{hash}/menstrual-cycle/form', AthleteMenstrualCycleForm::class)->name('athletes.menstrual-cycle.form');
    Route::get('/a/{hash}/injuries', AthleteInjuryList::class)->name('athletes.injuries.index');
    Route::get('/a/{hash}/injuries/create', AthleteInjuryForm::class)->name('athletes.injuries.create');
    Route::get('/a/{hash}/injuries/{injury}', AthleteInjuryShow::class)->name('athletes.injuries.show');
    Route::get('/a/{hash}/health-events/create', AthleteHealthEventForm::class)->name('athletes.health-events.create');
    Route::get('/a/{hash}/health-events/{healthEvent}/edit', AthleteHealthEventForm::class)->name('athletes.health-events.edit');
    Route::get('/a/{hash}/injuries/{injury}/health-events/create', AthleteHealthEventForm::class)->name('athletes.injuries.health-events.create');
    Route::get('/a/{hash}/reports', [ReportController::class, 'showReport'])->name('athletes.reports.show');
    Route::get('/a/{hash}/reports/monthly', [ReportController::class, 'showMonthlyReport'])->name('athletes.reports.monthly');
    Route::get('/a/{hash}/reports/biannual', [ReportController::class, 'showBiannualReport'])->name('athletes.reports.biannual');
});

Route::get('/run/reminders', function () {
    Artisan::call('reminders:send');

    return response('Reminders command executed.', 200);
})->name('run.reminders');
