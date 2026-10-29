<x-filament-widgets::widget>
    <div class="sa-activity-v110">
        <header class="sa-activity-head-v110">
            <div>
                <span class="sa-activity-kicker-v110">Quick views</span>
                <h2>Recent activity</h2>
                <p>Latest service requests, player signups, and successful payments across PLYRCARD.</p>
            </div>
            <span class="sa-updated-v110">Updated {{ $lastUpdated }}</span>
        </header>

        <div class="sa-activity-grid-v110">
            <section class="sa-activity-panel-v110">
                <header class="sa-card-head-v110">
                    <span class="sa-card-icon-v110 is-orange" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M9 11h6M9 15h4M8 3h8l4 4v14H4V3h4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 3v5h5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    </span>
                    <span>
                        <strong>Service requests</strong>
                        <small>{{ $serviceActivity['summary'] }}</small>
                    </span>
                    @if($serviceActivity['all_url'])
                        <a href="{{ $serviceActivity['all_url'] }}" class="sa-view-all-v110">View all</a>
                    @endif
                </header>

                <div class="sa-activity-list-v110">
                    @forelse($serviceActivity['items'] as $request)
                        @php($tag = $request['rush'] ? 'Rush · ' . $request['status'] : $request['status'])
                        @if($request['url'])
                            <a href="{{ $request['url'] }}" class="sa-activity-row-v110">
                        @else
                            <div class="sa-activity-row-v110">
                        @endif
                            <span class="sa-avatar-v110">{{ $request['initials'] }}</span>
                            <span class="sa-row-main-v110">
                                <strong>{{ $request['service'] }}@if($request['quantity'] > 1) × {{ $request['quantity'] }}@endif</strong>
                                <small>{{ $request['player'] }} · {{ number_format($request['points']) }} pts · {{ $request['when'] }}</small>
                            </span>
                            <span class="sa-status-v110 is-{{ $request['status_tone'] }}">{{ $tag }}</span>
                        @if($request['url'])
                            </a>
                        @else
                            </div>
                        @endif
                    @empty
                        <div class="sa-empty-v110">No recent service requests.</div>
                    @endforelse
                </div>
            </section>

            <section class="sa-activity-panel-v110">
                <header class="sa-card-head-v110">
                    <span class="sa-card-icon-v110 is-blue" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M15 19a6 6 0 0 0-12 0M9 11a4 4 0 1 0 0-8a4 4 0 0 0 0 8ZM19 8v6M16 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </span>
                    <span>
                        <strong>New signups</strong>
                        <small>{{ $signupActivity['summary'] }}</small>
                    </span>
                    @if($signupActivity['all_url'])
                        <a href="{{ $signupActivity['all_url'] }}" class="sa-view-all-v110">View all</a>
                    @endif
                </header>

                <div class="sa-activity-list-v110">
                    @forelse($signupActivity['items'] as $signup)
                        @if($signup['url'])
                            <a href="{{ $signup['url'] }}" class="sa-activity-row-v110">
                        @else
                            <div class="sa-activity-row-v110">
                        @endif
                            <span class="sa-avatar-v110">{{ $signup['initials'] }}</span>
                            <span class="sa-row-main-v110">
                                <strong>{{ $signup['name'] }}</strong>
                                <small>{{ $signup['sport'] }} · {{ $signup['when'] }}</small>
                            </span>
                            <span class="sa-plan-v110">{{ $signup['plan'] }}</span>
                        @if($signup['url'])
                            </a>
                        @else
                            </div>
                        @endif
                    @empty
                        <div class="sa-empty-v110">No recent player signups.</div>
                    @endforelse
                </div>
            </section>

            <section class="sa-activity-panel-v110">
                <header class="sa-card-head-v110">
                    <span class="sa-card-icon-v110 is-green" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M3 7h18v12H3V7Zm0 4h18M7 16h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span>
                        <strong>Recent payments</strong>
                        <small>{{ $paymentActivity['summary'] }}</small>
                    </span>
                </header>

                <div class="sa-activity-list-v110">
                    @forelse($paymentActivity['items'] as $payment)
                        @if($payment['url'])
                            <a href="{{ $payment['url'] }}" class="sa-activity-row-v110">
                        @else
                            <div class="sa-activity-row-v110">
                        @endif
                            <span class="sa-avatar-v110">{{ $payment['initials'] }}</span>
                            <span class="sa-row-main-v110">
                                <strong>{{ $payment['player'] }}</strong>
                                <small>{{ $payment['source'] }} · {{ $payment['provider'] }} · {{ $payment['when'] }}</small>
                            </span>
                            <span class="sa-payment-v110">{{ $payment['amount'] }}</span>
                        @if($payment['url'])
                            </a>
                        @else
                            </div>
                        @endif
                    @empty
                        <div class="sa-empty-v110">No successful payments have been recorded yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <style>
        .sa-activity-v110{--sa-a-text:#111827;--sa-a-muted:#7b8799;--sa-a-border:#e5e7eb;--sa-a-card:#fff;--sa-a-soft:#f8fafc;--sa-a-orange:#ff6338;display:flex;flex-direction:column;gap:.8rem;width:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif}.dark .sa-activity-v110{--sa-a-text:#f8fafc;--sa-a-muted:#94a3b8;--sa-a-border:rgba(148,163,184,.16);--sa-a-card:#0f172a;--sa-a-soft:#111827}
        .sa-activity-head-v110{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding:.1rem .1rem .2rem}.sa-activity-kicker-v110{display:block;margin-bottom:.22rem;color:var(--sa-a-orange);font-size:.66rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.sa-activity-head-v110 h2{margin:0;color:var(--sa-a-text);font-size:1.05rem;font-weight:850;letter-spacing:-.02em}.sa-activity-head-v110 p{margin:.25rem 0 0;color:var(--sa-a-muted);font-size:.72rem}.sa-updated-v110{color:var(--sa-a-muted);font-size:.66rem;white-space:nowrap}
        .sa-activity-grid-v110{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.9rem}.sa-activity-panel-v110{min-width:0;overflow:hidden;border:1px solid var(--sa-a-border);border-radius:1rem;background:var(--sa-a-card);box-shadow:0 1px 2px rgba(15,23,42,.03)}.dark .sa-activity-panel-v110{box-shadow:none}
        .sa-card-head-v110{min-height:4.25rem;padding:.85rem .9rem;display:grid;grid-template-columns:2rem minmax(0,1fr) auto;align-items:center;gap:.65rem;border-bottom:1px solid var(--sa-a-border)}.sa-card-icon-v110{width:2rem;height:2rem;display:grid;place-items:center;border-radius:.6rem}.sa-card-icon-v110 svg{width:1rem;height:1rem}.sa-card-icon-v110.is-orange{background:rgba(255,99,56,.11);color:var(--sa-a-orange)}.sa-card-icon-v110.is-blue{background:rgba(59,130,246,.11);color:#2563eb}.sa-card-icon-v110.is-green{background:rgba(16,185,129,.11);color:#059669}.sa-card-head-v110>span:nth-child(2){min-width:0}.sa-card-head-v110 strong{display:block;color:var(--sa-a-text);font-size:.78rem;font-weight:850}.sa-card-head-v110 small{display:block;margin-top:.12rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-a-muted);font-size:.64rem}.sa-view-all-v110{padding:.32rem .48rem;border:1px solid var(--sa-a-border);border-radius:.5rem;color:var(--sa-a-muted);font-size:.62rem;font-weight:800;text-decoration:none;white-space:nowrap}.sa-view-all-v110:hover{border-color:rgba(255,99,56,.35);color:var(--sa-a-orange)}
        .sa-activity-list-v110{display:flex;flex-direction:column}.sa-activity-row-v110{min-width:0;display:grid;grid-template-columns:2rem minmax(0,1fr) auto;align-items:center;gap:.62rem;padding:.68rem .9rem;border-bottom:1px solid var(--sa-a-border);color:inherit;text-decoration:none;transition:background .15s ease}.sa-activity-row-v110:last-child{border-bottom:0}.sa-activity-row-v110:hover{background:rgba(255,99,56,.035)}.dark .sa-activity-row-v110:hover{background:rgba(255,99,56,.055)}.sa-avatar-v110{width:2rem;height:2rem;display:grid;place-items:center;border:1px solid var(--sa-a-border);border-radius:.62rem;background:var(--sa-a-soft);color:var(--sa-a-text);font-size:.64rem;font-weight:850}.sa-row-main-v110{min-width:0}.sa-row-main-v110 strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-a-text);font-size:.72rem;font-weight:800}.sa-row-main-v110 small{display:block;margin-top:.14rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-a-muted);font-size:.62rem}
        .sa-status-v110,.sa-plan-v110,.sa-payment-v110{justify-self:end;max-width:8.2rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.sa-status-v110{padding:.27rem .42rem;border:1px solid var(--sa-a-border);border-radius:999px;font-size:.59rem;font-weight:800}.sa-status-v110.is-orange{color:#ea580c;background:rgba(249,115,22,.08);border-color:rgba(249,115,22,.2)}.sa-status-v110.is-warning{color:#b45309;background:rgba(245,158,11,.09);border-color:rgba(245,158,11,.23)}.sa-status-v110.is-info{color:#2563eb;background:rgba(59,130,246,.08);border-color:rgba(59,130,246,.2)}.sa-status-v110.is-success{color:#059669;background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.2)}.sa-status-v110.is-neutral{color:var(--sa-a-muted);background:var(--sa-a-soft)}.sa-plan-v110{color:var(--sa-a-muted);font-size:.61rem;font-weight:800}.sa-payment-v110{color:#059669;font-size:.74rem;font-weight:900}.sa-empty-v110{padding:1rem;color:var(--sa-a-muted);font-size:.72rem}
        @media(max-width:1180px){.sa-activity-grid-v110{grid-template-columns:1fr}.sa-activity-panel-v110{max-width:none}}@media(max-width:620px){.sa-activity-head-v110{align-items:flex-start;flex-direction:column}.sa-card-head-v110{grid-template-columns:2rem minmax(0,1fr) auto}.sa-activity-row-v110{padding:.65rem .72rem}.sa-status-v110,.sa-plan-v110{max-width:6.6rem}}
    </style>
</x-filament-widgets::widget>
