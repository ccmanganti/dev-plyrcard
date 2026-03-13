<?php

namespace App\Filament\Resources\Websites\RelationManagers;

use App\Models\SiteTemplateField;
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

class FieldValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'fieldValues';

    protected static ?string $title = 'Website Content Fields';

    protected function getTemplateFieldType(?int $fieldId): ?string
    {
        if (! $fieldId) {
            return null;
        }

        return SiteTemplateField::query()
            ->whereKey($fieldId)
            ->value('type');
    }

    protected function getTemplateFieldGuideImage(?int $fieldId): ?string
    {
        if (! $fieldId) {
            return null;
        }

        return SiteTemplateField::query()
            ->whereKey($fieldId)
            ->value('guide_image');
    }

    protected function getAvailableTemplateFieldOptions(?int $currentFieldId = null): array
    {
        $website = $this->getOwnerRecord();

        $usedFieldIds = $website->fieldValues()
            ->when(
                $currentFieldId,
                fn ($query) => $query->where('site_template_field_id', '!=', $currentFieldId)
            )
            ->pluck('site_template_field_id')
            ->all();

        return SiteTemplateField::query()
            ->where('site_template_id', $website->site_template_id)
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
        $type = $this->getTemplateFieldType($data['site_template_field_id'] ?? null);
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
        $type = $this->getTemplateFieldType($data['site_template_field_id'] ?? null);
        $proxyField = $this->getProxyFieldName($type);

        $data['value'] = $data[$proxyField] ?? null;

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
                    Select::make('site_template_field_id')
                        ->label('Template Field')
                        ->options(fn ($record) => $this->getAvailableTemplateFieldOptions($record?->site_template_field_id))
                        ->searchable()
                        ->required()
                        ->live(),

                    Placeholder::make('template_field_guide')
                        ->label('Field Location Guide')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => filled($this->getTemplateFieldGuideImage($get('site_template_field_id'))))
                        ->content(function (Get $get) {
                            $path = $this->getTemplateFieldGuideImage($get('site_template_field_id'));

                            if (! $path) {
                                return new HtmlString('');
                            }

                            $url = Storage::disk('public')->url($path);

                            return new HtmlString(
                                '<div class="space-y-2">' .
                                    '<p class="text-sm text-gray-500">This shows where the selected field appears in the template.</p>' .
                                    '<img src="' . e($url) . '" alt="Field location guide" class="max-h-[320px] w-auto rounded-xl border border-gray-200 shadow-sm" />' .
                                '</div>'
                            );
                        }),

                    TextInput::make('value_text')
                        ->label('Value')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('site_template_field_id')) === 'text')
                        ->dehydrated(false),

                    TextInput::make('value_url')
                        ->label('URL')
                        ->url()
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('site_template_field_id')) === 'url')
                        ->dehydrated(false),

                    ColorPicker::make('value_color')
                        ->label('Color')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('site_template_field_id')) === 'color')
                        ->dehydrated(false),

                    FileUpload::make('value_image')
                        ->label('Image')
                        ->image()
                        ->disk('public')
                        ->directory('website-field-images')
                        ->visibility('public')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('site_template_field_id')) === 'image')
                        ->dehydrated(false),

                    RichEditor::make('value_richtext')
                        ->label('Value')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $this->getTemplateFieldType($get('site_template_field_id')) === 'richtext')
                        ->dehydrated(false)
                        ->formatStateUsing(function ($state) {
                            if (blank($state)) {
                                return '';
                            }

                            if (is_array($state)) {
                                return '';
                            }

                            return (string) $state;
                        }),

                    Textarea::make('value_textarea')
                        ->label('Value')
                        ->rows(6)
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => in_array(
                            $this->getTemplateFieldType($get('site_template_field_id')),
                            ['textarea', 'embed', 'json'],
                            true
                        ))
                        ->dehydrated(false),

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