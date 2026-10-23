<?php

namespace App\Http\Controllers;

use App\Filament\Pages\AdminSettings;
use App\Models\PlyrPointService;
use App\Models\PlyrPointServiceSample;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlyrPointServiceAdminController extends Controller
{
    public function store(Request $request): RedirectResponse
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

        return $this->editorRedirect($service, 'Service added.');
    }

    public function update(Request $request, PlyrPointService $service): RedirectResponse
    {
        $this->authorizeAdmin();
        $service->update($this->serviceData($request));

        return $this->editorRedirect($service, 'Service updated.');
    }

    public function toggle(PlyrPointService $service): RedirectResponse
    {
        $this->authorizeAdmin();
        $service->update(['is_enabled' => ! $service->is_enabled]);

        return redirect()->to(AdminSettings::getUrl() . '#plyr-points-services');
    }

    public function destroy(PlyrPointService $service): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($service->is_system) {
            return $this->editorRedirect($service, 'Built-in services can be turned off, but not deleted.', 'error');
        }

        $service->load('samples');
        foreach ($service->samples as $sample) {
            if (filled($sample->path)) {
                Storage::disk('public')->delete((string) $sample->path);
            }
        }
        $service->delete();

        return redirect()->to(AdminSettings::getUrl() . '#plyr-points-services')
            ->with('plyr_points_success', 'Service deleted.');
    }

    public function addUrlSample(Request $request, PlyrPointService $service): RedirectResponse
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

        return $this->editorRedirect($service, 'Sample link added.');
    }

    public function uploadSamples(Request $request, PlyrPointService $service): RedirectResponse
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

        return $this->editorRedirect($service, 'Sample media uploaded.');
    }

    public function removeSample(PlyrPointService $service, PlyrPointServiceSample $sample): RedirectResponse
    {
        $this->authorizeAdmin();
        abort_unless((int) $sample->plyr_point_service_id === (int) $service->getKey(), 404);

        if (filled($sample->path)) {
            Storage::disk('public')->delete((string) $sample->path);
        }
        $sample->delete();

        return $this->editorRedirect($service, 'Sample removed.');
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
