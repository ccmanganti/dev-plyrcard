<?php

namespace App\Livewire;

use App\Models\AdminSupportMessage;
use App\Models\User;
use App\Services\AdminSupportMessagingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class AdminSupportMessenger extends Component
{
    public string $userSearch = '';
    public ?int $targetUserId = null;
    public string $concern = 'finish_profile';
    public string $channel = 'email_sms';
    public string $subject = '';
    public string $message = '';
    public ?string $notice = null;
    public string $noticeType = 'success';

    public function mount(AdminSupportMessagingService $messenger): void
    {
        $admin = auth()->user();
        abort_unless($messenger->isAdmin($admin), 403);

        $this->loadConcernTemplate($messenger, $this->concern);
        $this->autoSelectUserFromAdminRoute();
    }

    public function selectTargetUser(int $userId, AdminSupportMessagingService $messenger): void
    {
        $this->authorizeAdmin($messenger);

        $target = User::query()->find($userId);
        if (! $target || (int) $target->getKey() === (int) auth()->id()) {
            return;
        }

        $this->targetUserId = (int) $target->getKey();
        $this->userSearch = '';
        $this->notice = null;
        $this->loadConcernTemplate($messenger, $this->concern);
    }

    public function clearTarget(): void
    {
        $this->targetUserId = null;
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
        $target = $this->targetUser;

        if (! $target) {
            $this->noticeType = 'error';
            $this->notice = 'Choose a user first.';
            return;
        }

        $this->validate([
            'concern' => ['required', 'string', 'max:80'],
            'channel' => ['required', 'in:email,sms,email_sms'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $result = $messenger->send(
                admin: $admin,
                target: $target,
                concern: $this->concern,
                channel: $this->channel,
                subjectTemplate: $this->subject,
                messageTemplate: $this->message,
            );

            if ($result['success'] ?? false) {
                $this->noticeType = 'success';
                $this->notice = 'Message sent to ' . $this->targetName($target) . '.';
            } elseif ($result['partial'] ?? false) {
                $this->noticeType = 'warning';
                $this->notice = 'Part of the message was sent. ' . implode(' · ', $result['errors'] ?? []);
            } else {
                $this->noticeType = 'error';
                $this->notice = implode(' · ', $result['errors'] ?? []) ?: 'The message could not be sent.';
            }
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

        return User::query()->with('roles')->find($this->targetUserId);
    }

    public function getTargetUsersProperty()
    {
        $query = User::query()
            ->where('id', '!=', auth()->id())
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $search = trim($this->userSearch);
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('personal_email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        return $query->limit($search !== '' ? 30 : 12)->get();
    }

    public function getVariablesProperty(): array
    {
        $target = $this->targetUser;
        $admin = auth()->user();

        if (! $target || ! $admin) {
            return [];
        }

        return app(AdminSupportMessagingService::class)->variablesFor($target, $admin);
    }

    public function getRenderedSubjectProperty(): string
    {
        if (! $this->targetUser) {
            return $this->subject;
        }

        return app(AdminSupportMessagingService::class)->renderTemplate($this->subject, $this->variables);
    }

    public function getRenderedMessageProperty(): string
    {
        if (! $this->targetUser) {
            return $this->message;
        }

        return app(AdminSupportMessagingService::class)->renderTemplate($this->message, $this->variables);
    }

    public function getHistoryProperty()
    {
        if (! $this->targetUserId || ! Schema::hasTable('admin_support_messages')) {
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

    protected function autoSelectUserFromAdminRoute(): void
    {
        try {
            $route = request()->route();
            $routeName = (string) ($route?->getName() ?? '');
            if ($routeName === '' || ! str_contains(strtolower($routeName), 'users')) {
                return;
            }

            $record = request()->route('record');
            if ($record instanceof User) {
                if ((int) $record->getKey() !== (int) auth()->id()) {
                    $this->targetUserId = (int) $record->getKey();
                }
                return;
            }

            if (is_numeric($record)) {
                $id = (int) $record;
                if ($id > 0 && $id !== (int) auth()->id() && User::query()->whereKey($id)->exists()) {
                    $this->targetUserId = $id;
                }
            }
        } catch (\Throwable) {
            // The messenger is global; route auto-selection is only a convenience.
        }
    }

    protected function targetName(User $target): string
    {
        return trim((string) (($target->first_name ?? '') . ' ' . ($target->last_name ?? '')))
            ?: (string) ($target->email ?? 'user');
    }
}
