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

    /**
     * APP_ENV is occasionally copied from production into a local checkout. Detect the
     * actual runtime as well, so 127.0.0.1/localhost never accidentally selects an
     * unattended queue merely because QUEUE_CONNECTION=database or redis is configured.
     */
    public function isLocalRuntime(): bool
    {
        if (app()->environment(['local', 'testing']) || app()->runningUnitTests()) {
            return true;
        }

        $hosts = [];
        $appHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = strtolower($appHost);
        }

        if (! app()->runningInConsole()) {
            try {
                $hosts[] = strtolower((string) request()->getHost());
            } catch (\Throwable) {
                // Request is not available in every execution context.
            }
        }

        foreach (array_unique($hosts) as $host) {
            if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                || str_ends_with($host, '.test')
                || str_ends_with($host, '.local')) {
                return true;
            }
        }

        return false;
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

        if ($this->isLocalRuntime()) {
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

        if ($this->isLocalRuntime()) {
            // A local database/redis queue connection does not prove that queue:work is
            // running. Prefer a verified detached CLI process, then the self-driving web
            // fallback. Queue remains available when explicitly selected in .env.
            return array_values(array_filter([
                $shellAvailable ? 'shell' : null,
                $webAvailable ? 'web_tick' : null,
                $schedulerAvailable ? 'scheduler' : null,
            ]));
        }

        // Queue launch is verified by a real worker heartbeat in the launcher. If no
        // worker checks in within the grace window, auto mode immediately continues to
        // scheduler/shell/web compatibility mode instead of remaining stuck or failing.
        return array_values(array_filter([
            $queueAvailable ? 'queue' : null,
            $schedulerAvailable ? 'scheduler' : null,
            $shellAvailable ? 'shell' : null,
            $webAvailable ? 'web_tick' : null,
        ]));
    }

    public function resolvedAutoDriver(): string
    {
        return $this->autoStrategies()[0] ?? 'web_tick';
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
            'local_runtime_detected' => $this->isLocalRuntime(),
            'queue_connection' => $this->queueConnection(),
            'queue_driver' => $this->queueDriver(),
            'queue_is_asynchronous' => $this->queueIsAsynchronous(),
            'queue_requires_worker_checkin' => true,
            'shell_enabled' => $this->shellEnabled(),
            'shell_allowed_here' => $this->canSpawnDetachedProcess(),
            'allow_shell_in_production' => $this->allowShellInProduction(),
            'proc_open_available' => $this->functionAvailable('proc_open'),
            'scheduler_is_healthy' => $this->schedulerIsHealthy(),
            'web_fallback_enabled' => $this->webFallbackEnabled(),
            'os_family' => PHP_OS_FAMILY,
            'php_binary' => PHP_BINARY,
            'app_environment' => app()->environment(),
            'app_url' => (string) config('app.url', ''),
        ];
    }
}