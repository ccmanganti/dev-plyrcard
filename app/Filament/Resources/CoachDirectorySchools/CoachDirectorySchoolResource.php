<?php

namespace App\Filament\Resources\CoachDirectorySchools;

use App\Filament\Clusters\CoachDatabaseManagement;
use App\Filament\Resources\CoachDirectorySchools\Pages\CreateCoachDirectorySchool;
use App\Filament\Resources\CoachDirectorySchools\Pages\EditCoachDirectorySchool;
use App\Filament\Resources\CoachDirectorySchools\Pages\ListCoachDirectorySchools;
use App\Models\School;
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

class CoachDirectorySchoolResource extends Resource
{
    protected static ?string $model = School::class;
    protected static ?string $cluster = CoachDatabaseManagement::class;
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedAcademicCap;
    protected static string | BackedEnum | null $activeNavigationIcon = Heroicon::AcademicCap;
    protected static ?string $navigationLabel = 'Schools';
    protected static ?string $modelLabel = 'School';
    protected static ?string $pluralModelLabel = 'Schools';
    protected static ?string $slug = 'schools';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('School')
                ->columns(2)
                ->schema([
                    FileUpload::make('logo_path')
                        ->label('Logo')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('school-logos')
                        ->visibility('public')
                        ->columnSpanFull(),
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('website_url')->url()->maxLength(255),
                    TextInput::make('street')->maxLength(255)->columnSpanFull(),
                    TextInput::make('city')->maxLength(255),
                    TextInput::make('state')->maxLength(255),
                    TextInput::make('zipcode')->maxLength(50),
                    TextInput::make('ghl_business_id')
                        ->label('GHL business ID')
                        ->helperText('Resolved by school name when email synchronization is added.')
                        ->maxLength(255),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')->label('Logo')->disk('public')->square(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('coaches_count')->counts('coaches')->label('Coaches')->sortable(),
                TextColumn::make('city')->searchable()->toggleable(),
                TextColumn::make('state')->searchable()->toggleable(),
                TextColumn::make('ghl_business_id')->label('GHL business')->placeholder('Not mapped')->toggleable(),
                TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (School $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoachDirectorySchools::route('/'),
            'create' => CreateCoachDirectorySchool::route('/create'),
            'edit' => EditCoachDirectorySchool::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
