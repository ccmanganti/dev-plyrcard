@php
    $firstName = trim((string) ($user->first_name ?? '')) ?: 'Player';
    $website = $user->activeWebsite ?? null;
    $rawDomain = trim((string) ($website->domain ?? ''));
    $slug = trim((string) ($website->slug ?? ''));
    $profileUrl = $rawDomain !== ''
        ? 'https://' . preg_replace('/^https?:\/\//i', '', $rawDomain)
        : ($slug !== '' ? url('/' . ltrim($slug, '/')) : '');
    $dashboardUrl = trim((string) ($dashboardUrl ?? '')) ?: url('/admin');
    $isMyJourney = (bool) ($isMyJourney ?? false);
@endphp
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to PLYRCARD</title>
</head>
<body style="margin:0;padding:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11">
<tr><td align="center" style="padding:32px 12px;">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;">
        <tr>
            <td style="padding:0 34px 22px;font-size:19px;font-weight:800;color:#F2F0ED;">
                PLYR<span style="color:#FF5A3C;">CARD</span>
            </td>
        </tr>
        <tr>
            <td bgcolor="#131619" style="background:#131619;border:1px solid #1E242A;border-radius:14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:38px 34px 0;">
                            <div style="font-family:'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C;">Account created</div>
                            <h1 style="margin:12px 0 0;font-size:32px;line-height:1.1;color:#F2F0ED;">Welcome to PLYRCARD, {{ $firstName }}.</h1>
                            <p style="margin:14px 0 0;font-size:15px;line-height:1.6;color:#868E99;">Your account is ready. Open your dashboard to continue building your profile, adding your film and stats, and managing your recruiting activity.</p>
                        </td>
                    </tr>

                    @if($profileUrl !== '')
                    <tr>
                        <td style="padding:28px 34px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#1A1E23" style="background:#1A1E23;border:1px solid #262C33;border-radius:12px;">
                                <tr><td style="padding:22px;">
                                    <div style="font-family:'Courier New',monospace;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#868E99;">{{ $isMyJourney ? 'Requested PLYRSITE domain' : 'Your PLYRSITE' }}</div>
                                    <div style="margin-top:11px;font-size:20px;font-weight:800;color:#F2F0ED;word-break:break-all;">{{ $profileUrl }}</div>
                                    @if($isMyJourney)
                                    <div style="margin-top:12px;padding:11px 13px;border:1px solid #3A3324;border-radius:9px;background:#1B1812;color:#D7B56D;font-size:12.5px;line-height:1.5;">
                                        <strong style="color:#F2F0ED;">Pending PLYRCARD team review.</strong> Your selected domain will not be publicly visible yet. It will become available after the team reviews and approves the request.
                                    </div>
                                    @endif
                                </td></tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <tr>
                        <td style="padding:26px 34px 0;">
                            <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:14px 28px;background:#FF5A3C;border-radius:10px;color:#0C0E11;text-decoration:none;font-weight:700;">Go to Dashboard</a>
                            <p style="margin:10px 0 0;font-size:12px;line-height:1.5;color:#5E6670;">You can sign in and manage your PLYRCARD from the dashboard at any time.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 34px 34px;font-size:14px;line-height:1.6;color:#868E99;">
                            Questions? Reply to this email.
                            <div style="margin-top:16px;font-weight:600;color:#F2F0ED;">This is your journey. It has to come from you.<br><span style="color:#FF5A3C;">Authenticity is Key.</span></div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:24px 34px 0;font-size:11.5px;line-height:1.7;color:#5E6670;">You are receiving this because a PLYRCARD account was created with this address.<br>&copy; 2026 PLYRCARD.</td>
        </tr>
    </table>
</td></tr>
</table>
</body>
</html>
