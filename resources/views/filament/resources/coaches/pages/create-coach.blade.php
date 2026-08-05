<x-filament-panels::page>
    <style>
        .coach-create-sync-overlay{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:1.5rem;background:rgba(15,23,42,.42);backdrop-filter:blur(5px)}
        .coach-create-sync-overlay[style*="display: flex"]{display:flex!important}
        .coach-create-sync-card{width:min(34rem,100%);border-radius:1.25rem;background:var(--fi-body-bg,#fff);padding:1.5rem;box-shadow:0 24px 70px rgba(15,23,42,.24);border:1px solid rgba(148,163,184,.25)}
        .coach-create-sync-spinner{width:2.5rem;height:2.5rem;border-radius:999px;border:3px solid rgba(255,99,56,.18);border-top-color:#ff6338;animation:coach-sync-spin .75s linear infinite;margin-bottom:1rem}
        .coach-create-sync-title{font-size:1.05rem;font-weight:800;color:var(--fi-color-gray-950,#0f172a)}
        .coach-create-sync-copy{margin-top:.45rem;color:var(--fi-color-gray-600,#64748b);line-height:1.55}
        .coach-create-sync-bar{height:.4rem;margin-top:1rem;border-radius:999px;overflow:hidden;background:rgba(148,163,184,.18)}
        .coach-create-sync-bar::after{content:"";display:block;width:42%;height:100%;border-radius:inherit;background:#ff6338;animation:coach-sync-slide 1.15s ease-in-out infinite}
        @keyframes coach-sync-spin{to{transform:rotate(360deg)}}
        @keyframes coach-sync-slide{0%{transform:translateX(-110%)}100%{transform:translateX(340%)}}
    </style>

    <div wire:loading.flex wire:target="create,createAnother" class="coach-create-sync-overlay">
        <div class="coach-create-sync-card">
            <div class="coach-create-sync-spinner"></div>
            <div class="coach-create-sync-title">Saving coach and checking configured GHL subaccounts</div>
            <div class="coach-create-sync-copy">
                Comparing the coach email and school name, grouping accounts that share the same API key, and preparing one pending synchronization target per unique subaccount.
            </div>
            <div class="coach-create-sync-bar"></div>
        </div>
    </div>

    {{ $this->content }}
</x-filament-panels::page>