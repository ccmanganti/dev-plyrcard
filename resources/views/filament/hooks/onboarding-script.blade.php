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
        background: rgba(255, 255, 255, 0.05) !important;
        color: #cbd5e1 !important;
        border-radius: 12px !important;
        padding: 10px 14px !important;
    }

    .driver-popover.plyrcard-driver-theme .driver-popover-arrow {
        color: #111827 !important;
    }

    .driver-overlay {
        background: rgba(2, 6, 23, 0.35) !important;
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
        const TOUR_VERSION = 'v8';
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

        function makeStep(selector, title, description, side = 'bottom', align = 'start') {
            if (!selector) return null;

            const el = document.querySelector(selector);
            if (!visible(el)) return null;

            return {
                element: selector,
                popover: {
                    title,
                    description,
                    side,
                    align,
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

            // Be tolerant here: if one widget is missing, still allow the tour.
            const steps = [
                makeStep(viewsCard, 'Card activity', 'This card shows your current views and visibility stats.'),
                makeStep(progressSection, 'Profile progress', 'This section shows how complete your profile is and what is still missing.'),
                makeStep(completeProfile, 'Complete profile', 'Use this button to jump into finishing your profile.'),
                makeStep(profileLink, 'Profile section', 'Next, you will continue in the Profile section.', 'right'),
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

            const driverObj = window.driver.js.driver({
                showProgress: true,
                allowClose: false,
                animate: true,
                overlayOpacity: 0.35,
                stagePadding: 8,
                stageRadius: 16,
                disableActiveInteraction: true,
                popoverClass: 'plyrcard-driver-theme',
                nextBtnText: 'Next',
                prevBtnText: 'Back',
                doneBtnText: 'Finish',
                steps: config.steps,
                onDestroyed: () => {
                    console.log('Tour destroyed');
                    activeTourInstance = null;
                    activeTourKey = null;
                    activeStepsCount = 0;
                    completionLocked = false;
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
                        driverObj.destroy();
                        return;
                    }

                    driverObj.moveNext();
                },
                onPrevClick: () => {
                    driverObj.movePrevious();
                },
                onCloseClick: () => {
                    driverObj.destroy();
                },
            });

            activeTourInstance = driverObj;
            driverObj.drive();
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