<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Services\SupportAlertService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply to Client')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->schema([
                    Textarea::make('reply')
                        ->label('Reply')
                        ->rows(7)
                        ->required()
                        ->minLength(2)
                        ->maxLength(5000)
                        ->placeholder('Write the message the client should receive.'),
                ])
                ->modalHeading('Reply to Support Ticket')
                ->modalSubmitActionLabel('Send Reply')
                ->action(function (array $data): void {
                    $ticket = $this->record;
                    $admin = auth()->user();
                    $adminName = 'PLYRCARD Support';
                    $reply = trim((string) ($data['reply'] ?? ''));

                    $ticket->appendConversation('admin', $admin?->getKey(), $adminName, $reply);
                    if (! in_array($ticket->status, ['resolved', 'closed'], true)) {
                        $ticket->status = 'waiting_on_user';
                    }
                    $metadata = is_array($ticket->metadata) ? $ticket->metadata : [];
                    $metadata['last_admin_reply_at'] = now()->toIso8601String();
                    $metadata['last_admin_reply_by'] = $admin?->getKey();
                    $ticket->metadata = $metadata;
                    $ticket->save();

                    $result = app(SupportAlertService::class)->sendSupportReply($ticket->fresh('user'), $reply);

                    // Keep an audit trail of whether the player notification was delivered.
                    $ticket->refresh();
                    $metadata = is_array($ticket->metadata) ? $ticket->metadata : [];
                    $metadata['last_admin_reply_notification'] = [
                        'sent_at' => now()->toIso8601String(),
                        'success' => (bool) ($result['success'] ?? false),
                        'recipients' => array_values((array) ($result['sent_recipients'] ?? ($result['recipient'] ?? []))),
                        'failed_recipients' => array_values((array) ($result['failed_recipients'] ?? [])),
                        'error' => $result['error'] ?? null,
                    ];
                    $ticket->metadata = $metadata;
                    $ticket->save();

                    if ($result['success'] ?? false) {
                        $recipientLabel = collect((array) ($result['sent_recipients'] ?? []))->filter()->implode(', ');
                        Notification::make()
                            ->title('Reply sent to player')
                            ->body($recipientLabel !== ''
                                ? 'The reply was saved and emailed to ' . $recipientLabel . '.'
                                : 'The reply was saved and the player notification was sent.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Reply saved, notification not sent')
                            ->body((string) ($result['error'] ?? 'The player email could not be sent.'))
                            ->warning()
                            ->send();
                    }

                    $this->record = $ticket->fresh();
                    $this->refreshFormData(['conversation', 'status']);
                }),
            DeleteAction::make(),
        ];
    }
}