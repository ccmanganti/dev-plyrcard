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
            ['icon' => 'fa-trophy', 'logo' => $leagueLogo, 'label' => 'League', 'value' => $club->league?->name ?: 'TBD'],
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
        .nav{height:58px;display:flex;align-items:center;justify-content:space-between;gap:16px;animation:fadeDown .55s ease both}
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
        .hero{position:relative;isolation:isolate;min-height:clamp(560px, 68vh, 720px);display:grid;align-items:end;overflow:hidden;background:#050506}
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
        .teams-panel{display:none}.teams-panel.is-active{display:grid;grid-template-columns:1fr;gap:14px;animation:fadeUp .45s ease both}
        .team-card{position:relative;min-height:138px;display:grid;grid-template-columns:minmax(0,1fr) 156px;align-items:center;gap:16px;padding:18px 16px;background:linear-gradient(120deg, rgba(255,255,255,.05), rgba(255,255,255,.02)), #0b0b0e;border:1px solid var(--line);overflow:hidden;text-decoration:none;color:#fff;transition:transform .18s ease,border-color .18s ease,background-color .18s ease}
        .team-card:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 92% 40%, color-mix(in srgb, var(--brand) 26%, transparent), transparent 38%),linear-gradient(90deg, color-mix(in srgb, var(--brand) 18%, transparent), transparent 58%);opacity:.95;pointer-events:none}
        .team-card:after{content:"";position:absolute;left:0;right:0;bottom:0;height:2px;background:linear-gradient(90deg,var(--brand-readable),transparent);opacity:.72}
        .team-card > *{position:relative;z-index:2}
        .team-card:hover{border-color:color-mix(in srgb, var(--brand-readable) 70%, white 0%);transform:translateY(-2px);background-color:#111217}
        .team-card-main{display:grid;grid-template-columns:44px minmax(0,1fr);gap:12px;align-items:center;min-width:0}
        .team-card-logo{width:44px;height:44px;object-fit:contain}
        .team-card-logo-fallback{width:44px;height:44px;display:grid;place-items:center;color:var(--brand-readable);font-size:26px}
        .team-card-meta{min-width:0}
        .team-card-name{font-family:var(--heading);font-size:clamp(28px,5vw,48px);line-height:.9;text-transform:uppercase;font-weight:900;letter-spacing:.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .team-card-copy{margin-top:8px;color:var(--muted);font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;line-height:1.35}
        .team-card-copy strong{color:#fff;font-weight:900}
        .team-card-player-stack{position:relative;height:104px;display:flex;align-items:center;justify-content:flex-end;isolation:isolate}
        .team-card-player{position:absolute;top:50%;right:0;width:72px;height:100px;border-radius:12px 12px 16px 16px;background:#16202a center/cover no-repeat;border:1px solid rgba(255,255,255,.24);box-shadow:0 14px 26px rgba(0,0,0,.40);transform-origin:center;animation:floatJourney 5.6s ease-in-out infinite}
        .team-card-player:after{content:"";position:absolute;inset:0;border-radius:inherit;background:linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,0) 32%, rgba(0,0,0,.08) 100%)}
        .team-card-player.is-1{right:68px;transform:translateY(-44%) rotate(-10deg);z-index:1;animation-delay:.3s;opacity:.92}
        .team-card-player.is-2{right:36px;transform:translateY(-50%) rotate(-2deg) scale(1.03);z-index:3;animation-delay:.8s}
        .team-card-player.is-3{right:0;transform:translateY(-44%) rotate(9deg);z-index:2;animation-delay:1.2s;opacity:.95}
        .team-card-player.is-single{right:18px;transform:translateY(-50%) rotate(-1deg);width:78px;height:108px;z-index:3}
        .team-card-player-tag{display:none!important;position:absolute;left:8px;right:8px;bottom:8px;padding:4px 6px;border-radius:8px;background:rgba(0,0,0,.54);backdrop-filter:blur(10px);font-size:8px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#fff}
        .team-card-fallback-logos{height:92px;display:flex;align-items:center;justify-content:flex-end;isolation:isolate}
        .team-card-fallback-logos .team-card-tile{width:54px;height:74px;margin-left:-18px;border-radius:9px 9px 12px 12px;background:linear-gradient(160deg,#c9c9c9,#565b65);border:1px solid rgba(255,255,255,.30);box-shadow:0 12px 24px rgba(0,0,0,.38);display:grid;place-items:center;transform:skewY(-2deg)}
        .team-card-fallback-logos .team-card-tile img{max-width:76%;max-height:76%;object-fit:contain}
        .team-card-fallback-logos .team-card-tile.is-gold{width:62px;height:86px;z-index:3;background:linear-gradient(160deg,#f6d46d,#b98220 70%,#7d4c12);transform:translateY(-4px) skewY(-2deg)}
        .team-card-fallback-logos .team-card-tile.is-bronze{background:linear-gradient(160deg,#d29061,#78402d)}
        .team-card-fallback-logos .team-card-tile.is-empty i{font-size:22px;color:rgba(255,255,255,.72)}
        .empty{padding:26px;border:1px solid var(--line);background:#0d0d10;color:var(--muted);font-weight:800;text-align:center;grid-column:1/-1}
        @keyframes floatJourney{0%,100%{translate:0 0}50%{translate:0 -6px}}
        .saved-strip{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.saved-pill{border:1px solid var(--line);background:var(--soft);padding:8px 10px;font-size:11px;font-weight:850;color:var(--muted)}
        .footer{padding:28px 0;color:var(--muted);font-size:12px;font-weight:750}.footer-grid{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap}.footer a{color:#fff}
        .modal{position:fixed;inset:0;z-index:2000;display:none;background:rgba(0,0,0,.72);backdrop-filter:blur(14px);padding:18px;align-items:center;justify-content:center}.modal.is-open{display:flex}.modal-card{width:min(460px,100%);background:#08080a;border:1px solid var(--line);box-shadow:0 28px 80px rgba(0,0,0,.52);animation:popIn .22s ease both}.modal-head{height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;border-bottom:1px solid var(--line)}.modal-title{font-family:var(--heading);font-size:18px;text-transform:uppercase;font-weight:900;letter-spacing:.1em}.modal-close{border:0;background:rgba(255,255,255,.08);color:#fff;width:34px;height:34px;cursor:pointer}.modal-body{padding:16px}.coach-status{border-left:2px solid var(--brand-readable);padding:10px 12px;margin-bottom:12px;background:rgba(255,255,255,.045);font-size:12px;color:var(--muted)}.coach-form{display:grid;gap:10px}.coach-form label{display:grid;gap:6px;color:var(--muted);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.coach-form input{height:42px;border:1px solid var(--line);background:#0d0d10;color:#fff;padding:0 12px;font:inherit}.coach-submit{height:44px;border:0;background:var(--brand);color:var(--brand-on);font-family:var(--heading);font-size:13px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;cursor:pointer}.coach-out{height:40px;border:1px solid var(--line);background:transparent;color:#fff;font-family:var(--heading);font-size:12px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;width:100%;margin-top:10px}
        @keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}@keyframes fadeDown{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:none}}@keyframes popIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
        @media (max-width:900px){.wrap{width:100%}.nav{padding:0 16px}.nav-link{display:none}.hero{min-height:clamp(540px, 68vh, 650px);align-items:end}.hero-inner{grid-template-columns:1fr;gap:16px;padding:56px 22px 0}.identity{grid-template-columns:54px minmax(0,1fr) 54px;gap:13px;margin-bottom:12px;align-items:center}.identity-logo{width:50px;height:50px}.identity-league{width:50px;height:50px}.club-label{font-size:9px;letter-spacing:.22em}.club-name{font-size:clamp(30px, 8.8vw, 40px);line-height:.94;max-width:100%;letter-spacing:.004em}.club-copy{font-size:13px;margin-top:12px;line-height:1.45}.hero-actions{display:none}.hero-side{border-left:0;border-right:0;margin:26px -22px 0}
            .hero-side.facts-count-1{grid-template-columns:1fr}
            .hero-side.facts-count-2{grid-template-columns:repeat(2,minmax(0,1fr))}
            .hero-side.facts-count-3{grid-template-columns:repeat(3,minmax(0,1fr))}
            .hero-side.facts-count-4{grid-template-columns:repeat(2,minmax(0,1fr))}.fact{display:block;padding:13px 14px}.fact i{font-size:14px;margin-bottom:10px}.fact span{font-size:8px}.fact strong{font-size:13px}.section{padding:34px 18px}.section-head{display:block}.team-switch{margin-top:14px}.teams-panel.is-active{grid-template-columns:1fr}.team-card{min-height:120px;grid-template-columns:minmax(0,1fr) 126px;padding:15px 13px}.team-card-player-stack{height:88px}.team-card-player{width:60px;height:84px}.team-card-player.is-1{right:52px}.team-card-player.is-2{right:26px}.team-card-player.is-single{width:64px;height:90px;right:14px}.team-card-fallback-logos{height:82px}.team-card-fallback-logos .team-card-tile{width:48px;height:66px}.team-card-fallback-logos .team-card-tile.is-gold{width:56px;height:78px}.footer{padding:24px 18px}.nav-actions .coach-btn{padding:0 12px;font-size:11px}}
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
            .team-card{min-height:104px;grid-template-columns:minmax(0,1fr) 102px}
            .team-card-name{font-size:32px}.team-card-main{grid-template-columns:38px minmax(0,1fr);gap:10px}.team-card-logo,.team-card-logo-fallback{width:38px;height:38px}.team-card-player-stack{height:74px}.team-card-player{width:50px;height:70px}.team-card-player.is-1{right:42px}.team-card-player.is-2{right:20px}.team-card-player.is-single{width:54px;height:76px;right:12px}.team-card-fallback-logos{height:78px}.team-card-fallback-logos .team-card-tile{width:44px;height:62px}.team-card-fallback-logos .team-card-tile.is-gold{width:52px;height:74px}
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

    

        /* FINAL FIX: logos top-left/top-right of HERO, text is its own row, no wasted hero space */
        .hero{
            min-height:clamp(430px, 54vh, 560px) !important;
            display:block !important;
            position:relative !important;
            overflow:hidden !important;
        }
        .hero-inner{
            position:relative !important;
            display:grid !important;
            grid-template-columns:1fr !important;
            align-items:start !important;
            align-content:start !important;
            min-height:clamp(430px, 54vh, 560px) !important;
            padding:96px 22px 0 !important;
            gap:0 !important;
        }
        .hero-main{
            position:relative !important;
            width:100% !important;
            max-width:760px !important;
            padding:0 !important;
            margin:0 !important;
        }
        .identity.identity-stacked{
            position:static !important;
            display:block !important;
            max-width:760px !important;
            margin:0 !important;
        }
        .club-logo-row{
            position:absolute !important;
            top:28px !important;
            left:22px !important;
            right:22px !important;
            width:auto !important;
            height:64px !important;
            display:flex !important;
            align-items:flex-start !important;
            justify-content:space-between !important;
            gap:18px !important;
            margin:0 !important;
            pointer-events:none !important;
            z-index:4 !important;
        }
        .identity-stacked .identity-logo,
        .identity-stacked .identity-league{
            width:58px !important;
            height:58px !important;
            object-fit:contain !important;
            margin:0 !important;
        }
        .club-title-stack{
            display:block !important;
            width:100% !important;
            max-width:620px !important;
            margin:0 !important;
            text-align:left !important;
            padding:0 !important;
        }
        .club-label{
            font-size:10px !important;
            letter-spacing:.22em !important;
            margin-bottom:8px !important;
            color:rgba(255,255,255,.74) !important;
        }
        .identity-stacked .club-name{
            font-size:clamp(38px,4.2vw,60px) !important;
            line-height:.92 !important;
            max-width:620px !important;
            letter-spacing:.015em !important;
        }
        .club-copy{display:none !important;}
        .hero-side{
            margin-top:28px !important;
            align-self:auto !important;
        }
        @media(min-width:901px){
            .hero-inner{
                padding-top:110px !important;
                padding-bottom:0 !important;
            }
            .club-logo-row{
                top:34px !important;
                left:0 !important;
                right:0 !important;
                height:78px !important;
            }
            .identity-stacked .identity-logo,
            .identity-stacked .identity-league{
                width:74px !important;
                height:74px !important;
            }
            .hero-main{max-width:760px !important;}
            .hero-side{margin-top:34px !important;}
        }
        @media(max-width:900px){
            .hero{min-height:430px !important;}
            .hero-inner{
                min-height:430px !important;
                padding:92px 20px 0 !important;
            }
            .club-logo-row{
                top:28px !important;
                left:20px !important;
                right:20px !important;
                height:54px !important;
            }
            .identity-stacked .identity-logo,
            .identity-stacked .identity-league{
                width:50px !important;
                height:50px !important;
            }
            .identity-stacked .club-name{
                font-size:clamp(31px,8vw,39px) !important;
                line-height:.94 !important;
                max-width:76% !important;
            }
            .hero-side{
                margin:24px -20px 0 !important;
            }
        }
        @media(max-width:420px){
            .hero{min-height:414px !important;}
            .hero-inner{min-height:414px !important;padding:86px 18px 0 !important;}
            .club-logo-row{top:26px !important;left:18px !important;right:18px !important;}
            .identity-stacked .identity-logo,
            .identity-stacked .identity-league{width:46px !important;height:46px !important;}
            .identity-stacked .club-name{font-size:clamp(29px,7.8vw,36px) !important;}
        }

    

        /* === FINAL CLUB HERO FIX: logos in true corners, text in separate row, no wasted space === */
        .hero{
            min-height:clamp(390px, 56vh, 560px) !important;
            align-items:center !important;
        }
        .hero-logo-corners{
            position:absolute !important;
            top:clamp(22px, 3vw, 34px) !important;
            left:clamp(22px, 4vw, 52px) !important;
            right:clamp(22px, 4vw, 52px) !important;
            z-index:5 !important;
            display:flex !important;
            align-items:flex-start !important;
            justify-content:space-between !important;
            pointer-events:none !important;
        }
        .hero-logo-corners .identity-logo,
        .hero-logo-corners .identity-league{
            width:clamp(48px, 6vw, 76px) !important;
            height:clamp(48px, 6vw, 76px) !important;
            object-fit:contain !important;
            filter:drop-shadow(0 12px 22px rgba(0,0,0,.38)) !important;
        }
        .hero-inner{
            grid-template-columns:1fr !important;
            min-height:clamp(330px, 48vh, 500px) !important;
            padding:clamp(78px, 10vh, 112px) 0 0 !important;
            align-items:end !important;
            gap:0 !important;
        }
        .hero-main{
            width:min(680px,100%) !important;
            max-width:680px !important;
            margin:0 !important;
            padding-bottom:clamp(24px, 4vh, 42px) !important;
        }
        .identity.identity-stacked{
            display:block !important;
            max-width:680px !important;
        }
        .identity-stacked .club-title-stack{
            display:grid !important;
            gap:8px !important;
            max-width:620px !important;
        }
        .identity-stacked .club-name{
            font-size:clamp(36px, 5.4vw, 70px) !important;
            line-height:.9 !important;
            letter-spacing:.01em !important;
            max-width:620px !important;
        }
        .club-label{font-size:11px !important;color:rgba(255,255,255,.72) !important;}
        .club-copy{display:none !important;}
        .hero-side{
            width:100% !important;
            margin:0 !important;
            display:grid !important;
            grid-template-columns:repeat(4,minmax(0,1fr)) !important;
            border-left:0 !important;
            border-right:0 !important;
        }
        .hero-side.facts-count-1{grid-template-columns:1fr !important;}
        .hero-side.facts-count-2{grid-template-columns:repeat(2,minmax(0,1fr)) !important;}
        .hero-side.facts-count-3{grid-template-columns:repeat(3,minmax(0,1fr)) !important;}
        .hero-side.facts-count-4{grid-template-columns:repeat(4,minmax(0,1fr)) !important;}
        .fact{min-height:92px !important;padding:16px 18px !important;}
        .fact strong{font-size:16px !important;}
        .fact span{font-size:9px !important;}
        @media(max-width:900px){
            .hero{min-height:430px !important;}
            .hero-logo-corners{top:36px !important;left:26px !important;right:26px !important;}
            .hero-logo-corners .identity-logo,
            .hero-logo-corners .identity-league{width:50px !important;height:50px !important;}
            .hero-inner{min-height:360px !important;padding:104px 28px 0 !important;}
            .hero-main{padding-bottom:34px !important;}
            .identity-stacked .club-name{font-size:clamp(34px, 8.8vw, 43px) !important;line-height:.9 !important;max-width:320px !important;}
            .club-label{font-size:9px !important;letter-spacing:.22em !important;}
            .hero-side.facts-count-4{grid-template-columns:repeat(2,minmax(0,1fr)) !important;}
            .hero-side.facts-count-3{grid-template-columns:repeat(3,minmax(0,1fr)) !important;}
            .fact{min-height:86px !important;padding:14px 16px !important;}
            .fact strong{font-size:13px !important;}
            .section{padding-top:34px !important;}
        }
        @media(max-width:420px){
            .hero{min-height:408px !important;}
            .hero-logo-corners{top:30px !important;left:22px !important;right:22px !important;}
            .hero-logo-corners .identity-logo,
            .hero-logo-corners .identity-league{width:44px !important;height:44px !important;}
            .hero-inner{min-height:342px !important;padding:92px 22px 0 !important;}
            .identity-stacked .club-name{font-size:34px !important;max-width:290px !important;}
            .hero-main{padding-bottom:30px !important;}
        }

    
        /* Final full-width club team list override */
        #teams.section{
            padding-left:0 !important;
            padding-right:0 !important;
        }
        #teams .wrap{
            width:100% !important;
            max-width:none !important;
            margin:0 !important;
        }
        #teams .section-head{
            padding-left:clamp(22px,4vw,52px) !important;
            padding-right:clamp(22px,4vw,52px) !important;
        }
        #teams .team-switch{
            width:100% !important;
            margin-left:0 !important;
            margin-right:0 !important;
            border-left:0 !important;
            border-right:0 !important;
        }
        #teams .team-tab{
            flex:1 1 50% !important;
        }
        #teams .teams-panel{
            width:100% !important;
        }
        #teams .team-card{
            width:100% !important;
            border-left:0 !important;
            border-right:0 !important;
            border-radius:0 !important;
            margin:0 !important;
            padding-left:clamp(22px,4vw,52px) !important;
            padding-right:clamp(22px,4vw,52px) !important;
        }
        #teams .empty{
            border-left:0 !important;
            border-right:0 !important;
            border-radius:0 !important;
            margin:0 !important;
        }
        @media (max-width:900px){
            #teams.section{
                padding-top:34px !important;
                padding-bottom:28px !important;
            }
            #teams .section-head{
                padding-left:20px !important;
                padding-right:20px !important;
                margin-bottom:16px !important;
            }
            #teams .team-switch{
                margin-top:16px !important;
            }
            #teams .team-card{
                min-height:116px !important;
                padding-left:20px !important;
                padding-right:20px !important;
                grid-template-columns:minmax(0,1fr) 128px !important;
            }
        }
        @media (max-width:420px){
            #teams .section-head{
                padding-left:16px !important;
                padding-right:16px !important;
            }
            #teams .team-card{
                padding-left:16px !important;
                padding-right:16px !important;
                grid-template-columns:minmax(0,1fr) 104px !important;
            }
        }

    

        /* FINAL FIX: make the club info/facts box full-bleed across the hero */
        .hero .hero-side{
            width:100vw !important;
            max-width:none !important;
            margin-left:calc(50% - 50vw) !important;
            margin-right:calc(50% - 50vw) !important;
            border-left:0 !important;
            border-right:0 !important;
            border-radius:0 !important;
        }
        @media (min-width:901px){
            .hero .hero-side{
                width:100% !important;
                margin-left:0 !important;
                margin-right:0 !important;
            }
        }
        @media (max-width:900px){
            .hero .hero-side{
                width:100vw !important;
                margin-left:calc(50% - 50vw) !important;
                margin-right:calc(50% - 50vw) !important;
            }
        }


        /* Pull-up navigation, matching Locker Room drawer motion and card grid. */
        .coach-action-drawer{position:fixed;inset:0;z-index:3000;pointer-events:none;color:#fff;font-family:var(--heading)}
        .coach-action-drawer.is-open{pointer-events:auto}
        .coach-drawer-scrim{position:absolute;inset:0;background:rgba(0,0,0,.46);backdrop-filter:blur(4px);opacity:0;transition:opacity .22s ease}
        .coach-action-drawer.is-open .coach-drawer-scrim{opacity:1}
        .coach-drawer-panel{position:absolute;left:0;right:0;bottom:0;width:100%;max-height:min(78dvh,580px);background:#050505;border-radius:16px 16px 0 0;box-shadow:0 -18px 46px rgba(0,0,0,.50);transform:translateY(100%);transition:transform .28s cubic-bezier(.2,.8,.2,1);overflow:hidden}
        .coach-action-drawer.is-open .coach-drawer-panel{transform:translateY(0)}
        .coach-drawer-handle{position:absolute;top:8px;left:50%;width:52px;height:5px;border-radius:999px;background:rgba(0,0,0,.22);transform:translateX(-50%);z-index:2}
        .coach-drawer-head{min-height:54px;padding:16px 12px 10px;display:flex;align-items:center;justify-content:space-between;gap:8px;background:#fff;color:#050505;border-radius:16px 16px 0 0}
        .coach-drawer-title{margin:0;font-size:15px;line-height:1;font-weight:950;text-transform:uppercase;letter-spacing:.02em;color:#050505}
        .coach-drawer-close{width:30px;height:30px;border:0;background:transparent;color:#050505;display:inline-flex;align-items:center;justify-content:center;font-size:19px;cursor:pointer}
        .coach-drawer-body{padding:10px 10px calc(78px + env(safe-area-inset-bottom,0px));max-height:calc(min(78dvh,580px) - 54px);overflow:auto;background:#050505}
        .coach-drawer-group-title{display:block;margin:0 0 6px;color:rgba(255,255,255,.62);font-size:11px;line-height:1;font-weight:900;text-transform:uppercase;letter-spacing:.06em}
        .coach-drawer-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}
        .coach-drawer-card{min-height:62px;padding:7px 5px 6px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;border:0;border-radius:8px;background:#fff;color:#050505;box-shadow:0 4px 10px rgba(0,0,0,.24);text-align:center;text-decoration:none;cursor:pointer;font:inherit;font-weight:900;text-transform:uppercase;letter-spacing:.015em}
        .coach-drawer-card.is-accent{background:#ff5c35;color:#fff}.coach-drawer-card.is-dark{background:#121318;color:#fff;border:1px solid rgba(255,255,255,.08)}
        .coach-drawer-card i{font-size:15px;color:currentColor}.coach-drawer-card span{display:block;font-size:10.5px;line-height:1;font-weight:900;color:currentColor}.coach-drawer-note{margin:10px 0 0;color:rgba(255,255,255,.62);font-size:12px;line-height:1.3;font-weight:700}
        @media(max-width:420px){.coach-drawer-grid{gap:6px}.coach-drawer-card{min-height:56px}.coach-drawer-card span{font-size:9.5px}}


        /* Final pull-up nav revision: check-in lives inside the drawer. */
        .coach-drawer-form{display:grid!important;gap:8px!important;margin-top:8px!important;}
        .coach-drawer-form label{display:grid!important;gap:5px!important;color:rgba(255,255,255,.62)!important;font-size:10px!important;font-weight:900!important;letter-spacing:.055em!important;text-transform:uppercase!important;}
        .coach-drawer-form input{height:38px!important;border:1px solid rgba(255,255,255,.12)!important;background:#101116!important;color:#fff!important;padding:0 11px!important;font:inherit!important;font-family:var(--heading)!important;font-size:13px!important;font-weight:800!important;outline:none!important;}
        .coach-drawer-form input:focus{border-color:#ff5c35!important;box-shadow:0 0 0 3px rgba(255,92,53,.15)!important;}
        .coach-drawer-submit{height:40px!important;border:0!important;background:#ff5c35!important;color:#fff!important;font-family:var(--heading)!important;font-size:13px!important;font-weight:950!important;letter-spacing:.09em!important;text-transform:uppercase!important;cursor:pointer!important;}
        .coach-drawer-form-row{display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important;}
        .coach-out-inline{margin:0!important;display:contents!important;}
        .coach-out-inline button{font:inherit!important;}
        @media(max-width:420px){.coach-drawer-form-row{grid-template-columns:1fr!important}}



        /* FINAL NAV FIX: drawer is opened from the Locker Room-style bottom tab, not the header. */
        .coach-drawer-tab{position:fixed!important;right:0!important;bottom:0!important;width:196px!important;height:54px!important;padding:0 12px 0 42px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;border:0!important;border-radius:0!important;background:#ff5c35!important;color:#fff!important;font-family:var(--heading)!important;font-size:18px!important;font-weight:900!important;line-height:1!important;text-transform:uppercase!important;cursor:pointer!important;pointer-events:auto!important;clip-path:polygon(32px 0,100% 0,100% 100%,0 100%)!important;z-index:2!important;}
        .coach-drawer-tab i{font-size:13px!important;transition:transform .25s ease!important;}
        .coach-action-drawer.is-open .coach-drawer-tab i{transform:rotate(180deg)!important;}
        @media(max-width:390px){.coach-drawer-tab{width:184px!important;height:50px!important;padding-left:38px!important;font-size:16px!important;clip-path:polygon(29px 0,100% 0,100% 100%,0 100%)!important}}

    

        /* FINAL REVISION: club hero facts use 2-row layout, full-width, icon spans both rows. */
        .hero .hero-side{
            display:grid !important;
            gap:0 !important;
            background:rgba(255,255,255,.12) !important;
            border-top:1px solid rgba(255,255,255,.16) !important;
            border-bottom:1px solid rgba(255,255,255,.16) !important;
            border-left:0 !important;
            border-right:0 !important;
        }
        .hero .hero-side.facts-count-1{grid-template-columns:1fr !important;}
        .hero .hero-side.facts-count-2{grid-template-columns:repeat(2,minmax(0,1fr)) !important;}
        .hero .hero-side.facts-count-3{grid-template-columns:repeat(3,minmax(0,1fr)) !important;}
        .hero .hero-side.facts-count-4{grid-template-columns:repeat(4,minmax(0,1fr)) !important;}
        .hero .fact{
            min-height:92px !important;
            display:grid !important;
            grid-template-columns:54px minmax(0,1fr) !important;
            grid-template-rows:auto auto !important;
            column-gap:14px !important;
            align-items:center !important;
            padding:18px 24px !important;
            border-left:1px solid rgba(255,255,255,.16) !important;
            background:rgba(5,5,6,.52) !important;
            backdrop-filter:blur(16px) !important;
        }
        .hero .fact:first-child{border-left:0 !important;}
        .hero .fact i,
        .hero .fact .fact-logo{
            grid-column:1 !important;
            grid-row:1 / span 2 !important;
            width:34px !important;
            height:34px !important;
            object-fit:contain !important;
            align-self:center !important;
            justify-self:center !important;
            color:rgba(220,232,239,.82) !important;
            font-size:22px !important;
            margin:0 !important;
        }
        .hero .fact span{
            grid-column:2 !important;
            grid-row:1 !important;
            display:block !important;
            margin:0 0 5px !important;
            color:rgba(220,232,239,.70) !important;
            font-size:10px !important;
            font-weight:900 !important;
            letter-spacing:.14em !important;
            text-transform:uppercase !important;
            line-height:1 !important;
            align-self:end !important;
        }
        .hero .fact strong{
            grid-column:2 !important;
            grid-row:2 !important;
            display:block !important;
            color:#fff !important;
            font-family:var(--heading) !important;
            font-size:20px !important;
            font-weight:900 !important;
            text-transform:uppercase !important;
            line-height:1.05 !important;
            align-self:start !important;
        }
        @media(max-width:900px){
            .hero .hero-side{grid-template-columns:1fr !important;margin-left:-22px !important;margin-right:-22px !important;}
            .hero .fact{min-height:76px !important;grid-template-columns:48px minmax(0,1fr) !important;padding:14px 20px !important;border-left:0 !important;border-top:1px solid rgba(255,255,255,.14) !important;}
            .hero .fact:first-child{border-top:0 !important;}
            .hero .fact i,.hero .fact .fact-logo{width:30px !important;height:30px !important;font-size:18px !important;}
            .hero .fact strong{font-size:18px !important;}
        }

        /* Global loading feedback for club page navigation and form actions. */
        .page-loading-overlay{position:fixed;inset:0;z-index:200000;display:grid;place-items:center;background:rgba(0,0,0,.62);backdrop-filter:blur(5px);opacity:0;pointer-events:none;transition:opacity .18s ease;}
        .page-loading-overlay.is-visible{opacity:1;pointer-events:auto;}
        .page-loader-card{display:grid;gap:10px;place-items:center;color:#fff;font-family:var(--heading);font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;}
        .page-loader-spinner{width:38px;height:38px;border-radius:999px;border:3px solid rgba(255,255,255,.24);border-top-color:#ff5c35;animation:plyrSpin .72s linear infinite;}
        .is-loading{position:relative;opacity:.72!important;pointer-events:none!important;}
        .is-loading:after{content:"";width:14px;height:14px;border-radius:999px;border:2px solid currentColor;border-top-color:transparent;display:inline-block;margin-left:8px;vertical-align:-2px;animation:plyrSpin .7s linear infinite;}
        @keyframes plyrSpin{to{transform:rotate(360deg)}}

    

        /* FINAL TIGHTEN: keep hero fact label/value close together */
        .hero .fact{
            grid-template-columns:44px minmax(0,1fr) !important;
            grid-template-rows:min-content min-content !important;
            column-gap:12px !important;
            row-gap:3px !important;
            align-content:center !important;
            align-items:center !important;
        }
        .hero .fact i,
        .hero .fact .fact-logo{
            grid-row:1 / 3 !important;
            align-self:center !important;
        }
        .hero .fact span{
            grid-column:2 !important;
            grid-row:1 !important;
            align-self:end !important;
            margin:0 !important;
            line-height:1 !important;
        }
        .hero .fact strong{
            grid-column:2 !important;
            grid-row:2 !important;
            align-self:start !important;
            margin:0 !important;
            line-height:1.02 !important;
        }
        @media(max-width:900px){
            .hero .fact{
                grid-template-columns:38px minmax(0,1fr) !important;
                row-gap:3px !important;
                align-content:center !important;
            }
        }

    

        /* FINAL CLUB COACH DRAWER FIX: 4 equal buttons in one row, no broken form spacing. */
        .coach-drawer-grid{
            display:grid!important;
            grid-template-columns:repeat(4,minmax(0,1fr))!important;
            gap:6px!important;
            align-items:stretch!important;
            width:100%!important;
        }
        .coach-drawer-card-form{
            margin:0!important;
            padding:0!important;
            display:contents!important;
        }
        .coach-drawer-card{
            width:100%!important;
            min-width:0!important;
            min-height:56px!important;
            padding:6px 4px!important;
            border-radius:7px!important;
            display:flex!important;
            flex-direction:column!important;
            align-items:center!important;
            justify-content:center!important;
            gap:5px!important;
            text-align:center!important;
            line-height:1!important;
        }
        .coach-drawer-card i{
            font-size:14px!important;
            line-height:1!important;
        }
        .coach-drawer-card span{
            display:block!important;
            width:100%!important;
            max-width:100%!important;
            overflow:hidden!important;
            text-overflow:ellipsis!important;
            color:currentColor!important;
            font-size:9.5px!important;
            line-height:1.02!important;
            font-weight:900!important;
            letter-spacing:.01em!important;
            white-space:normal!important;
        }
        .coach-drawer-card.is-dark{
            background:#111217!important;
            color:#fff!important;
            border:1px solid rgba(255,255,255,.10)!important;
        }
        .coach-drawer-note{
            margin-top:12px!important;
            font-size:12px!important;
            line-height:1.25!important;
            max-width:100%!important;
        }
        @media(max-width:390px){
            .coach-drawer-grid{gap:5px!important;}
            .coach-drawer-card{min-height:52px!important;padding:5px 3px!important;}
            .coach-drawer-card i{font-size:13px!important;}
            .coach-drawer-card span{font-size:8.8px!important;}
        }


        /* Final fix: no highlighted Remove buttons in coach/watchlist actions. */
        .coach-drawer-card.is-remove,
        .coach-drawer-card-form button.coach-drawer-card.is-remove{
            background:#fff!important;
            color:#050505!important;
            border:0!important;
        }
        .coach-drawer-card.is-remove i,
        .coach-drawer-card-form button.coach-drawer-card.is-remove i{
            display:block!important;
            color:currentColor!important;
            font-size:13px!important;
            margin:0 0 2px!important;
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
                </div>
            </nav>
        </div>

        <section class="hero">
            <div class="hero-bg"></div>
            <div class="hero-logo-corners" aria-hidden="true">
                @if($logo)<img class="identity-logo" src="{{ $logo }}" alt="{{ $club->name }} logo">@endif
                @if($leagueLogo)<img class="identity-league" src="{{ $leagueLogo }}" alt="{{ $club->league?->name }} logo">@endif
            </div>
            <div class="wrap hero-inner">
                <div class="hero-main">
                    <div class="identity identity-stacked">
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
                            @if(filled($fact['logo'] ?? null))
                                <img class="fact-logo" src="{{ $fact['logo'] }}" alt="{{ $fact['label'] }} logo">
                            @else
                                <i class="fa-solid {{ $fact['icon'] }}"></i>
                            @endif
                            <span>{{ $fact['label'] }}</span>
                            <strong>{{ $fact['value'] }}</strong>
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
                            @php
                                $teamLogo = $resolveAsset($team->logo ?? null);
                                $teamSettings = is_array($team->team_settings ?? null) ? $team->team_settings : [];
                                $teamSub = $teamSettings['subtitle'] ?? $teamSettings['division'] ?? $teamSettings['age_group'] ?? 'Team';
                            @endphp
                            <div class="team-card-main">
                                @if($teamLogo)
                                    <img class="team-card-logo" src="{{ $teamLogo }}" alt="{{ $team->name }} logo">
                                @elseif($logo)
                                    <img class="team-card-logo" src="{{ $logo }}" alt="{{ $club->name }} logo">
                                @else
                                    <span class="team-card-logo-fallback"><i class="fa-solid fa-shield-halved"></i></span>
                                @endif
                                <div class="team-card-meta">
                                    <div class="team-card-name">{{ $team->name }}</div>
                                </div>
                            </div>
                            @php
                                $journeyCards = collect($teamJourneyCards[$team->id] ?? [])->shuffle()->take(3)->values();
                            @endphp
                            @if($journeyCards->isNotEmpty())
                                <div class="team-card-player-stack" aria-hidden="true">
                                    @foreach($journeyCards as $journeyIndex => $journeyCard)
                                        <span
                                            class="team-card-player {{ $journeyCards->count() === 1 ? 'is-single' : 'is-' . ($journeyIndex + 1) }}"
                                            style="background-image:url('{{ $resolveAsset($journeyCard['image'] ?? null) }}')"
                                        >
                                            <span class="team-card-player-tag">{{ $journeyCard['name'] ?? 'PlyrCard' }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <div class="team-card-fallback-logos" aria-hidden="true">
                                    <span class="team-card-tile">@if($logo)<img src="{{ $logo }}" alt="">@else<i class="fa-solid fa-user"></i>@endif</span>
                                    <span class="team-card-tile is-gold">@if($teamLogo)<img src="{{ $teamLogo }}" alt="">@elseif($logo)<img src="{{ $logo }}" alt="">@else<i class="fa-solid fa-users"></i>@endif</span>
                                    <span class="team-card-tile is-bronze">@if($leagueLogo)<img src="{{ $leagueLogo }}" alt="">@else<i class="fa-solid fa-trophy"></i>@endif</span>
                                </div>
                            @endif
                        </a>
                    @empty
                        <div class="empty">Boys teams will appear here once published.</div>
                    @endforelse
                </div>

                <div class="teams-panel" data-team-panel="girls">
                    @forelse($girlsTeams as $team)
                        <a class="team-card" href="{{ $team->landingUrl() ?: '#' }}">
                            @php
                                $teamLogo = $resolveAsset($team->logo ?? null);
                                $teamSettings = is_array($team->team_settings ?? null) ? $team->team_settings : [];
                                $teamSub = $teamSettings['subtitle'] ?? $teamSettings['division'] ?? $teamSettings['age_group'] ?? 'Team';
                            @endphp
                            <div class="team-card-main">
                                @if($teamLogo)
                                    <img class="team-card-logo" src="{{ $teamLogo }}" alt="{{ $team->name }} logo">
                                @elseif($logo)
                                    <img class="team-card-logo" src="{{ $logo }}" alt="{{ $club->name }} logo">
                                @else
                                    <span class="team-card-logo-fallback"><i class="fa-solid fa-shield-halved"></i></span>
                                @endif
                                <div class="team-card-meta">
                                    <div class="team-card-name">{{ $team->name }}</div>
                                </div>
                            </div>
                            @php
                                $journeyCards = collect($teamJourneyCards[$team->id] ?? [])->shuffle()->take(3)->values();
                            @endphp
                            @if($journeyCards->isNotEmpty())
                                <div class="team-card-player-stack" aria-hidden="true">
                                    @foreach($journeyCards as $journeyIndex => $journeyCard)
                                        <span
                                            class="team-card-player {{ $journeyCards->count() === 1 ? 'is-single' : 'is-' . ($journeyIndex + 1) }}"
                                            style="background-image:url('{{ $resolveAsset($journeyCard['image'] ?? null) }}')"
                                        >
                                            <span class="team-card-player-tag">{{ $journeyCard['name'] ?? 'PlyrCard' }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <div class="team-card-fallback-logos" aria-hidden="true">
                                    <span class="team-card-tile">@if($logo)<img src="{{ $logo }}" alt="">@else<i class="fa-solid fa-user"></i>@endif</span>
                                    <span class="team-card-tile is-gold">@if($teamLogo)<img src="{{ $teamLogo }}" alt="">@elseif($logo)<img src="{{ $logo }}" alt="">@else<i class="fa-solid fa-users"></i>@endif</span>
                                    <span class="team-card-tile is-bronze">@if($leagueLogo)<img src="{{ $leagueLogo }}" alt="">@else<i class="fa-solid fa-trophy"></i>@endif</span>
                                </div>
                            @endif
                        </a>
                    @empty
                        <div class="empty">Girls teams will appear here once published.</div>
                    @endforelse
                </div>

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

    
<div class="page-loading-overlay" id="pageLoadingOverlay" aria-hidden="true"><div class="page-loader-card"><span class="page-loader-spinner"></span><span>Loading</span></div></div>

<div class="coach-action-drawer" id="coachActionDrawer" aria-hidden="true">
        <div class="coach-drawer-scrim" data-close-actions></div>
        <section class="coach-drawer-panel" aria-label="Coach navigation">
            <div class="coach-drawer-handle" aria-hidden="true"></div>
            <div class="coach-drawer-head">
                <h2 class="coach-drawer-title">{{ $coachSession ? 'Coach Navigation' : 'Coach Check-In' }}</h2>
                <button class="coach-drawer-close" type="button" data-close-actions aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="coach-drawer-body">
                @if($coachSession)
                    <strong class="coach-drawer-group-title">{{ filled($coachSession['name'] ?? null) ? 'Hi ' . $coachSession['name'] : 'Coach Tools' }}</strong>
                    <div class="coach-drawer-grid">
                        <a class="coach-drawer-card is-accent" href="#teams" data-close-actions><i class="fa-solid fa-people-group"></i><span>View Teams</span></a>
                        <form class="coach-drawer-card-form" data-email-watchlist-form method="POST" action="{{ route('clubs.coach-email-watchlist', ['clubSlug' => $club->landing_page_slug]) }}">
                            @csrf
                            <button class="coach-drawer-card" type="submit"><i class="fa-solid fa-paper-plane"></i><span>Email Watchlist</span></button>
                        </form>
                        <button class="coach-drawer-card" type="button" data-open-coach data-close-actions><i class="fa-solid fa-user-tie"></i><span>Coach Info</span></button>
                        <form class="coach-out-inline coach-drawer-card-form" method="POST" action="{{ route('clubs.coach-checkout', ['clubSlug' => $club->landing_page_slug]) }}">
                            @csrf
                            <button class="coach-drawer-card is-dark" type="submit"><i class="fa-solid fa-right-from-bracket"></i><span>Check Out</span></button>
                        </form>
                    </div>
                    <p class="coach-drawer-note">Use the team pages to save players. Tap Email Watchlist here to send one email with every saved player to {{ $coachSession['email'] ?? 'your inbox' }}.</p>
                @else
                    <strong class="coach-drawer-group-title">Coach Check-In</strong>
                    <form class="coach-drawer-form" method="POST" action="{{ route('clubs.coach-checkin', ['clubSlug' => $club->landing_page_slug]) }}">
                        @csrf
                        <label>School<input name="school" type="text" required></label>
                        <div class="coach-drawer-form-row">
                            <label>Name<input name="name" type="text" required></label>
                            <label>Title<input name="title" type="text" placeholder="Scout"></label>
                        </div>
                        <label>Email<input name="email" type="email" required></label>
                        <button class="coach-drawer-submit" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Check In</button>
                    </form>
                    <p class="coach-drawer-note">Check in once to unlock profile saving and watchlist tools on team pages.</p>
                @endif
            </div>
        </section>
        <button class="coach-drawer-tab" type="button" data-open-actions>
            <i class="fa-solid {{ $coachSession ? 'fa-bookmark' : 'fa-chevron-up' }}"></i>
            <span>{{ $coachSession ? 'WATCHLIST' : 'CHECK IN' }}</span>
        </button>
    </div>

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


<style>
    .watchlist-toast{position:fixed;left:50%;bottom:74px;z-index:100002;display:flex;align-items:center;gap:9px;min-height:42px;max-width:calc(100vw - 32px);padding:0 14px;border-radius:999px;background:#fff;color:#050505;box-shadow:0 18px 48px rgba(0,0,0,.34);font-family:var(--heading);font-size:13px;font-weight:950;text-transform:uppercase;letter-spacing:.06em;opacity:0;transform:translate(-50%,10px);pointer-events:none;transition:opacity .2s ease,transform .2s ease}.watchlist-toast.is-visible{opacity:1;transform:translate(-50%,0)}.watchlist-toast i{color:#16a34a}.watchlist-toast.is-error i{color:#ff5c35}.coach-drawer-card.is-success{background:#16a34a!important;color:#fff!important}.coach-drawer-card.is-loading{opacity:.72!important;pointer-events:none!important}.coach-drawer-card.is-loading i{animation:spinLoader .75s linear infinite!important}@keyframes spinLoader{to{transform:rotate(360deg)}}
</style>
    <div class="watchlist-toast" id="watchlistToast" aria-live="polite"><i class="fa-solid fa-circle-check"></i><span>Email Sent</span></div>

    <script>
        document.addEventListener('DOMContentLoaded', function(){

    const pageLoadingOverlay = document.getElementById('pageLoadingOverlay');
    const showPageLoading = (label) => {
        if(!pageLoadingOverlay) return;
        const text = pageLoadingOverlay.querySelector('.page-loader-card span:last-child');
        if(text) text.textContent = label || 'Loading';
        pageLoadingOverlay.classList.add('is-visible');
        pageLoadingOverlay.setAttribute('aria-hidden','false');
    };
    document.querySelectorAll('a[href]').forEach(link => {
        link.addEventListener('click', function(e){
            const href = this.getAttribute('href') || '';
            if(!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('sms:') || this.target === '_blank' || this.hasAttribute('download')) return;
            showPageLoading('Loading');
        });
    });
    document.querySelectorAll('form').forEach(form => {
        if(form.hasAttribute('data-email-watchlist-form')) return;
        form.addEventListener('submit', function(){
            const button = form.querySelector('button[type="submit"]');
            button?.classList.add('is-loading');
            showPageLoading('Working');
        });
    });

            const modal = document.getElementById('coachModal');
            const coachActionDrawer = document.getElementById('coachActionDrawer');
            const openActions = () => { coachActionDrawer?.classList.add('is-open'); coachActionDrawer?.setAttribute('aria-hidden','false'); };
            const closeActions = () => { coachActionDrawer?.classList.remove('is-open'); coachActionDrawer?.setAttribute('aria-hidden','true'); };
            const showWatchlistToast = (message, isError = false) => {
                const toast = document.getElementById('watchlistToast');
                if(!toast) return;
                const span = toast.querySelector('span');
                const icon = toast.querySelector('i');
                if(span) span.textContent = message || 'Email Sent';
                if(icon) icon.className = isError ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check';
                toast.classList.toggle('is-error', !!isError);
                toast.classList.add('is-visible');
                clearTimeout(toast._hideTimer);
                toast._hideTimer = setTimeout(() => toast.classList.remove('is-visible'), 1800);
            };
            document.querySelectorAll('[data-open-actions]').forEach(btn => btn.addEventListener('click', openActions));
            document.querySelectorAll('[data-close-actions]').forEach(btn => btn.addEventListener('click', closeActions));
            document.querySelectorAll('[data-open-coach]').forEach(btn => btn.addEventListener('click', () => { closeActions(); modal?.classList.add('is-open'); modal?.setAttribute('aria-hidden','false'); }));
            document.querySelectorAll('[data-close-coach]').forEach(btn => btn.addEventListener('click', () => { modal?.classList.remove('is-open'); modal?.setAttribute('aria-hidden','true'); }));
            modal?.addEventListener('click', e => { if(e.target === modal){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }});
            document.querySelectorAll('[data-team-tab]').forEach(tab => tab.addEventListener('click', function(){
                const target = this.dataset.teamTab;
                document.querySelectorAll('[data-team-tab]').forEach(t => t.classList.toggle('is-active', t === this));
                document.querySelectorAll('[data-team-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.teamPanel === target));
            }));
            document.addEventListener('submit', async function(event){
                const form = event.target.closest('[data-email-watchlist-form]');
                if(!form) return;
                event.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                const originalHtml = button ? button.innerHTML : '';
                button?.classList.add('is-loading');
                if(button) button.innerHTML = '<i class="fa-solid fa-circle-notch"></i><span>Sending</span>';
                try{
                    const response = await fetch(form.action, {
                        method:'POST',
                        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
                        body:new FormData(form)
                    });
                    const data = await response.json().catch(() => ({}));
                    if(!response.ok || data.success === false){throw new Error(data.message || 'Email could not be sent.');}
                    button?.classList.remove('is-loading');
                    button?.classList.add('is-success');
                    if(button) button.innerHTML = '<i class="fa-solid fa-circle-check"></i><span>Email Sent</span>';
                    showWatchlistToast(data.message || 'Email Sent');
                    setTimeout(() => { button?.classList.remove('is-success'); if(button) button.innerHTML = originalHtml; }, 2200);
                }catch(error){
                    button?.classList.remove('is-loading');
                    if(button) button.innerHTML = originalHtml;
                    showWatchlistToast(error.message || 'Email could not be sent.', true);
                }
            });
            document.addEventListener('keydown', e => { if(e.key === 'Escape'){ closeActions(); modal?.classList.remove('is-open'); modal?.setAttribute('aria-hidden','true'); }});
        });
    </script>
</body>
</html>

{{-- FINAL PATCH: keep club hero info as clean 2x2 grid and hide player names on random PlyrCard art --}}
<style>
    .hero .hero-side{
        display:grid!important;
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        width:100%!important;
        max-width:100%!important;
        margin-left:0!important;
        margin-right:0!important;
        gap:1px!important;
        overflow:hidden!important;
        background:rgba(255,255,255,.12)!important;
        border-top:1px solid rgba(255,255,255,.15)!important;
        border-bottom:1px solid rgba(255,255,255,.15)!important;
        border-left:0!important;
        border-right:0!important;
    }
    .hero .hero-side.facts-count-1,
    .hero .hero-side.facts-count-2,
    .hero .hero-side.facts-count-3,
    .hero .hero-side.facts-count-4{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
    }
    .hero .fact{
        min-width:0!important;
        min-height:84px!important;
        display:grid!important;
        grid-template-columns:42px minmax(0,1fr)!important;
        grid-template-rows:auto auto!important;
        column-gap:12px!important;
        align-items:center!important;
        padding:14px 16px!important;
        background:rgba(5,5,6,.55)!important;
        border:0!important;
        box-shadow:none!important;
        overflow:hidden!important;
        text-align:left!important;
    }
    .hero .fact i,
    .hero .fact .fact-logo{
        grid-column:1!important;
        grid-row:1 / 3!important;
        width:30px!important;
        height:30px!important;
        font-size:19px!important;
        object-fit:contain!important;
        align-self:center!important;
        justify-self:center!important;
        margin:0!important;
        color:rgba(220,232,239,.82)!important;
    }
    .hero .fact span{
        grid-column:2!important;
        grid-row:1!important;
        min-width:0!important;
        margin:0 0 5px!important;
        padding:0!important;
        background:transparent!important;
        border:0!important;
        box-shadow:none!important;
        color:rgba(220,232,239,.70)!important;
        font-family:var(--heading)!important;
        font-size:9px!important;
        line-height:1!important;
        font-weight:900!important;
        letter-spacing:.13em!important;
        text-transform:uppercase!important;
        text-align:left!important;
        white-space:nowrap!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
    }
    .hero .fact strong{
        grid-column:2!important;
        grid-row:2!important;
        min-width:0!important;
        margin:0!important;
        padding:0!important;
        background:transparent!important;
        border:0!important;
        box-shadow:none!important;
        color:#fff!important;
        font-family:var(--heading)!important;
        font-size:17px!important;
        line-height:1.03!important;
        font-weight:900!important;
        letter-spacing:.02em!important;
        text-transform:uppercase!important;
        text-align:left!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
    }
    .team-card-player-tag{display:none!important;}
    .team-card-player:after{background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,0) 35%,rgba(0,0,0,.10))!important;}
    @media(max-width:900px){
        .hero .hero-side{
            grid-template-columns:repeat(2,minmax(0,1fr))!important;
            margin-left:-22px!important;
            margin-right:-22px!important;
            width:calc(100% + 44px)!important;
            max-width:calc(100% + 44px)!important;
        }
        .hero .hero-side.facts-count-1,
        .hero .hero-side.facts-count-2,
        .hero .hero-side.facts-count-3,
        .hero .hero-side.facts-count-4{
            grid-template-columns:repeat(2,minmax(0,1fr))!important;
        }
        .hero .fact{min-height:78px!important;grid-template-columns:38px minmax(0,1fr)!important;padding:13px 14px!important;}
        .hero .fact i,.hero .fact .fact-logo{width:28px!important;height:28px!important;font-size:17px!important;}
        .hero .fact span{font-size:8px!important;letter-spacing:.11em!important;}
        .hero .fact strong{font-size:15px!important;line-height:1.02!important;}
    }
    @media(max-width:420px){
        .hero .hero-side{margin-left:-18px!important;margin-right:-18px!important;width:calc(100% + 36px)!important;max-width:calc(100% + 36px)!important;}
        .hero .fact{min-height:76px!important;grid-template-columns:36px minmax(0,1fr)!important;padding:12px 12px!important;}
        .hero .fact strong{font-size:14px!important;}
    }
</style>


{{-- FINAL PATCH: team cards show only the team name, no duplicate club name or Team label. --}}
<style>
    .team-card-copy{display:none!important;}
    .team-card-name{font-size:clamp(34px,5.6vw,54px)!important;line-height:.88!important;}
    @media(max-width:900px){.team-card-name{font-size:40px!important;}}
</style>