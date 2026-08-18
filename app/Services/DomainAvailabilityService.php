<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DomainAvailabilityService
{
    /**
     * Check whether a domain currently has an RDAP registration record.
     *
     * Only conclusive results are cached. Transient network errors, rate limits,
     * and bootstrap failures are deliberately NOT cached so the next search can
     * recover immediately.
     */
    public function lookup(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $cacheKey = 'plyrcard:rdap:domain:' . sha1($domain);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['verified'] ?? false)) {
            return $cached;
        }

        $result = $this->performLookup($domain);

        if (($result['verified'] ?? false) === true) {
            $cacheMinutes = max(1, (int) config(
                'plyrcard-registration.domain_lookup.result_cache_minutes',
                10,
            ));

            Cache::put($cacheKey, $result, now()->addMinutes($cacheMinutes));
        }

        return $result;
    }

    protected function performLookup(string $domain): array
    {
        if ($domain === '' || ! str_contains($domain, '.')) {
            return $this->unknown($domain, '', 'Enter a complete domain name.');
        }

        $tld = strtolower((string) str($domain)->afterLast('.'));

        // Prefer known registry endpoints for the most common PLYRCARD search
        // candidates. This removes the IANA bootstrap request from the hot path
        // for .com/.net and makes local development substantially more reliable.
        $baseUrl = $this->commonRegistryBaseUrl($tld)
            ?: $this->resolveRdapBaseUrl($tld);

        if ($baseUrl) {
            $direct = $this->queryRdapUrl(
                rtrim($baseUrl, '/') . '/domain/' . rawurlencode($domain),
                $domain,
                $tld,
                $baseUrl,
                $this->commonRegistryBaseUrl($tld) ? 'direct-registry' : 'iana-bootstrap',
            );

            if (($direct['verified'] ?? false) === true) {
                return $direct;
            }

            // If the authoritative service gives us a temporary/non-conclusive
            // response, try the public bootstrap proxy before giving up.
            if (! in_array((int) ($direct['http_status'] ?? 0), [400, 404], true)) {
                $fallback = $this->queryBootstrapFallback($domain, $tld);
                if (($fallback['verified'] ?? false) === true) {
                    return $fallback;
                }
            }

            return $direct;
        }

        // IANA bootstrap itself may be temporarily unreachable from the server.
        // rdap.org is a bootstrap service that redirects to the proper registry.
        return $this->queryBootstrapFallback($domain, $tld);
    }

    protected function queryBootstrapFallback(string $domain, string $tld): array
    {
        $fallbackBase = rtrim((string) config(
            'plyrcard-registration.domain_lookup.fallback_base_url',
            'https://rdap.org',
        ), '/');

        if ($fallbackBase === '') {
            return $this->unknown($domain, $tld, 'Domain availability could not be verified right now.');
        }

        return $this->queryRdapUrl(
            $fallbackBase . '/domain/' . rawurlencode($domain),
            $domain,
            $tld,
            $fallbackBase,
            'bootstrap-fallback',
        );
    }

    protected function queryRdapUrl(
        string $url,
        string $domain,
        string $tld,
        string $rdapServer,
        string $lookupPath,
    ): array {
        try {
            $response = Http::withHeaders([
                    'Accept' => 'application/rdap+json, application/json;q=0.9',
                    'User-Agent' => 'PLYRCARD-Domain-Availability/1.1',
                ])
                ->connectTimeout((int) config('plyrcard-registration.domain_lookup.connect_timeout', 4))
                ->timeout((int) config('plyrcard-registration.domain_lookup.timeout', 10))
                ->retry(1, 250, throw: false)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 8,
                        'strict' => true,
                        'referer' => false,
                        'track_redirects' => true,
                    ],
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('Domain RDAP lookup failed before a response was received.', [
                'domain' => $domain,
                'tld' => $tld,
                'rdap_url' => $url,
                'lookup_path' => $lookupPath,
                'message' => $e->getMessage(),
            ]);

            return $this->unknown(
                $domain,
                $tld,
                'Domain availability could not be verified right now.',
                null,
                $rdapServer,
                $lookupPath,
            );
        }

        return $this->interpretResponse($response, $domain, $tld, $rdapServer, $lookupPath);
    }

    protected function interpretResponse(
        Response $response,
        string $domain,
        string $tld,
        string $rdapServer,
        string $lookupPath,
    ): array {
        if ($response->status() === 404) {
            return [
                'status' => 'available',
                'available' => true,
                'registered' => false,
                'verified' => true,
                'domain' => $domain,
                'tld' => $tld,
                'source' => 'rdap',
                'lookup_path' => $lookupPath,
                'rdap_server' => $rdapServer,
                'http_status' => 404,
                'message' => 'This domain appears available.',
            ];
        }

        if ($response->successful()) {
            $data = $response->json();
            $looksLikeDomain = is_array($data)
                && (($data['objectClassName'] ?? null) === 'domain'
                    || filled($data['ldhName'] ?? null)
                    || filled($data['unicodeName'] ?? null)
                    || filled($data['handle'] ?? null));

            if ($looksLikeDomain) {
                return [
                    'status' => 'registered',
                    'available' => false,
                    'registered' => true,
                    'verified' => true,
                    'domain' => $domain,
                    'tld' => $tld,
                    'source' => 'rdap',
                    'lookup_path' => $lookupPath,
                    'rdap_server' => $rdapServer,
                    'http_status' => $response->status(),
                    'registration_name' => $data['ldhName'] ?? $data['unicodeName'] ?? $domain,
                    'message' => 'That domain is already registered.',
                ];
            }
        }

        if ($response->status() === 429) {
            return $this->unknown(
                $domain,
                $tld,
                'Domain lookup is temporarily busy. Please try again.',
                429,
                $rdapServer,
                $lookupPath,
            );
        }

        return $this->unknown(
            $domain,
            $tld,
            'Domain availability could not be verified right now.',
            $response->status(),
            $rdapServer,
            $lookupPath,
        );
    }

    protected function commonRegistryBaseUrl(string $tld): ?string
    {
        return match (strtolower(trim($tld))) {
            // Verisign's published RDAP bootstrap endpoints.
            'com' => 'https://rdap.verisign.com/com/v1/',
            'net' => 'https://rdap.verisign.com/net/v1/',
            default => null,
        };
    }

    protected function resolveRdapBaseUrl(string $tld): ?string
    {
        $bootstrapUrl = (string) config(
            'plyrcard-registration.domain_lookup.bootstrap_url',
            'https://data.iana.org/rdap/dns.json',
        );
        $cacheHours = max(1, (int) config(
            'plyrcard-registration.domain_lookup.bootstrap_cache_hours',
            24,
        ));

        $bootstrap = Cache::get('plyrcard:rdap:iana-bootstrap');

        if (! is_array($bootstrap) || empty($bootstrap['services'])) {
            try {
                $response = Http::withHeaders([
                        'Accept' => 'application/json',
                        'User-Agent' => 'PLYRCARD-Domain-Availability/1.1',
                    ])
                    ->connectTimeout((int) config('plyrcard-registration.domain_lookup.connect_timeout', 4))
                    ->timeout((int) config('plyrcard-registration.domain_lookup.timeout', 10))
                    ->retry(1, 250, throw: false)
                    ->get($bootstrapUrl);

                if ($response->successful() && is_array($response->json())) {
                    $bootstrap = $response->json();
                    Cache::put(
                        'plyrcard:rdap:iana-bootstrap',
                        $bootstrap,
                        now()->addHours($cacheHours),
                    );
                } else {
                    $bootstrap = [];
                }
            } catch (Throwable $e) {
                Log::warning('Unable to load IANA RDAP DNS bootstrap registry.', [
                    'bootstrap_url' => $bootstrapUrl,
                    'message' => $e->getMessage(),
                ]);
                $bootstrap = [];
            }
        }

        foreach (($bootstrap['services'] ?? []) as $service) {
            if (! is_array($service) || count($service) < 2) {
                continue;
            }

            $tlds = collect($service[0] ?? [])
                ->map(fn ($value) => strtolower(trim((string) $value)))
                ->filter();

            if (! $tlds->contains($tld)) {
                continue;
            }

            $urls = collect($service[1] ?? [])
                ->map(fn ($value) => trim((string) $value))
                ->filter();

            return $urls->first(fn (string $url) => str_starts_with($url, 'https://'))
                ?: $urls->first();
        }

        return null;
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
        return trim($domain, ". \t\n\r\0\x0B");
    }

    protected function unknown(
        string $domain,
        string $tld,
        string $message,
        ?int $httpStatus = null,
        ?string $rdapServer = null,
        ?string $lookupPath = null,
    ): array {
        return [
            'status' => 'unknown',
            'available' => false,
            'registered' => null,
            'verified' => false,
            'domain' => $domain,
            'tld' => $tld,
            'source' => 'rdap',
            'lookup_path' => $lookupPath,
            'rdap_server' => $rdapServer,
            'http_status' => $httpStatus,
            'message' => $message,
        ];
    }
}