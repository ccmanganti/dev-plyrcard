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

        <section class="pc-setting-section-v2 pc-points-admin-v3">
            <div class="pc-setting-head-v2">
                <div class="pc-setting-title-v2">
                    <h2>PLYR Points services</h2>
                    <span>{{ count($plyrPointEnabled) }} enabled</span>
                    <p>Turn services on or off and manage the sample media players see before requesting work.</p>
                </div>

                <div class="pc-setting-actions-v2">
                    <button type="button" class="primary" wire:click="savePlyrPointServices" wire:loading.attr="disabled" wire:target="savePlyrPointServices">
                        <span wire:loading.remove wire:target="savePlyrPointServices">Save services</span>
                        <span wire:loading wire:target="savePlyrPointServices">Saving...</span>
                    </button>
                </div>
            </div>

            <div class="pc-points-service-list-v3">
                @foreach($this->plyrPointServices as $serviceKey => $service)
                    @php($isEnabled = in_array($serviceKey, $plyrPointEnabled, true))
                    <article class="pc-points-service-admin-v3" wire:key="plyr-points-admin-{{ $serviceKey }}">
                        <div class="pc-points-service-admin-head-v3">
                            <div class="pc-points-service-admin-copy-v3">
                                <div class="pc-points-service-admin-title-v3">
                                    <strong>{{ $service['name'] }}</strong>
                                    <span>{{ number_format((int) $service['points']) }} pts</span>
                                </div>
                                <p>{{ $service['description'] }}</p>
                            </div>

                            <label class="pc-points-service-toggle-v3 {{ $isEnabled ? 'is-enabled' : '' }}">
                                <input type="checkbox" wire:model.live="plyrPointEnabled" value="{{ $serviceKey }}">
                                <span class="pc-switch-v2" aria-hidden="true"><i></i></span>
                                <small>{{ $isEnabled ? 'Available' : 'Hidden' }}</small>
                            </label>
                        </div>

                        <div class="pc-points-sample-section-v3">
                            <div class="pc-points-sample-head-v3">
                                <div>
                                    <strong>Samples</strong>
                                    <span>{{ count($service['samples'] ?? []) }} added</span>
                                </div>
                                <small>Images, videos, and YouTube embeds can be mixed. Players see them as one slider.</small>
                            </div>

                            <div class="pc-points-sample-grid-v3">
                                @forelse(($service['samples'] ?? []) as $sample)
                                    <div class="pc-points-sample-card-v3" wire:key="plyr-points-sample-{{ $serviceKey }}-{{ $sample['id'] }}">
                                        <div class="pc-points-sample-preview-v3">
                                            @if(($sample['type'] ?? '') === 'image')
                                                <img src="{{ $sample['url'] ?? '' }}" alt="{{ $service['name'] }} sample">
                                            @elseif(($sample['type'] ?? '') === 'video')
                                                <video src="{{ $sample['url'] ?? '' }}" controls preload="metadata"></video>
                                            @elseif(($sample['type'] ?? '') === 'youtube')
                                                <iframe src="{{ $sample['embed_url'] ?? '' }}" title="{{ $service['name'] }} YouTube sample" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                            @endif
                                        </div>
                                        <div class="pc-points-sample-card-copy-v3">
                                            <div>
                                                <strong>{{ $sample['name'] ?? ucfirst((string) ($sample['type'] ?? 'sample')) }}</strong>
                                                <span>{{ strtoupper((string) ($sample['type'] ?? 'sample')) }}</span>
                                            </div>
                                            <button type="button" wire:click='removePlyrPointSample(@js($serviceKey), @js((string) ($sample["id"] ?? "")))' wire:confirm="Remove this sample?">Remove</button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="pc-points-sample-empty-v3">No sample media added yet.</div>
                                @endforelse
                            </div>

                            <div class="pc-points-sample-add-v3">
                                <div class="pc-points-sample-add-card-v3">
                                    <label>
                                        <span>YouTube URL</span>
                                        <div class="pc-points-youtube-row-v3">
                                            <input type="url" wire:model.defer="plyrPointYoutube.{{ $serviceKey }}" placeholder="https://youtube.com/watch?v=...">
                                            <button type="button" wire:click='addPlyrPointYoutubeSample(@js($serviceKey))' wire:loading.attr="disabled" wire:target="addPlyrPointYoutubeSample">
                                                <span wire:loading.remove wire:target="addPlyrPointYoutubeSample">Add YouTube</span>
                                                <span wire:loading wire:target="addPlyrPointYoutubeSample">Adding...</span>
                                            </button>
                                        </div>
                                    </label>
                                    @error('plyrPointYoutube.' . $serviceKey)<div class="pc-points-field-error-v3">{{ $message }}</div>@enderror
                                </div>

                                <div class="pc-points-sample-add-card-v3">
                                    <label>
                                        <span>Upload image or video</span>
                                        <input type="file" wire:model="plyrPointUploads.{{ $serviceKey }}" accept="image/*,video/mp4,video/quicktime,video/webm,video/x-m4v" multiple>
                                    </label>
                                    @error('plyrPointUploads.' . $serviceKey)<div class="pc-points-field-error-v3">{{ $message }}</div>@enderror
                                    @error('plyrPointUploads.' . $serviceKey . '.*')<div class="pc-points-field-error-v3">{{ $message }}</div>@enderror

                                    @if(! empty($plyrPointUploads[$serviceKey] ?? []))
                                        <button type="button" class="pc-points-upload-btn-v3" wire:click='uploadPlyrPointSamples(@js($serviceKey))' wire:loading.attr="disabled" wire:target="uploadPlyrPointSamples,plyrPointUploads.{{ $serviceKey }}">
                                            <span wire:loading.remove wire:target="uploadPlyrPointSamples">Upload selected</span>
                                            <span wire:loading wire:target="uploadPlyrPointSamples">Uploading...</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>

    <style>
        .pc-admin-settings-v2{max-width:1180px;margin:0 auto;padding:.25rem 0 1.5rem;display:grid;gap:1rem}
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
        .pc-sport-row-v2.is-enabled .pc-switch-v2,.pc-points-service-toggle-v3.is-enabled .pc-switch-v2{background:#ff6338}
        .pc-sport-row-v2.is-enabled .pc-switch-v2 i,.pc-points-service-toggle-v3.is-enabled .pc-switch-v2 i{transform:translateX(.8rem)}

        .pc-points-service-list-v3{display:grid;gap:0}
        .pc-points-service-admin-v3{padding:1rem;border-bottom:1px solid #eef0f3;display:grid;gap:1rem}
        .pc-points-service-admin-v3:last-child{border-bottom:0}.dark .pc-points-service-admin-v3{border-color:#252a33}
        .pc-points-service-admin-head-v3{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}
        .pc-points-service-admin-copy-v3{min-width:0}.pc-points-service-admin-title-v3{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap}
        .pc-points-service-admin-title-v3 strong{font-size:.92rem;color:#101828}.dark .pc-points-service-admin-title-v3 strong{color:#f8fafc}
        .pc-points-service-admin-title-v3 span{padding:.18rem .45rem;border-radius:999px;background:#fff0eb;color:#c2410c;font-size:.66rem;font-weight:850}
        .pc-points-service-admin-copy-v3 p{margin:.3rem 0 0;color:#667085;font-size:.76rem}.dark .pc-points-service-admin-copy-v3 p{color:#98a2b3}
        .pc-points-service-toggle-v3{position:relative;display:flex;align-items:center;gap:.45rem;cursor:pointer;flex:none}.pc-points-service-toggle-v3 input{position:absolute;opacity:0;pointer-events:none}.pc-points-service-toggle-v3 small{font-size:.68rem;font-weight:750;color:#667085}.dark .pc-points-service-toggle-v3 small{color:#98a2b3}

        .pc-points-sample-section-v3{display:grid;gap:.75rem;padding:.85rem;border:1px solid #e8ebef;border-radius:.8rem;background:#fafbfc}.dark .pc-points-sample-section-v3{background:#15181e;border-color:#2c313b}
        .pc-points-sample-head-v3{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.pc-points-sample-head-v3>div{display:flex;align-items:center;gap:.45rem}.pc-points-sample-head-v3 strong{font-size:.78rem}.pc-points-sample-head-v3 span{padding:.13rem .4rem;border-radius:999px;background:#eef2f6;color:#667085;font-size:.62rem;font-weight:800}.pc-points-sample-head-v3 small{max-width:32rem;text-align:right;color:#667085;font-size:.67rem;line-height:1.35}.dark .pc-points-sample-head-v3 small{color:#98a2b3}
        .pc-points-sample-grid-v3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}.pc-points-sample-card-v3{border:1px solid #e3e7ec;border-radius:.7rem;overflow:hidden;background:#fff}.dark .pc-points-sample-card-v3{background:#111318;border-color:#303641}
        .pc-points-sample-preview-v3{aspect-ratio:16/9;background:#0d1117;display:grid;place-items:center;overflow:hidden}.pc-points-sample-preview-v3 img,.pc-points-sample-preview-v3 video,.pc-points-sample-preview-v3 iframe{width:100%;height:100%;object-fit:contain;border:0;background:#0d1117}
        .pc-points-sample-card-copy-v3{display:flex;align-items:center;justify-content:space-between;gap:.55rem;padding:.55rem}.pc-points-sample-card-copy-v3>div{min-width:0}.pc-points-sample-card-copy-v3 strong{display:block;font-size:.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pc-points-sample-card-copy-v3 span{display:block;margin-top:.12rem;color:#98a2b3;font-size:.56rem;font-weight:800}.pc-points-sample-card-copy-v3 button{border:0;background:transparent;color:#b42318;font-size:.62rem;font-weight:800;cursor:pointer}
        .pc-points-sample-empty-v3{grid-column:1/-1;padding:1rem;border:1px dashed #d0d5dd;border-radius:.65rem;color:#98a2b3;text-align:center;font-size:.72rem}.dark .pc-points-sample-empty-v3{border-color:#343943}
        .pc-points-sample-add-v3{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}.pc-points-sample-add-card-v3{display:grid;gap:.45rem;padding:.7rem;border:1px solid #e3e7ec;border-radius:.7rem;background:#fff}.dark .pc-points-sample-add-card-v3{background:#111318;border-color:#303641}.pc-points-sample-add-card-v3 label{display:grid;gap:.35rem}.pc-points-sample-add-card-v3 label>span{font-size:.68rem;font-weight:800;color:#344054}.dark .pc-points-sample-add-card-v3 label>span{color:#e5e7eb}.pc-points-sample-add-card-v3 input[type="url"],.pc-points-sample-add-card-v3 input[type="file"]{width:100%;min-height:2.2rem;border:1px solid #d0d5dd;border-radius:.55rem;background:#fff;color:#101828;padding:.42rem .55rem;font-size:.7rem}.dark .pc-points-sample-add-card-v3 input{background:#17191f!important;color:#f8fafc!important;border-color:#343943!important}
        .pc-points-youtube-row-v3{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.45rem}.pc-points-youtube-row-v3 button,.pc-points-upload-btn-v3{border:1px solid #ff6338;border-radius:.55rem;background:#ff6338;color:#fff;padding:.45rem .65rem;font-size:.68rem;font-weight:800;cursor:pointer}.pc-points-upload-btn-v3{justify-self:start}.pc-points-field-error-v3{color:#b42318;font-size:.66rem;font-weight:700}

        @media(max-width:1050px){.pc-sport-grid-v2{grid-template-columns:repeat(3,minmax(0,1fr))}.pc-sport-row-v2:nth-child(4n){border-right:1px solid #f0f1f3}.pc-sport-row-v2:nth-child(3n){border-right:0}.pc-points-sample-grid-v3{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:760px){.pc-setting-head-v2,.pc-points-service-admin-head-v3,.pc-points-sample-head-v3{align-items:flex-start;flex-direction:column}.pc-setting-actions-v2{width:100%}.pc-setting-actions-v2 .primary{margin-left:auto}.pc-sport-grid-v2{grid-template-columns:repeat(2,minmax(0,1fr))}.pc-sport-row-v2:nth-child(3n){border-right:1px solid #f0f1f3}.pc-sport-row-v2:nth-child(2n){border-right:0}.pc-points-sample-head-v3 small{text-align:left}.pc-points-sample-add-v3{grid-template-columns:1fr}}
        @media(max-width:560px){.pc-points-sample-grid-v3{grid-template-columns:1fr}.pc-points-youtube-row-v3{grid-template-columns:1fr}}
        @media(max-width:480px){.pc-setting-actions-v2{display:grid;grid-template-columns:1fr 1fr auto}.pc-setting-actions-v2 .primary{margin-left:0}.pc-sport-grid-v2{grid-template-columns:1fr}.pc-sport-row-v2{border-right:0!important}}
    </style>
</x-filament-panels::page>