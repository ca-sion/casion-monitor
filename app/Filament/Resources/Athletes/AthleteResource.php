<?php

namespace App\Filament\Resources\Athletes;

use BackedEnum;
use App\Models\Athlete;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\Athletes\Pages\EditAthlete;
use App\Filament\Resources\Athletes\Pages\ListAthletes;
use App\Filament\Resources\Athletes\Pages\CreateAthlete;
use App\Filament\Resources\Athletes\Schemas\AthleteForm;
use App\Filament\Resources\Athletes\Tables\AthletesTable;

class AthleteResource extends Resource
{
    protected static ?string $model = Athlete::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AthleteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AthletesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAthletes::route('/'),
            'create' => CreateAthlete::route('/create'),
            'edit'   => EditAthlete::route('/{record}/edit'),
        ];
    }
}
