<?php

namespace App\Filament\Resources\Leagues;

use App\Filament\Resources\Leagues\Pages\CreateLeague;
use App\Filament\Resources\Leagues\Pages\EditLeague;
use App\Filament\Resources\Leagues\Pages\ListLeagues;
use App\Filament\Resources\Leagues\Pages\ViewLeague;
use App\Models\League;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use Filament\Forms\Components\Select;

class LeagueResource extends Resource
{
    protected static ?string $model = League::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Trophy;
    protected static string|UnitEnum|null $navigationGroup = 'Organizations';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('League')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('gender')->required()
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('gender')->searchable()->sortable(),
                TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (League $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeagues::route('/'),
            'create' => CreateLeague::route('/create'),
            'view' => ViewLeague::route('/{record}'),
            'edit' => EditLeague::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}