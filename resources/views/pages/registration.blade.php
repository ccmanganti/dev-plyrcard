@php
    $activePage = 'registration';

    $allowedPlans = ['free', 'plyr', 'plyr-plus', 'my-journey'];
    $registrationPlan = request()->query('utm_plan', 'free');
    $registrationPlan = in_array($registrationPlan, $allowedPlans, true) ? $registrationPlan : 'free';

    $registrationEmbedUrl = 'https://plyrcard.com/player-intake-app?' . http_build_query([
        'utm_plan' => $registrationPlan,
    ]);
@endphp

@include('partials.images')
<!DOCTYPE html>
<html lang="en">
<head>
	
	<script src="https://widgets.leadconnectorhq.com/loader.js" data-resources-url="https://widgets.leadconnectorhq.com/chat-widget/loader.js" data-widget-id="6941fea74ca18223c7de491d"></script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="description" content="Start free with PLYRCARD — Create your athlete profile and begin building your recruiting presence." />
  <title>Start Free — PLYRCARD</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --accent:       #FF5C35;
      --accent-dark:  #E04520;
      --black:        #0D0D0D;
      --surface:      #171717;
      --surface-2:    #111111;
      --white:        #FFFFFF;
      --font-display: 'Antonio', sans-serif;
      --font-body:    'Inter', sans-serif;
      --safe-top:     env(safe-area-inset-top, 0px);
      --safe-bottom:  env(safe-area-inset-bottom, 0px);
      --header-h:     60px;
      --radius-card:  20px;
      --radius-btn:   9999px;
      --ease-out:     cubic-bezier(0.16, 1, 0.3, 1);
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: var(--font-body);
      background: var(--black);
      color: var(--white);
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
    }

    img { display: block; max-width: 100%; }
    a   { color: inherit; text-decoration: none; }
    ul  { list-style: none; }

    /* HEADER */
    #site-header {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 100;
      height: calc(var(--header-h) + var(--safe-top));
      padding-top: var(--safe-top);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-left: 20px;
      padding-right: 20px;
      background: rgba(13,13,13,0.92);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(255,255,255,0.07);
      transition: background 0.3s var(--ease-out), border-color 0.3s var(--ease-out);
    }

    #site-header.over-hero {
      background: transparent;
      border-bottom-color: transparent;
    }

    .logo-wrap {
      display: flex;
      align-items: center;
      height: 32px;
    }

    .logo-wrap img {
      height: 32px;
      width: auto;
      object-fit: contain;
      filter: brightness(0) invert(1);
    }

    .logo-text {
      font-family: var(--font-display);
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 0.04em;
      line-height: 1;
    }

    .logo-text span { color: var(--accent); }

    .menu-btn {
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 5px;
      width: 44px; height: 44px;
      background: none;
      border: none;
      cursor: pointer;
      padding: 10px 6px;
      margin-right: -6px;
    }

    .menu-btn span {
      display: block;
      width: 24px; height: 2px;
      background: var(--white);
      border-radius: 2px;
      transition: transform 0.3s, opacity 0.3s;
    }

    .menu-btn.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .menu-btn.open span:nth-child(2) { opacity: 0; }
    .menu-btn.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    #mobile-nav {
      position: fixed;
      inset: 0;
      z-index: 99;
      background: var(--black);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 32px;
      opacity: 0;
      pointer-events: none;
      transform: translateY(-12px);
      transition: opacity 0.35s var(--ease-out), transform 0.35s var(--ease-out);
    }

    #mobile-nav.open {
      opacity: 1;
      pointer-events: auto;
      transform: translateY(0);
    }

    .nav-link {
      font-family: var(--font-display);
      font-size: 36px;
      font-weight: 700;
      letter-spacing: -0.01em;
      color: var(--white);
      text-transform: uppercase;
      transition: color 0.2s;
    }

    .nav-link:hover,
    .nav-link.active { color: var(--accent); }

    .nav-cta-pill {
      display: inline-flex;
      align-items: center;
      background: var(--accent);
      color: var(--white);
      font-family: var(--font-display);
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      border-radius: var(--radius-btn);
      padding: 14px 32px;
      margin-top: 8px;
    }

    /* ONE SECTION DEMO PAGE */
    #book-demo {
      position: relative;
      min-height: 100svh;
      padding: calc(var(--header-h) + var(--safe-top) + 44px) 24px calc(64px + var(--safe-bottom));
      background-color: var(--black);
      /* background-image: url('https://sspark.genspark.ai/cfimages?u1=iQbVZ84bc1gGwxenWqUmUOgj%2F2Wg%2BqiEhoLVWNblJQ8%2FPLZy1BcpnRWyXC4lwJcZqpWhKu4CZJ8lTCaA0EiurwYpLi9YlAQazS7mQ3YrY2F0rB7A&u2=0S8QqchqH1OMoytY&width=2560'); */
      background-size: cover;
      background-position: center 34%;
      overflow: hidden;
    }

    #book-demo::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 18% 28%, rgba(255,92,53,0.20), transparent 32%),
        linear-gradient(to bottom, rgba(0,0,0,0.42) 0%, rgba(13,13,13,0.86) 46%, rgba(13,13,13,0.96) 100%);
      pointer-events: none;
    }

    #book-demo::after {
      content: 'PLYRCARD';
      position: absolute;
      left: -12px;
      bottom: 18px;
      font-family: var(--font-display);
      font-size: clamp(72px, 23vw, 240px);
      font-weight: 700;
      line-height: 0.75;
      letter-spacing: -0.05em;
      color: rgba(255,255,255,0.025);
      pointer-events: none;
      user-select: none;
      white-space: nowrap;
    }

    .demo-shell {
      position: relative;
      z-index: 1;
      width: min(1180px, 100%);
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr;
      gap: 30px;
      align-items: center;
    }

    .demo-copy {
      max-width: 560px;
    }

    .page-eyebrow {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 12px;
    }

    .demo-title {
      font-family: var(--font-display);
      font-size: clamp(52px, 15vw, 92px);
      font-weight: 700;
      line-height: 0.90;
      letter-spacing: -0.035em;
      text-transform: uppercase;
      color: var(--white);
      margin-bottom: 18px;
    }

    .demo-title span { color: var(--accent); }

    .demo-lead {
      font-size: 17px;
      line-height: 1.65;
      color: rgba(255,255,255,0.76);
      max-width: 440px;
      margin-bottom: 24px;
    }

    .demo-points {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      margin-top: 26px;
      max-width: 500px;
    }

    .demo-point {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      padding: 14px 0;
      border-bottom: 1px solid rgba(255,255,255,0.075);
    }

    .demo-point:last-child { border-bottom: none; }

    .point-number {
      flex-shrink: 0;
      font-family: var(--font-display);
      font-size: 22px;
      font-weight: 700;
      line-height: 1;
      color: var(--accent);
      width: 34px;
    }

    .point-title {
      font-family: var(--font-display);
      font-size: 18px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: -0.01em;
      margin-bottom: 3px;
    }

    .point-copy {
      font-size: 14px;
      line-height: 1.55;
      color: rgba(255,255,255,0.56);
    }

    .mini-proof {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      max-width: 480px;
      margin-top: 28px;
    }

    .proof-box {
      background: rgba(255,255,255,0.055);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px;
      padding: 14px 12px;
    }

    .proof-num {
      font-family: var(--font-display);
      font-size: 24px;
      font-weight: 700;
      line-height: 1;
      color: var(--white);
    }

    .proof-label {
      margin-top: 4px;
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.38);
    }

    .calendar-card {
      width: 100%;
      background: rgba(23,23,23,0.86);
      border: 1px solid rgba(255,255,255,0.10);
      border-radius: var(--radius-card);
      box-shadow: 0 34px 80px rgba(0,0,0,0.42);
      overflow: hidden;
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
    }

    .calendar-card-top {
      padding: 18px 18px 14px;
      border-bottom: 1px solid rgba(255,255,255,0.075);
      display: flex;
      justify-content: space-between;
      gap: 14px;
      align-items: center;
    }

    .calendar-label {
      font-family: var(--font-display);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.11em;
      text-transform: uppercase;
      color: var(--accent);
    }

    .calendar-title {
      font-family: var(--font-display);
      font-size: 24px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: -0.01em;
      margin-top: 3px;
    }

    .calendar-badge {
      flex-shrink: 0;
      font-family: var(--font-display);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--white);
      background: rgba(255,92,53,0.16);
      border: 1px solid rgba(255,92,53,0.32);
      border-radius: var(--radius-btn);
      padding: 7px 11px;
    }

    .calendar-embed {
      height: min(680px, calc(100svh - 210px));
      min-height: 560px;
      background: var(--white);
      overflow: hidden;
    }

    .calendar-embed iframe {
      display: block;
      width: 100%;
      height: 100%;
      border: 0;
      background: var(--white);
    }

    .calendar-placeholder {
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 28px;
      text-align: center;
      color: #0D0D0D;
      background:
        linear-gradient(135deg, rgba(255,92,53,0.10), rgba(255,255,255,0.95)),
        var(--white);
    }

    .placeholder-inner {
      max-width: 340px;
    }

    .placeholder-icon {
      width: 58px;
      height: 58px;
      border-radius: 18px;
      background: var(--accent);
      color: var(--white);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-display);
      font-size: 30px;
      font-weight: 700;
      margin-bottom: 18px;
    }

    .placeholder-title {
      font-family: var(--font-display);
      font-size: 30px;
      font-weight: 700;
      text-transform: uppercase;
      line-height: 0.95;
      letter-spacing: -0.02em;
      margin-bottom: 12px;
    }

    .placeholder-copy {
      font-size: 14px;
      line-height: 1.55;
      color: rgba(0,0,0,0.58);
    }

    .calendar-note {
      padding: 13px 18px 16px;
      font-size: 12px;
      line-height: 1.45;
      color: rgba(255,255,255,0.42);
    }

    /* FOOTER */
    #site-footer {
      background: var(--black);
      border-top: 1px solid rgba(255,255,255,0.07);
      padding: 48px 24px calc(40px + var(--safe-bottom));
    }

    .footer-logo {
      font-family: var(--font-display);
      font-size: 20px;
      font-weight: 700;
      letter-spacing: 0.04em;
      margin-bottom: 28px;
    }

    .footer-logo span { color: var(--accent); }

    .footer-nav {
      display: flex;
      flex-direction: column;
      gap: 14px;
      margin-bottom: 36px;
    }

    .footer-nav a {
      font-size: 15px;
      color: rgba(255,255,255,0.50);
      transition: color 0.2s;
    }

    .footer-nav a:hover,
    .footer-nav a.active { color: var(--white); }

    .footer-bottom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      border-top: 1px solid rgba(255,255,255,0.06);
      padding-top: 24px;
    }

    .footer-copy { font-size: 12px; color: rgba(255,255,255,0.25); }

    .footer-tagline {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.18);
    }

    .reveal {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.7s var(--ease-out), transform 0.7s var(--ease-out);
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    @media (min-width: 768px) {
      #book-demo {
        padding-left: 48px;
        padding-right: 48px;
      }

      .demo-shell {
        grid-template-columns: minmax(0, 0.92fr) minmax(430px, 1.08fr);
        gap: 44px;
      }

      .calendar-card {
        align-self: center;
      }

      #site-footer {
        padding-left: 48px;
        padding-right: 48px;
      }
    }

    @media (min-width: 1100px) {
      #book-demo {
        display: flex;
        align-items: center;
      }

      .demo-shell {
        gap: 64px;
      }

      .calendar-embed {
        min-height: 620px;
      }
    }

    @media (max-width: 420px) {
      #book-demo {
        padding-left: 18px;
        padding-right: 18px;
      }

      .mini-proof {
        grid-template-columns: 1fr;
      }

      .calendar-card-top {
        align-items: flex-start;
        flex-direction: column;
      }

      .calendar-embed {
        min-height: 520px;
        height: 540px;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .reveal { transition: none; }
    }
  
    /* SHARED NAV / FOOTER TEMPLATE SUPPORT */
    .desktop-nav {
      display: none;
      align-items: center;
      gap: 28px;
      margin-left: auto;
      flex-wrap: nowrap;
    }
    .desktop-nav a {
      font-family: var(--font-display);
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.72);
      white-space: nowrap;
      transition: color 0.2s, background 0.2s;
    }
    .desktop-nav a:hover,
    .desktop-nav a.active { color: var(--white); }
    .desktop-nav-cta {
      background: var(--accent);
      color: var(--white) !important;
      border-radius: var(--radius-btn);
      padding: 12px 22px;
    }
    .desktop-nav-cta:hover { background: var(--accent-dark); }
    @media (min-width: 1024px) {
      #site-header {
        padding-left: clamp(40px, 5vw, 72px);
        padding-right: clamp(40px, 5vw, 72px);
        white-space: nowrap;
      }
      .logo-wrap { flex-shrink: 0; }
      .logo-wrap img { height: 36px; }
      .desktop-nav { display: flex; justify-content: flex-end; }
      .menu-btn, #mobile-nav { display: none; }
      #site-footer {
        padding-left: clamp(40px, 5vw, 72px);
        padding-right: clamp(40px, 5vw, 72px);
      }
      .footer-nav {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 24px;
      }
    }
    @media (max-width: 1140px) and (min-width: 1024px) {
      .desktop-nav { gap: 18px; }
      .desktop-nav a { font-size: 12px; }
      .desktop-nav-cta { padding: 10px 18px; }
    }

  

    .registration-form-placeholder {
      min-height: 620px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      gap: 10px;
      padding: 32px;
      border: 1px dashed rgba(255,255,255,0.20);
      border-radius: 22px;
      background: rgba(255,255,255,0.035);
    }

    .registration-form-placeholder .placeholder-eyebrow {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
    }

    .registration-form-placeholder h2 {
      font-family: var(--font-display);
      font-size: clamp(30px, 5vw, 48px);
      line-height: 0.95;
      text-transform: uppercase;
    }

    .registration-form-placeholder p:last-child {
      max-width: 340px;
      color: rgba(255,255,255,0.62);
      line-height: 1.6;
      font-size: 14px;
    }

    @media (max-width: 767px) {
      .registration-page {
        padding: 0 !important;
        min-height: 100svh;
        background: var(--black) !important;
      }

      .registration-page::before,
      .registration-page::after,
      .registration-page .demo-copy {
        display: none !important;
      }

      .registration-page .demo-shell {
        display: block;
        width: 100%;
        min-height: 100svh;
        margin: 0;
      }

      .registration-page .calendar-card,
      .registration-page .form-card {
        min-height: 100svh;
        width: 100%;
        border: 0 !important;
        border-radius: 0 !important;
        background: var(--black) !important;
        padding: 0 !important;
      }

      .registration-form-placeholder {
        min-height: 100svh;
        border-radius: 0;
        border-left: 0;
        border-right: 0;
      }

      body:has(.registration-page) #site-header,
      
    }

  
    .registration-form-embed {
      width: 100%;
      height: 100%;
      min-height: 760px;
      border-radius: 22px;
      overflow: hidden;
      background: var(--black);
    }

    .registration-form-embed iframe {
      background: var(--black);
    }

    @media (max-width: 767px) {
      .registration-form-embed {
        min-height: 100svh;
        border-radius: 0;
      }

      .registration-form-embed iframe {
        min-height: 100svh !important;
      }
    }

  
    /* ─── REGISTRATION IFRAME DISPLAY FIX ───────────────────────── */
    .registration-page .calendar-card.form-card {
      display: flex !important;
      justify-content: center !important;
      align-items: flex-start !important;
      padding: 0 !important;
      background: transparent !important;
      border: 0 !important;
      box-shadow: none !important;
      overflow: visible !important;
    }

    .registration-form-embed {
      width: min(425px, 100%) !important;
      max-width: 425px !important;
      min-height: 811px !important;
      height: 811px !important;
      border-radius: 24px !important;
      overflow: hidden !important;
      background: #000 !important;
      box-shadow: 0 24px 80px rgba(0,0,0,0.38) !important;
      border: 1px solid rgba(255,255,255,0.12) !important;
    }

    .registration-form-embed iframe {
      width: 425px !important;
      max-width: 100% !important;
      height: 811px !important;
      min-height: 811px !important;
      border: 0 !important;
      display: block !important;
      background: #000 !important;
    }

    @media (min-width: 768px) {
      .registration-page .demo-shell {
        align-items: flex-start !important;
      }

      .registration-page .form-card {
        padding-top: 0 !important;
      }
    }

    @media (max-width: 767px) {
      body:has(.registration-page) #site-header {
        display: flex !important;
      }

      body:has(.registration-page) #mobile-nav {
        display: flex !important;
      }

      

      .registration-page {
        padding-top: calc(var(--header-h, 76px) + var(--safe-top, 0px)) !important;
        min-height: 100svh !important;
        background: var(--black) !important;
      }

      .registration-page::before,
      .registration-page::after,
      .registration-page .demo-copy {
        display: none !important;
      }

      .registration-page .demo-shell {
        display: block !important;
        width: 100% !important;
        min-height: calc(100svh - var(--header-h, 76px) - var(--safe-top, 0px)) !important;
        margin: 0 !important;
      }

      .registration-page .calendar-card,
      .registration-page .form-card {
        width: 100% !important;
        min-height: calc(100svh - var(--header-h, 76px) - var(--safe-top, 0px)) !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: var(--black) !important;
        padding: 0 !important;
        display: block !important;
      }

      .registration-form-embed {
        width: 100% !important;
        max-width: none !important;
        height: calc(100svh - var(--header-h, 76px) - var(--safe-top, 0px)) !important;
        min-height: calc(100svh - var(--header-h, 76px) - var(--safe-top, 0px)) !important;
        border-radius: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
      }

      .registration-form-embed iframe {
        width: 100% !important;
        height: calc(100svh - var(--header-h, 76px) - var(--safe-top, 0px)) !important;
        min-height: calc(100svh - var(--header-h, 76px) - var(--safe-top, 0px)) !important;
      }
    }

  </style>
</head>
<body>
@include('partials.navigation')

<main>
  <section id="book-demo" class="registration-page" aria-labelledby="registration-title">
    <div class="demo-shell">

      <div class="demo-copy">
        <p class="page-eyebrow reveal">Start Free</p>
        <h1 class="demo-title reveal" id="registration-title">
          Build Your<br>PLYRCARD<br><span>Free.</span>
        </h1>

        <p class="demo-lead reveal" style="transition-delay:0.08s">
          Create your free athlete profile and start turning your highlights, stats, story, schedule, and contact details into one clean coach-ready link.
        </p>

        <div class="demo-points" aria-label="What the demo covers">
          <div class="demo-point reveal" style="transition-delay:0.12s">
            <span class="point-number">01</span>
            <div>
              <h2 class="point-title">Create Your Account</h2>
              <p class="point-copy">Start with the essentials so your athlete profile has a clean foundation from day one.</p>
            </div>
          </div>

          <div class="demo-point reveal" style="transition-delay:0.18s">
            <span class="point-number">02</span>
            <div>
              <h2 class="point-title">Add Your Athlete Details</h2>
              <p class="point-copy">Upload your sport, position, contact details, highlights, images, and recruiting information.</p>
            </div>
          </div>

          <div class="demo-point reveal" style="transition-delay:0.24s">
            <span class="point-number">03</span>
            <div>
              <h2 class="point-title">Preview Your Card</h2>
              <p class="point-copy">Once submitted, your PLYRCARD profile can be reviewed and prepared for sharing.</p>
            </div>
          </div>
        </div>

        <div class="mini-proof reveal" style="transition-delay:0.30s" aria-label="PLYRCARD platform metrics">
          <div class="proof-box">
            <div class="proof-num">8.8K+</div>
            <div class="proof-label">Emails Sent</div>
          </div>
          <div class="proof-box">
            <div class="proof-num">10K+</div>
            <div class="proof-label">Total Clicks</div>
          </div>
          <div class="proof-box">
            <div class="proof-num">4.8K+</div>
            <div class="proof-label">Coach Views</div>
          </div>
        </div>
      </div>

      <aside class="calendar-card form-card reveal" style="transition-delay:0.16s" aria-label="Start free registration form">
        <!-- Placeholder here: replace this block with your registration form embed. -->
        <div class="registration-form-embed">
          <iframe
            src="{{ $registrationEmbedUrl }}"
            title="PLYRCARD Player Intake Registration"
            loading="lazy"
            scrolling="yes"
            style="width:425px;max-width:100%;height:811px;min-height:811px;border:none;display:block;background:#000;"
          ></iframe>
        </div>
      </aside>

    </div>
  </section>
</main>

@include('partials.footer')

<script>
(function () {
  'use strict';

  const SLIDE_DUR = 6000;
  const slides = document.querySelectorAll('.hero-slide');
  const rails = document.querySelectorAll('.indicator-rail');
  const hero = document.getElementById('hero-carousel');

  let current = 0;
  let timer = null;
  let paused = false;
  let touchStartX = 0;
  let isDragging = false;

  function startTimer() {
    if (slides.length <= 1) return;
    clearInterval(timer);
    timer = setInterval(() => goTo(current + 1), SLIDE_DUR);
  }

  function goTo(idx) {
    if (!slides.length || !rails.length) return;

    slides[current]?.classList.remove('active');
    rails[current]?.classList.remove('active');

    current = (idx + slides.length) % slides.length;

    slides[current]?.classList.add('active');
    rails[current]?.classList.add('active');

    rails.forEach((rail, i) => {
      const fill = rail.querySelector('.indicator-fill');
      if (!fill) return;

      fill.style.animation = 'none';
      fill.offsetHeight;

      if (i === current) {
        rail.classList.add('active');
        fill.style.animation = `railFill ${SLIDE_DUR}ms linear forwards`;
      } else {
        rail.classList.remove('active');
      }
    });

    if (!paused) startTimer();
  }

  if (slides.length && rails.length) {
    const firstFill = rails[0]?.querySelector('.indicator-fill');
    if (firstFill) firstFill.style.animation = `railFill ${SLIDE_DUR}ms linear forwards`;

    startTimer();

    const prevBtn = document.getElementById('hero-prev');
    const nextBtn = document.getElementById('hero-next');

    if (prevBtn) prevBtn.addEventListener('click', () => { paused = true; goTo(current - 1); });
    if (nextBtn) nextBtn.addEventListener('click', () => { paused = true; goTo(current + 1); });

    rails.forEach(rail => {
      rail.addEventListener('click', () => {
        paused = true;
        goTo(Number(rail.dataset.slide || 0));
      });

      rail.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          paused = true;
          goTo(Number(rail.dataset.slide || 0));
        }
      });
    });
  }

  if (hero) {
    hero.addEventListener('touchstart', e => {
      touchStartX = e.touches[0].clientX;
      isDragging = false;
    }, { passive: true });

    hero.addEventListener('touchmove', e => {
      if (Math.abs(e.touches[0].clientX - touchStartX) > 10) isDragging = true;
    }, { passive: true });

    hero.addEventListener('touchend', e => {
      if (!isDragging) return;

      const dx = e.changedTouches[0].clientX - touchStartX;
      if (Math.abs(dx) > 40) {
        paused = true;
        goTo(dx < 0 ? current + 1 : current - 1);
      }

      setTimeout(() => {
        paused = false;
        startTimer();
      }, 10000);
    });
  }

  const header = document.getElementById('site-header');

  if (header) {
    const pageHero = document.getElementById('page-hero');

    const onScroll = () => {
      header.classList.toggle('scrolled', window.scrollY > 24);
      if (pageHero) {
        header.classList.toggle('over-hero', window.scrollY < pageHero.offsetHeight - 80);
      } else {
        header.classList.toggle('over-hero', window.scrollY < 36);
      }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  const menuBtn = document.getElementById('menu-btn');
  const mobileNav = document.getElementById('mobile-nav');

  if (menuBtn && mobileNav) {
    menuBtn.addEventListener('click', () => {
      const open = menuBtn.classList.toggle('open');
      mobileNav.classList.toggle('open', open);
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });

    document.querySelectorAll('[data-nav]').forEach(link => {
      link.addEventListener('click', () => {
        menuBtn.classList.remove('open');
        mobileNav.classList.remove('open');
        menuBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      });
    });
  }

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.dataset.tab;
      if (!key) return;

      document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });

      document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));

      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');

      const targetPanel = document.getElementById('tab-' + key);
      if (targetPanel) targetPanel.classList.add('active');
    });
  });

  document.querySelectorAll('.faq-question').forEach(question => {
    const item = question.closest('.faq-item');
    const answer = item ? item.querySelector('.faq-answer') : null;
    if (!item || !answer) return;

    const toggle = () => {
      const open = item.classList.toggle('open');
      question.setAttribute('aria-expanded', open ? 'true' : 'false');
      answer.style.maxHeight = open ? answer.scrollHeight + 'px' : '0';
    };

    question.addEventListener('click', toggle);
    question.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggle();
      }
    });
  });

  const billingToggle = document.getElementById('billing-toggle');
  const labelMonthly = document.getElementById('label-monthly');
  const labelAnnual = document.getElementById('label-annual');

  if (billingToggle && labelMonthly && labelAnnual) {
    let isAnnual = false;

    const setBilling = annual => {
      isAnnual = annual;
      billingToggle.classList.toggle('annual', annual);
      billingToggle.setAttribute('aria-checked', annual ? 'true' : 'false');
      labelMonthly.classList.toggle('active', !annual);
      labelAnnual.classList.toggle('active', annual);
      document.body.classList.toggle('annual', annual);

      document.querySelectorAll('.monthly-price').forEach(el => {
        el.style.display = annual ? 'none' : (el.tagName === 'DIV' ? 'flex' : 'inline');
      });

      document.querySelectorAll('.annual-price').forEach(el => {
        el.style.display = annual ? (el.tagName === 'DIV' ? 'flex' : 'inline') : 'none';
      });
    };

    billingToggle.addEventListener('click', () => setBilling(!isAnnual));
    billingToggle.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        setBilling(!isAnnual);
      }
    });

    labelMonthly.addEventListener('click', () => setBilling(false));
    labelAnnual.addEventListener('click', () => setBilling(true));
  }

  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

    const counterObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;

        const el = entry.target;
        const target = Number(el.dataset.count || 0);
        const suffix = el.dataset.suffix || '';
        const duration = 1200;
        const step = 16;
        const increment = target / (duration / step);
        let count = 0;

        const tick = setInterval(() => {
          count += increment;
          if (count >= target) {
            count = target;
            clearInterval(tick);
          }
          el.textContent = Math.floor(count).toLocaleString() + suffix;
        }, step);

        counterObserver.unobserve(el);
      });
    }, { threshold: 0.5 });

    document.querySelectorAll('[data-count]').forEach(el => counterObserver.observe(el));
  } else {
    document.querySelectorAll('.reveal').forEach(el => el.classList.add('visible'));
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    clearInterval(timer);
    rails.forEach(rail => {
      const fill = rail.querySelector('.indicator-fill');
      if (fill) {
        fill.style.animation = 'none';
        fill.style.width = '100%';
      }
    });
  }
})();
</script>

</body>
</html>