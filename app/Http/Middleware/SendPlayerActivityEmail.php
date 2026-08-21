<?php

namespace App\Http\Middleware;

use App\Models\Website;
use App\Services\PlayerActivityEmailService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SendPlayerActivityEmail
{
    public function __construct(protected PlayerActivityEmailService $emails) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        // Social redirects in v10.18 send the email inside
        // ExternalSocialTrackingController while the exact click context is
        // still available. This flag prevents a duplicate notification if an
        // old cached route definition still includes this middleware.
        if ($request->attributes->get('plyrcard_activity_email_sent') === true) {
            return $response;
        }

        try {
            $website = $this->resolveWebsite($request);
            if (! $website?->user || (auth()->check() && (int) auth()->id() === (int) $website->user_id)) {
                return $response;
            }

            $platform = strtolower((string) $request->route('platform'));
            if (in_array($platform, ['instagram', 'youtube', 'x'], true)) {
                $this->emails->socialClicked($website->user, $website, $platform, $request);
            } else {
                $this->emails->profileViewed($website->user, $website, $request);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    protected function resolveWebsite(Request $request): ?Website
    {
        if ($slug = trim((string) ($request->route('slug') ?: $request->route('websiteName')))) {
            return Website::query()->where('is_active', true)->with('user')
                ->where(fn ($q) => $q->whereRaw('LOWER(slug) = ?', [strtolower($slug)])->orWhereRaw('LOWER(name) = ?', [strtolower($slug)]))
                ->first();
        }

        $host = strtolower($request->getHost());
        if (in_array($host, ['localhost', '127.0.0.1', 'plyrcard.com', 'www.plyrcard.com', 'dev.plyrcard.com'], true)) {
            return null;
        }
        $plain = Str::startsWith($host, 'www.') ? Str::after($host, 'www.') : $host;
        return Website::query()->where('is_active', true)->with('user')
            ->where(fn ($q) => $q->whereRaw('LOWER(domain) = ?', [$host])->orWhereRaw('LOWER(domain) = ?', [$plain])->orWhereRaw('LOWER(domain) = ?', ['www.'.$plain]))
            ->first();
    }
}