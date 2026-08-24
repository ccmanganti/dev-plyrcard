<?php

namespace App\Http\Middleware;

use App\Models\Website;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShowPendingPlyrcard
{
    public function handle(Request $request, Closure $next): Response
    {
        $website = $this->pendingWebsiteForRequest($request);

        if (! $website) {
            return $next($request);
        }

        return response()->view('public.plyrcard-preparing', [
            'website' => $website,
            'completeProfileUrl' => $this->completeProfileUrl($request),
        ], 200);
    }

    protected function pendingWebsiteForRequest(Request $request): ?Website
    {
        $query = Website::query()
            ->where('is_active', true)
            ->where('is_published', false);

        $websiteName = trim((string) $request->route('websiteName', ''));

        if ($websiteName !== '') {
            return $query
                ->whereRaw('LOWER(slug) = ?', [strtolower($websiteName)])
                ->first();
        }

        if ($request->path() === '/') {
            $host = strtolower(trim($request->getHost()));
            $bareHost = preg_replace('/^www\./i', '', $host) ?: $host;

            return $query
                ->where(function ($domainQuery) use ($host, $bareHost): void {
                    $domainQuery
                        ->whereRaw('LOWER(domain) = ?', [$host])
                        ->orWhereRaw('LOWER(domain) = ?', [$bareHost])
                        ->orWhereRaw('LOWER(domain) = ?', ['www.' . $bareHost]);
                })
                ->first();
        }

        return null;
    }

    protected function completeProfileUrl(Request $request): string
    {
        $host = strtolower(trim($request->getHost()));
        $isPlyrcardHost = $host === 'plyrcard.com'
            || $host === 'www.plyrcard.com'
            || str_ends_with($host, '.plyrcard.com');
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if ($isPlyrcardHost || $isLocal) {
            return rtrim($request->getSchemeAndHttpHost(), '/') . '/admin/my-profile';
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        return ($appUrl !== '' ? $appUrl : $request->getSchemeAndHttpHost()) . '/admin/my-profile';
    }
}