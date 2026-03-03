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

        // Colors (NON-RED defaults)
        $primary   = $website?->primary_color        ?: '#334155'; // slate-700
        $secondary = $website?->secondary_color      ?: '#0f172a'; // slate-900
        $accent    = $website?->accent_color         ?: '#2563eb'; // blue-600
        $bg        = $website?->background_color     ?: '#f8fafc'; // slate-50
        $surface   = $website?->surface_color        ?: '#ffffff';
        $text1     = $website?->text_primary_color   ?: '#0f172a'; // slate-900
        $text2     = $website?->text_secondary_color ?: '#475569'; // slate-600

        // YouTube embeds
        $ytEmbed         = $website?->yt_embed ?? '';
        $ytPlaylistEmbed = $website?->yt_playlist_embed ?? '';

        // Helper: strip default placeholder content but keep layout
        $hideIfDefault = function ($value) {
            if (!is_string($value) || $value === '') return $value;
            return str_starts_with(trim($value), '[DEFAULT PLACEHOLDER:') ? '' : $value;
        };

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
         * COACHING STAFF (FROM USER MODEL FIELDS)
         * Include ONLY if name is not empty.
         * Email rule:
         * - Use the specific coach email field when available
         * - Otherwise fall back to player's email ($user->email)
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
         * FOOTER (FROM USER + WEBSITE)
         * - ONE logo container only (use first logo in Website->logos)
         * - Social links from user fields (ig_handle/x_handle/yt_url)
         */
        $logos = collect($website?->logos ?? [])->values();

        // FIRST LOGO ONLY
        $footerLogoUrl = '';
        $firstLogo = $logos->first();

        if (is_string($firstLogo)) {
            $footerLogoUrl = $firstLogo;
        } elseif (is_array($firstLogo)) {
            $footerLogoUrl = $firstLogo['url'] ?? $firstLogo['path'] ?? $firstLogo['image_url'] ?? '';
        }
        $footerLogoUrl = $hideIfDefault($footerLogoUrl);

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

        // Footer contact
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
        }

        body{
            font-family: "Poppins", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
        }

        .font-heading{
            font-family: "Bebas Neue", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        }

        .embed-responsive iframe { width: 100%; height: 100%; }

        .logo-slot{
            background: rgba(255,255,255,0.14);
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

    /* Custom 3-line icon */
    .acad-list li::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0.4em;
        width: 18px;
        height: 18px;

        background-color: {{ $accent }}; /* ✅ Dynamic color */

        -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='4' y='6' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='11' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='16' width='16' height='2' rx='1' fill='black'/%3E%3C/svg%3E") no-repeat center / contain;

                mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='4' y='6' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='11' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='16' width='16' height='2' rx='1' fill='black'/%3E%3C/svg%3E") no-repeat center / contain;
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

    {{-- TOP: Tabs + Right column --}}
    <div class="flex h-auto">

        {{-- LEFT COLUMN --}}
        <div class="w-2/3 mt-[-50px]">

            {{-- Tabs --}}
            <div class="flex" id="tabs">
                <button class="tab-btn px-6 py-3 font-semibold transition-all bg-white text-gray-900" data-tab="about">
                    ABOUT ME
                </button>

                <button class="tab-btn px-6 py-3 font-semibold transition-all text-white" style="background: {{ $primary }};" data-tab="schedule">
                    SCHEDULE
                </button>

                <button class="tab-btn px-6 py-3 font-semibold transition-all text-white" style="background: {{ $primary }};" data-tab="highlights">
                    HIGHLIGHTS
                </button>

                <button class="tab-btn px-6 py-3 font-semibold transition-all text-white" style="background: {{ $primary }};" data-tab="accolades">
                    ACCOLADES
                </button>
            </div>

            {{-- Tab Content --}}
            <div class="bg-white p-10">

                {{-- ABOUT --}}
                <div id="tab-about" class="tab-content">
                    <h2 class="text-4xl font-heading tracking-[0.17em] mb-3 min-h-[2.5rem]" style="color: {{ $text1 }};">
                        {{ $aboutHeadline }}
                    </h2>

                    <div class="text-lg mb-6 min-h-[1.75rem] tracking-[0.1em]" style="color: {{ $accent }};">
                        {{ $aboutTagline }}
                    </div>

                    <div class="space-y-6 text-[17px] leading-8 min-h-[4rem]" style="color: {{ $text2 }};">
                        {!! $aboutBio !!}
                    </div>

                    {{-- YouTube embed under bio (slot always visible) --}}
                    <div class="mt-8">
                        <div class="embed-responsive w-full aspect-video border rounded overflow-hidden"
                             style="background: {{ $bg }}; border-color: rgba(15, 23, 42, 0.12);">
                            {!! $ytEmbed ?: ($ytPlaylistEmbed ?: '<div class="w-full h-full"></div>') !!}
                        </div>
                    </div>
                </div>

                {{-- SCHEDULE --}}
                <div id="tab-schedule" class="tab-content hidden">
                    <h2 class="text-4xl font-heading tracking-[0.17em] mb-3 min-h-[2.5rem]" style="color: {{ $text1 }};">
                        {{ $scheduleHeadline }}
                    </h2>

                    <div class="text-lg mb-6 min-h-[1.75rem] tracking-[0.17em]" style="color: {{ $accent }};">
                        {{ $scheduleTagline }}
                    </div>

                    <div class="min-h-[6rem]"></div>
                </div>

                {{-- HIGHLIGHTS --}}
                <div id="tab-highlights" class="tab-content hidden">
                    <div class="tracking-[0.17em] uppercase font-heading text-4xl mb-2 min-h-[2.5rem]" style="color: {{ $text1 }};">
                        {{ $highHeadline }}
                    </div>

                    <div class="tracking-[0.17em] text-lg mb-6 min-h-[1.75rem]" style="color: {{ $accent }};">
                        {{ $highTagline }}
                    </div>

                    <div class="min-h-[10rem]"></div>
                </div>

                {{-- ACCOLADES --}}
                <div id="tab-accolades" class="tab-content hidden">
                    <div class="mb-10">
                        <h2 class="text-4xl tracking-[0.17em] font-heading uppercase mb-3 min-h-[2.5rem]" style="color: {{ $text1 }};">
                            {{ $acadHeadline }}
                        </h2>

                        <div class="text-lg mb-6 min-h-[1.75rem] tracking-[0.17em]" style="color: {{ $accent }};">
                            {{ $acadTagline }}
                        </div>

                        <div class="acad-list space-y-3 text-[17px] min-h-[4rem]" style="color: {{ $text2 }};">
                            {!! $acadBody !!}
                        </div>
                    </div>

                    <div>
                        <h2 class="text-4xl font-extrabold uppercase mb-3 min-h-[2.5rem]" style="color: {{ $text1 }};">
                            {{ $sportHeadline }}
                        </h2>

                        <div class="text-lg mb-6 min-h-[1.75rem]" style="color: {{ $accent }};">
                            {{ $sportTagline }}
                        </div>

                        <div class="space-y-3 text-[17px] min-h-[4rem]" style="color: {{ $text2 }};">
                            {!! $sportBody !!}
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- RIGHT COLUMN --}}
        <div class="w-1/3 text-white p-10" style="background: {{ $secondary }};">
            <!-- <h2 class="text-2xl font-bold mb-6">
                Interested in Recruiting?
            </h2> -->

            <div class="p-6 rounded min-h-[180px]">
                {!! $hideIfDefault($website?->contact_form_embed ?? '') !!}
            </div>
        </div>
    </div>

    {{-- COACHING STAFF (MATCH SCREENSHOT + DYNAMIC) --}}
    <section class="w-full">

        {{-- Header with wave --}}
        <div class="relative overflow-hidden pt-[20px]" style="background: {{ $secondary }}; color: #ffff;">
            <div class="absolute top-0 left-0 w-full -translate-y-[1px]">
                <svg viewBox="0 0 1440 100" class="w-full h-[70px] md:h-[90px]" preserveAspectRatio="none">
                    <path
                        fill="{{ $bg }}"
                        d="M0,64L80,58.7C160,53,320,43,480,53.3C640,64,800,96,960,101.3C1120,107,1280,85,1360,74.7L1440,64L1440,0L0,0Z">
                    </path>
                </svg>
            </div>

            <div class="relative text-center py-10 md:py-30 mb-[-50px]">
                <h2 class="font-heading text-6xl md:text-8xl leading-none uppercase">
                    Coaching Staff
                </h2>
                <p class="text-lg md:text-2xl uppercase"
                   style="font-family: Poppins, ui-sans-serif, system-ui; color: rgba(255,255,255,0.85); letter-spacing: 0.01em;">
                    Guided by the Best in the Game
                </p>
            </div>
        </div>

{{-- Staff grid (horizontal centered row(s) that wrap) --}}
<div class="py-16 px-6 md:px-20" style="background: {{ $bg }};">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-wrap justify-center gap-12 md:gap-16">

            @foreach ($coachRows as $coach)
                @php
                    $name   = $coach['name'] ?? '';
                    $label  = $coach['label'] ?? '';
                    $title  = $coach['title'] ?? '';
                    $email  = $coach['email'] ?? $playerEmail;
                    $mailto = $email ? 'mailto:' . $email : '#';
                @endphp

                {{-- Card --}}
                <div class="w-[280px] sm:w-[300px] md:w-[320px] text-center">
                    <div class="font-extrabold uppercase tracking-wide text-lg" style="color: {{ $text1 }};">
                        {{ $name }}
                    </div>

                    <div class="mt-1 text-xs uppercase tracking-widest" style="color: {{ $accent }};">
                        {{ $label }}
                    </div>

                    <div class="mt-6 flex justify-center">
                        <a href="{{ $mailto }}" class="inline-flex items-center justify-center" aria-label="Email coach">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="w-12 h-12"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="{{ $accent }}"
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

    {{-- FOOTER (ONE LOGO CONTAINER) --}}
    <footer class="w-full">
        <div class="py-16 px-6 md:px-20" style="background: {{ $secondary }}; color: rgba(255, 255, 255, 1)
            <div class="max-w-7xl mx-auto grid md:grid-cols-2 gap-12 items-start">

                {{-- LEFT: SINGLE LOGO CONTAINER --}}
                <div class="flex items-center">
                    <div class="logo-slot h-16 w-48 rounded flex items-center justify-center overflow-hidden">
                        @if (!empty($footerLogoUrl))
                            <img src="{{ $footerLogoUrl }}" alt="Footer logo" class="h-full w-full object-contain p-3">
                        @else
                            <div class="h-full w-full"></div>
                        @endif
                    </div>
                </div>

                {{-- RIGHT: CONTACT --}}
                <div class="border-l pl-8" style="border-color: rgba(255,255,255,0.25);">
                    <h3 class="text-xl font-bold uppercase tracking-wide mb-6">
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

                    {{-- Social --}}
                    <div>
                        <p class="text-sm uppercase tracking-wider mb-3" style="color: rgba(255,255,255,0.8);">
                            Connect
                        </p>

                        <div class="flex items-center gap-6">
                            {{-- Instagram --}}
                            <a href="{{ $igUrl ?: '#' }}" class="{{ empty($igUrl) ? 'pointer-events-none opacity-60' : 'hover:opacity-90' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M7.75 2h8.5A5.75 5.75 0 0122 7.75v8.5A5.75 5.75 0 0116.25 22h-8.5A5.75 5.75 0 012 16.25v-8.5A5.75 5.75 0 017.75 2zm4.25 5.5A4.75 4.75 0 1016.75 12 4.76 4.76 0 0012 7.5zm0 7.8A3.05 3.05 0 1115.05 12 3.05 3.05 0 0112 15.3zm4.9-8.55a1.1 1.1 0 11-1.1-1.1 1.1 1.1 0 011.1 1.1z"/>
                                </svg>
                            </a>

                            {{-- YouTube --}}
                            <a href="{{ $ytUrl ?: '#' }}" class="{{ empty($ytUrl) ? 'pointer-events-none opacity-60' : 'hover:opacity-90' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M23.5 6.2a3 3 0 00-2.1-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.4.6A3 3 0 00.5 6.2 31.4 31.4 0 000 12a31.4 31.4 0 00.5 5.8 3 3 0 002.1 2.1c1.8.6 9.4.6 9.4.6s7.6 0 9.4-.6a3 3 0 002.1-2.1A31.4 31.4 0 0024 12a31.4 31.4 0 00-.5-5.8zM9.8 15.5v-7l6.2 3.5-6.2 3.5z"/>
                                </svg>
                            </a>

                            {{-- X --}}
                            <a href="{{ $xUrl ?: '#' }}" class="{{ empty($xUrl) ? 'pointer-events-none opacity-60' : 'hover:opacity-90' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18.244 2H21l-6.5 7.43L22 22h-6.828l-4.27-5.588L5.6 22H3l7.1-8.12L2 2h6.828l3.84 5.088L18.244 2z"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="text-center py-4 text-sm uppercase tracking-wider" style="background: {{ $primary }}; color: #fff;">
            {{ $copyright }}
        </div>
    </footer>

    <script>
    document.addEventListener("DOMContentLoaded", function () {

        const buttons  = document.querySelectorAll(".tab-btn");
        const contents = document.querySelectorAll(".tab-content");

        const primary = @json($primary);
        const accent  = @json($accent);

        buttons.forEach(button => {

            // ✅ Pointer cursor only
            button.style.cursor = "pointer";

            // ✅ Simple hover (only if NOT active)
            button.addEventListener("mouseenter", function () {
                if (!this.classList.contains("bg-white")) {
                    this.style.background = accent;
                }
            });

            button.addEventListener("mouseleave", function () {
                if (!this.classList.contains("bg-white")) {
                    this.style.background = primary;
                }
            });

            // Click behavior (your original logic preserved)
            button.addEventListener("click", function () {

                const target = this.dataset.tab;

                // Reset buttons
                buttons.forEach(btn => {
                    btn.classList.remove("bg-white", "text-gray-900");
                    btn.classList.add("text-white");
                    btn.style.background = primary;
                });

                // Activate clicked
                this.classList.remove("text-white");
                this.classList.add("bg-white", "text-gray-900");
                this.style.background = "";

                // Hide all content
                contents.forEach(content => content.classList.add("hidden"));

                // Show correct content
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