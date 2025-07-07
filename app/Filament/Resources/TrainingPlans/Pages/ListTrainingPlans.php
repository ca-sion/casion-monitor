<?php

namespace App\Filament\Resources\TrainingPlans\Pages;

use App\Filament\Resources\TrainingPlans\TrainingPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
