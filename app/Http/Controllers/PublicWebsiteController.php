<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\YouTubeChannelService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicWebsiteController extends Controller
{
    public function home(Request $request, YouTubeChannelService $youtube)
    {
        $host = strtolower($request->getHost());

        // ONLY redirect dev domain to admin
        if ($host === 'dev.plyrcard.com') {
            return redirect('/admin');
        }

        // 👉 MAIN DOMAIN → show marketing homepage
        if (in_array($host, ['plyrcard.com', 'www.plyrcard.com'], true)) {
            return view('pages.index');
        }

        // fallback (localhost or custom domains)
        return abort(404);
    }

    public function preview(Website $website, YouTubeChannelService $youtube)
    {
        $website->load([
            'user.school',
            'user.club',
            'siteTemplate',
            'heroTemplate',
            'fieldValues.templateField',
            'heroFieldValues.templateField',
        ]);

        return $this->renderWebsite($website, $youtube);
    }

    public function showByName(string $websiteName, YouTubeChannelService $youtube)
    {
        $normalizedRequestedName = $this->normalizeWebsiteName($websiteName);

        $website = Website::query()
            ->with([
                'user.school',
                'user.club',
                'siteTemplate',
                'heroTemplate',
                'fieldValues.templateField',
                'heroFieldValues.templateField',
            ])
            ->where('is_active', true)
            ->where('is_published', true)
            ->get()
            ->first(function (Website $website) use ($normalizedRequestedName) {
                return $this->normalizeWebsiteName($website->name) === $normalizedRequestedName;
            });

        if (! $website) {
            abort(404);
        }

        return $this->renderWebsite($website, $youtube);
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

    protected function normalizeWebsiteName(?string $value): string
    {
        return Str::slug((string) $value);
    }
}