@php
    $user = $website->user;

    $primary   = $website->primary_color ?: '#ef4444';
    $secondary = $website->secondary_color ?: '#111111';
    $accent    = $website->accent_color ?: '#ffffff';
    $bg        = $website->background_color ?: '#0b0b0b';
    $surface   = $website->surface_color ?: '#171717';
    $text1     = $website->text_primary_color ?: '#ffffff';
    $text2     = $website->text_secondary_color ?: '#d4d4d8';

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

    $formatCoachDisplay = function ($value) use ($normalizeDisplayValue) {
        $fullName = trim($normalizeDisplayValue($value));

        if ($fullName === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        $lastName = end($parts) ?: $fullName;

        return 'Coach ' . $lastName;
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

    $formatPositionDisplay = function ($value) use ($normalizeDisplayValue) {
        $position = $normalizeDisplayValue($value);

        if ($position === '') {
            return '';
        }

        return collect(explode(' / ', $position))
            ->map(fn ($item) => str($item)->replace('_', ' ')->title()->toString())
            ->implode(' / ');
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

    $playerFullName = trim($getHeroFieldValue('hero_player_name', ($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')));

    $nameParts = preg_split('/\s+/', trim($playerFullName)) ?: [];
    $firstName = strtoupper($nameParts[0] ?? '');
    $lastName  = strtoupper(count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '');

    $plyrCardImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_plyrcard_image', $user?->plyrcard_image),
        ''
    );

    $playerImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_player_image', $user?->player_image),
        ''
    );

    $playerActionImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_player_action_image'),
        ''
    );

    $playerNationalImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_player_national_image'),
        ''
    );

    $mobileHeroImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_mobile_image', $user?->mobile_hero_image),
        ''
    );

    $defaultPlayerLogosRaw = $user?->player_logos
        ?? $user?->logos_image
        ?? $user?->logo_image
        ?? null;

    $templateLogosImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_soccer_two_logos_image'),
        ''
    );

    $logosImageUrl = $resolveMediaUrl($defaultPlayerLogosRaw, '');

    if (blank($logosImageUrl)) {
        $logosImageUrl = $templateLogosImageUrl;
    }

    $bottomTeamImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_bottom_team_image'), '');

    $defaultBackgroundImageUrl = asset('hero_images/hero_one/background_soccer.png');

    $backgroundImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_background_image'),
        $defaultBackgroundImageUrl
    );

    $sportRaw = $normalizeDisplayValue(
        $getHeroFieldValue(
            'hero_sport',
            $user?->sport?->name
                ?? $user?->sport
                ?? $website?->sport
                ?? $website?->sports
                ?? ''
        )
    );

    $sportKey = str($sportRaw)
        ->lower()
        ->trim()
        ->replace('&', 'and')
        ->replace('-', '_')
        ->replace(' ', '_')
        ->replace('__', '_')
        ->toString();

    $sportBallMap = [
        'basketball' => 'basketball.png',
        'football' => 'football.png',
        'american_football' => 'football.png',
        'baseball' => 'baseball.png',
        'softball' => 'softball.png',
        'soccer' => 'soccer.png',
        'futbol' => 'soccer.png',
        'volleyball' => 'volleyball.png',
        'tennis' => 'tennis.png',
        'golf' => 'golf.png',
        'lacrosse' => 'lacrosse.png',
    ];

    $defaultBallLogoUrl = isset($sportBallMap[$sportKey])
        ? asset('hero_images/hero_one/' . $sportBallMap[$sportKey])
        : '';

    $ballLogoUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_ball_logo'),
        $defaultBallLogoUrl
    );

    $bgJerseyNumber = $getHeroFieldValue('hero_bg_jersey_number', $user?->jersey_number ?? '');

    $positionDisplay = $formatPositionDisplay($getHeroFieldValue('hero_stat_position', $user?->position ?? ''));

    $hometown = $normalizeDisplayValue($getHeroFieldValue('hero_stat_hometown', ''));
    if ($hometown === '') {
        $hometown = collect([
            $user?->city,
            $user?->state,
        ])
            ->filter(fn ($value) => filled($value))
            ->implode(', ');
    }

    $stats = [
        'GPA' => $formatGpaDisplay($getHeroFieldValue('hero_stat_gpa', $user?->gpa ?? '')),
        'DOB' => $formatDateDisplay($getHeroFieldValue('hero_stat_dob', $user?->birth ?? '')),
        'Hometown' => $hometown,
        'International' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_international', '')),
        'League' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_league', $user?->club->league?->name ?? '')),
        'High School' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_high_school', $user?->school?->name ?? '')),
        'Height' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_height', $user?->height ?? '')),
        'Weight' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_weight', $user?->weight ?? '')),
        'Class' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_class', $user?->year ?? '')),
        'Coach' => $formatCoachDisplay($getHeroFieldValue('hero_stat_coach', $user?->club_coach ?? '')),
        'Championship' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_championship', '')),
    ];
@endphp

<style>
    .font-antonio {
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
    }

    .font-iceberg {
        font-family: "Iceberg", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
    }

    .hero-desktop {
        display: block;
    }

    .hero-mobile-fallback {
        display: none;
    }

    .hero-layered-stack img {
        user-select: none;
        -webkit-user-drag: none;
    }

    .hero-player-card {
        width: 200px !important;
        max-width: 200px !important;
        height: auto !important;
        flex: 0 0 200px !important;
    }

    .hero-right-content {
        width: 100%;
        max-width: 760px;
        margin-left: auto;
        padding-top: 1.5rem;
    }

    .hero-name-and-card {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        position: relative;
    }

    .hero-name-block {
        margin: 0 !important;
        padding: 0 !important;
        min-width: 0;
    }

    .hero-position-line {
        margin-top: 4px;
        margin-bottom:10px;
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        font-weight: 300;
        font-size: 22px;
        line-height: 0.95;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.92);
    }

    .hero-card-stack {
        position: relative;
        width: 200px;
        max-width: 200px;
        flex: 0 0 200px;
        display: flex;
        justify-content: flex-end;
    }

    .hero-card-stack-inner {
        position: relative;
        width: 100%;
    }

    .hero-card-logos {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: 100%;
        display: flex;
        justify-content: center;
        pointer-events: none;
    }

    .hero-card-logos img {
        display: block;
        width: 140px;
        max-width: 140px;
        height: auto;
        object-fit: contain;
    }

    .hero-stats-block {
        margin-left: 0 !important;
        padding-left: 0 !important;
        max-width: 610px;
    }

    .hero-stats-grid {
        display: grid;
        grid-template-columns: minmax(120px, 170px) minmax(0, 1fr);
        column-gap: 1.1rem;
        row-gap: 0.45rem;
    }

    .hero-main-player-wrap {
        position: absolute;
        left: -4% !important;
        bottom: 0 !important;
        width: 78% !important;
        max-width: 78% !important;
        z-index: 2 !important;
        display: flex !important;
        align-items: flex-end !important;
        justify-content: flex-start !important;
        pointer-events: none;
    }

    .hero-main-player-img {
        width: 100% !important;
        max-width: 100% !important;
        max-height: 94vh !important;
        height: auto !important;
        object-fit: contain !important;
        object-position: bottom left !important;
        display: block !important;
    }

    .hero-action-player-wrap {
        position: absolute;
        left: 48% !important;
        bottom: 5% !important;
        width: 44% !important;
        max-width: 44% !important;
        z-index: 3 !important;
        display: flex !important;
        align-items: flex-end !important;
        justify-content: flex-start !important;
        pointer-events: none;
    }

    .hero-action-player-img {
        width: 100% !important;
        max-width: 100% !important;
        max-height: 64vh !important;
        height: auto !important;
        object-fit: contain !important;
        object-position: bottom center !important;
        display: block !important;
    }

    .hero-national-player-wrap {
        position: absolute;
        left: 36% !important;
        bottom: 16% !important;
        width: 34% !important;
        max-width: 34% !important;
        z-index: 1 !important;
        display: flex !important;
        align-items: flex-end !important;
        justify-content: center !important;
        pointer-events: none;
    }

    .hero-national-player-img {
        width: 100% !important;
        max-width: 100% !important;
        max-height: 54vh !important;
        height: auto !important;
        object-fit: contain !important;
        object-position: bottom center !important;
        display: block !important;
        opacity: 0.80 !important;
    }

    .hero-team-bottom {
        position: absolute;
        right: 0 !important;
        bottom: -2px !important;
        z-index: 4 !important;
        width: 62% !important;
        max-width: 62% !important;
        pointer-events: none;
    }

    .hero-team-bottom img {
        display: block !important;
        width: 100% !important;
        height: auto !important;
        object-fit: contain !important;
    }

    @media (min-width: 768px) {
        .hero-player-card {
            width: 200px !important;
            max-width: 200px !important;
            flex-basis: 200px !important;
        }

        .hero-right-content {
            max-width: 790px;
            padding-top: 1.25rem;
        }

        .hero-card-stack {
            width: 200px;
            max-width: 200px;
            flex-basis: 200px;
        }

        .hero-card-logos img {
            width: 150px;
            max-width: 150px;
        }

        .hero-position-line {
            font-size: 24px;
        }

        .hero-stats-block {
            max-width: 640px;
        }

        .hero-stats-grid {
            grid-template-columns: minmax(135px, 185px) minmax(0, 1fr);
            column-gap: 1.35rem;
            row-gap: 0.55rem;
        }

        .hero-main-player-wrap {
            left: -3% !important;
            bottom: 0 !important;
            width: 80% !important;
            max-width: 80% !important;
        }

        .hero-main-player-img {
            max-height: 95vh !important;
        }

        .hero-action-player-wrap {
            left: 49% !important;
            bottom: 5% !important;
            width: 45% !important;
            max-width: 45% !important;
        }

        .hero-action-player-img {
            max-height: 66vh !important;
        }

        .hero-national-player-wrap {
            left: 30% !important;
            bottom: 40% !important;
            width: 35% !important;
            max-width: 35% !important;
        }

        .hero-national-player-img {
            max-height: 56vh !important;
        }
    }

    @media (min-width: 1024px) {
        .hero-player-card {
            width: 200px !important;
            max-width: 200px !important;
            flex-basis: 200px !important;
        }

        .hero-right-content {
            max-width: 800px;
            padding-top: 0.75rem;
        }

        .hero-name-and-card {
            gap: 0.75rem;
        }

        .hero-card-stack {
            width: 200px;
            max-width: 200px;
            flex-basis: 200px;
        }

        .hero-card-logos img {
            width: 155px;
            max-width: 155px;
        }

        .hero-position-line {
            font-size: 25px;
        }

        .hero-stats-block {
            max-width: 650px;
        }

        .hero-stats-grid {
            grid-template-columns: minmax(145px, 195px) minmax(0, 1fr);
            column-gap: 1.4rem;
            row-gap: 0.55rem;
        }

        .hero-main-player-wrap {
            left: -2% !important;
            bottom: 0 !important;
            width: 82% !important;
            max-width: 82% !important;
        }

        .hero-main-player-img {
            max-height: 96vh !important;
        }

        .hero-action-player-wrap {
            left: 50% !important;
            bottom: 5% !important;
            width: 46% !important;
            max-width: 46% !important;
        }

        .hero-action-player-img {
            max-height: 68vh !important;
        }

        .hero-national-player-wrap {
            left: 30% !important;
            bottom: 40% !important;
            width: 36% !important;
            max-width: 36% !important;
        }

        .hero-national-player-img {
            max-height: 58vh !important;
        }

        .hero-team-bottom {
            width: 68% !important;
            max-width: 68% !important;
        }
    }

    @media (min-width: 1280px) {
        .hero-player-card {
            padding: 20px 20px 0 0;
            width: 200px !important;
            max-width: 200px !important;
            flex-basis: 200px !important;
        }

        .hero-right-content {
            max-width: 820px;
        }

        .hero-card-stack {
            width: 220px;
            max-width: 220px;
            flex-basis: 220px;
        }

        .hero-card-logos {
            top: calc(100% + 12px);
        }

        .hero-card-logos img {
            width: 165px;
            max-width: 165px;
        }

        .hero-position-line {
            font-size: 26px;
        }

        .hero-stats-block {
            margin-top: -40px;
            max-width: 670px;
        }

        .hero-main-player-wrap {
            left: -1% !important;
            bottom: 0 !important;
            width: 84% !important;
            max-width: 84% !important;
        }

        .hero-main-player-img {
            max-height: 98vh !important;
        }

        .hero-action-player-wrap {
            left: 40% !important;
            bottom: -10% !important;
            width: 47% !important;
            max-width: 47% !important;
        }

        .hero-action-player-img {
            max-height: 70vh !important;
        }

        .hero-national-player-wrap {
            left: 40% !important;
            bottom: 40% !important;
            width: 37% !important;
            max-width: 37% !important;
        }

        .hero-national-player-img {
            max-height: 60vh !important;
        }
    }

    @media (max-width: 1023px) {
        .hero-desktop {
            display: none;
        }

        .hero-mobile-fallback {
            display: block;
        }
    }

    @media (max-width: 1535px) {
        .hero-scale {
            transform: scale(1) !important;
            transform-origin: center center;
        }
    }

    @media (max-width: 1280px) {
        .hero-scale {
            transform: scale(1) !important;
            transform-origin: center center;
        }
    }

    @media (max-width: 1150px) {
        .hero-scale {
            transform: scale(1) !important;
            transform-origin: center center;
        }
    }
</style>

<section
    class="hero-desktop relative z-0 overflow-hidden h-[95vh] min-h-[700px] max-h-[940px]"
    style="background-color: {{ $primary }};"
>
    @if ($backgroundImageUrl)
        <div class="absolute inset-0 z-0">
            <img
                src="{{ $backgroundImageUrl }}"
                alt="Hero background"
                class="h-full w-full object-cover"
            />
        </div>
    @endif

    <div
        class="absolute inset-0 z-[1]"
        style="background:
            linear-gradient(90deg, rgba(0,0,0,0.12) 0%, rgba(0,0,0,0.06) 30%, rgba(0,0,0,0.24) 60%, rgba(0,0,0,0.46) 100%),
            radial-gradient(circle at 16% 30%, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.0) 32%);"
    ></div>

    <div
        class="absolute inset-x-0 bottom-0 z-[3] h-[58%] pointer-events-none"
        style="background: linear-gradient(to top, rgba(0,0,0,0.98) 0%, rgba(0,0,0,0.82) 22%, rgba(0,0,0,0.48) 48%, rgba(0,0,0,0.18) 68%, rgba(0,0,0,0) 100%);"
    ></div>

    <div class="hero-scale relative z-10 mx-auto h-full max-w-[1800px] px-4 md:px-8 lg:px-10">
        <div class="grid h-full grid-cols-1 lg:grid-cols-[46%_54%]">
            <div class="relative flex min-h-0 items-end">
                @if ($ballLogoUrl)
                    <div class="absolute left-2 top-5 z-30 md:left-3 md:top-6">
                        <img
                            src="{{ $ballLogoUrl }}"
                            alt="Ball logo"
                            class="h-auto w-[78px] md:w-[96px] lg:w-[118px] object-contain drop-shadow-[0_10px_24px_rgba(0,0,0,.35)]"
                        />
                    </div>
                @endif

                <div class="hero-layered-stack absolute inset-0 z-10">
                    @if ($playerNationalImageUrl)
                        <div class="hero-national-player-wrap">
                            <img
                                src="{{ $playerNationalImageUrl }}"
                                alt="{{ $playerFullName }} national team"
                                class="hero-national-player-img drop-shadow-[0_16px_30px_rgba(0,0,0,.28)]"
                            />
                        </div>
                    @endif

                    @if ($playerActionImageUrl)
                        <div class="hero-action-player-wrap">
                            <img
                                src="{{ $playerActionImageUrl }}"
                                alt="{{ $playerFullName }} action"
                                class="hero-action-player-img drop-shadow-[0_16px_30px_rgba(0,0,0,.30)]"
                            />
                        </div>
                    @endif

                    @if ($playerImageUrl)
                        <div class="hero-main-player-wrap">
                            <img
                                src="{{ $playerImageUrl }}"
                                alt="{{ $playerFullName }}"
                                class="hero-main-player-img drop-shadow-[0_18px_35px_rgba(0,0,0,.45)]"
                            />
                        </div>
                    @endif
                </div>
            </div>

            <div class="relative flex items-end justify-end md:pt-2 lg:pt-2">
                @if ($bgJerseyNumber)
                    <div class="pointer-events-none absolute left-[-90px] top-[5%] z-[1] font-iceberg text-[320px] leading-none tracking-[-0.02em] text-white/[0.05] md:text-[420px] lg:text-[520px] xl:text-[640px]">
                        {{ $bgJerseyNumber }}
                    </div>
                @endif

                <div class="hero-right-content relative z-10 h-full">
                    <div class="hero-name-and-card">
                        <div class="hero-name-block">
                            <div class="font-antonio font-light text-[60px] leading-none text-white md:text-[60px] lg:text-[60px]">
                                {{ $firstName }}
                            </div>

                            <div class="font-antonio font-bold text-[80px] leading-none text-white md:text-[80px] lg:text-[90px]">
                                {{ $lastName }}
                            </div>

                            @if (filled($positionDisplay))
                                <div class="hero-position-line">
                                    {{ $positionDisplay }}
                                </div>
                            @endif
                        </div>

                        <div class="hero-card-stack">
                            <div class="hero-card-stack-inner">
                                @if ($plyrCardImageUrl)
                                    <div class="flex justify-end">
                                        <img
                                            src="{{ $plyrCardImageUrl }}"
                                            alt="PlyrCard"
                                            class="hero-player-card object-contain drop-shadow-[0_10px_24px_rgba(0,0,0,.35)]"
                                        />
                                    </div>
                                @endif

                                @if ($logosImageUrl)
                                    <div class="hero-card-logos">
                                        <img
                                            src="{{ $logosImageUrl }}"
                                            alt="Player logos"
                                        />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="hero-stats-block">
                        <div class="hero-stats-grid">
                            @foreach ($stats as $label => $value)
                                @if (filled($value))
                                    <div class="font-antonio font-light text-[18px] uppercase leading-[1.03] tracking-[0.01em] text-white/95 md:text-[23px] lg:text-[21px] 2xl:text-[23px]">
                                        {{ $label }}
                                    </div>

                                    <div class="font-antonio font-light text-[18px] uppercase leading-[1.03] tracking-[0.01em] text-white md:text-[23px] lg:text-[21px] 2xl:text-[23px]">
                                        {{ $value }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    @if ($bottomTeamImageUrl)
                        <div class="hero-team-bottom">
                            <img
                                src="{{ $bottomTeamImageUrl }}"
                                alt="Team image"
                                class="align-bottom"
                            />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

<section class="hero-mobile-fallback w-full" style="background-color: {{ $primary }};">
    @if ($mobileHeroImageUrl)
        <img
            src="{{ $mobileHeroImageUrl }}"
            alt="Mobile hero"
            class="block w-full h-auto object-cover"
        />
    @elseif ($backgroundImageUrl)
        <img
            src="{{ $backgroundImageUrl }}"
            alt="Hero fallback"
            class="block w-full h-auto object-cover"
        />
    @endif
</section>