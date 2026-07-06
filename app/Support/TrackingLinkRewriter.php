<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TrackingLinkRewriter
{
    public function rewriteHtml(string $html, array $context = []): string
    {
        if (trim($html) === '') {
            return $html;
        }

        return $this->rewriteAnchorHrefs($html, $context) ?: $html;
    }

    public function appendOpenPixel(string $html, array $context = []): string
    {
        if (stripos($html, 'data-recruiting-open-pixel') !== false || stripos($html, '/track/open/') !== false) {
            return $html;
        }

        $contactId = trim((string) ($context['contact_id'] ?? $context['ghl_contact_id'] ?? ''));
        if ($contactId === '') {
            return $html;
        }

        $token = $this->makeToken(array_merge($context, [
            'event_type' => 'email_open',
            'platform' => 'email',
            'destination_url' => null,
        ]));

        $src = rtrim($this->trackingBaseUrl(), '/') . '/track/open/' . $token . '.gif';
        // Do not use display:none. Some mail clients/proxies skip hidden images,
        // which prevents the open request from ever reaching /track/open.
        $pixel = '<img data-recruiting-open-pixel="1" src="' . e($src) . '" width="1" height="1" alt="" style="width:1px;height:1px;max-width:1px;max-height:1px;overflow:hidden;border:0;outline:none;text-decoration:none;" />';

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $pixel . '</body>', $html, 1) ?: ($html . $pixel);
        }

        return $html . $pixel;
    }

    public function trackedUrl(string $destinationUrl, array $context = []): string
    {
        $destinationUrl = trim($destinationUrl);

        if ($destinationUrl === '' || $this->shouldSkipUrl($destinationUrl)) {
            return $destinationUrl;
        }

        $contactId = trim((string) ($context['contact_id'] ?? $context['ghl_contact_id'] ?? ''));
        if ($contactId === '') {
            return $destinationUrl;
        }

        if (($context['event_type'] ?? null) === 'profile_view' || $this->isProfileDestinationUrl($destinationUrl, $context)) {
            return $this->trackedProfileUrl($destinationUrl, $context);
        }

        $payload = array_merge($context, [
            'event_type' => $context['event_type'] ?? 'link_click',
            'platform' => $context['platform'] ?? $this->detectPlatform($destinationUrl),
            'destination_url' => $destinationUrl,
        ]);

        return rtrim($this->trackingBaseUrl(), '/') . '/track/click/' . $this->makeToken($payload);
    }

    public function trackedProfileUrl(string $profileUrl, array $context = []): string
    {
        $profileUrl = trim($profileUrl);

        if ($profileUrl === '' || $this->shouldSkipUrl($profileUrl)) {
            return $profileUrl;
        }

        $contactId = trim((string) ($context['contact_id'] ?? $context['ghl_contact_id'] ?? ''));
        if ($contactId === '') {
            return $profileUrl;
        }

        $payload = array_merge($context, [
            'event_type' => 'profile_view',
            'platform' => $context['platform'] ?? $this->detectPlatform($profileUrl),
            'source' => $context['source'] ?? 'profile_tracking_link',
            'destination_url' => $profileUrl,
        ]);

        return rtrim($this->trackingBaseUrl(), '/') . '/track/profile/' . $this->makeToken($payload);
    }


    /**
     * Adds the athlete signature, wraps all web/social/profile links, then appends
     * the open pixel. Use this as the final email-body preparation step before send.
     */
    public function prepareTrackedEmailHtml(string $html, $user = null, array $context = []): string
    {
        $html = $this->appendTrackedSignature($html, $user, $context);
        $html = $this->rewriteHtml($html, $context);

        return $this->appendOpenPixel($html, $context);
    }

    /**
     * Signature links are intentionally inserted as normal links first; rewriteHtml()
     * converts the website/social links into /track/click/{token} links afterwards.
     */
    public function appendTrackedSignature(string $html, $user = null, array $context = []): string
    {
        if (stripos($html, 'data-recruiting-signature') !== false) {
            return $html;
        }

        $links = $this->signatureLinks($user, $context);
        $email = $this->signatureValue($user, $context, ['athlete_email', 'reply_to_email', 'from_email', 'email'], ['email']);
        $name = $this->signatureValue($user, $context, ['athlete_name', 'from_name', 'sender_name', 'name'], ['name']);

        if (empty($links) && $email === '' && $name === '') {
            return $html;
        }

        $parts = [];
        if ($name !== '') {
            $parts[] = '<div style="font-weight:700;color:#111827;margin-bottom:4px;">' . e($name) . '</div>';
        }

        if (! empty($links)) {
            $linkHtml = [];
            foreach ($links as $link) {
                $linkHtml[] = '<a href="' . e($link['url']) . '" target="_blank" rel="noopener noreferrer" style="color:#92400e;text-decoration:none;font-weight:600;">' . e($link['label']) . '</a>';
            }
            $parts[] = '<div style="margin-top:4px;">' . implode('<span style="color:#d1d5db;margin:0 8px;">|</span>', $linkHtml) . '</div>';
        }

        if ($email !== '') {
            $parts[] = '<div style="margin-top:6px;color:#4b5563;">Email: <a href="mailto:' . e($email) . '" style="color:#92400e;text-decoration:none;">' . e($email) . '</a></div>';
        }

        $signature = '<div data-recruiting-signature="1" style="margin-top:28px;padding-top:16px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:#374151;">'
            . implode('', $parts)
            . '</div>';

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $signature . '</body>', $html, 1) ?: ($html . $signature);
        }

        return $html . $signature;
    }

    public function decodeToken(string $token): array
    {
        $token = trim($token);

        if (str_contains($token, '.')) {
            [$encodedPayload, $signature] = array_pad(explode('.', $token, 2), 2, '');
            $secret = $this->trackingSharedSecret();

            if ($secret !== '' && $encodedPayload !== '' && $signature !== '') {
                $expected = hash_hmac('sha256', $encodedPayload, $secret);

                if (hash_equals($expected, $signature)) {
                    $json = $this->base64UrlDecode($encodedPayload);
                    $payload = json_decode($json, true);

                    return is_array($payload) ? $payload : [];
                }
            }

            return [];
        }

        $padded = strtr($token, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        $encrypted = base64_decode($padded, true);
        if (! is_string($encrypted) || $encrypted === '') {
            return [];
        }

        $json = Crypt::decryptString($encrypted);
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    }

    public function detectPlatform(?string $url): string
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: '';

        return match (true) {
            str_contains($host, 'instagram.com') => 'instagram',
            str_contains($host, 'youtube.com'), str_contains($host, 'youtu.be') => 'youtube',
            $host === 'x.com', str_ends_with($host, '.x.com'), str_contains($host, 'twitter.com') => 'x',
            default => 'website',
        };
    }


    protected function signatureLinks($user, array $context): array
    {
        $candidates = [
            'Website' => [
                $this->signatureValue($user, $context, ['website_url', 'profile_url', 'public_profile_url', 'athlete_profile_url', 'plyrcard_url'], ['website_url', 'profile_url', 'public_url', 'url']),
            ],
            'Instagram' => [
                $this->signatureValue($user, $context, ['instagram_url', 'instagram'], ['instagram_url', 'instagram', 'instagram_handle']),
            ],
            'YouTube' => [
                $this->signatureValue($user, $context, ['youtube_url', 'youtube'], ['youtube_url', 'youtube', 'youtube_channel']),
            ],
            'X' => [
                $this->signatureValue($user, $context, ['x_url', 'twitter_url', 'x', 'twitter'], ['x_url', 'twitter_url', 'x', 'twitter', 'twitter_handle']),
            ],
        ];

        $links = [];
        foreach ($candidates as $label => $values) {
            foreach ($values as $value) {
                $url = $this->normalizeSignatureUrl((string) $value, $label);
                if ($url === '') {
                    continue;
                }

                $links[] = ['label' => $label, 'url' => $url];
                break;
            }
        }

        $seen = [];
        return array_values(array_filter($links, function (array $link) use (&$seen): bool {
            $key = strtolower($link['label'] . '|' . $link['url']);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));
    }

    protected function signatureValue($user, array $context, array $contextKeys, array $userKeys): string
    {
        foreach ($contextKeys as $key) {
            $value = $context[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        if ($user) {
            foreach ($userKeys as $key) {
                $value = data_get($user, $key);
                if (is_scalar($value) && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    protected function normalizeSignatureUrl(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        if (Str::startsWith($value, '/')) {
            $base = config('app.url') ?: request()?->getSchemeAndHttpHost();
            return rtrim((string) $base, '/') . $value;
        }

        $handle = ltrim($value, '@');
        $lowerLabel = strtolower($label);

        if ($lowerLabel === 'instagram' && ! str_contains($handle, '.')) {
            return 'https://instagram.com/' . $handle;
        }

        if ($lowerLabel === 'x' && ! str_contains($handle, '.')) {
            return 'https://x.com/' . $handle;
        }

        if ($lowerLabel === 'youtube' && ! str_contains($handle, '.')) {
            return 'https://youtube.com/@' . $handle;
        }

        if (str_contains($value, '.')) {
            return 'https://' . $value;
        }

        return '';
    }

    protected function rewriteAnchorHrefs(string $html, array $context): string
    {
        return preg_replace_callback('/(<a\b[^>]*?\bhref=["\'])([^"\']+)(["\'][^>]*>)/i', function (array $matches) use ($context): string {
            $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($this->shouldSkipUrl($url)) {
                return $matches[0];
            }

            return $matches[1] . e($this->trackedUrl($url, $context)) . $matches[3];
        }, $html) ?: $html;
    }

    protected function shouldSkipUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return true;
        }

        $lower = strtolower($url);

        if (Str::startsWith($lower, ['#', 'mailto:', 'tel:', 'sms:', 'javascript:', 'data:'])) {
            return true;
        }

        if (str_contains($lower, '/track/click/') || str_contains($lower, '/track/open/') || str_contains($lower, '/track/profile/')) {
            return true;
        }

        return ! Str::startsWith($lower, ['http://', 'https://']);
    }

    protected function makeToken(array $payload): string
    {
        $payload = array_merge([
            'issued_at' => now()->toIso8601String(),
            'tracking_host' => request()?->getHost(),
        ], $payload);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $secret = $this->trackingSharedSecret();

        if ($secret !== '') {
            $encodedPayload = $this->base64UrlEncode($json);
            return $encodedPayload . '.' . hash_hmac('sha256', $encodedPayload, $secret);
        }

        $encrypted = Crypt::encryptString($json);

        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }


    protected function isProfileDestinationUrl(string $url, array $context = []): bool
    {
        if (empty($context['athlete_id']) && empty($context['profile_url']) && empty($context['public_profile_url']) && empty($context['athlete_profile_url']) && empty($context['plyrcard_url'])) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if ($host === '') {
            return false;
        }

        $knownHosts = collect([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('services.tracking.base_url'), PHP_URL_HOST),
            parse_url((string) env('PLYRCARD_TRACKING_BASE_URL'), PHP_URL_HOST),
            parse_url((string) env('TRACKING_BASE_URL'), PHP_URL_HOST),
            'plyrcard.com',
            'dev.plyrcard.com',
        ])->filter()->map(fn ($value): string => preg_replace('/^www\./', '', strtolower((string) $value)))->unique()->all();

        $host = preg_replace('/^www\./', '', $host) ?: $host;

        if (! in_array($host, $knownHosts, true)) {
            return false;
        }

        if (str_contains($path, '/track/')) {
            return false;
        }

        foreach (['profile_url', 'public_profile_url', 'athlete_profile_url', 'plyrcard_url', 'website_url'] as $key) {
            $profileUrl = trim((string) ($context[$key] ?? ''));
            if ($profileUrl !== '' && rtrim($profileUrl, '/') === rtrim($url, '/')) {
                return true;
            }
        }

        return $path !== '' && ! str_contains($path, '/admin');
    }

    protected function trackingSharedSecret(): string
    {
        return trim((string) (
            config('services.tracking.shared_secret')
            ?: config('app.tracking_shared_secret')
            ?: env('PLYRCARD_TRACKING_TOKEN_SECRET')
            ?: env('TRACKING_TOKEN_SECRET')
        ));
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        return (string) base64_decode($padded, true);
    }

    protected function trackingBaseUrl(): string
    {
        return rtrim((string) (
            config('services.tracking.base_url')
            ?: config('app.tracking_base_url')
            ?: env('PLYRCARD_TRACKING_BASE_URL')
            ?: env('TRACKING_BASE_URL')
            ?: config('app.url')
            ?: request()?->getSchemeAndHttpHost()
        ), '/');
    }
}