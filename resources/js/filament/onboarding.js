console.log('PLYRCARD onboarding.js loaded')

const TOUR_KEYS = {
    dashboard: 'plyrcard-tour-dashboard',
    profile: 'plyrcard-tour-profile',
    schedules: 'plyrcard-tour-schedules',
    upgrade: 'plyrcard-tour-upgrade',
}

const ALL_TOURS = Object.values(TOUR_KEYS)

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

async function markOnboardingComplete() {
    try {
        await fetch('/onboarding/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ completed: true }),
        })
    } catch (error) {
        console.error('Failed to save onboarding completion.', error)
    }
}

function allToursFinished() {
    return ALL_TOURS.every((key) => localStorage.getItem(key) === 'done')
}

function setTourDone(key) {
    localStorage.setItem(key, 'done')

    if (allToursFinished()) {
        markOnboardingComplete()
    }
}

function containsText(el, text) {
    return el && el.textContent && el.textContent.toLowerCase().includes(text.toLowerCase())
}

function findButton(text) {
    const elements = document.querySelectorAll('button, a, [role="button"]')
    for (const el of elements) {
        if (containsText(el, text)) return el
    }
    return null
}

function findHeading(text) {
    const elements = document.querySelectorAll('h1, h2, h3, h4, h5, h6')
    for (const el of elements) {
        if (containsText(el, text)) return el
    }
    return null
}

function findInputByPlaceholder(text) {
    const inputs = document.querySelectorAll('input, textarea')
    for (const input of inputs) {
        const placeholder = input.getAttribute('placeholder') || ''
        if (placeholder.toLowerCase().includes(text.toLowerCase())) {
            return input
        }
    }
    return null
}

function findSidebarLink(text) {
    const links = document.querySelectorAll('nav a, aside a, .fi-sidebar a')
    for (const link of links) {
        if (containsText(link, text)) return link
    }
    return null
}

function closestCard(el) {
    if (!el) return null

    return (
        el.closest('.fi-section') ||
        el.closest('.fi-card') ||
        el.closest('[class*="card"]') ||
        el.closest('[class*="section"]') ||
        el.closest('section') ||
        el.parentElement
    )
}

function clearTourMarks() {
    document.querySelectorAll('[data-plyr-tour]').forEach((el) => {
        el.removeAttribute('data-plyr-tour')
    })
}

function mark(name, el) {
    if (!el) return null
    el.setAttribute('data-plyr-tour', name)
    return `[data-plyr-tour="${name}"]`
}

function visible(el) {
    if (!el) return false
    const rect = el.getBoundingClientRect()
    return rect.width > 0 && rect.height > 0
}

function makeStep(selector, title, description, side = 'bottom', align = 'start') {
    if (!selector) return null

    const el = document.querySelector(selector)
    if (!visible(el)) return null

    return {
        element: selector,
        popover: {
            title,
            description,
            side,
            align,
        },
    }
}

function detectPage() {
    const path = window.location.pathname

    if (path === '/admin' || path === '/admin/') return 'dashboard'
    if (path.includes('/admin/profile')) return 'profile'
    if (path.includes('/admin/schedules')) return 'schedules'
    if (path.includes('/admin/my-journey') || path.includes('/admin/upgrade')) return 'upgrade'

    return null
}

function buildDashboardSteps() {
    clearTourMarks()

    const dashboardLink = mark('dashboard-link', findSidebarLink('Dashboard'))
    const profileLink = mark('profile-link', findSidebarLink('Profile'))
    const schedulesLink = mark('schedules-link', findSidebarLink('Schedules'))
    const upgradeLink = mark('upgrade-link', findSidebarLink('Upgrade'))
    const search = mark('top-search', findInputByPlaceholder('Search'))
    const viewsCard = mark('views-card', closestCard(findHeading('Card Views')))
    const progressSection = mark('progress-section', closestCard(findHeading('Profile progress')))
    const completeProfile = mark('complete-profile', findButton('Complete profile'))

    const steps = [
        makeStep(dashboardLink, 'Dashboard', 'This is your main hub.', 'right'),
        makeStep(profileLink, 'Profile', 'Go here to build your athlete profile.'),
        makeStep(schedulesLink, 'Schedules', 'Manage your schedule here.'),
        makeStep(upgradeLink, 'Upgrade', 'See your plan options here.'),
        makeStep(search, 'Search', 'Quickly search pages and actions.', 'left', 'end'),
        makeStep(viewsCard, 'Top stats', 'These top cards summarize your current stats.'),
        makeStep(progressSection, 'Profile progress', 'This area shows what is still missing from your profile.'),
        makeStep(completeProfile, 'Complete profile', 'Use this to jump into completing your profile.'),
    ].filter(Boolean)

    console.log('Dashboard steps:', steps)
    return steps
}

function buildProfileSteps() {
    clearTourMarks()

    const previewCard = mark('preview-card', findButton('Preview Card'))
    const saveAll = mark('save-all', findButton('Save All') || findButton('Save Profile'))
    const basicInfo = mark('tab-basic', findButton('Basic Info'))
    const athleteInfo = mark('tab-athlete', findButton('Athlete Info'))
    const bioAccolades = mark('tab-bio', findButton('Bio & Accolades'))
    const media = mark('tab-media', findButton('Media'))
    const social = mark('tab-social', findButton('Social'))
    const people = mark('tab-people', findButton('People'))
    const website = mark('tab-website', findButton('Website'))

    const steps = [
        makeStep(previewCard, 'Preview Card', 'Preview your public card here.', 'left', 'end'),
        makeStep(saveAll, 'Save', 'Save your profile changes here.', 'left', 'end'),
        makeStep(basicInfo, 'Basic Info', 'Personal details live here.'),
        makeStep(athleteInfo, 'Athlete Info', 'Sport and athlete details go here.'),
        makeStep(bioAccolades, 'Bio & Accolades', 'Tell your story and achievements here.'),
        makeStep(media, 'Media', 'Upload and manage media here.'),
        makeStep(social, 'Social', 'Add your social links here.'),
        makeStep(people, 'People', 'Add parents, coaches, and trainers here.'),
        makeStep(website, 'Website', 'Website settings are here.'),
    ].filter(Boolean)

    console.log('Profile steps:', steps)
    return steps
}

function buildSchedulesSteps() {
    clearTourMarks()

    const newSchedule = mark('new-schedule', findButton('New Schedule'))
    const search = mark('schedule-search', findInputByPlaceholder('Search'))
    const table = mark('schedule-table', document.querySelector('table')?.closest('div'))

    const steps = [
        makeStep(newSchedule, 'New Schedule', 'Create a new schedule item here.', 'left', 'end'),
        makeStep(search, 'Search schedules', 'Search schedule entries here.', 'left', 'end'),
        makeStep(table, 'Schedule table', 'Your current schedule entries appear here.'),
    ].filter(Boolean)

    console.log('Schedules steps:', steps)
    return steps
}

function buildUpgradeSteps() {
    clearTourMarks()

    const freeCard = mark('free-card', closestCard(findHeading('FREE')))
    const plyrCard = mark('plyr-card', closestCard(findHeading('PLYR')))
    const journeyCard = mark('journey-card', closestCard(findHeading('MY JOURNEY')))

    const steps = [
        makeStep(freeCard, 'Free plan', 'This card shows the free plan features.'),
        makeStep(plyrCard, 'PLYR plan', 'This is your middle-tier plan option.'),
        makeStep(journeyCard, 'My Journey plan', 'This is your premium plan option.'),
    ].filter(Boolean)

    console.log('Upgrade steps:', steps)
    return steps
}

function getTourConfig(page) {
    if (page === 'dashboard') return { key: TOUR_KEYS.dashboard, steps: buildDashboardSteps() }
    if (page === 'profile') return { key: TOUR_KEYS.profile, steps: buildProfileSteps() }
    if (page === 'schedules') return { key: TOUR_KEYS.schedules, steps: buildSchedulesSteps() }
    if (page === 'upgrade') return { key: TOUR_KEYS.upgrade, steps: buildUpgradeSteps() }

    return null
}

function startTourForCurrentPage() {
    const page = detectPage()
    console.log('Detected onboarding page:', page)

    if (!page) return

    const config = getTourConfig(page)
    console.log('Tour config:', config)

    if (!config || !config.steps.length) {
        console.log('No tour steps found')
        return
    }

    if (localStorage.getItem(config.key) === 'done') {
        console.log('Tour already completed:', config.key)
        return
    }

    if (!window.driver || !window.driver.js || !window.driver.js.driver) {
        console.error('Driver.js not available on window')
        return
    }

    const driverObj = window.driver.js.driver({
        showProgress: true,
        allowClose: true,
        nextBtnText: 'Next',
        prevBtnText: 'Back',
        doneBtnText: 'Finish',
        steps: config.steps,
        onDestroyed: () => {
            setTourDone(config.key)
        },
    })

    driverObj.drive()
}

window.startPlyrCardTour = function () {
    startTourForCurrentPage()
}

window.resetPlyrCardTours = function () {
    Object.values(TOUR_KEYS).forEach((key) => localStorage.removeItem(key))
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOMContentLoaded onboarding')
    console.log('PLYRCARD_ONBOARDING_ENABLED:', window.PLYRCARD_ONBOARDING_ENABLED)

    if (!window.PLYRCARD_ONBOARDING_ENABLED) return

    setTimeout(() => {
        startTourForCurrentPage()
    }, 900)
})