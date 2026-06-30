<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoHighLevelService;
use App\Support\TrackingLinkRewriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    public function click(string $token, Request $request, TrackingLinkRewriter $rewriter, GoHighLevelService $ghl): RedirectResponse
    {
        $payload = $this->safeDecode($rewriter, $token);
        $destination = trim((string) ($payload['destination_url'] ?? ''));

        if ($destination === '' || ! preg_match('/^https?:\/\//i', $destination)) {
            abort(404);
        }

        $this->track($payload, $request, $ghl, $rewriter, 'link_click');

        return redirect()->away($destination);
    }

    public function open(string $token, Request $request, TrackingLinkRewriter $rewriter, GoHighLevelService $ghl): Response
    {
        $payload = $this->safeDecode($rewriter, $token);
        $this->track($payload, $request, $ghl, $rewriter, 'email_open');

        $gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    protected function track(array $payload, Request $request, GoHighLevelService $ghl, TrackingLinkRewriter $rewriter, string $fallbackEventType): void
    {
        $contactId = trim((string) ($payload['contact_id'] ?? $payload['ghl_contact_id'] ?? ''));
        if ($contactId === '') {
            return;
        }

        $athlete = null;
        if (! empty($payload['athlete_id'])) {
            $athlete = User::query()->find($payload['athlete_id']);
        }

        $destination = (string) ($payload['destination_url'] ?? '');
        $platform = (string) ($payload['platform'] ?? '');
        if ($platform === '' && $destination !== '') {
            $platform = $rewriter->detectPlatform($destination);
        }

        $eventType = (string) ($payload['event_type'] ?? $fallbackEventType);

        try {
            $ghl->incrementTrackingFieldsForUser(
                user: $athlete,
                contactId: $contactId,
                platform: $platform ?: 'website',
                eventType: $eventType,
                metadata: [
                    'destination_url' => $destination,
                    'source' => $payload['source'] ?? null,
                    'subject' => $payload['email_subject'] ?? null,
                    'host' => $request->getHost(),
                    'full_url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                ],
            );
        } catch (\Throwable $exception) {
            Log::warning('PLYRCard tracking skipped.', [
                'contact_id' => $contactId,
                'platform' => $platform,
                'event_type' => $eventType,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function safeDecode(TrackingLinkRewriter $rewriter, string $token): array
    {
        try {
            return $rewriter->decodeToken($token);
        } catch (\Throwable $exception) {
            Log::warning('PLYRCard tracking token decode failed.', ['error' => $exception->getMessage()]);
            return [];
        }
    }
}
