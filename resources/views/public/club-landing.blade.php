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
        $autoAccent = $primaryLum < 0.18
            ? $mixHex($primary, '#FFFFFF', 0.48)
            : ($primaryLum > 0.72 ? $mixHex($primary, '#000000', 0.34) : $primary);

        $autoAccentSoft = $mixHex($autoAccent, '#FFFFFF', 0.22);
        $autoAccentDeep = $mixHex($autoAccent, '#000000', 0.36);
        $textOnAccent = $luminance($autoAccent) > 0.58 ? '#070707' : '#FFFFFF';

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
            --club-primary: {{ $autoAccent }};
            --club-primary-soft: {{ $autoAccentSoft }};
            --club-primary-deep: {{ $autoAccentDeep }};
            --club-text-on-accent: {{ $textOnAccent }};
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
            background: #020202;
            color: #fff;
            font-family: var(--club-body);
            overflow-x: hidden;
        }

        .club-page {
            position: relative;
            min-height: 100vh;
            background:
                radial-gradient(circle at 18% 8%, color-mix(in srgb, var(--club-primary) 26%, transparent), transparent 30%),
                radial-gradient(circle at 82% 18%, color-mix(in srgb, var(--club-primary-soft) 14%, transparent), transparent 28%),
                linear-gradient(180deg, #050505 0%, #010101 100%);
        }

        .club-page::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(180deg, rgba(0,0,0,.52), rgba(0,0,0,.92)),
                url("{{ $heroImageUrl }}") center/cover no-repeat;
            opacity: .46;
            pointer-events: none;
        }

        .club-page::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 30%, transparent), transparent 42%),
                radial-gradient(circle at 50% 105%, rgba(255,255,255,.08), transparent 28%);
        }

        .club-shell {
            position: relative;
            z-index: 2;
            width: min(1180px, calc(100% - 28px));
            margin: 0 auto;
            min-height: 100vh;
            display: grid;
            grid-template-rows: 1fr auto;
            gap: 18px;
            padding: clamp(18px, 3vw, 34px) 0;
        }

        .club-hero {
            min-height: 580px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 380px);
            gap: 18px;
            align-items: stretch;
        }

        .club-hero-main {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 520px;
            padding: clamp(24px, 5vw, 64px);
            overflow: hidden;
            border-radius: 30px;
            border: 1px solid color-mix(in srgb, var(--club-primary) 22%, rgba(255,255,255,.12));
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 12%, transparent), transparent 42%),
                rgba(8,8,10,.78);
            backdrop-filter: blur(20px);
            box-shadow:
                0 26px 100px rgba(0,0,0,.52),
                inset 0 1px 0 rgba(255,255,255,.06);
        }

        .club-hero-main::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, var(--club-primary), transparent 48%) top left / 100% 4px no-repeat,
                radial-gradient(circle at 12% 0%, color-mix(in srgb, var(--club-primary) 20%, transparent), transparent 28%);
            pointer-events: none;
        }

        .club-brand {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: clamp(26px, 4vw, 46px);
        }

        .club-logo {
            width: clamp(62px, 8vw, 92px);
            height: clamp(62px, 8vw, 92px);
            border-radius: 22px;
            object-fit: contain;
            padding: 8px;
            background: rgba(0,0,0,.38);
            border: 1px solid rgba(255,255,255,.15);
            box-shadow: 0 16px 34px rgba(0,0,0,.32);
        }

        .club-brand-text {
            min-width: 0;
        }

        .club-name {
            font-family: var(--club-heading);
            font-size: clamp(28px, 4.5vw, 60px);
            line-height: .9;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-type {
            margin-top: 8px;
            color: var(--club-primary);
            font-family: var(--club-heading);
            font-size: 13px;
            letter-spacing: .28em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-kicker {
            position: relative;
            color: var(--club-primary);
            font-family: var(--club-heading);
            font-size: clamp(14px, 1.8vw, 20px);
            letter-spacing: .22em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 12px;
        }

        .club-headline {
            position: relative;
            max-width: 820px;
            margin: 0;
            font-family: var(--club-heading);
            font-size: clamp(58px, 10vw, 132px);
            line-height: .84;
            letter-spacing: .035em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-copy {
            position: relative;
            max-width: 720px;
            margin-top: 22px;
            color: rgba(255,255,255,.82);
            font-size: clamp(15px, 1.35vw, 18px);
            line-height: 1.58;
            font-weight: 600;
        }

        .club-actions {
            position: relative;
            margin-top: 26px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .club-action {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            border-radius: 13px;
            padding: 0 18px;
            color: #fff;
            border: 1px solid rgba(255,255,255,.14);
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
            background: rgba(255,255,255,.10);
        }

        .club-action.primary {
            background: linear-gradient(135deg, var(--club-primary), var(--club-primary-deep));
            border-color: var(--club-primary);
            color: var(--club-text-on-accent);
            box-shadow: 0 14px 30px color-mix(in srgb, var(--club-primary) 32%, transparent);
        }

        .club-hero-side {
            display: grid;
            gap: 12px;
            align-content: stretch;
        }

        .club-mini-stat {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,.12);
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 16%, transparent), transparent 54%),
                rgba(8,8,10,.74);
            backdrop-filter: blur(18px);
            padding: 20px;
            min-height: 142px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            box-shadow: 0 18px 50px rgba(0,0,0,.34);
        }

        .club-mini-stat i {
            color: var(--club-primary);
            font-size: 25px;
            margin-bottom: 20px;
        }

        .club-mini-stat span {
            color: rgba(255,255,255,.62);
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 6px;
        }

        .club-mini-stat strong {
            font-family: var(--club-heading);
            font-size: clamp(26px, 3vw, 38px);
            line-height: 1;
            font-weight: 900;
            text-transform: uppercase;
        }

        .club-teams {
            margin-top: 18px;
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid color-mix(in srgb, var(--club-primary) 18%, rgba(255,255,255,.12));
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 9%, transparent), transparent 38%),
                rgba(8,8,10,.78);
            backdrop-filter: blur(20px);
            box-shadow: 0 24px 80px rgba(0,0,0,.40);
        }

        .club-teams-head {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(255,255,255,.10);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .club-section-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-family: var(--club-heading);
            font-size: 24px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-section-title i {
            color: var(--club-primary);
        }

        .club-team-layout {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            min-height: 210px;
        }

        .club-gender-tabs {
            padding: 14px;
            border-right: 1px solid rgba(255,255,255,.10);
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .club-gender-tab {
            min-height: 72px;
            border: 1px solid rgba(255,255,255,.11);
            border-radius: 16px;
            background: rgba(255,255,255,.052);
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
            background: color-mix(in srgb, var(--club-primary) 18%, rgba(255,255,255,.052));
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
            color: var(--club-text-on-accent);
        }

        .club-team-arrow:disabled {
            opacity: .25;
            pointer-events: none;
        }

        .club-team-arrow.is-left { left: 10px; }
        .club-team-arrow.is-right { right: 10px; }

        .club-team-card {
            scroll-snap-align: start;
            width: 180px;
            min-width: 180px;
            min-height: 176px;
            border-radius: 18px;
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

        .club-footer {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            border: 1px solid color-mix(in srgb, var(--club-primary) 18%, rgba(255,255,255,.12));
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 10%, transparent), transparent 42%),
                rgba(8,8,10,.84);
            backdrop-filter: blur(20px);
            box-shadow: 0 22px 70px rgba(0,0,0,.42);
        }

        .club-footer-top {
            padding: 22px;
            border-bottom: 1px solid rgba(255,255,255,.10);
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr);
            gap: 22px;
        }

        .club-footer h2 {
            margin: 0 0 10px;
            font-family: var(--club-heading);
            font-size: clamp(32px, 4vw, 52px);
            line-height: .9;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-footer p {
            margin: 0;
            color: rgba(255,255,255,.72);
            line-height: 1.55;
            font-size: 14px;
            font-weight: 600;
        }

        .club-footer-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .club-footer-item {
            min-height: 58px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 15px;
            padding: 11px;
            background: rgba(255,255,255,.052);
            border: 1px solid rgba(255,255,255,.09);
            color: rgba(255,255,255,.80);
            text-decoration: none;
            font-size: 13px;
        }

        .club-footer-item i {
            color: var(--club-primary);
            width: 22px;
            text-align: center;
            flex: 0 0 auto;
        }

        .club-footer-item strong {
            display: block;
            color: #fff;
            font-family: var(--club-heading);
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 2px;
        }

        .club-footer-bottom {
            min-height: 54px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 22px;
            color: rgba(255,255,255,.52);
            font-size: 12px;
            font-weight: 700;
        }

        .club-sponsor-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .club-sponsor {
            min-height: 32px;
            border-radius: 999px;
            padding: 0 12px;
            display: inline-flex;
            align-items: center;
            color: rgba(255,255,255,.72);
            border: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.045);
            font-size: 11px;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
        }

        @media (max-width: 980px) {
            .club-shell {
                width: min(760px, calc(100% - 22px));
            }

            .club-hero {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .club-hero-main {
                min-height: 440px;
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
                border-bottom: 1px solid rgba(255,255,255,.10);
            }

            .club-team-slider-wrap {
                min-height: 208px;
            }

            .club-footer-top {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .club-shell {
                width: calc(100% - 18px);
                padding: 10px 0 18px;
            }

            .club-hero-main {
                min-height: 420px;
                border-radius: 22px;
            }

            .club-headline {
                font-size: 52px;
            }

            .club-hero-side {
                grid-template-columns: 1fr;
            }

            .club-gender-tabs {
                grid-template-columns: 1fr;
            }

            .club-team-slider {
                padding-left: 50px;
                padding-right: 50px;
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

                                <div class="club-brand-text">
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