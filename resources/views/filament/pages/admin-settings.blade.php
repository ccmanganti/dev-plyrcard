<div class="pc-admin-settings-livewire-root-v7">
<x-filament-panels::page>
    @php
        $pointServices = $this->plyrPointServices;
        $pointsBaseUrl = url('/admin/plyr-points/services');
    @endphp

    <div class="pc-admin-settings-v7">
        <section class="pc-settings-card-v7">
            <div class="pc-settings-head-v7">
                <div>
                    <h2>Sports availability</h2>
                    <p>Choose which sports are available across PLYRCARD.</p>
                </div>
                <div class="pc-settings-actions-v7">
                    <button type="button" class="pc-btn-v7 ghost" wire:click="enableAllSports">Enable all</button>
                    <button type="button" class="pc-btn-v7 ghost" wire:click="disableAllSports">Disable all</button>
                    <button type="button" class="pc-btn-v7 primary" wire:click="saveSports" wire:loading.attr="disabled" wire:target="saveSports">Save</button>
                </div>
            </div>

            <div class="pc-sport-grid-v7">
                @foreach($this->sports as $sport)
                    @php($isEnabled = in_array($sport['key'], $enabledSports, true))
                    <label class="pc-sport-row-v7 {{ $isEnabled ? 'is-enabled' : '' }}" wire:key="admin-sport-{{ $sport['key'] }}">
                        <span>
                            <strong>{{ $sport['label'] }}</strong>
                            @if(! $sport['athlete'])<small>Coach only</small>@endif
                        </span>
                        <input type="checkbox" wire:model.live="enabledSports" value="{{ $sport['key'] }}">
                        <i class="pc-switch-v7" aria-hidden="true"><b></b></i>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="pc-settings-card-v7" id="plyr-points-services">
            <div class="pc-points-head-v7">
                <div>
                    <h2>PLYR Points services</h2>
                    <p>Turn services on or off, edit details, manage inclusions, and add sample media.</p>
                </div>
                <button type="button" class="pc-btn-v7 primary" data-plyr-service-new>+ Add service</button>
            </div>

            @if(! $this->plyrPointDatabaseReady)
                <div class="pc-points-alert-v7">Run the PLYR Points migration first. Database service controls are unavailable until then.</div>
            @endif

            <div class="pc-inline-notice-v7" data-plyr-notice hidden></div>

            <div class="pc-service-list-v7" data-plyr-service-list>
                @forelse($pointServices as $service)
                    <article class="pc-service-row-v7" data-service-row="{{ $service['id'] ?? '' }}">
                        <div class="pc-service-icon-v7">{{ strtoupper(substr($service['name'] ?? 'P', 0, 1)) }}</div>
                        <div class="pc-service-copy-v7">
                            <strong data-service-name>{{ $service['name'] }}</strong>
                            <span data-service-meta>
                                {{ number_format((int) $service['points']) }} pts
                                · {{ count($service['inclusions'] ?? []) }} {{ count($service['inclusions'] ?? []) === 1 ? 'inclusion' : 'inclusions' }}
                                · {{ count($service['samples'] ?? []) }} {{ count($service['samples'] ?? []) === 1 ? 'sample' : 'samples' }}
                            </span>
                        </div>

                        @if(!empty($service['id']))
                            <button
                                type="button"
                                class="pc-service-toggle-v7 {{ ($service['enabled'] ?? true) ? 'is-on' : '' }}"
                                data-plyr-toggle="{{ $service['id'] }}"
                                aria-pressed="{{ ($service['enabled'] ?? true) ? 'true' : 'false' }}"
                            >
                                <i></i><span>{{ ($service['enabled'] ?? true) ? 'On' : 'Off' }}</span>
                            </button>
                            <button type="button" class="pc-service-edit-v7" data-plyr-service-edit="{{ $service['id'] }}">Edit</button>
                        @else
                            <span class="pc-service-migrate-v7">Migration required</span>
                        @endif
                    </article>
                @empty
                    <div class="pc-service-empty-v7" data-plyr-service-empty>No PLYR Points services yet.</div>
                @endforelse
            </div>
        </section>
    </div>

    <button type="button" class="pc-points-overlay-v7" data-plyr-service-close aria-label="Close service editor" tabindex="-1"></button>
    <aside class="pc-points-drawer-v7" data-plyr-service-drawer aria-hidden="true" role="dialog" aria-modal="true" aria-label="PLYR Points service editor">
        <header class="pc-points-drawer-head-v7">
            <div>
                <span data-plyr-editor-kicker>Service</span>
                <h2 data-plyr-editor-title>PLYR Points service</h2>
            </div>
            <button type="button" class="pc-drawer-close-v7" data-plyr-service-close aria-label="Close">×</button>
        </header>

        <div class="pc-points-drawer-body-v7">
            <div class="pc-drawer-notice-v7" data-plyr-drawer-notice hidden></div>

            <form data-plyr-service-form class="pc-editor-card-v7">
                @csrf
                <div class="pc-editor-topline-v7">
                    <div>
                        <strong>Service details</strong>
                        <span>What the player sees before spending points.</span>
                    </div>
                    <label class="pc-editor-enabled-v7">
                        <input type="checkbox" name="is_enabled" value="1" data-plyr-service-enabled checked>
                        <span>Available</span>
                    </label>
                </div>

                <div class="pc-editor-grid-v7">
                    <label class="wide">
                        <span>Service name</span>
                        <input type="text" name="name" maxlength="120" required data-plyr-service-name>
                    </label>
                    <label>
                        <span>Points</span>
                        <input type="number" name="points" min="1" max="100000" required data-plyr-service-points>
                    </label>
                    <label class="wide">
                        <span>Description</span>
                        <textarea name="description" rows="3" maxlength="1000" data-plyr-service-description></textarea>
                    </label>
                    <label class="wide">
                        <span>Button label</span>
                        <input type="text" name="cta" maxlength="120" placeholder="Request service" data-plyr-service-cta>
                    </label>
                </div>

                <div class="pc-inclusions-v7">
                    <div class="pc-subhead-v7">
                        <div><strong>Inclusions</strong><span>Add one inclusion per line.</span></div>
                        <button type="button" class="pc-text-button-v7" data-plyr-add-inclusion>+ Add</button>
                    </div>
                    <div class="pc-inclusion-list-v7" data-plyr-inclusion-list></div>
                </div>

                <div class="pc-editor-actions-v7">
                    <button type="button" class="pc-btn-v7 ghost" data-plyr-service-close>Cancel</button>
                    <button type="submit" class="pc-btn-v7 primary" data-plyr-save-service>Save service</button>
                </div>
            </form>

            <section class="pc-editor-card-v7" data-plyr-samples-section hidden>
                <div class="pc-subhead-v7">
                    <div><strong>Samples</strong><span>Images, videos, YouTube embeds, or external links.</span></div>
                    <span class="pc-sample-count-v7" data-plyr-sample-count>0</span>
                </div>

                <div class="pc-sample-list-v7" data-plyr-sample-list></div>

                <form data-plyr-sample-url-form class="pc-sample-add-v7">
                    @csrf
                    <div class="pc-sample-add-copy-v7">
                        <strong>Add URL</strong>
                        <span>YouTube links embed automatically.</span>
                    </div>
                    <input type="text" name="sample_title" maxlength="180" placeholder="Optional title">
                    <input type="url" name="sample_url" required placeholder="https://...">
                    <button type="submit" class="pc-btn-v7 primary" data-plyr-add-url>Add</button>
                </form>

                <form enctype="multipart/form-data" data-plyr-sample-upload-form class="pc-sample-add-v7">
                    @csrf
                    <div class="pc-sample-add-copy-v7">
                        <strong>Upload media</strong>
                        <span>Images or videos, up to 50 MB each.</span>
                    </div>
                    <input type="file" name="sample_files[]" multiple required accept="image/*,video/mp4,video/quicktime,video/webm">
                    <button type="submit" class="pc-btn-v7 primary" data-plyr-upload>Upload</button>
                </form>
            </section>

            <div class="pc-delete-service-v7" data-plyr-delete-section hidden>
                <div><strong>Delete service</strong><span>Custom services can be permanently removed.</span></div>
                <button type="button" class="pc-btn-v7 danger" data-plyr-delete-service>Delete</button>
            </div>
        </div>
    </aside>

    <style>
        .pc-admin-settings-v7{max-width:1180px;margin:0 auto;padding:.25rem 0 2rem;display:grid;gap:1rem;color:#101828}.dark .pc-admin-settings-v7{color:#f8fafc}
        .pc-settings-card-v7{border:1px solid #e4e7ec;border-radius:1rem;background:#fff;overflow:hidden;box-shadow:0 8px 28px rgba(15,23,42,.04)}.dark .pc-settings-card-v7{background:#111318;border-color:#2c313b;box-shadow:none}
        .pc-settings-head-v7,.pc-points-head-v7{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid #eaecf0}.dark .pc-settings-head-v7{border-color:#252a31}.pc-settings-head-v7 h2,.pc-points-head-v7 h2{margin:0;font-size:1rem;font-weight:850}.pc-settings-head-v7 p,.pc-points-head-v7 p{margin:.22rem 0 0;color:#667085;font-size:.75rem;line-height:1.4}.dark .pc-settings-head-v7 p{color:#98a2b3}
        .pc-settings-actions-v7{display:flex;gap:.4rem}.pc-btn-v7{height:2.2rem;border-radius:.62rem;padding:0 .78rem;font-size:.72rem;font-weight:850;cursor:pointer;transition:transform .14s ease,opacity .14s ease,background .14s ease,border-color .14s ease}.pc-btn-v7:active{transform:translateY(1px)}.pc-btn-v7:disabled{opacity:.58;cursor:wait}.pc-btn-v7.primary{border:1px solid #ff6338;background:#ff6338;color:#fff}.pc-btn-v7.primary:hover{background:#f4552d;border-color:#f4552d}.pc-btn-v7.ghost{border:1px solid #d0d5dd;background:#fff;color:#344054}.dark .pc-btn-v7.ghost{background:#17191f;border-color:#343943;color:#e5e7eb}.pc-btn-v7.danger{border:1px solid #fda29b;background:#fff;color:#b42318}.dark .pc-btn-v7.danger{background:#2a1717;border-color:#7a271a;color:#fda29b}
        .pc-sport-grid-v7{display:grid;grid-template-columns:repeat(4,minmax(0,1fr))}.pc-sport-row-v7{position:relative;display:flex;align-items:center;justify-content:space-between;gap:.65rem;min-height:3.1rem;padding:.58rem .8rem;border-right:1px solid #f0f1f3;border-bottom:1px solid #f0f1f3;background:#fff;cursor:pointer}.pc-sport-row-v7:nth-child(4n){border-right:0}.dark .pc-sport-row-v7{background:#111318;border-color:#252a31}.pc-sport-row-v7>span{min-width:0;display:flex;gap:.35rem;align-items:center}.pc-sport-row-v7 strong{font-size:.75rem}.pc-sport-row-v7 small{padding:.1rem .3rem;border-radius:.3rem;background:#f2f4f7;color:#98a2b3;font-size:.55rem;font-weight:800;text-transform:uppercase}.pc-sport-row-v7 input{position:absolute;opacity:0;pointer-events:none}.pc-switch-v7{width:1.8rem;height:1.02rem;padding:.11rem;display:flex;align-items:center;border-radius:999px;background:#d0d5dd}.pc-switch-v7 b{width:.8rem;height:.8rem;border-radius:50%;background:#fff;transition:.15s}.pc-sport-row-v7.is-enabled .pc-switch-v7{background:#ff6338}.pc-sport-row-v7.is-enabled .pc-switch-v7 b{transform:translateX(.77rem)}
        .pc-points-head-v7{background:#17191f;color:#fff;border-bottom-color:#242933}.pc-points-head-v7 p{color:#a8b2c1}.pc-points-alert-v7{margin:.8rem 1rem 0;padding:.7rem .8rem;border-radius:.7rem;background:#fff4e5;color:#92400e;font-size:.72rem;font-weight:750}
        .pc-inline-notice-v7,.pc-drawer-notice-v7{border-radius:.7rem;padding:.65rem .75rem;font-size:.7rem;font-weight:800;line-height:1.35}.pc-inline-notice-v7{margin:.7rem 1rem 0}.pc-inline-notice-v7.is-success,.pc-drawer-notice-v7.is-success{background:#ecfdf3;color:#027a48}.pc-inline-notice-v7.is-error,.pc-drawer-notice-v7.is-error{background:#fef3f2;color:#b42318}
        .pc-service-list-v7{display:grid}.pc-service-row-v7{display:grid;grid-template-columns:2.5rem minmax(0,1fr) auto auto;align-items:center;gap:.8rem;padding:.78rem 1rem;border-bottom:1px solid #eef0f3;transition:background .15s ease}.pc-service-row-v7:last-child{border-bottom:0}.pc-service-row-v7.is-updating{background:#fff8f5}.dark .pc-service-row-v7{border-color:#252a31}.dark .pc-service-row-v7.is-updating{background:rgba(255,99,56,.06)}.pc-service-icon-v7{width:2.4rem;height:2.4rem;border-radius:.72rem;display:grid;place-items:center;background:#fff0eb;color:#c2410c;font-size:.8rem;font-weight:950}.dark .pc-service-icon-v7{background:rgba(255,99,56,.14);color:#ff8f73}.pc-service-copy-v7{min-width:0}.pc-service-copy-v7 strong{display:block;font-size:.82rem;font-weight:850}.pc-service-copy-v7 span{display:block;margin-top:.18rem;color:#667085;font-size:.67rem}.dark .pc-service-copy-v7 span{color:#98a2b3}
        .pc-service-toggle-v7{min-width:4.25rem;height:2rem;border:1px solid #d0d5dd;border-radius:999px;background:#f2f4f7;color:#667085;padding:.18rem .42rem .18rem .2rem;display:flex;align-items:center;gap:.35rem;font-size:.64rem;font-weight:900;cursor:pointer;transition:all .18s ease}.pc-service-toggle-v7 i{width:1.3rem;height:1.3rem;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.15);transition:transform .18s ease}.pc-service-toggle-v7.is-on{background:#ff6338;border-color:#ff6338;color:#fff}.pc-service-toggle-v7.is-on i{transform:translateX(.04rem)}.pc-service-toggle-v7:disabled{opacity:.58;cursor:wait}.pc-service-edit-v7{height:2rem;border:1px solid #d0d5dd;border-radius:.58rem;background:#fff;color:#344054;padding:0 .7rem;font-size:.68rem;font-weight:850;cursor:pointer}.dark .pc-service-edit-v7{background:#17191f;border-color:#343943;color:#e5e7eb}.pc-service-migrate-v7{color:#b54708;font-size:.67rem;font-weight:800}.pc-service-empty-v7{padding:2rem;text-align:center;color:#98a2b3;font-size:.75rem}
        .pc-points-overlay-v7{position:fixed;inset:0;z-index:10050;border:0;background:rgba(15,23,42,.34);backdrop-filter:blur(2px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility 0s linear .22s}.pc-points-overlay-v7.is-open{opacity:1;visibility:visible;pointer-events:auto;transition:opacity .22s ease,visibility 0s}
        .pc-points-drawer-v7{position:fixed;z-index:10051;top:0;right:0;width:min(560px,100vw);height:100vh;display:grid;grid-template-rows:auto 1fr;background:#fff;color:#101828;border-left:1px solid #e4e7ec;box-shadow:-24px 0 70px rgba(15,23,42,.22);transform:translate3d(100%,0,0);visibility:hidden;pointer-events:none;will-change:transform;transition:transform .26s cubic-bezier(.22,.8,.22,1),visibility 0s linear .26s}.pc-points-drawer-v7.is-open{transform:translate3d(0,0,0);visibility:visible;pointer-events:auto;transition:transform .26s cubic-bezier(.22,.8,.22,1),visibility 0s}.dark .pc-points-drawer-v7{background:#111318;color:#f8fafc;border-color:#2c313b}
        .pc-points-drawer-head-v7{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:5rem;padding:1rem 1.1rem;background:#fff;border-bottom:1px solid #e4e7ec}.dark .pc-points-drawer-head-v7{background:#111318;border-color:#2c313b}.pc-points-drawer-head-v7 span{display:block;color:#ff6338;font-size:.62rem;font-weight:900;text-transform:uppercase;letter-spacing:.09em}.pc-points-drawer-head-v7 h2{margin:.2rem 0 0;font-size:1.15rem;font-weight:900;letter-spacing:-.02em}.pc-drawer-close-v7{width:2.35rem;height:2.35rem;border:1px solid #e4e7ec;border-radius:.8rem;background:#fff;color:#667085;font-size:1.45rem;line-height:1;cursor:pointer;transition:.15s}.pc-drawer-close-v7:hover{border-color:#ffb8a5;color:#ff6338;background:#fff7f4}.dark .pc-drawer-close-v7{background:#17191f;border-color:#343943;color:#d0d5dd}
        .pc-points-drawer-body-v7{overflow:auto;padding:1rem;display:grid;gap:.85rem;align-content:start;background:#f8fafc}.dark .pc-points-drawer-body-v7{background:#0f1217}.pc-editor-card-v7,.pc-delete-service-v7{border:1px solid #e4e7ec;border-radius:.9rem;background:#fff;padding:1rem}.dark .pc-editor-card-v7,.dark .pc-delete-service-v7{background:#17191f;border-color:#303641}.pc-editor-topline-v7,.pc-subhead-v7{display:flex;align-items:center;justify-content:space-between;gap:1rem}.pc-editor-topline-v7 strong,.pc-subhead-v7 strong{display:block;font-size:.8rem;font-weight:900}.pc-editor-topline-v7 span,.pc-subhead-v7 span{display:block;margin-top:.12rem;color:#667085;font-size:.63rem}.dark .pc-editor-topline-v7 span,.dark .pc-subhead-v7 span{color:#98a2b3}.pc-editor-enabled-v7{display:flex;align-items:center;gap:.4rem;font-size:.68rem;font-weight:850}.pc-editor-enabled-v7 input{accent-color:#ff6338}.pc-editor-grid-v7{display:grid;grid-template-columns:minmax(0,1fr) 7rem;gap:.7rem;margin-top:.9rem}.pc-editor-grid-v7 label{display:grid;gap:.35rem}.pc-editor-grid-v7 label.wide{grid-column:1/-1}.pc-editor-grid-v7 label>span{font-size:.65rem;font-weight:850;color:#475467}.dark .pc-editor-grid-v7 label>span{color:#cbd5e1}.pc-editor-grid-v7 input,.pc-editor-grid-v7 textarea,.pc-sample-add-v7 input{width:100%;border:1px solid #d0d5dd;border-radius:.62rem;background:#fff;color:#101828;padding:.58rem .65rem;font:inherit;font-size:.72rem;outline:none;transition:border-color .15s ease,box-shadow .15s ease}.pc-editor-grid-v7 input:focus,.pc-editor-grid-v7 textarea:focus,.pc-sample-add-v7 input:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.11)}.dark .pc-editor-grid-v7 input,.dark .pc-editor-grid-v7 textarea,.dark .pc-sample-add-v7 input{background:#111318;color:#f8fafc;border-color:#343943}
        .pc-inclusions-v7{margin-top:1rem;padding-top:1rem;border-top:1px solid #eaecf0}.dark .pc-inclusions-v7{border-color:#303641}.pc-text-button-v7{border:0;background:transparent;color:#ff6338;font-size:.68rem;font-weight:900;cursor:pointer}.pc-inclusion-list-v7{display:grid;gap:.45rem;margin-top:.65rem}.pc-inclusion-row-v7{display:grid;grid-template-columns:minmax(0,1fr) 2rem;gap:.4rem}.pc-inclusion-row-v7 input{width:100%;border:1px solid #d0d5dd;border-radius:.58rem;padding:.55rem .62rem;font-size:.7rem;background:#fff;color:#101828}.dark .pc-inclusion-row-v7 input{background:#111318;color:#f8fafc;border-color:#343943}.pc-inclusion-row-v7 button{border:1px solid #e4e7ec;border-radius:.55rem;background:#fff;color:#b42318;cursor:pointer}.dark .pc-inclusion-row-v7 button{background:#111318;border-color:#343943}.pc-editor-actions-v7{display:flex;justify-content:flex-end;gap:.45rem;margin-top:1rem}
        .pc-sample-list-v7{display:grid;margin-top:.7rem;border:1px solid #eaecf0;border-radius:.72rem;overflow:hidden}.dark .pc-sample-list-v7{border-color:#303641}.pc-sample-row-v7{display:grid;grid-template-columns:4rem minmax(0,1fr) auto auto;gap:.55rem;align-items:center;padding:.58rem .65rem;border-bottom:1px solid #eef0f3}.pc-sample-row-v7:last-child{border-bottom:0}.dark .pc-sample-row-v7{border-color:#252a31}.pc-sample-type-v7{color:#c2410c;font-size:.56rem;font-weight:950;text-transform:uppercase}.pc-sample-row-v7 strong{display:block;font-size:.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pc-sample-row-v7 a{font-size:.62rem;font-weight:850;color:#2563eb;text-decoration:none}.pc-sample-row-v7 button{border:0;background:transparent;color:#b42318;font-size:.62rem;font-weight:850;cursor:pointer}.pc-sample-empty-v7{padding:.8rem;text-align:center;color:#98a2b3;font-size:.68rem}.pc-sample-count-v7{margin:0!important;padding:.16rem .4rem;border-radius:999px;background:#f2f4f7;font-weight:850}.pc-sample-add-v7{display:grid;grid-template-columns:minmax(7rem,.8fr) minmax(0,.9fr) minmax(0,1.2fr) auto;gap:.5rem;align-items:end;margin-top:.65rem;padding:.65rem;border:1px solid #eaecf0;border-radius:.72rem;background:#f9fafb}.dark .pc-sample-add-v7{background:#111318;border-color:#303641}.pc-sample-add-copy-v7 strong{display:block;font-size:.67rem}.pc-sample-add-copy-v7 span{display:block;margin-top:.12rem;color:#98a2b3;font-size:.58rem;line-height:1.3}.pc-delete-service-v7{display:flex;align-items:center;justify-content:space-between;gap:1rem}.pc-delete-service-v7 strong{display:block;font-size:.72rem}.pc-delete-service-v7 span{display:block;margin-top:.12rem;color:#98a2b3;font-size:.62rem}
        [hidden]{display:none!important}
        @media(max-width:900px){.pc-sport-grid-v7{grid-template-columns:repeat(2,minmax(0,1fr))}.pc-service-row-v7{grid-template-columns:2.5rem minmax(0,1fr) auto}.pc-service-edit-v7{grid-column:2;justify-self:start}.pc-sample-add-v7{grid-template-columns:1fr 1fr}.pc-sample-add-copy-v7{grid-column:1/-1}}
        @media(max-width:640px){.pc-settings-head-v7,.pc-points-head-v7{align-items:flex-start;flex-direction:column}.pc-settings-actions-v7{width:100%;flex-wrap:wrap}.pc-sport-grid-v7{grid-template-columns:1fr}.pc-sport-row-v7{border-right:0!important}.pc-service-row-v7{grid-template-columns:2.5rem minmax(0,1fr) auto}.pc-editor-grid-v7{grid-template-columns:1fr}.pc-editor-grid-v7 label.wide{grid-column:auto}.pc-sample-row-v7{grid-template-columns:3.5rem minmax(0,1fr) auto}.pc-sample-row-v7 a{display:none}.pc-sample-add-v7{grid-template-columns:1fr}.pc-sample-add-copy-v7{grid-column:auto}}
    </style>

    <script>
        (() => {
            let services = @json($pointServices);
            const baseUrl = @js($pointsBaseUrl);
            const csrf = @js(csrf_token());
            const byId = new Map();
            services.filter(row => row && row.id).forEach(row => byId.set(String(row.id), row));

            const list = document.querySelector('[data-plyr-service-list]');
            const drawer = document.querySelector('[data-plyr-service-drawer]');
            const overlay = document.querySelector('.pc-points-overlay-v7');
            const form = drawer?.querySelector('[data-plyr-service-form]');
            const samplesSection = drawer?.querySelector('[data-plyr-samples-section]');
            const sampleList = drawer?.querySelector('[data-plyr-sample-list]');
            const sampleCount = drawer?.querySelector('[data-plyr-sample-count]');
            const urlForm = drawer?.querySelector('[data-plyr-sample-url-form]');
            const uploadForm = drawer?.querySelector('[data-plyr-sample-upload-form]');
            const deleteSection = drawer?.querySelector('[data-plyr-delete-section]');
            const inclusionList = drawer?.querySelector('[data-plyr-inclusion-list]');
            const pageNotice = document.querySelector('[data-plyr-notice]');
            const drawerNotice = drawer?.querySelector('[data-plyr-drawer-notice]');
            let activeServiceId = null;
            let noticeTimer = null;

            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
            const safeUrl = value => { try { const url = new URL(String(value || ''), window.location.origin); return ['http:','https:'].includes(url.protocol) ? url.href : ''; } catch (_) { return ''; } };
            const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;

            const showNotice = (message, type = 'success', insideDrawer = false) => {
                const target = insideDrawer ? drawerNotice : pageNotice;
                if (!target) return;
                target.textContent = String(message || '');
                target.className = insideDrawer ? 'pc-drawer-notice-v7' : 'pc-inline-notice-v7';
                target.classList.add(type === 'error' ? 'is-error' : 'is-success');
                target.hidden = false;
                if (noticeTimer) window.clearTimeout(noticeTimer);
                noticeTimer = window.setTimeout(() => { target.hidden = true; }, 3200);
            };

            const errorMessage = data => {
                const errors = data?.errors && typeof data.errors === 'object' ? Object.values(data.errors).flat() : [];
                return String(errors[0] || data?.message || 'Unable to save this change.');
            };

            const requestJson = async (url, options = {}) => {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    ...options,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                        ...(options.headers || {}),
                    },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data?.success === false) throw new Error(errorMessage(data));
                return data;
            };

            const serviceMeta = service => `${Number(service.points || 0).toLocaleString()} pts · ${plural((service.inclusions || []).length, 'inclusion', 'inclusions')} · ${plural((service.samples || []).length, 'sample', 'samples')}`;

            const rowHtml = service => `<article class="pc-service-row-v7" data-service-row="${escapeHtml(service.id)}">
                <div class="pc-service-icon-v7">${escapeHtml(String(service.name || 'P').slice(0,1).toUpperCase())}</div>
                <div class="pc-service-copy-v7"><strong data-service-name>${escapeHtml(service.name)}</strong><span data-service-meta>${escapeHtml(serviceMeta(service))}</span></div>
                <button type="button" class="pc-service-toggle-v7 ${service.enabled ? 'is-on' : ''}" data-plyr-toggle="${escapeHtml(service.id)}" aria-pressed="${service.enabled ? 'true' : 'false'}"><i></i><span>${service.enabled ? 'On' : 'Off'}</span></button>
                <button type="button" class="pc-service-edit-v7" data-plyr-service-edit="${escapeHtml(service.id)}">Edit</button>
            </article>`;

            const upsertService = service => {
                if (!service?.id) return;
                byId.set(String(service.id), service);
                const index = services.findIndex(row => String(row?.id || '') === String(service.id));
                if (index >= 0) services[index] = service; else services.push(service);
                document.querySelector('[data-plyr-service-empty]')?.remove();
                const current = list?.querySelector(`[data-service-row="${CSS.escape(String(service.id))}"]`);
                if (current) current.outerHTML = rowHtml(service);
                else list?.insertAdjacentHTML('beforeend', rowHtml(service));
            };

            const setDrawerOpen = open => {
                if (!drawer || !overlay) return;
                drawer.classList.toggle('is-open', open);
                overlay.classList.toggle('is-open', open);
                drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
                document.documentElement.style.overflow = open ? 'hidden' : '';
            };

            const addInclusion = value => {
                if (!inclusionList) return;
                const row = document.createElement('div');
                row.className = 'pc-inclusion-row-v7';
                row.innerHTML = '<input type="text" name="inclusions[]" maxlength="180" placeholder="Example: Up to 2 minutes"><button type="button" aria-label="Remove inclusion">×</button>';
                row.querySelector('input').value = String(value || '');
                row.querySelector('button').addEventListener('click', () => row.remove());
                inclusionList.appendChild(row);
            };

            const renderSamples = service => {
                if (!sampleList || !sampleCount) return;
                const samples = Array.isArray(service?.samples) ? service.samples : [];
                sampleCount.textContent = String(samples.length);
                if (!samples.length) {
                    sampleList.innerHTML = '<div class="pc-sample-empty-v7">No samples yet.</div>';
                    return;
                }
                sampleList.innerHTML = samples.map(sample => {
                    const openUrl = safeUrl(sample.url || sample.embed_url || '');
                    return `<div class="pc-sample-row-v7" data-sample-row="${escapeHtml(sample.db_id)}"><span class="pc-sample-type-v7">${escapeHtml(sample.type || 'sample')}</span><strong>${escapeHtml(sample.name || 'Sample')}</strong>${openUrl ? `<a href="${escapeHtml(openUrl)}" target="_blank" rel="noopener">Open</a>` : '<span></span>'}<button type="button" data-plyr-remove-sample="${escapeHtml(sample.db_id)}">Remove</button></div>`;
                }).join('');
            };

            const syncEditor = service => {
                if (!drawer || !form) return;
                const creating = !service;
                activeServiceId = service?.id ? String(service.id) : null;
                drawer.querySelector('[data-plyr-editor-kicker]').textContent = creating ? 'New service' : 'Edit service';
                drawer.querySelector('[data-plyr-editor-title]').textContent = creating ? 'Add PLYR Points service' : service.name;
                form.querySelector('[data-plyr-service-name]').value = service?.name || '';
                form.querySelector('[data-plyr-service-points]').value = service?.points || 50;
                form.querySelector('[data-plyr-service-description]').value = service?.description || '';
                form.querySelector('[data-plyr-service-cta]').value = service?.cta || '';
                form.querySelector('[data-plyr-service-enabled]').checked = service ? !!service.enabled : true;
                inclusionList.innerHTML = '';
                const inclusions = Array.isArray(service?.inclusions) ? service.inclusions : [];
                (inclusions.length ? inclusions : ['']).forEach(addInclusion);
                samplesSection.hidden = creating;
                deleteSection.hidden = creating || !!service?.is_system;
                if (!creating) renderSamples(service);
                if (drawerNotice) drawerNotice.hidden = true;
            };

            const openEditor = service => {
                syncEditor(service);
                window.requestAnimationFrame(() => setDrawerOpen(true));
            };

            const closeEditor = () => setDrawerOpen(false);

            document.querySelector('[data-plyr-service-new]')?.addEventListener('click', () => openEditor(null));
            list?.addEventListener('click', async event => {
                const edit = event.target.closest('[data-plyr-service-edit]');
                if (edit) {
                    openEditor(byId.get(String(edit.dataset.plyrServiceEdit)) || null);
                    return;
                }

                const toggle = event.target.closest('[data-plyr-toggle]');
                if (!toggle || toggle.disabled) return;
                const id = String(toggle.dataset.plyrToggle || '');
                const row = toggle.closest('[data-service-row]');
                toggle.disabled = true;
                row?.classList.add('is-updating');
                try {
                    const data = await requestJson(`${baseUrl}/${encodeURIComponent(id)}/toggle`, { method: 'POST' });
                    upsertService(data.service);
                    if (activeServiceId === id) syncEditor(data.service);
                    showNotice(data.message || 'Service updated.');
                } catch (error) {
                    showNotice(error.message, 'error');
                } finally {
                    row?.classList.remove('is-updating');
                }
            });

            form?.addEventListener('submit', async event => {
                event.preventDefault();
                const button = form.querySelector('[data-plyr-save-service]');
                const creating = !activeServiceId;
                const body = new FormData(form);
                if (!form.querySelector('[data-plyr-service-enabled]').checked) body.set('is_enabled', '0');
                if (!creating) body.set('_method', 'PUT');
                button.disabled = true;
                const label = button.textContent;
                button.textContent = creating ? 'Adding...' : 'Saving...';
                try {
                    const data = await requestJson(creating ? baseUrl : `${baseUrl}/${encodeURIComponent(activeServiceId)}`, { method: 'POST', body });
                    upsertService(data.service);
                    syncEditor(data.service);
                    showNotice(data.message || 'Service saved.', 'success', true);
                } catch (error) {
                    showNotice(error.message, 'error', true);
                } finally {
                    button.disabled = false;
                    button.textContent = label;
                }
            });

            urlForm?.addEventListener('submit', async event => {
                event.preventDefault();
                if (!activeServiceId) return;
                const button = urlForm.querySelector('[data-plyr-add-url]');
                button.disabled = true;
                try {
                    const data = await requestJson(`${baseUrl}/${encodeURIComponent(activeServiceId)}/samples/url`, { method: 'POST', body: new FormData(urlForm) });
                    upsertService(data.service);
                    syncEditor(data.service);
                    urlForm.reset();
                    showNotice(data.message || 'Sample added.', 'success', true);
                } catch (error) {
                    showNotice(error.message, 'error', true);
                } finally { button.disabled = false; }
            });

            uploadForm?.addEventListener('submit', async event => {
                event.preventDefault();
                if (!activeServiceId) return;
                const button = uploadForm.querySelector('[data-plyr-upload]');
                button.disabled = true;
                const label = button.textContent;
                button.textContent = 'Uploading...';
                try {
                    const data = await requestJson(`${baseUrl}/${encodeURIComponent(activeServiceId)}/samples/upload`, { method: 'POST', body: new FormData(uploadForm) });
                    upsertService(data.service);
                    syncEditor(data.service);
                    uploadForm.reset();
                    showNotice(data.message || 'Samples uploaded.', 'success', true);
                } catch (error) {
                    showNotice(error.message, 'error', true);
                } finally { button.disabled = false; button.textContent = label; }
            });

            sampleList?.addEventListener('click', async event => {
                const button = event.target.closest('[data-plyr-remove-sample]');
                if (!button || !activeServiceId) return;
                if (!window.confirm('Remove this sample?')) return;
                button.disabled = true;
                const sampleId = String(button.dataset.plyrRemoveSample || '');
                const body = new FormData();
                body.set('_method', 'DELETE');
                try {
                    const data = await requestJson(`${baseUrl}/${encodeURIComponent(activeServiceId)}/samples/${encodeURIComponent(sampleId)}`, { method: 'POST', body });
                    upsertService(data.service);
                    syncEditor(data.service);
                    showNotice(data.message || 'Sample removed.', 'success', true);
                } catch (error) {
                    button.disabled = false;
                    showNotice(error.message, 'error', true);
                }
            });

            drawer?.querySelector('[data-plyr-delete-service]')?.addEventListener('click', async event => {
                if (!activeServiceId || !window.confirm('Delete this service? This also removes its saved samples.')) return;
                const button = event.currentTarget;
                button.disabled = true;
                const body = new FormData();
                body.set('_method', 'DELETE');
                try {
                    const data = await requestJson(`${baseUrl}/${encodeURIComponent(activeServiceId)}`, { method: 'POST', body });
                    byId.delete(String(activeServiceId));
                    services = services.filter(row => String(row?.id || '') !== String(activeServiceId));
                    list?.querySelector(`[data-service-row="${CSS.escape(String(activeServiceId))}"]`)?.remove();
                    closeEditor();
                    showNotice(data.message || 'Service deleted.');
                } catch (error) {
                    button.disabled = false;
                    showNotice(error.message, 'error', true);
                }
            });

            drawer?.querySelector('[data-plyr-add-inclusion]')?.addEventListener('click', () => addInclusion(''));
            document.querySelectorAll('[data-plyr-service-close]').forEach(button => button.addEventListener('click', closeEditor));
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && drawer?.classList.contains('is-open')) closeEditor(); });
        })();
    </script>
</x-filament-panels::page>
</div>