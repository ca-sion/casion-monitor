<?php

namespace App\Livewire;

use App\Models\Injury;
use App\Models\Athlete;
use Livewire\Component;
use App\Enums\RecoveryType;
use Filament\Schemas\Schema;
use Livewire\Attributes\Layout;
use App\Models\RecoveryProtocol;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;

class AthleteRecoveryProtocolForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public Athlete $athlete;

    public ?Injury $injury = null; // Optionnel, si le protocole est lié à une blessure

    public function mount(Injury $injury): void
    {
        $this->athlete = auth('athlete')->user();

        if ($injury->exists) {
            $this->injury = $injury;
        } else {
            $this->injury = null;
        }

        $this->form->fill([
            'athlete_id'        => $this->athlete->id,
            'date'              => now()->startOfDay(),
            'related_injury_id' => $this->injury ? $this->injury->id : null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('athlete_id')
                    ->hidden()
                    ->required(),
                TextInput::make('related_injury_id')
                    ->hidden(),
                DatePicker::make('date')
                    ->label('Date du protocole')
                    ->required()
                    ->native(false)
                    ->displayFormat('d.m.Y'),
                Select::make('recovery_type')
                    ->label('Type de récupération')
                    ->options(RecoveryType::class)
                    ->required(),
                TextInput::make('duration_minutes')
                    ->label('Durée (minutes)')
                    ->numeric()
                    ->minValue(1)
                    ->nullable(),
                Textarea::make('notes')
                    ->label('Notes / Description')
                    ->rows(3)
                    ->maxLength(65535)
                    ->nullable(),
                ToggleButtons::make('effect_on_pain_intensity')
                    ->label('Effet sur l\'intensité de la douleur')
                    ->helperText('1 = aucune amélioration, 10 = douleur totalement disparue')
                    ->inline()
                    ->grouped()
                    ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                ToggleButtons::make('effectiveness_rating')
                    ->label('Évaluation de l\'efficacité')
                    ->helperText('1 = pas efficace du tout, 5 = très efficace')
                    ->inline()
                    ->grouped()
                    ->options(fn () => array_combine(range(1, 5), range(1, 5))),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['athlete_id'] = $this->athlete->id;
        $data['related_injury_id'] = $this->injury ? $this->injury->id : null;

        RecoveryProtocol::create($data);

        Notification::make()
            ->title('Protocole de récupération ajouté avec succès !')
            ->success()
            ->send();

        // Rediriger vers le tableau de bord de l'athlète ou la page de la blessure si liée
        if ($this->injury) {
            $this->redirect(route('athletes.injuries.show', ['hash' => $this->athlete->hash, 'injury' => $this->injury->id]));
        } else {
            $this->redirect(route('athletes.dashboard', ['hash' => $this->athlete->hash]));
        }
    }

    #[Layout('components.layouts.athlete')]
    public function render()
    {
        return view('livewire.athlete-recovery-protocol-form');
    }
}
