<x-filament-widgets::widget>
<div
    class="pc-admin"
    x-data="{
        tab: 'dashboard',
        athleteDrawer: null,
        activityDrawer: null,
        search: '',
        sport: 'all',
        plan: 'all',
        state: 'all',
        openTab(name) {
            this.tab = name;
            this.athleteDrawer = null;
            this.activityDrawer = null;
            document.body.style.overflow = '';
        },
        matches(row) {
            const q = this.search.trim().toLowerCase();
            const hay = [row.name,row.sport,row.plan,row.club,row.year,row.email].join(' ').toLowerCase();
            if (q && !hay.includes(q)) return false;
            if (this.sport !== 'all' && row.sport_key !== this.sport) return false;
            if (this.plan !== 'all' && row.plan_key !== this.plan) return false;
            if (this.state !== 'all' && row.state !== this.state) return false;
            return true;
        },
        closeOverlays() {
            this.athleteDrawer = null;
            this.activityDrawer = null;
            document.body.style.overflow = '';
        }
    }"
    x-on:keydown.escape.window="closeOverlays()"
>
    <div class="pc-tabs">
        <button type="button" :class="{ 'is-active': tab === 'dashboard' }" x-on:click="openTab('dashboard')">
            <span class="pc-tab-icon">▦</span>
            Dashboard
        </button>
        <button type="button" :class="{ 'is-active': tab === 'athletes' }" x-on:click="openTab('athletes')">
            <span class="pc-tab-icon">◎</span>
            Athletes
            <span class="pc-tab-count">{{ number_format($athlete_count) }}</span>
        </button>
        <button type="button" :class="{ 'is-active': tab === 'followups' }" x-on:click="openTab('followups')">
            <span class="pc-tab-icon">!</span>
            Follow-ups
            <span class="pc-tab-count is-hot">{{ number_format(count($attention_all)) }}</span>
        </button>
        <button type="button" :class="{ 'is-active': tab === 'billing' }" x-on:click="openTab('billing')">
            <span class="pc-tab-icon">▤</span>
            Billing
            <span class="pc-tab-count">{{ number_format($billing_stats['active']) }}</span>
        </button>
    </div>

    <section x-show="tab === 'dashboard'" x-cloak class="pc-view">
        <div class="pc-view-head">
            <div>
                <span class="pc-kicker">SUPERADMIN</span>
                <h2>Dashboard</h2>
                <p>Every athlete, plan, request, payment, and loose end in one place.</p>
            </div>
            <span class="pc-updated">Updated {{ $last_updated }}</span>
        </div>

        <div class="pc-stat-grid">
            <button type="button" class="pc-stat" x-on:click="openTab('athletes')">
                <span class="pc-stat-icon is-coral">◎</span>
                <small>Athletes on platform</small>
                <strong>{{ number_format($stats['athletes']['value']) }}</strong>
                <span>{{ $stats['athletes']['sub'] }}</span>
            </button>
            <button type="button" class="pc-stat" x-on:click="openTab('billing')">
                <span class="pc-stat-icon is-green">↗</span>
                <small>Monthly recurring</small>
                <strong>{{ $stats['mrr']['value'] }}</strong>
                <span>{{ $stats['mrr']['sub'] }}</span>
            </button>
            <button type="button" class="pc-stat" x-on:click="openTab('followups')">
                <span class="pc-stat-icon is-amber">!</span>
                <small>Need follow-up</small>
                <strong>{{ number_format($stats['follow_up']['value']) }}</strong>
                <span>{{ $stats['follow_up']['sub'] }}</span>
            </button>
            <button type="button" class="pc-stat" x-on:click="activityDrawer = 'services'; document.body.style.overflow='hidden'">
                <span class="pc-stat-icon is-violet">◇</span>
                <small>Open service requests</small>
                <strong>{{ number_format($service_activity['open']) }}</strong>
                <span>{{ number_format($service_activity['week']) }} submitted in the last 7 days</span>
            </button>
            <button type="button" class="pc-stat" x-on:click="activityDrawer = 'payments'; document.body.style.overflow='hidden'">
                <span class="pc-stat-icon is-blue">$</span>
                <small>Payments this week</small>
                <strong>{{ $payment_activity['week_amount'] }}</strong>
                <span>{{ number_format($payment_activity['week_count']) }} successful payment{{ $payment_activity['week_count'] === 1 ? '' : 's' }}</span>
            </button>
        </div>

        <div class="pc-dashboard-grid">
            <section class="pc-panel">
                <header class="pc-panel-head">
                    <div>
                        <h3>Worth your attention today</h3>
                        <p>Highest-priority athlete issues from live account data.</p>
                    </div>
                    <button type="button" class="pc-btn" x-on:click="openTab('followups')">See all queues</button>
                </header>
                <div class="pc-table-wrap">
                    <table class="pc-table">
                        <tbody>
                        @forelse(array_slice($attention_all, 0, 7) as $athlete)
                            @php($flag = $athlete['top_flag'] ?? null)
                            <tr class="pc-click-row" x-on:click="athleteDrawer={{ (int) $athlete['id'] }}; document.body.style.overflow='hidden'">
                                <td>
                                    <div class="pc-person">
                                        <span class="pc-avatar">{{ $athlete['initials'] }}</span>
                                        <span><b>{{ $athlete['name'] }}</b><small>{{ $athlete['sport'] }} · {{ $athlete['plan'] }}</small></span>
                                    </div>
                                </td>
                                <td><span class="pc-pill is-{{ $flag['tone'] ?? 'warning' }}">{{ $flag['label'] ?? 'Needs follow-up' }}</span></td>
                                <td>
                                    <div class="pc-health"><span><i style="width:{{ max(3,(int)$athlete['health']) }}%"></i></span><small>{{ $athlete['health'] }}%</small></div>
                                </td>
                                <td class="pc-action-cell" x-on:click.stop>
                                    <button type="button" class="pc-btn is-small" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int) $athlete['id'] }}, concern: '{{ $athlete['reminder_concern'] }}' })">Support</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="pc-empty">Nothing urgent is currently flagged.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="pc-side-stack">
                <section class="pc-panel">
                    <header class="pc-panel-head"><div><h3>Athletes by sport</h3><p>Current platform distribution</p></div></header>
                    <div class="pc-sports">
                        @forelse($sports as $sport)
                            <button type="button" x-on:click="sport=@js(strtolower($sport['sport'])); openTab('athletes')" class="pc-sport">
                                <b>{{ $sport['sport'] }}</b><span><i style="width:{{ $sport['percent'] }}%"></i></span><small>{{ $sport['count'] }}</small>
                            </button>
                        @empty
                            <div class="pc-empty">No athlete sports available.</div>
                        @endforelse
                    </div>
                </section>
                <section class="pc-panel">
                    <header class="pc-panel-head"><div><h3>Reminders this week</h3><p>Admin Support Messenger sends</p></div></header>
                    <div class="pc-timeline">
                        @forelse($reminders as $reminder)
                            <div><i class="{{ $reminder['success'] ? 'ok' : 'bad' }}"></i><span><b>{{ $reminder['name'] }} — {{ $reminder['label'] }}</b><small>{{ $reminder['when'] }} · {{ str($reminder['status'])->replace('_',' ')->lower() }}</small></span></div>
                        @empty
                            <div class="pc-empty">No support reminders sent in the last 7 days.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        <div class="pc-section-title">
            <div><h3>Recent activity</h3><p>Quick access without leaving the dashboard.</p></div>
        </div>
        <div class="pc-quick-grid">
            <section class="pc-panel">
                <header class="pc-panel-head">
                    <div><h3>Service requests</h3><p>{{ number_format($service_activity['week']) }} new this week</p></div>
                    <button type="button" class="pc-btn is-small" x-on:click="activityDrawer='services'; document.body.style.overflow='hidden'">View all</button>
                </header>
                <div class="pc-mini-list">
                    @forelse(array_slice($service_activity['items'],0,5) as $request)
                        <a href="{{ $request['url'] ?: '#' }}">
                            <span class="pc-avatar">{{ $request['initials'] }}</span>
                            <span><b>{{ $request['service'] }}{{ $request['quantity'] > 1 ? ' × '.$request['quantity'] : '' }}</b><small>{{ $request['player'] }} · {{ number_format($request['points']) }} pts · {{ $request['when'] }}</small></span>
                            <span class="pc-pill">{{ $request['rush'] ? 'Rush · ' : '' }}{{ $request['status'] }}</span>
                        </a>
                    @empty
                        <div class="pc-empty">No recent service requests.</div>
                    @endforelse
                </div>
            </section>

            <section class="pc-panel">
                <header class="pc-panel-head">
                    <div><h3>New signups</h3><p>Most recently created player accounts</p></div>
                    @if($users_url)<a href="{{ $users_url }}" class="pc-btn is-small">View users</a>@endif
                </header>
                <div class="pc-mini-list">
                    @forelse($new_signups as $signup)
                        <button type="button" x-on:click="athleteDrawer={{ (int) $signup['id'] }}; document.body.style.overflow='hidden'">
                            <span class="pc-avatar">{{ $signup['initials'] }}</span>
                            <span><b>{{ $signup['name'] }}</b><small>{{ $signup['sport'] }} · joined {{ $signup['joined'] }}</small></span>
                            <span class="pc-plan">{{ $signup['plan'] }}</span>
                        </button>
                    @empty
                        <div class="pc-empty">No player accounts available.</div>
                    @endforelse
                </div>
            </section>

            <section class="pc-panel">
                <header class="pc-panel-head">
                    <div><h3>Recent payments</h3><p>{{ $payment_activity['week_amount'] }} this week</p></div>
                    <button type="button" class="pc-btn is-small" x-on:click="activityDrawer='payments'; document.body.style.overflow='hidden'">View all</button>
                </header>
                <div class="pc-mini-list">
                    @forelse(array_slice($payment_activity['items'],0,5) as $payment)
                        <a href="{{ $payment['url'] ?: '#' }}">
                            <span class="pc-avatar">{{ $payment['initials'] }}</span>
                            <span><b>{{ $payment['player'] }}</b><small>{{ $payment['source'] }} · {{ $payment['provider'] }} · {{ $payment['when'] }}</small></span>
                            <strong class="pc-money">{{ $payment['amount'] }}</strong>
                        </a>
                    @empty
                        <div class="pc-empty">No successful payments recorded yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </section>

    <section x-show="tab === 'athletes'" x-cloak class="pc-view">
        <div class="pc-view-head">
            <div><span class="pc-kicker">PLAYER OPERATIONS</span><h2>Athletes</h2><p>Search, filter, review account health, and contact a player without leaving the dashboard.</p></div>
            @if($users_url)<a href="{{ $users_url }}" class="pc-btn">Open full Users admin</a>@endif
        </div>

        <div class="pc-filters">
            <div class="pc-search-box"><span>⌕</span><input type="search" x-model="search" placeholder="Search athletes, clubs, plans, email"></div>
            <select x-model="sport"><option value="all">All sports</option>@foreach(collect($athletes)->pluck('sport_key')->filter()->unique()->sort() as $sportKey)<option value="{{ $sportKey }}">{{ str($sportKey)->headline() }}</option>@endforeach</select>
            <select x-model="plan"><option value="all">All plans</option>@foreach(collect($athletes)->pluck('plan_key')->filter()->unique()->sort() as $planKey)<option value="{{ $planKey }}">{{ str($planKey)->replace('-',' ')->headline() }}</option>@endforeach</select>
            <select x-model="state"><option value="all">All states</option><option value="active">Active</option><option value="needs_push">Needs push</option><option value="at_risk">At risk</option><option value="past_due">Past due</option><option value="churned">Churned</option></select>
            <button type="button" class="pc-btn" x-on:click="search='';sport='all';plan='all';state='all'">Clear</button>
        </div>

        <section class="pc-panel">
            <div class="pc-table-wrap">
                <table class="pc-table pc-athlete-table">
                    <thead><tr><th>Athlete</th><th>Sport</th><th>Plan</th><th>Profile</th><th>Last login</th><th>Last outreach</th><th>Status</th><th>Health</th><th></th></tr></thead>
                    <tbody>
                    @foreach($athletes as $athlete)
                        <tr
                            x-show="matches(@js(['name'=>$athlete['name'],'sport'=>$athlete['sport'],'sport_key'=>$athlete['sport_key'],'plan'=>$athlete['plan'],'plan_key'=>$athlete['plan_key'],'club'=>$athlete['club'],'year'=>$athlete['year'],'email'=>$athlete['email'],'state'=>$athlete['state']]))"
                            class="pc-click-row"
                            x-on:click="athleteDrawer={{ (int) $athlete['id'] }}; document.body.style.overflow='hidden'"
                        >
                            <td><div class="pc-person"><span class="pc-avatar">{{ $athlete['initials'] }}</span><span><b>{{ $athlete['name'] }}</b><small>{{ $athlete['year'] ?: 'Grad year not set' }} · {{ $athlete['position'] }} · {{ $athlete['club'] }}</small></span></div></td>
                            <td>{{ $athlete['sport'] }}</td>
                            <td><span class="pc-plan">{{ $athlete['plan'] }}</span></td>
                            <td><div class="pc-health"><span><i style="width:{{ max(3,(int)$athlete['profile_completion']) }}%"></i></span><small>{{ $athlete['profile_completion'] }}%</small></div></td>
                            <td>{{ $athlete['last_login'] }}</td>
                            <td>{{ $athlete['last_outreach'] }}</td>
                            <td><span class="pc-state is-{{ $athlete['state'] }}">{{ str($athlete['state'])->replace('_',' ')->headline() }}</span></td>
                            <td><div class="pc-health"><span><i style="width:{{ max(3,(int)$athlete['health']) }}%"></i></span><small>{{ $athlete['health'] }}%</small></div></td>
                            <td class="pc-action-cell" x-on:click.stop><button type="button" class="pc-btn is-small" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int) $athlete['id'] }}, concern: '{{ $athlete['reminder_concern'] }}' })">Support</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @if(!$login_tracking_available)<div class="pc-note">Login activity is not stored on this environment yet, so login-based follow-up flags are intentionally not fabricated.</div>@endif
    </section>

    <section x-show="tab === 'followups'" x-cloak class="pc-view">
        <div class="pc-view-head">
            <div><span class="pc-kicker">ACTION QUEUES</span><h2>Follow-ups</h2><p>Queues are built from what is actually blocking each player. Every action opens the existing Admin Support Messenger with the player and concern preselected.</p></div>
        </div>
        <div class="pc-queue-grid">
            @foreach($follow_up_queues as $queue)
                <section class="pc-queue">
                    <header>
                        <div><span class="pc-queue-mark is-{{ $queue['key'] }}">{{ strtoupper(substr($queue['title'],0,1)) }}</span></div>
                        <div><h3>{{ $queue['title'] }}</h3><p>{{ $queue['description'] }}</p></div>
                        <strong>{{ number_format($queue['count']) }}</strong>
                    </header>
                    <div class="pc-queue-list">
                        @forelse(array_slice($queue['items'],0,8) as $athlete)
                            <div>
                                <button type="button" class="pc-person is-button" x-on:click="athleteDrawer={{ (int)$athlete['id'] }}; document.body.style.overflow='hidden'">
                                    <span class="pc-avatar">{{ $athlete['initials'] }}</span><span><b>{{ $athlete['name'] }}</b><small>{{ $athlete['sport'] }} · {{ $athlete['plan'] }}</small></span>
                                </button>
                                <button type="button" class="pc-btn is-small" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int)$athlete['id'] }}, concern: '{{ $queue['concern'] }}' })">Message</button>
                            </div>
                        @empty
                            <div class="pc-empty">Queue is clear.</div>
                        @endforelse
                    </div>
                    @if($queue['count'] > 8)<footer>{{ number_format($queue['count'] - 8) }} more player{{ ($queue['count'] - 8) === 1 ? '' : 's' }} in this queue</footer>@endif
                </section>
            @endforeach
        </div>
    </section>

    <section x-show="tab === 'billing'" x-cloak class="pc-view">
        <div class="pc-view-head">
            <div><span class="pc-kicker">REVENUE & ACCOUNT HEALTH</span><h2>Billing</h2><p>Recurring revenue, conversion, payment risk, cancellations, and recent purchases.</p></div>
            @if($users_url)<a href="{{ $users_url }}" class="pc-btn">Manage Users & Billing</a>@endif
        </div>
        <div class="pc-stat-grid pc-stat-grid-four">
            <div class="pc-stat"><span class="pc-stat-icon is-green">↗</span><small>Monthly recurring</small><strong>{{ $billing_stats['mrr'] }}</strong><span>{{ number_format($billing_stats['active']) }} active subscriptions</span></div>
            <div class="pc-stat"><span class="pc-stat-icon is-coral">%</span><small>Free to paid</small><strong>{{ $billing_stats['conversion'] }}%</strong><span>{{ number_format($billing_stats['free']) }} still on free</span></div>
            <div class="pc-stat"><span class="pc-stat-icon is-red">!</span><small>Revenue at risk</small><strong>{{ $billing_stats['at_risk'] }}</strong><span>{{ number_format($billing_stats['past_due']) }} past due</span></div>
            <div class="pc-stat"><span class="pc-stat-icon">×</span><small>Churned</small><strong>{{ number_format($billing_stats['churned']) }}</strong><span>canceled / expired accounts</span></div>
        </div>

        <div class="pc-plan-grid">
            @foreach($plan_summary as $plan)
                <div class="pc-panel pc-plan-card"><span class="pc-plan">{{ $plan['plan'] }}</span><strong>{{ number_format($plan['count']) }}</strong><small>{{ $plan['recurring_cents'] > 0 ? '$'.number_format($plan['recurring_cents']/100,2).'/mo recurring' : 'no active recurring revenue' }}</small></div>
            @endforeach
        </div>

        <section class="pc-panel">
            <header class="pc-panel-head"><div><h3>Billing accounts</h3><p>Past due first, then active and inactive accounts.</p></div></header>
            <div class="pc-table-wrap">
                <table class="pc-table">
                    <thead><tr><th>Athlete</th><th>Plan</th><th>Rate</th><th>Provider</th><th>Payment</th><th>Subscription</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($billing_rows as $athlete)
                        <tr class="pc-click-row" x-on:click="athleteDrawer={{ (int)$athlete['id'] }}; document.body.style.overflow='hidden'">
                            <td><div class="pc-person"><span class="pc-avatar">{{ $athlete['initials'] }}</span><span><b>{{ $athlete['name'] }}</b><small>{{ $athlete['sport'] }} · joined {{ $athlete['joined'] }}</small></span></div></td>
                            <td><span class="pc-plan">{{ $athlete['plan'] }}</span></td>
                            <td class="pc-mono">{{ $athlete['billing']['recurring_cents'] > 0 ? '$'.number_format($athlete['billing']['recurring_cents']/100,2).'/mo' : '—' }}</td>
                            <td>{{ str($athlete['billing']['provider'])->headline() }}</td>
                            <td><span class="pc-state is-{{ $athlete['state'] }}">{{ str($athlete['billing']['payment_status'])->headline() }}</span></td>
                            <td>{{ str($athlete['billing']['subscription_status'])->headline() }}</td>
                            <td x-on:click.stop><button type="button" class="pc-btn is-small" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int)$athlete['id'] }}, concern: 'payment_attention' })">Support</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="pc-empty">No billing records are available yet.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="pc-panel">
            <header class="pc-panel-head"><div><h3>Recent payments & purchases</h3><p>Latest successful transactions recorded by PLYRCARD.</p></div><button type="button" class="pc-btn is-small" x-on:click="activityDrawer='payments'; document.body.style.overflow='hidden'">Expand</button></header>
            <div class="pc-mini-list">
                @forelse(array_slice($payment_activity['items'],0,8) as $payment)
                    <a href="{{ $payment['url'] ?: '#' }}"><span class="pc-avatar">{{ $payment['initials'] }}</span><span><b>{{ $payment['player'] }}</b><small>{{ $payment['source'] }} · {{ $payment['provider'] }} · {{ $payment['when'] }}</small></span><strong class="pc-money">{{ $payment['amount'] }}</strong></a>
                @empty
                    <div class="pc-empty">No successful payments recorded yet.</div>
                @endforelse
            </div>
        </section>
    </section>

    <div x-cloak x-show="athleteDrawer || activityDrawer" x-transition.opacity class="pc-scrim" x-on:click="closeOverlays()"></div>

    @foreach($athletes as $athlete)
        <aside x-cloak x-show="athleteDrawer === {{ (int)$athlete['id'] }}" x-transition:enter="pc-slide-in" x-transition:leave="pc-slide-out" class="pc-drawer">
            <header class="pc-drawer-head">
                <span class="pc-avatar is-large">{{ $athlete['initials'] }}</span>
                <div><h3>{{ $athlete['name'] }}</h3><p>{{ $athlete['sport'] }} · {{ $athlete['position'] }} · {{ $athlete['year'] ?: 'Grad year not set' }} · {{ $athlete['club'] }}</p><div class="pc-chip-row"><span class="pc-plan">{{ $athlete['plan'] }}</span><span class="pc-state is-{{ $athlete['state'] }}">{{ str($athlete['state'])->replace('_',' ')->headline() }}</span></div></div>
                <button type="button" class="pc-close" x-on:click="closeOverlays()">×</button>
            </header>
            @if(count($athlete['flags']))
                <section class="pc-drawer-section"><h4>NEEDS ATTENTION</h4>
                    @foreach($athlete['flags'] as $flag)
                        <div class="pc-flag-row"><div><span class="pc-pill is-{{ $flag['tone'] ?? 'warning' }}">{{ $flag['label'] }}</span><p>{{ $flag['detail'] ?? '' }}</p></div><button type="button" class="pc-btn is-small" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int)$athlete['id'] }}, concern: '{{ $flag['concern'] ?? 'custom' }}' })">Message</button></div>
                    @endforeach
                </section>
            @endif
            <section class="pc-drawer-section"><h4>ACCOUNT</h4>
                <div class="pc-kv"><span>Health score</span><b>{{ $athlete['health'] }} / 100</b></div>
                <div class="pc-kv"><span>Profile completion</span><b>{{ $athlete['profile_completion'] }}%</b></div>
                <div class="pc-kv"><span>Plan</span><b>{{ $athlete['plan'] }}</b></div>
                <div class="pc-kv"><span>Payment</span><b>{{ str($athlete['billing']['payment_status'])->headline() }}</b></div>
                <div class="pc-kv"><span>Subscription</span><b>{{ str($athlete['billing']['subscription_status'])->headline() }}</b></div>
                <div class="pc-kv"><span>Joined</span><b>{{ $athlete['joined'] }}</b></div>
                <div class="pc-kv"><span>Last login</span><b>{{ $athlete['last_login'] }}</b></div>
                <div class="pc-kv"><span>Last outreach</span><b>{{ $athlete['last_outreach'] }}</b></div>
            </section>
            <section class="pc-drawer-section"><h4>WEBSITE</h4>
                <div class="pc-kv"><span>Website</span><b>{{ $athlete['website']['exists'] ? 'Created' : 'Not created' }}</b></div>
                <div class="pc-kv"><span>Published</span><b>{{ $athlete['website']['published'] ? 'Yes' : 'No' }}</b></div>
                <div class="pc-kv"><span>Domain / slug</span><b>{{ $athlete['website']['domain'] ?: ($athlete['website']['slug'] ?: 'Not set') }}</b></div>
            </section>
            <footer class="pc-drawer-actions">
                <button type="button" class="pc-btn is-primary" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int)$athlete['id'] }}, concern: '{{ $athlete['reminder_concern'] }}' })">Open Support Messenger</button>
                @if($athlete['user_url'])<a href="{{ $athlete['user_url'] }}" class="pc-btn">View player details</a>@endif
                <span class="pc-impersonate">{{ ($this->impersonateAction)(['userId' => (int)$athlete['id']]) }}</span>
            </footer>
        </aside>
    @endforeach

    <aside x-cloak x-show="activityDrawer === 'services'" class="pc-drawer">
        <header class="pc-drawer-head"><div><span class="pc-kicker">PLYR POINTS</span><h3>Recent service requests</h3><p>Review the latest service requests, then jump into the full Credit Requests system.</p></div><button type="button" class="pc-close" x-on:click="closeOverlays()">×</button></header>
        <div class="pc-drawer-list">
            @forelse($service_activity['items'] as $request)
                <div class="pc-drawer-list-row">
                    <a href="{{ $request['url'] ?: '#' }}" class="pc-person"><span class="pc-avatar">{{ $request['initials'] }}</span><span><b>{{ $request['service'] }}{{ $request['quantity'] > 1 ? ' × '.$request['quantity'] : '' }}</b><small>{{ $request['player'] }} · {{ number_format($request['points']) }} pts · {{ $request['when'] }}</small></span></a>
                    <span class="pc-pill">{{ $request['rush'] ? 'Rush · ' : '' }}{{ $request['status'] }}</span>
                    <button type="button" class="pc-btn is-small" wire:click="$dispatch('open-credit-request-follow-up', { requestId: {{ (int)$request['id'] }} })">Support</button>
                </div>
            @empty<div class="pc-empty">No service requests available.</div>@endforelse
        </div>
        <footer class="pc-drawer-actions">@if($credit_requests_url)<a href="{{ $credit_requests_url }}" class="pc-btn is-primary">Open Credit Requests</a>@endif</footer>
    </aside>

    <aside x-cloak x-show="activityDrawer === 'payments'" class="pc-drawer">
        <header class="pc-drawer-head"><div><span class="pc-kicker">BILLING ACTIVITY</span><h3>Recent payments & purchases</h3><p>Successful transactions recorded across subscriptions and purchases.</p></div><button type="button" class="pc-close" x-on:click="closeOverlays()">×</button></header>
        <div class="pc-drawer-list">
            @forelse($payment_activity['items'] as $payment)
                <div class="pc-drawer-list-row">
                    <a href="{{ $payment['url'] ?: '#' }}" class="pc-person"><span class="pc-avatar">{{ $payment['initials'] }}</span><span><b>{{ $payment['player'] }}</b><small>{{ $payment['source'] }} · {{ $payment['provider'] }} · {{ $payment['when'] }}</small></span></a>
                    <strong class="pc-money">{{ $payment['amount'] }}</strong>
                    @if($payment['user_id'] > 0)<button type="button" class="pc-btn is-small" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int)$payment['user_id'] }}, concern: 'payment_attention' })">Support</button>@endif
                </div>
            @empty<div class="pc-empty">No successful payments recorded yet.</div>@endforelse
        </div>
        <footer class="pc-drawer-actions">@if($users_url)<a href="{{ $users_url }}" class="pc-btn is-primary">Manage Users & Billing</a>@endif</footer>
    </aside>

    <x-filament-actions::modals />

<style>
.pc-admin{--pc-bg:#f3f5f7;--pc-panel:#fff;--pc-panel2:#f7f9fb;--pc-line:#e4e8ed;--pc-line2:#d6dbe2;--pc-text:#12151a;--pc-muted:#69727e;--pc-dim:#8b94a0;--pc-coral:#ff6448;--pc-green:#16a377;--pc-amber:#d68d18;--pc-red:#dc4b46;--pc-blue:#3979e6;--pc-violet:#8065dd;display:flex;flex-direction:column;gap:1rem;color:var(--pc-text);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.dark .pc-admin{--pc-bg:#0c0e11;--pc-panel:#14171c;--pc-panel2:#1a1e24;--pc-line:#242932;--pc-line2:#2e343e;--pc-text:#e9ecf1;--pc-muted:#8a93a0;--pc-dim:#5f6874}
.pc-tabs{display:flex;gap:.25rem;overflow-x:auto;padding:.35rem;border:1px solid var(--pc-line);border-radius:.75rem;background:var(--pc-panel)}.pc-tabs button{display:flex;align-items:center;gap:.48rem;min-height:2.45rem;padding:.45rem .72rem;border:0;border-radius:.55rem;background:transparent;color:var(--pc-muted);font-size:.76rem;font-weight:700;cursor:pointer;white-space:nowrap}.pc-tabs button:hover{background:var(--pc-panel2);color:var(--pc-text)}.pc-tabs button.is-active{background:var(--pc-panel2);color:var(--pc-text);box-shadow:inset 2px 0 0 var(--pc-coral)}.pc-tab-icon{width:1.15rem;text-align:center}.pc-tab-count{margin-left:.12rem;padding:.08rem .35rem;border:1px solid var(--pc-line);border-radius:.35rem;background:var(--pc-bg);font-size:.61rem}.pc-tab-count.is-hot{color:var(--pc-coral);border-color:rgba(255,100,72,.35)}
.pc-view{display:flex;flex-direction:column;gap:1rem}.pc-view-head,.pc-section-title{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem}.pc-view-head h2,.pc-section-title h3{margin:.1rem 0 0;font-size:1.08rem;font-weight:800;letter-spacing:-.025em}.pc-view-head p,.pc-section-title p{margin:.18rem 0 0;color:var(--pc-muted);font-size:.72rem}.pc-kicker{color:var(--pc-coral);font-size:.58rem;font-weight:850;letter-spacing:.13em}.pc-updated{color:var(--pc-dim);font-size:.64rem}
.pc-stat-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.75rem}.pc-stat-grid-four{grid-template-columns:repeat(4,minmax(0,1fr))}.pc-stat{min-width:0;min-height:8.6rem;padding:.88rem;border:1px solid var(--pc-line);border-radius:.7rem;background:var(--pc-panel);color:inherit;text-align:left}.pc-stat button&{cursor:pointer}.pc-stat:hover{border-color:var(--pc-line2)}.pc-stat-icon{width:1.85rem;height:1.85rem;display:grid;place-items:center;margin-bottom:.7rem;border-radius:.5rem;background:var(--pc-panel2);font-weight:900}.pc-stat-icon.is-coral{background:rgba(255,100,72,.11);color:var(--pc-coral)}.pc-stat-icon.is-green{background:rgba(34,192,138,.11);color:var(--pc-green)}.pc-stat-icon.is-amber{background:rgba(240,169,59,.12);color:var(--pc-amber)}.pc-stat-icon.is-red{background:rgba(242,86,79,.11);color:var(--pc-red)}.pc-stat-icon.is-blue{background:rgba(76,141,255,.11);color:var(--pc-blue)}.pc-stat-icon.is-violet{background:rgba(156,123,255,.11);color:var(--pc-violet)}.pc-stat small{display:block;color:var(--pc-muted);font-size:.66rem;font-weight:700}.pc-stat strong{display:block;margin-top:.25rem;font-size:1.55rem;letter-spacing:-.045em}.pc-stat>span:last-child{display:block;margin-top:.6rem;color:var(--pc-dim);font-size:.64rem;line-height:1.35}
.pc-dashboard-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(18rem,.7fr);gap:.85rem}.pc-side-stack{display:flex;flex-direction:column;gap:.85rem}.pc-panel,.pc-queue{overflow:hidden;border:1px solid var(--pc-line);border-radius:.72rem;background:var(--pc-panel)}.pc-panel-head{min-height:3.5rem;padding:.78rem .88rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;border-bottom:1px solid var(--pc-line)}.pc-panel-head h3{margin:0;font-size:.78rem;font-weight:800}.pc-panel-head p{margin:.13rem 0 0;color:var(--pc-dim);font-size:.63rem}.pc-btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;padding:.42rem .62rem;border:1px solid var(--pc-line2);border-radius:.5rem;background:var(--pc-panel);color:var(--pc-text);font-size:.65rem;font-weight:750;text-decoration:none;cursor:pointer;white-space:nowrap}.pc-btn:hover{background:var(--pc-panel2)}.pc-btn.is-primary{background:var(--pc-coral);border-color:var(--pc-coral);color:#fff}.pc-btn.is-small{padding:.32rem .48rem;font-size:.61rem}
.pc-table-wrap{overflow:auto}.pc-table{width:100%;border-collapse:collapse}.pc-table th{padding:.58rem .72rem;border-bottom:1px solid var(--pc-line);background:var(--pc-panel2);color:var(--pc-dim);font-size:.59rem;font-weight:800;letter-spacing:.08em;text-align:left;text-transform:uppercase;white-space:nowrap}.pc-table td{padding:.64rem .72rem;border-bottom:1px solid var(--pc-line);font-size:.68rem;vertical-align:middle}.pc-table tbody tr:last-child td{border-bottom:0}.pc-click-row{cursor:pointer}.pc-click-row:hover{background:var(--pc-panel2)}.pc-action-cell{text-align:right}.pc-person{min-width:0;display:flex;align-items:center;gap:.55rem;color:inherit;text-decoration:none}.pc-person.is-button{border:0;background:transparent;text-align:left;cursor:pointer}.pc-person>span:last-child{min-width:0}.pc-person b{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.69rem}.pc-person small{display:block;margin-top:.11rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--pc-dim);font-size:.59rem}.pc-avatar{width:1.9rem;height:1.9rem;display:grid;place-items:center;flex:0 0 1.9rem;border:1px solid var(--pc-line2);border-radius:.55rem;background:var(--pc-panel2);font-size:.59rem;font-weight:800}.pc-avatar.is-large{width:2.7rem;height:2.7rem;flex-basis:2.7rem;font-size:.75rem}
.pc-pill,.pc-plan,.pc-state{display:inline-flex;align-items:center;padding:.25rem .42rem;border:1px solid var(--pc-line);border-radius:999px;background:var(--pc-panel2);color:var(--pc-muted);font-size:.58rem;font-weight:800;white-space:nowrap}.pc-pill.is-danger,.pc-state.is-past_due,.pc-state.is-at_risk{color:var(--pc-red);border-color:rgba(242,86,79,.3);background:rgba(242,86,79,.08)}.pc-pill.is-warning,.pc-state.is-needs_push{color:var(--pc-amber);border-color:rgba(240,169,59,.3);background:rgba(240,169,59,.08)}.pc-state.is-active{color:var(--pc-green);border-color:rgba(34,192,138,.3);background:rgba(34,192,138,.08)}.pc-state.is-churned{color:var(--pc-muted)}
.pc-health{display:flex;align-items:center;gap:.35rem;min-width:5rem}.pc-health>span{height:.27rem;min-width:2.5rem;flex:1;overflow:hidden;border-radius:999px;background:var(--pc-line)}.pc-health i{display:block;height:100%;border-radius:inherit;background:var(--pc-coral)}.pc-health small{width:2rem;color:var(--pc-muted);font-size:.58rem;text-align:right}
.pc-sports{padding:.75rem .85rem}.pc-sport{width:100%;display:grid;grid-template-columns:6rem 1fr 1.5rem;align-items:center;gap:.55rem;padding:.34rem 0;border:0;background:transparent;color:inherit;text-align:left;cursor:pointer}.pc-sport b{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.65rem}.pc-sport>span{height:.25rem;overflow:hidden;border-radius:999px;background:var(--pc-line)}.pc-sport i{display:block;height:100%;background:var(--pc-coral)}.pc-sport small{text-align:right;color:var(--pc-muted);font-size:.6rem}.pc-timeline{padding:.7rem .85rem}.pc-timeline>div{display:grid;grid-template-columns:.45rem 1fr;gap:.55rem;padding:.48rem 0;border-bottom:1px solid var(--pc-line)}.pc-timeline>div:last-child{border-bottom:0}.pc-timeline i{width:.4rem;height:.4rem;margin-top:.22rem;border-radius:999px;background:var(--pc-coral)}.pc-timeline i.ok{background:var(--pc-green)}.pc-timeline i.bad{background:var(--pc-red)}.pc-timeline b{display:block;font-size:.63rem}.pc-timeline small{display:block;margin-top:.1rem;color:var(--pc-dim);font-size:.58rem}
.pc-quick-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.pc-mini-list{display:flex;flex-direction:column}.pc-mini-list>a,.pc-mini-list>button{display:grid;grid-template-columns:1.9rem minmax(0,1fr) auto;align-items:center;gap:.55rem;padding:.58rem .75rem;border:0;border-bottom:1px solid var(--pc-line);background:transparent;color:inherit;text-align:left;text-decoration:none;cursor:pointer}.pc-mini-list>a:last-child,.pc-mini-list>button:last-child{border-bottom:0}.pc-mini-list>a:hover,.pc-mini-list>button:hover{background:var(--pc-panel2)}.pc-mini-list b{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.66rem}.pc-mini-list small{display:block;margin-top:.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--pc-dim);font-size:.57rem}.pc-money{color:var(--pc-green);font-size:.7rem}.pc-empty{padding:1rem;color:var(--pc-dim);font-size:.68rem}
.pc-filters{display:flex;gap:.55rem;flex-wrap:wrap;padding:.68rem;border:1px solid var(--pc-line);border-radius:.7rem;background:var(--pc-panel)}.pc-filters select,.pc-search-box{min-height:2.25rem;border:1px solid var(--pc-line);border-radius:.5rem;background:var(--pc-panel2);color:var(--pc-text);font-size:.66rem}.pc-filters select{padding:.35rem .55rem}.pc-search-box{min-width:16rem;display:flex;align-items:center;gap:.4rem;padding:0 .55rem}.pc-search-box input{width:100%;border:0;outline:0;background:transparent;color:inherit;font:inherit}.pc-note{padding:.65rem .8rem;border:1px dashed var(--pc-line2);border-radius:.55rem;background:var(--pc-panel2);color:var(--pc-muted);font-size:.63rem}
.pc-queue-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.pc-queue>header{display:grid;grid-template-columns:2rem minmax(0,1fr) auto;gap:.6rem;align-items:start;padding:.72rem .78rem;border-bottom:1px solid var(--pc-line)}.pc-queue>header h3{margin:0;font-size:.72rem}.pc-queue>header p{margin:.12rem 0 0;color:var(--pc-dim);font-size:.6rem;line-height:1.35}.pc-queue>header>strong{font-size:1.25rem}.pc-queue-mark{width:1.8rem;height:1.8rem;display:grid;place-items:center;border-radius:.5rem;background:rgba(240,169,59,.1);color:var(--pc-amber);font-size:.68rem;font-weight:900}.pc-queue-mark.is-billing{background:rgba(242,86,79,.1);color:var(--pc-red)}.pc-queue-list>div{display:flex;align-items:center;justify-content:space-between;gap:.65rem;padding:.48rem .78rem;border-bottom:1px solid var(--pc-line)}.pc-queue-list>div:last-child{border-bottom:0}.pc-queue footer{padding:.55rem .78rem;border-top:1px solid var(--pc-line);color:var(--pc-dim);font-size:.59rem}
.pc-plan-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:.65rem}.pc-plan-card{padding:.75rem}.pc-plan-card>strong{display:block;margin-top:.5rem;font-size:1.35rem}.pc-plan-card>small{display:block;margin-top:.2rem;color:var(--pc-dim);font-size:.6rem}.pc-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.pc-scrim{position:fixed;inset:0;z-index:10080;background:rgba(5,8,12,.58);backdrop-filter:blur(2px)}.pc-drawer{position:fixed;top:0;right:0;z-index:10090;width:min(560px,100vw);height:100dvh;overflow-y:auto;border-left:1px solid var(--pc-line);background:var(--pc-panel);box-shadow:-22px 0 55px rgba(0,0,0,.22)}.pc-drawer-head{position:sticky;top:0;z-index:2;display:flex;align-items:flex-start;gap:.7rem;padding:.9rem 1rem;border-bottom:1px solid var(--pc-line);background:var(--pc-panel)}.pc-drawer-head>div{min-width:0;flex:1}.pc-drawer-head h3{margin:.12rem 0 0;font-size:1rem}.pc-drawer-head p{margin:.18rem 0 0;color:var(--pc-muted);font-size:.65rem;line-height:1.4}.pc-close{width:2rem;height:2rem;border:1px solid var(--pc-line);border-radius:.5rem;background:var(--pc-panel2);color:var(--pc-muted);font-size:1.1rem;cursor:pointer}.pc-chip-row{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.45rem}.pc-drawer-section{padding:.85rem 1rem;border-bottom:1px solid var(--pc-line)}.pc-drawer-section h4{margin:0 0 .55rem;color:var(--pc-dim);font-size:.57rem;letter-spacing:.12em}.pc-flag-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.65rem;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--pc-line)}.pc-flag-row:last-child{border-bottom:0}.pc-flag-row p{margin:.16rem 0 0;color:var(--pc-muted);font-size:.61rem;line-height:1.4}.pc-kv{display:flex;justify-content:space-between;gap:1rem;padding:.4rem 0;border-bottom:1px solid var(--pc-line);font-size:.65rem}.pc-kv:last-child{border-bottom:0}.pc-kv span{color:var(--pc-muted)}.pc-kv b{text-align:right}.pc-drawer-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;padding:.78rem 1rem;background:var(--pc-panel2)}.pc-impersonate{margin-left:auto}.pc-drawer-list-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:.55rem;align-items:center;padding:.58rem .8rem;border-bottom:1px solid var(--pc-line)}.pc-drawer-list-row:last-child{border-bottom:0}.pc-slide-in{transition:transform .18s ease}.pc-slide-out{transition:transform .14s ease}[x-cloak]{display:none!important}
@media(max-width:1250px){.pc-stat-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.pc-dashboard-grid{grid-template-columns:1fr}.pc-side-stack{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.pc-quick-grid{grid-template-columns:1fr}.pc-queue-grid{grid-template-columns:1fr}}
@media(max-width:780px){.pc-stat-grid,.pc-stat-grid-four{grid-template-columns:1fr 1fr}.pc-side-stack{grid-template-columns:1fr}.pc-view-head{align-items:flex-start;flex-direction:column}.pc-athlete-table th:nth-child(4),.pc-athlete-table td:nth-child(4),.pc-athlete-table th:nth-child(6),.pc-athlete-table td:nth-child(6){display:none}.pc-filters{display:grid;grid-template-columns:1fr 1fr}.pc-search-box{grid-column:1/-1;min-width:0}}
@media(max-width:520px){.pc-stat-grid,.pc-stat-grid-four{grid-template-columns:1fr}.pc-filters{grid-template-columns:1fr}.pc-athlete-table th:nth-child(2),.pc-athlete-table td:nth-child(2),.pc-athlete-table th:nth-child(5),.pc-athlete-table td:nth-child(5),.pc-athlete-table th:nth-child(8),.pc-athlete-table td:nth-child(8){display:none}.pc-drawer-list-row{grid-template-columns:minmax(0,1fr) auto}.pc-drawer-list-row .pc-btn{grid-column:2}}
</style>
</div>
</x-filament-widgets::widget>