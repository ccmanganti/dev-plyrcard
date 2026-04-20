<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Allow the password change page, logout, and onboarding-complete endpoint
        $allowedRouteNames = [
            'filament.admin.pages.force-password-change',
            'filament.admin.auth.logout',
            'onboarding.complete',
        ];

        $routeName = $request->route()?->getName();

        if ($user->must_change_password && ! in_array($routeName, $allowedRouteNames, true)) {
            return redirect()->route('filament.admin.pages.force-password-change');
        }

        return $next($request);
    }
}