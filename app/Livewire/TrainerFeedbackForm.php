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

    public Carbon $date;

    public Carbon $prevDate;

    public Carbon $nextDate;

    public Athlete $athlete;

    public Trainer $trainer;

    public function mount(): void
    {
        $this->trainer = auth('trainer')->user();

        $this->d = $this->d ?? now()->timezone('Europe/Zurich')->startOfDay()->format('Y-m-d');
        $this->date = Carbon::parse($this->d) ?? now()->timezone('Europe/Zurich')->startOfDay();
        $this->prevDate = $this->date->copy()->subDay();
        $this->nextDate = $this->date->copy()->addDay();

        $athleteId = request()->input('athlete');

        $this->form->fill([
            'date'       => $this->date,
            'athlete_id' => $athleteId,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Date'),
                Select::make('athlete_id')
                    ->label('Athlète')
                    ->options(fn () => $this->trainer->athletes->pluck('name', 'id'))
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                        $feedbacks = Feedback::where('athlete_id', $state)
                            ->whereDate('date', $get('date'))->get();
                        foreach ($feedbacks as $feedback) {
                            $set($feedback->type->value, $feedback->content);
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

        foreach ($feedbacksData as $feedback => $content) {
            if ($content != null && $content != '<p></p>') {
                Feedback::updateOrCreate(
                    ['athlete_id' => $data['athlete_id'], 'date' => $data['date'], 'type' => $feedback],
                    ['content' => $content, 'author_type' => 'trainer', 'trainer_id' => $this->trainer->id]
                );
            }
        }

    }

    #[Layout('components.layouts.trainer')]
    public function render(): View
    {
        return view('livewire.trainer-feedback-form');
    }
}
