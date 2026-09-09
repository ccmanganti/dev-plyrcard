<?php

namespace App\Livewire;

use App\Models\AdminSupportMessage;
use App\Models\User;
use App\Services\AdminSupportMessagingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class AdminSupportMessenger extends Component
{
    #[Locked]
    public ?int $adminUserId = null;

    public string $audienceMode = 'individual';
    public string $userSearch = '';
    public ?int $targetUserId = null;
    public array $customUserIds = [];
    public bool $bulkConfirmed = false;

    public string $concern = 'finish_profile';
    public string $subject = '';
    public string $message = '';
    public ?string $notice = null;
    public string $noticeType = 'success';

    public function mount(AdminSupportMessagingService $messenger): void
    {
        $admin = auth()->user();
        abort_unless($messenger->isAdmin($admin), 403);

        $this->adminUserId = (int) $admin->getKey();
        $this->loadConcernTemplate($messenger, $this->concern);
        $this->autoSelectUserFromAdminRoute($messenger);
    }

    /**
     * These selection actions intentionally do not re-run auth()->user().
     * The component is mounted only for admins and adminUserId is locked into the
     * signed Livewire snapshot. This avoids false 403s on generic Livewire update
     * requests while keeping the actual send operation server-authorized.
     */

    #[On('open-admin-support-reminder')]
    public function openReminder(int $userId, string $concern, AdminSupportMessagingService $messenger): void
    {
        $target = $this->baseRecipientQuery($messenger)->find($userId);

        if (! $target) {
            $this->noticeType = 'error';
            $this->notice = 'That athlete could not be loaded for Admin Support.';
            $this->dispatch('admin-support-open');
            return;
        }

        $concerns = $messenger->concerns();
        if (! array_key_exists($concern, $concerns)) {
            $concern = 'custom';
        }

        $this->audienceMode = 'individual';
        $this->targetUserId = (int) $target->getKey();
        $this->customUserIds = [];
        $this->bulkConfirmed = false;
        $this->userSearch = '';
        $this->concern = $concern;
        $this->notice = null;
        $this->loadConcernTemplate($messenger, $concern);

        if (! $messenger->personalEmailFor($target)) {
            $this->noticeType = 'warning';
            $this->notice = 'This athlete is selected, but no valid personal email is on file yet.';
        }

        $this->dispatch('admin-support-open');
    }

    public function selectAudienceMode(string $mode): void
    {
        if (! in_array($mode, ['all', 'custom', 'individual'], true)) {
            return;
        }

        $this->audienceMode = $mode;
        $this->bulkConfirmed = false;
        $this->notice = null;

        if ($mode !== 'individual') {
            $this->targetUserId = null;
        }

        if ($mode !== 'custom') {
            $this->customUserIds = [];
        }
    }

    public function selectTargetUser(int $userId, AdminSupportMessagingService $messenger): void
    {
        $target = $this->baseRecipientQuery($messenger)->find($userId);
        if (! $target) {
            return;
        }

        if (! $messenger->personalEmailFor($target)) {
            $this->noticeType = 'warning';
            $this->notice = 'That user does not have a valid personal email address.';
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
        $target = $this->baseRecipientQuery($messenger)->find($userId);
        if (! $target) {
            return;
        }

        if (! $messenger->personalEmailFor($target)) {
            $this->noticeType = 'warning';
            $this->notice = 'That user cannot be added because no valid personal email is on file.';
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
        if (! array_key_exists($concern, $messenger->concerns())) {
            return;
        }

        $this->concern = $concern;
        $this->notice = null;
        $this->loadConcernTemplate($messenger, $concern);
    }

    public function appendVariable(string $key, AdminSupportMessagingService $messenger): void
    {
        if (! array_key_exists($key, $messenger->variableDefinitions())) {
            return;
        }

        $token = '{{' . $key . '}}';
        $this->message = rtrim($this->message) . ($this->message !== '' ? ' ' : '') . $token;
    }

    public function resetTemplate(AdminSupportMessagingService $messenger): void
    {
        $this->loadConcernTemplate($messenger, $this->concern);
        $this->notice = null;
    }

    public function send(AdminSupportMessagingService $messenger): void
    {
        $admin = $this->lockedAdmin($messenger);

        $this->validate([
            'audienceMode' => ['required', 'in:all,custom,individual'],
            'concern' => ['required', 'string', 'max:80'],
            'subject' => ['required', 'string', 'max:255'],
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
            $this->notice = 'Confirm the recipient list before sending this email.';
            return;
        }

        $batchId = (string) Str::uuid();
        $summary = [
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
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
                            subjectTemplate: $this->subject,
                            messageTemplate: $this->message,
                            audienceMode: $this->audienceMode,
                            batchId: $batchId,
                        );

                        $status = (string) data_get($result, 'email.status', 'failed');
                        if ($result['success'] ?? false) {
                            $summary['sent']++;
                        } elseif (str_starts_with($status, 'skipped_')) {
                            $summary['skipped']++;
                        } else {
                            $summary['failed']++;
                        }
                    } catch (\Throwable $exception) {
                        report($exception);
                        $summary['failed']++;
                    }
                }
            });

            $this->noticeType = $summary['failed'] === 0 ? ($summary['sent'] > 0 ? 'success' : 'warning') : ($summary['sent'] > 0 ? 'warning' : 'error');
            $this->notice = "{$summary['sent']} email(s) sent";

            if ($summary['skipped'] > 0) {
                $this->notice .= " · {$summary['skipped']} skipped (no valid personal email)";
            }
            if ($summary['failed'] > 0) {
                $this->notice .= " · {$summary['failed']} failed";
            }

            $this->notice .= '.';
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
                    ->orWhere('personal_email', 'like', $like);
            });
        }

        return $query->limit($search !== '' ? 35 : 12)->get();
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
        $admin = $this->adminUserId ? User::find($this->adminUserId) : null;

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
        // Do not perform auth()->user() authorization here. Livewire update requests
        // for this global Filament render-hook component can run outside the panel's
        // route middleware stack. Authorization is enforced at mount and again at send.
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

    protected function lockedAdmin(AdminSupportMessagingService $messenger): User
    {
        $admin = $this->adminUserId
            ? User::query()->with('roles')->find($this->adminUserId)
            : null;

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
        $query = User::query();

        if ($this->adminUserId) {
            $query->where('id', '!=', $this->adminUserId);
        }

        $roles = collect($messenger->adminRoles())
            ->map(fn ($role): string => strtolower(trim($role)))
            ->filter()
            ->unique()
            ->values();

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