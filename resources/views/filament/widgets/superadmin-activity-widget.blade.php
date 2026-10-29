<x-filament-widgets::widget>
    <div
        class="sa-activity-v110"
        x-data="{ activityDrawer: null }"
        x-on:keydown.escape.window="if (activityDrawer) { activityDrawer = null; document.body.style.overflow = ''; }"
    >
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
                    <button
                        type="button"
                        class="sa-view-all-v110"
                        x-on:click="activityDrawer = 'services'; document.body.style.overflow = 'hidden'"
                    >View all</button>
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
                    <button
                        type="button"
                        class="sa-view-all-v110"
                        x-on:click="activityDrawer = 'payments'; document.body.style.overflow = 'hidden'"
                    >View all</button>
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

        <div
            x-cloak
            x-show="activityDrawer"
            x-transition.opacity
            class="sa-drawer-backdrop-v110"
            x-on:click="activityDrawer = null; document.body.style.overflow = ''"
        ></div>

        <aside
            x-cloak
            x-show="activityDrawer === 'services'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="sa-drawer-v110"
            aria-label="All recent service requests"
        >
            <div class="sa-drawer-shell-v110">
                <header class="sa-drawer-head-v110">
                    <div>
                        <span class="sa-activity-kicker-v110">Service activity</span>
                        <h2>Recent service requests</h2>
                        <p>Latest requests submitted through the PLYR Points service system.</p>
                    </div>
                    <button type="button" class="sa-drawer-close-v110" x-on:click="activityDrawer = null; document.body.style.overflow = ''" aria-label="Close">×</button>
                </header>

                <div class="sa-drawer-list-v110">
                    @forelse($serviceActivity['drawer_items'] ?? [] as $request)
                        @php($drawerTag = $request['rush'] ? 'Rush · ' . $request['status'] : $request['status'])
                        @if($request['url'])
                            <a href="{{ $request['url'] }}" class="sa-drawer-row-v110">
                        @else
                            <div class="sa-drawer-row-v110">
                        @endif
                            <span class="sa-avatar-v110">{{ $request['initials'] }}</span>
                            <span class="sa-row-main-v110">
                                <strong>{{ $request['service'] }}@if($request['quantity'] > 1) × {{ $request['quantity'] }}@endif</strong>
                                <small>{{ $request['player'] }} · {{ number_format($request['points']) }} pts · {{ $request['when'] }}</small>
                            </span>
                            <span class="sa-status-v110 is-{{ $request['status_tone'] }}">{{ $drawerTag }}</span>
                        @if($request['url'])
                            </a>
                        @else
                            </div>
                        @endif
                    @empty
                        <div class="sa-empty-v110">No service requests are available yet.</div>
                    @endforelse
                </div>

                <footer class="sa-drawer-footer-v110">
                    @if($serviceActivity['all_url'])
                        <a href="{{ $serviceActivity['all_url'] }}" class="sa-drawer-primary-v110">Open Credit Requests</a>
                    @endif
                    <span>Review, fulfill, follow up, or return credits from the full Credit Requests area.</span>
                </footer>
            </div>
        </aside>

        <aside
            x-cloak
            x-show="activityDrawer === 'payments'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="sa-drawer-v110"
            aria-label="All recent payments"
        >
            <div class="sa-drawer-shell-v110">
                <header class="sa-drawer-head-v110">
                    <div>
                        <span class="sa-activity-kicker-v110">Billing activity</span>
                        <h2>Recent payments & purchases</h2>
                        <p>Latest successful subscriptions, plan purchases, and PLYR Points transactions.</p>
                    </div>
                    <button type="button" class="sa-drawer-close-v110" x-on:click="activityDrawer = null; document.body.style.overflow = ''" aria-label="Close">×</button>
                </header>

                <div class="sa-drawer-list-v110">
                    @forelse($paymentActivity['drawer_items'] ?? [] as $payment)
                        @if($payment['url'])
                            <a href="{{ $payment['url'] }}" class="sa-drawer-row-v110">
                        @else
                            <div class="sa-drawer-row-v110">
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

                <footer class="sa-drawer-footer-v110">
                    @if($paymentActivity['all_url'])
                        <a href="{{ $paymentActivity['all_url'] }}" class="sa-drawer-primary-v110">Manage Users & Billing</a>
                    @endif
                    <span>Open a player from this list for account, subscription, and billing details.</span>
                </footer>
            </div>
        </aside>
    </div>

    <style>
        .sa-activity-v110{--sa-a-text:#111827;--sa-a-muted:#7b8799;--sa-a-border:#e5e7eb;--sa-a-card:#fff;--sa-a-soft:#f8fafc;--sa-a-orange:#ff6338;display:flex;flex-direction:column;gap:.8rem;width:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif}.dark .sa-activity-v110{--sa-a-text:#f8fafc;--sa-a-muted:#94a3b8;--sa-a-border:rgba(148,163,184,.16);--sa-a-card:#0f172a;--sa-a-soft:#111827}
        .sa-activity-head-v110{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding:.1rem .1rem .2rem}.sa-activity-kicker-v110{display:block;margin-bottom:.22rem;color:var(--sa-a-orange);font-size:.66rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.sa-activity-head-v110 h2{margin:0;color:var(--sa-a-text);font-size:1.05rem;font-weight:850;letter-spacing:-.02em}.sa-activity-head-v110 p{margin:.25rem 0 0;color:var(--sa-a-muted);font-size:.72rem}.sa-updated-v110{color:var(--sa-a-muted);font-size:.66rem;white-space:nowrap}
        .sa-activity-grid-v110{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.9rem}.sa-activity-panel-v110{min-width:0;overflow:hidden;border:1px solid var(--sa-a-border);border-radius:1rem;background:var(--sa-a-card);box-shadow:0 1px 2px rgba(15,23,42,.03)}.dark .sa-activity-panel-v110{box-shadow:none}
        .sa-card-head-v110{min-height:4.25rem;padding:.85rem .9rem;display:grid;grid-template-columns:2rem minmax(0,1fr) auto;align-items:center;gap:.65rem;border-bottom:1px solid var(--sa-a-border)}.sa-card-icon-v110{width:2rem;height:2rem;display:grid;place-items:center;border-radius:.6rem}.sa-card-icon-v110 svg{width:1rem;height:1rem}.sa-card-icon-v110.is-orange{background:rgba(255,99,56,.11);color:var(--sa-a-orange)}.sa-card-icon-v110.is-blue{background:rgba(59,130,246,.11);color:#2563eb}.sa-card-icon-v110.is-green{background:rgba(16,185,129,.11);color:#059669}.sa-card-head-v110>span:nth-child(2){min-width:0}.sa-card-head-v110 strong{display:block;color:var(--sa-a-text);font-size:.78rem;font-weight:850}.sa-card-head-v110 small{display:block;margin-top:.12rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-a-muted);font-size:.64rem}.sa-view-all-v110{padding:.32rem .48rem;border:1px solid var(--sa-a-border);border-radius:.5rem;background:var(--sa-a-card);color:var(--sa-a-muted);font:inherit;font-size:.62rem;font-weight:800;text-decoration:none;white-space:nowrap;cursor:pointer}.sa-view-all-v110:hover{border-color:rgba(255,99,56,.35);color:var(--sa-a-orange)}
        .sa-activity-list-v110{display:flex;flex-direction:column}.sa-activity-row-v110{min-width:0;display:grid;grid-template-columns:2rem minmax(0,1fr) auto;align-items:center;gap:.62rem;padding:.68rem .9rem;border-bottom:1px solid var(--sa-a-border);color:inherit;text-decoration:none;transition:background .15s ease}.sa-activity-row-v110:last-child{border-bottom:0}.sa-activity-row-v110:hover{background:rgba(255,99,56,.035)}.dark .sa-activity-row-v110:hover{background:rgba(255,99,56,.055)}.sa-avatar-v110{width:2rem;height:2rem;display:grid;place-items:center;border:1px solid var(--sa-a-border);border-radius:.62rem;background:var(--sa-a-soft);color:var(--sa-a-text);font-size:.64rem;font-weight:850}.sa-row-main-v110{min-width:0}.sa-row-main-v110 strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-a-text);font-size:.72rem;font-weight:800}.sa-row-main-v110 small{display:block;margin-top:.14rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sa-a-muted);font-size:.62rem}
        .sa-status-v110,.sa-plan-v110,.sa-payment-v110{justify-self:end;max-width:8.2rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.sa-status-v110{padding:.27rem .42rem;border:1px solid var(--sa-a-border);border-radius:999px;font-size:.59rem;font-weight:800}.sa-status-v110.is-orange{color:#ea580c;background:rgba(249,115,22,.08);border-color:rgba(249,115,22,.2)}.sa-status-v110.is-warning{color:#b45309;background:rgba(245,158,11,.09);border-color:rgba(245,158,11,.23)}.sa-status-v110.is-info{color:#2563eb;background:rgba(59,130,246,.08);border-color:rgba(59,130,246,.2)}.sa-status-v110.is-success{color:#059669;background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.2)}.sa-status-v110.is-neutral{color:var(--sa-a-muted);background:var(--sa-a-soft)}.sa-plan-v110{color:var(--sa-a-muted);font-size:.61rem;font-weight:800}.sa-payment-v110{color:#059669;font-size:.74rem;font-weight:900}.sa-empty-v110{padding:1rem;color:var(--sa-a-muted);font-size:.72rem}
        .sa-drawer-backdrop-v110{position:fixed;inset:0;z-index:10080;background:rgba(2,6,12,.48);backdrop-filter:blur(2px)}.sa-drawer-v110{position:fixed;top:0;right:0;z-index:10090;width:min(620px,100vw);height:100dvh;background:var(--sa-a-card);color:var(--sa-a-text);border-left:1px solid var(--sa-a-border);box-shadow:-24px 0 60px rgba(15,23,42,.18);overflow:hidden}.dark .sa-drawer-v110{box-shadow:-24px 0 60px rgba(0,0,0,.4)}.sa-drawer-shell-v110{height:100%;display:flex;flex-direction:column}.sa-drawer-head-v110{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.05rem 1.1rem;border-bottom:1px solid var(--sa-a-border);background:var(--sa-a-card)}.sa-drawer-head-v110 h2{margin:.18rem 0 0;color:var(--sa-a-text);font-size:1.1rem;font-weight:850;letter-spacing:-.02em}.sa-drawer-head-v110 p{margin:.24rem 0 0;color:var(--sa-a-muted);font-size:.69rem;line-height:1.45}.sa-drawer-close-v110{width:2rem;height:2rem;flex:0 0 auto;border:1px solid var(--sa-a-border);border-radius:.6rem;background:var(--sa-a-soft);color:var(--sa-a-muted);font-size:1.15rem;cursor:pointer}.sa-drawer-list-v110{flex:1;overflow-y:auto}.sa-drawer-row-v110{display:grid;grid-template-columns:2rem minmax(0,1fr) auto;align-items:center;gap:.65rem;padding:.72rem 1rem;border-bottom:1px solid var(--sa-a-border);color:inherit;text-decoration:none}.sa-drawer-row-v110:hover{background:rgba(255,99,56,.035)}.dark .sa-drawer-row-v110:hover{background:rgba(255,99,56,.055)}.sa-drawer-footer-v110{padding:.9rem 1rem 1rem;border-top:1px solid var(--sa-a-border);background:var(--sa-a-card)}.sa-drawer-primary-v110{display:flex;align-items:center;justify-content:center;min-height:2.5rem;padding:.55rem .8rem;border-radius:.65rem;background:var(--sa-a-orange);color:#fff;font-size:.72rem;font-weight:850;text-decoration:none}.sa-drawer-footer-v110 span{display:block;margin-top:.5rem;color:var(--sa-a-muted);font-size:.62rem;line-height:1.4;text-align:center}[x-cloak]{display:none!important}
        @media(max-width:1180px){.sa-activity-grid-v110{grid-template-columns:1fr}.sa-activity-panel-v110{max-width:none}}@media(max-width:620px){.sa-activity-head-v110{align-items:flex-start;flex-direction:column}.sa-card-head-v110{grid-template-columns:2rem minmax(0,1fr) auto}.sa-activity-row-v110{padding:.65rem .72rem}.sa-status-v110,.sa-plan-v110{max-width:6.6rem}}
    </style>
</x-filament-widgets::widget>
