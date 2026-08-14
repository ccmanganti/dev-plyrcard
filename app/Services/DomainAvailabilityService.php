<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DomainAvailabilityService
{
    /**
     * Check whether RDAP currently has registration data for a domain.
     *
     * Important: an RDAP 404 means the registry returned an empty result set.
     * That is a strong signal that the domain is not currently registered, but
     * it does NOT guarantee the name is purchasable (reserved/premium/policy
     * restrictions are still determined by the registrar during provisioning).
     */
    public function lookup(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $cacheMinutes = max(1, (int) config('plyrcard-registration.domain_lookup.result_cache_minutes', 10));

        return Cache::remember(
            'plyrcard:rdap:domain:' . sha1($domain),
            now()->addMinutes($cacheMinutes),
            fn (): array => $this->performLookup($domain),
        );
    }

    protected function performLookup(string $domain): array
    {
        $tld = strtolower((string) str($domain)->afterLast('.'));
        $baseUrl = $this->resolveRdapBaseUrl($tld);

        if (! $baseUrl) {
            return $this->unknown($domain, $tld, 'No RDAP service is published by IANA for this TLD.');
        }

        $url = rtrim($baseUrl, '/') . '/domain/' . rawurlencode($domain);

        try {
            $response = Http::withHeaders([
                    'Accept' => 'application/rdap+json, application/json;q=0.9',
                    'User-Agent' => 'PLYRCARD-Domain-Availability/1.0 (' . config('app.url') . ')',
                ])
                ->connectTimeout((int) config('plyrcard-registration.domain_lookup.connect_timeout', 3))
                ->timeout((int) config('plyrcard-registration.domain_lookup.timeout', 7))
                ->retry(1, 200, throw: false)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => true,
                        'referer' => false,
                    ],
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('RDAP domain lookup failed before a response was received.', [
                'domain' => $domain,
                'tld' => $tld,
                'rdap_url' => $url,
                'message' => $e->getMessage(),
            ]);

            return $this->unknown($domain, $tld, 'The RDAP registry could not be reached.');
        }

        if ($response->status() === 404) {
            return [
                'status' => 'available',
                'available' => true,
                'registered' => false,
                'verified' => true,
                'domain' => $domain,
                'tld' => $tld,
                'source' => 'rdap',
                'rdap_server' => $baseUrl,
                'http_status' => 404,
                'message' => 'RDAP found no current registration for this domain.',
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
                    'rdap_server' => $baseUrl,
                    'http_status' => $response->status(),
                    'registration_name' => $data['ldhName'] ?? $data['unicodeName'] ?? $domain,
                    'message' => 'RDAP returned an existing registration for this domain.',
                ];
            }
        }

        if ($response->status() === 429) {
            return $this->unknown($domain, $tld, 'The RDAP registry is rate-limiting lookups. Please try again shortly.', 429, $baseUrl);
        }

        return $this->unknown(
            $domain,
            $tld,
            'The RDAP registry did not return a conclusive availability result.',
            $response->status(),
            $baseUrl,
        );
    }

    protected function resolveRdapBaseUrl(string $tld): ?string
    {
        $bootstrapUrl = (string) config(
            'plyrcard-registration.domain_lookup.bootstrap_url',
            'https://data.iana.org/rdap/dns.json',
        );
        $cacheHours = max(1, (int) config('plyrcard-registration.domain_lookup.bootstrap_cache_hours', 24));

        try {
            $bootstrap = Cache::remember(
                'plyrcard:rdap:iana-bootstrap',
                now()->addHours($cacheHours),
                function () use ($bootstrapUrl): array {
                    $response = Http::withHeaders([
                            'Accept' => 'application/json',
                            'User-Agent' => 'PLYRCARD-Domain-Availability/1.0 (' . config('app.url') . ')',
                        ])
                        ->connectTimeout((int) config('plyrcard-registration.domain_lookup.connect_timeout', 3))
                        ->timeout((int) config('plyrcard-registration.domain_lookup.timeout', 7))
                        ->retry(1, 200, throw: false)
                        ->get($bootstrapUrl);

                    return $response->successful() && is_array($response->json())
                        ? $response->json()
                        : [];
                },
            );
        } catch (Throwable $e) {
            Log::warning('Unable to load IANA RDAP DNS bootstrap registry.', [
                'bootstrap_url' => $bootstrapUrl,
                'message' => $e->getMessage(),
            ]);

            return null;
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

    protected function unknown(
        string $domain,
        string $tld,
        string $message,
        ?int $httpStatus = null,
        ?string $rdapServer = null,
    ): array {
        return [
            'status' => 'unknown',
            'available' => false,
            'registered' => null,
            'verified' => false,
            'domain' => $domain,
            'tld' => $tld,
            'source' => 'rdap',
            'rdap_server' => $rdapServer,
            'http_status' => $httpStatus,
            'message' => $message,
        ];
    }
}
