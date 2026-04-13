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
            --muted: #a3a3a3;
            --accent: #ff7a00;
            --accent-2: #ff9a1f;
            --border: #2a2a2a;
            --danger: #ef4444;
            --success: #22c55e;
            --shadow: 0 18px 40px rgba(0, 0, 0, 0.38);
        }

        * { box-sizing: border-box; }

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

        body.embed-mode { padding: 14px; }

        .wrapper {
            width: 100%;
            max-width: 1220px;
            margin: 0 auto;
            padding-bottom: 260px;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: 26px;
            overflow: visible;
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

        .hero-title .accent { color: var(--accent); }

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
            overflow: visible;
        }

        .tabs-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: none;
        }

        .tabs-bar::-webkit-scrollbar { display: none; }

        .tab-btn {
            appearance: none;
            border: 1px solid var(--border);
            background: #151515;
            color: #d5d5d5;
            padding: 12px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            transition: .2s ease;
            white-space: nowrap;
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: 9px;
        }

        .tab-btn svg {
            width: 16px;
            height: 16px;
            stroke-width: 2;
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

        .tab-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .step-panel { display: none; }
        .step-panel.active { display: block; }

        .section {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 22px;
            background: linear-gradient(180deg, #151515 0%, #111111 100%);
            overflow: visible;
        }

        .section-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 18px;
        }

        .section-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: rgba(255, 122, 0, 0.10);
            border: 1px solid rgba(255, 122, 0, 0.16);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-2);
            flex: 0 0 auto;
        }

        .section-icon svg {
            width: 20px;
            height: 20px;
            stroke-width: 2;
        }

        .section h2 {
            margin: 0;
            font-size: 22px;
            line-height: 1.1;
            font-weight: 700;
            color: #ffffff;
        }

        .section-copy {
            margin: 6px 0 0;
            font-size: 13px;
            color: var(--muted);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 16px;
            overflow: visible;
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

        .required { color: var(--accent-2); }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="url"],
        select,
        textarea {
            width: 100%;
            min-height: 46px;
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

        input:disabled,
        select:disabled,
        textarea:disabled {
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

        .switch input:checked + .slider { background: var(--accent); }
        .switch input:checked + .slider::before { transform: translateX(24px); }

        .field-error {
            border-color: var(--danger) !important;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.10) !important;
        }

        .hidden-section,
        .other-wrap {
            display: none;
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

        .btn:active { transform: translateY(1px); }

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

        .btn:disabled {
            opacity: .45;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
            background: #3a3a3a;
        }

        .submit-wrap { display: none; }
        .submit-wrap.visible { display: inline-flex; }

        .search-select {
            position: relative;
            z-index: 10;
        }

        .search-select.open {
            z-index: 60;
        }

        .search-select.is-disabled {
            opacity: .55;
            pointer-events: none;
        }

        .search-select-control {
            width: 100%;
            min-height: 46px;
            padding: 11px 14px;
            border-radius: 14px;
            border: 1px solid #2c2c2c;
            background: #141414;
            color: #f9fafb;
            font-size: 14px;
            line-height: 1.45;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            cursor: pointer;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .search-select-control:hover {
            border-color: rgba(255, 122, 0, 0.35);
        }

        .search-select.open .search-select-control {
            border-color: var(--accent);
            background: #1a1a1a;
            box-shadow: 0 0 0 4px rgba(255, 122, 0, 0.12);
        }

        .search-select-placeholder {
            color: #7b7b7b;
        }

        .search-select-value {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .search-select-logo {
            width: 22px;
            height: 22px;
            border-radius: 999px;
            object-fit: cover;
            background: #1e1e1e;
            border: 1px solid rgba(255,255,255,.08);
            flex: 0 0 auto;
        }

        .search-select-meta {
            display: inline-flex;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .search-select-caret,
        .search-select-search-icon {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
            opacity: .8;
        }

        .search-select-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            z-index: 70;
            border-radius: 16px;
            border: 1px solid rgba(255, 122, 0, 0.18);
            background: #101010;
            box-shadow: 0 16px 40px rgba(0,0,0,.35);
            display: none;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .search-select.open .search-select-dropdown {
            display: block;
        }

        .search-select-search {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            background: #121212;
        }

        .search-select-search input {
            min-height: 40px;
            padding: 9px 12px;
        }

        .search-select-list {
            max-height: 320px;
            overflow-y: auto;
            padding-bottom: 10px;
        }

        .search-select-option {
            width: 100%;
            border: 0;
            background: transparent;
            color: #f3f4f6;
            text-align: left;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: background .15s ease;
        }

        .search-select-option:hover,
        .search-select-option.is-active {
            background: rgba(255, 122, 0, 0.08);
        }

        .search-select-option-copy {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .search-select-option-title {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .search-select-option-subtitle {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .search-select-empty {
            padding: 14px;
            font-size: 13px;
            color: var(--muted);
        }

        @media (max-width: 980px) {
            .hero-title { font-size: 52px; }
            .header-copy { font-size: 17px; }

            .col-8, .col-6, .col-4, .col-3 { grid-column: span 12; }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .actions-right {
                margin-left: 0;
                width: 100%;
                flex-direction: column;
            }

            .btn { width: 100%; }
        }

        @media (max-width: 640px) {
            body.embed-mode { padding: 0; }
            .wrapper {
                max-width: 100%;
                padding-bottom: 300px;
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

            .hero-title { font-size: 42px; }
            .header-copy { font-size: 15px; }

            .tabs-bar {
                margin: 0 -2px 18px;
                padding: 0 2px 4px;
            }

            .tab-btn {
                min-height: 42px;
                font-size: 12px;
                padding: 10px 14px;
            }

            .search-select-dropdown {
                max-height: min(60vh, 360px);
            }

            .search-select-list {
                max-height: min(48vh, 260px);
            }
        }
    </style>
</head>
<body class="embed-mode">
<div class="wrapper">
    <div class="card">
        <div class="header">
            <div class="eyebrow">{{ $packageLabel ?? 'PLYRCard Package' }}</div>
            <h1 class="hero-title"><span translate="no">Plyr</span> <span class="accent">Intake</span> Form</h1>
            <p class="header-copy">
                Complete this form for your <span translate="no">{{ $packageLabel ?? 'PLYRCard Package' }}</span> purchase.
                Share your athlete details, school and organization details, media, contacts, and images.
            </p>
            <p class="hint">Sections unlock progressively once the current section is complete.</p>
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
                <button type="button" class="tab-btn" data-step-pill="1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21a8 8 0 1 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Athlete Details</span>
                </button>
                <button type="button" class="tab-btn" data-step-pill="2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 10l9-6 9 6-9 6-3.272-2.182"/><path d="M6 12v5c0 1 3 3 6 3s6-2 6-3v-5"/></svg>
                    <span>School & Organization</span>
                </button>
                <button type="button" class="tab-btn" data-step-pill="3">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m10 9 5 3-5 3V9Z"/></svg>
                    <span>Media & Bio</span>
                </button>
                <button type="button" class="tab-btn" data-step-pill="4">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 4h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-2"/><path d="M8 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h2"/><path d="M9 7h6"/><path d="M9 12h6"/><path d="M9 17h4"/></svg>
                    <span>Contacts</span>
                </button>
                <button type="button" class="tab-btn" data-step-pill="5">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                    <span>Images</span>
                </button>
            </div>

            <form method="POST" action="{{ route('public.player-intake.store') }}" enctype="multipart/form-data" id="playerIntakeForm">
                @csrf

                <div class="step-panel" data-step="1">
                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21a8 8 0 1 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div>
                                <h2>Athlete Details</h2>
                                <p class="section-copy">Player identity, sport profile, athletic metrics, address, and national team experience.</p>
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-4">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" maxlength="255" required placeholder="Enter first name">
                            </div>

                            <div class="col-4">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name') }}" maxlength="255" placeholder="Enter middle name">
                            </div>

                            <div class="col-4">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" maxlength="255" required placeholder="Enter last name">
                            </div>

                            <div class="col-4">
                                <label for="personal_email">Personal Email <span class="required">*</span></label>
                                <input type="email" id="personal_email" name="personal_email" value="{{ old('personal_email') }}" maxlength="255" required placeholder="name@example.com">
                                <div class="hint">Only the personal email should be used here.</div>
                            </div>

                            <div class="col-4">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" value="{{ old('phone') }}" maxlength="50" inputmode="tel" placeholder="+1 (555) 123-4567">
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
                                <div class="hint">Used to filter leagues, clubs, and teams dynamically.</div>
                            </div>

                            <div class="col-4">
                                <label for="sport">Sport <span class="required">*</span></label>
                                <select id="sport" name="sport" required>
                                    <option value="">Select sport</option>
                                    @foreach ($sportPositions as $sportKey => $positions)
                                        <option value="{{ $sportKey }}" {{ old('sport') === $sportKey ? 'selected' : '' }}>
                                            {{ str($sportKey)->replace('_', ' ')->title() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-8">
                                <label>Position <span class="required">*</span></label>
                                <div id="positionOptions" class="checkbox-group"></div>
                                <div class="hint">Positions update automatically based on selected sport.</div>
                            </div>

                            <div class="col-4" id="dominant_foot_wrap" style="display:none;">
                                <label for="dominant_foot">Dominant Foot</label>
                                <select id="dominant_foot" name="dominant_foot">
                                    <option value="">Select dominant foot</option>
                                    <option value="left" {{ old('dominant_foot') === 'left' ? 'selected' : '' }}>Left</option>
                                    <option value="right" {{ old('dominant_foot') === 'right' ? 'selected' : '' }}>Right</option>
                                    <option value="both" {{ old('dominant_foot') === 'both' ? 'selected' : '' }}>Both</option>
                                </select>
                                <div class="hint">Optional. Only shown for soccer.</div>
                            </div>

                            <div class="col-4">
                                <label for="birth">Birth Date</label>
                                <input type="date" id="birth" name="birth" value="{{ old('birth') }}">
                            </div>

                            <div class="col-4">
                                <label for="year">Graduation Year</label>
                                <input type="text" id="year" name="year" value="{{ old('year') }}" maxlength="50" inputmode="numeric" placeholder="2027">
                            </div>

                            <div class="col-4">
                                <label for="gpa">GPA</label>
                                <input type="text" id="gpa" name="gpa" value="{{ old('gpa') }}" maxlength="50" placeholder="3.8">
                            </div>

                            <div class="col-3">
                                <label for="height">Height</label>
                                <input type="text" id="height" name="height" value="{{ old('height') }}" maxlength="50" placeholder="6'2&quot;">
                            </div>

                            <div class="col-3">
                                <label for="weight">Weight</label>
                                <input type="text" id="weight" name="weight" value="{{ old('weight') }}" maxlength="50" placeholder="185 lbs">
                            </div>

                            <div class="col-3">
                                <label for="jersey_number">Jersey Number</label>
                                <input type="text" id="jersey_number" name="jersey_number" value="{{ old('jersey_number') }}" maxlength="50" placeholder="12">
                            </div>

                            <div class="col-3">
                                <label for="vertical_jump">Vertical Jump</label>
                                <input type="text" id="vertical_jump" name="vertical_jump" value="{{ old('vertical_jump') }}" maxlength="50" placeholder="32 in">
                            </div>

                            <div class="col-3">
                                <label for="max_speed">Max Speed</label>
                                <input type="text" id="max_speed" name="max_speed" value="{{ old('max_speed') }}" maxlength="50" placeholder="21 mph">
                            </div>

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
                            </div>

                            <div id="state_international_wrap" class="col-3 other-wrap">
                                <label for="state_international">State / Province / Region</label>
                                <input type="text" id="state_international" value="{{ old('state') }}" maxlength="255" placeholder="Enter state, province, or region">
                            </div>

                            <input type="hidden" id="state_hidden" name="state" value="{{ old('state') }}">

                            <div class="col-3">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" value="{{ old('city') }}" maxlength="255" placeholder="Enter city">
                            </div>

                            <div class="col-3">
                                <label for="street">Street</label>
                                <input type="text" id="street" name="street" value="{{ old('street') }}" maxlength="255" placeholder="Enter street address">
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
                                <input type="text" id="national_team_period" name="national_team_period" value="{{ old('national_team_period') }}" maxlength="255" placeholder="Example: 2022-2024">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-panel" data-step="2">
                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 10l9-6 9 6-9 6-3.272-2.182"/><path d="M6 12v5c0 1 3 3 6 3s6-2 6-3v-5"/></svg>
                            </div>
                            <div>
                                <h2>School & Organization</h2>
                                <p class="section-copy">School, league, club, team, and national team details. League, club, and team are filtered by gender and sport.</p>
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-4">
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
                                <div class="hint">Choose Other if the school is not listed.</div>
                            </div>

                            <div class="col-4 other-wrap" id="school_other_wrap">
                                <label for="school_other">School Name</label>
                                <input type="text" id="school_other" name="school_other" value="{{ old('school_other') }}" maxlength="255" placeholder="Enter school name">
                            </div>

                            <div class="col-4">
                                <label>League</label>
                                <div id="leagueSelectRoot"></div>
                                <input type="hidden" id="league_id" name="league_id" value="{{ old('league_id') }}">
                                <div class="hint">Filtered by selected gender and sport.</div>
                            </div>

                            <div class="col-4">
                                <label>Club</label>
                                <div id="clubSelectRoot"></div>
                                <input type="hidden" id="club_id" name="club_id" value="{{ old('club_id') }}">
                                <div class="hint">Filtered by selected league, gender, and sport. Logos are shown where available.</div>
                            </div>

                            <div class="col-4">
                                <label>Team</label>
                                <div id="teamSelectRoot"></div>
                                <input type="hidden" id="team_id" name="team_id" value="{{ old('team_id') }}">
                                <div class="hint">Filtered by selected club, gender, and sport.</div>
                            </div>

                            <div class="col-4 other-wrap" id="league_other_wrap">
                                <label for="league_other">League Name</label>
                                <input type="text" id="league_other" name="league_other" value="{{ old('league_other') }}" maxlength="255" placeholder="Enter league name">
                            </div>

                            <div class="col-4 other-wrap" id="club_other_wrap">
                                <label for="club_other">Club Name</label>
                                <input type="text" id="club_other" name="club_other" value="{{ old('club_other') }}" maxlength="255" placeholder="Enter club name">
                            </div>

                            <div class="col-4 other-wrap" id="team_other_wrap">
                                <label for="team_other">Team Name</label>
                                <input type="text" id="team_other" name="team_other" value="{{ old('team_other') }}" maxlength="255" placeholder="Enter team name">
                            </div>

                            <div class="col-4" id="national_team_field_wrap">
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
                            </div>

                            <div id="national_team_other_section" class="col-4 other-wrap">
                                <label for="national_team_other">New National Team Name</label>
                                <input type="text" id="national_team_other" name="national_team_other" value="{{ old('national_team_other') }}" placeholder="Enter new national team name" maxlength="255">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-panel" data-step="3">
                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m10 9 5 3-5 3V9Z"/></svg>
                            </div>
                            <div>
                                <h2>Media, Links & Bio</h2>
                                <p class="section-copy">Social links, YouTube content, highlights, bio, accolades, and press notes.</p>
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-4">
                                <label for="ig_handle">Instagram Profile URL</label>
                                <input type="url" id="ig_handle" name="ig_handle" value="{{ old('ig_handle') }}" maxlength="255" placeholder="https://www.instagram.com/yourprofile/">
                            </div>

                            <div class="col-4">
                                <label for="x_handle">X Profile URL</label>
                                <input type="url" id="x_handle" name="x_handle" value="{{ old('x_handle') }}" maxlength="255" placeholder="https://x.com/yourprofile">
                            </div>

                            <div class="col-4">
                                <label for="yt_url">YouTube Channel URL</label>
                                <input type="url" id="yt_url" name="yt_url" value="{{ old('yt_url') }}" maxlength="500" placeholder="https://www.youtube.com/@YourChannelName">
                            </div>

                            <div class="col-6">
                                <label for="featured_video_url">Featured Video URL</label>
                                <input type="url" id="featured_video_url" name="featured_video_url" value="{{ old('featured_video_url') }}" maxlength="500" placeholder="https://www.youtube.com/watch?v=...">
                            </div>

                            <div class="col-6">
                                <label>Highlight Videos</label>
                                <div class="toggle-card">
                                    <div class="toggle-copy">
                                        <p class="toggle-title">Pick My Own Videos</p>
                                        <p class="toggle-description">Turn this on to manually add highlight video URLs.</p>
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
                            </div>

                            <div class="col-12">
                                <label for="player_bio">Player Bio</label>
                                <textarea id="player_bio" name="player_bio" placeholder="Write a short player bio for the website.">{{ old('player_bio') }}</textarea>
                            </div>

                            <div class="col-6">
                                <label for="academic_accolades">Academic Accolades</label>
                                <textarea id="academic_accolades" name="academic_accolades" placeholder="Enter one accolade per line">{{ old('academic_accolades') }}</textarea>
                            </div>

                            <div class="col-6">
                                <label for="sports_accolades">Sports Accolades</label>
                                <textarea id="sports_accolades" name="sports_accolades" placeholder="Enter one accolade per line">{{ old('sports_accolades') }}</textarea>
                            </div>

                            <div class="col-12">
                                <label for="press">Press / Notes</label>
                                <textarea id="press" name="press" placeholder="Add articles, press mentions, or important notes.">{{ old('press') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-panel" data-step="4">
                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 4h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-2"/><path d="M8 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h2"/><path d="M9 7h6"/><path d="M9 12h6"/><path d="M9 17h4"/></svg>
                            </div>
                            <div>
                                <h2>Parent / Guardian Information</h2>
                                <p class="section-copy">Primary and secondary parent or guardian details.</p>
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-4">
                                <label for="parent">Primary Parent / Guardian</label>
                                <input type="text" id="parent" name="parent" value="{{ old('parent') }}" maxlength="255" placeholder="Enter full name">
                            </div>

                            <div class="col-4">
                                <label for="parent_email">Primary Parent Email</label>
                                <input type="email" id="parent_email" name="parent_email" value="{{ old('parent_email') }}" maxlength="255" placeholder="parent@example.com">
                            </div>

                            <div class="col-4">
                                <label for="parent_phone">Primary Parent Phone</label>
                                <input type="text" id="parent_phone" name="parent_phone" value="{{ old('parent_phone') }}" maxlength="50" inputmode="tel" placeholder="+1 (555) 123-4567">
                            </div>

                            <div class="col-4">
                                <label for="sec_parent">Secondary Parent / Guardian</label>
                                <input type="text" id="sec_parent" name="sec_parent" value="{{ old('sec_parent') }}" maxlength="255" placeholder="Enter full name">
                            </div>

                            <div class="col-4">
                                <label for="sec_parent_email">Secondary Parent Email</label>
                                <input type="email" id="sec_parent_email" name="sec_parent_email" value="{{ old('sec_parent_email') }}" maxlength="255" placeholder="parent2@example.com">
                            </div>

                            <div class="col-4">
                                <label for="sec_parent_phone">Secondary Parent Phone</label>
                                <input type="text" id="sec_parent_phone" name="sec_parent_phone" value="{{ old('sec_parent_phone') }}" maxlength="50" inputmode="tel" placeholder="+1 (555) 123-4567">
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 12a4 4 0 1 0-4-4"/><path d="M20 21a8 8 0 0 0-16 0"/><path d="M17 11h4"/><path d="M19 9v4"/></svg>
                            </div>
                            <div>
                                <h2>Coaches & Trainers</h2>
                                <p class="section-copy">Add coaches and trainers connected to the athlete.</p>
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-4">
                                <label for="club_coach">Club Coach</label>
                                <input type="text" id="club_coach" name="club_coach" value="{{ old('club_coach') }}" maxlength="255" placeholder="Enter club coach name">
                            </div>

                            <div class="col-4">
                                <label for="club_coach_email">Club Coach Email</label>
                                <input type="email" id="club_coach_email" name="club_coach_email" value="{{ old('club_coach_email') }}" maxlength="255" placeholder="coach@example.com">
                            </div>

                            <div class="col-4">
                                <label for="club_coach_phone">Club Coach Phone</label>
                                <input type="text" id="club_coach_phone" name="club_coach_phone" value="{{ old('club_coach_phone') }}" maxlength="50" inputmode="tel" placeholder="+1 (555) 123-4567">
                            </div>

                            <div class="col-4">
                                <label for="natl_coach">National Coach</label>
                                <input type="text" id="natl_coach" name="natl_coach" value="{{ old('natl_coach') }}" maxlength="255" placeholder="Enter national coach name">
                            </div>

                            <div class="col-4">
                                <label for="natl_coach_email">National Coach Email</label>
                                <input type="email" id="natl_coach_email" name="natl_coach_email" value="{{ old('natl_coach_email') }}" maxlength="255" placeholder="coach@example.com">
                            </div>

                            <div class="col-4">
                                <label for="natl_coach_phone">National Coach Phone</label>
                                <input type="text" id="natl_coach_phone" name="natl_coach_phone" value="{{ old('natl_coach_phone') }}" maxlength="50" inputmode="tel" placeholder="+1 (555) 123-4567">
                            </div>

                            <div class="col-4">
                                <label for="tech_trainer">Technical Trainer</label>
                                <input type="text" id="tech_trainer" name="tech_trainer" value="{{ old('tech_trainer') }}" maxlength="255" placeholder="Enter technical trainer name">
                            </div>

                            <div class="col-4">
                                <label for="tech_trainer_email">Technical Trainer Email</label>
                                <input type="email" id="tech_trainer_email" name="tech_trainer_email" value="{{ old('tech_trainer_email') }}" maxlength="255" placeholder="trainer@example.com">
                            </div>

                            <div class="col-4">
                                <label for="tech_trainer_phone">Technical Trainer Phone</label>
                                <input type="text" id="tech_trainer_phone" name="tech_trainer_phone" value="{{ old('tech_trainer_phone') }}" maxlength="50" inputmode="tel" placeholder="+1 (555) 123-4567">
                            </div>

                            <div class="col-4">
                                <label for="snc_trainer">Strength & Conditioning Trainer</label>
                                <input type="text" id="snc_trainer" name="snc_trainer" value="{{ old('snc_trainer') }}" maxlength="255" placeholder="Enter S&C trainer name">
                            </div>

                            <div class="col-4">
                                <label for="snc_trainer_email">S&amp;C Trainer Email</label>
                                <input type="email" id="snc_trainer_email" name="snc_trainer_email" value="{{ old('snc_trainer_email') }}" maxlength="255" placeholder="trainer@example.com">
                            </div>

                            <div class="col-4">
                                <label for="snc_trainer_phone">S&amp;C Trainer Phone</label>
                                <input type="text" id="snc_trainer_phone" name="snc_trainer_phone" value="{{ old('snc_trainer_phone') }}" maxlength="50" inputmode="tel" placeholder="+1 (555) 123-4567">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-panel" data-step="5">
                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            </div>
                            <div>
                                <h2>Images</h2>
                                <p class="section-copy">Upload action, portrait, team, and national team images.</p>
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-6">
                                <label for="action_images">Action Images</label>
                                <input type="file" id="action_images" name="action_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                            </div>

                            <div class="col-6">
                                <label for="portrait_images">Portrait Images</label>
                                <input type="file" id="portrait_images" name="portrait_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                            </div>

                            <div class="col-6 other-wrap" id="national_team_images_wrap">
                                <label for="national_team_images">National Team Images</label>
                                <input type="file" id="national_team_images" name="national_team_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                            </div>

                            <div class="col-6">
                                <label for="team_images">Team Images</label>
                                <input type="file" id="team_images" name="team_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                            </div>

                            <div class="col-12">
                                <div class="hint">Combined maximum: 20 images total across all image fields. Max 5MB each.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-secondary" id="prevStepBtn">Back</button>

                    <div class="actions-right">
                        <button type="button" class="btn" id="nextStepBtn">Next</button>
                        <div class="submit-wrap" id="submitWrap">
                            <button type="submit" class="btn" id="submitBtn">Submit Intake Form</button>
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

    const leagueDirectory = @json($leagueDirectory ?? []);
    const clubDirectory = @json($clubDirectory ?? []);
    const teamDirectory = @json($teamDirectory ?? []);

    const oldLeagueId = @json(old('league_id'));
    const oldClubId = @json(old('club_id'));
    const oldTeamId = @json(old('team_id'));

    let currentStep = 1;
    const totalSteps = 5;

    function iconSearch() {
        return `
            <svg class="search-select-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-3.5-3.5"></path>
            </svg>
        `;
    }

    function iconCaret() {
        return `
            <svg class="search-select-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="m6 9 6 6 6-6"></path>
            </svg>
        `;
    }

    function safeString(value) {
        return String(value ?? '').trim();
    }

    function titleize(value) {
        return safeString(value)
            .replace(/_/g, ' ')
            .replace(/\b\w/g, c => c.toUpperCase());
    }

    function isLeagueGenderCompatible(leagueGender, userGender) {
        const lg = safeString(leagueGender).toLowerCase();
        const ug = safeString(userGender).toLowerCase();

        if (!lg || !ug) return true;
        if (ug === 'coed') return lg === 'coed';
        return lg === ug || lg === 'coed';
    }

    function isLeagueSportCompatible(leagueSport, userSport) {
        const ls = safeString(leagueSport).toLowerCase();
        const us = safeString(userSport).toLowerCase();
        if (!ls || !us) return true;
        return ls === us;
    }

    function renderPositions() {
    const sportSelect = document.getElementById('sport');
    const container = document.getElementById('positionOptions');
    const selectedSport = sportSelect.value;

    const currentChecked = Array.from(document.querySelectorAll('input[name="position[]"]:checked'))
        .map(input => input.value);

    container.innerHTML = '';

    if (!selectedSport || !sportPositions[selectedSport]) return;

    Object.entries(sportPositions[selectedSport]).forEach(([key, label]) => {
        const wrapper = document.createElement('label');
        wrapper.className = 'check-pill';

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = 'position[]';
        input.value = key;

        if (currentChecked.includes(key) || (Array.isArray(oldPositions) && oldPositions.includes(key))) {
            input.checked = true;
        }

        const span = document.createElement('span');
        span.textContent = label;

        wrapper.appendChild(input);
        wrapper.appendChild(span);
        container.appendChild(wrapper);
    });
}

    function toggleDominantFoot() {
        const sportSelect = document.getElementById('sport');
        const wrap = document.getElementById('dominant_foot_wrap');
        const input = document.getElementById('dominant_foot');

        const isSoccer = sportSelect.value === 'soccer';
        wrap.style.display = isSoccer ? 'block' : 'none';

        if (!isSoccer) input.value = '';
    }

    function toggleSchoolOther() {
        const select = document.getElementById('school_id');
        const wrap = document.getElementById('school_other_wrap');
        wrap.style.display = select.value === '__other__' ? 'block' : 'none';
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

    function toggleCustomHighlights() {
        const toggle = document.getElementById('use_custom_highlights');
        const wrap = document.getElementById('custom_highlights_wrap');
        wrap.classList.toggle('hidden-section', !toggle.checked);
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

        const selectedCountry = countrySelect.value;

        if (selectedCountry === 'USA' || selectedCountry === '') {
            countryOtherWrap.style.display = 'none';
            stateUsWrap.style.display = 'block';
            stateInternationalWrap.style.display = 'none';
            stateHidden.value = stateUs.value || '';
            return;
        }

        countryOtherWrap.style.display = selectedCountry === '__other__' ? 'block' : 'block';
        stateUsWrap.style.display = 'none';
        stateInternationalWrap.style.display = 'block';
        stateHidden.value = stateInternational.value || '';
    }

    function syncStateValue() {
        const country = document.getElementById('country').value;
        const stateUs = document.getElementById('state_us');
        const stateInternational = document.getElementById('state_international');
        const stateHidden = document.getElementById('state_hidden');
        stateHidden.value = (country === 'USA' || country === '') ? (stateUs.value || '') : (stateInternational.value || '');
    }

    function validateEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function validateUrl(value) {
        try {
            new URL(value);
            return true;
        } catch (_) {
            return false;
        }
    }

    function isVisible(el) {
        return !!(el && el.offsetParent !== null);
    }

    function getStepFromErrors() {
        if (!Array.isArray(errorFields) || errorFields.length === 0) return 1;

        for (const [step, fields] of Object.entries(stepFieldMap)) {
            const hasMatch = errorFields.some((errorField) => {
                return fields.includes(errorField) || fields.some((field) => {
                    if (!field.includes('*')) return false;
                    const base = field.replace('.*', '');
                    return errorField.startsWith(base);
                });
            });

            if (hasMatch) return Number(step);
        }

        return 1;
    }

    function getStepValidationErrors(step) {
    const errors = [];

    if (step === 1) {
        const firstName = document.getElementById('first_name');
        const lastName = document.getElementById('last_name');
        const personalEmail = document.getElementById('personal_email');
        const gender = document.getElementById('gender');
        const sport = document.getElementById('sport');

        if (!firstName.value.trim()) errors.push('First Name is required.');
        if (!lastName.value.trim()) errors.push('Last Name is required.');

        if (!personalEmail.value.trim()) {
            errors.push('Personal Email is required.');
        } else if (!validateEmail(personalEmail.value.trim())) {
            errors.push('Personal Email must be valid.');
        }

        if (!gender.value.trim()) errors.push('Gender is required.');
        if (!sport.value.trim()) errors.push('Sport is required.');

        const checkedPositions = document.querySelectorAll('input[name="position[]"]:checked');
        if (!checkedPositions.length) errors.push('Select at least one position.');

        const country = document.getElementById('country');
        const countryOther = document.getElementById('country_other');
        if (country && country.value === '__other__' && countryOther && !countryOther.value.trim()) {
            errors.push('Country Name is required.');
        }

        const natlTeamExp = document.getElementById('natl_team_exp');
        const natlTeamPeriod = document.getElementById('national_team_period');
        if (natlTeamExp && natlTeamExp.value === '1' && natlTeamPeriod && !natlTeamPeriod.value.trim()) {
            errors.push('National Team Period is required.');
        }

        // dominant_foot intentionally NOT required
        // max_speed intentionally NOT required
        // jersey_number intentionally NOT required
        // vertical_jump intentionally NOT required
        // height / weight / gpa intentionally NOT required
    }

    if (step === 2) {
        const school = document.getElementById('school_id');
        const schoolOther = document.getElementById('school_other');
        const leagueId = document.getElementById('league_id').value;
        const leagueOther = document.getElementById('league_other');
        const clubOther = document.getElementById('club_other');
        const teamOther = document.getElementById('team_other');
        const natlTeamExp = document.getElementById('natl_team_exp');
        const natlTeamId = document.getElementById('national_team_id');
        const natlTeamOther = document.getElementById('national_team_other');

        if (school && school.value === '__other__' && schoolOther && !schoolOther.value.trim()) {
            errors.push('School Name is required.');
        }

        if (leagueId === '__other__') {
            if (leagueOther && !leagueOther.value.trim()) errors.push('League Name is required.');
            if (clubOther && !clubOther.value.trim()) errors.push('Club Name is required.');
            if (teamOther && !teamOther.value.trim()) errors.push('Team Name is required.');
        }

        if (natlTeamExp && natlTeamExp.value === '1' && natlTeamId && natlTeamId.value === '__other__' && natlTeamOther && !natlTeamOther.value.trim()) {
            errors.push('New National Team Name is required.');
        }
    }

    if (step === 3) {
        ['ig_handle', 'x_handle', 'yt_url', 'featured_video_url'].forEach((id) => {
            const el = document.getElementById(id);
            if (el && el.value.trim() && !validateUrl(el.value.trim())) {
                errors.push(`${id.replace(/_/g, ' ')} must be a valid URL.`);
            }
        });

        const useCustom = document.getElementById('use_custom_highlights');
        const manualUrls = document.getElementById('featured_video_urls');

        if (useCustom && useCustom.checked) {
            const lines = (manualUrls?.value || '')
                .split(/\r?\n/)
                .map(v => v.trim())
                .filter(Boolean);

            if (!lines.length) {
                errors.push('Add at least one Highlight Video URL.');
            } else if (lines.some(url => !validateUrl(url))) {
                errors.push('All Highlight Video URLs must be valid.');
            }
        }
    }

    return errors;
}

    function clearFieldErrors() {
        document.querySelectorAll('.field-error').forEach((el) => el.classList.remove('field-error'));
    }

    function markFieldState() {
    clearFieldErrors();

    if (currentStep === 1) {
        const firstName = document.getElementById('first_name');
        const lastName = document.getElementById('last_name');
        const personalEmail = document.getElementById('personal_email');
        const gender = document.getElementById('gender');
        const sport = document.getElementById('sport');

        if (!firstName.value.trim()) firstName.classList.add('field-error');
        if (!lastName.value.trim()) lastName.classList.add('field-error');
        if (!personalEmail.value.trim() || !validateEmail(personalEmail.value.trim())) personalEmail.classList.add('field-error');
        if (!gender.value.trim()) gender.classList.add('field-error');
        if (!sport.value.trim()) sport.classList.add('field-error');

        // No sport-specific optional fields should be marked as error
    }

    if (currentStep === 3) {
        ['ig_handle', 'x_handle', 'yt_url', 'featured_video_url'].forEach((id) => {
            const el = document.getElementById(id);
            if (el && el.value.trim() && !validateUrl(el.value.trim())) {
                el.classList.add('field-error');
            }
        });
    }
}

    function isStepComplete(step) {
        return getStepValidationErrors(step).length === 0;
    }

    function updateNextButtonState() {
        const nextBtn = document.getElementById('nextStepBtn');
        const submitBtn = document.getElementById('submitBtn');

        if (currentStep < totalSteps) {
            nextBtn.disabled = !isStepComplete(currentStep);
        } else {
            nextBtn.disabled = true;
        }

        if (submitBtn) {
            submitBtn.disabled = !isStepComplete(totalSteps);
        }

        markFieldState();
        refreshStepPills();
    }

    function refreshStepPills() {
        document.querySelectorAll('[data-step-pill]').forEach((pill) => {
            const pillStep = Number(pill.dataset.stepPill);

            let accessible = false;
            if (pillStep === 1) {
                accessible = true;
            } else if (pillStep <= currentStep) {
                accessible = true;
            } else {
                accessible = isStepComplete(pillStep - 1) && pillStep === currentStep + 1;
            }

            pill.classList.toggle('active', pillStep === currentStep);
            pill.classList.toggle('done', pillStep < currentStep && isStepComplete(pillStep));
            pill.disabled = !accessible;
        });
    }

    function showStep(step) {
        currentStep = Math.max(1, Math.min(totalSteps, Number(step)));

        document.querySelectorAll('.step-panel').forEach((panel) => {
            panel.classList.toggle('active', Number(panel.dataset.step) === currentStep);
        });

        const prevBtn = document.getElementById('prevStepBtn');
        const nextBtn = document.getElementById('nextStepBtn');
        const submitWrap = document.getElementById('submitWrap');

        prevBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        nextBtn.style.display = currentStep === totalSteps ? 'none' : 'inline-flex';
        submitWrap.classList.toggle('visible', currentStep === totalSteps);

        updateNextButtonState();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function goNextStep() {
        const errors = getStepValidationErrors(currentStep);
        if (errors.length) {
            alert(errors[0]);
            updateNextButtonState();
            return;
        }

        if (currentStep < totalSteps) showStep(currentStep + 1);
    }

    function goPrevStep() {
        if (currentStep > 1) showStep(currentStep - 1);
    }

    function buildSearchSelect({
        rootId,
        hiddenInputId,
        placeholder,
        getOptions,
        onChange,
        allowOther = true,
        disabledWhen = null,
        emptyMessage = 'No results found.',
    }) {
        const root = document.getElementById(rootId);
        const hiddenInput = document.getElementById(hiddenInputId);

        root.innerHTML = `
            <div class="search-select" data-search-select="${rootId}">
                <button type="button" class="search-select-control">
                    <span class="search-select-value">
                        <span class="search-select-placeholder">${placeholder}</span>
                    </span>
                    ${iconCaret()}
                </button>
                <div class="search-select-dropdown">
                    <div class="search-select-search">
                        ${iconSearch()}
                        <input type="text" placeholder="Search..." autocomplete="off">
                    </div>
                    <div class="search-select-list"></div>
                </div>
            </div>
        `;

        const wrapper = root.querySelector('.search-select');
        const control = root.querySelector('.search-select-control');
        const searchInput = root.querySelector('.search-select-search input');
        const list = root.querySelector('.search-select-list');

        function isDisabled() {
            return typeof disabledWhen === 'function' ? !!disabledWhen() : false;
        }

        function close() {
            wrapper.classList.remove('open');
            searchInput.value = '';
            render();
        }

        function open() {
            if (isDisabled()) return;
            document.querySelectorAll('.search-select.open').forEach(el => {
                if (el !== wrapper) el.classList.remove('open');
            });
            wrapper.classList.add('open');
            searchInput.focus();
            render();
        }

        function setDisplay(item) {
            const valueEl = control.querySelector('.search-select-value');

            if (!item) {
                valueEl.innerHTML = `<span class="search-select-placeholder">${placeholder}</span>`;
                return;
            }

            const logo = item.logo_url
                ? `<img src="${item.logo_url}" class="search-select-logo" alt="">`
                : '';

            valueEl.innerHTML = `
                ${logo}
                <span class="search-select-meta">${item.label}</span>
            `;
        }

        function getAllOptions() {
            let options = getOptions() || [];
            if (allowOther) {
                options = [...options, {
                    id: '__other__',
                    label: 'Other',
                    subtitle: 'Create a new entry manually',
                    logo_url: null,
                }];
            }
            return options;
        }

        function render() {
            wrapper.classList.toggle('is-disabled', isDisabled());

            const options = getAllOptions();
            const query = safeString(searchInput.value).toLowerCase();

            const filtered = options.filter(item => {
                const haystack = `${safeString(item.label)} ${safeString(item.subtitle)}`.toLowerCase();
                return haystack.includes(query);
            });

            list.innerHTML = '';

            if (!filtered.length) {
                list.innerHTML = `<div class="search-select-empty">${emptyMessage}</div>`;
            } else {
                filtered.forEach(item => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'search-select-option';
                    btn.innerHTML = `
                        ${item.logo_url ? `<img src="${item.logo_url}" class="search-select-logo" alt="">` : `<span class="search-select-logo" style="display:inline-flex;align-items:center;justify-content:center;">•</span>`}
                        <span class="search-select-option-copy">
                            <span class="search-select-option-title">${item.label}</span>
                            ${item.subtitle ? `<span class="search-select-option-subtitle">${item.subtitle}</span>` : ''}
                        </span>
                    `;
                    btn.addEventListener('click', () => {
                        hiddenInput.value = item.id;
                        setDisplay(item);
                        close();
                        if (typeof onChange === 'function') onChange(item);
                        updateNextButtonState();
                    });
                    list.appendChild(btn);
                });
            }

            const selected = options.find(item => String(item.id) === String(hiddenInput.value));
            setDisplay(selected || null);
        }

        control.addEventListener('click', () => {
            if (wrapper.classList.contains('open')) {
                close();
            } else {
                open();
            }
        });

        searchInput.addEventListener('input', render);

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) {
                close();
            }
        });

        return {
            render,
            close,
            setValue(value) {
                hiddenInput.value = value ?? '';
                render();
            },
            getValue() {
                return hiddenInput.value;
            }
        };
    }

    function getSelectedGender() {
        return safeString(document.getElementById('gender').value).toLowerCase();
    }

    function getSelectedSport() {
        return safeString(document.getElementById('sport').value).toLowerCase();
    }

    function getSelectedLeagueId() {
        return safeString(document.getElementById('league_id').value);
    }

    function getSelectedClubId() {
        return safeString(document.getElementById('club_id').value);
    }

    function mapLeagueOptions() {
        const selectedGender = getSelectedGender();
        const selectedSport = getSelectedSport();

        return leagueDirectory
            .filter(league =>
                isLeagueGenderCompatible(league.gender, selectedGender) &&
                isLeagueSportCompatible(league.sport, selectedSport)
            )
            .map(league => {
                const showGenderSuffix = !selectedGender && safeString(league.gender_label);
                const suffix = showGenderSuffix ? ` (${league.gender_label})` : '';
                return {
                    id: String(league.id),
                    label: `${league.name}${suffix}`,
                    subtitle: [league.sport_label].filter(Boolean).join(' • '),
                    logo_url: null,
                    raw: league,
                };
            });
    }

    function mapClubOptions() {
        const selectedGender = getSelectedGender();
        const selectedSport = getSelectedSport();
        const selectedLeagueId = getSelectedLeagueId();

        return clubDirectory
            .filter(club =>
                (!selectedLeagueId || selectedLeagueId === '__other__' ? true : String(club.league_id) === String(selectedLeagueId)) &&
                isLeagueGenderCompatible(club.gender, selectedGender) &&
                isLeagueSportCompatible(club.sport, selectedSport)
            )
            .map(club => ({
                id: String(club.id),
                label: club.name,
                subtitle: [club.league_name, club.gender_label, club.sport_label].filter(Boolean).join(' • '),
                logo_url: club.logo_url || null,
                raw: club,
            }));
    }

    function mapTeamOptions() {
        const selectedGender = getSelectedGender();
        const selectedSport = getSelectedSport();
        const selectedClubId = getSelectedClubId();

        return teamDirectory
            .filter(team =>
                (!selectedClubId ? true : String(team.club_id) === String(selectedClubId)) &&
                isLeagueGenderCompatible(team.gender, selectedGender) &&
                isLeagueSportCompatible(team.sport, selectedSport)
            )
            .map(team => ({
                id: String(team.id),
                label: team.name,
                subtitle: [team.club_name, team.league_name, team.gender_label, team.sport_label].filter(Boolean).join(' • '),
                logo_url: team.club_logo_url || null,
                raw: team,
            }));
    }

    function toggleOrganizationMode() {
        const leagueId = getSelectedLeagueId();
        const isOther = leagueId === '__other__';

        document.getElementById('league_other_wrap').style.display = isOther ? 'block' : 'none';
        document.getElementById('club_other_wrap').style.display = isOther ? 'block' : 'none';
        document.getElementById('team_other_wrap').style.display = isOther ? 'block' : 'none';

        if (isOther) {
            document.getElementById('club_id').value = '';
            document.getElementById('team_id').value = '';
        }
    }

    let leagueSelectApi;
    let clubSelectApi;
    let teamSelectApi;

    function initializeOrganizationSelectors() {
        leagueSelectApi = buildSearchSelect({
            rootId: 'leagueSelectRoot',
            hiddenInputId: 'league_id',
            placeholder: 'Select or search league',
            getOptions: mapLeagueOptions,
            onChange: () => {
                document.getElementById('club_id').value = '';
                document.getElementById('team_id').value = '';
                toggleOrganizationMode();
                clubSelectApi.render();
                teamSelectApi.render();
            },
        });

        clubSelectApi = buildSearchSelect({
            rootId: 'clubSelectRoot',
            hiddenInputId: 'club_id',
            placeholder: 'Select or search club',
            getOptions: mapClubOptions,
            disabledWhen: () => {
                const leagueId = getSelectedLeagueId();
                return !leagueId || leagueId === '__other__';
            },
            onChange: () => {
                document.getElementById('team_id').value = '';
                teamSelectApi.render();
            },
        });

        teamSelectApi = buildSearchSelect({
            rootId: 'teamSelectRoot',
            hiddenInputId: 'team_id',
            placeholder: 'Select or search team',
            getOptions: mapTeamOptions,
            disabledWhen: () => {
                const leagueId = getSelectedLeagueId();
                const clubId = getSelectedClubId();
                return !leagueId || leagueId === '__other__' || !clubId || clubId === '__other__';
            },
            onChange: () => {},
        });

        toggleOrganizationMode();
        leagueSelectApi.render();
        clubSelectApi.render();
        teamSelectApi.render();
    }

    function refreshOrganizationSelectors() {
        toggleOrganizationMode();

        const leagueId = getSelectedLeagueId();
        const clubId = getSelectedClubId();
        const teamId = document.getElementById('team_id').value;

        const validLeagueIds = mapLeagueOptions().map(item => String(item.id));
        if (leagueId && leagueId !== '__other__' && !validLeagueIds.includes(String(leagueId))) {
            document.getElementById('league_id').value = '';
            document.getElementById('club_id').value = '';
            document.getElementById('team_id').value = '';
        }

        const validClubIds = mapClubOptions().map(item => String(item.id));
        if (clubId && clubId !== '__other__' && !validClubIds.includes(String(clubId))) {
            document.getElementById('club_id').value = '';
            document.getElementById('team_id').value = '';
        }

        const validTeamIds = mapTeamOptions().map(item => String(item.id));
        if (teamId && teamId !== '__other__' && !validTeamIds.includes(String(teamId))) {
            document.getElementById('team_id').value = '';
        }

        leagueSelectApi.render();
        clubSelectApi.render();
        teamSelectApi.render();
    }

    function bindValidationListeners() {
        document.querySelectorAll('#playerIntakeForm input, #playerIntakeForm select, #playerIntakeForm textarea').forEach((el) => {
            ['input', 'change', 'blur'].forEach((evt) => {
                el.addEventListener(evt, updateNextButtonState);
            });
        });

        document.querySelectorAll('[data-step-pill]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!button.disabled) showStep(Number(button.dataset.stepPill));
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderPositions();
        toggleDominantFoot();
        toggleSchoolOther();
        toggleNationalTeamOther();
        toggleCustomHighlights();
        toggleCountryFields();
        syncStateValue();

        initializeOrganizationSelectors();

        if (oldLeagueId) document.getElementById('league_id').value = oldLeagueId;
        if (oldClubId) document.getElementById('club_id').value = oldClubId;
        if (oldTeamId) document.getElementById('team_id').value = oldTeamId;
        refreshOrganizationSelectors();

        const initialStep = {{ $errors->any() ? 'getStepFromErrors()' : '1' }};
        showStep(initialStep);

        document.getElementById('sport').addEventListener('change', () => {
            renderPositions();
            toggleDominantFoot();
            refreshOrganizationSelectors();
            updateNextButtonState();
        });

        document.getElementById('gender').addEventListener('change', () => {
            refreshOrganizationSelectors();
            updateNextButtonState();
        });

        document.getElementById('school_id').addEventListener('change', () => {
            toggleSchoolOther();
            updateNextButtonState();
        });

        document.getElementById('national_team_id').addEventListener('change', () => {
            toggleNationalTeamOther();
            updateNextButtonState();
        });

        document.getElementById('natl_team_exp').addEventListener('change', () => {
            toggleNationalTeamOther();
            updateNextButtonState();
        });

        document.getElementById('use_custom_highlights').addEventListener('change', () => {
            toggleCustomHighlights();
            updateNextButtonState();
        });

        document.getElementById('country').addEventListener('change', () => {
            toggleCountryFields();
            syncStateValue();
            updateNextButtonState();
        });

        document.getElementById('state_us').addEventListener('change', () => {
            syncStateValue();
            updateNextButtonState();
        });

        document.getElementById('state_international').addEventListener('input', () => {
            syncStateValue();
            updateNextButtonState();
        });

        document.getElementById('nextStepBtn').addEventListener('click', goNextStep);
        document.getElementById('prevStepBtn').addEventListener('click', goPrevStep);

        bindValidationListeners();
        updateNextButtonState();
    });
</script>
</body>
</html>