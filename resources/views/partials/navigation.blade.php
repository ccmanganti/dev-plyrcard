<style>
    /* PLYRCARD shared navigation - single source of truth */
    :root {
      --plyrcard-nav-height: calc(var(--header-h, 76px) + var(--safe-top, 0px));
      --plyrcard-accent: var(--accent, #ff2d35);
      --plyrcard-panel-bg: rgba(8,10,12,0.94);
      --plyrcard-panel-line: rgba(255,255,255,0.18);
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

    #mobile-nav.plyrcard-mobile-nav a,
    #mobile-nav.plyrcard-mobile-nav button {
      font-family: var(--font-display, sans-serif) !important;
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

    #mobile-nav.plyrcard-mobile-nav a:hover,
    #mobile-nav.plyrcard-mobile-nav a.active,
    #mobile-nav.plyrcard-mobile-nav button:hover {
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

    /* PLYRCARD locker-room / get-started action menu */
    .plyrcard-action-drawer,
    .plyrcard-action-drawer * {
      box-sizing: border-box !important;
    }

    .plyrcard-action-drawer {
      position: fixed !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      z-index: 9980 !important;
      pointer-events: none !important;
      font-family: var(--font-display, 'Arial Narrow', Impact, sans-serif) !important;
    }

    .plyrcard-action-drawer.is-open {
      pointer-events: auto !important;
    }

    .plyrcard-drawer-scrim {
      position: fixed !important;
      inset: 0 !important;
      background: rgba(0,0,0,0.36) !important;
      backdrop-filter: blur(4px) !important;
      -webkit-backdrop-filter: blur(4px) !important;
      opacity: 0 !important;
      pointer-events: none !important;
      transition: opacity 0.25s ease !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-scrim {
      opacity: 1 !important;
      pointer-events: auto !important;
    }

    .plyrcard-drawer-panel {
      width: min(100%, 980px) !important;
      margin: 0 auto !important;
      max-height: min(78vh, 760px) !important;
      overflow: hidden !important;
      border-radius: 24px 24px 0 0 !important;
      background: var(--plyrcard-panel-bg) !important;
      border: 1px solid rgba(255,255,255,0.12) !important;
      box-shadow: 0 -22px 60px rgba(0,0,0,0.5) !important;
      transform: translateY(calc(100% - 96px)) !important;
      transition: transform 0.34s var(--ease-out, cubic-bezier(.2,.8,.2,1)) !important;
      pointer-events: auto !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-panel {
      transform: translateY(0) !important;
    }

    .plyrcard-drawer-handle {
      position: absolute !important;
      top: 14px !important;
      left: 50% !important;
      transform: translateX(-50%) !important;
      width: 86px !important;
      height: 8px !important;
      border-radius: 999px !important;
      background: rgba(0,0,0,0.2) !important;
      z-index: 2 !important;
    }

    .plyrcard-drawer-head {
      min-height: 96px !important;
      padding: 28px 50px 18px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      gap: 18px !important;
      background: #fff !important;
      color: #090909 !important;
      border-radius: 24px 24px 0 0 !important;
    }

    .plyrcard-drawer-title-row {
      display: flex !important;
      align-items: center !important;
      gap: 14px !important;
      min-width: 0 !important;
    }

    .plyrcard-drawer-title {
      margin: 0 !important;
      color: #050505 !important;
      font-size: clamp(29px, 4.6vw, 42px) !important;
      line-height: 1 !important;
      font-weight: 900 !important;
      letter-spacing: 0.01em !important;
      text-transform: none !important;
      white-space: nowrap !important;
    }

    .plyrcard-journey-badge {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      min-height: 36px !important;
      padding: 9px 15px !important;
      border-radius: 9px !important;
      background: linear-gradient(135deg, #ff2731, #ff404a) !important;
      color: #fff !important;
      font-size: 18px !important;
      line-height: 1 !important;
      font-weight: 800 !important;
      text-decoration: none !important;
    }

    .plyrcard-drawer-icon-btn,
    .plyrcard-drawer-back {
      flex: 0 0 auto !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      min-width: 44px !important;
      min-height: 44px !important;
      padding: 0 !important;
      background: transparent !important;
      border: 0 !important;
      color: #050505 !important;
      cursor: pointer !important;
      font: inherit !important;
      text-decoration: none !important;
    }

    .plyrcard-drawer-back {
      gap: 10px !important;
      min-width: auto !important;
      font-size: 25px !important;
      font-weight: 800 !important;
      line-height: 1 !important;
    }

    .plyrcard-drawer-close svg,
    .plyrcard-drawer-back svg,
    .plyrcard-drawer-tab-chevron svg {
      width: 34px !important;
      height: 34px !important;
      stroke-width: 3 !important;
    }

    .plyrcard-drawer-body {
      padding: 28px 50px calc(110px + var(--safe-bottom, 0px)) !important;
      color: #fff !important;
      overflow-y: auto !important;
      max-height: calc(min(78vh, 760px) - 96px) !important;
    }

    .plyrcard-drawer-view {
      display: none !important;
    }

    .plyrcard-drawer-view.is-active {
      display: block !important;
    }

    .plyrcard-drawer-grid {
      display: grid !important;
      grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
      gap: 18px 26px !important;
    }

    .plyrcard-drawer-card {
      min-height: 156px !important;
      padding: 19px 14px 16px !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 13px !important;
      border-radius: 14px !important;
      background: #fff !important;
      color: #050505 !important;
      border: 1px solid rgba(0,0,0,0.08) !important;
      box-shadow: 0 8px 22px rgba(0,0,0,0.25), inset 0 0 0 1px rgba(255,255,255,0.6) !important;
      text-decoration: none !important;
      text-align: center !important;
      cursor: pointer !important;
      font: inherit !important;
      transition: transform 0.2s ease, box-shadow 0.2s ease !important;
    }

    .plyrcard-drawer-card:hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 12px 28px rgba(0,0,0,0.35), inset 0 0 0 1px rgba(255,255,255,0.6) !important;
    }

    .plyrcard-drawer-card.is-accent {
      background: linear-gradient(135deg, #ff2731, #ff424b) !important;
      color: #fff !important;
    }

    .plyrcard-drawer-card svg {
      width: 56px !important;
      height: 56px !important;
      stroke: currentColor !important;
      stroke-width: 1.8 !important;
      fill: none !important;
    }

    .plyrcard-drawer-card span {
      display: block !important;
      font-size: clamp(21px, 3vw, 29px) !important;
      font-weight: 850 !important;
      line-height: 1.05 !important;
      color: currentColor !important;
    }

    .plyrcard-drawer-section-divider {
      margin: 30px 0 24px !important;
      height: 1px !important;
      background: var(--plyrcard-panel-line) !important;
    }

    .plyrcard-social-row {
      display: flex !important;
      align-items: center !important;
      flex-wrap: wrap !important;
      gap: clamp(22px, 5vw, 46px) !important;
      color: #fff !important;
    }

    .plyrcard-social-label {
      font-size: clamp(25px, 4vw, 36px) !important;
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

    .plyrcard-social-row svg {
      width: 34px !important;
      height: 34px !important;
      stroke: currentColor !important;
      fill: none !important;
      stroke-width: 2 !important;
    }

    .plyrcard-social-row .youtube-wordmark {
      display: inline-flex !important;
      align-items: center !important;
      gap: 8px !important;
      font-size: clamp(22px, 3.4vw, 32px) !important;
      font-weight: 900 !important;
      line-height: 1 !important;
    }

    .plyrcard-drawer-tab {
      position: absolute !important;
      right: 0 !important;
      bottom: 0 !important;
      min-width: min(380px, 72vw) !important;
      height: 96px !important;
      padding: 0 35px 0 86px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 16px !important;
      background: linear-gradient(135deg, #ff2731, #ff424b) !important;
      color: #fff !important;
      border: 0 !important;
      border-radius: 0 !important;
      font-family: var(--font-display, 'Arial Narrow', Impact, sans-serif) !important;
      font-size: clamp(32px, 5.3vw, 47px) !important;
      font-weight: 900 !important;
      line-height: 1 !important;
      text-transform: none !important;
      cursor: pointer !important;
      pointer-events: auto !important;
      clip-path: polygon(70px 0, 100% 0, 100% 100%, 0% 100%) !important;
    }

    .plyrcard-drawer-tab-chevron {
      display: inline-flex !important;
      transform: rotate(0deg) !important;
      transition: transform 0.28s ease !important;
    }

    .plyrcard-action-drawer.is-open .plyrcard-drawer-tab-chevron {
      transform: rotate(180deg) !important;
    }

    .plyrcard-form-stack {
      display: grid !important;
      gap: 14px !important;
    }

    .plyrcard-input-wrap {
      position: relative !important;
    }

    .plyrcard-input-wrap svg {
      position: absolute !important;
      left: 28px !important;
      top: 50% !important;
      transform: translateY(-50%) !important;
      width: 32px !important;
      height: 32px !important;
      color: #111 !important;
      stroke: currentColor !important;
      fill: none !important;
      stroke-width: 1.8 !important;
    }

    .plyrcard-input-wrap.textarea svg {
      top: 28px !important;
      transform: none !important;
    }

    .plyrcard-drawer-input,
    .plyrcard-drawer-select,
    .plyrcard-drawer-textarea {
      width: 100% !important;
      min-height: 74px !important;
      border-radius: 12px !important;
      border: 0 !important;
      background: #fff !important;
      color: #111 !important;
      padding: 18px 24px 18px 92px !important;
      font-family: var(--font-display, sans-serif) !important;
      font-size: clamp(19px, 3vw, 28px) !important;
      font-weight: 700 !important;
      outline: none !important;
    }

    .plyrcard-drawer-textarea {
      min-height: 130px !important;
      resize: vertical !important;
      padding-top: 22px !important;
    }

    .plyrcard-drawer-input::placeholder,
    .plyrcard-drawer-textarea::placeholder,
    .plyrcard-drawer-select {
      color: rgba(0,0,0,0.42) !important;
    }

    .plyrcard-submit-btn {
      min-height: 78px !important;
      width: min(100%, 560px) !important;
      margin: 18px auto 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 16px !important;
      border: 0 !important;
      border-radius: 12px !important;
      background: linear-gradient(135deg, #ff2731, #ff424b) !important;
      color: #fff !important;
      font-family: var(--font-display, sans-serif) !important;
      font-size: clamp(27px, 4vw, 38px) !important;
      font-weight: 900 !important;
      cursor: pointer !important;
      text-decoration: none !important;
    }

    .plyrcard-submit-btn svg {
      width: 36px !important;
      height: 36px !important;
      stroke: currentColor !important;
      fill: none !important;
      stroke-width: 2 !important;
    }

    .plyrcard-offer-list {
      display: grid !important;
      gap: 18px !important;
    }

    .plyrcard-offer-card {
      display: grid !important;
      grid-template-columns: 92px 1fr auto !important;
      align-items: center !important;
      gap: 22px !important;
      min-height: 140px !important;
      padding: 20px 36px 20px 24px !important;
      border-radius: 14px !important;
      background: #fff !important;
      color: #050505 !important;
      text-decoration: none !important;
      box-shadow: 0 8px 22px rgba(0,0,0,0.25) !important;
    }

    .plyrcard-offer-icon {
      width: 70px !important;
      height: 70px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border-radius: 14px !important;
      background: #fff !important;
      box-shadow: 0 4px 12px rgba(0,0,0,0.14) !important;
    }

    .plyrcard-offer-icon svg {
      width: 44px !important;
      height: 44px !important;
      stroke: currentColor !important;
      fill: none !important;
      stroke-width: 1.8 !important;
    }

    .plyrcard-offer-title {
      margin: 0 0 6px !important;
      font-size: clamp(25px, 3.6vw, 35px) !important;
      line-height: 1 !important;
      font-weight: 900 !important;
      color: #050505 !important;
    }

    .plyrcard-offer-copy {
      margin: 0 !important;
      color: #303746 !important;
      font-family: var(--font-body, sans-serif) !important;
      font-size: clamp(16px, 2.7vw, 23px) !important;
      line-height: 1.25 !important;
      font-weight: 500 !important;
    }

    .plyrcard-offer-price {
      text-align: right !important;
      color: #168bff !important;
      font-size: clamp(31px, 4.5vw, 45px) !important;
      font-weight: 950 !important;
      line-height: .9 !important;
      white-space: nowrap !important;
    }

    .plyrcard-offer-price small {
      display: block !important;
      margin-top: 8px !important;
      color: #4d5565 !important;
      font-size: clamp(14px, 2vw, 21px) !important;
      line-height: 1 !important;
      letter-spacing: 0.06em !important;
      text-transform: uppercase !important;
    }

    .plyrcard-placeholder-panel {
      padding: 28px !important;
      border-radius: 16px !important;
      border: 1px dashed rgba(255,255,255,0.35) !important;
      color: rgba(255,255,255,0.8) !important;
      font-family: var(--font-body, sans-serif) !important;
      font-size: 18px !important;
      line-height: 1.45 !important;
    }

    @media (min-width: 960px) {
      .plyrcard-action-drawer { display: none !important; }
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

      .plyrcard-drawer-panel {
        border-radius: 18px 18px 0 0 !important;
        transform: translateY(calc(100% - 84px)) !important;
      }

      .plyrcard-drawer-head {
        min-height: 84px !important;
        padding: 24px 20px 14px !important;
        border-radius: 18px 18px 0 0 !important;
      }

      .plyrcard-drawer-title-row {
        gap: 9px !important;
      }

      .plyrcard-drawer-title {
        font-size: 30px !important;
      }

      .plyrcard-journey-badge {
        min-height: 32px !important;
        padding: 8px 12px !important;
        font-size: 16px !important;
      }

      .plyrcard-drawer-back {
        font-size: 21px !important;
        gap: 5px !important;
      }

      .plyrcard-drawer-close svg,
      .plyrcard-drawer-back svg {
        width: 30px !important;
        height: 30px !important;
      }

      .plyrcard-drawer-body {
        padding: 24px 20px calc(98px + var(--safe-bottom, 0px)) !important;
      }

      .plyrcard-drawer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 16px !important;
      }

      .plyrcard-drawer-card {
        min-height: 124px !important;
        padding: 16px 10px 14px !important;
        gap: 10px !important;
      }

      .plyrcard-drawer-card svg {
        width: 46px !important;
        height: 46px !important;
      }

      .plyrcard-drawer-card span {
        font-size: 22px !important;
      }

      .plyrcard-drawer-tab {
        min-width: 62vw !important;
        height: 84px !important;
        padding: 0 22px 0 70px !important;
        font-size: 31px !important;
        clip-path: polygon(54px 0, 100% 0, 100% 100%, 0% 100%) !important;
      }

      .plyrcard-social-row {
        gap: 22px !important;
      }

      .plyrcard-social-label {
        width: 100% !important;
        font-size: 28px !important;
      }

      .plyrcard-offer-card {
        grid-template-columns: 58px 1fr auto !important;
        gap: 12px !important;
        min-height: 116px !important;
        padding: 18px 18px 18px 14px !important;
      }

      .plyrcard-offer-icon {
        width: 52px !important;
        height: 52px !important;
      }

      .plyrcard-offer-icon svg {
        width: 34px !important;
        height: 34px !important;
      }

      .plyrcard-offer-title {
        font-size: 24px !important;
      }

      .plyrcard-offer-copy {
        font-size: 16px !important;
      }

      .plyrcard-offer-price {
        font-size: 30px !important;
      }

      .plyrcard-offer-price small {
        font-size: 13px !important;
      }
    }


    /* Share pop-up and final compact mobile sizing */
    .plyrcard-share-grid {
      display: grid !important;
      grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
      gap: 14px !important;
    }

    .plyrcard-share-option {
      min-height: 104px !important;
      padding: 14px 8px !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 9px !important;
      border-radius: 12px !important;
      background: #fff !important;
      color: #050505 !important;
      text-decoration: none !important;
      box-shadow: 0 6px 16px rgba(0,0,0,0.24) !important;
      border: 0 !important;
      cursor: pointer !important;
    }

    .plyrcard-share-option i {
      font-size: 30px !important;
      line-height: 1 !important;
      color: #050505 !important;
    }

    .plyrcard-share-option span {
      font-size: 16px !important;
      font-weight: 850 !important;
      line-height: 1 !important;
      color: #050505 !important;
    }

    .plyrcard-share-note {
      margin: 14px 0 0 !important;
      font-family: var(--font-body, sans-serif) !important;
      color: rgba(255,255,255,0.72) !important;
      font-size: 13px !important;
      line-height: 1.35 !important;
    }

    .plyrcard-menu-icon,
    .plyrcard-social-row i,
    .plyrcard-field-icon,
    .plyrcard-offer-icon i {
      line-height: 1 !important;
      color: currentColor !important;
    }

    @media (max-width: 959px) {
      .plyrcard-action-drawer { display: block !important; }
      .plyrcard-drawer-grid { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; gap: 10px 14px !important; }
      .plyrcard-drawer-card { min-height: 90px !important; padding: 10px 6px 8px !important; gap: 6px !important; border-radius: 10px !important; }
      .plyrcard-menu-icon { font-size: 25px !important; width: 28px !important; height: 28px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }
      .plyrcard-drawer-card span { font-size: clamp(13px, 2.2vw, 17px) !important; line-height: 1.05 !important; }
      .plyrcard-drawer-head { min-height: 62px !important; padding: 17px 26px 10px !important; }
      .plyrcard-drawer-title { font-size: clamp(21px, 3.1vw, 28px) !important; }
      .plyrcard-drawer-body { padding: 16px 26px calc(76px + var(--safe-bottom, 0px)) !important; }
      .plyrcard-drawer-tab { width: min(340px, 64vw) !important; min-width: 260px !important; height: 66px !important; padding: 0 22px 0 58px !important; font-size: clamp(23px, 3.7vw, 31px) !important; clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%) !important; }
      .plyrcard-drawer-tab-chevron svg { width: 26px !important; height: 26px !important; }
      .plyrcard-social-row { gap: 16px 26px !important; }
      .plyrcard-social-label { font-size: clamp(19px, 3.2vw, 24px) !important; }
      .plyrcard-social-row i { font-size: 22px !important; width: 22px !important; height: 22px !important; }
      .plyrcard-form-stack { gap: 10px !important; }
      .plyrcard-drawer-input, .plyrcard-drawer-select, .plyrcard-drawer-textarea { min-height: 54px !important; padding-left: 62px !important; font-size: clamp(14px, 2.3vw, 19px) !important; }
      .plyrcard-field-icon { left: 20px !important; font-size: 20px !important; }
      .plyrcard-submit-btn { min-height: 54px !important; font-size: clamp(19px, 3vw, 25px) !important; }
      .plyrcard-offer-card { min-height: 88px !important; grid-template-columns: 54px 1fr auto !important; padding: 12px 18px 12px 12px !important; }
      .plyrcard-offer-icon { width: 46px !important; height: 46px !important; }
      .plyrcard-offer-icon i { font-size: 24px !important; }
      .plyrcard-offer-title { font-size: clamp(17px, 2.8vw, 23px) !important; }
      .plyrcard-offer-copy { font-size: clamp(11px, 1.8vw, 15px) !important; }
      .plyrcard-offer-price { font-size: clamp(21px, 3.4vw, 29px) !important; }
    }

    @media (max-width: 520px) {
      .plyrcard-drawer-body { padding: 14px 16px calc(70px + var(--safe-bottom, 0px)) !important; }
      .plyrcard-drawer-grid { gap: 8px !important; }
      .plyrcard-drawer-card { min-height: 78px !important; padding: 8px 4px 7px !important; }
      .plyrcard-menu-icon { font-size: 21px !important; width: 24px !important; height: 24px !important; }
      .plyrcard-drawer-card span { font-size: 11px !important; }
      .plyrcard-drawer-tab { min-width: 240px !important; width: 68vw !important; height: 58px !important; padding-left: 50px !important; font-size: 21px !important; }
      .plyrcard-share-grid { gap: 8px !important; }
      .plyrcard-share-option { min-height: 76px !important; padding: 9px 4px !important; gap: 6px !important; }
      .plyrcard-share-option i { font-size: 22px !important; }
      .plyrcard-share-option span { font-size: 11px !important; }
    }


    /* On admin and player website pages, only the bottom pull-up drawer should show. */
    .plyrcard-action-drawer.is-pullup-only {
      display: block !important;
    }

    @media (min-width: 960px) {
      .plyrcard-action-drawer.is-pullup-only {
        display: block !important;
      }
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
  $plyrOnAdmin = request()->is('admin') || request()->is('admin/*');
  $plyrCurrentPath = trim(request()->path(), '/');
  $plyrCurrentHost = strtolower(preg_replace('/:\d+$/', '', request()->getHost()));
  $plyrReservedPaths = ['', 'about', 'pricing', 'podcast', 'book-demo', 'registration', 'login', 'admin'];

  if (! ($plyrCurrentWebsite instanceof \App\Models\Website) && class_exists(\App\Models\Website::class)) {
      if (! in_array($plyrCurrentHost, ['127.0.0.1', 'localhost'], true)) {
          $plyrCurrentWebsite = \App\Models\Website::query()
              ->where('is_active', true)
              ->where('is_published', true)
              ->whereNotNull('domain')
              ->get()
              ->first(function ($candidate) use ($plyrCurrentHost) {
                  $domain = strtolower(trim((string) $candidate->domain));
                  $domain = preg_replace('#^https?://#i', '', $domain);
                  $domain = preg_replace('/:\d+$/', '', $domain);
                  $domain = rtrim($domain, '/');

                  return preg_replace('/^www\./', '', $domain) === preg_replace('/^www\./', '', $plyrCurrentHost);
              });
      }

      if (! $plyrCurrentWebsite && ! $plyrOnAdmin && ! in_array($plyrCurrentPath, $plyrReservedPaths, true)) {
          $normalizedPath = \Illuminate\Support\Str::slug($plyrCurrentPath);

          $plyrCurrentWebsite = \App\Models\Website::query()
              ->where('is_active', true)
              ->where('is_published', true)
              ->where(function ($query) use ($plyrCurrentPath, $normalizedPath) {
                  $query->whereRaw('LOWER(slug) = ?', [strtolower($plyrCurrentPath)])
                      ->orWhereRaw('LOWER(slug) = ?', [strtolower($normalizedPath)]);
              })
              ->first();

          if (! $plyrCurrentWebsite) {
              $plyrCurrentWebsite = \App\Models\Website::query()
                  ->where('is_active', true)
                  ->where('is_published', true)
                  ->get()
                  ->first(function ($candidate) use ($normalizedPath) {
                      return \Illuminate\Support\Str::slug((string) $candidate->name) === $normalizedPath;
                  });
          }
      }
  }

  $plyrViewingPlayerWebsite = $plyrCurrentWebsite instanceof \App\Models\Website;
  $plyrOwnsCurrentWebsite = $plyrViewingPlayerWebsite && $plyrLoggedIn && $plyrUser && ((int) $plyrCurrentWebsite->user_id === (int) $plyrUser->id);

  /*
   * Main PLYRCard site: show GET STARTED when logged out, Locker Room when logged in.
   * Own player website: show Locker Room only when logged in.
   * Other player websites: show nothing, regardless of auth state.
   */
  $plyrShouldRenderNavigation = ! $plyrViewingPlayerWebsite || ($plyrLoggedIn && $plyrOwnsCurrentWebsite);
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
          <svg width="43" height="43" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4 21c1.6-4.2 4.2-6.3 8-6.3s6.4 2.1 8 6.3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          <h2 class="plyrcard-drawer-title">Hi {{ $plyrFirstName ?? 'Clark' }}!</h2>
          <a class="plyrcard-journey-badge" href="/admin">My Journey</a>
        @else
          <h2 class="plyrcard-drawer-title">Welcome to PLYRCARD!</h2>
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
            <a href="{{ $plyrYouTubeUrl }}" target="_blank" rel="noopener" aria-label="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true"></i><span>YouTube</span></a>
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
              <i class="plyrcard-menu-icon fa-regular fa-user-plus" aria-hidden="true"></i><span>Register Now</span>
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
            <a href="{{ $plyrYouTubeUrl }}" target="_blank" rel="noopener" aria-label="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true"></i><span>YouTube</span></a>
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
            <a class="plyrcard-share-option" target="_blank" rel="noopener" href="https://www.youtube.com/@plyrcard"><i class="fa-brands fa-youtube" aria-hidden="true"></i><span>YouTube</span></a>
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