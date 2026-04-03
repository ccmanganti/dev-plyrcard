<?php

namespace App\Filament\Resources\Schedules;

use App\Filament\Resources\Schedules\Pages\CreateSchedule;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Models\Schedule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    protected static string|UnitEnum|null $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Schedules';
    protected static ?string $modelLabel = 'Schedule';
    protected static ?string $pluralModelLabel = 'Schedules';
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Game Details')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->maxLength(255)
                        ->placeholder('League Match, Tournament Game, Playoff Game'),

                    TextInput::make('opponent')
                        ->maxLength(255)
                        ->placeholder('Team / School / Club Name')
                        ->required(),

                    Select::make('status')
                        ->options([
                            'upcoming' => 'Upcoming',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            'postponed' => 'Postponed',
                        ])
                        ->default('upcoming')
                        ->required(),

                    Toggle::make('is_home')
                        ->label('Home Game'),

                    DatePicker::make('game_date')
                        ->required(),

                    TimePicker::make('game_time')
                        ->seconds(false),

                    TextInput::make('location')
                        ->maxLength(255)
                        ->placeholder('City, State'),

                    TextInput::make('venue')
                        ->maxLength(255)
                        ->placeholder('Gym / Field / Stadium / Court'),

                    TextInput::make('result')
                        ->maxLength(255)
                        ->placeholder('W 3-1 / L 58-61'),

                    TextInput::make('score')
                        ->maxLength(255)
                        ->placeholder('3-1 / 58-61'),

                    Textarea::make('notes')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['creator', 'users']);

                if (! auth()->user()?->hasRole('superadmin')) {
                    $query->whereHas('users', function (Builder $q) {
                        $q->where('users.id', auth()->id());
                    });
                }
            })
            ->defaultSort('game_date', 'asc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('opponent')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('game_date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('game_time')
                    ->time('g:i A')
                    ->sortable(),

                IconColumn::make('is_home')
                    ->label('Home')
                    ->boolean(),

                TextColumn::make('location')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('venue')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'upcoming' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'postponed' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('result')
                    ->toggleable(),

                TextColumn::make('score')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('creator_name')
                    ->label('Created By')
                    ->state(fn (Schedule $record) => trim(
                        ($record->creator?->first_name ?? '') . ' ' . ($record->creator?->last_name ?? '')
                    ))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'upcoming' => 'Upcoming',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'postponed' => 'Postponed',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => auth()->user()?->hasRole('superadmin')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['creator', 'users']);

        if (! auth()->user()?->hasRole('superadmin')) {
            $query->whereHas('users', function (Builder $q) {
                $q->where('users.id', auth()->id());
            });
        }

        return $query;
    }

    public static function mutateScheduleDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    public static function mutateScheduleDataBeforeSave(array $data): array
    {
        return $data;
    }

    public static function afterCreate(Model $record): void
    {
        if (auth()->check() && ! $record->users()->where('users.id', auth()->id())->exists()) {
            $record->users()->attach(auth()->id());
        }
    }

    public static function getRedirectAfterSaveUrl(): string
    {
        return static::getUrl('index');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchedules::route('/'),
            'create' => CreateSchedule::route('/create'),
            'edit' => EditSchedule::route('/{record}/edit'),
        ];
    }
}