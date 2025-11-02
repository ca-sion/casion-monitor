<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class SendDailyReminder extends Notification
{
    use Queueable;

    protected $title;

    protected $body;

    protected $url;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $body, $url = null)
    {
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
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

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable, $notification)
    {
        $message = (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->options([
                'TTL'     => 1000, // Time to live in seconds
                'urgency' => 'normal',
                'icon'    => '/favicon.png', // Icon for the notification
            ]);

        if ($this->url) {
            $message->action('Ouvrir', 'open_url');
            $message->data(['url' => $this->url]);
        }

        return $message;
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram(object $notifiable)
    {
        $message = TelegramMessage::create()
            ->to($notifiable->routeNotificationFor('telegram'))
            ->content('*'.$this->title."*\n".$this->body);

        if ($this->url) {
            $message->button('Ouvrir', $this->url);
        }

        return $message;
    }
}
