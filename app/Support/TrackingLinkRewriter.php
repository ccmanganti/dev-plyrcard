<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TrackingLinkRewriter
{
    /**
     * Keep tracking URLs short and portable across localhost, dev, and production.
     * The token is signed, not Laravel-Crypt encrypted.
     * Tokens are signed with the athlete/account-specific secret whenever athlete_id
     * is present, so links can be generated from local/dev/production without a single
     * shared fixed tracking secret.
     */
    protected array $compactPayloadMap = [
        'event_type' => 'e',
        'destination_url' => 'd',
        'contact_id' => 'c',
        'ghl_contact_id' => 'c',
        'athlete_id' => 'a',
        'athlete_email' => 'ae',
        'athlete_ghl_location_id' => 'al',
        'athlete_ghl_contact_id' => 'ac',
        'platform' => 'p',
        'source' => 's',
        'business_id' => 'b',
        'ghl_business_id' => 'b',
        'company_id' => 'b',
        'school' => 'sc',
        'school_name' => 'sc',
        'company_name' => 'sc',
        'school_logo_url' => 'sl',
        'business_logo_url' => 'sl',
        'logo_url' => 'sl',
        'coach_name' => 'cn',
        'contact_name' => 'cn',
        'coach_email' => 'ce',
        'contact_email' => 'ce',
        'email_subject' => 'sj',
        'issued_at' => 'iat',
        'tracking_host' => 'h',
    ];

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
        $context = $this->trackingContextForUser($user, $context);

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

        if ($token === '') {
            return [];
        }

        // New portable token format: base64url(compact-json)~hmac.
        // Also accept the earlier dot separator for compatibility.
        if (str_contains($token, '~') || str_contains($token, '.')) {
            $separator = str_contains($token, '~') ? '~' : '.';
            [$encodedPayload, $signature] = array_pad(explode($separator, $token, 2), 2, '');
            if ($encodedPayload === '' || $signature === '') {
                return [];
            }

            $json = $this->base64UrlDecode($encodedPayload);
            $payload = json_decode($json, true);

            if (! is_array($payload)) {
                return [];
            }

            $expandedPayload = $this->expandCompactPayload($payload);
            $secret = $this->trackingSecretForPayload($expandedPayload);

            if ($secret === '') {
                return [];
            }

            $expected = hash_hmac('sha256', $encodedPayload, $secret);

            return hash_equals($expected, $signature) ? $expandedPayload : [];
        }

        // Legacy fallback for old Laravel-Crypt tokens. New links should not use this
        // because those tokens are long and tied to the APP_KEY of the environment that
        // generated them.
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
                $this->signatureValue($user, $context, ['instagram_url', 'instagram', 'ig_handle'], ['ig_handle', 'instagram_url', 'instagram', 'instagram_handle']),
            ],
            'YouTube' => [
                $this->signatureValue($user, $context, ['youtube_url', 'youtube', 'yt_url'], ['yt_url', 'youtube_url', 'youtube', 'youtube_channel', 'featured_video_url']),
            ],
            'X' => [
                $this->signatureValue($user, $context, ['x_url', 'twitter_url', 'x', 'twitter', 'x_handle'], ['x_handle', 'x_url', 'twitter_url', 'x', 'twitter', 'twitter_handle']),
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

        $secret = $this->trackingSecretForPayload($payload);
        $payload = $this->compactTokenPayload($payload);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($secret === '') {
            $secret = hash('sha256', 'plyrcard-tracking-fallback|' . (string) config('app.url'));
        }

        $encodedPayload = $this->base64UrlEncode($json);

        return $encodedPayload . '~' . hash_hmac('sha256', $encodedPayload, $secret);
    }

    protected function compactTokenPayload(array $payload): array
    {
        $compact = [];

        foreach ($payload as $key => $value) {
            if (is_null($value) || $value === '' || is_array($value) || is_object($value)) {
                continue;
            }

            $alias = $this->compactPayloadMap[$key] ?? null;
            if (! $alias) {
                continue;
            }

            if ($alias === 'c' && isset($compact['c'])) {
                continue;
            }

            if ($alias === 'b' && isset($compact['b'])) {
                continue;
            }

            if ($alias === 'sc' && isset($compact['sc'])) {
                continue;
            }

            if ($alias === 'sl' && isset($compact['sl'])) {
                continue;
            }

            $compact[$alias] = (string) $value;
        }

        return $compact;
    }

    protected function expandCompactPayload(array $payload): array
    {
        $expanded = $payload;

        $aliases = [
            'e' => 'event_type',
            'd' => 'destination_url',
            'c' => 'contact_id',
            'a' => 'athlete_id',
            'ae' => 'athlete_email',
            'al' => 'athlete_ghl_location_id',
            'ac' => 'athlete_ghl_contact_id',
            'p' => 'platform',
            's' => 'source',
            'b' => 'business_id',
            'sc' => 'school',
            'sl' => 'school_logo_url',
            'cn' => 'coach_name',
            'ce' => 'coach_email',
            'sj' => 'email_subject',
            'iat' => 'issued_at',
            'h' => 'tracking_host',
        ];

        foreach ($aliases as $alias => $fullKey) {
            if (array_key_exists($alias, $payload) && ! array_key_exists($fullKey, $expanded)) {
                $expanded[$fullKey] = $payload[$alias];
            }
        }

        if (! empty($expanded['contact_id']) && empty($expanded['ghl_contact_id'])) {
            $expanded['ghl_contact_id'] = $expanded['contact_id'];
        }

        if (! empty($expanded['business_id']) && empty($expanded['ghl_business_id'])) {
            $expanded['ghl_business_id'] = $expanded['business_id'];
        }

        if (! empty($expanded['school']) && empty($expanded['school_name'])) {
            $expanded['school_name'] = $expanded['school'];
        }

        if (! empty($expanded['school_logo_url']) && empty($expanded['business_logo_url'])) {
            $expanded['business_logo_url'] = $expanded['school_logo_url'];
        }

        return $expanded;
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

    protected function trackingSecretForPayload(array $payload): string
    {
        $user = $this->trackingUserFromPayload($payload);

        if ($user) {
            return $this->trackingSecretForUser($user);
        }

        return $this->trackingFallbackSecret();
    }

    protected function trackingUserFromPayload(array $payload): ?User
    {
        $athleteId = trim((string) ($payload['athlete_id'] ?? $payload['a'] ?? ''));

        if ($athleteId !== '' && ctype_digit($athleteId)) {
            $user = User::query()->find((int) $athleteId);
            if ($user) {
                return $user;
            }
        }

        $athleteGhlContactId = trim((string) ($payload['athlete_ghl_contact_id'] ?? $payload['ac'] ?? ''));
        if ($athleteGhlContactId !== '') {
            $user = User::query()
                ->where('ghl_contact_id', $athleteGhlContactId)
                ->first();

            if ($user) {
                return $user;
            }
        }

        $athleteEmail = strtolower(trim((string) ($payload['athlete_email'] ?? $payload['ae'] ?? '')));
        if ($athleteEmail !== '') {
            $user = User::query()
                ->whereRaw('lower(email) = ?', [$athleteEmail])
                ->orWhereRaw('lower(personal_email) = ?', [$athleteEmail])
                ->first();

            if ($user) {
                return $user;
            }
        }

        $athleteLocationId = trim((string) ($payload['athlete_ghl_location_id'] ?? $payload['al'] ?? ''));
        if ($athleteLocationId !== '') {
            $user = User::query()
                ->where('ghl_location_id', $athleteLocationId)
                ->first();

            if ($user) {
                return $user;
            }
        }

        return null;
    }

    protected function trackingSecretForUser(User $user): string
    {
        // Stable across localhost/dev/production when the same PLYRCARD user account
        // is connected to the same GHL account. Do not use APP_KEY, password hash,
        // created_at, or database-only values here because those can differ per env.
        $parts = [
            'plyrcard-account-tracking-v3',
            strtolower((string) ($user->email ?? '')),
            strtolower((string) ($user->personal_email ?? '')),
            (string) ($user->ghl_location_id ?? ''),
            (string) ($user->ghl_contact_id ?? ''),
        ];

        return hash('sha256', implode('|', $parts));
    }

    protected function trackingContextForUser($user, array $context = []): array
    {
        if (! $user) {
            return $context;
        }

        $context['athlete_id'] = $context['athlete_id'] ?? data_get($user, 'id');
        $context['athlete_email'] = $context['athlete_email'] ?? data_get($user, 'email') ?? data_get($user, 'personal_email');
        $context['athlete_ghl_location_id'] = $context['athlete_ghl_location_id'] ?? data_get($user, 'ghl_location_id');
        $context['athlete_ghl_contact_id'] = $context['athlete_ghl_contact_id'] ?? data_get($user, 'ghl_contact_id');
        $context['athlete_name'] = $context['athlete_name'] ?? trim((string) data_get($user, 'first_name') . ' ' . (string) data_get($user, 'last_name'));

        $context['instagram'] = $context['instagram'] ?? data_get($user, 'ig_handle');
        $context['x'] = $context['x'] ?? data_get($user, 'x_handle');
        $context['youtube'] = $context['youtube'] ?? data_get($user, 'yt_url');
        $context['youtube_url'] = $context['youtube_url'] ?? data_get($user, 'yt_url');

        if (empty($context['profile_url'])) {
            $activeWebsite = data_get($user, 'activeWebsite');
            $websiteName = data_get($activeWebsite, 'website_name')
                ?: data_get($activeWebsite, 'slug')
                ?: data_get($activeWebsite, 'name');

            if ($websiteName) {
                $context['profile_url'] = rtrim($this->publicProfileBaseUrl(), '/') . '/' . ltrim((string) $websiteName, '/');
            }
        }

        return $context;
    }

    protected function trackingFallbackSecret(): string
    {
        // Only used for legacy/generic tokens that do not carry athlete_id.
        // Normal recruiting links should include athlete_id and use the account secret.
        return trim((string) (
            config('services.tracking.shared_secret')
            ?: config('app.tracking_shared_secret')
            ?: env('PLYRCARD_TRACKING_TOKEN_SECRET')
            ?: env('TRACKING_TOKEN_SECRET')
            ?: config('app.key')
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
        // Explicit env/config always wins when you want all generated links to resolve
        // through dev.plyrcard.com or plyrcard.com.
        $configured = trim((string) (
            config('services.tracking.base_url')
            ?: config('app.tracking_base_url')
            ?: env('PLYRCARD_TRACKING_BASE_URL')
            ?: env('TRACKING_BASE_URL')
        ));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $requestHost = request()?->getSchemeAndHttpHost();
        $host = strtolower((string) request()?->getHost());

        // Localhost/127 links inside emails are not reachable by coaches. When emails
        // are sent from local and no explicit tracking base URL is configured, default
        // to dev so the tracking redirect can still execute and record to GHL.
        if ($host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return 'https://dev.plyrcard.com';
        }

        if ($requestHost) {
            return rtrim($requestHost, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    protected function publicProfileBaseUrl(): string
    {
        $configured = trim((string) (
            config('services.profile.base_url')
            ?: config('app.public_profile_base_url')
            ?: env('PLYRCARD_PROFILE_BASE_URL')
        ));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $requestHost = request()?->getSchemeAndHttpHost();
        $host = strtolower((string) request()?->getHost());

        if ($host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return 'https://dev.plyrcard.com';
        }

        return $requestHost ? rtrim($requestHost, '/') : rtrim((string) config('app.url'), '/');
    }

}