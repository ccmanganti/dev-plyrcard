<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $team->name }} | {{ $club->name }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
        $teamBranding = is_array($team->branding ?? null) ? $team->branding : [];
        $clubBranding = is_array($club?->branding ?? null) ? $club->branding : [];
        $teamSettings = is_array($team->team_settings ?? null) ? $team->team_settings : [];
        $coaches = collect(is_array($team->coaching_staff ?? null) ? $team->coaching_staff : []);
        $headCoach = $coaches->first() ?? [];

        $primary = $teamBranding['primary_color'] ?? $clubBranding['primary_color'] ?? $club?->primary_color ?? '#00A3FF';
        $secondary = $teamBranding['secondary_color'] ?? $clubBranding['secondary_color'] ?? $club?->secondary_color ?? '#050505';
        $accent = $teamBranding['accent_color'] ?? $clubBranding['accent_color'] ?? $primary;
        $headingFont = $teamBranding['heading_font'] ?? $clubBranding['heading_font'] ?? 'Antonio';
        $bodyFont = $teamBranding['body_font'] ?? $clubBranding['body_font'] ?? 'Inter';

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

        $normalizeValue = function ($value, string $separator = ' | ') {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $value = $decoded;
            }
            if (is_array($value)) return collect($value)->filter()->map(fn ($item) => is_scalar($item) ? (string) $item : '')->filter()->implode($separator);
            return filled($value) ? (string) $value : '';
        };

        $formatPosition = function ($value) use ($normalizeValue) {
            $position = $normalizeValue($value, ' | ');
            if ($position === '') return 'PLYR';
            $map = [
                'goalkeeper' => 'GK', 'keeper' => 'GK', 'defender' => 'DEF', 'center_back' => 'CB', 'centre_back' => 'CB', 'left_back' => 'LB', 'right_back' => 'RB', 'full_back' => 'FB', 'wing_back' => 'WB',
                'midfielder' => 'MID', 'defensive_midfielder' => 'CDM', 'central_midfielder' => 'CM', 'attacking_midfielder' => 'CAM', 'wide_midfielder' => 'WM',
                'forward' => 'FWD', 'wide_forward' => 'WF', 'striker' => 'ST', 'winger' => 'WG', 'left_wing' => 'LW', 'right_wing' => 'RW',
                'point_guard' => 'PG', 'shooting_guard' => 'SG', 'small_forward' => 'SF', 'power_forward' => 'PF', 'center' => 'C',
            ];
            return collect(explode(' | ', str_replace([' / ', ','], ' | ', $position)))->filter()->map(function ($item) use ($map) {
                $key = str($item)->lower()->replace('&', 'and')->replace('-', '_')->replace(' ', '_')->toString();
                return $map[$key] ?? str($item)->replace('_', ' ')->upper()->toString();
            })->implode(' / ');
        };

        $formatDate = function ($value) {
            if (blank($value)) return '';
            try { return strtoupper(\Carbon\Carbon::parse($value)->format('M j, Y')); } catch (\Throwable $e) { return strtoupper((string) $value); }
        };

        $teamLogo = $resolveAsset($team->logo ?: $club?->logo);
        $clubLogo = $resolveAsset($club?->logo);
        $leagueLogo = $resolveAsset($club?->league?->logo);
        $heroImageUrl = $resolveAsset($team->background_image ?? $team->hero_image ?? $teamBranding['background_image'] ?? $teamBranding['hero_image'] ?? $club?->background_image ?? $club?->hero_image ?? null, asset('images/PLYRCARD-SITE.jpg'));
        $leagueName = $club?->league?->name ?? ($teamSettings['league'] ?? 'League');
        $coachName = $headCoach['name'] ?? $headCoach['full_name'] ?? 'TBA';
        $coachEmail = $headCoach['email'] ?? null;
        $coachPhone = $headCoach['phone'] ?? null;
        $teamIntro = trim((string) ($team->landing_page_content ?? $team->landing_page_intro ?? ''));
        $currentGenderSegment = request()->route('gender') ?? $team->landingGenderSegment();
        $coachSession = $coachCheckIn ?? session('coach_checkin');
        $coachButtonText = is_array($coachSession) && filled($coachSession['name'] ?? null) ? 'Hi ' . str($coachSession['name'])->before(' ')->title() : 'Coach Check-In';
        $coachButtonIcon = is_array($coachSession) && filled($coachSession['name'] ?? null) ? 'fa-user-tie' : 'fa-right-to-bracket';
        $savedPlayers = collect($savedPlayers ?? session('coach_saved_players', []))->filter(fn ($saved) => (int) ($saved['team_id'] ?? 0) === (int) $team->id)->unique('player_id')->values();
        $savedIds = $savedPlayers->pluck('player_id')->map(fn ($id) => (int) $id)->all();

        $playerWebsiteUrl = function ($player) {
            $website = $player->websites->first();
            if (! $website) return '';
            return filled($website->domain) ? 'https://' . preg_replace('/^https?:\/\//', '', $website->domain) : url('/' . ltrim($website->slug, '/'));
        };

        $isSubscribed = function ($player) {
            if (! method_exists($player, 'getRoleNames')) return false;
            $roles = $player->getRoleNames()->map(fn ($role) => strtolower(trim($role)));
            return $roles->contains('plyr plus') || $roles->contains('my journey');
        };

        $playerRows = collect($players ?? [])->map(function ($player, $index) use ($resolveAsset, $formatPosition, $normalizeValue, $formatDate, $playerWebsiteUrl, $isSubscribed, $club, $leagueLogo) {
            $name = trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')) ?: 'Player';
            $initial = strtoupper(substr(trim($player->first_name ?: $name), 0, 1));
            $subscribed = $isSubscribed($player);
            $cardImage = $subscribed ? $resolveAsset($player->plyrcard_image ?: null) : '';
            $portraitImage = $resolveAsset($player->player_image ?: $player->action_image ?: $player->youtube_thumbnail ?: $player->mobile_hero_image ?: null);
            $mobileHeroImage = $resolveAsset($player->mobile_hero_image ?: null);
            $mainImage = $resolveAsset($player->player_image ?: $player->action_image ?: $player->mobile_hero_image ?: $player->plyrcard_image ?: null);
            $clubLogo = $resolveAsset($player->club?->logo ?? $club?->logo ?? null);
            $playerLeagueLogo = $resolveAsset($player->league?->logo ?? $player->club?->league?->logo ?? $leagueLogo ?? null);
            $websiteUrl = $playerWebsiteUrl($player);
            $age = '';
            try { $age = $player->birth ? \Carbon\Carbon::parse($player->birth)->age : ''; } catch (\Throwable $e) { $age = ''; }
            $position = $formatPosition($player->position ?? '');
            $gpa = is_numeric($player->gpa ?? null) ? number_format((float) $player->gpa, 1, '.', '') : ($player->gpa ?? '');
            $first = strtoupper((string) ($player->first_name ?: 'PLAYER'));
            $last = strtoupper((string) ($player->last_name ?: ''));
            return [
                'id' => $player->id,
                'index' => $index,
                'name' => $name,
                'first_name' => $first,
                'last_name' => $last,
                'initial' => $initial,
                'jersey' => $player->jersey_number ?: '',
                'position' => $position,
                'position_full' => $normalizeValue($player->position, ', ') ?: 'Player',
                'card_image' => $cardImage,
                'portrait_image' => $portraitImage,
                'mobile_hero_image' => $mobileHeroImage,
                'main_image' => $mainImage,
                'website_url' => $websiteUrl,
                'age' => $age,
                'year' => $player->year ?: '',
                'gpa' => $gpa,
                'height' => $player->height ?: '',
                'weight' => $player->weight ?: '',
                'max_speed' => $player->max_speed ?: '',
                'dominant_foot' => $player->dominant_foot ? str((string) $player->dominant_foot)->replace('_', ' ')->upper()->toString() : '',
                'dob' => $formatDate($player->birth ?? ''),
                'city' => $player->city ?: '',
                'state' => $player->state ?: '',
                'school' => $player->school?->name ?? '',
                'club' => $player->club?->name ?? $club?->name ?? '',
                'league' => $player->league?->name ?? $player->club?->league?->name ?? $club?->league?->name ?? '',
                'club_logo' => $clubLogo,
                'league_logo' => $playerLeagueLogo,
                'coach' => $player->club_coach ?: '',
                'email' => $player->email ?: '',
                'personal_email' => $player->personal_email ?: '',
                'phone' => $player->phone ?: '',
                'parent' => $player->parent ?: '',
                'parent_email' => $player->parent_email ?: '',
                'parent_phone' => $player->parent_phone ?: '',
                'coach_email' => $player->club_coach_email ?: '',
                'coach_phone' => $player->club_coach_phone ?: '',
                'subscribed' => $subscribed,
            ];
        })->values();
    @endphp

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|inter:400,500,600,700,800,900|iceberg:400" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Luxurious+Script&display=swap');
        :root{--brand:{{ $primary }};--brand-readable:{{ $readablePrimary }};--brand-on:{{ $onPrimary }};--secondary:{{ $secondary }};--accent:{{ $accent }};--heading:"{{ $headingFont }}","Antonio",sans-serif;--body:"{{ $bodyFont }}","Inter",sans-serif;--bg:#050506;--panel:#0b0b0d;--line:rgba(255,255,255,.105);--text:#f6f7fb;--muted:rgba(246,247,251,.68);--soft:rgba(255,255,255,.045)}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--text);font-family:var(--body);overflow-x:hidden}a{color:inherit;text-decoration:none}img{display:block;max-width:100%}.site{position:relative;min-height:100vh;background:radial-gradient(circle at 78% -8%, color-mix(in srgb,var(--brand) 18%,transparent), transparent 30%),#050506}.frame{width:min(1180px,calc(100% - 32px));margin:0 auto}.wrap{width:min(1180px,calc(100% - 32px));margin:0 auto}.nav{height:58px;display:flex;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);animation:fadeDown .55s ease both}.nav-brand-stack{display:flex;align-items:center;gap:10px;min-width:0}.nav-back-mobile{display:none}.mobile-back-row{display:none}.page-back-mobile{display:none}.nav-brand{display:flex;align-items:center;gap:10px;min-width:0}.nav-brand img{width:30px;height:30px;object-fit:contain}.nav-brand span{font-family:var(--heading);font-size:13px;font-weight:900;letter-spacing:.18em;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.plyr-wordmark{display:inline-flex!important;align-items:baseline!important;gap:0!important;font-family:var(--heading)!important;font-weight:900!important;letter-spacing:-.035em!important;line-height:1!important}.plyr-wordmark b{color:#fff!important;font-style:normal!important;font-weight:900!important}.plyr-wordmark em{color:#ff7a1a!important;font-style:normal!important;font-weight:900!important;margin-left:0!important}.nav-actions{display:flex;align-items:center;gap:12px}.nav-link{font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:var(--muted)}.nav-link:hover{color:#fff}.btn{height:38px;border:0;border-left:2px solid var(--brand-readable);background:color-mix(in srgb,var(--brand) 26%,#060708);color:#fff;padding:0 16px;font-family:var(--heading);font-size:12px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;display:inline-flex;align-items:center;gap:8px;cursor:pointer}.btn:hover{filter:brightness(1.12)}
        .hero{position:relative;isolation:isolate;min-height:clamp(470px,64vh,700px);display:grid;align-items:end;overflow:hidden;border-bottom:1px solid var(--line)}.hero-bg{position:absolute;inset:0;z-index:-2;background:url("{{ $heroImageUrl }}") center/cover no-repeat;filter:saturate(1.08) contrast(1.04)}.hero-bg:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,color-mix(in srgb,var(--brand) 72%,rgba(0,0,0,.58)) 0%,color-mix(in srgb,var(--brand) 36%,rgba(0,0,0,.34)) 50%,color-mix(in srgb,var(--brand) 58%,rgba(0,0,0,.62)) 100%),linear-gradient(180deg,rgba(0,0,0,.12) 0%,rgba(0,0,0,.12) 42%,color-mix(in srgb,var(--brand) 40%,rgba(0,0,0,.70)) 100%)}.hero-inner{display:grid;grid-template-columns:minmax(0,.92fr) minmax(300px,.48fr);gap:34px;align-items:end;padding:64px 0 34px}.team-main{animation:fadeUp .65s ease both}.back{display:inline-flex;align-items:center;gap:8px;color:rgba(255,255,255,.78);font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;margin-bottom:18px}.identity{display:grid;grid-template-columns:76px minmax(0,1fr) 68px;align-items:center;gap:18px;margin-bottom:0;max-width:760px}.identity-logo{width:72px;height:72px;object-fit:contain}.identity-league{width:64px;height:64px;object-fit:contain;justify-self:end}.label{font-family:var(--heading);font-size:12px;line-height:1;letter-spacing:.22em;text-transform:uppercase;font-weight:900;color:rgba(255,255,255,.72);margin-bottom:8px}.team-name{font-family:var(--heading);font-size:clamp(48px,7vw,98px);line-height:.88;letter-spacing:.01em;text-transform:uppercase;font-weight:900;text-shadow:0 18px 40px rgba(0,0,0,.36)}.team-copy{display:none}.hero-actions{display:none}.team-facts{display:grid;gap:1px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.12);animation:fadeUp .65s ease both .12s}.fact{display:grid;grid-template-columns:36px 1fr;gap:12px;align-items:center;background:rgba(5,5,6,.58);padding:16px 18px;backdrop-filter:blur(16px)}.fact i{color:var(--brand-readable);font-size:17px;text-align:center}.fact span{display:block;color:var(--muted);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;margin-bottom:4px}.fact strong{display:block;font-family:var(--heading);font-size:18px;line-height:1.04;text-transform:uppercase;letter-spacing:.02em}
        .section{padding:42px 0;border-bottom:1px solid var(--line)}.section-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:18px}.eyebrow{font-family:var(--heading);font-size:11px;font-weight:900;letter-spacing:.24em;text-transform:uppercase;color:var(--brand-readable);margin-bottom:6px}.section-title{font-family:var(--heading);font-size:clamp(30px,4vw,54px);line-height:.92;text-transform:uppercase;font-weight:900;letter-spacing:.02em}.roster-tools{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:11px;font-weight:850;text-transform:uppercase;letter-spacing:.12em}.saved-button{height:38px;border:1px solid var(--line);background:#0d0d10;color:#fff;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 12px;font-family:var(--heading);font-size:11px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;cursor:pointer}.saved-button strong{color:var(--brand-readable)}.saved-list{display:grid;gap:8px}.saved-item{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 0;border-bottom:1px solid var(--line);color:#fff;font-size:13px;font-weight:850}.saved-item span{color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.08em}.roster{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.player-row{position:relative;width:100%;border:1px solid var(--line);background:#0d0d10;color:#fff;text-align:left;display:grid;grid-template-columns:68px 1fr auto;align-items:center;gap:14px;padding:10px;cursor:pointer;overflow:hidden}.player-row:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,color-mix(in srgb,var(--brand) 15%,transparent),transparent 50%);opacity:.65}.player-row>*{position:relative;z-index:2}.player-row:hover{border-color:var(--brand-readable);transform:translateY(-1px)}.avatar{width:58px;height:58px;object-fit:cover;border-radius:999px;background:rgba(255,255,255,.09);display:grid;place-items:center;font-family:var(--heading);font-size:22px;font-weight:900;color:#fff;overflow:hidden}.avatar.card-img{border-radius:7px;object-fit:cover;object-position:center top;background:#111;width:46px;height:62px}.player-name{font-family:var(--heading);font-size:22px;line-height:1;text-transform:uppercase;font-weight:900;letter-spacing:.04em}.player-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:7px;color:var(--muted);font-size:11px;font-weight:850}.player-num{font-family:var(--heading);font-size:28px;line-height:1;font-weight:900;color:var(--brand-readable);text-align:right}.player-save-mini{font-size:10px;color:var(--muted);margin-top:6px;font-weight:900;text-transform:uppercase}.empty{grid-column:1/-1;padding:28px;background:#0d0d10;border:1px solid var(--line);color:var(--muted);text-align:center;font-weight:850}
        .modal{position:fixed;inset:0;z-index:2000;display:none;background:rgba(0,0,0,.76);backdrop-filter:blur(14px);padding:18px;align-items:center;justify-content:center}.modal.is-open{display:flex}.coach-card{width:min(460px,100%);background:#08080a;border:1px solid var(--line);box-shadow:0 28px 80px rgba(0,0,0,.52);animation:popIn .22s ease both}.modal-head{height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;border-bottom:1px solid var(--line)}.modal-title{font-family:var(--heading);font-size:18px;text-transform:uppercase;font-weight:900;letter-spacing:.1em}.modal-close{border:0;background:rgba(255,255,255,.08);color:#fff;width:34px;height:34px;cursor:pointer}.modal-body{padding:16px}.coach-status{border-left:2px solid var(--brand-readable);padding:10px 12px;margin-bottom:12px;background:rgba(255,255,255,.045);font-size:12px;color:var(--muted)}.coach-form{display:grid;gap:10px}.coach-form label{display:grid;gap:6px;color:var(--muted);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.coach-form input{height:42px;border:1px solid var(--line);background:#0d0d10;color:#fff;padding:0 12px;font:inherit}.coach-submit,.coach-out{height:44px;border:0;background:var(--brand);color:var(--brand-on);font-family:var(--heading);font-size:13px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;cursor:pointer}.coach-out{width:100%;margin-top:10px;background:transparent;color:#fff;border:1px solid var(--line)}
        .player-overlay{position:fixed;inset:0;z-index:2100;display:none;background:rgba(0,0,0,.78);backdrop-filter:blur(16px)}.player-overlay.is-open{display:block}.player-panel{position:absolute;top:0;right:0;width:min(100%,520px);height:100%;background:#050506;box-shadow:-24px 0 70px rgba(0,0,0,.5);transform:translateX(100%);animation:panelIn .24s ease forwards;overflow:hidden}.player-panel-bar{position:relative;z-index:100;height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 12px;background:#050506;border-bottom:1px solid var(--line)}.player-panel-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:var(--heading);font-size:16px;text-transform:uppercase;font-weight:900;letter-spacing:.1em;display:flex;align-items:center;gap:8px}.player-panel-title i{color:var(--brand-readable)}.player-panel-btn{height:36px;border:0;background:rgba(255,255,255,.08);color:#fff;padding:0 12px;font-family:var(--heading);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}.player-dialog{position:relative;z-index:1;height:calc(100% - 56px);overflow:auto;background:#050506;padding:0 0 0}.player-nav-arrow{position:absolute;z-index:80;top:48%;transform:translateY(-50%);width:42px;height:56px;border:0;background:rgba(0,0,0,.72);color:#fff;display:grid;place-items:center;cursor:pointer}.player-nav-arrow:hover{background:var(--brand);color:var(--brand-on)}.player-nav-arrow.is-left{left:10px}.player-nav-arrow.is-right{right:10px}
        .mobile-card{position:relative;z-index:2;width:min(390px,100%);height:min(640px,calc(100svh - 118px));min-height:0;margin:0 auto;background:var(--brand);color:#fff;overflow:hidden;font-family:var(--heading)}.mobile-card.has-override{min-height:auto;background:#000}.mobile-override-img{width:100%;height:auto}.mobile-bg-number{position:absolute;left:8px;top:160px;font-family:"Iceberg",sans-serif;font-size:220px;line-height:.8;letter-spacing:-18px;color:rgba(255,255,255,.10);z-index:1}.mobile-logo{position:absolute;top:18px;right:18px;z-index:4;max-width:54px;max-height:54px;object-fit:contain}.mobile-name{position:relative;z-index:5;padding:48px 18px 0;width:62%}.mobile-jersey{font-size:36px;line-height:.9;font-weight:800}.mobile-first{font-size:45px;line-height:.9;font-weight:900;text-transform:uppercase;letter-spacing:-.05em}.mobile-last{font-size:52px;line-height:.85;font-weight:900;text-transform:uppercase;letter-spacing:-.055em}.mobile-pos{margin-top:8px;font-size:20px;line-height:1;font-weight:800;text-transform:uppercase}.mobile-signature{position:absolute;left:18px;top:238px;z-index:1;font-family:"Luxurious Script",cursive;font-size:84px;color:rgba(255,255,255,.16);transform:rotate(-7deg)}.mobile-player-stage{position:absolute;right:-16px;top:112px;z-index:3;width:64%;height:315px;display:flex;align-items:flex-end;justify-content:center;pointer-events:none}.mobile-player-stage img{height:100%;width:auto;max-width:none;object-fit:contain;object-position:bottom center;filter:drop-shadow(0 14px 24px rgba(0,0,0,.24))}.mobile-info-grid{position:absolute;left:8px;right:8px;bottom:10px;z-index:8;display:grid;grid-template-columns:1fr 1fr;gap:8px}.mobile-stat{min-height:205px;background:#f2f2f2;color:#080808;padding:10px;border-radius:8px;overflow:hidden}.mobile-big-row{display:flex;align-items:flex-end;gap:5px;margin-bottom:14px}.mobile-big{font-size:64px;line-height:.78;font-weight:900;letter-spacing:-.06em}.mobile-big-label{font-size:19px;line-height:.9;font-weight:900;text-transform:uppercase;padding-bottom:7px}.mobile-org{display:grid;gap:9px}.mobile-org-row{display:grid;grid-template-columns:34px 1fr;align-items:center;gap:7px}.mobile-org-row img{width:34px;height:34px;object-fit:contain}.mobile-org-title{font-size:14px;line-height:.95;font-weight:900;text-transform:uppercase}.mobile-org-value{font-family:var(--body);font-size:10px;line-height:1.05;font-weight:800}.mobile-class-row{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:12px;gap:4px}.mobile-class-year{font-size:57px;line-height:.78;font-weight:900;letter-spacing:-.055em}.mobile-class-label{font-size:19px;line-height:.9;font-weight:900;text-transform:uppercase;padding-bottom:7px}.mobile-meta{display:grid;gap:7px}.mobile-meta-row{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:baseline}.mobile-meta-label{font-size:12px;line-height:1;font-weight:700;text-transform:uppercase}.mobile-meta-value{font-size:12px;line-height:1;font-weight:900;text-transform:uppercase;text-align:right}.player-actions{position:sticky;bottom:0;z-index:20;width:min(390px,100%);margin:0 auto;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:0;border-top:1px solid var(--line);background:#050506}.player-action{min-height:54px;border:0;border-right:1px solid rgba(255,255,255,.08);background:#090a0d;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:0 4px;font-family:var(--heading);font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;text-decoration:none}.player-action i{font-size:14px;color:var(--brand-readable)}.player-action.primary{background:var(--brand);color:var(--brand-on);border-color:var(--brand)}.player-action.primary i{color:var(--brand-on)}
        .footer{padding:28px 0;color:var(--muted);font-size:12px;font-weight:750}.footer-grid{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap}.footer a{color:#fff}
        @keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}@keyframes fadeDown{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:none}}@keyframes popIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}@keyframes panelIn{to{transform:translateX(0)}}
        @media(max-width:900px){.avatar.card-img{width:44px;height:58px}.frame,.wrap{width:100%}.nav{padding:0 16px;height:66px}.nav-brand-stack{display:flex;align-items:center;gap:0;align-content:center}.nav-brand img{display:none}.nav-brand span{display:inline-flex!important;font-size:22px!important;letter-spacing:-.045em!important}.nav-brand:before,.nav-brand:after{display:none!important;content:none!important}.mobile-back-row{display:block;position:absolute;top:66px;left:0;right:0;z-index:40;padding:10px 18px 0;background:transparent!important;border:0!important;pointer-events:none}.page-back-mobile{display:inline-flex;align-items:center;gap:7px;color:rgba(255,255,255,.84);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;line-height:1;font-family:var(--heading);padding:0;background:transparent!important;border:0!important;box-shadow:none!important;text-shadow:0 2px 12px rgba(0,0,0,.46);pointer-events:auto}.page-back-mobile i{font-size:9px;color:#fff}.page-back-mobile:hover{color:#fff}.nav-link{display:none}.hero{min-height:430px}.hero-inner{grid-template-columns:1fr;gap:18px;padding:64px 24px 0;position:relative;min-height:362px;align-items:end}.back{display:none}.identity{display:grid;grid-template-columns:54px minmax(0,1fr) 54px;gap:12px;margin-bottom:0;padding-top:0;align-items:center}.identity-logo{width:52px;height:52px}.identity-league{width:52px;height:52px}.label{font-size:10px;color:rgba(255,255,255,.72);letter-spacing:.2em;margin-bottom:7px}.team-name{font-size:43px;line-height:.88}.team-copy{display:none}.team-facts{grid-template-columns:repeat(3,minmax(0,1fr));border-left:0;border-right:0;margin:44px -24px 0}.fact{display:block;padding:13px 14px}.fact i{font-size:14px;margin-bottom:10px}.fact span{font-size:8px}.fact strong{font-size:13px}.section{padding:30px 14px}.section-head{display:flex;align-items:end;justify-content:space-between;gap:12px}.roster-tools{margin-top:0;justify-content:flex-end}.roster-tools .sort-label{display:none}.saved-button{height:34px;padding:0 10px;font-size:10px}.roster{grid-template-columns:1fr}.player-row{grid-template-columns:62px 1fr auto;gap:10px}.avatar{width:54px;height:54px}.player-name{font-size:19px}.player-num{font-size:23px}.player-panel{width:100%}.mobile-card{height:min(640px,calc(100svh - 112px))}.footer{padding:24px 18px}.nav-actions .btn{padding:0 12px;font-size:11px}.player-panel-bar{height:52px}.player-dialog{height:calc(100% - 52px)}.player-panel-btn{height:34px}.player-nav-arrow{top:45%;width:38px;height:54px}.player-nav-arrow.is-left{left:0}.player-nav-arrow.is-right{right:0}}
        @media(max-width:380px){.mobile-card{min-height:650px}.mobile-first{font-size:44px}.mobile-last{font-size:52px}.mobile-big{font-size:64px}.mobile-class-year{font-size:58px}.mobile-stat{min-height:226px}.mobile-player-stage{height:330px}}
    

        /* Requested refinement: polished hero info and compact roster rows */
        .team-main-polished{max-width:820px}
        .hero-info-strip{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            margin:0 0 18px;
        }
        .hero-info-strip span{
            min-height:34px;
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:0 11px;
            background:rgba(255,255,255,.075);
            border-left:2px solid var(--brand-readable);
            backdrop-filter:blur(14px);
            color:rgba(255,255,255,.88);
            font-family:var(--heading);
            font-size:11px;
            font-weight:900;
            letter-spacing:.09em;
            text-transform:uppercase;
        }
        .hero-info-strip i{color:var(--brand-readable)}
        .team-hero-identity{
            grid-template-columns:74px minmax(0,1fr) 68px;
            gap:18px;
            padding:18px 0 0;
            border-top:1px solid rgba(255,255,255,.18);
        }
        .team-title-stack{min-width:0}
        .team-title-stack .label{color:rgba(255,255,255,.82)}
        .team-facts{display:none!important}
        .player-row.player-row-polished{
            grid-template-columns:64px 84px minmax(0,1fr) 24px;
            gap:14px;
            padding:12px 14px;
            align-items:center;
        }
        .player-jersey-big{
            font-family:var(--heading);
            font-size:42px;
            line-height:.9;
            font-weight:900;
            color:#fff;
            letter-spacing:-.04em;
            text-align:center;
        }
        .player-roster-copy{min-width:0}
        .player-roster-meta{
            display:grid;
            gap:4px;
            margin-top:7px;
            color:var(--muted);
            font-size:11px;
            font-weight:900;
            white-space:nowrap;
        }
        .player-roster-position{
            display:block;
            max-width:100%;
            overflow:hidden;
            text-overflow:ellipsis;
            color:rgba(255,255,255,.82);
            text-transform:uppercase;
        }
        .player-roster-academic{
            display:block;
            color:var(--muted);
        }
        .player-row-arrow{color:var(--brand-readable);font-size:15px;display:grid;place-items:center}
        .player-row-polished .avatar{border:0;box-shadow:none}
        .player-row-polished .player-name{font-size:21px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        @media(max-width:900px){
            .hero{min-height:460px}
            .hero-inner{min-height:392px;padding-top:78px;padding-bottom:0}
            .hero-info-strip{gap:6px;margin-bottom:13px}
            .hero-info-strip span{min-height:28px;font-size:8.5px;padding:0 8px;letter-spacing:.075em}
            .team-hero-identity{grid-template-columns:52px minmax(0,1fr) 52px;gap:12px;padding-top:14px}
            .team-name{font-size:46px}
            .player-row.player-row-polished{grid-template-columns:58px 58px minmax(0,1fr) 18px;gap:10px;padding:11px 10px}
            .player-jersey-big{font-size:30px;text-align:left}
            .player-roster-meta{gap:3px;font-size:10px;margin-top:5px}.player-roster-academic{white-space:nowrap}.player-roster-position{white-space:nowrap}
            .player-row-polished .player-name{font-size:18px}
            .player-row-arrow{font-size:13px}
        }


        /* Final team hero space refinement */
        .hero{
            align-items:center;
            min-height:clamp(560px,68vh,720px);
        }
        .hero-inner{
            min-height:clamp(500px,60vh,650px);
            align-items:center;
            padding:44px 0 38px;
        }
        .team-main-polished{
            width:min(860px,100%);
            margin:0;
        }
        .hero-info-strip{
            margin:0 0 24px;
        }
        .team-hero-identity{
            padding-top:22px;
            border-top:1px solid rgba(255,255,255,.22);
        }
        .team-title-stack .label{
            max-width:100%;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        @media(max-width:900px){
            .hero{min-height:520px;align-items:center}
            .hero-inner{min-height:452px;align-items:center;padding:72px 24px 28px}
            .team-main-polished{display:grid;align-content:center;min-height:360px}
            .hero-info-strip{margin:0 0 20px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
            .hero-info-strip span{justify-content:center;text-align:center;min-height:32px;padding:0 6px}
            .team-hero-identity{padding-top:20px}
            .team-name{font-size:clamp(44px,12vw,58px);line-height:.88}
        }
        @media(max-width:420px){
            .hero{min-height:500px}
            .hero-inner{min-height:432px;padding:68px 20px 24px}
            .team-main-polished{min-height:340px}
            .hero-info-strip span{font-size:7.8px}
            .team-hero-identity{grid-template-columns:46px minmax(0,1fr) 46px}
            .identity-logo,.identity-league{width:46px;height:46px}
            .team-name{font-size:clamp(38px,11vw,50px)}
        }


        /* Revision: pull team hero content up, use hero space, keep info before team name */
        .hero{
            min-height:clamp(500px, 60vh, 650px) !important;
            align-items:center !important;
        }
        .hero-inner{
            min-height:clamp(430px, 52vh, 560px) !important;
            align-items:center !important;
            padding:38px 0 34px !important;
        }
        .team-main-polished{
            display:grid !important;
            align-content:center !important;
            gap:18px !important;
            min-height:auto !important;
            width:min(880px,100%) !important;
            transform:translateY(-8px) !important;
        }
        .hero-info-strip{
            margin:0 !important;
            width:min(620px,100%) !important;
        }
        .team-hero-identity{
            margin-top:0 !important;
            padding-top:18px !important;
            grid-template-columns:72px minmax(0,1fr) 66px !important;
            max-width:760px !important;
        }
        .team-title-stack .label{
            font-size:11px !important;
            letter-spacing:.18em !important;
            color:rgba(255,255,255,.86) !important;
            margin-bottom:5px !important;
        }
        .team-name{
            font-size:clamp(54px, 6vw, 78px) !important;
            line-height:.88 !important;
            letter-spacing:.01em !important;
        }
        .team-copy{display:none !important;}
        @media(max-width:900px){
            .hero{min-height:470px !important;}
            .hero-inner{
                min-height:410px !important;
                padding:58px 22px 22px !important;
                align-items:center !important;
            }
            .team-main-polished{
                min-height:0 !important;
                transform:translateY(-4px) !important;
                gap:14px !important;
            }
            .hero-info-strip{
                display:grid !important;
                grid-template-columns:repeat(3,minmax(0,1fr)) !important;
                gap:6px !important;
                width:100% !important;
            }
            .hero-info-strip span{
                min-height:30px !important;
                padding:0 6px !important;
                font-size:7.8px !important;
                justify-content:center !important;
                text-align:center !important;
            }
            .team-hero-identity{
                grid-template-columns:50px minmax(0,1fr) 50px !important;
                gap:12px !important;
                padding-top:15px !important;
            }
            .identity-logo,.identity-league{width:50px !important;height:50px !important;}
            .team-title-stack .label{font-size:9px !important;letter-spacing:.14em !important;white-space:normal !important;}
            .team-name{font-size:clamp(40px, 11vw, 52px) !important;line-height:.9 !important;}
        }
        @media(max-width:420px){
            .hero{min-height:450px !important;}
            .hero-inner{min-height:390px !important;padding:54px 18px 18px !important;}
            .team-hero-identity{grid-template-columns:46px minmax(0,1fr) 46px !important;}
            .identity-logo,.identity-league{width:46px !important;height:46px !important;}
            .team-name{font-size:clamp(36px, 10vw, 48px) !important;}
        }

    

        /* FINAL FIX: pull team hero content up, enlarge info boxes, remove wasted hero space */
        @media(max-width:900px){
            .hero{
                min-height:395px !important;
                align-items:start !important;
                display:block !important;
            }
            .hero-inner{
                min-height:395px !important;
                padding:54px 20px 24px !important;
                align-items:start !important;
                align-content:start !important;
                display:block !important;
            }
            .team-main-polished{
                display:block !important;
                min-height:0 !important;
                transform:none !important;
                width:100% !important;
                margin:0 !important;
            }
            .hero-info-strip{
                display:grid !important;
                grid-template-columns:repeat(3,minmax(0,1fr)) !important;
                gap:8px !important;
                width:100% !important;
                margin:0 0 18px !important;
            }
            .hero-info-strip span{
                min-height:42px !important;
                padding:0 8px !important;
                justify-content:center !important;
                text-align:center !important;
                font-size:10px !important;
                line-height:1.05 !important;
                letter-spacing:.08em !important;
                background:rgba(255,255,255,.105) !important;
                border-left:2px solid rgba(255,255,255,.34) !important;
                backdrop-filter:blur(14px) !important;
            }
            .hero-info-strip span i{
                font-size:11px !important;
                margin-right:5px !important;
            }
            .team-hero-identity{
                display:grid !important;
                grid-template-columns:52px minmax(0,1fr) 52px !important;
                gap:12px !important;
                align-items:center !important;
                max-width:100% !important;
                margin:0 !important;
                padding-top:16px !important;
                border-top:1px solid rgba(255,255,255,.24) !important;
            }
            .identity-logo,.identity-league{
                width:50px !important;
                height:50px !important;
                object-fit:contain !important;
            }
            .team-title-stack .label{
                font-size:10px !important;
                line-height:1.1 !important;
                letter-spacing:.12em !important;
                margin-bottom:6px !important;
                color:rgba(255,255,255,.82) !important;
                white-space:normal !important;
            }
            .team-name{
                font-size:clamp(42px,11.6vw,56px) !important;
                line-height:.9 !important;
                letter-spacing:.01em !important;
            }
        }
        @media(max-width:420px){
            .hero{min-height:380px !important;}
            .hero-inner{min-height:380px !important;padding:50px 18px 20px !important;}
            .hero-info-strip{gap:6px !important;margin-bottom:15px !important;}
            .hero-info-strip span{min-height:40px !important;font-size:9px !important;padding:0 6px !important;}
            .team-hero-identity{grid-template-columns:48px minmax(0,1fr) 48px !important;gap:10px !important;}
            .identity-logo,.identity-league{width:46px !important;height:46px !important;}
            .team-name{font-size:clamp(38px,10.7vw,50px) !important;}
        }
        @media(min-width:901px){
            .hero{min-height:clamp(460px,58vh,620px) !important;}
            .hero-inner{min-height:clamp(420px,52vh,560px) !important;padding:44px 0 34px !important;}
            .team-main-polished{transform:none !important;}
            .hero-info-strip span{font-size:11px !important;min-height:42px !important;}
        }

    

        /* === FINAL TEAM HERO FIX: no wasted vertical space, larger readable info boxes === */
        .hero{
            min-height:clamp(380px, 52vh, 560px) !important;
            align-items:center !important;
        }
        .hero-inner{
            min-height:clamp(320px, 44vh, 470px) !important;
            padding:30px 0 24px !important;
            align-items:center !important;
        }
        .team-main-polished{
            width:min(860px,100%) !important;
            display:grid !important;
            align-content:center !important;
            gap:18px !important;
            transform:none !important;
            margin:0 !important;
        }
        .hero-info-strip{
            width:min(760px,100%) !important;
            display:grid !important;
            grid-template-columns:repeat(3,minmax(0,1fr)) !important;
            gap:8px !important;
            margin:0 !important;
        }
        .hero-info-strip span{
            min-height:48px !important;
            padding:0 13px !important;
            font-size:12px !important;
            line-height:1.08 !important;
            letter-spacing:.08em !important;
            justify-content:center !important;
            text-align:center !important;
            background:rgba(255,255,255,.105) !important;
            border-left:2px solid rgba(255,255,255,.30) !important;
            backdrop-filter:blur(14px) !important;
        }
        .hero-info-strip span i{font-size:13px !important;margin-right:5px !important;}
        .team-hero-identity{
            padding-top:18px !important;
            border-top:1px solid rgba(255,255,255,.22) !important;
            grid-template-columns:66px minmax(0,1fr) 62px !important;
            gap:16px !important;
            max-width:760px !important;
        }
        .team-hero-identity .identity-logo{width:62px !important;height:62px !important;}
        .team-hero-identity .identity-league{width:58px !important;height:58px !important;}
        .team-title-stack .label{font-size:11px !important;letter-spacing:.18em !important;color:rgba(255,255,255,.75) !important;}
        .team-name{font-size:clamp(44px, 6vw, 82px) !important;line-height:.9 !important;}
        @media(max-width:900px){
            .hero{min-height:360px !important;}
            .hero-inner{min-height:294px !important;padding:58px 24px 22px !important;}
            .team-main-polished{gap:14px !important;align-content:center !important;}
            .hero-info-strip{gap:7px !important;}
            .hero-info-strip span{min-height:42px !important;padding:0 8px !important;font-size:10px !important;letter-spacing:.06em !important;}
            .team-hero-identity{grid-template-columns:50px minmax(0,1fr) 50px !important;gap:12px !important;padding-top:15px !important;}
            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{width:48px !important;height:48px !important;}
            .team-title-stack .label{font-size:9px !important;}
            .team-name{font-size:clamp(40px, 11vw, 54px) !important;}
            .section{padding-top:34px !important;}
        }
        @media(max-width:420px){
            .hero{min-height:342px !important;}
            .hero-inner{min-height:276px !important;padding:52px 20px 18px !important;}
            .hero-info-strip span{min-height:40px !important;font-size:9px !important;padding:0 6px !important;}
            .team-hero-identity{grid-template-columns:44px minmax(0,1fr) 44px !important;}
            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{width:42px !important;height:42px !important;}
            .team-name{font-size:40px !important;}
        }


        /* FINAL SPACING PATCH: compact hero with no dead bottom space */
        .hero{
            min-height:clamp(390px, 46vh, 540px) !important;
            align-items:center !important;
        }
        .hero-bg{
            background-position:center 38% !important;
        }
        .hero-inner{
            min-height:clamp(330px, 39vh, 470px) !important;
            padding:24px 0 24px !important;
            align-items:center !important;
        }
        .team-main-polished{
            width:min(820px,100%) !important;
            gap:16px !important;
            transform:none !important;
            margin:0 !important;
            align-content:center !important;
        }
        .team-main-polished .back{
            margin:0 0 10px !important;
        }
        .hero-info-strip{
            width:min(760px,100%) !important;
            gap:10px !important;
            margin:0 !important;
        }
        .hero-info-strip span{
            min-height:50px !important;
            padding:0 14px !important;
            font-size:12px !important;
            line-height:1.05 !important;
            letter-spacing:.08em !important;
        }
        .team-hero-identity{
            max-width:760px !important;
            padding-top:16px !important;
            grid-template-columns:62px minmax(0,1fr) 58px !important;
            gap:15px !important;
        }
        .team-hero-identity .identity-logo{width:60px !important;height:60px !important;}
        .team-hero-identity .identity-league{width:56px !important;height:56px !important;}
        .team-title-stack .label{font-size:11px !important;letter-spacing:.16em !important;}
        .team-name{font-size:clamp(44px, 5.8vw, 78px) !important;line-height:.9 !important;}

        @media(max-width:900px){
            .hero{
                min-height:312px !important;
                align-items:start !important;
            }
            .hero-bg{
                background-position:center 35% !important;
            }
            .hero-inner{
                min-height:312px !important;
                padding:24px 22px 18px !important;
                align-items:start !important;
            }
            .team-main-polished{
                gap:13px !important;
                align-content:start !important;
                padding-top:0 !important;
            }
            .team-main-polished .back{
                display:inline-flex !important;
                margin:0 0 8px !important;
                font-size:10px !important;
                line-height:1 !important;
            }
            .hero-info-strip{
                display:grid !important;
                grid-template-columns:repeat(3,minmax(0,1fr)) !important;
                gap:8px !important;
                margin:0 !important;
            }
            .hero-info-strip span{
                min-height:45px !important;
                padding:0 8px !important;
                font-size:10px !important;
                letter-spacing:.055em !important;
                background:rgba(255,255,255,.13) !important;
                border-left:2px solid rgba(255,255,255,.36) !important;
            }
            .hero-info-strip span i{
                font-size:13px !important;
                margin-right:5px !important;
            }
            .team-hero-identity{
                grid-template-columns:52px minmax(0,1fr) 52px !important;
                gap:12px !important;
                padding-top:13px !important;
                margin-top:0 !important;
            }
            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{
                width:50px !important;
                height:50px !important;
            }
            .team-title-stack .label{
                font-size:9px !important;
                line-height:1.05 !important;
                letter-spacing:.14em !important;
                margin-bottom:7px !important;
            }
            .team-name{
                font-size:clamp(38px,10.5vw,48px) !important;
                line-height:.88 !important;
            }
            .section{
                padding-top:34px !important;
            }
        }

        @media(max-width:420px){
            .hero{min-height:300px !important;}
            .hero-inner{min-height:300px !important;padding:22px 18px 16px !important;}
            .hero-info-strip{gap:6px !important;}
            .hero-info-strip span{min-height:43px !important;font-size:9px !important;padding:0 6px !important;}
            .team-hero-identity{grid-template-columns:46px minmax(0,1fr) 46px !important;gap:10px !important;padding-top:12px !important;}
            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{width:44px !important;height:44px !important;}
            .team-name{font-size:40px !important;}
        }


        /* TRUE FINAL MOBILE HERO FIX: one back link, logos in hero corners, no wasted space */
        .team-main-polished .back{
            display:none !important;
        }

        @media(max-width:900px){
            .mobile-back-row{
                display:block !important;
                position:absolute !important;
                top:66px !important;
                left:0 !important;
                right:0 !important;
                z-index:60 !important;
                padding:12px 20px 0 !important;
                background:transparent !important;
                border:0 !important;
                box-shadow:none !important;
                pointer-events:none !important;
            }
            .page-back-mobile{
                background:transparent !important;
                border:0 !important;
                box-shadow:none !important;
                padding:0 !important;
                margin:0 !important;
                color:rgba(255,255,255,.88) !important;
                font-size:10px !important;
                letter-spacing:.14em !important;
                text-shadow:0 2px 12px rgba(0,0,0,.55) !important;
                pointer-events:auto !important;
            }

            .hero{
                min-height:330px !important;
                height:330px !important;
                display:block !important;
                overflow:hidden !important;
            }
            .hero-bg{
                background-position:center 40% !important;
            }
            .hero-bg:after{
                background:
                    linear-gradient(90deg,
                        color-mix(in srgb,var(--brand) 68%,rgba(0,0,0,.52)) 0%,
                        color-mix(in srgb,var(--brand) 28%,rgba(0,0,0,.22)) 52%,
                        color-mix(in srgb,var(--brand) 58%,rgba(0,0,0,.58)) 100%),
                    linear-gradient(180deg,rgba(0,0,0,.10) 0%,rgba(0,0,0,.06) 42%,color-mix(in srgb,var(--brand) 42%,rgba(0,0,0,.72)) 100%) !important;
            }
            .hero-inner{
                position:relative !important;
                width:100% !important;
                min-height:330px !important;
                height:330px !important;
                padding:0 20px 18px !important;
                display:flex !important;
                align-items:flex-start !important;
                justify-content:flex-start !important;
            }
            .team-main-polished{
                width:100% !important;
                display:grid !important;
                grid-template-columns:1fr !important;
                gap:12px !important;
                padding:0 !important;
                margin:0 !important;
                align-content:start !important;
            }

            .team-hero-identity{
                display:block !important;
                position:static !important;
                padding:0 !important;
                margin:0 !important;
                min-height:0 !important;
            }
            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{
                position:absolute !important;
                top:70px !important;
                z-index:9 !important;
                width:50px !important;
                height:50px !important;
                object-fit:contain !important;
            }
            .team-hero-identity .identity-logo{
                left:20px !important;
                justify-self:auto !important;
            }
            .team-hero-identity .identity-league{
                right:20px !important;
                justify-self:auto !important;
            }

            .hero-info-strip{
                order:1 !important;
                display:grid !important;
                grid-template-columns:repeat(3,minmax(0,1fr)) !important;
                gap:8px !important;
                margin:132px 0 0 !important;
                padding:0 !important;
                border:0 !important;
            }
            .hero-info-strip span{
                min-height:48px !important;
                padding:0 8px !important;
                justify-content:center !important;
                text-align:center !important;
                font-size:10px !important;
                line-height:1.02 !important;
                letter-spacing:.055em !important;
                background:rgba(255,255,255,.15) !important;
                border:1px solid rgba(255,255,255,.13) !important;
                border-left:2px solid rgba(255,255,255,.45) !important;
                backdrop-filter:blur(14px) !important;
                color:#fff !important;
            }
            .hero-info-strip span i{
                display:inline-block !important;
                font-size:14px !important;
                margin-right:6px !important;
                color:var(--brand-readable) !important;
            }

            .team-title-stack{
                order:2 !important;
                position:relative !important;
                z-index:8 !important;
                width:100% !important;
                margin:12px auto 0 !important;
                padding:12px 0 0 !important;
                border-top:1px solid rgba(255,255,255,.16) !important;
                text-align:left !important;
                padding-left:66px !important;
                padding-right:62px !important;
            }
            .team-title-stack .label{
                font-size:9px !important;
                line-height:1.05 !important;
                letter-spacing:.15em !important;
                margin:0 0 7px !important;
                color:rgba(255,255,255,.78) !important;
                white-space:nowrap !important;
                overflow:hidden !important;
                text-overflow:ellipsis !important;
            }
            .team-name{
                font-size:42px !important;
                line-height:.9 !important;
                letter-spacing:.01em !important;
                margin:0 !important;
                white-space:nowrap !important;
            }
            .section{
                padding-top:34px !important;
            }
        }

        @media(max-width:420px){
            .hero,
            .hero-inner{
                min-height:318px !important;
                height:318px !important;
            }
            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{
                top:68px !important;
                width:44px !important;
                height:44px !important;
            }
            .hero-info-strip{
                margin-top:124px !important;
                gap:6px !important;
            }
            .hero-info-strip span{
                min-height:44px !important;
                font-size:9px !important;
                padding:0 6px !important;
            }
            .team-title-stack{
                margin-top:11px !important;
                padding-left:56px !important;
                padding-right:54px !important;
            }
            .team-name{
                font-size:39px !important;
            }
        }

    

        /* HARD FINAL TEAM MOBILE HERO FIX - clean back row, corner logos, compact hero */
        @media (max-width:900px){
            .mobile-back-row{
                display:block !important;
                position:relative !important;
                top:auto !important;
                left:auto !important;
                right:auto !important;
                z-index:25 !important;
                width:100% !important;
                padding:10px 18px 2px !important;
                margin:0 !important;
                background:transparent !important;
                border:0 !important;
                pointer-events:none !important;
            }

            .page-back-mobile{
                display:inline-flex !important;
                align-items:center !important;
                gap:7px !important;
                padding:0 !important;
                margin:0 !important;
                background:transparent !important;
                border:0 !important;
                box-shadow:none !important;
                color:rgba(255,255,255,.86) !important;
                font-family:var(--heading) !important;
                font-size:10px !important;
                line-height:1 !important;
                font-weight:900 !important;
                letter-spacing:.14em !important;
                text-transform:uppercase !important;
                text-shadow:0 2px 14px rgba(0,0,0,.58) !important;
                pointer-events:auto !important;
            }

            .team-main-polished .back,
            .hero .back{
                display:none !important;
            }

            .hero{
                min-height:318px !important;
                height:318px !important;
                display:block !important;
                overflow:hidden !important;
                border-bottom:1px solid var(--line) !important;
            }

            .hero-bg{
                background-position:center 40% !important;
            }

            .hero-bg:after{
                background:
                    linear-gradient(90deg,
                        color-mix(in srgb,var(--brand) 58%,rgba(0,0,0,.46)) 0%,
                        color-mix(in srgb,var(--brand) 30%,rgba(0,0,0,.22)) 48%,
                        color-mix(in srgb,var(--brand) 52%,rgba(0,0,0,.46)) 100%),
                    linear-gradient(180deg,rgba(0,0,0,.04) 0%,rgba(0,0,0,.10) 48%,color-mix(in srgb,var(--brand) 48%,rgba(0,0,0,.44)) 100%) !important;
            }

            .hero-inner{
                display:block !important;
                position:relative !important;
                min-height:318px !important;
                height:318px !important;
                padding:0 18px !important;
                margin:0 !important;
            }

            .team-main-polished{
                position:relative !important;
                min-height:318px !important;
                height:318px !important;
                display:block !important;
                padding:12px 0 0 !important;
            }

            .hero-info-strip{
                position:relative !important;
                z-index:6 !important;
                display:grid !important;
                grid-template-columns:repeat(3,minmax(0,1fr)) !important;
                gap:8px !important;
                margin:4px 0 22px !important;
                padding:0 !important;
                border:0 !important;
            }

            .hero-info-strip span{
                min-height:48px !important;
                display:flex !important;
                align-items:center !important;
                justify-content:center !important;
                gap:7px !important;
                padding:0 8px !important;
                border:1px solid rgba(255,255,255,.24) !important;
                background:rgba(255,255,255,.12) !important;
                backdrop-filter:blur(14px) !important;
                font-size:10px !important;
                line-height:1.05 !important;
                font-weight:900 !important;
                letter-spacing:.04em !important;
                color:#fff !important;
                text-align:center !important;
            }

            .hero-info-strip span i{
                font-size:13px !important;
                color:rgba(255,255,255,.84) !important;
            }

            .team-hero-identity{
                position:relative !important;
                z-index:5 !important;
                display:block !important;
                min-height:116px !important;
                margin:0 !important;
                padding:24px 68px 0 !important;
                border-top:1px solid rgba(255,255,255,.18) !important;
                max-width:none !important;
            }

            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{
                position:absolute !important;
                top:28px !important;
                width:52px !important;
                height:52px !important;
                object-fit:contain !important;
                z-index:7 !important;
                margin:0 !important;
            }

            .team-hero-identity .identity-logo{
                left:0 !important;
                justify-self:auto !important;
            }

            .team-hero-identity .identity-league{
                right:0 !important;
                justify-self:auto !important;
            }

            .team-title-stack{
                position:relative !important;
                z-index:8 !important;
                min-width:0 !important;
                text-align:left !important;
            }

            .team-title-stack .label,
            .team-hero-identity .label{
                margin:0 0 8px !important;
                color:rgba(255,255,255,.76) !important;
                font-size:9px !important;
                line-height:1 !important;
                letter-spacing:.18em !important;
                white-space:nowrap !important;
                overflow:hidden !important;
                text-overflow:ellipsis !important;
            }

            .team-name{
                margin:0 !important;
                font-size:42px !important;
                line-height:.88 !important;
                letter-spacing:.015em !important;
                text-shadow:0 14px 32px rgba(0,0,0,.36) !important;
                white-space:nowrap !important;
            }

            #roster.section{
                padding-top:32px !important;
            }
        }

        @media (max-width:420px){
            .hero{height:304px !important;min-height:304px !important;}
            .hero-inner,.team-main-polished{height:304px !important;min-height:304px !important;}
            .hero-info-strip{gap:6px !important;margin-bottom:18px !important;}
            .hero-info-strip span{min-height:44px !important;font-size:9px !important;padding:0 5px !important;}
            .team-hero-identity{padding:22px 58px 0 !important;}
            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{width:46px !important;height:46px !important;top:26px !important;}
            .team-name{font-size:38px !important;}
        }



        /* CLUB-STYLE TEAM HERO FINAL OVERRIDE
           Goal: match the club landing structure: nav, one clean back row,
           hero logos in the top-left/top-right of the hero, title as its own row,
           readable info boxes, and no unused bottom space. */
        .team-main-polished .back,
        .hero .back{
            display:none !important;
        }

        @media (max-width:900px){
            .mobile-back-row{
                display:block !important;
                position:relative !important;
                top:auto !important;
                left:auto !important;
                right:auto !important;
                z-index:20 !important;
                width:100% !important;
                padding:11px 20px 8px !important;
                margin:0 !important;
                background:#050607 !important;
                border-top:1px solid rgba(255,255,255,.045) !important;
                border-bottom:1px solid rgba(255,255,255,.045) !important;
                box-shadow:none !important;
                pointer-events:none !important;
            }

            .page-back-mobile{
                display:inline-flex !important;
                align-items:center !important;
                gap:8px !important;
                padding:0 !important;
                margin:0 !important;
                background:transparent !important;
                border:0 !important;
                box-shadow:none !important;
                color:rgba(255,255,255,.82) !important;
                font-family:var(--heading) !important;
                font-size:10px !important;
                line-height:1 !important;
                font-weight:900 !important;
                letter-spacing:.13em !important;
                text-transform:uppercase !important;
                text-shadow:none !important;
                pointer-events:auto !important;
            }

            .page-back-mobile i{
                font-size:9px !important;
                color:rgba(255,255,255,.72) !important;
            }

            .hero{
                min-height:0 !important;
                height:auto !important;
                display:block !important;
                overflow:hidden !important;
                border-bottom:1px solid rgba(255,255,255,.08) !important;
            }

            .hero-bg{
                background-position:center 42% !important;
            }

            .hero-bg:after{
                background:
                    linear-gradient(90deg,
                        color-mix(in srgb,var(--brand) 62%,rgba(0,0,0,.46)) 0%,
                        color-mix(in srgb,var(--brand) 34%,rgba(0,0,0,.20)) 52%,
                        color-mix(in srgb,var(--brand) 54%,rgba(0,0,0,.42)) 100%),
                    linear-gradient(180deg,
                        rgba(0,0,0,.05) 0%,
                        rgba(0,0,0,.10) 52%,
                        color-mix(in srgb,var(--brand) 48%,rgba(0,0,0,.44)) 100%) !important;
            }

            .hero-inner{
                position:relative !important;
                display:block !important;
                width:100% !important;
                height:auto !important;
                min-height:0 !important;
                padding:22px 20px 24px !important;
                margin:0 !important;
            }

            .team-main-polished{
                position:relative !important;
                display:flex !important;
                flex-direction:column !important;
                gap:16px !important;
                width:100% !important;
                height:auto !important;
                min-height:0 !important;
                padding:0 !important;
                margin:0 !important;
            }

            .team-hero-identity{
                order:1 !important;
                position:relative !important;
                z-index:6 !important;
                display:grid !important;
                grid-template-columns:56px minmax(0,1fr) 56px !important;
                grid-template-rows:auto auto !important;
                align-items:start !important;
                column-gap:12px !important;
                row-gap:14px !important;
                min-height:0 !important;
                margin:0 !important;
                padding:0 !important;
                border:0 !important;
                max-width:none !important;
            }

            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{
                position:static !important;
                top:auto !important;
                left:auto !important;
                right:auto !important;
                z-index:7 !important;
                width:54px !important;
                height:54px !important;
                max-width:54px !important;
                max-height:54px !important;
                object-fit:contain !important;
                margin:0 !important;
                padding:0 !important;
            }

            .team-hero-identity .identity-logo{
                grid-column:1 !important;
                grid-row:1 !important;
                justify-self:start !important;
            }

            .team-hero-identity .identity-league{
                grid-column:3 !important;
                grid-row:1 !important;
                justify-self:end !important;
            }

            .team-title-stack{
                grid-column:1 / -1 !important;
                grid-row:2 !important;
                position:relative !important;
                z-index:8 !important;
                width:100% !important;
                min-width:0 !important;
                padding:0 !important;
                margin:0 !important;
                text-align:left !important;
            }

            .team-title-stack .label,
            .team-hero-identity .label{
                margin:0 0 8px !important;
                color:rgba(255,255,255,.76) !important;
                font-size:10px !important;
                line-height:1 !important;
                font-weight:900 !important;
                letter-spacing:.18em !important;
                text-transform:uppercase !important;
                white-space:nowrap !important;
                overflow:hidden !important;
                text-overflow:ellipsis !important;
            }

            .team-name{
                margin:0 !important;
                font-size:clamp(42px,12.4vw,58px) !important;
                line-height:.88 !important;
                letter-spacing:.01em !important;
                text-transform:uppercase !important;
                white-space:nowrap !important;
                text-shadow:0 14px 32px rgba(0,0,0,.36) !important;
            }

            .hero-info-strip{
                order:2 !important;
                position:relative !important;
                z-index:6 !important;
                display:grid !important;
                grid-template-columns:repeat(3,minmax(0,1fr)) !important;
                gap:8px !important;
                margin:0 !important;
                padding:0 !important;
                border:0 !important;
            }

            .hero-info-strip span{
                min-height:54px !important;
                display:flex !important;
                align-items:center !important;
                justify-content:center !important;
                gap:8px !important;
                padding:0 8px !important;
                border:1px solid rgba(255,255,255,.20) !important;
                border-left:2px solid rgba(255,255,255,.45) !important;
                background:rgba(255,255,255,.13) !important;
                backdrop-filter:blur(14px) !important;
                color:#fff !important;
                font-size:10px !important;
                line-height:1.05 !important;
                font-weight:900 !important;
                letter-spacing:.045em !important;
                text-transform:uppercase !important;
                text-align:center !important;
            }

            .hero-info-strip span i{
                display:inline-block !important;
                font-size:14px !important;
                margin:0 !important;
                color:rgba(255,255,255,.82) !important;
            }

            #roster.section{
                padding-top:32px !important;
            }
        }

        @media (max-width:420px){
            .hero-inner{
                padding:20px 18px 22px !important;
            }

            .team-main-polished{
                gap:14px !important;
            }

            .team-hero-identity{
                grid-template-columns:50px minmax(0,1fr) 50px !important;
                column-gap:10px !important;
                row-gap:12px !important;
            }

            .team-hero-identity .identity-logo,
            .team-hero-identity .identity-league{
                width:48px !important;
                height:48px !important;
                max-width:48px !important;
                max-height:48px !important;
            }

            .team-title-stack .label,
            .team-hero-identity .label{
                font-size:9px !important;
                letter-spacing:.15em !important;
            }

            .team-name{
                font-size:42px !important;
            }

            .hero-info-strip{
                gap:6px !important;
            }

            .hero-info-strip span{
                min-height:50px !important;
                font-size:9px !important;
                padding:0 6px !important;
            }
        }



        /* Final patch: remove the thin divider lines around the hero/back row. */
        .hero,
        .hero-inner,
        .team-main-polished,
        .team-hero-identity,
        .mobile-back-row {
            border: 0 !important;
            border-top: 0 !important;
            border-bottom: 0 !important;
        }

        .team-hero-identity::before,
        .team-hero-identity::after,
        .hero::before,
        .hero::after,
        .mobile-back-row::before,
        .mobile-back-row::after {
            display: none !important;
            content: none !important;
        }



        /* Final request: hero info cards + watchlist/navigation polish. */
        .mobile-back-row{display:none!important}
        .hero{min-height:clamp(430px,54vh,590px)!important;align-items:center!important}
        .hero-inner{min-height:auto!important;align-items:center!important;padding:40px 0 34px!important}
        .team-main-polished{width:100%!important;max-width:900px!important;display:grid!important;gap:18px!important;align-content:center!important}
        .hero-info-strip{
            display:grid!important;
            grid-template-columns:repeat(3,minmax(0,1fr))!important;
            gap:0!important;
            width:100%!important;
            margin:0!important;
            border:1px solid rgba(255,255,255,.14)!important;
            background:rgba(255,255,255,.10)!important;
            backdrop-filter:blur(16px)!important;
        }
        .hero-info-strip .hero-info-card{
            min-height:84px!important;
            display:grid!important;
            grid-template-columns:46px minmax(0,1fr)!important;
            grid-template-rows:auto auto!important;
            gap:2px 12px!important;
            align-items:center!important;
            padding:13px 14px!important;
            border-left:1px solid rgba(255,255,255,.12)!important;
            background:rgba(8,10,13,.48)!important;
            text-align:left!important;
        }
        .hero-info-strip .hero-info-card:first-child{border-left:0!important}
        .hero-info-icon,.hero-info-logo{grid-row:1/span 2!important;width:34px!important;height:34px!important;font-size:22px!important;color:var(--brand-readable)!important;display:grid!important;place-items:center!important;object-fit:contain!important;align-self:center!important}
        .hero-info-label{display:block!important;font-family:var(--heading)!important;font-size:9px!important;font-weight:900!important;letter-spacing:.15em!important;text-transform:uppercase!important;color:var(--muted)!important;line-height:1!important;align-self:end!important}
        .hero-info-value{display:block!important;font-family:var(--heading)!important;font-size:17px!important;font-weight:900!important;letter-spacing:.04em!important;text-transform:uppercase!important;color:#fff!important;line-height:1.02!important;align-self:start!important;overflow:hidden!important;text-overflow:ellipsis!important}
        .hero-coach-actions{grid-column:2!important;display:flex!important;gap:8px!important;margin-top:7px!important}
        .hero-coach-action{height:24px!important;display:inline-flex!important;align-items:center!important;gap:5px!important;padding:0 8px!important;background:rgba(255,255,255,.10)!important;color:#fff!important;border:1px solid rgba(255,255,255,.12)!important;font-family:var(--heading)!important;font-size:8px!important;font-weight:900!important;letter-spacing:.08em!important;text-transform:uppercase!important;text-decoration:none!important}
        .team-hero-identity{border:0!important;padding:0!important;grid-template-columns:72px minmax(0,1fr) 70px!important;align-items:center!important}
        .team-name{font-size:clamp(52px,7vw,92px)!important;line-height:.88!important}
        .player-row.player-row-polished{grid-template-columns:70px 86px minmax(0,1fr) 24px!important;gap:14px!important;align-items:center!important}
        .player-jersey-big{display:grid!important;gap:6px!important;text-align:left!important;color:#fff!important}
        .player-jersey-big .jersey-number{font-family:var(--heading)!important;font-size:46px!important;line-height:.82!important;font-weight:900!important;letter-spacing:-.06em!important;color:#fff!important}
        .player-jersey-position{display:block!important;max-width:100%!important;overflow:hidden!important;text-overflow:ellipsis!important;font-family:var(--heading)!important;font-size:14px!important;line-height:1!important;font-weight:900!important;letter-spacing:.08em!important;text-transform:uppercase!important;color:var(--brand-readable)!important;white-space:nowrap!important}
        .player-roster-position{display:none!important}
        .player-roster-academic{font-size:14px!important;color:rgba(255,255,255,.82)!important;font-weight:900!important;letter-spacing:.04em!important}
        .player-row-polished .player-name{font-size:23px!important}
        .player-panel-bar{display:grid!important;grid-template-columns:auto 1fr auto!important;gap:10px!important;align-items:center!important}
        .player-panel-back{height:36px;width:36px;border:0;background:rgba(255,255,255,.08);color:#fff;display:grid;place-items:center;cursor:pointer}
        .player-actions{grid-template-columns:repeat(4,minmax(0,1fr))!important;background:#050505!important;border-top:1px solid rgba(255,255,255,.14)!important;padding:8px!important;gap:7px!important}
        .player-action{min-height:58px!important;border:0!important;border-radius:7px!important;background:#fff!important;color:#050505!important;box-shadow:0 4px 10px rgba(0,0,0,.24)!important;font-size:10px!important;line-height:1!important;gap:6px!important}
        .player-action i{color:currentColor!important;font-size:14px!important}.player-action.primary{background:#ff5c35!important;color:#fff!important}.player-action.danger{background:#111!important;color:#fff!important}.player-action.is-disabled{opacity:.48!important;pointer-events:none!important}
        @media(max-width:900px){
            .hero{min-height:390px!important}.hero-inner{padding:30px 18px 28px!important}.team-main-polished{gap:15px!important}.hero-info-strip{grid-template-columns:1fr!important}.hero-info-strip .hero-info-card{min-height:68px!important;border-left:0!important;border-top:1px solid rgba(255,255,255,.10)!important;grid-template-columns:42px minmax(0,1fr)!important;padding:10px 12px!important}.hero-info-strip .hero-info-card:first-child{border-top:0!important}.hero-info-icon,.hero-info-logo{width:30px!important;height:30px!important}.hero-info-value{font-size:15px!important}.team-hero-identity{grid-template-columns:54px minmax(0,1fr) 54px!important;gap:12px!important}.team-name{font-size:44px!important}.player-row.player-row-polished{grid-template-columns:58px 70px minmax(0,1fr) 18px!important;gap:10px!important}.player-jersey-big .jersey-number{font-size:34px!important}.player-jersey-position{font-size:11px!important}.player-roster-academic{font-size:12px!important}.player-row-polished .player-name{font-size:18px!important}.avatar.card-img{width:48px!important;height:64px!important}.nav-actions{gap:8px!important}.nav-actions .btn{height:40px!important}.player-actions{grid-template-columns:repeat(4,minmax(0,1fr))!important;padding-bottom:calc(8px + env(safe-area-inset-bottom,0px))!important}.player-action{min-height:54px!important;font-size:9px!important}}



        /* Pull-up navigation, modeled after the Locker Room drawer behavior. */
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
        .coach-drawer-card.is-accent{background:#ff5c35;color:#fff}
        .coach-drawer-card.is-dark{background:#121318;color:#fff;border:1px solid rgba(255,255,255,.08)}
        .coach-drawer-card i{font-size:15px;color:currentColor}.coach-drawer-card span{display:block;font-size:10.5px;line-height:1;font-weight:900;color:currentColor}
        .coach-drawer-note{margin:10px 0 0;color:rgba(255,255,255,.62);font-size:12px;line-height:1.3;font-weight:700}
        @media(max-width:420px){.coach-drawer-grid{gap:6px}.coach-drawer-card{min-height:56px}.coach-drawer-card span{font-size:9.5px}}

        /* Club-style team hero override: same layout rhythm as the club landing hero. */
        .team-main-polished{display:grid!important;gap:22px!important;align-content:start!important;width:100%!important}
        .team-hero-identity{order:1!important;display:grid!important;grid-template-columns:70px minmax(0,1fr) 70px!important;grid-template-rows:auto auto!important;align-items:start!important;column-gap:18px!important;row-gap:18px!important;margin:0!important;padding:0!important;border:0!important;max-width:none!important}
        .team-hero-identity .identity-logo{grid-column:1!important;grid-row:1!important;justify-self:start!important;position:static!important;width:66px!important;height:66px!important;object-fit:contain!important}
        .team-hero-identity .identity-league{grid-column:3!important;grid-row:1!important;justify-self:end!important;position:static!important;width:66px!important;height:66px!important;object-fit:contain!important}
        .team-title-stack{grid-column:1/-1!important;grid-row:2!important;margin:0!important;padding:0!important;text-align:left!important;border:0!important}
        .team-title-stack .label{font-size:11px!important;letter-spacing:.22em!important;margin:0 0 8px!important;color:rgba(255,255,255,.72)!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important}
        .team-name{font-size:clamp(44px,6vw,78px)!important;line-height:.9!important;white-space:normal!important;max-width:760px!important}
        .hero-info-strip{order:2!important;display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:1px!important;margin:0!important;padding:0!important;background:rgba(255,255,255,.12)!important;border:1px solid rgba(255,255,255,.12)!important}
        .hero-info-card{min-height:74px!important;display:grid!important;grid-template-columns:44px minmax(0,1fr)!important;grid-template-rows:auto auto!important;column-gap:12px!important;align-items:center!important;background:rgba(5,5,6,.62)!important;padding:14px 16px!important;border:0!important;backdrop-filter:blur(16px)!important}
        .hero-info-icon,.hero-info-logo{grid-row:1/3!important;grid-column:1!important;width:34px!important;height:34px!important;object-fit:contain!important;color:var(--brand-readable)!important;font-size:19px!important;align-self:center!important;justify-self:center!important}
        .hero-info-label{grid-column:2!important;grid-row:1!important;color:var(--muted)!important;font-size:10px!important;font-weight:900!important;letter-spacing:.14em!important;text-transform:uppercase!important;line-height:1!important}
        .hero-info-value{grid-column:2!important;grid-row:2!important;font-family:var(--heading)!important;font-size:18px!important;line-height:1!important;text-transform:uppercase!important;font-weight:900!important;color:#fff!important}
        .hero-coach-actions{grid-column:2!important;margin-top:6px;display:flex;gap:8px;flex-wrap:wrap}.hero-coach-action{font-size:10px;font-weight:900;text-transform:uppercase;color:var(--brand-readable)}
        @media(max-width:900px){.hero{min-height:0!important;height:auto!important}.hero-inner{padding:30px 18px 0!important}.team-main-polished{gap:18px!important}.team-hero-identity{grid-template-columns:54px minmax(0,1fr) 54px!important;column-gap:12px!important;row-gap:14px!important}.team-hero-identity .identity-logo,.team-hero-identity .identity-league{width:52px!important;height:52px!important}.team-title-stack .label{font-size:9px!important}.team-name{font-size:42px!important}.hero-info-strip{grid-template-columns:1fr!important;margin:0 -18px!important;border-left:0!important;border-right:0!important}.hero-info-card{min-height:66px!important;grid-template-columns:42px minmax(0,1fr)!important;padding:12px 18px!important}.hero-info-value{font-size:16px!important}}


        /* LOCKER ROOM STYLE PULL-UP + CLEAN CLUB-STYLE TEAM HERO FINAL PATCH */
        .hero-info-card .hero-info-label,
        .hero-info-card span.hero-info-label{
            background:transparent!important;
            border:0!important;
            box-shadow:none!important;
            min-height:auto!important;
            padding:0!important;
            margin:0!important;
            justify-content:start!important;
            text-align:left!important;
            backdrop-filter:none!important;
        }

        .hero-info-card .hero-info-value{
            background:transparent!important;
            border:0!important;
            box-shadow:none!important;
            padding:0!important;
            margin:0!important;
        }

        .hero-info-card{overflow:hidden!important;}
        .hero-info-card .hero-coach-actions{grid-column:2!important;grid-row:auto!important;margin-top:7px!important;display:flex!important;gap:10px!important;align-items:center!important;flex-wrap:wrap!important;}
        .hero-coach-action{display:inline-flex!important;align-items:center!important;gap:5px!important;color:#fff!important;opacity:.92!important;font-family:var(--heading)!important;font-size:10px!important;line-height:1!important;font-weight:900!important;letter-spacing:.08em!important;text-transform:uppercase!important;}

        .coach-action-drawer{position:fixed!important;left:0!important;right:0!important;bottom:0!important;top:auto!important;width:100vw!important;max-width:100vw!important;z-index:100000!important;pointer-events:none!important;color:#fff!important;}
        .coach-action-drawer.is-open{pointer-events:auto!important;}
        .coach-drawer-scrim{position:fixed!important;inset:0!important;background:rgba(0,0,0,.44)!important;backdrop-filter:blur(3px)!important;-webkit-backdrop-filter:blur(3px)!important;opacity:0!important;pointer-events:none!important;transition:opacity .2s ease!important;}
        .coach-action-drawer.is-open .coach-drawer-scrim{opacity:1!important;pointer-events:auto!important;}
        .coach-drawer-panel{position:fixed!important;left:0!important;right:0!important;bottom:0!important;width:100vw!important;max-width:100vw!important;margin:0!important;padding:0!important;max-height:min(78dvh,580px)!important;background:#050505!important;border-radius:14px 14px 0 0!important;overflow:hidden!important;box-shadow:0 -12px 34px rgba(0,0,0,.46)!important;transform:translateY(100%)!important;transition:transform .28s cubic-bezier(.2,.8,.2,1)!important;pointer-events:auto!important;}
        .coach-action-drawer.is-open .coach-drawer-panel{transform:translateY(0)!important;}
        .coach-drawer-handle{position:absolute!important;top:7px!important;left:50%!important;width:50px!important;height:4px!important;border-radius:999px!important;background:rgba(0,0,0,.20)!important;transform:translateX(-50%)!important;z-index:2!important;}
        .coach-drawer-head{min-height:50px!important;padding:13px 10px 8px!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:6px!important;background:#fff!important;color:#050505!important;border-radius:14px 14px 0 0!important;}
        .coach-drawer-title{margin:0!important;color:#050505!important;font-family:var(--heading)!important;font-size:14px!important;line-height:1!important;font-weight:950!important;text-transform:uppercase!important;letter-spacing:.015em!important;}
        .coach-drawer-close{min-width:26px!important;width:26px!important;height:26px!important;border:0!important;background:transparent!important;color:#050505!important;font-size:17px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;cursor:pointer!important;}
        .coach-drawer-body{padding:7px 9px calc(68px + env(safe-area-inset-bottom,0px))!important;max-height:calc(min(78dvh,580px) - 50px)!important;overflow-y:auto!important;background:#050505!important;color:#fff!important;}
        .coach-drawer-group-title{display:block!important;margin:0 0 6px!important;color:rgba(255,255,255,.58)!important;font-size:10px!important;line-height:1!important;font-weight:900!important;letter-spacing:.055em!important;text-transform:uppercase!important;}
        .coach-drawer-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:6px!important;}
        .coach-drawer-card{min-width:0!important;min-height:52px!important;padding:6px 4px 5px!important;display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;gap:4px!important;border:0!important;border-radius:7px!important;background:#fff!important;color:#050505!important;box-shadow:0 3px 8px rgba(0,0,0,.22)!important;text-align:center!important;text-decoration:none!important;cursor:pointer!important;font:inherit!important;font-family:var(--heading)!important;}
        .coach-drawer-card.is-accent{background:#ff5c35!important;color:#fff!important;}
        .coach-drawer-card.is-dark{background:#fff!important;color:#050505!important;border:0!important;}
        .coach-drawer-card i{font-size:13px!important;line-height:1!important;color:currentColor!important;}
        .coach-drawer-card span{display:block!important;color:currentColor!important;font-size:10px!important;line-height:1.02!important;font-weight:850!important;}
        .coach-drawer-note{margin:10px 0 0!important;color:rgba(255,255,255,.62)!important;font-size:12px!important;line-height:1.28!important;font-weight:650!important;}
        .coach-drawer-tab{position:fixed!important;right:0!important;bottom:0!important;width:196px!important;height:54px!important;padding:0 12px 0 42px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;border:0!important;border-radius:0!important;background:#ff5c35!important;color:#fff!important;font-family:var(--heading)!important;font-size:18px!important;font-weight:900!important;line-height:1!important;text-transform:uppercase!important;cursor:pointer!important;pointer-events:auto!important;clip-path:polygon(32px 0,100% 0,100% 100%,0 100%)!important;z-index:2!important;}
        .coach-drawer-tab i{font-size:13px!important;transition:transform .25s ease!important;}
        .coach-action-drawer.is-open .coach-drawer-tab i{transform:rotate(180deg)!important;}
        @media(max-width:390px){.coach-drawer-grid{gap:5px!important}.coach-drawer-card{min-height:49px!important;padding:5px 3px!important}.coach-drawer-card span{font-size:9.5px!important}.coach-drawer-tab{width:184px!important;height:50px!important;padding-left:38px!important;font-size:16px!important;clip-path:polygon(29px 0,100% 0,100% 100%,0 100%)!important}}

        .player-panel-back{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:34px!important;height:34px!important;border:0!important;background:rgba(255,255,255,.08)!important;color:#fff!important;cursor:pointer!important;}
        .player-panel-title{font-family:var(--heading)!important;font-size:14px!important;font-weight:900!important;letter-spacing:.08em!important;text-transform:uppercase!important;color:#fff!important;}
        .player-panel-bar{grid-template-columns:40px 1fr auto!important;}

        .nav-email-list{display:inline-flex!important;text-decoration:none!important;}

        @media(min-width:901px){
            .hero{min-height:clamp(560px,62vh,720px)!important;display:grid!important;align-items:end!important;}
            .hero-inner{padding:72px 0 42px!important;display:grid!important;align-items:end!important;}
            .team-main-polished{max-width:900px!important;gap:28px!important;}
            .team-hero-identity{grid-template-columns:86px minmax(0,1fr) 86px!important;row-gap:20px!important;column-gap:20px!important;}
            .team-hero-identity .identity-logo,.team-hero-identity .identity-league{width:76px!important;height:76px!important;}
            .team-title-stack{grid-column:1/-1!important;}
            .team-name{font-size:clamp(58px,7vw,96px)!important;}
            .hero-info-strip{grid-template-columns:repeat(3,minmax(0,1fr))!important;margin:0!important;border-left:0!important;border-right:0!important;}
        }

        @media(max-width:900px){
            .mobile-back-row{display:none!important;}
            .hero{min-height:0!important;height:auto!important;display:block!important;overflow:hidden!important;}
            .hero-inner{padding:28px 18px 0!important;min-height:0!important;height:auto!important;display:block!important;}
            .team-main-polished{height:auto!important;min-height:0!important;display:grid!important;gap:18px!important;align-content:start!important;padding:0!important;margin:0!important;}
            .team-hero-identity{display:grid!important;grid-template-columns:54px minmax(0,1fr) 54px!important;grid-template-rows:auto auto!important;column-gap:12px!important;row-gap:14px!important;align-items:start!important;margin:0!important;padding:0!important;}
            .team-hero-identity .identity-logo{grid-column:1!important;grid-row:1!important;position:static!important;width:52px!important;height:52px!important;justify-self:start!important;}
            .team-hero-identity .identity-league{grid-column:3!important;grid-row:1!important;position:static!important;width:52px!important;height:52px!important;justify-self:end!important;}
            .team-title-stack{grid-column:1/-1!important;grid-row:2!important;margin:0!important;padding:0!important;border:0!important;text-align:left!important;}
            .team-title-stack .label{font-size:9px!important;letter-spacing:.20em!important;margin:0 0 8px!important;line-height:1!important;color:rgba(255,255,255,.72)!important;}
            .team-name{font-size:42px!important;line-height:.90!important;white-space:normal!important;}
            .hero-info-strip{display:grid!important;grid-template-columns:1fr!important;gap:1px!important;margin:0 -18px!important;padding:0!important;background:rgba(255,255,255,.12)!important;border-left:0!important;border-right:0!important;border-top:1px solid rgba(255,255,255,.12)!important;border-bottom:1px solid rgba(255,255,255,.12)!important;}
            .hero-info-card{min-height:66px!important;display:grid!important;grid-template-columns:42px minmax(0,1fr)!important;grid-template-rows:auto auto!important;column-gap:12px!important;align-items:center!important;padding:12px 18px!important;background:rgba(5,5,6,.50)!important;border:0!important;}
            .hero-info-icon,.hero-info-logo{grid-row:1/3!important;grid-column:1!important;width:30px!important;height:30px!important;font-size:18px!important;}
            .hero-info-label{grid-column:2!important;grid-row:1!important;font-size:9px!important;letter-spacing:.13em!important;}
            .hero-info-value{grid-column:2!important;grid-row:2!important;font-size:17px!important;}
            .hero-coach-actions{grid-column:2!important;}
            .section{padding-top:34px!important;}
            .nav-actions{gap:8px!important;}
            .nav-actions .btn{height:42px!important;padding:0 12px!important;font-size:10px!important;}
            .nav-email-list{display:none!important;}
        }


        /* Final pull-up navigation revision: keep player actions inside drawer, not sticky under card. */
        .player-actions{display:none!important;}
        .player-panel-bar{display:grid!important;grid-template-columns:auto auto minmax(0,1fr) auto!important;gap:8px!important;align-items:center!important;}
        .player-quick-save{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:36px!important;}
        .player-quick-save form{margin:0!important;display:inline-flex!important;}
        .player-quick-save button{height:34px!important;min-width:74px!important;border:0!important;border-radius:0!important;background:#ff5c35!important;color:#fff!important;font-family:var(--heading)!important;font-size:12px!important;font-weight:950!important;letter-spacing:.08em!important;text-transform:uppercase!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:6px!important;cursor:pointer!important;padding:0 11px!important;}
        .player-quick-save button.is-remove{background:#fff!important;color:#050505!important;}
        .coach-drawer-form{display:grid!important;gap:8px!important;margin-top:8px!important;}
        .coach-drawer-form label{display:grid!important;gap:5px!important;color:rgba(255,255,255,.62)!important;font-size:10px!important;font-weight:900!important;letter-spacing:.055em!important;text-transform:uppercase!important;}
        .coach-drawer-form input{height:38px!important;border:1px solid rgba(255,255,255,.12)!important;background:#101116!important;color:#fff!important;padding:0 11px!important;font:inherit!important;font-family:var(--heading)!important;font-size:13px!important;font-weight:800!important;outline:none!important;}
        .coach-drawer-form input:focus{border-color:#ff5c35!important;box-shadow:0 0 0 3px rgba(255,92,53,.15)!important;}
        .coach-drawer-submit{height:40px!important;border:0!important;background:#ff5c35!important;color:#fff!important;font-family:var(--heading)!important;font-size:13px!important;font-weight:950!important;letter-spacing:.09em!important;text-transform:uppercase!important;cursor:pointer!important;}
        .coach-drawer-form-row{display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important;}
        .coach-drawer-player-actions{display:none;}
        .coach-action-drawer.has-player .coach-drawer-main-actions{display:none!important;}
        .coach-action-drawer.has-player .coach-drawer-player-actions{display:block!important;}
        .coach-player-summary{display:grid!important;grid-template-columns:42px 1fr!important;gap:10px!important;align-items:center!important;margin:0 0 10px!important;padding:10px!important;background:rgba(255,255,255,.07)!important;border:1px solid rgba(255,255,255,.10)!important;}
        .coach-player-summary strong{display:block!important;font-size:16px!important;line-height:1!important;text-transform:uppercase!important;color:#fff!important;}
        .coach-player-summary span{display:block!important;margin-top:4px!important;color:rgba(255,255,255,.62)!important;font-size:11px!important;font-weight:850!important;text-transform:uppercase!important;}
        .coach-player-summary-img{width:42px!important;height:42px!important;border-radius:50%!important;background:#15161b!important;object-fit:cover!important;display:grid!important;place-items:center!important;color:#fff!important;}
        .coach-drawer-tab.is-player{background:#ff5c35!important;}
        @media(max-width:420px){.coach-drawer-form-row{grid-template-columns:1fr!important}.player-panel-bar{grid-template-columns:auto auto 1fr auto!important}.player-panel-title{font-size:12px!important}}

    </style>
</head>
<body>
<main class="site">
    <div class="frame">
        <nav class="nav">
            <div class="nav-brand-stack">
                <a class="nav-brand" href="{{ $club?->landing_page_slug ? route('clubs.landing', ['clubSlug' => $club->landing_page_slug]) : '/' }}" aria-label="PlyrCard home">
                    <span class="plyr-wordmark"><b>PLYR</b><em>CARD</em></span>
                </a>
            </div>
            @php
                $watchlistEmailSubject = 'My PlyrCard Watchlist - ' . ($team->name ?? 'Team');
                $watchlistEmailBody = "My saved PlyrCard watchlist:

" . $savedPlayers->map(function ($saved) {
                    return trim(($saved['player_name'] ?? 'Player') . "
" . ($saved['player_url'] ?? '') . "
" . ($saved['player_email'] ?? '') . "
" . ($saved['player_phone'] ?? ''));
                })->filter()->implode("

");
            @endphp
            <div class="nav-actions">
                @if($coachSession)
                    <button class="btn" type="button" data-open-actions>
                        <i class="fa-solid fa-bookmark"></i>
                        My Watchlist
                    </button>
                    <a class="btn nav-email-list" href="mailto:{{ $coachSession['email'] ?? '' }}?subject={{ rawurlencode($watchlistEmailSubject) }}&body={{ rawurlencode($watchlistEmailBody) }}">
                        <i class="fa-solid fa-envelope"></i>
                        Email List
                    </a>
                @else
                    <button class="btn" type="button" data-open-actions>
                        <i class="fa-solid fa-right-to-bracket"></i>
                        Check In
                    </button>
                @endif
            </div>
        </nav>
    </div>

    <section class="hero">
        <div class="hero-bg"></div>
        <div class="wrap hero-inner">
            <div class="team-main team-main-polished">
                <div class="identity team-hero-identity">
                    @if($teamLogo)<img class="identity-logo" src="{{ $teamLogo }}" alt="{{ $team->name }} logo">@endif
                    @if($leagueLogo)<img class="identity-league" src="{{ $leagueLogo }}" alt="{{ $leagueName }} logo">@endif
                    <div class="team-title-stack">
                        <div class="label">{{ $club?->name ?: 'Club' }}</div>
                        <h1 class="team-name">Team {{ $team->name }}</h1>
                    </div>
                </div>

                <div class="hero-info-strip" aria-label="Basic team information">
                    <div class="hero-info-card">
                        <i class="hero-info-icon fa-solid fa-users"></i>
                        <span class="hero-info-label">Roster</span>
                        <strong class="hero-info-value">{{ $playerRows->count() }} Players</strong>
                    </div>
                    <div class="hero-info-card">
                        @if($leagueLogo)
                            <img class="hero-info-logo" src="{{ $leagueLogo }}" alt="{{ $leagueName }} logo">
                        @else
                            <i class="hero-info-icon fa-solid fa-trophy"></i>
                        @endif
                        <span class="hero-info-label">League</span>
                        <strong class="hero-info-value">{{ $leagueName }}</strong>
                    </div>
                    <div class="hero-info-card">
                        <i class="hero-info-icon fa-solid fa-user-tie"></i>
                        <span class="hero-info-label">Coach</span>
                        <strong class="hero-info-value">{{ $coachName }}</strong>
                        <div class="hero-coach-actions">
                            @if($coachPhone)
                                <a class="hero-coach-action" href="sms:{{ preg_replace('/\D+/', '', $coachPhone) }}"><i class="fa-solid fa-message"></i> Text Coach</a>
                                <a class="hero-coach-action" href="tel:{{ preg_replace('/\D+/', '', $coachPhone) }}"><i class="fa-solid fa-phone"></i> Call Coach</a>
                            @elseif($coachEmail)
                                <a class="hero-coach-action" href="mailto:{{ $coachEmail }}"><i class="fa-solid fa-envelope"></i> Email Coach</a>
                            @endif
                        </div>
                    </div>
                </div>
                @if($teamIntro)<div class="team-copy">{!! nl2br(e($teamIntro)) !!}</div>@endif
            </div>
        </div>
    </section>

    <section class="section" id="roster">
        <div class="wrap">
            <div class="section-head">
                <div><div class="eyebrow">Team Roster</div><div class="section-title">Players</div></div>
                <div class="roster-tools">
                    <span class="sort-label"><i class="fa-solid fa-arrow-down-1-9"></i> Sorted by number</span>
                    <button class="saved-button" type="button" data-open-saved><i class="fa-solid fa-bookmark"></i> Watchlist <strong>{{ $savedPlayers->count() }}</strong></button>
                </div>
            </div>
            <div class="roster">
                @if($playerRows->isNotEmpty())
                    @foreach($playerRows as $player)
                        <button class="player-row player-row-polished" type="button" data-player-card data-player-index="{{ $player['index'] }}" data-player='@json($player)'>
                            @if($player['card_image'])
                                <img class="avatar card-img" src="{{ $player['card_image'] }}" alt="{{ $player['name'] }} PlyrCard image">
                            @elseif($player['portrait_image'])
                                <img class="avatar" src="{{ $player['portrait_image'] }}" alt="{{ $player['name'] }} portrait">
                            @else
                                <div class="avatar"><i class="fa-solid fa-user"></i></div>
                            @endif

                            <div class="player-jersey-big">
                                <span class="jersey-number">{{ filled($player['jersey']) ? '#' . ltrim($player['jersey'], '#') : '—' }}</span>
                                @if($player['position'])
                                    <span class="player-jersey-position">{{ $player['position'] }}</span>
                                @endif
                            </div>

                            <div class="player-roster-copy">
                                <div class="player-name">{{ $player['name'] }}</div>

                                <div class="player-roster-meta">
                                    @if($player['position'])
                                        <span class="player-roster-position">{{ $player['position'] }}</span>
                                    @endif

                                    @php
                                        $academicLine = collect([
                                            filled($player['year'] ?? null) ? 'Class ' . $player['year'] : null,
                                            filled($player['gpa'] ?? null) ? $player['gpa'] . ' GPA' : null,
                                        ])->filter()->implode(' | ');
                                    @endphp

                                    @if(filled($academicLine))
                                        <span class="player-roster-academic">{{ $academicLine }}</span>
                                    @endif
                                </div>

                                @if(in_array((int) $player['id'], $savedIds, true))
                                    <div class="player-save-mini">Saved by coach</div>
                                @endif
                            </div>

                            <div class="player-row-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                        </button>
                    @endforeach
                @else
                    <div class="empty">Players will appear once they are assigned to this team.</div>
                @endif
            </div>
        </div>
    </section>

    <footer class="footer"><div class="wrap footer-grid"><div>© {{ now()->year }} {{ $team->name }}. Powered by PlyrCard.</div><div>{{ $club->name }}</div></div></footer>
</main>

<div class="modal" id="coachModal" aria-hidden="true">
    <div class="coach-card" role="dialog" aria-modal="true" aria-labelledby="coachModalTitle">
        <div class="modal-head"><div class="modal-title" id="coachModalTitle">Coach Info</div><button class="modal-close" type="button" data-close-coach><i class="fa-solid fa-xmark"></i></button></div>
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

@php
    $saveUrlTemplate = route('clubs.coach-save-player', ['clubSlug' => $club->landing_page_slug, 'gender' => $currentGenderSegment, 'teamSlug' => $team->landing_page_slug, 'player' => '__PLAYER_ID__']);
    $unsaveUrlTemplate = route('clubs.coach-unsave-player', ['clubSlug' => $club->landing_page_slug, 'gender' => $currentGenderSegment, 'teamSlug' => $team->landing_page_slug, 'player' => '__PLAYER_ID__']);
@endphp

<div class="modal" id="savedModal" aria-hidden="true">
    <div class="coach-card" role="dialog" aria-modal="true" aria-labelledby="savedModalTitle">
        <div class="modal-head"><div class="modal-title" id="savedModalTitle">Saved Profiles</div><button class="modal-close" type="button" data-close-saved><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            @if($savedPlayers->isNotEmpty())
                <div class="saved-list">
                    @foreach($savedPlayers as $saved)
                        <div class="saved-item"><strong>{{ $saved['player_name'] ?? 'Player' }}</strong><span>{{ $saved['saved_at'] ?? '' }}</span></div>
                    @endforeach
                </div>
            @else
                <div class="coach-status">No saved profiles yet. Open a player and tap Save Profile.</div>
            @endif
        </div>
    </div>
</div>


<div class="coach-action-drawer" id="coachActionDrawer" aria-hidden="true">
    <div class="coach-drawer-scrim" data-close-actions></div>
    <section class="coach-drawer-panel" aria-label="Coach navigation">
        <div class="coach-drawer-handle" aria-hidden="true"></div>
        <div class="coach-drawer-head">
            <h2 class="coach-drawer-title">{{ $coachSession ? 'Coach Navigation' : 'Coach Check-In' }}</h2>
            <button class="coach-drawer-close" type="button" data-close-actions aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="coach-drawer-body">
            <div class="coach-drawer-main-actions">
            @if($coachSession)
                <strong class="coach-drawer-group-title">{{ filled($coachSession['name'] ?? null) ? 'Hi ' . $coachSession['name'] : 'Coach Tools' }}</strong>
                <div class="coach-drawer-grid">
                    <button class="coach-drawer-card is-accent" type="button" data-open-saved data-close-actions><i class="fa-solid fa-binoculars"></i><span>My Watchlist</span></button>
                    <a class="coach-drawer-card" href="mailto:{{ $coachSession['email'] ?? '' }}?subject={{ rawurlencode($watchlistEmailSubject) }}&body={{ rawurlencode($watchlistEmailBody) }}"><i class="fa-solid fa-envelope"></i><span>Email Me the List</span></a>
                    <button class="coach-drawer-card is-dark" type="button" data-open-coach data-close-actions><i class="fa-solid fa-user-tie"></i><span>Coach Info</span></button>
                </div>
                <p class="coach-drawer-note">Saved profiles stay in this browser session and can be emailed to {{ $coachSession['email'] ?? 'your inbox' }}.</p>
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
                <p class="coach-drawer-note">Check in once to save players, build a watchlist, and email yourself the list.</p>
            @endif
            </div>
            <div class="coach-drawer-player-actions" id="coachDrawerPlayerActions">
                <strong class="coach-drawer-group-title">Player Actions</strong>
                <div class="coach-player-summary" id="coachDrawerPlayerSummary"></div>
                <div class="coach-drawer-grid" id="coachDrawerPlayerGrid"></div>
                <p class="coach-drawer-note">Use these quick actions while viewing the player card.</p>
            </div>
        </div>
    </section>
    <button class="coach-drawer-tab" type="button" data-open-actions>
        <i class="fa-solid {{ $coachSession ? 'fa-bookmark' : 'fa-chevron-up' }}"></i>
        <span>{{ $coachSession ? 'WATCHLIST' : 'CHECK IN' }}</span>
    </button>
</div>

<div class="player-overlay" id="playerOverlay" aria-hidden="true">
    <div class="player-panel">
        <div class="player-panel-bar"><button class="player-panel-back" id="playerBackBtn" type="button" aria-label="Back"><i class="fa-solid fa-chevron-left"></i></button><div class="player-quick-save" id="playerQuickSave"></div><div class="player-panel-title" id="playerPanelTitle"><i class="fa-solid fa-id-card"></i> Player Card</div><button class="player-panel-btn" id="playerCloseBtn" type="button"><i class="fa-solid fa-xmark"></i> Close</button></div>
        <button class="player-nav-arrow is-left" id="playerPrevBtn" type="button" aria-label="Previous player"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="player-nav-arrow is-right" id="playerNextBtn" type="button" aria-label="Next player"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="player-dialog" id="playerDialog"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const coachModal = document.getElementById('coachModal');
    const savedModal = document.getElementById('savedModal');
    const coachActionDrawer = document.getElementById('coachActionDrawer');
    const drawerTitle = coachActionDrawer?.querySelector('.coach-drawer-title');
    const drawerPlayerSummary = document.getElementById('coachDrawerPlayerSummary');
    const drawerPlayerGrid = document.getElementById('coachDrawerPlayerGrid');
    const playerQuickSave = document.getElementById('playerQuickSave');
    let currentPlayer = null;
    const openActions = () => { coachActionDrawer?.classList.add('is-open'); coachActionDrawer?.setAttribute('aria-hidden','false'); };
    const closeActions = () => { coachActionDrawer?.classList.remove('is-open'); coachActionDrawer?.setAttribute('aria-hidden','true'); };
    document.querySelectorAll('[data-open-actions]').forEach(btn => btn.addEventListener('click', openActions));
    document.querySelectorAll('[data-close-actions]').forEach(btn => btn.addEventListener('click', closeActions));
    document.querySelectorAll('[data-open-coach]').forEach(btn => btn.addEventListener('click', () => { coachActionDrawer?.classList.remove('has-player'); openActions(); }));
    document.querySelectorAll('[data-close-coach]').forEach(btn => btn.addEventListener('click', () => { coachModal?.classList.remove('is-open');savedModal?.classList.remove('is-open'); coachModal?.setAttribute('aria-hidden','true'); }));
    document.querySelectorAll('[data-open-saved]').forEach(btn => btn.addEventListener('click', () => { closeActions(); savedModal?.classList.add('is-open'); savedModal?.setAttribute('aria-hidden','false'); }));
    document.querySelectorAll('[data-close-saved]').forEach(btn => btn.addEventListener('click', () => { savedModal?.classList.remove('is-open'); savedModal?.setAttribute('aria-hidden','true'); }));
    coachModal?.addEventListener('click', e => { if(e.target === coachModal){ coachModal.classList.remove('is-open'); coachModal.setAttribute('aria-hidden','true'); }});
    savedModal?.addEventListener('click', e => { if(e.target === savedModal){ savedModal.classList.remove('is-open'); savedModal.setAttribute('aria-hidden','true'); }});

    const cards = Array.from(document.querySelectorAll('[data-player-card]'));
    const overlay = document.getElementById('playerOverlay');
    const dialog = document.getElementById('playerDialog');
    const title = document.getElementById('playerPanelTitle');
    const closeBtn = document.getElementById('playerCloseBtn');
    const nextBtn = document.getElementById('playerNextBtn');
    const prevBtn = document.getElementById('playerPrevBtn');
    const backBtn = document.getElementById('playerBackBtn');
    const saveUrlTemplate = @json($saveUrlTemplate);
    const unsaveUrlTemplate = @json($unsaveUrlTemplate);
    const savedIds = @json($savedIds);
    const csrfToken = @json(csrf_token());
    const checkedIn = @json((bool) $coachSession);
    let active = 0;

    function esc(value){return String(value || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
    function dataAt(index){const el = cards[index]; if(!el) return null; try{return JSON.parse(el.dataset.player || '{}');}catch(e){return {};}}
    function clean(value){return value && value !== 'null' && value !== 'undefined' ? String(value) : '';}
    function action(label, icon, href, extraClass){return href ? `<a class="coach-drawer-card ${extraClass || ''}" href="${esc(href)}"><i class="fa-solid ${icon}"></i><span>${esc(label)}</span></a>` : '';}
    function saveButtonHtml(player, compact){
        const saveUrl = saveUrlTemplate.replace('__PLAYER_ID__', encodeURIComponent(player.id));
        const unsaveUrl = unsaveUrlTemplate.replace('__PLAYER_ID__', encodeURIComponent(player.id));
        const isSaved = savedIds.map(Number).includes(Number(player.id));
        if(!checkedIn){
            return `<button class="${compact ? '' : 'coach-drawer-card is-accent'}" type="button" data-open-coach><i class="fa-solid fa-right-to-bracket"></i>${compact ? 'Check In' : '<span>Check In</span>'}</button>`;
        }
        if(isSaved){
            return `<form method="POST" action="${esc(unsaveUrl)}" style="margin:0"><input type="hidden" name="_token" value="${esc(csrfToken)}"><input type="hidden" name="_method" value="DELETE"><button class="${compact ? 'is-remove' : 'coach-drawer-card'}" type="submit"><i class="fa-solid fa-bookmark-slash"></i>${compact ? 'Remove' : '<span>Remove</span>'}</button></form>`;
        }
        return `<form method="POST" action="${esc(saveUrl)}" style="margin:0"><input type="hidden" name="_token" value="${esc(csrfToken)}"><button class="${compact ? '' : 'coach-drawer-card is-accent'}" type="submit"><i class="fa-solid fa-bookmark"></i>${compact ? 'Save' : '<span>Save</span>'}</button></form>`;
    }
    function renderPlayerDrawer(player){
        currentPlayer = player;
        const phone = clean(player.phone).replace(/\D+/g, '');
        const contactEmail = player.personal_email || player.email || '';
        if(drawerTitle) drawerTitle.textContent = 'Player Actions';
        if(drawerPlayerSummary){
            const img = player.card_image || player.portrait_image || player.main_image || '';
            drawerPlayerSummary.innerHTML = `${img ? `<img class="coach-player-summary-img" src="${esc(img)}" alt="${esc(player.name)}">` : `<div class="coach-player-summary-img"><i class="fa-solid fa-user"></i></div>`}<div><strong>${esc(player.name || 'Player')}</strong><span>${esc([player.position, player.year ? 'Class '+player.year : ''].filter(Boolean).join(' | '))}</span></div>`;
        }
        if(drawerPlayerGrid){
            drawerPlayerGrid.innerHTML = `${saveButtonHtml(player,false)}${action('Website','fa-arrow-up-right-from-square',player.website_url)}${action('Email','fa-envelope',contactEmail ? 'mailto:'+contactEmail : '')}${action('Call','fa-phone',phone ? 'tel:'+phone : '')}`;
            drawerPlayerGrid.querySelectorAll('[data-open-coach]').forEach(btn => btn.addEventListener('click', () => { coachActionDrawer?.classList.remove('has-player'); }));
        }
        if(playerQuickSave){
            playerQuickSave.innerHTML = saveButtonHtml(player,true);
            playerQuickSave.querySelectorAll('[data-open-coach]').forEach(btn => btn.addEventListener('click', () => { closePlayer(); coachActionDrawer?.classList.remove('has-player'); openActions(); }));
        }
    }

    function render(player){
        const jersey = clean(player.jersey) ? '#' + String(player.jersey).replace(/^#/, '') : '';
        const logo = player.league_logo || player.club_logo || '';
        const override = clean(player.mobile_hero_image);
        const mainImage = player.main_image || player.portrait_image || '';
        const saveUrl = saveUrlTemplate.replace('__PLAYER_ID__', encodeURIComponent(player.id));
        const unsaveUrl = unsaveUrlTemplate.replace('__PLAYER_ID__', encodeURIComponent(player.id));
        const isSaved = savedIds.map(Number).includes(Number(player.id));
        let cardHtml = '';
        if(override){
            cardHtml = `<article class="mobile-card has-override"><img class="mobile-override-img" src="${esc(override)}" alt="${esc(player.name)}"></article>`;
        } else {
            cardHtml = `
                <article class="mobile-card">
                    <div class="mobile-bg-number">${esc(clean(player.jersey) || '')}</div>
                    ${logo ? `<img class="mobile-logo" src="${esc(logo)}" alt="Logo">` : ''}
                    <div class="mobile-name">
                        ${jersey ? `<div class="mobile-jersey">${esc(jersey)}</div>` : ''}
                        <div class="mobile-first">${esc(player.first_name || 'PLAYER')}</div>
                        <div class="mobile-last">${esc(player.last_name || '')}</div>
                        <div class="mobile-pos">${esc(player.position || 'POSITION')}</div>
                    </div>
                    <div class="mobile-signature">${esc((player.first_name || 'Name').toLowerCase())}</div>
                    <div class="mobile-player-stage">${mainImage ? `<img src="${esc(mainImage)}" alt="${esc(player.name)}">` : ''}</div>
                    <div class="mobile-info-grid">
                        <div class="mobile-stat">
                            <div class="mobile-big-row"><div class="mobile-big">${esc(player.gpa || '0.0')}</div><div class="mobile-big-label">/GPA</div></div>
                            <div class="mobile-org">
                                <div class="mobile-org-row">${player.club_logo ? `<img src="${esc(player.club_logo)}" alt="Club">` : '<div></div>'}<div><div class="mobile-org-title">Club</div><div class="mobile-org-value">${esc(player.club || '--')}</div></div></div>
                                <div class="mobile-org-row">${player.league_logo ? `<img src="${esc(player.league_logo)}" alt="League">` : '<div></div>'}<div><div class="mobile-org-title">League</div><div class="mobile-org-value">${esc(player.league || '--')}</div></div></div>
                            </div>
                        </div>
                        <div class="mobile-stat">
                            <div class="mobile-class-row"><div class="mobile-class-year">${esc(player.year || '—')}</div><div class="mobile-class-label">/CLASS</div></div>
                            <div class="mobile-meta">
                                <div class="mobile-meta-row"><div class="mobile-meta-label">Height:</div><div class="mobile-meta-value">${esc(player.height || '--')}</div></div>
                                <div class="mobile-meta-row"><div class="mobile-meta-label">Weight:</div><div class="mobile-meta-value">${esc(player.weight || '--')}</div></div>
                                <div class="mobile-meta-row"><div class="mobile-meta-label">Max Speed:</div><div class="mobile-meta-value">${esc(player.max_speed || '--')}</div></div>
                                <div class="mobile-meta-row"><div class="mobile-meta-label">Dominant Foot:</div><div class="mobile-meta-value">${esc(player.dominant_foot || '--')}</div></div>
                                <div class="mobile-meta-row"><div class="mobile-meta-label">DOB:</div><div class="mobile-meta-value">${esc(player.dob || '--')}</div></div>
                                <div class="mobile-meta-row"><div class="mobile-meta-label">Coach:</div><div class="mobile-meta-value">${esc(player.coach || '--')}</div></div>
                            </div>
                        </div>
                    </div>
                </article>`;
        }
        return `${cardHtml}`;
    }
    function openPlayer(index){const player = dataAt(index); if(!player) return; active = index; renderPlayerDrawer(player); coachActionDrawer?.classList.add('has-player'); document.querySelector('.coach-drawer-tab')?.classList.add('is-player'); const tabText = document.querySelector('.coach-drawer-tab span'); if(tabText) tabText.textContent = 'ACTIONS'; title.innerHTML = '<i class="fa-solid fa-id-card"></i> Player Card'; dialog.innerHTML = render(player); overlay.classList.add('is-open'); overlay.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden';}
    function closePlayer(){overlay.classList.remove('is-open'); overlay.setAttribute('aria-hidden','true'); dialog.innerHTML=''; if(playerQuickSave) playerQuickSave.innerHTML=''; coachActionDrawer?.classList.remove('has-player'); document.querySelector('.coach-drawer-tab')?.classList.remove('is-player'); const tabText = document.querySelector('.coach-drawer-tab span'); if(tabText) tabText.textContent = checkedIn ? 'WATCHLIST' : 'CHECK IN'; if(drawerTitle) drawerTitle.textContent = checkedIn ? 'Coach Navigation' : 'Coach Check-In'; document.body.style.overflow='';}
    function next(){if(!cards.length)return; openPlayer((active+1)%cards.length);} function prev(){if(!cards.length)return; openPlayer((active-1+cards.length)%cards.length);}
    cards.forEach((card, i) => card.addEventListener('click', e => {e.preventDefault(); openPlayer(i);}));
    closeBtn?.addEventListener('click', closePlayer); backBtn?.addEventListener('click', closePlayer); nextBtn?.addEventListener('click', next); prevBtn?.addEventListener('click', prev); overlay?.addEventListener('click', e => {if(e.target===overlay) closePlayer();});
    document.addEventListener('keydown', e => { if(e.key==='Escape'){closePlayer();coachModal?.classList.remove('is-open');savedModal?.classList.remove('is-open');} if(overlay?.classList.contains('is-open') && e.key==='ArrowRight') next(); if(overlay?.classList.contains('is-open') && e.key==='ArrowLeft') prev(); });
});
</script>
</body>
</html>