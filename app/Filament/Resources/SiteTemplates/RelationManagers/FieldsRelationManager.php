<?php

namespace App\Filament\Resources\SiteTemplates\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Template Fields';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Internal key, e.g. aboutme_headline'),

                    TextInput::make('label')
                        ->required()
                        ->maxLength(255),

                    Select::make('type')
                        ->required()
                        ->options([
                            'text' => 'Text',
                            'textarea' => 'Textarea',
                            'richtext' => 'Rich Text',
                            'url' => 'URL',
                            'image' => 'Image',
                            'color' => 'Color',
                            'embed' => 'Embed',
                            'json' => 'JSON',
                        ]),

                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_required')
                        ->default(false),

                    FileUpload::make('guide_image')
                        ->label('Guide Image / GIF')
                        ->image()
                        ->disk('public')
                        ->directory('site-template-field-guides')
                        ->visibility('public')
                        ->columnSpanFull()
                        ->helperText('Upload an image or GIF showing where this field appears on the template.'),

                    KeyValue::make('options')
                        ->columnSpanFull(),

                    KeyValue::make('default_value')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('type')->badge(),
                IconColumn::make('is_required')->boolean(),
                ImageColumn::make('guide_image')
                    ->label('Guide')
                    ->disk('public')
                    ->square(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}