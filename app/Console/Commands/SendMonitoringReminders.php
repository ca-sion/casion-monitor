<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Athlete;
use Illuminate\Console\Command;
use App\Models\NotificationPreference;
use App\Notifications\SendDailyReminder;

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
    protected $description = 'Send daily monitoring reminders to athletes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentTime = now();
        $currentDay = $currentTime->dayOfWeek; // 0 for Sunday, 6 for Saturday

        // Determine the exact 15-minute mark this cron run is responsible for
        $responsibleMinute = floor($currentTime->minute / 15) * 15;
        $responsibleTime = $currentTime->copy()->minute($responsibleMinute)->second(0)->format('H:i');

        $this->info("Cron running at {$currentTime->format('H:i')}. Responsible for reminders at {$responsibleTime} on day {$currentDay}...");

        // Find preferences for athletes matching the rounded time and current day.
        $preferences = NotificationPreference::where('notifiable_type', Athlete::class)
            ->get()
            ->filter(function ($preference) use ($responsibleTime, $currentDay) {
                // Round the preference's notification_time to the nearest 15-minute interval
                $prefTime = Carbon::createFromFormat('H:i', $preference->notification_time);
                $roundedPrefMinute = round($prefTime->minute / 15) * 15;

                if ($roundedPrefMinute == 60) {
                    $roundedPrefTime = $prefTime->copy()->addHour()->minute(0)->format('H:i');
                } else {
                    $roundedPrefTime = $prefTime->copy()->minute($roundedPrefMinute)->second(0)->format('H:i');
                }

                // Check if the rounded time matches this cron's responsible time and day
                return $roundedPrefTime === $responsibleTime && in_array($currentDay, $preference->notification_days);
            });

        foreach ($preferences as $preference) {
            $athlete = $preference->notifiable;

            if ($athlete) {
                if ($athlete->pushSubscriptions()->exists() || $athlete->telegram_chat_id) {
                    $this->info("Sending reminder to athlete {$athlete->name} for {$preference->notification_time}...");
                    $athlete->notify(new SendDailyReminder);
                } else {
                    $this->warn("Athlete {$athlete->name} has no active push or Telegram subscriptions.");
                }
            }
        }

        $this->info('Reminders check complete.');
    }
}
