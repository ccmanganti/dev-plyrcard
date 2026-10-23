<?php

namespace App\Http\Controllers;

use App\Models\CreditPointTransaction;
use App\Models\CreditServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlyrPointsHistoryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (! Schema::hasTable('credit_point_transactions')) {
            return response()->json([
                'success' => true,
                'balance' => (int) ($user->points_available ?? 0),
                'entries' => [],
            ]);
        }

        $transactions = CreditPointTransaction::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->limit(200)
            ->get();

        $requests = Schema::hasTable('credit_service_requests')
            ? CreditServiceRequest::query()
                ->where('user_id', $user->getKey())
                ->latest('id')
                ->limit(200)
                ->get()
            : collect();

        $requestsById = $requests->keyBy(fn (CreditServiceRequest $serviceRequest): string => (string) $serviceRequest->getKey());
        $requestsByTransaction = $requests
            ->filter(fn (CreditServiceRequest $serviceRequest): bool => (int) ($serviceRequest->credit_point_transaction_id ?? 0) > 0)
            ->keyBy(fn (CreditServiceRequest $serviceRequest): string => (string) $serviceRequest->credit_point_transaction_id);

        $seenRequestIds = [];

        $entries = $transactions->map(function (CreditPointTransaction $transaction) use ($requestsById, $requestsByTransaction, &$seenRequestIds): array {
            $serviceRequest = null;

            if ((string) $transaction->source_type === 'service_request' && filled($transaction->source_id)) {
                $serviceRequest = $requestsById->get((string) $transaction->source_id);
            }

            if (! $serviceRequest) {
                $serviceRequest = $requestsByTransaction->get((string) $transaction->getKey());
            }

            if ($serviceRequest) {
                $seenRequestIds[(string) $serviceRequest->getKey()] = true;
            }

            return $this->transactionEntry($transaction, $serviceRequest);
        });

        // A service request should still appear even if its matching point ledger entry is
        // older than the transaction window or was created before the current ledger link.
        $missingRequestEntries = $requests
            ->reject(fn (CreditServiceRequest $serviceRequest): bool => isset($seenRequestIds[(string) $serviceRequest->getKey()]))
            ->map(fn (CreditServiceRequest $serviceRequest): array => $this->standaloneRequestEntry($serviceRequest));

        $entries = $entries
            ->concat($missingRequestEntries)
            ->sortByDesc(fn (array $entry): int => (int) ($entry['sort_timestamp'] ?? 0))
            ->values();

        return response()->json([
            'success' => true,
            'balance' => (int) ($user->fresh()->points_available ?? 0),
            'entries' => $entries,
        ]);
    }

    private function transactionEntry(CreditPointTransaction $transaction, ?CreditServiceRequest $serviceRequest): array
    {
        $meta = (array) ($transaction->meta ?? []);
        $positive = $this->isPositiveTransaction($transaction, $meta);
        $points = abs((int) $transaction->points);
        $status = $serviceRequest?->statusLabel() ?? $this->transactionStatus($transaction);
        $item = trim((string) ($transaction->reason ?: data_get($meta, 'item_name', 'PLYR Points activity')));

        if ($item === '' && $serviceRequest) {
            $item = (string) $serviceRequest->item_name;
        }

        $kind = $serviceRequest
            ? 'request'
            : (in_array((string) $transaction->source_type, ['package_purchase', 'top_up_purchase'], true) ? 'purchase' : 'activity');

        return [
            'id' => 'transaction-' . $transaction->getKey(),
            'transaction_id' => (int) $transaction->getKey(),
            'request_id' => $serviceRequest?->getKey(),
            'kind' => $kind,
            'date' => optional($transaction->created_at)->format('M j, Y') ?: '—',
            'date_time' => optional($transaction->created_at)->format('M j, Y g:i A') ?: '—',
            'created_at' => optional($transaction->created_at)->toIso8601String(),
            'sort_timestamp' => optional($transaction->created_at)->timestamp ?? 0,
            'item' => $item !== '' ? $item : 'PLYR Points activity',
            'points' => $positive ? $points : -$points,
            'points_absolute' => $points,
            'amount' => ($positive ? '+' : '−') . number_format($points),
            'positive' => $positive,
            'status' => $status,
            'type' => Str::headline((string) $transaction->type),
            'source' => Str::headline((string) ($transaction->source_type ?: 'account activity')),
            'request' => $serviceRequest ? $this->requestDetail($serviceRequest) : null,
        ];
    }

    private function standaloneRequestEntry(CreditServiceRequest $serviceRequest): array
    {
        $points = abs((int) $serviceRequest->points_spent);

        return [
            'id' => 'request-' . $serviceRequest->getKey(),
            'transaction_id' => $serviceRequest->credit_point_transaction_id ? (int) $serviceRequest->credit_point_transaction_id : null,
            'request_id' => (int) $serviceRequest->getKey(),
            'kind' => 'request',
            'date' => optional($serviceRequest->created_at)->format('M j, Y') ?: '—',
            'date_time' => optional($serviceRequest->created_at)->format('M j, Y g:i A') ?: '—',
            'created_at' => optional($serviceRequest->created_at)->toIso8601String(),
            'sort_timestamp' => optional($serviceRequest->created_at)->timestamp ?? 0,
            'item' => (string) $serviceRequest->item_name,
            'points' => -$points,
            'points_absolute' => $points,
            'amount' => '−' . number_format($points),
            'positive' => false,
            'status' => $serviceRequest->statusLabel(),
            'type' => 'Service Request',
            'source' => 'Service Request',
            'request' => $this->requestDetail($serviceRequest),
        ];
    }

    private function requestDetail(CreditServiceRequest $serviceRequest): array
    {
        $resources = collect((array) $serviceRequest->request_resources)
            ->filter(fn ($resource): bool => is_array($resource) && filled($resource['path'] ?? null))
            ->map(function (array $resource): array {
                $path = (string) $resource['path'];

                return [
                    'name' => trim((string) ($resource['name'] ?? basename($path))) ?: 'Resource file',
                    'url' => Storage::disk('public')->url($path),
                ];
            })
            ->values()
            ->all();

        $deliveryFilePath = trim((string) ($serviceRequest->delivery_file_path ?? ''));

        return [
            'id' => (int) $serviceRequest->getKey(),
            'item_name' => (string) $serviceRequest->item_name,
            'status_key' => (string) $serviceRequest->status,
            'status' => $serviceRequest->statusLabel(),
            'status_rank' => match ((string) $serviceRequest->status) {
                'reviewed' => 2,
                'in_progress' => 3,
                'completed' => 4,
                default => 1,
            },
            'quantity' => (int) $serviceRequest->quantity,
            'modifier' => (string) ($serviceRequest->modifier ?: 'standard'),
            'turnaround' => $serviceRequest->modifier === 'rush' ? 'Rush +50%' : 'Standard',
            'points_spent' => (int) $serviceRequest->points_spent,
            'credits_returned' => (int) $serviceRequest->credits_returned,
            'notes' => trim((string) ($serviceRequest->notes ?? '')),
            'resources' => $resources,
            'delivery_file_url' => $deliveryFilePath !== '' ? Storage::disk('public')->url($deliveryFilePath) : null,
            'delivery_file_name' => $deliveryFilePath !== '' ? basename($deliveryFilePath) : null,
            'delivery_url' => filled($serviceRequest->delivery_url) ? (string) $serviceRequest->delivery_url : null,
            'delivery_notes' => trim((string) ($serviceRequest->delivery_notes ?? '')),
            'provided_at' => optional($serviceRequest->provided_at)->format('M j, Y'),
            'created_at' => optional($serviceRequest->created_at)->format('M j, Y g:i A') ?: '—',
            'updated_at' => optional($serviceRequest->updated_at)->format('M j, Y g:i A') ?: '—',
        ];
    }

    private function isPositiveTransaction(CreditPointTransaction $transaction, array $meta): bool
    {
        if (in_array((string) $transaction->type, ['grant', 'refund', 'release'], true)) {
            return true;
        }

        if (in_array((string) $transaction->type, ['debit', 'hold'], true)) {
            return false;
        }

        return (int) ($meta['adjust_sign'] ?? 1) >= 0;
    }

    private function transactionStatus(CreditPointTransaction $transaction): string
    {
        return match (true) {
            in_array((string) $transaction->source_type, ['package_purchase', 'top_up_purchase'], true) => 'Added',
            in_array((string) $transaction->type, ['refund', 'release'], true) => 'Returned',
            (string) $transaction->type === 'adjust' => 'Adjusted',
            (string) $transaction->type === 'hold' => 'Reserved',
            (string) $transaction->type === 'debit' => 'Used',
            default => Str::headline((string) $transaction->type),
        };
    }
}
