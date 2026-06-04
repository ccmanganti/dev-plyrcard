<?php

namespace App\Filament\Resources\ClubReferrals;

use App\Filament\Resources\ClubReferrals\Pages\CreateClubReferral;
use App\Filament\Resources\ClubReferrals\Pages\ListClubReferrals;
use App\Models\ClubLeague;
use App\Models\ClubReferral;
use App\Support\ClubManagerAccess;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ClubReferralResource extends Resource
{
    protected static ?string $model = ClubReferral::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string | UnitEnum | null $navigationGroup = 'Club Admin';

    protected static ?string $navigationLabel = 'Invites';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static function ageGroupOptions(): array
    {
        return collect(config('plyrcard.age_groups', ['U13', 'U14', 'U15', 'U16', 'U17', 'U18', 'U19']))
            ->mapWithKeys(fn ($label) => [(string) $label => (string) $label])
            ->all();
    }

    protected static function programOptions(?string $clubId): array
    {
        if (blank($clubId) || ! ClubManagerAccess::userCanAccessClub(auth()->user(), (int) $clubId)) {
            return [];
        }

        return ClubLeague::query()
            ->with('league')
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function (ClubLeague $program): array {
                $league = $program->league;
                $sport = $program->sport ?: $league?->sport;
                $label = collect([$league?->name, $sport])->filter()->implode(' • ');

                return [(string) $program->id => $label ?: 'Program #' . $program->id];
            })
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        $clubIds = ClubManagerAccess::clubAdminClubIds(auth()->user());
        $clubId = count($clubIds) === 1 ? $clubIds[0] : null;

        return $schema->components([
            Hidden::make('club_manager_id')->default(fn () => auth()->id()),
            Hidden::make('club_id')->default($clubId),

            Section::make('Invite Details')
                ->description('Create a registration link for this club. Club, league, sport, and team values will be locked in the intake form.')
                ->columns(2)
                ->schema([
                    Select::make('club_league_id')
                        ->label('Program / League')
                        ->options(fn (): array => static::programOptions((string) $clubId))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->afterStateUpdated(function ($state, callable $set): void {
                            $program = filled($state)
                                ? ClubLeague::query()->with('league')->find($state)
                                : null;

                            $set('league_id', $program?->league_id);
                            $set('sport', $program?->sport ?: $program?->league?->sport);
                            $set('gender', collect($program?->genders ?? [])->first());
                        }),

                    Hidden::make('league_id'),
                    Hidden::make('sport'),
                    Hidden::make('gender'),

                    Select::make('team_name')
                        ->label('Team / Age Group')
                        ->options(fn (): array => static::ageGroupOptions())
                        ->searchable()
                        ->required(),

                    TextInput::make('invited_name')->label('Invitee Name')->maxLength(255),
                    TextInput::make('invited_email')->label('Invitee Email')->email()->maxLength(255),

                    Placeholder::make('url_note')
                        ->label('Invite Link')
                        ->content(new HtmlString('After saving, copy the generated invite URL from the table.'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->whereIn('club_id', ClubManagerAccess::clubAdminClubIds(auth()->user()) ?: [-1])
                    ->with(['league', 'registeredUser'])
            )
            ->columns([
                TextColumn::make('league.name')->label('League')->searchable(),
                TextColumn::make('team_name')->label('Team')->badge()->searchable(),
                TextColumn::make('invited_name')->label('Invitee')->searchable(),
                TextColumn::make('invited_email')->label('Email')->searchable()->copyable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('invite_url')->label('Invite URL')->copyable()->limit(48),
                TextColumn::make('clicked_at')->label('Clicked')->since()->placeholder('Not clicked'),
                TextColumn::make('registered_at')->label('Registered')->since()->placeholder('Not registered'),
                TextColumn::make('created_at')->label('Created')->since()->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubReferrals::route('/'),
            'create' => CreateClubReferral::route('/create'),
        ];
    }
}
