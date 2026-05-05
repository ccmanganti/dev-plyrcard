<style>
    /* PLYRCARD shared navigation - single source of truth */
    :root {
      --plyrcard-nav-height: calc(var(--header-h, 76px) + var(--safe-top, 0px));
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

    #site-header.plyrcard-site-header .logo-text {
      display: none !important;
      font-family: var(--font-display, sans-serif) !important;
      font-size: 30px !important;
      font-weight: 800 !important;
      letter-spacing: 0.03em !important;
      line-height: 1 !important;
      color: var(--white, #fff) !important;
      text-transform: uppercase !important;
    }

    #site-header.plyrcard-site-header .logo-text span {
      color: var(--accent, #ff5c35) !important;
    }

    #site-header.plyrcard-site-header .desktop-nav {
      margin-left: auto !important;
      display: none !important;
      align-items: center !important;
      justify-content: flex-end !important;
      gap: clamp(30px, 3vw, 48px) !important;
      font-family: var(--font-display, sans-serif) !important;
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
      color: var(--white, #fff) !important;
    }

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

    #site-header.plyrcard-site-header .desktop-nav .desktop-nav-cta:hover,
    #site-header.plyrcard-site-header .desktop-nav .desktop-nav-cta.active {
      color: var(--white, #fff) !important;
      background: var(--accent, #ff5c35) !important;
      transform: translateY(-1px) !important;
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
      border-radius: 0 !important;
      box-shadow: none !important;
      cursor: pointer !important;
    }

    #site-header.plyrcard-site-header .menu-btn span {
      display: block !important;
      width: 24px !important;
      height: 2px !important;
      background: var(--white, #fff) !important;
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
      bottom: auto !important;
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
      justify-content: flex-start !important;
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

    #mobile-nav.plyrcard-mobile-nav a {
      font-family: var(--font-display, sans-serif) !important;
      font-size: 18px !important;
      font-weight: 800 !important;
      line-height: 1 !important;
      letter-spacing: 0.04em !important;
      text-transform: uppercase !important;
      color: rgba(255,255,255,0.76) !important;
      text-decoration: none !important;
      padding: 7px 0 !important;
    }

    #mobile-nav.plyrcard-mobile-nav a:hover,
    #mobile-nav.plyrcard-mobile-nav a.active {
      color: var(--white, #fff) !important;
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
      #site-header.plyrcard-site-header {
        padding-left: 24px !important;
        padding-right: 24px !important;
      }

      #site-header.plyrcard-site-header .desktop-nav {
        display: flex !important;
      }

      #site-header.plyrcard-site-header .menu-btn {
        display: none !important;
      }

      #mobile-nav.plyrcard-mobile-nav {
        display: none !important;
      }
    }

    @media (max-width: 767px) {
      #site-header.plyrcard-site-header {
        min-height: calc(64px + var(--safe-top, 0px)) !important;
        padding-left: 20px !important;
        padding-right: 20px !important;
      }

      #site-header.plyrcard-site-header .logo-wrap {
        height: 46px !important;
      }

      #site-header.plyrcard-site-header .logo-text {
        font-size: 27px !important;
      }
    }
</style>

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
    <a href="/admin">Login</a>
    <a data-nav href="/registration?utm_plan=free" class="desktop-nav-cta{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
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
  <a href="/admin" class="nav-link">Login</a>
  <a data-nav href="/registration?utm_plan=free" class="nav-cta-pill{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
</nav>