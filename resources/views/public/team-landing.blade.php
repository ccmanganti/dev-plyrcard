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

        $primaryLum = $luminance($primary);
        $secondaryLum = $luminance($secondary);
        $readablePrimary = $primaryLum < 0.28
            ? $mixHex($primary, '#FFFFFF', 0.58)
            : ($primaryLum > 0.72 ? $mixHex($primary, '#000000', 0.38) : $primary);
        $readableSecondary = $secondaryLum < 0.28
            ? $mixHex($secondary, '#FFFFFF', 0.55)
            : ($secondaryLum > 0.72 ? $mixHex($secondary, '#000000', 0.38) : $secondary);
        $onPrimary = $primaryLum > 0.58 ? '#071018' : '#FFFFFF';
        $softOnDark = '#F4F7FB';
        $mutedOnDark = '#D7DCE4';

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

                $playerCardImage = $isPremium ? $resolveAsset($player->plyrcard_image ?: null) : null;
                $portraitImage = $resolveAsset($player->player_image ?: $player->action_image ?: $player->youtube_thumbnail ?: null);
                $listImage = $playerCardImage ?: $portraitImage;
                $listImageType = $playerCardImage ? 'card' : ($portraitImage ? 'portrait' : 'placeholder');

                $mobileHeroImageUrl = $resolveAsset($player->mobile_hero_image ?: null);
                $playerImageUrl = $resolveAsset($player->player_image ?: $player->action_image ?: $player->plyrcard_image ?: null);
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
                    'listImageType' => $listImageType,
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
                    'birth' => $player->birth ? strtoupper(Carbon::parse($player->birth)->format('F j, Y')) : '',
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
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|iceberg:400|inter:400,500,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Luxurious+Script&display=swap');

        :root{
            --team-primary: {{ $primary }};
            --team-secondary: {{ $secondary }};
            --team-accent: {{ $accent }};
            --team-readable-primary: {{ $readablePrimary }};
            --team-readable-secondary: {{ $readableSecondary }};
            --team-on-primary: {{ $onPrimary }};
            --team-soft-text: {{ $softOnDark }};
            --team-muted-text: {{ $mutedOnDark }};
            --team-heading: "{{ $headingFont }}", "Antonio", sans-serif;
            --team-body: "{{ $bodyFont }}", "Inter", sans-serif;
            --team-bg: #050506;
            --team-surface: #111114;
            --team-line: rgba(255,255,255,.10);
            --team-muted: rgba(255,255,255,.72);
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
                radial-gradient(circle at 12% 12%, color-mix(in srgb, var(--team-primary) 58%, transparent), transparent 30%),
                linear-gradient(90deg, color-mix(in srgb, var(--team-primary) 82%, rgba(0,0,0,.72)) 0%, rgba(0,0,0,.72) 48%, color-mix(in srgb, var(--team-secondary) 76%, rgba(0,0,0,.70)) 100%),
                linear-gradient(180deg, rgba(0,0,0,.16), rgba(0,0,0,.86));
            opacity:.94;
        }

        .team-hero::after{
            content:"";
            position:absolute;
            inset:0;
            z-index:2;
            background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(0,0,0,.70));
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
            color:var(--team-soft-text);
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

        .team-meta i{ color:var(--team-readable-primary); width:18px; text-align:center; }

        .team-meta span{
            display:block;
            color:var(--team-muted-text);
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
            color:var(--team-muted-text);
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
            color:var(--team-muted-text);
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
            border-left-color:var(--team-readable-primary);
            background:
                linear-gradient(90deg, color-mix(in srgb, var(--team-primary) 24%, transparent), transparent 48%),
                rgba(255,255,255,.09);
        }

        .player-media{
            width:64px;
            height:64px;
            border-radius:999px;
            overflow:hidden;
            display:flex;
            align-items:center;
            justify-content:center;
            background:rgba(0,0,0,.34);
            flex:0 0 auto;
            border:0;
            box-shadow:none;
        }

        .player-media.is-card{
            height:72px;
            border-radius:10px;
        }

        .player-media.is-portrait{
            border-radius:999px;
        }

        .player-media img{
            width:100%;
            height:100%;
            object-fit:cover;
            border:0;
            box-shadow:none;
        }

        .player-free-shape{
            width:54px;
            height:54px;
            border-radius:999px;
            background:
                radial-gradient(circle at 50% 36%, rgba(255,255,255,.96) 0 18%, transparent 19%),
                radial-gradient(circle at 50% 84%, rgba(255,255,255,.90) 0 30%, transparent 31%),
                color-mix(in srgb, var(--team-primary) 42%, #171717);
            box-shadow:none;
            border:0;
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
            color:var(--team-muted-text);
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
            color:var(--team-readable-primary);
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
            height:100%;
            background:#030304;
            overflow:hidden;
            transform:translateX(100%);
            animation:panelIn .22s ease forwards;
        }

        @keyframes panelIn{ to{ transform:translateX(0); } }

        .player-panel-bar{
            position:relative;
            z-index:100;
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
            z-index:90;
            width:42px;
            height:56px;
            border:0;
            border-radius:12px;
            background:rgba(0,0,0,.76);
            color:#fff;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            box-shadow:0 12px 28px rgba(0,0,0,.38);
            backdrop-filter:blur(12px);
            transform:translateY(-50%);
            transition:transform .18s ease, background .18s ease, opacity .18s ease;
        }

        .player-nav-arrow:hover{
            background:var(--team-primary);
            color:var(--team-text-on-primary);
            transform:translateY(-50%) scale(1.06);
        }

        .player-nav-arrow.is-left{ left:10px; }
        .player-nav-arrow.is-right{ right:10px; }

        /*
        |--------------------------------------------------------------------------
        | Player Website Mobile Card
        |--------------------------------------------------------------------------
        | These styles intentionally mirror the dynamic mobile hero template used on
        | player websites: 390 x 680 design frame, Antonio / Iceberg / Luxurious
        | Script fonts, same image staging, info cards, logo rows, and spacing.
        */
        .player-dialog{
            position:relative;
            z-index:1;
            height:calc(100% - 54px);
            overflow:auto;
            padding:0 4px 28px;
            background:#030304;
        }

        .mobile-card{
            position:relative;
            z-index:2;
            width:390px;
            max-width:100%;
            aspect-ratio:390 / 680;
            min-height:auto;
            margin:0 auto;
            overflow:hidden;
            background:var(--team-primary);
            color:#fff;
            --mobile-card-heading:"Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            --mobile-card-number:"Iceberg", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        }

        .mobile-card-override{
            width:390px;
            max-width:100%;
            margin:0 auto;
            background:var(--team-primary);
            overflow:hidden;
        }

        .mobile-card-override img{
            display:block;
            width:100%;
            height:auto;
            object-fit:cover;
        }

        .mobile-bg-number{
            position:absolute;
            left:4px;
            top:170px;
            z-index:1;
            letter-spacing:-18px;
            font-family:var(--mobile-card-number);
            font-size:250px;
            line-height:.8;
            color:rgba(255,255,255,.10);
            pointer-events:none;
            user-select:none;
        }

        .mobile-top{
            position:relative;
            z-index:4;
            padding:12px 16px 0;
        }

        .mobile-logo-row{
            display:flex;
            align-items:center;
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

        .mobile-name-box{
            margin-top:-30px;
            width:100%;
            position:relative;
            z-index:5;
        }

        .mobile-jersey,
        .mobile-first,
        .mobile-last,
        .mobile-position{
            font-family:var(--mobile-card-heading);
            text-transform:uppercase;
            color:#fff;
        }

        .mobile-jersey{
            font-size:38px;
            line-height:.9;
            font-weight:700;
            letter-spacing:-.04em;
            margin-bottom:2px;
        }

        .mobile-first{
            font-size:45px;
            line-height:1;
            font-weight:800;
            letter-spacing:-.05em;
        }

        .mobile-last{
            font-size:56px;
            line-height:.86;
            font-weight:800;
            letter-spacing:-.05em;
            margin-top:2px;
        }

        .mobile-position{
            margin-top:12px;
            font-size:22px;
            line-height:.95;
            font-weight:700;
        }

        .mobile-signature{
            position:absolute;
            left:8px;
            top:180px;
            z-index:1;
            font-size:110px;
            line-height:1;
            color:rgba(255,255,255,.14);
            font-family:"Luxurious Script", cursive;
            transform:rotate(-7deg);
            pointer-events:none;
            user-select:none;
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
            overflow:visible;
            pointer-events:none;
        }

        .mobile-player-stage img{
            width:auto;
            height:100%;
            max-width:none;
            display:block;
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
            align-items:stretch;
        }

        .mobile-stat-card{
            min-height:240px;
            background:#f1f1f1;
            color:#111;
            border-radius:8px;
            padding:10px 10px 12px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
            overflow:hidden;
            position:relative;
            display:flex;
            flex-direction:column;
            justify-content:flex-start;
        }

        .mobile-big-row{
            display:flex;
            align-items:flex-end;
            gap:6px;
            margin-bottom:12px;
            line-height:.8;
            flex-wrap:nowrap;
        }

        .mobile-big-value{
            font-family:var(--mobile-card-heading);
            font-size:76px;
            line-height:.8;
            font-weight:900;
            letter-spacing:-.05em;
            color:#000;
            flex:0 1 auto;
            min-width:0;
        }

        .mobile-big-label{
            font-family:var(--mobile-card-heading);
            font-size:24px;
            line-height:.9;
            font-weight:900;
            text-transform:uppercase;
            color:#000;
            padding-bottom:9px;
            white-space:nowrap;
            flex:0 0 auto;
        }

        .mobile-org-list{
            display:grid;
            gap:12px;
        }

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
            font-family:var(--mobile-card-heading);
            font-size:18px;
            line-height:.95;
            font-weight:900;
            text-transform:uppercase;
            color:#111;
        }

        .mobile-org-value{
            margin-top:2px;
            font-family:var(--mobile-card-heading);
            font-size:12px;
            line-height:1.05;
            font-weight:500;
            color:#111;
            text-transform:none;
        }

        .mobile-class-row{
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:4px;
            flex-wrap:nowrap;
            margin-bottom:14px;
            min-width:0;
        }

        .mobile-class-year{
            font-family:var(--mobile-card-heading);
            font-size:64px;
            line-height:.8;
            font-weight:900;
            letter-spacing:-.045em;
            color:#000;
            flex:0 1 auto;
            min-width:0;
        }

        .mobile-class-label{
            font-family:var(--mobile-card-heading);
            font-size:24px;
            line-height:.9;
            font-weight:900;
            text-transform:uppercase;
            color:#000;
            padding-bottom:8px;
            white-space:nowrap;
            flex:0 0 auto;
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

        .mobile-meta-label{
            font-family:var(--mobile-card-heading);
            font-size:15px;
            line-height:1;
            font-weight:500;
            text-transform:uppercase;
            color:#111;
        }

        .mobile-meta-value{
            font-family:var(--mobile-card-heading);
            font-size:15px;
            line-height:1;
            font-weight:800;
            text-transform:uppercase;
            color:#111;
            text-align:right;
        }

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
            color:var(--team-muted-text);
            cursor:default;
        }


        .coach-open-btn{
            min-height:42px;
            border:0;
            padding:0 14px;
            margin:12px 10px 0;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            background:linear-gradient(135deg, var(--team-primary), var(--team-secondary));
            color:#fff;
            font-family:var(--team-heading);
            font-size:12px;
            letter-spacing:.10em;
            text-transform:uppercase;
            font-weight:900;
            cursor:pointer;
        }

        .coach-modal{
            position:fixed;
            inset:0;
            z-index:1200;
            display:none;
            align-items:center;
            justify-content:center;
            padding:18px;
            background:rgba(0,0,0,.74);
            backdrop-filter:blur(12px);
        }

        .coach-modal.is-open{ display:flex; }

        .coach-modal-card{
            width:min(430px, 100%);
            background:#070708;
            color:#fff;
            box-shadow:0 24px 60px rgba(0,0,0,.48);
            overflow:hidden;
        }

        .coach-modal-head{
            min-height:54px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding:0 14px;
            background:linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 34%, #000), #050506);
        }

        .coach-modal-title{
            font-family:var(--team-heading);
            font-size:22px;
            line-height:1;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
        }

        .coach-close-btn{
            border:0;
            background:rgba(255,255,255,.10);
            color:#fff;
            min-height:34px;
            border-radius:999px;
            padding:0 12px;
            font-family:var(--team-heading);
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
            cursor:pointer;
        }

        .coach-modal-body{ padding:16px; }

        .coach-modal-copy{
            margin:0 0 14px;
            color:var(--team-muted-text);
            font-size:13px;
            line-height:1.45;
            font-weight:650;
        }

        .coach-form{ display:grid; gap:10px; }
        .coach-field{ display:grid; gap:5px; }
        .coach-field label{ color:var(--team-muted-text); font-size:9px; text-transform:uppercase; letter-spacing:.12em; font-weight:900; }
        .coach-field input{ width:100%; height:44px; border:0; outline:0; padding:0 12px; background:rgba(255,255,255,.10); color:#fff; font-weight:750; }
        .coach-field input::placeholder{ color:rgba(255,255,255,.38); }
        .coach-submit{ min-height:44px; border:0; background:linear-gradient(135deg, var(--team-primary), var(--team-secondary)); color:#fff; font-family:var(--team-heading); font-size:13px; letter-spacing:.10em; text-transform:uppercase; font-weight:900; cursor:pointer; }

        .empty-squad{
            padding:22px;
            color:var(--team-muted-text);
            background:rgba(255,255,255,.06);
            font-weight:800;
            text-align:center;
        }

        @media (max-width:520px){
            .team-app{ width:100%; }
            .player-panel{ width:100%; }
        }
    

        /*
        |--------------------------------------------------------------------------
        | Compact mobile-app tuning
        |--------------------------------------------------------------------------
        */
        :root{ --app-width:390px; }
        .team-top{ height:44px; grid-template-columns:40px 1fr 40px; }
        .team-top-btn{ width:32px; height:32px; }
        .team-top-title{ font-size:18px; letter-spacing:.085em; }
        .team-hero{ min-height:214px; }
        .team-hero-inner{ padding:14px 14px 16px; }
        .team-logo{ width:48px; height:48px; }
        .team-kicker{ font-size:9px; letter-spacing:.14em; }
        .team-name{ font-size:26px; line-height:.94; letter-spacing:.045em; }
        .team-subtitle{ font-size:10px; letter-spacing:.12em; }
        .team-meta-strip{ gap:6px; padding:8px 10px; }
        .team-meta{ min-height:48px; padding:8px; gap:7px; }
        .team-meta i{ font-size:13px; width:20px; }
        .team-meta span{ font-size:7px; letter-spacing:.10em; }
        .team-meta strong{ font-size:10.5px; line-height:1.05; }
        .coach-session-bar{ margin:8px 10px 0; padding:8px 9px; }
        .coach-session-bar strong{ font-size:10px; }
        .coach-session-bar span{ font-size:9px; }
        .coach-open-btn{ min-height:36px; margin:9px 10px 0; padding:0 11px; gap:6px; font-size:10.5px; letter-spacing:.09em; }
        .squad-section{ padding:10px; }
        .squad-head{ margin-bottom:8px; }
        .squad-title{ font-size:21px; letter-spacing:.075em; }
        .squad-sort{ font-size:9px; }
        .player-list{ gap:6px; }
        .player-row{ min-height:66px; padding:7px 8px; grid-template-columns:54px 1fr auto; gap:9px; }
        .player-media{ width:50px; height:50px; border-radius:999px; }
        .player-media.is-card{ width:44px; height:58px; border-radius:5px; }
        .player-row-name{ font-size:15px; letter-spacing:.045em; }
        .player-row-meta{ font-size:9px; gap:5px 7px; }
        .player-number-pill{ min-width:36px; height:30px; font-size:15px; }
        .player-panel-bar{ height:48px; padding:0 9px; }
        .player-panel-title{ font-size:17px; letter-spacing:.075em; }
        .player-panel-btn{ min-height:31px; padding:0 10px; font-size:10.5px; }
        .player-nav-arrow{ z-index:80; width:38px; height:50px; }
        .player-nav-arrow.is-left{ left:6px; }
        .player-nav-arrow.is-right{ right:6px; }
        .player-dialog{ height:calc(100% - 48px); padding:0 4px 24px; }
        .mobile-card{ width:360px; aspect-ratio:390 / 680; }
        .mobile-card-override{ width:360px; }
        .mobile-bg-number{ top:150px; font-size:218px; letter-spacing:-15px; }
        .mobile-top{ padding:10px 14px 0; }
        .mobile-logo-row{ min-height:34px; }
        .mobile-logo-row img{ max-height:33px; }
        .mobile-head{ margin-top:16px; min-height:392px; }
        .mobile-name-box{ margin-top:-24px; }
        .mobile-jersey{ font-size:33px; }
        .mobile-first{ font-size:39px; }
        .mobile-last{ font-size:49px; }
        .mobile-position{ margin-top:9px; font-size:19px; }
        .mobile-signature{ top:160px; font-size:92px; }
        .mobile-player-stage{ height:392px; right:-9px; }
        .mobile-info-grid{ left:6px; right:6px; bottom:62px; gap:6px; }
        .mobile-stat-card{ min-height:210px; padding:9px 9px 10px; border-radius:7px; }
        .mobile-big-row{ gap:5px; margin-bottom:10px; }
        .mobile-big-value{ font-size:64px; }
        .mobile-big-label{ font-size:20px; padding-bottom:7px; }
        .mobile-org-list{ gap:9px; }
        .mobile-org-row{ grid-template-columns:34px 1fr; gap:8px; }
        .mobile-org-row img,.mobile-org-fallback{ width:34px; height:34px; }
        .mobile-org-title{ font-size:15px; }
        .mobile-org-value{ font-size:10.5px; }
        .mobile-class-row{ margin-bottom:11px; }
        .mobile-class-year{ font-size:54px; }
        .mobile-class-label{ font-size:20px; padding-bottom:6px; }
        .mobile-meta{ gap:8px; }
        .mobile-meta-row{ gap:7px; }
        .mobile-meta-label,.mobile-meta-value{ font-size:12.5px; }
        .player-actions{ width:min(360px, 100%); gap:6px; margin-top:8px; }
        .player-action{ min-height:36px; font-size:10.5px; }
        .coach-modal-head{ min-height:48px; padding:0 12px; }
        .coach-modal-title{ font-size:18px; }
        .coach-modal-body{ padding:14px; }
        .coach-modal-copy{ font-size:12px; }
        .coach-field input{ height:40px; }
        .coach-submit{ min-height:40px; font-size:11.5px; }
        @media (max-width:380px){
            .mobile-card,.mobile-card-override{ width:340px; }
            .mobile-big-value{ font-size:58px; }
            .mobile-class-year{ font-size:48px; }
            .mobile-meta-label,.mobile-meta-value{ font-size:11.5px; }
        }

    

        /*
        |--------------------------------------------------------------------------
        | PLYRCard app polish pass
        |--------------------------------------------------------------------------
        */
        body{
            background:#030304 !important;
        }

        .team-page{
            background:
                radial-gradient(circle at 50% -8%, color-mix(in srgb, var(--team-readable-primary) 18%, transparent), transparent 34%),
                #030304 !important;
            padding:0 !important;
        }

        .team-app{
            width:min(430px, 100%) !important;
            min-height:100vh !important;
            margin:0 auto !important;
            background:#050506 !important;
            box-shadow:0 0 0 1px rgba(255,255,255,.04) !important;
            border:0 !important;
        }

        .team-content{
            padding:10px 10px 22px !important;
        }

        .team-top{
            height:42px !important;
            grid-template-columns:36px 1fr 36px !important;
            margin-bottom:8px !important;
        }

        .team-top-title{
            font-size:17px !important;
            letter-spacing:.10em !important;
        }

        .team-top-btn{
            width:34px !important;
            height:34px !important;
            border-radius:12px !important;
            background:#111216 !important;
        }

        .team-hero{
            min-height:188px !important;
            border-radius:22px !important;
            overflow:hidden !important;
            background:#111216 !important;
        }

        .team-hero::before{
            background:
                radial-gradient(circle at 14% 6%, color-mix(in srgb, var(--team-primary) 56%, transparent), transparent 32%),
                radial-gradient(circle at 88% 0%, color-mix(in srgb, var(--team-secondary) 46%, transparent), transparent 34%),
                linear-gradient(100deg, color-mix(in srgb, var(--team-primary) 58%, rgba(0,0,0,.90)) 0%, rgba(0,0,0,.72) 55%, color-mix(in srgb, var(--team-secondary) 58%, rgba(0,0,0,.92)) 100%) !important;
            opacity:.98 !important;
        }

        .team-hero::after{
            background:linear-gradient(180deg, rgba(255,255,255,.045), rgba(0,0,0,.76)) !important;
        }

        .team-hero-inner{
            padding:14px !important;
            min-height:188px !important;
            display:flex !important;
            align-items:flex-end !important;
        }

        .team-brand-row{
            grid-template-columns:48px 1fr !important;
            gap:10px !important;
            align-items:end !important;
        }

        .team-logo{
            width:48px !important;
            height:48px !important;
            object-fit:contain !important;
        }

        .team-kicker{
            font-size:8px !important;
            letter-spacing:.18em !important;
            color:var(--team-readable-primary) !important;
        }

        .team-name{
            font-size:24px !important;
            line-height:.92 !important;
            letter-spacing:.055em !important;
        }

        .team-subtitle{
            margin-top:5px !important;
            font-size:10px !important;
            line-height:1.15 !important;
            color:rgba(255,255,255,.72) !important;
        }

        .team-meta-strip{
            grid-template-columns:1fr 1fr !important;
            gap:8px !important;
            margin:9px 0 !important;
        }

        .team-meta{
            min-height:58px !important;
            padding:9px !important;
            border-radius:16px !important;
            background:#111216 !important;
            border:1px solid rgba(255,255,255,.07) !important;
        }

        .team-meta i{
            font-size:13px !important;
            color:var(--team-readable-primary) !important;
        }

        .team-meta span{
            font-size:7px !important;
            letter-spacing:.13em !important;
            color:rgba(255,255,255,.55) !important;
        }

        .team-meta strong{
            font-size:11px !important;
            line-height:1.1 !important;
            color:#fff !important;
        }

        .coach-session-bar{
            border-radius:16px !important;
            min-height:48px !important;
            padding:9px 10px !important;
            background:#111216 !important;
            border:1px solid rgba(255,255,255,.07) !important;
        }

        .coach-session-bar strong{
            font-size:10px !important;
        }

        .coach-session-bar span{
            font-size:9px !important;
        }

        .coach-open-btn{
            min-height:36px !important;
            padding:0 12px !important;
            border-radius:12px !important;
            font-size:10px !important;
            letter-spacing:.09em !important;
            background:linear-gradient(135deg, color-mix(in srgb, var(--team-primary) 68%, #111), color-mix(in srgb, var(--team-secondary) 68%, #111)) !important;
            color:#fff !important;
            border:1px solid rgba(255,255,255,.10) !important;
        }

        .squad-section{
            padding:12px 0 0 !important;
        }

        .squad-head{
            align-items:center !important;
            margin:0 2px 9px !important;
        }

        .squad-title{
            font-size:22px !important;
            letter-spacing:.08em !important;
        }

        .squad-sort{
            font-size:8px !important;
            letter-spacing:.11em !important;
            color:rgba(255,255,255,.54) !important;
        }

        .player-list{
            gap:7px !important;
        }

        .player-row{
            min-height:64px !important;
            grid-template-columns:52px minmax(0, 1fr) auto !important;
            gap:9px !important;
            padding:7px 8px !important;
            border-radius:17px !important;
            background:#111216 !important;
            border:1px solid rgba(255,255,255,.07) !important;
            border-left:0 !important;
        }

        .player-row:hover,
        .player-row.is-active{
            transform:translateY(-1px) !important;
            background:
                linear-gradient(90deg, color-mix(in srgb, var(--team-primary) 16%, transparent), transparent 54%),
                #15161b !important;
            border-color:color-mix(in srgb, var(--team-readable-primary) 42%, rgba(255,255,255,.10)) !important;
        }

        .player-media{
            width:48px !important;
            height:48px !important;
            border:0 !important;
            box-shadow:none !important;
            background:#1c1d22 !important;
        }

        .player-media.is-card{
            width:48px !important;
            height:56px !important;
            border-radius:10px !important;
        }

        .player-media.is-portrait{
            border-radius:999px !important;
        }

        .player-media img{
            border:0 !important;
            box-shadow:none !important;
        }

        .player-free-shape{
            width:42px !important;
            height:42px !important;
            border:0 !important;
            box-shadow:none !important;
        }

        .player-row-name{
            font-size:14px !important;
            line-height:1 !important;
            letter-spacing:.055em !important;
        }

        .player-row-meta{
            margin-top:5px !important;
            gap:4px 7px !important;
            font-size:8px !important;
            color:rgba(255,255,255,.58) !important;
        }

        .player-number-pill{
            min-width:38px !important;
            height:30px !important;
            padding:0 7px !important;
            border-radius:11px !important;
            font-size:13px !important;
            background:color-mix(in srgb, var(--team-readable-primary) 16%, #14151a) !important;
            color:#fff !important;
        }

        .player-panel{
            width:min(430px, 100%) !important;
            background:#050506 !important;
        }

        .player-panel-bar{
            height:52px !important;
            padding:0 10px !important;
            background:#050506 !important;
            border-bottom:1px solid rgba(255,255,255,.08) !important;
        }

        .player-panel-title{
            font-size:16px !important;
            letter-spacing:.10em !important;
        }

        .player-panel-btn{
            min-height:34px !important;
            border-radius:12px !important;
            font-size:10px !important;
            background:#15161a !important;
        }

        .player-nav-arrow{
            z-index:120 !important;
            width:38px !important;
            height:54px !important;
            border-radius:13px !important;
            background:rgba(0,0,0,.72) !important;
        }

        .player-nav-arrow.is-left{ left:6px !important; }
        .player-nav-arrow.is-right{ right:6px !important; }

        .coach-modal-card{
            width:min(390px, calc(100% - 24px)) !important;
            border-radius:22px !important;
            background:#08090b !important;
        }

        .coach-modal-head{
            min-height:48px !important;
            border-bottom:1px solid rgba(255,255,255,.08) !important;
        }

        .coach-modal-title{
            font-size:17px !important;
            letter-spacing:.08em !important;
        }

        .coach-field label{
            font-size:8px !important;
            letter-spacing:.14em !important;
        }

        .coach-field input{
            height:38px !important;
            border-radius:12px !important;
            font-size:12px !important;
        }

        .coach-submit{
            min-height:38px !important;
            border-radius:12px !important;
            font-size:10px !important;
        }

        @media (max-width:520px){
            .team-app{ width:100% !important; }
            .team-content{ padding:8px 8px 20px !important; }
            .team-hero{ border-radius:20px !important; }
            .team-name{ font-size:22px !important; }
            .player-row{ min-height:62px !important; }
        }


        /*
        |--------------------------------------------------------------------------
        | Clean PLYRCard website pass - no bubbles, no cardy backgrounds
        |--------------------------------------------------------------------------
        | Keeps navigation easy on mobile while removing rounded bubble UI.
        */
        body{ background:#030303 !important; }

        .team-page{
            background:#030303 !important;
            padding:0 !important;
        }

        .team-app{
            width:100% !important;
            max-width:none !important;
            margin:0 !important;
            min-height:100vh !important;
            background:#050505 !important;
            box-shadow:none !important;
            border:0 !important;
        }

        .team-top{
            height:56px !important;
            width:min(1180px, calc(100% - 32px)) !important;
            margin:0 auto !important;
            padding:0 !important;
            background:#050505 !important;
            border-bottom:1px solid rgba(255,255,255,.10) !important;
            backdrop-filter:none !important;
        }

        .team-top-btn{
            width:40px !important;
            height:40px !important;
            border-radius:0 !important;
            background:transparent !important;
            border:1px solid rgba(255,255,255,.16) !important;
        }

        .team-top-title{
            font-size:18px !important;
            letter-spacing:.12em !important;
        }

        .team-content{
            width:min(1180px, calc(100% - 32px)) !important;
            margin:0 auto !important;
            padding:0 0 48px !important;
        }

        .team-hero{
            min-height:470px !important;
            border-radius:0 !important;
            background:#050505 !important;
            border:0 !important;
            border-bottom:1px solid rgba(255,255,255,.12) !important;
            overflow:hidden !important;
        }

        .team-hero::before{
            background:
                linear-gradient(100deg,
                    color-mix(in srgb, var(--team-primary) 70%, rgba(0,0,0,.84)) 0%,
                    rgba(0,0,0,.72) 48%,
                    color-mix(in srgb, var(--team-secondary) 62%, rgba(0,0,0,.86)) 100%) !important;
            opacity:.92 !important;
        }

        .team-hero::after{
            background:
                linear-gradient(180deg, rgba(0,0,0,.12) 0%, rgba(0,0,0,.32) 46%, rgba(0,0,0,.88) 100%),
                linear-gradient(90deg, rgba(255,255,255,.05), transparent 48%) !important;
        }

        .team-hero-inner{
            min-height:470px !important;
            padding:clamp(24px, 4vw, 46px) !important;
            justify-content:flex-end !important;
        }

        .team-brand-row{
            gap:14px !important;
            margin-bottom:22px !important;
        }

        .team-logo{
            width:clamp(56px, 7vw, 92px) !important;
            height:clamp(56px, 7vw, 92px) !important;
        }

        .team-kicker{
            font-size:10px !important;
            letter-spacing:.20em !important;
            color:var(--team-readable-primary) !important;
        }

        .team-name{
            font-size:clamp(42px, 7vw, 84px) !important;
            line-height:.88 !important;
            letter-spacing:.015em !important;
        }

        .team-subtitle{
            max-width:620px !important;
            margin-top:14px !important;
            font-size:clamp(12px, 1.15vw, 15px) !important;
            line-height:1.55 !important;
            color:rgba(255,255,255,.80) !important;
        }

        .team-meta-strip{
            width:100% !important;
            display:grid !important;
            grid-template-columns:repeat(2, minmax(0, 1fr)) !important;
            gap:0 !important;
            background:#050505 !important;
            border-bottom:1px solid rgba(255,255,255,.10) !important;
        }

        .team-meta{
            min-height:92px !important;
            padding:18px !important;
            border-radius:0 !important;
            background:#050505 !important;
            border:0 !important;
            border-right:1px solid rgba(255,255,255,.10) !important;
        }

        .team-meta:last-child{ border-right:0 !important; }

        .team-meta i{ color:var(--team-readable-primary) !important; }
        .team-meta span{ font-size:8px !important; letter-spacing:.18em !important; color:rgba(255,255,255,.54) !important; }
        .team-meta strong{ font-size:clamp(13px, 1.6vw, 18px) !important; color:#fff !important; }

        .coach-session-bar{
            margin:18px 0 0 !important;
            min-height:52px !important;
            border-radius:0 !important;
            background:#050505 !important;
            border:1px solid rgba(255,255,255,.12) !important;
            border-left:3px solid var(--team-primary) !important;
        }

        .coach-open-btn,
        .coach-submit,
        .coach-close-btn,
        .player-panel-btn,
        .player-nav-arrow{
            border-radius:0 !important;
            box-shadow:none !important;
        }

        .coach-open-btn{
            background:var(--team-primary) !important;
            color:var(--team-on-primary) !important;
            border:1px solid var(--team-primary) !important;
        }

        .squad-section{
            padding:34px 0 48px !important;
        }

        .squad-head{
            margin:0 0 18px !important;
            padding:0 !important;
            align-items:flex-end !important;
        }

        .squad-title{
            font-size:clamp(30px, 4.4vw, 52px) !important;
            letter-spacing:.05em !important;
        }

        .squad-sort{
            font-size:9px !important;
            letter-spacing:.16em !important;
        }

        .player-list{
            display:grid !important;
            grid-template-columns:repeat(2, minmax(0, 1fr)) !important;
            gap:0 !important;
            border-top:1px solid rgba(255,255,255,.10) !important;
        }

        .player-row{
            min-height:92px !important;
            grid-template-columns:64px minmax(0, 1fr) auto !important;
            gap:14px !important;
            padding:14px 16px !important;
            border-radius:0 !important;
            background:#050505 !important;
            border:0 !important;
            border-right:1px solid rgba(255,255,255,.08) !important;
            border-bottom:1px solid rgba(255,255,255,.10) !important;
            border-left:0 !important;
        }

        .player-row:nth-child(2n){ border-right:0 !important; }

        .player-row:hover,
        .player-row.is-active{
            transform:none !important;
            background:
                linear-gradient(90deg, color-mix(in srgb, var(--team-primary) 18%, transparent), transparent 54%),
                #080808 !important;
            border-color:rgba(255,255,255,.14) !important;
        }

        .player-media{
            width:58px !important;
            height:58px !important;
            border-radius:0 !important;
            background:#101010 !important;
            border:0 !important;
            box-shadow:none !important;
        }

        .player-media.is-card{
            width:48px !important;
            height:64px !important;
            border-radius:0 !important;
        }

        .player-media.is-portrait{
            border-radius:999px !important;
            background:transparent !important;
        }

        .player-media img{
            border:0 !important;
            box-shadow:none !important;
        }

        .player-free-shape{
            border:0 !important;
            box-shadow:none !important;
        }

        .player-row-name{
            font-size:clamp(16px, 2.1vw, 22px) !important;
            letter-spacing:.04em !important;
        }

        .player-row-meta{
            font-size:10px !important;
            color:rgba(255,255,255,.60) !important;
        }

        .player-number-pill{
            min-width:40px !important;
            height:auto !important;
            border-radius:0 !important;
            padding:0 !important;
            background:transparent !important;
            color:var(--team-readable-primary) !important;
            font-size:clamp(20px, 3vw, 32px) !important;
        }

        .player-panel{
            width:min(470px, 100%) !important;
            background:#050505 !important;
        }

        .player-panel-bar{
            background:#050505 !important;
            border-bottom:1px solid rgba(255,255,255,.10) !important;
        }

        .player-nav-arrow{
            z-index:140 !important;
            background:rgba(0,0,0,.78) !important;
            border:1px solid rgba(255,255,255,.16) !important;
        }

        .coach-modal-card{
            border-radius:0 !important;
            background:#060606 !important;
            border:1px solid rgba(255,255,255,.14) !important;
            box-shadow:0 24px 80px rgba(0,0,0,.55) !important;
        }

        .coach-modal-head{
            background:#080808 !important;
            border-bottom:1px solid rgba(255,255,255,.12) !important;
        }

        .coach-field input{
            border-radius:0 !important;
            background:#0d0d0d !important;
            border:1px solid rgba(255,255,255,.14) !important;
        }

        @media (max-width:760px){
            .team-top,
            .team-content{
                width:100% !important;
            }

            .team-top{
                padding:0 12px !important;
            }

            .team-content{
                padding:0 0 28px !important;
            }

            .team-hero{
                min-height:430px !important;
            }

            .team-hero-inner{
                min-height:430px !important;
                padding:22px 16px !important;
            }

            .team-name{ font-size:42px !important; }
            .team-subtitle{ font-size:12px !important; max-width:94% !important; }

            .team-meta-strip{
                grid-template-columns:repeat(2, minmax(0, 1fr)) !important;
            }

            .team-meta{
                min-height:74px !important;
                padding:12px !important;
            }

            .squad-section{
                padding:28px 14px 32px !important;
            }

            .squad-head{
                flex-direction:column !important;
                align-items:flex-start !important;
                gap:4px !important;
            }

            .player-list{
                grid-template-columns:1fr !important;
            }

            .player-row{
                min-height:74px !important;
                grid-template-columns:54px minmax(0, 1fr) auto !important;
                padding:10px 0 !important;
                gap:10px !important;
                border-right:0 !important;
            }

            .player-media{
                width:48px !important;
                height:48px !important;
            }

            .player-media.is-card{
                width:42px !important;
                height:56px !important;
            }

            .player-row-name{ font-size:16px !important; }
            .player-row-meta{ font-size:9px !important; }
            .player-number-pill{ font-size:20px !important; }
        }

/* --------------------------------------------------------------------------
   Clean website/app layout correction - no bubble UI
-------------------------------------------------------------------------- */
.team-page{ background:#050506 !important; }
.team-hero{
    min-height:clamp(430px, 62vh, 620px) !important;
    padding:clamp(20px,4vw,50px) !important;
}
.team-hero::before{
    background:
        linear-gradient(90deg,
            color-mix(in srgb, var(--team-primary) 30%, rgba(0,0,0,.90)) 0%,
            rgba(0,0,0,.76) 46%,
            color-mix(in srgb, var(--team-secondary) 24%, rgba(0,0,0,.88)) 100%),
        linear-gradient(180deg, rgba(0,0,0,.18), rgba(0,0,0,.90)) !important;
}
.team-hero-inner{ width:min(1060px,100%) !important; }
.team-topline{ font-size:10px !important; letter-spacing:.18em !important; color:var(--team-readable-primary) !important; }
.team-title{ font-size:clamp(42px,7vw,86px) !important; line-height:.88 !important; }
.team-subtitle{ max-width:560px !important; font-size:14px !important; line-height:1.5 !important; }
.roster-section{ width:min(1060px, calc(100% - 28px)) !important; padding:32px 0 48px !important; }
.roster-row{ border-radius:0 !important; border-left:0 !important; border-right:0 !important; background:#0b0b0c !important; }
.roster-avatar,.roster-avatar img,.roster-placeholder{
    border:0 !important;
    box-shadow:none !important;
}
.roster-avatar{
    background:transparent !important;
}
.roster-card-image{
    border-radius:0 !important;
}
.roster-name{ font-size:18px !important; }
.roster-meta,.roster-detail{ font-size:11px !important; }
@media(max-width:640px){
    .team-hero{ min-height:430px !important; align-items:flex-end !important; padding:22px 20px 28px !important; }
    .team-title{ font-size:38px !important; }
    .team-subtitle{ font-size:12.25px !important; }
    .roster-section{ width:calc(100% - 24px) !important; padding-top:26px !important; }
    .roster-row{ min-height:70px !important; padding:10px 0 !important; }
    .roster-avatar{ width:52px !important; height:52px !important; }
    .roster-name{ font-size:15px !important; }
    .roster-meta,.roster-detail{ font-size:9.5px !important; }
}

</style>


<style>
/* --------------------------------------------------------------------------
   Final team hero spacing pass: compact app-like website layout
-------------------------------------------------------------------------- */
.team-page{
    background:#050506 !important;
}

.team-hero{
    min-height:clamp(330px, 46vh, 470px) !important;
    padding:clamp(20px, 4vw, 40px) clamp(18px, 4vw, 42px) !important;
    display:flex !important;
    align-items:center !important;
    overflow:hidden !important;
}

.team-hero-bg{
    object-position:center 42% !important;
    filter:saturate(1.04) contrast(1.05) !important;
}

.team-hero::before{
    background:
        linear-gradient(90deg,
            color-mix(in srgb, var(--team-primary) 36%, rgba(0,0,0,.92)) 0%,
            rgba(0,0,0,.80) 44%,
            rgba(0,0,0,.94) 100%
        ),
        linear-gradient(180deg, rgba(0,0,0,.12) 0%, rgba(0,0,0,.58) 76%, rgba(0,0,0,.88) 100%) !important;
    opacity:1 !important;
}

.team-hero::after{
    opacity:.55 !important;
}

.team-hero-inner{
    width:min(980px, 100%) !important;
    margin:0 auto !important;
    padding:0 !important;
}

.team-brand-row{
    display:grid !important;
    grid-template-columns:auto minmax(0, 1fr) auto !important;
    align-items:center !important;
    gap:12px !important;
    margin-bottom:14px !important;
    max-width:640px !important;
}

.team-logo,
.team-league-logo{
    width:46px !important;
    height:46px !important;
    object-fit:contain !important;
}

.team-kicker{
    margin-bottom:5px !important;
    font-size:9px !important;
    letter-spacing:.20em !important;
    color:rgba(255,255,255,.70) !important;
}

.team-title{
    font-size:clamp(32px, 7vw, 54px) !important;
    line-height:.88 !important;
    letter-spacing:.035em !important;
}

.team-subtitle{
    margin-top:10px !important;
    max-width:520px !important;
    font-size:clamp(12px, 2.5vw, 14px) !important;
    line-height:1.45 !important;
    color:rgba(255,255,255,.82) !important;
}

.team-meta-strip{
    margin-top:18px !important;
    display:grid !important;
    grid-template-columns:repeat(3,minmax(0,1fr)) !important;
    max-width:680px !important;
    border-top:1px solid rgba(255,255,255,.08) !important;
    border-bottom:1px solid rgba(255,255,255,.08) !important;
}

.team-meta-item{
    min-height:58px !important;
    padding:10px 10px !important;
    border-right:1px solid rgba(255,255,255,.08) !important;
}
.team-meta-item:last-child{ border-right:0 !important; }
.team-meta-item span{ font-size:7px !important; letter-spacing:.14em !important; }
.team-meta-item strong{ font-size:11px !important; line-height:1.12 !important; }

.roster-section{
    width:min(980px, 100%) !important;
    margin:0 auto !important;
    padding:26px 18px 42px !important;
}

.roster-head{
    margin-bottom:14px !important;
}

.roster-title{
    font-size:30px !important;
    line-height:.9 !important;
}

.roster-subtitle{
    font-size:11px !important;
    line-height:1.35 !important;
}

.player-row,
.roster-row{
    min-height:64px !important;
    padding:9px 0 !important;
}

.player-row-name,
.roster-name{
    font-size:15.5px !important;
    line-height:1 !important;
}

.player-row-meta,
.roster-meta,
.roster-detail{
    font-size:9px !important;
    line-height:1.25 !important;
}

.roster-avatar{
    width:50px !important;
    height:50px !important;
}

@media (max-width:640px){
    .team-hero{
        min-height:360px !important;
        padding:22px 20px !important;
        align-items:center !important;
    }

    .team-brand-row{
        gap:10px !important;
        margin-bottom:12px !important;
    }

    .team-logo,
    .team-league-logo{
        width:42px !important;
        height:42px !important;
    }

    .team-title{
        font-size:39px !important;
        line-height:.88 !important;
    }

    .team-subtitle{
        font-size:12px !important;
        max-width:330px !important;
    }

    .team-meta-strip{
        margin-top:16px !important;
    }

    .team-meta-item{
        min-height:54px !important;
        padding:9px 7px !important;
    }

    .team-meta-item strong{ font-size:9.5px !important; }
    .roster-section{ padding:23px 14px 36px !important; }
}
</style>


<style>
/* PLYRCARD CLEAN REFRACTOR V2 - team page compact website polish */
.team-page{background:#050506 !important;}
.team-hero{min-height:clamp(300px,44vh,430px) !important;padding:22px 18px !important;overflow:hidden !important;}
.team-hero-bg{opacity:.76 !important;filter:saturate(1.08) contrast(1.04) brightness(.92) !important;}
.team-hero::before{background:linear-gradient(90deg,color-mix(in srgb,var(--team-primary) 24%,rgba(0,0,0,.68)) 0%,rgba(0,0,0,.46) 48%,rgba(0,0,0,.42) 100%) !important;opacity:1 !important;}
.team-hero::after{background:linear-gradient(180deg,rgba(0,0,0,.04),rgba(0,0,0,.38)),linear-gradient(90deg,var(--team-readable-accent,var(--team-primary)) 0 2px,transparent 2px 100%) !important;opacity:1 !important;}
.team-brand{gap:10px !important;margin-bottom:12px !important;}
.team-logo,.team-league-logo{width:40px !important;height:40px !important;border:0 !important;border-radius:0 !important;background:transparent !important;box-shadow:none !important;padding:0 !important;}
.team-name{font-size:clamp(28px,7vw,46px) !important;line-height:.88 !important;}
.team-kicker{font-size:8px !important;letter-spacing:.18em !important;color:var(--team-readable-accent,var(--team-primary)) !important;}
.team-copy{font-size:12px !important;line-height:1.42 !important;max-width:360px !important;color:rgba(255,255,255,.86) !important;}
.roster-section{padding-top:18px !important;}
.player-list-row,.player-modal-card,.coach-card,.team-stat{border-radius:0 !important;box-shadow:none !important;}
.player-list-row{min-height:64px !important;padding:9px 10px !important;}
.player-list-name{font-size:15px !important;line-height:1 !important;}
.player-list-meta{font-size:9px !important;}
@media(max-width:640px){.team-hero{min-height:310px !important;padding:20px 16px !important}.team-name{font-size:34px !important}.team-copy{font-size:11.5px !important}.player-list-row{min-height:60px !important}}
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
                    <button class="coach-open-btn" type="button" data-open-coach-modal>
                        <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                        Coach Info
                    </button>
                </div>
            @else
                <button class="coach-open-btn" type="button" data-open-coach-modal>
                    <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                    Coach Check In
                </button>
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
                            <span class="player-media {{ $player['listImageType'] === 'card' ? 'is-card' : 'is-portrait' }}">
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

        <div class="coach-modal" id="coachModal" aria-hidden="true">
            <div class="coach-modal-card" role="dialog" aria-modal="true" aria-labelledby="coachModalTitle">
                <div class="coach-modal-head">
                    <div class="coach-modal-title" id="coachModalTitle">Coach Check In</div>
                    <button class="coach-close-btn" type="button" data-close-coach-modal>
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        Close
                    </button>
                </div>
                <div class="coach-modal-body">
                    @if($coachSession)
                        <p class="coach-modal-copy">
                            You are checked in as <strong>{{ $coachSession['name'] ?? 'Coach' }}</strong>. Saved players stay in this temporary browser session.
                        </p>
                    @else
                        <p class="coach-modal-copy">Check in to save player information while reviewing this team.</p>
                        <form class="coach-form" method="POST" action="{{ route('clubs.coach-checkin', ['clubSlug' => $club->landing_page_slug]) }}">
                            @csrf
                            <div class="coach-field">
                                <label for="team_coach_school">School</label>
                                <input id="team_coach_school" name="school" type="text" placeholder="School name" required>
                            </div>
                            <div class="coach-field">
                                <label for="team_coach_name">Name</label>
                                <input id="team_coach_name" name="name" type="text" placeholder="Coach name" required>
                            </div>
                            <div class="coach-field">
                                <label for="team_coach_title">Title</label>
                                <input id="team_coach_title" name="title" type="text" placeholder="Head Coach, Assistant Coach..." required>
                            </div>
                            <div class="coach-field">
                                <label for="team_coach_email">Email</label>
                                <input id="team_coach_email" name="email" type="email" placeholder="coach@school.edu" required>
                            </div>
                            <button class="coach-submit" type="submit">Check In</button>
                        </form>
                    @endif
                </div>
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
            const coachModal = document.getElementById('coachModal');
            const coachOpenButtons = document.querySelectorAll('[data-open-coach-modal]');
            const coachCloseButtons = document.querySelectorAll('[data-close-coach-modal]');
            const title = document.getElementById('playerPanelTitle');
            const closeBtn = document.getElementById('playerCloseBtn');
            const nextBtn = document.getElementById('playerNextBtn');
            const prevBtn = document.getElementById('playerPrevBtn');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const saveUrlTemplate = @json($coachSavePlayerUrlTemplate);
            const unsaveUrlTemplate = @json($coachUnsavePlayerUrlTemplate);
            const coachCheckedIn = @json((bool) $coachSession);
            let activeIndex = 0;

            function openCoachModal() {
                coachModal?.classList.add('is-open');
                coachModal?.setAttribute('aria-hidden', 'false');
            }

            function closeCoachModal() {
                coachModal?.classList.remove('is-open');
                coachModal?.setAttribute('aria-hidden', 'true');
            }

            coachOpenButtons.forEach((button) => button.addEventListener('click', openCoachModal));
            coachCloseButtons.forEach((button) => button.addEventListener('click', closeCoachModal));
            coachModal?.addEventListener('click', (event) => {
                if (event.target === coachModal) closeCoachModal();
            });

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
                    orgRow('NATIONAL TEAM', player.nationalTeam, player.nationalLogo),
                    orgRow('CLUB', player.club, player.clubLogo),
                    orgRow('LEAGUE', player.league, player.leagueLogo),
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
                                    <div class="mobile-name-box">
                                        ${jersey ? `<div class="mobile-jersey">${escapeHtml(jersey)}</div>` : ''}
                                        <div class="mobile-first">${escapeHtml(first)}</div>
                                        <div class="mobile-last">${escapeHtml(last)}</div>
                                        <div class="mobile-position">${escapeHtml(clean(player.positionShort, 'POSITION'))}</div>
                                    </div>
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
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">HEIGHT:</div><div class="mobile-meta-value">${escapeHtml(clean(player.height, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">WEIGHT:</div><div class="mobile-meta-value">${escapeHtml(clean(player.weight, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">MAX SPEED:</div><div class="mobile-meta-value">${escapeHtml(clean(player.maxSpeed, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">DOMINANT FOOT:</div><div class="mobile-meta-value">${escapeHtml(clean(player.dominantFoot, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">DOB:</div><div class="mobile-meta-value">${escapeHtml(clean(player.birth, '--'))}</div></div>
                                    <div class="mobile-meta-row"><div class="mobile-meta-label">COACH:</div><div class="mobile-meta-value">${escapeHtml(clean(player.clubCoach, '--'))}</div></div>
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