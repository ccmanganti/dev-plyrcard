@php
    $user = $website->user;

    $primary   = $website->primary_color ?: '#0e4f86';
    $secondary = $website->secondary_color ?: '#061a4f';
    $accent    = $website->accent_color ?: '#ffffff';
    $bg        = $website->background_color ?: '#0b0b0b';
    $surface   = $website->surface_color ?: '#171717';
    $text1     = $website->text_primary_color ?: '#ffffff';
    $text2     = $website->text_secondary_color ?: '#dbeafe';

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

    $formatDateDisplay = function ($value) use ($normalizeDisplayValue) {
        $date = trim($normalizeDisplayValue($value));

        if ($date === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('F j, Y');
        } catch (\Throwable $e) {
            return $date;
        }
    };

    $formatBornYearDisplay = function ($value) use ($normalizeDisplayValue) {
        $date = trim($normalizeDisplayValue($value));

        if ($date === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('Y');
        } catch (\Throwable $e) {
            return $date;
        }
    };

    $formatGpaDisplay = function ($value) use ($normalizeDisplayValue) {
        $gpa = trim($normalizeDisplayValue($value));

        if ($gpa === '') {
            return '';
        }

        if (is_numeric($gpa)) {
            return number_format((float) $gpa, 1, '.', '');
        }

        return $gpa;
    };

    $formatPositionDisplay = function ($value) use ($normalizeDisplayValue) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return collect($value)
                ->filter()
                ->map(fn ($item) => str((string) $item)->replace('_', ' ')->replace('-', ' ')->squish()->title()->toString())
                ->implode(', ');
        }

        $position = trim($normalizeDisplayValue($value));

        if ($position === '') {
            return '';
        }

        $parts = preg_split('/\s*\/\s*|\s*,\s*/', $position) ?: [];

        return collect($parts)
            ->filter()
            ->map(fn ($item) => str((string) $item)->replace('_', ' ')->replace('-', ' ')->squish()->title()->toString())
            ->implode(', ');
    };

    $lightenHex = function ($hex, $percent = 25) {
        $hex = ltrim((string) $hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return '#26b7d7';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = (int) round($r + ((255 - $r) * ($percent / 100)));
        $g = (int) round($g + ((255 - $g) * ($percent / 100)));
        $b = (int) round($b + ((255 - $b) * ($percent / 100)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    };

    $jerseyNumber = $normalizeDisplayValue($getHeroFieldValue('hero_jersey_number', $user?->jersey_number ?? ''));
    $firstName = trim($normalizeDisplayValue($getHeroFieldValue('hero_first_name', $user?->first_name ?? '')));
    $lastName = trim($normalizeDisplayValue($getHeroFieldValue('hero_last_name', $user?->last_name ?? '')));
    $position = $formatPositionDisplay($getHeroFieldValue('hero_position', $user?->position ?? ''));
    $dateOfBirth = $formatDateDisplay($getHeroFieldValue('hero_date_of_birth', $user?->birth ?? ''));
    $bornYear = $formatBornYearDisplay($getHeroFieldValue('hero_date_of_birth', $user?->birth ?? ''));
    $club = $normalizeDisplayValue($getHeroFieldValue('hero_club', $user?->club?->name ?? ''));
    $highSchool = $normalizeDisplayValue($getHeroFieldValue('hero_high_school', $user?->school?->name ?? ''));
    $gpa = $formatGpaDisplay($getHeroFieldValue('hero_gpa', $user?->gpa ?? ''));
    $coach = $normalizeDisplayValue($getHeroFieldValue('hero_coach', $user?->club_coach ?? ''));

    $playerCardImageUrl = $resolveMediaUrl($user?->plyrcard_image, '');
    $mobileHeroImageUrl = $resolveMediaUrl($user?->mobile_hero_image, '');
    $playerImageUrl = $resolveMediaUrl($user?->player_image, '');
    $playerActionImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_action_image'), '');

    $centerGradient = $lightenHex($primary, 24);
    $fullName = trim($firstName . ' ' . $lastName);

    $firstNameLength = mb_strlen(preg_replace('/\s+/', '', $firstName));
    $lastNameLength = mb_strlen(preg_replace('/\s+/', '', $lastName));

    $positionBesideLastName = $firstNameLength > $lastNameLength;

    $buildInstagramUrl = function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        return 'https://instagram.com/' . ltrim($value, '@');
    };

    $buildXUrl = function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        return 'https://x.com/' . ltrim($value, '@');
    };

    $igUrl = $buildInstagramUrl(
        $user?->instagram_url
        ?? $user?->instagram
        ?? $user?->ig_url
        ?? $user?->ig_handle
        ?? ''
    );

    $xUrl = $buildXUrl(
        $user?->x_url
        ?? $user?->twitter_url
        ?? $user?->twitter
        ?? $user?->x_handle
        ?? ''
    );

    $ytUrl = trim((string) ($user?->youtube_url ?? $user?->youtube ?? $user?->yt_url ?? ''));
    $playerEmail = trim((string) ($user?->email ?? ''));

    $hasAnySocial = filled($playerEmail) || filled($igUrl) || filled($ytUrl) || filled($xUrl);
@endphp
<style>
    :root{
        --hero-two-shell-max: 1720px;

        --hero-two-card-width: clamp(16rem, 16vw, 21rem);
        --hero-two-player-max-width: clamp(32rem, 34vw, 44rem);
        --hero-two-player-max-height: min(84vh, 920px);
        --hero-two-action-width: clamp(10rem, 10vw, 14rem);

        --hero-two-first-name-size: clamp(5.5rem, 7.2vw, 8.8rem);
        --hero-two-first-name-size-with-pos: clamp(5.9rem, 7.7vw, 9.1rem);
        --hero-two-last-name-size: clamp(4.8rem, 6.4vw, 7.9rem);

        --hero-two-front-jersey-size: clamp(4.5rem, 6vw, 7.2rem);
        --hero-two-back-jersey-size: clamp(22rem, 26vw, 34rem);

        --hero-two-pos-size: clamp(1.5rem, 1.9vw, 2.3rem);
        --hero-two-panel-width: clamp(360px, 35vw, 500px);
    }

    .hero-two-font-jersey-front {
        font-family: "Anton SC", "Antonio", "Anton", "Bebas Neue", Arial, sans-serif;
        font-feature-settings: "calt" 0, "liga" 0;
    }

    .hero-two-font-jersey-back {
        font-family: "Iceberg", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        font-feature-settings: "calt" 0, "liga" 0;
    }

    .hero-two-font-name {
        font-family: "Anton SC", "Antonio", "Anton", "Bebas Neue", Arial, sans-serif;
        font-feature-settings: "calt" 0, "liga" 0;
        font-weight: 400;
    }

    .hero-two-font-sans {
        font-family: "Poppins", "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .hero-two-desktop {
        display: block;
    }

    .hero-two-mobile {
        display: none;
    }

    @media (max-width: 1023px) {
        .hero-two-desktop {
            display: none;
        }

        .hero-two-mobile {
            display: block;
        }
    }

    .hero-two-shell {
        position: relative;
        width: min(100%, var(--hero-two-shell-max));
        height: 100%;
        margin: 0 auto;
        padding-inline: clamp(1.5rem, 2.5vw, 2.75rem);
    }

    .hero-two-shadow {
        filter: drop-shadow(0 18px 28px rgba(0, 0, 0, 0.26));
    }

    .hero-two-card-shadow {
        filter: drop-shadow(0 10px 24px rgba(0, 0, 0, 0.22));
    }

    .hero-two-name-line {
        line-height: 0.84;
        white-space: nowrap;
    }

    .hero-two-stat-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .55rem .75rem;
    }

    .hero-two-stat-block {
        display: block;
    }

    .hero-two-stat-block .hero-two-stat-label {
        display: block;
        margin-bottom: .32rem;
    }

    .hero-two-stat-block .hero-two-stat-value {
        display: block;
        width: 100%;
        min-width: 0;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .hero-two-stat-label {
        font-size: clamp(14px, .9vw, 18px);
        line-height: 1.05;
        font-weight: 800;
        text-transform: uppercase;
        color: rgba(255,255,255,.98);
        flex: 0 0 auto;
    }

    .hero-two-stat-value {
        font-size: clamp(15px, 1vw, 19px);
        line-height: 1.32;
        font-weight: 500;
        color: rgba(255,255,255,.98);
        flex: 1 1 220px;
        min-width: 0;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .hero-two-info-panel {
        position: relative;
        width: var(--hero-two-panel-width);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 16px;
        padding: 1.2rem 1.35rem 1.3rem;
        background:
            linear-gradient(90deg,
                rgba(11, 73, 154, .78) 0%,
                rgba(20, 97, 182, .58) 45%,
                rgba(17, 69, 145, .46) 100%);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        box-shadow: 0 16px 34px rgba(0,0,0,.18);
    }

    .hero-two-social-floating {
        position: absolute;
        top: 1rem;
        right: 1rem;
        z-index: 80;
        display: flex;
        align-items: center;
        gap: 12px;
        pointer-events: auto;
    }

    .hero-two-social-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        color: rgba(255,255,255,.92);
        transition: opacity .2s ease, transform .2s ease;
        text-decoration: none;
        flex: 0 0 auto;
    }

    .hero-two-social-link:hover {
        opacity: 1;
        transform: translateY(-1px);
    }

    .hero-two-social-link.is-disabled {
        opacity: .35;
        pointer-events: none;
    }

    .hero-two-social-link svg {
        width: 28px;
        height: 28px;
        display: block;
    }

    .hero-two-position {
        white-space: nowrap;
        line-height: 1;
        color: rgba(255,255,255,.95);
        font-size: var(--hero-two-pos-size);
    }

    .hero-two-left-group {
        position: absolute;
        left: clamp(2rem, 3vw, 3rem);
        top: clamp(2rem, 4.2vh, 3.2rem);
        z-index: 20;
        width: min(54%, 860px);
    }

    .hero-two-info-wrap {
        margin-top: clamp(1rem, 1.5vw, 1.5rem);
        position: relative;
        z-index: 24;
    }

    .hero-two-name-first {
        font-size: var(--hero-two-first-name-size);
    }

    .hero-two-name-first.has-inline-position {
        font-size: var(--hero-two-first-name-size-with-pos);
    }

    .hero-two-name-last {
        font-size: var(--hero-two-last-name-size);
    }

    .hero-two-front-jersey {
        font-size: var(--hero-two-front-jersey-size);
        line-height: 1.02;
    }

    .hero-two-back-jersey {
        font-size: var(--hero-two-back-jersey-size);
        line-height: 1;
    }

    .hero-two-card {
        width: var(--hero-two-card-width);
    }

    .hero-two-card-wrap {
        position: absolute;
        right: clamp(1.5rem, 2.4vw, 2.4rem);
        top: clamp(2rem, 4.2vh, 3rem);
        z-index: 25;
    }

    .hero-two-player-wrap {
        position: absolute;
        right: clamp(12rem, 18vw, 19rem);
        bottom: 0;
        z-index: 12;
    }

    .hero-two-player-image {
        max-height: var(--hero-two-player-max-height);
        max-width: var(--hero-two-player-max-width);
        width: auto;
        height: auto;
        object-fit: contain;
    }

    .hero-two-action-wrap {
        position: absolute;
        left: clamp(37%, 40%, 42%);
        bottom: -26px;
        transform: translateX(-50%);
        z-index: 32;
        pointer-events: none;
    }

    .hero-two-action-image {
        width: var(--hero-two-action-width);
        height: auto;
        object-fit: contain;
    }

    @media (max-width: 1536px) {
        .hero-two-left-group {
            width: min(56%, 820px);
        }

        .hero-two-player-wrap {
            right: clamp(10rem, 15vw, 16rem);
        }
    }

    @media (max-width: 1366px) {
        .hero-two-left-group {
            width: min(58%, 780px);
        }

        .hero-two-player-wrap {
            right: 8.5rem;
        }

        .hero-two-card {
            width: clamp(15rem, 15vw, 18rem);
        }

        .hero-two-player-image {
            max-width: clamp(29rem, 33vw, 39rem);
        }
    }

    @media (max-width: 1180px) {
        .hero-two-shell {
            padding-inline: 1.2rem;
        }

        .hero-two-left-group {
            left: 1.6rem;
            width: min(60%, 720px);
        }

        .hero-two-card-wrap {
            right: 1.4rem;
        }

        .hero-two-player-wrap {
            right: 6rem;
        }

        .hero-two-social-floating {
            gap: 10px;
        }

        .hero-two-social-link {
            width: 28px;
            height: 28px;
        }

        .hero-two-social-link svg {
            width: 24px;
            height: 24px;
        }
    }

    @media (min-width: 1800px) {
        :root{
            --hero-two-shell-max: 1820px;
            --hero-two-card-width: clamp(18rem, 15vw, 22rem);
            --hero-two-player-max-width: clamp(36rem, 32vw, 46rem);
            --hero-two-player-max-height: min(86vh, 980px);
            --hero-two-action-width: clamp(11rem, 9vw, 15rem);
            --hero-two-first-name-size: clamp(6.4rem, 6.7vw, 9.2rem);
            --hero-two-first-name-size-with-pos: clamp(6.8rem, 7vw, 9.5rem);
            --hero-two-last-name-size: clamp(5.5rem, 5.8vw, 8.2rem);
            --hero-two-front-jersey-size: clamp(5rem, 5.2vw, 7.6rem);
            --hero-two-back-jersey-size: clamp(25rem, 24vw, 36rem);
            --hero-two-pos-size: clamp(1.8rem, 1.8vw, 2.45rem);
            --hero-two-panel-width: clamp(390px, 24vw, 480px);
        }

        .hero-two-player-wrap {
            right: 15rem;
        }
    }

    @media (min-width: 2200px) {
        :root{
            --hero-two-shell-max: 1940px;
            --hero-two-player-max-width: clamp(38rem, 30vw, 48rem);
            --hero-two-back-jersey-size: clamp(27rem, 22vw, 38rem);
            --hero-two-panel-width: clamp(410px, 22vw, 500px);
        }

        .hero-two-left-group {
            width: min(52%, 900px);
        }

        .hero-two-player-wrap {
            right: 16rem;
        }

        .hero-two-card-wrap {
            right: 2rem;
        }
    }
</style>

<section
    class="hero-two-desktop relative z-0 overflow-hidden h-[100vh] min-h-[720px] max-h-[1200px]"
    style="background:
        radial-gradient(circle at center, {{ $centerGradient }} 0%, {{ $primary }} 48%, {{ $secondary }} 100%);
        color: {{ $text1 }};"
>
    <div class="absolute inset-0 z-0 pointer-events-none">
        <div class="absolute inset-x-0 bottom-0 h-[34%]" style="background: linear-gradient(to top, rgba(0,0,0,.26), rgba(0,0,0,0));"></div>
        <div class="absolute inset-y-0 left-0 w-[16%]" style="background: linear-gradient(to right, rgba(0, 12, 70, .44), rgba(0, 12, 70, 0));"></div>
        <div class="absolute inset-y-0 right-0 w-[16%]" style="background: linear-gradient(to left, rgba(0, 12, 70, .34), rgba(0, 12, 70, 0));"></div>
    </div>

    <div class="hero-two-shell relative z-10">
        @if ($jerseyNumber)
            <div class="pointer-events-none absolute left-[60%] top-[54%] z-[1] -translate-x-1/2 -translate-y-1/2 hero-two-font-jersey-back hero-two-back-jersey tracking-[-0.03em] text-white/[0.14]">
                {{ $jerseyNumber }}
            </div>
        @endif

        @if ($playerCardImageUrl)
            <div class="hero-two-card-wrap">
                <img
                    src="{{ $playerCardImageUrl }}"
                    alt="Player card"
                    class="hero-two-card-shadow hero-two-card object-contain"
                />
            </div>
        @endif

        <div class="hero-two-left-group">
            @if ($jerseyNumber)
                <div class="hero-two-font-jersey-front hero-two-front-jersey tracking-[-0.04em] text-white">
                    #{{ $jerseyNumber }}
                </div>
            @endif

            <div>
                @if ($firstName)
                    <div class="flex items-end gap-4">
                        <div class="hero-two-font-name hero-two-name-line hero-two-name-first text-white {{ ! $positionBesideLastName && $position ? 'has-inline-position' : '' }}">
                            {{ $firstName }}
                        </div>

                        @if ($position && ! $positionBesideLastName)
                            <div class="hero-two-font-sans hero-two-position mb-[0.75rem]">
                                {{ $position }}
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex items-end gap-4 mt-3">
                    @if ($lastName)
                        <div class="hero-two-font-name hero-two-name-line hero-two-name-last text-white">
                            {{ $lastName }}
                        </div>
                    @endif

                    @if ($position && $positionBesideLastName)
                        <div class="hero-two-font-sans hero-two-position mb-[0.7rem]">
                            {{ $position }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="hero-two-info-wrap">
                <div class="hero-two-info-panel">
                    @if ($hasAnySocial)
                        <div class="hero-two-social-floating">
                            <a href="{{ $playerEmail ? 'mailto:' . $playerEmail : '#' }}"
                               class="hero-two-social-link {{ empty($playerEmail) ? 'is-disabled' : '' }}"
                               aria-label="Email">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 5.5h18v13H3z"></path>
                                    <path d="m4 7 8 6 8-6"></path>
                                </svg>
                            </a>

                            <a href="{{ $igUrl ?: '#' }}"
                               class="hero-two-social-link {{ empty($igUrl) ? 'is-disabled' : '' }}"
                               aria-label="Instagram"
                               target="{{ !empty($igUrl) ? '_blank' : '_self' }}"
                               rel="{{ !empty($igUrl) ? 'noopener noreferrer' : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.15" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="2.75" y="2.75" width="18.5" height="18.5" rx="5.25" ry="5.25"></rect>
                                    <circle cx="12" cy="12" r="4.2"></circle>
                                    <circle cx="17.35" cy="6.65" r="1.15" fill="currentColor" stroke="none"></circle>
                                </svg>
                            </a>

                            <a href="{{ $ytUrl ?: '#' }}"
                               class="hero-two-social-link {{ empty($ytUrl) ? 'is-disabled' : '' }}"
                               aria-label="YouTube"
                               target="{{ !empty($ytUrl) ? '_blank' : '_self' }}"
                               rel="{{ !empty($ytUrl) ? 'noopener noreferrer' : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.4.6A3 3 0 0 0 .5 6.2 31.4 31.4 0 0 0 0 12a31.4 31.4 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.8.6 9.4.6 9.4.6s7.6 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.4 31.4 0 0 0 24 12a31.4 31.4 0 0 0-.5-5.8ZM9.8 15.5v-7l6.2 3.5-6.2 3.5Z"/>
                                </svg>
                            </a>

                            <a href="{{ $xUrl ?: '#' }}"
                               class="hero-two-social-link {{ empty($xUrl) ? 'is-disabled' : '' }}"
                               aria-label="X"
                               target="{{ !empty($xUrl) ? '_blank' : '_self' }}"
                               rel="{{ !empty($xUrl) ? 'noopener noreferrer' : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                    <path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865z"/>
                                </svg>
                            </a>
                        </div>
                    @endif

                    <div class="hero-two-font-sans space-y-3">
                        <div class="hero-two-stat-row {{ $hasAnySocial ? 'pr-[190px]' : '' }}">
                            <div class="hero-two-stat-label">Full Name</div>
                            <div class="hero-two-stat-value">{{ $fullName }}</div>
                        </div>

                        @if ($bornYear || $dateOfBirth)
                            <div class="hero-two-stat-row">
                                <div class="hero-two-stat-label">Born</div>
                                <div class="hero-two-stat-value">{{ $bornYear ?: $dateOfBirth }}</div>
                            </div>
                        @endif

                        @if ($club)
                            <div class="hero-two-stat-row">
                                <div class="hero-two-stat-label">Club</div>
                                <div class="hero-two-stat-value">{{ $club }}</div>
                            </div>
                        @endif

                        @if ($highSchool || $gpa)
                            <div class="hero-two-stat-block">
                                <div class="hero-two-stat-label">High School</div>
                                <div class="hero-two-stat-value">
                                    @if ($highSchool && $gpa)
                                        {{ $highSchool }} - GPA {{ $gpa }}
                                    @elseif ($highSchool)
                                        {{ $highSchool }}
                                    @else
                                        GPA {{ $gpa }}
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($coach)
                            <div class="hero-two-stat-block">
                                <div class="hero-two-stat-label">Coach</div>
                                <div class="hero-two-stat-value uppercase font-semibold tracking-[0.02em]">{{ $coach }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($playerImageUrl)
            <div class="pointer-events-none hero-two-player-wrap">
                <img
                    src="{{ $playerImageUrl }}"
                    alt="{{ $fullName }}"
                    class="hero-two-shadow hero-two-player-image"
                />
            </div>
        @endif

        @if ($playerActionImageUrl)
            <div class="hero-two-action-wrap">
                <img
                    src="{{ $playerActionImageUrl }}"
                    alt="Player action image"
                    class="hero-two-shadow hero-two-action-image"
                />
            </div>
        @endif
    </div>
</section>

<section
    class="hero-two-mobile relative overflow-hidden h-[100svh] min-h-[100svh]"
    style="background: {{ $primary }};"
>
    @if ($mobileHeroImageUrl)
        <img
            src="{{ $mobileHeroImageUrl }}"
            alt="Mobile hero"
            class="absolute inset-0 block w-full h-full object-cover"
        />
    @endif
</section>