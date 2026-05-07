@once
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
@endonce

<style>
    /* PLYRCARD navigation: final compact design lock */
    :root {
      --plyrcard-nav-height: calc(var(--header-h, 76px) + var(--safe-top, 0px));
      --plyrcard-accent: #ff2731;
      --plyrcard-accent-2: #ff424b;
      --plyrcard-dark: rgba(8,10,12,0.96);
      --plyrcard-font: 'Antonio', 'Arial Narrow', Impact, sans-serif;
    }

    .plyrcard-site-header,
    .plyrcard-site-header *,
    .plyrcard-mobile-nav,
    .plyrcard-mobile-nav *,
    .plyrcard-action-drawer,
    .plyrcard-action-drawer * {
      box-sizing: border-box !important;
      font-family: var(--plyrcard-font) !important;
    }

    .plyrcard-action-drawer .fa-solid,
    .plyrcard-action-drawer .fas,
    .plyrcard-mobile-nav .fa-solid,
    .plyrcard-site-header .fa-solid {
      font-family: "Font Awesome 6 Free" !important;
      font-weight: 900 !important;
    }

    .plyrcard-action-drawer .fa-regular,
    .plyrcard-action-drawer .far,
    .plyrcard-mobile-nav .fa-regular,
    .plyrcard-site-header .fa-regular {
      font-family: "Font Awesome 6 Free" !important;
      font-weight: 400 !important;
    }

    .plyrcard-action-drawer .fa-brands,
    .plyrcard-action-drawer .fab,
    .plyrcard-mobile-nav .fa-brands,
    .plyrcard-site-header .fa-brands {
      font-family: "Font Awesome 6 Brands" !important;
      font-weight: 400 !important;
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
      transition: background .25s ease, border-color .25s ease, backdrop-filter .25s ease !important;
    }

    #site-header.plyrcard-site-header.scrolled {
      background: rgba(13,13,13,.92) !important;
      border-bottom-color: rgba(255,255,255,.07) !important;
      backdrop-filter: blur(16px) !important;
      -webkit-backdrop-filter: blur(16px) !important;
    }

    #site-header.plyrcard-site-header .logo-wrap {
      display: flex !important;
      align-items: center !important;
      height: 50px !important;
      text-decoration: none !important;
    }

    #site-header.plyrcard-site-header .logo-wrap img {
      display: block !important;
      height: 32px !important;
      width: auto !important;
      object-fit: contain !important;
    }

    #site-header.plyrcard-site-header .desktop-nav {
      margin-left: auto !important;
      display: none !important;
      align-items: center !important;
      justify-content: flex-end !important;
      gap: clamp(28px, 3vw, 46px) !important;
      font-size: clamp(20px, 1.4vw, 28px) !important;
      line-height: 1 !important;
      font-weight: 800 !important;
      letter-spacing: .08em !important;
      text-transform: uppercase !important;
      white-space: nowrap !important;
    }

    #site-header.plyrcard-site-header .desktop-nav a {
      color: rgba(255,255,255,.74) !important;
      text-decoration: none !important;
      padding: 8px 0 !important;
      background: transparent !important;
      border: 0 !important;
      font: inherit !important;
      letter-spacing: inherit !important;
      text-transform: inherit !important;
    }

    #site-header.plyrcard-site-header .desktop-nav a:hover,
    #site-header.plyrcard-site-header .desktop-nav a.active { color: #fff !important; }

    #site-header.plyrcard-site-header .desktop-nav .desktop-nav-cta {
      min-height: 54px !important;
      padding: 16px 26px !important;
      border-radius: 999px !important;
      background: #ff5c35 !important;
      color: #fff !important;
      box-shadow: 0 14px 34px rgba(255,92,53,.28) !important;
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
      transition: transform .25s ease, opacity .25s ease !important;
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
      display: flex !important;
      flex-direction: column !important;
      gap: 14px !important;
      padding: 20px 24px calc(24px + var(--safe-bottom, 0px)) !important;
      background: rgba(13,13,13,.98) !important;
      border-top: 1px solid rgba(255,255,255,.08) !important;
      border-bottom: 1px solid rgba(255,255,255,.08) !important;
      backdrop-filter: blur(18px) !important;
      -webkit-backdrop-filter: blur(18px) !important;
      opacity: 0 !important;
      transform: translateY(-120%) !important;
      pointer-events: none !important;
      transition: transform .3s ease, opacity .2s ease !important;
    }

    #mobile-nav.plyrcard-mobile-nav.open {
      opacity: 1 !important;
      transform: translateY(0) !important;
      pointer-events: auto !important;
    }

    #mobile-nav.plyrcard-mobile-nav a,
    #mobile-nav.plyrcard-mobile-nav button {
      color: rgba(255,255,255,.78) !important;
      text-decoration: none !important;
      border: 0 !important;
      background: transparent !important;
      padding: 6px 0 !important;
      text-align: left !important;
      font-size: 18px !important;
      font-weight: 800 !important;
      line-height: 1 !important;
      letter-spacing: .04em !important;
      text-transform: uppercase !important;
      cursor: pointer !important;
    }

    #mobile-nav.plyrcard-mobile-nav .nav-cta-pill {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      min-height: 48px !important;
      border-radius: 999px !important;
      background: #ff5c35 !important;
      color: #fff !important;
      padding: 12px 18px !important;
    }

    .plyrcard-action-drawer {
      position: fixed !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      z-index: 10000 !important;
      display: block !important;
      width: 100vw !important;
      pointer-events: none !important;
    }

    .plyrcard-drawer-scrim {
      position: fixed !important;
      inset: 0 !important;
      background: rgba(0,0,0,.34) !important;
      backdrop-filter: blur(4px) !important;
      -webkit-backdrop-filter: blur(4px) !important;
      opacity: 0 !important;
      pointer-events: none !important;
      transition: opacity .22s ease !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-scrim {
      opacity: 1 !important;
      pointer-events: auto !important;
    }

    .plyrcard-drawer-panel {
      position: relative !important;
      width: 100vw !important;
      max-width: none !important;
      margin: 0 !important;
      max-height: min(74vh, 560px) !important;
      overflow: hidden !important;
      border-radius: 18px 18px 0 0 !important;
      background: var(--plyrcard-dark) !important;
      border: 1px solid rgba(255,255,255,.12) !important;
      box-shadow: 0 -20px 54px rgba(0,0,0,.5) !important;
      transform: translateY(100%) !important;
      transition: transform .32s cubic-bezier(.2,.8,.2,1) !important;
      pointer-events: auto !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-panel { transform: translateY(0) !important; }

    .plyrcard-drawer-handle {
      position: absolute !important;
      top: 8px !important;
      left: 50% !important;
      transform: translateX(-50%) !important;
      width: 48px !important;
      height: 5px !important;
      border-radius: 999px !important;
      background: rgba(0,0,0,.22) !important;
      z-index: 2 !important;
    }

    .plyrcard-drawer-head {
      min-height: 58px !important;
      padding: 16px 16px 9px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      gap: 10px !important;
      background: #fff !important;
      color: #070707 !important;
      border-radius: 18px 18px 0 0 !important;
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
      font-size: 24px !important;
      line-height: 1 !important;
      font-weight: 900 !important;
      letter-spacing: .01em !important;
      white-space: nowrap !important;
      text-transform: none !important;
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
      text-decoration: none !important;
      font: inherit !important;
    }

    .plyrcard-drawer-back {
      gap: 5px !important;
      font-size: 18px !important;
      font-weight: 800 !important;
      line-height: 1 !important;
    }

    .plyrcard-drawer-close svg,
    .plyrcard-drawer-back svg,
    .plyrcard-drawer-tab-chevron svg {
      width: 23px !important;
      height: 23px !important;
      stroke-width: 3 !important;
    }

    .plyrcard-drawer-body {
      width: 100% !important;
      max-width: 430px !important;
      margin: 0 auto !important;
      padding: 14px 15px calc(70px + var(--safe-bottom, 0px)) !important;
      color: #fff !important;
      overflow-y: auto !important;
      max-height: calc(min(74vh, 560px) - 58px) !important;
    }

    .plyrcard-drawer-view { display: none !important; }
    .plyrcard-drawer-view.is-active { display: block !important; }

    .plyrcard-drawer-grid {
      display: grid !important;
      grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
      gap: 8px !important;
    }

    .plyrcard-drawer-card {
      min-width: 0 !important;
      min-height: 78px !important;
      padding: 8px 4px 7px !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 6px !important;
      border-radius: 10px !important;
      background: #fff !important;
      color: #050505 !important;
      border: 1px solid rgba(0,0,0,.08) !important;
      box-shadow: 0 6px 16px rgba(0,0,0,.24), inset 0 0 0 1px rgba(255,255,255,.55) !important;
      text-decoration: none !important;
      text-align: center !important;
      cursor: pointer !important;
      font: inherit !important;
      appearance: none !important;
      -webkit-appearance: none !important;
    }

    .plyrcard-drawer-card:hover { transform: translateY(-1px) !important; }
    .plyrcard-drawer-card.is-accent { background: linear-gradient(135deg, var(--plyrcard-accent), var(--plyrcard-accent-2)) !important; color: #fff !important; }
    .plyrcard-drawer-card.is-disabled { opacity: .55 !important; cursor: not-allowed !important; }

    .plyrcard-menu-icon {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 24px !important;
      height: 24px !important;
      font-size: 21px !important;
      line-height: 1 !important;
      color: currentColor !important;
      text-rendering: auto !important;
      -webkit-font-smoothing: antialiased !important;
    }

    .plyrcard-drawer-card > span {
      display: block !important;
      max-width: 100% !important;
      color: currentColor !important;
      font-size: 11px !important;
      font-weight: 850 !important;
      line-height: 1.05 !important;
      letter-spacing: 0 !important;
      text-transform: none !important;
      word-break: normal !important;
    }

    .plyrcard-drawer-section-divider {
      margin: 15px 0 13px !important;
      height: 1px !important;
      background: rgba(255,255,255,.16) !important;
    }

    .plyrcard-social-row {
      display: flex !important;
      align-items: center !important;
      flex-wrap: wrap !important;
      gap: 14px 22px !important;
      color: #fff !important;
    }

    .plyrcard-social-label {
      font-size: 20px !important;
      font-weight: 850 !important;
      line-height: 1 !important;
    }

    .plyrcard-social-row a {
      color: #fff !important;
      text-decoration: none !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
    }

    .plyrcard-social-row i {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 21px !important;
      height: 21px !important;
      font-size: 21px !important;
      line-height: 1 !important;
      color: #fff !important;
    }

    .plyrcard-drawer-tab {
      position: absolute !important;
      right: 0 !important;
      bottom: 0 !important;
      width: 250px !important;
      min-width: 250px !important;
      height: 54px !important;
      padding: 0 18px 0 46px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 10px !important;
      background: linear-gradient(135deg, var(--plyrcard-accent), var(--plyrcard-accent-2)) !important;
      color: #fff !important;
      border: 0 !important;
      border-radius: 0 !important;
      font-size: 22px !important;
      font-weight: 900 !important;
      line-height: 1 !important;
      letter-spacing: .01em !important;
      text-transform: none !important;
      cursor: pointer !important;
      pointer-events: auto !important;
      clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%) !important;
      box-shadow: 0 -8px 28px rgba(0,0,0,.22) !important;
      z-index: 3 !important;
    }

    .plyrcard-drawer-tab-chevron {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      transform: rotate(0deg) !important;
      transition: transform .25s ease !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-tab-chevron { transform: rotate(180deg) !important; }

    .plyrcard-form-stack { display: grid !important; gap: 10px !important; }
    .plyrcard-input-wrap { position: relative !important; display: block !important; }

    .plyrcard-field-icon {
      position: absolute !important;
      left: 18px !important;
      top: 50% !important;
      transform: translateY(-50%) !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 20px !important;
      height: 20px !important;
      color: #111 !important;
      font-size: 19px !important;
      line-height: 1 !important;
      z-index: 1 !important;
    }

    .plyrcard-input-wrap.textarea .plyrcard-field-icon { top: 18px !important; transform: none !important; }

    .plyrcard-drawer-input,
    .plyrcard-drawer-select,
    .plyrcard-drawer-textarea {
      width: 100% !important;
      min-height: 52px !important;
      border-radius: 10px !important;
      border: 0 !important;
      background: #fff !important;
      color: #111 !important;
      padding: 13px 16px 13px 54px !important;
      font-size: 15px !important;
      font-weight: 700 !important;
      outline: none !important;
    }

    .plyrcard-drawer-textarea { min-height: 92px !important; resize: vertical !important; padding-top: 15px !important; }
    .plyrcard-drawer-input::placeholder,
    .plyrcard-drawer-textarea::placeholder,
    .plyrcard-drawer-select { color: rgba(0,0,0,.44) !important; }

    .plyrcard-submit-btn {
      width: 100% !important;
      min-height: 52px !important;
      margin-top: 12px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 10px !important;
      border: 0 !important;
      border-radius: 10px !important;
      background: linear-gradient(135deg, var(--plyrcard-accent), var(--plyrcard-accent-2)) !important;
      color: #fff !important;
      font-size: 20px !important;
      font-weight: 900 !important;
      cursor: pointer !important;
      text-decoration: none !important;
    }

    .plyrcard-offer-list { display: grid !important; gap: 10px !important; }

    .plyrcard-offer-card {
      display: grid !important;
      grid-template-columns: 48px 1fr auto !important;
      align-items: center !important;
      gap: 10px !important;
      min-height: 88px !important;
      padding: 12px 14px 12px 12px !important;
      border-radius: 10px !important;
      background: #fff !important;
      color: #050505 !important;
      text-decoration: none !important;
      box-shadow: 0 6px 16px rgba(0,0,0,.24) !important;
    }

    .plyrcard-offer-icon {
      width: 44px !important;
      height: 44px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border-radius: 10px !important;
      background: #fff !important;
      box-shadow: 0 4px 12px rgba(0,0,0,.14) !important;
    }

    .plyrcard-offer-icon i { font-size: 22px !important; color: #050505 !important; line-height: 1 !important; }
    .plyrcard-offer-title { margin: 0 0 4px !important; font-size: 18px !important; line-height: 1 !important; font-weight: 900 !important; color: #050505 !important; }
    .plyrcard-offer-copy { margin: 0 !important; color: #303746 !important; font-size: 12px !important; line-height: 1.2 !important; font-weight: 500 !important; }
    .plyrcard-offer-price { text-align: right !important; color: #168bff !important; font-size: 23px !important; font-weight: 950 !important; line-height: .9 !important; white-space: nowrap !important; }
    .plyrcard-offer-price small { display: block !important; margin-top: 6px !important; color: #4d5565 !important; font-size: 10px !important; line-height: 1 !important; letter-spacing: .06em !important; text-transform: uppercase !important; }

    .plyrcard-contact-card {
      display: grid !important;
      grid-template-columns: 42px 1fr !important;
      gap: 12px !important;
      align-items: center !important;
      min-height: 82px !important;
      padding: 14px !important;
      border-radius: 12px !important;
      background: #fff !important;
      color: #050505 !important;
      text-decoration: none !important;
      box-shadow: 0 6px 16px rgba(0,0,0,.24) !important;
    }

    .plyrcard-contact-card > i { font-size: 25px !important; color: #050505 !important; text-align: center !important; }
    .plyrcard-contact-card strong { display: block !important; font-size: 19px !important; line-height: 1 !important; color: #050505 !important; }
    .plyrcard-contact-card span span { display: block !important; margin-top: 5px !important; font-size: 12px !important; line-height: 1.2 !important; color: #49505c !important; }

    .plyrcard-share-grid {
      display: grid !important;
      grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
      gap: 8px !important;
    }

    .plyrcard-share-option {
      min-height: 76px !important;
      padding: 9px 4px !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 6px !important;
      border-radius: 10px !important;
      background: #fff !important;
      color: #050505 !important;
      text-decoration: none !important;
      box-shadow: 0 6px 16px rgba(0,0,0,.24) !important;
    }

    .plyrcard-share-option i { font-size: 22px !important; line-height: 1 !important; color: #050505 !important; }
    .plyrcard-share-option span { font-size: 11px !important; font-weight: 850 !important; line-height: 1 !important; color: #050505 !important; }
    .plyrcard-share-note { margin: 12px 0 0 !important; color: rgba(255,255,255,.72) !important; font-size: 12px !important; line-height: 1.35 !important; }

    @media (min-width: 960px) {
      #site-header.plyrcard-site-header .desktop-nav { display: flex !important; }
      #site-header.plyrcard-site-header .menu-btn { display: none !important; }
      #mobile-nav.plyrcard-mobile-nav { display: none !important; }
      .plyrcard-action-drawer:not(.is-pullup-only) { display: none !important; }
    }

    @media (max-width: 520px) {
      #site-header.plyrcard-site-header { min-height: calc(64px + var(--safe-top, 0px)) !important; padding-left: 20px !important; padding-right: 20px !important; }
      #site-header.plyrcard-site-header .logo-wrap img { height: 30px !important; }
      .plyrcard-drawer-body { max-width: none !important; padding-left: 14px !important; padding-right: 14px !important; }
      .plyrcard-drawer-grid { gap: 7px !important; }
      .plyrcard-drawer-card { min-height: 74px !important; padding: 7px 3px 6px !important; }
      .plyrcard-menu-icon { width: 22px !important; height: 22px !important; font-size: 20px !important; }
      .plyrcard-drawer-card > span { font-size: 10.5px !important; }
      .plyrcard-drawer-tab { width: min(250px, 68vw) !important; min-width: 226px !important; height: 54px !important; padding-left: 46px !important; font-size: 21px !important; }
    }
</style>

@php
  $plyrUser = auth()->user();
  $plyrLoggedIn = auth()->check();
  $plyrFirstName = $plyrLoggedIn ? explode(' ', trim($plyrUser->name ?? 'Clark'))[0] : null;

  $plyrSupportEmail = 'support@plyrcard.com';
  $plyrSupportPhoneDisplay = '(555) 555-5555';
  $plyrSupportPhoneHref = '5555555555';
  $plyrShareUrl = 'https://plyrcard.com';
  $plyrFacebookUrl = 'https://www.facebook.com/plyrcard';
  $plyrInstagramUrl = 'https://www.instagram.com/plyrcard';
  $plyrXUrl = 'https://x.com/plyrcard';
  $plyrYouTubeUrl = 'https://www.youtube.com/@plyrcard';

  $plyrHasMyJourney = false;
  if ($plyrLoggedIn && $plyrUser) {
      if (method_exists($plyrUser, 'hasRole')) {
          $plyrHasMyJourney = $plyrUser->hasRole('My Journey');
      } elseif (method_exists($plyrUser, 'roles')) {
          $plyrHasMyJourney = $plyrUser->roles()->where('name', 'My Journey')->exists();
      }
  }

  $plyrCurrentWebsite = $website ?? null;
  $plyrViewingPlayerWebsite = $plyrCurrentWebsite instanceof \App\Models\Website;
  $plyrOnAdmin = request()->is('admin') || request()->is('admin/*');
  $plyrOwnsCurrentWebsite = $plyrViewingPlayerWebsite && $plyrLoggedIn && $plyrUser && ((int) $plyrCurrentWebsite->user_id === (int) $plyrUser->id);
  $plyrShouldRenderNavigation = ! $plyrViewingPlayerWebsite || ! $plyrLoggedIn || $plyrOwnsCurrentWebsite;
  $plyrPullUpOnly = $plyrOnAdmin || $plyrViewingPlayerWebsite;

  $plyrWebsite = null;
  $plyrWebsiteUrl = null;
  if ($plyrLoggedIn && $plyrUser && class_exists(\App\Models\Website::class)) {
      $plyrWebsite = \App\Models\Website::query()
          ->where('user_id', $plyrUser->id)
          ->where('is_active', true)
          ->where('is_published', true)
          ->latest('updated_at')
          ->first();

      if ($plyrWebsite) {
          if (! empty($plyrWebsite->domain)) {
              $plyrWebsiteUrl = \Illuminate\Support\Str::startsWith($plyrWebsite->domain, ['http://', 'https://'])
                  ? $plyrWebsite->domain
                  : 'https://' . $plyrWebsite->domain;
          } else {
              $plyrWebsiteSlug = $plyrWebsite->slug ?: \Illuminate\Support\Str::slug($plyrWebsite->name);
              $plyrWebsiteUrl = url('/' . $plyrWebsiteSlug);
          }
      }
  }
@endphp

@if($plyrShouldRenderNavigation)
@if(! $plyrPullUpOnly)
<header id="site-header" class="plyrcard-site-header over-hero">
  <a data-nav href="/" class="logo-wrap" aria-label="PLYRCARD Home">
    <img src="../images/plyr-logo.png" alt="PLYRCARD Logo">
  </a>

  <nav class="desktop-nav" aria-label="Primary navigation">
    <a data-nav href="/" class="{{ ($activePage ?? '') === 'home' ? ' active' : '' }}">Home</a>
    <a data-nav href="/about" class="{{ ($activePage ?? '') === 'about' ? ' active' : '' }}">About</a>
    <a data-nav href="/pricing" class="{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Pricing</a>
    <a data-nav href="/podcast" class="{{ ($activePage ?? '') === 'podcast' ? ' active' : '' }}">Podcast</a>
    <a data-nav href="/book-demo" class="{{ ($activePage ?? '') === 'book-demo' ? ' active' : '' }}">Book a Demo</a>
    @auth
      <a href="/admin">Dashboard</a>
      <a href="/logout">Logout</a>
    @else
      <a href="/admin">Login</a>
      <a data-nav href="/pricing" class="desktop-nav-cta{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Start Free</a>
    @endauth
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
  @auth
    <button type="button" class="nav-link" data-plyrcard-open-drawer>Locker Room</button>
    <a href="/admin" class="nav-link">Dashboard</a>
    <a href="/logout" class="nav-link">Logout</a>
  @else
    <button type="button" class="nav-link" data-plyrcard-open-drawer>Get Started</button>
    <a href="/admin" class="nav-link">Login</a>
    <a data-nav href="/pricing" class="nav-cta-pill{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Start Free</a>
  @endauth
</nav>
@endif

<div id="plyrcard-action-drawer" class="plyrcard-action-drawer{{ $plyrPullUpOnly ? ' is-pullup-only' : '' }}" data-state="closed" data-logged-in="{{ $plyrLoggedIn ? 'true' : 'false' }}">
  <div class="plyrcard-drawer-scrim" data-plyrcard-close-drawer></div>

  <section class="plyrcard-drawer-panel" aria-label="{{ $plyrLoggedIn ? 'Locker Room menu' : 'Get Started menu' }}">
    <div class="plyrcard-drawer-handle" aria-hidden="true"></div>

    <div class="plyrcard-drawer-head">
      <div class="plyrcard-drawer-title-row" data-plyrcard-main-title>
        @auth
          <h2 class="plyrcard-drawer-title">Locker Room</h2>
        @else
          <h2 class="plyrcard-drawer-title">GET STARTED</h2>
        @endauth
      </div>

      <div class="plyrcard-drawer-title-row" data-plyrcard-sub-title style="display:none !important;">
        <button type="button" class="plyrcard-drawer-back" data-plyrcard-back>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5 8 12l7 7" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Back</span>
        </button>
        <h2 class="plyrcard-drawer-title" data-plyrcard-section-title></h2>
      </div>

      <button type="button" class="plyrcard-drawer-icon-btn plyrcard-drawer-close" aria-label="Close menu" data-plyrcard-close-drawer>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-linecap="round"/></svg>
      </button>
    </div>

    <div class="plyrcard-drawer-body">
      @auth
        <div class="plyrcard-drawer-view is-active" data-plyrcard-view="main">
          <div class="plyrcard-drawer-grid">
            @if($plyrHasMyJourney)
              <span class="plyrcard-drawer-card is-disabled" aria-disabled="true">
                <i class="plyrcard-menu-icon fa-regular fa-envelope-open" aria-hidden="true"></i><span>Upgrade</span>
              </span>
            @else
              <a class="plyrcard-drawer-card" href="/admin/my-journey">
                <i class="plyrcard-menu-icon fa-regular fa-envelope-open" aria-hidden="true"></i><span>Upgrade</span>
              </a>
            @endif
            <a class="plyrcard-drawer-card" href="#">
              <i class="plyrcard-menu-icon fa-regular fa-message" aria-hidden="true"></i><span>Support</span>
            </a>
            <a class="plyrcard-drawer-card" href="/book-demo">
              <i class="plyrcard-menu-icon fa-solid fa-phone-volume" aria-hidden="true"></i><span>Book a Call</span>
            </a>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="refer-friend">
              <i class="plyrcard-menu-icon fa-regular fa-comments" aria-hidden="true"></i><span>Refer a Friend</span>
            </button>
            <a class="plyrcard-drawer-card" href="/podcast">
              <i class="plyrcard-menu-icon fa-solid fa-share" aria-hidden="true"></i><span>PLYRCard Show</span>
            </a>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="a-la-carte">
              <i class="plyrcard-menu-icon fa-regular fa-calendar-days" aria-hidden="true"></i><span>A La Carte</span>
            </button>
            @if($plyrWebsiteUrl)
              <a class="plyrcard-drawer-card" href="{{ $plyrWebsiteUrl }}" target="_blank" rel="noopener">
                <i class="plyrcard-menu-icon fa-regular fa-address-card" aria-hidden="true"></i><span>Go to my Website</span>
              </a>
            @else
              <span class="plyrcard-drawer-card is-disabled" aria-disabled="true">
                <i class="plyrcard-menu-icon fa-regular fa-address-card" aria-hidden="true"></i><span>Go to my Website</span>
              </span>
            @endif
            <a class="plyrcard-drawer-card is-accent" href="/admin">
              <i class="plyrcard-menu-icon fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i><span>My Journey</span>
            </a>
          </div>
          @includeWhen(View::exists('partials.navigation-socials'), 'partials.navigation-socials')
          <div class="plyrcard-drawer-section-divider"></div>
          <div class="plyrcard-social-row">
            <span class="plyrcard-social-label">Follow Us</span>
            <a href="{{ $plyrInstagramUrl }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
            <a href="{{ $plyrFacebookUrl }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
            <a href="{{ $plyrXUrl }}" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i></a>
            <a href="{{ $plyrYouTubeUrl }}" target="_blank" rel="noopener" aria-label="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="support" data-title="Support">
          <form class="plyrcard-form-stack" action="#" method="POST">
            @csrf
            <select class="plyrcard-drawer-select" name="concern">
              <option value="">Select your concern</option>
              <option>Billing</option>
              <option>Website update</option>
              <option>Graphics</option>
              <option>Account help</option>
              <option>Other</option>
            </select>
            <textarea class="plyrcard-drawer-textarea" name="details" placeholder="Give us some more details..."></textarea>
            <button class="plyrcard-submit-btn" type="submit">Submit</button>
          </form>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="refer-friend" data-title="Refer a Friend">
          <form class="plyrcard-form-stack" action="#" method="POST">
            @csrf
            <label class="plyrcard-input-wrap"><i class="plyrcard-field-icon fa-regular fa-user" aria-hidden="true"></i><input class="plyrcard-drawer-input" name="friend_name" placeholder="Friend’s name"></label>
            <label class="plyrcard-input-wrap"><i class="plyrcard-field-icon fa-regular fa-envelope" aria-hidden="true"></i><input class="plyrcard-drawer-input" type="email" name="friend_email" placeholder="Friend’s email"></label>
            <label class="plyrcard-input-wrap"><i class="plyrcard-field-icon fa-solid fa-phone" aria-hidden="true"></i><input class="plyrcard-drawer-input" name="friend_phone" placeholder="Friend’s phone"></label>
            <label class="plyrcard-input-wrap textarea"><i class="plyrcard-field-icon fa-regular fa-message" aria-hidden="true"></i><textarea class="plyrcard-drawer-textarea" name="message" placeholder="Add a short message..."></textarea></label>
            <button class="plyrcard-submit-btn" type="submit"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send Invite</button>
          </form>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="a-la-carte" data-title="A La Carte">
          <div class="plyrcard-offer-list">
            <a href="#" class="plyrcard-offer-card">
              <span class="plyrcard-offer-icon"><i class="fa-regular fa-window-maximize" aria-hidden="true"></i></span>
              <span><h3 class="plyrcard-offer-title">Upgraded Site Design</h3><p class="plyrcard-offer-copy">A full redesign of your athlete website</p></span>
              <strong class="plyrcard-offer-price">$150<small>One-time</small></strong>
            </a>
            <a href="#" class="plyrcard-offer-card">
              <span class="plyrcard-offer-icon"><i class="fa-regular fa-images" aria-hidden="true"></i></span>
              <span><h3 class="plyrcard-offer-title">Starting Graphics Bundle</h3><p class="plyrcard-offer-copy">Starting graphic • Showcase graphic • Thank You graphic</p></span>
              <strong class="plyrcard-offer-price">$70<small>Bundle</small></strong>
            </a>
            <a href="#" class="plyrcard-offer-card">
              <span class="plyrcard-offer-icon"><i class="fa-solid fa-pen-nib" aria-hidden="true"></i></span>
              <span><h3 class="plyrcard-offer-title">Individual Graphic</h3><p class="plyrcard-offer-copy">Single custom athlete graphic</p></span>
              <strong class="plyrcard-offer-price">$35<small>Each</small></strong>
            </a>
            <a href="#" class="plyrcard-offer-card">
              <span class="plyrcard-offer-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
              <span><h3 class="plyrcard-offer-title">Domain</h3><p class="plyrcard-offer-copy">Custom domain registration for your athlete site</p></span>
              <strong class="plyrcard-offer-price">$45<small>/Year</small></strong>
            </a>
          </div>
        </div>
      @else
        <div class="plyrcard-drawer-view is-active" data-plyrcard-view="main">
          <div class="plyrcard-drawer-grid">
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="email-us">
              <i class="plyrcard-menu-icon fa-regular fa-envelope" aria-hidden="true"></i><span>Email Us</span>
            </button>
            <a class="plyrcard-drawer-card" href="sms:{{ $plyrSupportPhoneHref }}">
              <i class="plyrcard-menu-icon fa-regular fa-comment-dots" aria-hidden="true"></i><span>Text us</span>
            </a>
            <a class="plyrcard-drawer-card" href="tel:{{ $plyrSupportPhoneHref }}">
              <i class="plyrcard-menu-icon fa-solid fa-phone-volume" aria-hidden="true"></i><span>Call us</span>
            </a>
            <a class="plyrcard-drawer-card" href="{{ $plyrFacebookUrl }}" target="_blank" rel="noopener">
              <i class="plyrcard-menu-icon fa-regular fa-comments" aria-hidden="true"></i><span>Chat Us</span>
            </a>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="share">
              <i class="plyrcard-menu-icon fa-solid fa-share-nodes" aria-hidden="true"></i><span>Share</span>
            </button>
            <a class="plyrcard-drawer-card" href="/book-demo">
              <i class="plyrcard-menu-icon fa-regular fa-calendar-days" aria-hidden="true"></i><span>Book a Demo</span>
            </a>
            <a class="plyrcard-drawer-card" href="/pricing">
              <i class="plyrcard-menu-icon fa-solid fa-user-plus" aria-hidden="true"></i><span>Register Now</span>
            </a>
            <a class="plyrcard-drawer-card is-accent" href="/admin">
              <i class="plyrcard-menu-icon fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i><span>Login</span>
            </a>
          </div>
          <div class="plyrcard-drawer-section-divider"></div>
          <div class="plyrcard-social-row">
            <span class="plyrcard-social-label">Follow Us</span>
            <a href="{{ $plyrInstagramUrl }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
            <a href="{{ $plyrFacebookUrl }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
            <a href="{{ $plyrXUrl }}" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i></a>
            <a href="{{ $plyrYouTubeUrl }}" target="_blank" rel="noopener" aria-label="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="email-us" data-title="Email Us">
          <a class="plyrcard-contact-card" href="mailto:{{ $plyrSupportEmail }}">
            <i class="fa-regular fa-envelope" aria-hidden="true"></i>
            <span><strong>{{ $plyrSupportEmail }}</strong><span>Tap to open Gmail or your email app.</span></span>
          </a>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="share" data-title="Share">
          <div class="plyrcard-share-grid">
            <a class="plyrcard-share-option" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($plyrShareUrl) }}"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i><span>Facebook</span></a>
            <a class="plyrcard-share-option" target="_blank" rel="noopener" href="https://www.instagram.com/plyrcard/"><i class="fa-brands fa-instagram" aria-hidden="true"></i><span>Instagram</span></a>
            <a class="plyrcard-share-option" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url={{ urlencode($plyrShareUrl) }}&text={{ urlencode('Check out PLYRCARD') }}"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i><span>X</span></a>
            <a class="plyrcard-share-option" target="_blank" rel="noopener" href="https://www.youtube.com/@plyrcard"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
          </div>
          <p class="plyrcard-share-note">Facebook and X open pre-filled share dialogs. Instagram and YouTube do not support website pre-fill sharing, so they open the PLYRCARD profile/link destination.</p>
        </div>
      @endauth
    </div>

  </section>

  <button type="button" class="plyrcard-drawer-tab" data-plyrcard-toggle-drawer aria-expanded="false">
    <span class="plyrcard-drawer-tab-chevron" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
    <span>{{ $plyrLoggedIn ? 'Locker Room' : 'GET STARTED' }}</span>
  </button>
</div>

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

    function setOpen(isOpen) {
      drawer.classList.toggle('is-open', isOpen);
      drawer.dataset.state = isOpen ? 'open' : 'closed';
      if (tabButton) tabButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      document.documentElement.classList.toggle('plyrcard-drawer-open', isOpen);
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
    }

    toggleButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        setOpen(!drawer.classList.contains('is-open'));
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

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        setOpen(false);
        showView('main');
      }
    });
  })();
</script>
@endif