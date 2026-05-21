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
        $clubCoaches = collect(is_array($club->coaching_staff ?? null) ? $club->coaching_staff : []);
        $sponsors = collect(is_array($club->sponsors_partners ?? null) ? $club->sponsors_partners : []);

        $primary = $branding['primary_color'] ?? $club->primary_color ?? '#00A3FF';
        $secondary = $branding['secondary_color'] ?? $club->secondary_color ?? '#050505';
        $accent = $branding['accent_color'] ?? $primary;
        $headingFont = $branding['heading_font'] ?? $branding['font_heading'] ?? 'Antonio';
        $bodyFont = $branding['body_font'] ?? $branding['font_body'] ?? 'Inter';

        $normalizeHex = function (?string $hex, string $fallback = '#00A3FF') {
            $hex = trim((string) $hex);
            if ($hex === '') return $fallback;
            if (! str_starts_with($hex, '#')) $hex = '#' . $hex;
            return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtoupper($hex) : $fallback;
        };

        $hexToRgb = function (string $hex) {
            $hex = ltrim($hex, '#');
            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        };

        $rgbToHex = function (array $rgb) {
            return sprintf('#%02X%02X%02X', max(0, min(255, (int) round($rgb[0]))), max(0, min(255, (int) round($rgb[1]))), max(0, min(255, (int) round($rgb[2]))));
        };

        $mixHex = function (string $hex, string $mixWith, float $amount) use ($hexToRgb, $rgbToHex) {
            $a = $hexToRgb($hex);
            $b = $hexToRgb($mixWith);
            return $rgbToHex([$a[0] + (($b[0] - $a[0]) * $amount), $a[1] + (($b[1] - $a[1]) * $amount), $a[2] + (($b[2] - $a[2]) * $amount)]);
        };

        $luminance = function (string $hex) use ($hexToRgb) {
            [$r, $g, $b] = array_map(fn ($value) => $value / 255, $hexToRgb($hex));
            $convert = fn ($channel) => $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            return (0.2126 * $convert($r)) + (0.7152 * $convert($g)) + (0.0722 * $convert($b));
        };

        $primary = $normalizeHex($primary);
        $secondary = $normalizeHex($secondary, '#050505');
        $accent = $normalizeHex($accent, $primary);
        $primaryLum = $luminance($primary);
        $readablePrimary = $primaryLum < 0.30 ? $mixHex($primary, '#FFFFFF', 0.58) : ($primaryLum > 0.70 ? $mixHex($primary, '#000000', 0.35) : $primary);
        $onPrimary = $primaryLum > 0.58 ? '#061018' : '#FFFFFF';

        $resolveAsset = function ($value, $fallback = null) use (&$resolveAsset) {
            if (blank($value)) return $fallback;
            if (is_array($value)) {
                if (isset($value[0])) {
                    $first = $value[0];
                    if (is_string($first)) return filter_var($first, FILTER_VALIDATE_URL) ? $first : asset('storage/' . ltrim($first, '/'));
                    if (is_array($first)) $value = $first;
                }
                $path = $value['url'] ?? $value['path'] ?? $value['image_url'] ?? null;
                return $path ? (filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . ltrim($path, '/'))) : $fallback;
            }
            $value = trim((string) $value);
            if ($value === '') return $fallback;
            if (filter_var($value, FILTER_VALIDATE_URL)) return $value;
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $resolveAsset($decoded, $fallback);
            return asset('storage/' . ltrim($value, '/'));
        };

        $logo = $resolveAsset($club->logo ?? null);
        $leagueLogo = $resolveAsset($club->league?->logo ?? null);
        $heroImageUrl = $resolveAsset($club->background_image ?? $club->hero_image ?? $branding['background_image'] ?? $branding['hero_image'] ?? null, asset('images/PLYRCARD-SITE.jpg'));
        $clubContent = trim((string) ($club->landing_page_content ?? ''));
        $address = $contact['address'] ?? trim(collect([$club->city, $club->state])->filter()->implode(', '));
        $phone = $contact['phone'] ?? null;
        $email = $contact['email'] ?? null;
        $mapsUrl = $contact['maps_url'] ?? $contact['google_maps_url'] ?? null;
        $coach = $clubCoaches->first() ?? [];
        $coachName = $coach['name'] ?? $coach['full_name'] ?? 'Coach info';

        $teamGender = function ($team) {
            $settings = is_array($team->team_settings ?? null) ? $team->team_settings : [];
            $raw = strtolower((string) ($settings['gender'] ?? $settings['division_gender'] ?? $team->club?->league?->gender ?? ''));
            $name = strtolower((string) $team->name);
            if (str_contains($raw, 'female') || str_contains($raw, 'women') || str_contains($raw, 'girl') || str_contains($name, 'women') || str_contains($name, 'girl') || str_contains($name, 'female')) return 'girls';
            return 'boys';
        };

        $boysTeams = collect($teams ?? [])->filter(fn ($team) => $teamGender($team) === 'boys')->values();
        $girlsTeams = collect($teams ?? [])->filter(fn ($team) => $teamGender($team) === 'girls')->values();
        $teamCount = collect($teams ?? [])->count();
        $coachSession = $coachCheckIn ?? session('coach_checkin');
        $savedPlayers = collect($savedPlayers ?? session('coach_saved_players', []))->filter(fn ($saved) => (int) ($saved['club_id'] ?? 0) === (int) $club->id)->unique('player_id')->values();
        $clubFacts = collect([
            ['icon' => 'fa-shield-halved', 'label' => 'Teams', 'value' => $teamCount],
            ['icon' => 'fa-trophy', 'label' => 'League', 'value' => $club->league?->name ?: 'TBD'],
            ['icon' => 'fa-location-dot', 'label' => 'Location', 'value' => $address ?: 'TBD'],
        ]);

        if ($coachName && $coachName !== 'Coach info') {
            $clubFacts->push(['icon' => 'fa-user-tie', 'label' => 'Coach', 'value' => $coachName]);
        }

    @endphp

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|inter:400,500,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        :root{
            --brand: {{ $primary }};
            --brand-readable: {{ $readablePrimary }};
            --brand-on: {{ $onPrimary }};
            --secondary: {{ $secondary }};
            --accent: {{ $accent }};
            --heading: "{{ $headingFont }}", "Antonio", sans-serif;
            --body: "{{ $bodyFont }}", "Inter", sans-serif;
            --bg:#050506;
            --panel:#0b0b0d;
            --line:rgba(255,255,255,.105);
            --text:#f6f7fb;
            --muted:rgba(246,247,251,.68);
            --soft:rgba(255,255,255,.045);
        }
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{margin:0;background:var(--bg);color:var(--text);font-family:var(--body);overflow-x:hidden}
        a{color:inherit;text-decoration:none}
        img{display:block;max-width:100%}
        .site{min-height:100vh;background:radial-gradient(circle at 78% -8%, color-mix(in srgb, var(--brand) 18%, transparent), transparent 30%), #050506}
        .wrap{width:min(1180px, calc(100% - 32px));margin:0 auto}
        .nav{height:58px;display:flex;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);animation:fadeDown .55s ease both}
        .nav-brand{display:flex;align-items:center;gap:10px;min-width:0}
        .nav-brand img{width:30px;height:30px;object-fit:contain}
        .nav-brand span{font-family:var(--heading);font-size:13px;font-weight:900;letter-spacing:.18em;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .plyrcard-brand{gap:0;line-height:1;letter-spacing:-.02em}
        .plyrcard-brand span{font-family:var(--heading);font-size:24px;font-weight:900;letter-spacing:-.045em;overflow:visible;text-overflow:clip}
        .plyrcard-brand .plyr-word{color:#fff}
        .plyrcard-brand .card-word{color:#ff6a00;margin-left:0}
        .nav-actions{display:flex;align-items:center;gap:12px}
        .nav-link{font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:var(--muted)}
        .nav-link:hover{color:#fff}
        .coach-btn{height:38px;border:0;border-left:2px solid var(--brand-readable);background:color-mix(in srgb, var(--brand) 26%, #060708);color:#fff;padding:0 16px;font-family:var(--heading);font-size:12px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;display:inline-flex;align-items:center;gap:8px;cursor:pointer}
        .coach-btn:hover{filter:brightness(1.12)}
        .hero{position:relative;isolation:isolate;min-height:clamp(560px, 68vh, 720px);display:grid;align-items:end;overflow:hidden;border-bottom:1px solid var(--line);background:#050506}
        .hero:before{content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;background:linear-gradient(90deg, color-mix(in srgb, var(--brand) 42%, transparent) 0%, transparent 42%, color-mix(in srgb, var(--brand) 20%, transparent) 100%);mix-blend-mode:screen;opacity:.62}
        .hero-bg{position:absolute;inset:0;z-index:-2;background:url("{{ $heroImageUrl }}") center/cover no-repeat;filter:saturate(1.14) contrast(1.04) brightness(.93)}
        .hero-bg:after{content:"";position:absolute;inset:0;background:
            linear-gradient(90deg, color-mix(in srgb, var(--brand) 64%, rgba(0,0,0,.28)) 0%, color-mix(in srgb, var(--brand) 34%, rgba(0,0,0,.18)) 38%, rgba(0,0,0,.18) 62%, color-mix(in srgb, var(--brand) 42%, rgba(0,0,0,.28)) 100%),
            linear-gradient(180deg, rgba(0,0,0,.10) 0%, rgba(0,0,0,.12) 44%, rgba(0,0,0,.46) 100%),
            radial-gradient(circle at 28% 48%, color-mix(in srgb, var(--brand) 48%, transparent), transparent 39%)}
        .hero-inner{display:grid;grid-template-columns:minmax(0, .95fr) minmax(320px, .46fr);gap:32px;align-items:end;padding:58px 0 30px}
        .hero-main{max-width:760px;animation:fadeUp .7s ease both .08s}
        .identity{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:16px;margin-bottom:18px;max-width:760px}
        .identity-logo{width:66px;height:66px;object-fit:contain;flex:0 0 auto;filter:drop-shadow(0 10px 18px rgba(0,0,0,.28))}
        .identity-league{width:60px;height:60px;object-fit:contain;margin-left:0;flex:0 0 auto;filter:drop-shadow(0 10px 18px rgba(0,0,0,.28))}
        .club-title-stack{min-width:0;display:grid;gap:7px;max-width:min(600px,100%)}
        .club-label{font-family:var(--heading);font-size:11px;line-height:1;letter-spacing:.24em;text-transform:uppercase;font-weight:900;color:var(--brand-readable);text-shadow:0 8px 18px rgba(0,0,0,.35)}
        .club-name{font-family:var(--heading);font-size:clamp(40px, 4.8vw, 66px);line-height:.92;letter-spacing:.006em;text-transform:uppercase;font-weight:900;text-wrap:balance;text-shadow:0 16px 38px rgba(0,0,0,.42);max-width:640px}
        .club-copy{margin:14px 0 0;max-width:660px;color:rgba(255,255,255,.88);font-size:14px;line-height:1.5;font-weight:800;text-shadow:0 8px 20px rgba(0,0,0,.32)}
        .hero-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}.hero-actions .coach-btn{height:42px}
        .hero-side{display:grid;gap:1px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.12);animation:fadeUp .7s ease both .18s}
        .fact{display:grid;grid-template-columns:36px 1fr;gap:12px;align-items:center;background:rgba(5,5,6,.72);padding:16px 18px;backdrop-filter:blur(16px)}
        .fact i{color:var(--brand-readable);font-size:17px;text-align:center}.fact span{display:block;color:var(--muted);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;margin-bottom:4px}.fact strong{display:block;font-family:var(--heading);font-size:18px;line-height:1.04;text-transform:uppercase;letter-spacing:.02em}
        .section{padding:42px 0;border-bottom:1px solid var(--line)}
        .section-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:18px;animation:fadeUp .55s ease both}
        .eyebrow{font-family:var(--heading);font-size:11px;font-weight:900;letter-spacing:.24em;text-transform:uppercase;color:var(--brand-readable);margin-bottom:6px}.section-title{font-family:var(--heading);font-size:clamp(30px, 4vw, 54px);line-height:.92;text-transform:uppercase;font-weight:900;letter-spacing:.02em}
        .team-switch{display:flex;gap:1px;border:1px solid var(--line);background:var(--line)}.team-tab{border:0;background:#0d0d10;color:var(--muted);height:38px;padding:0 18px;font-family:var(--heading);font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;cursor:pointer}.team-tab.is-active{background:var(--brand);color:var(--brand-on)}
        .teams-panel{display:none}.teams-panel.is-active{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;animation:fadeUp .45s ease both}.team-card{position:relative;min-height:160px;display:flex;flex-direction:column;justify-content:flex-end;gap:10px;padding:18px;background:#101014;border:1px solid var(--line);overflow:hidden}.team-card:before{content:"";position:absolute;inset:0;background:linear-gradient(135deg, color-mix(in srgb, var(--brand) 22%, transparent), transparent 58%);opacity:.75}.team-card > *{position:relative;z-index:2}.team-card:hover{border-color:color-mix(in srgb, var(--brand-readable) 70%, white 0%);transform:translateY(-2px)}.team-card-name{font-family:var(--heading);font-size:24px;line-height:1;text-transform:uppercase;font-weight:900}.team-card-copy{color:var(--muted);font-size:12px;font-weight:800}.empty{padding:26px;border:1px solid var(--line);background:#0d0d10;color:var(--muted);font-weight:800;text-align:center;grid-column:1/-1}
        .saved-strip{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.saved-pill{border:1px solid var(--line);background:var(--soft);padding:8px 10px;font-size:11px;font-weight:850;color:var(--muted)}
        .footer{padding:28px 0;color:var(--muted);font-size:12px;font-weight:750}.footer-grid{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap}.footer a{color:#fff}
        .modal{position:fixed;inset:0;z-index:2000;display:none;background:rgba(0,0,0,.72);backdrop-filter:blur(14px);padding:18px;align-items:center;justify-content:center}.modal.is-open{display:flex}.modal-card{width:min(460px,100%);background:#08080a;border:1px solid var(--line);box-shadow:0 28px 80px rgba(0,0,0,.52);animation:popIn .22s ease both}.modal-head{height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;border-bottom:1px solid var(--line)}.modal-title{font-family:var(--heading);font-size:18px;text-transform:uppercase;font-weight:900;letter-spacing:.1em}.modal-close{border:0;background:rgba(255,255,255,.08);color:#fff;width:34px;height:34px;cursor:pointer}.modal-body{padding:16px}.coach-status{border-left:2px solid var(--brand-readable);padding:10px 12px;margin-bottom:12px;background:rgba(255,255,255,.045);font-size:12px;color:var(--muted)}.coach-form{display:grid;gap:10px}.coach-form label{display:grid;gap:6px;color:var(--muted);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.coach-form input{height:42px;border:1px solid var(--line);background:#0d0d10;color:#fff;padding:0 12px;font:inherit}.coach-submit{height:44px;border:0;background:var(--brand);color:var(--brand-on);font-family:var(--heading);font-size:13px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;cursor:pointer}.coach-out{height:40px;border:1px solid var(--line);background:transparent;color:#fff;font-family:var(--heading);font-size:12px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;width:100%;margin-top:10px}
        @keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}@keyframes fadeDown{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:none}}@keyframes popIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
        @media (max-width:900px){.wrap{width:100%}.nav{padding:0 16px}.nav-link{display:none}.hero{min-height:clamp(540px, 68vh, 650px);align-items:end}.hero-inner{grid-template-columns:1fr;gap:16px;padding:56px 22px 0}.identity{grid-template-columns:54px minmax(0,1fr) 54px;gap:13px;margin-bottom:12px;align-items:center}.identity-logo{width:50px;height:50px}.identity-league{width:50px;height:50px}.club-label{font-size:9px;letter-spacing:.22em}.club-name{font-size:clamp(30px, 8.8vw, 40px);line-height:.94;max-width:100%;letter-spacing:.004em}.club-copy{font-size:13px;margin-top:12px;line-height:1.45}.hero-actions{display:none}.hero-side{border-left:0;border-right:0;margin:26px -22px 0}
            .hero-side.facts-count-1{grid-template-columns:1fr}
            .hero-side.facts-count-2{grid-template-columns:repeat(2,minmax(0,1fr))}
            .hero-side.facts-count-3{grid-template-columns:repeat(3,minmax(0,1fr))}
            .hero-side.facts-count-4{grid-template-columns:repeat(2,minmax(0,1fr))}.fact{display:block;padding:13px 14px}.fact i{font-size:14px;margin-bottom:10px}.fact span{font-size:8px}.fact strong{font-size:13px}.section{padding:34px 18px}.section-head{display:block}.team-switch{margin-top:14px}.teams-panel.is-active{grid-template-columns:1fr}.team-card{min-height:128px}.footer{padding:24px 18px}.nav-actions .coach-btn{padding:0 12px;font-size:11px}}
        @media (min-width:901px){.site{padding-bottom:28px}.hero{margin:0 auto}.section{animation:fadeUp .7s ease both}.club-page-frame{width:min(1180px, calc(100% - 32px));margin:0 auto}.hero .wrap{width:min(1180px, calc(100% - 32px))}}
    

        /* Requested refinement: logo row, title row, full-width tabs, larger team names */
        .identity.identity-stacked{
            display:grid;
            grid-template-columns:1fr;
            gap:18px;
            max-width:780px;
            margin-bottom:0;
        }
        .club-logo-row{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            width:min(360px,100%);
        }
        .identity-stacked .identity-logo,
        .identity-stacked .identity-league{
            width:68px;
            height:68px;
        }
        .identity-stacked .club-title-stack{
            gap:9px;
        }
        .identity-stacked .club-name{
            font-size:clamp(44px,5.3vw,72px);
            line-height:.9;
            max-width:720px;
        }
        .section-head{
            display:block;
        }
        .team-switch{
            width:100%;
            margin-top:20px;
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
        }
        .team-tab{
            width:100%;
            height:46px;
            font-size:14px;
        }
        .team-card-name{
            font-size:clamp(32px,3.8vw,48px);
            letter-spacing:.025em;
        }
        .team-card{
            min-height:178px;
        }
        @media (max-width:900px){
            .hero{min-height:clamp(500px,64vh,620px)}
            .hero-inner{padding-top:48px;gap:20px}
            .identity.identity-stacked{gap:14px}
            .club-logo-row{width:100%}
            .identity-stacked .identity-logo,
            .identity-stacked .identity-league{width:54px;height:54px}
            .identity-stacked .club-name{font-size:clamp(34px,10vw,46px);line-height:.9}
            .team-tab{height:42px;font-size:12px}
            .team-card{min-height:116px}
            .team-card-name{font-size:34px}
        }


        /* Final spacing/logo refinement */
        .hero-inner{
            align-items:center;
            padding:42px 0 34px;
            min-height:clamp(520px, 64vh, 660px);
        }
        .hero-main{
            position:relative;
            width:100%;
            max-width:820px;
            padding-top:92px;
        }
        .identity.identity-stacked{
            position:relative;
            gap:20px;
            max-width:820px;
        }
        .club-logo-row{
            position:absolute;
            top:0;
            left:0;
            right:0;
            width:100%;
            display:flex;
            align-items:center;
            justify-content:space-between;
            pointer-events:none;
        }
        .identity-stacked .identity-logo,
        .identity-stacked .identity-league{
            width:74px;
            height:74px;
            margin:0;
        }
        .club-title-stack{
            width:min(650px, calc(100% - 168px));
            margin:0 auto;
            text-align:left;
        }
        .identity-stacked .club-name{
            font-size:clamp(42px,4.8vw,68px);
            line-height:.9;
        }
        .club-copy{
            width:min(650px, calc(100% - 168px));
            margin:14px auto 0;
        }
        @media (max-width:900px){
            .hero{min-height:clamp(500px,64vh,620px)}
            .hero-inner{min-height:clamp(500px,64vh,620px);align-items:center;padding:28px 22px 22px}
            .hero-main{padding-top:76px}
            .club-logo-row{top:0}
            .identity-stacked .identity-logo,
            .identity-stacked .identity-league{width:54px;height:54px}
            .club-title-stack{width:calc(100% - 122px);margin:0 auto;text-align:left}
            .identity-stacked .club-name{font-size:clamp(32px,9vw,43px);line-height:.91}
            .club-copy{width:calc(100% - 122px);margin:12px auto 0;font-size:12.5px;line-height:1.42}
            .hero-side{margin:22px -22px 0}
        }
        @media (max-width:420px){
            .club-title-stack,.club-copy{width:calc(100% - 104px)}
            .identity-stacked .identity-logo,
            .identity-stacked .identity-league{width:46px;height:46px}
            .identity-stacked .club-name{font-size:clamp(29px,8.7vw,38px)}
        }


        /* Revision: normal logo row + separate title row, pull hero content up and use space */
        .hero{
            min-height:clamp(500px, 60vh, 620px) !important;
            align-items:center !important;
        }
        .hero-inner{
            display:grid !important;
            grid-template-columns:minmax(0, 1fr) minmax(280px, 360px) !important;
            gap:34px !important;
            align-items:center !important;
            min-height:clamp(440px, 54vh, 560px) !important;
            padding:34px 0 28px !important;
        }
        .hero-main{
            max-width:760px !important;
            width:100% !important;
            padding-top:0 !important;
        }
        .identity.identity-stacked{
            position:relative !important;
            display:grid !important;
            grid-template-columns:1fr !important;
            gap:18px !important;
            max-width:760px !important;
            margin:0 !important;
        }
        .club-logo-row{
            position:relative !important;
            top:auto !important;
            left:auto !important;
            right:auto !important;
            width:100% !important;
            display:flex !important;
            align-items:center !important;
            justify-content:space-between !important;
            gap:18px !important;
            pointer-events:none !important;
            margin:0 0 2px !important;
        }
        .identity-stacked .identity-logo,
        .identity-stacked .identity-league{
            width:70px !important;
            height:70px !important;
            margin:0 !important;
        }
        .club-title-stack{
            width:100% !important;
            max-width:680px !important;
            margin:0 !important;
            text-align:left !important;
        }
        .identity-stacked .club-name{
            font-size:clamp(42px, 4.65vw, 64px) !important;
            line-height:.91 !important;
            max-width:680px !important;
        }
        .club-copy{
            width:100% !important;
            max-width:640px !important;
            margin:14px 0 0 !important;
        }
        .hero-side{
            align-self:stretch !important;
            min-height:0 !important;
        }
        .hero-side.facts-count-4{grid-template-columns:1fr 1fr !important;}
        .hero-side.facts-count-3{grid-template-columns:1fr !important;}
        .fact{min-height:0 !important;}
        @media (max-width:900px){
            .hero{min-height:455px !important;}
            .hero-inner{
                grid-template-columns:1fr !important;
                gap:20px !important;
                min-height:455px !important;
                padding:32px 22px 0 !important;
                align-content:center !important;
            }
            .identity.identity-stacked{gap:14px !important;}
            .club-logo-row{margin-bottom:0 !important;}
            .identity-stacked .identity-logo,
            .identity-stacked .identity-league{width:54px !important;height:54px !important;}
            .club-title-stack{width:100% !important;max-width:100% !important;}
            .identity-stacked .club-name{font-size:clamp(34px, 9.2vw, 44px) !important;line-height:.92 !important;}
            .club-copy{display:none !important;}
            .hero-side{margin:18px -22px 0 !important;align-self:auto !important;}
            .hero-side.facts-count-4{grid-template-columns:repeat(2, minmax(0,1fr)) !important;}
            .hero-side.facts-count-3{grid-template-columns:repeat(3, minmax(0,1fr)) !important;}
            .fact{padding:12px 14px !important;}
        }
        @media (max-width:420px){
            .hero{min-height:440px !important;}
            .hero-inner{min-height:440px !important;padding:28px 18px 0 !important;}
            .identity-stacked .identity-logo,
            .identity-stacked .identity-league{width:48px !important;height:48px !important;}
            .identity-stacked .club-name{font-size:clamp(31px, 8.5vw, 39px) !important;}
        }

    </style>
</head>
<body>
    <main class="site">
        <div class="club-page-frame">
            <nav class="nav">
                <a class="nav-brand plyrcard-brand" href="{{ url('/') }}" aria-label="PlyrCard home">
                    <span class="plyr-word">PLYR</span><span class="card-word">CARD</span>
                </a>
                <div class="nav-actions">
                    <a class="nav-link" href="#teams">Teams</a>
                    @if($email)<a class="nav-link" href="mailto:{{ $email }}">Contact</a>@endif
                    <button class="coach-btn {{ $coachSession ? '' : 'is-checkin' }}" type="button" data-open-coach><i class="fa-solid {{ $coachSession ? 'fa-user-tie' : 'fa-right-to-bracket' }}"></i> {{ $coachSession ? 'Coach Info' : 'Coach Check-In' }}</button>
                </div>
            </nav>
        </div>

        <section class="hero">
            <div class="hero-bg"></div>
            <div class="wrap hero-inner">
                <div class="hero-main">
                    <div class="identity identity-stacked">
                        <div class="club-logo-row">
                            @if($logo)<img class="identity-logo" src="{{ $logo }}" alt="{{ $club->name }} logo">@endif
                            @if($leagueLogo)<img class="identity-league" src="{{ $leagueLogo }}" alt="{{ $club->league?->name }} logo">@endif
                        </div>
                        <div class="club-title-stack">
                            <div class="club-label">Sports Club</div>
                            <h1 class="club-name">{{ $club->name }}</h1>
                        </div>
                    </div>
                    @if($clubContent)
                        <div class="club-copy">{!! nl2br(e($clubContent)) !!}</div>
                    @endif
                </div>

                <aside class="hero-side facts-count-{{ $clubFacts->count() }}" aria-label="Club information">
                    @foreach($clubFacts as $fact)
                        <div class="fact">
                            <i class="fa-solid {{ $fact['icon'] }}"></i>
                            <div>
                                <span>{{ $fact['label'] }}</span>
                                <strong>{{ $fact['value'] }}</strong>
                            </div>
                        </div>
                    @endforeach
                </aside>
            </div>
        </section>

        <section class="section" id="teams">
            <div class="wrap">
                <div class="section-head">
                    <div><div class="eyebrow">Club Teams</div><div class="section-title">Teams</div></div>
                    <div class="team-switch" role="tablist">
                        <button class="team-tab is-active" type="button" data-team-tab="boys">Boys</button>
                        <button class="team-tab" type="button" data-team-tab="girls">Girls</button>
                    </div>
                </div>

                <div class="teams-panel is-active" data-team-panel="boys">
                    @forelse($boysTeams as $team)
                        <a class="team-card" href="{{ $team->landingUrl() ?: '#' }}">
                            <div class="team-card-name">{{ $team->name }}</div>
                            <div class="team-card-copy">Open team <i class="fa-solid fa-arrow-right"></i></div>
                        </a>
                    @empty
                        <div class="empty">Boys teams will appear here once published.</div>
                    @endforelse
                </div>

                <div class="teams-panel" data-team-panel="girls">
                    @forelse($girlsTeams as $team)
                        <a class="team-card" href="{{ $team->landingUrl() ?: '#' }}">
                            <div class="team-card-name">{{ $team->name }}</div>
                            <div class="team-card-copy">Open team <i class="fa-solid fa-arrow-right"></i></div>
                        </a>
                    @empty
                        <div class="empty">Girls teams will appear here once published.</div>
                    @endforelse
                </div>

                @if($savedPlayers->isNotEmpty())
                    <div class="saved-strip">
                        @foreach($savedPlayers as $saved)
                            <span class="saved-pill">Saved: {{ $saved['player_name'] ?? 'Player' }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <footer class="footer">
            <div class="wrap footer-grid">
                <div>© {{ now()->year }} {{ $club->name }}. Powered by PlyrCard.</div>
                <div>
                    @if($phone)<a href="tel:{{ preg_replace('/\D+/', '', $phone) }}">{{ $phone }}</a>@endif
                    @if($phone && $email)<span> / </span>@endif
                    @if($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@endif
                </div>
            </div>
        </footer>
    </main>

    <div class="modal" id="coachModal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="coachModalTitle">
            <div class="modal-head"><div class="modal-title" id="coachModalTitle">{{ $coachSession ? 'Coach Info' : 'Coach Check-In' }}</div><button class="modal-close" type="button" data-close-coach><i class="fa-solid fa-xmark"></i></button></div>
            <div class="modal-body">
                @if($coachSession)
                    <div class="coach-status">Checked in as <strong>{{ $coachSession['name'] ?? 'Coach' }}</strong>{{ filled($coachSession['school'] ?? null) ? ' / ' . $coachSession['school'] : '' }}.</div>
                    <form method="POST" action="{{ route('clubs.coach-checkout', ['clubSlug' => $club->landing_page_slug]) }}">@csrf<button class="coach-out" type="submit">Check Out</button></form>
                @else
                    <form class="coach-form" method="POST" action="{{ route('clubs.coach-checkin', ['clubSlug' => $club->landing_page_slug]) }}">
                        @csrf
                        <label>School<input name="school" type="text" required></label>
                        <label>Name<input name="name" type="text" required></label>
                        <label>Title<input name="title" type="text" placeholder="Head Coach, Scout, Director"></label>
                        <label>Email<input name="email" type="email" required></label>
                        <button class="coach-submit" type="submit">Continue</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const modal = document.getElementById('coachModal');
            document.querySelectorAll('[data-open-coach]').forEach(btn => btn.addEventListener('click', () => { modal?.classList.add('is-open'); modal?.setAttribute('aria-hidden','false'); }));
            document.querySelectorAll('[data-close-coach]').forEach(btn => btn.addEventListener('click', () => { modal?.classList.remove('is-open'); modal?.setAttribute('aria-hidden','true'); }));
            modal?.addEventListener('click', e => { if(e.target === modal){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }});
            document.querySelectorAll('[data-team-tab]').forEach(tab => tab.addEventListener('click', function(){
                const target = this.dataset.teamTab;
                document.querySelectorAll('[data-team-tab]').forEach(t => t.classList.toggle('is-active', t === this));
                document.querySelectorAll('[data-team-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.teamPanel === target));
            }));
            document.addEventListener('keydown', e => { if(e.key === 'Escape'){ modal?.classList.remove('is-open'); modal?.setAttribute('aria-hidden','true'); }});
        });
    </script>
</body>
</html>