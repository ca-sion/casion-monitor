<?php

namespace App\Filament\Resources\Athletes\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Athletes\AthleteResource;

class CreateAthlete extends CreateRecord
{
    protected static string $resource = AthleteResource::class;
}
