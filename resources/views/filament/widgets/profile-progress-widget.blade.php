<x-filament-widgets::widget>
    <x-filament::section
        heading="Profile progress"
        description="Complete your profile to improve visibility and unlock achievements."
    >
        <style>
            .profile-progress-widget {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 1.5rem;
                width: 100%;
            }

            .profile-progress-stack {
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
                min-width: 0;
            }

            .profile-card {
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.03);
                padding: 1.25rem;
            }

            .profile-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1rem;
            }

            .profile-title {
                font-size: 0.95rem;
                font-weight: 700;
                color: #fff;
                margin: 0;
            }

            .profile-subtle {
                font-size: 0.82rem;
                color: #94a3b8;
                margin: 0.25rem 0 0;
            }

            .profile-completion-value {
                font-size: 2rem;
                font-weight: 800;
                line-height: 1;
                color: #fff;
                margin: 0;
            }

            .profile-progress-bar {
                width: 100%;
                height: 12px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.08);
                overflow: hidden;
                margin-top: 1rem;
            }

            .profile-progress-fill {
                height: 100%;
                border-radius: 999px;
                background: #f97316;
                transition: width 0.3s ease;
            }

            .profile-sections {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .profile-section-block {
                border: 1px solid rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.025);
                border-radius: 14px;
                padding: 1rem;
            }

            .profile-section-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: 0.85rem;
            }

            .profile-section-title-wrap {
                min-width: 0;
            }

            .profile-section-title {
                margin: 0;
                font-size: 0.9rem;
                font-weight: 700;
                color: #fff;
            }

            .profile-section-meta {
                margin: 0.2rem 0 0;
                font-size: 0.78rem;
                color: #94a3b8;
            }

            .profile-section-link {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                font-size: 0.8rem;
                font-weight: 700;
                color: #fb923c;
                text-decoration: none;
                white-space: nowrap;
            }

            .profile-section-link:hover {
                color: #fdba74;
            }

            .profile-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.75rem;
            }

            .profile-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                min-width: 0;
                padding: 0.85rem 1rem;
                border-radius: 12px;
                border: 1px solid rgba(249, 115, 22, 0.18);
                background: rgba(249, 115, 22, 0.08);
                text-decoration: none;
                transition: 0.2s ease;
            }

            .profile-item:hover {
                border-color: rgba(251, 146, 60, 0.35);
                background: rgba(249, 115, 22, 0.14);
                transform: translateY(-1px);
            }

            .profile-item-icon {
                flex: 0 0 18px;
                width: 18px;
                height: 18px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #fb923c;
            }

            .profile-item-text {
                font-size: 0.9rem;
                font-weight: 600;
                color: #fff;
                line-height: 1.3;
                word-break: break-word;
            }

            .profile-achievements {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .profile-achievement {
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
                padding: 0.9rem 1rem;
                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.03);
            }

            .profile-achievement.unlocked {
                border-color: rgba(34, 197, 94, 0.2);
                background: rgba(34, 197, 94, 0.08);
            }

            .profile-achievement-icon {
                flex: 0 0 18px;
                width: 18px;
                height: 18px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-top: 2px;
            }

            .profile-achievement.unlocked .profile-achievement-icon {
                color: #22c55e;
            }

            .profile-achievement.locked .profile-achievement-icon {
                color: #94a3b8;
            }

            .profile-achievement-body {
                min-width: 0;
                flex: 1;
            }

            .profile-achievement-title {
                margin: 0;
                font-size: 0.92rem;
                font-weight: 700;
                color: #fff;
                line-height: 1.3;
            }

            .profile-achievement-meta {
                margin: 0.2rem 0 0;
                font-size: 0.78rem;
                color: #94a3b8;
            }

            .profile-empty {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 1rem;
                border-radius: 12px;
                border: 1px solid rgba(34, 197, 94, 0.2);
                background: rgba(34, 197, 94, 0.08);
                color: #fff;
                font-weight: 600;
            }

            @media (max-width: 1024px) {
                .profile-progress-widget {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 640px) {
                .profile-grid {
                    grid-template-columns: 1fr;
                }

                .profile-card-header,
                .profile-section-header {
                    flex-direction: column;
                    align-items: flex-start;
                }
            }
        </style>

        <div class="profile-progress-widget">
            <div class="profile-progress-stack">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <p class="profile-title">Completion</p>
                            <p class="profile-completion-value">{{ $completion }}%</p>
                            <p class="profile-subtle">
                                @if ($completion >= 100)
                                    Your profile is fully complete.
                                @elseif ($completion >= 85)
                                    You're almost done — just a few details left.
                                @elseif ($completion >= 60)
                                    Great progress — keep going.
                                @else
                                    Add more details to strengthen your profile.
                                @endif
                            </p>
                        </div>

                        @if ($profileUrl)
                            <x-filament::button
                                tag="a"
                                :href="$profileUrl"
                                icon="heroicon-o-user"
                                color="primary"
                            >
                                Complete profile
                            </x-filament::button>
                        @endif
                    </div>

                    <div class="profile-progress-bar">
                        <div
                            class="profile-progress-fill"
                            style="width: {{ $completion }}%;"
                        ></div>
                    </div>
                </div>

                <div class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <p class="profile-title">Missing profile parts</p>
                            <p class="profile-subtle">
                                {{ collect($missingSections)->sum('count') }} remaining across {{ count($missingSections) }} section(s)
                            </p>
                        </div>
                    </div>

                    @if (count($missingSections))
                        <div class="profile-sections">
                            @foreach ($missingSections as $section)
                                <div class="profile-section-block">
                                    <div class="profile-section-header">
                                        <div class="profile-section-title-wrap">
                                            <p class="profile-section-title">{{ $section['title'] }}</p>
                                            <p class="profile-section-meta">
                                                {{ $section['count'] }} missing item{{ $section['count'] > 1 ? 's' : '' }}
                                            </p>
                                        </div>

                                        <a href="{{ $section['url'] }}" class="profile-section-link">
                                            <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                                            <span>Edit section</span>
                                        </a>
                                    </div>

                                    <div class="profile-grid">
                                        @foreach ($section['items'] as $item)
                                            <a href="{{ $item['url'] }}" class="profile-item">
                                                <span class="profile-item-icon">
                                                    <x-filament::icon icon="heroicon-o-exclamation-circle" class="h-4 w-4" />
                                                </span>
                                                <span class="profile-item-text">{{ $item['label'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="profile-empty">
                            <x-filament::icon icon="heroicon-o-check-badge" class="h-5 w-5" />
                            <span>Nothing missing — your profile looks complete.</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="profile-card">
                <div class="profile-card-header">
                    <div>
                        <p class="profile-title">Achievements</p>
                        <p class="profile-subtle">
                            {{ collect($achievements)->where('unlocked', true)->count() }}/{{ count($achievements) }} unlocked
                        </p>
                    </div>
                </div>

                <div class="profile-achievements">
                    @foreach ($achievements as $achievement)
                        <div class="profile-achievement {{ $achievement['unlocked'] ? 'unlocked' : 'locked' }}">
                            <span class="profile-achievement-icon">
                                <x-filament::icon
                                    :icon="$achievement['unlocked'] ? 'heroicon-o-trophy' : 'heroicon-o-lock-closed'"
                                    class="h-4 w-4"
                                />
                            </span>

                            <div class="profile-achievement-body">
                                <p class="profile-achievement-title">{{ $achievement['label'] }}</p>
                                <p class="profile-achievement-meta">
                                    Unlocks at {{ $achievement['threshold'] }}%
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>