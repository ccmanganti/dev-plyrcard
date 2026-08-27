@php
    $firstName = trim((string) ($user->first_name ?? '')) ?: 'Player';
    $resetUrl = (string) ($resetUrl ?? '');
    $expiresInMinutes = (int) ($expiresInMinutes ?? 60);
@endphp
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset your PLYRCARD password</title></head>
<body style="margin:0;padding:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11"><tr><td align="center" style="padding:32px 12px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px">
<tr><td style="padding:0 30px 22px;font-size:20px;font-weight:850">PLYR<span style="color:#FF5A3C">CARD</span></td></tr>
<tr><td bgcolor="#131619" style="background:#131619;border:1px solid #242A31;border-radius:16px"><table role="presentation" width="100%">
<tr><td style="padding:36px 30px 0"><div style="font-family:Courier New,monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C">Account security</div><h1 style="margin:12px 0 0;font-size:30px;line-height:1.15;color:#F2F0ED">Reset your password</h1><p style="margin:14px 0 0;font-size:15px;line-height:1.65;color:#98A2B3">Hi {{ $firstName }}, we received a request to reset your PLYRCARD password. Use the button below to create a new one.</p></td></tr>
<tr><td style="padding:25px 30px 0"><a href="{{ $resetUrl }}" style="display:inline-block;padding:14px 26px;background:#FF5A3C;border-radius:10px;color:#fff;text-decoration:none;font-weight:800">Reset Password</a></td></tr>
<tr><td style="padding:22px 30px 32px;font-size:13px;line-height:1.6;color:#7E8793"><strong style="color:#D0D5DD">Link expires in {{ $expiresInMinutes }} minutes.</strong><br>If you did not request a password reset, you can safely ignore this email. Your password will not change unless the reset link is used.</td></tr>
</table></td></tr><tr><td align="center" style="padding:22px 30px 0;font-size:11px;line-height:1.5;color:#5E6670">PLYRCARD account security · © {{ date('Y') }} PLYRCARD</td></tr>
</table></td></tr></table></body></html>