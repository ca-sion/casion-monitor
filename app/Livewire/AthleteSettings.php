<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Livewire\Attributes\Layout;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use App\Models\NotificationPreference;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use App\Notifications\SendDailyReminder;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TimePicker;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
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

    public $newPreferenceTime = '08:00';

    public $newPreferenceDays = [];

    public $isSubscribed = false;

    public $vapidPublicKey;

    public $daysOfWeek = [];

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

        // Initialize the form for adding new preferences

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
                    ->visible(fn () => $this->isSubscribed),
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

        if (! $this->isSubscribed) {
            Notification::make()
                ->title('Vous n\'êtes pas abonné aux notifications.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->notifiable->notify(new SendDailyReminder(
                'Notification de test',
                'Si vous recevez ceci, les notifications sont bien configurées.',
                $this->notifiable->accountLink
            ));

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
