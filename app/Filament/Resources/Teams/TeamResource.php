<?php

namespace App\Filament\Resources\Teams;

use App\Filament\Resources\Teams\Pages\CreateTeam;
use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ListTeams;
use App\Filament\Resources\Teams\Pages\ViewTeam;
use App\Models\Club;
use App\Models\Team;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Users;
    protected static string|UnitEnum|null $navigationGroup = 'Organizations';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Team')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Select::make('club_id')
                        ->label('Club')
                        ->options(
                            Club::query()
                                ->with('league')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(function (Club $club) {
                                    $label = $club->name;

                                    if ($club->league?->name) {
                                        $label .= ' (' . $club->league->name . ')';
                                    }

                                    return [$club->id => $label];
                                })
                                ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),

                    Placeholder::make('league_preview')
                        ->label('League')
                        ->content(function (callable $get, $record) {
                            $clubId = $get('club_id');

                            if ($clubId) {
                                $club = Club::with('league')->find($clubId);
                                return $club?->league?->name ?: '—';
                            }

                            if ($record?->club?->league?->name) {
                                return $record->club->league->name;
                            }

                            return '—';
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('club.name')
                    ->label('Club')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('club.league.name')
                    ->label('League')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated'),
            ])
            ->filters([
                SelectFilter::make('club_id')
                    ->label('Club')
                    ->relationship('club', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('league')
                    ->label('League')
                    ->relationship('club.league', 'name'),
            ])
            ->recordUrl(fn (Team $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/create'),
            'view' => ViewTeam::route('/{record}'),
            'edit' => EditTeam::route('/{record}/edit'),
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