<?php

namespace App\Jobs;

use App\Models\Coach;
use App\Models\MyListSchool;
use App\Services\GoHighLevelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncMyListSchoolTagsToGhl implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(
        public int $myListId,
        public int $schoolId,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return $this->myListId . ':' . $this->schoolId;
    }

    public function backoff(): array
    {
        return [10, 30, 90, 180];
    }

    public function handle(GoHighLevelService $ghl): void
    {
        $membership = MyListSchool::query()
            ->where('my_list_id', $this->myListId)
            ->where('school_id', $this->schoolId)
            ->with([
                'list.user',
                'school.coaches' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('id'),
            ])
            ->first();

        // The school may have been removed again before this queued job ran.
        if (! $membership || ! $membership->list || ! $membership->school) {
            return;
        }

        $list = $membership->list;
        $user = $list->user;

        if (! $user) {
            return;
        }

        $tag = $this->tagForList($list->slug, $list->name, (bool) $list->is_system);
        if ($tag === '') {
            return;
        }

        $gender = Coach::normalizeGender($user->gender ?? null);

        $coaches = collect($membership->school->coaches ?? [])
            ->when($gender, fn ($rows) => $rows->filter(
                fn (Coach $coach): bool => Coach::normalizeGender($coach->gender) === $gender
            ))
            ->filter(fn (Coach $coach): bool => filled($coach->email))
            ->unique(fn (Coach $coach): string => strtolower(trim((string) $coach->email)))
            ->values();

        if ($coaches->isEmpty()) {
            Log::info('My Lists GHL tag sync skipped: school has no matching coach emails.', [
                'user_id' => $user->getKey(),
                'my_list_id' => $list->getKey(),
                'school_id' => $membership->school->getKey(),
                'tag' => $tag,
            ]);

            return;
        }

        $contactIds = [];
        $unresolvedEmails = [];

        foreach ($coaches as $coach) {
            $email = strtolower(trim((string) $coach->email));
            $resolved = $ghl->resolveCoachContactByEmailForUser($user, $email);

            if (($resolved['success'] ?? false) && filled($resolved['contact_id'] ?? null)) {
                $contactIds[] = (string) $resolved['contact_id'];
                continue;
            }

            $unresolvedEmails[] = $email;
        }

        $contactIds = array_values(array_unique(array_filter($contactIds)));

        if ($contactIds === []) {
            Log::warning('My Lists GHL tag sync found no contacts in player subaccount.', [
                'user_id' => $user->getKey(),
                'my_list_id' => $list->getKey(),
                'school_id' => $membership->school->getKey(),
                'tag' => $tag,
                'unresolved_emails' => $unresolvedEmails,
            ]);

            return;
        }

        $result = $ghl->updateContactTagsForUser(
            $user,
            $contactIds,
            [$tag],
            'add',
        );

        if (! ($result['success'] ?? false) && ! ($result['partial_success'] ?? false)) {
            throw new \RuntimeException((string) ($result['error'] ?? 'Unable to tag My Lists contacts in GHL.'));
        }

        Log::info('My Lists school tagged in GHL.', [
            'user_id' => $user->getKey(),
            'my_list_id' => $list->getKey(),
            'list_slug' => $list->slug,
            'school_id' => $membership->school->getKey(),
            'school_name' => $membership->school->name,
            'tag' => $tag,
            'requested_contacts' => count($contactIds),
            'updated_contacts' => count($result['updated_contact_ids'] ?? []),
            'failed_contacts' => $result['failed_contact_ids'] ?? [],
            'unresolved_emails' => $unresolvedEmails,
        ]);
    }

    protected function tagForList(string $slug, string $name, bool $isSystem): string
    {
        $slug = strtolower(trim($slug));

        $configKey = match ($slug) {
            'general_recruiting' => 'general',
            default => $slug,
        };

        $configured = trim((string) config("ghl.coach_database.lists.{$configKey}.tag", ''));

        if ($isSystem && $configured !== '') {
            return $configured;
        }

        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return Str::lower('plyrcard list: ' . $name);
    }
}
