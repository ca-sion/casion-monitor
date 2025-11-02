<?php

namespace App\Observers;

use App\Models\Feedback;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendAthleteFeedbackCreated;

class FeedbackObserver
{
    /**
     * Handle the Feedback "created" event.
     */
    public function created(Feedback $feedback): void
    {
        $byAthlete = $feedback->author_type == 'athlete';

        if ($byAthlete) {
            $athlete = $feedback->athlete;
            $trainers = $athlete->trainers;
            Notification::send($trainers, new SendAthleteFeedbackCreated($feedback, $athlete));
        }
    }

    /**
     * Handle the Feedback "updated" event.
     */
    public function updated(Feedback $feedback): void
    {
        //
    }

    /**
     * Handle the Feedback "deleted" event.
     */
    public function deleted(Feedback $feedback): void
    {
        //
    }

    /**
     * Handle the Feedback "restored" event.
     */
    public function restored(Feedback $feedback): void
    {
        //
    }

    /**
     * Handle the Feedback "force deleted" event.
     */
    public function forceDeleted(Feedback $feedback): void
    {
        //
    }
}
