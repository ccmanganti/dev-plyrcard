<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporarily restrict player PLYRLab API operations on production while
 * leaving every existing controller, booking action, and dev/test flow intact.
 */
class PlyrLabProductionGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() === 'plyrcard.com') {
            return response()->json([
                'success' => false,
                'message' => 'PLYRLab is coming soon.',
            ], 503);
        }

        return $next($request);
    }
}
