<x-filament-panels::page>
    @php
        $club = $this->assignedClub;
        $stats = $this->stats;
        $ageGroups = $this->ageGroups;
        $programs = $this->programs;
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
    @endphp

    <style>
        .club-dashboard-v4, .club-dashboard-v4 * { box-sizing: border-box; }
        .club-dashboard-v4 select:not(.club-dark-select), .club-dashboard-v4 option:not(.club-dark-select option) { display: none !important; }

        .club-dashboard-v4 { display:grid; gap:16px; --card:#111; --muted:rgba(255,255,255,.55); }
        .club-dashboard-v4 .hero { position:relative; overflow:hidden; border-radius:28px; padding:22px; background:linear-gradient(135deg, rgba(0,0,0,.78), rgba(0,0,0,.92)), var(--club-hero-image, linear-gradient(135deg, #111, #050505)); background-size:cover; background-position:center; color:#fff; border:1px solid rgba(255,255,255,.1); }
        .club-dashboard-v4 .hero::before { content:""; position:absolute; inset:0; background:radial-gradient(circle at 12% 12%, color-mix(in srgb, var(--club-primary) 42%, transparent), transparent 34%); pointer-events:none; }
        .club-dashboard-v4 .hero-inner { position:relative; z-index:1; display:flex; align-items:center; gap:16px; }
        .club-dashboard-v4 .club-logo { width:76px; height:76px; border-radius:21px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; overflow:hidden; flex:0 0 76px; font-size:26px; font-weight:950; }
        .club-dashboard-v4 .club-logo img { width:100%; height:100%; object-fit:cover; }
        .club-dashboard-v4 .kicker { margin:0 0 8px; color:rgba(255,255,255,.58); font-size:11px; letter-spacing:.16em; text-transform:uppercase; font-weight:900; }
        .club-dashboard-v4 h2 { margin:0; font-size:clamp(30px, 5vw, 54px); line-height:.94; letter-spacing:-.05em; font-weight:950; }
        .club-dashboard-v4 p { margin:0; }
        .club-dashboard-v4 .hero p.copy { margin-top:10px; max-width:760px; color:rgba(255,255,255,.72); font-size:14px; line-height:1.55; }
        .club-dashboard-v4 .actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:16px; }
        .club-dashboard-v4 .btn { display:inline-flex; align-items:center; justify-content:center; min-height:40px; border-radius:999px; padding:0 15px; text-decoration:none; font-size:12px; font-weight:900; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.08); color:#fff; cursor:pointer; }
        .club-dashboard-v4 .btn-primary { color:#120806; background:linear-gradient(135deg, #fff2de, var(--club-primary)); border-color:color-mix(in srgb, var(--club-primary) 50%, transparent); }

        .club-dashboard-v4 .stat-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:10px; }
        .club-dashboard-v4 .stat-card { border:0; text-align:left; cursor:pointer; border-radius:20px; padding:14px; background:#111; border:1px solid rgba(255,255,255,.09); color:#fff; box-shadow:0 16px 44px rgba(0,0,0,.14); }
        .club-dashboard-v4 .stat-card:hover { background:#151515; }
        .club-dashboard-v4 .stat-card.is-active { outline:2px solid color-mix(in srgb, var(--club-primary) 65%, transparent); }
        .club-dashboard-v4 .stat-label { color:rgba(255,255,255,.5); font-size:10px; letter-spacing:.11em; text-transform:uppercase; font-weight:900; }
        .club-dashboard-v4 .stat-value { margin-top:7px; font-size:29px; line-height:1; font-weight:950; letter-spacing:-.04em; }

        .club-dashboard-v4 .layout { display:grid; grid-template-columns:360px minmax(0,1fr); gap:14px; align-items:start; }
                .club-dashboard-v4 .panel { border-radius:22px; padding:15px; background:#111; border:1px solid rgba(255,255,255,.09); color:#fff; box-shadow:0 18px 50px rgba(0,0,0,.15); min-width:0; }
        .club-dashboard-v4 .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
        .club-dashboard-v4 .panel h3 { margin:0; font-size:18px; font-weight:950; letter-spacing:-.02em; }
        .club-dashboard-v4 .note { color:rgba(255,255,255,.48); font-size:12px; font-weight:700; }
        .club-dashboard-v4 .list { display:grid; gap:9px; }
        .club-dashboard-v4 .list-item { width:100%; display:flex; justify-content:space-between; gap:12px; border-radius:16px; padding:12px; background:rgba(255,255,255,.055); min-width:0; border:0; color:inherit; text-align:left; cursor:pointer; }
        .club-dashboard-v4 .list-item:hover { background:rgba(255,255,255,.085); }
        .club-dashboard-v4 .list-item.is-active { background:color-mix(in srgb, var(--club-primary) 20%, rgba(255,255,255,.055)); }
        .club-dashboard-v4 .list-main { min-width:0; }
        .club-dashboard-v4 .list-main strong { display:block; color:#fff; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .club-dashboard-v4 .list-main span { display:block; color:rgba(255,255,255,.55); font-size:12px; margin-top:3px; }
        .club-dashboard-v4 .badge { display:inline-flex; align-items:center; height:27px; border-radius:999px; padding:0 10px; background:color-mix(in srgb, var(--club-primary) 20%, transparent); color:#ffd1c7; font-size:11px; font-weight:900; white-space:nowrap; }
        .club-dashboard-v4 .empty { color:rgba(255,255,255,.58); font-size:13px; line-height:1.55; }

        .club-dashboard-v4 .filter-row { display:grid; grid-template-columns:minmax(0,1fr); gap:9px; margin-bottom:12px; }
        .club-dashboard-v4 .filter-row input, .club-dashboard-v4 .modal-field {
            min-height:42px; border-radius:13px; border:1px solid rgba(255,255,255,.12); background:#1b1b1d !important; color:#fff !important; padding:0 12px; font-size:13px; color-scheme:dark;
        }
        .club-dashboard-v4 .pill-row { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:12px; }
        .club-dashboard-v4 .pill { border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.075); color:#fff; border-radius:999px; min-height:32px; padding:0 11px; font-size:11px; font-weight:900; cursor:pointer; }
        .club-dashboard-v4 .pill.is-active { background:color-mix(in srgb, var(--club-primary) 36%, rgba(255,255,255,.08)); border-color:color-mix(in srgb, var(--club-primary) 52%, rgba(255,255,255,.12)); }

        .club-dashboard-v4 .player-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; align-items:stretch; }
        .club-dashboard-v4 .player-card { border:0; text-align:left; cursor:pointer; border-radius:22px; overflow:hidden; background:rgba(255,255,255,.055); border:1px solid rgba(255,255,255,.08); color:#fff; min-height:392px; display:flex; flex-direction:column; }
        .club-dashboard-v4 .player-card:hover { background:rgba(255,255,255,.08); box-shadow:0 18px 50px rgba(0,0,0,.2); }
        .club-dashboard-v4 .player-media { height:310px; background:linear-gradient(135deg, rgba(255,255,255,.08), rgba(255,255,255,.02)); display:flex; align-items:center; justify-content:center; color:#fff; position:relative; overflow:hidden; flex:0 0 310px; }
        .club-dashboard-v4 .player-media.is-plyrcard { background:radial-gradient(circle at 30% 10%, color-mix(in srgb, var(--club-primary) 22%, transparent), transparent 38%), rgba(255,255,255,.035); }
        .club-dashboard-v4 .player-media.is-plyrcard img { width:100%; height:100%; object-fit:contain !important; object-position:center center; padding:0; border-radius:0 !important; }
        .club-dashboard-v4 .player-media.is-plyrcard::after { content:"PlyrCard"; position:absolute; top:9px; left:9px; height:24px; display:inline-flex; align-items:center; border-radius:999px; padding:0 9px; background:color-mix(in srgb, var(--club-primary) 28%, rgba(0,0,0,.55)); color:#fff; font-size:10px; font-weight:950; letter-spacing:.08em; text-transform:uppercase; }
        .club-dashboard-v4 .player-media.is-avatar { padding:30px; }
        .club-dashboard-v4 .avatar-circle { width:172px; height:172px; border-radius:999px; border:1px solid rgba(255,255,255,.15); background:radial-gradient(circle at 30% 20%, rgba(255,255,255,.18), transparent 38%), color-mix(in srgb, var(--club-primary) 28%, #151515); display:flex; align-items:center; justify-content:center; font-size:48px; font-weight:950; color:#fff; overflow:hidden; }
        .club-dashboard-v4 .avatar-circle img { width:100%; height:100%; object-fit:cover !important; }
        .club-dashboard-v4 .player-body { padding:12px; flex:1; }
        .club-dashboard-v4 .player-title { color:#fff; font-weight:950; margin:0; }
        .club-dashboard-v4 .player-meta { color:rgba(255,255,255,.55); margin:4px 0 0; font-size:12px; }
        .club-dashboard-v4 .player-actions { display:flex; gap:7px; flex-wrap:wrap; margin-top:12px; }
        .club-dashboard-v4 .player-action { display:inline-flex; align-items:center; justify-content:center; min-height:32px; border-radius:999px; padding:0 10px; background:rgba(255,255,255,.09); border:1px solid rgba(255,255,255,.09); color:#fff; font-size:11px; font-weight:900; text-decoration:none; }

        .club-dashboard-v4 .schedule-grid { display:grid; gap:10px; }
        .club-dashboard-v4 .schedule-card { border-radius:16px; padding:12px; background:rgba(255,255,255,.055); display:flex; justify-content:space-between; gap:12px; }
        .club-dashboard-v4 .schedule-card strong { color:#fff; display:block; }
        .club-dashboard-v4 .schedule-card span { color:rgba(255,255,255,.55); display:block; font-size:12px; margin-top:3px; }
        .club-dashboard-v4 .loader-wrap { display:none; min-height:180px; align-items:center; justify-content:center; border-radius:20px; background:rgba(255,255,255,.045); }
        .club-dashboard-v4 [wire\:loading].loader-wrap { display:flex; }
        .club-dashboard-v4 .circle-loader { width:42px; height:42px; border-radius:999px; border:4px solid rgba(255,255,255,.18); border-top-color:var(--club-primary); animation:clubSpin .8s linear infinite; }
        @keyframes clubSpin { to { transform:rotate(360deg); } }

        .club-dashboard-v4 .modal-grid { display:grid; gap:12px; }
        .club-dashboard-v4 .modal-grid label { display:grid; gap:6px; color:rgba(255,255,255,.75); font-size:12px; font-weight:900; }
        .club-dashboard-v4 textarea.modal-field { padding:12px; min-height:86px; }
        .club-dashboard-v4 .club-dark-select {
            display:block !important;
            width:100%;
            appearance:auto;
            -webkit-appearance:auto;
            background:#1b1b1d !important;
            color:#fff !important;
            color-scheme:dark;
        }
        .club-dashboard-v4 .club-dark-select option {
            display:block !important;
            background:#151518 !important;
            color:#fff !important;
        }

        @media (max-width:1280px) { .club-dashboard-v4 .player-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
        @media (max-width:980px) { .club-dashboard-v4 .layout { grid-template-columns:1fr; } .club-dashboard-v4 .stat-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }  }
        @media (max-width:560px) {
            .club-dashboard-v4 .hero { border-radius:20px; padding:18px; }
            .club-dashboard-v4 .hero-inner { align-items:flex-start; flex-direction:column; }
            .club-dashboard-v4 .btn { width:100%; }
            .club-dashboard-v4 .player-grid { grid-template-columns:1fr; }
            .club-dashboard-v4 .stat-grid { grid-template-columns:repeat(2, minmax(0,1fr)); gap:8px; }
            .club-dashboard-v4 .stat-card { padding:12px; border-radius:17px; }
            .club-dashboard-v4 .stat-value { font-size:25px; }
            .club-dashboard-v4 .player-media { height:285px; flex-basis:285px; }
        }
    </style>

    <div class="club-dashboard-v4" style="--club-primary: {{ $primary }}; {{ $heroUrl ? "--club-hero-image: url('{$heroUrl}');" : '' }}">
        <section class="hero">
            <div class="hero-inner">
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

                    @if ($club)
                        <p class="copy">Manage teams, player access, and shared games from one focused dashboard.</p>
                        <div class="actions">
                            @if ($landingUrl)
                                <a href="{{ $landingUrl }}" target="_blank" rel="noopener" class="btn btn-primary">Visit Club Site</a>
                            @endif
                            <button type="button" class="btn" x-data x-on:click="$dispatch('open-modal', { id: 'club-invite-modal' })">Send Invite</button>
                            <button type="button" class="btn" x-data x-on:click="$dispatch('open-modal', { id: 'club-game-modal' })">Create Game</button>
                        </div>
                    @else
                        <p class="copy">This account does not have a club assigned yet. Assign one club through the user editor.</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <button type="button" wire:click="selectPanel('teams')" class="stat-card {{ $this->activePanel === 'teams' ? 'is-active' : '' }}"><p class="stat-label">Teams</p><p class="stat-value">{{ number_format($stats['teams']) }}</p></button>
            <button type="button" wire:click="selectPanel('players')" class="stat-card {{ $this->activePanel === 'players' ? 'is-active' : '' }}"><p class="stat-label">Players</p><p class="stat-value">{{ number_format($stats['players']) }}</p></button>
            <button type="button" wire:click="selectPanel('games')" class="stat-card {{ $this->activePanel === 'games' ? 'is-active' : '' }}"><p class="stat-label">Games</p><p class="stat-value">{{ number_format($stats['games']) }}</p></button>
            <button type="button" wire:click="selectPanel('games')" class="stat-card"><p class="stat-label">Upcoming Games</p><p class="stat-value">{{ number_format($stats['upcoming_games']) }}</p></button>
        </section>

        <section class="layout">
            <aside class="panel team-panel">
                <div class="panel-head">
                    <h3>Teams</h3>
                    <span class="note">U13-U19</span>
                </div>

                <div class="list">
                    @foreach ($ageGroups as $ageGroup)
                        <button type="button" wire:click="selectTeam('{{ $ageGroup['name'] }}')" class="list-item {{ $this->selectedTeam === $ageGroup['name'] ? 'is-active' : '' }}">
                            <div class="list-main">
                                <strong>{{ $ageGroup['name'] }}</strong>
                                <span>{{ number_format($ageGroup['player_count']) }} players • {{ number_format($ageGroup['game_count']) }} games</span>
                            </div>
                            <span class="badge">Open</span>
                        </button>
                    @endforeach
                </div>
            </aside>

            <main class="panel">
                    <div wire:loading.flex wire:target="selectTeam,showTeamGames,selectPanel,selectPlayer,clearSelectedPlayer,createTeamGame,playerSearch,setPlayerPositionFilter,gameSearch,setGameStatusFilter" class="loader-wrap">
                        <div class="circle-loader"></div>
                    </div>

                    <div wire:loading.remove wire:target="selectTeam,showTeamGames,selectPanel,selectPlayer,clearSelectedPlayer,createTeamGame,playerSearch,setPlayerPositionFilter,gameSearch,setGameStatusFilter">
                        @if ($this->activePanel === 'games')
                            <div class="panel-head">
                                <h3>{{ $this->selectedTeam ? $this->selectedTeam . ' Games' : 'Games' }}</h3>
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
                                    <p class="empty">No games found.</p>
                                @endforelse
                            </div>

                        @elseif ($this->activePanel === 'player' && $selectedPlayer)
                            @php
                                $image = \App\Support\ClubManagerAccess::playerImageUrl($selectedPlayer);
                                $email = \App\Support\ClubManagerAccess::playerEmail($selectedPlayer);
                                $phone = \App\Support\ClubManagerAccess::playerPhone($selectedPlayer);
                                $website = \App\Support\ClubManagerAccess::playerWebsiteUrl($selectedPlayer);
                            @endphp

                            <div class="panel-head">
                                <h3>{{ \App\Support\ClubManagerAccess::playerDisplayName($selectedPlayer) }}</h3>
                                <button type="button" wire:click="clearSelectedPlayer" class="btn">Back to Players</button>
                            </div>

                            <article class="player-card" style="cursor: default; max-width: 440px;">
                                <div class="player-media {{ $image ? 'is-plyrcard' : 'is-avatar' }}">
                                    @if ($image)
                                        <img src="{{ $image }}" alt="{{ \App\Support\ClubManagerAccess::playerDisplayName($selectedPlayer) }}">
                                    @elseif ($image)
                                        <div class="avatar-circle"><img src="{{ $image }}" alt="{{ \App\Support\ClubManagerAccess::playerDisplayName($selectedPlayer) }}"></div>
                                    @else
                                        <div class="avatar-circle">{{ \App\Support\ClubManagerAccess::playerInitials($selectedPlayer) }}</div>
                                    @endif
                                </div>

                                <div class="player-body">
                                    <p class="player-title">{{ \App\Support\ClubManagerAccess::playerDisplayName($selectedPlayer) }}</p>
                                    <p class="player-meta">{{ collect([$selectedPlayer->team_name, $selectedPlayer->sport, $selectedPlayer->year])->flatten()->filter()->implode(' • ') ?: $selectedPlayer->email }}</p>

                                    <div class="player-actions">
                                        @if ($phone)
                                            <a class="player-action" href="tel:{{ $phone }}">Contact</a>
                                        @endif
                                        @if ($email)
                                            <a class="player-action" href="mailto:{{ $email }}">Email</a>
                                        @endif
                                        @if ($website)
                                            <a class="player-action" href="{{ $website }}" target="_blank" rel="noopener">Website</a>
                                        @endif
                                        <button type="button" wire:click="showTeamGames('{{ $selectedPlayer->team_name }}')" class="player-action">Team Games</button>
                                    </div>
                                </div>
                            </article>

                        @else
                            <div class="panel-head">
                                <h3>{{ $this->selectedTeam ? $this->selectedTeam . ' Players' : 'Players' }}</h3>
                                @if ($this->selectedTeam)
                                    <button type="button" wire:click="clearSelectedTeam" class="btn">Back to Teams</button>
                                @else
                                    <span class="note">Choose a team to view players</span>
                                @endif
                            </div>

                            @if (! $this->selectedTeam)
                                <p class="empty">Player names are hidden until a team is selected.</p>
                            @else
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
                                    <p class="empty">No players found for {{ $this->selectedTeam }}.</p>
                                @else
                                    <div class="player-grid">
                                        @foreach ($players as $player)
                                            @php
                                                $image = \App\Support\ClubManagerAccess::playerImageUrl($player);
                                            @endphp

                                            <button type="button" wire:click="selectPlayer({{ $player->id }})" class="player-card">
                                                <div class="player-media {{ $image ? 'is-plyrcard' : 'is-avatar' }}">
                                                    @if ($image)
                                                        <img src="{{ $image }}" alt="{{ \App\Support\ClubManagerAccess::playerDisplayName($player) }}">
                                                    @elseif ($image)
                                                        <div class="avatar-circle"><img src="{{ $image }}" alt="{{ \App\Support\ClubManagerAccess::playerDisplayName($player) }}"></div>
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
                        @endif
                    </div>
                </main>
        </section>

        <x-filament::modal id="club-invite-modal" width="lg">
            <x-slot name="heading">Send Invite</x-slot>

            <div class="modal-grid">
                <label>League
                    <select
                        class="modal-field club-dark-select"
                        wire:model.live="inviteClubLeagueId"
                        x-data
                        x-on:change="$wire.set('inviteClubLeagueId', $event.target.value)"
                    >
                        <option value="">Select a league</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">
                                {{ collect([$program->league?->name, $program->sport ?: $program->league?->sport, collect($program->genders ?? [])->filter()->implode('/')])->filter()->implode(' • ') }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>Team / Age Group
                    <select
                        class="modal-field club-dark-select"
                        wire:model.live="inviteTeamName"
                        x-data
                        x-on:change="$wire.set('inviteTeamName', $event.target.value)"
                    >
                        @foreach ($ageGroups as $ageGroup)
                            <option value="{{ $ageGroup['name'] }}">{{ $ageGroup['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label>Invitee Name <input class="modal-field" type="text" wire:model="inviteName" placeholder="Optional"></label>
                <label>Invitee Email <input class="modal-field" type="email" wire:model="inviteEmail" placeholder="Optional"></label>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                    <button type="button" class="btn" x-data x-on:click="$dispatch('close-modal', { id: 'club-invite-modal' })">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="createInvite">Create Invite</button>
                </div>
            </div>
        </x-filament::modal>

        <x-filament::modal id="club-game-modal" width="lg">
            <x-slot name="heading">Create Game</x-slot>

            <div class="modal-grid">
                <label>League
                    <select
                        class="modal-field club-dark-select"
                        wire:model.live="scheduleClubLeagueId"
                        x-data
                        x-on:change="$wire.set('scheduleClubLeagueId', $event.target.value)"
                    >
                        <option value="">Select a league</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">
                                {{ collect([$program->league?->name, $program->sport ?: $program->league?->sport, collect($program->genders ?? [])->filter()->implode('/')])->filter()->implode(' • ') }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>Team / Age Group
                    <select
                        class="modal-field club-dark-select"
                        wire:model.live="scheduleTeamName"
                        x-data
                        x-on:change="$wire.set('scheduleTeamName', $event.target.value)"
                    >
                        @foreach ($ageGroups as $ageGroup)
                            <option value="{{ $ageGroup['name'] }}">{{ $ageGroup['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label>Title <input class="modal-field" type="text" wire:model="scheduleTitle" placeholder="Game, Training, Showcase"></label>
                <label>Opponent <input class="modal-field" type="text" wire:model="scheduleOpponent" placeholder="Optional"></label>
                <label>Date <input class="modal-field" type="date" wire:model="scheduleDate"></label>
                <label>Time <input class="modal-field" type="time" wire:model="scheduleTime"></label>
                <label>Location <input class="modal-field" type="text" wire:model="scheduleLocation" placeholder="City / Field"></label>
                <label>Venue <input class="modal-field" type="text" wire:model="scheduleVenue" placeholder="Optional"></label>

                <div class="pill-row">
                    @foreach (['scheduled' => 'Scheduled', 'tentative' => 'Tentative', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                        <button type="button" wire:click="$set('scheduleStatus', '{{ $value }}')" class="pill {{ $this->scheduleStatus === $value ? 'is-active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>

                <label>Notes <textarea class="modal-field" wire:model="scheduleNotes" placeholder="Optional"></textarea></label>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                    <button type="button" class="btn" x-data x-on:click="$dispatch('close-modal', { id: 'club-game-modal' })">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="createTeamGame">Create Game</button>
                </div>
            </div>
        </x-filament::modal>
    </div>
</x-filament-panels::page>