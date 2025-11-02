<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Actions\Action;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Telegram\Bot\Laravel\Facades\Telegram;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class TrainerSettings extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public $isSubscribed = false;

    public $vapidPublicKey;

    public $telegramBotUsername;

    public $telegramActivationUrl;

    public $telegramActivationToken;

    public $telegramChatId;

    public function mount()
    {
        $this->checkSubscriptionStatus();
        $this->vapidPublicKey = config('webpush.vapid.public_key');

        $this->telegramChatId = $this->notifiable->telegram_chat_id;

        if (! $this->telegramChatId) {
            $this->generateTelegramActivationUrl();
        }

    }

    public function generateTelegramActivationUrl()
    {
        try {
            $telegramBotUser = Telegram::getMe();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur avec l\'API Telegram')
                ->body($e)
                ->danger()
                ->send();
        }

        $this->telegramActivationToken = str()->random(32);
        $this->telegramActivationUrl = 'https://t.me/'.config('services.telegram-bot-api.username').'?start='.$this->telegramActivationToken;
    }

    public function checkTelegramActivation($notifyUser = false)
    {
        if (! $this->telegramActivationToken) {
            if ($notifyUser) {
                Notification::make()->title('Erreur')->body('Aucun jeton d\'activation n\'est disponible. Veuillez rafraîchir la page.')->danger()->send();
            }

            return;
        }

        try {
            $updates = Telegram::getUpdates(['limit' => 20, 'timeout' => 0]);
            $foundChatId = null;
            $maxUpdateId = 0;

            foreach ($updates as $update) {
                $message = $update->getMessage();
                if ($message && $message->text === '/start '.$this->telegramActivationToken) {
                    $foundChatId = $message->getChat()->id;
                    $maxUpdateId = $update->getUpdateId();
                    break;
                }
            }

            if ($foundChatId) {
                $this->notifiable->update(['telegram_chat_id' => $foundChatId]);

                Telegram::sendMessage([
                    'chat_id' => $foundChatId,
                    'text'    => '✅ Votre compte a été lié avec succès !',
                ]);

                Telegram::getUpdates(['offset' => $maxUpdateId + 1]);

                $this->telegramChatId = $foundChatId;

                if ($notifyUser) {
                    Notification::make()->title('Compte lié !')->body('Votre compte Telegram a été lié avec succès.')->success()->send();
                }
            } elseif ($notifyUser) {
                Notification::make()->title('Échec de la vérification')->body('Nous n\'avons pas pu confirmer votre activation. Veuillez cliquer sur le lien et démarrer la conversation avec le bot avant de vérifier manuellement.')->warning()->send();
            }
        } catch (\Exception $e) {
            if ($notifyUser) {
                Notification::make()->title('Erreur de l\'API')->body('Un problème de communication avec Telegram est survenu. Veuillez réessayer plus tard.')->danger()->send();
            }
            // Do not bubble up exception for polling
        }
    }

    public function scanForTelegramChatIdAction(): Action
    {
        return Action::make('scanForTelegramChatId')
            ->label('vérification manuelle')
            ->link()
            ->color('gray')
            ->disabled(fn () => ! $this->telegramActivationToken)
            ->action(fn () => $this->checkTelegramActivation(true));
    }

    public function linkTelegramManuallyAction(): Action
    {
        return Action::make('linkTelegramManually')
            ->label('Lier manuellement Telegram')
            ->link()
            ->modalHeading('Lier le compte Telegram')
            ->modalSubmitActionLabel('Confirmer la liaison')
            ->schema([
                TextInput::make('chat_id')
                    ->label('Votre Chat ID Telegram')
                    ->helperText('Ouvrez Telegram, contactez le bot @userinfobot et copiez le numéro ID qu\'il vous renvoie. Ce numéro doit être renseigné ici.')
                    ->required()
                    ->numeric() // Assurez-vous que c'est un nombre
                    ->rules(['required', 'numeric']),
            ])
            ->action(function (array $data) {
                $chatId = $data['chat_id'];
                $athlete = $this->notifiable;

                // 1. Enregistrer le Chat ID dans la base de données
                $athlete->update(['telegram_chat_id' => $chatId]);

                // 2. Tenter d'envoyer un message de test pour valider l'ID
                try {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text'    => '🎉 Votre compte Telegram a été lié avec succès à notre service !',
                    ]);

                    // Mise à jour de la propriété Livewire
                    $this->telegramChatId = $chatId;

                    Notification::make()
                        ->title('Liaison Telegram réussie')
                        ->body('Un message de confirmation a été envoyé sur Telegram.')
                        ->success()
                        ->send();

                } catch (\Exception $e) {
                    // Si le message échoue (ID incorrect, bot bloqué, etc.)
                    $athlete->update(['telegram_chat_id' => null]); // Annuler la liaison

                    Notification::make()
                        ->title('Erreur de liaison Telegram')
                        ->body("Impossible d'envoyer un message à ce Chat ID. Assurez-vous d'avoir le bon ID et d'avoir démarré la conversation avec le bot. Le compte n'a PAS été lié.")
                        ->danger()
                        ->send();
                }
            });
    }

    public function unlinkTelegram()
    {
        $this->notifiable->update(['telegram_chat_id' => null]);
        $this->telegramChatId = null;
        $this->generateTelegramActivationUrl();
        Notification::make()
            ->title('Compte Telegram dissocié.')
            ->warning()
            ->send();
    }

    public function getNotifiableProperty()
    {
        if (Auth::guard('trainer')->check()) {
            return Auth::guard('trainer')->user();
        }

        return null;
    }

    protected function checkSubscriptionStatus()
    {
        if ($this->notifiable) {
            $this->isSubscribed = $this->notifiable->pushSubscriptions()->exists();
        }
    }

    public function subscribe($subscription)
    {
        if (! $this->notifiable) {
            $this->dispatch('notify', type: 'error', message: 'Utilisateur non authentifié.');

            return;
        }

        try {
            // Extract keys from the nested 'keys' array
            $publicKey = $subscription['keys']['p256dh'] ?? null;
            $authToken = $subscription['keys']['auth'] ?? null;
            $contentEncoding = $subscription['contentEncoding'] ?? null; // This might still be null if not provided by browser

            $this->notifiable->updatePushSubscription(
                $subscription['endpoint'],
                $publicKey,
                $authToken,
                $contentEncoding
            );

            $this->isSubscribed = true;
            Notification::make()
                ->title('Abonnement aux notifications activé.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title("Erreur d'abonnement WebPush pour l'utilisateur ID {$this->notifiable->id}")
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function unsubscribe(string $endpoint)
    {
        if (! $this->notifiable) {
            Notification::make()
                ->title('Utilisateur non authentifié.')
                ->danger()
                ->send();

            return;
        }

        $this->notifiable->deletePushSubscription($endpoint);
        $this->isSubscribed = false;
        Notification::make()
            ->title('Abonnement aux notifications désactivé.')
            ->warning()
            ->send();
    }

    #[Layout('components.layouts.trainer')]
    public function render()
    {
        return view('livewire.trainer-settings');
    }
}
