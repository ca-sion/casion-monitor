<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReminderService;
use App\Notifications\SendMonthlyMetricReminder;

class SendMonthlyMetricReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:monthly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send monthly metric reminders to athletes who haven\'t filled them.';

    /**
     * Execute the console command.
     */
    public function handle(ReminderService $reminderService)
    {
        $this->info('Checking for athletes who haven\'t filled their monthly metrics...');

        $athletes = $reminderService->getAthletesNeedingMonthlyReminder();

        if ($athletes->isEmpty()) {
            $this->info('All athletes have filled their monthly metrics.');
            return;
        }

        $count = 0;
        foreach ($athletes as $athlete) {
            if ($athlete->pushSubscriptions()->exists() || $athlete->telegram_chat_id) {
                $athlete->notify(new SendMonthlyMetricReminder());
                $count++;
            }
        }

        $this->info("Sent {$count} monthly metric reminders.");
    }
}
