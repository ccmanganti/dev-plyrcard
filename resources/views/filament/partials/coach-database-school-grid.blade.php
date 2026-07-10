@php
    $schools = collect($schools ?? [])->filter(fn ($school) => is_array($school))->values();
    $viewMode = $viewMode ?? 'grid';
    $compact = (bool) ($compact ?? false);
    $selectedSchoolIds = collect($selectedSchoolIds ?? [])->map(fn ($id): string => (string) $id)->filter()->values()->all();

    $schoolInitialsFor = function (string $name): string {
        return strtoupper(collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->map(fn ($part) => mb_substr((string) $part, 0, 1))
            ->take(2)
            ->implode('') ?: 'S');
    };

    $normalizeLogoUrl = function ($value) use (&$normalizeLogoUrl): string {
        if (is_array($value)) {
            foreach (['url', 'value', 'src', 'link', 'mediaUrl', 'fileUrl', 'downloadUrl', 'thumbnailUrl'] as $key) {
                if (array_key_exists($key, $value)) {
                    $resolved = $normalizeLogoUrl($value[$key]);
                    if ($resolved !== '') {
                        return $resolved;
                    }
                }
            }

            foreach ($value as $child) {
                $resolved = $normalizeLogoUrl($child);
                if ($resolved !== '') {
                    return $resolved;
                }
            }

            return '';
        }

        if (! is_scalar($value)) {
            return '';
        }

        $url = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url = trim($url, " \t\n\r\0\x0B\"'");

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $lowerUrl = strtolower($url);
        if (str_starts_with($lowerUrl, 'http://') || str_starts_with($lowerUrl, 'https://')) {
            return $url;
        }

        return '';
    };


    $looksLikeLogoField = function ($identifier): bool {
        if (! is_scalar($identifier)) {
            return false;
        }

        $key = strtolower(trim((string) $identifier));
        $key = trim(str_replace(['{{', '}}'], '', $key), '{} ' . "\t\n\r\0\x0B");
        $key = str_replace([' ', '-', '.', ':', '/', '\\'], '_', $key);

        return $key === 'logo'
            || $key === 'business_logo'
            || $key === 'business_logo_url'
            || $key === 'school_logo'
            || $key === 'school_logo_url'
            || $key === 'contact_school_logo'
            || str_ends_with($key, '_logo')
            || str_contains($key, 'school_logo')
            || str_contains($key, 'business_logo');
    };

    $remoteUrlFromCustomFields = function ($row) use (&$remoteUrlFromCustomFields, $normalizeLogoUrl, $looksLikeLogoField): string {
        if (! is_array($row)) {
            return '';
        }

        foreach (['customFields', 'customField', 'custom_fields', 'customFieldValues', 'custom_field_values', 'customValues', 'custom_values'] as $containerKey) {
            $raw = data_get($row, $containerKey, []);

            if (! is_array($raw)) {
                continue;
            }

            foreach ($raw as $fieldKey => $fieldValue) {
                $identifiers = [$fieldKey];

                if (is_array($fieldValue)) {
                    foreach (['id', '_id', 'key', 'name', 'label', 'fieldKey', 'field_key', 'customFieldId', 'custom_field_id', 'fieldId', 'field_id', 'mergeField', 'merge_field', 'placeholder', 'slug'] as $identifierKey) {
                        $identifiers[] = $fieldValue[$identifierKey] ?? null;
                    }
                }

                $isLogo = collect($identifiers)->contains(fn ($identifier): bool => $looksLikeLogoField($identifier));
                $url = $normalizeLogoUrl($fieldValue);

                if ($isLogo && $url !== '') {
                    return $url;
                }
            }
        }

        foreach (['contact', 'business', 'company', 'data', 'result'] as $nestedKey) {
            $nested = data_get($row, $nestedKey);
            if (is_array($nested)) {
                $url = $remoteUrlFromCustomFields($nested);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return '';
    };

    $logoForSchool = function (array $school) use ($normalizeLogoUrl, $remoteUrlFromCustomFields): string {
        $candidates = [
            $school['logo_url'] ?? null,
            $school['school_logo_url'] ?? null,
            $school['business_logo_url'] ?? null,
            $school['logo'] ?? null,
            $school['school_logo'] ?? null,
            $school['business_logo'] ?? null,
            $school['business.logo'] ?? null,
            $school['contact.school_logo'] ?? null,
            data_get($school, 'business.logo'),
            data_get($school, 'contact.school_logo'),
            data_get($school, 'customFields.logo'),
            data_get($school, 'customFields.school_logo'),
            data_get($school, 'custom_fields.logo'),
            data_get($school, 'custom_fields.school_logo'),
            data_get($school, 'head_coach.logo_url'),
            data_get($school, 'head_coach.school_logo_url'),
            data_get($school, 'head_coach.business_logo_url'),
            data_get($school, 'head_coach.school_logo'),
            data_get($school, 'head_coach.logo'),
            data_get($school, 'head_coach.contact.school_logo'),
            data_get($school, 'head_coach.business.logo'),
            data_get($school, 'head_coach.customFields.business.logo'),
            data_get($school, 'head_coach.customFields.school_logo'),
            $school['raw_business'] ?? null,
            $school['raw_contact'] ?? null,
            data_get($school, 'head_coach.raw_contact'),
            data_get($school, 'head_coach.raw_business'),
        ];

        foreach ($candidates as $candidate) {
            $url = $normalizeLogoUrl($candidate);
            if ($url !== '') {
                return $url;
            }
        }

        $url = $remoteUrlFromCustomFields($school);
        if ($url !== '') {
            return $url;
        }

        foreach (($school['coaches'] ?? []) as $coach) {
            if (! is_array($coach)) {
                continue;
            }

            foreach (['logo_url', 'school_logo_url', 'business_logo_url', 'logo', 'school_logo', 'business_logo', 'business.logo', 'contact.school_logo'] as $key) {
                $url = $normalizeLogoUrl($coach[$key] ?? null);
                if ($url !== '') {
                    return $url;
                }
            }

            $url = $remoteUrlFromCustomFields($coach);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    };

    $headCoachFor = function (array $school): array {
        $headCoach = is_array($school['head_coach'] ?? null) ? $school['head_coach'] : [];
        if (trim((string) ($headCoach['name'] ?? '')) !== '') {
            return $headCoach;
        }

        foreach (['coaches_preview', 'coaches'] as $field) {
            foreach (($school[$field] ?? []) as $coach) {
                if (is_array($coach) && trim((string) ($coach['name'] ?? '')) !== '') {
                    return $coach;
                }
            }
        }

        return [];
    };

    $coachNameFor = function (array $school) use ($headCoachFor): string {
        $headCoach = $headCoachFor($school);
        $name = trim((string) ($headCoach['name'] ?? ''));
        return $name !== '' ? $name : '—';
    };

    $coachTitleFor = function (array $school) use ($headCoachFor): string {
        $headCoach = $headCoachFor($school);
        $title = trim((string) ($headCoach['title'] ?? ''));
        return $title !== '' ? $title : 'Coach';
    };

    $coachEmailFor = function (array $school) use ($headCoachFor): string {
        $headCoach = $headCoachFor($school);
        return trim((string) ($headCoach['email'] ?? ''));
    };
@endphp

<div x-data="window.rcDiscoverSelection ? window.rcDiscoverSelection({{ \Illuminate\Support\Js::from($selectedSchoolIds) }}) : { isSelected(id) { return @js($selectedSchoolIds).includes(String(id)) }, toggle() {} }" x-init="init && init()" data-rc-discover-selection style="display:contents">

@if($schools->isEmpty())
    <div class="rc-empty rc-discover-empty">
        <strong>No schools found.</strong>
        <span>Refresh the Recruiting Center or adjust your filters.</span>
    </div>
@elseif($viewMode === 'list' && ! $compact)
    <div class="rc-school-list-table rc-discover-school-list">
        <div class="rc-school-list-head rc-discover-school-list-head">
            <span>School</span>
            <span>Head Coach</span>
            <span>Title</span>
            <span>Email</span>
            <span>Div</span>
            <span></span>
        </div>

        @foreach($schools as $school)
            @php
                $schoolId = (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim((string) ($school['name'] ?? '')))));
                $schoolName = trim((string) ($school['name'] ?? 'Unnamed School'));
                $isSelected = in_array($schoolId, $selectedSchoolIds, true);
                $schoolLogoUrl = $logoForSchool($school);
                $schoolInitials = $schoolInitialsFor($schoolName);
                $division = trim((string) ($school['division'] ?? ''));
                $shortDivision = str_replace(['NCAA D-', 'NCAA Division ', 'Division '], ['D', 'D', 'D'], $division);
                $headCoachName = $coachNameFor($school);
                $headCoachTitle = $coachTitleFor($school);
                $headCoachEmail = $coachEmailFor($school);
            @endphp

            <div class="rc-school-list-row rc-discover-school-list-row {{ $isSelected ? 'is-selected' : '' }}" x-bind:class="{ 'is-selected': isSelected({{ \Illuminate\Support\Js::from($schoolId) }}) }">
                <button class="rc-school-list-name rc-discover-school-list-school" type="button" wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from($schoolId) }})">
                    <span class="rc-school-list-logo-box rc-school-logo-placeholder {{ $schoolLogoUrl === '' ? 'is-missing-logo' : '' }}">
                        @if($schoolLogoUrl !== '')
                            <img class="rc-school-list-logo" src="{{ $schoolLogoUrl }}" alt="{{ $schoolName }} logo" loading="lazy" referrerpolicy="no-referrer" onerror="this.closest('.rc-school-list-logo-box').classList.add('is-missing-logo')">
                        @endif
                        <span class="rc-logo-fallback-text">{{ $schoolInitials }}</span>
                    </span>
                    <span class="rc-discover-school-list-name-copy">{{ $schoolName }}</span>
                </button>

                <span class="rc-discover-list-coach">
                    {{ $headCoachName }}
                    @if(str_contains(strtolower($headCoachTitle), 'head'))
                        <span class="rc-head-coach-chip">HC</span>
                    @endif
                </span>
                <span class="rc-discover-list-muted">{{ $headCoachTitle }}</span>
                <span class="rc-discover-list-email">
                    @if($headCoachEmail !== '')
                        <a href="mailto:{{ $headCoachEmail }}">{{ $headCoachEmail }}</a>
                    @else
                        —
                    @endif
                </span>
                <span class="rc-discover-list-division">{{ $shortDivision !== '' ? $shortDivision : '—' }}</span>
                <div class="rc-school-list-actions rc-discover-list-actions">
                    <button class="rc-discover-row-check {{ $isSelected ? 'is-selected' : '' }}" type="button" x-on:click.stop="toggle({{ \Illuminate\Support\Js::from($schoolId) }})" x-bind:class="{ 'is-selected': isSelected({{ \Illuminate\Support\Js::from($schoolId) }}) }" x-bind:aria-pressed="isSelected({{ \Illuminate\Support\Js::from($schoolId) }}) ? 'true' : 'false'" aria-label="Select {{ $schoolName }}">
                        <span x-text="isSelected({{ \Illuminate\Support\Js::from($schoolId) }}) ? '✓' : ''">{{ $isSelected ? '✓' : '' }}</span>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="rc-school-grid rc-discover-school-grid {{ $compact ? 'is-compact' : '' }}">
        @foreach($schools as $school)
            @php
                $schoolId = (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim((string) ($school['name'] ?? '')))));
                $schoolName = trim((string) ($school['name'] ?? 'Unnamed School'));
                $isSelected = in_array($schoolId, $selectedSchoolIds, true);
                $schoolLogoUrl = $logoForSchool($school);
                $schoolInitials = $schoolInitialsFor($schoolName);
                $conference = trim((string) ($school['conference'] ?? ''));
                $division = trim((string) ($school['division'] ?? ''));
                $resolvedCoachReferences = is_array($school['coach_keys'] ?? null)
                    ? count(array_unique(array_filter($school['coach_keys'])))
                    : max(
                        is_array($school['coach_ids'] ?? null) ? count(array_unique(array_filter($school['coach_ids']))) : 0,
                        is_array($school['coach_emails'] ?? null) ? count(array_unique(array_filter($school['coach_emails']))) : 0
                    );
                $coachCount = max(
                    $resolvedCoachReferences,
                    (int) ($school['coach_count'] ?? 0),
                    (int) ($school['coaches_count'] ?? 0),
                    (int) ($school['coach_count_cross_referenced'] ?? 0),
                    is_array($school['coaches'] ?? null) ? count(array_filter($school['coaches'], fn ($coach) => is_array($coach))) : 0,
                    is_array($school['coaches_preview'] ?? null) ? count(array_filter($school['coaches_preview'], fn ($coach) => is_array($coach))) : 0
                );
            @endphp

            <article class="rc-school-card rc-discover-school-card {{ $isSelected ? 'is-selected' : '' }}" x-bind:class="{ 'is-selected': isSelected({{ \Illuminate\Support\Js::from($schoolId) }}) }">
                <div class="rc-discover-card-main">
                    <button class="rc-school-list-name rc-discover-card-title rc-discover-card-title-with-list-logo" type="button" wire:click="openSchoolDashboardModal({{ \Illuminate\Support\Js::from($schoolId) }})">
                        {{-- Use the exact same logo render path as the working list view. --}}
                        <span class="rc-school-list-logo-box rc-school-logo-placeholder {{ $schoolLogoUrl === '' ? 'is-missing-logo' : '' }}" style="width:2.15rem;height:2.15rem;display:inline-flex;align-items:center;justify-content:center;overflow:hidden;background:#fff;border-radius:.55rem;flex:0 0 auto;">
                            @if($schoolLogoUrl !== '')
                                <img class="rc-school-list-logo" src="{{ $schoolLogoUrl }}" alt="{{ $schoolName }} logo" loading="lazy" referrerpolicy="no-referrer" style="display:block;width:100%;height:100%;object-fit:contain;object-position:center;background:#fff;" onerror="this.style.display='none';this.closest('.rc-school-list-logo-box').classList.add('is-missing-logo')">
                            @endif
                            <span class="rc-logo-fallback-text">{{ $schoolInitials }}</span>
                        </span>
                        <span class="rc-discover-card-copy">
                            <strong>{{ $schoolName }}</strong>
                            <small>{{ $conference !== '' ? $conference : 'Conference unavailable' }}</small>
                        </span>
                    </button>

                    <button class="rc-discover-card-check {{ $isSelected ? 'is-selected' : '' }}" type="button" x-on:click.stop="toggle({{ \Illuminate\Support\Js::from($schoolId) }})" x-bind:class="{ 'is-selected': isSelected({{ \Illuminate\Support\Js::from($schoolId) }}) }" x-bind:aria-pressed="isSelected({{ \Illuminate\Support\Js::from($schoolId) }}) ? 'true' : 'false'" aria-label="Select {{ $schoolName }}">
                        <span x-text="isSelected({{ \Illuminate\Support\Js::from($schoolId) }}) ? '✓' : ''">{{ $isSelected ? '✓' : '' }}</span>
                    </button>
                </div>

                <div class="rc-discover-card-rule"></div>

                <div class="rc-discover-card-footer">
                    <span class="rc-discover-division-pill">{{ $division !== '' ? $division : 'Unlisted' }}</span>
                    <span class="rc-discover-coach-count">{{ number_format($coachCount) }} {{ \Illuminate\Support\Str::plural('coach', $coachCount) }}</span>
                </div>
            </article>
        @endforeach
    </div>
@endif
</div>