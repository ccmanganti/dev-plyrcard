<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Intake Form</title>
    <style>
        :root {
            --bg: #ffffff;
            --panel: #ffffff;
            --panel-soft: #fafafa;
            --field: #ffffff;
            --field-border: #d4d4d8;
            --text: #111111;
            --muted: #6b7280;
            --accent: #f97316;
            --accent-hover: #ea580c;
            --accent-soft: rgba(249, 115, 22, 0.08);
            --border: #e5e7eb;
            --success-bg: rgba(34, 197, 94, 0.10);
            --success-border: rgba(34, 197, 94, 0.28);
            --error-bg: rgba(239, 68, 68, 0.10);
            --error-border: rgba(239, 68, 68, 0.28);
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            --radius-xl: 24px;
            --radius-lg: 18px;
            --radius-md: 14px;
            --radius-sm: 12px;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        body.embed-mode { padding: 12px; }

        .wrapper {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .header {
            padding: 28px 28px 18px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            border: 1px solid rgba(249, 115, 22, 0.18);
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 34px;
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #111827;
        }

        .header p {
            margin: 0;
            max-width: 760px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
        }

        .content {
            padding: 24px;
            background: #ffffff;
        }

        .section {
            margin-bottom: 24px;
            padding: 22px;
            background: var(--panel-soft);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
        }

        .section h2 {
            margin: 0 0 16px;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #111827;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 16px;
        }

        .col-12 { grid-column: span 12; }
        .col-6 { grid-column: span 6; }
        .col-4 { grid-column: span 4; }
        .col-3 { grid-column: span 3; }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.01em;
            color: #111827;
        }

        .required { color: var(--accent); }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="url"],
        select,
        textarea {
            width: 100%;
            padding: 13px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--field-border);
            background: var(--field);
            color: #111827;
            font-size: 14px;
            line-height: 1.4;
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease;
            appearance: none;
        }

        input::placeholder,
        textarea::placeholder { color: #9ca3af; }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.14);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        input[type="file"] {
            width: 100%;
            padding: 12px;
            border-radius: var(--radius-sm);
            border: 1px dashed rgba(249, 115, 22, 0.45);
            background: #fff7ed;
            color: #111827;
        }

        .hint {
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
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
            background: #ffffff;
            border: 1px solid var(--border);
            color: #111827;
            font-size: 14px;
            transition: border-color .18s ease, background .18s ease;
        }

        .check-pill:hover {
            border-color: rgba(249, 115, 22, 0.45);
            background: #fff7ed;
        }

        .check-pill input {
            accent-color: var(--accent);
        }

        .error-list,
        .success-box {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: var(--radius-md);
            font-size: 14px;
        }

        .error-list {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: #991b1b;
        }

        .success-box {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: #166534;
        }

        .other-wrap {
            display: none;
            margin-top: 10px;
        }

        .readonly-box {
            width: 100%;
            padding: 13px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--field-border);
            background: #f3f4f6;
            color: #111827;
            font-size: 14px;
            min-height: 47px;
            display: flex;
            align-items: center;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }

        .btn {
            appearance: none;
            border: 0;
            background: var(--accent);
            color: #ffffff;
            font-weight: 800;
            font-size: 15px;
            padding: 15px 24px;
            border-radius: 14px;
            cursor: pointer;
            box-shadow: 0 10px 22px rgba(249, 115, 22, 0.22);
            transition: transform .12s ease, box-shadow .18s ease, background .18s ease;
        }

        .btn:hover {
            background: var(--accent-hover);
            box-shadow: 0 14px 26px rgba(249, 115, 22, 0.26);
        }

        .btn:active {
            transform: translateY(1px);
        }

        @media (max-width: 980px) {
            .header h1 { font-size: 28px; }
            .content { padding: 18px; }
            .header { padding: 24px 20px 18px; }
            .section { padding: 18px; }
            .col-6, .col-4, .col-3 { grid-column: span 12; }
            .actions { justify-content: stretch; }
            .btn { width: 100%; }
        }

        @media (max-width: 640px) {
            body.embed-mode { padding: 0; }
            .wrapper { max-width: 100%; }
            .card {
                border-radius: 0;
                border-left: 0;
                border-right: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body class="embed-mode">
<div class="wrapper">
    <div class="card">
        <div class="header">
            <div class="eyebrow">PlyrCard Intake</div>
            <h1>Player Intake Form</h1>
            <p>Complete the form below so we can create the athlete record and website automatically when supported.</p>
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
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                        </div>

                        <div class="col-4">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name') }}">
                        </div>

                        <div class="col-4">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
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
                            <div class="hint">Used only if a new club and league need to be created.</div>
                        </div>

                        <div class="col-4">
                            <label for="personal_email">Personal Email <span class="required">*</span></label>
                            <input type="email" id="personal_email" name="personal_email" value="{{ old('personal_email') }}" required>
                            <div class="hint">The PlyrCard email will be generated automatically.</div>
                        </div>

                        <div class="col-4">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" value="{{ old('phone') }}">
                        </div>

                        <div class="col-4">
                            <label for="birth">Birth Date</label>
                            <input type="date" id="birth" name="birth" value="{{ old('birth') }}">
                        </div>

                        <div class="col-4">
                            <label for="year">Graduation Year</label>
                            <input type="text" id="year" name="year" value="{{ old('year') }}">
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

                        <div class="col-4">
                            <label for="jersey_number">Jersey Number</label>
                            <input type="text" id="jersey_number" name="jersey_number" value="{{ old('jersey_number') }}">
                        </div>

                        <div class="col-4">
                            <label for="team_name">Team Name</label>
                            <input type="text" id="team_name" name="team_name" value="{{ old('team_name') }}">
                        </div>

                        <div class="col-4">
                            <label for="gpa">GPA</label>
                            <input type="text" id="gpa" name="gpa" value="{{ old('gpa') }}">
                        </div>

                        <div class="col-4">
                            <label for="height">Height</label>
                            <input type="text" id="height" name="height" value="{{ old('height') }}">
                        </div>

                        <div class="col-4">
                            <label for="weight">Weight</label>
                            <input type="text" id="weight" name="weight" value="{{ old('weight') }}">
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
                            <label for="ig_handle">Instagram Handle</label>
                            <input type="text" id="ig_handle" name="ig_handle" value="{{ old('ig_handle') }}">
                        </div>

                        <div class="col-4">
                            <label for="x_handle">X Handle</label>
                            <input type="text" id="x_handle" name="x_handle" value="{{ old('x_handle') }}">
                        </div>

                        <div class="col-6">
                            <label for="yt_url">YouTube URL</label>
                            <input type="url" id="yt_url" name="yt_url" value="{{ old('yt_url') }}">
                        </div>

                        <div class="col-6"></div>

                        <div class="col-6">
                            <label for="academic_accolades">Academic Accolades</label>
                            <textarea id="academic_accolades" name="academic_accolades">{{ old('academic_accolades') }}</textarea>
                        </div>

                        <div class="col-6">
                            <label for="sports_accolades">Sports Accolades</label>
                            <textarea id="sports_accolades" name="sports_accolades">{{ old('sports_accolades') }}</textarea>
                        </div>

                        <div class="col-12">
                            <label for="press">Press / Notes</label>
                            <textarea id="press" name="press">{{ old('press') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Location, School & Club</h2>
                    <div class="grid">
                        <div class="col-3">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" value="{{ old('country', 'USA') }}">
                        </div>

                        <div class="col-3">
                            <label for="state">State</label>
                            <select id="state" name="state">
                                <option value="">Select state</option>
                                @foreach ($states as $abbr => $label)
                                    <option value="{{ $abbr }}" {{ old('state') === $abbr ? 'selected' : '' }}>
                                        {{ $label }} ({{ $abbr }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="hint">State will be saved as its abbreviation.</div>
                        </div>

                        <div class="col-3">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" value="{{ old('city') }}">
                        </div>

                        <div class="col-3">
                            <label for="street">Street</label>
                            <input type="text" id="street" name="street" value="{{ old('street') }}">
                        </div>

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
                            <div id="school_other_wrap" class="other-wrap">
                                <input type="text" name="school_other" placeholder="Enter school name" value="{{ old('school_other') }}">
                            </div>
                        </div>

                        <div class="col-4">
                            <label for="club_id">Club</label>
                            <select id="club_id" name="club_id">
                                <option value="">Select club</option>
                                @foreach ($clubs as $club)
                                    <option
                                        value="{{ $club->id }}"
                                        data-league-name="{{ $club->league?->name }}"
                                        data-league-gender="{{ $club->league?->gender }}"
                                        {{ (string) old('club_id') === (string) $club->id ? 'selected' : '' }}
                                    >
                                        {{ $club->name }}
                                    </option>
                                @endforeach
                                <option value="__other__" {{ old('club_id') === '__other__' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>

                        <div class="col-4">
                            <label>League</label>
                            <div id="league_display" class="readonly-box">Select a club first</div>
                            <div class="hint">League is automatically assigned from the selected club.</div>
                        </div>

                        <div id="club_other_section" class="col-12 other-wrap">
                            <div class="grid">
                                <div class="col-6">
                                    <label for="club_other">New Club Name</label>
                                    <input type="text" id="club_other" name="club_other" value="{{ old('club_other') }}" placeholder="Enter new club name">
                                </div>

                                <div class="col-6">
                                    <label for="league_other">New League Name</label>
                                    <input type="text" id="league_other" name="league_other" value="{{ old('league_other') }}" placeholder="Enter new league name">
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
                            <input type="text" id="parent" name="parent" value="{{ old('parent') }}">
                        </div>

                        <div class="col-4">
                            <label for="parent_email">Primary Parent Email</label>
                            <input type="email" id="parent_email" name="parent_email" value="{{ old('parent_email') }}">
                        </div>

                        <div class="col-4">
                            <label for="parent_phone">Primary Parent Phone</label>
                            <input type="text" id="parent_phone" name="parent_phone" value="{{ old('parent_phone') }}">
                        </div>

                        <div class="col-4">
                            <label for="sec_parent">Secondary Parent / Guardian</label>
                            <input type="text" id="sec_parent" name="sec_parent" value="{{ old('sec_parent') }}">
                        </div>

                        <div class="col-4">
                            <label for="sec_parent_email">Secondary Parent Email</label>
                            <input type="email" id="sec_parent_email" name="sec_parent_email" value="{{ old('sec_parent_email') }}">
                        </div>

                        <div class="col-4">
                            <label for="sec_parent_phone">Secondary Parent Phone</label>
                            <input type="text" id="sec_parent_phone" name="sec_parent_phone" value="{{ old('sec_parent_phone') }}">
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Coaches & Trainers</h2>
                    <div class="grid">
                        <div class="col-4">
                            <label for="club_coach">Club Coach</label>
                            <input type="text" id="club_coach" name="club_coach" value="{{ old('club_coach') }}">
                        </div>

                        <div class="col-4">
                            <label for="club_coach_email">Club Coach Email</label>
                            <input type="email" id="club_coach_email" name="club_coach_email" value="{{ old('club_coach_email') }}">
                        </div>

                        <div class="col-4">
                            <label for="club_coach_phone">Club Coach Phone</label>
                            <input type="text" id="club_coach_phone" name="club_coach_phone" value="{{ old('club_coach_phone') }}">
                        </div>

                        <div class="col-4">
                            <label for="natl_coach">National Coach</label>
                            <input type="text" id="natl_coach" name="natl_coach" value="{{ old('natl_coach') }}">
                        </div>

                        <div class="col-4">
                            <label for="natl_coach_email">National Coach Email</label>
                            <input type="email" id="natl_coach_email" name="natl_coach_email" value="{{ old('natl_coach_email') }}">
                        </div>

                        <div class="col-4">
                            <label for="natl_coach_phone">National Coach Phone</label>
                            <input type="text" id="natl_coach_phone" name="natl_coach_phone" value="{{ old('natl_coach_phone') }}">
                        </div>

                        <div class="col-4">
                            <label for="tech_trainer">Technical Trainer</label>
                            <input type="text" id="tech_trainer" name="tech_trainer" value="{{ old('tech_trainer') }}">
                        </div>

                        <div class="col-4">
                            <label for="tech_trainer_email">Technical Trainer Email</label>
                            <input type="email" id="tech_trainer_email" name="tech_trainer_email" value="{{ old('tech_trainer_email') }}">
                        </div>

                        <div class="col-4">
                            <label for="tech_trainer_phone">Technical Trainer Phone</label>
                            <input type="text" id="tech_trainer_phone" name="tech_trainer_phone" value="{{ old('tech_trainer_phone') }}">
                        </div>

                        <div class="col-4">
                            <label for="snc_trainer">Strength & Conditioning Trainer</label>
                            <input type="text" id="snc_trainer" name="snc_trainer" value="{{ old('snc_trainer') }}">
                        </div>

                        <div class="col-4">
                            <label for="snc_trainer_email">S&C Trainer Email</label>
                            <input type="email" id="snc_trainer_email" name="snc_trainer_email" value="{{ old('snc_trainer_email') }}">
                        </div>

                        <div class="col-4">
                            <label for="snc_trainer_phone">S&C Trainer Phone</label>
                            <input type="text" id="snc_trainer_phone" name="snc_trainer_phone" value="{{ old('snc_trainer_phone') }}">
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Images</h2>
                    <div class="grid">
                        <div class="col-4">
                            <label for="player_card_image">Player Card Image</label>
                            <input type="file" id="player_card_image" name="player_card_image" accept="image/png">
                            <div class="hint">
                                Upload a PNG player card image. Transparent background preferred. Max 5MB.
                            </div>
                        </div>

                        <div class="col-4">
                            <label for="player_image">Player Image</label>
                            <input type="file" id="player_image" name="player_image" accept="image/png">
                            <div class="hint">
                                Upload a PNG solo player image cropped from head to belly (half body). Transparent background preferred. Max 5MB.
                            </div>
                        </div>

                        <div class="col-4">
                            <label for="mobile_view_image">Mobile View Image</label>
                            <input type="file" id="mobile_view_image" name="mobile_view_image" accept="image/png">
                            <div class="hint">
                                Upload a PNG mobile hero image for phone display on your website. Vertical-friendly crop recommended. Max 5MB.
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

    function renderPositions() {
        const sportSelect = document.getElementById('sport');
        const container = document.getElementById('positionOptions');
        const selectedSport = sportSelect.value;

        container.innerHTML = '';

        if (!selectedSport || !sportPositions[selectedSport]) {
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
        wrap.style.display = select.value === '__other__' ? 'block' : 'none';
    }

    function updateLeagueDisplay() {
        const clubSelect = document.getElementById('club_id');
        const leagueDisplay = document.getElementById('league_display');
        const clubOtherSection = document.getElementById('club_other_section');
        const selectedOption = clubSelect.options[clubSelect.selectedIndex];

        if (!clubSelect.value) {
            leagueDisplay.textContent = 'Select a club first';
            clubOtherSection.style.display = 'none';
            return;
        }

        if (clubSelect.value === '__other__') {
            leagueDisplay.textContent = 'Enter a new league below';
            clubOtherSection.style.display = 'block';
            return;
        }

        const leagueName = selectedOption?.dataset?.leagueName || 'League not found';
        clubOtherSection.style.display = 'none';
        leagueDisplay.textContent = leagueName;
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

    document.getElementById('sport').addEventListener('change', () => {
        renderPositions();
        updateImageInstructions();
    });

    document.getElementById('school_id').addEventListener('change', toggleSchoolOther);
    document.getElementById('club_id').addEventListener('change', updateLeagueDisplay);

    renderPositions();
    toggleSchoolOther();
    updateLeagueDisplay();
    updateImageInstructions();
</script>   
</body>
</html>