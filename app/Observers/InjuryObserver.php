<?php

namespace App\Observers;

use App\Models\Injury;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendAthleteInjuryCreated;

class InjuryObserver
{
    /**
     * Handle the Injury "created" event.
     */
    public function created(Injury $injury): void
    {
        $athlete = $injury->athlete;
        $trainers = $athlete?->trainers;
        Notification::send($trainers, new SendAthleteInjuryCreated($injury, $athlete));

    }

    /**
     * Handle the Injury "updated" event.
     */
    public function updated(Injury $injury): void
    {
        //
    }

    /**
     * Handle the Injury "deleted" event.
     */
    public function deleted(Injury $injury): void
    {
        //
    }

    /**
     * Handle the Injury "restored" event.
     */
    public function restored(Injury $injury): void
    {
        //
    }

    /**
     * Handle the Injury "force deleted" event.
     */
    public function forceDeleted(Injury $injury): void
    {
        //
    }
}
