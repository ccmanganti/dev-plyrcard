<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\YouTubeChannelService;
use Illuminate\Http\Request;

class PublicWebsiteController extends Controller
{
    public function home(Request $request, YouTubeChannelService $youtube)
    {
        $host = strtolower($request->getHost());
        $normalizedHost = preg_replace('/^www\./', '', $host);

        $platformHosts = [
            'dev.plyrcard.com',
            'plyrcard.com',
            'www.plyrcard.com',
        ];

        if (in_array($host, $platformHosts, true)) {
            return redirect('/admin');
        }

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $website = Website::query()
                ->with([
                    'user.school',
                    'user.club.league',
                    'siteTemplate',
                    'heroTemplate',
                    'fieldValues.templateField',
                    'heroFieldValues.templateField',
                ])
                ->orderBy('id')
                ->first();

            abort_unless($website, 404, 'No website record found.');

            abort_unless(
                $website->siteTemplate && $website->siteTemplate->blade_view,
                404,
                'The website does not have a valid site template.'
            );

            $autoHighlightVideos = $website->user
                ? $youtube->getUserVideos($website->user, limit: 12, refreshDays: 3)
                : [];

            return view($website->siteTemplate->blade_view, [
                'website' => $website,
                'autoHighlightVideos' => $autoHighlightVideos,
            ]);
        }

        $website = Website::query()
            ->whereHas('user', function ($query) use ($host, $normalizedHost) {
                $query->where(function ($subQuery) use ($host, $normalizedHost) {
                    $subQuery
                        ->whereRaw('LOWER(domain) = ?', [$host])
                        ->orWhereRaw('LOWER(domain) = ?', [$normalizedHost])
                        ->orWhereRaw("LOWER(REPLACE(domain, 'www.', '')) = ?", [$normalizedHost]);
                });
            })
            ->with([
                'user.school',
                'user.club.league',
                'siteTemplate',
                'heroTemplate',
                'fieldValues.templateField',
                'heroFieldValues.templateField',
            ])
            ->first();

        if (! $website) {
            abort(404);
        }

        if (! $website->siteTemplate || ! $website->siteTemplate->blade_view) {
            abort(404, 'The website does not have a valid site template.');
        }

        $autoHighlightVideos = $website->user
            ? $youtube->getUserVideos($website->user, limit: 12, refreshDays: 3)
            : [];

        return view($website->siteTemplate->blade_view, [
            'website' => $website,
            'autoHighlightVideos' => $autoHighlightVideos,
        ]);
    }

    public function preview(Website $website, YouTubeChannelService $youtube)
    {
        $website->load([
            'user.school',
            'user.club.league',
            'siteTemplate',
            'heroTemplate',
            'fieldValues.templateField',
            'heroFieldValues.templateField',
        ]);

        abort_unless(
            $website->siteTemplate && $website->siteTemplate->blade_view,
            404,
            'The website does not have a valid site template.'
        );

        $autoHighlightVideos = $website->user
            ? $youtube->getUserVideos($website->user, limit: 12, refreshDays: 3)
            : [];

        return view($website->siteTemplate->blade_view, [
            'website' => $website,
            'autoHighlightVideos' => $autoHighlightVideos,
        ]);
    }
}