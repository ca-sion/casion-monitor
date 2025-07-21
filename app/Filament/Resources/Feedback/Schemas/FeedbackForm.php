<?php

namespace App\Filament\Resources\Feedback\Schemas;

use App\Enums\FeedbackType;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;

class FeedbackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('athlete_id')
                    ->relationship('athlete', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->first_name} {$record->last_name}"),
                Select::make('trainer_id')
                    ->relationship('trainer', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->first_name} {$record->last_name}"),
                DatePicker::make('date')
                    ->native(false)
                    ->displayFormat('d.m.Y'),
                Select::make('type')
                    ->options(FeedbackType::class),
                TextInput::make('author_type'),
                Textarea::make('content')
                    ->columnSpanFull(),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
