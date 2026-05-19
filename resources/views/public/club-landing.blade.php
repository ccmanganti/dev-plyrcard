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

        /*
         * Brand-safe auto colors:
         * - brandPrimary/brandSecondary are the real club colors.
         * - uiAccent is the readable version used for text/icons/borders.
         * - surface/bg are generated to contrast with the brand.
         */
        $uiAccent = $primaryLum < 0.22
            ? $mixHex($primary, '#FFFFFF', 0.58)
            : ($primaryLum > 0.70 ? $mixHex($primary, '#000000', 0.34) : $primary);

        $uiAccentSoft = $mixHex($uiAccent, '#FFFFFF', 0.18);
        $uiAccentDeep = $mixHex($uiAccent, '#000000', 0.34);

        $bgBase = ($primaryLum < 0.22 && $secondaryLum < 0.22)
            ? $mixHex($secondary, '#FFFFFF', 0.055)
            : '#050506';

        $surface = ($primaryLum < 0.22 && $secondaryLum < 0.22)
            ? $mixHex($secondary, '#FFFFFF', 0.11)
            : $mixHex($secondary, '#000000', 0.28);

        $surfaceRaised = $mixHex($surface, '#FFFFFF', 0.07);
        $borderColor = $mixHex($uiAccent, '#FFFFFF', 0.16);
        $mutedText = '#B7BEC8';
        $textOnAccent = $luminance($uiAccent) > 0.58 ? '#071018' : '#FFFFFF';

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
            --club-brand-primary: {{ $primary }};
            --club-brand-secondary: {{ $secondary }};
            --club-primary: {{ $uiAccent }};
            --club-primary-soft: {{ $uiAccentSoft }};
            --club-primary-deep: {{ $uiAccentDeep }};
            --club-bg: {{ $bgBase }};
            --club-surface: {{ $surface }};
            --club-surface-raised: {{ $surfaceRaised }};
            --club-border: {{ $borderColor }};
            --club-muted: {{ $mutedText }};
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
            background: var(--club-bg);
            color: #fff;
            font-family: var(--club-body);
            overflow-x: hidden;
        }

        .club-page {
            position: relative;
            min-height: 100vh;
            background:
                radial-gradient(circle at 18% 3%, color-mix(in srgb, var(--club-primary) 24%, transparent), transparent 26%),
                radial-gradient(circle at 86% 12%, color-mix(in srgb, var(--club-brand-secondary) 28%, transparent), transparent 30%),
                linear-gradient(180deg, var(--club-bg), #010101 80%);
        }

        .club-page::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(180deg, rgba(0,0,0,.42), rgba(0,0,0,.90)),
                url("{{ $heroImageUrl }}") center/cover no-repeat;
            opacity: .36;
            pointer-events: none;
        }

        .club-page::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-brand-primary) 34%, transparent), transparent 45%),
                linear-gradient(215deg, color-mix(in srgb, var(--club-primary) 16%, transparent), transparent 48%);
        }

        .club-shell {
            position: relative;
            z-index: 2;
            width: min(980px, calc(100% - 22px));
            margin: 0 auto;
            min-height: 100vh;
            display: grid;
            grid-template-rows: 1fr auto;
            gap: 12px;
            padding: 14px 0;
        }

                .club-card {
            border: 1px solid color-mix(in srgb, var(--club-border) 32%, rgba(255,255,255,.11));
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-brand-primary) 10%, transparent), transparent 44%),
                color-mix(in srgb, var(--club-surface) 91%, transparent);
            backdrop-filter: blur(16px);
            box-shadow: 0 14px 38px rgba(0,0,0,.36), inset 0 1px 0 rgba(255,255,255,.045);
        }

        .club-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 10px;
        }

        .club-hero-main {
            position: relative;
            min-height: 355px;
            border-radius: 22px;
            overflow: hidden;
            padding: clamp(20px, 3.4vw, 36px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .club-hero-main::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(90deg, rgba(0,0,0,.74), rgba(0,0,0,.58)),
                url("{{ $heroImageUrl }}") center/cover no-repeat;
            opacity: .42;
            pointer-events: none;
        }

        .club-hero-main > * {
            position: relative;
            z-index: 2;
        }

        .club-hero-main::before,
        .club-teams::before,
        .club-footer::before {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--club-primary), var(--club-brand-primary), var(--club-brand-secondary), transparent);
            pointer-events: none;
        }

        .club-brand {
            position: relative;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 12px;
            margin-bottom: clamp(18px, 2.4vw, 26px);
            max-width: 680px;
        }

        .club-logo {
            width: clamp(56px, 6.4vw, 72px);
            height: clamp(56px, 6.4vw, 72px);
            border-radius: 17px;
            object-fit: contain;
            padding: 8px;
            background: color-mix(in srgb, var(--club-surface-raised) 86%, transparent);
            border: 1px solid color-mix(in srgb, var(--club-primary) 28%, rgba(255,255,255,.14));
        }

                .club-name {
            font-family: var(--club-heading);
            font-size: clamp(23px, 3vw, 36px);
            line-height: .96;
            letter-spacing: .075em;
            text-transform: uppercase;
            font-weight: 900;
            text-wrap: balance;
            max-width: 520px;
        }

        .club-type {
            margin-top: 6px;
            color: var(--club-primary);
            font-family: var(--club-heading);
            font-size: 10.5px;
            letter-spacing: .20em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-kicker {
            position: relative;
            color: var(--club-primary);
            font-family: var(--club-heading);
            font-size: clamp(12px, 1.25vw, 15px);
            letter-spacing: .18em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 9px;
        }

                .club-headline {
            position: relative;
            max-width: 720px;
            margin: 0;
            font-family: var(--club-heading);
            font-size: clamp(37px, 5.5vw, 64px);
            line-height: 1.02;
            letter-spacing: .026em;
            text-transform: uppercase;
            font-weight: 900;
            text-wrap: balance;
        }

        .club-copy {
            position: relative;
            max-width: 640px;
            margin-top: 15px;
            color: rgba(255,255,255,.82);
            font-size: 14px;
            line-height: 1.55;
            font-weight: 650;
        }

        .club-actions {
            position: relative;
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .club-action {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border-radius: 11px;
            padding: 0 14px;
            color: #fff;
            border: 1px solid rgba(255,255,255,.14);
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
            background: linear-gradient(135deg, var(--club-primary), var(--club-brand-primary));
            border-color: var(--club-border);
            color: var(--club-text-on-accent);
            box-shadow: 0 10px 22px color-mix(in srgb, var(--club-primary) 26%, transparent);
        }

        .club-hero-side {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

                        .club-mini-stat {
            min-height: 76px;
            border-radius: 12px;
            padding: 10px 11px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 7px;
        }

                        .club-mini-stat i {
            width: 22px;
            height: 22px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--club-primary);
            font-size: 11px;
            background: rgba(255,255,255,.06);
            filter: drop-shadow(0 0 10px color-mix(in srgb, var(--club-primary) 24%, transparent));
            margin: 0;
            flex: 0 0 auto;
        }

                        .club-mini-stat span {
            display: inline-flex;
            align-items: center;
            min-width: 0;
            color: rgba(255,255,255,.68);
            font-size: 7.5px;
            letter-spacing: .09em;
            text-transform: uppercase;
            font-weight: 900;
            margin: 0;
        }

                        .club-mini-stat i + span {
            margin-top: -29px;
            margin-left: 30px;
            min-height: 22px;
        }

        .club-mini-stat strong {
            display: block;
            width: 100%;
            font-family: var(--club-heading);
            font-size: clamp(13px, 1.25vw, 17px);
            line-height: 1.08;
            font-weight: 900;
            text-transform: uppercase;
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            overflow-wrap: normal;
            word-break: normal;
        }

        .club-teams {
            position: relative;
            margin-top: 12px;
            border-radius: 20px;
            overflow: hidden;
        }

        .club-teams-head {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255,255,255,.09);
        }

        .club-section-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 9px;
            font-family: var(--club-heading);
            font-size: 20px;
            letter-spacing: .1em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-section-title i {
            color: var(--club-primary);
        }

        .club-team-layout {
            display: grid;
            grid-template-columns: 180px minmax(0, 1fr);
            min-height: 170px;
        }

        .club-gender-tabs {
            padding: 10px;
            border-right: 1px solid rgba(255,255,255,.09);
            display: grid;
            gap: 8px;
            align-content: start;
        }

        .club-gender-tab {
            min-height: 58px;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 13px;
            background: rgba(255,255,255,.05);
            color: #fff;
            cursor: pointer;
            padding: 9px 10px;
            text-align: left;
            transition: transform .18s ease, background .18s ease, border-color .18s ease;
        }

        .club-gender-tab:hover,
        .club-gender-tab.is-active {
            transform: translateX(2px);
            border-color: var(--club-border);
            background: color-mix(in srgb, var(--club-primary) 18%, rgba(255,255,255,.055));
        }

        .club-gender-tab strong {
            display: block;
            font-family: var(--club-heading);
            font-size: 19px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-gender-tab span {
            display: block;
            margin-top: 5px;
            color: rgba(255,255,255,.68);
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
            gap: 9px;
            padding: 10px 48px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            transform: translateX(22px);
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
            width: 32px;
            height: 32px;
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
            color: var(--club-text-on-accent);
        }

        .club-team-arrow:disabled {
            opacity: .25;
            pointer-events: none;
        }

        .club-team-arrow.is-left { left: 8px; }
        .club-team-arrow.is-right { right: 8px; }

        .club-team-card {
            scroll-snap-align: start;
            width: 142px;
            min-width: 142px;
            min-height: 138px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,.11);
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 18%, transparent), transparent 50%),
                rgba(255,255,255,.05);
            color: #fff;
            text-decoration: none;
            padding: 11px;
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
            top: 9px;
            right: 9px;
            width: 30px;
            height: 30px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--club-primary);
            background: rgba(0,0,0,.32);
        }

        .club-team-name {
            font-family: var(--club-heading);
            font-size: 18px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .club-team-copy {
            margin-top: 5px;
            color: rgba(255,255,255,.68);
            font-size: 11px;
            font-weight: 800;
        }

                .club-footer {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 2px;
            border-left: 0;
            border-right: 0;
            background:
                linear-gradient(180deg, color-mix(in srgb, var(--club-surface) 86%, transparent), rgba(0,0,0,.90));
            box-shadow: none;
        }

                .club-footer-top {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, .86fr);
            gap: 12px;
        }

                .club-footer h2 {
            margin: 0 0 6px;
            font-family: var(--club-heading);
            font-size: clamp(20px, 2.4vw, 30px);
            line-height: 1;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
        }

                .club-footer p {
            margin: 0;
            color: rgba(255,255,255,.68);
            line-height: 1.38;
            font-size: 11.5px;
            font-weight: 650;
        }

        .club-footer-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 7px;
        }

                .club-footer-item {
            min-height: 40px;
            display: flex;
            align-items: center;
            gap: 7px;
            border-radius: 8px;
            padding: 7px;
            background: rgba(255,255,255,.035);
            border: 1px solid rgba(255,255,255,.07);
            color: rgba(255,255,255,.76);
            text-decoration: none;
            font-size: 10.5px;
        }

        .club-footer-item i {
            color: var(--club-primary);
            width: 19px;
            text-align: center;
            flex: 0 0 auto;
        }

        .club-footer-item strong {
            display: block;
            color: #fff;
            font-family: var(--club-heading);
            font-size: 10px;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 2px;
        }

        .club-footer-bottom {
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 14px;
            color: rgba(255,255,255,.56);
            font-size: 11px;
            font-weight: 700;
        }

        .club-sponsor-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .club-sponsor {
            min-height: 26px;
            border-radius: 999px;
            padding: 0 9px;
            display: inline-flex;
            align-items: center;
            color: rgba(255,255,255,.76);
            border: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.045);
            font-size: 10px;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
        }

        @media (max-width: 920px) {
            .club-shell {
                width: min(720px, calc(100% - 16px));
                padding: 10px 0 12px;
            }

            .club-hero {
                grid-template-columns: 1fr;
            }

            .club-hero-main {
                min-height: 320px;
            }

            .club-hero-side {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 7px;
            }

            .club-mini-stat {
                min-height: 62px;
                grid-template-columns: 24px minmax(0, 1fr);
                gap: 7px;
                padding: 9px;
            }

            .club-mini-stat i {
                width: 24px;
                height: 24px;
                font-size: 13px;
            }

            .club-mini-stat span {
                font-size: 7.5px;
                margin-bottom: 3px;
            }

            .club-mini-stat strong {
                font-size: 15px;
            }

            .club-team-layout {
                grid-template-columns: 1fr;
            }

            .club-gender-tabs {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                border-right: 0;
                border-bottom: 1px solid rgba(255,255,255,.09);
                padding: 9px;
            }

            .club-team-slider-wrap {
                min-height: 160px;
            }

            .club-footer-top {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .club-shell {
                width: calc(100% - 14px);
                padding: 7px 0 12px;
                gap: 10px;
            }

            .club-hero-main {
                min-height: 325px;
                border-radius: 18px;
                padding: 18px;
            }

            .club-brand {
                align-items: center;
                gap: 9px;
                margin-bottom: 14px;
            }

            .club-logo {
                width: 54px;
                height: 54px;
                border-radius: 14px;
            }

            .club-name {
                font-size: clamp(24px, 7.2vw, 30px);
                line-height: .92;
                letter-spacing: .07em;
            }

            .club-type {
                font-size: 10px;
                letter-spacing: .22em;
            }

            .club-kicker {
                font-size: 11px;
                letter-spacing: .16em;
            }

            .club-headline {
                font-size: clamp(35px, 10.8vw, 46px);
                line-height: .94;
            }

            .club-copy {
                font-size: 12.5px;
                line-height: 1.45;
            }

            .club-actions {
                margin-top: 15px;
            }

            .club-action {
                min-height: 36px;
                font-size: 11px;
                padding: 0 12px;
            }

            .club-hero-side {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .club-mini-stat {
                min-height: 58px;
                border-radius: 13px;
                padding: 8px;
                grid-template-columns: 22px minmax(0, 1fr);
            }

            .club-mini-stat i {
                width: 22px;
                height: 22px;
                font-size: 12px;
            }

            .club-mini-stat span {
                font-size: 7px;
            }

            .club-mini-stat strong {
                font-size: 13px;
                letter-spacing: .02em;
            }

            .club-teams {
                border-radius: 18px;
            }

            .club-teams-head {
                padding: 11px 12px;
            }

            .club-section-title {
                font-size: 18px;
            }

            .club-gender-tabs {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 7px;
                padding: 8px;
            }

            .club-gender-tab {
                min-height: 54px;
                border-radius: 12px;
                padding: 8px;
            }

            .club-gender-tab strong {
                font-size: 17px;
            }

            .club-gender-tab span {
                font-size: 10px;
            }

            .club-team-slider {
                padding-left: 42px;
                padding-right: 42px;
            }

            .club-team-card {
                width: 132px;
                min-width: 132px;
                min-height: 128px;
            }

            .club-footer {
                border-radius: 18px;
            }

            .club-footer-top {
                padding: 12px;
                gap: 12px;
            }

            .club-footer h2 {
                font-size: 25px;
            }

            .club-footer p {
                font-size: 12px;
            }

            .club-footer-info {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 7px;
            }

            .club-footer-item {
                min-height: 46px;
                padding: 7px;
                font-size: 10.5px;
                align-items: flex-start;
            }

            .club-footer-item i {
                margin-top: 2px;
            }

            .club-footer-bottom {
                align-items: flex-start;
                flex-direction: column;
                padding: 9px 12px;
            }
        }


        @media (max-width: 560px) {
            .club-hero-side {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 6px;
            }

            .club-mini-stat {
                min-height: 54px !important;
                grid-template-columns: 20px minmax(0, 1fr) !important;
                gap: 6px !important;
                padding: 7px !important;
                border-radius: 10px !important;
            }

            .club-mini-stat i {
                width: 20px !important;
                height: 20px !important;
                font-size: 10px !important;
                border-radius: 7px !important;
            }

            .club-mini-stat span {
                font-size: 6.5px !important;
                margin-bottom: 2px !important;
                letter-spacing: .08em !important;
            }

            .club-mini-stat strong {
                font-size: 10.5px !important;
                line-height: 1.05 !important;
                letter-spacing: .02em !important;
            }

            .club-headline {
                font-size: clamp(33px, 9.8vw, 42px) !important;
                line-height: 1.02 !important;
            }

            .club-name {
                font-size: clamp(21px, 6.4vw, 27px) !important;
                line-height: .96 !important;
            }
        }

        @media (max-width: 380px) {
            .club-footer-info {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .club-hero-side {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }

            .club-headline {
                font-size: 40px;
            }
        }

        @media (max-width: 560px) {
            .club-hero-side {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 6px !important;
            }

            .club-mini-stat {
                min-height: 72px !important;
                display: flex !important;
                grid-template-columns: none !important;
                flex-direction: column !important;
                justify-content: center !important;
                gap: 7px !important;
                padding: 9px !important;
                border-radius: 10px !important;
            }

            .club-mini-stat i {
                width: 20px !important;
                height: 20px !important;
                font-size: 10px !important;
                border-radius: 7px !important;
            }

            .club-mini-stat i + span {
                margin-top: -27px !important;
                margin-left: 27px !important;
                min-height: 20px !important;
                font-size: 6.5px !important;
                letter-spacing: .08em !important;
            }

            .club-mini-stat strong {
                width: 100% !important;
                font-size: clamp(10.5px, 3.1vw, 13px) !important;
                line-height: 1.08 !important;
                letter-spacing: .02em !important;
                white-space: normal !important;
                overflow-wrap: normal !important;
                word-break: normal !important;
            }
        }

        @media (max-width: 380px) {
            .club-mini-stat {
                min-height: 70px !important;
                padding: 8px !important;
            }

            .club-mini-stat strong {
                font-size: 10px !important;
                line-height: 1.08 !important;
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
                        <div class="club-hero-main club-card">
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
                        <div class="club-mini-stat club-card">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                            <span>Teams</span>
                            <strong>{{ $teamCount }}</strong>
                        </div>

                        <div class="club-mini-stat club-card">
                            <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                            <span>League</span>
                            <strong>{{ $club->league?->name ?: 'TBD' }}</strong>
                        </div>

                        <div class="club-mini-stat club-card">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <span>Location</span>
                            <strong>{{ $address ?: 'TBD' }}</strong>
                        </div>
                    </aside>
                </section>

                <section class="club-teams club-card" id="club-teams">
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

            <footer class="club-footer club-card">
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

                const amount = Math.max(168, Math.round(activePanel.clientWidth * .72));

                activePanel.scrollBy({
                    left: direction === 'left' ? -amount : amount,
                    behavior: 'smooth',
                });

                window.setTimeout(updateArrows, 230);
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