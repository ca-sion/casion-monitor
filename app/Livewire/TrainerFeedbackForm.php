<?php

namespace App\Livewire;

use App\Models\Athlete;
use App\Models\Trainer;
use Livewire\Component;
use App\Models\Feedback;
use App\Enums\FeedbackType;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Illuminate\Contracts\View\View;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class TrainerFeedbackForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    #[Url]
    public string $d;

    #[Url]
    public ?int $athlete_id = null;

    public Carbon $date;

    public Carbon $prevDate;

    public Carbon $nextDate;

    public ?Athlete $athlete = null;

    public Trainer $trainer;

    public function mount(): void
    {
        $this->trainer = auth('trainer')->user();

        // Récupérer la date de l'URL ou la date du jour
        $this->d = $this->d ?? now()->timezone('Europe/Zurich')->startOfDay()->format('Y-m-d');
        $this->date = Carbon::parse($this->d) ?? now()->timezone('Europe/Zurich')->startOfDay();
        $this->prevDate = $this->date->copy()->subDay();
        $this->nextDate = $this->date->copy()->addDay();

        // Si athlete_id est passé via l'URL, le charger
        if ($this->athlete_id) {
            $this->athlete = $this->trainer->athletes()->find($this->athlete_id);
            if (! $this->athlete) {
                // Gérer le cas où l'athlète n'existe pas ou n'appartient pas à cet entraîneur
                abort(404, 'Athlète non trouvé ou non autorisé.');
            }
        } else {
            // Si aucun athlète n'est spécifié, prendre le premier de l'entraîneur (ou laisser null)
            $this->athlete = $this->trainer->athletes->first();
            $this->athlete_id = $this->athlete?->id; // S'assurer que athlete_id est mis à jour
        }

        // Pré-remplir le formulaire avec la date et l'athlète
        $formData = [
            'date'       => $this->date,
            'athlete_id' => $this->athlete_id,
        ];

        // Charger les feedbacks existants pour l'athlète et la date sélectionnée
        if ($this->athlete_id && $this->date) {
            $feedbacks = Feedback::where('athlete_id', $this->athlete_id)
                ->whereDate('date', $this->date)
                ->get();

            foreach ($feedbacks as $feedback) {
                $formData[$feedback->type->value] = $feedback->content;
                // Définir le type de session/compétition pour la bascule
                if (in_array($feedback->type, [FeedbackType::PRE_SESSION_GOALS, FeedbackType::POST_SESSION_FEEDBACK, FeedbackType::POST_SESSION_SENSATION])) {
                    $formData['choose_type'] = 'training';
                } elseif (in_array($feedback->type, [FeedbackType::PRE_COMPETITION_GOALS, FeedbackType::POST_COMPETITION_FEEDBACK, FeedbackType::POST_COMPETITION_SENSATION])) {
                    $formData['choose_type'] = 'competition';
                }
            }
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Date')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                        // Mettre à jour la date pour la navigation
                        $this->date = Carbon::parse($state);
                        $this->prevDate = $this->date->copy()->subDay();
                        $this->nextDate = $this->date->copy()->addDay();

                        // Recharger les feedbacks pour la nouvelle date et l'athlète sélectionné
                        $athleteId = $get('athlete_id');
                        if ($athleteId) {
                            $feedbacks = Feedback::where('athlete_id', $athleteId)
                                ->whereDate('date', $this->date)->get();
                            foreach ($feedbacks as $feedback) {
                                $set($feedback->type->value, $feedback->content);
                            }
                            // Clear fields that are not present in new feedbacks
                            foreach (FeedbackType::cases() as $type) {
                                if (! $feedbacks->contains('type', $type)) {
                                    $set($type->value, null);
                                }
                            }
                        }
                    }),
                Select::make('athlete_id')
                    ->label('Athlète')
                    ->options(fn () => $this->trainer->athletes->pluck('name', 'id'))
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                        $this->athlete_id = (int) $state;
                        $this->athlete = $this->trainer->athletes->find($this->athlete_id);

                        // Recharger les feedbacks pour le nouvel athlète et la date sélectionnée
                        $feedbacks = Feedback::where('athlete_id', $state)
                            ->whereDate('date', $this->date)->get();
                        foreach ($feedbacks as $feedback) {
                            $set($feedback->type->value, $feedback->content);
                        }
                        // Clear fields that are not present in new feedbacks
                        foreach (FeedbackType::cases() as $type) {
                            if (! $feedbacks->contains('type', $type)) {
                                $set($type->value, null);
                            }
                        }
                    }),
                ToggleButtons::make('choose_type')
                    ->label('Type')
                    ->grouped()
                    ->options([
                        'training'    => 'Session',
                        'competition' => 'Compétition',
                    ]),
                Section::make('Session')
                    ->description('Session ou entraînement.')
                    ->visibleJs(<<<'JS'
                        $get('choose_type') === 'training'
                        JS)
                    ->compact()
                    ->schema([
                        Textarea::make(FeedbackType::PRE_SESSION_GOALS->value)
                            ->label(FeedbackType::PRE_SESSION_GOALS->getLabel())
                            ->maxLength(1500)
                            ->autosize(),
                        Textarea::make(FeedbackType::POST_SESSION_FEEDBACK->value)
                            ->label(FeedbackType::POST_SESSION_FEEDBACK->getLabel())
                            ->maxLength(1500)
                            ->autosize(),
                        Textarea::make(FeedbackType::POST_SESSION_SENSATION->value)
                            ->label(FeedbackType::POST_SESSION_SENSATION->getLabel())
                            ->readOnly()
                            ->disabled()
                            ->autosize(),
                    ]),
                Section::make('Compétition')
                    ->description('Session ou entraînement.')
                    ->visibleJs(<<<'JS'
                        $get('choose_type') === 'competition'
                        JS)
                    ->compact()
                    ->schema([
                        Textarea::make(FeedbackType::PRE_COMPETITION_GOALS->value)
                            ->label(FeedbackType::PRE_COMPETITION_GOALS->getLabel())
                            ->maxLength(1500)
                            ->autosize(),
                        Textarea::make(FeedbackType::POST_COMPETITION_FEEDBACK->value)
                            ->label(FeedbackType::POST_COMPETITION_FEEDBACK->getLabel())
                            ->maxLength(1500)
                            ->autosize(),
                        Textarea::make(FeedbackType::POST_COMPETITION_SENSATION->value)
                            ->label(FeedbackType::POST_COMPETITION_SENSATION->getLabel())
                            ->readOnly()
                            ->disabled()
                            ->autosize(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $feedbacksData = collect($data)->filter(function (mixed $value, string $key) {
            return in_array($key, [
                FeedbackType::PRE_SESSION_GOALS->value,
                FeedbackType::POST_SESSION_FEEDBACK->value,
                FeedbackType::POST_SESSION_SENSATION->value,
                FeedbackType::PRE_COMPETITION_GOALS->value,
                FeedbackType::POST_COMPETITION_FEEDBACK->value,
                FeedbackType::POST_COMPETITION_SENSATION->value,
            ]);
        });

        foreach ($feedbacksData as $feedbackType => $content) {
            if ($content != null && $content != '<p></p>') {
                if (in_array($feedbackType, [
                    FeedbackType::POST_SESSION_FEEDBACK->value,
                    FeedbackType::POST_COMPETITION_FEEDBACK->value,
                ])) {
                    $f = Feedback::updateOrCreate(
                        ['athlete_id' => $this->athlete_id, 'date' => $this->date->format('Y-m-d H:i:s'), 'type' => $feedbackType],
                        ['content' => $content, 'author_type' => 'trainer', 'trainer_id' => $this->trainer->id]
                    );
                } else {
                    $f = Feedback::updateOrCreate(
                        ['athlete_id' => $this->athlete_id, 'date' => $this->date->format('Y-m-d H:i:s'), 'type' => $feedbackType],
                        ['content' => $content]
                    );
                }
            } else {
                // Si le contenu est vide, supprime le feedback existant
                Feedback::where('athlete_id', $this->athlete_id)
                    ->where('date', $this->date->format('Y-m-d H:i:s'))
                    ->where('type', $feedbackType)
                    ->delete();
            }
        }
        $this->dispatch('feedback-saved'); // Déclenche un événement pour notifier la sauvegarde si besoin
        Notification::make()
            ->title('Sauvegardé')
            ->success()
            ->send();
    }

    #[Layout('components.layouts.trainer')]
    public function render(): View
    {
        return view('livewire.trainer-feedback-form');
    }
}
