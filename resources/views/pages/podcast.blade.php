@php
    $activePage = 'podcast';
@endphp

@include('partials.images')

<!DOCTYPE html>
<html lang="en">
<head>
	
	<script src="https://widgets.leadconnectorhq.com/loader.js" data-resources-url="https://widgets.leadconnectorhq.com/chat-widget/loader.js" data-widget-id="6941fea74ca18223c7de491d"></script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="description" content="The PLYRCARD Show — podcast media page for athlete stories, coach conversations, and recruiting insight." />
  <title>The PLYRCARD Show — Podcast</title>

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

  
    /* ─── PODCAST PAGE ───────────────────────────────────────────── */
    #podcast-hero {
      min-height: 100svh;
      padding: calc(var(--header-h) + var(--safe-top) + 56px) 24px 88px;
      background:
        radial-gradient(circle at 16% 16%, rgba(255,92,53,0.24), transparent 30%),
        radial-gradient(circle at 84% 18%, rgba(255,92,53,0.10), transparent 28%),
        linear-gradient(180deg, rgba(255,255,255,0.022), rgba(255,255,255,0));
      display: grid;
      align-items: center;
    }

    .podcast-shell {
      width: min(1180px, 100%);
      margin: 0 auto;
      display: grid;
      grid-template-columns: minmax(0, 0.95fr) minmax(360px, 1fr);
      gap: 56px;
      align-items: center;
    }

    .podcast-kicker {
      font-family: var(--font-display);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 18px;
    }

    .podcast-title {
      font-family: var(--font-display);
      font-size: clamp(64px, 13vw, 148px);
      line-height: 0.82;
      letter-spacing: -0.06em;
      text-transform: uppercase;
      color: var(--white);
    }

    .podcast-title span { color: var(--accent); }

    .podcast-lede {
      max-width: 560px;
      margin-top: 24px;
      color: rgba(255,255,255,0.68);
      font-size: 17px;
      line-height: 1.65;
    }

    .podcast-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 34px;
    }

    .podcast-feature-card {
      border-radius: 32px;
      border: 1px solid rgba(255,255,255,0.11);
      background: radial-gradient(circle at 24% 12%, rgba(255,92,53,0.20), transparent 32%), rgba(255,255,255,0.045);
      padding: 20px;
      box-shadow: 0 32px 90px rgba(0,0,0,0.34);
      overflow: hidden;
    }

    .podcast-art {
      min-height: 440px;
      border-radius: 24px;
      background: linear-gradient(135deg, rgba(255,92,53,0.88), rgba(225,29,72,0.76)), url('/images/podcast-placeholder.jpg');
      background-size: cover;
      background-position: center;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 28px;
      position: relative;
      overflow: hidden;
    }

    .podcast-art::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.62), transparent 58%);
    }

    .podcast-art-content { position: relative; z-index: 1; }

    .podcast-art-label {
      font-family: var(--font-display);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.80);
      margin-bottom: 10px;
    }

    .podcast-art-title {
      font-family: var(--font-display);
      font-size: clamp(38px, 6vw, 68px);
      line-height: 0.86;
      text-transform: uppercase;
      color: var(--white);
    }

    .listen-strip {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-top: 14px;
    }

    .listen-pill {
      border-radius: 999px;
      background: rgba(0,0,0,0.30);
      border: 1px solid rgba(255,255,255,0.12);
      padding: 12px 14px;
      font-family: var(--font-display);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      text-align: center;
      color: rgba(255,255,255,0.76);
    }

    #latest-episodes,
    #podcast-gallery {
      padding: 96px 24px;
      background: var(--black);
      position: relative;
      overflow: hidden;
    }

    #latest-episodes::before,
    #podcast-gallery::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at 14% 10%, rgba(255,92,53,0.16), transparent 32%), radial-gradient(circle at 90% 30%, rgba(255,92,53,0.07), transparent 26%);
      pointer-events: none;
    }

    #latest-episodes > *,
    #podcast-gallery > * { position: relative; z-index: 1; }

    .podcast-section-copy {
      width: min(760px, 100%);
      margin: 0 auto 42px;
      text-align: left;
    }

    .podcast-section-copy .section-title { text-align: left; }

    .episodes-grid {
      width: min(1180px, 100%);
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 22px;
    }

    .episode-card {
      border-radius: 24px;
      border: 1px dashed rgba(255,255,255,0.16);
      background: rgba(255,255,255,0.045);
      min-height: 270px;
      padding: 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .episode-video {
      height: 130px;
      border-radius: 18px;
      background: radial-gradient(circle at 24% 20%, rgba(255,92,53,0.20), transparent 38%), rgba(255,255,255,0.05);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 18px;
    }

    .episode-play {
      width: 58px;
      height: 58px;
      border-radius: 999px;
      background: var(--accent);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 18px 42px rgba(255,92,53,0.28);
    }

    .episode-play svg {
      width: 25px;
      height: 25px;
      fill: var(--white);
      margin-left: 4px;
    }

    .episode-label {
      font-family: var(--font-display);
      color: var(--accent);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 8px;
    }

    .episode-title {
      font-family: var(--font-display);
      font-size: 26px;
      line-height: 0.95;
      color: var(--white);
      text-transform: uppercase;
    }

    .episode-copy {
      margin-top: 10px;
      font-size: 14px;
      line-height: 1.55;
      color: rgba(255,255,255,0.56);
    }

    .youtube-gallery-shell {
      width: min(1080px, 100%);
      margin: 0 auto;
      border-radius: 28px;
      overflow: hidden;
      border: 1px solid rgba(255,255,255,0.12);
      background: rgba(255,255,255,0.045);
      padding: 18px;
      box-shadow: 0 32px 90px rgba(0,0,0,0.28);
    }

    .youtube-gallery-frame {
      border-radius: 20px;
      overflow: hidden;
      background: #050505;
      min-height: 520px;
    }

    @media (max-width: 980px) {
      .podcast-shell { grid-template-columns: 1fr; gap: 38px; }
      .episodes-grid { grid-template-columns: 1fr; }
      .podcast-art { min-height: 360px; }
    }

    @media (max-width: 640px) {
      #podcast-hero { padding-top: calc(var(--header-h) + var(--safe-top) + 34px); }
      .listen-strip { grid-template-columns: 1fr; }
      .youtube-gallery-frame { min-height: 380px; }
    }

  </style>
</head>
<body>
@include('partials.navigation')

<main>
  <section id="podcast-hero" aria-labelledby="podcast-title">
    <div class="podcast-shell">
      <div class="podcast-copy">
        <p class="podcast-kicker reveal">The PLYRCARD Show</p>
        <h1 class="podcast-title reveal" id="podcast-title">Stories<br>From<br><span>The Game.</span></h1>
        <p class="podcast-lede reveal" style="transition-delay:0.08s">
          A media home for conversations with athletes, coaches, creators, trainers, and people shaping the future of recruiting.
        </p>
        <div class="podcast-actions reveal" style="transition-delay:0.14s">
          <a href="#podcast-gallery" class="btn btn-accent">Watch Episodes</a>
          <a href="/registration" class="btn btn-outline-white">Start Free</a>
        </div>
      </div>

      <aside class="podcast-feature-card reveal" style="transition-delay:0.18s" aria-label="Featured PLYRCARD Show artwork">
        <div class="podcast-art">
          <!-- Placeholder here: replace with final podcast cover image. -->
          <div class="podcast-art-content">
            <p class="podcast-art-label">Podcast Media</p>
            <h2 class="podcast-art-title">The<br>PLYRCARD<br>Show</h2>
          </div>
        </div>
        <div class="listen-strip" aria-label="Podcast platforms">
          <span class="listen-pill">YouTube</span>
          <span class="listen-pill">Spotify</span>
          <span class="listen-pill">Apple</span>
        </div>
      </aside>
    </div>
  </section>

  <section id="latest-episodes" aria-labelledby="episodes-title">
    <div class="podcast-section-copy">
      <p class="section-eyebrow reveal">Latest Conversations</p>
      <h2 class="section-title reveal" id="episodes-title">Featured<br>Episodes</h2>
      <p class="section-body reveal" style="transition-delay:0.08s">
        Highlight interviews, athlete stories, recruiting insight, and the voices behind the game.
      </p>
    </div>

    <div class="episodes-grid">
      <article class="episode-card reveal" style="transition-delay:0.05s">
        <div class="episode-video"><span class="episode-play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></div>
        <div><p class="episode-label">Episode Placeholder</p><h3 class="episode-title">Athlete Story</h3><p class="episode-copy">Use this card for a player interview, recruiting lesson, or featured clip.</p></div>
      </article>

      <article class="episode-card reveal" style="transition-delay:0.10s">
        <div class="episode-video"><span class="episode-play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></div>
        <div><p class="episode-label">Episode Placeholder</p><h3 class="episode-title">Coach Insight</h3><p class="episode-copy">Use this card for a coach perspective, recruiting tip, or program feature.</p></div>
      </article>

      <article class="episode-card reveal" style="transition-delay:0.15s">
        <div class="episode-video"><span class="episode-play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></div>
        <div><p class="episode-label">Episode Placeholder</p><h3 class="episode-title">Behind the Brand</h3><p class="episode-copy">Use this card for PLYRCARD updates, creator stories, or media announcements.</p></div>
      </article>
    </div>
  </section>

  <section id="podcast-gallery" aria-labelledby="gallery-title">
    <div class="podcast-section-copy">
      <p class="section-eyebrow reveal">Watch More</p>
      <h2 class="section-title reveal" id="gallery-title">PLYRCARD<br>Show Gallery</h2>
      <p class="section-body reveal" style="transition-delay:0.08s">
        A dedicated space for the show library. Embed the full YouTube gallery, playlist, or channel widget below.
      </p>
    </div>

    <div class="youtube-gallery-shell reveal" style="transition-delay:0.14s">
      <div class="youtube-gallery-frame">
        <!-- Elfsight YouTube Gallery | The PLYRCard Show -->
        <script src="https://elfsightcdn.com/platform.js" async></script>
        <div class="elfsight-app-cabef38f-ac42-4eba-942b-2a8c871fad33" data-elfsight-app-lazy></div>
      </div>
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
