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
    public function store(Request $request, SupportAlertService $alerts): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'category' => ['required', Rule::in(array_keys(SupportTicket::categories()))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'source' => ['nullable', Rule::in(['coach_database', 'locker_room', 'admin_panel'])],
        ]);

        $ticket = SupportTicket::query()->create([
            'user_id' => $user->getKey(),
            'email' => strtolower(trim((string) $validated['email'])),
            'category' => (string) $validated['category'],
            'message' => trim((string) $validated['message']),
            'status' => 'open',
            'priority' => 'normal',
            'source' => (string) ($validated['source'] ?? 'coach_database'),
            'metadata' => [
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [],
                'page_url' => $request->headers->get('referer'),
            ],
        ]);

        $alert = $alerts->sendSupportTicket($ticket);

        $ticket->forceFill([
            'email_alert_status' => ($alert['success'] ?? false) ? 'sent' : 'failed',
            'email_alerted_at' => ($alert['success'] ?? false) ? now() : null,
            'email_alert_error' => ($alert['success'] ?? false) ? null : ($alert['error'] ?? 'Alert email was not accepted by the mail server.'),
        ])->save();

        $payload = [
            'success' => true,
            'message' => 'Your support ticket has been submitted. Our team will review it soon.',
            'ticket_number' => $ticket->ticket_number,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return back()->with('support_ticket_success', $payload['message'] . ' Ticket: ' . $ticket->ticket_number);
    }
}
