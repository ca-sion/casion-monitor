<?php

namespace App\Console\Commands;

use Carbon\Carbon;
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
        $currentTime = now();
        $currentDay = $currentTime->dayOfWeek; // 0 for Sunday, 6 for Saturday

        // Determine the exact 15-minute mark this cron run is responsible for
        // e.g., if cron runs at 08:00-08:14, it's responsible for 08:00
        // if cron runs at 08:15-08:29, it's responsible for 08:15
        $responsibleMinute = floor($currentTime->minute / 15) * 15;
        $responsibleTime = $currentTime->copy()->minute($responsibleMinute)->second(0)->format('H:i');

        $this->info("Cron running at {$currentTime->format('H:i')}. Responsible for reminders at {$responsibleTime} on day {$currentDay}...");

        $preferences = NotificationPreference::all(); // Fetch all preferences to process rounding in PHP

        foreach ($preferences as $preference) {
            // Round the preference's notification_time to the nearest 15-minute interval
            $prefTime = Carbon::createFromFormat('H:i', $preference->notification_time);
            $roundedPrefMinute = round($prefTime->minute / 15) * 15;
            if ($roundedPrefMinute == 60) { // Handle rounding up to next hour
                $roundedPrefTime = $prefTime->copy()->addHour()->minute(0)->format('H:i');
            } else {
                $roundedPrefTime = $prefTime->copy()->minute($roundedPrefMinute)->second(0)->format('H:i');
            }

            // Check if the rounded preference time matches the responsible time for this cron run
            if ($roundedPrefTime === $responsibleTime) {
                // Check if the current day is in the notification_days array
                if (in_array($currentDay, $preference->notification_days)) {
                    $notifiable = $preference->notifiable;

                    if ($notifiable && $notifiable->pushSubscriptions()->exists()) {
                        $this->info("Sending reminder to {$notifiable->name} for {$preference->notification_time} (rounded to {$roundedPrefTime})...");

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
        }

        $this->info('Reminders check complete.');
    }
}
