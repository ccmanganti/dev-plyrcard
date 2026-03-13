<?php

namespace App\Filament\Resources\Websites\RelationManagers;

use App\Models\HeroTemplateField;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class HeroFieldValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'heroFieldValues';

    protected static ?string $title = 'Hero Content Fields';

    protected function getTemplateFieldType(?int $fieldId): ?string
    {
        if (! $fieldId) {
            return null;
        }

        return HeroTemplateField::query()
            ->whereKey($fieldId)
            ->value('type');
    }

    protected function getTemplateFieldGuideImage(?int $fieldId): ?string
    {
        if (! $fieldId) {
            return null;
        }

        return HeroTemplateField::query()
            ->whereKey($fieldId)
            ->value('guide_image');
    }

    protected function getAvailableTemplateFieldOptions(?int $currentFieldId = null): array
    {
        $website = $this->getOwnerRecord();

        $usedFieldIds = $website->heroFieldValues()
            ->when(
                $currentFieldId,
                fn ($query) => $query->where('hero_template_field_id', '!=', $currentFieldId)
            )
            ->pluck('hero_template_field_id')
            ->all();

        return HeroTemplateField::query()
            ->where('hero_template_id', $website->hero_template_id)
            ->when(
                ! empty($usedFieldIds),
                fn ($query) => $query->whereNotIn('id', $usedFieldIds)
            )
            ->orderBy('sort_order')
            ->pluck('label', 'id')
            ->all();
    }

    protected function getProxyFieldName(?string $type): string
    {
        return match ($type) {
            'text' => 'value_text',
            'url' => 'value_url',
            'color' => 'value_color',
            'image' => 'value_image',
            'richtext' => 'value_richtext',
            'textarea', 'embed', 'json' => 'value_textarea',
            default => 'value_text',
        };
    }

    protected function fillProxyFields(array $data): array
    {
        $type = $this->getTemplateFieldType($data['hero_template_field_id'] ?? null);
        $proxyField = $this->getProxyFieldName($type);
        $value = $data['value'] ?? null;

        $data['value_text'] = null;
        $data['value_url'] = null;
        $data['value_color'] = null;
        $data['value_image'] = null;
        $data['value_richtext'] = null;
        $data['value_textarea'] = null;

        $data[$proxyField] = $value;

        return $data;
    }

    protected function collapseProxyFields(array $data): array
    {
        $type = $this->getTemplateFieldType($data['hero_template_field_id'] ?? null);
        $proxyField = $this->getProxyFieldName($type);
        $value = $data[$proxyField] ?? null;

        if ($type === 'image') {
            if (is_array($value)) {
                $value = count($value) ? reset($value) : null;
            }

            $data['value'] = filled($value) ? (string) $value : null;
        } else {
            $data['value'] = $value;
        }

        unset(
            $data['value_text'],
            $data['value_url'],
            $data['value_color'],
            $data['value_image'],
            $data['value_richtext'],
            $data['value_textarea'],
        );

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Select::make('hero_template_field_id')
                        ->label('Hero Field')
                        ->options(fn ($record) => $this->getAvailableTemplateFieldOptions($record?->hero_template_field_id))
                        ->searchable()
                        ->required()
                        ->live(),

                    Placeholder::make('hero_field_guide')
                        ->label('Field Location Guide')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => filled($this->getTemplateFieldGuideImage($get('hero_template_field_id'))))
                        ->content(function (Get $get) {
                            $path = $this->getTemplateFieldGuideImage($get('hero_template_field_id'));

                            if (! $path) {
                                return new HtmlString('');
                            }

                            $url = Storage::disk('public')->url($path);

                            return new HtmlString(
                                '<div class="space-y-2">' .
                                    '<p class="text-sm text-gray-500">This shows where the selected hero field appears in the template.</p>' .
                                    '<img src="' . e($url) . '" alt="Hero field location guide" class="max-h-[320px] w-auto rounded-xl border border-gray-200 shadow-sm" />' .
                                '</div>'
                            );
                        }),

                    TextInput::make('value_text')
                        ->label('Value')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('hero_template_field_id')) === 'text'),

                    TextInput::make('value_url')
                        ->label('URL')
                        ->url()
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('hero_template_field_id')) === 'url'),

                    ColorPicker::make('value_color')
                        ->label('Color')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('hero_template_field_id')) === 'color'),

                    FileUpload::make('value_image')
                        ->label('Image')
                        ->image()
                        ->disk('public')
                        ->directory('hero-field-images')
                        ->visibility('public')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('hero_template_field_id')) === 'image'),

                    RichEditor::make('value_richtext')
                        ->label('Value')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('hero_template_field_id')) === 'richtext')
                        ->formatStateUsing(function ($state) {
                            if (blank($state)) {
                                return '';
                            }

                            return is_string($state) ? $state : '';
                        }),

                    Textarea::make('value_textarea')
                        ->label('Value')
                        ->rows(6)
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => in_array(
                            $this->getTemplateFieldType($get('hero_template_field_id')),
                            ['textarea', 'embed', 'json'],
                            true
                        )),

                    KeyValue::make('meta')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('templateField'))
            ->columns([
                TextColumn::make('templateField.label')
                    ->label('Field')
                    ->searchable(),

                TextColumn::make('templateField.type')
                    ->label('Type')
                    ->badge(),

                ImageColumn::make('templateField.guide_image')
                    ->label('Guide')
                    ->disk('public')
                    ->square(),

                ImageColumn::make('value')
                    ->label('Preview')
                    ->disk('public')
                    ->square()
                    ->visible(fn ($record = null) => $record?->templateField?->type === 'image'),

                TextColumn::make('value')
                    ->limit(60)
                    ->wrap()
                    ->visible(fn ($record = null) => $record?->templateField?->type !== 'image'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->collapseProxyFields($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        return $this->fillProxyFields($data);
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->collapseProxyFields($data);
                    }),

                DeleteAction::make(),
            ]);
    }
}