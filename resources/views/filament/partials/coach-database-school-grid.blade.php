@php
    $viewMode = ($viewMode ?? 'grid') === 'list' && !($compact ?? false) ? 'list' : 'grid';
    $schools = collect($schools ?? [])->values();
@endphp

@if($schools->isEmpty())
    <div class="rc-empty rc-empty-wide">
        <strong>No schools found.</strong>
        <span>Try searching by school, coach, division, conference, city, or state.</span>
    </div>
@elseif($viewMode === 'list')
    <div class="rc-school-list-table">
        <div class="rc-school-list-head" aria-hidden="true">
            <span>School</span>
            <span>Division</span>
            <span>Conference</span>
            <span>Location</span>
            <span>Coaches</span>
            <span>Match</span>
            <span style="text-align:right">Actions</span>
        </div>

        @foreach($schools as $school)
            @php
                $schoolId = (string) ($school['id'] ?? '');
                $division = (string) ($school['division'] ?? 'N/A');
                $conference = (string) (($school['conference'] ?? '') ?: 'N/A');
                $city = trim((string) ($school['city'] ?? ''));
                $state = trim((string) ($school['state'] ?? ''));
                $location = trim($city . ($city && $state ? ', ' : '') . $state) ?: 'N/A';
                $coachCount = (int) ($school['coach_count'] ?? 0);
                $score = (int) ($school['match_score'] ?? $school['engagement_score'] ?? 0);
                $isFavorite = (bool) ($school['is_favorite'] ?? false);
                $favoriteAction = $isFavorite ? 'unfavoriteSchoolById' : 'favoriteSchoolById';
                $schoolListKeys = collect($school['list_keys'] ?? [])->map(fn ($key) => (string) $key)->filter()->values();
                $logo = (string) ($school['logo'] ?? $school['logo_url'] ?? $school['image'] ?? '');
                $name = (string) (($school['name'] ?? '') ?: 'Unnamed School');
                $initials = collect(explode(' ', $name))->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
            @endphp

            <article class="rc-school-list-row" wire:key="school-list-row-{{ $schoolId }}">
                <button type="button" class="rc-school-list-name" wire:click="selectSchoolById(@js($schoolId))" wire:loading.attr="disabled" wire:target="selectSchoolById(@js($schoolId))">
                    @if($logo !== '')
                        <img class="rc-school-list-logo" src="{{ $logo }}" alt="{{ $name }} logo">
                    @else
                        <span class="rc-coach-avatar rc-school-list-logo">{{ $initials ?: 'S' }}</span>
                    @endif
                    <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $name }}</span>
                </button>
                <span class="rc-subtle">{{ $division }}</span>
                <span class="rc-subtle" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $conference }}</span>
                <span class="rc-subtle" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $location }}</span>
                <span class="rc-subtle">{{ number_format($coachCount) }}</span>
                <span class="rc-pill rc-pill-accent">{{ $score > 0 ? $score . '%' : '—' }}</span>
                <div class="rc-school-list-actions">
                    <button type="button" class="rc-icon-button {{ $isFavorite ? 'is-active' : '' }}" wire:click="{{ $favoriteAction }}(@js($schoolId))" wire:loading.attr="disabled" wire:target="favoriteSchoolById(@js($schoolId)),unfavoriteSchoolById(@js($schoolId))" aria-label="{{ $isFavorite ? 'Remove favorite school' : 'Favorite school' }}">
                        <span aria-hidden="true">★</span>
                    </button>
                    <div class="rc-school-list-picker" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <button type="button" class="rc-btn rc-school-list-trigger" @click="open = !open" :aria-expanded="open.toString()" aria-label="Choose a school list">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            <span>List</span>
                        </button>
                        <div class="rc-school-list-menu" x-cloak x-show="open" x-transition.origin.top.right>
                            @forelse($lists as $list)
                                @php
                                    $listKey = (string) ($list['key'] ?? '');
                                    $inList = $schoolListKeys->contains($listKey);
                                @endphp
                                @if($listKey !== '')
                                    <button type="button" class="rc-school-list-option {{ $inList ? 'is-active' : '' }}" wire:click="{{ $inList ? 'removeSchoolFromListById' : 'addSchoolToListById' }}(@js($schoolId), @js($listKey))" wire:loading.attr="disabled" wire:target="addSchoolToListById(@js($schoolId), @js($listKey)),removeSchoolFromListById(@js($schoolId), @js($listKey))" @click="open = false">
                                        <span>{{ $list['label'] ?? 'List' }}</span>
                                        @if($inList)<span class="rc-school-list-check">✓</span>@endif
                                    </button>
                                @endif
                            @empty
                                <div class="rc-school-list-empty">No lists</div>
                            @endforelse
                        </div>
                    </div>
                    <button type="button" class="rc-icon-button" wire:click="selectSchoolById(@js($schoolId))" wire:loading.attr="disabled" wire:target="selectSchoolById(@js($schoolId))" aria-label="View coaches">
                        <span aria-hidden="true">→</span>
                    </button>
                </div>
            </article>
        @endforeach
    </div>
@else
    <div class="rc-school-grid {{ ($compact ?? false) ? 'is-compact' : '' }}">
        @foreach($schools as $school)
            @php
                $schoolId = (string) ($school['id'] ?? '');
                $division = (string) ($school['division'] ?? 'N/A');
                $isFavorite = (bool) ($school['is_favorite'] ?? false);
                $isSaved = (bool) ($school['is_saved'] ?? false);
                $headCoach = $school['head_coach']['name'] ?? null;
                $favoriteAction = $isFavorite ? 'unfavoriteSchoolById' : 'favoriteSchoolById';
                $schoolListKeys = collect($school['list_keys'] ?? [])->map(fn ($key) => (string) $key)->filter()->values();
            @endphp

            <article class="rc-school-card" wire:key="school-card-{{ $schoolId }}">
                <div class="rc-school-topline">
                    <span class="rc-badge">{{ $division }}</span>
                    <button type="button" class="rc-icon-button {{ $isFavorite ? 'is-active' : '' }}" wire:click="{{ $favoriteAction }}(@js($schoolId))" wire:loading.attr="disabled" wire:target="favoriteSchoolById(@js($schoolId)),unfavoriteSchoolById(@js($schoolId))" aria-label="{{ $isFavorite ? 'Remove favorite school' : 'Favorite school' }}">
                        <span aria-hidden="true">★</span>
                    </button>
                </div>

                <h3>{{ $school['name'] ?? 'Unnamed School' }}</h3>
                <p class="rc-school-conference">{{ ($school['conference'] ?? null) ?: 'Conference unavailable' }}</p>

                <div class="rc-school-meta">
                    <span>{{ $headCoach ?: 'Coach unavailable' }}</span>
                    <span>{{ number_format((int) ($school['coach_count'] ?? 0)) }} {{ ((int) ($school['coach_count'] ?? 0)) === 1 ? 'coach' : 'coaches' }}</span>
                </div>

                <div class="rc-toolbar rc-school-flags">
                    @if($isSaved)<span class="rc-pill rc-pill-accent">Saved</span>@endif
                    @if($isFavorite)<span class="rc-pill rc-pill-accent">Favorite</span>@endif
                    @if($schoolListKeys->count())<span class="rc-pill">{{ $schoolListKeys->count() }} {{ $schoolListKeys->count() === 1 ? 'list' : 'lists' }}</span>@endif
                </div>

                <div class="rc-school-actions">
                    <button type="button" class="rc-btn rc-btn-primary" wire:click="selectSchoolById(@js($schoolId))" wire:loading.attr="disabled" wire:target="selectSchoolById(@js($schoolId))">
                        <span wire:loading.remove wire:target="selectSchoolById(@js($schoolId))">View coaches</span>
                        <span wire:loading.flex wire:target="selectSchoolById(@js($schoolId))" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Loading</span>
                    </button>
                    <div class="rc-school-list-picker" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <button type="button" class="rc-btn rc-school-list-trigger" @click="open = !open" :aria-expanded="open.toString()" aria-label="Choose a school list">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            <span>List</span>
                        </button>

                        <div class="rc-school-list-menu" x-cloak x-show="open" x-transition.origin.top.right>
                            @forelse($lists as $list)
                                @php
                                    $listKey = (string) ($list['key'] ?? '');
                                    $inList = $schoolListKeys->contains($listKey);
                                @endphp
                                @if($listKey !== '')
                                    <button type="button" class="rc-school-list-option {{ $inList ? 'is-active' : '' }}" wire:click="{{ $inList ? 'removeSchoolFromListById' : 'addSchoolToListById' }}(@js($schoolId), @js($listKey))" wire:loading.attr="disabled" wire:target="addSchoolToListById(@js($schoolId), @js($listKey)),removeSchoolFromListById(@js($schoolId), @js($listKey))" @click="open = false">
                                        <span>{{ $list['label'] ?? 'List' }}</span>
                                        @if($inList)<span class="rc-school-list-check">✓</span>@endif
                                    </button>
                                @endif
                            @empty
                                <div class="rc-school-list-empty">No lists</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif