@php
    $compact = (bool) ($compact ?? false);
    $schoolRows = collect($schools ?? [])->values();
@endphp

<div class="rc-school-grid {{ $compact ? 'is-compact' : '' }}">
    @if($schoolRows->isNotEmpty())
        @foreach ($schoolRows as $school)
            @php
                $schoolId = (string) ($school['id'] ?? '');
                $division = (string) ($school['division'] ?? 'N/A');
                $isFavorite = (bool) ($school['is_favorite'] ?? false);
                $isSaved = (bool) ($school['is_saved'] ?? false);
                $headCoach = $school['head_coach']['name'] ?? null;
                $favoriteAction = $isFavorite ? 'unfavoriteSchoolById' : 'favoriteSchoolById';
                $saveAction = $isSaved ? 'unsaveSchoolById' : 'saveSchoolById';
                $schoolListKeys = collect($school['list_keys'] ?? [])->map(fn ($key) => (string) $key)->filter()->values();
            @endphp

            <article class="rc-school-card" wire:key="school-card-{{ $schoolId }}">
                <div class="rc-school-topline">
                    <span class="rc-badge">{{ $division }}</span>

                    <div class="rc-action-menu" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <button
                            type="button"
                            class="rc-icon-button rc-action-trigger {{ $isFavorite ? 'is-active' : '' }}"
                            @click="open = !open"
                            :aria-expanded="open.toString()"
                            aria-label="School actions"
                        >
                            <span aria-hidden="true">⋯</span>
                        </button>

                        <div class="rc-menu-panel" x-cloak x-show="open" x-transition.origin.top.right>
                            <button
                                type="button"
                                class="rc-menu-item"
                                wire:click="{{ $saveAction }}('{{ $schoolId }}')"
                                wire:loading.attr="disabled"
                                wire:target="saveSchoolById('{{ $schoolId }}'),unsaveSchoolById('{{ $schoolId }}')"
                                @click="open = false"
                            >
                                <span wire:loading.remove wire:target="saveSchoolById('{{ $schoolId }}'),unsaveSchoolById('{{ $schoolId }}')">
                                    {{ $isSaved ? 'Remove saved school' : 'Save school' }}
                                </span>
                                <span wire:loading.flex wire:target="saveSchoolById('{{ $schoolId }}'),unsaveSchoolById('{{ $schoolId }}')" style="align-items:center;gap:.35rem">
                                    <span class="rc-spinner-mini"></span> Updating
                                </span>
                            </button>

                            <button
                                type="button"
                                class="rc-menu-item"
                                wire:click="{{ $favoriteAction }}('{{ $schoolId }}')"
                                wire:loading.attr="disabled"
                                wire:target="favoriteSchoolById('{{ $schoolId }}'),unfavoriteSchoolById('{{ $schoolId }}')"
                                @click="open = false"
                            >
                                <span wire:loading.remove wire:target="favoriteSchoolById('{{ $schoolId }}'),unfavoriteSchoolById('{{ $schoolId }}')">
                                    {{ $isFavorite ? 'Remove favorite school' : 'Favorite school' }}
                                </span>
                                <span wire:loading.flex wire:target="favoriteSchoolById('{{ $schoolId }}'),unfavoriteSchoolById('{{ $schoolId }}')" style="align-items:center;gap:.35rem">
                                    <span class="rc-spinner-mini"></span> Updating
                                </span>
                            </button>

                            @if(!empty($lists))
                                <div class="rc-menu-label">Lists</div>
                                @foreach($lists as $list)
                                    @php
                                        $listKey = (string) ($list['key'] ?? '');
                                        $listLabel = (string) ($list['label'] ?? 'List');
                                        $inList = $schoolListKeys->contains($listKey);
                                        $listAction = $inList ? 'removeSchoolFromListById' : 'addSchoolToListById';
                                    @endphp

                                    @if($listKey !== '')
                                        <button
                                            type="button"
                                            class="rc-menu-item"
                                            wire:click="{{ $listAction }}('{{ $schoolId }}','{{ $listKey }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="addSchoolToListById('{{ $schoolId }}','{{ $listKey }}'),removeSchoolFromListById('{{ $schoolId }}','{{ $listKey }}')"
                                            @click="open = false"
                                        >
                                            <span wire:loading.remove wire:target="addSchoolToListById('{{ $schoolId }}','{{ $listKey }}'),removeSchoolFromListById('{{ $schoolId }}','{{ $listKey }}')">
                                                {{ $inList ? 'Remove from ' : 'Add to ' }}{{ $listLabel }}
                                            </span>
                                            <span wire:loading.flex wire:target="addSchoolToListById('{{ $schoolId }}','{{ $listKey }}'),removeSchoolFromListById('{{ $schoolId }}','{{ $listKey }}')" style="align-items:center;gap:.35rem">
                                                <span class="rc-spinner-mini"></span> Updating
                                            </span>
                                        </button>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>

                <h3>{{ $school['name'] ?? 'Unnamed School' }}</h3>
                <p class="rc-school-conference">{{ $school['conference'] ?: 'Conference unavailable' }}</p>

                <div class="rc-school-meta">
                    <span>{{ $headCoach ?: 'Coach unavailable' }}</span>
                    <span>{{ number_format((int) ($school['coach_count'] ?? 0)) }} {{ ((int) ($school['coach_count'] ?? 0)) === 1 ? 'coach' : 'coaches' }}</span>
                </div>

                <div class="rc-toolbar rc-school-flags">
                    @if($isSaved)
                        <span class="rc-pill rc-pill-accent">Saved</span>
                    @endif
                    @if($isFavorite)
                        <span class="rc-pill rc-pill-accent">Favorite</span>
                    @endif
                    @if($schoolListKeys->count())
                        <span class="rc-pill">{{ $schoolListKeys->count() }} {{ $schoolListKeys->count() === 1 ? 'list' : 'lists' }}</span>
                    @endif
                </div>

                <div class="rc-school-actions">
                    <button type="button" class="rc-btn rc-btn-primary" wire:click="selectSchoolById('{{ $schoolId }}')" wire:loading.attr="disabled" wire:target="selectSchoolById('{{ $schoolId }}')">
                        <span wire:loading.remove wire:target="selectSchoolById('{{ $schoolId }}')">View coaches</span>
                        <span wire:loading.flex wire:target="selectSchoolById('{{ $schoolId }}')" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Loading</span>
                    </button>
                </div>
            </article>
        @endforeach
    @else
        <div class="rc-empty rc-empty-wide">
            <strong>No schools found.</strong>
            <span>Try changing your search or filters.</span>
        </div>
    @endif
</div>