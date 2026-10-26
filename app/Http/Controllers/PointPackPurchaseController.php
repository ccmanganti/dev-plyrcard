<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PointPackPurchaseController extends Controller
{
    public function start(Request $request, StripeBillingService $stripe, string $pack): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        try {
            $pack = $this->prepareDynamicPack($pack);

            return response()->json($stripe->startPointPack($user, $pack));
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'completed' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function status(Request $request, StripeBillingService $stripe, string $pack): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        return response()->json($stripe->pointPackStatus($user, strtolower(trim($pack))));
    }

    protected function prepareDynamicPack(string $pack): string
    {
        $pack = strtolower(trim($pack));

        if (! str_starts_with($pack, 'custom-')) {
            return $pack;
        }

        if (! (bool) config('plyrcard-points.custom_point_purchase.enabled', true)) {
            throw new RuntimeException('Custom point purchases are currently unavailable.');
        }

        $points = (int) substr($pack, strlen('custom-'));
        $minimum = max(1, (int) config('plyrcard-points.custom_point_purchase.min_points', 1));
        $maximum = max($minimum, (int) config('plyrcard-points.custom_point_purchase.max_points', 1000));

        if ($points < $minimum || $points > $maximum) {
            throw new RuntimeException(sprintf(
                'Choose between %s and %s points.',
                number_format($minimum),
                number_format($maximum),
            ));
        }

        $pricePerPoint = max(1, (int) config('plyrcard-points.custom_point_purchase.price_per_point_cents', 100));
        $productId = trim((string) config('plyrcard-points.custom_point_purchase.stripe_product_id', ''));

        if ($productId === '') {
            // Reusing the 50-point Stripe product is a safe deployment fallback.
            // A dedicated generic "PLYR Points" Stripe product is preferred.
            $productId = trim((string) config('plyrcard-points.point_packs.50.stripe_product_id', ''));
        }

        if ($productId === '') {
            $productId = trim((string) config('plyrcard-points.point_packs.100.stripe_product_id', ''));
        }

        if ($productId === '') {
            throw new RuntimeException('The Stripe Product ID for custom point purchases is not configured.');
        }

        config([
            'plyrcard-points.point_packs.' . $pack => [
                'label' => number_format($points) . ' Custom Points',
                'points' => $points,
                'price_cents' => $points * $pricePerPoint,
                'stripe_product_id' => $productId,
                'enabled' => true,
                'note' => '$1 per point custom top-up.',
            ],
        ]);

        return $pack;
    }
}