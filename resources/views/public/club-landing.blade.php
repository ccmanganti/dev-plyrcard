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
        $onSecondary = $secondaryLum > 0.58 ? '#071018' : '#FFFFFF';
        $mutedOnDark = '#D7DCE4';
        $softOnDark = '#F4F7FB';

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
            --club-readable-primary: {{ $readablePrimary }};
            --club-readable-secondary: {{ $readableSecondary }};
            --club-on-primary: {{ $onPrimary }};
            --club-on-secondary: {{ $onSecondary }};
            --club-soft-text: {{ $softOnDark }};
            --club-muted-text: {{ $mutedOnDark }};
            --club-heading: "{{ $headingFont }}", "Antonio", sans-serif;
            --club-body: "{{ $bodyFont }}", "Inter", sans-serif;
            --club-bg:#050506;
            --club-surface:#111114;
            --club-surface-2:#17171b;
            --club-line:rgba(255,255,255,.10);
            --club-muted:rgba(255,255,255,.72);
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
            color:var(--club-muted-text);
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
            color:var(--club-readable-primary);
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
            color:var(--club-soft-text);
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
            color:var(--club-readable-primary);
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
            grid-template-columns:repeat(3, minmax(0, 1fr));
            background:#070708;
            width:min(1120px, calc(100% - 28px));
            margin:0 auto;
        }

        .club-stat{
            min-height:104px;
            padding:14px 12px;
            display:flex;
            flex-direction:column;
            justify-content:center;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 12%, transparent), transparent 60%),
                rgba(255,255,255,.035);
        }

        .club-stat i{
            color:var(--club-readable-primary);
            font-size:22px;
            margin-bottom:12px;
        }

        .club-stat span{
            display:block;
            color:var(--club-muted-text);
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
            color:var(--club-readable-primary);
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
            color:var(--club-readable-primary);
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
            color:var(--club-soft-text);
            font-size:10px;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:900;
        }

        @media (max-width:860px){
            .club-hero{ min-height:auto; padding:24px 14px; }
            .club-hero-inner{ grid-template-columns:1fr; }
            .club-stats{ grid-template-columns:repeat(3, minmax(0, 1fr)); }
            .section-head{ align-items:flex-start; flex-direction:column; }
            .team-grid{ grid-template-columns:1fr; }
            .club-info-grid{ grid-template-columns:1fr; }
        }

        @media (max-width:560px){
            .club-brand{ gap:10px; margin-bottom:22px; }
            .club-logo{ width:62px; height:62px; }
            .league-logo{ max-width:90px; max-height:44px; }
            .club-headline{ font-size:52px; }
            .club-stats{ grid-template-columns:repeat(3, minmax(0, 1fr)); width:calc(100% - 20px); }
            .club-stat{ min-height:86px; padding:10px 8px; }
            .club-stat i{ font-size:16px; margin-bottom:8px; }
            .club-stat span{ font-size:8px; margin-bottom:5px; }
            .club-stat strong{ font-size:clamp(13px, 4vw, 18px); line-height:1; }
            .team-tabs{ width:100%; display:grid; grid-template-columns:1fr 1fr; }
            .team-tab{ width:100%; }
            .team-card{ min-height:230px; }
            .club-footer{ align-items:flex-start; flex-direction:column; }
        }
    

        /*
        |--------------------------------------------------------------------------
        | Compact mobile-app tuning
        |--------------------------------------------------------------------------
        */
        .club-shell{ width:min(390px, 100%); padding:0; }
        .club-hero-main{ min-height:430px; padding:18px 16px; }
        .club-brand{ gap:9px; margin-bottom:14px; }
        .club-logo{ width:50px; height:50px; }
        .club-name{ font-size:25px; line-height:.94; letter-spacing:.055em; }
        .club-type{ font-size:9px; letter-spacing:.18em; }
        .club-kicker{ font-size:10px; letter-spacing:.14em; margin-bottom:7px; }
        .club-headline{ font-size:43px; line-height:.96; letter-spacing:.02em; }
        .club-copy{ margin-top:12px; font-size:12.5px; line-height:1.45; max-width:330px; }
        .club-actions{ margin-top:15px; gap:7px; }
        .club-action{ min-height:36px; padding:0 12px; font-size:10.5px; border-radius:0; }
        .club-stats{ grid-template-columns:repeat(3, minmax(0, 1fr)); gap:6px; width:100%; padding:8px; }
        .club-stat{ min-height:72px; padding:8px 7px; }
        .club-stat i{ font-size:14px; margin-bottom:5px; }
        .club-stat span{ font-size:7px; margin-bottom:4px; letter-spacing:.09em; }
        .club-stat strong{ font-size:clamp(10px, 3.1vw, 13px); line-height:1.05; letter-spacing:.015em; }
        .club-teams{ margin-top:8px; padding:12px 10px; }
        .club-section-kicker{ font-size:9px; letter-spacing:.18em; }
        .club-section-title{ font-size:25px; line-height:.96; }
        .club-gender-tab{ min-height:40px; font-size:12px; padding:0 10px; }
        .club-team-card{ min-height:74px; padding:10px; }
        .club-team-logo{ width:40px; height:40px; }
        .club-team-name{ font-size:17px; }
        .club-team-copy{ font-size:10px; }
        .coach-modal-head{ min-height:48px; }
        .coach-modal-title{ font-size:18px; }
        .coach-modal-copy{ font-size:12px; }
        .coach-field input{ height:40px; }
        .coach-submit{ min-height:40px; font-size:11.5px; }
        .club-footer{ padding:14px 12px; }
        .club-footer h2{ font-size:22px; }
        .club-footer p,.club-footer-item{ font-size:11px; }
        @media (max-width:560px){
            .club-shell{ width:100%; }
            .club-hero-main{ min-height:420px; border-radius:0 !important; }
            .club-headline{ font-size:40px !important; }
            .club-stats{ grid-template-columns:repeat(3, minmax(0, 1fr)) !important; width:100% !important; }
            .club-stat{ min-height:70px !important; padding:8px 6px !important; }
            .club-stat strong{ font-size:11px !important; }
        }

    

        /*
        |--------------------------------------------------------------------------
        | PLYRCard app polish pass
        |--------------------------------------------------------------------------
        | Inspired by plyrcard.com: black surface, compact athletic type,
        | high-contrast cards, and app-like spacing.
        */
        .club-page{
            background:
                radial-gradient(circle at 50% -8%, color-mix(in srgb, var(--club-readable-primary) 18%, transparent), transparent 34%),
                #030304 !important;
        }

        .club-hero,
        .club-section,
        .club-footer,
        .club-stats{
            width:min(430px, 100%) !important;
            margin-left:auto !important;
            margin-right:auto !important;
        }

        .club-hero{
            min-height:0 !important;
            padding:12px !important;
            align-items:stretch !important;
            background:#050506 !important;
        }

        .club-hero-bg{
            border-radius:24px !important;
            overflow:hidden !important;
        }

        .club-hero::before{
            inset:12px !important;
            border-radius:24px !important;
            background:
                radial-gradient(circle at 12% 6%, color-mix(in srgb, var(--club-primary) 54%, transparent), transparent 31%),
                radial-gradient(circle at 90% 0%, color-mix(in srgb, var(--club-secondary) 42%, transparent), transparent 34%),
                linear-gradient(100deg, color-mix(in srgb, var(--club-primary) 56%, rgba(0,0,0,.92)) 0%, rgba(0,0,0,.78) 54%, color-mix(in srgb, var(--club-secondary) 52%, rgba(0,0,0,.92)) 100%) !important;
            opacity:.98 !important;
        }

        .club-hero::after{
            inset:12px !important;
            border-radius:24px !important;
            background:
                linear-gradient(180deg, rgba(0,0,0,.08), rgba(0,0,0,.84)),
                linear-gradient(90deg, rgba(255,255,255,.06), transparent 44%) !important;
        }

        .club-hero-inner{
            min-height:330px !important;
            display:flex !important;
            align-items:flex-end !important;
            padding:18px !important;
        }

        .club-hero-copy{
            width:100% !important;
        }

        .club-brand{
            margin-bottom:16px !important;
            gap:10px !important;
        }

        .club-logo{
            width:48px !important;
            height:48px !important;
            object-fit:contain !important;
        }

        .league-logo{
            max-width:58px !important;
            max-height:34px !important;
            margin-left:auto !important;
        }

        .club-type{
            font-size:8px !important;
            letter-spacing:.18em !important;
            color:rgba(255,255,255,.78) !important;
        }

        .club-name{
            font-size:22px !important;
            line-height:.92 !important;
            letter-spacing:.055em !important;
            max-width:250px !important;
        }

        .club-kicker{
            font-size:9px !important;
            line-height:1.1 !important;
            letter-spacing:.16em !important;
            margin-bottom:7px !important;
            color:var(--club-readable-primary) !important;
            filter:drop-shadow(0 0 10px rgba(0,0,0,.55));
        }

        .club-headline{
            font-size:34px !important;
            line-height:.92 !important;
            letter-spacing:.025em !important;
            max-width:340px !important;
        }

        .club-copy{
            max-width:320px !important;
            margin-top:11px !important;
            font-size:11.5px !important;
            line-height:1.45 !important;
            color:rgba(255,255,255,.84) !important;
            font-weight:700 !important;
        }

        .club-actions{
            margin-top:14px !important;
            gap:8px !important;
        }

        .club-action,
        .coach-open-btn{
            min-height:36px !important;
            border-radius:12px !important;
            padding:0 12px !important;
            font-size:10px !important;
            letter-spacing:.09em !important;
            background:rgba(255,255,255,.075) !important;
            border:1px solid rgba(255,255,255,.10) !important;
            color:#fff !important;
        }

        .club-action.primary,
        .coach-open-btn{
            background:linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 72%, #111), color-mix(in srgb, var(--club-secondary) 72%, #111)) !important;
            color:#fff !important;
        }

        .club-stats{
            display:grid !important;
            grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
            gap:8px !important;
            padding:8px 12px 2px !important;
            background:#050506 !important;
        }

        .club-stat{
            min-height:76px !important;
            padding:9px 7px !important;
            border:0 !important;
            border-radius:16px !important;
            background:
                linear-gradient(145deg, rgba(255,255,255,.075), rgba(255,255,255,.025)),
                #101114 !important;
            box-shadow:none !important;
        }

        .club-stat i{
            font-size:13px !important;
            margin-bottom:6px !important;
            color:var(--club-readable-primary) !important;
            opacity:.95 !important;
        }

        .club-stat span{
            font-size:6.8px !important;
            line-height:1 !important;
            letter-spacing:.12em !important;
            color:rgba(255,255,255,.58) !important;
        }

        .club-stat strong{
            margin-top:5px !important;
            font-size:clamp(10px, 2.9vw, 13px) !important;
            line-height:1.03 !important;
            letter-spacing:.025em !important;
            color:#fff !important;
            overflow-wrap:anywhere !important;
        }

        .club-section{
            padding:14px 12px 20px !important;
            background:#050506 !important;
        }

        .section-head{
            align-items:center !important;
            gap:10px !important;
            margin-bottom:10px !important;
        }

        .section-kicker,
        .club-section-kicker{
            font-size:8px !important;
            letter-spacing:.18em !important;
            color:var(--club-readable-primary) !important;
        }

        .section-title,
        .club-section-title{
            font-size:22px !important;
            line-height:1 !important;
            letter-spacing:.08em !important;
        }

        .team-tabs{
            gap:6px !important;
        }

        .team-tab{
            min-height:34px !important;
            border-radius:11px !important;
            font-size:10px !important;
            padding:0 10px !important;
            background:#111216 !important;
            border:1px solid rgba(255,255,255,.08) !important;
            color:rgba(255,255,255,.78) !important;
        }

        .team-tab.is-active{
            background:linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 52%, #111), color-mix(in srgb, var(--club-secondary) 42%, #111)) !important;
            color:#fff !important;
        }

        .team-grid{
            gap:8px !important;
        }

        .team-card{
            min-height:82px !important;
            border-radius:17px !important;
            background:#111216 !important;
            border:1px solid rgba(255,255,255,.08) !important;
            overflow:hidden !important;
        }

        .team-card::before{
            background:linear-gradient(90deg, rgba(0,0,0,.70), rgba(0,0,0,.32)) !important;
        }

        .team-card-content{
            padding:10px !important;
            min-height:82px !important;
            justify-content:center !important;
        }

        .team-card-logo,
        .club-team-logo{
            width:36px !important;
            height:36px !important;
        }

        .team-card-league-logo{
            max-width:40px !important;
            max-height:26px !important;
        }

        .team-card-name,
        .club-team-name{
            font-size:15px !important;
            line-height:.98 !important;
            letter-spacing:.06em !important;
        }

        .team-card-copy,
        .club-team-copy{
            font-size:8.5px !important;
            color:rgba(255,255,255,.62) !important;
        }

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

        .club-footer{
            width:min(430px, 100%) !important;
            padding:14px 12px 18px !important;
            background:#050506 !important;
        }

        .club-footer h2{
            font-size:18px !important;
        }

        .club-footer p,
        .club-footer-item{
            font-size:10.5px !important;
            line-height:1.35 !important;
        }


        /*
        |--------------------------------------------------------------------------
        | Clean PLYRCard website pass - no bubbles, no cardy backgrounds
        |--------------------------------------------------------------------------
        | Full-width website rhythm inspired by plyrcard.com: dark stage, sharp
        | editorial sections, clean navigation, strong imagery, compact type.
        */
        body{
            background:#030303 !important;
        }

        .club-page{
            background:#030303 !important;
        }

        .club-hero,
        .club-stats,
        .club-section,
        .club-info-band,
        .club-footer{
            width:min(1180px, calc(100% - 32px)) !important;
            margin-left:auto !important;
            margin-right:auto !important;
        }

        .club-hero{
            margin-top:16px !important;
            min-height:560px !important;
            padding:0 !important;
            background:#050505 !important;
            border-radius:0 !important;
            border:0 !important;
            border-bottom:1px solid rgba(255,255,255,.12) !important;
            overflow:hidden !important;
            align-items:flex-end !important;
        }

        .club-hero-bg{
            border-radius:0 !important;
            opacity:.58 !important;
            filter:saturate(1.08) contrast(1.04) !important;
        }

        .club-hero::before{
            inset:0 !important;
            border-radius:0 !important;
            background:
                linear-gradient(100deg,
                    color-mix(in srgb, var(--club-primary) 70%, rgba(0,0,0,.84)) 0%,
                    rgba(0,0,0,.72) 45%,
                    color-mix(in srgb, var(--club-secondary) 62%, rgba(0,0,0,.86)) 100%) !important;
            opacity:.92 !important;
        }

        .club-hero::after{
            inset:0 !important;
            border-radius:0 !important;
            background:
                linear-gradient(180deg, rgba(0,0,0,.12) 0%, rgba(0,0,0,.28) 42%, rgba(0,0,0,.88) 100%),
                linear-gradient(90deg, rgba(255,255,255,.05), transparent 48%) !important;
        }

        .club-hero-inner{
            min-height:560px !important;
            width:100% !important;
            display:flex !important;
            align-items:flex-end !important;
            padding:clamp(24px, 4vw, 48px) !important;
        }

        .club-brand{
            margin-bottom:24px !important;
            gap:14px !important;
        }

        .club-logo{
            width:clamp(56px, 7vw, 92px) !important;
            height:clamp(56px, 7vw, 92px) !important;
            object-fit:contain !important;
        }

        .league-logo{
            max-width:92px !important;
            max-height:48px !important;
            margin-left:0 !important;
        }

        .club-type{
            font-size:10px !important;
            letter-spacing:.22em !important;
            color:rgba(255,255,255,.70) !important;
        }

        .club-name{
            max-width:760px !important;
            font-size:clamp(30px, 5.8vw, 70px) !important;
            line-height:.9 !important;
            letter-spacing:.045em !important;
        }

        .club-kicker{
            font-size:clamp(10px, 1.2vw, 13px) !important;
            letter-spacing:.18em !important;
            color:var(--club-readable-primary) !important;
            margin-bottom:10px !important;
        }

        .club-headline{
            max-width:780px !important;
            font-size:clamp(38px, 7vw, 86px) !important;
            line-height:.88 !important;
            letter-spacing:.01em !important;
        }

        .club-copy{
            max-width:620px !important;
            margin-top:16px !important;
            font-size:clamp(12px, 1.15vw, 15px) !important;
            line-height:1.55 !important;
            color:rgba(255,255,255,.82) !important;
            font-weight:650 !important;
        }

        .club-actions{
            margin-top:20px !important;
            gap:10px !important;
        }

        .club-action,
        .coach-open-btn,
        .coach-close-btn,
        .coach-submit,
        .team-tab{
            border-radius:0 !important;
            box-shadow:none !important;
        }

        .club-action,
        .coach-open-btn{
            min-height:38px !important;
            padding:0 14px !important;
            font-size:10.5px !important;
            background:transparent !important;
            border:1px solid rgba(255,255,255,.22) !important;
            color:#fff !important;
        }

        .club-action.primary,
        .coach-open-btn{
            background:var(--club-primary) !important;
            border-color:var(--club-primary) !important;
            color:var(--club-on-primary) !important;
        }

        .club-stats{
            display:grid !important;
            grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
            gap:0 !important;
            padding:0 !important;
            background:#050505 !important;
            border-bottom:1px solid rgba(255,255,255,.10) !important;
        }

        .club-stat{
            min-height:96px !important;
            padding:16px 14px !important;
            border:0 !important;
            border-right:1px solid rgba(255,255,255,.10) !important;
            border-radius:0 !important;
            background:#050505 !important;
            box-shadow:none !important;
        }

        .club-stat:last-child{ border-right:0 !important; }

        .club-stat i{
            color:var(--club-readable-primary) !important;
            font-size:16px !important;
            margin-bottom:9px !important;
        }

        .club-stat span{
            font-size:8px !important;
            letter-spacing:.18em !important;
            color:rgba(255,255,255,.54) !important;
        }

        .club-stat strong{
            font-size:clamp(12px, 1.7vw, 18px) !important;
            line-height:1.05 !important;
            color:#fff !important;
        }

        .club-section{
            padding:34px 0 44px !important;
            background:#050505 !important;
            border-bottom:1px solid rgba(255,255,255,.10) !important;
        }

        .section-head{
            margin-bottom:18px !important;
            padding:0 !important;
        }

        .section-kicker,
        .club-section-kicker{
            font-size:9px !important;
            letter-spacing:.20em !important;
            color:var(--club-readable-primary) !important;
        }

        .section-title,
        .club-section-title{
            font-size:clamp(26px, 4vw, 46px) !important;
            line-height:.92 !important;
            letter-spacing:.055em !important;
        }

        .team-tabs{
            display:flex !important;
            gap:0 !important;
            border:1px solid rgba(255,255,255,.12) !important;
            width:max-content !important;
        }

        .team-tab{
            min-height:38px !important;
            padding:0 18px !important;
            background:#050505 !important;
            border:0 !important;
            border-right:1px solid rgba(255,255,255,.12) !important;
            color:rgba(255,255,255,.72) !important;
        }

        .team-tab:last-child{ border-right:0 !important; }

        .team-tab.is-active{
            background:var(--club-primary) !important;
            color:var(--club-on-primary) !important;
        }

        .team-grid{
            display:grid !important;
            grid-template-columns:repeat(2, minmax(0, 1fr)) !important;
            gap:14px !important;
        }

        .team-card{
            min-height:220px !important;
            border-radius:0 !important;
            border:0 !important;
            border-bottom:1px solid rgba(255,255,255,.14) !important;
            background:#090909 !important;
            box-shadow:none !important;
        }

        .team-card::before{
            background:
                linear-gradient(90deg, rgba(0,0,0,.82), rgba(0,0,0,.36) 58%, rgba(0,0,0,.72)) !important;
        }

        .team-card::after{ display:none !important; }

        .team-card-content{
            min-height:220px !important;
            padding:20px !important;
            justify-content:flex-end !important;
        }

        .team-card-logo,
        .club-team-logo{
            width:46px !important;
            height:46px !important;
        }

        .team-card-name,
        .club-team-name{
            font-size:clamp(20px, 2.8vw, 32px) !important;
        }

        .team-card-copy,
        .club-team-copy{
            font-size:10px !important;
            color:rgba(255,255,255,.64) !important;
        }

        .club-info-band{
            padding:38px 0 !important;
            background:#050505 !important;
            border-bottom:1px solid rgba(255,255,255,.10) !important;
        }

        .club-info-grid{
            grid-template-columns:minmax(0, .85fr) minmax(0, 1.15fr) !important;
            gap:30px !important;
        }

        .club-info-grid h2{
            font-size:clamp(24px, 3.7vw, 44px) !important;
        }

        .club-info-grid p,
        .club-footer p,
        .club-footer-item{
            font-size:13px !important;
            line-height:1.55 !important;
            color:rgba(255,255,255,.72) !important;
        }

        .club-footer{
            padding:28px 0 34px !important;
            background:#050505 !important;
        }

        .club-footer h2{ font-size:24px !important; }

        .club-footer-item,
        .club-sponsor{
            border-radius:0 !important;
            background:transparent !important;
            border-color:rgba(255,255,255,.10) !important;
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
            .club-hero,
            .club-stats,
            .club-section,
            .club-info-band,
            .club-footer{
                width:100% !important;
            }

            .club-hero{
                margin-top:0 !important;
                min-height:520px !important;
            }

            .club-hero-inner{
                min-height:520px !important;
                padding:22px 16px !important;
            }

            .club-brand{ margin-bottom:18px !important; }
            .club-name{ font-size:28px !important; }
            .club-headline{ font-size:42px !important; }
            .club-copy{ font-size:12px !important; max-width:94% !important; }

            .club-stats{
                grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
            }

            .club-stat{
                min-height:74px !important;
                padding:10px 7px !important;
            }

            .club-stat i{ font-size:13px !important; margin-bottom:6px !important; }
            .club-stat span{ font-size:6.8px !important; letter-spacing:.12em !important; }
            .club-stat strong{ font-size:10px !important; }

            .club-section,
            .club-info-band,
            .club-footer{
                padding-left:14px !important;
                padding-right:14px !important;
            }

            .section-head{
                flex-direction:column !important;
                align-items:flex-start !important;
            }

            .team-tabs{ width:100% !important; }
            .team-tab{ flex:1 !important; padding:0 10px !important; }
            .team-grid{ grid-template-columns:1fr !important; gap:10px !important; }
            .team-card{ min-height:150px !important; }
            .team-card-content{ min-height:150px !important; padding:14px !important; }
            .club-info-grid{ grid-template-columns:1fr !important; gap:18px !important; }
        }

/* --------------------------------------------------------------------------
   Clean website/app layout correction - no bubble UI
-------------------------------------------------------------------------- */
.club-page{
    background:#050506 !important;
}

.club-hero{
    min-height:clamp(520px, 72vh, 680px) !important;
    align-items:center !important;
    padding:clamp(18px, 4vw, 54px) !important;
}

.club-hero::before{
    background:
        linear-gradient(90deg,
            color-mix(in srgb, var(--club-primary) 30%, rgba(0,0,0,.92)) 0%,
            rgba(0,0,0,.78) 42%,
            color-mix(in srgb, var(--club-secondary) 22%, rgba(0,0,0,.88)) 100%
        ),
        linear-gradient(180deg, rgba(0,0,0,.30) 0%, rgba(0,0,0,.68) 58%, rgba(0,0,0,.94) 100%) !important;
    opacity:1 !important;
}

.club-hero::after{
    background:
        linear-gradient(180deg, rgba(255,255,255,.018), rgba(0,0,0,.76)),
        linear-gradient(90deg, var(--club-primary) 0 3px, transparent 3px 100%) !important;
    opacity:.72 !important;
}

.club-hero-inner{
    width:min(1060px, 100%) !important;
    margin:0 auto !important;
}

.club-brand{
    display:grid !important;
    grid-template-columns:auto minmax(0, 1fr) auto !important;
    align-items:center !important;
    gap:14px !important;
    max-width:640px !important;
    margin-bottom:24px !important;
}

.club-logo{
    width:60px !important;
    height:60px !important;
    object-fit:contain !important;
}

.league-logo{
    width:58px !important;
    height:58px !important;
    object-fit:contain !important;
    justify-self:end !important;
}

.club-type{
    color:var(--club-readable-primary) !important;
    font-size:10px !important;
    letter-spacing:.18em !important;
    line-height:1 !important;
}

.club-name{
    font-size:clamp(28px, 4.6vw, 56px) !important;
    line-height:.93 !important;
    letter-spacing:.035em !important;
    max-width:540px !important;
}

.club-kicker{
    color:var(--club-readable-primary) !important;
    font-size:11px !important;
    letter-spacing:.16em !important;
    margin-bottom:10px !important;
}

.club-headline{
    font-size:clamp(44px, 7vw, 92px) !important;
    line-height:.88 !important;
    letter-spacing:.01em !important;
    max-width:760px !important;
}

.club-copy{
    margin-top:18px !important;
    max-width:590px !important;
    font-size:15px !important;
    line-height:1.48 !important;
    color:rgba(255,255,255,.86) !important;
}

.club-actions{
    margin-top:24px !important;
    gap:10px !important;
}

.club-action,
.coach-open-btn{
    min-height:40px !important;
    padding:0 15px !important;
    border-radius:0 !important;
    font-size:11px !important;
    letter-spacing:.08em !important;
}

.club-stats{
    width:min(1060px, calc(100% - 28px)) !important;
    margin:0 auto !important;
    display:grid !important;
    grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
    background:#09090a !important;
    border-top:1px solid rgba(255,255,255,.075) !important;
    border-bottom:1px solid rgba(255,255,255,.075) !important;
}

.club-stat{
    min-height:88px !important;
    padding:13px 14px !important;
    background:rgba(255,255,255,.025) !important;
    border-left:1px solid rgba(255,255,255,.075) !important;
}

.club-stat:first-child{
    border-left:0 !important;
}

.club-stat i{
    font-size:16px !important;
    margin-bottom:8px !important;
    color:var(--club-readable-primary) !important;
}

.club-stat span{
    font-size:8px !important;
    letter-spacing:.12em !important;
    margin-bottom:5px !important;
    color:rgba(255,255,255,.58) !important;
}

.club-stat strong{
    font-size:clamp(13px, 2.1vw, 22px) !important;
    line-height:1.05 !important;
    letter-spacing:.02em !important;
    color:#fff !important;
    overflow-wrap:normal !important;
    word-break:normal !important;
}

.club-section{
    width:min(1060px, calc(100% - 28px)) !important;
    padding:34px 0 48px !important;
}

.section-head{
    margin-bottom:18px !important;
}

.club-section-kicker{
    color:var(--club-readable-primary) !important;
    font-size:10px !important;
    letter-spacing:.20em !important;
}

.club-section-title{
    font-size:clamp(32px, 5vw, 56px) !important;
    line-height:.9 !important;
}

.club-gender-tabs{
    border-top:1px solid rgba(255,255,255,.075) !important;
    border-bottom:1px solid rgba(255,255,255,.075) !important;
    background:#080809 !important;
}

.club-gender-tab{
    border-radius:0 !important;
    min-height:44px !important;
    background:transparent !important;
    font-size:12px !important;
}

.club-gender-tab.is-active{
    background:var(--club-readable-primary) !important;
    color:#050506 !important;
}

.team-grid{
    gap:1px !important;
    background:rgba(255,255,255,.075) !important;
}

.team-card{
    border-radius:0 !important;
    border:0 !important;
    background:#0d0d0f !important;
}

.team-card-content{
    border-radius:0 !important;
    min-height:142px !important;
    padding:16px !important;
}

.club-team-logo,
.team-logo{
    border-radius:0 !important;
    background:transparent !important;
    box-shadow:none !important;
}

@media (max-width: 640px){
    .club-hero{
        min-height:500px !important;
        padding:22px 20px 26px !important;
        align-items:flex-end !important;
    }

    .club-brand{
        gap:10px !important;
        margin-bottom:18px !important;
        grid-template-columns:auto minmax(0,1fr) auto !important;
    }

    .club-logo,
    .league-logo{
        width:48px !important;
        height:48px !important;
    }

    .club-name{
        font-size:27px !important;
        line-height:.94 !important;
    }

    .club-type{
        font-size:8.5px !important;
        letter-spacing:.15em !important;
    }

    .club-kicker{
        font-size:9px !important;
        margin-bottom:8px !important;
    }

    .club-headline{
        font-size:40px !important;
        line-height:.92 !important;
        max-width:350px !important;
    }

    .club-copy{
        max-width:360px !important;
        font-size:12.25px !important;
        line-height:1.45 !important;
        margin-top:13px !important;
    }

    .club-actions{
        margin-top:18px !important;
    }

    .club-action,
    .coach-open-btn{
        min-height:36px !important;
        font-size:10px !important;
        padding:0 12px !important;
    }

    .club-stats{
        width:100% !important;
        grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
    }

    .club-stat{
        min-height:74px !important;
        padding:9px 8px !important;
    }

    .club-stat i{
        font-size:13px !important;
        margin-bottom:6px !important;
    }

    .club-stat span{
        font-size:6.75px !important;
        letter-spacing:.09em !important;
    }

    .club-stat strong{
        font-size:10px !important;
        line-height:1.08 !important;
    }

    .club-section{
        width:calc(100% - 28px) !important;
        padding:28px 0 42px !important;
    }

    .club-section-title{
        font-size:30px !important;
    }

    .team-card-content{
        min-height:118px !important;
        padding:13px !important;
    }
}

</style>


<style>
/* --------------------------------------------------------------------------
   Final hero spacing pass: tighter, cleaner, still website-like
-------------------------------------------------------------------------- */
.club-page{
    background:#050506 !important;
}

.club-hero{
    min-height:clamp(380px, 54vh, 520px) !important;
    padding:clamp(22px, 4vw, 42px) clamp(20px, 4vw, 44px) !important;
    display:flex !important;
    align-items:center !important;
    overflow:hidden !important;
}

.club-hero-bg{
    object-position:center 42% !important;
    filter:saturate(1.04) contrast(1.05) !important;
}

.club-hero::before{
    background:
        linear-gradient(90deg,
            color-mix(in srgb, var(--club-primary) 36%, rgba(0,0,0,.92)) 0%,
            rgba(0,0,0,.78) 44%,
            rgba(0,0,0,.94) 100%
        ),
        linear-gradient(180deg, rgba(0,0,0,.16) 0%, rgba(0,0,0,.56) 72%, rgba(0,0,0,.86) 100%) !important;
    opacity:1 !important;
}

.club-hero::after{
    background:
        linear-gradient(90deg, var(--club-readable-accent, var(--club-primary)) 0 2px, transparent 2px 100%),
        linear-gradient(180deg, rgba(255,255,255,.018), rgba(0,0,0,.36)) !important;
    opacity:.56 !important;
}

.club-hero-inner{
    width:min(980px, 100%) !important;
    margin:0 auto !important;
    display:grid !important;
    grid-template-columns:minmax(0, 1fr) !important;
    align-items:center !important;
}

.club-brand{
    display:grid !important;
    grid-template-columns:auto minmax(0, 1fr) auto !important;
    align-items:center !important;
    gap:12px !important;
    max-width:600px !important;
    margin:0 0 14px !important;
}

.club-logo,
.league-logo{
    width:48px !important;
    height:48px !important;
    object-fit:contain !important;
}

.club-type{
    margin:0 0 4px !important;
    font-size:9px !important;
    line-height:1 !important;
    letter-spacing:.22em !important;
    color:rgba(255,255,255,.78) !important;
}

.club-name{
    font-size:clamp(24px, 5.8vw, 38px) !important;
    line-height:.9 !important;
    letter-spacing:.045em !important;
    max-width:520px !important;
}

.club-kicker{
    margin:0 0 8px !important;
    color:var(--club-readable-accent, var(--club-primary)) !important;
    font-size:10px !important;
    line-height:1 !important;
    letter-spacing:.20em !important;
    opacity:.92 !important;
}

.club-headline{
    margin:0 !important;
    font-size:clamp(36px, 8vw, 64px) !important;
    line-height:.86 !important;
    letter-spacing:.018em !important;
    max-width:680px !important;
}

.club-copy{
    margin-top:12px !important;
    max-width:560px !important;
    color:rgba(255,255,255,.86) !important;
    font-size:clamp(12px, 2.6vw, 14px) !important;
    line-height:1.45 !important;
    font-weight:750 !important;
}

.club-actions{
    margin-top:18px !important;
    display:flex !important;
    flex-wrap:wrap !important;
    gap:8px !important;
}

.club-action,
.coach-open-btn{
    min-height:36px !important;
    padding:0 12px !important;
    border-radius:0 !important;
    font-size:10px !important;
    letter-spacing:.08em !important;
}

.club-stats{
    display:grid !important;
    grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
    width:min(980px, 100%) !important;
    margin:0 auto !important;
    border-top:1px solid rgba(255,255,255,.08) !important;
    border-bottom:1px solid rgba(255,255,255,.08) !important;
}

.club-stat{
    min-height:68px !important;
    padding:11px 10px !important;
    border-right:1px solid rgba(255,255,255,.08) !important;
    background:rgba(255,255,255,.015) !important;
}

.club-stat:last-child{ border-right:0 !important; }
.club-stat i{ font-size:13px !important; margin-bottom:6px !important; color:var(--club-readable-accent, var(--club-primary)) !important; }
.club-stat span{ font-size:7px !important; letter-spacing:.14em !important; color:rgba(255,255,255,.62) !important; }
.club-stat strong{ font-size:clamp(10px, 2.1vw, 13px) !important; line-height:1.12 !important; color:#fff !important; }

.club-section{
    width:min(980px, 100%) !important;
    margin:0 auto !important;
    padding:28px 20px 34px !important;
}

.section-head{
    margin-bottom:14px !important;
}

.section-kicker{
    font-size:9px !important;
    letter-spacing:.22em !important;
    color:var(--club-readable-accent, var(--club-primary)) !important;
}

.section-title{
    font-size:30px !important;
    line-height:.9 !important;
}

.team-grid{
    gap:10px !important;
}

.team-card-content{
    min-height:132px !important;
    padding:14px !important;
}

.club-info-band{
    width:min(980px, 100%) !important;
    margin:0 auto !important;
    padding:30px 20px !important;
}

.club-footer{
    width:min(980px, 100%) !important;
    margin:0 auto !important;
    padding:14px 20px 24px !important;
}

@media (max-width:640px){
    .club-hero{
        min-height:405px !important;
        padding:24px 20px 24px !important;
        align-items:center !important;
    }

    .club-brand{
        gap:10px !important;
        margin-bottom:13px !important;
    }

    .club-logo,
    .league-logo{
        width:44px !important;
        height:44px !important;
    }

    .club-name{
        font-size:27px !important;
        line-height:.9 !important;
    }

    .club-headline{
        font-size:41px !important;
        line-height:.86 !important;
        max-width:360px !important;
    }

    .club-copy{
        max-width:340px !important;
        font-size:12.5px !important;
        line-height:1.45 !important;
        margin-top:11px !important;
    }

    .club-actions{ margin-top:16px !important; }
    .club-stats{ grid-template-columns:repeat(3, minmax(0,1fr)) !important; }
    .club-stat{ min-height:64px !important; padding:10px 7px !important; }
    .club-stat strong{ font-size:9.6px !important; }
    .club-section{ padding:24px 14px 30px !important; }
}
</style>


<style>
/* --------------------------------------------------------------------------
   PLYRCARD CLEAN REFRACTOR V2
   Goal: brighter hero image, tighter spacing, clean website/app navigation.
-------------------------------------------------------------------------- */
:root{
    --club-ink:#f7f7f7;
    --club-muted-clean:rgba(255,255,255,.72);
    --club-line-clean:rgba(255,255,255,.09);
}

body{
    background:#050506 !important;
}

.club-page{
    background:#050506 !important;
}

.club-hero{
    position:relative !important;
    min-height:clamp(360px, 52vh, 520px) !important;
    padding:clamp(24px, 4vw, 46px) clamp(20px, 4vw, 48px) !important;
    display:flex !important;
    align-items:center !important;
    border:0 !important;
    overflow:hidden !important;
    isolation:isolate !important;
}

.club-hero-bg{
    position:absolute !important;
    inset:0 !important;
    width:100% !important;
    height:100% !important;
    object-fit:cover !important;
    object-position:center 42% !important;
    opacity:.72 !important;
    filter:saturate(1.1) contrast(1.05) brightness(.92) !important;
    transform:scale(1.01) !important;
    z-index:0 !important;
}

.club-hero::before{
    content:"" !important;
    position:absolute !important;
    inset:0 !important;
    z-index:1 !important;
    opacity:1 !important;
    background:
        linear-gradient(90deg,
            color-mix(in srgb, var(--club-primary) 24%, rgba(0,0,0,.68)) 0%,
            rgba(0,0,0,.46) 42%,
            rgba(0,0,0,.36) 72%,
            rgba(0,0,0,.56) 100%
        ),
        radial-gradient(circle at 16% 42%, color-mix(in srgb, var(--club-primary) 20%, transparent), transparent 38%) !important;
    pointer-events:none !important;
}

.club-hero::after{
    content:"" !important;
    position:absolute !important;
    inset:0 !important;
    z-index:2 !important;
    opacity:1 !important;
    background:
        linear-gradient(180deg, rgba(0,0,0,.04) 0%, rgba(0,0,0,.08) 48%, rgba(0,0,0,.42) 100%),
        linear-gradient(90deg, var(--club-readable-accent, var(--club-primary)) 0 2px, transparent 2px 100%) !important;
    pointer-events:none !important;
}

.club-hero-inner{
    position:relative !important;
    z-index:3 !important;
    width:min(1040px, 100%) !important;
    margin:0 auto !important;
    display:block !important;
}

.club-brand{
    width:min(610px,100%) !important;
    display:grid !important;
    grid-template-columns:auto minmax(0,1fr) auto !important;
    align-items:center !important;
    gap:13px !important;
    margin:0 0 14px !important;
}

.club-logo,
.league-logo{
    width:46px !important;
    height:46px !important;
    object-fit:contain !important;
    background:transparent !important;
    border:0 !important;
    border-radius:0 !important;
    box-shadow:none !important;
    padding:0 !important;
}

.club-type{
    margin:0 0 5px !important;
    color:rgba(255,255,255,.78) !important;
    font-size:9px !important;
    line-height:1 !important;
    letter-spacing:.24em !important;
    font-weight:900 !important;
}

.club-name{
    color:#fff !important;
    font-size:clamp(25px, 4.7vw, 42px) !important;
    line-height:.91 !important;
    letter-spacing:.035em !important;
    text-shadow:0 2px 18px rgba(0,0,0,.28) !important;
    max-width:560px !important;
}

.club-kicker{
    margin:0 0 8px !important;
    color:var(--club-readable-accent, var(--club-primary)) !important;
    font-size:9.5px !important;
    line-height:1 !important;
    letter-spacing:.20em !important;
    opacity:1 !important;
    font-weight:900 !important;
}

.club-headline{
    margin:0 !important;
    color:#fff !important;
    font-size:clamp(38px, 7vw, 72px) !important;
    line-height:.88 !important;
    letter-spacing:.012em !important;
    max-width:690px !important;
    text-shadow:0 4px 22px rgba(0,0,0,.28) !important;
}

.club-copy{
    margin:12px 0 0 !important;
    max-width:600px !important;
    color:rgba(255,255,255,.88) !important;
    font-size:clamp(12px, 1.8vw, 14px) !important;
    line-height:1.48 !important;
    font-weight:760 !important;
    text-shadow:0 2px 12px rgba(0,0,0,.22) !important;
}

.club-actions{
    margin-top:16px !important;
    display:flex !important;
    gap:8px !important;
    flex-wrap:wrap !important;
}

.club-action,
.coach-open-btn{
    min-height:36px !important;
    border-radius:0 !important;
    padding:0 13px !important;
    background:var(--club-readable-accent, var(--club-primary)) !important;
    color:#050506 !important;
    border:0 !important;
    box-shadow:none !important;
    font-size:10px !important;
    letter-spacing:.08em !important;
    font-weight:950 !important;
}

.club-stats{
    width:min(1040px, 100%) !important;
    margin:0 auto !important;
    display:grid !important;
    grid-template-columns:repeat(3,minmax(0,1fr)) !important;
    background:#09090a !important;
    border-top:1px solid var(--club-line-clean) !important;
    border-bottom:1px solid var(--club-line-clean) !important;
    gap:0 !important;
}

.club-stat{
    min-height:62px !important;
    padding:10px 12px !important;
    border:0 !important;
    border-right:1px solid var(--club-line-clean) !important;
    background:linear-gradient(180deg, rgba(255,255,255,.025), rgba(255,255,255,.008)) !important;
}

.club-stat:last-child{border-right:0 !important;}
.club-stat i{
    color:var(--club-readable-accent, var(--club-primary)) !important;
    font-size:13px !important;
    margin:0 0 6px !important;
    opacity:.95 !important;
}
.club-stat span{
    display:block !important;
    color:rgba(255,255,255,.58) !important;
    font-size:7px !important;
    line-height:1 !important;
    letter-spacing:.13em !important;
    margin:0 0 5px !important;
    font-weight:900 !important;
}
.club-stat strong{
    display:block !important;
    color:#fff !important;
    font-size:clamp(10px, 1.65vw, 14px) !important;
    line-height:1.08 !important;
    letter-spacing:.015em !important;
    font-weight:950 !important;
    text-wrap:balance !important;
}

.club-section,
.club-info-band,
.club-footer{
    width:min(1040px, 100%) !important;
    margin:0 auto !important;
}

.club-section{
    padding:24px 20px 34px !important;
}

.section-head{margin-bottom:14px !important;}
.section-kicker,
.club-section-kicker{
    color:var(--club-readable-accent, var(--club-primary)) !important;
    font-size:9px !important;
    letter-spacing:.22em !important;
}
.section-title,
.club-section-title{
    color:#fff !important;
    font-size:clamp(28px, 4.2vw, 44px) !important;
    line-height:.9 !important;
}

.club-gender-tabs,
.team-grid,
.club-info-grid{
    border-radius:0 !important;
    box-shadow:none !important;
}

.club-gender-tab,
.team-card,
.team-card-content,
.club-footer-item,
.club-info-card{
    border-radius:0 !important;
    box-shadow:none !important;
}

@media (max-width:640px){
    .club-hero{
        min-height:360px !important;
        padding:22px 18px 22px !important;
        align-items:center !important;
    }

    .club-hero-bg{
        opacity:.76 !important;
        object-position:center 38% !important;
    }

    .club-hero::before{
        background:
            linear-gradient(90deg,
                color-mix(in srgb, var(--club-primary) 25%, rgba(0,0,0,.66)) 0%,
                rgba(0,0,0,.46) 48%,
                rgba(0,0,0,.42) 100%
            ),
            radial-gradient(circle at 18% 45%, color-mix(in srgb, var(--club-primary) 18%, transparent), transparent 42%) !important;
    }

    .club-brand{
        gap:10px !important;
        margin-bottom:12px !important;
    }

    .club-logo,
    .league-logo{
        width:38px !important;
        height:38px !important;
    }

    .club-type{
        font-size:7.7px !important;
        letter-spacing:.20em !important;
    }

    .club-name{
        font-size:24px !important;
        line-height:.92 !important;
        max-width:250px !important;
    }

    .club-kicker{
        font-size:8px !important;
        letter-spacing:.18em !important;
        margin-bottom:7px !important;
    }

    .club-headline{
        font-size:34px !important;
        line-height:.88 !important;
        max-width:330px !important;
    }

    .club-copy{
        max-width:340px !important;
        font-size:11.5px !important;
        line-height:1.42 !important;
        margin-top:10px !important;
    }

    .club-actions{margin-top:14px !important;}
    .club-action,.coach-open-btn{min-height:34px !important;font-size:9px !important;padding:0 11px !important;}
    .club-stat{min-height:58px !important;padding:9px 7px !important;}
    .club-stat i{font-size:12px !important;margin-bottom:5px !important;}
    .club-stat span{font-size:6.2px !important;letter-spacing:.11em !important;}
    .club-stat strong{font-size:9px !important;line-height:1.08 !important;}
    .club-section{padding:22px 14px 30px !important;}
}
</style>


<style>
/* === FINAL CLEAN MOBILE-WEBSITE RESET === */
body{
    background:#111 !important;
}
.club-page{
    width:min(430px, 100%) !important;
    margin:0 auto !important;
    background:#050506 !important;
    color:#fff !important;
    border-left:1px solid rgba(255,255,255,.06) !important;
    border-right:1px solid rgba(255,255,255,.06) !important;
    box-shadow:none !important;
}
.club-hero{
    position:relative !important;
    min-height:0 !important;
    height:auto !important;
    padding:0 !important;
    margin:0 !important;
    overflow:hidden !important;
    background:#050506 !important;
    border-bottom:1px solid rgba(255,255,255,.10) !important;
}
.club-hero-bg{
    position:absolute !important;
    inset:0 !important;
    width:100% !important;
    height:100% !important;
    object-fit:cover !important;
    object-position:center center !important;
    opacity:.82 !important;
    filter:saturate(1.04) contrast(1.02) brightness(.96) !important;
}
.club-hero::before{
    content:"" !important;
    position:absolute !important;
    inset:0 !important;
    z-index:1 !important;
    background:
        linear-gradient(90deg, rgba(0,0,0,.70) 0%, rgba(0,0,0,.43) 48%, rgba(0,0,0,.64) 100%),
        linear-gradient(135deg, color-mix(in srgb, var(--club-primary) 30%, transparent), transparent 54%),
        linear-gradient(180deg, rgba(0,0,0,.10) 0%, rgba(0,0,0,.32) 100%) !important;
    opacity:1 !important;
}
.club-hero::after{
    content:"" !important;
    position:absolute !important;
    left:0 !important;
    right:0 !important;
    bottom:0 !important;
    height:2px !important;
    z-index:2 !important;
    background:linear-gradient(90deg, var(--club-readable-primary), color-mix(in srgb, var(--club-readable-primary) 55%, transparent), transparent) !important;
    opacity:.85 !important;
}
.club-hero-inner{
    position:relative !important;
    z-index:3 !important;
    width:100% !important;
    min-height:0 !important;
    display:block !important;
    grid-template-columns:1fr !important;
    padding:48px 24px 28px !important;
    margin:0 !important;
}
.club-brand{
    display:grid !important;
    grid-template-columns:52px minmax(0,1fr) 48px !important;
    gap:12px !important;
    align-items:center !important;
    margin:0 0 18px !important;
}
.club-logo,.league-logo{
    width:46px !important;
    height:46px !important;
    object-fit:contain !important;
    background:transparent !important;
    border:0 !important;
    box-shadow:none !important;
    padding:0 !important;
    border-radius:0 !important;
}
.league-logo{ justify-self:end !important; width:42px !important; height:42px !important; }
.club-type{
    margin:0 0 6px !important;
    font-family:var(--club-heading) !important;
    color:rgba(255,255,255,.78) !important;
    font-size:10px !important;
    line-height:1 !important;
    letter-spacing:.20em !important;
    text-transform:uppercase !important;
    font-weight:900 !important;
}
.club-name{
    margin:0 !important;
    font-family:var(--club-heading) !important;
    color:#fff !important;
    font-size:clamp(25px, 7vw, 31px) !important;
    line-height:.92 !important;
    letter-spacing:.035em !important;
    text-transform:uppercase !important;
    font-weight:900 !important;
    max-width:260px !important;
}
.club-kicker{
    margin:0 0 9px !important;
    color:var(--club-readable-primary) !important;
    filter:none !important;
    font-family:var(--club-heading) !important;
    font-size:10px !important;
    line-height:1 !important;
    letter-spacing:.15em !important;
    text-transform:uppercase !important;
    font-weight:900 !important;
}
.club-headline{
    margin:0 !important;
    max-width:330px !important;
    color:#fff !important;
    font-family:var(--club-heading) !important;
    font-size:clamp(38px, 11vw, 48px) !important;
    line-height:.86 !important;
    letter-spacing:-.025em !important;
    text-transform:uppercase !important;
    font-weight:900 !important;
}
.club-copy{
    max-width:335px !important;
    margin:13px 0 0 !important;
    color:rgba(255,255,255,.88) !important;
    font-size:13px !important;
    line-height:1.42 !important;
    font-weight:800 !important;
}
.club-actions{
    margin-top:18px !important;
    display:flex !important;
    flex-wrap:wrap !important;
    gap:8px !important;
}
.club-action,.coach-open-btn{
    min-height:38px !important;
    border-radius:0 !important;
    border:0 !important;
    border-left:2px solid var(--club-readable-primary) !important;
    background:rgba(0,0,0,.48) !important;
    color:#fff !important;
    padding:0 13px !important;
    font-family:var(--club-heading) !important;
    font-size:10px !important;
    letter-spacing:.08em !important;
    text-transform:uppercase !important;
    font-weight:900 !important;
    box-shadow:none !important;
}
.club-action.primary,.coach-open-btn{
    background:color-mix(in srgb, var(--club-primary) 34%, rgba(0,0,0,.48)) !important;
    color:#fff !important;
}
.club-stats{
    display:grid !important;
    grid-template-columns:repeat(3,minmax(0,1fr)) !important;
    gap:0 !important;
    width:100% !important;
    margin:0 !important;
    padding:0 !important;
    background:#0b0b0d !important;
    border-top:0 !important;
    border-bottom:1px solid rgba(255,255,255,.10) !important;
}
.club-stat{
    min-height:74px !important;
    padding:10px 9px !important;
    border:0 !important;
    border-right:1px solid rgba(255,255,255,.10) !important;
    background:transparent !important;
    display:flex !important;
    flex-direction:column !important;
    justify-content:center !important;
    box-shadow:none !important;
}
.club-stat:last-child{ border-right:0 !important; }
.club-stat i{
    color:var(--club-readable-primary) !important;
    font-size:13px !important;
    margin:0 0 7px !important;
    opacity:.95 !important;
}
.club-stat span{
    color:rgba(255,255,255,.60) !important;
    font-family:var(--club-heading) !important;
    font-size:7px !important;
    line-height:1 !important;
    letter-spacing:.13em !important;
    text-transform:uppercase !important;
    font-weight:900 !important;
    margin:0 0 5px !important;
}
.club-stat strong{
    color:#fff !important;
    font-family:var(--club-heading) !important;
    font-size:clamp(10px, 3.2vw, 13px) !important;
    line-height:1.08 !important;
    letter-spacing:.02em !important;
    text-transform:uppercase !important;
    font-weight:900 !important;
    overflow-wrap:normal !important;
    word-break:normal !important;
}
.club-section{
    padding:24px 20px 40px !important;
    margin:0 !important;
    background:#050506 !important;
    border:0 !important;
}
.section-head{
    display:flex !important;
    align-items:flex-end !important;
    justify-content:space-between !important;
    gap:12px !important;
    margin:0 0 14px !important;
}
.section-kicker{
    color:var(--club-readable-primary) !important;
    font-size:9px !important;
    letter-spacing:.16em !important;
    margin-bottom:4px !important;
}
.section-title{
    color:#fff !important;
    font-size:28px !important;
    line-height:.92 !important;
    margin:0 !important;
}
.team-tabs{ gap:4px !important; }
.team-tab{
    border-radius:0 !important;
    padding:7px 8px !important;
    font-size:9px !important;
    background:#101014 !important;
    border:1px solid rgba(255,255,255,.10) !important;
}
.team-tab.is-active{
    background:var(--club-readable-primary) !important;
    color:#050506 !important;
}
.team-grid{ gap:10px !important; }
.club-team-card{
    border-radius:0 !important;
    min-height:92px !important;
    background:#0d0d10 !important;
    border:1px solid rgba(255,255,255,.10) !important;
    box-shadow:none !important;
}
.club-team-card::before{
    background:linear-gradient(90deg, rgba(0,0,0,.72), rgba(0,0,0,.44)) !important;
}
.club-team-name{ font-size:18px !important; }
.club-team-copy{ font-size:10px !important; color:rgba(255,255,255,.70) !important; }
.coach-modal-card{
    border-radius:0 !important;
    background:#070708 !important;
    border:1px solid rgba(255,255,255,.16) !important;
}
@media (max-width:440px){
    .club-page{ width:100% !important; border:0 !important; }
    .club-hero-inner{ padding:42px 22px 26px !important; }
    .club-headline{ font-size:44px !important; }
    .club-copy{ font-size:12.5px !important; }
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