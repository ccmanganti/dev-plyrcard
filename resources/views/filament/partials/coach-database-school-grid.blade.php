@php
    $schools = collect($schools ?? [])->filter(fn ($school) => is_array($school))->values();
    $viewMode = $viewMode ?? 'grid';
    $compact = (bool) ($compact ?? false);
@endphp

@if($schools->isEmpty())
    <div class="rc-empty">
        <strong>No schools found.</strong>
        <span>Refresh the Recruiting Center or adjust your filters.</span>
    </div>
@elseif($viewMode === 'list' && ! $compact)
    <div class="rc-school-list-table">
        <div class="rc-school-list-head">
            <span>School</span>
            <span>Coaches</span>
            <span>Conference</span>
            <span>Division</span>
            <span>Views</span>
            <span>Score</span>
            <span></span>
        </div>

        @foreach($schools as $school)
            @php
                $schoolId = (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim((string) ($school['name'] ?? '')))));
                $schoolName = trim((string) ($school['name'] ?? 'Unnamed School'));
                $schoolLogoUrl = trim((string) ($school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? ''));
                $schoolInitials = collect(explode(' ', $schoolName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
                $schoolInitials = strtoupper($schoolInitials ?: 'S');
                $conference = trim((string) ($school['conference'] ?? ''));
                $division = trim((string) ($school['division'] ?? ''));
                $profileViews = (int) ($school['profile_views'] ?? 0);
                $highlightViews = (int) ($school['highlight_views'] ?? 0);
                $score = (int) ($school['engagement_score'] ?? 0);
            @endphp

            <div class="rc-school-list-row">
                <button class="rc-school-list-name" type="button" wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from($schoolId) }})">
                    <span class="rc-school-list-logo-box rc-school-logo-placeholder {{ $schoolLogoUrl === '' ? 'is-missing-logo' : '' }}">
                        @if($schoolLogoUrl !== '')
                            <img class="rc-school-list-logo" src="{{ $schoolLogoUrl }}" alt="{{ $schoolName }} logo" loading="lazy" onerror="this.closest('.rc-school-list-logo-box').classList.add('is-missing-logo')">
                        @endif
                        <span class="rc-logo-fallback-text">{{ $schoolInitials }}</span>
                    </span>
                    <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $schoolName }}</span>
                </button>
                <span>{{ number_format((int) ($school['coach_count'] ?? 0)) }}</span>
                <span>{{ $conference !== '' ? $conference : '—' }}</span>
                <span>{{ $division !== '' ? $division : '—' }}</span>
                <span>{{ number_format($profileViews + $highlightViews) }}</span>
                <span>{{ number_format($score) }}</span>
                <div class="rc-school-list-actions">
                    @if($school['is_favorite'] ?? false)
                        <button class="rc-btn rc-btn-compact" type="button" wire:click="unfavoriteSchoolById({{ \Illuminate\Support\Js::from($schoolId) }})">★</button>
                    @else
                        <button class="rc-btn rc-btn-compact" type="button" wire:click="favoriteSchoolById({{ \Illuminate\Support\Js::from($schoolId) }})">☆</button>
                    @endif
                    <button class="rc-btn rc-btn-compact rc-btn-primary" type="button" wire:click="composeEmailSchool({{ \Illuminate\Support\Js::from($schoolId) }})">Email</button>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="rc-school-grid {{ $compact ? 'is-compact' : '' }}">
        @foreach($schools as $school)
            @php
                $schoolId = (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim((string) ($school['name'] ?? '')))));
                $schoolName = trim((string) ($school['name'] ?? 'Unnamed School'));
                $schoolLogoUrl = trim((string) ($school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? ''));
                $schoolInitials = collect(explode(' ', $schoolName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
                $schoolInitials = strtoupper($schoolInitials ?: 'S');
                $conference = trim((string) ($school['conference'] ?? ''));
                $division = trim((string) ($school['division'] ?? ''));
                $score = (int) ($school['engagement_score'] ?? 0);
            @endphp

            <div class="rc-school-card">
                <div class="rc-school-topline">
                    <button class="rc-school-card-title" type="button" wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from($schoolId) }})">
                        <span class="rc-school-card-logo-box rc-school-logo-placeholder {{ $schoolLogoUrl === '' ? 'is-missing-logo' : '' }}">
                            @if($schoolLogoUrl !== '')
                                <img class="rc-school-card-logo" src="{{ $schoolLogoUrl }}" alt="{{ $schoolName }} logo" loading="lazy" onerror="this.closest('.rc-school-card-logo-box').classList.add('is-missing-logo')">
                            @endif
                            <span class="rc-logo-fallback-text">{{ $schoolInitials }}</span>
                        </span>
                        <span>{{ $schoolName }}</span>
                    </button>
                    <span class="rc-badge">{{ number_format($score) }}</span>
                </div>

                <p class="rc-school-conference">
                    {{ collect([$conference, $division])->filter()->implode(' · ') ?: 'No conference or division listed' }}
                </p>

                <div class="rc-school-meta">
                    <span>{{ number_format((int) ($school['coach_count'] ?? 0)) }} coaches</span>
                    <span>{{ number_format((int) (($school['profile_views'] ?? 0) + ($school['highlight_views'] ?? 0))) }} views</span>
                </div>

                <div class="rc-school-flags rc-toolbar">
                    @if($school['is_saved'] ?? false)
                        <span class="rc-pill">Saved</span>
                    @endif
                    @if($school['is_favorite'] ?? false)
                        <span class="rc-pill rc-pill-accent">Favorite</span>
                    @endif
                </div>

                <div class="rc-school-actions">
                    @if($school['is_favorite'] ?? false)
                        <button class="rc-btn rc-btn-compact" type="button" wire:click="unfavoriteSchoolById({{ \Illuminate\Support\Js::from($schoolId) }})">★ Favorited</button>
                    @else
                        <button class="rc-btn rc-btn-compact" type="button" wire:click="favoriteSchoolById({{ \Illuminate\Support\Js::from($schoolId) }})">☆ Favorite</button>
                    @endif

                    @if($school['is_saved'] ?? false)
                        <button class="rc-btn rc-btn-compact" type="button" wire:click="unsaveSchoolById({{ \Illuminate\Support\Js::from($schoolId) }})">Saved</button>
                    @else
                        <button class="rc-btn rc-btn-compact" type="button" wire:click="saveSchoolById({{ \Illuminate\Support\Js::from($schoolId) }})">Save</button>
                    @endif

                    <button class="rc-btn rc-btn-compact rc-btn-primary" type="button" wire:click="composeEmailSchool({{ \Illuminate\Support\Js::from($schoolId) }})">Email</button>
                </div>
            </div>
        @endforeach
    </div>
@endif