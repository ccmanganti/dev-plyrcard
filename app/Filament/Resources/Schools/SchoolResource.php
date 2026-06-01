<?php

namespace App\Filament\Resources\Schools;

use App\Filament\Clusters\Organizations;
use App\Filament\Resources\Schools\Pages\CreateSchool;
use App\Filament\Resources\Schools\Pages\EditSchool;
use App\Filament\Resources\Schools\Pages\ListSchools;
use App\Filament\Resources\Schools\Pages\ViewSchool;
use App\Models\School;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SchoolResource extends Resource
{
    protected static ?string $model = School::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::AcademicCap;
    protected static ?string $cluster = Organizations::class;
    protected static ?int $navigationSort = 4;
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
            Section::make('School')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('zipcode')->maxLength(255),
                    TextInput::make('state')->maxLength(255),
                    TextInput::make('city')->maxLength(255),
                    TextInput::make('street')->maxLength(255)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('city')->searchable()->toggleable(),
                TextColumn::make('state')->searchable()->toggleable(),
                TextColumn::make('zipcode')->toggleable(),
                TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (School $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchools::route('/'),
            'create' => CreateSchool::route('/create'),
            'view' => ViewSchool::route('/{record}'),
            'edit' => EditSchool::route('/{record}/edit'),
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