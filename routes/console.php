<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:send')->everyMinute();
Schedule::command('reminders:monthly')->monthlyOn(10, '09:00');
Schedule::command('reminders:menstrual')->dailyAt('08:30');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
