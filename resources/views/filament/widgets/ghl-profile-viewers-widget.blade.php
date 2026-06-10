<x-filament-widgets::widget>
    <x-filament::section
        heading="Profile viewers"
        description="Pull contacts from GHL that have the viewed profile tag."
    >
        <style>
            .ghl-viewers-widget { display: flex; flex-direction: column; gap: 1rem; }
            .ghl-viewers-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
            .ghl-viewers-summary { display: flex; flex-direction: column; gap: 0.25rem; }
            .ghl-viewers-count { margin: 0; font-size: 2rem; line-height: 1; font-weight: 800; color: #fff; }
            .ghl-viewers-subtle { margin: 0; font-size: 0.85rem; color: #94a3b8; }
            .ghl-viewers-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
            .ghl-viewers-alert { border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.25); background: rgba(239, 68, 68, 0.08); padding: 1rem; color: #fecaca; font-size: 0.9rem; font-weight: 600; }
            .ghl-viewers-empty { border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.03); padding: 1rem; color: #cbd5e1; font-size: 0.9rem; }
            .ghl-viewers-table-wrap { overflow-x: auto; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.08); }
            .ghl-viewers-table { width: 100%; border-collapse: collapse; min-width: 900px; background: rgba(255, 255, 255, 0.02); }
            .ghl-viewers-table th { text-align: left; padding: 0.85rem 1rem; font-size: 0.75rem; letter-spacing: 0.04em; text-transform: uppercase; color: #94a3b8; background: rgba(255, 255, 255, 0.04); border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
            .ghl-viewers-table td { padding: 0.9rem 1rem; font-size: 0.88rem; color: #e5e7eb; border-bottom: 1px solid rgba(255, 255, 255, 0.06); vertical-align: top; }
            .ghl-viewers-table tr:last-child td { border-bottom: 0; }
            .ghl-viewers-name { font-weight: 700; color: #fff; }
            .ghl-viewers-email { color: #fb923c; font-weight: 600; }
            .ghl-viewers-tags { display: flex; flex-wrap: wrap; gap: 0.35rem; }
            .ghl-viewers-tag { display: inline-flex; align-items: center; border-radius: 999px; padding: 0.2rem 0.5rem; font-size: 0.72rem; font-weight: 700; color: #fed7aa; background: rgba(249, 115, 22, 0.12); border: 1px solid rgba(249, 115, 22, 0.18); white-space: nowrap; }
        </style>

        <div class="ghl-viewers-widget">
            <div class="ghl-viewers-header">
                <div class="ghl-viewers-summary">
                    <p class="ghl-viewers-count">{{ number_format($count) }}</p>
                    <p class="ghl-viewers-subtle">GHL contacts tagged <strong>viewed profile</strong></p>
                </div>

                <div class="ghl-viewers-actions">
                    @if ($loaded)
                        <x-filament::button type="button" icon="heroicon-o-arrow-path" color="gray" wire:click="refreshProfileViewers" wire:loading.attr="disabled">
                            Refresh from GHL
                        </x-filament::button>
                    @else
                        <x-filament::button type="button" icon="heroicon-o-eye" color="primary" wire:click="loadProfileViewers" wire:loading.attr="disabled">
                            Show profile viewers
                        </x-filament::button>
                    @endif
                </div>
            </div>

            <div wire:loading wire:target="loadProfileViewers,refreshProfileViewers">
                <div class="ghl-viewers-empty">Pulling latest contacts from GHL...</div>
            </div>

            @if ($error)
                <div class="ghl-viewers-alert">{{ $error }}</div>
            @endif

            @if (! $loaded && ! $error)
                <div class="ghl-viewers-empty">
                    Click <strong>Show profile viewers</strong> to pull contacts from GHL using this player's saved Location ID and API key.
                </div>
            @elseif ($loaded && ! $error && count($contacts) === 0)
                <div class="ghl-viewers-empty">No GHL contacts were found with the <strong>viewed profile</strong> tag.</div>
            @elseif ($loaded && ! $error)
                <div class="ghl-viewers-table-wrap">
                    <table class="ghl-viewers-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>School / Company</th>
                                <th>Title</th>
                                <th>Conference</th>
                                <th>Division</th>
                                <th>Tags</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contacts as $contact)
                                <tr>
                                    <td><span class="ghl-viewers-name">{{ $contact['name'] ?: '-' }}</span></td>
                                    <td>
                                        @if (! empty($contact['email']))
                                            <span class="ghl-viewers-email">{{ $contact['email'] }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $contact['school_or_company'] ?? '-' }}</td>
                                    <td>{{ $contact['title'] ?? '-' }}</td>
                                    <td>{{ $contact['conference'] ?? '-' }}</td>
                                    <td>{{ $contact['division'] ?? '-' }}</td>
                                    <td>
                                        <div class="ghl-viewers-tags">
                                            @foreach (($contact['tags'] ?? []) as $tag)
                                                <span class="ghl-viewers-tag">{{ $tag }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
