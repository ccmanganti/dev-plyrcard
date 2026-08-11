<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\FavoriteSchool;
use App\Models\MyList;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalRecruitingDatabaseService
{
    public const FAVORITE_KEY = '__favorite__';

    public const DEFAULT_LISTS = [
        'dream' => ['name' => 'Dream Schools', 'color' => '#ff6338', 'sort_order' => 10],
        'target' => ['name' => 'Target Schools', 'color' => '#3b82f6', 'sort_order' => 20],
        'safety' => ['name' => 'Safety Schools', 'color' => '#22c55e', 'sort_order' => 30],
        'camp_follow_up' => ['name' => 'Camp Follow-Up', 'color' => '#f59e0b', 'sort_order' => 40],
        'showcase_follow_up' => ['name' => 'Showcase Follow-Up', 'color' => '#7c5cff', 'sort_order' => 50],
        'general_recruiting' => ['name' => 'General Recruiting', 'color' => '#64748b', 'sort_order' => 60],
    ];

    public function ensureDefaultLists(User $user): void
    {
        foreach (self::DEFAULT_LISTS as $slug => $definition) {
            MyList::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'slug' => $slug],
                [
                    'name' => $definition['name'],
                    'color' => $definition['color'],
                    'is_system' => true,
                    'sort_order' => $definition['sort_order'],
                ],
            );
        }
    }

    public function lists(User $user): array
    {
        $this->ensureDefaultLists($user);

        return MyList::query()
            ->where('user_id', $user->getKey())
            ->withCount('schools')
            ->with(['schools' => fn ($query) => $query->withCount('coaches')->with(['coaches:id,school_id,conference,division'])->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MyList $list): array => $this->listDisplayRow($list))
            ->all();
    }

    public function createList(User $user, string $name, string $color = '#ff6338'): MyList
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Enter a list name.');
        }

        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            throw new \InvalidArgumentException('Use a list name with letters or numbers.');
        }

        $slug = $baseSlug;
        $suffix = 2;
        while (MyList::query()->where('user_id', $user->getKey())->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix++;
        }

        return MyList::query()->create([
            'user_id' => $user->getKey(),
            'name' => $name,
            'slug' => $slug,
            'color' => $this->normalizeColor($color),
            'is_system' => false,
            'sort_order' => 100,
        ]);
    }

    public function deleteList(User $user, string $listKey): bool
    {
        $list = $this->resolveList($user, $listKey);
        if (! $list) {
            return false;
        }
        return (bool) $list->delete();
    }

    public function resolveList(User $user, string $listKey): ?MyList
    {
        $slug = $this->normalizeListKey($listKey);
        if ($slug === '') {
            return null;
        }

        return MyList::query()
            ->where('user_id', $user->getKey())
            ->where('slug', $slug)
            ->first();
    }

    public function findSchool(User $user, string|int $identifier): ?School
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            $school = School::query()->find((int) $identifier);
            if ($school) {
                return $school;
            }
        }

        $school = School::query()->where('ghl_business_id', $identifier)->first();
        if ($school) {
            return $school;
        }

        $normalized = $this->normalizeSchoolName($identifier);
        if ($normalized === '') {
            return null;
        }

        return School::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();
    }

    public function membershipKeys(User $user, string|int $schoolIdentifier): array
    {
        $school = $this->findSchool($user, $schoolIdentifier);
        if (! $school) {
            return [];
        }

        $keys = MyList::query()
            ->where('user_id', $user->getKey())
            ->whereHas('schools', fn ($query) => $query->where('schools.id', $school->getKey()))
            ->pluck('slug')
            ->map(fn ($slug): string => $this->publicListKey((string) $slug));

        if (FavoriteSchool::query()->where('user_id', $user->getKey())->where('school_id', $school->getKey())->exists()) {
            $keys->push(self::FAVORITE_KEY);
        }

        return $keys->unique()->values()->all();
    }

    public function replaceMembershipKeys(User $user, string|int $schoolIdentifier, array $listKeys): array
    {
        $school = $this->findSchool($user, $schoolIdentifier);
        if (! $school) {
            return ['success' => false, 'error' => 'The local school could not be found.'];
        }

        $normalized = collect($listKeys)
            ->map(fn ($key): string => strtolower(trim((string) $key)))
            ->filter()
            ->unique()
            ->values();

        $favorite = $normalized->contains(self::FAVORITE_KEY);
        $listSlugs = $normalized
            ->reject(fn (string $key): bool => $key === self::FAVORITE_KEY)
            ->map(fn (string $key): string => $this->normalizeListKey($key))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($user, $school, $favorite, $listSlugs): void {
            if ($favorite) {
                FavoriteSchool::query()->firstOrCreate([
                    'user_id' => $user->getKey(),
                    'school_id' => $school->getKey(),
                ]);
            } else {
                FavoriteSchool::query()
                    ->where('user_id', $user->getKey())
                    ->where('school_id', $school->getKey())
                    ->delete();
            }

            $this->ensureDefaultLists($user);
            $lists = MyList::query()->where('user_id', $user->getKey())->get();
            foreach ($lists as $list) {
                $shouldContain = $listSlugs->contains($list->slug);
                if ($shouldContain) {
                    $list->schools()->syncWithoutDetaching([$school->getKey()]);
                } else {
                    $list->schools()->detach($school->getKey());
                }
            }
        });

        return [
            'success' => true,
            'school_id' => $school->getKey(),
            'business_id' => $school->ghl_business_id,
            'list_keys' => $this->membershipKeys($user, $school->getKey()),
            'updated' => 1,
            'failed' => 0,
        ];
    }

    /**
     * Add/remove many canonical local schools to/from one player-owned list.
     * No GHL/API work is performed here.
     */
    public function setSchoolsInList(User $user, array $schoolIds, string $listKey, bool $inList = true): array
    {
        $list = $this->resolveList($user, $listKey);
        if (! $list) {
            return ['success' => false, 'updated' => 0, 'error' => 'The local list could not be found.'];
        }

        $ids = School::query()
            ->whereIn('id', collect($schoolIds)
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all())
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            return ['success' => false, 'updated' => 0, 'error' => 'No valid local schools were supplied.'];
        }

        if ($inList) {
            $list->schools()->syncWithoutDetaching($ids);
        } else {
            $list->schools()->detach($ids);
        }

        return ['success' => true, 'updated' => count($ids), 'school_count' => count($ids)];
    }

    /**
     * Set the favorite state for one canonical local school.
     * This is player scoped and never calls GHL.
     */
    public function setFavorite(User $user, string|int $schoolId, bool $favorite): array
    {
        $school = $this->findSchool($user, $schoolId);
        if (! $school) {
            return ['success' => false, 'favorite' => ! $favorite, 'error' => 'The local school could not be found.'];
        }

        if ($favorite) {
            FavoriteSchool::query()->firstOrCreate([
                'user_id' => $user->getKey(),
                'school_id' => $school->getKey(),
            ]);
        } else {
            FavoriteSchool::query()
                ->where('user_id', $user->getKey())
                ->where('school_id', $school->getKey())
                ->delete();
        }

        return ['success' => true, 'favorite' => $favorite, 'school_id' => $school->getKey()];
    }

    public function replaceMembershipKeysBulk(User $user, array $schools): array
    {
        $updated = 0;
        $failed = 0;
        foreach ($schools as $row) {
            if (! is_array($row)) {
                continue;
            }
            $identifier = $row['school_id'] ?? $row['id'] ?? $row['business_id'] ?? $row['company_id'] ?? '';
            $result = $this->replaceMembershipKeys(
                $user,
                (string) $identifier,
                $row['membership_keys'] ?? $row['list_keys'] ?? $row['lists'] ?? [],
            );
            ($result['success'] ?? false) ? $updated++ : $failed++;
        }

        return ['success' => $updated > 0, 'updated' => $updated, 'failed' => $failed, 'school_updates' => $updated];
    }

    public function removeListsFromAllSchools(User $user, array $listKeys): int
    {
        $slugs = collect($listKeys)->map(fn ($key): string => $this->normalizeListKey((string) $key))->filter()->unique();
        $lists = MyList::query()->where('user_id', $user->getKey())->whereIn('slug', $slugs)->get();
        $deleted = 0;
        foreach ($lists as $list) {
            $deleted += $list->schools()->count();
            $list->schools()->detach();
        }
        return $deleted;
    }

    public function favoriteSchools(User $user): array
    {
        $schoolIds = FavoriteSchool::query()
            ->where('user_id', $user->getKey())
            ->pluck('school_id');

        return $this->schoolQuery()
            ->whereIn('schools.id', $schoolIds)
            ->orderBy('schools.name')
            ->get()
            ->map(fn (School $school): array => $this->schoolDisplayRow($user, $school, true))
            ->all();
    }

    /**
     * Resolve one canonical local school for the school drawer / Compose UI.
     *
     * This intentionally queries only one School row and its coaches. It avoids
     * rebuilding the full Discover Schools collection just to open a drawer.
     */
    public function schoolRow(User $user, string|int $schoolId): ?array
    {
        $schoolId = trim((string) $schoolId);
        if ($schoolId === '') {
            return null;
        }

        $query = $this->schoolQuery();

        $query->where(function ($query) use ($schoolId): void {
            if (ctype_digit($schoolId)) {
                $query->whereKey((int) $schoolId);
                return;
            }

            // Compatibility fallbacks for older links. New Discover cards pass
            // the local schools.id, so these should rarely be needed.
            $query->where('ghl_business_id', $schoolId)
                ->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($schoolId))]);
        });

        /** @var School|null $school */
        $school = $query->first();
        if (! $school) {
            return null;
        }

        $favorite = FavoriteSchool::query()
            ->where('user_id', $user->getKey())
            ->where('school_id', $school->getKey())
            ->exists();

        $row = $this->schoolDisplayRow($user, $school, $favorite);
        $listKeys = $this->membershipKeysForSchoolId($user, (int) $school->getKey());
        $row['list_keys'] = $listKeys;
        $row['lists'] = $listKeys;

        // Drawer needs the complete local roster for this ONE school only.
        $row['coaches'] = $school->coaches->map(fn (Coach $coach): array => [
            'id' => (string) $coach->getKey(),
            'local_id' => $coach->getKey(),
            'contact_id' => $coach->ghl_contact_id ?? null,
            'ghl_contact_id' => $coach->ghl_contact_id ?? null,
            'name' => trim((string) ($coach->display_name ?: ($coach->first_name . ' ' . $coach->last_name))),
            'first_name' => (string) $coach->first_name,
            'last_name' => (string) $coach->last_name,
            'email' => (string) $coach->email,
            'phone' => $coach->phone,
            'title' => $coach->title,
            'sport' => $coach->sport,
            'division' => $coach->division,
            'conference' => $coach->conference,
            'school_id' => $school->getKey(),
            'business_id' => $school->ghl_business_id,
            'company_id' => $school->ghl_business_id,
            'school' => $school->name,
            'school_name' => $school->name,
            'company_name' => $school->name,
            'business_name' => $school->name,
            'logo_url' => $school->logo_url,
            'school_logo_url' => $school->logo_url,
            'business_logo_url' => $school->logo_url,
        ])->values()->all();

        $row['coach_count'] = count($row['coaches']);
        $row['coaches_count'] = count($row['coaches']);
        $row['coach_count_cross_referenced'] = count($row['coaches']);

        return $row;
    }

    public function schoolRows(User $user): array
    {
        // The canonical school catalog is shared by every player. Cache only the
        // user-neutral School/Coach display rows, then overlay this player's
        // Favorites/My Lists in two small local queries. This prevents every
        // checkbox click or drawer open from re-hydrating ~1,000 schools and all
        // coach relations during the Livewire render.
        $schoolVersion = (string) (School::query()->max('updated_at') ?? '0');
        $coachVersion = (string) (Coach::query()->max('updated_at') ?? '0');
        $catalogKey = 'recruiting:local-school-catalog:v105:' . sha1($schoolVersion . '|' . $coachVersion);

        $baseRows = Cache::remember($catalogKey, now()->addMinutes(15), function () use ($user): array {
            return $this->schoolQuery()
                ->orderBy('schools.name')
                ->get()
                ->map(function (School $school) use ($user): array {
                    $row = $this->schoolDisplayRow($user, $school, false);
                    $row['list_keys'] = [];
                    $row['lists'] = [];
                    return $row;
                })
                ->all();
        });

        $favoriteIds = FavoriteSchool::query()
            ->where('user_id', $user->getKey())
            ->pluck('school_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();
        $memberships = $this->membershipMapBySchoolId($user);

        return collect($baseRows)->map(function (array $row) use ($favoriteIds, $memberships): array {
            $schoolId = (int) ($row['local_id'] ?? $row['school_id'] ?? $row['id'] ?? 0);
            $favorite = $schoolId > 0 && $favoriteIds->has($schoolId);
            $keys = $memberships[$schoolId] ?? [];
            if ($favorite && ! in_array(self::FAVORITE_KEY, $keys, true)) {
                $keys[] = self::FAVORITE_KEY;
            }

            $row['is_favorite'] = $favorite;
            $row['is_favorite_school'] = $favorite;
            $row['list_keys'] = array_values(array_unique($keys));
            $row['lists'] = $row['list_keys'];
            return $row;
        })->all();
    }

    public function coachRows(User $user): array
    {
        return Coach::query()
            ->with('school:id,name,logo_url,ghl_business_id')
            ->orderBy('school_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Coach $coach): array {
                $school = $coach->school;
                return [
                    'id' => (string) $coach->getKey(),
                    'local_id' => $coach->getKey(),
                    'contact_id' => $coach->ghl_contact_id ?? null,
                    'ghl_contact_id' => $coach->ghl_contact_id ?? null,
                    'name' => trim((string) ($coach->display_name ?: ($coach->first_name . ' ' . $coach->last_name))),
                    'first_name' => (string) $coach->first_name,
                    'last_name' => (string) $coach->last_name,
                    'email' => (string) $coach->email,
                    'secondary_email' => $coach->secondary_email,
                    'phone' => $coach->phone,
                    'title' => $coach->title,
                    'sport' => $coach->sport,
                    'division' => $coach->division,
                    'conference' => $coach->conference,
                    'school_id' => $school?->getKey(),
                    'business_id' => $school?->ghl_business_id,
                    'company_id' => $school?->ghl_business_id,
                    'school' => $school?->name,
                    'school_name' => $school?->name,
                    'company_name' => $school?->name,
                    'business_name' => $school?->name,
                    'logo_url' => $school?->logo_url,
                    'school_logo_url' => $school?->logo_url,
                    'business_logo_url' => $school?->logo_url,
                    'state' => $coach->state,
                    'city' => $coach->city,
                ];
            })
            ->all();
    }

    protected function schoolQuery()
    {
        return School::query()
            ->withCount('coaches')
            ->with(['coaches' => function ($query): void {
                $query->select(['id','school_id','display_name','first_name','last_name','email','title','sport','division','conference'])
                    ->orderByRaw("CASE WHEN LOWER(title) LIKE '%head%' AND LOWER(title) NOT LIKE '%assistant%' AND LOWER(title) NOT LIKE '%associate%' THEN 0 ELSE 1 END")
                    ->orderBy('last_name');
            }]);
    }

    protected function schoolDisplayRow(User $user, School $school, bool $favorite = false): array
    {
        $coaches = $school->relationLoaded('coaches') ? $school->coaches : collect();
        $head = $coaches->first(fn ($coach): bool => str_contains(strtolower((string) $coach->title), 'head')
            && ! str_contains(strtolower((string) $coach->title), 'assistant')
            && ! str_contains(strtolower((string) $coach->title), 'associate')) ?: $coaches->first();

        $conference = (string) ($coaches->pluck('conference')->filter()->first() ?? '');
        $division = (string) ($coaches->pluck('division')->filter()->first() ?? '');

        return [
            'id' => (string) $school->getKey(),
            'local_id' => $school->getKey(),
            'school_id' => $school->getKey(),
            'business_id' => (string) ($school->ghl_business_id ?? ''),
            'company_id' => (string) ($school->ghl_business_id ?? ''),
            'name' => (string) $school->name,
            'logo_url' => (string) ($school->logo_url ?? ''),
            'school_logo_url' => (string) ($school->logo_url ?? ''),
            'business_logo_url' => (string) ($school->logo_url ?? ''),
            'conference' => $conference,
            'division' => $division,
            'city' => (string) ($school->city ?? ''),
            'state' => (string) ($school->state ?? ''),
            'coach_count' => (int) ($school->coaches_count ?? $coaches->count()),
            'coaches_count' => (int) ($school->coaches_count ?? $coaches->count()),
            'coach_ids' => $coaches->pluck('id')->map(fn ($id): string => (string) $id)->all(),
            'coach_emails' => $coaches->pluck('email')->filter()->values()->all(),
            'coaches_preview' => $coaches->take(3)->map(fn ($coach): array => [
                'id' => (string) $coach->id,
                'name' => (string) ($coach->display_name ?: trim($coach->first_name . ' ' . $coach->last_name)),
                'email' => (string) $coach->email,
                'title' => (string) ($coach->title ?? ''),
                'school_id' => $school->getKey(),
                'school' => $school->name,
            ])->all(),
            'head_coach' => $head ? [
                'id' => (string) $head->id,
                'name' => (string) ($head->display_name ?: trim($head->first_name . ' ' . $head->last_name)),
                'email' => (string) $head->email,
                'title' => (string) ($head->title ?? ''),
                'school_id' => $school->getKey(),
                'school' => $school->name,
            ] : ['name' => '', 'email' => '', 'title' => ''],
            'head_coach_name' => (string) ($head?->display_name ?? ''),
            'head_coach_title' => (string) ($head?->title ?? ''),
            'head_coach_email' => (string) ($head?->email ?? ''),
            'is_favorite' => $favorite,
            'is_favorite_school' => $favorite,
            'list_keys' => [],
            'lists' => [],
            'search_text' => strtolower(trim(implode(' ', array_filter([$school->name, $conference, $division, $school->city, $school->state])))),
        ];
    }

    protected function membershipKeysForSchoolId(User $user, int $schoolId): array
    {
        $this->ensureDefaultLists($user);

        $keys = DB::table('my_list_schools')
            ->join('my_lists', 'my_lists.id', '=', 'my_list_schools.my_list_id')
            ->where('my_lists.user_id', $user->getKey())
            ->where('my_list_schools.school_id', $schoolId)
            ->pluck('my_lists.slug')
            ->map(fn ($slug): string => $this->publicListKey((string) $slug))
            ->filter()
            ->values();

        if (FavoriteSchool::query()
            ->where('user_id', $user->getKey())
            ->where('school_id', $schoolId)
            ->exists()) {
            $keys->push(self::FAVORITE_KEY);
        }

        return $keys->unique()->values()->all();
    }

    protected function membershipMapBySchoolId(User $user): array
    {
        $this->ensureDefaultLists($user);
        $rows = DB::table('my_list_schools')
            ->join('my_lists', 'my_lists.id', '=', 'my_list_schools.my_list_id')
            ->where('my_lists.user_id', $user->getKey())
            ->get(['my_list_schools.school_id', 'my_lists.slug']);

        $map = $rows->groupBy('school_id')->map(fn (Collection $rows): array => $rows
            ->pluck('slug')->map(fn ($slug): string => $this->publicListKey((string) $slug))->values()->all())->all();

        foreach (FavoriteSchool::query()->where('user_id', $user->getKey())->pluck('school_id') as $schoolId) {
            $map[$schoolId] = collect($map[$schoolId] ?? [])->push(self::FAVORITE_KEY)->unique()->values()->all();
        }
        return $map;
    }

    protected function listDisplayRow(MyList $list): array
    {
        return [
            'id' => $list->getKey(),
            'key' => $this->publicListKey($list->slug),
            'slug' => $list->slug,
            'label' => $list->name,
            'name' => $list->name,
            'tag' => null,
            'custom' => ! $list->is_system,
            'color' => $list->color ?: '#ff6338',
            'schools_count' => (int) ($list->schools_count ?? $list->schools->count()),
            'coaches_count' => (int) $list->schools->sum(fn ($school): int => (int) ($school->coaches_count ?? 0)),
            'schools' => $list->schools->map(fn ($school): array => [
                'id' => (string) $school->id,
                'name' => (string) $school->name,
                'logo_url' => (string) ($school->logo_url ?? ''),
                'conference' => (string) ($school->coaches->pluck('conference')->filter()->first() ?? ''),
                'division' => (string) ($school->coaches->pluck('division')->filter()->first() ?? ''),
                'coach_count' => (int) ($school->coaches_count ?? 0),
            ])->all(),
        ];
    }

    protected function normalizeListKey(string $key): string
    {
        $key = strtolower(trim($key));
        return str_starts_with($key, 'custom:') ? substr($key, 7) : $key;
    }

    protected function publicListKey(string $slug): string
    {
        return array_key_exists($slug, self::DEFAULT_LISTS) ? $slug : 'custom:' . $slug;
    }

    protected function normalizeSchoolName(string $name): string
    {
        return strtolower(trim((string) preg_replace('/\\s+/', ' ', $name)));
    }

    protected function normalizeColor(string $color): string
    {
        $color = strtolower(trim($color));
        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '#ff6338';
    }
}