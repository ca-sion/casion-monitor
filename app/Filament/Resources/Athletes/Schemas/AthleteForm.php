<?php

namespace App\Filament\Resources\Athletes\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AthleteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                TextInput::make('email')
                    ->email(),
                DatePicker::make('birthdate'),
                TextInput::make('gender'),
                DatePicker::make('last_connection'),
            ]);
    }
}
