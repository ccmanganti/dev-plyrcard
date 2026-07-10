<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CoachDatabaseSyncRuntime
{
    public function configuredDriver(): string
    {
        $driver = strtolower(trim((string) config('coach-database-sync.background.driver', 'auto')));

        return in_array($driver, ['auto', 'queue', 'shell', 'scheduler', 'web_tick'], true)
            ? $driver
            : 'auto';
    }

    public function queueConnection(): string
    {
        $configured = trim((string) config('coach-database-sync.background.queue_connection', ''));

        return $configured !== ''
            ? $configured
            : (string) config('queue.default', 'sync');
    }

    public function queueDriver(): string
    {
        $connection = $this->queueConnection();

        return (string) config("queue.connections.{$connection}.driver", 'unknown');
    }

    public function queueIsAsynchronous(): bool
    {
        $driver = strtolower($this->queueDriver());

        return $driver !== '' && ! in_array($driver, ['sync', 'unknown'], true);
    }

    public function shellEnabled(): bool
    {
        return (bool) config('coach-database-sync.background.shell_enabled', true);
    }

    public function allowShellInProduction(): bool
    {
        return (bool) config('coach-database-sync.background.allow_shell_in_production', false);
    }

    public function webFallbackEnabled(): bool
    {
        return (bool) config('coach-database-sync.web_fallback.enabled', true);
    }

    public function functionAvailable(string $function): bool
    {
        if (! function_exists($function)) {
            return false;
        }

        $disabled = array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) ini_get('disable_functions')),
        ));

        return ! in_array($function, $disabled, true);
    }

    public function canSpawnDetachedProcess(): bool
    {
        if (! $this->shellEnabled() || ! $this->functionAvailable('proc_open')) {
            return false;
        }

        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        return $this->allowShellInProduction();
    }

    public function schedulerHeartbeatKey(): string
    {
        return CoachDatabaseSyncCoordinator::SCHEDULER_HEARTBEAT_KEY;
    }

    public function schedulerIsHealthy(): bool
    {
        $heartbeat = Cache::get($this->schedulerHeartbeatKey(), []);
        if (! is_array($heartbeat) || blank($heartbeat['at'] ?? null)) {
            return false;
        }

        $timestamp = strtotime((string) $heartbeat['at']);
        $maxAge = max(60, (int) config('coach-database-sync.background.scheduler_healthy_seconds', 180));

        return $timestamp !== false && (time() - $timestamp) <= $maxAge;
    }

    /** @return array<int, string> */
    public function autoStrategies(): array
    {
        $queueAvailable = (bool) config('coach-database-sync.background.queue_enabled', true)
            && $this->queueIsAsynchronous();
        $shellAvailable = $this->canSpawnDetachedProcess();
        $schedulerAvailable = $this->schedulerIsHealthy();
        $webAvailable = $this->webFallbackEnabled();
        $local = app()->environment(['local', 'testing']);
        $preferShellLocally = (bool) config('coach-database-sync.background.prefer_shell_locally', true) && $local;

        if ($preferShellLocally) {
            return array_values(array_filter([
                $shellAvailable ? 'shell' : null,
                $queueAvailable ? 'queue' : null,
                $schedulerAvailable ? 'scheduler' : null,
                $webAvailable ? 'web_tick' : null,
            ]));
        }

        // Production auto mode only chooses runners that have evidence they can actually
        // continue after the HTTP request. A merely available proc_open is not enough on
        // shared hosting, where detached children are commonly killed by PHP-FPM.
        return array_values(array_filter([
            $queueAvailable ? 'queue' : null,
            $schedulerAvailable ? 'scheduler' : null,
            $shellAvailable ? 'shell' : null,
            $webAvailable ? 'web_tick' : null,
        ]));
    }

    public function resolvedAutoDriver(): string
    {
        return $this->autoStrategies()[0] ?? 'scheduler';
    }

    /** @return array<string, mixed> */
    public function diagnostics(): array
    {
        return [
            'configured_driver' => $this->configuredDriver(),
            'resolved_driver' => $this->configuredDriver() === 'auto'
                ? $this->resolvedAutoDriver()
                : $this->configuredDriver(),
            'auto_strategies' => $this->autoStrategies(),
            'queue_connection' => $this->queueConnection(),
            'queue_driver' => $this->queueDriver(),
            'queue_is_asynchronous' => $this->queueIsAsynchronous(),
            'shell_enabled' => $this->shellEnabled(),
            'shell_allowed_here' => $this->canSpawnDetachedProcess(),
            'allow_shell_in_production' => $this->allowShellInProduction(),
            'proc_open_available' => $this->functionAvailable('proc_open'),
            'scheduler_is_healthy' => $this->schedulerIsHealthy(),
            'web_fallback_enabled' => $this->webFallbackEnabled(),
            'os_family' => PHP_OS_FAMILY,
            'php_binary' => PHP_BINARY,
            'app_environment' => app()->environment(),
        ];
    }
}
