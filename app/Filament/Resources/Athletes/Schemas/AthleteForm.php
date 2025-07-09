<?php

namespace App\Filament\Resources\Athletes\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;

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
                DateTimePicker::make('last_connection')
                    ->native(false)
                    ->displayFormat('d.m.Y H:i')
                    ->locale('fr'),
                Select::make('trainingPlans')
                    ->label('Plans d\'entraînement')
                    ->relationship('trainingPlans', 'name')
                    ->multiple()
                    ->preload(),
            ]);
    }
}
