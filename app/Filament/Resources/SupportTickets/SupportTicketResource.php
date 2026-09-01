<?php

namespace App\Filament\Resources\SupportTickets;

use App\Filament\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\SupportTicketSettings;
use App\Models\SupportTicket;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Support Tickets';
    protected static ?string $modelLabel = 'Support Ticket';
    protected static ?string $pluralModelLabel = 'Support Tickets';
    protected static ?string $slug = 'support-tickets';
    protected static ?int $navigationSort = 90;
    protected static ?string $recordTitleAttribute = 'ticket_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ticket')
                ->columns(2)
                ->schema([
                    TextInput::make('ticket_number')
                        ->label('Ticket')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('email')
                        ->label('Account Email')
                        ->disabled()
                        ->dehydrated(false),
                    Select::make('category')
                        ->options(SupportTicket::categories())
                        ->required(),
                    Select::make('status')
                        ->options(SupportTicket::statuses())
                        ->required(),
                    Select::make('priority')
                        ->options(SupportTicket::priorities())
                        ->required(),
                    TextInput::make('source')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('email_alert_status')
                        ->label('Alert Email')
                        ->disabled()
                        ->dehydrated(false),
                    Textarea::make('message')
                        ->rows(8)
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Textarea::make('conversation')
                        ->label('Conversation')
                        ->rows(12)
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(function ($state, ?SupportTicket $record): string {
                            if (! $record) {
                                return '';
                            }

                            // Support replies live directly in support_tickets.conversation JSON.
                            // Do not load a messages relationship: this project intentionally uses
                            // the single-table ticket architecture.
                            return (string) $record->conversation_text;
                        })
                        ->columnSpanFull(),
                    Textarea::make('admin_notes')
                        ->label('Internal Notes')
                        ->rows(6)
                        ->placeholder('Add internal follow-up notes for the PLYRCARD team.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (SupportTicket $record): string => static::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('user.first_name')
                    ->label('User')
                    ->formatStateUsing(function ($state, SupportTicket $record): string {
                        $name = trim(collect([$record->user?->first_name, $record->user?->last_name])->filter()->implode(' '));
                        return $name !== '' ? $name : 'User #' . ($record->user_id ?: '—');
                    }),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('category')
                    ->label('Concern')
                    ->formatStateUsing(fn (string $state): string => (string) (SupportTicket::categories()[$state] ?? ucfirst(str_replace('_', ' ', $state))))
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => (string) (SupportTicket::statuses()[$state] ?? ucfirst(str_replace('_', ' ', $state))))
                    ->color(fn (string $state): string => match ($state) {
                        'resolved', 'closed' => 'success',
                        'in_progress' => 'warning',
                        'waiting_on_user' => 'info',
                        default => 'danger',
                    }),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('email_alert_status')
                    ->label('Alert')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'sent' ? 'success' : ($state === 'failed' ? 'danger' : 'gray')),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(SupportTicket::statuses()),
                SelectFilter::make('category')->options(SupportTicket::categories()),
                SelectFilter::make('priority')->options(SupportTicket::priorities()),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isAdminUser();
    }

    public static function canAccess(): bool
    {
        return static::isAdminUser();
    }

    public static function canViewAny(): bool
    {
        return static::isAdminUser();
    }

    public static function canView(Model $record): bool
    {
        return static::isAdminUser();
    }

    public static function canEdit(Model $record): bool
    {
        return static::isAdminUser();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::isAdminUser();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::isAdminUser()) {
            return null;
        }

        if (! SchemaFacade::hasTable('support_tickets')) {
            return null;
        }

        $count = SupportTicket::query()->whereIn('status', ['open', 'in_progress', 'waiting_on_user'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'settings' => SupportTicketSettings::route('/settings'),
            'edit' => EditSupportTicket::route('/{record}/edit'),
        ];
    }

    protected static function isAdminUser(): bool
    {
        $user = auth()->user();
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        foreach (['Superadmin', 'superadmin', 'Super Admin', 'Administrator', 'Admin'] as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}