<div
    class="pas-root"
    x-data="{ open: false }"
    x-on:keydown.escape.window="if (open) { open = false; document.body.style.overflow = '' }"
    wire:key="admin-support-messenger-root-v1062"
>
    <style>
        .pas-root{--pas-accent:#ff6338;--pas-bg:#fff;--pas-surface:#f8fafc;--pas-surface-2:#f1f5f9;--pas-border:#e2e8f0;--pas-text:#111827;--pas-muted:#64748b;--pas-shadow:0 18px 55px rgba(15,23,42,.18);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif}
        .dark .pas-root{--pas-bg:#0f1217;--pas-surface:#151a20;--pas-surface-2:#1a2028;--pas-border:#2a313b;--pas-text:#f3f4f6;--pas-muted:#8b95a3;--pas-shadow:-22px 0 60px rgba(0,0,0,.38)}
        .pas-root *{box-sizing:border-box}
        .pas-launcher{position:fixed;right:22px;bottom:22px;z-index:10080;width:52px;height:52px;border:0;border-radius:15px;background:var(--pas-accent);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 12px 28px rgba(255,99,56,.28);transition:transform .16s ease,box-shadow .16s ease}
        .pas-launcher:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(255,99,56,.34)}
        .pas-launcher svg{width:21px;height:21px}
        .pas-backdrop{position:fixed;inset:0;z-index:10090;background:rgba(2,6,12,.48);backdrop-filter:blur(2px)}
        .pas-drawer{position:fixed;top:0;right:0;z-index:10100;width:min(520px,100vw);height:100dvh;background:var(--pas-bg);color:var(--pas-text);border-left:1px solid var(--pas-border);box-shadow:var(--pas-shadow);overflow:hidden}
        .pas-shell{height:100%;overflow-y:auto;padding-bottom:24px;scrollbar-width:thin;scrollbar-color:#475569 transparent}
        .pas-head{position:sticky;top:0;z-index:4;display:flex;justify-content:space-between;gap:14px;align-items:flex-start;padding:20px 22px 17px;background:color-mix(in srgb,var(--pas-bg) 94%,transparent);backdrop-filter:blur(12px);border-bottom:1px solid var(--pas-border)}
        .pas-kicker{font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--pas-muted)}
        .pas-head h2{margin:5px 0 4px;font-size:20px;line-height:1.2;font-weight:800}
        .pas-head p{margin:0;font-size:12px;line-height:1.5;color:var(--pas-muted)}
        .pas-close{width:34px;height:34px;border:1px solid var(--pas-border);border-radius:10px;background:var(--pas-surface);color:var(--pas-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;flex:0 0 auto;font-size:20px}
        .pas-email-only{display:inline-flex;align-items:center;gap:6px;margin-top:9px;padding:5px 8px;border:1px solid var(--pas-border);border-radius:999px;background:var(--pas-surface);color:var(--pas-muted);font-size:10.5px;font-weight:700}.pas-email-only i{width:6px;height:6px;border-radius:50%;background:var(--pas-accent)}
        .pas-section{padding:17px 22px;border-bottom:1px solid var(--pas-border)}
        .pas-label{display:block;margin-bottom:7px;font-size:11px;font-weight:750;color:var(--pas-muted)}
        .pas-select,.pas-input,.pas-textarea{width:100%;border:1px solid var(--pas-border);border-radius:10px;background:var(--pas-surface);color:var(--pas-text);outline:none;font:inherit;transition:border-color .15s ease,box-shadow .15s ease}.pas-select,.pas-input{height:42px;padding:0 11px;font-size:13px}.pas-textarea{min-height:150px;padding:11px 12px;resize:vertical;font-size:13px;line-height:1.55}.pas-select:focus,.pas-input:focus,.pas-textarea:focus{border-color:var(--pas-accent);box-shadow:0 0 0 3px rgba(255,99,56,.09)}
        .pas-hint{margin-top:6px;font-size:11.5px;line-height:1.5;color:var(--pas-muted)}
        .pas-summary{margin-top:10px;padding:10px 11px;border:1px solid var(--pas-border);border-radius:10px;background:var(--pas-surface);font-size:11.5px;line-height:1.5;color:var(--pas-muted)}.pas-summary strong{color:var(--pas-text)}
        .pas-target{display:flex;align-items:center;gap:10px;padding:10px 11px;border:1px solid var(--pas-border);border-radius:10px;background:var(--pas-surface)}
        .pas-avatar,.pas-mini-avatar{display:flex;align-items:center;justify-content:center;border-radius:9px;background:rgba(255,99,56,.12);color:var(--pas-accent);font-weight:800;flex:0 0 auto}.pas-avatar{width:36px;height:36px;font-size:11px}.pas-mini-avatar{width:31px;height:31px;font-size:10px}
        .pas-target-main,.pas-user-copy{min-width:0;flex:1}.pas-target-name,.pas-user-name{display:block;font-size:12.5px;font-weight:750;color:var(--pas-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pas-target-meta,.pas-user-email{display:block;margin-top:2px;font-size:10.5px;color:var(--pas-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .pas-link{border:0;background:transparent;color:var(--pas-accent);font-size:11px;font-weight:750;cursor:pointer;padding:4px}
        .pas-user-list{display:grid;gap:6px;margin-top:8px;max-height:230px;overflow:auto}.pas-user-row{width:100%;display:flex;align-items:center;gap:9px;padding:8px 9px;border:1px solid var(--pas-border);border-radius:9px;background:var(--pas-surface);color:var(--pas-text);cursor:pointer;text-align:left}.pas-user-row:hover,.pas-user-row.is-selected{border-color:rgba(255,99,56,.62);background:rgba(255,99,56,.07)}.pas-user-row:disabled{cursor:not-allowed;opacity:.48}.pas-user-action{font-size:10px;font-weight:800;color:var(--pas-accent)}
        .pas-chips{display:flex;flex-wrap:wrap;gap:6px;margin:9px 0}.pas-chip{display:inline-flex;align-items:center;gap:5px;padding:6px 8px;border:1px solid var(--pas-border);border-radius:999px;background:var(--pas-surface);font-size:10.5px;color:var(--pas-text)}.pas-chip button{border:0;background:transparent;color:var(--pas-muted);cursor:pointer;font-size:14px;line-height:1}
        .pas-details{border-bottom:1px solid var(--pas-border)}.pas-details>summary{list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 22px;cursor:pointer;font-size:12.5px;font-weight:750;color:var(--pas-text)}.pas-details>summary::-webkit-details-marker{display:none}.pas-details>summary:after{content:'+';font-size:18px;font-weight:400;color:var(--pas-muted)}.pas-details[open]>summary:after{content:'–'}.pas-details-body{padding:0 22px 17px}.pas-details-sub{font-size:11px;color:var(--pas-muted);font-weight:500}
        .pas-field-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:7px}.pas-reset{border:0;background:transparent;color:var(--pas-accent);font-size:10.5px;font-weight:750;cursor:pointer}.pas-count{margin-top:5px;text-align:right;font-size:10px;color:var(--pas-muted)}
        .pas-vars{display:flex;flex-wrap:wrap;gap:6px}.pas-var{border:1px solid var(--pas-border);border-radius:8px;background:var(--pas-surface);color:var(--pas-muted);padding:6px 8px;font-size:10.5px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;cursor:pointer}.pas-var:hover{border-color:rgba(255,99,56,.55);color:var(--pas-accent)}
        .pas-preview{padding:12px;border:1px solid var(--pas-border);border-radius:10px;background:var(--pas-surface);font-size:12.5px;line-height:1.65;color:var(--pas-text);white-space:pre-wrap}.pas-preview-subject{display:block;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--pas-border);font-weight:800}
        .pas-history{display:grid;gap:7px}.pas-history-row{padding:10px 11px;border:1px solid var(--pas-border);border-radius:9px;background:var(--pas-surface);font-size:11px;color:var(--pas-muted)}.pas-history-row strong{color:var(--pas-text)}
        .pas-footer{padding:17px 22px 20px}.pas-confirm{display:flex;align-items:flex-start;gap:8px;margin-bottom:11px;font-size:11px;line-height:1.45;color:var(--pas-muted)}.pas-confirm input{margin-top:2px;accent-color:var(--pas-accent)}
        .pas-send{width:100%;height:44px;border:0;border-radius:10px;background:var(--pas-accent);color:#fff;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 8px 20px rgba(255,99,56,.18)}.pas-send:disabled{opacity:.58;cursor:wait}.pas-send-inner{display:flex;align-items:center;justify-content:center;gap:8px}.pas-spinner{width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:pas-spin .7s linear infinite}@keyframes pas-spin{to{transform:rotate(360deg)}}
        .pas-notice{margin:0 22px 12px;padding:10px 11px;border-radius:9px;font-size:11.5px;line-height:1.45}.pas-notice.success{border:1px solid rgba(34,197,94,.28);background:rgba(34,197,94,.08);color:#36a96d}.pas-notice.warning{border:1px solid rgba(245,158,11,.3);background:rgba(245,158,11,.08);color:#c68a22}.pas-notice.error{border:1px solid rgba(239,68,68,.28);background:rgba(239,68,68,.08);color:#df6767}
        .pas-empty{padding:11px;border:1px dashed var(--pas-border);border-radius:9px;text-align:center;color:var(--pas-muted);font-size:11px}
        [x-cloak]{display:none!important}
        @media(max-width:640px){.pas-launcher{right:16px;bottom:16px}.pas-drawer{width:100vw}.pas-head,.pas-section,.pas-footer{padding-left:17px;padding-right:17px}.pas-details>summary{padding-left:17px;padding-right:17px}.pas-details-body{padding-left:17px;padding-right:17px}.pas-notice{margin-left:17px;margin-right:17px}}
    </style>

    <button
        type="button"
        class="pas-launcher"
        title="Admin Support"
        aria-label="Open Admin Support"
        x-on:click="open = true; document.body.style.overflow = 'hidden'"
    >
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5.8A2.8 2.8 0 0 1 6.8 3h10.4A2.8 2.8 0 0 1 20 5.8v7.4a2.8 2.8 0 0 1-2.8 2.8H10l-4.7 4v-4.3A2.8 2.8 0 0 1 4 13.2V5.8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 8.5h8M8 11.5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>

    <div x-cloak x-show="open" x-transition.opacity class="pas-backdrop" x-on:click="open = false; document.body.style.overflow = ''"></div>

    <aside x-cloak x-show="open" x-transition:enter="transition ease-out duration-180" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-140" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="pas-drawer" aria-label="Admin Support email composer">
        <div class="pas-shell">
            <header class="pas-head">
                <div>
                    <div class="pas-kicker">Admin Support</div>
                    <h2>Message users</h2>
                    <p>Send a one-way support email from the Admin panel.</p>
                    <span class="pas-email-only"><i></i>Email only · personal email</span>
                </div>
                <button type="button" class="pas-close" aria-label="Close" x-on:click="open = false; document.body.style.overflow = ''">×</button>
            </header>

            <section class="pas-section">
                <label class="pas-label" for="pas-audience-v1062">Recipients</label>
                <select id="pas-audience-v1062" class="pas-select" wire:change="selectAudienceMode($event.target.value)">
                    <option value="individual" @selected($audienceMode === 'individual')>Individual user</option>
                    <option value="custom" @selected($audienceMode === 'custom')>Custom list of users</option>
                    <option value="all" @selected($audienceMode === 'all')>All users</option>
                </select>

                @if ($audienceMode === 'all')
                    <div class="pas-summary"><strong>{{ $this->audienceCount }} accounts</strong> selected · {{ $this->personalEmailCount }} have a personal email on file. Only valid <code>personal_email</code> addresses will receive the email.</div>
                @elseif ($audienceMode === 'custom')
                    @if ($this->selectedCustomUsers->isNotEmpty())
                        <div class="pas-chips">
                            @foreach ($this->selectedCustomUsers as $selectedUser)
                                @php $selectedName = trim(($selectedUser->first_name ?? '') . ' ' . ($selectedUser->last_name ?? '')) ?: 'User #' . $selectedUser->id; @endphp
                                <span class="pas-chip" wire:key="pas-selected-{{ $selectedUser->id }}">{{ $selectedName }}<button type="button" wire:click="toggleCustomUser({{ $selectedUser->id }})" aria-label="Remove {{ $selectedName }}">×</button></span>
                            @endforeach
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><span class="pas-hint" style="margin:0">{{ $this->audienceCount }} recipient(s)</span><button type="button" class="pas-link" wire:click="clearCustomUsers">Clear</button></div>
                    @endif

                    <input type="search" class="pas-input" placeholder="Search name or personal email…" wire:model.live.debounce.300ms="userSearch" autocomplete="off">
                    <div class="pas-user-list">
                        @php $selectedIds = array_map('intval', $customUserIds); @endphp
                        @forelse ($this->targetUsers as $userOption)
                            @php
                                $optionName = trim(($userOption->first_name ?? '') . ' ' . ($userOption->last_name ?? '')) ?: 'User #' . $userOption->id;
                                $optionInitials = collect(explode(' ', $optionName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                                $validPersonalEmail = filter_var($userOption->personal_email, FILTER_VALIDATE_EMAIL) !== false;
                                $isSelected = in_array((int) $userOption->id, $selectedIds, true);
                            @endphp
                            <button type="button" class="pas-user-row {{ $isSelected ? 'is-selected' : '' }}" wire:key="pas-custom-option-{{ $userOption->id }}" wire:click="toggleCustomUser({{ $userOption->id }})" @disabled(! $validPersonalEmail)>
                                <span class="pas-mini-avatar">{{ $optionInitials ?: 'U' }}</span>
                                <span class="pas-user-copy"><span class="pas-user-name">{{ $optionName }}</span><span class="pas-user-email">{{ $validPersonalEmail ? $userOption->personal_email : 'No personal email on file' }}</span></span>
                                <span class="pas-user-action">{{ $validPersonalEmail ? ($isSelected ? 'Remove' : 'Add') : 'Unavailable' }}</span>
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
                        @endphp
                        <div class="pas-target" style="margin-top:9px">
                            <div class="pas-avatar">{{ $initials ?: 'U' }}</div>
                            <div class="pas-target-main"><div class="pas-target-name">{{ $targetName }}</div><div class="pas-target-meta">{{ $target->personal_email ?: 'No personal email' }}</div></div>
                            <button type="button" class="pas-link" wire:click="clearTarget">Change</button>
                        </div>
                    @else
                        <input type="search" class="pas-input" placeholder="Search name or personal email…" wire:model.live.debounce.300ms="userSearch" autocomplete="off" style="margin-top:9px">
                        <div class="pas-user-list">
                            @forelse ($this->targetUsers as $userOption)
                                @php
                                    $optionName = trim(($userOption->first_name ?? '') . ' ' . ($userOption->last_name ?? '')) ?: 'User #' . $userOption->id;
                                    $optionInitials = collect(explode(' ', $optionName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                                    $validPersonalEmail = filter_var($userOption->personal_email, FILTER_VALIDATE_EMAIL) !== false;
                                @endphp
                                <button type="button" class="pas-user-row" wire:key="pas-user-{{ $userOption->id }}" wire:click="selectTargetUser({{ $userOption->id }})" @disabled(! $validPersonalEmail)>
                                    <span class="pas-mini-avatar">{{ $optionInitials ?: 'U' }}</span>
                                    <span class="pas-user-copy"><span class="pas-user-name">{{ $optionName }}</span><span class="pas-user-email">{{ $validPersonalEmail ? $userOption->personal_email : 'No personal email on file' }}</span></span>
                                    <span class="pas-user-action">{{ $validPersonalEmail ? 'Select' : 'Unavailable' }}</span>
                                </button>
                            @empty
                                <div class="pas-empty">No users found.</div>
                            @endforelse
                        </div>
                    @endif
                @endif
            </section>

            <section class="pas-section">
                <label class="pas-label" for="pas-concern-v1062">Concern</label>
                <select id="pas-concern-v1062" class="pas-select" wire:change="selectConcern($event.target.value)">
                    @foreach ($concerns as $key => $item)
                        <option value="{{ $key }}" @selected($concern === $key)>{{ $item['label'] ?? \Illuminate\Support\Str::headline($key) }}</option>
                    @endforeach
                </select>
                <div class="pas-hint">{{ $concerns[$concern]['hint'] ?? '' }}</div>
            </section>

            <details class="pas-details" open>
                <summary><span>Message</span><span class="pas-details-sub">Editable before sending</span></summary>
                <div class="pas-details-body">
                    <div class="pas-field-row"><label class="pas-label" style="margin:0">Subject</label><button type="button" class="pas-reset" wire:click="resetTemplate">Reset template</button></div>
                    <input type="text" class="pas-input" wire:model.live.debounce.450ms="subject" maxlength="255">
                    <label class="pas-label" style="margin-top:13px">Body</label>
                    <textarea class="pas-textarea" wire:model.live.debounce.450ms="message" maxlength="5000"></textarea>
                    <div class="pas-count">{{ mb_strlen($message) }} / 5,000</div>
                </div>
            </details>

            <details class="pas-details">
                <summary><span>Variables</span><span class="pas-details-sub">Insert user-specific data</span></summary>
                <div class="pas-details-body">
                    <div class="pas-vars">
                        @foreach ($variableDefinitions as $key => $label)
                            <button type="button" class="pas-var" title="{{ $label }}" wire:click="appendVariable('{{ $key }}')">{{ '{' . '{' . $key . '}' . '}' }}</button>
                        @endforeach
                    </div>
                </div>
            </details>

            <details class="pas-details">
                <summary><span>Preview</span><span class="pas-details-sub">Uses the first selected user</span></summary>
                <div class="pas-details-body">
                    @if ($this->previewUser)
                        <div class="pas-preview"><span class="pas-preview-subject">{{ $this->renderedSubject }}</span>{{ $this->renderedMessage }}</div>
                    @else
                        <div class="pas-empty">Select at least one user to render a personalized preview.</div>
                    @endif
                </div>
            </details>

            @if ($audienceMode === 'individual' && $this->targetUser && $this->history->isNotEmpty())
                <details class="pas-details">
                    <summary><span>Recent sends</span><span class="pas-details-sub">Last {{ $this->history->count() }}</span></summary>
                    <div class="pas-details-body"><div class="pas-history">
                        @foreach ($this->history as $historyItem)
                            <div class="pas-history-row" wire:key="pas-history-{{ $historyItem->id }}"><strong>{{ $historyItem->subject ?: 'Support email' }}</strong><br>{{ $historyItem->sent_at?->format('M j, Y g:i A') ?: $historyItem->created_at?->format('M j, Y g:i A') }} · {{ $historyItem->email_status ?: 'unknown' }}</div>
                        @endforeach
                    </div></div>
                </details>
            @endif

            <div class="pas-footer">
                @if ($audienceMode !== 'individual')
                    <label class="pas-confirm"><input type="checkbox" wire:model.live="bulkConfirmed"><span>I reviewed this audience and want to send this email to {{ $this->audienceCount }} user account(s). Only valid personal emails will be used.</span></label>
                @endif

                @if ($notice)
                    <div class="pas-notice {{ $noticeType }}">{{ $notice }}</div>
                @endif

                <button type="button" class="pas-send" wire:click="send" wire:loading.attr="disabled" wire:target="send">
                    <span class="pas-send-inner"><span wire:loading wire:target="send" class="pas-spinner" aria-hidden="true"></span><span wire:loading.remove wire:target="send">Send email</span><span wire:loading wire:target="send">Sending…</span></span>
                </button>
                <div class="pas-hint" style="text-align:center;margin-top:8px">One-way email. Replies are directed away from this tool; users should use PLYRCARD Support.</div>
            </div>
        </div>
    </aside>
</div>