<?php

namespace App\Filament\Resources\ClubReferrals;

use App\Filament\Clusters\Organizations;
use App\Filament\Resources\ClubReferrals\Pages\ListClubReferrals;
use App\Models\ClubReferral;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClubReferralResource extends Resource
{
    protected static ?string $model = ClubReferral::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPaperAirplane;
    protected static ?string $cluster = Organizations::class;
    protected static ?string $navigationLabel = 'Invite Tracking';
    protected static ?string $modelLabel = 'Invite';
    protected static ?string $pluralModelLabel = 'Invite Tracking';
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('Superadmin') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['club', 'league']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invited_email')->label('Invitee Email')->searchable()->copyable(),
                TextColumn::make('invited_name')->label('Invitee Name')->placeholder('-')->searchable(),
                TextColumn::make('club.name')->label('Club')->searchable(),
                TextColumn::make('league.name')->label('League')->searchable(),
                TextColumn::make('team_name')->label('Team')->badge(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (?string $state): string => filled($state) ? str($state)->title() : 'Pending'),
                TextColumn::make('click_count')->label('Clicks')->state(fn ($record): int => (int) ($record->click_count ?? 0))->sortable(),
                TextColumn::make('sent_at')->label('Sent')->since()->placeholder('-'),
                TextColumn::make('clicked_at')->label('Last Click')->since()->placeholder('-'),
                TextColumn::make('accepted_at')->label('Accepted')->since()->placeholder('-'),
                TextColumn::make('created_at')->label('Created')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubReferrals::route('/'),
        ];
    }
}