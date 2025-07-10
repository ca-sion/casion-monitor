<?php

namespace App\Livewire;

use App\Models\Metric;
use App\Models\Athlete;
use Livewire\Component;
use App\Models\Feedback;
use App\Enums\MetricType;
use App\Enums\FeedbackType;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use Illuminate\Support\Carbon;
use App\Enums\CalculatedMetric;
use Livewire\Attributes\Layout;
use App\Models\TrainingPlanWeek;
use Illuminate\Contracts\View\View;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Icon;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use App\Services\MetricStatisticsService;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class AthleteDailyMetricForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    #[Url]
    public string $d;

    public Carbon $date;

    public Carbon $prevDate;

    public Carbon $nextDate;

    public Athlete $athlete;

    public ?string $type = 'daily';

    public bool $canGetNextDay;

    public ?TrainingPlanWeek $athleteCurrentTrainingPlanWeek;

    public function mount(): void
    {
        $this->athlete = auth('athlete')->user();

        $this->d = $this->d ?? now()->timezone('Europe/Zurich')->startOfDay()->format('Y-m-d');
        $this->date = Carbon::parse($this->d) ?? now()->timezone('Europe/Zurich')->startOfDay();
        $this->prevDate = $this->date->copy()->subDay();
        $this->nextDate = $this->date->copy()->addDay();

        $this->canGetNextDay = $this->nextDate < now()->endOfDay();

        $currentWeekStartDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $this->athleteCurrentTrainingPlanWeek = resolve(MetricStatisticsService::class)->getTrainingPlanWeekForAthlete($this->athlete, $currentWeekStartDate);

        $metrics = Metric::where('athlete_id', $this->athlete->id)
            ->whereDate('date', $this->date)
            ->whereIn('metric_type', $this->desiredMetricTypes())
            ->get();
        $metricsData = $metrics->mapWithKeys(function (Metric $metric) {
            return [$metric->metric_type->value => $metric->{$metric->metric_type->getValueColumn()} ?? null];
        });

        $feedbacks = Feedback::where('athlete_id', $this->athlete->id)
            ->whereDate('date', $this->date)
            ->whereIn('type', $this->desiredFeedbackTypes())
            ->get();
        $feedbacksData = $feedbacks->pluck('content', 'type.value');

        $data = $metricsData->merge($feedbacksData)->put('date', $this->date)->all();

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('date')
                    ->label('Date des métriques')
                    ->state($this->date)
                    ->isoDate('dddd LL')
                    ->timezone('Europe/Zurich')
                    ->sinceTooltip(),
                Section::make('Matin')
                    ->description('Remplir le matin au réveil.')
                    ->collapsed(fn () => now()->timezone('Europe/Zurich')->format('H') > 12)
                    ->compact()
                    ->schema([
                        TextInput::make(MetricType::MORNING_HRV->value)
                            ->label(MetricType::MORNING_HRV->getLabel())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::MORNING_HRV->getHint()),
                            ])
                            ->integer()
                            ->inputMode('numeric')
                            ->minValue(10)
                            ->maxValue(200)
                            ->suffix('ms'),
                        ToggleButtons::make(MetricType::MORNING_SLEEP_QUALITY->value)
                            ->label(MetricType::MORNING_SLEEP_QUALITY->getLabel())
                            ->helperText(MetricType::MORNING_SLEEP_QUALITY->getScaleHint())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::MORNING_SLEEP_QUALITY->getHint()),
                            ])
                            ->inline()
                            ->grouped()
                            ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                        ToggleButtons::make(MetricType::MORNING_GENERAL_FATIGUE->value)
                            ->label(MetricType::MORNING_GENERAL_FATIGUE->getLabel())
                            ->helperText(MetricType::MORNING_GENERAL_FATIGUE->getScaleHint())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::MORNING_GENERAL_FATIGUE->getHint()),
                            ])
                            ->inline()
                            ->grouped()
                            ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                        ToggleButtons::make(MetricType::MORNING_MOOD_WELLBEING->value)
                            ->label(MetricType::MORNING_MOOD_WELLBEING->getLabel())
                            ->helperText(MetricType::MORNING_MOOD_WELLBEING->getScaleHint())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::MORNING_MOOD_WELLBEING->getHint()),
                            ])
                            ->grouped()
                            ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                        ToggleButtons::make(MetricType::MORNING_FIRST_DAY_PERIOD->value)
                            ->label(MetricType::MORNING_FIRST_DAY_PERIOD->getLabel())
                            ->helperText(MetricType::MORNING_FIRST_DAY_PERIOD->getScaleHint())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::MORNING_FIRST_DAY_PERIOD->getHint()),
                            ])
                            ->visible(fn () => $this->athlete->gender->value == 'w')
                            ->inline()
                            ->grouped()
                            ->options([
                                0 => 'Non',
                                1 => 'Oui',
                            ])
                            ->colors([
                                0 => 'gray',
                                1 => 'danger',
                            ]),
                    ]),
                Section::make('Avant la session')
                    ->description('Remplir avant la session.')
                    ->collapsed(fn () => now()->timezone('Europe/Zurich')->format('H') < 12)
                    ->compact()
                    ->schema([
                        ToggleButtons::make(MetricType::PRE_SESSION_ENERGY_LEVEL->value)
                            ->label(MetricType::PRE_SESSION_ENERGY_LEVEL->getLabel())
                            ->helperText(MetricType::PRE_SESSION_ENERGY_LEVEL->getScaleHint())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::PRE_SESSION_ENERGY_LEVEL->getHint()),
                            ])
                            ->inline()
                            ->grouped()
                            ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                        ToggleButtons::make(MetricType::PRE_SESSION_LEG_FEEL->value)
                            ->label(MetricType::PRE_SESSION_LEG_FEEL->getLabel())
                            ->helperText(MetricType::PRE_SESSION_LEG_FEEL->getScaleHint())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::PRE_SESSION_LEG_FEEL->getHint()),
                            ])
                            ->inline()
                            ->grouped()
                            ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                        Textarea::make(FeedbackType::PRE_SESSION_GOALS->value)
                            ->label(FeedbackType::PRE_SESSION_GOALS->getLabel())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(FeedbackType::PRE_SESSION_GOALS->getHint()),
                            ])
                            ->maxLength(255)
                            ->autosize(),
                    ]),
                Section::make('Après la session')
                    ->description('Remplir après la session.')
                    ->collapsed(fn () => now()->timezone('Europe/Zurich')->format('H') < 12)
                    ->compact()
                    ->schema([
                        ToggleButtons::make(MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->value)
                            ->label(MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->getLabel())
                            ->helperText(MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->getScaleHint())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->getHint()),
                            ])
                            ->inline()
                            ->grouped()
                            ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                        ToggleButtons::make(MetricType::POST_SESSION_SESSION_LOAD->value)
                            ->label(MetricType::POST_SESSION_SESSION_LOAD->getLabel())
                            ->helperText(MetricType::POST_SESSION_SESSION_LOAD->getScaleHint())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::POST_SESSION_SESSION_LOAD->getHint()),
                            ])
                            ->belowLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(CalculatedMetric::CPH->getDescription()),
                                CalculatedMetric::CPH->getLabelShort().':',
                                $this->athleteCurrentTrainingPlanWeek?->cphNormalizedOverTen ?? 'N/A',
                            ])
                            ->inline()
                            ->grouped()
                            ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                        ToggleButtons::make(MetricType::POST_SESSION_PERFORMANCE_FEEL->value)
                            ->label(MetricType::POST_SESSION_PERFORMANCE_FEEL->getLabel())
                            ->helperText(MetricType::POST_SESSION_PERFORMANCE_FEEL->getScaleHint())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(MetricType::POST_SESSION_PERFORMANCE_FEEL->getHint()),
                            ])
                            ->inline()
                            ->grouped()
                            ->options(fn () => array_combine(range(1, 10), range(1, 10))),
                        Textarea::make(FeedbackType::POST_SESSION_SENSATION->value)
                            ->label(FeedbackType::POST_SESSION_SENSATION->getLabel())
                            ->afterLabel([
                                Icon::make(Heroicon::OutlinedInformationCircle)
                                    ->color('gray')
                                    ->tooltip(FeedbackType::POST_SESSION_SENSATION->getHint()),
                            ])
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $metricsData = collect($data)->filter(function (mixed $value, string $key) {
            return in_array($key, $this->desiredMetricTypes());
        });
        foreach ($metricsData as $metric => $value) {
            if ($value != null && $value != '<p></p>') {
                Metric::updateOrCreate(
                    ['athlete_id' => $this->athlete->id, 'date' => $this->date, 'metric_type' => $metric, 'type' => $this->type],
                    [MetricType::from($metric)->getValueColumn() ?? 'value' => $value, 'unit' => MetricType::from($metric)->getUnit()]
                );
            }
        }

        $feedbacksData = collect($data)->filter(function (mixed $value, string $key) {
            return in_array($key, $this->desiredFeedbackTypes());
        });
        foreach ($feedbacksData as $feedback => $content) {
            if ($content != null && $content != '<p></p>') {
                Feedback::updateOrCreate(
                    ['athlete_id' => $this->athlete->id, 'date' => $this->date, 'type' => $feedback],
                    ['content' => $content, 'author_type' => 'athlete']
                );
            }
        }

    }

    private function desiredMetricTypes(): array
    {
        return [
            MetricType::MORNING_HRV->value,
            MetricType::MORNING_SLEEP_QUALITY->value,
            MetricType::MORNING_GENERAL_FATIGUE->value,
            MetricType::MORNING_MOOD_WELLBEING->value,
            MetricType::MORNING_FIRST_DAY_PERIOD->value,
            MetricType::PRE_SESSION_ENERGY_LEVEL->value,
            MetricType::PRE_SESSION_LEG_FEEL->value,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->value,
            MetricType::POST_SESSION_SESSION_LOAD->value,
            MetricType::POST_SESSION_PERFORMANCE_FEEL->value,
        ];
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
        return view('livewire.athlete-daily-metric-form');
    }
}
