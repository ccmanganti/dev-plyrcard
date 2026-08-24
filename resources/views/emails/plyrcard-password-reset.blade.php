<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your PLYRCARD password</title>
</head>
<body style="margin:0;padding:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11">
    <tr>
        <td align="center" style="padding:32px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;">
                <tr>
                    <td style="padding:0 34px 22px;font-size:19px;font-weight:800;">PLYR<span style="color:#FF5A3C;">CARD</span></td>
                </tr>
                <tr>
                    <td bgcolor="#131619" style="background:#131619;border:1px solid #1E242A;border-radius:14px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:38px 34px 0;">
                                    <div style="font-family:'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C;">Password reset</div>
                                    <h1 style="margin:12px 0 0;font-size:32px;line-height:1.1;color:#F2F0ED;">Reset your PLYRCARD password</h1>
                                    <p style="margin:14px 0 0;font-size:15px;line-height:1.6;color:#868E99;">
                                        Hi {{ $user->first_name ?: 'Player' }}, we received a request to reset the password for your PLYRCARD account.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:26px 34px 0;">
                                    <a href="{!! $resetUrl !!}" style="display:inline-block;padding:14px 28px;background:#FF5A3C;border-radius:10px;color:#0C0E11;text-decoration:none;font-weight:700;">Reset Password</a>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:24px 34px 0;font-size:13px;line-height:1.6;color:#868E99;">
                                    This reset link expires in {{ $expiresInMinutes }} minutes. If you did not request a password reset, you can ignore this email and your password will remain unchanged.
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:24px 34px 34px;font-size:12px;line-height:1.6;color:#5E6670;word-break:break-all;">
                                    If the button does not work, copy and paste this link into your browser:<br>
                                    <span style="color:#868E99;">{!! $resetUrl !!}</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:24px 34px 0;font-size:11.5px;line-height:1.7;color:#5E6670;">PLYRCARD account security<br>&copy; 2026 PLYRCARD.</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
