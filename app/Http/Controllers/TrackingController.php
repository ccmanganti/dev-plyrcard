<?php

namespace App\Http\Controllers;

use App\Services\LocalRecruitingTrackingService;
use App\Support\TrackingLinkRewriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    public function click(
        string $token,
        Request $request,
        TrackingLinkRewriter $rewriter,
        LocalRecruitingTrackingService $tracking,
    ): RedirectResponse {
        $payload = $this->safeDecode($rewriter, $token);
        $destination = $this->cleanUrl((string) ($payload['destination_url'] ?? ''));

        if ($destination === '' || ! preg_match('/^https?:\/\//i', $destination)) {
            abort(404);
        }

        $payload['event_type'] = $payload['event_type'] ?? 'link_click';
        $payload['source'] = $payload['source'] ?? 'compose_email';
        $tracking->record($payload, $request, 'link_click');

        return redirect()->away($destination);
    }

    public function profile(
        string $token,
        Request $request,
        TrackingLinkRewriter $rewriter,
        LocalRecruitingTrackingService $tracking,
    ): RedirectResponse {
        $payload = $this->safeDecode($rewriter, $token);
        $destination = $this->cleanUrl((string) ($payload['destination_url'] ?? ''));

        if ($destination === '' || ! preg_match('/^https?:\/\//i', $destination)) {
            abort(404);
        }

        $payload['event_type'] = 'profile_view';
        $payload['platform'] = 'website';
        $payload['source'] = $payload['source'] ?? 'compose_email';

        $event = $tracking->record($payload, $request, 'profile_view');

        if (! $event) {
            Log::warning('Recruiting profile redirect continued without a persisted event.', [
                'destination_url' => $destination,
                'request_host' => $request->getHost(),
                'payload_keys' => array_keys($payload),
            ]);
        }

        // Prevent PublicWebsiteController from recording the redirected request
        // as a second anonymous/direct profile view.
        return redirect()->away($this->appendQuery($destination, [
            'rc_tracked' => '1',
            'rc_source' => 'compose_email',
        ]));
    }

    public function open(
        string $token,
        Request $request,
        TrackingLinkRewriter $rewriter,
        LocalRecruitingTrackingService $tracking,
    ): Response {
        $payload = $this->safeDecode($rewriter, $token);
        $payload['event_type'] = 'email_open';
        $payload['platform'] = 'email';
        $payload['source'] = $payload['source'] ?? 'compose_email';
        $tracking->record($payload, $request, 'email_open');

        $gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    protected function safeDecode(TrackingLinkRewriter $rewriter, string $token): array
    {
        try {
            return $rewriter->decodeToken($token);
        } catch (\Throwable $exception) {
            Log::warning('Recruiting tracking token decode failed.', ['error' => $exception->getMessage()]);
            return [];
        }
    }

    protected function appendQuery(string $url, array $parameters): string
    {
        $fragment = '';
        if (str_contains($url, '#')) {
            [$url, $fragment] = explode('#', $url, 2);
            $fragment = '#' . $fragment;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($parameters) . $fragment;
    }

    protected function cleanUrl(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = preg_replace('/[\x{00A0}\x{2007}\x{202F}\x{200B}\x{FEFF}]+/u', '', $url) ?? $url;
        return trim($url);
    }
}