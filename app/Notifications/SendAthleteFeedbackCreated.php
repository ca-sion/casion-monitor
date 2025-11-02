<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class SendAthleteFeedbackCreated extends Notification
{
    use Queueable;

    protected $feedback;

    protected $athlete;

    /**
     * Create a new notification instance.
     */
    public function __construct($feedback, $athlete)
    {
        $this->feedback = $feedback;
        $this->athlete = $athlete;
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
        //
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram(object $notifiable)
    {
        $message = TelegramMessage::create()
            ->content('*'.$this->athlete?->name."* a ajouté un feedback _".$this->feedback?->type?->getLabel()."_ pour le ".$this->feedback?->date?->locale('fr_CH')->isoFormat('ll')." :\n".$this->feedback->content);

        $message->button('Ouvrir', route('trainers.feedbacks.form', ['hash' => $notifiable->hash, 'd' => $this->feedback?->date->format('Y-m-d'), 'athlete_id' => $this->feedback->athlete_id]));

        return $message;
    }
}
