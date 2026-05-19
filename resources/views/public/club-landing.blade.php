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
        $sponsors = collect(is_array($club->sponsors_partners ?? null) ? $club->sponsors_partners : []);

        $primary = $branding['primary_color'] ?? $club->primary_color ?? '#ff3131';
        $secondary = $branding['secondary_color'] ?? $club->secondary_color ?? '#050505';
        $accent = $branding['accent_color'] ?? $primary;
        $headingFont = $branding['heading_font'] ?? $branding['font_heading'] ?? 'Antonio';
        $bodyFont = $branding['body_font'] ?? $branding['font_body'] ?? 'Inter';

        $normalizeHex = function (?string $hex, string $fallback = '#ff3131') {
            $hex = trim((string) $hex);

            if ($hex === '') {
                return $fallback;
            }

            if (! str_starts_with($hex, '#')) {
                $hex = '#' . $hex;
            }

            if (! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
                return $fallback;
            }

            return strtoupper($hex);
        };

        $hexToRgb = function (string $hex) {
            $hex = ltrim($hex, '#');

            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
            ];
        };

        $rgbToHex = function (array $rgb) {
            return sprintf(
                '#%02X%02X%02X',
                max(0, min(255, (int) round($rgb[0]))),
                max(0, min(255, (int) round($rgb[1]))),
                max(0, min(255, (int) round($rgb[2])))
            );
        };

        $mixHex = function (string $hex, string $mixWith, float $amount) use ($hexToRgb, $rgbToHex) {
            $a = $hexToRgb($hex);
            $b = $hexToRgb($mixWith);

            return $rgbToHex([
                $a[0] + (($b[0] - $a[0]) * $amount),
                $a[1] + (($b[1] - $a[1]) * $amount),
                $a[2] + (($b[2] - $a[2]) * $amount),
            ]);
        };

        $luminance = function (string $hex) use ($hexToRgb) {
            [$r, $g, $b] = array_map(fn ($value) => $value / 255, $hexToRgb($hex));

            $convert = function ($channel) {
                return $channel <= 0.03928
                    ? $channel / 12.92
                    : (($channel + 0.055) / 1.055) ** 2.4;
            };

            return (0.2126 * $convert($r)) + (0.7152 * $convert($g)) + (0.0722 * $convert($b));
        };

        $primary = $normalizeHex($primary);
        $secondary = $normalizeHex($secondary, '#050505');
        $accent = $normalizeHex($accent, $primary);

        $primaryLum = $luminance($primary);
        $secondaryLum = $luminance($secondary);

        $autoBackground = ($primaryLum < 0.28 && $secondaryLum < 0.28)
            ? $mixHex($secondary, '#FFFFFF', 0.075)
            : (($primaryLum > 0.62 || $secondaryLum > 0.62) ? '#050506' : '#070708');

        $autoSurface = ($primaryLum < 0.28 && $secondaryLum < 0.28)
            ? $mixHex($secondary, '#FFFFFF', 0.12)
            : (($primaryLum > 0.62 || $secondaryLum > 0.62) ? '#0A0A0C' : $mixHex($secondary, '#000000', 0.24));

        $autoSurfaceSoft = $mixHex($autoSurface, '#FFFFFF', 0.055);
        $autoBorder = ($primaryLum < 0.22) ? $mixHex($primary, '#FFFFFF', 0.46) : $primary;
        $autoGlow = ($primaryLum < 0.22) ? $mixHex($primary, '#FFFFFF', 0.32) : $primary;
        $textOnPrimary = $primaryLum > 0.58 ? '#070707' : '#FFFFFF';

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

        $headline = $club->landing_page_intro ?: 'Built for the next level.';
        $content = $club->landing_page_content ?: 'A club home for athletes, families, and staff. View teams, follow the pathway, and connect with the right people.';

        $address = $contact['address'] ?? trim(collect([$club->city, $club->state])->filter()->implode(', '));
        $phone = $contact['phone'] ?? null;
        $email = $contact['email'] ?? null;
        $mapsUrl = $contact['maps_url'] ?? $contact['google_maps_url'] ?? null;

        $teamGender = function ($team) {
            $settings = is_array($team->team_settings ?? null) ? $team->team_settings : [];
            $gender = strtolower((string) ($settings['gender'] ?? $settings['division_gender'] ?? $team->club?->league?->gender ?? ''));
            $name = strtolower((string) $team->name);

            if (
                str_contains($gender, 'female')
                || str_contains($gender, 'women')
                || str_contains($gender, 'woman')
                || str_contains($gender, 'girls')
                || str_contains($gender, 'girl')
                || str_contains($name, 'women')
                || str_contains($name, 'woman')
                || str_contains($name, 'girls')
                || str_contains($name, 'girl')
                || str_contains($name, 'female')
            ) {
                return 'women';
            }

            return 'men';
        };

        $mensTeams = collect($teams ?? [])->filter(fn ($team) => $teamGender($team) === 'men')->values();
        $womensTeams = collect($teams ?? [])->filter(fn ($team) => $teamGender($team) === 'women')->values();
        $teamCount = collect($teams ?? [])->count();
    @endphp

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|inter:400,500,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        :root {
            --club-primary: {{ $primary }};
            --club-secondary: {{ $secondary }};
            --club-accent: {{ $accent }};
            --club-bg: {{ $autoBackground }};
            --club-surface: {{ $autoSurface }};
            --club-surface-soft: {{ $autoSurfaceSoft }};
            --club-border: {{ $autoBorder }};
            --club-glow: {{ $autoGlow }};
            --club-text-on-primary: {{ $textOnPrimary }};
            --club-heading: "{{ $headingFont }}", "Antonio", sans-serif;
            --club-body: "{{ $bodyFont }}", "Inter", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--club-bg);
            color: #fff;
            font-family: var(--club-body);
            overflow-x: hidden;
        }

        .club-page {
            position: relative;
            min-height: 100vh;
            background:
                radial-gradient(circle at 18% 4%, color-mix(in srgb, var(--club-glow) 22%, transparent), transparent 28%),
                radial-gradient(circle at 82% 14%, color-mix(in srgb, var(--club-secondary) 18%, transparent), transparent 28%),
                linear-gradient(180deg, var(--club-bg) 0%, #010101 100%);
        }

        .club-page::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(180deg, rgba(0,0,0,.46), rgba(0,0,0,.88)),
                url("{{ $heroImageUrl }}") center/cover no-repeat;
            opacity: .38;
            pointer-events: none;
        }

        .club-page::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 24%, transparent), transparent 42%),
                linear-gradient(215deg, color-mix(in srgb, var(--club-secondary) 18%, transparent), transparent 46%);
        }

        .club-shell {
            position: relative;
            z-index: 2;
            width: min(1120px, calc(100% - 24px));
            min-height: 100vh;
            margin: 0 auto;
            display: grid;
            grid-template-rows: 1fr auto;
            gap: 14px;
            padding: 16px 0;
        }

        .club-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 14px;
            align-items: stretch;
        }

        .club-hero-main,
        .club-mini-stat,
        .club-teams,
        .club-footer {
            border: 1px solid color-mix(in srgb, var(--club-border) 24%, rgba(255,255,255,.10));
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 8%, transparent), transparent 40%),
                color-mix(in srgb, var(--club-surface) 88%, transparent);
            backdrop-filter: blur(18px);
            box-shadow: 0 18px 58px rgba(0,0,0,.38), inset 0 1px 0 rgba(255,255,255,.045);
        }

        .club-hero-main {
            position: relative;
            min-height: 440px;
            border-radius: 24px;
            overflow: hidden;
            padding: clamp(22px, 4.2vw, 48px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .club-hero-main::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(90deg, var(--club-primary), var(--club-secondary), transparent 62%) top left / 100% 3px no-repeat,
                radial-gradient(circle at 10% 0%, color-mix(in srgb, var(--club-glow) 18%, transparent), transparent 30%);
        }

        .club-brand {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: clamp(20px, 3vw, 34px);
        }

        .club-logo {
            width: clamp(58px, 7vw, 78px);
            height: clamp(58px, 7vw, 78px);
            border-radius: 18px;
            object-fit: contain;
            padding: 8px;
            background: color-mix(in srgb, var(--club-surface) 65%, transparent);
            border: 1px solid rgba(255,255,255,.13);
        }

        .club-name {
            font-family: var(--club-heading);
            font-size: clamp(26px, 3.8vw, 48px);
            line-height: .9;
            letter-spacing: .1em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-type {
            margin-top: 6px;
            color: var(--club-primary);
            font-family: var(--club-heading);
            font-size: 12px;
            letter-spacing: .24em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-kicker {
            position: relative;
            color: var(--club-primary);
            font-family: var(--club-heading);
            font-size: clamp(13px, 1.5vw, 18px);
            letter-spacing: .18em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 10px;
        }

        .club-headline {
            position: relative;
            max-width: 760px;
            margin: 0;
            font-family: var(--club-heading);
            font-size: clamp(46px, 8vw, 104px);
            line-height: .84;
            letter-spacing: .032em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-copy {
            position: relative;
            max-width: 660px;
            margin-top: 16px;
            color: rgba(255,255,255,.78);
            font-size: 14px;
            line-height: 1.55;
            font-weight: 600;
        }

        .club-actions {
            position: relative;
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
        }

        .club-action {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 12px;
            padding: 0 15px;
            color: #fff;
            border: 1px solid rgba(255,255,255,.13);
            background: rgba(255,255,255,.06);
            text-decoration: none;
            font-family: var(--club-heading);
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }

        .club-action:hover {
            transform: translateY(-2px);
            border-color: var(--club-border);
            background: rgba(255,255,255,.10);
        }

        .club-action.primary {
            background: linear-gradient(135deg, var(--club-primary), var(--club-secondary));
            border-color: var(--club-border);
            color: var(--club-text-on-primary);
            box-shadow: 0 12px 26px color-mix(in srgb, var(--club-primary) 28%, transparent);
        }

        .club-hero-side {
            display: grid;
            gap: 10px;
        }

        .club-mini-stat {
            min-height: 126px;
            border-radius: 20px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .club-mini-stat i {
            color: var(--club-primary);
            font-size: 22px;
            margin-bottom: 16px;
        }

        .club-mini-stat span {
            color: rgba(255,255,255,.58);
            font-size: 10px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 5px;
        }

        .club-mini-stat strong {
            font-family: var(--club-heading);
            font-size: clamp(23px, 2.4vw, 32px);
            line-height: 1;
            font-weight: 900;
            text-transform: uppercase;
        }

        .club-teams {
            margin-top: 14px;
            border-radius: 22px;
            overflow: hidden;
        }

        .club-teams-head {
            padding: 13px 15px;
            border-bottom: 1px solid rgba(255,255,255,.09);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .club-section-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 9px;
            font-family: var(--club-heading);
            font-size: 21px;
            letter-spacing: .1em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-section-title i {
            color: var(--club-primary);
        }

        .club-team-layout {
            display: grid;
            grid-template-columns: 190px minmax(0, 1fr);
            min-height: 182px;
        }

        .club-gender-tabs {
            padding: 12px;
            border-right: 1px solid rgba(255,255,255,.09);
            display: grid;
            gap: 9px;
            align-content: start;
        }

        .club-gender-tab {
            min-height: 64px;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 14px;
            background: rgba(255,255,255,.05);
            color: #fff;
            cursor: pointer;
            padding: 10px;
            text-align: left;
            transition: transform .18s ease, background .18s ease, border-color .18s ease;
        }

        .club-gender-tab:hover,
        .club-gender-tab.is-active {
            transform: translateX(2px);
            border-color: var(--club-border);
            background: color-mix(in srgb, var(--club-primary) 16%, rgba(255,255,255,.05));
        }

        .club-gender-tab strong {
            display: block;
            font-family: var(--club-heading);
            font-size: 21px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-gender-tab span {
            display: block;
            margin-top: 5px;
            color: rgba(255,255,255,.62);
            font-size: 11px;
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
            gap: 10px;
            padding: 12px 52px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            transform: translateX(24px);
            opacity: 0;
            pointer-events: none;
            transition: transform .22s ease, opacity .22s ease;
        }

        .club-team-slider.is-active {
            transform: translateX(0);
            opacity: 1;
            pointer-events: auto;
        }

        .club-team-slider::-webkit-scrollbar {
            height: 6px;
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
            width: 34px;
            height: 34px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.14);
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
            color: var(--club-text-on-primary);
        }

        .club-team-arrow:disabled {
            opacity: .25;
            pointer-events: none;
        }

        .club-team-arrow.is-left { left: 9px; }
        .club-team-arrow.is-right { right: 9px; }

        .club-team-card {
            scroll-snap-align: start;
            width: 156px;
            min-width: 156px;
            min-height: 150px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.11);
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 20%, transparent), transparent 50%),
                rgba(255,255,255,.05);
            color: #fff;
            text-decoration: none;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
            overflow: hidden;
            transition: transform .18s ease, border-color .18s ease;
        }

        .club-team-card:hover {
            transform: translateY(-3px);
            border-color: var(--club-border);
        }

        .club-team-card-mark {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--club-primary);
            background: rgba(0,0,0,.32);
        }

        .club-team-name {
            font-family: var(--club-heading);
            font-size: 20px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-team-copy {
            margin-top: 6px;
            color: rgba(255,255,255,.62);
            font-size: 11px;
            font-weight: 800;
        }

        .club-footer {
            border-radius: 22px;
            overflow: hidden;
        }

        .club-footer-top {
            padding: 16px;
            border-bottom: 1px solid rgba(255,255,255,.09);
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(240px, .9fr);
            gap: 16px;
        }

        .club-footer h2 {
            margin: 0 0 8px;
            font-family: var(--club-heading);
            font-size: clamp(27px, 3.2vw, 42px);
            line-height: .9;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-footer p {
            margin: 0;
            color: rgba(255,255,255,.70);
            line-height: 1.45;
            font-size: 13px;
            font-weight: 600;
        }

        .club-footer-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .club-footer-item {
            min-height: 50px;
            display: flex;
            align-items: center;
            gap: 9px;
            border-radius: 13px;
            padding: 9px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.08);
            color: rgba(255,255,255,.78);
            text-decoration: none;
            font-size: 12px;
        }

        .club-footer-item i {
            color: var(--club-primary);
            width: 20px;
            text-align: center;
            flex: 0 0 auto;
        }

        .club-footer-item strong {
            display: block;
            color: #fff;
            font-family: var(--club-heading);
            font-size: 11px;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 1px;
        }

        .club-footer-bottom {
            min-height: 46px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255,255,255,.52);
            font-size: 11px;
            font-weight: 700;
        }

        .club-sponsor-row {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .club-sponsor {
            min-height: 28px;
            border-radius: 999px;
            padding: 0 10px;
            display: inline-flex;
            align-items: center;
            color: rgba(255,255,255,.72);
            border: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.045);
            font-size: 10px;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
        }

        @media (max-width: 920px) {
            .club-shell {
                width: min(720px, calc(100% - 18px));
            }

            .club-hero {
                grid-template-columns: 1fr;
            }

            .club-hero-main {
                min-height: 390px;
            }

            .club-hero-side {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .club-team-layout {
                grid-template-columns: 1fr;
            }

            .club-gender-tabs {
                grid-template-columns: 1fr 1fr;
                border-right: 0;
                border-bottom: 1px solid rgba(255,255,255,.09);
            }

            .club-team-slider-wrap {
                min-height: 182px;
            }

            .club-footer-top {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .club-shell {
                width: calc(100% - 14px);
                padding: 8px 0 14px;
            }

            .club-hero-main {
                min-height: 360px;
                border-radius: 18px;
                padding: 20px;
            }

            .club-headline {
                font-size: 46px;
            }

            .club-copy {
                font-size: 13px;
            }

            .club-hero-side {
                grid-template-columns: 1fr;
            }

            .club-gender-tabs {
                grid-template-columns: 1fr;
            }

            .club-team-slider {
                padding-left: 46px;
                padding-right: 46px;
            }

            .club-footer-info {
                grid-template-columns: 1fr;
            }

            .club-footer-bottom {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <main class="club-page">
        <div class="club-shell">
            <div>
                <section class="club-hero">
                    <div>
                        <div class="club-hero-main">
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
                            <h1 class="club-headline">One Club.<br>One Standard.</h1>

                            <div class="club-copy">
                                {!! nl2br(e($content)) !!}
                            </div>

                            <div class="club-actions">
                                <a class="club-action primary" href="#club-teams">
                                    <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                                    View Teams
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
                        </div>
                    </div>

                    <aside class="club-hero-side" aria-label="Club highlights">
                        <div class="club-mini-stat">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                            <span>Teams</span>
                            <strong>{{ $teamCount }}</strong>
                        </div>

                        <div class="club-mini-stat">
                            <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                            <span>League</span>
                            <strong>{{ $club->league?->name ? \Illuminate\Support\Str::of($club->league->name)->limit(12, '') : 'TBD' }}</strong>
                        </div>

                        <div class="club-mini-stat">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <span>Location</span>
                            <strong>{{ $address ? \Illuminate\Support\Str::of($address)->limit(12, '') : 'TBD' }}</strong>
                        </div>
                    </aside>
                </section>

                <section class="club-teams" id="club-teams">
                    <div class="club-teams-head">
                        <h2 class="club-section-title">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            Teams
                        </h2>
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
                                    <a class="club-team-card" href="{{ $team->landingUrl() ?: '#' }}">
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
                                    <a class="club-team-card" href="{{ $team->landingUrl() ?: '#' }}">
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
                </section>
            </div>

            <footer class="club-footer">
                <div class="club-footer-top">
                    <div>
                        <h2>{{ $club->name }}</h2>
                        <p>{!! nl2br(e($content)) !!}</p>
                    </div>

                    <div class="club-footer-info">
                        @if($club->league)
                            <div class="club-footer-item">
                                <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                                <div>
                                    <strong>League</strong>
                                    {{ $club->league->name }}
                                </div>
                            </div>
                        @endif

                        @if($address)
                            <div class="club-footer-item">
                                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                <div>
                                    <strong>Location</strong>
                                    {{ $address }}
                                </div>
                            </div>
                        @endif

                        @if($phone)
                            <a class="club-footer-item" href="tel:{{ preg_replace('/\D+/', '', $phone) }}">
                                <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                <div>
                                    <strong>Phone</strong>
                                    {{ $phone }}
                                </div>
                            </a>
                        @endif

                        @if($email)
                            <a class="club-footer-item" href="mailto:{{ $email }}">
                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                <div>
                                    <strong>Email</strong>
                                    {{ $email }}
                                </div>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="club-footer-bottom">
                    <div>© {{ now()->year }} {{ $club->name }}. Powered by PlyrCard.</div>

                    @if($sponsors->isNotEmpty())
                        <div class="club-sponsor-row">
                            @foreach($sponsors as $sponsor)
                                @if(filled($sponsor['name'] ?? null))
                                    <span class="club-sponsor">{{ $sponsor['name'] }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </footer>
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

                const amount = Math.max(180, Math.round(activePanel.clientWidth * .72));

                activePanel.scrollBy({
                    left: direction === 'left' ? -amount : amount,
                    behavior: 'smooth',
                });

                window.setTimeout(updateArrows, 240);
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