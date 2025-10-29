<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationPreference;
use App\Notifications\WebPushNotification;

class SendMonitoringReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily monitoring reminders to athletes and trainers.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentTime = now()->format('H:i');
        $currentDay = now()->dayOfWeek; // 0 for Sunday, 6 for Saturday

        $this->info("Checking for reminders at {$currentTime} on day {$currentDay}...");

        $preferences = NotificationPreference::query()
            ->where('notification_time', $currentTime)
            ->get();

        foreach ($preferences as $preference) {
            // Check if the current day is in the notification_days array
            if (in_array($currentDay, $preference->notification_days)) {
                $notifiable = $preference->notifiable;

                if ($notifiable && $notifiable->pushSubscriptions()->exists()) {
                    $this->info("Sending reminder to {$notifiable->name}...");

                    $notifiable->notify(new WebPushNotification(
                        'Rappel Monitoring',
                        "N'oubliez pas de remplir votre monitoring quotidien !",
                        $notifiable->accountLink
                    ));
                } else {
                    $this->warn("Notifiable {$notifiable->name} has no active push subscriptions.");
                }
            }
        }

        $this->info('Reminders check complete.');
    }
}