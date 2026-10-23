<?php

namespace App\Http\Controllers;

use App\Filament\Pages\AdminSettings;
use App\Models\PlyrPointService;
use App\Models\PlyrPointServiceSample;
use App\Services\PlyrPointCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlyrPointServiceAdminController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $this->serviceData($request);

        $base = Str::slug($data['name'], '_') ?: 'service';
        $key = $base;
        $suffix = 2;
        while (PlyrPointService::query()->where('key', $key)->exists()) {
            $key = $base . '_' . $suffix;
            $suffix++;
        }

        $service = PlyrPointService::query()->create([
            ...$data,
            'key' => $key,
            'is_system' => false,
            'sort_order' => ((int) PlyrPointService::query()->max('sort_order')) + 10,
        ]);

        return $this->respond($request, $service, 'Service added.');
    }

    public function update(Request $request, PlyrPointService $service): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();
        $service->update($this->serviceData($request));

        return $this->respond($request, $service->fresh(), 'Service updated.');
    }

    public function toggle(Request $request, PlyrPointService $service): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
        ]);

        // Prefer the explicit target state from the instant admin switch. Falling
        // back to inversion keeps this endpoint compatible with older clients.
        $enabled = array_key_exists('enabled', $data)
            ? (bool) $data['enabled']
            : ! (bool) $service->is_enabled;

        if ((bool) $service->is_enabled !== $enabled) {
            $service->update(['is_enabled' => $enabled]);
        }

        return $this->respond(
            $request,
            $service->fresh(),
            $enabled ? 'Service turned on.' : 'Service turned off.',
        );
    }

    public function destroy(Request $request, PlyrPointService $service): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();

        if ($service->is_system) {
            return $this->respond($request, $service, 'Built-in services can be turned off, but not deleted.', 'error', 422);
        }

        $service->load('samples');
        foreach ($service->samples as $sample) {
            if (filled($sample->path)) {
                Storage::disk('public')->delete((string) $sample->path);
            }
        }

        $deletedId = (int) $service->getKey();
        $service->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Service deleted.',
                'deleted_id' => $deletedId,
            ]);
        }

        return redirect()->to(AdminSettings::getUrl() . '#plyr-points-services')
            ->with('plyr_points_success', 'Service deleted.');
    }

    public function addUrlSample(Request $request, PlyrPointService $service): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'sample_url' => ['required', 'url', 'max:2000'],
            'sample_title' => ['nullable', 'string', 'max:180'],
        ]);

        $url = trim((string) $data['sample_url']);
        $youtubeId = $this->youtubeId($url);
        $type = $youtubeId ? 'youtube' : $this->urlMediaType($url);

        $service->samples()->create([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'title' => trim((string) ($data['sample_title'] ?? '')) ?: ($type === 'youtube' ? 'YouTube sample' : 'Sample link'),
            'source_url' => $url,
            'youtube_id' => $youtubeId,
            'sort_order' => ((int) $service->samples()->max('sort_order')) + 10,
        ]);

        return $this->respond($request, $service->fresh(), 'Sample added.');
    }

    public function uploadSamples(Request $request, PlyrPointService $service): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'sample_files' => ['required', 'array', 'min:1', 'max:12'],
            'sample_files.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,m4v,webm'],
        ], [
            'sample_files.*.max' => 'Each sample must be 50 MB or smaller. Use a YouTube link for larger video files.',
            'sample_files.*.mimes' => 'Samples must be an image or video file.',
        ]);

        $sort = ((int) $service->samples()->max('sort_order')) + 10;
        foreach ((array) $request->file('sample_files', []) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
            $type = str_starts_with($mime, 'image/') ? 'image' : 'video';
            $path = $file->store('plyr-points/samples/' . $service->key, 'public');

            $service->samples()->create([
                'uuid' => (string) Str::uuid(),
                'type' => $type,
                'title' => trim((string) $file->getClientOriginalName()) ?: ucfirst($type) . ' sample',
                'path' => $path,
                'mime' => $mime,
                'sort_order' => $sort,
            ]);
            $sort += 10;
        }

        return $this->respond($request, $service->fresh(), 'Sample media uploaded.');
    }

    public function removeSample(Request $request, PlyrPointService $service, PlyrPointServiceSample $sample): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin();
        abort_unless((int) $sample->plyr_point_service_id === (int) $service->getKey(), 404);

        if (filled($sample->path)) {
            Storage::disk('public')->delete((string) $sample->path);
        }
        $sample->delete();

        return $this->respond($request, $service->fresh(), 'Sample removed.');
    }

    private function serviceData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'points' => ['required', 'integer', 'min:1', 'max:100000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cta' => ['nullable', 'string', 'max:120'],
            'inclusions' => ['nullable', 'array', 'max:30'],
            'inclusions.*' => ['nullable', 'string', 'max:180'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim((string) $data['name']),
            'points' => max(1, (int) $data['points']),
            'description' => trim((string) ($data['description'] ?? '')),
            'cta' => trim((string) ($data['cta'] ?? '')) ?: 'Request service',
            'inclusions' => collect((array) ($data['inclusions'] ?? []))
                ->map(fn ($item): string => trim((string) $item))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'is_enabled' => $request->boolean('is_enabled'),
        ];
    }

    private function authorizeAdmin(): void
    {
        abort_unless(AdminSettings::canAccess(), 403);
    }

    private function respond(
        Request $request,
        PlyrPointService $service,
        string $message,
        string $type = 'success',
        int $status = 200,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $type !== 'error',
                'message' => $message,
                'service' => $this->servicePayload($service),
            ], $status);
        }

        return $this->editorRedirect($service, $message, $type);
    }

    private function servicePayload(PlyrPointService $service): array
    {
        $id = (int) $service->getKey();

        return collect(app(PlyrPointCatalogService::class)->adminRows())
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $id)
            ?? [
                'id' => $id,
                'key' => (string) $service->key,
                'name' => (string) $service->name,
                'points' => (int) $service->points,
                'description' => (string) ($service->description ?? ''),
                'cta' => (string) ($service->cta ?? 'Request service'),
                'inclusions' => (array) $service->inclusions,
                'samples' => [],
                'enabled' => (bool) $service->is_enabled,
                'is_system' => (bool) $service->is_system,
            ];
    }

    private function editorRedirect(PlyrPointService $service, string $message, string $type = 'success'): RedirectResponse
    {
        return redirect()->to(AdminSettings::getUrl(['service' => $service->getKey()]) . '#plyr-points-services')
            ->with($type === 'error' ? 'plyr_points_error' : 'plyr_points_success', $message);
    }

    private function youtubeId(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', (string) ($parts['host'] ?? '')) ?? '');
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($host === 'youtu.be' && $path !== '') {
            return explode('/', $path)[0] ?: null;
        }

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            if (filled($query['v'] ?? null)) {
                return trim((string) $query['v']);
            }

            foreach (['embed/', 'shorts/', 'live/'] as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return trim(explode('/', substr($path, strlen($prefix)))[0] ?? '') ?: null;
                }
            }
        }

        return null;
    }

    private function urlMediaType(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if (preg_match('/\.(jpg|jpeg|png|webp|gif)(?:$|\?)/i', $path)) {
            return 'image';
        }
        if (preg_match('/\.(mp4|mov|m4v|webm)(?:$|\?)/i', $path)) {
            return 'video';
        }

        return 'link';
    }
}