<?php

namespace App\Notifications;

use App\Models\Injury;
use App\Models\Athlete;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class SendAthleteInjuryCreated extends Notification
{
    use Queueable;

    protected $injury;

    protected $athlete;

    /**
     * Create a new notification instance.
     */
    public function __construct(Injury $injury, Athlete $athlete)
    {
        $this->injury = $injury;
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
            ->content('*'.$this->athlete?->name.'* a ajouté une blessure pour le '.$this->injury?->declaration_date?->locale('fr_CH')->isoFormat('ll')." :\n".$this->injury?->pain_location?->getLabel().' ('.$this->injury?->injury_type?->getLabel().')');

        $message->button('Voir', route('trainers.injuries.show', ['hash' => $notifiable->hash, 'injury' => $this->injury?->id]));

        return $message;
    }
}
