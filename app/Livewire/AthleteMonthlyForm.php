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

    public Athlete $athlete;

    public Carbon $date;

    public $currentMonth;

    public function mount(): void
    {
        $this->athlete = Auth::guard('athlete')->user();
        $this->date = Carbon::now()->startOfMonth();
        $this->currentMonth = $this->date->locale('fr_CH')->isoFormat('MMMM YYYY');

        $existingEntry = Metric::where('athlete_id', $this->athlete->id)
            ->where('metric_type', MetricType::MORNING_BODY_WEIGHT_KG->value)
            ->whereMonth('date', $this->date->month)
            ->whereYear('date', $this->date->year)
            ->first();

        $data = collect();

        if ($existingEntry) {
            $data->put(MetricType::MORNING_BODY_WEIGHT_KG->value, $existingEntry->value);
        }
        $data->put('current_month', $this->currentMonth);

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
                    ->numeric()
                    ->suffix('kg')
                    ->required()
                    ->step(0.01)
                    ->maxValue(250),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $existingEntry = Metric::where('athlete_id', $this->athlete->id)
            ->where('metric_type', MetricType::MORNING_BODY_WEIGHT_KG->value)
            ->whereMonth('date', $this->date->month)
            ->whereYear('date', $this->date->year)
            ->first();

        if ($existingEntry) {
            Notification::make()
                ->title('Vous avez déjà soumis votre poids pour ce mois.')
                ->warning()
                ->send();

            return;
        }

        Metric::create([
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_BODY_WEIGHT_KG->value,
            'value'       => $this->weight,
            'date'        => $this->date,
        ]);

        Notification::make()
            ->title('Votre poids a été enregistré')
            ->success()
            ->send();
    }

    #[Layout('components.layouts.athlete')]
    public function render()
    {
        return view('livewire.athlete-monthly-form');
    }
}
