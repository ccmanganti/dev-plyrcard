    @php
        $user = $website->user;

        $primary = $getHeroFieldValue(
            'primary_color',
            $website->primary_color
                ?: $club?->primary_color
                ?: $defaultPrimary
        );

        $secondary = $getHeroFieldValue(
            'secondary_color',
            $website->secondary_color
                ?: $club?->secondary_color
                ?: $defaultSecondary
        );
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

            return $fullName;
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

        $abbreviatePosition = function ($value) use ($normalizeDisplayValue) {
            $position = $normalizeDisplayValue($value);

            if ($position === '') {
                return '';
            }

            $map = [
                'point_guard' => 'PG',
                'shooting_guard' => 'SG',
                'small_forward' => 'SF',
                'power_forward' => 'PF',
                'center' => 'C',
                'goalkeeper' => 'GK',
                'defender' => 'DEF',
                'midfielder' => 'MID',
                'forward' => 'FWD',
                'striker' => 'ST',
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

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                }
            }

            if (is_array($value)) {
                return collect($value)
                    ->filter()
                    ->map(function ($item) use ($map) {
                        $key = str((string) $item)->lower()->replace(' ', '_')->toString();
                        return $map[$key] ?? str((string) $item)->replace('_', ' ')->upper()->toString();
                    })
                    ->implode(' / ');
            }

            $key = str((string) $value)->lower()->replace(' ', '_')->toString();

            return $map[$key] ?? str((string) $value)->replace('_', ' ')->upper()->toString();
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
        $rightPosition = $abbreviatePosition($getHeroFieldValue('hero_right_position', $user?->position ?? 'PG'));
        $statsTitle = $getHeroFieldValue('hero_stats_title', 'STATISTICS');

        $plyrCardImageUrl   = $resolveMediaUrl($user?->plyrcard_image, '');
        $compositeImageUrl  = $resolveMediaUrl($user?->player_image, '');
        $mobileHeroImageUrl = $resolveMediaUrl($user?->mobile_hero_image, '');
        $logosImageUrl      = $resolveMediaUrl($getHeroFieldValue('hero_logos_image'), '');
        $bottomTeamImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_bottom_team_image'), '');

        $defaultBackgroundImageUrl = asset('hero_images/hero_one/background.png');

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
            'Position' => $formatPositionDisplay($getHeroFieldValue('hero_stat_position', $user?->position ?? '')),
            'International' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_international', '')),
            'League' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_league', $user?->club->league?->name ?? '')),
            'High School' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_high_school', $user?->school?->name ?? '')),
            'Height' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_height', $user?->height ?? '')),
            'Weight' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_weight', $user?->weight ?? '')),
            'Class' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_class', $user?->year ?? '')),
            'Coach' => $formatCoachDisplay($getHeroFieldValue('hero_stat_coach', $user?->club_coach ?? '')),
            'Championship' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_championship', '')),
        ];

        $nameParts = preg_split('/\s+/', trim($playerFullName)) ?: [];
        $firstName = $nameParts[0] ?? '';
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';
    @endphp

    <style>
        .font-antonio{
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        }

        .font-iceberg{
            font-family: "Iceberg", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        }

        .hero-desktop {
            display: block;
        }

        .hero-mobile-fallback {
            display: none;
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
                transform: scale(0.94);
                transform-origin: center center;
            }
        }

        @media (max-width: 1280px) {
            .hero-scale {
                transform: scale(0.88);
                transform-origin: center center;
            }
        }

        @media (max-width: 1150px) {
            .hero-scale {
                transform: scale(0.82);
                transform-origin: center center;
            }
        }

        @media (min-width: 1800px) {
            .stats-ultra-wrap {
                max-width: 1060px;
            }

            .stats-ultra-title {
                left: 12rem;
            }

            .stats-ultra-grid-wrap {
                left: 12rem;
            }

            .stats-ultra-grid {
                grid-template-columns: minmax(145px, 210px) 1fr;
                column-gap: 0.75rem;
                row-gap: 0.9rem;
            }

            .stats-ultra-team {
                width: 75%;
            }
        }

        @media (min-width: 2100px) {
            .stats-ultra-wrap {
                max-width: 1180px;
            }

            .stats-ultra-title {
                left: 20rem;
            }

            .stats-ultra-grid-wrap {
                left: 20rem;
            }

            .stats-ultra-grid {
                grid-template-columns: minmax(165px, 245px) 1fr;
                column-gap: 1rem;
                row-gap: 1rem;
            }

            .stats-ultra-team {
                width: 79%;
            }
        }

        @media (min-width: 2400px) {
            .stats-ultra-wrap {
                max-width: 1280px;
            }

            .stats-ultra-title {
                left: 30rem;
            }

            .stats-ultra-grid-wrap {
                left: 30rem;
            }

            .stats-ultra-grid {
                grid-template-columns: minmax(180px, 270px) 1fr;
                column-gap: 1.1rem;
                row-gap: 1.05rem;
            }

            .stats-ultra-team {
                width: 82%;
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
            class="absolute inset-x-0 bottom-0 z-[3] h-[56%] pointer-events-none"
            style="background: linear-gradient(to top, rgba(0,0,0,0.98) 0%, rgba(0,0,0,0.82) 22%, rgba(0,0,0,0.48) 48%, rgba(0,0,0,0.18) 68%, rgba(0,0,0,0) 100%);"
        ></div>

        <div class="hero-scale relative z-10 mx-auto h-full max-w-[1800px] px-4 md:px-8 lg:px-10">
            <div class="grid h-full grid-cols-1 lg:grid-cols-[46%_54%]">
                <div class="relative flex items-end min-h-0">
                    @if ($plyrCardImageUrl)
                        <div class="absolute left-2 top-5 z-30 md:left-3 md:top-6">
                            <img
                                src="{{ $plyrCardImageUrl }}"
                                alt="PlyrCard"
                                class="h-auto w-[78px] md:w-[96px] lg:w-[118px] object-contain drop-shadow-[0_10px_24px_rgba(0,0,0,.35)]"
                            />
                        </div>
                    @endif

                    @if ($compositeImageUrl)
                        <div class="absolute inset-0 z-10 ps-10 flex items-end justify-center lg:justify-start">
                            <img
                                src="{{ $compositeImageUrl }}"
                                alt="{{ $playerFullName }}"
                                class="max-h-[98%] w-auto max-w-[115%] object-contain drop-shadow-[0_18px_35px_rgba(0,0,0,.45)]"
                            />
                        </div>
                    @endif

                    <div class="relative z-20 w-full pb-6 pt-6 md:pb-8 lg:pb-20">
                        <div class="max-w-[420px] pl-1 md:pl-2">
                            <div class="font-antonio font-bold text-[54px] leading-[0.82] tracking-tight text-white md:text-[70px] lg:text-[55px]">
                                {{ $rightPosition }}
                            </div>

                            <div class="mt-5 font-antonio font-light text-[36px] leading-[0.86] tracking-tight text-white md:text-[50px] lg:text-[50px]">
                                {{ strtoupper($firstName) }}
                            </div>

                            <div class="mt-3 flex items-center gap-3">
                                <div class="font-antonio font-bold text-[44px] leading-[0.84] tracking-tight text-white md:text-[60px] lg:text-[50px]">
                                    {{ strtoupper($lastName) }}
                                </div>

                                @if ($logosImageUrl)
                                    <div class="pb-2 md:pb-3">
                                        <img
                                            src="{{ $logosImageUrl }}"
                                            alt="Player logos"
                                            class="h-auto max-h-[42px] md:max-h-[50px] lg:max-h-[58px] w-auto object-contain"
                                        />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative flex items-end justify-end md:pt-2 lg:pt-2">
                    @if ($bgJerseyNumber)
                        <div class="pointer-events-none absolute left-[-140px] z-[1] font-iceberg text-[340px] leading-none tracking-[-0.02em] text-white/[0.05] md:text-[460px] lg:text-[560px] xl:text-[700px]">
                            {{ $bgJerseyNumber }}
                        </div>
                    @endif

                    <div class="stats-ultra-wrap relative z-10 h-full w-full max-w-[820px]">
                        <div class="flex items-start justify-between gap-4">
                            <div class="stats-ultra-title relative font-antonio left-30 font-bold text-[70px] leading-[0.90] tracking-normal text-white md:text-[70px] lg:text-[90px] xl:text-[100px]">
                                {{ strtoupper($statsTitle) }}
                            </div>

                            @if ($ballLogoUrl)
                                <img
                                    src="{{ $ballLogoUrl }}"
                                    alt="Ball logo"
                                    class="mt-1 h-auto max-h-[72px] md:max-h-[90px] lg:max-h-[104px] w-auto object-contain"
                                />
                            @endif
                        </div>

                        <div class="stats-ultra-grid-wrap relative left-30 pl-1 md:pl-2 lg:pl-3 mt-5">
                            <div class="stats-ultra-grid relative grid grid-cols-[minmax(100px,150px)_1fr] gap-x-0 gap-y-[12px]">
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
                            <div class="stats-ultra-team pointer-events-none absolute bottom-[-2px] right-0 z-[4] w-[44%] md:w-[46%] lg:w-[70%]">
                                <img
                                    src="{{ $bottomTeamImageUrl }}"
                                    alt="Team image"
                                    class="block h-auto w-full object-contain align-bottom"
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