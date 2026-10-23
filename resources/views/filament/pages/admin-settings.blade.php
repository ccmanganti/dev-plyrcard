<div class="pc-admin-settings-livewire-root-v6" style="display: contents;">
<x-filament-panels::page>
    @php
        $pointServices = $this->plyrPointServices;
        $pointsBaseUrl = url('/admin/plyr-points/services');
        $settingsUrl = \App\Filament\Pages\AdminSettings::getUrl();
    @endphp

    <div class="pc-admin-settings-v5">
        <section class="pc-settings-card-v5">
            <div class="pc-settings-head-v5">
                <div>
                    <h2>Sports availability</h2>
                    <p>Choose which sports are available across PLYRCARD.</p>
                </div>
                <div class="pc-settings-actions-v5">
                    <button type="button" class="ghost" wire:click="enableAllSports">Enable all</button>
                    <button type="button" class="ghost" wire:click="disableAllSports">Disable all</button>
                    <button type="button" class="primary" wire:click="saveSports" wire:loading.attr="disabled" wire:target="saveSports">Save</button>
                </div>
            </div>

            <div class="pc-sport-grid-v5">
                @foreach($this->sports as $sport)
                    @php($isEnabled = in_array($sport['key'], $enabledSports, true))
                    <label class="pc-sport-row-v5 {{ $isEnabled ? 'is-enabled' : '' }}" wire:key="admin-sport-{{ $sport['key'] }}">
                        <span>
                            <strong>{{ $sport['label'] }}</strong>
                            @if(! $sport['athlete'])<small>Coach only</small>@endif
                        </span>
                        <input type="checkbox" wire:model.live="enabledSports" value="{{ $sport['key'] }}">
                        <i class="pc-switch-v5" aria-hidden="true"><b></b></i>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="pc-settings-card-v5" id="plyr-points-services">
            <div class="pc-settings-head-v5 pc-points-heading-v5">
                <div>
                    <h2>PLYR Points services</h2>
                    <p>Simple service controls. Turn a service on or off, or open Edit when you need to change details, inclusions, or samples.</p>
                </div>
                <a class="primary pc-link-button-v5" href="{{ $settingsUrl }}?service=new#plyr-points-services" data-plyr-service-new>+ Add service</a>
            </div>

            @if(! $this->plyrPointDatabaseReady)
                <div class="pc-points-alert-v5">Run the new PLYR Points migration first. The current catalog is being shown as a fallback.</div>
            @endif
            @if(session('plyr_points_success'))
                <div class="pc-points-flash-v5 is-success">{{ session('plyr_points_success') }}</div>
            @endif
            @if(session('plyr_points_error'))
                <div class="pc-points-flash-v5 is-error">{{ session('plyr_points_error') }}</div>
            @endif

            <div class="pc-service-list-v5">
                @forelse($pointServices as $service)
                    <article class="pc-service-row-v5">
                        <div class="pc-service-icon-v5">{{ strtoupper(substr($service['name'] ?? 'P', 0, 1)) }}</div>
                        <div class="pc-service-copy-v5">
                            <strong>{{ $service['name'] }}</strong>
                            <span>
                                {{ number_format((int) $service['points']) }} pts
                                · {{ count($service['inclusions'] ?? []) }} {{ count($service['inclusions'] ?? []) === 1 ? 'inclusion' : 'inclusions' }}
                                · {{ count($service['samples'] ?? []) }} {{ count($service['samples'] ?? []) === 1 ? 'sample' : 'samples' }}
                            </span>
                        </div>

                        @if(!empty($service['id']))
                            <form method="POST" action="{{ route('admin.plyr-points.services.toggle', $service['id']) }}" class="pc-toggle-form-v5">
                                @csrf
                                <button type="submit" class="pc-service-toggle-v5 {{ ($service['enabled'] ?? true) ? 'is-on' : '' }}" aria-label="Toggle {{ $service['name'] }}">
                                    <i></i><span>{{ ($service['enabled'] ?? true) ? 'On' : 'Off' }}</span>
                                </button>
                            </form>
                            <a class="pc-service-edit-v5" href="{{ $settingsUrl }}?service={{ $service['id'] }}#plyr-points-services" data-plyr-service-edit="{{ $service['id'] }}">Edit</a>
                        @else
                            <span class="pc-service-migrate-v5">Migration required</span>
                        @endif
                    </article>
                @empty
                    <div class="pc-service-empty-v5">No PLYR Points services yet.</div>
                @endforelse
            </div>
        </section>
    </div>

    <button type="button" class="pc-points-overlay-v5" data-plyr-service-close hidden aria-label="Close service editor"></button>
    <aside class="pc-points-drawer-v5" data-plyr-service-drawer hidden role="dialog" aria-modal="true" aria-label="PLYR Points service editor">
        <header class="pc-points-drawer-head-v5">
            <div>
                <span data-plyr-editor-kicker>Service</span>
                <h2 data-plyr-editor-title>PLYR Points service</h2>
            </div>
            <button type="button" data-plyr-service-close aria-label="Close">×</button>
        </header>

        <div class="pc-points-drawer-body-v5">
            @if($errors->any())<div class="pc-points-flash-v5 is-error" style="margin:0">{{ $errors->first() }}</div>@endif
            <form method="POST" data-plyr-service-form class="pc-editor-card-v5">
                @csrf
                <input type="hidden" name="_method" value="POST" data-plyr-service-method>

                <div class="pc-editor-topline-v5">
                    <div>
                        <strong>Service details</strong>
                        <span>Player-facing content and point cost.</span>
                    </div>
                    <label class="pc-editor-enabled-v5">
                        <input type="hidden" name="is_enabled" value="0">
                        <input type="checkbox" name="is_enabled" value="1" data-plyr-service-enabled checked>
                        <span>Available</span>
                    </label>
                </div>

                <div class="pc-editor-grid-v5">
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

                <div class="pc-inclusions-v5">
                    <div class="pc-subhead-v5">
                        <div><strong>Inclusions</strong><span>One clear deliverable or inclusion per row.</span></div>
                        <button type="button" class="ghost" data-plyr-add-inclusion>+ Add inclusion</button>
                    </div>
                    <div class="pc-inclusion-list-v5" data-plyr-inclusion-list></div>
                </div>

                <div class="pc-editor-actions-v5">
                    <button type="button" class="ghost" data-plyr-service-close>Cancel</button>
                    <button type="submit" class="primary">Save service</button>
                </div>
            </form>

            <section class="pc-editor-card-v5" data-plyr-samples-section hidden>
                <div class="pc-subhead-v5">
                    <div><strong>Samples</strong><span>Images, videos, YouTube embeds, or regular links.</span></div>
                    <span class="pc-sample-count-v5" data-plyr-sample-count>0</span>
                </div>

                <div class="pc-sample-list-v5" data-plyr-sample-list></div>

                <form method="POST" data-plyr-sample-url-form class="pc-sample-add-v5">
                    @csrf
                    <div class="pc-sample-add-copy-v5">
                        <strong>Add link or YouTube</strong>
                        <span>YouTube is embedded automatically. Direct image/video URLs are previewed when possible.</span>
                    </div>
                    <input type="text" name="sample_title" maxlength="180" placeholder="Optional title">
                    <input type="url" name="sample_url" required placeholder="https://...">
                    <button type="submit" class="primary">Add</button>
                </form>

                <form method="POST" enctype="multipart/form-data" data-plyr-sample-upload-form class="pc-sample-add-v5">
                    @csrf
                    <div class="pc-sample-add-copy-v5">
                        <strong>Upload image or video</strong>
                        <span>Multiple files allowed, up to 50 MB each.</span>
                    </div>
                    <input type="file" name="sample_files[]" multiple required accept="image/*,video/mp4,video/quicktime,video/webm">
                    <button type="submit" class="primary">Upload</button>
                </form>
            </section>

            <form method="POST" data-plyr-delete-form class="pc-delete-service-v5" hidden onsubmit="return confirm('Delete this service? This also removes its saved samples.');">
                @csrf
                @method('DELETE')
                <div><strong>Delete service</strong><span>Only custom services can be deleted. Built-in services can simply be turned off.</span></div>
                <button type="submit" class="danger">Delete</button>
            </form>
        </div>
    </aside>

    <style>
        .pc-admin-settings-v5{max-width:1180px;margin:0 auto;padding:.25rem 0 2rem;display:grid;gap:1rem;color:#101828}
        .dark .pc-admin-settings-v5{color:#f8fafc}
        .pc-settings-card-v5{border:1px solid #e4e7ec;border-radius:1rem;background:#fff;overflow:hidden;box-shadow:0 8px 28px rgba(15,23,42,.04)}
        .dark .pc-settings-card-v5{background:#111318;border-color:#2c313b;box-shadow:none}
        .pc-settings-head-v5{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid #eaecf0}
        .dark .pc-settings-head-v5{border-color:#252a31}.pc-settings-head-v5 h2{margin:0;font-size:1rem;font-weight:850}.pc-settings-head-v5 p{margin:.22rem 0 0;color:#667085;font-size:.75rem}.dark .pc-settings-head-v5 p{color:#98a2b3}
        .pc-settings-actions-v5{display:flex;gap:.4rem}.primary,.ghost,.danger{height:2.2rem;border-radius:.62rem;padding:0 .75rem;font-size:.72rem;font-weight:850;cursor:pointer}.primary{border:1px solid #ff6338;background:#ff6338;color:#fff}.pc-link-button-v5{display:inline-flex;align-items:center;justify-content:center;text-decoration:none}.ghost{border:1px solid #d0d5dd;background:#fff;color:#344054}.dark .ghost{background:#17191f;border-color:#343943;color:#e5e7eb}.danger{border:1px solid #fda29b;background:#fff;color:#b42318}.dark .danger{background:#2a1717;border-color:#7a271a;color:#fda29b}
        .pc-sport-grid-v5{display:grid;grid-template-columns:repeat(4,minmax(0,1fr))}.pc-sport-row-v5{position:relative;display:flex;align-items:center;justify-content:space-between;gap:.65rem;min-height:3.1rem;padding:.58rem .8rem;border-right:1px solid #f0f1f3;border-bottom:1px solid #f0f1f3;background:#fff;cursor:pointer}.pc-sport-row-v5:nth-child(4n){border-right:0}.dark .pc-sport-row-v5{background:#111318;border-color:#252a31}.pc-sport-row-v5>span{min-width:0;display:flex;gap:.35rem;align-items:center}.pc-sport-row-v5 strong{font-size:.75rem}.pc-sport-row-v5 small{padding:.1rem .3rem;border-radius:.3rem;background:#f2f4f7;color:#98a2b3;font-size:.55rem;font-weight:800;text-transform:uppercase}.pc-sport-row-v5 input{position:absolute;opacity:0;pointer-events:none}.pc-switch-v5{width:1.8rem;height:1.02rem;padding:.11rem;display:flex;align-items:center;border-radius:999px;background:#d0d5dd}.pc-switch-v5 b{width:.8rem;height:.8rem;border-radius:50%;background:#fff;transition:.15s}.pc-sport-row-v5.is-enabled .pc-switch-v5{background:#ff6338}.pc-sport-row-v5.is-enabled .pc-switch-v5 b{transform:translateX(.77rem)}
        .pc-points-heading-v5{background:#17191f;color:#fff;border-bottom-color:#242933}.pc-points-heading-v5 p{color:#98a2b3}.pc-points-alert-v5,.pc-points-flash-v5{margin:.8rem 1rem 0;padding:.7rem .8rem;border-radius:.7rem;font-size:.72rem;font-weight:750}.pc-points-alert-v5{background:#fff4e5;color:#92400e}.pc-points-flash-v5.is-success{background:#ecfdf3;color:#027a48}.pc-points-flash-v5.is-error{background:#fef3f2;color:#b42318}
        .pc-service-list-v5{display:grid}.pc-service-row-v5{display:grid;grid-template-columns:2.5rem minmax(0,1fr) auto auto;align-items:center;gap:.8rem;padding:.78rem 1rem;border-bottom:1px solid #eef0f3}.pc-service-row-v5:last-child{border-bottom:0}.dark .pc-service-row-v5{border-color:#252a31}.pc-service-icon-v5{width:2.4rem;height:2.4rem;border-radius:.72rem;display:grid;place-items:center;background:#fff0eb;color:#c2410c;font-size:.8rem;font-weight:950}.dark .pc-service-icon-v5{background:rgba(255,99,56,.14);color:#ff8f73}.pc-service-copy-v5{min-width:0}.pc-service-copy-v5 strong{display:block;font-size:.82rem;font-weight:850}.pc-service-copy-v5 span{display:block;margin-top:.18rem;color:#667085;font-size:.67rem}.dark .pc-service-copy-v5 span{color:#98a2b3}.pc-toggle-form-v5{margin:0}.pc-service-toggle-v5{min-width:4.2rem;height:2rem;border:1px solid #d0d5dd;border-radius:999px;background:#f2f4f7;color:#667085;padding:.18rem .42rem .18rem .2rem;display:flex;align-items:center;gap:.35rem;font-size:.64rem;font-weight:900;cursor:pointer}.pc-service-toggle-v5 i{width:1.3rem;height:1.3rem;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.15)}.pc-service-toggle-v5.is-on{background:#ff6338;border-color:#ff6338;color:#fff}.pc-service-edit-v5{height:2rem;border:1px solid #d0d5dd;border-radius:.58rem;background:#fff;color:#344054;padding:0 .7rem;font-size:.68rem;font-weight:850;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}.dark .pc-service-edit-v5{background:#17191f;border-color:#343943;color:#e5e7eb}.pc-service-migrate-v5{color:#b54708;font-size:.67rem;font-weight:800}.pc-service-empty-v5{padding:2rem;text-align:center;color:#98a2b3;font-size:.75rem}
        .pc-points-overlay-v5{position:fixed;inset:0;z-index:10050;border:0;background:rgba(15,23,42,.58);backdrop-filter:blur(3px)}.pc-points-drawer-v5{position:fixed;z-index:10051;top:0;right:0;bottom:0;width:min(600px,100vw);display:grid;grid-template-rows:auto 1fr;background:#f8fafc;color:#101828;border-left:1px solid #e4e7ec;box-shadow:-24px 0 70px rgba(15,23,42,.24)}.dark .pc-points-drawer-v5{background:#0f1217;color:#f8fafc;border-color:#2c313b}.pc-points-drawer-head-v5{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.15rem;background:#17191f;color:#fff;border-bottom:1px solid #242933}.pc-points-drawer-head-v5 span{display:block;color:#ff8b6e;font-size:.62rem;font-weight:900;text-transform:uppercase;letter-spacing:.09em}.pc-points-drawer-head-v5 h2{margin:.18rem 0 0;font-size:1.08rem;font-weight:900}.pc-points-drawer-head-v5>button{width:2.2rem;height:2.2rem;border:1px solid #343943;border-radius:.65rem;background:#20242c;color:#fff;font-size:1.35rem;cursor:pointer}.pc-points-drawer-body-v5{overflow:auto;padding:1rem;display:grid;gap:.85rem;align-content:start}.pc-editor-card-v5,.pc-delete-service-v5{border:1px solid #e4e7ec;border-radius:.9rem;background:#fff;padding:1rem}.dark .pc-editor-card-v5,.dark .pc-delete-service-v5{background:#17191f;border-color:#303641}.pc-editor-topline-v5,.pc-subhead-v5{display:flex;align-items:center;justify-content:space-between;gap:1rem}.pc-editor-topline-v5 strong,.pc-subhead-v5 strong{display:block;font-size:.8rem;font-weight:900}.pc-editor-topline-v5 span,.pc-subhead-v5 span{display:block;margin-top:.12rem;color:#667085;font-size:.63rem}.dark .pc-editor-topline-v5 span,.dark .pc-subhead-v5 span{color:#98a2b3}.pc-editor-enabled-v5{display:flex;align-items:center;gap:.4rem;font-size:.68rem;font-weight:850}.pc-editor-enabled-v5 input{accent-color:#ff6338}.pc-editor-grid-v5{display:grid;grid-template-columns:minmax(0,1fr) 7rem;gap:.7rem;margin-top:.9rem}.pc-editor-grid-v5 label{display:grid;gap:.35rem}.pc-editor-grid-v5 label.wide{grid-column:1/-1}.pc-editor-grid-v5 label>span{font-size:.65rem;font-weight:850;color:#475467}.dark .pc-editor-grid-v5 label>span{color:#cbd5e1}.pc-editor-grid-v5 input,.pc-editor-grid-v5 textarea,.pc-sample-add-v5 input{width:100%;border:1px solid #d0d5dd;border-radius:.62rem;background:#fff;color:#101828;padding:.58rem .65rem;font:inherit;font-size:.72rem;outline:none}.dark .pc-editor-grid-v5 input,.dark .pc-editor-grid-v5 textarea,.dark .pc-sample-add-v5 input{background:#111318;color:#f8fafc;border-color:#343943}.pc-inclusions-v5{margin-top:1rem;padding-top:1rem;border-top:1px solid #eaecf0}.dark .pc-inclusions-v5{border-color:#303641}.pc-inclusion-list-v5{display:grid;gap:.45rem;margin-top:.65rem}.pc-inclusion-row-v5{display:grid;grid-template-columns:minmax(0,1fr) 2rem;gap:.4rem}.pc-inclusion-row-v5 input{width:100%;border:1px solid #d0d5dd;border-radius:.58rem;padding:.55rem .62rem;font-size:.7rem;background:#fff;color:#101828}.dark .pc-inclusion-row-v5 input{background:#111318;color:#f8fafc;border-color:#343943}.pc-inclusion-row-v5 button{border:1px solid #e4e7ec;border-radius:.55rem;background:#fff;color:#b42318;cursor:pointer}.dark .pc-inclusion-row-v5 button{background:#111318;border-color:#343943}.pc-editor-actions-v5{display:flex;justify-content:flex-end;gap:.45rem;margin-top:1rem}
        .pc-sample-list-v5{display:grid;margin-top:.7rem;border:1px solid #eaecf0;border-radius:.72rem;overflow:hidden}.dark .pc-sample-list-v5{border-color:#303641}.pc-sample-row-v5{display:grid;grid-template-columns:4rem minmax(0,1fr) auto auto;gap:.55rem;align-items:center;padding:.58rem .65rem;border-bottom:1px solid #eef0f3}.pc-sample-row-v5:last-child{border-bottom:0}.dark .pc-sample-row-v5{border-color:#252a31}.pc-sample-type-v5{color:#c2410c;font-size:.56rem;font-weight:950;text-transform:uppercase}.pc-sample-row-v5 strong{display:block;font-size:.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pc-sample-row-v5 a{font-size:.62rem;font-weight:850;color:#2563eb;text-decoration:none}.pc-sample-row-v5 form{margin:0}.pc-sample-row-v5 button{border:0;background:transparent;color:#b42318;font-size:.62rem;font-weight:850;cursor:pointer}.pc-sample-empty-v5{padding:.8rem;text-align:center;color:#98a2b3;font-size:.68rem}.pc-sample-count-v5{margin:0!important;padding:.16rem .4rem;border-radius:999px;background:#f2f4f7;font-weight:850}.pc-sample-add-v5{display:grid;grid-template-columns:minmax(8rem,.85fr) minmax(0,1fr) minmax(0,1.35fr) auto;gap:.5rem;align-items:end;margin-top:.65rem;padding:.65rem;border:1px solid #eaecf0;border-radius:.72rem;background:#f9fafb}.dark .pc-sample-add-v5{background:#111318;border-color:#303641}.pc-sample-add-copy-v5 strong{display:block;font-size:.67rem}.pc-sample-add-copy-v5 span{display:block;margin-top:.12rem;color:#98a2b3;font-size:.58rem;line-height:1.3}.pc-delete-service-v5{display:flex;align-items:center;justify-content:space-between;gap:1rem}.pc-delete-service-v5 strong{display:block;font-size:.72rem}.pc-delete-service-v5 span{display:block;margin-top:.12rem;color:#98a2b3;font-size:.62rem}
        [hidden]{display:none!important}
        @media(max-width:900px){.pc-sport-grid-v5{grid-template-columns:repeat(2,minmax(0,1fr))}.pc-service-row-v5{grid-template-columns:2.5rem minmax(0,1fr) auto}.pc-service-edit-v5{grid-column:2;justify-self:start}.pc-sample-add-v5{grid-template-columns:1fr 1fr}.pc-sample-add-copy-v5{grid-column:1/-1}}
        @media(max-width:640px){.pc-settings-head-v5{align-items:flex-start;flex-direction:column}.pc-settings-actions-v5{width:100%;flex-wrap:wrap}.pc-sport-grid-v5{grid-template-columns:1fr}.pc-sport-row-v5{border-right:0!important}.pc-service-row-v5{grid-template-columns:2.5rem minmax(0,1fr) auto}.pc-editor-grid-v5{grid-template-columns:1fr}.pc-editor-grid-v5 label.wide{grid-column:auto}.pc-sample-row-v5{grid-template-columns:3.5rem minmax(0,1fr) auto}.pc-sample-row-v5 a{display:none}.pc-sample-add-v5{grid-template-columns:1fr}.pc-sample-add-copy-v5{grid-column:auto}}
    </style>

    <script>
        (() => {
            const services = @json($pointServices);
            const baseUrl = @js($pointsBaseUrl);
            const settingsUrl = @js($settingsUrl);
            const csrf = @js(csrf_token());
            const byId = new Map(services.filter(row => row && row.id).map(row => [String(row.id), row]));

            const drawer = document.querySelector('[data-plyr-service-drawer]');
            const overlay = document.querySelector('.pc-points-overlay-v5');
            const form = drawer?.querySelector('[data-plyr-service-form]');
            const method = drawer?.querySelector('[data-plyr-service-method]');
            const samplesSection = drawer?.querySelector('[data-plyr-samples-section]');
            const sampleList = drawer?.querySelector('[data-plyr-sample-list]');
            const sampleCount = drawer?.querySelector('[data-plyr-sample-count]');
            const urlForm = drawer?.querySelector('[data-plyr-sample-url-form]');
            const uploadForm = drawer?.querySelector('[data-plyr-sample-upload-form]');
            const deleteForm = drawer?.querySelector('[data-plyr-delete-form]');
            const inclusionList = drawer?.querySelector('[data-plyr-inclusion-list]');

            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
            const safeUrl = value => { try { const url = new URL(String(value || ''), window.location.origin); return ['http:','https:'].includes(url.protocol) ? url.href : ''; } catch (_) { return ''; } };

            const setOpen = open => {
                if (!drawer || !overlay) return;
                drawer.hidden = !open;
                overlay.hidden = !open;
                document.documentElement.style.overflow = open ? 'hidden' : '';
            };

            const addInclusion = value => {
                if (!inclusionList) return;
                const row = document.createElement('div');
                row.className = 'pc-inclusion-row-v5';
                row.innerHTML = `<input type="text" name="inclusions[]" maxlength="180" placeholder="Example: Up to 2 minutes"><button type="button" aria-label="Remove inclusion">×</button>`;
                row.querySelector('input').value = String(value || '');
                row.querySelector('button').addEventListener('click', () => row.remove());
                inclusionList.appendChild(row);
            };

            const renderSamples = service => {
                if (!sampleList || !sampleCount) return;
                const samples = Array.isArray(service?.samples) ? service.samples : [];
                sampleCount.textContent = String(samples.length);
                if (!samples.length) {
                    sampleList.innerHTML = '<div class="pc-sample-empty-v5">No samples yet.</div>';
                    return;
                }
                sampleList.innerHTML = samples.map(sample => {
                    const openUrl = safeUrl(sample.url || sample.embed_url || '');
                    const removeUrl = `${baseUrl}/${service.id}/samples/${sample.db_id}`;
                    return `<div class="pc-sample-row-v5"><span class="pc-sample-type-v5">${escapeHtml(sample.type || 'sample')}</span><strong>${escapeHtml(sample.name || 'Sample')}</strong>${openUrl ? `<a href="${escapeHtml(openUrl)}" target="_blank" rel="noopener">Open</a>` : '<span></span>'}<form method="POST" action="${escapeHtml(removeUrl)}" onsubmit="return confirm('Remove this sample?');"><input type="hidden" name="_token" value="${escapeHtml(csrf)}"><input type="hidden" name="_method" value="DELETE"><button type="submit">Remove</button></form></div>`;
                }).join('');
            };

            const openEditor = service => {
                if (!drawer || !form || !method) return;
                const creating = !service;
                drawer.querySelector('[data-plyr-editor-kicker]').textContent = creating ? 'New service' : 'Edit service';
                drawer.querySelector('[data-plyr-editor-title]').textContent = creating ? 'Add PLYR Points service' : service.name;
                form.action = creating ? baseUrl : `${baseUrl}/${service.id}`;
                method.disabled = creating;
                method.value = 'PUT';
                form.querySelector('[data-plyr-service-name]').value = service?.name || '';
                form.querySelector('[data-plyr-service-points]').value = service?.points || 50;
                form.querySelector('[data-plyr-service-description]').value = service?.description || '';
                form.querySelector('[data-plyr-service-cta]').value = service?.cta || '';
                form.querySelector('[data-plyr-service-enabled]').checked = service ? !!service.enabled : true;
                inclusionList.innerHTML = '';
                const inclusions = Array.isArray(service?.inclusions) ? service.inclusions : [];
                (inclusions.length ? inclusions : ['']).forEach(addInclusion);

                samplesSection.hidden = creating;
                deleteForm.hidden = creating || !!service?.is_system;
                if (!creating) {
                    urlForm.action = `${baseUrl}/${service.id}/samples/url`;
                    uploadForm.action = `${baseUrl}/${service.id}/samples/upload`;
                    deleteForm.action = `${baseUrl}/${service.id}`;
                    renderSamples(service);
                }
                setOpen(true);
                const nextUrl = `${settingsUrl}?service=${creating ? 'new' : service.id}#plyr-points-services`;
                window.history.replaceState({}, '', nextUrl);
            };

            document.querySelector('[data-plyr-service-new]')?.addEventListener('click', event => { event.preventDefault(); openEditor(null); });
            document.querySelectorAll('[data-plyr-service-edit]').forEach(button => button.addEventListener('click', event => { event.preventDefault(); openEditor(byId.get(String(button.dataset.plyrServiceEdit)) || null); }));
            const closeEditor = () => { setOpen(false); window.history.replaceState({}, '', `${settingsUrl}#plyr-points-services`); };
            drawer?.querySelectorAll('[data-plyr-service-close]').forEach(button => button.addEventListener('click', closeEditor));
            overlay?.addEventListener('click', closeEditor);
            drawer?.querySelector('[data-plyr-add-inclusion]')?.addEventListener('click', () => addInclusion(''));
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && drawer && !drawer.hidden) closeEditor(); });

            const requested = new URLSearchParams(window.location.search).get('service');
            if (requested === 'new') openEditor(null);
            else if (requested && byId.has(String(requested))) openEditor(byId.get(String(requested)));
        })();
    </script>
</x-filament-panels::page>
</div>