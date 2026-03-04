<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bebas-neue:400|poppins:300,400,500,600,700" rel="stylesheet" />

    <!-- Styles / Scripts -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @php
        /**
         * DATA
         */
        $website = $user->website;

        // GrapesJS HTML/CSS (optional)
        $css  = $website?->css ?? '';
        $html = $website?->html ?? '';

        // Colors
        $primary   = $website?->primary_color        ?: '#334155'; // slate-700
        $secondary = $website?->secondary_color      ?: '#0f172a'; // slate-900
        $accent    = $website?->accent_color         ?: '#2563eb'; // (kept, but not used for tabs now)
        $bg        = $website?->background_color     ?: '#f8fafc'; // slate-50
        $surface   = $website?->surface_color        ?: '#ffffff';
        $text1     = $website?->text_primary_color   ?: '#0f172a'; // slate-900
        $text2     = $website?->text_secondary_color ?: '#475569'; // slate-600

        // ===== YouTube URL -> embed helper =====
        $toYoutubeEmbed = function (string $url) {
            $url = trim($url);
            if ($url === '') return null;

            $videoId = null;

            // Handle youtu.be/<id>
            if (preg_match('~youtu\.be/([^?&/]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            // Handle youtube.com/watch?v=<id>
            if (!$videoId && preg_match('~v=([^&]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            // Handle youtube.com/shorts/<id>
            if (!$videoId && preg_match('~youtube\.com/shorts/([^?&/]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            // Handle youtube.com/embed/<id>
            if (!$videoId && preg_match('~youtube\.com/embed/([^?&/]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            if (!$videoId) return null;

            // Reduce overlays as much as YouTube allows (not all are removable)
            $params = http_build_query([
                'rel' => 0,
                'modestbranding' => 1,
                'playsinline' => 1,
            ]);

            return "https://www.youtube.com/embed/{$videoId}?{$params}";
        };

        $parseUrlList = function ($raw) use ($toYoutubeEmbed) {
            if (!is_string($raw) || trim($raw) === '') return [];

            // allow newline list OR csv
            $raw = str_replace(["\r\n", "\r"], "\n", $raw);
            $parts = preg_split('/\n|,/', $raw);

            $out = [];
            foreach ($parts as $p) {
                $embed = $toYoutubeEmbed(trim($p));
                if ($embed) $out[] = $embed;
            }

            // de-dupe
            return array_values(array_unique($out));
        };

        // ===== ABOUT videos =====
        $about_video_urls = $website?->yt_embed ?? '';
        $aboutVideos = $parseUrlList($about_video_urls);

        // ===== HIGHLIGHTS videos =====
        $yt_video_urls = $website?->yt_playlist_embed ?? ''; // keep your existing db field name for highlights for now
        $highlightVideos = $parseUrlList($yt_video_urls);

        // Helper: strip default placeholder content but keep layout
        $hideIfDefault = function ($value) {
            if (!is_string($value) || $value === '') return $value;
            return str_starts_with(trim($value), '[DEFAULT PLACEHOLDER:') ? '' : $value;
        };

        /**
         * Contrast helper: returns #ffffff or #0f172a depending on background
         */
        $hexToRgb = function (string $hex) {
            $hex = ltrim(trim($hex), '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            if (strlen($hex) !== 6) return [15, 23, 42]; // fallback slate-900
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

        /**
         * ABOUT TAB CONTENT
         */
        $aboutHeadline = $hideIfDefault($website?->aboutme_headline ?? '');
        $aboutTagline  = $hideIfDefault($website?->player_tagline ?? '');
        $aboutBio      = $hideIfDefault($website?->player_bio ?? '');

        /**
         * OTHER TAB HEADLINES/TAGLINES
         */
        $scheduleHeadline = $hideIfDefault($website?->schedules_headline ?? '');
        $scheduleTagline  = $hideIfDefault($website?->schedules_tagline ?? '');

        $highHeadline = $hideIfDefault($website?->highlights_headline ?? '');
        $highTagline  = $hideIfDefault($website?->highlights_tagline ?? '');

        $acadHeadline  = $hideIfDefault($website?->acad_accolades_headline ?? '');
        $acadTagline   = $hideIfDefault($website?->acad_accolades_tagline ?? '');
        $acadBody      = $hideIfDefault($website?->academic_accolades ?? '');

        $sportHeadline = $hideIfDefault($website?->sport_accolades_headline ?? '');
        $sportTagline  = $hideIfDefault($website?->sport_accolades_tagline ?? '');
        $sportBody     = $hideIfDefault($website?->sports_accolades ?? '');

        /**
         * COACHING STAFF
         */
        $playerEmail = $user->email ?? '';

        $coachRows = collect([
            [
                'name'   => $user->club_coach ?? '',
                'label'  => 'HEAD COACH',
                'title'  => $user->club?->name ?? ($user->team_name ?? ''),
                'email'  => $user->club_coach_email ?? $playerEmail,
            ],
            [
                'name'   => $user->tech_trainer ?? '',
                'label'  => 'TECHNICAL TRAINING & MENTORSHIP',
                'title'  => '',
                'email'  => $user->tech_trainer_email ?? $playerEmail,
            ],
            [
                'name'   => $user->snc_trainer ?? '',
                'label'  => 'AGILITY AND STRENGTH TRAINING',
                'title'  => '',
                'email'  => $user->snc_trainer_email ?? $playerEmail,
            ],
            [
                'name'   => $user->natl_coach ?? '',
                'label'  => 'NATIONAL TEAM COACH',
                'title'  => $user->natl_team_exp ?? '',
                'email'  => $user->natl_coach_email ?? $playerEmail,
            ],
        ])->filter(fn($c) => trim((string)($c['name'] ?? '')) !== '')
          ->values();

        /**
         * FOOTER
         */
        $logos = collect($website?->logos ?? [])->filter()->values();

        $footerLogoUrl = '';
        $first = $logos->first();

        if (is_string($first) && $first !== '') {
            $footerLogoUrl = asset('storage/' . ltrim($first, '/'));
        } elseif (is_array($first)) {
            $path = $first['url'] ?? $first['path'] ?? $first['image_url'] ?? '';
            if ($path) $footerLogoUrl = asset('storage/' . ltrim($path, '/'));
        }

        // Social URLs
        $igUrl = '';
        if (!empty($user->ig_handle)) {
            $handle = ltrim(trim($user->ig_handle), '@');
            $igUrl = 'https://instagram.com/' . $handle;
        }

        $xUrl = '';
        if (!empty($user->x_handle)) {
            $handle = ltrim(trim($user->x_handle), '@');
            $xUrl = 'https://x.com/' . $handle;
        }

        $ytUrl = $user->yt_url ?? '';

        $footerPhone = $user->phone ?? '';
        $footerEmail = $user->email ?? '';
        $copyright   = 'Plyr Card 2026 © All Rights Reserved';
    @endphp

    @if (!empty($css))
        <style>
            #website-preview { all: initial; display:block; }
            {!! str_replace(['body','html'], ['#website-preview','#website-preview'], $css) !!}
        </style>
    @endif

    <style>
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
        }

        .font-heading{
            font-family: "Bebas Neue", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        }

        .embed-responsive iframe { width: 100%; height: 100%; }

        /* Tabs: hover + active */
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

        /* Icon hover (do not change SVG markup; only style on hover) */
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

        /* Accolades list icon */
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

        /* Mobile sticky social bar spacing */
        @media (max-width: 767px){
            body{ padding-bottom: 76px; } /* leaves room for sticky bar */
        }
    </style>
</head>

<body class="">
    {{-- GrapesJS Render (optional) --}}
    @if (!empty($html))
        <div id="website-preview">
            {!! $html !!}
        </div>
    @endif

    {{-- TOP: Tabs + Right column (Responsive) --}}
    <div class="flex flex-col md:flex-row h-auto">

        {{-- LEFT COLUMN --}}
        <div class="w-full md:w-8/12 md:mt-[-50px]">

            {{-- Tabs (mobile: full-width + wraps nicely) --}}
            {{-- Tabs --}}
            <div id="tabs" class="flex flex-wrap md:flex-nowrap w-full">
                <button class="tab-btn w-1/2 md:w-auto px-5 py-3 font-semibold whitespace-nowrap text-center"
                        style="background: {{ $secondary }}; color: {{ $onSecondary }};"
                        data-tab="about">
                    ABOUT ME
                </button>

                <button class="tab-btn w-1/2 md:w-auto px-5 py-3 font-semibold whitespace-nowrap text-center"
                        style="background: {{ $primary }}; color: {{ $onPrimary }};"
                        data-tab="schedule">
                    SCHEDULE
                </button>

                <button class="tab-btn w-1/2 md:w-auto px-5 py-3 font-semibold whitespace-nowrap text-center"
                        style="background: {{ $primary }}; color: {{ $onPrimary }};"
                        data-tab="highlights">
                    HIGHLIGHTS
                </button>

                <button class="tab-btn w-1/2 md:w-auto px-5 py-3 font-semibold whitespace-nowrap text-center"
                        style="background: {{ $primary }}; color: {{ $onPrimary }};"
                        data-tab="accolades">
                    ACCOLADES
                </button>
            </div>

            {{-- Tab Content --}}
            <div class="bg-white p-6 md:p-10">

                {{-- ABOUT --}}
                <div id="tab-about" class="tab-content">
                    <h2 class="text-3xl md:text-4xl font-heading tracking-[0.17em] min-h-[2.5rem]" style="color: {{ $text1 }};">
                        {{ $aboutHeadline }}
                    </h2>

                    <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.1em]" style="color: {{ $primary }};">
                        {{ $aboutTagline }}
                    </div>

                    <div class="space-y-5 md:space-y-6 text-[16px] md:text-[17px] leading-6 min-h-[4rem]" style="color: {{ $text2 }};">
                        {!! $aboutBio !!}
                    </div>

                    {{-- About Me videos (URL list -> responsive grid) --}}
                    @if(!empty($aboutVideos))
                        <div class="mt-6 md:mt-8">
                            <div class="grid grid-cols-1 gap-6">
                                @foreach($aboutVideos as $video)
                                    <div class="w-full aspect-video rounded overflow-hidden border"
                                        style="background: {{ $bg }}; border-color: rgba(15, 23, 42, 0.12);">
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
                        </div>
                    @endif
                    
                </div>

                {{-- SCHEDULE --}}
                <div id="tab-schedule" class="tab-content hidden">
                    <h2 class="text-3xl md:text-4xl font-heading tracking-[0.17em] min-h-[2.5rem]" style="color: {{ $text1 }};">
                        {{ $scheduleHeadline }}
                    </h2>

                    <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.17em]" style="color: {{ $primary }};">
                        {{ $scheduleTagline }}
                    </div>

                    <div class="min-h-[6rem]"></div>
                </div>

                {{-- HIGHLIGHTS --}}
                <div id="tab-highlights" class="tab-content hidden">
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

                {{-- ACCOLADES --}}
                <div id="tab-accolades" class="tab-content hidden">
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

                    <div>
                        <h2 class="text-3xl md:text-4xl font-extrabold uppercase mb-3 min-h-[2.5rem]" style="color: {{ $text1 }};">
                            {{ $sportHeadline }}
                        </h2>

                        <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem]" style="color: {{ $accent }};">
                            {{ $sportTagline }}
                        </div>

                        <div class="space-y-3 text-[16px] md:text-[17px] min-h-[4rem]" style="color: {{ $text2 }};">
                            {!! $sportBody !!}
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- RIGHT COLUMN (mobile stacks under tabs content) --}}
        <div class="w-full md:w-4/12 p-6 md:p-10" style="background: {{ $primary }}; color: {{ $onPrimary }};">
            <div class="p-4 md:p-6 rounded min-h-[180px]">
                {!! $hideIfDefault($website?->contact_form_embed ?? '') !!}
            </div>
        </div>
    </div>

    {{-- COACHING STAFF --}}
    <section class="w-full">

        {{-- Header with wave --}}
        <div class="relative overflow-hidden pt-[20px]" style="background: {{ $primary }}; color: {{ $onPrimary }};">
            <div class="absolute top-0 left-0 w-full -translate-y-[1px]">
                <svg viewBox="0 0 1440 160" class="w-full h-[90px] md:h-[120px]" preserveAspectRatio="none">
                    <path
                        fill="{{ $bg }}"
                        d="M0,100L80,94C160,88,320,76,480,86C640,96,800,128,960,134C1120,140,1280,118,1360,108L1440,100L1440,0L0,0Z">
                    </path>
                </svg>
            </div>
            <div class="relative text-center py-8 md:py-30 mb-[-50px] md:mb-[-60px]">
                <h2 class="font-heading text-5xl md:text-8xl leading-none uppercase">
                    Coaching Staff
                </h2>
                <p class="text-base md:text-2xl uppercase"
                   style="font-family: Poppins, ui-sans-serif, system-ui; opacity: 0.85; letter-spacing: 0.01em;">
                    Guided by the Best in the Game
                </p>
            </div>
        </div>

        {{-- Staff grid --}}
        <div class="py-12 md:py-16 px-6 md:px-20" style="background: {{ $bg }};">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-wrap justify-center gap-10 md:gap-16">

                    @foreach ($coachRows as $coach)
                        @php
                            $name   = $coach['name'] ?? '';
                            $label  = $coach['label'] ?? '';
                            $title  = $coach['title'] ?? '';
                            $email  = $coach['email'] ?? $playerEmail;
                            $mailto = $email ? 'mailto:' . $email : '#';
                        @endphp

                        <div class="w-full sm:w-[320px] text-center">
                            <div class="font-extrabold uppercase tracking-wide text-lg" style="color: {{ $text1 }};">
                                {{ $name }}
                            </div>

                            <div class="mt-1 text-xs uppercase tracking-widest" style="color: {{ $primary }};">
                                {{ $label }}
                            </div>

                            <div class="mt-6 flex justify-center">
                                <a href="{{ $mailto }}" class="icon-link inline-flex items-center justify-center" aria-label="Email coach">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="w-12 h-12"
                                         viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="{{ $primary }}"
                                         stroke-width="1.8"
                                         stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <path d="M4 4h16v16H4z"></path>
                                        <path d="m4 6 8 6 8-6"></path>
                                    </svg>
                                </a>
                            </div>

                            <div class="mt-6 text-sm" style="color: {{ $text1 }};">
                                {{ $title }}
                            </div>
                        </div>
                    @endforeach

                </div>
            </div>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="w-full">
        <div class="py-12 md:py-16 px-6 md:px-20" style="background: {{ $primary }}; color: {{ $onPrimary }};">
            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-10 md:gap-12 items-start">

                {{-- LEFT: LOGO --}}
                <div class="flex items-center justify-start md:justify-start">
                    <div class="h-40 md:h-60 w-full md:w-auto rounded flex items-center justify-center overflow-hidden">
                        @if (!empty($footerLogoUrl))
                            <img src="{{ $footerLogoUrl }}" alt="Footer logo" class="h-full w-full object-contain p-3">
                        @else
                            <div class="h-full w-full"></div>
                        @endif
                    </div>
                </div>

                {{-- RIGHT: CONTACT --}}
                <div class="md:border-l md:pl-8 border-t md:border-t-0 pt-8 md:pt-0"
                     style="border-color: rgba(255,255,255,0.25);">
                    <h3 class="text-lg md:text-xl font-bold uppercase tracking-wide mb-6">
                        Get in Touch
                    </h3>

                    {{-- Phone --}}
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

                    {{-- Email --}}
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

                    {{-- Social (desktop only here; mobile uses sticky bar below) --}}
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

    {{-- MOBILE STICKY SOCIAL BAR (matches your screenshot behavior) --}}
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

            {{-- Optional sticky CTA like your screenshot --}}
            <a href="#"
               class="text-xs font-semibold px-4 py-2 rounded-full"
               style="background: {{ $secondary }}; color: {{ $onSecondary }};">
                TEXT COACH
            </a>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const buttons  = document.querySelectorAll(".tab-btn");
            const contents = document.querySelectorAll(".tab-content");

            const primary     = @json($primary);
            const onPrimary   = @json($onPrimary);
            const secondary   = @json($secondary);
            const onSecondary = @json($onSecondary);

            // Pointer cursor
            buttons.forEach(btn => { btn.style.cursor = "pointer"; });

            buttons.forEach(button => {
                button.addEventListener("click", function () {

                    const target = this.dataset.tab;

                    // Reset all tabs → PRIMARY
                    buttons.forEach(btn => {
                        btn.classList.remove("is-active");
                        btn.style.background = primary;
                        btn.style.color = onPrimary;
                    });

                    // Active tab → SECONDARY (full fill)
                    this.classList.add("is-active");
                    this.style.background = secondary;
                    this.style.color = onSecondary;

                    // Hide all content
                    contents.forEach(content => content.classList.add("hidden"));

                    // Show selected content
                    const active = document.getElementById("tab-" + target);
                    if (active) active.classList.remove("hidden");

                });
            });

        });
    </script>

    @if (Route::has('login'))
        <div class="h-14.5 hidden lg:block"></div>
    @endif
</body>
</html>