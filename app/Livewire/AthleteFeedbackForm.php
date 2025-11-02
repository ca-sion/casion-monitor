<?php

namespace App\Livewire;

use App\Models\Athlete;
use Livewire\Component;
use App\Models\Feedback;
use App\Enums\FeedbackType;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Illuminate\Contracts\View\View;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class AthleteFeedbackForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    #[Url]
    public string $d;

    public Carbon $date;

    public Carbon $prevDate;

    public Carbon $nextDate;

    public bool $canGetNextDay;

    public ?Athlete $athlete = null;

    public function mount(): void
    {
        $this->athlete = auth('athlete')->user();

        // Récupérer la date de l'URL ou la date du jour
        $this->d = $this->d ?? now()->timezone('Europe/Zurich')->startOfDay()->format('Y-m-d');
        $this->date = Carbon::parse($this->d) ?? now()->timezone('Europe/Zurich')->startOfDay();
        $this->prevDate = $this->date->copy()->subDay();
        $this->nextDate = $this->date->copy()->addDay();

        $this->canGetNextDay = $this->nextDate < now()->endOfDay();

        if (! $this->athlete) {
            // Gérer le cas où l'athlète n'existe pas ou n'appartient pas à cet entraîneur
            abort(404, 'Athlète non trouvé ou non autorisé.');
        }

        // Pré-remplir le formulaire avec la date et l'athlète
        $formData = [
            'date'       => $this->date,
            'athlete_id' => $this->athlete->id,
        ];

        // Charger les feedbacks existants pour l'athlète et la date sélectionnée
        if ($this->athlete && $this->date) {
            $feedbacks = Feedback::where('athlete_id', $this->athlete->id)
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
                Hidden::make('athlete_id'),
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
                        Textarea::make(FeedbackType::POST_SESSION_SENSATION->value)
                            ->label(FeedbackType::POST_SESSION_SENSATION->getLabel())
                            ->autosize(),
                        Textarea::make(FeedbackType::POST_SESSION_FEEDBACK->value)
                            ->label(FeedbackType::POST_SESSION_FEEDBACK->getLabel())
                            ->disabled()
                            ->maxLength(1500)
                            ->autosize(),
                    ]),
                Section::make('Compétition')
                    ->description('Session ou entraînement.')
                    ->visibleJs(<<<'JS'
                        $get('choose_type') === 'competition'
                        JS)
                    ->compact()
                    ->schema([
                        Textarea::make(FeedbackType::POST_COMPETITION_SENSATION->value)
                            ->label(FeedbackType::POST_COMPETITION_SENSATION->getLabel())
                            ->autosize(),
                        Textarea::make(FeedbackType::PRE_COMPETITION_GOALS->value)
                            ->label(FeedbackType::PRE_COMPETITION_GOALS->getLabel())
                            ->disabled()
                            ->maxLength(1500)
                            ->autosize(),
                        Textarea::make(FeedbackType::POST_COMPETITION_FEEDBACK->value)
                            ->label(FeedbackType::POST_COMPETITION_FEEDBACK->getLabel())
                            ->disabled()
                            ->maxLength(1500)
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
                        ['athlete_id' => $this->athlete->id, 'date' => $this->date->format('Y-m-d H:i:s'), 'type' => $feedbackType],
                        ['content' => $content, 'author_type' => 'trainer', 'trainer_id' => $this->trainer->id]
                    );
                } else {
                    $f = Feedback::updateOrCreate(
                        ['athlete_id' => $this->athlete->id, 'date' => $this->date->format('Y-m-d H:i:s'), 'type' => $feedbackType],
                        ['content' => $content]
                    );
                }
            } else {
                // Si le contenu est vide, supprime le feedback existant
                Feedback::where('athlete_id', $this->athlete->id)
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

    private function desiredFeedbackTypes(): array
    {
        return [
            FeedbackType::PRE_SESSION_GOALS->value,
            FeedbackType::POST_SESSION_SENSATION->value,
        ];
    }

    #[Layout('components.layouts.athlete')]
    public function render(): View
    {
        return view('livewire.athlete-feedback-form');
    }
}
