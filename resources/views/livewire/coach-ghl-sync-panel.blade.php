@php
    $run = $this->run;
    $running = $run && in_array($run->status, ['queued', 'running'], true);
    $percent = $run ? $run->progressPercent() : 0;
    $heartbeatAge = $run?->heartbeat_at ? now()->diffInSeconds($run->heartbeat_at) : null;
    $workerDelayed = $run && $run->status === 'queued' && $run->processed === 0 && $heartbeatAge !== null && $heartbeatAge > 20;
    $schoolTotal = $run
        ? $run->school_created_count + $run->school_updated_count + $run->school_unchanged_count + $run->school_failed_count
        : 0;
@endphp

<div class="ghl-sync-panel" wire:poll.2s.visible="pollStatus" x-data="{ pressed: null }">
    <div class="ghl-sync-bar">
        <div class="ghl-sync-title">
            <span class="ghl-sync-dot {{ $running ? 'is-live' : '' }}"></span>
            <div>
                <span class="ghl-sync-eyebrow">GHL sync</span>
                <strong>Schools &amp; coaches</strong>
                @if($run)
                    <small>{{ str($run->status)->replace('_', ' ')->title() }} · {{ number_format($run->processed) }}/{{ number_format($run->total) }}</small>
                @else
                    <small>Ready to synchronize pending records</small>
                @endif
            </div>
        </div>

        <div class="ghl-sync-actions">
            @if($running)
                <button type="button" class="ghl-btn ghl-btn-danger" wire:click="stopSync" wire:target="stopSync" wire:loading.attr="disabled" @click="pressed = 'stop'">
                    <span wire:loading.remove wire:target="stopSync"><x-filament::icon icon="heroicon-o-stop-circle" /></span>
                    <span class="ghl-spinner" wire:loading wire:target="stopSync"></span>
                    <span wire:loading.remove wire:target="stopSync">Stop</span>
                    <span wire:loading wire:target="stopSync">Stopping…</span>
                </button>
            @else
                <button type="button" class="ghl-btn ghl-btn-primary" wire:click="startSync" wire:target="startSync" wire:loading.attr="disabled" @click="pressed = 'push'">
                    <span wire:loading.remove wire:target="startSync"><x-filament::icon icon="heroicon-o-cloud-arrow-up" /></span>
                    <span class="ghl-spinner" wire:loading wire:target="startSync"></span>
                    <span wire:loading.remove wire:target="startSync">Push to GHL</span>
                    <span wire:loading wire:target="startSync">Queuing…</span>
                </button>
            @endif

            @if($run)
                <button type="button" class="ghl-btn ghl-btn-secondary" wire:click="restartSync" wire:target="restartSync" wire:loading.attr="disabled" @click="pressed = 'restart'">
                    <span wire:loading.remove wire:target="restartSync"><x-filament::icon icon="heroicon-o-arrow-path" /></span>
                    <span class="ghl-spinner" wire:loading wire:target="restartSync"></span>
                    <span wire:loading.remove wire:target="restartSync">Restart</span>
                    <span wire:loading wire:target="restartSync">Restarting…</span>
                </button>
            @endif
        </div>
    </div>

    @if($run)
        <section class="ghl-sync-status {{ $running ? 'is-running' : '' }}">
            <div class="ghl-progress-row">
                <div class="ghl-sync-progress"><i style="width: {{ max($run->processed > 0 ? 1 : 0, $percent) }}%"></i></div>
                <b>{{ $percent === 0 && $run->processed > 0 ? '<1%' : $percent . '%' }}</b>
            </div>

            <div class="ghl-stats">
                <span><b>{{ number_format($run->processed) }}</b>/{{ number_format($run->total) }} coaches</span>
                <span><b>{{ number_format($run->created_count) }}</b> created</span>
                <span><b>{{ number_format($run->updated_count) }}</b> updated</span>
                <span><b>{{ number_format($run->unchanged_count) }}</b> unchanged</span>
                <span class="{{ $run->failed_count ? 'is-error' : '' }}"><b>{{ number_format($run->failed_count) }}</b> failed</span>
                <span class="ghl-divider"></span>
                <span><b>{{ number_format($schoolTotal) }}</b> schools</span>
                <span><b>{{ number_format($run->school_created_count) }}</b> new</span>
                <span><b>{{ number_format($run->school_updated_count) }}</b> updated</span>
                <span class="{{ $run->school_failed_count ? 'is-error' : '' }}"><b>{{ number_format($run->school_failed_count) }}</b> failed</span>
            </div>

            @if($running && $run->current_email)
                <div class="ghl-current"><i class="ghl-spinner"></i><span>{{ $run->current_email }} · {{ $run->current_location_id }}</span></div>
            @elseif($run->message)
                <div class="ghl-current is-neutral"><span>{{ $run->message }}</span></div>
            @endif

            @if($workerDelayed)
                <div class="ghl-alert">Queue worker has not picked up this job yet.</div>
            @endif

            @if(!empty($this->errorSummary))
                <details class="ghl-errors">
                    <summary>Backend errors</summary>
                    @foreach($this->errorSummary as $error)
                        <code>{{ number_format($error['affected']) }} affected: {{ $error['message'] }}</code>
                    @endforeach
                </details>
            @endif
        </section>
    @elseif($this->pendingCount > 0)
        <div class="ghl-ready">{{ number_format($this->pendingCount) }} pending coach targets</div>
    @endif

    <style>
        .ghl-sync-panel{display:grid;grid-column:1/-1;width:100%;gap:7px;margin:8px 0 12px}.ghl-sync-bar,.ghl-sync-status{box-sizing:border-box;width:100%;border:1px solid #e5e7eb;border-radius:12px;background:#fff}.dark .ghl-sync-bar,.dark .ghl-sync-status{background:#111827;border-color:rgba(255,255,255,.1)}
        .ghl-sync-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 12px}.ghl-sync-title{display:flex;align-items:center;gap:9px;min-width:0}.ghl-sync-title>div{display:flex;align-items:baseline;gap:7px;min-width:0;flex-wrap:wrap}.ghl-sync-eyebrow{font-size:9px;font-weight:900;letter-spacing:.13em;text-transform:uppercase;color:#ff6338}.ghl-sync-title strong{font-size:13px;color:#111827}.dark .ghl-sync-title strong{color:#f8fafc}.ghl-sync-title small{font-size:10px;color:#64748b}.ghl-sync-dot{width:8px;height:8px;flex:0 0 auto;border-radius:999px;background:#94a3b8}.ghl-sync-dot.is-live{background:#ff6338;box-shadow:0 0 0 4px rgba(255,99,56,.12);animation:ghlPulse 1.5s ease-in-out infinite}
        .ghl-sync-actions{display:flex;gap:6px;flex-wrap:wrap}.ghl-btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;min-height:32px;padding:6px 10px;border-radius:8px;font-size:11px;font-weight:850;cursor:pointer;transition:transform .12s ease,box-shadow .12s ease,opacity .12s ease}.ghl-btn:hover{transform:translateY(-1px)}.ghl-btn:active{transform:scale(.96)}.ghl-btn:disabled{cursor:wait;opacity:.72;transform:scale(.98)}.ghl-btn svg{width:14px;height:14px}.ghl-btn-primary{border:0;background:#ff6338;color:#fff;box-shadow:0 4px 10px rgba(255,99,56,.18)}.ghl-btn-secondary{border:1px solid #dbe2ea;background:#fff;color:#334155}.ghl-btn-danger{border:1px solid #fecaca;background:#fff;color:#dc2626}
        .ghl-sync-status{padding:9px 12px}.ghl-progress-row{display:flex;align-items:center;gap:9px}.ghl-progress-row>b{min-width:35px;text-align:right;font-size:11px;color:#ff6338}.ghl-sync-progress{height:5px;flex:1;overflow:hidden;border-radius:999px;background:#e5e7eb}.ghl-sync-progress i{display:block;height:100%;border-radius:inherit;background:#ff6338;transition:width .28s ease}.is-running .ghl-sync-progress i{background-image:linear-gradient(90deg,#ff6338,#ff8b69,#ff6338);background-size:200% 100%;animation:ghlFlow 1.2s linear infinite}
        .ghl-stats{display:flex;align-items:center;gap:7px 13px;flex-wrap:wrap;margin-top:8px;font-size:10px;color:#64748b}.ghl-stats span{white-space:nowrap}.ghl-stats b{font-size:12px;color:#111827}.dark .ghl-stats b{color:#f8fafc}.ghl-stats .is-error,.ghl-stats .is-error b{color:#dc2626}.ghl-divider{width:1px;height:17px;background:#e5e7eb}.ghl-current{display:flex;align-items:center;gap:7px;min-width:0;margin-top:8px;padding:6px 8px;border-radius:7px;background:#fff7ed;color:#9a3412;font-size:10px}.ghl-current span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ghl-current.is-neutral{background:#f8fafc;color:#64748b}.ghl-alert{margin-top:7px;padding:7px 8px;border:1px solid #fed7aa;border-radius:7px;background:#fff7ed;color:#9a3412;font-size:10px}.ghl-errors{margin-top:7px;padding:7px 8px;border:1px solid #fecaca;border-radius:7px;background:#fef2f2;font-size:10px}.ghl-errors summary{cursor:pointer;font-weight:800}.ghl-errors code{display:block;margin-top:5px;white-space:normal;word-break:break-word;font-size:10px}.ghl-ready{padding:8px 10px;border:1px dashed #cbd5e1;border-radius:9px;color:#64748b;font-size:10px}.ghl-spinner{width:12px;height:12px;flex:0 0 auto;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:ghlSpin .65s linear infinite}
        @keyframes ghlSpin{to{transform:rotate(360deg)}}@keyframes ghlPulse{50%{box-shadow:0 0 0 7px rgba(255,99,56,0)}}@keyframes ghlFlow{to{background-position:-200% 0}}
        @media(max-width:760px){.ghl-sync-bar{align-items:flex-start;flex-direction:column}.ghl-sync-actions{width:100%}.ghl-btn{flex:1}.ghl-divider{display:none}.ghl-stats{gap:6px 10px}}
    </style>
</div>