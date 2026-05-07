<?php

namespace App\Http\Controllers;

use App\Models\Website;
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
    ];

    public function home(Request $request, YouTubeChannelService $youtube)
    {
        $host = strtolower($request->getHost());

        /*
        |--------------------------------------------------------------------------
        | Platform domains show the marketing homepage
        |--------------------------------------------------------------------------
        |
        | Localhost should also show the platform homepage.
        | Do not automatically render the first website record locally.
        |
        */

        if ($this->isPlatformHost($host)) {
            return view('pages.index');
        }

        /*
        |--------------------------------------------------------------------------
        | Parked/custom player domains show the matching player website
        |--------------------------------------------------------------------------
        */

        $website = $this->findWebsiteByDomain($host);

        abort_unless($website, 404);

        return $this->renderWebsite($website, $youtube);
    }

    public function preview(Website $website, YouTubeChannelService $youtube)
    {
        $website->load($this->websiteRelations());

        return $this->renderWebsite($website, $youtube);
    }

public function showByName(Request $request, string $websiteName, YouTubeChannelService $youtube)
{
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

    return $this->renderWebsite($website, $youtube);
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