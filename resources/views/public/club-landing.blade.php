<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $club->name }} | Club</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
        use Illuminate\Support\Str;

        $branding = is_array($club->branding ?? null) ? $club->branding : [];
        $contact = is_array($club->contact_info ?? null) ? $club->contact_info : [];
        $sponsors = collect(is_array($club->sponsors_partners ?? null) ? $club->sponsors_partners : []);
        $clubCoaches = collect(is_array($club->coaching_staff ?? null) ? $club->coaching_staff : []);

        $primary = $branding['primary_color'] ?? $club->primary_color ?? '#ff3131';
        $secondary = $branding['secondary_color'] ?? $club->secondary_color ?? '#050505';
        $accent = $branding['accent_color'] ?? $primary;
        $headingFont = $branding['heading_font'] ?? $branding['font_heading'] ?? 'Antonio';
        $bodyFont = $branding['body_font'] ?? $branding['font_body'] ?? 'Inter';

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

        $logo = $resolveAsset($club->logo ?? null);
        $leagueLogo = $resolveAsset($club->league?->logo ?? null);
        $heroImageUrl = $resolveAsset(
            $club->background_image
                ?? $club->hero_image
                ?? $branding['background_image']
                ?? $branding['hero_image']
                ?? null,
            asset('images/PLYRCARD-SITE.jpg')
        );

        $headline = $club->landing_page_intro ?: 'Built for the next level.';
        $content = $club->landing_page_content ?: 'A club home for athletes, families, and staff. Explore teams, follow the player pathway, and connect with the right people.';

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
                return 'girls';
            }

            return 'boys';
        };

        $boysTeams = collect($teams ?? [])->filter(fn ($team) => $teamGender($team) === 'boys')->values();
        $girlsTeams = collect($teams ?? [])->filter(fn ($team) => $teamGender($team) === 'girls')->values();
        $teamCount = collect($teams ?? [])->count();

        $teamCardImage = function ($team) use ($resolveAsset, $club) {
            return $resolveAsset(
                $team->background_image
                    ?? $team->hero_image
                    ?? $team->logo
                    ?? $club?->background_image
                    ?? $club?->hero_image
                    ?? null
            );
        };

        $teamLogo = function ($team) use ($resolveAsset, $club) {
            return $resolveAsset($team->logo ?: $club?->logo);
        };

        $coachSession = $coachCheckIn ?? session('coach_checkin');
        $savedPlayers = collect($savedPlayers ?? session('coach_saved_players', []))->filter(fn ($saved) => (int) ($saved['club_id'] ?? 0) === (int) $club->id)->unique('player_id')->values();
    @endphp

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|inter:400,500,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        :root{
            --club-primary: {{ $primary }};
            --club-secondary: {{ $secondary }};
            --club-accent: {{ $accent }};
            --club-heading: "{{ $headingFont }}", "Antonio", sans-serif;
            --club-body: "{{ $bodyFont }}", "Inter", sans-serif;
            --club-bg:#050506;
            --club-surface:#111114;
            --club-surface-2:#17171b;
            --club-line:rgba(255,255,255,.10);
            --club-muted:rgba(255,255,255,.68);
            --club-on-primary:#fff;
        }

        *{ box-sizing:border-box; }

        html{ scroll-behavior:smooth; }

        body{
            margin:0;
            min-height:100vh;
            background:var(--club-bg);
            color:#fff;
            font-family:var(--club-body);
            overflow-x:hidden;
        }

        .club-page{
            min-height:100vh;
            background:
                radial-gradient(circle at 16% 0%, color-mix(in srgb, var(--club-primary) 24%, transparent), transparent 28%),
                radial-gradient(circle at 88% 10%, color-mix(in srgb, var(--club-secondary) 30%, transparent), transparent 30%),
                #030304;
        }

        .club-hero{
            position:relative;
            min-height:640px;
            overflow:hidden;
            display:flex;
            align-items:flex-end;
            padding:clamp(22px, 4vw, 54px);
        }

        .club-hero-bg{
            position:absolute;
            inset:0;
            width:100%;
            height:100%;
            object-fit:cover;
            z-index:0;
        }

        .club-hero::before{
            content:"";
            position:absolute;
            inset:0;
            z-index:1;
            background:
                radial-gradient(circle at 10% 10%, color-mix(in srgb, var(--club-primary) 64%, transparent), transparent 28%),
                radial-gradient(circle at 88% 10%, color-mix(in srgb, var(--club-primary) 42%, transparent), transparent 26%),
                linear-gradient(90deg, color-mix(in srgb, var(--club-primary) 78%, rgba(0,0,0,.76)) 0%, rgba(0,0,0,.70) 50%, color-mix(in srgb, var(--club-secondary) 72%, rgba(0,0,0,.80)) 100%),
                linear-gradient(180deg, rgba(0,0,0,.12), rgba(0,0,0,.86));
            opacity:.95;
        }

        .club-hero::after{
            content:"";
            position:absolute;
            inset:0;
            z-index:2;
            background:linear-gradient(180deg, rgba(255,255,255,.02), rgba(0,0,0,.82));
        }

        .club-hero-inner{
            position:relative;
            z-index:3;
            width:min(1120px, 100%);
            margin:0 auto;
            display:grid;
            grid-template-columns:minmax(0, 1fr);
            gap:clamp(22px, 4vw, 48px);
            align-items:end;
        }

        .club-brand{
            display:flex;
            align-items:center;
            gap:16px;
            margin-bottom:28px;
        }

        .club-logo{
            width:clamp(70px, 8vw, 110px);
            height:clamp(70px, 8vw, 110px);
            object-fit:contain;
            background:transparent;
            border:0;
            box-shadow:none;
        }

        .league-logo{
            max-width:108px;
            max-height:54px;
            object-fit:contain;
            opacity:.94;
        }

        .club-type{
            color:rgba(255,255,255,.76);
            font-size:12px;
            letter-spacing:.20em;
            text-transform:uppercase;
            font-weight:900;
        }

        .club-name{
            margin-top:6px;
            font-family:var(--club-heading);
            font-size:clamp(28px, 4.4vw, 66px);
            line-height:.92;
            letter-spacing:.055em;
            text-transform:uppercase;
            font-weight:900;
        }

        .club-kicker{
            color:var(--club-primary);
            font-family:var(--club-heading);
            font-size:clamp(13px, 1.6vw, 20px);
            letter-spacing:.16em;
            text-transform:uppercase;
            font-weight:900;
            margin-bottom:12px;
        }

        .club-headline{
            margin:0;
            max-width:780px;
            font-family:var(--club-heading);
            font-size:clamp(48px, 8vw, 116px);
            line-height:.88;
            letter-spacing:.02em;
            text-transform:uppercase;
            font-weight:900;
            text-wrap:balance;
        }

        .club-copy{
            margin-top:20px;
            max-width:720px;
            color:rgba(255,255,255,.80);
            font-size:clamp(14px, 1.35vw, 17px);
            line-height:1.55;
            font-weight:650;
        }

        .club-actions{
            margin-top:22px;
            display:flex;
            flex-wrap:wrap;
            gap:10px;
        }

        .club-action{
            min-height:42px;
            padding:0 15px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            background:rgba(255,255,255,.08);
            color:#fff;
            text-decoration:none;
            font-family:var(--club-heading);
            font-size:12px;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
        }

        .club-action.primary{
            background:linear-gradient(135deg, var(--club-primary), var(--club-secondary));
        }

        .coach-checkin{
            background:rgba(0,0,0,.56);
            backdrop-filter:blur(16px);
            padding:18px;
        }

        .coach-checkin h2{
            margin:0 0 8px;
            font-family:var(--club-heading);
            font-size:28px;
            line-height:1;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
        }

        .coach-checkin p{
            margin:0 0 14px;
            color:rgba(255,255,255,.70);
            font-size:12px;
            line-height:1.45;
            font-weight:650;
        }

        .coach-form{
            display:grid;
            gap:9px;
        }

        .coach-field{
            display:grid;
            gap:5px;
        }

        .coach-field label{
            color:rgba(255,255,255,.62);
            font-size:9px;
            text-transform:uppercase;
            letter-spacing:.12em;
            font-weight:900;
        }

        .coach-field input{
            width:100%;
            height:42px;
            border:0;
            outline:0;
            padding:0 12px;
            background:rgba(255,255,255,.10);
            color:#fff;
            font-weight:750;
        }

        .coach-field input::placeholder{ color:rgba(255,255,255,.38); }

        .coach-submit{
            min-height:44px;
            border:0;
            background:linear-gradient(135deg, var(--club-primary), var(--club-secondary));
            color:#fff;
            font-family:var(--club-heading);
            font-size:13px;
            letter-spacing:.10em;
            text-transform:uppercase;
            font-weight:900;
            cursor:pointer;
        }

        .coach-session{
            background:rgba(255,255,255,.10);
            border-left:4px solid var(--club-primary);
            padding:14px;
        }

        .coach-session strong{
            display:block;
            font-family:var(--club-heading);
            font-size:24px;
            line-height:1;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
        }

        .coach-session span{
            display:block;
            margin-top:6px;
            color:rgba(255,255,255,.70);
            font-size:12px;
            font-weight:700;
        }

        .coach-saved{
            margin-top:12px;
            padding-top:12px;
            border-top:1px solid rgba(255,255,255,.10);
        }

        .coach-saved-title{
            display:flex;
            align-items:center;
            gap:8px;
            font-size:10px;
            letter-spacing:.12em;
            text-transform:uppercase;
            font-weight:900;
            color:var(--club-primary);
            margin-bottom:9px;
        }

        .coach-saved-list{
            display:grid;
            gap:7px;
            max-height:180px;
            overflow:auto;
        }

        .coach-saved-item{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:9px;
            padding:8px 0;
            border-bottom:1px solid rgba(255,255,255,.07);
            color:#fff;
            text-decoration:none;
            font-size:12px;
            font-weight:800;
        }


        .coach-open-btn{
            min-height:42px;
            border:0;
            padding:0 15px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            background:linear-gradient(135deg, var(--club-primary), var(--club-secondary));
            color:#fff;
            font-family:var(--club-heading);
            font-size:12px;
            letter-spacing:.08em;
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
        .coach-modal-card{ width:min(430px, 100%); background:#070708; color:#fff; box-shadow:0 24px 60px rgba(0,0,0,.48); overflow:hidden; }
        .coach-modal-head{ min-height:54px; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:0 14px; background:linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 34%, #000), #050506); }
        .coach-modal-title{ font-family:var(--club-heading); font-size:22px; line-height:1; letter-spacing:.08em; text-transform:uppercase; font-weight:900; }
        .coach-close-btn{ border:0; background:rgba(255,255,255,.10); color:#fff; min-height:34px; border-radius:999px; padding:0 12px; font-family:var(--club-heading); letter-spacing:.08em; text-transform:uppercase; font-weight:900; cursor:pointer; }
        .coach-modal-body{ padding:16px; }
        .coach-modal-copy{ margin:0 0 14px; color:rgba(255,255,255,.68); font-size:13px; line-height:1.45; font-weight:650; }

        .club-stats{
            display:grid;
            grid-template-columns:minmax(0, 1fr);
            background:#070708;
            width:min(1120px, calc(100% - 28px));
            margin:0 auto;
        }

        .club-stat{
            min-height:118px;
            padding:18px;
            display:flex;
            flex-direction:column;
            justify-content:center;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 12%, transparent), transparent 60%),
                rgba(255,255,255,.035);
        }

        .club-stat i{
            color:var(--club-primary);
            font-size:22px;
            margin-bottom:12px;
        }

        .club-stat span{
            display:block;
            color:rgba(255,255,255,.58);
            font-size:10px;
            letter-spacing:.12em;
            text-transform:uppercase;
            font-weight:900;
            margin-bottom:6px;
        }

        .club-stat strong{
            font-family:var(--club-heading);
            font-size:clamp(20px, 2.8vw, 34px);
            line-height:.95;
            text-transform:uppercase;
            font-weight:900;
        }

        .club-section{
            width:min(1120px, calc(100% - 28px));
            margin:0 auto;
            padding:clamp(34px, 6vw, 72px) 0;
        }

        .section-head{
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:18px;
            margin-bottom:22px;
        }

        .section-title{
            margin:0;
            font-family:var(--club-heading);
            font-size:clamp(36px, 5.8vw, 78px);
            line-height:.88;
            letter-spacing:.04em;
            text-transform:uppercase;
            font-weight:900;
        }

        .section-kicker{
            color:var(--club-primary);
            font-size:11px;
            letter-spacing:.16em;
            text-transform:uppercase;
            font-weight:900;
        }

        .team-tabs{
            display:flex;
            gap:8px;
        }

        .team-tab{
            min-height:42px;
            border:0;
            padding:0 14px;
            background:rgba(255,255,255,.08);
            color:#fff;
            cursor:pointer;
            font-family:var(--club-heading);
            font-size:13px;
            letter-spacing:.10em;
            text-transform:uppercase;
            font-weight:900;
        }

        .team-tab.is-active{
            background:linear-gradient(135deg, var(--club-primary), var(--club-secondary));
        }

        .team-panel{ display:none; }
        .team-panel.is-active{ display:block; }

        .team-grid{
            display:grid;
            grid-template-columns:repeat(3, minmax(0, 1fr));
            gap:14px;
        }

        .team-card{
            position:relative;
            min-height:260px;
            overflow:hidden;
            display:flex;
            align-items:flex-end;
            color:#fff;
            text-decoration:none;
            background:#111;
        }

        .team-card-bg{
            position:absolute;
            inset:0;
            width:100%;
            height:100%;
            object-fit:cover;
            z-index:0;
            transition:transform .25s ease;
        }

        .team-card::before{
            content:"";
            position:absolute;
            inset:0;
            z-index:1;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 52%, transparent), transparent 48%),
                linear-gradient(215deg, color-mix(in srgb, var(--club-secondary) 62%, transparent), transparent 56%),
                linear-gradient(180deg, rgba(0,0,0,.16), rgba(0,0,0,.84));
            mix-blend-mode:multiply;
        }

        .team-card::after{
            content:"";
            position:absolute;
            inset:0;
            z-index:2;
            background:linear-gradient(180deg, rgba(0,0,0,.06), rgba(0,0,0,.78));
        }

        .team-card:hover .team-card-bg{ transform:scale(1.05); }

        .team-card-content{
            position:relative;
            z-index:3;
            width:100%;
            padding:16px;
        }

        .team-card-logo-row{
            display:flex;
            align-items:center;
            gap:10px;
            margin-bottom:60px;
        }

        .team-card-logo{
            width:54px;
            height:54px;
            object-fit:contain;
            background:transparent;
        }

        .team-card-league-logo{
            max-width:84px;
            max-height:38px;
            object-fit:contain;
            opacity:.90;
        }

        .team-card-name{
            font-family:var(--club-heading);
            font-size:31px;
            line-height:.9;
            letter-spacing:.06em;
            text-transform:uppercase;
            font-weight:900;
        }

        .team-card-copy{
            margin-top:8px;
            color:rgba(255,255,255,.72);
            font-size:12px;
            font-weight:800;
        }

        .empty-teams{
            padding:28px;
            background:rgba(255,255,255,.055);
            color:rgba(255,255,255,.66);
            font-weight:800;
        }

        .club-info-band{
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 14%, transparent), transparent 42%),
                #080809;
        }

        .club-info-grid{
            width:min(1120px, calc(100% - 28px));
            margin:0 auto;
            padding:clamp(34px, 5vw, 62px) 0;
            display:grid;
            grid-template-columns:minmax(0, 1.2fr) minmax(260px, .8fr);
            gap:28px;
        }

        .club-info-grid h2{
            margin:0 0 12px;
            font-family:var(--club-heading);
            font-size:clamp(34px, 5vw, 72px);
            line-height:.9;
            text-transform:uppercase;
            letter-spacing:.04em;
            font-weight:900;
        }

        .club-info-grid p{
            margin:0;
            color:rgba(255,255,255,.72);
            line-height:1.55;
            font-weight:650;
        }

        .footer-info{
            display:grid;
            gap:8px;
        }

        .footer-item{
            min-height:44px;
            display:flex;
            align-items:center;
            gap:10px;
            color:#fff;
            text-decoration:none;
            background:rgba(255,255,255,.06);
            padding:9px 11px;
        }

        .footer-item i{
            color:var(--club-primary);
            width:20px;
            text-align:center;
        }

        .footer-item strong{
            display:block;
            font-size:10px;
            letter-spacing:.12em;
            text-transform:uppercase;
            color:rgba(255,255,255,.55);
            margin-bottom:2px;
        }

        .footer-item span{
            display:block;
            font-size:13px;
            font-weight:800;
        }

        .club-footer{
            min-height:54px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
            padding:14px clamp(14px, 4vw, 36px);
            background:#010101;
            color:rgba(255,255,255,.56);
            font-size:12px;
            font-weight:700;
        }

        .sponsor-row{
            display:flex;
            flex-wrap:wrap;
            gap:7px;
        }

        .sponsor{
            min-height:26px;
            display:inline-flex;
            align-items:center;
            padding:0 9px;
            background:rgba(255,255,255,.08);
            color:rgba(255,255,255,.78);
            font-size:10px;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
        }

        @media (max-width:860px){
            .club-hero{ min-height:auto; padding:24px 14px; }
            .club-hero-inner{ grid-template-columns:1fr; }
            .club-stats{ grid-template-columns:1fr 1fr 1fr; }
            .section-head{ align-items:flex-start; flex-direction:column; }
            .team-grid{ grid-template-columns:1fr; }
            .club-info-grid{ grid-template-columns:1fr; }
        }

        @media (max-width:560px){
            .club-brand{ gap:10px; margin-bottom:22px; }
            .club-logo{ width:62px; height:62px; }
            .league-logo{ max-width:90px; max-height:44px; }
            .club-headline{ font-size:52px; }
            .club-stats{ grid-template-columns:1fr; }
            .club-stat{ min-height:86px; }
            .team-tabs{ width:100%; display:grid; grid-template-columns:1fr 1fr; }
            .team-tab{ width:100%; }
            .team-card{ min-height:230px; }
            .club-footer{ align-items:flex-start; flex-direction:column; }
        }
    </style>
</head>

<body>
    <main class="club-page">
        <section class="club-hero">
            <img class="club-hero-bg" src="{{ $heroImageUrl }}" alt="{{ $club->name }} hero image">

            <div class="club-hero-inner">
                <div>
                    <div class="club-brand">
                        @if($logo)
                            <img class="club-logo" src="{{ $logo }}" alt="{{ $club->name }} logo">
                        @endif

                        <div>
                            <div class="club-type">Sports Club</div>
                            <div class="club-name">{{ $club->name }}</div>
                        </div>

                        @if($leagueLogo)
                            <img class="league-logo" src="{{ $leagueLogo }}" alt="{{ $club->league?->name }} logo">
                        @endif
                    </div>

                    <div class="club-kicker">{{ $headline }}</div>
                    <h1 class="club-headline">One Club.<br>One Standard.</h1>

                    <div class="club-copy">
                        {!! nl2br(e($content)) !!}
                    </div>

                    <div class="club-actions">
                        @if($email)
                            <a class="club-action primary" href="mailto:{{ $email }}">
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

                        <button class="coach-open-btn" type="button" data-open-coach-modal>
                            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                            {{ $coachSession ? 'Coach Info' : 'Coach Check In' }}
                        </button>
                    </div>
                </div>


            </div>
        </section>


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
                        <div class="coach-session">
                            <strong>Checked In</strong>
                            <span>{{ $coachSession['name'] ?? 'Coach' }} · {{ $coachSession['title'] ?? 'Coach' }}</span>
                            <span>{{ $coachSession['school'] ?? '' }}</span>
                            <div class="coach-saved">
                                <div class="coach-saved-title">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                    Saved Players
                                </div>
                                <div class="coach-saved-list">
                                    @forelse($savedPlayers as $saved)
                                        <a class="coach-saved-item" href="{{ $saved['player_url'] ?? '#club-teams' }}">
                                            <span>{{ $saved['player_name'] ?? 'Player' }}</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                        </a>
                                    @empty
                                        <div style="color:rgba(255,255,255,.62);font-size:12px;font-weight:800;">Saved players will appear after you tap the plus icon on a player card.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="coach-modal-copy">Check in to save player information while viewing team rosters.</p>
                        <form class="coach-form" method="POST" action="{{ route('clubs.coach-checkin', ['clubSlug' => $club->landing_page_slug]) }}">
                            @csrf
                            <div class="coach-field"><label for="coach_school">School</label><input id="coach_school" name="school" type="text" placeholder="School name" required></div>
                            <div class="coach-field"><label for="coach_name">Name</label><input id="coach_name" name="name" type="text" placeholder="Coach name" required></div>
                            <div class="coach-field"><label for="coach_title">Title</label><input id="coach_title" name="title" type="text" placeholder="Head Coach, Assistant Coach..." required></div>
                            <div class="coach-field"><label for="coach_email">Email</label><input id="coach_email" name="email" type="email" placeholder="coach@school.edu" required></div>
                            <button class="coach-submit" type="submit">Check In</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <section class="club-stats" aria-label="Club highlights">
            <div class="club-stat">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span>Teams</span>
                <strong>{{ $teamCount }}</strong>
            </div>

            <div class="club-stat">
                <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                <span>League</span>
                <strong>{{ $club->league?->name ?: 'TBD' }}</strong>
            </div>

            <div class="club-stat">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <span>Location</span>
                <strong>{{ $address ?: 'TBD' }}</strong>
            </div>
        </section>

        <section class="club-section" id="club-teams">
            <div class="section-head">
                <div>
                    <div class="section-kicker">Club Teams</div>
                    <h2 class="section-title">Teams</h2>
                </div>

                <div class="team-tabs">
                    <button class="team-tab is-active" type="button" data-team-tab="boys">
                        Boys · {{ $boysTeams->count() }}
                    </button>
                    <button class="team-tab" type="button" data-team-tab="girls">
                        Girls · {{ $girlsTeams->count() }}
                    </button>
                </div>
            </div>

            <div class="team-panel is-active" data-team-panel="boys">
                @if($boysTeams->isNotEmpty())
                    <div class="team-grid">
                        @foreach($boysTeams as $team)
                            @php
                                $image = $teamCardImage($team);
                                $logoUrl = $teamLogo($team);
                            @endphp

                            <a class="team-card" href="{{ $team->landingUrl() ?: '#' }}">
                                @if($image)
                                    <img class="team-card-bg" src="{{ $image }}" alt="{{ $team->name }} image">
                                @endif

                                <div class="team-card-content">
                                    <div class="team-card-logo-row">
                                        @if($logoUrl)
                                            <img class="team-card-logo" src="{{ $logoUrl }}" alt="{{ $team->name }} logo">
                                        @endif

                                        @if($leagueLogo)
                                            <img class="team-card-league-logo" src="{{ $leagueLogo }}" alt="{{ $club->league?->name }} logo">
                                        @endif
                                    </div>

                                    <div class="team-card-name">{{ $team->name }}</div>
                                    <div class="team-card-copy">Open roster</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="empty-teams">Boys teams will appear here once they are published.</div>
                @endif
            </div>

            <div class="team-panel" data-team-panel="girls">
                @if($girlsTeams->isNotEmpty())
                    <div class="team-grid">
                        @foreach($girlsTeams as $team)
                            @php
                                $image = $teamCardImage($team);
                                $logoUrl = $teamLogo($team);
                            @endphp

                            <a class="team-card" href="{{ $team->landingUrl() ?: '#' }}">
                                @if($image)
                                    <img class="team-card-bg" src="{{ $image }}" alt="{{ $team->name }} image">
                                @endif

                                <div class="team-card-content">
                                    <div class="team-card-logo-row">
                                        @if($logoUrl)
                                            <img class="team-card-logo" src="{{ $logoUrl }}" alt="{{ $team->name }} logo">
                                        @endif

                                        @if($leagueLogo)
                                            <img class="team-card-league-logo" src="{{ $leagueLogo }}" alt="{{ $club->league?->name }} logo">
                                        @endif
                                    </div>

                                    <div class="team-card-name">{{ $team->name }}</div>
                                    <div class="team-card-copy">Open roster</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="empty-teams">Girls teams will appear here once they are published.</div>
                @endif
            </div>
        </section>

        <section class="club-info-band">
            <div class="club-info-grid">
                <div>
                    <h2>{{ $club->name }}</h2>
                    <p>{!! nl2br(e($content)) !!}</p>
                </div>

                <div class="footer-info">
                    @if($club->league)
                        <div class="footer-item">
                            <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                            <div>
                                <strong>League</strong>
                                <span>{{ $club->league->name }}</span>
                            </div>
                        </div>
                    @endif

                    @if($address)
                        <div class="footer-item">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <div>
                                <strong>Location</strong>
                                <span>{{ $address }}</span>
                            </div>
                        </div>
                    @endif

                    @if($phone)
                        <a class="footer-item" href="tel:{{ preg_replace('/\D+/', '', $phone) }}">
                            <i class="fa-solid fa-phone" aria-hidden="true"></i>
                            <div>
                                <strong>Phone</strong>
                                <span>{{ $phone }}</span>
                            </div>
                        </a>
                    @endif

                    @if($email)
                        <a class="footer-item" href="mailto:{{ $email }}">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            <div>
                                <strong>Email</strong>
                                <span>{{ $email }}</span>
                            </div>
                        </a>
                    @endif

                    @foreach($clubCoaches->take(3) as $coach)
                        @if(filled($coach['name'] ?? null))
                            <div class="footer-item">
                                <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                                <div>
                                    <strong>{{ $coach['title'] ?? 'Coach' }}</strong>
                                    <span>{{ $coach['name'] }}</span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>

        <footer class="club-footer">
            <div>© {{ now()->year }} {{ $club->name }}. Powered by PlyrCard.</div>

            @if($sponsors->isNotEmpty())
                <div class="sponsor-row">
                    @foreach($sponsors as $sponsor)
                        @if(filled($sponsor['name'] ?? null))
                            <span class="sponsor">{{ $sponsor['name'] }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </footer>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('[data-team-tab]');
            const panels = document.querySelectorAll('[data-team-panel]');

            tabs.forEach((tab) => {
                tab.addEventListener('click', function () {
                    const target = this.getAttribute('data-team-tab');

                    tabs.forEach((item) => item.classList.toggle('is-active', item === this));
                    panels.forEach((panel) => {
                        panel.classList.toggle('is-active', panel.getAttribute('data-team-panel') === target);
                    });
                });
            });
        });
    </script>
</body>
</html>