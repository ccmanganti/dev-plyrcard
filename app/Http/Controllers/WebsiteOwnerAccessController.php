<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WebsiteOwnerAccessController extends Controller
{
    /**
     * Main-domain owner probe.
     *
     * A custom player domain cannot read the plyrcard.com auth cookie. This
     * endpoint is intentionally called on APP_URL so it can see the platform
     * session. Only the authenticated owner of this exact Website is bridged.
     */
    public function probe(Request $request, Website $website): RedirectResponse
    {
        $this->ensureWebsiteIsPublic($website);

        if ($request->user() && (int) $request->user()->getKey() === (int) $website->user_id) {
            return $this->redirectToOwnedWebsite($request, $website);
        }

        return redirect()->away($this->websiteUrl($website, ['plyr_owner_checked' => '1']));
    }

    /**
     * Start a short-lived cross-domain owner bridge from PLYRCARD.
     */
    public function redirectToOwnedWebsite(Request $request, Website $website): RedirectResponse
    {
        $this->ensureWebsiteIsPublic($website);

        $user = $request->user();
        abort_unless($user && (int) $user->getKey() === (int) $website->user_id, 403);

        $host = $this->websiteHost($website);

        // Slug/path-hosted websites share the same PLYRCARD session already.
        if ($host === null) {
            return redirect()->to($this->websiteUrl($website));
        }

        $payload = $this->encodePayload([
            'website_id' => (int) $website->getKey(),
            'user_id' => (int) $user->getKey(),
            'host' => $host,
            'expires' => now()->addMinutes(2)->timestamp,
            'nonce' => Str::random(32),
        ]);

        $signature = hash_hmac('sha256', $payload, $this->signingKey());

        $target = 'https://' . $host . '/locker-room/owner-access?' . http_build_query([
            'token' => $payload,
            'signature' => $signature,
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($target);
    }

    /**
     * Consume the bridge on the player's actual custom domain and create a
     * normal Laravel session for that host.
     */
    public function consumeOwnerAccess(Request $request): RedirectResponse
    {
        $payload = (string) $request->query('token', '');
        $signature = (string) $request->query('signature', '');

        abort_if($payload === '' || $signature === '', 403);

        $expected = hash_hmac('sha256', $payload, $this->signingKey());
        abort_unless(hash_equals($expected, $signature), 403);

        $data = $this->decodePayload($payload);
        abort_unless(is_array($data), 403);
        abort_unless((int) ($data['expires'] ?? 0) >= now()->timestamp, 403);

        $website = Website::query()->find((int) ($data['website_id'] ?? 0));
        abort_unless($website, 404);
        $this->ensureWebsiteIsPublic($website);

        $userId = (int) ($data['user_id'] ?? 0);
        abort_unless($userId > 0 && (int) $website->user_id === $userId, 403);

        $expectedHost = $this->websiteHost($website);
        $tokenHost = $this->normalizeHost((string) ($data['host'] ?? ''));
        $requestHost = $this->normalizeHost($request->getHost());

        abort_unless(
            $expectedHost !== null
            && $tokenHost === $expectedHost
            && $requestHost === $expectedHost,
            403
        );

        Auth::loginUsingId($userId, false);
        $request->session()->regenerate();
        $request->session()->put('plyrcard_owner_website_id', (int) $website->getKey());

        return redirect()->away($this->websiteUrl($website));
    }

    protected function ensureWebsiteIsPublic(Website $website): void
    {
        abort_unless((bool) $website->is_active && (bool) $website->is_published, 404);
    }

    protected function websiteUrl(Website $website, array $query = []): string
    {
        $host = $this->websiteHost($website);

        if ($host !== null) {
            $url = 'https://' . $host . '/';
        } elseif (filled($website->slug)) {
            $url = rtrim((string) config('app.url', url('/')), '/') . '/' . ltrim((string) $website->slug, '/');
        } else {
            $slug = Str::slug((string) $website->name);
            $url = rtrim((string) config('app.url', url('/')), '/') . '/' . ltrim($slug, '/');
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    protected function websiteHost(Website $website): ?string
    {
        if (blank($website->domain)) {
            return null;
        }

        $host = $this->normalizeHost((string) $website->domain);
        return $host !== '' ? $host : null;
    }

    protected function normalizeHost(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^https?://#i', '', $value);
        $value = preg_replace('#/.*$#', '', $value);
        $value = preg_replace('/:\d+$/', '', $value);
        return preg_replace('/^www\./i', '', rtrim($value, '/'));
    }

    protected function signingKey(): string
    {
        return (string) config('app.key');
    }

    protected function encodePayload(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    protected function decodePayload(string $payload): ?array
    {
        $padding = strlen($payload) % 4;
        if ($padding > 0) {
            $payload .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);
        return is_array($data) ? $data : null;
    }
}