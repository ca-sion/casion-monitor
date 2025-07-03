<?php

namespace App\Filament\Resources\Trainers\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;

class TrainerForm
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
                Select::make('athletes')
                    ->label('Athlètes')
                    ->relationship('athletes')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->first_name} {$record->last_name}")
                    ->multiple()
                    ->preload(),
            ]);
    }
}
