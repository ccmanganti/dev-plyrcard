@php
    // v10.55: compute the canonical drawer catalog before Alpine serializes it.
    $globalSchoolDrawerCatalog = in_array($section, ['dashboard', 'schools', 'favorites', 'lists'], true)
        ? $this->discoverClientSchools
        : [];
@endphp

<x-filament-panels::page>
    <div class="rc-livewire-root"
        data-rc-current-section="{{ $section }}"
        data-rc-free-plan="{{ ($isFreePlanAccount ?? false) ? '1' : '0' }}"
        x-data="{
            freeGateOpen: @js((bool) ($showFreePlanGate ?? false)),
            freeGateSection: @js((string) ($freePlanGateSection ?? 'dashboard')),
            freeGateNames: {
                dashboard: 'Dashboard',
                schools: 'Discover Schools',
                coaches: 'Coach Database',
                favorites: 'Favorites',
                lists: 'Lists',
                conversations: 'Inbox',
                campaigns: 'Campaigns',
                compose: 'Compose Email',
                support: 'Support',
                schedule: 'Schedule'
            },
            openFreeGate(section) {
                const normalized = String(section || 'dashboard').toLowerCase();
                this.freeGateSection = Object.prototype.hasOwnProperty.call(this.freeGateNames, normalized)
                    ? normalized
                    : 'dashboard';
                this.freeGateOpen = true;
            },
            closeFreeGate() {
                this.freeGateOpen = false;
            },
discoverSelectedIds: [],
            discoverSearch: '',
            discoverDivision: '',
            discoverConference: '',
            discoverAvailableConferences: [],
            discoverViewMode: 'grid',
            discoverClientCount: 0,
            discoverClientShown: 24,
            discoverListsOpen: false,
            discoverDrawerTab: 'coaches',
            discoverSchoolComms: [],
            discoverSchoolCommsLoading: false,
            discoverSchoolCommsLoadedFor: '',
            dashboardDetail: @js(in_array($section, ['profile-views', 'coach-engagement'], true) ? $section : ''),
            discoverLists: @js(collect($this->lists ?? [])->filter(fn($list) => is_array($list))->values()->all()),
            discoverBulkNotice: '',
            discoverBulkNoticeTimer: null,
            discoverNewBulkListName: '',
            discoverNewDrawerListName: '',
            discoverCreatingList: false,
            optimisticSchool: window.__plyrSchoolDrawerOptimistic || null,
            globalSchoolCatalog: @js($globalSchoolDrawerCatalog),
            normalizeGlobalSchoolName(value) {
                return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
            },
            applyGlobalSchoolState(detail) {
                if (!detail) return;
                const id = String(detail.id ?? '').trim();
                const row = (Array.isArray(this.globalSchoolCatalog) ? this.globalSchoolCatalog : []).find(item => String(item?.id ?? '').trim() === id);
                if (row) {
                    if (Object.prototype.hasOwnProperty.call(detail, 'is_favorite')) row.is_favorite = !!detail.is_favorite;
                    if (Array.isArray(detail.list_keys)) row.list_keys = [...detail.list_keys];
                }
            },
            openGlobalSchool(reference) {
                const source = (reference && typeof reference === 'object') ? reference : { id: reference };
                const sourceId = String(source?.id ?? source?.school_id ?? source?.business_id ?? source?.company_id ?? source?.ghl_business_id ?? '').trim();
                const sourceName = this.normalizeGlobalSchoolName(source?.name ?? source?.school ?? source?.school_name ?? source?.company_name ?? '');
                const rows = Array.isArray(this.globalSchoolCatalog) ? this.globalSchoolCatalog : [];
                const local = rows.find(row => {
                    const ids = [row?.id, row?.school_id, row?.business_id, row?.company_id, row?.ghl_business_id]
                        .map(value => String(value ?? '').trim())
                        .filter(Boolean);
                    if (sourceId && ids.includes(sourceId)) return true;
                    const rowName = this.normalizeGlobalSchoolName(row?.name ?? row?.school ?? row?.school_name ?? row?.company_name ?? '');
                    return !!sourceName && rowName === sourceName;
                }) || null;

                if (!local && (!source || typeof source !== 'object')) return;

                const merged = { ...(local || {}), ...(source || {}) };
                if (local) {
                    // Canonical local values must win for all interactive identity/state.
                    merged.id = local.id;
                    merged.school_id = local.school_id ?? local.id;
                    merged.name = local.name ?? merged.name;
                    merged.logo_url = local.logo_url || merged.logo_url || '';
                    merged.city = local.city ?? merged.city;
                    merged.state = local.state ?? merged.state;
                    merged.division = local.division ?? merged.division;
                    merged.conference = local.conference ?? merged.conference;
                    merged.coaches = Array.isArray(local.coaches) ? local.coaches : [];
                    merged.coach_count = Number(local.coach_count ?? local.coaches_count ?? merged.coach_count ?? merged.coaches.length ?? 0);
                    merged.is_favorite = !!local.is_favorite;
                    merged.list_keys = Array.isArray(local.list_keys) ? [...local.list_keys] : [];
                }

                window.__plyrSchoolDrawerOptimistic = merged;
                this.optimisticSchool = merged;
                this.discoverDrawerTab = 'coaches';
                this.discoverSchoolComms = [];
                this.discoverSchoolCommsLoading = false;
                this.discoverSchoolCommsLoadedFor = '';
                this.discoverListsOpen = false;
                this.discoverNewDrawerListName = '';
            },
            async loadDiscoverCommunications(force = false) {
                const id = String(this.optimisticSchool?.id ?? this.optimisticSchool?.school_id ?? '').trim();
                if (!id || this.discoverSchoolCommsLoading) return;
                if (!force && this.discoverSchoolCommsLoadedFor === id && this.discoverSchoolComms.length > 0) return;

                this.discoverSchoolCommsLoading = true;
                try {
                    const rows = await this.$wire.call('schoolCommunicationHistoryForClient', id);
                    // Ignore a late response if the user already opened a different school.
                    const currentId = String(this.optimisticSchool?.id ?? this.optimisticSchool?.school_id ?? '').trim();
                    if (currentId !== id) return;
                    this.discoverSchoolComms = Array.isArray(rows) ? rows : [];
                    this.discoverSchoolCommsLoadedFor = id;
                } catch (error) {
                    console.error('Unable to load school communication history.', error);
                    this.discoverSchoolComms = [];
                } finally {
                    this.discoverSchoolCommsLoading = false;
                }
            },
            discoverListKey(list) {
                return String(list?.key || list?.slug || list?.id || '').trim();
            },
            discoverListLabel(list) {
                return String(list?.label || list?.name || this.discoverListKey(list) || 'List');
            },
            discoverListColor(list) {
                return String(list?.color || '#ff6338');
            },
            discoverListCount(list) {
                return Number(list?.schools_count ?? list?.school_count ?? (Array.isArray(list?.schools) ? list.schools.length : 0) ?? 0);
            },
            setDiscoverListCount(key, count) {
                key = String(key || '').toLowerCase();
                const item = this.discoverLists.find(list => this.discoverListKey(list).toLowerCase() === key);
                if (item) item.schools_count = Math.max(0, Number(count || 0));
            },
            showDiscoverBulkNotice(message) {
                this.discoverBulkNotice = String(message || '');
                if (this.discoverBulkNoticeTimer) window.clearTimeout(this.discoverBulkNoticeTimer);
                this.discoverBulkNoticeTimer = window.setTimeout(() => { this.discoverBulkNotice = ''; }, 3200);
            },
            async addSelectedSchoolsToDiscoverList(list) {
                const ids = [...this.discoverSelectedIds].map(String).filter(Boolean);
                const key = this.discoverListKey(list);
                if (!ids.length || !key) return;
                const label = this.discoverListLabel(list);
                const previousCount = this.discoverListCount(list);
                this.setDiscoverListCount(key, previousCount + ids.length);
                this.showDiscoverBulkNotice(`Adding ${ids.length} school${ids.length === 1 ? '' : 's'} to ${label}...`);
                try {
                    const result = await this.$wire.call('queueSchoolIdsToList', ids, key);
                    if (!result || result.success === false) {
                        this.setDiscoverListCount(key, previousCount);
                        this.showDiscoverBulkNotice(result?.error || `Unable to add schools to ${label}.`);
                        return;
                    }
                    const added = Number(result.updated_schools ?? result.school_count ?? ids.length);
                    const listCount = Number(result.list_count ?? (previousCount + added));
                    this.setDiscoverListCount(key, listCount);
                    this.showDiscoverBulkNotice(added > 0
                        ? `Added ${added} school${added === 1 ? '' : 's'} to ${label}.`
                        : `Selected school${ids.length === 1 ? ' is' : 's are'} already in ${label}.`);
                    this.discoverSelectedIds = [];
                    window.dispatchEvent(new CustomEvent('rc-discover-clear-selection'));
                } catch (error) {
                    this.setDiscoverListCount(key, previousCount);
                    this.showDiscoverBulkNotice(`Unable to add schools to ${label}.`);
                }
            },
            async createDiscoverBulkList() {
                const name = String(this.discoverNewBulkListName || '').trim();
                if (!name || this.discoverCreatingList) return;
                this.discoverCreatingList = true;
                try {
                    const result = await this.$wire.call('createCustomListQuick', name, '#ff6338');
                    if (!result || result.success === false || !result.list) {
                        this.showDiscoverBulkNotice(result?.error || 'Unable to create the list.');
                        return;
                    }
                    const list = result.list;
                    const key = this.discoverListKey(list);
                    if (key && !this.discoverLists.some(item => this.discoverListKey(item).toLowerCase() === key.toLowerCase())) {
                        this.discoverLists.push(list);
                    }
                    this.discoverNewBulkListName = '';
                    await this.addSelectedSchoolsToDiscoverList(list);
                } finally {
                    this.discoverCreatingList = false;
                }
            },
            async createDiscoverDrawerList() {
                const name = String(this.discoverNewDrawerListName || '').trim();
                if (!name || !this.optimisticSchool || this.discoverCreatingList) return;
                this.discoverCreatingList = true;
                try {
                    const result = await this.$wire.call('createCustomListQuick', name, '#ff6338');
                    if (!result || result.success === false || !result.list) return;
                    const list = result.list;
                    const key = this.discoverListKey(list);
                    if (key && !this.discoverLists.some(item => this.discoverListKey(item).toLowerCase() === key.toLowerCase())) {
                        this.discoverLists.push(list);
                    }
                    this.discoverNewDrawerListName = '';
                    if (key && !this.discoverInList(key)) this.toggleDiscoverList(key);
                } finally {
                    this.discoverCreatingList = false;
                }
            },
            closeDiscoverSchool() {
                window.__plyrSchoolDrawerOptimistic = null;
                this.discoverListsOpen = false;
                this.discoverDrawerTab = 'coaches';
                this.optimisticSchool = null;
                // v110: explicit close event is also consumed by any nested Discover
                // controller, so a stale Alpine subtree cannot immediately repaint it.
                window.dispatchEvent(new CustomEvent('rc-discover-drawer-closed'));
            },
            favoriteDiscoverSchool() {
                if (!this.optimisticSchool) return;
                const previous = !!this.optimisticSchool.is_favorite;
                const next = !previous;
                this.optimisticSchool.is_favorite = next;
                window.__plyrSchoolDrawerOptimistic = this.optimisticSchool;
                window.dispatchEvent(new CustomEvent('rc-discover-school-state', { detail: { id: String(this.optimisticSchool.id), is_favorite: next, list_keys: this.optimisticSchool.list_keys || [] } }));
                Promise.resolve(this.$wire.call('queueSchoolFavoriteState', String(this.optimisticSchool.id), next))
                    .then(result => {
                        if (!result || result.success === false) {
                            if (this.optimisticSchool) this.optimisticSchool.is_favorite = previous;
                            window.__plyrSchoolDrawerOptimistic = this.optimisticSchool;
                            if (this.optimisticSchool) window.dispatchEvent(new CustomEvent('rc-discover-school-state', { detail: { id: String(this.optimisticSchool.id), is_favorite: previous, list_keys: this.optimisticSchool.list_keys || [] } }));
                        }
                    })
                    .catch(() => {
                        if (this.optimisticSchool) this.optimisticSchool.is_favorite = previous;
                        window.__plyrSchoolDrawerOptimistic = this.optimisticSchool;
                    });
            },
            toggleDiscoverList(key) {
                if (!this.optimisticSchool || !key) return;
                key = String(key).toLowerCase();
                const previous = Array.isArray(this.optimisticSchool.list_keys) ? [...this.optimisticSchool.list_keys] : [];
                const has = previous.map(v => String(v).toLowerCase()).includes(key);
                const list = this.discoverLists.find(item => this.discoverListKey(item).toLowerCase() === key);
                const previousCount = list ? this.discoverListCount(list) : 0;
                this.optimisticSchool.list_keys = has
                    ? previous.filter(v => String(v).toLowerCase() !== key)
                    : [...previous, key];
                if (list) this.setDiscoverListCount(key, previousCount + (has ? -1 : 1));
                window.__plyrSchoolDrawerOptimistic = this.optimisticSchool;
                window.dispatchEvent(new CustomEvent('rc-discover-school-state', { detail: { id: String(this.optimisticSchool.id), is_favorite: !!this.optimisticSchool.is_favorite, list_keys: this.optimisticSchool.list_keys || [] } }));
                Promise.resolve(this.$wire.call('queueSchoolListMemberships', String(this.optimisticSchool.id), { [key]: !has }))
                    .then(result => {
                        if (!result || result.success === false) {
                            if (this.optimisticSchool) this.optimisticSchool.list_keys = previous;
                            if (list) this.setDiscoverListCount(key, previousCount);
                            window.__plyrSchoolDrawerOptimistic = this.optimisticSchool;
                            if (this.optimisticSchool) window.dispatchEvent(new CustomEvent('rc-discover-school-state', { detail: { id: String(this.optimisticSchool.id), is_favorite: !!this.optimisticSchool.is_favorite, list_keys: previous } }));
                            return;
                        }
                        const serverCount = Number(result?.list_counts?.[key]);
                        if (list && Number.isFinite(serverCount)) this.setDiscoverListCount(key, serverCount);
                    })
                    .catch(() => {
                        if (this.optimisticSchool) this.optimisticSchool.list_keys = previous;
                        if (list) this.setDiscoverListCount(key, previousCount);
                        window.__plyrSchoolDrawerOptimistic = this.optimisticSchool;
                    });
            },
            discoverInList(key) {
                const keys = Array.isArray(this.optimisticSchool?.list_keys) ? this.optimisticSchool.list_keys : [];
                return keys.map(v => String(v).toLowerCase()).includes(String(key || '').toLowerCase());
            }
        }"
        x-on:rc-fast-section.window="$wire.switchRecruitingSection($event.detail?.section || 'dashboard')"
        x-on:rc-free-plan-gate.window="openFreeGate($event.detail?.section || 'dashboard')"
        x-on:rc-free-plan-gate-close.window="closeFreeGate()"
        x-on:keydown.escape.window="if (freeGateOpen) closeFreeGate()"
        x-on:rc-recruiting-account-ready.window="$nextTick(() => $wire.bootDeferredUiData())"
        x-on:rc-fast-inbox-refresh.window="if (($event.detail?.section || '') === 'conversations') { $nextTick(async () => { await $wire.bootDeferredUiData(); await $wire.ensureInboxConversationLoaded(); }) }">
    <style>
        :root {
            --rc-accent: #ff6338;
            --rc-accent-soft: rgba(255, 99, 56, .11);
            --rc-border: rgb(229 231 235);
            --rc-muted: rgb(107 114 128);
            --rc-surface: #ffffff;
            --rc-soft: rgb(249 250 251);
            --rc-text: rgb(17 24 39);
        }

        .dark {
            --rc-border: rgb(63 63 70);
            --rc-muted: rgb(161 161 170);
            --rc-surface: rgb(24 24 27);
            --rc-soft: rgb(39 39 42);
            --rc-text: rgb(244 244 245);
        }

        [x-cloak] { display: none !important; }

        .rc-wrap {
            display: grid;
            gap: 1rem;
            color: var(--rc-text);
        }

        .rc-subtle {
            color: var(--rc-muted);
            font-size: .8125rem;
            line-height: 1.35;
        }

        .rc-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .875rem;
        }

        .rc-title {
            font-size: 1.125rem;
            font-weight: 500;
            letter-spacing: -.02em;
            line-height: 1.2;
        }

        .rc-grid {
            display: grid;
            gap: .875rem;
        }

        .rc-stats {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .rc-card {
            border: 1px solid var(--rc-border);
            background: var(--rc-surface);
            border-radius: .875rem;
            padding: .875rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .rc-card.is-flat {
            box-shadow: none;
        }

        .rc-stat-number {
            font-size: 1.35rem;
            font-weight: 500;
            letter-spacing: -.025em;
            line-height: 1;
        }

        .rc-toolbar {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .rc-input,
        .rc-select,
        .rc-textarea {
            width: auto;
            border: 1px solid var(--rc-border);
            border-radius: .625rem;
            background: var(--rc-surface);
            color: var(--rc-text);
            padding: .5rem .65rem;
            font-size: .8125rem;
            min-height: 2.125rem;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .rc-input:focus,
        .rc-select:focus,
        .rc-textarea:focus {
            border-color: var(--rc-accent);
            box-shadow: 0 0 0 3px var(--rc-accent-soft);
        }

        .rc-textarea {
            width: 100%;
            line-height: 1.45;
        }

        .rc-rich-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .375rem;
            margin-top: .5rem;
        }

        .rc-rich-editor {
            width: 100%;
            min-height: 12rem;
            border: 1px solid var(--rc-border);
            border-radius: .75rem;
            background: var(--rc-surface);
            color: var(--rc-text);
            padding: .75rem;
            font-size: .875rem;
            line-height: 1.55;
            outline: none;
            margin-top: .5rem;
        }

        .rc-rich-editor:focus {
            border-color: var(--rc-accent);
            box-shadow: 0 0 0 3px var(--rc-accent-soft);
        }

        .rc-rich-editor:empty:before {
            content: attr(data-placeholder);
            color: var(--rc-muted);
        }

        .rc-mini-list {
            display: grid;
            gap: .5rem;
            max-height: 22rem;
            overflow: auto;
        }

        .rc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            border: 1px solid var(--rc-border);
            border-radius: .625rem;
            padding: .475rem .7rem;
            min-height: 2.125rem;
            font-size: .7875rem;
            font-weight: 650;
            background: var(--rc-surface);
            color: var(--rc-text);
            transition: background .15s ease, border-color .15s ease, transform .15s ease;
        }

        .rc-btn:hover {
            background: var(--rc-soft);
        }

        .rc-btn:active {
            transform: translateY(1px);
        }

        .rc-btn-primary {
            background: var(--rc-accent);
            border-color: var(--rc-accent);
            color: white;
        }

        .rc-btn-primary:hover {
            background: #f0522b;
            border-color: #f0522b;
        }

        .rc-btn[disabled] {
            opacity: .55;
            cursor: not-allowed;
        }

        .rc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .875rem;
            padding: .7rem 0;
            border-top: 1px solid var(--rc-border);
        }

        .rc-row:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .rc-row:last-child {
            padding-bottom: 0;
        }

        .rc-row-title {
            font-weight: 650;
            font-size: .875rem;
        }



        .rc-coach-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .6rem;
            align-items: center;
            padding: .72rem .78rem;
            border: 1px solid var(--rc-border);
            background: var(--rc-surface);
            border-radius: .78rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .035);
        }

        .rc-card > .rc-coach-row + .rc-coach-row,
        .rc-drawer-panel > .rc-coach-row + .rc-coach-row {
            margin-top: .5rem;
        }

        .rc-coach-main {
            display: grid;
            grid-template-columns: 2.35rem minmax(0, 1fr);
            gap: .6rem;
            align-items: center;
            min-width: 0;
        }

        .rc-coach-avatar {
            width: 2.35rem;
            height: 2.35rem;
            border-radius: .75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--rc-accent-soft);
            color: var(--rc-accent);
            font-weight: 600;
            font-size: .9rem;
        }

        .rc-coach-copy {
            min-width: 0;
            display: grid;
            gap: .28rem;
        }

        .rc-coach-heading {
            display: flex;
            align-items: center;
            gap: .5rem;
            min-width: 0;
        }

        .rc-coach-heading h3 {
            margin: 0;
            font-size: .93rem;
            line-height: 1.25;
            font-weight: 750;
            letter-spacing: -.015em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rc-coach-badges {
            display: inline-flex;
            gap: .3rem;
            flex-wrap: wrap;
            flex: 0 0 auto;
        }

        .rc-coach-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem .6rem;
            color: var(--rc-muted);
            font-size: .78rem;
            line-height: 1.35;
        }

        .rc-coach-meta span {
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rc-coach-meta span:not(:last-child)::after {
            content: "";
            display: inline-block;
            width: .22rem;
            height: .22rem;
            margin-left: .6rem;
            border-radius: 999px;
            background: currentColor;
            opacity: .45;
            vertical-align: middle;
        }

        .rc-coach-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: .4rem;
            flex-wrap: nowrap;
            max-width: 14rem;
        }

        .rc-coach-list-actions {
            display: none;
        }

        .rc-action-menu {
            position: relative;
            display: inline-flex;
            flex: 0 0 auto;
        }

        .rc-action-trigger {
            font-weight: 650;
            letter-spacing: .08em;
        }


        .rc-school-list-picker {
            position: relative;
            display: inline-flex;
            flex: 0 0 auto;
        }

        .rc-school-list-trigger {
            min-width: 4.25rem;
            padding-inline: .55rem;
            gap: .3rem;
        }

        .rc-school-list-menu {
            position: absolute;
            z-index: 45;
            right: 0;
            bottom: calc(100% + .35rem);
            width: max-content;
            min-width: 8.5rem;
            max-width: 12rem;
            max-height: 10.5rem;
            overflow: auto;
            border: 1px solid var(--rc-border);
            border-radius: .65rem;
            background: var(--rc-surface);
            box-shadow: 0 12px 28px rgba(15, 23, 42, .16);
            padding: .25rem;
        }

        .rc-school-list-option {
            width: 100%;
            border: 0;
            background: transparent;
            color: var(--rc-text);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            text-align: left;
            padding: .38rem .48rem;
            border-radius: .45rem;
            font-size: .72rem;
            font-weight: 500;
            line-height: 1.2;
            cursor: pointer;
        }

        .rc-school-list-option:hover,
        .rc-school-list-option.is-active {
            background: var(--rc-accent-soft);
            color: var(--rc-accent);
        }

        .rc-school-list-check {
            font-size: .72rem;
            font-weight: 650;
        }

        .rc-school-list-empty {
            color: var(--rc-muted);
            font-size: .72rem;
            padding: .38rem .48rem;
        }

        .rc-menu-panel {
            position: absolute;
            z-index: 40;
            top: calc(100% + .45rem);
            right: 0;
            width: min(17rem, 80vw);
            max-height: 22rem;
            overflow: auto;
            border: 1px solid var(--rc-border);
            border-radius: .85rem;
            background: var(--rc-surface);
            box-shadow: 0 18px 45px rgba(15, 23, 42, .18);
            padding: .35rem;
        }

        .rc-menu-item {
            width: 100%;
            border: 0;
            background: transparent;
            color: var(--rc-text);
            padding: .58rem .65rem;
            border-radius: .58rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .65rem;
            text-align: left;
            font-size: .78rem;
            font-weight: 650;
            cursor: pointer;
        }

        .rc-menu-item:hover {
            background: var(--rc-soft);
        }

        .rc-menu-label {
            padding: .65rem .65rem .32rem;
            color: var(--rc-muted);
            font-size: .68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .rc-btn-compact {
            min-height: 1.95rem;
            padding: .36rem .55rem;
            font-size: .72rem;
        }


        .rc-pill {
            display: inline-flex;
            align-items: center;
            max-width: max-content;
            border: 1px solid transparent;
            border-radius: 999px;
            background: var(--rc-soft);
            padding: .18rem .5rem;
            font-size: .6875rem;
            font-weight: 650;
            color: var(--rc-muted);
            line-height: 1.2;
        }

        .rc-pill-accent {
            background: var(--rc-accent-soft);
            color: var(--rc-accent);
        }

        .rc-progress {
            height: .38rem;
            background: var(--rc-soft);
            border-radius: 999px;
            overflow: hidden;
        }

        .rc-progress span {
            display: block;
            height: 100%;
            background: var(--rc-accent);
            transition: width .35s ease;
        }

        .rc-pulse {
            animation: rcFade .35s ease-in;
        }

        @keyframes rcFade {
            from { opacity: .2; transform: translateY(4px); }
            to { opacity: 1; transform: none; }
        }

        .rc-chat {
            display: grid;
            grid-template-columns: minmax(250px, 340px) minmax(0, 1fr);
            gap: .875rem;
            align-items: start;
        }

        .rc-thread {
            max-height: 620px;
            overflow: auto;
            padding: .25rem;
        }

        .rc-message {
            max-width: 82%;
            border: 1px solid var(--rc-border);
            border-radius: .875rem;
            padding: .65rem .75rem;
            margin: .45rem 0;
            background: var(--rc-soft);
            font-size: .8375rem;
            line-height: 1.45;
        }

        .rc-message.out {
            margin-left: auto;
            background: var(--rc-accent-soft);
            border-color: rgba(255, 99, 56, .25);
        }

        .rc-thread-button {
            width: 100%;
            text-align: left;
            background: transparent;
            border-left: 0;
            border-right: 0;
            border-bottom: 0;
            border-radius: 0;
        }

        .rc-thread-button:hover {
            background: var(--rc-soft);
            margin-left: -.5rem;
            margin-right: -.5rem;
            padding-left: .5rem;
            padding-right: .5rem;
            width: calc(100% + 1rem);
        }

        .rc-drawer {
            position: fixed;
            inset: 0;
            z-index: 50;
            background: rgba(15, 23, 42, .28);
            display: flex;
            justify-content: flex-end;
            backdrop-filter: blur(2px);
        }

        .rc-drawer-panel {
            width: min(560px, 100%);
            height: 100%;
            background: var(--rc-surface);
            padding: .82rem;
            overflow: auto;
            box-shadow: -20px 0 40px rgba(15, 23, 42, .16);
        }

        .rc-empty {
            border: 1px dashed var(--rc-border);
            border-radius: .875rem;
            padding: .82rem;
            color: var(--rc-muted);
            font-size: .875rem;
            display: grid;
            gap: .2rem;
        }

        .rc-school-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(15.5rem, 1fr));
            gap: .65rem;
        }

        .rc-school-view-toggle {
            display:inline-flex;
            gap:.25rem;
            padding:.22rem;
            border:1px solid var(--rc-border);
            border-radius:.75rem;
            background:var(--rc-surface);
        }

        .rc-school-view-toggle .rc-btn {
            min-height:1.9rem;
            padding:.32rem .5rem;
            border-radius:.55rem;
            border-color:transparent;
        }

        .rc-school-view-toggle .rc-btn.is-active {
            border-color:rgba(255,99,56,.28);
            background:var(--rc-accent-soft);
            color:var(--rc-accent);
        }

        .rc-school-list-table {
            display:grid;
            gap:.4rem;
        }

        .rc-school-list-head,
        .rc-school-list-row {
            display:grid;
            grid-template-columns:minmax(13rem,2fr) 5.5rem minmax(7rem,1fr) minmax(7rem,1fr) 4rem 4rem 9rem;
            gap:.65rem;
            align-items:center;
        }

        .rc-school-list-head {
            color:var(--rc-muted);
            font-size:.68rem;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.05em;
            padding:.2rem .75rem;
        }

        .rc-school-list-row {
            border:1px solid var(--rc-border);
            border-radius:.78rem;
            background:var(--rc-surface);
            padding:.58rem .75rem;
            box-shadow:0 1px 2px rgba(15,23,42,.035);
        }

        .rc-school-list-row:hover {
            border-color:rgba(255,99,56,.35);
            background:var(--rc-soft);
        }

        .rc-school-list-name {
            border:0;
            background:transparent;
            color:var(--rc-text);
            text-align:left;
            cursor:pointer;
            display:flex;
            align-items:center;
            gap:.55rem;
            min-width:0;
            font-weight:750;
            font-size:.82rem;
        }

        .rc-school-list-logo-box,
        .rc-school-card-logo-box,
        .rc-coach-school-logo-wrap {
            width:2rem;
            height:2rem;
            border-radius:.55rem;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
            background:#ffffff;
            color:#111827;
            border:1px solid var(--rc-border);
            flex:0 0 auto;
            font-size:.72rem;
            font-weight:900;
            letter-spacing:.02em;
        }

        .rc-school-card-logo-box {
            width:2.45rem;
            height:2.45rem;
            border-radius:.7rem;
        }

        .rc-school-list-logo,
        .rc-school-card-logo,
        .rc-coach-school-logo {
            width:100%;
            height:100%;
            object-fit:contain;
            object-position:center;
            display:block;
            background:#fff;
        }

        .rc-school-logo-placeholder,
        .rc-logo-initials {
            color:#111827;
            background:#ffffff;
            font-size:.72rem;
            font-weight:900;
            letter-spacing:.02em;
        }

        .rc-logo-fallback-text {
            display:none;
        }

        .is-missing-logo .rc-logo-fallback-text {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:100%;
            height:100%;
        }

        .is-missing-logo img {
            display:none !important;
        }

        .rc-school-card-title {
            min-width:0;
            display:flex;
            align-items:center;
            gap:.58rem;
            border:0;
            background:transparent;
            color:var(--rc-text);
            padding:0;
            text-align:left;
            cursor:pointer;
            font-weight:750;
        }

        .rc-school-card-title span:last-child {
            display:-webkit-box;
            -webkit-line-clamp:2;
            -webkit-box-orient:vertical;
            overflow:hidden;
        }

        .rc-school-list-actions {
            display:flex;
            justify-content:flex-end;
            align-items:center;
            gap:.35rem;
        }

        .rc-school-list-picker { position:relative; display:inline-flex; }
        .rc-school-list-trigger { min-height:1.95rem; padding:.35rem .55rem; font-size:.72rem; white-space:nowrap; }
        .rc-school-list-menu { position:absolute; z-index:60; right:0; bottom:calc(100% + .35rem); width:10.5rem; max-height:12rem; overflow:auto; padding:.25rem; border:1px solid var(--rc-border); border-radius:.7rem; background:var(--rc-surface); box-shadow:0 16px 35px rgba(15,23,42,.16); }
        .rc-school-list-option { width:100%; border:0; border-radius:.5rem; background:transparent; color:var(--rc-text); display:flex; align-items:center; justify-content:space-between; gap:.45rem; padding:.42rem .5rem; font-size:.72rem; font-weight:700; text-align:left; }
        .rc-school-list-option:hover, .rc-school-list-option.is-active { background:var(--rc-accent-soft); color:var(--rc-accent); }
        .rc-school-list-empty { padding:.45rem .5rem; color:var(--rc-muted); font-size:.72rem; }
        .rc-school-list-check { font-size:.72rem; font-weight:900; }

        @media (max-width: 980px) {
            .rc-school-list-head { display:none; }
            .rc-school-list-row { grid-template-columns:1fr auto; gap:.45rem; }
            .rc-school-list-row > :nth-child(n+2):nth-child(-n+6) { display:none; }
        }


        .rc-school-card {
            border: 1px solid var(--rc-border);
            background: var(--rc-surface);
            border-radius: .78rem;
            padding: .75rem;
            min-height: 8.65rem;
            display: flex;
            flex-direction: column;
            gap: .5rem;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .rc-school-card:hover {
            border-color: rgba(255, 99, 56, .55);
            box-shadow: 0 8px 20px rgba(15, 23, 42, .08);
            transform: translateY(-1px);
        }

        .rc-school-topline,
        .rc-school-actions,
        .rc-school-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .rc-school-card h3 {
            margin: 0;
            font-size: .88rem;
            line-height: 1.2;
            font-weight: 750;
            letter-spacing: -.015em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rc-school-conference {
            margin: -.2rem 0 0;
            color: var(--rc-muted);
            font-size: .73rem;
            line-height: 1.25;
            min-height: 1.8rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rc-school-meta {
            margin-top: auto;
            color: var(--rc-muted);
            font-size: .72rem;
            line-height: 1.25;
        }

        .rc-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: var(--rc-accent-soft);
            color: var(--rc-accent);
            padding: .16rem .46rem;
            font-size: .68rem;
            font-weight: 500;
            line-height: 1.2;
        }

        .rc-icon-button {
            width: 1.85rem;
            height: 1.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--rc-border);
            border-radius: .55rem;
            color: var(--rc-muted);
            background: var(--rc-surface);
            font-size: .9rem;
        }

        .rc-icon-button.is-active {
            color: var(--rc-accent);
            border-color: rgba(255, 99, 56, .35);
            background: var(--rc-accent-soft);
        }

        .rc-spinner-mini {
            width: .8rem;
            height: .8rem;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 999px;
            animation: rcSpin .65s linear infinite;
        }

        @keyframes rcSpin {
            to { transform: rotate(360deg); }
        }

        .rc-section-title {
            font-size: .82rem;
            font-weight: 500;
            color: var(--rc-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: .65rem;
        }

        .rc-list-button {
            width: 100%;
            text-align: left;
            justify-content: space-between;
        }

        .rc-favorites-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: .6rem;
            align-items: start;
        }

        .rc-favorites-panel .rc-school-grid {
            grid-template-columns: 1fr;
        }

        .rc-favorites-panel .rc-school-card {
            min-height: 8rem;
            gap: .45rem;
        }

        .rc-school-flags {
            min-height: 1.35rem;
            gap: .3rem;
        }

        .rc-favorites-panel .rc-school-actions {
            justify-content: flex-start;
        }

        .rc-favorites-panel .rc-school-actions .rc-btn-primary {
            min-width: 7.5rem;
        }

        .rc-coach-panel {
            min-height: 5.25rem;
        }


        .rc-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }

        .rc-format-btn {
            min-width: 2.15rem;
            padding-inline: .55rem;
        }

        .rc-rich-toolbar {
            gap: .35rem;
            flex-wrap: wrap;
        }

        .rc-rich-editor {
            min-height: 11rem;
            line-height: 1.55;
        }

        .rc-rich-editor p {
            margin: 0 0 .75rem;
        }

        .rc-rich-editor ul,
        .rc-rich-editor ol {
            margin: .5rem 0 .75rem 1.25rem;
            padding: 0;
        }

        .rc-school-grid.is-compact {
            display: grid;
            grid-template-columns: 1fr;
            gap: .5rem;
        }

        .rc-school-grid.is-compact .rc-school-card {
            min-height: 0;
            padding: .7rem;
            gap: .35rem;
            border-radius: .75rem;
        }

        .rc-school-grid.is-compact .rc-school-topline {
            margin-bottom: .1rem;
        }

        .rc-school-grid.is-compact .rc-school-card h3 {
            font-size: .88rem;
            line-height: 1.2;
            margin: 0;
        }

        .rc-school-grid.is-compact .rc-school-conference {
            font-size: .72rem;
            -webkit-line-clamp: 1;
            margin: 0;
        }

        .rc-school-grid.is-compact .rc-school-meta {
            font-size: .7rem;
            margin-top: .15rem;
        }

        .rc-school-grid.is-compact .rc-school-flags {
            min-height: 0;
            margin-top: .15rem;
        }

        .rc-school-grid.is-compact .rc-school-actions {
            margin-top: .2rem;
        }

        .rc-school-grid.is-compact .rc-btn-primary {
            min-height: 2.1rem;
            padding: .45rem .65rem;
        }

        .rc-list-button {
            min-height: 2.35rem;
            padding: .45rem .65rem;
            font-size: .78rem;
        }


        .rc-campaign-shell {
            display: grid;
            grid-template-columns: minmax(240px, 320px) minmax(520px, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .rc-campaign-panel {
            position: relative;
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: 1.35rem;
            background: linear-gradient(180deg, rgba(255,255,255,.055), rgba(255,255,255,.026));
            box-shadow: 0 18px 48px rgba(0,0,0,.22);
            overflow: hidden;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .rc-campaign-panel:hover {
            border-color: rgba(255, 91, 50, .22);
        }

        .rc-campaign-panel-header {
            padding: 1.05rem 1rem .85rem;
            border-bottom: 1px solid rgba(148, 163, 184, .12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
        }

        .rc-template-list,
        .rc-picker-list {
            display: grid;
            gap: .55rem;
            max-height: 38rem;
            overflow: auto;
            padding: .85rem;
            scroll-behavior: smooth;
        }

        .rc-template-item,
        .rc-picker-row {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            border: 1px solid rgba(148, 163, 184, .14);
            border-radius: .85rem;
            padding: .78rem;
            background: rgba(255,255,255,.032);
            transition: border-color .18s ease, background .18s ease, transform .18s ease, box-shadow .18s ease;
        }

        .rc-template-item:hover,
        .rc-picker-row:hover {
            border-color: rgba(255, 91, 50, .42);
            background: rgba(255, 91, 50, .055);
            transform: translateY(-1px);
            box-shadow: 0 12px 30px rgba(0,0,0,.18);
        }

        .rc-template-item.is-selected {
            border-color: rgba(255, 91, 50, .9);
            background: linear-gradient(135deg, rgba(255, 91, 50, .95), rgba(255, 91, 50, .72));
            box-shadow: 0 16px 34px rgba(255, 91, 50, .16), 0 10px 28px rgba(0,0,0,.18);
        }
        .rc-template-item.is-selected .rc-template-main strong,
        .rc-template-item.is-selected .rc-template-main span,
        .rc-template-item.is-selected .rc-template-icon { color:#fff; }
        .rc-template-item.is-selected .rc-template-icon { background:rgba(255,255,255,.16); }

        .rc-template-icon {
            width: 2.15rem;
            height: 2.15rem;
            border-radius: .75rem;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            background: rgba(255, 91, 50, .13);
            color: var(--rc-accent);
            font-weight: 600;
        }

        .rc-template-main {
            min-width: 0;
            flex: 1;
            display: grid;
            gap: .15rem;
            text-align: left;
        }

        .rc-template-main strong,
        .rc-picker-row strong {
            color: var(--rc-text);
            font-size: .88rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-template-main span,
        .rc-picker-row small {
            color: var(--rc-muted);
            font-size: .75rem;
            line-height: 1.35;
        }

        .rc-picker-row {
            justify-content: flex-start;
            cursor: pointer;
        }

        .rc-picker-row input {
            accent-color: var(--rc-accent);
        }

        .rc-campaign-fields {
            display: grid;
            gap: .6rem;
            padding: .82rem;
        }


        .rc-campaign-compose {
            display: grid;
            gap: .82rem;
            padding: .82rem;
        }
        .rc-template-field-label {
            display:block;
            margin-bottom:.4rem;
            color:rgba(203,213,225,.78);
            font-size:.7rem;
            text-transform:uppercase;
            letter-spacing:.06em;
            font-weight:800;
        }
        .rc-template-graphic-card {
            padding:.85rem;
            border:1px solid rgba(148,163,184,.16);
            border-radius:1rem;
            background:rgba(255,255,255,.024);
        }

        .rc-rich-editor-shell {
            border: 1px solid rgba(148,163,184,.2);
            border-radius: 1.1rem;
            overflow: hidden;
            background: rgba(2,6,23,.32);
        }

        .rc-rich-editor-toolbar {
            display:flex;
            flex-wrap:wrap;
            gap:.38rem;
            padding:.65rem;
            border-bottom:1px solid rgba(148,163,184,.14);
            background:rgba(255,255,255,.025);
        }

        .rc-rich-tool {
            min-width:2.15rem;
            height:2.15rem;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:.35rem;
            border:1px solid rgba(148,163,184,.18);
            border-radius:.65rem;
            background:rgba(255,255,255,.035);
            color:#e5e7eb;
            font-size:.78rem;
            font-weight:800;
            transition:.15s ease;
        }

        .rc-rich-tool:hover {
            border-color:rgba(255,91,50,.42);
            color:#fff;
            background:rgba(255,91,50,.11);
        }

        .rc-rich-editor {
            min-height:34rem;
            padding:1.1rem;
            color:var(--rc-text);
            background:rgba(2,6,23,.18);
            font-family:Arial,Helvetica,sans-serif;
            font-size:.98rem;
            line-height:1.7;
            outline:none;
        }

        .rc-rich-editor:empty:before {
            content: attr(data-placeholder);
            color:rgba(148,163,184,.72);
        }

        .rc-rich-editor img {
            width:100%;
            max-width:100%;
            height:auto;
            border-radius:.75rem;
            margin:.7rem 0;
        }

        .rc-rich-editor a.rc-email-button {
            display:inline-block;
            margin:.75rem 0;
            padding:.7rem 1rem;
            border-radius:.75rem;
            background:#ff5b32;
            color:#fff !important;
            font-weight:800;
            text-decoration:none;
        }


        .rc-quill-editor {
            min-height: 34rem;
            background: #ffffff;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
        }

        .rc-rich-editor-shell .ql-toolbar.ql-snow {
            border: 0;
            border-bottom: 1px solid rgba(148,163,184,.18);
            background: rgba(255,255,255,.96);
            padding: .65rem .75rem;
            border-radius: 1.1rem 1.1rem 0 0;
        }

        .rc-rich-editor-shell .ql-container.ql-snow {
            border: 0;
            min-height: 34rem;
            background: #ffffff;
            border-radius: 0 0 1.1rem 1.1rem;
            font-size: 1rem;
        }

        .rc-rich-editor-shell .ql-editor {
            min-height: 34rem;
            padding: 1.35rem;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.7;
        }

        .rc-rich-editor-shell .ql-editor.ql-blank::before {
            color: #94a3b8;
            font-style: normal;
            left: 1.35rem;
            right: 1.35rem;
        }

        .rc-rich-editor-shell .ql-editor img {
            width: 100%;
            max-width: 100%;
            border-radius: .75rem;
            margin: .65rem 0;
        }

        .rc-rich-editor-shell .ql-editor .rc-email-button {
            display: inline-block;
            margin: .75rem 0;
            padding: .7rem 1rem;
            border-radius: .75rem;
            background: #ff5b32;
            color: #fff !important;
            font-weight: 600;
            text-decoration: none;
        }

        .rc-preview-modal-backdrop {
            position:fixed;
            inset:0;
            z-index:70;
            background:rgba(0,0,0,.72);
            backdrop-filter:blur(9px);
            display:grid;
            place-items:center;
            padding:1.5rem;
        }

        .rc-preview-modal {
            width:min(820px,96vw);
            max-height:88vh;
            overflow:auto;
            border-radius:1.35rem;
            border:1px solid rgba(148,163,184,.22);
            background:#fff;
            color:#111827;
            box-shadow:0 30px 90px rgba(0,0,0,.45);
        }

        .rc-preview-modal-head {
            position:sticky;
            top:0;
            z-index:2;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:1rem;
            padding:1rem 1.15rem;
            border-bottom:1px solid #e5e7eb;
            background:#fff;
        }

        .rc-preview-modal-body {
            padding:1.35rem;
            font-family:Arial,Helvetica,sans-serif;
            line-height:1.7;
        }


        /* Compose Email preview: emulate a real email canvas instead of inheriting
           Filament's global/reset spacing rules. */
        .rc-compose-preview-shell-v46 {
            width:min(62rem,94vw);
            max-height:88vh;
            overflow:auto;
            border:1px solid var(--rc-border);
            border-radius:1.15rem;
            background:#f3f4f6;
            box-shadow:0 28px 90px rgba(0,0,0,.34);
        }
        .rc-compose-preview-head-v46 {
            position:sticky;
            top:0;
            z-index:3;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:1rem;
            padding:1rem 1.15rem;
            border-bottom:1px solid #e5e7eb;
            background:rgba(255,255,255,.98);
            color:#111827;
        }
        .rc-compose-preview-subject-v46 {
            margin-top:.22rem;
            color:#64748b;
            font-size:.8rem;
            line-height:1.35;
        }
        .rc-compose-preview-stage-v46 {
            padding:1.4rem;
            background:#f3f4f6;
        }
        .rc-compose-preview-email-v46 {
            width:min(100%,46rem);
            margin:0 auto;
            box-sizing:border-box;
            border:1px solid #e5e7eb;
            border-radius:1rem;
            background:#fff;
            color:#111827;
            padding:2rem 2.15rem;
            font-family:Arial,Helvetica,sans-serif;
            font-size:16px;
            line-height:1.65;
            box-shadow:0 12px 36px rgba(15,23,42,.07);
            overflow-wrap:anywhere;
        }
        .rc-compose-preview-email-v46 p { margin:0 0 1rem !important; }
        .rc-compose-preview-email-v46 p:last-child { margin-bottom:0 !important; }
        .rc-compose-preview-email-v46 h1,
        .rc-compose-preview-email-v46 h2,
        .rc-compose-preview-email-v46 h3,
        .rc-compose-preview-email-v46 h4 {
            margin:1.35rem 0 .75rem !important;
            line-height:1.25;
        }
        .rc-compose-preview-email-v46 ul,
        .rc-compose-preview-email-v46 ol {
            margin:.45rem 0 1rem 1.3rem !important;
            padding:0 !important;
        }
        .rc-compose-preview-email-v46 li { margin:.3rem 0 !important; }
        .rc-compose-preview-email-v46 img { max-width:100%; height:auto; }
        .rc-compose-preview-email-v46 table { max-width:100%; }
        .rc-compose-preview-email-v46 a { text-underline-offset:2px; }
        .rc-compose-preview-email-v46 .plyrcard-email-signature {
            margin-top:1.75rem !important;
            padding-top:1.4rem;
            border-top:1px solid #e5e7eb;
        }
        @media (max-width:640px) {
            .rc-compose-preview-stage-v46 { padding:.75rem; }
            .rc-compose-preview-email-v46 { padding:1.25rem; border-radius:.8rem; }
        }

        .rc-campaign-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
            align-items: center;
        }

        .rc-token-chip {
            border: 1px solid rgba(255, 91, 50, .24);
            background: rgba(255, 91, 50, .08);
            color: #ffb19d;
            border-radius: 999px;
            padding: .35rem .55rem;
            font-size: .72rem;
            font-weight: 600;
        }

        .rc-campaign-editor {
            width: 100%;
            min-height: 17rem;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .82rem;
            background: rgba(2, 6, 23, .32);
            color: var(--rc-text);
            padding: .9rem;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: .88rem;
            line-height: 1.55;
            resize: vertical;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .rc-campaign-editor:focus {
            outline: none;
            border-color: rgba(255, 91, 50, .62);
            box-shadow: 0 0 0 3px rgba(255, 91, 50, .12);
        }

        .rc-mini-preview-card {
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: .85rem;
            background: rgba(255,255,255,.026);
            padding: .75rem;
        }

        .rc-campaign-loading {
            border: 1px dashed rgba(148, 163, 184, .28);
            border-radius: .9rem;
            padding: .82rem;
            color: var(--rc-muted);
            display: flex;
            gap: .55rem;
            align-items: center;
            justify-content: center;
        }

        .rc-campaign-preview-wrap {
            padding: .82rem;
            display: grid;
            gap: .6rem;
        }

        .rc-email-preview {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: .85rem;
            overflow: hidden;
            background: #fff;
            color: #111827;
            box-shadow: 0 18px 45px rgba(0,0,0,.18);
        }

        .rc-email-subject {
            padding: .8rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #111827;
            background: #f9fafb;
            display: grid;
            gap: .2rem;
        }

        .rc-email-subject small {
            color: #6b7280;
            font-weight: 500;
        }

        .rc-preview-frame {
            width: 100%;
            min-height: 30rem;
            border: 0;
            display: block;
            background: #fff;
        }

        .rc-template-saving-overlay,
        .rc-template-loading-overlay {
            position: absolute;
            inset: 0;
            z-index: 5;
            display: grid;
            place-items: center;
            background: rgba(3, 7, 18, .28);
            backdrop-filter: blur(1.5px);
            color: var(--rc-text);
            font-weight: 600;
        }

        .rc-template-loading-card {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            border: 1px solid rgba(255, 91, 50, .22);
            border-radius: 999px;
            padding: .48rem .72rem;
            background: rgba(15, 23, 42, .9);
            box-shadow: 0 12px 32px rgba(0,0,0,.24);
            font-size:.82rem;
        }
        .rc-preview-updating {
            position:absolute;
            top:.85rem;
            right:.85rem;
            z-index:4;
            display:inline-flex;
            align-items:center;
            gap:.45rem;
            border:1px solid rgba(148,163,184,.22);
            background:rgba(255,255,255,.92);
            color:#111827;
            border-radius:999px;
            padding:.42rem .65rem;
            font-size:.78rem;
            font-weight:800;
            box-shadow:0 12px 28px rgba(15,23,42,.16);
        }

        .rc-skeleton {
            position: relative;
            overflow: hidden;
            border-radius: .75rem;
            background: rgba(148, 163, 184, .11);
        }

        .rc-skeleton::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.14), transparent);
            animation: rc-shimmer 1.15s infinite;
        }

        @keyframes rc-shimmer { 100% { transform: translateX(100%); } }

        .rc-preview-card-soft {
            background: #f8fafc;
            color: #111827;
            border-radius: 1.25rem;
            overflow: hidden;
            border: 1px solid rgba(148,163,184,.18);
            box-shadow: 0 18px 50px rgba(0,0,0,.20);
        }

        .rc-preview-content-font {
            font-family: Arial, Helvetica, sans-serif;
            letter-spacing: normal;
        }

        .rc-email-body-fallback {
            padding: .82rem;
            min-height: 18rem;
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        .rc-email-body-fallback img {
            width: 100%;
            max-width: 100%;
            height: auto;
        }

        .rc-target-card {
            display: grid;
            gap: .6rem;
            padding: .82rem;
        }

        .rc-recipient-stat {
            border: 1px solid rgba(255, 91, 50, .28);
            border-radius: .9rem;
            padding: .85rem;
            background: rgba(255, 91, 50, .08);
        }

        .rc-recipient-stat strong {
            color: var(--rc-text);
            font-size: 1.6rem;
            display: block;
            line-height: 1;
        }

        .rc-template-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            width: fit-content;
            border-radius: 999px;
            padding: .35rem .55rem;
            font-size: .72rem;
            color: #fed7aa;
            background: rgba(255, 91, 50, .14);
            border: 1px solid rgba(255, 91, 50, .24);
        }

        .rc-empty.is-small {
            min-height: 0;
            padding: .75rem;
            align-items: flex-start;
            text-align: left;
        }


    
        .rc-visual-editor-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            align-items: center;
            padding: .65rem;
            border: 1px solid rgba(255,255,255,.14);
            border-bottom: 0;
            border-radius: 1rem 1rem 0 0;
            background: rgba(15, 23, 42, .8);
        }

        .rc-visual-tool {
            height: 2rem;
            min-width: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .6rem;
            background: rgba(255,255,255,.055);
            color: var(--rc-text);
            font-size: .78rem;
            font-weight: 650;
        }

        .rc-visual-tool:hover { border-color: rgba(255,91,50,.55); color:#fed7aa; }

        .rc-template-live-editor-wrap{
            border:1px solid rgba(255,255,255,.14);
            border-radius:0 0 1rem 1rem;
            overflow:hidden;
            background:#fff;
            min-height:46rem;
        }
        .rc-template-live-editor{display:block;width:100%;height:52rem;border:0;background:#fff}
    .rc-design-editor {
            display:grid;
            gap:.75rem;
            max-height:28rem;
            overflow:auto;
            padding:.85rem;
            border:1px solid rgba(148,163,184,.22);
            border-radius:1rem;
            background:rgba(15,23,42,.38);
        }
        .rc-design-edit-row {
            display:grid;
            gap:.4rem;
        }
        .rc-design-edit-row label {
            color:#cbd5e1;
            font-size:.78rem;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.04em;
        }
        .rc-design-textarea {
            min-height:4.75rem;
            resize:vertical;
            line-height:1.45;
        }
        .rc-image-mini-preview {
            width:5rem;
            height:5rem;
            border-radius:.75rem;
            overflow:hidden;
            border:1px solid rgba(148,163,184,.22);
            background:#fff;
        }
        .rc-image-mini-preview img {
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
        }

        @media (max-width: 1180px) {
            .rc-school-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .rc-campaign-shell { grid-template-columns: minmax(240px, 320px) minmax(0, 1fr); }
            .rc-campaign-shell > .rc-campaign-panel:last-child { grid-column: 1 / -1; }
        }

        @media (max-width: 900px) {
            .rc-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .rc-chat { grid-template-columns: 1fr; }
            .rc-top { display: grid; }
            .rc-school-grid { grid-template-columns: 1fr; }
            .rc-favorites-layout { grid-template-columns: 1fr; }
            .rc-input, .rc-select { width: 100%; }
            .rc-coach-row { grid-template-columns: 1fr; align-items: stretch; }
            .rc-coach-actions { justify-content: flex-start; max-width: none; }
            .rc-menu-panel { right: auto; left: 0; }
            .rc-campaign-shell { grid-template-columns: 1fr; }
            .rc-campaign-shell > .rc-campaign-panel:last-child { grid-column: auto; }
            .rc-preview-frame { min-height: 24rem; }
        }

        @media (max-width: 640px) {
            .rc-coach-main { grid-template-columns: 2rem minmax(0, 1fr); }
            .rc-coach-avatar { width: 2rem; height: 2rem; border-radius: .65rem; }
            .rc-coach-heading { display: grid; gap: .35rem; }
            .rc-coach-heading h3 { white-space: normal; }
            .rc-coach-actions .rc-btn-primary { flex: 1 1 auto; }
        }


        /* v58 compact controls + inbox thread polish */
        .rc-page-heading { display:grid; gap:.35rem; margin: .25rem 0 1.25rem; }
        .rc-page-heading h1 { margin:0; font-size: clamp(1.55rem, 3vw, 2.15rem); line-height:1.05; font-weight:850; letter-spacing:-.04em; color:var(--rc-text); }
        .rc-search-hero { display:flex; align-items:center; gap:.7rem; border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1rem; padding:.58rem .7rem; box-shadow: 0 10px 26px rgba(0,0,0,.10); }
        .rc-search-hero svg { width:1.2rem; height:1.2rem; color:var(--rc-muted); flex:0 0 auto; }
        .rc-search-hero input { flex:1; border:0 !important; background:transparent !important; box-shadow:none !important; min-height:2.35rem; font-size:.95rem; }
        .rc-school-filter-box { display:grid; grid-template-columns: minmax(0,1fr) minmax(220px,.75fr) minmax(180px,.5fr); gap:1rem; align-items:end; margin-top:.85rem; padding:1rem; border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1rem; }
        .rc-filter-label { display:block; font-size:.72rem; letter-spacing:.09em; text-transform:uppercase; color:#9fb0c5; font-weight:750; margin-bottom:.55rem; }
        .rc-chip-row { display:flex; flex-wrap:wrap; gap:.45rem; }
        .rc-filter-chip { border:1px solid var(--rc-border); background:var(--rc-soft); color:#cbd5e1; border-radius:999px; padding:.52rem .78rem; font-size:.78rem; font-weight:700; transition:.15s ease; }
        .rc-filter-chip:hover, .rc-filter-chip.is-active { border-color:var(--rc-accent); color:#fff; background:var(--rc-accent-soft); }
        .rc-compose-compact-grid { display:grid; grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr); gap:1rem; }
        .rc-compose-summary { display:grid; gap:.65rem; }
        .rc-recipient-tabs { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.5rem; }
        .rc-recipient-tabs .rc-btn { padding:.62rem .55rem; font-size:.75rem; }
        .rc-compact-panel { border:1px solid var(--rc-border); background:rgba(24,24,27,.65); border-radius:.9rem; padding:.8rem; }
        .rc-details { border:1px solid var(--rc-border); border-radius:.85rem; background:var(--rc-surface); overflow:hidden; }
        .rc-details summary { list-style:none; cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.78rem .9rem; font-weight:800; }
        .rc-details summary::-webkit-details-marker { display:none; }
        .rc-details-body { border-top:1px solid var(--rc-border); padding:.8rem; }
        .rc-choice-list { display:grid; gap:.42rem; max-height:15rem; overflow:auto; padding-right:.2rem; }
        .rc-choice-row { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:.65rem; border:1px solid transparent; background:rgba(255,255,255,.025); border-radius:.75rem; padding:.6rem .7rem; text-align:left; transition:.15s ease; }
        .rc-choice-row:hover, .rc-choice-row.is-selected { border-color:rgba(255,99,56,.55); background:rgba(255,99,56,.10); }
        .rc-choice-title { font-weight:800; font-size:.86rem; color:var(--rc-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rc-choice-sub { color:var(--rc-muted); font-size:.74rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rc-icon-sm { width:1rem; height:1rem; }
        .rc-loading-inline { display:inline-flex; align-items:center; gap:.35rem; color:var(--rc-muted); font-size:.75rem; }
        .rc-inbox-layout { display:grid; grid-template-columns:minmax(320px,.42fr) minmax(0,1fr); gap:1rem; min-height:36rem; }
        .rc-inbox-list { display:grid; gap:.5rem; max-height:38rem; overflow:auto; padding-right:.25rem; }
        .rc-thread-card { width:100%; text-align:left; border:1px solid transparent; background:rgba(255,255,255,.025); border-radius:.9rem; padding:.72rem; display:grid; grid-template-columns:2.3rem minmax(0,1fr) auto; gap:.7rem; transition:.16s ease; }
        .rc-thread-card:hover, .rc-thread-card.is-selected { border-color:rgba(255,99,56,.55); background:rgba(255,99,56,.12); }
        .rc-avatar-mini { display:flex; align-items:center; justify-content:center; width:2.25rem; height:2.25rem; border-radius:.75rem; background:var(--rc-accent); color:white; font-weight:900; font-size:.8rem; }
        .rc-thread-subject { color:var(--rc-text); font-weight:850; font-size:.88rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rc-thread-preview { color:var(--rc-muted); font-size:.76rem; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .rc-email-thread { min-height:34rem; display:flex; flex-direction:column; }
        .rc-email-thread-head { display:flex; justify-content:space-between; gap:1rem; align-items:center; padding-bottom:.9rem; border-bottom:1px solid var(--rc-border); }
        .rc-message-list { display:grid; gap:1rem; padding:1rem 0; flex:1; overflow:auto; }
        .rc-email-message { border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1rem; padding:0; max-width:100%; overflow:hidden; box-shadow:0 12px 30px rgba(15,23,42,.06); }
        .rc-email-message.out { margin-left:0; background:var(--rc-surface); border-color:rgba(255,99,56,.32); }
        .rc-email-message.out .rc-email-format-head { border-left:4px solid var(--rc-accent); }
        .rc-email-format-head { display:grid; gap:.35rem; padding:.9rem 1rem; border-bottom:1px solid var(--rc-border); background:var(--rc-soft); }
        .rc-email-format-line { display:flex; gap:.45rem; align-items:baseline; min-width:0; color:var(--rc-muted); font-size:.75rem; line-height:1.35; }
        .rc-email-format-line strong { color:var(--rc-text); font-size:.76rem; min-width:3.1rem; }
        .rc-email-format-subject { color:var(--rc-text); font-weight:850; font-size:.95rem; line-height:1.35; margin-top:.15rem; }
        .rc-message-meta { display:flex; align-items:center; justify-content:space-between; gap:.65rem; margin-bottom:.55rem; color:var(--rc-muted); font-size:.72rem; }
        .rc-message-body { color:#111827; background:#fff; line-height:1.65; font-size:.94rem; padding:1.05rem; overflow:auto; }
        .rc-message-body img { max-width:100%; height:auto; border-radius:.75rem; display:block; margin:.75rem 0; }
        .rc-message-body table { max-width:100%; border-collapse:collapse; }
        .rc-message-body a { color:#2563eb; text-decoration:underline; }
        .rc-message-attachments { display:grid; gap:.55rem; padding:0 1.05rem 1.05rem; background:#fff; }
        .rc-message-attachment-image { max-width:100%; border-radius:.85rem; border:1px solid #e5e7eb; background:#fff; }
        .rc-message-attachment-link { display:inline-flex; align-items:center; gap:.4rem; width:max-content; max-width:100%; border:1px solid #e5e7eb; border-radius:.7rem; padding:.45rem .65rem; color:#2563eb; background:#f8fafc; font-size:.82rem; font-weight:750; text-decoration:none; }
        .rc-school-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:1rem; }
        .rc-school-card { min-height:unset; padding:1rem; border-radius:1rem; transition:transform .15s ease, border-color .15s ease, background .15s ease; }
        .rc-school-card:hover { transform:translateY(-2px); border-color:rgba(255,99,56,.5); }
        .rc-school-actions .rc-btn { min-width:5rem; }

        /* v62 compose/template quick actions */
        .rc-loading-spin { display:inline-block; width:1rem; height:1rem; border:2px solid currentColor; border-right-color:transparent; border-radius:999px; animation:rcSpin .7s linear infinite; vertical-align:-.16em; }
        @keyframes rcSpin { to { transform:rotate(360deg); } }
        .rc-btn-loading { pointer-events:none; opacity:.72; }
        .rc-mini-action { display:inline-flex; align-items:center; gap:.35rem; padding:.45rem .7rem; border-radius:.7rem; border:1px solid rgba(148,163,184,.18); background:rgba(255,255,255,.045); color:#fff; font-weight:800; font-size:.82rem; transition:all .14s ease; }
        .rc-mini-action:hover { border-color:rgba(255,99,56,.55); color:#ff7a5c; transform:translateY(-1px); }
        .rc-rich-toolbar { display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.55rem; }
        .rc-rich-toolbar button { min-width:2.1rem; justify-content:center; }
        .rc-search-slim { margin:.75rem 0 1rem; }
        .rc-school-modal-actions { display:flex; flex-wrap:wrap; gap:.55rem; margin:1rem 0; }



        /* v56 Inbox redesign */
        .rc-inbox-page-v56 { display:grid; gap:1rem; }
        .rc-inbox-shell-v56 { display:grid; grid-template-columns: 23rem minmax(0,1fr) 22rem; min-height:42rem; border:1px solid var(--rc-border); border-radius:1.15rem; background:var(--rc-surface); box-shadow:0 16px 40px rgba(15,23,42,.07); overflow:hidden; }
        .dark .rc-inbox-shell-v56 { box-shadow:0 20px 50px rgba(0,0,0,.26); }
        .rc-inbox-left-v56 { border-right:1px solid var(--rc-border); background:var(--rc-surface); min-width:0; }
        .rc-inbox-mid-v56 { min-width:0; display:flex; flex-direction:column; background:var(--rc-surface); }
        .rc-inbox-right-v56 { border-left:1px solid var(--rc-border); background:var(--rc-soft); min-width:0; }
        .rc-inbox-panel-head-v56 { padding:1.05rem 1.1rem .8rem; display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
        .rc-inbox-panel-head-v56 h2 { margin:0; font-size:1.15rem; font-weight:760; letter-spacing:-.03em; color:var(--rc-text); }
        .rc-inbox-head-actions-v56 { display:flex; align-items:center; gap:.4rem; }
        .rc-inbox-icon-btn-v56 { width:2.05rem; height:2.05rem; display:grid; place-items:center; border:0; border-radius:.65rem; background:transparent; color:var(--rc-muted); cursor:pointer; }
        .rc-inbox-icon-btn-v56:hover { background:var(--rc-soft); color:var(--rc-text); }
        .rc-inbox-search-v56 { padding:0 1.1rem .75rem; }
        .rc-inbox-search-v56 label { position:relative; display:block; }
        .rc-inbox-search-v56 svg { position:absolute; left:.7rem; top:50%; transform:translateY(-50%); width:1rem; height:1rem; color:#94a3b8; }
        .rc-inbox-search-v56 input { width:100%; height:2.45rem; border:1px solid var(--rc-border); border-radius:.75rem; background:var(--rc-soft); color:var(--rc-text); padding:0 .8rem 0 2.05rem; font-size:.84rem; outline:none; }
        .rc-inbox-tabs-v56 { display:flex; gap:1rem; align-items:center; padding:0 1.1rem .7rem; border-bottom:1px solid var(--rc-border); }
        .rc-inbox-tab-v56 { border:0; background:transparent; color:var(--rc-muted); padding:.35rem 0; font-size:.82rem; font-weight:650; cursor:pointer; position:relative; }
        .rc-inbox-tab-v56.is-active { color:#ff6338; }
        .rc-inbox-tab-v56.is-active::after { content:""; position:absolute; left:0; right:0; bottom:-.72rem; height:2px; background:#ff6338; border-radius:999px; }
        .rc-inbox-list-v56 { max-height:36rem; overflow:auto; }
        .rc-thread-card-v56 { width:100%; border:0; border-left:3px solid transparent; background:transparent; color:var(--rc-text); text-align:left; padding:.95rem 1.1rem; display:grid; grid-template-columns:2.65rem minmax(0,1fr) auto; gap:.7rem; cursor:pointer; border-bottom:1px solid var(--rc-border); }
        .rc-thread-card-v56:hover { background:rgba(255,99,56,.055); }
        .rc-thread-card-v56.is-selected { background:rgba(255,99,56,.13); border-left-color:#ff6338; }
        .rc-thread-logo-v56 { width:2.3rem; height:2.3rem; border-radius:999px; display:grid; place-items:center; overflow:hidden; border:1px solid var(--rc-border); background:#fff; color:#111827; font-size:.76rem; font-weight:760; }
        .rc-thread-logo-v56 img { width:100%; height:100%; object-fit:contain; padding:.25rem; }
        .rc-thread-name-v56 { display:block; font-size:.88rem; line-height:1.15; font-weight:760; color:var(--rc-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rc-thread-school-v56 { display:block; margin-top:.18rem; font-size:.78rem; color:var(--rc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rc-thread-preview-v56 { display:block; margin-top:.45rem; font-size:.78rem; line-height:1.35; color:var(--rc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rc-thread-date-v56 { font-size:.72rem; color:var(--rc-muted); white-space:nowrap; }
        .rc-thread-status-v56 { display:inline-flex; align-items:center; justify-content:center; border-radius:.45rem; padding:.18rem .45rem; font-size:.68rem; font-weight:760; color:#059669; background:rgba(16,185,129,.12); margin-top:.5rem; }
        .rc-thread-status-v56.is-opened { color:#f59e0b; background:rgba(245,158,11,.13); }
        .rc-thread-unread-dot-v56 { width:.46rem; height:.46rem; border-radius:999px; background:#ff6338; margin-top:.45rem; justify-self:end; }
        .rc-inbox-mid-head-v56 { min-height:5.3rem; border-bottom:1px solid var(--rc-border); padding:.9rem 1.2rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
        .rc-inbox-coach-title-v56 { display:grid; grid-template-columns:2.75rem minmax(0,1fr); gap:.7rem; align-items:center; }
        .rc-inbox-school-logo-v56 { width:2.45rem; height:2.45rem; border-radius:999px; border:1px solid var(--rc-border); display:grid; place-items:center; overflow:hidden; background:#fff; color:#111827; font-size:.75rem; font-weight:760; }
        .rc-inbox-school-logo-v56 img { width:100%; height:100%; object-fit:contain; padding:.28rem; }
        .rc-inbox-coach-title-v56 h3 { margin:0; font-size:1rem; font-weight:760; color:var(--rc-text); }
        .rc-inbox-coach-title-v56 p { margin:.15rem 0 0; color:var(--rc-muted); font-size:.78rem; }
        .rc-inbox-mid-actions-v56 { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; justify-content:flex-end; }
        .rc-inbox-open-composer-v56 { min-height:2.15rem; border:1px solid var(--rc-border); border-radius:.7rem; background:var(--rc-surface); color:var(--rc-text); padding:0 .75rem; display:inline-flex; gap:.4rem; align-items:center; font-size:.78rem; font-weight:700; cursor:pointer; }
        .rc-inbox-open-composer-v56:hover { border-color:#ff6338; color:#ff6338; }
        .rc-message-stream-v56 { overflow:auto; padding:1.2rem; display:grid; gap:1.15rem; flex:1; align-content:start; }
        .rc-inbox-message-v56 { display:grid; grid-template-columns:2.4rem minmax(0,1fr); gap:.7rem; align-items:start; }
        .rc-inbox-message-v56.is-out { grid-template-columns:2.4rem minmax(0,1fr); }
        .rc-msg-avatar-v56 { width:2.25rem; height:2.25rem; border-radius:999px; background:#ff6338; color:#fff; display:grid; place-items:center; font-size:.74rem; font-weight:800; }
        .rc-inbox-message-v56.is-out .rc-msg-avatar-v56 { background:#ff6338; }
        .rc-msg-meta-v56 { display:flex; align-items:center; justify-content:space-between; gap:.75rem; color:var(--rc-muted); font-size:.74rem; margin:0 0 .5rem; }
        .rc-msg-meta-v56 strong { color:var(--rc-text); font-size:.84rem; }
        .rc-msg-bubble-v56 { width:min(100%,42rem); border-radius:.9rem; padding:1rem; background:#f2f4f8; color:#111827; line-height:1.58; font-size:.9rem; }
        .dark .rc-msg-bubble-v56 { background:#111827; color:#e5e7eb; }
        .rc-msg-bubble-v56 p { margin:.45rem 0; }
        .rc-msg-bubble-v56 img { max-width:100%; height:auto; border-radius:.65rem; }
        .rc-message-status-v56 { margin:.35rem 0 0 3.1rem; color:#059669; font-size:.75rem; display:flex; align-items:center; gap:.35rem; }
        .rc-coach-profile-v56 { height:100%; overflow:auto; }
        .rc-coach-cover-v56 { height:6.6rem; background:linear-gradient(135deg,#1f2937,#111827); display:grid; place-items:center; position:relative; }
        .rc-coach-cover-v56 .rc-cover-logo-v56 { max-width:8rem; max-height:3.3rem; object-fit:contain; filter:drop-shadow(0 10px 20px rgba(0,0,0,.3)); }
        .rc-profile-content-v56 { padding:0 1.15rem 1.15rem; }
        .rc-profile-avatar-v56 { width:4.1rem; height:4.1rem; border-radius:999px; background:#ff6338; color:#fff; border:4px solid var(--rc-soft); display:grid; place-items:center; font-weight:850; margin-top:-2.1rem; position:relative; box-shadow:0 10px 24px rgba(15,23,42,.12); }
        .rc-profile-name-v56 { display:flex; align-items:center; gap:.35rem; margin-top:1rem; }
        .rc-profile-name-v56 h3 { margin:0; color:var(--rc-text); font-size:1rem; font-weight:780; }
        .rc-verified-v56 { width:1rem; height:1rem; border-radius:999px; background:#3b82f6; color:#fff; display:grid; place-items:center; font-size:.65rem; }
        .rc-profile-sub-v56 { color:var(--rc-muted); font-size:.82rem; margin:.22rem 0 0; line-height:1.35; }
        .rc-contact-lines-v56 { display:grid; gap:.55rem; margin:1.05rem 0; }
        .rc-contact-line-v56 { display:grid; grid-template-columns:1.1rem minmax(0,1fr); gap:.5rem; align-items:center; color:var(--rc-text); font-size:.78rem; }
        .rc-contact-line-v56 svg { color:var(--rc-muted); width:1rem; height:1rem; }
        .rc-profile-actions-v56 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.5rem; margin:1rem 0; }
        .rc-profile-action-v56 { min-height:3.25rem; border:1px solid var(--rc-border); border-radius:.8rem; background:var(--rc-surface); color:var(--rc-text); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.22rem; padding:.45rem .25rem; font-size:.7rem; line-height:1.1; font-weight:700; cursor:pointer; min-width:0; text-align:center; }
        .rc-profile-action-v56:hover { border-color:#ff6338; color:#ff6338; }
        .rc-about-grid-v56 { display:grid; grid-template-columns:1fr 1fr; gap:.85rem; }
        .rc-about-item-v56 { display:grid; grid-template-columns:1.1rem minmax(0,1fr); gap:.45rem; color:var(--rc-muted); font-size:.72rem; }
        .rc-about-item-v56 strong { display:block; color:var(--rc-text); font-size:.86rem; margin-bottom:.12rem; }
        .rc-inbox-empty-v56 { min-height:20rem; display:grid; place-items:center; color:var(--rc-muted); text-align:center; padding:2rem; }
        @media (max-width: 1320px) { .rc-inbox-shell-v56 { grid-template-columns:20rem minmax(0,1fr); } .rc-inbox-right-v56 { display:none; } }
        @media (max-width: 900px) { .rc-inbox-shell-v56 { grid-template-columns:1fr; } .rc-inbox-left-v56 { border-right:0; } .rc-inbox-mid-v56 { min-height:34rem; } }

        @media (max-width: 1100px) { .rc-compose-compact-grid,.rc-inbox-layout,.rc-school-filter-box { grid-template-columns:1fr; } .rc-recipient-tabs { grid-template-columns:repeat(2,minmax(0,1fr)); } }


        /* v60 dashboard refresh */
        .rc-dashboard { display:grid; gap:1.45rem; }
        .rc-dashboard-hero { display:grid; gap:.45rem; margin:.35rem 0 .45rem; }
        .rc-dashboard-hero h1 { margin:0; font-size:clamp(1.85rem, 4vw, 2.75rem); line-height:1.02; font-weight:950; letter-spacing:-.055em; color:#fff; text-shadow:0 2px 0 rgba(255,99,56,.18); }
        .rc-dashboard-hero p { margin:0; color:#b8c4d5; font-size:1rem; }
        .rc-dashboard-stat-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
        .rc-dashboard-card { position:relative; overflow:hidden; border:1px solid rgba(148,163,184,.22); background:linear-gradient(180deg, rgba(32,35,42,.98), rgba(24,26,31,.98)); border-radius:1.1rem; padding:1.35rem; box-shadow:0 18px 42px rgba(0,0,0,.22); }
        .rc-dashboard-card.is-centered { text-align:center; display:grid; place-items:center; min-height:10.2rem; }
        .rc-dashboard-card.is-centered .rc-dashboard-icon { margin-inline:auto; }
        .rc-dashboard-card.is-centered .rc-dashboard-number { letter-spacing:-.055em; }
        .rc-dashboard-stat { min-height:11.25rem; display:flex; flex-direction:column; justify-content:space-between; }
        .rc-dashboard-stat:before { content:""; position:absolute; left:0; top:.55rem; bottom:.55rem; width:.33rem; border-radius:999px; background:var(--stat-color, var(--rc-accent)); }
        .rc-dashboard-icon { display:inline-flex; width:3.05rem; height:3.05rem; align-items:center; justify-content:center; border-radius:.9rem; color:var(--stat-color, var(--rc-accent)); background:color-mix(in srgb, var(--stat-color, var(--rc-accent)) 18%, transparent); }
        .rc-dashboard-icon svg { width:1.35rem; height:1.35rem; }
        .rc-dashboard-number { margin-top:1.05rem; font-size:2.7rem; line-height:.9; font-weight:950; letter-spacing:-.075em; color:#fff; }
        .rc-dashboard-label { margin-top:.55rem; color:#aab7c8; font-size:.92rem; }
        .rc-dashboard-engagement { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
        .rc-metric-card { min-height:12.5rem; display:grid; gap:.85rem; }
        .rc-metric-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
        .rc-metric-delta { display:inline-flex; align-items:center; gap:.25rem; border-radius:999px; padding:.22rem .48rem; font-size:.75rem; font-weight:850; background:rgba(20,184,166,.13); color:#2dd4bf; }
        .rc-metric-delta.is-down { background:rgba(248,113,113,.14); color:#fb7185; }
        .rc-metric-value { font-size:2.15rem; line-height:1; font-weight:950; letter-spacing:-.06em; color:#030712; }
        .dark .rc-metric-value { color:#fff; }
        .rc-metric-name { color:#aab7c8; font-size:.86rem; }
        .rc-spark { width:100%; height:2.25rem; margin-top:auto; color:var(--rc-accent); }
        .rc-spark polyline { fill:none; stroke:currentColor; stroke-width:4; stroke-linecap:round; stroke-linejoin:round; }
        .rc-spark polygon { fill:rgba(255,99,56,.14); }
        .rc-dashboard-section-title { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin:.15rem 0 .55rem; }
        .rc-dashboard-section-title h2 { margin:0; font-size:1.35rem; line-height:1.1; font-weight:950; letter-spacing:-.04em; color:#fff; }
        .rc-dashboard-wide { display:grid; grid-template-columns:minmax(0,1fr); gap:1rem; }
        .rc-engaged-list { display:grid; gap:.65rem; }
        .rc-engaged-row { display:grid; grid-template-columns:2.6rem minmax(0,1fr) auto 10rem 3.5rem; align-items:center; gap:1rem; border-radius:1rem; background:rgba(15,18,24,.42); padding:.95rem 1rem; }
        .rc-rank { width:2.35rem; height:2.35rem; border-radius:.75rem; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#ff6338,#ff9885); color:white; font-weight:950; }
        .rc-rank.is-muted { background:#94a3b8; }
        .rc-school-title { font-size:1rem; font-weight:900; color:#fff; line-height:1.2; }
        .rc-school-mini { display:flex; flex-wrap:wrap; align-items:center; gap:.65rem; margin-top:.32rem; color:#9fb0c5; font-size:.78rem; }
        .rc-school-mini span { display:inline-flex; align-items:center; gap:.24rem; }
        .rc-school-mini svg { width:.9rem; height:.9rem; }
        .rc-replied-badge { display:inline-flex; border-radius:.38rem; padding:.18rem .42rem; background:rgba(20,184,166,.16); color:#2dd4bf; font-size:.66rem; font-weight:900; letter-spacing:.03em; text-transform:uppercase; }
        .rc-lead-bar { height:.58rem; border-radius:999px; background:rgba(255,255,255,.08); overflow:hidden; }
        .rc-lead-bar span { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#ff6338,#ff8d78); }
        .rc-lead-score { color:#fff; font-weight:950; font-size:1.05rem; text-align:right; }
        .rc-dashboard-bottom { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,.88fr); gap:1rem; }
        .rc-step-list, .rc-activity-list, .rc-list-pills { display:grid; gap:.8rem; }
        .rc-step-row { display:grid; grid-template-columns:2.35rem minmax(0,1fr) auto; gap:.8rem; align-items:center; border-radius:1rem; background:rgba(15,18,24,.36); padding:1rem; }
        .rc-step-index { width:2.15rem; height:2.15rem; display:flex; align-items:center; justify-content:center; border-radius:999px; color:white; background:var(--rc-accent); font-weight:950; }
        .rc-step-title { color:#fff; font-weight:900; }
        .rc-step-copy { color:#aab7c8; font-size:.86rem; margin-top:.22rem; }
        .rc-activity-empty { color:#aab7c8; font-size:.95rem; padding:1rem 0; }
        .rc-list-box { grid-column:1 / -1; }
        .rc-list-pills { grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:.8rem; }
        .rc-list-pill { display:flex; align-items:center; justify-content:space-between; gap:.75rem; border:1px solid rgba(148,163,184,.2); border-radius:.85rem; padding:.85rem 1rem; background:rgba(15,18,24,.28); color:#fff; font-weight:800; }
        .rc-list-count { min-width:1.55rem; height:1.25rem; border-radius:999px; background:var(--rc-accent); color:white; display:inline-flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:950; padding:0 .4rem; }
        @media (max-width:1180px) { .rc-dashboard-stat-grid,.rc-dashboard-engagement { grid-template-columns:repeat(2,minmax(0,1fr)); } .rc-dashboard-bottom { grid-template-columns:1fr; } .rc-engaged-row { grid-template-columns:2.6rem minmax(0,1fr) auto; } .rc-lead-bar,.rc-lead-score { display:none; } }
        @media (max-width:640px) { .rc-dashboard-stat-grid,.rc-dashboard-engagement { grid-template-columns:1fr; } .rc-step-row { grid-template-columns:2.35rem minmax(0,1fr); } .rc-step-row .rc-btn { grid-column:2; justify-self:start; } }



        /* v61 dashboard polish: compact text, png icons, scrollable activity, school slider */
        .rc-dashboard { gap: 1.25rem; }
        .rc-dashboard-hero h1 { max-width: 58rem; }
        .rc-dashboard-hero p { max-width: 62rem; color:#a9b6c8; }
        .rc-dashboard-stat-grid { grid-template-columns: repeat(4, minmax(0,1fr)); }
        .rc-dashboard-stat { min-height: 9.65rem; }
        .rc-dashboard-icon { border-radius: .85rem; }
        .rc-dashboard-icon img.rc-png-icon { width:1.35rem; height:1.35rem; display:block; object-fit:contain; }
        .rc-dashboard-number { font-size:2.55rem; }
        .rc-dashboard-label { font-size:.86rem; line-height:1.15; }
        .rc-dashboard-subline { margin-top:.28rem; color:#7f8da2; font-size:.72rem; line-height:1.25; min-height:1.8em; max-width:15rem; }
        .rc-metric-card { min-height:11.4rem; padding:1.15rem; }
        .rc-metric-value { font-size:2rem; }
        .rc-metric-name { font-size:.83rem; line-height:1.15; }
        .rc-metric-caption { color:#7f8da2; font-size:.72rem; line-height:1.25; margin-top:.28rem; max-width:14rem; }
        .rc-spark { height:2rem; opacity:.95; }
        .rc-engaged-row { cursor:pointer; transition:transform .15s ease, background .15s ease, border-color .15s ease; border:1px solid transparent; }
        .rc-engaged-row:hover { transform:translateY(-1px); border-color:rgba(255,99,56,.35); background:rgba(255,99,56,.075); }
        .rc-activity-list { max-height:24rem; overflow:auto; padding-right:.35rem; }
        .rc-activity-card { display:grid; grid-template-columns:2.2rem minmax(0,1fr) auto; gap:.72rem; align-items:start; border-radius:1rem; background:rgba(15,18,24,.36); padding:.85rem; }
        .rc-activity-copy { color:#aab7c8; font-size:.78rem; line-height:1.35; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
        .rc-activity-meta { color:#718096; font-size:.7rem; margin-top:.25rem; }
        .rc-activity-view { min-height:1.85rem; padding:.25rem .5rem; font-size:.7rem; }
        .rc-drawer { justify-content:center; align-items:center; background:rgba(0,0,0,.68); backdrop-filter:blur(8px); }
        .rc-drawer-panel { width:min(760px,92vw); height:min(82vh,760px); border:1px solid rgba(148,163,184,.22); border-radius:1.25rem; background:linear-gradient(180deg, rgb(31 34 41), rgb(24 26 31)); box-shadow:0 30px 90px rgba(0,0,0,.45); padding:1.35rem; }
        .rc-school-slide-head { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:1rem; align-items:start; }
        .rc-school-score-ring { width:4.15rem; height:4.15rem; border-radius:999px; display:grid; place-items:center; border:.42rem solid var(--rc-accent); color:#fff; font-weight:950; font-size:1.1rem; }
        .rc-school-detail-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; margin:1rem 0; }
        .rc-school-detail-stat { border-radius:.9rem; background:rgba(15,18,24,.42); padding:.85rem; }
        .rc-school-detail-stat strong { display:block; color:#fff; font-size:1.2rem; }
        .rc-school-detail-stat span { color:#9fb0c5; font-size:.72rem; }
        .rc-school-coach-list { display:grid; gap:.7rem; max-height:20rem; overflow:auto; padding-right:.2rem; }
        @media (max-width:1180px) { .rc-dashboard-stat-grid,.rc-dashboard-engagement { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:640px) { .rc-dashboard-stat-grid,.rc-dashboard-engagement,.rc-school-detail-grid { grid-template-columns:1fr; } .rc-activity-card { grid-template-columns:2.2rem minmax(0,1fr); } .rc-activity-view { grid-column:2; justify-self:start; } }
        /* v62 upper dashboard stat alignment fix */
        .rc-dashboard-hero { margin-top: .15rem; }
        .rc-dashboard-hero h1 { margin-bottom: .15rem; }
        .rc-dashboard-stat-grid { align-items: stretch; }
        .rc-dashboard-card.rc-dashboard-stat.is-centered {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 8.8rem;
            padding: 1.05rem 1rem 1rem;
            text-align: center;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered > div {
            width: 100%;
            display: grid;
            justify-items: center;
            align-content: center;
            gap: .32rem;
        }
        .rc-dashboard-card.rc-dashboard-stat:before {
            top: .6rem;
            bottom: .6rem;
            width: .28rem;
        }
        .rc-dashboard-icon {
            width: 2.7rem;
            height: 2.7rem;
            border-radius: .85rem;
            margin: 0 auto .18rem;
        }
        .rc-dashboard-icon img.rc-png-icon {
            width: 1.18rem;
            height: 1.18rem;
        }
        .rc-dashboard-number {
            margin: .08rem 0 0;
            font-size: clamp(2rem, 3.4vw, 2.45rem);
            line-height: .92;
            letter-spacing: -.055em;
        }
        .rc-dashboard-label {
            margin: 0;
            font-size: .82rem;
            line-height: 1.05;
            color: #d9e5f5;
        }
        .rc-dashboard-subline {
            margin: .05rem auto 0;
            min-height: 0;
            max-width: 11.5rem;
            font-size: .66rem;
            line-height: 1.18;
            color: #8190a5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .rc-dashboard-section-title { margin-top: .35rem; }
        .rc-dashboard-section-title .rc-subtle { max-width: 34rem; text-align: right; line-height: 1.25; }

        /* v63 stat layout: top-left content, no stat subtext */
        .rc-dashboard-stat-grid { align-items: stretch; }
        .rc-dashboard-card.rc-dashboard-stat.is-centered {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            text-align: left !important;
            min-height: 7.85rem !important;
            padding: 1.05rem 1.15rem 1rem 1.35rem !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered > div {
            width: 100% !important;
            display: grid !important;
            justify-items: start !important;
            align-content: start !important;
            gap: .28rem !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered .rc-dashboard-icon {
            margin: 0 0 .6rem 0 !important;
            width: 2.55rem !important;
            height: 2.55rem !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered .rc-dashboard-number {
            margin: 0 !important;
            font-size: clamp(2.05rem, 3vw, 2.45rem) !important;
            line-height: .9 !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered .rc-dashboard-label {
            margin: .1rem 0 0 !important;
            font-size: .9rem !important;
            line-height: 1.05 !important;
            color: #e7eef9 !important;
        }
        .rc-dashboard-subline, .rc-metric-caption { display: none !important; }
        .rc-metric-card { min-height: 8.6rem; }
        .rc-metric-card .rc-metric-head { margin-bottom: .7rem; }

        /* v64 engagement metrics: keep icons top-left, never centered */
        .rc-dashboard-engagement .rc-dashboard-card.rc-metric-card {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            justify-content: flex-start !important;
            text-align: left !important;
            min-height: 8.75rem !important;
            padding: 1.05rem 1.15rem !important;
        }
        .rc-dashboard-engagement .rc-metric-head {
            display: flex !important;
            justify-content: flex-start !important;
            align-items: flex-start !important;
            margin: 0 0 .72rem 0 !important;
            width: 100% !important;
        }
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon {
            margin: 0 !important;
            width: 2.5rem !important;
            height: 2.5rem !important;
        }
        .rc-dashboard-engagement .rc-metric-card > div:nth-child(2) {
            margin: 0 !important;
            text-align: left !important;
        }
        .rc-dashboard-engagement .rc-metric-value {
            margin: 0 !important;
            font-size: 2.2rem !important;
            line-height: .92 !important;
        }
        .rc-dashboard-engagement .rc-metric-name {
            margin-top: .22rem !important;
            color: #e7eef9 !important;
        }
        .rc-dashboard-engagement .rc-spark {
            margin-top: auto !important;
        }


        /* v65 dashboard final alignment + readable activity */
        .rc-load-status { display:flex; align-items:center; gap:.45rem; color:#b7c5d9; font-size:.88rem; font-weight:760; letter-spacing:.01em; }
        .rc-load-status-icon { color:var(--rc-accent); font-weight:950; font-size:1.15rem; line-height:1; }
        .rc-top .rc-pill { font-size:.76rem; padding:.48rem .68rem; color:#dbeafe; }
        .rc-dashboard-engagement .rc-dashboard-card.rc-metric-card,
        .rc-dashboard-engagement .rc-metric-card {
            display:grid !important;
            grid-template-rows:auto auto 1fr !important;
            align-items:start !important;
            justify-items:start !important;
            align-content:start !important;
            justify-content:stretch !important;
            place-items:start stretch !important;
            text-align:left !important;
            min-height:9.2rem !important;
        }
        .rc-dashboard-engagement .rc-metric-head,
        .rc-dashboard-engagement .rc-metric-head * { align-self:start !important; justify-self:start !important; }
        .rc-dashboard-engagement .rc-metric-head { width:100% !important; display:block !important; margin:0 0 .58rem 0 !important; }
        .rc-dashboard-engagement .rc-dashboard-icon,
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon { margin:0 !important; display:inline-flex !important; }
        .rc-dashboard-engagement .rc-metric-card > div:nth-child(2) { align-self:start !important; justify-self:start !important; width:100% !important; }
        .rc-dashboard-engagement .rc-spark { align-self:end !important; justify-self:stretch !important; width:100% !important; margin-top:.55rem !important; }

        /* v66 dashboard stat alignment: all stat icons/content stay top-left */
        .rc-dashboard-card,
        .rc-dashboard-card.rc-dashboard-stat,
        .rc-dashboard-card.rc-metric-card {
            position: relative;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered,
        .rc-dashboard-card.rc-metric-card,
        .rc-dashboard-engagement .rc-dashboard-card.rc-metric-card {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            text-align: left !important;
            gap: .72rem !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered > div {
            align-items: flex-start !important;
            justify-content: flex-start !important;
            text-align: left !important;
            width: 100% !important;
        }
        .rc-dashboard-icon {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex: 0 0 auto !important;
            margin: 0 !important;
        }
        .rc-dashboard-icon .rc-png-icon,
        .rc-png-icon {
            width: 1.05rem !important;
            height: 1.05rem !important;
            display: block !important;
            object-fit: contain !important;
            object-position: center center !important;
            margin: auto !important;
        }
        .rc-dashboard-number,
        .rc-metric-value {
            margin-top: .2rem !important;
            text-align: left !important;
        }
        .rc-dashboard-label,
        .rc-metric-name {
            text-align: left !important;
            margin-top: -.18rem !important;
        }
        .rc-dashboard-engagement .rc-metric-head {
            display: flex !important;
            width: auto !important;
            margin: 0 !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
        }
        .rc-dashboard-engagement .rc-metric-card > div:nth-child(2) {
            width: 100% !important;
            align-self: flex-start !important;
        }
        .rc-dashboard-engagement .rc-spark {
            width: 100% !important;
            margin-top: auto !important;
            align-self: stretch !important;
        }
        .rc-engaged-row { cursor: pointer; }

        .rc-activity-card.has-asset .rc-activity-copy { -webkit-line-clamp:2; }
        .rc-activity-asset { display:inline-flex; align-items:center; gap:.28rem; margin-top:.35rem; width:max-content; max-width:100%; border:1px solid rgba(148,163,184,.18); border-radius:999px; padding:.2rem .48rem; color:#dbeafe; background:rgba(59,130,246,.12); font-size:.68rem; font-weight:850; }

        /* v68 console-safe top school clicks + locked icon alignment */
        .rc-dashboard-stat-grid .rc-dashboard-card,
        .rc-dashboard-engagement .rc-dashboard-card {
            align-items:flex-start !important;
            justify-content:flex-start !important;
            place-items:start !important;
            text-align:left !important;
        }
        .rc-dashboard-stat-grid .rc-dashboard-card > div,
        .rc-dashboard-engagement .rc-dashboard-card > div {
            align-self:flex-start !important;
            justify-self:flex-start !important;
            text-align:left !important;
        }
        .rc-dashboard-icon {
            display:inline-grid !important;
            place-items:center !important;
            align-self:flex-start !important;
            justify-self:flex-start !important;
            overflow:hidden !important;
            line-height:1 !important;
        }
        .rc-dashboard-icon img.rc-png-icon,
        img.rc-png-icon {
            width:1rem !important;
            height:1rem !important;
            object-fit:contain !important;
            object-position:50% 50% !important;
            display:block !important;
            margin:0 !important;
            transform:none !important;
        }
        .rc-dashboard-engagement .rc-metric-head {
            align-self:flex-start !important;
            justify-self:flex-start !important;
            margin:0 0 .72rem 0 !important;
        }


        /* v69 centered PNG stat icons: content stays top-left, icon artwork stays centered inside its badge */
        .rc-dashboard-card .rc-dashboard-icon,
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon {
            display:flex !important;
            align-items:center !important;
            justify-content:center !important;
            padding:0 !important;
            line-height:1 !important;
            text-align:center !important;
        }
        .rc-dashboard-card .rc-dashboard-icon img.rc-png-icon,
        .rc-dashboard-engagement .rc-dashboard-icon img.rc-png-icon,
        .rc-png-icon {
            width:1.06rem !important;
            height:1.06rem !important;
            display:block !important;
            object-fit:contain !important;
            object-position:center center !important;
            margin:0 !important;
            padding:0 !important;
            transform:none !important;
            position:static !important;
            inset:auto !important;
        }
        .rc-dashboard-engagement .rc-metric-head {
            display:flex !important;
            align-items:flex-start !important;
            justify-content:flex-start !important;
        }



        /* FINAL CLEAN DASHBOARD ICON RULES: one badge size, one artwork size, centered artwork */
        .rc-dashboard-stat-grid .rc-dashboard-icon,
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon {
            width: 3rem !important;
            height: 3rem !important;
            min-width: 3rem !important;
            min-height: 3rem !important;
            max-width: 3rem !important;
            max-height: 3rem !important;
            border-radius: 1rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
            line-height: 0 !important;
            box-sizing: border-box !important;
            flex: 0 0 3rem !important;
        }

        .rc-dashboard-stat-grid .rc-dashboard-icon > img.rc-png-icon,
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon > img.rc-png-icon {
            width: 1.42rem !important;
            height: 1.42rem !important;
            min-width: 1.42rem !important;
            min-height: 1.42rem !important;
            max-width: 1.42rem !important;
            max-height: 1.42rem !important;
            display: block !important;
            object-fit: contain !important;
            object-position: 50% 50% !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            position: static !important;
            inset: auto !important;
            transform: none !important;
            translate: none !important;
            vertical-align: middle !important;
        }

        .rc-dashboard-engagement .rc-metric-card {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            text-align: left !important;
        }

        .rc-dashboard-engagement .rc-metric-head {
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            max-width: none !important;
            max-height: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            align-self: flex-start !important;
            justify-self: flex-start !important;
            margin: 0 0 .78rem 0 !important;
            padding: 0 !important;
            line-height: 0 !important;
        }

        /* FINAL OVERRIDE: truly center engagement icons inside their colored badges */
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon {
            position: relative !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon > img.rc-png-icon {
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            margin: 0 !important;
            display: block !important;
            object-fit: contain !important;
            object-position: 50% 50% !important;
        }

        /* Top engaged school dialog */
        .rc-school-modal-backdrop {
            justify-content: center !important;
            align-items: center !important;
            padding: 1.25rem !important;
            background: rgba(0,0,0,.72) !important;
            backdrop-filter: blur(10px) !important;
        }

        .rc-school-modal-panel {
            position: relative !important;
            width: min(720px, 92vw) !important;
            height: min(82vh, 760px) !important;
            max-height: 82vh !important;
            overflow: auto !important;
            border-radius: 1.35rem !important;
            border: 1px solid rgba(148,163,184,.20) !important;
            background: linear-gradient(180deg, #20232b 0%, #1b1d23 100%) !important;
            box-shadow: 0 28px 90px rgba(0,0,0,.55) !important;
            padding: 1.55rem !important;
            color: #f8fafc !important;
        }

        /* v106: optimistic local drawer must never blur/lock the whole Discover page. */
        .rc-school-optimistic-shell-v106 {
            justify-content: flex-end !important;
            align-items: stretch !important;
            padding: 0 !important;
            background: transparent !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            pointer-events: none;
        }

        .rc-school-optimistic-panel-v106 {
            width: min(560px, 100vw) !important;
            height: 100vh !important;
            max-height: 100vh !important;
            border-radius: 0 !important;
            pointer-events: auto;
            animation: rcOptimisticSchoolInV106 .16s ease-out both;
        }

        @keyframes rcOptimisticSchoolInV106 {
            from { transform: translateX(24px); opacity: .65; }
            to { transform: translateX(0); opacity: 1; }
        }


        .rc-school-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 2.35rem;
            height: 2.35rem;
            border: 1px solid rgba(148,163,184,.16);
            border-radius: .85rem;
            background: rgba(15,18,24,.46);
            color: #9ca3af;
            font-size: 1.6rem;
            line-height: 1;
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: .15s ease;
            z-index: 3;
        }

        .rc-school-modal-close:hover {
            color: #fff;
            border-color: rgba(255,99,56,.35);
            background: rgba(255,99,56,.12);
        }

        .rc-school-modal-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1.25rem;
            align-items: start;
            padding-right: 3.25rem;
        }

        .rc-school-modal-main h2 {
            margin: .65rem 0 .35rem;
            font-size: clamp(1.45rem, 3vw, 1.95rem);
            line-height: 1.05;
            letter-spacing: -.035em;
            font-weight: 500;
            color: #fff;
        }

        .rc-school-division-pill {
            display: inline-flex;
            width: max-content;
            border-radius: .55rem;
            background: rgba(245,158,11,.20);
            color: #fbbf24;
            padding: .22rem .48rem;
            font-size: .72rem;
            font-weight: 500;
            letter-spacing: .035em;
        }

        .rc-school-modal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            color: #9fb0c5;
            font-size: .86rem;
            line-height: 1.35;
        }

        .rc-school-score-wrap {
            display: grid;
            justify-items: center;
            gap: .28rem;
            padding-top: .25rem;
        }

        .rc-school-score-ring {
            width: 4.55rem !important;
            height: 4.55rem !important;
            border-radius: 999px !important;
            display: grid !important;
            place-items: center !important;
            border: .42rem solid #ff6b50 !important;
            color: #fff !important;
            font-weight: 950 !important;
            font-size: 1.35rem !important;
            line-height: 1 !important;
            box-shadow: 0 0 0 .22rem rgba(255,99,56,.10), inset 0 0 0 1px rgba(255,255,255,.10) !important;
        }

        .rc-school-score-label {
            color: #ff6b50;
            font-size: .78rem;
            font-weight: 500;
            letter-spacing: .04em;
        }

        .rc-school-modal-actions {
            display: flex !important;
            align-items: center;
            flex-wrap: wrap;
            gap: .6rem;
            margin: 1.25rem 0 0 !important;
        }

        .rc-school-action {
            border: 1px solid rgba(148,163,184,.18);
            background: rgba(15,18,24,.36);
            color: #f8fafc;
            border-radius: .8rem;
            min-height: 2.65rem;
            padding: .62rem .9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            font-size: .82rem;
            font-weight: 650;
            transition: .15s ease;
        }

        .rc-school-action:hover {
            border-color: rgba(255,99,56,.40);
            background: rgba(255,99,56,.10);
        }

        .rc-school-action-primary {
            background: #ff6b50;
            border-color: #ff6b50;
            color: #fff;
        }

        .rc-school-action-primary:hover {
            background: #ff5837;
            border-color: #ff5837;
        }

        .rc-school-modal-rule {
            height: 1px;
            margin: 1.35rem 0 1.2rem;
            background: rgba(148,163,184,.18);
        }

        .rc-school-modal-section {
            display: grid;
            gap: .78rem;
            margin-top: 1.25rem;
        }

        .rc-school-section-title {
            color: #fff;
            font-size: 1rem;
            line-height: 1.2;
            font-weight: 500;
            letter-spacing: -.02em;
        }

        .rc-school-modal-coaches {
            display: grid;
            gap: .65rem;
            max-height: 19rem;
            overflow: auto;
            padding-right: .15rem;
        }

        .rc-school-coach-card {
            display: grid;
            grid-template-columns: 2.75rem minmax(0, 1fr) auto;
            align-items: center;
            gap: .8rem;
            border-radius: .82rem;
            background: rgba(15,18,24,.28);
            border: 1px solid rgba(148,163,184,.08);
            padding: .78rem;
        }

        .rc-school-coach-avatar {
            width: 2.5rem;
            height: 2.5rem;
            display: grid;
            place-items: center;
            border-radius: .75rem;
            background: #ff6b50;
            color: #fff;
            font-size: .78rem;
            font-weight: 500;
        }

        .rc-school-coach-info {
            display: grid;
            gap: .12rem;
            min-width: 0;
        }

        .rc-school-coach-info strong {
            color: #fff;
            font-size: .86rem;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-school-coach-info span {
            color: #aab7c8;
            font-size: .78rem;
            line-height: 1.25;
        }

        .rc-school-coach-info a {
            color: #4ea3ff;
            font-size: .8rem;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-school-copy-btn {
            width: 2.15rem;
            height: 2.15rem;
            display: grid;
            place-items: center;
            border: 1px solid rgba(148,163,184,.16);
            border-radius: .65rem;
            background: rgba(15,18,24,.32);
            color: #9fb0c5;
        }

        .rc-school-copy-btn:hover {
            color: #fff;
            border-color: rgba(255,99,56,.35);
        }

        .rc-school-stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .6rem;
        }

        .rc-school-stat-card {
            display: grid;
            grid-template-columns: 2.35rem minmax(0, 1fr);
            grid-template-rows: auto auto;
            column-gap: .6rem;
            align-items: center;
            border-radius: .82rem;
            background: rgba(15,18,24,.28);
            border: 1px solid rgba(148,163,184,.08);
            padding: .78rem;
        }

        .rc-school-stat-card span {
            grid-row: 1 / span 2;
            width: 2.15rem;
            height: 2.15rem;
            display: grid;
            place-items: center;
            border-radius: .7rem;
            background: rgba(255,99,56,.14);
            color: #ff6b50;
            font-weight: 500;
            line-height: 1;
        }

        .rc-school-stat-card strong {
            color: #fff;
            font-size: 1.35rem;
            line-height: 1;
            font-weight: 500;
        }

        .rc-school-stat-card small {
            color: #9fb0c5;
            font-size: .78rem;
            line-height: 1.2;
        }

        @media (max-width: 680px) {
            .rc-school-modal-panel {
                width: min(94vw, 720px) !important;
                height: min(86vh, 760px) !important;
                padding: 1rem !important;
            }

            .rc-school-modal-hero {
                grid-template-columns: 1fr;
                padding-right: 2.75rem;
            }

            .rc-school-score-wrap {
                justify-items: start;
            }

            .rc-school-stat-grid {
                grid-template-columns: 1fr;
            }

            .rc-school-coach-card {
                grid-template-columns: 2.75rem minmax(0, 1fr);
            }

            .rc-school-copy-btn {
                grid-column: 2;
                justify-self: start;
            }
        }



        /* v80 PLYRCard recruiting dashboard redesign: Filament light/dark aware */
        .rc-home-dashboard {
            display: grid;
            gap: 1.35rem;
            color: #0f172a;
            padding: .15rem 0 1rem;
        }
        .dark .rc-home-dashboard { color: #f8fafc; }
        .rc-home-topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .55rem;
        }
        .rc-home-topbar h1 {
            margin: 0;
            font-size: clamp(1.35rem, 2.7vw, 2rem);
            line-height: 1.1;
            letter-spacing: -.04em;
            font-weight: 650;
            color: #0f172a;
        }
        .dark .rc-home-topbar h1 { color: #fff; }
        .rc-home-topbar p,
        .rc-home-panel-head p {
            margin: .28rem 0 0;
            color: #7c8799;
            font-size: .82rem;
        }
        .dark .rc-home-topbar p,
        .dark .rc-home-panel-head p { color: #94a3b8; }
        .rc-home-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .6rem;
            flex-wrap: wrap;
        }
        .rc-home-search {
            min-width: min(28rem, 48vw);
            height: 2.65rem;
            display: flex;
            align-items: center;
            gap: .55rem;
            border: 1px solid #e5e7eb;
            border-radius: .85rem;
            background: rgba(255,255,255,.92);
            color: #94a3b8;
            padding: 0 .75rem;
            box-shadow: 0 8px 24px rgba(15,23,42,.08);
        }
        .dark .rc-home-search {
            border-color: rgba(148,163,184,.18);
            background: rgba(17,24,39,.82);
            box-shadow: none;
        }
        .rc-home-search input {
            border: 0;
            outline: 0;
            box-shadow: none !important;
            background: transparent;
            min-width: 0;
            flex: 1;
            font-size: .84rem;
            color: inherit;
        }
        .rc-home-search kbd {
            border: 1px solid #e5e7eb;
            border-radius: .45rem;
            padding: .08rem .36rem;
            color: #94a3b8;
            font-size: .7rem;
            font-weight: 500;
        }
        .dark .rc-home-search kbd { border-color: rgba(148,163,184,.2); }
        .rc-home-icon-btn,
        .rc-home-new-email,
        .rc-home-panel-head a,
        .rc-home-outline-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            border-radius: .85rem;
            min-height: 2.15rem;
            padding: .55rem .85rem;
            background: #fff;
            color: #0f172a;
            font-size: .82rem;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(15,23,42,.06);
        }
        .dark .rc-home-icon-btn,
        .dark .rc-home-panel-head a,
        .dark .rc-home-outline-btn {
            border-color: rgba(148,163,184,.18);
            background: rgba(17,24,39,.72);
            color: #e5e7eb;
            box-shadow: none;
        }
        .rc-home-new-email {
            background: #ff6338;
            border-color: #ff6338;
            color: #fff;
            box-shadow: 0 12px 24px rgba(255,99,56,.25);
        }
        .rc-home-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
        }
        .rc-home-stat-card,
        .rc-home-panel {
            border: 1px solid #e7eaf0;
            background: rgba(255,255,255,.96);
            border-radius: 1.1rem;
            box-shadow: 0 8px 22px rgba(15,23,42,.07);
        }
        .dark .rc-home-stat-card,
        .dark .rc-home-panel {
            border-color: rgba(148,163,184,.16);
            background: rgba(24,29,39,.88);
            box-shadow: none;
        }
        .rc-home-stat-card {
            min-height: 7.6rem;
            padding: .82rem;
            display: grid;
            grid-template-columns: 2.65rem minmax(0,1fr);
            align-content: start;
            gap: .55rem .8rem;
        }
        .rc-home-stat-icon,
        .rc-home-activity-icon {
            width: 2.65rem;
            height: 2.65rem;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            font-weight: 650;
            line-height: 1;
            flex: 0 0 auto;
        }
        .rc-home-stat-card.is-coral .rc-home-stat-icon { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-home-stat-card.is-blue .rc-home-stat-icon { background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-home-stat-card.is-gold .rc-home-stat-icon { background: rgba(245,158,11,.14); color: #f59e0b; }
        .rc-home-stat-card.is-green .rc-home-stat-icon { background: rgba(16,185,129,.13); color: #10b981; }
        .rc-home-stat-card.is-indigo .rc-home-stat-icon { background: rgba(96,165,250,.14); color: #60a5fa; }
        .rc-home-stat-copy { min-width: 0; }
        .rc-home-stat-label { color: #7c8799; font-size: .78rem; font-weight: 600; }
        .dark .rc-home-stat-label { color: #94a3b8; }
        .rc-home-stat-value { color: #0f172a; font-size: 1.45rem; line-height: 1; font-weight: 500; letter-spacing: -.04em; margin-top: .15rem; }
        .dark .rc-home-stat-value { color: #fff; }
        .rc-home-progress {
            grid-column: 1 / -1;
            height: .42rem;
            background: #eef1f6;
            border-radius: 999px;
            overflow: hidden;
            margin-top: .2rem;
        }
        .dark .rc-home-progress { background: rgba(148,163,184,.16); }
        .rc-home-progress span { display: block; height: 100%; border-radius: inherit; background: #ff6338; }
        .rc-home-stat-sub {
            grid-column: 1 / -1;
            color: #7c8799;
            font-size: .76rem;
        }
        .rc-home-stat-card.is-blue .rc-home-stat-sub,
        .rc-home-stat-card.is-green .rc-home-stat-sub { color: #059669; font-weight: 600; }
        .dark .rc-home-stat-sub { color: #94a3b8; }
        .dark .rc-home-stat-card.is-blue .rc-home-stat-sub,
        .dark .rc-home-stat-card.is-green .rc-home-stat-sub { color: #34d399; }
        .rc-home-main-grid,
        .rc-home-lower-grid {
            display: grid;
            grid-template-columns: minmax(0,1fr) minmax(320px,.82fr);
            gap: 1rem;
        }
        .rc-home-panel { padding: 1.2rem; }
        .rc-home-panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .rc-home-panel-head h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1rem;
            line-height: 1.15;
            font-weight: 650;
            letter-spacing: -.025em;
        }
        .dark .rc-home-panel-head h2 { color: #fff; }
        .rc-progress-layout {
            display: grid;
            grid-template-columns: 12.5rem minmax(0,1fr);
            gap: 1.35rem;
            align-items: center;
        }
        .rc-readiness-ring {
            width: 9.7rem;
            height: 9.7rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: conic-gradient(#ff6338 calc(var(--ready) * 1%), #edf0f5 0);
            margin-inline: auto;
            position: relative;
        }
        .dark .rc-readiness-ring { background: conic-gradient(#ff6f51 calc(var(--ready) * 1%), rgba(148,163,184,.18) 0); }
        .rc-readiness-ring:before {
            content: "";
            position: absolute;
            inset: 1rem;
            border-radius: inherit;
            background: #fff;
        }
        .dark .rc-readiness-ring:before { background: #181d27; }
        .rc-readiness-ring div { position: relative; display: grid; justify-items: center; gap: .25rem; text-align: center; }
        .rc-readiness-ring strong { font-size: 1.75rem; line-height: 1; color: #0f172a; font-weight: 500; }
        .dark .rc-readiness-ring strong { color: #fff; }
        .rc-readiness-ring span { color: #7c8799; font-size: .75rem; }
        .rc-check-list { display: grid; gap: .78rem; }
        .rc-check-row { display: grid; grid-template-columns: 1.35rem minmax(0,1fr); gap: .65rem; align-items: start; }
        .rc-check-dot { width: 1.05rem; height: 1.05rem; border-radius: 999px; border: 2px solid #94a3b8; display: grid; place-items: center; color: #10b981; font-size: .72rem; font-weight: 500; }
        .rc-check-row.is-done .rc-check-dot { border-color: #10b981; }
        .rc-check-row strong { display: block; color: #0f172a; font-size: .82rem; line-height: 1.2; }
        .dark .rc-check-row strong { color: #fff; }
        .rc-check-row small { display: block; color: #7c8799; font-size: .78rem; margin-top: .15rem; }
        .rc-home-outline-btn { width: 100%; margin-top: .25rem; }
        .rc-home-activity-list { display: grid; gap: .78rem; max-height: 20rem; overflow: auto; padding-right: .25rem; }
        .rc-home-activity { display: grid; grid-template-columns: 2.35rem minmax(0,1fr) auto; gap: .6rem; align-items: center; text-decoration: none; color: inherit; }
        .rc-home-activity-icon { width: 2.05rem; height: 2.05rem; font-size: .8rem; background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-home-activity-icon.is-coral { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-home-activity-icon.is-green { background: rgba(16,185,129,.13); color: #10b981; }
        .rc-home-activity-copy { min-width: 0; display: grid; gap: .12rem; }
        .rc-home-activity-copy strong { color: #0f172a; font-size: .84rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dark .rc-home-activity-copy strong { color: #fff; }
        .rc-home-activity-copy small { color: #7c8799; font-size: .76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rc-home-activity-time { color: #94a3b8; font-size: .74rem; white-space: nowrap; }
        .rc-radar-panel { grid-column: 1; }
        .rc-radar-schools { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .6rem; }
        .rc-radar-card {
            border: 1px solid #e7eaf0;
            background: #fff;
            border-radius: .9rem;
            overflow: hidden;
            padding: 0 0 .8rem;
            text-align: left;
            display: grid;
            gap: .28rem;
            color: #0f172a;
        }
        .dark .rc-radar-card { border-color: rgba(148,163,184,.16); background: rgba(17,24,39,.72); color: #fff; }
        .rc-radar-logo { height: 5.25rem; display: flex; align-items: center; justify-content: center; background: #fff; color: #0f172a; font-weight: 500; font-size: 1.15rem; overflow: hidden; padding: .75rem; box-sizing: border-box; }
        .rc-radar-logo img { width: auto !important; height: auto !important; max-width: 100% !important; max-height: 100% !important; object-fit: contain !important; object-position: center; display: block; padding: 0 !important; }
        .dark .rc-radar-logo { background: #fff; color: #111827; }
        .rc-radar-card strong, .rc-radar-card small, .rc-radar-card em { margin-inline: .8rem; }
        .rc-radar-card strong { font-size: .84rem; line-height: 1.15; }
        .rc-radar-card small { color: #7c8799; font-size: .73rem; }
        .rc-radar-card em { width: max-content; border-radius: 999px; background: rgba(16,185,129,.12); color: #059669; padding: .22rem .48rem; font-size: .7rem; font-style: normal; font-weight: 650; margin-top: .25rem; }
        .rc-interested-list { display: grid; gap: .6rem; }
        .rc-interested-row { display: grid; grid-template-columns: 1.1rem 2.35rem minmax(0,1fr) auto; gap: .6rem; align-items: center; border: 0; background: transparent; text-align: left; color: inherit; padding: 0; }
        .rc-interested-rank { color: #94a3b8; font-weight: 650; font-size: .82rem; }
        .rc-interested-logo { width: 2.35rem; height: 2.35rem; border-radius: .55rem; display: grid; place-items: center; background: #fff; color: #111827; border: 1px solid #e5e7eb; font-size: .72rem; font-weight: 500; }
        .rc-interested-row strong { display: block; color: #0f172a; font-size: .84rem; line-height: 1.2; }
        .dark .rc-interested-row strong { color: #fff; }
        .rc-interested-row small { color: #7c8799; font-size: .73rem; }
        .rc-interested-row b { color: #ff6338; font-weight: 500; }
        .rc-home-empty { color: #7c8799; font-size: .82rem; padding: .82rem; }
        .dark .rc-home-empty { color: #94a3b8; }
        @media (max-width: 1180px) {
            .rc-home-stats { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .rc-home-main-grid, .rc-home-lower-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .rc-home-topbar { display: grid; }
            .rc-home-search { min-width: 0; width: 100%; }
            .rc-home-actions { justify-content: stretch; }
            .rc-home-stats, .rc-radar-schools { grid-template-columns: 1fr; }
            .rc-progress-layout { grid-template-columns: 1fr; }
        }



        /* FINAL v90 light recruiting dashboard */
        .rc-home-dashboard-v2 {
            display: grid;
            gap: 1.25rem;
            padding: .3rem 0 2rem;
            color: #101827;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
        }

        .rc-home-dashboard-v2 * { box-sizing: border-box; }

        .rc-home-header-v2 {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.1rem;
        }

        .rc-home-header-v2 h1 {
            margin: 0;
            white-space: nowrap;
            color: #101827;
            font-size: clamp(1.45rem, 2vw, 1.85rem);
            line-height: 1.05;
            font-weight: 650;
            letter-spacing: -.035em;
        }

        .rc-home-header-v2 p {
            margin: .5rem 0 0;
            color: #7d8798;
            font-size: .88rem;
        }

        .rc-home-actions-v2 {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: .72rem;
        }

        .rc-home-search-v2 {
            width: min(28rem, 42vw);
            height: 2.75rem;
            display: flex;
            align-items: center;
            gap: .55rem;
            border: 1px solid #e8ebf0;
            border-radius: .82rem;
            background: rgba(255,255,255,.94);
            padding: 0 .72rem;
            color: #9aa4b5;
            box-shadow: 0 8px 20px rgba(15,23,42,.07);
        }

        .rc-home-search-v2 svg {
            width: 1.05rem;
            height: 1.05rem;
            flex: 0 0 auto;
        }

        .rc-home-search-v2 input {
            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
            color: #475569;
            min-width: 0;
            flex: 1;
            font-size: .84rem;
        }

        .rc-home-search-v2 kbd {
            border: 1px solid #e5e7eb;
            border-radius: .42rem;
            color: #94a3b8;
            font-size: .68rem;
            font-weight: 650;
            padding: .08rem .35rem;
        }

        .rc-home-new-email-v2 {
            height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            border: 0;
            border-radius: .85rem;
            background: #ff6338;
            color: #fff !important;
            padding: 0 1.15rem;
            font-size: .88rem;
            font-weight: 750;
            text-decoration: none;
            box-shadow: 0 12px 22px rgba(255,99,56,.24);
        }

        .rc-home-new-email-v2 span {
            font-size: 1.15rem;
            line-height: 1;
            font-weight: 500;
        }

        .rc-home-stats-v2 {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
        }

        .rc-home-stat-v2,
        .rc-home-panel-v2 {
            border: 1px solid #e8ebf0;
            background: rgba(255,255,255,.96);
            border-radius: 1.05rem;
            box-shadow: 0 8px 22px rgba(15,23,42,.07);
        }

        .rc-home-stat-v2 {
            min-height: 7.75rem;
            padding: 1.05rem;
            display: grid;
            grid-template-columns: 2.65rem minmax(0,1fr);
            gap: .45rem .85rem;
            align-content: start;
        }

        .rc-home-stat-icon-v2 {
            width: 2.65rem;
            height: 2.65rem;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            flex: 0 0 auto;
        }

        .rc-home-stat-icon-v2 svg {
            width: 1.22rem;
            height: 1.22rem;
        }

        .rc-home-stat-v2.is-coral .rc-home-stat-icon-v2 { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-home-stat-v2.is-blue .rc-home-stat-icon-v2 { background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-home-stat-v2.is-gold .rc-home-stat-icon-v2 { background: rgba(245,158,11,.14); color: #f59e0b; }
        .rc-home-stat-v2.is-green .rc-home-stat-icon-v2 { background: rgba(16,185,129,.13); color: #10b981; }
        .rc-home-stat-v2.is-indigo .rc-home-stat-icon-v2 { background: rgba(96,165,250,.14); color: #60a5fa; }

        .rc-home-stat-copy-v2 {
            min-width: 0;
            align-self: center;
        }

        .rc-home-stat-label-v2 {
            color: #7d8798;
            font-size: .78rem;
            line-height: 1.1;
            font-weight: 750;
        }

        .rc-home-stat-value-v2 {
            margin-top: .18rem;
            color: #0f172a;
            font-size: 1.65rem;
            line-height: .95;
            font-weight: 650;
            letter-spacing: -.04em;
        }

        .rc-home-progress-v2 {
            grid-column: 1 / -1;
            height: .42rem;
            border-radius: 999px;
            background: #edf0f5;
            overflow: hidden;
            margin-top: .15rem;
        }

        .rc-home-progress-v2 span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #ff6338;
        }

        .rc-home-stat-sub-v2 {
            grid-column: 1 / -1;
            color: #7d8798;
            font-size: .76rem;
            line-height: 1.25;
        }

        .rc-home-stat-v2.is-blue .rc-home-stat-sub-v2,
        .rc-home-stat-v2.is-green .rc-home-stat-sub-v2 {
            color: #059669;
            font-weight: 750;
        }

        .rc-home-grid-v2,
        .rc-home-lower-grid-v2 {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, .82fr);
            gap: 1rem;
        }

        .rc-home-panel-v2 { padding: 1.2rem; }

        .rc-home-panel-head-v2 {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.05rem;
        }

        .rc-home-panel-head-v2 h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1rem;
            line-height: 1.15;
            font-weight: 650;
            letter-spacing: -.02em;
        }

        .rc-home-panel-head-v2 p {
            margin: .35rem 0 0;
            color: #7d8798;
            font-size: .78rem;
        }

        .rc-home-panel-head-v2 a,
        .rc-home-panel-head-v2 span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e8ebf0;
            border-radius: .75rem;
            min-height: 2.15rem;
            padding: .45rem .75rem;
            background: #fff;
            color: #0f172a;
            font-size: .78rem;
            font-weight: 500;
            text-decoration: none;
        }

        .rc-home-progress-layout-v2 {
            display: grid;
            grid-template-columns: 12rem minmax(0,1fr);
            gap: 1.35rem;
            align-items: center;
        }

        .rc-readiness-ring-v2 {
            width: 9.7rem;
            height: 9.7rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            margin-inline: auto;
            background: conic-gradient(#ff6338 calc(var(--ready) * 1%), #edf0f5 0);
            position: relative;
        }

        .rc-readiness-ring-v2:before {
            content: "";
            position: absolute;
            inset: 1rem;
            border-radius: inherit;
            background: #fff;
        }

        .rc-readiness-ring-v2 div {
            position: relative;
            display: grid;
            justify-items: center;
            gap: .25rem;
            text-align: center;
        }

        .rc-readiness-ring-v2 strong {
            color: #0f172a;
            font-size: 1.75rem;
            line-height: 1;
            font-weight: 650;
        }

        .rc-readiness-ring-v2 span {
            color: #7d8798;
            font-size: .75rem;
        }

        .rc-check-list-v2 { display: grid; gap: .78rem; }

        .rc-check-row-v2 {
            display: grid;
            grid-template-columns: 1.35rem minmax(0,1fr);
            align-items: start;
            gap: .65rem;
        }

        .rc-check-dot-v2 {
            width: 1.05rem;
            height: 1.05rem;
            border-radius: 999px;
            border: 2px solid #94a3b8;
            display: grid;
            place-items: center;
            color: #10b981;
            font-size: .72rem;
            line-height: 1;
            font-weight: 650;
        }

        .rc-check-row-v2.is-done .rc-check-dot-v2 { border-color: #10b981; }

        .rc-check-row-v2 strong {
            display: block;
            color: #0f172a;
            font-size: .82rem;
            line-height: 1.15;
            font-weight: 780;
        }

        .rc-check-row-v2 small {
            display: block;
            color: #7d8798;
            font-size: .78rem;
            margin-top: .15rem;
        }

        .rc-home-outline-btn-v2 {
            width: 100%;
            min-height: 2.35rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e8ebf0;
            border-radius: .72rem;
            background: #fff;
            color: #0f172a;
            font-size: .78rem;
            font-weight: 720;
            text-decoration: none;
            margin-top: .25rem;
        }

        .rc-profile-milestones-v2 {
            display: flex;
            flex-wrap: wrap;
            gap: .42rem;
            margin-top: .2rem;
        }

        .rc-profile-milestones-v2 span {
            display: inline-flex;
            align-items: center;
            min-height: 1.7rem;
            border-radius: 999px;
            border: 1px solid #e8ebf0;
            background: #f8fafc;
            color: #7d8798;
            padding: .28rem .55rem;
            font-size: .68rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .rc-profile-milestones-v2 span.is-unlocked {
            border-color: rgba(255, 99, 56, .24);
            background: rgba(255, 99, 56, .1);
            color: #ff6338;
        }

        .dark .rc-profile-milestones-v2 span {
            border-color: rgba(148, 163, 184, .16);
            background: rgba(148, 163, 184, .08);
            color: #94a3b8;
        }

        .dark .rc-profile-milestones-v2 span.is-unlocked {
            border-color: rgba(255, 99, 56, .28);
            background: rgba(255, 99, 56, .12);
            color: #ff8a70;
        }

        .rc-home-activity-list-v2 {
            display: grid;
            gap: .82rem;
            max-height: 20rem;
            overflow: auto;
            padding-right: .25rem;
        }

        .rc-home-activity-v2 {
            display: grid;
            grid-template-columns: 2.35rem minmax(0,1fr) auto;
            gap: .6rem;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }

        .rc-home-activity-icon-v2 {
            width: 2.05rem;
            height: 2.05rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-size: .78rem;
            font-weight: 600;
        }

        .rc-home-activity-icon-v2.is-blue { background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-home-activity-icon-v2.is-coral { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-home-activity-icon-v2.is-gold { background: rgba(245,158,11,.14); color: #f59e0b; }
        .rc-home-activity-icon-v2.is-green { background: rgba(16,185,129,.13); color: #10b981; }
        .rc-home-activity-icon-v2.is-purple { background: rgba(139,92,246,.13); color: #8b5cf6; }

        .rc-home-activity-copy-v2 { display: grid; gap: .12rem; min-width: 0; }

        .rc-home-activity-copy-v2 strong {
            color: #0f172a;
            font-size: .84rem;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 600;
        }

        .rc-home-activity-copy-v2 small {
            color: #7d8798;
            font-size: .76rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-home-activity-time-v2 {
            color: #94a3b8;
            font-size: .74rem;
            white-space: nowrap;
        }

        .rc-radar-panel-v2 { grid-column: 1; }

        .rc-radar-schools-v2 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: .6rem;
        }

        .rc-radar-card-v2 {
            border: 1px solid #e8ebf0;
            background: #fff;
            border-radius: .9rem;
            overflow: hidden;
            padding: 0 0 .8rem;
            text-align: left;
            display: grid;
            gap: .28rem;
            color: #0f172a;
            cursor: pointer;
        }

        .rc-radar-logo-v2 {
            height: 5.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            color: #111827;
            font-weight: 500;
            font-size: 1.15rem;
            overflow: hidden;
            border-bottom: 1px solid #eef2f7;
            padding: .75rem;
            box-sizing: border-box;
        }

        .rc-radar-logo-v2 img {
            width: auto !important;
            height: auto !important;
            max-width: 100% !important;
            max-height: 100% !important;
            display: block;
            object-fit: contain !important;
            object-position: center;
            padding: 0 !important;
            box-sizing: border-box;
            flex: 0 1 auto;
        }

        .rc-radar-card-v2 strong,
        .rc-radar-card-v2 small,
        .rc-radar-card-v2 em { margin-inline: .8rem; }

        .rc-radar-card-v2 strong {
            color: #0f172a;
            font-size: .84rem;
            line-height: 1.15;
            font-weight: 600;
        }

        .rc-radar-card-v2 small { color: #7d8798; font-size: .73rem; }

        .rc-radar-card-v2 em {
            width: max-content;
            border-radius: 999px;
            background: rgba(16,185,129,.12);
            color: #059669;
            padding: .22rem .48rem;
            font-size: .7rem;
            font-style: normal;
            font-weight: 650;
            margin-top: .25rem;
        }

        .rc-home-dots-v2 {
            display: flex;
            justify-content: center;
            gap: .32rem;
            margin-top: .8rem;
        }

        .rc-home-dots-v2 span {
            width: .35rem;
            height: .35rem;
            border-radius: 999px;
            background: #d9dde5;
        }

        .rc-home-dots-v2 span:first-child {
            width: .75rem;
            background: #ff6338;
        }

        .rc-interested-list-v2 { display: grid; gap: .6rem; }

        .rc-interested-row-v2 {
            display: grid;
            grid-template-columns: 1.1rem 2.35rem minmax(0,1fr) auto;
            align-items: center;
            gap: .6rem;
            border: 0;
            background: transparent;
            text-align: left;
            color: inherit;
            padding: 0;
            cursor: pointer;
        }

        .rc-interested-rank-v2 {
            color: #94a3b8;
            font-weight: 650;
            font-size: .82rem;
        }

        .rc-interested-logo-v2 {
            width: 2.35rem;
            height: 2.35rem;
            border-radius: .55rem;
            display: grid;
            place-items: center;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #111827;
            font-size: .72rem;
            font-weight: 650;
            overflow: hidden;
            flex: 0 0 auto;
        }

        .rc-interested-logo-v2 img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            padding: .18rem;
        }

        .rc-interested-row-v2 strong {
            display: block;
            color: #0f172a;
            font-size: .84rem;
            line-height: 1.2;
            font-weight: 600;
        }

        .rc-interested-row-v2 small { color: #7d8798; font-size: .73rem; }
        .rc-interested-row-v2 b { color: #ff6338; font-weight: 650; }
        .rc-home-empty-v2 { color: #7d8798; font-size: .82rem; padding: .82rem; }

        .dark .rc-home-dashboard-v2 { color: #f8fafc; }

        .dark .rc-home-header-v2 h1,
        .dark .rc-home-panel-head-v2 h2,
        .dark .rc-home-stat-value-v2,
        .dark .rc-check-row-v2 strong,
        .dark .rc-readiness-ring-v2 strong,
        .dark .rc-home-activity-copy-v2 strong,
        .dark .rc-interested-row-v2 strong,
        .dark .rc-radar-card-v2 strong { color: #fff; }

        .dark .rc-home-stat-v2,
        .dark .rc-home-panel-v2,
        .dark .rc-home-search-v2,
        .dark .rc-home-panel-head-v2 a,
        .dark .rc-home-outline-btn-v2 {
            border-color: rgba(148,163,184,.16);
            background: rgba(24,29,39,.88);
            box-shadow: none;
            color: #e5e7eb;
        }

        .dark .rc-readiness-ring-v2:before { background: #181d27; }

        .dark .rc-radar-card-v2 {
            border-color: rgba(148,163,184,.16);
            background: rgba(17,24,39,.72);
        }

        .dark .rc-radar-logo-v2 {
            background: #fff;
            color: #111827;
        }

        @media (max-width: 1180px) {
            .rc-home-stats-v2 { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .rc-home-grid-v2,
            .rc-home-lower-grid-v2 { grid-template-columns: 1fr; }
        }

        @media (max-width: 760px) {
            .rc-home-welcome-copy-v2 h1,
            .rc-home-welcome-copy-v2 p { white-space: normal !important; }
            .rc-home-header-v2 { display: grid; }
            .rc-home-actions-v2 { justify-content: stretch; }
            .rc-home-search-v2 { width: 100%; }
            .rc-home-stats-v2,
            .rc-radar-schools-v2 { grid-template-columns: 1fr; }
            .rc-home-progress-layout-v2 { grid-template-columns: 1fr; }
        }


        /* Dashboard functional-card + detail page fixes */
        .rc-home-header-v2 {
            display: grid !important;
            grid-template-columns: minmax(34rem, 1fr) minmax(34rem, auto) !important;
            align-items: start !important;
            column-gap: 1rem !important;
        }

        .rc-home-welcome-copy-v2 {
            min-width: 0 !important;
            max-width: none !important;
        }

        .rc-home-welcome-copy-v2 h1,
        .rc-home-welcome-copy-v2 p {
            white-space: nowrap !important;
            max-width: none !important;
        }

        .rc-home-welcome-copy-v2 p {
            overflow: visible !important;
        }

        .rc-home-actions-v2 {
            display: grid !important;
            grid-template-columns: minmax(21rem, 27rem) auto !important;
            align-items: center !important;
            justify-content: end !important;
            gap: .75rem !important;
            width: auto !important;
        }

        .rc-home-search-v2 {
            width: 100% !important;
            min-width: 0 !important;
        }

        .rc-home-new-email-v2 {
            width: auto !important;
            min-width: 7.6rem !important;
            max-width: 8.8rem !important;
            padding-inline: 1rem !important;
            white-space: nowrap !important;
        }



        /* Header action fix: search + dark mode row, New Email on the next line. */
        .rc-home-header-v2 {
            align-items: start !important;
        }

        .rc-home-actions-v2 {
            display: grid !important;
            grid-template-columns: minmax(22rem, 30rem) auto auto !important;
            grid-template-areas:
                "search refresh dark"
                ". email email" !important;
            align-items: center !important;
            justify-content: end !important;
            gap: .75rem !important;
            width: auto !important;
        }

        .rc-home-search-v2 {
            grid-area: search !important;
            width: 100% !important;
            min-width: 0 !important;
        }

        .rc-home-dark-toggle-v2 {
            grid-area: dark !important;
            width: 2.75rem !important;
            min-width: 2.75rem !important;
            max-width: 2.75rem !important;
            height: 2.75rem !important;
            min-height: 2.75rem !important;
            max-height: 2.75rem !important;
            aspect-ratio: 1 / 1 !important;
            flex: 0 0 2.75rem !important;
            padding: 0 !important;
            display: inline-grid !important;
            place-items: center !important;
            border: 1px solid #e5e7eb !important;
            border-radius: .85rem !important;
            background: rgba(255,255,255,.94) !important;
            color: #0f172a !important;
            box-shadow: 0 8px 24px rgba(15,23,42,.08) !important;
            cursor: pointer !important;
            transition: transform .18s ease, border-color .18s ease, background .18s ease !important;
        }

        .rc-home-dark-toggle-v2:hover {
            transform: translateY(-1px) !important;
            border-color: rgba(255, 99, 56, .35) !important;
        }

        .rc-home-dark-toggle-v2 svg {
            width: 1.1rem !important;
            height: 1.1rem !important;
        }

        .rc-home-dark-toggle-v2 .rc-dark-icon-sun {
            display: none !important;
        }

        .dark .rc-home-dark-toggle-v2 {
            border-color: rgba(148,163,184,.18) !important;
            background: rgba(17,24,39,.82) !important;
            color: #f8fafc !important;
            box-shadow: none !important;
        }

        .dark .rc-home-dark-toggle-v2 .rc-dark-icon-moon {
            display: none !important;
        }

        .dark .rc-home-dark-toggle-v2 .rc-dark-icon-sun {
            display: block !important;
        }



        .rc-home-refresh-v2 {
            grid-area: refresh !important;
            width: 3rem !important;
            min-width: 3rem !important;
            max-width: 3rem !important;
            height: 3rem !important;
            min-height: 3rem !important;
            max-height: 3rem !important;
            aspect-ratio: 1 / 1 !important;
            padding: 0 !important;
            border-radius: .95rem !important;
            display: inline-grid !important;
            place-items: center !important;
            justify-self: end !important;
            flex: 0 0 3rem !important;
            box-sizing: border-box !important;
            border: 1px solid #e5e7eb !important;
            background: rgba(255,255,255,.94) !important;
            color: #0f172a !important;
            box-shadow: 0 8px 24px rgba(15,23,42,.08) !important;
            cursor: pointer !important;
            transition: transform .18s ease, border-color .18s ease, background .18s ease, opacity .18s ease !important;
        }

        .rc-home-refresh-v2:hover {
            transform: translateY(-1px) !important;
            border-color: rgba(255, 99, 56, .35) !important;
        }

        .rc-home-refresh-v2 svg {
            width: 1.12rem !important;
            height: 1.12rem !important;
        }

        .rc-home-refresh-v2[disabled] {
            opacity: .62 !important;
            cursor: wait !important;
            transform: none !important;
        }

        .rc-home-refresh-v2[disabled] svg {
            animation: rcSpin .75s linear infinite;
        }

        .dark .rc-home-refresh-v2 {
            border-color: rgba(148,163,184,.18) !important;
            background: rgba(17,24,39,.82) !important;
            color: #f8fafc !important;
            box-shadow: none !important;
        }


        .rc-refresh-dropdown-v2 {
            position: relative !important;
            grid-area: refresh !important;
            justify-self: end !important;
            flex: 0 0 auto !important;
            z-index: 35 !important;
        }

        .rc-refresh-menu-v2 {
            position: absolute !important;
            top: calc(100% + .55rem) !important;
            right: 0 !important;
            width: min(18rem, 86vw) !important;
            border: 1px solid rgba(226,232,240,.95) !important;
            border-radius: 1rem !important;
            background: rgba(255,255,255,.98) !important;
            color: #0f172a !important;
            box-shadow: 0 18px 46px rgba(15,23,42,.16) !important;
            padding: .42rem !important;
            z-index: 80 !important;
        }

        .rc-refresh-menu-item-v2 {
            width: 100% !important;
            border: 0 !important;
            background: transparent !important;
            color: inherit !important;
            display: grid !important;
            grid-template-columns: 2.15rem minmax(0,1fr) !important;
            gap: .65rem !important;
            align-items: center !important;
            text-align: left !important;
            padding: .68rem .7rem !important;
            border-radius: .78rem !important;
            cursor: pointer !important;
        }

        .rc-refresh-menu-item-v2:hover {
            background: rgba(255,99,56,.09) !important;
        }

        .rc-refresh-menu-item-v2 svg {
            width: 1rem !important;
            height: 1rem !important;
        }

        .rc-refresh-menu-icon-v2 {
            width: 2.15rem !important;
            height: 2.15rem !important;
            border-radius: .72rem !important;
            display: inline-grid !important;
            place-items: center !important;
            background: rgba(255,99,56,.1) !important;
            color: #ff6338 !important;
        }

        .rc-refresh-menu-copy-v2 {
            min-width: 0 !important;
            display: grid !important;
            gap: .12rem !important;
        }

        .rc-refresh-menu-copy-v2 strong {
            font-size: .82rem !important;
            line-height: 1.2 !important;
            font-weight: 800 !important;
            color: inherit !important;
        }

        .rc-refresh-menu-copy-v2 small {
            font-size: .72rem !important;
            line-height: 1.3 !important;
            color: #64748b !important;
        }

        .dark .rc-refresh-menu-v2 {
            border-color: rgba(148,163,184,.18) !important;
            background: rgba(15,23,42,.98) !important;
            color: #f8fafc !important;
            box-shadow: 0 18px 46px rgba(0,0,0,.32) !important;
        }

        .dark .rc-refresh-menu-item-v2:hover {
            background: rgba(255,99,56,.15) !important;
        }

        .dark .rc-refresh-menu-copy-v2 small {
            color: rgba(203,213,225,.72) !important;
        }

        .rc-home-new-email-v2 {
            grid-area: email !important;
            justify-self: end !important;
            width: auto !important;
            min-width: 8.4rem !important;
            max-width: none !important;
            padding-inline: 1rem !important;
            white-space: nowrap !important;
        }

        @media (max-width: 760px) {
            .rc-home-actions-v2 {
                grid-template-columns: 1fr auto !important;
                grid-template-areas:
                    "search dark"
                    "email email" !important;
                justify-content: stretch !important;
                width: 100% !important;
            }

            .rc-home-new-email-v2 {
                justify-self: stretch !important;
                width: 100% !important;
            }
        }


        .rc-home-stat-v2 {
            border: 1px solid #e8ebf0;
            text-align: left;
            color: inherit;
        }

        button.rc-home-stat-v2 {
            cursor: default;
            appearance: none;
        }

        .rc-home-stat-v2.is-clickable {
            cursor: pointer;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .rc-home-stat-v2.is-clickable:hover {
            border-color: #ff6338 !important;
            box-shadow: 0 0 0 3px rgba(255, 99, 56, .12), 0 12px 28px rgba(15, 23, 42, .08) !important;
            transform: translateY(-1px);
        }

        .rc-detail-page-v2 {
            display: grid;
            gap: 1rem;
            color: #101827;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
        }

        .rc-detail-header-v2 {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(22rem, 28rem);
            gap: 1rem;
            align-items: start;
        }

        .rc-detail-header-v2 h1 {
            margin: 0;
            color: #0f172a;
            font-size: 1.8rem;
            line-height: 1.1;
            font-weight: 650;
            letter-spacing: -.035em;
        }

        .rc-detail-header-v2 p {
            margin: .45rem 0 0;
            color: #7d8798;
            font-size: .95rem;
        }

        .rc-detail-search-v2 {
            height: 2.8rem;
            display: flex;
            align-items: center;
            gap: .55rem;
            border: 1px solid #e8ebf0;
            border-radius: .85rem;
            background: #fff;
            padding: 0 .8rem;
            color: #94a3b8;
            box-shadow: 0 8px 20px rgba(15,23,42,.06);
        }

        .rc-detail-search-v2 svg { width: 1rem; height: 1rem; flex: 0 0 auto; }
        .rc-detail-search-v2 input {
            min-width: 0;
            flex: 1;
            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
            font-size: .85rem;
            color: #475569;
        }

        .rc-detail-stats-v2 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .rc-detail-stat-v2,
        .rc-detail-table-v2 {
            border: 1px solid #e8ebf0;
            background: rgba(255,255,255,.96);
            border-radius: 1.05rem;
            box-shadow: 0 8px 22px rgba(15,23,42,.06);
        }

        .rc-detail-stat-v2 {
            min-height: 7.5rem;
            padding: 1.05rem;
            display: grid;
            grid-template-columns: 3rem minmax(0,1fr);
            gap: .8rem;
            align-items: start;
        }

        .rc-detail-stat-v2 > span {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: .85rem;
            display: grid;
            place-items: center;
            font-weight: 650;
        }

        .rc-detail-stat-v2 small {
            display: block;
            color: #64748b;
            font-size: .84rem;
            font-weight: 500;
        }

        .rc-detail-stat-v2 strong {
            display: block;
            margin-top: .2rem;
            color: #0f172a;
            font-size: 2rem;
            line-height: 1;
            font-weight: 650;
            letter-spacing: -.04em;
        }

        .rc-detail-stat-v2 em {
            display: block;
            margin-top: .55rem;
            color: #7d8798;
            font-size: .82rem;
            font-style: normal;
        }

        .rc-detail-stat-v2.is-blue > span { background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-detail-stat-v2.is-coral > span { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-detail-stat-v2.is-purple > span { background: rgba(139,92,246,.13); color: #8b5cf6; }
        .rc-detail-stat-v2.is-green > span { background: rgba(16,185,129,.13); color: #10b981; }
        .rc-detail-stat-v2.is-neutral > span { background: #eceef3; color: #111827; }
        .rc-detail-stat-v2.is-pink > span { background: rgba(236,72,153,.14); color: #ec4899; }
        .rc-detail-stat-v2.is-red > span { background: rgba(239,68,68,.13); color: #ef4444; }

        .rc-detail-table-v2 { overflow: hidden; }
        .rc-detail-table-v2 header {
            min-height: 3.55rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0 1.15rem;
            border-bottom: 1px solid #edf0f5;
        }

        .rc-detail-table-v2 h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 650;
        }

        .rc-detail-table-v2 header span {
            color: #10b981;
            font-size: .78rem;
            font-weight: 750;
        }

        .rc-detail-rows-v2 { display: grid; }
        .rc-detail-row-v2 {
            width: 100%;
            min-height: 4.35rem;
            display: grid;
            grid-template-columns: 2rem 2.45rem minmax(0, 1fr) auto 3rem 4.75rem 1rem;
            align-items: center;
            gap: .6rem;
            border: 0;
            border-bottom: 1px solid #f0f2f6;
            background: transparent;
            padding: .65rem 1.15rem;
            text-align: left;
            color: inherit;
            cursor: pointer;
        }

        .rc-detail-row-v2:hover { background: #fafafa; }
        .rc-detail-row-v2:last-child { border-bottom: 0; }
        .rc-detail-rank-v2 { color: #94a3b8; font-size: .8rem; font-weight: 600; }
        .rc-detail-avatar-v2,
        .rc-detail-platform-icon-v2 {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: .7rem;
            display: grid;
            place-items: center;
            background: #f1f3f7;
            color: #111827;
            font-size: .82rem;
            font-weight: 600;
            overflow: hidden;
        }

        .rc-detail-avatar-v2 img { width: 100%; height: 100%; object-fit: contain; }
        .rc-detail-platform-icon-v2.is-red { background: rgba(239,68,68,.12); color: #ef4444; }
        .rc-detail-platform-icon-v2.is-pink { background: rgba(236,72,153,.14); color: #ec4899; }
        .rc-detail-platform-icon-v2.is-neutral { background: #eceef3; color: #111827; }

        .rc-detail-person-v2 { min-width: 0; display: grid; gap: .15rem; }
        .rc-detail-person-v2 strong {
            color: #0f172a;
            font-size: .88rem;
            line-height: 1.2;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-detail-person-v2 strong em {
            margin-left: .35rem;
            border-radius: .35rem;
            background: rgba(255,99,56,.13);
            color: #ff6338;
            padding: .08rem .28rem;
            font-size: .62rem;
            font-style: normal;
            font-weight: 600;
            vertical-align: middle;
        }

        .rc-detail-person-v2 small {
            color: #7d8798;
            font-size: .78rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-detail-pill-v2 {
            border-radius: 999px;
            background: rgba(255,99,56,.12);
            color: #ff6338;
            padding: .28rem .55rem;
            font-size: .72rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .rc-detail-pill-v2.is-pink { background: rgba(236,72,153,.14); color: #ec4899; }
        .rc-detail-pill-v2.is-red { background: rgba(239,68,68,.13); color: #ef4444; }
        .rc-detail-pill-v2.is-neutral { background: #eceef3; color: #111827; }

        .rc-detail-count-v2 { display: grid; justify-items: center; color: #7d8798; }
        .rc-detail-count-v2 b { color: #ff6338; font-size: 1.1rem; line-height: 1; font-weight: 650; }
        .rc-detail-count-v2 small { font-size: .68rem; }
        .rc-detail-time-v2 { color: #94a3b8; font-size: .78rem; white-space: nowrap; }
        .rc-detail-chevron-v2 { color: #94a3b8; font-size: 1.35rem; }


        .rc-stats-drawer-backdrop {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: flex;
            justify-content: flex-end;
            background: rgba(15, 23, 42, .28);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            animation: rcDrawerBackdropIn .18s ease both;
        }

        .dark .rc-stats-drawer-backdrop {
            background: rgba(2, 6, 23, .52);
        }

        .rc-stats-drawer-panel {
            width: min(760px, calc(100vw - 1.25rem));
            height: 100vh;
            overflow-y: auto;
            background: var(--rc-bg);
            border-left: 1px solid var(--rc-border);
            box-shadow: -24px 0 70px rgba(15, 23, 42, .18);
            padding: 1.2rem;
            animation: rcStatsDrawerIn .24s cubic-bezier(.22, 1, .36, 1) both;
        }

        .dark .rc-stats-drawer-panel {
            box-shadow: -24px 0 70px rgba(0, 0, 0, .45);
        }

        .rc-stats-drawer-panel .rc-detail-page-v2 {
            max-width: none;
            margin: 0;
            padding: 0;
            min-height: auto;
        }

        .rc-stats-drawer-close {
            position: sticky;
            top: .2rem;
            z-index: 2;
            margin-left: auto;
            margin-bottom: .75rem;
            width: 2.6rem;
            height: 2.6rem;
            border-radius: .9rem;
            border: 1px solid var(--rc-border);
            background: var(--rc-surface);
            color: var(--rc-text);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            line-height: 1;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
            transition: transform .18s ease, border-color .18s ease, color .18s ease;
        }

        .rc-stats-drawer-close:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 99, 56, .35);
            color: #ff6338;
        }

        .rc-stats-drawer-panel .rc-detail-header-v2 {
            grid-template-columns: 1fr;
            gap: .6rem;
            margin-bottom: 1rem;
        }

        .rc-stats-drawer-panel .rc-detail-search-v2 {
            display: none;
        }

        .rc-stats-drawer-panel .rc-detail-stats-v2 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .rc-stats-drawer-panel .rc-detail-row-v2 {
            grid-template-columns: 2.4rem minmax(0, 1fr) auto auto;
        }

        .rc-stats-drawer-panel .rc-detail-chevron-v2 {
            display: none;
        }

        @keyframes rcStatsDrawerIn {
            from { transform: translateX(100%); opacity: .6; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes rcDrawerBackdropIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 760px) {
            .rc-stats-drawer-panel {
                width: 100vw;
                padding: .82rem;
            }

            .rc-stats-drawer-panel .rc-detail-stats-v2 {
                grid-template-columns: 1fr;
            }

            .rc-stats-drawer-panel .rc-detail-row-v2 {
                grid-template-columns: 2.35rem minmax(0, 1fr) auto;
            }

            .rc-stats-drawer-panel .rc-detail-rank-v2,
            .rc-stats-drawer-panel .rc-detail-time-v2 {
                display: none;
            }
        }

        @media (max-width: 1180px) {
            .rc-home-header-v2,
            .rc-detail-header-v2 { grid-template-columns: 1fr !important; }
            .rc-home-actions-v2 { justify-content: stretch !important; grid-template-columns: 1fr auto !important; }
            .rc-detail-stats-v2 { grid-template-columns: 1fr; }
            .rc-detail-row-v2 { grid-template-columns: 2.35rem minmax(0, 1fr) auto; }
            .rc-detail-rank-v2, .rc-detail-time-v2, .rc-detail-chevron-v2 { display: none; }
        }



        /* Coach dashboard top meta cleanup. */
        .rc-load-status { display: none !important; }
        .rc-home-dashboard-v2 .rc-top:empty { display: none !important; }

    

        /* Final header alignment: search + square dark toggle sit on the right edge. */
        .rc-home-header-v2 {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) minmax(34rem, 45rem) !important;
            column-gap: 1.25rem !important;
            align-items: start !important;
        }

        .rc-home-actions-v2 {
            justify-self: end !important;
            width: 100% !important;
            max-width: 45rem !important;
            display: grid !important;
            grid-template-columns: minmax(28rem, 1fr) 3rem 3rem !important;
            grid-template-areas:
                "search refresh dark"
                ". email email" !important;
            justify-content: end !important;
            align-items: center !important;
            gap: .75rem !important;
        }

        .rc-home-search-v2 {
            grid-area: search !important;
            width: 100% !important;
            min-width: 0 !important;
        }

        .rc-home-dark-toggle-v2,
        button.rc-home-dark-toggle-v2,
        [data-plyr-dark-toggle].rc-home-dark-toggle-v2 {
            grid-area: dark !important;
            width: 3rem !important;
            min-width: 3rem !important;
            max-width: 3rem !important;
            height: 3rem !important;
            min-height: 3rem !important;
            max-height: 3rem !important;
            aspect-ratio: 1 / 1 !important;
            padding: 0 !important;
            border-radius: .95rem !important;
            display: inline-grid !important;
            place-items: center !important;
            justify-self: end !important;
            flex: 0 0 3rem !important;
            box-sizing: border-box !important;
        }

        .rc-home-new-email-v2 {
            grid-area: email !important;
            justify-self: end !important;
            margin-top: .2rem !important;
        }

        @media (max-width: 1100px) {
            .rc-home-header-v2 {
                grid-template-columns: 1fr !important;
            }

            .rc-home-actions-v2 {
                justify-self: stretch !important;
                max-width: none !important;
                grid-template-columns: minmax(0, 1fr) 3rem !important;
            }
        }

        /* v72 stat drawer: keep background blur on page only, panel itself stays solid */
        .rc-stats-drawer-panel {
            background: #ffffff !important;
            background-color: #ffffff !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            isolation: isolate;
        }

        .dark .rc-stats-drawer-panel {
            background: #0f172a !important;
            background-color: #0f172a !important;
        }

        .rc-stats-drawer-panel .rc-detail-page-v2,
        .rc-stats-drawer-panel .rc-detail-header-v2,
        .rc-stats-drawer-panel .rc-detail-search-v2,
        .rc-stats-drawer-panel .rc-detail-stats-v2,
        .rc-stats-drawer-panel .rc-detail-list-v2,
        .rc-stats-drawer-panel .rc-detail-row-v2 {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }

        .rc-stats-drawer-panel .rc-detail-page-v2 {
            background: #ffffff !important;
        }

        .dark .rc-stats-drawer-panel .rc-detail-page-v2 {
            background: #0f172a !important;
        }

        .rc-stats-drawer-panel .rc-detail-row-v2,
        .rc-stats-drawer-panel .rc-detail-search-v2,
        .rc-stats-drawer-panel .rc-detail-stat-card-v2,
        .rc-stats-drawer-panel .rc-detail-card-v2 {
            background-color: #ffffff !important;
        }

        .dark .rc-stats-drawer-panel .rc-detail-row-v2,
        .dark .rc-stats-drawer-panel .rc-detail-search-v2,
        .dark .rc-stats-drawer-panel .rc-detail-stat-card-v2,
        .dark .rc-stats-drawer-panel .rc-detail-card-v2 {
            background-color: #111827 !important;
        }


        /* v73 stat drawer: responsive panel, proper close button, faster slide animations */
        .rc-stats-drawer-backdrop {
            align-items: stretch !important;
            padding: 0 !important;
            overflow: hidden !important;
            will-change: opacity !important;
        }

        .rc-stats-drawer-panel {
            width: min(780px, 92vw) !important;
            max-width: 100vw !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            overflow-y: auto !important;
            overscroll-behavior: contain !important;
            -webkit-overflow-scrolling: touch !important;
            transform: translateX(0);
            will-change: transform, opacity !important;
        }

        .rc-stats-drawer-panel[x-cloak],
        .rc-stats-drawer-backdrop[x-cloak] {
            display: none !important;
        }

        .rc-stats-drawer-close {
            position: sticky !important;
            top: .75rem !important;
            z-index: 20 !important;
            cursor: pointer !important;
            user-select: none !important;
            transition: transform .12s ease, background-color .12s ease, border-color .12s ease, color .12s ease !important;
        }

        .rc-stats-drawer-close:active {
            transform: scale(.94) !important;
        }

        .rc-stats-drawer-panel .rc-detail-table-v2 {
            overflow: visible !important;
        }

        .rc-stats-drawer-panel .rc-detail-rows-v2 {
            display: grid !important;
            gap: .75rem !important;
        }

        @media (max-width: 900px) {
            .rc-stats-drawer-panel {
                width: min(620px, 94vw) !important;
                padding: 1rem !important;
            }
        }

        @media (max-width: 640px) {
            .rc-stats-drawer-backdrop {
                justify-content: stretch !important;
            }

            .rc-stats-drawer-panel {
                width: 100vw !important;
                height: 100dvh !important;
                max-height: 100dvh !important;
                border-left: 0 !important;
                border-radius: 0 !important;
                padding: .9rem !important;
            }

            .rc-stats-drawer-close {
                top: .5rem !important;
                width: 2.45rem !important;
                height: 2.45rem !important;
                border-radius: .85rem !important;
            }

            .rc-stats-drawer-panel .rc-detail-header-v2 h1 {
                font-size: 1.45rem !important;
            }

            .rc-stats-drawer-panel .rc-detail-stat-v2 {
                min-width: 0 !important;
            }
        }



        /* v74 stat drawer detail layout: match reference detail pages while keeping blur slider. */
        .rc-stats-drawer-panel {
            width: min(1120px, calc(100vw - 4rem)) !important;
            padding: 2rem 2.2rem !important;
        }

        .rc-stats-drawer-panel .rc-detail-page-v2 {
            gap: 1.25rem !important;
            background: transparent !important;
        }

        .rc-stats-drawer-panel .rc-detail-header-v2 {
            display: block !important;
            margin-bottom: .55rem !important;
        }

        .rc-stats-drawer-panel .rc-detail-header-v2 h1 {
            font-size: 1.65rem !important;
            line-height: 1.12 !important;
            letter-spacing: -.04em !important;
        }

        .rc-stats-drawer-panel .rc-detail-header-v2 p {
            font-size: .95rem !important;
            color: #7b879b !important;
            margin-top: .45rem !important;
        }

        .rc-stats-drawer-panel .rc-detail-stats-v2 {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 1.05rem !important;
        }

        .rc-stats-drawer-panel .rc-detail-stat-v2 {
            min-height: 8rem !important;
            border-radius: 1.05rem !important;
            padding: 1.1rem 1.2rem !important;
            background: #ffffff !important;
            border: 1px solid #e8ebf0 !important;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .07) !important;
            grid-template-columns: 3rem minmax(0, 1fr) !important;
            align-items: start !important;
        }

        .dark .rc-stats-drawer-panel .rc-detail-stat-v2 {
            background: #111827 !important;
            border-color: rgba(148, 163, 184, .16) !important;
            box-shadow: none !important;
        }

        .rc-stats-drawer-panel .rc-detail-stat-v2 > span {
            width: 2.85rem !important;
            height: 2.85rem !important;
            border-radius: .85rem !important;
            font-size: 1.1rem !important;
        }

        .rc-stats-drawer-panel .rc-detail-stat-v2 strong {
            margin-top: .15rem !important;
            font-size: 1.85rem !important;
        }

        .rc-stats-drawer-panel .rc-detail-table-v2 {
            overflow: hidden !important;
            border-radius: 1.05rem !important;
            background: #ffffff !important;
            border: 1px solid #e8ebf0 !important;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .07) !important;
        }

        .dark .rc-stats-drawer-panel .rc-detail-table-v2 {
            background: #111827 !important;
            border-color: rgba(148, 163, 184, .16) !important;
            box-shadow: none !important;
        }

        .rc-stats-drawer-panel .rc-detail-table-v2 header {
            min-height: 3.75rem !important;
            background: inherit !important;
            padding: 0 1.25rem !important;
        }

        .rc-stats-drawer-panel .rc-detail-table-v2 header span {
            color: #10b981 !important;
            font-weight: 800 !important;
        }

        .rc-stats-drawer-panel .rc-detail-rows-v2 {
            gap: 0 !important;
        }

        .rc-stats-drawer-panel .rc-detail-row-v2 {
            min-height: 4.65rem !important;
            display: grid !important;
            grid-template-columns: 2rem 2.6rem minmax(0, 1fr) auto 4.1rem 5.3rem 1rem !important;
            gap: .85rem !important;
            padding: .72rem 1.25rem !important;
            border-bottom: 1px solid #f0f2f6 !important;
            background: #ffffff !important;
            border-radius: 0 !important;
        }

        .dark .rc-stats-drawer-panel .rc-detail-row-v2 {
            background: #111827 !important;
            border-bottom-color: rgba(148, 163, 184, .13) !important;
        }

        .rc-stats-drawer-panel .rc-detail-row-v2:hover {
            background: #fafafa !important;
        }

        .dark .rc-stats-drawer-panel .rc-detail-row-v2:hover {
            background: #0f172a !important;
        }

        .rc-stats-drawer-panel .rc-detail-chevron-v2,
        .rc-stats-drawer-panel .rc-detail-rank-v2,
        .rc-stats-drawer-panel .rc-detail-time-v2 {
            display: inline-flex !important;
            align-items: center !important;
        }

        .rc-stats-drawer-panel .rc-detail-platform-icon-v2,
        .rc-stats-drawer-panel .rc-detail-avatar-v2 {
            width: 2.45rem !important;
            height: 2.45rem !important;
            border-radius: .72rem !important;
        }

        .rc-home-stat-v2:not(.is-clickable) {
            cursor: default !important;
        }


        /* Engagement drawer rows have no rank column, so keep them aligned like the reference layout. */
        .rc-stats-drawer-panel .rc-detail-row-v2.is-engagement {
            grid-template-columns: 2.6rem minmax(0, 1fr) auto 4.1rem 5.3rem 1rem !important;
        }

        .rc-stats-drawer-panel .rc-detail-row-v2.is-engagement .rc-detail-person-v2 {
            min-width: 0 !important;
        }

        .rc-stats-drawer-panel .rc-detail-row-v2.is-engagement .rc-detail-person-v2 strong,
        .rc-stats-drawer-panel .rc-detail-row-v2.is-engagement .rc-detail-person-v2 small {
            max-width: 100% !important;
        }

        .rc-stats-drawer-panel .rc-detail-row-v2.is-engagement .rc-detail-pill-v2 {
            width: auto !important;
            max-width: max-content !important;
            justify-self: end !important;
        }

        @media (max-width: 980px) {
            .rc-stats-drawer-panel .rc-detail-row-v2.is-engagement {
                grid-template-columns: 2.45rem minmax(0, 1fr) auto !important;
            }
        }

        @media (max-width: 980px) {
            .rc-stats-drawer-panel {
                width: 100vw !important;
                padding: 1rem !important;
            }

            .rc-stats-drawer-panel .rc-detail-stats-v2 {
                grid-template-columns: 1fr !important;
            }

            .rc-stats-drawer-panel .rc-detail-row-v2 {
                grid-template-columns: 2.45rem minmax(0, 1fr) auto !important;
            }

            .rc-stats-drawer-panel .rc-detail-rank-v2,
            .rc-stats-drawer-panel .rc-detail-pill-v2,
            .rc-stats-drawer-panel .rc-detail-time-v2,
            .rc-stats-drawer-panel .rc-detail-chevron-v2 {
                display: none !important;
            }
        }



        .rc-global-search-wrapper,
        .rc-home-search-v2,
        .rc-detail-search-v2,
        .rc-discover-search {
            position: relative;
        }

        .rc-global-search-bar {
            display: grid;
            grid-template-columns: minmax(18rem, 1fr) auto auto;
            gap: .55rem;
            align-items: center;
            margin-bottom: .9rem;
        }

        .rc-global-search-shell {
            position: relative;
            display: flex;
            align-items: center;
            gap: .65rem;
            border: 1px solid var(--rc-border);
            background: var(--rc-surface);
            color: var(--rc-text);
            border-radius: .85rem;
            padding: .55rem .65rem;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .06);
        }

        .rc-global-search-shell svg {
            width: 1.08rem;
            height: 1.08rem;
            color: var(--rc-muted);
            flex: 0 0 auto;
        }

        .rc-global-search-shell input {
            width: 100%;
            border: 0 !important;
            background: transparent !important;
            color: var(--rc-text);
            box-shadow: none !important;
            outline: none !important;
            min-height: 2.35rem;
            font-size: .86rem;
        }

        .rc-global-search-clear {
            border: 0;
            background: transparent;
            color: var(--rc-muted);
            width: 1.75rem;
            height: 1.75rem;
            display: inline-grid;
            place-items: center;
            border-radius: 999px;
            font-weight: 650;
        }

        .rc-global-search-clear:hover {
            color: var(--rc-accent);
            background: var(--rc-accent-soft);
        }

        .rc-global-suggestions {
            position: absolute;
            z-index: 80;
            top: calc(100% + .5rem);
            left: 0;
            right: 0;
            min-width: min(34rem, 92vw);
            border: 1px solid var(--rc-border);
            border-radius: .85rem;
            background: var(--rc-surface);
            box-shadow: 0 24px 60px rgba(15, 23, 42, .22);
            padding: .45rem;
            display: grid;
            gap: .35rem;
            max-height: 28rem;
            overflow: auto;
        }

        .rc-global-suggestion-group {
            display: grid;
            gap: .25rem;
        }

        .rc-global-suggestion-heading {
            color: var(--rc-muted);
            font-size: .66rem;
            font-weight: 650;
            letter-spacing: .07em;
            text-transform: uppercase;
            padding: .5rem .55rem .2rem;
        }

        .rc-global-suggestion-item {
            width: 100%;
            border: 0;
            border-radius: .78rem;
            background: transparent;
            color: var(--rc-text);
            display: grid;
            grid-template-columns: 2.2rem minmax(0, 1fr) auto;
            gap: .65rem;
            align-items: center;
            text-align: left;
            padding: .52rem .55rem;
            cursor: pointer;
        }

        .rc-global-suggestion-item:hover {
            background: var(--rc-accent-soft);
        }

        .rc-global-suggestion-icon {
            width: 2.2rem;
            height: 2.2rem;
            border-radius: .68rem;
            background: #fff;
            color: var(--rc-accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 500;
            border: 1px solid rgba(148, 163, 184, .2);
            overflow: hidden;
        }

        .rc-global-suggestion-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .rc-global-suggestion-copy {
            min-width: 0;
            display: grid;
            gap: .1rem;
        }

        .rc-global-suggestion-copy strong {
            font-size: .82rem;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rc-global-suggestion-copy small {
            color: var(--rc-muted);
            font-size: .72rem;
            line-height: 1.25;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rc-global-suggestion-category {
            border-radius: 999px;
            background: var(--rc-soft);
            color: var(--rc-muted);
            padding: .22rem .5rem;
            font-size: .66rem;
            font-weight: 650;
            white-space: nowrap;
        }

        .rc-global-search-empty {
            color: var(--rc-muted);
            font-size: .78rem;
            padding: .75rem;
        }

        @media (max-width: 760px) {
            .rc-global-search-bar {
                grid-template-columns: 1fr auto auto;
            }
            .rc-global-suggestions {
                min-width: 0;
            }
        }


        /* v9 header/search refinements. Keeps the dashboard top tighter and prevents the search from dominating the header. */
        .rc-home-dashboard-v2 {
            padding-top: 0 !important;
            margin-top: -1rem !important;
        }

        .rc-home-header-v2 {
            margin-top: -.35rem !important;
            margin-bottom: .85rem !important;
            grid-template-columns: minmax(0, 1fr) minmax(28rem, 39rem) !important;
        }

        .rc-home-actions-v2 {
            max-width: 39rem !important;
            grid-template-columns: minmax(22rem, 33rem) 3rem 3rem !important;
            gap: .65rem !important;
        }

        .rc-home-search-v2,
        .rc-global-search-shell {
            max-width: 33rem !important;
        }

        .rc-global-suggestions {
            z-index: 95 !important;
        }

        @media (max-width: 1180px) {
            .rc-home-dashboard-v2 {
                margin-top: -.35rem !important;
            }

            .rc-home-header-v2 {
                grid-template-columns: 1fr !important;
                row-gap: .85rem !important;
            }

            .rc-home-actions-v2 {
                justify-self: stretch !important;
                width: 100% !important;
                max-width: none !important;
                grid-template-columns: minmax(0, 1fr) 3rem 3rem !important;
            }

            .rc-home-search-v2,
            .rc-global-search-shell {
                max-width: none !important;
            }
        }

        /* v25: right-side school drawer and Discover Schools UI matched to new reference. */
        .rc-school-modal-backdrop,
        .rc-drawer.rc-school-modal-backdrop {
            position: fixed !important;
            inset: 0 !important;
            z-index: 80 !important;
            display: flex !important;
            justify-content: flex-end !important;
            align-items: stretch !important;
            padding: 0 !important;
            background: rgba(15, 23, 42, .34) !important;
            backdrop-filter: blur(3px) !important;
        }

        .rc-school-modal-panel,
        .rc-drawer-panel.rc-school-modal-panel {
            width: min(520px, 100vw) !important;
            height: 100vh !important;
            max-height: 100vh !important;
            margin: 0 !important;
            overflow: auto !important;
            border-radius: 1.35rem 0 0 1.35rem !important;
            border: 0 !important;
            border-left: 1px solid var(--rc-border) !important;
            background: var(--rc-surface) !important;
            color: var(--rc-text) !important;
            box-shadow: -24px 0 70px rgba(15, 23, 42, .18) !important;
            padding: 1.25rem !important;
            transform: translateX(0) !important;
            animation: rcSlideInRight .22s ease-out both !important;
        }

        .dark .rc-school-modal-panel,
        .dark .rc-drawer-panel.rc-school-modal-panel {
            background: rgb(18 18 22) !important;
            color: var(--rc-text) !important;
            border-left-color: rgba(148, 163, 184, .16) !important;
            box-shadow: -28px 0 80px rgba(0, 0, 0, .45) !important;
        }

        @keyframes rcSlideInRight {
            from { transform: translateX(100%); opacity: .7; }
            to { transform: translateX(0); opacity: 1; }
        }

        .rc-school-modal-close {
            background: var(--rc-soft) !important;
            border-color: var(--rc-border) !important;
            color: var(--rc-muted) !important;
        }

        .rc-school-modal-close:hover {
            color: var(--rc-accent) !important;
            border-color: rgba(255, 99, 56, .36) !important;
            background: var(--rc-accent-soft) !important;
        }

        .rc-school-modal-main h2,
        .rc-school-section-title,
        .rc-school-coach-info strong,
        .rc-school-stat-card strong {
            color: var(--rc-text) !important;
        }

        .rc-school-modal-meta,
        .rc-school-coach-info span,
        .rc-school-stat-card small {
            color: var(--rc-muted) !important;
        }

        .rc-school-modal-rule {
            background: var(--rc-border) !important;
        }

        .rc-school-action,
        .rc-school-coach-card,
        .rc-school-stat-card,
        .rc-school-copy-btn {
            background: var(--rc-soft) !important;
            color: var(--rc-text) !important;
            border-color: var(--rc-border) !important;
        }

        .rc-school-action-primary {
            background: var(--rc-accent) !important;
            border-color: var(--rc-accent) !important;
            color: #fff !important;
        }

        .rc-school-score-ring {
            color: var(--rc-text) !important;
            background: var(--rc-surface) !important;
        }

        .rc-school-score-label { color: var(--rc-accent) !important; }

        .rc-school-division-pill,
        .rc-school-stat-card span,
        .rc-school-coach-avatar {
            background: var(--rc-accent-soft) !important;
            color: var(--rc-accent) !important;
        }



        /* v81: polished animated school drawer + list/favorite interactions */
        .rc-school-modal-backdrop {
            animation: rcBackdropInV81 .18s ease-out both !important;
        }

        @keyframes rcBackdropInV81 {
            from { background: rgba(15, 23, 42, 0); backdrop-filter: blur(0); }
            to { background: rgba(15, 23, 42, .38); backdrop-filter: blur(4px); }
        }

        .rc-school-modal-panel,
        .rc-drawer-panel.rc-school-modal-panel {
            width: min(640px, 100vw) !important;
            padding: 1.25rem !important;
            overflow-x: hidden !important;
            animation: rcSchoolDrawerInV81 .24s cubic-bezier(.2,.8,.2,1) both !important;
        }

        @keyframes rcSchoolDrawerInV81 {
            from { transform: translateX(42px); opacity: .15; }
            to { transform: translateX(0); opacity: 1; }
        }

        .rc-school-modal-close {
            position: absolute !important;
            top: .85rem !important;
            right: .85rem !important;
            width: 2.35rem !important;
            height: 2.35rem !important;
            border-radius: .8rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 8 !important;
            font-size: 0 !important;
        }

        .rc-school-modal-close::before,
        .rc-school-modal-close::after {
            content: '' !important;
            position: absolute !important;
            width: 1rem !important;
            height: 2px !important;
            background: currentColor !important;
            border-radius: 999px !important;
        }

        .rc-school-modal-close::before { transform: rotate(45deg); }
        .rc-school-modal-close::after { transform: rotate(-45deg); }

        .rc-school-modal-hero-v72 {
            display: grid !important;
            grid-template-columns: 4rem minmax(0, 1fr) 4.35rem !important;
            gap: .85rem !important;
            align-items: start !important;
            padding: .45rem 3.15rem 0 0 !important;
        }

        .rc-school-logo-large-v72 {
            width: 4rem !important;
            height: 4rem !important;
            border-radius: .9rem !important;
            background: #f3f4f6 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
            padding: .45rem !important;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .06) !important;
        }

        .rc-school-logo-large-v72 img {
            width: auto !important;
            height: auto !important;
            max-width: 100% !important;
            max-height: 100% !important;
            object-fit: contain !important;
        }

        .rc-school-logo-large-v72 span {
            color: #0f172a !important;
            font-size: .85rem !important;
            font-weight: 700 !important;
        }

        .dark .rc-school-logo-large-v72 { background: rgba(148, 163, 184, .12) !important; }
        .dark .rc-school-logo-large-v72 span { color: #e5e7eb !important; }

        .rc-school-modal-main {
            min-width: 0 !important;
            padding-top: .08rem !important;
        }

        .rc-school-modal-main h2 {
            font-size: 1.28rem !important;
            line-height: 1.08 !important;
            letter-spacing: -.035em !important;
            margin: .35rem 0 .28rem !important;
            padding-right: .35rem !important;
        }

        .rc-school-modal-meta {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: .25rem !important;
            font-size: .85rem !important;
            line-height: 1.25 !important;
        }

        .rc-school-division-pill {
            display: inline-flex !important;
            align-items: center !important;
            border-radius: .5rem !important;
            padding: .28rem .6rem !important;
            font-size: .72rem !important;
            font-weight: 700 !important;
        }

        .rc-school-score-wrap {
            align-self: start !important;
            justify-self: end !important;
            display: grid !important;
            gap: .18rem !important;
            justify-items: center !important;
            padding-top: .05rem !important;
        }

        .rc-school-score-ring {
            width: 3.8rem !important;
            height: 3.8rem !important;
            border-radius: 999px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.1rem !important;
            font-weight: 800 !important;
            border: .42rem solid #ff6338 !important;
            box-shadow: inset 0 0 0 4px var(--rc-surface), 0 8px 20px rgba(255, 99, 56, .14) !important;
        }

        .rc-school-score-label {
            font-size: .72rem !important;
            font-weight: 800 !important;
        }

        .rc-school-modal-actions-v72 {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: .55rem !important;
            align-items: center !important;
            margin-top: 1.25rem !important;
        }

        .rc-school-action {
            height: 2.85rem !important;
            border-radius: .8rem !important;
            padding: 0 .9rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: .45rem !important;
            font-size: .89rem !important;
            font-weight: 750 !important;
            border: 1px solid var(--rc-border) !important;
            transition: transform .16s ease, box-shadow .16s ease, background .16s ease, border-color .16s ease !important;
        }

        .rc-school-action:hover { transform: translateY(-1px) !important; }
        .rc-school-action svg { width: 1rem !important; height: 1rem !important; flex: 0 0 auto !important; }

        .rc-school-action.is-in-list,
        .rc-school-action.is-favorited {
            background: var(--rc-accent) !important;
            border-color: var(--rc-accent) !important;
            color: #fff !important;
            box-shadow: 0 12px 25px rgba(255, 99, 56, .22) !important;
        }

        .rc-school-action.is-loading {
            opacity: .78 !important;
            pointer-events: none !important;
        }

        .rc-action-spinner-v81 {
            width: .95rem !important;
            height: .95rem !important;
            border: 2px solid rgba(255,255,255,.5) !important;
            border-top-color: #fff !important;
            border-radius: 999px !important;
            animation: rcSpinV81 .7s linear infinite !important;
        }

        @keyframes rcSpinV81 { to { transform: rotate(360deg); } }

        .rc-school-list-dropdown-v72 { position: relative !important; }

        .rc-school-list-menu-v72 {
            width: min(29rem, calc(100vw - 2rem)) !important;
            max-height: 26rem !important;
            overflow: auto !important;
            border-radius: .95rem !important;
            border: 1px solid var(--rc-border) !important;
            box-shadow: 0 20px 45px rgba(15,23,42,.16) !important;
            padding: .8rem !important;
            animation: rcMenuInV81 .16s ease-out both !important;
        }

        @keyframes rcMenuInV81 {
            from { transform: translateY(-6px) scale(.98); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .rc-school-list-menu-v72 h4 {
            margin: 0 0 .55rem !important;
            padding: 0 .3rem !important;
            font-size: .72rem !important;
            text-transform: uppercase !important;
            letter-spacing: .08em !important;
            color: var(--rc-muted) !important;
        }

        .rc-school-list-menu-v72 button {
            width: 100% !important;
            min-height: 2.6rem !important;
            display: grid !important;
            grid-template-columns: 1.25rem minmax(13rem, 1fr) auto !important;
            gap: .65rem !important;
            align-items: center !important;
            border-radius: .75rem !important;
            padding: .45rem .5rem !important;
            color: var(--rc-text) !important;
            transition: background .15s ease, transform .15s ease !important;
        }

        .rc-school-list-menu-v72 button:hover {
            background: var(--rc-soft) !important;
            transform: translateX(2px) !important;
        }

        .rc-school-list-menu-v72 button.is-active {
            background: color-mix(in srgb, var(--list-color, #ff6338) 13%, white) !important;
        }

        .dark .rc-school-list-menu-v72 button.is-active {
            background: color-mix(in srgb, var(--list-color, #ff6338) 22%, transparent) !important;
        }

        .rc-list-check-v81 {
            width: 1.05rem !important;
            height: 1.05rem !important;
            border-radius: .34rem !important;
            border: 1.5px solid var(--rc-border) !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: transparent !important;
            background: var(--rc-surface) !important;
        }

        .rc-school-list-menu-v72 button.is-active .rc-list-check-v81 {
            border-color: var(--list-color, #ff6338) !important;
            background: var(--list-color, #ff6338) !important;
            color: #fff !important;
        }

        .rc-list-check-v81 svg { width: .75rem !important; height: .75rem !important; }

        .rc-school-list-dot-v72 {
            width: .65rem !important;
            height: .65rem !important;
            background: var(--dot, #ff6338) !important;
            border-radius: 999px !important;
            display: inline-block !important;
            margin-right: .45rem !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--dot, #ff6338) 16%, transparent) !important;
        }

        .rc-list-count-v81 {
            min-width: 1.5rem !important;
            height: 1.5rem !important;
            border-radius: 999px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 .4rem !important;
            background: var(--rc-soft) !important;
            color: var(--rc-muted) !important;
            font-size: .78rem !important;
        }


        /* v87: wider school drawer + readable colored list dropdown */
        .rc-drawer-panel.rc-school-modal-panel {
            width: min(660px, 100vw) !important;
        }

        .rc-school-list-dropdown-v72 {
            position: relative !important;
            flex: 0 0 auto !important;
        }

        .rc-school-list-menu-v72 {
            width: min(30rem, calc(100vw - 2rem)) !important;
            max-width: calc(100vw - 2rem) !important;
            right: 0 !important;
            left: auto !important;
            z-index: 40 !important;
        }

        .rc-school-list-menu-v72 button {
            grid-template-columns: 1.25rem minmax(15rem, 1fr) auto !important;
            min-height: 3rem !important;
            padding: .52rem .65rem !important;
        }

        .rc-school-list-label-v87 {
            display: flex !important;
            align-items: center !important;
            gap: .55rem !important;
            min-width: 0 !important;
            color: var(--rc-text) !important;
            font-size: .92rem !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            white-space: normal !important;
            overflow: visible !important;
            word-break: break-word !important;
        }

        .rc-school-list-menu-v72 button.is-active {
            background: color-mix(in srgb, var(--list-color, #ff6338) 18%, white) !important;
            box-shadow: inset 3px 0 0 var(--list-color, #ff6338) !important;
        }

        .dark .rc-school-list-menu-v72 button.is-active {
            background: color-mix(in srgb, var(--list-color, #ff6338) 24%, #111827) !important;
        }

        .rc-school-action.is-favorited[disabled],
        .rc-school-action[disabled] {
            opacity: .78 !important;
            cursor: wait !important;
        }

        .rc-school-action .rc-action-spinner-v81 {
            flex: 0 0 auto !important;
        }

        @media (min-width: 780px) {
            .rc-school-modal-actions-v72 { flex-wrap: nowrap !important; }
        }

        .rc-school-tabbar-v72 {
            border-radius: .9rem !important;
            padding: .25rem !important;
            background: var(--rc-soft) !important;
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: .2rem !important;
        }

        .rc-school-tab-v72 {
            min-height: 2.75rem !important;
            border-radius: .72rem !important;
            font-size: .84rem !important;
            font-weight: 700 !important;
            color: var(--rc-muted) !important;
            transition: background .15s ease, box-shadow .15s ease, color .15s ease !important;
        }

        .rc-school-tab-v72.is-active {
            background: var(--rc-surface) !important;
            color: var(--rc-text) !important;
            box-shadow: 0 6px 16px rgba(15,23,42,.08) !important;
        }

        .rc-school-modal-coaches,
        .rc-school-list-menu-v72 {
            scrollbar-width: thin !important;
            scrollbar-color: rgba(255,99,56,.48) transparent !important;
        }

        .rc-school-modal-coaches::-webkit-scrollbar,
        .rc-school-list-menu-v72::-webkit-scrollbar { width: .45rem !important; }
        .rc-school-modal-coaches::-webkit-scrollbar-thumb,
        .rc-school-list-menu-v72::-webkit-scrollbar-thumb { background: rgba(255,99,56,.42) !important; border-radius: 999px !important; }
        .rc-school-modal-coaches::-webkit-scrollbar-track,
        .rc-school-list-menu-v72::-webkit-scrollbar-track { background: transparent !important; }

        /* School drawer no longer uses a global wire:loading overlay.
           The previous overlay could stay visible when any Livewire request was active,
           blocking the entire dashboard. Keep school opening cache-only and let the drawer
           animation handle the transition. */


        @media (max-width: 680px) {
            .rc-school-modal-panel,
            .rc-drawer-panel.rc-school-modal-panel {
                width: 100vw !important;
                border-radius: 0 !important;
                padding: 1rem !important;
            }
        }

        .rc-school-grid.rc-discover-school-grid {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 1rem !important;
        }

        .rc-school-card.rc-discover-school-card {
            min-height: 0 !important;
            border: 1px solid var(--rc-border) !important;
            border-radius: .95rem !important;
            background: var(--rc-surface) !important;
            padding: 1.1rem !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .055) !important;
            gap: 0 !important;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease !important;
        }

        .rc-school-card.rc-discover-school-card:hover {
            transform: translateY(-1px) !important;
            border-color: rgba(255, 99, 56, .28) !important;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .09) !important;
        }

        .rc-discover-card-main {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 1.7rem;
            gap: .6rem;
            align-items: start;
        }

        .rc-discover-card-title {
            border: 0;
            background: transparent;
            padding: 0;
            min-width: 0;
            display: grid !important;
            grid-template-columns: 3.2rem minmax(0, 1fr) !important;
            gap: .8rem !important;
            align-items: center !important;
            color: var(--rc-text) !important;
            text-align: left !important;
            cursor: pointer;
        }

        .rc-school-card-logo-box,
        .rc-school-list-logo-box {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: var(--rc-soft) !important;
            border: 0 !important;
            overflow: hidden !important;
            flex: 0 0 auto !important;
            position: relative !important;
        }

        .rc-school-card-logo-box {
            width: 3.2rem !important;
            height: 3.2rem !important;
            border-radius: .75rem !important;
            padding: .35rem !important;
        }

        .rc-school-list-logo-box {
            width: 2.15rem !important;
            height: 2.15rem !important;
            border-radius: .55rem !important;
            padding: .25rem !important;
        }

        .rc-school-card-logo,
        .rc-school-list-logo {
            width: auto !important;
            height: auto !important;
            max-width: 100% !important;
            max-height: 100% !important;
            object-fit: contain !important;
            display: block !important;
        }

        .rc-logo-fallback-text {
            position: absolute;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            color: #334155;
            font-size: .78rem;
            font-weight: 500;
            letter-spacing: -.02em;
            background: #f3f4f6;
        }

        .dark .rc-logo-fallback-text { color: #e5e7eb; background: rgba(148, 163, 184, .12); }
        .is-missing-logo .rc-logo-fallback-text { display: flex; }
        .is-missing-logo img { display: none !important; }

        .rc-discover-card-copy {
            min-width: 0;
            display: grid;
            gap: .18rem;
        }

        .rc-discover-card-copy strong {
            color: var(--rc-text);
            font-size: .98rem;
            line-height: 1.15;
            font-weight: 650;
            letter-spacing: -.025em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rc-discover-card-copy small {
            color: var(--rc-muted);
            font-size: .82rem;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rc-discover-card-check,
        .rc-discover-row-check {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.55rem;
            height: 1.55rem;
            border: 1px solid var(--rc-border);
            border-radius: .45rem;
            background: var(--rc-surface);
            color: var(--rc-accent);
            font-size: .8rem;
            font-weight: 650;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
        }

        .rc-discover-card-rule {
            height: 1px;
            background: var(--rc-border);
            margin: .9rem 0 .85rem;
            opacity: .72;
        }

        .rc-discover-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
        }

        .rc-discover-division-pill {
            display: inline-flex;
            align-items: center;
            border-radius: .48rem;
            background: rgba(255, 99, 56, .13);
            color: var(--rc-accent);
            padding: .34rem .55rem;
            font-size: .72rem;
            line-height: 1;
            font-weight: 650;
            white-space: nowrap;
        }

        .rc-discover-coach-count {
            color: var(--rc-muted);
            font-size: .82rem;
            line-height: 1.1;
            white-space: nowrap;
        }

        .rc-school-list-table.rc-discover-school-list {
            display: grid !important;
            gap: 0 !important;
            border: 1px solid var(--rc-border) !important;
            border-radius: 1rem !important;
            background: var(--rc-surface) !important;
            overflow: hidden !important;
            box-shadow: 0 8px 28px rgba(15, 23, 42, .045) !important;
        }

        .rc-discover-school-list-head,
        .rc-discover-school-list-row {
            display: grid !important;
            grid-template-columns: minmax(15rem, 1.45fr) minmax(10rem, 1.05fr) minmax(9rem, 1fr) minmax(13rem, 1.2fr) 4rem 2.5rem !important;
            gap: 1rem !important;
            align-items: center !important;
        }

        .rc-discover-school-list-head {
            padding: .9rem 1.25rem !important;
            background: var(--rc-soft) !important;
            color: var(--rc-muted) !important;
            font-size: .72rem !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
            letter-spacing: .06em !important;
        }

        .rc-discover-school-list-row {
            border: 0 !important;
            border-top: 1px solid var(--rc-border) !important;
            border-radius: 0 !important;
            background: transparent !important;
            padding: .88rem 1.25rem !important;
            box-shadow: none !important;
        }

        .rc-discover-school-list-row:hover { background: var(--rc-soft) !important; }

        .rc-discover-school-list-school {
            display: grid !important;
            grid-template-columns: 2.15rem minmax(0, 1fr) !important;
            gap: .75rem !important;
            align-items: center !important;
            font-size: .9rem !important;
            font-weight: 850 !important;
        }

        .rc-discover-school-list-name-copy,
        .rc-discover-list-coach,
        .rc-discover-list-muted,
        .rc-discover-list-email {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rc-discover-list-coach { color: var(--rc-text); font-weight: 600; font-size: .82rem; }
        .rc-discover-list-muted { color: var(--rc-muted); font-size: .82rem; }
        .rc-discover-list-email a { color: #3b82f6; text-decoration: none; font-size: .82rem; }
        .rc-discover-list-division { color: var(--rc-accent); font-size: .76rem; font-weight: 650; }
        .rc-head-coach-chip { display:inline-flex; margin-left:.28rem; border-radius:.35rem; padding:.12rem .28rem; background:rgba(255,99,56,.13); color:var(--rc-accent); font-size:.62rem; font-weight:950; vertical-align:middle; }

        .rc-discover-list-actions { justify-content: flex-end !important; }

        @media (max-width: 1320px) {
            .rc-school-grid.rc-discover-school-grid { grid-template-columns: repeat(3, minmax(0,1fr)) !important; }
        }

        @media (max-width: 1024px) {
            .rc-school-grid.rc-discover-school-grid { grid-template-columns: repeat(2, minmax(0,1fr)) !important; }
            .rc-discover-school-list-head { display: none !important; }
            .rc-discover-school-list-row { grid-template-columns: 1fr auto !important; gap: .5rem !important; }
            .rc-discover-school-list-row > :nth-child(n+2):nth-child(-n+5) { display: none !important; }
        }

        @media (max-width: 640px) {
            .rc-school-grid.rc-discover-school-grid { grid-template-columns: 1fr !important; }
        }



        /* v72: school drawer tabs, stronger checkboxes, inbox scrollers, schedule/settings views */

        /* v73: compact schedule/inbox/drawer refinements */
        .rc-school-modal-panel { max-width: 30rem; }
        .rc-school-modal-hero-v72 { gap:.75rem!important; padding-bottom:.85rem!important; }
        .rc-school-logo-large-v72 { width:3.6rem!important;height:3.6rem!important;border-radius:.9rem!important; }
        .rc-school-modal-main h2 { font-size:1.35rem!important; line-height:1.1!important; }
        .rc-school-modal-actions-v72 { gap:.5rem!important; }
        .rc-school-action { min-height:2.65rem!important; padding:.68rem .9rem!important; border-radius:.75rem!important; font-size:.84rem!important; }
        .rc-school-tabbar-v72 { padding:.25rem!important; border-radius:.85rem!important; }
        .rc-school-tab-v72 { padding:.72rem .8rem!important; font-size:.82rem!important; }
        .rc-school-coach-list { max-height:18rem!important; }
        .rc-school-coach-card { padding:.72rem!important; border-radius:.8rem!important; }
        .rc-school-copy-btn { font-size:0!important; width:2.25rem!important; height:2.25rem!important; }
        .rc-school-copy-btn svg { display:block; width:1.05rem; height:1.05rem; flex:0 0 auto; }
        .rc-school-copy-btn::before { content:none!important; display:none!important; }
        .rc-inbox-shell-v56 { height: min(41rem, calc(100vh - 10rem))!important; min-height:30rem!important; max-height:41rem!important; }
        .rc-inbox-list-v56, .rc-inbox-messages-v56, .rc-coach-profile-v56 { scrollbar-width:thin; scrollbar-color:rgba(148,163,184,.8) transparent; }
        .rc-inbox-list-v56::-webkit-scrollbar, .rc-inbox-messages-v56::-webkit-scrollbar, .rc-coach-profile-v56::-webkit-scrollbar { width:.45rem; }
        .rc-inbox-list-v56::-webkit-scrollbar-thumb, .rc-inbox-messages-v56::-webkit-scrollbar-thumb, .rc-coach-profile-v56::-webkit-scrollbar-thumb { background:rgba(148,163,184,.65); border-radius:999px; }
        .rc-about-item-v56 span:first-child { display:grid; place-items:center; color:var(--rc-muted); }
        .rc-schedule-page-v72 { gap:.9rem!important; }
        .rc-schedule-titlebar-v72 h1 { font-size:1.25rem!important; }
        .rc-schedule-form-v72 { padding:1rem!important; border-radius:1rem!important; }
        .rc-schedule-grid-v72 { gap:.7rem!important; }
        .rc-schedule-row-v72 { padding:.82rem .95rem!important; grid-template-columns:4.35rem minmax(0,1fr) auto!important; }
        .rc-schedule-date-v72 strong { font-size:1.35rem!important; }
        .rc-schedule-pill-v72 { font-size:.68rem!important; padding:.2rem .48rem!important; }
        .rc-schedule-meta-v72 { font-size:.78rem!important; gap:.9rem!important; }
        .rc-schedule-icon-v73 { width:.95rem; height:.95rem; display:inline-block; vertical-align:-.15rem; color:var(--rc-muted); margin-right:.25rem; }
        .rc-icon-clean-v72 { width:2.2rem!important;height:2.2rem!important;display:grid!important;place-items:center!important;font-size:0!important; }
        .rc-icon-clean-v72 svg { width:1rem;height:1rem; }
        .rc-discover-card-check, .rc-discover-row-check { width:1.9rem!important;height:1.9rem!important;border:2.5px solid #94a3b8!important;background:#fff!important;box-shadow:0 2px 12px rgba(15,23,42,.14)!important; }
        .rc-discover-card-check::after, .rc-discover-row-check::after { content:'✓'; font-size:1rem; font-weight:900; line-height:1; color:#fff; opacity:0; }
        .rc-discover-card-check.is-selected::after, .rc-discover-row-check.is-selected::after { opacity:1; }
        .rc-school-modal-panel { scrollbar-width: thin; scrollbar-color: rgba(255,99,56,.45) rgba(148,163,184,.16); }
        .rc-school-modal-panel::-webkit-scrollbar,
        .rc-school-modal-coaches::-webkit-scrollbar,
        .rc-inbox-scroll-v72::-webkit-scrollbar,
        .rc-inbox-list-v56::-webkit-scrollbar,
        .rc-inbox-messages-v56::-webkit-scrollbar { width: .55rem; height:.55rem; }
        .rc-school-modal-panel::-webkit-scrollbar-track,
        .rc-school-modal-coaches::-webkit-scrollbar-track,
        .rc-inbox-scroll-v72::-webkit-scrollbar-track,
        .rc-inbox-list-v56::-webkit-scrollbar-track,
        .rc-inbox-messages-v56::-webkit-scrollbar-track { background: rgba(148,163,184,.12); border-radius:999px; }
        .rc-school-modal-panel::-webkit-scrollbar-thumb,
        .rc-school-modal-coaches::-webkit-scrollbar-thumb,
        .rc-inbox-scroll-v72::-webkit-scrollbar-thumb,
        .rc-inbox-list-v56::-webkit-scrollbar-thumb,
        .rc-inbox-messages-v56::-webkit-scrollbar-thumb { background: rgba(255,99,56,.55); border-radius:999px; }
        .rc-school-logo-large-v72 { width:4.35rem;height:4.35rem;border-radius:1rem;background:#fff;border:1px solid var(--rc-border);display:grid;place-items:center;overflow:hidden;box-shadow:0 10px 24px rgba(15,23,42,.08); }
        .rc-school-logo-large-v72 img { width:100%;height:100%;object-fit:contain;padding:.35rem; }
        .rc-school-logo-large-v72 span { font-weight:800;color:#0f172a; }
        .rc-school-modal-hero-v72 { display:grid; grid-template-columns:auto minmax(0,1fr) auto; gap:1rem; align-items:start; animation: rcFadeUp .22s ease both; }
        .rc-school-modal-actions-v72 { display:flex; flex-wrap:wrap; gap:.55rem; margin-top:1.15rem; }
        .rc-school-tabbar-v72 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.35rem; padding:.35rem; border-radius:1rem; background:var(--rc-soft); border:1px solid var(--rc-border); margin:1rem 0; }
        .rc-school-tab-v72 { border:0;border-radius:.78rem;background:transparent;color:var(--rc-muted);font-weight:700;padding:.78rem .5rem;cursor:pointer;transition:.15s ease; }
        .rc-school-tab-v72.is-active { background:var(--rc-surface);color:var(--rc-text);box-shadow:0 8px 18px rgba(15,23,42,.08); }
        .rc-school-tab-panel-v72 { animation: rcFadeUp .2s ease both; }
        .rc-school-list-dropdown-v72 { position:relative; }
        .rc-school-list-menu-v72 { position:absolute;left:0;top:calc(100% + .45rem);width:min(21rem,86vw);z-index:15;background:var(--rc-surface);border:1px solid var(--rc-border);border-radius:1rem;box-shadow:0 18px 44px rgba(15,23,42,.16);padding:.75rem;display:grid;gap:.35rem; }
        .rc-school-list-menu-v72 h4 { margin:0 0 .35rem;font-size:.76rem;text-transform:uppercase;letter-spacing:.08em;color:var(--rc-muted); }
        .rc-school-list-menu-v72 button { width:100%;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.55rem;border:0;background:transparent;border-radius:.75rem;padding:.55rem;text-align:left;color:var(--rc-text);cursor:pointer; }
        .rc-school-list-menu-v72 button:hover { background:var(--rc-soft); }
        .rc-school-list-dot-v72 { width:.65rem;height:.65rem;border-radius:999px;background:var(--dot,#ff6338); }
        .rc-coming-soon-v72 { min-height:13rem;border:1px dashed var(--rc-border);border-radius:1rem;display:grid;place-items:center;text-align:center;color:var(--rc-muted);background:var(--rc-soft); }
        .rc-coming-soon-v72 strong { display:block;color:var(--rc-text);font-size:1.15rem;margin-bottom:.25rem; }
        @keyframes rcFadeUp { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

        .rc-discover-card-check,
        .rc-discover-row-check { width:1.65rem!important;height:1.65rem!important;border:2px solid #cbd5e1!important;background:#fff!important;color:#fff!important;border-radius:.5rem!important;display:inline-grid!important;place-items:center!important;box-shadow:0 2px 8px rgba(15,23,42,.10)!important;font-weight:900!important; }
        .rc-discover-card-check:hover,
        .rc-discover-row-check:hover { border-color:#ff6338!important; box-shadow:0 0 0 4px rgba(255,99,56,.12)!important; }
        .rc-discover-card-check.is-selected,
        .rc-discover-row-check.is-selected { background:#ff6338!important;border-color:#ff6338!important;color:#fff!important; }

        .rc-inbox-page-v56 { max-height:calc(100vh - 11rem); min-height:35rem; overflow:hidden; }
        .rc-inbox-shell-v56 { height:calc(100vh - 12rem); min-height:34rem; max-height:48rem; }
        .rc-inbox-left-v56,
        .rc-inbox-main-v56,
        .rc-inbox-right-v56 { min-height:0; overflow:hidden; }
        .rc-inbox-list-v56 { overflow:auto; max-height:calc(100% - 8.5rem); }
        .rc-inbox-messages-v56 { overflow:auto; max-height:calc(100% - 6.25rem); padding-right:.25rem; }
        .rc-inbox-right-v56 { overflow:auto; scrollbar-width:thin; }
        .rc-about-grid-v56 { grid-template-columns:1fr!important; }

        .rc-schedule-page-v72 { display:grid; gap:1.15rem; }
        .rc-schedule-titlebar-v72 { display:flex;align-items:flex-end;justify-content:space-between;gap:1rem; }
        .rc-schedule-titlebar-v72 h1 { margin:0;font-size:1.35rem;letter-spacing:-.03em; }
        .rc-schedule-sub-v72 { color:var(--rc-muted);margin:.25rem 0 0; }
        .rc-schedule-live-v72 { color:#059669;font-weight:700;font-size:.85rem; }
        .rc-schedule-form-v72 { border:1px solid var(--rc-border);border-radius:1.15rem;background:var(--rc-surface);box-shadow:0 12px 28px rgba(15,23,42,.07);padding:1.15rem;display:grid;gap:1rem;animation:rcFadeUp .18s ease both; }
        .rc-schedule-grid-v72 { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem; }
        .rc-field-v72 label { display:block;font-size:.76rem;font-weight:700;color:var(--rc-text);margin-bottom:.4rem; }
        .rc-field-v72 input,.rc-field-v72 select { width:100%;border:1px solid var(--rc-border);border-radius:.75rem;background:var(--rc-surface);color:var(--rc-text);padding:.75rem .85rem;outline:0; }
        .rc-schedule-list-title-v72 { color:var(--rc-muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;font-weight:800;margin-top:.5rem; }
        .rc-schedule-list-v72 { border:1px solid var(--rc-border);border-radius:1.15rem;background:var(--rc-surface);box-shadow:0 12px 28px rgba(15,23,42,.06);overflow:hidden; }
        .rc-schedule-row-v72 { display:grid;grid-template-columns:5rem minmax(0,1fr) auto;gap:1rem;align-items:center;padding:1rem 1.1rem;border-bottom:1px solid var(--rc-border); }
        .rc-schedule-row-v72:last-child { border-bottom:0; }
        .rc-schedule-date-v72 { text-align:center;border-right:1px solid var(--rc-border);padding-right:.85rem; }
        .rc-schedule-date-v72 small { display:block;color:#ff6338;font-weight:800;text-transform:uppercase;font-size:.7rem; }
        .rc-schedule-date-v72 strong { display:block;font-size:1.55rem;line-height:1;color:var(--rc-text); }
        .rc-schedule-date-v72 span { color:var(--rc-muted);font-size:.75rem; }
        .rc-schedule-pill-v72 { display:inline-flex;border-radius:999px;padding:.25rem .55rem;background:rgba(99,102,241,.12);color:#6366f1;font-weight:800;font-size:.72rem;margin-right:.45rem; }
        .rc-schedule-meta-v72 { display:flex;flex-wrap:wrap;gap:1rem;color:var(--rc-muted);font-size:.85rem;margin-top:.55rem; }
        .rc-schedule-actions-v72 { display:flex;gap:.45rem; }
        .rc-icon-clean-v72 { width:2rem;height:2rem;border:0;background:transparent;color:var(--rc-muted);border-radius:.55rem;cursor:pointer;display:grid;place-items:center; }
        .rc-icon-clean-v72:hover { background:var(--rc-soft);color:#ff6338; }
        .rc-settings-page-v72 { display:grid;gap:1rem; }
        .rc-settings-card-v72 { border:1px solid var(--rc-border);border-radius:1.15rem;background:var(--rc-surface);box-shadow:0 12px 28px rgba(15,23,42,.06);padding:1.25rem;max-width:56rem; }
        .rc-settings-head-v72 { display:flex;gap:1rem;align-items:center;padding-bottom:1rem;border-bottom:1px solid var(--rc-border); }
        .rc-settings-icon-v72 { width:3rem;height:3rem;border-radius:.85rem;background:#eff6ff;color:#2563eb;display:grid;place-items:center; }
        .rc-setting-row-v72 { display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 0;border-bottom:1px solid var(--rc-border); }
        .rc-setting-row-v72:last-child { border-bottom:0; }
        .rc-setting-row-v72 h3 { margin:0;font-size:.98rem; }
        .rc-setting-row-v72 p { margin:.25rem 0 0;color:var(--rc-muted); }
        .rc-toggle-v72 { width:3.25rem;height:1.8rem;border:0;border-radius:999px;background:#e5e7eb;padding:.2rem;display:flex;align-items:center;justify-content:flex-start;cursor:pointer;transition:.15s ease; }
        .rc-toggle-v72 span { width:1.4rem;height:1.4rem;border-radius:999px;background:#fff;box-shadow:0 2px 5px rgba(0,0,0,.18);transition:.15s ease; }
        .rc-toggle-v72.is-on { background:#ff6338;justify-content:flex-end; }
        @media (max-width:900px){ .rc-schedule-grid-v72{grid-template-columns:1fr}.rc-schedule-row-v72{grid-template-columns:1fr}.rc-schedule-date-v72{text-align:left;border-right:0;border-bottom:1px solid var(--rc-border);padding-bottom:.65rem} }



        /* v87 final overrides: keep list dropdown readable after later compact rules */
        .rc-drawer-panel.rc-school-modal-panel { width: min(680px, 100vw) !important; }
        .rc-school-list-menu-v72 {
            left: auto !important;
            right: 0 !important;
            width: min(31rem, calc(100vw - 2rem)) !important;
            max-width: calc(100vw - 2rem) !important;
            z-index: 50 !important;
            gap: .42rem !important;
        }
        .rc-school-list-menu-v72 button {
            grid-template-columns: 1.25rem minmax(16rem, 1fr) auto !important;
            min-height: 3rem !important;
            padding: .55rem .7rem !important;
        }
        .rc-school-list-label-v87 {
            display:flex !important;
            align-items:center !important;
            gap:.55rem !important;
            min-width:0 !important;
            white-space:normal !important;
            overflow:visible !important;
            text-overflow:clip !important;
            word-break:break-word !important;
            font-size:.92rem !important;
            font-weight:700 !important;
            color:var(--rc-text) !important;
        }
        .rc-school-list-dot-v72 { flex: 0 0 auto !important; }
        .rc-school-list-menu-v72 button.is-active {
            background: color-mix(in srgb, var(--list-color, #ff6338) 20%, white) !important;
            box-shadow: inset 3px 0 0 var(--list-color, #ff6338) !important;
        }
        .dark .rc-school-list-menu-v72 button.is-active {
            background: color-mix(in srgb, var(--list-color, #ff6338) 24%, #111827) !important;
        }



        /* v90: keep the list menu under the In Lists button, shifted right so the left edge is never clipped. */
        .rc-drawer-panel.rc-school-modal-panel {
            width: min(760px, 100vw) !important;
            max-width: 100vw !important;
            overflow-x: visible !important;
        }

        .rc-school-modal-actions-v72 {
            position: relative !important;
            z-index: 40 !important;
            overflow: visible !important;
        }

        .rc-school-list-dropdown-v72 {
            position: relative !important;
            display: inline-flex !important;
            overflow: visible !important;
            z-index: 90 !important;
        }

        .rc-school-list-menu-v72 {
            position: absolute !important;
            top: calc(100% + .55rem) !important;
            right: -9rem !important;
            left: auto !important;
            width: min(27rem, calc(100vw - 2rem)) !important;
            min-width: min(23rem, calc(100vw - 2rem)) !important;
            max-width: calc(100vw - 2rem) !important;
            z-index: 120 !important;
            max-height: min(25rem, calc(100vh - 18rem)) !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            transform-origin: top center !important;
        }

        .rc-school-list-menu-v72 button {
            grid-template-columns: 1.45rem minmax(0, 1fr) auto !important;
            width: 100% !important;
            overflow: visible !important;
        }

        @media (max-width: 900px) {
            .rc-school-list-menu-v72 {
                right: 0 !important;
                width: min(23rem, calc(100vw - 2rem)) !important;
                min-width: min(19rem, calc(100vw - 2rem)) !important;
            }
        }

        .rc-school-list-label-v87 {
            min-width: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow: visible !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: anywhere !important;
        }

        .rc-school-list-label-v87 > span:last-child {
            display: block !important;
            min-width: 0 !important;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }

        .rc-school-list-menu-v72 h4 {
            white-space: nowrap !important;
            overflow: visible !important;
        }

        @media (max-width: 760px) {
            .rc-school-list-menu-v72 {
                right: auto !important;
                left: 0 !important;
                width: min(25rem, calc(100vw - 2rem)) !important;
                min-width: min(21rem, calc(100vw - 2rem)) !important;
                transform-origin: top left !important;
            }
        }


        /* v93 Inbox scroll fix: use the actual inbox markup class names */
        .rc-inbox-page-v56 {
            height: calc(100vh - 9.75rem) !important;
            min-height: 34rem !important;
            overflow: hidden !important;
        }
        .rc-inbox-shell-v56 {
            height: 100% !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow: hidden !important;
        }
        .rc-inbox-left-v56,
        .rc-inbox-mid-v56,
        .rc-inbox-right-v56 {
            min-height: 0 !important;
            height: 100% !important;
            overflow: hidden !important;
        }
        .rc-inbox-left-v56 {
            display: flex !important;
            flex-direction: column !important;
        }
        .rc-inbox-list-v56 {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }
        .rc-inbox-mid-v56 {
            display: flex !important;
            flex-direction: column !important;
        }
        .rc-message-stream-v56 {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            overscroll-behavior: contain !important;
        }
        .rc-inbox-right-v56 {
            overflow: hidden !important;
        }
        .rc-coach-profile-v56 {
            height: 100% !important;
            max-height: none !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            overscroll-behavior: contain !important;
        }
        .rc-inbox-list-v56,
        .rc-message-stream-v56,
        .rc-coach-profile-v56 {
            scrollbar-width: thin;
            scrollbar-color: rgba(255,99,56,.55) rgba(148,163,184,.12);
        }
        .rc-inbox-list-v56::-webkit-scrollbar,
        .rc-message-stream-v56::-webkit-scrollbar,
        .rc-coach-profile-v56::-webkit-scrollbar {
            width: .55rem;
        }
        .rc-inbox-list-v56::-webkit-scrollbar-track,
        .rc-message-stream-v56::-webkit-scrollbar-track,
        .rc-coach-profile-v56::-webkit-scrollbar-track {
            background: rgba(148,163,184,.12);
            border-radius: 999px;
        }
        .rc-inbox-list-v56::-webkit-scrollbar-thumb,
        .rc-message-stream-v56::-webkit-scrollbar-thumb,
        .rc-coach-profile-v56::-webkit-scrollbar-thumb {
            background: rgba(255,99,56,.55);
            border-radius: 999px;
        }

        /* v94: keep dashboard first visit pinned to the top and avoid stale loading panels covering content. */
        .rc-wrap { min-height: 0 !important; }
        .rc-livewire-root [data-stale-school-loader],
        .rc-livewire-root .rc-school-loader-backdrop,
        .rc-livewire-root .rc-school-loading-backdrop,
        .rc-livewire-root .rc-opening-school-backdrop {
            display: none !important;
            pointer-events: none !important;
        }

        @media (max-width: 900px) {
            .rc-inbox-page-v56 { height: auto !important; min-height: 0 !important; overflow: visible !important; }
            .rc-inbox-shell-v56 { height: auto !important; overflow: visible !important; }
            .rc-inbox-left-v56,
            .rc-inbox-mid-v56,
            .rc-inbox-right-v56 { height: auto !important; overflow: visible !important; }
            .rc-inbox-list-v56 { max-height: 24rem !important; }
            .rc-message-stream-v56 { max-height: 36rem !important; }
        }



        /* v102 non-overlay Recruiting Center sync status */
        .rc-reload-status-v101 {
            position: static !important;
            top: auto !important;
            z-index: auto !important;
            display: grid;
            gap: .55rem;
            margin: 0 0 1rem 0;
            border: 1px solid rgba(255, 99, 56, .22);
            background: linear-gradient(135deg, rgba(255, 99, 56, .08), rgba(255, 255, 255, .95));
            border-radius: .9rem;
            padding: .75rem .9rem;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
            backdrop-filter: none;
        }

        .dark .rc-reload-status-v101 {
            background: linear-gradient(135deg, rgba(255, 99, 56, .12), rgba(24, 24, 27, .92));
            box-shadow: 0 10px 24px rgba(0, 0, 0, .18);
        }

        .rc-reload-main-v101 {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
        }

        .rc-reload-copy-v101 {
            display: grid;
            gap: .18rem;
            min-width: 0;
        }

        .rc-reload-copy-v101 strong {
            color: var(--rc-text);
            font-size: .92rem;
            font-weight: 850;
            letter-spacing: -.02em;
        }

        .rc-reload-copy-v101 span,
        .rc-reload-meta-v101 {
            color: var(--rc-muted);
            font-size: .78rem;
            line-height: 1.35;
        }

        .rc-reload-pill-v101 {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border-radius: 999px;
            padding: .42rem .7rem;
            background: var(--rc-accent-soft);
            color: var(--rc-accent);
            font-size: .76rem;
            font-weight: 850;
            white-space: nowrap;
        }

        .rc-reload-pulse-v101 {
            width: .52rem;
            height: .52rem;
            border-radius: 999px;
            background: currentColor;
            box-shadow: 0 0 0 rgba(255, 99, 56, .4);
            animation: rcReloadPulse 1.4s infinite;
        }

        @keyframes rcReloadPulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 99, 56, .34); }
            70% { box-shadow: 0 0 0 .55rem rgba(255, 99, 56, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 99, 56, 0); }
        }

        .rc-reload-stats-v101 {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem .85rem;
        }

        .rc-reload-stats-v101 span {
            color: var(--rc-muted);
            font-size: .74rem;
            font-weight: 700;
        }

        .rc-reload-stats-v101 b { color: var(--rc-text); }

        .rc-reload-copy-v101 strong { font-size: .86rem; }
        .rc-reload-copy-v101 span,
        .rc-reload-stats-v101 span { font-size: .72rem; }
        .rc-reload-pill-v101 { padding: .35rem .6rem; font-size: .72rem; }

        @media (max-width: 900px) {
            .rc-reload-main-v101 {
                align-items: flex-start;
                flex-direction: column;
            }

            .rc-reload-status-v101 { padding: .7rem; }
        }
</style>

    @php
        $formatRecruitingTimestamp = function ($value) {
            if (blank($value)) {
                return null;
            }

            try {
                return \Carbon\Carbon::parse($value)->timezone(config('app.timezone', 'UTC'))->format('M j, Y \a\t g:i A');
            } catch (\Throwable $exception) {
                return is_string($value) ? $value : null;
            }
        };

        $formattedCachedAt = $formatRecruitingTimestamp($cachedAt ?? null);
        $formattedTagUpdatedAt = $formatRecruitingTimestamp($tagUpdatedAt ?? null);

        $formatActivityTimeLabel = function ($time): string {
            if (! $time) {
                return 'Recent';
            }

            try {
                $timeValue = \Illuminate\Support\Carbon::parse($time);

                if ($timeValue->lessThan(now()->subYears(3)) || $timeValue->greaterThan(now()->addDay())) {
                    return 'Recent';
                }

                return $timeValue->diffForHumans();
            } catch (\Throwable $exception) {
                return 'Recent';
            }
        };


        $statDrawerSections = ['profile-views', 'coach-engagement'];
        $isStatDrawerOpen = in_array($section, $statDrawerSections, true);
        $globalSearchSuggestions = $this->globalSearchSuggestions;
        $globalSearchHasSuggestions = (int) ($globalSearchSuggestions['total'] ?? 0) > 0;
        $globalSearchGroups = [
            'schools' => 'Schools',
            'coaches' => 'Coaches',
            'conferences' => 'Conferences',
            'divisions' => 'Divisions',
            'lists' => 'Student Lists',
        ];
        $globalSearchInitials = function (string $label): string {
            $initials = collect(explode(' ', trim($label)))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
            return strtoupper($initials ?: '•');
        };
        $authUser = auth()->user();
        $athleteName = trim((string) (method_exists($authUser, 'getFilamentName') ? $authUser?->getFilamentName() : ''));
        if ($athleteName === '') {
            $athleteName = trim((string) (($authUser?->first_name ?? '') . ' ' . ($authUser?->last_name ?? '')));
        }
        if ($athleteName === '') {
            $athleteName = trim((string) ($authUser?->name ?? ''));
        }
        $firstName = $athleteName !== '' ? $athleteName : 'Player';
    @endphp

    <script>
        (function () {
            const isCoachDashboardRoot = function () {
                const path = window.location.pathname.replace(/\/$/, '');
                return path === '/admin/coach-database';
            };

            const resetOne = function (el) {
                if (! el) return;
                try { el.scrollTop = 0; } catch (error) {}
                try { el.scrollLeft = 0; } catch (error) {}
            };

            window.resetCoachDatabaseDashboardScroll = function () {
                if (! isCoachDashboardRoot()) return;

                try {
                    if ('scrollRestoration' in window.history) {
                        window.history.scrollRestoration = 'manual';
                    }
                } catch (error) {}

                try { window.scrollTo(0, 0); } catch (error) {}
                resetOne(document.documentElement);
                resetOne(document.body);

                document.querySelectorAll('main, .fi-main, .fi-page, .fi-main-ctn, .fi-layout, .fi-body, [data-filament-main], [data-slot="main"], .fi-panel-page').forEach(resetOne);

                document.querySelectorAll('*').forEach(function (el) {
                    try {
                        if (el.scrollHeight > el.clientHeight + 40 && getComputedStyle(el).overflowY !== 'visible') {
                            el.scrollTop = 0;
                        }
                    } catch (error) {}
                });
            };

            window.runCoachDatabaseScrollResetLoop = function () {
                if (! isCoachDashboardRoot()) return;

                let count = 0;
                const run = function () {
                    window.resetCoachDatabaseDashboardScroll();
                    count += 1;
                    if (count < 18) {
                        window.setTimeout(run, count < 6 ? 50 : 150);
                    }
                };

                run();
                window.requestAnimationFrame(function () {
                    window.resetCoachDatabaseDashboardScroll();
                });
            };

            window.addEventListener('pageshow', window.runCoachDatabaseScrollResetLoop);
            window.addEventListener('load', window.runCoachDatabaseScrollResetLoop);
            document.addEventListener('DOMContentLoaded', window.runCoachDatabaseScrollResetLoop);
            document.addEventListener('livewire:navigated', window.runCoachDatabaseScrollResetLoop);
        })();

        window.initCoachDatabasePage = function (wire) {
            window.runCoachDatabaseScrollResetLoop && window.runCoachDatabaseScrollResetLoop();

            window.setTimeout(function () {
                window.runCoachDatabaseScrollResetLoop && window.runCoachDatabaseScrollResetLoop();
            }, 250);

            window.setTimeout(function () {
                if (wire && typeof wire.startBackgroundLoad === 'function') {
                    wire.startBackgroundLoad();
                }
            }, 900);

            if (! window.__plyrCoachDatabaseLoadNextInstalled) {
                window.__plyrCoachDatabaseLoadNextInstalled = true;
                window.addEventListener('coach-database-load-next', function () {
                    window.setTimeout(function () {
                        if (wire && typeof wire.loadNextBatch === 'function') {
                            wire.loadNextBatch();
                        }
                    }, 75);
                });
            }
        };
    </script>

    <div
        class="rc-wrap"
        x-init="window.initCoachDatabasePage && window.initCoachDatabasePage($wire)"
        x-on:rc-discover-selection.window="discoverSelectedIds = Array.isArray($event.detail?.ids) ? $event.detail.ids.map(String) : []"
        x-on:rc-open-school-optimistic.window="openGlobalSchool($event.detail?.school || null)"
        x-on:rc-open-school-global.window="openGlobalSchool($event.detail?.school || $event.detail?.id || null)"
        x-on:rc-school-optimistic-clear.window="closeDiscoverSchool()"
        x-on:rc-discover-school-state.window="applyGlobalSchoolState($event.detail)"
        x-on:rc-discover-count.window="discoverClientCount = Number($event.detail?.total || 0); discoverClientShown = Number($event.detail?.shown || 0)"
        x-on:rc-discover-conferences.window="discoverAvailableConferences = Array.isArray($event.detail?.conferences) ? $event.detail.conferences : []; if (discoverConference && !discoverAvailableConferences.includes(discoverConference)) discoverConference = ''"
        @if(! in_array($section, ['schools', 'favorites', 'lists'], true))
            wire:poll.5s.visible="pollRealtime"
        @endif
    >
        @if($error)
            <div class="rc-card"><strong>{{ $error }}</strong></div>
        @endif

        @if($showAccountPreparationNotice ?? false)
            <div class="rc-plyrcard-preparing-banner-v129" role="status" wire:poll.10s="checkRecruitingAccountReadiness">
                <div class="rc-plyrcard-preparing-icon-v129" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v3M12 18v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M3 12h3M18 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                </div>
                <div class="rc-plyrcard-preparing-copy-v129">
                    <strong>We are preparing your PLYRCARD</strong>
                    <span>Your PLYRCARD is being prepared for publication. Complete your profile while our team reviews your information and gets your public PLYRCARD ready.</span>
                </div>
                <a class="rc-plyrcard-preparing-action-v129" href="{{ url('/admin/my-profile') }}">Complete My Profile</a>
            </div>
        @endif

        @if(! (in_array($section, ['dashboard', 'schools', 'favorites', 'lists', 'compose', 'templates', 'campaigns', 'conversations', 'schedule', 'settings'], true) || $isStatDrawerOpen))
            <div class="rc-global-search-bar">
                <div class="rc-global-search-shell" role="search" aria-label="Global Recruiting Center search">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <input type="search" placeholder="Search schools, coaches, conferences, divisions, student lists..." wire:model.live.debounce.300ms="search">
                    @if($search !== '')
                        <button type="button" class="rc-global-search-clear" wire:click="clearGlobalSearch" aria-label="Clear search">×</button>
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
                <div class="rc-refresh-dropdown-v2" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
                    <button
                        type="button"
                        class="rc-home-refresh-v2"
                        x-on:click="open = ! open"
                        wire:loading.attr="disabled"
                        wire:target="refreshStatsOnly,refreshCoachDatabase,refreshData,startBackgroundLoad,loadNextBatch"
                        aria-label="Open refresh options"
                        title="Refresh options"
                        @disabled($isRecruitingSyncRunning ?? false)
                    >
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6v5h-5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M19.2 11A7.6 7.6 0 1 0 17 16.35" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div class="rc-refresh-menu-v2" x-cloak x-show="open" x-transition.origin.top.right>
                        <button type="button" class="rc-refresh-menu-item-v2" wire:click="refreshStatsOnly" x-on:click="open = false" @disabled($isRecruitingSyncRunning ?? false)>
                            <span class="rc-refresh-menu-icon-v2"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19V5M4 19h16M8 16v-5M13 16V8M18 16v-8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span class="rc-refresh-menu-copy-v2"><strong>Reload stats only</strong><small>Sync email sent, profile views, and social clicks from GHL cache fields.</small></span>
                        </button>
                        <button type="button" class="rc-refresh-menu-item-v2" wire:click="refreshCoachDatabase" x-on:click="open = false" @disabled($isRecruitingSyncRunning ?? false)>
                            <span class="rc-refresh-menu-icon-v2"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M8 4v4M16 10v4M11 16v4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg></span>
                            <span class="rc-refresh-menu-copy-v2"><strong>{{ ($isRecruitingSyncRunning ?? false) ? 'Reload running' : 'Reload whole Coach Database' }}</strong><small>{{ ($isRecruitingSyncRunning ?? false) ? 'A locked background sync is already running; existing rows stay visible.' : 'Reload schools, coaches, logos, tags, filters, and stats from GHL without blanking current data.' }}</small></span>
                        </button>
                    </div>
                </div>
                <button type="button" class="rc-home-dark-toggle-v2" data-plyr-dark-toggle aria-label="Toggle dark mode" aria-pressed="false">
                    <svg class="rc-dark-icon-moon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 14.35A8.5 8.5 0 0 1 9.65 3A8.75 8.75 0 1 0 21 14.35Z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg class="rc-dark-icon-sun" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 17a5 5 0 1 0 0-10a5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="1.9"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                </button>
            </div>
        @endif

        @if($section === 'dashboard' || $isStatDrawerOpen)
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $dashboardTopSchools = collect($this->dashboardTopEngagedSchools ?? [])->take(5)->values()->all();
                $dashboardRecentActivity = collect($this->dashboardRecentActivity ?? [])->values()->all();

                $authUser = auth()->user();
                $athleteName = trim((string) (method_exists($authUser, 'getFilamentName') ? $authUser?->getFilamentName() : ''));
                if ($athleteName === '') {
                    $athleteName = trim((string) (($authUser?->first_name ?? '') . ' ' . ($authUser?->last_name ?? '')));
                }
                if ($athleteName === '') {
                    $athleteName = trim((string) ($authUser?->name ?? ''));
                }
                $firstName = $athleteName !== '' ? $athleteName : 'Player';

                $savedSchools = (int) ($dashboardMetrics['saved_schools'] ?? 0);
                $favoriteSchools = max(
                    (int) ($dashboardMetrics['favorite_schools'] ?? 0),
                    count($this->favoriteSchools ?? []),
                );

                $trackedWebsiteViews = (int) ($dashboardMetrics['view_profile_website'] ?? $dashboardMetrics['website_clicks'] ?? 0);
                $trackedInstagramViews = (int) ($dashboardMetrics['view_profile_instagram'] ?? $dashboardMetrics['instagram_clicks'] ?? 0);
                $trackedYoutubeViews = (int) ($dashboardMetrics['view_profile_youtube'] ?? $dashboardMetrics['youtube_clicks'] ?? 0);
                $trackedXViews = (int) ($dashboardMetrics['view_profile_x'] ?? $dashboardMetrics['x_clicks'] ?? $dashboardMetrics['twitter_clicks'] ?? 0);
                $trackedEmailLinkViews = (int) ($dashboardMetrics['view_profile_email_link'] ?? 0);
                $trackedProfileComponentTotal = $trackedWebsiteViews + $trackedInstagramViews + $trackedYoutubeViews + $trackedXViews + $trackedEmailLinkViews;
                $trackedProfileTotal = max((int) ($dashboardMetrics['view_profile_total'] ?? 0), (int) ($dashboardMetrics['profile_views'] ?? 0), $trackedProfileComponentTotal);
                $profileViews = $trackedProfileTotal;
                $profileUniqueContacts = max((int) ($dashboardMetrics['profile_view_unique_contact_count'] ?? 0), (int) ($dashboardMetrics['unique_profile_view_contacts'] ?? 0), (int) ($dashboardMetrics['unique_profile_view_count'] ?? 0), $trackedProfileTotal > 0 ? 1 : 0);
                $profileUniqueSchools = max((int) ($dashboardMetrics['profile_view_unique_school_count'] ?? 0), (int) ($dashboardMetrics['schools_with_profile_views'] ?? 0));
                $profileSchoolClicks = max((int) ($dashboardMetrics['profile_view_school_click_count'] ?? 0), (int) ($dashboardMetrics['school_profile_views'] ?? 0), (int) ($dashboardMetrics['school_profile_view_count'] ?? 0));
                // Coach Engagement is social-link activity only. Keep the dashboard
                // summary tied to the same authoritative rows shown in its drawer.
                $engagementUniqueCoaches = (int) ($dashboardMetrics['engagement_unique_coaches'] ?? $dashboardMetrics['unique_link_click_contacts'] ?? 0);
                $engagementUniqueSchools = (int) ($dashboardMetrics['engagement_unique_schools'] ?? $dashboardMetrics['schools_with_clicks'] ?? 0);

                $savedEmailSentCount = max(0, (int) (auth()->user()?->total_emails_sent ?? 0));
                $emailSentCount = $savedEmailSentCount > 0
                    ? $savedEmailSentCount
                    : max((int) ($dashboardMetrics['email_sent_count'] ?? 0), (int) ($dashboardMetrics['emails_sent'] ?? 0), (int) ($dashboardMetrics['personal_emails_sent'] ?? 0) + (int) ($dashboardMetrics['campaigns_sent'] ?? 0));
                $hasSavedEmailSentCount = $savedEmailSentCount > 0;
                $emailOpenCount = (int) ($dashboardMetrics['email_open_count'] ?? $dashboardMetrics['email_opens'] ?? 0);
                $emailClickCount = (int) ($dashboardMetrics['email_click_count'] ?? $dashboardMetrics['email_clicks'] ?? 0);
                $socialClickCount = (int) ($dashboardMetrics['instagram_click_count'] ?? 0)
                    + (int) ($dashboardMetrics['youtube_click_count'] ?? 0)
                    + (int) ($dashboardMetrics['x_click_count'] ?? 0);
                $emailsSent = $emailSentCount;

                $coachReplies = (int) ($dashboardMetrics['coach_replies'] ?? 0);
                $engagedSchools = (int) ($dashboardMetrics['engaged_schools'] ?? count($dashboardTopSchools));
                // Match the Coach Engagement drawer exactly: Instagram + YouTube + X.
                $coachEngagementTotal = $socialClickCount;

                $profileCompletion = 0;
                $profileUrl = '#';
                $profileMissingSections = [];
                $profileSectionProgress = [];
                $profileAchievements = [];

                if ($authUser) {
                    try {
                        $profileCompletion = (int) app(\App\Services\ProfileCompletionService::class)->calculate($authUser);
                    } catch (\Throwable $exception) {
                        $profileHasValue = function (mixed $value): bool {
                            if (is_null($value)) {
                                return false;
                            }

                            if (is_string($value)) {
                                return trim($value) !== '';
                            }

                            if (is_array($value)) {
                                return count(array_filter($value, fn ($item) => ! is_null($item) && $item !== '')) > 0;
                            }

                            return true;
                        };

                        $coreFields = [
                            'first_name',
                            'last_name',
                            'email',
                            'phone',
                            'birth',
                            'gender',
                            'country',
                            'city',
                            'sport',
                            'height',
                            'weight',
                            'player_bio',
                            'player_image',
                            'plyrcard_image',
                            'school_id',
                            'club_id',
                            'league_id',
                            'featured_video_url',
                            'ig_handle',
                        ];

                        $sportSpecificFields = [
                            'position',
                            'dominant_foot',
                            'jersey_number',
                            'max_speed',
                            'natl_team_exp',
                            'national_team_id',
                            'national_team_period',
                        ];

                        $completedCore = collect($coreFields)
                            ->filter(fn ($field) => $profileHasValue($authUser->{$field} ?? null))
                            ->count();

                        $corePercentage = count($coreFields)
                            ? ($completedCore / count($coreFields)) * 100
                            : 0;

                        $completedSportSpecific = collect($sportSpecificFields)
                            ->filter(fn ($field) => $profileHasValue($authUser->{$field} ?? null))
                            ->count();

                        $sportBonus = count($sportSpecificFields)
                            ? ($completedSportSpecific / count($sportSpecificFields)) * 10
                            : 0;

                        $profileCompletion = (int) min(100, round($corePercentage + $sportBonus));
                    }

                    try {
                        $profileUrl = \App\Filament\Resources\Profiles\ProfileResource::getUrl('index');
                    } catch (\Throwable $exception) {
                        $profileUrl = url('/admin/profiles');
                    }

                    $profileHasValue = function (mixed $value): bool {
                        if (is_null($value)) {
                            return false;
                        }

                        if (is_string($value)) {
                            return trim($value) !== '';
                        }

                        if (is_array($value)) {
                            return count(array_filter($value, fn ($item) => ! is_null($item) && $item !== '')) > 0;
                        }

                        return true;
                    };

                    $profileSections = [
                        [
                            'key' => 'basic-information',
                            'title' => 'Basic Information',
                            'items' => [
                                'first_name' => 'First name',
                                'last_name' => 'Last name',
                                'email' => 'Email',
                                'phone' => 'Phone',
                                'birth' => 'Birth date',
                                'gender' => 'Gender',
                            ],
                        ],
                        [
                            'key' => 'location',
                            'title' => 'Location',
                            'items' => [
                                'country' => 'Country',
                                'city' => 'City',
                            ],
                        ],
                        [
                            'key' => 'athletic-profile',
                            'title' => 'Athletic Profile',
                            'items' => [
                                'sport' => 'Sport',
                                'position' => 'Position',
                                'dominant_foot' => 'Dominant foot',
                                'height' => 'Height',
                                'weight' => 'Weight',
                                'jersey_number' => 'Jersey number',
                                'max_speed' => 'Max speed',
                                'player_bio' => 'Player bio',
                            ],
                        ],
                        [
                            'key' => 'associations',
                            'title' => 'Associations',
                            'items' => [
                                'school_id' => 'School',
                                'club_id' => 'Club',
                                'league_id' => 'League',
                            ],
                        ],
                        [
                            'key' => 'media-branding',
                            'title' => 'Media & Branding',
                            'items' => [
                                'player_image' => 'Profile photo',
                                'plyrcard_image' => 'PlyrCard image',
                                'featured_video_url' => 'Featured video',
                                'ig_handle' => 'Instagram handle',
                            ],
                        ],
                        [
                            'key' => 'national-team',
                            'title' => 'National Team',
                            'items' => [
                                'natl_team_exp' => 'National team experience',
                                'national_team_id' => 'National team',
                                'national_team_period' => 'National team period',
                            ],
                        ],
                    ];

                    $profileSectionProgress = collect($profileSections)
                        ->map(function (array $section) use ($authUser, $profileUrl, $profileHasValue) {
                            $totalCount = count($section['items']);
                            $missingItems = collect($section['items'])
                                ->filter(fn ($label, $field) => ! $profileHasValue($authUser->{$field} ?? null))
                                ->map(function ($label) use ($profileUrl, $section) {
                                    return [
                                        'label' => $label,
                                        'url' => $profileUrl . '?section=' . $section['key'],
                                    ];
                                })
                                ->values()
                                ->all();

                            return [
                                'key' => $section['key'],
                                'title' => $section['title'],
                                'count' => count($missingItems),
                                'total' => $totalCount,
                                'items' => $missingItems,
                                'url' => $profileUrl . '?section=' . $section['key'],
                            ];
                        })
                        ->values()
                        ->all();

                    $profileMissingSections = collect($profileSectionProgress)
                        ->filter(fn (array $section) => $section['count'] > 0)
                        ->values()
                        ->all();

                    $profileAchievements = collect([
                        ['label' => 'Starter', 'threshold' => 25],
                        ['label' => 'Rising Talent', 'threshold' => 50],
                        ['label' => 'Scouted Ready', 'threshold' => 75],
                        ['label' => 'PlyrCard Complete', 'threshold' => 100],
                    ])->map(function (array $milestone) use ($profileCompletion): array {
                        return [
                            'label' => $milestone['label'],
                            'threshold' => $milestone['threshold'],
                            'unlocked' => $profileCompletion >= $milestone['threshold'],
                        ];
                    })->all();
                }

                $readinessScore = $profileCompletion;
                $profileCompletionSubtext = empty($profileMissingSections)
                    ? 'Profile complete!'
                    : count($profileMissingSections) . ' section' . (count($profileMissingSections) === 1 ? '' : 's') . ' to finish';

                $quickStats = [
                    [
                        'label' => 'Profile Completion',
                        'value' => $profileCompletion . '%',
                        'sub' => $profileCompletionSubtext,
                        'icon' => 'cap',
                        'tone' => 'coral',
                        'progress' => $profileCompletion,
                    ],
                    [
                        'label' => 'Profile Views',
                        'value' => number_format($profileViews),
                        'sub' => number_format($profileUniqueContacts) . ' unique contacts · ' . number_format($profileUniqueSchools) . ' schools',
                        'icon' => 'eye',
                        'tone' => 'blue',
                        'target' => 'profile-views',
                    ],
                    [
                        'label' => 'Favorites',
                        'value' => number_format($favoriteSchools),
                        'sub' => 'Schools saved',
                        'icon' => 'star',
                        'tone' => 'gold',
                        'target' => 'favorites',
                    ],
                    [
                        'label' => 'Coach Engagement',
                        'value' => number_format($coachEngagementTotal),
                        'sub' => number_format($engagementUniqueCoaches) . ' ' . \Illuminate\Support\Str::plural('coach', $engagementUniqueCoaches) . ' · ' . number_format($engagementUniqueSchools) . ' ' . \Illuminate\Support\Str::plural('school', $engagementUniqueSchools),
                        'icon' => 'mail',
                        'tone' => 'green',
                        'target' => 'coach-engagement',
                    ],
                    [
                        'label' => 'Emails Sent',
                        'value' => number_format($emailsSent),
                        'sub' => 'Tracked emails sent',
                        'icon' => 'chart',
                        'tone' => 'indigo',
                    ],
                ];

                $progressItems = collect($profileSectionProgress)
                    ->map(function (array $section) {
                        $missingCount = (int) ($section['count'] ?? 0);
                        $totalCount = max(1, (int) ($section['total'] ?? 1));
                        $completedCount = max(0, $totalCount - $missingCount);

                        return [
                            'label' => $section['title'],
                            'state' => $missingCount === 0
                                ? 'Complete'
                                : $completedCount . '/' . $totalCount . ' complete · ' . $missingCount . ' missing',
                            'done' => $missingCount === 0,
                            'url' => $section['url'] ?? '#',
                        ];
                    })
                    ->values()
                    ->all();

                $radarSchools = collect($dashboardTopSchools)->take(4)->values()->all();

                if (empty($radarSchools)) {
                    $radarSchools = collect($this->filteredSchools ?? [])->take(4)->values()->all();
                }

                $formatActivityTimeLabel = function ($time): string {
                    if (! $time) {
                        return 'Recent';
                    }

                    try {
                        $timeValue = \Illuminate\Support\Carbon::parse($time);

                        if ($timeValue->lessThan(now()->subYears(3)) || $timeValue->greaterThan(now()->addDay())) {
                            return 'Recent';
                        }

                        return $timeValue->diffForHumans();
                    } catch (\Throwable $exception) {
                        return 'Recent';
                    }
                };

                $dashboardActivityRows = collect($dashboardRecentActivity)->map(function ($activity) use ($formatActivityTimeLabel) {
                    $activityType = strtolower((string) ($activity['type'] ?? $activity['title'] ?? $activity['copy'] ?? 'activity'));
                    $tone = 'blue';
                    $icon = '◉';

                    if (str_contains($activityType, 'reply')) {
                        $tone = 'green';
                        $icon = '↩';
                    } elseif (str_contains($activityType, 'profile') || str_contains($activityType, 'view')) {
                        $tone = 'blue';
                        $icon = '◎';
                    } elseif (str_contains($activityType, 'instagram') || str_contains($activityType, 'youtube') || str_contains($activityType, 'social')) {
                        $tone = 'purple';
                        $icon = '↗';
                    } elseif (str_contains($activityType, 'email')) {
                        $tone = 'coral';
                        $icon = '✉';
                    } elseif (str_contains($activityType, 'favorite')) {
                        $tone = 'gold';
                        $icon = '☆';
                    } elseif (str_contains($activityType, 'smart')) {
                        $tone = 'purple';
                        $icon = '⊞';
                    }

                    $time = $activity['time'] ?? null;
                    $timeLabel = 'Recent';

                    if ($time) {
                        try {
                            $timeValue = \Illuminate\Support\Carbon::parse($time);
                            $timeLabel = $timeValue->lessThan(now()->subYears(3))
                                ? 'Recent'
                                : $timeValue->diffForHumans();
                        } catch (\Throwable $exception) {
                            $timeLabel = 'Recent';
                        }
                    }

                    return [
                        'title' => (string) ($activity['title'] ?? 'Recruiting activity'),
                        'copy' => trim(strip_tags((string) ($activity['copy'] ?? 'Recruiting update'))) ?: 'Recruiting update',
                        'url' => $activity['url'] ?? '#',
                        'tone' => $tone,
                        'icon' => $icon,
                        'time_label' => $timeLabel,
                    ];
                })->values();


                $radarScoreForSchool = function ($school): int {
                    return max(
                        (int) ($school['lead_score'] ?? 0),
                        (int) ($school['engagement_score'] ?? 0),
                        ((int) ($school['profile_views'] ?? 0) * 5)
                            + ((int) ($school['highlight_views'] ?? 0) * 4)
                            + ((int) ($school['trigger_link_clicks'] ?? $school['link_clicks'] ?? 0) * 3)
                            + ((int) ($school['replies'] ?? $school['coach_replies'] ?? 0) * 10)
                            + ((int) ($school['coach_count'] ?? 0))
                    );
                };

                $maxRadarScore = max(1, collect($radarSchools)->map(fn ($school) => $radarScoreForSchool($school))->max() ?: 1);

                $radarSchoolRows = collect($radarSchools)->map(function ($school) use ($radarScoreForSchool, $maxRadarScore) {
                    $schoolName = (string) ($school['name'] ?? 'School');
                    $schoolConference = (string) ($school['conference'] ?? $school['league'] ?? 'Conference');
                    $rawScore = $radarScoreForSchool($school);
                    $match = $rawScore > 0 ? max(1, min(100, (int) round(($rawScore / $maxRadarScore) * 100))) : 0;
                    $initials = collect(explode(' ', $schoolName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
                    $logoUrl = trim((string) (
                        $school['logo_url']
                        ?? $school['school_logo_url']
                        ?? $school['business_logo_url']
                        ?? data_get($school, 'head_coach.logo_url')
                        ?? data_get($school, 'head_coach.school_logo_url')
                        ?? data_get($school, 'head_coach.business_logo_url')
                        ?? ''
                    ));

                    return [
                        'id' => $school['id'] ?? $school['business_id'] ?? $schoolName,
                        'name' => $schoolName,
                        'conference' => $schoolConference,
                        'match' => $match,
                        'score' => $rawScore,
                        'initials' => strtoupper($initials ?: 'PC'),
                        'logo_url' => $logoUrl,
                    ];
                })->values();

                if ($radarSchoolRows->isEmpty()) {
                    $radarSchoolRows = collect([
                        ['id' => 'Virginia Commonwealth', 'name' => 'Virginia Commonwealth', 'conference' => 'Atlantic 10 Conference', 'match' => 94, 'initials' => 'VCU', 'logo_url' => ''],
                        ['id' => 'James Madison University', 'name' => 'James Madison University', 'conference' => 'Sun Belt Conference', 'match' => 91, 'initials' => 'JMU', 'logo_url' => ''],
                        ['id' => 'Duke University', 'name' => 'Duke University', 'conference' => 'ACC Conference', 'match' => 89, 'initials' => 'DU', 'logo_url' => ''],
                        ['id' => 'Wake Forest University', 'name' => 'Wake Forest University', 'conference' => 'ACC Conference', 'match' => 86, 'initials' => 'WF', 'logo_url' => ''],
                    ]);
                }

                $interestedSchoolRows = collect($dashboardTopSchools)->take(4)->values()->map(function ($school, $rank) {
                    $schoolName = (string) ($school['name'] ?? 'School');
                    $views = (int) (($school['profile_views'] ?? 0) + ($school['highlight_views'] ?? 0) + ($school['link_clicks'] ?? 0));
                    $score = max($views, (int) ($school['lead_score'] ?? $school['engagement_score'] ?? 0));
                    $initials = collect(explode(' ', $schoolName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');

                    return [
                        'rank' => $rank + 1,
                        'name' => $schoolName,
                        'score' => $score,
                        'initials' => strtoupper($initials ?: 'S'),
                        'logo_url' => trim((string) ($school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? '')),
                    ];
                })->values();

                if ($interestedSchoolRows->isEmpty()) {
                    $interestedSchoolRows = collect([
                        ['rank' => 1, 'name' => 'Virginia Commonwealth', 'score' => 14, 'initials' => 'VCU'],
                        ['rank' => 2, 'name' => 'University of Maryland', 'score' => 9, 'initials' => 'M'],
                        ['rank' => 3, 'name' => 'Florida State', 'score' => 7, 'initials' => 'FS'],
                        ['rank' => 4, 'name' => 'Indiana University', 'score' => 6, 'initials' => 'IU'],
                    ]);
                }
            @endphp

            <style id="rc-dashboard-email-live-fetch-v136">
                .rc-email-live-fetch-value-v136{align-items:center;gap:.45rem;font-size:1.15rem!important;letter-spacing:-.02em!important}
                .rc-email-live-fetch-status-v136{align-items:center;gap:.35rem}
                .rc-email-live-fetch-error-v136{color:#ef4444;font-weight:700}
            </style>

            <div class="rc-home-dashboard-v2"
                wire:key="rc-home-dashboard-email-fetch-{{ (int) $dashboardVisitVersion }}"
                wire:init="fetchDashboardEmailSentCount">
                @include('filament.partials.coach-database-header', [
                    'firstName' => $firstName,
                    'placeholder' => 'Search schools, coaches, conferences, divisions, lists...',
                    'showNewEmail' => true,
                ])

                {{-- When there are zero usable schools, always expose the sync state so the user can tell whether the database is loading, completed empty, or needs attention. Once schools exist, background refreshes remain silent. --}}
                @php
                    $hasUsableSchoolDatabase =
                        ! empty($this->filteredSchools ?? [])
                        || (int) ($this->filteredSchoolsCount ?? 0) > 0
                        || (int) ($loadedSchoolsCount ?? 0) > 0;

                    $hasRecruitingSyncState =
                        ($isLoadingDataset ?? false)
                        || ($isRecruitingSyncRunning ?? false)
                        || filled($recruitingSyncStatus ?? null)
                        || filled($recruitingSyncMessage ?? null)
                        || filled($cachedAt ?? null);

                    $shouldShowInitialSyncBanner = ! $hasUsableSchoolDatabase && $hasRecruitingSyncState;
                @endphp

                @if($shouldShowInitialSyncBanner)
                    @php
                        $reloadPercent = $isLoadingDataset
                            ? max(5, min(98, (int) ($remoteTotalSchools ? round(($loadedSchoolsCount / max(1, $remoteTotalSchools)) * 100) : min(96, max(1, $loadedPages) * 8))))
                            : ($isRecruitingSyncRunning ? 35 : (($recruitingSyncStatus ?? '') === 'completed' ? 100 : 10));
                        $reloadStatusLabel = match ($recruitingSyncStatus) {
                            'completed' => 'Synced',
                            'failed', 'failed_to_start' => 'Needs attention',
                            'already_running' => 'Already syncing',
                            'queued' => 'Queued',
                            default => ($isRecruitingSyncRunning ? 'Syncing' : 'Waiting'),
                        };
                    @endphp
                    <div class="rc-reload-status-v101" role="status" aria-live="polite">
                        <div class="rc-reload-main-v101">
                            <div class="rc-reload-copy-v101">
                                <strong>Recruiting Center is updating</strong>
                                <span>{{ $recruitingSyncMessage ?: 'Loading schools, coaches, and tracking stats from GHL. Existing data stays visible while this runs.' }}</span>
                            </div>
                            <span class="rc-reload-pill-v101"><i class="rc-reload-pulse-v101"></i>{{ $reloadStatusLabel }}</span>
                        </div>
                        <div class="rc-progress" aria-label="Coach Database loading progress"><span style="width:{{ $reloadPercent }}%"></span></div>
                        <div class="rc-reload-stats-v101">
                            <span><b>{{ number_format($loadedSchoolsCount) }}</b> schools cached</span>
                            <span><b>{{ number_format($loadedContactsCount) }}</b> coaches cached</span>
                            <span><b>{{ number_format($loadedPages) }}</b> pages processed</span>
                            @if($cachedAt)<span>Last cache {{ $cachedAt }}</span>@endif
                        </div>
                    </div>
                @endif


                <div class="rc-home-stats-v2">
                    @foreach($quickStats as $stat)
                        @if(! empty($stat['target']))
                            <button
                                type="button"
                                class="rc-home-stat-v2 is-{{ $stat['tone'] }} is-clickable"
                                x-on:click="dashboardDetail = @js($stat['target'])"
                            >
                        @else
                            <button
                                type="button"
                                class="rc-home-stat-v2 is-{{ $stat['tone'] }}"
                            >
                        @endif
                            <div class="rc-home-stat-icon-v2">
                                @switch($stat['icon'])
                                    @case('cap')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M3 8.5 12 4l9 4.5-9 4.5L3 8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="M7 11v4.2c0 1.6 2.2 3 5 3s5-1.4 5-3V11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        @break
                                    @case('eye')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8"/>
                                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                                        </svg>
                                        @break
                                    @case('star')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m12 3 2.7 5.5 6 .9-4.35 4.2 1.05 6-5.4-2.85-5.4 2.85 1.05-6L3.3 9.4l6-.9L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                        </svg>
                                        @break
                                    @case('mail')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 6h16v12H4V6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        @break
                                    @default
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M7 17 17 7M9 7h8v8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                @endswitch
                            </div>

                            <div class="rc-home-stat-copy-v2">
                                <div class="rc-home-stat-label-v2">{{ $stat['label'] }}</div>
                                @if($stat['label'] === 'Emails Sent')
                                    @if($hasSavedEmailSentCount)
                                        {{-- v148: keep the last persisted total visible while the fresh total is fetched. --}}
                                        <div class="rc-home-stat-value-v2">{{ $stat['value'] }}</div>
                                    @else
                                        {{-- No persisted total yet: preserve the existing visible first-load fetch state. --}}
                                        <div class="rc-home-stat-value-v2" wire:loading.remove wire:target="fetchDashboardEmailSentCount">{{ $stat['value'] }}</div>
                                        <div class="rc-home-stat-value-v2 rc-email-live-fetch-value-v136" wire:loading.flex wire:target="fetchDashboardEmailSentCount">
                                            <span class="rc-spinner-mini" aria-hidden="true"></span>
                                            <span>Fetching…</span>
                                        </div>
                                    @endif
                                @else
                                    <div class="rc-home-stat-value-v2">{{ $stat['value'] }}</div>
                                @endif
                            </div>

                            @if(isset($stat['progress']))
                                <div class="rc-home-progress-v2">
                                    <span style="width: {{ (int) $stat['progress'] }}%"></span>
                                </div>
                            @endif

                            @if($stat['label'] === 'Emails Sent')
                                @if($hasSavedEmailSentCount)
                                    <div class="rc-home-stat-sub-v2 rc-email-saved-refresh-v148">
                                        <span wire:loading.remove wire:target="fetchDashboardEmailSentCount">
                                            @if(filled($dashboardEmailFetchError ?? null))
                                                <span class="rc-email-live-fetch-error-v136">Unable to refresh · showing last saved count</span>
                                            @else
                                                {{ $dashboardEmailFetchStatus ?: 'Email activity' }}
                                            @endif
                                        </span>
                                        <span class="rc-email-saved-refresh-indicator-v148" wire:loading.inline-flex wire:target="fetchDashboardEmailSentCount" aria-live="polite">
                                            <span class="rc-email-saved-refresh-dot-v148" aria-hidden="true"></span>
                                            <span>Updating latest</span>
                                        </span>
                                    </div>
                                @else
                                    <div class="rc-home-stat-sub-v2" wire:loading.remove wire:target="fetchDashboardEmailSentCount">
                                        @if(filled($dashboardEmailFetchError ?? null))
                                            <span class="rc-email-live-fetch-error-v136">Unable to refresh · showing last saved count</span>
                                        @else
                                            <span>{{ $dashboardEmailFetchStatus ?: 'Email activity' }}</span>
                                        @endif
                                    </div>
                                    <div class="rc-home-stat-sub-v2 rc-email-live-fetch-status-v136" wire:loading.flex wire:target="fetchDashboardEmailSentCount">
                                        Updating sent email count…
                                    </div>
                                @endif
                            @else
                                <div class="rc-home-stat-sub-v2">{{ $stat['sub'] }}</div>
                            @endif
                        </button>
                    @endforeach
                </div>

                <style>
                    .rc-email-saved-refresh-v148 { min-height: 1.05rem; }
                    .rc-email-saved-refresh-indicator-v148 {
                        align-items: center;
                        gap: .32rem;
                        font-size: .68rem;
                        line-height: 1;
                        opacity: .68;
                    }
                    .rc-email-saved-refresh-dot-v148 {
                        width: .32rem;
                        height: .32rem;
                        border-radius: 999px;
                        background: currentColor;
                        animation: rcEmailSavedRefreshPulseV148 1.05s ease-in-out infinite;
                    }
                    @keyframes rcEmailSavedRefreshPulseV148 {
                        0%, 100% { opacity: .28; transform: scale(.82); }
                        50% { opacity: 1; transform: scale(1); }
                    }
                    @media (prefers-reduced-motion: reduce) {
                        .rc-email-saved-refresh-dot-v148 { animation: none; opacity: .72; }
                    }
                </style>

                <div class="rc-home-grid-v2">
                    <section class="rc-home-panel-v2 rc-home-progress-panel-v2">
                        <div class="rc-home-panel-head-v2">
                            <h2>Profile Progress</h2>
                        </div>

                        <div class="rc-home-progress-layout-v2">
                            <div class="rc-readiness-ring-v2" style="--ready: {{ $readinessScore }};">
                                <div>
                                    <strong>{{ $profileCompletion }}%</strong>
                                    <span>Profile Completion</span>
                                </div>
                            </div>

                            <div class="rc-check-list-v2">
                                @foreach($progressItems as $item)
                                    <div class="rc-check-row-v2 {{ $item['done'] ? 'is-done' : '' }}">
                                        <span class="rc-check-dot-v2">
                                            @if($item['done']) ✓ @endif
                                        </span>
                                        <span>
                                            <strong>{{ $item['label'] }}</strong>
                                            <small>{{ $item['state'] }}</small>
                                        </span>
                                    </div>
                                @endforeach

                                <div class="rc-profile-milestones-v2">
                                    @foreach($profileAchievements as $achievement)
                                        <span class="{{ $achievement['unlocked'] ? 'is-unlocked' : '' }}">
                                            {{ $achievement['label'] }}
                                        </span>
                                    @endforeach
                                </div>

                                <a class="rc-home-outline-btn-v2" href="{{ $profileUrl }}">Complete Profile</a>
                            </div>
                        </div>
                    </section>

                    <section class="rc-home-panel-v2">
                        <div class="rc-home-panel-head-v2">
                            <h2>Recent Activity</h2>
                            <a href="#">View All</a>
                        </div>

                        <div class="rc-home-activity-list-v2">
                            @forelse($dashboardActivityRows as $activityRow)
                                <a class="rc-home-activity-v2" href="{{ $activityRow['url'] ?? '#' }}">
                                    <span class="rc-home-activity-icon-v2 is-{{ $activityRow['tone'] ?? 'blue' }}">{{ $activityRow['icon'] ?? '◉' }}</span>

                                    <span class="rc-home-activity-copy-v2">
                                        <strong>{{ $activityRow['title'] ?? 'Recruiting activity' }}</strong>
                                        <small>{{ $activityRow['copy'] ?? 'Recruiting update' }}</small>
                                    </span>

                                    <span class="rc-home-activity-time-v2">{{ $activityRow['time_label'] ?? 'Recent' }}</span>
                                </a>
                            @empty
                                <div class="rc-home-empty-v2">Recent coach views, social clicks, email sends, and replies will appear here after the next sync.</div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="rc-home-lower-grid-v2">
                    <section class="rc-home-panel-v2 rc-radar-panel-v2">
                        <div class="rc-home-panel-head-v2">
                            <div>
                                <h2>On The Radar</h2>
                                <p>Local school records · engagement signals from GHL</p>
                            </div>
                            <a href="#">View All</a>
                        </div>

                        <div class="rc-radar-schools-v2">
                            @foreach($radarSchoolRows as $radarSchool)
                                <button type="button" class="rc-radar-card-v2" x-on:click.stop="openGlobalSchool(@js($radarSchool))">
                                    <span class="rc-radar-logo-v2 {{ empty($radarSchool['logo_url']) ? 'is-missing-logo' : '' }}">
                                        @if(! empty($radarSchool['logo_url']))
                                            <img src="{{ $radarSchool['logo_url'] }}" alt="{{ $radarSchool['name'] }} logo" loading="lazy" onerror="this.closest('.rc-radar-logo-v2').classList.add('is-missing-logo')">
                                        @endif
                                        <span class="rc-logo-fallback-text">{{ $radarSchool['initials'] }}</span>
                                    </span>
                                    <strong>{{ $radarSchool['name'] }}</strong>
                                    <small>{{ $radarSchool['conference'] }}</small>
                                    <em>{{ $radarSchool['match'] }}% Match</em>
                                </button>
                            @endforeach
                        </div>

                        <div class="rc-home-dots-v2">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </section>

                    <section class="rc-home-panel-v2">
                        <div class="rc-home-panel-head-v2">
                            <h2>Schools Most Interested</h2>
                            <span>Local schools + GHL engagement</span>
                        </div>

                        <div class="rc-interested-list-v2">
                            @foreach($interestedSchoolRows as $interestedSchool)
                                <button type="button" class="rc-interested-row-v2" x-on:click.stop="openGlobalSchool(@js($interestedSchool))">
                                    <span class="rc-interested-rank-v2">{{ $interestedSchool['rank'] }}</span>
                                    <span class="rc-interested-logo-v2 {{ empty($interestedSchool['logo_url']) ? 'is-missing-logo' : '' }}">
                                        @if(! empty($interestedSchool['logo_url']))
                                            <img src="{{ $interestedSchool['logo_url'] }}" alt="{{ $interestedSchool['name'] }} logo" loading="lazy" onerror="this.closest('.rc-interested-logo-v2').classList.add('is-missing-logo')">
                                        @endif
                                        <span class="rc-logo-fallback-text">{{ $interestedSchool['initials'] }}</span>
                                    </span>
                                    <span>
                                        <strong>{{ $interestedSchool['name'] }}</strong>
                                        <small>Profile views</small>
                                    </span>
                                    <b>{{ $interestedSchool['score'] }}</b>
                                </button>
                            @endforeach
                        </div>

                        <a class="rc-home-outline-btn-v2" href="#">View Full Analytics</a>
                    </section>
                </div>
            </div>
        @endif

        @if(in_array($section, ['dashboard', 'profile-views'], true))
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $dashboardTopSchools = collect($this->dashboardTopEngagedSchools ?? [])->values();
                $dashboardRecentActivity = collect($this->getDashboardRecentActivityProperty())->values();

                $websiteViews = (int) ($dashboardMetrics['view_profile_website'] ?? $dashboardMetrics['website_clicks'] ?? 0);
                $instagramViews = (int) ($dashboardMetrics['view_profile_instagram'] ?? $dashboardMetrics['instagram_clicks'] ?? 0);
                $youtubeViews = (int) ($dashboardMetrics['view_profile_youtube'] ?? $dashboardMetrics['youtube_clicks'] ?? 0);
                $xViews = (int) ($dashboardMetrics['view_profile_x'] ?? $dashboardMetrics['x_clicks'] ?? $dashboardMetrics['twitter_clicks'] ?? 0);
                $emailLinkViews = (int) ($dashboardMetrics['view_profile_email_link'] ?? 0);
                $profileViewsTotal = max((int) ($dashboardMetrics['view_profile_total'] ?? 0), (int) ($dashboardMetrics['profile_views'] ?? 0), $websiteViews + $instagramViews + $youtubeViews + $xViews + $emailLinkViews);
                $uniqueProfileViews = max((int) ($dashboardMetrics['profile_view_unique_contact_count'] ?? 0), (int) ($dashboardMetrics['unique_profile_view_contacts'] ?? 0), (int) ($dashboardMetrics['unique_profile_views'] ?? 0), (int) ($dashboardMetrics['unique_profile_view_count'] ?? 0), $profileViewsTotal > 0 ? 1 : 0);
                $ghlContactClicks = max((int) ($dashboardMetrics['ghl_contact_clicks'] ?? 0), (int) ($dashboardMetrics['contact_clicks'] ?? 0), (int) ($dashboardMetrics['contact_link_clicks'] ?? 0), $profileViewsTotal + (int) ($dashboardMetrics['link_clicks'] ?? 0));
                $profileSchoolClicks = max((int) ($dashboardMetrics['profile_view_school_click_count'] ?? 0), (int) ($dashboardMetrics['school_profile_views'] ?? 0), (int) ($dashboardMetrics['school_profile_view_count'] ?? 0), $profileViewsTotal);
                $profilePrograms = max(0, (int) ($dashboardMetrics['profile_view_unique_school_count'] ?? 0), (int) ($dashboardMetrics['schools_with_profile_views'] ?? 0), (int) ($dashboardMetrics['schools_with_clicks'] ?? 0));

                $profileBreakdownRows = collect([
                    ['title' => 'Website profile link', 'copy' => 'Website profile clicks', 'views' => $websiteViews, 'type' => 'Website', 'initials' => 'W', 'time_label' => 'Updated'],
                    ['title' => 'Instagram profile link', 'copy' => 'Instagram profile clicks', 'views' => $instagramViews, 'type' => 'Instagram', 'initials' => 'IG', 'time_label' => 'Updated'],
                    ['title' => 'YouTube highlight link', 'copy' => 'YouTube profile clicks', 'views' => $youtubeViews, 'type' => 'YouTube', 'initials' => 'YT', 'time_label' => 'Updated'],
                    ['title' => 'X profile link', 'copy' => 'X profile clicks', 'views' => $xViews, 'type' => 'X', 'initials' => 'X', 'time_label' => 'Updated'],
                    ['title' => 'Email profile link', 'copy' => 'Profile links clicked from email', 'views' => $emailLinkViews, 'type' => 'Email Link', 'initials' => 'EM', 'time_label' => 'Updated'],
                ])->filter(fn (array $row): bool => (int) ($row['views'] ?? 0) > 0)->values();

                // v126: The viewer table is coach/school activity only. Collapse repeated
                // snapshots for the same coach and rank by the highest tracked view count.
                // Platform summary rows are intentionally not mixed into this table.
                $profileActivitySource = collect($this->profileViewRows ?? [])->filter(fn ($activity) => is_array($activity))->values();
                if ($profileActivitySource->isEmpty()) {
                    $profileActivitySource = $dashboardRecentActivity
                        ->filter(fn ($activity) => is_array($activity) && str_contains(strtolower((string) ($activity['type'] ?? $activity['title'] ?? $activity['copy'] ?? '')), 'view'))
                        ->values();
                }

                $activityProfileRows = $profileActivitySource
                    ->map(function (array $activity) use ($formatActivityTimeLabel) {
                        $title = (string) ($activity['title'] ?? 'Coach viewed profile');
                        $copy = trim(strip_tags((string) ($activity['copy'] ?? 'Tracked profile activity'))) ?: 'Tracked profile activity';
                        $initials = collect(explode(' ', $title))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
                        $time = $activity['time'] ?? $activity['created_at'] ?? null;
                        $views = max(1, (int) ($activity['views'] ?? $activity['count'] ?? 0));

                        // Older activity snapshots sometimes keep the rolled-up count only
                        // in the copy, e.g. "16 tracked profile views".
                        if (preg_match('/(\d[\d,]*)\s+tracked\s+profile\s+views?/i', $copy, $matches)) {
                            $views = max($views, (int) str_replace(',', '', $matches[1]));
                        }

                        $schoolId = trim((string) ($activity['school_id'] ?? $activity['school_business_id'] ?? $activity['business_id'] ?? ''));
                        $coachKey = trim((string) ($activity['coach_id'] ?? $activity['coach_contact_id'] ?? $activity['contact_id'] ?? ''));
                        $identityKey = $coachKey !== ''
                            ? 'coach:' . $coachKey
                            : 'viewer:' . $schoolId . '|' . strtolower(trim($title));

                        return [
                            'identity_key' => $identityKey,
                            'school_id' => $schoolId,
                            'title' => $title,
                            'copy' => $copy,
                            'views' => $views,
                            'type' => (string) ($activity['platform'] ?? $activity['source'] ?? 'Profile'),
                            'logo' => $activity['logo'] ?? null,
                            'initials' => strtoupper($initials ?: 'PV'),
                            'time' => $time,
                            'time_label' => $formatActivityTimeLabel($time),
                        ];
                    })
                    ->groupBy('identity_key')
                    ->map(function ($rows) {
                        // Each cached activity row may itself already be rolled up, so use
                        // the largest count rather than summing snapshots and double-counting.
                        return collect($rows)->sortByDesc(fn ($row) => (int) ($row['views'] ?? 0))->first();
                    })
                    ->filter()
                    ->sortByDesc(fn ($row) => (int) ($row['views'] ?? 0))
                    ->take(30)
                    ->values();

                $profileViewRows = $activityProfileRows->values()->map(function ($row, $index) {
                    return array_merge($row, ['rank' => $index + 1]);
                });
            @endphp

            <div class="rc-stats-drawer-backdrop"
                x-show="dashboardDetail === 'profile-views'"
                x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:keydown.escape.window="dashboardDetail = ''"
                x-on:click.self="dashboardDetail = ''">
                <aside class="rc-stats-drawer-panel"
                    role="dialog"
                    aria-modal="true"
                    x-show="dashboardDetail === 'profile-views'"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-x-full opacity-80"
                    x-transition:enter-end="translate-x-0 opacity-100"
                    x-transition:leave="transition ease-in duration-120"
                    x-transition:leave-start="translate-x-0 opacity-100"
                    x-transition:leave-end="translate-x-full opacity-80">
                    <button type="button" class="rc-stats-drawer-close" x-on:click="dashboardDetail = ''" aria-label="Close details">×</button>
                    <div class="rc-detail-page-v2">
                <div class="rc-detail-header-v2">
                    <div>
                        <h1>Profile Views</h1>
                        <p>Tracked profile views from website, Instagram, YouTube, X, and email links.</p>
                    </div>
                    <form class="rc-detail-search-v2" wire:submit.prevent="$set('section', 'schools')">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <input type="search" placeholder="Search schools, coaches, conferences, divisions, lists..." wire:model.live.debounce.350ms="search">

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
                    </form>
                </div>

                <div class="rc-detail-stats-v2">
                    <div class="rc-detail-stat-v2 is-blue"><span>◎</span><div><small>Total Views</small><strong>{{ number_format($profileViewsTotal) }}</strong><em>Player website/profile views</em></div></div>
                    <div class="rc-detail-stat-v2 is-coral"><span>☷</span><div><small>Unique Contacts</small><strong>{{ number_format($uniqueProfileViews) }}</strong><em>Distinct coaches who viewed your profile</em></div></div>
                    <div class="rc-detail-stat-v2 is-purple"><span>▥</span><div><small>Schools Reached</small><strong>{{ number_format($profilePrograms) }}</strong><em>Schools represented by those viewers</em></div></div>
                </div>

                <section class="rc-detail-table-v2">
                    <header><h2>Who's Viewing You</h2><span>● Synced</span></header>
                    <div class="rc-detail-rows-v2">
                        @forelse($profileViewRows as $profileRow)
                            <button
                                type="button"
                                class="rc-detail-row-v2"
                                @if(! empty($profileRow['school_id']))
                                    x-on:click.stop="dashboardDetail = ''; $nextTick(() => openGlobalSchool(@js((string) $profileRow['school_id'])))"
                                @else
                                    disabled
                                @endif
                            >
                                <span class="rc-detail-rank-v2">#{{ $profileRow['rank'] }}</span>
                                <span class="rc-detail-avatar-v2">
                                    @if(! empty($profileRow['logo']))
                                        <img src="{{ $profileRow['logo'] }}" alt="{{ $profileRow['title'] }}">
                                    @else
                                        {{ $profileRow['initials'] }}
                                    @endif
                                </span>
                                <span class="rc-detail-person-v2"><strong>{{ $profileRow['title'] }}</strong><small>{{ $profileRow['copy'] }}</small></span>
                                <span class="rc-detail-pill-v2">{{ $profileRow['type'] }}</span>
                                <span class="rc-detail-count-v2"><b>{{ $profileRow['views'] }}</b><small>{{ \Illuminate\Support\Str::plural('view', $profileRow['views']) }}</small></span>
                                <span class="rc-detail-time-v2">{{ $profileRow['time_label'] }}</span>
                                <span class="rc-detail-chevron-v2">›</span>
                            </button>
                        @empty
                            <div class="rc-home-empty-v2">Profile view activity will appear here after coaches click tracked links.</div>
                        @endforelse
                    </div>
                </section>
            </div>
                </aside>
            </div>
        @endif

        <script>
            (function () {
                window.__rcCoachEngagementFilter = window.__rcCoachEngagementFilter || '';

                window.rcApplyCoachEngagementFilter = function () {
                    const normalized = ['instagram', 'youtube', 'x'].includes(String(window.__rcCoachEngagementFilter || '').toLowerCase())
                        ? String(window.__rcCoachEngagementFilter).toLowerCase()
                        : '';
                    const drawer = document.querySelector('[data-rc-modal-id="coach-engagement"]');

                    if (!drawer) return;

                    drawer.dataset.engagementPlatform = normalized;

                    drawer.querySelectorAll('[data-engagement-filter]').forEach((card) => {
                        const active = card.dataset.engagementFilter === normalized;
                        card.classList.toggle('is-filter-active', active);
                        card.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });

                    drawer.querySelectorAll('[data-engagement-row]').forEach((row) => {
                        const rowPlatform = String(row.dataset.platform || '').toLowerCase();
                        row.hidden = normalized !== '' && rowPlatform !== normalized;
                    });

                    const title = drawer.querySelector('[data-engagement-table-title]');
                    if (title) {
                        const labels = { instagram: 'Instagram', youtube: 'YouTube', x: 'X (Twitter)' };
                        title.textContent = normalized ? `Clicks — ${labels[normalized]}` : "Who's Clicking";
                    }

                    const clear = drawer.querySelector('[data-engagement-clear]');
                    if (clear) clear.hidden = normalized === '';
                };

                window.rcFilterCoachEngagement = function (source, platform) {
                    const normalized = ['instagram', 'youtube', 'x'].includes(String(platform || '').toLowerCase())
                        ? String(platform).toLowerCase()
                        : '';

                    window.__rcCoachEngagementFilter = normalized;
                    window.rcApplyCoachEngagementFilter();
                };

                if (!window.__rcCoachEngagementFilterObserverBound) {
                    window.__rcCoachEngagementFilterObserverBound = true;
                    let scheduled = false;
                    const scheduleApply = () => {
                        if (scheduled) return;
                        scheduled = true;
                        window.requestAnimationFrame(() => {
                            scheduled = false;
                            window.rcApplyCoachEngagementFilter?.();
                        });
                    };

                    const observer = new MutationObserver((mutations) => {
                        if (!window.__rcCoachEngagementFilter) return;
                        if (mutations.some((mutation) => mutation.type === 'childList')) scheduleApply();
                    });

                    const bindObserver = () => {
                        if (!document.body) return;
                        observer.disconnect();
                        observer.observe(document.body, { childList: true, subtree: true });
                        scheduleApply();
                    };

                    document.addEventListener('DOMContentLoaded', bindObserver, { once: true });
                    document.addEventListener('livewire:navigated', scheduleApply);
                    document.addEventListener('livewire:init', () => {
                        if (window.Livewire?.hook) {
                            window.Livewire.hook('morph.updated', scheduleApply);
                        }
                    }, { once: true });

                    if (document.body) bindObserver();
                }

                window.rcApplyCoachEngagementFilter();
            })();
        </script>

        <style>
            [data-rc-modal-id="coach-engagement"] [data-engagement-row][hidden] { display: none !important; }
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card { cursor:pointer; transition:transform .14s ease, box-shadow .14s ease, border-color .14s ease; }
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card:hover { transform:translateY(-1px); }
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card.is-filter-active { border-color:#ff6338 !important; box-shadow:0 0 0 2px rgba(255,99,56,.12),0 10px 24px rgba(15,23,42,.08); }
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card {
                position: relative;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-height: 8rem !important;
                padding: 1.15rem 4.25rem !important;
                text-align: center !important;
            }
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card > span {
                position: absolute !important;
                left: 1.2rem !important;
                top: 1.2rem !important;
                width: 2.9rem !important;
                height: 2.9rem !important;
            }
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card > div {
                width: 100%;
                min-width: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card small,
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card strong,
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card em { text-align:center !important; }
            [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card strong { font-size:2rem !important; }
            @media (max-width: 720px) {
                [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card { padding:1rem 1rem 1rem 4.6rem !important; text-align:left !important; }
                [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card > div { align-items:flex-start; }
                [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card small,
                [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card strong,
                [data-rc-modal-id="coach-engagement"] .rc-engagement-filter-card em { text-align:left !important; }
            }
            .rc-engagement-brand-icon-v124 { width:1.7rem; height:1.7rem; object-fit:contain; display:block; }
        </style>

        @if(in_array($section, ['dashboard', 'coach-engagement'], true))
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $dashboardRecentActivity = collect($this->dashboardRecentActivity ?? [])->values();

                $xClicks = (int) ($dashboardMetrics['x_click_count'] ?? $dashboardMetrics['x_clicks'] ?? $dashboardMetrics['twitter_clicks'] ?? 0);
                $igClicks = (int) ($dashboardMetrics['instagram_click_count'] ?? $dashboardMetrics['instagram_clicks'] ?? 0);
                $ytClicks = (int) ($dashboardMetrics['youtube_click_count'] ?? $dashboardMetrics['youtube_clicks'] ?? 0);

                $socialIconUrls = [
                    'instagram' => 'https://img.icons8.com/fluency/48/instagram-new.png',
                    'youtube' => 'https://img.icons8.com/color/48/youtube-play.png',
                    'x' => 'https://img.icons8.com/ios-filled/50/x.png',
                ];

                $normalizeSocialPlatform = function (array $row): string {
                    $raw = strtolower(trim((string) (
                        $row['platform_icon_key']
                        ?? $row['platform']
                        ?? $row['platform_key']
                        ?? $row['type']
                        ?? $row['title']
                        ?? ''
                    )));

                    return match (true) {
                        str_contains($raw, 'instagram'), $raw === 'ig' => 'instagram',
                        str_contains($raw, 'youtube'), str_contains($raw, 'you_tube'), $raw === 'yt' => 'youtube',
                        $raw === 'x', str_contains($raw, 'twitter'), str_contains($raw, 'x.com'), str_contains($raw, 'social_click_x') => 'x',
                        default => '',
                    };
                };

                $coachEngagementRows = collect($this->coachEngagementRows ?? [])
                    ->filter(fn ($row): bool => is_array($row))
                    ->map(function (array $row) use ($normalizeSocialPlatform) {
                        $canonical = $normalizeSocialPlatform($row);
                        if ($canonical === '') return null;

                        return array_merge($row, [
                            'platform_key' => $canonical,
                            'platform' => match ($canonical) {
                                'instagram' => 'Instagram',
                                'youtube' => 'YouTube',
                                'x' => 'X (Twitter)',
                            },
                            'platform_class' => match ($canonical) {
                                'instagram' => 'is-pink',
                                'youtube' => 'is-red',
                                'x' => 'is-neutral',
                            },
                            'clicks' => max(1, (int) ($row['clicks'] ?? $row['count'] ?? 1)),
                            'time_label' => (string) ($row['time_label'] ?? 'Recent'),
                        ]);
                    })
                    ->filter()
                    ->values();

                if ($coachEngagementRows->isEmpty()) {
                    $coachEngagementRows = $dashboardRecentActivity
                        ->filter(fn ($row): bool => is_array($row))
                        ->map(function (array $row) use ($normalizeSocialPlatform, $formatActivityTimeLabel) {
                            $canonical = $normalizeSocialPlatform($row);
                            if ($canonical === '') return null;
                            $time = $row['time'] ?? $row['created_at'] ?? null;

                            return [
                                'title' => (string) ($row['title'] ?? 'Tracked coach engagement'),
                                'copy' => trim(strip_tags((string) ($row['copy'] ?? 'Social click activity'))) ?: 'Social click activity',
                                'school_id' => (string) ($row['school_id'] ?? ''),
                                'platform_key' => $canonical,
                                'platform' => match ($canonical) {
                                    'instagram' => 'Instagram',
                                    'youtube' => 'YouTube',
                                    'x' => 'X (Twitter)',
                                },
                                'platform_class' => match ($canonical) {
                                    'instagram' => 'is-pink',
                                    'youtube' => 'is-red',
                                    'x' => 'is-neutral',
                                },
                                'clicks' => max(1, (int) ($row['clicks'] ?? $row['count'] ?? 1)),
                                'time_label' => $formatActivityTimeLabel($time),
                            ];
                        })
                        ->filter()
                        ->take(30)
                        ->values();
                }

                // v126: Highest social-click totals always rank first, regardless of source.
                $coachEngagementRows = $coachEngagementRows
                    ->sortByDesc(fn ($row) => (int) ($row['clicks'] ?? $row['count'] ?? 0))
                    ->values();
            @endphp

            <div class="rc-stats-drawer-backdrop"
                data-rc-modal-id="coach-engagement"
                data-engagement-platform=""
                x-show="dashboardDetail === 'coach-engagement'"
                x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:keydown.escape.window="dashboardDetail = ''"
                x-on:click.self="dashboardDetail = ''">
                <aside class="rc-stats-drawer-panel"
                    role="dialog"
                    aria-modal="true"
                    x-show="dashboardDetail === 'coach-engagement'"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-x-full opacity-80"
                    x-transition:enter-end="translate-x-0 opacity-100"
                    x-transition:leave="transition ease-in duration-120"
                    x-transition:leave-start="translate-x-0 opacity-100"
                    x-transition:leave-end="translate-x-full opacity-80"
                    x-on:click.stop>
                    <button type="button" class="rc-stats-drawer-close" x-on:click="dashboardDetail = ''" aria-label="Close details">×</button>
                    <div class="rc-detail-page-v2">
                        <div class="rc-detail-header-v2">
                            <div>
                                <h1>Coach Engagement</h1>
                                <p>How coaches are engaging with your social platforms, and who's clicking through.</p>
                            </div>
                        </div>

                        <div class="rc-detail-stats-v2">
                            <button type="button" class="rc-detail-stat-v2 is-neutral rc-engagement-filter-card" data-engagement-filter="x" aria-pressed="false" onclick="window.rcFilterCoachEngagement(this, 'x')">
                                <span><img class="rc-engagement-brand-icon-v124" src="{{ $socialIconUrls['x'] }}" alt="X" referrerpolicy="no-referrer"></span>
                                <div><small>X (Twitter)</small><strong>{{ number_format($xClicks) }}</strong><em>{{ number_format(max(0, $xClicks)) }} clicks</em></div>
                            </button>
                            <button type="button" class="rc-detail-stat-v2 is-pink rc-engagement-filter-card" data-engagement-filter="instagram" aria-pressed="false" onclick="window.rcFilterCoachEngagement(this, 'instagram')">
                                <span><img class="rc-engagement-brand-icon-v124" src="{{ $socialIconUrls['instagram'] }}" alt="Instagram" referrerpolicy="no-referrer"></span>
                                <div><small>Instagram</small><strong>{{ number_format($igClicks) }}</strong><em>{{ number_format(max(0, $igClicks)) }} clicks</em></div>
                            </button>
                            <button type="button" class="rc-detail-stat-v2 is-red rc-engagement-filter-card" data-engagement-filter="youtube" aria-pressed="false" onclick="window.rcFilterCoachEngagement(this, 'youtube')">
                                <span><img class="rc-engagement-brand-icon-v124" src="{{ $socialIconUrls['youtube'] }}" alt="YouTube" referrerpolicy="no-referrer"></span>
                                <div><small>YouTube</small><strong>{{ number_format($ytClicks) }}</strong><em>{{ number_format(max(0, $ytClicks)) }} clicks</em></div>
                            </button>
                        </div>

                        <section class="rc-detail-table-v2">
                            <header>
                                <h2 data-engagement-table-title>Who's Clicking</h2>
                                <span style="display:inline-flex;align-items:center;gap:.55rem">
                                    <button type="button" data-engagement-clear hidden onclick="window.rcFilterCoachEngagement(this, '')" class="rc-home-link-v2" style="border:0;background:transparent;cursor:pointer">Show All</button>
                                    <span>● Synced</span>
                                </span>
                            </header>
                            <div class="rc-detail-rows-v2">
                                @forelse($coachEngagementRows as $engagementRow)
                                    @php
                                        $platformKey = (string) ($engagementRow['platform_key'] ?? '');
                                        $platformClass = (string) ($engagementRow['platform_class'] ?? 'is-neutral');
                                        $platformLabel = (string) ($engagementRow['platform'] ?? 'Social');
                                        $clickCount = max(1, (int) ($engagementRow['clicks'] ?? $engagementRow['count'] ?? 1));
                                    @endphp
                                    <button type="button"
                                        class="rc-detail-row-v2 is-engagement"
                                        data-engagement-row
                                        data-platform="{{ $platformKey }}"
                                        @if(! empty($engagementRow['school_id']))
                                            x-on:click.stop="dashboardDetail = ''; $nextTick(() => openGlobalSchool(@js((string) $engagementRow['school_id'])))"
                                        @else
                                            disabled
                                        @endif>
                                        <span class="rc-detail-platform-icon-v2 {{ $platformClass }}">
                                            @if(isset($socialIconUrls[$platformKey]))
                                                <img class="rc-engagement-brand-icon-v124" src="{{ $socialIconUrls[$platformKey] }}" alt="{{ $platformLabel }}" referrerpolicy="no-referrer">
                                            @else
                                                •
                                            @endif
                                        </span>
                                        <span class="rc-detail-person-v2"><strong>{{ $engagementRow['title'] ?? 'Coach engagement' }}</strong><small>{{ $engagementRow['copy'] ?? 'Tracked social click activity' }}</small></span>
                                        <span class="rc-detail-pill-v2 {{ $platformClass }}">{{ $platformLabel }}</span>
                                        <span class="rc-detail-count-v2"><b>{{ $clickCount }}</b><small>{{ \Illuminate\Support\Str::plural('click', $clickCount) }}</small></span>
                                        <span class="rc-detail-time-v2">{{ $engagementRow['time_label'] ?? 'Recent' }}</span>
                                        <span class="rc-detail-chevron-v2" aria-hidden="true">›</span>
                                    </button>
                                @empty
                                    <div class="rc-home-empty-v2">Coach engagement will appear here after coaches click tracked Instagram, YouTube, or X links.</div>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        @endif

        @if($section === 'emails-sent')
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $dashboardRecentActivity = collect($this->dashboardRecentActivity ?? [])->values();

                $emailSentCount = max((int) ($dashboardMetrics['email_sent_count'] ?? 0), (int) ($dashboardMetrics['emails_sent'] ?? 0), (int) ($dashboardMetrics['personal_emails_sent'] ?? 0) + (int) ($dashboardMetrics['campaigns_sent'] ?? 0));
                $emailOpenCount = (int) ($dashboardMetrics['email_open_count'] ?? $dashboardMetrics['email_opens'] ?? 0);
                $emailClickCount = (int) ($dashboardMetrics['email_click_count'] ?? $dashboardMetrics['email_clicks'] ?? 0);
                $emailProfileLinkCount = (int) ($dashboardMetrics['view_profile_email_link'] ?? 0);

                $emailRows = $dashboardRecentActivity
                    ->filter(fn ($activity) => str_contains(strtolower((string) ($activity['type'] ?? $activity['title'] ?? $activity['copy'] ?? '')), 'email'))
                    ->take(12)
                    ->values()
                    ->map(function ($row, $index) use ($formatActivityTimeLabel) {
                        $time = $row['time'] ?? null;

                        return [
                            'rank' => $index + 1,
                            'title' => (string) ($row['title'] ?? 'Email activity'),
                            'copy' => trim(strip_tags((string) ($row['copy'] ?? 'Tracked email event'))) ?: 'Tracked email event',
                            'type' => (string) ($row['type'] ?? 'Email'),
                            'count' => (int) ($row['count'] ?? $row['clicks'] ?? 1),
                            'time_label' => $formatActivityTimeLabel($time),
                        ];
                    });

                if ($emailRows->isEmpty()) {
                    $emailRows = collect([
                        ['rank' => 1, 'title' => 'Emails sent', 'copy' => 'Emails sent from the recruiting center', 'type' => 'Sent', 'count' => $emailSentCount, 'time_label' => 'Updated'],
                        ['rank' => 2, 'title' => 'Emails opened', 'copy' => 'Emails opened', 'type' => 'Open', 'count' => $emailOpenCount, 'time_label' => 'Updated'],
                        ['rank' => 3, 'title' => 'Email links clicked', 'copy' => 'Email links clicked', 'type' => 'Click', 'count' => $emailClickCount, 'time_label' => 'Updated'],
                        ['rank' => 4, 'title' => 'Email profile links clicked', 'copy' => 'Profile links clicked from email', 'type' => 'Profile Link', 'count' => $emailProfileLinkCount, 'time_label' => 'Updated'],
                    ])->filter(fn (array $row): bool => (int) ($row['count'] ?? 0) > 0)->values()->map(function ($row, $index) {
                        $row['rank'] = $index + 1;
                        return $row;
                    });
                }
            @endphp

            <div class="rc-stats-drawer-backdrop"
                x-data="{ open: true, close() { this.open = false; setTimeout(() => $wire.set('section', 'dashboard'), 130); } }"
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:keydown.escape.window="close()"
                x-on:click.self="close()">
                <aside class="rc-stats-drawer-panel"
                    role="dialog"
                    aria-modal="true"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-x-full opacity-80"
                    x-transition:enter-end="translate-x-0 opacity-100"
                    x-transition:leave="transition ease-in duration-120"
                    x-transition:leave-start="translate-x-0 opacity-100"
                    x-transition:leave-end="translate-x-full opacity-80">
                    <button type="button" class="rc-stats-drawer-close" x-on:click="close()" aria-label="Close details">×</button>
                    <div class="rc-detail-page-v2">
                <div class="rc-detail-header-v2">
                    <div>
                        <h1>Emails Sent</h1>
                        <p>Email sending, opens, and link clicks from recruiting emails.</p>
                    </div>
                    <form class="rc-detail-search-v2" wire:submit.prevent="$set('section', 'schools')">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <input type="search" placeholder="Search schools, coaches, conferences..." wire:model.live.debounce.350ms="search">
                    </form>
                </div>

                <div class="rc-detail-stats-v2">
                    <div class="rc-detail-stat-v2 is-coral"><span>✉</span><div><small>Sent</small><strong>{{ number_format($emailSentCount) }}</strong><em>Sent email count</em></div></div>
                    <div class="rc-detail-stat-v2 is-blue"><span>◉</span><div><small>Opened</small><strong>{{ number_format($emailOpenCount) }}</strong><em>Open count</em></div></div>
                    <div class="rc-detail-stat-v2 is-green"><span>↗</span><div><small>Clicked</small><strong>{{ number_format($emailClickCount) }}</strong><em>Click count</em></div></div>
                </div>

                <section class="rc-detail-table-v2">
                    <header><h2>Email Tracking</h2><span>● Updated</span></header>
                    <div class="rc-detail-rows-v2">
                        @forelse($emailRows as $emailRow)
                            <button type="button" class="rc-detail-row-v2">
                                <span class="rc-detail-rank-v2">#{{ $emailRow['rank'] }}</span>
                                <span class="rc-detail-avatar-v2">✉</span>
                                <span class="rc-detail-person-v2"><strong>{{ $emailRow['title'] }}</strong><small>{{ $emailRow['copy'] }}</small></span>
                                <span class="rc-detail-pill-v2">{{ $emailRow['type'] }}</span>
                                <span class="rc-detail-count-v2"><b>{{ $emailRow['count'] }}</b><small>{{ \Illuminate\Support\Str::plural('event', $emailRow['count']) }}</small></span>
                                <span class="rc-detail-time-v2">{{ $emailRow['time_label'] }}</span>
                                <span class="rc-detail-chevron-v2">›</span>
                            </button>
                        @empty
                            <div class="rc-home-empty-v2">Email activity will appear here after recruiting emails are sent and opened/clicked.</div>
                        @endforelse
                    </div>
                </section>
            </div>
                </aside>
            </div>
        @endif

        @if($section === 'schools')
            @php
                $discoverSchoolCount = (int) ($this->filteredSchoolsCount ?? 0);
                $discoverLoadedCount = (int) ($loadedSchoolsCount ?? 0);
                $discoverSearchTotal = max($discoverSchoolCount, $discoverLoadedCount);
                $discoverDivisionTabs = [
                    '' => 'All Divisions',
                    'NCAA D-I' => 'NCAA D-I',
                    'NCAA D-II' => 'NCAA D-II',
                    'NCAA D-III' => 'NCAA D-III',
                    'NAIA' => 'NAIA',
                    'NJCAA' => 'NJCAA',
                ];
                $discoverShownCount = count($this->filteredSchools ?? []);
            @endphp

            <style>
                .rc-discover-v29 {
                    display: grid;
                    gap: .9rem;
                    color: var(--rc-text);
                }

                .rc-discover-top-v29 {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) auto;
                    gap: 1rem;
                    align-items: start;
                    margin: -.65rem 0 .45rem;
                }

                .rc-discover-title-v29 h1 {
                    margin: 0;
                    color: var(--rc-text);
                    font-size: clamp(1.65rem, 2.05vw, 2.05rem);
                    line-height: 1.02;
                    font-weight: 500;
                    letter-spacing: -.035em;
                }

                .rc-discover-title-v29 p {
                    margin: .45rem 0 0;
                    color: var(--rc-muted);
                    font-size: .82rem;
                }

                .rc-discover-actions-v29 {
                    display: grid;
                    grid-template-columns: minmax(28rem, 1fr) 3rem 3rem;
                    gap: .6rem;
                    align-items: start;
                }

                .rc-discover-actions-v29 .rc-home-search-v2 {
                    width: 100%;
                    max-width: none;
                    min-height: 2.65rem;
                    border-radius: .82rem;
                }

                .rc-discover-actions-v29 .rc-home-search-v2 input {
                    min-height: 2.65rem;
                    font-size: .86rem;
                }

                .rc-discover-actions-v29 .rc-home-refresh-v2,
                .rc-discover-actions-v29 .rc-home-dark-toggle-v2 {
                    width: 2.75rem;
                    height: 2.75rem;
                    min-height: 2.65rem;
                    border-radius: .82rem;
                }

                .rc-discover-program-search-v27 {
                    position: relative;
                    display: flex;
                    align-items: center;
                    min-height: 2.65rem;
                    border: 1px solid var(--rc-border);
                    background: var(--rc-surface);
                    border-radius: .82rem;
                    box-shadow: 0 8px 20px rgba(15,23,42,.045);
                    overflow: visible;
                }

                .rc-discover-program-search-v27 svg {
                    width: 1.08rem;
                    height: 1.08rem;
                    color: var(--rc-muted);
                    margin-left: 1rem;
                    flex: 0 0 auto;
                }

                .rc-discover-program-search-v27 input {
                    width: 100%;
                    border: 0 !important;
                    background: transparent !important;
                    box-shadow: none !important;
                    outline: none !important;
                    min-height: 2.65rem;
                    padding: 0 1rem;
                    color: var(--rc-text);
                    font-size: .95rem;
                }

                .rc-discover-filter-v27 {
                    display: grid;
                    grid-template-columns: minmax(0, 34rem) minmax(15rem, 20rem) 1fr;
                    gap: .6rem;
                    align-items: center;
                }

                .rc-discover-tabs-v27 {
                    display: flex;
                    align-items: center;
                    gap: .28rem;
                    padding: .25rem;
                    border-radius: .9rem;
                    background: var(--rc-soft);
                    min-width: 0;
                    overflow: auto;
                }

                .rc-discover-tab-v27 {
                    border: 0;
                    min-height: 2.15rem;
                    border-radius: .68rem;
                    background: transparent;
                    color: var(--rc-muted);
                    padding: 0 .82rem;
                    font-size: .82rem;
                    font-weight: 600;
                    white-space: nowrap;
                    transition: background .16s ease, color .16s ease;
                }

                .rc-discover-tab-v27.is-active {
                    color: #fff;
                    background: var(--rc-accent);
                    box-shadow: 0 10px 22px rgba(255,99,56,.2);
                }

                .rc-discover-select-v27 {
                    width: 100%;
                    min-height: 2.55rem;
                    border: 1px solid var(--rc-border);
                    border-radius: .88rem;
                    background: var(--rc-surface);
                    color: var(--rc-text);
                    padding: 0 .95rem;
                    font-size: .86rem;
                    font-weight: 500;
                    box-shadow: 0 8px 20px rgba(15,23,42,.035);
                }

                .rc-discover-meta-v27 {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1rem;
                    margin: .15rem 0 -.05rem;
                    flex-wrap: wrap;
                }

                .rc-discover-count-v27 {
                    display: inline-flex;
                    align-items: center;
                    gap: .6rem;
                    color: var(--rc-muted);
                    font-size: .9rem;
                    font-weight: 600;
                }

                .rc-discover-count-v27 strong { color: var(--rc-text); }

                .rc-discover-select-all-v27 {
                    display: inline-flex;
                    align-items: center;
                    gap: .42rem;
                    color: var(--rc-text);
                    font-size: .84rem;
                    font-weight: 650;
                }

                .rc-discover-select-all-v27 input {
                    width: 1.05rem;
                    height: 1.05rem;
                    border-radius: .35rem;
                    accent-color: var(--rc-accent);
                }

                .rc-discover-right-v27 {
                    display: inline-flex;
                    align-items: center;
                    gap: .65rem;
                }

                .rc-discover-toggle-v27 {
                    display: inline-flex;
                    align-items: center;
                    gap: .22rem;
                    padding: .24rem;
                    border: 1px solid var(--rc-border);
                    border-radius: .85rem;
                    background: var(--rc-surface);
                    box-shadow: 0 10px 24px rgba(15,23,42,.06);
                }

                .rc-discover-toggle-v27 button {
                    width: 2.35rem;
                    height: 2.35rem;
                    display: inline-grid;
                    place-items: center;
                    border: 0;
                    border-radius: .65rem;
                    color: var(--rc-muted);
                    background: transparent;
                }

                .rc-discover-toggle-v27 button.is-active {
                    color: var(--rc-accent);
                    background: var(--rc-accent-soft);
                }

                .rc-discover-loading-v27 {
                    position: relative;
                    min-height: 12rem;
                }

                .rc-discover-loading-overlay-v27 {
                    position: absolute;
                    inset: 0;
                    z-index: 30;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    border-radius: .85rem;
                    background: color-mix(in srgb, var(--rc-surface) 75%, transparent);
                    backdrop-filter: blur(3px);
                }

                .rc-discover-loading-v27.is-loading .rc-discover-loading-overlay-v27 { display: flex; }

                .rc-discover-v29 .rc-school-grid.rc-discover-school-grid {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 1rem;
                }

                .rc-discover-v29 .rc-school-card.rc-discover-school-card {
                    min-height: 8.25rem;
                    border: 1px solid var(--rc-border);
                    border-radius: .85rem;
                    background: var(--rc-surface);
                    padding: .82rem;
                    box-shadow: 0 8px 20px rgba(15,23,42,.045);
                    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
                }

                .rc-discover-v29 .rc-school-card.rc-discover-school-card:hover {
                    transform: translateY(-1px);
                    border-color: rgba(255,99,56,.3);
                    box-shadow: 0 16px 34px rgba(15,23,42,.08);
                }

                .rc-discover-v29 .rc-discover-card-main {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 1.7rem;
                    gap: .6rem;
                    align-items: start;
                }

                .rc-discover-v29 .rc-discover-card-title {
                    display: grid;
                    grid-template-columns: 3.15rem minmax(0, 1fr);
                    gap: .8rem;
                    align-items: center;
                    border: 0;
                    background: transparent;
                    color: var(--rc-text);
                    padding: 0;
                    text-align: left;
                    cursor: pointer;
                    min-width: 0;
                }

                .rc-discover-v29 .rc-school-card-logo-box,
                .rc-discover-v29 .rc-school-list-logo-box {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    background: #f3f4f6;
                    overflow: hidden;
                    position: relative;
                    flex: 0 0 auto;
                }

                .dark .rc-discover-v29 .rc-school-card-logo-box,
                .dark .rc-discover-v29 .rc-school-list-logo-box {
                    background: rgba(148,163,184,.12);
                }

                .rc-discover-v29 .rc-school-card-logo-box {
                    width: 2.7rem;
                    height: 2.7rem;
                    border-radius: .78rem;
                    padding: .4rem;
                }

                .rc-discover-v29 .rc-school-list-logo-box {
                    width: 2.15rem;
                    height: 2.15rem;
                    border-radius: .55rem;
                    padding: .25rem;
                }

                .rc-discover-v29 .rc-school-card-logo,
                .rc-discover-v29 .rc-school-list-logo {
                    width: auto;
                    height: auto;
                    max-width: 100%;
                    max-height: 100%;
                    object-fit: contain;
                    display: block;
                }

                .rc-discover-v29 .rc-logo-fallback-text {
                    position: absolute;
                    inset: 0;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    color: #0f172a;
                    font-size: .76rem;
                    font-weight: 500;
                    background: #f3f4f6;
                }

                .dark .rc-discover-v29 .rc-logo-fallback-text {
                    color: #e5e7eb;
                    background: rgba(148,163,184,.12);
                }

                .rc-discover-v29 .is-missing-logo .rc-logo-fallback-text { display: flex; }
                .rc-discover-v29 .is-missing-logo img { display: none; }

                .rc-discover-v29 .rc-discover-card-copy {
                    min-width: 0;
                    display: grid;
                    gap: .18rem;
                }

                .rc-discover-v29 .rc-discover-card-copy strong {
                    color: var(--rc-text);
                    font-size: .98rem;
                    line-height: 1.15;
                    font-weight: 920;
                    letter-spacing: -.025em;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .rc-discover-v29 .rc-discover-card-copy small {
                    color: var(--rc-muted);
                    font-size: .8rem;
                    line-height: 1.25;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }

                .rc-discover-v29 .rc-discover-card-check,
                .rc-discover-v29 .rc-discover-row-check {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 1.55rem;
                    height: 1.55rem;
                    border: 1px solid var(--rc-border);
                    border-radius: .45rem;
                    background: var(--rc-surface);
                    color: var(--rc-accent);
                    font-size: .82rem;
                    font-weight: 500;
                    box-shadow: 0 1px 3px rgba(15,23,42,.04);
                }

                .rc-discover-v29 .rc-discover-card-rule {
                    height: 1px;
                    background: var(--rc-border);
                    margin: .92rem 0 .82rem;
                    opacity: .75;
                }

                .rc-discover-v29 .rc-discover-card-footer {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: .6rem;
                }

                .rc-discover-v29 .rc-discover-division-pill {
                    display: inline-flex;
                    align-items: center;
                    border-radius: .48rem;
                    background: rgba(255,99,56,.13);
                    color: var(--rc-accent);
                    padding: .34rem .55rem;
                    font-size: .72rem;
                    line-height: 1;
                    font-weight: 650;
                    white-space: nowrap;
                }

                .rc-discover-v29 .rc-discover-coach-count {
                    color: var(--rc-muted);
                    font-size: .82rem;
                    white-space: nowrap;
                }

                .rc-discover-v29 .rc-school-list-table.rc-discover-school-list {
                    display: grid;
                    gap: 0;
                    border: 1px solid var(--rc-border);
                    border-radius: .85rem;
                    background: var(--rc-surface);
                    overflow: hidden;
                    box-shadow: 0 10px 26px rgba(15,23,42,.05);
                }

                .rc-discover-v29 .rc-discover-school-list-head,
                .rc-discover-v29 .rc-discover-school-list-row {
                    display: grid;
                    grid-template-columns: minmax(15rem, 1.35fr) minmax(10rem, 1fr) minmax(9rem, 1fr) minmax(13rem, 1.2fr) 4rem 2.6rem;
                    gap: 1rem;
                    align-items: center;
                }

                .rc-discover-v29 .rc-discover-school-list-head {
                    padding: .88rem 1.2rem;
                    background: var(--rc-soft);
                    color: var(--rc-muted);
                    font-size: .72rem;
                    font-weight: 650;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                }

                .rc-discover-v29 .rc-discover-school-list-row {
                    border-top: 1px solid var(--rc-border);
                    padding: .86rem 1.2rem;
                    background: transparent;
                    box-shadow: none;
                }

                .rc-discover-v29 .rc-discover-school-list-row:hover { background: var(--rc-soft); }

                .rc-discover-v29 .rc-discover-school-list-school {
                    display: grid;
                    grid-template-columns: 2.15rem minmax(0, 1fr);
                    gap: .6rem;
                    align-items: center;
                    border: 0;
                    background: transparent;
                    color: var(--rc-text);
                    text-align: left;
                    font-size: .88rem;
                    font-weight: 650;
                    cursor: pointer;
                }

                .rc-discover-v29 .rc-discover-school-list-name-copy,
                .rc-discover-v29 .rc-discover-list-coach,
                .rc-discover-v29 .rc-discover-list-muted,
                .rc-discover-v29 .rc-discover-list-email {
                    min-width: 0;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .rc-discover-v29 .rc-discover-list-coach { color: var(--rc-text); font-weight: 650; font-size: .82rem; }
                .rc-discover-v29 .rc-discover-list-muted { color: var(--rc-muted); font-size: .82rem; }
                .rc-discover-v29 .rc-discover-list-email a { color: #3b82f6; text-decoration: none; font-size: .82rem; }
                .rc-discover-v29 .rc-discover-list-division { color: var(--rc-accent); font-size: .76rem; font-weight: 650; }
                .rc-discover-v29 .rc-head-coach-chip { display:inline-flex; margin-left:.28rem; border-radius:.35rem; padding:.12rem .28rem; background:rgba(255,99,56,.13); color:var(--rc-accent); font-size:.62rem; font-weight:950; vertical-align:middle; }

                @media (max-width: 1320px) {
                    .rc-discover-v29 .rc-school-grid.rc-discover-school-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
                    .rc-discover-filter-v27 { grid-template-columns: minmax(0, 1fr) minmax(16rem, 21rem); }
                }



                .rc-discover-bulk-v36 {
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:.85rem;
                    width:100%;
                    min-height:3.6rem;
                    padding:.75rem 1rem;
                    margin:.45rem 0 .95rem;
                    border-radius:.72rem;
                    background:#18191f;
                    color:#fff;
                    box-shadow:0 14px 32px rgba(15,23,42,.13);
                }

                .dark .rc-discover-bulk-v36 {
                    background:#111217;
                    box-shadow:0 14px 32px rgba(0,0,0,.28);
                }

                .rc-discover-bulk-left-v36,
                .rc-discover-bulk-actions-v36 {
                    display:flex;
                    align-items:center;
                    gap:.7rem;
                    min-width:0;
                }

                .rc-discover-bulk-count-v36 {
                    font-size:.86rem;
                    font-weight:700;
                    white-space:nowrap;
                }

                .rc-discover-bulk-email-v36,
                .rc-discover-bulk-list-v36 > button {
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    gap:.42rem;
                    min-height:2.35rem;
                    padding:0 .9rem;
                    border:0;
                    border-radius:.62rem;
                    font-size:.84rem;
                    font-weight:700;
                    line-height:1;
                    cursor:pointer;
                    transition:transform .15s ease, box-shadow .15s ease, background .15s ease;
                }

                .rc-discover-bulk-email-v36 {
                    background:#ff6338;
                    color:#fff;
                    box-shadow:0 10px 24px rgba(255,99,56,.22);
                }

                .rc-discover-bulk-list-v36 {
                    position:relative;
                    display:inline-flex;
                }

                .rc-discover-bulk-list-v36 > button {
                    background:#fff;
                    color:#273044;
                    box-shadow:0 8px 18px rgba(0,0,0,.12);
                }

                .rc-discover-bulk-email-v36:hover,
                .rc-discover-bulk-list-v36 > button:hover {
                    transform:translateY(-1px);
                }

                .rc-discover-bulk-clear-v36 {
                    border:0;
                    background:transparent;
                    color:#f8fafc;
                    font-size:.78rem;
                    font-weight:700;
                    cursor:pointer;
                    padding:.4rem .2rem;
                    opacity:.92;
                }

                .rc-discover-bulk-clear-v36:hover {
                    opacity:1;
                    text-decoration:underline;
                }

                .rc-discover-bulk-menu-v36 {
                    position:absolute;
                    z-index:80;
                    top:calc(100% + .45rem);
                    left:0;
                    min-width:12.5rem;
                    max-height:15rem;
                    overflow:auto;
                    padding:.35rem;
                    border:1px solid rgba(226,232,240,.85);
                    border-radius:.75rem;
                    background:#fff;
                    color:#111827;
                    box-shadow:0 20px 45px rgba(15,23,42,.18);
                }

                .dark .rc-discover-bulk-menu-v36 {
                    border-color:rgba(63,63,70,.95);
                    background:#18181b;
                    color:#f4f4f5;
                }

                .rc-discover-bulk-option-v36 {
                    width:100%;
                    border:0;
                    background:transparent;
                    color:inherit;
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:.5rem;
                    padding:.55rem .65rem;
                    border-radius:.55rem;
                    text-align:left;
                    font-size:.8rem;
                    font-weight:650;
                    cursor:pointer;
                }

                .rc-discover-bulk-option-v36:hover {
                    background:rgba(255,99,56,.1);
                    color:#ff6338;
                }

                .rc-discover-school-card.is-selected {
                    border-color:#ff6338 !important;
                    box-shadow:0 12px 30px rgba(255,99,56,.12), 0 1px 2px rgba(15,23,42,.05) !important;
                }

                .rc-discover-card-check.is-selected,
                .rc-discover-row-check.is-selected {
                    background:#ff6338 !important;
                    border-color:#ff6338 !important;
                    color:#fff !important;
                    box-shadow:0 8px 18px rgba(255,99,56,.18);
                }

                @media (max-width: 1100px) {
                    .rc-discover-top-v29 { grid-template-columns: 1fr; }
                    .rc-discover-actions-v29 { justify-self: stretch; grid-template-columns: minmax(0, 1fr) 3rem 3rem; }
                    .rc-discover-filter-v27 { grid-template-columns: 1fr; }
                    .rc-discover-v29 .rc-school-grid.rc-discover-school-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                    .rc-discover-v29 .rc-discover-school-list-head { display: none; }
                    .rc-discover-v29 .rc-discover-school-list-row { grid-template-columns: 1fr auto; gap: .5rem; }
                    .rc-discover-v29 .rc-discover-school-list-row > :nth-child(n+2):nth-child(-n+5) { display: none; }
                }

                @media (max-width: 640px) {
                    .rc-discover-v29 .rc-school-grid.rc-discover-school-grid { grid-template-columns: 1fr; }
                    .rc-discover-actions-v29 { grid-template-columns: 1fr 3rem 3rem; }
                    .rc-discover-title-v29 h1 { font-size: 1.75rem; }
                }
            
                .rc-discover-top-v29 {
                    display: grid !important;
                    grid-template-columns: minmax(0, 1fr) minmax(34rem, 45rem) !important;
                    column-gap: 1.25rem !important;
                    align-items: start !important;
                    margin: -.7rem 0 .55rem !important;
                }

                .rc-discover-title-v29 h1 {
                    white-space: nowrap;
                    font-weight: 760 !important;
                    font-size: clamp(1.6rem, 2.05vw, 2.05rem) !important;
                    line-height: 1.05 !important;
                }

                .rc-discover-title-v29 p {
                    font-size: .88rem !important;
                    margin-top: .38rem !important;
                }

                .rc-discover-actions-v29 {
                    justify-self: end !important;
                    width: 100% !important;
                    max-width: 45rem !important;
                    display: grid !important;
                    grid-template-columns: minmax(28rem, 1fr) 2.75rem 2.75rem !important;
                    grid-template-areas: "search refresh dark" !important;
                    justify-content: end !important;
                    align-items: center !important;
                    gap: .65rem !important;
                }

                .rc-discover-actions-v29 .rc-home-search-v2,
                .rc-discover-program-search-v27 {
                    min-height: 2.62rem !important;
                    border-radius: .8rem !important;
                }

                .rc-discover-actions-v29 .rc-home-search-v2 input,
                .rc-discover-program-search-v27 input {
                    min-height: 2.62rem !important;
                    font-size: .86rem !important;
                    font-weight: 400 !important;
                }

                .rc-discover-actions-v29 .rc-home-refresh-v2,
                .rc-discover-actions-v29 .rc-home-dark-toggle-v2 {
                    width: 2.75rem !important;
                    min-width: 2.75rem !important;
                    max-width: 2.75rem !important;
                    height: 2.75rem !important;
                    min-height: 2.75rem !important;
                    max-height: 2.75rem !important;
                    border-radius: .8rem !important;
                }

                .rc-discover-tabs-v27 {
                    min-height: 2.55rem !important;
                    padding: .2rem !important;
                    border-radius: .78rem !important;
                }

                .rc-discover-tab-v27 {
                    min-height: 2.14rem !important;
                    border-radius: .62rem !important;
                    font-size: .8rem !important;
                    font-weight: 600 !important;
                }

                .rc-discover-select-v27 {
                    min-height: 2.55rem !important;
                    border-radius: .78rem !important;
                    font-size: .86rem !important;
                    font-weight: 500 !important;
                }

                .rc-discover-count-v27,
                .rc-discover-select-all-v27,
                .rc-discover-card-copy strong,
                .rc-discover-list-coach {
                    font-weight: 600 !important;
                }



                .rc-discover-bulk-v36 {
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:.85rem;
                    width:100%;
                    min-height:3.6rem;
                    padding:.75rem 1rem;
                    margin:.45rem 0 .95rem;
                    border-radius:.72rem;
                    background:#18191f;
                    color:#fff;
                    box-shadow:0 14px 32px rgba(15,23,42,.13);
                }

                .dark .rc-discover-bulk-v36 {
                    background:#111217;
                    box-shadow:0 14px 32px rgba(0,0,0,.28);
                }

                .rc-discover-bulk-left-v36,
                .rc-discover-bulk-actions-v36 {
                    display:flex;
                    align-items:center;
                    gap:.7rem;
                    min-width:0;
                }

                .rc-discover-bulk-count-v36 {
                    font-size:.86rem;
                    font-weight:700;
                    white-space:nowrap;
                }

                .rc-discover-bulk-email-v36,
                .rc-discover-bulk-list-v36 > button {
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    gap:.42rem;
                    min-height:2.35rem;
                    padding:0 .9rem;
                    border:0;
                    border-radius:.62rem;
                    font-size:.84rem;
                    font-weight:700;
                    line-height:1;
                    cursor:pointer;
                    transition:transform .15s ease, box-shadow .15s ease, background .15s ease;
                }

                .rc-discover-bulk-email-v36 {
                    background:#ff6338;
                    color:#fff;
                    box-shadow:0 10px 24px rgba(255,99,56,.22);
                }

                .rc-discover-bulk-list-v36 {
                    position:relative;
                    display:inline-flex;
                }

                .rc-discover-bulk-list-v36 > button {
                    background:#fff;
                    color:#273044;
                    box-shadow:0 8px 18px rgba(0,0,0,.12);
                }

                .rc-discover-bulk-email-v36:hover,
                .rc-discover-bulk-list-v36 > button:hover {
                    transform:translateY(-1px);
                }

                .rc-discover-bulk-clear-v36 {
                    border:0;
                    background:transparent;
                    color:#f8fafc;
                    font-size:.78rem;
                    font-weight:700;
                    cursor:pointer;
                    padding:.4rem .2rem;
                    opacity:.92;
                }

                .rc-discover-bulk-clear-v36:hover {
                    opacity:1;
                    text-decoration:underline;
                }

                .rc-discover-bulk-menu-v36 {
                    position:absolute;
                    z-index:80;
                    top:calc(100% + .45rem);
                    left:0;
                    min-width:12.5rem;
                    max-height:15rem;
                    overflow:auto;
                    padding:.35rem;
                    border:1px solid rgba(226,232,240,.85);
                    border-radius:.75rem;
                    background:#fff;
                    color:#111827;
                    box-shadow:0 20px 45px rgba(15,23,42,.18);
                }

                .dark .rc-discover-bulk-menu-v36 {
                    border-color:rgba(63,63,70,.95);
                    background:#18181b;
                    color:#f4f4f5;
                }

                .rc-discover-bulk-option-v36 {
                    width:100%;
                    border:0;
                    background:transparent;
                    color:inherit;
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:.5rem;
                    padding:.55rem .65rem;
                    border-radius:.55rem;
                    text-align:left;
                    font-size:.8rem;
                    font-weight:650;
                    cursor:pointer;
                }

                .rc-discover-bulk-option-v36:hover {
                    background:rgba(255,99,56,.1);
                    color:#ff6338;
                }

                .rc-discover-school-card.is-selected {
                    border-color:#ff6338 !important;
                    box-shadow:0 12px 30px rgba(255,99,56,.12), 0 1px 2px rgba(15,23,42,.05) !important;
                }

                .rc-discover-card-check.is-selected,
                .rc-discover-row-check.is-selected {
                    background:#ff6338 !important;
                    border-color:#ff6338 !important;
                    color:#fff !important;
                    box-shadow:0 8px 18px rgba(255,99,56,.18);
                }

                @media (max-width: 1100px) {
                    .rc-discover-top-v29 { grid-template-columns: 1fr !important; }
                    .rc-discover-actions-v29 { max-width:none !important; grid-template-columns:minmax(0,1fr) 2.75rem 2.75rem !important; }
                    .rc-discover-title-v29 h1 { white-space: normal; }
                }

                /* v73: Discover Schools uses its own program search. Hide the shared
                   header search here so two inputs never mirror the same Livewire state. */
                .rc-discover-v29 .rc-home-header-v2 .rc-home-search-v2 {
                    display: none !important;
                }
                .rc-discover-v29 .rc-home-header-v2 .rc-home-actions-v2 {
                    grid-template-columns: 2.75rem 2.75rem !important;
                    grid-template-areas: "refresh dark" !important;
                    width: auto !important;
                    max-width: none !important;
                    justify-self: end !important;
                }
                .rc-discover-v29 button {
                    transition: transform .1s ease, opacity .12s ease, border-color .14s ease, background-color .14s ease, box-shadow .14s ease;
                    touch-action: manipulation;
                }
                .rc-discover-v29 button:active,
                .rc-discover-v29 button.rc-click-feedback-v73 {
                    transform: scale(.975);
                    opacity: .82;
                }
                .rc-discover-v29 button[disabled] {
                    cursor: wait !important;
                    opacity: .62;
                }
                .rc-discover-program-search-v27 input { padding-right: 3rem !important; }
                .rc-discover-search-busy-v73 {
                    position: absolute;
                    right: .9rem;
                    top: 50%;
                    transform: translateY(-50%);
                    align-items: center;
                    justify-content: center;
                    color: var(--rc-accent);
                    pointer-events: none;
                }
                @media (max-width: 1100px) {
                    .rc-discover-v29 .rc-home-header-v2 .rc-home-actions-v2 {
                        justify-self: end !important;
                        width: auto !important;
                    }
                }
</style>

            <div class="rc-discover-v29"
                 x-on:click.capture="const b = $event.target.closest('button'); if (b && !b.disabled) { b.classList.add('rc-click-feedback-v73'); setTimeout(() => b.classList.remove('rc-click-feedback-v73'), 240); }">
                @include('filament.partials.coach-database-header', [
                    'firstName' => $firstName,
                    'placeholder' => 'Search schools, coaches, conferences, divisions, lists...',
                    'showNewEmail' => false,
                ])

                <div class="rc-discover-program-search-v27" role="search" aria-label="Search schools and coaches">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                    <input placeholder="Search {{ number_format($discoverSearchTotal) }} women's soccer programs & coaches..." x-model="discoverSearch" x-on:input.debounce.40ms="$dispatch('rc-discover-filter', { search: discoverSearch, division: discoverDivision, conference: discoverConference })" autocomplete="off" />
                </div>

                <div class="rc-discover-filter-v27">
                    <div class="rc-discover-tabs-v27" aria-label="Division filter">
                        @foreach($discoverDivisionTabs as $divisionValue => $divisionLabel)
                            <button type="button" class="rc-discover-tab-v27" x-bind:class="discoverDivision === @js($divisionValue) ? 'is-active' : ''" x-on:click="discoverDivision = (discoverDivision === @js($divisionValue) ? '' : @js($divisionValue)); discoverConference=''; $dispatch('rc-discover-filter', { search: discoverSearch, division: discoverDivision, conference: discoverConference })">{{ $divisionLabel }}</button>
                        @endforeach
                    </div>

                    <select class="rc-discover-select-v27" x-model="discoverConference" x-on:change="$dispatch('rc-discover-filter', { search: discoverSearch, division: discoverDivision, conference: discoverConference })" aria-label="Conference filter">
                        <option value="" x-text="`All Conferences (${Number(discoverAvailableConferences.length || 0).toLocaleString()})`"></option>
                        <template x-for="conferenceOption in discoverAvailableConferences" :key="`discover-conf-${conferenceOption}`">
                            <option x-bind:value="conferenceOption" x-text="conferenceOption"></option>
                        </template>
                    </select>
                </div>

                <div class="rc-discover-meta-v27">
                    <div class="rc-discover-count-v27">
                        <span><strong x-text="Number(discoverClientCount || {{ (int) $discoverSchoolCount }}).toLocaleString()"></strong> schools</span>
                        <button type="button" class="rc-discover-select-all-v27 rc-discover-select-all-button-v36" x-on:click="$dispatch('rc-discover-toggle-visible')"><input type="checkbox" x-bind:checked="discoverSelectedIds.length > 0 && discoverSelectedIds.length >= discoverClientShown" readonly tabindex="-1"><span>Select All (<span x-text="Number(discoverClientShown || 0).toLocaleString()"></span>)</span></button>
                    </div>

                    <div class="rc-discover-right-v27">
                        <div class="rc-discover-toggle-v27" aria-label="School view">
                            <button type="button" x-bind:class="discoverViewMode === 'grid' ? 'is-active' : ''" x-on:click="discoverViewMode='grid'; $dispatch('rc-discover-view',{mode:'grid'})" aria-label="Grid view"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></button>
                            <button type="button" x-bind:class="discoverViewMode === 'list' ? 'is-active' : ''" x-on:click="discoverViewMode='list'; $dispatch('rc-discover-view',{mode:'list'})" aria-label="List view"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg></button>
                        </div>
                    </div>
                </div>

                <div class="rc-discover-bulk-v36" x-cloak x-show="discoverSelectedIds.length > 0" x-transition.opacity wire:key="discover-bulk-selection-bar">
                        <div class="rc-discover-bulk-left-v36">
                            <span class="rc-discover-bulk-count-v36"><span x-text="Number(discoverSelectedIds.length).toLocaleString()"></span> selected</span>
                            <div class="rc-discover-bulk-list-v36" x-data="{ open: false }" x-on:click.outside="open = false">
                                <button type="button" x-on:click="open = ! open">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                    <span>Add to List</span>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="rc-discover-bulk-menu-v36 rc-discover-list-menu-v112" x-cloak x-show="open" x-transition.origin.top.left>
                                    <h4>Add to a list</h4>
                                    <template x-for="list in discoverLists" :key="`bulk-list-${discoverListKey(list)}`">
                                        <button type="button" class="rc-discover-bulk-option-v36 rc-discover-list-option-v112" x-on:click="open=false; addSelectedSchoolsToDiscoverList(list)">
                                            <span class="rc-list-check-v81 rc-list-check-plus-v112">+</span>
                                            <span class="rc-school-list-label-v87">
                                                <span class="rc-school-list-dot-v72" x-bind:style="`--dot:${discoverListColor(list)}`"></span>
                                                <span x-text="discoverListLabel(list)"></span>
                                            </span>
                                            <small class="rc-list-count-v81" x-text="discoverListCount(list)"></small>
                                        </button>
                                    </template>
                                    <div class="rc-school-list-empty" x-show="discoverLists.length === 0">No lists yet.</div>

                                    <div class="rc-list-quick-create-v112">
                                        <div class="rc-list-quick-create-title-v112">Create new list</div>
                                        <div class="rc-list-quick-create-row-v112">
                                            <input type="text" x-model="discoverNewBulkListName" x-on:keydown.enter.prevent="createDiscoverBulkList()" placeholder="New list name" maxlength="80">
                                            <button type="button" class="rc-list-quick-create-btn-v112" x-on:click="createDiscoverBulkList()" x-bind:disabled="discoverCreatingList || !String(discoverNewBulkListName || '').trim()">
                                                <span x-show="!discoverCreatingList">Create &amp; Add</span>
                                                <span x-show="discoverCreatingList" x-cloak>Creating…</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="rc-discover-bulk-clear-v36" x-on:click="discoverSelectedIds=[]; window.dispatchEvent(new CustomEvent('rc-discover-clear-selection'))">Clear</button>
                </div>

                <div class="rc-discover-bulk-notice-v112" x-cloak x-show="discoverBulkNotice" x-transition.opacity x-text="discoverBulkNotice"></div>

                {{-- v103: Discover is local DB-backed. Never blur/block the entire grid for local filters or selection. --}}
                <div class="rc-discover-loading-v27">
                    @include('filament.partials.coach-database-school-grid', ['schools' => $this->discoverClientSchools, 'viewMode' => 'grid', 'selectedSchoolIds' => $selectedSchoolIds])
                </div>
                <div style="margin-top:.35rem;text-align:center" x-show="discoverClientShown < discoverClientCount" x-cloak>
                    <button class="rc-btn" type="button" x-on:click="$dispatch('rc-discover-load-more')">Load more</button>
                </div>
            </div>
        @endif


        @if($section === 'favorites')
            <style>
                .rc-favorites-v37 { display:grid; gap:1.05rem; margin-top:1.15rem; }
                .rc-favorites-head-v37 { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; }
                .rc-favorites-title-v37 { display:grid; gap:.28rem; min-width:0; }
                .rc-favorites-title-v37 h2 { margin:0; color:var(--rc-text); font-size:1.35rem; line-height:1.15; font-weight:750; letter-spacing:-.025em; }
                .rc-favorites-title-v37 p { margin:0; color:var(--rc-muted); font-size:.9rem; line-height:1.35; }
                .rc-favorites-actions-v37 { display:flex; align-items:center; justify-content:flex-end; gap:.6rem; flex-wrap:wrap; }
                .rc-fav-view-toggle-v37 { display:inline-flex; align-items:center; gap:.28rem; padding:.22rem; border-radius:.86rem; background:var(--rc-surface); border:1px solid var(--rc-border); box-shadow:0 10px 24px rgba(15,23,42,.05); }
                .rc-fav-view-toggle-v37 button { width:2.25rem; height:2.25rem; border:0; border-radius:.7rem; background:transparent; color:var(--rc-muted); display:grid; place-items:center; cursor:pointer; transition:.16s ease; }
                .rc-fav-view-toggle-v37 button.is-active { color:#ff6338; background:#fff2ed; }
                .dark .rc-fav-view-toggle-v37 button.is-active { background:rgba(255,99,56,.15); }
                .rc-fav-discover-btn-v37 { min-height:2.55rem; padding:0 1rem; border-radius:.82rem; border:1px solid var(--rc-border); background:var(--rc-surface); color:var(--rc-text); display:inline-flex; align-items:center; gap:.45rem; font-size:.84rem; font-weight:650; text-decoration:none; box-shadow:0 10px 24px rgba(15,23,42,.05); }
                .rc-favorites-grid-v37 { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
                .rc-favorite-card-v37 { border:1px solid var(--rc-border); border-radius:1.05rem; background:var(--rc-surface); box-shadow:0 14px 35px rgba(15,23,42,.07); padding:1.18rem; display:grid; gap:.85rem; min-height:13.6rem; }
                .rc-favorite-top-v37 { display:flex; align-items:flex-start; justify-content:space-between; gap:.85rem; }
                .rc-favorite-logo-v37 { width:3.3rem; height:3.3rem; border-radius:.75rem; border:1px solid var(--rc-border); background:#fff; display:inline-flex; align-items:center; justify-content:center; overflow:hidden; flex:0 0 auto; color:#111827; font-size:1.05rem; font-weight:700; }
                .rc-favorite-logo-v37 img { display:block; width:100%; height:100%; object-fit:contain; object-position:center; background:#fff; }
                .rc-favorite-logo-v37 .rc-logo-fallback-text { display:none; }
                .rc-favorite-logo-v37.is-missing-logo .rc-logo-fallback-text { display:inline-flex; }
                .rc-favorite-logo-v37.is-missing-logo img { display:none !important; }
                .rc-favorite-star-v37 { width:2.2rem; height:2.2rem; border:0; border-radius:.65rem; display:grid; place-items:center; color:#f59e0b; background:#fff0cc; cursor:pointer; }
                .rc-favorite-copy-v37 { display:grid; gap:.35rem; min-width:0; }
                .rc-favorite-copy-v37 h3 { margin:0; color:var(--rc-text); font-size:.95rem; line-height:1.25; font-weight:700; letter-spacing:-.01em; }
                .rc-favorite-copy-v37 p { margin:0; color:var(--rc-muted); font-size:.8rem; line-height:1.35; }
                .rc-favorite-actions-v37 { display:flex; align-items:center; gap:.5rem; margin-top:auto; }
                .rc-favorite-view-v37, .rc-favorite-remove-v37 { min-height:2.55rem; border-radius:.72rem; display:inline-flex; align-items:center; justify-content:center; gap:.45rem; padding:0 .9rem; font-size:.84rem; font-weight:700; cursor:pointer; transition:.16s ease; }
                .rc-favorite-view-v37 { border:1px solid #ff6338; color:#fff; background:#ff6338; box-shadow:0 10px 24px rgba(255,99,56,.22); min-width:7.25rem; }
                .rc-favorite-remove-v37 { border:1px solid var(--rc-border); color:var(--rc-text); background:var(--rc-surface); min-width:6.5rem; }
                .rc-favorites-list-v40 { border:1px solid var(--rc-border); border-radius:1rem; background:var(--rc-surface); box-shadow:0 14px 35px rgba(15,23,42,.055); overflow:hidden; max-width:78rem; }
                .rc-fav-list-row-v40 { display:grid; grid-template-columns:minmax(0,1fr) auto auto auto; gap:.8rem; align-items:center; padding:.8rem .95rem; border-top:1px solid var(--rc-border); }
                .rc-fav-list-row-v40:first-child { border-top:0; }
                .rc-fav-list-main-v40 { display:grid; grid-template-columns:2.25rem minmax(0,1fr); gap:.75rem; align-items:center; min-width:0; }
                .rc-fav-list-logo-v40 { width:2.25rem; height:2.25rem; border-radius:.55rem; border:1px solid var(--rc-border); background:#fff; display:inline-flex; align-items:center; justify-content:center; overflow:hidden; color:#111827; font-size:.78rem; font-weight:750; flex:0 0 auto; }
                .rc-fav-list-logo-v40 img { width:100%; height:100%; object-fit:contain; object-position:center; display:block; background:#fff; }
                .rc-fav-list-logo-v40 .rc-logo-fallback-text { display:none; }
                .rc-fav-list-logo-v40.is-missing-logo .rc-logo-fallback-text { display:inline-flex; }
                .rc-fav-list-logo-v40.is-missing-logo img { display:none !important; }
                .rc-fav-list-copy-v40 { display:grid; gap:.1rem; min-width:0; }
                .rc-fav-list-copy-v40 strong { color:var(--rc-text); font-size:.88rem; line-height:1.2; font-weight:650; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
                .rc-fav-list-copy-v40 span { color:var(--rc-muted); font-size:.76rem; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
                .rc-fav-list-count-v40 { color:var(--rc-muted); font-size:.78rem; white-space:nowrap; }
                .rc-fav-list-view-v40 { min-height:2.05rem; padding:0 .85rem; border-radius:.62rem; border:1px solid var(--rc-border); color:var(--rc-text); background:var(--rc-surface); display:inline-flex; align-items:center; justify-content:center; gap:.4rem; font-size:.78rem; font-weight:650; cursor:pointer; }
                .rc-fav-list-remove-v40 { width:2rem; height:2rem; border:0; background:transparent; color:var(--rc-muted); display:grid; place-items:center; border-radius:.5rem; cursor:pointer; }
                .rc-fav-list-remove-v40:hover { background:var(--rc-soft); color:#ff6338; }
                .rc-favorites-empty-v37 { border:1px dashed var(--rc-border); border-radius:1rem; padding:1rem; color:var(--rc-muted); background:var(--rc-surface); }
                .rc-favorites-loading-v37 { display:inline-flex; align-items:center; gap:.45rem; color:var(--rc-muted); font-size:.82rem; }
                @media (max-width: 1280px) { .rc-favorites-grid-v37 { grid-template-columns:repeat(3,minmax(0,1fr)); } }
                @media (max-width: 900px) { .rc-favorites-head-v37 { align-items:flex-start; flex-direction:column; } .rc-favorites-grid-v37 { grid-template-columns:repeat(2,minmax(0,1fr)); } .rc-fav-list-row-v40 { grid-template-columns:minmax(0,1fr) auto; } .rc-fav-list-count-v40 { display:none; } }
                @media (max-width: 640px) { .rc-favorites-grid-v37 { grid-template-columns:1fr; } }
            </style>

            @php
                $favoriteSchoolRows = collect($this->favoriteSchools ?? [])->filter(fn ($school) => is_array($school))->values();
                $favoriteInitialsFor = function (string $name): string {
                    return strtoupper(collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->map(fn ($part) => mb_substr((string) $part, 0, 1))->take(2)->implode('') ?: 'S');
                };
                $favoriteLogoUrlFor = function (array $school): string {
                    foreach (['logo_url', 'school_logo_url', 'business_logo_url', 'logo', 'school_logo', 'business_logo'] as $key) {
                        $value = $school[$key] ?? null;
                        if (is_scalar($value)) {
                            $url = trim((string) $value);
                            if (str_starts_with(strtolower($url), 'http://') || str_starts_with(strtolower($url), 'https://') || str_starts_with($url, '//')) {
                                return str_starts_with($url, '//') ? 'https:' . $url : $url;
                            }
                        }
                    }
                    foreach (['head_coach.logo_url', 'head_coach.school_logo_url', 'head_coach.business_logo_url'] as $key) {
                        $url = trim((string) data_get($school, $key, ''));
                        if (str_starts_with(strtolower($url), 'http://') || str_starts_with(strtolower($url), 'https://') || str_starts_with($url, '//')) {
                            return str_starts_with($url, '//') ? 'https:' . $url : $url;
                        }
                    }
                    return '';
                };
            @endphp

            @include('filament.partials.coach-database-header', [
                'firstName' => $firstName,
                'placeholder' => 'Search schools, coaches, conferences, divisions, lists...',
                'showNewEmail' => false,
            ])

            <div class="rc-favorites-v37">
                <div class="rc-favorites-head-v37">
                    <div class="rc-favorites-title-v37">
                        <h2>Favorites</h2>
                        <p>Schools you’ve starred, saved for quick access.</p>
                    </div>
                    <div class="rc-favorites-actions-v37">
                        <div class="rc-fav-view-toggle-v37" aria-label="Favorite school view options">
                            <button type="button" class="{{ $schoolViewMode !== 'list' ? 'is-active' : '' }}" wire:click="setSchoolViewMode('grid')" aria-label="Grid view">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                            </button>
                            <button type="button" class="{{ $schoolViewMode === 'list' ? 'is-active' : '' }}" wire:click="setSchoolViewMode('list')" aria-label="List view">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>
                            </button>
                        </div>
                        <a class="rc-fav-discover-btn-v37" href="{{ \App\Filament\Pages\CoachDatabaseSchools::getUrl() }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m16 8-2.2 6.3L8 16l2.2-6.3L16 8Z"/></svg>
                            Discover Schools
                        </a>
                    </div>
                </div>

                @if($isSyncingTags)
                    <div class="rc-favorites-loading-v37"><span class="rc-spinner-mini"></span> Syncing saved and favorite tags…</div>
                @endif

                @if($favoriteSchoolRows->isEmpty())
                    <div class="rc-favorites-empty-v37">No favorite schools yet. Star a school from Discover Schools to keep it here.</div>
                @elseif($schoolViewMode === 'list')
                    <div class="rc-favorites-list-v40">
                        @foreach($favoriteSchoolRows as $school)
                            @php
                                $schoolId = (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim((string) ($school['name'] ?? '')))));
                                $schoolName = trim((string) ($school['name'] ?? 'Unnamed School'));
                                $conference = trim((string) ($school['conference'] ?? ''));
                                $coachCount = (int) ($school['coach_count'] ?? 0);
                                $logoUrl = $favoriteLogoUrlFor($school);
                                $initials = $favoriteInitialsFor($schoolName);
                            @endphp
                            <div class="rc-fav-list-row-v40">
                                <div class="rc-fav-list-main-v40">
                                    <span class="rc-fav-list-logo-v40 {{ $logoUrl === '' ? 'is-missing-logo' : '' }}">
                                        @if($logoUrl !== '')
                                            <img src="{{ $logoUrl }}" alt="{{ $schoolName }} logo" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none';this.closest('.rc-fav-list-logo-v40').classList.add('is-missing-logo')">
                                        @endif
                                        <span class="rc-logo-fallback-text">{{ $initials }}</span>
                                    </span>
                                    <span class="rc-fav-list-copy-v40">
                                        <strong>{{ $schoolName }}</strong>
                                        <span>{{ $conference !== '' ? $conference : 'Conference unavailable' }}</span>
                                    </span>
                                </div>
                                <span class="rc-fav-list-count-v40">{{ number_format($coachCount) }} {{ \Illuminate\Support\Str::plural('coach', $coachCount) }}</span>
                                <button type="button" class="rc-fav-list-view-v40" x-on:click.stop="openGlobalSchool(@js($school))">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                    View
                                </button>
                                <button type="button" class="rc-fav-list-remove-v40" wire:click="unfavoriteSchoolById({{ \Illuminate\Support\Js::from($schoolId) }})" aria-label="Remove {{ $schoolName }} from favorites">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rc-favorites-grid-v37">
                        @foreach($favoriteSchoolRows as $school)
                            @php
                                $schoolId = (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim((string) ($school['name'] ?? '')))));
                                $schoolName = trim((string) ($school['name'] ?? 'Unnamed School'));
                                $conference = trim((string) ($school['conference'] ?? ''));
                                $coachCount = (int) ($school['coach_count'] ?? 0);
                                $logoUrl = $favoriteLogoUrlFor($school);
                                $initials = $favoriteInitialsFor($schoolName);
                            @endphp
                            <article class="rc-favorite-card-v37">
                                <div class="rc-favorite-top-v37">
                                    <span class="rc-favorite-logo-v37 {{ $logoUrl === '' ? 'is-missing-logo' : '' }}">
                                        @if($logoUrl !== '')
                                            <img src="{{ $logoUrl }}" alt="{{ $schoolName }} logo" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none';this.closest('.rc-favorite-logo-v37').classList.add('is-missing-logo')">
                                        @endif
                                        <span class="rc-logo-fallback-text">{{ $initials }}</span>
                                    </span>
                                    <button type="button" class="rc-favorite-star-v37" wire:click="unfavoriteSchoolById({{ \Illuminate\Support\Js::from($schoolId) }})" aria-label="Remove {{ $schoolName }} from favorites">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2.75 2.83 5.73 6.32.92-4.57 4.46 1.08 6.3L12 17.18l-5.66 2.98 1.08-6.3L2.85 9.4l6.32-.92L12 2.75Z"/></svg>
                                    </button>
                                </div>
                                <div class="rc-favorite-copy-v37">
                                    <h3>{{ $schoolName }}</h3>
                                    <p>{{ $conference !== '' ? $conference : 'Conference unavailable' }} · {{ number_format($coachCount) }} {{ \Illuminate\Support\Str::plural('coach', $coachCount) }}</p>
                                </div>
                                <div class="rc-favorite-actions-v37">
                                    <button type="button" class="rc-favorite-view-v37" x-on:click.stop="openGlobalSchool(@js($school))">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                        View
                                    </button>
                                    <button type="button" class="rc-favorite-remove-v37" wire:click="unfavoriteSchoolById({{ \Illuminate\Support\Js::from($schoolId) }})">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                        Remove
                                    </button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if($section === 'lists')
            <style>
                .rc-my-lists-v115{display:grid;gap:1.15rem}
                .rc-my-lists-head-v115{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-top:.25rem}
                .rc-my-lists-title-v115 h2{margin:0;color:var(--rc-text);font-size:1.42rem;line-height:1.1;letter-spacing:-.03em;font-weight:800}
                .rc-my-lists-title-v115 p{margin:.45rem 0 0;color:var(--rc-muted);font-size:.9rem;line-height:1.45}
                .rc-new-list-btn-v115{min-height:2.75rem;padding:0 1.05rem;border:0;border-radius:.85rem;display:inline-flex;align-items:center;justify-content:center;gap:.5rem;background:#ff6338;color:#fff;font-size:.86rem;font-weight:800;box-shadow:0 13px 26px rgba(255,99,56,.2);cursor:pointer}
                .rc-new-list-panel-v115{border:1px solid var(--rc-border);background:var(--rc-surface);border-radius:1rem;padding:.9rem;box-shadow:0 14px 35px rgba(15,23,42,.055);display:grid;grid-template-columns:minmax(0,1fr) auto auto auto;align-items:center;gap:.6rem}
                .rc-new-list-panel-v115 .rc-input{min-height:2.55rem;width:100%;border-color:#ff6338}
                .rc-list-color-picker-v115{display:inline-flex;align-items:center;gap:.35rem}
                .rc-list-color-option-v115{width:1.45rem;height:1.45rem;border-radius:.4rem;border:2px solid transparent;cursor:pointer;padding:0;box-shadow:inset 0 0 0 1px rgba(15,23,42,.08)}
                .rc-list-color-option-v115.is-selected{border-color:var(--rc-text)}
                .rc-list-stack-v115{display:grid;gap:1rem}
                .rc-list-card-v115{position:relative;border:1px solid var(--rc-border);background:var(--rc-surface);border-radius:1.2rem;padding:1rem 1.15rem 1.1rem;box-shadow:0 14px 36px rgba(15,23,42,.055);overflow:hidden}
                .rc-list-card-head-v115{display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:2.3rem}
                .rc-list-card-title-v115{display:flex;align-items:center;gap:.65rem;min-width:0}
                .rc-list-dot-v115{width:.72rem;height:.72rem;border-radius:999px;flex:0 0 auto;background:var(--list-color,#ff6338)}
                .rc-list-card-title-v115 strong{color:var(--rc-text);font-size:1rem;font-weight:800;line-height:1.2}
                .rc-list-count-pill-v115{border-radius:999px;padding:.24rem .62rem;background:var(--rc-soft);color:var(--rc-muted);font-size:.77rem;white-space:nowrap}
                .rc-list-card-actions-v115{display:inline-flex;align-items:center;gap:.35rem;flex:0 0 auto}
                .rc-list-icon-btn-v115{width:2.05rem;height:2.05rem;border:0;border-radius:.62rem;background:transparent;color:#8a94a6;display:grid;place-items:center;cursor:pointer;transition:.15s ease}
                .rc-list-icon-btn-v115:hover,.rc-list-icon-btn-v115.is-active{background:var(--rc-soft);color:#ff6338}
                .rc-list-tools-v115{margin:.75rem 0 .65rem}
                .rc-list-search-v115{position:relative;max-width:31rem}
                .rc-list-search-v115 input{width:100%;height:2.65rem;padding:0 .85rem 0 2.35rem;border:1px solid var(--rc-border);border-radius:.72rem;background:var(--rc-surface);color:var(--rc-text);outline:none}
                .rc-list-search-v115 input:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.1)}
                .rc-list-search-v115 svg{position:absolute;left:.78rem;top:50%;transform:translateY(-50%);color:#8a94a6}
                .rc-list-add-results-v115{position:absolute;left:0;right:0;top:calc(100% + .35rem);z-index:40;max-height:15rem;overflow:auto;border:1px solid var(--rc-border);border-radius:.72rem;background:var(--rc-surface);box-shadow:0 18px 40px rgba(15,23,42,.16);padding:.35rem}
                .rc-list-add-result-v115{width:100%;min-height:2.55rem;border:0;border-radius:.58rem;background:transparent;color:var(--rc-text);display:flex;align-items:center;gap:.55rem;padding:.4rem .55rem;text-align:left;cursor:pointer}
                .rc-list-add-result-v115:hover{background:var(--rc-soft)}
                .rc-list-add-logo-v115,.rc-list-chip-logo-v115{width:1.75rem;height:1.75rem;border-radius:.45rem;border:1px solid var(--rc-border);background:#fff;color:#111827;display:inline-flex;align-items:center;justify-content:center;overflow:hidden;flex:0 0 auto;font-size:.66rem;font-weight:800}
                .rc-list-add-logo-v115 img,.rc-list-chip-logo-v115 img{width:100%;height:100%;object-fit:contain;background:#fff}
                .rc-list-rename-v115{display:flex;align-items:center;gap:.42rem;flex-wrap:wrap}
                .rc-list-rename-v115 input{height:2.35rem;min-width:17rem;padding:0 .7rem;border:1px solid #ff6338;border-radius:.65rem;background:var(--rc-surface);color:var(--rc-text);font-weight:750;outline:none}
                .rc-list-rename-v115 button{height:2.35rem;padding:0 .7rem;border:0;border-radius:.6rem;font-size:.75rem;font-weight:750;cursor:pointer}
                .rc-list-rename-save-v115{background:#ff6338;color:#fff}.rc-list-rename-cancel-v115{background:var(--rc-soft);color:var(--rc-text)}
                .rc-list-delete-confirm-v115{margin:.7rem 0 .8rem;padding:.75rem .9rem;border-radius:.72rem;background:#fff0ee;display:flex;align-items:center;justify-content:space-between;gap:1rem;color:#c7352c;font-size:.82rem;font-weight:650}
                .rc-list-delete-actions-v115{display:flex;gap:.45rem}.rc-list-delete-actions-v115 button{min-height:2.25rem;padding:0 .8rem;border-radius:.6rem;font-weight:750;cursor:pointer}
                .rc-list-delete-cancel-v115{border:1px solid var(--rc-border);background:#fff;color:#344054}.rc-list-delete-btn-v115{border:0;background:#d8453b;color:#fff}
                .rc-list-chip-stage-v115{position:relative;padding-bottom:.1rem}
                .rc-list-chip-wrap-v115{display:flex;flex-wrap:wrap;gap:.55rem;align-content:flex-start;transition:max-height .2s ease}
                .rc-list-chip-wrap-v115.is-collapsed{max-height:6.55rem;overflow:hidden}
                .rc-list-fade-v115{position:absolute;left:0;right:0;bottom:0;height:2.8rem;pointer-events:none;background:linear-gradient(to bottom,rgba(255,255,255,0),var(--rc-surface) 86%)}
                .rc-list-school-chip-v115{display:inline-flex;align-items:center;gap:.48rem;border:1px solid var(--rc-border);border-radius:.65rem;background:var(--rc-soft);color:var(--rc-text);padding:.32rem .42rem;font-size:.8rem;font-weight:650;line-height:1.2}
                .rc-list-chip-name-v115{border:0;background:transparent;color:inherit;font:inherit;font-weight:650;padding:0;cursor:pointer;max-width:16rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .rc-list-chip-remove-v115{border:0;background:transparent;color:#8a94a6;width:1.15rem;height:1.15rem;display:grid;place-items:center;cursor:pointer;padding:0}.rc-list-chip-remove-v115:hover{color:#ff6338}
                .rc-list-empty-v115{padding:.5rem 0;color:var(--rc-muted);font-size:.82rem}
                .rc-list-expand-v115{width:2rem;height:2rem;border:1px solid var(--rc-border);border-radius:.62rem;background:var(--rc-surface);color:#667085;display:grid;place-items:center;cursor:pointer;box-shadow:0 6px 16px rgba(15,23,42,.08);flex:0 0 auto}
                .rc-list-expand-v115:hover{color:#ff6338;border-color:rgba(255,99,56,.35)}
                .rc-list-expand-v115.is-active{color:#ff6338;border-color:rgba(255,99,56,.35);background:rgba(255,99,56,.08)}
                .rc-list-inline-error-v115{margin-top:.5rem;color:#d92d20;font-size:.76rem;font-weight:600}
                .dark .rc-list-delete-confirm-v115{background:rgba(216,69,59,.14)}
                .dark .rc-list-fade-v115{background:linear-gradient(to bottom,rgba(31,31,35,0),var(--rc-surface) 86%)}
                @media(max-width:780px){.rc-my-lists-head-v115{align-items:flex-start;flex-direction:column}.rc-new-list-btn-v115{width:100%}.rc-list-card-head-v115{align-items:flex-start}.rc-list-rename-v115 input{min-width:11rem}.rc-list-delete-confirm-v115{align-items:flex-start;flex-direction:column}.rc-list-card-actions-v115{align-self:flex-start}}
            </style>

            @include('filament.partials.coach-database-header', [
                'firstName' => $firstName,
                'placeholder' => 'Search schools, coaches, conferences, divisions, lists...',
                'showNewEmail' => false,
            ])

            @php
                $listRows = collect($lists ?? [])->filter(fn ($list) => is_array($list))->values();
                $listColorPalette = ['#ff6338', '#3b82f6', '#22c55e', '#f59e0b', '#7c5cff'];
                $safeListColor = function (?string $color, int $index = 0) use ($listColorPalette): string {
                    $color = strtolower(trim((string) $color));
                    return in_array($color, $listColorPalette, true) ? $color : $listColorPalette[$index % count($listColorPalette)];
                };
                $listInitials = function (string $name): string {
                    return strtoupper(collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->map(fn ($part) => mb_substr((string) $part, 0, 1))->take(2)->implode('') ?: 'S');
                };
                $listLogoUrlFor = function (array $school): string {
                    foreach (['logo_url', 'school_logo_url', 'business_logo_url', 'logo', 'school_logo', 'business_logo'] as $key) {
                        $url = trim((string) ($school[$key] ?? ''));
                        if ($url !== '' && (str_starts_with(strtolower($url), 'http://') || str_starts_with(strtolower($url), 'https://') || str_starts_with($url, '//'))) {
                            return str_starts_with($url, '//') ? 'https:' . $url : $url;
                        }
                    }
                    return '';
                };
                $schoolsForListKey = function (array $list): array {
                    $listKey = (string) ($list['key'] ?? '');
                    if ($listKey === '') return [];
                    return collect($this->allSchools())->filter(fn (array $school): bool => in_array($listKey, $school['list_keys'] ?? [], true))->values()->all();
                };
                $allListSchoolOptions = collect($this->allSchools())->map(function (array $school) use ($listLogoUrlFor, $listInitials): array {
                    $name = (string) ($school['name'] ?? 'School');
                    return [
                        'id' => (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim($name)))),
                        'name' => $name,
                        'logo' => $listLogoUrlFor($school),
                        'initials' => $listInitials($name),
                    ];
                })->filter(fn(array $school): bool => $school['id'] !== '')->values()->all();
            @endphp

            <div class="rc-my-lists-v115">
                <div class="rc-my-lists-head-v115">
                    <div class="rc-my-lists-title-v115">
                        <h2>My Lists</h2>
                        <p>Organize schools into your own lists — Dream Schools, On the Radar, by conference, however you want.</p>
                    </div>
                    <button type="button" class="rc-new-list-btn-v115" wire:click="$set('showNewListComposer', true)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        New List
                    </button>
                </div>

                @if($showNewListComposer)
                    <div class="rc-new-list-panel-v115">
                        <input class="rc-input" placeholder="List name" wire:model.defer="newListName" wire:keydown.enter="createCustomList" autofocus>
                        <div class="rc-list-color-picker-v115">
                            @foreach($listColorPalette as $colorOption)
                                <button type="button" class="rc-list-color-option-v115 {{ strtolower($newListColor) === strtolower($colorOption) ? 'is-selected' : '' }}" style="background:{{ $colorOption }}" wire:click="$set('newListColor', '{{ $colorOption }}')" aria-label="Use {{ $colorOption }}"></button>
                            @endforeach
                        </div>
                        <button class="rc-btn rc-btn-primary" wire:click="createCustomList">Create</button>
                        <button class="rc-btn" type="button" wire:click="$set('showNewListComposer', false)">Cancel</button>
                    </div>
                @endif

                <div class="rc-list-stack-v115">
                    @forelse($listRows as $listIndex => $list)
                        @php
                            $listKey = (string) ($list['key'] ?? '');
                            $listLabel = (string) ($list['label'] ?? 'List');
                            $listSchools = $schoolsForListKey($list);
                            $listSchoolsLight = collect($listSchools)->map(function (array $school) use ($listLogoUrlFor, $listInitials): array {
                                $name = (string) ($school['name'] ?? 'School');
                                return ['id'=>(string)($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim($name)))),'name'=>$name,'logo'=>$listLogoUrlFor($school),'initials'=>$listInitials($name)];
                            })->values()->all();
                            $listColor = $safeListColor($list['color'] ?? null, $listIndex);
                        @endphp
                        <article
                            class="rc-list-card-v115"
                            x-cloak
                            x-show="!deleted"
                            wire:key="list-card-v115-{{ md5($listKey) }}"
                            wire:ignore
                            x-data="{
                                key:@js($listKey), label:@js($listLabel), items:@js($listSchoolsLight), allSchools:@js($allListSchoolOptions),
                                addOpen:false, renameOpen:false, confirmDelete:false, deleted:false, expanded:false,
                                addQuery:'', renameValue:@js($listLabel), adding:{}, removing:{}, savingRename:false, deleting:false, error:'',
                                get addResults(){ const q=String(this.addQuery||'').trim().toLowerCase(); if(q.length<2)return []; const current=new Set(this.items.map(s=>String(s.id))); return this.allSchools.filter(s=>!current.has(String(s.id))&&String(s.name||'').toLowerCase().includes(q)).slice(0,12); },
                                get needsCollapse(){ return this.items.length>10; },
                                async addSchool(s){ const id=String(s?.id||''); if(!id||this.adding[id]||this.items.some(i=>String(i.id)===id))return; const row={id,name:String(s.name||'School'),logo:String(s.logo||''),initials:String(s.initials||'')}; this.error=''; this.items=[...this.items,row]; this.addQuery=''; this.adding={...this.adding,[id]:true}; try{ const r=await $wire.call('queueSchoolListMemberships',id,{[this.key]:true}); if(!r||r.success===false)throw new Error(r?.error||'Unable to add school'); }catch(e){ this.items=this.items.filter(i=>String(i.id)!==id); this.error=e?.message||'Unable to add school.'; }finally{ const n={...this.adding}; delete n[id]; this.adding=n; } },
                                async removeSchool(s){ const id=String(s?.id||''); if(!id||this.removing[id])return; const ix=this.items.findIndex(i=>String(i.id)===id); if(ix<0)return; const removed=this.items[ix]; this.items=this.items.filter(i=>String(i.id)!==id); this.removing={...this.removing,[id]:true}; try{ const r=await $wire.call('queueSchoolListMemberships',id,{[this.key]:false}); if(!r||r.success===false)throw new Error(r?.error||'Unable to remove school'); }catch(e){ const c=[...this.items]; c.splice(Math.min(ix,c.length),0,removed); this.items=c; this.error=e?.message||'Unable to remove school.'; }finally{ const n={...this.removing}; delete n[id]; this.removing=n; } },
                                async saveRename(){ const next=String(this.renameValue||'').trim(); if(!next||this.savingRename)return; const old=this.label; this.label=next; this.renameOpen=false; this.savingRename=true; this.error=''; try{ const r=await $wire.call('renameRecruitingList',this.key,next); if(!r||r.success===false)throw new Error(r?.error||'Unable to rename list'); this.label=String(r.label||next); this.renameValue=this.label; }catch(e){ this.label=old; this.renameValue=old; this.renameOpen=true; this.error=e?.message||'Unable to rename list.'; }finally{ this.savingRename=false; } },
                                async deleteList(){ if(this.deleting)return; this.deleting=true; this.error=''; try{ const r=await $wire.call('deleteCustomList',this.key); if(!r||r.success===false)throw new Error(r?.error||'Unable to delete list'); this.confirmDelete=false; this.deleted=true; }catch(e){ this.error=e?.message||'Unable to delete list.'; }finally{ this.deleting=false; } }
                            }"
                        >
                            <div class="rc-list-card-head-v115">
                                <div class="rc-list-card-title-v115">
                                    <span class="rc-list-dot-v115" style="--list-color:{{ $listColor }}"></span>
                                    <template x-if="!renameOpen"><strong x-text="label"></strong></template>
                                    <template x-if="renameOpen">
                                        <span class="rc-list-rename-v115">
                                            <input type="text" x-model="renameValue" x-on:keydown.enter.prevent="saveRename()" x-on:keydown.escape.prevent="renameOpen=false;renameValue=label" maxlength="80" autofocus>
                                            <button type="button" class="rc-list-rename-save-v115" x-on:click="saveRename()">Save</button>
                                            <button type="button" class="rc-list-rename-cancel-v115" x-on:click="renameOpen=false;renameValue=label">Cancel</button>
                                        </span>
                                    </template>
                                    <span class="rc-list-count-pill-v115"><span x-text="items.length"></span> <span x-text="items.length===1?'school':'schools'"></span></span>
                                </div>
                                <div class="rc-list-card-actions-v115">
                                    <button type="button" class="rc-list-icon-btn-v115" x-bind:class="addOpen?'is-active':''" x-on:click="addOpen=!addOpen;renameOpen=false;confirmDelete=false;$nextTick(()=>addOpen&&$refs.addInput?.focus())" title="Add schools" aria-label="Add schools">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                    <button type="button" class="rc-list-icon-btn-v115" x-bind:class="renameOpen?'is-active':''" x-on:click="renameOpen=!renameOpen;addOpen=false;confirmDelete=false;$nextTick(()=>renameOpen&&$el.closest('article')?.querySelector('.rc-list-rename-v115 input')?.focus())" title="Rename list" aria-label="Rename list">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="m9 15 5-5 2 2-5 5H9v-2Z"/></svg>
                                    </button>
                                    <button type="button" class="rc-list-icon-btn-v115" x-bind:class="confirmDelete?'is-active':''" x-on:click="confirmDelete=!confirmDelete;addOpen=false;renameOpen=false" title="Delete list" aria-label="Delete list">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                                    </button>
                                    <button type="button" class="rc-list-expand-v115" x-cloak x-show="needsCollapse" x-bind:class="expanded?'is-active':''" x-on:click="expanded=!expanded" :title="expanded?'Minimize list':'Maximize list'" :aria-label="expanded?'Minimize list':'Maximize list'">
                                        <svg x-show="!expanded" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/></svg>
                                        <svg x-show="expanded" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h5V3M21 8h-5V3M3 16h5v5M21 16h-5v5"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="rc-list-tools-v115" x-cloak x-show="addOpen" x-transition.opacity>
                                <div class="rc-list-search-v115">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                    <input x-ref="addInput" type="search" x-model="addQuery" placeholder="Search a school to add..." autocomplete="off">
                                    <div class="rc-list-add-results-v115" x-cloak x-show="String(addQuery||'').trim().length>=2">
                                        <template x-for="school in addResults" :key="`add-${key}-${school.id}`">
                                            <button type="button" class="rc-list-add-result-v115" x-on:click="addSchool(school)">
                                                <span class="rc-list-add-logo-v115"><template x-if="school.logo"><img :src="school.logo" :alt="`${school.name} logo`" onerror="this.remove()"></template><span x-show="!school.logo" x-text="school.initials"></span></span>
                                                <span x-text="school.name"></span>
                                            </button>
                                        </template>
                                        <div class="rc-list-empty-v115" x-show="addResults.length===0">No matching schools available.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="rc-list-delete-confirm-v115" x-cloak x-show="confirmDelete" x-transition.opacity>
                                <span>Delete “<span x-text="label"></span>” and remove all of its school memberships?</span>
                                <div class="rc-list-delete-actions-v115">
                                    <button type="button" class="rc-list-delete-cancel-v115" x-on:click="confirmDelete=false">Cancel</button>
                                    <button type="button" class="rc-list-delete-btn-v115" x-on:click="deleteList()" x-bind:disabled="deleting"><span x-text="deleting?'Deleting…':'Delete'"></span></button>
                                </div>
                            </div>

                            <div class="rc-list-chip-stage-v115">
                                <div class="rc-list-chip-wrap-v115" x-bind:class="needsCollapse&&!expanded?'is-collapsed':''">
                                    <template x-for="school in items" :key="`list-${key}-${school.id}`">
                                        <span class="rc-list-school-chip-v115">
                                            <span class="rc-list-chip-logo-v115"><template x-if="school.logo"><img :src="school.logo" :alt="`${school.name} logo`" onerror="this.remove()"></template><span x-show="!school.logo" x-text="school.initials"></span></span>
                                            <button type="button" class="rc-list-chip-name-v115" x-on:click.stop="openGlobalSchool(school)" x-text="school.name"></button>
                                            <button type="button" class="rc-list-chip-remove-v115" x-on:click="removeSchool(school)" :aria-label="`Remove ${school.name}`">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                            </button>
                                        </span>
                                    </template>
                                    <div class="rc-list-empty-v115" x-show="items.length===0">No schools in this list yet. Use the plus button to add one.</div>
                                </div>
                                <div class="rc-list-fade-v115" x-cloak x-show="needsCollapse&&!expanded"></div>
                            </div>


                            <div class="rc-list-inline-error-v115" x-cloak x-show="error" x-text="error"></div>
                        </article>
                    @empty
                        <div class="rc-list-empty-v115">No lists yet. Create your first list to start organizing schools.</div>
                    @endforelse
                </div>
            </div>
        @endif


        @if($section === 'coaches')
            <div class="rc-card rc-toolbar is-flat"><input class="rc-input" placeholder="Search coaches" wire:model.live.debounce.400ms="coachSearch" /></div>
            <div class="rc-card">
                @forelse($this->filteredCoaches as $coach)
                    @include('filament.partials.coach-row', ['coach' => $coach])
                @empty
                    <div class="rc-subtle">Coaches are still loading. Schools appear first, then coaches are added as each school syncs.</div>
                @endforelse
                @if($this->canLoadMoreCoaches)<div style="margin-top:1rem"><button class="rc-btn" wire:click="loadMoreCoaches">Load more</button></div>@endif
            </div>
        @endif

        {{-- v118: Inbox restored from the supplied latest reference implementation. --}}
        @if($section === 'conversations')
            @include('filament.partials.coach-database-header', [
                'firstName' => $firstName,
                'placeholder' => 'Search schools, coaches, conferences...',
                'showNewEmail' => false,
            ])



            <style>
                .rc-inbox-shell-v56{grid-template-columns:19.5rem minmax(0,1fr)20rem;min-height:34rem;height:calc(100vh - 11.5rem);max-height:calc(100vh - 8rem)}
                .rc-inbox-panel-head-v56{padding:.8rem .95rem .58rem}.rc-inbox-panel-head-v56 h2{font-size:1rem}.rc-inbox-search-v56{padding:0 .95rem .55rem}.rc-inbox-search-v56 input{height:2.15rem;font-size:.78rem}.rc-inbox-tabs-v56{padding:0 .95rem .55rem;gap:.72rem}.rc-inbox-tab-v56{font-size:.74rem}.rc-thread-card-v56{grid-template-columns:2.05rem minmax(0,1fr)auto;padding:.7rem .9rem;gap:.55rem}.rc-thread-logo-v56{width:1.9rem;height:1.9rem}.rc-thread-name-v56{font-size:.8rem}.rc-thread-school-v56,.rc-thread-preview-v56{font-size:.7rem}.rc-thread-status-v56{font-size:.62rem;padding:.12rem .34rem;margin-top:.35rem}.rc-inbox-mid-head-v56{min-height:4.25rem;padding:.62rem .95rem}.rc-inbox-coach-title-v56{grid-template-columns:2.15rem minmax(0,1fr)}.rc-inbox-school-logo-v56{width:2rem;height:2rem}.rc-inbox-coach-title-v56 h3{font-size:.9rem}.rc-inbox-coach-title-v56 p{font-size:.72rem}.rc-inbox-open-composer-v56{min-height:1.9rem;font-size:.72rem;padding:0 .58rem}.rc-inbox-icon-btn-v56{width:1.9rem;height:1.9rem}.rc-message-stream-v56{overflow:auto;max-height:none;height:100%;padding:.9rem;scroll-behavior:auto}.rc-inbox-message-v56{grid-template-columns:2rem minmax(0,1fr);gap:.55rem}.rc-msg-avatar-v56{width:1.9rem;height:1.9rem;font-size:.68rem}.rc-msg-meta-v56{font-size:.68rem;margin-bottom:.35rem}.rc-msg-bubble-v56{width:min(100%,36rem);max-width:100%;padding:.78rem .85rem;font-size:.82rem;line-height:1.5;overflow-wrap:anywhere;word-break:break-word;white-space:normal}.rc-msg-bubble-v56 a{color:#2563eb;text-decoration:underline;overflow-wrap:break-word;word-break:normal}.rc-msg-bubble-v56 a.rc-message-link-short{display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;vertical-align:bottom;white-space:nowrap}.rc-msg-bubble-v56 img{max-width:100%;height:auto;border-radius:.55rem;display:block;margin:.5rem 0}.rc-msg-bubble-v56 p{margin:.35rem 0}.rc-msg-bubble-v56 pre{white-space:pre-wrap;overflow-wrap:anywhere}.rc-message-attachment-image{max-width:100%;height:auto;display:block}.rc-message-attachment-link{max-width:100%;overflow-wrap:anywhere}.rc-inbox-right-v56{min-width:0}.rc-coach-cover-v56{height:5rem}.rc-profile-content-v56{padding:0 .9rem .9rem}.rc-profile-avatar-v56{width:3.3rem;height:3.3rem;margin-top:-1.7rem}.rc-profile-name-v56 h3{font-size:.9rem}.rc-profile-sub-v56,.rc-contact-line-v56{font-size:.72rem}.rc-profile-actions-v56{gap:.45rem}.rc-profile-action-v56{min-height:2.8rem;font-size:.7rem}.rc-about-grid-v56{grid-template-columns:1fr;gap:.55rem}.rc-about-item-v56{font-size:.68rem}.rc-inbox-icon-btn-v56.is-starred{color:#f59e0b;background:rgba(245,158,11,.12)}.rc-inbox-icon-btn-v56.is-starred svg{fill:currentColor}.rc-compose-history{font-family:Arial,Helvetica,sans-serif}.rc-compose-history-message a{color:#2563eb;text-decoration:underline}.rc-compose-history-message img{max-width:100%;height:auto;border-radius:.5rem;margin:.4rem 0}.rc-compose-history-message p{margin:.25rem 0}

                .rc-inbox-shell-v56,
                .rc-inbox-list-v56,
                .rc-message-stream-v56 {
                    scroll-behavior: auto !important;
                    overscroll-behavior: contain;
                    contain: layout paint style;
                }
                .rc-inbox-list-v56,
                .rc-message-stream-v56 {
                    transform: translateZ(0);
                    will-change: auto;
                }
                .rc-inbox-message-v56 {
                    content-visibility: auto;
                    contain-intrinsic-size: 9rem;
                    contain: layout paint style;
                }
                .rc-msg-bubble-v56,
                .rc-msg-bubble-v56 * {
                    max-width: 100%;
                }
                .rc-msg-bubble-v56 a {
                    overflow-wrap: anywhere;
                    word-break: break-word;
                }

                .rc-inbox-history-trimmed{margin:.3rem 0 .7rem;padding:.5rem .7rem;border:1px solid var(--rc-border);border-radius:.7rem;background:var(--rc-soft);color:var(--rc-muted);font-size:.72rem;text-align:center;}
                .rc-message-stream-v56{padding:0 .9rem .9rem !important;}
                .rc-inbox-load-older-top{position:sticky;top:0;z-index:8;display:flex;align-items:center;justify-content:center;height:2.65rem;margin:0 -.9rem .9rem;padding:0;background:var(--rc-surface);border-bottom:1px solid var(--rc-border);}
                .rc-inbox-load-older-top .rc-inbox-open-composer-v56{background:var(--rc-surface);}

                @media (max-width:1320px){.rc-inbox-shell-v56{grid-template-columns:18.5rem minmax(0,1fr)}.rc-inbox-right-v56{display:none}}
                @media (max-width:900px){.rc-inbox-shell-v56{grid-template-columns:1fr;height:auto;max-height:none}.rc-message-stream-v56{height:auto;max-height:38rem}}
            </style>

            <div class="rc-section-async-banner {{ $isLoadingConversations ? 'is-visible' : '' }}">
                Loading conversations. Use the refresh button to update the inbox.
            </div>

            @php
                $inboxConversations = collect($this->filteredConversations ?? [])->values();
                $selectedConversation = $selectedConversationId ? collect($this->conversations)->firstWhere('id', $selectedConversationId) : null;
                if (! $selectedConversation && $inboxConversations->isNotEmpty()) {
                    $selectedConversation = $inboxConversations->first();
                }
                $selectedContactId = (string) ($selectedConversation['contact_id'] ?? $selectedConversation['contactId'] ?? '');
                $selectedEmail = strtolower(trim((string) ($selectedConversation['email'] ?? $selectedConversation['contact_email'] ?? '')));
                $selectedCoach = null;
                if ($selectedContactId !== '') {
                    $selectedCoach = collect($this->allCoaches())->firstWhere('id', $selectedContactId);
                }
                if (! $selectedCoach && $selectedEmail !== '') {
                    $selectedCoach = collect($this->allCoaches())->first(function ($coach) use ($selectedEmail) {
                        return strtolower(trim((string) ($coach['email'] ?? ''))) === $selectedEmail;
                    });
                }
                $selectedName = (string) ($selectedConversation['contact_name'] ?? $selectedConversation['name'] ?? data_get($selectedCoach, 'name') ?? 'Coach');
                $selectedSchool = (string) ($selectedConversation['school'] ?? $selectedConversation['company_name'] ?? data_get($selectedCoach, 'school') ?? data_get($selectedCoach, 'company_name') ?? 'School');
                $selectedTitle = (string) (data_get($selectedCoach, 'title') ?? $selectedConversation['title'] ?? 'Coach');
                $selectedInitials = strtoupper(collect(explode(' ', trim($selectedName)))->filter()->map(fn($part) => substr((string) $part, 0, 1))->take(2)->implode('') ?: 'C');
                $selectedSchoolLogo = trim((string) (data_get($selectedCoach, 'school_logo_url') ?? data_get($selectedCoach, 'business_logo_url') ?? data_get($selectedCoach, 'logo_url') ?? $selectedConversation['school_logo_url'] ?? $selectedConversation['logo_url'] ?? ''));
                $selectedStarred = (bool) ($selectedConversation['starred'] ?? $selectedConversation['is_starred'] ?? false);
                $threadMessages = is_array($messages ?? null) ? $messages : [];
                $filterStatus = $conversationStatusFilter ?? 'all';
                $threadInitials = function (string $name): string {
                    return strtoupper(collect(explode(' ', trim($name)))->filter()->map(fn($part) => substr((string) $part, 0, 1))->take(2)->implode('') ?: 'C');
                };
                $threadLogo = function (array $conversation) {
                    $contactId = (string) ($conversation['contact_id'] ?? $conversation['contactId'] ?? '');
                    $email = strtolower(trim((string) ($conversation['email'] ?? $conversation['contact_email'] ?? '')));
                    $coach = null;
                    if ($contactId !== '') {
                        $coach = collect($this->allCoaches())->firstWhere('id', $contactId);
                    }
                    if (! $coach && $email !== '') {
                        $coach = collect($this->allCoaches())->first(fn($row) => strtolower(trim((string) ($row['email'] ?? ''))) === $email);
                    }
                    return trim((string) ($conversation['school_logo_url'] ?? $conversation['logo_url'] ?? data_get($coach, 'school_logo_url') ?? data_get($coach, 'business_logo_url') ?? data_get($coach, 'logo_url') ?? ''));
                };
                $formatInboxDate = function ($value): string {
                    if (! $value) { return ''; }
                    try {
                        if (is_numeric($value)) {
                            $date = \Illuminate\Support\Carbon::createFromTimestampMs((int) $value);
                        } else {
                            $date = \Illuminate\Support\Carbon::parse($value);
                        }
                        return $date->isCurrentYear() ? $date->format('M j') : $date->format('M j, Y');
                    } catch (\Throwable $exception) {
                        return is_scalar($value) ? (string) $value : '';
                    }
                };
                $formatMessageDate = function ($value): string {
                    if (! $value) { return ''; }
                    try {
                        if (is_numeric($value)) {
                            $date = \Illuminate\Support\Carbon::createFromTimestampMs((int) $value);
                        } else {
                            $date = \Illuminate\Support\Carbon::parse($value);
                        }
                        return $date->format('M j, g:i A');
                    } catch (\Throwable $exception) {
                        return is_scalar($value) ? (string) $value : '';
                    }
                };
                $prepareInboxEmailDocument = function ($body): string {
                    $raw = trim((string) $body);

                    if ($raw === '') {
                        return '<!doctype html><html><body style="margin:0;font:14px Arial,sans-serif;color:#64748b">No message body.</body></html>';
                    }

                    $decoded = $raw;
                    for ($i = 0; $i < 3; $i++) {
                        $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        if ($next === $decoded || trim($next) === '') {
                            break;
                        }
                        $decoded = $next;
                    }

                    $hasDocumentHtml = (bool) preg_match('/<!doctype\s+html|<html\b|<head\b|<body\b/i', $decoded);
                    $hasHtml = (bool) preg_match('/<\s*(table|tbody|tr|td|p|div|br|a|img|ul|ol|li|span|strong|em|h[1-6])\b/i', $decoded);

                    if (! $hasHtml) {
                        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
                            . '<body style="margin:0;padding:0;font:14px/1.6 Arial,sans-serif;color:#111827;white-space:pre-wrap;overflow-wrap:anywhere">'
                            . e($decoded)
                            . '</body></html>';
                    }

                    // GHL's email detail endpoint returns the complete compiled email
                    // document in emailMessage.body. Keep its head, style blocks,
                    // media queries, tables, buttons, images, and signatures intact.
                    // Scripts are removed because email clients do not execute them.
                    $clean = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $decoded) ?? $decoded;
                    $clean = preg_replace('/<\s*script\b[^>]*\/?>/is', '', $clean) ?? $clean;
                    $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
                    $clean = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $clean) ?? $clean;

                    $responsiveEmailCss = <<<'CSS'
<style id="rc-inbox-email-fit-v62">
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
        -webkit-text-size-adjust: 100% !important;
        text-size-adjust: 100% !important;
    }
    body {
        font-size: 12px !important;
        line-height: 1.45 !important;
    }
    body, body table, body td, body div, body p, body span,
    body a, body li, body strong, body em {
        box-sizing: border-box !important;
        max-width: 100% !important;
    }
    body table {
        max-width: 100% !important;
    }
    body img {
        max-width: 100% !important;
        height: auto !important;
        object-fit: contain !important;
    }
    body p, body li, body td, body div, body span, body a {
        overflow-wrap: anywhere !important;
        word-break: normal !important;
    }
    body p, body li, body td, body div, body span {
        font-size: 12px !important;
        line-height: 1.45 !important;
    }
    body a {
        font-size: 12px !important;
        line-height: 1.35 !important;
    }
    body h1 { font-size: 20px !important; line-height: 1.2 !important; }
    body h2 { font-size: 18px !important; line-height: 1.22 !important; }
    body h3 { font-size: 16px !important; line-height: 1.25 !important; }
    body h4, body h5, body h6 { font-size: 14px !important; line-height: 1.3 !important; }
    .email-content,
    body > div,
    body > table {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }
    @media (max-width: 640px) {
        body table[width] { width: 100% !important; }
        body td[width] { max-width: 100% !important; }
    }
</style>
CSS;

                    if ($hasDocumentHtml) {
                        if (preg_match('/<\/head\s*>/i', $clean)) {
                            return preg_replace('/<\/head\s*>/i', $responsiveEmailCss . '</head>', $clean, 1) ?? $clean;
                        }

                        if (preg_match('/<body\b/i', $clean)) {
                            return preg_replace('/<body\b/i', '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $responsiveEmailCss . '</head><body', $clean, 1) ?? $clean;
                        }

                        return $responsiveEmailCss . $clean;
                    }

                    return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                        . $responsiveEmailCss
                        . '</head><body style="margin:0;padding:0">' . $clean . '</body></html>';
                };
            @endphp

            <style>
                .rc-msg-bubble-email-v61 {
                    display:block;
                    width:min(100%,42rem);
                    max-width:100%;
                    height:auto;
                    min-height:0;
                    background:#f1f5f9;
                    padding:.55rem;
                    overflow:visible;
                }
                rc-inbox-email-view.rc-email-document-v64 {
                    display:block;
                    width:100%;
                    max-width:100%;
                    height:auto;
                    min-height:0;
                    overflow:visible;
                    contain:layout style;
                }
                rc-inbox-email-view.rc-email-document-v64::part(toggle) {
                    position:absolute;
                    right:.25rem;
                    bottom:.2rem;
                }
                .rc-message-stream-v56 { position:relative; }
                .rc-inbox-thread-loader-v63 {
                    position:absolute;
                    inset:0;
                    z-index:40;
                    align-items:center;
                    justify-content:center;
                    background:color-mix(in srgb, var(--rc-surface) 90%, transparent);
                    backdrop-filter:blur(2px);
                }
                .rc-inbox-thread-loader-card-v63 {
                    display:inline-flex;
                    align-items:center;
                    gap:.55rem;
                    min-height:2.6rem;
                    padding:.65rem .9rem;
                    border:1px solid var(--rc-border);
                    border-radius:.8rem;
                    background:var(--rc-surface);
                    color:var(--rc-text);
                    box-shadow:0 14px 34px rgba(15,23,42,.14);
                    font-size:.78rem;
                    font-weight:750;
                }
            </style>
            <script>
                (() => {
                    if (customElements.get('rc-inbox-email-view')) return;

                    class RcInboxEmailView extends HTMLElement {
                        connectedCallback() {
                            if (this.shadowRoot) return;

                            const template = this.querySelector('template');
                            if (!(template instanceof HTMLTemplateElement)) return;

                            const parsed = new DOMParser().parseFromString(template.innerHTML, 'text/html');
                            const shadow = this.attachShadow({ mode: 'open' });

                            const base = document.createElement('style');
                            base.textContent = `
                                :host { display:block; width:100%; max-width:100%; height:auto; min-height:0; position:relative; }
                                *, *::before, *::after { box-sizing:border-box; }
                                .rc-email-viewport { display:block; width:100%; max-width:100%; min-width:0; max-height:none; overflow:visible; transition:max-height .18s ease; }
                                :host([data-collapsible="1"]:not([data-expanded="1"])) .rc-email-viewport { max-height:100px; overflow:hidden; padding-right:2rem; }
                                :host([data-collapsible="1"]:not([data-expanded="1"]))::after { content:""; position:absolute; left:0; right:0; bottom:0; height:2.75rem; z-index:2; pointer-events:none; background:linear-gradient(to bottom, rgba(242,244,248,0), rgba(242,244,248,.96) 78%, rgba(242,244,248,1)); }
                                .rc-email-root { display:block; width:100%; max-width:100%; min-width:0; margin:0; overflow:visible; font-size:12px; line-height:1.45; }
                                .rc-email-root img { max-width:100% !important; height:auto !important; }
                                .rc-email-root table { max-width:100% !important; }
                                .rc-email-root td, .rc-email-root th { max-width:100% !important; }
                                .rc-email-root a { overflow-wrap:anywhere; word-break:break-word; }
                                .rc-email-toggle { display:none; position:absolute; right:.2rem; bottom:.18rem; z-index:4; width:1.75rem; height:1.75rem; padding:0; border:0; border-radius:0; background:transparent; color:#475569; align-items:center; justify-content:center; cursor:pointer; box-shadow:none; }
                                :host([data-collapsible="1"]) .rc-email-toggle { display:flex; }
                                .rc-email-toggle svg { width:.9rem; height:.9rem; transition:transform .18s ease; }
                                :host([data-expanded="1"]) .rc-email-toggle svg { transform:rotate(180deg); }
                            `;
                            shadow.appendChild(base);

                            parsed.head.querySelectorAll('style, link[rel="stylesheet"]').forEach((node) => {
                                const clone = node.cloneNode(true);
                                if (clone instanceof HTMLStyleElement) {
                                    clone.textContent = String(clone.textContent || '').replace(/\bbody\b/g, '.rc-email-root');
                                }
                                shadow.appendChild(clone);
                            });

                            const viewport = document.createElement('div');
                            viewport.className = 'rc-email-viewport';

                            const root = document.createElement('div');
                            root.className = `rc-email-root ${parsed.body.className || ''}`.trim();

                            const bodyStyle = parsed.body.getAttribute('style');
                            if (bodyStyle) root.setAttribute('style', bodyStyle);

                            Array.from(parsed.body.childNodes).forEach((node) => {
                                root.appendChild(document.importNode(node, true));
                            });

                            viewport.appendChild(root);
                            shadow.appendChild(viewport);

                            const toggle = document.createElement('button');
                            toggle.type = 'button';
                            toggle.className = 'rc-email-toggle';
                            toggle.setAttribute('part', 'toggle');
                            toggle.setAttribute('aria-label', 'Expand email');
                            toggle.setAttribute('aria-expanded', 'false');
                            toggle.innerHTML = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                            toggle.addEventListener('click', () => {
                                const expanded = this.dataset.expanded === '1';
                                if (expanded) {
                                    delete this.dataset.expanded;
                                    toggle.setAttribute('aria-expanded', 'false');
                                    toggle.setAttribute('aria-label', 'Expand email');
                                } else {
                                    this.dataset.expanded = '1';
                                    toggle.setAttribute('aria-expanded', 'true');
                                    toggle.setAttribute('aria-label', 'Collapse email');
                                }
                            });
                            shadow.appendChild(toggle);

                            // Keep inbox controls authoritative even after delayed email
                            // stylesheets finish loading. Email HTML can contain broad rules
                            // such as div/button/* selectors, so this guard must be the final
                            // stylesheet in the shadow root and use !important.
                            const guard = document.createElement('style');
                            guard.textContent = `
                                :host { display:block !important; width:100% !important; max-width:100% !important; height:auto !important; min-height:0 !important; position:relative !important; overflow:visible !important; }
                                .rc-email-viewport { display:block !important; width:100% !important; max-width:100% !important; min-width:0 !important; max-height:none !important; height:auto !important; overflow:visible !important; position:relative !important; }
                                :host([data-collapsible="1"]:not([data-expanded="1"])) .rc-email-viewport { max-height:100px !important; overflow:hidden !important; padding-right:2rem !important; }
                                :host([data-collapsible="1"]:not([data-expanded="1"]))::after { content:"" !important; display:block !important; position:absolute !important; left:0 !important; right:0 !important; bottom:0 !important; height:2.75rem !important; z-index:2147483645 !important; pointer-events:none !important; background:linear-gradient(to bottom, rgba(242,244,248,0), rgba(242,244,248,.96) 78%, rgba(242,244,248,1)) !important; }
                                .rc-email-toggle { display:none !important; position:absolute !important; right:.2rem !important; bottom:.18rem !important; z-index:2147483646 !important; width:1.75rem !important; height:1.75rem !important; min-width:0 !important; min-height:0 !important; margin:0 !important; padding:0 !important; border:0 !important; border-radius:0 !important; background:transparent !important; color:#475569 !important; align-items:center !important; justify-content:center !important; cursor:pointer !important; box-shadow:none !important; appearance:none !important; }
                                :host([data-collapsible="1"]) .rc-email-toggle { display:flex !important; }
                                .rc-email-toggle svg { display:block !important; width:.9rem !important; height:.9rem !important; min-width:.9rem !important; min-height:.9rem !important; transition:transform .18s ease !important; }
                                :host([data-expanded="1"]) .rc-email-toggle svg { transform:rotate(180deg) !important; }
                            `;
                            shadow.appendChild(guard);
                            template.remove();

                            const evaluateHeight = () => {
                                const height = Math.ceil(root.getBoundingClientRect().height);
                                if (height > 100) this.dataset.collapsible = '1';
                            };

                            requestAnimationFrame(() => requestAnimationFrame(evaluateHeight));
                            root.querySelectorAll('img').forEach((image) => {
                                if (!image.complete) {
                                    image.addEventListener('load', evaluateHeight, { once:true });
                                    image.addEventListener('error', evaluateHeight, { once:true });
                                }
                            });
                            if (document.fonts?.ready) document.fonts.ready.then(evaluateHeight).catch(() => {});
                        }
                    }

                    customElements.define('rc-inbox-email-view', RcInboxEmailView);
                })();

                (() => {
                    let activeRun = 0;
                    let observer = null;
                    let observedStream = null;
                    let loadingOlderMessages = false;
                    let forceLatestAfterConversationChange = true;
                    let olderAnchor = null;
                    let scrollListener = null;

                    const getStream = () => document.querySelector('[data-rc-inbox-message-stream]');
                    const isNearBottom = (stream, threshold = 120) => {
                        if (!stream) return false;
                        return (stream.scrollHeight - stream.scrollTop - stream.clientHeight) <= threshold;
                    };

                    const moveToLatest = (force = false) => {
                        if (loadingOlderMessages) return false;

                        const stream = getStream();
                        if (!stream) return false;
                        if (!force && !forceLatestAfterConversationChange && !isNearBottom(stream)) return false;

                        stream.scrollTo({ top: stream.scrollHeight, behavior: 'auto' });
                        forceLatestAfterConversationChange = false;
                        return true;
                    };

                    const restoreOlderAnchor = () => {
                        if (!loadingOlderMessages || !olderAnchor) return;

                        const run = ++activeRun;
                        const delays = [0, 16, 50, 120, 250, 500];
                        delays.forEach((delay, index) => {
                            window.setTimeout(() => {
                                if (run !== activeRun || !olderAnchor) return;
                                const stream = getStream();
                                if (!stream) return;

                                const addedHeight = Math.max(0, stream.scrollHeight - olderAnchor.height);
                                stream.scrollTop = Math.max(0, olderAnchor.top + addedHeight);

                                if (index === delays.length - 1) {
                                    loadingOlderMessages = false;
                                    olderAnchor = null;
                                }
                            }, delay);
                        });
                    };

                    const showLatestMessage = (force = false) => {
                        if (loadingOlderMessages) return;

                        const stream = getStream();
                        if (!stream) return;
                        if (!force && !forceLatestAfterConversationChange && !isNearBottom(stream)) return;

                        const run = ++activeRun;
                        const delays = force ? [0, 16, 50, 120, 250, 500, 900] : [0, 50, 180];
                        delays.forEach((delay) => {
                            window.setTimeout(() => {
                                if (run !== activeRun || loadingOlderMessages) return;
                                moveToLatest(force);
                            }, delay);
                        });
                    };

                    const observeStream = () => {
                        const stream = getStream();
                        if (!stream) return;
                        if (observedStream === stream) return;

                        observer?.disconnect();
                        if (observedStream && scrollListener) {
                            observedStream.removeEventListener('scroll', scrollListener);
                        }

                        observedStream = stream;
                        scrollListener = () => {
                            // Once the user intentionally scrolls away from the newest
                            // message, never fight them by snapping back to the bottom.
                            if (!loadingOlderMessages && !isNearBottom(stream)) {
                                forceLatestAfterConversationChange = false;
                                activeRun += 1;
                            }
                        };
                        stream.addEventListener('scroll', scrollListener, { passive: true });

                        observer = new MutationObserver(() => {
                            if (loadingOlderMessages) {
                                restoreOlderAnchor();
                                return;
                            }

                            if (forceLatestAfterConversationChange || isNearBottom(stream)) {
                                showLatestMessage(forceLatestAfterConversationChange);
                            }
                        });
                        observer.observe(stream, { childList:true, subtree:true });

                        stream.addEventListener('load', () => {
                            if (loadingOlderMessages) {
                                restoreOlderAnchor();
                                return;
                            }

                            if (forceLatestAfterConversationChange || isNearBottom(stream)) {
                                showLatestMessage(forceLatestAfterConversationChange);
                            }
                        }, true);

                        if (forceLatestAfterConversationChange) showLatestMessage(true);
                    };

                    document.addEventListener('click', (event) => {
                        const clickedElement = event.target instanceof Element
                            ? event.target.closest('[wire\\:click]')
                            : null;
                        const loadOlderButton = clickedElement?.getAttribute('wire:click') === 'loadOlderConversationMessages'
                            ? clickedElement
                            : null;

                        if (loadOlderButton) {
                            const stream = getStream();
                            loadingOlderMessages = true;
                            forceLatestAfterConversationChange = false;
                            activeRun += 1;
                            olderAnchor = stream ? { height: stream.scrollHeight, top: stream.scrollTop } : null;
                            return;
                        }

                        if (!event.target?.closest?.('[data-rc-inbox-conversation-trigger]')) return;
                        loadingOlderMessages = false;
                        olderAnchor = null;
                        forceLatestAfterConversationChange = true;
                        activeRun += 1;

                        window.setTimeout(() => {
                            observeStream();
                            showLatestMessage(true);
                        }, 0);
                    }, true);

                    const boot = () => {
                        forceLatestAfterConversationChange = true;
                        observeStream();
                        showLatestMessage(true);
                    };

                    document.addEventListener('DOMContentLoaded', boot, { once:true });
                    document.addEventListener('livewire:navigated', boot);
                    document.addEventListener('livewire:initialized', boot);

                    if (window.Livewire?.hook) {
                        window.Livewire.hook('morph.updated', ({ el }) => {
                            if (el?.matches?.('[data-rc-inbox-message-stream]')
                                || el?.querySelector?.('[data-rc-inbox-message-stream]')) {
                                observeStream();
                                if (loadingOlderMessages) restoreOlderAnchor();
                                else if (forceLatestAfterConversationChange || isNearBottom(getStream())) {
                                    showLatestMessage(forceLatestAfterConversationChange);
                                }
                            }
                        });

                        window.Livewire.hook('commit', ({ succeed }) => {
                            succeed(() => {
                                queueMicrotask(() => {
                                    observeStream();
                                    if (loadingOlderMessages) restoreOlderAnchor();
                                    else if (forceLatestAfterConversationChange || isNearBottom(getStream())) {
                                        showLatestMessage(forceLatestAfterConversationChange);
                                    }
                                });
                            });
                        });
                    }
                })();
            </script>


            <style id="rc-inbox-unread-status-v73">
                .rc-thread-unread-dot-v56{width:.62rem!important;height:.62rem!important;border-radius:999px!important;background:#ff6338!important;box-shadow:0 0 0 3px rgba(255,99,56,.14)!important;}
                .rc-inbox-icon-btn-v56.is-unread{color:#ff6338!important;background:rgba(255,99,56,.11)!important;border-color:rgba(255,99,56,.28)!important;}
                .rc-message-status-v56.is-opened{color:#16a34a!important;}
                .rc-message-status-v56.is-error{color:#dc2626!important;}
            </style>
            <div class="rc-inbox-page-v56"
                x-data
                x-init="$nextTick(async () => { await $wire.bootDeferredUiData(); await $wire.ensureInboxConversationLoaded(); })">
                <div class="rc-inbox-shell-v56">
                    <aside class="rc-inbox-left-v56">
                        <div class="rc-inbox-panel-head-v56">
                            <h2>Conversations</h2>
                            <div class="rc-inbox-head-actions-v56">
                                <button type="button" class="rc-inbox-icon-btn-v56" wire:click="refreshConversationsRealtime" wire:loading.attr="disabled" wire:target="refreshConversationsRealtime" title="Refresh conversations" aria-label="Refresh conversations">
                                <span wire:loading.remove wire:target="refreshConversationsRealtime">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M4 7h11M4 12h16M4 17h11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                </span>
                                <span wire:loading.flex wire:target="refreshConversationsRealtime" class="rc-spinner-mini"></span>
                                </button>
                            </div>
                        </div>

                        <div class="rc-inbox-search-v56">
                            <label>
                                <svg viewBox="0 0 24 24" fill="none"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                <input type="search" placeholder="Search conversations..." wire:model.live.debounce.450ms="conversationSearch">
                            </label>
                        </div>

                        <div class="rc-inbox-quick-filters-v56" role="group" aria-label="Conversation filters">
                            <button type="button" class="{{ $filterStatus === 'all' ? 'is-active' : '' }}" wire:click="$set('conversationStatusFilter', 'all')">All</button>
                            <button type="button" class="{{ $filterStatus === 'unread' ? 'is-active' : '' }}" wire:click="$set('conversationStatusFilter', 'unread')">
                                Unread
                                @php $unreadConversationCount = collect($this->conversations ?? [])->filter(fn ($row) => is_array($row) && (int) ($row['unread_count'] ?? 0) > 0)->count(); @endphp
                                <span wire:key="unread-count-{{ $unreadConversationCount }}">{{ $unreadConversationCount }}</span>
                            </button>
                            <button type="button" class="{{ $filterStatus === 'starred' ? 'is-active' : '' }}" wire:click="$set('conversationStatusFilter', 'starred')">
                                Starred
                                @php $starredConversationCount = collect($this->conversations ?? [])->filter(fn ($row) => is_array($row) && (bool) ($row['starred'] ?? $row['is_starred'] ?? false))->count(); @endphp
                                <span wire:key="starred-count-{{ $starredConversationCount }}">{{ $starredConversationCount }}</span>
                            </button>
                        </div>

                        @if(empty($inboxConversations))
                            <div wire:loading.delay.longer.flex wire:target="bootDeferredUiData,loadConversations" class="rc-loading-inline" style="padding:.55rem .95rem">
                                <span class="rc-spinner-mini"></span> Loading inbox
                            </div>
                        @endif

                        <div
                            class="rc-inbox-list-v56"
                            x-data="{
                                selectedConversationId: window.__rcInboxPendingConversationId || @js((string) ($selectedConversationId ?? '')),
                                init() {
                                    const serverConversationId = @js((string) ($selectedConversationId ?? ''));

                                    if (window.__rcInboxPendingConversationId
                                        && serverConversationId === window.__rcInboxPendingConversationId) {
                                        window.__rcInboxPendingConversationId = null;
                                    }

                                    this.selectedConversationId = window.__rcInboxPendingConversationId || serverConversationId;
                                },
                                selectConversation(conversationId) {
                                    const id = String(conversationId || '');
                                    if (! id) return;

                                    window.__rcInboxPendingConversationId = id;
                                    this.selectedConversationId = id;
                                    this.$wire.selectConversation(id).then(() => {
                                        if (window.__rcInboxPendingConversationId === id || this.selectedConversationId === id) {
                                            this.$wire.refreshConversationMessagesForClient(id);
                                        }
                                    });
                                },
                            }"
                            x-init="init()"
                        >
                            @forelse($inboxConversations as $inboxConversation)
                                @php
                                    $inboxConversationId = (string) ($inboxConversation['id'] ?? '');
                                    $inboxContactName = (string) ($inboxConversation['contact_name'] ?? $inboxConversation['name'] ?? 'Coach');
                                    $inboxSchoolLine = (string) ($inboxConversation['school'] ?? $inboxConversation['company_name'] ?? $inboxConversation['email'] ?? 'School unavailable');
                                    $inboxLastMessage = trim(strip_tags((string) ($inboxConversation['last_message'] ?? $inboxConversation['snippet'] ?? 'No preview available.')));
                                    $inboxDate = $formatInboxDate($inboxConversation['last_message_at'] ?? $inboxConversation['updated_at'] ?? $inboxConversation['created_at'] ?? '');
                                    $isSelectedThread = $selectedConversationId === $inboxConversationId;
                                    $unreadCount = (int) ($inboxConversation['unread_count'] ?? 0);
                                    $isStarredThread = (bool) ($inboxConversation['starred'] ?? $inboxConversation['is_starred'] ?? false);
                                    $statusLabel = $unreadCount > 0 ? 'Unread' : ((bool) ($inboxConversation['replied'] ?? $inboxConversation['has_reply'] ?? false) ? 'Replied' : 'Opened');
                                    $logo = $threadLogo($inboxConversation);
                                @endphp
                                <button type="button" class="rc-thread-card-v56" x-bind:class="{ 'is-selected': selectedConversationId === @js($inboxConversationId) }" data-rc-inbox-conversation-trigger x-on:click.stop="selectConversation(@js($inboxConversationId))">
                                    <span class="rc-thread-logo-v56">
                                        @if($logo !== '')
                                            <img src="{{ $logo }}" alt="{{ $inboxSchoolLine }} logo" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.remove();">
                                        @else
                                            {{ $threadInitials($inboxContactName) }}
                                        @endif
                                    </span>
                                    <span style="min-width:0">
                                        <span class="rc-thread-name-v56">{{ $inboxContactName }}</span>
                                        <span class="rc-thread-school-v56">{{ $inboxSchoolLine }}</span>
                                        <span class="rc-thread-preview-v56">{{ $inboxLastMessage }}</span>
                                        <span class="rc-thread-status-v56 {{ $statusLabel === 'Opened' ? 'is-opened' : '' }}">{{ $statusLabel }}</span>
                                    </span>
                                    <span class="rc-thread-card-side-v56">
                                        <span class="rc-thread-date-v56">{{ $inboxDate }}</span>
                                        @if($unreadCount > 0)<span class="rc-thread-unread-dot-v56"></span>@endif
                                        @if($isStarredThread)
                                            <span class="rc-thread-star-v56" title="Starred" aria-label="Starred">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                            </span>
                                        @endif
                                    </span>
                                </button>
                            @empty
                                <div class="rc-inbox-empty-v56"><div><strong>No conversations found.</strong><br><span>Try another search or send a new coach email.</span></div></div>
                            @endforelse
                        </div>
                    </aside>

                    <main class="rc-inbox-mid-v56 rc-inbox-mid-loading-host-v82">
                        <div
                            wire:loading.flex
                            wire:target="selectConversation"
                            class="rc-inbox-inline-conversation-loader-v82"
                            aria-live="polite"
                            aria-label="Loading conversation"
                        >
                            <div class="rc-inbox-inline-loader-head-v82">
                                <span class="rc-inbox-inline-loader-avatar-v82"></span>
                                <span class="rc-inbox-inline-loader-copy-v82">
                                    <span></span>
                                    <span></span>
                                </span>
                            </div>
                            <div class="rc-inbox-inline-loader-message-v82 is-short"></div>
                            <div class="rc-inbox-inline-loader-message-v82"></div>
                            <div class="rc-inbox-inline-loader-message-v82 is-medium"></div>
                        </div>
                        @if($selectedConversation)
                            <div class="rc-inbox-mid-head-v56">
                                <div class="rc-inbox-coach-title-v56">
                                    <span class="rc-inbox-school-logo-v56">
                                        @if($selectedSchoolLogo !== '')
                                            <img src="{{ $selectedSchoolLogo }}" alt="{{ $selectedSchool }} logo" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.remove();">
                                        @else
                                            {{ strtoupper(substr($selectedSchool, 0, 2)) }}
                                        @endif
                                    </span>
                                    <span style="min-width:0">
                                        <h3>{{ $selectedName }}</h3>
                                        <p>{{ $selectedTitle }} • {{ $selectedSchool }}</p>
                                    </span>
                                </div>
                                <div class="rc-inbox-mid-actions-v56">
                                    <button type="button" class="rc-inbox-icon-btn-v56 {{ $selectedStarred ? 'is-starred' : '' }}" wire:click="starSelectedConversation" title="{{ $selectedStarred ? 'Remove from Starred' : 'Star coach' }}" aria-pressed="{{ $selectedStarred ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" width="20" height="20" fill="{{ $selectedStarred ? 'currentColor' : 'none' }}"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></button>
                                    @php $selectedUnread = (int) ($selectedConversation['unread_count'] ?? 0) > 0; @endphp
                                    <button type="button" class="rc-inbox-icon-btn-v56 {{ $selectedUnread ? 'is-unread' : '' }}" wire:click="toggleSelectedConversationUnread" title="{{ $selectedUnread ? 'Mark as read' : 'Mark as unread' }}" aria-pressed="{{ $selectedUnread ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M3.5 6.5h17v12h-17v-12Z" stroke="currentColor" stroke-width="1.7"/><path d="m4.5 7.5 7.5 5.5 7.5-5.5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></button>
                                </div>
                            </div>

                            <div class="rc-message-stream-v56" data-rc-inbox-message-stream>
                                @if(empty($threadMessages))
                                    <div class="rc-inbox-empty-v56">
                                        <div>
                                            <span class="rc-spinner-mini" aria-hidden="true"></span>
                                            <strong>Loading conversation…</strong>
                                        </div>
                                    </div>
                                @else
                                    @php
                                        // GHL may return messages newest-first or oldest-first depending on
                                        // the endpoint/page. Normalize them chronologically, then keep the
                                        // newest 10 so a newly opened conversation always shows the latest emails.
                                        $orderedThreadMessages = collect($threadMessages)
                                            ->sortBy(function ($message) {
                                                $message = is_array($message) ? $message : [];
                                                $value = $message['created_at']
                                                    ?? $message['createdAt']
                                                    ?? $message['date']
                                                    ?? $message['messageDate']
                                                    ?? $message['timestamp']
                                                    ?? $message['updated_at']
                                                    ?? $message['updatedAt']
                                                    ?? 0;

                                                if (is_numeric($value)) {
                                                    $number = (float) $value;
                                                    return $number > 9999999999 ? $number / 1000 : $number;
                                                }

                                                try {
                                                    return \Illuminate\Support\Carbon::parse($value)->getTimestamp();
                                                } catch (\Throwable $exception) {
                                                    return 0;
                                                }
                                            })
                                            ->values();

                                        // The Livewire method is responsible for loading the initial latest
                                        // batch and prepending older batches. Render every message currently
                                        // present instead of trimming back to ten after each request.
                                        $visibleThreadMessages = $orderedThreadMessages;
                                    @endphp
                                    @if((bool) $hasMoreMessages)
                                        <div class="rc-inbox-load-older-top">
                                            <button
                                                class="rc-inbox-open-composer-v56"
                                                type="button"
                                                wire:click="loadOlderConversationMessages"
                                                wire:loading.attr="disabled"
                                                wire:target="loadOlderConversationMessages"
                                            >
                                                <span wire:loading.remove wire:target="loadOlderConversationMessages">Load older emails</span>
                                                <span wire:loading wire:target="loadOlderConversationMessages">Loading 10 older emails…</span>
                                            </button>
                                        </div>
                                    @endif
                                    @foreach($visibleThreadMessages as $message)
                                        @php
                                            $message = is_array($message) ? $message : [];
                                            $normalizedDirection = strtolower(trim((string) (
                                                $message['direction']
                                                ?? $message['message_direction']
                                                ?? $message['messageDirection']
                                                ?? data_get($message, 'meta.email.direction')
                                                ?? data_get($message, 'email.direction')
                                                ?? data_get($message, 'message.direction')
                                                ?? ''
                                            )));
                                            $isOut = str_contains($normalizedDirection, 'out')
                                                || in_array($normalizedDirection, ['sent', 'send', 'outgoing', 'out'], true);
                                            $fromLabel = $isOut ? 'You' : ($message['from_name'] ?? $selectedName);
                                            $toLabel = $message['to'] ?? ($isOut ? $selectedName : 'You');
                                            if (is_array($toLabel)) {
                                                $toLabel = collect($toLabel)->map(fn($item) => is_array($item) ? ($item['email'] ?? $item['name'] ?? $item['address'] ?? '') : (is_scalar($item) ? (string) $item : ''))->filter()->implode(', ');
                                            }
                                            $compressedMessageBody = is_scalar($message['_livewire_body_gzip'] ?? null)
                                                ? (string) $message['_livewire_body_gzip']
                                                : '';
                                            $decodedCompressedBody = '';
                                            if ($compressedMessageBody !== '') {
                                                try {
                                                    $decodedCompressedBody = gzdecode(base64_decode($compressedMessageBody, true) ?: '') ?: '';
                                                } catch (\Throwable $exception) {
                                                    $decodedCompressedBody = '';
                                                }
                                            }
                                            $messageBody = collect([
                                                $decodedCompressedBody,
                                                $message['html_body'] ?? null,
                                                $message['htmlBody'] ?? null,
                                                $message['message_html'] ?? null,
                                                $message['html'] ?? null,
                                                $message['body'] ?? null,
                                                $message['text_body'] ?? null,
                                                $message['textBody'] ?? null,
                                                $message['text'] ?? null,
                                                $message['content'] ?? null,
                                                $message['snippet'] ?? null,
                                                is_scalar($message['message'] ?? null) ? $message['message'] : null,
                                                data_get($message, 'message.html'),
                                                data_get($message, 'message.body'),
                                                data_get($message, 'message.content'),
                                                data_get($message, 'message.text'),
                                                data_get($message, 'emailMessage.html'),
                                                data_get($message, 'emailMessage.body'),
                                                data_get($message, 'emailMessage.content'),
                                                data_get($message, 'email.html'),
                                                data_get($message, 'email.body'),
                                                data_get($message, 'email.content'),
                                                data_get($message, 'meta.email.html'),
                                                data_get($message, 'meta.email.body'),
                                                data_get($message, 'meta.email.content'),
                                                data_get($message, 'payload.html'),
                                                data_get($message, 'payload.body'),
                                                data_get($message, 'payload.content'),
                                                data_get($message, 'payload.text'),
                                            ])->first(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '');
                                            $messageBody = is_scalar($messageBody) ? (string) $messageBody : '';
                                            if (trim($messageBody) === '') {
                                                $messageBody = '<p><em>This email was received, but HighLevel did not include its body in the message payload.</em></p>';
                                            }
                                            $messageDate = $formatMessageDate($message['created_at'] ?? $message['date'] ?? $message['messageDate'] ?? '');
                                            $messageAttachments = collect($message['attachments'] ?? [])->filter(fn($attachment) => is_array($attachment) && filled($attachment['url'] ?? null));
                                        @endphp
                                        <article wire:key="inbox-message-{{ (string) ($message['id'] ?? $loop->index) }}" class="rc-inbox-message-v56 {{ $isOut ? 'is-out' : '' }}">
                                            <span class="rc-msg-avatar-v56">{{ $isOut ? strtoupper(substr($firstName, 0, 1)) : $selectedInitials }}</span>
                                            <div style="min-width:0">
                                                <div class="rc-msg-meta-v56"><span><strong>{{ $fromLabel }}</strong> <span>to {{ $isOut ? $selectedName : 'You' }}</span></span><span>{{ $messageDate }}</span></div>
                                                @php
                                                    $emailDocument = $prepareInboxEmailDocument($messageBody);
                                                @endphp
                                                <div class="rc-msg-bubble-v56 rc-msg-bubble-email-v61">
                                                    <rc-inbox-email-view wire:ignore class="rc-email-document-v64" aria-label="Email message">
                                                        <template>{!! $emailDocument !!}</template>
                                                    </rc-inbox-email-view>
                                                </div>
                                                @if($messageAttachments->isNotEmpty())
                                                    <div class="rc-message-attachments" style="padding:.6rem 0 0;background:transparent">
                                                        @foreach($messageAttachments as $attachment)
                                                            @php
                                                                $attachmentUrl = (string) ($attachment['url'] ?? '');
                                                                $attachmentName = (string) ($attachment['name'] ?? 'Attachment');
                                                                $attachmentType = strtolower((string) ($attachment['mime_type'] ?? $attachment['type'] ?? ''));
                                                                $isImageAttachment = str_starts_with($attachmentType, 'image/') || preg_match('/\.(png|jpe?g|gif|webp|svg)(\?|$)/i', $attachmentUrl);
                                                            @endphp
                                                            @if($isImageAttachment)
                                                                <img class="rc-message-attachment-image" src="{{ $attachmentUrl }}" alt="{{ $attachmentName }}" loading="lazy" decoding="async">
                                                            @else
                                                                <a class="rc-message-attachment-link" href="{{ $attachmentUrl }}" target="_blank" rel="noopener">Open {{ $attachmentName }}</a>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if($isOut)
                                                    @php
                                                        $rawDeliveryStatus = strtolower(trim((string) ($message['status'] ?? '')));
                                                        $deliveryStatus = match (true) {
                                                            str_contains($rawDeliveryStatus, 'click') => 'Clicked',
                                                            str_contains($rawDeliveryStatus, 'open') => 'Opened',
                                                            str_contains($rawDeliveryStatus, 'deliver') => 'Delivered',
                                                            str_contains($rawDeliveryStatus, 'send') || str_contains($rawDeliveryStatus, 'queue') || str_contains($rawDeliveryStatus, 'pending') => 'Sent',
                                                            str_contains($rawDeliveryStatus, 'bounce') => 'Bounced',
                                                            str_contains($rawDeliveryStatus, 'fail') || str_contains($rawDeliveryStatus, 'error') => 'Failed',
                                                            default => $rawDeliveryStatus !== '' ? ucfirst($rawDeliveryStatus) : 'Sent',
                                                        };
                                                        $deliveryTone = in_array($deliveryStatus, ['Failed', 'Bounced'], true) ? 'is-error' : (in_array($deliveryStatus, ['Opened', 'Clicked'], true) ? 'is-opened' : '');
                                                    @endphp
                                                    <div class="rc-message-status-v56 {{ $deliveryTone }}"><span>⊙</span> {{ $deliveryStatus }}</div>
                                                @endif
                                            </div>
                                        </article>
                                    @endforeach
                                @endif

                            </div>

                            <form
                                class="rc-inbox-quick-reply-v92"
                                wire:submit.prevent="sendQuickReply"
                                x-data="{
                                    conversationKey: String(@js((string) ($selectedConversationId ?? 'none'))),
                                    initialBody: @js((string) ($quickReplyBody ?? '')),
                                    lastAppliedServerBody: '',
                                    init() {
                                        window.__rcInboxReplyDrafts = window.__rcInboxReplyDrafts || {};

                                        this.$nextTick(() => {
                                            if (!this.$refs.replyEditor) return;

                                            // Server/template content wins only when it is explicitly present.
                                            // Otherwise preserve the current browser draft for this conversation.
                                            const serverBody = String(this.initialBody || '');
                                            const localBody = String(window.__rcInboxReplyDrafts[this.conversationKey] || '');
                                            const body = serverBody !== '' ? serverBody : localBody;

                                            if (body !== '') {
                                                this.$refs.replyEditor.innerHTML = body;
                                                this.lastAppliedServerBody = serverBody;
                                            }

                                            this.sync(false);
                                        });
                                    },
                                    applyServerBody(value) {
                                        const html = String(value || '');
                                        if (!this.$refs.replyEditor || html === '' || html === this.lastAppliedServerBody) return;

                                        // A template/body change from Livewire should populate the editor once.
                                        // Never re-apply the same server value over a user's subsequent edits.
                                        this.lastAppliedServerBody = html;
                                        this.$refs.replyEditor.innerHTML = html;
                                        window.__rcInboxReplyDrafts[this.conversationKey] = html;
                                        this.sync(false);
                                    },
                                    command(name) {
                                        this.$refs.replyEditor?.focus();
                                        document.execCommand(name, false, null);
                                        this.sync();
                                    },
                                    sync(save = true) {
                                        const html = this.$refs.replyEditor?.innerHTML || '';
                                        if (this.$refs.replyValue) {
                                            this.$refs.replyValue.value = html;
                                            this.$refs.replyValue.dispatchEvent(new Event('input', { bubbles: true }));
                                        }
                                        if (save) {
                                            window.__rcInboxReplyDrafts = window.__rcInboxReplyDrafts || {};
                                            window.__rcInboxReplyDrafts[this.conversationKey] = html;
                                            this.lastAppliedServerBody = html;
                                        }
                                    },
                                    uploadActive: false,
                                    uploadProgress: 0,
                                    uploadFileName: '',
                                    beginUpload(event) {
                                        const files = Array.from(event.target.files || []);
                                        this.uploadFileName = files.length > 1
                                            ? files.length + ' files'
                                            : (files[0]?.name || 'Uploading file');
                                        this.uploadProgress = 0;
                                        this.uploadActive = files.length > 0;
                                    },
                                    finishUpload() {
    this.uploadProgress = 100;

    this.$nextTick(() => {
        this.uploadActive = false;
        this.uploadFileName = '';

        if (this.$refs.quickReplyFileInput) {
            this.$refs.quickReplyFileInput.value = '';
        }
    });
},
                                    clear() {
                                        if (this.$refs.replyEditor) this.$refs.replyEditor.innerHTML = '';
                                        window.__rcInboxReplyDrafts = window.__rcInboxReplyDrafts || {};
                                        delete window.__rcInboxReplyDrafts[this.conversationKey];
                                        this.lastAppliedServerBody = '';
                                        this.sync(false);
                                    }
                                }"
                                x-init="init()"
                                x-effect="applyServerBody($wire.quickReplyBody)"
                                x-on:keydown.ctrl.enter.prevent="sync(); $el.requestSubmit()"
                                x-on:keydown.meta.enter.prevent="sync(); $el.requestSubmit()"
                                x-on:rc-inbox-quick-reply-sent.window="clear()"
                                x-on:rc-quick-reply-attachment-uploaded.window="finishUpload()"
                                x-on:rc-quick-reply-attachment-upload-failed.window="finishUpload()"
                            >
                                <div class="rc-inbox-quick-reply-editor-v92">
                                    <div class="rc-inbox-quick-reply-toolbar-v92" aria-label="Text formatting controls">
                                        <button type="button" title="Bold" x-on:click="command('bold')"><strong>B</strong></button>
                                        <button type="button" title="Italic" x-on:click="command('italic')"><em>I</em></button>
                                        <button type="button" title="Underline" x-on:click="command('underline')"><span style="text-decoration:underline">U</span></button>
                                        <span class="rc-inbox-quick-reply-divider-v92" aria-hidden="true"></span>
                                        <button type="button" title="Bulleted list" x-on:click="command('insertUnorderedList')">•</button>
                                        <button type="button" title="Numbered list" x-on:click="command('insertOrderedList')">1.</button>
                                    </div>

                                    <div wire:ignore>
                                        <div
                                            x-ref="replyEditor"
                                            class="rc-inbox-quick-reply-contenteditable-v93"
                                            contenteditable="true"
                                            role="textbox"
                                            aria-multiline="true"
                                            aria-label="Quick reply message"
                                            data-placeholder="Write your reply…"
                                            x-on:input="sync()"
                                            x-on:blur="sync()"
                                        ></div>
                                    </div>
                                    <textarea
                                        x-ref="replyValue"
                                        wire:model.defer="quickReplyBody"
                                        class="rc-inbox-quick-reply-hidden-v93"
                                        tabindex="-1"
                                        aria-hidden="true"
                                    ></textarea>

                                    <div
    class="rc-inbox-quick-reply-uploading-v96"
    x-show="uploadActive"
    x-cloak
    role="status"
    aria-live="polite"
>
    <span
        class="rc-inbox-quick-reply-upload-spinner-v96"
        aria-hidden="true"
    ></span>

    <span
        class="rc-inbox-quick-reply-upload-name-v96"
        x-text="uploadFileName || 'Uploading file'"
    ></span>

    <span
        class="rc-inbox-quick-reply-upload-percent-v96"
        x-text="uploadProgress >= 100
            ? 'Uploading…'
            : (uploadProgress > 0
                ? uploadProgress + '%'
                : 'Uploading…')"
    ></span>
</div>

@if(! empty($quickReplyAttachments))
    <div
        class="rc-inbox-quick-reply-attachments-v94"
        aria-label="Attached files"
    >
        @foreach($quickReplyAttachments as $attachmentIndex => $attachment)
            @php
                $attachmentUrl = trim(
                    (string) (
                        $attachment['url']
                        ?? $attachment['media_url']
                        ?? ''
                    )
                );

                $attachmentName = trim(
                    (string) ($attachment['name'] ?? 'Attachment')
                ) ?: 'Attachment';

                $attachmentMime = strtolower(
                    (string) ($attachment['mime_type'] ?? '')
                );

                $isImageAttachment =
                    str_starts_with($attachmentMime, 'image/')
                    || preg_match(
                        '/\.(png|jpe?g|gif|webp|svg|bmp|avif)$/i',
                        $attachmentName
                    );

                $attachmentKey = sha1(
                    $attachmentUrl !== ''
                        ? $attachmentUrl
                        : $attachmentName . ':' . $attachmentIndex
                );
            @endphp

            <span
                class="rc-inbox-quick-reply-attachment-chip-v96"
                wire:key="quick-reply-attachment-{{ $attachmentKey }}"
            >
                <span
                    class="rc-inbox-quick-reply-attachment-icon-v96"
                    aria-hidden="true"
                >
                    @if($isImageAttachment)
                        <svg
                            viewBox="0 0 24 24"
                            width="13"
                            height="13"
                            fill="none"
                        >
                            <rect
                                x="3"
                                y="4"
                                width="18"
                                height="16"
                                rx="2"
                                stroke="currentColor"
                                stroke-width="1.7"
                            />
                            <circle
                                cx="8.5"
                                cy="9"
                                r="1.5"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />
                            <path
                                d="m5.5 17 4.2-4 3.1 2.7 2.3-2.2 3.4 3.5"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    @else
                        <svg
                            viewBox="0 0 24 24"
                            width="13"
                            height="13"
                            fill="none"
                        >
                            <path
                                d="M3.5 6.5h6l1.8 2H20.5v9.75a1.75 1.75 0 0 1-1.75 1.75H5.25a1.75 1.75 0 0 1-1.75-1.75V6.5Z"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linejoin="round"
                            />
                            <path
                                d="M3.5 9h17"
                                stroke="currentColor"
                                stroke-width="1.7"
                            />
                        </svg>
                    @endif
                </span>

                <span
                    class="rc-inbox-quick-reply-attachment-name-v96"
                    title="{{ $attachmentName }}"
                >
                    {{ $attachmentName }}
                </span>

                <button
                    type="button"
                    wire:click="removeQuickReplyAttachmentByUrl(@js($attachmentUrl))"
                    wire:loading.attr="disabled"
                    wire:target="removeQuickReplyAttachmentByUrl"
                    aria-label="Remove {{ $attachmentName }}"
                    title="Remove attachment"
                >
                    <span
                        wire:loading.remove
                        wire:target="removeQuickReplyAttachmentByUrl"
                    >
                        ×
                    </span>

                    <span
                        wire:loading
                        wire:target="removeQuickReplyAttachmentByUrl"
                        class="rc-inbox-quick-reply-upload-spinner-v96"
                        aria-hidden="true"
                    ></span>
                </button>
            </span>
        @endforeach
    </div>
@endif

                                    <div class="rc-inbox-quick-reply-footer-v92">
                                        <div class="rc-inbox-quick-reply-tools-v92" aria-label="Reply attachment">
                                            <label
                                                title="Attach file"
                                                aria-label="Attach file"
                                                wire:loading.class="is-uploading"
                                                wire:target="quickReplyAttachmentUploads,addQuickReplyAttachments"
                                            >
                                                <input
                                                    x-ref="quickReplyFileInput"
                                                    type="file"
                                                    multiple
                                                    wire:model="quickReplyAttachmentUploads"
                                                    x-on:change="beginUpload($event)"
                                                    x-on:livewire-upload-start="uploadActive = true"
                                                    x-on:livewire-upload-progress="uploadProgress = $event.detail.progress"
                                                    x-on:livewire-upload-error="finishUpload()"
                                                    wire:loading.attr="disabled"
                                                    wire:target="quickReplyAttachmentUploads,addQuickReplyAttachments"
                                                    hidden
                                                >
                                                <span wire:loading.remove wire:target="quickReplyAttachmentUploads,addQuickReplyAttachments">📎</span>
                                                <span wire:loading wire:target="quickReplyAttachmentUploads,addQuickReplyAttachments" class="rc-inbox-quick-reply-tool-spinner-v95" aria-hidden="true"></span>
                                            </label>
                                        </div>

                                        <div class="rc-inbox-quick-reply-actions-v92">
                                            <button
                                                type="submit"
                                                class="rc-inbox-quick-reply-send-v92"
                                                wire:loading.attr="disabled"
                                                wire:target="sendQuickReply,addQuickReplyAttachments"
                                                x-on:click="sync()"
                                                title="Send reply"
                                            >
                                                <svg wire:loading.remove wire:target="sendQuickReply" viewBox="0 0 24 24" width="14" height="14" fill="none" aria-hidden="true"><path d="m4 4 16 8-16 8 3-8-3-8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 12h13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                                <span wire:loading wire:target="sendQuickReply" class="rc-inbox-quick-reply-spinner-v92" aria-hidden="true"></span>
                                                <span>Send Reply</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @else
                            <div class="rc-inbox-empty-v56"><div><strong>Select a conversation.</strong><br><span>Email messages will appear here.</span></div></div>
                        @endif
                    </main>

                    <aside class="rc-inbox-right-v56">
                        <div class="rc-coach-profile-v56">
                            <div class="rc-coach-cover-v56">
                                @if($selectedSchoolLogo !== '')
                                    <img class="rc-cover-logo-v56" src="{{ $selectedSchoolLogo }}" alt="{{ $selectedSchool }} logo" referrerpolicy="no-referrer" onerror="this.remove();">
                                @endif
                            </div>
                            <div class="rc-profile-content-v56">
                                <div class="rc-profile-avatar-v56">{{ $selectedInitials }}</div>
                                <div class="rc-profile-name-v56"><h3>{{ $selectedName }}</h3><span class="rc-verified-v56">✓</span></div>
                                <div class="rc-profile-sub-v56">{{ $selectedTitle }}<br>{{ $selectedSchool }}</div>

                                <div class="rc-contact-lines-v56">
                                    <div class="rc-contact-line-v56"><svg viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4V6Zm0 0 8 7 8-7" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg><span>{{ data_get($selectedCoach, 'email') ?? $selectedConversation['email'] ?? 'Email unavailable' }}</span></div>
                                    <!-- <div class="rc-contact-line-v56"><svg viewBox="0 0 24 24" fill="none"><path d="M6 2h12v20H6V2Zm5 17h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg><span>{{ data_get($selectedCoach, 'phone') ?? $selectedConversation['phone'] ?? 'Phone unavailable' }}</span></div> -->
                                    <!-- <div class="rc-contact-line-v56"><svg viewBox="0 0 24 24" fill="none"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="10" r="2.3" stroke="currentColor" stroke-width="1.7"/></svg><span>{{ data_get($selectedCoach, 'city') ?: data_get($selectedCoach, 'state') ?: 'Location unavailable' }}</span></div> -->
                                </div>

                                <div class="rc-profile-actions-v56">
                                    <button type="button" class="rc-profile-action-v56" wire:click="viewSelectedConversationSchool" wire:loading.attr="disabled" wire:target="viewSelectedConversationSchool" title="View school">
                                        <svg viewBox="0 0 24 24" width="19" height="19" fill="none"><path d="M4 21V8l8-4 8 4v13M9 21v-7h6v7" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                                        <span>View School</span>
                                    </button>
                                    <button type="button" class="rc-profile-action-v56" wire:click="addSelectedConversationSchoolToList" wire:loading.attr="disabled" wire:target="addSelectedConversationSchoolToList" title="Add school to list">
                                        <svg viewBox="0 0 24 24" width="19" height="19" fill="none"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                        <span>Add to List</span>
                                    </button>
                                    <button type="button" class="rc-profile-action-v56" wire:click="favoriteSelectedConversationSchool" wire:loading.attr="disabled" wire:target="favoriteSelectedConversationSchool" title="Add school to favorites">
                                        <svg viewBox="0 0 24 24" width="19" height="19" fill="none"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                                        <span>Favorite</span>
                                    </button>
                                </div>

                                <div class="rc-section-title" style="margin:1rem 0 .75rem">About School</div>
                                <div class="rc-about-grid-v56">
                                    <div class="rc-about-item-v56"><span><svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M4 21V9l8-5 8 5v12M9 21v-7h6v7" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></span><span><strong>{{ $selectedSchool }}</strong>School</span></div>
                                    <div class="rc-about-item-v56"><span><svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 2a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 21a6 6 0 0 1 12 0M13 18a5 5 0 0 1 9 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span><span><strong>{{ data_get($selectedCoach, 'conference') ?? data_get($selectedCoach, 'league') ?? $selectedConversation['conference'] ?? '—' }}</strong>Conference</span></div>
                                    <div class="rc-about-item-v56"><span><svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Zm10 2h3a3 3 0 0 1-3 3M7 6H4a3 3 0 0 0 3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><strong>{{ data_get($selectedCoach, 'division') ?? $selectedConversation['division'] ?? '—' }}</strong>Division</span></div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        @endif

        @if($section === 'support')
            @php
                $supportUser = auth()->user();
                $supportFirstName = trim((string) (
                    $supportUser?->first_name
                    ?? str($supportUser?->name ?? 'Athlete')->before(' ')
                    ?? 'Athlete'
                ));
            @endphp

            <div class="rc-support-page-v1">
                @include('filament.partials.coach-database-header', [
                    'firstName' => $supportFirstName !== '' ? $supportFirstName : 'Athlete',
                    'placeholder' => 'Search schools, coaches, conferences, divisions, lists...',
                    'showNewEmail' => true,
                ])

                <section class="rc-support-card-v1" x-data="{ loaded: false }">
                    <header class="rc-support-head-v1">
                        <div>
                            <span class="rc-support-kicker-v1">PLYRCard Support</span>
                            <h2>How can we help?</h2>
                            <p>Submit a support ticket and our team will review it.</p>
                        </div>
                    </header>

                    <div class="rc-support-frame-wrap-v1">
                        <div class="rc-support-frame-loader-v1" x-show="! loaded" x-transition.opacity>
                            <span class="rc-support-spinner-v1" aria-label="Loading support form"></span>
                        </div>
                        <iframe
                            src="https://systems.plyrcard.com/widget/form/HDaBy0CDwdO7Fw54wi1K"
                            id="inline-HDaBy0CDwdO7Fw54wi1K"
                            data-layout="{'id':'INLINE'}"
                            data-trigger-type="alwaysShow"
                            data-trigger-value=""
                            data-activation-type="alwaysActivated"
                            data-activation-value=""
                            data-deactivation-type="neverDeactivate"
                            data-deactivation-value=""
                            data-form-name="PLYRCard Support Ticket"
                            data-height="760"
                            data-layout-iframe-id="inline-HDaBy0CDwdO7Fw54wi1K"
                            data-form-id="HDaBy0CDwdO7Fw54wi1K"
                            title="PLYRCard support ticket form"
                            class="rc-support-frame-v1"
                            loading="eager"
                            scrolling="no"
                            referrerpolicy="strict-origin-when-cross-origin"
                            x-on:load="loaded = true"
                        ></iframe>
                        <script src="https://link.msgsndr.com/js/form_embed.js" defer></script>
                    </div>
                </section>
            </div>
        @endif

        @if($section === 'schedule')
            @include('filament.partials.coach-database-header', [
                'firstName' => $firstName,
                'placeholder' => 'Search schools, coaches, conferences...',
                'showNewEmail' => false,
            ])

            @php
                $scheduleEvents = collect($this->myScheduleEvents ?? [])->values();
            @endphp

            <div class="rc-schedule-page-v72">
                <div class="rc-schedule-titlebar-v72">
                    <div>
                        <h1>My Schedule</h1>
                        <p class="rc-schedule-sub-v72">Add your games and events. <span class="rc-schedule-live-v72">● Live on {{ parse_url(config('app.url', 'plyrcard.com'), PHP_URL_HOST) ?: 'plyrcard.com' }}</span></p>
                    </div>
                    <button type="button" class="rc-btn rc-btn-primary" wire:click="startAddScheduleEvent" wire:loading.attr="disabled" wire:target="startAddScheduleEvent">+ Add Event</button>
                </div>

                @if($showScheduleForm)
                    <form class="rc-schedule-form-v72" wire:submit.prevent="saveScheduleEvent">
                        <h2 style="margin:0;font-size:1rem;">{{ $editingScheduleId ? 'Edit Event' : 'Add Event' }}</h2>
                        <div class="rc-schedule-grid-v72">
                            <div class="rc-field-v72"><label>Event Type</label><select wire:model="scheduleEventType"><option>Game</option><option>Showcase</option><option>Tournament</option><option>ID Camp</option><option>Training</option><option>Other</option></select></div>
                            <div class="rc-field-v72"><label>Date</label><input type="date" wire:model="scheduleDate"></div>
                            <div class="rc-field-v72"><label>Time</label><input type="time" wire:model="scheduleTime"></div>
                            <div class="rc-field-v72"><label>Opponent / Event Name</label><input type="text" placeholder="e.g. Bethesda SC" wire:model.defer="scheduleOpponent"></div>
                            <div class="rc-field-v72"><label>Location</label><input type="text" placeholder="e.g. Seattle, WA" wire:model.defer="scheduleLocation"></div>
                            <div class="rc-field-v72"><label>Field / Venue</label><input type="text" placeholder="e.g. Starfire Complex - Field 3" wire:model.defer="scheduleVenue"></div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:.65rem;"><button class="rc-btn" type="button" wire:click="cancelScheduleEvent">Cancel</button><button class="rc-btn rc-btn-primary" type="submit" wire:loading.attr="disabled" wire:target="saveScheduleEvent">{{ $editingScheduleId ? 'Save Changes' : 'Add Event' }}</button></div>
                    </form>
                @endif

                <div class="rc-schedule-list-title-v72">Upcoming ({{ $scheduleEvents->count() }})</div>
                <div class="rc-schedule-list-v72">
                    @forelse($scheduleEvents as $event)
                        <article class="rc-schedule-row-v72">
                            <div class="rc-schedule-date-v72"><small>{{ strtoupper((string) ($event['day'] ?? '')) }}</small><strong>{{ $event['date_number'] ?? '—' }}</strong><span>{{ $event['time'] ?? '' }}</span></div>
                            <div>
                                <div><span class="rc-schedule-pill-v72">{{ $event['type'] ?? 'Game' }}</span><strong>vs {{ $event['opponent'] ?? $event['title'] ?? 'Event' }}</strong></div>
                                <div class="rc-schedule-meta-v72"><span><svg class="rc-schedule-icon-v73" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="10" r="2.2" stroke="currentColor" stroke-width="1.7"/></svg>{{ $event['location'] ?: 'Location unavailable' }}</span><span><svg class="rc-schedule-icon-v73" viewBox="0 0 24 24" fill="none"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Zm10 2h3a3 3 0 0 1-3 3M7 6H4a3 3 0 0 0 3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $event['venue'] ?: 'Venue unavailable' }}</span></div>
                            </div>
                            <div class="rc-schedule-actions-v72"><button type="button" class="rc-icon-clean-v72" wire:click="editScheduleEvent({{ (int) $event['id'] }})" aria-label="Edit event"><svg viewBox="0 0 24 24" fill="none"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m13.5 6.5 4 4" stroke="currentColor" stroke-width="1.7"/></svg></button><button type="button" class="rc-icon-clean-v72" wire:click="deleteScheduleEvent({{ (int) $event['id'] }})" wire:confirm="Remove this event?" aria-label="Delete event"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div>
                        </article>
                    @empty
                        <div class="rc-empty" style="padding:1.25rem;">No scheduled games or events yet.</div>
                    @endforelse
                </div>
            </div>
        @endif

        @if($section === 'settings')
            @include('filament.partials.coach-database-header', [
                'firstName' => $firstName,
                'placeholder' => 'Search schools, coaches, conferences...',
                'showNewEmail' => false,
            ])
            <div class="rc-settings-page-v72">
                <div class="rc-schedule-titlebar-v72"><div><h1>Settings</h1><p class="rc-schedule-sub-v72">Control your recruiting notifications and account shortcuts.</p></div></div>

                <div class="rc-settings-card-v72">
                    <div class="rc-settings-head-v72">
                        <div class="rc-settings-icon-v72">🔔</div>
                        <div>
                            <h2 style="margin:0;">Coach Activity Notifications</h2>
                            <p style="margin:.2rem 0 0;color:var(--rc-muted);">Choose exactly which verified coach interactions should send an email to you and your parent/guardian recipients.</p>
                        </div>
                    </div>
                    @foreach([
                        'profile_views' => ['Profile views', 'When a verified coach views your PLYRCARD profile'],
                        'instagram_clicks' => ['Instagram clicks', 'When a verified coach clicks your Instagram link'],
                        'youtube_clicks' => ['YouTube / Highlight clicks', 'When a verified coach clicks your YouTube or highlight link'],
                        'x_clicks' => ['X clicks', 'When a verified coach clicks your X link'],
                    ] as $settingKey => $settingCopy)
                        <div class="rc-setting-row-v72">
                            <div><h3>{{ $settingCopy[0] }}</h3><p>{{ $settingCopy[1] }}</p></div>
                            <button
                                type="button"
                                class="rc-toggle-v72 {{ ($notificationSettings[$settingKey] ?? true) ? 'is-on' : '' }}"
                                wire:click="toggleNotificationSetting('{{ $settingKey }}')"
                                aria-pressed="{{ ($notificationSettings[$settingKey] ?? true) ? 'true' : 'false' }}"
                                aria-label="Toggle {{ $settingCopy[0] }}"
                            ><span></span></button>
                        </div>
                    @endforeach
                </div>

                <div class="rc-settings-card-v72">
                    <div class="rc-settings-head-v72">
                        <div class="rc-settings-icon-v72">⚙️</div>
                        <div>
                            <h2 style="margin:0;">Other Notifications</h2>
                            <p style="margin:.2rem 0 0;color:var(--rc-muted);">Keep your existing Recruiting Center notification preferences.</p>
                        </div>
                    </div>
                    @foreach([
                        'email_opens' => ['Email opens', 'When a coach opens one of your emails'],
                        'coach_replies' => ['Coach replies', 'When a coach replies to your outreach'],
                        'weekly_digest' => ['Weekly digest', 'A Monday summary of your recruiting activity'],
                        'product_news' => ['Product news', 'New PLYRCARD features and tips'],
                    ] as $settingKey => $settingCopy)
                        <div class="rc-setting-row-v72">
                            <div><h3>{{ $settingCopy[0] }}</h3><p>{{ $settingCopy[1] }}</p></div>
                            <button
                                type="button"
                                class="rc-toggle-v72 {{ ($notificationSettings[$settingKey] ?? false) ? 'is-on' : '' }}"
                                wire:click="toggleNotificationSetting('{{ $settingKey }}')"
                                aria-pressed="{{ ($notificationSettings[$settingKey] ?? false) ? 'true' : 'false' }}"
                                aria-label="Toggle {{ $settingCopy[0] }}"
                            ><span></span></button>
                        </div>
                    @endforeach
                </div>

                @php
                    $settingsBillingService = app(\App\Services\BillingProfileService::class);
                    $settingsBilling = $settingsBillingService->get(auth()->user());
                    $settingsPaymentUpdateUrl = $settingsBillingService->paymentMethodUpdateUrl(auth()->user(), $settingsBilling);
                    $settingsBillingConnected = filled($settingsBilling->ghl_contact_id);
                    $settingsBrand = strtoupper((string) ($settingsBilling->payment_brand ?: 'CARD'));
                @endphp

                <div class="rc-settings-card-v72" id="billing-payments">
                    <div class="rc-settings-head-v72">
                        <div class="rc-settings-icon-v72">💳</div>
                        <div>
                            <h2 style="margin:0;">Billing &amp; Payments</h2>
                            <p style="margin:.2rem 0 0;color:var(--rc-muted);">Manage the billing contact, address, subscription, and saved payment method used for your PLYRCARD account.</p>
                        </div>
                    </div>

                    @if(session('success'))
                        <div style="margin:0 0 1rem;padding:.7rem .8rem;border:1px solid rgba(16,185,129,.3);border-radius:.7rem;background:rgba(16,185,129,.08);color:#047857;font-size:.82rem;font-weight:650;">{{ session('success') }}</div>
                    @endif
                    @if($errors->has('locker_room'))
                        <div style="margin:0 0 1rem;padding:.7rem .8rem;border:1px solid rgba(239,68,68,.3);border-radius:.7rem;background:rgba(239,68,68,.08);color:#b91c1c;font-size:.82rem;font-weight:650;">{{ $errors->first('locker_room') }}</div>
                    @endif

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.65rem;margin-bottom:1rem;">
                        <div class="rc-card is-flat"><div class="rc-subtle">Billing account</div><strong>{{ $settingsBillingConnected ? 'Connected' : 'Not connected yet' }}</strong></div>
                        <div class="rc-card is-flat"><div class="rc-subtle">Plan</div><strong>{{ str($settingsBilling->plan_key ?: 'free')->replace('-', ' ')->title() }}</strong></div>
                        <div class="rc-card is-flat"><div class="rc-subtle">Subscription</div><strong>{{ str($settingsBilling->subscription_status ?: 'not available')->replace('_', ' ')->title() }}</strong></div>
                        <div class="rc-card is-flat"><div class="rc-subtle">Payment</div><strong>{{ str($settingsBilling->payment_status ?: 'not available')->replace('_', ' ')->title() }}</strong></div>
                    </div>

                    @php
                        $settingsIsAmplify = auth()->user()?->getRoleNames()?->contains(fn ($role) => strcasecmp(trim((string) $role), 'Amplify') === 0) ?? false;
                    @endphp

                    @if(!$settingsIsAmplify)
                        <div class="rc-row" style="align-items:center;margin-bottom:.8rem;border:1px solid rgba(255,99,56,.22);background:rgba(255,99,56,.055);border-radius:.85rem;padding:.85rem 1rem;">
                            <div><div class="rc-row-title">Amplify</div><p class="rc-subtle" style="margin:.2rem 0 0;">Upgrade without leaving Settings. Payment confirmation updates your account automatically.</p></div>
                            <button class="rc-btn rc-btn-primary" type="button" data-plyrcard-amplify-open>Upgrade to Amplify</button>
                        </div>
                    @endif

                    <div class="rc-row" style="align-items:flex-start;">
                        <div>
                            <div class="rc-row-title">Payment Method</div>
                            @if($settingsBilling->card_last_four)
                                <p class="rc-subtle" style="margin:.25rem 0 0;">{{ $settingsBrand }} ending in {{ $settingsBilling->card_last_four }}{{ $settingsBilling->card_expiration ? ' · Expires '.$settingsBilling->card_expiration : '' }}</p>
                            @else
                                <p class="rc-subtle" style="margin:.25rem 0 0;">No saved payment method is available yet.</p>
                            @endif
                        </div>
                        @if($settingsPaymentUpdateUrl)
                            <a class="rc-btn rc-btn-primary" href="{{ $settingsPaymentUpdateUrl }}">{{ $settingsBilling->card_last_four ? 'Update Card' : 'Add Payment Method' }}</a>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('locker-room.billing.update') }}" style="margin-top:1rem;">
                        @csrf
                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;">
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Billing Name<input class="rc-input" style="width:100%;" name="billing_name" value="{{ old('billing_name', $settingsBilling->billing_name) }}" required></label>
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Billing Email<input class="rc-input" style="width:100%;" type="email" name="billing_email" value="{{ old('billing_email', $settingsBilling->billing_email) }}" required></label>
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Phone<input class="rc-input" style="width:100%;" name="billing_phone" value="{{ old('billing_phone', $settingsBilling->billing_phone) }}"></label>
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Company / Organization<input class="rc-input" style="width:100%;" name="billing_company" value="{{ old('billing_company', $settingsBilling->billing_company) }}"></label>
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;grid-column:1/-1;">Address Line 1<input class="rc-input" style="width:100%;" name="billing_address_1" value="{{ old('billing_address_1', $settingsBilling->billing_address_1) }}" required></label>
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;grid-column:1/-1;">Address Line 2<input class="rc-input" style="width:100%;" name="billing_address_2" value="{{ old('billing_address_2', $settingsBilling->billing_address_2) }}"></label>
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">City<input class="rc-input" style="width:100%;" name="billing_city" value="{{ old('billing_city', $settingsBilling->billing_city) }}" required></label>
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">State / Province<input class="rc-input" style="width:100%;" name="billing_state" value="{{ old('billing_state', $settingsBilling->billing_state) }}" required></label>
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Postal Code<input class="rc-input" style="width:100%;" name="billing_postal_code" value="{{ old('billing_postal_code', $settingsBilling->billing_postal_code) }}" required></label>
                            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Country<input class="rc-input" style="width:100%;" name="billing_country" value="{{ old('billing_country', $settingsBilling->billing_country ?: 'US') }}" required></label>
                        </div>
                        <div style="display:flex;align-items:center;gap:.65rem;flex-wrap:wrap;margin-top:1rem;">
                            <button class="rc-btn rc-btn-primary" type="submit">Save Billing Information</button>
                            @if(!$settingsBillingConnected)
                                <span class="rc-subtle">Saving will automatically connect this billing profile to your PLYRCARD billing account.</span>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- v118: Compose school/coach selection is browser-local; GHL is touched only when sending. --}}
        @if($section === 'compose')
            <script>
                (() => {
                    if (window.__rcComposeLegacyOpenerGuardV82) return;
                    window.__rcComposeLegacyOpenerGuardV82 = true;

                    const hideLegacyComposeOpeners = (root = document) => {
                        root.querySelectorAll?.('[data-rc-opening-overlay], .rc-open-loading-overlay, .rc-compose-opening-overlay, .rc-compose-loading-backdrop').forEach((node) => {
                            const text = String(node.textContent || '').toLowerCase();
                            const type = String(node.getAttribute?.('data-rc-type') || node.getAttribute?.('data-rc-opening-overlay') || '').toLowerCase();
                            if (type.includes('compose') || text.includes('opening the composer') || text.includes('preparing recipient data')) {
                                node.style.setProperty('display', 'none', 'important');
                                node.style.setProperty('visibility', 'hidden', 'important');
                                node.style.setProperty('pointer-events', 'none', 'important');
                            }
                        });
                    };

                    hideLegacyComposeOpeners();
                    const observer = new MutationObserver(() => hideLegacyComposeOpeners());
                    observer.observe(document.documentElement, { childList: true, subtree: true });
                    document.addEventListener('livewire:navigated', () => hideLegacyComposeOpeners());
                })();
            </script>

            @include('filament.partials.coach-database-header', [
                'firstName' => $firstName,
                'showNewEmail' => false,
            ])

            <div class="rc-section-async-banner {{ ($isLoadingTemplates || $isLoadingTemplateDetail) ? 'is-visible' : '' }}">
                Preparing templates and recipient data. You can keep editing while it refreshes.
            </div>

            <style>
                .rc-compose-page-v45 { display:grid; gap:1rem; }
                .rc-compose-titlebar-v45 { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; }
                .rc-compose-titlebar-v45 h1 { margin:0; font-size:1.25rem; line-height:1.15; font-weight:700; letter-spacing:-.025em; }
                .rc-compose-actions-v45 { display:flex; align-items:center; justify-content:flex-end; gap:.55rem; flex-wrap:wrap; }
                .rc-compose-save-v45 { display:inline-flex; align-items:center; gap:.35rem; color:#059669; font-size:.76rem; font-weight:600; }
                .rc-compose-layout-v45 { display:grid; grid-template-columns:minmax(0,1fr); gap:1rem; }
                .rc-compose-card-v45 { border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1rem; box-shadow:0 12px 28px rgba(15,23,42,.06); overflow:hidden; }
                .rc-compose-inner-v45 { padding:1rem; display:grid; gap:1rem; }
                .rc-compose-label-v45 { color:var(--rc-text); font-size:.73rem; font-weight:700; margin-bottom:.45rem; }
                .rc-compose-recipient-bar-v45 { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
                .rc-compose-chip-v45 { display:inline-flex; align-items:center; gap:.45rem; border:1px solid rgba(255,99,56,.12); background:rgba(255,99,56,.10); color:#fb4f32; border-radius:.55rem; padding:.48rem .65rem; font-size:.78rem; font-weight:600; }
                .rc-compose-chip-v45 button { border:0; background:transparent; color:inherit; cursor:pointer; padding:0; line-height:1; }
                .rc-compose-tab-v45 { border:1px solid var(--rc-border); background:var(--rc-surface); color:var(--rc-text); border-radius:.58rem; padding:.48rem .72rem; min-height:2.25rem; font-size:.78rem; font-weight:600; }
                .rc-compose-tab-v45.is-active { border-color:#ff6338; background:rgba(255,99,56,.08); color:#ff6338; }
                .rc-compose-school-search-v45 { max-width:28rem; position:relative; }
                .rc-compose-send-line-v45 { border-radius:.58rem; background:var(--rc-soft); color:var(--rc-muted); font-size:.76rem; padding:.58rem .75rem; }
                .rc-compose-coach-grid-v45 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.42rem; margin-top:.65rem; }
                .rc-compose-coach-pill-v45 { border:1px solid rgba(255,99,56,.55); background:rgba(255,99,56,.08); border-radius:.5rem; padding:.5rem .55rem; display:flex; align-items:center; justify-content:space-between; gap:.6rem; cursor:pointer; color:var(--rc-text); }
                .rc-compose-coach-pill-v45:not(.is-selected) { border-color:var(--rc-border); background:var(--rc-surface); }
                .rc-compose-coach-name-v45 { display:flex; align-items:center; gap:.42rem; min-width:0; font-size:.76rem; font-weight:650; }
                .rc-compose-check-v45 { width:1rem; height:1rem; border-radius:.28rem; border:1px solid var(--rc-border); display:grid; place-items:center; flex:0 0 auto; font-size:.68rem; color:white; }
                .rc-compose-coach-pill-v45.is-selected .rc-compose-check-v45 { background:#ff6338; border-color:#ff6338; }
                .rc-compose-native-check-v89 { width:1rem; height:1rem; margin:0; flex:0 0 auto; accent-color:#ff6338; pointer-events:none; }
                .rc-compose-native-check-v95 { width:1rem; height:1rem; flex:0 0 1rem; display:inline-grid; place-items:center; border:1.5px solid #cbd5e1; border-radius:.22rem; background:#fff; color:#fff; transition:background .08s ease,border-color .08s ease; }
                .rc-compose-native-check-v95.is-checked { background:#ff6338; border-color:#ff6338; }
                .rc-compose-native-check-v95 svg { width:.72rem; height:.72rem; display:block; }
                .rc-compose-coach-title-v45 { color:var(--rc-muted); font-size:.68rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .rc-compose-field-row-v45 { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.55rem; align-items:center; }
                .rc-compose-template-wrap-v45 { position:relative; }
                .rc-compose-template-menu-v45 { position:absolute; z-index:55; right:0; top:calc(100% + .45rem); width:min(23rem,88vw); border:1px solid var(--rc-border); border-radius:.85rem; background:var(--rc-surface); box-shadow:0 18px 45px rgba(15,23,42,.15); padding:.7rem; display:grid; gap:.25rem; }
                .rc-compose-template-menu-v45 button { width:100%; border:0; background:transparent; color:var(--rc-text); border-radius:.55rem; padding:.65rem .7rem; text-align:left; cursor:pointer; }
                .rc-compose-template-menu-v45 button:hover, .rc-compose-template-menu-v45 button.is-active { background:rgba(255,99,56,.12); }
                .rc-compose-template-menu-v45 button:disabled { cursor:wait; opacity:.72; }
                .rc-compose-template-menu-v45 strong { display:block; font-size:.8rem; }
                .rc-compose-template-loading-v83 { flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center; width:1.35rem; height:1.35rem; color:#ff6338; }
                .rc-compose-template-loading-v83 .rc-spinner-mini { width:.9rem; height:.9rem; border-width:2px; }
                .rc-compose-template-menu-v45 span { display:block; color:var(--rc-muted); font-size:.72rem; margin-top:.12rem; }
                .rc-compose-template-preview-v45 { color:var(--rc-muted); font-size:.7rem; margin-top:.2rem; line-height:1.35; }
                .rc-compose-vars-v45 { display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; }
                .rc-compose-var-v45 { border:0; border-radius:.45rem; background:var(--rc-soft); color:var(--rc-text); padding:.38rem .55rem; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.72rem; font-weight:650; }
                .rc-merge-token-v48 { display:inline-block; border-radius:.42rem; background:rgba(255,99,56,.14); color:#ff6338; padding:.12rem .3rem; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.92em; font-weight:750; white-space:nowrap; }
                .dark .rc-merge-token-v48 { background:rgba(255,99,56,.18); color:#ff8a72; }
                .rc-compose-editor-shell-v45 { border:1px solid var(--rc-border); border-radius:.85rem; overflow:hidden; background:var(--rc-surface); }
                .rc-compose-toolbar-v45 { display:flex; flex-wrap:wrap; gap:.32rem; align-items:center; padding:.48rem .55rem; border-bottom:1px solid var(--rc-border); background:var(--rc-soft); }
                .rc-compose-toolbar-v45 .rc-rich-tool { background:transparent; color:var(--rc-text); border-color:transparent; height:1.9rem; min-width:1.9rem; }
                .rc-compose-toolbar-v45 .rc-select { min-height:1.9rem; padding:.28rem .5rem; }
                .rc-compose-editor-shell-v45 .rc-rich-editor { min-height:24rem; border:0; border-radius:0; box-shadow:none; background:var(--rc-surface); color:var(--rc-text); padding:1rem; }
                .rc-compose-editor-foot-v45 { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.6rem .7rem; border-top:1px solid var(--rc-border); color:var(--rc-muted); font-size:.75rem; }
                .rc-compose-icon-row-v45 { display:flex; gap:.65rem; align-items:center; }
                .rc-compose-icon-row-v45 button, .rc-compose-icon-row-v45 label { border:0; background:transparent; padding:0; color:var(--rc-muted); cursor:pointer; display:inline-flex; align-items:center; }
                .rc-attachments-v45 { border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1rem; box-shadow:0 10px 24px rgba(15,23,42,.05); padding:1rem; display:grid; gap:.85rem; }
                .rc-attachment-grid-v45 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; }
                .rc-attachment-card-v45 { border:1px solid var(--rc-border); border-radius:.8rem; padding:.85rem; display:flex; align-items:center; gap:.75rem; min-height:4.6rem; background:var(--rc-surface); }
                .rc-attachment-icon-v45 { width:2.35rem; height:2.35rem; border-radius:.55rem; display:grid; place-items:center; background:#ef4444; color:white; font-size:.68rem; font-weight:800; flex:0 0 auto; }
                .rc-attachment-icon-v45.is-file { background:#3b82f6; }
                .rc-attachment-drop-v45 { border:1px dashed rgba(148,163,184,.55); border-radius:.8rem; display:grid; place-items:center; min-height:4.6rem; color:var(--rc-muted); text-align:center; cursor:pointer; background:var(--rc-soft); }
                .rc-compose-modal-v45 { position:fixed; inset:0; z-index:90; display:grid; place-items:center; padding:1rem; background:rgba(2,6,23,.62); backdrop-filter:blur(5px); }
                @media (max-width: 1100px) { .rc-compose-titlebar-v45 { align-items:flex-start; flex-direction:column; } .rc-attachment-grid-v45 { grid-template-columns:1fr; } .rc-compose-field-row-v45 { grid-template-columns:1fr; } .rc-compose-coach-grid-v45 { grid-template-columns:1fr; } }
            </style>

            <div class="rc-compose-page-v45"
                x-data="{
                    dataset: @js($this->composeClientDataset),
                    schoolQuery: '',
                    coachQuery: '',
                    selectedSchoolId: @js((string) ($campaignSchoolId ?? '')),
                    targetMode: @js((string) ($campaignTargetMode ?? 'school')),
                    headCoachOnly: @js((bool) ($campaignHeadCoachOnly ?? true)),
                    chooserOpen: @js((bool) ($composeChooseCoachesOpen ?? false)),
                    selectedCoachIds: @js(array_values(array_map('strval', $campaignCoachIds ?? []))),
                    coachRevision: 0,
                    init() {
                        const cached = window.__rcComposeRecipientStateV101;
                        const currentPath = String(window.location?.pathname || '');

                        // v101: the browser-side Compose recipient state is authoritative
                        // across Livewire morphs on this same page. The server-rendered
                        // campaignSchoolId may still be empty/older because school/coach
                        // selection is intentionally local-only for instant interaction.
                        if (cached && String(cached.path || '') === currentPath) {
                            const cachedSchoolId = String(cached.schoolId || '');
                            const schoolExists = cachedSchoolId === '' || this.schools.some(row => String(row.id || '') === cachedSchoolId);

                            if (schoolExists) {
                                this.selectedSchoolId = cachedSchoolId;
                                this.selectedCoachIds = Array.isArray(cached.selectedCoachIds) ? [...cached.selectedCoachIds].map(String) : [];
                                this.targetMode = String(cached.targetMode || 'school');
                                this.headCoachOnly = Boolean(cached.headCoachOnly);
                                this.chooserOpen = Boolean(cached.chooserOpen);
                            }
                        }

                        this.rememberRecipientState();
                    },
                    rememberRecipientState() {
                        window.__rcComposeRecipientStateV101 = {
                            path: String(window.location?.pathname || ''),
                            schoolId: String(this.selectedSchoolId || ''),
                            selectedCoachIds: [...this.selectedCoachIds].map(String),
                            targetMode: String(this.targetMode || 'school'),
                            headCoachOnly: Boolean(this.headCoachOnly),
                            chooserOpen: Boolean(this.chooserOpen),
                        };
                    },
                    previewStaticTokens: @js($this->composePreviewTokenValues),
                    previewSignatureHtml: @js($this->composePreviewSignatureHtml),
                    previewCoach() {
                        const coaches = this.schoolCoaches;
                        if (this.selectedSchool && coaches.length) {
                            if (this.targetMode === 'coaches') {
                                const selected = coaches.find(row => this.selectedCoachIds.includes(String(row.id || '')));
                                if (selected) return selected;
                            } else if (this.headCoachOnly) {
                                return coaches.find(row => Boolean(row.is_head)) || coaches[0];
                            } else {
                                return coaches[0];
                            }
                        }
                        return {
                            name: 'Coach Name',
                            first_name: 'Coach Name',
                            last_name: '',
                            title: 'Coach',
                            email: '',
                            school: this.selectedSchool?.name || 'School Name',
                        };
                    },
                    previewTokenMap(coach) {
                        const name = String(coach?.name || 'Coach Name').trim() || 'Coach Name';
                        const parts = name.split(/\s+/).filter(Boolean);
                        const firstName = String(coach?.first_name || '').trim() || (name === 'Coach Name' ? 'Coach Name' : (parts[0] || 'Coach Name'));
                        const lastName = String(coach?.last_name || '').trim() || (name === 'Coach Name' ? '' : (parts.slice(1).join(' ') || ''));
                        return {
                            ...this.previewStaticTokens,
                            CoachName: name,
                            CoachFirstName: firstName,
                            CoachLastName: lastName,
                            CoachTitle: String(coach?.title || 'Coach'),
                            CoachEmail: String(coach?.email || ''),
                            SchoolName: String(this.selectedSchool?.name || coach?.school || 'School Name'),
                        };
                    },
                    renderPreviewTokens(value, coach) {
                        let output = String(value || '');
                        const values = this.previewTokenMap(coach);
                        Object.entries(values).forEach(([token, replacement]) => {
                            const escaped = String(token).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                            output = output.replace(new RegExp('\\{\\{\\s*' + escaped + '\\s*\\}\\}', 'gi'), String(replacement ?? ''));
                        });

                        // Preview should look like the actual email, not the editor.
                        // Merge/custom values are highlighted inside the contenteditable
                        // with rc-merge-token-v48, so unwrap those editor-only spans after
                        // token replacement and keep only their plain text value.
                        if (output.includes('rc-merge-token-v48')) {
                            const template = document.createElement('template');
                            template.innerHTML = output;
                            template.content.querySelectorAll('.rc-merge-token-v48').forEach((node) => {
                                node.replaceWith(document.createTextNode(node.textContent || ''));
                            });
                            output = template.innerHTML;
                        }

                        return output;
                    },
                    openPreview() {
                        const coach = this.previewCoach();
                        const subjectInput = this.$root.querySelector('[data-rc-compose-subject]');
                        const editor = this.$root.querySelector('[data-plyr-native-editor=campaign-body]');
                        const subjectRaw = String(subjectInput?.value || 'Subject preview');
                        const bodyRaw = String(editor?.innerHTML || '').trim() || 'Choose a template or write your message.';
                        const subject = this.renderPreviewTokens(subjectRaw, coach);
                        const body = this.renderPreviewTokens(bodyRaw, coach);
                        const signature = this.renderPreviewTokens(this.previewSignatureHtml || '', coach);
                        window.dispatchEvent(new CustomEvent('rc-compose-preview-open', {
                            detail: {
                                subject,
                                body: body + (signature ? '\n' + signature : ''),
                            },
                        }));
                    },
                    sendingFast: false,
                    get schools() { return Array.isArray(this.dataset?.schools) ? this.dataset.schools : []; },
                    get schoolResults() {
                        const q = String(this.schoolQuery || '').trim().toLowerCase();
                        if (!q) return [];
                        return this.schools.filter(row => String(row.search_text || '').includes(q)).slice(0, 15);
                    },
                    get selectedSchool() {
                        return this.schools.find(row => String(row.id) === String(this.selectedSchoolId)) || null;
                    },
                    get schoolCoaches() {
                        return Array.isArray(this.selectedSchool?.coaches) ? this.selectedSchool.coaches : [];
                    },
                    get selectedSchoolCoachCount() {
                        if (!this.selectedSchool) return 0;
                        return this.schoolCoaches.length || Number(this.selectedSchool?.coach_count || 0);
                    },
                    get visibleCoaches() {
                        const q = String(this.coachQuery || '').trim().toLowerCase();
                        if (!q) return this.schoolCoaches;
                        return this.schoolCoaches.filter(row => String(row.search_text || '').includes(q));
                    },
                    get activeCoachIds() {
                        if (!this.selectedSchool) return [];
                        if (this.targetMode === 'coaches') return [...this.selectedCoachIds];
                        if (this.headCoachOnly) {
                            const head = this.schoolCoaches.find(row => row.is_head) || this.schoolCoaches[0];
                            return head ? [String(head.id)] : [];
                        }
                        return this.schoolCoaches.map(row => String(row.id));
                    },
                    get recipientCount() { this.coachRevision; return this.activeCoachIds.length; },
                    get sendingDescription() {
                        if (!this.selectedSchool) return 'No school selected — search to add one below';
                        if (this.targetMode === 'coaches') return `Sending to ${this.recipientCount.toLocaleString()} selected coach${this.recipientCount === 1 ? '' : 'es'} at ${this.selectedSchool.name}`;
                        return `Sending to ${this.headCoachOnly ? 'head coach only' : 'all coaches'} at ${this.selectedSchool.name}`;
                    },
                    coachSelected(id) {
                        return this.selectedCoachIds.includes(String(id || ''));
                    },
                    syncRecipientStateDeferred() {
                        // v99: local-only recipient state. Do not start a Livewire request
                        // for school/coach selection; sendFast passes the exact state explicitly.
                        this.rememberRecipientState();
                    },
                    toggleCoach(id) {
                        id = String(id || '');
                        if (!id) return;
                        this.targetMode = 'coaches';
                        this.headCoachOnly = false;
                        this.chooserOpen = true;
                        this.selectedCoachIds = this.selectedCoachIds.includes(id)
                            ? this.selectedCoachIds.filter(value => value !== id)
                            : [...this.selectedCoachIds, id];
                        this.coachRevision++;
                        this.rememberRecipientState();
                    },
                    selectAllCoaches() {
                        if (!this.selectedSchool) return;
                        this.targetMode = 'coaches';
                        this.headCoachOnly = false;
                        this.chooserOpen = true;
                        this.selectedCoachIds = this.schoolCoaches
                            .map(row => String(row.id || ''))
                            .filter(Boolean);
                        this.coachRevision++;
                        this.rememberRecipientState();
                    },
                    clearCoaches() {
                        this.targetMode = 'coaches';
                        this.headCoachOnly = false;
                        this.chooserOpen = true;
                        this.selectedCoachIds = [];
                        this.coachRevision++;
                        this.rememberRecipientState();
                    },
                    clearRecipients() {
                        this.selectedSchoolId = '';
                        this.schoolQuery = '';
                        this.coachQuery = '';
                        this.selectedCoachIds = [];
                        this.targetMode = 'school';
                        this.headCoachOnly = true;
                        this.chooserOpen = false;
                        this.coachRevision++;
                        this.rememberRecipientState();
                    },
                    chooseSchool(school) {
                        const id = String(school?.id || '');
                        if (!id) return;
                        this.selectedSchoolId = id;
                        this.schoolQuery = '';
                        this.coachQuery = '';
                        this.selectedCoachIds = [];
                        this.targetMode = 'school';
                        this.headCoachOnly = true;
                        this.chooserOpen = false;
                        this.coachRevision++;
                        this.rememberRecipientState();
                    },
                    chooseHeadCoach() {
                        if (!this.selectedSchool) return;
                        this.targetMode = 'school';
                        this.headCoachOnly = true;
                        this.chooserOpen = false;
                        this.coachRevision++;
                        this.rememberRecipientState();
                    },
                    chooseAllCoaches() {
                        if (!this.selectedSchool) return;
                        this.targetMode = 'school';
                        this.headCoachOnly = false;
                        this.chooserOpen = false;
                        this.coachRevision++;
                        this.rememberRecipientState();
                    },
                    chooseSpecificCoaches() {
                        if (!this.selectedSchool) return;
                        this.targetMode = 'coaches';
                        this.headCoachOnly = false;
                        this.chooserOpen = true;
                        if (!this.selectedCoachIds.length) {
                            this.selectedCoachIds = this.schoolCoaches
                                .map(row => String(row.id || ''))
                                .filter(Boolean);
                        }
                        this.coachRevision++;
                        this.rememberRecipientState();
                    },
                    async sendFast() {
                        if (this.sendingFast) return;
                        if (!this.selectedSchool || this.recipientCount < 1) { toast('Choose at least one coach.'); return; }
                        this.sendingFast = true;
                        try {
                            await this.$wire.call('sendComposedEmailWithComposeState', this.selectedSchoolId, this.targetMode, this.headCoachOnly, [...this.selectedCoachIds]);
                        } finally { this.sendingFast = false; }
                    },
                }" x-init="init()">
                <div class="rc-compose-titlebar-v45">
                    <div>
                        <h1>Compose Email</h1>
                        <div class="rc-subtle" style="margin-top:.35rem">Create a personalized email to coaches.</div>
                    </div>
                    <div class="rc-compose-actions-v45">
                        <span class="rc-compose-save-v45">
                            <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            Saved just now
                        </span>
                        <button class="rc-btn" type="button" data-rc-local-action data-rc-compose-preview-instant-v99 x-on:click.prevent.stop="openPreview()">
                            <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            Preview
                        </button>
                        <button class="rc-btn" type="button" wire:click="openSaveComposeTemplatePrompt" wire:loading.attr="disabled" wire:target="openSaveComposeTemplatePrompt">
                            <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" /></svg>
                            <span wire:loading.remove wire:target="openSaveComposeTemplatePrompt">Save as Template</span>
                            <span wire:loading.flex wire:target="openSaveComposeTemplatePrompt" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Opening</span>
                        </button>
                        <button class="rc-btn rc-btn-primary" type="button" x-on:click="sendFast()" x-bind:disabled="sendingFast">
                            <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12 3.269 3.125A59.77 59.77 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.875L6 12Zm0 0h7.5" /></svg>
                            <span x-show="!sendingFast" x-text="recipientCount > 0 ? `Send to ${recipientCount.toLocaleString()} coach${recipientCount === 1 ? '' : 'es'}` : 'Add a school'"></span>
                            <span x-cloak x-show="sendingFast" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Sending</span>
                        </button>
                    </div>
                </div>

                <div class="rc-compose-layout-v45">
                    <div class="rc-compose-card-v45">
                        <div class="rc-compose-inner-v45">
                            <div>
                                <div class="rc-compose-label-v45">Recipients</div>
                                <div class="rc-compose-recipient-bar-v45">
                                    <template x-if="selectedSchool">
                                        <span class="rc-compose-chip-v45">
                                            <span x-text="`${selectedSchool.name} (${selectedSchoolCoachCount.toLocaleString()} coaches)`"></span>
                                            <button type="button" x-on:click="clearRecipients()">×</button>
                                        </span>
                                    </template>
                                    <template x-if="!selectedSchool"><em class="rc-subtle">No school selected — search to add one below</em></template>

                                    <button type="button" class="rc-compose-tab-v45" x-bind:class="{'is-active':selectedSchool && targetMode==='school' && headCoachOnly}" x-on:click="chooseHeadCoach()">Head Coach Only</button>
                                    <button type="button" class="rc-compose-tab-v45" x-bind:class="{'is-active':selectedSchool && targetMode==='school' && !headCoachOnly}" x-on:click="chooseAllCoaches()">All Coaches</button>
                                    <button type="button" class="rc-compose-tab-v45" x-bind:class="{'is-active':selectedSchool && targetMode==='coaches'}" x-on:click="chooseSpecificCoaches()">Choose Coaches</button>
                                    <button type="button" class="rc-compose-tab-v45 {{ $composeShowCcBcc ? 'is-active' : '' }}" wire:click="$toggle('composeShowCcBcc')">CC / BCC</button>
                                </div>

                                <div class="rc-compose-school-search-v45" style="margin-top:.65rem;position:relative;max-width:34rem">
                                    <div class="rc-global-search-shell" style="width:100%;height:2.85rem;box-shadow:none">
                                        <svg class="rc-global-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" /></svg>
                                        <input class="rc-global-search-input" style="font-size:.88rem" placeholder="Search for a school..." x-model="schoolQuery" />
                                        <button x-cloak x-show="schoolQuery.length" type="button" class="rc-global-search-clear" x-on:click="schoolQuery=''" aria-label="Clear school search">×</button>
                                    </div>
                                    <div x-cloak x-show="schoolQuery.length" class="rc-global-suggestions" style="z-index:95;min-width:100%;max-height:18rem">
                                        <template x-for="school in schoolResults" :key="school.id">
                                            <button type="button" class="rc-global-suggestion-item" x-on:pointerdown.prevent.stop x-on:click.prevent.stop="chooseSchool(school)">
                                                <span class="rc-global-suggestion-icon">
                                                    <template x-if="school.logo_url"><img :src="school.logo_url" alt="" referrerpolicy="no-referrer"></template>
                                                    <template x-if="!school.logo_url"><span x-text="String(school.name).split(/\s+/).slice(0,2).map(v=>v[0]).join('').toUpperCase()"></span></template>
                                                </span>
                                                <span class="rc-global-suggestion-copy"><strong x-text="school.name"></strong><small><span x-text="[school.conference,school.division].filter(Boolean).join(' • ') || 'Conference unavailable'"></span> · <span x-text="Number(school.coach_count||0).toLocaleString()"></span> coaches</small></span>
                                                <span class="rc-global-suggestion-category">School</span>
                                            </button>
                                        </template>
                                        <div x-show="schoolResults.length===0" class="rc-empty-state" style="padding:.8rem">No schools found.</div>
                                    </div>
                                </div>

                                @if($composeShowCcBcc)
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.55rem;margin-top:.65rem;max-width:42rem">
                                        <input class="rc-input" placeholder="CC emails, comma separated" wire:model.blur="campaignCc" />
                                        <input class="rc-input" placeholder="BCC emails, comma separated" wire:model.blur="campaignBcc" />
                                    </div>
                                @endif

                                <div x-cloak x-show="selectedSchool && chooserOpen" style="margin-top:.65rem">
                                    <input class="rc-input" style="width:100%;max-width:28rem" placeholder="Filter coaches..." x-model="coachQuery" />
                                    <div class="rc-compose-coach-grid-v45">
                                        <template x-for="coach in visibleCoaches" :key="coach.id">
                                            <button type="button" class="rc-compose-coach-pill-v45" x-bind:class="{ 'is-selected': selectedCoachIds.includes(String(coach.id || '')) }" x-on:click.prevent="toggleCoach(coach.id)">
                                                <span class="rc-compose-coach-name-v45">
                                                    <span class="rc-compose-native-check-v95" x-bind:class="{ 'is-checked': selectedCoachIds.includes(String(coach.id || '')) }" aria-hidden="true"><svg x-show="selectedCoachIds.includes(String(coach.id || ''))" viewBox="0 0 20 20" fill="none"><path d="m4.5 10 3.3 3.3 7.7-7.7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                                    <span x-text="coach.name"></span>
                                                    <span x-show="coach.is_head" style="color:#ff6338;font-size:.62rem;font-weight:800">HC</span>
                                                </span>
                                                <span class="rc-compose-coach-title-v45" x-text="coach.title"></span>
                                            </button>
                                        </template>
                                    </div>
                                    <div style="display:flex;gap:.45rem;margin-top:.55rem">
                                        <button type="button" class="rc-btn" x-on:click="selectAllCoaches()">Select all</button>
                                        <button type="button" class="rc-btn" x-on:click="clearCoaches()">Clear coaches</button>
                                    </div>
                                </div>

                                <div class="rc-compose-send-line-v45" style="margin-top:.7rem" x-text="sendingDescription"></div>
                            </div>

                            <div>
                                <div class="rc-compose-label-v45">Subject Line</div>
                                <div class="rc-compose-field-row-v45">
                                    <input class="rc-input" style="width:100%" placeholder="Subject line" data-rc-compose-subject wire:model="campaignSubject" />
                                    <div class="rc-compose-template-wrap-v45" x-data="{ open:false, loadingTemplateId:'' }">
                                        <button class="rc-btn" type="button" x-on:click="open=!open">
                                            <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /></svg>
                                            Templates
                                        </button>
                                        <div x-cloak x-show="open" x-on:click.outside="open=false" class="rc-compose-template-menu-v45">
                                            <div class="rc-subtle" style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;padding:.25rem .4rem">Choose a template</div>
                                            @forelse($this->composeTemplateOptions as $template)
                                                <button
                                                    type="button"
                                                    class="{{ (string) ($campaignTemplateId ?? '') === (string) ($template['id'] ?? '') ? 'is-active' : '' }}"
                                                    data-rc-local-action
                                                    x-bind:disabled="loadingTemplateId !== ''"
                                                    x-on:click.prevent.stop="
                                                        const id = @js((string) ($template['id'] ?? ''));
                                                        if (!id || loadingTemplateId) return;
                                                        loadingTemplateId = id;
                                                        open = false;
                                                        $wire.call('useTemplateForCompose', id)
                                                            .catch((error) => console.error(error))
                                                            .finally(() => { loadingTemplateId = ''; });
                                                    "
                                                >
                                                    <span style="display:flex;align-items:flex-start;justify-content:space-between;gap:.65rem;">
                                                        <span style="min-width:0;display:block;">
                                                            <strong>{{ $template['name'] ?? 'Untitled Template' }}</strong>
                                                            <span>{{ $template['compose_subject_preview'] ?? 'Recruiting email' }}</span>
                                                        </span>
                                                        <span x-show="loadingTemplateId === @js((string) ($template['id'] ?? ''))" x-cloak class="rc-compose-template-loading-v83"><span class="rc-spinner-mini"></span></span>
                                                    </span>
                                                    <div class="rc-compose-template-preview-v45">{{ $template['compose_body_preview'] ?? 'Personalized message preview' }}</div>
                                                </button>
                                            @empty
                                                <div class="rc-subtle" style="padding:.5rem">No templates found.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="rc-compose-label-v45">Insert Variable</div>
                                <div class="rc-compose-vars-v45">
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'CoachFirstName'})">@{{CoachFirstName}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'CoachLastName'})">@{{CoachLastName}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'SchoolName'})">@{{SchoolName}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'CoachTitle'})">@{{CoachTitle}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'AthleteName'})">@{{AthleteName}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'ProfileLink'})">@{{ProfileLink}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'HighlightLink'})">@{{HighlightLink}}</button>
                                </div>
                            </div>

                            <div x-data="plyrNativeEditorBase('campaignBody')" x-init="mount()" x-on:plyr-editor-insert-token.window="insertMerge($event.detail.token)" wire:key="compose-email-editor-v45-{{ $campaignTemplateId ?: 'blank' }}">
                                <div class="rc-compose-editor-shell-v45">
                                    <div class="rc-compose-toolbar-v45">
                                        <select class="rc-select" x-on:change="formatBlock($event.target.value); $event.target.value='p'">
                                            <option value="p">Paragraph</option>
                                            <option value="h2">Heading</option>
                                            <option value="h3">Subheading</option>
                                        </select>
                                        <button type="button" class="rc-rich-tool" x-on:click="format('bold')"><strong>B</strong></button>
                                        <button type="button" class="rc-rich-tool" x-on:click="format('italic')"><em>I</em></button>
                                        <button type="button" class="rc-rich-tool" x-on:click="format('underline')"><u>U</u></button>
                                        <button type="button" class="rc-rich-tool" x-on:click="format('insertUnorderedList')">☷</button>
                                        <button type="button" class="rc-rich-tool" x-on:click="format('insertOrderedList')">☑</button>
                                        <button type="button" class="rc-rich-tool" x-on:click="openLinkPanel()">🔗</button>
                                        <button type="button" class="rc-rich-tool" x-on:click="$refs.imageUpload.click()">▧</button>
                                    </div>
                                    <input x-ref="imageUpload" type="file" accept="image/*" multiple style="display:none" x-on:change="uploadInlineImages($event)">
                                    <div
                                        x-ref="editor"
                                        wire:ignore
                                        class="rc-rich-editor rc-native-editor"
                                        contenteditable="true"
                                        data-plyr-native-editor="campaign-body"
                                        data-placeholder="Write your message..."
                                        data-initial-body="{{ base64_encode($campaignBody ?? '') }}"
                                        x-on:input="queueSync()"
                                        x-on:blur="syncNow()"
                                    ></div>
                                    <input x-ref="campaignBodyHidden" type="hidden" data-plyr-native-editor-hidden="campaign-body" wire:model="campaignBody" />
                                    <div class="rc-compose-editor-foot-v45">
                                        <div class="rc-compose-icon-row-v45">
                                            <button type="button" title="Clear" wire:click="clearComposeTemplate">🗑</button>
                                            <button type="button" title="Image" x-on:click="$refs.imageUpload.click()">▧</button>
                                            <button type="button" title="Link" x-on:click="openLinkPanel()">🔗</button>
                                            <label title="Attach files">
                                                <input type="file" multiple style="display:none" wire:model="composeAttachmentUploads" />
                                                📎
                                            </label>
                                            <button type="button" title="Email">✉</button>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:.85rem">
                                            <span>Words: {{ str_word_count(strip_tags($campaignBody)) }}</span>
                                            <span style="color:#10b981;font-weight:650">Looks good!</span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    x-cloak
                                    x-show="activePanel"
                                    class="rc-compose-modal-v45"
                                    x-on:keydown.escape.window="closeEditorPanel()"
                                    x-on:click.self="closeEditorPanel()"
                                >
                                    <div style="width:min(26rem,94vw);border:1px solid var(--rc-border);border-radius:1rem;background:var(--rc-surface);box-shadow:0 24px 80px rgba(0,0,0,.30);overflow:hidden;">
                                        <div style="display:flex;align-items:center;justify-content:space-between;padding:.85rem 1rem;border-bottom:1px solid var(--rc-border)"><strong x-text="activePanel === 'button' ? 'Insert button' : 'Insert link'"></strong><button type="button" class="rc-icon-button" x-on:click="closeEditorPanel()">×</button></div>
                                        <div x-show="activePanel === 'link'" style="display:grid;gap:.65rem;padding:1rem">
                                            <input class="rc-input" style="width:100%" placeholder="Link text" x-model="panelLinkLabel">
                                            <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelLinkUrl">
                                            <div class="rc-toolbar" style="justify-content:flex-end"><button type="button" class="rc-btn" x-on:click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" x-on:click="applyLinkPanel()">Insert link</button></div>
                                        </div>
                                        <div x-show="activePanel === 'button'" style="display:grid;gap:.65rem;padding:1rem">
                                            <input class="rc-input" style="width:100%" placeholder="Button text" x-model="panelButtonLabel">
                                            <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelButtonUrl">
                                            <div class="rc-toolbar" style="justify-content:flex-end"><button type="button" class="rc-btn" x-on:click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" x-on:click="applyButtonPanel()">Insert button</button></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rc-attachments-v45">
                        <div style="font-weight:700">Attachments ({{ count($composeAttachments) }})</div>
                        <div class="rc-attachment-grid-v45">
                            @foreach($composeAttachments as $index => $attachment)
                                <?php $name = (string) ($attachment['name'] ?? 'Attachment'); $ext = strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE'); ?>
                                <div class="rc-attachment-card-v45">
                                    <div class="rc-attachment-icon-v45 {{ $ext === 'PDF' ? '' : 'is-file' }}">{{ \Illuminate\Support\Str::limit($ext, 4, '') }}</div>
                                    <div style="min-width:0;flex:1">
                                        <div style="font-size:.8rem;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $name }}</div>
                                        <div class="rc-subtle">{{ $attachment['mime_type'] ?? 'File' }} @if(!empty($attachment['size'])) · {{ number_format(((int) $attachment['size']) / 1048576, 1) }} MB @endif</div>
                                    </div>
                                    <button type="button" class="rc-icon-button" wire:click="removeComposeAttachment({{ $index }})">×</button>
                                </div>
                            @endforeach
                            <label class="rc-attachment-drop-v45">
                                <input type="file" multiple style="display:none" wire:model="composeAttachmentUploads" />
                                <span>
                                    <svg class="rc-icon-sm" style="margin:0 auto .3rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1M12 4v12m0-12 4 4m-4-4-4 4" /></svg>
                                    <strong style="display:block;color:var(--rc-text);font-size:.82rem">Drag & drop files here</strong>
                                    <span style="font-size:.72rem">or click to browse · Max 25MB per file</span>
                                </span>
                            </label>
                        </div>
                        <div wire:loading.flex wire:target="composeAttachmentUploads,addComposeAttachments" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Uploading files</div>
                    </div>
                </div>
            </div>

            @teleport('body')
            <div
                x-data="{ open: false, subject: '', body: '' }"
                x-cloak
                x-show="open"
                class="rc-compose-modal-v45 rc-compose-preview-backdrop-v82"
                x-on:rc-compose-preview-open.window="subject = String($event.detail?.subject || 'Subject preview'); body = String($event.detail?.body || ''); open = true"
                x-on:click.self="open = false"
                x-on:keydown.escape.window="open = false"
            >
                <div style="width:min(56rem,94vw);max-height:86vh;overflow:auto;border:1px solid var(--rc-border);border-radius:1rem;background:var(--rc-surface);box-shadow:0 24px 80px rgba(0,0,0,.30);">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--rc-border)">
                        <div><strong>Preview Email</strong><div class="rc-subtle" x-text="subject"></div></div>
                        <button type="button" class="rc-icon-button" data-rc-local-action x-on:click.prevent.stop="open = false">×</button>
                    </div>
                    <div style="padding:1rem;background:var(--rc-soft)">
                        <div style="background:var(--rc-surface);border:1px solid var(--rc-border);border-radius:.85rem;padding:1.25rem;line-height:1.6">
                            <div x-html="body"></div>
                        </div>
                    </div>
                </div>
            </div>
            @endteleport
        @endif

        @if($section === 'campaigns')
            @include('filament.partials.coach-database-header')

            <div class="rc-section-async-banner {{ ($isLoadingTemplates || $isLoadingTemplateDetail) ? 'is-visible' : '' }}">
                Refreshing templates. Cached and built-in templates remain available.
            </div>

            @php
                $templateQuery = strtolower(trim((string) ($templateSearch ?? '')));
                $templateRows = collect($templates ?? [])
                    ->filter(fn ($template): bool => is_array($template))
                    ->filter(function (array $template) use ($templateQuery): bool {
                        if ($templateQuery === '') {
                            return true;
                        }

                        return str_contains(strtolower((string) ($template['name'] ?? '')), $templateQuery)
                            || str_contains(strtolower((string) ($template['subjectLine'] ?? $template['subject'] ?? '')), $templateQuery)
                            || str_contains(strtolower((string) ($template['previewText'] ?? $template['description'] ?? '')), $templateQuery);
                    })
                    ->values();
                $templateCount = $templateRows->count();
            @endphp

            <style>
                .rc-templates-page-v50{display:grid;gap:1.1rem;margin-top:1.1rem}
                .rc-templates-head-v50{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem}
                .rc-templates-title-v50{display:flex;align-items:center;gap:.72rem;min-width:0}
                .rc-templates-title-v50 h2{margin:0;color:var(--rc-text);font-size:1.42rem;line-height:1.15;font-weight:760;letter-spacing:-.025em}
                .rc-templates-title-v50 p{margin:.2rem 0 0;color:var(--rc-muted);font-size:.88rem;line-height:1.35}
                .rc-template-back-v50{width:2.35rem;height:2.35rem;border-radius:.72rem;border:1px solid var(--rc-border);background:var(--rc-surface);color:var(--rc-text);display:grid;place-items:center;cursor:pointer;box-shadow:0 10px 24px rgba(15,23,42,.05)}
                .rc-templates-actions-v50{display:flex;align-items:center;justify-content:flex-end;gap:.55rem;flex-wrap:wrap}
                .rc-template-list-top-v50{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:.15rem}
                .rc-template-search-v50{height:2.75rem;border:1px solid var(--rc-border);background:var(--rc-surface);border-radius:.9rem;padding:0 .95rem;display:flex;align-items:center;gap:.55rem;min-width:min(34rem,100%);box-shadow:0 10px 24px rgba(15,23,42,.045)}
                .rc-template-search-v50 input{border:0;outline:0;background:transparent;width:100%;font-size:.9rem;color:var(--rc-text)}
                .rc-template-grid-v50{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.05rem;margin-top:.55rem}
                .rc-template-card-v50{border:1px solid var(--rc-border);border-radius:1.05rem;background:var(--rc-surface);box-shadow:0 16px 36px rgba(15,23,42,.065);padding:1.08rem;display:grid;gap:.9rem;min-height:15.4rem;transition:.16s ease;text-align:left}
                .rc-template-card-v50:hover{transform:translateY(-1px);box-shadow:0 20px 45px rgba(15,23,42,.09)}
                .rc-template-card-head-v50{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}
                .rc-template-icon-v50{width:2.85rem;height:2.85rem;border-radius:.85rem;background:#fff2ed;color:#ff6338;display:grid;place-items:center;font-weight:760;border:1px solid rgba(255,99,56,.15);flex:0 0 auto}
                .dark .rc-template-icon-v50{background:rgba(255,99,56,.13);color:#ffb199}
                .rc-template-card-main-v50{display:grid;gap:.3rem;min-width:0}
                .rc-template-card-main-v50 h3{margin:0;color:var(--rc-text);font-size:1rem;line-height:1.2;font-weight:760;letter-spacing:-.015em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .rc-template-card-main-v50 p{margin:0;color:var(--rc-muted);font-size:.8rem;line-height:1.3;display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
                .rc-template-subject-v50{border-radius:.58rem;background:rgba(148,163,184,.11);padding:.55rem .6rem;color:var(--rc-text);font-size:.73rem;line-height:1.32;min-height:2.35rem}.rc-template-subject-v50 strong{font-weight:720;color:var(--rc-text)}
                .rc-template-card-actions-v50{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.45rem;align-items:center;margin-top:auto}.rc-template-delete-v52{width:1.75rem;height:1.75rem;border:0;background:transparent;color:var(--rc-muted);display:grid;place-items:center;border-radius:.5rem;cursor:pointer}.rc-template-delete-v52:hover{background:rgba(239,68,68,.08);color:#ef4444}.rc-template-body-v52{color:var(--rc-muted);font-size:.74rem;line-height:1.42;min-height:3.25rem;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.rc-template-use-v52{height:2.3rem;border:0;border-radius:.62rem;background:#ff5f3f;color:#fff;font-weight:720;font-size:.82rem;box-shadow:0 8px 18px rgba(255,95,63,.18);display:inline-flex;align-items:center;justify-content:center;gap:.36rem;cursor:pointer}.rc-template-edit-v52{height:2.3rem;border:1px solid var(--rc-border);border-radius:.62rem;background:var(--rc-surface);color:var(--rc-text);font-weight:690;font-size:.82rem;display:inline-flex;align-items:center;justify-content:center;gap:.35rem;padding:0 .7rem;cursor:pointer}
                .rc-template-editor-layout-v50{display:grid;grid-template-columns:minmax(0,1fr);gap:1rem;align-items:start}
                .rc-template-editor-card-v50{border:1px solid var(--rc-border);border-radius:1.05rem;background:var(--rc-surface);box-shadow:0 16px 38px rgba(15,23,42,.07);padding:1.05rem;display:grid;gap:.95rem}
                .rc-template-ai-v50{border:1px solid var(--rc-border);border-radius:1.05rem;background:var(--rc-surface);box-shadow:0 16px 38px rgba(15,23,42,.07);padding:1rem;position:sticky;top:1rem;display:grid;gap:.8rem}
                .rc-template-ai-head-v50{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
                .rc-template-ai-head-v50 strong{font-size:.95rem;color:var(--rc-text)}
                .rc-template-ai-section-v50{font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;color:var(--rc-muted);font-weight:760;margin-top:.25rem}
                .rc-template-ai-action-v50{width:100%;border:1px solid var(--rc-border);border-radius:.82rem;background:var(--rc-surface);color:var(--rc-text);display:grid;grid-template-columns:2rem minmax(0,1fr);gap:.65rem;align-items:center;text-align:left;padding:.72rem;cursor:pointer;transition:.16s ease}
                .rc-template-ai-action-v50:hover{border-color:rgba(255,99,56,.42);background:rgba(255,99,56,.055)}
                .rc-template-ai-action-v50 i{width:2rem;height:2rem;border-radius:.62rem;display:grid;place-items:center;background:#f1f5f9;color:#7c3aed;font-style:normal}
                .rc-template-ai-action-v50 span{font-size:.84rem;font-weight:700;display:block}.rc-template-ai-action-v50 small{display:block;color:var(--rc-muted);font-size:.72rem;margin-top:.1rem}
                .rc-email-score-v50{display:grid;grid-template-columns:3rem minmax(0,1fr);gap:.65rem;align-items:center}.rc-score-ring-v50{width:3rem;height:3rem;border-radius:999px;border:4px solid #22c55e;display:grid;place-items:center;color:#0f172a;background:#fff;font-weight:800}.dark .rc-score-ring-v50{color:#e5e7eb;background:rgba(15,23,42,.65)}
                .rc-template-field-v50{display:grid;gap:.4rem}.rc-template-field-v50 label{font-size:.75rem;color:var(--rc-text);font-weight:760}.rc-template-field-v50 input{width:100%;height:2.65rem;border:1px solid var(--rc-border);border-radius:.78rem;background:var(--rc-surface);color:var(--rc-text);padding:0 .78rem;font-size:.88rem;outline:none}.rc-template-field-v50 input:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.12)}
                .rc-template-editor-shell-v50{border:1px solid var(--rc-border);border-radius:.9rem;overflow:hidden;background:var(--rc-surface)}
                .rc-template-editor-v50{min-height:19rem;padding:1rem;background:var(--rc-surface);color:var(--rc-text);outline:none;font-size:.9rem;line-height:1.65}.rc-template-editor-v50:empty:before{content:attr(data-placeholder);color:var(--rc-muted)}
                .rc-template-attachments-v50{border:1px solid var(--rc-border);border-radius:1.05rem;background:var(--rc-surface);box-shadow:0 16px 38px rgba(15,23,42,.06);padding:1rem;margin-top:1rem}.rc-template-drop-v50{border:1px dashed rgba(148,163,184,.42);border-radius:.95rem;min-height:4.6rem;display:grid;place-items:center;text-align:center;color:var(--rc-muted);font-size:.82rem;background:rgba(148,163,184,.035);cursor:pointer}
                
                /* Compact templates cards */
                .rc-template-grid-v50{gap:.78rem!important;margin-top:.85rem!important}
                .rc-template-card-v50{border-radius:.9rem!important;padding:.78rem!important;gap:.62rem!important;min-height:12.4rem!important;box-shadow:0 10px 26px rgba(15,23,42,.055)!important}
                .rc-template-icon-v50{width:2.25rem!important;height:2.25rem!important;border-radius:.6rem!important;font-size:.82rem!important;font-weight:750!important}
                .rc-template-card-main-v50 h3{font-size:.88rem!important;font-weight:720!important;line-height:1.22!important}
                .rc-template-card-main-v50 p{font-size:.72rem!important;margin-top:.14rem!important}
                .rc-template-subject-v50{font-size:.73rem!important;line-height:1.32!important;min-height:2.35rem!important;padding:.55rem .6rem!important;border-radius:.58rem!important}
                .rc-template-subject-v50 strong{font-weight:720!important}
                .rc-template-body-v52{font-size:.74rem!important;line-height:1.42!important;min-height:3.25rem!important}
                .rc-template-use-v52,.rc-template-edit-v52{height:2.3rem!important;border-radius:.62rem!important;font-size:.82rem!important}
                .rc-template-use-v52{font-weight:720!important;box-shadow:0 8px 18px rgba(255,95,63,.18)!important}
                .rc-template-edit-v52{font-weight:690!important;padding:0 .7rem!important}
                .rc-template-delete-v52{width:1.75rem!important;height:1.75rem!important}
@media(max-width:1180px){.rc-template-grid-v50{grid-template-columns:repeat(2,minmax(0,1fr))}.rc-template-editor-layout-v50{grid-template-columns:1fr}}
                @media(max-width:720px){.rc-template-grid-v50{grid-template-columns:1fr}.rc-templates-head-v50,.rc-template-list-top-v50{align-items:stretch;flex-direction:column}.rc-template-search-v50{min-width:0}}
            </style>

            <div class="rc-templates-page-v50">
                @if(! $templateEditorOpen)
                    <div class="rc-templates-head-v50" style="margin-top:.25rem">
                        <div>
                            <h2 style="margin:0;color:var(--rc-text);font-size:1.22rem;line-height:1.15;font-weight:760;letter-spacing:-.018em">Templates</h2>
                            <p style="margin:.22rem 0 0;color:var(--rc-muted);font-size:.8rem">Reusable email templates for your coach outreach.</p>
                        </div>
                        <div class="rc-templates-actions-v50">
                            <button class="rc-btn rc-btn-primary" type="button" wire:click="newTemplate" wire:loading.attr="disabled" wire:target="newTemplate"><span wire:loading.remove wire:target="newTemplate">+ New Template</span><span wire:loading.flex wire:target="newTemplate" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Loading</span></button>
                        </div>
                    </div>

                    <div class="rc-template-grid-v50" wire:loading.class="opacity-60" wire:target="loadTemplates,selectTemplate,duplicateTemplate,deleteTemplate,deleteTemplateById,useTemplateForCompose">
                        @forelse($templateRows as $template)
                            @php
                                $templateId = (string) ($template['id'] ?? '');
                                $templateNameDisplay = (string) ($template['name'] ?? 'Untitled Template');
                                $templateSubjectRaw = trim((string) ($template['subjectLine'] ?? $template['subject'] ?? ''));
                                $templateSubjectDisplay = $templateSubjectRaw !== '' ? $templateSubjectRaw : 'No subject yet';
                                $templateBodyRaw = (string) ($template['body'] ?? $template['html'] ?? $template['content'] ?? $template['template'] ?? $template['message'] ?? '');
                                $templateBodyPlain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($templateBodyRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                                $templatePreviewRaw = trim((string) ($template['previewText'] ?? $template['preview_text'] ?? $template['description'] ?? ''));
                                if ($templatePreviewRaw === '') {
                                    $templatePreviewRaw = $templateBodyPlain;
                                }
                                $templatePreviewDisplay = $templatePreviewRaw !== '' ? \Illuminate\Support\Str::limit($templatePreviewRaw, 165) : 'No preview text yet. Open the template to add a short message preview.';
                                $templateSource = (string) ($template['source_type'] ?? 'ghl');
                            @endphp
                            <article class="rc-template-card-v50" wire:key="template-card-v52-{{ $templateId }}">
                                <div class="rc-template-card-head-v50">
                                    <div style="display:flex;align-items:flex-start;gap:.75rem;min-width:0">
                                        <span class="rc-template-icon-v50">{{ strtoupper(substr($templateNameDisplay ?: 'T', 0, 1)) }}</span>
                                        <div class="rc-template-card-main-v50">
                                            <h3>{{ $templateNameDisplay }}</h3>
                                            <p>Coach outreach</p>
                                        </div>
                                    </div>
                                    @if($templateSource !== 'built_in')
                                        <button class="rc-template-delete-v52" type="button" wire:click="deleteTemplateById({{ \Illuminate\Support\Js::from($templateId) }})" wire:confirm="Delete this template?" aria-label="Delete {{ $templateNameDisplay }}">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        </button>
                                    @endif
                                </div>
                                <div class="rc-template-subject-v50"><strong>Subject:</strong> {{ $templateSubjectDisplay }}</div>
                                <div class="rc-template-body-v52">{{ $templatePreviewDisplay }}</div>
                                <div class="rc-template-card-actions-v50">
                                    <button class="rc-template-use-v52" type="button" data-rc-local-action wire:click="useTemplateForCompose({{ \Illuminate\Support\Js::from($templateId) }})" wire:loading.attr="disabled" wire:target="useTemplateForCompose({{ \Illuminate\Support\Js::from($templateId) }})">
                                        <span wire:loading.remove wire:target="useTemplateForCompose({{ \Illuminate\Support\Js::from($templateId) }})" style="display:inline-flex;align-items:center;gap:.4rem"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                                        Use Template</span><span wire:loading.flex wire:target="useTemplateForCompose({{ \Illuminate\Support\Js::from($templateId) }})" style="align-items:center;gap:.4rem"><span class="rc-spinner-mini"></span> Loading</span>
                                    </button>
                                    <button class="rc-template-edit-v52" type="button" wire:click="selectTemplate({{ \Illuminate\Support\Js::from($templateId) }})" data-rc-open="template" data-rc-title="{{ $templateNameDisplay }}" data-rc-copy="Opening the editor now. The latest template content will load inside it.">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M9 15h6"/></svg>
                                        Edit
                                    </button>
                                </div>
                            </article>
                        @empty
                            <div class="rc-empty" style="grid-column:1/-1"><strong>No templates found.</strong><span>Create your first reusable email template.</span></div>
                        @endforelse
                    </div>
                @else
                    <div class="rc-templates-head-v50">
                        <div class="rc-templates-title-v50">
                            <button type="button" class="rc-template-back-v50" wire:click="closeTemplateEditor" aria-label="Back to templates"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                            <div>
                                <h2>{{ $templateIsNew ? 'New Template' : 'Edit Template' }}</h2>
                                <p>Build a reusable email with formatting and merge variables.</p>
                            </div>
                        </div>
                        <div class="rc-templates-actions-v50">
                            <button class="rc-btn" type="button" x-data x-on:click="document.dispatchEvent(new CustomEvent('rc-open-template-preview'))"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> Preview</button>
                            <button class="rc-btn rc-btn-primary" type="button" wire:click="saveTemplate" wire:loading.attr="disabled" wire:target="saveTemplate"><span wire:loading.remove wire:target="saveTemplate">✓ Save Template</span><span wire:loading.flex wire:target="saveTemplate" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Saving</span></button>
                        </div>
                    </div>

                    <div class="rc-template-editor-layout-v50" wire:key="template-editor-{{ $templateEditorRefreshKey }}" x-data="plyrTemplateEditor()" x-init="mount()" x-on:keydown.escape.window="showPreview = false">
                        <section class="rc-template-editor-card-v50">
                            <div class="rc-template-field-v50"><label>Template Name</label><input placeholder="e.g. Spring Showcase Intro" wire:model.live.debounce.650ms="templateName"></div>
                            <div class="rc-template-field-v50"><label>Subject Line</label><input x-ref="subject" placeholder="Subject (you can use @{{variables}})" wire:model.live.debounce.650ms="templateSubject"></div>
                            <div class="rc-template-field-v50"><label>Preview Text</label><input x-ref="preview" placeholder="Short inbox preview text" wire:model.live.debounce.650ms="templatePreviewText"></div>

                            <div>
                                <div class="rc-template-field-label">Insert Variable</div>
                                <div class="rc-toolbar" style="gap:.45rem;flex-wrap:wrap;margin-top:.45rem">
                                    @foreach(['CoachFirstName','SchoolName','CoachTitle','AthleteName','CoachLastName','ProfileLink','HighlightLink','InstagramLink','YoutubeLink','XLink'] as $token)
                                        <button class="rc-token-chip" type="button" data-token="{{ $token }}" x-on:click="insertMerge($el.dataset.token)">{!! '&#123;&#123;' . e($token) . '&#125;&#125;' !!}</button>
                                    @endforeach
                                    <select class="rc-select" style="width:auto" x-on:change="insertMergeFromSelect($event)"><option value="">More</option><option value="GraduationYear">Graduation Year</option><option value="Position">Position</option><option value="ClubTeam">Club Team</option><option value="GPA">GPA</option><option value="AthleteEmail">Athlete Email</option><option value="AthletePhone">Athlete Phone</option><option value="__custom__">Custom value...</option></select>
                                </div>
                            </div>

                            <div class="rc-template-editor-shell-v50">
                                <div class="rc-rich-editor-toolbar" role="toolbar" aria-label="Template editor toolbar">
                                    <select class="rc-select" style="width:auto;height:2rem" x-on:change="block($event.target.value); $event.target.value='p'"><option value="p">Paragraph</option><option value="h2">Heading</option><option value="blockquote">Quote</option></select>
                                    <button class="rc-rich-tool" type="button" x-on:click="command('bold')"><strong>B</strong></button>
                                    <button class="rc-rich-tool" type="button" x-on:click="command('italic')"><em>I</em></button>
                                    <button class="rc-rich-tool" type="button" x-on:click="command('underline')"><u>U</u></button>
                                    <button class="rc-rich-tool" type="button" x-on:click="command('insertUnorderedList')">☷</button>
                                    <button class="rc-rich-tool" type="button" x-on:click="command('insertOrderedList')">☑</button>
                                    <button class="rc-rich-tool" type="button" x-on:click="addLink()">🔗</button>
                                    <button class="rc-rich-tool" type="button" x-on:click="openImageUpload()">▧</button>
                                </div>
                                <input x-ref="imageUpload" type="file" accept="image/*" multiple class="sr-only" x-on:change="uploadInlineImages($event)">
                                <div x-show="uploadingImages" class="rc-loading-inline" style="padding:.5rem .75rem"><span class="rc-spinner-mini"></span> Uploading image</div>
                                <div x-ref="editor"
                                     wire:ignore
                                     class="rc-template-editor-v50"
                                     contenteditable="true"
                                     data-placeholder="Write your reusable email template..."
                                     data-initial-body="{{ base64_encode($templateBody ?? '') }}"
                                     data-refresh-key="{{ $templateEditorRefreshKey }}"
                                     x-on:input="queueSync()"
                                     x-on:blur="syncNow()">{!! $templateBody ?? '' !!}</div>
                                <input x-ref="hidden" type="hidden" data-plyr-native-editor-hidden="template-body" wire:model.live.debounce.900ms="templateBody">
                            </div>

                            <div class="rc-attachments-v45" style="box-shadow:none;padding:.85rem">
                                <div style="font-weight:700;font-size:.85rem">Attachments ({{ count($templateAttachments ?? []) }})</div>
                                <div class="rc-attachment-grid-v45">
                                    @foreach(($templateAttachments ?? []) as $index => $attachment)
                                        <?php $name = (string) ($attachment['name'] ?? 'Attachment'); $ext = strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE'); ?>
                                        <div class="rc-attachment-card-v45">
                                            <div class="rc-attachment-icon-v45 {{ $ext === 'PDF' ? '' : 'is-file' }}">{{ \Illuminate\Support\Str::limit($ext, 4, '') }}</div>
                                            <div style="min-width:0;flex:1">
                                                <div style="font-size:.8rem;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $name }}</div>
                                                <div class="rc-subtle">{{ $attachment['mime_type'] ?? 'File' }} @if(!empty($attachment['size'])) · {{ number_format(((int) $attachment['size']) / 1048576, 1) }} MB @endif</div>
                                            </div>
                                            <button type="button" class="rc-icon-button" wire:click="removeTemplateAttachment({{ $index }})">×</button>
                                        </div>
                                    @endforeach
                                    <label class="rc-attachment-drop-v45">
                                        <input type="file" multiple style="display:none" wire:model="templateAttachmentUploads" />
                                        <span>
                                            <svg class="rc-icon-sm" style="margin:0 auto .3rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1M12 4v12m0-12 4 4m-4-4-4 4" /></svg>
                                            <strong style="display:block;color:var(--rc-text);font-size:.82rem">Upload files</strong>
                                            <span style="font-size:.72rem">or click to browse · Max 25MB per file</span>
                                        </span>
                                    </label>
                                </div>
                                <div wire:loading.flex wire:target="templateAttachmentUploads,addTemplateAttachments" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Uploading files</div>
                            </div>

                            <div class="rc-toolbar" style="justify-content:space-between">
                                <div class="rc-subtle">Words: {{ str_word_count(strip_tags($templateBody ?? '')) }} &nbsp; <span style="color:#16a34a">Looks good!</span></div>
                                @if($selectedTemplateId && ! $templateIsNew)
                                    <button class="rc-btn" type="button" wire:click="deleteTemplate" wire:confirm="Delete this template?">Delete Template</button>
                                @endif
                            </div>

                            <div class="rc-preview-modal-backdrop" x-cloak x-show="showPreview" x-transition.opacity>
                                <div class="rc-preview-modal" x-on:click.outside="showPreview = false">
                                    <div class="rc-preview-modal-head"><div><div style="font-size:.78rem;color:#64748b;margin-bottom:.25rem">Template preview</div><h3 style="margin:0;font-size:1.2rem;line-height:1.35;font-weight:800" x-text="previewSubject()"></h3></div><button type="button" class="rc-btn" x-on:click="showPreview = false">Close</button></div>
                                    <div class="rc-preview-modal-body"><div x-html="previewHtml()"></div></div>
                                </div>
                            </div>
                        </section>

                    </div>
                @endif
            </div>
        @endif


        @if($selectedCoachId && $section !== 'conversations')
            <div class="rc-card">
                <?php $composerCoach = $this->selectedCoach; ?>
                        <div class="rc-native-email-composer" x-data="plyrNativeEditorBase('emailBody')" x-init="mount()" wire:key="native-email-composer-{{ $selectedCoachId ?: $selectedConversationId ?: 'new' }}">
                            <div class="rc-card is-flat" style="display:grid;gap:.75rem;margin-top:.75rem">
                                <div class="rc-top">
                                    <div>
                                        <div class="rc-row-title">Email {{ $composerCoach['name'] ?? ($selectedConversation['contact_name'] ?? 'coach') }}</div>
                                        <div class="rc-subtle">Built-in PLYRCard editor. No external editor account required.</div>
                                    </div>
                                    <button class="rc-btn" type="button" wire:click="closeComposer">Close</button>
                                </div>

                                <label style="display:grid;gap:.35rem">
                                    <span class="rc-section-title" style="margin:0">Subject</span>
                                    <input class="rc-input" style="width:100%" type="text" wire:model.live.debounce.500ms="emailSubject" placeholder="Subject">
                                </label>

                                <div class="rc-rich-editor-shell rc-native-editor-shell">
                                    <div class="rc-rich-editor-toolbar" role="toolbar" aria-label="Email message toolbar">
                                        <button class="rc-rich-tool" type="button" x-on:click="command('undo')">↶</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="command('redo')">↷</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="block('p')">P</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="block('h2')">H2</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="command('bold')"><strong>B</strong></button>
                                        <button class="rc-rich-tool" type="button" x-on:click="command('italic')"><em>I</em></button>
                                        <button class="rc-rich-tool" type="button" x-on:click="command('underline')"><u>U</u></button>
                                        <button class="rc-rich-tool" type="button" x-on:click="command('insertUnorderedList')">• List</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="command('insertOrderedList')">1. List</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="addLink()">Link</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="openImageUpload()">Image</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="addButton()">Button</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="addTable()">Table</button>
                                        <button class="rc-rich-tool" type="button" x-on:click="command('removeFormat')">Clear</button>
                                    </div>
                                    <div class="rc-rich-editor-toolbar rc-merge-toolbar" aria-label="Merge values">
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('CoachFirstName')">Coach first</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('CoachLastName')">Coach last</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('CoachName')">Coach full</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('SchoolName')">School</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('CoachTitle')">Coach title</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('AthleteName')">Athlete</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('GraduationYear')">Grad year</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('Position')">Position</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('HighlightLink')">Highlight link</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('ProfileLink')">Profile link</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('AthleteEmail')">Email</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('AthletePhone')">Phone</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('InstagramLink')">Instagram</button>
                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('TwitterLink')">X</button>
                                                                                                                        <button class="rc-token-chip" type="button" x-on:click="insertMerge('YoutubeLink')">YouTube</button>
                                    </div>
                                    <input x-ref="imageUpload" type="file" accept="image/*" multiple style="display:none" x-on:change="uploadInlineImages($event)">
                                    
                                    <div
                                        x-cloak
                                        x-show="activePanel"
                                        x-transition.opacity
                                        x-on:keydown.escape.window="closeEditorPanel()"
                                        x-on:click.self="closeEditorPanel()"
                                        style="position:fixed;inset:0;z-index:90;display:grid;place-items:center;padding:1rem;background:rgba(2,6,23,.62);backdrop-filter:blur(5px);"
                                    >
                                        <div style="width:min(26rem,94vw);border:1px solid rgba(148,163,184,.22);border-radius:1.1rem;background:var(--rc-surface);box-shadow:0 24px 80px rgba(0,0,0,.38);overflow:hidden;">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid var(--rc-border);">
                                                <strong x-text="activePanel === 'button' ? 'Insert button' : 'Insert link'" style="font-size:.92rem"></strong>
                                                <button type="button" class="rc-icon-button" x-on:click="closeEditorPanel()" aria-label="Close">×</button>
                                            </div>
                                            <div x-show="activePanel === 'link'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Link text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Link text" x-model="panelLinkLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelLinkUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" x-on:click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" x-on:click="applyLinkPanel()">Insert link</button></div>
                                            </div>
                                            <div x-show="activePanel === 'button'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Button text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Button text" x-model="panelButtonLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelButtonUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" x-on:click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" x-on:click="applyButtonPanel()">Insert button</button></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-cloak x-show="editorNotice" class="rc-subtle" style="padding:.55rem .65rem;border-top:1px solid rgba(148,163,184,.14);color:#fed7aa" x-text="editorNotice"></div>
                                    <div x-show="uploadingImages" class="rc-loading-inline" style="padding:.5rem .65rem"><span class="rc-spinner-mini"></span> Uploading image</div>
                                    <div
                                        x-ref="editor"
                                        wire:ignore
                                        class="rc-rich-editor rc-native-editor"
                                        contenteditable="true"
                                        data-placeholder="Write your message..."
                                        data-initial-body="{{ base64_encode($emailBody ?? '') }}"
                                        x-on:input="queueSync()"
                                        @blur="syncNow()"
                                    ></div>
                                </div>

                                <div class="rc-toolbar" style="justify-content:flex-end">
                                    <button class="rc-btn" type="button" wire:click="closeComposer">Cancel</button>
                                    <button class="rc-btn rc-btn-primary" type="button" wire:click="sendEmail" wire:loading.attr="disabled" wire:target="sendEmail">
                                        <span wire:loading.remove wire:target="sendEmail">Send email</span>
                                        <span wire:loading.flex wire:target="sendEmail" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Sending</span>
                                    </button>
                                </div>
                            </div>
                        </div>
            </div>
        @endif

        {{-- v108: persistent instant client-side school drawer shell. The selected school is mirrored
            to window.__plyrSchoolDrawerOptimistic so a Livewire morph cannot reset the open drawer
            between the optimistic click state and the final local roster response. --}}
        {{-- v105: instant client-side school drawer shell. This opens from the already-rendered
             local card payload before Livewire performs the one-school local DB query. --}}
        {{-- v109 Discover drawer: entirely browser-local for opening/closing/filtering and
             interaction state. Favorite/list calls only persist the already-applied state in
             the background and use skipRender(), so this drawer is never replaced or flickered. --}}
        <template x-if="optimisticSchool">
            <div class="rc-drawer rc-school-optimistic-shell-v106" x-on:click.self="closeDiscoverSchool()" x-on:keydown.escape.window="closeDiscoverSchool()" style="z-index:9999">
                <div class="rc-drawer-panel rc-school-modal-panel rc-school-optimistic-panel-v106 rc-discover-drawer-panel-v111" role="dialog" aria-modal="true" aria-label="School details" x-on:click.stop> 
                    <button class="rc-school-modal-close" type="button" x-on:click.stop.prevent="closeDiscoverSchool()" aria-label="Close school details">×</button>

                <div class="rc-school-modal-hero-v72">
                    <div class="rc-school-logo-large-v72">
                        <img x-show="optimisticSchool?.logo_url" x-bind:src="optimisticSchool?.logo_url || ''" x-bind:alt="`${optimisticSchool?.name || 'School'} logo`" referrerpolicy="no-referrer" onerror="this.style.display='none'">
                        <span x-show="!optimisticSchool?.logo_url" x-text="String(optimisticSchool?.name || 'S').split(/\s+/).slice(0,2).map(v => v[0] || '').join('').toUpperCase()"></span>
                    </div>
                    <div class="rc-school-modal-main">
                        <span class="rc-school-division-pill" x-text="optimisticSchool?.division || 'Division'"></span>
                        <h2 x-text="optimisticSchool?.name || 'School'"></h2>
                        <div class="rc-school-modal-meta">
                            <span x-text="`◎ ${optimisticSchool?.conference || 'Conference unavailable'}`"></span>
                            <span x-show="optimisticSchool?.city || optimisticSchool?.state" x-text="`· ${[optimisticSchool?.city, optimisticSchool?.state].filter(Boolean).join(', ')}`"></span>
                        </div>
                    </div>
                    <div class="rc-school-score-wrap">
                        <div class="rc-school-score-ring" x-text="Math.max(0, Math.min(100, Number(optimisticSchool?.engagement_score || 0)))"></div>
                        <div class="rc-school-score-label" x-text="Number(optimisticSchool?.engagement_score || 0) >= 70 ? 'HOT' : (Number(optimisticSchool?.engagement_score || 0) >= 35 ? 'WARM' : 'NEW')"></div>
                    </div>
                </div>

                <div class="rc-school-modal-actions-v72">
                    <button class="rc-school-action rc-school-action-primary" type="button" x-on:click="if (optimisticSchool?.id) $wire.composeEmailSchool(String(optimisticSchool.id))">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6.5h16v11H4v-11Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m4.5 7 7.5 6 7.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span>Email Coaches</span>
                    </button>

                    <button class="rc-school-action" type="button" x-on:click="favoriteDiscoverSchool()" x-bind:class="optimisticSchool?.is_favorite ? 'is-favorited' : ''">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 3.8 2.48 5.03 5.55.8-4.02 3.91.95 5.53L12 16.46l-4.96 2.61.95-5.53-4.02-3.91 5.55-.8L12 3.8Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                        <span x-text="optimisticSchool?.is_favorite ? 'Favorited' : 'Favorite'"></span>
                    </button>

                    <div class="rc-school-list-dropdown-v72" x-on:click.outside="discoverListsOpen=false">
                        <button class="rc-school-action" type="button" x-on:click="discoverListsOpen=!discoverListsOpen" x-bind:class="(optimisticSchool?.list_keys || []).filter(k => String(k).toLowerCase() !== '__favorite__').length ? 'is-in-list' : ''">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                            <span x-text="(optimisticSchool?.list_keys || []).filter(k => String(k).toLowerCase() !== '__favorite__').length ? 'In Lists' : 'Add to List'"></span>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m7 10 5 5 5-5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="rc-school-list-menu-v72 rc-discover-list-menu-v112" x-cloak x-show="discoverListsOpen" x-transition.opacity.scale.origin.top.left>
                            <h4>Add to a list</h4>
                            <template x-for="list in discoverLists" :key="`drawer-list-${discoverListKey(list)}`">
                                <button type="button" x-bind:style="`--list-color:${discoverListColor(list)}`" x-bind:class="discoverInList(discoverListKey(list)) ? 'is-active' : ''" x-on:click="toggleDiscoverList(discoverListKey(list))">
                                    <span class="rc-list-check-v81"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10.5 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                    <span class="rc-school-list-label-v87"><span class="rc-school-list-dot-v72" x-bind:style="`--dot:${discoverListColor(list)}`"></span><span x-text="discoverListLabel(list)"></span></span>
                                    <small class="rc-list-count-v81" x-text="discoverListCount(list)"></small>
                                </button>
                            </template>
                            <div class="rc-school-list-empty" x-show="discoverLists.length === 0">No lists yet.</div>

                            <div class="rc-list-quick-create-v112">
                                <div class="rc-list-quick-create-title-v112">Create new list</div>
                                <div class="rc-list-quick-create-row-v112">
                                    <input type="text" x-model="discoverNewDrawerListName" x-on:keydown.enter.prevent="createDiscoverDrawerList()" placeholder="New list name" maxlength="80">
                                    <button type="button" class="rc-list-quick-create-btn-v112" x-on:click="createDiscoverDrawerList()" x-bind:disabled="discoverCreatingList || !String(discoverNewDrawerListName || '').trim()">
                                        <span x-show="!discoverCreatingList">Create &amp; Add</span>
                                        <span x-show="discoverCreatingList" x-cloak>Creating…</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rc-school-tabbar-v72 rc-discover-tabbar-v111" role="tablist" aria-label="School detail tabs">
                    <button type="button" class="rc-school-tab-v72" x-bind:class="discoverDrawerTab === 'coaches' ? 'is-active' : ''" x-on:click.stop="discoverDrawerTab='coaches'">Coaching Staff</button>
                    <button type="button" class="rc-school-tab-v72" x-bind:class="discoverDrawerTab === 'roster' ? 'is-active' : ''" x-on:click.stop="discoverDrawerTab='roster'">Roster &amp; Stats</button>
                    <button type="button" class="rc-school-tab-v72" x-bind:class="discoverDrawerTab === 'comms' ? 'is-active' : ''" x-on:click.stop="discoverDrawerTab='comms'; loadDiscoverCommunications(true)">Communications</button>
                </div>

                <section class="rc-school-tab-panel-v72 rc-discover-tab-panel-v111" x-show="discoverDrawerTab === 'coaches'">
                    <div class="rc-school-coach-list rc-school-modal-coaches" style="max-height:22rem;overflow:auto;padding-right:.15rem;">
                        <template x-if="Array.isArray(optimisticSchool?.coaches) && optimisticSchool.coaches.length">
                            <div style="display:grid;gap:.7rem">
                                <template x-for="coach in optimisticSchool.coaches" :key="`discover-drawer-coach-${coach.id}`">
                                    <div class="rc-school-coach-card">
                                        <div class="rc-school-coach-avatar" x-text="String(coach.name || 'C').split(/\s+/).slice(0,2).map(v => v[0] || '').join('').toUpperCase()"></div>
                                        <div class="rc-school-coach-info">
                                            <strong>
                                                <span x-text="coach.name || 'Coach'"></span>
                                                <span class="rc-head-coach-chip" x-show="String(coach.title || '').toLowerCase().includes('head')">Head Coach</span>
                                            </strong>
                                            <span x-text="coach.title || 'Coach'"></span>
                                            <a x-show="coach.email" x-bind:href="`mailto:${coach.email}`" x-text="coach.email"></a>
                                        </div>
                                        <a class="rc-school-copy-btn" x-show="coach.email" x-bind:href="`mailto:${coach.email}`" title="Email coach" x-on:click.stop>
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="M4 6.5h16v11H4v-11Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m4.5 7 7.5 6 7.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div class="rc-empty" x-show="!Array.isArray(optimisticSchool?.coaches) || optimisticSchool.coaches.length === 0">
                            <strong>No local coaches found.</strong>
                        </div>
                    </div>
                </section>

                <section class="rc-school-tab-panel-v72 rc-discover-tab-panel-v111" x-show="discoverDrawerTab === 'roster'" x-cloak>
                    <div style="min-height:18rem;display:flex;align-items:center;justify-content:center;padding:2rem;text-align:center;">
                        <div style="max-width:24rem;">
                            <div style="width:3.25rem;height:3.25rem;margin:0 auto 1rem;border-radius:1rem;display:grid;place-items:center;background:var(--rc-soft);color:var(--rc-accent);font-size:1.35rem;font-weight:900;">↗</div>
                            <strong style="display:block;font-size:1.15rem;line-height:1.25;color:var(--rc-text);">Roster &amp; Stats Coming Soon</strong>
                            <span style="display:block;margin-top:.5rem;color:var(--rc-muted);font-size:.9rem;line-height:1.5;">Team roster and school performance insights will be available here soon.</span>
                        </div>
                    </div>
                </section>

                <section class="rc-school-tab-panel-v72 rc-discover-tab-panel-v111" x-show="discoverDrawerTab === 'comms'" x-cloak>
                    <div class="rc-school-comms-history-v123">
                        <div class="rc-school-comms-loading-v123" x-show="discoverSchoolCommsLoading">
                            <span class="rc-spinner-mini"></span><span>Loading conversation history…</span>
                        </div>
                        <template x-if="!discoverSchoolCommsLoading && discoverSchoolComms.length">
                            <div class="rc-school-comms-list-v123">
                                <template x-for="row in discoverSchoolComms" :key="row.id">
                                    <div class="rc-school-comms-row-v123">
                                        <span class="rc-school-comms-direction-v123" x-bind:class="row.direction === 'inbound' ? 'is-inbound' : 'is-outbound'" x-text="row.direction === 'inbound' ? '↙' : '↗'"></span>
                                        <div class="rc-school-comms-copy-v123">
                                            <strong x-text="row.title || 'Conversation activity'"></strong>
                                            <span x-text="row.preview || 'No message preview available.'"></span>
                                            <small>
                                                <span x-text="row.date_label || ''"></span>
                                                <span x-show="row.opened"> · Opened</span>
                                                <span x-show="row.reply"> · Reply</span>
                                            </small>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div class="rc-empty" x-show="!discoverSchoolCommsLoading && discoverSchoolComms.length === 0">
                            <strong>No conversation history yet.</strong>
                            <span>Emails and replies with coaches from this school will appear here.</span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        </template>

        {{-- v113: legacy section-specific server drawer removed. The Discover drawer above
             is the single global drawer for Dashboard, Discover, Favorites, and My Lists. --}}
    </div>
    </div>

    <style>
        /* v100: keep logo fallback initials from ever becoming a full-page overlay.
           This is intentionally non-invasive: it does not touch data loading, school loading,
           logo URL handling, or pagination. */
        .rc-wrap .rc-logo-fallback-text {
            position: relative !important;
            inset: auto !important;
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            max-width: 4.5rem !important;
            max-height: 4.5rem !important;
            box-sizing: border-box !important;
            z-index: 0 !important;
        }

        .rc-wrap .rc-school-card-logo-box,
        .rc-wrap .rc-school-list-logo-box,
        .rc-wrap .rc-radar-logo-v2,
        .rc-wrap .rc-interested-logo-v2,
        .rc-wrap .rc-favorite-logo-v37,
        .rc-wrap .rc-fav-list-logo-v40,
        .rc-wrap .rc-drawer-school-logo,
        .rc-wrap .rc-school-drawer-logo,
        .rc-wrap .rc-inbox-school-logo,
        .rc-wrap .rc-school-logo-placeholder,
        .rc-wrap .rc-logo-initials {
            position: relative !important;
            overflow: hidden !important;
        }
    </style>

    <style id="rc-school-comms-v123">
        .rc-school-comms-history-v123{min-height:14rem;padding:.15rem 0}.rc-school-comms-loading-v123{min-height:10rem;display:flex;align-items:center;justify-content:center;gap:.55rem;color:var(--rc-muted);font-size:.84rem}.rc-school-comms-list-v123{display:grid;gap:.65rem}.rc-school-comms-row-v123{display:grid;grid-template-columns:2.15rem minmax(0,1fr);gap:.7rem;align-items:start;padding:.8rem;border:1px solid var(--rc-border);border-radius:.85rem;background:var(--rc-surface)}.rc-school-comms-direction-v123{width:2.15rem;height:2.15rem;display:grid;place-items:center;border-radius:.65rem;background:#fff1ed;color:#ff6338;font-weight:900}.rc-school-comms-direction-v123.is-inbound{background:#ecfdf5;color:#16a34a}.rc-school-comms-copy-v123{min-width:0;display:grid;gap:.18rem}.rc-school-comms-copy-v123 strong{font-size:.82rem;color:var(--rc-text)}.rc-school-comms-copy-v123 span{font-size:.75rem;color:var(--rc-muted);line-height:1.42}.rc-school-comms-copy-v123 small{font-size:.68rem;color:#9aa3b2}
    </style>

    <style>
        /* v111: Discover drawer owns the interaction plane. The backdrop catches clicks and
           the panel opts into pointer events explicitly so nothing can click through to cards. */
        .rc-wrap .rc-school-optimistic-shell-v106 {
            position: fixed !important;
            inset: 0 !important;
            z-index: 2147483000 !important;
            display: flex !important;
            justify-content: flex-end !important;
            align-items: stretch !important;
            pointer-events: auto !important;
            background: rgba(15, 23, 42, .34) !important;
            backdrop-filter: blur(3px) !important;
            -webkit-backdrop-filter: blur(3px) !important;
            isolation: isolate !important;
        }
        .rc-wrap .rc-school-optimistic-panel-v106,
        .rc-wrap .rc-discover-drawer-panel-v111 {
            position: relative !important;
            z-index: 2147483001 !important;
            pointer-events: auto !important;
            width: min(640px, 100vw) !important;
            height: 100vh !important;
            max-height: 100vh !important;
            overflow-y: auto !important;
            overflow-x: visible !important;
            border-radius: 1.35rem 0 0 1.35rem !important;
        }
        .rc-wrap .rc-discover-drawer-panel-v111 *,
        .rc-wrap .rc-discover-drawer-panel-v111 button,
        .rc-wrap .rc-discover-drawer-panel-v111 a {
            pointer-events: auto !important;
        }
        .rc-wrap .rc-discover-drawer-panel-v111 .rc-school-modal-close {
            z-index: 2147483003 !important;
            cursor: pointer !important;
        }
        .rc-wrap .rc-discover-drawer-panel-v111 .rc-school-list-dropdown-v72,
        .rc-wrap .rc-discover-drawer-panel-v111 .rc-school-list-menu-v72 {
            z-index: 2147483004 !important;
        }
        .rc-wrap .rc-discover-tabbar-v111 {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: .35rem !important;
            margin-top: 1rem !important;
            padding: .3rem !important;
            border: 1px solid var(--rc-border) !important;
            border-radius: 1rem !important;
            background: var(--rc-soft) !important;
        }
        .rc-wrap .rc-discover-tabbar-v111 .rc-school-tab-v72 {
            min-height: 3rem !important;
            border: 0 !important;
            border-radius: .8rem !important;
            background: transparent !important;
            color: var(--rc-muted) !important;
            font-weight: 750 !important;
            cursor: pointer !important;
        }
        .rc-wrap .rc-discover-tabbar-v111 .rc-school-tab-v72.is-active {
            background: var(--rc-surface) !important;
            color: var(--rc-text) !important;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .08) !important;
        }
        .rc-wrap .rc-discover-tab-panel-v111 {
            padding-top: 1rem !important;
        }

        /* v112: Discover bulk/list menus share the polished drawer menu language and
           include inline list creation without a page rerender. */
        .rc-wrap .rc-discover-list-menu-v112 {
            width: min(22rem, 88vw) !important;
            max-height: 28rem !important;
            overflow-y: auto !important;
            padding: .75rem !important;
            border: 1px solid var(--rc-border) !important;
            border-radius: 1rem !important;
            background: var(--rc-surface) !important;
            box-shadow: 0 22px 55px rgba(15, 23, 42, .18) !important;
        }
        .rc-wrap .rc-discover-list-menu-v112 h4 {
            margin: 0 0 .45rem !important;
            padding: 0 .15rem !important;
            font-size: .72rem !important;
            line-height: 1 !important;
            text-transform: uppercase !important;
            letter-spacing: .1em !important;
            color: var(--rc-muted) !important;
            font-weight: 800 !important;
        }
        .rc-wrap .rc-discover-list-option-v112 {
            display: grid !important;
            grid-template-columns: auto minmax(0, 1fr) auto !important;
            align-items: center !important;
            gap: .6rem !important;
            min-height: 2.9rem !important;
            padding: .55rem .65rem !important;
            border-radius: .8rem !important;
        }
        .rc-wrap .rc-list-check-plus-v112 {
            display: inline-grid !important;
            place-items: center !important;
            font-size: 1rem !important;
            line-height: 1 !important;
            color: var(--rc-muted) !important;
        }
        .rc-wrap .rc-list-quick-create-v112 {
            margin-top: .65rem !important;
            padding-top: .7rem !important;
            border-top: 1px solid var(--rc-border) !important;
        }
        .rc-wrap .rc-list-quick-create-title-v112 {
            margin: 0 0 .45rem !important;
            font-size: .72rem !important;
            text-transform: uppercase !important;
            letter-spacing: .09em !important;
            color: var(--rc-muted) !important;
            font-weight: 800 !important;
        }
        .rc-wrap .rc-list-quick-create-row-v112 {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) auto !important;
            gap: .45rem !important;
            align-items: center !important;
        }
        .rc-wrap .rc-list-quick-create-row-v112 input {
            width: 100% !important;
            min-width: 0 !important;
            height: 2.55rem !important;
            padding: 0 .75rem !important;
            border: 1px solid var(--rc-border) !important;
            border-radius: .72rem !important;
            background: var(--rc-surface) !important;
            color: var(--rc-text) !important;
            outline: none !important;
            font-size: .82rem !important;
        }
        .rc-wrap .rc-list-quick-create-row-v112 input:focus {
            border-color: rgba(255, 99, 56, .7) !important;
            box-shadow: 0 0 0 3px rgba(255, 99, 56, .12) !important;
        }
        .rc-wrap .rc-list-quick-create-btn-v112 {
            min-height: 2.55rem !important;
            padding: 0 .8rem !important;
            border: 0 !important;
            border-radius: .72rem !important;
            background: var(--rc-accent) !important;
            color: #fff !important;
            font-size: .78rem !important;
            font-weight: 800 !important;
            white-space: nowrap !important;
            cursor: pointer !important;
        }
        .rc-wrap .rc-list-quick-create-btn-v112:disabled {
            opacity: .5 !important;
            cursor: default !important;
        }
        .rc-wrap .rc-discover-bulk-notice-v112 {
            margin: .55rem 0 0 !important;
            padding: .65rem .8rem !important;
            border: 1px solid rgba(34, 197, 94, .22) !important;
            border-radius: .75rem !important;
            background: rgba(34, 197, 94, .08) !important;
            color: #15803d !important;
            font-size: .8rem !important;
            font-weight: 750 !important;
        }
        .dark .rc-wrap .rc-discover-bulk-notice-v112 {
            color: #86efac !important;
            background: rgba(34, 197, 94, .12) !important;
        }
    </style>

    <script>

        window.plyrRepairBrokenEditorLinkFragments = function (html) {
            let source = String(html || '');
            if (!source) return '';

            const buttonStyle = 'display:block;width:100%;box-sizing:border-box;text-align:center;text-decoration:none;font-weight:800;border-radius:10px;padding:12px 16px;margin:0 0 10px;';
            const repairs = [
                { token: 'ProfileLink', label: 'View PLYRCard Profile', style: buttonStyle + 'background:#ff5b32;color:#ffffff;', className: 'rc-email-button' },
                { token: 'HighlightLink', label: 'Watch Highlights', style: buttonStyle + 'background:#111827;color:#ffffff;', className: 'rc-email-button' },
            ];

            const escReg = (value) => String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            repairs.forEach((item) => {
                const tokenPattern = '\\{\\{\\s*' + escReg(item.token) + '\\s*\\}\\}';
                const attrQuote = '(?:"|\\\'|&quot;|&#034;|&#39;)';
                const classAttr = item.className ? ' class="' + item.className + '"' : '';
                const replacement = '<a' + classAttr + ' href="{{' + item.token + '}}" target="_blank" style="' + item.style + '">' + item.label + '</a>';
                source = source.replace(new RegExp(tokenPattern + '\\s*' + attrQuote + '\\s*(?:data-plyrcard-link\\s*=\\s*' + attrQuote + '[^"\\\' >]+' + attrQuote + '\\s*)?(?:target\\s*=\\s*' + attrQuote + '?_blank' + attrQuote + '?\\s*)?[^>\\n\\r]*>\\s*' + escReg(item.label), 'gi'), replacement);
                if (['InstagramLink', 'XLink', 'TwitterLink', 'YoutubeLink', 'YouTubeLink'].includes(item.token)) {
                    source = source.replace(new RegExp(tokenPattern + '\\s*' + attrQuote + '\\s*data-plyrcard-link\\s*=\\s*' + attrQuote + '[^"\\\' >]+' + attrQuote + '\\s*[^>\\n\\r]*>\\s*', 'gi'), replacement + ' ');
                }
            });

            source = source.replace(/<span\b[^>]*style="[^"]*(?:background\s*:\s*#?000|background-color\s*:\s*#?000)[^"]*"[^>]*>\s*(?:<\/span>|&nbsp;)?/gi, '');
            source = source.replace(/<span\b[^>]*class="[^"]*social[^"]*"[^>]*>\s*<\/span>/gi, '');
            source = source.replace(/<\/a>\s*(?=<a\b)/gi, '');
            return source;
        };

        window.plyrNativeEditorBase = function (modelName, initialBody = '') {
            return {
                syncTimer: null,
                mounted: false,
                uploadingImages: false,
                editorNotice: '',
                activePanel: '',
                panelLinkLabel: '',
                panelLinkUrl: '',
                panelButtonLabel: '',
                panelButtonUrl: '',
                composeRefreshHandler: null,
                savedSelectionRange: null,
                selectionHandler: null,
                mount() {
                    if (this.mounted) return;
                    this.mounted = true;
                    this.selectionHandler = () => this.captureSelection();
                    document.addEventListener('selectionchange', this.selectionHandler);
                    this.$nextTick(() => {
                        this.bootEditor();
                        setTimeout(() => this.bootEditor(true), 80);
                        setTimeout(() => this.bootEditor(true), 250);
                    });
                    if (modelName === 'campaignBody') {
                        if (window.__plyrComposeEditorRefreshHandler) {
                            window.removeEventListener('rc-compose-editor-refresh', window.__plyrComposeEditorRefreshHandler);
                        }

                        this.composeRefreshHandler = (event) => {
                            const editor = this.$refs.editor;
                            if (!editor || !editor.isConnected) return;

                            const encoded = event.detail?.body || '';
                            const html = this.decodeInitialBody(encoded);

                            editor.dataset.initialBody = encoded;
                            editor.innerHTML = this.highlightMergeTokens(html || '');

                            // The body already came from Livewire/PHP. Do not immediately
                            // sync it back to the server here: stale editor instances can
                            // otherwise overwrite the newly selected template with blank HTML.
                        };

                        window.__plyrComposeEditorRefreshHandler = this.composeRefreshHandler;
                        window.addEventListener('rc-compose-editor-refresh', this.composeRefreshHandler);
                    }
                },
                destroy() {
                    if (this.selectionHandler) {
                        document.removeEventListener('selectionchange', this.selectionHandler);
                        this.selectionHandler = null;
                    }
                    if (this.composeRefreshHandler) {
                        window.removeEventListener('rc-compose-editor-refresh', this.composeRefreshHandler);
                        if (window.__plyrComposeEditorRefreshHandler === this.composeRefreshHandler) {
                            window.__plyrComposeEditorRefreshHandler = null;
                        }
                    }
                },
                bootEditor() {
                    if (!this.$refs.editor) return;
                    const html = this.decodeInitialBody(initialBody || this.$refs.editor.dataset.initialBody || '');
                    if (html && this.$refs.editor.innerHTML.trim() === '') {
                        this.$refs.editor.innerHTML = this.highlightMergeTokens(html);
                    } else {
                        this.$refs.editor.innerHTML = this.highlightMergeTokens(this.$refs.editor.innerHTML || '');
                    }
                    this.syncNow();
                },
                decodeInitialBody(initial) {
                    if (!initial) return '';
                    try { return decodeURIComponent(escape(window.atob(initial))); }
                    catch (error) { try { return window.atob(initial); } catch (_) { return ''; } }
                },
                queueSync() {
                    clearTimeout(this.syncTimer);
                    this.syncTimer = setTimeout(() => this.syncNow(), 700);
                },
                syncNow() {
                    if (!this.$refs.editor) return;
                    const html = this.serializeEditorHtml();
                    if (modelName && this.$wire) this.$wire.set(modelName, html, false);
                },
                serializeEditorHtml() {
                    const clone = this.$refs.editor.cloneNode(true);
                    clone.querySelectorAll('.rc-merge-token-v48').forEach((node) => {
                        node.replaceWith(document.createTextNode(node.textContent || ''));
                    });
                    return clone.innerHTML || '';
                },
                focusEditor() { this.$refs.editor?.focus(); },
                command(name, value = null) {
                    this.focusEditor();
                    document.execCommand(name, false, value);
                    this.syncNow();
                },
                block(tag) {
                    const safeTag = ['p', 'h1', 'h2', 'h3', 'blockquote'].includes(tag) ? tag : 'p';
                    this.command('formatBlock', safeTag);
                },
                escapeHtml(value) {
                    return String(value || '')
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                },
                highlightMergeTokens(html) {
                    const source = window.plyrRepairBrokenEditorLinkFragments ? window.plyrRepairBrokenEditorLinkFragments(String(html || '')) : String(html || '');
                    if (!source) return '';
                    if (source.includes('rc-merge-token-v48')) return source;

                    const template = document.createElement('template');
                    template.innerHTML = source;
                    const pattern = /\{\{\s*([A-Za-z][A-Za-z0-9_ .]{0,80})\s*\}\}/g;

                    const walk = (node) => {
                        if (node.nodeType === Node.TEXT_NODE) {
                            const text = node.nodeValue || '';
                            if (!pattern.test(text)) {
                                pattern.lastIndex = 0;
                                return;
                            }

                            pattern.lastIndex = 0;
                            const fragment = document.createDocumentFragment();
                            let lastIndex = 0;
                            text.replace(pattern, (match, _name, offset) => {
                                if (offset > lastIndex) {
                                    fragment.appendChild(document.createTextNode(text.slice(lastIndex, offset)));
                                }
                                const span = document.createElement('span');
                                span.className = 'rc-merge-token-v48';
                                span.contentEditable = 'false';
                                span.textContent = match;
                                fragment.appendChild(span);
                                lastIndex = offset + match.length;
                                return match;
                            });
                            if (lastIndex < text.length) {
                                fragment.appendChild(document.createTextNode(text.slice(lastIndex)));
                            }
                            node.parentNode?.replaceChild(fragment, node);
                            return;
                        }

                        if (node.nodeType === Node.ELEMENT_NODE) {
                            const tag = String(node.tagName || '').toLowerCase();
                            if (['script', 'style', 'textarea', 'input', 'select', 'option'].includes(tag)) return;
                        }

                        Array.from(node.childNodes || []).forEach(walk);
                    };

                    Array.from(template.content.childNodes || []).forEach(walk);
                    return template.innerHTML;
                },
                cleanUrl(url) { return String(url || '').trim().replace(/["<>]/g, ''); },
                showNotice(message) {
                    this.editorNotice = String(message || '');
                    if (this.editorNotice) setTimeout(() => { this.editorNotice = ''; }, 4500);
                },
                closeEditorPanel() {
                    this.activePanel = '';
                },
                openLinkPanel() {
                    const selection = String(window.getSelection?.() || '').trim();
                    this.panelLinkLabel = selection || 'Profile link';
                    this.panelLinkUrl = this.mergeToken('ProfileLink');
                    this.activePanel = 'link';
                },
                applyLinkPanel() {
                    const url = this.cleanUrl(this.panelLinkUrl || this.mergeToken('ProfileLink'));
                    const label = String(this.panelLinkLabel || 'Profile link').trim();
                    if (!url || !label) return;
                    this.insertHtml('<a href="' + this.escapeHtml(url) + '" target="_blank">' + this.escapeHtml(label) + '</a>');
                    this.closeEditorPanel();
                },
                openButtonPanel() {
                    this.panelButtonLabel = 'View profile';
                    this.panelButtonUrl = this.mergeToken('ProfileLink');
                    this.activePanel = 'button';
                },
                applyButtonPanel() {
                    const url = this.cleanUrl(this.panelButtonUrl || this.mergeToken('ProfileLink'));
                    const label = String(this.panelButtonLabel || 'View profile').trim();
                    if (!url || !label) return;
                    this.insertHtml('<p><a class="rc-email-button" href="' + this.escapeHtml(url) + '" target="_blank" style="display:block;width:100%;box-sizing:border-box;text-align:center;">' + this.escapeHtml(label) + '</a></p>');
                    this.closeEditorPanel();
                },
                mergeToken(name) { return '{' + '{' + String(name || '').trim() + '}' + '}'; },
                insertHtml(html) {
                    if (!this.restoreSelection()) this.focusEditor();
                    document.execCommand('insertHTML', false, html);
                    this.captureSelection();
                    this.syncNow();
                },
                insertMerge(name) {
                    const token = this.mergeToken(name);
                    this.insertHtml('<span class="rc-merge-token-v48" contenteditable="false">' + this.escapeHtml(token) + '</span>&nbsp;');
                },
                addLink() {
                    this.openLinkPanel();
                },
                addImage() {
                    this.showNotice('Use the Image button to upload an image inside the app.');
                },
                openImageUpload() {
                    if (!this.$refs.imageUpload) {
                        this.addImage();
                        return;
                    }
                    this.$refs.imageUpload.value = '';
                    this.$refs.imageUpload.click();
                },
                uploadInlineImages(event) {
                    const files = Array.from(event.target.files || []);
                    if (!files.length) return;

                    this.uploadingImages = true;
                    const uploadNext = (index = 0) => {
                        if (index >= files.length) {
                            this.uploadingImages = false;
                            event.target.value = '';
                            this.syncNow();
                            return;
                        }

                        const file = files[index];
                        this.$wire.upload('templateInlineImageUpload', file, () => {
                            this.$wire.call('uploadTemplateEditorImage').then((result) => {
                                if (result && result.success && result.url) {
                                    this.insertImage(result.url);
                                } else {
                                    this.showNotice((result && result.error) ? result.error : 'Image upload failed.');
                                }
                                uploadNext(index + 1);
                            }).catch((error) => {
                                console.error(error);
                                this.showNotice('Image upload failed.');
                                uploadNext(index + 1);
                            });
                        }, () => {
                            this.showNotice('Image upload failed.');
                            uploadNext(index + 1);
                        });
                    };

                    uploadNext();
                },
                insertImage(url) {
                    const clean = this.cleanUrl(url);
                    if (!clean) return;
                    this.insertHtml('<p><img src="' + this.escapeHtml(clean) + '" alt="Email image" style="width:100%;max-width:100%;height:auto;display:block;border-radius:12px;" /></p>');
                },
                addTable() {
                    this.insertHtml('<table style="width:100%;border-collapse:collapse;margin:12px 0;"><tr><td style="border:1px solid #e5e7eb;padding:8px;">Label</td><td style="border:1px solid #e5e7eb;padding:8px;">Value</td></tr><tr><td style="border:1px solid #e5e7eb;padding:8px;">School</td><td style="border:1px solid #e5e7eb;padding:8px;">' + this.escapeHtml(this.mergeToken('SchoolName')) + '</td></tr></table>');
                },
                addButton() {
                    this.openButtonPanel();
                }
            };
        };

        window.plyrCampaignBodyEditor = function () {
            return window.plyrNativeEditorBase('campaignBody');
        };

        window.plyrTemplateEditor = function () {
            return {
                showPreview: false,
                mounted: false,
                syncTimer: null,
                uploadingImages: false,
                editorNotice: '',
                activePanel: '',
                panelLinkLabel: '',
                panelLinkUrl: '',
                panelButtonLabel: '',
                panelButtonUrl: '',
                mount() {
                    if (this.mounted) return;
                    this.mounted = true;

                    this.$nextTick(() => this.bootEditor());

                    document.addEventListener('rc-open-template-preview', () => {
                        this.syncNow();
                        this.showPreview = true;
                    });

                    window.addEventListener('rc-template-editor-refresh', (event) => {
                        const encoded = event.detail?.body || '';
                        const html = this.decodeBodyValue(encoded);
                        if (this.$refs.editor && html.trim() !== '') {
                            this.$refs.editor.dataset.initialBody = encoded;
                            this.$refs.editor.innerHTML = this.highlightMergeTokens(html);
                            this.syncNow();
                        }
                    });
                },
                bootEditor(force = false) {
                    if (!this.$refs.editor) return;

                    const current = String(this.$refs.editor.innerHTML || '').trim();
                    const currentLooksEmpty = current === '' || current === '<br>' || current.includes('Write your reusable email template...');
                    const html = this.decodeInitialBody();

                    if (html && (force || currentLooksEmpty)) {
                        this.$refs.editor.innerHTML = this.highlightMergeTokens(html);
                    } else if (current && !current.includes('rc-merge-token-v48')) {
                        this.$refs.editor.innerHTML = this.highlightMergeTokens(current);
                    }

                    this.syncNow();
                },
                decodeBodyValue(initial) {
                    if (!initial) return '';
                    try {
                        return decodeURIComponent(escape(window.atob(initial)));
                    } catch (error) {
                        try { return window.atob(initial); } catch (_) { return ''; }
                    }
                },
                decodeInitialBody() {
                    const initial = this.$refs.editor?.dataset?.initialBody || '';
                    return this.decodeBodyValue(initial);
                },
                queueSync() {
                    clearTimeout(this.syncTimer);
                    this.syncTimer = setTimeout(() => this.syncNow(), 250);
                },
                syncNow() {
                    if (!this.$refs.hidden || !this.$refs.editor) return;

                    const html = this.serializeEditorHtml();
                    this.$refs.hidden.value = html;
                    this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                },
                serializeEditorHtml() {
                    const clone = this.$refs.editor.cloneNode(true);
                    clone.querySelectorAll('.rc-merge-token-v48').forEach((node) => {
                        node.replaceWith(document.createTextNode(node.textContent || ''));
                    });
                    return clone.innerHTML || '';
                },
                highlightMergeTokens(html) {
                    const source = window.plyrRepairBrokenEditorLinkFragments ? window.plyrRepairBrokenEditorLinkFragments(String(html || '')) : String(html || '');
                    if (!source) return '';
                    if (source.includes('rc-merge-token-v48')) return source;

                    const template = document.createElement('template');
                    template.innerHTML = source;
                    const pattern = /\{\{\s*([A-Za-z][A-Za-z0-9_ .]{0,80})\s*\}\}/g;

                    const walk = (node) => {
                        if (node.nodeType === Node.TEXT_NODE) {
                            const text = node.nodeValue || '';
                            if (!pattern.test(text)) {
                                pattern.lastIndex = 0;
                                return;
                            }

                            pattern.lastIndex = 0;
                            const fragment = document.createDocumentFragment();
                            let lastIndex = 0;
                            text.replace(pattern, (match, _name, offset) => {
                                if (offset > lastIndex) {
                                    fragment.appendChild(document.createTextNode(text.slice(lastIndex, offset)));
                                }
                                const span = document.createElement('span');
                                span.className = 'rc-merge-token-v48';
                                span.contentEditable = 'false';
                                span.textContent = match;
                                fragment.appendChild(span);
                                lastIndex = offset + match.length;
                                return match;
                            });
                            if (lastIndex < text.length) {
                                fragment.appendChild(document.createTextNode(text.slice(lastIndex)));
                            }
                            node.parentNode?.replaceChild(fragment, node);
                            return;
                        }

                        if (node.nodeType === Node.ELEMENT_NODE) {
                            const tag = String(node.tagName || '').toLowerCase();
                            if (['script', 'style', 'textarea', 'input', 'select', 'option'].includes(tag)) return;
                        }

                        Array.from(node.childNodes || []).forEach(walk);
                    };

                    Array.from(template.content.childNodes || []).forEach(walk);
                    return template.innerHTML;
                },
                captureSelection() {
                    const editor = this.$refs.editor;
                    const selection = window.getSelection?.();
                    if (!editor || !selection || selection.rangeCount < 1) return;
                    const range = selection.getRangeAt(0);
                    const node = range.commonAncestorContainer;
                    if (node === editor || editor.contains(node.nodeType === Node.ELEMENT_NODE ? node : node.parentNode)) {
                        this.savedSelectionRange = range.cloneRange();
                    }
                },
                restoreSelection() {
                    const editor = this.$refs.editor;
                    const selection = window.getSelection?.();
                    if (!editor || !selection) return false;
                    editor.focus();
                    if (!this.savedSelectionRange) return false;
                    try {
                        selection.removeAllRanges();
                        selection.addRange(this.savedSelectionRange);
                        return true;
                    } catch (_) {
                        this.savedSelectionRange = null;
                        return false;
                    }
                },
                focusEditor() {
                    this.$refs.editor?.focus();
                },
                command(name, value = null) {
                    this.focusEditor();
                    document.execCommand(name, false, value);
                    this.syncNow();
                },
                block(tag) {
                    const safeTag = ['p', 'h1', 'h2', 'h3', 'blockquote'].includes(tag) ? tag : 'p';
                    this.command('formatBlock', safeTag);
                },
                mergeToken(name) {
                    return '{' + '{' + String(name || '').trim() + '}' + '}';
                },
                insertHtml(html) {
                    if (!this.restoreSelection()) this.focusEditor();
                    document.execCommand('insertHTML', false, html);
                    this.captureSelection();
                    this.syncNow();
                },
                insertMerge(name) {
                    const token = this.mergeToken(name);
                    if (!token) return;
                    this.insertHtml('<span class="rc-merge-token-v48" contenteditable="false">' + this.escapeHtml(token) + '</span>&nbsp;');
                },
                mergeTokenFromSelect(event) {
                    const select = event?.target;
                    const value = String(select?.value || '').trim();
                    if (select) select.value = '';
                    if (!value) return '';
                    if (value === '__custom__') {
                        this.showNotice('Inserted a custom value placeholder. Replace "your_value" with the exact field key.');
                        return this.mergeToken('custom_values.your_value');
                    }
                    return this.mergeToken(value);
                },
                insertMergeFromSelect(event) {
                    const token = this.mergeTokenFromSelect(event);
                    if (!token) return;
                    this.insertHtml(this.escapeHtml(token));
                },
                insertFieldMerge(refName, event) {
                    const token = this.mergeTokenFromSelect(event);
                    if (!token) return;
                    const input = this.$refs?.[refName];
                    if (!input) return;
                    input.focus();
                    const start = input.selectionStart ?? input.value.length;
                    const end = input.selectionEnd ?? input.value.length;
                    input.setRangeText(token, start, end, 'end');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                },
                mergeTokenFromSelect(event) {
                    const select = event?.target;
                    const value = String(select?.value || '').trim();
                    if (select) select.value = '';
                    if (!value) return '';
                    if (value === '__custom__') {
                        this.showNotice('Inserted a custom value placeholder. Replace "your_value" with the exact field key.');
                        return this.mergeToken('custom_values.your_value');
                    }
                    return this.mergeToken(value);
                },
                insertMergeFromSelect(event) {
                    const token = this.mergeTokenFromSelect(event);
                    if (!token) return;
                    this.insertHtml(this.escapeHtml(token));
                },
                insertFieldMerge(refName, event) {
                    const token = this.mergeTokenFromSelect(event);
                    if (!token) return;
                    const input = this.$refs?.[refName];
                    if (!input) return;
                    input.focus();
                    const start = input.selectionStart ?? input.value.length;
                    const end = input.selectionEnd ?? input.value.length;
                    input.setRangeText(token, start, end, 'end');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                },
                cleanUrl(url) {
                    return String(url || '').trim().replace(/["<>]/g, '');
                },
                showNotice(message) {
                    this.editorNotice = String(message || '');
                    if (this.editorNotice) setTimeout(() => { this.editorNotice = ''; }, 4500);
                },
                closeEditorPanel() {
                    this.activePanel = '';
                },
                openLinkPanel() {
                    const selection = String(window.getSelection?.() || '').trim();
                    this.panelLinkLabel = selection || 'Profile link';
                    this.panelLinkUrl = this.mergeToken('ProfileLink');
                    this.activePanel = 'link';
                },
                applyLinkPanel() {
                    const url = this.cleanUrl(this.panelLinkUrl || this.mergeToken('ProfileLink'));
                    const label = String(this.panelLinkLabel || 'Profile link').trim();
                    if (!url || !label) return;
                    this.insertHtml('<a href="' + this.escapeHtml(url) + '" target="_blank">' + this.escapeHtml(label) + '</a>');
                    this.closeEditorPanel();
                },
                openButtonPanel() {
                    this.panelButtonLabel = 'View profile';
                    this.panelButtonUrl = this.mergeToken('ProfileLink');
                    this.activePanel = 'button';
                },
                applyButtonPanel() {
                    const url = this.cleanUrl(this.panelButtonUrl || this.mergeToken('ProfileLink'));
                    const label = String(this.panelButtonLabel || 'View profile').trim();
                    if (!url || !label) return;
                    this.insertHtml('<p><a class="rc-email-button" href="' + this.escapeHtml(url) + '" target="_blank" style="display:block;width:100%;box-sizing:border-box;text-align:center;">' + this.escapeHtml(label) + '</a></p>');
                    this.closeEditorPanel();
                },
                escapeHtml(value) {
                    return String(value || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                },
                openImageUpload() {
                    if (!this.$refs.imageUpload) return;
                    this.$refs.imageUpload.value = '';
                    this.$refs.imageUpload.click();
                },
                uploadInlineImages(event) {
                    const files = Array.from(event.target.files || []);
                    if (!files.length) return;

                    this.uploadingImages = true;
                    const uploadNext = (index = 0) => {
                        if (index >= files.length) {
                            this.uploadingImages = false;
                            event.target.value = '';
                            this.syncNow();
                            return;
                        }

                        const file = files[index];
                        this.$wire.upload('templateInlineImageUpload', file, () => {
                            this.$wire.call('uploadTemplateEditorImage').then((result) => {
                                if (result && result.success && result.url) {
                                    this.insertImage(result.url);
                                } else {
                                    this.showNotice((result && result.error) ? result.error : 'Image upload failed.');
                                }
                                uploadNext(index + 1);
                            }).catch((error) => {
                                console.error(error);
                                this.showNotice('Image upload failed.');
                                uploadNext(index + 1);
                            });
                        }, () => {
                            this.showNotice('Image upload failed.');
                            uploadNext(index + 1);
                        });
                    };

                    uploadNext();
                },
                insertImage(url) {
                    const clean = this.cleanUrl(url);
                    if (!clean) return;
                    this.insertHtml('<p><img src="' + this.escapeHtml(clean) + '" style="width:100%;max-width:100%;height:auto;display:block;" alt="" /></p>');
                },
                addLink() {
                    this.openLinkPanel();
                },
                addButton() {
                    this.openButtonPanel();
                },
                addTable() {
                    this.insertHtml('<table style="width:100%;border-collapse:collapse;margin:12px 0;"><tr><td style="border:1px solid #e5e7eb;padding:8px;">Label</td><td style="border:1px solid #e5e7eb;padding:8px;">Value</td></tr><tr><td style="border:1px solid #e5e7eb;padding:8px;">School</td><td style="border:1px solid #e5e7eb;padding:8px;">' + this.escapeHtml(this.mergeToken('SchoolName')) + '</td></tr></table>');
                },
                previewSubject() {
                    return this.$refs.subject?.value || 'Subject preview';
                },
                previewGraphic() {
                    return '';
                },
                previewHtml() {
                    return this.$refs.editor ? this.$refs.editor.innerHTML : '';
                }
            };
        };
    </script>



{{-- v118: restored latest Inbox interaction helpers + instant Compose support. --}}
<style id="rc-inbox-inline-conversation-loader-v82">
    .rc-inbox-mid-loading-host-v82 {
        position: relative !important;
        isolation: isolate;
    }

    .rc-inbox-inline-conversation-loader-v82 {
        position: absolute;
        inset: 0;
        z-index: 80;
        display: none;
        flex-direction: column;
        gap: .9rem;
        padding: 1rem;
        overflow: hidden;
        background: var(--rc-surface, #fff);
        color: var(--rc-text, #111827);
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
    }

    .rc-inbox-inline-loader-head-v82 {
        display: grid;
        grid-template-columns: 2.8rem minmax(0, 1fr);
        align-items: center;
        gap: .75rem;
        padding-bottom: .85rem;
        border-bottom: 1px solid var(--rc-border, #e5e7eb);
    }

    .rc-inbox-inline-loader-avatar-v82,
    .rc-inbox-inline-loader-copy-v82 span,
    .rc-inbox-inline-loader-message-v82 {
        display: block;
        background: linear-gradient(90deg, rgba(148,163,184,.12), rgba(148,163,184,.28), rgba(148,163,184,.12));
        background-size: 220% 100%;
        animation: rcInboxInlineLoadingV82 1.05s ease-in-out infinite;
    }

    .rc-inbox-inline-loader-avatar-v82 {
        width: 2.8rem;
        height: 2.8rem;
        border-radius: .8rem;
    }

    .rc-inbox-inline-loader-copy-v82 {
        display: grid;
        gap: .45rem;
    }

    .rc-inbox-inline-loader-copy-v82 span {
        width: min(24rem, 72%);
        height: .72rem;
        border-radius: 999px;
    }

    .rc-inbox-inline-loader-copy-v82 span:last-child {
        width: min(15rem, 48%);
        height: .58rem;
    }

    .rc-inbox-inline-loader-message-v82 {
        width: 82%;
        min-height: 6.5rem;
        border: 1px solid var(--rc-border, #e5e7eb);
        border-radius: .9rem;
    }

    .rc-inbox-inline-loader-message-v82.is-short {
        width: 58%;
        min-height: 4.75rem;
        margin-left: auto;
    }

    .rc-inbox-inline-loader-message-v82.is-medium {
        width: 70%;
        min-height: 5.5rem;
        margin-left: auto;
    }

    @keyframes rcInboxInlineLoadingV82 {
        from { background-position: 120% 0; }
        to { background-position: -120% 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .rc-inbox-inline-loader-avatar-v82,
        .rc-inbox-inline-loader-copy-v82 span,
        .rc-inbox-inline-loader-message-v82 { animation: none; }
    }
</style>

<style id="rc-compose-overlay-fixes-v82">
    /* Template selection inside Compose must update in-place, never show the legacy page-wide opening skeleton. */
    [data-rc-opening-overlay="compose"],
    [data-rc-opening-overlay="compose-email"],
    .rc-compose-opening-overlay,
    .rc-compose-loading-backdrop,
    .rc-open-loading-overlay[data-rc-type="compose"],
    .rc-open-loading-overlay[data-rc-type="compose-email"] {
        display:none !important;
        visibility:hidden !important;
        pointer-events:none !important;
        opacity:0 !important;
        backdrop-filter:none !important;
        -webkit-backdrop-filter:none !important;
    }

    .rc-compose-preview-backdrop-v82 {
        z-index:2147483200 !important;
        background:rgba(2,6,23,.62) !important;
        backdrop-filter:blur(5px) !important;
        -webkit-backdrop-filter:blur(5px) !important;
    }
</style>

<style id="rc-inbox-quick-reply-v92">
    .rc-inbox-mid-v56 {
        display:flex !important;
        flex-direction:column !important;
        min-height:0 !important;
    }
    .rc-inbox-mid-v56 > .rc-message-stream-v56 {
        min-height:0 !important;
        flex:1 1 auto !important;
        padding-bottom:1rem !important;
    }
    .rc-inbox-quick-reply-v92 {
        position:sticky;
        bottom:0;
        z-index:24;
        flex:0 0 auto;
        border-top:1px solid var(--rc-border);
        background:var(--rc-surface);
        box-shadow:0 -8px 22px rgba(15,23,42,.045);
    }
    .rc-inbox-quick-reply-suggestions-v92 {
        display:flex;
        align-items:center;
        gap:.38rem;
        padding:.55rem .72rem .42rem;
        overflow-x:auto;
        scrollbar-width:none;
    }
    .rc-inbox-quick-reply-suggestions-v92::-webkit-scrollbar { display:none; }
    .rc-inbox-quick-reply-suggestions-v92 button {
        flex:0 0 auto;
        border:1px solid var(--rc-border);
        border-radius:999px;
        padding:.28rem .55rem;
        background:var(--rc-surface);
        color:var(--rc-muted);
        font-size:.62rem;
        line-height:1;
        cursor:pointer;
        transition:border-color .15s ease,color .15s ease,background .15s ease;
    }
    .rc-inbox-quick-reply-suggestions-v92 button:hover {
        border-color:rgba(255,99,56,.38);
        color:#ff6338;
        background:rgba(255,99,56,.055);
    }
    .rc-inbox-quick-reply-tabs-v92 {
        display:flex;
        gap:.75rem;
        padding:0 .72rem;
        border-top:1px solid var(--rc-border);
        border-bottom:1px solid var(--rc-border);
    }
    .rc-inbox-quick-reply-tabs-v92 span {
        position:relative;
        border:0;
        background:transparent;
        padding:.52rem 0 .46rem;
        color:var(--rc-muted);
        font-size:.68rem;
        font-weight:700;
    }
    .rc-inbox-quick-reply-tabs-v92 span.is-active { color:#ff6338; }
    .rc-inbox-quick-reply-tabs-v92 span.is-active::after {
        content:'';
        position:absolute;
        left:0;
        right:0;
        bottom:-1px;
        height:2px;
        border-radius:999px;
        background:#ff6338;
    }
    .rc-inbox-quick-reply-editor-v92 {
        padding:.42rem .72rem .58rem;
        transition:background .15s ease;
    }
    .rc-inbox-quick-reply-editor-v92.is-note { background:rgba(250,204,21,.045); }
    .rc-inbox-quick-reply-toolbar-v92,
    .rc-inbox-quick-reply-tools-v92 {
        display:flex;
        align-items:center;
        gap:.12rem;
    }
    .rc-inbox-quick-reply-toolbar-v92 { padding:0 0 .28rem; }
    .rc-inbox-quick-reply-toolbar-v92 button,
    .rc-inbox-quick-reply-tools-v92 button {
        width:1.55rem;
        height:1.55rem;
        border:0;
        border-radius:.34rem;
        display:grid;
        place-items:center;
        background:transparent;
        color:var(--rc-muted);
        font-size:.69rem;
        cursor:pointer;
    }
    .rc-inbox-quick-reply-toolbar-v92 button:hover,
    .rc-inbox-quick-reply-tools-v92 button:hover {
        color:var(--rc-text);
        background:var(--rc-soft);
    }
    .rc-inbox-quick-reply-divider-v92 {
        width:1px;
        height:1rem;
        margin:0 .14rem;
        background:var(--rc-border);
    }
    .rc-inbox-quick-reply-contenteditable-v93 {
        display:block;
        width:100%;
        min-height:3.15rem;
        max-height:9rem;
        resize:vertical;
        overflow:auto;
        border:1px solid var(--rc-border);
        border-radius:.55rem;
        outline:0;
        padding:.58rem .66rem;
        background:var(--rc-surface);
        color:var(--rc-text);
        font:inherit;
        font-size:.75rem;
        line-height:1.45;
        transition:border-color .15s ease,box-shadow .15s ease;
    }
    .rc-inbox-quick-reply-contenteditable-v93:focus {
        border-color:#ff6338;
        box-shadow:0 0 0 3px rgba(255,99,56,.10);
    }
    .rc-inbox-quick-reply-contenteditable-v93:empty::before {
        content:attr(data-placeholder);
        color:var(--rc-muted);
        pointer-events:none;
    }
    .rc-inbox-quick-reply-contenteditable-v93 p { margin:.15rem 0; }
    .rc-inbox-quick-reply-contenteditable-v93 ul,
    .rc-inbox-quick-reply-contenteditable-v93 ol { margin:.2rem 0 .2rem 1.2rem; }
    .rc-inbox-quick-reply-hidden-v93 { display:none !important; }
    .rc-inbox-quick-reply-footer-v92 {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:.5rem;
        padding-top:.38rem;
    }
    .rc-inbox-quick-reply-actions-v92 {
        display:flex;
        align-items:center;
        gap:.35rem;
    }
    .rc-inbox-quick-reply-send-v92 {
        min-height:1.95rem;
        border-radius:.48rem;
        padding:.36rem .58rem;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:.28rem;
        font-size:.66rem;
        font-weight:750;
        cursor:pointer;
        white-space:nowrap;
    }
    .rc-inbox-quick-reply-send-v92 {
        border:1px solid #ff6338;
        background:#ff6338;
        color:#fff;
        box-shadow:0 4px 10px rgba(255,99,56,.2);
    }
    .rc-inbox-quick-reply-send-v92:hover { background:#f0522b; border-color:#f0522b; }
    .rc-inbox-quick-reply-send-v92:disabled { opacity:.52; cursor:not-allowed; box-shadow:none; }
    .rc-inbox-quick-reply-spinner-v92 {
        width:.78rem;
        height:.78rem;
        border:2px solid rgba(255,255,255,.5);
        border-right-color:#fff;
        border-radius:999px;
        animation:rcInboxQuickReplySpinV92 .7s linear infinite;
    }
    @keyframes rcInboxQuickReplySpinV92 { to { transform:rotate(360deg); } }
    @media (max-width:700px) {
        .rc-inbox-quick-reply-suggestions-v92 { padding-inline:.55rem; }
        .rc-inbox-quick-reply-tabs-v92 { padding-inline:.55rem; }
        .rc-inbox-quick-reply-editor-v92 { padding-inline:.55rem; }
        .rc-inbox-quick-reply-tools-v92 button:nth-child(n+4) { display:none; }
    }

    .rc-inbox-quick-reply-tools-v92 label {
        width:1.75rem;height:1.75rem;display:inline-flex;align-items:center;justify-content:center;
        border:0;border-radius:.4rem;color:#64748b;cursor:pointer;font-size:.9rem;
    }
    .rc-inbox-quick-reply-tools-v92 label:hover { background:#f1f5f9;color:#ff6338; }
    .rc-inbox-quick-reply-uploading-v95 {
        display:none;
        align-items:center;
        gap:.45rem;
        margin:.45rem 0 .15rem;
        padding:.48rem .6rem;
        border:1px solid rgba(255,99,56,.24);
        border-radius:.55rem;
        background:rgba(255,99,56,.07);
        color:#c2410c;
        font-size:.72rem;
        font-weight:650;
    }
    .rc-inbox-quick-reply-upload-spinner-v95,
    .rc-inbox-quick-reply-tool-spinner-v95 {
        display:inline-block;
        width:.85rem;
        height:.85rem;
        border:2px solid rgba(255,99,56,.28);
        border-right-color:#ff6338;
        border-radius:999px;
        animation:rcInboxQuickReplySpinV92 .7s linear infinite;
        flex:0 0 auto;
    }
    .rc-inbox-quick-reply-tool-spinner-v95 { width:.78rem;height:.78rem; }
    .rc-inbox-quick-reply-tools-v92 label.is-uploading { pointer-events:none;opacity:.7; }
    .rc-inbox-quick-reply-attachments-v94 { display:flex;flex-wrap:wrap;gap:.4rem;padding:.5rem 0 .15rem; }
    .rc-inbox-quick-reply-attachment-chip-v95 {
        display:inline-flex;
        align-items:center;
        min-width:0;
        max-width:100%;
        gap:.38rem;
        padding:.36rem .46rem;
        border:1px solid var(--rc-border);
        border-radius:.55rem;
        font-size:.72rem;
        background:var(--rc-soft);
        color:var(--rc-text);
    }
    .rc-inbox-quick-reply-attachment-icon-v95 { flex:0 0 auto;font-size:.8rem; }
    .rc-inbox-quick-reply-attachment-name-v95 {
        min-width:0;
        max-width:15rem;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
        font-weight:650;
    }
    .rc-inbox-quick-reply-attachment-ready-v95 {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:1rem;
        height:1rem;
        border-radius:999px;
        background:#dcfce7;
        color:#15803d;
        font-size:.64rem;
        font-weight:800;
        flex:0 0 auto;
    }
    .rc-inbox-quick-reply-attachments-v94 button {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:1.15rem;
        height:1.15rem;
        border:0;
        border-radius:999px;
        background:transparent;
        color:var(--rc-muted);
        cursor:pointer;
        font-size:1rem;
        line-height:1;
        flex:0 0 auto;
    }
    .rc-inbox-quick-reply-attachments-v94 button:hover { background:rgba(239,68,68,.1);color:#dc2626; }
    .rc-inbox-quick-reply-attachments-v94 button:disabled { opacity:.45;cursor:not-allowed; }

    .rc-inbox-quick-reply-contenteditable-v93 ul,
    .rc-inbox-quick-reply-contenteditable-v93 ol {
        display:block;
        margin:.35rem 0 .35rem 1.4rem;
        padding-left:1.15rem;
    }
    .rc-inbox-quick-reply-contenteditable-v93 ul { list-style:disc outside !important; }
    .rc-inbox-quick-reply-contenteditable-v93 ol { list-style:decimal outside !important; }
    .rc-inbox-quick-reply-contenteditable-v93 li {
        display:list-item !important;
        margin:.15rem 0;
        padding-left:.15rem;
    }
    .rc-inbox-quick-reply-divider-v92 {
        width:1px;
        height:1.05rem;
        background:var(--rc-border);
        margin:0 .15rem;
    }

        .rc-inbox-quick-reply-uploading-v96,
        .rc-inbox-quick-reply-attachment-chip-v96 {
            display:inline-flex;
            align-items:center;
            gap:5px;
            width:max-content;
            max-width:100%;
            min-height:22px;
            padding:3px 7px;
            border:1px solid #d9dee7;
            border-radius:6px;
            background:#f3f4f6;
            color:#596273;
            font-size:11px;
            line-height:1;
        }
        .rc-inbox-quick-reply-uploading-v96 { margin:7px 14px 0; }
        .rc-inbox-quick-reply-attachment-chip-v96 button {
            border:0;
            background:transparent;
            color:#7b8493;
            padding:0;
            width:14px;
            height:14px;
            line-height:12px;
            cursor:pointer;
            font-size:13px;
        }
        .rc-inbox-quick-reply-attachment-icon-v96 { font-size:8px; color:#7b8493; transform:rotate(45deg); }
        .rc-inbox-quick-reply-attachment-name-v96,
        .rc-inbox-quick-reply-upload-name-v96 {
            max-width:220px;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        .rc-inbox-quick-reply-upload-percent-v96 { color:#8b93a1; }
        .rc-inbox-quick-reply-upload-spinner-v96 {
            width:11px;
            height:11px;
            border:2px solid #c9ced8;
            border-top-color:#ff5a43;
            border-radius:999px;
            animation:rcQuickReplySpinV96 .7s linear infinite;
            flex:0 0 auto;
        }
        @keyframes rcQuickReplySpinV96 { to { transform:rotate(360deg); } }

    .rc-account-readiness-shell {
    position: relative;
    min-height: calc(100vh - 8rem);
    isolation: isolate;
}

.rc-account-readiness-content {
    transition:
        filter 0.4s ease,
        opacity 0.4s ease,
        transform 0.4s ease;
}

.rc-account-readiness-shell.is-preparing
.rc-account-readiness-content {
    filter: blur(7px);
    opacity: 0.38;
    transform: scale(0.995);
    pointer-events: none;
    user-select: none;
}

html.rc-account-preparing,
body.rc-account-preparing {
    overflow: hidden !important;
}

body.rc-account-preparing .fi-sidebar,
body.rc-account-preparing .fi-topbar,
body.rc-account-preparing .fi-main-ctn,
body.rc-account-preparing [data-rc-navigation] {
    pointer-events: none !important;
    user-select: none !important;
}

/* Keep the admin impersonation banner and Leave control usable. */
body.rc-account-preparing .rc-account-impersonation-bar,
body.rc-account-preparing .rc-account-impersonation-bar * {
    pointer-events: auto !important;
    user-select: auto !important;
}

body.rc-account-preparing .rc-account-impersonation-bar {
    position: relative;
    z-index: 2147483647 !important;
}

.rc-account-preparation-overlay {
    position: fixed;
    inset: var(--rc-account-overlay-top, 0px) 0 0;
    z-index: 2147483646;
    display: grid;
    place-items: center;
    overflow: hidden;
    overscroll-behavior: contain;
    pointer-events: auto;
    touch-action: none;
    padding: 1.25rem;
    background:
        radial-gradient(
            circle at 50% 40%,
            rgba(255, 99, 56, 0.1),
            transparent 32rem
        ),
        rgba(3, 7, 18, 0.68);
    backdrop-filter: blur(5px);
}

.rc-account-preparation-glow {
    position: absolute;
    width: 24rem;
    height: 24rem;
    border-radius: 999px;
    filter: blur(80px);
    pointer-events: none;
    opacity: 0.24;
    animation: rcAccountPreparationFloat 7s ease-in-out infinite;
}

.rc-account-preparation-glow-one {
    top: 5%;
    left: 12%;
    background: #ff6338;
}

.rc-account-preparation-glow-two {
    right: 9%;
    bottom: 3%;
    background: #3b82f6;
    animation-delay: -3.5s;
}

.rc-account-preparation-card {
    position: relative;
    width: min(34rem, 100%);
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1.4rem;
    background:
        linear-gradient(
            145deg,
            rgba(25, 31, 43, 0.96),
            rgba(12, 17, 27, 0.96)
        );
    box-shadow:
        0 30px 100px rgba(0, 0, 0, 0.52),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
    padding: 1.45rem;
    color: #f8fafc;
}

.rc-account-preparation-card::before {
    content: "";
    position: absolute;
    inset: 0 auto auto 0;
    width: 100%;
    height: 3px;
    background:
        linear-gradient(
            90deg,
            transparent,
            #ff6338,
            #fb923c,
            transparent
        );
    background-size: 220% 100%;
    animation: rcAccountPreparationShimmer 2.1s linear infinite;
}

.rc-account-preparation-brand {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    color: #cbd5e1;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.rc-account-preparation-orbit {
    position: relative;
    width: 2.55rem;
    height: 2.55rem;
    display: grid;
    place-items: center;
    border-radius: 0.8rem;
    background: rgba(255, 99, 56, 0.11);
    color: #ff6338;
}

.rc-account-preparation-orbit > svg {
    width: 1.35rem;
    height: 1.35rem;
}

.rc-account-preparation-orbit-ring {
    position: absolute;
    inset: -0.3rem;
    border: 1px solid rgba(255, 99, 56, 0.22);
    border-radius: 999px;
    animation: rcAccountPreparationRotate 3.8s linear infinite;
}

.rc-account-preparation-orbit-dot {
    position: absolute;
    top: -0.36rem;
    left: 50%;
    width: 0.42rem;
    height: 0.42rem;
    border-radius: 999px;
    background: #ff6338;
    box-shadow: 0 0 14px rgba(255, 99, 56, 0.85);
    transform: translateX(-50%);
}

.rc-account-preparation-copy {
    margin-top: 1.3rem;
}

.rc-account-preparation-kicker {
    display: inline-flex;
    align-items: center;
    min-height: 1.55rem;
    padding: 0.25rem 0.55rem;
    border: 1px solid rgba(255, 99, 56, 0.22);
    border-radius: 999px;
    background: rgba(255, 99, 56, 0.09);
    color: #fb923c;
    font-size: 0.68rem;
    font-weight: 800;
}

.rc-account-preparation-copy h2 {
    margin: 0.8rem 0 0;
    color: #ffffff;
    font-size: clamp(1.45rem, 3vw, 2rem);
    font-weight: 850;
    letter-spacing: -0.035em;
    line-height: 1.08;
}

.rc-account-preparation-copy p {
    margin: 0.7rem 0 0;
    max-width: 29rem;
    color: #94a3b8;
    font-size: 0.86rem;
    line-height: 1.6;
}

.rc-account-preparation-progress {
    height: 0.43rem;
    margin-top: 1.25rem;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.13);
}

.rc-account-preparation-progress span {
    display: block;
    width: 42%;
    height: 100%;
    border-radius: inherit;
    background:
        linear-gradient(
            90deg,
            #ff6338,
            #fb923c,
            #ff6338
        );
    box-shadow: 0 0 18px rgba(255, 99, 56, 0.35);
    animation: rcAccountPreparationProgress 1.8s ease-in-out infinite;
}

.rc-account-preparation-statuses {
    display: grid;
    gap: 0.58rem;
    margin-top: 1.1rem;
}

.rc-account-preparation-status {
    display: grid;
    grid-template-columns: 2.15rem minmax(0, 1fr);
    align-items: center;
    gap: 0.7rem;
    min-height: 3.5rem;
    padding: 0.65rem 0.72rem;
    border: 1px solid rgba(148, 163, 184, 0.11);
    border-radius: 0.8rem;
    background: rgba(255, 255, 255, 0.025);
    color: #64748b;
}

.rc-account-preparation-status.is-complete {
    color: #10b981;
}

.rc-account-preparation-status.is-active {
    border-color: rgba(255, 99, 56, 0.22);
    background: rgba(255, 99, 56, 0.07);
    color: #ff6338;
    animation: rcAccountPreparationActive 1.8s ease-in-out infinite;
}

.rc-account-preparation-status-icon {
    width: 2rem;
    height: 2rem;
    display: grid;
    place-items: center;
    border-radius: 0.62rem;
    background: rgba(148, 163, 184, 0.09);
}

.rc-account-preparation-status-icon svg {
    width: 1.05rem;
    height: 1.05rem;
}

.rc-account-preparation-status strong {
    display: block;
    color: #e2e8f0;
    font-size: 0.76rem;
    line-height: 1.25;
}

.rc-account-preparation-status small {
    display: block;
    margin-top: 0.18rem;
    color: #64748b;
    font-size: 0.67rem;
    line-height: 1.35;
}

.rc-account-preparation-status.is-active small {
    color: #94a3b8;
}

.rc-account-preparation-mini-spinner {
    width: 0.88rem;
    height: 0.88rem;
    border: 2px solid rgba(255, 99, 56, 0.22);
    border-top-color: #ff6338;
    border-right-color: #ff6338;
    border-radius: 999px;
    animation: rcAccountPreparationRotate 0.7s linear infinite;
}

.rc-account-preparation-note {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 0.9rem;
    border-top: 1px solid rgba(148, 163, 184, 0.1);
    color: #94a3b8;
    font-size: 0.7rem;
    font-weight: 650;
}

.rc-account-preparation-pulse {
    width: 0.46rem;
    height: 0.46rem;
    flex: 0 0 auto;
    border-radius: 999px;
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.42);
    animation: rcAccountPreparationPulse 1.7s ease-out infinite;
}

@keyframes rcAccountPreparationRotate {
    to {
        transform: rotate(360deg);
    }
}

@keyframes rcAccountPreparationProgress {
    0% {
        transform: translateX(-115%);
    }

    55% {
        transform: translateX(125%);
    }

    100% {
        transform: translateX(285%);
    }
}

@keyframes rcAccountPreparationShimmer {
    from {
        background-position: 130% 0;
    }

    to {
        background-position: -130% 0;
    }
}

@keyframes rcAccountPreparationFloat {
    0%,
    100% {
        transform: translate3d(0, 0, 0) scale(1);
    }

    50% {
        transform: translate3d(0, -1rem, 0) scale(1.08);
    }
}

@keyframes rcAccountPreparationActive {
    0%,
    100% {
        box-shadow: 0 0 0 rgba(255, 99, 56, 0);
    }

    50% {
        box-shadow: 0 0 24px rgba(255, 99, 56, 0.08);
    }
}

@keyframes rcAccountPreparationPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.38);
    }

    70%,
    100% {
        box-shadow: 0 0 0 0.6rem rgba(16, 185, 129, 0);
    }
}

@media (max-width: 640px) {
    .rc-account-preparation-card {
        padding: 1.05rem;
        border-radius: 1.05rem;
    }

    .rc-account-preparation-overlay {
        padding: 0.75rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .rc-account-preparation-glow,
    .rc-account-preparation-progress span,
    .rc-account-preparation-orbit-ring,
    .rc-account-preparation-mini-spinner,
    .rc-account-preparation-status.is-active,
    .rc-account-preparation-pulse {
        animation: none;
    }
}
</style>

<script id="rc-inbox-editor-link-guard-v91">
    (() => {
        if (window.__rcInboxEditorLinkGuardV91) return;
        window.__rcInboxEditorLinkGuardV91 = true;

        document.addEventListener('click', (event) => {
            const anchor = event.target.closest?.('[contenteditable="true"] a[href]');
            if (!anchor) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            // In an editor, a normal click selects/edits the link instead of leaving
            // the page. Ctrl/Cmd-click is still available when the user wants to open it.
            event.preventDefault();
            event.stopPropagation();
        }, true);
    })();
    </script>

{{-- v90: Inbox viewport-height layout. Keep quick reply/send visible on short screens. --}}
<style id="rc-inbox-viewport-fit-v90">
    @if($section === 'conversations')
    .rc-inbox-page-v56 {
        min-height: 0 !important;
        max-height: none !important;
        overflow: hidden !important;
    }

    .rc-inbox-shell-v56 {
        height: var(--rc-inbox-fit-height, calc(100dvh - 10rem)) !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow: hidden !important;
    }

    .rc-inbox-shell-v56 > *,
    .rc-inbox-left-v56,
    .rc-inbox-mid-v56,
    .rc-inbox-right-v56 {
        min-height: 0 !important;
        max-height: 100% !important;
    }

    .rc-inbox-left-v56 {
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }

    .rc-inbox-left-v56 > .rc-inbox-list-v56,
    .rc-inbox-list-v56 {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
    }

    .rc-inbox-mid-v56 {
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        overflow: hidden !important;
    }

    .rc-inbox-mid-head-v56 {
        flex: 0 0 auto !important;
    }

    .rc-inbox-mid-v56 > .rc-message-stream-v56,
    .rc-message-stream-v56 {
        flex: 1 1 0 !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
    }

    .rc-inbox-quick-reply-v92 {
        position: relative !important;
        inset: auto !important;
        flex: 0 0 auto !important;
        min-height: 0 !important;
        max-height: min(18rem, 42vh) !important;
        overflow-y: auto !important;
        z-index: 24 !important;
        background: var(--rc-surface) !important;
        border-top: 1px solid var(--rc-border) !important;
        box-shadow: 0 -8px 22px rgba(15,23,42,.045) !important;
    }

    .rc-inbox-quick-reply-contenteditable-v93 {
        min-height: 2.75rem !important;
        max-height: min(7rem, 18vh) !important;
    }

    .rc-inbox-quick-reply-footer-v92 {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: var(--rc-surface);
        padding-bottom: .05rem;
    }

    .rc-inbox-right-v56,
    .rc-coach-profile-v56 {
        height: 100% !important;
        min-height: 0 !important;
        overflow-y: auto !important;
    }

    @media (max-height: 760px) and (min-width: 901px) {
        .rc-inbox-panel-head-v56 { padding-top: .55rem !important; padding-bottom: .4rem !important; }
        .rc-inbox-search-v56 { padding-bottom: .4rem !important; }
        .rc-inbox-tabs-v56 { padding-bottom: .45rem !important; }
        .rc-inbox-mid-head-v56 { min-height: 3.7rem !important; padding-block: .45rem !important; }
        .rc-inbox-quick-reply-toolbar-v92 { padding-bottom: .18rem !important; }
        .rc-inbox-quick-reply-editor-v92 { padding-top: .32rem !important; padding-bottom: .4rem !important; }
        .rc-inbox-quick-reply-contenteditable-v93 { min-height: 2.35rem !important; max-height: 4.5rem !important; }
    }

    @media (max-width: 900px) {
        .rc-inbox-page-v56,
        .rc-inbox-shell-v56 {
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
        }
        .rc-inbox-mid-v56 { overflow: visible !important; }
        .rc-message-stream-v56 { max-height: 42rem !important; }
        .rc-inbox-quick-reply-v92 { max-height: none !important; overflow: visible !important; }
    }
    @endif
</style>

@if($section === 'conversations')
<script id="rc-inbox-viewport-fit-script-v90">
(() => {
    if (window.__rcInboxViewportFitV90) {
        window.__rcInboxViewportFitV90();
        return;
    }

    let frame = null;

    const fit = () => {
        if (frame) cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            const shell = document.querySelector('.rc-inbox-shell-v56');
            if (!shell) return;

            if (window.innerWidth <= 900) {
                shell.style.removeProperty('--rc-inbox-fit-height');
                return;
            }

            const top = Math.max(0, shell.getBoundingClientRect().top);
            const bottomGap = 14;
            const available = Math.max(320, Math.floor(window.innerHeight - top - bottomGap));
            shell.style.setProperty('--rc-inbox-fit-height', `${available}px`);
        });
    };

    window.__rcInboxViewportFitV90 = fit;
    window.addEventListener('resize', fit, { passive: true });
    window.addEventListener('orientationchange', fit, { passive: true });
    document.addEventListener('livewire:navigated', fit);
    document.addEventListener('livewire:initialized', fit);

    if (window.Livewire?.hook) {
        window.Livewire.hook('morph.updated', ({ el }) => {
            if (el?.matches?.('.rc-inbox-page-v56, .rc-inbox-shell-v56') || el?.querySelector?.('.rc-inbox-shell-v56')) {
                fit();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fit, { once: true });
    } else {
        fit();
    }
})();
</script>
@endif


{{-- v119 Inbox visual alignment: reference-style compact three-column inbox. --}}
@if($section === 'conversations')
<style id="rc-inbox-reference-v119">
    .rc-section-async-banner { display:none !important; }

    .rc-inbox-page-v56 {
        width:100%;
        min-width:0;
        height:auto !important;
        min-height:0 !important;
        max-height:none !important;
        overflow:visible !important;
        margin-top:.8rem;
    }

    .rc-inbox-shell-v56 {
        --rc-inbox-fit-height: calc(100dvh - 9.75rem);
        width:100%;
        height:var(--rc-inbox-fit-height) !important;
        min-height:34rem !important;
        max-height:none !important;
        display:grid !important;
        grid-template-columns:20.75rem minmax(0,1fr) 21.5rem !important;
        overflow:hidden !important;
        border:1px solid #e4e7ec !important;
        border-radius:1.05rem !important;
        background:#fff !important;
        box-shadow:0 12px 30px rgba(15,23,42,.045) !important;
    }

    .dark .rc-inbox-shell-v56 {
        border-color:var(--rc-border) !important;
        background:var(--rc-surface) !important;
    }

    .rc-inbox-left-v56 {
        display:flex !important;
        flex-direction:column !important;
        min-width:0 !important;
        min-height:0 !important;
        height:100% !important;
        overflow:hidden !important;
        border-right:1px solid #e7e9ee !important;
        background:#fff !important;
    }

    .rc-inbox-panel-head-v56 {
        min-height:3.1rem !important;
        padding:.78rem 1rem .55rem !important;
        display:flex !important;
        align-items:center !important;
        justify-content:space-between !important;
    }
    .rc-inbox-panel-head-v56 h2 {
        margin:0 !important;
        font-size:1rem !important;
        line-height:1.2 !important;
        font-weight:800 !important;
        letter-spacing:-.02em !important;
        color:#111827 !important;
    }
    .rc-inbox-head-actions-v56 { gap:.25rem !important; }
    .rc-inbox-icon-btn-v56 {
        width:2rem !important;
        height:2rem !important;
        border:0 !important;
        border-radius:.55rem !important;
        background:transparent !important;
        color:#667085 !important;
    }
    .rc-inbox-icon-btn-v56:hover { background:#f2f4f7 !important; color:#111827 !important; }

    .rc-inbox-search-v56 { padding:0 .9rem .55rem !important; }
    .rc-inbox-search-v56 label { display:block !important; position:relative !important; }
    .rc-inbox-search-v56 input {
        width:100% !important;
        height:2.38rem !important;
        border:1px solid #dfe3e8 !important;
        border-radius:.72rem !important;
        background:#f8fafc !important;
        color:#111827 !important;
        padding:0 .75rem 0 2rem !important;
        font-size:.78rem !important;
        box-shadow:none !important;
    }
    .rc-inbox-search-v56 svg { left:.62rem !important; width:.95rem !important; height:.95rem !important; color:#98a2b3 !important; }

    .rc-inbox-quick-filters-v56 {
        padding:0 .9rem .62rem !important;
        gap:.38rem !important;
        border-bottom:1px solid #edf0f3 !important;
    }
    .rc-inbox-quick-filters-v56 button {
        min-height:2rem !important;
        padding:.35rem .68rem !important;
        border:1px solid #e1e5ea !important;
        border-radius:999px !important;
        background:#fff !important;
        color:#667085 !important;
        font-size:.7rem !important;
        font-weight:750 !important;
    }
    .rc-inbox-quick-filters-v56 button.is-active {
        border-color:rgba(255,99,56,.42) !important;
        background:#fff5f1 !important;
        color:#ff6338 !important;
    }
    .rc-inbox-quick-filters-v56 button span {
        min-width:1.2rem !important;
        height:1.2rem !important;
        padding:0 .3rem !important;
        background:#ff6338 !important;
        color:#fff !important;
        font-size:.61rem !important;
    }

    .rc-inbox-list-v56 {
        flex:1 1 auto !important;
        min-height:0 !important;
        max-height:none !important;
        overflow-y:auto !important;
        overflow-x:hidden !important;
        background:#fff !important;
    }
    .rc-thread-card-v56 {
        width:100% !important;
        min-height:6.7rem !important;
        padding:.78rem .88rem !important;
        display:grid !important;
        grid-template-columns:2.25rem minmax(0,1fr) auto !important;
        gap:.62rem !important;
        border:0 !important;
        border-left:3px solid transparent !important;
        border-bottom:1px solid #edf0f3 !important;
        border-radius:0 !important;
        background:#fff !important;
        color:#111827 !important;
        text-align:left !important;
        box-shadow:none !important;
        transform:none !important;
    }
    .rc-thread-card-v56:hover { background:#fff8f5 !important; }
    .rc-thread-card-v56.is-selected {
        border-left-color:#ff6338 !important;
        background:#ffe7de !important;
    }
    .rc-thread-logo-v56 {
        width:2.15rem !important;
        height:2.15rem !important;
        border:1px solid #e4e7ec !important;
        border-radius:999px !important;
        background:#fff !important;
        color:#111827 !important;
        font-size:.7rem !important;
    }
    .rc-thread-logo-v56 img { padding:.18rem !important; object-fit:contain !important; }
    .rc-thread-name-v56 { font-size:.8rem !important; font-weight:800 !important; color:#101828 !important; }
    .rc-thread-school-v56 { margin-top:.15rem !important; font-size:.7rem !important; color:#667085 !important; }
    .rc-thread-preview-v56 {
        margin-top:.5rem !important;
        font-size:.7rem !important;
        line-height:1.3 !important;
        color:#7b8495 !important;
        white-space:nowrap !important;
        overflow:hidden !important;
        text-overflow:ellipsis !important;
    }
    .rc-thread-date-v56 { font-size:.68rem !important; color:#667085 !important; }
    .rc-thread-status-v56 {
        margin-top:.4rem !important;
        width:max-content !important;
        padding:.18rem .42rem !important;
        border-radius:.48rem !important;
        background:#fff1cc !important;
        color:#f59e0b !important;
        font-size:.62rem !important;
        font-weight:800 !important;
    }

    .rc-inbox-mid-v56 {
        position:relative !important;
        min-width:0 !important;
        min-height:0 !important;
        height:100% !important;
        display:flex !important;
        flex-direction:column !important;
        overflow:hidden !important;
        background:#fff !important;
    }
    .rc-inbox-mid-head-v56 {
        flex:0 0 auto !important;
        min-height:3.9rem !important;
        padding:.58rem .95rem !important;
        border-bottom:1px solid #e8ebef !important;
        background:#fff !important;
    }
    .rc-inbox-coach-title-v56 { grid-template-columns:2.25rem minmax(0,1fr) !important; gap:.62rem !important; }
    .rc-inbox-school-logo-v56 { width:2.15rem !important; height:2.15rem !important; border-radius:999px !important; }
    .rc-inbox-coach-title-v56 h3 { font-size:.88rem !important; font-weight:800 !important; color:#111827 !important; }
    .rc-inbox-coach-title-v56 p { margin-top:.08rem !important; font-size:.68rem !important; color:#667085 !important; }

    .rc-inbox-inline-conversation-loader-v82 {
        inset:3.95rem 0 0 !important;
        z-index:20 !important;
        padding:1rem !important;
        background:#fff !important;
    }

    .rc-message-stream-v56 {
        flex:1 1 0 !important;
        min-height:0 !important;
        height:auto !important;
        max-height:none !important;
        overflow-y:auto !important;
        overflow-x:hidden !important;
        padding:0 .95rem .8rem !important;
        background:#fff !important;
    }
    .rc-inbox-load-older-top {
        height:2.65rem !important;
        margin:0 -.95rem .7rem !important;
        border-bottom:1px solid #edf0f3 !important;
        background:#fff !important;
    }
    .rc-inbox-open-composer-v56 {
        min-height:1.9rem !important;
        padding:0 .65rem !important;
        border:1px solid #e4e7ec !important;
        border-radius:.65rem !important;
        background:#fff !important;
        color:#344054 !important;
        font-size:.68rem !important;
    }

    .rc-inbox-message-v56 {
        width:100% !important;
        display:grid !important;
        grid-template-columns:2.15rem minmax(0,1fr) !important;
        gap:.58rem !important;
        padding:.1rem 0 .55rem !important;
        border:0 !important;
        background:transparent !important;
        box-shadow:none !important;
    }
    .rc-msg-avatar-v56 {
        width:2rem !important;
        height:2rem !important;
        border-radius:999px !important;
        background:#ff6338 !important;
        color:#fff !important;
        font-size:.67rem !important;
        font-weight:800 !important;
    }
    .rc-msg-meta-v56 {
        margin-bottom:.3rem !important;
        color:#667085 !important;
        font-size:.67rem !important;
    }
    .rc-msg-meta-v56 strong { color:#101828 !important; }
    .rc-msg-bubble-v56,
    .rc-msg-bubble-email-v61 {
        width:min(100%,38rem) !important;
        max-width:100% !important;
        padding:.82rem .9rem !important;
        border:0 !important;
        border-radius:.85rem !important;
        background:#f2f5f9 !important;
        color:#344054 !important;
        font-size:.78rem !important;
        line-height:1.55 !important;
        box-shadow:none !important;
    }
    .rc-inbox-message-v56.is-out .rc-msg-bubble-v56,
    .rc-inbox-message-v56.is-out .rc-msg-bubble-email-v61 {
        background:#f2f5f9 !important;
    }
    .rc-message-status-v56 { margin:.38rem 0 0 2.75rem !important; font-size:.66rem !important; color:#16a34a !important; }

    .rc-inbox-quick-reply-v92 {
        position:relative !important;
        inset:auto !important;
        flex:0 0 auto !important;
        max-height:min(13rem,34vh) !important;
        overflow-y:auto !important;
        z-index:25 !important;
        padding:.45rem .75rem .52rem !important;
        border-top:1px solid #e8ebef !important;
        background:#fff !important;
        box-shadow:0 -5px 18px rgba(15,23,42,.035) !important;
    }
    .rc-inbox-quick-reply-toolbar-v92 {
        min-height:1.85rem !important;
        padding:0 0 .28rem !important;
        gap:.12rem !important;
        background:#fff !important;
    }
    .rc-inbox-quick-reply-toolbar-v92 button {
        min-width:1.8rem !important;
        height:1.8rem !important;
        border:0 !important;
        border-radius:.42rem !important;
        background:transparent !important;
        color:#667085 !important;
        font-size:.7rem !important;
    }
    .rc-inbox-quick-reply-toolbar-v92 button:hover { background:#f2f4f7 !important; }
    .rc-inbox-quick-reply-editor-v92 { padding:0 !important; }
    .rc-inbox-quick-reply-contenteditable-v93 {
        min-height:2.75rem !important;
        max-height:6.5rem !important;
        overflow-y:auto !important;
        padding:.62rem .7rem !important;
        border:1px solid #e1e5ea !important;
        border-radius:.65rem !important;
        background:#fff !important;
        color:#111827 !important;
        font-size:.76rem !important;
        line-height:1.45 !important;
        outline:none !important;
    }
    .rc-inbox-quick-reply-contenteditable-v93:focus {
        border-color:rgba(255,99,56,.55) !important;
        box-shadow:0 0 0 3px rgba(255,99,56,.10) !important;
    }
    .rc-inbox-quick-reply-footer-v92 {
        position:sticky !important;
        bottom:0 !important;
        z-index:2 !important;
        padding-top:.35rem !important;
        background:#fff !important;
    }
    .rc-inbox-quick-reply-footer-v92 .rc-btn-primary,
    .rc-inbox-quick-reply-send-v92 {
        background:#ff6338 !important;
        border-color:#ff6338 !important;
        color:#fff !important;
        box-shadow:0 6px 16px rgba(255,99,56,.18) !important;
    }

    .rc-inbox-right-v56 {
        min-width:0 !important;
        min-height:0 !important;
        height:100% !important;
        overflow:hidden !important;
        border-left:1px solid #e7e9ee !important;
        background:#fff !important;
    }
    .rc-coach-profile-v56 {
        height:100% !important;
        min-height:0 !important;
        overflow-y:auto !important;
        background:#fff !important;
    }
    .rc-coach-cover-v56 {
        height:5.35rem !important;
        display:grid !important;
        place-items:center !important;
        background:#121c2c !important;
        overflow:hidden !important;
    }
    .rc-coach-cover-v56 img {
        max-width:5.5rem !important;
        max-height:3.35rem !important;
        object-fit:contain !important;
        filter:none !important;
    }
    .rc-profile-content-v56 { padding:0 .95rem 1rem !important; }
    .rc-profile-avatar-v56 {
        width:3.45rem !important;
        height:3.45rem !important;
        margin-top:-1.7rem !important;
        border:3px solid #fff !important;
        border-radius:999px !important;
        background:#ff6338 !important;
        color:#fff !important;
        font-size:.78rem !important;
        font-weight:850 !important;
        box-shadow:0 3px 9px rgba(15,23,42,.10) !important;
    }
    .rc-profile-name-v56 { margin-top:.7rem !important; }
    .rc-profile-name-v56 h3 { margin:0 !important; font-size:.88rem !important; font-weight:850 !important; color:#101828 !important; }
    .rc-profile-sub-v56 { margin-top:.12rem !important; font-size:.68rem !important; line-height:1.45 !important; color:#667085 !important; }
    .rc-contact-line-v56 { margin-top:.9rem !important; font-size:.68rem !important; color:#344054 !important; }
    .rc-about-title-v56 { margin-top:1.1rem !important; color:#667085 !important; font-size:.66rem !important; font-weight:850 !important; letter-spacing:.08em !important; text-transform:uppercase !important; }
    .rc-about-grid-v56 { margin-top:.55rem !important; display:grid !important; gap:.68rem !important; grid-template-columns:1fr !important; }
    .rc-about-item-v56 { font-size:.68rem !important; color:#667085 !important; }
    .rc-about-item-v56 strong { display:block !important; margin-bottom:.08rem !important; color:#101828 !important; font-size:.78rem !important; font-weight:800 !important; }

    .dark .rc-inbox-left-v56,
    .dark .rc-inbox-mid-v56,
    .dark .rc-inbox-right-v56,
    .dark .rc-inbox-list-v56,
    .dark .rc-thread-card-v56,
    .dark .rc-inbox-mid-head-v56,
    .dark .rc-message-stream-v56,
    .dark .rc-inbox-load-older-top,
    .dark .rc-inbox-quick-reply-v92,
    .dark .rc-inbox-quick-reply-toolbar-v92,
    .dark .rc-inbox-quick-reply-footer-v92,
    .dark .rc-coach-profile-v56 { background:var(--rc-surface) !important; }
    .dark .rc-thread-card-v56.is-selected { background:rgba(255,99,56,.16) !important; }
    .dark .rc-inbox-panel-head-v56 h2,
    .dark .rc-thread-name-v56,
    .dark .rc-inbox-coach-title-v56 h3,
    .dark .rc-profile-name-v56 h3,
    .dark .rc-about-item-v56 strong { color:var(--rc-text) !important; }
    .dark .rc-msg-bubble-v56,
    .dark .rc-msg-bubble-email-v61 { background:var(--rc-soft) !important; color:var(--rc-text) !important; }

    @media (max-width:1320px) {
        .rc-inbox-shell-v56 { grid-template-columns:19.5rem minmax(0,1fr) !important; }
        .rc-inbox-right-v56 { display:none !important; }
    }
    @media (max-width:900px) {
        .rc-inbox-page-v56,
        .rc-inbox-shell-v56 { height:auto !important; min-height:0 !important; overflow:visible !important; }
        .rc-inbox-shell-v56 { grid-template-columns:1fr !important; }
        .rc-inbox-left-v56 { min-height:24rem !important; border-right:0 !important; }
        .rc-inbox-mid-v56 { min-height:36rem !important; }
        .rc-message-stream-v56 { max-height:42rem !important; }
    }
</style>
@endif



<style id="rc-plan-access-v129">
.rc-plyrcard-preparing-banner-v129 {
    display:grid;
    grid-template-columns:auto minmax(0,1fr) auto;
    align-items:center;
    gap:.85rem;
    margin:0 0 1rem;
    padding:.9rem 1rem;
    border:1px solid rgba(255,99,56,.24);
    border-radius:1rem;
    background:linear-gradient(135deg,rgba(255,99,56,.11),rgba(15,23,42,.04));
    box-shadow:0 10px 28px rgba(15,23,42,.05);
}
.rc-plyrcard-preparing-icon-v129 {
    width:2.35rem;height:2.35rem;border-radius:.78rem;display:grid;place-items:center;
    background:rgba(255,99,56,.13);color:#ff6338;flex:0 0 auto;
}
.rc-plyrcard-preparing-icon-v129 svg { width:1.15rem;height:1.15rem;animation:rcPrepareSpinV129 5s linear infinite; }
.rc-plyrcard-preparing-copy-v129 { min-width:0;display:grid;gap:.18rem; }
.rc-plyrcard-preparing-copy-v129 strong { color:var(--rc-text);font-size:.88rem;font-weight:850; }
.rc-plyrcard-preparing-copy-v129 span { color:var(--rc-muted);font-size:.76rem;line-height:1.45; }
.rc-plyrcard-preparing-action-v129 {
    display:inline-flex;align-items:center;justify-content:center;min-height:2.2rem;padding:.45rem .75rem;
    border:1px solid rgba(255,99,56,.42);border-radius:.7rem;background:#ff6338;color:#fff!important;
    font-size:.74rem;font-weight:800;text-decoration:none!important;white-space:nowrap;
}
@keyframes rcPrepareSpinV129 { to { transform:rotate(360deg); } }

.rc-free-plan-gate-v129 {
    position:fixed;inset:0;z-index:2147483000;display:flex;justify-content:flex-end;
    background:rgba(2,6,23,.72);backdrop-filter:blur(9px);padding:1rem;
    contain:layout paint;isolation:isolate;
}
.rc-free-plan-gate-v129[x-cloak] { display:none !important; }
.rc-free-plan-gate-panel-v129 {
    width:min(38rem,100%);height:100%;overflow:auto;border-radius:1.35rem;background:#fff;color:#111827;
    box-shadow:-28px 0 80px rgba(0,0,0,.36);display:flex;flex-direction:column;
}
.rc-free-plan-gate-head-v129 {
    position:relative;padding:2rem 2.1rem 1.7rem;background:linear-gradient(135deg,#ff6338,#ff4f3e);color:#fff;
}
.rc-free-plan-gate-badge-v129 { display:inline-flex;align-items:center;gap:.35rem;padding:.32rem .65rem;border:1px solid rgba(255,255,255,.58);border-radius:999px;font-size:.7rem;font-weight:900;letter-spacing:.04em;text-transform:uppercase; }
.rc-free-plan-gate-head-v129 h2 { margin:1.2rem 0 .45rem;font-size:1.35rem;line-height:1.15;font-weight:900;letter-spacing:-.03em; }
.rc-free-plan-gate-head-v129 p { margin:0;max-width:30rem;font-size:.86rem;line-height:1.5;color:rgba(255,255,255,.94); }
.rc-free-plan-gate-close-v129 { position:absolute;top:1rem;right:1rem;width:2.45rem;height:2.45rem;border:0;border-radius:.75rem;background:rgba(255,255,255,.16);color:#fff;font-size:1.35rem;cursor:pointer; }
.rc-free-plan-gate-body-v129 { padding:1.6rem 2rem;display:grid;gap:1rem; }
.rc-free-plan-gate-point-v129 { display:grid;grid-template-columns:1.5rem minmax(0,1fr);gap:.65rem;color:#5b6472;font-size:.86rem;line-height:1.45; }
.rc-free-plan-gate-check-v129 { width:1.5rem;height:1.5rem;border-radius:999px;display:grid;place-items:center;background:#dcfce7;color:#10b981;font-weight:900; }
.rc-free-plan-gate-footer-v129 { margin-top:auto;padding:1rem 2rem 1.35rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.65rem; }
.rc-free-plan-gate-secondary-v129,.rc-free-plan-gate-primary-v129 { min-height:2.75rem;padding:.65rem 1.1rem;border-radius:.8rem;font-size:.8rem;font-weight:850;text-decoration:none!important;display:inline-flex;align-items:center;justify-content:center; }
.rc-free-plan-gate-secondary-v129 { border:1px solid #d9dee7;background:#fff;color:#1f2937;cursor:pointer; }
.rc-free-plan-gate-primary-v129 { border:1px solid #ff6338;background:#ff6338;color:#fff!important;cursor:pointer;font-family:inherit; }
@media(max-width:700px){
    .rc-plyrcard-preparing-banner-v129{grid-template-columns:auto minmax(0,1fr)}
    .rc-plyrcard-preparing-action-v129{grid-column:1/-1}
    .rc-free-plan-gate-v129{padding:0}
    .rc-free-plan-gate-panel-v129{border-radius:0}
}
@media(prefers-reduced-motion:reduce){.rc-plyrcard-preparing-icon-v129 svg{animation:none}}
</style>

@if($isFreePlanAccount ?? false)
    {{-- v10.30: This gate is deliberately client-only. It is always present for a
         Free account and Alpine only toggles visibility. Do not use wire:click or
         conditionally mount/unmount it; either behavior causes a Livewire morph of
         the very large Recruiting Center component and makes the slider flicker. --}}
    <div
        class="rc-free-plan-gate-v129"
        role="presentation"
        x-cloak
        x-show="freeGateOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click.self="closeFreeGate()"
        wire:ignore
    >
        <section
            class="rc-free-plan-gate-panel-v129"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rc-free-plan-gate-title-v130"
            x-on:click.stop
            x-transition:enter="transition ease-out duration-180"
            x-transition:enter-start="translate-x-6 opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-120"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-6 opacity-0"
        >
            <header class="rc-free-plan-gate-head-v129">
                <button type="button" class="rc-free-plan-gate-close-v129" x-on:click="closeFreeGate()" aria-label="Close">×</button>
                <span class="rc-free-plan-gate-badge-v129">My Journey</span>
                <h2 id="rc-free-plan-gate-title-v130"><span x-text="freeGateNames[freeGateSection] || 'This feature'"></span> is a My Journey feature</h2>
                <p>Your Free plan includes Edit Profile and Settings. Upgrade to My Journey to unlock the recruiting workspace and outreach tools.</p>
            </header>
            <div class="rc-free-plan-gate-body-v129">
                <div class="rc-free-plan-gate-point-v129"><span class="rc-free-plan-gate-check-v129">✓</span><span>Discover Schools, Favorites, Lists, Compose Email, Inbox, Schedule, outreach, and analytics are available with My Journey.</span></div>
                <div class="rc-free-plan-gate-point-v129"><span class="rc-free-plan-gate-check-v129">✓</span><span>You can keep editing your athlete profile and account settings on Free at any time.</span></div>
            </div>
            <footer class="rc-free-plan-gate-footer-v129">
                <button type="button" class="rc-free-plan-gate-secondary-v129" x-on:click="closeFreeGate()">Not now</button>
                <button type="button" class="rc-free-plan-gate-primary-v129" data-plyrcard-amplify-open>Upgrade to Amplify</button>
            </footer>
        </section>
    </div>
@endif

<script data-navigate-once>
(() => {
    if (window.__plyrRcPersistentNavInstalled) return;
    window.__plyrRcPersistentNavInstalled = true;

    const pathToSection = (pathname) => {
        const path = String(pathname || '').replace(/\/+$/, '') || '/';
        if (!path.startsWith('/admin/coach-database')) return null;
        if (path === '/admin/coach-database') return 'dashboard';
        if (/\/(schools)$/.test(path)) return 'schools';
        if (/\/(coaches)$/.test(path)) return 'coaches';
        if (/\/(favorites)$/.test(path)) return 'favorites';
        if (/\/(lists)$/.test(path)) return 'lists';
        if (/\/(templates|campaigns)$/.test(path)) return 'campaigns';
        if (/\/(compose-email|compose)$/.test(path)) return 'compose';
        if (/\/(conversations|inbox)$/.test(path)) return 'conversations';
        if (/\/(schedule|my-schedule)$/.test(path)) return 'schedule';
        if (/\/(settings)$/.test(path)) return 'settings';
        if (/\/(support)$/.test(path)) return 'support';
        return null;
    };

    const sectionFromAnchor = (anchor) => {
        try {
            const url = new URL(anchor.href, window.location.href);
            if (url.origin !== window.location.origin) return null;
            return pathToSection(url.pathname);
        } catch (_) {
            return null;
        }
    };

    const currentRoot = () => document.querySelector('.rc-livewire-root');
    const freePlanLockedSections = new Set(['dashboard','schools','coaches','favorites','lists','conversations','campaigns','compose','support','schedule']);
    const isFreePlan = () => currentRoot()?.dataset?.rcFreePlan === '1';

    const openFreePlanGate = (section) => {
        if (!isFreePlan() || !freePlanLockedSections.has(section)) return false;
        window.dispatchEvent(new CustomEvent('rc-free-plan-gate', { detail: { section } }));
        return true;
    };

    const setSidebarActive = (section) => {
        document.querySelectorAll('.fi-sidebar a[href]').forEach((anchor) => {
            const candidate = sectionFromAnchor(anchor);
            const active = candidate === section;
            anchor.classList.toggle('rc-fast-active', active);
            if (active) anchor.setAttribute('aria-current', 'page');
            else if (anchor.getAttribute('aria-current') === 'page') anchor.removeAttribute('aria-current');
        });
    };

    const switchSection = (section, href = null, replace = false) => {
        const root = currentRoot();
        if (!root || !section) return false;

        // Update navigation feedback before the Livewire request even starts.
        root.dataset.rcCurrentSection = section;
        setSidebarActive(section);

        if (href) {
            const target = new URL(href, window.location.href);
            const historyFn = replace ? 'replaceState' : 'pushState';
            window.history[historyFn]({ ...(window.history.state || {}), rcSection: section }, '', target.pathname + target.search + target.hash);
        }

        window.dispatchEvent(new CustomEvent('rc-fast-section', { detail: { section } }));
        return true;
    };

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const anchor = event.target?.closest?.('.fi-sidebar a[href], a[data-rc-fast-nav][href]');
        if (!anchor) return;

        const section = sectionFromAnchor(anchor);
        if (!section || !currentRoot()) return;

        event.preventDefault();
        event.stopPropagation();

        // Free plan: do not mutate history and do not ask Livewire to switch to a
        // restricted section. Open the upgrade slider in place instead.
        if (openFreePlanGate(section)) return;

        switchSection(section, anchor.href, false);
    }, true);

    window.addEventListener('popstate', () => {
        const section = pathToSection(window.location.pathname);
        if (!section || !currentRoot()) return;
        if (openFreePlanGate(section)) return;
        switchSection(section, null, true);
    });

    document.addEventListener('livewire:init', () => {
        if (window.Livewire?.on) {
            Livewire.on('rc-section-switched', ({ section } = {}) => {
                if (section) setSidebarActive(section);
            });
            Livewire.on('rc-fast-section-ready', ({ section } = {}) => {
                if (section === 'conversations') {
                    window.dispatchEvent(new CustomEvent('rc-fast-inbox-refresh', { detail: { section } }));
                }
            });
        }
    }, { once: true });

    const initial = pathToSection(window.location.pathname);
    if (initial) setSidebarActive(initial);
})();
</script>

<style data-navigate-once>
.fi-sidebar a.rc-fast-active,
.fi-sidebar a.rc-fast-active:hover {
    background: rgba(255, 99, 56, .14) !important;
    color: #ff6338 !important;
}
.fi-sidebar a.rc-fast-active svg {
    color: #ff6338 !important;
}
</style>

    @include('partials.amplify-upgrade-modal')
</x-filament-panels::page>