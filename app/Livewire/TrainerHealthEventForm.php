<?php

namespace App\Livewire;

use App\Models\Injury;
use App\Models\Athlete;
use App\Models\Trainer;
use Livewire\Component;
use App\Models\HealthEvent;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use App\Enums\HealthEventType;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class TrainerHealthEventForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?array $data = [];

    public ?Athlete $athlete = null;

    public ?Injury $injury = null;

    public ?HealthEvent $healthEvent = null;

    #[Url]
    public string $date;

    public ?Trainer $trainer;

    public function mount(?Injury $injury, ?HealthEvent $healthEvent): void
    {
        $this->trainer = auth('trainer')->user();

        $this->date = $this->date ?? now()->timezone('Europe/Zurich')->startOfDay()->format('Y-m-d');
        $date = Carbon::parse($this->date) ?? now()->timezone('Europe/Zurich')->startOfDay();

        if ($healthEvent?->exists) {
            $this->athlete = $healthEvent->athlete;
            $this->healthEvent = $healthEvent;
            $this->form->fill($this->healthEvent->toArray());
        }

        if ($injury?->exists) {
            $this->injury = $injury;
            $this->athlete = $injury->athlete;
            // Vérifier que la blessure appartient à l\'athlète connecté
            if ($injury->athlete_id !== $this->athlete->id) {
                abort(403, 'Accès non autorisé à cette blessure.');
            }
        }

        if (! $this->healthEvent) {
            $this->form->fill([
                'athlete_id'          => $this->athlete?->id,
                'injury_id'           => $this->injury?->id,
                'date'                => $date,
                'reported_by_athlete' => true,
            ]);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('athlete_id')
                    ->label('Athlète')
                    ->options($this->trainer->athletes->pluck('name', 'id'))
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->first_name} {$record->last_name}")
                    ->required(),
                TextInput::make('injury_id')
                    ->hidden(),
                DatePicker::make('date')
                    ->label('Date')
                    ->default(now())
                    ->required(),
                Select::make('type')
                    ->label('Type')
                    ->options(HealthEventType::class)
                    ->required()
                    ->live(),
                TextInput::make('purpose')
                    ->label('Raison/Objet'),

                // Champs pour le suivi médical
                Select::make('professional_id')
                    ->label('Professionnel de santé')
                    ->relationship('professional', 'name')
                    ->searchable()
                    ->visible(fn (Get $get) => in_array($get('type'), [HealthEventType::MEDICAL_CONSULTATION, HealthEventType::PHYSICAL_FOLLOWUP, HealthEventType::MENTAL_FOLLOWUP]))
                    ->createOptionForm([
                        TextInput::make('first_name')
                            ->required(),
                        TextInput::make('last_name')
                            ->required(),
                    ]),
                Textarea::make('summary_notes')
                    ->label('Résumé / Diagnostic')
                    ->rows(3)
                    ->maxLength(65535)
                    ->nullable()
                    ->helperText('Diagnostic, résumé de la séance.')
                    ->visible(fn (Get $get) => in_array($get('type'), [HealthEventType::MEDICAL_CONSULTATION, HealthEventType::PHYSICAL_FOLLOWUP, HealthEventType::MENTAL_FOLLOWUP])),
                Textarea::make('recommendations')
                    ->label('Recommandations / Plan de traitement')
                    ->rows(4)
                    ->maxLength(65535)
                    ->nullable()
                    ->helperText('Traitement prescrit, exercices, etc.')
                    ->visible(fn (Get $get) => in_array($get('type'), [HealthEventType::MEDICAL_CONSULTATION, HealthEventType::PHYSICAL_FOLLOWUP, HealthEventType::MENTAL_FOLLOWUP])),

                // Champs pour le protocole de récupération
                TextInput::make('duration_minutes')
                    ->label('Durée (minutes)')
                    ->numeric()
                    ->minValue(1)
                    ->nullable()
                    ->visible(fn (Get $get) => in_array($get('type'), [HealthEventType::MASSAGE, HealthEventType::CONTRAST_BATHS, HealthEventType::CRYOTHERAPY, HealthEventType::OTHER])),
                ToggleButtons::make('effect_on_pain_intensity')
                    ->label('Effet sur l\'intensité de la douleur')
                    ->helperText('1 = aucune amélioration, 10 = douleur totalement disparue')
                    ->inline()
                    ->grouped()
                    ->options(fn () => array_combine(range(1, 10), range(1, 10)))
                    ->visible(fn (Get $get) => $this->injury !== null),
                ToggleButtons::make('effectiveness_rating')
                    ->label('Évaluation de l\'efficacité')
                    ->helperText('1 = pas efficace du tout, 5 = très efficace')
                    ->inline()
                    ->grouped()
                    ->options(fn () => array_combine(range(1, 5), range(1, 5)))
                    ->visible(fn (Get $get) => in_array($get('type'), [HealthEventType::MASSAGE, HealthEventType::CONTRAST_BATHS, HealthEventType::CRYOTHERAPY, HealthEventType::OTHER])),

                // Champ de notes générales
                Textarea::make('note')
                    ->label('Notes complémentaires')
                    ->rows(4)
                    ->maxLength(65535)
                    ->nullable(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['athlete_id'] = $this->athlete->id;
        $data['injury_id'] = $this->injury?->id;
        $data['reported_by_athlete'] = true;

        if ($this->healthEvent) {
            $this->healthEvent->update($data);

            Notification::make()
                ->title('Événement de santé mis à jour avec succès !')
                ->success()
                ->send();
        } else {
            HealthEvent::create($data);

            Notification::make()
                ->title('Événement de santé ajouté avec succès !')
                ->success()
                ->send();
        }

        $this->redirect(url()->previous());
    }

    #[Layout('components.layouts.trainer')]
    public function render()
    {
        return view('livewire.trainer-health-event-form');
    }
}
