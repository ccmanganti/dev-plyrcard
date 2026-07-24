<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>External Tracking URL Generator</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body{font-family:Inter,system-ui,sans-serif;background:#f6f7fb;color:#111827;margin:0}
        .wrap{max-width:1100px;margin:32px auto;padding:0 16px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;margin-bottom:16px;box-shadow:0 8px 30px rgba(15,23,42,.05)}
        h1,h2{margin:0 0 8px}.muted{color:#6b7280}.row{display:flex;gap:10px;align-items:center}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.field{display:grid;gap:6px}.input,.select{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px}.btn{border:0;border-radius:10px;padding:10px 14px;background:#ff6338;color:#fff;font-weight:700;cursor:pointer}.btn.alt{background:#fff;color:#111827;border:1px solid #d1d5db}.player{display:flex;justify-content:space-between;padding:12px;border:1px solid #e5e7eb;border-radius:12px;text-decoration:none;color:inherit;margin-top:8px}.urlrow{display:grid;grid-template-columns:110px 1fr auto;gap:10px;align-items:center;margin-top:10px}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}@media(max-width:760px){.grid,.urlrow{grid-template-columns:1fr}.row{align-items:stretch;flex-direction:column}}
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <h1>External Tracking URL Generator</h1>
        <p class="muted">Generate GHL-ready profile, Instagram, YouTube, and X links.</p>
    </section>

    @if ($errors->any())
        <section class="card" style="color:#991b1b">{{ $errors->first() }}</section>
    @endif

    <section class="card">
        <form method="GET" action="{{ route('admin.external-tracking-url-generator') }}">
            <div class="row">
                <input class="input" name="search" value="{{ $search }}" placeholder="Search player name, email, slug, or domain">
                <button class="btn">Search</button>
            </div>
        </form>

        @foreach ($players as $player)
            @php($site = $player->websites->first())
            <a class="player" href="{{ route('admin.external-tracking-url-generator', ['player' => $player->id]) }}">
                <span>
                    <strong>{{ trim($player->first_name.' '.$player->last_name) ?: $player->email }}</strong><br>
                    <small class="muted">{{ $player->email }} @if($site) · {{ $site->domain ?: $site->slug }} @endif</small>
                </span>
                <span>Generate →</span>
            </a>
        @endforeach
    </section>

    @if ($selectedPlayer)
        <section class="card">
            <h2>{{ trim($selectedPlayer->first_name.' '.$selectedPlayer->last_name) ?: $selectedPlayer->email }}</h2>
            <form method="POST" action="{{ route('admin.external-tracking-url-generator.generate') }}">
                @csrf
                <input type="hidden" name="player_id" value="{{ $selectedPlayer->id }}">

                <div class="field" style="margin-bottom:12px">
                    <label>Website</label>
                    <select class="select" name="website_id" required>
                        @foreach ($selectedPlayer->websites as $website)
                            <option value="{{ $website->id }}">{{ $website->domain ?: config('app.url').'/'.$website->slug }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid">
                    <div class="field"><label>Campaign</label><input class="input" name="campaign" value="{{ old('campaign', 'recruiting') }}" required></div>
                    <div class="field"><label>Source</label><input class="input" name="source" value="{{ old('source', 'ghl') }}" required></div>
                    <div class="field"><label>Medium</label><input class="input" name="medium" value="{{ old('medium', 'email') }}" required></div>
                </div>

                <div class="row" style="margin:14px 0">
                    <label><input type="checkbox" name="include_contact_id" value="1" checked> Include GHL contact ID</label>
                    <label><input type="checkbox" name="include_email" value="1" checked> Include contact email</label>
                </div>

                <button class="btn">Generate links</button>
            </form>
        </section>
    @endif

    @if (is_array($generated))
        <section class="card">
            <h2>Generated links</h2>
            <p class="muted">Paste each URL directly into the corresponding GHL campaign button or link.</p>

            @foreach (['profile', 'instagram', 'youtube', 'x'] as $type)
                @if (! empty($generated[$type]))
                    <div class="urlrow">
                        <strong>{{ ucfirst($type) }}</strong>
                        <input id="url-{{ $type }}" class="input mono" readonly value="{{ $generated[$type] }}">
                        <button class="btn alt" type="button" onclick="navigator.clipboard.writeText(document.getElementById('url-{{ $type }}').value);this.textContent='Copied';setTimeout(()=>this.textContent='Copy',1200)">Copy</button>
                    </div>
                @endif
            @endforeach
        </section>
    @endif
</main>
</body>
</html>
