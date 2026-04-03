<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plyr Intake Form</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700|bebas-neue:400" rel="stylesheet" />

    <style>
        :root {
            --bg: #f8f8f6;
            --panel: #f2f2f2;
            --panel-2: #eceae5;
            --field: #f7f7f7;
            --field-border: #cfcfd4;
            --text: #111111;
            --muted: #6b7280;
            --accent: #ff5f3a;
            --accent-2: #f97316;
            --border: #d8d8dc;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --radius-xl: 24px;
            --radius-lg: 20px;
            --radius-md: 16px;
            --radius-sm: 14px;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            color: var(--text);
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: var(--bg);
            background-image: radial-gradient(circle, rgba(148, 163, 184, 0.28) 1px, transparent 1.2px);
            background-size: 18px 18px;
        }

        body.embed-mode {
            padding: 14px;
        }

        .wrapper {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: 26px;
            overflow: hidden;
            background: #f5f5f5;
            box-shadow: var(--shadow);
        }

        .header {
            padding: 24px 24px 20px;
            background: #ede8df;
            border-bottom: 1px solid var(--border);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255, 95, 58, 0.2);
            background: rgba(255, 95, 58, 0.08);
            color: var(--accent-2);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .hero-title {
            margin: 0;
            font-family: "Bebas Neue", ui-sans-serif, system-ui;
            font-size: 68px;
            line-height: .9;
            letter-spacing: .015em;
            text-transform: uppercase;
            color: #000000;
        }

        .hero-title .accent {
            color: var(--accent);
        }

        .header-copy {
            margin: 8px 0 0;
            font-size: 20px;
            line-height: 1.3;
            color: #232323;
            max-width: 980px;
        }

        .content {
            padding: 20px;
            background: #f7f7f7;
        }

        .section {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 22px;
            background: #f3f3f3;
        }

        .section h2 {
            margin: 0 0 14px;
            font-size: 22px;
            line-height: 1.1;
            font-weight: 700;
            color: #1f2937;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 16px;
        }

        .col-12 { grid-column: span 12; }
        .col-8 { grid-column: span 8; }
        .col-6 { grid-column: span 6; }
        .col-4 { grid-column: span 4; }
        .col-3 { grid-column: span 3; }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }

        .required {
            color: var(--accent-2);
        }

        .field-label-inline {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .tooltip-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .info-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            border: 1px solid #111111;
            background: #ffffff;
            color: #111111;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            cursor: help;
            font-family: Arial, sans-serif;
        }

        .tooltip-box {
            position: absolute;
            left: 50%;
            bottom: calc(100% + 10px);
            transform: translateX(-50%);
            min-width: 280px;
            max-width: 360px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #111111;
            color: #ffffff;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 400;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.18);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .18s ease, visibility .18s ease, transform .18s ease;
            z-index: 40;
            text-transform: none;
            letter-spacing: normal;
            white-space: normal;
        }

        .tooltip-box::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 6px;
            border-style: solid;
            border-color: #111111 transparent transparent transparent;
        }

        .tooltip-wrap:hover .tooltip-box,
        .tooltip-wrap:focus-within .tooltip-box {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-2px);
        }

        .tooltip-box strong {
            color: #ffffff;
        }

        .tooltip-box ul {
            margin: 6px 0 0 18px;
            padding: 0;
        }

        .tooltip-box li {
            margin-bottom: 4px;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="url"],
        select,
        textarea {
            width: 100%;
            min-height: 44px;
            padding: 11px 14px;
            border-radius: 14px;
            border: 1px solid var(--field-border);
            background: var(--field);
            color: #111827;
            font-size: 14px;
            line-height: 1.45;
            outline: none;
            appearance: none;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        input::placeholder,
        textarea::placeholder {
            color: #9ca3af;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(255, 95, 58, 0.12);
        }

        textarea {
            min-height: 108px;
            resize: vertical;
        }

        input[type="file"] {
            width: 100%;
            min-height: 46px;
            padding: 10px 12px;
            border-radius: 14px;
            border: 1px dashed rgba(255, 95, 58, 0.35);
            background: #fff7f3;
            color: #111827;
        }

        .hint {
            margin-top: 7px;
            font-size: 12px;
            line-height: 1.45;
            color: var(--muted);
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 4px;
        }

        .check-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #ffffff;
            font-size: 14px;
            color: #111827;
        }

        .check-pill input {
            accent-color: var(--accent);
        }

        .other-wrap {
            display: none;
            margin-top: 10px;
        }

        .hidden-section {
            display: none;
        }

        .toggle-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 2px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #ffffff;
        }

        .toggle-copy {
            min-width: 0;
            flex: 1 1 auto;
        }

        .toggle-title {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }

        .toggle-description {
            margin: 4px 0 0;
            font-size: 12px;
            line-height: 1.45;
            color: var(--muted);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 30px;
            flex: 0 0 auto;
        }

        .switch input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            inset: 0;
            cursor: pointer;
            border-radius: 999px;
            background: #d1d5db;
            transition: .2s ease;
        }

        .slider::before {
            content: "";
            position: absolute;
            width: 22px;
            height: 22px;
            left: 4px;
            top: 4px;
            border-radius: 999px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,.18);
            transition: .2s ease;
        }

        .switch input:checked + .slider {
            background: var(--accent);
        }

        .switch input:checked + .slider::before {
            transform: translateX(24px);
        }

        .error-list,
        .success-box {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 14px;
        }

        .error-list {
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.25);
            background: rgba(239, 68, 68, 0.08);
        }

        .success-box {
            color: #166534;
            border: 1px solid rgba(34, 197, 94, 0.25);
            background: rgba(34, 197, 94, 0.08);
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 14px;
            background: var(--accent);
            color: #ffffff;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(255, 95, 58, 0.22);
            transition: background .18s ease, transform .12s ease;
        }

        .btn:hover {
            background: #f0522d;
        }

        .btn:active {
            transform: translateY(1px);
        }

        @media (max-width: 980px) {
            .hero-title {
                font-size: 52px;
            }

            .header-copy {
                font-size: 17px;
            }

            .col-8,
            .col-6,
            .col-4,
            .col-3 {
                grid-column: span 12;
            }

            .actions {
                justify-content: stretch;
            }

            .btn {
                width: 100%;
            }

            .tooltip-box {
                left: 0;
                transform: translateX(0);
                max-width: min(320px, calc(100vw - 48px));
            }

            .tooltip-box::after {
                left: 18px;
                transform: none;
            }

            .tooltip-wrap:hover .tooltip-box,
            .tooltip-wrap:focus-within .tooltip-box {
                transform: translateX(0) translateY(-2px);
            }
        }

        @media (max-width: 640px) {
            body.embed-mode {
                padding: 0;
            }

            .wrapper {
                max-width: 100%;
            }

            .card {
                border-radius: 0;
                border-left: 0;
                border-right: 0;
                box-shadow: none;
            }

            .header {
                padding: 18px 16px 16px;
            }

            .content {
                padding: 14px;
            }

            .section {
                padding: 16px;
                border-radius: 18px;
            }

            .hero-title {
                font-size: 42px;
            }

            .header-copy {
                font-size: 15px;
            }
        }
    </style>
</head>
<body class="embed-mode">
<div class="wrapper">
    <div class="card">
        <div class="header">
            <div class="eyebrow"><span translate="no">PlyrCard</span> Intake</div>
            <h1 class="hero-title"><span translate="no">Plyr</span> <span class="accent">Intake</span> Form</h1>
            <p class="header-copy">
                Use this form to build your <span translate="no">PLYRCard</span> Portfolio: share your key details, highlights, and links so we can create a portfolio that’s accurate, polished, and ready to share.
            </p>
            <p class="hint">This form will translate automatically based on detected region.</p>
        </div>

        <div class="content">
            @if (session('success'))
                <div class="success-box">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="error-list">
                    <strong>Please fix the following:</strong>
                    <ul style="margin: 10px 0 0 18px;">
                        @foreach ($errors->all() as $error)
                            <li style="margin-bottom: 4px;">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('public.player-intake.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="section">
                    <h2>Athlete Details</h2>

                    <div class="grid">
                        <div class="col-4">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" maxlength="255" required>
                        </div>

                        <div class="col-4">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" maxlength="255" required>
                        </div>

                        <div class="col-4">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select gender</option>
                                @foreach ($genderOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('gender') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-4">
                            <label for="personal_email">Personal Email <span class="required">*</span></label>
                            <input type="email" id="personal_email" name="personal_email" value="{{ old('personal_email') }}" maxlength="255" required>
                            <div class="hint">The <span translate="no">PlyrCard</span> email will be generated automatically.</div>
                        </div>

                        <div class="col-4">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" value="{{ old('phone') }}" maxlength="50" inputmode="tel">
                            <div class="hint">Use the athlete’s direct phone if available.</div>
                        </div>

                        <div class="col-4">
                            <label for="birth">Birth Date</label>
                            <input type="date" id="birth" name="birth" value="{{ old('birth') }}">
                        </div>

                        <div class="col-4">
                            <label for="year">Graduation Year</label>
                            <input type="text" id="year" name="year" value="{{ old('year') }}" maxlength="50" inputmode="numeric">
                        </div>

                        <div class="col-4">
                            <label for="sport">Sport <span class="required">*</span></label>
                            <select id="sport" name="sport" required>
                                <option value="">Select sport</option>
                                @foreach ($sportPositions as $sportKey => $positions)
                                    @php
                                        $enabledSports = ['basketball', 'soccer'];
                                        $isEnabled = in_array($sportKey, $enabledSports, true);
                                    @endphp
                                    <option
                                        value="{{ $sportKey }}"
                                        {{ old('sport') === $sportKey ? 'selected' : '' }}
                                        {{ $isEnabled ? '' : 'disabled' }}
                                    >
                                        {{ str($sportKey)->replace('_', ' ')->title() }}{{ $isEnabled ? '' : ' (Coming Soon)' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="hint">Currently available: Basketball and Soccer only.</div>
                        </div>

                        <div class="col-4">
                            <label for="jersey_number">Jersey Number</label>
                            <input type="text" id="jersey_number" name="jersey_number" value="{{ old('jersey_number') }}" maxlength="50">
                        </div>

                        <div class="col-4">
                            <label for="vertical_jump">Vertical Jump</label>
                            <input type="text" id="vertical_jump" name="vertical_jump" value="{{ old('vertical_jump') }}" maxlength="50">
                        </div>

                        <div class="col-4">
                            <label for="gpa">GPA</label>
                            <input type="text" id="gpa" name="gpa" value="{{ old('gpa') }}" maxlength="50">
                        </div>

                        <div class="col-4">
                            <label for="height">Height</label>
                            <input type="text" id="height" name="height" value="{{ old('height') }}" maxlength="50">
                        </div>

                        <div class="col-4">
                            <label for="weight">Weight</label>
                            <input type="text" id="weight" name="weight" value="{{ old('weight') }}" maxlength="50">
                        </div>

                        <div class="col-4">
                            <label for="max_speed">Max Speed</label>
                            <input type="text" id="max_speed" name="max_speed" value="{{ old('max_speed') }}" maxlength="50">
                        </div>

                        <div class="col-12">
                            <label>Position</label>
                            <div id="positionOptions" class="checkbox-group"></div>
                            <div class="hint">Only positions for the selected sport will be shown.</div>
                        </div>

                        <div class="col-4">
                            <label for="natl_team_exp">National Team Experience</label>
                            <select id="natl_team_exp" name="natl_team_exp">
                                <option value="">Select one</option>
                                <option value="1" {{ old('natl_team_exp') === '1' ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ old('natl_team_exp') === '0' ? 'selected' : '' }}>No</option>
                            </select>
                        </div>

                        <div class="col-4">
                            <label for="ig_handle">
                                <span class="field-label-inline">
                                    <span>Instagram Profile URL</span>
                                    <span class="tooltip-wrap" tabindex="0">
                                        <span class="info-icon">i</span>
                                        <span class="tooltip-box">
                                            Please paste your Instagram profile link (URL), not just your @handle.<br><br>
                                            <strong>Example:</strong><br>
                                            https://www.instagram.com/plyrcard/<br><br>
                                            <strong>Not accepted:</strong><br>
                                            @plyrcard or plyrcard
                                        </span>
                                    </span>
                                </span>
                            </label>
                            <input
                                type="url"
                                id="ig_handle"
                                name="ig_handle"
                                value="{{ old('ig_handle') }}"
                                maxlength="255"
                                placeholder="https://www.instagram.com/yourprofile/"
                            >
                        </div>

                        <div class="col-4">
                            <label for="x_handle">
                                <span class="field-label-inline">
                                    <span>X Profile URL</span>
                                    <span class="tooltip-wrap" tabindex="0">
                                        <span class="info-icon">i</span>
                                        <span class="tooltip-box">
                                            Please paste your Twitter/X profile link (URL), not just your @handle.<br><br>
                                            <strong>Example:</strong><br>
                                            https://x.com/plyrcard<br><br>
                                            <strong>Not accepted:</strong><br>
                                            @plyrcard or plyrcard
                                        </span>
                                    </span>
                                </span>
                            </label>
                            <input
                                type="url"
                                id="x_handle"
                                name="x_handle"
                                value="{{ old('x_handle') }}"
                                maxlength="255"
                                placeholder="https://x.com/yourprofile"
                            >
                        </div>

                        <div class="col-12">
                            <label for="yt_url">
                                <span class="field-label-inline">
                                    <span>YouTube Channel URL</span>
                                    <span class="tooltip-wrap" tabindex="0">
                                        <span class="info-icon">i</span>
                                        <span class="tooltip-box">
                                            Please paste your YouTube channel link (URL), not just the channel name.<br><br>
                                            We’ll use this to automatically pull highlights from your channel if you do not choose to manually pick your own videos.<br><br>
                                            <strong>Examples:</strong>
                                            <ul>
                                                <li>https://www.youtube.com/@YourChannelName</li>
                                                <li>https://www.youtube.com/channel/UCxxxxxxxxxxxxxxx</li>
                                                <li>https://www.youtube.com/c/YourCustomName</li>
                                            </ul>
                                            <strong>Not accepted:</strong><br>
                                            Channel name only or handle only.
                                        </span>
                                    </span>
                                </span>
                            </label>
                            <input
                                type="url"
                                id="yt_url"
                                name="yt_url"
                                value="{{ old('yt_url') }}"
                                maxlength="500"
                                placeholder="https://www.youtube.com/@YourChannelName"
                            >
                            <div class="hint">Optional. If you leave manual highlight selection off, the backend can use this channel URL to pull videos.</div>
                        </div>

                        <div class="col-6">
                            <label for="featured_video_url">
                                <span class="field-label-inline">
                                    <span>Featured Video URL</span>
                                    <span class="tooltip-wrap" tabindex="0">
                                        <span class="info-icon">i</span>
                                        <span class="tooltip-box">
                                            Paste the full URL to the one YouTube video you want featured on your <span translate="no">PLYR</span> Profile.<br><br>
                                            This can be your personal intro video or your best highlight.<br><br>
                                            <strong>Examples:</strong>
                                            <ul>
                                                <li>https://www.youtube.com/watch?v=dQw4w9WgXcQ</li>
                                                <li>https://youtu.be/dQw4w9WgXcQ</li>
                                            </ul>
                                            <strong>Not accepted:</strong><br>
                                            Video title only or channel link.
                                        </span>
                                    </span>
                                </span>
                            </label>
                            <input
                                type="url"
                                id="featured_video_url"
                                name="featured_video_url"
                                value="{{ old('featured_video_url') }}"
                                maxlength="500"
                                placeholder="https://www.youtube.com/watch?v=..."
                            >
                            <div class="hint">Optional. This is the main featured video for the website.</div>
                        </div>

                        <div class="col-6">
                            <label>Highlight Videos</label>
                            <div class="toggle-card">
                                <div class="toggle-copy">
                                    <p class="toggle-title">Pick My Own Videos</p>
                                    <p class="toggle-description">
                                        Turn this on if you want to manually add your highlight video URLs line by line. Leave it off to use the YouTube channel URL above.
                                    </p>
                                </div>

                                <label class="switch" for="use_custom_highlights">
                                    <input
                                        type="checkbox"
                                        id="use_custom_highlights"
                                        name="use_custom_highlights"
                                        value="1"
                                        {{ old('use_custom_highlights') ? 'checked' : '' }}
                                    >
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <div id="custom_highlights_wrap" class="col-12 hidden-section">
                            <label for="featured_video_urls">
                                <span class="field-label-inline">
                                    <span>Highlight Video URLs</span>
                                    <span class="tooltip-wrap" tabindex="0">
                                        <span class="info-icon">i</span>
                                        <span class="tooltip-box">
                                            Use this only if you turned on <strong>Pick My Own Videos</strong>.<br><br>
                                            Paste full video URLs here, one per line, in the order you want them displayed.<br><br>
                                            <strong>Examples:</strong>
                                            <ul>
                                                <li>https://www.youtube.com/watch?v=dQw4w9WgXcQ</li>
                                                <li>https://youtu.be/dQw4w9WgXcQ</li>
                                                <li>https://www.youtube.com/shorts/abcdeFGhijk</li>
                                            </ul>
                                            <strong>Not accepted:</strong><br>
                                            Video titles only or channel links in this field.
                                        </span>
                                    </span>
                                </span>
                            </label>
                            <textarea
                                id="featured_video_urls"
                                name="featured_video_urls"
                                placeholder="Enter one video URL per line&#10;https://www.youtube.com/watch?v=abc123&#10;https://www.youtube.com/watch?v=def456"
                            >{{ old('featured_video_urls') }}</textarea>
                            <div class="hint">One full video URL per line. Leave this blank if you want the system to use your YouTube channel URL instead.</div>
                        </div>

                        <div class="col-12">
                            <label for="player_bio">Player Bio</label>
                            <textarea
                                id="player_bio"
                                name="player_bio"
                                placeholder="Write a short player bio for the website."
                            >{{ old('player_bio') }}</textarea>
                            <div class="hint">This will be used in the website bio/about section.</div>
                        </div>

                        <div class="col-6">
                            <label for="academic_accolades">Academic Accolades</label>
                            <textarea
                                id="academic_accolades"
                                name="academic_accolades"
                                placeholder="Enter one accolade per line&#10;Honor Roll&#10;National Honor Society&#10;AP Scholar"
                            >{{ old('academic_accolades') }}</textarea>
                            <div class="hint">Enter one accolade per line.</div>
                        </div>

                        <div class="col-6">
                            <label for="sports_accolades">Sports Accolades</label>
                            <textarea
                                id="sports_accolades"
                                name="sports_accolades"
                                placeholder="Enter one accolade per line&#10;All League First Team&#10;MVP&#10;Team Captain"
                            >{{ old('sports_accolades') }}</textarea>
                            <div class="hint">Enter one accolade per line.</div>
                        </div>

                        <div class="col-12">
                            <label for="press">Press / Notes</label>
                            <textarea id="press" name="press">{{ old('press') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Location, School, League, Club & National Team</h2>

                    <div class="grid">
                        <div class="col-3">
                            <label for="country">Country</label>
                            <select id="country" name="country">
                                <option value="">Select country</option>
                                @foreach ($countryOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('country', 'USA') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="country_other_wrap" class="col-3 other-wrap">
                            <label for="country_other">Country Name</label>
                            <input
                                type="text"
                                id="country_other"
                                name="country_other"
                                value="{{ old('country_other') }}"
                                maxlength="255"
                                placeholder="Enter country name"
                            >
                        </div>

                        <div id="state_us_wrap" class="col-3">
                            <label for="state_us">State</label>
                            <select id="state_us">
                                <option value="">Select state</option>
                                @foreach ($states as $abbr => $label)
                                    <option value="{{ $abbr }}" {{ old('state') === $abbr ? 'selected' : '' }}>
                                        {{ $label }} ({{ $abbr }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="hint">State will be saved as its abbreviation.</div>
                        </div>

                        <div id="state_international_wrap" class="col-3 other-wrap">
                            <label for="state_international">State / Province / Region</label>
                            <input
                                type="text"
                                id="state_international"
                                value="{{ old('state') }}"
                                maxlength="255"
                                placeholder="Enter state, province, or region"
                            >
                            <div class="hint">For non-U.S. countries, enter the region, province, or state if applicable.</div>
                        </div>

                        <input type="hidden" id="state_hidden" name="state" value="{{ old('state') }}">

                        <div class="col-3">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" value="{{ old('city') }}" maxlength="255">
                        </div>

                        <div class="col-3">
                            <label for="street">Street</label>
                            <input type="text" id="street" name="street" value="{{ old('street') }}" maxlength="255">
                        </div>

                        <div class="col-3">
                            <label for="school_id">School</label>
                            <select id="school_id" name="school_id">
                                <option value="">Select school</option>
                                @foreach ($schools as $school)
                                    <option value="{{ $school->id }}" {{ (string) old('school_id') === (string) $school->id ? 'selected' : '' }}>
                                        {{ $school->name }}
                                    </option>
                                @endforeach
                                <option value="__other__" {{ old('school_id') === '__other__' ? 'selected' : '' }}>Other</option>
                            </select>
                            <div class="hint">Choose Other to manually enter a school not listed.</div>
                            <div id="school_other_wrap" class="other-wrap">
                                <input type="text" name="school_other" placeholder="Enter school name" value="{{ old('school_other') }}" maxlength="255">
                            </div>
                        </div>

                        <div class="col-3">
                            <label for="league_id">League</label>
                            <select id="league_id" name="league_id">
                                <option value="">Select league</option>
                                @foreach ($leagues as $league)
                                    <option value="{{ $league->id }}" {{ (string) old('league_id') === (string) $league->id ? 'selected' : '' }}>
                                        {{ $league->name }}
                                    </option>
                                @endforeach
                                <option value="__other__" {{ old('league_id') === '__other__' ? 'selected' : '' }}>Other</option>
                            </select>
                            <div class="hint">League is selected independently and is not tied to club selection.</div>
                            <div id="league_other_wrap" class="other-wrap">
                                <input type="text" id="league_other" name="league_other" value="{{ old('league_other') }}" maxlength="255" placeholder="Enter new league name">
                            </div>
                        </div>

                        <div class="col-3">
                            <label for="club_id">Club</label>
                            <select id="club_id" name="club_id">
                                <option value="">Select club</option>
                                @foreach ($clubs as $club)
                                    <option value="{{ $club->id }}" {{ (string) old('club_id') === (string) $club->id ? 'selected' : '' }}>
                                        {{ $club->name }}
                                    </option>
                                @endforeach
                                <option value="__other__" {{ old('club_id') === '__other__' ? 'selected' : '' }}>Other</option>
                            </select>
                            <div class="hint">Choose Other to manually enter a club not listed.</div>
                        </div>

                        <div class="col-3" id="national_team_field_wrap">
                            <label for="national_team_id">National Team</label>
                            <select id="national_team_id" name="national_team_id">
                                <option value="">Select national team</option>
                                @foreach ($nationalTeams as $nationalTeam)
                                    <option value="{{ $nationalTeam->id }}" {{ (string) old('national_team_id') === (string) $nationalTeam->id ? 'selected' : '' }}>
                                        {{ $nationalTeam->name }}
                                    </option>
                                @endforeach
                                <option value="__other__" {{ old('national_team_id') === '__other__' ? 'selected' : '' }}>Other</option>
                            </select>
                            <div class="hint">Choose Other to manually enter a national team not listed.</div>
                        </div>

                        <div class="col-3">
                            <label for="team_name">Team</label>
                            <input type="text" id="team_name" name="team_name" value="{{ old('team_name') }}" maxlength="255">
                        </div>

                        <div id="club_other_section" class="col-12 other-wrap">
                            <div class="grid">
                                <div class="col-6">
                                    <label for="club_other">New Club Name</label>
                                    <input type="text" id="club_other" name="club_other" value="{{ old('club_other') }}" placeholder="Enter new club name" maxlength="255">
                                </div>
                            </div>
                        </div>

                        <div id="national_team_other_section" class="col-12 other-wrap">
                            <div class="grid">
                                <div class="col-6">
                                    <label for="national_team_other">New National Team Name</label>
                                    <input
                                        type="text"
                                        id="national_team_other"
                                        name="national_team_other"
                                        value="{{ old('national_team_other') }}"
                                        placeholder="Enter new national team name"
                                        maxlength="255"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Parent / Guardian Information</h2>

                    <div class="grid">
                        <div class="col-4">
                            <label for="parent">Primary Parent / Guardian</label>
                            <input type="text" id="parent" name="parent" value="{{ old('parent') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="parent_email">Primary Parent Email</label>
                            <input type="email" id="parent_email" name="parent_email" value="{{ old('parent_email') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="parent_phone">Primary Parent Phone</label>
                            <input type="text" id="parent_phone" name="parent_phone" value="{{ old('parent_phone') }}" maxlength="50" inputmode="tel">
                        </div>

                        <div class="col-4">
                            <label for="sec_parent">Secondary Parent / Guardian</label>
                            <input type="text" id="sec_parent" name="sec_parent" value="{{ old('sec_parent') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="sec_parent_email">Secondary Parent Email</label>
                            <input type="email" id="sec_parent_email" name="sec_parent_email" value="{{ old('sec_parent_email') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="sec_parent_phone">Secondary Parent Phone</label>
                            <input type="text" id="sec_parent_phone" name="sec_parent_phone" value="{{ old('sec_parent_phone') }}" maxlength="50" inputmode="tel">
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Coaches & Trainers</h2>

                    <div class="grid">
                        <div class="col-4">
                            <label for="club_coach">Club Coach</label>
                            <input type="text" id="club_coach" name="club_coach" value="{{ old('club_coach') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="club_coach_email">Club Coach Email</label>
                            <input type="email" id="club_coach_email" name="club_coach_email" value="{{ old('club_coach_email') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="club_coach_phone">Club Coach Phone</label>
                            <input type="text" id="club_coach_phone" name="club_coach_phone" value="{{ old('club_coach_phone') }}" maxlength="50" inputmode="tel">
                        </div>

                        <div class="col-4">
                            <label for="natl_coach">National Coach</label>
                            <input type="text" id="natl_coach" name="natl_coach" value="{{ old('natl_coach') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="natl_coach_email">National Coach Email</label>
                            <input type="email" id="natl_coach_email" name="natl_coach_email" value="{{ old('natl_coach_email') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="natl_coach_phone">National Coach Phone</label>
                            <input type="text" id="natl_coach_phone" name="natl_coach_phone" value="{{ old('natl_coach_phone') }}" maxlength="50" inputmode="tel">
                        </div>

                        <div class="col-4">
                            <label for="tech_trainer">Technical Trainer</label>
                            <input type="text" id="tech_trainer" name="tech_trainer" value="{{ old('tech_trainer') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="tech_trainer_email">Technical Trainer Email</label>
                            <input type="email" id="tech_trainer_email" name="tech_trainer_email" value="{{ old('tech_trainer_email') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="tech_trainer_phone">Technical Trainer Phone</label>
                            <input type="text" id="tech_trainer_phone" name="tech_trainer_phone" value="{{ old('tech_trainer_phone') }}" maxlength="50" inputmode="tel">
                        </div>

                        <div class="col-4">
                            <label for="snc_trainer">Strength & Conditioning Trainer</label>
                            <input type="text" id="snc_trainer" name="snc_trainer" value="{{ old('snc_trainer') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="snc_trainer_email">S&amp;C Trainer Email</label>
                            <input type="email" id="snc_trainer_email" name="snc_trainer_email" value="{{ old('snc_trainer_email') }}" maxlength="255">
                        </div>

                        <div class="col-4">
                            <label for="snc_trainer_phone">S&amp;C Trainer Phone</label>
                            <input type="text" id="snc_trainer_phone" name="snc_trainer_phone" value="{{ old('snc_trainer_phone') }}" maxlength="50" inputmode="tel">
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Images</h2>

                    <div class="grid">
                        <div class="col-6">
                            <label for="action_images">Action Images</label>
                            <input
                                type="file"
                                id="action_images"
                                name="action_images[]"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                multiple
                            >
                            <div class="hint">
                                Upload action shots of the athlete. These will still be stored under raw player images.
                            </div>
                        </div>

                        <div class="col-6">
                            <label for="portrait_images">Portrait Images</label>
                            <input
                                type="file"
                                id="portrait_images"
                                name="portrait_images[]"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                multiple
                            >
                            <div class="hint" id="portrait_images_hint">
                                Upload portrait or solo player images. These will still be stored under raw player images.
                            </div>
                        </div>

                        <div class="col-6 other-wrap" id="national_team_images_wrap">
                            <label for="national_team_images">National Team Images</label>
                            <input
                                type="file"
                                id="national_team_images"
                                name="national_team_images[]"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                multiple
                            >
                            <div class="hint">
                                Upload images related to national team play. These will still be stored under raw player images.
                            </div>
                        </div>

                        <div class="col-6">
                            <label for="team_images">Team Images</label>
                            <input
                                type="file"
                                id="team_images"
                                name="team_images[]"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                multiple
                            >
                            <div class="hint">
                                Upload team-related images. These will still be stored under raw player images.
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="hint" id="raw_images_total_hint">
                                You can upload a combined maximum of 20 images across all four image groups. Max 5MB per image.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn">Submit Intake Form</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const sportPositions = @json($sportPositions);
    const oldPositions = @json(old('position', []));
    const enabledSports = ['basketball', 'soccer'];

    const REGION_TEST_OVERRIDE = null;
    const DETECTED_COUNTRY_FROM_SERVER = @json($detectedCountry ?? '');

    function getCountryCode() {
        if (REGION_TEST_OVERRIDE) {
            return String(REGION_TEST_OVERRIDE).toUpperCase();
        }

        if (DETECTED_COUNTRY_FROM_SERVER) {
            return String(DETECTED_COUNTRY_FROM_SERVER).toUpperCase();
        }

        return '';
    }

    function mapCountryToLanguage(countryCode) {
        const countryToLanguage = {
            FR: 'fr',
            BE: 'fr',
            CH: 'fr',
            DE: 'de',
            AT: 'de',
            ES: 'es',
            IT: 'it',
            NL: 'nl',
            PT: 'pt',
        };

        return countryToLanguage[countryCode] || 'en';
    }

    function getTargetLanguage() {
        const countryCode = getCountryCode();
        const language = mapCountryToLanguage(countryCode);

        console.log('DETECTED_COUNTRY_FROM_SERVER:', DETECTED_COUNTRY_FROM_SERVER);
        console.log('countryCode:', countryCode);
        console.log('language:', language);

        const debugEl = document.getElementById('translation-debug');
        if (debugEl) {
            debugEl.innerHTML =
                '<strong>JS language:</strong> ' + language +
                ' &nbsp;|&nbsp; <strong>JS country:</strong> ' + (countryCode || '(empty)');
        }

        return language;
    }

    const phraseTranslations = {
        es: {
            "Intake": "Ingreso",
            "Form": "Formulario",
            "Use this form to build your": "Use este formulario para crear su",
            "Portfolio: share your key details, highlights, and links so we can create a portfolio that’s accurate, polished, and ready to share.": "Portafolio: comparta sus datos clave, momentos destacados y enlaces para que podamos crear un portafolio preciso, pulido y listo para compartir.",
            "This form will translate automatically based on detected region.": "Este formulario se traducirá automáticamente según la región detectada.",
            "Athlete Details": "Detalles del atleta",
            "First Name": "Nombre",
            "Middle Name": "Segundo nombre",
            "Last Name": "Apellido",
            "Gender": "Género",
            "Select gender": "Seleccionar género",
            "Personal Email": "Correo electrónico personal",
            "The PlyrCard email will be generated automatically.": "El correo de PlyrCard se generará automáticamente.",
            "Phone": "Teléfono",
            "Use the athlete’s direct phone if available.": "Use el teléfono directo del atleta si está disponible.",
            "Birth Date": "Fecha de nacimiento",
            "Graduation Year": "Año de graduación",
            "Sport": "Deporte",
            "Select sport": "Seleccionar deporte",
            "Currently available: Basketball and Soccer only.": "Actualmente disponible: solo baloncesto y fútbol.",
            "Jersey Number": "Número de camiseta",
            "Vertical Jump": "Salto vertical",
            "GPA": "Promedio",
            "Height": "Altura",
            "Weight": "Peso",
            "Max Speed": "Velocidad máxima",
            "Position": "Posición",
            "Only positions for the selected sport will be shown.": "Solo se mostrarán las posiciones del deporte seleccionado.",
            "National Team Experience": "Experiencia en selección nacional",
            "Select one": "Seleccionar uno",
            "Yes": "Sí",
            "No": "No",
            "Instagram Profile URL": "URL del perfil de Instagram",
            "X Profile URL": "URL del perfil de X",
            "YouTube Channel URL": "URL del canal de YouTube",
            "Featured Video URL": "URL del video destacado",
            "Highlight Videos": "Videos destacados",
            "Pick My Own Videos": "Elegir mis propios videos",
            "Turn this on if you want to manually add your highlight video URLs line by line. Leave it off to use the YouTube channel URL above.": "Activa esto si quieres agregar manualmente las URLs de tus videos destacados línea por línea. Déjalo apagado para usar la URL del canal de YouTube de arriba.",
            "Highlight Video URLs": "URLs de videos destacados",
            "One full video URL per line. Leave this blank if you want the system to use your YouTube channel URL instead.": "Una URL completa de video por línea. Déjalo en blanco si quieres que el sistema use la URL de tu canal de YouTube.",
            "Player Bio": "Biografía del jugador",
            "This will be used in the website bio/about section.": "Esto se usará en la sección biografía/acerca de del sitio web.",
            "Academic Accolades": "Logros académicos",
            "Sports Accolades": "Logros deportivos",
            "Enter one accolade per line.": "Ingresa un logro por línea.",
            "Press / Notes": "Prensa / Notas",
            "Location, School, League, Club & National Team": "Ubicación, escuela, liga, club y selección nacional",
            "Country": "País",
            "Select country": "Seleccionar país",
            "Country Name": "Nombre del país",
            "Enter country name": "Ingresar nombre del país",
            "State": "Estado",
            "Select state": "Seleccionar estado",
            "State will be saved as its abbreviation.": "El estado se guardará como su abreviatura.",
            "State / Province / Region": "Estado / Provincia / Región",
            "Enter state, province, or region": "Ingresar estado, provincia o región",
            "For non-U.S. countries, enter the region, province, or state if applicable.": "Para países fuera de EE. UU., ingresa la región, provincia o estado si aplica.",
            "City": "Ciudad",
            "Street": "Calle",
            "School": "Escuela",
            "Select school": "Seleccionar escuela",
            "Other": "Otro",
            "Choose Other to manually enter a school not listed.": "Elige Otro para ingresar manualmente una escuela que no figure en la lista.",
            "Enter school name": "Ingresar nombre de la escuela",
            "League": "Liga",
            "Select league": "Seleccionar liga",
            "League is selected independently and is not tied to club selection.": "La liga se selecciona de forma independiente y no está vinculada a la selección del club.",
            "Enter new league name": "Ingresar nuevo nombre de la liga",
            "Club": "Club",
            "Select club": "Seleccionar club",
            "Choose Other to manually enter a club not listed.": "Elige Otro para ingresar manualmente un club que no figure en la lista.",
            "National Team": "Selección nacional",
            "Select national team": "Seleccionar selección nacional",
            "Choose Other to manually enter a national team not listed.": "Elige Otro para ingresar manualmente una selección nacional que no figure en la lista.",
            "Team": "Equipo",
            "New Club Name": "Nuevo nombre del club",
            "Enter new club name": "Ingresar nuevo nombre del club",
            "New National Team Name": "Nuevo nombre de la selección nacional",
            "Enter new national team name": "Ingresar nuevo nombre de la selección nacional",
            "Parent / Guardian Information": "Información de padre / tutor",
            "Primary Parent / Guardian": "Padre / tutor principal",
            "Primary Parent Email": "Correo del padre / tutor principal",
            "Primary Parent Phone": "Teléfono del padre / tutor principal",
            "Secondary Parent / Guardian": "Padre / tutor secundario",
            "Secondary Parent Email": "Correo del padre / tutor secundario",
            "Secondary Parent Phone": "Teléfono del padre / tutor secundario",
            "Coaches & Trainers": "Entrenadores y preparadores",
            "Club Coach": "Entrenador del club",
            "Club Coach Email": "Correo del entrenador del club",
            "Club Coach Phone": "Teléfono del entrenador del club",
            "National Coach": "Entrenador nacional",
            "National Coach Email": "Correo del entrenador nacional",
            "National Coach Phone": "Teléfono del entrenador nacional",
            "Technical Trainer": "Entrenador técnico",
            "Technical Trainer Email": "Correo del entrenador técnico",
            "Technical Trainer Phone": "Teléfono del entrenador técnico",
            "Strength & Conditioning Trainer": "Entrenador de fuerza y acondicionamiento",
            "S&C Trainer Email": "Correo del entrenador de fuerza y acondicionamiento",
            "S&C Trainer Phone": "Teléfono del entrenador de fuerza y acondicionamiento",
            "Images": "Imágenes",
            "Action Images": "Imágenes de acción",
            "Upload action shots of the athlete. These will still be stored under raw player images.": "Sube fotos de acción del atleta. Estas seguirán guardándose dentro de las imágenes sin procesar del jugador.",
            "Portrait Images": "Imágenes de retrato",
            "National Team Images": "Imágenes de selección nacional",
            "Upload images related to national team play. These will still be stored under raw player images.": "Sube imágenes relacionadas con la selección nacional. Estas seguirán guardándose dentro de las imágenes sin procesar del jugador.",
            "Team Images": "Imágenes de equipo",
            "Upload team-related images. These will still be stored under raw player images.": "Sube imágenes relacionadas con el equipo. Estas seguirán guardándose dentro de las imágenes sin procesar del jugador.",
            "You can upload a combined maximum of 20 images across all four image groups. Max 5MB per image.": "Puedes subir un máximo combinado de 20 imágenes entre los cuatro grupos. Máximo 5 MB por imagen.",
            "Submit Intake Form": "Enviar formulario de ingreso",
            "Please fix the following:": "Corrige lo siguiente:"
        },
        fr: {
            "Intake": "Inscription",
            "Form": "Formulaire",
            "Use this form to build your": "Utilisez ce formulaire pour créer votre",
            "Portfolio: share your key details, highlights, and links so we can create a portfolio that’s accurate, polished, and ready to share.": "Portfolio : partagez vos informations clés, vos highlights et vos liens afin que nous puissions créer un portfolio précis, soigné et prêt à être partagé.",
            "This form will translate automatically based on detected region.": "Ce formulaire sera traduit automatiquement en fonction de la région détectée.",
            "Athlete Details": "Détails de l’athlète",
            "First Name": "Prénom",
            "Middle Name": "Deuxième prénom",
            "Last Name": "Nom de famille",
            "Gender": "Genre",
            "Select gender": "Sélectionner le genre",
            "Personal Email": "E-mail personnel",
            "The PlyrCard email will be generated automatically.": "L’e-mail PlyrCard sera généré automatiquement.",
            "Phone": "Téléphone",
            "Use the athlete’s direct phone if available.": "Utilisez le téléphone direct de l’athlète s’il est disponible.",
            "Birth Date": "Date de naissance",
            "Graduation Year": "Année de diplôme",
            "Sport": "Sport",
            "Select sport": "Sélectionner un sport",
            "Currently available: Basketball and Soccer only.": "Actuellement disponible : basket-ball et football uniquement.",
            "Jersey Number": "Numéro de maillot",
            "Vertical Jump": "Détente verticale",
            "GPA": "Moyenne",
            "Height": "Taille",
            "Weight": "Poids",
            "Max Speed": "Vitesse maximale",
            "Position": "Poste",
            "Only positions for the selected sport will be shown.": "Seules les positions du sport sélectionné seront affichées.",
            "National Team Experience": "Expérience en équipe nationale",
            "Select one": "Sélectionner une option",
            "Yes": "Oui",
            "No": "Non",
            "Instagram Profile URL": "URL du profil Instagram",
            "X Profile URL": "URL du profil X",
            "YouTube Channel URL": "URL de la chaîne YouTube",
            "Featured Video URL": "URL de la vidéo en vedette",
            "Highlight Videos": "Vidéos de highlights",
            "Pick My Own Videos": "Choisir mes propres vidéos",
            "Turn this on if you want to manually add your highlight video URLs line by line. Leave it off to use the YouTube channel URL above.": "Activez ceci si vous souhaitez ajouter manuellement les URL de vos vidéos highlight ligne par ligne. Laissez désactivé pour utiliser l’URL de la chaîne YouTube ci-dessus.",
            "Highlight Video URLs": "URL des vidéos de highlights",
            "One full video URL per line. Leave this blank if you want the system to use your YouTube channel URL instead.": "Une URL vidéo complète par ligne. Laissez vide si vous voulez que le système utilise l’URL de votre chaîne YouTube.",
            "Player Bio": "Biographie du joueur",
            "This will be used in the website bio/about section.": "Cela sera utilisé dans la section biographie/à propos du site web.",
            "Academic Accolades": "Distinctions académiques",
            "Sports Accolades": "Distinctions sportives",
            "Enter one accolade per line.": "Saisissez une distinction par ligne.",
            "Press / Notes": "Presse / Notes",
            "Location, School, League, Club & National Team": "Lieu, école, ligue, club et équipe nationale",
            "Country": "Pays",
            "Select country": "Sélectionner un pays",
            "Country Name": "Nom du pays",
            "Enter country name": "Saisir le nom du pays",
            "State": "État",
            "Select state": "Sélectionner un État",
            "State will be saved as its abbreviation.": "L’État sera enregistré sous forme d’abréviation.",
            "State / Province / Region": "État / Province / Région",
            "Enter state, province, or region": "Saisir l’État, la province ou la région",
            "For non-U.S. countries, enter the region, province, or state if applicable.": "Pour les pays hors États-Unis, saisissez la région, la province ou l’État si applicable.",
            "City": "Ville",
            "Street": "Rue",
            "School": "École",
            "Select school": "Sélectionner une école",
            "Other": "Autre",
            "Choose Other to manually enter a school not listed.": "Choisissez Autre pour saisir manuellement une école non répertoriée.",
            "Enter school name": "Saisir le nom de l’école",
            "League": "Ligue",
            "Select league": "Sélectionner une ligue",
            "League is selected independently and is not tied to club selection.": "La ligue est sélectionnée indépendamment et n’est pas liée au choix du club.",
            "Enter new league name": "Saisir le nom de la nouvelle ligue",
            "Club": "Club",
            "Select club": "Sélectionner un club",
            "Choose Other to manually enter a club not listed.": "Choisissez Autre pour saisir manuellement un club non répertorié.",
            "National Team": "Équipe nationale",
            "Select national team": "Sélectionner une équipe nationale",
            "Choose Other to manually enter a national team not listed.": "Choisissez Autre pour saisir manuellement une équipe nationale non répertoriée.",
            "Team": "Équipe",
            "New Club Name": "Nouveau nom du club",
            "Enter new club name": "Saisir le nouveau nom du club",
            "New National Team Name": "Nouveau nom de l’équipe nationale",
            "Enter new national team name": "Saisir le nouveau nom de l’équipe nationale",
            "Parent / Guardian Information": "Informations parent / tuteur",
            "Primary Parent / Guardian": "Parent / tuteur principal",
            "Primary Parent Email": "E-mail du parent principal",
            "Primary Parent Phone": "Téléphone du parent principal",
            "Secondary Parent / Guardian": "Parent / tuteur secondaire",
            "Secondary Parent Email": "E-mail du parent secondaire",
            "Secondary Parent Phone": "Téléphone du parent secondaire",
            "Coaches & Trainers": "Entraîneurs et préparateurs",
            "Club Coach": "Entraîneur du club",
            "Club Coach Email": "E-mail de l’entraîneur du club",
            "Club Coach Phone": "Téléphone de l’entraîneur du club",
            "National Coach": "Entraîneur national",
            "National Coach Email": "E-mail de l’entraîneur national",
            "National Coach Phone": "Téléphone de l’entraîneur national",
            "Technical Trainer": "Préparateur technique",
            "Technical Trainer Email": "E-mail du préparateur technique",
            "Technical Trainer Phone": "Téléphone du préparateur technique",
            "Strength & Conditioning Trainer": "Préparateur physique",
            "S&C Trainer Email": "E-mail du préparateur physique",
            "S&C Trainer Phone": "Téléphone du préparateur physique",
            "Images": "Images",
            "Action Images": "Images d’action",
            "Upload action shots of the athlete. These will still be stored under raw player images.": "Téléchargez des photos d’action de l’athlète. Elles seront toujours enregistrées dans les images brutes du joueur.",
            "Portrait Images": "Images de portrait",
            "National Team Images": "Images d’équipe nationale",
            "Upload images related to national team play. These will still be stored under raw player images.": "Téléchargez des images liées au jeu en équipe nationale. Elles seront toujours enregistrées dans les images brutes du joueur.",
            "Team Images": "Images d’équipe",
            "Upload team-related images. These will still be stored under raw player images.": "Téléchargez des images liées à l’équipe. Elles seront toujours enregistrées dans les images brutes du joueur.",
            "You can upload a combined maximum of 20 images across all four image groups. Max 5MB per image.": "Vous pouvez télécharger un maximum combiné de 20 images sur les quatre groupes d’images. Max 5 Mo par image.",
            "Submit Intake Form": "Soumettre le formulaire d’inscription",
            "Please fix the following:": "Veuillez corriger les éléments suivants :"
        },
        de: {
            "Intake": "Anmeldung",
            "Form": "Formular",
            "Use this form to build your": "Verwenden Sie dieses Formular, um Ihr",
            "Portfolio: share your key details, highlights, and links so we can create a portfolio that’s accurate, polished, and ready to share.": "Portfolio zu erstellen: Teilen Sie Ihre wichtigsten Daten, Highlights und Links, damit wir ein Portfolio erstellen können, das präzise, professionell und bereit zum Teilen ist.",
            "This form will translate automatically based on detected region.": "Dieses Formular wird automatisch basierend auf der erkannten Region übersetzt.",
            "Athlete Details": "Athletendaten",
            "First Name": "Vorname",
            "Middle Name": "Zweiter Vorname",
            "Last Name": "Nachname",
            "Gender": "Geschlecht",
            "Select gender": "Geschlecht auswählen",
            "Personal Email": "Persönliche E-Mail",
            "The PlyrCard email will be generated automatically.": "Die PlyrCard-E-Mail wird automatisch generiert.",
            "Phone": "Telefon",
            "Use the athlete’s direct phone if available.": "Verwenden Sie die direkte Telefonnummer des Athleten, falls verfügbar.",
            "Birth Date": "Geburtsdatum",
            "Graduation Year": "Abschlussjahr",
            "Sport": "Sportart",
            "Select sport": "Sportart auswählen",
            "Currently available: Basketball and Soccer only.": "Derzeit verfügbar: nur Basketball und Fußball.",
            "Jersey Number": "Trikotnummer",
            "Vertical Jump": "Vertikalsprung",
            "GPA": "Notendurchschnitt",
            "Height": "Größe",
            "Weight": "Gewicht",
            "Max Speed": "Höchstgeschwindigkeit",
            "Position": "Position",
            "Only positions for the selected sport will be shown.": "Es werden nur Positionen für die ausgewählte Sportart angezeigt.",
            "National Team Experience": "Erfahrung in der Nationalmannschaft",
            "Select one": "Eine Option auswählen",
            "Yes": "Ja",
            "No": "Nein",
            "Instagram Profile URL": "Instagram-Profil-URL",
            "X Profile URL": "X-Profil-URL",
            "YouTube Channel URL": "YouTube-Kanal-URL",
            "Featured Video URL": "Empfohlene Video-URL",
            "Highlight Videos": "Highlight-Videos",
            "Pick My Own Videos": "Eigene Videos auswählen",
            "Turn this on if you want to manually add your highlight video URLs line by line. Leave it off to use the YouTube channel URL above.": "Aktivieren Sie dies, wenn Sie Ihre Highlight-Video-URLs manuell Zeile für Zeile hinzufügen möchten. Lassen Sie es deaktiviert, um die YouTube-Kanal-URL oben zu verwenden.",
            "Highlight Video URLs": "Highlight-Video-URLs",
            "One full video URL per line. Leave this blank if you want the system to use your YouTube channel URL instead.": "Eine vollständige Video-URL pro Zeile. Lassen Sie dies leer, wenn das System stattdessen Ihre YouTube-Kanal-URL verwenden soll.",
            "Player Bio": "Spielerbiografie",
            "This will be used in the website bio/about section.": "Dies wird im Bio-/Über-uns-Bereich der Website verwendet.",
            "Academic Accolades": "Akademische Auszeichnungen",
            "Sports Accolades": "Sportliche Auszeichnungen",
            "Enter one accolade per line.": "Geben Sie eine Auszeichnung pro Zeile ein.",
            "Press / Notes": "Presse / Notizen",
            "Location, School, League, Club & National Team": "Standort, Schule, Liga, Verein und Nationalmannschaft",
            "Country": "Land",
            "Select country": "Land auswählen",
            "Country Name": "Ländername",
            "Enter country name": "Ländernamen eingeben",
            "State": "Bundesland",
            "Select state": "Bundesland auswählen",
            "State will be saved as its abbreviation.": "Das Bundesland wird als Abkürzung gespeichert.",
            "State / Province / Region": "Bundesland / Provinz / Region",
            "Enter state, province, or region": "Bundesland, Provinz oder Region eingeben",
            "For non-U.S. countries, enter the region, province, or state if applicable.": "Für Länder außerhalb der USA geben Sie Region, Provinz oder Bundesland an, falls zutreffend.",
            "City": "Stadt",
            "Street": "Straße",
            "School": "Schule",
            "Select school": "Schule auswählen",
            "Other": "Andere",
            "Choose Other to manually enter a school not listed.": "Wählen Sie Andere, um eine nicht aufgeführte Schule manuell einzugeben.",
            "Enter school name": "Schulnamen eingeben",
            "League": "Liga",
            "Select league": "Liga auswählen",
            "League is selected independently and is not tied to club selection.": "Die Liga wird unabhängig ausgewählt und ist nicht an die Vereinauswahl gebunden.",
            "Enter new league name": "Neuen Liganamen eingeben",
            "Club": "Verein",
            "Select club": "Verein auswählen",
            "Choose Other to manually enter a club not listed.": "Wählen Sie Andere, um einen nicht aufgeführten Verein manuell einzugeben.",
            "National Team": "Nationalmannschaft",
            "Select national team": "Nationalmannschaft auswählen",
            "Choose Other to manually enter a national team not listed.": "Wählen Sie Andere, um eine nicht aufgeführte Nationalmannschaft manuell einzugeben.",
            "Team": "Team",
            "New Club Name": "Neuer Vereinsname",
            "Enter new club name": "Neuen Vereinsnamen eingeben",
            "New National Team Name": "Neuer Name der Nationalmannschaft",
            "Enter new national team name": "Neuen Namen der Nationalmannschaft eingeben",
            "Parent / Guardian Information": "Informationen zu Eltern / Erziehungsberechtigten",
            "Primary Parent / Guardian": "Hauptelternteil / Erziehungsberechtigter",
            "Primary Parent Email": "E-Mail des Haupterziehungsberechtigten",
            "Primary Parent Phone": "Telefon des Haupterziehungsberechtigten",
            "Secondary Parent / Guardian": "Zweiter Elternteil / Erziehungsberechtigter",
            "Secondary Parent Email": "E-Mail des zweiten Erziehungsberechtigten",
            "Secondary Parent Phone": "Telefon des zweiten Erziehungsberechtigten",
            "Coaches & Trainers": "Trainer und Coaches",
            "Club Coach": "Vereinstrainer",
            "Club Coach Email": "E-Mail des Vereinstrainers",
            "Club Coach Phone": "Telefon des Vereinstrainers",
            "National Coach": "Nationaltrainer",
            "National Coach Email": "E-Mail des Nationaltrainers",
            "National Coach Phone": "Telefon des Nationaltrainers",
            "Technical Trainer": "Techniktrainer",
            "Technical Trainer Email": "E-Mail des Techniktrainers",
            "Technical Trainer Phone": "Telefon des Techniktrainers",
            "Strength & Conditioning Trainer": "Kraft- und Konditionstrainer",
            "S&C Trainer Email": "E-Mail des Kraft- und Konditionstrainers",
            "S&C Trainer Phone": "Telefon des Kraft- und Konditionstrainers",
            "Images": "Bilder",
            "Action Images": "Aktionsbilder",
            "Upload action shots of the athlete. These will still be stored under raw player images.": "Laden Sie Actionfotos des Athleten hoch. Diese werden weiterhin unter den rohen Spielerbildern gespeichert.",
            "Portrait Images": "Porträtbilder",
            "National Team Images": "Nationalmannschaftsbilder",
            "Upload images related to national team play. These will still be stored under raw player images.": "Laden Sie Bilder hoch, die mit Einsätzen in der Nationalmannschaft zusammenhängen. Diese werden weiterhin unter den rohen Spielerbildern gespeichert.",
            "Team Images": "Teambilder",
            "Upload team-related images. These will still be stored under raw player images.": "Laden Sie teambezogene Bilder hoch. Diese werden weiterhin unter den rohen Spielerbildern gespeichert.",
            "You can upload a combined maximum of 20 images across all four image groups. Max 5MB per image.": "Sie können insgesamt maximal 20 Bilder über alle vier Bildgruppen hinweg hochladen. Max. 5 MB pro Bild.",
            "Submit Intake Form": "Anmeldeformular absenden",
            "Please fix the following:": "Bitte korrigieren Sie Folgendes:"
        },
        it: {
            "Intake": "Registrazione",
            "Form": "Modulo",
            "Use this form to build your": "Usa questo modulo per creare il tuo",
            "Portfolio: share your key details, highlights, and links so we can create a portfolio that’s accurate, polished, and ready to share.": "Portfolio: condividi i tuoi dettagli principali, highlights e link così possiamo creare un portfolio accurato, curato e pronto da condividere.",
            "This form will translate automatically based on detected region.": "Questo modulo verrà tradotto automaticamente in base alla regione rilevata.",
            "Athlete Details": "Dettagli dell’atleta",
            "First Name": "Nome",
            "Middle Name": "Secondo nome",
            "Last Name": "Cognome",
            "Gender": "Genere",
            "Select gender": "Seleziona genere",
            "Personal Email": "Email personale",
            "The PlyrCard email will be generated automatically.": "L’email PlyrCard verrà generata automaticamente.",
            "Phone": "Telefono",
            "Use the athlete’s direct phone if available.": "Usa il telefono diretto dell’atleta se disponibile.",
            "Birth Date": "Data di nascita",
            "Graduation Year": "Anno di diploma",
            "Sport": "Sport",
            "Select sport": "Seleziona sport",
            "Currently available: Basketball and Soccer only.": "Attualmente disponibili: solo basket e calcio.",
            "Jersey Number": "Numero di maglia",
            "Vertical Jump": "Salto verticale",
            "GPA": "Media scolastica",
            "Height": "Altezza",
            "Weight": "Peso",
            "Max Speed": "Velocità massima",
            "Position": "Posizione",
            "Only positions for the selected sport will be shown.": "Saranno mostrate solo le posizioni dello sport selezionato.",
            "National Team Experience": "Esperienza in nazionale",
            "Select one": "Seleziona una voce",
            "Yes": "Sì",
            "No": "No",
            "Instagram Profile URL": "URL del profilo Instagram",
            "X Profile URL": "URL del profilo X",
            "YouTube Channel URL": "URL del canale YouTube",
            "Featured Video URL": "URL del video in evidenza",
            "Highlight Videos": "Video highlights",
            "Pick My Own Videos": "Scegli i miei video",
            "Turn this on if you want to manually add your highlight video URLs line by line. Leave it off to use the YouTube channel URL above.": "Attiva questa opzione se vuoi aggiungere manualmente gli URL dei tuoi video highlight riga per riga. Lascia disattivato per usare l’URL del canale YouTube sopra.",
            "Highlight Video URLs": "URL dei video highlights",
            "One full video URL per line. Leave this blank if you want the system to use your YouTube channel URL instead.": "Un URL video completo per riga. Lascia vuoto se vuoi che il sistema usi l’URL del tuo canale YouTube.",
            "Player Bio": "Biografia del giocatore",
            "This will be used in the website bio/about section.": "Questo verrà usato nella sezione biografia/informazioni del sito.",
            "Academic Accolades": "Riconoscimenti accademici",
            "Sports Accolades": "Riconoscimenti sportivi",
            "Enter one accolade per line.": "Inserisci un riconoscimento per riga.",
            "Press / Notes": "Stampa / Note",
            "Location, School, League, Club & National Team": "Località, scuola, lega, club e nazionale",
            "Country": "Paese",
            "Select country": "Seleziona paese",
            "Country Name": "Nome del paese",
            "Enter country name": "Inserisci nome del paese",
            "State": "Stato",
            "Select state": "Seleziona stato",
            "State will be saved as its abbreviation.": "Lo stato verrà salvato come abbreviazione.",
            "State / Province / Region": "Stato / Provincia / Regione",
            "Enter state, province, or region": "Inserisci stato, provincia o regione",
            "For non-U.S. countries, enter the region, province, or state if applicable.": "Per i paesi non USA, inserisci la regione, provincia o stato se applicabile.",
            "City": "Città",
            "Street": "Via",
            "School": "Scuola",
            "Select school": "Seleziona scuola",
            "Other": "Altro",
            "Choose Other to manually enter a school not listed.": "Scegli Altro per inserire manualmente una scuola non presente nell’elenco.",
            "Enter school name": "Inserisci nome della scuola",
            "League": "Lega",
            "Select league": "Seleziona lega",
            "League is selected independently and is not tied to club selection.": "La lega è selezionata in modo indipendente e non è collegata alla selezione del club.",
            "Enter new league name": "Inserisci nuovo nome della lega",
            "Club": "Club",
            "Select club": "Seleziona club",
            "Choose Other to manually enter a club not listed.": "Scegli Altro per inserire manualmente un club non presente nell’elenco.",
            "National Team": "Nazionale",
            "Select national team": "Seleziona nazionale",
            "Choose Other to manually enter a national team not listed.": "Scegli Altro per inserire manualmente una nazionale non presente nell’elenco.",
            "Team": "Squadra",
            "New Club Name": "Nuovo nome del club",
            "Enter new club name": "Inserisci nuovo nome del club",
            "New National Team Name": "Nuovo nome della nazionale",
            "Enter new national team name": "Inserisci nuovo nome della nazionale",
            "Parent / Guardian Information": "Informazioni genitore / tutore",
            "Primary Parent / Guardian": "Genitore / tutore principale",
            "Primary Parent Email": "Email del genitore principale",
            "Primary Parent Phone": "Telefono del genitore principale",
            "Secondary Parent / Guardian": "Genitore / tutore secondario",
            "Secondary Parent Email": "Email del genitore secondario",
            "Secondary Parent Phone": "Telefono del genitore secondario",
            "Coaches & Trainers": "Allenatori e preparatori",
            "Club Coach": "Allenatore del club",
            "Club Coach Email": "Email dell’allenatore del club",
            "Club Coach Phone": "Telefono dell’allenatore del club",
            "National Coach": "Allenatore nazionale",
            "National Coach Email": "Email dell’allenatore nazionale",
            "National Coach Phone": "Telefono dell’allenatore nazionale",
            "Technical Trainer": "Preparatore tecnico",
            "Technical Trainer Email": "Email del preparatore tecnico",
            "Technical Trainer Phone": "Telefono del preparatore tecnico",
            "Strength & Conditioning Trainer": "Preparatore atletico",
            "S&C Trainer Email": "Email del preparatore atletico",
            "S&C Trainer Phone": "Telefono del preparatore atletico",
            "Images": "Immagini",
            "Action Images": "Immagini d'azione",
            "Upload action shots of the athlete. These will still be stored under raw player images.": "Carica foto d'azione dell'atleta. Verranno comunque salvate nelle immagini grezze del giocatore.",
            "Portrait Images": "Immagini ritratto",
            "National Team Images": "Immagini della nazionale",
            "Upload images related to national team play. These will still be stored under raw player images.": "Carica immagini relative alla nazionale. Verranno comunque salvate nelle immagini grezze del giocatore.",
            "Team Images": "Immagini di squadra",
            "Upload team-related images. These will still be stored under raw player images.": "Carica immagini relative alla squadra. Verranno comunque salvate nelle immagini grezze del giocatore.",
            "You can upload a combined maximum of 20 images across all four image groups. Max 5MB per image.": "Puoi caricare un massimo combinato di 20 immagini in tutti e quattro i gruppi. Max 5 MB per immagine.",
            "Submit Intake Form": "Invia modulo di registrazione",
            "Please fix the following:": "Correggi quanto segue:"
        },
        nl: {
            "Intake": "Inschrijving",
            "Form": "Formulier",
            "Use this form to build your": "Gebruik dit formulier om uw",
            "Portfolio: share your key details, highlights, and links so we can create a portfolio that’s accurate, polished, and ready to share.": "Portfolio op te bouwen: deel uw belangrijkste gegevens, highlights en links zodat wij een portfolio kunnen maken dat nauwkeurig, verzorgd en klaar is om te delen.",
            "This form will translate automatically based on detected region.": "Dit formulier wordt automatisch vertaald op basis van de gedetecteerde regio.",
            "Athlete Details": "Gegevens van de atleet",
            "First Name": "Voornaam",
            "Middle Name": "Tweede naam",
            "Last Name": "Achternaam",
            "Gender": "Geslacht",
            "Select gender": "Selecteer geslacht",
            "Personal Email": "Persoonlijke e-mail",
            "The PlyrCard email will be generated automatically.": "De PlyrCard-e-mail wordt automatisch gegenereerd.",
            "Phone": "Telefoon",
            "Use the athlete’s direct phone if available.": "Gebruik het directe telefoonnummer van de atleet indien beschikbaar.",
            "Birth Date": "Geboortedatum",
            "Graduation Year": "Afstudeerjaar",
            "Sport": "Sport",
            "Select sport": "Selecteer sport",
            "Currently available: Basketball and Soccer only.": "Momenteel beschikbaar: alleen basketbal en voetbal.",
            "Jersey Number": "Rugnummer",
            "Vertical Jump": "Verticale sprong",
            "GPA": "Gemiddeld cijfer",
            "Height": "Lengte",
            "Weight": "Gewicht",
            "Max Speed": "Maximale snelheid",
            "Position": "Positie",
            "Only positions for the selected sport will be shown.": "Alleen posities voor de geselecteerde sport worden getoond.",
            "National Team Experience": "Ervaring in het nationale team",
            "Select one": "Selecteer één",
            "Yes": "Ja",
            "No": "Nee",
            "Instagram Profile URL": "Instagram-profiel-URL",
            "X Profile URL": "X-profiel-URL",
            "YouTube Channel URL": "YouTube-kanaal-URL",
            "Featured Video URL": "Uitgelichte video-URL",
            "Highlight Videos": "Highlight-video’s",
            "Pick My Own Videos": "Mijn eigen video’s kiezen",
            "Turn this on if you want to manually add your highlight video URLs line by line. Leave it off to use the YouTube channel URL above.": "Zet dit aan als je handmatig je highlight-video-URL’s regel voor regel wilt toevoegen. Laat het uit om de YouTube-kanaal-URL hierboven te gebruiken.",
            "Highlight Video URLs": "Highlight-video-URL’s",
            "One full video URL per line. Leave this blank if you want the system to use your YouTube channel URL instead.": "Eén volledige video-URL per regel. Laat dit leeg als je wilt dat het systeem in plaats daarvan je YouTube-kanaal-URL gebruikt.",
            "Player Bio": "Spelersbio",
            "This will be used in the website bio/about section.": "Dit wordt gebruikt in de bio-/over-sectie van de website.",
            "Academic Accolades": "Academische onderscheidingen",
            "Sports Accolades": "Sportieve onderscheidingen",
            "Enter one accolade per line.": "Voer één onderscheiding per regel in.",
            "Press / Notes": "Pers / Notities",
            "Location, School, League, Club & National Team": "Locatie, school, competitie, club en nationaal team",
            "Country": "Land",
            "Select country": "Selecteer land",
            "Country Name": "Naam van land",
            "Enter country name": "Voer landnaam in",
            "State": "Staat",
            "Select state": "Selecteer staat",
            "State will be saved as its abbreviation.": "De staat wordt opgeslagen als afkorting.",
            "State / Province / Region": "Staat / Provincie / Regio",
            "Enter state, province, or region": "Voer staat, provincie of regio in",
            "For non-U.S. countries, enter the region, province, or state if applicable.": "Voor niet-Amerikaanse landen voer je de regio, provincie of staat in indien van toepassing.",
            "City": "Stad",
            "Street": "Straat",
            "School": "School",
            "Select school": "Selecteer school",
            "Other": "Anders",
            "Choose Other to manually enter a school not listed.": "Kies Anders om handmatig een school in te voeren die niet in de lijst staat.",
            "Enter school name": "Voer schoolnaam in",
            "League": "Competitie",
            "Select league": "Selecteer competitie",
            "League is selected independently and is not tied to club selection.": "De competitie wordt onafhankelijk geselecteerd en is niet gekoppeld aan de clubkeuze.",
            "Enter new league name": "Voer nieuwe competitienaam in",
            "Club": "Club",
            "Select club": "Selecteer club",
            "Choose Other to manually enter a club not listed.": "Kies Anders om handmatig een club in te voeren die niet in de lijst staat.",
            "National Team": "Nationaal team",
            "Select national team": "Selecteer nationaal team",
            "Choose Other to manually enter a national team not listed.": "Kies Anders om handmatig een nationaal team in te voeren dat niet in de lijst staat.",
            "Team": "Team",
            "New Club Name": "Nieuwe clubnaam",
            "Enter new club name": "Voer nieuwe clubnaam in",
            "New National Team Name": "Nieuwe naam van nationaal team",
            "Enter new national team name": "Voer nieuwe naam van nationaal team in",
            "Parent / Guardian Information": "Informatie ouder / voogd",
            "Primary Parent / Guardian": "Primaire ouder / voogd",
            "Primary Parent Email": "E-mail primaire ouder",
            "Primary Parent Phone": "Telefoon primaire ouder",
            "Secondary Parent / Guardian": "Secundaire ouder / voogd",
            "Secondary Parent Email": "E-mail secundaire ouder",
            "Secondary Parent Phone": "Telefoon secundaire ouder",
            "Coaches & Trainers": "Coaches en trainers",
            "Club Coach": "Clubcoach",
            "Club Coach Email": "E-mail clubcoach",
            "Club Coach Phone": "Telefoon clubcoach",
            "National Coach": "Nationale coach",
            "National Coach Email": "E-mail nationale coach",
            "National Coach Phone": "Telefoon nationale coach",
            "Technical Trainer": "Technische trainer",
            "Technical Trainer Email": "E-mail technische trainer",
            "Technical Trainer Phone": "Telefoon technische trainer",
            "Strength & Conditioning Trainer": "Kracht- en conditietrainer",
            "S&C Trainer Email": "E-mail kracht- en conditietrainer",
            "S&C Trainer Phone": "Telefoon kracht- en conditietrainer",
            "Images": "Afbeeldingen",
            "Action Images": "Actieafbeeldingen",
            "Upload action shots of the athlete. These will still be stored under raw player images.": "Upload actiefoto's van de atleet. Deze worden nog steeds opgeslagen onder ruwe spelersafbeeldingen.",
            "Portrait Images": "Portretafbeeldingen",
            "National Team Images": "Afbeeldingen nationaal team",
            "Upload images related to national team play. These will still be stored under raw player images.": "Upload afbeeldingen die te maken hebben met het nationale team. Deze worden nog steeds opgeslagen onder ruwe spelersafbeeldingen.",
            "Team Images": "Teamafbeeldingen",
            "Upload team-related images. These will still be stored under raw player images.": "Upload teamgerelateerde afbeeldingen. Deze worden nog steeds opgeslagen onder ruwe spelersafbeeldingen.",
            "You can upload a combined maximum of 20 images across all four image groups. Max 5MB per image.": "U kunt in totaal maximaal 20 afbeeldingen uploaden verdeeld over alle vier afbeeldingsgroepen. Maximaal 5 MB per afbeelding.",
            "Submit Intake Form": "Inschrijfformulier verzenden",
            "Please fix the following:": "Corrigeer het volgende:"
        }
    };

    function translateExactText(text, lang) {
        const dict = phraseTranslations[lang];
        if (!dict) return text;

        const trimmed = text.trim().replace(/\s+/g, ' ');
        if (!trimmed) return text;

        if (dict[trimmed]) {
            return text.replace(text.trim(), dict[trimmed]);
        }

        return text;
    }

    function shouldSkipNode(node) {
        if (!node || !node.parentElement) return true;

        const parent = node.parentElement;

        if (
            parent.closest('[translate="no"]') ||
            parent.closest('script') ||
            parent.closest('style')
        ) {
            return true;
        }

        return false;
    }

    function translateTextNodes(lang) {
        if (!lang || lang === 'en' || !phraseTranslations[lang]) {
            document.documentElement.setAttribute('lang', 'en');
            return;
        }

        document.documentElement.setAttribute('lang', lang);

        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            null
        );

        const textNodes = [];

        while (walker.nextNode()) {
            textNodes.push(walker.currentNode);
        }

        textNodes.forEach((node) => {
            if (shouldSkipNode(node)) return;

            const original = node.nodeValue;
            const translated = translateExactText(original, lang);

            if (translated !== original) {
                node.nodeValue = translated;
            }
        });

        document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach((el) => {
            if (el.closest('[translate="no"]')) return;

            const original = el.getAttribute('placeholder');
            if (!original) return;

            const translated = translateExactText(original, lang);
            if (translated !== original) {
                el.setAttribute('placeholder', translated);
            }
        });
    }

    function renderPositions() {
        const sportSelect = document.getElementById('sport');
        const container = document.getElementById('positionOptions');
        const selectedSport = sportSelect.value;

        container.innerHTML = '';

        if (!selectedSport || !sportPositions[selectedSport] || !enabledSports.includes(selectedSport)) {
            return;
        }

        Object.entries(sportPositions[selectedSport]).forEach(([key, label]) => {
            const wrapper = document.createElement('label');
            wrapper.className = 'check-pill';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'position[]';
            input.value = key;

            if (Array.isArray(oldPositions) && oldPositions.includes(key)) {
                input.checked = true;
            }

            const span = document.createElement('span');
            span.textContent = label;

            wrapper.appendChild(input);
            wrapper.appendChild(span);
            container.appendChild(wrapper);
        });
    }

    function toggleSchoolOther() {
        const select = document.getElementById('school_id');
        const wrap = document.getElementById('school_other_wrap');

        if (!select || !wrap) return;
        wrap.style.display = select.value === '__other__' ? 'block' : 'none';
    }

    function toggleLeagueOther() {
        const select = document.getElementById('league_id');
        const wrap = document.getElementById('league_other_wrap');

        if (!select || !wrap) return;
        wrap.style.display = select.value === '__other__' ? 'block' : 'none';
    }

    function toggleClubOther() {
        const clubSelect = document.getElementById('club_id');
        const clubOtherSection = document.getElementById('club_other_section');

        if (!clubSelect || !clubOtherSection) return;
        clubOtherSection.style.display = clubSelect.value === '__other__' ? 'block' : 'none';
    }

    function toggleNationalTeamOther() {
        const natlTeamExp = document.getElementById('natl_team_exp');
        const nationalTeamFieldWrap = document.getElementById('national_team_field_wrap');
        const nationalTeamSelect = document.getElementById('national_team_id');
        const nationalTeamOtherSection = document.getElementById('national_team_other_section');
        const nationalTeamImagesWrap = document.getElementById('national_team_images_wrap');
        const nationalTeamImagesInput = document.getElementById('national_team_images');

        if (!natlTeamExp || !nationalTeamFieldWrap || !nationalTeamSelect || !nationalTeamOtherSection) return;

        const hasExperience = natlTeamExp.value === '1';

        nationalTeamFieldWrap.style.display = hasExperience ? 'block' : 'none';

        if (nationalTeamImagesWrap) {
            nationalTeamImagesWrap.style.display = hasExperience ? 'block' : 'block';
        }

        if (!hasExperience) {
            nationalTeamFieldWrap.style.display = 'none';

            if (nationalTeamImagesWrap) {
                nationalTeamImagesWrap.style.display = 'none';
            }

            nationalTeamSelect.value = '';
            nationalTeamOtherSection.style.display = 'none';

            const otherInput = document.getElementById('national_team_other');
            if (otherInput) {
                otherInput.value = '';
            }

            if (nationalTeamImagesInput) {
                nationalTeamImagesInput.value = '';
            }

            return;
        }

        nationalTeamOtherSection.style.display = nationalTeamSelect.value === '__other__' ? 'block' : 'none';
    }

    function updateImageInstructions(lang) {
        const sport = document.getElementById('sport').value;
        const portraitHint = document.getElementById('portrait_images_hint');

        if (!portraitHint) return;

        const translatedHints = {
            en: {
                soccer: 'Upload portrait or solo soccer player images. These will still be stored under raw player images.',
                basketball: 'Upload portrait or solo basketball player images. These will still be stored under raw player images.',
                default: 'Upload portrait or solo player images. These will still be stored under raw player images.',
            },
            nl: {
                soccer: 'Upload portret- of solo-afbeeldingen van de voetballer. Deze worden nog steeds opgeslagen onder ruwe spelersafbeeldingen.',
                basketball: 'Upload portret- of solo-afbeeldingen van de basketballer. Deze worden nog steeds opgeslagen onder ruwe spelersafbeeldingen.',
                default: 'Upload portret- of solo-afbeeldingen van de speler. Deze worden nog steeds opgeslagen onder ruwe spelersafbeeldingen.',
            },
            fr: {
                soccer: 'Téléchargez des portraits ou images solo du joueur de football. Elles seront toujours enregistrées dans les images brutes du joueur.',
                basketball: 'Téléchargez des portraits ou images solo du basketteur. Elles seront toujours enregistrées dans les images brutes du joueur.',
                default: 'Téléchargez des portraits ou images solo du joueur. Elles seront toujours enregistrées dans les images brutes du joueur.',
            },
            de: {
                soccer: 'Laden Sie Porträt- oder Einzelbilder des Fußballspielers hoch. Diese werden weiterhin unter rohen Spielerbildern gespeichert.',
                basketball: 'Laden Sie Porträt- oder Einzelbilder des Basketballspielers hoch. Diese werden weiterhin unter rohen Spielerbildern gespeichert.',
                default: 'Laden Sie Porträt- oder Einzelbilder des Spielers hoch. Diese werden weiterhin unter rohen Spielerbildern gespeichert.',
            },
            es: {
                soccer: 'Sube retratos o imágenes individuales del jugador de fútbol. Estas seguirán guardándose en las imágenes sin procesar del jugador.',
                basketball: 'Sube retratos o imágenes individuales del jugador de baloncesto. Estas seguirán guardándose en las imágenes sin procesar del jugador.',
                default: 'Sube retratos o imágenes individuales del jugador. Estas seguirán guardándose en las imágenes sin procesar del jugador.',
            },
            it: {
                soccer: 'Carica ritratti o immagini singole del calciatore. Verranno comunque salvate nelle immagini grezze del giocatore.',
                basketball: 'Carica ritratti o immagini singole del giocatore di basket. Verranno comunque salvate nelle immagini grezze del giocatore.',
                default: 'Carica ritratti o immagini singole del giocatore. Verranno comunque salvate nelle immagini grezze del giocatore.',
            },
        };

        const hints = translatedHints[lang] || translatedHints.en;

        if (sport === 'soccer') {
            portraitHint.textContent = hints.soccer;
            return;
        }

        if (sport === 'basketball') {
            portraitHint.textContent = hints.basketball;
            return;
        }

        portraitHint.textContent = hints.default;
    }

    function toggleCustomHighlights() {
        const toggle = document.getElementById('use_custom_highlights');
        const wrap = document.getElementById('custom_highlights_wrap');

        if (!toggle || !wrap) return;
        wrap.style.display = toggle.checked ? 'block' : 'none';
    }

    function toggleCountryFields() {
        const countrySelect = document.getElementById('country');
        const countryOtherWrap = document.getElementById('country_other_wrap');
        const stateUsWrap = document.getElementById('state_us_wrap');
        const stateInternationalWrap = document.getElementById('state_international_wrap');
        const stateUs = document.getElementById('state_us');
        const stateInternational = document.getElementById('state_international');
        const stateHidden = document.getElementById('state_hidden');

        if (!countrySelect || !countryOtherWrap || !stateUsWrap || !stateInternationalWrap || !stateUs || !stateInternational || !stateHidden) {
            return;
        }

        const selectedCountry = countrySelect.value;

        if (selectedCountry === 'USA' || selectedCountry === '') {
            countryOtherWrap.style.display = 'none';
            stateUsWrap.style.display = 'block';
            stateInternationalWrap.style.display = 'none';
            stateHidden.value = stateUs.value || '';
            return;
        }

        if (selectedCountry === '__other__') {
            countryOtherWrap.style.display = 'block';
        } else {
            countryOtherWrap.style.display = 'none';
        }

        stateUsWrap.style.display = 'none';
        stateInternationalWrap.style.display = 'block';
        stateHidden.value = stateInternational.value || '';
    }

    function syncStateValue() {
        const country = document.getElementById('country').value;
        const stateUs = document.getElementById('state_us');
        const stateInternational = document.getElementById('state_international');
        const stateHidden = document.getElementById('state_hidden');

        if (!stateUs || !stateInternational || !stateHidden) return;

        if (country === 'USA' || country === '') {
            stateHidden.value = stateUs.value || '';
        } else {
            stateHidden.value = stateInternational.value || '';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const lang = getTargetLanguage();

        const heroTitle = document.querySelector('.hero-title');
        if (heroTitle) {
            heroTitle.innerHTML = '<span translate="no">Player</span> <span class="accent">Intake</span> Form';
        }

        renderPositions();
        toggleSchoolOther();
        toggleLeagueOther();
        toggleClubOther();
        toggleNationalTeamOther();
        toggleCustomHighlights();
        toggleCountryFields();
        syncStateValue();

        translateTextNodes(lang);
        updateImageInstructions(lang);

        document.getElementById('sport').addEventListener('change', () => {
            renderPositions();
            updateImageInstructions(lang);
            translateTextNodes(lang);
        });

        document.getElementById('school_id').addEventListener('change', toggleSchoolOther);
        document.getElementById('league_id').addEventListener('change', toggleLeagueOther);
        document.getElementById('club_id').addEventListener('change', toggleClubOther);
        document.getElementById('national_team_id')?.addEventListener('change', toggleNationalTeamOther);
        document.getElementById('natl_team_exp')?.addEventListener('change', toggleNationalTeamOther);
        document.getElementById('use_custom_highlights').addEventListener('change', toggleCustomHighlights);
        document.getElementById('country').addEventListener('change', () => {
            toggleCountryFields();
            syncStateValue();
            translateTextNodes(lang);
        });
        document.getElementById('state_us').addEventListener('change', syncStateValue);
        document.getElementById('state_international').addEventListener('input', syncStateValue);
    });
</script>
</body>
</html>