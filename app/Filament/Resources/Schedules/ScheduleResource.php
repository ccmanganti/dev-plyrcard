<?php

namespace App\Filament\Resources\Schedules;

use App\Filament\Resources\Schedules\Pages\CreateSchedule;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Models\Schedule;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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
    protected static ?string $navigationLabel = 'Schedules';
    protected static ?string $modelLabel = 'Schedule';
    protected static ?string $pluralModelLabel = 'Schedules';
    protected static ?string $recordTitleAttribute = 'title';

    protected static function isSuperadmin(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Superadmin')
            || $user->hasRole('superadmin')
            || $user->hasRole('Super Admin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Schedule Details')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->description('Add the game or event details below.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Hidden::make('created_by_user_id')
                        ->default(fn () => auth()->id())
                        ->dehydrated(true),

                    TextInput::make('title')
                        ->label('Title')
                        ->prefixIcon(Heroicon::OutlinedPencilSquare)
                        ->maxLength(255)
                        ->placeholder('League Match, Tournament Game, Playoff Game')
                        ->helperText('Optional label for the schedule entry.'),

                    TextInput::make('opponent')
                        ->label('Opponent')
                        ->prefixIcon(Heroicon::OutlinedShieldCheck)
                        ->maxLength(255)
                        ->placeholder('Team / School / Club Name')
                        ->required()
                        ->helperText('Enter the opponent or event name.'),

                    Select::make('status')
                        ->label('Status')
                        ->prefixIcon(Heroicon::OutlinedFlag)
                        ->options([
                            'upcoming' => 'Upcoming',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            'postponed' => 'Postponed',
                        ])
                        ->default('upcoming')
                        ->required()
                        ->helperText('Set the current state of this schedule.'),

                    Toggle::make('is_home')
                        ->label('Home Game')
                        ->helperText('Turn on if this is a home game.')
                        ->default(false),

                    DatePicker::make('game_date')
                        ->label('Game Date')
                        ->native(false)
                        ->prefixIcon(Heroicon::OutlinedCalendar)
                        ->required()
                        ->helperText('Choose the scheduled date.'),

                    TimePicker::make('game_time')
                        ->label('Game Time')
                        ->seconds(false)
                        ->native(false)
                        ->prefixIcon(Heroicon::OutlinedClock)
                        ->helperText('Leave blank if the time is still to be confirmed.'),

                    TextInput::make('location')
                        ->label('Location')
                        ->prefixIcon(Heroicon::OutlinedMapPin)
                        ->maxLength(255)
                        ->placeholder('City, State')
                        ->helperText('General location of the event.'),

                    TextInput::make('venue')
                        ->label('Venue')
                        ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                        ->maxLength(255)
                        ->placeholder('Gym / Field / Stadium / Court')
                        ->helperText('Specific venue or facility name.'),

                    TextInput::make('result')
                        ->label('Result')
                        ->prefixIcon(Heroicon::OutlinedTrophy)
                        ->maxLength(255)
                        ->placeholder('W 3-1 / L 58-61')
                        ->helperText('Optional final result summary.'),

                    TextInput::make('score')
                        ->label('Score')
                        ->prefixIcon(Heroicon::OutlinedHashtag)
                        ->maxLength(255)
                        ->placeholder('3-1 / 58-61')
                        ->helperText('Optional score value.'),

                    Select::make('users')
                        ->label('Assigned Users')
                        ->relationship(
                            name: 'users',
                            titleAttribute: 'first_name',
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->orderBy('first_name')
                                ->orderBy('last_name')
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (User $record) => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? ''))
                        )
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->visible(fn () => static::isSuperadmin())
                        ->required(fn () => static::isSuperadmin())
                        ->dehydrated(false)
                        ->saveRelationshipsUsing(function (Select $component, ?array $state) {
                            $record = $component->getRecord();

                            if (! $record || ! auth()->check()) {
                                return;
                            }

                            if (static::isSuperadmin()) {
                                $record->users()->sync($state ?? []);
                                return;
                            }

                            $record->users()->syncWithoutDetaching([auth()->id()]);
                        })
                        ->helperText('Superadmin can assign this schedule to one or more users.')
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->label('Notes')
                        ->placeholder('Add any important schedule notes here...')
                        ->helperText('Optional extra details for this event.')
                        ->rows(5)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['creator', 'users']);

                if (! static::isSuperadmin()) {
                    $query->where('created_by_user_id', auth()->id());
                }
            })
            ->defaultSort('game_date', 'asc')
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('opponent')
                    ->label('Opponent')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('game_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('game_time')
                    ->label('Time')
                    ->time('g:i A')
                    ->sortable()
                    ->placeholder('TBD'),

                IconColumn::make('is_home')
                    ->label('Home')
                    ->boolean(),

                TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('venue')
                    ->label('Venue')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'upcoming' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'postponed' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('users_list')
                    ->label('User')
                    ->state(function (Schedule $record): string {
                        $names = $record->users
                            ->map(fn ($user) => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')))
                            ->filter()
                            ->values()
                            ->all();

                        return empty($names) ? '—' : implode(', ', $names);
                    })
                    ->visible(static::isSuperadmin()),

                TextColumn::make('result')
                    ->label('Result')
                    ->placeholder('—'),

                TextColumn::make('score')
                    ->label('Score')
                    ->placeholder('—')
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
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'upcoming' => 'Upcoming',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'postponed' => 'Postponed',
                    ]),

                SelectFilter::make('created_by_user_id')
                    ->label('Created By')
                    ->relationship('creator', 'first_name')
                    ->getOptionLabelFromRecordUsing(
                        fn (User $record) => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? ''))
                    )
                    ->searchable()
                    ->preload()
                    ->visible(fn () => static::isSuperadmin()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(function (Schedule $record): bool {
                        if (static::isSuperadmin()) {
                            return true;
                        }

                        return (int) $record->created_by_user_id === (int) auth()->id();
                    }),

                DeleteAction::make()
                    ->label('Delete')
                    ->visible(fn (Schedule $record): bool => static::isSuperadmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->visible(fn () => static::isSuperadmin()),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['creator', 'users']);

        if (! static::isSuperadmin()) {
            $query->where('created_by_user_id', auth()->id());
        }

        return $query;
    }

    public static function canEdit(Model $record): bool
    {
        if (static::isSuperadmin()) {
            return true;
        }

        return (int) $record->created_by_user_id === (int) auth()->id();
    }

    public static function canDelete(Model $record): bool
    {
        return static::isSuperadmin();
    }

    public static function canDeleteAny(): bool
    {
        return static::isSuperadmin();
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