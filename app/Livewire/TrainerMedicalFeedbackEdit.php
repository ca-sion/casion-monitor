<?php

namespace App\Livewire;

use App\Models\Trainer;
use Livewire\Component;
use Filament\Schemas\Schema;
use App\Enums\ProfessionalType;
use App\Models\MedicalFeedback;
use Livewire\Attributes\Layout;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;

class TrainerMedicalFeedbackEdit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public MedicalFeedback $medicalFeedback;

    public Trainer $trainer;

    public function mount(MedicalFeedback $medicalFeedback): void
    {
        $this->medicalFeedback = $medicalFeedback;
        $this->trainer = auth('trainer')->user();

        // Vérifier que l'entraîneur a accès à cette blessure
        $injury = $this->medicalFeedback->injury;
        if (! $this->trainer->athletes->contains($injury->athlete_id)) {
            abort(403, 'Accès non autorisé à ce feedback médical.');
        }

        $this->form->fill([
            'feedback_date'         => $this->medicalFeedback->feedback_date?->format('Y-m-d'),
            'professional_type'     => $this->medicalFeedback->professional_type,
            'diagnosis'             => $this->medicalFeedback->diagnosis,
            'treatment_plan'        => $this->medicalFeedback->treatment_plan,
            'rehab_progress'        => $this->medicalFeedback->rehab_progress,
            'training_limitations'  => $this->medicalFeedback->training_limitations,
            'next_appointment_date' => $this->medicalFeedback->next_appointment_date?->format('Y-m-d'),
            'notes'                 => $this->medicalFeedback->notes,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('feedback_date')
                    ->label('Date de la consultation')
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

        $this->medicalFeedback->update($data);

        Notification::make()
            ->title('Feedback médical mis à jour avec succès !')
            ->success()
            ->send();

        // Rediriger vers la page de l'athlète
        $this->redirect(route('trainers.athlete', [
            'hash'    => $this->trainer->hash,
            'athlete' => $this->medicalFeedback->injury->athlete_id,
        ]));
    }

    #[Layout('components.layouts.trainer')]
    public function render()
    {
        return view('livewire.trainer-medical-feedback-edit');
    }
}
