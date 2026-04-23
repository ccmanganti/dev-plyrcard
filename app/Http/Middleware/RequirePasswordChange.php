<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use STS\FilamentImpersonate\Facades\Impersonation;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // If this session is impersonating, do not force the password-change page.
        // This prevents superadmins from getting blocked when impersonating users.
        if (Impersonation::isImpersonating()) {
            return $next($request);
        }

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