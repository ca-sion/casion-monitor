<?php

namespace App\Notifications;

use App\Models\Athlete;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class SendMenstrualJ1Reminder extends Notification
{
    use Queueable;

    public function __construct(protected array $status) {}

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

    private function getTitle(): string
    {
        return $this->status['type'] === 'OVERDUE' ? 'Oubli du J1 ? 🌸' : 'Nouveau cycle en vue 🗓️';
    }

    private function getBody(Athlete $athlete): string
    {
        return $this->status['message'];
    }

    private function getUrl(Athlete $athlete): string
    {
        return route('athletes.menstrual-cycle.form', ['hash' => $athlete->hash]);
    }

    public function toWebPush(object $notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title($this->getTitle())
            ->body($this->getBody($notifiable))
            ->options([
                'TTL'     => 1000,
                'urgency' => 'normal',
                'icon'    => '/favicon-96x96.png',
            ])
            ->action('Saisir J1', 'open_url')
            ->data(['url' => $this->getUrl($notifiable)]);
    }

    public function toTelegram(object $notifiable)
    {
        return TelegramMessage::create()
            ->to($notifiable->routeNotificationFor('telegram'))
            ->content('*'.$this->getTitle()."*\n".$this->getBody($notifiable))
            ->button('Saisir J1', $this->getUrl($notifiable));
    }
}
