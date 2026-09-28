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

            <div class="recruiting-exclusivity-form-actions">
                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="recruiting-exclusivity-primary"
                >
                    <span wire:loading.remove wire:target="save">Save exclusivity</span>
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
        .recruiting-exclusivity-page{display:grid;gap:1rem;max-width:1180px}.recruiting-exclusivity-tabs{display:flex;gap:.25rem;border-bottom:1px solid #e5e7eb}.recruiting-exclusivity-tabs a{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem .8rem;color:#667085;font-size:.86rem;font-weight:750;text-decoration:none;border-bottom:2px solid transparent}.recruiting-exclusivity-tabs a svg{width:1.05rem;height:1.05rem}.recruiting-exclusivity-tabs a:hover,.recruiting-exclusivity-tabs a.is-active{color:#ff6338}.recruiting-exclusivity-tabs a.is-active{border-bottom-color:#ff6338}.dark .recruiting-exclusivity-tabs{border-color:#2b3038}.recruiting-exclusivity-intro{display:flex;justify-content:space-between;gap:1rem;padding:.15rem 0}.recruiting-exclusivity-intro h2{margin:0 0 .25rem;font-size:1.05rem;font-weight:850}.recruiting-exclusivity-intro p{margin:0;max-width:820px;color:#667085;font-size:.83rem;line-height:1.55}.dark .recruiting-exclusivity-intro p{color:#98a2b3}.recruiting-exclusivity-form-card,.recruiting-exclusivity-list-card{border:1px solid #e4e7ec;border-radius:.9rem;background:#fff}.dark .recruiting-exclusivity-form-card,.dark .recruiting-exclusivity-list-card{background:#111318;border-color:#2b3038}.recruiting-exclusivity-form-card{padding:1rem}.recruiting-exclusivity-form-actions{display:flex;justify-content:flex-end;margin-top:.85rem;padding-top:.85rem;border-top:1px solid #eef0f3}.dark .recruiting-exclusivity-form-actions{border-color:#252a32}.recruiting-exclusivity-primary{border:0;border-radius:.65rem;background:#ff6338;color:#fff;padding:.65rem .9rem;font-size:.82rem;font-weight:800;cursor:pointer}.recruiting-exclusivity-primary:disabled{opacity:.6;cursor:wait}.recruiting-exclusivity-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.recruiting-exclusivity-list-card{overflow:hidden}.recruiting-exclusivity-list-card>header{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;border-bottom:1px solid #eef0f3}.dark .recruiting-exclusivity-list-card>header{border-color:#252a32}.recruiting-exclusivity-list-card h3{margin:.1rem 0 0;font-size:.92rem;font-weight:850}.recruiting-exclusivity-kicker{font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#98a2b3}.recruiting-exclusivity-count{display:grid;place-items:center;min-width:1.7rem;height:1.7rem;border-radius:999px;background:#f2f4f7;color:#475467;font-size:.72rem;font-weight:850}.dark .recruiting-exclusivity-count{background:#242931;color:#cbd5e1}.recruiting-exclusivity-rule-list{display:grid}.recruiting-exclusivity-rule{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid #f2f4f7}.recruiting-exclusivity-rule:last-child{border-bottom:0}.dark .recruiting-exclusivity-rule{border-color:#232832}.recruiting-exclusivity-rule-main{min-width:0;display:grid;gap:.55rem}.recruiting-exclusivity-rule-title{display:flex;align-items:flex-start;gap:.55rem;min-width:0}.recruiting-exclusivity-rule-title>svg{width:1rem;height:1rem;flex:none;margin-top:.12rem;color:#ff6338}.recruiting-exclusivity-rule-title div{min-width:0}.recruiting-exclusivity-rule-title strong{display:block;font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.recruiting-exclusivity-rule-title small{display:block;margin-top:.1rem;color:#98a2b3;font-size:.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.recruiting-exclusivity-users{display:flex;gap:.3rem;flex-wrap:wrap}.recruiting-exclusivity-users span{display:inline-flex;max-width:180px;padding:.2rem .4rem;border-radius:.45rem;background:#fff4ef;color:#c2410c;font-size:.68rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .recruiting-exclusivity-users span{background:rgba(255,99,56,.12);color:#ff9a7c}.recruiting-exclusivity-rule-actions{display:flex;gap:.35rem;flex:none}.recruiting-exclusivity-rule-actions button{border:1px solid #d0d5dd;border-radius:.55rem;background:transparent;color:inherit;padding:.38rem .5rem;font-size:.7rem;font-weight:800;cursor:pointer}.dark .recruiting-exclusivity-rule-actions button{border-color:#3a404b}.recruiting-exclusivity-rule-actions button:hover{border-color:#ff6338;color:#ff6338}.recruiting-exclusivity-rule-actions button.is-danger:hover{border-color:#ef4444;color:#ef4444}.recruiting-exclusivity-empty{padding:1.1rem;color:#98a2b3;font-size:.78rem;line-height:1.5}@media(max-width:900px){.recruiting-exclusivity-grid{grid-template-columns:1fr}}@media(max-width:640px){.recruiting-exclusivity-tabs{overflow:auto}.recruiting-exclusivity-rule{align-items:flex-start;flex-direction:column}.recruiting-exclusivity-rule-actions{width:100%;justify-content:flex-end}}
    </style>
</x-filament-panels::page>
