@php
    $firstName = trim((string) ($user->first_name ?? '')) ?: 'Player';
    $dashboardUrl = trim((string) ($dashboardUrl ?? '')) ?: url('/admin');
    $plan = strtolower(trim((string) ($planKey ?? 'free')));
    $planLabel = $plan === 'amplify' ? 'Amplify' : ($plan === 'my-journey' ? 'My Journey' : 'Free');
    $website = $user->activeWebsite ?? null;
    $domain = trim((string) ($website->domain ?? ''));
    $slug = trim((string) ($website->slug ?? ''));
    $profileUrl = $domain !== '' ? 'https://' . preg_replace('/^https?:\/\//i','',$domain) : ($slug !== '' ? url('/'.ltrim($slug,'/')) : '');
@endphp
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Welcome to PLYRCARD</title></head>
<body style="margin:0;padding:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11"><tr><td align="center" style="padding:32px 12px"><table role="presentation" width="600" style="width:600px;max-width:600px">
<tr><td style="padding:0 30px 22px;font-size:20px;font-weight:850">PLYR<span style="color:#FF5A3C">CARD</span></td></tr>
<tr><td bgcolor="#131619" style="border:1px solid #242A31;border-radius:16px"><table role="presentation" width="100%">
<tr><td style="padding:36px 30px 0"><div style="font-family:Courier New,monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C">Account ready</div><h1 style="margin:12px 0 0;font-size:30px;line-height:1.15">Welcome to PLYRCARD, {{ $firstName }}.</h1><p style="margin:14px 0 0;font-size:15px;line-height:1.65;color:#98A2B3">Your {{ $planLabel }} account is ready. Complete your player profile, add your film and stats, and start managing your recruiting activity.</p></td></tr>
<tr><td style="padding:25px 30px 0"><a href="{{ $dashboardUrl }}" style="display:inline-block;padding:14px 26px;background:#FF5A3C;border-radius:10px;color:#fff;text-decoration:none;font-weight:800">Go to Dashboard</a></td></tr>
@if($profileUrl !== '')<tr><td style="padding:22px 30px 0"><table role="presentation" width="100%" bgcolor="#1A1E23" style="border:1px solid #262C33;border-radius:12px"><tr><td style="padding:18px"><div style="font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:#7E8793">Your PLYRCARD</div><div style="margin-top:8px;font-size:17px;font-weight:800;color:#F2F0ED;word-break:break-all">{{ $profileUrl }}</div></td></tr></table></td></tr>@endif
<tr><td style="padding:25px 30px 32px;font-size:13px;line-height:1.6;color:#7E8793">You can sign in anytime to update your profile, manage recruiting activity, billing, and account settings.</td></tr>
</table></td></tr><tr><td align="center" style="padding:22px 30px 0;font-size:11px;color:#5E6670">Built for players ready to be seen · © {{ date('Y') }} PLYRCARD</td></tr>
</table></td></tr></table></body></html>
