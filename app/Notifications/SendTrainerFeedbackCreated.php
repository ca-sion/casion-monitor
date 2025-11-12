<?php

namespace App\Notifications;

use App\Models\Feedback;
use App\Models\Trainer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class SendTrainerFeedbackCreated extends Notification
{
    use Queueable;

    protected $feedback;

    protected $trainer;

    /**
     * Create a new notification instance.
     */
    public function __construct(Feedback $feedback, Trainer $trainer)
    {
        $this->feedback = $feedback;
        $this->trainer = $trainer;
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
            ->content('*'.$this->trainer?->name.'* a ajouté un feedback _'.$this->feedback?->type?->getLabel().'_ pour le '.$this->feedback?->date?->locale('fr_CH')->isoFormat('ll')." :\n".$this->feedback?->content);

        $message->button('Voir', route('athletes.journal', ['hash' => $notifiable->hash]));

        return $message;
    }
}
