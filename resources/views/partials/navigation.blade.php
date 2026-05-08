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

    .plyrcard-hide-on-player-site,
    #mobile-nav.plyrcard-hide-on-player-site {
      display: none !important;
    }

</style>

@php
  $plyrUser = auth()->user();
  $plyrLoggedIn = auth()->check();
  $plyrFirstName = $plyrLoggedIn ? explode(' ', trim($plyrUser->name ?? 'Player'))[0] : null;

  $plyrHost = request()->getHost();
  $plyrHostNormalized = strtolower(preg_replace('/^www\./i', '', $plyrHost));
  $plyrPath = trim(request()->path(), '/');
  $plyrFirstSegment = $plyrPath === '' ? '' : explode('/', $plyrPath)[0];

  $plyrMainHosts = ['plyrcard.com', 'localhost', '127.0.0.1'];
  $plyrReservedPaths = [
      '', 'admin', 'about', 'pricing', 'podcast', 'book-demo', 'registration',
      'login', 'logout', 'dashboard', 'journey', 'api', 'css', 'js', 'images',
      'storage', 'livewire', 'filament',
  ];

  $plyrCurrentWebsite = null;

  if (class_exists(\App\Models\Website::class)) {
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

      if (! $plyrCurrentWebsite && ! in_array($plyrFirstSegment, $plyrReservedPaths, true)) {
          $plyrCurrentWebsite = \App\Models\Website::query()
              ->where('is_active', true)
              ->where('is_published', true)
              ->where('slug', $plyrFirstSegment)
              ->first();
      }
  }

  $plyrIsPlayerWebsite = (bool) $plyrCurrentWebsite;
  $plyrIsOwnPlayerWebsite = $plyrLoggedIn
      && $plyrCurrentWebsite
      && (int) $plyrCurrentWebsite->user_id === (int) $plyrUser->id;

  // Main PLYRCard site: show Get Started when logged out, Locker Room when logged in.
  // Player website: show Locker Room only to the owning logged-in player.
  // Other player website: show nothing.
  $plyrShowActionDrawer = $plyrIsPlayerWebsite ? $plyrIsOwnPlayerWebsite : true;
@endphp

<header id="site-header" class="plyrcard-site-header over-hero{{ $plyrIsPlayerWebsite ? ' plyrcard-hide-on-player-site' : '' }}">
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
      <a href="/dashboard">Dashboard</a>
      <a href="/logout">Logout</a>
    @else
      <a href="/admin">Login</a>
      <a data-nav href="/registration?utm_plan=free" class="desktop-nav-cta{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
    @endauth
  </nav>

  <button class="menu-btn" id="menu-btn" type="button" aria-label="Open menu" aria-controls="mobile-nav" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</header>

<nav id="mobile-nav" class="plyrcard-mobile-nav{{ $plyrIsPlayerWebsite ? ' plyrcard-hide-on-player-site' : '' }}" aria-label="Mobile navigation">
  <a data-nav href="/" class="nav-link{{ ($activePage ?? '') === 'home' ? ' active' : '' }}">Home</a>
  <a data-nav href="/about" class="nav-link{{ ($activePage ?? '') === 'about' ? ' active' : '' }}">About</a>
  <a data-nav href="/pricing" class="nav-link{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Pricing</a>
  <a data-nav href="/podcast" class="nav-link{{ ($activePage ?? '') === 'podcast' ? ' active' : '' }}">Podcast</a>
  <a data-nav href="/book-demo" class="nav-link{{ ($activePage ?? '') === 'book-demo' ? ' active' : '' }}">Book Demo</a>
  @auth
    <button type="button" class="nav-link" data-plyrcard-open-drawer>Locker Room</button>
    <a href="/dashboard" class="nav-link">Dashboard</a>
    <a href="/logout" class="nav-link">Logout</a>
  @else
    <button type="button" class="nav-link" data-plyrcard-open-drawer>Get Started</button>
    <a href="/admin" class="nav-link">Login</a>
    <a data-nav href="/registration?utm_plan=free" class="nav-cta-pill{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
  @endauth
</nav>

@if($plyrShowActionDrawer)
<div id="plyrcard-action-drawer" class="plyrcard-action-drawer" data-state="closed" data-logged-in="{{ $plyrLoggedIn ? 'true' : 'false' }}">
  <div class="plyrcard-drawer-scrim" data-plyrcard-close-drawer></div>

  <section class="plyrcard-drawer-panel" aria-label="{{ $plyrLoggedIn ? 'Locker Room menu' : 'Get Started menu' }}">
    <div class="plyrcard-drawer-handle" aria-hidden="true"></div>

    <div class="plyrcard-drawer-head">
      <div class="plyrcard-drawer-title-row" data-plyrcard-main-title>
        @auth
          <svg width="43" height="43" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4 21c1.6-4.2 4.2-6.3 8-6.3s6.4 2.1 8 6.3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          <h2 class="plyrcard-drawer-title">Hi {{ $plyrFirstName ?? 'Clark' }}!</h2>
          <a class="plyrcard-journey-badge" href="/journey">My Journey</a>
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
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="upgrade">
              <svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="m4 8 8 6 8-6"/></svg>
              <span>Upgrade</span>
            </button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="support">
              <svg viewBox="0 0 24 24"><path d="M4 5h12v10H7l-3 3V5z"/><path d="M17 8h3v9l-2-2h-5"/></svg>
              <span>Support</span>
            </button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="book-call">
              <svg viewBox="0 0 24 24"><path d="M6.6 10.8c1.5 3 3.6 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.3 1.2.4 2.4.6 3.7.6.4 0 .8.4.8.8V20c0 .4-.4.8-.8.8C10.7 20.8 3.2 13.3 3.2 3.8c0-.4.4-.8.8-.8h3.7c.4 0 .8.4.8.8 0 1.3.2 2.5.6 3.7.1.4 0 .8-.3 1.1l-2.2 2.2z"/><path d="M15 4c2.8.5 4.5 2.2 5 5"/><path d="M15 8c.8.2 1.3.7 1.5 1.5"/></svg>
              <span>Book a Call</span>
            </button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="refer-friend">
              <svg viewBox="0 0 24 24"><path d="M4 6h12v9H8l-4 4V6z"/><path d="M14 9h6v8l-3-3h-3"/></svg>
              <span>Refer a Friend</span>
            </button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="plyrcard-show">
              <svg viewBox="0 0 24 24"><path d="M4 14c5-1 9-4 12-9v5h4v8h-4v5c-3-5-7-8-12-9z"/></svg>
              <span>PLYRCard Show</span>
            </button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="a-la-carte">
              <svg viewBox="0 0 24 24"><path d="M7 3v4M17 3v4M4 8h16M5 5h14v15H5z"/><path d="M8 12h2M12 12h2M16 12h2M8 16h2M12 16h2M16 16h2"/></svg>
              <span>A La Carte</span>
            </button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="website">
              <svg viewBox="0 0 24 24"><path d="M5 20h14M7 17h10V5H7z"/><path d="M9 9h6M9 13h3"/><circle cx="16" cy="8" r="3"/><path d="M16 6v4M14 8h4"/></svg>
              <span>Go to my Website</span>
            </button>
            <a class="plyrcard-drawer-card is-accent" href="/journey">
              <svg viewBox="0 0 24 24"><path d="M9 6h9v12H9"/><path d="m13 8 4 4-4 4"/><path d="M4 12h13"/></svg>
              <span>My Journey</span>
            </a>
          </div>
          @includeWhen(View::exists('partials.navigation-socials'), 'partials.navigation-socials')
          <div class="plyrcard-drawer-section-divider"></div>
          <div class="plyrcard-social-row">
            <span class="plyrcard-social-label">Follow Us</span>
            <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7h.01"/></svg></a>
            <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v3H6v4h3v4h4v-4h3l1-4h-4V9c0-.6.4-1 1-1z"/></svg></a>
            <a href="#" aria-label="X"><svg viewBox="0 0 24 24"><path d="m4 4 16 16M20 4 4 20"/></svg></a>
            <a href="#" class="youtube-wordmark" aria-label="YouTube"><svg viewBox="0 0 24 24"><path d="M22 12s0-4-1-5c-.6-.7-1.3-.8-2-.9C16 5 12 5 12 5s-4 0-7 .1c-.7.1-1.4.2-2 .9-1 1-1 5-1 5s0 4 1 5c.6.7 1.3.8 2 .9 3 .1 7 .1 7 .1s4 0 7-.1c.7-.1 1.4-.2 2-.9 1-1 1-5 1-5z"/><path d="m10 9 5 3-5 3z"/></svg><span>YouTube</span></a>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="upgrade" data-title="Upgrade">
          <div class="plyrcard-placeholder-panel">Placeholder for Upgrade. Replace this area with your upgrade plans or link.</div>
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

        <div class="plyrcard-drawer-view" data-plyrcard-view="book-call" data-title="Book a Call">
          <div class="plyrcard-placeholder-panel">Placeholder for Book a Call. Add your calendar embed, booking route, or external scheduling link here.</div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="refer-friend" data-title="Refer a Friend">
          <form class="plyrcard-form-stack" action="#" method="POST">
            @csrf
            <label class="plyrcard-input-wrap"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.6-4.2 4.2-6.3 8-6.3s6.4 2.1 8 6.3"/></svg><input class="plyrcard-drawer-input" name="friend_name" placeholder="Friend’s name"></label>
            <label class="plyrcard-input-wrap"><svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="m4 8 8 6 8-6"/></svg><input class="plyrcard-drawer-input" type="email" name="friend_email" placeholder="Friend’s email"></label>
            <label class="plyrcard-input-wrap"><svg viewBox="0 0 24 24"><path d="M6.6 10.8c1.5 3 3.6 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.3 1.2.4 2.4.6 3.7.6.4 0 .8.4.8.8V20c0 .4-.4.8-.8.8C10.7 20.8 3.2 13.3 3.2 3.8c0-.4.4-.8.8-.8h3.7c.4 0 .8.4.8.8 0 1.3.2 2.5.6 3.7.1.4 0 .8-.3 1.1l-2.2 2.2z"/></svg><input class="plyrcard-drawer-input" name="friend_phone" placeholder="Friend’s phone"></label>
            <label class="plyrcard-input-wrap textarea"><svg viewBox="0 0 24 24"><path d="M4 5h16v12H8l-4 4V5z"/></svg><textarea class="plyrcard-drawer-textarea" name="message" placeholder="Add a short message..."></textarea></label>
            <button class="plyrcard-submit-btn" type="submit"><svg viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg> Send Invite</button>
          </form>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="plyrcard-show" data-title="PLYRCard Show">
          <div class="plyrcard-placeholder-panel">Placeholder for PLYRCard Show. Replace with your episode list, YouTube link, or podcast route.</div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="a-la-carte" data-title="A La Carte">
          <div class="plyrcard-offer-list">
            <a href="#" class="plyrcard-offer-card">
              <span class="plyrcard-offer-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/><path d="M16 14h3v5"/></svg></span>
              <span><h3 class="plyrcard-offer-title">Upgraded Site Design</h3><p class="plyrcard-offer-copy">A full redesign of your athlete website</p></span>
              <strong class="plyrcard-offer-price">$150<small>One-time</small></strong>
            </a>
            <a href="#" class="plyrcard-offer-card">
              <span class="plyrcard-offer-icon"><svg viewBox="0 0 24 24"><path d="M4 7h10v10H4z"/><path d="M10 4h10v10"/><path d="m7 14 3-3 3 3"/><circle cx="9" cy="10" r="1"/></svg></span>
              <span><h3 class="plyrcard-offer-title">Starting Graphics Bundle</h3><p class="plyrcard-offer-copy">Starting graphic • Showcase graphic • Thank You graphic</p></span>
              <strong class="plyrcard-offer-price">$70<small>Bundle</small></strong>
            </a>
            <a href="#" class="plyrcard-offer-card">
              <span class="plyrcard-offer-icon"><svg viewBox="0 0 24 24"><path d="M12 3v18"/><path d="m8 7 4-4 4 4"/><path d="M6 21h12"/><path d="M7 13c2-3 8-3 10 0"/></svg></span>
              <span><h3 class="plyrcard-offer-title">Individual Graphic</h3><p class="plyrcard-offer-copy">Single custom athlete graphic</p></span>
              <strong class="plyrcard-offer-price">$35<small>Each</small></strong>
            </a>
            <a href="#" class="plyrcard-offer-card">
              <span class="plyrcard-offer-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg></span>
              <span><h3 class="plyrcard-offer-title">Domain</h3><p class="plyrcard-offer-copy">Custom domain registration for your athlete site</p></span>
              <strong class="plyrcard-offer-price">$45<small>/Year</small></strong>
            </a>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="website" data-title="Go to my Website">
          <div class="plyrcard-placeholder-panel">Placeholder for Go to my Website. Replace this with the athlete website URL or generated route.</div>
        </div>
      @else
        <div class="plyrcard-drawer-view is-active" data-plyrcard-view="main">
          <div class="plyrcard-drawer-grid">
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="email-us"><svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="m4 8 8 6 8-6"/></svg><span>Email Us</span></button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="text-us"><svg viewBox="0 0 24 24"><path d="M4 5h16v12H8l-4 4V5z"/><path d="M8 9h8"/></svg><span>Text us</span></button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="call-us"><svg viewBox="0 0 24 24"><path d="M6.6 10.8c1.5 3 3.6 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.3 1.2.4 2.4.6 3.7.6.4 0 .8.4.8.8V20c0 .4-.4.8-.8.8C10.7 20.8 3.2 13.3 3.2 3.8c0-.4.4-.8.8-.8h3.7c.4 0 .8.4.8.8 0 1.3.2 2.5.6 3.7.1.4 0 .8-.3 1.1l-2.2 2.2z"/><path d="M15 4c2.8.5 4.5 2.2 5 5"/></svg><span>Call us</span></button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="chat-us"><svg viewBox="0 0 24 24"><path d="M4 6h10v8H8l-4 4V6z"/><path d="M12 10h8v8l-3-3h-5"/></svg><span>Chat Us</span></button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="share"><svg viewBox="0 0 24 24"><circle cx="6" cy="12" r="3"/><circle cx="18" cy="5" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.6 6.8-4.2M8.6 13.4l6.8 4.2"/></svg><span>Share</span></button>
            <a class="plyrcard-drawer-card" href="/book-demo"><svg viewBox="0 0 24 24"><path d="M7 3v4M17 3v4M4 8h16M5 5h14v15H5z"/><path d="M8 12h2M12 12h2M16 12h2M8 16h2M12 16h2M16 16h2"/></svg><span>Book a Demo</span></a>
            <a class="plyrcard-drawer-card" href="/registration?utm_plan=free"><svg viewBox="0 0 24 24"><circle cx="10" cy="8" r="4"/><path d="M3 21c1.4-4 3.8-6 7-6 1.5 0 2.8.4 3.9 1.2"/><circle cx="18" cy="17" r="4"/><path d="M18 15v4M16 17h4"/></svg><span>Register Now</span></a>
            <a class="plyrcard-drawer-card is-accent" href="/admin"><svg viewBox="0 0 24 24"><path d="M9 6h9v12H9"/><path d="m13 8 4 4-4 4"/><path d="M4 12h13"/></svg><span>Login</span></a>
          </div>
          <div class="plyrcard-drawer-section-divider"></div>
          <div class="plyrcard-social-row">
            <span class="plyrcard-social-label">Follow Us</span>
            <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7h.01"/></svg></a>
            <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v3H6v4h3v4h4v-4h3l1-4h-4V9c0-.6.4-1 1-1z"/></svg></a>
            <a href="#" aria-label="X"><svg viewBox="0 0 24 24"><path d="m4 4 16 16M20 4 4 20"/></svg></a>
            <a href="#" aria-label="YouTube"><svg viewBox="0 0 24 24"><path d="M22 12s0-4-1-5c-.6-.7-1.3-.8-2-.9C16 5 12 5 12 5s-4 0-7 .1c-.7.1-1.4.2-2 .9-1 1-1 5-1 5s0 4 1 5c.6.7 1.3.8 2 .9 3 .1 7 .1 7 .1s4 0 7-.1c.7-.1 1.4-.2 2-.9 1-1 1-5 1-5z"/><path d="m10 9 5 3-5 3z"/></svg></a>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="email-us" data-title="Email Us"><div class="plyrcard-placeholder-panel">Placeholder for Email Us. Replace with mailto link, contact form, or route.</div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="text-us" data-title="Text us"><div class="plyrcard-placeholder-panel">Placeholder for Text us. Replace with SMS link, form, or route.</div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="call-us" data-title="Call us"><div class="plyrcard-placeholder-panel">Placeholder for Call us. Replace with tel link or phone support details.</div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="chat-us" data-title="Chat Us"><div class="plyrcard-placeholder-panel">Placeholder for Chat Us. Replace with your chat widget trigger.</div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="share" data-title="Share"><div class="plyrcard-placeholder-panel">Placeholder for Share. Replace with native share code or referral link.</div></div>
      @endauth
    </div>

    <button type="button" class="plyrcard-drawer-tab" data-plyrcard-toggle-drawer aria-expanded="false">
      <span class="plyrcard-drawer-tab-chevron" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
      <span>{{ $plyrLoggedIn ? 'Locker Room' : 'GET STARTED' }}</span>
    </button>
  </section>
</div>
@endif

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