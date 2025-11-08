<?php

namespace App\Notifications;

use App\Models\Athlete;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class SendDailyReminder extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        if ($notifiable->telegram_chat_id) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    private function getTitle(Athlete $athlete): string
    {
        $streak = $athlete->metadata['gamification']['current_streak'] ?? 0;

        if ($streak > 1) {
            return "Bravo, continue ta série ! 🔥";
        }

        return "C'est l'heure de tes métriques !";
    }

    private function getBody(Athlete $athlete): string
    {
        $streak = $athlete->metadata['gamification']['current_streak'] ?? 0;
        $name = $athlete->first_name;

        if ($streak > 1) {
            return "Salut {$name}, tu es sur une série de {$streak} jours ! Ne la brise pas, saisis tes données maintenant.";
        }

        return "Salut {$name}, n'oublie pas de remplir tes données aujourd'hui.";
    }

    private function getUrl(Athlete $athlete): string
    {
        return $athlete->account_link;
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title($this->getTitle($notifiable))
            ->body($this->getBody($notifiable))
            ->options([
                'TTL'     => 1000, // Time to live in seconds
                'urgency' => 'normal',
                'icon'    => '/favicon-96x96.png', // Icon for the notification
            ])
            ->action('Ouvrir', 'open_url')
            ->data(['url' => $this->getUrl($notifiable)]);
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram(object $notifiable)
    {
        return TelegramMessage::create()
            ->to($notifiable->routeNotificationFor('telegram'))
            ->content('*'.$this->getTitle($notifiable)."*\n".$this->getBody($notifiable))
            ->button('Ouvrir', $this->getUrl($notifiable));
    }
}
