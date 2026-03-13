<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $user = $website->user;

        /*
        |--------------------------------------------------------------------------
        | Theme Colors
        |--------------------------------------------------------------------------
        */
        $primary   = $website->primary_color ?: '#334155';
        $secondary = $website->secondary_color ?: '#0f172a';
        $accent    = $website->accent_color ?: '#2563eb';
        $bg        = $website->background_color ?: '#f8fafc';
        $surface   = $website->surface_color ?: '#ffffff';
        $text1     = $website->text_primary_color ?: '#0f172a';
        $text2     = $website->text_secondary_color ?: '#475569';

        /*
        |--------------------------------------------------------------------------
        | Field Value Helpers
        |--------------------------------------------------------------------------
        */
        $fieldValues = $website->relationLoaded('fieldValues')
            ? $website->fieldValues
            : $website->fieldValues()->with('templateField')->get();

        $getFieldRecord = function (string $fieldName) use ($fieldValues) {
            return $fieldValues->first(function ($item) use ($fieldName) {
                return optional($item->templateField)->name === $fieldName;
            });
        };

        $getFieldValue = function (string $fieldName, $default = null) use ($getFieldRecord) {
            $record = $getFieldRecord($fieldName);
            return $record?->value ?? $default;
        };

        $getJsonFieldValue = function (string $fieldName, $default = null) use ($getFieldValue) {
            $raw = $getFieldValue($fieldName);

            if (blank($raw)) {
                return $default;
            }

            if (is_array($raw)) {
                return $raw;
            }

            $decoded = json_decode($raw, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        };

        $hideIfDefault = function ($value) {
            if (! is_string($value) || $value === '') {
                return $value;
            }

            return str_starts_with(trim($value), '[DEFAULT PLACEHOLDER:') ? '' : $value;
        };

        /*
        |--------------------------------------------------------------------------
        | Hero Field Helpers
        |--------------------------------------------------------------------------
        */
        $heroFieldValues = $website->relationLoaded('heroFieldValues')
            ? $website->heroFieldValues
            : $website->heroFieldValues()->with('templateField')->get();

        $getHeroFieldRecord = function (string $fieldName) use ($heroFieldValues) {
            return $heroFieldValues->first(function ($item) use ($fieldName) {
                return optional($item->templateField)->name === $fieldName;
            });
        };

        $getHeroFieldValue = function (string $fieldName, $default = null) use ($getHeroFieldRecord) {
            $record = $getHeroFieldRecord($fieldName);
            return $record?->value ?? $default;
        };

        /*
        |--------------------------------------------------------------------------
        | YouTube Helpers
        |--------------------------------------------------------------------------
        */
        $toYoutubeEmbed = function (string $url) {
            $url = trim($url);
            if ($url === '') {
                return null;
            }

            $videoId = null;

            if (preg_match('~youtu\.be/([^?&/]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            if (! $videoId && preg_match('~v=([^&]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            if (! $videoId && preg_match('~youtube\.com/shorts/([^?&/]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            if (! $videoId && preg_match('~youtube\.com/embed/([^?&/]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            if (! $videoId) {
                return null;
            }

            $params = http_build_query([
                'rel' => 0,
                'modestbranding' => 1,
                'playsinline' => 1,
            ]);

            return "https://www.youtube.com/embed/{$videoId}?{$params}";
        };

        $parseUrlList = function ($raw) use ($toYoutubeEmbed) {
            if (! is_string($raw) || trim($raw) === '') {
                return [];
            }

            $raw = str_replace(["\r\n", "\r"], "\n", $raw);
            $parts = preg_split('/\n|,/', $raw);

            $out = [];
            foreach ($parts as $p) {
                $embed = $toYoutubeEmbed(trim($p));
                if ($embed) {
                    $out[] = $embed;
                }
            }

            return array_values(array_unique($out));
        };

        /*
        |--------------------------------------------------------------------------
        | Media Helpers
        |--------------------------------------------------------------------------
        */
        $resolveMediaUrl = function ($raw, $fallback = '') {
            if (blank($raw)) {
                return $fallback;
            }

            if (is_string($raw)) {
                $trimmed = trim($raw);

                if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                    return $trimmed;
                }

                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $raw = $decoded;
                } else {
                    return asset('storage/' . ltrim($trimmed, '/'));
                }
            }

            if (is_array($raw)) {
                if (isset($raw[0])) {
                    $first = $raw[0];

                    if (is_string($first)) {
                        return filter_var($first, FILTER_VALIDATE_URL)
                            ? $first
                            : asset('storage/' . ltrim($first, '/'));
                    }

                    if (is_array($first)) {
                        $path = $first['url'] ?? $first['path'] ?? $first['image_url'] ?? null;

                        if ($path) {
                            return filter_var($path, FILTER_VALIDATE_URL)
                                ? $path
                                : asset('storage/' . ltrim($path, '/'));
                        }
                    }
                }

                $path = $raw['url'] ?? $raw['path'] ?? $raw['image_url'] ?? null;

                if ($path) {
                    return filter_var($path, FILTER_VALIDATE_URL)
                        ? $path
                        : asset('storage/' . ltrim($path, '/'));
                }
            }

            return $fallback;
        };

        /*
        |--------------------------------------------------------------------------
        | Content Fields
        |--------------------------------------------------------------------------
        */
        $listifyText = function ($value) {
            if (blank($value)) {
                return '';
            }

            $text = is_string($value) ? $value : (string) $value;
            $text = str_replace(["\r\n", "\r"], "\n", $text);

            $lines = collect(explode("\n", $text))
                ->map(fn ($line) => trim(strip_tags($line)))
                ->filter()
                ->values();

            if ($lines->isEmpty()) {
                return '';
            }

            return '<ul>' . $lines->map(fn ($line) => '<li>' . e($line) . '</li>')->implode('') . '</ul>';
        };

        $playerDisplayName = trim(
            ($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')
        );

        $leagueOrClub = $user?->club?->league?->name
            ?? $user?->club?->name
            ?? $user?->team_name
            ?? '';

        $gradYearText = filled($user?->year) ? "CLASS OF " . $user->year : '';

        $defaultPlayerTagline = collect([
            filled($playerDisplayName) ? strtoupper($playerDisplayName) : null,
            filled($leagueOrClub) ? strtoupper($leagueOrClub) : null,
            filled($gradYearText) ? strtoupper($gradYearText) : null,
        ])->filter(fn ($value) => filled($value))->implode(' | ');

        $aboutHeadline = $hideIfDefault($getFieldValue('aboutme_headline', ''))
            ?: 'About Me in 60 Seconds';

        $aboutTagline = $hideIfDefault($getFieldValue('player_tagline', ''))
            ?: $defaultPlayerTagline;

        $aboutBio = $hideIfDefault($getFieldValue('player_bio', ''));

        $scheduleHeadline = $hideIfDefault($getFieldValue('schedules_headline', ''))
            ?: 'Games Schedule';

        $scheduleTagline = $hideIfDefault($getFieldValue('schedules_tagline', ''))
            ?: 'Upcoming games and key dates.';

        $highHeadline = $hideIfDefault($getFieldValue('highlights_headline', ''))
            ?: 'Game Highlights';

        $highTagline = $hideIfDefault($getFieldValue('highlights_tagline', ''))
            ?: 'Top plays and standout moments.';

        $websiteAcademicAccolades = $getFieldValue('academic_accolades', '');
        $websiteSportsAccolades = $getFieldValue('sports_accolades', '');

        $acadRaw = filled($websiteAcademicAccolades)
            ? $websiteAcademicAccolades
            : ($user?->academic_accolades ?? '');

        $sportRaw = filled($websiteSportsAccolades)
            ? $websiteSportsAccolades
            : ($user?->sports_accolades ?? '');

        $acadHeadline = $hideIfDefault($getFieldValue('acad_accolades_headline', ''))
            ?: 'Academic Accolades';

        $acadTagline = $hideIfDefault($getFieldValue('acad_accolades_tagline', ''))
            ?: 'A collection of awards & accomplishments';

        $acadBody = $listifyText($hideIfDefault($acadRaw));

        $sportHeadline = $hideIfDefault($getFieldValue('sport_accolades_headline', ''))
            ?: 'Sports Accolades';

        $sportTagline = $hideIfDefault($getFieldValue('sport_accolades_tagline', ''))
            ?: 'A collection of awards & accomplishments';

        $sportBody = $listifyText($hideIfDefault($sportRaw));

        $contactFormEmbed = $hideIfDefault($getFieldValue('contact_form_embed', ''));
        $aboutVideoUrls   = $getFieldValue('yt_embed', '');
        $playlistUrls     = $getFieldValue('yt_playlist_embed', '');

        $aboutVideos     = $parseUrlList($aboutVideoUrls);
        $highlightVideos = $parseUrlList($playlistUrls);

        $aboutThumbnailUrl = $resolveMediaUrl(
            $getJsonFieldValue('highlights_thumbnail', $getFieldValue('highlights_thumbnail')),
            asset('temp-thumbnail.png')
        );

        $footerLogoUrl = $resolveMediaUrl(
            $getJsonFieldValue('logos', $getFieldValue('logos')),
            ''
        );

        /*
        |--------------------------------------------------------------------------
        | Hero Media
        |--------------------------------------------------------------------------
        */
        $heroPlyrCardUrl = $resolveMediaUrl($getHeroFieldValue('hero_plyrcard_image'), '');
        $heroBallLogoUrl = $resolveMediaUrl($getHeroFieldValue('hero_ball_logo'), '');
        $heroCompositeImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_composite_image'), '');
        $heroBackgroundImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_background_image'), '');
        $heroMobileImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_mobile_image'), '');

        /*
        |--------------------------------------------------------------------------
        | Contrast Helpers
        |--------------------------------------------------------------------------
        */
        $hexToRgb = function (string $hex) {
            $hex = ltrim(trim($hex), '#');

            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            if (strlen($hex) !== 6) {
                return [15, 23, 42];
            }

            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
            ];
        };

        $relativeLuminance = function (array $rgb) {
            $toLinear = function ($v) {
                $v = $v / 255;
                return ($v <= 0.03928) ? ($v / 12.92) : pow((($v + 0.055) / 1.055), 2.4);
            };

            $r = $toLinear($rgb[0]);
            $g = $toLinear($rgb[1]);
            $b = $toLinear($rgb[2]);

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        };

        $contrastText = function (string $bgHex) use ($hexToRgb, $relativeLuminance) {
            $lum = $relativeLuminance($hexToRgb($bgHex));
            return ($lum < 0.55) ? '#ffffff' : '#0f172a';
        };

        $onPrimary   = $contrastText($primary);
        $onSecondary = $contrastText($secondary);

        /*
        |--------------------------------------------------------------------------
        | Coaching Staff
        |--------------------------------------------------------------------------
        */
        $playerEmail = $user->email ?? '';

        $coachRows = collect([
            [
                'name'  => $user->club_coach ?? '',
                'label' => 'HEAD COACH',
                'title' => $user->club?->name ?? ($user->team_name ?? ''),
                'email' => $user->club_coach_email ?? $playerEmail,
            ],
            [
                'name'  => $user->tech_trainer ?? '',
                'label' => 'TECHNICAL TRAINING & MENTORSHIP',
                'email' => $user->tech_trainer_email ?? $playerEmail,
            ],
            [
                'name'  => $user->snc_trainer ?? '',
                'label' => 'AGILITY AND STRENGTH TRAINING',
                'email' => $user->snc_trainer_email ?? $playerEmail,
            ],
            [
                'name'  => $user->natl_coach ?? '',
                'label' => 'NATIONAL TEAM COACH',
                'email' => $user->natl_coach_email ?? $playerEmail,
            ],
        ])->filter(fn ($c) => trim((string) ($c['name'] ?? '')) !== '')->values();

        /*
        |--------------------------------------------------------------------------
        | Footer / Social
        |--------------------------------------------------------------------------
        */
        $igUrl = '';
        if (! empty($user->ig_handle)) {
            $handle = ltrim(trim($user->ig_handle), '@');
            $igUrl = 'https://instagram.com/' . $handle;
        }

        $xUrl = '';
        if (! empty($user->x_handle)) {
            $handle = ltrim(trim($user->x_handle), '@');
            $xUrl = 'https://x.com/' . $handle;
        }

        $ytUrl = $user->yt_url ?? '';
        $footerPhone = $user->phone ?? '';
        $footerEmail = $user->email ?? '';
        $copyright   = 'Plyr Card 2026 © All Rights Reserved';

        /*
        |--------------------------------------------------------------------------
        | SEO Helpers
        |--------------------------------------------------------------------------
        */
        $normalizePlainText = function ($value) {
            if (blank($value)) {
                return '';
            }

            $text = is_string($value) ? $value : json_encode($value);
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/u', ' ', $text);

            return trim((string) $text);
        };

        $truncate = function (?string $text, int $limit = 160) {
            $text = trim((string) $text);

            if ($text === '') {
                return '';
            }

            return \Illuminate\Support\Str::limit($text, $limit, '...');
        };

        $normalizeDisplayValue = function ($value, $separator = ' / ') {
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                }
            }

            if (is_array($value)) {
                return implode($separator, array_filter(array_map(function ($item) {
                    return is_scalar($item) ? (string) $item : '';
                }, $value)));
            }

            return filled($value) ? (string) $value : '';
        };

        $formatPositionDisplay = function ($value) use ($normalizeDisplayValue) {
            $position = $normalizeDisplayValue($value);

            if ($position === '') {
                return '';
            }

            return collect(explode(' / ', $position))
                ->map(fn ($item) => str($item)->replace('_', ' ')->title()->toString())
                ->implode(' / ');
        };

        /*
        |--------------------------------------------------------------------------
        | SEO Template Fields (overrides)
        |--------------------------------------------------------------------------
        */
        $seoTitleField = $normalizePlainText($getFieldValue('seo_title', ''));
        $seoDescriptionField = $normalizePlainText($getFieldValue('seo_description', ''));
        $seoKeywordsField = $normalizePlainText($getFieldValue('seo_keywords', ''));
        $seoRobotsField = $normalizePlainText($getFieldValue('seo_robots', ''));
        $seoCanonicalField = $normalizePlainText($getFieldValue('seo_canonical_url', ''));

        $ogTitleField = $normalizePlainText($getFieldValue('og_title', ''));
        $ogDescriptionField = $normalizePlainText($getFieldValue('og_description', ''));
        $ogImageField = $resolveMediaUrl($getFieldValue('og_image'), '');

        $twitterTitleField = $normalizePlainText($getFieldValue('twitter_title', ''));
        $twitterDescriptionField = $normalizePlainText($getFieldValue('twitter_description', ''));
        $twitterImageField = $resolveMediaUrl($getFieldValue('twitter_image'), '');

        $faviconField = $resolveMediaUrl($getFieldValue('favicon'), '');
        $schemaNameOverride = $normalizePlainText($getFieldValue('schema_override_name', ''));
        $schemaDescriptionOverride = $normalizePlainText($getFieldValue('schema_override_description', ''));

        /*
        |--------------------------------------------------------------------------
        | SEO Defaults
        |--------------------------------------------------------------------------
        */
        $playerName = trim(
            $website->name ?: (($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''))
        );

        $sportText = filled($user?->sport)
            ? str($user->sport)->replace('_', ' ')->title()->toString()
            : '';

        $positionText = $formatPositionDisplay($user?->position ?? '');

        $schoolName = $user?->school?->name ?? '';
        $clubName = $user?->club?->name ?? ($user?->team_name ?? '');
        $leagueName = $user?->club?->league?->name ?? '';

        $locationText = collect([$user?->city, $user?->state])
            ->filter(fn ($v) => filled($v))
            ->implode(', ');

        $coachNamesForSeo = $coachRows->pluck('name')->filter()->implode(', ');

        $canonicalUrl = filled($seoCanonicalField) ? $seoCanonicalField : url()->current();

        $defaultSeoTitle = collect([
            $playerName,
            $positionText,
            $sportText,
            filled($schoolName) ? $schoolName : null,
            'PlyrCard',
        ])->filter(fn ($v) => filled($v))->implode(' | ');

        $defaultLongDescription = collect([
            filled($playerName) ? "{$playerName} athlete profile." : null,
            filled($positionText) ? "Position: {$positionText}." : null,
            filled($sportText) ? "Sport: {$sportText}." : null,
            filled($schoolName) ? "School: {$schoolName}." : null,
            filled($clubName) ? "Club/Team: {$clubName}." : null,
            filled($leagueName) ? "League: {$leagueName}." : null,
            filled($locationText) ? "Location: {$locationText}." : null,
            filled($aboutTagline) ? $aboutTagline : null,
            filled($aboutHeadline) ? $aboutHeadline . '.' : null,
            filled($aboutBio) ? $truncate($normalizePlainText($aboutBio), 220) : null,
            filled($sportBody) ? $truncate($normalizePlainText($sportBody), 180) : null,
            filled($acadBody) ? $truncate($normalizePlainText($acadBody), 180) : null,
            filled($coachNamesForSeo) ? "Coaching staff includes {$coachNamesForSeo}." : null,
        ])->filter(fn ($v) => filled($v))->implode(' ');

        $defaultSeoDescription = $truncate($defaultLongDescription, 160);

        $defaultKeywords = collect([
            $playerName,
            $sportText,
            $positionText,
            $schoolName,
            $clubName,
            $leagueName,
            $locationText,
            'athlete profile',
            'player profile',
            'student athlete',
            'recruiting profile',
            'PlyrCard',
        ])->filter(fn ($v) => filled($v))->implode(', ');

        $seoTitle = filled($seoTitleField) ? $seoTitleField : $defaultSeoTitle;
        $seoDescription = filled($seoDescriptionField)
            ? $truncate($seoDescriptionField, 160)
            : $defaultSeoDescription;
        $seoKeywords = filled($seoKeywordsField) ? $seoKeywordsField : $defaultKeywords;
        $seoRobots = filled($seoRobotsField) ? $seoRobotsField : 'index,follow';

        $shareImage = $ogImageField
            ?: $twitterImageField
            ?: $aboutThumbnailUrl
            ?: $heroCompositeImageUrl
            ?: $heroBackgroundImageUrl
            ?: $heroMobileImageUrl
            ?: $footerLogoUrl
            ?: $heroPlyrCardUrl
            ?: asset('temp-thumbnail.png');

        $faviconUrl = $faviconField ?: asset('favicon.ico');

        $ogTitle = filled($ogTitleField) ? $ogTitleField : $seoTitle;
        $ogDescription = filled($ogDescriptionField)
            ? $truncate($ogDescriptionField, 200)
            : $seoDescription;

        $twitterTitle = filled($twitterTitleField) ? $twitterTitleField : $ogTitle;
        $twitterDescription = filled($twitterDescriptionField)
            ? $truncate($twitterDescriptionField, 200)
            : $ogDescription;

        $igHandle = filled($user?->ig_handle) ? '@' . ltrim(trim($user->ig_handle), '@') : '';
        $xHandle = filled($user?->x_handle) ? '@' . ltrim(trim($user->x_handle), '@') : '';

        $schemaName = filled($schemaNameOverride) ? $schemaNameOverride : ($playerName ?: 'PlyrCard Athlete');
        $schemaDescription = filled($schemaDescriptionOverride)
            ? $truncate($schemaDescriptionOverride, 220)
            : $truncate($defaultLongDescription, 220);

        $sameAs = collect([
            $igUrl ?: null,
            $xUrl ?: null,
            $ytUrl ?: null,
        ])->filter(fn ($v) => filled($v))->values()->all();

        $personSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $schemaName,
            'url' => $canonicalUrl,
            'description' => $schemaDescription,
            'image' => $shareImage,
        ];

        if (filled($schoolName)) {
            $personSchema['alumniOf'] = [
                '@type' => 'EducationalOrganization',
                'name' => $schoolName,
            ];
        }

        if (filled($clubName)) {
            $personSchema['memberOf'] = [
                '@type' => 'SportsOrganization',
                'name' => $clubName,
            ];
        }

        if (filled($sportText)) {
            $personSchema['sport'] = $sportText;
        }

        if (! empty($sameAs)) {
            $personSchema['sameAs'] = $sameAs;
        }

        $webPageSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'name' => $seoTitle,
            'url' => $canonicalUrl,
            'description' => $seoDescription,
            'mainEntity' => [
                '@type' => 'Person',
                'name' => $schemaName,
            ],
        ];
    @endphp

    <title>{{ $seoTitle }}</title>

    <meta name="description" content="{{ $seoDescription }}">
    <meta name="keywords" content="{{ $seoKeywords }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <meta name="theme-color" content="{{ $primary }}">
    <meta name="author" content="{{ $playerName ?: 'PlyrCard' }}">

    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">

    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="PlyrCard">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:image:secure_url" content="{{ $shareImage }}">
    <meta property="og:image:alt" content="{{ $playerName ?: 'PlyrCard athlete profile' }}">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">

    @if (filled($user?->first_name))
        <meta property="profile:first_name" content="{{ $user->first_name }}">
    @endif
    @if (filled($user?->last_name))
        <meta property="profile:last_name" content="{{ $user->last_name }}">
    @endif
    @if (filled($igHandle))
        <meta property="profile:username" content="{{ $igHandle }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $twitterTitle }}">
    <meta name="twitter:description" content="{{ $twitterDescription }}">
    <meta name="twitter:image" content="{{ $twitterImageField ?: $shareImage }}">
    @if (filled($xHandle))
        <meta name="twitter:site" content="{{ $xHandle }}">
        <meta name="twitter:creator" content="{{ $xHandle }}">
    @endif

    <meta itemprop="name" content="{{ $seoTitle }}">
    <meta itemprop="description" content="{{ $seoDescription }}">
    <meta itemprop="image" content="{{ $shareImage }}">

    <script type="application/ld+json">
{!! json_encode($personSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <script type="application/ld+json">
{!! json_encode($webPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|bebas-neue:400|iceberg:400|poppins:300,400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        #page-loader{
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity .45s ease, visibility .45s ease;
        }

        #page-loader.hidden{
            opacity:0;
            visibility:hidden;
        }

        .loader-spinner{
            width:48px;
            height:48px;
            border-radius:50%;
            border:4px solid rgba(255,255,255,0.15);
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
        }

        @keyframes spin{
            to{ transform: rotate(360deg); }
        }

        :root{
            --primary: {{ $primary }};
            --secondary: {{ $secondary }};
            --accent: {{ $accent }};
            --bg: {{ $bg }};
            --surface: {{ $surface }};
            --text1: {{ $text1 }};
            --text2: {{ $text2 }};
            --on-primary: {{ $onPrimary }};
            --on-secondary: {{ $onSecondary }};
        }

        body{
            font-family: "Poppins", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
            background: var(--bg);
            margin: 0;
        }

        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .font-heading{
            font-family: "Bebas Neue", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        }

        .embed-responsive iframe { width: 100%; height: 100%; }

        .tab-btn{
            cursor: pointer;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        .tab-btn:not(.is-active):hover{
            background: var(--secondary) !important;
            color: var(--on-secondary) !important;
        }

        .tab-btn.is-active{
            background: var(--secondary) !important;
            color: var(--on-secondary) !important;
        }

        .icon-link{
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: transparent;
        }

        .icon-link svg{
            transition: 150ms ease;
        }

        .icon-link:hover svg{
            stroke: var(--secondary);
        }

        .icon-link:hover{
            color: var(--secondary);
        }

        .acad-list ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .acad-list li {
            position: relative;
            padding-left: 30px;
            margin: 0.4rem 0;
            line-height: 1.6;
        }

        .acad-list li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0.4em;
            width: 18px;
            height: 18px;
            background-color: {{ $primary }};
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='4' y='6' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='11' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='16' width='16' height='2' rx='1' fill='black'/%3E%3C/svg%3E") no-repeat center / contain;
                    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='4' y='6' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='11' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='16' width='16' height='2' rx='1' fill='black'/%3E%3C/svg%3E") no-repeat center / contain;
        }

        @media (max-width: 767px){
            body{ padding-bottom: 76px; }
        }
    </style>
</head>

<body>
    <div id="page-loader">
        <div class="loader-spinner"></div>
    </div>

    @if ($website->heroTemplate?->blade_view)
        <div id="hero-container">
            @include($website->heroTemplate->blade_view, ['website' => $website])
        </div>
    @endif

    <div class="relative z-30 flex flex-col md:flex-row h-auto w-full">
        <div class="w-full md:w-8/12 min-w-0">
            <div id="tabs" class="flex w-full overflow-x-auto whitespace-nowrap no-scrollbar -mt-12">
                <button class="tab-btn flex-shrink-0 px-5 py-3 font-semibold text-center is-active"
                        style="background: {{ $secondary }}; color: {{ $onSecondary }};"
                        data-tab="about">
                    ABOUT ME
                </button>

                <button class="tab-btn flex-shrink-0 px-5 py-3 font-semibold text-center"
                        style="background: {{ $primary }}; color: {{ $onPrimary }};"
                        data-tab="schedule">
                    SCHEDULE
                </button>

                <button class="tab-btn flex-shrink-0 px-5 py-3 font-semibold text-center"
                        style="background: {{ $primary }}; color: {{ $onPrimary }};"
                        data-tab="highlights">
                    HIGHLIGHTS
                </button>

                <button class="tab-btn flex-shrink-0 px-5 py-3 font-semibold text-center"
                        style="background: {{ $primary }}; color: {{ $onPrimary }};"
                        data-tab="accolades">
                    ACCOLADES
                </button>
            </div>

            <div class="bg-white">
                <div id="tab-about" class="tab-content">
                    <div class="p-6 md:p-10">
                        <h2 class="text-3xl md:text-4xl font-heading tracking-[0.17em] min-h-[2.5rem]" style="color: {{ $text1 }};">
                            {{ $aboutHeadline }}
                        </h2>

                        <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.1em]" style="color: {{ $primary }};">
                            {{ $aboutTagline }}
                        </div>

                        <div class="space-y-5 md:space-y-6 text-[16px] md:text-[17px] leading-6 min-h-[4rem]" style="color: {{ $text2 }};">
                            {!! $aboutBio !!}
                        </div>
                    </div>

                    @if(!empty($aboutVideos) && !empty($aboutVideos[0]))
                        @php
                            $video = $aboutVideos[0];
                            $videoId = null;

                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtube\.com\/embed\/|youtu\.be\/)([A-Za-z0-9_-]{11})/', $video, $matches)) {
                                $videoId = $matches[1];
                            }

                            $embedUrl = $videoId
                                ? "https://www.youtube.com/embed/".$videoId."?autoplay=1&rel=0"
                                : $video;
                        @endphp

                        <div class="mt-6 md:mt-8">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="w-full aspect-video overflow-hidden relative"
                                     style="background: {{ $bg }};"
                                     id="about-video-player">
                                    <div
                                        class="relative w-full h-full cursor-pointer group"
                                        onclick="document.getElementById('about-video-player').innerHTML = `
                                            <iframe
                                                class='w-full h-full'
                                                src='{{ $embedUrl }}'
                                                title='YouTube video'
                                                frameborder='0'
                                                allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
                                                allowfullscreen>
                                            </iframe>`"
                                    >
                                        <img
                                            src="{{ $aboutThumbnailUrl }}"
                                            alt="Video Thumbnail"
                                            class="w-full h-full object-cover"
                                        />

                                        <div class="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition"></div>

                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <div class="w-20 h-20 bg-white/90 rounded-full flex items-center justify-center shadow-lg group-hover:scale-105 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 84 84" class="w-10 h-10 ml-1">
                                                    <path d="M32 25.5v33l26-16.5-26-16.5z" fill="{{ $primary }}"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div id="tab-schedule" class="tab-content hidden p-6 md:p-10">
                    <h2 class="text-3xl md:text-4xl font-heading tracking-[0.17em] min-h-[2.5rem]" style="color: {{ $text1 }};">
                        {{ $scheduleHeadline }}
                    </h2>

                    <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.17em]" style="color: {{ $primary }};">
                        {{ $scheduleTagline }}
                    </div>

                    <div class="min-h-[6rem]"></div>
                </div>

                <div id="tab-highlights" class="tab-content hidden p-6 md:p-10">
                    <div class="tracking-[0.17em] uppercase font-heading text-3xl md:text-4xl min-h-[2.5rem]" style="color: {{ $text1 }};">
                        {{ $highHeadline }}
                    </div>

                    <div class="tracking-[0.17em] text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem]" style="color: {{ $primary }};">
                        {{ $highTagline }}
                    </div>

                    @if(!empty($highlightVideos))
                        <div class="grid grid-cols-1 sm:grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                            @foreach($highlightVideos as $video)
                                <div class="w-full aspect-video rounded overflow-hidden">
                                    <iframe
                                        class="w-full h-full"
                                        src="{{ $video }}"
                                        title="YouTube video"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen>
                                    </iframe>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="min-h-[10rem]"></div>
                </div>

                <div id="tab-accolades" class="tab-content hidden p-6 md:p-10">
                    <div class="mb-8 md:mb-10">
                        <h2 class="text-3xl md:text-4xl tracking-[0.17em] font-heading uppercase min-h-[2.5rem]" style="color: {{ $text1 }};">
                            {{ $acadHeadline }}
                        </h2>

                        <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.17em]" style="color: {{ $primary }};">
                            {{ $acadTagline }}
                        </div>

                        <div class="acad-list space-y-3 text-[16px] md:text-[17px] min-h-[4rem]" style="color: {{ $text2 }};">
                            {!! $acadBody !!}
                        </div>
                    </div>

                    <div class="mb-8 md:mb-10">
                        <h2 class="text-3xl md:text-4xl tracking-[0.17em] font-heading uppercase min-h-[2.5rem]" style="color: {{ $text1 }};">
                            {{ $sportHeadline }}
                        </h2>

                        <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.17em]" style="color: {{ $primary }};">
                            {{ $sportTagline }}
                        </div>

                        <div class="acad-list space-y-3 text-[16px] md:text-[17px] min-h-[4rem]" style="color: {{ $text2 }};">
                            {!! $sportBody !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="w-full md:w-4/12 p-6 md:p-10" style="background: {{ $primary }}; color: {{ $onPrimary }};">
            <div class="p-4 md:p-6 rounded min-h-[180px]">
                {!! $contactFormEmbed !!}
            </div>
        </div>
    </div>

    <section class="w-full mt-5">
        <div class="relative pt-20 pb-24 md:pt-30 md:pb-10 overflow-hidden" style="background: {{ $primary }}; color: {{ $onPrimary }};">
            <div class="absolute top-0 left-0 w-full overflow-hidden leading-[0] z-20">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1899 357" preserveAspectRatio="none">
                    <rect width="1899" height="357" fill="{{ $primary }}"/>
                    <path d="M0,0 H1899 V38 C1715,50 1540,58 1368,52 C1215,47 1085,58 954,67 C774,79 585,58 396,40 C267,28 136,22 0,24 Z" fill="#ffffffff"/>
                    <path d="M0,20 C48,18 92,18 133,19 C106,20 62,22 0,22 Z" fill="{{ $primary }}"/>
                    <path d="M646,50 C820,58 1000,63 1147,58 C996,66 810,65 646,50 Z" fill="{{ $primary }}"/>
                    <path d="M728,69 C846,77 980,80 1087,74 C972,84 839,83 728,69 Z" fill="#ffffff" opacity="0.7"/>
                    <path d="M1065,28 C1278,29 1524,27 1818,8 C1644,26 1397,36 1065,28 Z" fill="{{ $primary }}" transform="translate(0,7)" />
                    <path d="M1868,34 C1878,33 1888,32 1899,31 V35 C1888,36 1878,36 1868,34 Z" fill="{{ $primary }}"/>
                    <rect x="0" y="344" width="1899" height="13" fill="{{ $primary }}"/>
                </svg>
            </div>

            <div class="relative text-center z-30 px-0">
                <h2 class="font-heading text-6xl md:text-[100px] leading-none uppercase tracking-tight" style="color: {{ $onPrimary }};">
                    Coaching Staff
                </h2>
                <p class="text-base md:text-[27px] uppercase tracking-[0.1rem] font-thin" style="font-family: Poppins, ui-sans-serif, system-ui; color: {{ $onPrimary }}; opacity: 0.9;">
                    Guided by the Best in the Game
                </p>
            </div>
        </div>

        <div class="py-12 md:py-16 px-6 md:px-20" style="background: {{ $bg }};">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-wrap justify-center lg:justify-between gap-y-8 gap-x-6 md:gap-x-8">
                    @foreach ($coachRows as $coach)
                        <div class="flex-1 min-w-[220px] max-w-[320px] text-center">
                            <div class="font-extrabold uppercase tracking-wide text-lg" style="color: {{ $text1 }};">
                                {{ $coach['name'] ?? '' }}
                            </div>

                            <div class="mt-1 text-xs uppercase tracking-widest" style="color: {{ $primary }};">
                                {{ $coach['label'] ?? '' }}
                            </div>

                            <div class="mt-6 flex justify-center">
                                @if(!empty($coach['email']) || !empty($playerEmail))
                                    <a href="mailto:{{ $coach['email'] ?? $playerEmail }}" class="icon-link inline-flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" viewBox="0 0 24 24" fill="none" stroke="{{ $primary }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 4h16v16H4z"></path>
                                            <path d="m4 6 8 6 8-6"></path>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <footer class="w-full">
        <div class="py-12 md:py-16 px-6 md:px-20" style="background: {{ $primary }}; color: {{ $onPrimary }};">
            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-10 md:gap-12 items-start">
                <div class="flex items-center justify-start md:justify-start">
                    <div class="h-40 md:h-60 w-full md:w-auto rounded flex items-center justify-center overflow-hidden">
                        @if (!empty($footerLogoUrl))
                            <img src="{{ $footerLogoUrl }}" alt="Footer logo" class="h-full w-full object-contain p-3">
                        @else
                            <div class="h-full w-full"></div>
                        @endif
                    </div>
                </div>

                <div class="md:border-l md:pl-8 border-t md:border-t-0 pt-8 md:pt-0"
                     style="border-color: rgba(255,255,255,0.25);">
                    <h3 class="text-lg md:text-xl font-bold uppercase tracking-wide mb-6">
                        Get in Touch
                    </h3>

                    <div class="flex items-center gap-4 mb-4 min-h-[1.5rem]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h2l2 5-2 1a11 11 0 005 5l1-2 5 2v2a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        @if (!empty($footerPhone))
                            <a href="tel:{{ preg_replace('/\D+/', '', $footerPhone) }}" class="hover:underline">
                                {{ $footerPhone }}
                            </a>
                        @else
                            <div class="flex-1"></div>
                        @endif
                    </div>

                    <div class="flex items-center gap-4 mb-6 min-h-[1.5rem]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M4 6h16a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                        </svg>
                        @if (!empty($footerEmail))
                            <a href="mailto:{{ $footerEmail }}" class="hover:underline">
                                {{ $footerEmail }}
                            </a>
                        @else
                            <div class="flex-1"></div>
                        @endif
                    </div>

                    <div class="hidden md:block">
                        <p class="text-sm uppercase tracking-wider mb-3" style="opacity: 0.8;">
                            Connect
                        </p>

                        <div class="flex items-center gap-6" style="color: {{ $onPrimary }};">
                            <a href="{{ $igUrl ?: '#' }}"
                               class="icon-link {{ empty($igUrl) ? 'pointer-events-none opacity-60' : '' }}"
                               aria-label="Instagram">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M7.75 2h8.5A5.75 5.75 0 0122 7.75v8.5A5.75 5.75 0 0116.25 22h-8.5A5.75 5.75 0 012 16.25v-8.5A5.75 5.75 0 017.75 2zm4.25 5.5A4.75 4.75 0 1016.75 12 4.76 4.76 0 0012 7.5zm0 7.8A3.05 3.05 0 1115.05 12 3.05 3.05 0 0112 15.3zm4.9-8.55a1.1 1.1 0 11-1.1-1.1 1.1 1.1 0 011.1 1.1z"/>
                                </svg>
                            </a>

                            <a href="{{ $ytUrl ?: '#' }}"
                               class="icon-link {{ empty($ytUrl) ? 'pointer-events-none opacity-60' : '' }}"
                               aria-label="YouTube">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M23.5 6.2a3 3 0 00-2.1-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.4.6A3 3 0 00.5 6.2 31.4 31.4 0 000 12a31.4 31.4 0 00.5 5.8 3 3 0 002.1 2.1c1.8.6 9.4.6 9.4.6s7.6 0 9.4-.6a3 3 0 002.1-2.1A31.4 31.4 0 0024 12a31.4 31.4 0 00-.5-5.8zM9.8 15.5v-7l6.2 3.5-6.2 3.5z"/>
                                </svg>
                            </a>

                            <a href="{{ $xUrl ?: '#' }}"
                               class="icon-link {{ empty($xUrl) ? 'pointer-events-none opacity-60' : '' }}"
                               aria-label="X">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18.244 2H21l-6.5 7.43L22 22h-6.828l-4.27-5.588L5.6 22H3l7.1-8.12L2 2h6.828l3.84 5.088L18.244 2z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center py-4 text-xs md:text-sm uppercase tracking-wider" style="background: {{ $secondary }}; color: {{ $onSecondary }};">
            {{ $copyright }}
        </div>
    </footer>

    <div class="fixed bottom-0 left-0 w-full z-50 md:hidden border-t"
         style="background: {{ $surface }}; border-color: rgba(15,23,42,0.12);">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-6" style="color: {{ $text1 }};">
                <a href="{{ $igUrl ?: '#' }}"
                   class="icon-link {{ empty($igUrl) ? 'pointer-events-none opacity-40' : '' }}"
                   aria-label="Instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M7.75 2h8.5A5.75 5.75 0 0122 7.75v8.5A5.75 5.75 0 0116.25 22h-8.5A5.75 5.75 0 012 16.25v-8.5A5.75 5.75 0 017.75 2zm4.25 5.5A4.75 4.75 0 1016.75 12 4.76 4.76 0 0012 7.5zm0 7.8A3.05 3.05 0 1115.05 12 3.05 3.05 0 0112 15.3zm4.9-8.55a1.1 1.1 0 11-1.1-1.1 1.1 1.1 0 011.1 1.1z"/>
                    </svg>
                </a>

                <a href="{{ $xUrl ?: '#' }}"
                   class="icon-link {{ empty($xUrl) ? 'pointer-events-none opacity-40' : '' }}"
                   aria-label="X">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18.244 2H21l-6.5 7.43L22 22h-6.828l-4.27-5.588L5.6 22H3l7.1-8.12L2 2h6.828l3.84 5.088L18.244 2z"/>
                    </svg>
                </a>

                <a href="{{ $ytUrl ?: '#' }}"
                   class="icon-link {{ empty($ytUrl) ? 'pointer-events-none opacity-40' : '' }}"
                   aria-label="YouTube">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M23.5 6.2a3 3 0 00-2.1-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.4.6A3 3 0 00.5 6.2 31.4 31.4 0 000 12a31.4 31.4 0 00.5 5.8 3 3 0 002.1 2.1c1.8.6 9.4.6 9.4.6s7.6 0 9.4-.6a3 3 0 002.1-2.1A31.4 31.4 0 0024 12a31.4 31.4 0 00-.5-5.8zM9.8 15.5v-7l6.2 3.5-6.2 3.5z"/>
                    </svg>
                </a>

                <a href="mailto:{{ $playerEmail ?: '#' }}"
                   class="icon-link {{ empty($playerEmail) ? 'pointer-events-none opacity-40' : '' }}"
                   aria-label="Email">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16v16H4z"></path>
                        <path d="m4 6 8 6 8-6"></path>
                    </svg>
                </a>
            </div>

            <a href="#"
               class="text-xs font-semibold px-4 py-2 rounded-full"
               style="background: {{ $secondary }}; color: {{ $onSecondary }};">
                TEXT COACH
            </a>
        </div>
    </div>

    <script>
        window.addEventListener("load", function () {
            const loader = document.getElementById("page-loader");
            const heroImages = document.querySelectorAll("#hero-container img");

            if (heroImages.length === 0) {
                loader.classList.add("hidden");
                return;
            }

            let loaded = 0;

            heroImages.forEach(img => {
                if (img.complete) {
                    loaded++;
                } else {
                    img.addEventListener("load", checkDone);
                    img.addEventListener("error", checkDone);
                }
            });

            function checkDone() {
                loaded++;
                if (loaded >= heroImages.length) {
                    loader.classList.add("hidden");
                }
            }

            if (loaded === heroImages.length) {
                loader.classList.add("hidden");
            }
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const buttons  = document.querySelectorAll(".tab-btn");
            const contents = document.querySelectorAll(".tab-content");

            const primary     = @json($primary);
            const onPrimary   = @json($onPrimary);
            const secondary   = @json($secondary);
            const onSecondary = @json($onSecondary);

            buttons.forEach(btn => {
                btn.style.cursor = "pointer";
            });

            buttons.forEach(button => {
                button.addEventListener("click", function () {
                    const target = this.dataset.tab;

                    buttons.forEach(btn => {
                        btn.classList.remove("is-active");
                        btn.style.background = primary;
                        btn.style.color = onPrimary;
                    });

                    this.classList.add("is-active");
                    this.style.background = secondary;
                    this.style.color = onSecondary;

                    contents.forEach(content => content.classList.add("hidden"));

                    const active = document.getElementById("tab-" + target);
                    if (active) {
                        active.classList.remove("hidden");
                    }
                });
            });
        });
    </script>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>