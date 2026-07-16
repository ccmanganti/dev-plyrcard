<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\LocalRecruitingTrackingService;
use App\Services\YouTubeChannelService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicWebsiteController extends Controller
{
    protected array $platformHosts = [
        'localhost',
        '127.0.0.1',
        'dev.plyrcard.com',
        'plyrcard.com',
        'www.plyrcard.com',
    ];

    protected array $reservedPaths = [
        'admin',
        'login',
        'logout',
        'register',
        'password-reset',
        'forgot-password',
        'email-verification',
        'livewire',
        'filament',
        'storage',
        'api',
        'track',
    ];

    public function home(Request $request, YouTubeChannelService $youtube, LocalRecruitingTrackingService $tracking)
    {
        $host = strtolower($request->getHost());

        if ($this->isPlatformHost($host)) {
            return view('pages.index');
        }

        $website = $this->findWebsiteByDomain($host);
        abort_unless($website, 404);

        $this->recordProfileVisit($website, $request, $tracking);

        return $this->renderWebsite($website, $youtube);
    }

    public function preview(Website $website, YouTubeChannelService $youtube)
    {
        $website->load($this->websiteRelations());

        // Preview is an authenticated editing action, not a public profile view.
        return $this->renderWebsite($website, $youtube);
    }

    public function showByName(
        Request $request,
        string $websiteName,
        YouTubeChannelService $youtube,
        LocalRecruitingTrackingService $tracking,
    ) {
        if ($this->isReservedPath($websiteName)) {
            abort(404);
        }

        $normalizedRequestedName = $this->normalizeWebsiteName($websiteName);

        $website = Website::query()
            ->with($this->websiteRelations())
            ->where('is_active', true)
            ->where('is_published', true)
            ->where(function ($query) use ($websiteName, $normalizedRequestedName) {
                $query
                    ->whereRaw('LOWER(slug) = ?', [strtolower($websiteName)])
                    ->orWhereRaw('LOWER(slug) = ?', [$normalizedRequestedName]);
            })
            ->first();

        if (! $website) {
            $website = Website::query()
                ->with($this->websiteRelations())
                ->where('is_active', true)
                ->where('is_published', true)
                ->get()
                ->first(function (Website $website) use ($normalizedRequestedName) {
                    return $this->normalizeWebsiteName($website->name) === $normalizedRequestedName;
                });
        }

        abort_unless($website, 404);

        $this->recordProfileVisit($website, $request, $tracking);

        return $this->renderWebsite($website, $youtube);
    }

    protected function recordProfileVisit(Website $website, Request $request, LocalRecruitingTrackingService $tracking): void
    {
        // A tracked email click records the attributed coach event before redirecting.
        // The redirect adds rc_tracked=1 so the destination request does not create
        // a second anonymous/direct event for the same click.
        if ($request->boolean('rc_tracked')) {
            return;
        }

        $tracking->recordDirectProfileVisit($website, $request);
    }

    protected function findWebsiteByDomain(string $host): ?Website
    {
        $host = strtolower(trim($host));
        $normalizedHost = preg_replace('/^www\./', '', $host);

        return Website::query()
            ->with($this->websiteRelations())
            ->where(function ($query) use ($host, $normalizedHost) {
                $query
                    ->whereRaw('LOWER(domain) = ?', [$host])
                    ->orWhereRaw('LOWER(domain) = ?', [$normalizedHost])
                    ->orWhereRaw("LOWER(REPLACE(domain, 'www.', '')) = ?", [$normalizedHost]);
            })
            ->where('is_active', true)
            ->where('is_published', true)
            ->first();
    }

    protected function renderWebsite(Website $website, YouTubeChannelService $youtube)
    {
        abort_unless(
            $website->siteTemplate && $website->siteTemplate->blade_view,
            404,
            'The website does not have a valid site template.'
        );

        $user = $website->user;

        if ($user) {
            $user->loadMissing([
                'createdSchedules' => fn ($query) => $query
                    ->orderBy('game_date')
                    ->orderBy('game_time'),
            ]);
        }

        $autoHighlightVideos = $user
            ? $youtube->getUserVideos($user, limit: 12, refreshDays: 3)
            : [];

        return view($website->siteTemplate->blade_view, [
            'website' => $website,
            'autoHighlightVideos' => $autoHighlightVideos,
        ]);
    }

    protected function websiteRelations(): array
    {
        return [
            'user.school',
            'user.club',
            'siteTemplate',
            'heroTemplate',
            'fieldValues.templateField',
            'heroFieldValues.templateField',
        ];
    }

    protected function normalizeWebsiteName(?string $value): string
    {
        return Str::slug((string) $value);
    }

    protected function isPlatformHost(string $host): bool
    {
        return in_array(strtolower($host), $this->platformHosts, true);
    }

    protected function isReservedPath(string $path): bool
    {
        return in_array($this->normalizeWebsiteName($path), $this->reservedPaths, true);
    }
}