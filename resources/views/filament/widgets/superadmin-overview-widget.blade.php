<x-filament-widgets::widget>
    <div
        class="sa-overview-v109"
        x-data="{ followUpOpen: false }"
        x-on:keydown.escape.window="if (followUpOpen) { followUpOpen = false; document.body.style.overflow = ''; }"
    >
        <div class="sa-stats-v109">
            <section class="sa-stat-v109">
                <span class="sa-stat-icon-v109 is-orange" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8a4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="sa-stat-label-v109">Athletes on platform</span>
                <strong>{{ number_format($stats['athletes']['value']) }}</strong>
                <small>{{ $stats['athletes']['sub'] }}</small>
            </section>

            <section class="sa-stat-v109">
                <span class="sa-stat-icon-v109 is-green" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 16l5-5l4 4l7-8M15 7h5v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="sa-stat-label-v109">Current monthly recurring</span>
                <strong>{{ $stats['mrr']['value'] }}</strong>
                <small>{{ $stats['mrr']['sub'] }}</small>
            </section>

            <button
                type="button"
                class="sa-stat-v109 sa-stat-button-v109"
                x-on:click="followUpOpen = true; document.body.style.overflow = 'hidden'"
                aria-label="Open follow-up details"
            >
                <span class="sa-stat-icon-v109 is-amber" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M10.3 3.6L2.8 17a2 2 0 0 0 1.75 3h14.9a2 2 0 0 0 1.75-3L13.7 3.6a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4M12 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="sa-stat-label-v109">Need follow-up</span>
                <strong>{{ number_format($stats['follow_up']['value']) }}</strong>
                <small>{{ $stats['follow_up']['sub'] }}</small>
                <span class="sa-stat-action-v109">View details →</span>
            </button>
        </div>

        <div class="sa-main-grid-v109">
            <section class="sa-panel-v109 sa-attention-v109">
                <header class="sa-panel-head-v109">
                    <div>
                        <h2>Worth your attention today</h2>
                        <p>Highest-priority athlete follow-ups based on live PLYRCARD data.</p>
                    </div>
                    <button
                        type="button"
                        class="sa-count-chip-v109 sa-count-button-v109"
                        x-on:click="followUpOpen = true; document.body.style.overflow = 'hidden'"
                    >{{ number_format($attention_total) }} total · View all</button>
                </header>

                @if(count($attention))
                    <div class="sa-attention-list-v109">
                        @foreach($attention as $athlete)
                            @php($flag = $athlete['top_flag'])
                            <div class="sa-athlete-row-v109">
                                <span class="sa-avatar-v109">{{ $athlete['initials'] }}</span>
                                <span class="sa-athlete-main-v109">
                                    <strong>{{ $athlete['name'] }}</strong>
                                    <small>{{ $athlete['sport'] }} · {{ $athlete['plan'] }}</small>
                                </span>
                                <span class="sa-pill-v109 is-{{ $flag['tone'] ?? 'warning' }}">{{ $flag['label'] ?? 'Needs follow-up' }}</span>
                                <span class="sa-health-v109">
                                    <span><i style="width: {{ max(4, (int) $athlete['health']) }}%"></i></span>
                                    <small>{{ $athlete['health'] }}%</small>
                                </span>
                                <span class="sa-actions-v109">
                                    <button
                                        type="button"
                                        class="sa-review-v109"
                                        wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int) $athlete['id'] }}, concern: '{{ $athlete['reminder_concern'] }}' })"
                                    >Remind</button>
                                    <span class="sa-impersonate-action-v109">
                                        {{ ($this->impersonateAction)(['userId' => (int) $athlete['id']]) }}
                                    </span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="sa-empty-v109">Nothing urgent is currently flagged.</div>
                @endif
            </section>

            <div class="sa-side-stack-v109">
                <section class="sa-panel-v109">
                    <header class="sa-panel-head-v109 is-compact">
                        <div>
                            <h2>Athletes by sport</h2>
                            <p>Current platform distribution</p>
                        </div>
                    </header>
                    <div class="sa-sports-v109">
                        @forelse($sports as $sport)
                            <div class="sa-sport-row-v109">
                                <strong>{{ $sport['sport'] }}</strong>
                                <span class="sa-sport-bar-v109"><i style="width: {{ $sport['percent'] }}%"></i></span>
                                <small>{{ number_format($sport['count']) }}</small>
                            </div>
                        @empty
                            <div class="sa-empty-v109">No athlete sports are available yet.</div>
                        @endforelse
                    </div>
                </section>

                <section class="sa-panel-v109">
                    <header class="sa-panel-head-v109 is-compact">
                        <div>
                            <h2>Reminders this week</h2>
                            <p>Successful Admin Support emails from the last 7 days</p>
                        </div>
                    </header>
                    <div class="sa-reminders-v109">
                        @forelse($reminders as $reminder)
                            <div class="sa-reminder-v109">
                                <span class="sa-reminder-dot-v109 {{ $reminder['success'] ? 'is-success' : 'is-danger' }}"></span>
                                <span>
                                    <strong>{{ $reminder['name'] }} — {{ $reminder['label'] }}</strong>
                                    <small>{{ $reminder['when'] }} · Email · {{ str($reminder['status'])->replace('_', ' ')->lower() }}</small>
                                </span>
                            </div>
                        @empty
                            <div class="sa-empty-v109">No Admin Support reminders were sent in the last 7 days.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        <div
            x-cloak
            x-show="followUpOpen"
            x-transition.opacity
            class="sa-followup-backdrop-v109"
            x-on:click="followUpOpen = false; document.body.style.overflow = ''"
        ></div>

        <aside
            x-cloak
            x-show="followUpOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="sa-followup-drawer-v109"
            aria-label="Athlete follow-up details"
        >
            <div class="sa-followup-shell-v109">
                <header class="sa-followup-head-v109">
                    <div>
                        <span class="sa-followup-kicker-v109">Action center</span>
                        <h2>Need follow-up</h2>
                        <p>{{ number_format($attention_total) }} athlete{{ $attention_total === 1 ? '' : 's' }} currently have one or more actionable flags.</p>
                    </div>
                    <button
                        type="button"
                        class="sa-followup-close-v109"
                        aria-label="Close follow-up details"
                        x-on:click="followUpOpen = false; document.body.style.overflow = ''"
                    >×</button>
                </header>

                <div class="sa-followup-summary-v109">
                    <span><b>{{ number_format($follow_up_breakdown['billing'] ?? 0) }}</b> Billing</span>
                    <span><b>{{ number_format($follow_up_breakdown['profile'] ?? 0) }}</b> Profile</span>
                    <span><b>{{ number_format($follow_up_breakdown['media'] ?? 0) }}</b> Media</span>
                    <span><b>{{ number_format($follow_up_breakdown['outreach'] ?? 0) }}</b> Outreach</span>
                </div>

                <div class="sa-followup-list-v109">
                    @forelse($attention_details as $athlete)
                        <article class="sa-followup-card-v109">
                            <header class="sa-followup-card-head-v109">
                                <span class="sa-followup-avatar-v109">{{ $athlete['initials'] }}</span>
                                <span class="sa-followup-athlete-v109">
                                    <strong>{{ $athlete['name'] }}</strong>
                                    <small>{{ $athlete['sport'] }} · {{ $athlete['plan'] }}</small>
                                    @if(!empty($athlete['email']))
                                        <small>{{ $athlete['email'] }}</small>
                                    @endif
                                </span>
                                <span class="sa-followup-health-v109">{{ $athlete['health'] }}%</span>
                            </header>

                            <div class="sa-followup-flags-v109">
                                @foreach($athlete['flags'] as $flag)
                                    <div class="sa-followup-flag-v109 is-{{ $flag['tone'] ?? 'warning' }}">
                                        <div class="sa-followup-flag-copy-v109">
                                            <strong>{{ $flag['label'] }}</strong>
                                            <p>{{ $flag['detail'] ?? '' }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            class="sa-followup-message-v109"
                                            x-on:click="followUpOpen = false; document.body.style.overflow = ''"
                                            wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int) $athlete['id'] }}, concern: '{{ $flag['concern'] ?? 'custom' }}' })"
                                        >{{ $flag['action_label'] ?? 'Send support message' }}</button>
                                    </div>
                                @endforeach
                            </div>

                            <footer class="sa-followup-card-actions-v109">
                                @if(!empty($athlete['user_url']))
                                    <a href="{{ $athlete['user_url'] }}" class="sa-followup-link-v109">View player details</a>
                                @endif
                                <button
                                    type="button"
                                    class="sa-followup-link-v109"
                                    x-on:click="followUpOpen = false; document.body.style.overflow = ''"
                                    wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int) $athlete['id'] }}, concern: '{{ $athlete['reminder_concern'] }}' })"
                                >Message highest-priority issue</button>
                                <span class="sa-followup-impersonate-v109">
                                    {{ ($this->impersonateAction)(['userId' => (int) $athlete['id']]) }}
                                </span>
                            </footer>
                        </article>
                    @empty
                        <div class="sa-empty-v109">There are no athletes currently flagged for follow-up.</div>
                    @endforelse
                </div>
            </div>
        </aside>

        @if(!$login_tracking_available)
            <p class="sa-note-v109">
                Login history is not currently stored on users, so this version does not fabricate “Never logged in” or “Dormant” alerts. Those can be added once login tracking is introduced.
            </p>
        @endif
    </div>

    <x-filament-actions::modals />

    <style>
        .sa-overview-v109{--sa-orange:#ff6338;--sa-text:#111827;--sa-muted:#7b8799;--sa-border:#e5e7eb;--sa-card:#fff;--sa-soft:#f8fafc;display:flex;flex-direction:column;gap:1rem;width:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif}.dark .sa-overview-v109{--sa-text:#f8fafc;--sa-muted:#94a3b8;--sa-border:rgba(148,163,184,.16);--sa-card:#0f172a;--sa-soft:#111827}
        .sa-stats-v109{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.9rem}.sa-stat-v109,.sa-panel-v109{border:1px solid var(--sa-border);background:var(--sa-card);border-radius:1rem;box-shadow:0 1px 2px rgba(15,23,42,.03)}.sa-stat-button-v109{width:100%;text-align:left;cursor:pointer;font:inherit;color:inherit}.sa-stat-button-v109:hover{border-color:rgba(255,99,56,.38);background:rgba(255,99,56,.025)}.sa-stat-button-v109:focus-visible{outline:2px solid rgba(255,99,56,.28);outline-offset:2px}.sa-stat-action-v109{margin-top:.45rem;color:var(--sa-orange);font-size:.68rem;font-weight:800}.sa-count-button-v109{cursor:pointer;font:inherit}.sa-count-button-v109:hover{border-color:rgba(255,99,56,.35);color:var(--sa-orange)}.dark .sa-stat-v109,.dark .sa-panel-v109{box-shadow:none}.sa-stat-v109{position:relative;min-height:9.4rem;padding:1rem;display:flex;flex-direction:column;align-items:flex-start}.sa-stat-icon-v109{width:2rem;height:2rem;display:grid;place-items:center;border-radius:.55rem;margin-bottom:.85rem}.sa-stat-icon-v109 svg{width:1rem;height:1rem}.sa-stat-icon-v109.is-orange{background:rgba(255,99,56,.11);color:var(--sa-orange)}.sa-stat-icon-v109.is-green{background:rgba(16,185,129,.11);color:#059669}.sa-stat-icon-v109.is-amber{background:rgba(245,158,11,.12);color:#d97706}.sa-stat-label-v109{font-size:.78rem;font-weight:650;color:var(--sa-muted)}.sa-stat-v109 strong{margin-top:.22rem;color:var(--sa-text);font-size:1.75rem;line-height:1.05;font-weight:800;letter-spacing:-.035em}.sa-stat-v109 small{margin-top:auto;padding-top:.65rem;color:var(--sa-muted);font-size:.75rem;line-height:1.35}
        .sa-main-grid-v109{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(22rem,1fr);gap:1rem;align-items:start}.sa-side-stack-v109{display:flex;flex-direction:column;gap:1rem}.sa-panel-v109{overflow:hidden}.sa-panel-head-v109{min-height:3.75rem;padding:.9rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;border-bottom:1px solid var(--sa-border)}.sa-panel-head-v109.is-compact{min-height:auto}.sa-panel-head-v109 h2{margin:0;color:var(--sa-text);font-size:.94rem;font-weight:800;letter-spacing:-.015em}.sa-panel-head-v109 p{margin:.22rem 0 0;color:var(--sa-muted);font-size:.7rem;line-height:1.35}.sa-count-chip-v109{padding:.36rem .56rem;border:1px solid var(--sa-border);border-radius:.55rem;color:var(--sa-muted);font-size:.68rem;font-weight:750;background:var(--sa-soft)}
        .sa-attention-list-v109{display:flex;flex-direction:column}.sa-athlete-row-v109{display:grid;grid-template-columns:2.15rem minmax(9rem,1.35fr) minmax(7.8rem,.85fr) minmax(7.4rem,.8fr) auto;align-items:center;gap:.7rem;padding:.72rem 1rem;border-bottom:1px solid var(--sa-border);text-decoration:none;transition:background .16s ease}.sa-athlete-row-v109:last-child{border-bottom:0}.sa-athlete-row-v109:hover{background:rgba(255,99,56,.035)}.dark .sa-athlete-row-v109:hover{background:rgba(255,99,56,.055)}.sa-avatar-v109{width:2.15rem;height:2.15rem;display:grid;place-items:center;border:1px solid var(--sa-border);border-radius:.65rem;background:var(--sa-soft);color:var(--sa-text);font-size:.68rem;font-weight:850}.sa-athlete-main-v109{min-width:0;display:flex;flex-direction:column}.sa-athlete-main-v109 strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-text);font-size:.78rem;font-weight:800}.sa-athlete-main-v109 small{margin-top:.16rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-muted);font-size:.68rem}.sa-pill-v109{justify-self:start;padding:.3rem .5rem;border-radius:999px;font-size:.65rem;font-weight:800;white-space:nowrap;border:1px solid transparent}.sa-pill-v109.is-danger{color:#dc2626;background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.22)}.sa-pill-v109.is-warning{color:#b45309;background:rgba(245,158,11,.09);border-color:rgba(245,158,11,.24)}.sa-health-v109{display:flex;align-items:center;gap:.5rem}.sa-health-v109>span{height:.3rem;flex:1;min-width:3.8rem;overflow:hidden;border-radius:999px;background:#e5e7eb}.dark .sa-health-v109>span{background:rgba(148,163,184,.17)}.sa-health-v109 i{display:block;height:100%;border-radius:inherit;background:var(--sa-orange)}.sa-health-v109 small{width:2.15rem;text-align:right;color:var(--sa-muted);font-size:.64rem}.sa-actions-v109{display:inline-flex;align-items:center;justify-self:end;gap:.38rem}.sa-review-v109{cursor:pointer;padding:.38rem .55rem;border:1px solid var(--sa-border);border-radius:.55rem;background:var(--sa-card);color:var(--sa-text);font-size:.66rem;font-weight:800}.sa-athlete-row-v109:hover .sa-review-v109{border-color:rgba(255,99,56,.35);color:var(--sa-orange)}.sa-impersonate-action-v109{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}.sa-impersonate-action-v109 .fi-icon-btn{width:1.95rem;height:1.95rem;min-width:1.95rem;border:1px solid var(--sa-border);border-radius:.55rem;background:var(--sa-card);color:var(--sa-muted)}.sa-impersonate-action-v109 .fi-icon-btn:hover{border-color:rgba(255,99,56,.42);background:rgba(255,99,56,.06);color:var(--sa-orange)}
        .sa-sports-v109{padding:.85rem 1rem;display:flex;flex-direction:column;gap:.72rem}.sa-sport-row-v109{display:grid;grid-template-columns:6.6rem 1fr 1.8rem;align-items:center;gap:.7rem}.sa-sport-row-v109 strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-text);font-size:.72rem;font-weight:750}.sa-sport-row-v109 small{text-align:right;color:var(--sa-muted);font-size:.68rem}.sa-sport-bar-v109{height:.3rem;overflow:hidden;border-radius:999px;background:#e5e7eb}.dark .sa-sport-bar-v109{background:rgba(148,163,184,.17)}.sa-sport-bar-v109 i{display:block;height:100%;border-radius:inherit;background:var(--sa-orange)}
        .sa-reminders-v109{padding:.78rem 1rem}.sa-reminder-v109{display:grid;grid-template-columns:.48rem 1fr;gap:.7rem;align-items:start;padding:.62rem 0;border-bottom:1px solid var(--sa-border)}.sa-reminder-v109:last-child{border-bottom:0}.sa-reminder-dot-v109{width:.42rem;height:.42rem;margin-top:.28rem;border-radius:999px;background:#94a3b8}.sa-reminder-dot-v109.is-success{background:#10b981}.sa-reminder-dot-v109.is-danger{background:#ef4444}.sa-reminder-v109 strong{display:block;color:var(--sa-text);font-size:.71rem;font-weight:750;line-height:1.35}.sa-reminder-v109 small{display:block;margin-top:.18rem;color:var(--sa-muted);font-size:.66rem}.sa-empty-v109{padding:1rem;color:var(--sa-muted);font-size:.75rem}.sa-note-v109{margin:0;padding:.7rem .85rem;border:1px dashed var(--sa-border);border-radius:.8rem;background:var(--sa-soft);color:var(--sa-muted);font-size:.7rem;line-height:1.45}
        .sa-followup-backdrop-v109{position:fixed;inset:0;z-index:10080;background:rgba(2,6,12,.48);backdrop-filter:blur(2px)}.sa-followup-drawer-v109{position:fixed;top:0;right:0;z-index:10090;width:min(620px,100vw);height:100dvh;background:var(--sa-card);color:var(--sa-text);border-left:1px solid var(--sa-border);box-shadow:-24px 0 60px rgba(15,23,42,.18);overflow:hidden}.dark .sa-followup-drawer-v109{box-shadow:-24px 0 60px rgba(0,0,0,.4)}.sa-followup-shell-v109{height:100%;overflow-y:auto}.sa-followup-head-v109{position:sticky;top:0;z-index:3;display:flex;justify-content:space-between;gap:1rem;padding:1.1rem 1.2rem;background:color-mix(in srgb,var(--sa-card) 94%,transparent);backdrop-filter:blur(12px);border-bottom:1px solid var(--sa-border)}.sa-followup-kicker-v109{display:block;color:var(--sa-orange);font-size:.62rem;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.sa-followup-head-v109 h2{margin:.25rem 0 0;font-size:1.2rem;font-weight:850;letter-spacing:-.025em}.sa-followup-head-v109 p{margin:.25rem 0 0;color:var(--sa-muted);font-size:.7rem;line-height:1.45}.sa-followup-close-v109{width:2rem;height:2rem;flex:0 0 auto;border:1px solid var(--sa-border);border-radius:.6rem;background:var(--sa-soft);color:var(--sa-muted);font-size:1.15rem;cursor:pointer}.sa-followup-summary-v109{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.55rem;padding:.85rem 1.2rem;border-bottom:1px solid var(--sa-border)}.sa-followup-summary-v109 span{padding:.55rem .6rem;border:1px solid var(--sa-border);border-radius:.65rem;background:var(--sa-soft);color:var(--sa-muted);font-size:.63rem}.sa-followup-summary-v109 b{display:block;margin-bottom:.08rem;color:var(--sa-text);font-size:.95rem}.sa-followup-list-v109{padding:.9rem 1.2rem 1.4rem;display:flex;flex-direction:column;gap:.8rem}.sa-followup-card-v109{overflow:hidden;border:1px solid var(--sa-border);border-radius:.85rem;background:var(--sa-card)}.sa-followup-card-head-v109{display:grid;grid-template-columns:2.25rem minmax(0,1fr) auto;gap:.7rem;align-items:center;padding:.8rem .9rem;border-bottom:1px solid var(--sa-border)}.sa-followup-avatar-v109{width:2.25rem;height:2.25rem;display:grid;place-items:center;border-radius:.65rem;background:var(--sa-soft);border:1px solid var(--sa-border);font-size:.68rem;font-weight:850}.sa-followup-athlete-v109{min-width:0}.sa-followup-athlete-v109 strong{display:block;font-size:.78rem;font-weight:850}.sa-followup-athlete-v109 small{display:block;margin-top:.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-muted);font-size:.62rem}.sa-followup-health-v109{padding:.3rem .42rem;border-radius:999px;background:rgba(255,99,56,.08);color:var(--sa-orange);font-size:.62rem;font-weight:850}.sa-followup-flags-v109{padding:.25rem .9rem}.sa-followup-flag-v109{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.7rem;align-items:center;padding:.72rem 0;border-bottom:1px solid var(--sa-border)}.sa-followup-flag-v109:last-child{border-bottom:0}.sa-followup-flag-copy-v109 strong{display:block;font-size:.71rem;font-weight:820}.sa-followup-flag-copy-v109 p{margin:.14rem 0 0;color:var(--sa-muted);font-size:.62rem;line-height:1.4}.sa-followup-flag-v109.is-danger .sa-followup-flag-copy-v109 strong{color:#dc2626}.sa-followup-message-v109{padding:.4rem .5rem;border:1px solid var(--sa-border);border-radius:.55rem;background:var(--sa-card);color:var(--sa-text);font-size:.61rem;font-weight:800;cursor:pointer;white-space:nowrap}.sa-followup-message-v109:hover{border-color:rgba(255,99,56,.4);color:var(--sa-orange)}.sa-followup-card-actions-v109{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;padding:.65rem .9rem;background:var(--sa-soft);border-top:1px solid var(--sa-border)}.sa-followup-link-v109{border:0;background:transparent;padding:.28rem 0;color:var(--sa-orange);font:inherit;font-size:.61rem;font-weight:800;cursor:pointer;text-decoration:none}.sa-followup-impersonate-v109{margin-left:auto}.sa-followup-impersonate-v109 .fi-icon-btn{width:1.9rem;height:1.9rem;min-width:1.9rem;border:1px solid var(--sa-border);border-radius:.55rem;background:var(--sa-card);color:var(--sa-muted)}[x-cloak]{display:none!important}
        @media(max-width:1100px){.sa-main-grid-v109{grid-template-columns:1fr}.sa-side-stack-v109{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:820px){.sa-stats-v109{grid-template-columns:1fr}.sa-stat-v109{min-height:7.8rem}.sa-side-stack-v109{grid-template-columns:1fr}.sa-athlete-row-v109{grid-template-columns:2.15rem minmax(0,1fr) auto}.sa-pill-v109{grid-column:2}.sa-health-v109{display:none}.sa-actions-v109{grid-column:3;grid-row:1 / span 2}.sa-review-v109{grid-column:auto;grid-row:auto}}@media(max-width:640px){.sa-followup-summary-v109{grid-template-columns:repeat(2,minmax(0,1fr))}.sa-followup-flag-v109{grid-template-columns:1fr}.sa-followup-message-v109{justify-self:start}.sa-followup-head-v109,.sa-followup-summary-v109,.sa-followup-list-v109{padding-left:.85rem;padding-right:.85rem}}@media(max-width:520px){.sa-athlete-row-v109{padding:.72rem}.sa-panel-head-v109{align-items:flex-start}.sa-sport-row-v109{grid-template-columns:5.7rem 1fr 1.6rem}}
    </style>
</x-filament-widgets::widget>