@php
    $user = auth()->user();
@endphp

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.5/dist/driver.css">
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.5/dist/driver.js.iife.js"></script>

<style>
    .driver-popover.plyrcard-driver-theme {
        background: #111827 !important;
        color: #f8fafc !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        border-radius: 18px !important;
        box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35) !important;
        max-width: 380px !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-title {
        color: #f8fafc !important;
        font-weight: 800 !important;
        font-size: 20px !important;
        line-height: 1.2 !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-description {
        color: #cbd5e1 !important;
        font-size: 14px !important;
        line-height: 1.7 !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-progress-text {
        color: #94a3b8 !important;
        font-weight: 700 !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-footer {
        margin-top: 14px !important;
        border-top: 1px solid rgba(255, 255, 255, 0.06) !important;
        padding-top: 14px !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-next-btn,
    .driver-popover.plyrcard-driver-theme .driver-popover-prev-btn,
    .driver-popover.plyrcard-driver-theme .driver-popover-close-btn {
        text-shadow: none !important;
        box-shadow: none !important;
        border: none !important;
        font-weight: 700 !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-prev-btn {
        background: rgba(255, 255, 255, 0.05) !important;
        color: #e2e8f0 !important;
        border-radius: 12px !important;
        padding: 10px 14px !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-next-btn {
        background: linear-gradient(135deg, #f97316 0%, #ea580c 55%, #e11d48 100%) !important;
        color: #ffffff !important;
        border-radius: 12px !important;
        padding: 10px 16px !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-close-btn {
        background: transparent !important;
        color: #94a3b8 !important;
        border-radius: 12px !important;
        padding: 10px 14px !important;
        font-weight: 700 !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-close-btn:hover {
        color: #f97316 !important;
        background: rgba(249, 115, 22, 0.08) !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-close-btn[aria-label="Close"] {
        font-size: 0 !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-close-btn[aria-label="Close"]::after {
        content: 'Skip';
        font-size: 13px !important;
    }


    .driver-popover.plyrcard-driver-theme .driver-popover-arrow {
        color: #111827 !important;
    }

    .driver-overlay {
        background: rgba(2, 6, 23, 0.35) !important;
    }

    @media (max-width: 768px) {
        .driver-popover.plyrcard-driver-theme {
            max-width: min(280px, calc(100vw - 20px)) !important;
            border-radius: 14px !important;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.28) !important;
            padding: 8px !important;
        }

        .driver-popover.plyrcard-driver-theme .driver-popover-title {
            font-size: 15px !important;
            line-height: 1.15 !important;
        }

        .driver-popover.plyrcard-driver-theme .driver-popover-description {
            font-size: 12px !important;
            line-height: 1.45 !important;
        }

        .driver-popover.plyrcard-driver-theme .driver-popover-footer {
            margin-top: 8px !important;
            padding-top: 8px !important;
        }

        .driver-popover.plyrcard-driver-theme .driver-popover-prev-btn,
        .driver-popover.plyrcard-driver-theme .driver-popover-next-btn,
        .driver-popover.plyrcard-driver-theme .driver-popover-close-btn {
            padding: 7px 10px !important;
            font-size: 12px !important;
            border-radius: 10px !important;
        }

        .driver-popover.plyrcard-driver-theme .driver-popover-progress-text {
            font-size: 11px !important;
        }
    }
</style>

<script>
    window.PLYRCARD_CURRENT_USER_ID = @json($user?->id ? (string) $user->id : null);
    window.PLYRCARD_ONBOARDING_ENABLED = @json(
        (bool) ($user && ! $user->must_change_password && is_null($user->onboarding_completed_at))
    );

    console.log('ONBOARDING HOOK LOADED');
    console.log({
        userExists: @json((bool) $user),
        userId: @json($user?->id),
        mustChangePassword: @json($user?->must_change_password),
        onboardingCompletedAt: @json($user?->onboarding_completed_at),
        onboardingEnabled: window.PLYRCARD_ONBOARDING_ENABLED,
    });

    (function () {
        const USER_ID = window.PLYRCARD_CURRENT_USER_ID ?? 'guest';
        const TOUR_VERSION = 'v10-mobile-fix';
        const KEY_PREFIX = `${TOUR_VERSION}-user-${USER_ID}`;

        const TOUR_KEYS = {
            dashboard: `${KEY_PREFIX}-dashboard`,
            profile: `${KEY_PREFIX}-profile`,
            schedules: `${KEY_PREFIX}-schedules`,
            upgrade: `${KEY_PREFIX}-upgrade`,
        };

        const TOUR_ORDER = ['dashboard', 'profile', 'schedules', 'upgrade'];

        let activeTourInstance = null;
        let activeTourKey = null;
        let activeStepsCount = 0;
        let completionLocked = false;
        let autoStartLocked = false;

        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        }

        async function markOnboardingComplete() {
            try {
                const response = await fetch('/onboarding/complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ completed: true }),
                });

                const data = await response.json().catch(() => ({}));

                console.log('Onboarding completion response:', {
                    ok: response.ok,
                    status: response.status,
                    data,
                });

                if (!response.ok) {
                    throw new Error(`Onboarding complete request failed with status ${response.status}`);
                }

                return data;
            } catch (error) {
                console.error('Failed to save onboarding completion.', error);
                return null;
            }
        }

        function getDone(page) {
            return localStorage.getItem(TOUR_KEYS[page]) === 'done';
        }

        function setDone(page) {
            localStorage.setItem(TOUR_KEYS[page], 'done');
        }

        function setAllDone() {
            TOUR_ORDER.forEach((page) => setDone(page));
        }

        async function skipOnboarding() {
            if (completionLocked) return;

            completionLocked = true;
            setAllDone();

            const result = await markOnboardingComplete();

            if (activeTourInstance) {
                try {
                    activeTourInstance.destroy();
                } catch (e) {
                    console.warn('Failed to destroy active onboarding tour after skip.', e);
                }
            }

            if (result?.success) {
                window.location.reload();
            }
        }

        window.skipPlyrCardOnboarding = skipOnboarding;

        function allToursFinished() {
            return TOUR_ORDER.every(page => getDone(page));
        }

        async function setTourDoneByKey(key) {
            const entry = Object.entries(TOUR_KEYS).find(([, value]) => value === key);
            if (!entry) return;

            const [page] = entry;

            if (getDone(page)) {
                console.log('Tour already saved as done:', page);
                return;
            }

            setDone(page);
            console.log('Tour marked done:', page);

            if (allToursFinished()) {
                console.log('All tours finished. Marking onboarding complete.');
                const result = await markOnboardingComplete();

                if (result?.success) {
                    console.log('Onboarding saved successfully. Reloading.');
                    window.location.reload();
                }
            }
        }

        function containsText(el, text) {
            return el && el.textContent && el.textContent.toLowerCase().includes(text.toLowerCase());
        }

        function findButton(text) {
            const elements = document.querySelectorAll('button, a, [role="button"]');
            for (const el of elements) {
                if (containsText(el, text)) return el;
            }
            return null;
        }

        function findHeading(text) {
            const elements = document.querySelectorAll('h1, h2, h3, h4, h5, h6');
            for (const el of elements) {
                if (containsText(el, text)) return el;
            }
            return null;
        }

        function findSidebarLink(text) {
            const links = document.querySelectorAll('nav a, aside a, .fi-sidebar a');
            for (const link of links) {
                if (containsText(link, text)) return link;
            }
            return null;
        }

        function closestCard(el) {
            if (!el) return null;

            return (
                el.closest('.fi-section') ||
                el.closest('.fi-card') ||
                el.closest('[class*="card"]') ||
                el.closest('[class*="section"]') ||
                el.closest('section') ||
                el.parentElement
            );
        }

        function clearTourMarks() {
            document.querySelectorAll('[data-plyr-tour]').forEach((el) => {
                el.removeAttribute('data-plyr-tour');
            });
        }

        function mark(name, el) {
            if (!el) return null;
            el.setAttribute('data-plyr-tour', name);
            return `[data-plyr-tour="${name}"]`;
        }

        function visible(el) {
            if (!el) return false;
            const rect = el.getBoundingClientRect();
            return rect.width > 0 && rect.height > 0;
        }

        function isMobileViewport() {
            return window.matchMedia('(max-width: 768px)').matches;
        }

        function isSidebarTourSelector(selector) {
            return !!selector && (
                selector.includes('profile-link') ||
                selector.includes('schedules-link') ||
                selector.includes('upgrade-link')
            );
        }

        function findMobileSidebarButton() {
            const direct =
                document.querySelector('[aria-label*="open sidebar" i]') ||
                document.querySelector('[aria-label*="toggle sidebar" i]') ||
                document.querySelector('[aria-label*="sidebar" i]') ||
                document.querySelector('[aria-label*="menu" i]') ||
                document.querySelector('[title*="sidebar" i]') ||
                document.querySelector('[title*="menu" i]') ||
                document.querySelector('.fi-topbar-open-sidebar-btn') ||
                document.querySelector('button.fi-icon-btn');

            if (direct) return direct;

            const buttons = Array.from(document.querySelectorAll('button'));
            const topLeftButtons = buttons
                .map((button) => {
                    const rect = button.getBoundingClientRect();
                    return { button, rect };
                })
                .filter(({ rect }) =>
                    rect.width > 20 &&
                    rect.height > 20 &&
                    rect.left < 110 &&
                    rect.top < 130
                )
                .sort((a, b) => (a.rect.left + a.rect.top) - (b.rect.left + b.rect.top));

            return topLeftButtons[0]?.button || null;
        }

        function findSidebarElement() {
            return (
                document.querySelector('.fi-sidebar') ||
                document.querySelector('aside.fi-sidebar') ||
                document.querySelector('aside') ||
                document.querySelector('nav')
            );
        }

        function sidebarLooksVisible() {
            const sidebar = findSidebarElement();
            if (!sidebar) return false;

            const rect = sidebar.getBoundingClientRect();
            const style = window.getComputedStyle(sidebar);

            if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) {
                return false;
            }

            return rect.width > 120 && rect.right > 40;
        }

        function triggerClick(el) {
            if (!el) return;

            try {
                el.click();
            } catch (e) {}

            try {
                el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
            } catch (e) {}
        }

        async function openMobileSidebar() {
            if (!isMobileViewport()) return;

            if (sidebarLooksVisible()) return;

            const btn = findMobileSidebarButton();
            if (!btn) {
                console.warn('Mobile sidebar button not found.');
                return;
            }

            triggerClick(btn);
            await new Promise((resolve) => setTimeout(resolve, 300));

            if (!sidebarLooksVisible()) {
                triggerClick(btn);
                await new Promise((resolve) => setTimeout(resolve, 400));
            }
        }

        async function ensureMobileSidebarOpenForSelector(selector) {
            if (!isMobileViewport()) return;
            if (!isSidebarTourSelector(selector)) return;

            for (let i = 0; i < 4; i++) {
                await openMobileSidebar();

                const el = document.querySelector(selector);
                if (el && visible(el)) {
                    return;
                }

                await new Promise((resolve) => setTimeout(resolve, 250));
            }

            console.warn('Sidebar target still not visible after retries:', selector);
        }

        function getScrollableTabsContainer(el) {
            if (!el) return null;

            return (
                el.closest('[role="tablist"]') ||
                el.closest('.overflow-x-auto') ||
                el.closest('.overflow-auto') ||
                el.parentElement
            );
        }

        function scrollElementIntoViewSmart(el) {
            if (!el) return;

            try {
                const rect = el.getBoundingClientRect();

                if (isMobileViewport()) {
                    // Put the target lower on the screen so the popover can live near the top.
                    const desiredTopInViewport = window.innerHeight * 0.46;
                    const absoluteTop = window.scrollY + rect.top - desiredTopInViewport;

                    window.scrollTo({
                        top: Math.max(0, absoluteTop),
                        behavior: 'smooth',
                    });

                    return;
                }

                const absoluteTop = window.scrollY + rect.top - 72;

                window.scrollTo({
                    top: Math.max(0, absoluteTop),
                    behavior: 'smooth',
                });
            } catch (e) {}
        }

        function ensureHorizontalVisibility(el) {
            if (!el) return;

            const container = getScrollableTabsContainer(el);
            if (!container) return;

            const containerStyle = window.getComputedStyle(container);
            const canScrollX =
                container.scrollWidth > container.clientWidth ||
                containerStyle.overflowX === 'auto' ||
                containerStyle.overflowX === 'scroll';

            if (!canScrollX) return;

            const elRect = el.getBoundingClientRect();
            const cRect = container.getBoundingClientRect();

            const outLeft = elRect.left < cRect.left + 8;
            const outRight = elRect.right > cRect.right - 8;

            if (outLeft || outRight) {
                try {
                    const left =
                        el.offsetLeft - Math.max(12, (container.clientWidth - el.clientWidth) / 2);

                    container.scrollTo({
                        left: Math.max(0, left),
                        behavior: 'smooth',
                    });
                } catch (e) {
                    try {
                        el.scrollIntoView({
                            behavior: 'smooth',
                            inline: 'center',
                            block: 'nearest',
                        });
                    } catch (_) {}
                }
            }
        }

        async function prepareElementForStep(selector) {
            await ensureMobileSidebarOpenForSelector(selector);

            const el = document.querySelector(selector);
            if (!el) return;

            ensureHorizontalVisibility(el);
            scrollElementIntoViewSmart(el);

            await new Promise((resolve) => {
                setTimeout(resolve, isMobileViewport() ? 500 : 250);
            });
        }

        function makeStep(selector, title, description, side = 'bottom', align = 'start') {
            if (!selector) return null;

            const el = document.querySelector(selector);
            if (!visible(el)) return null;

            const mobileSide = isMobileViewport() ? 'top' : side;
            const mobileAlign = isMobileViewport() ? 'center' : align;

            return {
                element: selector,
                popover: {
                    title,
                    description,
                    side: mobileSide,
                    align: mobileAlign,
                },
            };
        }

        function detectPage() {
            const path = window.location.pathname;

            console.log('Current path:', path);

            if (/^\/admin\/?$/.test(path)) return 'dashboard';
            if (path.includes('/admin/profile')) return 'profile';
            if (path.includes('/admin/schedules')) return 'schedules';
            if (path.includes('/admin/my-journey') || path.includes('/admin/upgrade')) return 'upgrade';

            return null;
        }

        function buildDashboardSteps() {
            clearTourMarks();

            const viewsCard = mark('views-card', closestCard(findHeading('Card Views')));
            const progressSection = mark('progress-section', closestCard(findHeading('Profile progress')));
            const completeProfile = mark('complete-profile', findButton('Complete profile'));
            const profileLink = mark('profile-link', findSidebarLink('Profile'));
            const schedulesLink = mark('schedules-link', findSidebarLink('Schedules'));

            const steps = [
                makeStep(viewsCard, 'Card activity', 'This card shows your current views and visibility stats.'),
                makeStep(progressSection, 'Profile progress', 'This section shows how complete your profile is and what is still missing.'),
                makeStep(completeProfile, 'Complete profile', 'Use this button to jump into finishing your profile.'),
                makeStep(profileLink, 'Profile section', 'This is where you edit your athlete profile.', 'right'),
                makeStep(schedulesLink, 'Schedules section', 'This is where you manage games and schedules.', 'right'),
            ].filter(Boolean);

            console.log('Dashboard steps:', steps);
            return steps;
        }

        function buildProfileSteps() {
            clearTourMarks();

            const previewCard = mark('preview-card', findButton('Preview Card'));
            const saveAll = mark('save-all', findButton('Save All') || findButton('Save Profile'));
            const basicInfo = mark('tab-basic', findButton('Basic Info'));
            const athleteInfo = mark('tab-athlete', findButton('Athlete Info'));
            const bioAccolades = mark('tab-bio', findButton('Bio & Accolades'));
            const media = mark('tab-media', findButton('Media'));
            const social = mark('tab-social', findButton('Social'));
            const people = mark('tab-people', findButton('People'));
            const website = mark('tab-website', findButton('Website'));

            const steps = [
                makeStep(previewCard, 'Preview Card', 'Preview how your public athlete card looks.', 'left', 'end'),
                makeStep(saveAll, 'Save changes', 'Save your updates from here.', 'left', 'end'),
                makeStep(basicInfo, 'Basic Info', 'Personal details and contact info live here.'),
                makeStep(athleteInfo, 'Athlete Info', 'Sport, position, school, and experience go here.'),
                makeStep(bioAccolades, 'Bio & Accolades', 'Tell your story and list your achievements here.'),
                makeStep(media, 'Media', 'Manage photos and videos here.'),
                makeStep(social, 'Social', 'Add your social links here.'),
                makeStep(people, 'People', 'Add parents, coaches, and trainers here.'),
                makeStep(website, 'Website', 'Control your public website settings here.'),
            ].filter(Boolean);

            console.log('Profile steps:', steps);
            return steps;
        }

        function buildSchedulesSteps() {
            clearTourMarks();

            const newSchedule = mark('new-schedule', findButton('New Schedule'));
            const table = mark('schedule-table', document.querySelector('table')?.closest('div'));

            const steps = [
                makeStep(newSchedule, 'New Schedule', 'Create a new game or schedule entry here.', 'left', 'end'),
                makeStep(table, 'Schedule table', 'Your schedule items are listed here.'),
            ].filter(Boolean);

            console.log('Schedules steps:', steps);
            return steps;
        }

        function buildUpgradeSteps() {
            clearTourMarks();

            const freeCard = mark('free-card', closestCard(findHeading('FREE')));
            const plyrCard = mark('plyr-card', closestCard(findHeading('PLYR')));
            const journeyCard = mark('journey-card', closestCard(findHeading('MY JOURNEY')));

            const steps = [
                makeStep(freeCard, 'Free plan', 'This card shows what is included in the free plan.'),
                makeStep(plyrCard, 'PLYR plan', 'This is your mid-tier plan option.'),
                makeStep(journeyCard, 'My Journey plan', 'This is the premium plan option.'),
            ].filter(Boolean);

            console.log('Upgrade steps:', steps);
            return steps;
        }

        function getTourConfig(page) {
            if (page === 'dashboard') return { key: TOUR_KEYS.dashboard, steps: buildDashboardSteps() };
            if (page === 'profile') return { key: TOUR_KEYS.profile, steps: buildProfileSteps() };
            if (page === 'schedules') return { key: TOUR_KEYS.schedules, steps: buildSchedulesSteps() };
            if (page === 'upgrade') return { key: TOUR_KEYS.upgrade, steps: buildUpgradeSteps() };
            return null;
        }

        function buildDriverConfig(config) {
            return {
                showProgress: true,
                allowClose: true,
                animate: true,
                overlayOpacity: isMobileViewport() ? 0.20 : 0.35,
                stagePadding: isMobileViewport() ? 6 : 8,
                stageRadius: isMobileViewport() ? 12 : 16,
                disableActiveInteraction: true,
                popoverClass: 'plyrcard-driver-theme',
                nextBtnText: 'Next',
                prevBtnText: 'Back',
                closeBtnText: 'Skip',
                doneBtnText: 'Finish',
                steps: config.steps,
                onDestroyed: () => {
                    console.log('Tour destroyed');
                    activeTourInstance = null;
                    activeTourKey = null;
                    activeStepsCount = 0;
                    completionLocked = false;
                },
                onHighlightStarted: async (element, step) => {
                    try {
                        const selector =
                            typeof step?.element === 'string'
                                ? step.element
                                : element?.id
                                    ? `#${element.id}`
                                    : null;

                        if (selector) {
                            await prepareElementForStep(selector);
                        } else if (element) {
                            ensureHorizontalVisibility(element);
                            scrollElementIntoViewSmart(element);
                        }
                    } catch (e) {}
                },
                onNextClick: async (element, step, options) => {
                    const currentIndex = Number(options.state.activeIndex ?? 0);
                    const isLastStep = currentIndex === activeStepsCount - 1;

                    console.log('Next clicked', {
                        currentIndex,
                        activeStepsCount,
                        isLastStep,
                        activeTourKey,
                    });

                    if (isLastStep) {
                        if (completionLocked) return;
                        completionLocked = true;

                        await setTourDoneByKey(activeTourKey);
                        activeTourInstance.destroy();
                        return;
                    }

                    const nextStep = config.steps[currentIndex + 1];
                    if (nextStep?.element) {
                        await prepareElementForStep(nextStep.element);
                    }

                    activeTourInstance.moveNext();
                },
                onPrevClick: async (element, step, options) => {
                    const currentIndex = Number(options.state.activeIndex ?? 0);
                    const prevStep = config.steps[currentIndex - 1];

                    if (prevStep?.element) {
                        await prepareElementForStep(prevStep.element);
                    }

                    activeTourInstance.movePrevious();
                },
                onCloseClick: async () => {
                    await window.skipPlyrCardOnboarding();
                },
            };
        }

        function startTourForCurrentPage() {
            const page = detectPage();
            console.log('Detected onboarding page:', page);

            if (!page) return false;

            const config = getTourConfig(page);
            console.log('Tour config:', config);

            if (!config || !config.steps.length) {
                console.log('No tour steps found');
                return false;
            }

            if (config.steps.length < 2) {
                console.warn('Skipping tour: too few steps loaded');
                return false;
            }

            if (localStorage.getItem(config.key) === 'done') {
                console.log('Tour already completed for this user:', config.key);
                return false;
            }

            if (!window.driver || !window.driver.js || !window.driver.js.driver) {
                console.error('Driver.js not available on window');
                return false;
            }

            if (activeTourInstance) {
                try {
                    activeTourInstance.destroy();
                } catch (e) {}
                activeTourInstance = null;
            }

            completionLocked = false;
            activeTourKey = config.key;
            activeStepsCount = config.steps.length;

            activeTourInstance = window.driver.js.driver(buildDriverConfig(config));
            activeTourInstance.drive();
            return true;
        }

        function waitForElementsAndStart(retries = 12) {
            if (!window.PLYRCARD_ONBOARDING_ENABLED) return;

            const started = startTourForCurrentPage();

            if (started) {
                console.log('Tour started successfully.');
                return;
            }

            if (retries <= 0) {
                console.warn('Tour aborted: elements never became ready.');
                return;
            }

            console.log('Waiting for lazy-loaded elements... retries left:', retries);

            setTimeout(() => {
                waitForElementsAndStart(retries - 1);
            }, 500);
        }

        window.startPlyrCardTour = function () {
            waitForElementsAndStart(1);
        };

        window.resetPlyrCardTours = function () {
            Object.values(TOUR_KEYS).forEach((key) => localStorage.removeItem(key));
            console.log('All user-specific tour keys cleared');
        };

        window.completePlyrCardOnboarding = async function () {
            await markOnboardingComplete();
        };

        console.log('Onboarding functions registered:', {
            start: typeof window.startPlyrCardTour,
            reset: typeof window.resetPlyrCardTours,
            complete: typeof window.completePlyrCardOnboarding,
            version: TOUR_VERSION,
            userId: USER_ID,
            keys: TOUR_KEYS,
        });

        function maybeStartOnboarding() {
            if (!window.PLYRCARD_ONBOARDING_ENABLED) return;

            if (autoStartLocked) {
                console.log('Auto start already attempted in this navigation cycle.');
                return;
            }

            autoStartLocked = true;

            setTimeout(() => {
                waitForElementsAndStart();
            }, 800);
        }

        document.addEventListener('DOMContentLoaded', maybeStartOnboarding);
        document.addEventListener('livewire:navigated', () => {
            autoStartLocked = false;
            maybeStartOnboarding();
        });
    })();
</script>