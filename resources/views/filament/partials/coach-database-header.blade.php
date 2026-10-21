@php
    $headerUser = auth()->user();
    $headerName = $headerUser ? trim((string) ($headerUser->first_name . ' ' . $headerUser->last_name)) : 'Player';
    $headerName = $headerName !== '' ? $headerName : 'Player';
    $headerFirstName = trim((string) ($firstName ?? $headerUser?->first_name ?? 'Player')) ?: 'Player';
    $headerPlaceholder = (string) ($placeholder ?? 'Search schools, coaches, conferences, divisions, lists...');
    $headerShowNewEmail = (bool) ($showNewEmail ?? false);
    $headerAvatarUrl = $headerUser && method_exists($headerUser, 'getFilamentAvatarUrl') ? $headerUser->getFilamentAvatarUrl() : null;
    $headerAvatarUrl = $headerAvatarUrl ?: ($headerUser?->profile_photo_url ?? null) ?: ($headerUser?->avatar_url ?? null) ?: 'https://ui-avatars.com/api/?name=' . urlencode($headerName) . '&background=ff6338&color=fff';
    $headerRoles = $headerUser && method_exists($headerUser, 'getRoleNames') ? $headerUser->getRoleNames() : collect();
    $headerPlanLabel = match (true) {
        $headerRoles->contains(fn ($role) => strcasecmp((string) $role, 'My Journey') === 0) => 'My Journey',
        $headerRoles->contains(fn ($role) => strcasecmp((string) $role, 'Plyr') === 0) => 'Plyr',
        $headerRoles->contains(fn ($role) => strcasecmp((string) $role, 'PLYR+') === 0) => 'PLYR+',
        $headerRoles->contains(fn ($role) => strcasecmp((string) $role, 'Free') === 0) => 'Free',
        default => (string) ($headerRoles->first() ?: 'Free'),
    };
    $headerPointBalance = (int) ($headerUser?->points_available ?? 0);
    $headerRecentPurchase = 'None yet';
    if ($headerUser && \Illuminate\Support\Facades\Schema::hasTable('credit_point_transactions')) {
        $headerLatestPurchase = \App\Models\CreditPointTransaction::query()
            ->where('user_id', $headerUser->getKey())
            ->where('type', 'grant')
            ->whereIn('source_type', ['package_purchase', 'top_up_purchase'])
            ->latest('id')
            ->first();
        if ($headerLatestPurchase) {
            $headerPackage = trim((string) data_get($headerLatestPurchase->meta ?? [], 'package', ''));
            $headerPurchaseName = $headerPackage !== ''
                ? str($headerPackage)->replace('-', ' ')->title()->toString()
                : trim((string) ($headerLatestPurchase->reason ?: 'Point purchase'));
            $headerPurchaseDate = optional($headerLatestPurchase->created_at)->format('M j');
            $headerRecentPurchase = $headerPurchaseName . ($headerPurchaseDate ? ' · ' . $headerPurchaseDate : '');
        }
    }
    $headerEditProfileUrl = url('/admin/my-profile');
    $headerSettingsUrl = url('/admin/coach-database/settings');
    $headerManagePlanUrl = url('/admin/my-journey');
    $headerLogoutUrl = \Illuminate\Support\Facades\Route::has('filament.admin.auth.logout') ? route('filament.admin.auth.logout') : url('/admin/logout');
    $headerComposeUrl = class_exists(\App\Filament\Pages\CoachDatabaseComposeEmail::class)
        ? \App\Filament\Pages\CoachDatabaseComposeEmail::getUrl()
        : url('/admin/coach-database/compose-email');
@endphp

<style>
    .rc-shared-header-v135{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;align-items:start;margin:0 0 1rem}
    .rc-shared-header-copy-v135{min-width:0;padding-top:.15rem}.rc-shared-header-copy-v135 h1{margin:0;color:var(--rc-text);font-size:clamp(1.6rem,2vw,2rem);line-height:1.08;font-weight:500;letter-spacing:-.025em}.rc-shared-header-copy-v135 p{margin:.35rem 0 0;color:var(--rc-muted);font-size:.88rem;line-height:1.4}
    .rc-shared-header-right-v135{display:grid;gap:.55rem;justify-items:end;min-width:0}
    .rc-shared-header-controls-v135{display:flex;align-items:center;justify-content:flex-end;gap:.55rem;min-width:0}
    .rc-shared-header-search-v135{position:relative;width:min(31rem,42vw);height:2.8rem;border:1px solid var(--rc-border);background:var(--rc-surface);border-radius:.9rem;display:flex;align-items:center;gap:.55rem;padding:0 .78rem;box-shadow:0 10px 24px rgba(15,23,42,.045)}
    .rc-shared-header-search-v135>svg{width:1rem;height:1rem;color:var(--rc-muted);flex:0 0 auto}.rc-shared-header-search-v135>input{width:100%;min-width:0;border:0!important;outline:0!important;background:transparent!important;box-shadow:none!important;color:var(--rc-text);font-size:.84rem;padding:0!important}.rc-shared-header-search-v135 kbd{border:1px solid var(--rc-border);border-radius:.42rem;background:var(--rc-soft);padding:.14rem .4rem;color:var(--rc-muted);font-size:.62rem;white-space:nowrap}.rc-shared-header-search-v135 .rc-global-suggestions{top:calc(100% + .4rem);left:0;right:0;z-index:160}
    .rc-shared-header-v135 .plyr-global-dark-toggle,.rc-shared-header-v135 .plyr-top-profile-trigger{width:2.8rem;height:2.8rem;min-width:2.8rem}
    .rc-shared-header-v135 .plyr-top-profile-wrap{position:relative}.rc-shared-header-v135 .plyr-top-profile-menu{z-index:180}
    .rc-shared-header-new-email-v135{display:inline-flex;align-items:center;justify-content:center;min-height:2.45rem;padding:0 1rem;border-radius:.8rem;background:#ff6338;color:#fff;text-decoration:none;font-size:.76rem;font-weight:800;box-shadow:0 8px 18px rgba(255,99,56,.18)}
    @media(max-width:1100px){.rc-shared-header-search-v135{width:min(25rem,38vw)}}
    @media(max-width:820px){.rc-shared-header-v135{grid-template-columns:1fr}.rc-shared-header-right-v135{width:100%;justify-items:stretch}.rc-shared-header-controls-v135{justify-content:flex-end}.rc-shared-header-search-v135{width:min(100%,28rem);margin-left:auto}.rc-shared-header-copy-v135 h1{font-size:1.55rem}}
    @media(max-width:620px){.rc-shared-header-controls-v135{display:grid;grid-template-columns:minmax(0,1fr) auto auto;width:100%}.rc-shared-header-search-v135{width:100%;margin:0}.rc-shared-header-search-v135 kbd{display:none}.rc-shared-header-new-email-v135{width:100%}}
</style>

<header class="rc-shared-header-v135" x-data="{ profileOpen:false, closeTimer:null, show(){clearTimeout(this.closeTimer);this.profileOpen=true}, hide(){clearTimeout(this.closeTimer);this.closeTimer=setTimeout(()=>this.profileOpen=false,180)} }" x-on:keydown.escape.window="profileOpen=false">
    <div class="rc-shared-header-copy-v135">
        <h1>Welcome back, {{ $headerFirstName }} <span aria-hidden="true">👋</span></h1>
        <p>Here's what's happening with your recruiting journey.</p>
    </div>
    <div class="rc-shared-header-right-v135">
        <div class="rc-shared-header-controls-v135">
            <div class="rc-shared-header-search-v135" role="search" aria-label="Global Recruiting Center search">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input type="search" placeholder="{{ $headerPlaceholder }}" wire:model.live.debounce.300ms="search">
                <kbd>Enter</kbd>
                @if(($search ?? '') !== '')
                    <div class="rc-global-suggestions">
                        @if($globalSearchHasSuggestions ?? false)
                            @foreach(($globalSearchGroups ?? []) as $groupKey => $groupLabel)
                                @if(! empty($globalSearchSuggestions[$groupKey] ?? []))
                                    <div class="rc-global-suggestion-group">
                                        <div class="rc-global-suggestion-heading">{{ $groupLabel }}</div>
                                        @foreach($globalSearchSuggestions[$groupKey] as $suggestion)
                                            <button type="button" class="rc-global-suggestion-item" wire:click="selectGlobalSearchSuggestion(@js($suggestion['type']), @js($suggestion['value']), @js($suggestion['id']))">
                                                <span class="rc-global-suggestion-icon">@if(! empty($suggestion['logo_url']))<img src="{{ $suggestion['logo_url'] }}" alt="" onerror="this.style.display='none';this.parentElement.textContent='{{ $globalSearchInitials($suggestion['label'] ?? '') }}';">@else{{ $globalSearchInitials($suggestion['label'] ?? '') }}@endif</span>
                                                <span class="rc-global-suggestion-copy"><strong>{{ $suggestion['label'] }}</strong><small>{{ $suggestion['detail'] ?: $suggestion['category'] }}</small></span>
                                                <span class="rc-global-suggestion-category">{{ $suggestion['category'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="rc-global-search-empty">No matching schools, coaches, conferences, divisions, or student lists yet.</div>
                        @endif
                    </div>
                @endif
            </div>
            <button type="button" class="plyr-global-dark-toggle" data-plyr-dark-toggle aria-label="Toggle dark mode" aria-pressed="false">
                <svg class="plyr-theme-icon-moon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 14.35A8.5 8.5 0 0 1 9.65 3A8.75 8.75 0 1 0 21 14.35Z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <svg class="plyr-theme-icon-sun" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 17a5 5 0 1 0 0-10a5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="1.9"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            </button>
            <div class="plyr-top-profile-wrap" x-on:mouseenter="show()" x-on:mouseleave="hide()">
                <button type="button" class="plyr-top-profile-trigger" @click="profileOpen=!profileOpen" aria-label="Open profile menu" x-bind:aria-expanded="profileOpen?'true':'false'"><span class="plyr-top-profile-avatar"><img src="{{ $headerAvatarUrl }}" alt="{{ $headerName }}"></span></button>
                <div class="plyr-top-profile-menu" x-show="profileOpen" x-cloak x-transition.opacity.scale.origin.top.right @click.outside="profileOpen=false" x-on:mouseenter="show()" x-on:mouseleave="hide()">
                    <div class="plyr-top-profile-user"><span class="plyr-top-profile-avatar"><img src="{{ $headerAvatarUrl }}" alt="{{ $headerName }}"></span><span class="plyr-top-profile-user-copy"><strong>{{ $headerName }}</strong><span>{{ $headerPlanLabel }}</span></span></div>
                    <div class="plyr-top-profile-section"><div class="plyr-top-profile-dual-actions"><a href="{{ $headerEditProfileUrl }}" class="plyr-top-profile-pill is-ghost">Edit Profile</a><a href="{{ $headerSettingsUrl }}" class="plyr-top-profile-pill is-ghost">Settings</a></div></div>
                    <div class="plyr-top-profile-section"><span class="plyr-top-profile-section-label">Points</span><div class="plyr-top-profile-summary-card"><div class="plyr-top-profile-summary-head"><strong>PLYRCARD Points</strong><a href="{{ url('/admin/coach-database/services') }}" class="plyr-top-profile-pill is-solid">Use Points</a></div><div class="plyr-top-profile-stat"><span>Available</span><strong>{{ number_format($headerPointBalance) }} points</strong></div><div class="plyr-top-profile-stat"><span>Recently Purchased</span><strong>{{ $headerRecentPurchase }}</strong></div></div></div>
                    <div class="plyr-top-profile-section"><span class="plyr-top-profile-section-label">Plan</span><a href="{{ $headerManagePlanUrl }}" class="plyr-top-profile-action"><span class="plyr-top-profile-plan-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M13 2L5 13h6l-1 9l9-12h-6V2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span><span class="plyr-top-profile-plan-copy"><strong>{{ $headerPlanLabel }}</strong><span>Manage Plan</span></span></a></div>
                    <div class="plyr-top-profile-section"><form method="POST" action="{{ $headerLogoutUrl }}" style="margin:0;">@csrf<button type="submit" class="plyr-top-profile-action is-danger"><svg viewBox="0 0 24 24" fill="none"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5M14 8l4 4-4 4M18 12H8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Sign Out</span></button></form></div>
                </div>
            </div>
        </div>
        @if($headerShowNewEmail)
            <a class="rc-shared-header-new-email-v135" href="{{ $headerComposeUrl }}">+ New Email</a>
        @endif
    </div>
</header>