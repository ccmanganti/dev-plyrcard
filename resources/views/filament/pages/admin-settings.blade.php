<x-filament-panels::page>
    <div class="pc-admin-settings-v1">
        <section class="pc-admin-settings-card-v1">
            <div class="pc-admin-settings-heading-v1">
                <div>
                    <span class="pc-admin-settings-kicker-v1">Platform controls</span>
                    <h2>Sports availability</h2>
                    <p>
                        Turn sports on or off for new selections across athlete registration,
                        athlete profile editing, and Coach Database management/imports.
                        Existing records keep their current sport even when that sport is disabled.
                    </p>
                </div>
                <div class="pc-admin-settings-count-v1">
                    <strong>{{ count($enabledSports) }}</strong>
                    <span>enabled</span>
                </div>
            </div>

            <div class="pc-admin-settings-toolbar-v1">
                <button type="button" wire:click="enableAllSports">Enable all</button>
                <button type="button" wire:click="disableAllSports">Disable all</button>
            </div>

            <div class="pc-admin-settings-grid-v1">
                @foreach($this->sports as $sport)
                    @php($isEnabled = in_array($sport['key'], $enabledSports, true))
                    <label class="pc-admin-sport-v1 {{ $isEnabled ? 'is-enabled' : '' }}" wire:key="admin-sport-{{ $sport['key'] }}">
                        <input
                            type="checkbox"
                            wire:model.live="enabledSports"
                            value="{{ $sport['key'] }}"
                        >
                        <span class="pc-admin-sport-switch-v1" aria-hidden="true"><i></i></span>
                        <span class="pc-admin-sport-copy-v1">
                            <strong>{{ $sport['label'] }}</strong>
                            <small>
                                @if($sport['athlete'])
                                    Athlete registration/profile + Coach Database
                                @else
                                    Coach Database only
                                @endif
                            </small>
                        </span>
                        <span class="pc-admin-sport-status-v1">{{ $isEnabled ? 'On' : 'Off' }}</span>
                    </label>
                @endforeach
            </div>

            <div class="pc-admin-settings-note-v1">
                <x-filament::icon icon="heroicon-o-information-circle" />
                <span>
                    Disabling a sport hides it from new choices. Existing athletes and coaches assigned to it remain unchanged and can still be viewed.
                </span>
            </div>

            <div class="pc-admin-settings-actions-v1">
                <button type="button" class="primary" wire:click="saveSports" wire:loading.attr="disabled" wire:target="saveSports">
                    <span wire:loading.remove wire:target="saveSports">Save Sports</span>
                    <span wire:loading wire:target="saveSports">Saving...</span>
                </button>
            </div>
        </section>
    </div>

    <style>
        .pc-admin-settings-v1{max-width:1180px;margin:0 auto;padding:.5rem 0 2rem}
        .pc-admin-settings-card-v1{border:1px solid #e5e7eb;border-radius:1.1rem;background:#fff;box-shadow:0 14px 36px rgba(15,23,42,.05);overflow:hidden}
        .dark .pc-admin-settings-card-v1{background:#111318;border-color:#2c313b}
        .pc-admin-settings-heading-v1{display:flex;gap:1rem;align-items:flex-start;justify-content:space-between;padding:1.4rem 1.5rem 1.1rem;border-bottom:1px solid #eaecf0}
        .dark .pc-admin-settings-heading-v1{border-color:#2c313b}
        .pc-admin-settings-heading-v1 h2{margin:.12rem 0 .3rem;font-size:1.35rem;font-weight:850;letter-spacing:-.025em}
        .pc-admin-settings-heading-v1 p{margin:0;max-width:760px;color:#667085;line-height:1.55;font-size:.9rem}
        .dark .pc-admin-settings-heading-v1 p{color:#98a2b3}
        .pc-admin-settings-kicker-v1{font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;font-weight:850;color:#ff6338}
        .pc-admin-settings-count-v1{min-width:86px;padding:.65rem .8rem;border:1px solid #ffd2c6;border-radius:.9rem;background:#fff7f4;text-align:center;color:#c4320a}
        .pc-admin-settings-count-v1 strong{display:block;font-size:1.25rem;line-height:1}.pc-admin-settings-count-v1 span{display:block;margin-top:.2rem;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
        .dark .pc-admin-settings-count-v1{background:#261712;border-color:#5b2b20;color:#ff8b6b}
        .pc-admin-settings-toolbar-v1{display:flex;justify-content:flex-end;gap:.5rem;padding:.85rem 1.5rem 0}
        .pc-admin-settings-toolbar-v1 button{border:1px solid #d0d5dd;border-radius:.7rem;background:#fff;color:#344054;padding:.5rem .7rem;font-weight:750;font-size:.78rem;cursor:pointer}
        .dark .pc-admin-settings-toolbar-v1 button{background:#17191f;border-color:#343943;color:#e5e7eb}
        .pc-admin-settings-grid-v1{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem;padding:1rem 1.5rem 1.35rem}
        .pc-admin-sport-v1{position:relative;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.75rem;padding:.82rem .9rem;border:1px solid #e4e7ec;border-radius:.9rem;background:#fff;cursor:pointer;transition:border-color .16s ease,box-shadow .16s ease,background .16s ease}
        .pc-admin-sport-v1:hover{border-color:#ffb49f;box-shadow:0 6px 18px rgba(16,24,40,.05)}
        .pc-admin-sport-v1.is-enabled{border-color:#ffc0ae;background:#fffaf8}
        .dark .pc-admin-sport-v1{background:#15181e;border-color:#303641}.dark .pc-admin-sport-v1.is-enabled{background:#211713;border-color:#6e3628}
        .pc-admin-sport-v1 input{position:absolute;opacity:0;pointer-events:none}
        .pc-admin-sport-switch-v1{width:2.25rem;height:1.25rem;border-radius:999px;background:#d0d5dd;padding:.15rem;transition:.18s ease;display:flex;align-items:center}
        .pc-admin-sport-switch-v1 i{display:block;width:.95rem;height:.95rem;border-radius:999px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.18);transition:.18s ease}
        .pc-admin-sport-v1.is-enabled .pc-admin-sport-switch-v1{background:#ff6338}.pc-admin-sport-v1.is-enabled .pc-admin-sport-switch-v1 i{transform:translateX(1rem)}
        .pc-admin-sport-copy-v1{min-width:0}.pc-admin-sport-copy-v1 strong{display:block;font-size:.88rem;font-weight:820;color:#101828}.dark .pc-admin-sport-copy-v1 strong{color:#f3f4f6}
        .pc-admin-sport-copy-v1 small{display:block;margin-top:.15rem;font-size:.7rem;color:#667085;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .pc-admin-sport-copy-v1 small{color:#98a2b3}
        .pc-admin-sport-status-v1{font-size:.68rem;font-weight:850;text-transform:uppercase;letter-spacing:.06em;color:#98a2b3}.pc-admin-sport-v1.is-enabled .pc-admin-sport-status-v1{color:#e2461e}
        .pc-admin-settings-note-v1{display:flex;align-items:flex-start;gap:.55rem;margin:0 1.5rem 1.1rem;padding:.75rem .85rem;border-radius:.8rem;background:#f8fafc;color:#475467;font-size:.78rem;line-height:1.45}.pc-admin-settings-note-v1 svg{width:1.05rem;height:1.05rem;flex:none;margin-top:.05rem}.dark .pc-admin-settings-note-v1{background:#171a20;color:#aeb7c5}
        .pc-admin-settings-actions-v1{display:flex;justify-content:flex-end;padding:1rem 1.5rem;border-top:1px solid #eaecf0}.dark .pc-admin-settings-actions-v1{border-color:#2c313b}
        .pc-admin-settings-actions-v1 .primary{border:0;border-radius:.8rem;background:#ff6338;color:#fff;font-weight:850;padding:.7rem 1.05rem;min-width:9rem;cursor:pointer;box-shadow:0 8px 20px rgba(255,99,56,.2)}.pc-admin-settings-actions-v1 .primary:disabled{opacity:.65;cursor:wait}
        @media(max-width:1000px){.pc-admin-settings-grid-v1{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:680px){.pc-admin-settings-heading-v1{flex-direction:column}.pc-admin-settings-count-v1{align-self:flex-start}.pc-admin-settings-grid-v1{grid-template-columns:1fr;padding-left:1rem;padding-right:1rem}.pc-admin-settings-toolbar-v1,.pc-admin-settings-actions-v1{padding-left:1rem;padding-right:1rem}.pc-admin-settings-note-v1{margin-left:1rem;margin-right:1rem}.pc-admin-settings-actions-v1 .primary{width:100%}}
    </style>
</x-filament-panels::page>
