<x-filament-panels::page>
    @php($rules = $this->rules)

    <div
        class="recruiting-exclusivity-page"
        x-data
        x-on:scroll-to-exclusivity-form.window="document.getElementById('recruiting-exclusivity-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
    >
        <div class="recruiting-exclusivity-tabs" aria-label="Coach database sections">
            <a href="{{ \App\Filament\Resources\Coaches\CoachResource::getUrl('index') }}">
                <x-filament::icon icon="heroicon-o-user-group" />
                <span>Coaches</span>
            </a>
            <a href="{{ \App\Filament\Resources\CoachDirectorySchools\CoachDirectorySchoolResource::getUrl('index') }}">
                <x-filament::icon icon="heroicon-o-academic-cap" />
                <span>Schools</span>
            </a>
            <a class="is-active" href="{{ \App\Filament\Resources\Coaches\CoachResource::getUrl('exclusivity') }}" aria-current="page">
                <x-filament::icon icon="heroicon-o-lock-closed" />
                <span>Exclusivity</span>
            </a>
        </div>

        <section class="recruiting-exclusivity-intro">
            <div>
                <h2>Exclusive recruiting access</h2>
                <p>
                    A school rule hides the school and every coach under it from all users except the selected users.
                    A coach rule hides only that coach; other coaches at the same school remain available.
                </p>
            </div>
        </section>

        <section id="recruiting-exclusivity-form" class="recruiting-exclusivity-form-card">
            {{ $this->form }}

            <div class="recruiting-exclusivity-access">
                <div class="recruiting-exclusivity-access-head">
                    <div>
                        <strong>Access</strong>
                        <span>Choose who can see the selected school or coach in Recruiting Center.</span>
                    </div>
                    <span class="recruiting-exclusivity-status {{ $visibilityType === 'public' ? 'is-public' : 'is-restricted' }}">
                        {{ $visibilityType === 'public' ? 'Public' : 'Restricted' }}
                    </span>
                </div>

                <div class="recruiting-exclusivity-modes" role="group" aria-label="Exclusivity audience type">
                    @foreach([
                        'public' => ['Public', 'heroicon-o-globe-alt'],
                        'users' => ['User(s)', 'heroicon-o-user-group'],
                        'club' => ['Club', 'heroicon-o-building-office-2'],
                        'league' => ['League', 'heroicon-o-trophy'],
                    ] as $mode => [$label, $icon])
                        <button
                            type="button"
                            wire:click="setVisibilityType('{{ $mode }}')"
                            wire:loading.attr="disabled"
                            class="recruiting-exclusivity-mode {{ $visibilityType === $mode ? 'is-active' : '' }}"
                        >
                            <x-filament::icon :icon="$icon" />
                            <span>{{ $label }}</span>
                        </button>
                    @endforeach
                </div>

                @if($visibilityType === 'users')
                    <div class="recruiting-exclusivity-user-picker">
                        <div class="recruiting-exclusivity-user-picker-head">
                            <div>
                                <span class="recruiting-exclusivity-field-label">Allowed users</span>
                                <small>Select one or several athletes. Click again to remove a selected user.</small>
                            </div>
                            <span class="recruiting-exclusivity-selected-count">{{ count($this->visibilitySelectedUsers) }} selected</span>
                        </div>

                        @if($this->visibilitySelectedUsers)
                            <div class="recruiting-exclusivity-selected-users">
                                @foreach($this->visibilitySelectedUsers as $selectedUser)
                                    <button
                                        type="button"
                                        wire:key="manual-selected-user-{{ $selectedUser['id'] }}"
                                        wire:click="removeVisibilityUser({{ $selectedUser['id'] }})"
                                        class="recruiting-exclusivity-selected-user"
                                        title="Remove this user"
                                    >
                                        <span>{{ $selectedUser['label'] }}</span>
                                        <b aria-hidden="true">×</b>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="recruiting-exclusivity-user-search">
                            <x-filament::icon icon="heroicon-o-magnifying-glass" />
                            <input
                                type="search"
                                wire:model.live.debounce.250ms="visibilityUserSearch"
                                placeholder="Search athlete by name or email"
                                autocomplete="off"
                            >
                        </div>

                        <div class="recruiting-exclusivity-user-results">
                            @forelse($this->visibilityUserOptions as $userId => $userLabel)
                                @php($userSelected = in_array((int) $userId, collect((array) $visibilityUserIds)->map(fn ($id) => (int) $id)->all(), true))
                                <button
                                    type="button"
                                    wire:key="manual-user-result-{{ $userId }}"
                                    wire:click="toggleVisibilityUser({{ (int) $userId }})"
                                    class="recruiting-exclusivity-user-result {{ $userSelected ? 'is-selected' : '' }}"
                                >
                                    <span class="recruiting-exclusivity-user-check">
                                        @if($userSelected)
                                            <x-filament::icon icon="heroicon-o-check" />
                                        @endif
                                    </span>
                                    <span>{{ $userLabel }}</span>
                                </button>
                            @empty
                                <div class="recruiting-exclusivity-user-empty">No matching eligible users.</div>
                            @endforelse
                        </div>
                    </div>
                @elseif($visibilityType === 'club')
                    <label class="recruiting-exclusivity-field">
                        <span class="recruiting-exclusivity-field-label">Club</span>
                        <select wire:model.live="visibilityClubId">
                            <option value="">Choose a club</option>
                            @foreach($this->visibilityClubOptions as $clubId => $clubLabel)
                                <option value="{{ $clubId }}">{{ $clubLabel }}</option>
                            @endforeach
                        </select>
                        <small>Every currently eligible athlete attached to this club will receive access.</small>
                    </label>
                @elseif($visibilityType === 'league')
                    <label class="recruiting-exclusivity-field">
                        <span class="recruiting-exclusivity-field-label">League</span>
                        <select wire:model.live="visibilityLeagueId">
                            <option value="">Choose a league</option>
                            @foreach($this->visibilityLeagueOptions as $leagueId => $leagueLabel)
                                <option value="{{ $leagueId }}">{{ $leagueLabel }}</option>
                            @endforeach
                        </select>
                        <small>Every currently eligible athlete attached to this league will receive access.</small>
                    </label>
                @else
                    <p class="recruiting-exclusivity-public-note">Public removes any existing exclusivity rule for the selected school or coach.</p>
                @endif
            </div>

            <div class="recruiting-exclusivity-form-actions">
                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="recruiting-exclusivity-primary {{ $visibilityType === 'public' ? 'is-public-action' : '' }}"
                >
                    <span wire:loading.remove wire:target="save">{{ $visibilityType === 'public' ? 'Make public' : 'Save exclusivity' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </section>

        <div class="recruiting-exclusivity-grid">
            <section class="recruiting-exclusivity-list-card">
                <header>
                    <div>
                        <span class="recruiting-exclusivity-kicker">School rules</span>
                        <h3>Exclusive schools</h3>
                    </div>
                    <span class="recruiting-exclusivity-count">{{ count($rules['schools'] ?? []) }}</span>
                </header>

                <div class="recruiting-exclusivity-rule-list">
                    @forelse ($rules['schools'] ?? [] as $rule)
                        <article class="recruiting-exclusivity-rule" wire:key="school-exclusivity-{{ $rule['id'] }}">
                            <div class="recruiting-exclusivity-rule-main">
                                <div class="recruiting-exclusivity-rule-title">
                                    <x-filament::icon icon="heroicon-o-academic-cap" />
                                    <div>
                                        <strong>{{ $rule['label'] }}</strong>
                                        @if (filled($rule['meta'] ?? null))
                                            <small>{{ $rule['meta'] }}</small>
                                        @endif
                                    </div>
                                </div>

                                <div class="recruiting-exclusivity-users">
                                    @foreach ($rule['users'] as $user)
                                        <span title="{{ $user['email'] }}">{{ $user['name'] }}</span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="recruiting-exclusivity-rule-actions">
                                <button type="button" wire:click="editRule('school', {{ $rule['id'] }})">Edit</button>
                                <button
                                    type="button"
                                    class="is-danger"
                                    wire:click="makePublic('school', {{ $rule['id'] }})"
                                    wire:confirm="Make this school public to every eligible Recruiting Center user?"
                                >Public</button>
                            </div>
                        </article>
                    @empty
                        <div class="recruiting-exclusivity-empty">No schools are exclusive. Every school is currently public to eligible users.</div>
                    @endforelse
                </div>
            </section>

            <section class="recruiting-exclusivity-list-card">
                <header>
                    <div>
                        <span class="recruiting-exclusivity-kicker">Coach rules</span>
                        <h3>Exclusive coaches</h3>
                    </div>
                    <span class="recruiting-exclusivity-count">{{ count($rules['coaches'] ?? []) }}</span>
                </header>

                <div class="recruiting-exclusivity-rule-list">
                    @forelse ($rules['coaches'] ?? [] as $rule)
                        <article class="recruiting-exclusivity-rule" wire:key="coach-exclusivity-{{ $rule['id'] }}">
                            <div class="recruiting-exclusivity-rule-main">
                                <div class="recruiting-exclusivity-rule-title">
                                    <x-filament::icon icon="heroicon-o-user" />
                                    <div>
                                        <strong>{{ $rule['label'] }}</strong>
                                        @if (filled($rule['meta'] ?? null))
                                            <small>{{ $rule['meta'] }}</small>
                                        @endif
                                    </div>
                                </div>

                                <div class="recruiting-exclusivity-users">
                                    @foreach ($rule['users'] as $user)
                                        <span title="{{ $user['email'] }}">{{ $user['name'] }}</span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="recruiting-exclusivity-rule-actions">
                                <button type="button" wire:click="editRule('coach', {{ $rule['id'] }})">Edit</button>
                                <button
                                    type="button"
                                    class="is-danger"
                                    wire:click="makePublic('coach', {{ $rule['id'] }})"
                                    wire:confirm="Make this coach public to every eligible Recruiting Center user?"
                                >Public</button>
                            </div>
                        </article>
                    @empty
                        <div class="recruiting-exclusivity-empty">No individual coaches are exclusive.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <style>
        .recruiting-exclusivity-page{display:grid;gap:1rem;max-width:1180px}.recruiting-exclusivity-tabs{display:flex;gap:.25rem;border-bottom:1px solid #e5e7eb}.recruiting-exclusivity-tabs a{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem .8rem;color:#667085;font-size:.86rem;font-weight:750;text-decoration:none;border-bottom:2px solid transparent}.recruiting-exclusivity-tabs a svg{width:1.05rem;height:1.05rem}.recruiting-exclusivity-tabs a:hover,.recruiting-exclusivity-tabs a.is-active{color:#ff6338}.recruiting-exclusivity-tabs a.is-active{border-bottom-color:#ff6338}.dark .recruiting-exclusivity-tabs{border-color:#2b3038}.recruiting-exclusivity-intro{display:flex;justify-content:space-between;gap:1rem;padding:.15rem 0}.recruiting-exclusivity-intro h2{margin:0 0 .25rem;font-size:1.05rem;font-weight:850}.recruiting-exclusivity-intro p{margin:0;max-width:820px;color:#667085;font-size:.83rem;line-height:1.55}.dark .recruiting-exclusivity-intro p{color:#98a2b3}.recruiting-exclusivity-form-card,.recruiting-exclusivity-list-card{border:1px solid #e4e7ec;border-radius:.9rem;background:#fff}.dark .recruiting-exclusivity-form-card,.dark .recruiting-exclusivity-list-card{background:#111318;border-color:#2b3038}.recruiting-exclusivity-form-card{padding:1rem}.recruiting-exclusivity-access{margin-top:14px;padding:14px;border:1px solid rgba(148,163,184,.28);border-radius:14px;background:linear-gradient(180deg,rgba(248,250,252,.9),rgba(255,255,255,.8));--rex-accent:#ff6338;--rex-muted:#64748b}.dark .recruiting-exclusivity-access{background:rgba(255,255,255,.025);border-color:rgba(148,163,184,.2)}.recruiting-exclusivity-access-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.recruiting-exclusivity-access-head>div{display:grid;gap:2px}.recruiting-exclusivity-access-head strong{font-size:14px}.recruiting-exclusivity-access-head span{color:var(--rex-muted);font-size:11px}.recruiting-exclusivity-status{display:inline-flex;align-items:center;min-height:24px;padding:3px 8px;border-radius:999px;font-size:10px!important;font-weight:850}.recruiting-exclusivity-status.is-public{background:rgba(34,197,94,.10);color:#15803d!important}.recruiting-exclusivity-status.is-restricted{background:rgba(255,99,56,.11);color:#c2410c!important}.recruiting-exclusivity-modes{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin-top:12px}.recruiting-exclusivity-mode{display:flex;align-items:center;justify-content:center;gap:6px;min-height:38px;border:1px solid rgba(148,163,184,.28);border-radius:10px;background:transparent;color:inherit;font-size:12px;font-weight:800;cursor:pointer;transition:.14s ease}.recruiting-exclusivity-mode svg{width:15px;height:15px}.recruiting-exclusivity-mode:hover{border-color:rgba(255,99,56,.5);color:var(--rex-accent)}.recruiting-exclusivity-mode.is-active{border-color:rgba(255,99,56,.48);background:rgba(255,99,56,.09);color:var(--rex-accent);box-shadow:inset 0 0 0 1px rgba(255,99,56,.04)}.recruiting-exclusivity-mode:disabled{opacity:.55;cursor:wait}.recruiting-exclusivity-field{display:block;margin-top:12px}.recruiting-exclusivity-field-label{display:block;margin-bottom:5px;font-size:11px;font-weight:800;color:var(--rex-muted);text-transform:uppercase;letter-spacing:.08em}.recruiting-exclusivity-field select{width:100%;min-height:42px;border:1px solid rgba(148,163,184,.45);border-radius:10px;padding:8px 10px;background:transparent}.recruiting-exclusivity-field small{display:block;margin-top:5px;color:var(--rex-muted);font-size:11px}.recruiting-exclusivity-user-picker{margin-top:12px;border:1px solid rgba(148,163,184,.28);border-radius:12px;overflow:hidden;background:#fff}.dark .recruiting-exclusivity-user-picker{background:rgba(24,24,27,.85);border-color:rgba(148,163,184,.2)}.recruiting-exclusivity-user-picker-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:10px 11px;border-bottom:1px solid rgba(148,163,184,.28)}.recruiting-exclusivity-user-picker-head small{display:block;color:var(--rex-muted);font-size:10px}.recruiting-exclusivity-selected-count{flex:none;padding:3px 7px;border-radius:999px;background:rgba(255,99,56,.10);color:var(--rex-accent);font-size:10px;font-weight:850}.recruiting-exclusivity-selected-users{display:flex;gap:6px;flex-wrap:wrap;padding:9px 11px 0}.recruiting-exclusivity-selected-user{display:inline-flex;align-items:center;gap:6px;max-width:100%;border:0;border-radius:999px;padding:5px 8px;background:rgba(255,99,56,.10);color:#c2410c;font-size:10px;font-weight:750;cursor:pointer}.recruiting-exclusivity-selected-user span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.recruiting-exclusivity-selected-user b{font-size:14px;line-height:1}.recruiting-exclusivity-user-search{display:flex;align-items:center;gap:8px;margin:9px 11px;border:1px solid rgba(148,163,184,.45);border-radius:10px;padding:0 10px;background:transparent}.recruiting-exclusivity-user-search svg{width:16px;height:16px;color:var(--rex-muted);flex:none}.recruiting-exclusivity-user-search input{width:100%;min-height:38px;border:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;padding:7px 0;font-size:12px}.recruiting-exclusivity-user-results{display:grid;max-height:230px;overflow:auto;border-top:1px solid rgba(148,163,184,.28)}.recruiting-exclusivity-user-result{display:flex;align-items:center;gap:9px;width:100%;padding:9px 11px;border:0;border-bottom:1px solid rgba(148,163,184,.15);background:transparent;color:inherit;text-align:left;font-size:11px;cursor:pointer}.recruiting-exclusivity-user-result:last-child{border-bottom:0}.recruiting-exclusivity-user-result:hover{background:rgba(148,163,184,.07)}.recruiting-exclusivity-user-result.is-selected{background:rgba(255,99,56,.06);color:#c2410c}.recruiting-exclusivity-user-check{display:grid;place-items:center;width:17px;height:17px;flex:none;border:1px solid rgba(148,163,184,.55);border-radius:5px}.recruiting-exclusivity-user-result.is-selected .recruiting-exclusivity-user-check{border-color:var(--rex-accent);background:var(--rex-accent);color:#fff}.recruiting-exclusivity-user-check svg{width:11px;height:11px}.recruiting-exclusivity-user-empty{padding:14px;color:var(--rex-muted);font-size:11px;text-align:center}.recruiting-exclusivity-public-note{margin:10px 0 0;padding:8px 10px;border-radius:9px;background:rgba(34,197,94,.06);color:#4b5563;font-size:11px;line-height:1.5}.dark .recruiting-exclusivity-public-note{color:#9ca3af}.recruiting-exclusivity-form-actions{display:flex;justify-content:flex-end;margin-top:.85rem;padding-top:.85rem;border-top:1px solid #eef0f3}.dark .recruiting-exclusivity-form-actions{border-color:#252a32}.recruiting-exclusivity-primary{border:0;border-radius:.65rem;background:#ff6338;color:#fff;padding:.65rem .9rem;font-size:.82rem;font-weight:800;cursor:pointer}.recruiting-exclusivity-primary:disabled{opacity:.6;cursor:wait}.recruiting-exclusivity-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.recruiting-exclusivity-list-card{overflow:hidden}.recruiting-exclusivity-list-card>header{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;border-bottom:1px solid #eef0f3}.dark .recruiting-exclusivity-list-card>header{border-color:#252a32}.recruiting-exclusivity-list-card h3{margin:.1rem 0 0;font-size:.92rem;font-weight:850}.recruiting-exclusivity-kicker{font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#98a2b3}.recruiting-exclusivity-count{display:grid;place-items:center;min-width:1.7rem;height:1.7rem;border-radius:999px;background:#f2f4f7;color:#475467;font-size:.72rem;font-weight:850}.dark .recruiting-exclusivity-count{background:#242931;color:#cbd5e1}.recruiting-exclusivity-rule-list{display:grid}.recruiting-exclusivity-rule{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid #f2f4f7}.recruiting-exclusivity-rule:last-child{border-bottom:0}.dark .recruiting-exclusivity-rule{border-color:#232832}.recruiting-exclusivity-rule-main{min-width:0;display:grid;gap:.55rem}.recruiting-exclusivity-rule-title{display:flex;align-items:flex-start;gap:.55rem;min-width:0}.recruiting-exclusivity-rule-title>svg{width:1rem;height:1rem;flex:none;margin-top:.12rem;color:#ff6338}.recruiting-exclusivity-rule-title div{min-width:0}.recruiting-exclusivity-rule-title strong{display:block;font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.recruiting-exclusivity-rule-title small{display:block;margin-top:.1rem;color:#98a2b3;font-size:.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.recruiting-exclusivity-users{display:flex;gap:.3rem;flex-wrap:wrap}.recruiting-exclusivity-users span{display:inline-flex;max-width:180px;padding:.2rem .4rem;border-radius:.45rem;background:#fff4ef;color:#c2410c;font-size:.68rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .recruiting-exclusivity-users span{background:rgba(255,99,56,.12);color:#ff9a7c}.recruiting-exclusivity-rule-actions{display:flex;gap:.35rem;flex:none}.recruiting-exclusivity-rule-actions button{border:1px solid #d0d5dd;border-radius:.55rem;background:transparent;color:inherit;padding:.38rem .5rem;font-size:.7rem;font-weight:800;cursor:pointer}.dark .recruiting-exclusivity-rule-actions button{border-color:#3a404b}.recruiting-exclusivity-rule-actions button:hover{border-color:#ff6338;color:#ff6338}.recruiting-exclusivity-rule-actions button.is-danger:hover{border-color:#ef4444;color:#ef4444}.recruiting-exclusivity-empty{padding:1.1rem;color:#98a2b3;font-size:.78rem;line-height:1.5}@media(max-width:900px){.recruiting-exclusivity-grid{grid-template-columns:1fr}}@media(max-width:800px){.recruiting-exclusivity-modes{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.recruiting-exclusivity-tabs{overflow:auto}.recruiting-exclusivity-rule{align-items:flex-start;flex-direction:column}.recruiting-exclusivity-rule-actions{width:100%;justify-content:flex-end}}
    </style>
</x-filament-panels::page>