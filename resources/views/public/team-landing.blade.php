<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $team->name }} | {{ $club->name }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
        use Illuminate\Support\Str;
        use Carbon\Carbon;

        $teamBranding = is_array($team->branding ?? null) ? $team->branding : [];
        $clubBranding = is_array($club?->branding ?? null) ? $club->branding : [];
        $settings = is_array($team->team_settings ?? null) ? $team->team_settings : [];
        $coaches = collect(is_array($team->coaching_staff ?? null) ? $team->coaching_staff : []);
        $headCoach = $coaches->first() ?? [];

        $primary = $teamBranding['primary_color'] ?? $clubBranding['primary_color'] ?? $club?->primary_color ?? '#ff3131';
        $secondary = $teamBranding['secondary_color'] ?? $clubBranding['secondary_color'] ?? $club?->secondary_color ?? '#050505';
        $accent = $teamBranding['accent_color'] ?? $clubBranding['accent_color'] ?? $primary;
        $headingFont = $teamBranding['heading_font'] ?? $clubBranding['heading_font'] ?? 'Antonio';
        $bodyFont = $teamBranding['body_font'] ?? $clubBranding['body_font'] ?? 'Inter';

        $normalizeHex = function (?string $hex, string $fallback = '#ff3131') {
            $hex = trim((string) $hex);
            if ($hex === '') return $fallback;
            if (! str_starts_with($hex, '#')) $hex = '#' . $hex;
            return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtoupper($hex) : $fallback;
        };

        $primary = $normalizeHex($primary);
        $secondary = $normalizeHex($secondary, '#050505');
        $accent = $normalizeHex($accent, $primary);

        $resolveAsset = function ($value, $fallback = null) {
            if (blank($value)) return $fallback;

            if (is_array($value)) {
                if (isset($value[0])) {
                    $first = $value[0];
                    if (is_string($first)) return filter_var($first, FILTER_VALIDATE_URL) ? $first : asset('storage/' . ltrim($first, '/'));
                    if (is_array($first)) $value = $first;
                }

                $path = $value['url'] ?? $value['path'] ?? $value['image_url'] ?? null;
                if ($path) return filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . ltrim($path, '/'));

                return $fallback;
            }

            $value = trim((string) $value);

            if ($value === '') return $fallback;
            if (filter_var($value, FILTER_VALIDATE_URL)) return $value;

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $resolveAsset($decoded, $fallback);
            }

            return asset('storage/' . ltrim($value, '/'));
        };

        $formatPositionFull = function ($value) {
            if (is_array($value)) {
                return collect($value)
                    ->filter()
                    ->map(fn ($item) => str((string) $item)->replace('_', ' ')->title()->toString())
                    ->implode(' / ');
            }

            $value = trim((string) $value);
            return $value !== '' ? str($value)->replace('_', ' ')->title()->toString() : '';
        };

        $abbreviatePosition = function ($value) use ($formatPositionFull) {
            $position = $formatPositionFull($value);

            if ($position === '') return 'PLYR';

            $map = [
                'Goalkeeper' => 'GK',
                'Keeper' => 'GK',
                'Defender' => 'DEF',
                'Center Back' => 'CB',
                'Centre Back' => 'CB',
                'Left Back' => 'LB',
                'Right Back' => 'RB',
                'Midfielder' => 'MID',
                'Defensive Midfielder' => 'CDM',
                'Central Midfielder' => 'CM',
                'Attacking Midfielder' => 'CAM',
                'Forward' => 'FWD',
                'Striker' => 'ST',
                'Winger' => 'WG',
                'Point Guard' => 'PG',
                'Shooting Guard' => 'SG',
                'Small Forward' => 'SF',
                'Power Forward' => 'PF',
                'Center' => 'C',
            ];

            return collect(explode(' / ', $position))
                ->map(fn ($item) => $map[$item] ?? Str::of($item)->upper()->limit(4, '')->toString())
                ->implode(' / ');
        };

        $teamLogo = $resolveAsset($team->logo ?: $club?->logo);
        $clubLogo = $resolveAsset($club?->logo);
        $leagueLogo = $resolveAsset($club?->league?->logo ?? null);
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

        $teamDisplayTitle = 'Team ' . trim((string) ($settings['age_group'] ?? $settings['age'] ?? Str::of($team->name)->match('/\bU\s?\d{1,2}\b/i') ?: $team->name));
        $subtitle = $team->landing_page_intro ?: ($settings['tagline'] ?? 'Player roster and recruiting information.');
        $leagueName = $club?->league?->name ?? ($settings['league'] ?? 'League');

        $coachSession = $coachCheckIn ?? session('coach_checkin');
        $savedPlayersForClub = collect($savedPlayers ?? session('coach_saved_players', []))
            ->filter(fn ($saved) => (int) ($saved['club_id'] ?? 0) === (int) $club->id);
        $savedPlayerIds = $savedPlayersForClub->pluck('player_id')->map(fn ($id) => (int) $id)->values()->all();

        $teamPlayers = collect($players ?? [])
            ->sortBy([
                fn ($player) => is_numeric($player->jersey_number) ? (int) $player->jersey_number : 9999,
                fn ($player) => strtolower((string) ($player->last_name ?? '')),
                fn ($player) => strtolower((string) ($player->first_name ?? '')),
            ])
            ->values()
            ->map(function ($player, $index) use ($resolveAsset, $formatPositionFull, $abbreviatePosition, $clubLogo, $leagueLogo, $club, $team, $savedPlayerIds) {
                $playerName = trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')) ?: 'Player Card';
                $firstName = trim((string) ($player->first_name ?? ''));
                $lastName = trim((string) ($player->last_name ?? ''));
                $initial = strtoupper(substr($firstName ?: $playerName ?: 'P', 0, 1));

                $roleNames = method_exists($player, 'getRoleNames')
                    ? $player->getRoleNames()->map(fn ($role) => strtolower(trim((string) $role)))->values()
                    : collect();

                $hasPlyrPlus = $roleNames->contains('plyr plus') || $roleNames->contains('plyrplus') || $roleNames->contains('plyr-plus');
                $hasMyJourney = $roleNames->contains('my journey') || $roleNames->contains('myjourney') || $roleNames->contains('my-journey');
                $isPremium = $hasPlyrPlus || $hasMyJourney;
                $isFreeOnly = $roleNames->contains('free') && ! $isPremium;

                $premiumCardImage = $resolveAsset($player->plyrcard_image ?: $player->player_image ?: $player->action_image ?: null);
                $listImage = $isPremium ? $premiumCardImage : null;

                $mobileHeroImageUrl = $isPremium ? $resolveAsset($player->mobile_hero_image ?: null) : null;
                $playerImageUrl = $isPremium ? $resolveAsset($player->player_image ?: $player->action_image ?: $player->plyrcard_image ?: null) : null;
                $nationalLogoUrl = $resolveAsset($player->nationalTeam?->logo ?? $player->national_team_image ?? null);

                $website = $player->websites->first();
                $playerUrl = $website
                    ? (filled($website->domain)
                        ? 'https://' . preg_replace('/^https?:\/\//', '', $website->domain)
                        : url('/' . ltrim($website->slug, '/')))
                    : '';

                $age = '';
                try {
                    $age = $player->birth ? Carbon::parse($player->birth)->age : '';
                } catch (\Throwable $e) {
                    $age = '';
                }

                $positionFull = $formatPositionFull($player->position ?? '');
                $positionShort = $abbreviatePosition($player->position ?? '');
                $classYear = trim((string) ($player->year ?? ''));

                return [
                    'id' => $player->id,
                    'index' => $index,
                    'name' => $playerName,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'initial' => $initial,
                    'isPremium' => $isPremium,
                    'isFreeOnly' => $isFreeOnly,
                    'isSaved' => in_array((int) $player->id, $savedPlayerIds, true),
                    'listImage' => $listImage,
                    'mobileHeroImage' => $mobileHeroImageUrl,
                    'playerImage' => $playerImageUrl,
                    'clubLogo' => $clubLogo,
                    'leagueLogo' => $leagueLogo,
                    'nationalLogo' => $nationalLogoUrl,
                    'jersey' => trim((string) ($player->jersey_number ?? '')),
                    'age' => $age,
                    'positionFull' => $positionFull ?: 'Player',
                    'positionShort' => $positionShort,
                    'year' => $classYear,
                    'height' => trim((string) ($player->height ?? '')),
                    'weight' => trim((string) ($player->weight ?? '')),
                    'gpa' => trim((string) ($player->gpa ?? '')),
                    'dominantFoot' => $player->dominant_foot ? str((string) $player->dominant_foot)->replace('_', ' ')->title()->toString() : '',
                    'birth' => $player->birth ? Carbon::parse($player->birth)->format('M j, Y') : '',
                    'maxSpeed' => trim((string) ($player->max_speed ?? '')),
                    'sport' => $player->sport ? str((string) $player->sport)->replace('_', ' ')->title()->toString() : '',
                    'school' => $player->school?->name ?? '',
                    'league' => $player->league?->name ?? $club?->league?->name ?? '',
                    'club' => $player->club?->name ?? $club?->name ?? '',
                    'team' => $player->team_name ?: $team->name,
                    'nationalTeam' => $player->nationalTeam?->name ?? '',
                    'clubCoach' => $player->club_coach ?: '',
                    'clubCoachEmail' => $player->club_coach_email ?: '',
                    'clubCoachPhone' => $player->club_coach_phone ?: '',
                    'email' => $player->email ?: $player->personal_email ?: '',
                    'phone' => $player->phone ?: '',
                    'parentEmail' => $player->parent_email ?: '',
                    'parentPhone' => $player->parent_phone ?: '',
                    'websiteUrl' => $playerUrl,
                    'city' => $player->city ?: '',
                    'state' => $player->state ?: '',
                ];
            })
            ->values();
    @endphp

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|inter:400,500,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        :root{
            --team-primary: {{ $primary }};
            --team-secondary: {{ $secondary }};
            --team-accent: {{ $accent }};
            --team-heading: "{{ $headingFont }}", "Antonio", sans-serif;
            --team-body: "{{ $bodyFont }}", "Inter", sans-serif;
            --team-bg: #050506;
            --team-surface: #111114;
            --team-line: rgba(255,255,255,.10);
            --team-muted: rgba(255,255,255,.66);
            --team-on-primary: #ffffff;
            --app-width: 430px;
        }

        *{ box-sizing:border-box; }

        body{
            margin:0;
            min-height:100vh;
            background:var(--team-bg);
            color:#fff;
            font-family:var(--team-body);
            overflow-x:hidden;
        }

        .team-page{
            min-height:100vh;
            background:
                radial-gradient(circle at 15% 0%, color-mix(in srgb, var(--team-primary) 24%, transparent), transparent 28%),
                radial-gradient(circle at 90% 8%, color-mix(in srgb, var(--team-secondary) 28%, transparent), transparent 30%),
                #030304;
        }

        .team-app{
            width:min(var(--app-width), 100%);
            min-height:100vh;
            margin:0 auto;
            background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.015));
        }

        .team-top{
            height:48px;
            display:grid;
            grid-template-columns:44px 1fr 44px;
            align-items:center;
            padding:0 6px;
            background:rgba(0,0,0,.52);
            backdrop-filter:blur(14px);
            position:sticky;
            top:0;
            z-index:20;
        }

        .team-top-btn{
            width:36px;
            height:36px;
            border:0;
            color:#fff;
            background:transparent;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            text-decoration:none;
        }

        .team-top-title{
            text-align:center;
            font-family:var(--team-heading);
            font-size:21px;
            line-height:1;
            letter-spacing:.10em;
            text-transform:uppercase;
            font-weight:900;
        }

        .team-hero{
            position:relative;
            min-height:255px;
            overflow:hidden;
            background:#111;
        }

        .team-hero img.team-hero-bg{
            position:absolute;
            inset:0;
            width:100%;
            height:100%;
            object-fit:cover;
            z-index:0;
        }

        .team-hero::before{
            content:"";
            position:absolute;
            inset:0;
            z-index:1;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 72%, transparent), transparent 48%),
                linear-gradient(215deg, color-mix(in srgb, var(--team-secondary) 76%, transparent), transparent 58%),
                linear-gradient(180deg, rgba(0,0,0,.36), rgba(0,0,0,.82));
            mix-blend-mode:multiply;
        }

        .team-hero::after{
            content:"";
            position:absolute;
            inset:0;
            z-index:2;
            background:linear-gradient(180deg, rgba(0,0,0,.10), rgba(0,0,0,.78));
        }

        .team-hero-inner{
            position:relative;
            z-index:3;
            min-height:255px;
            padding:18px 16px;
            display:flex;
            flex-direction:column;
            justify-content:flex-end;
        }

        .team-brand-row{
            display:flex;
            align-items:center;
            gap:12px;
            margin-bottom:16px;
        }

        .team-logo{
            width:66px;
            height:66px;
            object-fit:contain;
            background:transparent;
            border:0;
            box-shadow:none;
            flex:0 0 auto;
        }

        .team-kicker{
            color:rgba(255,255,255,.82);
            font-size:11px;
            letter-spacing:.18em;
            text-transform:uppercase;
            font-weight:900;
        }

        .team-name{
            margin:0;
            font-family:var(--team-heading);
            font-size:46px;
            line-height:.90;
            letter-spacing:.045em;
            text-transform:uppercase;
            font-weight:900;
        }

        .team-subtitle{
            margin-top:8px;
            max-width:330px;
            color:rgba(255,255,255,.78);
            font-size:13px;
            line-height:1.45;
            font-weight:700;
        }

        .team-meta-strip{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:1px;
            background:rgba(255,255,255,.10);
        }

        .team-meta{
            min-height:60px;
            padding:11px 12px;
            background:rgba(255,255,255,.045);
            display:flex;
            align-items:center;
            gap:10px;
        }

        .team-meta i{ color:var(--team-primary); width:18px; text-align:center; }

        .team-meta span{
            display:block;
            color:rgba(255,255,255,.60);
            font-size:8px;
            text-transform:uppercase;
            letter-spacing:.14em;
            font-weight:900;
            margin-bottom:4px;
        }

        .team-meta strong{
            display:block;
            font-size:12px;
            line-height:1.12;
            font-weight:850;
        }

        .coach-session-bar{
            margin:12px 10px 0;
            min-height:42px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            padding:9px 10px;
            background:rgba(255,255,255,.055);
            border-left:3px solid var(--team-primary);
        }

        .coach-session-bar strong{
            display:block;
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.08em;
            font-weight:900;
        }

        .coach-session-bar span{
            color:rgba(255,255,255,.64);
            font-size:11px;
            font-weight:700;
        }

        .squad-section{ padding:14px 10px 28px; }

        .squad-head{
            display:flex;
            justify-content:space-between;
            align-items:flex-end;
            gap:10px;
            margin-bottom:10px;
        }

        .squad-title{
            margin:0;
            font-family:var(--team-heading);
            font-size:31px;
            line-height:1;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
        }

        .squad-sort{
            color:rgba(255,255,255,.62);
            font-size:10px;
            font-weight:800;
            letter-spacing:.08em;
            text-transform:uppercase;
        }

        .player-list{
            display:grid;
            gap:8px;
        }

        .player-row{
            min-height:90px;
            width:100%;
            border:0;
            text-align:left;
            cursor:pointer;
            color:#fff;
            display:grid;
            grid-template-columns:70px minmax(0, 1fr) auto;
            align-items:center;
            gap:12px;
            padding:9px 11px 9px 9px;
            background:
                linear-gradient(90deg, color-mix(in srgb, var(--team-primary) 14%, transparent), transparent 40%),
                rgba(255,255,255,.065);
            border-left:3px solid transparent;
            transition:transform .16s ease, background .16s ease, border-color .16s ease;
        }

        .player-row:hover,
        .player-row.is-active{
            transform:translateY(-1px);
            border-left-color:var(--team-primary);
            background:
                linear-gradient(90deg, color-mix(in srgb, var(--team-primary) 24%, transparent), transparent 48%),
                rgba(255,255,255,.09);
        }

        .player-media{
            width:64px;
            height:72px;
            border-radius:10px;
            overflow:hidden;
            display:flex;
            align-items:center;
            justify-content:center;
            background:rgba(0,0,0,.34);
            flex:0 0 auto;
        }

        .player-media img{
            width:100%;
            height:100%;
            object-fit:cover;
        }

        .player-free-shape{
            width:54px;
            height:54px;
            border-radius:999px;
            background:
                radial-gradient(circle at 50% 36%, rgba(255,255,255,.96) 0 18%, transparent 19%),
                radial-gradient(circle at 50% 84%, rgba(255,255,255,.90) 0 30%, transparent 31%),
                color-mix(in srgb, var(--team-primary) 42%, #171717);
            box-shadow:inset 0 0 0 2px rgba(255,255,255,.14);
        }

        .player-row-name{
            font-family:var(--team-heading);
            font-size:21px;
            line-height:1;
            letter-spacing:.04em;
            text-transform:uppercase;
            font-weight:900;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }

        .player-row-meta{
            margin-top:6px;
            color:rgba(255,255,255,.68);
            display:flex;
            flex-wrap:wrap;
            gap:6px 10px;
            font-size:11px;
            font-weight:800;
        }

        .player-row-meta span{
            display:inline-flex;
            align-items:center;
            gap:5px;
        }

        .player-number-pill{
            min-width:44px;
            text-align:right;
            font-family:var(--team-heading);
            color:var(--team-primary);
            font-size:28px;
            line-height:1;
            font-weight:900;
        }

        .player-overlay{
            position:fixed;
            inset:0;
            z-index:1000;
            display:none;
            background:rgba(0,0,0,.78);
            backdrop-filter:blur(14px);
        }

        .player-overlay.is-open{ display:block; }

        .player-panel{
            position:absolute;
            inset:0 0 0 auto;
            width:min(100%, 470px);
            background:#030304;
            transform:translateX(100%);
            animation:panelIn .22s ease forwards;
        }

        @keyframes panelIn{ to{ transform:translateX(0); } }

        .player-panel-bar{
            height:54px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:8px;
            padding:0 10px;
            background:rgba(0,0,0,.92);
            border-bottom:1px solid rgba(255,255,255,.10);
        }

        .player-panel-title{
            min-width:0;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
            font-family:var(--team-heading);
            font-size:19px;
            text-transform:uppercase;
            letter-spacing:.08em;
            font-weight:900;
        }

        .player-panel-btn{
            min-height:34px;
            border:0;
            border-radius:999px;
            padding:0 12px;
            background:rgba(255,255,255,.09);
            color:#fff;
            cursor:pointer;
            font-family:var(--team-heading);
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
        }

        .player-dialog{
            height:calc(100% - 54px);
            overflow:auto;
            padding:12px 12px 28px;
        }

        .player-nav-arrow{
            position:absolute;
            top:50%;
            z-index:6;
            width:34px;
            height:54px;
            border:0;
            border-radius:10px;
            background:rgba(0,0,0,.58);
            color:#fff;
            cursor:pointer;
        }

        .player-nav-arrow:hover{ background:var(--team-primary); }

        .player-nav-arrow.is-left{ left:6px; }
        .player-nav-arrow.is-right{ right:6px; }

        .mobile-card{
            position:relative;
            width:min(390px, 100%);
            margin:0 auto;
            min-height:680px;
            overflow:hidden;
            background:var(--team-primary);
            color:#fff;
        }

        .mobile-card-override{
            width:min(390px, 100%);
            margin:0 auto;
            background:var(--team-primary);
        }

        .mobile-card-override img{
            display:block;
            width:100%;
            height:auto;
        }

        .mobile-bg-number{
            position:absolute;
            left:4px;
            top:170px;
            z-index:1;
            letter-spacing:-18px;
            font-family:"Iceberg", var(--team-heading);
            font-size:250px;
            line-height:.8;
            color:rgba(255,255,255,.10);
            pointer-events:none;
        }

        .mobile-top{
            position:relative;
            z-index:4;
            padding:12px 16px 0;
        }

        .mobile-logo-row{
            display:flex;
            justify-content:flex-end;
            min-height:40px;
        }

        .mobile-logo-row img{
            max-height:38px;
            width:auto;
            object-fit:contain;
        }

        .mobile-head{
            position:relative;
            margin-top:18px;
            min-height:430px;
        }

        .mobile-name-wrap{
            position:relative;
            z-index:5;
            width:58%;
        }

        .mobile-jersey,
        .mobile-first,
        .mobile-last,
        .mobile-position{
            font-family:var(--team-heading);
            text-transform:uppercase;
            color:#fff;
        }

        .mobile-jersey{
            font-size:38px;
            line-height:.9;
            font-weight:700;
            letter-spacing:-.04em;
        }

        .mobile-first{
            font-size:45px;
            line-height:1;
            font-weight:900;
            letter-spacing:-.05em;
        }

        .mobile-last{
            font-size:56px;
            line-height:.86;
            font-weight:900;
            letter-spacing:-.05em;
        }

        .mobile-position{
            margin-top:12px;
            font-size:22px;
            line-height:.95;
            font-weight:800;
        }

        .mobile-signature{
            position:absolute;
            left:8px;
            top:180px;
            z-index:1;
            font-size:92px;
            line-height:1;
            color:rgba(255,255,255,.14);
            font-family:cursive;
            transform:rotate(-7deg);
            pointer-events:none;
        }

        .mobile-player-stage{
            position:absolute;
            right:-10px;
            bottom:0;
            width:63%;
            height:440px;
            z-index:10;
            display:flex;
            align-items:flex-end;
            justify-content:center;
            pointer-events:none;
        }

        .mobile-player-stage img{
            width:auto;
            height:100%;
            max-width:none;
            object-fit:contain;
            object-position:bottom center;
            filter:drop-shadow(0 14px 24px rgba(0,0,0,.20));
        }

        .mobile-info-grid{
            position:absolute;
            left:6px;
            right:6px;
            bottom:70px;
            z-index:8;
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:6px;
        }

        .mobile-stat-card{
            min-height:240px;
            background:#f1f1f1;
            color:#111;
            border-radius:8px;
            padding:10px 10px 12px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
            overflow:hidden;
        }

        .mobile-big-row,
        .mobile-class-row{
            display:flex;
            align-items:flex-end;
            gap:6px;
            margin-bottom:12px;
            line-height:.8;
        }

        .mobile-big-value,
        .mobile-class-year{
            font-family:var(--team-heading);
            font-size:68px;
            line-height:.8;
            font-weight:900;
            letter-spacing:-.05em;
            color:#000;
        }

        .mobile-big-label,
        .mobile-class-label{
            font-family:var(--team-heading);
            font-size:22px;
            line-height:.9;
            font-weight:900;
            text-transform:uppercase;
            color:#000;
            padding-bottom:8px;
        }

        .mobile-org-list{ display:grid; gap:12px; }

        .mobile-org-row{
            display:grid;
            grid-template-columns:42px 1fr;
            gap:10px;
            align-items:center;
        }

        .mobile-org-row img,
        .mobile-org-fallback{
            width:42px;
            height:42px;
            object-fit:contain;
        }

        .mobile-org-title{
            font-family:var(--team-heading);
            font-size:18px;
            line-height:.95;
            font-weight:900;
            text-transform:uppercase;
            color:#111;
        }

        .mobile-org-value{
            margin-top:2px;
            font-family:var(--team-heading);
            font-size:12px;
            line-height:1.05;
            font-weight:600;
            color:#111;
        }

        .mobile-meta{
            display:grid;
            gap:10px;
            padding-top:2px;
        }

        .mobile-meta-row{
            display:grid;
            grid-template-columns:1fr auto;
            gap:10px;
            align-items:baseline;
        }

        .mobile-meta-label,
        .mobile-meta-value{
            font-family:var(--team-heading);
            font-size:15px;
            line-height:1;
            text-transform:uppercase;
            color:#111;
        }

        .mobile-meta-label{ font-weight:600; }
        .mobile-meta-value{ font-weight:900; text-align:right; }

        .player-actions{
            width:min(390px, 100%);
            margin:10px auto 0;
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:8px;
        }

        .player-action{
            min-height:42px;
            border:0;
            background:rgba(255,255,255,.08);
            color:#fff;
            text-decoration:none;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            font-family:var(--team-heading);
            font-size:12px;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
            cursor:pointer;
        }

        .player-action.primary{
            background:linear-gradient(135deg, var(--team-primary), var(--team-secondary));
        }

        .player-action.is-saved{
            background:rgba(255,255,255,.16);
            color:rgba(255,255,255,.68);
            cursor:default;
        }

        .empty-squad{
            padding:22px;
            color:rgba(255,255,255,.68);
            background:rgba(255,255,255,.06);
            font-weight:800;
            text-align:center;
        }

        @media (max-width:520px){
            .team-app{ width:100%; }
            .player-panel{ width:100%; }
        }
    </style>
</head>
<body>
    <main class="team-page">
        <div class="team-app">
            <div class="team-top">
                <a class="team-top-btn" href="{{ $club?->landing_page_slug ? route('clubs.landing', $club->landing_page_slug) : '/' }}" aria-label="Back to club">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </a>
                <div class="team-top-title">{{ $teamDisplayTitle }}</div>
                <span></span>
            </div>

            <section class="team-hero">
                <img class="team-hero-bg" src="{{ $heroImageUrl }}" alt="{{ $team->name }} background">

                <div class="team-hero-inner">
                    <div class="team-brand-row">
                        @if($teamLogo)
                            <img class="team-logo" src="{{ $teamLogo }}" alt="{{ $team->name }} logo">
                        @endif

                        <div>
                            <div class="team-kicker">{{ $leagueName }}</div>
                            <h1 class="team-name">{{ $team->name }}</h1>
                            <div class="team-subtitle">{{ $subtitle }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="team-meta-strip">
                <div class="team-meta">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    <div>
                        <span>Roster</span>
                        <strong>{{ $teamPlayers->count() }} player{{ $teamPlayers->count() === 1 ? '' : 's' }}</strong>
                    </div>
                </div>

                <div class="team-meta">
                    <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                    <div>
                        <span>Head Coach</span>
                        <strong>{{ $headCoach['name'] ?? 'TBA' }}</strong>
                    </div>
                </div>
            </div>

            @if($coachSession)
                <div class="coach-session-bar">
                    <div>
                        <strong>Coach checked in</strong>
                        <span>{{ $coachSession['name'] ?? 'Coach' }} · {{ count($savedPlayerIds) }} saved</span>
                    </div>
                    <i class="fa-solid fa-circle-check" style="color:var(--team-primary)" aria-hidden="true"></i>
                </div>
            @endif

            <section class="squad-section">
                <div class="squad-head">
                    <h2 class="squad-title">Players</h2>
                    <div class="squad-sort">
                        <i class="fa-solid fa-arrow-down-1-9" aria-hidden="true"></i>
                        Number
                    </div>
                </div>

                <div class="player-list">
                    @forelse($teamPlayers as $player)
                        <button
                            type="button"
                            class="player-row"
                            data-player-card
                            data-player='@json($player)'
                        >
                            <span class="player-media">
                                @if($player['listImage'])
                                    <img src="{{ $player['listImage'] }}" alt="{{ $player['name'] }}">
                                @else
                                    <span class="player-free-shape" aria-hidden="true"></span>
                                @endif
                            </span>

                            <span>
                                <span class="player-row-name">{{ $player['name'] }}</span>
                                <span class="player-row-meta">
                                    @if($player['age']) <span><i class="fa-solid fa-cake-candles"></i> {{ $player['age'] }}</span>@endif
                                    @if($player['positionFull']) <span><i class="fa-solid fa-location-dot"></i> {{ $player['positionFull'] }}</span>@endif
                                    @if($player['year']) <span><i class="fa-solid fa-graduation-cap"></i> {{ $player['year'] }}</span>@endif
                                </span>
                            </span>

                            <span class="player-number-pill">{{ $player['jersey'] ? '#' . ltrim($player['jersey'], '#') : '--' }}</span>
                        </button>
                    @empty
                        <div class="empty-squad">Squad players will appear once they are assigned to this team.</div>
                    @endforelse
                </div>
            </section>
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

                <div class="player-dialog" id="playerDialog" aria-live="polite"></div>
            </div>
        </div>
    </main>

    @php
        $currentGenderSegment = request()->route('gender') ?? $team->landingGenderSegment();

        $coachSavePlayerUrlTemplate = route('clubs.coach-save-player', [
            'clubSlug' => $club->landing_page_slug,
            'gender' => $currentGenderSegment,
            'teamSlug' => $team->landing_page_slug,
            'player' => '__PLAYER_ID__',
        ]);

        $coachUnsavePlayerUrlTemplate = route('clubs.coach-unsave-player', [
            'clubSlug' => $club->landing_page_slug,
            'gender' => $currentGenderSegment,
            'teamSlug' => $team->landing_page_slug,
            'player' => '__PLAYER_ID__',
        ]);
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cards = Array.from(document.querySelectorAll('[data-player-card]'));
            const overlay = document.getElementById('playerOverlay');
            const dialog = document.getElementById('playerDialog');
            const title = document.getElementById('playerPanelTitle');
            const closeBtn = document.getElementById('playerCloseBtn');
            const nextBtn = document.getElementById('playerNextBtn');
            const prevBtn = document.getElementById('playerPrevBtn');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const saveUrlTemplate = @json($coachSavePlayerUrlTemplate);
            const unsaveUrlTemplate = @json($coachUnsavePlayerUrlTemplate);
            const coachCheckedIn = @json((bool) $coachSession);
            let activeIndex = 0;

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
                });
            }

            function tel(value) {
                return String(value || '').replace(/\D+/g, '');
            }

            function clean(value, fallback = '') {
                if (value === null || value === undefined || value === '' || value === 'null' || value === 'undefined') {
                    return fallback;
                }

                return String(value);
            }

            function playerFromCard(card) {
                try {
                    return JSON.parse(card.getAttribute('data-player') || '{}');
                } catch (error) {
                    return {};
                }
            }

            function orgRow(title, value, logo) {
                if (!clean(value)) return '';

                const icon = clean(logo)
                    ? `<img src="${escapeHtml(logo)}" alt="${escapeHtml(title)} logo">`
                    : `<svg class="mobile-org-fallback" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3h8v3a4 4 0 0 1-8 0V3Z"/><path d="M6 5H4a3 3 0 0 0 3 3"/><path d="M18 5h2a3 3 0 0 1-3 3"/><path d="M12 9v7"/><path d="M8 21h8"/><path d="M9.5 16h5"/></svg>`;

                return `
                    <div class="mobile-org-row">
                        ${icon}
                        <div>
                            <div class="mobile-org-title">${escapeHtml(title)}</div>
                            <div class="mobile-org-value">${escapeHtml(value)}</div>
                        </div>
                    </div>
                `;
            }

            function renderMobileCard(player) {
                if (clean(player.mobileHeroImage)) {
                    return `
                        <div class="mobile-card-override">
                            <img src="${escapeHtml(player.mobileHeroImage)}" alt="${escapeHtml(player.name || 'Player')}">
                        </div>
                    `;
                }

                const first = clean(player.firstName, 'PLAYER').toUpperCase();
                const last = clean(player.lastName, 'LASTNAME').toUpperCase();
                const jersey = clean(player.jersey) ? '#' + clean(player.jersey).replace(/^#/, '') : '';
                const playerImage = clean(player.playerImage);
                const topLogo = clean(player.leagueLogo || player.clubLogo || player.nationalLogo);
                const bgNumber = clean(player.jersey, '00');

                const orgRows = [
                    orgRow('National Team', player.nationalTeam, player.nationalLogo),
                    orgRow('Club', player.club, player.clubLogo),
                    orgRow('League', player.league, player.leagueLogo),
                ].filter(Boolean).join('');

                return `
                    <article class="mobile-card">
                        <div class="mobile-bg-number">${escapeHtml(bgNumber)}</div>

                        <div class="mobile-top">
                            <div class="mobile-logo-row">
                                ${topLogo ? `<img src="${escapeHtml(topLogo)}" alt="Logo">` : ''}
                            </div>

                            <div class="mobile-head">
                                <div class="mobile-name-wrap">
                                    ${jersey ? `<div class="mobile-jersey">${escapeHtml(jersey)}</div>` : ''}
                                    <div class="mobile-first">${escapeHtml(first)}</div>
                                    <div class="mobile-last">${escapeHtml(last)}</div>
                                    <div class="mobile-position">${escapeHtml(clean(player.positionShort, 'POSITION'))}</div>
                                    <div class="mobile-signature">${escapeHtml(clean(player.firstName, 'Name'))}</div>
                                </div>

                                <div class="mobile-player-stage">
                                    ${playerImage ? `<img src="${escapeHtml(playerImage)}" alt="${escapeHtml(player.name || 'Player')}">` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="mobile-info-grid">
                            <div class="mobile-stat-card">
                                <div class="mobile-big-row">
                                    <div class="mobile-big-value">${escapeHtml(clean(player.gpa, '0.0'))}</div>
                                    <div class="mobile-big-label">/GPA</div>
                                </div>
                                <div class="mobile-org-list">${orgRows}</div>
                            </div>

                            <div class="mobile-stat-card">
                                <div class="mobile-class-row">
                                    <div class="mobile-class-year">${escapeHtml(clean(player.year, '2026'))}</div>
                                    <div class="mobile-class-label">/CLASS</div>
                                </div>

                                <div class="mobile-meta">
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">Height:</div><div class="mobile-meta-value">${escapeHtml(clean(player.height, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">Weight:</div><div class="mobile-meta-value">${escapeHtml(clean(player.weight, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">Max Speed:</div><div class="mobile-meta-value">${escapeHtml(clean(player.maxSpeed, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">Dominant Foot:</div><div class="mobile-meta-value">${escapeHtml(clean(player.dominantFoot, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">DOB:</div><div class="mobile-meta-value">${escapeHtml(clean(player.birth, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">Coach:</div><div class="mobile-meta-value">${escapeHtml(clean(player.clubCoach, '--'))}</div></div>
                                </div>
                            </div>
                        </div>
                    </article>
                `;
            }

            function actionLink(icon, label, href, extraClass = '') {
                if (!href) return '';
                return `<a class="player-action ${extraClass}" href="${escapeHtml(href)}"><i class="fa-solid ${icon}" aria-hidden="true"></i>${escapeHtml(label)}</a>`;
            }

            function renderActions(player) {
                const phone = tel(player.phone);
                const parentPhone = tel(player.parentPhone);
                const coachPhone = tel(player.clubCoachPhone);
                const saveButton = coachCheckedIn
                    ? `<button class="player-action primary ${player.isSaved ? 'is-saved' : ''}" type="button" data-save-player="${escapeHtml(player.id)}" ${player.isSaved ? 'disabled' : ''}>
                            <i class="fa-solid ${player.isSaved ? 'fa-check' : 'fa-plus'}" aria-hidden="true"></i>
                            ${player.isSaved ? 'Saved' : 'Save Player'}
                       </button>`
                    : '';

                return `
                    <div class="player-actions">
                        ${saveButton}
                        ${actionLink('fa-envelope', 'Email Player', clean(player.email) ? 'mailto:' + player.email : '', '')}
                        ${actionLink('fa-phone', 'Call Player', phone ? 'tel:' + phone : '', '')}
                        ${actionLink('fa-comment-dots', 'Text Player', phone ? 'sms:' + phone : '', '')}
                        ${actionLink('fa-envelope-open-text', 'Email Parent', clean(player.parentEmail) ? 'mailto:' + player.parentEmail : '', '')}
                        ${actionLink('fa-phone-volume', 'Call Parent', parentPhone ? 'tel:' + parentPhone : '', '')}
                        ${actionLink('fa-user-tie', 'Email Coach', clean(player.clubCoachEmail) ? 'mailto:' + player.clubCoachEmail : '', '')}
                        ${actionLink('fa-phone-flip', 'Call Coach', coachPhone ? 'tel:' + coachPhone : '', '')}
                        ${actionLink('fa-arrow-up-right-from-square', 'Website', clean(player.websiteUrl), '')}
                    </div>
                `;
            }

            function renderPlayer(card) {
                const player = playerFromCard(card);
                return renderMobileCard(player) + renderActions(player);
            }

            function openPlayer(index) {
                const card = cards[index];
                if (!card) return;

                activeIndex = index;
                cards.forEach((item) => item.classList.toggle('is-active', item === card));

                const player = playerFromCard(card);
                title.textContent = player.name || 'Player Card';
                dialog.innerHTML = renderPlayer(card);
                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closePlayer() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                dialog.innerHTML = '';
                cards.forEach((item) => item.classList.remove('is-active'));
                document.body.style.overflow = '';
            }

            function nextPlayer() {
                if (!cards.length) return;
                openPlayer((activeIndex + 1) % cards.length);
            }

            function prevPlayer() {
                if (!cards.length) return;
                openPlayer((activeIndex - 1 + cards.length) % cards.length);
            }

            cards.forEach((card, index) => {
                card.addEventListener('click', () => openPlayer(index));
            });

            closeBtn?.addEventListener('click', closePlayer);
            nextBtn?.addEventListener('click', nextPlayer);
            prevBtn?.addEventListener('click', prevPlayer);

            overlay?.addEventListener('click', (event) => {
                if (event.target === overlay) closePlayer();
            });

            dialog?.addEventListener('click', async function (event) {
                const button = event.target.closest('[data-save-player]');
                if (!button || button.disabled) return;

                button.disabled = true;
                const original = button.innerHTML;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving';

                try {
                    const playerId = button.getAttribute('data-save-player');
                    const saveUrl = saveUrlTemplate.replace('__PLAYER_ID__', encodeURIComponent(playerId));

                    const response = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ player_id: button.getAttribute('data-save-player') }),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok || payload.success === false) {
                        throw new Error(payload.message || 'Unable to save player.');
                    }

                    button.classList.add('is-saved');
                    button.innerHTML = '<i class="fa-solid fa-check"></i> Saved';
                    const activeCard = cards[activeIndex];
                    const activePlayer = playerFromCard(activeCard);
                    activePlayer.isSaved = true;
                    activeCard.setAttribute('data-player', JSON.stringify(activePlayer));
                } catch (error) {
                    button.disabled = false;
                    button.innerHTML = original;
                    alert(error.message || 'Unable to save player.');
                }
            });

            document.addEventListener('keydown', function (event) {
                if (!overlay.classList.contains('is-open')) return;
                if (event.key === 'Escape') closePlayer();
                if (event.key === 'ArrowRight') nextPlayer();
                if (event.key === 'ArrowLeft') prevPlayer();
            });
        });
    </script>
</body>
</html>