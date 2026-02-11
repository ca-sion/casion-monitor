<?php

namespace App\Livewire;

use App\Models\Metric;
use App\Models\Athlete;
use Livewire\Component;
use App\Enums\MetricType;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class AthleteMonthlyForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public Athlete $athlete;

    public Carbon $date;

    public $currentMonth;

    public function mount(): void
    {
        $this->athlete = Auth::guard('athlete')->user();
        $this->date = Carbon::now()->startOfMonth();
        $this->currentMonth = $this->date->locale('fr_CH')->isoFormat('MMMM YYYY');

        $data = collect();
        $data->put('current_month', $this->currentMonth);

        $monthlyMetricTypes = [
            MetricType::MORNING_BODY_WEIGHT_KG,
            MetricType::MONTHLY_MENTAL_LOAD,
            MetricType::MONTHLY_MOTIVATION,
        ];

        $existingEntries = Metric::where('athlete_id', $this->athlete->id)
            ->whereIn('metric_type', collect($monthlyMetricTypes)->pluck('value'))
            ->whereMonth('date', $this->date->month)
            ->whereYear('date', $this->date->year)
            ->get();

        if ($existingEntries->isNotEmpty()) {
            foreach ($existingEntries as $entry) {
                $data->put($entry->metric_type->value, $entry->value);
            }

            Notification::make()
                ->title('Vous avez déjà des entrées pour ce mois. Vous pouvez les mettre à jour.')
                ->info()
                ->send();
        }

        $this->form->fill($data->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('current_month')
                    ->label('Mois en cours')
                    ->state($this->currentMonth),

                TextInput::make(MetricType::MORNING_BODY_WEIGHT_KG->value)
                    ->label(MetricType::MORNING_BODY_WEIGHT_KG->getLabel())
                    ->helperText(MetricType::MORNING_BODY_WEIGHT_KG->getHint())
                    ->numeric()
                    ->suffix('kg')
                    ->required()
                    ->step(0.01)
                    ->maxValue(250)
                    ->visible(fn () => $this->athlete->getPreference('track_monthly_weight', true)),

                TextInput::make(MetricType::MONTHLY_MENTAL_LOAD->value)
                    ->label(MetricType::MONTHLY_MENTAL_LOAD->getLabel())
                    ->helperText(MetricType::MONTHLY_MENTAL_LOAD->getHint())
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(10)
                    ->hint(MetricType::MONTHLY_MENTAL_LOAD->getScaleHint()),

                TextInput::make(MetricType::MONTHLY_MOTIVATION->value)
                    ->label(MetricType::MONTHLY_MOTIVATION->getLabel())
                    ->helperText(MetricType::MONTHLY_MOTIVATION->getHint())
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(10)
                    ->hint(MetricType::MONTHLY_MOTIVATION->getScaleHint()),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $metricsToSave = [
            MetricType::MONTHLY_MENTAL_LOAD,
            MetricType::MONTHLY_MOTIVATION,
        ];

        if ($this->athlete->getPreference('track_monthly_weight', true)) {
            $metricsToSave[] = MetricType::MORNING_BODY_WEIGHT_KG;
        }

        foreach ($metricsToSave as $metricType) {
            $value = data_get($data, $metricType->value);

            if ($value !== null) {
                Metric::updateOrCreate([
                    'athlete_id'  => $this->athlete->id,
                    'metric_type' => $metricType->value,
                    'date'        => $this->date,
                ],
                    [
                        'value' => $value,
                    ]);
            }
        }

        Notification::make()
            ->title('Vos données mensuelles ont été enregistrées')
            ->success()
            ->send();
    }

    #[Layout('components.layouts.athlete')]
    public function render()
    {
        return view('livewire.athlete-monthly-form');
    }
}
