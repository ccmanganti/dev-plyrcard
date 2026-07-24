<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExternalTrackingUrlService
{
    public function generate(
        User $player,
        Website $website,
        array $options = []
    ): array {
        abort_unless((int) $website->user_id === (int) $player->getKey(), 404);

        $profileUrl = $this->profileUrl($website);

        $parameters = [
            'utm_source' => $this->cleanCampaignValue(
                $options['source'] ?? config('external_tracking.default_source', 'ghl')
            ),
            'utm_medium' => $this->cleanCampaignValue(
                $options['medium'] ?? config('external_tracking.default_medium', 'email')
            ),
            'utm_campaign' => $this->cleanCampaignValue(
                $options['campaign'] ?? config('external_tracking.default_campaign', 'recruiting')
            ),
            'rc_external' => '1',
        ];

        if (($options['include_contact_id'] ?? true) === true) {
            $parameters['rc_contact_id'] = (string) config(
                'external_tracking.ghl_contact_id_merge_field',
                '{{contact.id}}'
            );
        }

        if (($options['include_email'] ?? true) === true) {
            $parameters['rc_email'] = (string) config(
                'external_tracking.ghl_contact_email_merge_field',
                '{{contact.email}}'
            );
        }

        return [
            'player_id' => $player->getKey(),
            'player_name' => trim($player->first_name . ' ' . $player->last_name),
            'website_id' => $website->getKey(),
            'profile_base_url' => $profileUrl,
            'profile' => $this->appendParameters($profileUrl, [
                ...$parameters,
                'rc_destination' => 'profile',
            ]),
            'instagram' => filled($player->ig_handle)
                ? $this->appendParameters($this->socialTrackingUrl($website, 'instagram'), [
                    ...$parameters,
                    'rc_destination' => 'instagram',
                ])
                : null,
            'youtube' => filled($player->yt_url)
                ? $this->appendParameters($this->socialTrackingUrl($website, 'youtube'), [
                    ...$parameters,
                    'rc_destination' => 'youtube',
                ])
                : null,
            'x' => filled($player->x_handle)
                ? $this->appendParameters($this->socialTrackingUrl($website, 'x'), [
                    ...$parameters,
                    'rc_destination' => 'x',
                ])
                : null,
        ];
    }

    public function profileUrl(Website $website): string
    {
        $domain = $this->normalizeDomain($website->domain ?? null);
        $slug = trim((string) ($website->slug ?? $website->name ?? ''), '/');

        if ($domain !== '' && ! $this->isPlatformHost($domain)) {
            return $this->schemeFor($domain) . '://' . $domain;
        }

        $base = $domain !== ''
            ? $this->schemeFor($domain) . '://' . $domain
            : rtrim((string) config('app.url'), '/');

        return $slug !== '' ? $base . '/' . rawurlencode($slug) : $base;
    }

    public function socialTrackingUrl(Website $website, string $platform): string
    {
        $platform = strtolower(trim($platform));

        if (! in_array($platform, ['instagram', 'youtube', 'x'], true)) {
            throw new InvalidArgumentException('Unsupported social platform.');
        }

        $profileUrl = $this->profileUrl($website);

        return rtrim($profileUrl, '/') . '/out/' . $platform;
    }

    public function appendParameters(string $url, array $parameters): string
    {
        $pairs = [];

        foreach ($parameters as $key => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $value = (string) $value;
            $pairs[] = rawurlencode((string) $key) . '=' . (
                $this->isMergeField($value) ? $value : rawurlencode($value)
            );
        }

        return $pairs === []
            ? $url
            : $url . (str_contains($url, '?') ? '&' : '?') . implode('&', $pairs);
    }

    protected function normalizeDomain(mixed $domain): string
    {
        $domain = strtolower(trim((string) $domain));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;

        return trim($domain, '/');
    }

    protected function isPlatformHost(string $domain): bool
    {
        $host = preg_replace('/:\d+$/', '', $domain) ?: $domain;

        return in_array($host, config('external_tracking.platform_hosts', []), true);
    }

    protected function schemeFor(string $domain): string
    {
        $host = preg_replace('/:\d+$/', '', $domain) ?: $domain;

        return in_array($host, ['127.0.0.1', 'localhost'], true) ? 'http' : 'https';
    }

    protected function isMergeField(string $value): bool
    {
        return preg_match('/^\{\{[^{}]+\}\}$/', trim($value)) === 1;
    }

    protected function cleanCampaignValue(mixed $value): string
    {
        return Str::limit(trim((string) $value), 120, '');
    }
}
