<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CoachDatabaseService
{
    public function __construct(
        protected GoHighLevelService $goHighLevelService,
    ) {}

    public function canAccess(User $user): bool
    {
        if (method_exists($user, 'isSuperadminOrImpersonating') && $user->isSuperadminOrImpersonating()) {
            return true;
        }

        return method_exists($user, 'hasRole')
            && $user->hasRole('My Journey');
    }

    public function hasGhlConnection(User $user): bool
    {
        return filled($user->ghl_location_id ?? null)
            && filled($user->ghl_api_key ?? null);
    }


    public function getInitialState(User $user): array
    {
        if (! config('ghl.coach_database.enabled', true)) {
            return $this->locked('Recruiting Center is currently disabled.');
        }

        if (! $this->canAccess($user)) {
            return $this->locked('Coach Database is available on paid plans.');
        }

        if (! $this->hasGhlConnection($user)) {
            return [
                'allowed' => false,
                'locked' => false,
                'reason' => 'Missing recruiting data connection.',
                'coaches' => [],
                'schools' => [],
                'lists' => $this->emptyLists(),
                'stats' => $this->emptyStats(),
                'top_schools' => [],
            ];
        }

        return [
            'allowed' => true,
            'locked' => false,
            'reason' => null,
            'coaches' => [],
            'schools' => [],
            'lists' => $this->emptyLists(),
            'stats' => $this->emptyStats(),
            'top_schools' => [],
        ];
    }

    public function getCoachContactsPageForUser(
        User $user,
        ?int $startAfter = null,
        ?string $startAfterId = null,
        int $limit = 100,
    ): array {
        $result = $this->goHighLevelService->getCoachContactsPageForUser(
            user: $user,
            startAfter: $startAfter,
            startAfterId: $startAfterId,
            limit: $limit,
        );

        $coaches = collect($result['contacts'] ?? [])
            ->filter(fn (array $coach): bool => filled($coach['school'] ?? null))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->values();

        return [
            'success' => (bool) ($result['success'] ?? false),
            'contacts' => $coaches->all(),
            'count' => $coaches->count(),
            'total' => $result['total'] ?? null,
            'next_start_after' => $result['next_start_after'] ?? null,
            'next_start_after_id' => $result['next_start_after_id'] ?? null,
            'has_more' => (bool) ($result['has_more'] ?? false),
            'error' => $result['error'] ?? null,
        ];
    }

    public function actionTags(?User $user = null, array $customListTags = []): array
    {
        $defaults = [
            $this->savedSchoolTag(),
            $this->favoriteSchoolTag(),
            $this->savedCoachTag(),
            $this->favoriteCoachTag(),
        ];

        $listTags = collect($this->listDefinitions($user, null, $customListTags))
            ->pluck('tag')
            ->filter()
            ->values()
            ->all();

        return collect(array_merge($defaults, $listTags, $customListTags))
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique(fn (string $tag): string => strtolower($tag))
            ->values()
            ->all();
    }

    public function getContactsByTagsForUser(User $user, array $tags): array
    {
        $result = $this->goHighLevelService->getContactsByTagsForUser($user, $tags);

        $coaches = collect($result['contacts'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? null))
            ->unique('id')
            ->values();

        return [
            'success' => (bool) ($result['success'] ?? false),
            'contacts' => $coaches->all(),
            'count' => $coaches->count(),
            'by_tag' => $result['by_tag'] ?? [],
            'error' => $result['error'] ?? null,
            'debug' => $result['debug'] ?? [],
        ];
    }

    public function buildDashboardFromCoaches(array $coachRows, ?User $user = null, array $customListTags = []): array
    {
        $coaches = collect($coachRows)
            ->filter(fn ($coach): bool => is_array($coach) && filled($coach['school'] ?? null))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->unique('id')
            ->values();

        $customListTags = $this->normalizeCustomListTags($customListTags ?? []);
        $schools = $this->buildSchools($coaches, $user, $customListTags);

        return [
            'coaches' => $coaches->all(),
            'schools' => $schools->all(),
            'lists' => $this->buildLists($schools, $user, $coaches, $customListTags),
            'stats' => $this->buildStats($coaches, $schools),
            'top_schools' => $this->topEngagedSchools($schools),
        ];
    }

    public function updateContactsWithTag(User $user, array $contactIds, string $tag, string $type): array
    {
        $contactIds = collect($contactIds)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($contactIds)) {
            return ['success' => false, 'error' => 'No contacts found for this school.'];
        }

        return $this->goHighLevelService->updateContactTagsForUser(
            user: $user,
            contactIds: $contactIds,
            tags: [$tag],
            type: $type,
        );
    }

    public function savedSchoolTag(): string
    {
        return config('ghl.coach_database.tags.saved_school', 'saved school');
    }

    public function favoriteSchoolTag(): string
    {
        return config('ghl.coach_database.tags.favorite_school', 'favorite school');
    }

    public function listTagForKey(string $listKey, ?User $user = null): ?string
    {
        return $this->listTag($listKey, $user);
    }

    public function getDashboard(User $user): array
    {
        if (! config('ghl.coach_database.enabled', true)) {
            return $this->locked('Recruiting Center is currently disabled.');
        }

        if (! $this->canAccess($user)) {
            return $this->locked('Coach Database is available on paid plans.');
        }

        if (! $this->hasGhlConnection($user)) {
            return [
                'allowed' => false,
                'locked' => false,
                'reason' => 'Missing recruiting data connection.',
                'coaches' => [],
                'schools' => [],
                'lists' => $this->emptyLists(),
                'stats' => $this->emptyStats(),
                'top_schools' => [],
            ];
        }

        $result = $this->goHighLevelService->getCoachContactsForUser($user);

        $coaches = collect($result['contacts'] ?? [])
            ->filter(fn (array $coach): bool => filled($coach['school'] ?? null))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->values();

        $schools = $this->buildSchools($coaches, $user);

        return [
            'allowed' => true,
            'locked' => false,
            'reason' => null,
            'coaches' => $coaches->values()->all(),
            'schools' => $schools->values()->all(),
            'lists' => $this->buildLists($schools, $user, $coaches),
            'stats' => $this->buildStats($coaches, $schools),
            'top_schools' => $this->topEngagedSchools($schools),
            'error' => $result['error'] ?? null,
        ];
    }

    public function saveCoach(User $user, string $contactId): array
    {
        return $this->goHighLevelService->addTagsToContactForUser($user, $contactId, [
            config('ghl.coach_database.tags.saved_coach', 'saved coach'),
        ]);
    }

    public function unsaveCoach(User $user, string $contactId): array
    {
        return $this->goHighLevelService->removeTagsFromContactForUser($user, $contactId, [
            config('ghl.coach_database.tags.saved_coach', 'saved coach'),
        ]);
    }

    public function favoriteCoach(User $user, string $contactId): array
    {
        return $this->goHighLevelService->addTagsToContactForUser($user, $contactId, [
            config('ghl.coach_database.tags.favorite_coach', 'favorite coach'),
        ]);
    }

    public function unfavoriteCoach(User $user, string $contactId): array
    {
        return $this->goHighLevelService->removeTagsFromContactForUser($user, $contactId, [
            config('ghl.coach_database.tags.favorite_coach', 'favorite coach'),
        ]);
    }

    public function saveSchool(User $user, string $school): array
    {
        return $this->updateSchoolTag($user, $school, config('ghl.coach_database.tags.saved_school', 'saved school'), 'add');
    }

    public function unsaveSchool(User $user, string $school): array
    {
        return $this->updateSchoolTag($user, $school, config('ghl.coach_database.tags.saved_school', 'saved school'), 'remove');
    }

    public function favoriteSchool(User $user, string $school): array
    {
        return $this->updateSchoolTag($user, $school, config('ghl.coach_database.tags.favorite_school', 'favorite school'), 'add');
    }

    public function unfavoriteSchool(User $user, string $school): array
    {
        return $this->updateSchoolTag($user, $school, config('ghl.coach_database.tags.favorite_school', 'favorite school'), 'remove');
    }

    public function addSchoolToList(User $user, string $school, string $listKey): array
    {
        $tag = $this->listTag($listKey, $user);

        if (! $tag) {
            return ['success' => false, 'error' => 'Unknown list.'];
        }

        return $this->updateSchoolTag($user, $school, $tag, 'add');
    }

    public function removeSchoolFromList(User $user, string $school, string $listKey): array
    {
        $tag = $this->listTag($listKey, $user);

        if (! $tag) {
            return ['success' => false, 'error' => 'Unknown list.'];
        }

        return $this->updateSchoolTag($user, $school, $tag, 'remove');
    }

    protected function updateSchoolTag(User $user, string $school, string $tag, string $type): array
    {
        $dashboard = $this->getDashboard($user);

        $contactIds = collect($dashboard['coaches'] ?? [])
            ->filter(fn (array $coach): bool => strtolower(trim((string) ($coach['school'] ?? ''))) === strtolower(trim($school)))
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        if (empty($contactIds)) {
            return ['success' => false, 'error' => 'No contacts found for this school.'];
        }

        return $this->goHighLevelService->updateContactTagsForUser(
            user: $user,
            contactIds: $contactIds,
            tags: [$tag],
            type: $type,
        );
    }

    protected function buildSchools(Collection $coaches, ?User $user = null, array $customListTags = []): Collection
    {
        $listConfigs = $this->listDefinitions($user, $coaches, $customListTags);

        return $coaches
            ->filter(fn (array $coach): bool => filled($coach['school'] ?? null))
            ->groupBy(fn (array $coach): string => trim((string) $coach['school']))
            ->map(function (Collection $schoolCoaches, string $school) use ($listConfigs): array {
                $first = $schoolCoaches->first() ?: [];

                $headCoach = $schoolCoaches->first(function (array $coach): bool {
                    return str_contains(strtolower((string) ($coach['title'] ?? '')), 'head');
                }) ?: $first;

                $listKeys = collect($listConfigs)
                    ->filter(function (array $config) use ($schoolCoaches): bool {
                        $tag = strtolower(trim((string) ($config['tag'] ?? '')));

                        if (! $tag) {
                            return false;
                        }

                        return $schoolCoaches->contains(fn (array $coach): bool => $this->hasTag($coach['tags'] ?? [], $tag));
                    })
                    ->keys()
                    ->values()
                    ->all();

                $score = $this->schoolEngagementScore($schoolCoaches);

                return [
                    'id' => str($school)->slug()->toString(),
                    'name' => $school,
                    'conference' => $first['conference'] ?? null,
                    'division' => $first['division'] ?? null,
                    'state' => $first['state'] ?? null,
                    'city' => $first['city'] ?? null,
                    'coach_count' => $schoolCoaches->count(),
                    'head_coach' => $this->slimHeadCoach($headCoach),
                    'is_saved' => $schoolCoaches->contains(fn (array $coach): bool => (bool) ($coach['is_saved_school'] ?? false)),
                    'is_favorite' => $schoolCoaches->contains(fn (array $coach): bool => (bool) ($coach['is_favorite_school'] ?? false)),
                    'profile_views' => $schoolCoaches->filter(fn (array $coach): bool => (bool) ($coach['viewed_profile'] ?? false))->count(),
                    'highlight_views' => $schoolCoaches->filter(fn (array $coach): bool => (bool) ($coach['viewed_highlights'] ?? false))->count(),
                    'replies' => $schoolCoaches->filter(fn (array $coach): bool => (bool) ($coach['replied'] ?? false))->count(),
                    'trigger_link_clicks' => $schoolCoaches->filter(fn (array $coach): bool => (bool) ($coach['trigger_link_clicked'] ?? false))->count(),
                    'engagement_score' => $score,
                    'list_keys' => $listKeys,
                ];
            })
            ->sortBy('name')
            ->values();
    }

    protected function buildLists(Collection $schools, ?User $user = null, ?Collection $coaches = null, array $customListTags = []): array
    {
        return collect($this->listDefinitions($user, $coaches, $customListTags))
            ->map(function (array $config, string $key) use ($schools): array {
                $items = $schools
                    ->filter(fn (array $school): bool => in_array($key, $school['list_keys'] ?? [], true))
                    ->values();

                return [
                    'key' => $key,
                    'label' => $config['label'] ?? str($key)->headline()->toString(),
                    'description' => $config['description'] ?? null,
                    'tag' => $config['tag'] ?? null,
                    'custom' => (bool) ($config['custom'] ?? false),
                    'schools_count' => $items->count(),
                    'coaches_count' => $items->sum('coach_count'),
                    'schools' => $items
                        ->map(fn (array $school): array => [
                            'id' => $school['id'] ?? null,
                            'name' => $school['name'] ?? null,
                            'conference' => $school['conference'] ?? null,
                            'division' => $school['division'] ?? null,
                            'coach_count' => $school['coach_count'] ?? 0,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildStats(Collection $coaches, Collection $schools): array
    {
        $profileViews = $coaches->sum(fn (array $coach): int => max((int) ($coach['profile_view_count'] ?? 0), (int) ($coach['view_profile_total'] ?? 0), (bool) ($coach['viewed_profile'] ?? false) ? 1 : 0));
        $highlightViews = $coaches->sum(fn (array $coach): int => max((int) ($coach['highlight_view_count'] ?? 0), (bool) ($coach['viewed_highlights'] ?? false) ? 1 : 0));
        $linkClicks = $coaches->sum(fn (array $coach): int => max((int) ($coach['trigger_link_click_count'] ?? 0), (int) ($coach['email_click_count'] ?? 0), (bool) ($coach['trigger_link_clicked'] ?? false) ? 1 : 0));
        $emailSent = $coaches->sum(fn (array $coach): int => (int) ($coach['email_sent_count'] ?? 0));
        $emailOpens = $coaches->sum(fn (array $coach): int => (int) ($coach['email_open_count'] ?? 0));
        $emailClicks = $coaches->sum(fn (array $coach): int => (int) ($coach['email_click_count'] ?? 0));
        $replies = $coaches->sum(fn (array $coach): int => max((int) ($coach['coach_reply_count'] ?? 0), (bool) ($coach['replied'] ?? false) ? 1 : 0));

        return [
            'total_schools' => $schools->count(),
            'total_coaches' => $coaches->count(),
            'saved_schools' => $schools->filter(fn (array $school): bool => (bool) ($school['is_saved'] ?? false))->count(),
            'favorite_schools' => $schools->filter(fn (array $school): bool => (bool) ($school['is_favorite'] ?? false))->count(),
            'saved_coaches' => $coaches->filter(fn (array $coach): bool => (bool) ($coach['is_saved_coach'] ?? false))->count(),
            'favorite_coaches' => $coaches->filter(fn (array $coach): bool => (bool) ($coach['is_favorite_coach'] ?? false))->count(),
            'profile_views' => $profileViews,
            'highlight_views' => $highlightViews,
            'trigger_link_clicks' => $linkClicks,
            'email_sent_count' => $emailSent,
            'emails_sent' => $emailSent,
            'email_open_count' => $emailOpens,
            'email_opens' => $emailOpens,
            'email_click_count' => $emailClicks,
            'email_clicks' => $emailClicks,
            'coach_replies' => $replies,
        ];
    }

    protected function topEngagedSchools(Collection $schools): array
    {
        return $schools
            ->filter(fn (array $school): bool => (int) ($school['engagement_score'] ?? 0) > 0)
            ->sortByDesc('engagement_score')
            ->take(5)
            ->map(fn (array $school): array => [
                'id' => $school['id'] ?? null,
                'name' => $school['name'] ?? null,
                'conference' => $school['conference'] ?? null,
                'division' => $school['division'] ?? null,
                'coach_count' => $school['coach_count'] ?? 0,
                'profile_views' => $school['profile_views'] ?? 0,
                'highlight_views' => $school['highlight_views'] ?? 0,
                'trigger_link_clicks' => $school['trigger_link_clicks'] ?? 0,
                'replies' => $school['replies'] ?? 0,
                'lead_score' => $school['engagement_score'] ?? 0,
                'engagement_score' => $school['engagement_score'] ?? 0,
            ])
            ->values()
            ->all();
    }

    protected function schoolEngagementScore(Collection $schoolCoaches): int
    {
        return $schoolCoaches->sum(function (array $coach): int {
            return
                ((bool) ($coach['viewed_profile'] ?? false) ? 5 : 0)
                + ((bool) ($coach['viewed_highlights'] ?? false) ? 4 : 0)
                + ((bool) ($coach['trigger_link_clicked'] ?? false) ? 3 : 0)
                + ((bool) ($coach['replied'] ?? false) ? 10 : 0)
                + ((bool) ($coach['engaged'] ?? false) ? 3 : 0)
                + ((bool) ($coach['is_favorite_coach'] ?? false) ? 2 : 0)
                + ((bool) ($coach['is_saved_coach'] ?? false) ? 1 : 0);
        });
    }

    protected function slimCoach(array $coach): array
    {
        return [
            'id' => $coach['id'] ?? null,
            'name' => $this->formatPersonName($coach['name'] ?? trim((string) (($coach['first_name'] ?? '') . ' ' . ($coach['last_name'] ?? '')))),
            'first_name' => $this->formatPersonName($coach['first_name'] ?? null),
            'last_name' => $this->formatPersonName($coach['last_name'] ?? null),
            'email' => $coach['email'] ?? null,
            'phone' => $coach['phone'] ?? null,
            'school' => $coach['school'] ?? null,
            'conference' => $coach['conference'] ?? null,
            'division' => $coach['division'] ?? null,
            'title' => $coach['title'] ?? null,
            'sport' => $coach['sport'] ?? null,
            'state' => $coach['state'] ?? null,
            'city' => $coach['city'] ?? null,
            'external_id' => $coach['external_id'] ?? null,
            'tags' => $tags = array_values(array_filter($coach['tags'] ?? [])),
            'is_saved_school' => (bool) ($coach['is_saved_school'] ?? $this->hasTag($tags, $this->savedSchoolTag())),
            'is_favorite_school' => (bool) ($coach['is_favorite_school'] ?? $this->hasTag($tags, $this->favoriteSchoolTag())),
            'is_saved_coach' => (bool) ($coach['is_saved_coach'] ?? $this->hasTag($tags, $this->savedCoachTag())),
            'is_favorite_coach' => (bool) ($coach['is_favorite_coach'] ?? $this->hasTag($tags, $this->favoriteCoachTag())),
            'viewed_profile' => (bool) ($coach['viewed_profile'] ?? false),
            'viewed_highlights' => (bool) ($coach['viewed_highlights'] ?? false),
            'engaged' => (bool) ($coach['engaged'] ?? false),
            'replied' => (bool) ($coach['replied'] ?? false),
            'trigger_link_clicked' => (bool) ($coach['trigger_link_clicked'] ?? false),
            'profile_view_count' => max((int) ($coach['profile_view_count'] ?? 0), (int) ($coach['view_profile_total'] ?? 0)),
            'view_profile_total' => (int) ($coach['view_profile_total'] ?? $coach['profile_view_count'] ?? 0),
            'view_profile_website' => (int) ($coach['view_profile_website'] ?? 0),
            'view_profile_instagram' => (int) ($coach['view_profile_instagram'] ?? 0),
            'view_profile_youtube' => (int) ($coach['view_profile_youtube'] ?? 0),
            'view_profile_x' => (int) ($coach['view_profile_x'] ?? 0),
            'view_profile_email_link' => (int) ($coach['view_profile_email_link'] ?? 0),
            'highlight_view_count' => (int) ($coach['highlight_view_count'] ?? 0),
            'trigger_link_click_count' => max((int) ($coach['trigger_link_click_count'] ?? 0), (int) ($coach['email_click_count'] ?? 0)),
            'email_sent_count' => (int) ($coach['email_sent_count'] ?? 0),
            'email_open_count' => (int) ($coach['email_open_count'] ?? 0),
            'email_click_count' => (int) ($coach['email_click_count'] ?? 0),
            'coach_reply_count' => (int) ($coach['coach_reply_count'] ?? 0),
        ];
    }

    protected function slimHeadCoach(array $coach): array
    {
        return [
            'id' => $coach['id'] ?? null,
            'name' => $coach['name'] ?? null,
            'email' => $coach['email'] ?? null,
            'title' => $coach['title'] ?? null,
        ];
    }


    public function createCustomList(User $user, string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return [
                'success' => false,
                'error' => 'Enter a list name.',
            ];
        }

        $slug = Str::slug($name);

        if ($slug === '') {
            return [
                'success' => false,
                'error' => 'Use a list name with letters or numbers.',
            ];
        }

        $tag = $this->customListTagPrefix() . $slug;

        return [
            'success' => true,
            'tag' => $tag,
            'key' => 'custom:' . $slug,
            'list' => $this->formatListDefinition('custom:' . $slug, [
                'label' => $this->labelFromCustomListTag($tag, $name),
                'tag' => $tag,
                'custom' => true,
            ]),
        ];
    }

    public function savedCoachTag(): string
    {
        return config('ghl.coach_database.tags.saved_coach', 'saved coach');
    }

    public function favoriteCoachTag(): string
    {
        return config('ghl.coach_database.tags.favorite_coach', 'favorite coach');
    }

    public function customListTagPrefix(): string
    {
        return 'plyrcard:list:';
    }

    protected function listDefinitions(?User $user = null, ?Collection $coaches = null, array $customListTags = []): array
    {
        $fromRoot = config('ghl.coach_database.lists', []);
        $fromTags = collect(config('ghl.coach_database.tags.lists', []))
            ->map(fn (string $tag, string $key): array => [
                'label' => Str::of($key)->replace('_', ' ')->headline()->toString(),
                'tag' => $tag,
                'custom' => false,
            ])
            ->all();

        $defaults = [
            'dream' => ['label' => 'Dream Schools', 'tag' => 'dream school', 'custom' => false],
            'target' => ['label' => 'Target Schools', 'tag' => 'target school', 'custom' => false],
            'safety' => ['label' => 'Safety Schools', 'tag' => 'safety school', 'custom' => false],
            'camp_follow_up' => ['label' => 'Camp Follow-Up', 'tag' => 'camp follow-up', 'custom' => false],
            'showcase_follow_up' => ['label' => 'Showcase Follow-Up', 'tag' => 'showcase follow-up', 'custom' => false],
            'general_recruiting' => ['label' => 'General Recruiting', 'tag' => 'general recruiting', 'custom' => false],
        ];

        $definitions = array_replace_recursive($defaults, $fromTags, $fromRoot);

        $tagPrefix = $this->customListTagPrefix();

        $tagsFromCoaches = ($coaches ?: collect())
            ->flatMap(fn (array $coach): array => $coach['tags'] ?? [])
            ->map(fn ($tag): string => $this->stringTagFromMixed($tag))
            ->filter(fn (string $tag): bool => str_starts_with(strtolower($tag), strtolower($tagPrefix)))
            ->values()
            ->all();

        collect($customListTags)
            ->merge($tagsFromCoaches)
            ->map(fn ($tag): string => $this->stringTagFromMixed($tag))
            ->filter(fn (string $tag): bool => str_starts_with(strtolower($tag), strtolower($tagPrefix)))
            ->unique(fn (string $tag): string => strtolower($tag))
            ->each(function (string $tag) use (&$definitions, $tagPrefix): void {
                $slug = Str::after(strtolower($tag), strtolower($tagPrefix));

                if ($slug === '') {
                    return;
                }

                $definitions['custom:' . $slug] = [
                    'label' => $this->labelFromCustomListTag($tag),
                    'tag' => $tag,
                    'custom' => true,
                ];
            });

        return $definitions;
    }

    protected function listTag(string $listKey, ?User $user = null): ?string
    {
        if (str_starts_with($listKey, 'custom:')) {
            $slug = Str::slug(Str::after($listKey, 'custom:'));

            return $slug !== '' ? $this->customListTagPrefix() . $slug : null;
        }

        $definitions = $this->listDefinitions($user);

        return $definitions[$listKey]['tag'] ?? null;
    }

    protected function labelFromCustomListTag(string $tag, ?string $fallback = null): string
    {
        $slug = Str::after(strtolower(trim($tag)), strtolower($this->customListTagPrefix()));

        return filled($fallback)
            ? trim((string) $fallback)
            : Str::of($slug)->replace(['-', '_'], ' ')->headline()->toString();
    }

    protected function normalizeCustomListTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag): string => $this->stringTagFromMixed($tag))
            ->filter(fn (string $tag): bool => $tag !== '' && str_starts_with(strtolower($tag), strtolower($this->customListTagPrefix())))
            ->unique(fn (string $tag): string => strtolower($tag))
            ->values()
            ->all();
    }

    protected function stringTagFromMixed(mixed $tag): string
    {
        if (is_array($tag)) {
            $value = $tag['tag'] ?? $tag['name'] ?? $tag['value'] ?? $tag['id'] ?? '';

            return is_scalar($value) ? trim((string) $value) : '';
        }

        return is_scalar($tag) ? trim((string) $tag) : '';
    }

    protected function formatListDefinition(string $key, array $config): array
    {
        return [
            'key' => $key,
            'label' => $config['label'] ?? str($key)->headline()->toString(),
            'description' => $config['description'] ?? null,
            'tag' => $config['tag'] ?? null,
            'custom' => (bool) ($config['custom'] ?? false),
            'schools_count' => 0,
            'coaches_count' => 0,
            'schools' => [],
        ];
    }

    protected function formatPersonName(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return Str::of($name)
            ->lower()
            ->title()
            ->replace(" Mc", " Mc")
            ->replace(" Mac", " Mac")
            ->toString();
    }

    protected function hasTag(array $tags, string $tag): bool
    {
        $needle = strtolower(trim($tag));

        return collect($tags)
            ->map(fn ($coachTag): string => strtolower(trim((string) $coachTag)))
            ->contains($needle);
    }

    protected function emptyLists(?User $user = null): array
    {
        return collect($this->listDefinitions($user))
            ->map(fn (array $config, string $key): array => $this->formatListDefinition($key, $config))
            ->values()
            ->all();
    }

    protected function emptyStats(): array
    {
        return [
            'total_schools' => 0,
            'total_coaches' => 0,
            'saved_schools' => 0,
            'favorite_schools' => 0,
            'saved_coaches' => 0,
            'favorite_coaches' => 0,
            'profile_views' => 0,
            'highlight_views' => 0,
            'trigger_link_clicks' => 0,
            'coach_replies' => 0,
        ];
    }

    protected function locked(string $reason): array
    {
        return [
            'allowed' => false,
            'locked' => true,
            'reason' => $reason,
            'coaches' => [],
            'schools' => [],
            'lists' => $this->emptyLists(),
            'stats' => $this->emptyStats(),
            'top_schools' => [],
        ];
    }


    public function getSchoolBusinessesPageForUser(User $user, int $skip = 0, int $limit = 50): array
    {
        return $this->goHighLevelService->getSchoolBusinessesPageForUser($user, $skip, $limit);
    }

    public function getContactsForBusinessForUser(User $user, string $businessId, int $skip = 0, int $limit = 100, ?array $school = null): array
    {
        $result = $this->goHighLevelService->getContactsForBusinessForUser($user, $businessId, $skip, $limit, $school);
        $coaches = collect($result['coaches'] ?? $result['contacts'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->values()
            ->all();

        $result['coaches'] = $coaches;
        $result['contacts'] = $coaches;

        return $result;
    }

    public function rebuildFromSchoolCompanySnapshot(array $schools, array $coaches, ?User $user, array $customListTags = []): array
    {
        $schools = collect($schools)->filter(fn ($school): bool => is_array($school))->values();
        $coaches = collect($coaches)->filter(fn ($coach): bool => is_array($coach))->map(fn (array $coach): array => $this->slimCoach($coach))->unique('id')->values();
        $schoolsByName = $this->buildSchools($coaches, $user, $customListTags)->keyBy(fn (array $school): string => strtolower(trim((string) ($school['name'] ?? ''))));
        $schoolsByBusiness = $coaches->groupBy(fn (array $coach): string => (string) ($coach['business_id'] ?? ''));

        $mergedSchools = $schools->map(function (array $school) use ($schoolsByName, $schoolsByBusiness): array {
            $nameKey = strtolower(trim((string) ($school['name'] ?? '')));
            $businessId = (string) ($school['business_id'] ?? $school['id'] ?? '');
            $built = $schoolsByName->get($nameKey, []);
            $businessCoachCount = $businessId !== '' ? $schoolsByBusiness->get($businessId, collect())->count() : 0;
            $coachCount = max((int) ($built['coach_count'] ?? 0), $businessCoachCount, (int) ($school['coach_count'] ?? 0));
            $wasLoaded = (bool) ($school['coaches_loaded'] ?? false);

            return array_merge($school, $built, [
                'id' => $school['id'] ?? $school['business_id'] ?? ($built['id'] ?? $nameKey),
                'business_id' => $school['business_id'] ?? $school['id'] ?? ($built['business_id'] ?? null),
                'name' => $school['name'] ?? ($built['name'] ?? 'Unnamed School'),
                'conference' => $school['conference'] ?? ($built['conference'] ?? null),
                'division' => $school['division'] ?? ($built['division'] ?? null),
                'coach_count' => $coachCount,
                'coaches_loaded' => $wasLoaded || $coachCount > 0,
            ]);
        })->values();

        return [
            'coaches' => $coaches->values()->all(),
            'schools' => $mergedSchools->all(),
            'lists' => $this->buildLists($mergedSchools, $user, $coaches, $customListTags),
            'stats' => $this->buildStats($coaches, $mergedSchools),
            'top_schools' => $this->topEngagedSchools($mergedSchools),
        ];
    }

    public function getConversationsForUser(User $user, array $query = []): array
    {
        return $this->goHighLevelService->getConversationsForUser($user, $query);
    }

    public function getConversationMessagesForUser(User $user, string $conversationId, ?string $lastMessageId = null, int $limit = 50): array
    {
        return $this->goHighLevelService->getConversationMessagesForUser($user, $conversationId, $lastMessageId, $limit);
    }

    public function sendEmailMessageForUser(User $user, array $payload): array
    {
        return $this->goHighLevelService->sendEmailMessageForUser($user, $payload);
    }

    public function getEmailTemplatesForUser(User $user): array
    {
        return $this->goHighLevelService->getEmailTemplatesForUser($user);
    }

    public function getEmailTemplateForUser(User $user, string $templateId): array
    {
        return $this->goHighLevelService->getEmailTemplateForUser($user, $templateId);
    }

    public function createEmailCampaignForUser(User $user, array $payload): array
    {
        return $this->goHighLevelService->createEmailCampaignForUser($user, $payload);
    }

    public function scheduleEmailCampaignForUser(User $user, string $campaignId, ?int $scheduledTimestamp = null): array
    {
        return $this->goHighLevelService->scheduleEmailCampaignForUser($user, $campaignId, $scheduledTimestamp);
    }

    public function createEmailTemplateForUser(User $user, string $name, string $subject, string $body, string $previewText = ''): array
    {
        if (trim($name) === '' || trim($subject) === '' || trim($body) === '') {
            return ['success' => false, 'error' => 'Template name, subject, and message are required.'];
        }

        return $this->goHighLevelService->createEmailTemplateForUser($user, $name, $subject, $body, $previewText);
    }

    public function updateEmailTemplateForUser(User $user, string $templateId, string $name, string $subject, string $body, string $previewText = ''): array
    {
        if (trim($templateId) === '') {
            return ['success' => false, 'error' => 'Choose a template first.'];
        }

        if (trim($name) === '' || trim($subject) === '' || trim($body) === '') {
            return ['success' => false, 'error' => 'Template name, subject, and message are required.'];
        }

        return $this->goHighLevelService->updateEmailTemplateForUser($user, $templateId, $name, $subject, $body, $previewText);
    }

    public function deleteEmailTemplateForUser(User $user, string $templateId): array
    {
        if (trim($templateId) === '') {
            return ['success' => false, 'error' => 'Choose a template first.'];
        }

        return $this->goHighLevelService->deleteEmailTemplateForUser($user, $templateId);
    }

    public function uploadMediaForUser(User $user, mixed $file): array
    {
        return $this->goHighLevelService->uploadMediaForUser($user, $file);
    }

}