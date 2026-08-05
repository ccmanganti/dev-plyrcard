<x-filament-panels::page>
    <style>
        .coach-db-top-tabs {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            border-bottom: 1px solid rgba(148, 163, 184, .28);
            margin-bottom: 1.25rem;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .coach-db-top-tabs::-webkit-scrollbar { display: none; }
        .coach-db-top-tab {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            min-height: 3rem;
            padding: 0 .35rem;
            color: #64748b;
            font-size: .95rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            transition: color .15s ease, transform .15s ease;
        }
        .coach-db-top-tab:hover {
            color: #ff6338;
            transform: translateY(-1px);
        }
        .coach-db-top-tab.is-active { color: #ff6338; }
        .coach-db-top-tab.is-active::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;
            height: 3px;
            border-radius: 999px 999px 0 0;
            background: #ff6338;
        }
        .coach-db-top-tab svg {
            width: 1.15rem;
            height: 1.15rem;
            flex: 0 0 auto;
        }
        .dark .coach-db-top-tabs { border-bottom-color: rgba(71, 85, 105, .7); }
        .dark .coach-db-top-tab { color: #94a3b8; }
        .dark .coach-db-top-tab:hover,
        .dark .coach-db-top-tab.is-active { color: #ff835f; }
    </style>

    <nav class="coach-db-top-tabs" aria-label="Coach database sections">
        <a
            class="coach-db-top-tab"
            href="{{ \App\Filament\Resources\Coaches\CoachResource::getUrl('index') }}"
            wire:navigate
        >
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-4-12a4 4 0 0 1 0 7.75" />
            </svg>
            <span>Coaches</span>
        </a>

        <a
            class="coach-db-top-tab is-active"
            href="{{ \App\Filament\Resources\CoachDirectorySchools\CoachDirectorySchoolResource::getUrl('index') }}"
            aria-current="page"
            wire:navigate
        >
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 10 9-6 9 6-9 6-9-6Zm3 3.5V18l6 3 6-3v-4.5M21 10v6" />
            </svg>
            <span>Schools</span>
        </a>
    </nav>

    {{ $this->table }}
</x-filament-panels::page>
