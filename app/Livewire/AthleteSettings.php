<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Livewire\Attributes\Layout;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Toggle;
use App\Models\NotificationPreference;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use App\Notifications\SendDailyReminder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TimePicker;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Telegram\Bot\Laravel\Facades\Telegram;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Validation\ValidationException;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class AthleteSettings extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public $preferences = []; // This will now be managed by Filament Table

    public ?array $preferencesData = [];

    public $newPreferenceTime = '08:00';

    public $newPreferenceDays = [];

    public $isSubscribed = false;

    public $vapidPublicKey;

    public $daysOfWeek = [];

    public $telegramBotUsername;

    public $telegramActivationUrl;

    public $telegramActivationToken;

    public $telegramChatId;

    public function mount()
    {
        $this->checkSubscriptionStatus();
        $this->vapidPublicKey = config('webpush.vapid.public_key');
        $this->daysOfWeek = [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            0 => 'Dimanche',
        ];

        $this->telegramChatId = $this->notifiable->telegram_chat_id;

        if (! $this->telegramChatId) {
            $this->generateTelegramActivationUrl();
        }

        $this->preferencesForm->fill($this->notifiable->preferences ?? []);
    }

    public function preferencesForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('preferencesData')
            ->schema([
                Section::make('Préférences d\'affichage')
                    ->compact()
                    ->schema([
                        Toggle::make('show_morning_hrv')
                            ->label('Afficher le champ HRV')
                            ->helperText('Masque ou affiche le champ de la variabilité de la fréquence cardiaque (HRV) dans le formulaire quotidien.'),
                        Toggle::make('show_morning_sleep_duration')
                            ->label('Afficher le champ Durée du sommeil')
                            ->helperText('Masque ou affiche le champ de Durée du sommeil dans le formulaire quotidien.'),
                    ]),
            ]);
    }

    public function savePreferences(): void
    {
        $data = $this->preferencesForm->getState();
        $this->notifiable->preferences = $data;
        $this->notifiable->save();

        Notification::make()
            ->title('Préférences sauvegardées')
            ->success()
            ->send();
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
        if (Auth::guard('athlete')->check()) {
            return Auth::guard('athlete')->user();
        } elseif (Auth::guard('trainer')->check()) {
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

    public function table(Table $table): Table
    {
        return $table
            ->query(NotificationPreference::whereMorphedTo('notifiable', $this->notifiable))
            ->paginated(false)
            ->columns([
                TextColumn::make('notification_time')
                    ->label('Heure')
                    ->time('H:i')
                    ->timezone('Europe/Zurich'),
                TextColumn::make('notification_days')
                    ->label('Jours')
                    ->formatStateUsing(fn (string $state): string => collect(json_decode($state))
                        ->map(fn ($day) => $this->daysOfWeek[$day])
                        ->implode(', ')),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->before(function (DeleteAction $action, NotificationPreference $record) {
                        if ($this->notifiable->notificationPreferences()->count() <= 1) {
                            // Prevent deleting the last preference if we want to enforce at least one
                            // Or just allow it, depending on UX. For now, allow.
                        }
                    }),
            ])
            ->headerActions([
                Action::make('add_preference')
                    ->label('Ajouter un rappel')
                    ->schema([
                        TimePicker::make('time')
                            ->label('Heure du rappel')
                            ->required()
                            ->seconds(false)
                            ->native(true)
                            ->placeholder('HH:MM')
                            ->minutesStep(15),
                        CheckboxList::make('days')
                            ->label('Jours de la semaine')
                            ->options($this->daysOfWeek)
                            ->columns(3)
                            ->required()
                            ->helperText('Sélectionnez les jours où vous souhaitez recevoir ce rappel.'),
                    ])
                    ->action(function (array $data): void {
                        if ($this->notifiable->notificationPreferences()->count() >= 5) {
                            throw ValidationException::withMessages([
                                'add_preference' => 'Vous ne pouvez pas ajouter plus de 5 rappels par jour.',
                            ]);
                        }
                        $this->notifiable->notificationPreferences()->create([
                            'notification_time' => $data['time'],
                            'notification_days' => $data['days'],
                        ]);
                        Notification::make()
                            ->title('Rappel ajouté avec succès.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => $this->notifiable->notificationPreferences()->count() < 5),
                Action::make('sendTestNotification')
                    ->label('Tester l\'envoi')
                    ->action('sendTestNotification')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer une notification de test')
                    ->modalDescription('Nous allons envoyer une notification de test pour vérifier que votre appareil est bien configuré.')
                    ->visible(fn () => $this->isSubscribed || $this->telegramChatId),
            ]);
    }

    public function sendTestNotification()
    {
        if (! $this->notifiable) {
            Notification::make()
                ->title('Utilisateur non authentifié.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->isSubscribed && ! $this->telegramChatId) {
            Notification::make()
                ->title('Vous n\'êtes abonné à aucun canal de notification.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->notifiable->notify(new SendDailyReminder($this->notifiable));

            Notification::make()
                ->title('Notification de test envoyée.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur lors de l\'envoi de la notification de test.')
                ->body($e->getMessage())
                ->danger()
                ->send();
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

    #[Layout('components.layouts.athlete')]
    public function render()
    {
        return view('livewire.athlete-settings');
    }
}
