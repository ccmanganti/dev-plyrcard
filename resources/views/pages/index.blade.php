@php
    $activePage = 'home';
@endphp
@include('partials.images')
<!DOCTYPE html>
<html lang="en">
<head>
	
	<script src="https://widgets.leadconnectorhq.com/loader.js" data-resources-url="https://widgets.leadconnectorhq.com/chat-widget/loader.js" data-widget-id="6941fea74ca18223c7de491d"></script>

  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="description" content="PLYRCARD — Your Game. Your Brand. One Card. The premium recruiting card for serious athletes." />
  <title>PLYRCARD — Own Your Journey</title>

  <!-- Antonio + Inter from Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <style>
    /* ─── RESET & BASE ────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --accent:      #FF5C35;
      --accent-dark: #E04520;
      --black:       #0D0D0D;
      --white:       #FFFFFF;
      --gray-muted:  rgba(255,255,255,0.55);
      --font-display: 'Antonio', sans-serif;
      --font-body:    'Inter', sans-serif;
      --safe-top:    env(safe-area-inset-top, 0px);
      --safe-bottom: env(safe-area-inset-bottom, 0px);
      --header-h:    60px;
      --radius-card: 20px;
      --radius-btn:  9999px;
      --ease-out:    cubic-bezier(0.16, 1, 0.3, 1);
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

    /* ─── HEADER ──────────────────────────────────────────────────── */
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
      transition: background 0.3s var(--ease-out), backdrop-filter 0.3s;
      background: transparent;
    }
    #site-header.scrolled {
      background: rgba(13,13,13,0.92);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
    }

    /* Logo */
    .logo-wrap {
      display: flex;
      align-items: center;
      gap: 0;
      height: 32px;
    }
    .logo-wrap img {
      height: 50px;
      width: auto;
      object-fit: contain;
     /* filter: brightness(0) invert(1);
       Force white logo on dark/transparent header */
    }
    .logo-text {
      font-family: var(--font-display);
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 0.04em;
      line-height: 1;
    }
    .logo-text span { color: var(--accent); }

    /* Hamburger */
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

    /* Mobile Nav Drawer */
    #mobile-nav {
      position: fixed;
      inset: 0;
      z-index: 99;
      background: var(--black);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 36px;
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
    .nav-link:hover { color: var(--accent); }
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
      margin-top: 12px;
    }

    /* ─── HERO CAROUSEL ───────────────────────────────────────────── */
    #hero-carousel {
      position: relative;
      width: 100%;
      height: 100svh;
      min-height: 640px;
      overflow: hidden;
      background: var(--black);
    }

    /* Slides */
    .hero-slides {
      position: absolute;
      inset: 0;
    }
    .hero-slide {
      position: absolute;
      inset: 0;
      opacity: 0;
      transition: opacity 0.9s var(--ease-out);
    }
    .hero-slide.active { opacity: 1; }

    /* Background image */
    .slide-bg {
      position: absolute;
      inset: 0;
      background-size: cover;
      background-position: center top;
      transform: scale(1.06);
      transition: transform 7s linear;
    }
    .hero-slide.active .slide-bg { transform: scale(1); }

    /* Ken Burns crop variants */
    .slide-bg.pos-top    { background-position: center 15%; }
    .slide-bg.pos-center { background-position: center center; }
    .slide-bg.pos-bottom { background-position: center 80%; }

    /* Cinematic overlays — Nike style */
    .slide-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(
        to bottom,
        rgba(0,0,0,0.08) 0%,
        rgba(0,0,0,0.04) 30%,
        rgba(0,0,0,0.30) 55%,
        rgba(0,0,0,0.72) 78%,
        rgba(0,0,0,0.88) 100%
      );
    }

    /* Hero content block */
    .hero-content {
      position: absolute;
      bottom: calc(80px + var(--safe-bottom));
      left: 0; right: 0;
      padding: 0 24px;
      transform: translateY(12px);
      opacity: 0;
      transition: transform 0.8s var(--ease-out) 0.25s, opacity 0.8s var(--ease-out) 0.25s;
    }
    .hero-slide.active .hero-content {
      transform: translateY(0);
      opacity: 1;
    }

    .hero-eyebrow {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 8px;
    }
    .hero-headline {
      font-family: var(--font-display);
      font-size: clamp(46px, 14vw, 64px);
      font-weight: 700;
      line-height: 0.92;
      letter-spacing: -0.02em;
      text-transform: uppercase;
      color: var(--white);
      margin-bottom: 12px;
    }
    .hero-sub {
      font-family: var(--font-body);
      font-size: 15px;
      font-weight: 400;
      line-height: 1.45;
      color: rgba(255,255,255,0.82);
      max-width: 280px;
      margin-bottom: 24px;
    }
    .hero-cta {
      display: inline-flex;
      align-items: center;
      background: var(--white);
      color: var(--black);
      font-family: var(--font-display);
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      border-radius: var(--radius-btn);
      padding: 15px 28px;
      transition: background 0.2s, color 0.2s;
    }
    .hero-cta:hover {
      background: var(--accent);
      color: var(--white);
    }
    .hero-cta.accent {
      background: var(--accent);
      color: var(--white);
    }
    .hero-cta.accent:hover {
      background: var(--accent-dark);
    }

    /* Slide indicator rail */
    .hero-indicators {
      position: absolute;
      bottom: calc(32px + var(--safe-bottom));
      left: 24px;
      display: flex;
      gap: 6px;
      align-items: center;
    }
    .indicator-rail {
      width: 32px; height: 3px;
      background: rgba(255,255,255,0.25);
      border-radius: 9999px;
      overflow: hidden;
      cursor: pointer;
    }
    .indicator-rail.active { width: 56px; }
    .indicator-fill {
      height: 100%;
      width: 0%;
      background: var(--white);
      border-radius: 9999px;
      transition: none;
    }
    .indicator-rail.active .indicator-fill {
      animation: railFill var(--slide-dur, 6s) linear forwards;
    }
    @keyframes railFill { from { width: 0% } to { width: 100% } }

    /* Slide nav arrows (hidden on small screens, show on 480+) */
    .hero-arrow {
      position: absolute;
      top: 50%; transform: translateY(-50%);
      width: 48px; height: 48px;
      background: rgba(255,255,255,0.12);
      border: none;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(8px);
      transition: background 0.2s;
      display: none; /* hidden on mobile */
    }
    @media(min-width: 480px) { .hero-arrow { display: flex; } }
    .hero-arrow:hover { background: rgba(255,255,255,0.24); }
    .hero-arrow svg { width: 18px; height: 18px; fill: none; stroke: var(--white); stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
    .hero-arrow.prev { left: 16px; }
    .hero-arrow.next { right: 16px; }

    /* ─── SHARED SECTION STYLES ───────────────────────────────────── */
    section {
      padding: 64px 24px;
    }
    .section-eyebrow {
      font-family: var(--font-display);
      font-size: 20px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 10px;
    }
    .section-title {
      font-family: var(--font-display);
      font-size: clamp(34px, 9vw, 48px);
      font-weight: 700;
      line-height: 0.95;
      letter-spacing: -0.02em;
      text-transform: uppercase;
      margin-bottom: 16px;
    }
    .section-body {
      font-family: var(--font-body);
      font-size: 16px;
      line-height: 1.6;
      color: rgba(255,255,255,0.70);
      max-width: 360px;
    }
    .section-body.dark { color: rgba(0,0,0,0.62); }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      font-family: var(--font-display);
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      border-radius: var(--radius-btn);
      padding: 15px 28px;
      border: none;
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
    }
    .btn-white { background: var(--white); color: var(--black); }
    .btn-white:hover { background: var(--accent); color: var(--white); }
    .btn-accent { background: var(--accent); color: var(--white); }
    .btn-accent:hover { background: var(--accent-dark); }
    .btn-outline-white {
      background: transparent;
      color: var(--white);
      border: 1.5px solid rgba(255,255,255,0.40);
    }
    .btn-outline-white:hover {
      border-color: var(--white);
      background: rgba(255,255,255,0.08);
    }
    .btn-outline-dark {
      background: transparent;
      color: var(--black);
      border: 1.5px solid rgba(0,0,0,0.30);
    }
    .btn-outline-dark:hover {
      border-color: var(--black);
      background: rgba(0,0,0,0.06);
    }
    .btn-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 28px;
    }

    /* ─── PROOF STRIP ─────────────────────────────────────────────── */
    #proof-strip {
      padding: 48px 24px;
      background: var(--white);
      color: var(--black);
    }
    .proof-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 32px 16px;
    }
    .proof-item {}
    .proof-number {
      font-family: var(--font-display);
      font-size: 38px;
      font-weight: 700;
      line-height: 1;
      letter-spacing: -0.02em;
      color: var(--black);
    }
    .proof-label {
      font-family: var(--font-body);
      font-size: 13px;
      font-weight: 500;
      color: rgba(0,0,0,0.50);
      margin-top: 4px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .proof-strip-eyebrow {
      font-family: var(--font-display);
      font-size: 18px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 32px;
    }

    /* ─── WHAT IS SECTION ─────────────────────────────────────────── */
    #what-is {
      background: var(--black);
    }
    .phone-mockup-wrap {
      margin: 40px auto 0;
      max-width: 280px;
      position: relative;
    }
    .phone-shell {
      width: 100%;
      aspect-ratio: 9/19;
      background: #1A1A1A;
      border-radius: 36px;
      border: 2px solid rgba(255,255,255,0.10);
      overflow: hidden;
      position: relative;
      box-shadow: 0 40px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
    }
    .phone-screen-top {
      background: var(--black);
      padding: 12px 16px 10px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .phone-logo-text {
      font-family: var(--font-display);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.06em;
    }
    .phone-logo-text span { color: var(--accent); }
    .phone-avatar {
      width: 28px; height: 28px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #FF9A3C);
    }
    .phone-hero-img {
      width: 100%;
      height: 140px;
      background: linear-gradient(135deg, #1E1E1E, #2A2A2A);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-display);
      font-size: 28px;
      font-weight: 700;
      color: rgba(255,255,255,0.08);
      text-transform: uppercase;
      letter-spacing: 0.04em;
      overflow: hidden;
      position: relative;
    }
    .phone-hero-img::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.6) 100%);
    }
    .athlete-icon {
      font-size: 60px;
      opacity: 0.25;
    }
    .phone-name-bar {
      background: var(--black);
      padding: 12px 16px;
    }
    .phone-athlete-name {
      font-family: var(--font-display);
      font-size: 16px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.02em;
    }
    .phone-athlete-sport {
      font-size: 11px;
      color: rgba(255,255,255,0.45);
      margin-top: 2px;
    }
    .phone-stats-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      background: #111;
      border-top: 1px solid rgba(255,255,255,0.06);
    }
    .phone-stat-cell {
      padding: 10px 12px;
      border-right: 1px solid rgba(255,255,255,0.06);
      text-align: center;
    }
    .phone-stat-cell:last-child { border-right: none; }
    .pstat-num {
      font-family: var(--font-display);
      font-size: 16px;
      font-weight: 700;
      color: var(--accent);
    }
    .pstat-label {
      font-size: 9px;
      color: rgba(255,255,255,0.35);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-top: 2px;
    }
    .phone-links {
      padding: 10px 16px 14px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .phone-link-item {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 8px;
      padding: 8px 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .phone-link-dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: var(--accent);
      flex-shrink: 0;
    }
    .phone-link-text {
      font-size: 11px;
      color: rgba(255,255,255,0.55);
    }
    .phone-notch {
      position: absolute;
      top: 0; left: 50%;
      transform: translateX(-50%);
      width: 90px; height: 26px;
      background: var(--black);
      border-radius: 0 0 16px 16px;
    }

    /* ─── WHY IT MATTERS ──────────────────────────────────────────── */
    #why-it-matters {
      background: #111111;
      padding: 72px 24px;
    }
    .big-number {
      font-family: var(--font-display);
      font-size: clamp(80px, 24vw, 120px);
      font-weight: 700;
      line-height: 1;
      letter-spacing: -0.04em;
      color: rgba(255,255,255,0.27);
      margin-bottom: -16px;
      display: block;
    }

    /* ─── HOW IT WORKS ────────────────────────────────────────────── */
    #how-it-works {
      background: var(--white);
      color: var(--black);
    }
    #how-it-works .section-title { color: var(--black); }
    #how-it-works .section-eyebrow { color: var(--accent); }
    .steps-list {
      display: flex;
      flex-direction: column;
      gap: 0;
      margin-top: 40px;
    }
    .step-item {
      display: flex;
      gap: 20px;
      padding: 24px 0;
      border-bottom: 1px solid rgba(0,0,0,0.08);
    }
    .step-item:last-child { border-bottom: none; }
    .step-num {
      font-family: var(--font-display);
      font-size: 48px;
      font-weight: 700;
      line-height: 1;
      letter-spacing: -0.03em;
      color: var(--accent);
      flex-shrink: 0;
      width: 56px;
    }
    .step-text {}
    .step-title {
      font-family: var(--font-display);
      font-size: 20px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: -0.01em;
      color: var(--black);
      margin-bottom: 4px;
    }
    .step-copy {
      font-size: 14px;
      color: rgba(0,0,0,0.55);
      line-height: 1.5;
    }

    /* ─── SPORT STRIP (between sections) ─────────────────────────── */
    #sport-strip {
      height: 70vw;
      min-height: 260px;
      max-height: 640px;
      background-image: url('/images/PLYRCARD-SITE-READY.jpg');
      background-size: cover;
      background-position: center 30%;
      position: relative;
      overflow: hidden;
    }
    #sport-strip::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.6));
    }
    .sport-strip-text {
      position: absolute;
      inset: 0;
      z-index: 1;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 28px 24px;
    }
    .sport-strip-headline {
      font-family: var(--font-display);
      font-size: clamp(30px, 9vw, 48px);
      font-weight: 700;
      text-transform: uppercase;
      line-height: 0.95;
      letter-spacing: -0.02em;
    }

    /* ─── TESTIMONIALS ────────────────────────────────────────────── */
    #testimonials {
      background: var(--black);
    }
    .tab-row {
      display: flex;
      gap: 8px;
      margin-bottom: 32px;
    }
    .tab-btn {
      font-family: var(--font-display);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      background: rgba(255,255,255,0.06);
      color: rgba(255,255,255,0.50);
      border: none;
      border-radius: var(--radius-btn);
      padding: 10px 18px;
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
    }
    .tab-btn.active {
      background: var(--accent);
      color: var(--white);
    }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    .testimonial-card {
      background: #171717;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: var(--radius-card);
      padding: 28px 24px;
    }
    .testimonial-quote {
      font-family: var(--font-body);
      font-size: 18px;
      line-height: 1.55;
      color: rgba(255,255,255,0.90);
      margin-bottom: 20px;
      font-style: italic;
    }
    .testimonial-quote::before { content: '\201C'; }
    .testimonial-quote::after  { content: '\201D'; }
    .testimonial-role {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.10em;
      text-transform: uppercase;
      color: var(--accent);
    }
    .testimonial-name {
      font-family: var(--font-body);
      font-size: 14px;
      color: rgba(255,255,255,0.45);
      margin-top: 2px;
    }

    /* ─── TRUST LOGOS ─────────────────────────────────────────────── */
    #trust-logos {
      background: #111111;
      padding: 48px 24px;
    }
    .trust-eyebrow {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.30);
      text-align: center;
      margin-bottom: 28px;
    }
    .trust-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px 16px;
      align-items: center;
    }
    .trust-logo-box {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 10px;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .trust-logo-box span {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.25);
    }

    /* ─── FINAL CTA ───────────────────────────────────────────────── */
    #final-cta {
      background: var(--accent);
      padding: 80px 24px calc(80px + var(--safe-bottom));
      text-align: center;
    }
    #final-cta .section-eyebrow { color: rgba(255,255,255,0.70); }
    #final-cta .section-title { color: var(--white); text-align: center; }
    .cta-body {
      font-size: 16px;
      line-height: 1.55;
      color: rgba(255,255,255,0.80);
      margin-top: 12px;
    }
    #final-cta .btn-row { justify-content: center; }
    .btn-white-cta {
      background: var(--white);
      color: var(--accent);
      font-family: var(--font-display);
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      border-radius: var(--radius-btn);
      padding: 16px 32px;
      border: none;
      cursor: pointer;
    }
    .btn-outline-white-cta {
      background: transparent;
      color: var(--white);
      font-family: var(--font-display);
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      border-radius: var(--radius-btn);
      padding: 16px 32px;
      border: 2px solid rgba(255,255,255,0.50);
      cursor: pointer;
    }
    .fine-print {
      font-size: 12px;
      color: rgba(255,255,255,0.55);
      margin-top: 20px;
    }

    /* ─── FOOTER ──────────────────────────────────────────────────── */
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
      font-family: var(--font-body);
      font-size: 15px;
      color: rgba(255,255,255,0.55);
      transition: color 0.2s;
    }
    .footer-nav a:hover { color: var(--white); }
    .footer-bottom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      border-top: 1px solid rgba(255,255,255,0.06);
      padding-top: 24px;
    }
    .footer-copy {
      font-size: 12px;
      color: rgba(255,255,255,0.28);
    }
    .footer-tagline {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.20);
    }

    /* ─── FADE-IN ANIMATION ───────────────────────────────────────── */
    .reveal {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.7s var(--ease-out), transform 0.7s var(--ease-out);
    }
    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    /* ─── RESPONSIVE ENHANCEMENTS ─────────────────────────────────── */
    @media (min-width: 430px) {
      .hero-headline { font-size: 68px; }
      .proof-grid { grid-template-columns: repeat(4, 1fr); gap: 16px; }
      .proof-number { font-size: 32px; }
    }
    @media (min-width: 768px) {
      .hero-headline { font-size: 80px; }
      .hero-content  { max-width: 500px; padding: 0 48px; }
      section        { padding: 80px 48px; }
      .what-is-inner {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 48px;
        align-items: center;
      }
      .phone-mockup-wrap { margin: 0; }
    }



    /* ─── DESKTOP LAYOUT ADDITIONS ────────────────────────────────── */
    .desktop-nav { display: none; }

    @media (min-width: 1024px) {
      :root { --header-h: 74px; }

      #site-header {
        padding-left: clamp(40px, 5vw, 72px);
        padding-right: clamp(40px, 5vw, 72px);
      }

      .logo-wrap img { height: 50px; }
      .logo-text { font-size: 26px; }
      .menu-btn { display: none; }

      .desktop-nav {
        display: flex;
        align-items: center;
        gap: 28px;
      }

      .desktop-nav a {
        font-family: var(--font-display);
        font-size: 20px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.72);
        transition: color 0.2s, background 0.2s, border-color 0.2s;
      }

      .desktop-nav a:hover { color: var(--white); }

      .desktop-nav-cta {
        background: var(--accent);
        color: var(--white) !important;
        border-radius: var(--radius-btn);
        padding: 12px 22px;
      }

      .desktop-nav-cta:hover { background: var(--accent-dark); }

      #hero-carousel {
        height: 100vh;
        min-height: 760px;
      }

      .slide-bg { background-position: center center; }
      .slide-bg.pos-top { background-position: center 22%; }
      .slide-bg.pos-center { background-position: center center; }
      .slide-bg.pos-bottom { background-position: center 70%; }

      .slide-overlay {
        background:
          radial-gradient(circle at 18% 44%, rgba(0,0,0,0.10), transparent 30%),
          linear-gradient(to right, rgba(0,0,0,0.76) 0%, rgba(0,0,0,0.46) 38%, rgba(0,0,0,0.08) 72%, rgba(0,0,0,0.28) 100%),
          linear-gradient(to bottom, rgba(0,0,0,0.18) 0%, rgba(0,0,0,0.10) 48%, rgba(0,0,0,0.62) 100%);
      }

      .hero-content {
        top: 50%;
        bottom: auto;
        transform: translateY(calc(-50% + 18px));
        left: clamp(56px, 7vw, 112px);
        right: auto;
        width: min(620px, 45vw);
        padding: 0;
      }

      .hero-slide.active .hero-content { transform: translateY(-50%); }
      .hero-eyebrow { font-size: 13px; margin-bottom: 14px; }
      .hero-headline { font-size: clamp(82px, 7.6vw, 132px); line-height: 120px; margin-bottom: 22px;max-width: 900px; }
      .hero-sub { font-size: 19px; line-height: 1.55; max-width: 430px; margin-bottom: 34px; }
      .hero-cta { font-size: 15px; padding: 17px 34px; }

      .hero-indicators {
        left: clamp(56px, 7vw, 112px);
        bottom: 54px;
        gap: 10px;
      }

      .indicator-rail { width: 48px; height: 4px; }
      .indicator-rail.active { width: 86px; }
      .hero-arrow { width: 54px; height: 54px; }
      .hero-arrow.prev { left: 28px; }
      .hero-arrow.next { right: 28px; }

      section { padding: 104px clamp(56px, 7vw, 112px); }
      .section-eyebrow { font-size: 20px; margin-bottom: 14px; }
      .section-title { font-size: clamp(54px, 5vw, 82px); max-width: 780px; }
      .section-body { font-size: 18px; max-width: 520px; }

      #proof-strip { padding: 58px clamp(56px, 7vw, 112px); }
      .proof-grid { max-width: 1120px; margin: 0 auto; gap: 34px; }
      .proof-strip-eyebrow { max-width: 1120px; margin-left: auto; margin-right: auto; }
      .proof-number { font-size: 54px; }
      .proof-label { font-size: 13px; }

      #what-is { padding-top: 120px; padding-bottom: 120px; }
      .what-is-inner {
        max-width: 1160px;
        margin: 0 auto;
        grid-template-columns: minmax(0, 1fr) minmax(340px, 440px);
        gap: 96px;
      }
      .phone-mockup-wrap { max-width: 390px; justify-self: end; }
      .phone-shell { border-radius: 44px; }
      .phone-hero-img { height: 210px; }
      .phone-athlete-name { font-size: 22px; }
      .phone-athlete-sport { font-size: 13px; }
      .pstat-num { font-size: 22px; }
      .pstat-label { font-size: 10px; }
      .phone-link-item { padding: 12px 14px; }
      .phone-link-text { font-size: 13px; }

      #why-it-matters {
        padding: 124px clamp(56px, 7vw, 112px);
        background:
          linear-gradient(to right, rgba(17,17,17,1), rgba(17,17,17,0.92)),
          #111111;
      }
      #why-it-matters > * { max-width: 760px; }
      .big-number { font-size: clamp(150px, 15vw, 230px); margin-bottom: -38px; }

      #sport-strip {
        height: 460px;
        max-height: none;
        background-position: center 38%;
      }
      .sport-strip-text { padding: 52px clamp(56px, 7vw, 112px); }
      .sport-strip-headline { font-size: clamp(62px, 6vw, 92px); }

      #how-it-works {
        display: grid;
        grid-template-columns: minmax(320px, 0.82fr) minmax(420px, 1fr);
        gap: 80px;
        align-items: start;
      }
      #how-it-works > .section-eyebrow,
      #how-it-works > .section-title,
      #how-it-works > .section-body,
      #how-it-works > .btn-row { grid-column: 1; }
      #how-it-works .steps-list {
        grid-column: 2;
        grid-row: 1 / span 5;
        margin-top: 0;
      }
      .step-item { padding: 32px 0; gap: 28px; }
      .step-num { font-size: 68px; width: 78px; }
      .step-title { font-size: 27px; }
      .step-copy { font-size: 16px; max-width: 470px; }

      #testimonials {
        display: grid;
        grid-template-columns: minmax(320px, 0.78fr) minmax(420px, 1fr);
        gap: 70px;
        align-items: start;
      }
      #testimonials > .section-eyebrow,
      #testimonials > .section-title,
      #testimonials > .tab-row { grid-column: 1; }
      #testimonials > .tab-panel { grid-column: 2; grid-row: 1 / span 4; align-self: center; }
      .tab-row { margin-bottom: 0; }
      .testimonial-card { padding: 44px 42px; }
      .testimonial-quote { font-size: 24px; line-height: 1.55; }

      #trust-logos { padding: 66px clamp(56px, 7vw, 112px); }
      .trust-grid {
        max-width: 920px;
        margin: 0 auto;
        grid-template-columns: repeat(6, 1fr);
      }
      .trust-logo-box { height: 64px; }

      #final-cta { padding: 108px 48px calc(108px + var(--safe-bottom)); }
      #final-cta .section-title { margin-left: auto; margin-right: auto; }
      .cta-body { font-size: 19px; }
      .btn-white-cta,
      .btn-outline-white-cta { font-size: 15px; padding: 18px 36px; }

      #site-footer { padding: 58px clamp(56px, 7vw, 112px) calc(48px + var(--safe-bottom)); }
      .footer-nav {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 24px;
      }
    }

    @media (min-width: 1280px) {
      .hero-content { left: max(112px, calc((100vw - 1220px) / 2)); }
      .hero-indicators { left: max(112px, calc((100vw - 1220px) / 2)); }
      #how-it-works,
      #testimonials { grid-template-columns: minmax(380px, 0.8fr) minmax(520px, 1fr); }
    }

    /* ─── PREFERS REDUCED MOTION ──────────────────────────────────── */
    @media (prefers-reduced-motion: reduce) {
      .hero-slide,
      .hero-content,
      .slide-bg,
      .reveal {
        transition: none;
        animation: none;
      }
      .indicator-fill { animation: none; width: 100%; }
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
      font-size: 20px;
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
      .logo-wrap img { height: 50px; }
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

  </style>
</head>
<body>
<!-- ════════════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════════════ -->
@include('partials.navigation')


<!-- ════════════════════════════════════════════════════════
     HERO CAROUSEL
═══════════════════════════════════════════════════════════ -->
<section id="hero-carousel" aria-label="Hero — PLYRCARD brand campaign">

  <div class="hero-slides" aria-live="polite">

    <!-- Slide 1 — Soccer -->
    <article class="hero-slide active" aria-label="Own Your Journey">
      <div class="slide-bg pos-top" style="background-image: url('/images/PLYRCARD-SITE.jpg');" role="img" aria-label="Soccer athlete in action"></div>
      <div class="slide-overlay"></div>
      <div class="hero-content">
        <p class="hero-eyebrow">For Serious Athletes</p>
        <h1 class="hero-headline">Own Your<br>Journey</h1>
        <p class="hero-sub">Your game. Your brand. Your story — all in one place.</p>
        <a href="https://plyrcard.com/player-intake-app" class="hero-cta">Create Free Account</a>
      </div>
    </article>

    <!-- Slide 2 — Football -->
    <article class="hero-slide" aria-label="Control the Controllables">
      <div class="slide-bg pos-center" style="background-image: url('/images/PLYRCARD-SITE-SOCCER.jpg');" role="img" aria-label="Football player holding football"></div>
      <div class="slide-overlay"></div>
      <div class="hero-content">
        <p class="hero-eyebrow">Recruiting, Simplified</p>
        <h1 class="hero-headline">Control the<br>Controllables</h1>
        <p class="hero-sub">Give coaches a cleaner way to see your work.</p>
        <a href="https://plyrcard.com/player-intake-app" class="hero-cta accent">Build Your Card</a>
      </div>
    </article>

    <!-- Slide 3 — Basketball -->
    <article class="hero-slide" aria-label="More Than Just an Email">
      <div class="slide-bg pos-top" style="background-image: url('/images/PLYRCARD-SITE-FOOTBALL.jpg');" role="img" aria-label="Basketball players in action"></div>
      <div class="slide-overlay"></div>
      <div class="hero-content">
        <p class="hero-eyebrow">Be Seen. Be Remembered.</p>
        <h1 class="hero-headline">More Than<br>Just an Email</h1>
        <p class="hero-sub">Turn outreach into something coaches actually remember.</p>
        <a href="https://plyrcard.com/player-intake-app" class="hero-cta">See How It Works</a>
      </div>
    </article>

    <!-- Slide 4 — Track / Running -->
    <article class="hero-slide" aria-label="Know Who's Watching">
      <div class="slide-bg pos-center" style="background-image: url('/images/PLYRCARD-SITE-VOLLEYBALL.jpg');" role="img" aria-label="Athlete in sprint starting position"></div>
      <div class="slide-overlay"></div>
      <div class="hero-content">
        <p class="hero-eyebrow">Real Visibility. Real Signals.</p>
        <h1 class="hero-headline">Know Who's<br>Watching</h1>
        <p class="hero-sub">Track opens, clicks, and real engagement from coaches.</p>
        <a href="https://plyrcard.com/player-intake-app" class="hero-cta">Track Engagement</a>
      </div>
    </article>

    <!-- Slide 5 — Volleyball -->
    <article class="hero-slide" aria-label="Your Full Story, One Link">
      <div class="slide-bg pos-top" style="background-image: url('/images/PLYRCARD-SITE-TEAM.jpg');" role="img" aria-label="Volleyball player spiking the ball"></div>
      <div class="slide-overlay"></div>
      <div class="hero-content">
        <p class="hero-eyebrow">One Link. More Impact.</p>
        <h1 class="hero-headline">Your Full<br>Story.</h1>
        <p class="hero-sub">Highlights, stats, schedule, and personality — all in one card.</p>
        <a href="https://plyrcard.com/player-intake-app/" class="hero-cta accent">Create Your Card</a>
      </div>
    </article>

  </div><!-- /hero-slides -->

  <!-- Prev / Next arrows -->
  <button class="hero-arrow prev" id="hero-prev" aria-label="Previous slide">
    <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
  </button>
  <button class="hero-arrow next" id="hero-next" aria-label="Next slide">
    <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
  </button>

  <!-- Slide indicator rails -->
  <div class="hero-indicators" role="tablist" aria-label="Slide navigation">
    <div class="indicator-rail active" role="tab" aria-selected="true" aria-label="Slide 1" data-slide="0" tabindex="0">
      <div class="indicator-fill"></div>
    </div>
    <div class="indicator-rail" role="tab" aria-selected="false" aria-label="Slide 2" data-slide="1" tabindex="0">
      <div class="indicator-fill"></div>
    </div>
    <div class="indicator-rail" role="tab" aria-selected="false" aria-label="Slide 3" data-slide="2" tabindex="0">
      <div class="indicator-fill"></div>
    </div>
    <div class="indicator-rail" role="tab" aria-selected="false" aria-label="Slide 4" data-slide="3" tabindex="0">
      <div class="indicator-fill"></div>
    </div>
    <div class="indicator-rail" role="tab" aria-selected="false" aria-label="Slide 5" data-slide="4" tabindex="0">
      <div class="indicator-fill"></div>
    </div>
  </div>

</section>
<!-- /hero-carousel -->


<!-- ════════════════════════════════════════════════════════
     PROOF STRIP
═══════════════════════════════════════════════════════════ -->
<section id="proof-strip" aria-label="Key metrics">
  <p class="proof-strip-eyebrow">Trusted by Athletes on the Move</p>
  <div class="proof-grid">
    <div class="proof-item reveal">
      <div class="proof-number" data-count="8800" data-suffix="+">0+</div>
      <div class="proof-label">Emails Sent</div>
    </div>
    <div class="proof-item reveal" style="transition-delay:0.08s">
      <div class="proof-number" data-count="10235" data-suffix="+">0+</div>
      <div class="proof-label">Total Clicks</div>
    </div>
    <div class="proof-item reveal" style="transition-delay:0.16s">
      <div class="proof-number" data-count="6852" data-suffix="+">0+</div>
      <div class="proof-label">Profile Views</div>
    </div>
    <div class="proof-item reveal" style="transition-delay:0.24s">
      <div class="proof-number" data-count="4824" data-suffix="+">0+</div>
      <div class="proof-label">Coach Views</div>
    </div>
  </div>
</section>


<!-- ════════════════════════════════════════════════════════
     WHAT IS PLYRCARD
═══════════════════════════════════════════════════════════ -->
<section id="what-is">
  <div class="what-is-inner">
    <div class="what-is-copy">
      <p class="section-eyebrow reveal">What Is PLYRCARD</p>
      <h2 class="section-title reveal">Your Full<br>Story.<br>One Link.</h2>
      <p class="section-body reveal" style="transition-delay:0.1s">
        PLYRCARD helps athletes bring their highlights, stats, schedule, academics, and personality together in one clean recruiting card coaches can view in seconds.
      </p>
      <div class="btn-row reveal" style="transition-delay:0.15s">
        <a href="/about#sample" class="btn btn-accent">See a Sample Card</a>
        <a href="https://plyrcard.com/player-intake-app" class="btn btn-outline-white">Create Free</a>
      </div>
    </div>

    <!-- Phone mockup built in CSS -->
    <div class="phone-mockup-wrap reveal" style="transition-delay:0.2s" aria-hidden="true">
      <div class="phone-shell">
        <div class="phone-notch"></div>
        <div class="phone-screen-top">
          <span class="phone-logo-text"><span>PLYR</span>CARD</span>
          <div class="phone-avatar"></div>
        </div>
        <div class="phone-hero-img">
          <span class="athlete-icon">🏃</span>
        </div>
        <div class="phone-name-bar">
          <div class="phone-athlete-name">Jordan Williams</div>
          <div class="phone-athlete-sport">Midfielder · Class of 2026</div>
        </div>
        <div class="phone-stats-row">
          <div class="phone-stat-cell">
            <div class="pstat-num">4.1</div>
            <div class="pstat-label">GPA</div>
          </div>
          <div class="phone-stat-cell">
            <div class="pstat-num">34</div>
            <div class="pstat-label">Goals</div>
          </div>
          <div class="phone-stat-cell">
            <div class="pstat-num">D1</div>
            <div class="pstat-label">Target</div>
          </div>
        </div>
        <div class="phone-links">
          <div class="phone-link-item">
            <div class="phone-link-dot"></div>
            <span class="phone-link-text">Highlight Reel</span>
          </div>
          <div class="phone-link-item">
            <div class="phone-link-dot"></div>
            <span class="phone-link-text">Game Schedule</span>
          </div>
          <div class="phone-link-item">
            <div class="phone-link-dot"></div>
            <span class="phone-link-text">Academic Transcript</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ════════════════════════════════════════════════════════
     WHY IT MATTERS
═══════════════════════════════════════════════════════════ -->
<section id="why-it-matters"  style="background-image: url('/images/PLYRCARD-SITE-COACH.jpg');" role="img" aria-label="Athlete in sprint starting position">
  <span class="big-number" aria-hidden="true">300</span>
  <p class="section-eyebrow reveal">The Problem</p>
  <h2 class="section-title reveal">Coaches get<br>hundreds of<br>emails a day.</h2>
  <p class="section-body reveal" style="transition-delay:0.1s">
    Walls of text get ignored. Generic templates get lost. Athletes deserve a better way to show who they are and what they can do.
  </p>
  <p class="section-body reveal" style="transition-delay:0.15s; margin-top:16px;">
    PLYRCARD gives you a cleaner, more memorable way to be seen — before a coach ever replies.
  </p>
  <div class="btn-row reveal" style="transition-delay:0.2s">
    <a href="https://plyrcard.com/pricing.php#compare" class="btn btn-white">See the Difference</a>
  </div>
</section>

	
	<!-- ════════════════════════════════════════════════════════
     HOW IT WORKS
═══════════════════════════════════════════════════════════ -->
<section id="how-it-works">
  <p class="section-eyebrow reveal">Simple Process</p>
  <h2 class="section-title reveal" style="color:var(--black)">How It<br>Works</h2>
  <p class="section-body dark reveal" style="transition-delay:0.08s">
    Three steps to a better first impression.
  </p>
  <ol class="steps-list" aria-label="Three steps to use PLYRCARD">
    <li class="step-item reveal" style="transition-delay:0.12s">
      <span class="step-num">01</span>
      <div class="step-text">
        <h3 class="step-title">Build Your Card</h3>
        <p class="step-copy">Add highlights, stats, schedule, academics, and your story in one place.</p>
      </div>
    </li>
    <li class="step-item reveal" style="transition-delay:0.18s">
      <span class="step-num">02</span>
      <div class="step-text">
        <h3 class="step-title">Share One Link</h3>
        <p class="step-copy">Send coaches a clean, visual card — not another wall of text.</p>
      </div>
    </li>
    <li class="step-item reveal" style="transition-delay:0.24s">
      <span class="step-num">03</span>
      <div class="step-text">
        <h3 class="step-title">Track Engagement</h3>
        <p class="step-copy">See who opened, clicked, and viewed your profile in real time.</p>
      </div>
    </li>
  </ol>
  <div class="btn-row reveal" style="transition-delay:0.3s">
    <a href="https://plyrcard.com/player-intake-app" class="btn btn-accent">Start Free</a>
    <a href="/book-demo" class="btn btn-outline-dark">Book a Demo</a>
  </div>
</section>


<!-- ════════════════════════════════════════════════════════
     SPORT IMAGE STRIP
═══════════════════════════════════════════════════════════ -->
<div id="sport-strip" role="img" aria-label="Athletes competing on a track">
  <div class="sport-strip-text">
    <p class="section-eyebrow" style="margin-bottom:6px;">Built for Every Sport</p>
    <h3 class="sport-strip-headline">Show Up<br>Ready.</h3>
  </div>
</div>


<!-- ════════════════════════════════════════════════════════
     FINAL CTA
═══════════════════════════════════════════════════════════ -->
<section id="final-cta" aria-labelledby="final-cta-headline">
  <p class="section-eyebrow">Get Started Today</p>
  <h2 class="section-title" id="final-cta-headline">Start Your<br>Journey</h2>
  <p class="cta-body">Build a recruiting card coaches will actually remember.</p>
  <div class="btn-row">
    <a href="https://plyrcard.com/player-intake-app" class="btn-white-cta">Create Free Account</a>
    <a href="/pricing" class="btn-outline-white-cta">See Pricing</a>
  </div>
  <p class="fine-print">No credit card required &nbsp;·&nbsp; Free plan available &nbsp;·&nbsp; Set up in minutes</p>
</section>


<!-- ════════════════════════════════════════════════════════
     TESTIMONIALS
═══════════════════════════════════════════════════════════ -->
<section id="testimonials">
  <p class="section-eyebrow reveal">Hear It From Them</p>
  <h2 class="section-title reveal">Real Stories.<br>Real Trust.</h2>

  <div class="tab-row" role="tablist" aria-label="Testimonial categories">
    <button class="tab-btn active" role="tab" aria-selected="true" data-tab="coach">Coach</button>
    <button class="tab-btn" role="tab" aria-selected="false" data-tab="parent">Parent</button>
    <button class="tab-btn" role="tab" aria-selected="false" data-tab="athlete">Athlete</button>
  </div>

  <div class="tab-panel active" id="tab-coach" role="tabpanel">
    <div class="testimonial-card">
      <p class="testimonial-quote">Recruiting is crowded. PLYRCARD makes it easier to get the right information quickly. I can see exactly what I need in seconds.</p>
      <p class="testimonial-role">College Coach</p>
      <p class="testimonial-name">Division I Soccer Program</p>
    </div>
  </div>

  <div class="tab-panel" id="tab-parent" role="tabpanel">
    <div class="testimonial-card">
      <p class="testimonial-quote">It gave us one clean place to share everything clearly and professionally. Our daughter stood out in a way a regular email never could.</p>
      <p class="testimonial-role">Parent</p>
      <p class="testimonial-name">Volleyball Family</p>
    </div>
  </div>

  <div class="tab-panel" id="tab-athlete" role="tabpanel">
    <div class="testimonial-card">
      <p class="testimonial-quote">It felt like my brand, not just another email. I finally had a way to show coaches who I actually am — not just stats on a page.</p>
      <p class="testimonial-role">Athlete</p>
      <p class="testimonial-name">Class of 2026 · Midfielder</p>
    </div>
  </div>
</section>


<!-- ════════════════════════════════════════════════════════
     TRUST LOGOS
═══════════════════════════════════════════════════════════ -->
<section id="trust-logos">
  <p class="trust-eyebrow">Trusted in the Game</p>
  <div class="trust-grid" aria-label="Partner logos">
    <div class="trust-logo-box"><span>NCSAA</span></div>
    <div class="trust-logo-box"><span>RecruitIQ</span></div>
    <div class="trust-logo-box"><span>Verso</span></div>
    <div class="trust-logo-box"><span>D1 Programs</span></div>
    <div class="trust-logo-box"><span>ECNL</span></div>
    <div class="trust-logo-box"><span>Club Teams</span></div>
  </div>
</section>




<!-- ════════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════════ -->
@include('partials.footer')


<!-- ════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════ -->

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
