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

        $teamPlayers = collect($players ?? [])->map(function ($player, $index) use ($resolveAsset, $formatPosition) {
            $playerName = trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? ''));
            $lastName = $player->last_name ?: $playerName;
            $initial = strtoupper(substr($player->first_name ?: $playerName ?: 'P', 0, 1));
            $position = $formatPosition($player->position ?? '');
            $rating = max(70, min(99, 82 + (($index * 7) % 10)));

            $image = $player->player_image ?: $player->plyrcard_image ?: $player->youtube_thumbnail;
            $imageUrl = $resolveAsset($image);

            $website = $player->websites->first();
            $playerUrl = $website
                ? (filled($website->domain)
                    ? 'https://' . preg_replace('/^https?:\/\//', '', $website->domain)
                    : url('/' . ltrim($website->slug, '/')))
                : '';

            $statsPosition = is_array($player->position ?? null)
                ? collect($player->position)->map(fn ($item) => str($item)->replace('_', ' ')->title())->implode(', ')
                : str((string) ($player->position ?? ''))->replace('_', ' ')->title();

            return [
                'model' => $player,
                'index' => $index,
                'name' => $playerName ?: 'Player Card',
                'last_name' => $lastName ?: 'Player',
                'initial' => $initial,
                'position' => $position,
                'rating' => $rating,
                'image_url' => $imageUrl,
                'url' => $playerUrl,
                'stats_position' => $statsPosition ?: 'Player',
                'year' => $player->year ?: 'TBD',
                'height' => $player->height ?: 'TBD',
                'weight' => $player->weight ?: 'TBD',
                'gpa' => $player->gpa ?: 'TBD',
                'jersey' => $player->jersey_number ?: 'TBD',
                'city' => $player->city ?: 'TBD',
            ];
        })->values();
    @endphp

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|inter:400,500,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        :root {
            --team-primary: {{ $primary }};
            --team-secondary: {{ $secondary }};
            --team-accent: {{ $accent }};
            --team-heading: "{{ $headingFont }}", "Antonio", sans-serif;
            --team-body: "{{ $bodyFont }}", "Inter", sans-serif;
            --app-width: 430px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #000;
            color: #fff;
            font-family: var(--team-body);
            overflow-x: hidden;
        }

        .team-page {
            position: relative;
            min-height: 100vh;
            padding: 18px 10px;
            background:
                radial-gradient(circle at 50% 0%, color-mix(in srgb, var(--team-primary) 22%, transparent), transparent 31%),
                linear-gradient(180deg, #050505 0%, #010101 100%);
        }

        .team-page::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(180deg, rgba(0,0,0,.72), rgba(0,0,0,.92)),
                url("{{ $heroImageUrl }}") center/cover no-repeat;
            opacity: .36;
            pointer-events: none;
        }

        .team-app {
            position: relative;
            z-index: 2;
            width: min(var(--app-width), 100%);
            margin: 0 auto;
            min-height: calc(100vh - 36px);
            background: rgba(2,2,2,.9);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 24px;
            box-shadow: 0 24px 80px rgba(0,0,0,.62);
            overflow: hidden;
        }

        .team-content {
            padding: 12px;
        }

        .team-top {
            height: 42px;
            display: grid;
            grid-template-columns: 32px 1fr 32px;
            align-items: center;
            margin-bottom: 8px;
        }

        .team-top-btn {
            width: 30px;
            height: 30px;
            border: 0;
            background: transparent;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 18px;
        }

        .team-top-title {
            text-align: center;
            font-family: var(--team-heading);
            font-size: 25px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .team-hero-card {
            position: relative;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.12);
            overflow: hidden;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 52%, transparent), transparent 55%),
                url("{{ $heroImageUrl }}") center/cover no-repeat;
            box-shadow: 0 14px 34px rgba(0,0,0,.38);
        }

        .team-hero-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(0,0,0,.82), rgba(0,0,0,.38), rgba(0,0,0,.76)),
                linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 36%, transparent), transparent 60%);
        }

        .team-hero-inner {
            position: relative;
            z-index: 2;
            padding: 16px;
        }

        .team-brand-row {
            display: grid;
            grid-template-columns: 92px 1fr;
            gap: 14px;
            align-items: center;
            min-height: 116px;
        }

        .team-logo {
            width: 92px;
            height: 92px;
            border-radius: 18px;
            object-fit: contain;
            background: rgba(0,0,0,.45);
            border: 1px solid rgba(255,255,255,.14);
            padding: 8px;
        }

        .team-name {
            margin: 0;
            font-family: var(--team-heading);
            font-size: 40px;
            line-height: .88;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .team-tagline {
            margin-top: 7px;
            color: var(--team-primary);
            font-family: var(--team-heading);
            font-size: 17px;
            line-height: 1;
            letter-spacing: .1em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .team-meta-row {
            margin-top: 12px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            border-radius: 12px;
            overflow: hidden;
            background: rgba(255,255,255,.15);
        }

        .team-meta {
            min-height: 47px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: rgba(0,0,0,.48);
        }

        .team-meta i {
            width: 21px;
            color: rgba(255,255,255,.84);
            text-align: center;
            font-size: 16px;
        }

        .team-meta small {
            display: block;
            color: rgba(255,255,255,.64);
            font-size: 10px;
            line-height: 1;
            margin-bottom: 3px;
        }

        .team-meta strong {
            display: block;
            color: #fff;
            font-size: 13px;
            line-height: 1.05;
            font-weight: 800;
        }

        .team-contact-row {
            margin-top: 11px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .team-contact-btn {
            min-height: 41px;
            border-radius: 11px;
            border: 1px solid rgba(255,255,255,.15);
            background: rgba(0,0,0,.44);
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }

        .team-contact-btn:hover {
            transform: translateY(-2px);
            border-color: var(--team-primary);
            background: rgba(255,255,255,.08);
        }

        .team-contact-btn i {
            color: var(--team-primary);
            font-size: 15px;
        }

        .squad-head {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 22px 0 10px;
        }

        .squad-title {
            margin: 0;
            font-family: var(--team-heading);
            font-size: 31px;
            line-height: 1;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .squad-hint {
            display: flex;
            align-items: center;
            gap: 7px;
            color: rgba(255,255,255,.58);
            font-size: 13px;
            font-weight: 700;
        }

        .field-wrap {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.08);
            background: #061f0c;
        }

        .sport-field {
            position: relative;
            min-height: 474px;
            padding: 34px 12px;
            overflow: hidden;
            background:
                repeating-linear-gradient(90deg, rgba(255,255,255,.028) 0 1px, transparent 1px 52px),
                repeating-linear-gradient(0deg, rgba(255,255,255,.018) 0 1px, transparent 1px 52px),
                linear-gradient(90deg, #062b11 0%, #10461c 14%, #082f12 28%, #10461c 42%, #082f12 56%, #10461c 70%, #082f12 84%, #062b11 100%);
            box-shadow: inset 0 0 70px rgba(0,0,0,.35);
        }

        .sport-field.is-basketball {
            background:
                linear-gradient(90deg, rgba(0,0,0,.18), rgba(0,0,0,.12)),
                linear-gradient(90deg, #9f6428, #c88737, #9f6428);
        }

        .sport-field.is-football {
            background:
                repeating-linear-gradient(90deg, rgba(255,255,255,.035) 0 2px, transparent 2px 58px),
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
            border-color: rgba(255,255,255,.16);
        }

        .field-half {
            top: 50%;
            left: 0;
            right: 0;
            border-top: 2px solid rgba(255,255,255,.14);
        }

        .field-circle {
            width: 92px;
            height: 92px;
            border: 2px solid rgba(255,255,255,.14);
            border-radius: 999px;
            left: calc(50% - 46px);
            top: calc(50% - 46px);
        }

        .field-box-top,
        .field-box-bottom {
            left: 18%;
            right: 18%;
            height: 58px;
            border: 2px solid rgba(255,255,255,.14);
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
            min-height: 406px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-content: center;
            justify-items: center;
            gap: 26px 12px;
        }

        .player-card {
            position: relative;
            width: 74px;
            min-height: 104px;
            border: 0;
            color: #0b0b0b;
            background:
                linear-gradient(145deg, #fff1a4 0%, #e0b044 36%, #bd8628 66%, #ffe789 100%);
            box-shadow: 0 10px 18px rgba(0,0,0,.34);
            clip-path: polygon(11% 0, 89% 0, 100% 15%, 100% 86%, 50% 100%, 0 86%, 0 15%);
            padding: 6px 5px 8px;
            font-family: var(--team-heading);
            cursor: pointer;
            text-align: left;
            transition: transform .18s ease, filter .18s ease;
        }

        .player-card:hover,
        .player-card.is-active-card {
            transform: translateY(-4px) scale(1.04);
            filter: saturate(1.08);
        }

        .player-card.is-active-card {
            outline: 2px solid var(--team-primary);
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

        .player-rating,
        .player-position,
        .player-name,
        .player-tag {
            position: relative;
            z-index: 2;
        }

        .player-rating {
            font-size: 15px;
            line-height: 1;
            font-weight: 900;
        }

        .player-position {
            font-size: 8.5px;
            line-height: 1;
            font-weight: 900;
            text-transform: uppercase;
        }

        .player-img,
        .player-placeholder {
            position: relative;
            z-index: 1;
            width: 79%;
            aspect-ratio: 1/1;
            border-radius: 999px;
            margin: -1px auto 3px;
            object-fit: cover;
            background: rgba(0,0,0,.14);
            border: 1px solid rgba(0,0,0,.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 900;
        }

        .player-name {
            text-align: center;
            font-size: 7.6px;
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
            font-size: 6.7px;
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

        .player-overlay.is-switching .player-iframe,
        .player-overlay.is-switching .player-stats-dialog {
            opacity: .28;
            transform: scale(.985);
        }

        .player-panel {
            position: absolute;
            top: 0;
            right: 0;
            width: min(100%, 1040px);
            height: 100%;
            background: #050505;
            border-left: 1px solid rgba(255,255,255,.14);
            box-shadow: -34px 0 90px rgba(0,0,0,.62);
            transform: translateX(100%);
            animation: playerPanelIn .25s ease forwards;
        }

        @keyframes playerPanelIn {
            to { transform: translateX(0); }
        }

        .player-panel-bar {
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0 14px;
            background: rgba(0,0,0,.94);
            border-bottom: 1px solid rgba(255,255,255,.12);
        }

        .player-panel-title {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-family: var(--team-heading);
            font-size: 22px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .player-panel-btn {
            min-height: 40px;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 999px;
            background: rgba(255,255,255,.07);
            color: #fff;
            cursor: pointer;
            padding: 0 14px;
            font-family: var(--team-heading);
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .player-nav-arrow {
            position: absolute;
            z-index: 8;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 70px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.18);
            background: rgba(0,0,0,.64);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 18px 44px rgba(0,0,0,.46);
            backdrop-filter: blur(16px);
            transition: transform .18s ease, background .18s ease, border-color .18s ease;
        }

        .player-nav-arrow:hover {
            transform: translateY(-50%) scale(1.07);
            background: var(--team-primary);
            border-color: var(--team-primary);
        }

        .player-nav-arrow.is-left { left: 12px; }
        .player-nav-arrow.is-right { right: 12px; }

        .player-nav-arrow i {
            font-size: 20px;
        }

        .player-iframe {
            width: 100%;
            height: calc(100% - 64px);
            border: 0;
            background: #111;
            display: block;
            opacity: 1;
            transform: scale(1);
            transition: opacity .2s ease, transform .2s ease;
        }

        .player-stats-dialog {
            width: 100%;
            height: calc(100% - 64px);
            overflow: auto;
            display: none;
            padding: clamp(18px, 3vw, 34px);
            background:
                radial-gradient(circle at 18% 0%, color-mix(in srgb, var(--team-primary) 28%, transparent), transparent 34%),
                linear-gradient(180deg, #080808, #020202);
            opacity: 1;
            transform: scale(1);
            transition: opacity .2s ease, transform .2s ease;
        }

        .player-stats-dialog.is-open {
            display: block;
        }

        .player-stats-card {
            max-width: 720px;
            margin: 0 auto;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.055);
            box-shadow: 0 26px 70px rgba(0,0,0,.42);
            overflow: hidden;
        }

        .player-stats-hero {
            min-height: 190px;
            padding: 24px;
            display: flex;
            align-items: flex-end;
            gap: 18px;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 40%, transparent), transparent 58%),
                rgba(255,255,255,.04);
        }

        .player-stats-avatar {
            width: 96px;
            height: 96px;
            border-radius: 22px;
            object-fit: cover;
            background: rgba(0,0,0,.35);
            border: 1px solid rgba(255,255,255,.14);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--team-heading);
            font-size: 38px;
            font-weight: 900;
            color: #fff;
            flex: 0 0 auto;
        }

        .player-stats-name {
            margin: 0;
            font-family: var(--team-heading);
            font-size: clamp(34px, 5.4vw, 60px);
            line-height: .9;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .player-stats-subtitle {
            margin-top: 8px;
            color: var(--team-primary);
            font-family: var(--team-heading);
            font-size: 16px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 900;
        }

        .player-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            padding: 16px;
        }

        .player-stat-box {
            min-height: 84px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(0,0,0,.26);
            padding: 13px;
        }

        .player-stat-box span {
            display: block;
            color: rgba(255,255,255,.58);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .11em;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .player-stat-box strong {
            display: block;
            color: #fff;
            font-family: var(--team-heading);
            font-size: 22px;
            line-height: 1;
            font-weight: 900;
        }

        .player-stats-note {
            margin: 0 16px 16px;
            padding: 14px;
            border-radius: 16px;
            background: color-mix(in srgb, var(--team-primary) 16%, rgba(255,255,255,.05));
            border: 1px solid color-mix(in srgb, var(--team-primary) 36%, rgba(255,255,255,.12));
            color: rgba(255,255,255,.78);
            font-weight: 700;
            line-height: 1.45;
        }

        @media (min-width: 780px) {
            .team-app {
                width: min(430px, 100%);
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
                padding: 10px;
            }

            .team-top {
                height: 38px;
            }

            .team-hero-inner {
                padding: 14px;
            }

            .team-brand-row {
                grid-template-columns: 76px 1fr;
                min-height: 102px;
                gap: 12px;
            }

            .team-logo {
                width: 76px;
                height: 76px;
                border-radius: 15px;
            }

            .team-name {
                font-size: 34px;
            }

            .team-tagline {
                font-size: 14px;
            }

            .team-meta-row {
                grid-template-columns: 1fr 1fr;
            }

            .team-contact-row {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 6px;
            }

            .team-contact-btn {
                font-size: 10.5px;
                gap: 5px;
                min-height: 38px;
            }

            .team-contact-btn i {
                font-size: 13px;
            }

            .sport-field {
                min-height: 452px;
                padding: 32px 10px;
            }

            .squad-grid {
                gap: 24px 9px;
            }

            .player-card {
                width: 70px;
                min-height: 99px;
            }

            .player-stats-hero {
                align-items: flex-start;
                flex-direction: column;
            }

            .player-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .player-nav-arrow {
                width: 42px;
                height: 60px;
            }

            .player-nav-arrow.is-left { left: 8px; }
            .player-nav-arrow.is-right { right: 8px; }
        }

        @media (max-width: 360px) {
            .squad-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .player-card {
                width: 68px;
                min-height: 96px;
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
                                        Text Coach
                                    </a>

                                    <a class="team-contact-btn" href="tel:{{ preg_replace('/\D+/', '', $headCoach['phone']) }}">
                                        <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                        Call Coach
                                    </a>
                                @endif

                                @if(!empty($headCoach['email']))
                                    <a class="team-contact-btn" href="mailto:{{ $headCoach['email'] }}">
                                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                        Email Coach
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
                            Tap a player card for full stats
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
                                        data-player-position="{{ $player['stats_position'] }}"
                                        data-player-year="{{ $player['year'] }}"
                                        data-player-height="{{ $player['height'] }}"
                                        data-player-weight="{{ $player['weight'] }}"
                                        data-player-gpa="{{ $player['gpa'] }}"
                                        data-player-jersey="{{ $player['jersey'] }}"
                                        data-player-city="{{ $player['city'] }}"
                                    >
                                        <div class="player-rating">{{ $player['rating'] }}</div>
                                        <div class="player-position">{{ $player['position'] }}</div>

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

                <iframe class="player-iframe" id="playerIframe" src="about:blank" title="Player website"></iframe>
                <div class="player-stats-dialog" id="playerStatsDialog" aria-live="polite"></div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cards = Array.from(document.querySelectorAll('[data-player-card]'));
            const overlay = document.getElementById('playerOverlay');
            const iframe = document.getElementById('playerIframe');
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

            function renderStats(card) {
                const avatar = card.dataset.playerImage
                    ? `<img class="player-stats-avatar" src="${escapeHtml(card.dataset.playerImage)}" alt="${escapeHtml(card.dataset.playerName || 'Player')}">`
                    : `<div class="player-stats-avatar">${escapeHtml(card.dataset.playerInitial || 'P')}</div>`;

                return `
                    <article class="player-stats-card">
                        <div class="player-stats-hero">
                            ${avatar}
                            <div>
                                <h2 class="player-stats-name">${escapeHtml(card.dataset.playerName || 'Player Card')}</h2>
                                <div class="player-stats-subtitle">${escapeHtml(card.dataset.playerPosition || 'Player')}</div>
                            </div>
                        </div>

                        <div class="player-stats-grid">
                            <div class="player-stat-box"><span>Graduation</span><strong>${escapeHtml(card.dataset.playerYear || 'TBD')}</strong></div>
                            <div class="player-stat-box"><span>Height</span><strong>${escapeHtml(card.dataset.playerHeight || 'TBD')}</strong></div>
                            <div class="player-stat-box"><span>Weight</span><strong>${escapeHtml(card.dataset.playerWeight || 'TBD')}</strong></div>
                            <div class="player-stat-box"><span>GPA</span><strong>${escapeHtml(card.dataset.playerGpa || 'TBD')}</strong></div>
                            <div class="player-stat-box"><span>Number</span><strong>${escapeHtml(card.dataset.playerJersey || 'TBD')}</strong></div>
                            <div class="player-stat-box"><span>Location</span><strong>${escapeHtml(card.dataset.playerCity || 'TBD')}</strong></div>
                        </div>

                        <p class="player-stats-note">
                            This player does not have a published PlyrCard website yet. Their quick team stats are shown here until their card is ready.
                        </p>
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

                    if (card.dataset.playerUrl) {
                        statsDialog.classList.remove('is-open');
                        statsDialog.innerHTML = '';
                        iframe.style.display = 'block';
                        iframe.src = card.dataset.playerUrl;
                    } else {
                        iframe.src = 'about:blank';
                        iframe.style.display = 'none';
                        statsDialog.innerHTML = renderStats(card);
                        statsDialog.classList.add('is-open');
                    }

                    overlay.classList.add('is-open');
                    overlay.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';

                    window.setTimeout(() => overlay.classList.remove('is-switching'), 80);
                }, alreadyOpen ? 130 : 0);
            }

            function closePlayer() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                iframe.src = 'about:blank';
                iframe.style.display = 'block';
                statsDialog.classList.remove('is-open');
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