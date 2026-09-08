<div
    class="pas-root"
    x-data="{ open: false }"
    x-on:keydown.escape.window="if (open) { open = false; document.body.style.overflow = '' }"
    wire:key="admin-support-messenger-root"
>
    <style>
        .pas-root {
            --pas-orange: #ff6338;
            --pas-orange-soft: rgba(255, 99, 56, .13);
            --pas-bg: #11151b;
            --pas-panel: #151a21;
            --pas-panel-2: #0d1117;
            --pas-line: #29303a;
            --pas-text: #f3f4f6;
            --pas-muted: #7f8998;
            --pas-soft: #1a2028;
            --pas-success: #3fb985;
            --pas-warning: #f2b84b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
        }
        .pas-root * { box-sizing: border-box; }
        .pas-launcher {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 10080;
            width: 54px;
            height: 54px;
            border: 0;
            border-radius: 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #fff;
            background: var(--pas-orange);
            box-shadow: 0 14px 34px rgba(255, 99, 56, .32);
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .pas-launcher:hover { transform: translateY(-2px); box-shadow: 0 18px 38px rgba(255, 99, 56, .38); }
        .pas-launcher svg { width: 23px; height: 23px; }
        .pas-launcher-label {
            position: absolute;
            right: 64px;
            white-space: nowrap;
            padding: 7px 10px;
            border: 1px solid var(--pas-line);
            border-radius: 9px;
            background: #11151b;
            color: #d9dee7;
            font-size: 12px;
            font-weight: 700;
            opacity: 0;
            pointer-events: none;
            transform: translateX(4px);
            transition: opacity .15s ease, transform .15s ease;
        }
        .pas-launcher:hover .pas-launcher-label { opacity: 1; transform: translateX(0); }
        .pas-backdrop {
            position: fixed;
            inset: 0;
            z-index: 10090;
            background: rgba(3, 6, 10, .62);
            backdrop-filter: blur(3px);
        }
        .pas-drawer {
            position: fixed;
            top: 0;
            right: 0;
            z-index: 10100;
            width: min(620px, 100vw);
            height: 100dvh;
            overflow-y: auto;
            overscroll-behavior: contain;
            background: var(--pas-bg);
            color: var(--pas-text);
            border-left: 1px solid var(--pas-line);
            box-shadow: -22px 0 60px rgba(0, 0, 0, .32);
        }
        .pas-slide-enter { transition: transform .20s ease-out; }
        .pas-slide-leave { transition: transform .15s ease-in; }
        .pas-slide-offscreen { transform: translateX(100%); }
        .pas-slide-onscreen { transform: translateX(0); }
        .pas-shell { min-height: 100%; padding: 24px 24px 34px; }
        .pas-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; padding-bottom:20px; border-bottom:1px solid var(--pas-line); }
        .pas-eyebrow, .pas-section-label { color: #7e8998; font-size: 11px; line-height: 1; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        .pas-head h2 { margin: 7px 0 5px; color: #fff; font-size: 24px; line-height: 1.1; font-weight: 800; letter-spacing: -.025em; }
        .pas-head p { margin: 0; color: var(--pas-muted); font-size: 13px; line-height: 1.45; }
        .pas-close { flex:0 0 auto; width:38px; height:38px; border:1px solid var(--pas-line); border-radius:12px; background:var(--pas-panel); color:#aab2bf; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
        .pas-close:hover { color:#fff; border-color:#3a4451; }
        .pas-section { padding-top: 21px; }
        .pas-section-label { margin-bottom: 10px; }
        .pas-target {
            display:flex;
            align-items:center;
            gap:12px;
            padding:12px;
            border:1px solid var(--pas-line);
            border-radius:14px;
            background:var(--pas-panel);
        }
        .pas-avatar { width:42px; height:42px; flex:0 0 auto; border-radius:13px; display:flex; align-items:center; justify-content:center; background:var(--pas-orange-soft); color:var(--pas-orange); font-weight:800; font-size:13px; }
        .pas-target-main { min-width:0; flex:1; }
        .pas-target-name { color:#f7f8fa; font-size:14px; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .pas-target-meta { margin-top:2px; color:var(--pas-muted); font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .pas-link-btn { border:0; padding:7px 8px; background:transparent; color:var(--pas-orange); font-size:12px; font-weight:750; cursor:pointer; }
        .pas-search { width:100%; border:1px solid var(--pas-line); border-radius:12px; background:#0d1117; color:#eef2f7; padding:11px 12px; outline:none; font-size:13px; }
        .pas-search:focus, .pas-subject:focus, .pas-message:focus { border-color:var(--pas-orange); box-shadow:0 0 0 3px rgba(255,99,56,.09); }
        .pas-user-list { margin-top:8px; border:1px solid var(--pas-line); border-radius:13px; overflow:hidden; background:#0d1117; max-height:255px; overflow-y:auto; }
        .pas-user-row { width:100%; border:0; border-bottom:1px solid rgba(41,48,58,.72); background:transparent; color:inherit; display:flex; align-items:center; gap:11px; padding:10px 12px; text-align:left; cursor:pointer; }
        .pas-user-row:last-child { border-bottom:0; }
        .pas-user-row:hover { background:rgba(255,255,255,.035); }
        .pas-mini-avatar { width:34px; height:34px; border-radius:10px; background:#191f27; color:#aeb6c3; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; }
        .pas-user-copy { flex:1; min-width:0; }
        .pas-user-name { font-size:13px; font-weight:750; color:#e9edf3; }
        .pas-user-email { margin-top:2px; color:var(--pas-muted); font-size:11px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .pas-user-pick { color:var(--pas-orange); font-size:12px; font-weight:750; }
        .pas-empty { padding:18px; text-align:center; color:var(--pas-muted); font-size:12px; }
        .pas-concerns { display:grid; gap:8px; }
        .pas-concern {
            width:100%;
            display:flex;
            align-items:flex-start;
            gap:12px;
            padding:12px 14px;
            border:1px solid var(--pas-line);
            border-radius:13px;
            background:var(--pas-panel);
            color:inherit;
            text-align:left;
            cursor:pointer;
            transition:border-color .15s ease, background .15s ease;
        }
        .pas-concern:hover { border-color:#3b4552; }
        .pas-concern.is-selected { border-color:var(--pas-orange); background:rgba(255,99,56,.09); }
        .pas-radio { width:18px; height:18px; margin-top:1px; flex:0 0 auto; border:1.5px solid #3b4552; border-radius:999px; display:flex; align-items:center; justify-content:center; }
        .pas-concern.is-selected .pas-radio { border-color:var(--pas-orange); }
        .pas-concern.is-selected .pas-radio::after { content:""; width:8px; height:8px; border-radius:999px; background:var(--pas-orange); }
        .pas-concern-title { color:#f0f2f5; font-size:13px; font-weight:800; line-height:1.25; }
        .pas-concern-hint { margin-top:3px; color:#737e8d; font-size:11.5px; line-height:1.35; }
        .pas-channels { display:grid; grid-template-columns:repeat(3,1fr); gap:7px; }
        .pas-channel { border:1px solid var(--pas-line); border-radius:11px; background:var(--pas-panel); color:#8d97a6; min-height:40px; font-size:12px; font-weight:800; cursor:pointer; }
        .pas-channel:hover { color:#e4e7ec; }
        .pas-channel.is-selected { border-color:var(--pas-orange); color:var(--pas-orange); background:rgba(255,99,56,.10); }
        .pas-field-label { display:block; margin:0 0 7px; color:#9aa4b2; font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .pas-subject, .pas-message { width:100%; border:1px solid var(--pas-line); border-radius:12px; background:var(--pas-panel-2); color:#eef1f5; outline:none; padding:11px 12px; font-size:13px; line-height:1.5; }
        .pas-message { min-height:170px; resize:vertical; }
        .pas-field-row { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:7px; }
        .pas-reset { border:0; background:transparent; color:var(--pas-orange); font-size:11px; font-weight:800; cursor:pointer; padding:0; }
        .pas-variables { display:flex; flex-wrap:wrap; gap:6px; }
        .pas-variable { border:1px solid #323a45; border-radius:999px; padding:6px 8px; background:#151a21; color:#9da7b5; font-size:10.5px; font-weight:700; cursor:pointer; }
        .pas-variable:hover { border-color:var(--pas-orange); color:var(--pas-orange); }
        .pas-preview { padding:15px; border:1px solid var(--pas-line); border-radius:13px; background:#090d12; }
        .pas-preview-subject { color:#eef2f6; font-size:12px; font-weight:800; margin-bottom:10px; }
        .pas-preview-body { color:#aeb6c3; font-size:13px; line-height:1.62; overflow-wrap:anywhere; }
        .pas-recipient { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
        .pas-recipient-pill { display:inline-flex; align-items:center; gap:6px; border:1px solid var(--pas-line); border-radius:999px; padding:5px 8px; color:#7f8998; font-size:10.5px; }
        .pas-recipient-pill.is-ready { color:#a9d9c4; border-color:rgba(63,185,133,.28); background:rgba(63,185,133,.06); }
        .pas-notice { margin-top:15px; padding:11px 12px; border-radius:11px; font-size:12px; line-height:1.45; border:1px solid; }
        .pas-notice.success { color:#b8ead4; background:rgba(63,185,133,.09); border-color:rgba(63,185,133,.28); }
        .pas-notice.warning { color:#f7dba1; background:rgba(242,184,75,.08); border-color:rgba(242,184,75,.28); }
        .pas-notice.error { color:#ffb9a8; background:rgba(255,99,56,.08); border-color:rgba(255,99,56,.34); }
        .pas-send { width:100%; margin-top:16px; min-height:46px; border:0; border-radius:12px; display:flex; align-items:center; justify-content:center; gap:8px; background:var(--pas-orange); color:#fff; font-size:13px; font-weight:850; cursor:pointer; box-shadow:0 10px 24px rgba(255,99,56,.18); }
        .pas-send:disabled { opacity:.45; cursor:not-allowed; box-shadow:none; }
        .pas-spinner { width:14px; height:14px; border:2px solid rgba(255,255,255,.38); border-top-color:#fff; border-radius:999px; animation:pas-spin .75s linear infinite; }
        @keyframes pas-spin { to { transform:rotate(360deg); } }
        .pas-history { display:grid; gap:7px; }
        .pas-history-row { padding:10px 11px; border:1px solid var(--pas-line); border-radius:11px; background:#12171e; }
        .pas-history-top { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .pas-history-title { color:#d9dee6; font-size:11.5px; font-weight:800; }
        .pas-history-time { color:#697381; font-size:10px; }
        .pas-history-meta { margin-top:4px; color:#778291; font-size:10.5px; }
        .pas-live-count { margin-top:6px; text-align:right; color:#626d7c; font-size:10px; }
        .pas-note { color:#687383; font-size:10.5px; line-height:1.45; margin-top:8px; }
        @media (max-width: 700px) {
            .pas-launcher { right:14px; bottom:14px; }
            .pas-shell { padding:19px 16px 28px; }
            .pas-drawer { width:100vw; }
        }
    </style>

    <button
        type="button"
        class="pas-launcher"
        x-on:click="open = true; document.body.style.overflow = 'hidden'"
        aria-label="Open admin support messenger"
    >
        <span class="pas-launcher-label">Message a user</span>
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5 5.75A2.75 2.75 0 0 1 7.75 3h8.5A2.75 2.75 0 0 1 19 5.75v6.5A2.75 2.75 0 0 1 16.25 15H11l-4.8 4v-4.12A2.75 2.75 0 0 1 5 12.25v-6.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="M8.5 8.8h7M8.5 11.4h4.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
    </button>

    <div class="pas-backdrop" x-cloak x-show="open" x-transition.opacity x-on:click="open = false; document.body.style.overflow = ''"></div>

    <aside
        class="pas-drawer"
        x-cloak
        x-show="open"
        x-transition:enter="pas-slide-enter"
        x-transition:enter-start="pas-slide-offscreen"
        x-transition:enter-end="pas-slide-onscreen"
        x-transition:leave="pas-slide-leave"
        x-transition:leave-start="pas-slide-onscreen"
        x-transition:leave-end="pas-slide-offscreen"
        aria-label="Admin support messenger"
    >
        <div class="pas-shell">
            <div class="pas-head">
                <div>
                    <div class="pas-eyebrow">Admin Support</div>
                    <h2>Message a user</h2>
                    <p>Choose the concern, personalize the message, then send it by email, SMS, or both.</p>
                </div>
                <button type="button" class="pas-close" x-on:click="open = false; document.body.style.overflow = ''" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="m7 7 10 10M17 7 7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            <section class="pas-section">
                <div class="pas-section-label">Who this is for</div>

                @if ($this->targetUser)
                    @php
                        $target = $this->targetUser;
                        $targetName = trim(($target->first_name ?? '') . ' ' . ($target->last_name ?? '')) ?: ($target->email ?? 'User');
                        $initials = collect(explode(' ', $targetName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                        $roles = method_exists($target, 'getRoleNames') ? $target->getRoleNames()->implode(', ') : '';
                    @endphp
                    <div class="pas-target">
                        <div class="pas-avatar">{{ $initials ?: 'U' }}</div>
                        <div class="pas-target-main">
                            <div class="pas-target-name">{{ $targetName }}</div>
                            <div class="pas-target-meta">{{ $target->email ?: $target->personal_email ?: 'No email' }}{{ $roles ? ' · ' . $roles : '' }}</div>
                        </div>
                        <button type="button" class="pas-link-btn" wire:click="clearTarget">Change</button>
                    </div>
                @else
                    <input
                        type="search"
                        class="pas-search"
                        placeholder="Search name, email, or phone…"
                        wire:model.live.debounce.300ms="userSearch"
                        autocomplete="off"
                    >
                    <div class="pas-user-list">
                        @forelse ($this->targetUsers as $userOption)
                            @php
                                $optionName = trim(($userOption->first_name ?? '') . ' ' . ($userOption->last_name ?? '')) ?: ($userOption->email ?? 'User');
                                $optionInitials = collect(explode(' ', $optionName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                            @endphp
                            <button type="button" class="pas-user-row" wire:key="pas-user-{{ $userOption->id }}" wire:click="selectTargetUser({{ $userOption->id }})">
                                <span class="pas-mini-avatar">{{ $optionInitials ?: 'U' }}</span>
                                <span class="pas-user-copy">
                                    <span class="pas-user-name">{{ $optionName }}</span>
                                    <span class="pas-user-email">{{ $userOption->email ?: $userOption->personal_email ?: $userOption->phone ?: 'No contact info' }}</span>
                                </span>
                                <span class="pas-user-pick">Select</span>
                            </button>
                        @empty
                            <div class="pas-empty">No users found.</div>
                        @endforelse
                    </div>
                @endif
            </section>

            <section class="pas-section">
                <div class="pas-section-label">What this is about</div>
                <div class="pas-concerns">
                    @foreach ($concerns as $key => $item)
                        <button
                            type="button"
                            wire:key="pas-concern-{{ $key }}"
                            wire:click="selectConcern('{{ $key }}')"
                            class="pas-concern {{ $concern === $key ? 'is-selected' : '' }}"
                        >
                            <span class="pas-radio"></span>
                            <span>
                                <span class="pas-concern-title">{{ $item['label'] ?? \Illuminate\Support\Str::headline($key) }}</span>
                                <span class="pas-concern-hint">{{ $item['hint'] ?? '' }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="pas-section">
                <div class="pas-section-label">How it goes out</div>
                <div class="pas-channels">
                    <button type="button" class="pas-channel {{ $channel === 'email' ? 'is-selected' : '' }}" wire:click="setChannel('email')">Email</button>
                    <button type="button" class="pas-channel {{ $channel === 'sms' ? 'is-selected' : '' }}" wire:click="setChannel('sms')">SMS</button>
                    <button type="button" class="pas-channel {{ $channel === 'email_sms' ? 'is-selected' : '' }}" wire:click="setChannel('email_sms')">Email + SMS</button>
                </div>
            </section>

            <section class="pas-section">
                <div class="pas-field-row">
                    <label class="pas-field-label" style="margin:0">Subject</label>
                    <button type="button" class="pas-reset" wire:click="resetTemplate">Reset prebuilt message</button>
                </div>
                <input type="text" class="pas-subject" wire:model.live.debounce.450ms="subject" maxlength="255">
            </section>

            <section class="pas-section" style="padding-top:13px">
                <label class="pas-field-label">Message</label>
                <textarea class="pas-message" wire:model.live.debounce.450ms="message" maxlength="5000"></textarea>
                <div class="pas-live-count">{{ mb_strlen($message) }} / 5,000</div>
            </section>

            <section class="pas-section" style="padding-top:14px">
                <div class="pas-section-label">Add a variable</div>
                <div class="pas-variables">
                    @foreach ($variableDefinitions as $key => $label)
                        <button type="button" class="pas-variable" title="{{ $label }}" wire:click="appendVariable('{{ $key }}')">
                            {{ '{' . '{' . $key . '}' . '}' }}
                        </button>
                    @endforeach
                </div>
                <div class="pas-note">Variables are resolved from the selected user when the message is previewed and again at send time.</div>
            </section>

            <section class="pas-section">
                <div class="pas-section-label">Preview</div>
                <div class="pas-preview">
                    <div class="pas-preview-subject">{{ $this->renderedSubject ?: 'No subject' }}</div>
                    <div class="pas-preview-body">{!! nl2br(e($this->renderedMessage)) !!}</div>

                    @if ($this->targetUser)
                        <div class="pas-recipient">
                            <span class="pas-recipient-pill {{ ($this->targetUser->email || $this->targetUser->personal_email) ? 'is-ready' : '' }}">
                                Email · {{ $this->targetUser->email ?: $this->targetUser->personal_email ?: 'missing' }}
                            </span>
                            <span class="pas-recipient-pill {{ $this->targetUser->phone ? 'is-ready' : '' }}">
                                SMS · {{ $this->targetUser->phone ?: 'missing' }}
                            </span>
                        </div>
                    @endif
                </div>
            </section>

            @if ($notice)
                <div class="pas-notice {{ $noticeType }}">{{ $notice }}</div>
            @endif

            <button
                type="button"
                class="pas-send"
                wire:click="send"
                wire:loading.attr="disabled"
                wire:target="send"
                @disabled(! $this->targetUser)
            >
                <span wire:loading.remove wire:target="send">Send support message</span>
                <span wire:loading.flex wire:target="send" style="align-items:center;gap:8px"><span class="pas-spinner"></span> Sending…</span>
            </button>

            @if ($this->targetUser && $this->history->isNotEmpty())
                <section class="pas-section">
                    <div class="pas-section-label">Recent messages to this user</div>
                    <div class="pas-history">
                        @foreach ($this->history as $historyItem)
                            <div class="pas-history-row" wire:key="pas-history-{{ $historyItem->id }}">
                                <div class="pas-history-top">
                                    <span class="pas-history-title">{{ $concerns[$historyItem->concern]['label'] ?? \Illuminate\Support\Str::headline($historyItem->concern) }}</span>
                                    <span class="pas-history-time">{{ optional($historyItem->sent_at ?: $historyItem->created_at)->format('M j, g:i A') }}</span>
                                </div>
                                <div class="pas-history-meta">
                                    {{ strtoupper(str_replace('_', ' + ', $historyItem->channel)) }} · Email {{ $historyItem->email_status ?: '—' }} · SMS {{ $historyItem->sms_status ?: '—' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </aside>
</div>
