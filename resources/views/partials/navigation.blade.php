@once
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
@endonce

@php
    use App\Models\Website;
    use Illuminate\Support\Str;

    $plyrUser = auth()->user();
    $plyrLoggedIn = auth()->check();

    $plyrFirstName = 'Player';
    if ($plyrLoggedIn && $plyrUser) {
        $rawFirstName = $plyrUser->first_name
            ?? $plyrUser->firstname
            ?? $plyrUser->given_name
            ?? null;

        $plyrFirstName = $rawFirstName
            ? trim($rawFirstName)
            : Str::of($plyrUser->name ?? 'Player')->trim()->explode(' ')->first();
    }

    $plyrPlanName = 'Free';
    if ($plyrLoggedIn && $plyrUser && method_exists($plyrUser, 'hasRole')) {
        if ($plyrUser->hasRole('My Journey')) {
            $plyrPlanName = 'My Journey';
        } elseif ($plyrUser->hasRole('Plyr')) {
            $plyrPlanName = 'Plyr';
        } elseif ($plyrUser->hasRole('Free')) {
            $plyrPlanName = 'Free';
        }
    }

    $plyrHasMyJourneyRole = $plyrLoggedIn && $plyrUser && method_exists($plyrUser, 'hasRole')
        ? $plyrUser->hasRole('My Journey')
        : false;

    $plyrWebsite = null;
    $plyrWebsiteUrl = null;

    if ($plyrLoggedIn && $plyrUser && class_exists(Website::class)) {
        $plyrWebsite = Website::query()
            ->where('user_id', $plyrUser->id)
            ->where('is_active', true)
            ->where('is_published', true)
            ->latest('updated_at')
            ->first();

        if ($plyrWebsite) {
            if (! blank($plyrWebsite->domain)) {
                $domain = preg_replace('#^https?://#i', '', trim($plyrWebsite->domain));
                $plyrWebsiteUrl = 'https://' . rtrim($domain, '/');
            } elseif (! blank($plyrWebsite->slug)) {
                $plyrWebsiteUrl = url('/' . ltrim($plyrWebsite->slug, '/'));
            } elseif (! blank($plyrWebsite->name)) {
                $plyrWebsiteUrl = url('/' . Str::slug($plyrWebsite->name));
            }
        }
    }

    $plyrActivePage = $activePage ?? null;
    $plyrCurrentPath = trim(request()->path(), '/');
    $plyrCurrentHost = request()->getHost();
    $plyrReservedPaths = ['', '/', 'about', 'pricing', 'podcast', 'book-demo', 'registration', 'login', 'admin'];
    $plyrOnAdmin = request()->is('admin') || request()->is('admin/*') || $plyrActivePage === 'admin';

    $plyrOnPlayerWebsite = in_array($plyrActivePage, ['website', 'player', 'player-website'], true);
    if (! $plyrOnPlayerWebsite && $plyrWebsite) {
        $slug = trim((string) ($plyrWebsite->slug ?: Str::slug($plyrWebsite->name)), '/');
        $domain = $plyrWebsite->domain ? rtrim(preg_replace('#^https?://#i', '', trim($plyrWebsite->domain)), '/') : null;

        $plyrOnPlayerWebsite = ($slug && $plyrCurrentPath === $slug)
            || ($domain && strtolower($plyrCurrentHost) === strtolower($domain));
    }
    if (! $plyrOnPlayerWebsite && ! $plyrOnAdmin && ! in_array($plyrCurrentPath, $plyrReservedPaths, true)) {
        $plyrOnPlayerWebsite = true;
    }

    $plyrPullUpOnly = $plyrPullUpOnly ?? ($plyrOnAdmin || $plyrOnPlayerWebsite);
    $plyrHideHeaderNavigation = $plyrOnPlayerWebsite;
    $plyrTabLabel = $plyrLoggedIn ? 'Locker Room' : 'GET STARTED';
    $plyrWebsiteActionLabel = $plyrOnPlayerWebsite ? 'Edit my Website' : 'View my Website';
    $plyrWebsiteActionHref = $plyrOnPlayerWebsite ? '#' : ($plyrWebsiteUrl ?: '#');
    $plyrWebsiteActionTarget = (! $plyrOnPlayerWebsite && $plyrWebsiteUrl) ? '_blank' : null;
    $plyrWebsiteActionDisabled = (! $plyrOnPlayerWebsite && ! $plyrWebsiteUrl);

    $plyrSupportEmail = 'support@plyrcard.com';
    $plyrPhoneDisplay = '+1 571-888-0852';
    $plyrPhoneHref = '+15718880852';
    $plyrMainShareUrl = 'https://plyrcard.com';
    $plyrWebsiteShareUrl = $plyrWebsiteUrl ?: url('/');
    $plyrLogoutAction = url('/admin/logout');

    $plyrFacebookUrl = $plyrUser->facebook_url ?? $plyrUser->facebook ?? 'https://www.facebook.com/plyrcard';
    $plyrInstagramUrl = $plyrUser->instagram_url ?? $plyrUser->instagram ?? 'https://www.instagram.com/plyrcard/';
    $plyrXUrl = $plyrUser->x_url ?? $plyrUser->twitter_url ?? $plyrUser->twitter ?? 'https://x.com/plyrcard';
    $plyrYouTubeUrl = $plyrUser->youtube_url ?? $plyrUser->youtube ?? 'https://www.youtube.com/@plyrcard';
@endphp

<style>
    /* Existing header/navigation styles kept separate so desktop does not get touched by the drawer. */
    :root {
      --plyrcard-nav-height: calc(var(--header-h, 76px) + var(--safe-top, 0px));
      --plyr-accent: #FF5C35;
      --plyr-font: 'Antonio', 'Arial Narrow', Impact, sans-serif;
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
      transition: background 0.3s var(--ease-out, ease), border-color 0.3s var(--ease-out, ease), backdrop-filter 0.3s var(--ease-out, ease) !important;
    }

    #site-header.plyrcard-site-header.is-pullup-only,
    #mobile-nav.plyrcard-mobile-nav.is-pullup-only {
      display: none !important;
    }

    /* Hide only the normal header/nav on player website pages like /player-name.
       The pull-up Locker Room drawer/tab remains unchanged. */
    #site-header.plyrcard-site-header.is-player-website-header-hidden,
    #mobile-nav.plyrcard-mobile-nav.is-player-website-header-hidden {
      display: none !important;
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
      font-family: var(--font-display, var(--plyr-font)) !important;
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
    #site-header.plyrcard-site-header .desktop-nav a.active { color: var(--white, #fff) !important; }

    #site-header.plyrcard-site-header .desktop-nav .desktop-nav-cta {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      min-height: 58px !important;
      padding: 18px 28px !important;
      border-radius: var(--radius-btn, 9999px) !important;
      background: var(--accent, #ff5c35) !important;
      color: var(--white, #fff) !important;
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
      background: var(--white, #fff) !important;
      border-radius: 2px !important;
    }

    #mobile-nav.plyrcard-mobile-nav {
      position: fixed !important;
      top: var(--plyrcard-nav-height) !important;
      left: 0 !important;
      right: 0 !important;
      z-index: 9990 !important;
      background: rgba(13,13,13,0.98) !important;
      padding: 22px 24px calc(24px + var(--safe-bottom, 0px)) !important;
      display: flex !important;
      flex-direction: column !important;
      gap: 16px !important;
      opacity: 0 !important;
      transform: translateY(-120%) !important;
      pointer-events: none !important;
      transition: transform 0.32s var(--ease-out, ease), opacity 0.25s ease !important;
    }

    #mobile-nav.plyrcard-mobile-nav.open {
      opacity: 1 !important;
      transform: translateY(0) !important;
      pointer-events: auto !important;
    }

    #mobile-nav.plyrcard-mobile-nav a,
    #mobile-nav.plyrcard-mobile-nav button {
      font-family: var(--font-display, var(--plyr-font)) !important;
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
    }

    #mobile-nav.plyrcard-mobile-nav .nav-cta-pill {
      margin-top: 6px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border-radius: var(--radius-btn, 9999px) !important;
      background: var(--accent, #ff5c35) !important;
      color: var(--white, #fff) !important;
      padding: 15px 18px !important;
    }

    @media (min-width: 960px) {
      #site-header.plyrcard-site-header .desktop-nav { display: flex !important; }
      #site-header.plyrcard-site-header .menu-btn { display: none !important; }
      #mobile-nav.plyrcard-mobile-nav { display: none !important; }
    }

    @media (max-width: 767px) {
      #site-header.plyrcard-site-header {
        min-height: calc(64px + var(--safe-top, 0px)) !important;
        padding-left: 20px !important;
        padding-right: 20px !important;
      }
      #site-header.plyrcard-site-header .logo-wrap { height: 46px !important; }
    }

    /* Pull-up navigation only. */
    .plyrcard-action-drawer,
    .plyrcard-action-drawer * {
      box-sizing: border-box !important;
      font-family: var(--plyr-font) !important;
    }

    .plyrcard-action-drawer .fa-solid,
    .plyrcard-action-drawer .fas { font-family: "Font Awesome 6 Free" !important; font-weight: 900 !important; }
    .plyrcard-action-drawer .fa-regular,
    .plyrcard-action-drawer .far { font-family: "Font Awesome 6 Free" !important; font-weight: 400 !important; }
    .plyrcard-action-drawer .fa-brands,
    .plyrcard-action-drawer .fab { font-family: "Font Awesome 6 Brands" !important; font-weight: 400 !important; }

    .plyrcard-action-drawer {
      position: fixed !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      width: 100vw !important;
      max-width: 100vw !important;
      z-index: 100000 !important;
      color: #fff !important;
      pointer-events: none !important;
    }

    .plyrcard-action-drawer.is-open { pointer-events: auto !important; }

    .plyrcard-drawer-scrim {
      position: fixed !important;
      inset: 0 !important;
      background: rgba(0,0,0,.44) !important;
      backdrop-filter: blur(3px) !important;
      -webkit-backdrop-filter: blur(3px) !important;
      opacity: 0 !important;
      pointer-events: none !important;
      transition: opacity .2s ease !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-scrim { opacity: 1 !important; pointer-events: auto !important; }

    .plyrcard-drawer-panel {
      position: fixed !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      width: 100vw !important;
      max-width: 100vw !important;
      margin: 0 !important;
      padding: 0 !important;
      max-height: min(82dvh, 620px) !important;
      background: #050505 !important;
      border-radius: 17px 17px 0 0 !important;
      overflow: hidden !important;
      box-shadow: 0 -18px 46px rgba(0,0,0,.5) !important;
      transform: translateY(100%) !important;
      transition: transform .28s cubic-bezier(.2,.8,.2,1) !important;
      pointer-events: auto !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-panel { transform: translateY(0) !important; }

    .plyrcard-drawer-handle {
      position: absolute !important;
      top: 8px !important;
      left: 50% !important;
      width: 58px !important;
      height: 5px !important;
      border-radius: 999px !important;
      background: rgba(0,0,0,.22) !important;
      transform: translateX(-50%) !important;
      z-index: 2 !important;
    }

    .plyrcard-drawer-head {
      min-height: 56px !important;
      padding: 16px 12px 10px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      gap: 8px !important;
      background: #fff !important;
      color: #050505 !important;
      border-radius: 17px 17px 0 0 !important;
    }

    .plyrcard-drawer-title-row,
    .plyrcard-user-line,
    .plyrcard-drawer-actions {
      display: flex !important;
      align-items: center !important;
      gap: 7px !important;
      min-width: 0 !important;
    }

    .plyrcard-main-title,
    .plyrcard-section-title {
      margin: 0 !important;
      font-size: 16px !important;
      line-height: 1 !important;
      font-weight: 900 !important;
      color: #050505 !important;
      white-space: nowrap !important;
    }

    .plyrcard-plan-badge {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      height: 20px !important;
      padding: 0 7px !important;
      border-radius: 999px !important;
      background: var(--plyr-accent) !important;
      color: #fff !important;
      font-size: 9px !important;
      font-weight: 900 !important;
      line-height: 1 !important;
      text-transform: uppercase !important;
      max-width: 78px !important;
      overflow: hidden !important;
      text-overflow: ellipsis !important;
      white-space: nowrap !important;
    }

    .plyrcard-signout-form { margin: 0 !important; display: inline-flex !important; }

    .plyrcard-signout-btn,
    .plyrcard-drawer-close,
    .plyrcard-drawer-back {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border: 0 !important;
      cursor: pointer !important;
      text-decoration: none !important;
      line-height: 1 !important;
    }

    .plyrcard-signout-btn {
      gap: 5px !important;
      height: 26px !important;
      padding: 0 8px !important;
      border-radius: 999px !important;
      background: #050505 !important;
      color: #fff !important;
      font-size: 11px !important;
      font-weight: 900 !important;
    }

    .plyrcard-drawer-close,
    .plyrcard-drawer-back {
      min-width: 28px !important;
      height: 28px !important;
      padding: 0 !important;
      background: transparent !important;
      color: #050505 !important;
      font-size: 19px !important;
    }

    .plyrcard-drawer-back { gap: 5px !important; min-width: auto !important; font-size: 17px !important; font-weight: 900 !important; }

    .plyrcard-drawer-body {
      padding: 9px 12px 84px !important;
      max-height: calc(min(82dvh, 620px) - 56px) !important;
      overflow-y: auto !important;
      background: #050505 !important;
      color: #fff !important;
    }

    .plyrcard-drawer-view {
      display: none !important;
      opacity: 0 !important;
      transform: translateY(10px) scale(.985) !important;
      transform-origin: top center !important;
    }
    .plyrcard-drawer-view.is-active {
      display: block !important;
      visibility: visible !important;
      opacity: 1 !important;
      transform: none !important;
      filter: none !important;
      animation: plyrcardViewIn .26s cubic-bezier(.2,.8,.2,1) both !important;
    }
    .plyrcard-drawer-view.is-active .plyrcard-nav-group {
      display: block !important;
      visibility: visible !important;
      opacity: 1 !important;
    }
    .plyrcard-drawer-view.is-active .plyrcard-drawer-grid {
      display: grid !important;
      visibility: visible !important;
      opacity: 1 !important;
    }
    .plyrcard-drawer-view.is-active .plyrcard-drawer-card {
      display: flex !important;
      visibility: visible !important;
      opacity: 1 !important;
    }
    .plyrcard-drawer-view.is-active .plyrcard-drawer-card.is-disabled,
    .plyrcard-drawer-view.is-active .plyrcard-drawer-card[aria-disabled="true"] {
      opacity: .46 !important;
    }
    .plyrcard-drawer-panel.is-switching .plyrcard-drawer-view.is-active {
      animation: plyrcardViewIn .26s cubic-bezier(.2,.8,.2,1) both !important;
    }
    @keyframes plyrcardViewIn {
      from { opacity: 0; transform: translateY(12px) scale(.985); filter: blur(2px); }
      to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
    }
    @keyframes plyrcardCardIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .plyrcard-drawer-view.is-active .plyrcard-form-card,
    .plyrcard-drawer-view.is-active .plyrcard-mini-panel,
    .plyrcard-drawer-view.is-active .plyrcard-offer-card {
      animation: plyrcardCardIn .3s cubic-bezier(.2,.8,.2,1) both !important;
    }

    .plyrcard-nav-group + .plyrcard-nav-group { margin-top: 10px !important; }
    .plyrcard-nav-group-title {
      display: block !important;
      margin: 0 0 5px !important;
      color: rgba(255,255,255,.62) !important;
      font-size: 12px !important;
      line-height: 1 !important;
      font-weight: 900 !important;
      text-transform: uppercase !important;
      letter-spacing: .03em !important;
    }

    .plyrcard-drawer-grid {
      display: grid !important;
      grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
      gap: 8px !important;
    }

    .plyrcard-drawer-card {
      min-width: 0 !important;
      min-height: 66px !important;
      padding: 8px 5px 7px !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 6px !important;
      border: 0 !important;
      border-radius: 7px !important;
      background: #fff !important;
      color: #050505 !important;
      box-shadow: 0 4px 10px rgba(0,0,0,.24) !important;
      text-align: center !important;
      text-decoration: none !important;
      cursor: pointer !important;
      font: inherit !important;
    }

    .plyrcard-drawer-card.is-accent { background: var(--plyr-accent) !important; color: #fff !important; }
    .plyrcard-drawer-card.is-disabled,
    .plyrcard-drawer-card[aria-disabled="true"] { opacity: .46 !important; pointer-events: none !important; cursor: not-allowed !important; }
    .plyrcard-drawer-card.is-active-page { background: rgba(255,255,255,.42) !important; color: #050505 !important; }

    .plyrcard-menu-icon { font-size: 17px !important; line-height: 1 !important; color: currentColor !important; }
    .plyrcard-drawer-card span { display: block !important; color: currentColor !important; font-size: 12px !important; line-height: .98 !important; font-weight: 850 !important; }

    .plyrcard-drawer-tab {
      position: fixed !important;
      right: 0 !important;
      bottom: 0 !important;
      width: 210px !important;
      height: 60px !important;
      padding: 0 16px 0 48px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 9px !important;
      border: 0 !important;
      border-radius: 0 !important;
      background: var(--plyr-accent) !important;
      color: #fff !important;
      font-size: 21px !important;
      font-weight: 900 !important;
      line-height: 1 !important;
      cursor: pointer !important;
      pointer-events: auto !important;
      clip-path: polygon(36px 0, 100% 0, 100% 100%, 0 100%) !important;
    }

    .plyrcard-drawer-tab i { font-size: 14px !important; transition: transform .25s ease !important; }
    .plyrcard-action-drawer.is-open .plyrcard-drawer-tab i { transform: rotate(180deg) !important; }

    .plyrcard-drawer-section-divider { margin: 13px 0 12px !important; height: 1px !important; background: rgba(255,255,255,.16) !important; }
    .plyrcard-social-row { display: flex !important; align-items: center !important; gap: 22px !important; color: #fff !important; }
    .plyrcard-social-label { font-size: 21px !important; font-weight: 850 !important; line-height: 1 !important; }
    .plyrcard-social-row a { color: #fff !important; text-decoration: none !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }
    .plyrcard-social-row i { font-size: 23px !important; }

    .plyrcard-form-card,
    .plyrcard-mini-panel {
      border-radius: 16px !important;
      background: linear-gradient(180deg, #ffffff 0%, #f7f7f7 100%) !important;
      color: #111 !important;
      padding: 16px !important;
      box-shadow: 0 10px 26px rgba(0,0,0,.26) !important;
      border: 1px solid rgba(255,255,255,.75) !important;
    }

    .plyrcard-form-stack { display: grid !important; gap: 11px !important; }
    .plyrcard-input-label { display: grid !important; gap: 6px !important; color: rgba(0,0,0,.52) !important; font-size: 11px !important; font-weight: 900 !important; text-transform: uppercase !important; letter-spacing: .035em !important; }
    .plyrcard-input-wrap { position: relative !important; display: block !important; }
    .plyrcard-input-wrap > i { position: absolute !important; left: 12px !important; top: 50% !important; transform: translateY(-50%) !important; color: rgba(0,0,0,.8) !important; font-size: 13px !important; }
    .plyrcard-input-wrap.textarea > i { top: 15px !important; transform: none !important; }

    .plyrcard-drawer-input,
    .plyrcard-drawer-textarea,
    .plyrcard-drawer-select {
      width: 100% !important;
      min-height: 43px !important;
      border-radius: 12px !important;
      border: 1px solid rgba(0,0,0,.075) !important;
      background: #fff !important;
      color: #111 !important;
      padding: 10px 12px 10px 37px !important;
      font-size: 14px !important;
      font-weight: 750 !important;
      outline: none !important;
      box-shadow: inset 0 1px 0 rgba(0,0,0,.02), 0 1px 0 rgba(255,255,255,.75) !important;
      transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease !important;
    }
    .plyrcard-drawer-input:focus,
    .plyrcard-drawer-textarea:focus,
    .plyrcard-drawer-select:focus {
      border-color: rgba(255,92,53,.55) !important;
      box-shadow: 0 0 0 3px rgba(255,92,53,.12) !important;
    }

    .plyrcard-drawer-textarea { min-height: 92px !important; resize: vertical !important; padding-top: 12px !important; }
    .plyrcard-clean-row { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 10px !important; flex-wrap: wrap !important; }
    .plyrcard-text-link { border: 0 !important; background: transparent !important; color: #111 !important; padding: 0 !important; font: inherit !important; text-decoration: underline !important; cursor: pointer !important; }
    .plyrcard-subsection-lead { margin: 0 0 12px !important; color: rgba(255,255,255,.72) !important; font-size: 13px !important; line-height: 1.35 !important; font-weight: 650 !important; }
    .plyrcard-mini-title { margin: 0 0 6px !important; color: #111 !important; font-size: 18px !important; line-height: 1 !important; font-weight: 950 !important; }
    .plyrcard-mini-copy { margin: 0 0 13px !important; color: rgba(0,0,0,.58) !important; font-size: 13px !important; line-height: 1.35 !important; font-weight: 650 !important; }

    .plyrcard-submit-btn,
    .plyrcard-secondary-btn,
    .plyrcard-copy-btn {
      min-height: 42px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 8px !important;
      border: 0 !important;
      border-radius: 10px !important;
      padding: 0 14px !important;
      background: var(--plyr-accent) !important;
      color: #fff !important;
      font-size: 16px !important;
      font-weight: 900 !important;
      text-decoration: none !important;
      cursor: pointer !important;
    }

    .plyrcard-secondary-btn { background: #111 !important; }
    .plyrcard-copy-line { display: grid !important; grid-template-columns: 1fr auto !important; gap: 8px !important; }
    .plyrcard-copy-line input { padding-left: 12px !important; }

    .plyrcard-offer-list { display: grid !important; gap: 9px !important; }
    .plyrcard-offer-card { display: grid !important; grid-template-columns: 52px 1fr auto !important; align-items: center !important; gap: 10px !important; min-height: 74px !important; padding: 10px 14px 10px 10px !important; border-radius: 9px !important; background: #fff !important; color: #050505 !important; text-decoration: none !important; }
    .plyrcard-offer-icon { width: 42px !important; height: 42px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 9px !important; background: #fff !important; box-shadow: 0 3px 9px rgba(0,0,0,.14) !important; }
    .plyrcard-offer-icon i { font-size: 20px !important; color: #050505 !important; }
    .plyrcard-offer-title { margin: 0 0 4px !important; font-size: 18px !important; line-height: 1 !important; font-weight: 900 !important; color: #050505 !important; }
    .plyrcard-offer-copy { margin: 0 !important; color: #303746 !important; font-size: 13px !important; line-height: 1.2 !important; font-weight: 600 !important; }
    .plyrcard-offer-price { text-align: right !important; color: #168bff !important; font-size: 24px !important; font-weight: 950 !important; line-height: .9 !important; white-space: nowrap !important; }
    .plyrcard-offer-price small { display: block !important; margin-top: 5px !important; color: #4d5565 !important; font-size: 10px !important; letter-spacing: .06em !important; text-transform: uppercase !important; }

    .plyrcard-booking-wrap { height: calc(min(82dvh, 620px) - 70px) !important; border-radius: 12px !important; overflow: hidden !important; background: #fff !important; }
    .plyrcard-booking-wrap iframe { display: block !important; width: 100% !important; min-height: 100% !important; border: 0 !important; }

    .plyrcard-qr-wrap { display: grid !important; gap: 12px !important; place-items: center !important; text-align: center !important; }
    .plyrcard-qr-wrap img { width: 170px !important; height: 170px !important; border-radius: 12px !important; background: #fff !important; padding: 8px !important; }
    .plyrcard-share-options { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 8px !important; width: 100% !important; }

    @media (min-width: 768px) {
      .plyrcard-drawer-panel { max-height: min(82dvh, 620px) !important; }
      .plyrcard-drawer-body { padding-left: 12px !important; padding-right: 12px !important; }
      .plyrcard-drawer-tab { width: 210px !important; }
    }
</style>

<header id="site-header" class="plyrcard-site-header over-hero {{ $plyrPullUpOnly ? 'is-pullup-only' : '' }} {{ $plyrHideHeaderNavigation ? 'is-player-website-header-hidden' : '' }}">
  <a data-nav href="/" class="logo-wrap" aria-label="PLYRCARD Home">
    <img src="{{ asset('images/plyr-logo.png') }}" alt="PLYRCARD Logo">
  </a>

  <nav class="desktop-nav" aria-label="Primary navigation">
    <a data-nav href="/" class="{{ ($activePage ?? '') === 'home' ? ' active' : '' }}">Home</a>
    <a data-nav href="/about" class="{{ ($activePage ?? '') === 'about' ? ' active' : '' }}">About</a>
    <a data-nav href="/pricing" class="{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Pricing</a>
    <a data-nav href="/podcast" class="{{ ($activePage ?? '') === 'podcast' ? ' active' : '' }}">Podcast</a>
    <a data-nav href="/book-demo" class="{{ ($activePage ?? '') === 'book-demo' ? ' active' : '' }}">Book a Demo</a>
    @auth
      <a href="#" data-plyrcard-open-drawer>Dashboard</a>
    @else
      <a href="#" data-plyrcard-open-drawer>Login</a>
      <a data-nav href="/registration?utm_plan=free" class="desktop-nav-cta{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
    @endauth
  </nav>

  <button class="menu-btn" id="menu-btn" type="button" aria-label="Open menu" aria-controls="mobile-nav" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</header>

<nav id="mobile-nav" class="plyrcard-mobile-nav {{ $plyrPullUpOnly ? 'is-pullup-only' : '' }} {{ $plyrHideHeaderNavigation ? 'is-player-website-header-hidden' : '' }}" aria-label="Mobile navigation">
  <a data-nav href="/" class="nav-link{{ ($activePage ?? '') === 'home' ? ' active' : '' }}">Home</a>
  <a data-nav href="/about" class="nav-link{{ ($activePage ?? '') === 'about' ? ' active' : '' }}">About</a>
  <a data-nav href="/pricing" class="nav-link{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Pricing</a>
  <a data-nav href="/podcast" class="nav-link{{ ($activePage ?? '') === 'podcast' ? ' active' : '' }}">Podcast</a>
  <a data-nav href="/book-demo" class="nav-link{{ ($activePage ?? '') === 'book-demo' ? ' active' : '' }}">Book Demo</a>
  <button type="button" class="nav-link" data-plyrcard-open-drawer>{{ $plyrTabLabel }}</button>
  @guest
    <a data-nav href="/registration?utm_plan=free" class="nav-cta-pill{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
  @endguest
</nav>

<div id="plyrcard-action-drawer" class="plyrcard-action-drawer" data-state="closed">
  <div class="plyrcard-drawer-scrim" data-plyrcard-close-drawer></div>

  <section class="plyrcard-drawer-panel" aria-label="{{ $plyrLoggedIn ? 'Locker Room menu' : 'Get Started menu' }}">
    <div class="plyrcard-drawer-handle" aria-hidden="true"></div>

    <div class="plyrcard-drawer-head">
      <div class="plyrcard-drawer-title-row" data-plyrcard-main-title>
        @auth
          <div class="plyrcard-user-line">
            <h2 class="plyrcard-main-title">Hi {{ $plyrFirstName }}!</h2>
            <span class="plyrcard-plan-badge">{{ $plyrPlanName }}</span>
          </div>
        @else
          <h2 class="plyrcard-main-title">Get Started</h2>
        @endauth
      </div>

      <div class="plyrcard-drawer-title-row" data-plyrcard-sub-title style="display:none !important;">
        <button type="button" class="plyrcard-drawer-back" data-plyrcard-back>
          <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
          <span>Back</span>
        </button>
        <h2 class="plyrcard-section-title" data-plyrcard-section-title></h2>
      </div>

      <div class="plyrcard-drawer-actions">
        @auth
          <form class="plyrcard-signout-form" method="POST" action="{{ $plyrLogoutAction }}" data-plyrcard-logout-form>
            @csrf
            <button type="submit" class="plyrcard-signout-btn"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign Out</button>
          </form>
        @endauth
        <button type="button" class="plyrcard-drawer-close" aria-label="Close menu" data-plyrcard-close-drawer>
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </div>
    </div>

    <div class="plyrcard-drawer-body">
      @auth
        <div class="plyrcard-drawer-view is-active" data-plyrcard-view="main">
          <div class="plyrcard-nav-group">
            <strong class="plyrcard-nav-group-title">Locker Room</strong>
            <div class="plyrcard-drawer-grid">
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="dashboard"><i class="plyrcard-menu-icon fa-solid fa-gauge-high" aria-hidden="true"></i><span>Dashboard</span></button>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="profile"><i class="plyrcard-menu-icon fa-solid fa-user" aria-hidden="true"></i><span>Profile</span></button>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="schedule"><i class="plyrcard-menu-icon fa-solid fa-calendar-days" aria-hidden="true"></i><span>My Schedule</span></button>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="settings"><i class="plyrcard-menu-icon fa-solid fa-gear" aria-hidden="true"></i><span>Settings</span></button>
            </div>
          </div>

          <div class="plyrcard-nav-group">
            <strong class="plyrcard-nav-group-title">Website</strong>
            <div class="plyrcard-drawer-grid">
              @if($plyrWebsiteActionDisabled)
                <button type="button" class="plyrcard-drawer-card is-disabled" disabled aria-disabled="true"><i class="plyrcard-menu-icon fa-solid fa-globe" aria-hidden="true"></i><span>{{ $plyrWebsiteActionLabel }}</span></button>
              @else
                @if($plyrOnPlayerWebsite)
                <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="edit-website"><i class="plyrcard-menu-icon fa-solid fa-globe" aria-hidden="true"></i><span>{{ $plyrWebsiteActionLabel }}</span></button>
                @else
                <a class="plyrcard-drawer-card" href="{{ $plyrWebsiteActionHref }}" @if($plyrWebsiteActionTarget) target="{{ $plyrWebsiteActionTarget }}" rel="noopener" @endif><i class="plyrcard-menu-icon fa-solid fa-globe" aria-hidden="true"></i><span>{{ $plyrWebsiteActionLabel }}</span></a>
                @endif
              @endif
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="share-card"><i class="plyrcard-menu-icon fa-solid fa-qrcode" aria-hidden="true"></i><span>Share Card</span></button>
              <a class="plyrcard-drawer-card" href="/podcast"><i class="plyrcard-menu-icon fa-solid fa-podcast" aria-hidden="true"></i><span>PLYRCard Show</span></a>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="a-la-carte"><i class="plyrcard-menu-icon fa-solid fa-bag-shopping" aria-hidden="true"></i><span>A La Carte</span></button>
            </div>
          </div>

          <div class="plyrcard-nav-group">
            <strong class="plyrcard-nav-group-title">Growth</strong>
            <div class="plyrcard-drawer-grid">
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="upgrade"><i class="plyrcard-menu-icon fa-solid fa-arrow-trend-up" aria-hidden="true"></i><span>Upgrade</span></button>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="refer-friend"><i class="plyrcard-menu-icon fa-solid fa-user-plus" aria-hidden="true"></i><span>Refer Friend</span></button>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="support"><i class="plyrcard-menu-icon fa-solid fa-headset" aria-hidden="true"></i><span>Support</span></button>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="book-demo"><i class="plyrcard-menu-icon fa-solid fa-calendar-check" aria-hidden="true"></i><span>Book a Call</span></button>
            </div>
          </div>

          <div class="plyrcard-nav-group">
            <strong class="plyrcard-nav-group-title">Account</strong>
            <div class="plyrcard-drawer-grid">
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="billing"><i class="plyrcard-menu-icon fa-solid fa-credit-card" aria-hidden="true"></i><span>Billing</span></button>
            </div>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="refer-friend" data-title="Refer a Friend">
          <form class="plyrcard-form-card plyrcard-form-stack" action="#" method="POST">
            @csrf
            <label class="plyrcard-input-label">Friend Name<span class="plyrcard-input-wrap"><i class="fa-regular fa-user" aria-hidden="true"></i><input class="plyrcard-drawer-input" name="friend_name" placeholder="Full name"></span></label>
            <label class="plyrcard-input-label">Friend Email<span class="plyrcard-input-wrap"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input class="plyrcard-drawer-input" type="email" name="friend_email" placeholder="friend@example.com"></span></label>
            <label class="plyrcard-input-label">Friend Phone<span class="plyrcard-input-wrap"><i class="fa-solid fa-phone" aria-hidden="true"></i><input class="plyrcard-drawer-input" name="friend_phone" placeholder="{{ $plyrPhoneDisplay }}"></span></label>
            <label class="plyrcard-input-label">Message<span class="plyrcard-input-wrap textarea"><i class="fa-regular fa-message" aria-hidden="true"></i><textarea class="plyrcard-drawer-textarea" name="message" placeholder="Add a short message..."></textarea></span></label>
            <button class="plyrcard-submit-btn" type="submit"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send Invite</button>
          </form>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="support" data-title="Support">
          <div class="plyrcard-form-card plyrcard-form-stack">
            <label class="plyrcard-input-label">Concern<span class="plyrcard-input-wrap"><i class="fa-solid fa-circle-question" aria-hidden="true"></i><select class="plyrcard-drawer-select"><option>Select your concern</option><option>Billing</option><option>Website</option><option>Account</option><option>Other</option></select></span></label>
            <label class="plyrcard-input-label">Details<span class="plyrcard-input-wrap textarea"><i class="fa-regular fa-message" aria-hidden="true"></i><textarea class="plyrcard-drawer-textarea" placeholder="Give us some more details..."></textarea></span></label>
            <button class="plyrcard-submit-btn" type="button">Submit</button>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="share-card" data-title="Share Card">
          <div class="plyrcard-form-card plyrcard-qr-wrap">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($plyrWebsiteShareUrl) }}" alt="QR code for your PLYRCard">
            <div class="plyrcard-copy-line" style="width:100%;">
              <input class="plyrcard-drawer-input" type="text" value="{{ $plyrWebsiteShareUrl }}" readonly data-plyrcard-copy-source>
              <button type="button" class="plyrcard-copy-btn" data-plyrcard-copy="{{ $plyrWebsiteShareUrl }}">Copy</button>
            </div>
            <div class="plyrcard-share-options">
              <a class="plyrcard-secondary-btn" href="{{ $plyrFacebookUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f"></i> Facebook</a>
              <a class="plyrcard-secondary-btn" href="{{ $plyrInstagramUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i> Instagram</a>
              <a class="plyrcard-secondary-btn" href="{{ $plyrXUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i> X</a>
              <a class="plyrcard-submit-btn" href="{{ $plyrWebsiteShareUrl }}" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> Website</a>
            </div>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="a-la-carte" data-title="A La Carte">
          <div class="plyrcard-offer-list">
            <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-regular fa-window-maximize"></i></span><span><h3 class="plyrcard-offer-title">Upgraded Site Design</h3><p class="plyrcard-offer-copy">A full redesign of your athlete website</p></span><strong class="plyrcard-offer-price">$150<small>One-time</small></strong></a>
            <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-regular fa-images"></i></span><span><h3 class="plyrcard-offer-title">Starting Graphics Bundle</h3><p class="plyrcard-offer-copy">Starting graphic • Showcase graphic • Thank You graphic</p></span><strong class="plyrcard-offer-price">$70<small>Bundle</small></strong></a>
            <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-solid fa-pen-nib"></i></span><span><h3 class="plyrcard-offer-title">Individual Graphic</h3><p class="plyrcard-offer-copy">Single custom athlete graphic</p></span><strong class="plyrcard-offer-price">$35<small>Each</small></strong></a>
            <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-solid fa-globe"></i></span><span><h3 class="plyrcard-offer-title">Domain</h3><p class="plyrcard-offer-copy">Custom domain registration for your athlete site</p></span><strong class="plyrcard-offer-price">$45<small>/Year</small></strong></a>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="book-demo" data-title="Book a Call">
          <div class="plyrcard-booking-wrap">
            <iframe src="https://systems.plyrcard.com/widget/booking/SvuQy1svAyETQ5Q9px9l" scrolling="no" id="SvuQy1svAyETQ5Q9px9l_1778163042192"></iframe>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="dashboard" data-title="Dashboard">
          <div class="plyrcard-mini-panel"><h3 class="plyrcard-mini-title">Dashboard</h3><p class="plyrcard-mini-copy">Quick dashboard tools can live here without leaving the page.</p><button type="button" class="plyrcard-submit-btn"><i class="fa-solid fa-chart-line"></i> Add dashboard content</button></div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="profile" data-title="Profile">
          <div class="plyrcard-form-card plyrcard-form-stack">
            <p class="plyrcard-mini-copy">Profile controls placeholder. Add editable player profile fields here.</p>
            <label class="plyrcard-input-label">Display Name<span class="plyrcard-input-wrap"><i class="fa-solid fa-user"></i><input class="plyrcard-drawer-input" type="text" value="{{ $plyrFirstName }}" placeholder="Player name"></span></label>
            <button type="button" class="plyrcard-submit-btn"><i class="fa-solid fa-check"></i> Save Profile</button>
          </div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="edit-website" data-title="Edit Website">
          <div class="plyrcard-mini-panel"><h3 class="plyrcard-mini-title">Edit Website</h3><p class="plyrcard-mini-copy">Website editing tools can be embedded here so players stay on their card.</p><button type="button" class="plyrcard-submit-btn"><i class="fa-solid fa-pen-to-square"></i> Add editor shortcut</button></div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="upgrade" data-title="Upgrade">
          <div class="plyrcard-mini-panel"><h3 class="plyrcard-mini-title">Upgrade</h3><p class="plyrcard-mini-copy">Current plan: {{ $plyrPlanName }}. Add your plan upgrade cards or checkout embed here.</p><button type="button" class="plyrcard-submit-btn"><i class="fa-solid fa-arrow-trend-up"></i> Explore upgrades</button></div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="schedule" data-title="My Schedule">
          <div class="plyrcard-mini-panel"><h3 class="plyrcard-mini-title">My Schedule</h3><p class="plyrcard-mini-copy">Add schedule content, calendars, games, or booking tools here.</p><button type="button" class="plyrcard-submit-btn"><i class="fa-solid fa-calendar-days"></i> Add schedule content</button></div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="billing" data-title="Billing">
          <div class="plyrcard-mini-panel"><h3 class="plyrcard-mini-title">Billing</h3><p class="plyrcard-mini-copy">Add your billing portal, invoices, or plan management embed here.</p><button type="button" class="plyrcard-submit-btn"><i class="fa-solid fa-credit-card"></i> Add billing portal</button></div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="settings" data-title="Settings">
          <div class="plyrcard-mini-panel"><h3 class="plyrcard-mini-title">Settings</h3><p class="plyrcard-mini-copy">Add account preferences and notification controls here.</p><button type="button" class="plyrcard-submit-btn"><i class="fa-solid fa-gear"></i> Add settings content</button></div>
        </div>
      @else
        <div class="plyrcard-drawer-view is-active" data-plyrcard-view="main">
          <div class="plyrcard-nav-group"><strong class="plyrcard-nav-group-title">Contact</strong><div class="plyrcard-drawer-grid">
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="email-us"><i class="plyrcard-menu-icon fa-solid fa-envelope"></i><span>Email Us</span></button>
            <a class="plyrcard-drawer-card" href="sms:{{ $plyrPhoneHref }}"><i class="plyrcard-menu-icon fa-solid fa-comment-dots"></i><span>Text us</span></a>
            <a class="plyrcard-drawer-card" href="tel:{{ $plyrPhoneHref }}"><i class="plyrcard-menu-icon fa-solid fa-phone"></i><span>Call us</span></a>
            <a class="plyrcard-drawer-card" href="{{ $plyrFacebookUrl }}" target="_blank" rel="noopener"><i class="plyrcard-menu-icon fa-brands fa-facebook-messenger"></i><span>Chat Us</span></a>
          </div></div>
          <div class="plyrcard-nav-group"><strong class="plyrcard-nav-group-title">Start</strong><div class="plyrcard-drawer-grid">
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="share-site"><i class="plyrcard-menu-icon fa-solid fa-share-nodes"></i><span>Share</span></button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="book-demo"><i class="plyrcard-menu-icon fa-solid fa-calendar-check"></i><span>Book Demo</span></button>
            <a class="plyrcard-drawer-card" href="/pricing"><i class="plyrcard-menu-icon fa-solid fa-user-plus"></i><span>Register Now</span></a>
            <button type="button" class="plyrcard-drawer-card is-accent" data-plyrcard-section="login"><i class="plyrcard-menu-icon fa-solid fa-right-to-bracket"></i><span>Login</span></button>
          </div></div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="email-us" data-title="Email Us"><div class="plyrcard-form-card"><a class="plyrcard-submit-btn" href="mailto:{{ $plyrSupportEmail }}"><i class="fa-solid fa-envelope"></i>{{ $plyrSupportEmail }}</a></div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="share-site" data-title="Share"><div class="plyrcard-form-card plyrcard-form-stack"><label class="plyrcard-input-label">PLYRCard URL</label><div class="plyrcard-copy-line"><input class="plyrcard-drawer-input" type="text" value="{{ $plyrMainShareUrl }}" readonly><button type="button" class="plyrcard-copy-btn" data-plyrcard-copy="{{ $plyrMainShareUrl }}">Copy</button></div></div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="book-demo" data-title="Book Demo"><div class="plyrcard-booking-wrap"><iframe src="https://systems.plyrcard.com/widget/booking/SvuQy1svAyETQ5Q9px9l" scrolling="no" id="SvuQy1svAyETQ5Q9px9l_1778163042192"></iframe></div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="login" data-title="Login">
          <form class="plyrcard-form-card plyrcard-form-stack" method="POST" action="{{ url('/admin/login') }}">
            @csrf
            <label class="plyrcard-input-label">Email<span class="plyrcard-input-wrap"><i class="fa-solid fa-envelope"></i><input class="plyrcard-drawer-input" type="email" name="email" placeholder="you@example.com" required></span></label>
            <label class="plyrcard-input-label">Password<span class="plyrcard-input-wrap"><i class="fa-solid fa-lock"></i><input class="plyrcard-drawer-input" type="password" name="password" placeholder="Password" required></span></label>
            <label class="plyrcard-clean-row" style="color:#111;font-size:13px;font-weight:800;"><span><input type="checkbox" name="remember" value="1"> Remember me</span><button type="button" class="plyrcard-text-link" data-plyrcard-section="forgot-password">Forgot Password?</button></label>
            <button class="plyrcard-submit-btn" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
            <div class="plyrcard-clean-row"><a class="plyrcard-secondary-btn" href="/pricing">Register</a><button type="button" class="plyrcard-secondary-btn" data-plyrcard-section="book-demo">Book Demo</button></div>
          </form>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="forgot-password" data-title="Reset Password">
          <form class="plyrcard-form-card plyrcard-form-stack" method="POST" action="{{ url('/admin/password-reset/request') }}">
            @csrf
            <p class="plyrcard-mini-copy">Enter your email and we’ll send password reset instructions.</p>
            <label class="plyrcard-input-label">Email<span class="plyrcard-input-wrap"><i class="fa-solid fa-envelope"></i><input class="plyrcard-drawer-input" type="email" name="email" placeholder="you@example.com" required></span></label>
            <button class="plyrcard-submit-btn" type="submit"><i class="fa-solid fa-paper-plane"></i> Send Reset Link</button>
          </form>
        </div>
      @endauth
    </div>
  </section>

  <button type="button" class="plyrcard-drawer-tab" data-plyrcard-toggle-drawer aria-expanded="false">
    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
    <span>{{ $plyrTabLabel }}</span>
  </button>
</div>

@once
    <script src="https://systems.plyrcard.com/js/form_embed.js" type="text/javascript"></script>
@endonce

<script>
  (function () {
    const drawer = document.getElementById('plyrcard-action-drawer');
    if (!drawer) return;

    const toggleButtons = document.querySelectorAll('[data-plyrcard-toggle-drawer], [data-plyrcard-open-drawer]');
    const closeButtons = drawer.querySelectorAll('[data-plyrcard-close-drawer]');
    const mainTitle = drawer.querySelector('[data-plyrcard-main-title]');
    const subTitle = drawer.querySelector('[data-plyrcard-sub-title]');
    const sectionTitle = drawer.querySelector('[data-plyrcard-section-title]');
    const backButton = drawer.querySelector('[data-plyrcard-back]');
    const tabButton = drawer.querySelector('[data-plyrcard-toggle-drawer]');

    function resetDrawerScroll() {
      const body = drawer.querySelector('.plyrcard-drawer-body');
      if (body) body.scrollTop = 0;
    }

    function ensureMainView() {
      const active = drawer.querySelector('.plyrcard-drawer-view.is-active');
      if (!active || !active.querySelector('.plyrcard-drawer-card, .plyrcard-form-card, .plyrcard-mini-panel, .plyrcard-offer-list, .plyrcard-booking-wrap')) {
        showView('main');
      }
    }

    function setOpen(isOpen) {
      if (isOpen) ensureMainView();
      drawer.classList.toggle('is-open', isOpen);
      drawer.dataset.state = isOpen ? 'open' : 'closed';
      if (tabButton) tabButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      document.documentElement.classList.toggle('plyrcard-drawer-open', isOpen);
      if (isOpen) window.requestAnimationFrame(resetDrawerScroll);
    }

    function showView(name) {
      const view = drawer.querySelector('[data-plyrcard-view="' + name + '"]');
      if (!view) return;
      const panel = drawer.querySelector('.plyrcard-drawer-panel');
      if (panel) {
        panel.classList.remove('is-switching');
        void panel.offsetWidth;
        panel.classList.add('is-switching');
        window.setTimeout(() => panel.classList.remove('is-switching'), 280);
      }
      drawer.querySelectorAll('[data-plyrcard-view]').forEach(item => item.classList.toggle('is-active', item === view));
      resetDrawerScroll();
      const isMain = name === 'main';
      if (mainTitle) mainTitle.style.setProperty('display', isMain ? 'flex' : 'none', 'important');
      if (subTitle) subTitle.style.setProperty('display', isMain ? 'none' : 'flex', 'important');
      if (sectionTitle) sectionTitle.textContent = view.dataset.title || '';
    }

    toggleButtons.forEach(button => button.addEventListener('click', () => setOpen(!drawer.classList.contains('is-open'))));
    closeButtons.forEach(button => button.addEventListener('click', () => { setOpen(false); showView('main'); }));
    drawer.querySelectorAll('[data-plyrcard-section]').forEach(button => button.addEventListener('click', () => { showView(button.dataset.plyrcardSection); setOpen(true); }));
    if (backButton) backButton.addEventListener('click', () => showView('main'));

    showView('main');

    drawer.querySelectorAll('[data-plyrcard-logout-form]').forEach(form => {
      form.addEventListener('submit', async event => {
        event.preventDefault();

        const formData = new FormData(form);
        const token = formData.get('_token') || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        try {
          await fetch(form.action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
              'X-CSRF-TOKEN': token,
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'text/html, application/xhtml+xml',
            },
          });
        } catch (error) {
          // If the logout request fails because of a browser/network quirk,
          // still send the visitor back to the public website instead of /admin/login.
        }

        window.location.assign('/');
      });
    });

    drawer.querySelectorAll('[data-plyrcard-copy]').forEach(button => {
      button.addEventListener('click', async () => {
        const value = button.getAttribute('data-plyrcard-copy') || '';
        try {
          await navigator.clipboard.writeText(value);
          const original = button.textContent;
          button.textContent = 'Copied';
          setTimeout(() => button.textContent = original, 1400);
        } catch (error) {
          window.prompt('Copy this URL:', value);
        }
      });
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        setOpen(false);
        showView('main');
      }
    });
  })();
</script>