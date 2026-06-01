<?php

namespace App\Filament\Resources\NationalTeams;

use App\Filament\Clusters\Organizations;
use App\Filament\Resources\NationalTeams\Pages\CreateNationalTeam;
use App\Filament\Resources\NationalTeams\Pages\EditNationalTeam;
use App\Filament\Resources\NationalTeams\Pages\ListNationalTeams;
use App\Filament\Resources\NationalTeams\Pages\ViewNationalTeam;
use App\Models\NationalTeam;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class NationalTeamResource extends Resource
{
    protected static ?string $model = NationalTeam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Flag;
    protected static ?string $cluster = Organizations::class;
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';

    protected static function isSuperadminNavigationUser(): bool
    {
        $user = auth()->user();

        return $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperadminNavigationUser();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('National Team')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('national-team-logos')
                        ->visibility('public')
                        ->helperText('Upload the national team logo.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->square()
                    ->toggleable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (NationalTeam $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNationalTeams::route('/'),
            'create' => CreateNationalTeam::route('/create'),
            'edit' => EditNationalTeam::route('/{record}/edit'),
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