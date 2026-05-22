<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your PlyrCard Watchlist</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e5e7eb;">
                <tr>
                    <td style="background:#050505;color:#ffffff;padding:24px 26px;">
                        <div style="font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:#ff5c35;margin-bottom:8px;">PlyrCard Watchlist</div>
                        <h1 style="margin:0;font-size:28px;line-height:1.05;font-weight:900;letter-spacing:-.5px;">Saved Player Profiles</h1>
                        <p style="margin:10px 0 0;color:#d1d5db;font-size:14px;line-height:1.45;">{{ $club->name ?? 'Club' }}{{ filled($club->league?->name ?? null) ? ' / ' . $club->league->name : '' }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px 26px;border-bottom:1px solid #eef0f3;">
                        <p style="margin:0 0 6px;font-size:15px;line-height:1.45;">Hi {{ $coach['name'] ?? 'Coach' }},</p>
                        <p style="margin:0;color:#4b5563;font-size:14px;line-height:1.55;">Here is your saved PlyrCard watchlist. This email includes every player currently saved in your coach session.</p>
                        <div style="margin-top:14px;padding:12px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;color:#374151;font-size:13px;line-height:1.5;">
                            <strong>Coach:</strong> {{ $coach['name'] ?? '' }}<br>
                            <strong>School:</strong> {{ $coach['school'] ?? '' }}<br>
                            <strong>Title:</strong> {{ $coach['title'] ?? '' }}<br>
                            <strong>Email:</strong> {{ $coach['email'] ?? '' }}
                        </div>
                    </td>
                </tr>
                @foreach($watchlist as $savedPlayer)
                    <tr>
                        <td style="padding:20px 26px;border-bottom:1px solid #eef0f3;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:top;">
                                        <div style="font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#ff5c35;margin-bottom:6px;">
                                            {{ $savedPlayer['team_name'] ?? 'Team' }}
                                        </div>
                                        <h2 style="margin:0;color:#111827;font-size:22px;line-height:1.1;font-weight:900;">{{ $savedPlayer['player_name'] ?? 'Player' }}</h2>
                                        <p style="margin:8px 0 0;color:#4b5563;font-size:13px;line-height:1.45;">
                                            @if(filled($savedPlayer['jersey_number'] ?? null)) #{{ ltrim((string) $savedPlayer['jersey_number'], '#') }} @endif
                                            @if(filled($savedPlayer['position'] ?? null)) {{ filled($savedPlayer['jersey_number'] ?? null) ? ' / ' : '' }}{{ $savedPlayer['position'] }} @endif
                                            @if(filled($savedPlayer['year'] ?? null)) {{ (filled($savedPlayer['jersey_number'] ?? null) || filled($savedPlayer['position'] ?? null)) ? ' / ' : '' }}Class {{ $savedPlayer['year'] }} @endif
                                            @if(filled($savedPlayer['gpa'] ?? null)) / {{ $savedPlayer['gpa'] }} GPA @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;">
                                <tr>
                                    <td style="padding:12px 14px;color:#374151;font-size:13px;line-height:1.6;">
                                        <strong>Website:</strong>
                                        @if(filled($savedPlayer['player_url'] ?? $savedPlayer['website_url'] ?? null))
                                            <a href="{{ $savedPlayer['player_url'] ?? $savedPlayer['website_url'] }}" style="color:#ff5c35;text-decoration:none;font-weight:700;">{{ $savedPlayer['player_url'] ?? $savedPlayer['website_url'] }}</a>
                                        @else
                                            N/A
                                        @endif
                                        <br>
                                        <strong>Player Email:</strong> {{ $savedPlayer['player_email'] ?? 'N/A' }}<br>
                                        <strong>Personal Email:</strong> {{ $savedPlayer['player_personal_email'] ?? 'N/A' }}<br>
                                        <strong>Phone:</strong> {{ $savedPlayer['player_phone'] ?? 'N/A' }}<br>
                                        <strong>Parent:</strong> {{ $savedPlayer['parent'] ?? 'N/A' }}<br>
                                        <strong>Parent Email:</strong> {{ $savedPlayer['parent_email'] ?? 'N/A' }}<br>
                                        <strong>Parent Phone:</strong> {{ $savedPlayer['parent_phone'] ?? 'N/A' }}<br>
                                        <strong>Club Coach:</strong> {{ $savedPlayer['club_coach'] ?? 'N/A' }}<br>
                                        <strong>Club Coach Email:</strong> {{ $savedPlayer['club_coach_email'] ?? 'N/A' }}<br>
                                        <strong>Club Coach Phone:</strong> {{ $savedPlayer['club_coach_phone'] ?? 'N/A' }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td style="padding:18px 26px;background:#ffffff;color:#6b7280;font-size:12px;line-height:1.5;">
                        Sent by PlyrCard from support@plyrcard.com.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>