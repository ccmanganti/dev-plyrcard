<x-filament-panels::page>
    @php
        $club = $this->assignedClub;
        $stats = $this->stats;
        $ageGroups = $this->ageGroups;
        $allAgeGroups = $this->allAgeGroups;
        $leagueOptions = $this->leagueOptions;
        $genderOptions = $this->availableGenderOptions;
        $selectedLeague = $this->selectedLeague;
        $selectedProgram = $this->selectedProgram;
        $players = $this->selectedTeamPlayers;
        $selectedPlayer = $this->selectedPlayer;
        $teamGames = $this->selectedTeamGames;
        $upcomingGames = $this->upcomingGames;
        $positionOptions = $this->positionOptions;

        $clubName = $club?->name ?? 'No club assigned';
        $landingUrl = $club?->landingUrl();
        $logoUrl = $club?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($club->logo) : null;
        $heroUrl = $club?->background_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($club->background_image) : null;
        $primary = $club?->primary_color ?: '#ff5c35';

        $assetUrl = function ($path) {
            if (blank($path)) {
                return null;
            }

            $path = trim((string) $path);

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            return \Illuminate\Support\Facades\Storage::disk('public')->url(ltrim($path, '/'));
        };

        $selectedLeagueLogo = $selectedLeague ? $assetUrl($selectedLeague['logo'] ?? null) : null;
    @endphp

    <style>
        .club-dashboard-v7, .club-dashboard-v7 * { box-sizing: border-box; }
        .club-dashboard-v7 { display:grid; gap:16px; }
        .club-dashboard-v7 .hero { position:relative; overflow:hidden; border-radius:28px; padding:22px; background:linear-gradient(135deg, rgba(0,0,0,.78), rgba(0,0,0,.92)), var(--club-hero-image, linear-gradient(135deg, #111, #050505)); background-size:cover; color:#fff; border:1px solid rgba(255,255,255,.1); }
        .club-dashboard-v7 .hero::before { content:""; position:absolute; inset:0; background:radial-gradient(circle at 12% 12%, color-mix(in srgb, var(--club-primary) 42%, transparent), transparent 34%); pointer-events:none; }
        .club-dashboard-v7 .hero-inner { position:relative; z-index:1; display:grid; grid-template-columns:minmax(0,1fr) minmax(300px, 390px); align-items:center; gap:18px; }
        .club-dashboard-v7 .hero-main { display:flex; align-items:center; gap:16px; min-width:0; }
        .club-dashboard-v7 .club-logo { width:76px; height:76px; border-radius:21px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; overflow:hidden; flex:0 0 76px; font-size:26px; font-weight:950; }
        .club-dashboard-v7 .club-logo img { width:100%; height:100%; object-fit:cover; }
        .club-dashboard-v7 .kicker { margin:0 0 8px; color:rgba(255,255,255,.58); font-size:11px; letter-spacing:.16em; text-transform:uppercase; font-weight:900; }
        .club-dashboard-v7 h2 { margin:0; font-size:clamp(30px, 5vw, 54px); line-height:.94; letter-spacing:-.05em; font-weight:950; }
        .club-dashboard-v7 p { margin:0; }
        .club-dashboard-v7 .copy { margin-top:10px; max-width:760px; color:rgba(255,255,255,.72); font-size:14px; line-height:1.55; }
        .club-dashboard-v7 .actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:16px; }
        .club-dashboard-v7 .btn { display:inline-flex; align-items:center; justify-content:center; min-height:40px; border-radius:999px; padding:0 15px; text-decoration:none; font-size:12px; font-weight:900; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.08); color:#fff; cursor:pointer; }
        .club-dashboard-v7 .btn-primary { color:#120806; background:linear-gradient(135deg, #fff2de, var(--club-primary)); }

        .club-dashboard-v7 .hero-control {
            position:relative;
            justify-self:end;
            width:min(360px,100%);
            min-height:86px;
            display:flex;
            justify-content:flex-end;
            align-items:center;
            padding:0;
            background:transparent;
            border:0;
            overflow:visible;
        }
.club-dashboard-v7 .control-label {
            position:absolute;
            right:0;
            top:0;
            margin:0;
            color:rgba(255,255,255,.48);
            font-size:10px;
            font-weight:950;
            letter-spacing:.14em;
            text-transform:uppercase;
            text-align:right;
        }
        .club-dashboard-v7 .league-logo-grid {
            position:relative;
            z-index:3;
            display:flex;
            justify-content:flex-end;
            align-items:flex-start;
            flex-wrap:wrap;
            gap:18px;
            padding-top:18px;
            padding-right:4px;
            overflow:visible;
        }
        .club-dashboard-v7 .league-option {
            position:relative;
            display:grid;
            justify-items:center;
            align-items:start;
            min-width:82px;
            min-height:104px;
            isolation:isolate;
        }
        .club-dashboard-v7 .league-logo-button {
            border:0;
            background:transparent;
            color:#fff;
            cursor:pointer;
            display:grid;
            place-items:center;
            gap:8px;
            padding:0;
            text-align:center;
            transition:transform .2s ease, filter .2s ease, opacity .2s ease;
            opacity:.72;
        }
        .club-dashboard-v7 .league-logo-button:hover,
        .club-dashboard-v7 .league-option:hover .league-logo-button,
        .club-dashboard-v7 .league-option:focus-within .league-logo-button {
            transform:translateY(-3px) scale(1.04);
            filter:drop-shadow(0 18px 28px color-mix(in srgb, var(--club-primary) 34%, transparent));
            opacity:1;
        }
        .club-dashboard-v7 .league-logo-button.is-active {
            opacity:1;
            filter:drop-shadow(0 18px 34px color-mix(in srgb, var(--club-primary) 44%, transparent));
        }
        .club-dashboard-v7 .league-logo-button.is-active::after {
            content:"";
            width:34px;
            height:3px;
            border-radius:999px;
            background:linear-gradient(90deg,#fff2de,var(--club-primary));
            display:block;
            margin-top:3px;
        }
        .club-dashboard-v7 .league-logo-button img {
            width:62px;
            height:62px;
            object-fit:contain;
        }
        .club-dashboard-v7 .league-logo-fallback {
            width:62px;
            height:62px;
            border-radius:999px;
            display:grid;
            place-items:center;
            background:rgba(255,255,255,.08);
            font-size:15px;
            font-weight:950;
        }
        .club-dashboard-v7 .league-logo-button span {
            font-size:11px;
            font-weight:950;
            line-height:1.05;
            max-width:82px;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
            color:rgba(255,255,255,.78);
        }
        .club-dashboard-v7 .league-genders {
            position:absolute;
            top:-2px;
            left:50%;
            width:150px;
            height:86px;
            transform:translateX(-50%) scale(.82);
            visibility:hidden;
            opacity:0;
            pointer-events:none;
            z-index:80;
            transition:opacity .18s ease, transform .22s cubic-bezier(.2,.85,.25,1);
        }
        .club-dashboard-v7 .league-option:hover .league-genders,
        .club-dashboard-v7 .league-option:focus-within .league-genders {
            visibility:visible;
            opacity:1;
            pointer-events:auto;
            transform:translateX(-50%) scale(1);
        }
        .club-dashboard-v7 .split-gender-button {
            position:absolute;
            top:4px;
            width:70px;
            height:84px;
            border:0;
            padding:0;
            background:transparent;
            cursor:pointer;
            overflow:visible;
            transform:translateX(0) rotate(0deg);
            transition:transform .22s cubic-bezier(.2,.85,.25,1), filter .16s ease, opacity .16s ease;
        }
        .club-dashboard-v7 .split-gender-button.is-male {
            left:50%;
            margin-left:-35px;
        }
        .club-dashboard-v7 .split-gender-button.is-female {
            right:50%;
            margin-right:-35px;
        }
        .club-dashboard-v7 .league-option:hover .split-gender-button.is-male,
        .club-dashboard-v7 .league-option:focus-within .split-gender-button.is-male {
            transform:translateX(36px) rotate(4deg);
        }
        .club-dashboard-v7 .league-option:hover .split-gender-button.is-female,
        .club-dashboard-v7 .league-option:focus-within .split-gender-button.is-female {
            transform:translateX(-36px) rotate(-4deg);
        }
        .club-dashboard-v7 .split-gender-button:hover {
            filter:drop-shadow(0 18px 30px rgba(0,0,0,.45));
        }
        .club-dashboard-v7 .split-gender-button img,
        .club-dashboard-v7 .split-gender-button .split-gender-fallback {
            width:70px;
            height:70px;
            object-fit:contain;
            display:block;
            margin:0 auto;
            transition:transform .18s ease, opacity .18s ease;
        }
        .club-dashboard-v7 .split-gender-fallback {
            display:grid !important;
            place-items:center;
            border-radius:999px;
            background:rgba(255,255,255,.08);
            color:#fff;
            font-size:15px;
            font-weight:950;
        }
        .club-dashboard-v7 .split-gender-button::before {
            content:"";
            position:absolute;
            inset:5px 4px 15px;
            border-radius:999px;
            background:transparent;
            transition:background .18s ease, box-shadow .18s ease;
            pointer-events:none;
        }
        .club-dashboard-v7 .split-gender-button img,
        .club-dashboard-v7 .split-gender-button .split-gender-fallback {
            position:relative;
            z-index:2;
        }
        .club-dashboard-v7 .split-gender-button.is-male img,
        .club-dashboard-v7 .split-gender-button.is-male .split-gender-fallback {
            filter:grayscale(1) brightness(1.25) contrast(1.05) sepia(.35) hue-rotate(165deg) saturate(3.8);
        }
        .club-dashboard-v7 .split-gender-button.is-female img,
        .club-dashboard-v7 .split-gender-button.is-female .split-gender-fallback {
            filter:grayscale(1) brightness(1.22) contrast(1.04) sepia(.45) hue-rotate(290deg) saturate(3.2);
        }
        .club-dashboard-v7 .split-gender-button.is-male::before {
            box-shadow:0 0 0 1px rgba(82, 132, 255, .22), 0 18px 40px rgba(58, 108, 255, .12);
            background:radial-gradient(circle at center, rgba(76,116,255,.18) 0%, rgba(76,116,255,.08) 55%, transparent 72%);
        }
        .club-dashboard-v7 .split-gender-button.is-female::before {
            box-shadow:0 0 0 1px rgba(255, 97, 160, .24), 0 18px 40px rgba(255, 97, 160, .12);
            background:radial-gradient(circle at center, rgba(255,97,160,.18) 0%, rgba(255,97,160,.08) 55%, transparent 72%);
        }
        .club-dashboard-v7 .split-gender-button strong {
            position:absolute;
            left:50%;
            bottom:-2px;
            transform:translateX(-50%);
            font-size:10px;
            font-weight:950;
            letter-spacing:.14em;
            text-transform:uppercase;
            color:rgba(255,255,255,.82);
            white-space:nowrap;
            text-shadow:0 4px 14px rgba(0,0,0,.55);
            z-index:3;
        }
        .club-dashboard-v7 .split-gender-button.is-active strong {
            color:#fff;
        }
        .club-dashboard-v7 .split-gender-button.is-active img,
        .club-dashboard-v7 .split-gender-button.is-active .split-gender-fallback {
            transform:scale(1.03);
        }
        .club-dashboard-v7 .gender-row { display:none; }


        .club-dashboard-v7 .stat-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:10px; }
        .club-dashboard-v7 .stat-card { border:0; text-align:left; cursor:pointer; border-radius:20px; padding:14px; background:#111; border:1px solid rgba(255,255,255,.09); color:#fff; }
        .club-dashboard-v7 .stat-card.is-active { outline:2px solid color-mix(in srgb, var(--club-primary) 65%, transparent); }
        .club-dashboard-v7 .stat-label { color:rgba(255,255,255,.5); font-size:10px; letter-spacing:.11em; text-transform:uppercase; font-weight:900; }
        .club-dashboard-v7 .stat-value { margin-top:7px; font-size:29px; line-height:1; font-weight:950; }

        .club-dashboard-v7 .layout { display:grid; grid-template-columns:360px minmax(0,1fr); gap:14px; align-items:start; }
        .club-dashboard-v7 .panel { border-radius:22px; padding:15px; background:#111; border:1px solid rgba(255,255,255,.09); color:#fff; min-width:0; }
        .club-dashboard-v7 .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
        .club-dashboard-v7 .panel h3 { margin:0; font-size:15px; font-weight:950; }
        .club-dashboard-v7 .note { color:rgba(255,255,255,.48); font-size:12px; font-weight:700; }
        .club-dashboard-v7 .list { display:grid; gap:9px; }
        .club-dashboard-v7 .list-item { width:100%; display:flex; justify-content:space-between; gap:12px; border-radius:16px; padding:12px; background:rgba(255,255,255,.055); border:0; color:inherit; text-align:left; cursor:pointer; }
        .club-dashboard-v7 .list-item:hover { background:rgba(255,255,255,.085); }
        .club-dashboard-v7 .list-item.is-active { background:color-mix(in srgb, var(--club-primary) 20%, rgba(255,255,255,.055)); }
        .club-dashboard-v7 .list-main strong { display:block; color:#fff; }
        .club-dashboard-v7 .list-main span { display:block; color:rgba(255,255,255,.55); font-size:12px; margin-top:3px; }
        .club-dashboard-v7 .badge { display:inline-flex; align-items:center; height:27px; border-radius:999px; padding:0 10px; background:color-mix(in srgb, var(--club-primary) 20%, transparent); color:#ffd1c7; font-size:11px; font-weight:900; white-space:nowrap; }
        .club-dashboard-v7 .empty { color:rgba(255,255,255,.58); font-size:13px; line-height:1.55; }

        .club-dashboard-v7 input, .club-dashboard-v7 select, .club-dashboard-v7 textarea { width:100%; min-height:42px; border-radius:13px; border:1px solid rgba(255,255,255,.12); background:#1b1b1d !important; color:#fff !important; padding:0 12px; font-size:13px; color-scheme:dark; }
        .club-dashboard-v7 select option { background:#151518 !important; color:#fff !important; }
        .club-dashboard-v7 textarea { padding:12px; min-height:86px; }
        .club-dashboard-v7 .field { display:grid; gap:6px; color:rgba(255,255,255,.75); font-size:12px; font-weight:900; }
        .club-dashboard-v7 .filter-row { display:grid; grid-template-columns:minmax(0,1fr); gap:9px; margin-bottom:12px; }
        .club-dashboard-v7 .pill-row { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:12px; }
        .club-dashboard-v7 .pill { border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.075); color:#fff; border-radius:999px; min-height:32px; padding:0 11px; font-size:11px; font-weight:900; cursor:pointer; }
        .club-dashboard-v7 .pill.is-active { background:color-mix(in srgb, var(--club-primary) 36%, rgba(255,255,255,.08)); }

        .club-dashboard-v7 .player-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; align-items:stretch; }
        .club-dashboard-v7 .player-card { border:0; text-align:left; cursor:pointer; border-radius:22px; overflow:hidden; background:rgba(255,255,255,.055); border:1px solid rgba(255,255,255,.08); color:#fff; min-height:380px; display:flex; flex-direction:column; }
        .club-dashboard-v7 .player-media { height:300px; background:rgba(255,255,255,.035); display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; flex:0 0 300px; }
        .club-dashboard-v7 .player-media.is-plyrcard img { width:100%; height:100%; object-fit:contain !important; border-radius:0 !important; }
        .club-dashboard-v7 .player-media.is-plyrcard::after { content:"PlyrCard"; position:absolute; top:9px; left:9px; height:24px; display:inline-flex; align-items:center; border-radius:999px; padding:0 9px; background:color-mix(in srgb, var(--club-primary) 28%, rgba(0,0,0,.55)); color:#fff; font-size:10px; font-weight:950; letter-spacing:.08em; text-transform:uppercase; }
        .club-dashboard-v7 .avatar-circle { width:172px; height:172px; border-radius:999px; border:1px solid rgba(255,255,255,.15); background:color-mix(in srgb, var(--club-primary) 28%, #151515); display:flex; align-items:center; justify-content:center; font-size:48px; font-weight:950; color:#fff; overflow:hidden; }
        .club-dashboard-v7 .avatar-circle img { width:100%; height:100%; object-fit:cover !important; }
        .club-dashboard-v7 .player-body { padding:12px; flex:1; }
        .club-dashboard-v7 .player-title { color:#fff; font-weight:950; }
        .club-dashboard-v7 .player-meta { color:rgba(255,255,255,.55); margin-top:4px; font-size:12px; }
        .club-dashboard-v7 .player-actions { display:flex; gap:7px; flex-wrap:wrap; margin-top:12px; }
        .club-dashboard-v7 .player-action { display:inline-flex; align-items:center; justify-content:center; min-height:32px; border-radius:999px; padding:0 10px; background:rgba(255,255,255,.09); color:#fff; font-size:11px; font-weight:900; text-decoration:none; border:0; cursor:pointer; }

        .club-dashboard-v7 .schedule-grid { display:grid; gap:10px; }
        .club-dashboard-v7 .schedule-card { border-radius:16px; padding:12px; background:rgba(255,255,255,.055); display:flex; justify-content:space-between; gap:12px; }
        .club-dashboard-v7 .schedule-card span { color:rgba(255,255,255,.55); display:block; font-size:12px; margin-top:3px; }
        .club-dashboard-v7 .loader-wrap { display:none; min-height:180px; align-items:center; justify-content:center; border-radius:20px; background:rgba(255,255,255,.045); }
        .club-dashboard-v7 [wire\:loading].loader-wrap { display:flex; }
        .club-dashboard-v7 .circle-loader { width:42px; height:42px; border-radius:999px; border:4px solid rgba(255,255,255,.18); border-top-color:var(--club-primary); animation:clubSpin .8s linear infinite; }
        @keyframes clubSpin { to { transform:rotate(360deg); } }

        .club-dashboard-v7 .modal-grid { display:grid; gap:12px; }

        @media (max-width:1280px) { .club-dashboard-v7 .player-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
        @media (max-width:980px) { .club-dashboard-v7 .hero-inner, .club-dashboard-v7 .layout { grid-template-columns:1fr; } .club-dashboard-v7 .stat-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } .club-dashboard-v7 .league-logo-grid { grid-template-columns:repeat(4,minmax(0,1fr)); } }
        @media (max-width:560px) { .club-dashboard-v7 .hero-main { flex-direction:column; align-items:flex-start; } .club-dashboard-v7 .actions .btn { width:100%; } .club-dashboard-v7 .player-grid { grid-template-columns:1fr; } .club-dashboard-v7 .league-logo-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    </style>

    <div class="club-dashboard-v7" style="--club-primary: {{ $primary }}; {{ $heroUrl ? "--club-hero-image: url('{$heroUrl}');" : '' }}">
        <section class="hero">
            <div class="hero-inner">
                <div class="hero-main">
                    <div class="club-logo">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $clubName }} logo">
                        @else
                            {{ str($clubName)->substr(0, 2)->upper() }}
                        @endif
                    </div>

                    <div>
                        <p class="kicker">Club Dashboard</p>
                        <h2>{{ $clubName }}</h2>
                        <p class="copy">
                            {{ $selectedProgram['label'] ?? 'Choose a league and gender' }}
                        </p>

                        <div class="actions">
                            @if ($landingUrl)
                                <a href="{{ $landingUrl }}" target="_blank" rel="noopener" class="btn btn-primary">Visit Club Site</a>
                            @endif
                            <button type="button" class="btn" x-data x-on:click="$dispatch('open-modal', { id: 'club-game-modal' })">Create Game</button>
                        </div>
                    </div>
                </div>

                <div class="hero-control">
<p class="control-label">League / Gender</p>

                    <div class="league-logo-grid">
                        @forelse ($leagueOptions as $league)
                            @php
                                $leagueLogo = $assetUrl($league['logo'] ?? null);
                                $isActiveLeague = $this->selectedLeagueKey === $league['key'];
                                $leagueGenders = collect($league['genders'] ?? [])
                                    ->filter(fn ($gender) => in_array($gender, ['male', 'female', 'coed'], true))
                                    ->values();

                                if ($leagueGenders->contains('coed')) {
                                    $leagueGenders = collect(['male', 'female']);
                                }

                                $genderLabels = [
                                    'male' => 'Boys',
                                    'female' => 'Girls',
                                ];
                            @endphp

                            <div class="league-option {{ $isActiveLeague ? 'is-active' : '' }}">
                                <button
                                    type="button"
                                    wire:click="setSelectedLeague(@js($league['key']))"
                                    class="league-logo-button {{ $isActiveLeague ? 'is-active' : '' }}"
                                    title="{{ $league['label'] }}"
                                >
                                    @if ($leagueLogo)
                                        <img src="{{ $leagueLogo }}" alt="{{ $league['label'] }}">
                                    @else
                                        <span class="league-logo-fallback">{{ str($league['label'])->substr(0, 2)->upper() }}</span>
                                    @endif
                                    <span>{{ $league['label'] }}</span>
                                </button>

                                @if ($leagueGenders->isNotEmpty())
                                    <div class="league-genders">
                                        @foreach ($leagueGenders as $genderValue)
                                            @php
                                                $isMale = $genderValue === 'male';
                                                $genderClass = $isMale ? 'is-male' : 'is-female';
                                                $genderLabel = $genderLabels[$genderValue] ?? ucfirst((string) $genderValue);
                                            @endphp

                                            <button
                                                type="button"
                                                wire:click="setSelectedLeagueGender(@js($league['key']), @js($genderValue))"
                                                class="split-gender-button {{ $genderClass }} {{ $isActiveLeague && $this->selectedGender === $genderValue ? 'is-active' : '' }}"
                                                title="{{ $league['label'] }} {{ $genderLabel }}"
                                            >
                                                @if ($leagueLogo)
                                                    <img src="{{ $leagueLogo }}" alt="">
                                                @else
                                                    <span class="split-gender-fallback">{{ str($league['label'])->substr(0, 2)->upper() }}</span>
                                                @endif
                                                <strong>{{ $genderLabel }}</strong>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="empty">No active leagues are associated with this club.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <button type="button" wire:click="selectPanel('players')" class="stat-card {{ in_array($this->activePanel, ['players', 'player'], true) ? 'is-active' : '' }}"><p class="stat-label">Teams</p><p class="stat-value">{{ number_format($stats['teams']) }}</p></button>
            <button type="button" wire:click="selectPanel('players')" class="stat-card {{ in_array($this->activePanel, ['players', 'player'], true) ? 'is-active' : '' }}"><p class="stat-label">Players</p><p class="stat-value">{{ number_format($stats['players']) }}</p></button>
            <button type="button" wire:click="selectPanel('games')" class="stat-card {{ $this->activePanel === 'games' ? 'is-active' : '' }}"><p class="stat-label">Games</p><p class="stat-value">{{ number_format($stats['games']) }}</p></button>
            <button type="button" wire:click="selectPanel('games')" class="stat-card"><p class="stat-label">Upcoming Games</p><p class="stat-value">{{ number_format($stats['upcoming_games']) }}</p></button>
        </section>

        <section class="layout">
            <aside class="panel">
                <div class="panel-head">
                    <h3>Teams</h3>
                    <span class="note">{{ $selectedProgram['label'] ?? 'Select league' }}</span>
                </div>

                <div class="list">
                    @forelse ($ageGroups as $ageGroup)
                        <button type="button" wire:click="selectTeam('{{ $ageGroup['name'] }}')" class="list-item {{ $this->selectedTeam === $ageGroup['name'] ? 'is-active' : '' }}">
                            <div class="list-main">
                                <strong>{{ $ageGroup['name'] }}</strong>
                                <span>{{ number_format($ageGroup['player_count']) }} players • {{ number_format($ageGroup['game_count']) }} games</span>
                            </div>
                            <span class="badge">Open</span>
                        </button>
                    @empty
                        <p class="empty">No active age groups found for this league and gender.</p>
                    @endforelse
                </div>
            </aside>

            <main class="panel">
                <div wire:loading.flex wire:target="setSelectedLeague,setSelectedGender,selectTeam,showTeamGames,selectPanel,selectPlayer,clearSelectedPlayer,createTeamGame,playerSearch,setPlayerPositionFilter,gameSearch,setGameStatusFilter" class="loader-wrap">
                    <div class="circle-loader"></div>
                </div>

                <div wire:loading.remove wire:target="setSelectedLeague,setSelectedGender,selectTeam,showTeamGames,selectPanel,selectPlayer,clearSelectedPlayer,createTeamGame,playerSearch,setPlayerPositionFilter,gameSearch,setGameStatusFilter">
                    @if ($this->activePanel === 'games')
                        <div class="panel-head">
                            <h3>{{ collect([$selectedProgram['label'] ?? null, $this->selectedTeam ? $this->selectedTeam . ' Games' : 'Games'])->filter()->implode(' • ') }}</h3>
                            <button type="button" class="btn" x-data x-on:click="$dispatch('open-modal', { id: 'club-game-modal' })">Create Game</button>
                        </div>

                        <div class="filter-row">
                            <input type="search" wire:model.live.debounce.300ms="gameSearch" placeholder="Search games, opponents, venues...">
                        </div>

                        <div class="pill-row">
                            @foreach (['' => 'All', 'scheduled' => 'Scheduled', 'tentative' => 'Tentative', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                                <button type="button" wire:click="setGameStatusFilter('{{ $value }}')" class="pill {{ ($this->gameStatusFilter ?: '') === $value ? 'is-active' : '' }}">{{ $label }}</button>
                            @endforeach
                        </div>

                        <div class="schedule-grid">
                            @forelse ($this->selectedTeam ? $teamGames : $upcomingGames as $game)
                                <article class="schedule-card">
                                    <div>
                                        <strong>{{ $game->title }}</strong>
                                        <span>{{ collect([$game->opponent ? 'vs ' . $game->opponent : null, $game->game_date?->format('M j, Y'), $game->game_time?->format('g:i A'), $game->venue ?: $game->location])->filter()->implode(' • ') }}</span>
                                    </div>
                                    <span class="badge">{{ $game->users_count }} players</span>
                                </article>
                            @empty
                                <p class="empty">No games found for this league, gender, and team.</p>
                            @endforelse
                        </div>
                    @elseif ($this->activePanel === 'player' && $selectedPlayer)
                        @php
                            $plyrCardImage = \App\Support\ClubManagerAccess::playerPlyrCardImageUrl($selectedPlayer);
                            $profileImage = \App\Support\ClubManagerAccess::playerProfileImageUrl($selectedPlayer);
                            $email = \App\Support\ClubManagerAccess::playerEmail($selectedPlayer);
                            $phone = \App\Support\ClubManagerAccess::playerPhone($selectedPlayer);
                            $website = \App\Support\ClubManagerAccess::playerWebsiteUrl($selectedPlayer);
                        @endphp

                        <div class="panel-head">
                            <h3>{{ \App\Support\ClubManagerAccess::playerDisplayName($selectedPlayer) }}</h3>
                            <button type="button" wire:click="clearSelectedPlayer" class="btn">Back to Players</button>
                        </div>

                        <article class="player-card" style="cursor: default; max-width: 440px;">
                            <div class="player-media {{ $plyrCardImage ? 'is-plyrcard' : '' }}">
                                @if ($plyrCardImage)
                                    <img src="{{ $plyrCardImage }}" alt="{{ \App\Support\ClubManagerAccess::playerDisplayName($selectedPlayer) }}">
                                @elseif ($profileImage)
                                    <div class="avatar-circle"><img src="{{ $profileImage }}" alt="{{ \App\Support\ClubManagerAccess::playerDisplayName($selectedPlayer) }}"></div>
                                @else
                                    <div class="avatar-circle">{{ \App\Support\ClubManagerAccess::playerInitials($selectedPlayer) }}</div>
                                @endif
                            </div>

                            <div class="player-body">
                                <p class="player-title">{{ \App\Support\ClubManagerAccess::playerDisplayName($selectedPlayer) }}</p>
                                <p class="player-meta">{{ collect([$selectedPlayer->team_name, $selectedPlayer->sport, $selectedPlayer->year])->flatten()->filter()->implode(' • ') ?: $selectedPlayer->email }}</p>

                                <div class="player-actions">
                                    @if ($phone)<a class="player-action" href="tel:{{ $phone }}">Contact</a>@endif
                                    @if ($email)<a class="player-action" href="mailto:{{ $email }}">Email</a>@endif
                                    @if ($website)<a class="player-action" href="{{ $website }}" target="_blank" rel="noopener">Website</a>@endif
                                    <button type="button" wire:click="showTeamGames('{{ $selectedPlayer->team_name }}')" class="player-action">Team Games</button>
                                </div>
                            </div>
                        </article>
                    @else
                        <div class="panel-head">
                            <h3>{{ collect([$selectedProgram['label'] ?? null, $this->selectedTeam ? $this->selectedTeam . ' Players' : 'Players'])->filter()->implode(' • ') }}</h3>
                            <span class="note">Click a player to open details</span>
                        </div>

                        <div class="filter-row">
                            <input type="search" wire:model.live.debounce.300ms="playerSearch" placeholder="Search players...">
                        </div>

                        <div class="pill-row">
                            <button type="button" wire:click="setPlayerPositionFilter('')" class="pill {{ blank($this->playerPositionFilter) ? 'is-active' : '' }}">All positions</button>
                            @foreach ($positionOptions as $value => $label)
                                <button type="button" wire:click="setPlayerPositionFilter('{{ $value }}')" class="pill {{ $this->playerPositionFilter === $value ? 'is-active' : '' }}">{{ $label }}</button>
                            @endforeach
                        </div>

                        @if ($players->isEmpty())
                            <p class="empty">No players found for this league, gender, and team.</p>
                        @else
                            <div class="player-grid">
                                @foreach ($players as $player)
                                    @php
                                        $plyrCardImage = \App\Support\ClubManagerAccess::playerPlyrCardImageUrl($player);
                                        $profileImage = \App\Support\ClubManagerAccess::playerProfileImageUrl($player);
                                    @endphp

                                    <button type="button" wire:click="selectPlayer({{ $player->id }})" class="player-card">
                                        <div class="player-media {{ $plyrCardImage ? 'is-plyrcard' : '' }}">
                                            @if ($plyrCardImage)
                                                <img src="{{ $plyrCardImage }}" alt="{{ \App\Support\ClubManagerAccess::playerDisplayName($player) }}">
                                            @elseif ($profileImage)
                                                <div class="avatar-circle"><img src="{{ $profileImage }}" alt="{{ \App\Support\ClubManagerAccess::playerDisplayName($player) }}"></div>
                                            @else
                                                <div class="avatar-circle">{{ \App\Support\ClubManagerAccess::playerInitials($player) }}</div>
                                            @endif
                                        </div>

                                        <div class="player-body">
                                            <p class="player-title">{{ \App\Support\ClubManagerAccess::playerDisplayName($player) }}</p>
                                            <p class="player-meta">{{ collect([$player->position, $player->sport, $player->year])->flatten()->filter()->implode(' • ') ?: 'View player details' }}</p>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            </main>
        </section>

        <x-filament::modal id="club-game-modal" width="lg">
            <x-slot name="heading">Create Game</x-slot>

            <div class="modal-grid">
                <label class="field">League
                    <select wire:model.live="scheduleLeagueKey">
                        @foreach ($leagueOptions as $league)
                            <option value="{{ $league['key'] }}">{{ $league['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">Gender
                    <select wire:model.live="scheduleGender">
                        @foreach ($genderOptions as $gender)
                            <option value="{{ $gender['value'] }}">{{ $gender['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">Team / Age Group
                    <select wire:model.live="scheduleTeamName">
                        @foreach (($ageGroups->isNotEmpty() ? $ageGroups : $allAgeGroups->map(fn ($name) => ['name' => $name])) as $ageGroup)
                            <option value="{{ $ageGroup['name'] }}">{{ $ageGroup['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">Title <input type="text" wire:model="scheduleTitle" placeholder="Game, Training, Showcase"></label>
                <label class="field">Opponent <input type="text" wire:model="scheduleOpponent" placeholder="Optional"></label>
                <label class="field">Date <input type="date" wire:model="scheduleDate"></label>
                <label class="field">Time <input type="time" wire:model="scheduleTime"></label>
                <label class="field">Location <input type="text" wire:model="scheduleLocation" placeholder="City / Field"></label>
                <label class="field">Venue <input type="text" wire:model="scheduleVenue" placeholder="Optional"></label>

                <div class="pill-row">
                    @foreach (['scheduled' => 'Scheduled', 'tentative' => 'Tentative', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                        <button type="button" wire:click="$set('scheduleStatus', '{{ $value }}')" class="pill {{ $this->scheduleStatus === $value ? 'is-active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>

                <label class="field">Notes <textarea wire:model="scheduleNotes" placeholder="Optional"></textarea></label>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                    <button type="button" class="btn" x-data x-on:click="$dispatch('close-modal', { id: 'club-game-modal' })">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="createTeamGame">Create Game</button>
                </div>
            </div>
        </x-filament::modal>
    </div>
</x-filament-panels::page>