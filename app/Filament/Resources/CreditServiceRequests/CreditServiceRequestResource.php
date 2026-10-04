<?php

namespace App\Filament\Resources\CreditServiceRequests;

use App\Filament\Resources\CreditServiceRequests\Pages\EditCreditServiceRequest;
use App\Filament\Resources\CreditServiceRequests\Pages\ListCreditServiceRequests;
use App\Models\CreditServiceRequest;
use App\Services\CreditPointService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class CreditServiceRequestResource extends Resource
{
    protected static ?string $model = CreditServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static ?string $navigationLabel = 'Credit Requests';
    protected static ?string $modelLabel = 'Credit Request';
    protected static ?string $pluralModelLabel = 'Credit Requests';
    protected static string|UnitEnum|null $navigationGroup = null;
    protected static ?string $navigationParentItem = null;
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'credit-service-requests';

    protected static function canManage(): bool
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
        return static::canManage();
    }

    public static function canViewAny(): bool
    {
        return static::canManage();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canManage()) {
            return null;
        }

        return (string) static::getModel()::query()
            ->whereIn('status', ['submitted', 'reviewed', 'in_progress'])
            ->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('credit_request_tabs')
                ->id('credit-request-tabs')
                ->persistTab()
                ->contained(true)
                ->tabs([
                    Tab::make('Request')
                        ->icon('heroicon-m-clipboard-document-list')
                        ->schema([
                            Section::make('Request Overview')
                                ->description('Review the service, update its status, and keep internal notes. Status changes never return credits automatically.')
                                ->columns(3)
                                ->schema([
                                    Placeholder::make('player_summary')
                                        ->label('Player')
                                        ->content(fn (?CreditServiceRequest $record): string => $record
                                            ? (trim(($record->user?->first_name ?? '') . ' ' . ($record->user?->last_name ?? '')) ?: ($record->user?->email ?? 'User #' . $record->user_id))
                                            : '-'),
                                    Placeholder::make('player_email')
                                        ->label('Player Email')
                                        ->content(fn (?CreditServiceRequest $record): string => $record ? (static::playerEmail($record) ?: 'No valid email') : '-'),
                                    Placeholder::make('current_balance')
                                        ->label('Current Credits')
                                        ->content(fn (?CreditServiceRequest $record): string => $record ? number_format((int) ($record->user?->points_available ?? 0)) . ' credits' : '-'),
                                    Placeholder::make('service')
                                        ->label('Service')
                                        ->content(fn (?CreditServiceRequest $record): string => $record ? $record->item_name . ' × ' . number_format((int) $record->quantity) : '-'),
                                    Placeholder::make('points')
                                        ->label('Credits Spent')
                                        ->content(fn (?CreditServiceRequest $record): string => $record ? number_format((int) $record->points_spent) . ' credits' : '-'),
                                    Placeholder::make('rush')
                                        ->label('Rush')
                                        ->content(fn (?CreditServiceRequest $record): string => $record?->modifier === 'rush' ? 'Yes — 48 hour turnaround' : 'No'),
                                    Select::make('status')
                                        ->label('Status')
                                        ->options(CreditServiceRequest::statusOptions())
                                        ->required(),
                                    Placeholder::make('managed_by')
                                        ->label('Last Managed By')
                                        ->content(fn (?CreditServiceRequest $record): string => $record
                                            ? (trim(($record->managedBy?->first_name ?? '') . ' ' . ($record->managedBy?->last_name ?? '')) ?: ($record->managedBy?->email ?? 'Not assigned'))
                                            : '-'),
                                    Placeholder::make('submitted_at')
                                        ->label('Submitted')
                                        ->content(fn (?CreditServiceRequest $record): string => optional($record?->created_at)->format('M j, Y g:i A') ?: '-'),
                                    Textarea::make('admin_notes')
                                        ->label('Internal Admin Notes')
                                        ->placeholder('Fulfillment notes, next steps, vendor details, or anything the internal team should know.')
                                        ->rows(5)
                                        ->maxLength(5000)
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    Tab::make('Player Submission')
                        ->icon('heroicon-m-paper-clip')
                        ->schema([
                            Section::make('Player Notes')
                                ->schema([
                                    Placeholder::make('player_notes')
                                        ->label('Instructions submitted with this request')
                                        ->content(fn (?CreditServiceRequest $record): HtmlString => new HtmlString(
                                            '<div style="white-space:pre-wrap;line-height:1.6">' . e((string) ($record?->notes ?: 'No notes were provided.')) . '</div>'
                                        )),
                                ]),
                            Section::make('Player Resources')
                                ->description('Reference files uploaded by the player for this specific service request.')
                                ->schema([
                                    Placeholder::make('request_resources_display')
                                        ->label('Uploaded Resources')
                                        ->content(fn (?CreditServiceRequest $record): HtmlString => static::requestResourceLinks($record)),
                                ]),
                            Section::make('Same Submission')
                                ->description('Other services submitted in the same checkout.')
                                ->schema([
                                    Placeholder::make('batch_services')
                                        ->label('Services in this batch')
                                        ->content(fn (?CreditServiceRequest $record): HtmlString => static::batchSummary($record)),
                                ]),
                        ]),
                    Tab::make('Fulfillment & Audit')
                        ->icon('heroicon-m-check-badge')
                        ->schema([
                            Section::make('Delivery')
                                ->description('Files and links provided to the player for this service request.')
                                ->columns(3)
                                ->schema([
                                    Placeholder::make('delivery_status')
                                        ->label('Provided')
                                        ->content(fn (?CreditServiceRequest $record): string => $record?->provided_at ? $record->provided_at->format('M j, Y g:i A') : 'Not provided yet'),
                                    Placeholder::make('provided_by')
                                        ->label('Provided By')
                                        ->content(fn (?CreditServiceRequest $record): string => $record
                                            ? (trim(($record->providedBy?->first_name ?? '') . ' ' . ($record->providedBy?->last_name ?? '')) ?: ($record->providedBy?->email ?? '—'))
                                            : '—'),
                                    Placeholder::make('delivery_file')
                                        ->label('File')
                                        ->content(function (?CreditServiceRequest $record): HtmlString {
                                            if (! $record?->delivery_file_path) {
                                                return new HtmlString('<span style="color:#6b7280">No file uploaded.</span>');
                                            }
                                            $url = Storage::disk('public')->url($record->delivery_file_path);
                                            return new HtmlString('<a href="' . e($url) . '" target="_blank" rel="noopener" style="color:#16a34a;font-weight:700;text-decoration:none">Open delivered file</a>');
                                        }),
                                    Placeholder::make('delivery_link')
                                        ->label('Delivery Link')
                                        ->content(function (?CreditServiceRequest $record): HtmlString {
                                            if (! $record?->delivery_url) {
                                                return new HtmlString('<span style="color:#6b7280">No external link.</span>');
                                            }
                                            return new HtmlString('<a href="' . e($record->delivery_url) . '" target="_blank" rel="noopener" style="color:#16a34a;font-weight:700;text-decoration:none">Open delivery link</a>');
                                        }),
                                    Placeholder::make('delivery_notes')
                                        ->label('Delivery Notes')
                                        ->content(fn (?CreditServiceRequest $record): HtmlString => new HtmlString(
                                            '<div style="white-space:pre-wrap;line-height:1.6">' . e((string) ($record?->delivery_notes ?: 'No delivery notes.')) . '</div>'
                                        ))
                                        ->columnSpan(2),
                                ]),
                            Section::make('Credit Audit')
                                ->columns(3)
                                ->schema([
                                    Placeholder::make('original_spend')
                                        ->label('Originally Spent')
                                        ->content(fn (?CreditServiceRequest $record): string => $record ? number_format((int) $record->points_spent) . ' credits' : '-'),
                                    Placeholder::make('returned')
                                        ->label('Manually Returned')
                                        ->content(fn (?CreditServiceRequest $record): string => $record ? number_format((int) $record->credits_returned) . ' credits' : '-'),
                                    Placeholder::make('still_returnable')
                                        ->label('Eligible for Manual Return')
                                        ->content(fn (?CreditServiceRequest $record): string => $record ? number_format($record->refundableCredits()) . ' credits' : '-'),
                                    Placeholder::make('credit_transaction')
                                        ->label('Original Ledger Transaction')
                                        ->content(fn (?CreditServiceRequest $record): string => $record?->credit_point_transaction_id ? '#' . $record->credit_point_transaction_id : 'Not linked'),
                                    Placeholder::make('admin_alert')
                                        ->label('Admin Alert Email')
                                        ->content(fn (?CreditServiceRequest $record): string => $record
                                            ? match ($record->email_alert_status) {
                                                'sent' => 'Sent ' . (optional($record->email_alerted_at)->format('M j, Y g:i A') ?: ''),
                                                'failed' => 'Failed' . ($record->email_alert_error ? ': ' . $record->email_alert_error : ''),
                                                default => 'Not recorded',
                                            }
                                            : '-'),
                                    Placeholder::make('last_player_contact')
                                        ->label('Last Player Contact')
                                        ->content(fn (?CreditServiceRequest $record): string => optional($record?->admin_contacted_at)->format('M j, Y g:i A') ?: 'Not contacted yet'),
                                ]),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'managedBy', 'providedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user_name')
                    ->label('Player')
                    ->state(fn (CreditServiceRequest $record): string => trim(($record->user?->first_name ?? '') . ' ' . ($record->user?->last_name ?? '')) ?: ($record->user?->email ?? 'User #' . $record->user_id))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $query) use ($search): void {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('personal_email', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('item_name')
                    ->label('Service')
                    ->description(fn (CreditServiceRequest $record): string => 'Qty ' . number_format((int) $record->quantity) . ($record->modifier === 'rush' ? ' · Rush' : ''))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('points_spent')
                    ->label('Spent')
                    ->state(fn (CreditServiceRequest $record): string => number_format((int) $record->points_spent) . ' credits')
                    ->sortable(),

                TextColumn::make('user.points_available')
                    ->label('Balance')
                    ->state(fn (CreditServiceRequest $record): string => number_format((int) ($record->user?->points_available ?? 0)))
                    ->suffix(' credits')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => CreditServiceRequest::statusOptions()[$state] ?? Str::headline((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'submitted' => 'warning',
                        'reviewed' => 'info',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'declined' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('batch')
                    ->label('Batch')
                    ->state(fn (CreditServiceRequest $record): string => Str::limit($record->batchToken(), 18, '…'))
                    ->tooltip(fn (CreditServiceRequest $record): string => $record->batchToken())
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('resource_count')
                    ->label('Resources')
                    ->state(fn (CreditServiceRequest $record): int => count((array) $record->request_resources))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('managedBy.email')
                    ->label('Managed By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CreditServiceRequest::statusOptions())
                    ->multiple(),

                SelectFilter::make('item_key')
                    ->label('Service')
                    ->options(fn (): array => collect(app(CreditPointService::class)->catalog())
                        ->mapWithKeys(fn (array $item, string $key): array => [$key => $item['name']])
                        ->all())
                    ->multiple()
                    ->searchable(),

                TernaryFilter::make('rush')
                    ->label('Rush')
                    ->placeholder('All requests')
                    ->trueLabel('Rush only')
                    ->falseLabel('Standard only')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('modifier', 'rush'),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                            $query->whereNull('modifier')->orWhere('modifier', '!=', 'rush');
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),

                Filter::make('requested_at')
                    ->label('Requested Date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordAction('edit')
            ->recordUrl(null)
            ->actions([
                EditAction::make()
                    ->label('Review')
                    ->icon('heroicon-m-eye')
                    ->iconButton()
                    ->tooltip('Review request')
                    ->slideOver()
                    ->modalWidth('4xl'),

                Action::make('followUp')
                    ->label('Follow Up')
                    ->icon('heroicon-m-envelope')
                    ->iconButton()
                    ->tooltip('Follow up using Admin Support')
                    ->action(function (CreditServiceRequest $record, \Livewire\Component $livewire): void {
                        $livewire->dispatch(
                            'open-credit-request-follow-up',
                            requestId: (int) $record->getKey(),
                        )->to(\App\Livewire\AdminSupportMessenger::class);
                    }),

                Action::make('resources')
                    ->label('Resources')
                    ->icon('heroicon-m-paper-clip')
                    ->iconButton()
                    ->tooltip('Open player-uploaded resources')
                    ->color('info')
                    ->visible(fn (CreditServiceRequest $record): bool => count((array) $record->request_resources) > 0)
                    ->modalHeading(fn (CreditServiceRequest $record): string => 'Resources — ' . $record->item_name)
                    ->modalDescription('Open or download the files the player attached to this service request.')
                    ->form([
                        Placeholder::make('resource_files')
                            ->label('Player Uploads')
                            ->content(fn (CreditServiceRequest $record): HtmlString => static::requestResourceLinks($record)),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('provide')
                    ->label('Provide')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->iconButton()
                    ->tooltip('Provide file or delivery link')
                    ->color('success')
                    ->modalHeading(fn (CreditServiceRequest $record): string => 'Provide — ' . $record->item_name)
                    ->modalDescription('Upload the finished file, paste a delivery link, or provide both. Providing the request marks this service as completed.')
                    ->fillForm(fn (CreditServiceRequest $record): array => [
                        'delivery_file_path' => $record->delivery_file_path,
                        'delivery_url' => $record->delivery_url,
                        'delivery_notes' => $record->delivery_notes,
                    ])
                    ->form([
                        FileUpload::make('delivery_file_path')
                            ->label('Upload File')
                            ->disk('public')
                            ->directory('credit-service-deliveries')
                            ->visibility('public')
                            ->downloadable()
                            ->openable()
                            ->maxSize(51200)
                            ->helperText('Optional when a delivery link is provided. Maximum 50 MB.'),

                        TextInput::make('delivery_url')
                            ->label('Delivery Link')
                            ->url()
                            ->maxLength(2000)
                            ->placeholder('https://...')
                            ->helperText('Optional when a file is uploaded.'),

                        Textarea::make('delivery_notes')
                            ->label('Delivery Notes')
                            ->rows(5)
                            ->maxLength(3000)
                            ->placeholder('Add a short note about what was delivered, revisions, file contents, or next steps.'),
                    ])
                    ->action(function (CreditServiceRequest $record, array $data): void {
                        $file = $data['delivery_file_path'] ?? null;
                        if (is_array($file)) {
                            $file = collect($file)->filter()->first();
                        }

                        $file = trim((string) $file) ?: null;
                        $url = trim((string) ($data['delivery_url'] ?? '')) ?: null;
                        $notes = trim((string) ($data['delivery_notes'] ?? '')) ?: null;

                        if (! $file && ! $url) {
                            throw ValidationException::withMessages([
                                'delivery_file_path' => 'Upload a file or provide a delivery link before completing this request.',
                            ]);
                        }

                        $record->forceFill([
                            'delivery_file_path' => $file,
                            'delivery_url' => $url,
                            'delivery_notes' => $notes,
                            'provided_at' => now(),
                            'provided_by_user_id' => auth()->id(),
                            'managed_by_user_id' => auth()->id(),
                            'status' => 'completed',
                        ])->save();

                        Notification::make()
                            ->title('Request provided to the player.')
                            ->body('The delivery is now available in the player’s Credit Usage history.')
                            ->success()
                            ->send();
                    }),

                Action::make('returnCredits')
                    ->label('Return Credits')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->iconButton()
                    ->tooltip('Manually return credits')
                    ->color('warning')
                    ->visible(fn (CreditServiceRequest $record): bool => $record->refundableCredits() > 0)
                    ->modalHeading('Manually Return Credits')
                    ->modalDescription('This is the only request-level refund path. Changing or declining the request status never restores credits automatically.')
                    ->fillForm(fn (CreditServiceRequest $record): array => [
                        'points' => $record->refundableCredits(),
                        'reason' => '',
                    ])
                    ->form([
                        TextInput::make('points')
                            ->label('Credits to Return')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(fn (CreditServiceRequest $record): int => $record->refundableCredits()),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->rows(4)
                            ->required()
                            ->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->action(function (CreditServiceRequest $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            /** @var CreditServiceRequest $locked */
                            $locked = CreditServiceRequest::query()
                                ->with('user')
                                ->lockForUpdate()
                                ->findOrFail($record->getKey());

                            $points = (int) ($data['points'] ?? 0);
                            $remaining = $locked->refundableCredits();

                            if ($points <= 0 || $points > $remaining) {
                                throw ValidationException::withMessages([
                                    'points' => 'You can return up to ' . number_format($remaining) . ' credits for this request.',
                                ]);
                            }

                            $admin = auth()->user();
                            $reason = trim((string) ($data['reason'] ?? ''));

                            app(CreditPointService::class)->adjust(
                                $locked->user,
                                $points,
                                $reason,
                                'admin:' . ($admin?->email ?: (string) ($admin?->getKey() ?? 'system')),
                                'credit-request-return:' . $locked->getKey() . ':' . (string) Str::uuid(),
                                [
                                    'source_type' => 'credit_request_manual_return',
                                    'source_id' => (string) $locked->getKey(),
                                    'meta' => [
                                        'credit_service_request_id' => $locked->getKey(),
                                        'original_points_spent' => (int) $locked->points_spent,
                                    ],
                                ],
                            );

                            $locked->forceFill([
                                'credits_returned' => (int) $locked->credits_returned + $points,
                                'managed_by_user_id' => $admin?->getKey(),
                            ])->save();
                        });

                        Notification::make()
                            ->title('Credits returned manually.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected static function requestResourceLinks(?CreditServiceRequest $record): HtmlString
    {
        $resources = collect((array) ($record?->request_resources ?? []))
            ->filter(fn ($resource): bool => is_array($resource) && filled($resource['path'] ?? null))
            ->values();
        if ($resources->isEmpty()) {
            return new HtmlString('<span style="color:#6b7280">No resources uploaded.</span>');
        }
        $html = '<div style="display:grid;gap:.55rem">';
        foreach ($resources as $resource) {
            $path = (string) $resource['path'];
            $name = trim((string) ($resource['name'] ?? basename($path))) ?: basename($path);
            $url = Storage::disk('public')->url($path);
            $size = static::formatBytes((int) ($resource['size'] ?? 0));
            $html .= '<div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.7rem .75rem;border:1px solid #e5e7eb;border-radius:.65rem">'
                . '<div style="min-width:0"><strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . e($name) . '</strong><span style="font-size:.72rem;color:#6b7280">' . e($size) . '</span></div>'
                . '<div style="display:flex;gap:.4rem;flex:0 0 auto"><a href="' . e($url) . '" target="_blank" rel="noopener" style="color:#2563eb;font-weight:700;text-decoration:none">Open</a><a href="' . e($url) . '" download="' . e($name) . '" style="color:#16a34a;font-weight:700;text-decoration:none">Download</a></div></div>';
        }
        return new HtmlString($html . '</div>');
    }

    protected static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return 'File';
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = min((int) floor(log($bytes, 1024)), count($units) - 1);
        return number_format($bytes / (1024 ** $index), $index === 0 ? 0 : 1) . ' ' . $units[$index];
    }

    protected static function playerEmail(CreditServiceRequest $record): ?string
    {
        $record->loadMissing('user');

        foreach ([
            $record->user?->email,
            $record->user?->personal_email,
            $record->user?->parent_email,
        ] as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    protected static function batchSummary(?CreditServiceRequest $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('<div class="text-sm text-gray-500">No batch information available.</div>');
        }

        $batch = $record->batchToken();
        $rows = CreditServiceRequest::query()
            ->where('user_id', $record->user_id)
            ->where('request_token', 'like', $batch . ':%')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $rows = collect([$record]);
        }

        $html = $rows->map(function (CreditServiceRequest $row): string {
            $status = e($row->statusLabel());
            $name = e($row->item_name);
            $rush = $row->modifier === 'rush' ? ' · Rush' : '';

            return '<div style="display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-top:1px solid rgba(148,163,184,.18)">'
                . '<div><strong>' . $name . ' × ' . number_format((int) $row->quantity) . '</strong>'
                . '<div style="font-size:12px;color:#6b7280">' . $status . e($rush) . '</div></div>'
                . '<strong>-' . number_format((int) $row->points_spent) . '</strong>'
                . '</div>';
        })->implode('');

        return new HtmlString('<div>' . $html . '</div>');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditServiceRequests::route('/'),
            'edit' => EditCreditServiceRequest::route('/{record}/edit'),
        ];
    }
}