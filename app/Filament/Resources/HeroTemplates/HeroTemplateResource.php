<?php

namespace App\Filament\Resources\HeroTemplates;

use App\Filament\Resources\HeroTemplates\Pages\CreateHeroTemplate;
use App\Filament\Resources\HeroTemplates\Pages\EditHeroTemplate;
use App\Filament\Resources\HeroTemplates\Pages\ListHeroTemplates;
use App\Filament\Resources\HeroTemplates\Pages\ViewHeroTemplate;
use App\Filament\Resources\HeroTemplates\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\HeroTemplate;
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

class HeroTemplateResource extends Resource
{
    protected static ?string $model = HeroTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Photo;
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
                        ->helperText('Example: templates.heroes.hero-one'),

                    FileUpload::make('preview_image')
                        ->image()
                        ->disk('public')
                        ->directory('hero-template-previews')
                        ->visibility('public'),

                    Toggle::make('is_active')
                        ->default(true),

                    CheckboxList::make('sports')
                        ->label('Allowed Sports')
                        ->options(UserResource::getSportOptions())
                        ->columns(2)
                        ->searchable()
                        ->helperText('Leave empty to allow this hero template for all sports.')
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
                    ->state(function (HeroTemplate $record): array {

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
            ->recordUrl(fn (HeroTemplate $record): string => static::getUrl('edit', ['record' => $record]));
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
            'index' => ListHeroTemplates::route('/'),
            'create' => CreateHeroTemplate::route('/create'),
            'view' => ViewHeroTemplate::route('/{record}'),
            'edit' => EditHeroTemplate::route('/{record}/edit'),
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