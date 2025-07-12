<?php

use App\Livewire\Actions\Logout;
use App\Livewire\AthleteInjuryForm;
use App\Livewire\AthleteInjuryList;
use App\Livewire\AthleteInjuryShow;
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
    Route::get('/a/{hash}/injuries', AthleteInjuryList::class)->name('athletes.injuries.index');
    Route::get('/a/{hash}/injuries/create', AthleteInjuryForm::class)->name('athletes.injuries.create');
    Route::get('/a/{hash}/injuries/{injury}', AthleteInjuryShow::class)->name('athletes.injuries.show');
});
