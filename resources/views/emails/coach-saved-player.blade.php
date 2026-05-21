<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saved Player Information</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#050506;color:#ffffff;padding:22px 24px;">
                            <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#cbd5e1;font-weight:700;">PlyrCard Saved Player</div>
                            <h1 style="margin:8px 0 0;font-size:28px;line-height:1.05;font-weight:900;letter-spacing:-.02em;">{{ $savedPlayer['player_name'] ?? 'Player' }}</h1>
                            <div style="margin-top:8px;font-size:14px;color:#e5e7eb;">{{ $team->name ?? '' }} · {{ $club->name ?? '' }}</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 24px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#374151;">
                                Hi {{ $coach['name'] ?? 'Coach' }}, here is the player information you saved while reviewing {{ $club->name ?? 'the club' }}.
                            </p>

                            @if(!empty($savedPlayer['player_url']))
                                <p style="margin:0 0 20px;">
                                    <a href="{{ $savedPlayer['player_url'] }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:800;font-size:14px;">View Player Website</a>
                                </p>
                            @endif

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-bottom:18px;">
                                <tr>
                                    <td colspan="2" style="padding:10px 0 8px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#111827;font-weight:900;border-bottom:1px solid #e5e7eb;">Player Details</td>
                                </tr>
                                @php
                                    $detailRows = [
                                        'Name' => $savedPlayer['player_name'] ?? null,
                                        'Jersey' => $savedPlayer['jersey_number'] ?? null,
                                        'Position' => $savedPlayer['position'] ?? null,
                                        'Class' => $savedPlayer['year'] ?? null,
                                        'School' => $savedPlayer['school'] ?? null,
                                        'Sport' => $savedPlayer['sport'] ?? null,
                                        'GPA' => $savedPlayer['gpa'] ?? null,
                                        'Height' => $savedPlayer['height'] ?? null,
                                        'Weight' => $savedPlayer['weight'] ?? null,
                                        'Location' => trim(collect([$savedPlayer['city'] ?? null, $savedPlayer['state'] ?? null])->filter()->implode(', ')),
                                        'Club' => $savedPlayer['club_name'] ?? null,
                                        'Team' => $savedPlayer['team_name'] ?? null,
                                        'League' => $savedPlayer['league_name'] ?? null,
                                    ];
                                @endphp
                                @foreach($detailRows as $label => $value)
                                    @if(filled($value))
                                        <tr>
                                            <td style="width:34%;padding:9px 0;color:#6b7280;font-size:13px;border-bottom:1px solid #f3f4f6;">{{ $label }}</td>
                                            <td style="padding:9px 0;color:#111827;font-size:14px;font-weight:700;border-bottom:1px solid #f3f4f6;">{{ $value }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-bottom:18px;">
                                <tr>
                                    <td colspan="2" style="padding:10px 0 8px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#111827;font-weight:900;border-bottom:1px solid #e5e7eb;">Contact Information</td>
                                </tr>
                                @php
                                    $contactRows = [
                                        'Player Email' => $savedPlayer['player_email'] ?? null,
                                        'Personal Email' => $savedPlayer['player_personal_email'] ?? null,
                                        'Player Phone' => $savedPlayer['player_phone'] ?? null,
                                        'Parent' => $savedPlayer['parent'] ?? null,
                                        'Parent Email' => $savedPlayer['parent_email'] ?? null,
                                        'Parent Phone' => $savedPlayer['parent_phone'] ?? null,
                                        'Second Parent' => $savedPlayer['sec_parent'] ?? null,
                                        'Second Parent Email' => $savedPlayer['sec_parent_email'] ?? null,
                                        'Second Parent Phone' => $savedPlayer['sec_parent_phone'] ?? null,
                                        'Club Coach' => $savedPlayer['club_coach'] ?? null,
                                        'Club Coach Email' => $savedPlayer['club_coach_email'] ?? null,
                                        'Club Coach Phone' => $savedPlayer['club_coach_phone'] ?? null,
                                    ];
                                @endphp
                                @foreach($contactRows as $label => $value)
                                    @if(filled($value))
                                        <tr>
                                            <td style="width:34%;padding:9px 0;color:#6b7280;font-size:13px;border-bottom:1px solid #f3f4f6;">{{ $label }}</td>
                                            <td style="padding:9px 0;color:#111827;font-size:14px;font-weight:700;border-bottom:1px solid #f3f4f6;">
                                                @if(str_contains(strtolower($label), 'email'))
                                                    <a href="mailto:{{ $value }}" style="color:#0f172a;">{{ $value }}</a>
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                <tr>
                                    <td colspan="2" style="padding:10px 0 8px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#111827;font-weight:900;border-bottom:1px solid #e5e7eb;">Coach Check-In</td>
                                </tr>
                                <tr>
                                    <td style="width:34%;padding:9px 0;color:#6b7280;font-size:13px;border-bottom:1px solid #f3f4f6;">Coach</td>
                                    <td style="padding:9px 0;color:#111827;font-size:14px;font-weight:700;border-bottom:1px solid #f3f4f6;">{{ $coach['name'] ?? '' }}</td>
                                </tr>
                                <tr>
                                    <td style="width:34%;padding:9px 0;color:#6b7280;font-size:13px;border-bottom:1px solid #f3f4f6;">School</td>
                                    <td style="padding:9px 0;color:#111827;font-size:14px;font-weight:700;border-bottom:1px solid #f3f4f6;">{{ $coach['school'] ?? '' }}</td>
                                </tr>
                                @if(!empty($coach['title']))
                                    <tr>
                                        <td style="width:34%;padding:9px 0;color:#6b7280;font-size:13px;border-bottom:1px solid #f3f4f6;">Title</td>
                                        <td style="padding:9px 0;color:#111827;font-size:14px;font-weight:700;border-bottom:1px solid #f3f4f6;">{{ $coach['title'] }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="width:34%;padding:9px 0;color:#6b7280;font-size:13px;">Saved At</td>
                                    <td style="padding:9px 0;color:#111827;font-size:14px;font-weight:700;">{{ $savedPlayer['saved_at'] ?? now()->toDateTimeString() }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.45;">
                            This email was generated when a coach saved a player on a PlyrCard club or team landing page.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>