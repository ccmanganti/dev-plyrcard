@php
    use Illuminate\Support\Str;

    $plyrUser = auth()->user();
    $plyrLoggedIn = auth()->check();
    $plyrFirstName = $plyrLoggedIn ? explode(' ', trim($plyrUser->name ?? 'Player'))[0] : null;

    $plyrPlan = 'Free';
    if ($plyrLoggedIn && method_exists($plyrUser, 'hasRole')) {
        if ($plyrUser->hasRole('My Journey')) {
            $plyrPlan = 'My Journey';
        } elseif ($plyrUser->hasRole('Plyr')) {
            $plyrPlan = 'Plyr';
        }
    }

    $plyrHost = request()->getHost();
    $plyrHostNormalized = strtolower(preg_replace('/^www\./i', '', $plyrHost));
    $plyrPath = trim(request()->path(), '/');
    $plyrFirstSegment = $plyrPath === '' ? '' : explode('/', $plyrPath)[0];
    $plyrActivePage = $activePage ?? null;

    $plyrMainHosts = ['plyrcard.com', 'localhost', '127.0.0.1'];
    $plyrReservedPaths = [
        '', 'admin', 'about', 'pricing', 'podcast', 'book-demo', 'registration', 'login', 'logout',
        'dashboard', 'journey', 'api', 'css', 'js', 'images', 'storage', 'livewire', 'filament',
    ];

    $plyrCurrentWebsite = null;

    if (class_exists(\App\Models\Website::class)) {
        if (! in_array($plyrHostNormalized, $plyrMainHosts, true)) {
            $plyrCurrentWebsite = \App\Models\Website::query()
                ->where('is_active', true)
                ->where('is_published', true)
                ->whereNotNull('domain')
                ->get()
                ->first(function ($website) use ($plyrHostNormalized) {
                    $domain = strtolower(trim((string) $website->domain));
                    $domain = preg_replace('#^https?://#i', '', $domain);
                    $domain = preg_replace('/^www\./i', '', $domain);
                    $domain = trim($domain, '/');

                    return $domain !== '' && $domain === $plyrHostNormalized;
                });
        }

        if (! $plyrCurrentWebsite && in_array($plyrHostNormalized, $plyrMainHosts, true) && ! in_array($plyrFirstSegment, $plyrReservedPaths, true)) {
            $plyrCurrentWebsite = \App\Models\Website::query()
                ->where('is_active', true)
                ->where('is_published', true)
                ->where('slug', $plyrFirstSegment)
                ->first();
        }
    }

    $plyrIsPlayerWebsite = (bool) $plyrCurrentWebsite;
    $plyrIsOwnPlayerWebsite = $plyrLoggedIn && $plyrCurrentWebsite && (int) $plyrCurrentWebsite->user_id === (int) $plyrUser->id;
    $plyrIsAdmin = request()->is('admin') || request()->is('admin/*') || $plyrActivePage === 'admin';

    // Visibility rules:
    // Main PLYRCard/admin site: logged out = GET STARTED, logged in = Locker Room.
    // Own player site: logged in owner = Locker Room only.
    // Other player site/logged-out player site: show nothing.
    $plyrShowActionDrawer = $plyrIsPlayerWebsite ? $plyrIsOwnPlayerWebsite : true;
    $plyrHideHeaderNavigation = $plyrIsPlayerWebsite || $plyrIsAdmin;

    $plyrWebsiteUrl = '#';
    if ($plyrLoggedIn && class_exists(\App\Models\Website::class)) {
        $plyrOwnWebsite = \App\Models\Website::query()
            ->where('user_id', $plyrUser->id)
            ->where('is_active', true)
            ->where('is_published', true)
            ->latest('updated_at')
            ->first();

        if ($plyrOwnWebsite) {
            if (! blank($plyrOwnWebsite->domain)) {
                $domain = preg_replace('#^https?://#i', '', trim($plyrOwnWebsite->domain));
                $plyrWebsiteUrl = 'https://' . trim($domain, '/');
            } elseif (! blank($plyrOwnWebsite->slug)) {
                $plyrWebsiteUrl = '/' . ltrim($plyrOwnWebsite->slug, '/');
            }
        }
    }

    $plyrWebsiteActionLabel = $plyrIsOwnPlayerWebsite ? 'Edit my Website' : 'View my Website';
    $plyrWebsiteActionSection = $plyrIsOwnPlayerWebsite ? 'edit-website' : 'view-website';
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

<style>
    :root {
        --plyrcard-nav-height: calc(var(--header-h, 76px) + var(--safe-top, 0px));
        --plyrcard-accent: #FF5C35;
        --plyrcard-black: #030303;
        --plyrcard-white: #ffffff;
        --plyrcard-font: 'Antonio', var(--font-display, Impact, 'Arial Narrow', sans-serif);
    }

    #site-header.plyrcard-site-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 9999 !important;
        height: var(--plyrcard-nav-height) !important;
        min-height: calc(76px + var(--safe-top, 0px)) !important;
        padding: var(--safe-top, 0px) 24px 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        background: transparent !important;
        border-bottom: 1px solid transparent !important;
        transition: background 0.3s ease, border-color 0.3s ease, backdrop-filter 0.3s ease !important;
    }

    #site-header.plyrcard-site-header.scrolled {
        background: rgba(13,13,13,0.92) !important;
        border-bottom-color: rgba(255,255,255,0.07) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
    }

    #site-header.plyrcard-site-header .logo-wrap {
        display: flex !important;
        align-items: center !important;
        height: 50px !important;
        flex: 0 0 auto !important;
        gap: 0 !important;
    }

    #site-header.plyrcard-site-header .logo-wrap img {
        height: 32px !important;
        width: auto !important;
        object-fit: contain !important;
        display: block !important;
    }

    #site-header.plyrcard-site-header .desktop-nav {
        margin-left: auto !important;
        display: none !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: clamp(30px, 3vw, 48px) !important;
        font-family: var(--plyrcard-font) !important;
        font-size: clamp(22px, 1.45vw, 30px) !important;
        line-height: 1 !important;
        font-weight: 800 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        white-space: nowrap !important;
    }

    #site-header.plyrcard-site-header .desktop-nav a {
        font: inherit !important;
        line-height: 1 !important;
        letter-spacing: inherit !important;
        text-transform: inherit !important;
        color: rgba(255,255,255,0.72) !important;
        text-decoration: none !important;
        padding: 8px 0 !important;
        margin: 0 !important;
        white-space: nowrap !important;
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        text-shadow: 0 2px 18px rgba(0,0,0,0.28) !important;
        transition: color 0.2s ease, transform 0.2s ease, background 0.2s ease !important;
    }

    #site-header.plyrcard-site-header .desktop-nav a:hover,
    #site-header.plyrcard-site-header .desktop-nav a.active {
        color: #fff !important;
    }

    #site-header.plyrcard-site-header .desktop-nav .desktop-nav-cta {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: 58px !important;
        padding: 18px 28px !important;
        border-radius: 9999px !important;
        background: var(--plyrcard-accent) !important;
        color: #fff !important;
        box-shadow: 0 14px 34px rgba(255,92,53,0.28) !important;
    }

    #site-header.plyrcard-site-header .menu-btn {
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 5px !important;
        width: 44px !important;
        height: 44px !important;
        margin: 0 -6px 0 0 !important;
        padding: 10px 6px !important;
        background: transparent !important;
        border: 0 !important;
        cursor: pointer !important;
    }

    #site-header.plyrcard-site-header .menu-btn span {
        display: block !important;
        width: 24px !important;
        height: 2px !important;
        background: #fff !important;
        border-radius: 2px !important;
        transition: transform 0.3s ease, opacity 0.3s ease !important;
    }

    #site-header.plyrcard-site-header .menu-btn.open span:nth-child(1) { transform: translateY(7px) rotate(45deg) !important; }
    #site-header.plyrcard-site-header .menu-btn.open span:nth-child(2) { opacity: 0 !important; }
    #site-header.plyrcard-site-header .menu-btn.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg) !important; }

    #mobile-nav.plyrcard-mobile-nav {
        position: fixed !important;
        top: var(--plyrcard-nav-height) !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 9990 !important;
        background: rgba(13,13,13,0.98) !important;
        border-top: 1px solid rgba(255,255,255,0.08) !important;
        border-bottom: 1px solid rgba(255,255,255,0.08) !important;
        backdrop-filter: blur(18px) !important;
        -webkit-backdrop-filter: blur(18px) !important;
        padding: 22px 24px calc(24px + var(--safe-bottom, 0px)) !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 16px !important;
        opacity: 0 !important;
        transform: translateY(-120%) !important;
        pointer-events: none !important;
        transition: transform 0.32s ease, opacity 0.25s ease !important;
    }

    #mobile-nav.plyrcard-mobile-nav.open {
        opacity: 1 !important;
        transform: translateY(0) !important;
        pointer-events: auto !important;
    }

    #mobile-nav.plyrcard-mobile-nav a,
    #mobile-nav.plyrcard-mobile-nav button {
        font-family: var(--plyrcard-font) !important;
        font-size: 18px !important;
        font-weight: 800 !important;
        line-height: 1 !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
        color: rgba(255,255,255,0.76) !important;
        text-decoration: none !important;
        padding: 7px 0 !important;
        background: transparent !important;
        border: 0 !important;
        text-align: left !important;
        cursor: pointer !important;
    }

    #mobile-nav.plyrcard-mobile-nav .nav-cta-pill {
        margin-top: 6px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 9999px !important;
        background: var(--plyrcard-accent) !important;
        color: #fff !important;
        padding: 15px 18px !important;
    }

    .plyrcard-action-drawer,
    .plyrcard-action-drawer * {
        box-sizing: border-box !important;
    }

    .plyrcard-action-drawer {
        position: fixed !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        z-index: 9980 !important;
        pointer-events: none !important;
        font-family: var(--plyrcard-font) !important;
    }

    .plyrcard-action-drawer.is-open {
        pointer-events: auto !important;
    }

    .plyrcard-drawer-scrim {
        position: fixed !important;
        inset: 0 !important;
        background: rgba(0,0,0,0.34) !important;
        backdrop-filter: blur(3px) !important;
        -webkit-backdrop-filter: blur(3px) !important;
        opacity: 0 !important;
        pointer-events: none !important;
        transition: opacity 0.22s ease !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-scrim {
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    .plyrcard-drawer-panel {
        position: fixed !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        max-width: none !important;
        margin: 0 !important;
        max-height: min(78vh, 720px) !important;
        overflow: hidden !important;
        border-radius: 20px 20px 0 0 !important;
        background: #020202 !important;
        border: 0 !important;
        box-shadow: 0 -18px 46px rgba(0,0,0,0.48) !important;
        transform: translateY(100%) !important;
        transition: transform 0.34s cubic-bezier(.2,.8,.2,1) !important;
        pointer-events: auto !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-panel {
        transform: translateY(0) !important;
    }

    .plyrcard-drawer-handle {
        position: absolute !important;
        top: 10px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        width: 55px !important;
        height: 6px !important;
        border-radius: 999px !important;
        background: rgba(0,0,0,0.22) !important;
        z-index: 2 !important;
    }

    .plyrcard-drawer-head {
        min-height: 70px !important;
        padding: 18px 18px 12px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 10px !important;
        background: #fff !important;
        color: #080808 !important;
        border-radius: 20px 20px 0 0 !important;
    }

    .plyrcard-drawer-title-row {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        min-width: 0 !important;
    }

    .plyrcard-drawer-title {
        margin: 0 !important;
        color: #050505 !important;
        font-family: var(--plyrcard-font) !important;
        font-size: 22px !important;
        line-height: 1 !important;
        font-weight: 800 !important;
        letter-spacing: -0.01em !important;
        white-space: nowrap !important;
    }

    .plyrcard-plan-badge,
    .plyrcard-journey-badge {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: 24px !important;
        padding: 5px 8px !important;
        border-radius: 999px !important;
        background: var(--plyrcard-accent) !important;
        color: #fff !important;
        font-size: 11px !important;
        line-height: 1 !important;
        font-weight: 800 !important;
        text-transform: uppercase !important;
        text-decoration: none !important;
    }

    .plyrcard-signout-form {
        margin: 0 !important;
    }

    .plyrcard-signout-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 5px !important;
        min-height: 32px !important;
        padding: 7px 10px !important;
        border-radius: 999px !important;
        border: 0 !important;
        background: #050505 !important;
        color: #fff !important;
        font-family: var(--plyrcard-font) !important;
        font-size: 11px !important;
        font-weight: 800 !important;
        cursor: pointer !important;
    }

    .plyrcard-drawer-actions {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
    }

    .plyrcard-drawer-icon-btn,
    .plyrcard-drawer-back {
        flex: 0 0 auto !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 34px !important;
        min-height: 34px !important;
        padding: 0 !important;
        background: transparent !important;
        border: 0 !important;
        color: #050505 !important;
        cursor: pointer !important;
        font: inherit !important;
        text-decoration: none !important;
    }

    .plyrcard-drawer-back {
        gap: 5px !important;
        min-width: auto !important;
        font-size: 16px !important;
        font-weight: 800 !important;
        line-height: 1 !important;
    }

    .plyrcard-drawer-icon-btn i,
    .plyrcard-drawer-back i {
        font-size: 18px !important;
    }

    .plyrcard-drawer-body {
        padding: 14px 16px calc(90px + var(--safe-bottom, 0px)) !important;
        color: #fff !important;
        overflow-y: auto !important;
        max-height: calc(min(78vh, 720px) - 70px) !important;
        background: #020202 !important;
    }

    .plyrcard-drawer-view {
        display: none !important;
        animation: plyrcardScreenIn 0.18s ease both !important;
    }

    .plyrcard-drawer-view.is-active {
        display: block !important;
    }

    @keyframes plyrcardScreenIn {
        from { opacity: 0; transform: translateY(7px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .plyrcard-drawer-group {
        margin-bottom: 13px !important;
    }

    .plyrcard-drawer-group-title {
        margin: 0 0 7px !important;
        color: rgba(255,255,255,0.58) !important;
        font-family: var(--plyrcard-font) !important;
        font-size: 12px !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
    }

    .plyrcard-drawer-grid {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 8px !important;
    }

    .plyrcard-drawer-card {
        min-height: 68px !important;
        padding: 8px 6px 7px !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        border-radius: 8px !important;
        background: #fff !important;
        color: #050505 !important;
        border: 1px solid rgba(0,0,0,0.08) !important;
        box-shadow: 0 4px 10px rgba(0,0,0,0.18) !important;
        text-decoration: none !important;
        text-align: center !important;
        cursor: pointer !important;
        font-family: var(--plyrcard-font) !important;
        transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease !important;
    }

    .plyrcard-drawer-card:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 8px 18px rgba(0,0,0,0.25) !important;
    }

    .plyrcard-drawer-card.is-accent {
        background: var(--plyrcard-accent) !important;
        color: #fff !important;
    }

    .plyrcard-drawer-card.is-disabled {
        opacity: 0.48 !important;
        pointer-events: none !important;
    }

    .plyrcard-drawer-card i {
        font-size: 16px !important;
        line-height: 1 !important;
        color: currentColor !important;
    }

    .plyrcard-drawer-card span {
        display: block !important;
        font-size: 12px !important;
        font-weight: 800 !important;
        line-height: 1.05 !important;
        color: currentColor !important;
    }

    .plyrcard-drawer-tab {
        position: fixed !important;
        right: 0 !important;
        bottom: 0 !important;
        width: min(220px, 55vw) !important;
        height: 64px !important;
        padding: 0 14px 0 52px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        background: var(--plyrcard-accent) !important;
        color: #fff !important;
        border: 0 !important;
        border-radius: 0 !important;
        font-family: var(--plyrcard-font) !important;
        font-size: 20px !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        text-transform: none !important;
        cursor: pointer !important;
        pointer-events: auto !important;
        clip-path: polygon(42px 0, 100% 0, 100% 100%, 0% 100%) !important;
        z-index: 9985 !important;
    }

    .plyrcard-drawer-tab i {
        font-size: 14px !important;
        transition: transform 0.24s ease !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-tab i {
        transform: rotate(180deg) !important;
    }

    .plyrcard-panel-card,
    .plyrcard-placeholder-panel {
        padding: 16px !important;
        border-radius: 12px !important;
        background: rgba(255,255,255,0.06) !important;
        border: 1px solid rgba(255,255,255,0.12) !important;
        color: rgba(255,255,255,0.82) !important;
        font-family: var(--plyrcard-font) !important;
        font-size: 15px !important;
        line-height: 1.35 !important;
    }

    .plyrcard-panel-title {
        margin: 0 0 8px !important;
        color: #fff !important;
        font-size: 22px !important;
        line-height: 1 !important;
        font-weight: 900 !important;
    }

    .plyrcard-form-stack {
        display: grid !important;
        gap: 10px !important;
    }

    .plyrcard-field,
    .plyrcard-drawer-input,
    .plyrcard-drawer-select,
    .plyrcard-drawer-textarea {
        width: 100% !important;
        min-height: 42px !important;
        border-radius: 10px !important;
        border: 1px solid rgba(255,255,255,0.10) !important;
        background: rgba(255,255,255,0.96) !important;
        color: #111 !important;
        padding: 10px 12px !important;
        font-family: var(--plyrcard-font) !important;
        font-size: 15px !important;
        font-weight: 700 !important;
        outline: none !important;
    }

    .plyrcard-drawer-textarea {
        min-height: 82px !important;
        resize: vertical !important;
    }

    .plyrcard-submit-btn,
    .plyrcard-copy-btn,
    .plyrcard-mini-btn {
        min-height: 42px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 7px !important;
        border: 0 !important;
        border-radius: 10px !important;
        background: var(--plyrcard-accent) !important;
        color: #fff !important;
        font-family: var(--plyrcard-font) !important;
        font-size: 15px !important;
        font-weight: 900 !important;
        cursor: pointer !important;
        text-decoration: none !important;
        padding: 10px 14px !important;
    }

    .plyrcard-link-row {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-wrap: wrap !important;
        gap: 10px !important;
        margin-top: 8px !important;
    }

    .plyrcard-link-row a,
    .plyrcard-link-row button {
        background: transparent !important;
        border: 0 !important;
        color: rgba(255,255,255,0.76) !important;
        font-family: var(--plyrcard-font) !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        text-decoration: none !important;
        cursor: pointer !important;
    }

    .plyrcard-offer-list {
        display: grid !important;
        gap: 10px !important;
    }

    .plyrcard-offer-card {
        display: grid !important;
        grid-template-columns: 46px 1fr auto !important;
        align-items: center !important;
        gap: 10px !important;
        min-height: 78px !important;
        padding: 10px !important;
        border-radius: 10px !important;
        background: #fff !important;
        color: #050505 !important;
        text-decoration: none !important;
    }

    .plyrcard-offer-icon {
        width: 42px !important;
        height: 42px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 9px !important;
        background: #f7f7f7 !important;
        color: #111 !important;
    }

    .plyrcard-offer-title {
        margin: 0 0 3px !important;
        font-size: 17px !important;
        line-height: 1 !important;
        font-weight: 900 !important;
        color: #050505 !important;
    }

    .plyrcard-offer-copy {
        margin: 0 !important;
        color: #303746 !important;
        font-family: var(--plyrcard-font) !important;
        font-size: 13px !important;
        line-height: 1.15 !important;
        font-weight: 600 !important;
    }

    .plyrcard-offer-price {
        text-align: right !important;
        color: #168bff !important;
        font-size: 22px !important;
        font-weight: 950 !important;
        line-height: .9 !important;
        white-space: nowrap !important;
    }

    .plyrcard-offer-price small {
        display: block !important;
        margin-top: 5px !important;
        color: #4d5565 !important;
        font-size: 10px !important;
        line-height: 1 !important;
        letter-spacing: 0.06em !important;
        text-transform: uppercase !important;
    }

    .plyrcard-booking-embed iframe {
        width: 100% !important;
        min-height: 560px !important;
        border: 0 !important;
        background: #fff !important;
        border-radius: 12px !important;
    }

    @media (min-width: 960px) {
        #site-header.plyrcard-site-header .desktop-nav { display: flex !important; }
        #site-header.plyrcard-site-header .menu-btn { display: none !important; }
        #mobile-nav.plyrcard-mobile-nav { display: none !important; }
    }

    @media (max-width: 380px) {
        .plyrcard-drawer-body { padding-left: 11px !important; padding-right: 11px !important; }
        .plyrcard-drawer-grid { gap: 6px !important; }
        .plyrcard-drawer-card { min-height: 64px !important; }
        .plyrcard-drawer-card span { font-size: 11px !important; }
    }
</style>

@if(! $plyrHideHeaderNavigation)
<header id="site-header" class="plyrcard-site-header over-hero">
    <a data-nav href="/" class="logo-wrap" aria-label="PLYRCARD Home">
        <img src="/images/plyr-logo.png" alt="PLYRCARD Logo">
    </a>

    <nav class="desktop-nav" aria-label="Primary navigation">
        <a data-nav href="/" class="{{ ($activePage ?? '') === 'home' ? ' active' : '' }}">Home</a>
        <a data-nav href="/about" class="{{ ($activePage ?? '') === 'about' ? ' active' : '' }}">About</a>
        <a data-nav href="/pricing" class="{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Pricing</a>
        <a data-nav href="/podcast" class="{{ ($activePage ?? '') === 'podcast' ? ' active' : '' }}">Podcast</a>
        <a data-nav href="/book-demo" class="{{ ($activePage ?? '') === 'book-demo' ? ' active' : '' }}">Book a Demo</a>
        <button type="button" data-plyrcard-open-drawer style="font:inherit;color:rgba(255,255,255,0.72);background:transparent;border:0;text-transform:uppercase;cursor:pointer;padding:8px 0;">Login</button>
        <button type="button" data-plyrcard-open-drawer class="desktop-nav-cta">Start Free</button>
    </nav>

    <button class="menu-btn" id="menu-btn" type="button" aria-label="Open menu" aria-controls="mobile-nav" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</header>

<nav id="mobile-nav" class="plyrcard-mobile-nav" aria-label="Mobile navigation">
    <a data-nav href="/" class="nav-link{{ ($activePage ?? '') === 'home' ? ' active' : '' }}">Home</a>
    <a data-nav href="/about" class="nav-link{{ ($activePage ?? '') === 'about' ? ' active' : '' }}">About</a>
    <a data-nav href="/pricing" class="nav-link{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Pricing</a>
    <a data-nav href="/podcast" class="nav-link{{ ($activePage ?? '') === 'podcast' ? ' active' : '' }}">Podcast</a>
    <a data-nav href="/book-demo" class="nav-link{{ ($activePage ?? '') === 'book-demo' ? ' active' : '' }}">Book Demo</a>
    <button type="button" class="nav-link" data-plyrcard-open-drawer>{{ $plyrLoggedIn ? 'Locker Room' : 'Get Started' }}</button>
    @guest
        <a data-nav href="/registration?utm_plan=free" class="nav-cta-pill{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
    @endguest
</nav>
@endif

@if($plyrShowActionDrawer)
<div id="plyrcard-action-drawer" class="plyrcard-action-drawer" data-state="closed" data-logged-in="{{ $plyrLoggedIn ? 'true' : 'false' }}">
    <div class="plyrcard-drawer-scrim" data-plyrcard-close-drawer></div>

    <section class="plyrcard-drawer-panel" aria-label="{{ $plyrLoggedIn ? 'Locker Room menu' : 'Get Started menu' }}">
        <div class="plyrcard-drawer-handle" aria-hidden="true"></div>

        <div class="plyrcard-drawer-head">
            <div class="plyrcard-drawer-title-row" data-plyrcard-main-title>
                @auth
                    <h2 class="plyrcard-drawer-title">Hi {{ $plyrFirstName ?? 'Player' }}!</h2>
                    <span class="plyrcard-plan-badge">{{ $plyrPlan }}</span>
                @else
                    <h2 class="plyrcard-drawer-title">Get Started</h2>
                @endauth
            </div>

            <div class="plyrcard-drawer-title-row" data-plyrcard-sub-title style="display:none !important;">
                <button type="button" class="plyrcard-drawer-back" data-plyrcard-back>
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    <span>Back</span>
                </button>
                <h2 class="plyrcard-drawer-title" data-plyrcard-section-title></h2>
            </div>

            <div class="plyrcard-drawer-actions">
                @auth
                    <form class="plyrcard-signout-form" action="/admin/logout" method="POST" data-plyrcard-logout-form>
                        @csrf
                        <button class="plyrcard-signout-btn" type="submit"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</button>
                    </form>
                @endauth
                <button type="button" class="plyrcard-drawer-icon-btn" aria-label="Close menu" data-plyrcard-close-drawer>
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="plyrcard-drawer-body">
            @auth
                <div class="plyrcard-drawer-view is-active" data-plyrcard-view="main">
                    <div class="plyrcard-drawer-group">
                        <h3 class="plyrcard-drawer-group-title">Locker Room</h3>
                        <div class="plyrcard-drawer-grid">
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="dashboard"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="profile"><i class="fa-solid fa-user"></i><span>Profile</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="schedule"><i class="fa-solid fa-calendar-days"></i><span>My Schedule</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="settings"><i class="fa-solid fa-gear"></i><span>Settings</span></button>
                        </div>
                    </div>

                    <div class="plyrcard-drawer-group">
                        <h3 class="plyrcard-drawer-group-title">Website</h3>
                        <div class="plyrcard-drawer-grid">
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="{{ $plyrWebsiteActionSection }}"><i class="fa-solid fa-globe"></i><span>{{ $plyrWebsiteActionLabel }}</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="share-card"><i class="fa-solid fa-qrcode"></i><span>Share Card</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="plyrcard-show"><i class="fa-solid fa-podcast"></i><span>PLYRCard Show</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="a-la-carte"><i class="fa-solid fa-bag-shopping"></i><span>A La Carte</span></button>
                        </div>
                    </div>

                    <div class="plyrcard-drawer-group">
                        <h3 class="plyrcard-drawer-group-title">Growth</h3>
                        <div class="plyrcard-drawer-grid">
                            <button type="button" class="plyrcard-drawer-card{{ $plyrPlan === 'My Journey' ? ' is-disabled' : '' }}" data-plyrcard-section="upgrade"><i class="fa-solid fa-arrow-trend-up"></i><span>Upgrade</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="refer-friend"><i class="fa-solid fa-user-plus"></i><span>Refer Friend</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="support"><i class="fa-solid fa-headset"></i><span>Support</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="book-call"><i class="fa-solid fa-calendar-check"></i><span>Book a Call</span></button>
                        </div>
                    </div>

                    <div class="plyrcard-drawer-group">
                        <h3 class="plyrcard-drawer-group-title">Account</h3>
                        <div class="plyrcard-drawer-grid">
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="billing"><i class="fa-solid fa-credit-card"></i><span>Billing</span></button>
                        </div>
                    </div>
                </div>

                <div class="plyrcard-drawer-view" data-plyrcard-view="dashboard" data-title="Dashboard"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">Dashboard</h3><p>Your dashboard tools will appear here.</p></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="profile" data-title="Profile"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">Profile</h3><p>Edit profile details, bio, stats, and media here.</p></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="schedule" data-title="My Schedule"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">My Schedule</h3><p>Schedule tools placeholder.</p></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="settings" data-title="Settings"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">Settings</h3><p>Account settings placeholder.</p></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="billing" data-title="Billing"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">Billing</h3><p>Billing and invoices placeholder.</p></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="upgrade" data-title="Upgrade"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">Upgrade</h3><p>Upgrade options placeholder. Current plan: {{ $plyrPlan }}.</p></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="edit-website" data-title="Edit my Website"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">Edit my Website</h3><p>Website editor placeholder.</p></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="view-website" data-title="View my Website"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">View my Website</h3><p>Open your published website.</p><a class="plyrcard-mini-btn" href="{{ $plyrWebsiteUrl }}">Open Website</a></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="share-card" data-title="Share Card"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">Share Card</h3><p>Choose what to share.</p><div class="plyrcard-drawer-grid" style="margin-top:10px;"><a class="plyrcard-drawer-card" href="#"><i class="fa-brands fa-facebook-f"></i><span>Facebook</span></a><a class="plyrcard-drawer-card" href="#"><i class="fa-brands fa-instagram"></i><span>Instagram</span></a><a class="plyrcard-drawer-card" href="#"><i class="fa-brands fa-x-twitter"></i><span>X</span></a><a class="plyrcard-drawer-card" href="{{ $plyrWebsiteUrl }}"><i class="fa-solid fa-globe"></i><span>Website</span></a></div></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="plyrcard-show" data-title="PLYRCard Show"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">PLYRCard Show</h3><a class="plyrcard-mini-btn" href="/podcast">Open Podcast</a></div></div>

                <div class="plyrcard-drawer-view" data-plyrcard-view="a-la-carte" data-title="A La Carte">
                    <div class="plyrcard-offer-list">
                        <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-solid fa-window-maximize"></i></span><span><h3 class="plyrcard-offer-title">Upgraded Site Design</h3><p class="plyrcard-offer-copy">A full redesign of your athlete website</p></span><strong class="plyrcard-offer-price">$150<small>One-time</small></strong></a>
                        <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-regular fa-images"></i></span><span><h3 class="plyrcard-offer-title">Starting Graphics Bundle</h3><p class="plyrcard-offer-copy">Starting graphic • Showcase graphic • Thank You graphic</p></span><strong class="plyrcard-offer-price">$70<small>Bundle</small></strong></a>
                        <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-solid fa-pen-nib"></i></span><span><h3 class="plyrcard-offer-title">Individual Graphic</h3><p class="plyrcard-offer-copy">Single custom athlete graphic</p></span><strong class="plyrcard-offer-price">$35<small>Each</small></strong></a>
                        <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-solid fa-globe"></i></span><span><h3 class="plyrcard-offer-title">Domain</h3><p class="plyrcard-offer-copy">Custom domain registration for your athlete site</p></span><strong class="plyrcard-offer-price">$45<small>/Year</small></strong></a>
                    </div>
                </div>

                <div class="plyrcard-drawer-view" data-plyrcard-view="refer-friend" data-title="Refer Friend">
                    <form class="plyrcard-form-stack" action="#" method="POST">@csrf
                        <input class="plyrcard-drawer-input" name="friend_name" placeholder="Friend's name">
                        <input class="plyrcard-drawer-input" type="email" name="friend_email" placeholder="Friend's email">
                        <input class="plyrcard-drawer-input" name="friend_phone" placeholder="Friend's phone">
                        <textarea class="plyrcard-drawer-textarea" name="message" placeholder="Add a short message..."></textarea>
                        <button class="plyrcard-submit-btn" type="submit"><i class="fa-regular fa-paper-plane"></i> Send Invite</button>
                    </form>
                </div>

                <div class="plyrcard-drawer-view" data-plyrcard-view="support" data-title="Support">
                    <form class="plyrcard-form-stack" action="#" method="POST">@csrf
                        <select class="plyrcard-drawer-select" name="concern"><option value="">Select your concern</option><option>Billing</option><option>Website update</option><option>Graphics</option><option>Account help</option><option>Other</option></select>
                        <textarea class="plyrcard-drawer-textarea" name="details" placeholder="Give us some more details..."></textarea>
                        <button class="plyrcard-submit-btn" type="submit">Submit</button>
                    </form>
                </div>

                <div class="plyrcard-drawer-view" data-plyrcard-view="book-call" data-title="Book a Call">
                    <div class="plyrcard-booking-embed"><iframe src="https://systems.plyrcard.com/widget/booking/SvuQy1svAyETQ5Q9px9l" scrolling="no" id="SvuQy1svAyETQ5Q9px9l_1778163042192"></iframe></div>
                </div>
            @else
                <div class="plyrcard-drawer-view is-active" data-plyrcard-view="main">
                    <div class="plyrcard-drawer-group">
                        <h3 class="plyrcard-drawer-group-title">Contact</h3>
                        <div class="plyrcard-drawer-grid">
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="email-us"><i class="fa-solid fa-envelope"></i><span>Email Us</span></button>
                            <a class="plyrcard-drawer-card" href="sms:+15718880852"><i class="fa-solid fa-comment-dots"></i><span>Text us</span></a>
                            <a class="plyrcard-drawer-card" href="tel:+15718880852"><i class="fa-solid fa-phone"></i><span>Call us</span></a>
                            <a class="plyrcard-drawer-card" href="https://www.facebook.com/plyrcard" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-messenger"></i><span>Chat Us</span></a>
                        </div>
                    </div>
                    <div class="plyrcard-drawer-group">
                        <h3 class="plyrcard-drawer-group-title">Start</h3>
                        <div class="plyrcard-drawer-grid">
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="share"><i class="fa-solid fa-share-nodes"></i><span>Share</span></button>
                            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="book-demo"><i class="fa-solid fa-calendar-check"></i><span>Book Demo</span></button>
                            <a class="plyrcard-drawer-card" href="/pricing"><i class="fa-solid fa-user-plus"></i><span>Register Now</span></a>
                            <button type="button" class="plyrcard-drawer-card is-accent" data-plyrcard-section="login"><i class="fa-solid fa-right-to-bracket"></i><span>Login</span></button>
                        </div>
                    </div>
                </div>

                <div class="plyrcard-drawer-view" data-plyrcard-view="email-us" data-title="Email Us"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">Email Us</h3><p>Reach us at:</p><a class="plyrcard-mini-btn" href="mailto:support@plyrcard.com">support@plyrcard.com</a></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="share" data-title="Share"><div class="plyrcard-panel-card"><h3 class="plyrcard-panel-title">Copy PLYRCard URL</h3><input class="plyrcard-drawer-input" id="plyrcard-share-url" value="https://plyrcard.com" readonly><button type="button" class="plyrcard-copy-btn" data-plyrcard-copy-url data-copy-target="plyrcard-share-url"><i class="fa-regular fa-copy"></i> Copy URL</button></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="book-demo" data-title="Book Demo"><div class="plyrcard-booking-embed"><iframe src="https://systems.plyrcard.com/widget/booking/SvuQy1svAyETQ5Q9px9l" scrolling="no" id="SvuQy1svAyETQ5Q9px9l_1778163042192"></iframe></div></div>
                <div class="plyrcard-drawer-view" data-plyrcard-view="login" data-title="Login">
                    <form class="plyrcard-form-stack" action="/admin/login" method="POST">@csrf
                        <input class="plyrcard-drawer-input" type="email" name="email" placeholder="Email address" autocomplete="email" required>
                        <input class="plyrcard-drawer-input" type="password" name="password" placeholder="Password" autocomplete="current-password" required>
                        <label style="display:flex;align-items:center;gap:8px;color:#fff;font-family:var(--plyrcard-font);font-size:13px;"><input type="checkbox" name="remember" value="1"> Remember me</label>
                        <button class="plyrcard-submit-btn" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
                        <div class="plyrcard-link-row"><a href="/admin/password-reset/request">Forgot password?</a><a href="/pricing">Register</a></div>
                    </form>
                </div>
            @endauth
        </div>
    </section>

    <button type="button" class="plyrcard-drawer-tab" data-plyrcard-toggle-drawer aria-expanded="false">
        <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
        <span>{{ $plyrLoggedIn ? 'Locker Room' : 'GET STARTED' }}</span>
    </button>
</div>
@endif

<script>
(function () {
    const header = document.getElementById('site-header');
    const menuButton = document.getElementById('menu-btn');
    const mobileNav = document.getElementById('mobile-nav');

    if (header) {
        const onScroll = function () {
            header.classList.toggle('scrolled', window.scrollY > 12);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    if (menuButton && mobileNav) {
        menuButton.addEventListener('click', function () {
            const isOpen = mobileNav.classList.toggle('open');
            menuButton.classList.toggle('open', isOpen);
            menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    const drawer = document.getElementById('plyrcard-action-drawer');
    if (!drawer) return;

    const toggleButtons = document.querySelectorAll('[data-plyrcard-toggle-drawer], [data-plyrcard-open-drawer]');
    const closeButtons = drawer.querySelectorAll('[data-plyrcard-close-drawer]');
    const mainTitle = drawer.querySelector('[data-plyrcard-main-title]');
    const subTitle = drawer.querySelector('[data-plyrcard-sub-title]');
    const sectionTitle = drawer.querySelector('[data-plyrcard-section-title]');
    const backButton = drawer.querySelector('[data-plyrcard-back]');
    const tabButton = drawer.querySelector('[data-plyrcard-toggle-drawer]');
    const panelBody = drawer.querySelector('.plyrcard-drawer-body');

    function setOpen(isOpen) {
        drawer.classList.toggle('is-open', isOpen);
        drawer.dataset.state = isOpen ? 'open' : 'closed';
        if (tabButton) tabButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.documentElement.classList.toggle('plyrcard-drawer-open', isOpen);
        if (isOpen) window.requestAnimationFrame(() => showView('main'));
    }

    function showView(name) {
        const view = drawer.querySelector('[data-plyrcard-view="' + name + '"]');
        if (!view) return;

        drawer.querySelectorAll('[data-plyrcard-view]').forEach(function (item) {
            item.classList.toggle('is-active', item === view);
        });

        const isMain = name === 'main';
        if (mainTitle) mainTitle.style.setProperty('display', isMain ? 'flex' : 'none', 'important');
        if (subTitle) subTitle.style.setProperty('display', isMain ? 'none' : 'flex', 'important');
        if (sectionTitle) sectionTitle.textContent = view.dataset.title || '';
        if (panelBody) panelBody.scrollTop = 0;
    }

    toggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const willOpen = !drawer.classList.contains('is-open');
            setOpen(willOpen);
            if (willOpen) showView('main');
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setOpen(false);
            showView('main');
        });
    });

    drawer.querySelectorAll('[data-plyrcard-section]').forEach(function (button) {
        button.addEventListener('click', function () {
            showView(button.dataset.plyrcardSection);
            setOpen(true);
        });
    });

    if (backButton) {
        backButton.addEventListener('click', function () {
            showView('main');
        });
    }

    drawer.querySelectorAll('[data-plyrcard-copy-url]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const target = document.getElementById(button.dataset.copyTarget || '');
            if (!target) return;
            try {
                await navigator.clipboard.writeText(target.value);
                button.textContent = 'Copied!';
                window.setTimeout(function () { button.innerHTML = '<i class="fa-regular fa-copy"></i> Copy URL'; }, 1300);
            } catch (error) {
                target.select();
                document.execCommand('copy');
            }
        });
    });

    drawer.querySelectorAll('[data-plyrcard-logout-form]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            try {
                await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
            } finally {
                window.location.href = '/';
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
            showView('main');
        }
    });

    showView('main');
})();
</script>

<script src="https://systems.plyrcard.com/js/form_embed.js" type="text/javascript"></script>