<link rel="stylesheet" href="{{ asset('css/recruiting-center-ui.css') }}?v={{ filemtime(public_path('css/recruiting-center-ui.css')) }}">

<div id="rc-ui-progress" wire:ignore aria-hidden="true"></div>

@if ($isRefreshingRemoteData || $isLoadingTemplates)
    <span class="rc-ui-poll-anchor" wire:poll.2s="pollDeferredUiData" aria-hidden="true"></span>
@endif

<div
    id="rc-ui-instant-shell"
    wire:ignore
    aria-hidden="true"
    data-kind="drawer"
    data-rc-modal="loading"
    data-rc-modal-id="instant-shell"
>
    <button
        type="button"
        class="rc-ui-shell-backdrop"
        data-rc-shell-close
        aria-label="Close loading view"
        style="position:absolute;inset:0;border:0;background:transparent;cursor:default"
    ></button>

    <section
        class="rc-ui-shell-panel"
        role="dialog"
        aria-modal="true"
        aria-label="Loading details"
    >
        <header class="rc-ui-shell-head">
            <div>
                <strong id="rc-ui-shell-title">Loading details</strong>
                <span id="rc-ui-shell-copy">The view is open. Current data is being prepared.</span>
            </div>

            <button
                type="button"
                class="rc-ui-shell-close"
                data-rc-shell-close
                aria-label="Close"
            >
                ×
            </button>
        </header>

        <div class="rc-ui-shell-body">
            <div class="rc-ui-shell-hero">
                <span class="rc-ui-shell-circle"></span>

                <span style="display:grid;gap:.55rem">
                    <span class="rc-ui-shell-line is-title"></span>
                    <span class="rc-ui-shell-line is-short"></span>
                </span>
            </div>

            <div class="rc-ui-shell-grid">
                <span class="rc-ui-shell-block"></span>
                <span class="rc-ui-shell-block"></span>
            </div>

            <span class="rc-ui-shell-block"></span>
            <span class="rc-ui-shell-block"></span>
            <span class="rc-ui-shell-block"></span>
        </div>
    </section>
</div>

<div class="rc-ui-deferred-toast {{ (($section ?? '') !== 'conversations' && $isRefreshingRemoteData && blank($selectedSchoolId ?? null)) ? 'is-visible' : 'is-suppressed' }}">
    {{ $activeUiOperation ?: 'Updating the current view…' }}
</div>

<script
    src="{{ asset('js/recruiting-center-ui.js') }}?v={{ filemtime(public_path('js/recruiting-center-ui.js')) }}"
    defer
></script>