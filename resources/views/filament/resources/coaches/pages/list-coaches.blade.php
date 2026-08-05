<x-filament-panels::page>
    <div
        class="cd-shell"
        x-data="{
            saved: false,
            activeSport: @js($selectedSport),
            activeDivision: @js($selectedDivision),
            activeView: @js($directoryView),
            busyLabel: 'Updating directory…',
            setBusy(label) { this.busyLabel = label },
        }"
        x-on:coach-cell-saved.window="saved = true; setTimeout(() => saved = false, 900)"
    >
        <div class="cd-global-progress" wire:loading.delay.shorter>
            <span></span>
            <strong x-text="busyLabel"></strong>
        </div>

        <nav class="cd-resource-tabs" aria-label="Coach database sections">
            <a href="{{ \App\Filament\Resources\Coaches\CoachResource::getUrl() }}" class="is-active">
                <x-filament::icon icon="heroicon-o-user-group" />
                <span>Coaches</span>
            </a>
            <a href="{{ \App\Filament\Resources\CoachDirectorySchools\CoachDirectorySchoolResource::getUrl() }}">
                <x-filament::icon icon="heroicon-o-academic-cap" />
                <span>Schools</span>
            </a>
        </nav>

        <section class="cd-directory-panel">
            <div class="cd-directory-head">
                <div>
                    <div class="cd-eyebrow">Coach Directory</div>
                    <h2>{{ $this->selectedSportLabel }}</h2>
                    <p>Browse, filter, import, export, and maintain local coach records.</p>
                </div>

                <div class="cd-head-actions">
                    <div class="cd-view-toggle" role="group" aria-label="Directory view">
                    <button type="button" x-on:click="activeView='list'; setBusy('Opening list view…'); $wire.setDirectoryView('list')" x-bind:class="activeView === 'list' ? 'is-active' : ''">
                        <x-filament::icon icon="heroicon-o-list-bullet" /><span>List</span>
                    </button>
                    <button type="button" x-on:click="activeView='excel'; setBusy('Opening Excel view…'); $wire.setDirectoryView('excel')" x-bind:class="activeView === 'excel' ? 'is-active' : ''">
                        <x-filament::icon icon="heroicon-o-table-cells" /><span>Excel</span>
                    </button>
                    </div>
                </div>
            </div>

            <livewire:coach-ghl-sync-panel />

            <div class="cd-scroll-nav" x-data="{ left:false,right:true, sync(){ const e=this.$refs.track; this.left=e.scrollLeft>2; this.right=e.scrollLeft+e.clientWidth<e.scrollWidth-2 }, move(d){ this.$refs.track.scrollBy({left:d*this.$refs.track.clientWidth*.72,behavior:'smooth'}); setTimeout(()=>this.sync(),260) } }" x-init="$nextTick(()=>sync())" x-on:resize.window.debounce.150ms="sync()" :class="{'has-left':left,'has-right':right}">
                <button type="button" class="cd-scroll-button" x-on:click="move(-1)" :disabled="!left" aria-label="Previous sports"><x-filament::icon icon="heroicon-o-chevron-left" /></button>
                <div class="cd-sport-tabs" x-ref="track" x-on:scroll.debounce.50ms="sync()" aria-label="Sport filter">
                    @foreach ($this->sportTabs as $tab)
                        <button
                            type="button"
                            wire:key="sport-tab-{{ $tab['value'] ?? 'all' }}"
                            x-on:click="activeSport={{ $tab['value'] === null ? 'null' : \Illuminate\Support\Js::from($tab['value']) }}; activeDivision=''; setBusy('Filtering coaches…'); $wire.selectSport({{ $tab['value'] === null ? 'null' : \Illuminate\Support\Js::from($tab['value']) }})"
                            x-bind:class="activeSport === {{ $tab['value'] === null ? 'null' : \Illuminate\Support\Js::from($tab['value']) }} ? 'is-active' : ''"
                            class="cd-sport-tab"
                        ><span>{{ $tab['label'] }}</span><strong>{{ number_format($tab['count']) }}</strong></button>
                    @endforeach
                </div>
                <button type="button" class="cd-scroll-button" x-on:click="move(1)" :disabled="!right" aria-label="Next sports"><x-filament::icon icon="heroicon-o-chevron-right" /></button>
            </div>

            <div class="cd-filter-row">
                <div class="cd-scroll-nav" x-data="{ left:false,right:true, sync(){ const e=this.$refs.track; this.left=e.scrollLeft>2; this.right=e.scrollLeft+e.clientWidth<e.scrollWidth-2 }, move(d){ this.$refs.track.scrollBy({left:d*this.$refs.track.clientWidth*.72,behavior:'smooth'}); setTimeout(()=>this.sync(),260) } }" x-init="$nextTick(()=>sync())" x-on:resize.window.debounce.150ms="sync()" :class="{'has-left':left,'has-right':right}">
                    <button type="button" class="cd-scroll-button" x-on:click="move(-1)" :disabled="!left" aria-label="Previous divisions"><x-filament::icon icon="heroicon-o-chevron-left" /></button>
                    <div class="cd-division-tabs" x-ref="track" x-on:scroll.debounce.50ms="sync()" aria-label="Division filter">
                        @foreach ($this->divisionTabs as $divisionValue => $divisionLabel)
                            <button
                                type="button"
                                wire:key="division-tab-{{ md5($divisionValue) }}"
                                x-on:click="activeDivision={{ \Illuminate\Support\Js::from($divisionValue) }}; setBusy('Filtering divisions…'); $wire.selectDivision({{ \Illuminate\Support\Js::from($divisionValue) }})"
                                x-bind:class="activeDivision === {{ \Illuminate\Support\Js::from($divisionValue) }} ? 'is-active' : ''"
                            >{{ $divisionLabel }}</button>
                        @endforeach
                    </div>
                    <button type="button" class="cd-scroll-button" x-on:click="move(1)" :disabled="!right" aria-label="Next divisions"><x-filament::icon icon="heroicon-o-chevron-right" /></button>
                </div>
                <select wire:model.live="selectedConference" x-on:change="setBusy('Filtering conferences…')" class="cd-conference-select" aria-label="Conference filter">
                    <option value="">All Conferences</option>
                    @foreach ($this->conferenceOptions as $conference)<option value="{{ $conference }}">{{ $conference }}</option>@endforeach
                </select>
            </div>

            <div class="cd-status-line">
                <span wire:loading.remove>Showing <strong>{{ $this->selectedSportLabel }}</strong>@if($selectedDivision !== '') · {{ $selectedDivision }} @endif @if($selectedConference !== '') · {{ $selectedConference }} @endif</span>
                <span wire:loading class="cd-loading"><i></i><span x-text="busyLabel"></span></span>
                <span x-show="saved" x-transition class="cd-saved">Saved</span>
            </div>
        </section>

        @if ($directoryView === 'list')
            <div class="cd-table-wrap cd-content-stage">
                <div class="cd-content-loading" wire:loading.delay wire:target="selectSport,selectDivision,selectedConference,setDirectoryView"><span></span></div>
                {{ $this->table }}
            </div>
        @else
            <section class="cd-excel-panel cd-content-stage">
                <div class="cd-content-loading" wire:loading.delay wire:target="selectSport,selectDivision,selectedConference,setDirectoryView,sheetSearch"><span></span></div>
                <div class="cd-excel-head">
                    <div><h3>Excel-style editor</h3><p>Fast inline editing. Changes save on blur. Up to 100 filtered rows are loaded.</p></div>
                    <label class="cd-sheet-search"><x-filament::icon icon="heroicon-o-magnifying-glass" /><input type="search" wire:model.live.debounce.500ms="sheetSearch" x-on:input="setBusy('Searching sheet…')" placeholder="Search this sheet…"></label>
                </div>
                <div class="cd-sheet-scroll">
                    <table class="cd-sheet">
                        <thead><tr><th>First name</th><th>Last name</th><th>School</th><th>Title</th><th>Division</th><th>Conference</th><th>Email</th><th>Phone</th><th>Active</th></tr></thead>
                        <tbody>
                        @forelse ($this->excelRows as $coach)
                            <tr wire:key="coach-sheet-row-{{ $coach->id }}">
                                <td><input class="cd-cell" value="{{ $coach->first_name }}" wire:change="updateCoachCell({{ $coach->id }}, 'first_name', $event.target.value)"></td>
                                <td><input class="cd-cell" value="{{ $coach->last_name }}" wire:change="updateCoachCell({{ $coach->id }}, 'last_name', $event.target.value)"></td>
                                <td><select class="cd-cell" wire:change="updateCoachCell({{ $coach->id }}, 'school_id', $event.target.value)"><option value="">No school</option>@foreach ($this->schoolOptions as $schoolId => $schoolName)<option value="{{ $schoolId }}" @selected((int) $coach->school_id === (int) $schoolId)>{{ $schoolName }}</option>@endforeach</select></td>
                                <td><input class="cd-cell" value="{{ $coach->title }}" wire:change="updateCoachCell({{ $coach->id }}, 'title', $event.target.value)"></td>
                                <td><select class="cd-cell" wire:change="updateCoachCell({{ $coach->id }}, 'division', $event.target.value)"><option value="">—</option>@foreach (\App\Filament\Resources\Coaches\CoachResource::divisionOptions() as $value => $label)<option value="{{ $value }}" @selected($coach->division === $value)>{{ $label }}</option>@endforeach</select></td>
                                <td><input class="cd-cell" value="{{ $coach->conference }}" wire:change="updateCoachCell({{ $coach->id }}, 'conference', $event.target.value)"></td>
                                <td><input type="email" class="cd-cell" value="{{ $coach->email }}" wire:change="updateCoachCell({{ $coach->id }}, 'email', $event.target.value)"></td>
                                <td><input class="cd-cell" value="{{ $coach->phone }}" wire:change="updateCoachCell({{ $coach->id }}, 'phone', $event.target.value)"></td>
                                <td class="cd-check-cell"><input type="checkbox" @checked($coach->is_active) wire:change="updateCoachCell({{ $coach->id }}, 'is_active', $event.target.checked)"></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="cd-empty">No coaches found for these filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    <style>
        .cd-shell{display:grid;gap:18px;--cd-accent:#ff6338;--cd-text:#111827;--cd-muted:#6b7280;--cd-border:#e5e7eb;--cd-surface:#fff;--cd-soft:#f8fafc}.dark .cd-shell{--cd-text:#f9fafb;--cd-muted:#9ca3af;--cd-border:rgba(255,255,255,.1);--cd-surface:#111827;--cd-soft:rgba(255,255,255,.04)}
        .cd-global-progress{position:fixed;z-index:9999;top:0;left:0;right:0;height:4px;background:rgba(255,99,56,.15);overflow:hidden}.cd-global-progress span{display:block;width:38%;height:100%;background:var(--cd-accent);animation:cd-progress 1s infinite ease-in-out}.cd-global-progress strong{position:fixed;right:18px;top:14px;background:#111827;color:#fff;border-radius:999px;padding:7px 11px;font-size:11px;box-shadow:0 8px 24px rgba(15,23,42,.2)}@keyframes cd-progress{0%{transform:translateX(-120%)}100%{transform:translateX(360%)}}
        .cd-resource-tabs{display:flex;align-items:center;gap:6px;border-bottom:1px solid var(--cd-border);padding:0 2px}.cd-resource-tabs a{position:relative;display:inline-flex;align-items:center;gap:8px;padding:12px 16px;text-decoration:none;color:var(--cd-muted);font-size:14px;font-weight:750;transition:color .14s ease,background .14s ease}.cd-resource-tabs a svg{width:18px;height:18px}.cd-resource-tabs a:hover{color:var(--cd-text);background:var(--cd-soft)}.cd-resource-tabs a.is-active{color:var(--cd-accent)}.cd-resource-tabs a.is-active:after{content:"";position:absolute;left:10px;right:10px;bottom:-1px;height:3px;border-radius:999px 999px 0 0;background:var(--cd-accent)}
        .cd-directory-panel,.cd-excel-panel,.cd-table-wrap{background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.05)}.cd-directory-panel{padding:20px;overflow:hidden}.cd-directory-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.cd-head-actions{display:flex;align-items:flex-start;justify-content:flex-end;gap:10px;flex-wrap:wrap}.cd-eyebrow{font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--cd-accent)}.cd-directory-head h2{margin:4px 0 2px;font-size:22px;line-height:1.2;color:var(--cd-text);font-weight:800}.cd-directory-head p,.cd-excel-head p{margin:0;color:var(--cd-muted);font-size:13px}
        .cd-view-toggle{display:inline-flex;border:1px solid var(--cd-border);padding:4px;border-radius:12px;background:var(--cd-soft);flex:none}.cd-view-toggle button{display:flex;align-items:center;gap:7px;border:0;background:transparent;padding:8px 13px;border-radius:9px;color:var(--cd-muted);font-weight:700;font-size:13px;cursor:pointer;transition:.12s ease}.cd-view-toggle button svg{width:17px;height:17px}.cd-view-toggle button.is-active{background:var(--cd-surface);color:var(--cd-accent);box-shadow:0 2px 9px rgba(15,23,42,.08);transform:translateY(-1px)}
        .cd-scroll-nav{position:relative;display:grid;grid-template-columns:34px minmax(0,1fr) 34px;align-items:center;gap:6px;margin-top:18px}.cd-scroll-nav:before,.cd-scroll-nav:after{content:"";position:absolute;top:0;bottom:0;width:54px;pointer-events:none;z-index:2;opacity:0;transition:opacity .2s ease}.cd-scroll-nav:before{left:40px;background:linear-gradient(90deg,var(--cd-surface),transparent)}.cd-scroll-nav:after{right:40px;background:linear-gradient(270deg,var(--cd-surface),transparent)}.cd-scroll-nav.has-left:before,.cd-scroll-nav.has-right:after{opacity:1}.cd-scroll-button{position:relative;z-index:3;width:34px;height:34px;border:1px solid var(--cd-border);border-radius:10px;background:var(--cd-surface);color:var(--cd-text);display:grid;place-items:center;cursor:pointer;transition:.14s ease}.cd-scroll-button:hover{border-color:var(--cd-accent);color:var(--cd-accent)}.cd-scroll-button:disabled{opacity:.3;cursor:default}.cd-scroll-button svg{width:17px;height:17px}
        .cd-sport-tabs,.cd-division-tabs{display:flex;gap:8px;overflow-x:auto;scroll-behavior:smooth;scrollbar-width:none;padding:2px}.cd-sport-tabs::-webkit-scrollbar,.cd-division-tabs::-webkit-scrollbar{display:none}.cd-sport-tab,.cd-division-tabs button{flex:none;border:1px solid var(--cd-border);background:var(--cd-surface);color:var(--cd-text);border-radius:999px;padding:9px 14px;font-size:13px;font-weight:750;cursor:pointer;transition:transform .1s ease,background .1s ease,color .1s ease,border-color .1s ease}.cd-sport-tab:active,.cd-division-tabs button:active{transform:scale(.97)}.cd-sport-tab{display:flex;align-items:center;gap:8px}.cd-sport-tab strong{min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:var(--cd-soft);display:grid;place-items:center;font-size:11px;color:var(--cd-muted)}.cd-sport-tab:hover,.cd-division-tabs button:hover{border-color:rgba(255,99,56,.5);color:var(--cd-accent)}.cd-sport-tab.is-active,.cd-division-tabs button.is-active{background:var(--cd-accent);border-color:var(--cd-accent);color:#fff;box-shadow:0 6px 14px rgba(255,99,56,.2)}.cd-sport-tab.is-active strong{background:rgba(255,255,255,.2);color:#fff}
        .cd-filter-row{display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:12px;align-items:center;margin-top:12px}.cd-filter-row .cd-scroll-nav{margin-top:0}.cd-conference-select{width:100%;border:1px solid var(--cd-border);border-radius:12px;background:var(--cd-surface);color:var(--cd-text);padding:10px 12px;font-size:13px}.cd-status-line{border-top:1px solid var(--cd-border);margin-top:16px;padding-top:13px;color:var(--cd-muted);font-size:12px;display:flex;align-items:center;gap:12px}.cd-loading{color:var(--cd-accent);display:inline-flex;align-items:center;gap:7px}.cd-loading i{width:13px;height:13px;border:2px solid rgba(255,99,56,.25);border-top-color:var(--cd-accent);border-radius:50%;animation:cd-spin .65s linear infinite}.cd-saved{margin-left:auto;color:#16a34a;font-weight:700}@keyframes cd-spin{to{transform:rotate(360deg)}}
        .cd-content-stage{position:relative;min-height:180px}.cd-content-loading{position:absolute;z-index:20;inset:0;background:rgba(255,255,255,.72);backdrop-filter:blur(1px);display:grid;place-items:center}.dark .cd-content-loading{background:rgba(17,24,39,.72)}.cd-content-loading span{width:34px;height:34px;border:3px solid rgba(255,99,56,.22);border-top-color:var(--cd-accent);border-radius:50%;animation:cd-spin .7s linear infinite}.cd-table-wrap{overflow:hidden}.cd-excel-panel{padding:18px}.cd-excel-head{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:14px}.cd-excel-head h3{margin:0;color:var(--cd-text);font-size:16px}.cd-sheet-search{display:flex;align-items:center;gap:8px;border:1px solid var(--cd-border);border-radius:10px;padding:8px 10px;min-width:260px}.cd-sheet-search svg{width:17px;height:17px;color:var(--cd-muted)}.cd-sheet-search input{border:0;outline:0;background:transparent;color:var(--cd-text);width:100%}.cd-sheet-scroll{overflow:auto;border:1px solid var(--cd-border);border-radius:12px;max-height:66vh}.cd-sheet{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}.cd-sheet th{position:sticky;top:0;z-index:2;text-align:left;padding:10px;background:var(--cd-soft);color:var(--cd-muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--cd-border)}.cd-sheet td{padding:3px;border-bottom:1px solid var(--cd-border);border-right:1px solid var(--cd-border);background:var(--cd-surface)}.cd-sheet tr:hover td{background:var(--cd-soft)}.cd-cell{width:100%;min-width:130px;border:0;border-radius:6px;background:transparent;color:var(--cd-text);padding:8px 9px;outline:0;font-size:13px}.cd-cell:focus{background:rgba(255,99,56,.08);box-shadow:inset 0 0 0 2px var(--cd-accent)}.cd-check-cell{text-align:center}.cd-check-cell input{width:17px;height:17px;accent-color:var(--cd-accent)}.cd-empty{text-align:center!important;padding:50px!important;color:var(--cd-muted)}
        @media(max-width:1050px){.cd-filter-row{grid-template-columns:1fr}.cd-directory-head,.cd-excel-head{align-items:flex-start;flex-direction:column}.cd-sheet-search{width:100%}}@media(max-width:640px){.cd-resource-tabs a{flex:1;justify-content:center;padding:11px 10px}.cd-directory-panel{padding:15px}.cd-scroll-nav{grid-template-columns:30px minmax(0,1fr) 30px}.cd-scroll-button{width:30px;height:30px}}
    </style>
</x-filament-panels::page>