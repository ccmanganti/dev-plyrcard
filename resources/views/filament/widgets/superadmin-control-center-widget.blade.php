<x-filament-widgets::widget>
<div
    class="pc-admin"
    wire:key="superadmin-control-center-{{ $initialTab }}"
    x-data="{
        tab: @js($initialTab),
        athleteDrawer: null,
        activityDrawer: null,
        plyrLabTab: @js(($plyrlab_is_trainer_portal ?? false) ? 'bookings' : 'overview'),
        search: @js((string) request()->query('q', '')),
        sport: @js((string) request()->query('sport', 'all')),
        plan: @js((string) request()->query('plan', 'all')),
        grad: @js((string) request()->query('grad', 'all')),
        state: @js((string) request()->query('state', 'all')),
        openTab(name) {
            const routes = {
                overview: @js(url('/admin')),
                athletes: @js(url('/admin/athletes')),
                followups: @js(url('/admin/follow-ups')),
                subscriptions: @js(url('/admin/subscriptions')),
                plyrlab: @js(url('/admin/plyrlab')),
                amplify: @js(url('/admin/amplify-credits')),
            };
            if (routes[name]) {
                window.location.href = routes[name];
                return;
            }
            this.tab = name;
        },
        matches(row) {
            const q = this.search.trim().toLowerCase();
            const hay = [row.name,row.sport,row.plan,row.club,row.year,row.email].join(' ').toLowerCase();
            if (q && !hay.includes(q)) return false;
            if (this.sport !== 'all' && row.sport_key !== this.sport) return false;
            if (this.plan !== 'all' && row.plan_key !== this.plan) return false;
            if (this.grad !== 'all' && String(row.year || '') !== this.grad) return false;
            if (this.state === 'attention' && !row.has_followup) return false;
            if (this.state === 'healthy' && Number(row.health || 0) < 80) return false;
            if (!['all', 'attention', 'healthy'].includes(this.state) && row.state !== this.state) return false;
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
    @if($initialTab === 'overview')
    <section class="pc-view">
        <div class="pc-view-head">
            <div>
                <span class="pc-kicker">SUPERADMIN</span>
                <h2>Overview</h2>
                <p>Every athlete, every plan, every loose end in one place.</p>
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
            <button type="button" class="pc-stat" x-on:click="openTab('subscriptions')">
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
            <button type="button" class="pc-stat" x-on:click="openTab('amplify')">
                <span class="pc-stat-icon is-violet">ϟ</span>
                <small>Amplify packages</small>
                <strong>{{ number_format($amplify_credit_stats['amplify'] ?? 0) }}</strong>
                <span>{{ number_format($amplify_credit_stats['points_available'] ?? 0) }} package PLYR Points currently available</span>
            </button>
            <div class="pc-stat">
                <span class="pc-stat-icon is-blue">→</span>
                <small>Coach outreach sent</small>
                <strong>{{ number_format($coach_outreach_sent ?? 0) }}</strong>
                <span>all time, all athletes</span>
            </div>
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
                            @php
                                $flag = $athlete['top_flag'] ?? null;
                                $overviewReminder = $athlete['reminders'][$athlete['reminder_concern']] ?? null;
                            @endphp
                            <tr class="pc-click-row" x-on:click="athleteDrawer={{ (int) $athlete['id'] }}; document.body.style.overflow='hidden'">
                                <td>
                                    <div class="pc-person">
                                        <span class="pc-avatar">{{ $athlete['initials'] }}</span>
                                        <span><b>{{ $athlete['name'] }}</b><small>{{ $athlete['sport'] }} · {{ $athlete['plan'] }}</small></span>
                                    </div>
                                </td>
                                <td><span class="pc-pill is-{{ $flag['tone'] ?? 'warning' }}">{{ $flag['label'] ?? 'Needs follow-up' }}</span></td>
                                <td>
                                    <div class="pc-health is-{{ $athlete['health'] >= 80 ? 'good' : ($athlete['health'] >= 55 ? 'mid' : 'bad') }}"><span><i style="width:{{ max(3,(int)$athlete['health']) }}%"></i></span><small>{{ $athlete['health'] }}%</small></div>
                                </td>
                                <td class="pc-action-cell" x-on:click.stop>
                                    <span class="pc-quick-actions">
                                        <button type="button" class="pc-btn is-small {{ ($overviewReminder['week_count'] ?? 0) > 0 ? 'pc-reminder-sent' : '' }}" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int) $athlete['id'] }}, concern: '{{ $athlete['reminder_concern'] }}' })">
                                            {{ ($overviewReminder['week_count'] ?? 0) > 0 ? '✓ Sent '.($overviewReminder['when'] ?? '') : 'Remind' }}
                                        </button>
                                        <span class="pc-impersonate-quick" title="Impersonate {{ $athlete['name'] }}">
                                            {{ ($this->impersonateAction)(['userId' => (int) $athlete['id']]) }}
                                        </span>
                                    </span>
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
                            @php
                                $dashboardSportRow = collect($athletes)->firstWhere('sport', $sport['sport']);
                                $dashboardSportKey = (string) ($dashboardSportRow['sport_key'] ?? strtolower($sport['sport']));
                            @endphp
                            <button type="button" x-on:click="window.location.href = @js(url('/admin/athletes')) + '?sport=' + encodeURIComponent(@js($dashboardSportKey))" class="pc-sport">
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
                        <div class="pc-mini-player-row">
                            <button type="button" class="pc-mini-player-main" x-on:click="athleteDrawer={{ (int) $signup['id'] }}; document.body.style.overflow='hidden'">
                                <span class="pc-avatar">{{ $signup['initials'] }}</span>
                                <span><b>{{ $signup['name'] }}</b><small>{{ $signup['sport'] }} · joined {{ $signup['joined'] }}</small></span>
                                <span class="pc-plan is-{{ $signup['plan_key'] }}">{{ $signup['plan'] }}</span>
                            </button>
                            <span class="pc-impersonate-quick" title="Impersonate {{ $signup['name'] }}">
                                {{ ($this->impersonateAction)(['userId' => (int) $signup['id']]) }}
                            </span>
                        </div>
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

    @endif

    @if($initialTab === 'athletes')
    @php
        $athleteCollection = collect($athletes);
        $sportFilterCounts = $athleteCollection
            ->groupBy('sport_key')
            ->map(fn ($rows) => [
                'label' => (string) ($rows->first()['sport'] ?? 'Not set'),
                'count' => $rows->count(),
            ])
            ->sortByDesc('count');
        $planFilterCounts = $athleteCollection
            ->groupBy('plan_key')
            ->map(fn ($rows) => [
                'label' => (string) ($rows->first()['plan'] ?? 'Athlete'),
                'count' => $rows->count(),
            ]);
        $planFilterOrder = collect(['free-plyr', 'my-journey', 'jumpstart', 'amplify', 'plyr'])
            ->filter(fn ($key) => $planFilterCounts->has($key))
            ->merge($planFilterCounts->keys()->diff(['free-plyr', 'my-journey', 'jumpstart', 'amplify', 'plyr']))
            ->values();
        $gradFilterCounts = $athleteCollection
            ->filter(fn ($row) => filled($row['year'] ?? null))
            ->groupBy(fn ($row) => (string) $row['year'])
            ->map(fn ($rows) => $rows->count())
            ->sortKeys();
        $needsFollowUpCount = $athleteCollection->filter(fn ($row) => ! empty($row['flags'] ?? []))->count();
        $allGoodCount = $athleteCollection->filter(fn ($row) => (int) ($row['health'] ?? 0) >= 80)->count();
    @endphp
    <section class="pc-view">
        <div class="pc-view-head">
            <div><span class="pc-kicker">PLAYER OPERATIONS</span><h2>Athletes</h2><p>Filter by sport, plan, or grad year. Click a row for the full record.</p></div>
            <div class="pc-view-actions">
                <div class="pc-search-box"><span>⌕</span><input type="search" x-model="search" placeholder="Search athletes, clubs, plans, email"></div>
                <select class="pc-state-select" x-model="state" aria-label="Account state">
                    <option value="all">All states</option>
                    <option value="active">Active</option>
                    <option value="needs_push">Needs push</option>
                    <option value="at_risk">At risk</option>
                    <option value="past_due">Past due</option>
                    <option value="churned">Churned</option>
                </select>
                @if($users_url)<a href="{{ $users_url }}" class="pc-btn">Open full Users admin</a>@endif
            </div>
        </div>

        <div class="pc-filter-panel">
            <div class="pc-filter-row">
                <span class="pc-filter-label">SPORT</span>
                <div class="pc-filter-chips">
                    <button type="button" class="pc-filter-chip" :class="{ 'is-active': sport === 'all' }" x-on:click="sport='all'">All sports <span>{{ number_format($athleteCollection->count()) }}</span></button>
                    @foreach($sportFilterCounts as $sportKey => $sportData)
                        <button type="button" class="pc-filter-chip" :class="{ 'is-active': sport === @js((string) $sportKey) }" x-on:click="sport=@js((string) $sportKey)">{{ $sportData['label'] }} <span>{{ number_format($sportData['count']) }}</span></button>
                    @endforeach
                </div>
            </div>
            <div class="pc-filter-row">
                <span class="pc-filter-label">PLAN</span>
                <div class="pc-filter-chips">
                    <button type="button" class="pc-filter-chip" :class="{ 'is-active': plan === 'all' }" x-on:click="plan='all'">All</button>
                    @foreach($planFilterOrder as $planKey)
                        @php
                            $planData = $planFilterCounts->get($planKey);
                        @endphp
                        <button type="button" class="pc-filter-chip" :class="{ 'is-active': plan === @js((string) $planKey) }" x-on:click="plan=@js((string) $planKey)">{{ $planData['label'] }} <span>{{ number_format($planData['count']) }}</span></button>
                    @endforeach
                    <span class="pc-filter-divider"></span>
                    <button type="button" class="pc-filter-chip" :class="{ 'is-active': state === 'attention' }" x-on:click="state = state === 'attention' ? 'all' : 'attention'">Needs follow-up <span>{{ number_format($needsFollowUpCount) }}</span></button>
                    <button type="button" class="pc-filter-chip" :class="{ 'is-active': state === 'healthy' }" x-on:click="state = state === 'healthy' ? 'all' : 'healthy'">All good <span>{{ number_format($allGoodCount) }}</span></button>
                </div>
            </div>
            <div class="pc-filter-row">
                <span class="pc-filter-label">GRAD</span>
                <div class="pc-filter-chips">
                    <button type="button" class="pc-filter-chip" :class="{ 'is-active': grad === 'all' }" x-on:click="grad='all'">All</button>
                    @foreach($gradFilterCounts as $gradYear => $gradCount)
                        <button type="button" class="pc-filter-chip" :class="{ 'is-active': grad === @js((string) $gradYear) }" x-on:click="grad=@js((string) $gradYear)">{{ $gradYear }} <span>{{ number_format($gradCount) }}</span></button>
                    @endforeach
                </div>
            </div>
            <div class="pc-filter-clear-row" x-show="search || sport !== 'all' || plan !== 'all' || grad !== 'all' || state !== 'all'" x-cloak>
                <button type="button" class="pc-btn is-small" x-on:click="search='';sport='all';plan='all';grad='all';state='all'">Clear filters</button>
            </div>
        </div>

        <section class="pc-panel">
            <div class="pc-table-wrap">
                <table class="pc-table pc-athlete-table">
                    <thead><tr><th>Athlete</th><th>Sport</th><th>Plan</th><th>Profile</th><th>Last login</th><th>Outreach</th><th>Status</th><th>Health</th><th>Reminder</th></tr></thead>
                    <tbody>
                    @foreach($athletes as $athlete)
                        @php
                            $athleteReminder = $athlete['reminders'][$athlete['reminder_concern']] ?? null;
                        @endphp
                        <tr
                            x-show="matches(@js(['name'=>$athlete['name'],'sport'=>$athlete['sport'],'sport_key'=>$athlete['sport_key'],'plan'=>$athlete['plan'],'plan_key'=>$athlete['plan_key'],'club'=>$athlete['club'],'year'=>$athlete['year'],'email'=>$athlete['email'],'state'=>$athlete['state'],'health'=>$athlete['health'],'has_followup'=>!empty($athlete['flags'])]))"
                            class="pc-click-row"
                            x-on:click="athleteDrawer={{ (int) $athlete['id'] }}; document.body.style.overflow='hidden'"
                        >
                            <td><div class="pc-person"><span class="pc-avatar">{{ $athlete['initials'] }}</span><span><b>{{ $athlete['name'] }}</b><small>{{ $athlete['year'] ?: 'Grad year not set' }} · {{ $athlete['position'] }} · {{ $athlete['club'] }}</small></span></div></td>
                            <td>{{ $athlete['sport'] }}</td>
                            <td><span class="pc-plan is-{{ $athlete['plan_key'] }}">{{ $athlete['plan'] }}</span></td>
                            <td><div class="pc-health is-{{ $athlete['profile_completion'] >= 80 ? 'good' : ($athlete['profile_completion'] >= 55 ? 'mid' : 'bad') }}"><span><i style="width:{{ max(3,(int)$athlete['profile_completion']) }}%"></i></span><small>{{ $athlete['profile_completion'] }}%</small></div></td>
                            <td>{{ $athlete['last_login'] }}</td>
                            <td>
                                <div class="pc-outreach">
                                    <b>{{ number_format($athlete['outreach_sent']) }} sent</b>
                                    <small>{{ $athlete['last_outreach'] }}</small>
                                </div>
                            </td>
                            <td><span class="pc-state is-{{ $athlete['state'] }}">{{ str($athlete['state'])->replace('_',' ')->headline() }}</span></td>
                            <td><div class="pc-health is-{{ $athlete['health'] >= 80 ? 'good' : ($athlete['health'] >= 55 ? 'mid' : 'bad') }}"><span><i style="width:{{ max(3,(int)$athlete['health']) }}%"></i></span><small>{{ $athlete['health'] }}%</small></div></td>
                            <td class="pc-action-cell" x-on:click.stop>
                                <span class="pc-quick-actions">
                                    <button type="button" class="pc-btn is-small {{ ($athleteReminder['week_count'] ?? 0) > 0 ? 'pc-reminder-sent' : '' }}" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int) $athlete['id'] }}, concern: '{{ $athlete['reminder_concern'] }}' })">
                                        {{ ($athleteReminder['week_count'] ?? 0) > 0 ? '✓ Sent '.($athleteReminder['when'] ?? '') : 'Remind' }}
                                    </button>
                                    <span class="pc-impersonate-quick" title="Impersonate {{ $athlete['name'] }}">
                                        {{ ($this->impersonateAction)(['userId' => (int) $athlete['id']]) }}
                                    </span>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @if(!$login_tracking_available)<div class="pc-note">Login activity is not stored on this environment yet, so login-based follow-up flags are intentionally not fabricated.</div>@endif
    </section>

    @endif

    @if($initialTab === 'followups')
    <section class="pc-view">
        <div class="pc-view-head">
            <div><span class="pc-kicker">ACTION QUEUES</span><h2>Follow-ups</h2><p>Queues are built from what is actually blocking each player. Every action opens the existing Admin Support Messenger with the player and concern preselected.</p></div>
        </div>
        <div class="pc-queue-grid">
            @foreach($follow_up_queues as $queue)
                @php
                    $queueConcern = (string) ($queue['concern'] ?? 'custom');
                    $queueItems = collect($queue['items']);
                    $queueRemindedWeek = $queueItems->filter(
                        fn ($athlete) => (int) data_get($athlete, 'reminders.'.$queueConcern.'.week_count', 0) > 0
                    )->count();
                    $queuePending = max(0, (int) $queue['count'] - $queueRemindedWeek);
                @endphp
                <section class="pc-queue">
                    <header>
                        <div><span class="pc-queue-mark is-{{ $queue['key'] }}">{{ strtoupper(substr($queue['title'],0,1)) }}</span></div>
                        <div><h3>{{ $queue['title'] }}</h3><p>{{ $queue['description'] }}</p></div>
                        <strong>{{ number_format($queue['count']) }}</strong>
                    </header>
                    <div class="pc-queue-list">
                        @forelse(array_slice($queue['items'],0,8) as $athlete)
                            @php
                                $queueReminder = $athlete['reminders'][$queueConcern] ?? null;
                            @endphp
                            <div>
                                <button type="button" class="pc-person is-button" x-on:click="athleteDrawer={{ (int)$athlete['id'] }}; document.body.style.overflow='hidden'">
                                    <span class="pc-avatar">{{ $athlete['initials'] }}</span>
                                    <span>
                                        <b>{{ $athlete['name'] }}</b>
                                        <small>{{ $athlete['sport'] }} · {{ $athlete['plan'] }}</small>
                                        @if(filled($athlete['queue_detail'] ?? null))
                                            <em class="pc-queue-evidence">{{ $athlete['queue_detail'] }}</em>
                                        @endif
                                    </span>
                                </button>
                                <span class="pc-quick-actions">
                                    <button type="button" class="pc-btn is-small {{ ($queueReminder['week_count'] ?? 0) > 0 ? 'pc-reminder-sent' : '' }}" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int)$athlete['id'] }}, concern: '{{ $queueConcern }}' })">
                                        {{ ($queueReminder['week_count'] ?? 0) > 0 ? '✓ Sent '.($queueReminder['when'] ?? '') : 'Remind' }}
                                    </button>
                                    <span class="pc-impersonate-quick" title="Impersonate {{ $athlete['name'] }}">
                                        {{ ($this->impersonateAction)(['userId' => (int) $athlete['id']]) }}
                                    </span>
                                </span>
                            </div>
                        @empty
                            <div class="pc-empty">Queue is clear.</div>
                        @endforelse
                    </div>
                    <footer>
                        <b>{{ number_format($queuePending) }} not contacted</b>
                        @if($queueRemindedWeek > 0)<span>{{ number_format($queueRemindedWeek) }} reminded this week</span>@endif
                        @if($queue['count'] > 8)<span>{{ number_format($queue['count'] - 8) }} more in queue</span>@endif
                    </footer>
                </section>
            @endforeach
        </div>
    </section>

    @endif

    @if($initialTab === 'subscriptions')
    <section class="pc-view">
        <div class="pc-view-head">
            <div><span class="pc-kicker">REVENUE & ACCOUNT HEALTH</span><h2>Subscriptions</h2><p>Recurring revenue, conversion, and anything about to break.</p></div>
            @if($users_url)<a href="{{ $users_url }}" class="pc-btn">Manage subscription accounts</a>@endif
        </div>
        <div class="pc-stat-grid">
            <div class="pc-stat"><span class="pc-stat-icon is-green">↗</span><small>Monthly recurring</small><strong>{{ $billing_stats['mrr'] }}</strong><span>{{ number_format($billing_stats['active']) }} active subscriptions</span></div>
            <div class="pc-stat"><span class="pc-stat-icon is-coral">%</span><small>Free to paid</small><strong>{{ $billing_stats['conversion'] }}%</strong><span>{{ number_format($billing_stats['free']) }} still on free</span></div>
            <div class="pc-stat"><span class="pc-stat-icon is-red">!</span><small>Revenue at risk</small><strong>{{ $billing_stats['at_risk'] }}</strong><span>{{ number_format($billing_stats['past_due']) }} past due</span></div>
            <div class="pc-stat"><span class="pc-stat-icon">×</span><small>Churned</small><strong>{{ number_format($billing_stats['churned']) }}</strong><span>canceled / expired accounts</span></div>
            <div class="pc-stat"><span class="pc-stat-icon is-violet">▣</span><small>Setup fees recorded</small><strong>{{ $billing_stats['setup_fees'] }}</strong><span>configured one-time setup value on billing records</span></div>
        </div>

        <div class="pc-plan-grid">
            @foreach($plan_summary as $plan)
                <div class="pc-panel pc-plan-card">
                    <span class="pc-plan is-{{ str($plan['plan'])->slug() }}">{{ $plan['plan'] }}</span>
                    <strong>{{ number_format($plan['count']) }}</strong>
                    <small>
                        @if($plan['recurring_cents'] > 0)
                            &#36;{{ number_format($plan['recurring_cents'] / 100, 2) }}/mo recurring
                        @else
                            no active recurring revenue
                        @endif
                        @if(($plan['setup_cents'] ?? 0) > 0)
                            · &#36;{{ number_format($plan['setup_cents'] / 100, 2) }} setup recorded
                        @endif
                    </small>
                </div>
            @endforeach
        </div>

        <section class="pc-panel">
            <header class="pc-panel-head">
                <div><h3>Subscriptions</h3><p>Past due first, then active and inactive accounts.</p></div>
            </header>
            <div class="pc-table-wrap">
                <table class="pc-table">
                    <thead>
                        <tr>
                            <th>Athlete</th>
                            <th>Plan</th>
                            <th>Rate</th>
                            <th>Started</th>
                            <th>Last payment</th>
                            <th>Payment state</th>
                            <th>Subscription</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($billing_rows as $athlete)
                        @php
                            $billingReminder = $athlete['reminders']['payment_attention'] ?? null;
                        @endphp
                        <tr class="pc-click-row" x-on:click="athleteDrawer={{ (int) $athlete['id'] }}; document.body.style.overflow='hidden'">
                            <td>
                                <div class="pc-person">
                                    <span class="pc-avatar">{{ $athlete['initials'] }}</span>
                                    <span>
                                        <b>{{ $athlete['name'] }}</b>
                                        <small>{{ $athlete['sport'] }} · {{ $athlete['billing']['provider'] }}</small>
                                    </span>
                                </div>
                            </td>
                            <td><span class="pc-plan is-{{ $athlete['plan_key'] }}">{{ $athlete['plan'] }}</span></td>
                            <td class="pc-mono">
                                @if($athlete['billing']['recurring_cents'] > 0)
                                    &#36;{{ number_format($athlete['billing']['recurring_cents'] / 100, 2) }}/mo
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $athlete['billing']['started'] }}</td>
                            <td>
                                <div class="pc-payment-cell">
                                    <b>{{ $athlete['billing']['last_payment_amount'] }}</b>
                                    <small>{{ $athlete['billing']['last_payment'] }}</small>
                                </div>
                            </td>
                            <td><span class="pc-state is-{{ $athlete['state'] }}">{{ str($athlete['billing']['payment_status'])->headline() }}</span></td>
                            <td>{{ str($athlete['billing']['subscription_status'])->headline() }}</td>
                            <td x-on:click.stop>
                                <span class="pc-quick-actions">
                                    <button type="button" class="pc-btn is-small {{ ($billingReminder['week_count'] ?? 0) > 0 ? 'pc-reminder-sent' : '' }}" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int) $athlete['id'] }}, concern: 'payment_attention' })">
                                        {{ ($billingReminder['week_count'] ?? 0) > 0 ? '✓ Sent '.($billingReminder['when'] ?? '') : 'Remind' }}
                                    </button>
                                    <span class="pc-impersonate-quick" title="Impersonate {{ $athlete['name'] }}">
                                        {{ ($this->impersonateAction)(['userId' => (int) $athlete['id']]) }}
                                    </span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="pc-empty">No subscription records are available yet.</div></td></tr>
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

    @endif

    @if($initialTab === 'plyrlab')
    <section class="pc-view pc-plyrlab-hub">
        <div class="pc-view-head pc-plyrlab-head">
            <div>
                <span class="pc-kicker">{{ $plyrlab_is_trainer_portal ? 'TRAINER PORTAL' : 'PERFORMANCE OPERATIONS' }}</span>
                <h2>{{ $plyrlab_is_trainer_portal ? ($plyrlab_viewer_trainer['name'] ?? 'PLYRLab Trainer') : 'PLYRLab Management' }}</h2>
                <p>
                    @if($plyrlab_is_trainer_portal)
                        Manage your player bookings, monitor Plyomat connection status, and keep your trainer API connection ready.
                    @else
                        Manage trainers, manually connect athletes, monitor Plyomat provisioning, and reset player connections when needed.
                    @endif
                </p>
            </div>
            @if($plyrlab_is_superadmin)
                <div class="pc-view-actions">
                    @if($plyrlab_add_trainer_url)<a href="{{ $plyrlab_add_trainer_url }}" class="pc-btn is-primary">+ Add trainer</a>@endif
                    @if($plyrlab_trainers_url)<a href="{{ $plyrlab_trainers_url }}" class="pc-btn">Manage trainers</a>@endif
                </div>
            @else
                <div class="pc-plyrlab-api-badge is-{{ ($plyrlab_viewer_trainer['api_connected'] ?? false) ? 'ok' : 'wait' }}">
                    <span></span>
                    {{ ($plyrlab_viewer_trainer['api_connected'] ?? false) ? 'Plyomat API connected' : 'API key required' }}
                </div>
            @endif
        </div>

        <nav class="pc-plyrlab-tabs" aria-label="PLYRLab management sections">
            @if($plyrlab_is_superadmin)
                <button type="button" :class="{ 'is-active': plyrLabTab === 'overview' }" x-on:click="plyrLabTab='overview'">Overview</button>
                <button type="button" :class="{ 'is-active': plyrLabTab === 'bookings' }" x-on:click="plyrLabTab='bookings'">Bookings <span>{{ number_format($plyrlab_stats['bookings'] ?? 0) }}</span></button>
                <button type="button" :class="{ 'is-active': plyrLabTab === 'trainers' }" x-on:click="plyrLabTab='trainers'">Trainers <span>{{ number_format($plyrlab_stats['trainers'] ?? 0) }}</span></button>
            @else
                <button type="button" :class="{ 'is-active': plyrLabTab === 'bookings' }" x-on:click="plyrLabTab='bookings'">Bookings <span>{{ number_format($plyrlab_stats['bookings'] ?? 0) }}</span></button>
                <button type="button" :class="{ 'is-active': plyrLabTab === 'players' }" x-on:click="plyrLabTab='players'">Players <span>{{ number_format($plyrlab_stats['active_athletes'] ?? 0) }}</span></button>
                <button type="button" :class="{ 'is-active': plyrLabTab === 'api' }" x-on:click="plyrLabTab='api'">API Connection</button>
            @endif
        </nav>

        <div x-show="plyrLabTab === 'overview'" x-cloak>
            <div class="pc-stat-grid pc-stat-grid-four">
                <div class="pc-stat">
                    <span class="pc-stat-icon is-coral">⚗</span>
                    <small>PLYRLab trainers</small>
                    <strong>{{ number_format($plyrlab_stats['trainers'] ?? 0) }}</strong>
                    <span>{{ number_format($plyrlab_stats['bookable'] ?? 0) }} currently available for booking</span>
                </div>
                <div class="pc-stat">
                    <span class="pc-stat-icon is-green">✓</span>
                    <small>Plyomat connected</small>
                    <strong>{{ number_format($plyrlab_stats['connected'] ?? 0) }}</strong>
                    <span>trainers with API credentials ready for read/write athlete access</span>
                </div>
                <div class="pc-stat">
                    <span class="pc-stat-icon is-blue">◎</span>
                    <small>Connected players</small>
                    <strong>{{ number_format($plyrlab_stats['active_athletes'] ?? 0) }}</strong>
                    <span>{{ number_format($plyrlab_stats['bookings'] ?? 0) }} total trainer bookings</span>
                </div>
                <div class="pc-stat">
                    <span class="pc-stat-icon is-amber">!</span>
                    <small>Needs attention</small>
                    <strong>{{ number_format($plyrlab_stats['needs_attention'] ?? 0) }}</strong>
                    <span>waiting for API setup, Athlete ID, or provisioning recovery</span>
                </div>
            </div>

            <section class="pc-panel pc-plyrlab-overview-panel">
                <header class="pc-panel-head">
                    <div><h3>Connection health</h3><p>Operational state across trainer APIs and recent player bookings.</p></div>
                    <button type="button" class="pc-btn is-small" x-on:click="plyrLabTab='bookings'">Open bookings</button>
                </header>
                <div class="pc-plyrlab-health-grid">
                    <div><small>CONNECTED PLAYERS</small><b>{{ number_format($plyrlab_stats['active_athletes'] ?? 0) }}</b><span>Verified Athlete ID + active access</span></div>
                    <div><small>TRAINER API READY</small><b>{{ number_format($plyrlab_stats['connected'] ?? 0) }}/{{ number_format($plyrlab_stats['trainers'] ?? 0) }}</b><span>API key available</span></div>
                    <div><small>NEEDS ATTENTION</small><b>{{ number_format($plyrlab_stats['needs_attention'] ?? 0) }}</b><span>Review in Bookings</span></div>
                </div>
            </section>
        </div>

        @if($plyrlab_is_superadmin)
        <div x-show="plyrLabTab === 'trainers'" x-cloak>
            <section class="pc-panel">
                <header class="pc-panel-head">
                    <div><h3>Trainer management</h3><p>Registered PLYRCARD trainer accounts and their Plyomat readiness.</p></div>
                    <div class="pc-view-actions">
                        @if($plyrlab_add_trainer_url)<a href="{{ $plyrlab_add_trainer_url }}" class="pc-btn is-small is-primary">Add trainer</a>@endif
                        @if($plyrlab_trainers_url)<a href="{{ $plyrlab_trainers_url }}" class="pc-btn is-small">Open full manager</a>@endif
                    </div>
                </header>
                <div class="pc-table-wrap">
                    <table class="pc-table">
                        <thead><tr><th>Trainer</th><th>Specialties</th><th>Booking</th><th>Plyomat</th><th>Players</th><th>Bookings</th><th>Last booking</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse($plyrlab_trainers as $trainer)
                            <tr>
                                <td><div class="pc-person"><span class="pc-avatar">{{ $trainer['initials'] }}</span><span><b>{{ $trainer['name'] }}</b><small>{{ $trainer['headline'] }}{{ $trainer['location'] ? ' · '.$trainer['location'] : '' }}</small></span></div></td>
                                <td>
                                    @if(count($trainer['specialties']))
                                        <div class="pc-chip-row">@foreach(array_slice($trainer['specialties'],0,3) as $specialty)<span class="pc-pill">{{ $specialty }}</span>@endforeach</div>
                                    @else
                                        <span class="pc-muted-value">Not set</span>
                                    @endif
                                </td>
                                <td><span class="pc-state is-{{ $trainer['bookable'] ? 'active' : 'churned' }}">{{ $trainer['bookable'] ? 'Bookable' : 'Hidden' }}</span></td>
                                <td><span class="pc-state is-{{ $trainer['plyomat_connected'] ? 'active' : 'past_due' }}">{{ $trainer['plyomat_connected'] ? 'Connected' : 'API needed' }}</span></td>
                                <td class="pc-mono">{{ number_format($trainer['active_athletes']) }}</td>
                                <td class="pc-mono">{{ number_format($trainer['bookings']) }}</td>
                                <td>{{ $trainer['last_booking'] }}</td>
                                <td>@if($trainer['edit_url'])<a href="{{ $trainer['edit_url'] }}" class="pc-btn is-small">Edit trainer</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><div class="pc-empty">No PLYRLab trainers have been configured yet.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        @endif

        <div x-show="plyrLabTab === 'bookings'" x-cloak>
            @if($plyrlab_is_superadmin)
                <section class="pc-panel pc-plyrlab-admin-connect">
                    <header class="pc-panel-head">
                        <div>
                            <h3>Manual player connection</h3>
                            <p>Use this when a Plyomat athlete already exists or automatic provisioning cannot be used. The Athlete ID is verified through the selected trainer before PLYRLab is unlocked.</p>
                        </div>
                    </header>
                    <div class="pc-plyrlab-admin-connect-form">
                        <label>
                            <span>Player</span>
                            <select wire:model.defer="plyrLabAdminConnect.player_id">
                                <option value="">Select player</option>
                                @foreach($athletes as $athlete)
                                    <option value="{{ $athlete['id'] }}">{{ $athlete['name'] }} · {{ $athlete['sport'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Trainer</span>
                            <select wire:model.defer="plyrLabAdminConnect.trainer_id">
                                <option value="">Select trainer</option>
                                @foreach($plyrlab_trainers as $trainer)
                                    <option value="{{ $trainer['id'] }}">{{ $trainer['name'] }}{{ $trainer['plyomat_connected'] ? '' : ' · API pending' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Plyomat Athlete ID</span>
                            <input type="text" wire:model.defer="plyrLabAdminConnect.athlete_id" placeholder="Existing Athlete ID">
                        </label>
                        <button type="button" class="pc-btn is-primary" wire:click="manualConnectPlyrLabPlayer" wire:loading.attr="disabled" wire:target="manualConnectPlyrLabPlayer">Verify & Connect</button>
                    </div>
                </section>
            @endif

            <section class="pc-panel">
                <header class="pc-panel-head">
                    <div>
                        <h3>{{ $plyrlab_is_trainer_portal ? 'My player bookings' : 'Player bookings & connections' }}</h3>
                        <p>{{ $plyrlab_is_trainer_portal ? 'Manage your scheduled players and monitor whether each Plyomat profile is ready.' : 'Manual connect, reset, and troubleshoot every player-to-trainer connection.' }}</p>
                    </div>
                </header>
                <div class="pc-table-wrap">
                    <table class="pc-table pc-plyrlab-bookings-table">
                        <thead>
                            <tr>
                                <th>Player</th>
                                @if($plyrlab_is_superadmin)<th>Trainer</th>@endif
                                <th>Session</th>
                                <th>Booking</th>
                                <th>Plyomat</th>
                                <th>Last sync</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($plyrlab_bookings as $booking)
                            <tr>
                                <td>
                                    <div class="pc-person">
                                        <span class="pc-avatar">{{ $booking['initials'] }}</span>
                                        <span><b>{{ $booking['player'] }}</b><small>{{ $booking['sport'] }}{{ $booking['athlete_id'] ? ' · ID '.$booking['athlete_id'] : '' }}</small></span>
                                    </div>
                                    @if($booking['connection_error'])
                                        <small class="pc-plyrlab-error">{{ \Illuminate\Support\Str::limit($booking['connection_error'], 130) }}</small>
                                    @endif
                                </td>
                                @if($plyrlab_is_superadmin)<td>{{ $booking['trainer'] }}</td>@endif
                                <td>{{ $booking['scheduled_for'] }}</td>
                                <td><span class="pc-state is-{{ $booking['needs_attention'] ? 'past_due' : ($booking['status'] === 'active' || $booking['status'] === 'completed' ? 'active' : ($booking['status'] === 'cancelled' ? 'churned' : 'needs_push')) }}">{{ $booking['status_label'] }}</span></td>
                                <td>
                                    @if($booking['status'] === 'cancelled')
                                        <span class="pc-state is-churned">Disconnected</span>
                                    @elseif($booking['plyomat_ready'])
                                        <span class="pc-state is-active">Connected</span>
                                    @elseif($booking['status'] === 'awaiting_integration')
                                        <span class="pc-state is-past_due">Trainer API pending</span>
                                    @else
                                        <span class="pc-state is-needs_push">Awaiting connection</span>
                                    @endif
                                </td>
                                <td>{{ $booking['last_sync'] }}</td>
                                <td>
                                    <div class="pc-plyrlab-row-actions">
                                        @if($booking['user_url'])<a href="{{ $booking['user_url'] }}" class="pc-btn is-small">View player</a>@endif

                                        @if($booking['can_manage'] && $booking['status'] !== 'completed' && $booking['status'] !== 'cancelled')
                                            <button type="button" class="pc-btn is-small" wire:click="setPlyrLabBookingStatus({{ (int)$booking['id'] }}, 'completed')">Complete</button>
                                            <button type="button" class="pc-btn is-small is-danger" wire:click="setPlyrLabBookingStatus({{ (int)$booking['id'] }}, 'cancelled')" wire:confirm="Cancel this PLYRLab booking?">Cancel</button>
                                        @elseif($booking['can_manage'] && $booking['status'] === 'completed' && $booking['plyomat_ready'])
                                            <button type="button" class="pc-btn is-small" wire:click="setPlyrLabBookingStatus({{ (int)$booking['id'] }}, 'active')">Reopen</button>
                                        @endif

                                        @if($booking['can_reset'])
                                            <button type="button" class="pc-btn is-small is-danger" wire:click="resetPlyrLabBooking({{ (int)$booking['id'] }})" wire:confirm="Reset PLYRLab for {{ $booking['player'] }}? This clears the local Athlete ID and synced data, then lets the player start again. The Plyomat athlete itself is not deleted.">Reset</button>
                                        @endif
                                    </div>

                                    @if($plyrlab_is_superadmin && $booking['status'] !== 'cancelled')
                                        <div class="pc-plyrlab-manual-connect">
                                            <select wire:model.defer="plyrLabManualConnections.{{ (int)$booking['id'] }}.trainer_id">
                                                <option value="">Select trainer</option>
                                                @foreach($plyrlab_trainers as $trainer)
                                                    <option value="{{ $trainer['id'] }}">{{ $trainer['name'] }}{{ $trainer['plyomat_connected'] ? '' : ' · API pending' }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" wire:model.defer="plyrLabManualConnections.{{ (int)$booking['id'] }}.athlete_id" placeholder="Plyomat Athlete ID">
                                            <button type="button" class="pc-btn is-small is-primary" wire:click="manualConnectPlyrLabBooking({{ (int)$booking['id'] }})">Connect ID</button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $plyrlab_is_superadmin ? 7 : 6 }}"><div class="pc-empty">No PLYRLab bookings are available yet.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        @if($plyrlab_is_trainer_portal)
            <div x-show="plyrLabTab === 'players'" x-cloak>
                @php($trainerPlayers = collect($plyrlab_bookings)->unique('player_id')->values())
                <div class="pc-plyrlab-player-grid">
                    @forelse($trainerPlayers as $playerBooking)
                        <article class="pc-panel pc-plyrlab-player-card">
                            <div class="pc-person">
                                <span class="pc-avatar is-large">{{ $playerBooking['initials'] }}</span>
                                <span><b>{{ $playerBooking['player'] }}</b><small>{{ $playerBooking['sport'] }}</small></span>
                            </div>
                            <div class="pc-plyrlab-player-status">
                                <span class="pc-state is-{{ $playerBooking['plyomat_ready'] ? 'active' : 'needs_push' }}">{{ $playerBooking['plyomat_ready'] ? 'Plyomat connected' : 'Not connected' }}</span>
                                <span class="pc-state is-{{ $playerBooking['needs_attention'] ? 'past_due' : 'active' }}">{{ $playerBooking['status_label'] }}</span>
                            </div>
                            <div class="pc-kv"><span>Athlete ID</span><b>{{ $playerBooking['athlete_id'] ?: 'Pending' }}</b></div>
                            <div class="pc-kv"><span>Session</span><b>{{ $playerBooking['scheduled_for'] }}</b></div>
                            <div class="pc-kv"><span>Last sync</span><b>{{ $playerBooking['last_sync'] }}</b></div>
                            @if($playerBooking['user_url'])<a href="{{ $playerBooking['user_url'] }}" class="pc-btn is-small">Open player</a>@endif
                        </article>
                    @empty
                        <div class="pc-empty">No players have booked you yet.</div>
                    @endforelse
                </div>
            </div>

            <div x-show="plyrLabTab === 'api'" x-cloak>
                <section class="pc-panel pc-plyrlab-api-panel">
                    <header class="pc-panel-head">
                        <div>
                            <h3>Plyomat API Connection</h3>
                            <p>Your secret token is encrypted at rest and is never shown back after saving.</p>
                        </div>
                        <span class="pc-state is-{{ ($plyrlab_viewer_trainer['api_connected'] ?? false) ? 'active' : 'past_due' }}">{{ ($plyrlab_viewer_trainer['api_connected'] ?? false) ? 'Connected' : 'Connection required' }}</span>
                    </header>
                    <div class="pc-plyrlab-api-body">
                        @if(!($plyrlab_viewer_trainer['api_connected'] ?? false))
                            <div class="pc-note">Players may still book you, but their PLYRLab stays locked in <b>Awaiting Trainer Setup</b> until you connect a valid Plyomat token with Athlete Read + Athlete Write access.</div>
                        @else
                            <div class="pc-note">Your API connection is active. Saving a new token below replaces the current encrypted token.</div>
                        @endif
                        <label class="pc-plyrlab-field">
                            <span>Plyomat secret token</span>
                            <input type="password" wire:model.defer="plyrLabTrainerApiKey" placeholder="Paste Bearer token">
                        </label>
                        <button type="button" class="pc-btn is-primary" wire:click="saveMyPlyrLabApiKey" wire:loading.attr="disabled" wire:target="saveMyPlyrLabApiKey">
                            {{ ($plyrlab_viewer_trainer['api_connected'] ?? false) ? 'Replace API Key' : 'Connect API Key' }}
                        </button>
                    </div>
                </section>
            </div>
        @endif

        <div class="pc-note">
            @if($plyrlab_is_trainer_portal)
                Your trainer login uses your normal PLYRCard account. Player data shown here is limited to athletes who booked your PLYRLab trainer profile.
            @else
                Manual connection verifies the Athlete ID using the selected trainer's API key before saving it. Reset only disconnects PLYRCard; it does not delete the athlete from Plyomat.
            @endif
        </div>
    </section>

    @endif

    @if($initialTab === 'amplify')
    <section class="pc-view">
        <div class="pc-view-head">
            <div>
                <span class="pc-kicker">PACKAGE DELIVERY</span>
                <h2>Amplify credits</h2>
                <p>What families paid for and what we still owe them through PLYR Points and service delivery.</p>
            </div>
            @if($credit_requests_url)<a href="{{ $credit_requests_url }}" class="pc-btn">Open Credit Requests</a>@endif
        </div>

        <div class="pc-stat-grid pc-stat-grid-four">
            <div class="pc-stat"><span class="pc-stat-icon is-violet">ϟ</span><small>Amplify athletes</small><strong>{{ number_format($amplify_credit_stats['athletes'] ?? 0) }}</strong><span>{{ number_format($amplify_credit_stats['jumpstart'] ?? 0) }} of them Jumpstart</span></div>
            <div class="pc-stat"><span class="pc-stat-icon is-green">✓</span><small>Credits delivered</small><strong>{{ number_format($amplify_credit_stats['points_redeemed'] ?? 0) }}</strong><span>of {{ number_format($amplify_credit_stats['points_granted'] ?? 0) }} package points granted</span></div>
            <div class="pc-stat"><span class="pc-stat-icon is-amber">◷</span><small>Credits outstanding</small><strong>{{ number_format($amplify_credit_stats['points_available'] ?? 0) }}</strong><span>PLYR Points still available to use</span></div>
            <div class="pc-stat"><span class="pc-stat-icon is-red">!</span><small>Idle 30+ days</small><strong>{{ number_format($amplify_credit_stats['idle'] ?? 0) }}</strong><span>package balances needing follow-up</span></div>
        </div>

        <section class="pc-panel">
            <header class="pc-panel-head">
                <div><h3>Delivery across every package</h3><p>Real service requests grouped by the four package service categories.</p></div>
            </header>
            <div class="pc-credit-service-grid">
                @foreach($amplify_credit_services as $service)
                    <div class="pc-credit-service">
                        <small>{{ $service['label'] }}</small>
                        <strong>{{ number_format($service['requests']) }}</strong>
                        <span>{{ number_format($service['points']) }} pts redeemed</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="pc-panel">
            <header class="pc-panel-head">
                <div><h3>Credit ledger</h3><p>Package accounts with current balance and service usage. Highest idle balance is prioritized.</p></div>
            </header>
            <div class="pc-table-wrap">
                <table class="pc-table">
                    <thead>
                        <tr>
                            <th>Athlete</th>
                            <th>Package</th>
                            <th>Outreach</th>
                            <th>Reels</th>
                            <th>Graphics</th>
                            <th>Rep hours</th>
                            <th>Used</th>
                            <th>Available</th>
                            <th>Last activity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($amplify_credit_rows as $credit)
                        <tr class="pc-click-row" x-on:click="athleteDrawer={{ (int) $credit['id'] }}; document.body.style.overflow='hidden'">
                            <td>
                                <div class="pc-person">
                                    <span class="pc-avatar">{{ $credit['initials'] }}</span>
                                    <span><b>{{ $credit['name'] }}</b><small>{{ $credit['sport'] }}</small></span>
                                </div>
                            </td>
                            <td><span class="pc-plan is-{{ $credit['package_key'] }}">{{ $credit['package'] }}</span></td>
                            <td class="pc-credit-cell">{{ number_format($credit['usage']['outreach']['qty']) }}<small>{{ number_format($credit['usage']['outreach']['points']) }} pts</small></td>
                            <td class="pc-credit-cell">{{ number_format($credit['usage']['reel']['qty']) }}<small>{{ number_format($credit['usage']['reel']['points']) }} pts</small></td>
                            <td class="pc-credit-cell">{{ number_format($credit['usage']['graphic']['qty']) }}<small>{{ number_format($credit['usage']['graphic']['points']) }} pts</small></td>
                            <td class="pc-credit-cell">{{ number_format($credit['usage']['production_hour']['qty']) }}<small>{{ number_format($credit['usage']['production_hour']['points']) }} pts</small></td>
                            <td>
                                @if($credit['usage_percent'] !== null)
                                    <div class="pc-health is-{{ $credit['usage_percent'] >= 75 ? 'good' : ($credit['usage_percent'] >= 30 ? 'mid' : 'bad') }}"><span><i style="width:{{ max(3,(int)$credit['usage_percent']) }}%"></i></span><small>{{ $credit['usage_percent'] }}%</small></div>
                                @else
                                    <span class="pc-muted-value">—</span>
                                @endif
                            </td>
                            <td class="pc-mono">{{ number_format($credit['points_available']) }}</td>
                            <td><span class="{{ $credit['idle'] ? 'pc-idle' : '' }}">{{ $credit['last_activity'] }}</span></td>
                            <td x-on:click.stop>
                                <span class="pc-quick-actions">
                                    <button type="button" class="pc-btn is-small" wire:click="$dispatch('open-admin-support-reminder', { userId: {{ (int)$credit['id'] }}, concern: 'amplify_credits' })">Remind</button>
                                    <span class="pc-impersonate-quick" title="Impersonate {{ $credit['name'] }}">
                                        {{ ($this->impersonateAction)(['userId' => (int) $credit['id']]) }}
                                    </span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="pc-empty">No Amplify or Jumpstart package-credit records are available yet.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="pc-note">Package points are read from the existing PLYR Points ledger. The current database does not store a separate package-expiration date, so this view does not invent one.</div>
    </section>

    @endif

    <div x-cloak x-show="athleteDrawer || activityDrawer" x-transition.opacity class="pc-scrim" x-on:click="closeOverlays()"></div>

    @foreach($athletes as $athlete)
        <aside x-cloak x-show="athleteDrawer === {{ (int)$athlete['id'] }}" x-transition:enter="pc-slide-in" x-transition:leave="pc-slide-out" class="pc-drawer">
            <header class="pc-drawer-head">
                <span class="pc-avatar is-large">{{ $athlete['initials'] }}</span>
                <div><h3>{{ $athlete['name'] }}</h3><p>{{ $athlete['sport'] }} · {{ $athlete['position'] }} · {{ $athlete['year'] ?: 'Grad year not set' }} · {{ $athlete['club'] }}</p><div class="pc-chip-row"><span class="pc-plan is-{{ $athlete['plan_key'] }}">{{ $athlete['plan'] }}</span><span class="pc-state is-{{ $athlete['state'] }}">{{ str($athlete['state'])->replace('_',' ')->headline() }}</span></div></div>
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

.pc-view{display:flex;flex-direction:column;gap:1rem}.pc-view-head,.pc-section-title{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem}.pc-view-head h2,.pc-section-title h3{margin:.1rem 0 0;font-size:1.08rem;font-weight:800;letter-spacing:-.025em}.pc-view-head p,.pc-section-title p{margin:.18rem 0 0;color:var(--pc-muted);font-size:.72rem}.pc-kicker{color:var(--pc-coral);font-size:.58rem;font-weight:850;letter-spacing:.13em}.pc-updated{color:var(--pc-dim);font-size:.64rem}
.pc-stat-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.75rem}.pc-stat-grid-four{grid-template-columns:repeat(4,minmax(0,1fr))}.pc-stat{min-width:0;min-height:8.6rem;padding:.88rem;border:1px solid var(--pc-line);border-radius:.7rem;background:var(--pc-panel);color:inherit;text-align:left}.pc-stat button&{cursor:pointer}.pc-stat:hover{border-color:var(--pc-line2)}.pc-stat-icon{width:1.85rem;height:1.85rem;display:grid;place-items:center;margin-bottom:.7rem;border-radius:.5rem;background:var(--pc-panel2);font-weight:900}.pc-stat-icon.is-coral{background:rgba(255,100,72,.11);color:var(--pc-coral)}.pc-stat-icon.is-green{background:rgba(34,192,138,.11);color:var(--pc-green)}.pc-stat-icon.is-amber{background:rgba(240,169,59,.12);color:var(--pc-amber)}.pc-stat-icon.is-red{background:rgba(242,86,79,.11);color:var(--pc-red)}.pc-stat-icon.is-blue{background:rgba(76,141,255,.11);color:var(--pc-blue)}.pc-stat-icon.is-violet{background:rgba(156,123,255,.11);color:var(--pc-violet)}.pc-stat small{display:block;color:var(--pc-muted);font-size:.66rem;font-weight:700}.pc-stat strong{display:block;margin-top:.25rem;font-size:1.55rem;letter-spacing:-.045em}.pc-stat>span:last-child{display:block;margin-top:.6rem;color:var(--pc-dim);font-size:.64rem;line-height:1.35}
.pc-dashboard-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(18rem,.7fr);gap:.85rem}.pc-side-stack{display:flex;flex-direction:column;gap:.85rem}.pc-panel,.pc-queue{overflow:hidden;border:1px solid var(--pc-line);border-radius:.72rem;background:var(--pc-panel)}.pc-panel-head{min-height:3.5rem;padding:.78rem .88rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;border-bottom:1px solid var(--pc-line)}.pc-panel-head h3{margin:0;font-size:.78rem;font-weight:800}.pc-panel-head p{margin:.13rem 0 0;color:var(--pc-dim);font-size:.63rem}.pc-btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;padding:.42rem .62rem;border:1px solid var(--pc-line2);border-radius:.5rem;background:var(--pc-panel);color:var(--pc-text);font-size:.65rem;font-weight:750;text-decoration:none;cursor:pointer;white-space:nowrap}.pc-btn:hover{background:var(--pc-panel2)}.pc-btn.is-primary{background:var(--pc-coral);border-color:var(--pc-coral);color:#fff}.pc-btn.is-small{padding:.32rem .48rem;font-size:.61rem}
.pc-table-wrap{overflow:auto}.pc-table{width:100%;border-collapse:collapse}.pc-table th{padding:.58rem .72rem;border-bottom:1px solid var(--pc-line);background:var(--pc-panel2);color:var(--pc-dim);font-size:.59rem;font-weight:800;letter-spacing:.08em;text-align:left;text-transform:uppercase;white-space:nowrap}.pc-table td{padding:.64rem .72rem;border-bottom:1px solid var(--pc-line);font-size:.68rem;vertical-align:middle}.pc-table tbody tr:last-child td{border-bottom:0}.pc-click-row{cursor:pointer}.pc-click-row:hover{background:var(--pc-panel2)}.pc-action-cell{text-align:right}.pc-quick-actions{display:inline-flex;align-items:center;justify-content:flex-end;gap:.35rem}.pc-impersonate-quick{display:inline-flex;align-items:center;justify-content:center}.pc-impersonate-quick .fi-icon-btn{width:1.9rem;height:1.9rem;min-width:1.9rem;border:1px solid var(--pc-line2);border-radius:.5rem;background:var(--pc-panel);color:var(--pc-muted)}.pc-impersonate-quick .fi-icon-btn:hover{border-color:rgba(255,100,72,.38);background:rgba(255,100,72,.08);color:var(--pc-coral)}.pc-person{min-width:0;display:flex;align-items:center;gap:.55rem;color:inherit;text-decoration:none}.pc-person.is-button{border:0;background:transparent;text-align:left;cursor:pointer}.pc-person>span:last-child{min-width:0}.pc-person b{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.69rem}.pc-person small{display:block;margin-top:.11rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--pc-dim);font-size:.59rem}.pc-avatar{width:1.9rem;height:1.9rem;display:grid;place-items:center;flex:0 0 1.9rem;border:1px solid var(--pc-line2);border-radius:.55rem;background:var(--pc-panel2);font-size:.59rem;font-weight:800}.pc-avatar.is-large{width:2.7rem;height:2.7rem;flex-basis:2.7rem;font-size:.75rem}
.pc-pill,.pc-plan,.pc-state{display:inline-flex;align-items:center;padding:.25rem .42rem;border:1px solid var(--pc-line);border-radius:999px;background:var(--pc-panel2);color:var(--pc-muted);font-size:.58rem;font-weight:800;white-space:nowrap}.pc-pill.is-danger,.pc-state.is-past_due,.pc-state.is-at_risk{color:var(--pc-red);border-color:rgba(242,86,79,.3);background:rgba(242,86,79,.08)}.pc-pill.is-warning,.pc-state.is-needs_push{color:var(--pc-amber);border-color:rgba(240,169,59,.3);background:rgba(240,169,59,.08)}.pc-state.is-active{color:var(--pc-green);border-color:rgba(34,192,138,.3);background:rgba(34,192,138,.08)}.pc-state.is-churned{color:var(--pc-muted)}.pc-plan.is-free-plyr{color:var(--pc-muted);border-color:var(--pc-line2);background:var(--pc-panel2)}.pc-plan.is-my-journey{color:var(--pc-blue);border-color:rgba(76,141,255,.34);background:rgba(76,141,255,.10)}.pc-plan.is-jumpstart{color:var(--pc-violet);border-color:rgba(156,123,255,.34);background:rgba(156,123,255,.10)}.pc-plan.is-amplify{color:var(--pc-coral);border-color:rgba(255,100,72,.34);background:rgba(255,100,72,.10)}
.pc-health{display:flex;align-items:center;gap:.35rem;min-width:5rem}.pc-health>span{height:.27rem;min-width:2.5rem;flex:1;overflow:hidden;border-radius:999px;background:var(--pc-line)}.pc-health i{display:block;height:100%;border-radius:inherit;background:var(--pc-coral)}.pc-health.is-good i{background:var(--pc-green)}.pc-health.is-mid i{background:var(--pc-amber)}.pc-health.is-bad i{background:var(--pc-red)}.pc-health small{width:2rem;color:var(--pc-muted);font-size:.58rem;text-align:right}.pc-outreach,.pc-payment-cell{display:flex;flex-direction:column;gap:.08rem}.pc-outreach b,.pc-payment-cell b{font-size:.64rem}.pc-outreach small,.pc-payment-cell small{color:var(--pc-dim);font-size:.56rem}.pc-reminder-sent{border-style:dashed;border-color:rgba(34,192,138,.34);color:var(--pc-green);background:rgba(34,192,138,.05)}
.pc-sports{padding:.75rem .85rem}.pc-sport{width:100%;display:grid;grid-template-columns:6rem 1fr 1.5rem;align-items:center;gap:.55rem;padding:.34rem 0;border:0;background:transparent;color:inherit;text-align:left;cursor:pointer}.pc-sport b{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.65rem}.pc-sport>span{height:.25rem;overflow:hidden;border-radius:999px;background:var(--pc-line)}.pc-sport i{display:block;height:100%;background:var(--pc-coral)}.pc-sport small{text-align:right;color:var(--pc-muted);font-size:.6rem}.pc-timeline{padding:.7rem .85rem}.pc-timeline>div{display:grid;grid-template-columns:.45rem 1fr;gap:.55rem;padding:.48rem 0;border-bottom:1px solid var(--pc-line)}.pc-timeline>div:last-child{border-bottom:0}.pc-timeline i{width:.4rem;height:.4rem;margin-top:.22rem;border-radius:999px;background:var(--pc-coral)}.pc-timeline i.ok{background:var(--pc-green)}.pc-timeline i.bad{background:var(--pc-red)}.pc-timeline b{display:block;font-size:.63rem}.pc-timeline small{display:block;margin-top:.1rem;color:var(--pc-dim);font-size:.58rem}
.pc-quick-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.pc-mini-list{display:flex;flex-direction:column}.pc-mini-list>a,.pc-mini-list>button{display:grid;grid-template-columns:1.9rem minmax(0,1fr) auto;align-items:center;gap:.55rem;padding:.58rem .75rem;border:0;border-bottom:1px solid var(--pc-line);background:transparent;color:inherit;text-align:left;text-decoration:none;cursor:pointer}.pc-mini-player-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;border-bottom:1px solid var(--pc-line)}.pc-mini-player-row:last-child{border-bottom:0}.pc-mini-player-row:hover{background:var(--pc-panel2)}.pc-mini-player-main{min-width:0;display:grid;grid-template-columns:1.9rem minmax(0,1fr) auto;align-items:center;gap:.55rem;padding:.58rem .45rem .58rem .75rem;border:0;background:transparent;color:inherit;text-align:left;cursor:pointer}.pc-mini-player-row>.pc-impersonate-quick{padding-right:.65rem}.pc-mini-list>a:last-child,.pc-mini-list>button:last-child{border-bottom:0}.pc-mini-list>a:hover,.pc-mini-list>button:hover{background:var(--pc-panel2)}.pc-mini-list b{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.66rem}.pc-mini-list small{display:block;margin-top:.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--pc-dim);font-size:.57rem}.pc-money{color:var(--pc-green);font-size:.7rem}.pc-empty{padding:1rem;color:var(--pc-dim);font-size:.68rem}
.pc-view-actions{display:flex;align-items:center;justify-content:flex-end;gap:.5rem;flex-wrap:wrap}.pc-search-box{min-width:15.5rem;min-height:2.25rem;display:flex;align-items:center;gap:.4rem;padding:0 .6rem;border:1px solid var(--pc-line);border-radius:.5rem;background:var(--pc-panel);color:var(--pc-text);font-size:.66rem}.pc-search-box input{width:100%;border:0;outline:0;background:transparent;color:inherit;font:inherit}.pc-state-select{min-height:2.25rem;padding:.35rem .55rem;border:1px solid var(--pc-line);border-radius:.5rem;background:var(--pc-panel);color:var(--pc-text);font-size:.65rem}.pc-filter-panel{overflow:hidden;border:1px solid var(--pc-line);border-radius:.7rem;background:var(--pc-panel2)}.pc-filter-row{display:flex;align-items:flex-start;gap:.75rem;padding:.65rem .85rem;border-bottom:1px solid var(--pc-line)}.pc-filter-row:last-of-type{border-bottom:0}.pc-filter-label{width:3.5rem;flex:0 0 3.5rem;padding-top:.32rem;color:var(--pc-dim);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.58rem;font-weight:800;letter-spacing:.09em}.pc-filter-chips{display:flex;align-items:center;gap:.38rem;flex-wrap:wrap;min-width:0}.pc-filter-chip{display:inline-flex;align-items:center;gap:.22rem;padding:.34rem .62rem;border:1px solid var(--pc-line);border-radius:999px;background:var(--pc-panel);color:var(--pc-muted);font-size:.63rem;font-weight:750;cursor:pointer;transition:background .12s,border-color .12s,color .12s}.pc-filter-chip:hover{border-color:var(--pc-line2);color:var(--pc-text)}.pc-filter-chip.is-active{border-color:var(--pc-coral);background:var(--pc-coral);color:#fff}.pc-filter-chip span{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.56rem;opacity:.68}.pc-filter-divider{width:1px;height:1.25rem;margin:0 .15rem;background:var(--pc-line2)}.pc-filter-clear-row{display:flex;justify-content:flex-end;padding:.48rem .85rem;border-top:1px solid var(--pc-line);background:var(--pc-panel)}.pc-note{padding:.65rem .8rem;border:1px dashed var(--pc-line2);border-radius:.55rem;background:var(--pc-panel2);color:var(--pc-muted);font-size:.63rem}
.pc-queue-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.pc-queue>header{display:grid;grid-template-columns:2rem minmax(0,1fr) auto;gap:.6rem;align-items:start;padding:.72rem .78rem;border-bottom:1px solid var(--pc-line)}.pc-queue>header h3{margin:0;font-size:.72rem}.pc-queue>header p{margin:.12rem 0 0;color:var(--pc-dim);font-size:.6rem;line-height:1.35}.pc-queue>header>strong{font-size:1.25rem}.pc-queue-mark{width:1.8rem;height:1.8rem;display:grid;place-items:center;border-radius:.5rem;background:rgba(240,169,59,.1);color:var(--pc-amber);font-size:.68rem;font-weight:900}.pc-queue-mark.is-billing{background:rgba(242,86,79,.1);color:var(--pc-red)}.pc-queue-mark.is-amplify_credits{background:rgba(156,123,255,.11);color:var(--pc-violet)}.pc-queue-list{max-height:17rem;overflow:auto}.pc-queue-list>div{display:flex;align-items:center;justify-content:space-between;gap:.65rem;padding:.48rem .78rem;border-bottom:1px solid var(--pc-line)}.pc-queue-evidence{display:block;margin-top:.12rem;color:var(--pc-dim);font-size:.54rem;font-style:normal;line-height:1.35;white-space:normal}.pc-queue-list>div:last-child{border-bottom:0}.pc-queue footer{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap;padding:.55rem .78rem;border-top:1px solid var(--pc-line);color:var(--pc-dim);font-size:.59rem}.pc-queue footer b{color:var(--pc-coral)}
.pc-plan-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:.65rem}.pc-plan-card{padding:.75rem}.pc-plan-card>strong{display:block;margin-top:.5rem;font-size:1.35rem}.pc-plan-card>small{display:block;margin-top:.2rem;color:var(--pc-dim);font-size:.6rem}.pc-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.pc-credit-service-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem;padding:.75rem}.pc-credit-service{padding:.72rem;border:1px solid var(--pc-line);border-radius:.6rem;background:var(--pc-panel2)}.pc-credit-service small{display:block;color:var(--pc-muted);font-size:.61rem;font-weight:750}.pc-credit-service strong{display:block;margin-top:.35rem;font-size:1.25rem}.pc-credit-service span{display:block;margin-top:.18rem;color:var(--pc-dim);font-size:.58rem}.pc-credit-cell{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-weight:750}.pc-credit-cell small{display:block;margin-top:.08rem;color:var(--pc-dim);font-size:.54rem;font-weight:500}.pc-idle{color:var(--pc-red);font-weight:750}.pc-muted-value{color:var(--pc-dim)}
.pc-scrim{position:fixed;inset:0;z-index:10080;background:rgba(5,8,12,.58);backdrop-filter:blur(2px)}.pc-drawer{position:fixed;top:0;right:0;z-index:10090;width:min(560px,100vw);height:100dvh;overflow-y:auto;border-left:1px solid var(--pc-line);background:var(--pc-panel);box-shadow:-22px 0 55px rgba(0,0,0,.22)}.pc-drawer-head{position:sticky;top:0;z-index:2;display:flex;align-items:flex-start;gap:.7rem;padding:.9rem 1rem;border-bottom:1px solid var(--pc-line);background:var(--pc-panel)}.pc-drawer-head>div{min-width:0;flex:1}.pc-drawer-head h3{margin:.12rem 0 0;font-size:1rem}.pc-drawer-head p{margin:.18rem 0 0;color:var(--pc-muted);font-size:.65rem;line-height:1.4}.pc-close{width:2rem;height:2rem;border:1px solid var(--pc-line);border-radius:.5rem;background:var(--pc-panel2);color:var(--pc-muted);font-size:1.1rem;cursor:pointer}.pc-chip-row{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.45rem}.pc-drawer-section{padding:.85rem 1rem;border-bottom:1px solid var(--pc-line)}.pc-drawer-section h4{margin:0 0 .55rem;color:var(--pc-dim);font-size:.57rem;letter-spacing:.12em}.pc-flag-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.65rem;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--pc-line)}.pc-flag-row:last-child{border-bottom:0}.pc-flag-row p{margin:.16rem 0 0;color:var(--pc-muted);font-size:.61rem;line-height:1.4}.pc-kv{display:flex;justify-content:space-between;gap:1rem;padding:.4rem 0;border-bottom:1px solid var(--pc-line);font-size:.65rem}.pc-kv:last-child{border-bottom:0}.pc-kv span{color:var(--pc-muted)}.pc-kv b{text-align:right}.pc-drawer-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;padding:.78rem 1rem;background:var(--pc-panel2)}.pc-impersonate{margin-left:auto}.pc-drawer-list-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:.55rem;align-items:center;padding:.58rem .8rem;border-bottom:1px solid var(--pc-line)}.pc-drawer-list-row:last-child{border-bottom:0}.pc-slide-in{transition:transform .18s ease}.pc-slide-out{transition:transform .14s ease}[x-cloak]{display:none!important}
@media(max-width:1250px){.pc-credit-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.pc-stat-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.pc-dashboard-grid{grid-template-columns:1fr}.pc-side-stack{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.pc-quick-grid{grid-template-columns:1fr}.pc-queue-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:780px){.pc-stat-grid,.pc-stat-grid-four{grid-template-columns:1fr 1fr}.pc-queue-grid{grid-template-columns:1fr}.pc-side-stack{grid-template-columns:1fr}.pc-view-head{align-items:flex-start;flex-direction:column}.pc-view-actions{width:100%;justify-content:flex-start}.pc-search-box{min-width:0;flex:1 1 14rem}.pc-athlete-table th:nth-child(4),.pc-athlete-table td:nth-child(4),.pc-athlete-table th:nth-child(6),.pc-athlete-table td:nth-child(6){display:none}.pc-filter-row{align-items:flex-start}.pc-filter-label{width:3rem;flex-basis:3rem}}
@media(max-width:520px){.pc-credit-service-grid{grid-template-columns:1fr}.pc-stat-grid,.pc-stat-grid-four{grid-template-columns:1fr}.pc-view-actions{display:grid;grid-template-columns:1fr;width:100%}.pc-search-box,.pc-state-select,.pc-view-actions .pc-btn{width:100%}.pc-filter-row{display:block}.pc-filter-label{display:block;width:auto;margin-bottom:.35rem;padding-top:0}.pc-filter-divider{display:none}.pc-athlete-table th:nth-child(2),.pc-athlete-table td:nth-child(2),.pc-athlete-table th:nth-child(5),.pc-athlete-table td:nth-child(5),.pc-athlete-table th:nth-child(8),.pc-athlete-table td:nth-child(8){display:none}.pc-drawer-list-row{grid-template-columns:minmax(0,1fr) auto}.pc-drawer-list-row .pc-btn{grid-column:2}}


/* v10.115: stronger Superadmin visual hierarchy without changing the existing layout. */
.pc-admin{
    --pc-line:#d8dde4;
    --pc-line2:#c7ced8;
    --pc-muted:#5e6875;
    --pc-dim:#7a8491;
}
.dark .pc-admin{
    --pc-line:#303640;
    --pc-line2:#3a424e;
    --pc-muted:#a1a9b5;
    --pc-dim:#737d8a;
}

.pc-view{gap:1.15rem}
.pc-view-head,.pc-section-title{padding:.08rem .05rem .12rem}
.pc-view-head h2,.pc-section-title h3{font-size:1.22rem;font-weight:850;letter-spacing:-.032em}
.pc-view-head p,.pc-section-title p{margin-top:.24rem;font-size:.76rem;line-height:1.45}
.pc-kicker{font-size:.61rem;font-weight:900;letter-spacing:.15em}
.pc-updated{font-size:.67rem;font-weight:650}

.pc-stat-grid{gap:.85rem}
.pc-stat{
    min-height:9.15rem;
    padding:1rem 1.02rem;
    border-color:var(--pc-line2);
    border-radius:.82rem;
    box-shadow:0 4px 14px rgba(15,23,42,.045);
    transition:border-color .14s ease,box-shadow .14s ease,transform .14s ease;
}
.pc-stat:hover{
    border-color:rgba(255,100,72,.38);
    box-shadow:0 8px 22px rgba(15,23,42,.075);
    transform:translateY(-1px);
}
.dark .pc-stat{box-shadow:0 5px 16px rgba(0,0,0,.18)}
.dark .pc-stat:hover{box-shadow:0 9px 24px rgba(0,0,0,.25)}
.pc-stat-icon{width:2rem;height:2rem;margin-bottom:.78rem;border-radius:.58rem}
.pc-stat small{font-size:.69rem;font-weight:800}
.pc-stat strong{margin-top:.28rem;font-size:1.72rem;font-weight:850}
.pc-stat>span:last-child{margin-top:.64rem;font-size:.67rem;font-weight:520;line-height:1.42}

.pc-dashboard-grid,.pc-side-stack,.pc-quick-grid,.pc-queue-grid{gap:.92rem}
.pc-panel,.pc-queue{
    border-color:var(--pc-line2);
    border-radius:.82rem;
    box-shadow:0 3px 12px rgba(15,23,42,.035);
}
.dark .pc-panel,.dark .pc-queue{box-shadow:0 4px 14px rgba(0,0,0,.16)}
.pc-panel-head{
    min-height:3.85rem;
    padding:.88rem .98rem;
    border-bottom-color:var(--pc-line2);
}
.pc-panel-head h3{font-size:.82rem;font-weight:850;letter-spacing:-.01em}
.pc-panel-head p{margin-top:.16rem;font-size:.65rem;line-height:1.4}

.pc-btn{
    padding:.45rem .68rem;
    border-color:var(--pc-line2);
    border-radius:.56rem;
    font-size:.67rem;
    font-weight:800;
    box-shadow:0 1px 2px rgba(15,23,42,.035);
}
.pc-btn:hover{border-color:#b9c1cc}
.dark .pc-btn:hover{border-color:#4b5563}
.pc-btn.is-small{padding:.35rem .52rem;font-size:.63rem}
.pc-btn.is-danger{border-color:rgba(242,86,79,.38);background:rgba(242,86,79,.08);color:var(--pc-red)}.pc-btn.is-danger:hover{border-color:rgba(242,86,79,.62);background:rgba(242,86,79,.14)}

.pc-table th{
    padding:.64rem .78rem;
    border-bottom-color:var(--pc-line2);
    font-size:.61rem;
    font-weight:900;
    color:var(--pc-muted);
}
.pc-table td{
    padding:.72rem .78rem;
    border-bottom-color:var(--pc-line);
    font-size:.71rem;
}
.pc-click-row:hover{background:color-mix(in srgb,var(--pc-panel2) 84%,var(--pc-coral) 16%)}
.pc-person{gap:.62rem}
.pc-person b{font-size:.72rem;font-weight:800}
.pc-person small{margin-top:.13rem;font-size:.61rem;line-height:1.35}
.pc-avatar{
    width:2rem;height:2rem;flex-basis:2rem;
    border-color:var(--pc-line2);
    border-radius:.6rem;
    font-size:.61rem;
    font-weight:850;
}

.pc-pill,.pc-plan,.pc-state{
    padding:.27rem .46rem;
    border-color:var(--pc-line2);
    font-size:.6rem;
    font-weight:850;
}
.pc-health>span{height:.31rem;background:var(--pc-line2)}
.pc-health small{font-size:.6rem;font-weight:700}
.pc-outreach b,.pc-payment-cell b{font-size:.67rem;font-weight:800}
.pc-outreach small,.pc-payment-cell small{font-size:.58rem}

.pc-sports{padding:.82rem .95rem}
.pc-sport{padding:.39rem 0}
.pc-sport b{font-size:.68rem;font-weight:800}
.pc-sport small{font-size:.62rem;font-weight:700}
.pc-timeline{padding:.78rem .95rem}
.pc-timeline>div{padding:.53rem 0}
.pc-timeline b{font-size:.66rem;font-weight:800}
.pc-timeline small{font-size:.6rem}

.pc-mini-list>a,.pc-mini-list>button,.pc-mini-player-main{padding-top:.65rem;padding-bottom:.65rem}
.pc-mini-list b{font-size:.69rem;font-weight:800}
.pc-mini-list small{font-size:.59rem}
.pc-money{font-size:.73rem;font-weight:850}

.pc-search-box,.pc-state-select{
    min-height:2.4rem;
    border-color:var(--pc-line2);
    border-radius:.56rem;
    font-size:.68rem;
    box-shadow:0 1px 2px rgba(15,23,42,.025);
}
.pc-search-box:focus-within,.pc-state-select:focus{
    border-color:rgba(255,100,72,.55);
    box-shadow:0 0 0 3px rgba(255,100,72,.08);
}
.pc-filter-panel{
    border-color:var(--pc-line2);
    border-radius:.82rem;
    box-shadow:0 2px 8px rgba(15,23,42,.025);
}
.pc-filter-row{padding:.72rem .92rem;border-bottom-color:var(--pc-line)}
.pc-filter-label{font-size:.6rem;font-weight:900;color:var(--pc-muted)}
.pc-filter-chip{
    padding:.37rem .66rem;
    border-color:var(--pc-line2);
    font-size:.65rem;
    font-weight:800;
}
.pc-filter-chip span{font-size:.58rem;font-weight:800;opacity:.76}
.pc-note{border-color:var(--pc-line2);font-size:.65rem;line-height:1.45}

.pc-queue>header{
    grid-template-columns:2.1rem minmax(0,1fr) auto;
    gap:.68rem;
    padding:.82rem .88rem;
    border-bottom-color:var(--pc-line2);
}
.pc-queue>header h3{font-size:.76rem;font-weight:850}
.pc-queue>header p{font-size:.62rem;line-height:1.42}
.pc-queue>header>strong{font-size:1.36rem;font-weight:850}
.pc-queue-mark{width:1.92rem;height:1.92rem;border-radius:.57rem}
.pc-queue-list>div{padding:.56rem .88rem}
.pc-queue-evidence{font-size:.57rem;line-height:1.42}
.pc-queue footer{padding:.62rem .88rem;font-size:.61rem}

.pc-plan-grid{gap:.75rem}
.pc-plan-card{padding:.88rem}
.pc-plan-card>strong{font-size:1.48rem;font-weight:850}
.pc-plan-card>small{font-size:.63rem;line-height:1.4}
.pc-credit-service-grid{gap:.75rem;padding:.88rem}
.pc-credit-service{
    padding:.84rem;
    border-color:var(--pc-line2);
    border-radius:.7rem;
    background:var(--pc-panel);
    box-shadow:0 2px 8px rgba(15,23,42,.03);
}
.dark .pc-credit-service{background:var(--pc-panel2)}
.pc-credit-service small{font-size:.64rem;font-weight:800}
.pc-credit-service strong{font-size:1.38rem;font-weight:850}
.pc-credit-service span{font-size:.61rem}
.pc-credit-cell{font-weight:800}
.pc-credit-cell small{font-size:.56rem}

.pc-plyrlab-hub{gap:1rem}
.pc-plyrlab-head{padding-bottom:.2rem}
.pc-plyrlab-api-badge{display:flex;align-items:center;gap:.45rem;padding:.48rem .7rem;border:1px solid var(--pc-line2);border-radius:999px;font-size:.65rem;font-weight:800}
.pc-plyrlab-api-badge span{width:.5rem;height:.5rem;border-radius:50%;background:var(--pc-amber)}
.pc-plyrlab-api-badge.is-ok{color:var(--pc-green);border-color:rgba(34,192,138,.3);background:rgba(34,192,138,.07)}.pc-plyrlab-api-badge.is-ok span{background:var(--pc-green)}
.pc-plyrlab-api-badge.is-wait{color:var(--pc-amber);background:rgba(240,169,59,.07)}
.pc-plyrlab-tabs{display:flex;gap:.35rem;flex-wrap:wrap;padding:.35rem;border:1px solid var(--pc-line);border-radius:.72rem;background:var(--pc-panel2)}
.pc-plyrlab-tabs button{display:flex;align-items:center;gap:.42rem;padding:.48rem .75rem;border:0;border-radius:.5rem;background:transparent;color:var(--pc-muted);font-size:.66rem;font-weight:850;cursor:pointer}
.pc-plyrlab-tabs button:hover{color:var(--pc-text);background:var(--pc-panel)}
.pc-plyrlab-tabs button.is-active{background:var(--pc-coral);color:#fff;box-shadow:0 4px 12px rgba(255,100,72,.18)}
.pc-plyrlab-tabs button span{padding:.1rem .3rem;border-radius:999px;background:rgba(255,255,255,.13);font-size:.56rem}
.pc-plyrlab-health-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem;padding:.85rem}
.pc-plyrlab-health-grid>div{padding:.85rem;border:1px solid var(--pc-line);border-radius:.68rem;background:var(--pc-panel2)}
.pc-plyrlab-health-grid small{display:block;color:var(--pc-dim);font-size:.56rem;font-weight:900;letter-spacing:.1em}.pc-plyrlab-health-grid b{display:block;margin:.28rem 0;font-size:1.45rem}.pc-plyrlab-health-grid span{color:var(--pc-muted);font-size:.6rem}
.pc-plyrlab-row-actions{display:flex;gap:.35rem;flex-wrap:wrap}
.pc-plyrlab-manual-connect{display:grid;grid-template-columns:minmax(9rem,.9fr) minmax(10rem,1fr) auto;gap:.35rem;margin-top:.45rem;padding:.45rem;border:1px dashed var(--pc-line2);border-radius:.55rem;background:var(--pc-panel2)}
.pc-plyrlab-manual-connect select,.pc-plyrlab-manual-connect input,.pc-plyrlab-field input{min-height:2rem;border:1px solid var(--pc-line2);border-radius:.45rem;background:var(--pc-panel);color:var(--pc-text);padding:.35rem .5rem;font-size:.62rem}
.pc-plyrlab-error{display:block;margin-top:.28rem;max-width:32rem;color:var(--pc-red);font-size:.55rem;line-height:1.35}
.pc-plyrlab-player-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.75rem}
.pc-plyrlab-player-card{padding:.85rem}.pc-plyrlab-player-status{display:flex;gap:.35rem;flex-wrap:wrap;margin:.7rem 0}
.pc-plyrlab-api-panel{max-width:760px}.pc-plyrlab-api-body{display:grid;gap:.75rem;padding:.9rem}
.pc-plyrlab-field{display:grid;gap:.3rem}.pc-plyrlab-field span{color:var(--pc-muted);font-size:.62rem;font-weight:800}
@media(max-width:900px){.pc-plyrlab-health-grid{grid-template-columns:1fr}.pc-plyrlab-manual-connect{grid-template-columns:1fr}.pc-plyrlab-bookings-table{min-width:860px}}


.pc-plyrlab-admin-connect{margin-bottom:.8rem;border-color:rgba(255,100,72,.28)}
.pc-plyrlab-admin-connect-form{display:grid;grid-template-columns:minmax(180px,1fr) minmax(180px,1fr) minmax(190px,1fr) auto;gap:.55rem;align-items:end;padding:.85rem}
.pc-plyrlab-admin-connect-form label{display:grid;gap:.3rem}.pc-plyrlab-admin-connect-form label span{color:var(--pc-muted);font-size:.58rem;font-weight:850;letter-spacing:.04em}
.pc-plyrlab-admin-connect-form select,.pc-plyrlab-admin-connect-form input{min-height:2.3rem;border:1px solid var(--pc-line2);border-radius:.5rem;background:var(--pc-panel);color:var(--pc-text);padding:.35rem .5rem;font-size:.65rem}
@media(max-width:1050px){.pc-plyrlab-admin-connect-form{grid-template-columns:1fr 1fr}.pc-plyrlab-admin-connect-form .pc-btn{width:100%}}
@media(max-width:640px){.pc-plyrlab-admin-connect-form{grid-template-columns:1fr}}

</style>
</div>
</x-filament-widgets::widget>
