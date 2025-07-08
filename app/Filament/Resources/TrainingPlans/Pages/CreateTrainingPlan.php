<?php

namespace App\Filament\Resources\TrainingPlans\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\TrainingPlans\TrainingPlanResource;

class CreateTrainingPlan extends CreateRecord
{
    protected static string $resource = TrainingPlanResource::class;
}
