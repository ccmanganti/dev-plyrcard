<?php

namespace App\Filament\Resources\Websites;

use App\Filament\Clusters\Websites;
use App\Filament\Resources\Websites\Pages\CreateWebsite;
use App\Filament\Resources\Websites\Pages\EditWebsite;
use App\Filament\Resources\Websites\Pages\ListWebsites;
use App\Filament\Resources\Websites\Pages\ViewWebsite;
use App\Filament\Resources\Websites\RelationManagers\FieldValuesRelationManager;
use App\Filament\Resources\Websites\RelationManagers\HeroFieldValuesRelationManager;
use App\Models\HeroTemplate;
use App\Models\SiteTemplate;
use App\Models\User;
use App\Models\Website;
use App\Services\WebsitePublishedEmailService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use UnitEnum;

class WebsiteResource extends Resource
{
    protected static ?string $model = Website::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::GlobeAlt;
    protected static ?string $cluster = Websites::class;
    protected static ?int $navigationSort = 1;
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
            Tabs::make('Website')
                ->tabs([
                    Tabs\Tab::make('General')
                        ->schema([
                            Section::make('Owner & Structure')
                                ->columns(2)
                                ->schema([
                                    Select::make('user_id')
                                        ->label('Player')
                                        ->relationship('user', 'first_name')
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->first_name} {$record->last_name}")
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set) {
                                            $set('site_template_id', null);
                                            $set('hero_template_id', null);
                                        })
                                        ->required(),

                                    TextInput::make('name')
                                        ->maxLength(255)
                                        ->helperText('Internal website name'),

                                    TextInput::make('domain')
                                        ->label('Domain')
                                        ->placeholder('playerdomain.com')
                                        ->maxLength(255)
                                        ->unique(ignoreRecord: true)
                                        ->helperText('Use the custom domain assigned to this website. Do not include https:// unless needed.'),

                                    Select::make('site_template_id')
                                        ->label('Site Template')
                                        ->relationship(
                                            name: 'siteTemplate',
                                            titleAttribute: 'name',
                                            modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                                $userId = $get('user_id');

                                                $query->where('is_active', true);

                                                if (! filled($userId)) {
                                                    return $query->whereRaw('1 = 0');
                                                }

                                                $userSport = User::query()
                                                    ->whereKey($userId)
                                                    ->value('sport');

                                                if (blank($userSport)) {
                                                    return $query->where(function (Builder $subQuery) {
                                                        $subQuery
                                                            ->whereNull('sports')
                                                            ->orWhereJsonLength('sports', 0);
                                                    });
                                                }

                                                return $query->where(function (Builder $subQuery) use ($userSport) {
                                                    $subQuery
                                                        ->whereNull('sports')
                                                        ->orWhereJsonLength('sports', 0)
                                                        ->orWhereJsonContains('sports', $userSport);
                                                });
                                            }
                                        )
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->disabled(fn (Get $get): bool => blank($get('user_id')))
                                        ->helperText('Only templates allowed for the selected player’s sport are shown.')
                                        ->afterStateUpdated(fn (Set $set) => $set('hero_template_id', null))
                                        ->required(),

                                    Select::make('hero_template_id')
                                        ->label('Hero Template')
                                        ->options(function (Get $get): array {
                                            $userId = $get('user_id');

                                            if (! filled($userId)) {
                                                return [];
                                            }

                                            $userSport = User::query()
                                                ->whereKey($userId)
                                                ->value('sport');

                                            $query = HeroTemplate::query()
                                                ->where('is_active', true);

                                            if (blank($userSport)) {
                                                $query->where(function ($q) {
                                                    $q->whereNull('sports')
                                                        ->orWhereJsonLength('sports', 0);
                                                });
                                            } else {
                                                $query->where(function ($q) use ($userSport) {
                                                    $q->whereNull('sports')
                                                        ->orWhereJsonLength('sports', 0)
                                                        ->orWhereJsonContains('sports', $userSport);
                                                });
                                            }

                                            return $query
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->disabled(fn (Get $get): bool => blank($get('user_id')))
                                        ->helperText('Hero templates are filtered by sport but not restricted by site template.')
                                        ->nullable(),

                                    Placeholder::make('site_template_preview')
                                        ->label('Site Template Preview')
                                        ->content(function (Get $get): HtmlString {
                                            $template = filled($get('site_template_id'))
                                                ? SiteTemplate::query()->find($get('site_template_id'))
                                                : null;

                                            return new HtmlString(static::renderTemplatePreview(
                                                title: $template?->name ?? 'Site Template Preview',
                                                imageUrl: static::resolvePreviewImageUrl($template),
                                                emptyMessage: 'Select a site template to preview it here.'
                                            ));
                                        }),

                                    Placeholder::make('hero_template_preview')
                                        ->label('Hero Template Preview')
                                        ->content(function (Get $get): HtmlString {
                                            $template = filled($get('hero_template_id'))
                                                ? HeroTemplate::query()->find($get('hero_template_id'))
                                                : null;

                                            return new HtmlString(static::renderTemplatePreview(
                                                title: $template?->name ?? 'Hero Template Preview',
                                                imageUrl: static::resolvePreviewImageUrl($template),
                                                emptyMessage: 'Select a hero template to preview it here.'
                                            ));
                                        }),

                                    Toggle::make('is_active')
                                        ->default(true),

                                    Toggle::make('is_published')
                                        ->default(false),
                                ]),
                        ]),

                    Tabs\Tab::make('Theme')
                        ->schema([
                            Section::make('Colors')
                                ->columns(3)
                                ->schema([
                                    ColorPicker::make('primary_color')->nullable(),
                                    ColorPicker::make('secondary_color')->nullable(),
                                    ColorPicker::make('accent_color')->nullable(),
                                    ColorPicker::make('background_color')->nullable(),
                                    ColorPicker::make('surface_color')->nullable(),
                                    ColorPicker::make('text_primary_color')->nullable(),
                                    ColorPicker::make('text_secondary_color')->nullable(),
                                ]),
                        ]),

                    Tabs\Tab::make('GHL Settings')
                        ->schema([
                            Section::make('GHL Credentials')
                                ->description('Admin-only connection values for this player website. These credentials can be used by backend automations, syncs, calendar pulls, and future GHL features.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('ghl_location_id')
                                        ->label('GHL Location ID')
                                        ->placeholder('vlsP1Bv6vsSN9OI8WALb')
                                        ->maxLength(255)
                                        ->helperText('Sub-account/location ID for this player. Used by the player site to dynamically pull the first active personal calendar when Calendar is selected.'),

                                    TextInput::make('ghl_api_token')
                                        ->label('GHL Private Integration Token')
                                        ->password()
                                        ->revealable()
                                        ->placeholder(fn (?Website $record): string => $record?->ghl_api_token ? 'Saved token hidden. Enter a new token to replace it.' : 'Paste private integration token')
                                        ->maxLength(2048)
                                        ->dehydrated(fn ($state): bool => filled($state))
                                        ->afterStateHydrated(function (TextInput $component): void {
                                            $component->state(null);
                                        })
                                        ->helperText('Encrypted on the website record. Leave blank to keep the existing saved token.'),
                                ]),

                            Section::make('Calendar / Form Embed Override')
                                ->description('Optional manual override. Leave blank to dynamically pull the first active personal GHL calendar from the Location ID and token above.')
                                ->schema([
                                    Textarea::make('ghl_calendar_embed_url')
                                        ->label('Embed Form Override')
                                        ->placeholder('<iframe src="https://systems.plyrcard.com/widget/booking/..." style="width:100%;border:none;overflow:hidden;" scrolling="no"></iframe>')
                                        ->rows(6)
                                        ->columnSpanFull()
                                        ->helperText('Optional. If filled, this exact embed or URL is used. If blank, the player site dynamically pulls the first active personal calendar from GHL.'),
                                ]),
                        ]),

                    Tabs\Tab::make('Website Settings')
                        ->schema([
                            Section::make('Article Section')
                                ->description('Controls what appears in the right-side article/contact section of the player website.')
                                ->columns(2)
                                ->schema([
                                    Select::make('article_section_type')
                                        ->label('Article Section Display')
                                        ->options([
                                            'follow_me' => 'Follow Me Form',
                                            'calendar' => 'Calendar',
                                        ])
                                        ->default('follow_me')
                                        ->native(false)
                                        ->helperText('My Journey player sites can show a GHL calendar. Other plans should keep Follow Me.'),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->default('Untitled Website'),

                TextColumn::make('user.first_name')
                    ->label('Player')
                    ->formatStateUsing(fn ($state, Website $record) => $record->user ? "{$record->user->first_name} {$record->user->last_name}" : null)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $subQuery) use ($search) {
                            $subQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('article_section_type')
                    ->label('Article Section')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'calendar' => 'Calendar',
                        'follow_me' => 'Follow Me',
                        default => 'Follow Me',
                    })
                    ->toggleable(),

                TextColumn::make('ghl_location_id')
                    ->label('GHL Location')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ghl_calendar_name')
                    ->label('Calendar')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('siteTemplate.name')->label('Site Template')->toggleable(),
                TextColumn::make('heroTemplate.name')->label('Hero Template')->toggleable(),
                IconColumn::make('is_active')->boolean(),
                ToggleColumn::make('is_published')
                    ->label('Website Published')
                    ->updateStateUsing(function (Website $record, bool $state): void {
                        $wasPublished = (bool) $record->is_published;

                        $record->update([
                            'is_published' => $state,
                        ]);

                        if ($state && ! $wasPublished) {
                            try {
                                $record->loadMissing('user');

                                if ($record->user) {
                                    app(WebsitePublishedEmailService::class)->send($record->user, $record);
                                }
                            } catch (\Throwable $exception) {
                                report($exception);
                            }
                        }
                    }),
                TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('preview_site')
                        ->label('Preview Site')
                        ->icon(Heroicon::OutlinedEye)
                        ->url(fn (Website $record): string => static::getWebsiteUrl($record) ?? route('website.preview', ['website' => $record]))
                        ->openUrlInNewTab(),

                    Action::make('view_website')
                        ->label('View Website')
                        ->icon(Heroicon::OutlinedGlobeAlt)
                        ->url(fn (Website $record): ?string => static::getWebsiteUrl($record))
                        ->openUrlInNewTab()
                        ->visible(fn (Website $record): bool => filled(static::getWebsiteUrl($record))),
                ]),
            ])
            ->recordUrl(fn (Website $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            FieldValuesRelationManager::class,
            HeroFieldValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsites::route('/'),
            'create' => CreateWebsite::route('/create'),
            'view' => ViewWebsite::route('/{record}'),
            'edit' => EditWebsite::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected static function renderTemplatePreview(string $title, ?string $imageUrl, string $emptyMessage): string
    {
        if (blank($imageUrl)) {
            return <<<HTML
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm font-medium text-gray-950 dark:text-white">{$title}</div>
                    <div class="mt-3 flex aspect-[16/10] items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        {$emptyMessage}
                    </div>
                </div>
            HTML;
        }

        return <<<HTML
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm font-medium text-gray-950 dark:text-white">{$title}</div>
                <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <img
                        src="{$imageUrl}"
                        alt="{$title}"
                        class="aspect-[16/10] w-full object-cover"
                    >
                </div>
            </div>
        HTML;
    }

    protected static function resolvePreviewImageUrl(?Model $template): ?string
    {
        if (! $template) {
            return null;
        }

        foreach ([
            'preview_image_url',
            'preview_image',
            'image_url',
            'image',
            'thumbnail_url',
            'thumbnail',
        ] as $field) {
            $value = data_get($template, $field);

            if (blank($value)) {
                continue;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            return Storage::url($value);
        }

        return null;
    }

    protected static function getWebsiteUrl(Website $record): ?string
    {
        $domain = trim((string) ($record->domain ?? ''));

        if (blank($domain)) {
            return null;
        }

        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $domain;
        }

        return 'https://' . ltrim($domain, '/');
    }
}