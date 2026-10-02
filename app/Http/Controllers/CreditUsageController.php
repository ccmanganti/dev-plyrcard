<?php

namespace App\Http\Controllers;

use App\Models\CreditServiceRequest;
use App\Services\CreditPointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CreditUsageController extends Controller
{
    public function store(Request $request, CreditPointService $credits): RedirectResponse
    {
        $catalog = $credits->catalog();

        $data = $request->validate([
            'request_token' => ['required', 'string', 'max:100'],
            'item_key' => ['required', 'string', Rule::in(array_keys($catalog))],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'rush' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'confirm_spend' => ['accepted'],
        ]);

        $user = $request->user();
        $rush = (bool) ($data['rush'] ?? false);
        $quote = $credits->quoteService((string) $data['item_key'], (int) $data['quantity'], $rush);

        $serviceRequest = DB::transaction(function () use ($user, $data, $rush, $quote, $credits): CreditServiceRequest {
            $existing = CreditServiceRequest::query()
                ->where('user_id', $user->getKey())
                ->where('request_token', (string) $data['request_token'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $serviceRequest = CreditServiceRequest::query()->create([
                'user_id' => $user->getKey(),
                'request_token' => (string) $data['request_token'],
                'item_key' => $quote['item_key'],
                'item_name' => $quote['item_name'],
                'quantity' => $quote['quantity'],
                'unit_price_points' => $quote['unit_price'],
                'modifier' => $quote['modifier'],
                'points_spent' => $quote['points'],
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'status' => 'submitted',
            ]);

            // This is intentionally a permanent debit at submission time. There is
            // no athlete-facing release/refund path. If credits must be restored,
            // an admin must add them back through the User Resource adjustment action.
            $transaction = $credits->spendForServiceRequest(
                $user,
                $quote['item_key'],
                $quote['quantity'],
                $rush,
                (string) $serviceRequest->getKey(),
                [
                    'request_token' => (string) $data['request_token'],
                    'notes' => $serviceRequest->notes,
                ],
            );

            $serviceRequest->forceFill([
                'credit_point_transaction_id' => $transaction->getKey(),
            ])->save();

            return $serviceRequest->fresh();
        });

        return back()->with(
            'credit_success',
            sprintf(
                '%s submitted. %s credits were deducted from your balance.',
                $serviceRequest->item_name,
                number_format((int) $serviceRequest->points_spent),
            ),
        );
    }
}
