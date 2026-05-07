<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectParkedAdminDomains
{
    protected array $platformHosts = [
        '127.0.0.1',
        'localhost',
        'dev.plyrcard.com',
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
        | Allow platform domains
        |--------------------------------------------------------------------------
        */

        if (in_array($host, $this->platformHosts, true)) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Redirect custom/parked domains away from admin
        |--------------------------------------------------------------------------
        */

        $adminUrl = match (app()->environment()) {
            'staging' => 'https://dev.plyrcard.com/admin',
            'production' => 'https://plyrcard.com/admin',
            default => url('/admin'),
        };

        return redirect()->away($adminUrl);
    }
}