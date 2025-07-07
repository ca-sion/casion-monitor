<?php

namespace App\Filament\Resources\TrainingPlans\Pages;

use App\Models\TrainingPlanWeek;
use Filament\Resources\Pages\Page;
use App\Filament\Resources\TrainingPlans\TrainingPlanResource;
use Illuminate\Support\Facades\Log;

class TrainingPlanCalendar extends Page
{

    protected static string $resource = TrainingPlanResource::class;

    protected string $view = 'filament.resources.training-plans.pages.training-plan-calendar';

    public function mount(): void
    {
        // Pas besoin de résoudre un enregistrement spécifique pour cette page de calendrier générale
    }

    public $selectedTrainingPlanId;

    public $weeks = []; // Nouvelle propriété pour stocker toutes les semaines du plan

    public function getTrainingPlans(): array
    {
        return \App\Models\TrainingPlan::all(['id', 'name'])->toArray();
    }

    public function updatedSelectedTrainingPlanId(string $id): void
    {
        $this->selectedTrainingPlanId = $id;

        $trainingPlan = \App\Models\TrainingPlan::find($id);

        if ($trainingPlan && $trainingPlan->start_date && $trainingPlan->end_date) {
            $startDate = \Carbon\Carbon::parse($trainingPlan->start_date);
            $endDate = \Carbon\Carbon::parse($trainingPlan->end_date);

            $this->weeks = [];
            $currentWeekStart = $startDate->startOfWeek(\Carbon\Carbon::MONDAY);

            while ($currentWeekStart->lte($endDate)) {
                $weekNumber = $currentWeekStart->weekOfYear;
                $year = $currentWeekStart->year;
                $weekIdentifier = "{$year}-W{$weekNumber}"; // Identifiant unique pour la semaine

                // Récupérer les données de la semaine si elles existent
                $weekData = TrainingPlanWeek::where('training_plan_id', $id)
                                            ->where('week_number', $weekNumber)
                                            ->first();

                $this->weeks[] = [
                    'start_date' => $currentWeekStart->toDateString(),
                    'end_date' => $currentWeekStart->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString(),
                    'week_number' => $weekNumber,
                    'year' => $year,
                    'identifier' => $weekIdentifier,
                    'volume_planned' => $weekData->volume_planned ?? null,
                    'intensity_planned' => $weekData->intensity_planned ?? null,
                    'exists' => ($weekData?->volume_planned !== null || $weekData?->intensity_planned !== null),
                    'id' => $weekData?->id ?? null,
                ];

                $currentWeekStart->addWeek();
            }
        } else {
            $this->weeks = [];
        }

        \Filament\Notifications\Notification::make()
            ->title('Plan d\'entraînement sélectionné')
            ->body("Vous avez sélectionné le plan : " . ($trainingPlan ? $trainingPlan->name : 'N/A'))
            ->success()
            ->send();
    }

    public function createNewTrainingPlan(): void
    {
        // Rediriger vers la page de création d'un nouveau plan d'entraînement
        $this->redirect(TrainingPlanResource::getUrl('create'));
    }

    public function selectWeekForDailyRefinement(string $date): void
    {
        if (!$this->selectedTrainingPlanId) {
            \Filament\Notifications\Notification::make()
                ->title('Erreur')
                ->body('Veuillez sélectionner un plan d\'entraînement d\'abord.')
                ->danger()
                ->send();
            return;
        }

        // Convertir la date pour obtenir le début de la semaine (lundi)
        $startOfWeek = \Carbon\Carbon::parse($date)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        
        // Ici, nous pourrions ouvrir un modal ou rediriger vers une autre page
        // pour l'affinage quotidien. Pour l'instant, juste une notification.
        \Filament\Notifications\Notification::make()
            ->title('Affinage quotidien')
            ->body("Vous allez affiner les jours de la semaine du : {$startOfWeek} pour le plan : " . \App\Models\TrainingPlan::find($this->selectedTrainingPlanId)->name)
            ->info()
            ->send();
    }

    public function updateWeekData(string $startDate, string $field, $value): void
    {
        if (!$this->selectedTrainingPlanId) {
            \Filament\Notifications\Notification::make()
                ->title('Erreur')
                ->body('Veuillez sélectionner un plan d\'entraînement d\'abord.')
                ->danger()
                ->send();
            return;
        }

        $startOfWeek = \Carbon\Carbon::parse($startDate)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $weekNumber = \Carbon\Carbon::parse($startOfWeek)->weekOfYear;

        $weekData = TrainingPlanWeek::updateOrCreate(
            [
                'training_plan_id' => $this->selectedTrainingPlanId,
                'week_number' => $weekNumber,
            ],
            [
                $field => $value,
            ]
        );

        // Mettre à jour la propriété $weeks pour refléter le changement dans l'interface
        foreach ($this->weeks as $key => $week) {
            if ($week['week_number'] === $weekNumber) {
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
