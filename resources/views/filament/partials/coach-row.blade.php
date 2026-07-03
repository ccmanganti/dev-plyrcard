@php
    $coachId = (string) ($coach['id'] ?? '');
    $coachName = trim((string) ($coach['name'] ?? collect([$coach['first_name'] ?? null, $coach['last_name'] ?? null])->filter()->implode(' '))) ?: 'Coach';
    $coachTitle = trim((string) ($coach['title'] ?? 'Coach'));
    $coachSchool = trim((string) ($coach['school'] ?? $coach['company_name'] ?? ''));
    $coachConference = trim((string) ($coach['conference'] ?? ''));
    $coachDivision = trim((string) ($coach['division'] ?? ''));
    $coachEmail = trim((string) ($coach['email'] ?? ''));
    $schoolLogoUrl = trim((string) ($coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? ''));
    $coachInitials = collect(explode(' ', $coachName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
    $coachInitials = strtoupper($coachInitials ?: 'C');
    $schoolInitials = collect(explode(' ', $coachSchool))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
    $schoolInitials = strtoupper($schoolInitials ?: $coachInitials);
    $coachTags = collect($coach['tags'] ?? [])->map(fn ($tag) => trim((string) $tag))->filter()->values();
@endphp

<div class="rc-coach-row">
    <div class="rc-coach-main">
        <div class="rc-coach-avatar rc-coach-school-logo-wrap rc-school-logo-placeholder {{ $schoolLogoUrl === '' ? 'is-missing-logo' : '' }}">
            @if($schoolLogoUrl !== '')
                <img class="rc-coach-school-logo" src="{{ $schoolLogoUrl }}" alt="{{ $coachSchool !== '' ? $coachSchool . ' logo' : 'School logo' }}" loading="lazy" onerror="this.closest('.rc-coach-school-logo-wrap').classList.add('is-missing-logo')">
            @endif
            <span class="rc-logo-fallback-text">{{ $schoolInitials }}</span>
        </div>

        <div class="rc-coach-copy">
            <div class="rc-coach-heading">
                <h3>{{ $coachName }}</h3>
                <div class="rc-coach-badges">
                    @if($coach['is_favorite_coach'] ?? false)
                        <span class="rc-pill rc-pill-accent">Favorite</span>
                    @endif
                    @if($coach['is_saved_coach'] ?? false)
                        <span class="rc-pill">Saved</span>
                    @endif
                    @if($coach['viewed_profile'] ?? false)
                        <span class="rc-pill rc-pill-accent">Viewed profile</span>
                    @endif
                    @if($coach['viewed_highlights'] ?? false)
                        <span class="rc-pill rc-pill-accent">Viewed highlights</span>
                    @endif
                </div>
            </div>

            <div class="rc-coach-meta">
                @if($coachTitle !== '')
                    <span>{{ $coachTitle }}</span>
                @endif
                @if($coachSchool !== '')
                    <span>{{ $coachSchool }}</span>
                @endif
                @if($coachConference !== '')
                    <span>{{ $coachConference }}</span>
                @endif
                @if($coachDivision !== '')
                    <span>{{ $coachDivision }}</span>
                @endif
                @if($coachEmail !== '')
                    <span>{{ $coachEmail }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="rc-coach-actions">
        @if($coachId !== '')
            @if($coach['is_favorite_coach'] ?? false)
                <button type="button" class="rc-btn rc-btn-compact" wire:click="unfavoriteCoach({{ \Illuminate\Support\Js::from($coachId) }})" wire:loading.attr="disabled" wire:target="unfavoriteCoach">★</button>
            @else
                <button type="button" class="rc-btn rc-btn-compact" wire:click="favoriteCoach({{ \Illuminate\Support\Js::from($coachId) }})" wire:loading.attr="disabled" wire:target="favoriteCoach">☆</button>
            @endif

            @if($coach['is_saved_coach'] ?? false)
                <button type="button" class="rc-btn rc-btn-compact" wire:click="unsaveCoach({{ \Illuminate\Support\Js::from($coachId) }})" wire:loading.attr="disabled" wire:target="unsaveCoach">Saved</button>
            @else
                <button type="button" class="rc-btn rc-btn-compact" wire:click="saveCoach({{ \Illuminate\Support\Js::from($coachId) }})" wire:loading.attr="disabled" wire:target="saveCoach">Save</button>
            @endif
        @endif

        @if($coachEmail !== '')
            <a class="rc-btn rc-btn-compact rc-btn-primary" href="mailto:{{ $coachEmail }}">Email</a>
        @endif
    </div>
</div>