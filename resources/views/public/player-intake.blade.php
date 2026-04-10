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
            --bg: #0a0a0a;
            --panel: #111111;
            --panel-2: #181818;
            --field: #141414;
            --field-border: #2c2c2c;
            --text: #f5f5f5;
            --muted: #b0b0b0;
            --accent: #ff7a00;
            --accent-2: #ff9a1f;
            --border: #2a2a2a;
            --shadow: 0 18px 40px rgba(0, 0, 0, 0.38);
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
            background-image:
                radial-gradient(circle at top left, rgba(255, 122, 0, 0.08), transparent 32%),
                radial-gradient(circle, rgba(255, 122, 0, 0.12) 1px, transparent 1.2px);
            background-size: auto, 18px 18px;
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
            background: #101010;
            box-shadow: var(--shadow);
        }

        .header {
            padding: 24px 24px 20px;
            background:
                linear-gradient(135deg, rgba(255, 122, 0, 0.18) 0%, rgba(255, 122, 0, 0.05) 36%, rgba(0, 0, 0, 0) 100%),
                #111111;
            border-bottom: 1px solid var(--border);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255, 122, 0, 0.28);
            background: rgba(255, 122, 0, 0.12);
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
            color: #ffffff;
        }

        .hero-title .accent {
            color: var(--accent);
        }

        .header-copy {
            margin: 8px 0 0;
            font-size: 20px;
            line-height: 1.3;
            color: #d6d6d6;
            max-width: 980px;
        }

        .content {
            padding: 20px;
            background: #0f0f0f;
        }

        .tabs-bar {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .tab-btn {
            appearance: none;
            border: 1px solid var(--border);
            background: #151515;
            color: #d5d5d5;
            padding: 12px 14px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            transition: .2s ease;
        }

        .tab-btn.active {
            border-color: rgba(255, 122, 0, 0.45);
            background: rgba(255, 122, 0, 0.12);
            color: #fff;
        }

        .tab-btn.done {
            border-color: rgba(255, 122, 0, 0.25);
            background: rgba(255, 122, 0, 0.08);
            color: #ffbf84;
        }

        .section {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 22px;
            background: linear-gradient(180deg, #151515 0%, #111111 100%);
        }

        .section h2 {
            margin: 0 0 14px;
            font-size: 22px;
            line-height: 1.1;
            font-weight: 700;
            color: #ffffff;
        }

        .section-copy {
            margin: 0 0 18px;
            font-size: 13px;
            color: var(--muted);
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
            color: #f3f4f6;
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
            border: 1px solid rgba(255, 122, 0, 0.55);
            background: #1b1b1b;
            color: var(--accent-2);
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
            border: 1px solid rgba(255, 122, 0, 0.25);
            color: #ffffff;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 400;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .18s ease, visibility .18s ease, transform .18s ease;
            z-index: 40;
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
            border: 1px solid #2c2c2c;
            background: #141414;
            color: #f9fafb;
            font-size: 14px;
            line-height: 1.45;
            outline: none;
            appearance: none;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        input::placeholder,
        textarea::placeholder {
            color: #7b7b7b;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent);
            background: #1a1a1a;
            box-shadow: 0 0 0 4px rgba(255, 122, 0, 0.12);
        }

        select:disabled,
        input:disabled {
            opacity: .55;
            cursor: not-allowed;
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
            border: 1px dashed rgba(255, 122, 0, 0.35);
            background: rgba(255, 122, 0, 0.06);
            color: #f3f4f6;
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
            background: #171717;
            font-size: 14px;
            color: #f3f4f6;
        }

        .check-pill input {
            accent-color: var(--accent);
        }

        .other-wrap,
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
            background: #171717;
        }

        .toggle-copy {
            min-width: 0;
            flex: 1 1 auto;
        }

        .toggle-title {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
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
            background: #303030;
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
            box-shadow: 0 2px 6px rgba(0,0,0,.25);
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
            color: #fecaca;
            border: 1px solid rgba(239, 68, 68, 0.28);
            background: rgba(127, 29, 29, 0.22);
        }

        .success-box {
            color: #fdba74;
            border: 1px solid rgba(255, 122, 0, 0.28);
            background: rgba(255, 122, 0, 0.10);
        }

        .step-panel {
            display: none;
        }

        .step-panel.active {
            display: block;
        }

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
        }

        .actions-right {
            display: flex;
            gap: 12px;
            margin-left: auto;
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #ff7a00 0%, #ff9a1f 100%);
            color: #ffffff;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(255, 122, 0, 0.26);
            transition: background .18s ease, transform .12s ease, box-shadow .18s ease;
        }

        .btn:hover {
            background: linear-gradient(135deg, #f06f00 0%, #ff9110 100%);
            box-shadow: 0 14px 28px rgba(255, 122, 0, 0.32);
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn-secondary {
            background: #1b1b1b;
            box-shadow: none;
            border: 1px solid var(--border);
            color: #fff;
        }

        .btn-secondary:hover {
            background: #242424;
            box-shadow: none;
        }

        .submit-wrap {
            display: none;
        }

        .submit-wrap.visible {
            display: inline-flex;
        }

        @media (max-width: 980px) {
            .hero-title {
                font-size: 52px;
            }

            .header-copy {
                font-size: 17px;
            }

            .tabs-bar {
                grid-template-columns: 1fr;
            }

            .col-8,
            .col-6,
            .col-4,
            .col-3 {
                grid-column: span 12;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .actions-right {
                margin-left: 0;
                width: 100%;
                flex-direction: column;
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

            <div class="tabs-bar" id="stepsBar">
                <button type="button" class="tab-btn" data-step-pill="1">1. Athlete Details</button>
                <button type="button" class="tab-btn" data-step-pill="2">2. School & Team</button>
                <button type="button" class="tab-btn" data-step-pill="3">3. Media & Bio</button>
                <button type="button" class="tab-btn" data-step-pill="4">4. Contacts</button>
                <button type="button" class="tab-btn" data-step-pill="5">5. Images</button>
            </div>

            <form method="POST" action="{{ route('public.player-intake.store') }}" enctype="multipart/form-data" id="playerIntakeForm">
                @csrf

                <div class="step-panel" data-step="1">
                    <div class="section">
                        <h2>Athlete Details</h2>
                        <p class="section-copy">Basic player information, sport details, and athletic profile.</p>

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
                                <label for="gender">Gender <span class="required">*</span></label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select gender</option>
                                    @foreach ($genderOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('gender') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="hint">Required. Your gender filters the available leagues in the next tab and is used if you create a new league.</div>
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

                            <div class="col-4 other-wrap" id="dominant_foot_wrap">
                                <label for="dominant_foot">Dominant Foot</label>
                                <select id="dominant_foot" name="dominant_foot">
                                    <option value="">Select dominant foot</option>
                                    <option value="left" {{ old('dominant_foot') === 'left' ? 'selected' : '' }}>Left</option>
                                    <option value="right" {{ old('dominant_foot') === 'right' ? 'selected' : '' }}>Right</option>
                                    <option value="both" {{ old('dominant_foot') === 'both' ? 'selected' : '' }}>Both</option>
                                </select>
                                <div class="hint">Only shown for soccer players.</div>
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

                            <div class="col-4 other-wrap" id="national_team_period_wrap">
                                <label for="national_team_period">National Team Period</label>
                                <input
                                    type="text"
                                    id="national_team_period"
                                    name="national_team_period"
                                    value="{{ old('national_team_period') }}"
                                    maxlength="255"
                                    placeholder="Example: 2022-2024"
                                >
                                <div class="hint">Enter the year range or period played for the national team.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-panel" data-step="2">
                    <div class="section">
                        <h2>Location, School, League, Club & National Team</h2>
                        <p class="section-copy">Choose a league first, then club, then team. Club and team will unlock as you go.</p>

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
                                <input type="text" id="country_other" name="country_other" value="{{ old('country_other') }}" maxlength="255" placeholder="Enter country name">
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
                                <input type="text" id="state_international" value="{{ old('state') }}" maxlength="255" placeholder="Enter state, province, or region">
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
                                </select>
                                <div class="hint">Filtered by athlete gender. Choose Other to create a new league, club, and team.</div>
                            </div>

                            <div class="col-3">
                                <label for="club_id">Club</label>
                                <select id="club_id" name="club_id" disabled>
                                    <option value="">Select club</option>
                                </select>
                                <div class="hint">Enabled after league selection.</div>
                            </div>

                            <div class="col-3">
                                <label for="team_id">Team</label>
                                <select id="team_id" name="team_id" disabled>
                                    <option value="">Select team</option>
                                </select>
                                <div class="hint">Enabled after club selection.</div>
                            </div>

                            <div class="col-3 other-wrap" id="league_other_wrap">
                                <label for="league_other">League Name</label>
                                <input type="text" id="league_other" name="league_other" value="{{ old('league_other') }}" maxlength="255" placeholder="Enter league name">
                            </div>

                            <div class="col-3 other-wrap" id="club_other_wrap">
                                <label for="club_other">Club Name</label>
                                <input type="text" id="club_other" name="club_other" value="{{ old('club_other') }}" maxlength="255" placeholder="Enter club name">
                            </div>

                            <div class="col-3 other-wrap" id="team_other_wrap">
                                <label for="team_other">Team Name</label>
                                <input type="text" id="team_other" name="team_other" value="{{ old('team_other') }}" maxlength="255" placeholder="Enter team name">
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

                            <div id="national_team_other_section" class="col-12 other-wrap">
                                <div class="grid">
                                    <div class="col-6">
                                        <label for="national_team_other">New National Team Name</label>
                                        <input type="text" id="national_team_other" name="national_team_other" value="{{ old('national_team_other') }}" placeholder="Enter new national team name" maxlength="255">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-panel" data-step="3">
                    <div class="section">
                        <h2>Media, Links & Bio</h2>
                        <p class="section-copy">Social links, YouTube content, highlights, bio, and accolades.</p>

                        <div class="grid">
                            <div class="col-4">
                                <label for="ig_handle">
                                    <span class="field-label-inline">
                                        <span>Instagram Profile URL</span>
                                        <span class="tooltip-wrap" tabindex="0">
                                            <span class="info-icon">i</span>
                                            <span class="tooltip-box">
                                                Please paste your Instagram profile link (URL), not just your @handle.<br><br>
                                                <strong>Example:</strong><br>
                                                https://www.instagram.com/plyrcard/
                                            </span>
                                        </span>
                                    </span>
                                </label>
                                <input type="url" id="ig_handle" name="ig_handle" value="{{ old('ig_handle') }}" maxlength="255" placeholder="https://www.instagram.com/yourprofile/">
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
                                                https://x.com/plyrcard
                                            </span>
                                        </span>
                                    </span>
                                </label>
                                <input type="url" id="x_handle" name="x_handle" value="{{ old('x_handle') }}" maxlength="255" placeholder="https://x.com/yourprofile">
                            </div>

                            <div class="col-12">
                                <label for="yt_url">
                                    <span class="field-label-inline">
                                        <span>YouTube Channel URL</span>
                                        <span class="tooltip-wrap" tabindex="0">
                                            <span class="info-icon">i</span>
                                            <span class="tooltip-box">
                                                Paste your full YouTube channel URL, not just the name.
                                            </span>
                                        </span>
                                    </span>
                                </label>
                                <input type="url" id="yt_url" name="yt_url" value="{{ old('yt_url') }}" maxlength="500" placeholder="https://www.youtube.com/@YourChannelName">
                                <div class="hint">Optional. If manual highlight selection is off, this can be used to pull videos.</div>
                            </div>

                            <div class="col-6">
                                <label for="featured_video_url">Featured Video URL</label>
                                <input type="url" id="featured_video_url" name="featured_video_url" value="{{ old('featured_video_url') }}" maxlength="500" placeholder="https://www.youtube.com/watch?v=...">
                                <div class="hint">Optional. This is the main featured video for the website.</div>
                            </div>

                            <div class="col-6">
                                <label>Highlight Videos</label>
                                <div class="toggle-card">
                                    <div class="toggle-copy">
                                        <p class="toggle-title">Pick My Own Videos</p>
                                        <p class="toggle-description">
                                            Turn this on to manually add highlight video URLs. Leave it off to use the YouTube channel URL above.
                                        </p>
                                    </div>

                                    <label class="switch" for="use_custom_highlights">
                                        <input type="checkbox" id="use_custom_highlights" name="use_custom_highlights" value="1" {{ old('use_custom_highlights') ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div id="custom_highlights_wrap" class="col-12 hidden-section">
                                <label for="featured_video_urls">Highlight Video URLs</label>
                                <textarea id="featured_video_urls" name="featured_video_urls" placeholder="Enter one video URL per line&#10;https://www.youtube.com/watch?v=abc123&#10;https://www.youtube.com/watch?v=def456">{{ old('featured_video_urls') }}</textarea>
                                <div class="hint">One full video URL per line.</div>
                            </div>

                            <div class="col-12">
                                <label for="player_bio">Player Bio</label>
                                <textarea id="player_bio" name="player_bio" placeholder="Write a short player bio for the website.">{{ old('player_bio') }}</textarea>
                                <div class="hint">This will be used in the website bio/about section.</div>
                            </div>

                            <div class="col-6">
                                <label for="academic_accolades">Academic Accolades</label>
                                <textarea id="academic_accolades" name="academic_accolades" placeholder="Enter one accolade per line&#10;Honor Roll&#10;National Honor Society&#10;AP Scholar">{{ old('academic_accolades') }}</textarea>
                                <div class="hint">Enter one accolade per line.</div>
                            </div>

                            <div class="col-6">
                                <label for="sports_accolades">Sports Accolades</label>
                                <textarea id="sports_accolades" name="sports_accolades" placeholder="Enter one accolade per line&#10;All League First Team&#10;MVP&#10;Team Captain">{{ old('sports_accolades') }}</textarea>
                                <div class="hint">Enter one accolade per line.</div>
                            </div>

                            <div class="col-12">
                                <label for="press">Press / Notes</label>
                                <textarea id="press" name="press">{{ old('press') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-panel" data-step="4">
                    <div class="section">
                        <h2>Parent / Guardian Information</h2>
                        <p class="section-copy">Primary and secondary parent or guardian contact details.</p>

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
                        <p class="section-copy">Add coaches and trainers connected to the athlete.</p>

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
                </div>

                <div class="step-panel" data-step="5">
                    <div class="section">
                        <h2>Images</h2>
                        <p class="section-copy">Upload action, portrait, team, and national team images.</p>

                        <div class="grid">
                            <div class="col-6">
                                <label for="action_images">Action Images</label>
                                <input type="file" id="action_images" name="action_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <div class="hint">Upload action shots of the athlete.</div>
                            </div>

                            <div class="col-6">
                                <label for="portrait_images">Portrait Images</label>
                                <input type="file" id="portrait_images" name="portrait_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <div class="hint" id="portrait_images_hint">Upload portrait or solo player images.</div>
                            </div>

                            <div class="col-6 other-wrap" id="national_team_images_wrap">
                                <label for="national_team_images">National Team Images</label>
                                <input type="file" id="national_team_images" name="national_team_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <div class="hint">Upload images related to national team play.</div>
                            </div>

                            <div class="col-6">
                                <label for="team_images">Team Images</label>
                                <input type="file" id="team_images" name="team_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <div class="hint">Upload team-related images.</div>
                            </div>

                            <div class="col-12">
                                <div class="hint">You can upload a combined maximum of 20 images across all four image groups. Max 5MB per image.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-secondary" id="prevStepBtn">Back</button>

                    <div class="actions-right">
                        <button type="button" class="btn" id="nextStepBtn">Next</button>
                        <div class="submit-wrap" id="submitWrap">
                            <button type="submit" class="btn">Submit Intake Form</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const sportPositions = @json($sportPositions);
    const oldPositions = @json(old('position', []));
    const stepFieldMap = @json($stepFieldMap ?? []);
    const errorFields = @json(array_keys($errors->toArray()));
    const enabledSports = ['basketball', 'soccer'];

    const leagueDirectory = @json($leagueDirectory);
    const clubDirectory = @json($clubDirectory);
    const teamDirectory = @json($teamDirectory);

    const oldLeagueId = @json(old('league_id'));
    const oldClubId = @json(old('club_id'));
    const oldTeamId = @json(old('team_id'));
    const oldGender = @json(old('gender'));

    const REGION_TEST_OVERRIDE = null;
    const DETECTED_COUNTRY_FROM_SERVER = @json($detectedCountry ?? '');

    let currentStep = 1;
    const totalSteps = 5;

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
        return mapCountryToLanguage(countryCode);
    }

    const phraseTranslations = {
        es: {
            "Athlete Details": "Detalles del atleta",
            "School & Team": "Escuela y equipo",
            "Media & Bio": "Medios y biografía",
            "Contacts": "Contactos",
            "Images": "Imágenes",
            "Next": "Siguiente",
            "Back": "Atrás",
            "Submit Intake Form": "Enviar formulario de ingreso"
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

        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null);
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

    function isLeagueGenderCompatible(leagueGender, userGender) {
        const lg = (leagueGender || '').toLowerCase().trim();
        const ug = (userGender || '').toLowerCase().trim();

        if (!lg || !ug) {
            return true;
        }

        if (ug === 'coed') {
            return lg === 'coed';
        }

        return lg === ug || lg === 'coed';
    }

    function resetSelect(select, placeholder) {
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    }

    function populateLeagueOptions(preserveValue = null) {
        const genderSelect = document.getElementById('gender');
        const leagueSelect = document.getElementById('league_id');
        const selectedGender = genderSelect.value || '';

        resetSelect(leagueSelect, 'Select league');

        leagueDirectory
            .filter((league) => isLeagueGenderCompatible(league.gender, selectedGender))
            .forEach((league) => {
                const option = document.createElement('option');
                option.value = String(league.id);
                option.textContent = league.name;
                leagueSelect.appendChild(option);
            });

        const otherOption = document.createElement('option');
        otherOption.value = '__other__';
        otherOption.textContent = 'Other';
        leagueSelect.appendChild(otherOption);

        if (preserveValue) {
            const exists = Array.from(leagueSelect.options).some((option) => option.value === String(preserveValue));
            leagueSelect.value = exists ? String(preserveValue) : '';
        }
    }

    function populateClubOptions(preserveValue = null) {
        const leagueSelect = document.getElementById('league_id');
        const clubSelect = document.getElementById('club_id');
        const selectedLeague = leagueSelect.value;

        resetSelect(clubSelect, 'Select club');

        if (!selectedLeague || selectedLeague === '__other__') {
            clubSelect.disabled = true;
            clubSelect.value = '';
            return;
        }

        clubDirectory
            .filter((club) => String(club.league_id) === String(selectedLeague))
            .forEach((club) => {
                const option = document.createElement('option');
                option.value = String(club.id);
                option.textContent = club.name;
                clubSelect.appendChild(option);
            });

        clubSelect.disabled = false;

        if (preserveValue) {
            const exists = Array.from(clubSelect.options).some((option) => option.value === String(preserveValue));
            clubSelect.value = exists ? String(preserveValue) : '';
        }
    }

    function populateTeamOptions(preserveValue = null) {
        const clubSelect = document.getElementById('club_id');
        const teamSelect = document.getElementById('team_id');
        const selectedClub = clubSelect.value;

        resetSelect(teamSelect, 'Select team');

        if (!selectedClub) {
            teamSelect.disabled = true;
            teamSelect.value = '';
            return;
        }

        teamDirectory
            .filter((team) => String(team.club_id) === String(selectedClub))
            .forEach((team) => {
                const option = document.createElement('option');
                option.value = String(team.id);
                option.textContent = team.name;
                teamSelect.appendChild(option);
            });

        teamSelect.disabled = false;

        if (preserveValue) {
            const exists = Array.from(teamSelect.options).some((option) => option.value === String(preserveValue));
            teamSelect.value = exists ? String(preserveValue) : '';
        }
    }

    function toggleOrganizationMode() {
        const leagueSelect = document.getElementById('league_id');
        const clubSelect = document.getElementById('club_id');
        const teamSelect = document.getElementById('team_id');

        const leagueOtherWrap = document.getElementById('league_other_wrap');
        const clubOtherWrap = document.getElementById('club_other_wrap');
        const teamOtherWrap = document.getElementById('team_other_wrap');

        const isOther = leagueSelect.value === '__other__';

        if (isOther) {
            clubSelect.disabled = true;
            teamSelect.disabled = true;
            clubSelect.value = '';
            teamSelect.value = '';
            leagueOtherWrap.style.display = 'block';
            clubOtherWrap.style.display = 'block';
            teamOtherWrap.style.display = 'block';
            return;
        }

        leagueOtherWrap.style.display = 'none';
        clubOtherWrap.style.display = 'none';
        teamOtherWrap.style.display = 'none';
    }

    function syncOrganizationFlow({ preserveLeague = null, preserveClub = null, preserveTeam = null } = {}) {
        populateLeagueOptions(preserveLeague);
        toggleOrganizationMode();

        const leagueSelect = document.getElementById('league_id');

        if (leagueSelect.value === '__other__') {
            resetSelect(document.getElementById('club_id'), 'Select club');
            resetSelect(document.getElementById('team_id'), 'Select team');
            document.getElementById('club_id').disabled = true;
            document.getElementById('team_id').disabled = true;
            return;
        }

        populateClubOptions(preserveClub);
        populateTeamOptions(preserveTeam);
    }

    function toggleNationalTeamOther() {
        const natlTeamExp = document.getElementById('natl_team_exp');
        const nationalTeamFieldWrap = document.getElementById('national_team_field_wrap');
        const nationalTeamSelect = document.getElementById('national_team_id');
        const nationalTeamOtherSection = document.getElementById('national_team_other_section');
        const nationalTeamImagesWrap = document.getElementById('national_team_images_wrap');
        const nationalTeamImagesInput = document.getElementById('national_team_images');
        const nationalTeamPeriodWrap = document.getElementById('national_team_period_wrap');
        const nationalTeamPeriodInput = document.getElementById('national_team_period');

        if (!natlTeamExp || !nationalTeamFieldWrap || !nationalTeamSelect || !nationalTeamOtherSection) return;

        const hasExperience = natlTeamExp.value === '1';

        nationalTeamFieldWrap.style.display = hasExperience ? 'block' : 'none';
        nationalTeamPeriodWrap.style.display = hasExperience ? 'block' : 'none';
        nationalTeamImagesWrap.style.display = hasExperience ? 'block' : 'none';

        if (!hasExperience) {
            nationalTeamSelect.value = '';
            nationalTeamOtherSection.style.display = 'none';

            const otherInput = document.getElementById('national_team_other');
            if (otherInput) otherInput.value = '';
            if (nationalTeamPeriodInput) nationalTeamPeriodInput.value = '';
            if (nationalTeamImagesInput) nationalTeamImagesInput.value = '';
            return;
        }

        nationalTeamOtherSection.style.display = nationalTeamSelect.value === '__other__' ? 'block' : 'none';
    }

    function updateImageInstructions() {
        const sport = document.getElementById('sport').value;
        const portraitHint = document.getElementById('portrait_images_hint');

        if (!portraitHint) return;

        if (sport === 'soccer') {
            portraitHint.textContent = 'Upload portrait or solo soccer player images. These will still be stored under raw player images.';
            return;
        }

        if (sport === 'basketball') {
            portraitHint.textContent = 'Upload portrait or solo basketball player images. These will still be stored under raw player images.';
            return;
        }

        portraitHint.textContent = 'Upload portrait or solo player images. These will still be stored under raw player images.';
    }

    function toggleDominantFoot() {
        const sportSelect = document.getElementById('sport');
        const dominantFootWrap = document.getElementById('dominant_foot_wrap');
        const dominantFootSelect = document.getElementById('dominant_foot');

        if (!sportSelect || !dominantFootWrap || !dominantFootSelect) return;

        const isSoccer = sportSelect.value === 'soccer';
        dominantFootWrap.style.display = isSoccer ? 'block' : 'none';

        if (!isSoccer) {
            dominantFootSelect.value = '';
        }
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

        countryOtherWrap.style.display = selectedCountry === '__other__' ? 'block' : 'none';
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

        stateHidden.value = (country === 'USA' || country === '') ? (stateUs.value || '') : (stateInternational.value || '');
    }

    function getStepFromErrors() {
        if (!Array.isArray(errorFields) || errorFields.length === 0) {
            return 1;
        }

        for (const [step, fields] of Object.entries(stepFieldMap)) {
            const hasMatch = errorFields.some((errorField) => {
                return fields.includes(errorField) || fields.some((field) => {
                    if (!field.includes('*')) return false;
                    const base = field.replace('.*', '');
                    return errorField.startsWith(base);
                });
            });

            if (hasMatch) {
                return Number(step);
            }
        }

        return 1;
    }

    function showStep(step) {
        currentStep = Math.max(1, Math.min(totalSteps, Number(step)));

        document.querySelectorAll('.step-panel').forEach((panel) => {
            panel.classList.toggle('active', Number(panel.dataset.step) === currentStep);
        });

        document.querySelectorAll('[data-step-pill]').forEach((pill) => {
            const pillStep = Number(pill.dataset.stepPill);
            pill.classList.toggle('active', pillStep === currentStep);
            pill.classList.toggle('done', pillStep < currentStep);
        });

        const prevBtn = document.getElementById('prevStepBtn');
        const nextBtn = document.getElementById('nextStepBtn');
        const submitWrap = document.getElementById('submitWrap');

        prevBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        nextBtn.style.display = currentStep === totalSteps ? 'none' : 'inline-flex';
        submitWrap.classList.toggle('visible', currentStep === totalSteps);

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function goNextStep() {
        if (currentStep < totalSteps) {
            showStep(currentStep + 1);
        }
    }

    function goPrevStep() {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const lang = getTargetLanguage();

        renderPositions();
        toggleSchoolOther();
        toggleNationalTeamOther();
        toggleCustomHighlights();
        toggleCountryFields();
        syncStateValue();
        toggleDominantFoot();
        updateImageInstructions();
        translateTextNodes(lang);

        syncOrganizationFlow({
            preserveLeague: oldLeagueId,
            preserveClub: oldClubId,
            preserveTeam: oldTeamId,
        });

        const initialStep = {{ $errors->any() ? 'getStepFromErrors()' : '1' }};
        showStep(initialStep);

        document.getElementById('sport').addEventListener('change', () => {
            renderPositions();
            toggleDominantFoot();
            updateImageInstructions();
        });

        document.getElementById('gender').addEventListener('change', () => {
            syncOrganizationFlow();
        });

        document.getElementById('league_id').addEventListener('change', () => {
            toggleOrganizationMode();

            if (document.getElementById('league_id').value === '__other__') {
                resetSelect(document.getElementById('club_id'), 'Select club');
                resetSelect(document.getElementById('team_id'), 'Select team');
                document.getElementById('club_id').disabled = true;
                document.getElementById('team_id').disabled = true;
                return;
            }

            populateClubOptions();
            populateTeamOptions();
        });

        document.getElementById('club_id').addEventListener('change', () => {
            populateTeamOptions();
        });

        document.getElementById('school_id').addEventListener('change', toggleSchoolOther);
        document.getElementById('national_team_id').addEventListener('change', toggleNationalTeamOther);
        document.getElementById('natl_team_exp').addEventListener('change', toggleNationalTeamOther);
        document.getElementById('use_custom_highlights').addEventListener('change', toggleCustomHighlights);

        document.getElementById('country').addEventListener('change', () => {
            toggleCountryFields();
            syncStateValue();
        });

        document.getElementById('state_us').addEventListener('change', syncStateValue);
        document.getElementById('state_international').addEventListener('input', syncStateValue);

        document.getElementById('nextStepBtn').addEventListener('click', goNextStep);
        document.getElementById('prevStepBtn').addEventListener('click', goPrevStep);

        document.querySelectorAll('[data-step-pill]').forEach((button) => {
            button.addEventListener('click', () => {
                showStep(Number(button.dataset.stepPill));
            });
        });
    });
</script>
</body>
</html>