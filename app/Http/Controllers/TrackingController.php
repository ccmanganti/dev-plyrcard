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
        return $this->redirectEvent($token, $request, $rewriter, $tracking, 'link_click');
    }

    public function profile(
        string $token,
        Request $request,
        TrackingLinkRewriter $rewriter,
        LocalRecruitingTrackingService $tracking,
    ): RedirectResponse {
        return $this->redirectEvent($token, $request, $rewriter, $tracking, 'profile_view');
    }

    public function open(
        string $token,
        Request $request,
        TrackingLinkRewriter $rewriter,
        LocalRecruitingTrackingService $tracking,
    ): Response {
        $payload = $this->safeDecode($rewriter, $token);
        $event = $tracking->record($payload, $request, 'email_open');

        if (! $event) {
            Log::warning('Recruiting email open was not persisted.', [
                'host' => $request->getHost(),
                'payload_keys' => array_keys($payload),
            ]);
        }

        $gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    protected function redirectEvent(
        string $token,
        Request $request,
        TrackingLinkRewriter $rewriter,
        LocalRecruitingTrackingService $tracking,
        string $eventType,
    ): RedirectResponse {
        $payload = $this->safeDecode($rewriter, $token);
        $destination = trim((string) ($payload['destination_url'] ?? ''));

        if ($destination === '' || ! preg_match('~^https?://~i', $destination)) {
            Log::warning('Recruiting tracking destination is invalid.', [
                'event_type' => $eventType,
                'host' => $request->getHost(),
                'destination' => $destination,
            ]);

            abort(404);
        }

        $payload['event_type'] = $eventType;

        if (empty($payload['platform'])) {
            $payload['platform'] = $rewriter->detectPlatform($destination);
        }

        $record = $tracking->record($payload, $request, $eventType);

        if (! $record) {
            Log::warning('Recruiting tracking redirect continued without a persisted event.', [
                'event_type' => $eventType,
                'platform' => $payload['platform'] ?? null,
                'host' => $request->getHost(),
                'destination' => $destination,
                'athlete_id' => $payload['athlete_id'] ?? null,
                'athlete_email' => $payload['athlete_email'] ?? null,
                'athlete_ghl_contact_id' => $payload['athlete_ghl_contact_id'] ?? null,
            ]);
        }

        return redirect()->away($destination);
    }

    protected function safeDecode(TrackingLinkRewriter $rewriter, string $token): array
    {
        try {
            return $rewriter->decodeToken($token);
        } catch (\Throwable $exception) {
            Log::warning('Recruiting tracking token decode failed.', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}