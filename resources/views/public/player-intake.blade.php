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
            <div class="eyebrow">PlyrCard Intake</div>
            <h1 class="hero-title">Plyr <span class="accent">Intake</span> Form</h1>
            <p class="header-copy">
                Use this form to build your PLYRCard Portfolio: share your key details, highlights, and links so we can create a portfolio that’s accurate, polished, and ready to share.
            </p>
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
                            <div class="hint">The PlyrCard email will be generated automatically.</div>
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
                                            Paste the full URL to the one YouTube video you want featured on your PLYR Profile.<br><br>
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
                    <h2>Location, School, League & Club</h2>

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
                        <div class="col-3">
                            <label for="player_card_image">Player Card Image</label>
                            <input type="file" id="player_card_image" name="player_card_image" accept="image/png">
                            <div class="hint" id="player_card_hint">
                                Upload a PNG player card image. Transparent background preferred. Max 5MB.
                            </div>
                        </div>

                        <div class="col-3">
                            <label for="player_image">Player Image</label>
                            <input type="file" id="player_image" name="player_image" accept="image/png">
                            <div class="hint" id="player_image_hint">
                                Upload a PNG solo player image cropped from head to belly (half body). Transparent background preferred. Max 5MB.
                            </div>
                        </div>

                        <div class="col-3">
                            <label for="mobile_view_image">Mobile View Image</label>
                            <input type="file" id="mobile_view_image" name="mobile_view_image" accept="image/png">
                            <div class="hint" id="mobile_view_hint">
                                Upload a PNG mobile hero image for phone display on your website. Vertical-friendly crop recommended. Max 5MB.
                            </div>
                        </div>

                        <div class="col-3">
                            <label for="youtube_thumbnail">YouTube Thumbnail / Social Image</label>
                            <input type="file" id="youtube_thumbnail" name="youtube_thumbnail" accept="image/jpeg,image/jpg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                            <div class="hint">
                                Upload the image used as the highlights thumbnail, website social image, and SEO preview image. JPG, PNG, or WEBP. Max 5MB.
                            </div>
                        </div>

                        <div class="col-3">
                            <label for="logos_image">Logos</label>
                            <input type="file" id="logos_image" name="logos_image" accept="image/jpeg,image/jpg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                            <div class="hint">
                                Upload the logos image used in the footer or logo area of the website. JPG, PNG, or WEBP. Max 5MB.
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

    function updateImageInstructions() {
        const sport = document.getElementById('sport').value;
        const playerCardHint = document.getElementById('player_card_hint');
        const playerImageHint = document.getElementById('player_image_hint');
        const mobileViewHint = document.getElementById('mobile_view_hint');

        if (sport === 'soccer') {
            playerCardHint.textContent = 'Upload a PNG soccer player card image. Recommended portrait layout. Transparent background preferred. Max 5MB.';
            playerImageHint.textContent = 'Upload a PNG solo soccer player image cropped from the top of the head to around the belly area (half body). Transparent background preferred. Max 5MB.';
            mobileViewHint.textContent = 'Upload a PNG soccer mobile hero image for phone display. Vertical-friendly crop recommended. Max 5MB.';
            return;
        }

        if (sport === 'basketball') {
            playerCardHint.textContent = 'Upload a PNG basketball player card image. Recommended portrait layout. Transparent background preferred. Max 5MB.';
            playerImageHint.textContent = 'Upload a PNG solo basketball player image cropped from the top of the head to around the belly area (half body). Transparent background preferred. Max 5MB.';
            mobileViewHint.textContent = 'Upload a PNG basketball mobile hero image for phone display. Vertical-friendly crop recommended. Max 5MB.';
            return;
        }

        playerCardHint.textContent = 'Upload a PNG player card image. Recommended portrait layout. Transparent background preferred. Max 5MB.';
        playerImageHint.textContent = 'Upload a PNG solo player image cropped from the top of the head to around the belly area (half body). Transparent background preferred. Max 5MB.';
        mobileViewHint.textContent = 'Upload a PNG mobile hero image for phone display. Vertical-friendly crop recommended. Max 5MB.';
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

    document.getElementById('sport').addEventListener('change', () => {
        renderPositions();
        updateImageInstructions();
    });

    document.getElementById('school_id').addEventListener('change', toggleSchoolOther);
    document.getElementById('league_id').addEventListener('change', toggleLeagueOther);
    document.getElementById('club_id').addEventListener('change', toggleClubOther);
    document.getElementById('use_custom_highlights').addEventListener('change', toggleCustomHighlights);
    document.getElementById('country').addEventListener('change', () => {
        toggleCountryFields();
        syncStateValue();
    });
    document.getElementById('state_us').addEventListener('change', syncStateValue);
    document.getElementById('state_international').addEventListener('input', syncStateValue);

    renderPositions();
    toggleSchoolOther();
    toggleLeagueOther();
    toggleClubOther();
    updateImageInstructions();
    toggleCustomHighlights();
    toggleCountryFields();
    syncStateValue();
</script>
</body>
</html>