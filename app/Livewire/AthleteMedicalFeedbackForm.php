<?php

namespace App\Livewire;

use App\Models\Injury;
use App\Models\Athlete;
use Livewire\Component;
use Filament\Schemas\Schema;
use App\Enums\ProfessionalType;
use App\Models\MedicalFeedback;
use Livewire\Attributes\Layout;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;

class AthleteMedicalFeedbackForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public Injury $injury;
    public Athlete $athlete;

    public function mount(Injury $injury): void
    {
        $this->injury = $injury;
        $this->athlete = auth('athlete')->user();

        // Vérifier que la blessure appartient à l'athlète connecté
        if ($injury->athlete_id !== $this->athlete->id) {
            abort(403, 'Accès non autorisé à cette blessure.');
        }

        $this->form->fill([
            'injury_id' => $this->injury->id,
            'feedback_date' => now()->format('Y-m-d'),
            'reported_by_athlete' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('injury_id')
                    ->hidden()
                    ->required(),
                DatePicker::make('feedback_date')
                    ->label('Date de la consultation')
                    ->default(now())
                    ->required(),
                Select::make('professional_type')
                    ->label('Type de professionnel consulté')
                    ->options(ProfessionalType::class)
                    ->required(),
                Textarea::make('diagnosis')
                    ->label('Diagnostic médical')
                    ->rows(3)
                    ->maxLength(65535)
                    ->nullable()
                    ->helperText('Diagnostic établi par le professionnel de santé'),
                Textarea::make('treatment_plan')
                    ->label('Plan de traitement')
                    ->rows(4)
                    ->maxLength(65535)
                    ->nullable()
                    ->helperText('Traitement prescrit (médicaments, soins, etc.)'),
                Textarea::make('rehab_progress')
                    ->label('Progrès de rééducation')
                    ->rows(3)
                    ->maxLength(65535)
                    ->nullable()
                    ->helperText('État d\'avancement de la rééducation si applicable'),
                Textarea::make('training_limitations')
                    ->label('Limitations d\'entraînement')
                    ->rows(3)
                    ->maxLength(65535)
                    ->nullable()
                    ->helperText('Restrictions ou adaptations pour l\'entraînement'),
                DatePicker::make('next_appointment_date')
                    ->label('Prochain rendez-vous')
                    ->nullable()
                    ->helperText('Date du prochain rendez-vous médical'),
                Textarea::make('notes')
                    ->label('Notes complémentaires')
                    ->rows(4)
                    ->maxLength(65535)
                    ->nullable()
                    ->helperText('Autres informations importantes'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['injury_id'] = $this->injury->id;
        $data['reported_by_athlete'] = true;

        MedicalFeedback::create($data);

        Notification::make()
            ->title('Feedback médical ajouté avec succès !')
            ->success()
            ->send();

        // Rediriger vers la page de détail de la blessure
        $this->redirect(route('athletes.injuries.show', ['hash' => $this->athlete->hash, 'injury' => $this->injury->id]));
    }

    #[Layout('components.layouts.athlete')]
    public function render()
    {
        return view('livewire.athlete-medical-feedback-form');
    }
}
