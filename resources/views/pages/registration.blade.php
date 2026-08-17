@php
    $isPaid = $isPaidPlan;
    $isAmplify = $planKey === 'amplify';
    $planLabel = $plan['label'] ?? 'Free';
    $recurringDollars = number_format(((int) ($plan['recurring_amount_cents'] ?? 0)) / 100, 2);
    $setupDollars = number_format(((int) ($plan['setup_fee_cents'] ?? 0)) / 100, 2);
    $chargeFirstMonthUpfront = (bool) ($plan['charge_first_month_upfront'] ?? true);
    $initialCents = ((int) ($plan['setup_fee_cents'] ?? 0)) + ($chargeFirstMonthUpfront ? ((int) ($plan['recurring_amount_cents'] ?? 0)) : 0);
    $initialDollars = number_format($initialCents / 100, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $planLabel }} Registration — PLYRCARD</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{--ink:#0C0E11;--surface:#131619;--raised:#1A1E23;--line:#262C33;--line-soft:#1E242A;--coral:#FF5A3C;--coral-hi:#FF6E52;--paper:#F2F0ED;--mute:#868E99;--dim:#5E6670;--good:#4ADE9B;--warn:#FFB84D;--danger:#FF7A63;--r:10px}
*{box-sizing:border-box;margin:0;padding:0}html{-webkit-text-size-adjust:100%}body{background:var(--ink);color:var(--paper);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:15px;line-height:1.5;min-height:100vh;-webkit-font-smoothing:antialiased}a{color:inherit}::selection{background:var(--coral);color:#0C0E11}:focus-visible{outline:2px solid var(--coral);outline-offset:2px;border-radius:4px}
.shell{display:grid;grid-template-columns:300px 1fr;min-height:100vh}.rail{background:var(--surface);border-right:1px solid var(--line-soft);padding:34px 30px;display:flex;flex-direction:column;position:sticky;top:0;height:100vh}.logo{font-family:Archivo;font-weight:800;font-size:19px;letter-spacing:-.02em;color:var(--paper);text-decoration:none;display:block}.logo span{color:var(--coral)}
.tier{margin-top:26px;border:1px solid var(--line);border-left:2px solid var(--coral);border-radius:var(--r);padding:14px 15px;background:var(--raised)}.tier .k{font-family:"JetBrains Mono",monospace;font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--mute);font-weight:700}.tier .v{font-family:Archivo;font-weight:800;font-size:19px;margin-top:5px;letter-spacing:-.02em}.tier .pz{font-size:12.5px;color:var(--mute);margin-top:4px}.tier .pz b{color:var(--paper);font-weight:600}
.steps{list-style:none;margin-top:30px;flex:1}.steps li{display:flex;gap:13px;align-items:flex-start;padding:10px 0;position:relative}.steps li::before{content:"";position:absolute;left:12px;top:33px;bottom:-3px;width:1px;background:var(--line)}.steps li:last-child::before{display:none}.dot{width:25px;height:25px;flex:0 0 25px;border-radius:50%;border:1px solid var(--line);background:var(--ink);display:grid;place-items:center;font-family:"JetBrains Mono",monospace;font-size:11px;font-weight:700;color:var(--mute);transition:.25s}.steps li.on .dot{border-color:var(--coral);color:var(--coral);box-shadow:0 0 0 4px rgba(255,90,60,.11)}.steps li.done .dot{background:var(--coral);border-color:var(--coral);color:#0C0E11}.steps .lb{font-size:13.5px;font-weight:500;color:var(--mute);padding-top:3px}.steps li.on .lb,.steps li.done .lb{color:var(--paper)}.steps li.on .lb{font-weight:600}.rail-foot{font-size:11.5px;color:var(--mute);line-height:1.6;border-top:1px solid var(--line-soft);padding-top:16px}.rail-foot a{color:var(--paper);text-decoration:none;border-bottom:1px solid var(--line)}
.mbar{display:none}.stage{padding:54px 40px 90px;display:flex;justify-content:center}.card{width:100%;max-width:580px}.eyebrow{font-family:"JetBrains Mono",monospace;font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--coral)}h1{font-family:Archivo;font-weight:800;font-size:clamp(28px,5vw,38px);letter-spacing:-.03em;line-height:1.05;margin:11px 0 9px}.sub{color:var(--mute);font-size:15px;max-width:48ch;line-height:1.55}.panel{display:none}.panel.active{display:block;animation:in .3s ease both}@keyframes in{from{opacity:0;transform:translateY(9px)}to{opacity:1;transform:none}}
.fields{margin-top:28px;display:flex;flex-direction:column;gap:19px}.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}.row3{display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:14px}.f label,.legend{display:block;font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--mute);margin-bottom:7px}.opt{text-transform:none;letter-spacing:0;font-weight:400;color:var(--dim)}input[type=text],input[type=email],input[type=tel],input[type=password],input[type=number],input[type=url],select,textarea{width:100%;background:var(--surface);border:1px solid var(--line);color:var(--paper);border-radius:var(--r);padding:12px 13px;font-family:inherit;font-size:15px;transition:border-color .18s,background .18s,box-shadow .18s}textarea{resize:vertical;min-height:84px;line-height:1.5}select{appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' stroke='%23868E99' stroke-width='1.6' fill='none' stroke-linecap='round'/></svg>");background-repeat:no-repeat;background-position:right 13px center;padding-right:36px}input:hover,select:hover,textarea:hover{border-color:#333B44}input:focus,select:focus,textarea:focus{border-color:var(--coral);background:var(--raised);outline:none;box-shadow:0 0 0 3px rgba(255,90,60,.08)}input::placeholder,textarea::placeholder{color:#4E555E}.err{border-color:#F4593F!important;background:rgba(244,89,63,.05)!important}.msg{font-size:12.5px;color:var(--danger);margin-top:6px;display:none}.msg.show{display:block}.hint{font-size:12.5px;color:var(--mute);margin-top:6px;line-height:1.45}.mono{font-family:"JetBrains Mono",monospace}.divider{display:flex;align-items:center;gap:13px;margin:6px 0 -3px}.divider span{font-family:"JetBrains Mono",monospace;font-size:10.5px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--dim);white-space:nowrap}.divider::after{content:"";flex:1;height:1px;background:var(--line-soft)}
.seg{display:flex;gap:8px}.seg button{flex:1;background:var(--surface);border:1px solid var(--line);color:var(--mute);border-radius:var(--r);padding:12px;font-family:Archivo;font-weight:600;font-size:14.5px;cursor:pointer}.seg button[aria-pressed=true]{background:var(--coral);border-color:var(--coral);color:#0C0E11}.chips{display:flex;flex-wrap:wrap;gap:7px}.chip{background:var(--surface);border:1px solid var(--line);color:var(--mute);border-radius:100px;padding:8px 14px;font-size:13.5px;font-weight:500;cursor:pointer;font-family:inherit}.chip[aria-pressed=true]{background:rgba(255,90,60,.13);border-color:var(--coral);color:var(--coral)}.check{display:flex!important;gap:11px;align-items:flex-start;cursor:pointer;font-size:14px!important;line-height:1.5;color:var(--paper)!important;text-transform:none!important;letter-spacing:0!important}.check input{appearance:none;width:19px;height:19px;flex:0 0 19px;margin-top:1px;border:1px solid var(--line);border-radius:5px;background:var(--surface);cursor:pointer}.check input:checked{background:var(--coral);border-color:var(--coral)}.check input:checked::after{content:"";display:block;width:5px;height:9px;border:solid #0C0E11;border-width:0 2px 2px 0;transform:rotate(45deg);margin:2px auto}.reveal{display:none;border-left:2px solid var(--coral);padding-left:17px;margin-top:3px;flex-direction:column;gap:17px}.reveal.open{display:flex;animation:in .25s ease both}.why{font-size:12.5px;color:var(--mute);line-height:1.5}.rules{display:flex;flex-wrap:wrap;gap:6px 14px;margin-top:9px}.rules li{list-style:none;font-size:12px;color:var(--dim);display:flex;gap:6px;align-items:center}.rules li i{width:5px;height:5px;border-radius:50%;background:#3A424B}.rules li.ok{color:var(--good)}.rules li.ok i{background:var(--good)}.pw-wrap{position:relative}.peek{position:absolute;right:11px;top:11px;background:none;border:0;color:var(--mute);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;padding:2px 4px}
.browser{border:1px solid var(--line);border-radius:12px 12px 0 0;background:var(--raised);padding:11px 13px;display:flex;align-items:center;gap:11px;border-bottom:0}.tl{display:flex;gap:6px;flex:0 0 auto}.tl i{width:9px;height:9px;border-radius:50%;background:#2E353D;display:block}.bar{flex:1;background:var(--ink);border:1px solid var(--line-soft);border-radius:100px;padding:6px 13px;overflow:hidden}.bar .txt{font-family:"JetBrains Mono",monospace;font-size:12.5px;color:var(--mute);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bar .txt b{color:var(--paper);font-weight:500}.browser-body{border:1px solid var(--line);border-radius:0 0 12px 12px;background:var(--surface);padding:26px 24px 24px;position:relative;overflow:hidden}.browser-body.live{box-shadow:inset 0 1px 0 rgba(74,222,155,.08)}.dsearch{display:flex;gap:9px}.dsearch input{flex:1}.dsearch button{background:var(--paper);color:#0C0E11;border:0;border-radius:var(--r);padding:0 22px;font-family:Archivo;font-weight:700;font-size:14.5px;cursor:pointer}.results{margin-top:18px;display:none;flex-direction:column;gap:9px}.results.show{display:flex}.dom{border:1px solid var(--line);border-radius:var(--r);padding:14px 16px;background:var(--ink);display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;text-align:left;font-family:inherit;width:100%}.dom[aria-pressed=true]{border-color:var(--coral);background:rgba(255,90,60,.07)}.dom.dead{cursor:not-allowed;opacity:.44}.dom .n{font-family:Archivo;font-weight:700;font-size:16.5px;letter-spacing:-.02em;color:var(--paper);word-break:break-all}.dom .tag{font-family:"JetBrains Mono",monospace;font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;flex:0 0 auto}.tag.ok{color:var(--good)}.tag.warn{color:var(--warn)}.tag.no{color:var(--dim)}.tag.sel{color:var(--coral)}.loading-line{display:none;align-items:center;gap:9px;margin-top:18px;font-size:13.5px;color:var(--mute)}.loading-line.show{display:flex}.loading-line i{width:7px;height:7px;border-radius:50%;background:var(--warn);animation:blink 1s infinite}@keyframes blink{50%{opacity:.25}}.own{margin-top:16px;font-size:12.5px;color:var(--mute);line-height:1.55;border-top:1px solid var(--line-soft);padding-top:14px}.own b{color:var(--paper);font-weight:600}
.claim{border:1px solid var(--line);border-radius:14px;background:var(--surface);padding:26px 24px;margin-top:30px;position:relative;overflow:hidden}.claim.live{border-color:rgba(74,222,155,.38)}.claim .k{font-family:"JetBrains Mono",monospace;font-size:10.5px;letter-spacing:.16em;text-transform:uppercase;color:var(--mute);font-weight:700}.url{font-family:Archivo;font-weight:800;letter-spacing:-.028em;font-size:clamp(21px,4.4vw,30px);margin-top:12px;word-break:break-all;line-height:1.15}.url .d{color:#5A6169}.url .h{color:var(--coral);border-bottom:2px solid rgba(255,90,60,.35)}.status{display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;font-weight:500;color:var(--mute);min-height:19px}.pip{width:7px;height:7px;border-radius:50%;background:var(--mute)}.status.ok{color:var(--good)}.status.ok .pip{background:var(--good)}.status.no{color:var(--danger)}.status.no .pip{background:var(--danger)}.status.wait .pip{background:var(--warn);animation:blink 1s infinite}
.order{border:1px solid var(--line);border-radius:12px;margin-top:22px;overflow:hidden}.order-h{padding:14px 17px;background:var(--raised);border-bottom:1px solid var(--line);font-family:Archivo;font-weight:700;font-size:15px}.order ul{list-style:none;padding:15px 17px;display:flex;flex-direction:column;gap:10px}.order li{font-size:13.5px;color:var(--mute);display:flex;justify-content:space-between;gap:12px}.order li b{color:var(--good);font-weight:600;font-family:"JetBrains Mono",monospace;font-size:11.5px;white-space:nowrap}.total{padding:15px 17px;border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:baseline;background:var(--raised)}.total .l{font-family:Archivo;font-weight:700;font-size:14px}.total .l small{display:block;font-family:Inter;font-weight:400;font-size:12px;color:var(--mute);margin-top:3px}.total .r{font-family:Archivo;font-weight:800;font-size:26px;letter-spacing:-.03em}.pay-note{display:flex;gap:10px;padding:13px 14px;border:1px solid var(--line);border-radius:var(--r);background:rgba(74,222,155,.04);font-size:12.5px;color:var(--mute);line-height:1.5}.pay-note strong{color:var(--paper)}
.sum{border:1px solid var(--line);border-radius:var(--r);margin-top:22px;overflow:hidden}.sum-h{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;background:var(--raised);border-bottom:1px solid var(--line)}.sum-h .t{font-family:Archivo;font-weight:700;font-size:15px}.sum-h .p{font-family:"JetBrains Mono",monospace;font-size:12px;color:var(--coral);font-weight:700}.sum ul{list-style:none;padding:14px 16px;display:flex;flex-direction:column;gap:9px}.sum li{font-size:13.5px;color:var(--mute);display:flex;gap:9px}.sum li::before{content:"";width:5px;height:5px;border-radius:50%;background:var(--coral);margin-top:7px;flex:0 0 5px}.sum .up{padding:13px 16px;border-top:1px solid var(--line);font-size:13px;color:var(--mute);line-height:1.5}.sum .up b{color:var(--paper)}
.nav{display:flex;gap:11px;margin-top:30px;align-items:center}.btn{font-family:Archivo;font-weight:700;font-size:15px;border-radius:var(--r);padding:14px 26px;cursor:pointer;border:1px solid transparent;letter-spacing:-.01em}.btn.pri{background:var(--coral);color:#0C0E11;flex:1}.btn.pri:hover{background:var(--coral-hi)}.btn.pri:disabled{background:var(--line);color:var(--dim);cursor:not-allowed}.btn.gho{background:transparent;border-color:var(--line);color:var(--mute)}.tiny{font-size:12.5px;color:var(--mute);margin-top:15px;line-height:1.55;text-align:center}.tiny a{color:var(--paper)}
.form-alert{display:none;margin:0 0 22px;padding:13px 15px;border:1px solid rgba(255,122,99,.32);background:rgba(255,122,99,.07);border-radius:var(--r);color:#ffc1b7;font-size:13px;line-height:1.5}.form-alert.show{display:block}.done-wrap{text-align:center;padding-top:12px}.ring{width:64px;height:64px;margin:0 auto 22px;border-radius:18px;background:rgba(255,90,60,.12);border:1px solid rgba(255,90,60,.3);display:grid;place-items:center}.done-mail,.live-dom{font-family:"JetBrains Mono",monospace;color:var(--paper);background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:10px 15px;display:inline-block;margin-top:12px;word-break:break-all}.live-dom{font-family:Archivo;font-weight:800;font-size:22px;color:var(--coral)}.prov{display:inline-flex;align-items:center;gap:8px;margin-top:13px;border:1px solid var(--line);border-radius:100px;padding:7px 15px;font-size:12.5px;color:var(--mute)}.prov i{width:7px;height:7px;border-radius:50%;background:var(--warn);animation:blink 1.4s infinite}.prov.paid{color:var(--good);border-color:rgba(74,222,155,.3)}.prov.paid i{background:var(--good);animation:none}.next{margin-top:30px;border-top:1px solid var(--line-soft);padding-top:22px}.next h3{font-family:Archivo;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--mute);font-weight:700;margin-bottom:15px}.next ol{list-style:none;display:flex;flex-direction:column;gap:14px;counter-reset:n}.next li{display:flex;gap:13px;counter-increment:n;font-size:14px;line-height:1.5;color:var(--mute)}.next li::before{content:counter(n,decimal-leading-zero);font-family:"JetBrains Mono",monospace;font-size:11px;font-weight:700;color:var(--coral);padding-top:2px;flex:0 0 20px}.next li b{color:var(--paper);font-weight:600;font-family:Archivo;display:block;margin-bottom:2px}
.page-loader{position:fixed;inset:0;z-index:9999;background:rgba(12,14,17,.82);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center}.page-loader.show{display:flex}.tail-spinner{width:42px;height:42px;border-radius:50%;border:4px solid rgba(255,255,255,.12);border-top-color:var(--coral);animation:spin .75s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:940px){.shell{grid-template-columns:1fr}.rail{position:static;height:auto;padding:18px 20px;border-right:0;border-bottom:1px solid var(--line-soft);flex-direction:row;align-items:center;justify-content:space-between;gap:14px}.tier{margin-top:0;padding:8px 12px}.tier .v{font-size:14px}.tier .pz,.steps,.rail-foot{display:none}.mbar{display:block;height:2px;background:var(--line-soft);position:sticky;top:0;z-index:5}.mbar i{display:block;height:100%;background:var(--coral);width:0;transition:width .35s ease}.stage{padding:28px 20px 70px}}
@media(max-width:560px){.row,.row3{grid-template-columns:1fr}.dsearch{flex-direction:column}.dsearch button{padding:13px}.browser-body{padding:20px 16px}.nav{align-items:stretch}.btn{padding-left:18px;padding-right:18px}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<div class="page-loader" id="pageLoader" aria-hidden="true"><div class="tail-spinner" aria-label="Loading"></div></div>
<div class="mbar"><i id="mbar"></i></div>
<div class="shell">
<aside class="rail">
  <div>
    <a href="/" class="logo">PLYR<span>CARD</span></a>
    <div class="tier">
      <div class="k">Selected plan</div>
      <div class="v">{{ $planLabel }}</div>
      <div class="pz">
        @if($isPaid)
          @if($isAmplify)
            <b>${{ rtrim(rtrim($setupDollars, '0'), '.') }}</b> setup &middot; then ${{ rtrim(rtrim($recurringDollars, '0'), '.') }} / month
          @else
            <b>${{ rtrim(rtrim($recurringDollars, '0'), '.') }}</b> / month
          @endif
        @else
          <b>$0</b> &middot; no card required
        @endif
      </div>
    </div>
  </div>
  <ol class="steps" id="steps">
    @if($isPaid)
      <li class="on"><span class="dot">1</span><span class="lb">Claim your domain</span></li>
      <li><span class="dot">2</span><span class="lb">Your account</span></li>
      <li><span class="dot">3</span><span class="lb">Athlete profile</span></li>
      <li><span class="dot">4</span><span class="lb">Billing &amp; payment</span></li>
    @else
      <li class="on"><span class="dot">1</span><span class="lb">Your account</span></li>
      <li><span class="dot">2</span><span class="lb">Athlete basics</span></li>
      <li><span class="dot">3</span><span class="lb">Team &amp; club</span></li>
      <li><span class="dot">4</span><span class="lb">Claim your link</span></li>
    @endif
  </ol>
  <div class="rail-foot">Already a member? <a href="/login">Log in</a><br>{{ $isPaid ? 'Complete payment to activate your selected plan.' : 'Takes about 2 minutes. Photos and highlights can come later.' }}</div>
</aside>
<main class="stage"><div class="card">
<div class="form-alert" id="formAlert" role="alert"></div>
<form id="registrationForm" method="POST" action="{{ route('marketing.registration.store', ['utm_plan' => $planKey]) }}" novalidate>
@csrf
<input type="hidden" name="plan_key" value="{{ $planKey }}">
@foreach(($trackingParams ?? []) as $trackingKey => $trackingValue)
<input type="hidden" name="{{ $trackingKey }}" value="{{ $trackingValue }}">
@endforeach
<input type="hidden" name="gender" id="genderHidden">
<input type="hidden" name="requested_domain" id="domainHidden">
<div id="dynamicInputs"></div>

@if($isPaid)
<section class="panel active" data-step="1">
  <div class="eyebrow">Step 01 of 04</div><h1>Claim your name</h1>
  <p class="sub">Search for the domain you want to use for your PLYRSITE and choose an available option.</p>
  <div style="margin-top:28px">
    <div class="browser"><div class="tl"><i></i><i></i><i></i></div><div class="bar"><span class="txt" id="urlbar"><b>yourname.com</b></span></div></div>
    <div class="browser-body" id="bbody">
      <div class="dsearch"><input id="dq" type="text" placeholder="Your name or exact domain (e.g. alexsmith.com)" autocomplete="off"><button type="button" id="dgo">Search</button></div>
      <div class="loading-line" id="dload"><i></i><span>Checking domain availability</span></div>
      <div class="results" id="dres"></div>
      <div class="own"><b>Choose the domain you want.</b> Final registration is completed after your order is confirmed.</div>
    </div>
  </div>
  <div class="msg" id="dmsg" style="margin-top:12px">Choose an available domain request to continue.</div>
  <div class="nav"><button type="button" class="btn pri" data-go="2">Continue</button></div>
</section>

<section class="panel" data-step="2">
  <div class="eyebrow">Step 02 of 04</div><h1>Your account</h1><p class="sub">This is the login you'll use for your recruiting dashboard.</p>
  <div class="fields">
    <div class="row"><div class="f"><label>First name</label><input name="first_name" id="fn" type="text" autocomplete="given-name"><div class="msg">Enter your first name.</div></div><div class="f"><label>Last name</label><input name="last_name" id="ln" type="text" autocomplete="family-name"><div class="msg">Enter your last name.</div></div></div>
    <div class="f"><label>Email</label><input name="email" id="em" type="email" autocomplete="email" placeholder="you@email.com"><div class="msg">Enter a valid email.</div><div class="hint">Receipts, payment notices, and account alerts go here.</div></div>
    <div class="f"><label>Mobile</label><input name="phone" id="ph" type="tel" autocomplete="tel" placeholder="+1 (555) 555-5555"><div class="msg">Enter a mobile number.</div></div>
    <div class="f"><label>Password</label><div class="pw-wrap"><input name="password" id="pw" type="password" autocomplete="new-password"><button type="button" class="peek" id="peek">Show</button></div><input name="password_confirmation" id="pwc" type="hidden"><ul class="rules"><li data-r="len"><i></i>8+ characters</li><li data-r="up"><i></i>1 capital letter</li><li data-r="num"><i></i>1 number</li></ul><div class="msg">Password doesn't meet the requirements.</div></div>
    <div class="f"><label class="check"><input type="checkbox" name="is_minor" id="minor" value="1"><span>The athlete is under 18</span></label><div class="reveal" id="guardian"><p class="why">A parent or guardian is added as the primary account contact for a minor.</p><div class="row"><div class="f"><label>Guardian name</label><input name="guardian_name" id="gn" type="text"><div class="msg">Enter guardian name.</div></div><div class="f"><label>Guardian email</label><input name="guardian_email" id="ge" type="email"><div class="msg">Enter guardian email.</div></div></div></div></div>
  </div>
  <div class="nav"><button type="button" class="btn gho" data-go="1">Back</button><button type="button" class="btn pri" data-go="3">Continue</button></div>
</section>

<section class="panel" data-step="3">
  <div class="eyebrow">Step 03 of 04</div><h1>Athlete profile</h1><p class="sub">Enough to build the foundation of your card before onboarding.</p>
  @include('pages.registration-athlete-fields', ['paid' => true])
  <div class="nav"><button type="button" class="btn gho" data-go="2">Back</button><button type="button" class="btn pri" data-go="4">Continue</button></div>
</section>

<section class="panel" data-step="4">
  <div class="eyebrow">Step 04 of 04</div><h1>Billing &amp; payment</h1><p class="sub">Review your plan and enter the billing details for your order.</p>
  <div class="order"><div class="order-h">{{ $planLabel }}</div><ul>
    <li><span><span id="ord-dom">yourname.com</span> domain request</span><b>Included</b></li>
    <li><span>PLYRSITE profile and recruiting tools</span><b>Included</b></li>
    @if($isAmplify)<li><span>4 highlight reels + 4 custom graphics</span><b>Included</b></li><li><span>4 managed coach outreach sends</span><b>Included</b></li><li><span>One-time setup</span><b>${{ rtrim(rtrim($setupDollars, '0'), '.') }}</b></li>@endif
  </ul><div class="total"><div class="l">Due today<small>@if($isAmplify)Then ${{ rtrim(rtrim($recurringDollars, '0'), '.') }} / month starting next billing cycle @else Then ${{ rtrim(rtrim($recurringDollars, '0'), '.') }} / month @endif</small></div><div class="r">${{ $initialDollars }}</div></div></div>
  <div class="fields" style="margin-top:24px">
    <div class="divider"><span>Billing information</span></div>
    <div class="f"><label>Billing name</label><input name="billing_name" id="billingName" type="text" autocomplete="name"><div class="msg">Enter billing name.</div></div>
    <div class="row"><div class="f"><label>Billing email</label><input name="billing_email" id="billingEmail" type="email" autocomplete="email"><div class="msg">Enter billing email.</div></div><div class="f"><label>Billing phone</label><input name="billing_phone" id="billingPhone" type="tel" autocomplete="tel"><div class="msg">Enter billing phone.</div></div></div>
    <div class="f"><label>Address</label><input name="billing_address_1" id="ba1" type="text" autocomplete="address-line1"><div class="msg">Enter billing address.</div></div>
    <div class="row3"><div class="f"><label>City</label><input name="billing_city" id="bcity" type="text" autocomplete="address-level2"><div class="msg">Enter city.</div></div><div class="f"><label>State</label><input name="billing_state" id="bstate" type="text" autocomplete="address-level1"><div class="msg">Enter state.</div></div><div class="f"><label>ZIP</label><input name="billing_postal_code" id="bzip" class="mono" type="text" autocomplete="postal-code"><div class="msg">Enter ZIP.</div></div></div>
    <div class="f"><label>Country</label><select name="billing_country" id="bcountry"><option value="US">United States</option><option value="CA">Canada</option><option value="GB">United Kingdom</option><option value="AU">Australia</option><option value="PH">Philippines</option></select></div>
    <div class="divider"><span>Card information</span></div>
    <div class="payment-next"><div class="payment-next-icon">••••</div><div><b>Continue to card details</b><span>Your card details are entered on the payment screen after you continue.</span></div></div>
    <div class="f"><label class="check"><input type="checkbox" name="terms" id="terms" value="1"><span>I agree to the Terms, Privacy Policy, and applicable domain terms.</span></label><div class="msg">Please accept the terms.</div></div>
  </div>
  <div class="nav"><button type="button" class="btn gho" data-go="3">Back</button><button type="button" class="btn pri" id="submitRegistration">Create account &amp; continue to payment</button></div>
</section>

<section class="panel" data-step="5">
  <div class="done-wrap"><div class="ring"><svg width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="#FF5A3C" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 8l9 6 9-6"/></svg></div><div class="eyebrow">Account created</div><h1 id="paidDoneTitle">Finish your payment</h1><div class="live-dom" id="final-dom">yourname.com</div><div class="prov" id="paymentProv"><i></i><span id="paymentProvText">Payment pending</span></div><p class="tiny" id="doneMessage" style="margin-top:18px">Continue to payment to activate your plan.</p><div class="nav" style="justify-content:center"><a class="btn pri" id="paymentLink" href="#" style="display:none;text-decoration:none;text-align:center">Continue to payment</a><a class="btn gho" id="continueProfile" href="/admin/my-profile" style="display:none;text-decoration:none">Continue to My Profile</a></div></div>
  <div class="next"><h3>What happens next</h3><ol><li><div><b>Complete your payment</b>Your selected paid plan activates after payment is completed.</div></li><li><div><b>Your domain moves forward</b>Your selected domain proceeds to the registration stage.</div></li><li><div><b>Build your PLYRCARD</b>Add film, photos, stats, and the rest of your profile from My Profile.</div></li></ol></div>
</section>
@else
<section class="panel active" data-step="1">
  <div class="eyebrow">Step 01 of 04</div><h1>Create your account</h1><p class="sub">This is the login you'll use to build and update your profile.</p>
  <div class="fields">
    <div class="row"><div class="f"><label>First name</label><input name="first_name" id="fn" type="text" autocomplete="given-name"><div class="msg">Enter first name.</div></div><div class="f"><label>Last name</label><input name="last_name" id="ln" type="text" autocomplete="family-name"><div class="msg">Enter last name.</div></div></div>
    <div class="f"><label>Email</label><input name="email" id="em" type="email" autocomplete="email" placeholder="you@email.com"><div class="msg">Enter a valid email.</div></div>
    <div class="f"><label>Mobile <span class="opt">— optional</span></label><input name="phone" id="ph" type="tel" autocomplete="tel"></div>
    <div class="f"><label>Password</label><div class="pw-wrap"><input name="password" id="pw" type="password" autocomplete="new-password"><button type="button" class="peek" id="peek">Show</button></div><input name="password_confirmation" id="pwc" type="hidden"><ul class="rules"><li data-r="len"><i></i>8+ characters</li><li data-r="up"><i></i>1 capital letter</li><li data-r="num"><i></i>1 number</li></ul><div class="msg">Password doesn't meet the requirements.</div></div>
    <div class="f"><label class="check"><input type="checkbox" name="is_minor" id="minor" value="1"><span>I'm under 18</span></label><div class="reveal" id="guardian"><p class="why">Athletes under 18 need a parent or guardian on the account.</p><div class="row"><div class="f"><label>Guardian name</label><input name="guardian_name" id="gn" type="text"><div class="msg">Enter guardian name.</div></div><div class="f"><label>Guardian email</label><input name="guardian_email" id="ge" type="email"><div class="msg">Enter guardian email.</div></div></div></div></div>
  </div><div class="nav"><button type="button" class="btn pri" data-go="2">Continue</button></div><p class="tiny">Already have a PLYRCARD? <a href="/login">Log in instead</a></p>
</section>
<section class="panel" data-step="2"><div class="eyebrow">Step 02 of 04</div><h1>Athlete basics</h1><p class="sub">The fields coaches filter on first.</p>@include('pages.registration-athlete-fields', ['paid' => false, 'athleteOnly' => true])<div class="nav"><button type="button" class="btn gho" data-go="1">Back</button><button type="button" class="btn pri" data-go="3">Continue</button></div></section>
<section class="panel" data-step="3"><div class="eyebrow">Step 03 of 04</div><h1>Team &amp; club</h1><p class="sub">Add the team context coaches use to verify your profile.</p>@include('pages.registration-team-fields', ['paid' => false])<div class="nav"><button type="button" class="btn gho" data-go="2">Back</button><button type="button" class="btn pri" data-go="4">Continue</button></div></section>
<section class="panel" data-step="4">
  <div class="eyebrow">Step 04 of 04</div><h1>Claim your link</h1><p class="sub">One simple PLYRCARD link for your profile.</p>
  <div class="claim" id="claim"><div class="k">Your PLYRSITE</div><div class="url"><span class="d">plyrcard.com/</span><span class="h" id="preview">your-name</span></div><div class="f" style="margin-top:17px"><label>Edit your link</label><input name="requested_handle" id="handle" type="text" autocapitalize="none" spellcheck="false"></div><div class="status" id="handleStatus"><span class="pip"></span><span>Type to check availability</span></div></div>
  <div class="sum"><div class="sum-h"><span class="t">Free PLYR</span><span class="p">$0 / MONTH</span></div><ul><li>Your PLYRSITE profile link and stats page</li><li>Highlights, photos, schedule, and academic info can be added after signup</li><li>Unlimited profile edits</li></ul><div class="up"><b>My Journey and Amplify</b> can be added later without rebuilding your account.</div></div>
  <div class="fields" style="margin-top:22px"><div class="f"><label class="check"><input type="checkbox" name="terms" id="terms" value="1"><span>I agree to the Terms and Privacy Policy.</span></label><div class="msg">Please accept the terms.</div></div></div>
  <div class="nav"><button type="button" class="btn gho" data-go="3">Back</button><button type="button" class="btn pri" id="submitRegistration" disabled>Create my account</button></div>
</section>
<section class="panel" data-step="5"><div class="done-wrap"><div class="ring"><svg width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="#FF5A3C" stroke-width="1.8"><path d="M5 12l4 4L19 6"/></svg></div><div class="eyebrow">Account created</div><h1>Your PLYRCARD is ready to build</h1><div class="done-mail" id="sent-to">you@email.com</div><p class="tiny" id="doneMessage" style="margin-top:14px">Your account is active. Continue to My Profile to add film, photos, stats, and the rest of your recruiting information.</p><div class="nav"><a href="/admin/my-profile" class="btn pri" style="text-decoration:none;text-align:center">Continue to My Profile</a></div></div></section>
@endif
</form>
</div></main></div>
<script>
(function(){
'use strict';
const PAID=@json($isPaid), PLAN=@json($planKey), POS=@json($sportPositions), HANDLE_URL=@json(route('marketing.registration.check-handle')), DOMAIN_URL=@json(route('marketing.registration.check-domain')), STATUS_URL=@json(route('marketing.registration.payment-status'));
const $=s=>document.querySelector(s), $$=s=>Array.from(document.querySelectorAll(s));
const form=$('#registrationForm'), alertBox=$('#formAlert'), loader=$('#pageLoader'), maxStep=PAID?4:4, successStep=PAID?5:5;
let cur=1, division='', pickedPositions=[], chosenDomain='', handleAvailable=false, handleTimer=null, statusPoll=null;
function showAlert(message){alertBox.textContent=message||'Please review the form and try again.';alertBox.classList.add('show');window.scrollTo({top:0,behavior:'smooth'})}
function clearAlert(){alertBox.classList.remove('show');alertBox.textContent=''}
function setLoading(on){loader.classList.toggle('show',!!on)}
function mail(v){return /^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test((v||'').trim())}
function bad(el,cond){if(!el)return !!cond;el.classList.toggle('err',!!cond);const f=el.closest('.f'),m=f&&f.querySelector('.msg');if(m)m.classList.toggle('show',!!cond);return !!cond}
function pwState(){const pw=$('#pw'),v=pw?pw.value:'',r={len:v.length>=8,up:/[A-Z]/.test(v),num:/[0-9]/.test(v)};$$('.rules li').forEach(li=>li.classList.toggle('ok',!!r[li.dataset.r]));if($('#pwc'))$('#pwc').value=v;return r.len&&r.up&&r.num}
if($('#pw'))$('#pw').addEventListener('input',pwState);if($('#peek'))$('#peek').addEventListener('click',function(){const pw=$('#pw'),on=pw.type==='password';pw.type=on?'text':'password';this.textContent=on?'Hide':'Show'});if($('#minor'))$('#minor').addEventListener('change',function(){$('#guardian')?.classList.toggle('open',this.checked)});
function goto(n){$$('.panel').forEach(p=>p.classList.toggle('active',+p.dataset.step===n));$$('#steps li').forEach((li,i)=>{li.classList.toggle('on',i+1===n);li.classList.toggle('done',i+1<n);const d=li.querySelector('.dot');if(d)d.textContent=i+1<n?'✓':i+1});if($('#mbar'))$('#mbar').style.width=(Math.min(n,maxStep)/maxStep*100)+'%';cur=n;window.scrollTo({top:0,behavior:'smooth'})}
function validateAccount(){let e=[];e.push(bad($('#fn'),!$('#fn')?.value.trim()));e.push(bad($('#ln'),!$('#ln')?.value.trim()));e.push(bad($('#em'),!mail($('#em')?.value||'')));if(PAID)e.push(bad($('#ph'),($('#ph')?.value||'').replace(/\D/g,'').length<7));e.push(bad($('#pw'),!pwState()));if($('#minor')?.checked){e.push(bad($('#gn'),!$('#gn')?.value.trim()));e.push(bad($('#ge'),!mail($('#ge')?.value||'')))}return !e.includes(true)}
function validateAthlete(){let e=[];e.push(bad($('#sport'),!$('#sport')?.value));const dm=$('#divisionWrap .msg');if(dm)dm.classList.toggle('show',!division);e.push(!division);e.push(bad($('#grad'),!$('#grad')?.value));const pm=$('#positionWrap .msg');if(pm)pm.classList.toggle('show',pickedPositions.length===0);e.push(pickedPositions.length===0);e.push(bad($('#hs'),!$('#hs')?.value.trim()));e.push(bad($('#st'),!$('#st')?.value));if(PAID||cur===3){e.push(bad($('#club'),!$('#club')?.value.trim()));e.push(bad($('#league'),!$('#league')?.value.trim()));e.push(bad($('#age'),!$('#age')?.value));if(PAID){e.push(bad($('#cn'),!$('#cn')?.value.trim()));e.push(bad($('#ce'),!mail($('#ce')?.value||'')))}}return !e.includes(true)}
function validateBilling(){let e=[];['billingName','billingEmail','billingPhone','ba1','bcity','bstate','bzip'].forEach(id=>{const el=$('#'+id);let invalid=!el?.value.trim();if(id==='billingEmail')invalid=!mail(el?.value||'');e.push(bad(el,invalid))});const terms=$('#terms');const m=terms?.closest('.f')?.querySelector('.msg');if(m)m.classList.toggle('show',!terms?.checked);e.push(!terms?.checked);return !e.includes(true)}
function validate(step){if(PAID){if(step===1){const no=!chosenDomain;$('#dmsg')?.classList.toggle('show',no);return !no}if(step===2)return validateAccount();if(step===3)return validateAthlete();if(step===4)return validateBilling()}else{if(step===1)return validateAccount();if(step===2)return validateAthlete();if(step===3)return validateAthlete();if(step===4){const terms=$('#terms'),m=terms?.closest('.f')?.querySelector('.msg');if(m)m.classList.toggle('show',!terms?.checked);return handleAvailable&&!!terms?.checked}}return true}
$$('[data-go]').forEach(b=>b.addEventListener('click',()=>{const n=+b.dataset.go;if(n>cur&&!validate(cur))return;clearAlert();if(PAID&&n===4){if($('#billingName')&&!$('#billingName').value)$('#billingName').value=($('#fn').value+' '+$('#ln').value).trim();if($('#billingEmail')&&!$('#billingEmail').value)$('#billingEmail').value=$('#em').value;if($('#billingPhone')&&!$('#billingPhone').value)$('#billingPhone').value=$('#ph').value;if($('#bstate')&&!$('#bstate').value)$('#bstate').value=$('#st')?.value||''}goto(n)}));
$$('input,select,textarea').forEach(el=>el.addEventListener('input',()=>{el.classList.remove('err');el.closest('.f')?.querySelector('.msg')?.classList.remove('show')}));
// Gender segmented control.
$$('#divisionWrap button').forEach(b=>b.addEventListener('click',()=>{$$('#divisionWrap button').forEach(x=>x.setAttribute('aria-pressed','false'));b.setAttribute('aria-pressed','true');division=b.dataset.value;$('#genderHidden').value=division;$('#divisionWrap .msg')?.classList.remove('show')}));
// Sport-specific position chips use the same keys already stored by the intake/profile flow.
if($('#sport'))$('#sport').addEventListener('change',function(){pickedPositions=[];const box=$('#pos');box.innerHTML='';const items=POS[this.value]||{};Object.entries(items).forEach(([key,label])=>{const b=document.createElement('button');b.type='button';b.className='chip';b.textContent=label;b.dataset.value=key;b.setAttribute('aria-pressed','false');b.addEventListener('click',()=>{const on=b.getAttribute('aria-pressed')==='true';if(!on&&pickedPositions.length>=3)return;b.setAttribute('aria-pressed',on?'false':'true');pickedPositions=on?pickedPositions.filter(x=>x!==key):pickedPositions.concat(key);$('#positionWrap .msg')?.classList.remove('show')});box.appendChild(b)});if(!Object.keys(items).length)box.innerHTML='<span class="hint">No positions configured for this sport.</span>'});
function slug(v,hyphen=true){let s=(v||'').toLowerCase().trim().replace(/[^a-z0-9\s-]/g,'').replace(/\s+/g,hyphen?'-':'').replace(/-+/g,'-').replace(/^-|-$/g,'');return s}
async function jsonGet(url,params){const u=new URL(url,window.location.origin);Object.entries(params).forEach(([k,v])=>u.searchParams.set(k,v));const r=await fetch(u.toString(),{headers:{Accept:'application/json'}});return r.json()}
if(PAID){
 const search=async()=>{
   const raw=$('#dq').value.trim().toLowerCase();
   if(raw.length<2)return;
   chosenDomain='';$('#domainHidden').value='';$('#bbody').classList.remove('live');
   const looksLikeDomain=raw.includes('.');
   const exact=looksLikeDomain?raw.replace(/^https?:\/\//,'').replace(/\/.*$/,'').replace(/:\d+$/,'').replace(/^\.+|\.+$/g,''):'';
   const base=slug(raw,false),hy=slug(raw,true);
   const candidates=(looksLikeDomain?[exact]:[base+'.com',hy+'.com',base+'athlete.com',base+'.net',base+'.co'])
     .filter((v,i,a)=>v.length>3&&a.indexOf(v)===i).slice(0,5);
   $('#dres').classList.remove('show');$('#dload').classList.add('show');
   const results=await Promise.all(candidates.map(async d=>{
     try{return await jsonGet(DOMAIN_URL,{domain:d})}
     catch(e){return{available:false,status:'unknown',domain:d,message:'Could not reach the domain lookup service.'}}
   }));
   $('#dload').classList.remove('show');
   const box=$('#dres');box.innerHTML='';
   results.forEach(res=>{
     const status=res.available?'Available':res.status==='registered'?'Registered':res.status==='unknown'?'Could not verify':'Unavailable';
     const tagClass=res.available?'ok':res.status==='unknown'?'warn':'no';
     const b=document.createElement('button');
     b.type='button';b.className='dom'+(res.available?'':' dead');b.setAttribute('aria-pressed','false');b.title=res.message||status;
     b.innerHTML='<span class="n">'+res.domain+'</span><span class="tag '+tagClass+'">'+status+'</span>';
     if(res.available)b.addEventListener('click',()=>{
       $$('#dres .dom').forEach(x=>{x.setAttribute('aria-pressed','false');const t=x.querySelector('.tag');if(!x.classList.contains('dead')){t.className='tag ok';t.textContent='Available'}});
       b.setAttribute('aria-pressed','true');b.querySelector('.tag').className='tag sel';b.querySelector('.tag').textContent='Selected';
       chosenDomain=res.domain;$('#domainHidden').value=res.domain;$('#urlbar').innerHTML='<b>'+res.domain+'</b>';$('#ord-dom').textContent=res.domain;$('#final-dom').textContent=res.domain;$('#bbody').classList.add('live');$('#dmsg').classList.remove('show');
     });
     box.appendChild(b);
   });
   box.classList.add('show');
 };
 $('#dgo').addEventListener('click',search);$('#dq').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();search()}});
}else{
 const handle=$('#handle'),preview=$('#preview'),status=$('#handleStatus'),claim=$('#claim'),submit=$('#submitRegistration');function setHandleStatus(cls,text){status.className='status '+cls;status.innerHTML='<span class="pip"></span><span>'+text+'</span>'}
 async function checkHandle(){const h=slug(handle.value,true);handle.value=h;preview.textContent=h||'your-name';handleAvailable=false;submit.disabled=true;claim.classList.remove('live');if(h.length<3){setHandleStatus('','Use at least 3 characters');return}setHandleStatus('wait','Checking availability');try{const res=await jsonGet(HANDLE_URL,{handle:h});handleAvailable=!!res.available;if(handleAvailable){claim.classList.add('live');setHandleStatus('ok',res.message);submit.disabled=!$('#terms')?.checked}else setHandleStatus('no',res.message)}catch(e){setHandleStatus('no','Could not check availability. Try again.')}}
 handle.addEventListener('input',()=>{clearTimeout(handleTimer);handleTimer=setTimeout(checkHandle,350)});$('#terms').addEventListener('change',()=>{submit.disabled=!(handleAvailable&&$('#terms').checked)});
}
function rebuildDynamicInputs(){const box=$('#dynamicInputs');box.innerHTML='';const add=(name,value)=>{const i=document.createElement('input');i.type='hidden';i.name=name;i.value=value;box.appendChild(i)};pickedPositions.forEach(v=>add('position[]',v))}
async function submitRegistration(){if(!validate(maxStep))return;clearAlert();rebuildDynamicInputs();setLoading(true);const fd=new FormData(form);try{const r=await fetch(form.action,{method:'POST',body:fd,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const data=await r.json().catch(()=>({}));if(!r.ok){const errors=data.errors||{};const first=Object.values(errors).flat()[0]||data.message||'Registration could not be completed.';showAlert(first);return}if(PAID){if(data.payment_url){window.location.assign(data.payment_url);return}$('#doneMessage').textContent=data.message||'Continue to payment to activate your plan.';goto(successStep);startPaymentPolling()}else{$('#sent-to').textContent=$('#em').value;$('#doneMessage').textContent=data.message||'Your account is ready.';goto(successStep)}}catch(e){showAlert('We could not complete registration. Check your connection and try again.')}finally{setLoading(false)}}
if($('#submitRegistration'))$('#submitRegistration').addEventListener('click',submitRegistration);
function startPaymentPolling(){if(!PAID)return;clearInterval(statusPoll);statusPoll=setInterval(async()=>{try{const r=await fetch(STATUS_URL,{headers:{Accept:'application/json'}});if(!r.ok)return;const data=await r.json();if(data.paid){clearInterval(statusPoll);const p=$('#paymentProv');p.classList.add('paid');$('#paymentProvText').textContent='Payment confirmed — plan active';$('#paidDoneTitle').textContent='You’re in';$('#continueProfile').style.display='inline-flex';$('#doneMessage').textContent='Your payment is confirmed. Your paid PLYRCARD plan is now active.'}}catch(e){}},5000)}
})();
</script>
</body>
</html>