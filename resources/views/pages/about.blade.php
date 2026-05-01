@php
    $activePage = 'about';
@endphp

@include('partials.images')
@include('partials.navigation')


<!DOCTYPE html>
<html lang="en">
<head>
	
	<script src="https://widgets.leadconnectorhq.com/loader.js" data-resources-url="https://widgets.leadconnectorhq.com/chat-widget/loader.js" data-widget-id="6941fea74ca18223c7de491d"></script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="description" content="About PLYRCARD — Built by athletes, for athletes. Our mission is to give every player a better way to be seen by college coaches." />
  <title>About Us — PLYRCARD</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <style>
    /* ─── RESET & TOKENS ──────────────────────────────────────────── */
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
      background: rgba(13,13,13,0.92);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(255,255,255,0.07);
      transition: background 0.3s var(--ease-out);
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
      height: 50px;
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

    /* Nav drawer */
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
    #mobile-nav.open { opacity: 1; pointer-events: auto; transform: translateY(0); }
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

    /* ─── PAGE HERO ───────────────────────────────────────────────── */
    #page-hero {
      position: relative;
      width: 100%;
      height: 88svh;
      min-height: 560px;
      overflow: hidden;
      background: var(--black);
    }
    .page-hero-bg {
      position: absolute;
      inset: 0;
      background-image: url('https://sspark.genspark.ai/cfimages?u1=8kTPwIjs344zI2yPMLzpedipJl5y4iCeZYW1gv%2BnJMPksdTix7P%2BU2IjAOQLFcPw5%2BaZ2JWdYygxf86vnNY7bPXIFwcYpZ8rEYqIDspiS2VD8tYkHcLiAyoArtXr311jN9OcK63DSjytEJTOVAmilUAYQURzIQMfDQOjSQZpw2ZtD4UxyxuIOCmc0kE%3D&u2=3LvWB8%2Ful5AgIOlf&width=2560');
      background-size: cover;
      background-position: center 25%;
      transform: scale(1.04);
      transition: transform 8s linear;
    }
    #page-hero.loaded .page-hero-bg { transform: scale(1); }
    .page-hero-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(
        to bottom,
        rgba(0,0,0,0.12) 0%,
        rgba(0,0,0,0.18) 35%,
        rgba(0,0,0,0.72) 72%,
        rgba(0,0,0,0.92) 100%
      );
    }
    .page-hero-content {
      position: absolute;
      bottom: calc(52px + var(--safe-bottom));
      left: 0; right: 0;
      padding: 0 24px;
    }
    .page-eyebrow {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 10px;
    }
    .page-hero-headline {
      font-family: var(--font-display);
      font-size: clamp(52px, 14vw, 72px);
      font-weight: 700;
      line-height: 0.92;
      letter-spacing: -0.02em;
      text-transform: uppercase;
      color: var(--white);
      margin-bottom: 16px;
    }
    .page-hero-sub {
      font-size: 16px;
      line-height: 1.55;
      color: rgba(255,255,255,0.72);
      max-width: 300px;
    }

    /* ─── SHARED SECTION STYLES ───────────────────────────────────── */
    section { padding: 64px 24px; }
    .section-eyebrow {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 10px;
    }
    .section-title {
      font-family: var(--font-display);
      font-size: clamp(34px, 9vw, 52px);
      font-weight: 700;
      line-height: 0.95;
      letter-spacing: -0.02em;
      text-transform: uppercase;
      margin-bottom: 16px;
    }
    .section-body {
      font-size: 16px;
      line-height: 1.65;
      color: rgba(255,255,255,0.68);
      max-width: 380px;
    }
    .section-body.on-light { color: rgba(0,0,0,0.60); max-width: 380px; }
    .section-title.on-light { color: var(--black); }
    .section-eyebrow.on-light { color: var(--accent); }

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
    .btn-accent { background: var(--accent); color: var(--white); }
    .btn-accent:hover { background: var(--accent-dark); }
    .btn-white { background: var(--white); color: var(--black); }
    .btn-white:hover { background: var(--accent); color: var(--white); }
    .btn-outline-white { background: transparent; color: var(--white); border: 1.5px solid rgba(255,255,255,0.35); }
    .btn-outline-white:hover { border-color: var(--white); background: rgba(255,255,255,0.07); }
    .btn-outline-dark { background: transparent; color: var(--black); border: 1.5px solid rgba(0,0,0,0.28); }
    .btn-outline-dark:hover { border-color: var(--black); background: rgba(0,0,0,0.06); }
    .btn-row { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }

    /* ─── ORIGIN STORY ────────────────────────────────────────────── */
    #origin {
      background-color: var(--black);
      background-image: url('https://sspark.genspark.ai/cfimages?u1=SAXThPw76FWUXl7nFo644edz%2FYQWY77so7wrYNsH57U5G0p7yWPDWWs2BZP5LWB4JVvVhMJ%2BsE9MXnx8QhYy1CTJsiGPikQBVKJsZZpMgxi68A%3D%3D&u2=j4FIAtvXpQvCZ7FW&width=2560');
      background-size: cover;
      background-position: center 40%;
      background-attachment: fixed;
      position: relative;
    }
    #origin::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(13,13,13,0.88);
      pointer-events: none;
    }
    #origin > * { position: relative; z-index: 1; }
    .origin-number {
      font-family: var(--font-display);
      font-size: clamp(100px, 28vw, 160px);
      font-weight: 700;
      line-height: 1;
      letter-spacing: -0.04em;
      color: rgba(255,255,255,0.04);
      margin-bottom: -20px;
      display: block;
      user-select: none;
    }

    /* ─── MISSION STATEMENT ───────────────────────────────────────── */
    #mission {
      background: var(--accent);
      padding: 72px 24px;
    }
    .mission-statement {
      font-family: var(--font-display);
      font-size: clamp(28px, 8vw, 44px);
      font-weight: 700;
      line-height: 1.05;
      letter-spacing: -0.02em;
      text-transform: uppercase;
      color: var(--white);
      margin-bottom: 24px;
    }
    .mission-body {
      font-size: 16px;
      line-height: 1.65;
      color: rgba(255,255,255,0.82);
      max-width: 360px;
    }

    /* ─── TIMELINE ────────────────────────────────────────────────── */
    #timeline {
      background-color: var(--surface-2);
      background-image: url('https://sspark.genspark.ai/cfimages?u1=FqRT1x4OUkJ1NllpQISbVpYOao4BvEHJIEo0WTTwAzPnEcKwU7GdUFKHnaPJ2%2FUvA2pMfH1BE%2B4ccuePKvxnm3GVUJ5QdOYkV%2FrX6i1HRVNn&u2=tyaEp5yKJMDXuuPq&width=2560');
      background-size: cover;
      background-position: center center;
      background-attachment: fixed;
      position: relative;
    }
    #timeline::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(17,17,17,0.92);
      pointer-events: none;
    }
    #timeline > * { position: relative; z-index: 1; }
    .timeline-list {
      margin-top: 40px;
      position: relative;
    }
    .timeline-list::before {
      content: '';
      position: absolute;
      left: 16px;
      top: 8px;
      bottom: 8px;
      width: 2px;
      background: rgba(255,255,255,0.08);
    }
    .timeline-item {
      display: flex;
      gap: 24px;
      padding-bottom: 40px;
      position: relative;
    }
    .timeline-item:last-child { padding-bottom: 0; }
    .timeline-dot-wrap {
      flex-shrink: 0;
      width: 34px;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding-top: 4px;
    }
    .timeline-dot {
      width: 14px; height: 14px;
      border-radius: 50%;
      background: var(--accent);
      border: 2px solid var(--surface-2);
      flex-shrink: 0;
      z-index: 1;
    }
    .timeline-body {}
    .timeline-year {
      font-family: var(--font-display);
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.10em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 4px;
    }
    .timeline-event {
      font-family: var(--font-display);
      font-size: 20px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: -0.01em;
      line-height: 1.05;
      color: var(--white);
      margin-bottom: 8px;
    }
    .timeline-copy {
      font-size: 14px;
      line-height: 1.6;
      color: rgba(255,255,255,0.55);
      max-width: 300px;
    }

    /* ─── IMAGE BREAK ─────────────────────────────────────────────── */
    .img-break {
      height: 65vw;
      min-height: 260px;
      max-height: 420px;
      background-size: cover;
      background-position: center;
      position: relative;
      overflow: hidden;
    }
    .img-break::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, rgba(0,0,0,0.2), rgba(0,0,0,0.70));
    }
    .img-break-text {
      position: absolute;
      inset: 0;
      z-index: 1;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 24px;
    }
    .img-break-headline {
      font-family: var(--font-display);
      font-size: clamp(30px, 9vw, 52px);
      font-weight: 700;
      text-transform: uppercase;
      line-height: 0.95;
      letter-spacing: -0.02em;
      color: var(--white);
    }

    /* ─── PROBLEM / INSIGHT ───────────────────────────────────────── */
    #problem {
      background-color: var(--black);
      background-image: url('https://sspark.genspark.ai/cfimages?u1=SZhRECMu3N%2Bgp0%2FCygNRzSC%2BZqPR9VD90rJvLkybRy4qPIJTjCsGVMiFb%2F7EMQ7kyg%2BVlyI4d8Stj5IdgMOTRFFNxSBiDupm8Qlgk0U0qnMBkWX79TzmEmCnGv8wX7wT%2F4YQ4gDW2kW%2FMHKmtFt%2Bm%2BC5evTiGW%2F6KrD0w1P4niS%2BwC8FWLNHpTLpJBzXHg%3D%3D&u2=KzPz4x3XLhlBGBra&width=2560');
      background-size: cover;
      background-position: center 30%;
      background-attachment: fixed;
      position: relative;
    }
    #problem::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(13,13,13,0.90);
      pointer-events: none;
    }
    #problem > * { position: relative; z-index: 1; }
    .pull-quote {
      font-family: var(--font-display);
      font-size: clamp(22px, 6.5vw, 36px);
      font-weight: 700;
      line-height: 1.10;
      letter-spacing: -0.01em;
      text-transform: uppercase;
      color: var(--white);
      margin-bottom: 24px;
      border-left: 4px solid var(--accent);
      padding-left: 20px;
    }

    /* ─── STATS BAND ──────────────────────────────────────────────── */
    #stats-band {
      background: var(--white);
      padding: 52px 24px;
    }
    .stats-band-eyebrow {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 32px;
    }
    .stats-band-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 32px 16px;
    }
    @media(min-width: 480px) {
      .stats-band-grid { grid-template-columns: repeat(4, 1fr); gap: 16px; }
    }
    .stat-number {
      font-family: var(--font-display);
      font-size: 38px;
      font-weight: 700;
      line-height: 1;
      letter-spacing: -0.02em;
      color: var(--black);
    }
    .stat-label {
      font-size: 12px;
      font-weight: 500;
      color: rgba(0,0,0,0.45);
      margin-top: 4px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    /* ─── VALUES ──────────────────────────────────────────────────── */
    #values {
      background-color: var(--white);
      color: var(--black);
      background-image: url('https://sspark.genspark.ai/cfimages?u1=xfjiQMF65fEHI%2FJvCV%2Bi6JN6%2F%2B%2Blu8INSKPJkTUryqg0Oa5cxJfw0ZkClofXJ772fPvLwnwcy1RATtnmxwMM7EXn%2Fl3GtSuQ%2BURtxqhx5phV0%2FbHJJyhhsPQgOKE78lrwiDDcLnZdhbfDEyoHLiuJu%2FDVO5KJfzKKK3wPBPXs%2FqeXGcdbF0%2FUBfJPVTVCEk5TLz5XhJUUA8IkX784uYaRIlizxUqxAWABmH8Mj4zSHigEUpHF6O5EmSf2UxROx5utSBlflTTU9nHhft0bfNEypYH%2BTPFaEGFzA%2FJjq2SepU%3D&u2=4ng3izxGm5nwsTIn&width=2560');
      background-size: cover;
      background-position: center center;
      background-attachment: fixed;
      position: relative;
    }
    #values::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(255,255,255,0.93);
      pointer-events: none;
    }
    #values > * { position: relative; z-index: 1; }
    .values-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
      margin-top: 40px;
    }
    @media(min-width: 480px) { .values-grid { grid-template-columns: 1fr 1fr; } }
    .value-card {
      background: var(--black);
      border-radius: var(--radius-card);
      padding: 28px 24px;
      border: 1px solid rgba(255,255,255,0.04);
    }
    .value-icon {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      margin-bottom: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--accent);
      background: rgba(255,92,53,0.10);
      border: 1px solid rgba(255,92,53,0.20);
    }
    .value-icon svg {
      width: 21px;
      height: 21px;
      stroke: currentColor;
      stroke-width: 2;
      fill: none;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    .value-title {
      font-family: var(--font-display);
      font-size: 19px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: -0.01em;
      color: var(--white);
      margin-bottom: 8px;
    }
    .value-body {
      font-size: 14px;
      line-height: 1.55;
      color: rgba(255,255,255,0.50);
    }

    /* ─── WHAT MAKES IT DIFFERENT ─────────────────────────────────── */
    /* ─── DIFFERENTIATOR — see background styles below ──────────── */
    .diff-list {
      margin-top: 36px;
      display: flex;
      flex-direction: column;
      gap: 0;
    }
    .diff-item {
      display: flex;
      gap: 20px;
      padding: 22px 0;
      border-bottom: 1px solid rgba(255,255,255,0.07);
      align-items: flex-start;
    }
    .diff-item:last-child { border-bottom: none; }
    .diff-icon {
      width: 34px;
      height: 34px;
      border-radius: 12px;
      flex-shrink: 0;
      margin-top: 1px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--accent);
      background: rgba(255,92,53,0.10);
      border: 1px solid rgba(255,92,53,0.18);
    }
    .diff-icon svg {
      width: 18px;
      height: 18px;
      stroke: currentColor;
      stroke-width: 2;
      fill: none;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    .diff-text {}
    .diff-title {
      font-family: var(--font-display);
      font-size: 18px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: -0.01em;
      color: var(--white);
      margin-bottom: 4px;
    }
    .diff-copy {
      font-size: 14px;
      line-height: 1.55;
      color: rgba(255,255,255,0.52);
    }

    /* ─── TEAM ────────────────────────────────────────────────────── */
    #team {
      background-color: var(--black);
      background-image: url('https://sspark.genspark.ai/cfimages?u1=LeYjAIC5rgE3QA2%2FEHa%2Fn%2BGy3nIJqdjYzTihXAVl5BIqfvU0N5PzRQylg7u9NPxhi9%2F%2Fl39SoxwOTVJnVAvXurbMNAMj%2BDcu8vpNAIU0x1nVzmJ6&u2=6IeL6evo2x9SvC3y&width=2560');
      background-size: cover;
      background-position: center 55%;
      background-attachment: fixed;
      position: relative;
    }
    #team::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(13,13,13,0.91);
      pointer-events: none;
    }
    #team > * { position: relative; z-index: 1; }
    .team-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-top: 40px;
    }
    @media(min-width: 600px) { .team-grid { grid-template-columns: repeat(3, 1fr); } }
    .team-card {
      background: var(--surface);
      border-radius: var(--radius-card);
      overflow: hidden;
      border: 1px solid rgba(255,255,255,0.06);
    }
    .team-avatar {
      width: 100%;
      aspect-ratio: 1;
      position: relative;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .team-avatar-bg {
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, #1c1c1c 0%, #272727 100%);
    }
    .team-initials {
      font-family: var(--font-display);
      font-size: 34px;
      font-weight: 700;
      color: rgba(255,255,255,0.10);
      position: relative;
      z-index: 1;
      letter-spacing: 0.02em;
    }
    .team-stripe {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 4px;
      background: var(--accent);
    }
    .team-info {
      padding: 14px 16px 18px;
    }
    .team-name {
      font-family: var(--font-display);
      font-size: 15px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.02em;
      line-height: 1.1;
    }
    .team-role {
      font-size: 12px;
      color: rgba(255,255,255,0.38);
      margin-top: 3px;
    }
    .team-sport {
      font-size: 11px;
      color: var(--accent);
      margin-top: 2px;
      font-family: var(--font-display);
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    /* ─── PARTNERSHIPS ────────────────────────────────────────────── */
    #partnerships {
      background-color: var(--surface-2);
      background-image: url('https://sspark.genspark.ai/cfimages?u1=FppeRNbGuvnkpEpjN81JzwhkmKx2Q5yR3vndpTBwypujwHIjXCr5E%2BR5vomhPq%2FdWrLhAnO1CGlqNssIkyvAJb54Z4mDbJmoDDcgQH2iow%3D%3D&u2=FynSjC%2FNZIwi2IrQ&width=2560');
      background-size: cover;
      background-position: center 60%;
      background-attachment: fixed;
      position: relative;
    }
    #partnerships::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(17,17,17,0.91);
      pointer-events: none;
    }
    #partnerships > * { position: relative; z-index: 1; }
    .partner-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-top: 32px;
    }
    .partner-box {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 12px;
      height: 52px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .partner-box span {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.22);
    }

    /* ─── FINAL CTA ───────────────────────────────────────────────── */
    #final-cta {
      background: var(--accent);
      padding: 80px 24px calc(80px + var(--safe-bottom));
      text-align: center;
    }
    #final-cta .section-title { text-align: center; color: var(--white); }
    #final-cta .section-eyebrow { color: rgba(255,255,255,0.72); }
    .cta-body {
      font-size: 16px;
      color: rgba(255,255,255,0.80);
      line-height: 1.55;
      margin-top: 12px;
      max-width: 300px;
      margin-left: auto;
      margin-right: auto;
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
      text-decoration: none;
      display: inline-flex;
      align-items: center;
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
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }
    .fine-print {
      font-size: 12px;
      color: rgba(255,255,255,0.52);
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
      font-size: 15px;
      color: rgba(255,255,255,0.50);
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
    .footer-copy { font-size: 12px; color: rgba(255,255,255,0.25); }
    .footer-tagline {
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.18);
    }

    /* ─── REVEAL ANIMATION ────────────────────────────────────────── */
    .reveal {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.7s var(--ease-out), transform 0.7s var(--ease-out);
    }
    .reveal.visible { opacity: 1; transform: translateY(0); }

    @media(min-width: 768px) {
      section { padding: 80px 48px; }
      .page-hero-content { padding: 0 48px; }
    }

    /* ─── DIFFERENTIATOR ─────────────────────────────────────────── */
    #differentiator {
      background-color: var(--surface-2);
      background-image: url('https://sspark.genspark.ai/cfimages?u1=sZb1gdMNjUGKDdMzaZ%2ByKwu%2Bh4Bsge4RZHoBt4Q6%2BvzVOd8Ci3QxQ5YXi88%2FkclFwquHv5JdMYGxCNBMotBaNsFfxvXs1A4VsK%2BQM5ULQch0IyCc&u2=JNJKVTQCjIy8TZ%2Bi&width=2560');
      background-size: cover;
      background-position: center 40%;
      background-attachment: fixed;
      position: relative;
    }
    #differentiator::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(17,17,17,0.92);
      pointer-events: none;
    }
    #differentiator > * { position: relative; z-index: 1; }

    /* ─── iOS: disable fixed bg-attachment (not supported in WKWebView) */
    @supports (-webkit-touch-callout: none) {
      #origin, #timeline, #problem, #values, #team, #partnerships, #differentiator {
        background-attachment: scroll;
      }
    }

    /* ─── DESKTOP LAYOUT ENHANCEMENTS ─────────────────────────────── */
    .desktop-nav {
      display: none;
      align-items: center;
      gap: 26px;
      margin-left: auto;
    }

    .desktop-nav a {
      font-family: var(--font-display);
      font-size: 20px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.72);
      transition: color 0.2s, background 0.2s;
    }

    .desktop-nav a:hover,
    .desktop-nav a.active {
      color: var(--white);
    }

    .desktop-nav-cta {
      background: var(--accent);
      color: var(--white) !important;
      border-radius: var(--radius-btn);
      padding: 11px 20px;
    }

    .desktop-nav-cta:hover {
      background: var(--accent-dark);
    }

    @media (min-width: 1024px) {
      :root { --header-h: 72px; }

      #site-header {
        padding-left: 48px;
        padding-right: 48px;
      }

      .logo-wrap img { height: 50px; }
      .desktop-nav { display: flex; }
      .menu-btn { display: none; }

      #mobile-nav {
        display: none;
      }

      #page-hero {
        height: 100svh;
        min-height: 720px;
      }

      .page-hero-bg {
        background-position: center 30%;
      }

      .page-hero-overlay {
        background:
          linear-gradient(to right, rgba(0,0,0,0.84) 0%, rgba(0,0,0,0.52) 42%, rgba(0,0,0,0.12) 100%),
          linear-gradient(to bottom, rgba(0,0,0,0.08) 0%, rgba(0,0,0,0.32) 58%, rgba(0,0,0,0.92) 100%);
      }

      .page-hero-content {
        left: 50%;
        right: auto;
        transform: translateX(-50%);
        width: min(1180px, calc(100% - 96px));
        padding: 0;
        bottom: 96px;
      }

      .page-hero-headline {
        font-size: clamp(82px, 8vw, 132px);
        max-width: 700px;
      }

      .page-hero-sub {
        max-width: 460px;
        font-size: 18px;
      }

      section {
        padding: 110px 48px;
      }

      section > .section-eyebrow,
      section > .section-title,
      section > .section-body,
      section > .btn-row,
      section > .pull-quote,
      section > .mission-statement,
      section > .mission-body,
      section > .timeline-list,
      section > .diff-list,
      section > .values-grid,
      section > .team-grid,
      section > .partner-grid,
      section > .origin-number {
        width: min(1180px, 100%);
        margin-left: auto;
        margin-right: auto;
      }

      .section-title {
        font-size: clamp(58px, 5.6vw, 92px);
        max-width: 620px;
      }

      .section-body {
        max-width: 620px;
        font-size: 18px;
      }

      #origin {
        padding-top: 130px;
        padding-bottom: 130px;
      }

      .origin-number {
        font-size: 190px;
        margin-bottom: -36px;
      }

      #mission {
        padding: 120px 48px;
      }

      #mission .section-eyebrow,
      .mission-statement,
      .mission-body {
        width: min(1180px, 100%);
        margin-left: auto;
        margin-right: auto;
      }

      .mission-statement {
        font-size: clamp(64px, 6vw, 102px);
        max-width: 820px;
      }

      .mission-body {
        max-width: 700px;
        font-size: 18px;
      }

      #stats-band {
        padding: 66px 48px;
      }

      #stats-band > * {
        width: min(1180px, 100%);
        margin-left: auto;
        margin-right: auto;
      }

      .stats-band-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 28px;
      }

      .stat-number {
        font-size: 56px;
      }

      .timeline-list {
        max-width: 980px;
        padding-left: 0;
      }

      .timeline-list::before {
        left: 50%;
        transform: translateX(-50%);
      }

      .timeline-item {
        width: 50%;
        gap: 28px;
        padding-bottom: 64px;
      }

      .timeline-item:nth-child(odd) {
        margin-right: auto;
        padding-right: 58px;
        flex-direction: row-reverse;
        text-align: right;
      }

      .timeline-item:nth-child(even) {
        margin-left: auto;
        padding-left: 58px;
      }

      .timeline-item:nth-child(odd) .timeline-copy {
        margin-left: auto;
      }

      .timeline-dot-wrap {
        width: 20px;
        position: absolute;
        top: 4px;
      }

      .timeline-item:nth-child(odd) .timeline-dot-wrap {
        right: -10px;
      }

      .timeline-item:nth-child(even) .timeline-dot-wrap {
        left: -10px;
      }

      .timeline-copy {
        max-width: 380px;
      }

      .img-break {
        height: 46vw;
        max-height: 620px;
      }

      .img-break-text {
        width: min(1180px, calc(100% - 96px));
        left: 50%;
        transform: translateX(-50%);
        padding: 0 0 64px;
      }

      .img-break-headline {
        font-size: clamp(62px, 6vw, 100px);
      }

      #problem .pull-quote {
        max-width: 850px;
        font-size: clamp(38px, 3.8vw, 62px);
        margin-bottom: 34px;
      }

      .diff-list {
        max-width: 980px;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
      }

      .diff-item {
        background: rgba(255,255,255,0.045);
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: var(--radius-card);
        padding: 26px;
      }

      .diff-item:last-child {
        grid-column: span 2;
      }

      .values-grid {
        max-width: 1180px;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
      }

      .value-card {
        min-height: 220px;
        padding: 32px 28px;
      }

      .team-grid {
        max-width: 1180px;
        grid-template-columns: repeat(6, 1fr);
      }

      .team-card {
        transition: transform 0.25s var(--ease-out), border-color 0.25s;
      }

      .team-card:hover {
        transform: translateY(-6px);
        border-color: rgba(255,92,53,0.42);
      }

      .partner-grid {
        max-width: 820px;
        grid-template-columns: repeat(6, 1fr);
        gap: 14px;
      }

      #final-cta {
        padding: 110px 48px calc(110px + var(--safe-bottom));
      }

      #final-cta .section-title {
        font-size: clamp(64px, 5.8vw, 104px);
        max-width: none;
      }

      .cta-body {
        max-width: 520px;
        font-size: 18px;
      }

      #site-footer {
        padding: 58px 48px calc(48px + var(--safe-bottom));
      }

      #site-footer > * {
        width: min(1180px, 100%);
        margin-left: auto;
        margin-right: auto;
      }

      .footer-nav {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 22px;
      }
    }

    @media (min-width: 1280px) {
      #site-header {
        padding-left: max(64px, calc((100vw - 1180px) / 2));
        padding-right: max(64px, calc((100vw - 1180px) / 2));
      }
    }


    @media (prefers-reduced-motion: reduce) {
      .reveal { transition: none; }
      .page-hero-bg { transition: none; }
      #origin, #timeline, #problem, #values, #team, #partnerships, #differentiator {
        background-attachment: scroll;
      }
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

  
    /* ─── FINAL TRUSTED PARTNER LOGOS: 6 ACROSS / 2 ROWS ─────────── */
    .trust-grid,
    .partner-grid {
      width: min(1500px, calc(100% - 72px)) !important;
      max-width: 1500px !important;
      margin-left: auto !important;
      margin-right: auto !important;
      display: grid !important;
      grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
      gap: 20px !important;
    }

    .trust-logo-box,
    .partner-box {
      height: 124px !important;
      min-height: 124px !important;
      border-radius: 18px !important;
      background: rgba(255,255,255,0.055) !important;
      border: 1px solid rgba(255,255,255,0.10) !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      padding: 18px !important;
      overflow: hidden !important;
      transition: transform 0.25s ease, border-color 0.25s ease, background 0.25s ease, box-shadow 0.25s ease !important;
    }

    .trust-logo-box:hover,
    .partner-box:hover {
      transform: translateY(-3px) !important;
      background: rgba(255,255,255,0.085) !important;
      border-color: rgba(255,92,53,0.26) !important;
      box-shadow: 0 18px 44px rgba(0,0,0,0.22) !important;
    }

    .trust-logo-box img,
    .partner-box img {
      width: 100% !important;
      max-width: 165px !important;
      max-height: 76px !important;
      object-fit: contain !important;
      opacity: 0.68 !important;
      filter: grayscale(1) brightness(1.45) contrast(1.08) !important;
      transition: opacity 0.25s ease, filter 0.25s ease, transform 0.25s ease !important;
    }

    .trust-logo-box:hover img,
    .partner-box:hover img {
      opacity: 1 !important;
      filter: none !important;
      transform: scale(1.04) !important;
    }

    .trust-logo-box span,
    .partner-box span {
      font-size: 16px !important;
      color: rgba(255,255,255,0.44) !important;
    }

    @media (max-width: 1280px) {
      .trust-grid,
      .partner-grid {
        width: min(1180px, calc(100% - 48px)) !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 18px !important;
      }
    }

    @media (max-width: 720px) {
      .trust-grid,
      .partner-grid {
        width: calc(100% - 32px) !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 14px !important;
      }

      .trust-logo-box,
      .partner-box {
        height: 100px !important;
        min-height: 100px !important;
        border-radius: 15px !important;
        padding: 16px !important;
      }

      .trust-logo-box img,
      .partner-box img {
        max-width: 140px !important;
        max-height: 60px !important;
      }
    }

  
    .plyrs-embed-card {
      overflow: hidden !important;
    }

    .plyrs-embed-card .elfsight-app-c088c003-42fd-4aa0-8d82-ce6f542e31ac {
      width: 100%;
      min-height: 520px;
    }


    /* ─── PLYRS FINAL ALIGNMENT FIX ─────────────────────────────── */
    #plyrs {
      padding-top: 72px !important;
      padding-bottom: 72px !important;
    }

    #plyrs .section-eyebrow,
    #plyrs .section-title,
    #plyrs .section-body {
      width: min(720px, calc(100% - 64px)) !important;
      max-width: 720px !important;
      margin-left: auto !important;
      margin-right: auto !important;
      text-align: left !important;
      padding-left: 0 !important;
      transform: none !important;
    }

    #plyrs .section-eyebrow {
      margin-bottom: 12px !important;
    }

    #plyrs .section-title {
      margin-top: 0 !important;
      margin-bottom: 20px !important;
      line-height: 0.9 !important;
    }

    #plyrs .section-body {
      margin-top: 0 !important;
      margin-bottom: 42px !important;
      line-height: 1.48 !important;
    }

    #plyrs .plyrs-embed-card {
      width: min(1080px, calc(100% - 64px)) !important;
      margin: 0 auto !important;
      padding: 0 !important;
      min-height: 0 !important;
      display: block !important;
      overflow: hidden !important;
      border-radius: 18px !important;
      background: rgba(255,255,255,0.035) !important;
    }

    #plyrs .elfsight-app-c088c003-42fd-4aa0-8d82-ce6f542e31ac {
      width: 100% !important;
      min-height: 420px !important;
      display: block !important;
      overflow: hidden !important;
    }

    @media (max-width: 767px) {
      #plyrs {
        padding-top: 52px !important;
        padding-bottom: 52px !important;
      }

      #plyrs .section-eyebrow,
      #plyrs .section-title,
      #plyrs .section-body,
      #plyrs .plyrs-embed-card {
        width: calc(100% - 32px) !important;
        max-width: none !important;
      }

      #plyrs .section-body {
        margin-bottom: 30px !important;
      }

      #plyrs .plyrs-embed-card {
        border-radius: 14px !important;
      }
    }


    /* ─── PLYRS + VIDEO PLACEHOLDERS ─────────────────────────────── */
    #plyrs,
    #plyrcard-show {
      padding: 96px 0;
      position: relative;
      overflow: hidden;
    }

    #plyrs .plyrs-copy,
    #plyrcard-show .show-copy {
      width: min(720px, calc(100% - 64px));
      margin: 0 auto 42px;
      text-align: left;
    }

    #plyrs .plyrs-copy .section-eyebrow,
    #plyrcard-show .show-copy .section-eyebrow {
      margin-bottom: 12px;
    }

    #plyrs .plyrs-copy .section-title,
    #plyrcard-show .show-copy .section-title {
      margin: 0 0 20px;
      line-height: 0.9;
      text-align: left;
    }

    #plyrs .plyrs-copy .section-body,
    #plyrcard-show .show-copy .section-body {
      margin: 0;
      max-width: 720px;
      line-height: 1.5;
      text-align: left;
    }

    .plyrs-embed-card {
      width: min(1080px, calc(100% - 64px));
      margin: 0 auto;
      padding: 0;
      overflow: hidden;
      border-radius: 18px;
      background: rgba(255,255,255,0.035);
    }

    .plyrs-embed-card .elfsight-app-c088c003-42fd-4aa0-8d82-ce6f542e31ac {
      width: 100%;
      min-height: 420px;
      display: block;
      overflow: hidden;
    }

    .youtube-placeholder-grid {
      width: min(1180px, calc(100% - 64px));
      margin: 40px auto 0;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 22px;
    }

    .youtube-placeholder {
      position: relative;
      min-height: 230px;
      border-radius: 22px;
      border: 1px dashed rgba(255,255,255,0.18);
      background:
        radial-gradient(circle at 24% 10%, rgba(255,92,53,0.18), transparent 34%),
        linear-gradient(135deg, rgba(255,255,255,0.075), rgba(255,255,255,0.025));
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 28px;
      box-shadow: 0 24px 80px rgba(0,0,0,0.22);
    }

    .youtube-placeholder::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, transparent, rgba(0,0,0,0.28));
      pointer-events: none;
    }

    .youtube-placeholder-inner {
      position: relative;
      z-index: 1;
      display: grid;
      gap: 10px;
      justify-items: center;
    }

    .youtube-play {
      width: 64px;
      height: 64px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--accent);
      box-shadow: 0 18px 42px rgba(255,92,53,0.28);
    }

    .youtube-play svg {
      width: 26px;
      height: 26px;
      fill: var(--white);
      margin-left: 4px;
    }

    .youtube-placeholder-label {
      font-family: var(--font-display);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
    }

    .youtube-placeholder-title {
      font-family: var(--font-display);
      font-size: clamp(24px, 3vw, 40px);
      line-height: 0.95;
      text-transform: uppercase;
      color: var(--white);
    }

    .youtube-placeholder-copy {
      font-size: 14px;
      line-height: 1.55;
      color: rgba(255,255,255,0.58);
      max-width: 300px;
    }

    .youtube-channel-embed {
      width: min(1080px, calc(100% - 64px));
      margin: 42px auto 0;
      border-radius: 24px;
      overflow: hidden;
      border: 1px dashed rgba(255,255,255,0.18);
      background: rgba(255,255,255,0.045);
      min-height: 520px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 34px;
    }

    @media (max-width: 980px) {
      .youtube-placeholder-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 767px) {
      #plyrs,
      #plyrcard-show {
        padding: 64px 0;
      }

      #plyrs .plyrs-copy,
      #plyrcard-show .show-copy,
      .plyrs-embed-card,
      .youtube-placeholder-grid,
      .youtube-channel-embed {
        width: calc(100% - 32px);
      }

      .youtube-placeholder {
        min-height: 210px;
      }

      .youtube-channel-embed {
        min-height: 360px;
      }
    }

  
    /* ─── RADIAL OVERLAY ONLY FOR BLACK SECTIONS ─────────────────── */
    #testimonials,
    #plyrcard-show,
    #plyrs {
      position: relative;
      background-color: var(--black) !important;
      overflow: hidden;
    }

    #testimonials::before,
    #plyrcard-show::before,
    #plyrs::before {
      content: '';
      position: absolute;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background:
        radial-gradient(circle at 14% 12%, rgba(255, 92, 53, 0.14), transparent 30%),
        radial-gradient(circle at 82% 18%, rgba(255, 92, 53, 0.07), transparent 28%);
    }

    #testimonials > *,
    #plyrcard-show > *,
    #plyrs > * {
      position: relative;
      z-index: 1;
    }

    /* Keep existing image backgrounds intact. */
    #hero,
    #page-hero,
    #trusted,
    #partnerships,
    #team,
    .img-break {
      background-blend-mode: normal !important;
    }

    /* ─── TESTIMONIAL VIDEO ROW FIX ──────────────────────────────── */
    #testimonials .youtube-placeholder-grid {
      grid-column: 1 / -1 !important;
      width: min(1180px, calc(100% - 64px)) !important;
      margin: 56px auto 0 !important;
      display: grid !important;
      grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
      gap: 24px !important;
      clear: both !important;
    }

    #testimonials .youtube-placeholder {
      min-height: 250px !important;
      width: 100% !important;
    }

    @media (max-width: 980px) {
      #testimonials .youtube-placeholder-grid {
        grid-template-columns: 1fr !important;
      }
    }

  </style>
</head>
<body>
<!-- ══════════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════════ -->
@include('partials.navigation')


<!-- ══════════════════════════════════════════════════════
     PAGE HERO
═══════════════════════════════════════════════════════ -->
<section id="page-hero" aria-label="About PLYRCARD — Our Story">
  <div class="page-hero-bg" role="img" aria-label="Athlete resting on track after an intense workout, determined expression"></div>
  <div class="page-hero-overlay"></div>
  <div class="page-hero-content">
    <p class="page-eyebrow">Our Story</p>
    <h1 class="page-hero-headline">Built for<br>Athletes.<br>By Athletes.</h1>
    <p class="page-hero-sub">We saw the problem up close. Then we built the fix.</p>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════
     ORIGIN STORY
═══════════════════════════════════════════════════════ -->
<section id="origin">
  <span class="origin-number" aria-hidden="true">01</span>
  <p class="section-eyebrow reveal">Where It Started</p>
  <h2 class="section-title reveal">The Problem<br>We Lived.</h2>
  <p class="section-body reveal" style="transition-delay:0.08s">
    PLYRCARD was born out of a simple frustration: talented athletes were pouring everything into their sport — early mornings, late nights, years of sacrifice — and then struggling to communicate that story to the coaches who could change their future.
  </p>
  <p class="section-body reveal" style="transition-delay:0.14s; margin-top:16px;">
    The recruiting process hadn't changed. Copy-paste emails. Generic highlights links. No way to show personality, academic profile, or full schedule. No way to know if anyone even opened it.
  </p>
  <p class="section-body reveal" style="transition-delay:0.20s; margin-top:16px;">
    Coaches were drowning in hundreds of identical messages a day. Athletes were invisible. Something had to change.
  </p>
</section>


<!-- ══════════════════════════════════════════════════════
     MISSION STATEMENT
═══════════════════════════════════════════════════════ -->
<section id="mission" aria-label="Our Mission">
  <p class="section-eyebrow" style="color:rgba(255,255,255,0.65); margin-bottom:16px">Our Mission</p>
  <h2 class="mission-statement reveal">
    Every Athlete<br>Deserves to<br>Be Seen.
  </h2>
  <p class="mission-body reveal" style="transition-delay:0.10s">
    We believe recruiting visibility shouldn't depend on who you know or how many emails you send. It should depend on your work, your story, and your ability to present both clearly.
  </p>
  <p class="mission-body reveal" style="transition-delay:0.16s; margin-top:16px;">
    PLYRCARD gives every athlete — regardless of school, region, or resources — a professional, modern way to stand in front of college programs on their own terms.
  </p>
</section>


<!-- ══════════════════════════════════════════════════════
     STATS BAND
═══════════════════════════════════════════════════════ -->
<div id="stats-band" aria-label="Platform impact metrics">
  <p class="stats-band-eyebrow">The Impact So Far</p>
  <div class="stats-band-grid">
    <div class="reveal">
      <div class="stat-number" data-count="8800" data-suffix="+">0+</div>
      <div class="stat-label">Emails Sent</div>
    </div>
    <div class="reveal" style="transition-delay:0.07s">
      <div class="stat-number" data-count="10235" data-suffix="+">0+</div>
      <div class="stat-label">Total Clicks</div>
    </div>
    <div class="reveal" style="transition-delay:0.14s">
      <div class="stat-number" data-count="6852" data-suffix="+">0+</div>
      <div class="stat-label">Profile Views</div>
    </div>
    <div class="reveal" style="transition-delay:0.21s">
      <div class="stat-number" data-count="4824" data-suffix="+">0+</div>
      <div class="stat-label">Coach Views</div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════
     TIMELINE
═══════════════════════════════════════════════════════ -->
<section id="timeline" aria-label="PLYRCARD history and milestones">
  <p class="section-eyebrow reveal">How We Got Here</p>
  <h2 class="section-title reveal">Our Journey.</h2>

  <ol class="timeline-list" aria-label="PLYRCARD milestones timeline">

    <li class="timeline-item reveal" style="transition-delay:0.05s">
      <div class="timeline-dot-wrap">
        <div class="timeline-dot"></div>
      </div>
      <div class="timeline-body">
        <p class="timeline-year">The Idea</p>
        <h3 class="timeline-event">A Better Way to Reach Coaches</h3>
        <p class="timeline-copy">Watching athletes struggle with generic recruiting outreach, our founder asked a simple question: what if an athlete's recruiting email looked as serious as their game? That question became PLYRCARD.</p>
      </div>
    </li>

    <li class="timeline-item reveal" style="transition-delay:0.10s">
      <div class="timeline-dot-wrap">
        <div class="timeline-dot"></div>
      </div>
      <div class="timeline-body">
        <p class="timeline-year">Early Build</p>
        <h3 class="timeline-event">First Card. First Coach. First Yes.</h3>
        <p class="timeline-copy">We built the first version of PLYRCARD — a single-link athlete profile combining highlights, stats, schedule, and academics. The first athlete to use it received a response from a Division I coach within 48 hours.</p>
      </div>
    </li>

    <li class="timeline-item reveal" style="transition-delay:0.15s">
      <div class="timeline-dot-wrap">
        <div class="timeline-dot"></div>
      </div>
      <div class="timeline-body">
        <p class="timeline-year">Growing</p>
        <h3 class="timeline-event">Athletes Across Every Sport</h3>
        <p class="timeline-copy">Word spread fast. Soccer, football, basketball, volleyball, baseball, lacrosse, softball, track — athletes across every sport started using PLYRCARD to take control of their recruiting story and stand out from hundreds of identical emails.</p>
      </div>
    </li>

    <li class="timeline-item reveal" style="transition-delay:0.20s">
      <div class="timeline-dot-wrap">
        <div class="timeline-dot"></div>
      </div>
      <div class="timeline-body">
        <p class="timeline-year">Key Feature</p>
        <h3 class="timeline-event">Engagement Tracking Launches</h3>
        <p class="timeline-copy">We added real-time engagement analytics — showing athletes exactly when coaches opened, clicked, and viewed their card. For the first time, athletes could follow up with confidence, not guesswork.</p>
      </div>
    </li>

    <li class="timeline-item reveal" style="transition-delay:0.25s">
      <div class="timeline-dot-wrap">
        <div class="timeline-dot"></div>
      </div>
      <div class="timeline-body">
        <p class="timeline-year">Momentum</p>
        <h3 class="timeline-event">Coaches Start Sharing It</h3>
        <p class="timeline-copy">College coaches began recommending PLYRCARD to recruits directly — not because we paid them to, but because it made their job easier. Clean information, fast to read, easy to forward. That was the validation we needed.</p>
      </div>
    </li>

    <li class="timeline-item reveal" style="transition-delay:0.30s">
      <div class="timeline-dot-wrap">
        <div class="timeline-dot"></div>
      </div>
      <div class="timeline-body">
        <p class="timeline-year">Today</p>
        <h3 class="timeline-event">The PLYR Card Is Back. Harder. Sharper.</h3>
        <p class="timeline-copy">We heard feedback. We kept building. The PLYR Card is coming back harder, sharper, and packed with new packages made for the way athletes move today. The journey isn't over — it's just getting started.</p>
      </div>
    </li>

  </ol>
</section>


<!-- ══════════════════════════════════════════════════════
     IMAGE BREAK #1
═══════════════════════════════════════════════════════ -->
<div class="img-break" style="background-image: url('/Images/PLYRCARD-SITE-ABOUT.jpg'); background-position: center 35%;" role="img" aria-label="Runner in starting blocks on a track, preparing for a race">
  <div class="img-break-text">
    <p class="page-eyebrow" style="margin-bottom:6px">The Insight</p>
    <h3 class="img-break-headline">More Than<br>a Platform.</h3>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════
     THE PROBLEM WE SOLVE
═══════════════════════════════════════════════════════ -->
<section id="problem">
  <p class="section-eyebrow reveal">Why It Matters</p>
  <blockquote class="pull-quote reveal" style="transition-delay:0.06s">
    "Athletes shouldn't have to choose between their game and their story.<br>PLYRCARD lets them tell both."
  </blockquote>
  <p class="section-body reveal" style="transition-delay:0.12s">
    The average college coach receives hundreds of recruiting emails a day. Most are variations of the same template. No personality. No visual story. No way to quickly understand who an athlete is or what makes them different.
  </p>
  <p class="section-body reveal" style="transition-delay:0.18s; margin-top:16px;">
    PLYRCARD was built to solve that gap — a cleaner, smarter, more visual way for athletes to communicate everything coaches actually want to see: highlights, stats, schedule, academics, personality, and a direct way to respond.
  </p>
  <p class="section-body reveal" style="transition-delay:0.24s; margin-top:16px;">
    Not everyone has the same connections. But with PLYRCARD, every athlete can have the same quality of first impression.
  </p>
  <div class="btn-row reveal" style="transition-delay:0.28s">
    <a href="/" class="btn btn-accent">See the Product</a>
    <a href="/pricing" class="btn btn-outline-white">View Plans</a>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════
     WHAT MAKES IT DIFFERENT
═══════════════════════════════════════════════════════ -->
<section id="differentiator">
  <p class="section-eyebrow reveal">Why PLYRCARD</p>
  <h2 class="section-title reveal">What Sets<br>Us Apart.</h2>

  <ul class="diff-list" aria-label="PLYRCARD differentiators">

    <li class="diff-item reveal" style="transition-delay:0.05s">
      <span class="diff-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="3" width="12" height="18" rx="2"></rect><path d="M9 8h6M9 12h6M9 16h3"></path></svg></span>
      <div class="diff-text">
        <h3 class="diff-title">One Card. Your Whole Story.</h3>
        <p class="diff-copy">Stats, highlights, schedule, academics, and personality — all in a single shareable link. No more attaching five files to a cold email.</p>
      </div>
    </li>

    <li class="diff-item reveal" style="transition-delay:0.10s">
      <span class="diff-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16"></path><path d="M7 16V9"></path><path d="M12 16V5"></path><path d="M17 16v-4"></path></svg></span>
      <div class="diff-text">
        <h3 class="diff-title">Real Engagement Data</h3>
        <p class="diff-copy">Know exactly when a coach opens your card, clicks your video, and views your profile. Follow up with data, not guesswork.</p>
      </div>
    </li>

    <li class="diff-item reveal" style="transition-delay:0.15s">
      <span class="diff-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 0 0 18h1.2a1.8 1.8 0 0 0 1.2-3.1l-.3-.3a1.8 1.8 0 0 1 1.2-3.1H17a4 4 0 0 0 4-4C21 6.4 17 3 12 3Z"></path><circle cx="8" cy="10" r="1"></circle><circle cx="11" cy="7" r="1"></circle><circle cx="15" cy="8" r="1"></circle></svg></span>
      <div class="diff-text">
        <h3 class="diff-title">Custom Athlete Branding</h3>
        <p class="diff-copy">Your card looks like your brand — not a generic template. Custom graphics, colors, and layout that reflect your identity on and off the field.</p>
      </div>
    </li>

    <li class="diff-item reveal" style="transition-delay:0.20s">
      <span class="diff-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3v4M16 3v4M4 10h16"></path></svg></span>
      <div class="diff-text">
        <h3 class="diff-title">Live Schedule Integration</h3>
        <p class="diff-copy">Coaches can see your upcoming game schedule directly on your card — and filter to find events near them. Easier access means more chances to be seen in person.</p>
      </div>
    </li>

    <li class="diff-item reveal" style="transition-delay:0.25s">
      <span class="diff-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2"></rect><path d="M11 18h2"></path></svg></span>
      <div class="diff-text">
        <h3 class="diff-title">Built for Mobile</h3>
        <p class="diff-copy">Coaches recruit on their phones. PLYRCARD is designed to look and perform perfectly on any device — fast, clean, and easy to share forward.</p>
      </div>
    </li>

  </ul>
</section>


<!-- ══════════════════════════════════════════════════════
     IMAGE BREAK #2
═══════════════════════════════════════════════════════ -->
<div class="img-break" style="background-image: url('/Images/young-athlete-training.jpg'); background-position: center 28%;" role="img" aria-label="Young athlete training with focus and determination">
  <div class="img-break-text">
    <p class="page-eyebrow" style="margin-bottom:6px">Our Values</p>
    <h3 class="img-break-headline">What We<br>Stand For.</h3>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════
     VALUES
═══════════════════════════════════════════════════════ -->
<section id="values">
  <p class="section-eyebrow on-light reveal">What We Believe</p>
  <h2 class="section-title on-light reveal">Our Values</h2>
  <p class="section-body on-light reveal" style="transition-delay:0.08s">Six principles that guide every decision we make.</p>

  <div class="values-grid">

    <div class="value-card reveal" style="transition-delay:0.05s">
      <span class="value-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 21h8"></path><path d="M12 17v4"></path><path d="M7 4h10v4a5 5 0 0 1-10 0V4Z"></path><path d="M5 5H3v2a4 4 0 0 0 4 4"></path><path d="M19 5h2v2a4 4 0 0 1-4 4"></path></svg></span>
      <h3 class="value-title">Athlete First</h3>
      <p class="value-body">Every decision starts with: does this help the athlete? Not the brand, not the platform — the player.</p>
    </div>

    <div class="value-card reveal" style="transition-delay:0.10s">
      <span class="value-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4.2-4.2"></path></svg></span>
      <h3 class="value-title">Radical Clarity</h3>
      <p class="value-body">Recruiting is confusing enough. PLYRCARD cuts through the noise with clean, simple tools anyone can use on day one.</p>
    </div>

    <div class="value-card reveal" style="transition-delay:0.15s">
      <span class="value-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 10-13h-7l1-7Z"></path></svg></span>
      <h3 class="value-title">Own Your Brand</h3>
      <p class="value-body">Your story belongs to you. We give you the tools to tell it — on your terms, in your voice, with your personality front and center.</p>
    </div>

    <div class="value-card reveal" style="transition-delay:0.20s">
      <span class="value-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16"></path><path d="M7 16V9"></path><path d="M12 16V5"></path><path d="M17 16v-4"></path></svg></span>
      <h3 class="value-title">Real Visibility</h3>
      <p class="value-body">No more guessing. Know when coaches open, click, and engage so you can follow up with confidence — not anxiety.</p>
    </div>

    <div class="value-card reveal" style="transition-delay:0.25s">
      <span class="value-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path><path d="M12 3a14 14 0 0 1 0 18"></path><path d="M12 3a14 14 0 0 0 0 18"></path></svg></span>
      <h3 class="value-title">Equal Access</h3>
      <p class="value-body">The recruiting process shouldn't favor those with the most connections. We're building tools that give every athlete an equal shot.</p>
    </div>

    <div class="value-card reveal" style="transition-delay:0.30s">
      <span class="value-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19c2.5.5 4.5-.2 6-2l6-6c1.7-1.7 2.7-4 3-7-3 .3-5.3 1.3-7 3l-6 6c-1.8 1.5-2.5 3.5-2 6Z"></path><path d="M15 9h.01"></path><path d="M4 20l4-1"></path></svg></span>
      <h3 class="value-title">Always Improving</h3>
      <p class="value-body">We listen to athletes, coaches, and families constantly. The product evolves because the recruiting world never stops changing.</p>
    </div>

  </div>
</section>


<!-- ══════════════════════════════════════════════════════
     TEAM
═══════════════════════════════════════════════════════ -->
<section id="plyrs">
  <p id="sample" class="section-eyebrow reveal">The PLYRs</p>
  <h2 class="section-title reveal">Meet the<br>PLYRs</h2>
  <p class="section-body reveal" style="transition-delay:0.08s; margin-bottom:0;">
    A growing community of athletes building sharper profiles, stronger stories, and cleaner recruiting visibility.
  </p>

  <div class="plyrs-embed-card reveal" style="transition-delay:0.14s">
    <!-- Elfsight Instagram Feed | Instagram New PLYRs -->
    <script src="https://elfsightcdn.com/platform.js" async></script>
    <div class="elfsight-app-c088c003-42fd-4aa0-8d82-ce6f542e31ac" data-elfsight-app-lazy></div>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════
     PARTNERSHIPS
═══════════════════════════════════════════════════════ -->
<section id="partnerships">
  <p class="section-eyebrow reveal">In Good Company</p>
  <h2 class="section-title reveal">Trusted in<br>the Game.</h2>
  <p class="section-body reveal" style="transition-delay:0.08s">
    PLYRCARD works alongside the platforms, programs, and organizations that athletes already trust — building a full recruiting ecosystem, not just an isolated tool.
  </p>
  <div class="partner-grid reveal" style="transition-delay:0.14s" aria-label="Partner organizations">
    <?php foreach (($partnerLogoPlaceholders ?? []) as $index => $logoPath): ?>
      <div class="partner-box is-placeholder">
        <!-- Placeholder here: replace with partner logo image. -->
        <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Partner logo placeholder <?= $index + 1 ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';" />
        <span style="display:none;">Partner <?= $index + 1 ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════
     FINAL CTA
═══════════════════════════════════════════════════════ -->
<section id="final-cta" aria-labelledby="about-cta-headline">
  <p class="section-eyebrow">Join the Movement</p>
  <h2 class="section-title" id="about-cta-headline">Start Your<br>Journey</h2>
  <p class="cta-body">Build a recruiting card coaches will actually remember.</p>
  <div class="btn-row">
    <a href="/registration" class="btn-white-cta">Create Free Account</a>
    <a href="/pricing"          class="btn-outline-white-cta">See Pricing</a>
  </div>
  <p class="fine-print">No credit card required &nbsp;·&nbsp; Free plan available &nbsp;·&nbsp; Set up in minutes</p>
</section>


<!-- ══════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════ -->
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
