<?php

namespace App\Livewire;

use App\Models\Metric;
use App\Models\Athlete;
use Livewire\Component;
use App\Enums\MetricType;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Actions\Contracts\HasActions;

class AthleteMenstrualCycleForm extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    public ?array $data = [];

    public Athlete $athlete;

    public function mount(): void
    {
        $this->athlete = Auth::guard('athlete')->user();

        $menstrualCycleDates = $this->athlete->metrics()
            ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD)
            ->orderBy('date', 'desc')
            ->pluck('date')
            ->map(fn ($date) => ['date' => Carbon::parse($date)->format('Y-m-d')])
            ->toArray();

        $this->form->fill(['menstrual_cycle_dates' => $menstrualCycleDates]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('menstrual_cycle_dates')
                    ->label('Dates du premier jour des règles')
                    ->schema([
                        DatePicker::make('date')
                            ->label('Date')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->locale('fr_CH')
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule, \Closure $get) {
                                    return $rule->where('athlete_id', $this->athlete->id)
                                        ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD);
                                }
                            ),
                    ])
                    ->defaultItems(1)
                    ->addActionLabel('Ajouter une date')
                    ->itemLabel(fn (array $state): ?string => $state['date'] ?? null)
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Get existing menstrual cycle dates
        $existingMetrics = $this->athlete->metrics()
            ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD)
            ->get()
            ->keyBy(fn ($metric) => Carbon::parse($metric->date)->toDateString());

        $submittedDates = collect($data['menstrual_cycle_dates'])
            ->map(fn ($item) => Carbon::parse($item['date'])->toDateString())
            ->unique();

        // Determine dates to delete
        $datesToDelete = $existingMetrics->keys()->diff($submittedDates);

        // Determine dates to create
        $datesToCreate = $submittedDates->diff($existingMetrics->keys());

        // Delete metrics that are no longer in the submitted data
        foreach ($datesToDelete as $date) {
            $existingMetrics[$date]->delete();
        }

        // Create new metrics for dates that are in the submitted data but not in existing
        foreach ($datesToCreate as $date) {
            Metric::create([
                'athlete_id'  => $this->athlete->id,
                'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD,
                'date'        => $date,
                'value'       => 1, // Value for MORNING_FIRST_DAY_PERIOD is typically 1 (true)
            ]);
        }

        Notification::make()
            ->title('Dates des règles enregistrées avec succès')
            ->success()
            ->send();
    }

    #[Layout('components.layouts.athlete')]
    public function render()
    {
        return view('livewire.athlete-menstrual-cycle-form');
    }
}
