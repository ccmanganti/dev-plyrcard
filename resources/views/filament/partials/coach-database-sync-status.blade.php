@php
    $sync = $this->recruitingReloadStatus;
@endphp

@if($sync['visible'] ?? false)
    <section
        class="rc-sync-monitor rc-sync-monitor--{{ $sync['tone'] ?? 'neutral' }}"
        wire:poll.3s.visible="pollRealtime"
        wire:key="coach-database-sync-monitor-{{ $sync['status'] ?? 'checking' }}"
        role="status"
        aria-live="polite"
        aria-label="Coach Database background reload status"
    >
        <div class="rc-sync-monitor__head">
            <div class="rc-sync-monitor__signal" aria-hidden="true"></div>

            <div>
                <div class="rc-sync-monitor__title-row">
                    <strong class="rc-sync-monitor__title">{{ $sync['title'] ?? 'Coach Database reload' }}</strong>
                    <span class="rc-sync-monitor__badge">{{ $sync['status_label'] ?? 'Checking status' }}</span>
                </div>
                <div class="rc-sync-monitor__stage">{{ $sync['stage'] ?? '' }}</div>
                <div class="rc-sync-monitor__message">{{ $sync['message'] ?? '' }}</div>
            </div>

            <div class="rc-sync-monitor__percent">
                @if($sync['indeterminate'] ?? false)
                    Starting…
                @else
                    {{ (int) ($sync['percent'] ?? 1) }}%
                @endif
            </div>
        </div>

        <div class="rc-sync-monitor__bar {{ ($sync['indeterminate'] ?? false) ? 'is-indeterminate' : '' }}" aria-hidden="true">
            <span style="width: {{ (int) ($sync['percent'] ?? 1) }}%"></span>
        </div>

        <div class="rc-sync-monitor__meta">
            <span class="rc-sync-monitor__meta-item"><strong>{{ number_format((int) ($sync['loaded_schools'] ?? 0)) }}</strong> schools loaded</span>
            <span class="rc-sync-monitor__meta-item"><strong>{{ number_format((int) ($sync['loaded_contacts'] ?? 0)) }}</strong> coaches loaded</span>
            <span class="rc-sync-monitor__meta-item"><strong>{{ number_format((int) ($sync['loaded_pages'] ?? 0)) }}</strong> pages processed</span>
            <span class="rc-sync-monitor__meta-item"><strong>{{ $sync['launch_driver_label'] ?? 'Automatic' }}</strong> mode</span>
            <span class="rc-sync-monitor__meta-item"><strong>{{ $sync['heartbeat_label'] ?? 'No heartbeat yet' }}</strong> heartbeat</span>
            @if(! empty($sync['elapsed_label']))
                <span class="rc-sync-monitor__meta-item"><strong>{{ $sync['elapsed_label'] }}</strong> elapsed</span>
            @endif
        </div>

        @if(! empty($sync['worker_hint']))
            <div class="rc-sync-monitor__hint">
                <span>{{ $sync['worker_hint'] }}</span>
                @if($sync['can_clear'] ?? false)
                    <button type="button" class="rc-sync-monitor__action" wire:click="clearStuckRecruitingSync">
                        Clear status
                    </button>
                @endif
            </div>
        @endif
    </section>
@endif
