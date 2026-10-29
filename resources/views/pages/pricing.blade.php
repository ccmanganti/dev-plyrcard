@php
    $activePage = 'pricing';
    $plans = (array) config('plyrcard-registration.plans', []);
    $catalog = (array) config('plyrcard-points.catalog', []);
    $pointPacks = collect((array) config('plyrcard-points.point_packs', []))
        ->filter(fn (array $pack): bool => (bool) ($pack['enabled'] ?? true))
        ->keyBy(fn (array $pack, string $key): string => $key);

    $myJourneyCents = (int) data_get($plans, 'my-journey.recurring_amount_cents', 4900);
    $jumpstartCents = (int) data_get($plans, 'jumpstart.setup_fee_cents', 14900);
    $amplifyCents = (int) data_get($plans, 'amplify.setup_fee_cents', 55000);
    $jumpstartPoints = (int) config('plyrcard-points.packages.jumpstart', 320);
    $amplifyPoints = (int) config('plyrcard-points.packages.amplify', 1280);
    $customPointCents = max(1, (int) config('plyrcard-points.custom_point_purchase.price_per_point_cents', 100));
    $customPointMin = max(1, (int) config('plyrcard-points.custom_point_purchase.min_points', 1));
    $customPointMax = max($customPointMin, (int) config('plyrcard-points.custom_point_purchase.max_points', 1000));
    $customPointsEnabled = (bool) config('plyrcard-points.custom_point_purchase.enabled', true);

    $services = [
        'outreach' => [
            'label' => data_get($catalog, 'outreach.name', 'Coach Outreach Program'),
            'points' => (int) data_get($catalog, 'outreach.points', 80),
            'description' => 'Cast a wide net. Reach coaches in every division from D1 to JUCO, nationwide or by state and region.',
            'icon' => 'send',
        ],
        'reel' => [
            'label' => data_get($catalog, 'reel.name', 'Highlight Reel'),
            'points' => (int) data_get($catalog, 'reel.points', 90),
            'description' => 'Just send us your timestamps. We cut your best moments from VEO or game film and do the rest.',
            'icon' => 'video',
        ],
        'graphic' => [
            'label' => data_get($catalog, 'graphic.name', 'Custom Graphic'),
            'points' => (int) data_get($catalog, 'graphic.points', 50),
            'description' => 'Commitment posts, CV graphics, game day and stat cards. 100% custom, never AI generated.',
            'icon' => 'image',
        ],
        'production_hour' => [
            'label' => data_get($catalog, 'production_hour.name', 'Rep Hour'),
            'points' => (int) data_get($catalog, 'production_hour.points', 50),
            'description' => 'Have your rep review your profile, or hand off the work when you are short on time.',
            'icon' => 'clock',
        ],
    ];

    $money = static function (int $cents): string {
        $amount = $cents / 100;
        return '$' . ((int) $amount == $amount ? number_format($amount, 0) : number_format($amount, 2));
    };

    $freeHref = route('marketing.registration', ['plan' => 'free']);
    $journeyHref = route('marketing.registration', ['plan' => 'my-journey']);
    $jumpstartHref = route('marketing.registration', ['plan' => 'jumpstart']);
    $amplifyHref = route('marketing.registration', ['plan' => 'amplify']);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="description" content="PLYRCARD pricing: start free, join My Journey, then add PLYR Points for recruiting services whenever you need them.">
<title>PLYRCARD Pricing</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Oswald:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--pc-bg:#0b0b0c;--pc-panel:#161617;--pc-panel2:#111112;--pc-line:#2a2a2c;--pc-line2:#3a3a3d;--pc-text:#f2f2f2;--pc-muted:#a1a1a6;--pc-dim:#8a8a8f;--pc-accent:#ee6a47;--pc-accent2:#ff633f;--pc-blue:#5fa0f5;--pc-gold:#e9c46a;--pc-green:#8ee0ab;--pc-radius:26px;--pc-max:1240px}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body.plyrcard-pricing-page{margin:0;background:var(--pc-bg);color:var(--pc-text);font-family:Inter,system-ui,sans-serif;font-size:17px;line-height:1.55;-webkit-font-smoothing:antialiased;overflow-x:hidden}
body.plyrcard-pricing-page a{color:inherit;text-decoration:none}
body.plyrcard-pricing-page button{font:inherit}
.pc-wrap{width:min(var(--pc-max),calc(100% - 48px));margin-inline:auto}
.pc-kicker{font-family:Oswald,sans-serif;font-size:14px;letter-spacing:.2em;text-transform:uppercase;color:var(--pc-accent)}
.pc-display{font-family:"Bebas Neue",sans-serif;font-weight:400;text-transform:uppercase}
.pc-section{padding:80px 0}
.pc-section-head{display:flex;flex-direction:column;gap:5px;margin-bottom:28px}
.pc-section-head h2{margin:0;font-family:"Bebas Neue",sans-serif;font-size:clamp(40px,5vw,60px);font-weight:400;line-height:1;text-transform:uppercase}
.pc-section-head p{margin:0;color:var(--pc-muted);max-width:700px}
.pc-btn{min-height:58px;padding:0 28px;border-radius:999px;border:1.5px solid var(--pc-line2);display:inline-flex;align-items:center;justify-content:center;gap:8px;font-family:Oswald,sans-serif;font-size:18px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;background:transparent;color:var(--pc-text);transition:.18s ease}
.pc-btn:hover{border-color:#666;background:rgba(255,255,255,.035)}
.pc-btn.primary{border-color:var(--pc-accent);background:var(--pc-accent);color:#fff}
.pc-btn.primary:hover{background:#ff7758}
.pc-check{width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#3a2620;flex:0 0 24px;color:var(--pc-accent);font-size:14px;font-weight:900}
.pc-card{background:var(--pc-panel);border:1px solid var(--pc-line);border-radius:var(--pc-radius)}
.pc-hero{padding:148px 24px 54px;text-align:center}
.pc-hero .pc-kicker{margin-bottom:16px}
.pc-hero h1{margin:0 auto;max-width:13ch;font-family:"Bebas Neue",sans-serif;font-size:clamp(60px,8vw,104px);font-weight:400;line-height:.92;letter-spacing:.01em;text-transform:uppercase}
.pc-hero p{margin:20px auto 0;max-width:50ch;color:var(--pc-muted);font-size:18px}
.pc-hero-steps{list-style:none;margin:20px 0 0;padding:0;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.pc-hero-steps a{min-height:44px;padding:0 18px;border:1.5px solid var(--pc-line2);border-radius:999px;display:flex;align-items:center;gap:9px;font-family:Oswald,sans-serif;font-size:14px;letter-spacing:.08em;text-transform:uppercase}
.pc-hero-steps b{color:var(--pc-accent)}
.pc-plan-grid{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:24px;align-items:stretch}
.pc-plan-card{padding:36px;display:flex;flex-direction:column;gap:20px}
.pc-plan-card.featured{border-color:var(--pc-accent)}
.pc-badge{align-self:flex-start;padding:5px 14px;border-radius:999px;background:var(--pc-accent);font-family:Oswald,sans-serif;font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#fff}
.pc-plan-card h3{font-family:"Bebas Neue",sans-serif;font-size:52px;font-weight:400;line-height:.95;text-transform:uppercase;margin:0}
.pc-plan-card .desc{color:var(--pc-muted);min-height:52px}
.pc-price{display:flex;align-items:baseline;gap:6px}
.pc-price .currency{font-family:"Bebas Neue",sans-serif;font-size:30px}
.pc-price strong{font-family:"Bebas Neue",sans-serif;font-size:82px;line-height:.9;font-weight:400}
.pc-price .period{color:var(--pc-muted)}
.pc-subnote{font-size:14px;color:var(--pc-muted)}
.pc-feature-box{border-top:1px solid var(--pc-line);padding-top:20px;display:grid;gap:13px}
.pc-feature-title{padding-bottom:9px;border-bottom:1px solid var(--pc-line);font-family:Oswald,sans-serif;font-size:12px;letter-spacing:.15em;text-transform:uppercase;color:var(--pc-dim)}
.pc-feature{display:flex;gap:11px;align-items:flex-start}
.pc-feature-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px 24px}
.pc-points-band{background:var(--pc-panel2);border-block:1px solid #1f1f21}
.pc-points-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:30px}
.pc-never{padding:9px 16px;border-radius:999px;background:#17271d;color:var(--pc-green);font-family:Oswald,sans-serif;font-size:14px;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}
.pc-package-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}
.pc-package{padding:34px;display:flex;flex-direction:column;gap:18px}
.pc-package.jumpstart{border-color:#3d7fd6}.pc-package.amplify{border-color:#d9b24f}
.pc-package .pc-badge.blue{background:#3d8bf0;color:#071522}.pc-package .pc-badge.gold{background:var(--pc-gold);color:#0b0b0c}
.pc-package h3{margin:0;font-family:"Bebas Neue",sans-serif;font-size:52px;font-weight:400;line-height:.95;text-transform:uppercase}
.pc-package .desc{color:var(--pc-muted)}
.pc-points-big{font-family:"Bebas Neue",sans-serif;font-size:70px;line-height:.9;color:var(--pc-blue)}.pc-package.amplify .pc-points-big{color:var(--pc-gold)}
.pc-points-big small{font-size:28px;color:var(--pc-text)}
.pc-sale{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap}
.pc-sale .retail{font-family:"Bebas Neue",sans-serif;font-size:32px;color:#777;text-decoration:line-through;text-decoration-color:var(--pc-accent);text-decoration-thickness:3px}
.pc-sale strong{font-family:"Bebas Neue",sans-serif;font-size:62px;line-height:.9;font-weight:400}
.pc-sale span{color:var(--pc-muted);font-size:14px}
.pc-save{padding:4px 10px;border-radius:999px;background:var(--pc-accent);color:#fff;font-family:Oswald,sans-serif;font-size:12px;letter-spacing:.07em}
.pc-package-list{border-top:1px solid var(--pc-line);padding-top:18px;display:grid;gap:10px;font-size:14px}
.pc-package-list .row{display:flex;justify-content:space-between;gap:14px}.pc-package-list .row span:last-child{font-family:Oswald;color:var(--pc-dim)}
.pc-topups{margin-top:24px;padding:20px 26px;display:flex;align-items:center;gap:16px 20px;flex-wrap:wrap}
.pc-topups h3{margin:0;font-family:"Bebas Neue",sans-serif;font-size:28px;font-weight:400;text-transform:uppercase}
.pc-topup-pills{display:flex;gap:9px;flex-wrap:wrap;flex:1}
.pc-topup-pill{padding:9px 16px;border:1.5px solid var(--pc-line2);border-radius:999px;font-family:Oswald,sans-serif;font-size:15px}
.pc-topup-pill b{font-family:"Bebas Neue",sans-serif;font-size:22px;color:var(--pc-accent);margin-right:8px}
.pc-topups small{color:var(--pc-dim)}
.pc-service-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
.pc-service{padding:26px;display:flex;flex-direction:column;min-height:300px;gap:9px}
.pc-service-icon{width:29px;height:29px;color:var(--pc-accent)}
.pc-service h3{margin:0;font-family:Oswald,sans-serif;font-size:17px;font-weight:500;letter-spacing:.04em;text-transform:uppercase}
.pc-service .points{font-family:"Bebas Neue",sans-serif;font-size:54px;line-height:.9;color:var(--pc-accent)}.pc-service .points small{font-size:20px;color:var(--pc-text)}
.pc-service p{margin:0;color:var(--pc-muted);font-size:14px;flex:1}
.pc-service .pc-btn{min-height:44px;font-size:14px;padding:0 16px}
.pc-how{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:20px}
.pc-how-item{display:flex;gap:13px}.pc-how-num{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:var(--pc-accent);font-family:"Bebas Neue",sans-serif;font-size:22px}
.pc-how-item h4{margin:0;font-family:Oswald;font-weight:500;text-transform:uppercase}.pc-how-item p{margin:3px 0 0;color:var(--pc-muted);font-size:14px}
.pc-calculator{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(300px,.8fr);gap:24px;align-items:stretch}
.pc-calc-list{padding:14px 30px}
.pc-calc-row{display:grid;grid-template-columns:minmax(210px,1fr) auto 90px;gap:18px;align-items:center;padding:18px 0;border-bottom:1px solid var(--pc-line)}
.pc-calc-label strong{display:block;font-family:Oswald;font-size:17px;font-weight:500;text-transform:uppercase}.pc-calc-label small{color:var(--pc-dim)}
.pc-stepper{display:flex;align-items:center;gap:8px}.pc-stepper button{width:40px;height:40px;border-radius:50%;border:1.5px solid var(--pc-line2);background:transparent;color:var(--pc-text);font-size:20px;cursor:pointer}.pc-stepper button.plus{background:var(--pc-accent);border-color:var(--pc-accent);color:#fff}.pc-stepper output{width:34px;text-align:center;font-family:"Bebas Neue";font-size:27px}
.pc-calc-sub{text-align:right;font-family:Oswald;font-size:16px}
.pc-calc-total{display:flex;justify-content:space-between;align-items:baseline;padding:18px 0 8px}.pc-calc-total span{font-family:Oswald;font-size:14px;letter-spacing:.14em;text-transform:uppercase;color:var(--pc-dim)}.pc-calc-total strong{font-family:"Bebas Neue";font-size:42px;color:var(--pc-accent);font-weight:400}
.pc-rec{padding:30px;border-color:var(--pc-accent);display:flex;flex-direction:column;gap:12px}
.pc-rec h3{margin:0;font-family:"Bebas Neue";font-size:40px;font-weight:400;line-height:.98;text-transform:uppercase}
.pc-rec-price{display:flex;align-items:baseline;gap:8px}.pc-rec-price .strike{font-family:"Bebas Neue";font-size:27px;color:#777;text-decoration:line-through;text-decoration-color:var(--pc-accent)}.pc-rec-price strong{font-family:"Bebas Neue";font-size:60px;font-weight:400;line-height:.9}
.pc-rec .save{align-self:flex-start;padding:4px 10px;background:var(--pc-accent);border-radius:999px;font-family:Oswald;font-size:12px}
.pc-rec p{margin:0;color:var(--pc-muted);font-size:14px}.pc-rec .green{color:var(--pc-green);font-weight:600}.pc-rec .pc-btn{margin-top:auto}
.pc-compare{overflow:auto}
.pc-compare table{width:100%;min-width:600px;border-collapse:collapse;background:var(--pc-panel);border:1px solid var(--pc-line);border-radius:var(--pc-radius);overflow:hidden}
.pc-compare th,.pc-compare td{padding:15px 24px;border-top:1px solid var(--pc-line);font-size:14px}.pc-compare thead th{border-top:0;font-family:Oswald;text-transform:uppercase;letter-spacing:.08em;color:var(--pc-dim)}.pc-compare th:first-child,.pc-compare td:first-child{text-align:left}.pc-compare th:not(:first-child),.pc-compare td:not(:first-child){text-align:center}.pc-compare th:last-child{color:var(--pc-accent)}.pc-yes{color:var(--pc-accent);font-weight:800}.pc-no{color:#555}
.pc-faq{width:min(900px,calc(100% - 48px));margin-inline:auto;padding:30px 0 88px}.pc-faq .pc-section-head{text-align:center;align-items:center}
.pc-faq details{background:var(--pc-panel);border:1px solid var(--pc-line);border-radius:16px;margin-bottom:10px;padding:0 20px}.pc-faq summary{list-style:none;min-height:58px;display:flex;justify-content:space-between;align-items:center;gap:16px;cursor:pointer;font-weight:600;font-size:15px}.pc-faq summary::-webkit-details-marker{display:none}.pc-faq .plus{color:var(--pc-accent);font-size:24px;transition:.2s}.pc-faq details[open] .plus{transform:rotate(45deg)}.pc-faq details p{margin:0 0 18px;color:var(--pc-muted);font-size:14px;line-height:1.65}
.pc-final{border-top:1px solid #1f1f21;text-align:center;padding:84px 24px}.pc-final h2{margin:8px auto 0;max-width:14ch;font-family:"Bebas Neue";font-size:clamp(50px,6vw,80px);font-weight:400;line-height:.95;text-transform:uppercase}.pc-final p{color:var(--pc-muted);margin:15px auto 0}.pc-final-actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:22px}.pc-final small{display:block;color:var(--pc-dim);margin-top:12px}
.pc-dialog[hidden]{display:none}.pc-dialog{position:fixed;inset:0;z-index:999;display:grid;place-items:center;padding:24px;background:rgba(5,5,6,.8)}.pc-dialog-card{width:min(560px,100%);max-height:85vh;overflow:auto;padding:28px;background:var(--pc-panel);border:1px solid var(--pc-line);border-radius:22px}.pc-dialog-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.pc-dialog-head h3{margin:4px 0 0;font-family:"Bebas Neue";font-size:38px;font-weight:400;text-transform:uppercase}.pc-dialog-close{width:40px;height:40px;border-radius:50%;border:1px solid var(--pc-line2);background:transparent;color:#fff;cursor:pointer}.pc-dialog-copy{color:var(--pc-muted);margin:18px 0 0}.pc-dialog-note{margin-top:16px;padding:14px;border-radius:12px;background:#111;color:#ccc;font-size:14px}
body.plyrcard-pricing-page #site-header.plyrcard-site-header{background:#0b0b0c!important;border-bottom-color:#1f1f21!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important}
body.plyrcard-pricing-page #site-footer{border-top:1px solid #1f1f21;background:var(--pc-bg);padding:28px max(24px,calc((100vw - var(--pc-max))/2));color:var(--pc-dim)}
body.plyrcard-pricing-page #site-footer .footer-logo{font-family:"Bebas Neue";font-size:26px;color:var(--pc-text)}body.plyrcard-pricing-page #site-footer .footer-logo span{color:var(--pc-accent)}
body.plyrcard-pricing-page #site-footer .footer-nav{display:flex;gap:18px;flex-wrap:wrap;font-family:Oswald;font-size:13px;text-transform:uppercase}body.plyrcard-pricing-page #site-footer .footer-nav a.active{color:var(--pc-accent)}
body.plyrcard-pricing-page #site-footer .footer-bottom{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;font-size:12px;margin-top:18px}
@media(max-width:900px){.pc-plan-grid,.pc-package-grid,.pc-calculator{grid-template-columns:1fr}.pc-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.pc-how{grid-template-columns:1fr}.pc-rec .pc-btn{margin-top:10px}.pc-plan-card .desc{min-height:0}}
@media(max-width:620px){.pc-wrap{width:min(100% - 28px,var(--pc-max))}.pc-hero{padding:118px 16px 42px}.pc-section{padding:58px 0}.pc-plan-card,.pc-package{padding:25px}.pc-feature-columns{grid-template-columns:1fr}.pc-points-head{align-items:flex-start;flex-direction:column}.pc-service-grid{grid-template-columns:1fr}.pc-calc-list{padding:8px 20px}.pc-calc-row{grid-template-columns:1fr auto;gap:10px}.pc-calc-sub{grid-column:2}.pc-topups{padding:18px}.pc-price strong{font-size:70px}.pc-faq{width:calc(100% - 28px)}}
</style>
</head>
<body class="plyrcard-pricing-page">
@include('partials.navigation')

<main>
<section class="pc-hero">
  <div class="pc-kicker">Simple, transparent pricing</div>
  <h1>Your journey.<br>Your points.<br>Your way.</h1>
  <p>Start free. Join My Journey to run your own recruiting. Add PLYR Points when you want our team to build for you, and spend them on whatever you need.</p>
  <ol class="pc-hero-steps">
    <li><a href="#plans"><b>1</b> Pick your plan</a></li>
    <li><a href="#points"><b>2</b> Add points</a></li>
    <li><a href="#mix"><b>3</b> Spend them your way</a></li>
  </ol>
</section>

<section id="plans" class="pc-section">
  <div class="pc-wrap">
    <div class="pc-section-head"><span class="pc-kicker">Step 1</span><h2>Pick your plan</h2></div>
    <div class="pc-plan-grid">
      <article class="pc-card pc-plan-card">
        <div><h3>Free</h3><div class="desc">A simple PLYRSITE with your quick info. Get started in minutes.</div></div>
        <div><div class="pc-price"><span class="currency">$</span><strong>0</strong><span class="period">/mo</span></div><div class="pc-subnote">No credit card required</div></div>
        <a class="pc-btn" href="{{ $freeHref }}">Get Started Free</a>
        <div class="pc-feature-box">
          <div class="pc-feature-title">The basics</div>
          <div class="pc-feature"><span class="pc-check">✓</span><span>Simple PLYRSITE page</span></div>
          <div class="pc-feature"><span class="pc-check">✓</span><span>Quick athlete info</span></div>
          <div class="pc-feature"><span class="pc-check">✓</span><span>Bio and basic stats</span></div>
          <div class="pc-feature"><span class="pc-check">✓</span><span>Email support</span></div>
        </div>
      </article>

      <article class="pc-card pc-plan-card featured">
        <span class="pc-badge">Most popular</span>
        <div><h3>My Journey</h3><div class="desc">Your own recruiting HQ. Domain, email, tracking, templates, the coach database, and access to PLYR Points.</div></div>
        <div><div class="pc-price"><strong>{{ $money($myJourneyCents) }}</strong><span class="period">/mo</span></div><div class="pc-subnote">Monthly subscription · Cancel anytime</div></div>
        <a class="pc-btn primary" href="{{ $journeyHref }}">Get My Journey</a>
        <div class="pc-feature-box">
          <div class="pc-feature-title">Everything in Free, plus:</div>
          <div class="pc-feature-columns">
            <div class="pc-feature"><span class="pc-check">✓</span><span>Your own personalized domain</span></div>
            <div class="pc-feature"><span class="pc-check">✓</span><span>Your own email, sent from you</span></div>
            <div class="pc-feature"><span class="pc-check">✓</span><span>Coach engagement tracking</span></div>
            <div class="pc-feature"><span class="pc-check">✓</span><span>Outreach templates</span></div>
            <div class="pc-feature"><span class="pc-check">✓</span><span>Coach database, verified weekly</span></div>
            <div class="pc-feature"><span class="pc-check">✓</span><span>1-on-1 onboarding</span></div>
            <div class="pc-feature"><span class="pc-check">✓</span><span><strong>Unlocks PLYR Points</strong></span></div>
          </div>
        </div>
      </article>
    </div>
  </div>
</section>

<section id="points" class="pc-points-band">
  <div class="pc-wrap pc-section">
    <div class="pc-points-head">
      <div class="pc-section-head" style="margin:0"><span class="pc-kicker">Step 2 · For My Journey members</span><h2>Add PLYR Points</h2><p>You asked, so we listened. Load points once and spend them on any mix of reels, graphics, coach outreach and rep time.</p></div>
      <div class="pc-never">✓ Points never expire</div>
    </div>
    <div class="pc-package-grid">
      <article class="pc-card pc-package jumpstart">
        <span class="pc-badge blue">One-time push</span>
        <div><h3>Jumpstart</h3><div class="desc">One clean recruiting push added to My Journey. Spend the points on any mix you want.</div></div>
        <div class="pc-points-big">{{ number_format($jumpstartPoints) }} <small>pts</small></div>
        <div class="pc-sale"><span class="retail">{{ $money($jumpstartPoints * $customPointCents) }}</span><strong>{{ $money($jumpstartCents) }}</strong><span>one time</span><b class="pc-save">SAVE {{ $money(max(0, $jumpstartPoints * $customPointCents - $jumpstartCents)) }}</b></div>
        <div class="pc-subnote">New players also start My Journey at {{ $money($myJourneyCents) }}/month ({{ $money($jumpstartCents + $myJourneyCents) }} due today). Existing members pay only {{ $money($jumpstartCents) }}.</div>
        <a class="pc-btn" href="{{ $jumpstartHref }}">Get Jumpstart</a>
        <div class="pc-package-list">
          <div class="pc-feature-title">{{ number_format($jumpstartPoints) }} points is enough for:</div>
          <div class="row"><span>1 Coach Outreach Program</span><span>{{ number_format($services['outreach']['points']) }}</span></div>
          <div class="row"><span>1 Highlight Reel</span><span>{{ number_format($services['reel']['points']) }}</span></div>
          <div class="row"><span>1 Custom Graphic</span><span>{{ number_format($services['graphic']['points']) }}</span></div>
          <div class="row"><span>2 Rep Hours</span><span>{{ number_format($services['production_hour']['points'] * 2) }}</span></div>
          <small>Or any mix that adds up to {{ number_format($jumpstartPoints) }}.</small>
        </div>
      </article>

      <article class="pc-card pc-package amplify">
        <div style="display:flex;gap:8px;flex-wrap:wrap"><span class="pc-badge gold">Done for you</span><span class="pc-badge" style="background:transparent;border:1px solid var(--pc-gold);color:var(--pc-gold)">Best value</span></div>
        <div><h3>Amplify</h3><div class="desc">Your complete recruiting package. Graphics, highlight reels, managed coach outreach and hands-on support, your mix.</div></div>
        <div class="pc-points-big">{{ number_format($amplifyPoints) }} <small>pts</small></div>
        <div class="pc-sale"><span class="retail">{{ $money($amplifyPoints * $customPointCents) }}</span><strong>{{ $money($amplifyCents) }}</strong><span>one time</span><b class="pc-save">SAVE {{ $money(max(0, $amplifyPoints * $customPointCents - $amplifyCents)) }}</b></div>
        <div class="pc-subnote">New players also start My Journey at {{ $money($myJourneyCents) }}/month ({{ $money($amplifyCents + $myJourneyCents) }} due today). Existing members pay only {{ $money($amplifyCents) }}.</div>
        <a class="pc-btn primary" href="{{ $amplifyHref }}">Amplify My Recruiting</a>
        <div class="pc-package-list">
          <div class="pc-feature-title">{{ number_format($amplifyPoints) }} points is enough for:</div>
          <div class="row"><span>4 Coach Outreach Programs</span><span>{{ number_format($services['outreach']['points'] * 4) }}</span></div>
          <div class="row"><span>4 Highlight Reels</span><span>{{ number_format($services['reel']['points'] * 4) }}</span></div>
          <div class="row"><span>4 Custom Graphics</span><span>{{ number_format($services['graphic']['points'] * 4) }}</span></div>
          <div class="row"><span>8 Rep Hours</span><span>{{ number_format($services['production_hour']['points'] * 8) }}</span></div>
          <small>Or any mix that adds up to {{ number_format($amplifyPoints) }}.</small>
        </div>
      </article>
    </div>

    <div class="pc-card pc-topups">
      <h3>Just need a few more?</h3>
      <div class="pc-topup-pills">
        @foreach(['100','250','500'] as $packKey)
          @php
            $pack = $pointPacks->get($packKey);
          @endphp
          @if($pack)
            <span class="pc-topup-pill"><b>{{ number_format((int) $pack['points']) }} pts</b>{{ $money((int) $pack['price_cents']) }}</span>
          @endif
        @endforeach
        @if($customPointsEnabled)<span class="pc-topup-pill"><b>Custom</b>{{ $money($customPointCents) }} / point</span>@endif
      </div>
      <small>Point packs are available in your dashboard.</small>
    </div>
  </div>
</section>

<section class="pc-section">
  <div class="pc-wrap">
    <div class="pc-section-head"><span class="pc-kicker">What points buy</span><h2>Every service has a number</h2></div>
    <div class="pc-service-grid">
      @foreach($services as $serviceKey => $service)
      <article class="pc-card pc-service">
        @if($service['icon']==='send')
          <svg class="pc-service-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 3L10 14"></path><path d="M21 3l-7 18-4-7-7-4z"></path></svg>
        @elseif($service['icon']==='video')
          <svg class="pc-service-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M10 9l5 3-5 3z"></path></svg>
        @elseif($service['icon']==='image')
          <svg class="pc-service-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"></rect><circle cx="9" cy="10" r="1.5"></circle><path d="M21 16l-5-5-8 8"></path></svg>
        @else
          <svg class="pc-service-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
        @endif
        <h3>{{ $service['label'] }}</h3>
        <div class="points">{{ number_format($service['points']) }} <small>pts</small></div>
        <p>{{ $service['description'] }}</p>
        <button type="button" class="pc-btn" data-sample="{{ $serviceKey }}">◉ See sample</button>
      </article>
      @endforeach
    </div>
    <div class="pc-how">
      <div class="pc-how-item"><span class="pc-how-num">1</span><div><h4>Load your points</h4><p>They land in your dashboard right away after confirmed payment.</p></div></div>
      <div class="pc-how-item"><span class="pc-how-num">2</span><div><h4>Request what you need</h4><p>A reel this week, an outreach program next month.</p></div></div>
      <div class="pc-how-item"><span class="pc-how-num">3</span><div><h4>Points come off on delivery</h4><p>Whatever is left stays until you use it.</p></div></div>
    </div>
  </div>
</section>

<section id="mix" class="pc-section" style="padding-top:48px">
  <div class="pc-wrap">
    <div class="pc-section-head"><span class="pc-kicker">Not sure what to get?</span><h2>Build your mix</h2><p>Tell us what you need this season. We will show you the cheapest way to get it.</p></div>
    <div class="pc-calculator">
      <div class="pc-card pc-calc-list" id="pcCalculator">
        @php
          $defaultQty = ['outreach'=>2,'reel'=>2,'graphic'=>1,'production_hour'=>0];
        @endphp
        @foreach($services as $serviceKey => $service)
        <div class="pc-calc-row" data-service="{{ $serviceKey }}" data-points="{{ $service['points'] }}">
          <div class="pc-calc-label"><strong>{{ $service['label'] }}</strong><small>{{ number_format($service['points']) }} pts each</small></div>
          <div class="pc-stepper"><button type="button" data-step="-1" aria-label="Remove one {{ $service['label'] }}">−</button><output data-qty>{{ $defaultQty[$serviceKey] ?? 0 }}</output><button type="button" class="plus" data-step="1" aria-label="Add one {{ $service['label'] }}">+</button></div>
          <div class="pc-calc-sub"><span data-subtotal>0</span> pts</div>
        </div>
        @endforeach
        <div class="pc-calc-total"><span>Total</span><strong><span id="pcTotal">0</span> pts</strong></div>
      </div>

      <aside class="pc-card pc-rec" aria-live="polite">
        <span class="pc-badge">Your best option</span>
        <div id="pcRecEmpty" style="color:var(--pc-muted)">Add a service on the left to see what fits.</div>
        <div id="pcRecContent" hidden>
          <h3 id="pcRecTitle"></h3>
          <div class="pc-rec-price"><span class="strike" id="pcRecStrike"></span><strong id="pcRecPrice"></strong></div>
          <span class="save" id="pcRecSave" hidden></span>
          <p id="pcRecDetail"></p>
          <p class="green" id="pcRecLeft"></p>
          <p id="pcRecDue"></p>
          <a class="pc-btn primary" id="pcRecCta" href="{{ $journeyHref }}">Get My Journey</a>
        </div>
        <small style="color:var(--pc-dim)">Points require an active My Journey membership ({{ $money($myJourneyCents) }}/mo).</small>
      </aside>
    </div>
  </div>
</section>

<section class="pc-section" style="padding-top:24px">
  <div class="pc-wrap">
    <div class="pc-section-head"><h2>Compare plans</h2></div>
    <div class="pc-compare">
      <table>
        <thead><tr><th>Feature</th><th>Free</th><th>My Journey</th></tr></thead>
        <tbody>
          <tr><td>PLYRSITE profile page</td><td>Simple</td><td>Full</td></tr>
          <tr><td>Your own domain and email</td><td class="pc-no">—</td><td class="pc-yes">✓</td></tr>
          <tr><td>Coach engagement tracking</td><td class="pc-no">—</td><td class="pc-yes">✓</td></tr>
          <tr><td>Coach database, verified weekly</td><td class="pc-no">—</td><td class="pc-yes">✓</td></tr>
          <tr><td>Outreach templates</td><td class="pc-no">—</td><td class="pc-yes">✓</td></tr>
          <tr><td>1-on-1 onboarding</td><td class="pc-no">—</td><td class="pc-yes">✓</td></tr>
          <tr><td>Buy PLYR Points for reels, graphics, outreach and rep time</td><td class="pc-no">—</td><td class="pc-yes">✓</td></tr>
          <tr><td>Email support</td><td class="pc-yes">✓</td><td class="pc-yes">✓</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="pc-faq">
  <div class="pc-section-head"><span class="pc-kicker">FAQ</span><h2>We've got answers</h2></div>
  <details><summary>What are PLYR Points?<span class="plus">+</span></summary><p>Points are how you pay for the work our team does for you. Every service has a set number of points, so you can spend them on whatever mix your recruiting needs.</p></details>
  <details><summary>Do points expire?<span class="plus">+</span></summary><p>No. Your points stay in your account until you use them. Recruiting does not run on a 30-day clock, and neither do we.</p></details>
  <details><summary>Do I need My Journey to buy points?<span class="plus">+</span></summary><p>Yes. Your points, requests and deliveries live in your My Journey dashboard. New players can join My Journey and add their exact starting point balance in the same checkout.</p></details>
  <details><summary>Can I mix services inside Jumpstart or Amplify?<span class="plus">+</span></summary><p>Yes. A package is a point balance. Want more film and fewer graphics? Spend it that way.</p></details>
  <details><summary>When do points come off my balance?<span class="plus">+</span></summary><p>When the work is delivered. While we build it, the points are held. Cancel a request before delivery and eligible points go back.</p></details>
  <details><summary>Can I cancel?<span class="plus">+</span></summary><p>Anytime. My Journey is the only thing that renews monthly. Jumpstart, Amplify and point purchases are one-time charges.</p></details>
  <details><summary>Is there a free plan?<span class="plus">+</span></summary><p>Yes. Start free with a simple PLYRSITE page, no credit card needed, and upgrade whenever you are ready.</p></details>
</section>

<section class="pc-final">
  <span class="pc-kicker">Start your journey</span>
  <h2>This is your journey. It has to come from you.</h2>
  <p>Authenticity is key. Start free, then add points when you are ready.</p>
  <div class="pc-final-actions"><a class="pc-btn primary" href="{{ $freeHref }}">Create Free Account</a><a class="pc-btn" href="{{ route('marketing.book-demo') }}">Book a Demo</a></div>
  <small>No credit card · Free plan available · Quick setup</small>
</section>
</main>

@include('partials.footer')

<div class="pc-dialog" id="pcSampleDialog" hidden>
  <div class="pc-dialog-card" role="dialog" aria-modal="true" aria-labelledby="pcSampleTitle">
    <div class="pc-dialog-head"><div><span class="pc-kicker">Service sample</span><h3 id="pcSampleTitle">PLYR service</h3></div><button class="pc-dialog-close" type="button" aria-label="Close sample">×</button></div>
    <p class="pc-dialog-copy" id="pcSampleCopy"></p>
    <div class="pc-dialog-note" id="pcSampleNote"></div>
  </div>
</div>

<script>
(function(){
  'use strict';
  var MY_JOURNEY_CENTS={{ $myJourneyCents }};
  var JUMPSTART_CENTS={{ $jumpstartCents }};
  var AMPLIFY_CENTS={{ $amplifyCents }};
  var JUMPSTART_POINTS={{ $jumpstartPoints }};
  var AMPLIFY_POINTS={{ $amplifyPoints }};
  var CUSTOM_POINT_CENTS={{ $customPointCents }};
  var CUSTOM_MIN={{ $customPointMin }};
  var CUSTOM_MAX={{ $customPointMax }};
  var CUSTOM_ENABLED=@json($customPointsEnabled);
  var REG_URL=@json(route('marketing.registration'));
  var rows=Array.from(document.querySelectorAll('.pc-calc-row'));
  var totalEl=document.getElementById('pcTotal');
  var empty=document.getElementById('pcRecEmpty');
  var content=document.getElementById('pcRecContent');
  var title=document.getElementById('pcRecTitle');
  var strike=document.getElementById('pcRecStrike');
  var price=document.getElementById('pcRecPrice');
  var save=document.getElementById('pcRecSave');
  var detail=document.getElementById('pcRecDetail');
  var left=document.getElementById('pcRecLeft');
  var due=document.getElementById('pcRecDue');
  var cta=document.getElementById('pcRecCta');

  function dollars(cents){return '$'+(cents/100).toLocaleString('en-US',{minimumFractionDigits:cents%100?2:0,maximumFractionDigits:2});}
  function query(plan,points){
    var u=new URL(REG_URL,window.location.origin);
    u.searchParams.set('plan',plan);
    if(points>0)u.searchParams.set('initial_points',String(points));
    return u.pathname+u.search;
  }
  function option(name,plan,basePoints,baseCents,needed){
    var extra=Math.max(0,needed-basePoints);
    if(extra>0&&!CUSTOM_ENABLED)return null;
    if(extra>CUSTOM_MAX)return null;
    var effectiveExtra=extra>0?Math.max(CUSTOM_MIN,extra):0;
    var supplied=basePoints+effectiveExtra;
    var oneTime=baseCents+(effectiveExtra*CUSTOM_POINT_CENTS);
    return {name:name,plan:plan,basePoints:basePoints,extra:effectiveExtra,supplied:supplied,oneTime:oneTime,left:Math.max(0,supplied-needed)};
  }
  function recommend(needed){
    var options=[];
    if(CUSTOM_ENABLED&&needed<=CUSTOM_MAX)options.push(option('Custom PLYR Points','my-journey',0,0,needed));
    options.push(option('Jumpstart','jumpstart',JUMPSTART_POINTS,JUMPSTART_CENTS,needed));
    options.push(option('Amplify','amplify',AMPLIFY_POINTS,AMPLIFY_CENTS,needed));
    options=options.filter(Boolean);
    options.sort(function(a,b){if(a.oneTime!==b.oneTime)return a.oneTime-b.oneTime;if(a.left!==b.left)return a.left-b.left;return a.supplied-b.supplied;});
    return options[0]||null;
  }
  function render(){
    var total=0;
    rows.forEach(function(row){
      var qty=parseInt(row.querySelector('[data-qty]').textContent,10)||0;
      var pts=parseInt(row.dataset.points,10)||0;
      var sub=qty*pts;
      row.querySelector('[data-subtotal]').textContent=sub.toLocaleString('en-US');
      total+=sub;
    });
    totalEl.textContent=total.toLocaleString('en-US');
    if(total<=0){empty.hidden=false;content.hidden=true;return;}
    var rec=recommend(total);
    if(!rec){empty.hidden=false;empty.textContent='This mix is larger than the current online custom checkout limit. Book a demo and we will build the right package.';content.hidden=true;return;}
    empty.hidden=true;content.hidden=false;
    var recTitle=rec.name;
    if(rec.extra>0&&rec.plan!=='my-journey')recTitle+=' + '+rec.extra.toLocaleString('en-US')+' Custom Points';
    if(rec.plan==='my-journey')recTitle=total.toLocaleString('en-US')+' Custom PLYR Points';
    title.textContent=recTitle;
    price.textContent=dollars(rec.oneTime);
    var ala=total*CUSTOM_POINT_CENTS;
    if(rec.oneTime<ala){strike.textContent=dollars(ala);strike.style.display='inline';save.hidden=false;save.textContent='SAVE '+dollars(ala-rec.oneTime);}else{strike.style.display='none';save.hidden=true;}
    detail.textContent=rec.supplied.toLocaleString('en-US')+' points for '+total.toLocaleString('en-US')+' points of services.';
    left.textContent=rec.left>0?rec.left.toLocaleString('en-US')+' points left over. They never expire.':'Uses every point.';
    due.textContent='New player due today: '+dollars(rec.oneTime+MY_JOURNEY_CENTS)+', including the first My Journey month. Then '+dollars(MY_JOURNEY_CENTS)+'/month.';
    cta.href=query(rec.plan,rec.extra>0?rec.extra:(rec.plan==='my-journey'?total:0));
    cta.textContent=rec.plan==='my-journey'?'Get My Journey + Points':'Get '+recTitle;
  }
  rows.forEach(function(row){
    row.querySelectorAll('[data-step]').forEach(function(button){
      button.addEventListener('click',function(){
        var output=row.querySelector('[data-qty]');
        var qty=parseInt(output.textContent,10)||0;
        qty=Math.max(0,Math.min(12,qty+parseInt(button.dataset.step,10)));
        output.textContent=String(qty);
        render();
      });
    });
  });
  render();

  var sampleInfo={
    outreach:{title:'Coach Outreach Program',copy:'Reach more coaches at once across divisions, conferences, states and regions.',note:'Your outreach is sent from your recruiting identity and directs coaches back to your PLYRSITE.'},
    reel:{title:'Highlight Reel',copy:'Send your game-film link and timestamps. Our team cuts the moments into a coach-ready reel.',note:'The final delivery is ready to share from your recruiting profile and outreach.'},
    graphic:{title:'Custom Graphic',copy:'Commitment posts, player CV graphics, game-day cards and stat graphics built around your real photos and information.',note:'100% custom design. Never AI generated.'},
    production_hour:{title:'Rep Hour',copy:'Use an hour of your rep\'s time for profile review, updates or hands-on recruiting work.',note:'Use the hour where you need the help most.'}
  };
  var dialog=document.getElementById('pcSampleDialog');
  var close=dialog.querySelector('.pc-dialog-close');
  document.querySelectorAll('[data-sample]').forEach(function(button){
    button.addEventListener('click',function(){
      var info=sampleInfo[button.dataset.sample]||{};
      document.getElementById('pcSampleTitle').textContent=info.title||'PLYR service';
      document.getElementById('pcSampleCopy').textContent=info.copy||'See what this PLYR Points service can do for your recruiting.';
      document.getElementById('pcSampleNote').textContent=info.note||'Your completed work is delivered through your PLYRCARD account.';
      dialog.hidden=false;
      document.documentElement.style.overflow='hidden';
    });
  });
  function closeDialog(){dialog.hidden=true;document.documentElement.style.overflow='';}
  close.addEventListener('click',closeDialog);
  dialog.addEventListener('click',function(e){if(e.target===dialog)closeDialog();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!dialog.hidden)closeDialog();});
})();
</script>
</body>
</html>
