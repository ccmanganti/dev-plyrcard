<?php

namespace App\Support;

use Illuminate\Mail\Mailables\Address;

final class PlyrcardMailSender
{
    public static function email(): string
    {
        $host = static::currentHost();

        if (static::isDevelopmentHost($host)) {
            return 'support@dev.plyrcard.com';
        }

        return 'support@plyrcard.com';
    }

    public static function address(): Address
    {
        return new Address(static::email(), 'PLYRCARD Support');
    }

    private static function currentHost(): string
    {
        $host = '';

        try {
            if (app()->bound('request')) {
                $host = strtolower(trim((string) request()->getHost()));
            }
        } catch (\Throwable) {
            $host = '';
        }

        if ($host !== '') {
            return $host;
        }

        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return strtolower(trim((string) $configuredHost));
    }

    private static function isDevelopmentHost(string $host): bool
    {
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if ($host === 'dev.plyrcard.com' || str_ends_with($host, '.dev.plyrcard.com')) {
            return true;
        }

        return app()->environment(['local', 'development', 'testing']);
    }
}