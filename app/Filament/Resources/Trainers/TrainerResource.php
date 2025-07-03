<?php

namespace App\Filament\Resources\Trainers;

use BackedEnum;
use App\Models\Trainer;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\Trainers\Pages\EditTrainer;
use App\Filament\Resources\Trainers\Pages\ListTrainers;
use App\Filament\Resources\Trainers\Pages\CreateTrainer;
use App\Filament\Resources\Trainers\Schemas\TrainerForm;
use App\Filament\Resources\Trainers\Tables\TrainersTable;

class TrainerResource extends Resource
{
    protected static ?string $model = Trainer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TrainerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainersTable::configure($table);
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
            'index'  => ListTrainers::route('/'),
            'create' => CreateTrainer::route('/create'),
            'edit'   => EditTrainer::route('/{record}/edit'),
        ];
    }
}
