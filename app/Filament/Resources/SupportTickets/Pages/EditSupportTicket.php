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

                    $result = app(SupportAlertService::class)->sendSupportReply($ticket->fresh(), $reply);

                    if ($result['success'] ?? false) {
                        Notification::make()->title('Reply sent')->body('The reply was added to the ticket and emailed to the client.')->success()->send();
                    } else {
                        Notification::make()->title('Reply saved, email not sent')->body((string) ($result['error'] ?? 'The client email could not be sent.'))->warning()->send();
                    }

                    $this->refreshFormData(['conversation_text', 'status', 'metadata']);
                }),
            DeleteAction::make(),
        ];
    }
}