<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReminderService;
use App\Notifications\SendMenstrualJ1Reminder;

class SendMenstrualJ1Reminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:menstrual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send menstrual cycle J1 reminders to female athletes.';

    /**
     * Execute the console command.
     */
    public function handle(ReminderService $reminderService)
    {
        $this->info('Checking for athletes needing menstrual cycle reminders...');

        $athletes = $reminderService->getAthletesNeedingMenstrualNotification();

        if ($athletes->isEmpty()) {
            $this->info('No athletes need a menstrual reminder today.');

            return;
        }

        $count = 0;
        foreach ($athletes as $athlete) {
            $status = $reminderService->getMenstrualReminderStatus($athlete);
            if ($status) {
                $athlete->notify(new SendMenstrualJ1Reminder($status));
                $count++;
            }
        }

        $this->info("Sent {$count} menstrual J1 reminders.");
    }
}
