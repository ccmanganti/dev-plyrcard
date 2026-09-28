<x-filament-panels::page>
    <div class="pc-admin-settings-v2">
        <section class="pc-setting-section-v2">
            <div class="pc-setting-head-v2">
                <div class="pc-setting-title-v2">
                    <h2>Sports availability</h2>
                    <span>{{ count($enabledSports) }} enabled</span>
                    <p>Choose which sports can be selected across PLYRCARD. Existing records are not changed.</p>
                </div>

                <div class="pc-setting-actions-v2">
                    <button type="button" class="secondary" wire:click="enableAllSports">Enable all</button>
                    <button type="button" class="secondary" wire:click="disableAllSports">Disable all</button>
                    <button type="button" class="primary" wire:click="saveSports" wire:loading.attr="disabled" wire:target="saveSports">
                        <span wire:loading.remove wire:target="saveSports">Save</span>
                        <span wire:loading wire:target="saveSports">Saving...</span>
                    </button>
                </div>
            </div>

            <div class="pc-sport-grid-v2">
                @foreach($this->sports as $sport)
                    @php($isEnabled = in_array($sport['key'], $enabledSports, true))
                    <label
                        class="pc-sport-row-v2 {{ $isEnabled ? 'is-enabled' : '' }}"
                        wire:key="admin-sport-{{ $sport['key'] }}"
                    >
                        <span class="pc-sport-name-v2">
                            <strong>{{ $sport['label'] }}</strong>
                            @if(! $sport['athlete'])
                                <small>Coach only</small>
                            @endif
                        </span>

                        <input
                            type="checkbox"
                            wire:model.live="enabledSports"
                            value="{{ $sport['key'] }}"
                        >

                        <span class="pc-switch-v2" aria-hidden="true"><i></i></span>
                    </label>
                @endforeach
            </div>
        </section>
    </div>

    <style>
        .pc-admin-settings-v2{max-width:1180px;margin:0 auto;padding:.25rem 0 1.5rem}
        .pc-setting-section-v2{background:#fff;border:1px solid #e5e7eb;border-radius:.85rem;overflow:hidden}
        .dark .pc-setting-section-v2{background:#111318;border-color:#2c313b}

        .pc-setting-head-v2{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;border-bottom:1px solid #eaecf0}
        .dark .pc-setting-head-v2{border-color:#2c313b}
        .pc-setting-title-v2{min-width:0}
        .pc-setting-title-v2 h2{display:inline;margin:0;font-size:1rem;font-weight:800;color:#101828}
        .dark .pc-setting-title-v2 h2{color:#f3f4f6}
        .pc-setting-title-v2>span{display:inline-block;margin-left:.45rem;padding:.14rem .42rem;border-radius:999px;background:#f2f4f7;color:#667085;font-size:.68rem;font-weight:700;vertical-align:2px}
        .dark .pc-setting-title-v2>span{background:#20242c;color:#aeb7c5}
        .pc-setting-title-v2 p{margin:.2rem 0 0;color:#667085;font-size:.76rem;line-height:1.35}
        .dark .pc-setting-title-v2 p{color:#98a2b3}

        .pc-setting-actions-v2{display:flex;align-items:center;gap:.4rem;flex:none}
        .pc-setting-actions-v2 button{height:2rem;border-radius:.55rem;padding:0 .65rem;font-size:.73rem;font-weight:750;cursor:pointer;white-space:nowrap}
        .pc-setting-actions-v2 .secondary{border:1px solid #d0d5dd;background:#fff;color:#344054}
        .dark .pc-setting-actions-v2 .secondary{background:#17191f;border-color:#343943;color:#e5e7eb}
        .pc-setting-actions-v2 .primary{border:1px solid #ff6338;background:#ff6338;color:#fff;min-width:4.4rem}
        .pc-setting-actions-v2 .primary:disabled{opacity:.6;cursor:wait}

        .pc-sport-grid-v2{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;border-top:0}
        .pc-sport-row-v2{position:relative;display:flex;align-items:center;justify-content:space-between;gap:.7rem;min-height:3rem;padding:.55rem .8rem;border-right:1px solid #f0f1f3;border-bottom:1px solid #f0f1f3;cursor:pointer;background:#fff}
        .pc-sport-row-v2:nth-child(4n){border-right:0}
        .dark .pc-sport-row-v2{background:#111318;border-color:#252a33}
        .pc-sport-row-v2:hover{background:#fafafa}
        .dark .pc-sport-row-v2:hover{background:#15181e}
        .pc-sport-row-v2 input{position:absolute;opacity:0;pointer-events:none}

        .pc-sport-name-v2{min-width:0;display:flex;align-items:center;gap:.35rem}
        .pc-sport-name-v2 strong{font-size:.78rem;font-weight:750;color:#344054;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .dark .pc-sport-name-v2 strong{color:#e5e7eb}
        .pc-sport-name-v2 small{flex:none;padding:.1rem .3rem;border-radius:.35rem;background:#f2f4f7;color:#98a2b3;font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.025em}
        .dark .pc-sport-name-v2 small{background:#20242c;color:#8d98a8}

        .pc-switch-v2{flex:none;width:1.85rem;height:1.05rem;border-radius:999px;background:#d0d5dd;padding:.12rem;display:flex;align-items:center;transition:.16s ease}
        .pc-switch-v2 i{display:block;width:.81rem;height:.81rem;border-radius:999px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.18);transition:.16s ease}
        .pc-sport-row-v2.is-enabled .pc-switch-v2{background:#ff6338}
        .pc-sport-row-v2.is-enabled .pc-switch-v2 i{transform:translateX(.8rem)}

        @media(max-width:1050px){
            .pc-sport-grid-v2{grid-template-columns:repeat(3,minmax(0,1fr))}
            .pc-sport-row-v2:nth-child(4n){border-right:1px solid #f0f1f3}
            .pc-sport-row-v2:nth-child(3n){border-right:0}
        }
        @media(max-width:760px){
            .pc-setting-head-v2{align-items:flex-start;flex-direction:column}
            .pc-setting-actions-v2{width:100%}
            .pc-setting-actions-v2 .primary{margin-left:auto}
            .pc-sport-grid-v2{grid-template-columns:repeat(2,minmax(0,1fr))}
            .pc-sport-row-v2:nth-child(3n){border-right:1px solid #f0f1f3}
            .pc-sport-row-v2:nth-child(2n){border-right:0}
        }
        @media(max-width:480px){
            .pc-setting-actions-v2{display:grid;grid-template-columns:1fr 1fr auto}
            .pc-setting-actions-v2 .primary{margin-left:0}
            .pc-sport-grid-v2{grid-template-columns:1fr}
            .pc-sport-row-v2{border-right:0!important}
        }
    </style>
</x-filament-panels::page>