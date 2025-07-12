<?php

namespace App\Livewire;

use App\Models\Injury;
use App\Models\Athlete;
use Livewire\Component;
use App\Enums\InjuryType;
use App\Enums\InjuryStatus;
use Filament\Schemas\Schema;
use Livewire\Attributes\Layout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Concerns\InteractsWithForms;

class AthleteInjuryForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public Athlete $athlete;

    public function mount(): void
    {
        $this->athlete = auth('athlete')->user();

        $this->form->fill([
            'athlete_id'          => $this->athlete->id,
            'declaration_date'    => now()->format('Y-m-d'),
            'status'              => InjuryStatus::DECLARED->value,
            'pain_intensity'      => request()->query('pain_intensity'),
            'pain_location'       => request()->query('pain_location'),
            'onset_circumstances' => request()->query('onset_circumstances'),
            'session_related'     => request()->query('session_related', false),
            'session_date'        => request()->query('session_date'),
            'immediate_onset'     => request()->query('immediate_onset', false),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('athlete_id')
                    ->hidden()
                    ->required(),
                DatePicker::make('declaration_date')
                    ->label('Date de déclaration')
                    ->default(now())
                    ->required(),
                Select::make('status')
                    ->label('Statut de la blessure')
                    ->options(InjuryStatus::class)
                    ->default(InjuryStatus::DECLARED->value)
                    ->required(),
                ToggleButtons::make('pain_intensity')
                    ->label('Intensité de la douleur')
                    ->helperText('aucune ➝ très fortes')
                    ->inline()
                    ->grouped()
                    ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                TextInput::make('pain_location')
                    ->label('Localisation de la douleur')
                    ->maxLength(255)
                    ->nullable(),
                Select::make('injury_type')
                    ->label('Type de blessure')
                    ->options(InjuryType::class)
                    ->nullable(),
                Textarea::make('onset_circumstances')
                    ->label("Circonstances d'apparition")
                    ->rows(3)
                    ->maxLength(65535)
                    ->nullable(),
                Textarea::make('impact_on_training')
                    ->label("Impact sur l'entraînement")
                    ->rows(3)
                    ->maxLength(65535)
                    ->nullable(),
                Textarea::make('description')
                    ->label('Description détaillée')
                    ->rows(5)
                    ->maxLength(65535)
                    ->nullable(),
                Textarea::make('athlete_diagnosis_feeling')
                    ->label('Ressenti de l\'athlète sur le diagnostic')
                    ->rows(3)
                    ->maxLength(65535)
                    ->nullable(),
                Toggle::make('session_related')
                    ->label('Liée à une session')
                    ->inline(false)
                    ->live(),
                DatePicker::make('session_date')
                    ->label('Date de la session')
                    ->visible(fn (Get $get) => $get('session_related'))
                    ->nullable(),
                Toggle::make('immediate_onset')
                    ->label('Apparition immédiate')
                    ->inline(false),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['athlete_id'] = $this->athlete->id;

        Injury::create($data);

        Notification::make()
            ->title('Blessure déclarée avec succès !')
            ->success()
            ->send();

        // Rediriger l'athlète vers son tableau de bord ou une page de confirmation
        $this->redirect(route('athletes.dashboard', ['hash' => $this->athlete->hash]));
    }

    #[Layout('components.layouts.athlete')]
    public function render()
    {
        return view('livewire.athlete-injury-form');
    }
}
