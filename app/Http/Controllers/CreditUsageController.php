<?php

namespace App\Http\Controllers;

use App\Models\CreditServiceRequest;
use App\Models\User;
use App\Services\CreditPointService;
use App\Services\SupportAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreditUsageController extends Controller
{
    public function store(
        Request $request,
        CreditPointService $credits,
        SupportAlertService $alerts,
    ): RedirectResponse {
        $catalog = $credits->catalog();

        $data = $request->validate([
            'request_token' => ['required', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.item_key' => ['required', 'string', Rule::in(array_keys($catalog))],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.rush' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'confirm_spend' => ['accepted'],
        ]);

        $selected = collect($data['items'])
            ->values()
            ->filter(fn (array $item): bool => (bool) ($item['selected'] ?? false));

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Select at least one service to use your credits.',
            ]);
        }

        $quotes = $selected
            ->map(function (array $item) use ($credits): array {
                return $credits->quoteService(
                    (string) $item['item_key'],
                    max(1, (int) ($item['quantity'] ?? 1)),
                    (bool) ($item['rush'] ?? false),
                );
            })
            ->values();

        $totalPoints = (int) $quotes->sum('points');
        $user = $request->user();
        $batchToken = trim((string) $data['request_token']);
        $notes = trim((string) ($data['notes'] ?? '')) ?: null;

        /** @var array{requests: Collection<int, CreditServiceRequest>, points: int, created_new: bool} $result */
        $result = DB::transaction(function () use ($user, $quotes, $totalPoints, $batchToken, $notes, $credits): array {
            $existing = CreditServiceRequest::query()
                ->where('user_id', $user->getKey())
                ->where('request_token', 'like', $batchToken . ':%')
                ->orderBy('id')
                ->get();

            if ($existing->isNotEmpty()) {
                return [
                    'requests' => $existing,
                    'points' => (int) $existing->sum('points_spent'),
                    'created_new' => false,
                ];
            }

            /** @var User $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ((int) $lockedUser->points_available < $totalPoints) {
                throw ValidationException::withMessages([
                    'credits' => sprintf(
                        'This request needs %s credits, but you currently have %s available.',
                        number_format($totalPoints),
                        number_format((int) $lockedUser->points_available),
                    ),
                ]);
            }

            $created = collect();

            foreach ($quotes as $index => $quote) {
                $itemRequestToken = $batchToken . ':' . $index;

                $serviceRequest = CreditServiceRequest::query()->create([
                    'user_id' => $lockedUser->getKey(),
                    'request_token' => $itemRequestToken,
                    'item_key' => $quote['item_key'],
                    'item_name' => $quote['item_name'],
                    'quantity' => $quote['quantity'],
                    'unit_price_points' => $quote['unit_price'],
                    'modifier' => $quote['modifier'],
                    'points_spent' => $quote['points'],
                    'notes' => $notes,
                    'status' => 'submitted',
                ]);

                // Athlete redemptions are final. No status change, decline, or cancellation
                // returns credits automatically. Only an explicit admin adjustment can do that.
                $transaction = $credits->spendForServiceRequest(
                    $lockedUser,
                    $quote['item_key'],
                    $quote['quantity'],
                    $quote['modifier'] === 'rush',
                    (string) $serviceRequest->getKey(),
                    [
                        'batch_request_token' => $batchToken,
                        'request_token' => $itemRequestToken,
                        'notes' => $serviceRequest->notes,
                    ],
                );

                $serviceRequest->forceFill([
                    'credit_point_transaction_id' => $transaction->getKey(),
                ])->save();

                $created->push($serviceRequest->fresh());
            }

            return [
                'requests' => $created,
                'points' => $totalPoints,
                'created_new' => true,
            ];
        });

        // A mail failure must never reverse a valid credit redemption. Notify admins only
        // for a newly-created batch so browser retries do not send duplicate alerts.
        if ($result['created_new']) {
            try {
                $alert = $alerts->sendCreditServiceRequestBatch(
                    $user->fresh() ?? $user,
                    $result['requests'],
                    (int) $result['points'],
                );

                $this->recordAlertResult($result['requests'], $alert);
            } catch (\Throwable $exception) {
                Log::warning('PLYRCARD credit request admin notification failed after redemption.', [
                    'user_id' => $user->getKey(),
                    'request_ids' => $result['requests']->pluck('id')->all(),
                    'error' => $exception->getMessage(),
                ]);

                $this->recordAlertResult($result['requests'], [
                    'success' => false,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $count = $result['requests']->count();

        return back()->with(
            'credit_success',
            sprintf(
                '%s submitted. %s credits were deducted from your balance.',
                $count === 1 ? '1 service request' : number_format($count) . ' service requests',
                number_format((int) $result['points']),
            ),
        );
    }

    /** @param Collection<int, CreditServiceRequest> $requests */
    protected function recordAlertResult(Collection $requests, array $alert): void
    {
        if (! Schema::hasColumn('credit_service_requests', 'email_alert_status')) {
            return;
        }

        $success = (bool) ($alert['success'] ?? false);

        CreditServiceRequest::query()
            ->whereKey($requests->pluck('id')->filter()->all())
            ->update([
                'email_alert_status' => $success ? 'sent' : 'failed',
                'email_alerted_at' => $success ? now() : null,
                'email_alert_error' => $success ? null : ($alert['error'] ?? 'Admin alert email was not accepted by the mail server.'),
                'updated_at' => now(),
            ]);
    }
}