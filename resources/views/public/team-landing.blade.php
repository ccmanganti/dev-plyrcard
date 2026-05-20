<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $team->name }} | Team</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
        $teamBranding = is_array($team->branding ?? null) ? $team->branding : [];
        $clubBranding = is_array($club?->branding ?? null) ? $club->branding : [];

        $primary = $teamBranding['primary_color'] ?? $clubBranding['primary_color'] ?? $club?->primary_color ?? '#ff3131';
        $secondary = $teamBranding['secondary_color'] ?? $clubBranding['secondary_color'] ?? $club?->secondary_color ?? '#050505';
        $accent = $teamBranding['accent_color'] ?? $clubBranding['accent_color'] ?? $primary;
        $headingFont = $teamBranding['heading_font'] ?? $clubBranding['heading_font'] ?? 'Antonio';
        $bodyFont = $teamBranding['body_font'] ?? $clubBranding['body_font'] ?? 'Inter';

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

        $teamLogo = $resolveAsset($team->logo ?: $club?->logo);
        $heroImageUrl = $resolveAsset(
            $team->background_image
                ?? $team->hero_image
                ?? $teamBranding['background_image']
                ?? $teamBranding['hero_image']
                ?? $club?->background_image
                ?? $club?->hero_image
                ?? $clubBranding['background_image']
                ?? $clubBranding['hero_image']
                ?? null,
            asset('images/PLYRCARD-SITE.jpg')
        );

        $coaches = collect(is_array($team->coaching_staff ?? null) ? $team->coaching_staff : []);
        $headCoach = $coaches->first() ?? [];
        $settings = is_array($team->team_settings ?? null) ? $team->team_settings : [];
        $leagueName = $club?->league?->name ?? ($settings['league'] ?? 'League');
        $subtitle = $team->landing_page_intro ?: ($settings['tagline'] ?? 'We Fly Together');
        $sport = strtolower((string) ($team->sport ?? $settings['sport'] ?? $club?->sport ?? 'soccer'));

        $fieldClass = match (true) {
            str_contains($sport, 'basket') => 'is-basketball',
            str_contains($sport, 'baseball') || str_contains($sport, 'softball') => 'is-baseball',
            str_contains($sport, 'football') => 'is-football',
            default => 'is-soccer',
        };

        $formatPosition = function ($value) {
            if (is_array($value)) {
                $value = collect($value)->filter()->first();
            }

            $value = trim((string) $value);

            if ($value === '') {
                return 'PLYR';
            }

            $short = strtoupper(str_replace('_', ' ', $value));
            $map = [
                'GOALKEEPER' => 'GK',
                'DEFENDER' => 'DEF',
                'CENTER BACK' => 'CB',
                'FULL BACK' => 'FB',
                'WING BACK' => 'WB',
                'MIDFIELDER' => 'MID',
                'DEFENSIVE MIDFIELDER' => 'DM',
                'CENTRAL MIDFIELDER' => 'CM',
                'ATTACKING MIDFIELDER' => 'AM',
                'WINGER' => 'W',
                'FORWARD' => 'FW',
                'STRIKER' => 'ST',
                'POINT GUARD' => 'PG',
                'SHOOTING GUARD' => 'SG',
                'SMALL FORWARD' => 'SF',
                'POWER FORWARD' => 'PF',
                'CENTER' => 'C',
            ];

            return $map[$short] ?? \Illuminate\Support\Str::of($short)->limit(3, '')->toString();
        };

        $formatPositionFull = function ($value) {
            if (is_array($value)) {
                return collect($value)
                    ->filter()
                    ->map(fn ($item) => str((string) $item)->replace('_', ' ')->title())
                    ->implode(', ');
            }

            return str((string) $value)->replace('_', ' ')->title()->toString();
        };

        $teamPlayers = collect($players ?? [])->map(function ($player, $index) use ($resolveAsset, $formatPosition, $formatPositionFull) {
            $playerName = trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? ''));
            $lastName = $player->last_name ?: $playerName;
            $initial = strtoupper(substr($player->first_name ?: $playerName ?: 'P', 0, 1));
            $positionShort = $formatPosition($player->position ?? '');
            $positionFull = $formatPositionFull($player->position ?? '') ?: 'Player';

            $image = $player->player_image ?: $player->plyrcard_image ?: $player->youtube_thumbnail;
            $imageUrl = $resolveAsset($image);

            $website = $player->websites->first();
            $playerUrl = $website
                ? (filled($website->domain)
                    ? 'https://' . preg_replace('/^https?:\/\//', '', $website->domain)
                    : url('/' . ltrim($website->slug, '/')))
                : '';

            $birth = $player->birth ?? null;
            $age = null;

            try {
                $age = $birth ? \Carbon\Carbon::parse($birth)->age : null;
            } catch (\Throwable $e) {
                $age = null;
            }

            return [
                'model' => $player,
                'index' => $index,
                'name' => $playerName ?: 'Player Card',
                'last_name' => $lastName ?: 'Player',
                'initial' => $initial,
                'position_short' => $positionShort,
                'position_full' => $positionFull,
                'image_url' => $imageUrl,
                'url' => $playerUrl,
                'jersey' => $player->jersey_number ?: '',
                'year' => $player->year ?: '',
                'height' => $player->height ?: '',
                'weight' => $player->weight ?: '',
                'gpa' => $player->gpa ?: '',
                'dominant_foot' => $player->dominant_foot ? str((string) $player->dominant_foot)->replace('_', ' ')->title()->toString() : '',
                'gender' => $player->gender ? str((string) $player->gender)->replace('_', ' ')->title()->toString() : '',
                'birth' => $birth ? (string) $birth : '',
                'age' => $age ?: '',
                'city' => $player->city ?: '',
                'state' => $player->state ?: '',
                'sport' => $player->sport ? str((string) $player->sport)->replace('_', ' ')->title()->toString() : '',
                'school' => $player->school?->name ?? '',
                'league' => $player->league?->name ?? '',
                'club' => $player->club?->name ?? '',
                'team_name' => $player->team_name ?: '',
                'national_team' => $player->nationalTeam?->name ?? '',
                'national_team_period' => $player->national_team_period ?: '',
                'club_coach' => $player->club_coach ?: '',
                'club_coach_email' => $player->club_coach_email ?: '',
                'club_coach_phone' => $player->club_coach_phone ?: '',
            ];
        })->values();
    @endphp

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|inter:400,500,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        :root {
            --team-brand-primary: {{ $primary }};
            --team-brand-secondary: {{ $secondary }};
            --team-primary: {{ $autoBorder }};
            --team-secondary: {{ $secondary }};
            --team-accent: {{ $accent }};
            --team-bg: {{ $autoBackground }};
            --team-surface: {{ $autoSurface }};
            --team-border: {{ $autoBorder }};
            --team-glow: {{ $autoGlow }};
            --team-text-on-primary: {{ $textOnPrimary }};
            --team-heading: "{{ $headingFont }}", "Antonio", sans-serif;
            --team-body: "{{ $bodyFont }}", "Inter", sans-serif;
            --app-width: 410px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--team-bg);
            color: #fff;
            font-family: var(--team-body);
            overflow-x: hidden;
        }

        .team-page {
            position: relative;
            min-height: 100vh;
            padding: 10px 7px;
            background:
                radial-gradient(circle at 50% 0%, color-mix(in srgb, var(--team-glow) 22%, transparent), transparent 30%),
                radial-gradient(circle at 85% 16%, color-mix(in srgb, var(--team-secondary) 18%, transparent), transparent 28%),
                linear-gradient(180deg, var(--team-bg) 0%, #010101 100%);
        }

        .team-page::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(180deg, rgba(0,0,0,.68), rgba(0,0,0,.90)),
                url("{{ $heroImageUrl }}") center/cover no-repeat;
            opacity: .42;
            pointer-events: none;
        }

                .team-app {
            position: relative;
            z-index: 2;
            width: min(var(--app-width), 100%);
            margin: 0 auto;
            min-height: calc(100vh - 20px);
            border: 0;
            border-top: 1px solid color-mix(in srgb, var(--team-border) 26%, rgba(255,255,255,.10));
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 7%, transparent), transparent 34%),
                color-mix(in srgb, var(--team-surface) 92%, transparent);
            box-shadow: none;
            overflow: hidden;
        }

        .team-app::after {
            content: "";
            position: absolute;
            z-index: 3;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            pointer-events: none;
            background: linear-gradient(90deg, var(--team-primary), var(--team-secondary), transparent);
        }

        .team-content {
            position: relative;
            z-index: 2;
            padding: 9px;
        }

        .team-top {
            height: 34px;
            display: grid;
            grid-template-columns: 30px 1fr 30px;
            align-items: center;
            margin-bottom: 6px;
        }

        .team-top-btn {
            width: 28px;
            height: 28px;
            border: 0;
            background: transparent;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 16px;
        }

        .team-top-title {
            text-align: center;
            font-family: var(--team-heading);
            font-size: 22px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

                .team-hero-card {
            position: relative;
            border-radius: 6px;
            border: 0;
            border-top: 1px solid color-mix(in srgb, var(--team-border) 26%, rgba(255,255,255,.12));
            overflow: hidden;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 18%, transparent), transparent 48%),
                linear-gradient(215deg, color-mix(in srgb, var(--team-secondary) 24%, transparent), transparent 58%),
                url("{{ $heroImageUrl }}") center/cover no-repeat;
            box-shadow: none;
        }}") center/cover no-repeat;
            box-shadow: 0 12px 30px rgba(0,0,0,.36);
        }

                .team-hero-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(0,0,0,.78), rgba(0,0,0,.52), rgba(0,0,0,.76)),
                linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 22%, transparent), transparent 56%),
                linear-gradient(215deg, color-mix(in srgb, var(--team-secondary) 20%, transparent), transparent 62%);
        }

        .team-hero-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, var(--team-primary), var(--team-brand-primary, var(--team-primary)), var(--team-secondary), transparent) top left / 100% 3px no-repeat;
            pointer-events: none;
            z-index: 2;
        }

        .team-hero-inner {
            position: relative;
            z-index: 2;
            padding: 11px;
        }

        .team-brand-row {
            display: grid;
            grid-template-columns: 60px 1fr;
            gap: 11px;
            align-items: center;
            min-height: 74px;
        }

        .team-logo {
            width: 58px;
            height: 58px;
            border-radius: 15px;
            object-fit: contain;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.13);
            padding: 7px;
        }

        .team-name {
            margin: 0;
            font-family: var(--team-heading);
            font-size: 25px;
            line-height: .92;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .team-tagline {
            margin-top: 6px;
            color: var(--team-primary); filter: drop-shadow(0 0 12px color-mix(in srgb, var(--team-primary) 28%, transparent));
            font-family: var(--team-heading);
            font-size: 10.5px;
            line-height: 1;
            letter-spacing: .16em;
            text-transform: uppercase;
            font-weight: 900;
        }

                .team-meta-row {
            margin-top: 8px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            border-radius: 0;
            overflow: hidden;
            background: rgba(255,255,255,.10);
        }

                .team-meta {
            min-height: 36px;
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 6px 8px;
            background: rgba(255,255,255,.055);
        }

        .team-meta i {
            width: 19px;
            color: rgba(255,255,255,.84);
            text-align: center;
            font-size: 14px;
        }

        .team-meta small {
            display: block;
            color: rgba(255,255,255,.62);
            font-size: 8px;
            line-height: 1;
            margin-bottom: 3px;
        }

        .team-meta strong {
            display: block;
            color: #fff;
            font-size: 11px;
            line-height: 1.05;
            font-weight: 800;
        }

        .team-contact-row {
            margin-top: 7px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }

                .team-contact-btn {
            min-height: 32px;
            border-radius: 0;
            border: 0;
            border-bottom: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.045);
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
            transition: background .18s ease, border-color .18s ease;
        }

        .team-contact-btn:hover {
            transform: translateY(-2px);
            border-color: var(--team-border);
            background: rgba(255,255,255,.08);
        }

        .team-contact-btn i {
            color: var(--team-primary);
            font-size: 13px;
        }

        .squad-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 14px 0 7px;
        }

        .squad-title {
            margin: 0;
            font-family: var(--team-heading);
            font-size: 26px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .squad-hint {
            display: flex;
            align-items: center;
            gap: 6px;
            color: rgba(255,255,255,.56);
            font-size: 10.5px;
            font-weight: 700;
        }

                .field-wrap {
            border-radius: 4px;
            overflow: hidden;
            border: 0;
            border-top: 1px solid rgba(255,255,255,.08);
            background: #061f0c;
        }

        .sport-field {
            position: relative;
            min-height: 350px;
            padding: 22px 8px;
            overflow: hidden;
            background:
                repeating-linear-gradient(90deg, rgba(255,255,255,.026) 0 1px, transparent 1px 48px),
                repeating-linear-gradient(0deg, rgba(255,255,255,.016) 0 1px, transparent 1px 48px),
                linear-gradient(90deg, #062b11 0%, #10461c 14%, #082f12 28%, #10461c 42%, #082f12 56%, #10461c 70%, #082f12 84%, #062b11 100%);
            box-shadow: inset 0 0 60px rgba(0,0,0,.35);
        }

        .sport-field.is-basketball {
            background:
                linear-gradient(90deg, rgba(0,0,0,.18), rgba(0,0,0,.12)),
                linear-gradient(90deg, #9f6428, #c88737, #9f6428);
        }

        .sport-field.is-football {
            background:
                repeating-linear-gradient(90deg, rgba(255,255,255,.035) 0 2px, transparent 2px 56px),
                linear-gradient(90deg, #0c491c, #155c27, #0c491c);
        }

        .sport-field.is-baseball {
            background:
                radial-gradient(circle at 50% 72%, #b87a42 0 13%, transparent 13.4%),
                radial-gradient(circle at 50% 54%, #b87a42 0 8%, transparent 8.4%),
                linear-gradient(180deg, #0d5521, #073d18);
        }

        .field-line {
            position: absolute;
            pointer-events: none;
            border-color: rgba(255,255,255,.15);
        }

        .field-half {
            top: 50%;
            left: 0;
            right: 0;
            border-top: 2px solid rgba(255,255,255,.13);
        }

        .field-circle {
            width: 82px;
            height: 82px;
            border: 2px solid rgba(255,255,255,.13);
            border-radius: 999px;
            left: calc(50% - 41px);
            top: calc(50% - 41px);
        }

        .field-box-top,
        .field-box-bottom {
            left: 19%;
            right: 19%;
            height: 54px;
            border: 2px solid rgba(255,255,255,.13);
        }

        .field-box-top {
            top: 0;
            border-top: 0;
        }

        .field-box-bottom {
            bottom: 0;
            border-bottom: 0;
        }

        .squad-grid {
            position: relative;
            z-index: 4;
            min-height: 294px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-content: center;
            justify-items: center;
            gap: 15px 6px;
        }

        .player-card {
            position: relative;
            width: 64px;
            min-height: 89px;
            border: 0;
            color: #0b0b0b;
            background:
                linear-gradient(145deg, #fff1a4 0%, #e0b044 36%, #bd8628 66%, #ffe789 100%);
            box-shadow: 0 9px 16px rgba(0,0,0,.34);
            clip-path: polygon(11% 0, 89% 0, 100% 15%, 100% 86%, 50% 100%, 0 86%, 0 15%);
            padding: 6px 5px 8px;
            font-family: var(--team-heading);
            cursor: pointer;
            text-align: left;
            transition: transform .18s ease, filter .18s ease;
        }

        .player-card:hover,
        .player-card.is-active-card {
            transform: translateY(-3px) scale(1.04);
            filter: saturate(1.08);
        }

        .player-card.is-active-card {
            outline: 2px solid var(--team-border);
            outline-offset: 3px;
        }

        .player-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, rgba(255,255,255,.38), transparent 38%),
                radial-gradient(circle at 78% 20%, rgba(255,255,255,.30), transparent 22%);
            pointer-events: none;
        }

        .player-number,
        .player-position,
        .player-name,
        .player-tag {
            position: relative;
            z-index: 2;
        }

        .player-number {
            font-size: 14px;
            line-height: 1;
            font-weight: 900;
        }

        .player-position {
            font-size: 8px;
            line-height: 1;
            font-weight: 900;
            text-transform: uppercase;
        }

        .player-img,
        .player-placeholder {
            position: relative;
            z-index: 1;
            width: 78%;
            aspect-ratio: 1/1;
            border-radius: 999px;
            margin: -1px auto 3px;
            object-fit: cover;
            background: rgba(0,0,0,.14);
            border: 1px solid rgba(0,0,0,.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 900;
        }

        .player-name {
            text-align: center;
            font-size: 7.1px;
            line-height: 1.05;
            font-weight: 900;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .player-tag {
            text-align: center;
            color: rgba(0,0,0,.68);
            margin-top: 1px;
            font-size: 6.2px;
            letter-spacing: .04em;
            font-weight: 900;
            text-transform: uppercase;
        }

        .player-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            background: rgba(0,0,0,.74);
            backdrop-filter: blur(12px);
        }

        .player-overlay.is-open {
            display: block;
        }

        .player-overlay.is-switching .player-stats-dialog {
            opacity: .28;
            transform: scale(.985);
        }

                .player-panel {
            position: absolute;
            top: 0;
            right: 0;
            width: min(100%, 520px);
            height: 100%;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 7%, transparent), transparent 34%),
                #050505;
            border-left: 1px solid rgba(255,255,255,.10);
            box-shadow: -18px 0 48px rgba(0,0,0,.50);
            transform: translateX(100%);
            animation: playerPanelIn .24s ease forwards;
        }

        @keyframes playerPanelIn {
            to { transform: translateX(0); }
        }

        .player-panel-bar {
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 0 12px;
            background: rgba(0,0,0,.92);
            border-bottom: 1px solid rgba(255,255,255,.11);
        }

        .player-panel-title {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-family: var(--team-heading);
            font-size: 20px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .player-panel-btn {
            min-height: 34px;
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 999px;
            background: rgba(255,255,255,.07);
            color: #fff;
            cursor: pointer;
            padding: 0 12px;
            font-family: var(--team-heading);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

                .player-nav-arrow {
            position: absolute;
            z-index: 8;
            top: 50%;
            transform: translateY(-50%);
            width: 34px;
            height: 50px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(0,0,0,.58);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 12px 28px rgba(0,0,0,.40);
            backdrop-filter: blur(14px);
            transition: transform .18s ease, background .18s ease, border-color .18s ease;
        }

        .player-nav-arrow:hover {
            transform: translateY(-50%) scale(1.06);
            background: var(--team-primary);
            color: var(--team-text-on-primary);
            border-color: var(--team-border);
        }

        .player-nav-arrow.is-left { left: 6px; }
        .player-nav-arrow.is-right { right: 6px; }

        .player-stats-dialog {
            width: 100%;
            height: calc(100% - 56px);
            overflow: auto;
            display: block;
            padding: 12px;
            background:
                radial-gradient(circle at 18% 0%, color-mix(in srgb, var(--team-glow) 20%, transparent), transparent 34%),
                linear-gradient(180deg, #070707, #020202);
            opacity: 1;
            transform: scale(1);
            transition: opacity .2s ease, transform .2s ease;
        }

        .player-stats-dialog::-webkit-scrollbar {
            width: 7px;
        }

        .player-stats-dialog::-webkit-scrollbar-thumb {
            background: var(--team-primary);
            border-radius: 999px;
        }

                        .player-stats-card {
            max-width: 380px;
            margin: 0 auto;
            border-radius: 0;
            border: 0;
            border-top: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.035);
            box-shadow: none;
            overflow: hidden;
        }

                .player-stats-hero {
            min-height: 92px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 11px;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 24%, transparent), transparent 58%),
                rgba(255,255,255,.032);
        }

                .player-stats-avatar {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            object-fit: cover;
            background: rgba(0,0,0,.35);
            border: 1px solid rgba(255,255,255,.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--team-heading);
            font-size: 25px;
            font-weight: 900;
            color: #fff;
            flex: 0 0 auto;
        }

        .player-stats-name {
            margin: 0;
            font-family: var(--team-heading);
            font-size: clamp(26px, 6vw, 38px);
            line-height: .9;
            letter-spacing: .055em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .player-stats-subtitle {
            margin-top: 5px;
            color: var(--team-primary);
            font-family: var(--team-heading);
            font-size: 12px;
            letter-spacing: .11em;
            text-transform: uppercase;
            font-weight: 900;
        }

                .player-profile-actions {
            display: flex;
            gap: 8px;
            padding: 12px 12px 4px;
        }

                .player-website-btn {
            min-height: 34px;
            border-radius: 3px;
            background: linear-gradient(135deg, var(--team-primary), var(--team-brand-primary, var(--team-secondary)));
            color: var(--team-text-on-primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 12px;
            font-family: var(--team-heading);
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
            box-shadow: none;
        }

                .player-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 8px 12px 10px;
        }

                .player-chip {
            min-height: 26px;
            border-radius: 0;
            border: 0;
            border-bottom: 1px solid rgba(255,255,255,.08);
            background: transparent;
            color: rgba(255,255,255,.76);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0 4px;
            font-size: 10.5px;
            font-weight: 800;
        }

        .player-chip i {
            color: var(--team-primary);
        }

        .player-stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 7px;
            padding: 10px;
        }

                .player-stat-box {
            min-height: 54px;
            border-radius: 0;
            border: 0;
            border-left: 2px solid var(--team-primary);
            background: rgba(255,255,255,.035);
            padding: 8px;
        }

        .player-stat-box span {
            display: block;
            color: rgba(255,255,255,.56);
            font-size: 8.8px;
            text-transform: uppercase;
            letter-spacing: .10em;
            font-weight: 900;
            margin-bottom: 5px;
        }

        .player-stat-box strong {
            display: block;
            color: #fff;
            font-family: var(--team-heading);
            font-size: 17px;
            line-height: 1;
            font-weight: 900;
            overflow-wrap: anywhere;
        }

                .player-stats-section {
            padding: 12px;
            border-top: 1px solid rgba(255,255,255,.08);
        }

        .player-stats-section-title {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 0 0 10px;
            color: rgba(255,255,255,.86);
            font-family: var(--team-heading);
            font-size: 15px;
            letter-spacing: .09em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .player-stats-section-title i {
            color: var(--team-primary);
        }

        .player-info-list {
            display: grid;
            gap: 7px;
        }

                        .player-info-item {
            min-height: 38px;
            border-radius: 0;
            border: 0;
            border-bottom: 1px solid rgba(255,255,255,.08);
            background: transparent;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 2px;
        }

                .player-info-item i {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--team-primary);
            background: transparent;
            flex: 0 0 auto;
        }

        .player-info-item span {
            display: block;
            color: rgba(255,255,255,.58);
            font-size: 9px;
            letter-spacing: .10em;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 2px;
        }

        .player-info-item strong {
            display: block;
            color: #fff;
            font-size: 12px;
            font-weight: 850;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        @media (min-width: 720px) {
            .team-app {
                width: min(410px, 100%);
            }
        }

        @media (max-width: 520px) {
            .team-page {
                padding: 0;
            }

            .team-app {
                width: 100%;
                min-height: 100vh;
                border-radius: 0;
                border-left: 0;
                border-right: 0;
            }

            .team-content {
                padding: 9px;
            }

            .sport-field {
                min-height: 340px;
            }

            .team-brand-row {
                grid-template-columns: 56px 1fr;
                min-height: 70px;
            }

            .team-logo {
                width: 54px;
                height: 54px;
            }

            .team-name {
                font-size: 24px;
                line-height: .92;
            }

            .team-tagline {
                font-size: 9.5px;
                letter-spacing: .14em;
            }

            .squad-grid {
                min-height: 280px;
            }

            .player-panel {
                width: 100%;
            }
        }

        @media (max-width: 360px) {
            .squad-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .player-card {
                width: 58px;
                min-height: 74px;
            }
        }
    </style>
</head>

<body>
    <main class="team-page">
        <div class="team-app">
            <div class="team-content">
                <div class="team-top">
                    <a class="team-top-btn" href="{{ $club?->landing_page_slug ? route('clubs.landing', $club->landing_page_slug) : '/' }}" aria-label="Back to club">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </a>
                    <div class="team-top-title">Team</div>
                    <span aria-hidden="true"></span>
                </div>

                <section class="team-hero-card">
                    <div class="team-hero-inner">
                        <div class="team-brand-row">
                            @if($teamLogo)
                                <img class="team-logo" src="{{ $teamLogo }}" alt="{{ $team->name }} logo">
                            @endif

                            <div>
                                <h1 class="team-name">{{ $team->name }}</h1>
                                <div class="team-tagline">{{ $subtitle }}</div>
                            </div>
                        </div>

                        <div class="team-meta-row">
                            <div class="team-meta">
                                <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                                <div>
                                    <small>League</small>
                                    <strong>{{ $leagueName }}</strong>
                                </div>
                            </div>

                            <div class="team-meta">
                                <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                                <div>
                                    <small>Head Coach</small>
                                    <strong>{{ $headCoach['name'] ?? 'TBA' }}</strong>
                                </div>
                            </div>
                        </div>

                        @if(!empty($headCoach['phone']) || !empty($headCoach['email']))
                            <div class="team-contact-row">
                                @if(!empty($headCoach['phone']))
                                    <a class="team-contact-btn" href="sms:{{ preg_replace('/\D+/', '', $headCoach['phone']) }}">
                                        <i class="fa-solid fa-comment-dots" aria-hidden="true"></i>
                                        Text
                                    </a>

                                    <a class="team-contact-btn" href="tel:{{ preg_replace('/\D+/', '', $headCoach['phone']) }}">
                                        <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                        Call
                                    </a>
                                @endif

                                @if(!empty($headCoach['email']))
                                    <a class="team-contact-btn" href="mailto:{{ $headCoach['email'] }}">
                                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                        Email
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </section>

                <section class="squad-section">
                    <div class="squad-head">
                        <h2 class="squad-title">Squad</h2>
                        <div class="squad-hint">
                            <i class="fa-regular fa-hand-pointer" aria-hidden="true"></i>
                            Tap for player info
                        </div>
                    </div>

                    <div class="field-wrap">
                        <div class="sport-field {{ $fieldClass }}">
                            <div class="field-line field-half"></div>
                            <div class="field-line field-circle"></div>
                            <div class="field-line field-box-top"></div>
                            <div class="field-line field-box-bottom"></div>

                            <div class="squad-grid">
                                @forelse($teamPlayers as $player)
                                    <button
                                        type="button"
                                        class="player-card"
                                        data-player-card
                                        data-player-index="{{ $player['index'] }}"
                                        data-player-name="{{ $player['name'] }}"
                                        data-player-url="{{ $player['url'] }}"
                                        data-player-initial="{{ $player['initial'] }}"
                                        data-player-image="{{ $player['image_url'] }}"
                                        data-player-position="{{ $player['position_full'] }}"
                                        data-player-position-short="{{ $player['position_short'] }}"
                                        data-player-year="{{ $player['year'] }}"
                                        data-player-height="{{ $player['height'] }}"
                                        data-player-weight="{{ $player['weight'] }}"
                                        data-player-gpa="{{ $player['gpa'] }}"
                                        data-player-jersey="{{ $player['jersey'] }}"
                                        data-player-dominant-foot="{{ $player['dominant_foot'] }}"
                                        data-player-gender="{{ $player['gender'] }}"
                                        data-player-birth="{{ $player['birth'] }}"
                                        data-player-age="{{ $player['age'] }}"
                                        data-player-city="{{ $player['city'] }}"
                                        data-player-state="{{ $player['state'] }}"
                                        data-player-sport="{{ $player['sport'] }}"
                                        data-player-school="{{ $player['school'] }}"
                                        data-player-league="{{ $player['league'] }}"
                                        data-player-club="{{ $player['club'] }}"
                                        data-player-team="{{ $player['team_name'] }}"
                                        data-player-national-team="{{ $player['national_team'] }}"
                                        data-player-national-team-period="{{ $player['national_team_period'] }}"
                                        data-player-club-coach="{{ $player['club_coach'] }}"
                                        data-player-club-coach-email="{{ $player['club_coach_email'] }}"
                                        data-player-club-coach-phone="{{ $player['club_coach_phone'] }}"
                                    >
                                        <div class="player-number">{{ $player['jersey'] ?: '#' }}</div>
                                        <div class="player-position">{{ $player['position_short'] }}</div>

                                        @if($player['image_url'])
                                            <img class="player-img" src="{{ $player['image_url'] }}" alt="{{ $player['name'] }}">
                                        @else
                                            <div class="player-placeholder">{{ $player['initial'] }}</div>
                                        @endif

                                        <div class="player-name">{{ $player['initial'] }}. {{ $player['last_name'] }}</div>
                                        <div class="player-tag">PlyrCard</div>
                                    </button>
                                @empty
                                    <div style="grid-column:1/-1;color:rgba(255,255,255,.68);font-weight:800;text-align:center;padding:28px;">
                                        Squad players will appear once they are assigned to this team.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="player-overlay" id="playerOverlay" aria-hidden="true">
            <div class="player-panel">
                <div class="player-panel-bar">
                    <div class="player-panel-title" id="playerPanelTitle">Player Card</div>

                    <button class="player-panel-btn" type="button" id="playerCloseBtn">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        Close
                    </button>
                </div>

                <button class="player-nav-arrow is-left" type="button" id="playerPrevBtn" aria-label="Previous player">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </button>

                <button class="player-nav-arrow is-right" type="button" id="playerNextBtn" aria-label="Next player">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>

                <div class="player-stats-dialog" id="playerStatsDialog" aria-live="polite"></div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cards = Array.from(document.querySelectorAll('[data-player-card]'));
            const overlay = document.getElementById('playerOverlay');
            const statsDialog = document.getElementById('playerStatsDialog');
            const title = document.getElementById('playerPanelTitle');
            const closeBtn = document.getElementById('playerCloseBtn');
            const nextBtn = document.getElementById('playerNextBtn');
            const prevBtn = document.getElementById('playerPrevBtn');
            let activeIndex = 0;

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    })[char];
                });
            }

            function cleanValue(value) {
                if (!value || value === 'null' || value === 'undefined' || value === 'TBD') {
                    return '';
                }

                return String(value);
            }

            function statBox(label, value) {
                const clean = cleanValue(value);

                if (!clean) {
                    return '';
                }

                return `<div class="player-stat-box"><span>${escapeHtml(label)}</span><strong>${escapeHtml(clean)}</strong></div>`;
            }

            function infoItem(icon, label, value) {
                const clean = cleanValue(value);

                if (!clean) {
                    return '';
                }

                return `
                    <div class="player-info-item">
                        <i class="fa-solid ${icon}" aria-hidden="true"></i>
                        <div>
                            <span>${escapeHtml(label)}</span>
                            <strong>${escapeHtml(clean)}</strong>
                        </div>
                    </div>
                `;
            }

            function renderStats(card) {
                const avatar = card.dataset.playerImage
                    ? `<img class="player-stats-avatar" src="${escapeHtml(card.dataset.playerImage)}" alt="${escapeHtml(card.dataset.playerName || 'Player')}">`
                    : `<div class="player-stats-avatar">${escapeHtml(card.dataset.playerInitial || 'P')}</div>`;

                const websiteButton = cleanValue(card.dataset.playerUrl)
                    ? `<div class="player-profile-actions">
                            <a class="player-website-btn" href="${escapeHtml(card.dataset.playerUrl)}" target="_blank" rel="noopener noreferrer">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                Visit Website
                            </a>
                       </div>`
                    : '';

                const chips = [
                    cleanValue(card.dataset.playerJersey) ? `<span class="player-chip"><i class="fa-solid fa-shirt" aria-hidden="true"></i> #${escapeHtml(card.dataset.playerJersey)}</span>` : '',
                    cleanValue(card.dataset.playerCity) ? `<span class="player-chip"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ${escapeHtml(card.dataset.playerCity)}${cleanValue(card.dataset.playerState) ? ', ' + escapeHtml(card.dataset.playerState) : ''}</span>` : '',
                    cleanValue(card.dataset.playerYear) ? `<span class="player-chip"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> ${escapeHtml(card.dataset.playerYear)}</span>` : '',
                ].filter(Boolean).join('');

                const physicalStats = [
                    statBox('Height', card.dataset.playerHeight),
                    statBox('Weight', card.dataset.playerWeight),
                    statBox('GPA', card.dataset.playerGpa),
                    statBox('Age', card.dataset.playerAge),
                    statBox('Class', card.dataset.playerYear),
                    statBox('Foot', card.dataset.playerDominantFoot),
                ].filter(Boolean).join('');

                const teamInfo = [
                    infoItem('fa-futbol', 'Sport', card.dataset.playerSport),
                    infoItem('fa-location-dot', 'Position', card.dataset.playerPosition),
                    infoItem('fa-school', 'School', card.dataset.playerSchool),
                    infoItem('fa-trophy', 'League', card.dataset.playerLeague),
                    infoItem('fa-shield-halved', 'Club', card.dataset.playerClub),
                    infoItem('fa-users', 'Team', card.dataset.playerTeam),
                    infoItem('fa-flag', 'National Team', card.dataset.playerNationalTeam),
                    infoItem('fa-calendar-days', 'National Team Period', card.dataset.playerNationalTeamPeriod),
                ].filter(Boolean).join('');

                const coachInfo = [
                    infoItem('fa-user-tie', 'Club Coach', card.dataset.playerClubCoach),
                    infoItem('fa-envelope', 'Coach Email', card.dataset.playerClubCoachEmail),
                    infoItem('fa-phone', 'Coach Phone', card.dataset.playerClubCoachPhone),
                ].filter(Boolean).join('');

                return `
                    <article class="player-stats-card">
                        <div class="player-stats-hero">
                            ${avatar}
                            <div>
                                <h2 class="player-stats-name">${escapeHtml(card.dataset.playerName || 'Player Card')}</h2>
                                <div class="player-stats-subtitle">${escapeHtml(cleanValue(card.dataset.playerPosition) || 'Player')}</div>
                            </div>
                        </div>

                        ${websiteButton}

                        ${chips ? `<div class="player-chip-row">${chips}</div>` : ''}

                        ${physicalStats ? `<div class="player-stats-grid">${physicalStats}</div>` : ''}

                        ${teamInfo ? `
                            <div class="player-stats-section">
                                <h3 class="player-stats-section-title">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    Player Details
                                </h3>
                                <div class="player-info-list">${teamInfo}</div>
                            </div>
                        ` : ''}

                        ${coachInfo ? `
                            <div class="player-stats-section">
                                <h3 class="player-stats-section-title">
                                    <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                                    Coach Contact
                                </h3>
                                <div class="player-info-list">${coachInfo}</div>
                            </div>
                        ` : ''}
                    </article>
                `;
            }

            function cardByOriginalIndex(index) {
                return cards.find((card) => Number(card.dataset.playerIndex) === Number(index)) || cards[index] || null;
            }

            function currentCardPosition() {
                return cards.findIndex((card) => Number(card.dataset.playerIndex) === Number(activeIndex));
            }

            function openPlayer(originalIndex) {
                const card = cardByOriginalIndex(originalIndex);

                if (!card) {
                    return;
                }

                const alreadyOpen = overlay.classList.contains('is-open');
                activeIndex = Number(card.dataset.playerIndex || 0);

                cards.forEach((item) => item.classList.toggle('is-active-card', item === card));

                if (alreadyOpen) {
                    overlay.classList.add('is-switching');
                }

                window.setTimeout(() => {
                    title.textContent = card.dataset.playerName || 'Player Card';
                    statsDialog.innerHTML = renderStats(card);
                    overlay.classList.add('is-open');
                    overlay.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';

                    window.setTimeout(() => overlay.classList.remove('is-switching'), 80);
                }, alreadyOpen ? 120 : 0);
            }

            function closePlayer() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                statsDialog.innerHTML = '';
                cards.forEach((item) => item.classList.remove('is-active-card'));
                document.body.style.overflow = '';
            }

            function openNextPlayer() {
                if (!cards.length) {
                    return;
                }

                const current = currentCardPosition();
                const nextCard = cards[current + 1] || cards[0];

                openPlayer(Number(nextCard.dataset.playerIndex || 0));
            }

            function openPrevPlayer() {
                if (!cards.length) {
                    return;
                }

                const current = currentCardPosition();
                const prevCard = cards[current - 1] || cards[cards.length - 1];

                openPlayer(Number(prevCard.dataset.playerIndex || 0));
            }

            cards.forEach((card) => {
                card.addEventListener('click', function (event) {
                    event.preventDefault();
                    openPlayer(Number(card.dataset.playerIndex || 0));
                });
            });

            closeBtn?.addEventListener('click', closePlayer);
            nextBtn?.addEventListener('click', openNextPlayer);
            prevBtn?.addEventListener('click', openPrevPlayer);

            overlay?.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    closePlayer();
                }
            });

            let touchStartX = 0;
            let touchStartY = 0;

            overlay?.addEventListener('touchstart', function (event) {
                const touch = event.changedTouches[0];
                touchStartX = touch.clientX;
                touchStartY = touch.clientY;
            }, { passive: true });

            overlay?.addEventListener('touchend', function (event) {
                const touch = event.changedTouches[0];
                const deltaX = touch.clientX - touchStartX;
                const deltaY = touch.clientY - touchStartY;

                if (Math.abs(deltaX) > 60 && Math.abs(deltaX) > Math.abs(deltaY) * 1.4) {
                    if (deltaX < 0) {
                        openNextPlayer();
                    } else {
                        openPrevPlayer();
                    }
                }
            }, { passive: true });

            document.addEventListener('keydown', function (event) {
                if (!overlay.classList.contains('is-open')) {
                    return;
                }

                if (event.key === 'Escape') {
                    closePlayer();
                }

                if (event.key === 'ArrowRight') {
                    openNextPlayer();
                }

                if (event.key === 'ArrowLeft') {
                    openPrevPlayer();
                }
            });
        });
    </script>
</body>
</html>

<style>
@media (max-width: 560px) {
    .team-hero-card,
    .field-wrap,
    .player-stats-card,
    .player-stat-box,
    .player-info-item,
    .team-contact-btn {
        border-radius: 0 !important;
    }

    .team-app {
        border-radius: 0 !important;
    }
}
</style>