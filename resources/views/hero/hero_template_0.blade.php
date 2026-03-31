@php
    $user = $website->user;

    $primary   = $website->primary_color ?: '#cf4446';
    $secondary = $website->secondary_color ?: '#111111';
    $accent    = $website->accent_color ?: '#ffffff';
    $bg        = $website->background_color ?: '#0b0b0b';
    $surface   = $website->surface_color ?: '#171717';
    $text1     = $website->text_primary_color ?: '#ffffff';
    $text2     = $website->text_secondary_color ?: '#ffe5e5';

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

    $normalizeDisplayValue = function ($value, $separator = ' | ') {
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

    $abbreviatePositionDisplay = function ($value) use ($normalizeDisplayValue) {
        $position = $normalizeDisplayValue($value, ' | ');

        if ($position === '') {
            return '';
        }

        $map = [
            'goalkeeper' => 'GK',
            'keeper' => 'GK',
            'defender' => 'DEF',
            'center_back' => 'CB',
            'centre_back' => 'CB',
            'left_back' => 'LB',
            'right_back' => 'RB',
            'wing_back' => 'WB',
            'midfielder' => 'MID',
            'defensive_midfielder' => 'CDM',
            'central_midfielder' => 'CM',
            'attacking_midfielder' => 'CAM',
            'wide_midfielder' => 'WM',
            'forward' => 'FWD',
            'wide_forward' => 'WF',
            'striker' => 'ST',
            'winger' => 'WG',
            'left_wing' => 'LW',
            'right_wing' => 'RW',
            'point_guard' => 'PG',
            'shooting_guard' => 'SG',
            'small_forward' => 'SF',
            'power_forward' => 'PF',
            'center' => 'C',
            'setter' => 'S',
            'libero' => 'L',
            'outside_hitter' => 'OH',
            'opposite_hitter' => 'OPP',
            'middle_blocker' => 'MB',
            'quarterback' => 'QB',
            'running_back' => 'RB',
            'wide_receiver' => 'WR',
            'tight_end' => 'TE',
        ];

        return collect(explode(' | ', str_replace(' / ', ' | ', $position)))
            ->map(function ($item) use ($map) {
                $key = str($item)->lower()->replace('&', 'and')->replace('-', '_')->replace(' ', '_')->toString();
                return $map[$key] ?? str($item)->replace('_', ' ')->upper()->toString();
            })
            ->implode(' | ');
    };

    $formatCoachDisplay = function ($value) use ($normalizeDisplayValue) {
        $fullName = trim($normalizeDisplayValue($value, ' '));

        if ($fullName === '') {
            return '';
        }

        return strtoupper($fullName);
    };

    $formatDateDisplay = function ($value) use ($normalizeDisplayValue) {
        $date = trim($normalizeDisplayValue($value, ' '));

        if ($date === '') {
            return '';
        }

        try {
            return strtoupper(\Carbon\Carbon::parse($date)->format('M j, Y'));
        } catch (\Throwable $e) {
            return strtoupper($date);
        }
    };

    $formatGpaDisplay = function ($value) use ($normalizeDisplayValue) {
        $gpa = trim($normalizeDisplayValue($value, ' '));

        if ($gpa === '') {
            return '';
        }

        if (is_numeric($gpa)) {
            return number_format((float) $gpa, 1, '.', '');
        }

        return $gpa;
    };

    $playerFullName = trim($getHeroFieldValue('hero_player_name', ($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')));
    $statsTitle = strtoupper($getHeroFieldValue('hero_stats_title', 'STATISTICS'));

    $plyrCardImageUrl   = $resolveMediaUrl($getHeroFieldValue('hero_plyrcard_image', $user?->plyrcard_image), '');
    $playerImageUrl     = $resolveMediaUrl($getHeroFieldValue('hero_player_image', $user?->player_image), '');
    $mobileHeroImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_mobile_image', $user?->mobile_hero_image), '');
    $backgroundImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_background_image'), '');
    $ballLogoUrl        = $resolveMediaUrl($getHeroFieldValue('hero_ball_logo'), '');
    $brandLogoUrl       = $resolveMediaUrl($getHeroFieldValue('hero_brand_logo'), '');

    $sportRaw = $normalizeDisplayValue(
        $getHeroFieldValue(
            'hero_sport',
            $user?->sport?->name
                ?? $user?->sport
                ?? $website?->sport
                ?? $website?->sports
                ?? ''
        ),
        ' '
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

    if (! $ballLogoUrl) {
        $ballLogoUrl = $defaultBallLogoUrl;
    }

    $jerseyNumber = trim((string) $getHeroFieldValue('hero_jersey_number', $user?->jersey_number ?? ''));
    $bgJerseyNumber = trim((string) $getHeroFieldValue('hero_bg_jersey_number', $jerseyNumber));

    $hometown = $normalizeDisplayValue($getHeroFieldValue('hero_stat_hometown', ''), ' ');
    if ($hometown === '') {
        $hometown = strtoupper(collect([
            $user?->city,
            $user?->state,
        ])->filter(fn ($value) => filled($value))->implode(', '));
    }

    $clubValue = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_club', $user?->club?->name ?? $user?->team_name ?? ''),
        ' '
    );

    $leagueValue = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_league', $user?->club?->league?->name ?? ''),
        ' '
    );

    $highSchoolValue = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_high_school', $user?->school?->name ?? ''),
        ' '
    );

    $championshipValue = $normalizeDisplayValue($getHeroFieldValue('hero_stat_championship', ''), "\n");
    if (is_string($championshipValue) && str_contains($championshipValue, "\n")) {
        $championshipValue = collect(preg_split('/\r\n|\r|\n/', $championshipValue))
            ->filter(fn ($line) => filled(trim($line)))
            ->map(fn ($line) => strtoupper(trim($line)))
            ->implode('<br>');
    } else {
        $championshipValue = strtoupper((string) $championshipValue);
    }

    $stats = [
        'GPA' => strtoupper($formatGpaDisplay($getHeroFieldValue('hero_stat_gpa', $user?->gpa ?? ''))),
        'DOB' => $formatDateDisplay($getHeroFieldValue('hero_stat_dob', $user?->birth ?? '')),
        'HOMETOWN' => $hometown,
        'POSITION' => $abbreviatePositionDisplay($getHeroFieldValue('hero_stat_position', $user?->position ?? '')),
        'CLUB' => strtoupper($clubValue),
        'LEAGUE' => strtoupper($leagueValue),
        'HIGH SCHOOL' => strtoupper($highSchoolValue),
        'HEIGHT' => strtoupper($normalizeDisplayValue($getHeroFieldValue('hero_stat_height', $user?->height ?? ''), ' ')),
        'WEIGHT' => strtoupper($normalizeDisplayValue($getHeroFieldValue('hero_stat_weight', $user?->weight ?? ''), ' ')),
        'CLASS' => strtoupper($normalizeDisplayValue($getHeroFieldValue('hero_stat_class', $user?->year ?? ''), ' ')),
        'COACH' => $formatCoachDisplay($getHeroFieldValue('hero_stat_coach', $user?->club_coach ?? '')),
        'CHAMPIONSHIP' => $championshipValue,
    ];

    $nameParts = preg_split('/\s+/', trim($playerFullName)) ?: [];
    $firstName = strtoupper($nameParts[0] ?? '');
    $lastName = strtoupper(count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '');

    $displayPosition = $abbreviatePositionDisplay($getHeroFieldValue('hero_display_position', $user?->position ?? ''));
@endphp

<style>
    .font-antonio{
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
    }

    .hero-template-basic-desktop{
        display:block;
    }

    .hero-template-basic-mobile{
        display:none;
    }

    .hero-basic-stat-row:nth-child(even){
        background: rgba(255,255,255,0.035);
    }

    .hero-basic-stat-row:nth-child(odd){
        background: transparent;
    }

    @media (max-width: 1023px){
        .hero-template-basic-desktop{
            display:none;
        }

        .hero-template-basic-mobile{
            display:block;
        }
    }
</style>

<section
    class="hero-template-basic-desktop relative overflow-hidden h-screen w-full"
    style="background-color: {{ $primary }};"
>
    @if($backgroundImageUrl)
        <div class="absolute inset-0 z-0">
            <img
                src="{{ $backgroundImageUrl }}"
                alt="Hero background"
                class="h-full w-full object-cover opacity-100"
            >
        </div>
    @endif

    <div class="absolute inset-0 z-[1]" style="background:
        linear-gradient(to bottom, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.01) 35%, rgba(0,0,0,0.04) 100%),
        linear-gradient(to top, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.00) 28%);
    "></div>

    <div
        class="pointer-events-none absolute inset-x-0 bottom-0 z-[6] h-[18%]"
        style="background: linear-gradient(to top, rgba(0,0,0,0.20) 0%, rgba(0,0,0,0.08) 45%, rgba(0,0,0,0.00) 100%);"
    ></div>

    <div class="relative z-10 h-full w-full max-w-[1880px] mx-auto">
        @if($bgJerseyNumber)
            <div class="pointer-events-none absolute left-[11.2%] top-[3.8%] z-[3] font-antonio font-bold leading-none text-white/[0.10] text-[clamp(22rem,27vw,34rem)]">
                {{ $bgJerseyNumber }}
            </div>
        @endif

        <div class="absolute left-[1.2%] top-[15.9%] z-10 w-[24%]">
            @if($jerseyNumber)
                <div class="font-antonio font-bold text-white leading-[0.90] tracking-[-0.05em] text-[clamp(7.4rem,10vw,12.2rem)] mb-1">
                    #{{ ltrim($jerseyNumber, '#') }}
                </div>
            @endif

            <div class="font-antonio font-bold text-white uppercase leading-[0.90] tracking-[-0.065em] text-[clamp(5.8rem,7.8vw,9.2rem)]">
                {{ $firstName }}
            </div>

            @if($lastName)
                <div class="mt-1 font-antonio font-semibold text-white uppercase leading-[0.94] tracking-[-0.055em] text-[clamp(3.7rem,5vw,5.9rem)]">
                    {{ $lastName }}
                </div>
            @endif

            @if($displayPosition)
                <div class="mt-4 font-antonio font-light text-white uppercase leading-none tracking-[-0.02em] text-[clamp(1.7rem,2.3vw,2.8rem)]">
                    {{ $displayPosition }}
                </div>
            @endif
        </div>

        <div class="absolute inset-y-0 left-[25.5%] z-10 w-[27.5%] flex items-end justify-center pointer-events-none">
            @if($playerImageUrl)
                <img
                    src="{{ $playerImageUrl }}"
                    alt="{{ $playerFullName }}"
                    class="block h-[clamp(86%,99%,100%)] w-auto max-w-none object-contain drop-shadow-[0_26px_46px_rgba(0,0,0,0.22)]"
                >
            @endif
        </div>

        <div class="absolute right-[2.2%] top-[2.1%] z-10 w-[40.5%] h-[95.5%] flex flex-col">
    <div class="flex items-start justify-between gap-4">
        <div class="font-antonio font-light text-white uppercase tracking-[-0.04em] leading-[0.88] text-[clamp(4.1rem,5.4vw,6.4rem)]">
            {{ $statsTitle }}
        </div>

        @if($ballLogoUrl)
            <div class="pt-2">
                <img
                    src="{{ $ballLogoUrl }}"
                    alt="Ball logo"
                    class="w-[clamp(5.1rem,6.5vw,8.1rem)] h-auto object-contain"
                >
            </div>
        @endif
    </div>

    <div class="mt-[clamp(0.1rem,0.2vw,0.3rem)]">
        @foreach($stats as $label => $value)
            @if(filled(strip_tags((string) $value)))
                <div class="hero-basic-stat-row grid grid-cols-[clamp(10.5rem,12vw,13.5rem)_1fr] items-start gap-x-4 px-3 py-[0.4rem]">
                    <div class="font-antonio font-light text-white uppercase leading-[1.02] tracking-[-0.02em] text-[clamp(1.38rem,1.82vw,2.15rem)]">
                        {{ $label }}
                    </div>

                    <div class="font-antonio font-light text-white uppercase leading-[1.02] tracking-[-0.02em] text-[clamp(1.38rem,1.82vw,2.15rem)]">
                        {!! $value !!}
                    </div>
                </div>
            @endif
        @endforeach
    </div>

            <div class="mt-auto pb-4">
                @if($brandLogoUrl)
                    <img
                        src="{{ $brandLogoUrl }}"
                        alt="Brand logo"
                        class="w-[clamp(13.5rem,16vw,19rem)] h-auto object-contain"
                    >
                @else
                    <div class="font-antonio font-bold uppercase leading-none tracking-[-0.06em] text-[clamp(4.3rem,5.5vw,6.3rem)] text-white">
                        <span style="color:#111111;">PLYR</span><span style="color:#ffffff;">CARD</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="hero-template-basic-mobile w-full" style="background-color: {{ $primary }};">
    @if($mobileHeroImageUrl)
        <img
            src="{{ $mobileHeroImageUrl }}"
            alt="Mobile hero"
            class="block w-full h-auto object-cover"
        >
    @elseif($playerImageUrl)
        <div class="px-5 pt-8">
            <div class="absolute left-[5%] top-[16.4%] z-10 w-[22.5%]">
                @if($jerseyNumber)
                    <div class="font-antonio font-bold text-white leading-[0.92] tracking-[-0.05em] text-[clamp(6.8rem,9.2vw,11.2rem)] mb-1">
                        #{{ ltrim($jerseyNumber, '#') }}
                    </div>
                @endif

                <div class="font-antonio font-bold text-white uppercase leading-[0.92] tracking-[-0.065em] text-[clamp(5.2rem,7vw,8.5rem)]">
                    {{ $firstName }}
                </div>

                @if($lastName)
                    <div class="mt-1 font-antonio font-semibold text-white uppercase leading-[0.95] tracking-[-0.055em] text-[clamp(3.3rem,4.6vw,5.4rem)]">
                        {{ $lastName }}
                    </div>
                @endif

                @if($displayPosition)
                    <div class="mt-4 font-antonio font-light text-white uppercase leading-none tracking-[-0.02em] text-[clamp(1.55rem,2.1vw,2.5rem)]">
                        {{ $displayPosition }}
                    </div>
                @endif
            </div>
            <div class="mt-6 flex justify-center relative">
                <img
                    src="{{ $playerImageUrl }}"
                    alt="{{ $playerFullName }}"
                    class="relative z-10 max-w-full h-auto object-contain"
                >
            </div>

            <div class="mt-8">
                <div class="flex items-start justify-between gap-4">
                    <div class="font-antonio font-light text-white uppercase tracking-[-0.04em] text-[52px]">
                        {{ $statsTitle }}
                    </div>

                    @if($ballLogoUrl)
                        <img
                            src="{{ $ballLogoUrl }}"
                            alt="Ball logo"
                            class="w-[72px] h-auto object-contain"
                        >
                    @endif
                </div>

                <div class="mt-0">
                    @foreach($stats as $label => $value)
                        @if(filled(strip_tags((string) $value)))
                            <div class="hero-basic-stat-row grid grid-cols-[110px_1fr] items-start gap-x-4 px-3 py-2">
                                <div class="font-antonio font-light text-white uppercase leading-[1.05] tracking-[-0.02em] text-[18px]">
                                    {{ $label }}
                                </div>

                                <div class="font-antonio font-light text-white uppercase leading-[1.05] tracking-[-0.02em] text-[18px]">
                                    {!! $value !!}
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if($plyrCardImageUrl)
                    <div class="mt-6 flex justify-center">
                        <img
                            src="{{ $plyrCardImageUrl }}"
                            alt="PlyrCard"
                            class="w-[140px] h-auto object-contain"
                        >
                    </div>
                @endif

                <div class="mt-8 pb-8 text-center">
                    @if($brandLogoUrl)
                        <img
                            src="{{ $brandLogoUrl }}"
                            alt="Brand logo"
                            class="mx-auto w-[180px] h-auto object-contain"
                        >
                    @else
                        <div class="font-antonio font-bold uppercase leading-none tracking-[-0.06em] text-[56px] text-white">
                            <span style="color:#111111;">PLYR</span><span style="color:#ffffff;">CARD</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @elseif($backgroundImageUrl)
        <img
            src="{{ $backgroundImageUrl }}"
            alt="Hero fallback"
            class="block w-full h-auto object-cover"
        >
    @endif
</section>