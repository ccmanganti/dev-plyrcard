<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your PlyrCard coach account is ready</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f5f5; margin:0; padding:24px;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; margin:auto; background:#fff; border-radius:18px; overflow:hidden;">
    <tr>
        <td style="padding:30px;">
            <h1 style="margin:0 0 12px; color:#111827;">Your PlyrCard coach account is ready</h1>

            <p style="color:#374151; line-height:1.6;">Hi {{ $coach->first_name ?: $coach->name ?: 'Coach' }},</p>

            <p style="color:#374151; line-height:1.6;">
                An account has been created for you as <strong>{{ $accessTitle }}</strong>.
                Use the login details below.
            </p>

            <div style="background:#111827; color:#fff; border-radius:14px; padding:18px; margin:20px 0;">
                <p style="margin:0 0 8px;"><strong>Email:</strong> {{ $coach->email }}</p>
                <p style="margin:0;"><strong>Temporary password:</strong> {{ $plainPassword }}</p>
            </div>

            <p>
                <a href="{{ $loginUrl }}" style="display:inline-block; background:#ff5c35; color:#fff; padding:12px 18px; border-radius:999px; text-decoration:none; font-weight:bold;">
                    Log in to PlyrCard
                </a>
            </p>

            <p style="color:#6b7280; font-size:13px; line-height:1.6;">
                Please change your password after logging in.
            </p>
        </td>
    </tr>
</table>
</body>
</html>