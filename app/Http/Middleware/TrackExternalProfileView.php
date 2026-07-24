<?php

namespace App\Http\Middleware;

use App\Models\Website;
use App\Services\ExternalTrackingAttributionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackExternalProfileView
{
    public function __construct(
        protected ExternalTrackingAttributionService $tracking
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tracking->shouldTrack($request)) {
            return $next($request);
        }

        $website = $this->resolveWebsite($request);

        if (! $website?->user) {
            Log::warning('External profile tracking skipped: website/player not resolved.', [
                'host' => $request->getHost(),
                'path' => $request->path(),
                'route_website_name' => $request->route('websiteName'),
            ]);

            return $next($request);
        }

        try {
            $event = $this->tracking->recordProfileView($website->user, $request);

            if ($event) {
                // The existing direct tracker sees the same Request object.
                $request->attributes->set('external_tracking_recorded', true);
                $request->attributes->set('external_tracking_event_id', $event->getKey());
            }
        } catch (Throwable $exception) {
            Log::error('External profile tracking failed before page render.', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
                'host' => $request->getHost(),
                'path' => $request->path(),
                'route_website_name' => $request->route('websiteName'),
            ]);

            report($exception);
            // Continue. The normal direct tracker remains available as fallback
            // because external_tracking_recorded was not set.
        }

        return $next($request);
    }

    protected function resolveWebsite(Request $request): ?Website
    {
        $host = strtolower($request->getHost());
        $platformHosts = collect(config('external_tracking.platform_hosts', [
            '127.0.0.1',
            'localhost',
            'dev.plyrcard.com',
            'plyrcard.com',
            'www.plyrcard.com',
        ]))->map(fn ($value) => strtolower(trim((string) $value)));

        if ($platformHosts->contains($host)) {
            $slug = trim((string) ($request->route('websiteName') ?: $request->route('slug')));

            if ($slug === '') {
                return null;
            }

            return Website::query()
                ->whereRaw('LOWER(slug) = ?', [strtolower($slug)])
                ->where('is_active', true)
                ->with('user')
                ->latest('updated_at')
                ->first();
        }

        return Website::query()
            ->where(function ($query) use ($host): void {
                $query->whereRaw('LOWER(domain) = ?', [$host])
                    ->orWhereRaw('LOWER(domain) = ?', ['https://' . $host])
                    ->orWhereRaw('LOWER(domain) = ?', ['http://' . $host]);
            })
            ->where('is_active', true)
            ->with('user')
            ->latest('updated_at')
            ->first();
    }
}