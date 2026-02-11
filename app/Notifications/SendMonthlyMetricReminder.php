<?php

namespace App\Notifications;

use App\Models\Athlete;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class SendMonthlyMetricReminder extends Notification
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
        return 'Bilan mensuel 📊';
    }

    private function getBody(Athlete $athlete): string
    {
        $name = $athlete->first_name;

        return "Bonjour {$name}, n'oublie pas de compléter ton bilan mensuel ce mois-ci afin de suivre ton évolution sur le long terme.";
    }

    private function getUrl(Athlete $athlete): string
    {
        return route('athletes.metrics.monthly.form', ['hash' => $athlete->hash]);
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
                'TTL'     => 1000,
                'urgency' => 'normal',
                'icon'    => '/favicon-96x96.png',
            ])
            ->action('Compléter mon bilan', 'open_url')
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
            ->button('Compléter mon bilan', $this->getUrl($notifiable));
    }
}
