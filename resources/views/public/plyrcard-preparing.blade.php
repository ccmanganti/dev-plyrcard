<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>We’re preparing your PLYRCARD</title>
    <style>
        :root { color-scheme:dark; --accent:#ff6338; --bg:#090d12; --muted:#98a2b3; }
        * { box-sizing:border-box; }
        body { margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 50% 18%,rgba(255,99,56,.14),transparent 32rem),var(--bg);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#fff; }
        .shell { width:min(760px,100%); }
        .brand { display:flex;align-items:center;gap:10px;margin-bottom:22px;font-size:14px;font-weight:900;letter-spacing:.08em; }
        .mark { width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:var(--accent);font-size:17px; }
        .card { position:relative;overflow:hidden;border:1px solid rgba(148,163,184,.16);border-radius:24px;background:linear-gradient(145deg,rgba(20,27,36,.98),rgba(12,17,24,.98));box-shadow:0 32px 100px rgba(0,0,0,.44);padding:clamp(28px,6vw,56px); }
        .card:before { content:"";position:absolute;inset:0 0 auto;height:3px;background:linear-gradient(90deg,transparent,var(--accent),#fb923c,transparent); }
        .status { display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border:1px solid rgba(255,99,56,.28);border-radius:999px;background:rgba(255,99,56,.08);color:#ff8d70;font-size:12px;font-weight:850;text-transform:uppercase;letter-spacing:.08em; }
        .dot { width:8px;height:8px;border-radius:999px;background:var(--accent);box-shadow:0 0 0 6px rgba(255,99,56,.08); }
        h1 { margin:22px 0 0;font-size:clamp(34px,7vw,62px);line-height:.98;letter-spacing:-.055em;max-width:650px; }
        p { margin:18px 0 0;color:var(--muted);font-size:clamp(15px,2vw,18px);line-height:1.65;max-width:620px; }
        .name { color:#fff;font-weight:800; }
        .steps { display:grid;gap:10px;margin-top:28px; }
        .step { display:grid;grid-template-columns:30px minmax(0,1fr);gap:11px;align-items:start;padding:13px 14px;border:1px solid rgba(148,163,184,.1);border-radius:14px;background:rgba(255,255,255,.025);color:#cbd5e1;font-size:14px;line-height:1.45; }
        .num { width:30px;height:30px;display:grid;place-items:center;border-radius:9px;background:rgba(255,99,56,.12);color:#ff7d60;font-weight:900;font-size:12px; }
        .actions { display:flex;flex-wrap:wrap;gap:12px;margin-top:30px; }
        .primary { min-height:48px;display:inline-flex;align-items:center;justify-content:center;padding:0 20px;border-radius:12px;background:var(--accent);color:#fff;text-decoration:none;font-weight:850;font-size:14px;box-shadow:0 12px 32px rgba(255,99,56,.2); }
        .note { margin-top:20px;padding-top:18px;border-top:1px solid rgba(148,163,184,.12);color:#697586;font-size:12px;line-height:1.55; }
    </style>
</head>
<body>
    <main class="shell">
        <div class="brand"><span class="mark">P</span><span>PLYRCARD</span></div>
        <section class="card">
            <span class="status"><span class="dot"></span> Preparing for publication</span>
            <h1>We’re preparing this PLYRCARD.</h1>
            <p><span class="name">{{ $website->name ?: 'This athlete' }}</span> has started building a PLYRCARD. The page is reserved, but it is not publicly published yet.</p>
            <div class="steps">
                <div class="step"><span class="num">01</span><span>Complete the athlete profile with the information coaches need to see.</span></div>
                <div class="step"><span class="num">02</span><span>Our team reviews the profile so the PLYRCARD can be verified and prepared for publication.</span></div>
                <div class="step"><span class="num">03</span><span>Once published, this same link will open the athlete’s live PLYRCARD.</span></div>
            </div>
            <div class="actions"><a class="primary" href="{{ $completeProfileUrl }}">Complete My Profile</a></div>
            <div class="note">Already finished your profile? No action is needed here. This page will automatically be replaced by the published PLYRCARD once preparation is complete.</div>
        </section>
    </main>
</body>
</html>
