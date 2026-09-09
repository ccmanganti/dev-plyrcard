<x-filament-widgets::widget>
    <div class="sa-overview-v109">
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

            <section class="sa-stat-v109">
                <span class="sa-stat-icon-v109 is-amber" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M10.3 3.6L2.8 17a2 2 0 0 0 1.75 3h14.9a2 2 0 0 0 1.75-3L13.7 3.6a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4M12 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="sa-stat-label-v109">Need follow-up</span>
                <strong>{{ number_format($stats['follow_up']['value']) }}</strong>
                <small>{{ $stats['follow_up']['sub'] }}</small>
            </section>
        </div>

        <div class="sa-main-grid-v109">
            <section class="sa-panel-v109 sa-attention-v109">
                <header class="sa-panel-head-v109">
                    <div>
                        <h2>Worth your attention today</h2>
                        <p>Highest-priority athlete follow-ups based on live PLYRCARD data.</p>
                    </div>
                    <span class="sa-count-chip-v109">{{ number_format($attention_total) }} total</span>
                </header>

                @if(count($attention))
                    <div class="sa-attention-list-v109">
                        @foreach($attention as $athlete)
                            @php($flag = $athlete['top_flag'])
                            <a href="{{ $athlete['user_url'] }}" class="sa-athlete-row-v109">
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
                                <span class="sa-review-v109">Review</span>
                            </a>
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

        @if(!$login_tracking_available)
            <p class="sa-note-v109">
                Login history is not currently stored on users, so this version does not fabricate “Never logged in” or “Dormant” alerts. Those can be added once login tracking is introduced.
            </p>
        @endif
    </div>

    <style>
        .sa-overview-v109{--sa-orange:#ff6338;--sa-text:#111827;--sa-muted:#7b8799;--sa-border:#e5e7eb;--sa-card:#fff;--sa-soft:#f8fafc;display:flex;flex-direction:column;gap:1rem;width:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif}.dark .sa-overview-v109{--sa-text:#f8fafc;--sa-muted:#94a3b8;--sa-border:rgba(148,163,184,.16);--sa-card:#0f172a;--sa-soft:#111827}
        .sa-stats-v109{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.9rem}.sa-stat-v109,.sa-panel-v109{border:1px solid var(--sa-border);background:var(--sa-card);border-radius:1rem;box-shadow:0 1px 2px rgba(15,23,42,.03)}.dark .sa-stat-v109,.dark .sa-panel-v109{box-shadow:none}.sa-stat-v109{position:relative;min-height:9.4rem;padding:1rem;display:flex;flex-direction:column;align-items:flex-start}.sa-stat-icon-v109{width:2rem;height:2rem;display:grid;place-items:center;border-radius:.55rem;margin-bottom:.85rem}.sa-stat-icon-v109 svg{width:1rem;height:1rem}.sa-stat-icon-v109.is-orange{background:rgba(255,99,56,.11);color:var(--sa-orange)}.sa-stat-icon-v109.is-green{background:rgba(16,185,129,.11);color:#059669}.sa-stat-icon-v109.is-amber{background:rgba(245,158,11,.12);color:#d97706}.sa-stat-label-v109{font-size:.78rem;font-weight:650;color:var(--sa-muted)}.sa-stat-v109 strong{margin-top:.22rem;color:var(--sa-text);font-size:1.75rem;line-height:1.05;font-weight:800;letter-spacing:-.035em}.sa-stat-v109 small{margin-top:auto;padding-top:.65rem;color:var(--sa-muted);font-size:.75rem;line-height:1.35}
        .sa-main-grid-v109{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(22rem,1fr);gap:1rem;align-items:start}.sa-side-stack-v109{display:flex;flex-direction:column;gap:1rem}.sa-panel-v109{overflow:hidden}.sa-panel-head-v109{min-height:3.75rem;padding:.9rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;border-bottom:1px solid var(--sa-border)}.sa-panel-head-v109.is-compact{min-height:auto}.sa-panel-head-v109 h2{margin:0;color:var(--sa-text);font-size:.94rem;font-weight:800;letter-spacing:-.015em}.sa-panel-head-v109 p{margin:.22rem 0 0;color:var(--sa-muted);font-size:.7rem;line-height:1.35}.sa-count-chip-v109{padding:.36rem .56rem;border:1px solid var(--sa-border);border-radius:.55rem;color:var(--sa-muted);font-size:.68rem;font-weight:750;background:var(--sa-soft)}
        .sa-attention-list-v109{display:flex;flex-direction:column}.sa-athlete-row-v109{display:grid;grid-template-columns:2.15rem minmax(9rem,1.35fr) minmax(7.8rem,.85fr) minmax(7.4rem,.8fr) auto;align-items:center;gap:.7rem;padding:.72rem 1rem;border-bottom:1px solid var(--sa-border);text-decoration:none;transition:background .16s ease}.sa-athlete-row-v109:last-child{border-bottom:0}.sa-athlete-row-v109:hover{background:rgba(255,99,56,.035)}.dark .sa-athlete-row-v109:hover{background:rgba(255,99,56,.055)}.sa-avatar-v109{width:2.15rem;height:2.15rem;display:grid;place-items:center;border:1px solid var(--sa-border);border-radius:.65rem;background:var(--sa-soft);color:var(--sa-text);font-size:.68rem;font-weight:850}.sa-athlete-main-v109{min-width:0;display:flex;flex-direction:column}.sa-athlete-main-v109 strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-text);font-size:.78rem;font-weight:800}.sa-athlete-main-v109 small{margin-top:.16rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-muted);font-size:.68rem}.sa-pill-v109{justify-self:start;padding:.3rem .5rem;border-radius:999px;font-size:.65rem;font-weight:800;white-space:nowrap;border:1px solid transparent}.sa-pill-v109.is-danger{color:#dc2626;background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.22)}.sa-pill-v109.is-warning{color:#b45309;background:rgba(245,158,11,.09);border-color:rgba(245,158,11,.24)}.sa-health-v109{display:flex;align-items:center;gap:.5rem}.sa-health-v109>span{height:.3rem;flex:1;min-width:3.8rem;overflow:hidden;border-radius:999px;background:#e5e7eb}.dark .sa-health-v109>span{background:rgba(148,163,184,.17)}.sa-health-v109 i{display:block;height:100%;border-radius:inherit;background:var(--sa-orange)}.sa-health-v109 small{width:2.15rem;text-align:right;color:var(--sa-muted);font-size:.64rem}.sa-review-v109{padding:.38rem .55rem;border:1px solid var(--sa-border);border-radius:.55rem;background:var(--sa-card);color:var(--sa-text);font-size:.66rem;font-weight:800}.sa-athlete-row-v109:hover .sa-review-v109{border-color:rgba(255,99,56,.35);color:var(--sa-orange)}
        .sa-sports-v109{padding:.85rem 1rem;display:flex;flex-direction:column;gap:.72rem}.sa-sport-row-v109{display:grid;grid-template-columns:6.6rem 1fr 1.8rem;align-items:center;gap:.7rem}.sa-sport-row-v109 strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-text);font-size:.72rem;font-weight:750}.sa-sport-row-v109 small{text-align:right;color:var(--sa-muted);font-size:.68rem}.sa-sport-bar-v109{height:.3rem;overflow:hidden;border-radius:999px;background:#e5e7eb}.dark .sa-sport-bar-v109{background:rgba(148,163,184,.17)}.sa-sport-bar-v109 i{display:block;height:100%;border-radius:inherit;background:var(--sa-orange)}
        .sa-reminders-v109{padding:.78rem 1rem}.sa-reminder-v109{display:grid;grid-template-columns:.48rem 1fr;gap:.7rem;align-items:start;padding:.62rem 0;border-bottom:1px solid var(--sa-border)}.sa-reminder-v109:last-child{border-bottom:0}.sa-reminder-dot-v109{width:.42rem;height:.42rem;margin-top:.28rem;border-radius:999px;background:#94a3b8}.sa-reminder-dot-v109.is-success{background:#10b981}.sa-reminder-dot-v109.is-danger{background:#ef4444}.sa-reminder-v109 strong{display:block;color:var(--sa-text);font-size:.71rem;font-weight:750;line-height:1.35}.sa-reminder-v109 small{display:block;margin-top:.18rem;color:var(--sa-muted);font-size:.66rem}.sa-empty-v109{padding:1rem;color:var(--sa-muted);font-size:.75rem}.sa-note-v109{margin:0;padding:.7rem .85rem;border:1px dashed var(--sa-border);border-radius:.8rem;background:var(--sa-soft);color:var(--sa-muted);font-size:.7rem;line-height:1.45}
        @media(max-width:1100px){.sa-main-grid-v109{grid-template-columns:1fr}.sa-side-stack-v109{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:820px){.sa-stats-v109{grid-template-columns:1fr}.sa-stat-v109{min-height:7.8rem}.sa-side-stack-v109{grid-template-columns:1fr}.sa-athlete-row-v109{grid-template-columns:2.15rem minmax(0,1fr) auto}.sa-pill-v109{grid-column:2}.sa-health-v109{display:none}.sa-review-v109{grid-column:3;grid-row:1 / span 2}}@media(max-width:520px){.sa-athlete-row-v109{padding:.72rem}.sa-panel-head-v109{align-items:flex-start}.sa-sport-row-v109{grid-template-columns:5.7rem 1fr 1.6rem}}
    </style>
</x-filament-widgets::widget>
