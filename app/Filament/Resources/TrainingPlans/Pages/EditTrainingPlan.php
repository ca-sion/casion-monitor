<?php

namespace App\Filament\Resources\TrainingPlans\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\TrainingPlans\TrainingPlanResource;

class EditTrainingPlan extends EditRecord
{
    protected static string $resource = TrainingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('allocate')
                ->url(AllocateTrainingPlan::getUrl(['record' => $this->record])),
        ];
    }
}
