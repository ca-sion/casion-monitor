<?php

namespace App\Filament\Resources\Metrics;

use BackedEnum;
use App\Models\Metric;
use App\Enums\MetricType;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\Metrics\Pages\ManageMetrics;

class MetricResource extends Resource
{
    protected static ?string $model = Metric::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('athlete_id')
                    ->relationship('athlete', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->first_name} {$record->last_name}"),
                DatePicker::make('date'),
                TextInput::make('type'),
                Select::make('metric_type')
                    ->options(MetricType::class),
                TextInput::make('value')
                    ->numeric(),
                TextInput::make('unit'),
                TextInput::make('time'),
                Textarea::make('note')
                    ->columnSpanFull(),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('athlete.id')
                    ->numeric(),
                TextEntry::make('date')
                    ->date(),
                TextEntry::make('type'),
                TextEntry::make('metric_type'),
                TextEntry::make('value')
                    ->numeric(),
                TextEntry::make('unit'),
                TextEntry::make('time'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('athlete.id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('metric_type')
                    ->searchable(),
                TextColumn::make('value')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit')
                    ->searchable(),
                TextColumn::make('time')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMetrics::route('/'),
        ];
    }
}
