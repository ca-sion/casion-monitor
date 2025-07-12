<?php

namespace App\Livewire;

use App\Models\Injury;
use App\Models\Trainer;
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

class MedicalFeedbackForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public Injury $injury;
    public Trainer $trainer;

    public function mount(Injury $injury): void
    {
        $this->injury = $injury;
        $this->trainer = auth('trainer')->user();

        $this->form->fill([
            'injury_id' => $this->injury->id,
            'trainer_id' => $this->trainer->id,
            'feedback_date' => now()->format('Y-m-d'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('injury_id')
                    ->hidden()
                    ->required(),
                TextInput::make('trainer_id')
                    ->hidden()
                    ->required(),
                DatePicker::make('feedback_date')
                    ->label('Date du feedback')
                    ->default(now())
                    ->required(),
                Select::make('professional_type')
                    ->label('Type de professionnel')
                    ->options(ProfessionalType::class)
                    ->required(),
                Textarea::make('feedback_details')
                    ->label('Détails du feedback')
                    ->rows(5)
                    ->maxLength(65535)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['athlete_id'] = $this->athlete->id;
        $data['trainer_id'] = $this->trainer->id;

        MedicalFeedback::create($data);

        Notification::make()
            ->title('Feedback médical ajouté avec succès !')
            ->success()
            ->send();

        // Rediriger le professionnel vers la page de détail de la blessure ou une page de confirmation
        $this->redirect(route('trainers.athlete', ['hash' => $this->trainer->hash, 'athlete' => $this->injury->athlete_id]));
    }

    #[Layout('components.layouts.trainer')]
    public function render()
    {
        return view('livewire.medical-feedback-form');
    }
}
