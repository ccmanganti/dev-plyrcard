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
            <p class="coach-import-muted">The sport is locked to the selected folder. Your spreadsheet does not need a Sport column.</p>
            <div class="coach-import-sport">{{ \App\Filament\Resources\Coaches\CoachResource::sportOptions()[$selectedSport] ?? $selectedSport }}</div>

            <div class="coach-import-actions">
                <button class="coach-import-btn" type="button" wire:click="downloadTemplate('xlsx')" wire:loading.attr="disabled">Download Excel template</button>
                <button class="coach-import-btn" type="button" wire:click="downloadTemplate('csv')" wire:loading.attr="disabled">Download CSV template</button>
            </div>

            <label class="coach-drop-zone" x-bind:class="uploading ? 'is-uploading' : ''">
                <input type="file" wire:model="upload" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                <span class="coach-drop-icon"><x-filament::icon icon="heroicon-o-arrow-up-tray" /></span>
                <strong x-show="!uploading">Choose a CSV or Excel file</strong>
                <strong x-show="uploading">Uploading… <span x-text="uploadProgress + '%' "></span></strong>
                <small>Maximum file size: 20 MB</small>
                <div class="coach-progress" x-show="uploading || uploadProgress === 100" x-transition>
                    <i x-bind:style="`width:${uploadProgress}%`"></i>
                </div>
            </label>
            @error('upload') <div class="coach-errors">{{ $message }}</div> @enderror

            <div class="coach-import-actions">
                <button class="coach-import-btn coach-import-btn-primary" type="button" x-on:click="begin('Analyzing columns and preview rows…')" wire:click="analyzeUpload" wire:loading.attr="disabled" wire:target="analyzeUpload,upload">
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
                <p class="coach-import-muted">{{ number_format($totalRows) }} rows detected. Email, First Name, and Last Name are required. Display Name is generated automatically.</p>

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
                    <button class="coach-import-btn coach-import-btn-primary" type="button" x-on:click="runBatchImport()" x-bind:disabled="batchLoopRunning">
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
        [x-cloak]{display:none!important}.coach-import-stack{display:grid;gap:18px;--ci-accent:#ff6338;--ci-border:rgba(148,163,184,.28);--ci-muted:rgb(100 116 139)}.coach-import-card{background:var(--fi-color-white);border:1px solid var(--ci-border);border-radius:16px;padding:20px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .coach-import-card{background:rgb(24 24 27);border-color:rgba(148,163,184,.2)}.coach-import-title{font-size:18px;font-weight:800}.coach-import-muted{color:var(--ci-muted);font-size:14px}.coach-import-sport{display:inline-flex;margin-top:10px;border-radius:999px;padding:7px 12px;background:rgba(255,99,56,.12);color:var(--ci-accent);font-weight:800}.coach-import-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.coach-import-btn{border:1px solid rgba(148,163,184,.4);border-radius:10px;padding:9px 14px;font-weight:700;background:white;cursor:pointer;transition:.14s ease}.dark .coach-import-btn{background:rgb(39 39 42)}.coach-import-btn:hover{transform:translateY(-1px);border-color:var(--ci-accent)}.coach-import-btn:disabled{opacity:.58;cursor:wait;transform:none}.coach-import-btn-primary{background:var(--ci-accent);color:white;border-color:var(--ci-accent)}.coach-btn-loading{display:inline-flex;align-items:center;gap:7px}.coach-btn-loading i{width:14px;height:14px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:ci-spin .65s linear infinite}.coach-drop-zone{margin-top:16px;min-height:150px;border:1.5px dashed rgba(148,163,184,.65);border-radius:14px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:20px;text-align:center;cursor:pointer;transition:.18s ease}.coach-drop-zone:hover,.coach-drop-zone.is-uploading{border-color:var(--ci-accent);background:rgba(255,99,56,.04)}.coach-drop-zone input{position:absolute;width:1px;height:1px;opacity:0}.coach-drop-icon{width:42px;height:42px;border-radius:12px;background:rgba(255,99,56,.1);color:var(--ci-accent);display:grid;place-items:center}.coach-drop-icon svg{width:22px;height:22px}.coach-drop-zone small{color:var(--ci-muted)}.coach-progress{height:8px;width:min(460px,100%);border-radius:999px;background:rgba(148,163,184,.2);overflow:hidden;margin-top:8px}.coach-progress i{display:block;height:100%;border-radius:inherit;background:var(--ci-accent);transition:width .18s ease}.coach-map-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}.coach-map-field label{display:block;font-size:12px;font-weight:800;margin-bottom:5px}.coach-map-field select{width:100%;border:1px solid rgba(148,163,184,.45);border-radius:10px;padding:9px;background:transparent}.coach-preview{overflow:auto;margin-top:16px;border:1px solid var(--ci-border);border-radius:12px}.coach-preview table{width:100%;border-collapse:collapse;font-size:12px}.coach-preview th,.coach-preview td{padding:8px 10px;border-bottom:1px solid rgba(148,163,184,.18);white-space:nowrap;text-align:left}.coach-preview th{font-weight:800;background:rgba(148,163,184,.08)}.coach-errors{margin-top:12px;padding:12px;border-radius:12px;background:rgba(239,68,68,.08);color:rgb(185 28 28);max-height:260px;overflow:auto;font-size:13px}.coach-process-box{margin-top:14px;border:1px solid rgba(255,99,56,.25);background:rgba(255,99,56,.05);border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:10px}.coach-process-copy{display:flex;justify-content:space-between;gap:12px;font-size:13px}.coach-process-copy span{color:var(--ci-muted)}.coach-process-bar{height:9px;border-radius:999px;background:rgba(255,99,56,.15);overflow:hidden}.coach-process-bar.is-real i{display:block;height:100%;background:var(--ci-accent);border-radius:inherit;transition:width .25s ease}.coach-import-stats{display:flex;flex-wrap:wrap;gap:16px;font-size:12px;color:var(--ci-muted)}.coach-import-stats strong{color:inherit}.coach-import-global{position:fixed;z-index:9999;top:0;left:0;right:0;height:4px;background:rgba(255,99,56,.15)}.coach-import-global span{display:block;width:38%;height:100%;background:var(--ci-accent);animation:ci-progress 1s ease-in-out infinite}.coach-import-global strong{position:fixed;top:14px;right:18px;background:#111827;color:#fff;border-radius:999px;padding:7px 11px;font-size:11px;box-shadow:0 8px 24px rgba(15,23,42,.2)}@keyframes ci-spin{to{transform:rotate(360deg)}}@keyframes ci-progress{0%{transform:translateX(-120%)}100%{transform:translateX(360%)}}@media(max-width:800px){.coach-map-grid{grid-template-columns:1fr}.coach-process-copy{flex-direction:column}}
    </style>
</x-filament-panels::page>