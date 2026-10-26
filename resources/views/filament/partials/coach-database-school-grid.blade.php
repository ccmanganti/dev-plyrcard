@php
    // v115: Discover is a browser-local interaction surface. The payload contains only
    // canonical local School/Coach fields plus cached stat scalars and player-local
    // favorite/list membership. No raw GHL payloads are serialized here.
    $gridSchools = collect($schools ?? [])
        ->filter(fn ($school) => is_array($school))
        ->map(function (array $school): array {
            $headCoach = is_array($school['head_coach'] ?? null) ? $school['head_coach'] : [];
            $coaches = collect($school['coaches'] ?? [])
                ->filter(fn ($coach) => is_array($coach))
                ->map(fn (array $coach): array => [
                    'id' => (string) ($coach['id'] ?? $coach['local_id'] ?? ''),
                    'name' => (string) ($coach['name'] ?? trim((string) (($coach['first_name'] ?? '') . ' ' . ($coach['last_name'] ?? '')))),
                    'email' => (string) ($coach['email'] ?? ''),
                    'title' => (string) ($coach['title'] ?? ''),
                ])->values()->all();

            return [
                'id' => (string) ($school['id'] ?? $school['local_id'] ?? ''),
                'name' => (string) ($school['name'] ?? 'School'),
                'logo_url' => (string) ($school['logo_url'] ?? ''),
                'conference' => (string) ($school['conference'] ?? ''),
                'division' => (string) ($school['division'] ?? ''),
                'city' => (string) ($school['city'] ?? ''),
                'state' => (string) ($school['state'] ?? ''),
                'coach_count' => (int) ($school['coach_count'] ?? count($coaches)),
                'coaches' => $coaches,
                'head_coach_name' => (string) ($school['head_coach_name'] ?? $headCoach['name'] ?? ''),
                'head_coach_title' => (string) ($school['head_coach_title'] ?? $headCoach['title'] ?? ''),
                'head_coach_email' => (string) ($school['head_coach_email'] ?? $headCoach['email'] ?? ''),
                'is_favorite' => (bool) ($school['is_favorite'] ?? $school['is_favorite_school'] ?? false),
                'list_keys' => collect($school['list_keys'] ?? $school['lists'] ?? [])->map(fn ($key) => strtolower(trim((string) $key)))->filter()->unique()->values()->all(),
                'profile_views' => (int) ($school['profile_views'] ?? 0),
                'highlight_views' => (int) ($school['highlight_views'] ?? 0),
                'trigger_link_clicks' => (int) ($school['trigger_link_clicks'] ?? $school['link_clicks'] ?? 0),
                'replies' => (int) ($school['replies'] ?? $school['coach_replies'] ?? 0),
                'emails_sent' => (int) ($school['emails_sent'] ?? $school['sent_emails'] ?? 0),
                'engagement_score' => (int) ($school['engagement_score'] ?? $school['lead_score'] ?? 0),
            ];
        })
        ->filter(fn (array $school): bool => $school['id'] !== '')
        ->values()->all();

    $gridViewMode = in_array(($viewMode ?? 'grid'), ['grid', 'list'], true) ? ($viewMode ?? 'grid') : 'grid';
    $gridSelectedSchoolIds = collect($selectedSchoolIds ?? [])->map(fn ($id) => trim((string) $id))->filter()->unique()->values()->all();
@endphp

<style>
    .rc-discover-quick-actions-v115 {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: .38rem;
        flex: 0 0 auto;
    }
    .rc-discover-favorite-v115 {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.72rem;
        height: 1.72rem;
        border: 1px solid var(--rc-border);
        border-radius: .48rem;
        background: var(--rc-surface);
        color: var(--rc-muted);
        cursor: pointer;
        transition: color .14s ease, border-color .14s ease, background .14s ease, transform .14s ease;
    }
    .rc-discover-favorite-v115:hover {
        color: var(--rc-accent);
        border-color: color-mix(in srgb, var(--rc-accent) 45%, var(--rc-border));
        transform: translateY(-1px);
    }
    .rc-discover-favorite-v115.is-favorited {
        color: var(--rc-accent);
        border-color: color-mix(in srgb, var(--rc-accent) 50%, var(--rc-border));
        background: color-mix(in srgb, var(--rc-accent) 9%, var(--rc-surface));
    }
    .rc-discover-favorite-v115:disabled {
        cursor: wait;
        opacity: .62;
        transform: none;
    }
    .rc-discover-favorite-v115 svg {
        width: 15px;
        height: 15px;
        display: block;
    }
</style>

<div
    x-data="{
        allSchools: @js($gridSchools),
        viewMode: @js($gridViewMode),
        selectedSchoolIds: @js($gridSelectedSchoolIds),
        favoritePending: {},
        search: '', division: '', state: '', conference: '', displayLimit: 24,
        norm(value) { return String(value ?? '').trim().toLowerCase().replace(/\s+/g, ' '); },
        divisionKey(value) {
            return this.norm(value).replace(/ncaa\s*/g,'ncaa ').replace(/division\s*iii/g,'d-iii').replace(/division\s*ii/g,'d-ii').replace(/division\s*i/g,'d-i').replace(/\bd\s*-?\s*iii\b/g,'d-iii').replace(/\bd\s*-?\s*ii\b/g,'d-ii').replace(/\bd\s*-?\s*i\b/g,'d-i');
        },
        get matchingSchools() {
            const q = this.norm(this.search);
            const div = this.divisionKey(this.division);
            const state = this.norm(this.state);
            const conf = this.norm(this.conference);
            return this.allSchools.filter(school => {
                if (div && this.divisionKey(school.division) !== div) return false;
                if (state && this.norm(school.state) !== state) return false;
                if (conf && this.norm(school.conference) !== conf) return false;
                if (!q) return true;
                const coaches = Array.isArray(school.coaches) ? school.coaches : [];
                const haystack = this.norm([
                    school.name, school.conference, school.division, school.city, school.state,
                    ...coaches.flatMap(coach => [coach.name, coach.email, coach.title])
                ].filter(Boolean).join(' '));
                return haystack.includes(q);
            });
        },
        get availableStates() {
            const div = this.divisionKey(this.division);
            const rows = this.allSchools.filter(school => !div || this.divisionKey(school.division) === div);
            return Array.from(new Set(rows.map(school => String(school.state || '').trim()).filter(Boolean)))
                .sort((a, b) => a.localeCompare(b));
        },
        get availableConferences() {
            const div = this.divisionKey(this.division);
            const state = this.norm(this.state);
            const rows = this.allSchools.filter(school => {
                if (div && this.divisionKey(school.division) !== div) return false;
                if (state && this.norm(school.state) !== state) return false;
                return true;
            });
            return Array.from(new Set(rows.map(school => String(school.conference || '').trim()).filter(Boolean)))
                .sort((a, b) => a.localeCompare(b));
        },
        get visibleSchools() { return this.matchingSchools.slice(0, this.displayLimit); },
        isSelected(id) { return this.selectedSchoolIds.map(String).includes(String(id ?? '')); },
        toggleSchool(id) {
            id = String(id ?? ''); if (!id) return;
            this.selectedSchoolIds = this.isSelected(id)
                ? this.selectedSchoolIds.filter(item => String(item) !== id)
                : [...this.selectedSchoolIds, id];
            this.$dispatch('rc-discover-selection', { ids: [...this.selectedSchoolIds] });
        },
        favoriteIsPending(id) {
            return !!this.favoritePending[String(id ?? '')];
        },
        setFavoritePending(id, pending) {
            id = String(id ?? '');
            if (!id) return;
            const next = { ...this.favoritePending };
            if (pending) next[id] = true;
            else delete next[id];
            this.favoritePending = next;
        },
        emitFavoriteState(school) {
            if (!school) return;
            const id = String(school.id ?? '');
            if (!id) return;
            const detail = {
                id,
                school_id: id,
                is_favorite: !!school.is_favorite,
                list_keys: Array.isArray(school.list_keys) ? [...school.list_keys] : [],
                school: { ...school },
            };
            window.__plyrRcFavoritesDirty = true;
            window.dispatchEvent(new CustomEvent('rc-discover-school-state', { detail }));
            window.dispatchEvent(new CustomEvent('rc-favorite-school-updated', { detail }));
        },
        async toggleFavorite(school) {
            const id = String(school?.id ?? '');
            if (!id || this.favoriteIsPending(id)) return;

            const row = this.allSchools.find(item => String(item?.id ?? '') === id) || school;
            const previous = !!row.is_favorite;
            const next = !previous;

            row.is_favorite = next;
            this.setFavoritePending(id, true);
            this.emitFavoriteState(row);

            try {
                const result = await this.$wire.call('queueSchoolFavoriteState', id, next);
                if (!result || result.success === false) {
                    row.is_favorite = previous;
                    this.emitFavoriteState(row);
                    return;
                }

                if (Object.prototype.hasOwnProperty.call(result, 'favorite')) {
                    row.is_favorite = !!result.favorite;
                    this.emitFavoriteState(row);
                }
            } catch (_) {
                row.is_favorite = previous;
                this.emitFavoriteState(row);
            } finally {
                this.setFavoritePending(id, false);
            }
        },
        openSchool(id) {
            id = String(id ?? ''); if (!id) return;
            const school = this.allSchools.find(item => String(item?.id ?? '') === id) || null;
            if (!school) return;
            // v109: opening Discover drawer is 100% browser-local. No Livewire request.
            window.__plyrSchoolDrawerOptimistic = JSON.parse(JSON.stringify(school));
            this.$dispatch('rc-open-school-optimistic', { school: window.__plyrSchoolDrawerOptimistic });
        },
        initialsFor(name) { const words=String(name ?? '').trim().split(/\s+/).filter(Boolean); return words.slice(0,2).map(word=>word.charAt(0)).join('').toUpperCase() || 'S'; },
        shortDivision(value) { return String(value ?? '').trim() || '—'; },
        publishCount() { this.$dispatch('rc-discover-count', { total: this.matchingSchools.length, shown: this.visibleSchools.length }); },
        publishStates() {
            const options = this.availableStates;
            if (this.state && !options.includes(this.state)) this.state = '';
            this.$dispatch('rc-discover-states', { states: options, selected: this.state });
        },
        publishConferences() {
            const options = this.availableConferences;
            if (this.conference && !options.includes(this.conference)) this.conference = '';
            this.$dispatch('rc-discover-conferences', { conferences: options, selected: this.conference });
        }
    }"
    x-init="$nextTick(() => { publishCount(); publishStates(); publishConferences(); }); $watch('search', () => { displayLimit=24; $nextTick(() => publishCount()) }); $watch('division', () => { displayLimit=24; $nextTick(() => { publishStates(); publishConferences(); publishCount(); }) }); $watch('state', () => { displayLimit=24; $nextTick(() => { publishConferences(); publishCount(); }) }); $watch('conference', () => { displayLimit=24; $nextTick(() => publishCount()) })"
    x-on:rc-discover-filter.window="search=String($event.detail?.search ?? ''); division=String($event.detail?.division ?? ''); state=String($event.detail?.state ?? ''); conference=String($event.detail?.conference ?? ''); $nextTick(() => { publishStates(); publishConferences(); publishCount(); })"
    x-on:rc-discover-view.window="viewMode = $event.detail?.mode === 'list' ? 'list' : 'grid'"
    x-on:rc-discover-load-more.window="displayLimit += 24; $nextTick(() => publishCount())"
    x-on:rc-discover-toggle-visible.window="const visible = visibleSchools.map(item => String(item.id)); const all = visible.length > 0 && visible.every(id => isSelected(id)); selectedSchoolIds = all ? selectedSchoolIds.filter(id => !visible.includes(String(id))) : Array.from(new Set([...selectedSchoolIds.map(String), ...visible])); $dispatch('rc-discover-selection', { ids: [...selectedSchoolIds] })"
    x-on:rc-discover-clear-selection.window="selectedSchoolIds=[]; $dispatch('rc-discover-selection', { ids: [] })"
    x-on:rc-discover-school-state.window="const id=String($event.detail?.id || $event.detail?.school_id || ''); const row=allSchools.find(item => String(item.id)===id); if(row){ if(Object.prototype.hasOwnProperty.call($event.detail || {}, 'is_favorite')) row.is_favorite=!!$event.detail.is_favorite; row.list_keys=Array.isArray($event.detail?.list_keys)?[...$event.detail.list_keys]:row.list_keys; }"
>
    <div x-show="matchingSchools.length === 0" class="rc-empty rc-discover-empty"><strong>No schools found.</strong><span>Adjust your local filters.</span></div>

    <template x-if="viewMode === 'list' && matchingSchools.length > 0">
        <div class="rc-school-list-table rc-discover-school-list">
            <div class="rc-school-list-head rc-discover-school-list-head"><span>School</span><span>Head Coach</span><span>Title</span><span>Email</span><span>Div</span><span></span></div>
            <template x-for="schoolItem in visibleSchools" :key="`list-${schoolItem.id}`">
                <div class="rc-school-list-row rc-discover-school-list-row" x-bind:class="{ 'is-selected': isSelected(schoolItem.id) }">
                    <button class="rc-school-list-name rc-discover-school-list-school" type="button" x-on:click="openSchool(schoolItem.id)">
                        <span class="rc-school-list-logo-box rc-school-logo-placeholder" x-bind:class="{ 'is-missing-logo': !schoolItem.logo_url }"><img x-show="schoolItem.logo_url" class="rc-school-list-logo" x-bind:src="schoolItem.logo_url" x-bind:alt="`${schoolItem.name} logo`" loading="lazy" referrerpolicy="no-referrer" x-on:error="$el.style.display='none'"><span class="rc-logo-fallback-text" x-text="initialsFor(schoolItem.name)"></span></span>
                        <span class="rc-discover-school-list-name-copy" x-text="schoolItem.name"></span>
                    </button>
                    <span class="rc-discover-list-coach"><span x-text="schoolItem.head_coach_name || '—'"></span></span>
                    <span class="rc-discover-list-muted" x-text="schoolItem.head_coach_title || 'Coach'"></span>
                    <span class="rc-discover-list-email"><a x-show="schoolItem.head_coach_email" x-bind:href="`mailto:${schoolItem.head_coach_email}`" x-text="schoolItem.head_coach_email"></a><span x-show="!schoolItem.head_coach_email">—</span></span>
                    <span class="rc-discover-list-division" x-text="shortDivision(schoolItem.division)"></span>
                    <div class="rc-school-list-actions rc-discover-list-actions rc-discover-quick-actions-v115">
                        <button
                            class="rc-discover-favorite-v115"
                            type="button"
                            x-on:click.stop.prevent="toggleFavorite(schoolItem)"
                            x-bind:class="{ 'is-favorited': !!schoolItem.is_favorite }"
                            x-bind:disabled="favoriteIsPending(schoolItem.id)"
                            x-bind:aria-pressed="schoolItem.is_favorite ? 'true' : 'false'"
                            x-bind:title="schoolItem.is_favorite ? 'Remove from Favorites' : 'Add to Favorites'"
                            x-bind:aria-label="schoolItem.is_favorite ? `Remove ${schoolItem.name} from Favorites` : `Add ${schoolItem.name} to Favorites`"
                        >
                            <svg viewBox="0 0 24 24" x-bind:fill="schoolItem.is_favorite ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z"/></svg>
                        </button>
                        <button class="rc-discover-row-check" type="button" x-on:click.stop="toggleSchool(schoolItem.id)" x-bind:class="{ 'is-selected': isSelected(schoolItem.id) }" title="Select school"><span x-text="isSelected(schoolItem.id) ? '✓' : ''"></span></button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <template x-if="viewMode === 'grid' && matchingSchools.length > 0">
        <div class="rc-school-grid rc-discover-school-grid" style="display:grid">
            <template x-for="schoolItem in visibleSchools" :key="`grid-${schoolItem.id}`">
                <article class="rc-school-card rc-discover-school-card" x-bind:class="{ 'is-selected': isSelected(schoolItem.id) }">
                    <div class="rc-discover-card-main" style="grid-template-columns:minmax(0,1fr) auto">
                        <button class="rc-discover-card-title" type="button" x-on:click="openSchool(schoolItem.id)">
                            <span class="rc-school-card-logo-box rc-school-logo-placeholder" x-bind:class="{ 'is-missing-logo': !schoolItem.logo_url }"><img x-show="schoolItem.logo_url" class="rc-school-card-logo" x-bind:src="schoolItem.logo_url" x-bind:alt="`${schoolItem.name} logo`" loading="lazy" referrerpolicy="no-referrer" x-on:error="$el.style.display='none'"><span class="rc-logo-fallback-text" x-text="initialsFor(schoolItem.name)"></span></span>
                            <span class="rc-discover-card-copy"><strong x-text="schoolItem.name"></strong><small x-text="schoolItem.conference || 'Conference unavailable'"></small></span>
                        </button>
                        <div class="rc-discover-quick-actions-v115">
                            <button
                                class="rc-discover-favorite-v115"
                                type="button"
                                x-on:click.stop.prevent="toggleFavorite(schoolItem)"
                                x-bind:class="{ 'is-favorited': !!schoolItem.is_favorite }"
                                x-bind:disabled="favoriteIsPending(schoolItem.id)"
                                x-bind:aria-pressed="schoolItem.is_favorite ? 'true' : 'false'"
                                x-bind:title="schoolItem.is_favorite ? 'Remove from Favorites' : 'Add to Favorites'"
                                x-bind:aria-label="schoolItem.is_favorite ? `Remove ${schoolItem.name} from Favorites` : `Add ${schoolItem.name} to Favorites`"
                            >
                                <svg viewBox="0 0 24 24" x-bind:fill="schoolItem.is_favorite ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z"/></svg>
                            </button>
                            <button class="rc-discover-card-check" type="button" x-on:click.stop="toggleSchool(schoolItem.id)" x-bind:class="{ 'is-selected': isSelected(schoolItem.id) }" title="Select school"><span x-text="isSelected(schoolItem.id) ? '✓' : ''"></span></button>
                        </div>
                    </div>
                    <div class="rc-discover-card-rule"></div>
                    <div class="rc-discover-card-footer"><span class="rc-discover-division-pill" x-text="schoolItem.division || 'Unlisted'"></span><span class="rc-discover-coach-count" x-text="`${Number(schoolItem.coach_count || 0).toLocaleString()} ${Number(schoolItem.coach_count || 0) === 1 ? 'coach' : 'coaches'}`"></span></div>
                </article>
            </template>
        </div>
    </template>
</div>