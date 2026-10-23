<x-filament-panels::page>
    @php
        $pointServices = $this->plyrPointServices;
        $editingService = $plyrPointEditingKey ? ($pointServices[$plyrPointEditingKey] ?? null) : null;
    @endphp

    <div class="pc-admin-settings-v4">
        <section class="pc-settings-card-v4">
            <div class="pc-settings-head-v4">
                <div>
                    <h2>Sports availability</h2>
                    <p>Choose which sports are available across PLYRCARD.</p>
                </div>
                <div class="pc-settings-actions-v4">
                    <button type="button" class="ghost" wire:click="enableAllSports">Enable all</button>
                    <button type="button" class="ghost" wire:click="disableAllSports">Disable all</button>
                    <button type="button" class="primary" wire:click="saveSports" wire:loading.attr="disabled" wire:target="saveSports">Save</button>
                </div>
            </div>

            <div class="pc-sport-grid-v4">
                @foreach($this->sports as $sport)
                    @php($isEnabled = in_array($sport['key'], $enabledSports, true))
                    <label class="pc-sport-row-v4 {{ $isEnabled ? 'is-enabled' : '' }}" wire:key="admin-sport-{{ $sport['key'] }}">
                        <span>
                            <strong>{{ $sport['label'] }}</strong>
                            @if(! $sport['athlete'])<small>Coach only</small>@endif
                        </span>
                        <input type="checkbox" wire:model.live="enabledSports" value="{{ $sport['key'] }}">
                        <i class="pc-switch-v4" aria-hidden="true"><b></b></i>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="pc-settings-card-v4 pc-points-admin-v4">
            <div class="pc-settings-head-v4">
                <div>
                    <h2>PLYR Points services</h2>
                    <p>Turn services on or off. Open a service only when you need to edit details or samples.</p>
                </div>
                <button type="button" class="primary pc-add-service-v4" wire:click="createPlyrPointService">
                    <span>+</span> Add service
                </button>
            </div>

            <div class="pc-service-list-v4">
                @forelse($pointServices as $serviceKey => $service)
                    <div class="pc-service-row-v4" wire:key="plyr-points-service-{{ $serviceKey }}">
                        <div class="pc-service-icon-v4">{{ strtoupper(substr($service['name'] ?? 'P', 0, 1)) }}</div>
                        <button type="button" class="pc-service-main-v4" wire:click='editPlyrPointService(@js($serviceKey))'>
                            <strong>{{ $service['name'] }}</strong>
                            <span>{{ number_format((int) $service['points']) }} pts · {{ count($service['samples'] ?? []) }} {{ count($service['samples'] ?? []) === 1 ? 'sample' : 'samples' }}</span>
                        </button>
                        <button
                            type="button"
                            class="pc-service-toggle-v4 {{ ($service['enabled'] ?? true) ? 'is-on' : '' }}"
                            wire:click='togglePlyrPointService(@js($serviceKey))'
                            wire:loading.attr="disabled"
                            wire:target="togglePlyrPointService"
                            aria-label="Toggle {{ $service['name'] }}"
                        ><i></i><span>{{ ($service['enabled'] ?? true) ? 'On' : 'Off' }}</span></button>
                        <button type="button" class="pc-service-edit-v4" wire:click='editPlyrPointService(@js($serviceKey))'>Edit</button>
                    </div>
                @empty
                    <div class="pc-service-empty-v4">No PLYR Points services yet.</div>
                @endforelse
            </div>
        </section>
    </div>

    @if($plyrPointEditorOpen)
        <button type="button" class="pc-points-overlay-v4" wire:click="closePlyrPointEditor" aria-label="Close service editor"></button>
        <aside class="pc-points-drawer-v4" role="dialog" aria-modal="true" aria-label="PLYR Points service editor">
            <header class="pc-points-drawer-head-v4">
                <div>
                    <span>{{ $plyrPointEditingKey ? 'Edit service' : 'New service' }}</span>
                    <h2>{{ $plyrPointEditingKey ? ($editingService['name'] ?? 'PLYR Points service') : 'Add PLYR Points service' }}</h2>
                </div>
                <button type="button" wire:click="closePlyrPointEditor" aria-label="Close">×</button>
            </header>

            <div class="pc-points-drawer-body-v4">
                <section class="pc-points-editor-section-v4">
                    <div class="pc-points-editor-title-v4">
                        <h3>Service details</h3>
                        <button type="button" class="pc-service-toggle-v4 {{ $plyrPointEnabled ? 'is-on' : '' }}" wire:click="togglePlyrPointEditorEnabled">
                            <i></i><span>{{ $plyrPointEnabled ? 'On' : 'Off' }}</span>
                        </button>
                    </div>

                    <div class="pc-points-form-grid-v4">
                        <label class="wide">
                            <span>Name</span>
                            <input type="text" wire:model="plyrPointName" placeholder="Service name">
                            @error('plyrPointName')<small class="error">{{ $message }}</small>@enderror
                        </label>
                        <label>
                            <span>Points</span>
                            <input type="number" min="1" wire:model="plyrPointPoints">
                            @error('plyrPointPoints')<small class="error">{{ $message }}</small>@enderror
                        </label>
                        <label class="wide">
                            <span>Description</span>
                            <textarea rows="3" wire:model="plyrPointDescription" placeholder="Short player-facing description"></textarea>
                            @error('plyrPointDescription')<small class="error">{{ $message }}</small>@enderror
                        </label>
                        <label class="wide">
                            <span>Button label</span>
                            <input type="text" wire:model="plyrPointCta" placeholder="Request service">
                            @error('plyrPointCta')<small class="error">{{ $message }}</small>@enderror
                        </label>
                    </div>
                </section>

                @if($plyrPointEditingKey)
                    @php($samples = $editingService['samples'] ?? [])
                    <section class="pc-points-editor-section-v4">
                        <div class="pc-points-editor-title-v4">
                            <div>
                                <h3>Samples</h3>
                                <p>{{ count($samples) }} added</p>
                            </div>
                        </div>

                        <div class="pc-sample-list-v4">
                            @forelse($samples as $sample)
                                <div class="pc-sample-row-v4" wire:key="sample-{{ $sample['id'] ?? uniqid() }}">
                                    <div class="pc-sample-type-v4">{{ strtoupper((string) ($sample['type'] ?? 'file')) }}</div>
                                    <div class="pc-sample-copy-v4">
                                        <strong>{{ $sample['name'] ?? 'Sample' }}</strong>
                                        <span>{{ ($sample['type'] ?? '') === 'youtube' ? 'YouTube' : 'Uploaded media' }}</span>
                                    </div>
                                    @if(!empty($sample['url']))
                                        <a href="{{ $sample['url'] }}" target="_blank" rel="noopener">Open</a>
                                    @endif
                                    <button type="button" class="danger-link" wire:click='removePlyrPointSample(@js((string) ($sample["id"] ?? "")))' wire:confirm="Remove this sample?">Remove</button>
                                </div>
                            @empty
                                <div class="pc-sample-empty-v4">No samples yet.</div>
                            @endforelse
                        </div>

                        <div class="pc-sample-add-v4">
                            <div class="pc-sample-add-row-v4">
                                <div>
                                    <strong>YouTube</strong>
                                    <span>Paste a video, Shorts, live, or embed link.</span>
                                </div>
                                <input type="url" wire:model="plyrPointYoutube" placeholder="https://youtube.com/watch?v=...">
                                <button type="button" wire:click="addPlyrPointYoutubeSample" wire:loading.attr="disabled" wire:target="addPlyrPointYoutubeSample">Add</button>
                            </div>
                            @error('plyrPointYoutube')<small class="error">{{ $message }}</small>@enderror

                            <div class="pc-sample-add-row-v4">
                                <div>
                                    <strong>Image or video</strong>
                                    <span>Choose one or more files. 12 MB max each.</span>
                                </div>
                                <input type="file" wire:model="plyrPointUploads" multiple accept="image/*,video/mp4,video/quicktime,video/webm">
                                <button type="button" wire:click="uploadPlyrPointSamples" wire:loading.attr="disabled" wire:target="uploadPlyrPointSamples,plyrPointUploads">Upload</button>
                            </div>
                            @error('plyrPointUploads')<small class="error">{{ $message }}</small>@enderror
                            @error('plyrPointUploads.*')<small class="error">{{ $message }}</small>@enderror
                        </div>
                    </section>
                @else
                    <div class="pc-points-save-first-v4">Save the service first, then you can add image, video, or YouTube samples.</div>
                @endif
            </div>

            <footer class="pc-points-drawer-foot-v4">
                @if($plyrPointEditingKey && ($editingService['custom'] ?? false))
                    <button type="button" class="danger" wire:click="deletePlyrPointService" wire:confirm="Delete this service and its uploaded samples?">Delete service</button>
                @else
                    <span></span>
                @endif
                <div>
                    <button type="button" class="ghost" wire:click="closePlyrPointEditor">Cancel</button>
                    <button type="button" class="primary" wire:click="savePlyrPointService" wire:loading.attr="disabled" wire:target="savePlyrPointService">
                        <span wire:loading.remove wire:target="savePlyrPointService">Save service</span>
                        <span wire:loading wire:target="savePlyrPointService">Saving...</span>
                    </button>
                </div>
            </footer>
        </aside>
    @endif

    <style>
        .pc-admin-settings-v4{max-width:1080px;margin:0 auto;padding:.25rem 0 1.5rem;display:grid;gap:1rem;color:#101828}
        .dark .pc-admin-settings-v4{color:#f8fafc}
        .pc-settings-card-v4{background:#fff;border:1px solid #e4e7ec;border-radius:1rem;overflow:hidden;box-shadow:0 1px 2px rgba(16,24,40,.03)}
        .dark .pc-settings-card-v4{background:#111318;border-color:#2b3038}
        .pc-settings-head-v4{min-height:4.75rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid #eaecf0}
        .dark .pc-settings-head-v4{border-color:#272c34}
        .pc-settings-head-v4 h2{margin:0;font-size:1rem;font-weight:850;letter-spacing:-.015em}
        .pc-settings-head-v4 p{margin:.24rem 0 0;color:#667085;font-size:.75rem;line-height:1.35}
        .dark .pc-settings-head-v4 p{color:#98a2b3}
        .pc-settings-actions-v4{display:flex;gap:.4rem;align-items:center}
        .pc-settings-head-v4 button,.pc-points-drawer-v4 button{font:inherit}
        .primary,.ghost,.danger{height:2.3rem;border-radius:.65rem;padding:0 .8rem;font-size:.72rem;font-weight:800;cursor:pointer}
        .primary{border:1px solid #ff6338;background:#ff6338;color:#fff}.primary:hover{background:#ef522d;border-color:#ef522d}
        .ghost{border:1px solid #d0d5dd;background:#fff;color:#344054}.dark .ghost{background:#17191f;border-color:#343943;color:#e5e7eb}
        .danger{border:1px solid #fda29b;background:#fff;color:#b42318}.dark .danger{background:#17191f;border-color:#7a271a;color:#fda29b}
        button:disabled{opacity:.55;cursor:wait}

        .pc-sport-grid-v4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr))}
        .pc-sport-row-v4{position:relative;display:flex;align-items:center;justify-content:space-between;gap:.65rem;min-height:3.15rem;padding:.62rem .8rem;border-right:1px solid #f0f1f3;border-bottom:1px solid #f0f1f3;background:#fff;cursor:pointer}
        .pc-sport-row-v4:nth-child(4n){border-right:0}.dark .pc-sport-row-v4{background:#111318;border-color:#252a31}
        .pc-sport-row-v4>span{min-width:0;display:flex;align-items:center;gap:.35rem}.pc-sport-row-v4 strong{font-size:.75rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pc-sport-row-v4 small{padding:.1rem .3rem;border-radius:.3rem;background:#f2f4f7;color:#98a2b3;font-size:.55rem;font-weight:800;text-transform:uppercase}.pc-sport-row-v4 input{position:absolute;opacity:0;pointer-events:none}
        .pc-switch-v4{width:1.8rem;height:1.02rem;padding:.11rem;display:flex;align-items:center;border-radius:999px;background:#d0d5dd;transition:.15s}.pc-switch-v4 b{width:.8rem;height:.8rem;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(16,24,40,.18);transition:.15s}.pc-sport-row-v4.is-enabled .pc-switch-v4{background:#ff6338}.pc-sport-row-v4.is-enabled .pc-switch-v4 b{transform:translateX(.77rem)}

        .pc-add-service-v4{display:inline-flex;align-items:center;gap:.35rem}.pc-add-service-v4 span{font-size:1rem;line-height:1}
        .pc-service-list-v4{display:grid}
        .pc-service-row-v4{display:grid;grid-template-columns:2.4rem minmax(0,1fr) auto auto;gap:.75rem;align-items:center;min-height:4.6rem;padding:.7rem 1rem;border-bottom:1px solid #eef0f3;background:#fff}.pc-service-row-v4:last-child{border-bottom:0}.dark .pc-service-row-v4{background:#111318;border-color:#252a31}
        .pc-service-row-v4:hover{background:#fafafa}.dark .pc-service-row-v4:hover{background:#15181e}
        .pc-service-icon-v4{width:2.35rem;height:2.35rem;border-radius:.7rem;display:grid;place-items:center;background:#fff0eb;color:#c2410c;font-size:.8rem;font-weight:900}
        .dark .pc-service-icon-v4{background:rgba(255,99,56,.14);color:#ff8f73}
        .pc-service-main-v4{min-width:0;border:0;background:transparent;color:inherit;text-align:left;padding:0;cursor:pointer}.pc-service-main-v4 strong{display:block;font-size:.82rem;font-weight:850;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pc-service-main-v4 span{display:block;margin-top:.18rem;color:#667085;font-size:.68rem}.dark .pc-service-main-v4 span{color:#98a2b3}
        .pc-service-toggle-v4{min-width:4.35rem;height:2rem;padding:.18rem .45rem .18rem .2rem;border:1px solid #d0d5dd;border-radius:999px;background:#f2f4f7;color:#667085;display:inline-flex;align-items:center;gap:.35rem;cursor:pointer;font-size:.65rem;font-weight:850}.pc-service-toggle-v4 i{width:1.35rem;height:1.35rem;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(16,24,40,.18)}.pc-service-toggle-v4.is-on{border-color:#ff6338;background:#ff6338;color:#fff}.pc-service-toggle-v4.is-on i{background:#fff}.dark .pc-service-toggle-v4:not(.is-on){background:#20242c;border-color:#343943;color:#98a2b3}
        .pc-service-edit-v4{height:2rem;border:1px solid #d0d5dd;border-radius:.55rem;background:#fff;color:#344054;padding:0 .65rem;font-size:.68rem;font-weight:800;cursor:pointer}.dark .pc-service-edit-v4{background:#17191f;border-color:#343943;color:#e5e7eb}
        .pc-service-empty-v4{padding:2rem;text-align:center;color:#98a2b3;font-size:.78rem}

        .pc-points-overlay-v4{position:fixed;inset:0;z-index:10050;border:0;background:rgba(15,23,42,.5);backdrop-filter:blur(3px);cursor:default}
        .pc-points-drawer-v4{position:fixed;z-index:10051;top:0;right:0;bottom:0;width:min(560px,100vw);display:grid;grid-template-rows:auto 1fr auto;background:#f8fafc;color:#101828;border-left:1px solid #e4e7ec;box-shadow:-24px 0 70px rgba(15,23,42,.22);animation:pcPointsDrawerIn .18s ease-out both}
        .dark .pc-points-drawer-v4{background:#0f1217;color:#f8fafc;border-color:#2c313b}
        @keyframes pcPointsDrawerIn{from{transform:translateX(30px);opacity:.5}to{transform:none;opacity:1}}
        .pc-points-drawer-head-v4{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.15rem;background:#17191f;color:#fff;border-bottom:1px solid #242933}.pc-points-drawer-head-v4 span{display:block;color:#ff8b6e;font-size:.63rem;font-weight:850;text-transform:uppercase;letter-spacing:.08em}.pc-points-drawer-head-v4 h2{margin:.18rem 0 0;font-size:1.05rem;font-weight:850}.pc-points-drawer-head-v4>button{width:2.2rem;height:2.2rem;border:1px solid #343943;border-radius:.65rem;background:#20242c;color:#fff;font-size:1.35rem;cursor:pointer}
        .pc-points-drawer-body-v4{overflow:auto;padding:1rem;display:grid;gap:.85rem;align-content:start}
        .pc-points-editor-section-v4{padding:1rem;border:1px solid #e4e7ec;border-radius:.9rem;background:#fff}.dark .pc-points-editor-section-v4{background:#17191f;border-color:#303641}
        .pc-points-editor-title-v4{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.85rem}.pc-points-editor-title-v4 h3{margin:0;font-size:.85rem;font-weight:850}.pc-points-editor-title-v4 p{margin:.15rem 0 0;color:#667085;font-size:.65rem}.dark .pc-points-editor-title-v4 p{color:#98a2b3}
        .pc-points-form-grid-v4{display:grid;grid-template-columns:1fr 7rem;gap:.75rem}.pc-points-form-grid-v4 label{display:grid;gap:.35rem}.pc-points-form-grid-v4 label.wide{grid-column:1/-1}.pc-points-form-grid-v4 label>span{font-size:.67rem;font-weight:800;color:#475467}.dark .pc-points-form-grid-v4 label>span{color:#cbd5e1}.pc-points-form-grid-v4 input,.pc-points-form-grid-v4 textarea{width:100%;border:1px solid #d0d5dd;border-radius:.65rem;background:#fff;color:#101828;padding:.58rem .65rem;font:inherit;font-size:.74rem;outline:none}.dark .pc-points-form-grid-v4 input,.dark .pc-points-form-grid-v4 textarea{background:#111318;color:#f8fafc;border-color:#343943}.pc-points-form-grid-v4 input:focus,.pc-points-form-grid-v4 textarea:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.1)}
        .error{color:#b42318!important;font-size:.64rem!important;font-weight:750}
        .pc-sample-list-v4{display:grid;border:1px solid #eaecf0;border-radius:.72rem;overflow:hidden}.dark .pc-sample-list-v4{border-color:#303641}.pc-sample-row-v4{display:grid;grid-template-columns:3.7rem minmax(0,1fr) auto auto;gap:.6rem;align-items:center;padding:.6rem .65rem;border-bottom:1px solid #f0f1f3}.pc-sample-row-v4:last-child{border-bottom:0}.dark .pc-sample-row-v4{border-color:#252a31}.pc-sample-type-v4{font-size:.56rem;font-weight:900;color:#c2410c}.pc-sample-copy-v4{min-width:0}.pc-sample-copy-v4 strong{display:block;font-size:.69rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pc-sample-copy-v4 span{display:block;margin-top:.12rem;color:#98a2b3;font-size:.6rem}.pc-sample-row-v4 a,.danger-link{border:0;background:transparent;color:#475467;font-size:.63rem;font-weight:800;text-decoration:none;cursor:pointer}.dark .pc-sample-row-v4 a{color:#cbd5e1}.danger-link{color:#b42318}.pc-sample-empty-v4{padding:.85rem;color:#98a2b3;text-align:center;font-size:.68rem}
        .pc-sample-add-v4{display:grid;gap:.55rem;margin-top:.75rem}.pc-sample-add-row-v4{display:grid;grid-template-columns:minmax(8rem,.8fr) minmax(0,1.25fr) auto;gap:.55rem;align-items:center;padding:.65rem;border:1px solid #eaecf0;border-radius:.72rem;background:#f9fafb}.dark .pc-sample-add-row-v4{background:#111318;border-color:#303641}.pc-sample-add-row-v4 strong{display:block;font-size:.68rem}.pc-sample-add-row-v4 span{display:block;margin-top:.14rem;color:#98a2b3;font-size:.58rem;line-height:1.3}.pc-sample-add-row-v4 input{min-width:0;width:100%;border:1px solid #d0d5dd;border-radius:.55rem;background:#fff;color:#101828;padding:.5rem .55rem;font-size:.68rem}.dark .pc-sample-add-row-v4 input{background:#17191f;color:#f8fafc;border-color:#343943}.pc-sample-add-row-v4 button{height:2rem;border:1px solid #ff6338;border-radius:.55rem;background:#ff6338;color:#fff;padding:0 .65rem;font-size:.65rem;font-weight:850;cursor:pointer}
        .pc-points-save-first-v4{padding:1rem;border:1px dashed #d0d5dd;border-radius:.8rem;background:#fff;color:#667085;text-align:center;font-size:.7rem}.dark .pc-points-save-first-v4{background:#17191f;border-color:#343943;color:#98a2b3}
        .pc-points-drawer-foot-v4{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 1rem;border-top:1px solid #e4e7ec;background:#fff}.dark .pc-points-drawer-foot-v4{background:#111318;border-color:#2c313b}.pc-points-drawer-foot-v4>div{display:flex;gap:.45rem}

        @media(max-width:900px){.pc-sport-grid-v4{grid-template-columns:repeat(2,minmax(0,1fr))}.pc-sport-row-v4:nth-child(4n){border-right:1px solid #f0f1f3}.pc-sport-row-v4:nth-child(2n){border-right:0}}
        @media(max-width:640px){.pc-settings-head-v4{align-items:flex-start;flex-direction:column}.pc-settings-actions-v4{width:100%;flex-wrap:wrap}.pc-add-service-v4{width:100%;justify-content:center}.pc-service-row-v4{grid-template-columns:2.4rem minmax(0,1fr) auto}.pc-service-edit-v4{display:none}.pc-points-form-grid-v4{grid-template-columns:1fr}.pc-points-form-grid-v4 label.wide{grid-column:auto}.pc-sample-add-row-v4{grid-template-columns:1fr}.pc-sample-add-row-v4 button{width:100%}.pc-sample-row-v4{grid-template-columns:3.4rem minmax(0,1fr) auto}.pc-sample-row-v4 a{display:none}}
        @media(max-width:480px){.pc-sport-grid-v4{grid-template-columns:1fr}.pc-sport-row-v4{border-right:0!important}.pc-service-row-v4{padding-inline:.75rem}.pc-points-drawer-foot-v4{align-items:stretch;flex-direction:column}.pc-points-drawer-foot-v4>div{display:grid;grid-template-columns:1fr 1fr}.pc-points-drawer-foot-v4 .danger{width:100%}}
    </style>
</x-filament-panels::page>