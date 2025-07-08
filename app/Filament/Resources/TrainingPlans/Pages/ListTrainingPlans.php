<?php

namespace App\Filament\Resources\TrainingPlans\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TrainingPlans\TrainingPlanResource;

class ListTrainingPlans extends ListRecords
{
    protected static string $resource = TrainingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
