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
    public function click(string $token, Request $request, TrackingLinkRewriter $rewriter, GoHighLevelService $goHighLevelService): RedirectResponse
    {
        $payload = $this->safeDecode($rewriter, $token);
        $destination = trim((string) ($payload['destination_url'] ?? ''));

        if ($destination === '' || ! preg_match('/^https?:\/\//i', $destination)) {
            abort(404);
        }

        $this->track($payload, $request, $rewriter, $goHighLevelService, 'link_click');

        return redirect()->away($destination);
    }

    public function profile(string $token, Request $request, TrackingLinkRewriter $rewriter, GoHighLevelService $goHighLevelService): RedirectResponse
    {
        $payload = $this->safeDecode($rewriter, $token);
        $destination = trim((string) ($payload['destination_url'] ?? ''));

        if ($destination === '' || ! preg_match('/^https?:\/\//i', $destination)) {
            abort(404);
        }

        $payload['event_type'] = 'profile_view';
        $payload['source'] = $payload['source'] ?? 'profile_tracking_link';

        $this->track($payload, $request, $rewriter, $goHighLevelService, 'profile_view');

        return redirect()->away($destination);
    }

    public function open(string $token, Request $request, TrackingLinkRewriter $rewriter, GoHighLevelService $goHighLevelService): Response
    {
        $payload = $this->safeDecode($rewriter, $token);
        $this->track($payload, $request, $rewriter, $goHighLevelService, 'email_open');

        $gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    protected function track(array $payload, Request $request, TrackingLinkRewriter $rewriter, GoHighLevelService $goHighLevelService, string $fallbackEventType): void
    {
        $contactId = trim((string) ($payload['contact_id'] ?? $payload['ghl_contact_id'] ?? ''));
        if ($contactId === '') {
            Log::warning('Recruiting tracking skipped because contact id is missing.', ['payload_keys' => array_keys($payload)]);
            return;
        }

        $user = null;
        if (! empty($payload['athlete_id'])) {
            $user = User::query()->find($payload['athlete_id']);
        }

        $destination = (string) ($payload['destination_url'] ?? '');
        $platform = (string) ($payload['platform'] ?? '');
        if ($platform === '' && $destination !== '') {
            $platform = $rewriter->detectPlatform($destination);
        }

        $eventType = (string) ($payload['event_type'] ?? $fallbackEventType);

        try {
            $metadata = [
                'contact_id' => $contactId,
                'ghl_contact_id' => $contactId,
                'business_id' => $payload['business_id'] ?? $payload['ghl_business_id'] ?? $payload['company_id'] ?? null,
                'ghl_business_id' => $payload['ghl_business_id'] ?? $payload['business_id'] ?? $payload['company_id'] ?? null,
                'coach_name' => $payload['coach_name'] ?? $payload['contact_name'] ?? null,
                'coach_email' => $payload['coach_email'] ?? $payload['contact_email'] ?? null,
                'school' => $payload['school'] ?? $payload['school_name'] ?? $payload['company_name'] ?? null,
                'school_name' => $payload['school_name'] ?? $payload['school'] ?? $payload['company_name'] ?? null,
                'school_logo_url' => $payload['school_logo_url'] ?? $payload['business_logo_url'] ?? $payload['logo_url'] ?? null,
                'destination_url' => $destination,
                'source' => $payload['source'] ?? 'tracked_link',
                'subject' => $payload['email_subject'] ?? null,
                'email_subject' => $payload['email_subject'] ?? null,
                'host' => $request->getHost(),
                'full_url' => $request->fullUrl(),
                'referrer' => $request->headers->get('referer'),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'occurred_at' => now()->toIso8601String(),
                'tracking_signature_valid' => $payload['_tracking_signature_valid'] ?? null,
            ];

            $result = $goHighLevelService->trackRecruitingEventForUser(
                user: $user,
                contactId: $contactId,
                platform: $platform ?: 'website',
                eventType: $eventType,
                metadata: array_filter($metadata, fn ($value): bool => ! is_null($value) && $value !== ''),
            );

            Log::info('Recruiting tracking processed.', [
                'contact_id' => $contactId,
                'platform' => $platform,
                'event_type' => $eventType,
                'success' => (bool) ($result['success'] ?? false),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Recruiting tracking failed.', [
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
            Log::warning('Recruiting tracking token decode failed.', ['error' => $exception->getMessage()]);
            return [];
        }
    }
}