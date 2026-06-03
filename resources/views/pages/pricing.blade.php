@php
    $activePage = 'pricing';
@endphp

@include('partials.images')

<!DOCTYPE html>
<html lang="en">
<head>
	<!-- <script src="https://widgets.leadconnectorhq.com/loader.js" data-resources-url="https://widgets.leadconnectorhq.com/chat-widget/loader.js" data-widget-id="6941fea74ca18223c7de491d"></script> -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="description" content="PLYRCARD Pricing — Choose the plan that fits your recruiting journey. Start free or go all in." />
  <title>Pricing — PLYRCARD</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <style>
    /* ─── RESET & TOKENS ────────────────────────────── */
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

    /* ─── HEADER ────────────────────────────────────── */
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

    /* ─── PAGE HERO ──────────────────────────────────── */
    #page-hero {
      padding-top: calc(var(--header-h) + var(--safe-top) + 48px);
      padding-bottom: 56px;
      padding-left: 24px;
      padding-right: 24px;
      background: var(--black);
      text-align: center;
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
      font-size: clamp(44px, 13vw, 68px);
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
      color: rgba(255,255,255,0.60);
      max-width: 320px;
      margin: 0 auto;
    }

    /* Billing toggle */
    .billing-toggle {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-top: 32px;
    }
    .toggle-label {
      font-family: var(--font-display);
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.45);
      transition: color 0.2s;
      cursor: pointer;
    }
    .toggle-label.active { color: var(--white); }
    .toggle-switch {
      position: relative;
      width: 48px; height: 28px;
      background: var(--surface);
      border-radius: 9999px;
      border: 1px solid rgba(255,255,255,0.12);
      cursor: pointer;
      flex-shrink: 0;
    }
    .toggle-switch::after {
      content: '';
      position: absolute;
      top: 3px; left: 3px;
      width: 20px; height: 20px;
      border-radius: 50%;
      background: var(--accent);
      transition: transform 0.3s var(--ease-out);
    }
    .toggle-switch.annual::after { transform: translateX(20px); }
    .save-badge {
      display: inline-flex;
      align-items: center;
      background: rgba(255,92,53,0.15);
      color: var(--accent);
      font-family: var(--font-display);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      border-radius: var(--radius-btn);
      padding: 4px 10px;
      margin-left: 4px;
    }

    /* ─── PRICING SECTION ───────────────────────────── */
    #pricing-cards {
      padding: 0 16px 72px;
      background: var(--black);
    }

    /* Sticky plan label strip */
    .plan-strip {
      position: sticky;
      top: calc(var(--header-h) + var(--safe-top));
      z-index: 10;
      background: rgba(13,13,13,0.95);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255,255,255,0.07);
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0;
      text-align: center;
      padding: 10px 0;
    }
    .plan-strip-item {
      font-family: var(--font-display);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.40);
      padding: 4px 8px;
      cursor: pointer;
      transition: color 0.2s;
    }
    .plan-strip-item.active { color: var(--accent); }

    /* Cards stack */
    .pricing-stack {
      display: flex;
      flex-direction: column;
      gap: 16px;
      padding-top: 24px;
    }

    .plan-card {
      background: var(--surface);
      border-radius: var(--radius-card);
      border: 1px solid rgba(255,255,255,0.08);
      overflow: hidden;
      position: relative;
      transition: border-color 0.3s;
    }
    .plan-card.featured {
      border-color: var(--accent);
      background: var(--surface);
    }
    .plan-card.featured::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: var(--accent);
    }

    /* Popular badge */
    .popular-badge {
      display: inline-flex;
      align-items: center;
      background: var(--accent);
      color: var(--white);
      font-family: var(--font-display);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.10em;
      text-transform: uppercase;
      border-radius: var(--radius-btn);
      padding: 4px 12px;
      margin-bottom: 12px;
    }

    /* Card header */
    .plan-header {
      padding: 28px 24px 0;
    }
    .plan-name {
      font-family: var(--font-display);
      font-size: 28px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: -0.01em;
      line-height: 1;
      margin-bottom: 4px;
    }
    .plan-tagline {
      font-size: 13px;
      color: rgba(255,255,255,0.45);
      line-height: 1.4;
    }

    /* Price block */
    .plan-price-wrap {
      padding: 20px 24px 0;
      display: flex;
      align-items: flex-end;
      gap: 4px;
    }
    .plan-price {
      font-family: var(--font-display);
      font-size: 52px;
      font-weight: 700;
      line-height: 1;
      letter-spacing: -0.03em;
    }
    .plan-price-currency {
      font-family: var(--font-display);
      font-size: 22px;
      font-weight: 700;
      margin-bottom: 6px;
    }
    .plan-price-period {
      font-size: 14px;
      color: rgba(255,255,255,0.40);
      margin-bottom: 8px;
      margin-left: 2px;
    }
    .plan-setup {
      padding: 6px 24px 0;
      font-size: 12px;
      color: rgba(255,255,255,0.35);
    }
    .plan-setup strong { color: rgba(255,255,255,0.60); }

    /* Annual price display */
    .annual-price { display: none; }
    .monthly-price { display: flex; }
    body.annual .annual-price { display: flex; }
    body.annual .monthly-price { display: none; }

    /* CTA */
    .plan-cta-wrap {
      padding: 20px 24px;
    }
    .plan-cta {
      display: block;
      width: 100%;
      text-align: center;
      font-family: var(--font-display);
      font-size: 15px;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      border-radius: var(--radius-btn);
      padding: 16px 24px;
      border: none;
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
      text-decoration: none;
    }
    .plan-cta.solid {
      background: var(--accent);
      color: var(--white);
    }
    .plan-cta.solid:hover { background: var(--accent-dark); }
    .plan-cta.outline {
      background: transparent;
      color: var(--white);
      border: 1.5px solid rgba(255,255,255,0.25);
    }
    .plan-cta.outline:hover { border-color: var(--white); background: rgba(255,255,255,0.06); }

    /* Divider */
    .plan-divider {
      height: 1px;
      background: rgba(255,255,255,0.07);
      margin: 0 24px;
    }

    /* Features list */
    .plan-features {
      padding: 20px 24px 28px;
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .feature-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      font-size: 14px;
      color: rgba(255,255,255,0.75);
      line-height: 1.4;
    }
    .feature-check {
      flex-shrink: 0;
      width: 18px; height: 18px;
      border-radius: 50%;
      background: rgba(255,92,53,0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-top: 1px;
    }
    .feature-check svg {
      width: 10px; height: 10px;
      fill: none;
      stroke: var(--accent);
      stroke-width: 2.5;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    .feature-check.muted { background: rgba(255,255,255,0.05); }
    .feature-check.muted svg { stroke: rgba(255,255,255,0.20); }
    .feature-item.dimmed { color: rgba(255,255,255,0.30); }

    /* Section labels in features */
    .feature-group-label {
      font-family: var(--font-display);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.25);
      margin-top: 4px;
      padding-bottom: 4px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    /* ─── COMPARE TABLE (desktop) ───────────────────── */
    #compare-section {
      background: var(--surface-2);
      padding: 64px 24px;
    }
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
      font-size: clamp(32px, 8vw, 48px);
      font-weight: 700;
      line-height: 0.95;
      letter-spacing: -0.02em;
      text-transform: uppercase;
      margin-bottom: 32px;
    }
    .compare-table {
      width: 100%;
      border-collapse: collapse;
    }
    .compare-table th {
      font-family: var(--font-display);
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      padding: 12px 8px;
      text-align: center;
      color: rgba(255,255,255,0.50);
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .compare-table th:first-child { text-align: left; color: rgba(255,255,255,0.30); }
    .compare-table th.accent-col { color: var(--accent); }
    .compare-table td {
      font-size: 13px;
      padding: 12px 8px;
      text-align: center;
      border-bottom: 1px solid rgba(255,255,255,0.05);
      color: rgba(255,255,255,0.65);
      vertical-align: middle;
    }
    .compare-table td:first-child { text-align: left; color: rgba(255,255,255,0.55); padding-left: 0; }
    .compare-table tr:last-child td { border-bottom: none; }
    .compare-table .check-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 20px; height: 20px;
      border-radius: 50%;
      background: rgba(255,92,53,0.14);
    }
    .compare-table .check-icon svg {
      width: 10px; height: 10px;
      fill: none;
      stroke: var(--accent);
      stroke-width: 2.5;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    .compare-table .dash {
      color: rgba(255,255,255,0.18);
      font-size: 16px;
    }
    .compare-table .section-row td {
      font-family: var(--font-display);
      font-size: 10px;
      letter-spacing: 0.10em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.20);
      padding-top: 20px;
      border-bottom: none;
    }

    /* ─── FAQ ───────────────────────────────────────── */
    #faq {
      background: var(--black);
      padding: 64px 24px;
    }
    .faq-list {
      margin-top: 36px;
      display: flex;
      flex-direction: column;
      gap: 0;
    }
    .faq-item {
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .faq-question {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 20px 0;
      cursor: pointer;
      font-family: var(--font-display);
      font-size: 17px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: -0.01em;
      user-select: none;
    }
    .faq-question:hover { color: var(--accent); }
    .faq-icon {
      flex-shrink: 0;
      width: 24px; height: 24px;
      border-radius: 50%;
      background: rgba(255,255,255,0.07);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s, transform 0.3s;
    }
    .faq-icon svg {
      width: 12px; height: 12px;
      fill: none;
      stroke: var(--white);
      stroke-width: 2.5;
      stroke-linecap: round;
      stroke-linejoin: round;
      transition: transform 0.3s var(--ease-out);
    }
    .faq-item.open .faq-icon { background: rgba(255,92,53,0.15); }
    .faq-item.open .faq-icon svg { transform: rotate(45deg); stroke: var(--accent); }
    .faq-answer {
      overflow: hidden;
      max-height: 0;
      transition: max-height 0.4s var(--ease-out);
    }
    .faq-answer-inner {
      padding-bottom: 20px;
      font-size: 15px;
      line-height: 1.65;
      color: rgba(255,255,255,0.58);
    }

    /* ─── FINAL CTA ──────────────────────────────────── */
    #final-cta {
      background: var(--accent);
      padding: 72px 24px calc(72px + var(--safe-bottom));
      text-align: center;
    }
    #final-cta .section-eyebrow { color: rgba(255,255,255,0.70); }
    #final-cta .section-title { color: var(--white); }
    .cta-body {
      font-size: 16px;
      color: rgba(255,255,255,0.78);
      line-height: 1.55;
      margin-top: 10px;
    }
    .btn-row { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; justify-content: center; }
    .btn-white-cta {
      display: inline-flex;
      align-items: center;
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
      transition: opacity 0.2s;
    }
    .btn-white-cta:hover { opacity: 0.90; }
    .btn-outline-white-cta {
      display: inline-flex;
      align-items: center;
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
      transition: border-color 0.2s;
    }
    .btn-outline-white-cta:hover { border-color: var(--white); }
    .fine-print { font-size: 12px; color: rgba(255,255,255,0.55); margin-top: 18px; }

    /* ─── FOOTER ─────────────────────────────────────── */
    #site-footer {
      background: var(--black);
      border-top: 1px solid rgba(255,255,255,0.07);
      padding: 48px 24px calc(40px + var(--safe-bottom));
    }
    .footer-logo { font-family: var(--font-display); font-size: 20px; font-weight: 700; letter-spacing: 0.04em; margin-bottom: 28px; }
    .footer-logo span { color: var(--accent); }
    .footer-nav { display: flex; flex-direction: column; gap: 14px; margin-bottom: 36px; }
    .footer-nav a { font-size: 15px; color: rgba(255,255,255,0.50); transition: color 0.2s; }
    .footer-nav a:hover { color: var(--white); }
    .footer-bottom { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 24px; }
    .footer-copy { font-size: 12px; color: rgba(255,255,255,0.25); }
    .footer-tagline { font-family: var(--font-display); font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.18); }

    /* ─── REVEAL ─────────────────────────────────────── */
    .reveal { opacity: 0; transform: translateY(20px); transition: opacity 0.7s var(--ease-out), transform 0.7s var(--ease-out); }
    .reveal.visible { opacity: 1; transform: translateY(0); }

    @media(min-width: 768px) {
      #pricing-cards { padding: 0 24px 80px; }
      .pricing-stack { flex-direction: row; align-items: flex-start; gap: 16px; }
      .plan-card { flex: 1; }
      section, #compare-section, #faq { padding: 80px 48px; }
    }

    /* ─── MINIMAL DESKTOP ENHANCEMENTS ───────────────────────────── */
    .desktop-nav {
      display: none;
      align-items: center;
      gap: 26px;
      margin-left: auto;
    }

    .desktop-nav a {
      font-family: var(--font-display);
      font-size: 13px;
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

      .logo-wrap img { height: 36px; }
      .desktop-nav { display: flex; }
      .menu-btn { display: none; }
      #mobile-nav { display: none; }

      #page-hero {
        padding-top: calc(var(--header-h) + var(--safe-top) + 76px);
        padding-bottom: 70px;
      }

      .page-hero-headline {
        font-size: clamp(72px, 6vw, 104px);
      }

      .page-hero-sub {
        max-width: 520px;
        font-size: 18px;
      }

      #pricing-cards {
        padding-left: 48px;
        padding-right: 48px;
        padding-bottom: 96px;
      }

      .plan-strip,
      .pricing-stack,
      #compare-section > *,
      #faq > *,
      #final-cta > *,
      #site-footer > * {
        width: min(1180px, 100%);
        margin-left: auto;
        margin-right: auto;
      }

      .plan-strip {
        position: static;
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: var(--radius-card);
        margin-bottom: 22px;
        overflow: hidden;
      }

      .pricing-stack {
        gap: 18px;
        align-items: stretch;
      }

      .plan-card {
        display: flex;
        flex-direction: column;
      }

      .plan-card.featured {
        transform: translateY(-8px);
        box-shadow: 0 28px 70px rgba(0,0,0,0.35);
      }

      .plan-features {
        flex: 1;
      }

      #compare-section,
      #faq {
        padding-left: 48px;
        padding-right: 48px;
      }

      .section-title {
        font-size: clamp(48px, 4vw, 70px);
      }

      .compare-table th,
      .compare-table td {
        font-size: 14px;
        padding: 15px 12px;
      }

      .faq-list {
        max-width: 860px;
      }

      .faq-question {
        font-size: 19px;
      }

      #final-cta {
        padding-left: 48px;
        padding-right: 48px;
      }

      #final-cta .section-title {
        font-size: clamp(58px, 5vw, 92px);
      }

      .cta-body {
        font-size: 18px;
      }

      #site-footer {
        padding-left: 48px;
        padding-right: 48px;
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

  </style>
</head>
<body>
<!-- ══════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════ -->
@include('partials.navigation')


<!-- ══════════════════════════════════════════════
     PAGE HERO
═══════════════════════════════════════════════ -->
<section id="page-hero" aria-labelledby="pricing-title">
  <p class="page-eyebrow reveal">Simple, Transparent Pricing</p>
  <h1 class="page-hero-headline reveal" id="pricing-title">Choose Your Plan</h1>
  <p class="page-hero-sub reveal" style="transition-delay:0.08s">
    Start free and upgrade when you're ready. Every plan includes a recruiting card built to get you noticed.
  </p>

  <!-- Billing toggle -->
  <!-- <div class="billing-toggle reveal" style="transition-delay:0.14s">
    <span class="toggle-label active" id="label-monthly" aria-label="Switch to monthly billing">Monthly</span>
    <div class="toggle-switch" id="billing-toggle" role="switch" aria-checked="false" tabindex="0" aria-label="Toggle annual billing"></div>
    <span class="toggle-label" id="label-annual" aria-label="Switch to annual billing">
      Annual <span class="save-badge">Save 20%</span>
    </span>
  </div> -->
</section>


<!-- ══════════════════════════════════════════════
     PRICING CARDS
═══════════════════════════════════════════════ -->
<div id="pricing-cards">

  <!-- Sticky strip -->
  <div class="plan-strip" role="list" aria-label="Plan names">
    <div class="plan-strip-item" role="listitem">Free</div>
    <div class="plan-strip-item active" role="listitem">Plyr Plus</div>
    <div class="plan-strip-item" role="listitem">My Journey</div>
  </div>

  <div class="pricing-stack">

    <!-- ── PLAN 1: FREE ────────────────────────────────── -->
    <article class="plan-card reveal" aria-label="Free plan">
      <div class="plan-header">
        <div class="plan-name">Free</div>
        <div class="plan-tagline">Get started with the basics. Build your card and start sharing.</div>
      </div>

      <!-- Monthly price -->
      <div class="plan-price-wrap monthly-price" aria-label="Free">
        <span class="plan-price-currency">$</span>
        <span class="plan-price">0</span>
        <span style="display:flex;flex-direction:column;justify-content:flex-end;margin-bottom:10px;">
          <span style="font-family:var(--font-display);font-size:18px;font-weight:700;line-height:1;">.00</span>
          <span class="plan-price-period">/mo</span>
        </span>
      </div>
      <!-- Annual price (same — free is free) -->
      <div class="plan-price-wrap annual-price" aria-label="Free">
        <span class="plan-price-currency">$</span>
        <span class="plan-price">0</span>
        <span style="display:flex;flex-direction:column;justify-content:flex-end;margin-bottom:10px;">
          <span style="font-family:var(--font-display);font-size:18px;font-weight:700;line-height:1;">.00</span>
          <span class="plan-price-period">/mo</span>
        </span>
      </div>
      <p class="plan-setup">No set-up fee &nbsp;· No credit card required</p>

      <div class="plan-cta-wrap">
        <a href="/registration?utm_plan=free" class="plan-cta outline">Get Started Free</a>
      </div>

      <div class="plan-divider"></div>

      <ul class="plan-features" aria-label="Rookie plan features">
        <li class="feature-group-label">Card Basics</li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          PLYRCARD recruiting card
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Athlete profile link
        </li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Stats, schedule &amp; highlights
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Bio &amp; academic info
        </li>
        <li class="feature-group-label" style="margin-top:8px">Outreach</li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Share via email &amp; social
        </li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Email open tracking
        </li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Coach engagement analytics
        </li>
        <li class="feature-group-label" style="margin-top:8px">Support</li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Email support
        </li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Priority support
        </li>
      </ul>
    </article>


    <!-- ── PLAN 2: ROOKIE PLUS (FEATURED) ───────────────── -->
    <article class="plan-card featured reveal" style="transition-delay:0.08s" aria-label="Rookie Plus plan — most popular">
      <div class="plan-header">
        <div class="popular-badge">Most Popular</div>
        <div class="plan-name">Plyr Plus</div>
        <div class="plan-tagline">The full PLYRCARD experience with tracking and analytics.</div>
      </div>

      <div class="plan-price-wrap monthly-price" aria-label="$20.99 per month">
        <span class="plan-price-currency">$</span>
        <span class="plan-price">10</span>
        <span style="display:flex;flex-direction:column;justify-content:flex-end;margin-bottom:10px;">
          <span style="font-family:var(--font-display);font-size:18px;font-weight:700;line-height:1;">.99</span>
          <span class="plan-price-period">/mo</span>
        </span>
      </div>
      <div class="plan-price-wrap annual-price" aria-label="$16.79 per month billed annually">
        <span class="plan-price-currency">$</span>
        <span class="plan-price">8</span>
        <span style="display:flex;flex-direction:column;justify-content:flex-end;margin-bottom:10px;">
          <span style="font-family:var(--font-display);font-size:18px;font-weight:700;line-height:1;">.80</span>
          <span class="plan-price-period">/mo</span>
        </span>
      </div>
      <p class="plan-setup">
        <strong class="monthly-price" style="display:inline;">$50 set-up fee</strong>
        <strong class="annual-price" style="display:none;">$50 set-up fee</strong>
        &nbsp;· Limited time
      </p>

      <div class="plan-cta-wrap">
        <a href="/registration?utm_plan=plyr-plus" class="plan-cta solid">Get PLYR PLUS</a>
      </div>

      <div class="plan-divider"></div>

      <ul class="plan-features" aria-label="Rookie Plus plan features">
        <li class="feature-group-label">Everything in Free, plus:</li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          PLYR recruiting card
        </li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Custom branded graphics
        </li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Email open tracking
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Click &amp; profile view tracking
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Coach engagement dashboard
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Multiple email sends/month
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Schedule &amp; highlights page
        </li>
        <li class="feature-group-label" style="margin-top:8px">Support</li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Priority email support
        </li>
        <li class="feature-item dimmed">
          <span class="feature-check muted"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          1-on-1 onboarding call
        </li>
      </ul>
    </article>


    <!-- ── PLAN 3: MY JOURNEY ───────────────────────────── -->
    <article class="plan-card reveal" style="transition-delay:0.16s" aria-label="My Journey plan">
      <div class="plan-header">
        <div class="plan-name">My Journey</div>
        <div class="plan-tagline">Full-service recruiting presence. The complete athlete brand package.</div>
      </div>

      <div class="plan-price-wrap monthly-price" aria-label="$49 per month">
        <span class="plan-price-currency">$</span>
        <span class="plan-price">49</span>
        <span style="display:flex;flex-direction:column;justify-content:flex-end;margin-bottom:10px;">
          <span style="font-family:var(--font-display);font-size:18px;font-weight:700;line-height:1;">.00</span>
          <span class="plan-price-period">/mo</span>
        </span>
      </div>
      <div class="plan-price-wrap annual-price" aria-label="$49 per month">
        <span class="plan-price-currency">$</span>
        <span class="plan-price">49</span>
        <span style="display:flex;flex-direction:column;justify-content:flex-end;margin-bottom:10px;">
          <span style="font-family:var(--font-display);font-size:18px;font-weight:700;line-height:1;">.00</span>
          <span class="plan-price-period">/mo</span>
        </span>
      </div>
      <p class="plan-setup">
        <strong class="monthly-price" style="display:inline;">$200 set-up fee</strong>
        <strong class="annual-price" style="display:inline;">$200 set-up fee</strong>
        &nbsp;· Limited time
      </p>

      <div class="plan-cta-wrap">
        <a href="/registration?utm_plan=my-journey" class="plan-cta outline">Get My Journey</a>
        <!-- My Journey: fixed $49/mo — no annual discount -->
      </div>

      <div class="plan-divider"></div>

      <ul class="plan-features" aria-label="My Journey plan features">
        <li class="feature-group-label">Everything in Rookie Plus, plus:</li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Full brand identity package
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Custom graphics &amp; design
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Unlimited email outreach
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Advanced coach analytics
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          1-on-1 onboarding call
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Recruiting strategy session
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Video highlight integration
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Monthly card refresh
        </li>
        <li class="feature-group-label" style="margin-top:8px">Support</li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Dedicated account support
        </li>
        <li class="feature-item">
          <span class="feature-check"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span>
          Priority response time
        </li>
      </ul>
    </article>

  </div><!-- /pricing-stack -->
</div><!-- /pricing-cards -->


<!-- ══════════════════════════════════════════════
     COMPARE TABLE
═══════════════════════════════════════════════ -->
<section id="compare-section" aria-labelledby="compare">
  <p class="section-eyebrow reveal">Side by Side</p>
  <h2 class="section-title reveal" id="compare">Compare<br>Plans</h2>

  <div style="overflow-x:auto;" role="region" aria-label="Plan comparison table" tabindex="0">
    <table class="compare-table" aria-label="Feature comparison across Rookie, Rookie Plus, and My Journey plans">
      <thead>
        <tr>
          <th scope="col">Feature</th>
          <th scope="col">Free</th>
          <th scope="col" class="accent-col">PLYR Plus</th>
          <th scope="col">My Journey</th>
        </tr>
      </thead>
      <tbody>
        <tr class="section-row"><td colspan="4">Card</td></tr>
        <tr><td>Recruiting card</td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Custom link</td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Custom branded graphics</td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Full brand package</td>
          <td><span class="dash">—</span></td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>

        <tr class="section-row"><td colspan="4">Analytics</td></tr>
        <tr><td>Email open tracking</td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Click tracking</td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Coach engagement dashboard</td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Advanced analytics</td>
          <td><span class="dash">—</span></td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>

        <tr class="section-row"><td colspan="4">Outreach</td></tr>
        <tr><td>Share via email &amp; social</td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Multiple monthly sends</td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Unlimited outreach</td>
          <td><span class="dash">—</span></td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>

        <tr class="section-row"><td colspan="4">Support</td></tr>
        <tr><td>Email support</td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Priority support</td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>1-on-1 onboarding</td>
          <td><span class="dash">—</span></td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
        <tr><td>Dedicated account support</td>
          <td><span class="dash">—</span></td>
          <td><span class="dash">—</span></td>
          <td><span class="check-icon"><svg viewBox="0 0 12 12"><polyline points="1.5,6 5,9.5 10.5,2.5"/></svg></span></td>
        </tr>
      </tbody>
    </table>
  </div>
</section>


<!-- ══════════════════════════════════════════════
     FAQ
═══════════════════════════════════════════════ -->
<section id="faq" aria-labelledby="faq-title">
  <p class="section-eyebrow reveal">Got Questions</p>
  <h2 class="section-title reveal" id="faq-title">We've Got<br>Answers.</h2>

  <div class="faq-list" role="list">

    <div class="faq-item reveal" role="listitem">
      <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
        What is the set-up fee for?
        <span class="faq-icon"><svg viewBox="0 0 12 12"><line x1="6" y1="1" x2="6" y2="11"/><line x1="1" y1="6" x2="11" y2="6"/></svg></span>
      </div>
      <div class="faq-answer" role="region">
        <p class="faq-answer-inner">The set-up fee covers our team building and configuring your custom PLYRCARD — including graphics, profile setup, and initial card design. It's a one-time fee and is currently discounted for new accounts.</p>
      </div>
    </div>

    <div class="faq-item reveal" style="transition-delay:0.05s" role="listitem">
      <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
        Can I cancel anytime?
        <span class="faq-icon"><svg viewBox="0 0 12 12"><line x1="6" y1="1" x2="6" y2="11"/><line x1="1" y1="6" x2="11" y2="6"/></svg></span>
      </div>
      <div class="faq-answer" role="region">
        <p class="faq-answer-inner">Yes. Monthly plans can be cancelled at any time with no penalties. Annual plans can be cancelled before the next renewal date. We don't believe in locking athletes in.</p>
      </div>
    </div>

    <div class="faq-item reveal" style="transition-delay:0.10s" role="listitem">
      <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
        Can I upgrade my plan later?
        <span class="faq-icon"><svg viewBox="0 0 12 12"><line x1="6" y1="1" x2="6" y2="11"/><line x1="1" y1="6" x2="11" y2="6"/></svg></span>
      </div>
      <div class="faq-answer" role="region">
        <p class="faq-answer-inner">Absolutely. You can upgrade from Free to Rookie Plus or My Journey at any time. Your card stays intact — we just unlock more features and enhance your setup.</p>
      </div>
    </div>

    <div class="faq-item reveal" style="transition-delay:0.15s" role="listitem">
      <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
        What sports does PLYRCARD support?
        <span class="faq-icon"><svg viewBox="0 0 12 12"><line x1="6" y1="1" x2="6" y2="11"/><line x1="1" y1="6" x2="11" y2="6"/></svg></span>
      </div>
      <div class="faq-answer" role="region">
        <p class="faq-answer-inner">PLYRCARD works for any sport with a recruiting process — soccer, football, basketball, volleyball, softball, baseball, lacrosse, track, swimming, tennis, and more. If you're recruiting, we're built for you.</p>
      </div>
    </div>

    <div class="faq-item reveal" style="transition-delay:0.20s" role="listitem">
      <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
        How does email tracking work?
        <span class="faq-icon"><svg viewBox="0 0 12 12"><line x1="6" y1="1" x2="6" y2="11"/><line x1="1" y1="6" x2="11" y2="6"/></svg></span>
      </div>
      <div class="faq-answer" role="region">
        <p class="faq-answer-inner">When a coach opens your outreach email or clicks your card link, PLYRCARD captures that signal and shows it in your dashboard. You'll know who's engaging so you can follow up at the right moment.</p>
      </div>
    </div>

    <div class="faq-item reveal" style="transition-delay:0.25s" role="listitem">
      <div class="faq-question" role="button" tabindex="0" aria-expanded="false">
        Is there a free plan or trial?
        <span class="faq-icon"><svg viewBox="0 0 12 12"><line x1="6" y1="1" x2="6" y2="11"/><line x1="1" y1="6" x2="11" y2="6"/></svg></span>
      </div>
      <div class="faq-answer" role="region">
        <p class="faq-answer-inner">Yes — you can create a free account and start building your profile. Upgrade to a paid plan when you're ready to unlock full card creation, sharing, and coach tracking features.</p>
      </div>
    </div>

  </div>
</section>


<!-- ══════════════════════════════════════════════
     FINAL CTA
═══════════════════════════════════════════════ -->
<section id="final-cta" aria-labelledby="pricing-cta-title">
  <p class="section-eyebrow">Ready to Stand Out</p>
  <h2 class="section-title" id="pricing-cta-title">Start Your<br>Journey</h2>
  <p class="cta-body">Build a recruiting card coaches will actually remember.</p>
  <div class="btn-row">
    <a href="/registration?utm_plan=free" class="btn-white-cta">Create Free Account</a>
    <a href="/book-demo" class="btn-outline-white-cta">Book a Demo</a>
  </div>
  <p class="fine-print">No credit card required &nbsp;·&nbsp; Free plan available &nbsp;·&nbsp; Set up in minutes</p>
</section>


<!-- ══════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════ -->
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