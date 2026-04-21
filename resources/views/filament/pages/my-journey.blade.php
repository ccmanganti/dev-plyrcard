<x-filament-panels::page>
    @php
        $plans = $this->getPlans();
        $addons = $this->getAddons();

        function mjIcon($name) {
            return match ($name) {
                'user' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.118a7.5 7.5 0 0 1 15 0A17.933 17.933 0 0 1 12 21.75a17.933 17.933 0 0 1-7.5-1.632Z" /></svg>',
                'bolt' => '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z" /></svg>',
                'crown' => '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M5 16h14l1 3H4l1-3Zm14-9 1 7H4l1-7 4 3 3-5 3 5 4-3Z" /></svg>',
                'sparkles' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m9.813 15.904.782-2.347a1.5 1.5 0 0 1 .949-.949l2.347-.782-2.347-.782a1.5 1.5 0 0 1-.949-.949l-.782-2.347-.782 2.347a1.5 1.5 0 0 1-.949.949l-2.347.782 2.347.782a1.5 1.5 0 0 1 .949.949l.782 2.347ZM18.259 8.715 18 9.5l-.259-.785A1.125 1.125 0 0 0 17 8l-.785-.259L17 7.482c.337-.112.603-.378.715-.715L18 6l.259.767c.112.337.378.603.715.715L19.759 8 19 8.259a1.125 1.125 0 0 0-.741.456ZM16.5 20.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9A2.25 2.25 0 0 1 7.5 6.75h9A2.25 2.25 0 0 1 18.75 9v9a2.25 2.25 0 0 1-2.25 2.25Z" /></svg>',
                'photo' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0L21.75 15.75m-16.5 4.5h13.5A2.25 2.25 0 0 0 21 18V6A2.25 2.25 0 0 0 18.75 3.75H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Zm10.5-11.25h.008v.008h-.008V9Z" /></svg>',
                'globe-alt' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18m0 18c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m-8.25 9h16.5" /></svg>',
                default => '',
            };
        }
    @endphp

    <style>
        .mj-wrap {
            --bg: #050608;
            --panel: #0b0d10;
            --panel-2: #11141a;
            --muted: #7f8896;
            --text: #f7f8fa;
            --border: rgba(255,255,255,.08);
            --orange: #ff6347;
            --orange-2: #ff6347;
            --blue: #19a7ff;
            --green: #19cf7a;
            color: var(--text);
        }

        .mj-wrap * { box-sizing: border-box; }

        .mj-hero {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,100,61,.25);
            border-radius: 20px;
            background:
                radial-gradient(circle at top right, rgba(255,100,61,.16), transparent 25%),
                linear-gradient(180deg, rgba(54,14,9,.95) 0%, rgba(28,9,9,.98) 100%);
            padding: 2rem;
            min-height: 180px;
        }

        .mj-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            color: var(--orange);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .mj-hero-title {
            margin-top: .8rem;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: .95;
            font-weight: 900;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .mj-hero-title span {
            color: var(--orange);
        }

        .mj-hero-text {
            margin-top: .85rem;
            max-width: 740px;
            color: #b0b7c1;
            font-size: .96rem;
            line-height: 1.55;
        }

        .mj-hero-badge {
            position: absolute;
            right: 2rem;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .7rem;
            text-align: center;
            color: #c5ccd5;
        }

        .mj-hero-badge-circle {
            width: 84px;
            height: 84px;
            border-radius: 999px;
            border: 2px solid rgba(255,100,61,.65);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--orange);
            box-shadow: 0 0 0 10px rgba(255,100,61,.05);
            background: rgba(255,255,255,.02);
        }

        .mj-hero-badge-circle svg {
            width: 32px;
            height: 32px;
        }

        .mj-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1.25rem;
        }

        .mj-card {
            position: relative;
            border-radius: 18px;
            padding: 1.25rem;
            background: linear-gradient(180deg, #0a0c10 0%, #0a0b0f 100%);
            border: 1px solid var(--border);
            box-shadow: 0 14px 34px rgba(0,0,0,.28);
            overflow: hidden;
        }

        .mj-card.current {
            box-shadow: 0 0 0 1px rgba(255,255,255,.08), 0 14px 34px rgba(0,0,0,.28);
        }

        .mj-card.orange {
            border-color: rgba(255,100,61,.7);
            background:
                radial-gradient(circle at top left, rgba(255,100,61,.08), transparent 28%),
                linear-gradient(180deg, #140c0d 0%, #0a0b10 100%);
        }

        .mj-card.blue {
            border-color: rgba(25,167,255,.35);
            background:
                radial-gradient(circle at top left, rgba(25,167,255,.08), transparent 28%),
                linear-gradient(180deg, #08111a 0%, #0a0f16 100%);
        }

        .mj-card.current::after {
            content: "Current";
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: .68rem;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #d9dee5;
            padding: .35rem .65rem;
            border-radius: 999px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.10);
        }

        .mj-popular {
            position: absolute;
            top: -1px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--orange);
            color: white;
            font-size: .68rem;
            font-weight: 900;
            padding: .32rem .85rem;
            border-radius: 999px;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .mj-plan-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            background: rgba(255,255,255,.05);
            color: #d5dbe2;
        }

        .mj-plan-icon svg {
            width: 20px;
            height: 20px;
        }

        .mj-card.orange .mj-plan-icon {
            background: rgba(255,100,61,.12);
            color: var(--orange);
        }

        .mj-card.blue .mj-plan-icon {
            background: rgba(25,167,255,.12);
            color: var(--blue);
        }

        .mj-name {
            font-size: 2rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .mj-price {
            display: flex;
            align-items: flex-end;
            gap: .22rem;
            margin-top: .75rem;
        }

        .mj-price strong {
            font-size: 3rem;
            font-weight: 900;
            line-height: .9;
            letter-spacing: -.04em;
        }

        .mj-card.orange .mj-price strong {
            color: var(--orange);
        }

        .mj-card.blue .mj-price strong {
            color: var(--blue);
        }

        .mj-price span {
            color: #9aa3ae;
            font-size: .92rem;
            padding-bottom: .28rem;
        }

        .mj-tagline {
            margin-top: .35rem;
            color: #8f98a4;
            font-style: italic;
            font-size: .93rem;
        }

        .mj-features {
            margin-top: 1.1rem;
            display: grid;
            gap: .72rem;
        }

        .mj-feature {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            font-size: .94rem;
            line-height: 1.45;
        }

        .mj-feature.included { color: #eff4f8; }
        .mj-feature.excluded { color: #717986; }

        .mj-feature svg {
            width: 16px;
            height: 16px;
            flex: 0 0 16px;
            margin-top: .18rem;
        }

        .mj-feature.included svg { color: #22c55e; }
        .mj-feature.excluded svg { color: #6b7280; }

        .mj-note {
            margin-top: 1rem;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.03);
            border-radius: 12px;
            padding: .85rem .9rem;
            color: #98a1ac;
            font-size: .9rem;
            line-height: 1.45;
        }

        .mj-btn {
            margin-top: 1rem;
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            min-height: 46px;
            padding: .85rem 1rem;
            text-decoration: none;
            font-size: .9rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            border: 1px solid transparent;
            transition: .2s ease;
        }

        .mj-btn.disabled {
            background: rgba(255,255,255,.08);
            color: #7d8592;
            pointer-events: none;
        }

        .mj-btn.orange {
            background: var(--orange);
            color: white;
            box-shadow: 0 8px 24px rgba(255,100,61,.25);
        }

        .mj-btn.orange:hover { filter: brightness(1.05); }

        .mj-btn.blue {
            background: rgba(25,167,255,.12);
            border-color: rgba(25,167,255,.35);
            color: var(--blue);
        }

        .mj-btn.blue:hover {
            background: rgba(25,167,255,.18);
        }

        .mj-btn.ghost {
            background: rgba(255,255,255,.03);
            border-color: rgba(255,255,255,.10);
            color: #dde4eb;
        }

        .mj-btn.ghost:hover {
            background: rgba(255,255,255,.06);
        }

        .mj-addon-wrap {
            margin-top: 1.4rem;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 18px;
            background: linear-gradient(180deg, #0a0c10 0%, #0a0b0f 100%);
            overflow: hidden;
        }

        .mj-addon-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .mj-addon-icon {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            border-radius: 12px;
            background: rgba(25,167,255,.12);
            color: var(--blue);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mj-addon-icon svg {
            width: 20px;
            height: 20px;
        }

        .mj-addon-title {
            font-size: 1.55rem;
            font-weight: 900;
            line-height: 1;
            text-transform: uppercase;
        }

        .mj-addon-subtitle {
            color: #a0a9b4;
            margin-top: .35rem;
            font-size: .94rem;
            line-height: 1.5;
        }

        .mj-addon-list {
            padding: 1rem 1.5rem 1.5rem;
            display: grid;
            gap: .8rem;
        }

        .mj-addon-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border: 1px solid rgba(255,255,255,.07);
            background: rgba(255,255,255,.025);
            border-radius: 12px;
            padding: 1rem 1.1rem;
        }

        .mj-addon-left {
            display: flex;
            align-items: center;
            gap: .9rem;
            min-width: 0;
        }

        .mj-addon-badge {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 10px;
            background: rgba(25,167,255,.1);
            color: var(--blue);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mj-addon-badge svg {
            width: 18px;
            height: 18px;
        }

        .mj-addon-name {
            font-size: 1rem;
            font-weight: 800;
            color: #f1f5f9;
        }

        .mj-addon-desc {
            color: #8e98a5;
            font-size: .87rem;
            margin-top: .2rem;
        }

        .mj-addon-right {
            text-align: right;
            flex: 0 0 auto;
        }

        .mj-addon-price {
            color: var(--blue);
            font-size: 1.6rem;
            font-weight: 900;
            line-height: 1;
        }

        .mj-addon-unit {
            margin-top: .18rem;
            color: #95a0ad;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .mj-footer-note {
            margin-top: 1rem;
            border: 1px solid rgba(25,207,122,.22);
            border-radius: 14px;
            background: rgba(25,207,122,.08);
            color: #c8f5db;
            padding: 1rem 1.1rem;
        }

        .mj-footer-note strong {
            display: block;
            color: #32e08e;
            font-size: .98rem;
            font-weight: 900;
        }

        .mj-footer-note span {
            display: block;
            margin-top: .2rem;
            color: #a7d9bc;
            font-size: .9rem;
        }

        @media (max-width: 1024px) {
            .mj-grid {
                grid-template-columns: 1fr;
            }

            .mj-hero-badge {
                position: static;
                transform: none;
                margin-top: 1.25rem;
                align-items: flex-start;
                text-align: left;
            }
        }

        @media (max-width: 768px) {
            .mj-hero,
            .mj-card,
            .mj-addon-wrap {
                border-radius: 16px;
            }

            .mj-addon-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .mj-addon-right {
                text-align: left;
            }
        }
    </style>

    <div class="mj-wrap">
        <section class="mj-hero">
            <div class="mj-eyebrow">
                <span>🚀</span>
                <span>{{ $this->getHeroEyebrow() }}</span>
            </div>

            <h1 class="mj-hero-title">
                {!! $this->getHeroTitle() !!}
            </h1>

            <p class="mj-hero-text">
                {{ $this->getHeroDescription() }}
            </p>

            <div class="mj-hero-badge">
                <div class="mj-hero-badge-circle">
                    {!! mjIcon('crown') !!}
                </div>
                <div>
                    <div style="font-weight: 800;">{{ $this->getHeroBadgeLabel() }}</div>
                </div>
            </div>
        </section>

        <section class="mj-grid">
            @foreach ($plans as $plan)
                <article class="mj-card {{ $plan['accent'] }} {{ $plan['current'] ? 'current' : '' }}">
                    @if ($plan['popular'])
                        <div class="mj-popular">Most Popular</div>
                    @endif

                    <div class="mj-plan-icon">
                        {!! mjIcon($plan['icon']) !!}
                    </div>

                    <div class="mj-name">{{ $plan['name'] }}</div>

                    <div class="mj-price">
                        <strong>{{ $plan['price'] }}</strong>
                        <span>{{ $plan['suffix'] }}</span>
                    </div>

                    <div class="mj-tagline">{{ $plan['tagline'] }}</div>

                    <div class="mj-features">
                        @foreach ($plan['features'] as $feature)
                            <div class="mj-feature {{ $feature['included'] ? 'included' : 'excluded' }}">
                                @if ($feature['included'])
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                @endif

                                <span>{{ $feature['text'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mj-note">
                        {{ $plan['note'] }}
                    </div>

                    <a href="{{ $plan['button_href'] }}" class="mj-btn {{ $plan['button_style'] }}">
                        {{ $plan['button'] }}
                    </a>
                </article>
            @endforeach
        </section>

        @if ($this->shouldShowAddons())
            <section class="mj-addon-wrap">
                <div class="mj-addon-header">
                    <div class="mj-addon-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13 5.4 5M7 13l-1.5 3h12M10 21a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm8 0a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                        </svg>
                    </div>

                    <div>
                        <div class="mj-addon-title">A La Carte</div>
                        <div class="mj-addon-subtitle">
                            For My Journey Subscribers<br>
                            Order exactly what you need, when you need it. All add-ons available exclusively for My Journey subscribers.
                        </div>
                    </div>
                </div>

                <div class="mj-addon-list">
                    @foreach ($addons as $addon)
                        <a href="#" class="mj-addon-item" style="text-decoration:none;">
                            <div class="mj-addon-left">
                                <div class="mj-addon-badge">
                                    {!! mjIcon($addon['icon']) !!}
                                </div>

                                <div>
                                    <div class="mj-addon-name">{{ $addon['title'] }}</div>
                                    <div class="mj-addon-desc">{{ $addon['desc'] }}</div>
                                </div>
                            </div>

                            <div class="mj-addon-right">
                                <div class="mj-addon-price">{{ $addon['price'] }}</div>
                                <div class="mj-addon-unit">{{ $addon['unit'] }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="mj-footer-note">
            <strong>{{ $this->getFooterHeadline() }}</strong>
            <span>{{ $this->getFooterCopy() }}</span>
        </section>
    </div>
</x-filament-panels::page>