<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectParkedAdminDomains
{
    /**
     * Official PlyrCard application hosts.
     *
     * These hosts are allowed to access Filament directly.
     */
    protected array $platformHosts = [
        '127.0.0.1',
        'localhost',
        'dev.plyrcard.com',
        'test.plyrcard.com',
        'plyrcard.com',
        'www.plyrcard.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        /*
        |--------------------------------------------------------------------------
        | Allow local development
        |--------------------------------------------------------------------------
        */

        if (app()->environment('local')) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Allow official PlyrCard platform domains
        |--------------------------------------------------------------------------
        */

        if (in_array($host, $this->platformHosts, true)) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Redirect custom / parked domains to the main app
        |--------------------------------------------------------------------------
        |
        | APP_URL determines which environment owns the admin panel:
        |
        | Local:      http://localhost
        | Dev:        https://dev.plyrcard.com
        | Test:       https://test.plyrcard.com
        | Production: https://plyrcard.com
        |
        */

        $platformUrl = rtrim((string) config('app.url'), '/');

        if ($platformUrl === '') {
            $platformUrl = 'https://plyrcard.com';
        }

        /*
         * Preserve the requested admin path.
         *
         * Example:
         *
         * customdomain.com/admin/users
         *
         * becomes:
         *
         * test.plyrcard.com/admin/users
         *
         * when APP_URL=https://test.plyrcard.com
         */
        $requestUri = '/' . ltrim($request->getRequestUri(), '/');

        return redirect()->away($platformUrl . $requestUri);
    }
}