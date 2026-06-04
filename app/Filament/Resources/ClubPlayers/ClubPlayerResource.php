<?php

namespace App\Filament\Resources\ClubPlayers;

use App\Filament\Resources\ClubPlayers\Pages\ListClubPlayers;
use App\Models\User;
use App\Support\ClubManagerAccess;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClubPlayerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string | UnitEnum | null $navigationGroup = 'Club Admin';

    protected static ?string $navigationLabel = 'Players';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Player Information')
                ->description('Player information is read-only for Club Managers.')
                ->columns(3)
                ->schema([
                    TextInput::make('first_name')->label('First Name')->disabled()->dehydrated(false),
                    TextInput::make('last_name')->label('Last Name')->disabled()->dehydrated(false),
                    TextInput::make('email')->label('Email')->disabled()->dehydrated(false),
                    TextInput::make('sport')->label('Sport')->disabled()->dehydrated(false),
                    TextInput::make('team_name')->label('Team')->disabled()->dehydrated(false),
                    TextInput::make('club.name')->label('Club')->disabled()->dehydrated(false),
                    TextInput::make('league.name')->label('League')->disabled()->dehydrated(false),
                    Placeholder::make('access_note')->label('Access')->content('Read-only view.')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => ClubManagerAccess::scopePlayers($query, auth()->user())->with(['club', 'league']))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->state(fn (User $record): string => trim($record->first_name . ' ' . $record->last_name) ?: '-')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('sport')->label('Sport')->badge(),
                TextColumn::make('team_name')->label('Team')->searchable(),
                TextColumn::make('created_at')->label('Registered')->date()->sortable(),
            ])
            ->actions([
                ViewAction::make()->modalWidth('5xl'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubPlayers::route('/'),
        ];
    }
}
