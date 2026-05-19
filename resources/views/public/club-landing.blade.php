<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $club->name }} | Club</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
        $branding = is_array($club->branding ?? null) ? $club->branding : [];
        $contact = is_array($club->contact_info ?? null) ? $club->contact_info : [];
        $socials = is_array($club->social_links ?? null) ? $club->social_links : [];
        $sponsors = collect(is_array($club->sponsors_partners ?? null) ? $club->sponsors_partners : []);

        $primary = $branding['primary_color'] ?? $club->primary_color ?? '#ff5c35';
        $secondary = $branding['secondary_color'] ?? $club->secondary_color ?? '#050505';
        $accent = $branding['accent_color'] ?? $primary;
        $headingFont = $branding['heading_font'] ?? $branding['font_heading'] ?? 'Antonio';
        $bodyFont = $branding['body_font'] ?? $branding['font_body'] ?? 'Inter';

        $resolveAsset = function ($value, $fallback = null) {
            if (blank($value)) {
                return $fallback;
            }

            $value = trim((string) $value);

            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }

            return asset('storage/' . ltrim($value, '/'));
        };

        $logo = $resolveAsset($club->logo ?? null);
        $heroImageUrl = $resolveAsset(
            $club->background_image
                ?? $club->hero_image
                ?? $branding['background_image']
                ?? $branding['hero_image']
                ?? null,
            asset('images/PLYRCARD-SITE.jpg')
        );

        $headline = $club->landing_page_intro ?: ($branding['headline'] ?? 'Train. Compete. Grow Together.');
        $content = $club->landing_page_content ?: ($branding['content'] ?? 'A club home for athletes, families, and staff. View teams, connect with coaches, and follow the player pathway.');

        $address = $contact['address'] ?? trim(collect([$club->city, $club->state])->filter()->implode(', '));
        $phone = $contact['phone'] ?? null;
        $email = $contact['email'] ?? null;
        $mapsUrl = $contact['maps_url'] ?? $contact['google_maps_url'] ?? null;

        $teamGender = function ($team) {
            $settings = is_array($team->team_settings ?? null) ? $team->team_settings : [];
            $gender = strtolower((string) ($team->gender ?? $settings['gender'] ?? ''));

            if (str_contains($gender, 'female') || str_contains($gender, 'women') || str_contains($gender, 'girl')) {
                return 'women';
            }

            if (str_contains($gender, 'male') || str_contains($gender, 'men') || str_contains($gender, 'boy')) {
                return 'men';
            }

            $name = strtolower((string) $team->name);

            if (str_contains($name, 'women') || str_contains($name, 'girls') || str_contains($name, 'female')) {
                return 'women';
            }

            return 'men';
        };

        $mensTeams = collect($teams ?? [])->filter(fn ($team) => $teamGender($team) === 'men')->values();
        $womensTeams = collect($teams ?? [])->filter(fn ($team) => $teamGender($team) === 'women')->values();
    @endphp

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|inter:400,500,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        :root {
            --club-primary: {{ $primary }};
            --club-secondary: {{ $secondary }};
            --club-accent: {{ $accent }};
            --club-heading: "{{ $headingFont }}", "Antonio", sans-serif;
            --club-body: "{{ $bodyFont }}", "Inter", sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: #050505;
            color: #fff;
            font-family: var(--club-body);
            overflow-x: hidden;
        }

        .club-page {
            position: relative;
            min-height: 100vh;
            padding: 18px;
            display: flex;
            align-items: center;
            background:
                radial-gradient(circle at 16% 12%, color-mix(in srgb, var(--club-primary) 28%, transparent), transparent 30%),
                linear-gradient(135deg, #050505, #111111 52%, #050505);
        }

        .club-page::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(90deg, rgba(0,0,0,.88), rgba(0,0,0,.54), rgba(0,0,0,.86)),
                url("{{ $heroImageUrl }}") center/cover no-repeat;
            opacity: .55;
            pointer-events: none;
        }

        .club-page::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 1;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 28%, transparent), transparent 38%),
                radial-gradient(circle at 78% 18%, rgba(255,255,255,.06), transparent 26%);
            pointer-events: none;
        }

        .club-shell {
            position: relative;
            z-index: 2;
            width: min(1280px, 100%);
            margin: 0 auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 420px;
            gap: 18px;
            align-items: stretch;
        }

        .club-main,
        .club-side,
        .club-teams {
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(8,8,8,.76);
            backdrop-filter: blur(18px);
            box-shadow: 0 24px 80px rgba(0,0,0,.42);
            border-radius: 24px;
            overflow: hidden;
        }

        .club-main {
            min-height: 430px;
            padding: clamp(22px, 3.4vw, 42px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .club-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }

        .club-logo {
            width: 68px;
            height: 68px;
            border-radius: 18px;
            object-fit: contain;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            padding: 8px;
        }

        .club-name {
            font-family: var(--club-heading);
            font-size: clamp(26px, 3.4vw, 46px);
            line-height: .9;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-type {
            margin-top: 7px;
            color: var(--club-primary);
            font-family: var(--club-heading);
            font-size: 13px;
            letter-spacing: .26em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-kicker {
            color: var(--club-primary);
            font-family: var(--club-heading);
            font-size: clamp(13px, 1.4vw, 17px);
            letter-spacing: .22em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 10px;
        }

        .club-headline {
            margin: 0;
            font-family: var(--club-heading);
            font-size: clamp(48px, 7.6vw, 96px);
            line-height: .86;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-copy {
            margin-top: 18px;
            max-width: 720px;
            color: rgba(255,255,255,.82);
            font-size: 16px;
            line-height: 1.55;
        }

        .club-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .club-action {
            min-height: 43px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 12px;
            padding: 0 17px;
            color: #fff;
            background: rgba(255,255,255,.065);
            text-decoration: none;
            font-family: var(--club-heading);
            font-size: 13px;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }

        .club-action:hover {
            transform: translateY(-2px);
            border-color: var(--club-primary);
        }

        .club-action.primary {
            background: var(--club-primary);
            border-color: var(--club-primary);
        }

        .club-side {
            display: flex;
            flex-direction: column;
        }

        .club-side-head,
        .club-teams-head {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(255,255,255,.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .club-side-head i,
        .club-teams-head i {
            width: 34px;
            height: 34px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--club-primary);
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
        }

        .club-section-title {
            font-family: var(--club-heading);
            font-size: 21px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-info-list {
            display: grid;
        }

        .club-info-item {
            min-height: 58px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            color: rgba(255,255,255,.82);
            font-size: 14px;
        }

        .club-info-item:last-child {
            border-bottom: 0;
        }

        .club-info-item i {
            width: 22px;
            text-align: center;
            color: var(--club-primary);
            flex: 0 0 auto;
        }

        .club-info-item strong {
            display: block;
            margin-bottom: 2px;
            color: #fff;
            font-family: var(--club-heading);
            font-size: 13px;
            letter-spacing: .07em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-info-item a {
            color: inherit;
            text-decoration: none;
        }

        .club-teams {
            grid-column: 1 / -1;
        }

        .club-team-layout {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            min-height: 210px;
        }

        .club-gender-tabs {
            padding: 14px;
            border-right: 1px solid rgba(255,255,255,.1);
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .club-gender-tab {
            min-height: 72px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 15px;
            background: rgba(255,255,255,.055);
            color: #fff;
            cursor: pointer;
            padding: 12px;
            text-align: left;
            transition: transform .18s ease, background .18s ease, border-color .18s ease;
        }

        .club-gender-tab:hover,
        .club-gender-tab.is-active {
            transform: translateX(3px);
            border-color: var(--club-primary);
            background: color-mix(in srgb, var(--club-primary) 20%, rgba(255,255,255,.055));
        }

        .club-gender-tab strong {
            display: block;
            font-family: var(--club-heading);
            font-size: 24px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-gender-tab span {
            display: block;
            margin-top: 6px;
            color: rgba(255,255,255,.62);
            font-size: 12px;
            font-weight: 800;
        }

        .club-team-slider-wrap {
            position: relative;
            overflow: hidden;
        }

        .club-team-slider {
            position: absolute;
            inset: 0;
            display: flex;
            gap: 12px;
            padding: 14px 58px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            transform: translateX(28px);
            opacity: 0;
            pointer-events: none;
            transition: transform .24s ease, opacity .24s ease;
        }

        .club-team-slider.is-active {
            transform: translateX(0);
            opacity: 1;
            pointer-events: auto;
        }

        .club-team-slider::-webkit-scrollbar {
            height: 7px;
        }

        .club-team-slider::-webkit-scrollbar-thumb {
            background: var(--club-primary);
            border-radius: 999px;
        }

        .club-team-arrow {
            position: absolute;
            z-index: 5;
            top: 50%;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.15);
            background: rgba(0,0,0,.62);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            backdrop-filter: blur(12px);
            transition: transform .18s ease, background .18s ease, opacity .18s ease;
        }

        .club-team-arrow:hover {
            transform: translateY(-50%) scale(1.06);
            background: var(--club-primary);
        }

        .club-team-arrow:disabled {
            opacity: .25;
            pointer-events: none;
        }

        .club-team-arrow.is-left {
            left: 10px;
        }

        .club-team-arrow.is-right {
            right: 10px;
        }

        .club-team-card {
            scroll-snap-align: start;
            width: 180px;
            min-width: 180px;
            min-height: 176px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.12);
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 22%, transparent), transparent 52%),
                rgba(255,255,255,.055);
            color: #fff;
            text-decoration: none;
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
            overflow: hidden;
            transition: transform .18s ease, border-color .18s ease;
        }

        .club-team-card:hover {
            transform: translateY(-4px);
            border-color: var(--club-primary);
        }

        .club-team-card-mark {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--club-primary);
            background: rgba(0,0,0,.32);
        }

        .club-team-name {
            font-family: var(--club-heading);
            font-size: 23px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-team-copy {
            margin-top: 7px;
            color: rgba(255,255,255,.62);
            font-size: 12px;
            font-weight: 800;
        }

        .club-sponsor-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px 18px 16px;
            border-top: 1px solid rgba(255,255,255,.1);
        }

        .club-sponsor {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            padding: 0 14px;
            color: rgba(255,255,255,.74);
            background: rgba(255,255,255,.055);
            border: 1px solid rgba(255,255,255,.1);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        @media (max-width: 980px) {
            .club-page {
                align-items: flex-start;
                padding: 12px;
            }

            .club-shell {
                grid-template-columns: 1fr;
            }

            .club-main {
                min-height: auto;
            }

            .club-team-layout {
                grid-template-columns: 1fr;
            }

            .club-gender-tabs {
                border-right: 0;
                border-bottom: 1px solid rgba(255,255,255,.1);
                grid-template-columns: 1fr 1fr;
            }

            .club-team-slider-wrap {
                min-height: 212px;
            }
        }

        @media (max-width: 560px) {
            .club-brand {
                gap: 11px;
            }

            .club-logo {
                width: 56px;
                height: 56px;
                border-radius: 15px;
            }

            .club-name {
                font-size: 24px;
            }

            .club-headline {
                font-size: 48px;
            }

            .club-copy {
                font-size: 14px;
            }

            .club-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .club-gender-tabs {
                grid-template-columns: 1fr;
            }

            .club-team-slider {
                padding-left: 50px;
                padding-right: 50px;
            }
        }
    </style>
</head>

<body>
    <main class="club-page">
        <div class="club-shell">
            <section class="club-main">
                <div class="club-brand">
                    @if($logo)
                        <img class="club-logo" src="{{ $logo }}" alt="{{ $club->name }} logo">
                    @endif

                    <div>
                        <div class="club-name">{{ $club->name }}</div>
                        <div class="club-type">Sports Club</div>
                    </div>
                </div>

                <div class="club-kicker">{{ $headline }}</div>
                <h1 class="club-headline">More Than<br>Just A Club</h1>

                <div class="club-copy">
                    {!! nl2br(e($content)) !!}
                </div>

                <div class="club-actions">
                    <a class="club-action primary" href="#club-teams">
                        <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                        Teams
                    </a>

                    @if($email)
                        <a class="club-action" href="mailto:{{ $email }}">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            Contact
                        </a>
                    @endif

                    @if($mapsUrl)
                        <a class="club-action" href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            Map
                        </a>
                    @endif
                </div>
            </section>

            <aside class="club-side">
                <div class="club-side-head">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <div class="club-section-title">Club Info</div>
                </div>

                <div class="club-info-list">
                    @if($club->league)
                        <div class="club-info-item">
                            <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                            <div>
                                <strong>League</strong>
                                {{ $club->league->name }}
                            </div>
                        </div>
                    @endif

                    @if($address)
                        <div class="club-info-item">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <div>
                                <strong>Location</strong>
                                {{ $address }}
                            </div>
                        </div>
                    @endif

                    @if($phone)
                        <div class="club-info-item">
                            <i class="fa-solid fa-phone" aria-hidden="true"></i>
                            <div>
                                <strong>Phone</strong>
                                <a href="tel:{{ preg_replace('/\D+/', '', $phone) }}">{{ $phone }}</a>
                            </div>
                        </div>
                    @endif

                    @if($email)
                        <div class="club-info-item">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            <div>
                                <strong>Email</strong>
                                <a href="mailto:{{ $email }}">{{ $email }}</a>
                            </div>
                        </div>
                    @endif
                </div>
            </aside>

            <section class="club-teams" id="club-teams">
                <div class="club-teams-head">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <div class="club-section-title">Teams</div>
                </div>

                <div class="club-team-layout">
                    <div class="club-gender-tabs">
                        <button class="club-gender-tab is-active" type="button" data-club-team-tab="men">
                            <strong>Men's</strong>
                            <span>{{ $mensTeams->count() }} team{{ $mensTeams->count() === 1 ? '' : 's' }}</span>
                        </button>

                        <button class="club-gender-tab" type="button" data-club-team-tab="women">
                            <strong>Women's</strong>
                            <span>{{ $womensTeams->count() }} team{{ $womensTeams->count() === 1 ? '' : 's' }}</span>
                        </button>
                    </div>

                    <div class="club-team-slider-wrap">
                        <button class="club-team-arrow is-left" type="button" data-club-team-scroll="left" aria-label="Scroll teams left">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>

                        <button class="club-team-arrow is-right" type="button" data-club-team-scroll="right" aria-label="Scroll teams right">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>

                        <div class="club-team-slider is-active" data-club-team-panel="men">
                            @foreach($mensTeams as $team)
                                <a class="club-team-card" href="{{ $team->landing_page_slug ? route('teams.landing', $team->landing_page_slug) : '#' }}">
                                    <span class="club-team-card-mark">
                                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                                    </span>
                                    <span class="club-team-name">{{ $team->name }}</span>
                                    <span class="club-team-copy">Open team</span>
                                </a>
                            @endforeach
                        </div>

                        <div class="club-team-slider" data-club-team-panel="women">
                            @foreach($womensTeams as $team)
                                <a class="club-team-card" href="{{ $team->landing_page_slug ? route('teams.landing', $team->landing_page_slug) : '#' }}">
                                    <span class="club-team-card-mark">
                                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                                    </span>
                                    <span class="club-team-name">{{ $team->name }}</span>
                                    <span class="club-team-copy">Open team</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if($sponsors->isNotEmpty())
                    <div class="club-sponsor-row">
                        @foreach($sponsors as $sponsor)
                            @if(filled($sponsor['name'] ?? null))
                                <div class="club-sponsor">{{ $sponsor['name'] }}</div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('[data-club-team-tab]');
            const panels = document.querySelectorAll('[data-club-team-panel]');
            const scrollButtons = document.querySelectorAll('[data-club-team-scroll]');

            function getActivePanel() {
                return document.querySelector('[data-club-team-panel].is-active');
            }

            function updateArrows() {
                const activePanel = getActivePanel();

                scrollButtons.forEach((button) => {
                    if (!activePanel) {
                        button.disabled = true;
                        return;
                    }

                    const direction = button.getAttribute('data-club-team-scroll');
                    const maxScroll = activePanel.scrollWidth - activePanel.clientWidth;
                    const left = activePanel.scrollLeft;

                    if (maxScroll <= 4) {
                        button.disabled = true;
                        return;
                    }

                    button.disabled = direction === 'left'
                        ? left <= 4
                        : left >= maxScroll - 4;
                });
            }

            function scrollActivePanel(direction) {
                const activePanel = getActivePanel();

                if (!activePanel) {
                    return;
                }

                const amount = Math.max(190, Math.round(activePanel.clientWidth * .72));

                activePanel.scrollBy({
                    left: direction === 'left' ? -amount : amount,
                    behavior: 'smooth',
                });

                window.setTimeout(updateArrows, 260);
            }

            tabs.forEach((tab) => {
                tab.addEventListener('click', function () {
                    const target = this.getAttribute('data-club-team-tab');

                    tabs.forEach((item) => item.classList.toggle('is-active', item === this));

                    panels.forEach((panel) => {
                        const active = panel.getAttribute('data-club-team-panel') === target;
                        panel.classList.toggle('is-active', active);

                        if (active) {
                            panel.scrollTo({ left: 0, behavior: 'smooth' });
                        }
                    });

                    window.setTimeout(updateArrows, 80);
                });
            });

            scrollButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    scrollActivePanel(this.getAttribute('data-club-team-scroll'));
                });
            });

            panels.forEach((panel) => {
                panel.addEventListener('scroll', updateArrows, { passive: true });
            });

            window.addEventListener('resize', updateArrows);
            updateArrows();
        });
    </script>
</body>
</html>