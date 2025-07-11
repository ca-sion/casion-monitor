<?php

namespace App\Filament\Resources\TrainingPlans\Pages;

use App\Models\TrainingPlanWeek;
use Filament\Resources\Pages\Page;
use App\Services\MetricCalculationService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use App\Filament\Resources\TrainingPlans\TrainingPlanResource;

class AllocateTrainingPlan extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TrainingPlanResource::class;

    protected string $view = 'filament.resources.training-plans.pages.allocate';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->loadWeeks();
    }

    public $weeks = [];

    protected function loadWeeks(): void
    {
        $trainingPlan = $this->record;

        if ($trainingPlan && $trainingPlan->start_date && $trainingPlan->end_date) {
            $startDate = \Carbon\Carbon::parse($trainingPlan->start_date);
            $endDate = \Carbon\Carbon::parse($trainingPlan->end_date);

            $this->weeks = [];
            $currentWeekStart = $startDate->startOfWeek(\Carbon\Carbon::MONDAY);

            while ($currentWeekStart->lte($endDate)) {
                $weekNumber = $currentWeekStart->weekOfYear;
                $year = $currentWeekStart->year;
                $weekIdentifier = "{$year}-W{$weekNumber}";

                // Récupérer les données de la semaine si elles existent
                $weekData = TrainingPlanWeek::where('training_plan_id', $this->record->id)
                    ->where('start_date', $currentWeekStart->toDateString())
                    ->first();

                // Calcul des métriques de charge pour la semaine
                $cph = resolve(MetricCalculationService::class)->calculateCph($weekData ?? new TrainingPlanWeek);

                $this->weeks[] = [
                    'start_date'        => $currentWeekStart->toDateString(),
                    'end_date'          => $currentWeekStart->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString(),
                    'week_number'       => $weekNumber,
                    'year'              => $year,
                    'identifier'        => $weekIdentifier,
                    'volume_planned'    => $weekData->volume_planned ?? null,
                    'intensity_planned' => $weekData->intensity_planned ?? null,
                    'exists'            => ($weekData?->volume_planned !== null || $weekData?->intensity_planned !== null),
                    'id'                => $weekData?->id ?? null,
                    'cph'               => $cph,
                ];

                $currentWeekStart->addWeek()->startOfWeek();
            }
        } else {
            $this->weeks = [];
        }

        \Filament\Notifications\Notification::make()
            ->title('Plan d\'entraînement chargé')
            ->success()
            ->send();
    }

    public function selectWeekForDailyRefinement(string $date): void
    {
        // Convertir la date pour obtenir le début de la semaine (lundi)
        $startOfWeek = \Carbon\Carbon::parse($date)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();

        // Ici, nous pourrions ouvrir un modal ou rediriger vers une autre page
        // pour l'affinage quotidien. Pour l'instant, juste une notification.
        \Filament\Notifications\Notification::make()
            ->title('Affinage quotidien')
            ->body("Vous allez affiner les jours de la semaine du : {$startOfWeek} pour le plan : ".$this->record->name)
            ->info()
            ->send();
    }

    public function updateWeekData(string $startDate, string $field, $value): void
    {
        $startOfWeek = \Carbon\Carbon::parse($startDate)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $weekNumber = \Carbon\Carbon::parse($startOfWeek)->weekOfYear;

        $weekData = TrainingPlanWeek::updateOrCreate(
            [
                'training_plan_id' => $this->record->id,
                'start_date'       => $startOfWeek,
            ],
            [
                $field => $value,
            ]
        );

        // Mettre à jour la propriété $weeks pour refléter le changement dans l'interface
        foreach ($this->weeks as $key => $week) {
            if ($week['start_date'] === $startOfWeek) {
                $this->weeks[$key][$field] = $value;
                break;
            }
        }

        \Filament\Notifications\Notification::make()
            ->title('Données hebdomadaires mises à jour')
            ->body("{$field} mis à jour pour la semaine {$weekNumber}.")
            ->success()
            ->send();
    }
}
