<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>PLYRCARD Intake</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=antonio:300,400,500,600,700" rel="stylesheet" />

    <style>
        :root{
            --pc-orange:#ff6347;
            --pc-white:#f2f1ec;
            --pc-black:#111111;
            --pc-blue:#1f84d8;
            --pc-app-width:430px;
            --topbar-h:58px;
            --band-h:118px;
        }

        *{box-sizing:border-box}
        html,body{margin:0;padding:0}
        body{
            min-height:100vh;
            background:#e8e8e8;
            font-family:"Antonio", system-ui, sans-serif;
        }

        html{
            scrollbar-width:thin;
            scrollbar-color:rgba(17,17,17,.38) rgba(242,241,236,.2);
        }

        body::-webkit-scrollbar,
        .search-select-list::-webkit-scrollbar{
            width:10px;
            height:10px;
        }

        body::-webkit-scrollbar-track,
        .search-select-list::-webkit-scrollbar-track{
            background:rgba(242,241,236,.18);
            border-radius:999px;
        }

        body::-webkit-scrollbar-thumb,
        .search-select-list::-webkit-scrollbar-thumb{
            background:rgba(17,17,17,.38);
            border-radius:999px;
            border:2px solid transparent;
            background-clip:padding-box;
        }

        body::-webkit-scrollbar-thumb:hover,
        .search-select-list::-webkit-scrollbar-thumb:hover{
            background:rgba(17,17,17,.54);
            background-clip:padding-box;
        }

        .page{
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:0;
        }

        .app{
            width:100%;
            max-width:var(--pc-app-width);
            min-height:100vh;
            background:var(--pc-orange);
            position:relative;
            overflow:visible;
            box-shadow:0 18px 50px rgba(0,0,0,.14);
        }

        @media (max-width: 480px){
            body,.page{background:var(--pc-orange)}
            .app{max-width:none;box-shadow:none}
        }

        .app::before{
            content:"";
            position:absolute;
            inset:0;
            pointer-events:none;
            opacity:.16;
            background:
                radial-gradient(circle at 14% 18%, rgba(255,255,255,.20), transparent 18%),
                radial-gradient(circle at 82% 24%, rgba(255,255,255,.13), transparent 18%),
                radial-gradient(circle at 22% 72%, rgba(255,255,255,.11), transparent 18%),
                radial-gradient(circle at 78% 86%, rgba(255,255,255,.10), transparent 20%);
            mix-blend-mode:soft-light;
            z-index:0;
        }

        .screen{
            position:relative;
            min-height:100vh;
            background:var(--pc-orange);
            z-index:1;
        }

        .logo-main{width:98px;display:block;margin:0 auto;opacity:.92}

        .hero-screen{
            padding:34px 28px calc(28px + env(safe-area-inset-bottom));
            display:flex;
            flex-direction:column;
            color:#fff;
            min-height:100vh;
        }

        .hero-logo-wrap{text-align:center;padding-top:104px}
        .hero-copy{margin-top:auto}

        .hero-title,
        .final-title{
            margin:0 0 16px;
            font-size:56px;
            line-height:.9;
            letter-spacing:.01em;
            text-transform:uppercase;
            font-weight:700;
            color:#fff;
        }

        .hero-text,
        .final-text{
            margin:0 0 18px;
            font-size:14px;
            line-height:1.22;
            color:#fff;
            opacity:.96;
            max-width:310px;
        }

        .btn{
            width:100%;
            min-height:48px;
            border:0;
            border-radius:12px;
            background:var(--pc-white);
            color:var(--pc-black);
            font-family:"Antonio", system-ui, sans-serif;
            font-size:22px;
            line-height:1;
            letter-spacing:.03em;
            text-transform:uppercase;
            font-weight:700;
            cursor:pointer;
            transition:transform .18s ease, opacity .18s ease, box-shadow .18s ease;
            box-shadow:0 6px 16px rgba(0,0,0,.08);
        }

        .btn:hover{transform:translateY(-1px)}
        .btn:active{transform:translateY(0)}
        .btn:disabled{opacity:.55;cursor:not-allowed;transform:none}
        .btn-link{display:block;text-decoration:none}
        .bottom-cta{margin-top:10px}

        .topbar{
            height:var(--topbar-h);
            display:flex;
            align-items:center;
            gap:12px;
            padding:10px 14px 6px;
            position:relative;
            z-index:3;
            flex:0 0 var(--topbar-h);
        }

        .back-btn{
            width:38px;
            height:38px;
            border-radius:999px;
            border:0;
            background:var(--pc-white);
            display:flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
            flex:0 0 auto;
        }

        .back-btn svg{width:20px;height:20px;stroke:#666;stroke-width:2.6}
        .topbar-title{font-size:14px;line-height:1;color:#fff;letter-spacing:.02em;font-weight:700}

        .progress-band{
            background:#efefec;
            min-height:var(--band-h);
            display:grid;
            grid-template-columns:86px 1fr;
            gap:10px;
            align-items:center;
            padding:12px 14px 12px 6px;
            position:relative;
            z-index:2;
            flex:0 0 var(--band-h);
        }

        .ring-wrap{display:flex;justify-content:center;align-items:center}
        .ring{
            width:76px;
            height:76px;
            border-radius:50%;
            background:conic-gradient(var(--pc-blue) 0deg var(--ring-angle, 90deg), #dfdfdf var(--ring-angle, 90deg) 360deg);
            position:relative;
            display:grid;
            place-items:center;
            transition:background .28s ease;
        }
        .ring::after{content:"";position:absolute;inset:13px;border-radius:50%;background:#efefec}
        .ring-value{position:relative;z-index:1;font-size:18px;color:#4b4b4b;font-weight:300}
        .progress-title{margin:0;color:#141414;font-size:22px;line-height:.98;font-weight:700;max-width:250px}

        .step-screen{
            color:#fff;
            display:flex;
            flex-direction:column;
            min-height:100vh;
        }

        .step-panel,
        .final-screen{
            display:none;
            position:relative;
            flex:1 1 auto;
            min-height:0;
            padding:18px 22px calc(18px + env(safe-area-inset-bottom));
            overflow:visible;
            animation:fadeSlide .22s ease;
        }

        .panel-scroll{
            height:auto;
            overflow:visible;
            padding-bottom:8px;
        }

        .panel-scroll.with-sticky-cta{
            height:auto;
            min-height:0;
            padding-bottom:120px;
            overflow:visible;
        }

        .step-panel.active,
        .final-screen.active{display:block}

        @keyframes fadeSlide{
            from{opacity:0;transform:translateY(12px)}
            to{opacity:1;transform:translateY(0)}
        }

        .field{margin-bottom:18px;position:relative;z-index:2}
        .field.field-open{z-index:120}
        .field.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .field.narrow{max-width:172px}
        .label{display:block;margin:0 0 8px;font-size:17px;line-height:1;text-transform:uppercase;letter-spacing:.02em;color:#fff;font-weight:700}

        .input,
        .select,
        .search-select-control,
        .file-input,
        .manual-input{
            width:100%;
            min-height:34px;
            border:0;
            outline:none;
            border-radius:999px;
            background:var(--pc-white);
            color:#111;
            font-family:"Antonio", system-ui, sans-serif;
            font-size:13px;
            padding:7px 12px;
            appearance:none;
            transition:transform .16s ease, box-shadow .16s ease, opacity .16s ease;
        }

        .input:focus,
        .select:focus,
        .manual-input:focus,
        .search-select-control:focus{box-shadow:0 0 0 2px rgba(255,255,255,.22)}

        .watermark,
        .final-logo{
            position:absolute;
            pointer-events:none;
            user-select:none;
            opacity:.34;
        }

        .watermark{
            right:-4px;
            top:150px;
            width:192px;
        }

        .gender-list{display:flex;flex-direction:row;gap:10px;padding-left:1px}
        .gender-item{display:flex;align-items:center;gap:12px;color:#fff;font-size:15px;line-height:1}
        .gender-item input{width:16px;height:16px;accent-color:#111;transform:scale(1.65);margin:0}

        .step1-grid{
            display:grid;
            grid-template-columns:minmax(0, 1fr) 176px;
            gap:12px;
            align-items:start;
        }
        .step1-left{min-width:0}
        .step1-logo-col{position:relative;min-height:360px}
        .step1-logo-col .watermark{position:absolute;right:-10px;top:54px;width:204px}

        .search-select{position:relative;z-index:20;transition:opacity .18s ease, transform .18s ease}
        .search-select.open{z-index:260}
        .search-select.is-disabled{opacity:.58;pointer-events:none}
        .search-select.is-hidden{display:none}
        .search-select-control{display:flex;align-items:center;justify-content:space-between;gap:8px;text-align:left;cursor:pointer}
        .search-select-value{display:flex;align-items:center;gap:8px;min-width:0;flex:1 1 auto}
        .search-select-placeholder,.search-select-meta{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#111}
        .search-select-logo{width:18px;height:18px;border-radius:50%;object-fit:cover;background:#ddd;flex:0 0 auto}
        .search-select-caret,.search-select-search-icon{width:15px;height:15px;color:#666;flex:0 0 auto}

        .search-select-dropdown{
            display:none;
            position:absolute;
            left:0;
            right:0;
            top:calc(100% + 6px);
            z-index:280;
            background:#f6f6f2;
            border-radius:14px;
            overflow:hidden;
            box-shadow:0 18px 36px rgba(0,0,0,.16);
        }
        .search-select.open .search-select-dropdown{display:block}
        .search-select-search{display:flex;gap:8px;align-items:center;padding:8px;border-bottom:1px solid rgba(0,0,0,.08);color:#666}
        .search-select-search input{width:100%;min-height:32px;border:0;border-radius:999px;outline:none;background:#fff;padding:7px 12px;font-family:"Antonio", system-ui, sans-serif;font-size:13px}
        .search-select-list{max-height:190px;overflow:auto;padding:6px 0}
        .search-select-option{width:100%;border:0;background:transparent;color:#111;text-align:left;padding:10px 12px;display:flex;align-items:center;gap:8px;cursor:pointer}
        .search-select-option-copy{display:flex;flex-direction:column;min-width:0}
        .search-select-option-title{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .search-select-option-subtitle{font-size:10px;color:#666;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .search-select-empty{padding:12px;color:#666;font-size:12px}

        .manual-input-wrap{display:none;animation:fadeSlide .2s ease}
        .manual-input-actions{display:flex;justify-content:flex-end;margin-top:8px}
        .manual-toggle-back{border:0;background:transparent;color:#fff;font-family:"Antonio", system-ui, sans-serif;font-size:12px;letter-spacing:.02em;text-transform:uppercase;cursor:pointer;opacity:.9;padding:0}
        .manual-toggle-back:hover{opacity:1;text-decoration:underline}
        .manual-input::placeholder,.input::placeholder{color:#6a6a6a;opacity:.85}
        .manual-input-wrap.visible{display:block}

        .chips-wrap{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;min-height:20px}
        .chip{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.18);font-size:12px;color:#fff;line-height:1}
        .chip button{border:0;background:transparent;color:#fff;font:inherit;cursor:pointer;padding:0;line-height:1}

        .file-input{padding:7px 10px}
        .file-input::file-selector-button{border:0;border-radius:999px;padding:6px 10px;background:#deded8;color:#111;font-family:"Antonio", system-ui, sans-serif;margin-right:8px;cursor:pointer}
        .image-preview-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px;max-width:318px}
        .image-preview-card{position:relative;aspect-ratio:1/1;border-radius:14px;overflow:hidden;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);animation:fadeSlide .2s ease}
        .image-preview-card img{width:100%;height:100%;object-fit:cover;display:block}
        .image-preview-overlay{position:absolute;inset:0;background:linear-gradient(to top, rgba(0,0,0,.52), rgba(0,0,0,.08));display:flex;align-items:flex-end;justify-content:center;padding:8px}
        .image-preview-remove{border:0;border-radius:999px;background:rgba(255,255,255,.94);color:#111;font-family:"Antonio", system-ui, sans-serif;font-size:11px;line-height:1;text-transform:uppercase;padding:7px 10px;cursor:pointer}
        .sample-block{margin-top:6px;position:relative;z-index:2;max-width:292px}
        .sample-title{margin:0 0 14px;font-size:19px;line-height:1;text-transform:uppercase;color:#fff;font-weight:700}
        .sample-copy{margin:0 0 16px;font-size:13px;line-height:1.18;color:#fff;opacity:.95}

        .step-cta{
            position:sticky;
            left:0;
            right:0;
            bottom:0;
            z-index:4;
            margin-top:18px;
            padding-top:14px;
            padding-bottom:calc(2px + env(safe-area-inset-bottom));
            background:linear-gradient(to bottom, rgba(231,118,89,0), rgba(231,118,89,.92) 34%, rgba(231,118,89,1) 100%);
        }

        .final-screen{padding-top:14px}
        .final-title{margin-top:96px;max-width:290px;font-size:60px}
        .final-text{max-width:336px;font-size:16px;line-height:1.18;margin-top:18px}
        .final-logo{right:16px;bottom:146px;width:170px}

        .field-message,
        .file-warning-list{
            display:none;
            margin-top:8px;
            font-size:11px;
            line-height:1.3;
            color:#fff2f2;
        }

        .field.is-invalid .input,
        .field.is-invalid .select,
        .field.is-invalid .search-select-control,
        .field.is-invalid .manual-input,
        .field.is-invalid .file-input{
            box-shadow:0 0 0 2px rgba(120,0,0,.30);
        }

        .field-message.error,
        .file-warning-list.visible{
            display:block;
        }

        @media (max-width: 400px){
            .hero-title,.final-title{font-size:52px}
            .progress-title{font-size:20px}
            .step1-grid{grid-template-columns:minmax(0, 1fr) 158px}
            .step1-logo-col .watermark{width:188px;top:62px;right:-16px}
            .field.narrow{max-width:160px}
        }

        @media (max-width: 350px){
            .hero-title,.final-title{font-size:46px}
            .progress-title{font-size:18px}
            .label{font-size:16px}
            .hero-text,.final-text,.sample-copy{font-size:12px}
            .step1-grid{grid-template-columns:1fr 144px}
            .step1-logo-col .watermark{width:170px;right:-18px}
        }
    </style>
</head>
<body>
<div class="page">
    <div class="app">
        @php
            $submitted = session('intake_submitted', []);
            $submittedFirstName = $submitted['first_name'] ?? 'Athlete';
            $submittedEmail = $submitted['email'] ?? null;
            $autoLoginUrl = session('auto_login_url');
        @endphp

        @if (session('success'))
            <section class="screen hero-screen" id="thanksScreen">
                <div class="hero-logo-wrap">
                    <img src="{{ asset('images/plyrcardlogo.png') }}" alt="PLYRCARD" class="logo-main">
                </div>

                <div class="hero-copy">
                    <h1 class="hero-title">Thank You</h1>
                    <p class="hero-text">Your intake has been submitted successfully, {{ $submittedFirstName }}.</p>
                    <p class="hero-text">Please check your email{{ $submittedEmail ? ' at ' . $submittedEmail : '' }} for confirmation of your login and account access details.</p>
                    <p class="hero-text">When you are ready, tap below to go straight into the app.</p>

                    <div class="bottom-cta">
                        <a href="{{ $autoLoginUrl ?: url('admin/login') }}" class="btn-link">
                            <button type="button" class="btn">Login</button>
                        </a>
                    </div>
                </div>
            </section>
        @else
            <section class="screen hero-screen" id="introScreen">
                <div class="hero-logo-wrap">
                    <img src="{{ asset('images/plyrcardlogo.png') }}" alt="PLYRCARD" class="logo-main">
                </div>

                <div class="hero-copy">
                    <h1 class="hero-title">Start Your Journey</h1>
                    <p class="hero-text">Let’s build your PLYRCARD profile. Add the basics now, then finish the rest whenever you’re ready.</p>
                    <p class="hero-text">Reach 60% completion and we’ll start our review before publishing. Strong profiles start with strong details the more you share, the better your final card will be.</p>

                    <div class="bottom-cta">
                        <button type="button" class="btn" id="startBtn">Start</button>
                    </div>
                </div>
            </section>

            <form method="POST" action="{{ route('public.player-intake-app.store') }}" enctype="multipart/form-data" id="playerIntakeForm">
                @csrf
                <input type="hidden" name="selected_plan" value="{{ old('selected_plan', $selectedPlan ?? 'Free') }}">

                <section class="screen step-screen" id="formScreen" style="display:none;">
                    <div class="topbar">
                        <button type="button" class="back-btn" id="backBtn" aria-label="Back">
                            <svg viewBox="0 0 24 24" fill="none"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <div class="topbar-title" id="topbarTitle">PLYRPROFILE</div>
                    </div>

                    <div class="progress-band" id="progressBand">
                        <div class="ring-wrap">
                            <div class="ring" id="progressRing">
                                <div class="ring-value" id="progressValue">25%</div>
                            </div>
                        </div>
                        <div>
                            <h2 class="progress-title" id="progressTitle">Begin with the basics and lay the foundation for your PLYRCARD.</h2>
                        </div>
                    </div>

                    <div class="step-panel active" data-step="1">
                        <div class="panel-scroll">
                            <div class="field two">
                                        <div>
                                            <label class="label" for="first_name">First Name</label>
                                            <input class="input" type="text" id="first_name" name="first_name" value="{{ old('first_name', $prefill['first_name'] ?? '') }}" placeholder="Enter first name" required>
                                            <div class="field-message" id="first_name_error"></div>
                                        </div>
                                        <div>
                                            <label class="label" for="last_name">Last Name</label>
                                            <input class="input" type="text" id="last_name" name="last_name" value="{{ old('last_name', $prefill['last_name'] ?? '') }}" placeholder="Enter last name" required>
                                            <div class="field-message" id="last_name_error"></div>
                                        </div>
                                    </div>
                            <div class="step1-grid">
                                <div class="step1-left">
                                    

                                    <div class="field" id="gender_field">
                                        <label class="label">Gender</label>
                                        <div class="gender-list">
                                            @php
                                                $oldGender = old('gender');
                                                $isFemale = in_array($oldGender, ['female', 'girls'], true);
                                                $isMale = ! $isFemale;
                                            @endphp
                                            <label class="gender-item">
                                                <input type="radio" name="gender" value="male" {{ $isMale ? 'checked' : '' }}>
                                                <span>Boys</span>
                                            </label>
                                            <label class="gender-item">
                                                <input type="radio" name="gender" value="female" {{ $isFemale ? 'checked' : '' }}>
                                                <span>Girls</span>
                                            </label>
                                        </div>
                                        <div class="field-message" id="gender_error"></div>
                                    </div>

                                    <div class="field narrow">
                                        <label class="label" for="personal_email">Email</label>
                                        <input class="input" type="email" id="personal_email" name="personal_email" value="{{ old('personal_email', $prefill['personal_email'] ?? '') }}" placeholder="Enter email address" required>
                                        <div class="field-message" id="personal_email_error"></div>
                                    </div>

                                    <div class="field narrow">
                                        <label class="label" for="phone">Cell Phone</label>
                                        <input class="input phone-input" type="text" id="phone" name="phone" value="{{ old('phone', $prefill['phone'] ?? '') }}" inputmode="tel" placeholder="Enter mobile number">
                                        <div class="field-message" id="phone_error"></div>
                                    </div>
                                </div>

                                <div class="step1-logo-col">
                                    <img src="{{ asset('images/plyrcardlogo.png') }}" alt="" class="watermark">
                                </div>
                            </div>
                        </div>

                        <div class="step-cta">
                            <button type="button" class="btn" id="nextBtn1">Next</button>
                        </div>
                    </div>

                    <div class="step-panel" data-step="2">
                        <div class="panel-scroll">
                            <div class="field">
                                <label class="label" for="sport">Sport</label>
                                <select class="select" id="sport" name="sport" required>
                                    <option value="">Select One</option>
                                    @foreach ($sportPositions as $sportKey => $positions)
                                        <option value="{{ $sportKey }}" {{ old('sport') === $sportKey ? 'selected' : '' }}>
                                            {{ str($sportKey)->replace('_', ' ')->title() }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="field-message" id="sport_error"></div>
                            </div>

                            <div class="field" id="position_field">
                                <label class="label" for="position_select">Position</label>
                                <select class="select" id="position_select" aria-label="Position"></select>
                                <div class="field-message" id="position_error"></div>
                                <div class="chips-wrap" id="positionChips"></div>
                                <input type="hidden" name="position[]" id="position_values" value="">
                            </div>

                            <div class="field" id="league_field">
                                <label class="label">League</label>
                                <div id="leagueSelectRoot"></div>
                                <div class="field-message" id="league_error"></div>
                                <div class="manual-input-wrap" id="leagueManualWrap">
                                    <input class="manual-input" type="text" id="league_name_manual" name="league_name_manual" value="{{ old('league_name_manual') }}" placeholder="Enter league name">
                                    <div class="manual-input-actions"><button type="button" class="manual-toggle-back" data-restore-select="league">Use dropdown instead</button></div>
                                </div>
                                <input type="hidden" id="league_id" name="league_id" value="{{ old('league_id') }}">
                            </div>

                            <div class="field" id="club_field">
                                <label class="label">Club</label>
                                <div id="clubSelectRoot"></div>
                                <div class="field-message" id="club_error"></div>
                                <div class="manual-input-wrap" id="clubManualWrap">
                                    <input class="manual-input" type="text" id="club_name_manual" name="club_name_manual" value="{{ old('club_name_manual') }}" placeholder="Enter club name">
                                </div>
                                <input type="hidden" id="club_id" name="club_id" value="{{ old('club_id') }}">
                            </div>

                            <div class="field" id="team_field">
                                <label class="label">Team</label>
                                <div id="teamSelectRoot"></div>
                                <div class="field-message" id="team_error"></div>
                                <div class="manual-input-wrap" id="teamManualWrap">
                                    <input class="manual-input" type="text" id="team_name_manual" name="team_name_manual" value="{{ old('team_name_manual') }}" placeholder="Enter team name">
                                </div>
                                <input type="hidden" id="team_id" name="team_id" value="{{ old('team_id') }}">
                            </div>
                        </div>

                        <div class="step-cta">
                            <button type="button" class="btn" id="nextBtn2">Next</button>
                        </div>
                    </div>

                    <div class="step-panel" data-step="3">
                        <div class="panel-scroll with-sticky-cta" id="imageStepScroll">
                            <div class="field" id="portrait_images_field">
                                <label class="label" for="portrait_images">Profile Image</label>
                                <input class="file-input" type="file" id="portrait_images" name="portrait_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <div class="image-preview-grid" id="portrait_images_preview"></div>
                                <div class="file-warning-list" id="portrait_images_feedback"></div>
                            </div>

                            <div class="field" id="action_images_field">
                                <label class="label" for="action_images">Action Image</label>
                                <input class="file-input" type="file" id="action_images" name="action_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <div class="image-preview-grid" id="action_images_preview"></div>
                                <div class="file-warning-list" id="action_images_feedback"></div>
                            </div>

                            <div class="sample-block">
                                <h3 class="sample-title">Sample (Click Here)</h3>
                                <p class="sample-copy">Start with a strong headshot, then add a few action shots so we have options for a more custom image if needed.</p>
                                <p class="sample-copy">Please upload a clear profile headshot and a few action shots to help us build the best visual version of your PLYRCARD.</p>
                                <p class="sample-copy">Add a quality headshot for your main profile image, plus a few action shots for possible custom image creation.</p>
                            </div>
                        </div>

                        <div class="step-cta sticky">
                            <button type="button" class="btn" id="nextBtn3">Next</button>
                        </div>
                    </div>

                    <div class="final-screen" data-step="4">
                        <div class="panel-scroll">
                            <h1 class="final-title">Now Make It Unforgettable</h1>
                            <p class="final-text">You’ve built the foundation, now add the final touches that make your PLYRCARD stand out. Finish it now or come back later, but don’t skip the step that brings it all together.</p>
                            <img src="{{ asset('images/plyrcardlogo.png') }}" alt="" class="final-logo">
                        </div>

                        <div class="step-cta">
                            <button type="submit" class="btn" id="submitBtn">Submit</button>
                        </div>
                    </div>
                </section>
            </form>
        @endif
    </div>
</div>

<script>
window.plyrIntakeData = {
    sportPositions: @json($sportPositions ?? []),
    leagueDirectory: @json($leagueDirectory ?? []),
    clubDirectory: @json($clubDirectory ?? []),
    teamDirectory: @json($teamDirectory ?? []),
    oldLeagueId: @json(old('league_id')),
    oldClubId: @json(old('club_id')),
    oldTeamId: @json(old('team_id')),
    oldPositions: @json(old('position', [])),
    oldManualLeague: @json(old('league_name_manual')),
    oldManualClub: @json(old('club_name_manual')),
    oldManualTeam: @json(old('team_name_manual')),
    stepFieldMap: @json($stepFieldMap ?? []),
    serverErrors: @json($errors->getMessages()),
};
</script>
<script>
(function(){
    const data = window.plyrIntakeData || {};
    const sportPositions = data.sportPositions || {};
    const leagueDirectory = data.leagueDirectory || [];
    const clubDirectory = data.clubDirectory || [];
    const teamDirectory = data.teamDirectory || [];
    const oldLeagueId = data.oldLeagueId || '';
    const oldClubId = data.oldClubId || '';
    const oldTeamId = data.oldTeamId || '';
    const oldPositions = Array.isArray(data.oldPositions) ? data.oldPositions : [];
    const stepFieldMap = data.stepFieldMap || {};
    const serverErrors = data.serverErrors || {};
    const draftKey = 'plyrcard_intake_exact_mobile_v12';
    const ADD_NEW_VALUE = '__add_new__';
    let currentStep = 0;
    let selectedPositions = [];
    let leagueApi = null;
    let clubApi = null;
    let teamApi = null;

    const stepMeta = {
        1: { topbar: 'PLYRPROFILE', title: 'Begin with the basics and lay the foundation for your PLYRCARD.' },
        2: { topbar: 'In Your Element', title: 'Set the stage for your profile by entering the sport you play and the team you represent.' },
        3: { topbar: 'Show Your Story', title: 'Add your images so your PLYRCARD feels complete, personal, and ready to share.' },
        4: { topbar: 'Show Your Story', title: 'Now make it unforgettable.' }
    };

    const fieldAliases = {
        league_other: 'league',
        league_name_manual: 'league',
        league_id: 'league',
        club_other: 'club',
        club_name_manual: 'club',
        club_id: 'club',
        team_other: 'team',
        team_name_manual: 'team',
        team_id: 'team',
        action_images: 'action_images',
        'action_images.0': 'action_images',
        portrait_images: 'portrait_images',
        'portrait_images.0': 'portrait_images',
        position: 'position',
        sport: 'sport',
        gender: 'gender',
    };

    function $(selector, scope){ return (scope || document).querySelector(selector); }
    function $all(selector, scope){ return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }
    function safe(v){ return String(v == null ? '' : v).trim(); }

    function hideAllScreens(){
        const intro = $('#introScreen');
        const form = $('#formScreen');
        const thanks = $('#thanksScreen');
        if (intro) intro.style.display = 'none';
        if (form) form.style.display = 'none';
        if (thanks) thanks.style.display = 'none';
    }

    function showIntro(){
        currentStep = 0;
        hideAllScreens();
        const intro = $('#introScreen');
        if (intro) intro.style.display = 'flex';
        animateProgress(25);
    }

    function getStepBody(step){
        return document.querySelector('.step-panel[data-step="' + step + '"]') || document.querySelector('.final-screen[data-step="' + step + '"]');
    }

    function showStep(step){
        currentStep = step;
        hideAllScreens();
        const form = $('#formScreen');
        if (form) form.style.display = 'flex';

        $all('.step-panel').forEach(function(panel){ panel.classList.remove('active'); panel.scrollTop = 0; });
        $all('.final-screen').forEach(function(panel){ panel.classList.remove('active'); panel.scrollTop = 0; });

        const target = getStepBody(step);
        if (target) target.classList.add('active');

        $('.topbar').style.display = 'flex';
        $('#topbarTitle').textContent = stepMeta[step].topbar;

        if (step <= 3){
            $('#progressBand').style.display = 'grid';
            $('#progressTitle').textContent = stepMeta[step].title;
        } else {
            $('#progressBand').style.display = 'none';
        }

        updateLiveProgress();
        saveDraft();
    }

    function animateProgress(percent){
        const ring = $('#progressRing');
        const valueEl = $('#progressValue');
        if (!ring || !valueEl) return;
        const current = Number(ring.dataset.percent || 0);
        const target = Math.max(0, Math.min(100, Number(percent) || 0));
        const duration = 280;
        const start = performance.now();
        function tick(now){
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            const value = current + ((target - current) * eased);
            ring.style.setProperty('--ring-angle', (value * 3.6) + 'deg');
            valueEl.textContent = Math.round(value) + '%';
            if (t < 1) requestAnimationFrame(tick);
        }
        ring.dataset.percent = String(target);
        requestAnimationFrame(tick);
    }

    function validateEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(safe(v)); }
    function digitsOnly(v){ return safe(v).replace(/\D/g, ''); }
    function validatePhone(v){ const len = digitsOnly(v).length; return !safe(v) || (len >= 7 && len <= 15); }

    function getFieldContainer(fieldId){
        if (fieldId === 'gender') return document.getElementById('gender_field');
        if (fieldId === 'position') return document.getElementById('position_field');
        if (fieldId === 'league') return document.getElementById('league_field');
        if (fieldId === 'club') return document.getElementById('club_field');
        if (fieldId === 'team') return document.getElementById('team_field');
        if (fieldId === 'portrait_images') return document.getElementById('portrait_images_field');
        if (fieldId === 'action_images') return document.getElementById('action_images_field');
        const input = document.getElementById(fieldId);
        return input ? input.closest('.field') : null;
    }

    function setFieldError(fieldId, message){
        const canonical = fieldAliases[fieldId] || fieldId;
        const container = getFieldContainer(canonical);
        const el = document.getElementById(canonical + '_error') || document.getElementById(canonical + '_feedback');
        if (el){
            el.textContent = message || '';
            el.classList.toggle('error', !!message);
            el.classList.toggle('visible', !!message);
        }
        if (container) container.classList.toggle('is-invalid', !!message);
    }

    function appendFieldError(fieldId, message){
        const canonical = fieldAliases[fieldId] || fieldId;
        const el = document.getElementById(canonical + '_error') || document.getElementById(canonical + '_feedback');
        const container = getFieldContainer(canonical);
        if (el){
            const existing = safe(el.textContent);
            el.textContent = existing ? (existing + ' ' + message) : message;
            el.classList.add('error');
            el.classList.add('visible');
        }
        if (container) container.classList.add('is-invalid');
    }

    function clearKnownErrors(){
        ['first_name','last_name','personal_email','phone','gender','sport','position','league','club','team','portrait_images','action_images'].forEach(function(key){
            setFieldError(key, '');
        });
    }

    function getSelectedGender(){ return safe(document.querySelector('input[name="gender"]:checked') && document.querySelector('input[name="gender"]:checked').value).toLowerCase(); }
    function getSelectedSport(){ return safe($('#sport') && $('#sport').value).toLowerCase(); }

    function mapLeagueOptions(){
        const gender = getSelectedGender();
        const sport = getSelectedSport();
        const filtered = leagueDirectory.filter(function(league){
            const lg = safe(league.gender).toLowerCase();
            const ls = safe(league.sport).toLowerCase();
            return (!gender || !lg || lg === gender || lg === 'coed') && (!sport || !ls || ls === sport);
        }).map(function(league){
            return { id: String(league.id), label: league.name, subtitle: [league.gender_label, league.sport_label].filter(Boolean).join(' • '), logo_url: null };
        });
        return [
            { id: '', label: 'Select One', subtitle: '', logo_url: null },
            { id: ADD_NEW_VALUE, label: 'Add New', subtitle: 'Enter league manually', logo_url: null }
        ].concat(filtered);
    }

    function mapClubOptions(){
        const gender = getSelectedGender();
        const sport = getSelectedSport();
        const leagueId = safe($('#league_id') && $('#league_id').value);
        const filtered = clubDirectory.filter(function(club){
            const cg = safe(club.gender).toLowerCase();
            const cs = safe(club.sport).toLowerCase();
            return (!leagueId || leagueId === ADD_NEW_VALUE || String(club.league_id) === leagueId) && (!gender || !cg || cg === gender || cg === 'coed') && (!sport || !cs || cs === sport);
        }).map(function(club){
            return { id: String(club.id), label: club.name, subtitle: [club.league_name].filter(Boolean).join(' • '), logo_url: club.logo_url || null };
        });
        return [
            { id: '', label: 'Select One', subtitle: '', logo_url: null },
            { id: ADD_NEW_VALUE, label: 'Add New', subtitle: 'Enter club manually', logo_url: null }
        ].concat(filtered);
    }

    function mapTeamOptions(){
        const gender = getSelectedGender();
        const sport = getSelectedSport();
        const clubId = safe($('#club_id') && $('#club_id').value);
        const filtered = teamDirectory.filter(function(team){
            const tg = safe(team.gender).toLowerCase();
            const ts = safe(team.sport).toLowerCase();
            return (!clubId || clubId === ADD_NEW_VALUE || String(team.club_id) === clubId) && (!gender || !tg || tg === gender || tg === 'coed') && (!sport || !ts || ts === sport);
        }).map(function(team){
            return { id: String(team.id), label: team.name, subtitle: [team.club_name, team.league_name].filter(Boolean).join(' • '), logo_url: team.club_logo_url || null };
        });
        return [
            { id: '', label: 'Select One', subtitle: '', logo_url: null },
            { id: ADD_NEW_VALUE, label: 'Add New', subtitle: 'Enter team manually', logo_url: null }
        ].concat(filtered);
    }

    function iconSearch(){ return '<svg class="search-select-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>'; }
    function iconCaret(){ return '<svg class="search-select-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 9 6 6 6-6"></path></svg>'; }

    function toggleManualField(type, visible){
        const wrap = $('#' + type + 'ManualWrap');
        const root = $('#' + type + 'SelectRoot .search-select');
        if (wrap) wrap.classList.toggle('visible', !!visible);
        if (root) root.classList.toggle('is-hidden', !!visible);
    }

    function setHierarchyAddNewMode(enabled){
        if (enabled){
            $('#league_id').value = ADD_NEW_VALUE;
            $('#club_id').value = ADD_NEW_VALUE;
            $('#team_id').value = ADD_NEW_VALUE;
            toggleManualField('league', true);
            toggleManualField('club', true);
            toggleManualField('team', true);
        } else {
            $('#league_id').value = '';
            $('#club_id').value = '';
            $('#team_id').value = '';
            $('#league_name_manual').value = '';
            $('#club_name_manual').value = '';
            $('#team_name_manual').value = '';
            toggleManualField('league', false);
            toggleManualField('club', false);
            toggleManualField('team', false);
        }
    }

    function buildSearchSelect(config){
        const root = document.getElementById(config.rootId);
        const hiddenInput = document.getElementById(config.hiddenInputId);
        if (!root || !hiddenInput) return null;

        root.innerHTML = '' +
            '<div class="search-select">' +
                '<button type="button" class="search-select-control">' +
                    '<span class="search-select-value"><span class="search-select-placeholder">' + config.placeholder + '</span></span>' +
                    iconCaret() +
                '</button>' +
                '<div class="search-select-dropdown">' +
                    '<div class="search-select-search">' +
                        iconSearch() +
                        '<input type="text" placeholder="Search...">' +
                    '</div>' +
                    '<div class="search-select-list"></div>' +
                '</div>' +
            '</div>';

        const wrapper = $('.search-select', root);
        const control = $('.search-select-control', root);
        const searchInput = $('.search-select-search input', root);
        const list = $('.search-select-list', root);

        function isDisabled(){ return typeof config.disabledWhen === 'function' ? !!config.disabledWhen() : false; }
        const parentField = root.closest('.field');
        function syncOpenState(isOpen){ wrapper.classList.toggle('open', !!isOpen); if (parentField) parentField.classList.toggle('field-open', !!isOpen); }

        function setDisplay(item){
            const valueEl = $('.search-select-value', root);
            if (!item || !safe(item.id)){ valueEl.innerHTML = '<span class="search-select-placeholder">' + config.placeholder + '</span>'; return; }
            if (item.id === ADD_NEW_VALUE){ valueEl.innerHTML = '<span class="search-select-meta">Add New</span>'; return; }
            const logo = item.logo_url ? '<img src="' + item.logo_url + '" class="search-select-logo" alt="">' : '';
            valueEl.innerHTML = logo + '<span class="search-select-meta">' + item.label + '</span>';
        }

        function render(){
            wrapper.classList.toggle('is-disabled', isDisabled());
            if (isDisabled()) syncOpenState(false);
            const options = typeof config.getOptions === 'function' ? (config.getOptions() || []) : [];
            const query = safe(searchInput.value).toLowerCase();
            const filtered = options.filter(function(item){ return (safe(item.label) + ' ' + safe(item.subtitle)).toLowerCase().indexOf(query) !== -1; });

            list.innerHTML = '';
            if (!filtered.length){
                list.innerHTML = '<div class="search-select-empty">No results found.</div>';
            } else {
                filtered.forEach(function(item){
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'search-select-option';
                    btn.innerHTML =
                        (item.logo_url ? '<img src="' + item.logo_url + '" class="search-select-logo" alt="">' : '<span class="search-select-logo"></span>') +
                        '<span class="search-select-option-copy">' +
                            '<span class="search-select-option-title">' + item.label + '</span>' +
                            (item.subtitle ? '<span class="search-select-option-subtitle">' + item.subtitle + '</span>' : '') +
                        '</span>';
                    btn.addEventListener('click', function(){
                        hiddenInput.value = item.id;
                        setDisplay(item);
                        syncOpenState(false);
                        searchInput.value = '';
                        if (typeof config.onChange === 'function') config.onChange(item);
                        saveDraft();
                        updateLiveProgress();
                    });
                    list.appendChild(btn);
                });
            }

            const selected = options.find(function(item){ return String(item.id) === String(hiddenInput.value); });
            setDisplay(selected || null);
        }

        control.addEventListener('click', function(e){
            e.preventDefault();
            if (isDisabled()) return;
            document.querySelectorAll('.search-select.open').forEach(function(openEl){
                if (openEl !== wrapper){
                    openEl.classList.remove('open');
                    const field = openEl.closest('.field');
                    if (field) field.classList.remove('field-open');
                }
            });
            const willOpen = !wrapper.classList.contains('open');
            syncOpenState(willOpen);
            if (willOpen) searchInput.focus();
            render();
        });

        searchInput.addEventListener('input', render);
        document.addEventListener('click', function(e){ if (!root.contains(e.target)) syncOpenState(false); });
        render();
        return { render: render };
    }

    function renderPositions(){
        const sport = safe($('#sport') && $('#sport').value);
        const select = $('#position_select');
        if (!select) return;
        const current = selectedPositions.slice();
        select.innerHTML = '<option value="">Select position(s)</option>';
        if (!sport || !sportPositions[sport]) return;
        Object.keys(sportPositions[sport]).forEach(function(value){
            const option = document.createElement('option');
            option.value = value;
            option.textContent = sportPositions[sport][value];
            select.appendChild(option);
        });
        selectedPositions = current.filter(function(v){ return sportPositions[sport] && sportPositions[sport][v]; });
        updatePositionField();
    }

    function updatePositionField(){
        const chips = $('#positionChips');
        const hidden = $('#position_values');
        const sport = safe($('#sport') && $('#sport').value);
        if (!chips || !hidden) return;
        chips.innerHTML = '';
        hidden.value = selectedPositions.join('|');
        selectedPositions.forEach(function(value){
            const label = sportPositions[sport] && sportPositions[sport][value] ? sportPositions[sport][value] : value;
            const chip = document.createElement('span');
            chip.className = 'chip';
            chip.innerHTML = '<span>' + label + '</span><button type="button" aria-label="Remove">×</button>';
            $('button', chip).addEventListener('click', function(){
                selectedPositions = selectedPositions.filter(function(item){ return item !== value; });
                updatePositionField();
                saveDraft();
                updateLiveProgress();
            });
            chips.appendChild(chip);
        });
    }

    function syncFileInputFiles(input, files){
        if (!input) return;
        const dt = new DataTransfer();
        (files || []).forEach(function(file){ dt.items.add(file); });
        input.files = dt.files;
    }

    function renderImagePreviews(fieldId){
        const input = document.getElementById(fieldId);
        const preview = document.getElementById(fieldId + '_preview');
        if (!input || !preview) return;
        preview.innerHTML = '';
        Array.prototype.slice.call(input.files || []).forEach(function(file, index){
            if (!file.type.match(/^image\//)) return;
            const card = document.createElement('div');
            card.className = 'image-preview-card';
            const img = document.createElement('img');
            img.alt = file.name;
            const reader = new FileReader();
            reader.onload = function(e){ img.src = e.target.result; };
            reader.readAsDataURL(file);
            const overlay = document.createElement('div');
            overlay.className = 'image-preview-overlay';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'image-preview-remove';
            btn.textContent = 'Remove';
            btn.addEventListener('click', function(){
                const files = Array.prototype.slice.call(input.files || []);
                files.splice(index, 1);
                syncFileInputFiles(input, files);
                renderImagePreviews(fieldId);
                validateFilesLive(fieldId);
                saveDraft();
                updateLiveProgress();
            });
            overlay.appendChild(btn);
            card.appendChild(img);
            card.appendChild(overlay);
            preview.appendChild(card);
        });
    }

    function validateFilesLive(fieldId){
        const input = document.getElementById(fieldId);
        const feedback = document.getElementById(fieldId + '_feedback');
        if (!input || !feedback) return true;
        const errors = [];
        Array.prototype.slice.call(input.files || []).forEach(function(file){
            if (file.size > 5 * 1024 * 1024) errors.push(file.name + ' is larger than 5MB. Please choose a smaller image.');
        });
        renderImagePreviews(fieldId);
        const wrap = input.closest('.field');
        if (errors.length){
            feedback.textContent = errors.join(' ');
            feedback.classList.add('visible');
            if (wrap) wrap.classList.add('is-invalid');
            return false;
        }
        feedback.textContent = '';
        feedback.classList.remove('visible');
        if (wrap) wrap.classList.remove('is-invalid');
        return true;
    }

    function updateHierarchyManualStates(){
        const leagueVal = safe($('#league_id') && $('#league_id').value);
        const clubVal = safe($('#club_id') && $('#club_id').value);
        const teamVal = safe($('#team_id') && $('#team_id').value);
        if (leagueVal === ADD_NEW_VALUE){
            toggleManualField('league', true);
            toggleManualField('club', true);
            toggleManualField('team', true);
            return;
        }
        toggleManualField('league', false);
        toggleManualField('club', clubVal === ADD_NEW_VALUE);
        toggleManualField('team', teamVal === ADD_NEW_VALUE);
    }

    function validateStep(step){
        if (step === 1){
            const first = safe($('#first_name') && $('#first_name').value);
            const last = safe($('#last_name') && $('#last_name').value);
            const gender = safe(document.querySelector('input[name="gender"]:checked') && document.querySelector('input[name="gender"]:checked').value);
            const email = safe($('#personal_email') && $('#personal_email').value);
            const phone = safe($('#phone') && $('#phone').value);
            setFieldError('first_name', first ? '' : 'First name is required.');
            setFieldError('last_name', last ? '' : 'Last name is required.');
            setFieldError('gender', gender ? '' : 'Select a gender.');
            setFieldError('personal_email', !email ? 'Email is required.' : (validateEmail(email) ? '' : 'Enter a valid email address.'));
            setFieldError('phone', !phone ? '' : (validatePhone(phone) ? '' : 'Enter a valid phone number.'));
            return !!(first && last && gender && email && validateEmail(email) && validatePhone(phone));
        }

        if (step === 2){
            const leagueVal = safe($('#league_id') && $('#league_id').value);
            const clubVal = safe($('#club_id') && $('#club_id').value);
            const teamVal = safe($('#team_id') && $('#team_id').value);
            const manualMode = leagueVal === ADD_NEW_VALUE;
            const leagueOk = manualMode ? safe($('#league_name_manual') && $('#league_name_manual').value) : !!leagueVal;
            const clubOk = manualMode ? safe($('#club_name_manual') && $('#club_name_manual').value) : !!clubVal;
            const teamOk = manualMode ? safe($('#team_name_manual') && $('#team_name_manual').value) : !!teamVal;
            const sportOk = !!safe($('#sport') && $('#sport').value);
            const posOk = !!selectedPositions.length;
            setFieldError('sport', sportOk ? '' : 'Select a sport.');
            setFieldError('position', posOk ? '' : 'Select at least one position.');
            setFieldError('league', leagueOk ? '' : (manualMode ? 'Enter a league name.' : 'Select a league.'));
            setFieldError('club', clubOk ? '' : (manualMode ? 'Enter a club name.' : 'Select a club.'));
            setFieldError('team', teamOk ? '' : (manualMode ? 'Enter a team name.' : 'Select a team.'));
            return sportOk && posOk && leagueOk && clubOk && teamOk;
        }

        if (step === 3){
            return validateFilesLive('portrait_images') && validateFilesLive('action_images');
        }
        return true;
    }

    function stepOnePercent(){
        let filled = 0;
        const total = 5;
        if (safe($('#first_name') && $('#first_name').value)) filled += 1;
        if (safe($('#last_name') && $('#last_name').value)) filled += 1;
        if (safe(document.querySelector('input[name="gender"]:checked') && document.querySelector('input[name="gender"]:checked').value)) filled += 1;
        if (validateEmail(safe($('#personal_email') && $('#personal_email').value))) filled += 1;
        if (validatePhone(safe($('#phone') && $('#phone').value)) && safe($('#phone') && $('#phone').value)) filled += 1;
        return 25 + Math.round((filled / total) * 25);
    }

    function stepTwoPercent(){
        const leagueVal = safe($('#league_id') && $('#league_id').value);
        const manualMode = leagueVal === ADD_NEW_VALUE;
        let filled = 0;
        const total = 5;
        if (safe($('#sport') && $('#sport').value)) filled += 1;
        if (selectedPositions.length) filled += 1;
        if (manualMode ? safe($('#league_name_manual') && $('#league_name_manual').value) : leagueVal) filled += 1;
        if (manualMode ? safe($('#club_name_manual') && $('#club_name_manual').value) : safe($('#club_id') && $('#club_id').value)) filled += 1;
        if (manualMode ? safe($('#team_name_manual') && $('#team_name_manual').value) : safe($('#team_id') && $('#team_id').value)) filled += 1;
        return 50 + Math.round((filled / total) * 25);
    }

    function stepThreePercent(){
        let filled = 0;
        const total = 2;
        if ((document.getElementById('portrait_images')?.files || []).length) filled += 1;
        if ((document.getElementById('action_images')?.files || []).length) filled += 1;
        return 75 + Math.round((filled / total) * 25);
    }

    function getCurrentProgressPercent(){
        if (currentStep === 1) return stepOnePercent();
        if (currentStep === 2) return stepTwoPercent();
        if (currentStep === 3) return stepThreePercent();
        if (currentStep >= 4) return 100;
        return 25;
    }

    function updateLiveProgress(){ animateProgress(getCurrentProgressPercent()); }

    function firstErrorStep(){
        const keys = Object.keys(serverErrors || {});
        if (!keys.length) return null;
        for (let i = 0; i < keys.length; i++){
            const field = keys[i];
            for (const step in stepFieldMap){
                const list = stepFieldMap[step] || [];
                if (list.indexOf(field) !== -1 || list.indexOf((field.split('.')[0] + '.*')) !== -1 || list.indexOf(field.split('.')[0]) !== -1) {
                    return Number(step);
                }
            }
            const canonical = fieldAliases[field] || field.split('.')[0];
            if (['first_name','last_name','personal_email','phone','gender'].indexOf(canonical) !== -1) return 1;
            if (['sport','position','league','club','team'].indexOf(canonical) !== -1) return 2;
            if (['portrait_images','action_images'].indexOf(canonical) !== -1) return 3;
        }
        return 1;
    }

    function applyServerErrors(){
        clearKnownErrors();
        const keys = Object.keys(serverErrors || {});
        keys.forEach(function(field){
            const messages = serverErrors[field] || [];
            if (!messages.length) return;
            appendFieldError(field, messages[0]);
        });
    }

    function scrollToFirstServerError(){
        const keys = Object.keys(serverErrors || {});
        if (!keys.length) return;
        const first = fieldAliases[keys[0]] || keys[0].split('.')[0];
        const container = getFieldContainer(first);
        if (container) {
            requestAnimationFrame(function(){
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }
    }

    function saveDraft(){
        try{
            const payload = { currentStep: currentStep, selectedPositions: selectedPositions };
            $all('#playerIntakeForm input, #playerIntakeForm select').forEach(function(field){
                if (!field.name || field.type === 'file' || field.name === '_token') return;
                if (field.type === 'radio'){
                    if (field.checked) payload[field.name] = field.value;
                    return;
                }
                payload[field.name] = field.value;
            });
            localStorage.setItem(draftKey, JSON.stringify(payload));
        } catch (e){}
    }

    function readDraft(){
        try{ return JSON.parse(localStorage.getItem(draftKey) || '{}'); }
        catch (e){ return {}; }
    }

    function restoreDraft(){
        const payload = readDraft();
        Object.keys(payload).forEach(function(name){
            if (name === 'currentStep' || name === 'selectedPositions') return;
            const selector = '#playerIntakeForm [name="' + CSS.escape(name) + '"]';
            const fields = document.querySelectorAll(selector);
            if (!fields.length) return;
            Array.prototype.slice.call(fields).forEach(function(field){
                if (field.type === 'radio'){
                    field.checked = String(field.value) === String(payload[name]);
                } else {
                    field.value = payload[name] == null ? '' : payload[name];
                }
            });
        });
        if (Array.isArray(payload.selectedPositions)) selectedPositions = payload.selectedPositions.slice();
        return payload;
    }

    function clearDraft(){ try{ localStorage.removeItem(draftKey); } catch(e){} }

    function formatPhoneInputValue(value) {
        let raw = String(value || '').replace(/[^\d+]/g, '');
        const hasPlus = raw.indexOf('+') === 0;
        let digits = raw.replace(/\D/g, '').slice(0, 15);
        if (!digits) return hasPlus ? '+' : '';
        if (hasPlus) {
            if (digits.length <= 3) return '+' + digits;
            if (digits.length <= 6) return '+' + digits.slice(0,3) + ' ' + digits.slice(3);
            if (digits.length <= 10) return '+' + digits.slice(0,3) + ' ' + digits.slice(3,6) + ' ' + digits.slice(6);
            return '+' + digits.slice(0,3) + ' ' + digits.slice(3,6) + ' ' + digits.slice(6,10) + ' ' + digits.slice(10);
        }
        if (digits.length <= 4) return digits;
        if (digits.length <= 7) return digits.slice(0,4) + ' ' + digits.slice(4);
        if (digits.length <= 11) return digits.slice(0,4) + ' ' + digits.slice(4,7) + ' ' + digits.slice(7);
        return digits.slice(0,4) + ' ' + digits.slice(4,8) + ' ' + digits.slice(8,12) + ' ' + digits.slice(12);
    }

    document.addEventListener('DOMContentLoaded', function(){
        if (document.getElementById('thanksScreen')) { clearDraft(); return; }

        const draft = restoreDraft();
        if (!selectedPositions.length && oldPositions.length) selectedPositions = oldPositions.slice();

        renderPositions();
        updatePositionField();

        leagueApi = buildSearchSelect({
            rootId: 'leagueSelectRoot',
            hiddenInputId: 'league_id',
            placeholder: 'Select One',
            getOptions: mapLeagueOptions,
            onChange: function(item){
                $('#club_id').value = '';
                $('#team_id').value = '';
                if (item && item.id === ADD_NEW_VALUE){
                    setHierarchyAddNewMode(true);
                } else {
                    if (item) $('#league_name_manual').value = '';
                    $('#club_name_manual').value = '';
                    $('#team_name_manual').value = '';
                    toggleManualField('league', false);
                    toggleManualField('club', false);
                    toggleManualField('team', false);
                }
                clubApi && clubApi.render();
                teamApi && teamApi.render();
            }
        });

        clubApi = buildSearchSelect({
            rootId: 'clubSelectRoot',
            hiddenInputId: 'club_id',
            placeholder: 'Select One',
            getOptions: mapClubOptions,
            disabledWhen: function(){ return !safe($('#league_id') && $('#league_id').value) || safe($('#league_id') && $('#league_id').value) === ADD_NEW_VALUE; },
            onChange: function(item){
                $('#team_id').value = '';
                if (item && item.id !== ADD_NEW_VALUE) $('#club_name_manual').value = '';
                $('#team_name_manual').value = '';
                toggleManualField('club', item && item.id === ADD_NEW_VALUE);
                toggleManualField('team', false);
                teamApi && teamApi.render();
            }
        });

        teamApi = buildSearchSelect({
            rootId: 'teamSelectRoot',
            hiddenInputId: 'team_id',
            placeholder: 'Select One',
            getOptions: mapTeamOptions,
            disabledWhen: function(){ return !safe($('#club_id') && $('#club_id').value) || safe($('#club_id') && $('#club_id').value) === ADD_NEW_VALUE; },
            onChange: function(item){
                if (item && item.id !== ADD_NEW_VALUE) $('#team_name_manual').value = '';
                toggleManualField('team', item && item.id === ADD_NEW_VALUE);
            }
        });

        if (oldLeagueId) $('#league_id').value = oldLeagueId;
        if (oldClubId) $('#club_id').value = oldClubId;
        if (oldTeamId) $('#team_id').value = oldTeamId;
        if (data.oldManualLeague) $('#league_name_manual').value = data.oldManualLeague;
        if (data.oldManualClub) $('#club_name_manual').value = data.oldManualClub;
        if (data.oldManualTeam) $('#team_name_manual').value = data.oldManualTeam;

        leagueApi && leagueApi.render();
        clubApi && clubApi.render();
        teamApi && teamApi.render();
        updateHierarchyManualStates();
        renderImagePreviews('portrait_images');
        renderImagePreviews('action_images');
        validateFilesLive('portrait_images');
        validateFilesLive('action_images');
        updateLiveProgress();

        const startBtn = $('#startBtn');
        if (startBtn){
            startBtn.addEventListener('click', function(e){
                e.preventDefault();
                showStep(1);
                updateLiveProgress();
            });
        }

        $('#backBtn') && $('#backBtn').addEventListener('click', function(){
            if (currentStep === 1) showIntro();
            else showStep(currentStep - 1);
        });

        $('#nextBtn1') && $('#nextBtn1').addEventListener('click', function(){
            if (!validateStep(1)) return;
            showStep(2);
        });

        $('#nextBtn2') && $('#nextBtn2').addEventListener('click', function(){
            if (!validateStep(2)) return;
            showStep(3);
        });

        $('#nextBtn3') && $('#nextBtn3').addEventListener('click', function(){
            if (!validateStep(3)) return;
            showStep(4);
        });

        $('#sport') && $('#sport').addEventListener('change', function(){
            selectedPositions = [];
            renderPositions();
            updatePositionField();
            $('#league_id').value = '';
            $('#club_id').value = '';
            $('#team_id').value = '';
            $('#league_name_manual').value = '';
            $('#club_name_manual').value = '';
            $('#team_name_manual').value = '';
            toggleManualField('league', false);
            toggleManualField('club', false);
            toggleManualField('team', false);
            leagueApi && leagueApi.render();
            clubApi && clubApi.render();
            teamApi && teamApi.render();
            saveDraft();
            updateLiveProgress();
        });

        $all('input[name="gender"]').forEach(function(radio){
            radio.addEventListener('change', function(){
                $('#league_id').value = '';
                $('#club_id').value = '';
                $('#team_id').value = '';
                $('#league_name_manual').value = '';
                $('#club_name_manual').value = '';
                $('#team_name_manual').value = '';
                toggleManualField('league', false);
                toggleManualField('club', false);
                toggleManualField('team', false);
                leagueApi && leagueApi.render();
                clubApi && clubApi.render();
                teamApi && teamApi.render();
                saveDraft();
                updateLiveProgress();
            });
        });

        $('#position_select') && $('#position_select').addEventListener('change', function(){
            const value = safe(this.value);
            if (!value) return;
            if (selectedPositions.indexOf(value) === -1) selectedPositions.push(value);
            this.value = '';
            updatePositionField();
            saveDraft();
            updateLiveProgress();
        });

        $('#portrait_images') && $('#portrait_images').addEventListener('change', function(){ validateFilesLive('portrait_images'); saveDraft(); updateLiveProgress(); });
        $('#action_images') && $('#action_images').addEventListener('change', function(){ validateFilesLive('action_images'); saveDraft(); updateLiveProgress(); });

        $all('#playerIntakeForm input, #playerIntakeForm select').forEach(function(el){
            if (el.type === 'file') return;
            el.addEventListener('input', function(){ saveDraft(); updateLiveProgress(); });
            el.addEventListener('change', function(){ saveDraft(); updateLiveProgress(); });
        });

        $all('.phone-input').forEach(function(input){
            input.addEventListener('input', function(){
                input.value = formatPhoneInputValue(input.value);
                saveDraft();
                updateLiveProgress();
            });
        });

        $all('[data-restore-select="league"]').forEach(function(btn){
            btn.addEventListener('click', function(){
                setHierarchyAddNewMode(false);
                leagueApi && leagueApi.render();
                clubApi && clubApi.render();
                teamApi && teamApi.render();
                updateLiveProgress();
                saveDraft();
            });
        });

        ['first_name','last_name','personal_email','phone'].forEach(function(id){
            const input = document.getElementById(id);
            if (!input) return;
            input.addEventListener('blur', function(){ validateStep(1); });
        });

        ['league_name_manual','club_name_manual','team_name_manual'].forEach(function(id){
            const input = document.getElementById(id);
            if (!input) return;
            input.addEventListener('input', function(){ updateLiveProgress(); saveDraft(); });
        });

        $('#playerIntakeForm') && $('#playerIntakeForm').addEventListener('submit', function(){ clearDraft(); });

        applyServerErrors();

        const errorStep = firstErrorStep();
        if (errorStep) {
            showStep(Math.min(Number(errorStep), 4));
            scrollToFirstServerError();
        } else if (draft.currentStep) {
            showStep(Math.min(Number(draft.currentStep), 4));
        } else {
            showIntro();
        }
    });
})();
</script>
</body>
</html>