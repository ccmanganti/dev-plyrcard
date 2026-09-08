<?php

namespace App\Livewire;

use App\Models\AdminSupportMessage;
use App\Models\User;
use App\Services\AdminSupportMessagingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

class AdminSupportMessenger extends Component
{
    public string $audienceMode = 'individual';
    public string $userSearch = '';
    public ?int $targetUserId = null;
    public array $customUserIds = [];
    public bool $bulkConfirmed = false;

    public string $concern = 'finish_profile';
    public string $channel = 'email';
    public string $subject = '';
    public string $message = '';
    public ?string $notice = null;
    public string $noticeType = 'success';

    public function mount(AdminSupportMessagingService $messenger): void
    {
        $admin = auth()->user();
        abort_unless($messenger->isAdmin($admin), 403);

        $this->loadConcernTemplate($messenger, $this->concern);
        $this->autoSelectUserFromAdminRoute($messenger);
    }

    public function selectAudienceMode(string $mode, AdminSupportMessagingService $messenger): void
    {
        $this->authorizeAdmin($messenger);

        if (! in_array($mode, ['all', 'custom', 'individual'], true)) {
            return;
        }

        $this->audienceMode = $mode;
        $this->bulkConfirmed = false;
        $this->notice = null;

        if ($mode !== 'individual') {
            $this->targetUserId = null;
        }
    }

    public function selectTargetUser(int $userId, AdminSupportMessagingService $messenger): void
    {
        $this->authorizeAdmin($messenger);

        $target = $this->baseRecipientQuery($messenger)->find($userId);
        if (! $target) {
            return;
        }

        $this->audienceMode = 'individual';
        $this->targetUserId = (int) $target->getKey();
        $this->userSearch = '';
        $this->bulkConfirmed = false;
        $this->notice = null;
        $this->loadConcernTemplate($messenger, $this->concern);
    }

    public function clearTarget(): void
    {
        $this->targetUserId = null;
        $this->bulkConfirmed = false;
        $this->notice = null;
    }

    public function toggleCustomUser(int $userId, AdminSupportMessagingService $messenger): void
    {
        $this->authorizeAdmin($messenger);

        if (! $this->baseRecipientQuery($messenger)->whereKey($userId)->exists()) {
            return;
        }

        $ids = collect($this->customUserIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->contains($userId)) {
            $ids = $ids->reject(fn (int $id): bool => $id === $userId)->values();
        } else {
            $ids->push($userId);
        }

        $this->customUserIds = $ids->all();
        $this->audienceMode = 'custom';
        $this->bulkConfirmed = false;
        $this->notice = null;
    }

    public function clearCustomUsers(): void
    {
        $this->customUserIds = [];
        $this->bulkConfirmed = false;
        $this->notice = null;
    }

    public function selectConcern(string $concern, AdminSupportMessagingService $messenger): void
    {
        $this->authorizeAdmin($messenger);

        if (! array_key_exists($concern, $messenger->concerns())) {
            return;
        }

        $this->concern = $concern;
        $this->notice = null;
        $this->loadConcernTemplate($messenger, $concern);
    }

    public function setChannel(string $channel, AdminSupportMessagingService $messenger): void
    {
        $this->authorizeAdmin($messenger);

        if (in_array($channel, ['email', 'sms', 'email_sms'], true)) {
            $this->channel = $channel;
            $this->bulkConfirmed = false;
            $this->notice = null;
        }
    }

    public function appendVariable(string $key, AdminSupportMessagingService $messenger): void
    {
        $this->authorizeAdmin($messenger);

        if (! array_key_exists($key, $messenger->variableDefinitions())) {
            return;
        }

        $token = '{{' . $key . '}}';
        $this->message = rtrim($this->message) . ($this->message !== '' ? ' ' : '') . $token;
    }

    public function resetTemplate(AdminSupportMessagingService $messenger): void
    {
        $this->authorizeAdmin($messenger);
        $this->loadConcernTemplate($messenger, $this->concern);
        $this->notice = null;
    }

    public function send(AdminSupportMessagingService $messenger): void
    {
        $admin = $this->authorizeAdmin($messenger);

        $this->validate([
            'audienceMode' => ['required', 'in:all,custom,individual'],
            'concern' => ['required', 'string', 'max:80'],
            'channel' => ['required', 'in:email,sms,email_sms'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $recipientCount = $this->audienceCount;
        if ($recipientCount < 1) {
            $this->noticeType = 'error';
            $this->notice = match ($this->audienceMode) {
                'custom' => 'Choose at least one user for the custom list.',
                'individual' => 'Choose an individual user first.',
                default => 'No eligible user accounts were found.',
            };
            return;
        }

        if (($this->audienceMode !== 'individual' || $recipientCount > 1) && ! $this->bulkConfirmed) {
            $this->noticeType = 'warning';
            $this->notice = 'Confirm the recipient list before sending this bulk message.';
            return;
        }

        $batchId = (string) Str::uuid();
        $summary = [
            'attempted' => 0,
            'success' => 0,
            'partial' => 0,
            'failed' => 0,
            'email_sent' => 0,
            'email_failed' => 0,
            'sms_sent' => 0,
            'sms_failed' => 0,
        ];

        try {
            $query = $this->recipientQuery($messenger);

            $query->orderBy('id')->chunkById(50, function ($users) use ($messenger, $admin, $batchId, &$summary): void {
                foreach ($users as $target) {
                    $summary['attempted']++;

                    try {
                        $result = $messenger->send(
                            admin: $admin,
                            target: $target,
                            concern: $this->concern,
                            channel: $this->channel,
                            subjectTemplate: $this->subject,
                            messageTemplate: $this->message,
                            audienceMode: $this->audienceMode,
                            batchId: $batchId,
                        );

                        if ($result['success'] ?? false) {
                            $summary['success']++;
                        } elseif ($result['partial'] ?? false) {
                            $summary['partial']++;
                        } else {
                            $summary['failed']++;
                        }

                        if (($result['email']['requested'] ?? false)) {
                            ($result['email']['success'] ?? false)
                                ? $summary['email_sent']++
                                : $summary['email_failed']++;
                        }

                        if (($result['sms']['requested'] ?? false)) {
                            ($result['sms']['success'] ?? false)
                                ? $summary['sms_sent']++
                                : $summary['sms_failed']++;
                        }
                    } catch (\Throwable $exception) {
                        report($exception);
                        $summary['failed']++;

                        if (in_array($this->channel, ['email', 'email_sms'], true)) {
                            $summary['email_failed']++;
                        }
                        if (in_array($this->channel, ['sms', 'email_sms'], true)) {
                            $summary['sms_failed']++;
                        }
                    }
                }
            });

            $deliveredUsers = $summary['success'] + $summary['partial'];
            $parts = [
                "{$deliveredUsers} of {$summary['attempted']} user(s) received at least one requested channel",
            ];

            if (in_array($this->channel, ['email', 'email_sms'], true)) {
                $parts[] = "Email {$summary['email_sent']} sent / {$summary['email_failed']} failed or skipped";
            }
            if (in_array($this->channel, ['sms', 'email_sms'], true)) {
                $parts[] = "SMS {$summary['sms_sent']} sent / {$summary['sms_failed']} failed or skipped";
            }

            $this->noticeType = $summary['failed'] === 0 && $summary['partial'] === 0 ? 'success' : ($deliveredUsers > 0 ? 'warning' : 'error');
            $this->notice = implode(' · ', $parts) . '.';
            $this->bulkConfirmed = false;
        } catch (\Throwable $exception) {
            report($exception);
            $this->noticeType = 'error';
            $this->notice = $exception->getMessage();
        }
    }

    public function getTargetUserProperty(): ?User
    {
        if (! $this->targetUserId) {
            return null;
        }

        return $this->baseRecipientQuery(app(AdminSupportMessagingService::class))
            ->with(['roles', 'activeWebsite'])
            ->find($this->targetUserId);
    }

    public function getTargetUsersProperty(): Collection
    {
        $messenger = app(AdminSupportMessagingService::class);
        $query = $this->baseRecipientQuery($messenger)
            ->with('roles')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $search = trim($this->userSearch);
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('personal_email', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        return $query->limit($search !== '' ? 40 : 16)->get();
    }

    public function getSelectedCustomUsersProperty(): Collection
    {
        $ids = collect($this->customUserIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = $this->baseRecipientQuery(app(AdminSupportMessagingService::class))
            ->whereIn('id', $ids->all())
            ->with('roles')
            ->get()
            ->keyBy('id');

        return $ids->map(fn (int $id) => $rows->get($id))->filter()->values();
    }

    public function getAudienceCountProperty(): int
    {
        return $this->recipientQuery(app(AdminSupportMessagingService::class))->count();
    }

    public function getPersonalEmailCountProperty(): int
    {
        return $this->recipientQuery(app(AdminSupportMessagingService::class))
            ->whereNotNull('personal_email')
            ->where('personal_email', '!=', '')
            ->count();
    }

    public function getPhoneCountProperty(): int
    {
        return $this->recipientQuery(app(AdminSupportMessagingService::class))
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->count();
    }

    public function getPreviewUserProperty(): ?User
    {
        if ($this->audienceMode === 'individual') {
            return $this->targetUser;
        }

        if ($this->audienceMode === 'custom') {
            return $this->selectedCustomUsers->first();
        }

        return $this->recipientQuery(app(AdminSupportMessagingService::class))
            ->with(['roles', 'activeWebsite'])
            ->orderBy('id')
            ->first();
    }

    public function getVariablesProperty(): array
    {
        $target = $this->previewUser;
        $admin = auth()->user();

        if (! $target || ! $admin) {
            return [];
        }

        return app(AdminSupportMessagingService::class)->variablesFor($target, $admin);
    }

    public function getRenderedSubjectProperty(): string
    {
        return $this->previewUser
            ? app(AdminSupportMessagingService::class)->renderTemplate($this->subject, $this->variables)
            : $this->subject;
    }

    public function getRenderedMessageProperty(): string
    {
        return $this->previewUser
            ? app(AdminSupportMessagingService::class)->renderTemplate($this->message, $this->variables)
            : $this->message;
    }

    public function getHistoryProperty(): Collection
    {
        if ($this->audienceMode !== 'individual' || ! $this->targetUserId || ! Schema::hasTable('admin_support_messages')) {
            return collect();
        }

        return AdminSupportMessage::query()
            ->where('user_id', $this->targetUserId)
            ->with('admin:id,first_name,last_name')
            ->latest('id')
            ->limit(5)
            ->get();
    }

    public function render(AdminSupportMessagingService $messenger)
    {
        $this->authorizeAdmin($messenger);

        return view('livewire.admin-support-messenger', [
            'concerns' => $messenger->concerns(),
            'variableDefinitions' => $messenger->variableDefinitions(),
        ]);
    }

    protected function loadConcernTemplate(AdminSupportMessagingService $messenger, string $concern): void
    {
        $template = $messenger->template($concern);
        $this->subject = $template['subject'];
        $this->message = $template['message'];
    }

    protected function authorizeAdmin(AdminSupportMessagingService $messenger): User
    {
        $admin = auth()->user();
        abort_unless($messenger->isAdmin($admin), 403);
        return $admin;
    }

    protected function recipientQuery(AdminSupportMessagingService $messenger): Builder
    {
        $query = $this->baseRecipientQuery($messenger);

        return match ($this->audienceMode) {
            'custom' => $query->whereIn('id', collect($this->customUserIds)->map(fn ($id): int => (int) $id)->filter()->unique()->all()),
            'individual' => $this->targetUserId ? $query->whereKey($this->targetUserId) : $query->whereRaw('1 = 0'),
            default => $query,
        };
    }

    protected function baseRecipientQuery(AdminSupportMessagingService $messenger): Builder
    {
        $query = User::query()->where('id', '!=', (int) auth()->id());
        $roles = collect($messenger->adminRoles())->map(fn ($role): string => strtolower(trim($role)))->filter()->unique()->values();

        if ($roles->isNotEmpty()) {
            $query->whereDoesntHave('roles', function (Builder $roleQuery) use ($roles): void {
                $roleQuery->where(function (Builder $nested) use ($roles): void {
                    foreach ($roles as $index => $role) {
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $nested->{$method}('LOWER(name) = ?', [$role]);
                    }
                });
            });
        }

        return $query;
    }

    protected function autoSelectUserFromAdminRoute(AdminSupportMessagingService $messenger): void
    {
        try {
            $route = request()->route();
            $routeName = (string) ($route?->getName() ?? '');
            if ($routeName === '' || ! str_contains(strtolower($routeName), 'users')) {
                return;
            }

            $record = request()->route('record');
            $id = $record instanceof User ? (int) $record->getKey() : (is_numeric($record) ? (int) $record : 0);

            if ($id > 0 && $this->baseRecipientQuery($messenger)->whereKey($id)->exists()) {
                $this->audienceMode = 'individual';
                $this->targetUserId = $id;
            }
        } catch (\Throwable) {
            // Route auto-selection is convenience only.
        }
    }
}