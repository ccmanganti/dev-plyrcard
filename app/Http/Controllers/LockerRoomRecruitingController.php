<?php

namespace App\Http\Controllers;

use App\Models\CoachDatabaseEmailTemplate;
use App\Models\CreditServiceRequest;
use App\Models\User;
use App\Services\CoachDatabaseService;
use App\Services\CreditPointService;
use App\Services\LocalRecruitingDatabaseService;
use App\Services\LocalTemplateMergeValueService;
use App\Services\PlyrPointCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LockerRoomRecruitingController extends Controller
{
    public function schools(Request $request, LocalRecruitingDatabaseService $database): JsonResponse
    {
        $user = $this->premiumUser($request);
        $rows = collect($database->schoolRows($user));

        $rows = $this->filterSchools($rows, $request);

        return response()->json([
            ...$this->paginateRows($rows, $request),
            'lists' => $database->lists($user),
        ]);
    }

    public function coaches(Request $request, LocalRecruitingDatabaseService $database): JsonResponse
    {
        $user = $this->premiumUser($request);
        $rows = collect($database->coachRows($user));
        $search = strtolower(trim((string) $request->query('q', '')));

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search): bool {
                $haystack = strtolower(implode(' ', array_filter([
                    $row['name'] ?? '',
                    $row['email'] ?? '',
                    $row['title'] ?? '',
                    $row['school'] ?? $row['school_name'] ?? '',
                    $row['conference'] ?? '',
                    $row['division'] ?? '',
                    $row['city'] ?? '',
                    $row['state'] ?? '',
                ])));

                return str_contains($haystack, $search);
            });
        }

        foreach (['division', 'conference', 'state'] as $filter) {
            $value = strtolower(trim((string) $request->query($filter, '')));
            if ($value !== '') {
                $rows = $rows->filter(fn (array $row): bool => strtolower(trim((string) ($row[$filter] ?? ''))) === $value);
            }
        }

        return response()->json($this->paginateRows($rows, $request));
    }

    public function favorites(Request $request, LocalRecruitingDatabaseService $database): JsonResponse
    {
        $user = $this->premiumUser($request);
        $rows = $this->filterSchools(collect($database->favoriteSchools($user)), $request);

        return response()->json([
            ...$this->paginateRows($rows, $request),
            'lists' => $database->lists($user),
        ]);
    }

    public function lists(Request $request, LocalRecruitingDatabaseService $database): JsonResponse
    {
        $user = $this->premiumUser($request);

        return response()->json([
            'success' => true,
            'items' => $database->lists($user),
        ]);
    }

    public function createList(Request $request, LocalRecruitingDatabaseService $database): JsonResponse
    {
        $user = $this->premiumUser($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $database->createList(
            $user,
            trim((string) $data['name']),
            trim((string) ($data['color'] ?? '#ff6338')),
        );

        return response()->json([
            'success' => true,
            'message' => 'List created.',
            'items' => $database->lists($user),
        ]);
    }

    public function deleteList(Request $request, LocalRecruitingDatabaseService $database, string $list): JsonResponse
    {
        $user = $this->premiumUser($request);

        if (! $database->deleteList($user, $list)) {
            return response()->json([
                'success' => false,
                'message' => 'That list could not be deleted.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'List deleted.',
            'items' => $database->lists($user),
        ]);
    }

    public function conversations(
        Request $request,
        CoachDatabaseService $recruiting,
        LocalRecruitingDatabaseService $database,
    ): JsonResponse
    {
        $user = $this->premiumUser($request);
        $search = trim((string) $request->query('q', ''));
        $refresh = $request->boolean('refresh');
        $cacheKey = 'locker-room:inbox:' . $user->getKey() . ':' . md5(strtolower($search));

        $loader = function () use ($user, $recruiting, $search): array {
            return $recruiting->getConversationsForUser($user, [
                'limit' => 100,
                'search' => $search,
                'status' => 'all',
                'sortBy' => 'last_message_date',
                'sort' => 'desc',
                'fetch_all' => false,
            ]);
        };

        if ($refresh) {
            $result = $loader();
            if ($result['success'] ?? false) {
                Cache::put($cacheKey, $result, now()->addMinutes(5));
            }
        } else {
            $result = Cache::get($cacheKey);
            if (! is_array($result)) {
                $result = $loader();
                if ($result['success'] ?? false) {
                    Cache::put($cacheKey, $result, now()->addMinutes(5));
                }
            }
        }

        if ($result['success'] ?? false) {
            $coaches = collect($database->coachRows($user));
            $byContact = $coaches
                ->filter(fn (array $row): bool => filled($row['contact_id'] ?? $row['ghl_contact_id'] ?? null))
                ->keyBy(fn (array $row): string => strtolower(trim((string) ($row['contact_id'] ?? $row['ghl_contact_id'] ?? ''))));
            $byEmail = $coaches
                ->filter(fn (array $row): bool => filled($row['email'] ?? null))
                ->keyBy(fn (array $row): string => strtolower(trim((string) ($row['email'] ?? ''))));

            $result['conversations'] = collect((array) ($result['conversations'] ?? []))
                ->filter(fn ($row): bool => is_array($row))
                ->map(function (array $row) use ($byContact, $byEmail): array {
                    $contactId = strtolower(trim((string) ($row['contact_id'] ?? '')));
                    $email = strtolower(trim((string) ($row['email'] ?? '')));
                    $coach = ($contactId !== '' ? $byContact->get($contactId) : null)
                        ?: ($email !== '' ? $byEmail->get($email) : null);

                    $preview = $this->cleanInboxPreview((string) ($row['last_message'] ?? ''));

                    return [
                        ...$row,
                        'contact_name' => trim((string) ($coach['name'] ?? $row['contact_name'] ?? 'Coach')) ?: 'Coach',
                        'school' => trim((string) ($coach['school'] ?? $coach['school_name'] ?? '')),
                        'school_logo_url' => trim((string) ($coach['school_logo_url'] ?? $coach['logo_url'] ?? '')),
                        'title' => trim((string) ($coach['title'] ?? 'Coach')),
                        'conference' => trim((string) ($coach['conference'] ?? '')),
                        'division' => trim((string) ($coach['division'] ?? '')),
                        'preview' => $preview,
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function conversation(
        Request $request,
        CoachDatabaseService $recruiting,
        string $conversation,
    ): JsonResponse {
        $user = $this->premiumUser($request);
        $result = $recruiting->getConversationMessagesForUser(
            $user,
            trim($conversation),
            $request->filled('last_message_id') ? trim((string) $request->query('last_message_id')) : null,
            min(max((int) $request->query('limit', 10), 1), 100),
        );

        if ($result['success'] ?? false) {
            $result['messages'] = collect((array) ($result['messages'] ?? []))
                ->filter(fn ($row): bool => is_array($row))
                ->map(fn (array $row): array => [
                    ...$row,
                    'rendered_html' => $this->sanitizeInboxEmailHtml($row),
                    'display_text' => $this->inboxMessageText($row),
                ])
                ->values()
                ->all();
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function templates(Request $request): JsonResponse
    {
        $user = $this->premiumUser($request);

        return response()->json([
            'success' => true,
            'items' => $this->emailTemplates($user),
            'merge_values' => app(LocalTemplateMergeValueService::class)->availableValues(),
        ]);
    }

    public function template(Request $request, string $template): JsonResponse
    {
        $user = $this->premiumUser($request);
        $templateId = $this->templateDatabaseId($template);

        if (! $templateId || ! Schema::hasTable('coach_database_email_templates')) {
            return response()->json(['success' => false, 'message' => 'Template not found.'], 404);
        }

        $row = CoachDatabaseEmailTemplate::query()
            ->whereKey($templateId)
            ->where('is_active', true)
            ->where(function ($query) use ($user): void {
                $query->whereNull('user_id')->orWhere('user_id', $user->getKey());
            })
            ->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Template not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'item' => $this->templateRow($row),
        ]);
    }

    public function sendEmail(
        Request $request,
        LocalRecruitingDatabaseService $database,
        CoachDatabaseService $recruiting,
        LocalTemplateMergeValueService $mergeValues,
    ): JsonResponse {
        $user = $this->premiumUser($request);

        $data = $request->validate([
            'target_mode' => ['required', Rule::in(['school', 'coach', 'coaches', 'list', 'conversation'])],
            'school_id' => ['nullable', 'string', 'max:120'],
            'head_coach_only' => ['nullable', 'boolean'],
            'coach_id' => ['nullable', 'string', 'max:120'],
            'coach_ids' => ['nullable', 'array', 'max:100'],
            'coach_ids.*' => ['string', 'max:120'],
            'list_key' => ['nullable', 'string', 'max:120'],
            'conversation_id' => ['nullable', 'string', 'max:180'],
            'subject' => ['required', 'string', 'max:250'],
            'body' => ['required', 'string', 'max:100000'],
        ]);

        $recipients = $this->resolveRecipients(
            user: $user,
            data: $data,
            database: $database,
            recruiting: $recruiting,
        );

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'recipient' => 'Choose at least one coach with a valid email address.',
            ]);
        }

        $subject = trim((string) $data['subject']);
        $body = $this->sanitizeEmailHtml((string) $data['body']);

        if (trim(strip_tags($body)) === '' && ! str_contains(strtolower($body), '<img')) {
            throw ValidationException::withMessages(['body' => 'Enter an email message.']);
        }

        $sent = 0;
        $failed = [];
        $profileUrl = $mergeValues->profileUrlFor($user);

        foreach ($recipients->take(100) as $coach) {
            $personalizedSubject = $mergeValues->replace($subject, $user, $coach);
            $personalizedBody = $mergeValues->replace($body, $user, $coach);
            $personalizedBody = $this->appendPlayerSignature($personalizedBody, $user, $profileUrl);

            $contactId = trim((string) (
                $coach['contact_id']
                ?? $coach['ghl_contact_id']
                ?? ''
            ));
            $email = trim((string) ($coach['email'] ?? ''));

            $trackingContext = [
                'athlete_id' => $user->getKey(),
                'athlete_email' => $user->email ?: $user->personal_email,
                'contact_id' => $contactId,
                'coach_contact_id' => $contactId,
                'ghl_contact_id' => $contactId,
                'coach_name' => $coach['name'] ?? null,
                'coach_email' => $email,
                'business_id' => $coach['business_id'] ?? $coach['company_id'] ?? null,
                'ghl_business_id' => $coach['business_id'] ?? $coach['company_id'] ?? null,
                'school' => $coach['school'] ?? $coach['school_name'] ?? null,
                'school_name' => $coach['school'] ?? $coach['school_name'] ?? null,
                'school_logo_url' => $coach['school_logo_url'] ?? $coach['logo_url'] ?? null,
                'profile_url' => $profileUrl,
                'email_subject' => $personalizedSubject,
                'source' => 'locker_room_compose',
                'message_uuid' => (string) Str::uuid(),
            ];

            $result = $recruiting->sendEmailMessageForUser($user, [
                'contact_id' => $contactId !== '' ? $contactId : null,
                'contactId' => $contactId !== '' ? $contactId : null,
                'conversation_id' => $data['target_mode'] === 'conversation'
                    ? trim((string) ($data['conversation_id'] ?? ''))
                    : null,
                'subject' => $personalizedSubject,
                'body' => $personalizedBody,
                'html' => $personalizedBody,
                'text' => $this->emailPlainText($personalizedBody),
                'to' => $email,
                'emailTo' => $email,
                'fromName' => trim((string) ($user->name ?? 'PLYRCARD')),
                'tracking_context' => $trackingContext,
                'tracking_source' => 'locker_room_compose',
                'source' => 'locker_room_compose',
                'profile_url' => $profileUrl,
                'public_profile_url' => $profileUrl,
            ]);

            if ($result['success'] ?? false) {
                $sent++;
                continue;
            }

            $failed[] = [
                'name' => $coach['name'] ?? $email ?: 'Coach',
                'email' => $email,
                'error' => $result['error'] ?? 'Unable to send.',
            ];
        }

        return response()->json([
            'success' => $sent > 0 && $failed === [],
            'partial' => $sent > 0 && $failed !== [],
            'sent' => $sent,
            'failed' => count($failed),
            'failures' => $failed,
            'message' => $failed === []
                ? sprintf('Sent to %s coach%s.', number_format($sent), $sent === 1 ? '' : 'es')
                : sprintf('Sent to %s coach%s. %s failed.', number_format($sent), $sent === 1 ? '' : 'es', number_format(count($failed))),
        ], $sent > 0 ? 200 : 422);
    }

    public function sendTestEmail(
        Request $request,
        LocalRecruitingDatabaseService $database,
        LocalTemplateMergeValueService $mergeValues,
    ): JsonResponse {
        $user = $this->premiumUser($request);

        $data = $request->validate([
            'school_id' => ['nullable', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:250'],
            'body' => ['required', 'string', 'max:100000'],
        ]);

        $to = trim((string) ($user->personal_email ?: $user->email));

        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'email' => 'Add a valid personal/account email before sending a test email.',
            ]);
        }

        $coach = [
            'name' => 'Coach Preview',
            'first_name' => 'Coach',
            'last_name' => 'Preview',
            'email' => $to,
            'school' => 'Preview School',
            'school_name' => 'Preview School',
        ];

        $schoolId = trim((string) ($data['school_id'] ?? ''));
        if ($schoolId !== '') {
            $school = collect($database->schoolRows($user))
                ->first(function ($row) use ($schoolId): bool {
                    if (! is_array($row)) {
                        return false;
                    }

                    return in_array($schoolId, array_map('strval', array_filter([
                        $row['id'] ?? null,
                        $row['school_id'] ?? null,
                        $row['reference'] ?? null,
                        $row['business_id'] ?? null,
                    ])), true);
                });

            if (is_array($school)) {
                $coaches = collect((array) ($school['coaches'] ?? []))
                    ->filter(fn ($row): bool => is_array($row))
                    ->values();

                $head = $coaches->first(fn (array $row): bool => (bool) ($row['is_head_coach'] ?? $row['head_coach'] ?? false))
                    ?: $coaches->first();

                if (is_array($head)) {
                    $coach = array_merge($coach, $head);
                }

                $coach['school'] = (string) ($school['name'] ?? $coach['school']);
                $coach['school_name'] = (string) ($school['name'] ?? $coach['school_name']);
            }
        }

        $subject = $mergeValues->replace(trim((string) $data['subject']), $user, $coach);
        $html = $this->sanitizeEmailHtml((string) $data['body']);

        if (trim(strip_tags($html)) === '' && ! str_contains(strtolower($html), '<img')) {
            throw ValidationException::withMessages(['body' => 'Enter an email message.']);
        }

        $html = $mergeValues->replace($html, $user, $coach);
        $html = $this->appendPlayerSignature($html, $user, $mergeValues->profileUrlFor($user));

        Mail::html($html, function ($message) use ($to, $subject): void {
            $message
                ->to($to)
                ->subject('[TEST] ' . $subject);
        });

        return response()->json([
            'success' => true,
            'message' => 'Test email sent to ' . $to . ' with the current message and PLYRCard signature.',
        ]);
    }

    public function points(
        Request $request,
        PlyrPointCatalogService $catalog,
        CreditPointService $credits,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user, 403);

        $packs = collect((array) config('plyrcard-points.point_packs', []))
            ->map(function (array $pack, string $key): array {
                return [
                    'key' => $key,
                    'label' => trim((string) ($pack['label'] ?? '')) ?: number_format((int) ($pack['points'] ?? 0)) . ' Point Pack',
                    'points' => (int) ($pack['points'] ?? 0),
                    'price_cents' => (int) ($pack['price_cents'] ?? 0),
                    'enabled' => (bool) ($pack['enabled'] ?? true),
                    'note' => trim((string) ($pack['note'] ?? '')),
                ];
            })
            ->filter(fn (array $pack): bool => $pack['enabled'] && $pack['points'] > 0 && $pack['price_cents'] > 0)
            ->values()
            ->all();

        $requests = [];
        if (Schema::hasTable('credit_service_requests')) {
            $requests = CreditServiceRequest::query()
                ->where('user_id', $user->getKey())
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (CreditServiceRequest $row): array => [
                    'id' => $row->getKey(),
                    'item_key' => $row->item_key,
                    'item_name' => $row->item_name,
                    'quantity' => (int) $row->quantity,
                    'points_spent' => (int) $row->points_spent,
                    'credits_returned' => (int) $row->credits_returned,
                    'status' => $row->status,
                    'status_label' => $row->statusLabel(),
                    'notes' => $row->notes,
                    'admin_notes' => $row->admin_notes,
                    'delivery_url' => $row->delivery_url,
                    'delivery_notes' => $row->delivery_notes,
                    'provided_at' => optional($row->provided_at)->toIso8601String(),
                    'created_at' => optional($row->created_at)->toIso8601String(),
                    'updated_at' => optional($row->updated_at)->toIso8601String(),
                ])
                ->all();
        }

        return response()->json([
            'success' => true,
            'balance' => (int) ($user->fresh()->points_available ?? 0),
            'catalog' => array_values($catalog->catalog()),
            'point_packs' => $packs,
            'custom_purchase' => [
                'enabled' => (bool) config('plyrcard-points.custom_point_purchase.enabled', true),
                'min_points' => max(1, (int) config('plyrcard-points.custom_point_purchase.min_points', 1)),
                'max_points' => max(1, (int) config('plyrcard-points.custom_point_purchase.max_points', 1000)),
                'price_per_point_cents' => max(1, (int) config('plyrcard-points.custom_point_purchase.price_per_point_cents', 100)),
            ],
            'transactions' => $credits->recent($user, 30),
            'requests' => $requests,
            'can_buy_points' => $this->hasRole($user, 'My Journey'),
        ]);
    }

    protected function premiumUser(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user, 403);

        abort_unless(
            $this->hasRole($user, 'My Journey'),
            403,
            'My Journey is required for this Recruiting Center feature.',
        );

        return $user;
    }

    protected function hasRole(User $user, string $role): bool
    {
        try {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }

            if (method_exists($user, 'getRoleNames')) {
                return $user->getRoleNames()
                    ->contains(fn ($name): bool => strcasecmp(trim((string) $name), $role) === 0);
            }
        } catch (\Throwable) {
        }

        return false;
    }

    protected function filterSchools(Collection $rows, Request $request): Collection
    {
        $search = strtolower(trim((string) $request->query('q', '')));

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search): bool {
                $coachText = collect($row['coaches_preview'] ?? $row['coaches'] ?? [])
                    ->filter(fn ($coach): bool => is_array($coach))
                    ->map(fn (array $coach): string => implode(' ', array_filter([
                        $coach['name'] ?? '',
                        $coach['email'] ?? '',
                        $coach['title'] ?? '',
                    ])))
                    ->implode(' ');

                $haystack = strtolower(implode(' ', array_filter([
                    $row['name'] ?? '',
                    $row['conference'] ?? '',
                    $row['division'] ?? '',
                    $row['city'] ?? '',
                    $row['state'] ?? '',
                    $coachText,
                ])));

                return str_contains($haystack, $search);
            });
        }

        foreach (['division', 'conference', 'state'] as $filter) {
            $value = strtolower(trim((string) $request->query($filter, '')));
            if ($value !== '') {
                $rows = $rows->filter(fn (array $row): bool => strtolower(trim((string) ($row[$filter] ?? ''))) === $value);
            }
        }

        $listKey = trim((string) $request->query('list', ''));
        if ($listKey !== '') {
            $rows = $rows->filter(fn (array $row): bool => in_array($listKey, (array) ($row['list_keys'] ?? []), true));
        }

        return $rows;
    }

    protected function paginateRows(Collection $rows, Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max((int) $request->query('per_page', 30), 10), 60);
        $rows = $rows->values();
        $total = $rows->count();

        return [
            'success' => true,
            'items' => $rows->slice(($page - 1) * $perPage, $perPage)->values()->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    protected function resolveRecipients(
        User $user,
        array $data,
        LocalRecruitingDatabaseService $database,
        CoachDatabaseService $recruiting,
    ): Collection {
        $mode = (string) $data['target_mode'];

        if ($mode === 'school') {
            $schoolId = trim((string) ($data['school_id'] ?? ''));
            if ($schoolId === '') {
                return collect();
            }

            $school = $database->schoolRow($user, $schoolId);
            if (! is_array($school)) {
                return collect();
            }

            $coaches = collect((array) ($school['coaches'] ?? []))
                ->filter(fn ($coach): bool => is_array($coach) && filter_var($coach['email'] ?? '', FILTER_VALIDATE_EMAIL))
                ->map(function (array $coach) use ($school): array {
                    return [
                        ...$coach,
                        'school' => $school['name'] ?? $coach['school'] ?? '',
                        'school_name' => $school['name'] ?? $coach['school_name'] ?? '',
                        'business_id' => $school['business_id'] ?? $coach['business_id'] ?? '',
                    ];
                })
                ->values();

            if ($requestHeadOnly = (bool) ($data['head_coach_only'] ?? true)) {
                $head = $coaches->first(function (array $coach): bool {
                    $title = strtolower(trim((string) ($coach['title'] ?? '')));
                    return str_contains($title, 'head')
                        && ! str_contains($title, 'assistant')
                        && ! str_contains($title, 'associate');
                });

                return collect([$head ?: $coaches->first()])->filter();
            }

            return $coaches;
        }

        if ($mode === 'conversation') {
            $conversationId = trim((string) ($data['conversation_id'] ?? ''));
            if ($conversationId === '') {
                return collect();
            }

            $result = $recruiting->getConversationsForUser($user, [
                'limit' => 100,
                'status' => 'all',
                'fetch_all' => false,
            ]);

            $conversation = collect((array) ($result['conversations'] ?? []))
                ->first(fn ($row): bool => is_array($row) && (string) ($row['id'] ?? '') === $conversationId);

            if (! is_array($conversation)) {
                return collect();
            }

            return collect([[
                'id' => $conversation['contact_id'] ?? '',
                'contact_id' => $conversation['contact_id'] ?? '',
                'ghl_contact_id' => $conversation['contact_id'] ?? '',
                'name' => $conversation['contact_name'] ?? 'Coach',
                'email' => $conversation['email'] ?? '',
                'school' => $conversation['school'] ?? $conversation['company_name'] ?? '',
                'school_name' => $conversation['school'] ?? $conversation['company_name'] ?? '',
                'business_id' => $conversation['business_id'] ?? '',
            ]])->filter(fn (array $row): bool => filter_var($row['email'] ?? '', FILTER_VALIDATE_EMAIL) || filled($row['contact_id'] ?? null));
        }

        $coaches = collect($database->coachRows($user))
            ->filter(fn ($row): bool => is_array($row));

        if ($mode === 'list') {
            $listKey = trim((string) ($data['list_key'] ?? ''));
            $list = collect($database->lists($user))->firstWhere('key', $listKey);
            if (! is_array($list)) {
                return collect();
            }

            $schoolIds = collect($list['schools'] ?? [])
                ->map(fn ($school): string => is_array($school) ? (string) ($school['id'] ?? '') : '')
                ->filter()
                ->unique();

            return $coaches
                ->filter(fn (array $coach): bool => $schoolIds->contains((string) ($coach['school_id'] ?? '')))
                ->filter(fn (array $coach): bool => filter_var($coach['email'] ?? '', FILTER_VALIDATE_EMAIL))
                ->unique(fn (array $coach): string => strtolower((string) ($coach['contact_id'] ?? $coach['email'] ?? $coach['id'] ?? '')))
                ->values();
        }

        $ids = collect($mode === 'coach'
            ? [$data['coach_id'] ?? null]
            : ($data['coach_ids'] ?? []))
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique();

        return $coaches
            ->filter(function (array $coach) use ($ids): bool {
                return $ids->contains((string) ($coach['id'] ?? ''))
                    || $ids->contains((string) ($coach['local_id'] ?? ''))
                    || $ids->contains((string) ($coach['contact_id'] ?? ''))
                    || $ids->contains((string) ($coach['ghl_contact_id'] ?? ''));
            })
            ->filter(fn (array $coach): bool => filter_var($coach['email'] ?? '', FILTER_VALIDATE_EMAIL))
            ->unique(fn (array $coach): string => strtolower((string) ($coach['contact_id'] ?? $coach['email'] ?? $coach['id'] ?? '')))
            ->values();
    }

    protected function emailTemplates(User $user): array
    {
        if (! Schema::hasTable('coach_database_email_templates')) {
            return [$this->fallbackTemplate()];
        }

        $rows = CoachDatabaseEmailTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user): void {
                $query->whereNull('user_id')->orWhere('user_id', $user->getKey());
            })
            ->orderBy('is_locked', 'desc')
            ->orderBy('is_sample', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CoachDatabaseEmailTemplate $template): array => $this->templateRow($template))
            ->values()
            ->all();

        return $rows !== [] ? $rows : [$this->fallbackTemplate()];
    }

    protected function templateRow(CoachDatabaseEmailTemplate $template): array
    {
        return [
            'id' => 'local-template-' . $template->getKey(),
            'local_id' => $template->getKey(),
            'name' => (string) $template->name,
            'subject' => (string) $template->subject,
            'preview_text' => (string) ($template->preview_text ?? ''),
            'body_html' => (string) $template->body_html,
            'graphic_url' => $template->graphic_url,
            'attachments' => is_array($template->attachments) ? $template->attachments : [],
            'is_sample' => (bool) $template->is_sample,
            'is_locked' => (bool) $template->is_locked,
            'is_default' => (bool) ($template->is_locked && is_null($template->user_id)),
        ];
    }

    protected function fallbackTemplate(): array
    {
        return [
            'id' => 'locker-room-default',
            'local_id' => null,
            'name' => 'PLYRCARD Default Recruiting Email',
            'subject' => '{{AthleteName}} - {{Position}} interested in {{SchoolName}}',
            'preview_text' => 'Quick intro, profile, and recruiting links from {{AthleteName}}.',
            'body_html' => '<p>Hi Coach {{CoachLastName}},</p><p>My name is {{AthleteName}}, and I am a {{GraduationYear}} {{Position}}. I wanted to introduce myself because I am very interested in {{SchoolName}}.</p><p>I would love for your staff to review my player profile, highlights, and recruiting information.</p><p><a href="{{ProfileLink}}">View My Player Profile</a></p><p>Thank you for your time.</p><p>Best,<br>{{AthleteName}}</p>',
            'graphic_url' => null,
            'attachments' => [],
            'is_sample' => true,
            'is_locked' => true,
            'is_default' => true,
        ];
    }

    protected function templateDatabaseId(string $template): ?int
    {
        $template = trim($template);

        if (str_starts_with($template, 'local-template-')) {
            $template = substr($template, strlen('local-template-'));
        }

        return ctype_digit($template) ? (int) $template : null;
    }

    protected function cleanInboxPreview(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<\s*style\b[^>]*>.*?<\s*\/\s*style\s*>/is', ' ', $value) ?? $value;
        $value = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', ' ', $value) ?? $value;
        $value = strip_tags($value);
        $value = preg_replace('/\b(?:font-family|font-size|line-height|color|background|margin|padding|display|width|height)\s*:[^;}{]+;?/i', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return Str::limit($value !== '' ? $value : 'No message preview available.', 150, '…');
    }

    protected function sanitizeInboxEmailHtml(array $message): string
    {
        $raw = collect([
            $message['rendered_html'] ?? null,
            $message['html_body'] ?? null,
            $message['htmlBody'] ?? null,
            $message['body'] ?? null,
            $message['content'] ?? null,
            $message['text_body'] ?? null,
            $message['textBody'] ?? null,
            $message['text'] ?? null,
        ])->first(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '');

        $html = trim((string) ($raw ?? ''));
        if ($html === '') {
            return '<p><em>This email does not contain a displayable message body.</em></p>';
        }

        if (! str_contains($html, '<') && preg_match('/&lt;\/?[a-z][^&]*&gt;/i', $html)) {
            $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_contains($decoded, '<')) {
                $html = $decoded;
            }
        }

        if (! preg_match('/<\s*(?:p|div|br|strong|b|em|i|u|ul|ol|li|a|blockquote|span|h[1-6]|table|tr|td|th|img)\b/i', $html)) {
            return '<p>' . nl2br(e(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) . '</p>';
        }

        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $bodyMatch)) {
            $html = (string) ($bodyMatch[1] ?? $html);
        }

        $html = preg_replace('/<\s*style\b[^>]*>.*?<\s*\/\s*style\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/<\s*script\b[^>]*\/?>/is', '', $html) ?? $html;
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html) ?? $html;

        // Incoming email templates often carry desktop-only inline layout rules.
        // Strip only properties that can escape or horizontally stretch Locker Room;
        // keep normal typography, colors, spacing, and branding intact.
        $html = preg_replace_callback(
            '/\sstyle\s*=\s*(["\'])(.*?)\1/is',
            static function (array $matches): string {
                $style = (string) ($matches[2] ?? '');
                $style = preg_replace(
                    '/(?:^|;)\s*(?:position|z-index|left|right|top|bottom|transform|translate|float|clear|overflow(?:-x|-y)?|min-width|max-width|width|min-height|max-height)\s*:[^;]*/i',
                    '',
                    $style,
                ) ?? $style;
                $style = preg_replace('/;{2,}/', ';', $style) ?? $style;
                $style = trim($style, " ;\t\n\r\0\x0B");

                return $style !== '' ? ' style="' . e($style) . '"' : '';
            },
            $html,
        ) ?? $html;
        $html = preg_replace('/<\/?(?:html|head|body|meta|title|link)\b[^>]*>/i', '', $html) ?? $html;
        $html = strip_tags($html, '<p><div><br><strong><b><em><i><u><ul><ol><li><a><blockquote><span><h1><h2><h3><h4><h5><h6><table><tbody><thead><tr><td><th><img>');

        if (trim(strip_tags($html)) === '' && ! str_contains(strtolower($html), '<img')) {
            $text = $this->inboxMessageText($message);
            return $text !== ''
                ? '<p>' . nl2br(e($text)) . '</p>'
                : '<p><em>This email does not contain a displayable message body.</em></p>';
        }

        return trim($html);
    }

    protected function inboxMessageText(array $message): string
    {
        $raw = collect([
            $message['text_body'] ?? null,
            $message['textBody'] ?? null,
            $message['text'] ?? null,
            $message['html_body'] ?? null,
            $message['body'] ?? null,
        ])->first(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '');

        $text = html_entity_decode((string) ($raw ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected function sanitizeEmailHtml(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        // Rich-editor values can arrive entity-escaped after a template round trip.
        // Decode only when doing so reveals actual HTML markup.
        for ($i = 0; $i < 3; $i++) {
            if (str_contains($html, '<')) {
                break;
            }

            $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $html || ! str_contains($decoded, '<')) {
                break;
            }

            $html = $decoded;
        }

        if (! preg_match('/<\s*(?:p|div|br|strong|b|em|i|u|ul|ol|li|a|blockquote|span|h[1-6]|table|tr|td|th|img)\b/i', $html)) {
            return '<p>' . nl2br(e(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')), false) . '</p>';
        }

        $html = preg_replace('#<(script|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = strip_tags($html, '<p><div><br><strong><b><em><i><u><ul><ol><li><a><blockquote><span><h1><h2><h3><h4><h5><h6><table><tbody><thead><tr><td><th><img>');
        $html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*[^\s>]+/is', '', $html) ?? $html;
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html) ?? $html;

        // Chrome contenteditable occasionally serializes blank lines as empty DIVs.
        // Preserve those visual breaks rather than collapsing them on send.
        $html = preg_replace('/<div([^>]*)>\s*<br\s*\/?>\s*<\/div>/i', '<div$1><br></div>', $html) ?? $html;
        $html = preg_replace('/<p([^>]*)>\s*<\/p>/i', '<p$1><br></p>', $html) ?? $html;

        return trim($html);
    }

    protected function emailPlainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/(?:p|div|li|blockquote|h[1-6]|tr)>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<li\b[^>]*>/i', '• ', $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected function appendPlayerSignature(string $html, User $user, string $profileUrl): string
    {
        if (str_contains($html, 'data-plyrcard-signature')) {
            return $html;
        }

        $name = e(trim((string) ($user->name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')))));
        $email = e(trim((string) ($user->email ?: $user->personal_email ?? '')));
        $profile = e($profileUrl);

        $profileLink = $profileUrl !== ''
            ? '<a href="' . $profile . '" style="color:#111827;font-weight:700;text-decoration:none;">View my PLYRCARD</a>'
            : '';

        return trim($html)
            . '<div data-plyrcard-signature="1" style="margin-top:24px;padding-top:16px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
            . '<strong>' . $name . '</strong>'
            . ($email !== '' ? '<div style="margin-top:4px;font-size:13px;color:#667085;">' . $email . '</div>' : '')
            . ($profileLink !== '' ? '<div style="margin-top:8px;font-size:13px;">' . $profileLink . '</div>' : '')
            . '</div>';
    }
}
