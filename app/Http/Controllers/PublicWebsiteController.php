<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\YouTubeChannelService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicWebsiteController extends Controller
{
    protected array $platformHosts = [
        'dev.plyrcard.com',
        'plyrcard.com',
        'www.plyrcard.com',
    ];

    public function home(Request $request, YouTubeChannelService $youtube)
    {
        $host = strtolower($request->getHost());

        // Main platform domains show marketing homepage.
        if (in_array($host, $this->platformHosts, true)) {
            return view('pages.index');
        }

        // Local fallback for preview/testing.
        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $website = Website::query()
                ->with([
                    'user.school',
                    'user.club',
                    'siteTemplate',
                    'heroTemplate',
                    'fieldValues.templateField',
                    'heroFieldValues.templateField',
                ])
                ->orderBy('id')
                ->first();

            abort_unless($website, 404, 'No website record found.');

            return $this->renderWebsite($website, $youtube);
        }

        // Parked/custom player domains show the matching player website.
        $website = $this->findWebsiteByDomain($host);

        abort_unless($website, 404);

        return $this->renderWebsite($website, $youtube);
    }

    public function preview(Website $website, YouTubeChannelService $youtube)
    {
        $website->load($this->websiteRelations());

        return $this->renderWebsite($website, $youtube);
    }

    public function showByName(string $websiteName, YouTubeChannelService $youtube)
    {
        $normalizedRequestedName = $this->normalizeWebsiteName($websiteName);

        $website = Website::query()
            ->with($this->websiteRelations())
            ->where('is_active', true)
            ->where('is_published', true)
            ->get()
            ->first(function (Website $website) use ($normalizedRequestedName) {
                return $this->normalizeWebsiteName($website->name) === $normalizedRequestedName;
            });

        abort_unless($website, 404);

        return $this->renderWebsite($website, $youtube);
    }

    protected function findWebsiteByDomain(string $host): ?Website
    {
        $normalizedHost = preg_replace('/^www\./', '', $host);

        return Website::query()
            ->whereHas('user', function ($query) use ($host, $normalizedHost) {
                $query->where(function ($subQuery) use ($host, $normalizedHost) {
                    $subQuery
                        ->whereRaw('LOWER(domain) = ?', [$host])
                        ->orWhereRaw('LOWER(domain) = ?', [$normalizedHost])
                        ->orWhereRaw("LOWER(REPLACE(domain, 'www.', '')) = ?", [$normalizedHost]);
                });
            })
            ->with($this->websiteRelations())
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
}