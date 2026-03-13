<?php

namespace App\Filament\Resources\SiteTemplates\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;

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
                        ->helperText('Internal key, e.g. aboutme_headline')
                        ->rules([
                            fn ($record) => Rule::unique('site_template_fields', 'name')
                                ->where(function ($query) {
                                    return $query
                                        ->where('site_template_id', $this->getOwnerRecord()->getKey())
                                        ->whereNull('deleted_at');
                                })
                                ->ignore($record?->id),
                        ]),

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
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]))
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
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, string $model) {
                        $existing = $model::withTrashed()
                            ->where('site_template_id', $this->getOwnerRecord()->getKey())
                            ->where('name', $data['name'])
                            ->first();

                        if ($existing) {
                            $existing->fill($data);
                            $existing->site_template_id = $this->getOwnerRecord()->getKey();
                            $existing->deleted_at = null;
                            $existing->save();

                            return $existing;
                        }

                        $data['site_template_id'] = $this->getOwnerRecord()->getKey();

                        return $model::create($data);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ]);
    }
}