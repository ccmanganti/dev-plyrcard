<?php

namespace App\Jobs;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DownloadSchoolLogo implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;
    public int $uniqueFor = 3600;

    public function __construct(
        public int $schoolId,
        public string $logoUrl,
    ) {
    }

    public function uniqueId(): string
    {
        return 'school-logo:' . $this->schoolId;
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        $school = School::withTrashed()->find($this->schoolId);

        if (! $school || filled($school->logo_path)) {
            return;
        }

        $url = trim($this->logoUrl);
        $this->assertValidHttpUrl($url);

        $response = Http::connectTimeout(5)
            ->timeout(20)
            ->retry(2, 300, throw: false)
            ->withHeaders([
                'User-Agent' => 'PLYRCard School Logo Importer',
                'Accept' => 'image/png,image/jpeg,image/webp,image/gif,image/avif,image/svg+xml,*/*;q=0.8',
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('School logo download failed with HTTP ' . $response->status() . '.');
        }

        $body = $response->body();
        if ($body === '') {
            throw new RuntimeException('School logo response was empty.');
        }

        if (strlen($body) > 5 * 1024 * 1024) {
            throw new RuntimeException('School logo exceeds the 5 MB limit.');
        }

        $mime = strtolower(trim((string) $response->header('Content-Type')));
        $mime = trim(explode(';', $mime)[0]);

        $extension = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            default => $this->extensionFromUrl($url),
        };

        if ($extension === null) {
            throw new RuntimeException('Unsupported school logo image type: ' . ($mime !== '' ? $mime : 'unknown'));
        }

        $slug = Str::slug($school->name) ?: 'school';
        $hash = substr(sha1($url), 0, 12);
        $path = "schools/logos/{$slug}-{$hash}.{$extension}";

        if (! Storage::disk('public')->exists($path)) {
            $stored = Storage::disk('public')->put($path, $body);

            if (! $stored) {
                throw new RuntimeException('School logo could not be written to the public storage disk.');
            }
        }

        $school->forceFill(['logo_path' => $path])->save();
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Queued school logo download failed.', [
            'school_id' => $this->schoolId,
            'logo_url' => $this->logoUrl,
            'message' => $exception?->getMessage(),
        ]);
    }

    private function extensionFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'], true)
            ? ($extension === 'jpeg' ? 'jpg' : $extension)
            : null;
    }

    private function assertValidHttpUrl(string $url): void
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('The school logo URL is invalid.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('School logo URLs must use HTTP or HTTPS.');
        }
    }
}