<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class CoachSpreadsheetService
{
    public const IMPORT_FIELDS = [
        'school_name' => 'School',
        'school_logo_url' => 'School Logo URL',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'secondary_email' => 'Secondary Email',
        'phone' => 'Phone',
        'title' => 'Title',
        'division' => 'Division',
        'conference' => 'Conference',
        'verification_status' => 'Verification Status',
        'confidence_level' => 'Confidence Level',
        'audit_notes' => 'Audit Notes',
        'city' => 'Coach City',
        'state' => 'Coach State',
        'country' => 'Country',
        'website_url' => 'Website URL',
        'is_active' => 'Active',
        'notes' => 'Notes',
    ];

    public const EXPORT_HEADINGS = [
        'School', 'School Logo URL', 'First Name', 'Last Name', 'Display Name', 'Email', 'Secondary Email',
        'Phone', 'Title', 'Sport', 'Division', 'Conference', 'Verification Status',
        'Confidence Level', 'Audit Notes', 'Coach City', 'Coach State', 'Country',
        'Website URL', 'Active', 'GHL Sync Status', 'Notes', 'Updated At',
    ];

    public function analyze(string $path): array
    {
        $spreadsheet = $this->reader($path)->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $headers = [];

        for ($column = 1; $column <= $highestColumn; $column++) {
            $header = trim((string) $sheet->getCell([$column, 1])->getFormattedValue());
            if ($header !== '') {
                $headers[] = $header;
            }
        }

        if ($headers === []) {
            throw new RuntimeException('No header row was found.');
        }

        $preview = [];
        $totalRows = 0;

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $record = [];
            $hasValue = false;

            foreach ($headers as $index => $header) {
                $value = trim((string) $sheet->getCell([$index + 1, $row])->getFormattedValue());
                $record[$header] = $value;
                $hasValue = $hasValue || $value !== '';
            }

            if (! $hasValue) {
                continue;
            }

            $totalRows++;
            if (count($preview) < 5) {
                $preview[] = $record;
            }
        }

        $spreadsheet->disconnectWorksheets();

        return ['headers' => $headers, 'preview' => $preview, 'total_rows' => $totalRows];
    }

    public function suggestMapping(array $headers): array
    {
        $aliases = [
            'school_name' => ['school', 'school name', 'college', 'university'],
            'school_logo_url' => ['school logo', 'school logo url', 'logo', 'logo url', 'college logo', 'university logo'],
            'first_name' => ['first name', 'firstname', 'coach first name'],
            'last_name' => ['last name', 'lastname', 'coach last name'],
            'email' => ['email', 'coach email', 'email address'],
            'secondary_email' => ['secondary email', 'alternate email'],
            'phone' => ['phone', 'phone number'],
            'title' => ['title', 'coach title', 'position'],
            'division' => ['division'],
            'conference' => ['conference'],
            'verification_status' => ['verification status', 'verified status'],
            'confidence_level' => ['confidence level', 'confidence'],
            'audit_notes' => ['audit notes'],
            'city' => ['coach city', 'city'],
            'state' => ['coach state', 'state'],
            'country' => ['country'],
            'website_url' => ['website url', 'website'],
            'is_active' => ['active', 'is active'],
            'notes' => ['notes'],
        ];

        $normalized = collect($headers)
            ->mapWithKeys(fn ($header) => [$this->normalizeHeader($header) => $header]);

        $mapping = array_fill_keys(array_keys(self::IMPORT_FIELDS), '');

        foreach ($aliases as $field => $candidates) {
            foreach ($candidates as $candidate) {
                $match = $normalized[$this->normalizeHeader($candidate)] ?? null;
                if ($match) {
                    $mapping[$field] = $match;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Parse the spreadsheet once and save normalized rows to a temporary JSON file.
     * Later Livewire requests process this file in small batches, avoiding PHP's
     * 30-second request timeout on large imports.
     */
    public function prepareImport(string $path, array $mapping, string $sport, ?int $createdBy): array
    {
        $spreadsheet = $this->reader($path)->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [];

        for ($column = 1; $column <= Coordinate::columnIndexFromString($sheet->getHighestColumn()); $column++) {
            $header = trim((string) $sheet->getCell([$column, 1])->getFormattedValue());
            if ($header !== '') {
                $headers[$header] = $column;
            }
        }

        $rows = [];
        $skipped = 0;
        $errors = [];

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $source = [];
            $hasValue = false;

            foreach ($headers as $header => $column) {
                $value = trim((string) $sheet->getCell([$column, $row])->getFormattedValue());
                $source[$header] = $value;
                $hasValue = $hasValue || $value !== '';
            }

            if (! $hasValue) {
                $skipped++;
                continue;
            }

            $data = [];
            foreach (array_keys(self::IMPORT_FIELDS) as $field) {
                $mappedHeader = trim((string) ($mapping[$field] ?? ''));
                $data[$field] = $mappedHeader !== '' ? trim((string) ($source[$mappedHeader] ?? '')) : '';
            }

            $email = Str::lower(trim($data['email']));
            $firstName = trim($data['first_name']);
            $lastName = trim($data['last_name']);

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$row}: A valid email is required.";
                continue;
            }

            if ($firstName === '' || $lastName === '') {
                $errors[] = "Row {$row}: First Name and Last Name are required.";
                continue;
            }

            $rows[] = [
                'source_row' => $row,
                'school_name' => trim($data['school_name']),
                'school_logo_url' => $this->nullable($data['school_logo_url']),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => trim($firstName . ' ' . $lastName),
                'email' => $email,
                'secondary_email' => $this->nullableEmail($data['secondary_email']),
                'phone' => $this->nullable($data['phone']),
                'title' => $this->nullable($data['title']),
                'sport' => $sport,
                'division' => $this->nullable($data['division']),
                'conference' => $this->nullable($data['conference']),
                'verification_status' => $this->nullable($data['verification_status']),
                'confidence_level' => $this->nullable($data['confidence_level']),
                'audit_notes' => $this->nullable($data['audit_notes']),
                'city' => $this->nullable($data['city']),
                'state' => $this->nullable($data['state']),
                'country' => $this->nullable($data['country']) ?? 'United States',
                'website_url' => $this->nullable($data['website_url']),
                'is_active' => $this->toBoolean($data['is_active'], true),
                'notes' => $this->nullable($data['notes']),
                'source' => 'spreadsheet_import',
                'created_by' => $createdBy,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        $jobId = (string) Str::uuid();
        $jobPath = "coach-import-jobs/{$jobId}.json";

        Storage::disk('local')->put($jobPath, json_encode([
            'rows' => $rows,
            'initial_errors' => $errors,
            'initial_skipped' => $skipped,
        ], JSON_THROW_ON_ERROR));

        return [
            'job_id' => $jobId,
            'job_path' => $jobPath,
            'total' => count($rows),
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public function processImportBatch(string $jobPath, int $offset, int $batchSize = 150): array
    {
        if (! Storage::disk('local')->exists($jobPath)) {
            throw new RuntimeException('The temporary import job could not be found. Please analyze the file again.');
        }

        $payload = json_decode(Storage::disk('local')->get($jobPath), true, flags: JSON_THROW_ON_ERROR);
        $allRows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $batch = array_slice($allRows, $offset, $batchSize);

        if ($batch === []) {
            return ['created' => 0, 'updated' => 0, 'processed' => 0, 'errors' => [], 'done' => true];
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        // Build one school record per normalized school name. When the same school
        // appears on several coach rows, prefer a valid non-empty logo URL instead
        // of allowing a later blank value to replace it.
        $schoolRows = collect($batch)
            ->filter(fn (array $row): bool => filled($row['school_name'] ?? null))
            ->reduce(function (array $schools, array $row): array {
                $name = trim((string) ($row['school_name'] ?? ''));
                $key = $this->normalizeSchoolName($name);
                $logoUrl = trim((string) ($row['school_logo_url'] ?? ''));

                if (! isset($schools[$key])) {
                    $schools[$key] = [
                        'name' => $name,
                        'logo_url' => null,
                    ];
                }

                if ($this->isHttpUrl($logoUrl)) {
                    $schools[$key]['logo_url'] = $logoUrl;
                }

                return $schools;
            }, []);

        DB::transaction(function () use ($batch, $schoolRows, &$created, &$updated, &$errors): void {
            $schoolMap = [];

            if ($schoolRows !== []) {
                School::withTrashed()
                    ->whereIn(DB::raw('LOWER(TRIM(name))'), array_keys($schoolRows))
                    ->get()
                    ->each(function (School $school) use (&$schoolMap, $schoolRows): void {
                        if ($school->trashed()) {
                            $school->restore();
                        }

                        $schoolKey = $this->normalizeSchoolName($school->name);
                        $schoolData = $schoolRows[$schoolKey] ?? [];
                        $logoUrl = trim((string) ($schoolData['logo_url'] ?? ''));

                        // Store the actual remote image URL directly. Do not
                        // download the image and do not write logo_path.
                        if ($this->isHttpUrl($logoUrl) && $school->logo_url !== $logoUrl) {
                            $school->forceFill(['logo_url' => $logoUrl])->save();
                        }

                        $schoolMap[$schoolKey] = $school->getKey();
                    });

                foreach ($schoolRows as $schoolKey => $schoolData) {
                    if (isset($schoolMap[$schoolKey])) {
                        continue;
                    }

                    $logoUrl = trim((string) ($schoolData['logo_url'] ?? ''));

                    $school = School::create([
                        'name' => trim((string) $schoolData['name']),
                        'logo_url' => $this->isHttpUrl($logoUrl) ? $logoUrl : null,
                    ]);

                    $schoolMap[$schoolKey] = $school->getKey();
                }
            }

            $emails = collect($batch)->pluck('email')->filter()->unique()->values()->all();
            $existingEmails = Coach::withTrashed()
                ->whereIn('email', $emails)
                ->pluck('email')
                ->map(fn ($email): string => Str::lower(trim((string) $email)))
                ->flip();

            $now = now();
            $upsertRows = [];

            foreach ($batch as $row) {
                try {
                    $sourceRow = $row['source_row'] ?? '?';
                    $schoolName = trim((string) ($row['school_name'] ?? ''));
                    unset($row['source_row'], $row['school_name'], $row['school_logo_url']);

                    $row['school_id'] = $schoolName !== ''
                        ? ($schoolMap[$this->normalizeSchoolName($schoolName)] ?? null)
                        : null;
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                    $row['deleted_at'] = null;

                    $upsertRows[] = $row;

                    if ($existingEmails->has($row['email'])) {
                        $updated++;
                    } else {
                        $created++;
                    }
                } catch (Throwable $exception) {
                    $errors[] = 'Row ' . $sourceRow . ': ' . $exception->getMessage();
                }
            }

            if ($upsertRows !== []) {
                Coach::query()->upsert(
                    $upsertRows,
                    ['email'],
                    [
                        'school_id', 'first_name', 'last_name', 'display_name', 'secondary_email',
                        'phone', 'title', 'sport', 'division', 'conference', 'verification_status',
                        'confidence_level', 'audit_notes', 'city', 'state', 'country', 'website_url',
                        'is_active', 'notes', 'source', 'updated_at', 'deleted_at',
                    ],
                );
            }
        });

        $processed = count($batch);

        $batchEmails = collect($batch)
            ->pluck('email')
            ->filter()
            ->map(fn ($email): string => Str::lower(trim((string) $email)))
            ->unique()
            ->values();

        if ($batchEmails->isNotEmpty()) {
            $coachesForPlanning = Coach::query()
                ->with('school:id,name')
                ->whereIn('email', $batchEmails->all())
                ->get();

            app(CoachGhlSyncPlanner::class)->planForCoaches($coachesForPlanning);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'processed' => $processed,
            'errors' => $errors,
            'done' => ($offset + $processed) >= count($allRows),
        ];
    }

    public function deleteImportJob(?string $jobPath): void
    {
        if ($jobPath) {
            Storage::disk('local')->delete($jobPath);
        }
    }

    public function export(string $format, ?string $sport = null): string
    {
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            throw new RuntimeException('Unsupported export format.');
        }

        $rows = Coach::query()
            ->with('school:id,name,logo_url')
            ->when(filled($sport), fn ($query) => $query->where('sport', $sport))
            ->orderBy('sport')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::EXPORT_HEADINGS, null, 'A1');
        $rowNumber = 2;

        foreach ($rows as $coach) {
            $sheet->fromArray([
                $coach->school?->name, $coach->school?->logo_url, $coach->first_name, $coach->last_name, $coach->display_name,
                $coach->email, $coach->secondary_email, $coach->phone, $coach->title, $coach->sport,
                $coach->division, $coach->conference, $coach->verification_status, $coach->confidence_level,
                $coach->audit_notes, $coach->city, $coach->state, $coach->country, $coach->website_url,
                $coach->is_active ? 'Yes' : 'No', $coach->ghl_sync_status,
                $coach->notes, optional($coach->updated_at)->toDateTimeString(),
            ], null, "A{$rowNumber}");
            $rowNumber++;
        }

        return $this->saveSpreadsheet($spreadsheet, $format, 'coaches' . (filled($sport) ? '-' . Str::slug($sport) : ''));
    }

    public function createTemplate(string $format, string $sport): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headings = ['School', 'School Logo URL', 'First Name', 'Last Name', 'Email', 'Conference', 'Division', 'Title', 'Verification Status', 'Confidence Level', 'Audit Notes'];
        $sheet->fromArray($headings, null, 'A1');
        $sheet->fromArray(['Example University', 'https://example.edu/logo.png', 'Jordan', 'Smith', 'jordan@example.edu', 'Example Conference', 'NCAA Division I', 'Head Coach', 'Verified', 'High', 'Delete this sample row.'], null, 'A2');
        $sheet->getCell('L1')->setValue('Sport applied by import');
        $sheet->getCell('L2')->setValue($sport);

        return $this->saveSpreadsheet($spreadsheet, $format, 'coach-import-template-' . Str::slug($sport));
    }

    private function saveSpreadsheet(Spreadsheet $spreadsheet, string $format, string $basename): string
    {
        $sheet = $spreadsheet->getActiveSheet();
        $lastColumn = $sheet->getHighestColumn();
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E86F51');
        $sheet->getStyle("A1:{$lastColumn}{$sheet->getHighestRow()}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        for ($column = 1; $column <= Coordinate::columnIndexFromString($lastColumn); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $directory = storage_path('app/tmp/coach-exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory . '/' . $basename . '-' . now()->format('Ymd-His') . '.' . $format;
        $writer = $format === 'xlsx' ? new Xlsx($spreadsheet) : new CsvWriter($spreadsheet);

        if ($writer instanceof CsvWriter) {
            $writer->setUseBOM(true);
        }

        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function reader(string $path): Csv|XlsxReader
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx' ? new XlsxReader() : new Csv();
    }

    private function isHttpUrl(string $url): bool
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function normalizeHeader(string $value): string
    {
        return Str::of($value)->lower()->replace(['_', '-'], ' ')->squish()->toString();
    }

    private function normalizeSchoolName(string $value): string
    {
        return Str::of($value)->lower()->squish()->toString();
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function nullableEmail(string $value): ?string
    {
        $value = Str::lower(trim($value));
        return $value === '' ? null : $value;
    }

    private function toBoolean(string $value, bool $default): bool
    {
        if (trim($value) === '') {
            return $default;
        }

        return in_array($this->normalizeHeader($value), ['1', 'yes', 'true', 'active', 'enabled'], true);
    }
}