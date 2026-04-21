<x-filament-panels::page>
    <style>
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

        .profile-header-layout {
            display: flex;
            align-items: stretch;
            gap: 1rem;
            margin-bottom: 1rem;
            width: 100%;
        }

        .profile-header-title {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            min-width: 0;
        }

        .profile-header-title h1 {
            margin: 0;
            font-size: 2.35rem;
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #fff;
            white-space: nowrap;
        }

        .profile-header-card {
            flex: 1 1 auto;
            min-width: 0;
        }

        .profile-overview-card {
            position: relative;
            overflow: hidden;
            border-radius: 0.95rem;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)),
                #0b0b0d;
            border: 1px solid var(--pc-border);
            margin-bottom: 0;
            padding: 1rem 1.1rem;
            height: 100%;
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
            flex-wrap: nowrap;
            min-width: 0;
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

        .profile-section-highlight {
            scroll-margin-top: 110px;
            transition: box-shadow 0.25s ease, border-color 0.25s ease;
            box-shadow: 0 0 0 1px rgba(255, 107, 74, 0.45), 0 0 0 6px rgba(255, 107, 74, 0.08);
            border-radius: 1rem;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-meta-wrap {
            min-width: 0;
            flex: 1 1 auto;
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
            flex-direction: row;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            flex: 0 0 auto;
            flex-wrap: wrap;
        }

        .profile-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            min-width: 170px;
            height: 50px;
            padding: 0 1rem;
            border-radius: 0.85rem;
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: transform .15s ease, opacity .15s ease, border-color .15s ease;
            cursor: pointer;
            white-space: nowrap;
        }


        button.profile-action-btn {
            appearance: none;
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

        .profile-meta-item svg,
        .profile-pill svg,
        .profile-action-btn svg {
            width: 14px !important;
            height: 14px !important;
            min-width: 14px !important;
            min-height: 14px !important;
            max-width: 14px !important;
            max-height: 14px !important;
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

        @media (max-width: 1200px) {
            .profile-header-layout {
                flex-direction: column;
            }

            .profile-header-title h1 {
                white-space: normal;
            }
        }

        @media (max-width: 900px) {
            .profile-overview-grid {
                flex-direction: column;
                align-items: stretch;
                flex-wrap: wrap;
            }

            .profile-overview-actions {
                width: 100%;
                justify-content: stretch;
                gap: 0.65rem;
            }

            .profile-action-btn {
                flex: 1 1 0;
                width: auto;
                min-width: 0;
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
        <div class="profile-header-layout">
            <div class="profile-header-title">
                <h1>My Profile</h1>
            </div>

            <div class="profile-header-card">
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
                                @if ($this->canOpenPreviewCard())
                                    <a href="{{ $this->getPreviewUrl() }}" target="_blank" class="profile-action-btn profile-action-btn--ghost">
                                        <x-heroicon-m-eye />
                                        <span>Preview Card</span>
                                    </a>
                                @else
                                    <button
                                        type="button"
                                        wire:click="handlePreviewCardClick"
                                        class="profile-action-btn profile-action-btn--ghost"
                                    >
                                        <x-heroicon-m-eye />
                                        <span>Preview Card</span>
                                    </button>
                                @endif
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
            </div>
        </div>

        <div
            class="profile-page-tabs"
            data-requested-tab="{{ $this->requestedTab }}"
            data-requested-section="{{ $this->getRequestedSectionId() }}"
        >
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

                    @if ($this->getPlanInfo())
                        <a href="{{ $this->getPlanInfo()->getUpgradeUrl() }}" class="pc-lock-btn pc-lock-btn-primary">
                            See Plans
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($showPreviewAccessModal)
        <div class="pc-lock-modal-backdrop" wire:click="closePreviewAccessModal"></div>

        <div class="pc-lock-modal" aria-modal="true" role="dialog">
            <div class="pc-lock-modal-card">
                <div class="pc-lock-modal-icon">
                    @if ($previewAccessModalType === 'complete_profile')
                        <x-heroicon-m-clipboard-document-check />
                    @else
                        <x-heroicon-m-clock />
                    @endif
                </div>

                <h2 class="pc-lock-modal-title">
                    {!! $previewAccessModalTitle !!}
                </h2>

                <p class="pc-lock-modal-copy">
                    {{ $previewAccessModalMessage }}
                </p>

                <div class="pc-lock-modal-actions">
                    <button type="button" wire:click="closePreviewAccessModal" class="pc-lock-btn pc-lock-btn-secondary">
                        Maybe Later
                    </button>

                    @if ($previewAccessModalActionUrl)
                        <a href="{{ $previewAccessModalActionUrl }}" class="pc-lock-btn pc-lock-btn-primary">
                            {{ $previewAccessModalActionLabel }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('livewire:navigated', handleProfileSectionJump);
        document.addEventListener('livewire:initialized', handleProfileSectionJump);
        document.addEventListener('DOMContentLoaded', handleProfileSectionJump);

        function handleProfileSectionJump() {
            const wrapper = document.querySelector('.profile-page-tabs');

            if (!wrapper) {
                return;
            }

            const requestedTab = wrapper.dataset.requestedTab;
            const requestedSection = wrapper.dataset.requestedSection;

            if (!requestedTab && !requestedSection) {
                return;
            }

            const activateTabAndScroll = () => {
                if (requestedTab) {
                    const tabButtons = Array.from(
                        wrapper.querySelectorAll('[role="tab"], button[role="tab"]')
                    );

                    const matchingTab = tabButtons.find((button) => {
                        const label = (button.textContent || '').trim().toLowerCase();
                        return label === requestedTab.trim().toLowerCase();
                    });

                    if (matchingTab && matchingTab.getAttribute('aria-selected') !== 'true') {
                        matchingTab.click();
                    }
                }

                if (requestedSection) {
                    setTimeout(() => {
                        const target = document.getElementById(requestedSection);

                        if (!target) {
                            return;
                        }

                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start',
                        });

                        target.classList.add('profile-section-highlight');

                        setTimeout(() => {
                            target.classList.remove('profile-section-highlight');
                        }, 2200);
                    }, 180);
                }
            };

            requestAnimationFrame(() => {
                setTimeout(activateTabAndScroll, 80);
            });
        }
    </script>
</x-filament-panels::page>