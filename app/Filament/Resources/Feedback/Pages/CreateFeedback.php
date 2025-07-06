<?php

namespace App\Filament\Resources\Feedback\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Feedback\FeedbackResource;

class CreateFeedback extends CreateRecord
{
    protected static string $resource = FeedbackResource::class;
}
