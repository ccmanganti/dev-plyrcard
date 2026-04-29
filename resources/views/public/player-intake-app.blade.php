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
            position:relative;
            overflow:hidden;
            box-shadow:0 18px 50px rgba(0,0,0,.14);
            background:#000;
        }

        #contact_field{
            display:flex;
        }

        #gender_field.field{
            display:flex;
            flex-direction:column;
            gap:10px;
        }

        #league_field{
            margin-bottom:30px;
        }

        @media (max-width: 480px){
            body,.page{background:#000}
            .app{max-width:none;box-shadow:none}
        }

        .screen{
            position:relative;
            min-height:100vh;
            z-index:1;
            background-position:center center;
            background-repeat:no-repeat;
            background-size:cover;
            background-image:var(--screen-bg);
        }

        .screen::before{
            content:"";
            position:absolute;
            inset:0;
            pointer-events:none;
            z-index:0;
        }

        .screen > *{
            position:relative;
            z-index:1;
        }

        #introScreen{
            --screen-bg:url("{{ asset('images/plyr-1.png') }}");
        }

        #thanksScreen{
            --screen-bg:url("{{ asset('images/plyr-5.png') }}");
        }

        #formScreen.step-bg-1{
            --screen-bg:url("{{ asset('images/plyr-2.png') }}");
        }

        #formScreen.step-bg-2{
            --screen-bg:url("{{ asset('images/plyr-3.png') }}");
        }

        #formScreen.step-bg-3{
            --screen-bg:url("{{ asset('images/plyr-4.png') }}");
        }

        #formScreen.step-bg-4{
            --screen-bg:url("{{ asset('images/plyr-5.png') }}");
        }

        .logo-main{width:220px;display:block;}

        .hero-screen{
            padding:34px 28px calc(28px + env(safe-area-inset-bottom));
            display:flex;
            flex-direction:column;
            color:#fff;
            min-height:100vh;
        }

        .hero-logo-wrap{text-align:start;}
        .hero-copy{margin-top:auto}

        .hero-title,
        .final-title{
            margin:0 0 16px;
            font-size:36px;
            line-height:.9;
            letter-spacing:.01em;
            text-transform:uppercase;
            font-weight:700;
            color:#fff;
        }

        .hero-text,
        .final-text{
            margin:0 0 18px;
            font-size:12px;
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
            background:rgba(0,0,0,.14);
            backdrop-filter:blur(3px);
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
        .topbar-title{display:none}

        .topbar-logo{
            display:block;
            height:28px;
            width:auto;
            margin-left:auto;
            object-fit:contain;
        }

        .progress-band{
            position:relative;
            background:rgba(64, 64, 64, 0.2);
            min-height:var(--band-h);
            padding:16px 14px 14px;
            z-index:2;
            flex:0 0 var(--band-h);
            backdrop-filter:blur(6px);
        }

        .ring{
            position:absolute;
            top:0;
            left:0;
            width:100%;
            height:5px;
            background:rgba(0,0,0,.08);
            border-radius:0;
            overflow:hidden;
        }

        .ring::after{
            content:"";
            position:absolute;
            inset:0 auto 0 0;
            width:var(--progress-width, 25%);
            background:var(--pc-blue);
            transition:width .28s ease;
        }

        .progress-title{
            margin:10px 0 0;
            color:white;
            font-size:22px;
            line-height:.98;
            font-weight:700;
            max-width:320px;
        }

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
            width:100%;
            flex:1 1 auto;
            min-height:calc(100vh - var(--topbar-h) - var(--band-h));
            padding:20px 20px calc(20px + env(safe-area-inset-bottom));
            overflow:visible;
            animation:fadeSlide .22s ease;
        }

        .panel-scroll{
            display:flex;
            flex-direction:column;
            flex:1 1 auto;
            min-height:0;
            overflow:visible;
            padding-bottom:8px;
        }

        .panel-scroll.with-bottom-cta{
            display:flex;
            flex-direction:column;
            min-height:100%;
            overflow:visible;
        }

        .step-panel.active,
        .final-screen.active{display:flex}

        @keyframes fadeSlide{
            from{opacity:0;transform:translateY(12px)}
            to{opacity:1;transform:translateY(0)}
        }

        .field{
            margin-bottom:16px;
            position:relative;
            z-index:1;
        }

        .field.field-open{
            z-index:3000;
        }
        .field.two{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;z-index:999;}
        .field.three{display:grid;grid-template-columns:1fr 2fr 2fr;gap:16px}
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
            border-bottom: 2px solid #ff6347;
            outline:none;
            background: #8383832f;
            color:white;
            font-family:"Antonio", system-ui, sans-serif;
            font-size:15px;
            padding:15px 12px;
            appearance:none;
            transition:transform .16s ease, box-shadow .16s ease, opacity .16s ease;
        
        }


        .input {
            margin-top:8px;
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

        .gender-list{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:12px;
            padding-left:1px;
        }

        .gender-card{
            width:100%;
            border:1px solid rgba(255,255,255,.14);
            border-bottom:2px solid #ff6347;
            background:rgba(131,131,131,.18);
            color:#fff;
            border-radius:14px;
            padding:12px 10px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:8px;
            cursor:pointer;
            transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease;
        }

        .gender-card:hover{
            transform:translateY(-1px);
            background:rgba(131,131,131,.24);
        }

        .gender-card.is-selected{
            background:rgba(31,132,216,.18);
            border-color:rgba(31,132,216,.6);
            box-shadow:0 0 0 2px rgba(31,132,216,.18);
        }

        .gender-card svg{
            width:34px;
            height:34px;
            display:block;
        }

        .gender-card-label{
            font-size:15px;
            line-height:1;
            font-weight:700;
            letter-spacing:.02em;
            text-transform:uppercase;
        }

        .gender-input{
            display:none;
        }

        .step1-left{min-width:0}

        .search-select{
            position:relative;
            z-index:20;
            transition:opacity .18s ease, transform .18s ease;
        }

        .search-select.open{
            z-index:3100;
        }
        .search-select.is-disabled{opacity:.58;pointer-events:none}
        .search-select.is-hidden{display:none}
        .search-select-control{display:flex;align-items:center;justify-content:space-between;gap:8px;text-align:left;cursor:pointer}
        .search-select-value{display:flex;align-items:center;gap:8px;min-width:0;flex:1 1 auto}
        .search-select-placeholder,.search-select-meta{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color: white;}

        select.select{
            background-color:#232323;
            color:#fff;
            -webkit-appearance:none;
            appearance:none;
        }

        select.select option,
        select.select optgroup{
            background:#171717;
            color:#fff;
        }

        select.select option:checked,
        select.select option:hover,
        select.select option:focus{
            background:#232323 linear-gradient(0deg, #232323 0%, #232323 100%);
            color:#fff;
        }

        #position_select{
            background-color:#232323;
            color:#fff;
        }

        #position_select::-webkit-scrollbar{
            width:10px;
            height:10px;
        }

        #position_select::-webkit-scrollbar-track{
            background:rgba(242,241,236,.18);
            border-radius:999px;
        }

        #position_select::-webkit-scrollbar-thumb{
            background:rgba(17,17,17,.38);
            border-radius:999px;
            border:2px solid transparent;
            background-clip:padding-box;
        }

        .search-select-logo{width:18px;height:18px;border-radius:50%;object-fit:cover;background:#ddd;flex:0 0 auto}
        .search-select-caret,.search-select-search-icon{width:15px;height:15px;color:#666;flex:0 0 auto}

        .search-select-dropdown{
            display:none;
            position:absolute;
            left:0;
            right:0;
            top:calc(100% + 6px);
            z-index:3200;
            background:#171717;
            border:1px solid rgba(255,255,255,.08);
            border-radius:14px;
            overflow:hidden;
            box-shadow:0 18px 36px rgba(0,0,0,.28);
        }

        .search-select.open .search-select-dropdown{
            display:block;
            z-index:3200;
        }

        .search-select-search{
            display:flex;
            gap:8px;
            align-items:center;
            padding:8px;
            border-bottom:1px solid rgba(255,255,255,.08);
            color:#bfbfbf;
            background:#171717;
            position:relative;
            z-index:1;
        }
        .search-select-search input{width:100%;min-height:32px;border:1px solid rgba(255,255,255,.08);border-radius:999px;outline:none;background:#232323;color:#fff;padding:7px 12px;font-family:"Antonio", system-ui, sans-serif;font-size:13px}
        .search-select-list{
            max-height:190px;
            overflow:auto;
            padding:6px 0;
            background:#171717;
            scrollbar-width:thin;
            scrollbar-color:rgba(17,17,17,.38) rgba(242,241,236,.18);
        }

        .search-select-list::-webkit-scrollbar{
            width:10px;
            height:10px;
        }

        .search-select-list::-webkit-scrollbar-track{
            background:rgba(242,241,236,.18);
            border-radius:999px;
        }

        .search-select-list::-webkit-scrollbar-thumb{
            background:rgba(17,17,17,.38);
            border-radius:999px;
            border:2px solid transparent;
            background-clip:padding-box;
        }

        .search-select-list::-webkit-scrollbar-thumb:hover{
            background:rgba(17,17,17,.54);
            background-clip:padding-box;
        }
        .search-select-option{width:100%;border:0;background:#171717;color:#fff;text-align:left;padding:10px 12px;display:flex;align-items:center;gap:8px;cursor:pointer}
        .search-select-option-copy{display:flex;flex-direction:column;min-width:0}
        .search-select-option-title{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .search-select-option-subtitle{font-size:10px;color:#a7a7a7;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .search-select-empty{padding:12px;color:#bfbfbf;font-size:12px;background:#171717}

        .search-select-option:hover,
        .search-select-option:focus{
            background:#232323;
        }

        .search-select-option.is-selected{
            background:#202a36;
        }

        .manual-input-wrap{display:none;animation:fadeSlide .2s ease}
        .manual-input-actions{display:flex;justify-content:flex-end;margin-top:8px}
        .manual-toggle-back{border:0;background:transparent;color:#fff;font-family:"Antonio", system-ui, sans-serif;font-size:12px;letter-spacing:.02em;text-transform:uppercase;cursor:pointer;opacity:.9;padding:0}
        .manual-toggle-back:hover{opacity:1;text-decoration:underline}
        .manual-input::placeholder,.input::placeholder{color:#6a6a6a;opacity:.85}
        .manual-input-wrap.visible{display:block}

        .chips-wrap{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;min-height:20px}
        .chip{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.18);font-size:12px;color:#fff;line-height:1}
        .chip button{border:0;background:transparent;color:#fff;font:inherit;cursor:pointer;padding:0;line-height:1}

        .file-input{
            position:absolute;
            width:1px;
            height:1px;
            padding:0;
            margin:-1px;
            overflow:hidden;
            clip:rect(0,0,0,0);
            white-space:nowrap;
            border:0;
        }
        .file-input::file-selector-button{border:0;border-radius:999px;padding:6px 10px;background:#deded8;color:#111;font-family:"Antonio", system-ui, sans-serif;margin-right:8px;cursor:pointer}
        .image-preview-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px;max-width:318px}
        .image-preview-card{position:relative;aspect-ratio:1/1;border-radius:14px;overflow:hidden;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);animation:fadeSlide .2s ease}
        .image-preview-card img{width:100%;height:100%;object-fit:cover;display:block}
        .image-preview-overlay{position:absolute;inset:0;background:linear-gradient(to top, rgba(0,0,0,.52), rgba(0,0,0,.08));display:flex;align-items:flex-end;justify-content:center;padding:8px}
        .image-preview-remove{border:0;border-radius:999px;background:rgba(255,255,255,.94);color:#111;font-family:"Antonio", system-ui, sans-serif;font-size:11px;line-height:1;text-transform:uppercase;padding:7px 10px;cursor:pointer}
        .image-add-tile{
            aspect-ratio:1/1;
            border-radius:14px;
            border:1px dashed rgba(255,255,255,.28);
            background:rgba(255,255,255,.08);
            color:#fff;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:6px;
            cursor:pointer;
            padding:8px;
            text-align:center;
            transition:transform .18s ease, background .18s ease, border-color .18s ease;
        }
        .image-add-tile:hover{
            transform:translateY(-1px);
            background:rgba(255,255,255,.14);
            border-color:rgba(255,255,255,.4);
        }
        .image-add-tile svg{
            width:22px;
            height:22px;
            display:block;
        }
        .image-add-tile span{
            font-size:11px;
            line-height:1.15;
            text-transform:uppercase;
            font-weight:700;
            letter-spacing:.03em;
        }
        .sample-block{margin-top:6px;position:relative;z-index:2;max-width:292px}
        .sample-title{margin:0 0 14px;font-size:19px;line-height:1;text-transform:uppercase;color:#fff;font-weight:700}
        .sample-copy{margin:0 0 16px;font-size:13px;line-height:1.18;color:#fff;opacity:.95}

        .step-cta{
            margin-top:auto;
            padding-top:20px;
            padding-bottom:calc(6px + env(safe-area-inset-bottom));
            position:relative;
            z-index:1;
            background:linear-gradient(to top, rgba(0,0,0,.45), rgba(0,0,0,0));
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
            color: #ea3131ff;;
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
        .sample-trigger{
            display:inline-flex;
            align-items:center;
            color:black;
            margin-bottom: 10px;
            gap:8px;
            border:0;
            background:white;
            border-radius:10px;
            padding:10px 20px;
            font-family:"Antonio", system-ui, sans-serif;
            font-size:19px;
            line-height:1;
            text-transform:uppercase;
            font-weight:700;
            cursor:pointer;
        }

        .sample-trigger:hover{
            opacity:.92;
        }

        .sample-trigger svg{
            width:18px;
            height:18px;
            flex:0 0 auto;
        }

        .sample-modal{
            position:absolute;
            inset:0;
            z-index:5000;
            display:none;
            align-items:flex-center;
            justify-content:center;
            padding:12px;
            background:rgba(0,0,0,.66);
            backdrop-filter:blur(6px);
        }

        .sample-modal.is-open{
            display:flex;
        }

        .sample-modal-panel{
            width:100%;
            max-width:100%;
            overflow:auto;
            border-radius:18px;
            background:#0f0f10;
            border:1px solid rgba(255,255,255,.08);
            box-shadow:0 18px 40px rgba(0,0,0,.45);
            padding:14px 14px calc(14px + env(safe-area-inset-bottom));
        }

        .sample-modal-header{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:10px;
            margin-bottom:12px;
        }

        .sample-modal-title{
            margin:0;
            color:#fff;
            font-size:22px;
            line-height:.95;
            text-transform:uppercase;
            font-weight:700;
        }

        .sample-modal-text{
            margin:6px 0 0;
            color:rgba(255,255,255,.84);
            font-size:12px;
            line-height:1.28;
            max-width:100%;
        }

        .sample-modal-close{
            width:34px;
            height:34px;
            border-radius:999px;
            border:0;
            background:#f2f1ec;
            color:#111;
            font-family:"Antonio", system-ui, sans-serif;
            font-size:20px;
            line-height:1;
            cursor:pointer;
            flex:0 0 auto;
        }

        .sample-grid{
            display:grid;
            grid-template-columns:1fr;
            gap:12px;
        }

        .sample-card{
            border:1px solid rgba(255,255,255,.08);
            border-radius:14px;
            overflow:hidden;
            background:#171717;
        }

        .sample-card-image{
            display:block;
            width:100%;
            aspect-ratio:10/16;
            object-fit:cover;
            background:#111;
        }

        .sample-card-copy{
            padding:12px;
        }

        .sample-card-kicker{
            margin:0 0 6px;
            color:#ff6347;
            font-size:12px;
            line-height:1;
            text-transform:uppercase;
            letter-spacing:.04em;
            font-weight:700;
        }

        .sample-card-title{
            margin:0 0 8px;
            color:#fff;
            font-size:18px;
            line-height:.95;
            text-transform:uppercase;
            font-weight:700;
        }

        .sample-card-list{
            margin:0;
            padding-left:18px;
            color:rgba(255,255,255,.9);
            font-size:12px;
            line-height:1.28;
        }

        .sample-card-list li + li{
            margin-top:6px;
        }

        .sample-footer-note{
            margin:14px 0 0;
            color:rgba(255,255,255,.78);
            font-size:12px;
            line-height:1.3;
        }

        @media (min-width: 320px){
            .sample-modal{
                padding:14px;
            }

            .sample-modal-panel{
                width:min(100%, 680px);
            }

            .sample-grid{
                grid-template-columns:1fr 1fr;
            }
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
            $submittedPlan = $submitted['selected_plan'] ?? ($selectedPlan ?? 'Free');
            $submittedPlanSlug = $submitted['plan'] ?? match ($submittedPlan) {
                'Plyr Plus' => 'plyr-plus',
                'My Journey' => 'my-journey',
                default => 'free',
            };
        @endphp

        @if (session('success'))
            <section class="screen hero-screen" id="thanksScreen">
                <div class="hero-logo-wrap">
                    <img src="{{ asset('logo.png') }}" alt="PLYRCARD" class="logo-main">
                </div>

                <div class="hero-copy">
                    <h1 class="hero-title">Thank You</h1>
                    <p class="hero-text">Your intake has been submitted successfully, {{ $submittedFirstName }}.</p>
                    <p class="hero-text">Please check your email{{ $submittedEmail ? ' at ' . $submittedEmail : '' }} for confirmation of your login and account access details.</p>
                    <p class="hero-text">You are now signed in. You can continue to your profile when you are ready.</p>

                    <div class="bottom-cta">
                        <a href="{{ url('/admin/profile') }}" class="btn-link">
                            <button type="button" class="btn">Continue</button>
                        </a>
                    </div>
                </div>
            </section>
        @else
            <section class="screen hero-screen" id="introScreen">
                <div class="hero-logo-wrap">
                    <img src="{{ asset('logo.png') }}" alt="PLYRCARD" class="logo-main">
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

            <form method="POST" action="{{ route('public.player-intake-app.store', request()->only(['utm_plan', 'plan', 'package', 'package_name'])) }}" enctype="multipart/form-data" id="playerIntakeForm">
                @csrf
                <input type="hidden" name="selected_plan" value="{{ $selectedPlan ?? 'Free' }}">
                <input type="hidden" name="utm_plan" value="{{ request('utm_plan', request('plan', request('package', request('package_name', $selectedPlan ?? 'Free')))) }}">

                <section class="screen step-screen" id="formScreen" style="display:none;">
                    <div class="topbar">
                        <button type="button" class="back-btn" id="backBtn" aria-label="Back">
                            <svg viewBox="0 0 24 24" fill="none"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <img src="{{ asset('logo.png') }}" alt="PLYRCARD" class="topbar-logo">
                    </div>

                    <div class="progress-band" id="progressBand">
                        <div class="ring" id="progressRing" style="--progress-width:25%;"></div>
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
                                        @php
                                            $oldGender = old('gender');
                                            $isFemale = in_array($oldGender, ['female', 'girls'], true);
                                            $selectedGender = $isFemale ? 'female' : 'male';
                                        @endphp
                                        <input type="hidden" name="gender" id="gender" value="{{ $selectedGender }}">
                                        <div class="gender-list" id="genderList">
                                            <button type="button" class="gender-card {{ $selectedGender === 'male' ? 'is-selected' : '' }}" data-gender-value="male" aria-pressed="{{ $selectedGender === 'male' ? 'true' : 'false' }}">
                                                <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                                    <circle cx="18" cy="30" r="8" stroke="currentColor" stroke-width="3"/>
                                                    <path d="M24 24L36 12" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                                    <path d="M28 12H36V20" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span class="gender-card-label">Boys</span>
                                            </button>
                                            <button type="button" class="gender-card {{ $selectedGender === 'female' ? 'is-selected' : '' }}" data-gender-value="female" aria-pressed="{{ $selectedGender === 'female' ? 'true' : 'false' }}">
                                                <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                                    <circle cx="24" cy="18" r="8" stroke="currentColor" stroke-width="3"/>
                                                    <path d="M24 26V40" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                                    <path d="M18 34H30" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                                </svg>
                                                <span class="gender-card-label">Girls</span>
                                            </button>
                                        </div>
                                        <div class="field-message" id="gender_error"></div>
                                    </div>

                                    <div class="field two">
                                        <div class="field narrow">
                                            <label class="label" for="personal_email">Email</label>
                                            <input class="input" type="email" id="personal_email" name="personal_email" value="{{ old('personal_email', $prefill['personal_email'] ?? '') }}" placeholder="Enter email address" required>
                                            <div class="field-message" id="personal_email_error"></div>
                                        </div>

                                        <div class="field narrow">
                                            <label class="label" for="phone">Cell Phone</label>
                                            <input class="input phone-input" type="text" id="phone" name="phone" value="{{ old('phone', $prefill['phone'] ?? '') }}" inputmode="tel" placeholder="(555) 123-4567">
                                        <div class="field-message" id="phone_error"></div>
                                    </div>
                                </div>


                                </div>
                            </div>

                            <div class="step-cta">
                                <button type="button" class="btn" id="nextBtn1">Next</button>
                            </div>
                        </div>

                        
                    </div>

                    <div class="step-panel" data-step="2">
                        <div class="panel-scroll with-bottom-cta">
                            <div class="field two">
                                <div>
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
                                <div>
                                    <div class="field" id="position_field">
                                        <label class="label" for="position_select">Position</label>
                                        <select class="select" id="position_select" aria-label="Position"></select>
                                        <div class="field-message" id="position_error"></div>
                                        <div class="chips-wrap" id="positionChips"></div>
                                        <input type="hidden" name="position[]" id="position_values" value="">
                                    </div>
                                </div>
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
                            
                            <div class="field two">
                                
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

                        
                    </div>

                    <div class="step-panel" data-step="3">
                        <div class="panel-scroll with-bottom-cta" id="imageStepScroll">
                            <div class="field" id="portrait_images_field">
                                <label class="label" for="portrait_images">Portrait Images</label>
                                <input class="file-input" type="file" id="portrait_images" name="portrait_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <input class="file-input" type="file" id="portrait_images_addmore" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <div class="image-preview-grid" id="portrait_images_preview"></div>
                                <div class="file-warning-list" id="portrait_images_feedback"></div>
                            </div>

                            <div class="field" id="action_images_field">
                                <label class="label" for="action_images">Action Images</label>
                                <input class="file-input" type="file" id="action_images" name="action_images[]" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <input class="file-input" type="file" id="action_images_addmore" accept="image/png,image/jpeg,image/jpg,image/webp" multiple>
                                <div class="image-preview-grid" id="action_images_preview"></div>
                                <div class="file-warning-list" id="action_images_feedback"></div>
                            </div>

                            <div class="sample-block">
                                <button type="button" class="sample-trigger" id="openSampleModal">
                                    <span>Sample (Click Here)</span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14" stroke-width="2" stroke-linecap="round"/></svg>
                                </button>
                                <p class="sample-copy">Use the sample guide to see ideal framing for portrait and action uploads before you submit your images.</p>
                            </div>
                            <div class="step-cta">
                                <button type="button" class="btn" id="nextBtn3">Next</button>
                            </div>
                        </div>

                        
                    </div>

                    <div class="final-screen" data-step="4">
                        <div class="panel-scroll with-bottom-cta">
                            <h1 class="final-title">Now Make It Unforgettable</h1>
                            <p class="final-text">You’ve built the foundation, now add the final touches that make your PLYRCARD stand out. Finish it now or come back later, but don’t skip the step that brings it all together.</p>
                            <img src="{{ asset('images/plyrcardlogo.png') }}" alt="" class="final-logo">
                            <div class="step-cta">
                                <button type="submit" class="btn" id="submitBtn">Submit</button>
                            </div>
                        </div>

                        
                    </div>
                </section>
            </form>
        @endif
        <div class="sample-modal" id="sampleModal" aria-hidden="true">
            <div class="sample-modal-panel" role="dialog" aria-modal="true" aria-labelledby="sampleModalTitle">
                <div class="sample-modal-header">
                    <div>
                        <h2 class="sample-modal-title" id="sampleModalTitle">Image Upload Samples</h2>
                        <p class="sample-modal-text">Use these as quick guides for framing, clarity, and cropping.</p>
                    </div>
                    <button type="button" class="sample-modal-close" id="closeSampleModal" aria-label="Close sample guide">×</button>
                </div>

                <div class="sample-grid">
                    <article class="sample-card">
                        <img src="{{ asset('images/portrait-sample.png') }}" alt="Portrait sample" class="sample-card-image">
                        <div class="sample-card-copy">
                            <p class="sample-card-kicker">Portrait Sample</p>
                            <h3 class="sample-card-title">Best for Profile Image</h3>
                            <ul class="sample-card-list">
                                <li>Face forward.</li>
                                <li>Crop head to hips.</li>
                                <li>Keep face clear.</li>
                                <li>Use clean background.</li>
                            </ul>
                        </div>
                    </article>

                    <article class="sample-card">
                        <img src="{{ asset('images/action-sample.png') }}" alt="Action sample" class="sample-card-image">
                        <div class="sample-card-copy">
                            <p class="sample-card-kicker">Action Sample</p>
                            <h3 class="sample-card-title">Best for Action Image</h3>
                            <ul class="sample-card-list">
                                <li>Show real gameplay.</li>
                                <li>Keep full movement visible.</li>
                                <li>Jersey/number should show.</li>
                                <li>Use sharp, clear shots.</li>
                            </ul>
                        </div>
                    </article>
                </div>

                <p class="sample-footer-note">Best: 1 portrait + 2–3 action shots.</p>
            </div>
        </div>
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
    selectedPlan: @json($selectedPlan ?? 'Free'),
    submitted: @json(session('success') ? [
        'plan' => $submittedPlanSlug,
        'selected_plan' => $submittedPlan,
        'payment_url' => null,
        'app_url' => $submitted['app_url'] ?? url('/admin/profile'),
        'payload' => [
            'first_name' => $submitted['first_name'] ?? null,
            'last_name' => $submitted['last_name'] ?? null,
            'email' => $submitted['email'] ?? null,
            'phone' => $submitted['phone'] ?? null,
            'user_id' => $submitted['user_id'] ?? null,
            'contact_id' => $submitted['contact_id'] ?? null,
        ],
    ] : null),
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

    function setFormScreenBackground(step){
        const form = document.getElementById('formScreen');
        if (!form) return;

        form.classList.remove('step-bg-1', 'step-bg-2', 'step-bg-3', 'step-bg-4');
        form.classList.add('step-bg-' + String(step));
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
        if (form) {
            form.style.display = 'flex';
            setFormScreenBackground(step);
        }

        $all('.step-panel').forEach(function(panel){ panel.classList.remove('active'); panel.scrollTop = 0; });
        $all('.final-screen').forEach(function(panel){ panel.classList.remove('active'); panel.scrollTop = 0; });

        const target = getStepBody(step);
        if (target) target.classList.add('active');

        $('.topbar').style.display = 'flex';

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
        if (!ring) return;
        const current = Number(ring.dataset.percent || 0);
        const target = Math.max(0, Math.min(100, Number(percent) || 0));
        const duration = 280;
        const start = performance.now();

        function tick(now){
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            const value = current + ((target - current) * eased);
            ring.style.setProperty('--progress-width', value + '%');
            if (t < 1) {
                requestAnimationFrame(tick);
            } else {
                ring.style.setProperty('--progress-width', target + '%');
            }
        }

        ring.dataset.percent = String(target);
        requestAnimationFrame(tick);
    }

    function validateEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(safe(v)); }
    function digitsOnly(v){ return safe(v).replace(/\D/g, ''); }
    function validatePhone(v){ const len = digitsOnly(v).length; return !safe(v) || len === 10; }

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

    function getSelectedGender(){ return safe($('#gender') && $('#gender').value).toLowerCase(); }
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
                    if (String(item.id) === String(hiddenInput.value)) {
                        btn.classList.add('is-selected');
                    }
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

    function appendFilesToInput(input, newFiles){
        if (!input) return;
        const existingFiles = Array.prototype.slice.call(input.files || []);
        const dt = new DataTransfer();
        existingFiles.concat(Array.prototype.slice.call(newFiles || [])).forEach(function(file){
            dt.items.add(file);
        });
        input.files = dt.files;
    }

    function renderImagePreviews(fieldId){
        const input = document.getElementById(fieldId);
        const preview = document.getElementById(fieldId + '_preview');
        const addMoreInput = document.getElementById(fieldId + '_addmore');
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

        const addTile = document.createElement('label');
        addTile.className = 'image-add-tile';
        addTile.setAttribute('for', fieldId + '_addmore');
        addTile.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14" stroke-width="2" stroke-linecap="round"/></svg><span>Add More</span>';
        preview.appendChild(addTile);

        if (addMoreInput) addMoreInput.value = '';
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
            const gender = safe($('#gender') && $('#gender').value);
            const email = safe($('#personal_email') && $('#personal_email').value);
            const phone = safe($('#phone') && $('#phone').value);
            setFieldError('first_name', first ? '' : 'First name is required.');
            setFieldError('last_name', last ? '' : 'Last name is required.');
            setFieldError('gender', gender ? '' : 'Select a gender.');
            setFieldError('personal_email', !email ? 'Email is required.' : (validateEmail(email) ? '' : 'Enter a valid email address.'));
            setFieldError('phone', !phone ? '' : (validatePhone(phone) ? '' : 'Enter a valid US phone number.'));
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
        if (safe($('#gender') && $('#gender').value)) filled += 1;
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
                if (['selected_plan', 'utm_plan', 'plan', 'package', 'package_name'].indexOf(field.name) !== -1) return;
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
            if (['selected_plan', 'utm_plan', 'plan', 'package', 'package_name'].indexOf(name) !== -1) return;
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

    function postSubmittedMessage(submission){
        if (!submission || !window.parent || window.parent === window) return;

        window.parent.postMessage({
            type: 'plyrcard-intake-submitted',
            plan: submission.plan || 'free',
            selected_plan: submission.selected_plan || 'Free',
            payment_url: submission.payment_url || null,
            app_url: submission.app_url || null,
            payload: submission.payload || {}
        }, '*');
    }

    function formatPhoneInputValue(value) {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 10);
        if (!digits) return '';
        if (digits.length < 4) return '(' + digits;
        if (digits.length < 7) return '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
        return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
    }

    document.addEventListener('DOMContentLoaded', function(){
        if (document.getElementById('thanksScreen')) {
            clearDraft();
            postSubmittedMessage(data.submitted);
            return;
        }

        const draft = restoreDraft();
        if (!selectedPositions.length && oldPositions.length) selectedPositions = oldPositions.slice();

        const initialGender = safe($('#gender') && $('#gender').value);
        $all('[data-gender-value]').forEach(function(card){
            const isSelected = String(card.getAttribute('data-gender-value')) === String(initialGender);
            card.classList.toggle('is-selected', isSelected);
            card.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });

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

        $all('[data-gender-value]').forEach(function(btn){
            btn.addEventListener('click', function(){
                const genderInput = $('#gender');
                if (genderInput) genderInput.value = this.getAttribute('data-gender-value') || '';

                $all('[data-gender-value]').forEach(function(card){
                    const isSelected = card === btn;
                    card.classList.toggle('is-selected', isSelected);
                    card.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                });

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
                validateStep(1);
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

        $('#portrait_images') && $('#portrait_images').addEventListener('change', function(){
            validateFilesLive('portrait_images');
            saveDraft();
            updateLiveProgress();
        });
        $('#action_images') && $('#action_images').addEventListener('change', function(){
            validateFilesLive('action_images');
            saveDraft();
            updateLiveProgress();
        });

        $('#portrait_images_addmore') && $('#portrait_images_addmore').addEventListener('change', function(){
            appendFilesToInput($('#portrait_images'), this.files);
            validateFilesLive('portrait_images');
            saveDraft();
            updateLiveProgress();
            this.value = '';
        });

        $('#action_images_addmore') && $('#action_images_addmore').addEventListener('change', function(){
            appendFilesToInput($('#action_images'), this.files);
            validateFilesLive('action_images');
            saveDraft();
            updateLiveProgress();
            this.value = '';
        });

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

        const sampleModal = $('#sampleModal');
        const openSampleModalBtn = $('#openSampleModal');
        const closeSampleModalBtn = $('#closeSampleModal');

        function openSampleModal(){
            if (!sampleModal) return;
            sampleModal.classList.add('is-open');
            sampleModal.setAttribute('aria-hidden', 'false');
            
        }

        function closeSampleModal(){
            if (!sampleModal) return;
            sampleModal.classList.remove('is-open');
            sampleModal.setAttribute('aria-hidden', 'true');
            
        }

        openSampleModalBtn && openSampleModalBtn.addEventListener('click', openSampleModal);
        closeSampleModalBtn && closeSampleModalBtn.addEventListener('click', closeSampleModal);

        sampleModal && sampleModal.addEventListener('click', function(e){
            if (e.target === sampleModal) closeSampleModal();
        });

        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && sampleModal && sampleModal.classList.contains('is-open')) {
                closeSampleModal();
            }
        });

        const intakeForm = $('#playerIntakeForm');
        if (intakeForm) {
            intakeForm.addEventListener('submit', async function(event){
                clearDraft();

                // When this intake is embedded on plyrcard.com, submit with fetch so the
                // controller can return JSON, then ask the parent registration page to
                // switch this iframe to the correct GHL survey URL with query params.
                if (!(window.parent && window.parent !== window)) return;

                event.preventDefault();

                const submitBtn = $('#submitBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';
                }

                try {
                    const response = await fetch(intakeForm.action, {
                        method: 'POST',
                        body: new FormData(intakeForm),
                        credentials: 'include',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Plyrcard-Embed': '1'
                        }
                    });

                    const contentType = response.headers.get('content-type') || '';

                    if (response.ok && contentType.indexOf('application/json') !== -1) {
                        const submission = await response.json();
                        postSubmittedMessage(submission);

                        if (submission.payment_url) {
                            hideAllScreens();
                            const formScreen = $('#formScreen');
                            if (formScreen) formScreen.style.display = 'none';
                            document.body.innerHTML = '<div class="page"><div class="app"><section class="screen hero-screen" id="thanksScreen"><div class="hero-copy"><h1 class="hero-title">Almost Done</h1><p class="hero-text">Your intake has been submitted. Opening the next step now.</p></div></section></div></div>';
                            return;
                        }

                        if (submission.app_url) {
                            window.top.location.href = submission.app_url;
                            return;
                        }
                    }

                    // If validation fails, Laravel usually returns JSON 422 or HTML.
                    // Fall back to a normal submit so server-side errors render as before.
                    intakeForm.submit();
                } catch (error) {
                    intakeForm.submit();
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit';
                    }
                }
            });
        }


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