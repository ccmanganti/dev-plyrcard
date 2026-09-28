<?php

namespace App\Filament\Resources\Coaches;

use App\Filament\Clusters\CoachDatabaseManagement;
use App\Filament\Resources\Coaches\Pages\CreateCoach;
use App\Filament\Resources\Coaches\Pages\EditCoach;
use App\Filament\Resources\Coaches\Pages\ImportCoaches;
use App\Filament\Resources\Coaches\Pages\ListCoaches;
use App\Filament\Resources\Coaches\Pages\ManageCoachExclusivity;
use App\Models\Coach;
use App\Services\SportAvailabilityService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CoachResource extends Resource
{
    protected static ?string $model = Coach::class;
    protected static ?string $cluster = CoachDatabaseManagement::class;
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string | BackedEnum | null $activeNavigationIcon = Heroicon::Users;
    protected static ?string $navigationLabel = 'Coaches';
    protected static ?string $modelLabel = 'Coach';
    protected static ?string $pluralModelLabel = 'Coaches';
    protected static ?string $recordTitleAttribute = 'display_name';
    protected static ?int $navigationSort = 1;

    public static function allSportOptions(): array
    {
        return SportAvailabilityService::COACH_OPTIONS;
    }

    public static function sportOptions(?string $includeCurrent = null): array
    {
        return app(SportAvailabilityService::class)->coachOptions($includeCurrent);
    }

    public static function genderOptions(): array
    {
        return Coach::genderOptions();
    }

    public static function divisionOptions(): array
    {
        return [
            'NCAA Division I' => 'NCAA Division I', 'NCAA Division II' => 'NCAA Division II',
            'NCAA Division III' => 'NCAA Division III', 'NAIA' => 'NAIA',
            'NJCAA Division I' => 'NJCAA Division I', 'NJCAA Division II' => 'NJCAA Division II',
            'NJCAA Division III' => 'NJCAA Division III', 'CCCAA' => 'CCCAA',
            'USCAA' => 'USCAA', 'NCCAA' => 'NCCAA', 'Other' => 'Other',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Coach identity')
                ->columns(3)
                ->schema([
                    TextInput::make('first_name')->required()->maxLength(255)->live(onBlur: true),
                    TextInput::make('last_name')->required()->maxLength(255)->live(onBlur: true),
                    TextInput::make('display_name')->label('Display name')->disabled()->dehydrated(false)
                        ->helperText('Automatically generated from First Name + Last Name.'),
                    TextInput::make('email')->email()->unique(ignoreRecord: true)->maxLength(255),
                    TextInput::make('secondary_email')->email()->maxLength(255),
                    TextInput::make('phone')->tel()->maxLength(50),
                    TextInput::make('title')->maxLength(255),
                    Select::make('sport')->options(fn (?Coach $record): array => static::sportOptions($record?->sport))->searchable()->required(),
                    Select::make('gender')
                        ->options(static::genderOptions())
                        ->native(false)
                        ->required()
                        ->helperText('Controls which athletes can see this coach.'),
                    Select::make('school_id')
                        ->relationship('school', 'name')->searchable()->preload()
                        ->createOptionForm([
                            TextInput::make('name')->required()->maxLength(255),
                            TextInput::make('city')->maxLength(255),
                            TextInput::make('state')->maxLength(255),
                        ]),
                    Select::make('division')->options(static::divisionOptions())->searchable()->native(false),
                    TextInput::make('conference')->maxLength(255),
                    Toggle::make('is_active')->default(true),
                ]),

            Section::make('Data quality')->columns(3)->collapsed()->schema([
                TextInput::make('verification_status')->maxLength(100),
                TextInput::make('confidence_level')->maxLength(100),
                Textarea::make('audit_notes')->rows(3)->columnSpanFull(),
            ]),

            Section::make('Location and links')->columns(3)->collapsed()->schema([
                TextInput::make('city')->maxLength(255), TextInput::make('state')->maxLength(255),
                TextInput::make('country')->default('United States')->maxLength(255),
                TextInput::make('website_url')->url()->maxLength(255)->columnSpanFull(),
            ]),

            Section::make('Internal notes')->collapsed()->schema([
                Textarea::make('notes')->rows(5)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label('Coach')->searchable(['display_name', 'first_name', 'last_name'])->sortable(),
                TextColumn::make('school.name')->label('School')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->toggleable(),
                TextColumn::make('sport')->formatStateUsing(fn (?string $state): string => static::allSportOptions()[$state] ?? str($state)->headline()->toString())->badge()->sortable(),
                TextColumn::make('gender')->formatStateUsing(fn (?string $state): string => static::genderOptions()[$state] ?? 'Unassigned')->badge()->sortable(),
                TextColumn::make('division')->searchable()->sortable()->toggleable(),
                TextColumn::make('conference')->searchable()->sortable()->toggleable(),
                TextColumn::make('email')->searchable()->copyable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('verification_status')->label('Verified')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ghl_sync_status')->label('GHL sync')->badge()->placeholder('Pending')->toggleable(),
                TextColumn::make('updated_at')->since()->label('Updated')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('sport')->options(static::allSportOptions()),
                SelectFilter::make('gender')->options(static::genderOptions()),
                SelectFilter::make('division')->options(static::divisionOptions()),
                SelectFilter::make('school_id')->relationship('school', 'name')->searchable()->preload(),
                TernaryFilter::make('is_active')->label('Active'), TrashedFilter::make(),
            ])
            ->defaultSort('last_name')
            ->recordUrl(fn (Coach $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoaches::route('/'),
            'import' => ImportCoaches::route('/import'),
            'exclusivity' => ManageCoachExclusivity::route('/exclusivity'),
            'create' => CreateCoach::route('/create'),
            'edit' => EditCoach::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}