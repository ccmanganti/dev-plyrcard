@props([
    'firstName' => 'Player',
    'subtitle' => "Here's what's happening with your recruiting journey.",
    'placeholder' => 'Search schools, coaches, conferences, divisions, lists...',
    'showNewEmail' => false,
])

@php
    $displayName = trim((string) ($firstName ?: 'Player'));
    $displayName = preg_split('/\s+/', $displayName)[0] ?? $displayName;
    $displayName = $displayName !== '' ? $displayName : 'Player';
@endphp

<div class="rc-home-header-v2">
    <div class="rc-home-welcome-copy-v2">
        <h1>Welcome back, {{ $displayName }} <span aria-hidden="true">👋</span></h1>
        <p>{{ $subtitle }}</p>
    </div>

    <form class="rc-home-actions-v2" wire:submit.prevent="$set('section', 'schools')">
        <div class="rc-home-search-v2">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <input type="search" placeholder="{{ $placeholder }}" wire:model.live.debounce.350ms="search">
            @if($search !== '')
                <button type="button" class="rc-global-search-clear" wire:click="clearGlobalSearch" aria-label="Clear search">×</button>
            @else
                <kbd>Enter</kbd>
            @endif

            @if($search !== '')
                <div class="rc-global-suggestions">
                    @if($globalSearchHasSuggestions)
                        @foreach($globalSearchGroups as $groupKey => $groupLabel)
                            @if(! empty($globalSearchSuggestions[$groupKey] ?? []))
                                <div class="rc-global-suggestion-group">
                                    <div class="rc-global-suggestion-heading">{{ $groupLabel }}</div>
                                    @foreach($globalSearchSuggestions[$groupKey] as $suggestion)
                                        <button type="button" class="rc-global-suggestion-item" wire:click="selectGlobalSearchSuggestion(@js($suggestion['type']), @js($suggestion['value']), @js($suggestion['id']))">
                                            <span class="rc-global-suggestion-icon">
                                                @if(! empty($suggestion['logo_url']))
                                                    <img src="{{ $suggestion['logo_url'] }}" alt="" onerror="this.style.display='none';this.parentElement.textContent='{{ $globalSearchInitials($suggestion['label'] ?? '') }}';">
                                                @else
                                                    {{ $globalSearchInitials($suggestion['label'] ?? '') }}
                                                @endif
                                            </span>
                                            <span class="rc-global-suggestion-copy">
                                                <strong>{{ $suggestion['label'] }}</strong>
                                                <small>{{ $suggestion['detail'] ?: $suggestion['category'] }}</small>
                                            </span>
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

        <div class="rc-refresh-dropdown-v2" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
            <button
                type="button"
                class="rc-home-refresh-v2"
                x-on:click="open = ! open"
                wire:loading.attr="disabled"
                wire:target="refreshStatsOnly,refreshCoachDatabase,refreshData,startBackgroundLoad,loadNextBatch"
                aria-label="Open refresh options"
                title="Refresh options"
            >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6v5h-5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M19.2 11A7.6 7.6 0 1 0 17 16.35" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="rc-refresh-menu-v2" x-cloak x-show="open" x-transition.origin.top.right>
                <button type="button" class="rc-refresh-menu-item-v2" wire:click="refreshStatsOnly" x-on:click="open = false">
                    <span class="rc-refresh-menu-icon-v2"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19V5M4 19h16M8 16v-5M13 16V8M18 16v-8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <span class="rc-refresh-menu-copy-v2"><strong>Reload stats only</strong><small>Sync email sent, profile views, and social clicks from GHL cache fields.</small></span>
                </button>
                <button type="button" class="rc-refresh-menu-item-v2" wire:click="refreshCoachDatabase" x-on:click="open = false">
                    <span class="rc-refresh-menu-icon-v2"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M8 4v4M16 10v4M11 16v4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg></span>
                    <span class="rc-refresh-menu-copy-v2"><strong>Reload whole Coach Database</strong><small>Clear cache and reload schools, coaches, logos, tags, filters, and stats from GHL.</small></span>
                </button>
            </div>
        </div>

        <button type="button" class="rc-home-dark-toggle-v2" data-plyr-dark-toggle aria-label="Toggle dark mode" aria-pressed="false">
            <svg class="rc-dark-icon-moon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M21 14.35A8.5 8.5 0 0 1 9.65 3A8.75 8.75 0 1 0 21 14.35Z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg class="rc-dark-icon-sun" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 17a5 5 0 1 0 0-10a5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="1.9"/>
                <path d="M12 2v2M12 20v2M4 12H2M22 12h-2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
            </svg>
        </button>

        @if($showNewEmail)
            <a class="rc-home-new-email-v2" href="{{ \App\Filament\Pages\CoachDatabaseComposeEmail::getUrl() }}">
                <span>+</span>
                New Email
            </a>
        @endif
    </form>
</div>