<?php

namespace App\Support;

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

    public static function name(): string
    {
        return 'PLYRCARD Support';
    }

    public static function currentHost(): string
    {
        $host = '';

        try {
            if (app()->bound('request')) {
                $host = strtolower(trim((string) request()->getHost()));
            }
        } catch (\Throwable) {
            $host = '';
        }

        if ($host === '') {
            $host = strtolower(trim((string) parse_url((string) config('app.url'), PHP_URL_HOST)));
        }

        $host = preg_replace('/:\d+$/', '', $host) ?: $host;
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        return $host;
    }

    private static function isDevelopmentHost(string $host): bool
    {
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return $host === 'dev.plyrcard.com' || str_ends_with($host, '.dev.plyrcard.com');
    }
}