<x-filament-panels::page>
    <div class="rc-livewire-root" wire:init="bootDeferredUiData">
        @include('filament.partials.coach-database-ui-shell')

    <script>
        /*
         * Discover Schools Alpine bootstrap.
         *
         * This runs before Alpine parses the school markup, so production asset
         * timing, CDN caching, or deferred JavaScript can never leave x-data with
         * an incomplete fallback object.
         */
        (() => {
            const stores = window.__rcDiscoverSelectionStores || new Map();
            window.__rcDiscoverSelectionStores = stores;

            const key = () => window.location.pathname;
            const getStore = () => stores.get(key()) || null;

            const announce = (store) => {
                window.dispatchEvent(new CustomEvent('rc-discover-selection-changed', {
                    detail: {
                        selected: Array.from(store?.selected || []),
                        count: store?.selected?.size || 0,
                        allFilteredSelected: Boolean(store?.allFilteredSelected),
                        version: Number(store?.version || 0),
                    },
                }));

                window.dispatchEvent(new CustomEvent('rc-discover-selection-refresh', {
                    detail: { version: Number(store?.version || 0) },
                }));
            };

            const toast = (message, type = 'error') => {
                const text = String(message || '').trim();
                if (!text) return;

                const root = document.querySelector('.rc-livewire-root');
                const owner = root?.closest('[wire\\:id]');
                const id = owner?.getAttribute('wire:id');
                const instance = id && window.Livewire?.find ? window.Livewire.find(id) : null;

                if (instance?.call) {
                    instance.call(
                        'notifyRecruitingUi',
                        text,
                        type === 'error' ? 'danger' : type
                    ).catch(() => {});
                } else {
                    console[type === 'error' ? 'error' : 'log'](text);
                }
            };

            if (typeof window.rcDiscoverSelection !== 'function') {
                window.rcDiscoverSelection = (initialIds = [], initialAllFilteredSelected = false, initialFilteredIds = []) => {
                    let store = stores.get(key());

                    if (!store) {
                        store = {
                            selected: new Set((initialIds || []).map((id) => String(id))),
                            allFilteredSelected: Boolean(initialAllFilteredSelected),
                            dirty: false,
                            inFlight: false,
                            needsFlush: false,
                            timer: null,
                            version: 0,
                            filteredIds: new Set((initialFilteredIds || []).map((id) => String(id))),
                        };
                        stores.set(key(), store);
                    }

                    store.filteredIds = new Set((initialFilteredIds || []).map((id) => String(id)));
                    store.allFilteredSelected = store.filteredIds.size > 0
                        && Array.from(store.filteredIds).every((id) => store.selected.has(id));

                    return {
                        revision: Number(store.version || 0),
                        allFilteredSelected: Boolean(store.allFilteredSelected),
                        selectionListener: null,

                        init() {
                            this.revision = Number(store.version || 0);
                            this.allFilteredSelected = Boolean(store.allFilteredSelected);

                            this.selectionListener = (event) => {
                                this.revision = Number(
                                    event?.detail?.version
                                    ?? store.version
                                    ?? (this.revision + 1)
                                );
                                this.allFilteredSelected = Boolean(store.allFilteredSelected);
                            };

                            window.addEventListener(
                                'rc-discover-selection-refresh',
                                this.selectionListener
                            );

                            this.$cleanup?.(() => {
                                window.removeEventListener(
                                    'rc-discover-selection-refresh',
                                    this.selectionListener
                                );
                            });
                        },

                        count() {
                            this.revision;
                            return store.selected.size;
                        },

                        selectedIds() {
                            this.revision;
                            return Array.from(store.selected);
                        },

                        isSelected(id) {
                            this.revision;
                            return store.selected.has(String(id));
                        },

                        updateLocal(ids, allSelected = null) {
                            store.selected = new Set((ids || []).map((id) => String(id)));

                            if (allSelected !== null) {
                                store.allFilteredSelected = Boolean(allSelected);
                            }

                            store.version = Number(store.version || 0) + 1;
                            this.revision = store.version;
                            this.allFilteredSelected = Boolean(store.allFilteredSelected);
                            announce(store);
                        },

                        toggle(id) {
                            const normalized = String(id || '');
                            if (!normalized) return;

                            if (store.selected.has(normalized)) {
                                store.selected.delete(normalized);
                            } else {
                                store.selected.add(normalized);
                            }

                            store.allFilteredSelected = false;
                            store.dirty = true;
                            store.needsFlush = true;
                            store.version = Number(store.version || 0) + 1;
                            this.revision = store.version;
                            this.allFilteredSelected = false;
                            announce(store);

                            // Keep checkbox interactions entirely client-side.
                            // Selection is sent only for Add to List or Email.
                        },

                        async flush() {
                            // Selection is intentionally browser-local for speed.
                            return true;
                        },

                        toggleAllFiltered() {
                            const ids = Array.from(store.filteredIds || []);
                            if (!ids.length) return;

                            const allSelected = ids.every((id) => store.selected.has(id));

                            if (allSelected) {
                                ids.forEach((id) => store.selected.delete(id));
                            } else {
                                ids.forEach((id) => store.selected.add(id));
                            }

                            store.allFilteredSelected = !allSelected;
                            store.version = Number(store.version || 0) + 1;
                            this.revision = store.version;
                            this.allFilteredSelected = store.allFilteredSelected;
                            announce(store);
                        },

                        async clearAll() {
                            this.updateLocal([], false);
                            store.dirty = false;
                            store.needsFlush = false;
                        },

                        async emailSelected() {
                            await this.flush();

                            try {
                                await this.$wire.call(
                                'emailSchoolIds',
                                Array.from(store.selected)
                            );
                            } catch (error) {
                                console.error(error);
                                toast(
                                    'Unable to open Compose Email for the selected schools.'
                                );
                            }
                        },
                    };
                };
            }


            window.__rcDiscoverClientCacheVersion = '2026-07-15.7-grid-view-restored';
            window.rcDiscoverClientCache = (initialSchools = [], initialLists = [], initialView = 'grid') => ({
                schools: [],
                lists: Array.isArray(initialLists) ? initialLists : [],
                search: '',
                division: '',
                conference: '',
                viewMode: initialView === 'list' ? 'list' : 'grid',
                limit: 48,
                selected: new Set(),
                listMenuOpen: false,
                creating: false,
                newListName: '',
                newListColor: '#ff6338',
                pendingListKey: '',
                revision: 0,

                init() {
                    const rows = Array.isArray(initialSchools) ? initialSchools : [];
                    this.schools = rows.map((row) => ({
                        ...row,
                        id: String(row?.id || row?.business_id || ''),
                        business_id: String(row?.business_id || row?.id || ''),
                        name: String(row?.name || 'Unnamed School'),
                        division: String(row?.division || ''),
                        conference: String(row?.conference || ''),
                        logo_url: String(row?.logo_url || ''),
                        coach_count: Number(row?.coach_count || 0),
                        head_coach_name: String(row?.head_coach_name || '—'),
                        head_coach_title: String(row?.head_coach_title || 'Coach'),
                        head_coach_email: String(row?.head_coach_email || ''),
                        search_text: String(row?.search_text || '').toLowerCase(),
                    })).filter((row) => row.id !== '');

                    try {
                        sessionStorage.setItem('rcDiscoverSchools:' + window.location.pathname, JSON.stringify({
                            cachedAt: @js($cachedAt ?? null),
                            rows: this.schools,
                        }));
                    } catch (_) {}
                },

                get divisionTabs() {
                    return ['', 'NCAA D-I', 'NCAA D-II', 'NCAA D-III', 'NAIA', 'NJCAA'];
                },

                normalize(value) {
                    return String(value || '').trim().toLowerCase();
                },

                divisionMatches(value, filter) {
                    if (!filter) return true;
                    const current = this.normalize(value).replace(/division/g, '').replace(/ncaa/g, '').replace(/[^a-z0-9]+/g, '');
                    const wanted = this.normalize(filter).replace(/division/g, '').replace(/ncaa/g, '').replace(/[^a-z0-9]+/g, '');
                    return current === wanted || current.includes(wanted) || wanted.includes(current);
                },

                get conferenceOptions() {
                    return [...new Set(this.schools
                        .filter((row) => this.divisionMatches(row.division, this.division))
                        .map((row) => row.conference)
                        .filter(Boolean))]
                        .sort((a, b) => a.localeCompare(b));
                },

                initialsFor(name) {
                    return String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase() || 'S';
                },
                shortDivision(value) {
                    const division = String(value || '').trim();
                    if (!division) return '—';
                    return division.replace('NCAA Division ', 'D').replace('NCAA D-', 'D').replace('Division ', 'D');
                },
                get filteredSchools() {
                    const query = this.normalize(this.search);
                    const conference = this.normalize(this.conference);
                    return this.schools.filter((row) => {
                        if (!this.divisionMatches(row.division, this.division)) return false;
                        if (conference && this.normalize(row.conference) !== conference) return false;
                        if (query && !row.search_text.includes(query)) return false;
                        return true;
                    });
                },

                get visibleSchools() {
                    return this.filteredSchools.slice(0, this.limit);
                },

                get canLoadMore() {
                    return this.limit < this.filteredSchools.length;
                },

                get selectedCount() {
                    this.revision;
                    return this.selected.size;
                },

                get allFilteredSelected() {
                    this.revision;
                    const rows = this.filteredSchools;
                    return rows.length > 0 && rows.every((row) => this.selected.has(row.id));
                },

                setDivision(value) {
                    this.division = this.division === value ? '' : value;
                    this.conference = '';
                    this.limit = 48;
                },

                setConference(value) {
                    this.conference = value;
                    this.limit = 48;
                },

                setViewMode(mode) {
                    this.viewMode = mode === 'list' ? 'list' : 'grid';
                    this.limit = Math.max(48, Number(this.limit || 48));
                },

                setSearch(value) {
                    this.search = value;
                    this.limit = 48;
                },

                toggleSchool(id) {
                    id = String(id || '');
                    if (!id) return;
                    if (this.selected.has(id)) this.selected.delete(id);
                    else this.selected.add(id);
                    this.revision++;
                },

                isSelected(id) {
                    this.revision;
                    return this.selected.has(String(id || ''));
                },

                toggleAllFiltered() {
                    const rows = this.filteredSchools;
                    if (!rows.length) return;
                    const remove = rows.every((row) => this.selected.has(row.id));
                    rows.forEach((row) => remove ? this.selected.delete(row.id) : this.selected.add(row.id));
                    this.revision++;
                },

                clearSelection() {
                    this.selected.clear();
                    this.revision++;
                    this.listMenuOpen = false;
                },

                loadMore() {
                    this.limit += 48;
                },

                async openSchool(id) {
                    try {
                        await this.$wire.call('openSchoolDashboardModal', String(id));
                    } catch (error) {
                        console.error(error);
                        toast('Unable to open this school.');
                    }
                },

                async emailSelected() {
                    if (!this.selected.size) return;
                    try {
                        await this.$wire.call('emailSchoolIds', Array.from(this.selected));
                    } catch (error) {
                        console.error(error);
                        toast('Unable to open Compose Email for the selected schools.');
                    }
                },

                isPending(key) {
                    return this.pendingListKey === String(key || '');
                },

                async addToList(listKey, listLabel) {
                    if (!this.selected.size || this.pendingListKey) return;
                    const ids = Array.from(this.selected);
                    this.pendingListKey = String(listKey || '');
                    try {
                        const result = await this.$wire.call('queueSchoolIdsToList', ids, this.pendingListKey);
                        if (!result || result.success === false) {
                            toast(result?.error || 'Unable to save the selected schools.');
                            return;
                        }
                        const row = this.lists.find((item) => String(item?.key || '') === this.pendingListKey);
                        if (row) row.count = Number(row.count || 0) + Number(result.updated_schools || ids.length);
                        this.clearSelection();
                        toast(result.message || `${ids.length.toLocaleString()} school(s) added to ${listLabel}.`, 'success');
                    } catch (error) {
                        console.error(error);
                        toast('Unable to save the selected schools.');
                    } finally {
                        this.pendingListKey = '';
                    }
                },

                async createQuickList() {
                    const name = String(this.newListName || '').trim();
                    if (!name || this.creating) return;
                    this.creating = true;
                    try {
                        const result = await this.$wire.call('createCustomListQuick', name, this.newListColor);
                        if (!result || result.success === false || !result.list) {
                            toast(result?.error || 'Unable to create the list.');
                            return;
                        }
                        this.lists.push({
                            ...result.list,
                            count: Number(result.list?.count || result.list?.schools_count || 0),
                        });
                        this.newListName = '';
                        toast(result.message || 'List created.', 'success');
                    } catch (error) {
                        console.error(error);
                        toast('Unable to create the list.');
                    } finally {
                        this.creating = false;
                    }
                },
            });

            window.__rcLocalListControllerVersion = '2026-07-15.4-responsive';
            window.rcBulkSchoolListLocalV2 = (initialLists = []) => ({
                    open: false,
                    creating: false,
                    newListName: '',
                    newListColor: '#ff6338',
                    lists: Array.isArray(initialLists) ? [...initialLists] : [],
                    pendingLists: {},
                    pendingAdds: {},
                    statusText: '',
                    watchTimer: null,
                    watching: false,
                    selectedCount: getStore()?.selected?.size || 0,
                    selectionListener: null,

                    init() {
                        this.selectedCount = getStore()?.selected?.size || 0;

                        this.selectionListener = (event) => {
                            this.selectedCount = Number(
                                event?.detail?.count
                                ?? getStore()?.selected?.size
                                ?? 0
                            );
                        };

                        window.addEventListener(
                            'rc-discover-selection-changed',
                            this.selectionListener
                        );

                        this.$cleanup?.(() => {
                            window.removeEventListener(
                                'rc-discover-selection-changed',
                                this.selectionListener
                            );
                        });
                    },

                    count() {
                        return Number(this.selectedCount || 0);
                    },

                    isPending(listKey) {
                        return Boolean(this.pendingLists[String(listKey || '')]);
                    },

                    pendingCount() {
                        return Object.keys(this.pendingLists).length;
                    },

                    async syncSelection() {
                        const store = getStore();
                        if (!store) return true;

                        try {
                            const result = await this.$wire.call(
                                'setSelectedSchoolIds',
                                Array.from(store.selected)
                            );
                            return Boolean(result?.success !== false);
                        } catch (error) {
                            console.error(error);
                            return false;
                        }
                    },

                    async clearAll() {
                        const store = getStore();

                        if (store) {
                            store.selected.clear();
                            store.allFilteredSelected = false;
                            store.version = Number(store.version || 0) + 1;
                            announce(store);
                        }

                        this.selectedCount = 0;

                        try {
                            const result = await this.$wire.call(
                                'clearSelectedSchools'
                            );

                            if (!result || result.success === false) {
                                toast(
                                    result?.error
                                    || 'Unable to clear selected schools.'
                                );
                            }
                        } catch (error) {
                            console.error(error);
                            toast('Unable to clear selected schools.');
                        }
                    },

                    async createQuickList() {
                        const name = String(this.newListName || '').trim();
                        if (!name || this.creating) return;

                        this.creating = true;

                        try {
                            const result = await this.$wire.call(
                                'createCustomListQuick',
                                name,
                                this.newListColor
                            );

                            if (!result || result.success === false || !result.list) {
                                toast(result?.error || 'Unable to create the list.');
                                return;
                            }

                            const created = {
                                ...result.list,
                                count: Number(
                                    result.list?.count
                                    ?? result.list?.schools_count
                                    ?? 0
                                ),
                            };

                            if (!this.lists.some(
                                (list) => String(list?.key || '')
                                    === String(created.key || '')
                            )) {
                                this.lists.push(created);
                            }

                            this.newListName = '';
                            this.newListColor = '#ff6338';
                            toast(result.message || 'List created.', 'success');
                        } catch (error) {
                            console.error(error);
                            toast('Unable to create the list.');
                        } finally {
                            this.creating = false;
                        }
                    },

                    async queue(listKey, listLabel, selectedCount) {
                        const listKeyValue = String(listKey || '').trim();
                        if (!listKeyValue) return;

                        const actualCount = getStore()?.selected?.size
                            || Number(selectedCount || 0);
                        const label = String(listLabel || listKeyValue);

                        this.pendingLists = {
                            ...this.pendingLists,
                            [listKeyValue]: label,
                        };
                        this.pendingAdds = {
                            ...this.pendingAdds,
                            [listKeyValue]: Number(actualCount || 0),
                        };
                        this.statusText = `Saving ${Number(actualCount).toLocaleString()} selected school(s) to ${label}...`;
                        this.open = true;

                        try {
                            const result = await this.$wire.call(
                                'queueSchoolIdsToList',
                                Array.from(getStore()?.selected || []),
                                listKeyValue
                            );

                            if (!result || result.success === false) {
                                const nextPending = { ...this.pendingLists };
                                const nextAdds = { ...this.pendingAdds };
                                delete nextPending[listKeyValue];
                                delete nextAdds[listKeyValue];
                                this.pendingLists = nextPending;
                                this.pendingAdds = nextAdds;
                                this.statusText = this.pendingCount()
                                    ? this.statusText
                                    : '';
                                toast(
                                    result?.error
                                    || 'Unable to queue the selected schools.'
                                );
                                return;
                            }

                            const savedCount = Number(
                                result.updated_schools
                                || result.school_count
                                || actualCount
                                || 0
                            );

                            const nextPending = { ...this.pendingLists };
                            const nextAdds = { ...this.pendingAdds };
                            delete nextPending[listKeyValue];
                            delete nextAdds[listKeyValue];
                            this.pendingLists = nextPending;
                            this.pendingAdds = nextAdds;
                            this.statusText = '';

                            const listRow = this.lists.find(
                                (item) => String(item?.key || '') === listKeyValue
                            );
                            if (listRow) {
                                listRow.count = Number(listRow.count || 0) + savedCount;
                            }

                            this.open = false;
                            this.clearSelectionLocally();

                            toast(
                                result.message
                                || `${savedCount.toLocaleString()} school(s) added to ${result.list_label || label}.`,
                                'success'
                            );
                        } catch (error) {
                            console.error(error);
                            const nextPending = { ...this.pendingLists };
                            const nextAdds = { ...this.pendingAdds };
                            delete nextPending[listKeyValue];
                            delete nextAdds[listKeyValue];
                            this.pendingLists = nextPending;
                            this.pendingAdds = nextAdds;
                            this.statusText = '';
                            toast('Unable to queue the selected schools.');
                        }
                    },

                    clearSelectionLocally() {
                        const store = getStore();
                        if (store?.selected instanceof Set) {
                            store.selected.clear();
                            store.allFilteredSelected = false;
                            store.version = Number(store.version || 0) + 1;
                            announce(store);
                        }

                        this.$dispatch('rc-discover-selection-cleared');
                    },

                });
        })();
    </script>

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


        .rc-sync-monitor {
            --rc-sync-tone: #2563eb;
            --rc-sync-soft: rgba(37, 99, 235, .10);
            position: relative;
            display: grid;
            gap: .8rem;
            margin-bottom: 1rem;
            padding: 1rem 1.05rem;
            overflow: hidden;
            border: 1px solid color-mix(in srgb, var(--rc-sync-tone) 24%, var(--rc-border));
            border-radius: 1rem;
            background: color-mix(in srgb, var(--rc-sync-soft) 42%, var(--rc-card));
            box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
        }

        .rc-sync-monitor--success { --rc-sync-tone: #059669; --rc-sync-soft: rgba(5, 150, 105, .10); }
        .rc-sync-monitor--warning { --rc-sync-tone: #d97706; --rc-sync-soft: rgba(217, 119, 6, .10); }
        .rc-sync-monitor--danger { --rc-sync-tone: #dc2626; --rc-sync-soft: rgba(220, 38, 38, .10); }
        .rc-sync-monitor--neutral { --rc-sync-tone: #64748b; --rc-sync-soft: rgba(100, 116, 139, .10); }

        .rc-sync-monitor__head {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .8rem;
            align-items: start;
        }

        .rc-sync-monitor__signal {
            position: relative;
            width: 2.35rem;
            height: 2.35rem;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: .75rem;
            color: var(--rc-sync-tone);
            background: var(--rc-sync-soft);
        }

        .rc-sync-monitor__signal::before {
            content: '';
            width: .65rem;
            height: .65rem;
            border-radius: 999px;
            background: currentColor;
            box-shadow: 0 0 0 0 color-mix(in srgb, currentColor 28%, transparent);
            animation: rcSyncHeartbeat 1.65s ease-out infinite;
        }

        .rc-sync-monitor--danger .rc-sync-monitor__signal::before {
            animation: none;
        }

        .rc-sync-monitor__title-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .55rem;
            margin-bottom: .2rem;
        }

        .rc-sync-monitor__title {
            color: var(--rc-text);
            font-size: .92rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .rc-sync-monitor__badge {
            display: inline-flex;
            align-items: center;
            min-height: 1.45rem;
            padding: .2rem .5rem;
            border-radius: 999px;
            background: var(--rc-sync-soft);
            color: var(--rc-sync-tone);
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .01em;
        }

        .rc-sync-monitor__stage {
            color: var(--rc-text);
            font-size: .8rem;
            font-weight: 650;
            line-height: 1.45;
        }

        .rc-sync-monitor__message {
            margin-top: .15rem;
            color: var(--rc-muted);
            font-size: .75rem;
            line-height: 1.45;
        }

        .rc-sync-monitor__percent {
            min-width: 3.25rem;
            color: var(--rc-sync-tone);
            font-size: 1rem;
            font-weight: 850;
            text-align: right;
        }

        .rc-sync-monitor__bar {
            position: relative;
            height: .55rem;
            overflow: hidden;
            border-radius: 999px;
            background: color-mix(in srgb, var(--rc-sync-tone) 10%, var(--rc-soft));
        }

        .rc-sync-monitor__bar > span {
            position: relative;
            display: block;
            height: 100%;
            min-width: .5rem;
            border-radius: inherit;
            background: var(--rc-sync-tone);
            transition: width .3s ease;
        }

        .rc-sync-monitor__bar.is-indeterminate > span {
            width: 38% !important;
            min-width: 6rem;
            animation: rcSyncIndeterminate 1.2s ease-in-out infinite;
        }

        .rc-sync-monitor__meta {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .rc-sync-monitor__meta-item {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            min-height: 1.75rem;
            padding: .28rem .55rem;
            border: 1px solid color-mix(in srgb, var(--rc-border) 85%, transparent);
            border-radius: .55rem;
            background: color-mix(in srgb, var(--rc-card) 80%, transparent);
            color: var(--rc-muted);
            font-size: .7rem;
            font-weight: 650;
        }

        .rc-sync-monitor__meta-item strong {
            color: var(--rc-text);
            font-weight: 800;
        }

        .rc-sync-monitor__hint {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .75rem;
            padding: .65rem .75rem;
            border-radius: .7rem;
            background: var(--rc-sync-soft);
            color: color-mix(in srgb, var(--rc-sync-tone) 82%, var(--rc-text));
            font-size: .73rem;
            font-weight: 650;
            line-height: 1.45;
        }

        .rc-sync-monitor__action {
            flex: 0 0 auto;
            border: 1px solid currentColor;
            border-radius: .5rem;
            background: transparent;
            padding: .32rem .55rem;
            color: inherit;
            font: inherit;
            cursor: pointer;
        }

        @keyframes rcSyncHeartbeat {
            0% { box-shadow: 0 0 0 0 color-mix(in srgb, currentColor 30%, transparent); }
            70%, 100% { box-shadow: 0 0 0 .65rem transparent; }
        }

        @keyframes rcSyncIndeterminate {
            0% { transform: translateX(-115%); }
            55% { transform: translateX(110%); }
            100% { transform: translateX(285%); }
        }

        @media (max-width: 700px) {
            .rc-sync-monitor__head {
                grid-template-columns: auto minmax(0, 1fr);
            }
            .rc-sync-monitor__percent {
                grid-column: 2;
                text-align: left;
            }
            .rc-sync-monitor__hint {
                flex-direction: column;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .rc-sync-monitor__signal::before,
            .rc-sync-monitor__bar.is-indeterminate > span {
                animation: none;
            }
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
        .rc-profile-actions-v56 { display:grid; grid-template-columns:1fr 1fr; gap:.55rem; margin:1rem 0; }
        .rc-profile-action-v56 { min-height:3.55rem; border:1px solid var(--rc-border); border-radius:.8rem; background:var(--rc-surface); color:var(--rc-text); display:grid; place-items:center; gap:.25rem; font-size:.78rem; font-weight:700; cursor:pointer; }
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
            width: 100%;
            display: grid;
            grid-template-columns: 2.35rem minmax(0,1fr) auto;
            gap: .6rem;
            align-items: center;
            border: 0;
            background: transparent;
            padding: 0;
            text-align: left;
            font: inherit;
            cursor: pointer;
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


        .rc-engagement-filter-card {
            width:100%;
            border:1px solid transparent;
            text-align:left;
            cursor:pointer;
            font:inherit;
            position:relative;
            transition:border-color .16s ease, box-shadow .16s ease, transform .16s ease;
        }
        .rc-engagement-filter-card:hover { transform:translateY(-1px); }
        .rc-engagement-filter-card.is-filter-active {
            border-color:#ff6338 !important;
            box-shadow:0 0 0 2px rgba(255,99,56,.08), 0 12px 30px rgba(15,23,42,.08);
        }
        .rc-engagement-filter-note {
            grid-column:1 / -1;
            width:100%;
            margin-top:.65rem;
            padding-top:.65rem;
            border-top:1px solid rgba(148,163,184,.18);
            color:#ff6338;
            font-size:.72rem;
            font-weight:750;
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

        .rc-brand-icon-img {
            width: 1.2rem;
            height: 1.2rem;
            display: block;
            object-fit: contain;
        }

        .rc-detail-platform-icon-v2 .rc-brand-icon-img {
            width: 1.35rem;
            height: 1.35rem;
        }

        @media (min-width: 981px) {
            .rc-stats-drawer-panel .rc-detail-stats-v2.is-two {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 980px) {
            .rc-stats-drawer-panel .rc-detail-stats-v2.is-two {
                grid-template-columns: 1fr !important;
            }
        }
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


        .rc-discover-select-v27.is-updating { opacity:.72; cursor:progress; }
        .rc-discover-tab-v27 { transition: background-color .12s ease, color .12s ease, border-color .12s ease; }
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


        // Public PNG icons from Icons8's CDN. These replace missing local files
        // under /images/recruiting-center/icons and avoid SVG-only brand marks.
        $publicIconUrls = [
            'cap' => 'https://img.icons8.com/fluency/48/graduation-cap.png',
            'eye' => 'https://img.icons8.com/fluency/48/visible.png',
            'star' => 'https://img.icons8.com/fluency/48/star.png',
            'mail' => 'https://img.icons8.com/fluency/48/secured-letter.png',
            'chart' => 'https://img.icons8.com/fluency/48/sent.png',
            'profile' => 'https://img.icons8.com/fluency/48/user-male-circle.png',
            'website' => 'https://img.icons8.com/fluency/48/domain.png',
            'email' => 'https://img.icons8.com/fluency/48/new-post.png',
            'instagram' => 'https://img.icons8.com/fluency/48/instagram-new.png',
            'youtube' => 'https://img.icons8.com/color/48/youtube-play.png',
            'x' => 'https://img.icons8.com/ios-filled/50/x.png',
            'link' => 'https://img.icons8.com/fluency/48/link.png',
        ];

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
        x-data
        x-init="window.initCoachDatabasePage && window.initCoachDatabasePage($wire)"
    >
        @include('filament.partials.coach-database-sync-status')

        @if($error)
            <div class="rc-card"><strong>{{ $error }}</strong></div>
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
                    >
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6v5h-5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M19.2 11A7.6 7.6 0 1 0 17 16.35" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div class="rc-refresh-menu-v2" x-cloak x-show="open" x-transition.origin.top.right>
                        <button type="button" class="rc-refresh-menu-item-v2" wire:click="refreshStatsOnly" x-on:click="open = false">
                            <span class="rc-refresh-menu-icon-v2"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19V5M4 19h16M8 16v-5M13 16V8M18 16v-8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span class="rc-refresh-menu-copy-v2"><strong>Reload stats only</strong><small>Refresh email activity, profile views, and social clicks.</small></span>
                        </button>
                        <button type="button" class="rc-refresh-menu-item-v2" wire:click="refreshCoachDatabase" x-on:click="open = false">
                            <span class="rc-refresh-menu-icon-v2"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M8 4v4M16 10v4M11 16v4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg></span>
                            <span class="rc-refresh-menu-copy-v2"><strong>Reload whole Coach Database</strong><small>Reload schools, coaches, logos, tags, filters, and dashboard stats.</small></span>
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
                $dashboardMostInterestedSchools = collect($this->dashboardMostInterestedSchools ?? [])->take(5)->values()->all();
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
                $trackedProfileTotal = $trackedProfileComponentTotal;
                $profileViews = $trackedProfileTotal;

                $emailSentCount = max((int) ($dashboardMetrics['email_sent_count'] ?? 0), (int) ($dashboardMetrics['emails_sent'] ?? 0), (int) ($dashboardMetrics['personal_emails_sent'] ?? 0) + (int) ($dashboardMetrics['campaigns_sent'] ?? 0));
                $emailOpenCount = (int) ($dashboardMetrics['email_open_count'] ?? $dashboardMetrics['email_opens'] ?? 0);
                $emailClickCount = (int) ($dashboardMetrics['email_click_count'] ?? $dashboardMetrics['email_clicks'] ?? 0);
                $socialClickCount = (int) ($dashboardMetrics['website_click_count'] ?? 0)
                    + (int) ($dashboardMetrics['instagram_click_count'] ?? 0)
                    + (int) ($dashboardMetrics['youtube_click_count'] ?? 0)
                    + (int) ($dashboardMetrics['x_click_count'] ?? 0);
                $emailsSent = $emailSentCount;

                $coachReplies = (int) ($dashboardMetrics['coach_replies'] ?? 0);
                $engagedSchools = (int) ($dashboardMetrics['engaged_schools'] ?? count($dashboardTopSchools));
                $coachEngagementTotal = $trackedWebsiteViews
                    + $trackedInstagramViews
                    + $trackedYoutubeViews
                    + $trackedXViews
                    + $trackedEmailLinkViews
                    + $emailClickCount
                    + $socialClickCount
                    + $emailOpenCount
                    + $coachReplies;

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
                        'sub' => 'Tracked profile activity',
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
                        'url' => $this->pageUrl('favorites'),
                    ],
                    [
                        'label' => 'Coach Engagement',
                        'value' => number_format($coachEngagementTotal),
                        'sub' => 'Tracked opens, clicks, replies',
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
                        'school_id' => (string) ($activity['school_id'] ?? ''),
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

                $interestedSchoolRows = collect($dashboardMostInterestedSchools)->take(4)->values()->map(function ($school, $rank) {
                    $schoolName = (string) ($school['name'] ?? 'School');
                    $views = max(0, (int) ($school['profile_views'] ?? 0));
                    $engagementClicks = max(0, (int) ($school['interest_clicks'] ?? 0));
                    $initials = collect(explode(' ', $schoolName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');

                    return [
                        'rank' => $rank + 1,
                        'id' => (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim($schoolName)))),
                        'name' => $schoolName,
                        'score' => $views,
                        'engagement_clicks' => $engagementClicks,
                        'initials' => strtoupper($initials ?: 'S'),
                        'logo_url' => trim((string) ($school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? '')),
                    ];
                })->filter(fn (array $row): bool => (int) ($row['score'] ?? 0) > 0 || (int) ($row['engagement_clicks'] ?? 0) > 0)->values();
            @endphp

            <div class="rc-home-dashboard-v2">
                @include('filament.partials.coach-database-header', [
                    'firstName' => $firstName,
                    'placeholder' => 'Search schools, coaches, conferences, divisions, lists...',
                    'showNewEmail' => true,
                ])

                <div class="rc-home-stats-v2">
                    @foreach($quickStats as $stat)
                        @if(! empty($stat['target']))
                            <button
                                type="button"
                                class="rc-home-stat-v2 is-{{ $stat['tone'] }} is-clickable"
                                x-on:click="$dispatch('rc-open-{{ $stat['target'] }}')"
                                data-rc-stat-open="{{ $stat['target'] }}"
                                data-rc-no-pending
                            >
                        @elseif(! empty($stat['url']))
                            <a
                                class="rc-home-stat-v2 is-{{ $stat['tone'] }} is-clickable"
                                href="{{ $stat['url'] }}"
                                wire:navigate
                            >
                        @else
                            <div class="rc-home-stat-v2 is-{{ $stat['tone'] }}">
                        @endif
                            <div class="rc-home-stat-icon-v2">
                                <img
                                    class="rc-public-png-icon"
                                    src="{{ $publicIconUrls[$stat['icon']] ?? $publicIconUrls['link'] }}"
                                    alt=""
                                    width="48"
                                    height="48"
                                    loading="eager"
                                    referrerpolicy="no-referrer"
                                >
                            </div>

                            <div class="rc-home-stat-copy-v2">
                                <div class="rc-home-stat-label-v2">{{ $stat['label'] }}</div>
                                <div class="rc-home-stat-value-v2">{{ $stat['value'] }}</div>
                            </div>

                            @if(isset($stat['progress']))
                                <div class="rc-home-progress-v2">
                                    <span style="width: {{ (int) $stat['progress'] }}%"></span>
                                </div>
                            @endif

                            <div class="rc-home-stat-sub-v2">{{ $stat['sub'] }}</div>
                        @if(! empty($stat['target']))
                            </button>
                        @elseif(! empty($stat['url']))
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>

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
                            <a
                                href="#"
                                x-on:click.prevent="$dispatch('rc-open-profile-views')"
                                data-rc-stat-open="profile-views"
                                data-rc-no-pending
                            >View All</a>
                        </div>

                        <div class="rc-home-activity-list-v2">
                            @foreach($dashboardActivityRows as $activityRow)
                                @if(! empty($activityRow['school_id']))
                                    <button type="button" class="rc-home-activity-v2" wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from((string) $activityRow['school_id']) }})">
                                @else
                                    <a class="rc-home-activity-v2" href="{{ $activityRow['url'] ?? '#' }}">
                                @endif
                                    <span class="rc-home-activity-icon-v2 is-{{ $activityRow['tone'] ?? 'blue' }}">{{ $activityRow['icon'] ?? '◉' }}</span>

                                    <span class="rc-home-activity-copy-v2">
                                        <strong>{{ $activityRow['title'] ?? 'Recruiting activity' }}</strong>
                                        <small>{{ $activityRow['copy'] ?? 'Recruiting update' }}</small>
                                    </span>

                                    <span class="rc-home-activity-time-v2">{{ $activityRow['time_label'] ?? 'Recent' }}</span>
                                @if(! empty($activityRow['school_id']))
                                    </button>
                                @else
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </section>
                </div>

                <div class="rc-home-lower-grid-v2">
                    <section class="rc-home-panel-v2 rc-radar-panel-v2">
                        <div class="rc-home-panel-head-v2">
                            <div>
                                <h2>On The Radar</h2>
                                <p>Based on your profile and preferences</p>
                            </div>
                            <a href="{{ $this->pageUrl('lists') }}">View All</a>
                        </div>

                        <div class="rc-radar-schools-v2">
                            @foreach($radarSchoolRows as $radarSchool)
                                <button type="button" class="rc-radar-card-v2" wire:click="openSchoolDashboardModal(@js($radarSchool['id']))">
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
                            <span>Total tracked profile views</span>
                        </div>

                        <div class="rc-interested-list-v2">
                            @forelse($interestedSchoolRows as $interestedSchool)
                                <button type="button" class="rc-interested-row-v2" wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from((string) $interestedSchool['id']) }})">
                                    <span class="rc-interested-rank-v2">{{ $interestedSchool['rank'] }}</span>
                                    <span class="rc-interested-logo-v2 {{ empty($interestedSchool['logo_url']) ? 'is-missing-logo' : '' }}">
                                        @if(! empty($interestedSchool['logo_url']))
                                            <img src="{{ $interestedSchool['logo_url'] }}" alt="{{ $interestedSchool['name'] }} logo" loading="lazy" onerror="this.closest('.rc-interested-logo-v2').classList.add('is-missing-logo')">
                                        @endif
                                        <span class="rc-logo-fallback-text">{{ $interestedSchool['initials'] }}</span>
                                    </span>
                                    <span>
                                        <strong>{{ $interestedSchool['name'] }}</strong>
                                        <small>Profile views from this school</small>
                                    </span>
                                    <b>{{ number_format((int) $interestedSchool['score']) }}</b>
                                </button>
                            @empty
                                <div class="rc-home-empty-v2">School interest will appear after identified coach contacts view your profile or interact with your recruiting links.</div>
                            @endforelse
                        </div>

                        <button
                            type="button"
                            class="rc-home-outline-btn-v2"
                            x-on:click="$dispatch('rc-open-coach-engagement')"
                            data-rc-stat-open="coach-engagement"
                            data-rc-no-pending
                        >View Full Analytics</button>
                    </section>
                </div>
            </div>
        @endif

        @if(in_array($section, ['dashboard', 'profile-views'], true))
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $profileViewRows = collect($this->profileViewRows ?? [])->values();
                $profileViewsTotal = max(
                    (int) ($dashboardMetrics['view_profile_total'] ?? $dashboardMetrics['profile_views'] ?? 0),
                    (int) $profileViewRows->sum('views'),
                );
            @endphp

            <div class="rc-stats-drawer-backdrop rc-ui-stable-modal"
                wire:key="stats-drawer-profile-views"
                data-rc-modal="stats"
                data-rc-modal-id="profile-views"
                x-data="window.rcStatsDrawer ? window.rcStatsDrawer('profile-views', @js($section === 'profile-views'), @js($section !== 'dashboard')) : { open: @js($section === 'profile-views'), openDrawer() { this.open = true }, close() { this.open = false } }"
                x-init="init && init()"
                x-on:rc-open-profile-views.window="openDrawer()"
                x-show="open"
                x-cloak
                x-on:keydown.escape.window="if ($el.classList.contains('rc-stack-top')) { window.rcCloseOverlayNow($el); close(); }"
                x-on:click.self="if ($el.classList.contains('rc-stack-top')) { window.rcCloseOverlayNow($el); close(); }">
                <aside class="rc-stats-drawer-panel rc-ui-stable-panel"
                    data-rc-interaction-boundary
                    role="dialog"
                    aria-modal="true"
                    x-show="open"
                    x-on:click.stop>
                    <button type="button" class="rc-stats-drawer-close" data-rc-instant-close x-on:pointerdown.prevent.stop="window.rcCloseOverlayNow($el); close()" x-on:click.prevent.stop aria-label="Close details">×</button>
                    <div class="rc-detail-page-v2">
                <div class="rc-detail-header-v2">
                    <div>
                        <h1>Profile Views</h1>
                        <p>Tracked profile views from website, Instagram, YouTube, X, and email links.</p>
                    </div>
                    <form class="rc-detail-search-v2" wire:submit.prevent="$set('section', 'schools')">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <input type="search" placeholder="Search schools, coaches, conferences, divisions, lists..." wire:model.live.debounce.180ms="search">

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

                <div class="rc-detail-stats-v2 is-two">
                    <div class="rc-detail-stat-v2 is-blue"><span><img class="rc-brand-icon-img" src="{{ $publicIconUrls['profile'] }}" alt=""></span><div><small>Total Views</small><strong>{{ number_format($profileViewsTotal) }}</strong><em>{{ number_format(max(0, $profileViewsTotal)) }} tracked views</em></div></div>
                    <div class="rc-detail-stat-v2 is-coral"><span><img class="rc-brand-icon-img" src="{{ $publicIconUrls['website'] }}" alt=""></span><div><small>Coach Contacts</small><strong>{{ number_format(max(0, $profileViewRows->count())) }}</strong><em>Viewed your profile</em></div></div>
                </div>

                <section class="rc-detail-table-v2">
                    <header><h2>Who's Viewing You</h2><span>● Synced</span></header>
                    <div class="rc-detail-rows-v2">
                        @forelse($profileViewRows as $profileRow)
                            <button type="button" class="rc-detail-row-v2" wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from((string) ($profileRow['school_id'] ?? '')) }})">
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
            window.rcFilterCoachEngagement = function (source, platform) {
                const normalized = ['instagram', 'youtube', 'x'].includes(String(platform || '').toLowerCase())
                    ? String(platform).toLowerCase()
                    : '';
                const drawer = source?.closest?.('[data-rc-modal-id="coach-engagement"]')
                    || document.querySelector('[data-rc-modal-id="coach-engagement"]');

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
        </script>

        <style>
            [data-rc-modal-id="coach-engagement"] [data-engagement-row][hidden] {
                display: none !important;
            }
        </style>

        @if(in_array($section, ['dashboard', 'coach-engagement'], true))
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $dashboardRecentActivity = collect($this->dashboardRecentActivity ?? [])->values();

                $websiteClicks = (int) ($dashboardMetrics['website_click_count'] ?? $dashboardMetrics['website_clicks'] ?? 0);
                $xClicks = (int) ($dashboardMetrics['x_click_count'] ?? $dashboardMetrics['x_clicks'] ?? $dashboardMetrics['twitter_clicks'] ?? 0);
                $igClicks = (int) ($dashboardMetrics['instagram_click_count'] ?? $dashboardMetrics['instagram_clicks'] ?? 0);
                $ytClicks = (int) ($dashboardMetrics['youtube_click_count'] ?? $dashboardMetrics['youtube_clicks'] ?? 0);
                $emailLinkClicks = (int) ($dashboardMetrics['view_profile_email_link'] ?? 0);
                $emailClicks = (int) ($dashboardMetrics['email_click_count'] ?? $dashboardMetrics['email_clicks'] ?? $dashboardMetrics['Click count'] ?? 0);
                $emailOpens = (int) ($dashboardMetrics['email_open_count'] ?? $dashboardMetrics['email_opens'] ?? $dashboardMetrics['Open count'] ?? 0);
                $coachReplies = (int) ($dashboardMetrics['coach_replies'] ?? 0);

                $normalizeSocialPlatform = static function (array $row): string {
                    $raw = strtolower(trim((string) (
                        $row['platform_icon_key']
                        ?? $row['platform']
                        ?? $row['source']
                        ?? $row['channel']
                        ?? $row['type']
                        ?? ''
                    )));

                    return match (true) {
                        str_contains($raw, 'instagram'),
                        $raw === 'ig' => 'instagram',

                        str_contains($raw, 'youtube'),
                        str_contains($raw, 'you_tube'),
                        $raw === 'yt' => 'youtube',

                        $raw === 'x',
                        str_contains($raw, 'twitter'),
                        str_contains($raw, 'x.com') => 'x',

                        default => '',
                    };
                };

                // Coach Engagement must contain social click activity only.
                // Website clicks, email clicks/opens, profile-link clicks, and
                // unknown activity types are removed before rendering.
                $coachEngagementRows = collect($this->coachEngagementRows ?? [])
                    ->filter(fn ($row): bool => is_array($row))
                    ->map(function (array $row) use ($normalizeSocialPlatform) {
                        $canonical = $normalizeSocialPlatform($row);

                        if ($canonical === '') {
                            return null;
                        }

                        return array_merge($row, [
                            'platform' => match ($canonical) {
                                'instagram' => 'Instagram',
                                'youtube' => 'YouTube',
                                'x' => 'X (Twitter)',
                            },
                            'platform_icon_key' => $canonical,
                            'platform_class' => match ($canonical) {
                                'instagram' => 'is-pink',
                                'youtube' => 'is-red',
                                'x' => 'is-neutral',
                            },
                        ]);
                    })
                    ->filter()
                    ->values();

                if ($coachEngagementRows->isEmpty()) {
                    $coachEngagementRows = $dashboardRecentActivity
                        ->filter(fn ($row): bool => is_array($row))
                        ->map(function (array $row) use ($normalizeSocialPlatform, $formatActivityTimeLabel) {
                            $canonical = $normalizeSocialPlatform($row);

                            if ($canonical === '') {
                                return null;
                            }

                            $time = $row['time'] ?? $row['created_at'] ?? null;

                            return [
                                'title' => (string) ($row['title'] ?? 'Tracked coach engagement'),
                                'copy' => trim(strip_tags((string) ($row['copy'] ?? 'Social click activity'))) ?: 'Social click activity',
                                'school_id' => (string) ($row['school_id'] ?? ''),
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
                                'platform_icon_key' => $canonical,
                                'clicks' => max(1, (int) ($row['clicks'] ?? $row['count'] ?? 1)),
                                'time_label' => $formatActivityTimeLabel($time),
                            ];
                        })
                        ->filter()
                        ->take(20)
                        ->values();
                }
            @endphp

            <div class="rc-stats-drawer-backdrop rc-ui-stable-modal"
                wire:key="stats-drawer-coach-engagement"
                data-rc-modal="stats"
                data-rc-modal-id="coach-engagement"
                x-data="window.rcStatsDrawer ? window.rcStatsDrawer('coach-engagement', @js($section === 'coach-engagement'), @js($section !== 'dashboard')) : { open: @js($section === 'coach-engagement'), openDrawer() { this.open = true }, close() { this.open = false } }"
                x-init="init && init()"
                x-on:rc-open-coach-engagement.window="openDrawer()"
                x-show="open"
                x-cloak
                x-on:keydown.escape.window="if ($el.classList.contains('rc-stack-top')) { window.rcCloseOverlayNow($el); close(); }"
                x-on:click.self="if ($el.classList.contains('rc-stack-top')) { window.rcCloseOverlayNow($el); close(); }">
                <aside class="rc-stats-drawer-panel rc-ui-stable-panel"
                    data-rc-interaction-boundary
                    role="dialog"
                    aria-modal="true"
                    x-show="open"
                    x-on:click.stop>
                    <button type="button" class="rc-stats-drawer-close" data-rc-instant-close x-on:pointerdown.prevent.stop="window.rcCloseOverlayNow($el); close()" x-on:click.prevent.stop aria-label="Close details">×</button>
                    <div class="rc-detail-page-v2" data-engagement-platform="">
                <div class="rc-detail-header-v2">
                    <div>
                        <h1>Coach Engagement</h1>
                        <p>How coaches are engaging with your social platforms, and who's clicking through.</p>
                    </div>
                    <form class="rc-detail-search-v2" wire:submit.prevent="$set('section', 'schools')">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <input type="search" placeholder="Search schools, coaches, conferences..." wire:model.live.debounce.350ms="search">
                    </form>
                </div>

                <div class="rc-detail-stats-v2">
                    <button type="button" class="rc-detail-stat-v2 is-neutral rc-engagement-filter-card" data-engagement-filter="x" aria-pressed="false" onclick="window.rcFilterCoachEngagement(this, 'x')">
                        <span><img class="rc-brand-icon-img" src="{{ $publicIconUrls['x'] }}" alt="X"></span>
                        <div><small>X (Twitter)</small><strong>{{ number_format($xClicks) }}</strong><em>{{ number_format(max(0, $xClicks)) }} clicks</em></div>
                    </button>
                    <button type="button" class="rc-detail-stat-v2 is-pink rc-engagement-filter-card" data-engagement-filter="instagram" aria-pressed="false" onclick="window.rcFilterCoachEngagement(this, 'instagram')">
                        <span><img class="rc-brand-icon-img" src="{{ $publicIconUrls['instagram'] }}" alt="Instagram"></span>
                        <div><small>Instagram</small><strong>{{ number_format($igClicks) }}</strong><em>{{ number_format(max(0, $igClicks)) }} clicks</em></div>
                    </button>
                    <button type="button" class="rc-detail-stat-v2 is-red rc-engagement-filter-card" data-engagement-filter="youtube" aria-pressed="false" onclick="window.rcFilterCoachEngagement(this, 'youtube')">
                        <span><img class="rc-brand-icon-img" src="{{ $publicIconUrls['youtube'] }}" alt="YouTube"></span>
                        <div><small>YouTube</small><strong>{{ number_format($ytClicks) }}</strong><em>{{ number_format(max(0, $ytClicks)) }} clicks</em></div>
                    </button>
                </div>

                <section class="rc-detail-table-v2">
                    <header>
                        <h2 data-engagement-table-title>Who's Clicking</h2>
                        <span style="display:inline-flex;align-items:center;gap:.65rem">
                            <button
                                type="button"
                                class="rc-btn rc-btn-compact"
                                data-engagement-clear
                                hidden
                                onclick="window.rcFilterCoachEngagement(this, '')"
                            >
                                Show All
                            </button>
                            <span>● Synced</span>
                        </span>
                    </header>
                    <div class="rc-detail-rows-v2">
                        @forelse($coachEngagementRows as $engagementRow)
                            @php
                                $engagementPlatformRaw = strtolower(trim((string) (
                                    $engagementRow['platform_icon_key']
                                    ?? $engagementRow['platform']
                                    ?? ''
                                )));

                                $engagementPlatformCanonical = match (true) {
                                    str_contains($engagementPlatformRaw, 'instagram'),
                                    $engagementPlatformRaw === 'ig' => 'instagram',

                                    str_contains($engagementPlatformRaw, 'youtube'),
                                    str_contains($engagementPlatformRaw, 'you_tube'),
                                    $engagementPlatformRaw === 'yt' => 'youtube',

                                    $engagementPlatformRaw === 'x',
                                    str_contains($engagementPlatformRaw, 'twitter'),
                                    str_contains($engagementPlatformRaw, 'x.com') => 'x',

                                    default => '',
                                };

                                $engagementIconKey = match ($engagementPlatformCanonical) {
                                    'instagram' => 'instagram',
                                    'youtube' => 'youtube',
                                    'x' => 'x',
                                    default => 'link',
                                };
                            @endphp

                            <button
                                type="button"
                                class="rc-detail-row-v2 is-engagement"
                                data-engagement-row
                                data-platform="{{ $engagementPlatformCanonical }}"
                                wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from((string) ($engagementRow['school_id'] ?? '')) }})"
                            >
                                <span class="rc-detail-platform-icon-v2 {{ $engagementRow['platform_class'] }}"><img class="rc-brand-icon-img" src="{{ $publicIconUrls[$engagementIconKey] }}" alt="{{ $engagementRow['platform'] }}" referrerpolicy="no-referrer"></span>
                                <span class="rc-detail-person-v2"><strong>{{ $engagementRow['title'] }}</strong><small>{{ $engagementRow['copy'] }}</small></span>
                                <span class="rc-detail-pill-v2 {{ $engagementRow['platform_class'] }}">{{ $engagementRow['platform'] }}</span>
                                <span class="rc-detail-count-v2"><b>{{ $engagementRow['clicks'] }}</b><small>{{ \Illuminate\Support\Str::plural('click', $engagementRow['clicks']) }}</small></span>
                                <span class="rc-detail-time-v2">{{ $engagementRow['time_label'] }}</span>
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

            <div class="rc-stats-drawer-backdrop rc-ui-stable-modal"
                wire:key="stats-drawer-emails-sent"
                data-rc-modal="stats"
                data-rc-modal-id="emails-sent"
                x-data="window.rcStatsDrawer ? window.rcStatsDrawer('emails-sent', true, true) : { open: true, openDrawer() { this.open = true }, close() { this.open = false } }"
                x-init="init && init()"
                x-show="open"
                x-cloak
                x-on:keydown.escape.window="if ($el.classList.contains('rc-stack-top')) { window.rcCloseOverlayNow($el); close(); }"
                x-on:click.self="if ($el.classList.contains('rc-stack-top')) { window.rcCloseOverlayNow($el); close(); }">
                <aside class="rc-stats-drawer-panel rc-ui-stable-panel"
                    data-rc-interaction-boundary
                    role="dialog"
                    aria-modal="true"
                    x-show="open"
                    x-on:click.stop>
                    <button type="button" class="rc-stats-drawer-close" data-rc-instant-close x-on:pointerdown.prevent.stop="window.rcCloseOverlayNow($el); close()" x-on:click.prevent.stop aria-label="Close details">×</button>
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

                .rc-discover-bulk-status-v92 {
                    display:inline-flex;
                    align-items:center;
                    gap:.5rem;
                    min-height:2rem;
                    padding:.42rem .68rem;
                    border-radius:.72rem;
                    border:1px solid rgba(255,99,56,.2);
                    background:rgba(255,99,56,.08);
                    color:var(--rc-text);
                    font-size:.76rem;
                    font-weight:750;
                    line-height:1.25;
                }
                .rc-discover-bulk-option-v36 .rc-spinner-mini,
                .rc-discover-bulk-list-v36 > button .rc-spinner-mini {
                    width:.88rem;
                    height:.88rem;
                    border-width:2px;
                    flex:0 0 auto;
                }

                @media (max-width: 1100px) {
                    .rc-discover-top-v29 { grid-template-columns: 1fr !important; }
                    .rc-discover-actions-v29 { max-width:none !important; grid-template-columns:minmax(0,1fr) 2.75rem 2.75rem !important; }
                    .rc-discover-title-v29 h1 { white-space: normal; }
                }

                .rc-discover-quick-list-v97 {
                    display:grid;
                    gap:.5rem;
                    padding:.65rem;
                    margin-top:.35rem;
                    border-top:1px solid var(--rc-border);
                    background:var(--rc-soft);
                }
                .rc-discover-quick-list-title-v97 {
                    font-size:.72rem;
                    font-weight:800;
                    color:var(--rc-muted);
                    text-transform:uppercase;
                    letter-spacing:.05em;
                }
                .rc-discover-quick-list-v97 .rc-input { width:100%; }
                .rc-discover-quick-list-actions-v97 {
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:.5rem;
                }
                .rc-discover-quick-list-actions-v97 input[type="color"] {
                    width:2.4rem;
                    height:2.1rem;
                    padding:.15rem;
                    border:1px solid var(--rc-border);
                    border-radius:.55rem;
                    background:var(--rc-surface);
                }

                /* Add-to-list dropdown — compact reference layout */
                .rc-list-popover-v105 {
                    left:auto !important;
                    right:0 !important;
                    top:calc(100% + .55rem) !important;
                    width:21.75rem !important;
                    min-width:21.75rem !important;
                    max-width:min(21.75rem, calc(100vw - 2rem)) !important;
                    max-height:none !important;
                    overflow:visible !important;
                    padding:0 !important;
                    border:1px solid #e5e7eb !important;
                    border-radius:1rem !important;
                    background:#fff !important;
                    color:#111827 !important;
                    box-shadow:0 24px 60px rgba(15,23,42,.22) !important;
                }

                .rc-list-popover-title-v105 {
                    padding:1rem 1.15rem .72rem;
                    color:#8791a4;
                    font-size:.72rem;
                    font-weight:850;
                    line-height:1.2;
                    letter-spacing:.075em;
                    text-transform:uppercase;
                }

                .rc-list-popover-options-v105 {
                    display:grid;
                    gap:.12rem;
                    padding:0 .72rem .65rem;
                    max-height:15.5rem;
                    overflow:auto;
                }

                .rc-list-popover-row-v105 {
                    min-height:2.75rem;
                    padding:.58rem .58rem !important;
                    border-radius:.65rem !important;
                    font-size:.85rem !important;
                    font-weight:700 !important;
                }

                .rc-list-popover-row-v105:hover {
                    background:#f8fafc !important;
                    color:#111827 !important;
                }

                .rc-list-popover-row-v105:disabled {
                    cursor:wait;
                    opacity:.78;
                }

                .rc-list-popover-row-main-v105,
                .rc-list-popover-row-side-v105 {
                    display:inline-flex;
                    align-items:center;
                    gap:.68rem;
                    min-width:0;
                }

                .rc-list-popover-dot-v105 {
                    width:.72rem;
                    height:.72rem;
                    flex:0 0 auto;
                    border-radius:999px;
                    box-shadow:0 0 0 3px rgba(15,23,42,.025);
                }

                .rc-list-popover-label-v105 {
                    overflow:hidden;
                    color:#111827;
                    text-overflow:ellipsis;
                    white-space:nowrap;
                }

                .rc-list-popover-count-v105 {
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    min-width:1.75rem;
                    height:1.75rem;
                    padding:0 .48rem;
                    border-radius:999px;
                    background:#f2f4f7;
                    color:#9aa3b2;
                    font-size:.75rem;
                    font-weight:800;
                }

                .rc-list-saving-v105 {
                    display:inline-flex;
                    align-items:center;
                    gap:.38rem;
                    color:#ff6338;
                    font-size:.72rem;
                    font-weight:800;
                }

                .rc-list-saving-v105 .rc-spinner-mini {
                    width:.82rem;
                    height:.82rem;
                    border-width:2px;
                }

                .rc-list-quick-create-v105 {
                    display:grid !important;
                    grid-template-columns:minmax(0,1fr) 3rem;
                    gap:.55rem !important;
                    padding:.8rem .9rem .9rem !important;
                    margin:0 !important;
                    border-top:1px solid #edf0f4 !important;
                    background:#fff !important;
                }

                .rc-list-new-input-v105 {
                    width:100%;
                    min-width:0;
                    height:2.8rem;
                    padding:0 .9rem;
                    border:1px solid #dfe4ea;
                    border-radius:.72rem;
                    background:#fff;
                    color:#111827;
                    font-size:.86rem;
                    outline:none;
                    transition:border-color .15s ease, box-shadow .15s ease;
                }

                .rc-list-new-input-v105::placeholder {
                    color:#98a2b3;
                }

                .rc-list-new-input-v105:focus {
                    border-color:#ff8f73;
                    box-shadow:0 0 0 3px rgba(255,99,56,.12);
                }

                .rc-list-new-button-v105 {
                    width:3rem;
                    height:2.8rem;
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    border:0;
                    border-radius:.72rem;
                    background:#ff9a84;
                    color:#fff;
                    cursor:pointer;
                    transition:background .15s ease, transform .15s ease, opacity .15s ease;
                }

                .rc-list-new-button-v105:hover:not(:disabled) {
                    background:#ff6338;
                    transform:translateY(-1px);
                }

                .rc-list-new-button-v105:disabled {
                    cursor:not-allowed;
                    opacity:.58;
                }

                .rc-list-new-button-v105 .rc-spinner-mini {
                    width:1rem;
                    height:1rem;
                    border-width:2px;
                }

                .rc-list-popover-progress-v105 {
                    display:grid;
                    gap:.42rem;
                    padding:.72rem .9rem .82rem;
                    border-top:1px solid #edf0f4;
                    color:#667085;
                    font-size:.72rem;
                    font-weight:750;
                    line-height:1.35;
                }

                .rc-list-progress-bar-v105 {
                    position:relative;
                    height:.28rem;
                    overflow:hidden;
                    border-radius:999px;
                    background:#ffe4dd;
                }

                .rc-list-progress-bar-v105 > span {
                    position:absolute;
                    top:0;
                    bottom:0;
                    left:-38%;
                    width:38%;
                    border-radius:inherit;
                    background:#ff6338;
                    animation:rcListSavingBarV105 1.05s ease-in-out infinite;
                }

                @keyframes rcListSavingBarV105 {
                    0% { transform:translateX(0); }
                    100% { transform:translateX(365%); }
                }

                .dark .rc-list-popover-v105,
                .dark .rc-list-quick-create-v105 {
                    border-color:#34343b !important;
                    background:#18181b !important;
                    color:#f4f4f5 !important;
                }

                .dark .rc-list-popover-title-v105,
                .dark .rc-list-popover-progress-v105 {
                    color:#a1a1aa;
                }

                .dark .rc-list-popover-row-v105:hover {
                    background:#27272a !important;
                }

                .dark .rc-list-popover-label-v105 {
                    color:#f4f4f5;
                }

                .dark .rc-list-popover-count-v105 {
                    background:#27272a;
                    color:#a1a1aa;
                }

                .dark .rc-list-new-input-v105 {
                    border-color:#3f3f46;
                    background:#202024;
                    color:#f4f4f5;
                }

                .dark .rc-list-quick-create-v105,
                .dark .rc-list-popover-progress-v105 {
                    border-top-color:#34343b !important;
                }

</style>

            <div
                class="rc-discover-v29"
                wire:ignore
                x-data="window.rcDiscoverClientCache(
                    @js($this->discoverSchoolsClientDataset),
                    @js(collect($this->lists)->map(fn ($list) => [
                        'key' => (string) ($list['key'] ?? ''),
                        'label' => (string) ($list['label'] ?? ''),
                        'color' => (string) ($list['color'] ?? '#ff6338'),
                        'count' => (int) ($list['schools_count'] ?? count($list['schools'] ?? [])),
                    ])->filter(fn ($list) => $list['key'] !== '')->values()->all()),
                    @js($schoolViewMode)
                )"
                x-init="init()"
            >
                @include('filament.partials.coach-database-header', [
                    'firstName' => $firstName,
                    'placeholder' => 'Search schools, coaches, conferences, divisions, lists...',
                    'showNewEmail' => false,
                ])

                <div class="rc-discover-program-search-v27" role="search" aria-label="Search schools and coaches">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                    <input placeholder="Search women's soccer programs & coaches..." x-model.debounce.80ms="search" x-on:input="limit = 48" />
                </div>

                <div class="rc-discover-filter-v27">
                    <div class="rc-discover-tabs-v27" aria-label="Division filter">
                        <template x-for="divisionValue in divisionTabs" :key="divisionValue || 'all'">
                            <button
                                type="button"
                                class="rc-discover-tab-v27"
                                x-bind:class="{ 'is-active': division === divisionValue }"
                                x-on:click="setDivision(divisionValue)"
                                x-text="divisionValue || 'All Divisions'"
                            ></button>
                        </template>
                    </div>

                    <select class="rc-discover-select-v27" x-bind:value="conference" x-on:change="setConference($event.target.value)" aria-label="Conference filter">
                        <option value="">All Conferences</option>
                        <template x-for="conferenceName in conferenceOptions" :key="conferenceName">
                            <option x-bind:value="conferenceName" x-text="conferenceName"></option>
                        </template>
                    </select>
                </div>

                <div class="rc-discover-meta-v27">
                    <div class="rc-discover-count-v27">
                        <span><strong x-text="filteredSchools.length.toLocaleString()"></strong> schools</span>
                        <button type="button" class="rc-discover-select-all-v27 rc-discover-select-all-button-v36" x-on:click.stop="toggleAllFiltered()">
                            <input type="checkbox" x-bind:checked="allFilteredSelected" readonly tabindex="-1">
                            <span>Select All (<span x-text="filteredSchools.length.toLocaleString()"></span>)</span>
                        </button>
                    </div>

                    <div class="rc-discover-right-v27">
                        <div class="rc-discover-toggle-v27" aria-label="School view">
                            <button type="button" x-bind:class="{ 'is-active': viewMode === 'grid' }" x-on:click.stop="setViewMode('grid')" aria-label="Grid view"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></button>
                            <button type="button" x-bind:class="{ 'is-active': viewMode === 'list' }" x-on:click.stop="setViewMode('list')" aria-label="List view"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg></button>
                        </div>
                    </div>
                </div>

                <div class="rc-discover-bulk-v36" x-show="selectedCount > 0" x-cloak>
                    <div class="rc-discover-bulk-left-v36">
                        <span class="rc-discover-bulk-count-v36"><span x-text="selectedCount.toLocaleString()"></span> selected</span>
                        <button type="button" class="rc-discover-bulk-email-v36" x-on:click.prevent.stop="emailSelected()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            <span>Email</span>
                        </button>
                        <div class="rc-discover-bulk-list-v36" x-on:click.outside="listMenuOpen = false">
                            <button type="button" x-on:click.stop="listMenuOpen = !listMenuOpen">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                <span>Add to List</span>
                                <span x-show="Boolean(pendingListKey)" x-cloak class="rc-spinner-mini" aria-hidden="true"></span>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="rc-discover-bulk-menu-v36 rc-list-popover-v105" x-cloak x-show="listMenuOpen" x-transition.origin.top.left>
                                <div class="rc-list-popover-title-v105">ADD <span x-text="selectedCount.toLocaleString()"></span> TO A LIST</div>
                                <div class="rc-list-popover-options-v105">
                                    <template x-for="listItem in lists" :key="listItem.key">
                                        <button type="button" class="rc-discover-bulk-option-v36 rc-list-popover-row-v105" x-on:click.stop="addToList(listItem.key, listItem.label)" x-bind:disabled="Boolean(pendingListKey)">
                                            <span class="rc-list-popover-row-main-v105">
                                                <span class="rc-list-popover-dot-v105" x-bind:style="`background:${listItem.color || '#ff6338'}`"></span>
                                                <span class="rc-list-popover-label-v105" x-text="listItem.label"></span>
                                            </span>
                                            <span class="rc-list-popover-row-side-v105">
                                                <span class="rc-list-popover-count-v105" x-show="!isPending(listItem.key)" x-text="Number(listItem.count || 0).toLocaleString()"></span>
                                                <span x-show="isPending(listItem.key)" x-cloak class="rc-list-saving-v105" aria-label="Saving selected schools"><span class="rc-spinner-mini"></span></span>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                                <div class="rc-list-quick-create-v105" x-on:click.stop>
                                    <div class="rc-list-popover-title-v105">CREATE NEW LIST</div>
                                    <div class="rc-list-new-row-v105">
                                        <input type="text" class="rc-list-new-input-v105" placeholder="New list name..." x-model="newListName" x-on:keydown.enter.prevent="createQuickList()" x-bind:disabled="creating">
                                        <button type="button" class="rc-list-new-button-v105" x-on:click="createQuickList()" x-bind:disabled="creating || !newListName.trim()"><span x-show="!creating">+</span><span x-show="creating" x-cloak class="rc-spinner-mini"></span></button>
                                    </div>
                                </div>
                                <div class="rc-list-popover-progress-v105" x-show="Boolean(pendingListKey) || creating" x-cloak role="status" aria-live="polite">
                                    <span class="rc-list-progress-bar-v105"><span></span></span>
                                    <span x-text="creating ? 'Creating new list.' : 'Saving selected schools.'"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="rc-discover-bulk-clear-v36" x-on:click="clearSelection()">Clear</button>
                </div>

                <div class="rc-discover-loading-v27">
                    @include('filament.partials.coach-database-school-grid')
                </div>

                <div x-show="canLoadMore" style="margin-top:.35rem;text-align:center" x-cloak>
                    <button class="rc-btn" type="button" x-on:click="loadMore()">Load more</button>
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
                                <button type="button" class="rc-fav-list-view-v40" wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from($schoolId) }})">
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
                                    <button type="button" class="rc-favorite-view-v37" wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from($schoolId) }})">
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
                .rc-my-lists-v41 { display:grid; gap:1.15rem; }
                .rc-my-lists-head-v41 { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; margin-top:.25rem; }
                .rc-my-lists-title-v41 h2 { margin:0; color:var(--rc-text); font-size:1.35rem; line-height:1.1; letter-spacing:-.025em; font-weight:750; }
                .rc-my-lists-title-v41 p { margin:.45rem 0 0; color:var(--rc-muted); font-size:.9rem; line-height:1.4; }
                .rc-new-list-btn-v41 { min-height:2.85rem; padding:0 1.15rem; border:0; border-radius:.9rem; display:inline-flex; align-items:center; justify-content:center; gap:.55rem; background:#ff6338; color:#fff; font-size:.9rem; font-weight:750; box-shadow:0 15px 30px rgba(255,99,56,.22); cursor:pointer; }
                .rc-new-list-panel-v41 { border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1rem; padding:1rem; box-shadow:0 14px 35px rgba(15,23,42,.055); display:grid; grid-template-columns:minmax(0,1fr) auto auto auto; align-items:center; gap:.65rem; }
                .rc-new-list-panel-v41 .rc-input { flex:1; min-height:2.65rem; width:100%; border-color:#ff6338; }
                .rc-list-color-picker-v42 { display:inline-flex; align-items:center; gap:.38rem; flex:0 0 auto; }
                .rc-list-color-option-v42 { width:1.55rem; height:1.55rem; border-radius:.45rem; border:2px solid transparent; box-shadow:inset 0 0 0 1px rgba(15,23,42,.08); cursor:pointer; padding:0; display:inline-flex; align-items:center; justify-content:center; }
                .rc-list-color-option-v42.is-selected { border-color:var(--rc-text); box-shadow:0 0 0 2px var(--rc-surface), 0 0 0 4px currentColor; }
                .rc-list-color-option-v42 span { width:100%; height:100%; border-radius:.32rem; display:block; }
                .rc-list-stack-v41 { display:grid; gap:1rem; }
                .rc-list-card-v41 { border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1.15rem; padding:1.05rem 1.2rem; box-shadow:0 14px 35px rgba(15,23,42,.055); display:grid; gap:1rem; }
                .rc-list-card-head-v41 { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
                .rc-list-card-title-v41 { display:flex; align-items:center; gap:.7rem; min-width:0; }
                .rc-list-dot-v41 { width:.72rem; height:.72rem; border-radius:999px; flex:0 0 auto; background:var(--list-color, #ff6338); }
                .rc-list-card-title-v41 strong { color:var(--rc-text); font-size:1rem; font-weight:750; line-height:1.2; }
                .rc-list-count-pill-v41 { border-radius:999px; padding:.25rem .65rem; background:var(--rc-soft); color:var(--rc-muted); font-size:.78rem; line-height:1.2; white-space:nowrap; }
                .rc-list-card-actions-v41 { display:inline-flex; align-items:center; gap:.45rem; flex:0 0 auto; }
                .rc-list-icon-btn-v41 { width:2rem; height:2rem; border:0; border-radius:.65rem; background:transparent; color:var(--rc-muted); display:grid; place-items:center; cursor:pointer; transition:.16s ease; }
                .rc-list-icon-btn-v41:hover, .rc-list-icon-btn-v41.is-active { background:var(--rc-soft); color:var(--rc-accent); }
                .rc-list-chip-wrap-v41 { display:flex; flex-wrap:wrap; gap:.55rem; min-height:2.35rem; }
                .rc-list-school-chip-v41 { display:inline-flex; align-items:center; gap:.55rem; border:1px solid var(--rc-border); border-radius:.65rem; background:var(--rc-soft); color:var(--rc-text); padding:.35rem .45rem; font-size:.82rem; font-weight:650; line-height:1.2; }
                .rc-list-chip-logo-v41 { width:1.7rem; height:1.7rem; border-radius:.45rem; border:1px solid var(--rc-border); background:#fff; color:#111827; display:inline-flex; align-items:center; justify-content:center; overflow:hidden; flex:0 0 auto; font-size:.67rem; font-weight:800; }
                .rc-list-chip-logo-v41 img { width:100%; height:100%; object-fit:contain; display:block; background:#fff; }
                .rc-list-chip-remove-v41 { border:0; background:transparent; color:var(--rc-muted); width:1rem; height:1rem; display:grid; place-items:center; cursor:pointer; padding:0; }
                .rc-list-chip-remove-v41:hover { color:#ff6338; }
                .rc-list-empty-card-v41 { border:1px dashed var(--rc-border); border-radius:1rem; padding:1rem; color:var(--rc-muted); background:var(--rc-surface); }
                .rc-list-selected-v41 { border-color:rgba(255,99,56,.42); box-shadow:0 14px 35px rgba(255,99,56,.08); }
                .dark .rc-new-list-btn-v41 { box-shadow:0 15px 30px rgba(255,99,56,.16); }
                @media (max-width: 980px) { .rc-new-list-panel-v41 { grid-template-columns:1fr; align-items:stretch; } .rc-list-color-picker-v42 { justify-content:flex-start; } }
                @media (max-width: 780px) { .rc-my-lists-head-v41 { align-items:flex-start; flex-direction:column; } .rc-new-list-btn-v41 { width:100%; } .rc-list-card-head-v41 { align-items:flex-start; flex-direction:column; } }
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
                        $value = $school[$key] ?? null;
                        if (is_scalar($value)) {
                            $url = trim((string) $value);
                            $lower = strtolower($url);
                            if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://') || str_starts_with($url, '//')) {
                                return str_starts_with($url, '//') ? 'https:' . $url : $url;
                            }
                        }
                    }
                    foreach (['head_coach.logo_url', 'head_coach.school_logo_url', 'head_coach.business_logo_url'] as $key) {
                        $url = trim((string) data_get($school, $key, ''));
                        $lower = strtolower($url);
                        if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://') || str_starts_with($url, '//')) {
                            return str_starts_with($url, '//') ? 'https:' . $url : $url;
                        }
                    }
                    return '';
                };
                $schoolsForListKey = function (array $list): array {
                    $listKey = (string) ($list['key'] ?? '');
                    if ($listKey === '') {
                        return [];
                    }

                    return collect($this->allSchools())
                        ->filter(fn (array $school): bool => in_array($listKey, $school['list_keys'] ?? [], true))
                        ->values()
                        ->all();
                };
                $allListSchoolOptions = collect($this->allSchools())
                    ->filter(fn ($school): bool => is_array($school))
                    ->map(function (array $school) use ($listLogoUrlFor, $listInitials): array {
                        $name = (string) ($school['name'] ?? 'School');
                        return [
                            'id' => (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim($name)))),
                            'name' => $name,
                            'logo' => $listLogoUrlFor($school),
                            'initials' => $listInitials($name),
                        ];
                    })
                    ->filter(fn (array $school): bool => $school['id'] !== '')
                    ->unique('id')
                    ->values()
                    ->all();
            @endphp

            <style>
                .rc-list-card-v120{background:#fff;border:1px solid #e7e9ee;border-radius:1rem;padding:1rem 1.15rem;box-shadow:0 8px 24px rgba(15,23,42,.06)}
                .rc-list-card-head-v120{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
                .rc-list-card-title-v120{display:flex;align-items:center;gap:.72rem;min-width:0}
                .rc-list-card-title-v120 strong{font-size:1rem;color:#111827}
                .rc-list-card-actions-v120{display:flex;align-items:center;gap:.45rem;margin-left:auto}
                .rc-list-action-v120{width:2.35rem;height:2.35rem;border:0;border-radius:.72rem;background:transparent;color:#8b95a7;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:.15s ease}
                .rc-list-action-v120:hover,.rc-list-action-v120.is-active{background:#fff0ec;color:#ff6338}
                .rc-list-email-v120{width:auto;padding:0 .9rem;gap:.45rem;background:#ff6338;color:#fff;font-weight:750;box-shadow:0 8px 20px rgba(255,99,56,.22)}
                .rc-list-email-v120:hover{background:#ef5530;color:#fff}
                .rc-list-panel-v120{margin-top:.85rem;display:grid;gap:.72rem}
                .rc-list-confirm-v120{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1rem;border-radius:.8rem;background:#fee7e4;color:#b42318;font-weight:650}
                .rc-list-confirm-actions-v120{display:flex;gap:.55rem}
                .rc-list-confirm-actions-v120 button{border:0;border-radius:.65rem;padding:.62rem 1rem;font-weight:750;cursor:pointer}
                .rc-list-confirm-cancel-v120{background:#fff;color:#344054}
                .rc-list-confirm-delete-v120{background:#e94e43;color:#fff}
                .rc-list-tools-v120{display:flex;gap:.65rem;align-items:center;flex-wrap:wrap}
                .rc-list-search-v120{flex:1 1 20rem;position:relative}
                .rc-list-search-v120 input{width:100%;height:2.75rem;border:1px solid #e1e5eb;border-radius:.72rem;padding:0 .9rem;background:#f8f9fb;color:#111827;outline:none}
                .rc-list-search-v120 input:focus{border-color:#ff8b70;box-shadow:0 0 0 3px rgba(255,99,56,.1);background:#fff}
                .rc-list-add-results-v120{position:absolute;z-index:40;top:calc(100% + .35rem);left:0;right:0;max-height:17rem;overflow:auto;background:#fff;border:1px solid #e2e6ec;border-radius:.75rem;box-shadow:0 18px 40px rgba(15,23,42,.18);padding:.35rem}
                .rc-list-add-result-v120{width:100%;border:0;background:#fff;padding:.62rem .7rem;border-radius:.55rem;display:flex;align-items:center;gap:.65rem;text-align:left;cursor:pointer;color:#111827}
                .rc-list-add-result-v120:hover{background:#fff3ef}
                .rc-list-selection-bar-v120{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.72rem .85rem;border-radius:.72rem;background:#f7f8fa;flex-wrap:wrap}
                .rc-list-selection-meta-v120{display:flex;gap:.8rem;align-items:center;color:#667085;font-size:.78rem}
                .rc-list-selection-meta-v120 strong{color:#1d2939}
                .rc-list-selection-meta-v120 button{border:0;background:transparent;color:#667085;cursor:pointer;padding:0}
                .rc-list-selection-email-v120{border:0;border-radius:.65rem;background:#ff6338;color:#fff;padding:.62rem .9rem;font-weight:750;cursor:pointer}
                .rc-list-chip-wrap-v120{display:flex;flex-wrap:wrap;gap:.55rem}
                .rc-list-school-chip-v120{display:inline-flex;align-items:center;gap:.5rem;min-height:2.65rem;padding:.38rem .65rem;border:1px solid #e4e7ec;border-radius:.72rem;background:#f7f8fa;color:#111827;transition:.15s ease}
                .rc-list-school-chip-v120.is-selected{border-color:#ff6338;background:#fff1ed}
                .rc-list-chip-logo-v120{width:2rem;height:2rem;border-radius:.52rem;background:#fff;display:inline-flex;align-items:center;justify-content:center;overflow:hidden;font-size:.72rem;flex:0 0 auto}
                .rc-list-chip-logo-v120 img{width:100%;height:100%;object-fit:contain}
                .rc-list-chip-name-v120{border:0;background:transparent;color:inherit;font:inherit;font-weight:650;padding:0;cursor:pointer}
                .rc-list-chip-remove-v120,.rc-list-chip-check-v120{width:1.35rem;height:1.35rem;border:0;background:transparent;color:#8b95a7;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:0}
                .rc-list-chip-check-v120{border:1px solid #d9dee7;border-radius:.35rem;background:#fff}
                .rc-list-chip-check-v120.is-checked{background:#ff6338;border-color:#ff6338;color:#fff}
                .rc-list-expand-v120{justify-self:start;border:1px solid #e2e6ec;border-radius:.65rem;background:#fff;color:#344054;padding:.62rem .9rem;font-weight:700;cursor:pointer}
                .rc-list-rename-v120{display:flex;align-items:center;gap:.55rem}
                .rc-list-rename-v120 input{height:2.55rem;min-width:14rem;border:1px solid #ff6338;border-radius:.65rem;padding:0 .75rem;outline:none}
                .rc-list-rename-v120 button{height:2.55rem;border:0;border-radius:.65rem;padding:0 .8rem;font-weight:700;cursor:pointer}
                .rc-list-empty-v120{color:#667085;font-size:.85rem;padding:.8rem 0}
                .dark .rc-list-card-v120,.dark .rc-list-add-results-v120{background:#18181b;border-color:#34343b}

                /* Compact My Lists controls and chips. */
                .rc-list-card-v120{padding:1rem 1.05rem;border-radius:1rem}
                .rc-list-card-head-v120{gap:.65rem;margin-bottom:.7rem}
                .rc-list-card-title-v120{gap:.48rem}
                .rc-list-card-title-v120 strong{font-size:1rem;font-weight:700}
                .rc-list-count-pill-v41{min-height:1.7rem;padding:.15rem .58rem;font-size:.72rem;font-weight:500}
                .rc-list-card-actions-v120{gap:.3rem}
                .rc-list-action-v120{min-width:2.15rem;height:2.15rem;padding:0 .52rem;border-radius:.58rem;font-size:.75rem;font-weight:600}
                .rc-list-email-v120{padding:0 .7rem;gap:.35rem}
                .rc-list-tools-v120{margin:.45rem 0}
                .rc-list-search-v120{margin:.45rem 0}
                .rc-list-search-v120 input{height:2.35rem;padding:0 .72rem;font-size:.8rem;border-radius:.58rem}
                .rc-list-add-results-v120{max-height:14rem;border-radius:.65rem}
                .rc-list-add-result-v120{min-height:2.25rem;padding:.38rem .55rem;font-size:.78rem;font-weight:500}
                .rc-list-selection-bar-v120{min-height:3rem;padding:.55rem .7rem;border-radius:.65rem}
                .rc-list-selection-meta-v120{gap:.55rem;font-size:.75rem}
                .rc-list-selection-meta-v120 strong{font-size:.76rem;font-weight:650}
                .rc-list-selection-email-v120{min-height:2.15rem;padding:0 .72rem;border-radius:.58rem;font-size:.75rem;font-weight:650}
                .rc-list-chip-wrap-v120{gap:.42rem}
                .rc-list-school-chip-v120{gap:.36rem;min-height:2.15rem;padding:.24rem .48rem;border-radius:.58rem;font-size:.78rem}
                .rc-list-chip-logo-v120{width:1.65rem;height:1.65rem;border-radius:.45rem;font-size:.72rem}
                .rc-list-chip-name-v120{font-size:.78rem;font-weight:550}
                .rc-list-chip-remove-v120,.rc-list-chip-check-v120{width:1.45rem;height:1.45rem}
                .rc-list-expand-v120{min-height:2.15rem;padding:0 .68rem;margin-top:.65rem;border-radius:.58rem;font-size:.75rem;font-weight:600}
                .rc-list-rename-v120{gap:.35rem;flex-wrap:wrap}
                .rc-list-rename-v120 input{height:2.2rem;min-width:11rem;padding:0 .62rem;border-radius:.55rem;font-size:.8rem}
                .rc-list-rename-v120 button{height:2.2rem;padding:0 .62rem;border-radius:.55rem;font-size:.74rem;font-weight:600}
                .rc-list-rename-v120 .is-save{background:#ff6338;color:#fff}
                .rc-list-rename-v120 .is-cancel{background:#f2f4f7;color:#344054}
                .rc-list-inline-error-v121{flex-basis:100%;color:#d92d20;font-size:.7rem;font-weight:500}

                .dark .rc-list-card-title-v120 strong,.dark .rc-list-add-result-v120,.dark .rc-list-school-chip-v120{color:#f4f4f5}
                .dark .rc-list-school-chip-v120,.dark .rc-list-selection-bar-v120,.dark .rc-list-search-v120 input{background:#222226;border-color:#3f3f46}
            </style>

            <div class="rc-my-lists-v41">
                <div class="rc-my-lists-head-v41">
                    <div class="rc-my-lists-title-v41">
                        <h2>My Lists</h2>
                        <p>Organize schools into your own lists — Dream Schools, On the Radar, by conference, however you want.</p>
                    </div>
                    <button type="button" class="rc-new-list-btn-v41" wire:click="$set('showNewListComposer', true)" data-rc-local-action>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        New List
                    </button>
                </div>

                @if($showNewListComposer)
                    <div class="rc-new-list-panel-v41">
                        <input class="rc-input" placeholder="List name (e.g. Dream Schools)" wire:model.defer="newListName" wire:keydown.enter="createCustomList" autofocus />
                        <div class="rc-list-color-picker-v42" aria-label="Choose list color">
                            @foreach(['#ff6338', '#3b82f6', '#22c55e', '#f59e0b', '#7c5cff'] as $colorOption)
                                <button type="button" class="rc-list-color-option-v42 {{ strtolower($newListColor) === strtolower($colorOption) ? 'is-selected' : '' }}" style="color: {{ $colorOption }};" wire:click="$set('newListColor', '{{ $colorOption }}')"><span style="background: {{ $colorOption }};"></span></button>
                            @endforeach
                        </div>
                        <button class="rc-btn rc-btn-primary" wire:click="createCustomList" wire:loading.attr="disabled" wire:target="createCustomList"><span wire:loading.remove wire:target="createCustomList">Create</span><span wire:loading.flex wire:target="createCustomList" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Creating</span></button>
                        <button class="rc-btn" type="button" wire:click="$set('showNewListComposer', false)">Cancel</button>
                    </div>
                @endif

                <div class="rc-list-stack-v41">
                    @forelse($listRows as $listIndex => $list)
                        @php
                            $listKey = (string) ($list['key'] ?? '');
                            $listLabel = (string) ($list['label'] ?? 'List');
                            $listSchools = $schoolsForListKey($list);
                            $listSchoolsLight = collect($listSchools)->map(function (array $school) use ($listLogoUrlFor, $listInitials): array {
                                $name = (string) ($school['name'] ?? 'School');
                                return [
                                    'id' => (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim($name)))),
                                    'name' => $name,
                                    'logo' => $listLogoUrlFor($school),
                                    'initials' => $listInitials($name),
                                ];
                            })->values()->all();
                            $listColor = $safeListColor($list['color'] ?? null, $listIndex);
                        @endphp
                        <article
                            class="rc-list-card-v120"
                            x-show="!deleted"
                            x-cloak
                            wire:key="list-card-{{ md5($listKey) }}"
                            wire:ignore
                            x-data="{
                                key: @js($listKey),
                                label: @js($listLabel),
                                items: @js($listSchoolsLight),
                                allSchools: @js($allListSchoolOptions),
                                expanded: false,
                                addOpen: false,
                                selectMode: false,
                                renameOpen: false,
                                confirmDelete: false,
                                deleted: false,
                                deleting: false,
                                deleteError: '',
                                addQuery: '',
                                listQuery: '',
                                renameValue: @js($listLabel),
                                selected: [],
                                removing: {},
                                adding: {},
                                savingRename: false,
                                renameError: '',
                                get visibleItems() {
                                    const q = this.listQuery.trim().toLowerCase();
                                    const filtered = q === '' ? this.items : this.items.filter(s => String(s.name || '').toLowerCase().includes(q));
                                    return this.expanded || q !== '' ? filtered : filtered.slice(0, 10);
                                },
                                get addResults() {
                                    const q = this.addQuery.trim().toLowerCase();
                                    if (q.length < 2) return [];
                                    const current = new Set(this.items.map(s => String(s.id)));
                                    return this.allSchools.filter(s => !current.has(String(s.id)) && String(s.name || '').toLowerCase().includes(q)).slice(0, 12);
                                },
                                isSelected(id) { return this.selected.includes(String(id)); },
                                toggleSelected(id) {
                                    id = String(id);
                                    this.selected = this.isSelected(id) ? this.selected.filter(v => v !== id) : [...this.selected, id];
                                },
                                selectAllVisible() { this.selected = this.items.map(s => String(s.id)); },
                                clearSelection() { this.selected = []; },
                                async removeSchool(school) {
                                    const id = String(school.id);
                                    if (this.removing[id]) return;
                                    const index = this.items.findIndex(s => String(s.id) === id);
                                    if (index < 0) return;
                                    this.renameError = '';
                                    this.removing = { ...this.removing, [id]: true };
                                    const removed = this.items[index];
                                    this.items = this.items.filter(s => String(s.id) !== id);
                                    this.selected = this.selected.filter(v => v !== id);
                                    try {
                                        const result = await $wire.call('queueSchoolListMemberships', id, { [this.key]: false });
                                        if (!result || result.success === false) throw new Error(result?.error || 'Unable to remove school');
                                    } catch (error) {
                                        const copy = [...this.items];
                                        copy.splice(Math.min(index, copy.length), 0, removed);
                                        this.items = copy;
                                        this.renameError = error?.message || 'Unable to remove this school.';
                                        window.console.error(error);
                                    } finally {
                                        const next = { ...this.removing }; delete next[id]; this.removing = next;
                                    }
                                },
                                async addSchool(schoolItem) {
                                    const normalized = {
                                        id: String(schoolItem?.id || ''),
                                        name: String(schoolItem?.name || 'School'),
                                        logo: String(schoolItem?.logo || ''),
                                        initials: String(schoolItem?.initials || ''),
                                    };
                                    const id = normalized.id;
                                    if (!id || this.adding[id] || this.items.some(item => String(item.id) === id)) return;

                                    // Add to the visible list immediately. The remote GHL action
                                    // is queued afterward and does not block the interface.
                                    this.renameError = '';
                                    this.items = [...this.items, normalized];
                                    this.addQuery = '';
                                    this.expanded = true;
                                    this.adding = { ...this.adding, [id]: true };

                                    try {
                                        const result = await $wire.call('queueSchoolListMemberships', id, { [this.key]: true });
                                        if (!result || result.success === false) {
                                            throw new Error(result?.error || 'Unable to add school');
                                        }
                                    } catch (error) {
                                        this.items = this.items.filter(item => String(item.id) !== id);
                                        this.renameError = error?.message || 'Unable to add school.';
                                        window.console.error(error);
                                    } finally {
                                        const nextAdding = { ...this.adding };
                                        delete nextAdding[id];
                                        this.adding = nextAdding;
                                    }
                                },
                                async emailAll() { await $wire.call('emailSchoolsInList', this.key); },
                                async emailSelected() {
                                    if (this.selected.length === 0) return;
                                    await $wire.call('emailSchoolIdsFromList', this.selected, this.key);
                                },
                                async saveRename() {
                                    const nextLabel = String(this.renameValue || '').trim();
                                    if (!nextLabel || this.savingRename) return;

                                    this.savingRename = true;
                                    this.renameError = '';

                                    try {
                                        const result = await $wire.call('renameRecruitingList', this.key, nextLabel);
                                        if (!result || result.success === false) {
                                            throw new Error(result?.error || 'Unable to rename this list.');
                                        }

                                        this.label = String(result.label || nextLabel);
                                        this.renameValue = this.label;
                                        this.renameOpen = false;
                                    } catch (error) {
                                        this.renameError = error?.message || 'Unable to rename this list.';
                                        window.console.error(error);
                                    } finally {
                                        this.savingRename = false;
                                    }
                                },
                                async deleteList() {
                                    if (this.deleting) return;

                                    this.deleting = true;
                                    this.deleteError = '';

                                    try {
                                        const result = await $wire.call('deleteCustomList', this.key);
                                        if (!result || result.success === false) {
                                            throw new Error(result?.error || 'Unable to delete this list.');
                                        }

                                        // The card is wire:ignore, so remove it optimistically
                                        // instead of waiting for a Livewire DOM morph.
                                        this.confirmDelete = false;
                                        this.deleted = true;
                                        this.items = [];
                                        this.selected = [];
                                    } catch (error) {
                                        this.deleteError = error?.message || 'Unable to delete this list.';
                                        window.console.error(error);
                                    } finally {
                                        this.deleting = false;
                                    }
                                }
                            }"
                        >
                            <div class="rc-list-card-head-v120">
                                <div class="rc-list-card-title-v120">
                                    <span class="rc-list-dot-v41" style="--list-color: {{ $listColor }};"></span>
                                    <template x-if="!renameOpen"><strong x-text="label"></strong></template>
                                    <template x-if="renameOpen">
                                        <span class="rc-list-rename-v120">
                                            <input type="text" x-model="renameValue" x-on:keydown.enter.prevent="saveRename()" x-on:keydown.escape.prevent="renameOpen=false;renameValue=label;renameError=''" x-bind:disabled="savingRename">
                                            <button type="button" class="is-save" x-on:click="saveRename()" x-bind:disabled="savingRename || !renameValue.trim()">
                                                <span x-show="!savingRename">Save</span>
                                                <span x-show="savingRename" x-cloak>Saving…</span>
                                            </button>
                                            <button type="button" class="is-cancel" x-on:click="renameOpen=false;renameValue=label;renameError=''" x-bind:disabled="savingRename">Cancel</button>
                                            <small class="rc-list-inline-error-v121" x-show="renameError" x-text="renameError"></small>
                                        </span>
                                    </template>
                                    <span class="rc-list-count-pill-v41"><span x-text="items.length.toLocaleString()"></span> <span x-text="items.length === 1 ? 'school' : 'schools'"></span></span>
                                </div>

                                <div class="rc-list-card-actions-v120">
                                    <button type="button" class="rc-list-action-v120 rc-list-email-v120" x-on:click="emailAll()" title="Email all schools in this list">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 4h16v16H4z"/><path d="m4 6 8 6 8-6"/></svg>
                                        Email Schools
                                    </button>
                                    <button type="button" class="rc-list-action-v120" x-bind:class="selectMode ? 'is-active' : ''" x-on:click="selectMode=!selectMode" title="Select specific schools to email">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>
                                    </button>
                                    <button type="button" class="rc-list-action-v120" x-bind:class="addOpen ? 'is-active' : ''" x-on:click="addOpen=!addOpen" title="Add a school">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                    <button type="button" class="rc-list-action-v120" x-bind:class="renameOpen ? 'is-active' : ''" x-on:click="renameOpen=!renameOpen;renameValue=label;renameError=''" title="Rename list">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg>
                                    </button>
                                    <button type="button" class="rc-list-action-v120" x-on:click="confirmDelete=true" title="Delete list">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="rc-list-panel-v120">
                                <div class="rc-list-confirm-v120" x-show="confirmDelete" x-cloak>
                                    <span>Delete “<span x-text="label"></span>” and its schools?</span>
                                    <span class="rc-list-confirm-actions-v120">
                                        <button type="button" class="rc-list-confirm-cancel-v120" x-on:click="confirmDelete=false;deleteError=''" x-bind:disabled="deleting">Cancel</button>
                                        <button type="button" class="rc-list-confirm-delete-v120" x-on:click="deleteList()" x-bind:disabled="deleting">
                                            <span x-show="!deleting">Delete</span>
                                            <span x-show="deleting" x-cloak>Deleting…</span>
                                        </button>
                                    </span>
                                    <small class="rc-list-inline-error-v121" x-show="deleteError" x-text="deleteError"></small>
                                </div>

                                <div class="rc-list-tools-v120" x-show="addOpen" x-cloak>
                                    <div class="rc-list-search-v120">
                                        <input type="search" placeholder="Search a school to add..." x-model.debounce.150ms="addQuery">
                                        <div class="rc-list-add-results-v120" x-show="addResults.length > 0" x-cloak>
                                            <template x-for="schoolItem in addResults" :key="`add-${key}-${schoolItem.id}`">
                                                <button type="button" class="rc-list-add-result-v120" x-on:click.prevent.stop="addSchool(schoolItem)" x-bind:disabled="Boolean(adding[String(schoolItem.id)])">
                                                    <span class="rc-list-chip-logo-v120"><img x-show="schoolItem.logo" x-bind:src="schoolItem.logo" alt=""><span x-show="!schoolItem.logo" x-text="schoolItem.initials"></span></span>
                                                    <span x-text="schoolItem.name"></span>
                                                    <span class="rc-spinner-mini" x-show="Boolean(adding[String(schoolItem.id)])" x-cloak></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="rc-list-selection-bar-v120" x-show="selectMode" x-cloak>
                                    <div class="rc-list-selection-meta-v120">
                                        <strong><span x-text="selected.length"></span> of <span x-text="items.length"></span> selected</strong>
                                        <button type="button" x-on:click="selectAllVisible()">Select all</button>
                                        <button type="button" x-on:click="clearSelection()">Clear</button>
                                    </div>
                                    <button type="button" class="rc-list-selection-email-v120" x-bind:disabled="selected.length === 0" x-on:click="emailSelected()">Email Selected (<span x-text="selected.length"></span>)</button>
                                </div>

                                <div class="rc-list-search-v120" x-show="items.length > 10 || listQuery !== ''" x-cloak>
                                    <input type="search" placeholder="Search in this list..." x-model.debounce.120ms="listQuery">
                                </div>

                                <div class="rc-list-chip-wrap-v120">
                                    <template x-for="schoolItem in visibleItems" :key="`list-${key}-${schoolItem.id}`">
                                        <span class="rc-list-school-chip-v120" x-bind:class="isSelected(schoolItem.id) ? 'is-selected' : ''">
                                            <button type="button" class="rc-list-chip-check-v120" x-show="selectMode" x-bind:class="isSelected(schoolItem.id) ? 'is-checked' : ''" x-on:click.prevent.stop="toggleSelected(schoolItem.id)">
                                                <svg x-show="isSelected(schoolItem.id)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m5 12 4 4L19 6"/></svg>
                                            </button>
                                            <span class="rc-list-chip-logo-v120"><img x-show="schoolItem.logo" x-bind:src="schoolItem.logo" alt=""><span x-show="!schoolItem.logo" x-text="schoolItem.initials"></span></span>
                                            <button type="button" class="rc-list-chip-name-v120" x-text="schoolItem.name" x-on:click.prevent.stop="$wire.call('openSchoolDashboardModal', schoolItem.id)"></button>
                                            <button type="button" class="rc-list-chip-remove-v120" x-show="!selectMode" x-on:click.prevent.stop="removeSchool(schoolItem)" title="Remove school">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                            </button>
                                        </span>
                                    </template>
                                    <div class="rc-list-empty-v120" x-show="items.length === 0">No schools in this list yet. Use the plus button to add one.</div>
                                </div>

                                <button type="button" class="rc-list-expand-v120" x-show="items.length > 10 && listQuery === ''" x-on:click="expanded=!expanded">
                                    <span x-text="expanded ? 'Show fewer schools' : `See All ${items.length.toLocaleString()} Schools`"></span>
                                </button>
                            </div>
                        </article>
                    @empty
                        <div class="rc-list-empty-card-v41"><strong>No lists yet.</strong><div class="rc-subtle">Create your first list, then add schools from Discover Schools or school cards.</div></div>
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

        @if($section === 'conversations')
            @include('filament.partials.coach-database-header', [
                'firstName' => $firstName,
                'placeholder' => 'Search schools, coaches, conferences...',
                'showNewEmail' => false,
            ])

            <div class="rc-section-async-banner {{ $isLoadingConversations ? 'is-visible' : '' }}">
                Refreshing conversations while the current inbox stays available.
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
            @endphp

            <div class="rc-inbox-page-v56" wire:poll.30s.visible="pollConversationUpdates">
                <div class="rc-inbox-shell-v56">
                    <aside class="rc-inbox-left-v56">
                        <div class="rc-inbox-panel-head-v56">
                            <h2>Conversations</h2>
                            <div class="rc-inbox-head-actions-v56">
                                <button type="button" class="rc-inbox-icon-btn-v56" wire:click="startNewConversation" title="New message" aria-label="New message">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M6 3h9l3 3v15H6V3Z" stroke="currentColor" stroke-width="1.8"/><path d="M14 3v4h4M9 12h6M9 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                </button>
                                <button type="button" class="rc-inbox-icon-btn-v56" wire:click="loadConversations" title="Refresh conversations" aria-label="Refresh conversations">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M4 7h11M4 12h16M4 17h11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="rc-inbox-search-v56">
                            <label>
                                <svg viewBox="0 0 24 24" fill="none"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                <input type="search" placeholder="Search conversations..." wire:model.live.debounce.450ms="conversationSearch">
                            </label>
                        </div>

                        <div class="rc-inbox-tabs-v56">
                            <button type="button" class="rc-inbox-tab-v56 {{ $filterStatus === 'all' ? 'is-active' : '' }}" wire:click="$set('conversationStatusFilter', 'all')">All</button>
                            <button type="button" class="rc-inbox-tab-v56 {{ $filterStatus === 'unread' ? 'is-active' : '' }}" wire:click="$set('conversationStatusFilter', 'unread')">Unread ({{ collect($this->conversations)->sum(fn($row) => (int) ($row['unread_count'] ?? 0)) }})</button>
                            <button type="button" class="rc-inbox-tab-v56 {{ $filterStatus === 'starred' ? 'is-active' : '' }}" wire:click="$set('conversationStatusFilter', 'starred')">Starred</button>
                        </div>

                        <div wire:loading.flex wire:target="loadConversations,pollConversationUpdates,conversationSearch,conversationStatusFilter" class="rc-loading-inline" style="padding:.65rem 1.1rem"><span class="rc-spinner-mini"></span> Updating inbox</div>

                        <div class="rc-inbox-list-v56">
                            @forelse($inboxConversations as $inboxConversation)
                                @php
                                    $inboxConversationId = (string) ($inboxConversation['id'] ?? '');
                                    $inboxContactName = (string) ($inboxConversation['contact_name'] ?? $inboxConversation['name'] ?? 'Coach');
                                    $inboxSchoolLine = (string) ($inboxConversation['school'] ?? $inboxConversation['company_name'] ?? $inboxConversation['email'] ?? 'School unavailable');
                                    $inboxLastMessage = trim(strip_tags((string) ($inboxConversation['last_message'] ?? $inboxConversation['snippet'] ?? 'No preview available.')));
                                    $inboxDate = $formatInboxDate($inboxConversation['last_message_at'] ?? $inboxConversation['updated_at'] ?? $inboxConversation['created_at'] ?? '');
                                    $isSelectedThread = $selectedConversationId === $inboxConversationId;
                                    $unreadCount = (int) ($inboxConversation['unread_count'] ?? 0);
                                    $statusLabel = $unreadCount > 0 ? 'Unread' : ((bool) ($inboxConversation['replied'] ?? $inboxConversation['has_reply'] ?? false) ? 'Replied' : 'Opened');
                                    $logo = $threadLogo($inboxConversation);
                                @endphp
                                <button type="button" class="rc-thread-card-v56 {{ $isSelectedThread ? 'is-selected' : '' }}" wire:click="selectConversation(@js($inboxConversationId))" data-rc-open="conversation" data-rc-title="{{ $inboxContactName }}" data-rc-copy="Opening the conversation now. Messages will load inside the thread." wire:loading.attr="disabled" wire:target="selectConversation(@js($inboxConversationId))">
                                    <span class="rc-thread-logo-v56">
                                        @if($logo !== '')
                                            <img src="{{ $logo }}" alt="{{ $inboxSchoolLine }} logo" referrerpolicy="no-referrer" onerror="this.remove();">
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
                                    <span style="display:grid;justify-items:end;align-content:start;gap:.3rem">
                                        <span class="rc-thread-date-v56">{{ $inboxDate }}</span>
                                        @if($unreadCount > 0)<span class="rc-thread-unread-dot-v56"></span>@endif
                                    </span>
                                </button>
                            @empty
                                <div class="rc-inbox-empty-v56"><div><strong>No conversations found.</strong><br><span>Try another search or send a new coach email.</span></div></div>
                            @endforelse
                        </div>
                    </aside>

                    <main class="rc-inbox-mid-v56">
                        @if($selectedConversation)
                            <div class="rc-inbox-mid-head-v56">
                                <div class="rc-inbox-coach-title-v56">
                                    <span class="rc-inbox-school-logo-v56">
                                        @if($selectedSchoolLogo !== '')
                                            <img src="{{ $selectedSchoolLogo }}" alt="{{ $selectedSchool }} logo" referrerpolicy="no-referrer" onerror="this.remove();">
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
                                    <button type="button" class="rc-inbox-open-composer-v56" wire:click="openSelectedConversationInComposer">
                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none"><path d="m22 2-7 20-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                                        Open in Composer
                                    </button>
                                    <button type="button" class="rc-inbox-icon-btn-v56" wire:click="starSelectedConversation" title="Star coach"><svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></button>
                                    <button type="button" class="rc-inbox-icon-btn-v56" wire:click="scheduleSelectedConversation" title="Schedule"><svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M7 3v4M17 3v4M4 9h16M5 5h14v16H5V5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    <button type="button" class="rc-inbox-icon-btn-v56" wire:click="moreSelectedConversation" title="More"><svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M5 12h.01M12 12h.01M19 12h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></button>
                                </div>
                            </div>

                            <div class="rc-message-stream-v56">
                                <div class="rc-thread-loading-skeleton {{ $isLoadingConversationMessages ? 'is-visible' : '' }}">
                                    <span class="rc-skeleton"></span>
                                    <span class="rc-skeleton"></span>
                                    <span class="rc-skeleton"></span>
                                </div>
                                @if(empty($threadMessages))
                                    <div class="rc-inbox-empty-v56 {{ $isLoadingConversationMessages ? 'rc-ui-hidden' : '' }}"><div><strong>No messages loaded yet.</strong><br><button type="button" class="rc-inbox-open-composer-v56" wire:click="loadConversationMessages">Load conversation</button></div></div>
                                @else
                                    @foreach($threadMessages as $message)
                                        @php
                                            $message = is_array($message) ? $message : [];
                                            $isOut = str_contains(strtolower((string) ($message['direction'] ?? $message['type'] ?? '')), 'out');
                                            $fromLabel = $isOut ? 'You' : ($message['from_name'] ?? $selectedName);
                                            $toLabel = $message['to'] ?? ($isOut ? $selectedName : 'You');
                                            if (is_array($toLabel)) {
                                                $toLabel = collect($toLabel)->map(fn($item) => is_array($item) ? ($item['email'] ?? $item['name'] ?? $item['address'] ?? '') : (is_scalar($item) ? (string) $item : ''))->filter()->implode(', ');
                                            }
                                            $messageBody = (string) ($message['body'] ?? $message['html'] ?? $message['text'] ?? '');
                                            $messageDate = $formatMessageDate($message['created_at'] ?? $message['date'] ?? $message['messageDate'] ?? '');
                                            $messageAttachments = collect($message['attachments'] ?? [])->filter(fn($attachment) => is_array($attachment) && filled($attachment['url'] ?? null));
                                        @endphp
                                        <article class="rc-inbox-message-v56 {{ $isOut ? 'is-out' : '' }}">
                                            <span class="rc-msg-avatar-v56">{{ $isOut ? strtoupper(substr($firstName, 0, 1)) : $selectedInitials }}</span>
                                            <div style="min-width:0">
                                                <div class="rc-msg-meta-v56"><span><strong>{{ $fromLabel }}</strong> <span>to {{ $isOut ? $selectedName : 'You' }}</span></span><span>{{ $messageDate }}</span></div>
                                                <div class="rc-msg-bubble-v56">{!! $messageBody !== '' ? $messageBody : '<p>No message body.</p>' !!}</div>
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
                                                                <img class="rc-message-attachment-image" src="{{ $attachmentUrl }}" alt="{{ $attachmentName }}">
                                                            @else
                                                                <a class="rc-message-attachment-link" href="{{ $attachmentUrl }}" target="_blank" rel="noopener">Open {{ $attachmentName }}</a>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if($isOut)<div class="rc-message-status-v56"><span>⊙</span> Opened · just now</div>@endif
                                            </div>
                                        </article>
                                    @endforeach
                                @endif
                                @if($hasMoreMessages)
                                    <button class="rc-inbox-open-composer-v56" type="button" wire:click="loadConversationMessages" wire:loading.attr="disabled" wire:target="loadConversationMessages">Load older emails</button>
                                @endif
                            </div>
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
                                    <div class="rc-contact-line-v56"><svg viewBox="0 0 24 24" fill="none"><path d="M6 2h12v20H6V2Zm5 17h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg><span>{{ data_get($selectedCoach, 'phone') ?? $selectedConversation['phone'] ?? 'Phone unavailable' }}</span></div>
                                    <div class="rc-contact-line-v56"><svg viewBox="0 0 24 24" fill="none"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="10" r="2.3" stroke="currentColor" stroke-width="1.7"/></svg><span>{{ data_get($selectedCoach, 'city') ?: data_get($selectedCoach, 'state') ?: 'Location unavailable' }}</span></div>
                                </div>

                                <div class="rc-profile-actions-v56">
                                    <button type="button" class="rc-profile-action-v56" wire:click="viewSelectedConversationSchool"><svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M4 21V8l8-4 8 4v13M9 21v-7h6v7" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg><span>View School</span></button>
                                    <button type="button" class="rc-profile-action-v56" wire:click="addSelectedConversationSchoolToList"><svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><span>Add to List</span></button>
                                    <button type="button" class="rc-profile-action-v56" wire:click="scheduleSelectedConversation"><svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M7 3v4M17 3v4M4 9h16M5 5h14v16H5V5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Schedule</span></button>
                                    <button type="button" class="rc-profile-action-v56" wire:click="moreSelectedConversation"><svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M5 12h.01M12 12h.01M19 12h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg><span>More</span></button>
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
                    <div class="rc-settings-head-v72"><div class="rc-settings-icon-v72">🔔</div><div><h2 style="margin:0;">Notifications</h2><p style="margin:.2rem 0 0;color:var(--rc-muted);">Choose what you get notified about</p></div></div>
                    @foreach([
                        'profile_views' => ['Profile views', 'When a coach views your PLYR profile'],
                        'email_opens' => ['Email opens', 'When a coach opens one of your emails'],
                        'coach_replies' => ['Coach replies', 'When a coach replies to your outreach'],
                        'weekly_digest' => ['Weekly digest', 'A Monday summary of your recruiting activity'],
                        'product_news' => ['Product news', 'New PLYRCARD features and tips'],
                    ] as $settingKey => $settingCopy)
                        <div class="rc-setting-row-v72">
                            <div><h3>{{ $settingCopy[0] }}</h3><p>{{ $settingCopy[1] }}</p></div>
                            <button type="button" class="rc-toggle-v72 {{ ($notificationSettings[$settingKey] ?? false) ? 'is-on' : '' }}" wire:click="toggleNotificationSetting('{{ $settingKey }}')" aria-label="Toggle {{ $settingCopy[0] }}"><span></span></button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($section === 'compose')
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
                .rc-compose-coach-title-v45 { color:var(--rc-muted); font-size:.68rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .rc-compose-field-row-v45 { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.55rem; align-items:center; }
                .rc-compose-template-wrap-v45 { position:relative; }
                .rc-compose-template-menu-v45 { position:absolute; z-index:55; right:0; top:calc(100% + .45rem); width:min(23rem,88vw); border:1px solid var(--rc-border); border-radius:.85rem; background:var(--rc-surface); box-shadow:0 18px 45px rgba(15,23,42,.15); padding:.7rem; display:grid; gap:.25rem; }
                .rc-compose-template-menu-v45 button { width:100%; border:0; background:transparent; color:var(--rc-text); border-radius:.55rem; padding:.65rem .7rem; text-align:left; cursor:pointer; }
                .rc-compose-template-menu-v45 button:hover, .rc-compose-template-menu-v45 button.is-active { background:rgba(255,99,56,.12); }
                .rc-compose-template-menu-v45 strong { display:block; font-size:.8rem; }
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

            <div class="rc-compose-page-v45">
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
                        <button class="rc-btn" type="button" wire:click="openComposePreview">
                            <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            Preview
                        </button>
                        <button class="rc-btn" type="button" wire:click="saveTemplate" wire:loading.attr="disabled" wire:target="saveTemplate">
                            <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" /></svg>
                            <span wire:loading.remove wire:target="saveTemplate">Save as Template</span>
                            <span wire:loading.flex wire:target="saveTemplate" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Saving</span>
                        </button>
                        <button class="rc-btn rc-btn-primary" type="button" wire:click="sendComposedEmail" wire:loading.attr="disabled" wire:target="sendComposedEmail">
                            <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12 3.269 3.125A59.77 59.77 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.875L6 12Zm0 0h7.5" /></svg>
                            <span wire:loading.remove wire:target="sendComposedEmail">{{ $this->composeTargetLabel }}</span>
                            <span wire:loading.flex wire:target="sendComposedEmail" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Sending</span>
                        </button>
                    </div>
                </div>

                <div class="rc-compose-layout-v45">
                    <div class="rc-compose-card-v45">
                        <div class="rc-compose-inner-v45">
                            <div>
                                <div class="rc-compose-label-v45">Recipients</div>
                                <div class="rc-compose-recipient-bar-v45">
                                    @if(is_array($this->composeSelectedSchool))
                                        <span class="rc-compose-chip-v45">{{ $this->composeSelectedSchool['name'] ?? 'Selected School' }} ({{ number_format(count($this->composeSchoolCoaches)) }} coaches) <button type="button" wire:click="clearComposeRecipients">×</button></span>
                                    @elseif($campaignTargetMode === 'list' && $campaignListKey)
                                        <span class="rc-compose-chip-v45">{{ $this->composeSelectedList['label'] ?? 'Selected List' }} ({{ number_format($this->campaignRecipientCount) }} coaches) <button type="button" wire:click="$set('campaignListKey','')">×</button></span>
                                    @elseif($campaignTargetMode === 'coaches' && count($campaignCoachIds))
                                        <span class="rc-compose-chip-v45">{{ number_format(count($campaignCoachIds)) }} selected coaches <button type="button" wire:click="clearComposeCoachSelection">×</button></span>
                                    @else
                                        <em class="rc-subtle">No school selected — search to add one below</em>
                                    @endif

                                    <button type="button" class="rc-compose-tab-v45 {{ $campaignTargetMode === 'school' && $campaignHeadCoachOnly ? 'is-active' : '' }}" wire:click="setComposeSchoolHeadCoachOnly">Head Coach Only</button>
                                    <button type="button" class="rc-compose-tab-v45 {{ $campaignTargetMode === 'school' && ! $campaignHeadCoachOnly ? 'is-active' : '' }}" wire:click="setComposeSchoolAllCoaches">All Coaches</button>
                                    <button type="button" class="rc-compose-tab-v45 {{ $campaignTargetMode === 'coaches' ? 'is-active' : '' }}" wire:click="openComposeCoachChooser">Choose Coaches</button>
                                    <button type="button" class="rc-compose-tab-v45 {{ $composeShowCcBcc ? 'is-active' : '' }}" wire:click="$toggle('composeShowCcBcc')">CC / BCC</button>
                                </div>

                                <div class="rc-compose-school-search-v45" style="margin-top:.65rem;position:relative;max-width:34rem">
                                    <div class="rc-global-search-shell" style="width:100%;height:2.85rem;box-shadow:none">
                                        <svg class="rc-global-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" /></svg>
                                        <input class="rc-global-search-input" style="font-size:.88rem" placeholder="Search for a school..." wire:model.live.debounce.300ms="composeSchoolSearch" />
                                        @if(trim($composeSchoolSearch) !== '')
                                            <button type="button" class="rc-global-search-clear" wire:click="$set('composeSchoolSearch','')" aria-label="Clear school search">×</button>
                                        @endif
                                    </div>

                                    @if(trim($composeSchoolSearch) !== '')
                                        <div class="rc-global-suggestions" style="z-index:95;min-width:100%;max-height:18rem">
                                            @forelse($this->composeSchoolResults as $school)
                                                <?php
                                                    $sid = (string) ($school['id'] ?? '');
                                                    $schoolName = (string) ($school['name'] ?? 'School');
                                                    $schoolLogo = (string) ($school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? '');
                                                    $coachCount = (int) ($school['coach_count'] ?? 0);
                                                    $detail = trim(collect([$school['conference'] ?? null, $school['division'] ?? null])->filter()->implode(' • '));
                                                ?>
                                                <button type="button" class="rc-global-suggestion-item {{ $campaignSchoolId === $sid ? 'is-selected' : '' }}" wire:click="selectComposeSchool(@js($sid))">
                                                    <span class="rc-global-suggestion-icon">
                                                        @if($schoolLogo !== '')
                                                            <img src="{{ $schoolLogo }}" alt="" referrerpolicy="no-referrer" onerror="this.style.display='none';this.parentElement.textContent='{{ $globalSearchInitials($schoolName) }}';">
                                                        @else
                                                            {{ $globalSearchInitials($schoolName) }}
                                                        @endif
                                                    </span>
                                                    <span class="rc-global-suggestion-copy">
                                                        <strong>{{ $schoolName }}</strong>
                                                        <small>{{ $detail !== '' ? $detail : 'Conference unavailable' }} · {{ number_format($coachCount) }} {{ $coachCount === 1 ? 'coach' : 'coaches' }}</small>
                                                    </span>
                                                    <span class="rc-global-suggestion-category">School</span>
                                                </button>
                                            @empty
                                                <div class="rc-empty-state" style="padding:.8rem">No schools found for “{{ $composeSchoolSearch }}”.</div>
                                            @endforelse
                                        </div>
                                    @endif
                                </div>

                                @if($composeShowCcBcc)
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.55rem;margin-top:.65rem;max-width:42rem">
                                        <input class="rc-input" placeholder="CC emails, comma separated" wire:model.live.debounce.500ms="campaignCc" />
                                        <input class="rc-input" placeholder="BCC emails, comma separated" wire:model.live.debounce.500ms="campaignBcc" />
                                    </div>
                                @endif

                                @if($composeChooseCoachesOpen || ($campaignTargetMode === 'coaches' && is_array($this->composeSelectedSchool)))
                                    <div class="rc-compose-coach-grid-v45">
                                        @foreach($this->composeSchoolCoaches as $coach)
                                            <?php $cid = (string) ($coach['id'] ?? ''); $selectedCoach = in_array($cid, $campaignCoachIds, true); ?>
                                            <button type="button" class="rc-compose-coach-pill-v45 {{ $selectedCoach ? 'is-selected' : '' }}" wire:click="toggleCampaignCoach(@js($cid))">
                                                <span class="rc-compose-coach-name-v45"><span class="rc-compose-check-v45">{{ $selectedCoach ? '✓' : '' }}</span><span>{{ $coach['name'] ?? 'Coach' }}</span>@if(str_contains(strtolower((string) ($coach['title'] ?? '')), 'head'))<span style="color:#ff6338;font-size:.62rem;font-weight:800">HC</span>@endif</span>
                                                <span class="rc-compose-coach-title-v45">{{ $coach['title'] ?? 'Coach' }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                    <div style="display:flex;gap:.45rem;margin-top:.55rem">
                                        <button type="button" class="rc-btn" wire:click="selectAllComposeSchoolCoaches">Select all</button>
                                        <button type="button" class="rc-btn" wire:click="clearComposeCoachSelection">Clear coaches</button>
                                    </div>
                                @elseif($campaignTargetMode === 'list')
                                    <div style="margin-top:.65rem;max-width:28rem">
                                        <select class="rc-select" style="width:100%" wire:model.live="campaignListKey">
                                            <option value="">Select a list</option>
                                            @foreach($lists as $list)
                                                <option value="{{ $list['key'] ?? '' }}">{{ $list['label'] ?? 'List' }} ({{ number_format($list['coaches_count'] ?? $list['count'] ?? 0) }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="rc-compose-send-line-v45" style="margin-top:.7rem">
                                    {{ $this->composeSendingDescription }}
                                </div>
                            </div>

                            <div>
                                <div class="rc-compose-label-v45">Subject Line</div>
                                <div class="rc-compose-field-row-v45">
                                    <input class="rc-input" style="width:100%" placeholder="Subject line" wire:model.live.debounce.500ms="campaignSubject" />
                                    <div class="rc-compose-template-wrap-v45" x-data="{open:false}">
                                        <button class="rc-btn" type="button" x-on:click="open=!open">
                                            <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /></svg>
                                            Templates
                                        </button>
                                        <div x-cloak x-show="open" x-on:click.outside="open=false" class="rc-compose-template-menu-v45">
                                            <div class="rc-subtle" style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;padding:.25rem .4rem">Choose a template</div>
                                            @forelse($this->composeTemplateOptions as $template)
                                                <button type="button" class="{{ (string) ($campaignTemplateId ?? '') === (string) ($template['id'] ?? '') ? 'is-active' : '' }}" wire:click="useTemplateForCompose(@js((string) ($template['id'] ?? '')))" wire:loading.attr="disabled" wire:target="useTemplateForCompose" x-on:click="open=false">
                                                    <strong>{{ $template['name'] ?? 'Untitled Template' }}</strong>
                                                    <span>{{ $template['compose_subject_preview'] ?? 'Recruiting email' }}</span>
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
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'SchoolName'})">@{{SchoolName}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'CoachTitle'})">@{{CoachTitle}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'AthleteName'})">@{{AthleteName}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'ProfileLink'})">@{{ProfileLink}}</button>
                                    <button class="rc-compose-var-v45" type="button" x-data x-on:click="$dispatch('plyr-editor-insert-token',{token:'HighlightLink'})">@{{HighlightLink}}</button>
                                </div>
                            </div>

                            <div x-data="plyrNativeEditorBase('campaignBody')" x-init="mount(); window.addEventListener('plyr-editor-insert-token', e => insertMerge(e.detail.token))" wire:key="compose-email-editor-v45-{{ $campaignTemplateId ?: 'blank' }}">
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
                                    <input x-ref="campaignBodyHidden" type="hidden" data-plyr-native-editor-hidden="campaign-body" wire:model.live.debounce.800ms="campaignBody" />
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

            @if($showComposePreview)
                <div class="rc-compose-modal-v45" wire:click.self="closeComposePreview">
                    <div style="width:min(56rem,94vw);max-height:86vh;overflow:auto;border:1px solid var(--rc-border);border-radius:1rem;background:var(--rc-surface);box-shadow:0 24px 80px rgba(0,0,0,.30);">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--rc-border)">
                            <div><strong>Preview Email</strong><div class="rc-subtle">{{ $this->composeRenderedSubject }}</div></div>
                            <button type="button" class="rc-icon-button" wire:click="closeComposePreview">×</button>
                        </div>
                        <div style="padding:1rem;background:var(--rc-soft)">
                            <div style="background:var(--rc-surface);border:1px solid var(--rc-border);border-radius:.85rem;padding:1.25rem;line-height:1.6">
                                {!! $this->composeRenderedBody !!}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
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
                                    <button class="rc-template-use-v52" type="button" wire:click="useTemplateForCompose({{ \Illuminate\Support\Js::from($templateId) }})" wire:loading.attr="disabled" wire:target="useTemplateForCompose({{ \Illuminate\Support\Js::from($templateId) }})">
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

        @if($this->selectedSchool)
            @php
                $slideSchool = $this->selectedSchool;
                $slideSchoolId = (string) ($slideSchool['id'] ?? $slideSchool['business_id'] ?? '');
                $slideSchoolName = (string) ($slideSchool['name'] ?? 'School');
                $slideDivision = (string) ($slideSchool['division'] ?? 'Division');
                $slideConference = (string) ($slideSchool['conference'] ?? 'Conference unavailable');
                $slideLocation = trim((string) (($slideSchool['city'] ?? '') . ((!empty($slideSchool['city']) && !empty($slideSchool['state'])) ? ', ' : '') . ($slideSchool['state'] ?? '')));
                $slideCoaches = collect($slideSchool['coaches'] ?? [])->values();
                $slideReplies = (int) ($slideSchool['replies'] ?? $slideSchool['coach_replies'] ?? 0);
                $slideClicks = (int) ($slideSchool['link_clicks'] ?? $slideSchool['trigger_link_clicks'] ?? $slideSchool['trigger_clicks'] ?? 0);
                $slideViews = (int) (($slideSchool['profile_views'] ?? 0) + ($slideSchool['highlight_views'] ?? 0));
                $slideEmails = (int) ($slideSchool['emails_sent'] ?? $slideSchool['sent_emails'] ?? $slideSchool['email_count'] ?? 0);
                $slideScore = (int) ($slideSchool['lead_score'] ?? $slideSchool['engagement_score'] ?? max(0, ($slideReplies * 20) + ($slideClicks * 6) + ($slideViews * 2)));
                $slideLogo = trim((string) ($slideSchool['logo_url'] ?? $slideSchool['school_logo_url'] ?? $slideSchool['business_logo_url'] ?? data_get($slideSchool, 'business.logo') ?? data_get($slideSchool, 'contact.school_logo') ?? data_get($slideSchool, 'head_coach.school_logo_url') ?? data_get($slideSchool, 'head_coach.business_logo_url') ?? ''));
                if ($slideLogo === '') {
                    foreach ($slideCoaches as $coach) {
                        $slideLogo = trim((string) (($coach['school_logo_url'] ?? '') ?: ($coach['business_logo_url'] ?? '') ?: ($coach['logo_url'] ?? '')));
                        if ($slideLogo !== '') break;
                    }
                }
                $slideInitials = strtoupper(collect(preg_split('/\s+/', trim($slideSchoolName)) ?: [])->filter()->map(fn($part) => substr((string) $part, 0, 1))->take(2)->implode('') ?: 'S');
                $listRows = collect($this->lists ?? [])->filter(fn($list) => is_array($list))->values();
                $schoolListKeys = collect($slideSchool['list_keys'] ?? [])->merge($slideSchool['lists'] ?? [])->map(fn($key) => strtolower(trim((string) $key)))->filter()->unique()->values();
                $initialListMemberships = $listRows->mapWithKeys(function ($listRow) use ($schoolListKeys) {
                    $key = (string) ($listRow['key'] ?? '');
                    return $key === '' ? [] : [$key => $schoolListKeys->contains(strtolower($key))];
                })->all();
            @endphp

            <div class="rc-drawer rc-school-modal-backdrop rc-ui-stable-modal" wire:key="school-drawer-{{ $slideSchoolId }}" x-on:click.self="if ($el.classList.contains('rc-stack-top')) { window.rcRequestSchoolClose($el); }" data-rc-modal="school" data-rc-modal-id="school-{{ $slideSchoolId }}" data-rc-school-id="{{ $slideSchoolId }}" data-rc-drawer-backdrop>
                <div class="rc-drawer-panel rc-school-modal-panel rc-ui-stable-panel" wire:ignore.self x-data="window.rcSchoolDrawer ? window.rcSchoolDrawer({{ \Illuminate\Support\Js::from($slideSchoolId) }}, {{ \Illuminate\Support\Js::from($initialListMemberships) }}, @js((bool) ($slideSchool['is_favorite'] ?? false))) : { tab: 'coaches', listsOpen: false, optimisticLists: {{ \Illuminate\Support\Js::from($initialListMemberships) }}, isInList(key, value) { return Object.prototype.hasOwnProperty.call(this.optimisticLists || {}, String(key || '')) ? Boolean(this.optimisticLists[String(key || '')]) : Boolean(value) }, hasAnyList() { return Object.entries(this.optimisticLists || {}).some(([key, value]) => this.isInList(key, value)) }, isListPending() { return false }, toggleList() {} }" x-init="init && init()" x-on:click.stop x-on:pointerdown.stop x-on:mousedown.stop x-on:touchstart.stop data-rc-interaction-boundary role="dialog" aria-modal="true" aria-label="{{ $slideSchoolName }} details">
                    <button class="rc-school-modal-close" type="button" data-rc-instant-close x-on:pointerdown.prevent.stop="window.rcRequestSchoolClose($el)" x-on:click.prevent.stop aria-label="Close school details">×</button>

                    <div class="rc-school-modal-hero-v72">
                        <div class="rc-school-logo-large-v72">
                            @if($slideLogo !== '')
                                <img src="{{ $slideLogo }}" alt="{{ $slideSchoolName }} logo" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove();">
                            @else
                                <span>{{ $slideInitials }}</span>
                            @endif
                        </div>
                        <div class="rc-school-modal-main">
                            <span class="rc-school-division-pill">{{ $slideDivision }}</span>
                            <h2>{{ $slideSchoolName }}</h2>
                            <div class="rc-school-modal-meta">
                                <span>◎ {{ $slideConference }}</span>
                                @if($slideLocation !== '')
                                    <span>· {{ $slideLocation }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="rc-school-score-wrap">
                            <div class="rc-school-score-ring">{{ max(0, min(100, $slideScore)) }}</div>
                            <div class="rc-school-score-label">{{ $slideScore >= 70 ? 'HOT' : ($slideScore >= 35 ? 'WARM' : 'NEW') }}</div>
                        </div>
                    </div>

                    <div class="rc-school-modal-actions-v72">
                        <button class="rc-school-action rc-school-action-primary" type="button" wire:click="composeEmailSchool({{ \Illuminate\Support\Js::from($slideSchoolId) }})" data-rc-local-action wire:loading.attr="disabled" wire:target="composeEmailSchool">
                            <span wire:loading.remove wire:target="composeEmailSchool">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6.5h16v11H4v-11Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m4.5 7 7.5 6 7.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <span class="rc-action-spinner-v81" wire:loading wire:target="composeEmailSchool"></span>
                            <span>Email Coaches</span>
                        </button>

                        <button
                            class="rc-school-action"
                            type="button"
                            data-rc-local-action
                            data-rc-drawer-action
                            x-on:click.stop="toggleFavorite()"
                            x-bind:class="{ 'is-favorited': favorite }"
                            x-bind:aria-pressed="favorite ? 'true' : 'false'"
                        >
                            <span x-show="!favoritePending"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 3.8 2.48 5.03 5.55.8-4.02 3.91.95 5.53L12 16.46l-4.96 2.61.95-5.53-4.02-3.91 5.55-.8L12 3.8Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></span>
                            <span class="rc-action-spinner-v81" x-cloak x-show="favoritePending"></span>
                            <span x-text="favorite ? 'Favorited' : 'Favorite'"></span>
                        </button>

                        <div class="rc-school-list-dropdown-v72" x-on:click.outside="listsOpen=false" x-on:click.stop data-rc-dropdown-boundary>
                            <button
                                class="rc-school-action"
                                type="button"
                                x-on:click.stop="listsOpen=!listsOpen"
                                x-bind:class="{ 'is-in-list': Object.values(optimisticLists || {}).some(value => Boolean(value)) }"
                                x-bind:aria-expanded="listsOpen ? 'true' : 'false'"
                                aria-haspopup="menu"
                            >
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                                <span x-text="Object.values(optimisticLists || {}).some(value => Boolean(value)) ? 'In List' : 'Add to List'"></span>
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m7 10 5 5 5-5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div class="rc-school-list-menu-v72" x-cloak x-show="listsOpen" x-on:click.stop role="menu">
                                <h4>Add to a list</h4>
                                @forelse($listRows as $listRow)
                                    @php
                                        $listKey = (string) ($listRow['key'] ?? '');
                                        $listLabel = (string) ($listRow['label'] ?? $listRow['name'] ?? \Illuminate\Support\Str::headline($listKey));
                                        $defaultListColors = [
                                            'dream' => '#ff6338',
                                            'target' => '#3b82f6',
                                            'safety' => '#22c55e',
                                            'camp_follow_up' => '#f59e0b',
                                            'showcase_follow_up' => '#8b5cf6',
                                            'general_recruiting' => '#64748b',
                                        ];
                                        $listColor = (string) ($listRow['color'] ?? $defaultListColors[$listKey] ?? '#ff6338');
                                        $inList = $schoolListKeys->contains(strtolower($listKey));
                                        $count = (int) ($listRow['schools_count'] ?? count($listRow['schools'] ?? []));
                                    @endphp
                                    <button type="button" class="{{ $inList ? 'is-active' : '' }}" style="--list-color: {{ $listColor }}" data-rc-local-action data-rc-drawer-action x-on:click.stop="toggleList({{ \Illuminate\Support\Js::from($listKey) }}, @js($inList)); listsOpen = true" x-bind:class="{ 'is-active': isInList({{ \Illuminate\Support\Js::from($listKey) }}, @js($inList)), 'is-saving': isListPending({{ \Illuminate\Support\Js::from($listKey) }}) }" x-bind:data-list-pending="isListPending({{ \Illuminate\Support\Js::from($listKey) }}) ? 'true' : 'false'" wire:key="school-drawer-list-{{ $slideSchoolId }}-{{ $listKey }}" role="menuitemcheckbox" x-bind:aria-checked="isInList({{ \Illuminate\Support\Js::from($listKey) }}, @js($inList)) ? 'true' : 'false'">
                                        <span class="rc-list-check-v81"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10.5 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                        <span class="rc-school-list-label-v87"><span class="rc-school-list-dot-v72" style="--dot: {{ $listColor }}"></span><span>{{ $listLabel }}</span></span>
                                        <small class="rc-list-count-v81">{{ $count }}</small>
                                    </button>
                                @empty
                                    <button type="button" wire:click="$set('showNewListComposer', true)" data-rc-local-action data-rc-drawer-action x-on:click.stop="listsOpen = true">Create a list first</button>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="rc-school-modal-rule"></div>

                    <div class="rc-school-tabbar-v72">
                        <button type="button" class="rc-school-tab-v72" x-bind:class="tab === 'coaches' ? 'is-active' : ''" x-on:click="tab='coaches'">Coaching Staff</button>
                        <button type="button" class="rc-school-tab-v72" x-bind:class="tab === 'roster' ? 'is-active' : ''" x-on:click="tab='roster'">Roster & Stats</button>
                        <button type="button" class="rc-school-tab-v72" x-bind:class="tab === 'comms' ? 'is-active' : ''" x-on:click="tab='comms'">Communications</button>
                    </div>

                    <section class="rc-school-tab-panel-v72" x-show="tab === 'coaches'">
                        <div class="rc-school-coach-list rc-school-modal-coaches" style="max-height:22rem;overflow:auto;padding-right:.15rem;">
                            @forelse($slideCoaches as $coach)
                                @php
                                    $coachName = (string) ($coach['name'] ?? trim(($coach['first_name'] ?? '') . ' ' . ($coach['last_name'] ?? '')) ?: 'Coach');
                                    $coachTitle = (string) ($coach['title'] ?? $coach['position'] ?? 'Coach');
                                    $coachEmail = (string) ($coach['email'] ?? '');
                                    $coachInitials = collect(explode(' ', $coachName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
                                    $isHead = str_contains(strtolower($coachTitle), 'head');
                                @endphp
                                <div class="rc-school-coach-card">
                                    <div class="rc-school-coach-avatar">{{ strtoupper($coachInitials ?: 'C') }}</div>
                                    <div class="rc-school-coach-info">
                                        <strong>{{ $coachName }} @if($isHead)<span class="rc-head-coach-chip">Head Coach</span>@endif</strong>
                                        <span>{{ $coachTitle }}</span>
                                        @if($coachEmail !== '')<a href="mailto:{{ $coachEmail }}">{{ $coachEmail }}</a>@endif
                                    </div>
                                    @if((string) ($coach['id'] ?? '') !== '')<button class="rc-school-copy-btn" type="button" wire:click="composeEmailCoach({{ \Illuminate\Support\Js::from((string) ($coach['id'] ?? '')) }})" data-rc-local-action wire:loading.attr="disabled" wire:target="composeEmailCoach" title="Email coach"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="M4 6.5h16v11H4v-11Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m4.5 7 7.5 6 7.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>@endif
                                </div>
                            @empty
                                <div class="rc-empty">No coaches loaded for this school yet.</div>
                            @endforelse
                        </div>
                    </section>

                    <section class="rc-school-tab-panel-v72" x-show="tab === 'roster'">
                        <div class="rc-coming-soon-v72"><div><strong>Coming Soon</strong><span>Roster and advanced school stats will be available here soon.</span></div></div>
                    </section>

                    <section class="rc-school-tab-panel-v72" x-show="tab === 'comms'">
                        <div class="rc-school-stat-grid">
                            <div class="rc-school-stat-card"><span><svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M4 6.5h16v11H4v-11Z" stroke="currentColor" stroke-width="1.8"/><path d="m4.5 7 7.5 6 7.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span><strong>{{ number_format($slideEmails) }}</strong><small>Emails</small></div>
                            <div class="rc-school-stat-card"><span><svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg></span><strong>{{ number_format($slideViews) }}</strong><small>Views</small></div>
                            <div class="rc-school-stat-card"><span><svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M7 17 17 7M9 7h8v8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><strong>{{ number_format($slideClicks) }}</strong><small>Clicks</small></div>
                            <div class="rc-school-stat-card"><span><svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M9 10 4 15l5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 4v7a4 4 0 0 1-4 4H4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span><strong>{{ number_format($slideReplies) }}</strong><small>Replies</small></div>
                        </div>
                    </section>
                </div>
            </div>
        @endif
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
                const iconReplacement = socialIcon(item);
                const replacement = iconReplacement || ('<a' + classAttr + ' href="{{' + item.token + '}}" target="_blank" style="' + item.style + '">' + item.label + '</a>');
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
                mount() {
                    if (this.mounted) return;
                    this.mounted = true;
                    this.$nextTick(() => {
                        this.bootEditor();
                        setTimeout(() => this.bootEditor(true), 80);
                        setTimeout(() => this.bootEditor(true), 250);
                    });
                    window.addEventListener('rc-compose-editor-refresh', (event) => {
                        if (modelName !== 'campaignBody' || !this.$refs.editor) return;
                        const encoded = event.detail?.body || '';
                        const html = this.decodeInitialBody(encoded);
                        this.$refs.editor.innerHTML = this.highlightMergeTokens(html || '');
                        this.syncNow();
                    });
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
                    this.focusEditor();
                    document.execCommand('insertHTML', false, html);
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
                    this.focusEditor();
                    document.execCommand('insertHTML', false, html);
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

</x-filament-panels::page>