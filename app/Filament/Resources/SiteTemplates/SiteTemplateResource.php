<?php

namespace App\Filament\Resources\SiteTemplates;

use App\Filament\Resources\SiteTemplates\Pages\CreateSiteTemplate;
use App\Filament\Resources\SiteTemplates\Pages\EditSiteTemplate;
use App\Filament\Resources\SiteTemplates\Pages\ListSiteTemplates;
use App\Filament\Resources\SiteTemplates\Pages\ViewSiteTemplate;
use App\Filament\Resources\SiteTemplates\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\SiteTemplate;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SiteTemplateResource extends Resource
{
    protected static ?string $model = SiteTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::RectangleGroup;
    protected static string|UnitEnum|null $navigationGroup = 'Website Builder';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Template')
                ->columns(2)
                ->schema([

                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('blade_view')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Example: templates.sites.template-one'),

                    FileUpload::make('preview_image')
                        ->image()
                        ->disk('public')
                        ->directory('site-template-previews')
                        ->visibility('public'),

                    Toggle::make('is_active')
                        ->default(true),

                    CheckboxList::make('sports')
                        ->label('Allowed Sports')
                        ->options(UserResource::getSportOptions())
                        ->columns(2)
                        ->searchable()
                        ->helperText('Leave empty to allow this template for all sports.')
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->rows(4)
                        ->columnSpanFull(),

                    KeyValue::make('settings')
                        ->keyLabel('Setting')
                        ->valueLabel('Value')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('blade_view')->toggleable(),

                TextColumn::make('sports')
                    ->label('Sports')
                    ->state(function (SiteTemplate $record): array {

                        if (blank($record->sports)) {
                            return ['All Sports'];
                        }

                        return collect($record->sports)
                            ->map(function (string $sport): string {
                                return UserResource::getSportOptions()[$sport]
                                    ?? str($sport)->replace('_', ' ')->title()->toString();
                            })
                            ->values()
                            ->all();
                    })
                    ->badge(),

                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (SiteTemplate $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteTemplates::route('/'),
            'create' => CreateSiteTemplate::route('/create'),
            'view' => ViewSiteTemplate::route('/{record}'),
            'edit' => EditSiteTemplate::route('/{record}/edit'),
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