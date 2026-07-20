<?php

namespace App\Services;

use App\Models\CoachDatabaseSchool;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Str;

class LocalTemplateMergeValueService
{
    public function replace(string $content, User $athlete, array $coach = []): string
    {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $school = $this->school($athlete, $coach);

        $coachName = $this->text($coach['name'] ?? $coach['full_name'] ?? '');
        $parts = preg_split('/\s+/', $coachName, 2) ?: [];
        $first = $this->text($coach['first_name'] ?? ($parts[0] ?? ''));
        $last = $this->text($coach['last_name'] ?? ($parts[1] ?? ''));

        $athleteName = $this->text($athlete->name)
            ?: trim($this->text($athlete->first_name).' '.$this->text($athlete->last_name));
        $athleteParts = preg_split('/\s+/', $athleteName, 2) ?: [];

        $profileUrl = $this->cleanUrl($this->profileUrl($athlete));

        $values = [
            'AthleteName' => $athleteName,
            'AthleteFirstName' => $this->text($athlete->first_name ?? ($athleteParts[0] ?? '')),
            'AthleteLastName' => $this->text($athlete->last_name ?? ($athleteParts[1] ?? '')),
            'AthletePhone' => $this->text($athlete->phone),
            'AthleteEmail' => $this->text($athlete->email ?: $athlete->personal_email),
            'GraduationYear' => $this->text($athlete->graduation_year ?? $athlete->year ?? ''),
            'Position' => $this->readableText($athlete->position),
            'ClubTeam' => $this->text($athlete->club_team),
            'GPA' => $this->text($athlete->gpa),
            'CoachName' => $coachName ?: trim($first.' '.$last),
            'CoachFirstName' => $first ?: 'Coach',
            'CoachLastName' => $last,
            'CoachTitle' => $this->text($coach['title'] ?? 'Coach'),
            'CoachEmail' => $this->text($coach['email'] ?? ''),
            'CoachPhone' => $this->text($coach['phone'] ?? ''),
            'SchoolName' => $this->text($school['name'] ?? $coach['school'] ?? $coach['company_name'] ?? ''),
            'Conference' => $this->text($school['conference'] ?? $coach['conference'] ?? ''),
            'Division' => $this->text($school['division'] ?? $coach['division'] ?? ''),
            'City' => $this->text($school['city'] ?? $coach['city'] ?? ''),
            'State' => $this->text($school['state'] ?? $coach['state'] ?? ''),
            'ProfileLink' => $profileUrl,
            'HighlightLink' => $this->text($athlete->featured_video_url) ?: $profileUrl,
            'InstagramLink' => $this->social($this->text($athlete->ig_handle ?? $athlete->instagram_url ?? ''), 'instagram'),
            'YouTubeLink' => $this->text($athlete->yt_url ?? $athlete->youtube_url ?? ''),
            'YoutubeLink' => $this->text($athlete->yt_url ?? $athlete->youtube_url ?? ''),
            'XLink' => $this->social($this->text($athlete->x_handle ?? $athlete->twitter_url ?? ''), 'x'),
            'TwitterLink' => $this->social($this->text($athlete->x_handle ?? $athlete->twitter_url ?? ''), 'x'),
        ];

        $aliases = [
            'AthleteName' => ['athlete_name', 'player_name'],
            'AthleteFirstName' => ['athlete_first_name'],
            'AthleteLastName' => ['athlete_last_name'],
            'CoachName' => ['coach_name'],
            'CoachFirstName' => ['coach_first_name'],
            'CoachLastName' => ['coach_last_name'],
            'SchoolName' => ['school_name', 'school'],
            'CoachTitle' => ['coach_title'],
            'CoachEmail' => ['coach_email'],
            'Conference' => ['school_conference', 'conference'],
            'Division' => ['school_division', 'division'],
            'City' => ['school_city', 'city'],
            'State' => ['school_state', 'state'],
            'ProfileLink' => ['profile_url', 'profile_link'],
            'InstagramLink' => ['instagram_url', 'instagram_link'],
            'YouTubeLink' => ['youtube_url', 'youtube_link'],
            'XLink' => ['x_url', 'twitter_url', 'x_link'],
        ];

        foreach ($values as $canonical => $rawValue) {
            $value = $this->text($rawValue);
            $names = collect([$canonical, lcfirst($canonical), Str::snake($canonical)])
                ->merge($aliases[$canonical] ?? [])
                ->unique();

            foreach ($names as $name) {
                $quoted = preg_quote((string) $name, '/');
                $content = preg_replace_callback(
                    '/\{\{\s*'.$quoted.'\s*\}\}/i',
                    static fn (): string => $value,
                    $content
                ) ?? $content;
                $content = preg_replace_callback(
                    '/\[\s*'.$quoted.'\s*\]/i',
                    static fn (): string => $value,
                    $content
                ) ?? $content;
                $content = preg_replace_callback(
                    '/%'.$quoted.'%/i',
                    static fn (): string => $value,
                    $content
                ) ?? $content;
            }
        }

        return $content;
    }

    protected function readableText(mixed $value): string
    {
        $text = $this->text($value);

        $text = str_replace('_', ' ', $text);
        $text = preg_replace('/\s*,\s*/', ', ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    public function availableValues(): array
    {
        return ['AthleteName','AthleteFirstName','AthleteLastName','AthleteEmail','AthletePhone','GraduationYear','Position','ClubTeam','GPA','CoachName','CoachFirstName','CoachLastName','CoachTitle','CoachEmail','CoachPhone','SchoolName','Conference','Division','City','State','ProfileLink','HighlightLink','InstagramLink','YouTubeLink','XLink'];
    }

    protected function cleanUrl(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $url = preg_replace(
            '/[\x{00A0}\x{2007}\x{202F}\x{200B}\x{FEFF}]+/u',
            ' ',
            $url
        ) ?? $url;

        $url = preg_replace('/\s+/u', ' ', $url) ?? $url;

        return trim($url);
    }

    protected function text(mixed $value): string
    {
        if (is_null($value)) return '';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_scalar($value) || $value instanceof \Stringable) return trim((string) $value);

        if ($value instanceof \BackedEnum) return trim((string) $value->value);
        if ($value instanceof \UnitEnum) return $value->name;
        if ($value instanceof \Illuminate\Support\Collection) $value = $value->all();
        if ($value instanceof \JsonSerializable) $value = $value->jsonSerialize();
        if (is_object($value)) $value = get_object_vars($value);

        if (is_array($value)) {
            return collect($value)
                ->flatten()
                ->map(fn ($item): string => $this->text($item))
                ->filter(fn (string $item): bool => $item !== '')
                ->unique()
                ->implode(', ');
        }

        return '';
    }

    protected function school(User $user, array $coach): array
    {
        $id = $this->text($coach['business_id'] ?? $coach['ghl_business_id'] ?? $coach['company_id'] ?? '');
        if ($id === '') return [];

        $row = CoachDatabaseSchool::query()
            ->where('user_id', $user->id)
            ->where('ghl_location_id', trim((string) ($user->ghl_location_id ?? config('ghl.location_id') ?? '')))
            ->where('business_id', $id)
            ->first();

        return $row ? [
            'name' => $row->name,
            'conference' => $row->conference,
            'division' => $row->division,
            'city' => $row->city,
            'state' => $row->state,
        ] : [];
    }

    public function profileUrlFor(User $athlete): string
    {
        return $this->cleanUrl($this->profileUrl($athlete));
    }

    protected function profileUrl(User $athlete): string
    {
        $website = $athlete->relationLoaded('activeWebsite')
            ? $athlete->activeWebsite
            : null;

        if (! $website) {
            $website = Website::query()
                ->where('user_id', $athlete->getKey())
                ->orderByDesc('is_active')
                ->orderByDesc('is_published')
                ->latest('updated_at')
                ->first();
        }

        if (! $website) {
            return '';
        }

        $domain = trim($this->text($website->domain ?? ''));
        if ($domain !== '') {
            if (! preg_match('~^https?://~i', $domain)) {
                $domain = 'https://' . ltrim($domain, '/');
            }

            return rtrim($domain, '/');
        }

        $publicKey = trim($this->text(
            $website->slug
            ?? $website->website_name
            ?? $website->name
            ?? ''
        ));

        if ($publicKey === '') {
            return '';
        }

        return rtrim($this->publicProfileBaseUrl(), '/')
            . '/'
            . ltrim($publicKey, '/');
    }

    protected function publicProfileBaseUrl(): string
    {
        // An explicitly configured public profile domain always wins.
        $configured = trim((string) (
            config('services.profile.base_url')
            ?: config('app.public_profile_base_url')
            ?: env('PLYRCARD_PROFILE_BASE_URL')
        ));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        // Otherwise use the root domain handling the current request. This keeps
        // local testing on http://127.0.0.1:8000 and automatically uses the live
        // root domain when Compose Email is opened on production.
        $requestBaseUrl = request()?->getSchemeAndHttpHost();

        if (filled($requestBaseUrl)) {
            return rtrim((string) $requestBaseUrl, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    protected function social(string $value, string $platform): string
    {
        $value = trim($value);
        if ($value === '' || str_starts_with(strtolower($value), 'http')) return $value;
        $handle = ltrim($value, '@');
        return $platform === 'instagram' ? 'https://instagram.com/'.$handle : 'https://x.com/'.$handle;
    }
}