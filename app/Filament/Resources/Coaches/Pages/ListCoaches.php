<?php

namespace App\Filament\Resources\Coaches\Pages;

use App\Filament\Resources\Coaches\CoachResource;
use App\Models\Coach;
use App\Models\School;
use App\Services\CoachSpreadsheetService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class ListCoaches extends ListRecords
{
    protected static string $resource = CoachResource::class;
    protected string $view = 'filament.resources.coaches.pages.list-coaches';

    #[Url(as: 'sport')]
    public ?string $selectedSport = null;

    #[Url(as: 'division')]
    public string $selectedDivision = '';

    #[Url(as: 'conference')]
    public string $selectedConference = '';

    #[Url(as: 'view')]
    public string $directoryView = 'list';

    public string $sheetSearch = '';

    public function mount(): void
    {
        parent::mount();

        if (filled($this->selectedSport) && ! array_key_exists($this->selectedSport, CoachResource::sportOptions())) {
            $this->selectedSport = null;
        }

        if (! in_array($this->directoryView, ['list', 'excel'], true)) {
            $this->directoryView = 'list';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import CSV / Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->disabled(fn (): bool => blank($this->selectedSport))
                ->tooltip(fn (): ?string => blank($this->selectedSport) ? 'Select a sport first.' : null)
                ->url(fn (): string => CoachResource::getUrl('import', ['sport' => $this->selectedSport])),

            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (CoachSpreadsheetService $service) => $this->downloadExport('csv', $service)),

            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->action(fn (CoachSpreadsheetService $service) => $this->downloadExport('xlsx', $service)),

            CreateAction::make()
                ->label('Add coach')
                ->url(fn (): string => CoachResource::getUrl('create', ['sport' => $this->selectedSport])),
        ];
    }

    public function downloadExport(string $format, CoachSpreadsheetService $service)
    {
        $path = $service->export($format, $this->selectedSport);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with('school:id,name')
            ->when(filled($this->selectedSport), fn (Builder $query): Builder => $query->where('sport', $this->selectedSport))
            ->when(filled($this->selectedDivision), fn (Builder $query): Builder => $query->where('division', $this->selectedDivision))
            ->when(filled($this->selectedConference), fn (Builder $query): Builder => $query->where('conference', $this->selectedConference));
    }

    public function selectSport(?string $sport): void
    {
        $this->selectedSport = filled($sport) ? $sport : null;
        $this->selectedDivision = '';
        $this->selectedConference = '';
        $this->resetTable();
        $this->resetPage();
    }

    public function selectDivision(string $division): void
    {
        $this->selectedDivision = $division;
        $this->resetTable();
        $this->resetPage();
    }

    public function updatedSelectedConference(): void
    {
        $this->resetTable();
        $this->resetPage();
    }

    public function setDirectoryView(string $view): void
    {
        if (in_array($view, ['list', 'excel'], true)) {
            $this->directoryView = $view;
        }
    }

    public function getSportTabsProperty(): array
    {
        $counts = Cache::remember('coach-directory:sport-counts', now()->addMinutes(2), fn () =>
            Coach::query()
                ->select('sport', DB::raw('COUNT(*) as aggregate'))
                ->whereNull('deleted_at')
                ->groupBy('sport')
                ->pluck('aggregate', 'sport')
                ->all()
        );

        $total = array_sum(array_map('intval', $counts));

        return [
            ['value' => null, 'label' => 'All Sports', 'count' => $total],
            ...collect(CoachResource::sportOptions())
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                    'count' => (int) ($counts[$value] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }

    public function getDivisionTabsProperty(): array
    {
        return ['' => 'All Divisions'] + CoachResource::divisionOptions();
    }

    public function getConferenceOptionsProperty(): array
    {
        $sportKey = $this->selectedSport ?: 'all';

        return Cache::remember("coach-directory:conferences:{$sportKey}", now()->addMinutes(5), fn () =>
            Coach::query()
                ->when(filled($this->selectedSport), fn (Builder $query): Builder => $query->where('sport', $this->selectedSport))
                ->whereNotNull('conference')
                ->where('conference', '<>', '')
                ->distinct()
                ->orderBy('conference')
                ->pluck('conference')
                ->all()
        );
    }

    public function getSelectedSportLabelProperty(): string
    {
        return $this->selectedSport
            ? (CoachResource::sportOptions()[$this->selectedSport] ?? str($this->selectedSport)->headline()->toString())
            : 'All Sports';
    }

    public function getExcelRowsProperty()
    {
        return Coach::query()
            ->with('school:id,name')
            ->when(filled($this->selectedSport), fn (Builder $query): Builder => $query->where('sport', $this->selectedSport))
            ->when(filled($this->selectedDivision), fn (Builder $query): Builder => $query->where('division', $this->selectedDivision))
            ->when(filled($this->selectedConference), fn (Builder $query): Builder => $query->where('conference', $this->selectedConference))
            ->when(filled($this->sheetSearch), function (Builder $query): void {
                $search = trim($this->sheetSearch);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('display_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('conference', 'like', "%{$search}%")
                        ->orWhere('division', 'like', "%{$search}%")
                        ->orWhereHas('school', fn (Builder $school): Builder => $school->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(100)
            ->get();
    }

    public function getSchoolOptionsProperty(): array
    {
        return Cache::remember('coach-directory:school-options', now()->addMinutes(10), fn () =>
            School::query()->orderBy('name')->pluck('name', 'id')->all()
        );
    }

    public function updateCoachCell(int $coachId, string $field, mixed $value): void
    {
        $editableFields = [
            'first_name', 'last_name', 'email', 'phone', 'title',
            'division', 'conference', 'school_id', 'is_active',
        ];

        abort_unless(in_array($field, $editableFields, true), 403);

        $coach = Coach::query()->findOrFail($coachId);
        $value = is_string($value) ? trim($value) : $value;

        validator([$field => $value], match ($field) {
            'first_name', 'last_name' => [$field => ['required', 'string', 'max:255']],
            'email' => [$field => ['nullable', 'email', 'max:255', Rule::unique('coaches', 'email')->ignore($coach->id)]],
            'phone', 'title', 'conference' => [$field => ['nullable', 'string', 'max:255']],
            'division' => [$field => ['nullable', Rule::in(array_keys(CoachResource::divisionOptions()))]],
            'school_id' => [$field => ['nullable', 'integer', 'exists:schools,id']],
            'is_active' => [$field => ['boolean']],
            default => [],
        })->validate();

        if ($field === 'is_active') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($field === 'school_id' && blank($value)) {
            $value = null;
        }

        $coach->forceFill([$field => $value])->save();

        // Avoid rebuilding the whole page after every spreadsheet cell save.
        $this->skipRender();
        $this->dispatch('coach-cell-saved', coachId: $coachId, field: $field);
    }
}