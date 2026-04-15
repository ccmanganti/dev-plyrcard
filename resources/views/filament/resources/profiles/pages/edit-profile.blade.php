<x-filament-panels::page>
    <style>
        .profile-plan-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.55rem;
    margin-top: 0.6rem;
}

.profile-trial-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 32px;
    padding: 0.45rem 0.75rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 107, 74, 0.28);
    background: rgba(255, 107, 74, 0.10);
    color: #fff4ef;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    white-space: nowrap;
}

.profile-trial-badge svg {
    width: 13px !important;
    height: 13px !important;
    min-width: 13px !important;
    min-height: 13px !important;
    max-width: 13px !important;
    max-height: 13px !important;
    color: #ff7e5f;
    flex-shrink: 0 !important;
}

.profile-trial-badge strong {
    color: #ffb199;
    font-weight: 900;
}

.profile-trial-note {
    font-size: 0.72rem;
    color: #d8a395;
    line-height: 1.35;
}

.profile-bottom-save {
    margin-top: 1rem;
    display: flex;
    justify-content: flex-end;
}

.profile-bottom-save .profile-action-btn {
    min-width: 180px;
}
        .profile-shell {
            --pc-bg: #050505;
            --pc-panel: #0b0b0d;
            --pc-panel-2: #111114;
            --pc-border: rgba(255, 255, 255, 0.08);
            --pc-border-soft: rgba(255, 255, 255, 0.06);
            --pc-text: #f5f5f5;
            --pc-muted: #8f8f95;
            --pc-orange: #ff6b4a;
            --pc-orange-2: #ff7e5f;
            --pc-red-brown: #2a120f;
            --pc-red-border: rgba(255, 107, 74, 0.35);
            --pc-cyan: #29c5ff;
            --pc-green: #22c55e;
            --pc-green-2: #16a34a;
            --pc-green-brown: #0d1b12;
            --pc-green-border: rgba(34, 197, 94, 0.35);
        }

        .profile-shell {
            color: var(--pc-text);
        }

        .profile-plan-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.9rem;
            border-radius: 0.85rem;
            padding: 0.8rem 1rem;
            margin-bottom: 0.95rem;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.02);
        }

        .profile-plan-banner--warning {
            border: 1px solid var(--pc-red-border);
            background:
                linear-gradient(90deg, rgba(255,107,74,0.10) 0%, rgba(255,107,74,0.04) 45%, rgba(255,107,74,0.02) 100%),
                #180c0a;
        }

        .profile-plan-banner--success {
            border: 1px solid var(--pc-green-border);
            background:
                linear-gradient(90deg, rgba(34,197,94,0.12) 0%, rgba(34,197,94,0.05) 45%, rgba(34,197,94,0.02) 100%),
                #0d1b12;
        }

        .profile-plan-banner-left {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            min-width: 0;
        }

        .profile-plan-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        .profile-plan-banner--warning .profile-plan-icon {
            color: var(--pc-orange);
        }

        .profile-plan-banner--success .profile-plan-icon {
            color: var(--pc-green);
        }

        .profile-plan-copy {
            min-width: 0;
        }

        .profile-plan-headline {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .profile-plan-banner--warning .profile-plan-headline {
            color: #fff1ec;
        }

        .profile-plan-banner--success .profile-plan-headline {
            color: #ecfdf3;
        }

        .profile-plan-description {
            margin: 0.18rem 0 0;
            font-size: 0.72rem;
            line-height: 1.35;
        }

        .profile-plan-banner--warning .profile-plan-description {
            color: #b9948b;
        }

        .profile-plan-banner--success .profile-plan-description {
            color: #98c6a6;
        }

        .profile-plan-actions {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .profile-upgrade-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.65rem 0.95rem;
            border-radius: 0.75rem;
            background: var(--pc-orange);
            color: white;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-decoration: none;
            white-space: nowrap;
            transition: transform .15s ease, opacity .15s ease;
        }

        .profile-upgrade-btn:hover {
            opacity: 0.94;
            transform: translateY(-1px);
        }

        .profile-overview-card {
            position: relative;
            overflow: hidden;
            border-radius: 0.95rem;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)),
                #0b0b0d;
            border: 1px solid var(--pc-border);
            margin-bottom: 1rem;
            padding: 1rem 1.1rem;
        }

        .profile-overview-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: linear-gradient(90deg, var(--pc-orange), #ff7a58 35%, #dc74ff 60%, var(--pc-cyan));
        }

        .profile-overview-grid {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .profile-overview-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .profile-avatar {
            width: 58px;
            height: 58px;
            border-radius: 999px;
            background: var(--pc-orange);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 8px 16px rgba(255, 107, 74, 0.16);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-meta-wrap {
            min-width: 0;
        }

        .profile-name {
            margin: 0;
            font-size: 1.1rem;
            line-height: 1;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #fff;
        }

        .profile-meta-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.7rem;
            margin-top: 0.4rem;
            color: #b9b9bf;
            font-size: 0.78rem;
        }

        .profile-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 0.28rem;
            white-space: nowrap;
        }

        .profile-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.65rem;
        }

        .profile-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
            color: #d5d5da;
            border-radius: 999px;
            padding: 0.38rem 0.7rem;
            font-size: 0.64rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .profile-pill--accent {
            color: var(--pc-orange);
            border-color: rgba(255,107,74,0.35);
            background: rgba(255,107,74,0.08);
        }

        .profile-overview-actions {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            flex: 0 0 auto;
        }

        .profile-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            min-width: 128px;
            padding: 0.7rem 0.85rem;
            border-radius: 0.75rem;
            text-decoration: none;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: transform .15s ease, opacity .15s ease, border-color .15s ease;
        }

        .profile-action-btn:hover {
            transform: translateY(-1px);
        }

        .profile-action-btn--ghost {
            border: 1px solid rgba(255,255,255,0.10);
            background: rgba(255,255,255,0.02);
            color: #e5e5ea;
        }

        .profile-action-btn--primary {
            border: 1px solid rgba(255,107,74,0.45);
            background: var(--pc-orange);
            color: white;
        }

        .profile-page-tabs [role="tablist"] {
            justify-content: flex-start !important;
            width: 100% !important;
            margin-left: 0 !important;
            margin-right: auto !important;
        }

        .profile-page-tabs [role="tablist"] > * {
            flex: 0 0 auto !important;
        }

        .profile-plan-icon svg,
        .profile-meta-item svg,
        .profile-pill svg,
        .profile-action-btn svg {
            width: 12px !important;
            height: 12px !important;
            min-width: 12px !important;
            min-height: 12px !important;
            max-width: 12px !important;
            max-height: 12px !important;
            flex-shrink: 0 !important;
        }

        .profile-upgrade-btn svg {
            width: 11px !important;
            height: 11px !important;
            min-width: 11px !important;
            min-height: 11px !important;
            max-width: 11px !important;
            max-height: 11px !important;
            flex-shrink: 0 !important;
        }

        .pc-inline-lock {
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid rgba(255, 107, 74, 0.28);
            background: rgba(255, 107, 74, 0.08);
            border-radius: 1rem;
            padding: 1rem 1.1rem;
            margin-bottom: 1rem;
        }

        .pc-inline-lock__icon {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            background: rgba(255, 107, 74, 0.14);
            color: #ff6b4a;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .pc-inline-lock__icon svg {
            width: 20px;
            height: 20px;
        }

        .pc-inline-lock__content {
            flex: 1;
            min-width: 0;
        }

        .pc-inline-lock__content h4 {
            margin: 0;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .pc-inline-lock__content p {
            margin: 0.25rem 0 0;
            color: #b8a39b;
            font-size: 0.8rem;
            line-height: 1.45;
        }

        .pc-inline-lock__button {
            border: 0;
            background: #ff6b4a;
            color: #fff;
            border-radius: 0.8rem;
            padding: 0.8rem 1rem;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            white-space: nowrap;
        }

        .pc-lock-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: rgba(0, 0, 0, 0.78);
            backdrop-filter: blur(6px);
        }

        .pc-lock-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .pc-lock-modal-card {
            width: 100%;
            max-width: 640px;
            border-radius: 1.8rem;
            border: 1px solid rgba(255, 107, 74, 0.25);
            background: #111214;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.45);
            padding: 2.4rem 2rem 1.9rem;
            text-align: center;
        }

        .pc-lock-modal-icon {
            width: 92px;
            height: 92px;
            margin: 0 auto 1.5rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 107, 74, 0.12);
            border: 3px solid var(--pc-orange);
            color: var(--pc-orange);
        }

        .pc-lock-modal-icon svg {
            width: 34px;
            height: 34px;
        }

        .pc-lock-modal-title {
            margin: 0;
            color: #f5f5f5;
            font-size: 2rem;
            font-weight: 900;
            line-height: 1.05;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .pc-lock-modal-title span {
            color: var(--pc-orange);
        }

        .pc-lock-modal-copy {
            max-width: 500px;
            margin: 1.1rem auto 0;
            color: #9f9fa6;
            font-size: 1.05rem;
            line-height: 1.6;
        }

        .pc-lock-modal-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .pc-lock-btn {
            min-width: 190px;
            height: 70px;
            border-radius: 1rem;
            padding: 0 1.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            transition: transform .15s ease, opacity .15s ease, background .15s ease;
        }

        .pc-lock-btn:hover {
            transform: translateY(-1px);
        }

        .pc-lock-btn-secondary {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.10);
            color: #8f8f95;
        }

        .pc-lock-btn-primary {
            background: var(--pc-orange);
            border: 1px solid var(--pc-orange);
            color: #fff;
        }

        @media (max-width: 900px) {
            .profile-plan-banner {
                flex-direction: column;
                align-items: stretch;
            }

            .profile-overview-grid {
                flex-direction: column;
                align-items: stretch;
            }

            .profile-overview-actions {
                width: 100%;
            }

            .profile-action-btn {
                width: 100%;
            }

            .profile-name {
                font-size: 1rem;
            }

            .pc-lock-modal-title {
                font-size: 1.45rem;
            }

            .pc-lock-btn {
                width: 100%;
                min-width: 0;
                height: 58px;
            }

            .pc-inline-lock {
                flex-direction: column;
                align-items: flex-start;
            }

            .pc-inline-lock__button {
                width: 100%;
            }
        }
    </style>

    <div class="profile-shell">
        <div class="profile-plan-banner profile-plan-banner--{{ $this->getPlanTheme() }}">
            <div class="profile-plan-banner-left">
                <div class="profile-plan-icon">
                    @if ($this->getPlanTheme() === 'success')
                        <x-heroicon-m-check-circle />
                    @else
                        <x-heroicon-m-lock-closed />
                    @endif
                </div>

                <div class="profile-plan-copy">
                    <p class="profile-plan-headline">{{ $this->getPlanHeadline() }}</p>
                    <p class="profile-plan-description">{{ $this->getPlanDescription() }}</p>

                    @if ($this->isFreeTrialActive())
                        <div class="profile-trial-badge">
                            <x-heroicon-m-bolt />
                            <span>Trial Active</span>
                            <strong>{{ $this->getFreeTrialDaysLeft() }} {{ $this->getFreeTrialDaysLeft() === 1 ? 'day' : 'days' }} left</strong>
                        </div>
                    @endif
                </div>
            </div>

            <div class="profile-plan-actions">
                @if ($this->canUpgradePlan())
                    <a href="{{ $this->getUpgradeUrl() }}" class="profile-upgrade-btn">
                        <x-heroicon-m-paper-airplane />
                        <span>{{ $this->getUpgradeButtonLabel() }}</span>
                    </a>
                @endif

                @if ($this->shouldShowBookDemoButton())
                    <a href="{{ $this->getBookDemoUrl() }}" class="profile-action-btn profile-action-btn--ghost">
                        <x-heroicon-m-calendar-days />
                        <span>Book a Demo</span>
                    </a>
                @endif
            </div>
        </div>

        <div class="profile-overview-card">
            <div class="profile-overview-grid">
                <div class="profile-overview-left">
                    <div class="profile-avatar">
                        @if ($this->getProfileImageUrl())
                            <img src="{{ $this->getProfileImageUrl() }}" alt="{{ $this->getProfileFullName() }}">
                        @else
                            <span>{{ $this->getProfileInitials() }}</span>
                        @endif
                    </div>

                    <div class="profile-meta-wrap">
                        <h2 class="profile-name">{{ $this->getProfileFullName() ?: 'Your Profile' }}</h2>

                        <div class="profile-meta-row">
                            @if ($this->getProfileSportLabel())
                                <span class="profile-meta-item">
                                    <x-heroicon-m-trophy />
                                    <span>{{ $this->getProfileSportLabel() }}</span>
                                </span>
                            @endif

                            @if ($this->getProfileLocationLabel())
                                <span class="profile-meta-item">
                                    <x-heroicon-m-map-pin />
                                    <span>{{ $this->getProfileLocationLabel() }}</span>
                                </span>
                            @endif

                            @if ($this->getProfileGraduationLabel())
                                <span class="profile-meta-item">
                                    <x-heroicon-m-academic-cap />
                                    <span>{{ $this->getProfileGraduationLabel() }}</span>
                                </span>
                            @endif
                        </div>

                        <div class="profile-badge-row">
                            @if ($this->getNationalTeamBadgeLabel())
                                <span class="profile-pill">
                                    <x-heroicon-m-flag />
                                    <span>{{ $this->getNationalTeamBadgeLabel() }}</span>
                                </span>
                            @endif

                            @if ($this->getJerseyBadgeLabel())
                                <span class="profile-pill profile-pill--accent">
                                    <span>{{ $this->getJerseyBadgeLabel() }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="profile-overview-actions">
                    @if ($this->getPreviewUrl())
                        <a href="{{ $this->getPreviewUrl() }}" target="_blank" class="profile-action-btn profile-action-btn--ghost">
                            <x-heroicon-m-eye />
                            <span>Preview Card</span>
                        </a>
                    @endif

                    <button
                        type="button"
                        wire:click="save"
                        class="profile-action-btn profile-action-btn--primary"
                    >
                        <x-heroicon-m-bookmark-square />
                        <span>Save All</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="profile-page-tabs">
            {{ $this->form }}
        </div>

        <div class="profile-bottom-save">
            <button
                type="button"
                wire:click="save"
                class="profile-action-btn profile-action-btn--primary"
            >
                <x-heroicon-m-bookmark-square />
                <span>Save Profile</span>
            </button>
        </div>
    </div>

    @if ($showLockedFeatureModal)
        <div class="pc-lock-modal-backdrop" wire:click="closeLockedFeatureModal"></div>

        <div class="pc-lock-modal" aria-modal="true" role="dialog">
            <div class="pc-lock-modal-card">
                <div class="pc-lock-modal-icon">
                    <x-heroicon-m-lock-closed />
                </div>

                <h2 class="pc-lock-modal-title">
                    UNLOCK <span>SOCIAL &amp; VIDEO LINKS</span>
                </h2>

                <p class="pc-lock-modal-copy">
                    {{ $lockedFeatureMessage }}
                </p>

                <div class="pc-lock-modal-actions">
                    <button type="button" wire:click="closeLockedFeatureModal" class="pc-lock-btn pc-lock-btn-secondary">
                        Maybe Later
                    </button>

                    <a href="{{ $this->getUpgradeUrl() }}" class="pc-lock-btn pc-lock-btn-primary">
                        See Plans
                    </a>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>