<div
    class="pas-root"
    x-data="{ open: false }"
    x-on:keydown.escape.window="if (open) { open = false; document.body.style.overflow = '' }"
    wire:key="admin-support-messenger-root-v1061"
>
    <style>
        .pas-root {
            --pas-orange:#ff6338;
            --pas-orange-soft:rgba(255,99,56,.12);
            --pas-bg:#0d1117;
            --pas-panel:#13181f;
            --pas-panel-2:#171d25;
            --pas-line:#29313c;
            --pas-text:#f4f5f7;
            --pas-muted:#8993a2;
            --pas-green:#41bd88;
            --pas-yellow:#f2b84b;
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif;
        }
        .pas-root *{box-sizing:border-box}
        .pas-launcher{position:fixed;right:24px;bottom:24px;z-index:10080;width:56px;height:56px;border:0;border-radius:18px;background:var(--pas-orange);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 15px 38px rgba(255,99,56,.32);transition:.18s ease}
        .pas-launcher:hover{transform:translateY(-2px);box-shadow:0 18px 42px rgba(255,99,56,.4)}
        .pas-launcher svg{width:24px;height:24px}
        .pas-launcher-label{position:absolute;right:66px;white-space:nowrap;padding:7px 10px;border:1px solid var(--pas-line);border-radius:9px;background:#11161d;color:#dce1e8;font-size:12px;font-weight:750;opacity:0;transform:translateX(5px);pointer-events:none;transition:.15s ease}
        .pas-launcher:hover .pas-launcher-label{opacity:1;transform:translateX(0)}
        .pas-backdrop{position:fixed;inset:0;z-index:10090;background:rgba(2,5,9,.65);backdrop-filter:blur(3px)}
        .pas-drawer{position:fixed;top:0;right:0;z-index:10100;width:min(690px,100vw);height:100dvh;background:var(--pas-bg);color:var(--pas-text);border-left:1px solid #252c35;box-shadow:-24px 0 60px rgba(0,0,0,.38);overflow:hidden}
        .pas-shell{height:100%;overflow-y:auto;padding-bottom:30px;scrollbar-width:thin;scrollbar-color:#353d48 transparent}
        .pas-head{position:sticky;top:0;z-index:4;display:flex;gap:18px;justify-content:space-between;align-items:flex-start;padding:24px 26px 20px;background:rgba(13,17,23,.96);backdrop-filter:blur(12px);border-bottom:1px solid var(--pas-line)}
        .pas-eyebrow,.pas-section-label{font:800 10.5px/1.2 "Courier New",monospace;letter-spacing:.15em;text-transform:uppercase;color:#727c8b}
        .pas-head h2{margin:5px 0 5px;font-size:23px;line-height:1.15;font-weight:850}
        .pas-head p{margin:0;max-width:500px;font-size:13px;line-height:1.55;color:var(--pas-muted)}
        .pas-close{width:38px;height:38px;border:1px solid var(--pas-line);border-radius:12px;background:#151a21;color:#aab2be;display:flex;align-items:center;justify-content:center;cursor:pointer;flex:0 0 auto}
        .pas-one-way{display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:6px 9px;border:1px solid #323944;border-radius:999px;background:#11161d;color:#a4adba;font-size:11px;font-weight:750}
        .pas-one-way i{width:7px;height:7px;border-radius:50%;background:var(--pas-orange);display:inline-block}
        .pas-section{padding:20px 26px;border-bottom:1px solid var(--pas-line)}
        .pas-section-label{margin-bottom:10px}
        .pas-audience-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
        .pas-audience{border:1px solid var(--pas-line);border-radius:12px;background:#12171e;color:var(--pas-text);padding:13px 12px;text-align:left;cursor:pointer;transition:.15s ease}
        .pas-audience:hover{border-color:#3a4450;background:#161c24}
        .pas-audience.is-selected{border-color:var(--pas-orange);background:var(--pas-orange-soft);box-shadow:inset 0 0 0 1px rgba(255,99,56,.08)}
        .pas-audience-title{display:block;font-size:13px;font-weight:800}
        .pas-audience-sub{display:block;margin-top:3px;font-size:11.5px;line-height:1.35;color:var(--pas-muted)}
        .pas-audience-count{display:inline-flex;margin-top:8px;padding:4px 7px;border-radius:999px;background:#1d242d;color:#cdd3dc;font-size:10.5px;font-weight:800}
        .pas-audience.is-selected .pas-audience-count{background:rgba(255,99,56,.16);color:#ff8a6d}
        .pas-summary{margin-top:11px;padding:12px 13px;border:1px solid var(--pas-line);border-radius:11px;background:#11161d;font-size:12px;line-height:1.55;color:#a5aebb}
        .pas-summary strong{color:#f1f3f6}
        .pas-readiness{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}
        .pas-ready-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 8px;border:1px solid #2d3540;border-radius:999px;background:#151b22;color:#929dab;font-size:11px;font-weight:700}
        .pas-ready-pill.is-ready{border-color:rgba(65,189,136,.3);background:rgba(65,189,136,.08);color:#75d9ad}
        .pas-search{width:100%;height:42px;border:1px solid var(--pas-line);border-radius:10px;background:#0b0f14;color:#f4f5f7;padding:0 12px;outline:none;font-size:13px}
        .pas-search:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.09)}
        .pas-user-list{display:grid;gap:7px;margin-top:9px;max-height:300px;overflow-y:auto;padding-right:2px}
        .pas-user-row,.pas-target{width:100%;display:flex;align-items:center;gap:10px;padding:10px 11px;border:1px solid var(--pas-line);border-radius:11px;background:#12171e;color:var(--pas-text)}
        .pas-user-row{cursor:pointer;text-align:left}
        .pas-user-row:hover,.pas-user-row.is-selected{border-color:#3a4450;background:#171d25}
        .pas-user-row.is-selected{border-color:rgba(255,99,56,.72);background:var(--pas-orange-soft)}
        .pas-mini-avatar,.pas-avatar{display:flex;align-items:center;justify-content:center;border-radius:10px;background:#202730;color:#d7dce3;font-weight:850;flex:0 0 auto}
        .pas-mini-avatar{width:34px;height:34px;font-size:11px}.pas-avatar{width:42px;height:42px;font-size:13px}
        .pas-user-copy,.pas-target-main{min-width:0;flex:1}
        .pas-user-name,.pas-target-name{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .pas-user-email,.pas-target-meta{display:block;margin-top:3px;font-size:11px;color:var(--pas-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .pas-user-pick,.pas-link-btn{flex:0 0 auto;border:0;background:transparent;color:#ff7a57;font-size:11px;font-weight:800;cursor:pointer}
        .pas-custom-selected{display:flex;flex-wrap:wrap;gap:6px;margin:9px 0 0}
        .pas-custom-chip{display:inline-flex;align-items:center;gap:7px;padding:6px 8px;border:1px solid #313945;border-radius:999px;background:#171d25;color:#d7dce3;font-size:11px}
        .pas-custom-chip button{border:0;background:transparent;color:#7f8998;cursor:pointer;padding:0;font-size:13px;line-height:1}
        .pas-custom-actions{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:9px}
        .pas-small-link{border:0;background:transparent;padding:0;color:#ff7d5b;font-size:11px;font-weight:800;cursor:pointer}
        .pas-empty{padding:15px;border:1px dashed #303844;border-radius:10px;color:#707a89;text-align:center;font-size:12px}
        .pas-concerns{display:grid;gap:7px}
        .pas-concern{width:100%;display:grid;grid-template-columns:20px 1fr;gap:10px;padding:11px 12px;border:1px solid var(--pas-line);border-radius:11px;background:#12171e;color:var(--pas-text);text-align:left;cursor:pointer}
        .pas-concern.is-selected{border-color:var(--pas-orange);background:rgba(255,99,56,.09)}
        .pas-radio{width:17px;height:17px;border:1px solid #3c4652;border-radius:50%;position:relative;margin-top:1px}
        .pas-concern.is-selected .pas-radio{border-color:var(--pas-orange)}
        .pas-concern.is-selected .pas-radio:after{content:"";position:absolute;inset:4px;border-radius:50%;background:var(--pas-orange)}
        .pas-concern-title{display:block;font-size:12.5px;font-weight:800}.pas-concern-hint{display:block;margin-top:2px;color:#798392;font-size:11.5px;line-height:1.4}
        .pas-channels{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
        .pas-channel{height:38px;border:1px solid var(--pas-line);border-radius:10px;background:#12171e;color:#8e98a7;font-size:12px;font-weight:800;cursor:pointer}
        .pas-channel.is-selected{border-color:var(--pas-orange);background:rgba(255,99,56,.11);color:#ff7653}
        .pas-field-row{display:flex;align-items:center;justify-content:space-between;gap:12px}.pas-field-label{display:block;margin-bottom:7px;font-size:11px;font-weight:800;color:#aeb6c1}
        .pas-reset{border:0;background:transparent;color:#ff7857;font-size:10.5px;font-weight:800;cursor:pointer}
        .pas-subject,.pas-message{width:100%;border:1px solid var(--pas-line);border-radius:10px;background:#0a0e13;color:#eef1f5;padding:11px 12px;outline:none;font-size:13px}
        .pas-subject:focus,.pas-message:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.08)}
        .pas-message{min-height:170px;resize:vertical;line-height:1.55}
        .pas-live-count{text-align:right;margin-top:5px;color:#626d7c;font-size:10.5px}
        .pas-variables{display:flex;flex-wrap:wrap;gap:6px}.pas-variable{border:1px solid #303844;border-radius:7px;background:#151b22;color:#9ca6b4;padding:5px 7px;font:700 10px/1.2 "Courier New",monospace;cursor:pointer}.pas-variable:hover{border-color:#ff6338;color:#ff8464}
        .pas-note{margin-top:8px;color:#697483;font-size:10.5px;line-height:1.45}
        .pas-preview{border:1px solid var(--pas-line);border-radius:12px;background:#090d12;overflow:hidden}.pas-preview-head{padding:11px 13px;border-bottom:1px solid #222a33;background:#10151b}.pas-preview-subject{font-size:12px;font-weight:850;color:#e8ebef}.pas-preview-person{margin-top:4px;font-size:10.5px;color:#717c8a}.pas-preview-body{padding:15px 13px;white-space:normal;font-size:12px;line-height:1.65;color:#b8c0cb}.pas-preview-actions{display:flex;flex-wrap:wrap;gap:7px;padding:0 13px 13px}.pas-preview-button{padding:7px 9px;border-radius:7px;background:#ff6338;color:#11161d;font-size:10.5px;font-weight:850}.pas-preview-button.secondary{background:#edf0f4}
        .pas-recipient{display:flex;flex-wrap:wrap;gap:6px;padding:0 13px 13px}.pas-recipient-pill{padding:5px 7px;border:1px solid #313945;border-radius:999px;color:#788392;font-size:10.5px}.pas-recipient-pill.is-ready{border-color:rgba(65,189,136,.27);background:rgba(65,189,136,.07);color:#70d4a9}
        .pas-confirm{margin:16px 26px 0;padding:12px 13px;border:1px solid #37311f;border-radius:11px;background:rgba(242,184,75,.07);display:flex;gap:9px;align-items:flex-start;color:#c6b47a;font-size:11.5px;line-height:1.45}.pas-confirm input{margin-top:2px;accent-color:#ff6338}
        .pas-notice{margin:15px 26px 0;padding:11px 13px;border-radius:10px;border:1px solid #33404a;background:#151b22;color:#bcc4cf;font-size:11.5px;line-height:1.45}.pas-notice.success{border-color:rgba(65,189,136,.34);background:rgba(65,189,136,.08);color:#7eddb4}.pas-notice.warning{border-color:rgba(242,184,75,.35);background:rgba(242,184,75,.08);color:#e4c46a}.pas-notice.error{border-color:rgba(248,113,113,.35);background:rgba(248,113,113,.08);color:#f3a0a0}
        .pas-send{margin:15px 26px 0;width:calc(100% - 52px);min-height:44px;border:0;border-radius:11px;background:var(--pas-orange);color:#fff;font-size:12.5px;font-weight:850;cursor:pointer;display:flex;align-items:center;justify-content:center}.pas-send:disabled{opacity:.42;cursor:not-allowed}.pas-spinner{width:14px;height:14px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;display:inline-block;animation:pas-spin .75s linear infinite}@keyframes pas-spin{to{transform:rotate(360deg)}}
        .pas-history{display:grid;gap:7px}.pas-history-row{padding:10px 11px;border:1px solid var(--pas-line);border-radius:10px;background:#12171e}.pas-history-top{display:flex;justify-content:space-between;gap:10px}.pas-history-title{font-size:11.5px;font-weight:800}.pas-history-time,.pas-history-meta{font-size:10.5px;color:#717c8a}.pas-history-meta{margin-top:4px}
        @media(max-width:640px){.pas-launcher{right:16px;bottom:16px}.pas-drawer{width:100vw}.pas-head,.pas-section{padding-left:18px;padding-right:18px}.pas-audience-grid{grid-template-columns:1fr}.pas-confirm,.pas-notice{margin-left:18px;margin-right:18px}.pas-send{margin-left:18px;margin-right:18px;width:calc(100% - 36px)}}
    </style>

    <button type="button" class="pas-launcher" x-on:click="open = true; document.body.style.overflow = 'hidden'" aria-label="Open Admin Support Messenger">
        <span class="pas-launcher-label">Message users</span>
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.5 5.5A2.5 2.5 0 0 1 7 3h10a2.5 2.5 0 0 1 2.5 2.5v8A2.5 2.5 0 0 1 17 16H9l-4.5 4v-14.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 8h8M8 11.5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>

    <div class="pas-backdrop" x-cloak x-show="open" x-transition.opacity x-on:click="open = false; document.body.style.overflow = ''"></div>

    <aside class="pas-drawer" x-cloak x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
        <div class="pas-shell">
            <div class="pas-head">
                <div>
                    <div class="pas-eyebrow">Admin Support</div>
                    <h2>Message users</h2>
                    <p>Choose who receives it, pick the concern, personalize the message, and send. Email delivery uses <strong style="color:#dfe3e8">personal_email only</strong>.</p>
                    <span class="pas-one-way"><i></i> One-way message · replies go through Support</span>
                </div>
                <button type="button" class="pas-close" x-on:click="open = false; document.body.style.overflow = ''" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="m7 7 10 10M17 7 7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            <section class="pas-section">
                <div class="pas-section-label">Who should receive this</div>
                <div class="pas-audience-grid">
                    <button type="button" wire:click="selectAudienceMode('all')" class="pas-audience {{ $audienceMode === 'all' ? 'is-selected' : '' }}">
                        <span class="pas-audience-title">All Users</span>
                        <span class="pas-audience-sub">Every active non-admin account.</span>
                        <span class="pas-audience-count">{{ $audienceMode === 'all' ? $this->audienceCount : 'All' }}</span>
                    </button>
                    <button type="button" wire:click="selectAudienceMode('custom')" class="pas-audience {{ $audienceMode === 'custom' ? 'is-selected' : '' }}">
                        <span class="pas-audience-title">Custom List</span>
                        <span class="pas-audience-sub">Choose multiple specific users.</span>
                        <span class="pas-audience-count">{{ count($customUserIds) }} selected</span>
                    </button>
                    <button type="button" wire:click="selectAudienceMode('individual')" class="pas-audience {{ $audienceMode === 'individual' ? 'is-selected' : '' }}">
                        <span class="pas-audience-title">Individual User</span>
                        <span class="pas-audience-sub">Send to one selected user.</span>
                        <span class="pas-audience-count">{{ $this->targetUser ? '1 selected' : 'Choose user' }}</span>
                    </button>
                </div>

                @if ($audienceMode === 'all')
                    <div class="pas-summary">
                        <strong>{{ $this->audienceCount }} users</strong> are in this audience. Email will be attempted only to each user's <code>personal_email</code>; no account/recruiting email fallback is used.
                        <div class="pas-readiness">
                            <span class="pas-ready-pill {{ $this->personalEmailCount > 0 ? 'is-ready' : '' }}">{{ $this->personalEmailCount }} with personal email</span>
                            <span class="pas-ready-pill {{ $this->phoneCount > 0 ? 'is-ready' : '' }}">{{ $this->phoneCount }} with phone</span>
                        </div>
                    </div>
                @elseif ($audienceMode === 'custom')
                    @if ($this->selectedCustomUsers->isNotEmpty())
                        <div class="pas-custom-selected">
                            @foreach ($this->selectedCustomUsers as $selectedUser)
                                @php $selectedName = trim(($selectedUser->first_name ?? '') . ' ' . ($selectedUser->last_name ?? '')) ?: 'User #' . $selectedUser->id; @endphp
                                <span class="pas-custom-chip" wire:key="pas-selected-{{ $selectedUser->id }}">
                                    {{ $selectedName }}
                                    <button type="button" wire:click="toggleCustomUser({{ $selectedUser->id }})" aria-label="Remove {{ $selectedName }}">×</button>
                                </span>
                            @endforeach
                        </div>
                        <div class="pas-custom-actions"><span style="font-size:10.5px;color:#737e8d">{{ $this->audienceCount }} recipient(s)</span><button type="button" class="pas-small-link" wire:click="clearCustomUsers">Clear list</button></div>
                    @endif

                    <input type="search" class="pas-search" placeholder="Search name, personal email, account email, or phone…" wire:model.live.debounce.300ms="userSearch" autocomplete="off" style="margin-top:10px">
                    @php $selectedIds = array_map('intval', $customUserIds); @endphp
                    <div class="pas-user-list">
                        @forelse ($this->targetUsers as $userOption)
                            @php
                                $optionName = trim(($userOption->first_name ?? '') . ' ' . ($userOption->last_name ?? '')) ?: 'User #' . $userOption->id;
                                $optionInitials = collect(explode(' ', $optionName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                                $isSelected = in_array((int) $userOption->id, $selectedIds, true);
                            @endphp
                            <button type="button" class="pas-user-row {{ $isSelected ? 'is-selected' : '' }}" wire:key="pas-custom-option-{{ $userOption->id }}" wire:click="toggleCustomUser({{ $userOption->id }})">
                                <span class="pas-mini-avatar">{{ $optionInitials ?: 'U' }}</span>
                                <span class="pas-user-copy">
                                    <span class="pas-user-name">{{ $optionName }}</span>
                                    <span class="pas-user-email">Personal: {{ $userOption->personal_email ?: 'missing' }}</span>
                                </span>
                                <span class="pas-user-pick">{{ $isSelected ? 'Remove' : 'Add' }}</span>
                            </button>
                        @empty
                            <div class="pas-empty">No users found.</div>
                        @endforelse
                    </div>
                @else
                    @if ($this->targetUser)
                        @php
                            $target = $this->targetUser;
                            $targetName = trim(($target->first_name ?? '') . ' ' . ($target->last_name ?? '')) ?: 'User #' . $target->id;
                            $initials = collect(explode(' ', $targetName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                            $roles = method_exists($target, 'getRoleNames') ? $target->getRoleNames()->implode(', ') : '';
                        @endphp
                        <div class="pas-target">
                            <div class="pas-avatar">{{ $initials ?: 'U' }}</div>
                            <div class="pas-target-main">
                                <div class="pas-target-name">{{ $targetName }}</div>
                                <div class="pas-target-meta">Personal: {{ $target->personal_email ?: 'missing' }}{{ $roles ? ' · ' . $roles : '' }}</div>
                            </div>
                            <button type="button" class="pas-link-btn" wire:click="clearTarget">Change</button>
                        </div>
                    @else
                        <input type="search" class="pas-search" placeholder="Search name, personal email, account email, or phone…" wire:model.live.debounce.300ms="userSearch" autocomplete="off">
                        <div class="pas-user-list">
                            @forelse ($this->targetUsers as $userOption)
                                @php
                                    $optionName = trim(($userOption->first_name ?? '') . ' ' . ($userOption->last_name ?? '')) ?: 'User #' . $userOption->id;
                                    $optionInitials = collect(explode(' ', $optionName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                                @endphp
                                <button type="button" class="pas-user-row" wire:key="pas-user-{{ $userOption->id }}" wire:click="selectTargetUser({{ $userOption->id }})">
                                    <span class="pas-mini-avatar">{{ $optionInitials ?: 'U' }}</span>
                                    <span class="pas-user-copy">
                                        <span class="pas-user-name">{{ $optionName }}</span>
                                        <span class="pas-user-email">Personal: {{ $userOption->personal_email ?: 'missing' }}</span>
                                    </span>
                                    <span class="pas-user-pick">Select</span>
                                </button>
                            @empty
                                <div class="pas-empty">No users found.</div>
                            @endforelse
                        </div>
                    @endif
                @endif
            </section>

            <section class="pas-section">
                <div class="pas-section-label">What this is about</div>
                <div class="pas-concerns">
                    @foreach ($concerns as $key => $item)
                        <button type="button" wire:key="pas-concern-{{ $key }}" wire:click="selectConcern('{{ $key }}')" class="pas-concern {{ $concern === $key ? 'is-selected' : '' }}">
                            <span class="pas-radio"></span>
                            <span><span class="pas-concern-title">{{ $item['label'] ?? \Illuminate\Support\Str::headline($key) }}</span><span class="pas-concern-hint">{{ $item['hint'] ?? '' }}</span></span>
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
                <div class="pas-note">Email is sent as an individual PLYRCARD email to each recipient's <code>personal_email</code>. Users are never BCC'd together.</div>
            </section>

            <section class="pas-section">
                <div class="pas-field-row"><label class="pas-field-label" style="margin:0">Subject</label><button type="button" class="pas-reset" wire:click="resetTemplate">Reset prebuilt message</button></div>
                <input type="text" class="pas-subject" wire:model.live.debounce.450ms="subject" maxlength="255" style="margin-top:8px">
            </section>

            <section class="pas-section">
                <label class="pas-field-label">Message</label>
                <textarea class="pas-message" wire:model.live.debounce.450ms="message" maxlength="5000"></textarea>
                <div class="pas-live-count">{{ mb_strlen($message) }} / 5,000</div>
            </section>

            <section class="pas-section">
                <div class="pas-section-label">Add a variable</div>
                <div class="pas-variables">
                    @foreach ($variableDefinitions as $key => $label)
                        <button type="button" class="pas-variable" title="{{ $label }}" wire:click="appendVariable('{{ $key }}')">{{ '{' . '{' . $key . '}' . '}' }}</button>
                    @endforeach
                </div>
                <div class="pas-note">Each recipient gets their own resolved values. <code>{{ '{' . '{website_link}' . '}' }}</code> points to their PLYRSITE when available; <code>{{ '{' . '{admin_link}' . '}' }}</code> points to PLYRCARD Admin.</div>
            </section>

            <section class="pas-section">
                <div class="pas-section-label">Preview</div>
                <div class="pas-preview">
                    <div class="pas-preview-head">
                        <div class="pas-preview-subject">{{ $this->renderedSubject ?: 'No subject' }}</div>
                        @if ($this->previewUser)
                            @php $previewName = trim(($this->previewUser->first_name ?? '') . ' ' . ($this->previewUser->last_name ?? '')) ?: 'selected user'; @endphp
                            <div class="pas-preview-person">{{ $audienceMode === 'individual' ? 'Recipient' : 'Sample preview' }} · {{ $previewName }}</div>
                        @endif
                    </div>
                    <div class="pas-preview-body">{!! nl2br(e($this->renderedMessage)) !!}</div>
                    <div class="pas-preview-actions">
                        <span class="pas-preview-button">Open PLYRCARD</span>
                        @if (filled($this->variables['website_link'] ?? null))<span class="pas-preview-button secondary">View My Website</span>@endif
                    </div>
                    @if ($this->previewUser)
                        <div class="pas-recipient">
                            <span class="pas-recipient-pill {{ $this->previewUser->personal_email ? 'is-ready' : '' }}">Email · {{ $this->previewUser->personal_email ?: 'missing personal email' }}</span>
                            <span class="pas-recipient-pill {{ $this->previewUser->phone ? 'is-ready' : '' }}">SMS · {{ $this->previewUser->phone ?: 'missing' }}</span>
                        </div>
                    @endif
                </div>
                <div class="pas-note">The actual email uses the same PLYRCARD dark/orange system-email style as your existing account emails. It includes direct Admin/website buttons and a one-way footer that sends users to the Support form instead of asking them to reply.</div>
            </section>

            @if ($audienceMode !== 'individual')
                <label class="pas-confirm">
                    <input type="checkbox" wire:model.live="bulkConfirmed">
                    <span>I confirm this message should be sent to <strong>{{ $this->audienceCount }}</strong> selected user account(s). Each email uses the recipient's personal email only.</span>
                </label>
            @endif

            @if ($notice)<div class="pas-notice {{ $noticeType }}">{{ $notice }}</div>@endif

            <button type="button" class="pas-send" wire:click="send" wire:loading.attr="disabled" wire:target="send" @disabled($this->audienceCount < 1 || ($audienceMode !== 'individual' && ! $bulkConfirmed))>
                <span wire:loading.remove wire:target="send">Send to {{ $this->audienceCount }} user{{ $this->audienceCount === 1 ? '' : 's' }}</span>
                <span wire:loading.flex wire:target="send" style="align-items:center;gap:8px"><span class="pas-spinner"></span> Sending…</span>
            </button>

            @if ($audienceMode === 'individual' && $this->targetUser && $this->history->isNotEmpty())
                <section class="pas-section" style="margin-top:18px;border-top:1px solid var(--pas-line)">
                    <div class="pas-section-label">Recent messages to this user</div>
                    <div class="pas-history">
                        @foreach ($this->history as $historyItem)
                            <div class="pas-history-row" wire:key="pas-history-{{ $historyItem->id }}">
                                <div class="pas-history-top"><span class="pas-history-title">{{ $concerns[$historyItem->concern]['label'] ?? \Illuminate\Support\Str::headline($historyItem->concern) }}</span><span class="pas-history-time">{{ optional($historyItem->sent_at ?: $historyItem->created_at)->format('M j, g:i A') }}</span></div>
                                <div class="pas-history-meta">{{ strtoupper(str_replace('_', ' + ', $historyItem->channel)) }} · Email {{ $historyItem->email_status ?: '—' }} · SMS {{ $historyItem->sms_status ?: '—' }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </aside>
</div>