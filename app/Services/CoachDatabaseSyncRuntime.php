<?php

namespace App\Services;

class CoachDatabaseSyncRuntime
{
    public function configuredDriver(): string
    {
        $driver = strtolower(trim((string) config('coach-database-sync.background.driver', 'auto')));

        return in_array($driver, ['auto', 'queue', 'shell', 'scheduler'], true)
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
        return $this->shellEnabled() && $this->functionAvailable('proc_open');
    }

    public function schedulerHeartbeatKey(): string
    {
        return 'recruiting:dataset-sync-scheduler-heartbeat';
    }

    /** @return array<int, string> */
    public function autoStrategies(): array
    {
        $queueAvailable = (bool) config('coach-database-sync.background.queue_enabled', true)
            && $this->queueIsAsynchronous();
        $shellAvailable = $this->canSpawnDetachedProcess();
        $preferShellLocally = (bool) config('coach-database-sync.background.prefer_shell_locally', true)
            && app()->environment(['local', 'testing']);

        if ($preferShellLocally) {
            return array_values(array_filter([
                $shellAvailable ? 'shell' : null,
                $queueAvailable ? 'queue' : null,
            ]));
        }

        return array_values(array_filter([
            $queueAvailable ? 'queue' : null,
            $shellAvailable ? 'shell' : null,
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
            'queue_connection' => $this->queueConnection(),
            'queue_driver' => $this->queueDriver(),
            'queue_is_asynchronous' => $this->queueIsAsynchronous(),
            'shell_enabled' => $this->shellEnabled(),
            'proc_open_available' => $this->functionAvailable('proc_open'),
            'os_family' => PHP_OS_FAMILY,
            'php_binary' => PHP_BINARY,
            'app_environment' => app()->environment(),
        ];
    }
}
