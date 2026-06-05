<x-filament-panels::page>
    @php
        $club = $this->assignedClub;
        $stats = $this->stats;
        $ageGroups = $this->ageGroups;
        $programs = $this->programs;
        $players = $this->selectedTeamPlayers;
        $invites = $this->invites;

        $clubName = $club?->name ?? 'No club assigned';
        $landingUrl = $club?->landingUrl();
        $logoUrl = $club?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($club->logo) : null;
        $heroUrl = $club?->background_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($club->background_image) : null;
        $primary = $club?->primary_color ?: '#ff5c35';
    @endphp

    <style>
        .club-dashboard-shell { display: grid; gap: 18px; }
        .club-dashboard-hero {
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            padding: 24px;
            background:
                linear-gradient(135deg, rgba(0,0,0,.78), rgba(0,0,0,.92)),
                var(--club-hero-image, linear-gradient(135deg, #111 0%, #070707 62%, #1b100d 100%));
            background-size: cover;
            background-position: center;
            color: #fff;
            border: 1px solid rgba(255,255,255,.1);
            box-shadow: 0 20px 70px rgba(0,0,0,.22);
        }
        .club-dashboard-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 12% 12%, color-mix(in srgb, var(--club-primary) 42%, transparent), transparent 32%);
            pointer-events: none;
        }
        .club-hero-inner { position: relative; z-index: 1; display: flex; align-items: center; gap: 18px; }
        .club-logo {
            width: 82px;
            height: 82px;
            border-radius: 22px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.15);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex: 0 0 82px;
            font-size: 26px;
            font-weight: 950;
            color: #fff;
        }
        .club-logo img { width: 100%; height: 100%; object-fit: cover; }
        .club-dashboard-kicker { margin: 0 0 10px; color: rgba(255,255,255,.58); font-size: 11px; letter-spacing: .16em; text-transform: uppercase; font-weight: 900; }
        .club-dashboard-hero h2 { margin: 0; font-size: clamp(32px, 5vw, 58px); line-height: .92; letter-spacing: -.05em; font-weight: 950; }
        .club-dashboard-hero p { margin: 12px 0 0; max-width: 780px; color: rgba(255,255,255,.72); font-size: 14px; line-height: 1.65; }
        .club-dashboard-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        .club-dashboard-btn,
        .club-dashboard-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border-radius: 999px;
            padding: 0 16px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 900;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.08);
            color: #fff;
            cursor: pointer;
        }
        .club-dashboard-btn-primary {
            color: #120806;
            background: linear-gradient(135deg, #fff2de, var(--club-primary));
            border-color: color-mix(in srgb, var(--club-primary) 50%, transparent);
        }
        .club-dashboard-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .club-stat-card, .club-panel { border-radius: 22px; background: #111; border: 1px solid rgba(255,255,255,.09); color: #fff; box-shadow: 0 18px 50px rgba(0,0,0,.18); }
        .club-stat-card { padding: 16px; }
        .club-stat-label { margin: 0; color: rgba(255,255,255,.5); font-size: 11px; letter-spacing: .12em; text-transform: uppercase; font-weight: 900; }
        .club-stat-value { margin: 8px 0 0; font-size: 32px; line-height: 1; font-weight: 950; letter-spacing: -.04em; }
        .club-dashboard-columns { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .club-panel { padding: 16px; min-width: 0; }
        .club-panel-full { grid-column: 1 / -1; }
        .club-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .club-panel h3 { margin: 0; font-size: 18px; font-weight: 950; letter-spacing: -.02em; }
        .club-panel-note { color: rgba(255,255,255,.48); font-size: 12px; font-weight: 700; }
        .club-list { display: grid; gap: 9px; }
        .club-list-item { width: 100%; display: flex; justify-content: space-between; gap: 12px; border-radius: 16px; padding: 12px; background: rgba(255,255,255,.055); min-width: 0; border: 0; color: inherit; text-align: left; cursor: pointer; }
        .club-list-item:hover { background: rgba(255,255,255,.085); }
        .club-list-item-main { min-width: 0; }
        .club-list-item strong { display:block; color:#fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .club-list-item span { display:block; color:rgba(255,255,255,.55); font-size:12px; margin-top:3px; }
        .club-badge { display:inline-flex; align-items:center; height: 28px; border-radius:999px; padding:0 10px; background: color-mix(in srgb, var(--club-primary) 20%, transparent); color:#ffd1c7; font-size:11px; font-weight:900; white-space:nowrap; }
        .club-empty { color: rgba(255,255,255,.58); font-size: 13px; line-height: 1.55; }
        .player-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .player-card {
            border-radius: 22px;
            overflow: hidden;
            background: rgba(255,255,255,.055);
            border: 1px solid rgba(255,255,255,.08);
        }
        .player-image {
            aspect-ratio: 4 / 3;
            background: linear-gradient(135deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            font-weight: 950;
            color: #fff;
        }
        .player-image img { width: 100%; height: 100%; object-fit: cover; }
        .player-body { padding: 12px; }
        .player-title { color: #fff; font-weight: 950; margin: 0; }
        .player-meta { color: rgba(255,255,255,.55); margin: 4px 0 0; font-size: 12px; }
        .player-actions { display: flex; gap: 7px; flex-wrap: wrap; margin-top: 12px; }
        .player-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            border-radius: 999px;
            padding: 0 10px;
            background: rgba(255,255,255,.09);
            border: 1px solid rgba(255,255,255,.09);
            color: #fff;
            font-size: 11px;
            font-weight: 900;
            text-decoration: none;
        }
        .modal-grid { display: grid; gap: 12px; }
        .modal-grid label { display: grid; gap: 6px; color: rgba(255,255,255,.75); font-size: 12px; font-weight: 900; }
        .modal-grid input, .modal-grid select {
            min-height: 44px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.08);
            color: #fff;
            padding: 0 12px;
        }
        @media (max-width: 1100px) { .player-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 980px) { .club-dashboard-grid, .club-dashboard-columns { grid-template-columns: 1fr; } }
        @media (max-width: 560px) { .club-dashboard-hero { border-radius: 20px; } .club-hero-inner { align-items: flex-start; flex-direction: column; } .club-dashboard-btn { width: 100%; } .player-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="club-dashboard-shell" style="--club-primary: {{ $primary }}; {{ $heroUrl ? "--club-hero-image: url('{$heroUrl}');" : '' }}">
        <section class="club-dashboard-hero">
            <div class="club-hero-inner">
                <div class="club-logo">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $clubName }} logo">
                    @else
                        {{ str($clubName)->substr(0, 2)->upper() }}
                    @endif
                </div>

                <div>
                    <p class="club-dashboard-kicker">Club Dashboard</p>
                    <h2>{{ $clubName }}</h2>

                    @if ($club)
                        <p>View fixed teams, invite players, and open player contact details. Player cards appear after selecting a team.</p>
                        <div class="club-dashboard-actions">
                            @if ($landingUrl)
                                <a href="{{ $landingUrl }}" target="_blank" rel="noopener" class="club-dashboard-btn club-dashboard-btn-primary">Visit Club Site</a>
                            @endif

                            <button type="button" class="club-dashboard-button" x-data x-on:click="$dispatch('open-modal', { id: 'club-invite-modal' })">
                                Send Invite
                            </button>
                        </div>
                    @else
                        <p>This account does not have a club assigned yet. Assign one club through the user editor.</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="club-dashboard-grid">
            <article class="club-stat-card"><p class="club-stat-label">Teams</p><p class="club-stat-value">{{ number_format($stats['teams']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">Players</p><p class="club-stat-value">{{ number_format($stats['players']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">This Month</p><p class="club-stat-value">{{ number_format($stats['registered_this_month']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">Invites</p><p class="club-stat-value">{{ number_format($stats['invites']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">Invite Clicks</p><p class="club-stat-value">{{ number_format($stats['invite_clicks']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">Conversions</p><p class="club-stat-value">{{ number_format($stats['invite_conversions']) }}</p></article>
        </section>

        <section class="club-dashboard-columns">
            <article class="club-panel">
                <div class="club-panel-head">
                    <h3>Teams</h3>
                    <span class="club-panel-note">Select a team</span>
                </div>

                <div class="club-list">
                    @foreach ($ageGroups as $ageGroup)
                        <button type="button" wire:click="selectTeam('{{ $ageGroup['name'] }}')" class="club-list-item">
                            <div class="club-list-item-main">
                                <strong>{{ $ageGroup['name'] }}</strong>
                                <span>{{ number_format($ageGroup['player_count']) }} player{{ $ageGroup['player_count'] === 1 ? '' : 's' }} • {{ number_format($ageGroup['recent_count']) }} new this month</span>
                            </div>
                            <span class="club-badge">Open</span>
                        </button>
                    @endforeach
                </div>
            </article>

            <article class="club-panel">
                <div class="club-panel-head">
                    <h3>Invites</h3>
                    <span class="club-panel-note">{{ $invites->count() }} recent</span>
                </div>

                <div class="club-list">
                    @forelse ($invites as $invite)
                        <div class="club-list-item" style="cursor: default;">
                            <div class="club-list-item-main">
                                <strong>{{ $invite->team_name ?: 'Open invite' }}</strong>
                                <span>{{ collect([$invite->league?->name, $invite->invited_email, $invite->status])->filter()->implode(' • ') }}</span>
                            </div>
                            <span class="club-badge">{{ str($invite->status)->title() }}</span>
                        </div>
                    @empty
                        <p class="club-empty">No invites created for this club yet.</p>
                    @endforelse
                </div>
            </article>

            <article class="club-panel club-panel-full">
                <div class="club-panel-head">
                    <h3>{{ $this->selectedTeam ? $this->selectedTeam . ' Players' : 'Players' }}</h3>
                    @if ($this->selectedTeam)
                        <button type="button" wire:click="clearSelectedTeam" class="club-dashboard-button">Back to Teams</button>
                    @else
                        <span class="club-panel-note">Choose a team to view players</span>
                    @endif
                </div>

                @if (! $this->selectedTeam)
                    <p class="club-empty">Player names are hidden until a team is selected.</p>
                @elseif ($players->isEmpty())
                    <p class="club-empty">No players found for {{ $this->selectedTeam }}.</p>
                @else
                    <div class="player-grid">
                        @foreach ($players as $player)
                            @php
                                $image = \App\Support\ClubManagerAccess::playerImageUrl($player);
                                $email = \App\Support\ClubManagerAccess::playerEmail($player);
                                $phone = \App\Support\ClubManagerAccess::playerPhone($player);
                                $website = \App\Support\ClubManagerAccess::playerWebsiteUrl($player);
                            @endphp

                            <article class="player-card">
                                <div class="player-image">
                                    @if ($image)
                                        <img src="{{ $image }}" alt="{{ \App\Support\ClubManagerAccess::playerDisplayName($player) }}">
                                    @else
                                        {{ \App\Support\ClubManagerAccess::playerInitials($player) }}
                                    @endif
                                </div>

                                <div class="player-body">
                                    <p class="player-title">{{ \App\Support\ClubManagerAccess::playerDisplayName($player) }}</p>
                                    <p class="player-meta">{{ collect([$player->position, $player->sport, $player->year])->flatten()->filter()->implode(' • ') ?: $player->email }}</p>

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
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </article>
        </section>
    </div>

    <x-filament::modal id="club-invite-modal" width="lg">
        <x-slot name="heading">Send Invite</x-slot>

        <div class="modal-grid">
            <label>
                Program / League
                <select wire:model="inviteClubLeagueId">
                    <option value="">Select a program</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}">
                            {{ collect([$program->league?->name, $program->sport ?: $program->league?->sport])->filter()->implode(' • ') }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Team / Age Group
                <select wire:model="inviteTeamName">
                    @foreach ($ageGroups as $ageGroup)
                        <option value="{{ $ageGroup['name'] }}">{{ $ageGroup['name'] }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Invitee Name
                <input type="text" wire:model="inviteName" placeholder="Optional">
            </label>

            <label>
                Invitee Email
                <input type="email" wire:model="inviteEmail" placeholder="Optional">
            </label>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top: 8px;">
                <button type="button" class="club-dashboard-button" x-data x-on:click="$dispatch('close-modal', { id: 'club-invite-modal' })">Cancel</button>
                <button type="button" class="club-dashboard-btn club-dashboard-btn-primary" wire:click="createInvite">Create Invite</button>
            </div>
        </div>
    </x-filament::modal>
</x-filament-panels::page>