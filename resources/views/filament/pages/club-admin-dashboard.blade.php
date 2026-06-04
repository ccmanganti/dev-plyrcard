<x-filament-panels::page>
    @php
        $club = $this->assignedClub;
        $stats = $this->stats;
        $ageGroups = $this->ageGroups;
        $players = $this->players;
        $invites = $this->invites;

        $clubName = $club?->name ?? 'No club assigned';
        $landingUrl = $club?->landingUrl();
    @endphp

    <style>
        .club-dashboard-shell { display: grid; gap: 18px; }
        .club-dashboard-hero {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 24px;
            background:
                radial-gradient(circle at 10% 10%, rgba(255, 92, 53, .30), transparent 30%),
                linear-gradient(135deg, #111 0%, #070707 62%, #1b100d 100%);
            color: #fff;
            border: 1px solid rgba(255,255,255,.1);
            box-shadow: 0 20px 70px rgba(0,0,0,.22);
        }
        .club-dashboard-kicker { margin: 0 0 10px; color: rgba(255,255,255,.52); font-size: 11px; letter-spacing: .16em; text-transform: uppercase; font-weight: 900; }
        .club-dashboard-hero h2 { margin: 0; font-size: clamp(32px, 5vw, 58px); line-height: .92; letter-spacing: -.05em; font-weight: 950; }
        .club-dashboard-hero p { margin: 12px 0 0; max-width: 780px; color: rgba(255,255,255,.68); font-size: 14px; line-height: 1.65; }
        .club-dashboard-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        .club-dashboard-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; border-radius: 999px; padding: 0 16px; text-decoration: none; font-size: 12px; font-weight: 900; border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.08); color: #fff; }
        .club-dashboard-btn-primary { color: #100; background: linear-gradient(135deg, #fff2de, #ff5c35); border-color: rgba(255,92,53,.45); }
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
        .club-list-item { display: flex; justify-content: space-between; gap: 12px; border-radius: 16px; padding: 12px; background: rgba(255,255,255,.055); min-width: 0; }
        .club-list-item-main { min-width: 0; }
        .club-list-item strong { display:block; color:#fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .club-list-item span { display:block; color:rgba(255,255,255,.55); font-size:12px; margin-top:3px; }
        .club-badge { display:inline-flex; align-items:center; height: 28px; border-radius:999px; padding:0 10px; background:rgba(255,92,53,.16); color:#ffb09e; font-size:11px; font-weight:900; white-space:nowrap; }
        .club-empty { color: rgba(255,255,255,.58); font-size: 13px; line-height: 1.55; }
        @media (max-width: 980px) { .club-dashboard-grid, .club-dashboard-columns { grid-template-columns: 1fr; } }
        @media (max-width: 560px) { .club-dashboard-hero { border-radius: 20px; } .club-dashboard-btn { width: 100%; } }
    </style>

    <div class="club-dashboard-shell">
        <section class="club-dashboard-hero">
            <p class="club-dashboard-kicker">Club Dashboard</p>
            <h2>{{ $clubName }}</h2>

            @if ($club)
                <p>View your fixed age groups, registered players, invitations, and landing page from one place.</p>
                <div class="club-dashboard-actions">
                    @if ($landingUrl)
                        <a href="{{ $landingUrl }}" target="_blank" rel="noopener" class="club-dashboard-btn club-dashboard-btn-primary">Visit Club Site</a>
                    @endif

                    <a href="{{ \App\Filament\Resources\ClubReferrals\ClubReferralResource::getUrl('create') }}" class="club-dashboard-btn">Create Invite</a>
                    <a href="{{ \App\Filament\Resources\ClubLandingPages\ClubLandingPageResource::getUrl('edit', ['record' => $club]) }}" class="club-dashboard-btn">Edit Landing Page</a>
                </div>
            @else
                <p>This account does not have a club assigned yet. Assign one club through the user editor.</p>
            @endif
        </section>

        <section class="club-dashboard-grid">
            <article class="club-stat-card"><p class="club-stat-label">Age Groups</p><p class="club-stat-value">{{ number_format($stats['teams']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">Players</p><p class="club-stat-value">{{ number_format($stats['players']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">This Month</p><p class="club-stat-value">{{ number_format($stats['registered_this_month']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">Invites</p><p class="club-stat-value">{{ number_format($stats['invites']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">Invite Clicks</p><p class="club-stat-value">{{ number_format($stats['invite_clicks']) }}</p></article>
            <article class="club-stat-card"><p class="club-stat-label">Conversions</p><p class="club-stat-value">{{ number_format($stats['invite_conversions']) }}</p></article>
        </section>

        <section class="club-dashboard-columns">
            <article class="club-panel">
                <div class="club-panel-head">
                    <h3>Age Groups</h3>
                    <span class="club-panel-note">U13-U19</span>
                </div>

                <div class="club-list">
                    @foreach ($ageGroups as $ageGroup)
                        <div class="club-list-item">
                            <div class="club-list-item-main">
                                <strong>{{ $ageGroup['name'] }}</strong>
                                <span>{{ number_format($ageGroup['player_count']) }} registered player{{ $ageGroup['player_count'] === 1 ? '' : 's' }}</span>
                            </div>
                            <span class="club-badge">Team</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="club-panel">
                <div class="club-panel-head">
                    <h3>Players</h3>
                    <span class="club-panel-note">{{ $players->count() }} recent</span>
                </div>

                <div class="club-list">
                    @forelse ($players as $player)
                        <div class="club-list-item">
                            <div class="club-list-item-main">
                                <strong>{{ trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')) ?: $player->email }}</strong>
                                <span>{{ collect([$player->team_name, $player->sport, $player->email])->filter()->implode(' • ') }}</span>
                            </div>
                            <span class="club-badge">Player</span>
                        </div>
                    @empty
                        <p class="club-empty">No players registered for this club yet.</p>
                    @endforelse
                </div>
            </article>

            <article class="club-panel club-panel-full">
                <div class="club-panel-head">
                    <h3>Invites</h3>
                    <span class="club-panel-note">{{ $invites->count() }} recent</span>
                </div>

                <div class="club-list">
                    @forelse ($invites as $invite)
                        <div class="club-list-item">
                            <div class="club-list-item-main">
                                <strong>{{ $invite->invited_name ?: ($invite->invited_email ?: 'Open invite') }}</strong>
                                <span>{{ collect([$invite->team_name, $invite->league?->name, $invite->status])->filter()->implode(' • ') }}</span>
                            </div>
                            <span class="club-badge">{{ str($invite->status)->title() }}</span>
                        </div>
                    @empty
                        <p class="club-empty">No invites created for this club yet.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</x-filament-panels::page>