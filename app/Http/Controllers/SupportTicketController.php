<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\SupportAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        return response()->json([
            'success' => true,
            'tickets' => $this->ticketsForUser($user->getKey()),
        ]);
    }

    public function store(Request $request, SupportAlertService $alerts): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(SupportTicket::categories()))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'source' => ['nullable', Rule::in(['coach_database', 'locker_room', 'admin_panel'])],
        ]);

        $email = $this->accountEmail($user);
        $name = trim(collect([$user->first_name, $user->last_name])->filter()->implode(' ')) ?: 'PLYRCARD user';
        $message = trim((string) $validated['message']);

        $ticket = new SupportTicket([
            'user_id' => $user->getKey(),
            'email' => $email,
            'category' => (string) $validated['category'],
            'message' => $message,
            'status' => 'open',
            'priority' => 'normal',
            'source' => (string) ($validated['source'] ?? 'coach_database'),
            'metadata' => [
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [],
                'page_url' => $request->headers->get('referer'),
            ],
        ]);
        $ticket->appendConversation('client', (int) $user->getKey(), $name, $message);
        $ticket->save();

        $alert = $alerts->sendSupportTicket($ticket);
        $this->recordAlertResult($ticket, $alert);

        $payload = [
            'success' => true,
            'message' => 'Your support ticket has been submitted. Our team will review it soon.',
            'ticket_number' => $ticket->ticket_number,
            'tickets' => $this->ticketsForUser($user->getKey()),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return back()->with('support_ticket_success', $payload['message'] . ' Ticket: ' . $ticket->ticket_number);
    }

    public function followUp(Request $request, SupportTicket $ticket, SupportAlertService $alerts): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $ticket->user_id === (int) $user->getKey(), 404);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $name = trim(collect([$user->first_name, $user->last_name])->filter()->implode(' ')) ?: 'PLYRCARD user';
        $message = trim((string) $validated['message']);

        $ticket->appendConversation('client', (int) $user->getKey(), $name, $message);

        if (in_array($ticket->status, ['resolved', 'closed', 'waiting_on_user'], true)) {
            $ticket->status = 'open';
        }

        $metadata = is_array($ticket->metadata) ? $ticket->metadata : [];
        $metadata['last_client_follow_up_at'] = now()->toIso8601String();
        $ticket->metadata = $metadata;
        $ticket->save();

        $alerts->sendSupportFollowUp($ticket, $message);

        return response()->json([
            'success' => true,
            'message' => 'Your follow-up was added to the ticket.',
            'tickets' => $this->ticketsForUser($user->getKey()),
        ]);
    }

    protected function accountEmail($user): string
    {
        foreach ([$user->email ?? null, $user->personal_email ?? null, $user->parent_email ?? null] as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return 'no-email@plyrcard.local';
    }

    protected function recordAlertResult(SupportTicket $ticket, array $alert): void
    {
        $ticket->forceFill([
            'email_alert_status' => ($alert['success'] ?? false) ? 'sent' : 'failed',
            'email_alerted_at' => ($alert['success'] ?? false) ? now() : null,
            'email_alert_error' => ($alert['success'] ?? false) ? null : ($alert['error'] ?? 'Alert email was not accepted by the mail server.'),
        ])->save();
    }

    protected function ticketsForUser(int $userId): array
    {
        return SupportTicket::query()
            ->where('user_id', $userId)
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (SupportTicket $ticket): array => [
                'id' => $ticket->getKey(),
                'ticket_number' => $ticket->ticket_number,
                'category' => $ticket->category,
                'category_label' => $ticket->categoryLabel(),
                'status' => $ticket->status,
                'status_label' => $ticket->statusLabel(),
                'priority' => $ticket->priority,
                'message' => $ticket->message,
                'conversation' => is_array($ticket->conversation) ? $ticket->conversation : [],
                'created_at' => optional($ticket->created_at)->toIso8601String(),
                'updated_at' => optional($ticket->updated_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}