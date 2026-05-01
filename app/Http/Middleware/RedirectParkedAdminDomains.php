<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectParkedAdminDomains
{
    protected array $platformHosts = [
        'dev.plyrcard.com',
        'plyrcard.com',
        'www.plyrcard.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if (! in_array($host, $this->platformHosts, true)) {
            return redirect()->away('https://plyrcard.com/admin');
        }

        return $next($request);
    }
}