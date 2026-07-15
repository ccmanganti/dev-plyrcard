<div>
    <div x-show="filteredSchools.length === 0" class="rc-empty rc-discover-empty" x-cloak>
        <strong>No schools found.</strong>
        <span>Refresh the Recruiting Center or adjust your filters.</span>
    </div>

    <template x-if="viewMode === 'list' && filteredSchools.length > 0">
    <div class="rc-school-list-table rc-discover-school-list">
        <div class="rc-school-list-head rc-discover-school-list-head">
            <span>School</span><span>Head Coach</span><span>Title</span><span>Email</span><span>Div</span><span></span>
        </div>
        <template x-for="schoolItem in visibleSchools" :key="`list-${schoolItem.id}`">
            <div class="rc-school-list-row rc-discover-school-list-row" x-bind:class="{ 'is-selected': isSelected(schoolItem.id) }">
                <button class="rc-school-list-name rc-discover-school-list-school" type="button" x-on:click="openSchool(schoolItem.id)">
                    <span class="rc-school-list-logo-box rc-school-logo-placeholder" x-bind:class="{ 'is-missing-logo': !schoolItem.logo_url }">
                        <img x-show="schoolItem.logo_url" class="rc-school-list-logo" x-bind:src="schoolItem.logo_url" x-bind:alt="`${schoolItem.name} logo`" loading="lazy" referrerpolicy="no-referrer" x-on:error="$el.style.display='none'; $el.closest('.rc-school-list-logo-box')?.classList.add('is-missing-logo')">
                        <span class="rc-logo-fallback-text" x-text="initialsFor(schoolItem.name)"></span>
                    </span>
                    <span class="rc-discover-school-list-name-copy" x-text="schoolItem.name"></span>
                </button>
                <span class="rc-discover-list-coach">
                    <span x-text="schoolItem.head_coach_name || '—'"></span>
                    <span class="rc-head-coach-chip" x-show="String(schoolItem.head_coach_title || '').toLowerCase().includes('head')" x-cloak>HC</span>
                </span>
                <span class="rc-discover-list-muted" x-text="schoolItem.head_coach_title || 'Coach'"></span>
                <span class="rc-discover-list-email">
                    <a x-show="schoolItem.head_coach_email" x-bind:href="`mailto:${schoolItem.head_coach_email}`" x-text="schoolItem.head_coach_email"></a>
                    <span x-show="!schoolItem.head_coach_email">—</span>
                </span>
                <span class="rc-discover-list-division" x-text="shortDivision(schoolItem.division)"></span>
                <div class="rc-school-list-actions rc-discover-list-actions">
                    <button class="rc-discover-row-check" type="button" x-on:click.stop="toggleSchool(schoolItem.id)" x-bind:class="{ 'is-selected': isSelected(schoolItem.id) }" x-bind:aria-pressed="isSelected(schoolItem.id) ? 'true' : 'false'" x-bind:aria-label="`Select ${schoolItem.name}`">
                        <span x-text="isSelected(schoolItem.id) ? '✓' : ''"></span>
                    </button>
                </div>
            </div>
        </template>
    </div>
    </template>

    <template x-if="viewMode === 'grid' && filteredSchools.length > 0">
    <div class="rc-school-grid rc-discover-school-grid" style="display:grid">
        <template x-for="schoolItem in visibleSchools" :key="`grid-${schoolItem.id}`">
            <article class="rc-school-card rc-discover-school-card" x-bind:class="{ 'is-selected': isSelected(schoolItem.id) }">
                <div class="rc-discover-card-main">
                    <button class="rc-discover-card-title" type="button" x-on:click="openSchool(schoolItem.id)">
                        <span class="rc-school-card-logo-box rc-school-logo-placeholder" x-bind:class="{ 'is-missing-logo': !schoolItem.logo_url }">
                            <img x-show="schoolItem.logo_url" class="rc-school-card-logo" x-bind:src="schoolItem.logo_url" x-bind:alt="`${schoolItem.name} logo`" loading="lazy" referrerpolicy="no-referrer" x-on:error="$el.style.display='none'; $el.closest('.rc-school-card-logo-box')?.classList.add('is-missing-logo')">
                            <span class="rc-logo-fallback-text" x-text="initialsFor(schoolItem.name)"></span>
                        </span>
                        <span class="rc-discover-card-copy">
                            <strong x-text="schoolItem.name"></strong>
                            <small x-text="schoolItem.conference || 'Conference unavailable'"></small>
                        </span>
                    </button>
                    <button class="rc-discover-card-check" type="button" x-on:click.stop="toggleSchool(schoolItem.id)" x-bind:class="{ 'is-selected': isSelected(schoolItem.id) }" x-bind:aria-pressed="isSelected(schoolItem.id) ? 'true' : 'false'" x-bind:aria-label="`Select ${schoolItem.name}`">
                        <span x-text="isSelected(schoolItem.id) ? '✓' : ''"></span>
                    </button>
                </div>
                <div class="rc-discover-card-rule"></div>
                <div class="rc-discover-card-footer">
                    <span class="rc-discover-division-pill" x-text="schoolItem.division || 'Unlisted'"></span>
                    <span class="rc-discover-coach-count" x-text="`${Number(schoolItem.coach_count || 0).toLocaleString()} ${Number(schoolItem.coach_count || 0) === 1 ? 'coach' : 'coaches'}`"></span>
                </div>
            </article>
        </template>
    </div>
    </template>
</div>