<?php

namespace App\Filament\Resources\Clubs;

use App\Filament\Resources\Clubs\Pages\CreateClub;
use App\Filament\Resources\Clubs\Pages\EditClub;
use App\Filament\Resources\Clubs\Pages\ListClubs;
use App\Filament\Resources\Clubs\Pages\ViewClub;
use App\Models\Club;
use App\Models\Conference;
use App\Models\League;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShieldCheck;
    protected static string|UnitEnum|null $navigationGroup = 'Organizations';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Club')
                ->columnSpanFull()
                ->columns(1)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Select::make('league_id')
                        ->label('League')
                        ->options(
                            League::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('conference_id')
                        ->label('Conference')
                        ->options(
                            Conference::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    TextInput::make('city')
                        ->label('City')
                        ->maxLength(255),

                    TextInput::make('state')
                        ->label('State')
                        ->maxLength(255),

                    ColorPicker::make('primary_color')
                        ->label('Primary Color'),

                    ColorPicker::make('secondary_color')
                        ->label('Secondary Color'),

                    FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('club-logos')
                        ->visibility('public')
                        ->helperText('Upload the club logo.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
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

                TextColumn::make('league.name')
                    ->label('League')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('conference.name')
                    ->label('Conference')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('city')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('state')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated'),
            ])
            ->filters([
                SelectFilter::make('league_id')
                    ->label('League')
                    ->relationship('league', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('conference_id')
                    ->label('Conference')
                    ->relationship('conference', 'name')
                    ->searchable()
                    ->preload(),

                TrashedFilter::make(),
            ])
            ->recordUrl(fn (Club $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubs::route('/'),
            'create' => CreateClub::route('/create'),
            'view' => ViewClub::route('/{record}'),
            'edit' => EditClub::route('/{record}/edit'),
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