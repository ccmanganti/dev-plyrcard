<x-filament-panels::page>
    @php
        $club = $this->assignedClub;
        $stats = $this->stats;
        $ageGroups = $this->ageGroups;
        $programOptions = $this->programOptions;
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
    @endphp

    <style>
        .club-dashboard-v6, .club-dashboard-v6 * { box-sizing: border-box; }
        .club-dashboard-v6 { display:grid; gap:16px; }
        .club-dashboard-v6 .hero { position:relative; overflow:hidden; border-radius:28px; padding:22px; background:linear-gradient(135deg, rgba(0,0,0,.78), rgba(0,0,0,.92)), var(--club-hero-image, linear-gradient(135deg, #111, #050505)); background-size:cover; color:#fff; border:1px solid rgba(255,255,255,.1); }
        .club-dashboard-v6 .hero::before { content:""; position:absolute; inset:0; background:radial-gradient(circle at 12% 12%, color-mix(in srgb, var(--club-primary) 42%, transparent), transparent 34%); pointer-events:none; }
        .club-dashboard-v6 .hero-inner { position:relative; z-index:1; display:flex; align-items:center; gap:16px; }
        .club-dashboard-v6 .club-logo { width:76px; height:76px; border-radius:21px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; overflow:hidden; flex:0 0 76px; font-size:26px; font-weight:950; }
        .club-dashboard-v6 .club-logo img { width:100%; height:100%; object-fit:cover; }
        .club-dashboard-v6 .kicker { margin:0 0 8px; color:rgba(255,255,255,.58); font-size:11px; letter-spacing:.16em; text-transform:uppercase; font-weight:900; }
        .club-dashboard-v6 h2 { margin:0; font-size:clamp(30px, 5vw, 54px); line-height:.94; letter-spacing:-.05em; font-weight:950; }
        .club-dashboard-v6 p { margin:0; }
        .club-dashboard-v6 .copy { margin-top:10px; max-width:760px; color:rgba(255,255,255,.72); font-size:14px; line-height:1.55; }
        .club-dashboard-v6 .actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:16px; }
        .club-dashboard-v6 .btn { display:inline-flex; align-items:center; justify-content:center; min-height:40px; border-radius:999px; padding:0 15px; text-decoration:none; font-size:12px; font-weight:900; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.08); color:#fff; cursor:pointer; }
        .club-dashboard-v6 .btn-primary { color:#120806; background:linear-gradient(135deg, #fff2de, var(--club-primary)); }

        .club-dashboard-v6 .stat-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:10px; }
        .club-dashboard-v6 .stat-card { border:0; text-align:left; cursor:pointer; border-radius:20px; padding:14px; background:#111; border:1px solid rgba(255,255,255,.09); color:#fff; }
        .club-dashboard-v6 .stat-card.is-active { outline:2px solid color-mix(in srgb, var(--club-primary) 65%, transparent); }
        .club-dashboard-v6 .stat-label { color:rgba(255,255,255,.5); font-size:10px; letter-spacing:.11em; text-transform:uppercase; font-weight:900; }
        .club-dashboard-v6 .stat-value { margin-top:7px; font-size:29px; line-height:1; font-weight:950; }

        .club-dashboard-v6 .layout { display:grid; grid-template-columns:360px minmax(0,1fr); gap:14px; align-items:start; }
        .club-dashboard-v6 .panel { border-radius:22px; padding:15px; background:#111; border:1px solid rgba(255,255,255,.09); color:#fff; min-width:0; }
        .club-dashboard-v6 .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
        .club-dashboard-v6 .panel h3 { margin:0; font-size:18px; font-weight:950; }
        .club-dashboard-v6 .note { color:rgba(255,255,255,.48); font-size:12px; font-weight:700; }
        .club-dashboard-v6 .list { display:grid; gap:9px; }
        .club-dashboard-v6 .list-item { width:100%; display:flex; justify-content:space-between; gap:12px; border-radius:16px; padding:12px; background:rgba(255,255,255,.055); border:0; color:inherit; text-align:left; cursor:pointer; }
        .club-dashboard-v6 .list-item:hover { background:rgba(255,255,255,.085); }
        .club-dashboard-v6 .list-item.is-active { background:color-mix(in srgb, var(--club-primary) 20%, rgba(255,255,255,.055)); }
        .club-dashboard-v6 .list-main strong { display:block; color:#fff; }
        .club-dashboard-v6 .list-main span { display:block; color:rgba(255,255,255,.55); font-size:12px; margin-top:3px; }
        .club-dashboard-v6 .badge { display:inline-flex; align-items:center; height:27px; border-radius:999px; padding:0 10px; background:color-mix(in srgb, var(--club-primary) 20%, transparent); color:#ffd1c7; font-size:11px; font-weight:900; white-space:nowrap; }
        .club-dashboard-v6 .empty { color:rgba(255,255,255,.58); font-size:13px; line-height:1.55; }

        .club-dashboard-v6 input, .club-dashboard-v6 select, .club-dashboard-v6 textarea { width:100%; min-height:42px; border-radius:13px; border:1px solid rgba(255,255,255,.12); background:#1b1b1d !important; color:#fff !important; padding:0 12px; font-size:13px; color-scheme:dark; }
        .club-dashboard-v6 select option { background:#151518 !important; color:#fff !important; }
        .club-dashboard-v6 textarea { padding:12px; min-height:86px; }
        .club-dashboard-v6 .field { display:grid; gap:6px; color:rgba(255,255,255,.75); font-size:12px; font-weight:900; }
        .club-dashboard-v6 .filter-row { display:grid; grid-template-columns:minmax(0,1fr); gap:9px; margin-bottom:12px; }
        .club-dashboard-v6 .pill-row { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:12px; }
        .club-dashboard-v6 .pill { border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.075); color:#fff; border-radius:999px; min-height:32px; padding:0 11px; font-size:11px; font-weight:900; cursor:pointer; }
        .club-dashboard-v6 .pill.is-active { background:color-mix(in srgb, var(--club-primary) 36%, rgba(255,255,255,.08)); }

        .club-dashboard-v6 .player-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; align-items:stretch; }
        .club-dashboard-v6 .player-card { border:0; text-align:left; cursor:pointer; border-radius:22px; overflow:hidden; background:rgba(255,255,255,.055); border:1px solid rgba(255,255,255,.08); color:#fff; min-height:380px; display:flex; flex-direction:column; }
        .club-dashboard-v6 .player-media { height:300px; background:rgba(255,255,255,.035); display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; flex:0 0 300px; }
        .club-dashboard-v6 .player-media.is-plyrcard img { width:100%; height:100%; object-fit:contain !important; border-radius:0 !important; }
        .club-dashboard-v6 .player-media.is-plyrcard::after { content:"PlyrCard"; position:absolute; top:9px; left:9px; height:24px; display:inline-flex; align-items:center; border-radius:999px; padding:0 9px; background:color-mix(in srgb, var(--club-primary) 28%, rgba(0,0,0,.55)); color:#fff; font-size:10px; font-weight:950; letter-spacing:.08em; text-transform:uppercase; }
        .club-dashboard-v6 .avatar-circle { width:172px; height:172px; border-radius:999px; border:1px solid rgba(255,255,255,.15); background:color-mix(in srgb, var(--club-primary) 28%, #151515); display:flex; align-items:center; justify-content:center; font-size:48px; font-weight:950; color:#fff; overflow:hidden; }
        .club-dashboard-v6 .avatar-circle img { width:100%; height:100%; object-fit:cover !important; }
        .club-dashboard-v6 .player-body { padding:12px; flex:1; }
        .club-dashboard-v6 .player-title { color:#fff; font-weight:950; }
        .club-dashboard-v6 .player-meta { color:rgba(255,255,255,.55); margin-top:4px; font-size:12px; }
        .club-dashboard-v6 .player-actions { display:flex; gap:7px; flex-wrap:wrap; margin-top:12px; }
        .club-dashboard-v6 .player-action { display:inline-flex; align-items:center; justify-content:center; min-height:32px; border-radius:999px; padding:0 10px; background:rgba(255,255,255,.09); color:#fff; font-size:11px; font-weight:900; text-decoration:none; border:0; cursor:pointer; }

        .club-dashboard-v6 .schedule-grid { display:grid; gap:10px; }
        .club-dashboard-v6 .schedule-card { border-radius:16px; padding:12px; background:rgba(255,255,255,.055); display:flex; justify-content:space-between; gap:12px; }
        .club-dashboard-v6 .schedule-card span { color:rgba(255,255,255,.55); display:block; font-size:12px; margin-top:3px; }
        .club-dashboard-v6 .loader-wrap { display:none; min-height:180px; align-items:center; justify-content:center; border-radius:20px; background:rgba(255,255,255,.045); }
        .club-dashboard-v6 [wire\:loading].loader-wrap { display:flex; }
        .club-dashboard-v6 .circle-loader { width:42px; height:42px; border-radius:999px; border:4px solid rgba(255,255,255,.18); border-top-color:var(--club-primary); animation:clubSpin .8s linear infinite; }
        @keyframes clubSpin { to { transform:rotate(360deg); } }

        .club-dashboard-v6 .modal-grid { display:grid; gap:12px; }

        @media (max-width:1280px) { .club-dashboard-v6 .player-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
        @media (max-width:980px) { .club-dashboard-v6 .layout { grid-template-columns:1fr; } .club-dashboard-v6 .stat-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
        @media (max-width:560px) { .club-dashboard-v6 .hero-inner { flex-direction:column; align-items:flex-start; } .club-dashboard-v6 .actions .btn { width:100%; } .club-dashboard-v6 .player-grid { grid-template-columns:1fr; } }
    </style>

    <div class="club-dashboard-v6" style="--club-primary: {{ $primary }}; {{ $heroUrl ? "--club-hero-image: url('{$heroUrl}');" : '' }}">
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
                    <p class="copy">Choose one league/program first, then manage U13-U19 teams for that league.</p>

                    <div class="actions">
                        @if ($landingUrl)
                            <a href="{{ $landingUrl }}" target="_blank" rel="noopener" class="btn btn-primary">Visit Club Site</a>
                        @endif
                        <button type="button" class="btn" x-data x-on:click="$dispatch('open-modal', { id: 'club-invite-modal' })">Send Invite</button>
                        <button type="button" class="btn" x-data x-on:click="$dispatch('open-modal', { id: 'club-game-modal' })">Create Game</button>
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
                    <h3>League</h3>
                    <span class="note">De-duplicated</span>
                </div>

                <label class="field" style="margin-bottom:14px;">
                    <select
                        wire:model.live="selectedProgramKey"
                        x-data
                        x-on:change="$wire.call('setSelectedProgram', $event.target.value)"
                    >
                        @foreach ($programOptions as $program)
                            <option value="{{ $program['key'] }}">{{ $program['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="panel-head">
                    <h3>Teams</h3>
                    <span class="note">{{ $selectedProgram['label'] ?? 'U13-U19' }}</span>
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
                <div wire:loading.flex wire:target="setSelectedProgram,selectTeam,showTeamGames,selectPanel,selectPlayer,clearSelectedPlayer,createTeamGame,playerSearch,setPlayerPositionFilter,gameSearch,setGameStatusFilter" class="loader-wrap">
                    <div class="circle-loader"></div>
                </div>

                <div wire:loading.remove wire:target="setSelectedProgram,selectTeam,showTeamGames,selectPanel,selectPlayer,clearSelectedPlayer,createTeamGame,playerSearch,setPlayerPositionFilter,gameSearch,setGameStatusFilter">
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
                                <p class="empty">No games found for this league/team.</p>
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
                            <p class="empty">No players found for this league/team.</p>
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

        <x-filament::modal id="club-invite-modal" width="lg">
            <x-slot name="heading">Send Invite</x-slot>

            <div class="modal-grid">
                <label class="field">League
                    <select wire:model.live="inviteProgramKey">
                        @foreach ($programOptions as $program)
                            <option value="{{ $program['key'] }}">{{ $program['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">Team / Age Group
                    <select wire:model.live="inviteTeamName">
                        @foreach ($ageGroups as $ageGroup)
                            <option value="{{ $ageGroup['name'] }}">{{ $ageGroup['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">Invitee Name <input type="text" wire:model="inviteName" placeholder="Optional"></label>
                <label class="field">Invitee Email <input type="email" wire:model="inviteEmail" placeholder="Optional"></label>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                    <button type="button" class="btn" x-data x-on:click="$dispatch('close-modal', { id: 'club-invite-modal' })">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="createInvite">Create Invite</button>
                </div>
            </div>
        </x-filament::modal>

        <x-filament::modal id="club-game-modal" width="lg">
            <x-slot name="heading">Create Game</x-slot>

            <div class="modal-grid">
                <label class="field">League
                    <select wire:model.live="scheduleProgramKey">
                        @foreach ($programOptions as $program)
                            <option value="{{ $program['key'] }}">{{ $program['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">Team / Age Group
                    <select wire:model.live="scheduleTeamName">
                        @foreach ($ageGroups as $ageGroup)
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