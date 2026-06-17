@php
    $coachId = (string) ($coach['id'] ?? '');
    $favoriteAction = ($coach['is_favorite_coach'] ?? false) ? 'unfavoriteCoach' : 'favoriteCoach';
    $saveAction = ($coach['is_saved_coach'] ?? false) ? 'unsaveCoach' : 'saveCoach';
    $coachName = trim((string) ($coach['name'] ?? '')) ?: trim(((string) ($coach['first_name'] ?? '')).' '.((string) ($coach['last_name'] ?? ''))) ?: 'Coach';
    $coachTitle = trim((string) ($coach['title'] ?? '')) ?: 'Coach';
    $coachSchool = trim((string) ($coach['school'] ?? '')) ?: 'School unavailable';
    $coachEmail = trim((string) ($coach['email'] ?? ''));
    $coachTags = collect($coach['tags'] ?? [])->map(fn ($tag) => strtolower(trim((string) $tag)))->all();
    $coachListLabels = collect($lists ?? [])
        ->filter(function ($list) use ($coachTags) {
            $listTag = strtolower(trim((string) ($list['tag'] ?? '')));
            return $listTag !== '' && in_array($listTag, $coachTags, true);
        })
        ->pluck('label')
        ->filter()
        ->values();
@endphp

<article class="rc-coach-row rc-pulse" wire:key="coach-row-{{ $coachId ?: md5(json_encode($coach)) }}">
    <div class="rc-coach-main">
        <div class="rc-coach-avatar" aria-hidden="true">
            {{ strtoupper(substr($coachName, 0, 1)) }}
        </div>

        <div class="rc-coach-copy">
            <div class="rc-coach-heading">
                <h3>{{ $coachName }}</h3>
                <div class="rc-coach-badges">
                    @if($coach['is_saved_coach'] ?? false)
                        <span class="rc-pill rc-pill-accent">Saved</span>
                    @endif
                    @if($coach['is_favorite_coach'] ?? false)
                        <span class="rc-pill rc-pill-accent">Favorite</span>
                    @endif
                    @if($coachListLabels->isNotEmpty())
                        <span class="rc-pill">{{ $coachListLabels->count() }} {{ $coachListLabels->count() === 1 ? 'list' : 'lists' }}</span>
                    @endif
                </div>
            </div>

            <div class="rc-coach-meta">
                <span>{{ $coachTitle }}</span>
                <span>{{ $coachSchool }}</span>
                @if($coachEmail !== '')
                    <span>{{ $coachEmail }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="rc-coach-actions">
        <button class="rc-btn rc-btn-primary" type="button" wire:click="composeToCoach('{{ $coachId }}')" wire:loading.attr="disabled" wire:target="composeToCoach('{{ $coachId }}')">
            <span wire:loading.remove wire:target="composeToCoach('{{ $coachId }}')">Email</span>
            <span wire:loading.flex wire:target="composeToCoach('{{ $coachId }}')" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span></span>
        </button>

        <div class="rc-action-menu" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
            <button class="rc-icon-button rc-action-trigger" type="button" @click="open = !open" :aria-expanded="open.toString()" aria-label="Coach actions">
                <span aria-hidden="true">⋯</span>
            </button>

            <div class="rc-menu-panel" x-cloak x-show="open" x-transition.origin.top.right>
                <button class="rc-menu-item" type="button" wire:click="{{ $saveAction }}('{{ $coachId }}')" wire:loading.attr="disabled" wire:target="saveCoach('{{ $coachId }}'),unsaveCoach('{{ $coachId }}')" @click="open = false">
                    <span wire:loading.remove wire:target="saveCoach('{{ $coachId }}'),unsaveCoach('{{ $coachId }}')">{{ ($coach['is_saved_coach'] ?? false) ? 'Remove saved coach' : 'Save coach' }}</span>
                    <span wire:loading.flex wire:target="saveCoach('{{ $coachId }}'),unsaveCoach('{{ $coachId }}')" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Updating</span>
                </button>

                <button class="rc-menu-item" type="button" wire:click="{{ $favoriteAction }}('{{ $coachId }}')" wire:loading.attr="disabled" wire:target="favoriteCoach('{{ $coachId }}'),unfavoriteCoach('{{ $coachId }}')" @click="open = false">
                    <span wire:loading.remove wire:target="favoriteCoach('{{ $coachId }}'),unfavoriteCoach('{{ $coachId }}')">{{ ($coach['is_favorite_coach'] ?? false) ? 'Remove favorite coach' : 'Favorite coach' }}</span>
                    <span wire:loading.flex wire:target="favoriteCoach('{{ $coachId }}'),unfavoriteCoach('{{ $coachId }}')" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Updating</span>
                </button>

                @if($coach['school_id'] ?? null)
                    <button class="rc-menu-item" type="button" wire:click="openSchoolFromCoach('{{ $coach['school_id'] }}')" @click="open = false">Open school</button>
                @endif

                @if(!empty($lists))
                    <div class="rc-menu-label">Lists</div>
                    @foreach($lists as $list)
                        @php
                            $listKey = (string) ($list['key'] ?? '');
                            $listLabel = (string) ($list['label'] ?? 'List');
                            $listTag = strtolower(trim((string) ($list['tag'] ?? '')));
                            $inList = $listTag !== '' && in_array($listTag, $coachTags, true);
                        @endphp
                        <button class="rc-menu-item" type="button" wire:click="{{ $inList ? 'removeCoachFromList' : 'addCoachToList' }}('{{ $coachId }}','{{ $listKey }}')" wire:loading.attr="disabled" wire:target="addCoachToList('{{ $coachId }}','{{ $listKey }}'),removeCoachFromList('{{ $coachId }}','{{ $listKey }}')" @click="open = false">
                            <span wire:loading.remove wire:target="addCoachToList('{{ $coachId }}','{{ $listKey }}'),removeCoachFromList('{{ $coachId }}','{{ $listKey }}')">{{ $inList ? 'Remove from ' : 'Add to ' }}{{ $listLabel }}</span>
                            <span wire:loading.flex wire:target="addCoachToList('{{ $coachId }}','{{ $listKey }}'),removeCoachFromList('{{ $coachId }}','{{ $listKey }}')" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Updating</span>
                        </button>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</article>