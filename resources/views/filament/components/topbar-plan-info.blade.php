@php
    /** @var \App\Support\ProfilePlanInfo|null $planInfo */
@endphp

@if ($planInfo)
    <style>
        .fi-topbar {
            position: relative;
        }

        .pc-topbar-hook {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            width: min(760px, calc(100vw - 28rem));
            max-width: 760px;
            min-width: 420px;
            z-index: 20;
            pointer-events: none;
        }

        .pc-topbar-plan {
            pointer-events: auto;
            width: 100%;
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .45rem .75rem;
            border-radius: .95rem;
            border: 1px solid rgba(255, 255, 255, .08);
            background: rgba(255, 255, 255, .03);
            min-height: 40px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.02);
            min-width: 0;
        }

        .pc-topbar-plan--warning {
            border-color: rgba(255, 107, 74, 0.28);
            background:
                linear-gradient(90deg, rgba(255,107,74,0.10) 0%, rgba(255,107,74,0.04) 45%, rgba(255,107,74,0.02) 100%),
                #180c0a;
        }

        .pc-topbar-plan--success {
            border-color: rgba(34, 197, 94, 0.28);
            background:
                linear-gradient(90deg, rgba(34,197,94,0.12) 0%, rgba(34,197,94,0.05) 45%, rgba(34,197,94,0.02) 100%),
                #0d1b12;
        }

        .pc-topbar-plan__copy {
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1 1 auto;
            line-height: 1.1;
        }

        .pc-topbar-plan__title {
            font-size: .74rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #fff;
            line-height: 1;
        }

        .pc-topbar-plan__desc {
            font-size: .70rem;
            color: #b9b9bf;
            margin-top: .18rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            line-height: 1.1;
        }

        .pc-topbar-plan__badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .32rem .55rem;
            border-radius: 999px;
            background: rgba(255,255,255,.06);
            color: #fff4ef;
            font-size: .62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .pc-topbar-plan__actions {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-shrink: 0;
        }

        .pc-topbar-plan__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .5rem .8rem;
            border-radius: .8rem;
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            text-decoration: none;
            white-space: nowrap;
            transition: transform .15s ease, opacity .15s ease;
        }

        .pc-topbar-plan__btn:hover {
            transform: translateY(-1px);
            opacity: .94;
        }

        .pc-topbar-plan__btn--ghost {
            border: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.02);
            color: #e5e5ea;
        }

        .pc-topbar-plan__btn--primary {
            background: #ff6b4a;
            border: 1px solid rgba(255,107,74,.45);
            color: white;
        }

        @media (max-width: 1600px) {
            .pc-topbar-hook {
                width: min(680px, calc(100vw - 26rem));
            }
        }

        @media (max-width: 1400px) {
            .pc-topbar-hook {
                width: min(600px, calc(100vw - 24rem));
            }
        }

        @media (max-width: 1200px) {
            .pc-topbar-hook {
                width: min(520px, calc(100vw - 21rem));
                min-width: 360px;
            }

            .pc-topbar-plan__desc {
                font-size: .66rem;
            }

            .pc-topbar-plan__btn {
                padding: .45rem .7rem;
                font-size: .62rem;
            }
        }

        @media (max-width: 1024px) {
            .pc-topbar-hook {
                display: none;
            }
        }
    </style>

    <div class="pc-topbar-hook">
        <div class="pc-topbar-plan pc-topbar-plan--{{ $planInfo->getPlanTheme() }}">
            <div class="pc-topbar-plan__copy">
                <div class="pc-topbar-plan__title">
                    {{ $planInfo->getPlanHeadline() }}
                </div>

                <div class="pc-topbar-plan__desc">
                    {{ $planInfo->getPlanDescription() }}
                </div>
            </div>

            @if ($planInfo->isFreeTrialActive())
                <div class="pc-topbar-plan__badge">
                    Trial Active · {{ $planInfo->getFreeTrialDaysLeft() }} {{ $planInfo->getFreeTrialDaysLeft() === 1 ? 'day' : 'days' }} left
                </div>
            @endif

            <div class="pc-topbar-plan__actions">
                @if ($planInfo->canUpgradePlan())
                    <a href="{{ $planInfo->getUpgradeUrl() }}" class="pc-topbar-plan__btn pc-topbar-plan__btn--primary">
                        {{ $planInfo->getUpgradeButtonLabel() }}
                    </a>
                @endif

                @if ($planInfo->shouldShowBookDemoButton())
                    <a href="{{ $planInfo->getBookDemoUrl() }}" class="pc-topbar-plan__btn pc-topbar-plan__btn--ghost">
                        Book Demo
                    </a>
                @endif
            </div>
        </div>
    </div>
@endif