<?php

namespace App\Livewire;

use App\Models\Injury;
use App\Enums\BodyPart;
use App\Models\Trainer;
use Livewire\Component;
use App\Enums\InjuryType;
use App\Enums\InjuryStatus;
use Filament\Schemas\Schema;
use Livewire\Attributes\Layout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Concerns\InteractsWithForms;

class TrainerInjuryForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public Trainer $trainer;

    public function mount(): void
    {
        $this->trainer = auth('trainer')->user();

        $this->form->fill([
            'declaration_date' => now()->format('Y-m-d'),
            'status'           => InjuryStatus::DECLARED->value,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('athlete_id')
                    ->label('Athlète')
                    ->options($this->trainer->athletes->pluck('name', 'id'))
                    ->required(),
                DatePicker::make('declaration_date')
                    ->label('Date de déclaration')
                    ->native(false)
                    ->displayFormat('d.m.Y')
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
                Select::make('pain_location')
                    ->label('Localisation de la douleur')
                    ->required()
                    ->searchable()
                    ->options(BodyPart::class),
                Select::make('injury_type')
                    ->label('Type de blessure')
                    ->options(InjuryType::class)
                    ->required()
                    ->nullable(),
                Textarea::make('onset_circumstances')
                    ->label("Circonstances d'apparition")
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

        Injury::create($data);

        Notification::make()
            ->title('Blessure déclarée avec succès !')
            ->success()
            ->send();

        $this->redirect(route('trainers.injuries.index', ['hash' => $this->trainer->hash]));
    }

    #[Layout('components.layouts.trainer')]
    public function render()
    {
        return view('livewire.trainer.injury-form');
    }
}
