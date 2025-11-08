<?php

namespace App\Filament\Resources\Athletes\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\CodeEntry;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\CodeEditor\Enums\Language;

class AthleteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->label('Prénom'),
                TextInput::make('last_name')
                    ->label('Nom de famille'),
                TextInput::make('email')
                    ->label('Email')
                    ->email(),
                DatePicker::make('birthdate')
                    ->label('Date de naissance'),
                Select::make('gender')
                    ->label('Genre')
                    ->options([
                        'm' => 'Homme',
                        'w' => 'Femme',
                    ]),
                DateTimePicker::make('last_activity')
                    ->label('Dernière activité')
                    ->native(false)
                    ->displayFormat('d.m.Y H:i')
                    ->locale('fr'),
                DateTimePicker::make('last_connection')
                    ->label('Dernière connexion')
                    ->native(false)
                    ->displayFormat('d.m.Y H:i')
                    ->locale('fr'),
                Select::make('trainingPlans')
                    ->label('Plans d\'entraînement')
                    ->relationship('trainingPlans', 'name')
                    ->multiple()
                    ->preload(),
                KeyValue::make('preferences')
                    ->label('Préférences'),
                CodeEditor::make('metadata')
                    ->label('Metadonnées')
                    ->language(Language::Json),
            ]);
    }
}
