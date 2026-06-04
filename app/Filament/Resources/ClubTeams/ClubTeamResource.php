<?php

namespace App\Filament\Resources\ClubTeams;

use App\Filament\Resources\ClubTeams\Pages\ListClubTeams;
use App\Models\Team;
use App\Support\ClubManagerAccess;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClubTeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string | UnitEnum | null $navigationGroup = 'Club Admin';

    protected static ?string $navigationLabel = 'Teams';

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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Team Information')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Team')->disabled()->dehydrated(false),
                    TextInput::make('club.name')->label('Club')->disabled()->dehydrated(false),
                    TextInput::make('sport')->label('Sport')->disabled()->dehydrated(false),
                    TextInput::make('gender')->label('Gender')->disabled()->dehydrated(false),
                    TextInput::make('age_group')->label('Age Group')->disabled()->dehydrated(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => ClubManagerAccess::scopeTeams($query, auth()->user())->with('club'))
            ->columns([
                TextColumn::make('name')->label('Team')->searchable()->sortable(),
                TextColumn::make('club.name')->label('Club'),
                TextColumn::make('sport')->label('Sport')->badge(),
                TextColumn::make('gender')->label('Gender')->badge(),
                TextColumn::make('age_group')->label('Age Group')->badge(),
            ])
            ->actions([
                ViewAction::make()->modalWidth('3xl'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubTeams::route('/'),
        ];
    }
}
