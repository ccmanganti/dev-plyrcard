@php
    $run = $this->run;
    $running = $run && in_array($run->status, ['queued', 'running'], true);
    $done = $run && in_array($run->status, ['completed', 'completed_with_errors', 'cancelled', 'failed', 'paused'], true);
    $percent = $run ? $run->progressPercent() : 0;
@endphp

<div class="ghl-sync-panel" wire:poll.5s.visible>
    <div class="ghl-sync-topbar">
        <div>
            <span class="ghl-sync-eyebrow">GHL synchronization</span>
            <h3>Schools and coaches</h3>
            <p>Runs in the background and continues after reloading this page.</p>
        </div>

        <div class="ghl-sync-actions">
            @if($running)
                <button type="button" class="ghl-btn ghl-btn-danger" wire:click="stopSync" wire:loading.attr="disabled">
                    <x-filament::icon icon="heroicon-o-stop-circle" />
                    Stop
                </button>
            @else
                <button type="button" class="ghl-btn ghl-btn-primary" wire:click="startSync" wire:loading.attr="disabled">
                    <x-filament::icon icon="heroicon-o-cloud-arrow-up" />
                    Push pending to GHL
                </button>
            @endif

            @if($run)
                <button type="button" class="ghl-btn ghl-btn-secondary" wire:click="restartSync" wire:loading.attr="disabled">
                    <x-filament::icon icon="heroicon-o-arrow-path" />
                    Restart
                </button>
            @endif
        </div>
    </div>

    @if($run)
        <section class="ghl-sync-status {{ $running ? 'is-running' : '' }} {{ $run->failed_count > 0 ? 'has-errors' : '' }}">
            <div class="ghl-sync-status-head">
                <div class="ghl-sync-state">
                    <span class="ghl-sync-dot"></span>
                    <div>
                        <strong>{{ str($run->status)->replace('_', ' ')->title() }}</strong>
                        <p>{{ $run->message }}</p>
                    </div>
                </div>
                <b>{{ $percent === 0 && $run->processed > 0 ? '<1%' : $percent . '%' }}</b>
            </div>

            <div class="ghl-sync-progress"><i style="width: {{ max($run->processed > 0 ? 1 : 0, $percent) }}%"></i></div>

            <div class="ghl-sync-summary">
                <div class="ghl-summary-main">
                    <span>Coach progress</span>
                    <strong>{{ number_format($run->processed) }} / {{ number_format($run->total) }}</strong>
                </div>
                <div><strong>{{ number_format($run->created_count) }}</strong><span>Created</span></div>
                <div><strong>{{ number_format($run->updated_count) }}</strong><span>Updated</span></div>
                <div><strong>{{ number_format($run->unchanged_count) }}</strong><span>Unchanged</span></div>
                <div class="{{ $run->failed_count ? 'is-error' : '' }}"><strong>{{ number_format($run->failed_count) }}</strong><span>Failed</span></div>
            </div>

            <div class="ghl-sync-summary schools">
                <div class="ghl-summary-main"><span>School results</span><strong>{{ number_format($run->school_created_count + $run->school_updated_count + $run->school_unchanged_count + $run->school_failed_count) }}</strong></div>
                <div><strong>{{ number_format($run->school_created_count) }}</strong><span>Created</span></div>
                <div><strong>{{ number_format($run->school_updated_count) }}</strong><span>Updated</span></div>
                <div><strong>{{ number_format($run->school_unchanged_count) }}</strong><span>Unchanged</span></div>
                <div class="{{ $run->school_failed_count ? 'is-error' : '' }}"><strong>{{ number_format($run->school_failed_count) }}</strong><span>Failed</span></div>
            </div>

            @if($running && $run->current_email)
                <div class="ghl-sync-current">
                    <i class="ghl-spinner"></i>
                    <span>Checking <b>{{ $run->current_email }}</b> in <b>{{ $run->current_location_id }}</b></span>
                </div>
            @endif

            @if(!empty($this->errorSummary))
                <div class="ghl-sync-warning">
                    <div>
                        <strong>Synchronization is failing</strong>
                        <p>The worker now pauses after repeated identical failures so it does not mark the entire database failed.</p>
                    </div>
                    <details>
                        <summary>Show backend errors</summary>
                        @foreach($this->errorSummary as $error)
                            <code>{{ number_format($error['affected']) }} affected: {{ $error['message'] }}</code>
                        @endforeach
                    </details>
                </div>
            @endif
        </section>
    @elseif($this->pendingCount > 0)
        <div class="ghl-sync-ready">{{ number_format($this->pendingCount) }} pending coach targets are ready.</div>
    @endif

    <style>
        .ghl-sync-panel{display:grid;width:100%;gap:12px;margin:16px 0 20px;grid-column:1/-1}
        .ghl-sync-topbar,.ghl-sync-status{width:100%;box-sizing:border-box;border:1px solid #e5e7eb;border-radius:16px;background:#fff}.dark .ghl-sync-topbar,.dark .ghl-sync-status{background:#111827;border-color:rgba(255,255,255,.1)}
        .ghl-sync-topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 18px}.ghl-sync-eyebrow{font-size:10px;font-weight:900;letter-spacing:.15em;text-transform:uppercase;color:#ff6338}.ghl-sync-topbar h3{margin:2px 0 1px;font-size:17px;color:#111827}.dark .ghl-sync-topbar h3{color:#f9fafb}.ghl-sync-topbar p{margin:0;font-size:12px;color:#64748b}
        .ghl-sync-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.ghl-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:39px;padding:8px 13px;border-radius:10px;font-size:12px;font-weight:850;cursor:pointer}.ghl-btn svg{width:17px;height:17px}.ghl-btn-primary{border:0;background:#ff6338;color:#fff}.ghl-btn-secondary{border:1px solid #dbe2ea;background:#fff;color:#334155}.ghl-btn-danger{border:1px solid #fecaca;background:#fff;color:#dc2626}.dark .ghl-btn-secondary,.dark .ghl-btn-danger{background:#111827}
        .ghl-sync-status{padding:16px 18px}.ghl-sync-status-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}.ghl-sync-state{display:flex;gap:10px}.ghl-sync-dot{width:10px;height:10px;border-radius:999px;background:#94a3b8;margin-top:5px}.is-running .ghl-sync-dot{background:#ff6338;box-shadow:0 0 0 5px rgba(255,99,56,.12);animation:ghlPulse 1.5s infinite}.has-errors .ghl-sync-dot{background:#ef4444}.ghl-sync-state strong{font-size:14px;color:#111827}.dark .ghl-sync-state strong{color:#f9fafb}.ghl-sync-state p{margin:2px 0 0;font-size:12px;color:#64748b}.ghl-sync-status-head>b{font-size:17px;color:#ff6338}
        .ghl-sync-progress{height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin:13px 0}.ghl-sync-progress i{display:block;height:100%;background:#ff6338;border-radius:inherit;transition:width .4s}.is-running .ghl-sync-progress i{background:linear-gradient(90deg,#ff6338,#ff8a66,#ff6338);background-size:200% 100%;animation:ghlMove 1.2s linear infinite}
        .ghl-sync-summary{display:grid;grid-template-columns:minmax(180px,1.6fr) repeat(4,minmax(90px,1fr));gap:8px;margin-top:8px}.ghl-sync-summary>div{display:grid;gap:2px;padding:11px 12px;border:1px solid #edf1f5;border-radius:11px;background:#f8fafc}.dark .ghl-sync-summary>div{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.08)}.ghl-sync-summary strong{font-size:15px;color:#111827}.dark .ghl-sync-summary strong{color:#f9fafb}.ghl-sync-summary span{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.06em}.ghl-summary-main strong{font-size:17px}.ghl-sync-summary .is-error strong{color:#dc2626}
        .ghl-sync-current{display:flex;align-items:center;gap:9px;margin-top:12px;padding:10px 12px;border-radius:10px;background:#fff7ed;color:#9a3412;font-size:12px}.ghl-sync-warning{display:grid;gap:9px;margin-top:12px;padding:12px;border:1px solid #fecaca;border-radius:11px;background:#fef2f2}.ghl-sync-warning strong{color:#991b1b}.ghl-sync-warning p{margin:2px 0 0;color:#7f1d1d;font-size:12px}.ghl-sync-warning summary{cursor:pointer;font-weight:800;color:#b91c1c;font-size:12px}.ghl-sync-warning code{display:block;margin-top:7px;white-space:normal;word-break:break-word;font-size:11px;color:#7f1d1d;background:transparent}.ghl-sync-ready{padding:12px;border:1px dashed #cbd5e1;border-radius:12px;color:#64748b;font-size:12px}
        .ghl-spinner{width:14px;height:14px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:ghlSpin .7s linear infinite}@keyframes ghlSpin{to{transform:rotate(360deg)}}@keyframes ghlMove{to{background-position:-200% 0}}@keyframes ghlPulse{50%{box-shadow:0 0 0 8px rgba(255,99,56,0)}}
        @media(max-width:900px){.ghl-sync-topbar{align-items:flex-start;flex-direction:column}.ghl-sync-actions{justify-content:flex-start}.ghl-sync-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.ghl-summary-main{grid-column:1/-1}}
    </style>
</div>