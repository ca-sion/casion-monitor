<?php

namespace App\Filament\Resources\Professionals\Schemas;

use App\Enums\ProfessionalType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProfessionalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                TextInput::make('name')->readOnly(),
                Select::make('type')
                    ->options(ProfessionalType::class),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('address'),
                TextInput::make('locality'),
                TextInput::make('postal_code'),
                Textarea::make('note')
                    ->columnSpanFull(),
            ]);
    }
}
