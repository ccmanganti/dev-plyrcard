<x-filament-panels::page>
    <div
        class="coach-import-stack"
        x-data="{
            uploading: false,
            uploadProgress: 0,
            stage: '',
            batchLoopRunning: false,
            begin(label) { this.stage = label },
            async runBatchImport() {
                if (this.batchLoopRunning) return;
                this.batchLoopRunning = true;
                this.stage = 'Preparing import rows…';

                try {
                    await this.$wire.startImport();

                    while (this.$wire.importRunning) {
                        this.stage = 'Importing coaches…';
                        await this.$wire.processNextBatch();
                    }
                } finally {
                    this.batchLoopRunning = false;
                    this.stage = '';
                }
            },
        }"
        x-on:livewire-upload-start="uploading=true; uploadProgress=0; stage='Uploading file…'"
        x-on:livewire-upload-progress="uploadProgress=$event.detail.progress"
        x-on:livewire-upload-finish="uploading=false; uploadProgress=100; stage='Upload complete'"
        x-on:livewire-upload-error="uploading=false; stage='Upload failed'"
    >
        <div class="coach-import-global" x-show="batchLoopRunning || uploading" x-cloak>
            <span></span>
            <strong x-text="stage || 'Processing…'"></strong>
        </div>

        <section class="coach-import-card">
            <div class="coach-import-title">Import coaches from CSV or Excel</div>
            <p class="coach-import-muted">Sport comes from the selected folder. Choose the coach gender below; your spreadsheet does not need Sport or Gender columns.</p>
            <div class="coach-import-context">
                <div>
                    <span class="coach-import-context-label">Sport</span>
                    <div class="coach-import-sport">{{ \App\Filament\Resources\Coaches\CoachResource::sportOptions()[$selectedSport] ?? $selectedSport }}</div>
                </div>
                <label class="coach-import-gender">
                    <span class="coach-import-context-label">Gender</span>
                    <select wire:model.live="selectedGender" x-bind:disabled="batchLoopRunning">
                        <option value="">Select gender</option>
                        @foreach(\App\Filament\Resources\Coaches\CoachResource::genderOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <small>Applied to every coach in this import.</small>
                </label>
            </div>

            <div class="coach-import-visibility">
                <div class="coach-import-visibility-head">
                    <div>
                        <strong>Access after import</strong>
                        <span>Optional. Restrict the coaches from this file to a specific audience.</span>
                    </div>
                    <span class="coach-import-visibility-status {{ $quickVisibilityType === 'public' ? 'is-public' : 'is-restricted' }}">
                        {{ $quickVisibilityType === 'public' ? 'Public' : 'Restricted' }}
                    </span>
                </div>

                <div class="coach-import-visibility-modes" role="group" aria-label="Quick exclusivity type">
                    @foreach([
                        'public' => ['Public', 'heroicon-o-globe-alt'],
                        'users' => ['User(s)', 'heroicon-o-user-group'],
                        'club' => ['Club', 'heroicon-o-building-office-2'],
                        'league' => ['League', 'heroicon-o-trophy'],
                    ] as $mode => [$label, $icon])
                        <button
                            type="button"
                            wire:click="setQuickVisibilityType('{{ $mode }}')"
                            wire:loading.attr="disabled"
                            x-bind:disabled="batchLoopRunning"
                            class="coach-import-visibility-mode {{ $quickVisibilityType === $mode ? 'is-active' : '' }}"
                        >
                            <x-filament::icon :icon="$icon" />
                            <span>{{ $label }}</span>
                        </button>
                    @endforeach
                </div>

                @if($quickVisibilityType === 'users')
                    <div class="coach-import-user-picker">
                        <div class="coach-import-user-picker-head">
                            <div>
                                <span class="coach-import-context-label">Allowed users</span>
                                <small>Select one or several athletes. No Ctrl/Cmd key is needed.</small>
                            </div>
                            <span class="coach-import-selected-count">{{ count($this->quickVisibilitySelectedUsers) }} selected</span>
                        </div>

                        @if($this->quickVisibilitySelectedUsers)
                            <div class="coach-import-selected-users">
                                @foreach($this->quickVisibilitySelectedUsers as $selectedUser)
                                    <button
                                        type="button"
                                        wire:key="quick-selected-user-{{ $selectedUser['id'] }}"
                                        wire:click="removeQuickVisibilityUser({{ $selectedUser['id'] }})"
                                        class="coach-import-selected-user"
                                        title="Remove this user"
                                    >
                                        <span>{{ $selectedUser['label'] }}</span>
                                        <b aria-hidden="true">×</b>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="coach-import-user-search">
                            <x-filament::icon icon="heroicon-o-magnifying-glass" />
                            <input
                                type="search"
                                wire:model.live.debounce.250ms="quickVisibilityUserSearch"
                                placeholder="Search athlete by name or email"
                                autocomplete="off"
                                x-bind:disabled="batchLoopRunning"
                            >
                        </div>

                        <div class="coach-import-user-results">
                            @forelse($this->quickVisibilityUserOptions as $userId => $userLabel)
                                @php($userSelected = in_array((int) $userId, collect((array) $quickVisibilityUserIds)->map(fn ($id) => (int) $id)->all(), true))
                                <button
                                    type="button"
                                    wire:key="quick-user-result-{{ $userId }}"
                                    wire:click="toggleQuickVisibilityUser({{ (int) $userId }})"
                                    class="coach-import-user-result {{ $userSelected ? 'is-selected' : '' }}"
                                    x-bind:disabled="batchLoopRunning"
                                >
                                    <span class="coach-import-user-check">
                                        @if($userSelected)
                                            <x-filament::icon icon="heroicon-o-check" />
                                        @endif
                                    </span>
                                    <span>{{ $userLabel }}</span>
                                </button>
                            @empty
                                <div class="coach-import-user-empty">No matching eligible users.</div>
                            @endforelse
                        </div>
                    </div>
                @elseif($quickVisibilityType === 'club')
                    <label class="coach-import-visibility-field">
                        <span class="coach-import-context-label">Club</span>
                        <select wire:model.live="quickVisibilityClubId" x-bind:disabled="batchLoopRunning">
                            <option value="">Choose a club</option>
                            @foreach($this->quickVisibilityClubOptions as $clubId => $clubLabel)
                                <option value="{{ $clubId }}">{{ $clubLabel }}</option>
                            @endforeach
                        </select>
                        <small>Every currently eligible athlete attached to this club will receive access.</small>
                    </label>
                @elseif($quickVisibilityType === 'league')
                    <label class="coach-import-visibility-field">
                        <span class="coach-import-context-label">League</span>
                        <select wire:model.live="quickVisibilityLeagueId" x-bind:disabled="batchLoopRunning">
                            <option value="">Choose a league</option>
                            @foreach($this->quickVisibilityLeagueOptions as $leagueId => $leagueLabel)
                                <option value="{{ $leagueId }}">{{ $leagueLabel }}</option>
                            @endforeach
                        </select>
                        <small>Every currently eligible athlete attached to this league will receive access.</small>
                    </label>
                @else
                    <p class="coach-import-visibility-note is-public-note">Everyone who normally qualifies for these coaches can see them.</p>
                @endif

                @if($quickVisibilityType !== 'public')
                    <p class="coach-import-visibility-note">
                        Imported coaches receive this restriction. A school is restricted automatically only when that school is newly created by this file; existing schools stay public so their unrelated coaches are not hidden.
                    </p>
                @endif
            </div>

            <div class="coach-import-actions">
                <button class="coach-import-btn" type="button" wire:click="downloadTemplate('xlsx')" wire:loading.attr="disabled">Download Excel template</button>
                <button class="coach-import-btn" type="button" wire:click="downloadTemplate('csv')" wire:loading.attr="disabled">Download CSV template</button>
            </div>

            <label class="coach-drop-zone" x-bind:class="uploading ? 'is-uploading' : ''">
                <input type="file" wire:model="upload" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" x-bind:disabled="batchLoopRunning">
                <span class="coach-drop-icon"><x-filament::icon icon="heroicon-o-arrow-up-tray" /></span>
                @if($stagedUploadName && $storedImportPath)
                    <strong x-show="!uploading">{{ $stagedUploadName }}</strong>
                    <small x-show="!uploading">{{ number_format($stagedUploadBytes / 1048576, 2) }} MB · ready to analyze · click to replace</small>
                @else
                    <strong x-show="!uploading">Choose a CSV or Excel file</strong>
                    <small x-show="!uploading">Maximum file size: 20 MB</small>
                @endif
                <strong x-show="uploading">Uploading… <span x-text="uploadProgress + '%' "></span></strong>
                <div class="coach-progress" x-show="uploading || uploadProgress === 100" x-transition>
                    <i x-bind:style="`width:${uploadProgress}%`"></i>
                </div>
            </label>
            @error('upload') <div class="coach-errors">{{ $message }}</div> @enderror

            <div class="coach-import-actions">
                <button class="coach-import-btn coach-import-btn-primary" type="button" x-on:click="begin('Analyzing columns and preview rows…')" wire:click="analyzeUpload" wire:loading.attr="disabled" wire:target="analyzeUpload,upload" @disabled(! $storedImportPath)>
                    <span wire:loading.remove wire:target="analyzeUpload">Analyze file</span>
                    <span class="coach-btn-loading" wire:loading wire:target="analyzeUpload"><i></i> Analyzing…</span>
                </button>
                @if($headers)
                    <button class="coach-import-btn" type="button" wire:click="resetImport" wire:loading.attr="disabled" x-bind:disabled="batchLoopRunning">Start over</button>
                @endif
            </div>
        </section>

        @if($headers)
            <section class="coach-import-card">
                <div class="coach-import-title">Column mapping</div>
                <p class="coach-import-muted">{{ number_format($totalRows) }} rows detected. Email, First Name, and Last Name are required. Sport and Gender are applied from the import context above.</p>

                <div class="coach-map-grid">
                    @foreach(\App\Services\CoachSpreadsheetService::IMPORT_FIELDS as $field => $label)
                        <div class="coach-map-field">
                            <label>{{ $label }} @if(in_array($field, ['email','first_name','last_name'])) * @endif</label>
                            <select wire:model="mapping.{{ $field }}" x-bind:disabled="batchLoopRunning">
                                <option value="">Do not import</option>
                                @foreach($headers as $header)
                                    <option value="{{ $header }}">{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>

                <div class="coach-import-actions">
                    <button class="coach-import-btn coach-import-btn-primary" type="button" x-on:click="runBatchImport()" x-bind:disabled="batchLoopRunning || !@js((bool) $selectedGender) || !@js((bool) $this->quickVisibilityReady)">
                        <span x-show="!batchLoopRunning">Import {{ number_format($totalRows) }} coaches</span>
                        <span class="coach-btn-loading" x-show="batchLoopRunning" x-cloak><i></i> Importing and preparing GHL checks…</span>
                    </button>
                </div>

                @if($importRunning || $importProcessed > 0)
                    <div class="coach-process-box">
                        <div class="coach-process-copy">
                            <strong>{{ $importRunning ? 'Importing coaches and checking configured GHL subaccounts' : 'Import completed' }}</strong>
                            <span>{{ number_format($importProcessed) }} of {{ number_format($importTotal) }} valid rows processed</span>
                        </div>
                        <div class="coach-process-bar is-real"><i style="width: {{ $this->importProgress }}%"></i></div>
                        <div class="coach-import-stats">
                            <span><strong>{{ $this->importProgress }}%</strong> complete</span>
                            <span><strong>{{ number_format($importCreated) }}</strong> created</span>
                            <span><strong>{{ number_format($importUpdated) }}</strong> updated</span>
                            <span><strong>{{ number_format($importFailed) }}</strong> failed</span>
                        </div>
                    </div>
                @endif
            </section>

            <section class="coach-import-card">
                <div class="coach-import-title">Preview</div>
                <p class="coach-import-muted">First {{ count($previewRows) }} non-empty rows.</p>
                <div class="coach-preview">
                    <table>
                        <thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse($previewRows as $row)
                                <tr>@foreach($headers as $header)<td>{{ $row[$header] ?? '' }}</td>@endforeach</tr>
                            @empty
                                <tr><td colspan="{{ count($headers) }}">No preview rows found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($lastImportErrors)
            <section class="coach-import-card">
                <div class="coach-import-title">Rows needing review</div>
                <div class="coach-errors">@foreach($lastImportErrors as $error)<div>{{ $error }}</div>@endforeach</div>
            </section>
        @endif
    </div>

    <style>
        [x-cloak]{display:none!important}.coach-import-stack{display:grid;gap:18px;--ci-accent:#ff6338;--ci-border:rgba(148,163,184,.28);--ci-muted:rgb(100 116 139)}.coach-import-card{background:var(--fi-color-white);border:1px solid var(--ci-border);border-radius:16px;padding:20px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .coach-import-card{background:rgb(24 24 27);border-color:rgba(148,163,184,.2)}.coach-import-title{font-size:18px;font-weight:800}.coach-import-muted{color:var(--ci-muted);font-size:14px}.coach-import-context{display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-top:10px}.coach-import-context-label{display:block;margin-bottom:5px;font-size:11px;font-weight:800;color:var(--ci-muted);text-transform:uppercase;letter-spacing:.08em}.coach-import-sport{display:inline-flex;border-radius:10px;padding:9px 12px;background:rgba(255,99,56,.10);color:var(--ci-accent);font-weight:800;min-height:40px;align-items:center}.coach-import-gender{display:block;min-width:220px}.coach-import-gender select{width:100%;min-height:40px;border:1px solid rgba(148,163,184,.45);border-radius:10px;padding:8px 10px;background:transparent}.coach-import-gender small{display:block;margin-top:4px;color:var(--ci-muted);font-size:11px}.coach-import-visibility{margin-top:14px;padding:14px;border:1px solid var(--ci-border);border-radius:14px;background:linear-gradient(180deg,rgba(248,250,252,.9),rgba(255,255,255,.8))}.dark .coach-import-visibility{background:rgba(255,255,255,.025)}.coach-import-visibility-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.coach-import-visibility-head>div{display:grid;gap:2px}.coach-import-visibility-head strong{font-size:14px}.coach-import-visibility-head span{color:var(--ci-muted);font-size:11px}.coach-import-visibility-status{display:inline-flex;align-items:center;min-height:24px;padding:3px 8px;border-radius:999px;font-size:10px!important;font-weight:850}.coach-import-visibility-status.is-public{background:rgba(34,197,94,.10);color:#15803d!important}.coach-import-visibility-status.is-restricted{background:rgba(255,99,56,.11);color:#c2410c!important}.coach-import-visibility-modes{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin-top:12px}.coach-import-visibility-mode{display:flex;align-items:center;justify-content:center;gap:6px;min-height:38px;border:1px solid var(--ci-border);border-radius:10px;background:transparent;color:inherit;font-size:12px;font-weight:800;cursor:pointer;transition:.14s ease}.coach-import-visibility-mode svg{width:15px;height:15px}.coach-import-visibility-mode:hover{border-color:rgba(255,99,56,.5);color:var(--ci-accent)}.coach-import-visibility-mode.is-active{border-color:rgba(255,99,56,.48);background:rgba(255,99,56,.09);color:var(--ci-accent);box-shadow:inset 0 0 0 1px rgba(255,99,56,.04)}.coach-import-visibility-mode:disabled{opacity:.55;cursor:wait}.coach-import-visibility-field{display:block;margin-top:12px}.coach-import-visibility-field select{width:100%;min-height:42px;border:1px solid rgba(148,163,184,.45);border-radius:10px;padding:8px 10px;background:transparent}.coach-import-visibility-field small{display:block;margin-top:5px;color:var(--ci-muted);font-size:11px}.coach-import-user-picker{margin-top:12px;border:1px solid var(--ci-border);border-radius:12px;overflow:hidden;background:var(--fi-color-white)}.dark .coach-import-user-picker{background:rgba(24,24,27,.85)}.coach-import-user-picker-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:10px 11px;border-bottom:1px solid var(--ci-border)}.coach-import-user-picker-head small{display:block;color:var(--ci-muted);font-size:10px}.coach-import-selected-count{flex:none;padding:3px 7px;border-radius:999px;background:rgba(255,99,56,.10);color:var(--ci-accent);font-size:10px;font-weight:850}.coach-import-selected-users{display:flex;gap:6px;flex-wrap:wrap;padding:9px 11px 0}.coach-import-selected-user{display:inline-flex;align-items:center;gap:6px;max-width:100%;border:0;border-radius:999px;padding:5px 8px;background:rgba(255,99,56,.10);color:#c2410c;font-size:10px;font-weight:750;cursor:pointer}.coach-import-selected-user span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.coach-import-selected-user b{font-size:14px;line-height:1}.coach-import-user-search{display:flex;align-items:center;gap:8px;margin:9px 11px;border:1px solid rgba(148,163,184,.45);border-radius:10px;padding:0 10px;background:transparent}.coach-import-user-search svg{width:16px;height:16px;color:var(--ci-muted);flex:none}.coach-import-user-search input{width:100%;min-height:38px;border:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;padding:7px 0;font-size:12px}.coach-import-user-results{display:grid;max-height:230px;overflow:auto;border-top:1px solid var(--ci-border)}.coach-import-user-result{display:flex;align-items:center;gap:9px;width:100%;padding:9px 11px;border:0;border-bottom:1px solid rgba(148,163,184,.15);background:transparent;color:inherit;text-align:left;font-size:11px;cursor:pointer}.coach-import-user-result:last-child{border-bottom:0}.coach-import-user-result:hover{background:rgba(148,163,184,.07)}.coach-import-user-result.is-selected{background:rgba(255,99,56,.06);color:#c2410c}.coach-import-user-check{display:grid;place-items:center;width:17px;height:17px;flex:none;border:1px solid rgba(148,163,184,.55);border-radius:5px}.coach-import-user-result.is-selected .coach-import-user-check{border-color:var(--ci-accent);background:var(--ci-accent);color:#fff}.coach-import-user-check svg{width:11px;height:11px}.coach-import-user-empty{padding:14px;color:var(--ci-muted);font-size:11px;text-align:center}.coach-import-visibility-note{margin:9px 0 0;color:var(--ci-muted);font-size:11px;line-height:1.5}.coach-import-visibility-note.is-public-note{margin-top:10px;padding:8px 10px;border-radius:9px;background:rgba(34,197,94,.06);color:#4b5563}.dark .coach-import-visibility-note.is-public-note{color:#9ca3af}.coach-import-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.coach-import-btn{border:1px solid rgba(148,163,184,.4);border-radius:10px;padding:9px 14px;font-weight:700;background:white;cursor:pointer;transition:.14s ease}.dark .coach-import-btn{background:rgb(39 39 42)}.coach-import-btn:hover{transform:translateY(-1px);border-color:var(--ci-accent)}.coach-import-btn:disabled{opacity:.58;cursor:wait;transform:none}.coach-import-btn-primary{background:var(--ci-accent);color:white;border-color:var(--ci-accent)}.coach-btn-loading{display:inline-flex;align-items:center;gap:7px}.coach-btn-loading i{width:14px;height:14px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:ci-spin .65s linear infinite}.coach-drop-zone{margin-top:16px;min-height:150px;border:1.5px dashed rgba(148,163,184,.65);border-radius:14px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:20px;text-align:center;cursor:pointer;transition:.18s ease}.coach-drop-zone:hover,.coach-drop-zone.is-uploading{border-color:var(--ci-accent);background:rgba(255,99,56,.04)}.coach-drop-zone input{position:absolute;width:1px;height:1px;opacity:0}.coach-drop-icon{width:42px;height:42px;border-radius:12px;background:rgba(255,99,56,.1);color:var(--ci-accent);display:grid;place-items:center}.coach-drop-icon svg{width:22px;height:22px}.coach-drop-zone small{color:var(--ci-muted)}.coach-progress{height:8px;width:min(460px,100%);border-radius:999px;background:rgba(148,163,184,.2);overflow:hidden;margin-top:8px}.coach-progress i{display:block;height:100%;border-radius:inherit;background:var(--ci-accent);transition:width .18s ease}.coach-map-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}.coach-map-field label{display:block;font-size:12px;font-weight:800;margin-bottom:5px}.coach-map-field select{width:100%;border:1px solid rgba(148,163,184,.45);border-radius:10px;padding:9px;background:transparent}.coach-preview{overflow:auto;margin-top:16px;border:1px solid var(--ci-border);border-radius:12px}.coach-preview table{width:100%;border-collapse:collapse;font-size:12px}.coach-preview th,.coach-preview td{padding:8px 10px;border-bottom:1px solid rgba(148,163,184,.18);white-space:nowrap;text-align:left}.coach-preview th{font-weight:800;background:rgba(148,163,184,.08)}.coach-errors{margin-top:12px;padding:12px;border-radius:12px;background:rgba(239,68,68,.08);color:rgb(185 28 28);max-height:260px;overflow:auto;font-size:13px}.coach-process-box{margin-top:14px;border:1px solid rgba(255,99,56,.25);background:rgba(255,99,56,.05);border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:10px}.coach-process-copy{display:flex;justify-content:space-between;gap:12px;font-size:13px}.coach-process-copy span{color:var(--ci-muted)}.coach-process-bar{height:9px;border-radius:999px;background:rgba(255,99,56,.15);overflow:hidden}.coach-process-bar.is-real i{display:block;height:100%;background:var(--ci-accent);border-radius:inherit;transition:width .25s ease}.coach-import-stats{display:flex;flex-wrap:wrap;gap:16px;font-size:12px;color:var(--ci-muted)}.coach-import-stats strong{color:inherit}.coach-import-global{position:fixed;z-index:9999;top:0;left:0;right:0;height:4px;background:rgba(255,99,56,.15)}.coach-import-global span{display:block;width:38%;height:100%;background:var(--ci-accent);animation:ci-progress 1s ease-in-out infinite}.coach-import-global strong{position:fixed;top:14px;right:18px;background:#111827;color:#fff;border-radius:999px;padding:7px 11px;font-size:11px;box-shadow:0 8px 24px rgba(15,23,42,.2)}@keyframes ci-spin{to{transform:rotate(360deg)}}@keyframes ci-progress{0%{transform:translateX(-120%)}100%{transform:translateX(360%)}}@media(max-width:800px){.coach-map-grid{grid-template-columns:1fr}.coach-import-visibility-modes{grid-template-columns:repeat(2,minmax(0,1fr))}.coach-process-copy{flex-direction:column}}
    </style>
</x-filament-panels::page>