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
use Filament\Notifications\Notification;
use Filament\Forms\Components\TimePicker;
use App\Notifications\SendDailyReminder;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Validation\ValidationException;
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

    public function mount()
    {
        $this->checkSubscriptionStatus();
        $this->vapidPublicKey = config('webpush.vapid.public_key');

        // Initialize the form for adding new preferences

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
