<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class WebPushNotification extends Notification
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
        return [WebPushChannel::class];
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->action('Ouvrir', $this->url) // Define an action button and set its target URL
            ->options([
                'TTL' => 1000, // Time to live in seconds
                'urgency' => 'normal',
                'icon' => '/favicon.png', // Icon for the notification
            ]);
    }
}