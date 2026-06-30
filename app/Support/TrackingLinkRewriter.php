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

        $rewritten = $this->rewriteAnchorHrefs($html, $context);

        return $rewritten ?: $html;
    }

    public function appendOpenPixel(string $html, array $context = []): string
    {
        $token = $this->makeToken(array_merge($context, [
            'event_type' => 'email_open',
            'platform' => 'email',
            'destination_url' => null,
        ]));

        $src = rtrim($this->trackingBaseUrl(), '/') . '/track/open/' . $token . '.gif';
        $pixel = '<img src="' . e($src) . '" width="1" height="1" alt="" style="display:none!important;width:1px;height:1px;opacity:0;overflow:hidden;border:0;" />';

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

        $payload = array_merge($context, [
            'event_type' => $context['event_type'] ?? 'link_click',
            'platform' => $context['platform'] ?? $this->detectPlatform($destinationUrl),
            'destination_url' => $destinationUrl,
        ]);

        return rtrim($this->trackingBaseUrl(), '/') . '/track/click/' . $this->makeToken($payload);
    }

    public function decodeToken(string $token): array
    {
        $token = trim($token);
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

    protected function rewriteAnchorHrefs(string $html, array $context): string
    {
        $callback = function (array $matches) use ($context): string {
            $prefix = $matches[1];
            $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $suffix = $matches[3];

            if ($this->shouldSkipUrl($url)) {
                return $matches[0];
            }

            return $prefix . e($this->trackedUrl($url, $context)) . $suffix;
        };

        return preg_replace_callback('/(<a\b[^>]*?\bhref=["\'])([^"\']+)(["\'][^>]*>)/i', $callback, $html) ?: $html;
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

        if (str_contains($lower, '/track/click/') || str_contains($lower, '/track/open/')) {
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

        $encrypted = Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    protected function trackingBaseUrl(): string
    {
        return rtrim((string) (
            config('services.tracking.base_url')
            ?: config('app.tracking_base_url')
            ?: env('TRACKING_BASE_URL')
            ?: config('app.url')
            ?: request()?->getSchemeAndHttpHost()
        ), '/');
    }
}
