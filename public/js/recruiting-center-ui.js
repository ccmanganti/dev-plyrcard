(() => {
    if (window.__recruitingCenterUiLoaded) return;
    window.__recruitingCenterUiLoaded = true;

    const state = {
        pending: new Set(),
        fallbackTimer: null,
        shellTimer: null,
        shellSafetyTimer: null,
        hooked: false,
        scheduledCalls: new Set(),
        drawerScrollTop: new Map(),
        activeInteractiveRequest: false,
        pendingSchoolOpen: false,
        pendingSchoolOpenId: null,
        livewireRequestsInFlight: 0,
        deferredSchoolCloseIds: new Set(),
        deferredSchoolCloseTimer: null,
        schoolCloseRequestInFlight: false,
        actionStatusTimer: null,
        lastActionCompletion: null,
    };

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const progress = () => qs('#rc-ui-progress');
    const shell = () => qs('#rc-ui-instant-shell');
    const own = (object, key) => Object.prototype.hasOwnProperty.call(object || {}, key);

    // A locally closed overlay must stay closed while older background Livewire
    // requests finish. Otherwise an optimistic favorite/list update can morph the
    // same drawer back into the DOM and lock page scrolling again.
    const suppressedOverlayIds = window.__rcSuppressedOverlayIds || new Set();
    const closedSchoolIds = window.__rcClosedSchoolIds || new Set();
    window.__rcSuppressedOverlayIds = suppressedOverlayIds;
    window.__rcClosedSchoolIds = closedSchoolIds;

    const drawerStateStore = window.__rcSchoolDrawerStateStore || new Map();
    const statsDrawerStateStore = window.__rcStatsDrawerStateStore || new Map();
    window.__rcSchoolDrawerStateStore = drawerStateStore;
    window.__rcStatsDrawerStateStore = statsDrawerStateStore;

    const persistSchoolState = (key, controller) => {
        drawerStateStore.set(key, {
            tab: controller.tab || 'coaches',
            listsOpen: Boolean(controller.listsOpen),
            optimisticLists: { ...(controller.optimisticLists || {}) },
            pendingLists: { ...(controller.pendingLists || {}) },
            queuedChanges: { ...(controller.queuedChanges || {}) },
            favorite: Boolean(controller.favorite),
            favoritePending: Boolean(controller.favoritePending),
        });
    };

    window.rcSchoolDrawer = (schoolId, initialMemberships = {}, initialFavorite = false) => {
        const key = String(schoolId || 'school');
        const saved = drawerStateStore.get(key) || {};
        const initial = { ...initialMemberships };
        const optimistic = { ...(saved.optimisticLists || {}) };
        const pending = { ...(saved.pendingLists || {}) };

        Object.entries(initial).forEach(([listKey, value]) => {
            // A completed Livewire response contains the optimistically updated
            // cache. When no newer click is queued, treat that server value as
            // authoritative and clear the row-level spinner after morphing.
            if (!own(saved.queuedChanges || {}, listKey)) {
                optimistic[listKey] = Boolean(value);
                pending[listKey] = false;
            } else if (!own(optimistic, listKey)) {
                optimistic[listKey] = Boolean(value);
            }
        });

        return {
            tab: saved.tab || 'coaches',
            listsOpen: Boolean(saved.listsOpen),
            optimisticLists: optimistic,
            pendingLists: pending,
            queuedChanges: { ...(saved.queuedChanges || {}) },
            favorite: Object.prototype.hasOwnProperty.call(saved, 'favorite') ? Boolean(saved.favorite) : Boolean(initialFavorite),
            favoritePending: Boolean(saved.favoritePending),
            favoriteInFlight: false,
            favoriteNeedsFlush: false,
            sendInFlight: false,
            needsFlush: false,
            flushTimer: null,

            init() {
                persistSchoolState(key, this);
                this.$watch('tab', () => persistSchoolState(key, this));
                this.$watch('listsOpen', () => persistSchoolState(key, this));

                // A component morph may create a fresh Alpine instance while a prior
                // instance has already staged checkbox changes. Flush them here too.
                if (Object.keys(this.queuedChanges).length) this.scheduleListFlush(30);
            },

            async toggleFavorite() {
                this.favorite = !this.favorite;
                this.favoritePending = true;
                this.favoriteNeedsFlush = true;
                persistSchoolState(key, this);

                if (this.favoriteInFlight) return;

                while (this.favoriteNeedsFlush) {
                    this.favoriteNeedsFlush = false;
                    const desired = Boolean(this.favorite);
                    this.favoriteInFlight = true;

                    try {
                        const result = await this.$wire.call('queueSchoolFavoriteState', key, desired);
                        if (!result || result.success === false) {
                            if (!this.favoriteNeedsFlush) this.favorite = !desired;
                            showToast(result?.error || 'Unable to update this favorite.', 'error');
                        } else {
                            beginActionStatusWatch();
                        }
                    } catch (error) {
                        if (!this.favoriteNeedsFlush) this.favorite = !desired;
                        console.error(error);
                        showToast('Unable to update this favorite.', 'error');
                    } finally {
                        this.favoriteInFlight = false;
                        if (!this.favoriteNeedsFlush) this.favoritePending = false;
                        persistSchoolState(key, this);
                    }
                }
            },

            isInList(listKey, serverValue = false) {
                const normalized = String(listKey || '');
                return own(this.optimisticLists, normalized)
                    ? Boolean(this.optimisticLists[normalized])
                    : Boolean(serverValue);
            },

            isListPending(listKey) {
                return Boolean(this.pendingLists[String(listKey || '')]);
            },

            toggleList(listKey, serverValue = false) {
                const normalized = String(listKey || '');
                if (!normalized) return;

                const desired = !this.isInList(normalized, serverValue);
                this.optimisticLists[normalized] = desired;
                this.pendingLists[normalized] = true;
                this.queuedChanges[normalized] = desired;
                this.listsOpen = true;
                persistSchoolState(key, this);
                this.scheduleListFlush(90);
            },

            scheduleListFlush(delay = 90) {
                clearTimeout(this.flushTimer);
                this.flushTimer = setTimeout(() => this.flushListChanges(), delay);
            },

            async flushListChanges() {
                if (this.sendInFlight) {
                    this.needsFlush = true;
                    return;
                }

                const batch = { ...this.queuedChanges };
                if (!Object.keys(batch).length) return;

                this.queuedChanges = {};
                this.sendInFlight = true;
                this.needsFlush = false;
                persistSchoolState(key, this);

                try {
                    const result = await this.$wire.call('queueSchoolListMemberships', key, batch);
                    if (!result || result.success === false) {
                        Object.entries(batch).forEach(([listKey, desired]) => {
                            if (!own(this.queuedChanges, listKey)) {
                                this.optimisticLists[listKey] = !Boolean(desired);
                            }
                        });
                        window.dispatchEvent(new CustomEvent('rc-action-error', {
                            detail: { message: result?.error || 'Unable to update the selected lists.' },
                        }));
                    } else {
                        beginActionStatusWatch();
                    }
                } catch (error) {
                    Object.entries(batch).forEach(([listKey, desired]) => {
                        if (!own(this.queuedChanges, listKey)) {
                            this.optimisticLists[listKey] = !Boolean(desired);
                        }
                    });
                    console.error(error);
                } finally {
                    Object.keys(batch).forEach((listKey) => {
                        if (!own(this.queuedChanges, listKey)) this.pendingLists[listKey] = false;
                    });
                    this.sendInFlight = false;
                    persistSchoolState(key, this);

                    if (this.needsFlush || Object.keys(this.queuedChanges).length) {
                        this.scheduleListFlush(25);
                    }
                }
            },
        };
    };

    const showToast = (message, type = 'success') => {
        const text = String(message || '').trim();
        if (!text) return;

        // Use Filament's native notification system instead of a custom fixed
        // toast that can overlap drawer controls such as the close button.
        const instance = component();
        if (!instance?.call) {
            console[type === 'error' ? 'error' : 'log'](text);
            return;
        }

        const normalizedType = type === 'error' ? 'danger' : type;
        instance.call('notifyRecruitingUi', text, normalizedType).catch((error) => {
            console.error(error);
        });
    };

    const beginActionStatusWatch = () => {
        clearTimeout(state.actionStatusTimer);

        const check = async () => {
            const instance = component();
            if (!instance?.call) {
                state.actionStatusTimer = setTimeout(check, 600);
                return;
            }

            try {
                const status = await instance.call('pollCoachDatabaseActionStatus');
                const current = String(status?.status || 'idle');
                const completion = String(status?.completed_at || status?.updated_at || '');

                if (current === 'completed' || current === 'completed_with_errors') {
                    const token = `${current}:${completion}:${status?.processed || 0}:${status?.failed || 0}`;
                    state.lastActionCompletion = token;
                    // pollCoachDatabaseActionStatus() sends the deduplicated
                    // Filament notification. No custom overlay is created here.
                    return;
                }

                if (current === 'queued' || current === 'running') {
                    state.actionStatusTimer = setTimeout(check, 700);
                }
            } catch (error) {
                console.error(error);
                state.actionStatusTimer = setTimeout(check, 1200);
            }
        };

        state.actionStatusTimer = setTimeout(check, 250);
    };

    const discoverSelectionStores = window.__rcDiscoverSelectionStores || new Map();
    window.__rcDiscoverSelectionStores = discoverSelectionStores;

    window.rcDiscoverSelection = (initialIds = []) => {
        const key = window.location.pathname;
        let store = discoverSelectionStores.get(key);
        if (!store) {
            store = {
                selected: new Set((initialIds || []).map((id) => String(id))),
                dirty: false,
                inFlight: false,
                needsFlush: false,
                timer: null,
            };
            discoverSelectionStores.set(key, store);
        } else if (!store.dirty && !store.inFlight) {
            store.selected = new Set((initialIds || []).map((id) => String(id)));
        }

        return {
            revision: 0,
            init() { this.revision += 1; },
            isSelected(id) {
                this.revision;
                return store.selected.has(String(id));
            },
            toggle(id) {
                const normalized = String(id || '');
                if (!normalized) return;
                if (store.selected.has(normalized)) store.selected.delete(normalized);
                else store.selected.add(normalized);
                store.dirty = true;
                store.needsFlush = true;
                this.revision += 1;
                clearTimeout(store.timer);
                store.timer = setTimeout(() => this.flush(), 45);
            },
            async flush() {
                if (store.inFlight) {
                    store.needsFlush = true;
                    return;
                }

                store.inFlight = true;
                store.needsFlush = false;
                const snapshot = Array.from(store.selected);

                try {
                    const result = await this.$wire.call('setSelectedSchoolIds', snapshot);
                    if (!result || result.success === false) {
                        showToast(result?.error || 'Unable to update selected schools.', 'error');
                    }
                } catch (error) {
                    console.error(error);
                    showToast('Unable to update selected schools.', 'error');
                } finally {
                    store.inFlight = false;
                    store.dirty = store.needsFlush;
                    if (store.needsFlush) {
                        clearTimeout(store.timer);
                        store.timer = setTimeout(() => this.flush(), 20);
                    }
                }
            },
        };
    };

    window.rcBulkSchoolList = () => ({
        open: false,
        pendingLists: {},
        statusText: '',
        watchTimer: null,
        watching: false,

        isPending(listKey) {
            return Boolean(this.pendingLists[String(listKey || '')]);
        },

        pendingCount() {
            return Object.keys(this.pendingLists).length;
        },

        async queue(listKey, listLabel, selectedCount) {
            const key = String(listKey || '').trim();
            if (!key) return;

            const label = String(listLabel || key);
            this.pendingLists = { ...this.pendingLists, [key]: label };
            this.statusText = `Adding ${Number(selectedCount || 0).toLocaleString()} selected school(s) to ${label} in the background...`;
            this.open = false;

            try {
                const result = await this.$wire.call('queueSelectedSchoolsToList', key);
                if (!result || result.success === false) {
                    const next = { ...this.pendingLists };
                    delete next[key];
                    this.pendingLists = next;
                    this.statusText = this.pendingCount() ? this.statusText : '';
                    showToast(result?.error || 'Unable to queue the selected schools.', 'error');
                    return;
                }

                const count = Number(result.school_count || selectedCount || 0);
                const resolvedLabel = String(result.list_label || label);
                this.statusText = `Adding ${count.toLocaleString()} selected school(s) to ${resolvedLabel} in the background...`;
                beginActionStatusWatch();
                this.watchUntilComplete();
            } catch (error) {
                const next = { ...this.pendingLists };
                delete next[key];
                this.pendingLists = next;
                this.statusText = this.pendingCount() ? this.statusText : '';
                console.error(error);
                showToast('Unable to queue the selected schools.', 'error');
            }
        },

        watchUntilComplete() {
            if (this.watching) return;
            this.watching = true;
            clearTimeout(this.watchTimer);

            const check = async () => {
                try {
                    const status = await this.$wire.call('pollCoachDatabaseActionStatus');
                    const current = String(status?.status || 'idle');

                    if (current === 'completed' || current === 'completed_with_errors') {
                        this.pendingLists = {};
                        this.statusText = '';
                        this.watching = false;
                        return;
                    }

                    if (this.pendingCount() > 0) {
                        this.watchTimer = setTimeout(check, current === 'running' ? 650 : 400);
                        return;
                    }

                    this.watching = false;
                } catch (error) {
                    console.error(error);
                    this.watchTimer = setTimeout(check, 1000);
                }
            };

            this.watchTimer = setTimeout(check, 300);
        },
    });

    const modalIdentity = (modal) => String(modal?.dataset?.rcModalId || modal?.id || '');
    const schoolIdentity = (modal) => String(modal?.dataset?.rcSchoolId || '');

    const isOverlaySuppressed = (modal) => {
        if (!modal) return false;
        const id = modalIdentity(modal);
        const schoolId = schoolIdentity(modal);
        return Boolean((id && suppressedOverlayIds.has(id)) || (schoolId && closedSchoolIds.has(schoolId)));
    };

    const applySuppressedOverlayState = (modal) => {
        if (!modal || !isOverlaySuppressed(modal)) return false;

        modal.dataset.rcClosedLocally = '1';
        modal.classList.add('rc-ui-closed-now');
        modal.classList.remove('rc-stack-top', 'rc-stack-layer', 'rc-stack-underlay', 'rc-ui-leaving');
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('inert', '');
        modal.style.setProperty('display', 'none', 'important');
        modal.style.setProperty('visibility', 'hidden', 'important');
        modal.style.setProperty('pointer-events', 'none', 'important');
        modal.style.setProperty('opacity', '0', 'important');
        return true;
    };

    const clearImmediateCloseState = (modal) => {
        if (!modal) return;
        modal.classList.remove('rc-ui-closed-now', 'rc-ui-leaving');
        delete modal.dataset.rcClosedLocally;
        modal.style.removeProperty('display');
        modal.style.removeProperty('visibility');
        modal.style.removeProperty('pointer-events');
        modal.style.removeProperty('opacity');
        modal.removeAttribute('inert');
    };

    const hideOverlayNodeNow = (modal) => {
        if (!modal) return false;

        modal.dataset.rcClosedLocally = '1';
        modal.classList.add('rc-ui-closed-now');
        modal.classList.remove('rc-stack-top', 'rc-stack-layer', 'rc-stack-underlay', 'rc-ui-leaving');
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('inert', '');
        modal.style.setProperty('display', 'none', 'important');
        modal.style.setProperty('visibility', 'hidden', 'important');
        modal.style.setProperty('pointer-events', 'none', 'important');
        modal.style.setProperty('opacity', '0', 'important');
        return true;
    };

    const scheduleDeferredSchoolClose = (schoolId) => {
        const id = String(schoolId || '');
        if (!id) return;
        state.deferredSchoolCloseIds.add(id);
        clearTimeout(state.deferredSchoolCloseTimer);
        state.deferredSchoolCloseTimer = setTimeout(flushDeferredSchoolClose, 20);
    };

    const flushDeferredSchoolClose = () => {
        clearTimeout(state.deferredSchoolCloseTimer);

        if (state.schoolCloseRequestInFlight || state.livewireRequestsInFlight > 0) {
            state.deferredSchoolCloseTimer = setTimeout(flushDeferredSchoolClose, 40);
            return;
        }

        const schoolId = state.deferredSchoolCloseIds.values().next().value;
        if (!schoolId) return;

        state.deferredSchoolCloseIds.delete(schoolId);
        const instance = component();
        if (!instance?.call) {
            state.deferredSchoolCloseIds.add(schoolId);
            state.deferredSchoolCloseTimer = setTimeout(flushDeferredSchoolClose, 80);
            return;
        }

        state.schoolCloseRequestInFlight = true;
        Promise.resolve(instance.call('closeSchoolIfSelected', schoolId))
            .catch((error) => console.error(error))
            .finally(() => {
                state.schoolCloseRequestInFlight = false;
                refreshOverlayStack();
                if (state.deferredSchoolCloseIds.size) {
                    state.deferredSchoolCloseTimer = setTimeout(flushDeferredSchoolClose, 20);
                }
            });
    };

    window.rcCloseOverlayNow = (source) => {
        const modal = source?.matches?.('.rc-school-modal-backdrop, .rc-stats-drawer-backdrop, .rc-preview-modal-backdrop')
            ? source
            : source?.closest?.('.rc-school-modal-backdrop, .rc-stats-drawer-backdrop, .rc-preview-modal-backdrop');

        if (!modal) return false;

        const modalId = modalIdentity(modal);
        const schoolId = schoolIdentity(modal);
        if (modalId) suppressedOverlayIds.add(modalId);

        if (modal.matches('.rc-school-modal-backdrop') && schoolId) {
            closedSchoolIds.add(schoolId);
            if (!state.pendingSchoolOpenId || String(state.pendingSchoolOpenId) === schoolId) {
                state.pendingSchoolOpen = false;
                state.pendingSchoolOpenId = null;
            }
        }

        if (modal.matches('.rc-stats-drawer-backdrop') && modalId) {
            statsDrawerStateStore.set(modalId, { open: false });
        }

        hideOverlayNodeNow(modal);
        refreshOverlayStack();
        return true;
    };

    window.rcRequestSchoolClose = (source) => {
        const modal = source?.matches?.('.rc-school-modal-backdrop')
            ? source
            : source?.closest?.('.rc-school-modal-backdrop');
        if (!modal) return false;

        window.rcCloseOverlayNow(modal);
        closeShell(true);
        return true;
    };

    window.rcOpenOverlayNow = (modalId) => {
        const id = String(modalId || '');
        if (!id) return;
        const escaped = window.CSS?.escape ? window.CSS.escape(id) : id.replace(/["\\]/g, '\\$&');
        const modal = document.querySelector(`[data-rc-modal-id="${escaped}"]`);
        suppressedOverlayIds.delete(id);
        const schoolId = schoolIdentity(modal);
        if (schoolId) {
            closedSchoolIds.delete(schoolId);
            state.deferredSchoolCloseIds.delete(schoolId);
        }
        clearImmediateCloseState(modal);
        requestAnimationFrame(refreshOverlayStack);
    };

    window.rcStatsDrawer = (drawerId, initialOpen = false, syncBack = false) => {
        const key = String(drawerId || 'stats');
        const saved = statsDrawerStateStore.get(key);
        const shouldOpen = saved ? Boolean(saved.open) : Boolean(initialOpen);

        return {
            open: shouldOpen,
            syncBack: Boolean(syncBack),
            init() {
                if (initialOpen) {
                    window.rcOpenOverlayNow?.(key);
                    this.open = true;
                }
                statsDrawerStateStore.set(key, { open: Boolean(this.open) });
                this.$watch('open', (value) => {
                    statsDrawerStateStore.set(key, { open: Boolean(value) });
                    requestAnimationFrame(refreshOverlayStack);
                });
            },
            openDrawer() {
                window.rcOpenOverlayNow?.(key);
                this.open = true;
                statsDrawerStateStore.set(key, { open: true });
                requestAnimationFrame(refreshOverlayStack);
            },
            close() {
                this.open = false;
                statsDrawerStateStore.set(key, { open: false });
                if (this.syncBack) setTimeout(() => this.$wire.set('section', 'dashboard'), 0);
                requestAnimationFrame(refreshOverlayStack);
            },
        };
    };

    const overlaySelector = [
        '#rc-ui-instant-shell.is-open',
        '.rc-school-modal-backdrop',
        '.rc-stats-drawer-backdrop',
        '.rc-preview-modal-backdrop',
        '.fi-modal-window',
    ].join(',');

    const isVisible = (node) => {
        if (!node?.isConnected) return false;
        if (isOverlaySuppressed(node) || node.dataset?.rcClosedLocally === '1' || node.classList?.contains('rc-ui-closed-now')) return false;
        const style = window.getComputedStyle(node);
        return style.display !== 'none'
            && style.visibility !== 'hidden'
            && style.opacity !== '0'
            && node.getClientRects().length > 0;
    };

    const overlayPriority = (node) => {
        if (node.id === 'rc-ui-instant-shell') return node.dataset.kind === 'school' ? 500 : 450;
        if (node.matches('.fi-modal-window')) return 420;
        if (node.matches('.rc-preview-modal-backdrop')) return 400;
        if (node.matches('.rc-school-modal-backdrop')) return 350;
        if (node.matches('.rc-stats-drawer-backdrop')) return 250;
        return 100;
    };

    const visibleOverlays = () => qsa(overlaySelector)
        .filter(isVisible)
        .sort((a, b) => {
            const priority = overlayPriority(a) - overlayPriority(b);
            if (priority !== 0) return priority;
            return (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING) ? -1 : 1;
        });

    const topVisibleOverlay = () => visibleOverlays().at(-1) || null;

    function refreshOverlayStack() {
        qsa(overlaySelector).forEach(applySuppressedOverlayState);
        const overlays = visibleOverlays();
        const positions = new Map(overlays.map((node, index) => [node, index]));

        qsa(overlaySelector).forEach((node) => {
            const index = positions.get(node);
            const active = Number.isInteger(index);
            const top = active && index === overlays.length - 1;
            const zIndex = active ? String(100000 + (index * 100)) : '';

            node.classList.toggle('rc-stack-layer', active);
            node.classList.toggle('rc-stack-top', top);
            node.classList.toggle('rc-stack-underlay', active && !top);

            if (active) {
                if (node.style.getPropertyValue('--rc-stack-z') !== zIndex) {
                    node.style.setProperty('--rc-stack-z', zIndex);
                }
                if (node.dataset.rcStackPosition !== String(index)) {
                    node.dataset.rcStackPosition = String(index);
                }
                if (node !== shell() && node.getAttribute('aria-hidden') !== (top ? 'false' : 'true')) {
                    node.setAttribute('aria-hidden', top ? 'false' : 'true');
                }
            } else {
                node.style.removeProperty('--rc-stack-z');
                if (node !== shell()) {
                    node.removeAttribute('data-rc-stack-position');
                    node.removeAttribute('aria-hidden');
                }
            }
        });

        document.documentElement.classList.toggle('rc-has-overlay-stack', overlays.length > 0);
        document.documentElement.classList.toggle('rc-has-multiple-overlays', overlays.length > 1);
        document.body?.classList.toggle('rc-has-overlay-stack-body', overlays.length > 0);
    }

    const stabilizeDrawers = (root = document) => {
        const nodes = [];
        if (root?.matches?.('.rc-school-modal-backdrop, .rc-stats-drawer-backdrop')) nodes.push(root);
        nodes.push(...qsa('.rc-school-modal-backdrop, .rc-stats-drawer-backdrop', root));

        nodes.forEach((drawer) => {
            drawer.classList.add('rc-ui-stable-modal');
            drawer.querySelector('.rc-school-modal-panel, .rc-stats-drawer-panel')?.classList.add('rc-ui-stable-panel');
            applySuppressedOverlayState(drawer);
        });
        refreshOverlayStack();
    };

    const startProgress = () => progress()?.classList.add('is-active');
    const stopProgress = () => {
        if (state.pending.size > 0) return;
        progress()?.classList.remove('is-active');
    };

    const component = () => {
        const root = qs('.rc-livewire-root');
        const owner = root?.closest('[wire\\:id]');
        const id = owner?.getAttribute('wire:id');
        if (!id || !window.Livewire?.find) return null;
        try { return window.Livewire.find(id); } catch (_) { return null; }
    };

    const cleanText = (value, fallback = 'Loading details') => {
        const text = String(value || '').replace(/\s+/g, ' ').trim();
        return text ? text.slice(0, 100) : fallback;
    };

    const inferOpenMeta = (element, wireAction) => {
        const explicitKind = element?.dataset?.rcOpen;
        const explicitTitle = element?.dataset?.rcTitle;
        if (explicitKind) {
            return {
                kind: explicitKind,
                title: cleanText(explicitTitle || element?.innerText),
                copy: element?.dataset?.rcCopy || 'The view is open. Current data is being prepared.',
            };
        }

        const action = String(wireAction || '');
        const text = cleanText(element?.innerText, 'Loading details');
        if (/openSchoolDashboardModal|selectSchoolById|openSchoolFromCoach|openDashboardEngagedSchool/.test(action)) {
            return { kind: 'school', title: text, copy: 'Opening the school profile and coaching staff.' };
        }
        if (/selectConversation/.test(action)) {
            return { kind: 'conversation', title: text, copy: 'Opening the conversation now. Messages will appear as they load.' };
        }
        if (/selectTemplate|newTemplate|createTemplate|previewTemplate|duplicateTemplate/.test(action)) {
            return { kind: 'template', title: text, copy: 'Opening the template editor. The latest template content is loading.' };
        }
        if (/composeEmailSchool|composeEmailCoach|composeToCoach|startNewConversation|useTemplateForCompose|useCampaignTemplate/.test(action)) {
            return { kind: 'compose', title: 'Compose Email', copy: 'Opening the composer and preparing recipient data.' };
        }
        return null;
    };

    const openShell = (meta) => {
        const node = shell();
        if (!node || !meta) return;
        clearTimeout(state.shellTimer);
        clearTimeout(state.shellSafetyTimer);
        node.dataset.kind = meta.kind || 'drawer';
        qs('#rc-ui-shell-title')?.replaceChildren(document.createTextNode(cleanText(meta.title)));
        qs('#rc-ui-shell-copy')?.replaceChildren(document.createTextNode(cleanText(meta.copy, 'Loading the latest data.')));
        node.classList.remove('is-closing');
        node.classList.add('is-open');
        node.setAttribute('aria-hidden', 'false');
        refreshOverlayStack();

        // Never leave a loading drawer stranded if the selected record no
        // longer exists. A real school drawer closes this timer immediately.
        if (node.dataset.kind === 'school') {
            state.shellSafetyTimer = setTimeout(() => {
                if (!qsa('.rc-school-modal-backdrop').some(isVisible)) closeShell(true);
            }, 12000);
        }
    };

    const closeShell = (immediate = false) => {
        const node = shell();
        if (!node || !node.classList.contains('is-open')) return;
        clearTimeout(state.shellTimer);
        clearTimeout(state.shellSafetyTimer);
        if (immediate) {
            node.classList.remove('is-open', 'is-closing');
            node.setAttribute('aria-hidden', 'true');
            refreshOverlayStack();
            return;
        }
        node.classList.add('is-closing');
        state.shellTimer = setTimeout(() => {
            node.classList.remove('is-open', 'is-closing');
            node.setAttribute('aria-hidden', 'true');
            refreshOverlayStack();
        }, 100);
    };

    const modalFor = (element) => element?.closest([
        '.rc-school-modal-backdrop',
        '.rc-stats-drawer-backdrop',
        '.rc-preview-modal-backdrop',
        '.rc-drawer',
        '[role="dialog"]',
        '.fi-modal-window',
    ].join(','));

    const markPending = (element) => {
        if (!element || element.matches('[data-rc-no-pending]')) return;
        const isLocal = element.matches('[data-rc-local-action]') || Boolean(modalFor(element));
        if (!isLocal) {
            element.classList.add('rc-action-pending');
            element.setAttribute('aria-busy', 'true');
            state.pending.add(element);
            state.activeInteractiveRequest = true;
            startProgress();
        }
        clearTimeout(state.fallbackTimer);
        state.fallbackTimer = setTimeout(finishAll, 35000);
    };

    function finishAll() {
        state.pending.forEach((element) => {
            if (!element?.isConnected) return;
            element.classList.remove('rc-action-pending');
            element.removeAttribute('aria-busy');
        });
        state.pending.clear();
        state.activeInteractiveRequest = false;
        stopProgress();

        // A school loading shell is the top layer until the real school drawer
        // is present. Closing it merely because the request completed caused the
        // visible close/open flash reported during dashboard stress tests.
        if (shell()?.dataset.kind !== 'school') setTimeout(() => closeShell(false), 35);
    }

    const actionSelector = [
        '[wire\\:click]',
        '[wire\\:click\\.stop]',
        '[wire\\:click\\.prevent]',
        '[wire\\:click\\.self]',
        '[data-rc-open]',
        '[data-rc-instant-close]',
    ].join(',');

    const actionElementForEvent = (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return null;
        const boundary = target.closest('[data-rc-interaction-boundary], .rc-school-modal-panel, .rc-stats-drawer-panel, .fi-modal-window');
        let node = target;

        while (node && node !== document.documentElement) {
            if (node.matches?.(actionSelector)) {
                if (node.hasAttribute('wire:click.self') && target !== node) return null;
                return node;
            }
            if (boundary && node === boundary) return null;
            node = node.parentElement;
        }
        return null;
    };

    const shouldInstantClose = (element, wireAction) => {
        if (!element) return false;
        if (element.matches('[data-rc-instant-close], .rc-school-modal-close, .rc-stats-drawer-close, [data-rc-shell-close]')) return true;
        if (/closeSchool|closeComposer|closePreview|closeModal/.test(String(wireAction || ''))) return true;
        if (/\$set\([^,]+,\s*false\)/.test(String(wireAction || '')) && modalFor(element)) return true;
        const aria = String(element.getAttribute('aria-label') || '').toLowerCase();
        return aria.startsWith('close') && Boolean(modalFor(element));
    };

    const hideModalImmediately = (element) => {
        const modal = modalFor(element);
        if (!modal || modal !== topVisibleOverlay()) return false;
        return window.rcCloseOverlayNow?.(modal) ?? false;
    };

    const schoolIdFromAction = (wireAction) => {
        const action = String(wireAction || '');
        const quoted = action.match(/\(\s*['"]([^'"]+)['"]/);
        if (quoted?.[1]) return quoted[1];
        const numeric = action.match(/\(\s*(\d+)/);
        return numeric?.[1] || null;
    };

    document.addEventListener('pointerdown', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const instantClose = target.closest('[data-rc-instant-close], [data-rc-shell-close]');
        if (!instantClose) return;

        if (instantClose.matches('[data-rc-shell-close]')) {
            closeShell(true);
            return;
        }

        const schoolModal = instantClose.closest('.rc-school-modal-backdrop');
        if (schoolModal) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.rcRequestSchoolClose(schoolModal);
            return;
        }

        hideModalImmediately(instantClose);
    }, true);

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const closeButton = target.closest('[data-rc-shell-close]');
        if (closeButton) {
            closeShell(true);
            return;
        }

        const statTrigger = target.closest('[data-rc-stat-open]');
        if (statTrigger) {
            closeShell(true);
            requestAnimationFrame(refreshOverlayStack);
            return;
        }

        const schoolCloseButton = target.closest('.rc-school-modal-close[data-rc-instant-close]');
        if (schoolCloseButton) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.rcRequestSchoolClose(schoolCloseButton);
            return;
        }

        const clickedBackdrop = target.matches('.rc-school-modal-backdrop, .rc-stats-drawer-backdrop, .rc-preview-modal-backdrop');
        if (clickedBackdrop && target !== topVisibleOverlay()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        const element = actionElementForEvent(event);
        if (clickedBackdrop && target.matches('.rc-school-modal-backdrop')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.rcRequestSchoolClose(target);
            return;
        }
        if (clickedBackdrop) hideModalImmediately(target);
        if (!element) return;

        const wireAction = element.getAttribute('wire:click')
            || element.getAttribute('wire:click.stop')
            || element.getAttribute('wire:click.prevent')
            || element.getAttribute('wire:click.self')
            || '';

        if (shouldInstantClose(element, wireAction)) {
            hideModalImmediately(element);
            closeShell(true);
            return;
        }

        const insideOpenDrawer = Boolean(element.closest('.rc-school-modal-panel, .rc-stats-drawer-panel, .fi-modal-window'));
        const meta = insideOpenDrawer && !element.hasAttribute('data-rc-open')
            ? inferOpenMeta(element, wireAction)
            : inferOpenMeta(element, wireAction);

        // School details are cached, but the selected-school property still needs
        // one Livewire round trip. Keep a stable top placeholder instead of ever
        // hiding the drawer underneath it.
        if (meta?.kind === 'school') {
            const requestedSchoolId = schoolIdFromAction(wireAction);
            state.deferredSchoolCloseIds.clear();
            if (requestedSchoolId) {
                closedSchoolIds.delete(String(requestedSchoolId));
                suppressedOverlayIds.delete(`school-${requestedSchoolId}`);
            }
            state.pendingSchoolOpen = true;
            state.pendingSchoolOpenId = requestedSchoolId;
            openShell(meta);
        } else if (meta && !insideOpenDrawer) openShell(meta);

        if (!element.hasAttribute('wire:confirm')) markPending(element);
    }, true);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.closest('.rc-school-modal-panel, .rc-stats-drawer-panel, .fi-modal-window')) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;
        const href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
        try {
            const destination = new URL(link.href, window.location.href);
            if (destination.origin !== window.location.origin) return;
        } catch (_) { return; }
        startProgress();
    }, true);

    const callComponent = (method) => {
        if (state.scheduledCalls.has(method)) return;
        state.scheduledCalls.add(method);
        setTimeout(() => {
            const instance = component();
            if (!instance?.call) {
                state.scheduledCalls.delete(method);
                return;
            }
            Promise.resolve(instance.call(method)).finally(() => {
                state.scheduledCalls.delete(method);
                finishAll();
            });
        }, 20);
    };

    const bindDeferredEvents = () => {
        const messageLoader = () => callComponent('loadConversationMessages');
        const templateLoader = () => callComponent('loadSelectedTemplateDetail');
        window.addEventListener('rc-load-conversation-messages', messageLoader);
        document.addEventListener('rc-load-conversation-messages', messageLoader);
        window.addEventListener('rc-load-template-detail', templateLoader);
        document.addEventListener('rc-load-template-detail', templateLoader);
    };

    const afterLivewireUpdate = () => {
        stabilizeDrawers();

        // Only a deliberate school-open action may release a locally closed
        // school drawer. Background favorite/list/tag responses are never
        // allowed to resurrect it.
        if (state.pendingSchoolOpen) {
            const drawers = qsa('.rc-school-modal-backdrop');
            const requestedId = state.pendingSchoolOpenId ? String(state.pendingSchoolOpenId) : null;
            const candidate = drawers.find((drawer) => {
                const schoolId = String(drawer.dataset?.rcSchoolId || '');
                return !requestedId || schoolId === requestedId;
            });
            if (candidate) {
                const id = modalIdentity(candidate);
                const schoolId = schoolIdentity(candidate);
                if (id) suppressedOverlayIds.delete(id);
                if (schoolId) closedSchoolIds.delete(schoolId);
                clearImmediateCloseState(candidate);
                state.pendingSchoolOpen = false;
                state.pendingSchoolOpenId = null;
                stabilizeDrawers(candidate);
            }
        }

        const realSchoolDrawer = qsa('.rc-school-modal-backdrop').find(isVisible);
        if (realSchoolDrawer && shell()?.classList.contains('is-open') && shell()?.dataset.kind === 'school') {
            closeShell(true);
        } else if (!realSchoolDrawer && shell()?.classList.contains('is-open') && shell()?.dataset.kind === 'school') {
            setTimeout(() => {
                if (!qsa('.rc-school-modal-backdrop').some(isVisible)) closeShell(true);
            }, 450);
        }
        if (state.activeInteractiveRequest || state.pending.size > 0 || (shell()?.classList.contains('is-open') && shell()?.dataset.kind !== 'school')) {
            setTimeout(finishAll, 35);
        }
        window.dispatchEvent(new CustomEvent('rc-drawer-action-finished'));
        refreshOverlayStack();
    };

    const hookLivewire = () => {
        if (state.hooked || !window.Livewire?.hook) return;
        state.hooked = true;

        try {
            window.Livewire.hook('request', ({ succeed, fail }) => {
                state.livewireRequestsInFlight += 1;
                const settle = (callback) => () => {
                    state.livewireRequestsInFlight = Math.max(0, state.livewireRequestsInFlight - 1);
                    callback?.();
                };
                succeed?.(settle(afterLivewireUpdate));
                fail?.(settle(() => { setTimeout(finishAll, 35); refreshOverlayStack(); }));
            });
        } catch (_) {}

        try {
            window.Livewire.hook('commit', ({ succeed, fail }) => {
                succeed?.(afterLivewireUpdate);
                fail?.(() => { setTimeout(finishAll, 35); refreshOverlayStack(); });
            });
        } catch (_) {}

        try {
            window.Livewire.hook('morph.updating', ({ el, toEl }) => {
                if (el?.matches?.('.rc-school-modal-backdrop')) {
                    const currentId = String(el.dataset?.rcSchoolId || '');
                    const nextId = String(toEl?.dataset?.rcSchoolId || '');
                    if (currentId && currentId === nextId) {
                        const panel = el.querySelector('.rc-school-modal-panel');
                        if (panel) state.drawerScrollTop.set(currentId, panel.scrollTop || 0);
                        el.classList.add('rc-ui-stable-modal');
                        toEl?.classList?.add('rc-ui-stable-modal');
                    }
                }

                if (el?.matches?.('.rc-stats-drawer-backdrop')) {
                    el.classList.add('rc-ui-stable-modal');
                    toEl?.classList?.add('rc-ui-stable-modal');
                }
            });
        } catch (_) {}

        try {
            window.Livewire.hook('morph.updated', ({ el }) => {
                stabilizeDrawers(el || document);
                const drawers = [];
                if (el?.matches?.('.rc-school-modal-backdrop')) drawers.push(el);
                drawers.push(...qsa('.rc-school-modal-backdrop', el || document));
                drawers.forEach((drawer) => {
                    const id = String(drawer.dataset?.rcSchoolId || '');
                    const panel = drawer.querySelector('.rc-school-modal-panel');
                    if (id && panel && state.drawerScrollTop.has(id)) {
                        panel.scrollTop = state.drawerScrollTop.get(id) || 0;
                        state.drawerScrollTop.delete(id);
                    }
                    if (isOverlaySuppressed(drawer)) {
                        applySuppressedOverlayState(drawer);
                    } else if (drawer.dataset.rcClosedLocally !== '1') {
                        drawer.classList.remove('rc-ui-leaving', 'rc-ui-closed-now');
                        drawer.style.removeProperty('display');
                        drawer.style.removeProperty('visibility');
                        drawer.style.removeProperty('pointer-events');
                        drawer.style.removeProperty('opacity');
                        drawer.removeAttribute('inert');
                    }
                });
                afterLivewireUpdate();
            });
        } catch (_) {}
    };

    const observer = new MutationObserver(() => {
        stabilizeDrawers();
        const realSchoolDrawer = qsa('.rc-school-modal-backdrop').find(isVisible);
        if (realSchoolDrawer && shell()?.classList.contains('is-open') && shell()?.dataset.kind === 'school') {
            closeShell(true);
        }
        hookLivewire();
    });

    bindDeferredEvents();
    document.addEventListener('livewire:init', hookLivewire);
    document.addEventListener('livewire:initialized', hookLivewire);
    document.addEventListener('livewire:navigated', () => {
        suppressedOverlayIds.clear();
        closedSchoolIds.clear();
        state.deferredSchoolCloseIds.clear();
        state.pendingSchoolOpen = false;
        state.pendingSchoolOpenId = null;
        state.livewireRequestsInFlight = 0;
        clearTimeout(state.actionStatusTimer);
        discoverSelectionStores.delete(window.location.pathname);
        finishAll();
        stabilizeDrawers();
        hookLivewire();
    });
    window.addEventListener('pageshow', () => { finishAll(); stabilizeDrawers(); });
    const preserveClosedSchoolAfterAction = () => {
        qsa('.rc-school-modal-backdrop').forEach((drawer) => {
            if (isOverlaySuppressed(drawer)) applySuppressedOverlayState(drawer);
        });
        stabilizeDrawers();
    };
    window.addEventListener('rc-school-action-complete', preserveClosedSchoolAfterAction);
    document.addEventListener('rc-school-action-complete', preserveClosedSchoolAfterAction);

    window.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const top = topVisibleOverlay();
        if (!top) return;

        event.preventDefault();
        event.stopPropagation();

        if (top === shell()) {
            closeShell(true);
            return;
        }

        const close = qs('.rc-school-modal-close, .rc-stats-drawer-close, [data-rc-instant-close], [aria-label^="Close"]', top);
        close?.click();
    }, true);

    observer.observe(document.documentElement, { childList: true, subtree: true });
    stabilizeDrawers();
    hookLivewire();
})();