<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CreditServiceRequests\CreditServiceRequestResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\CreditServiceRequest;
use App\Models\PaymentTransaction;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SuperadminActivityWidget extends Widget
{
    protected string $view = 'filament.widgets.superadmin-activity-widget';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    /** @var array<int, string> */
    protected array $athleteRoleNames = [
        'Free',
        'My Journey',
        'Jumpstart',
        'Amplify',
        'Plyr',
        'PLYR+',
    ];

    /** @var array<int, string> */
    protected array $operatorRoleNames = [
        'Superadmin',
        'superadmin',
        'Super Admin',
        'Administrator',
        'Admin',
        'Club Manager',
    ];

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            ));
    }

    public function getViewData(): array
    {
        return [
            'serviceActivity' => $this->recentServiceRequests(),
            'signupActivity' => $this->recentSignups(),
            'paymentActivity' => $this->recentPayments(),
            'lastUpdated' => now()->format('g:i A'),
        ];
    }

    protected function recentServiceRequests(): array
    {
        if (! class_exists(CreditServiceRequest::class) || ! Schema::hasTable('credit_service_requests')) {
            return [
                'items' => [],
                'summary' => 'No request data yet',
                'all_url' => null,
            ];
        }

        try {
            $weekCount = CreditServiceRequest::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            $items = CreditServiceRequest::query()
                ->with('user:id,first_name,last_name,email')
                ->latest('created_at')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(function (CreditServiceRequest $request): array {
                    $user = $request->user;

                    return [
                        'id' => (int) $request->getKey(),
                        'player' => $this->userName($user),
                        'initials' => $this->userInitials($user),
                        'service' => trim((string) $request->item_name) ?: 'Service request',
                        'quantity' => max(1, (int) $request->quantity),
                        'points' => max(0, (int) $request->points_spent),
                        'status' => $request->statusLabel(),
                        'status_tone' => $this->requestStatusTone((string) $request->status),
                        'rush' => (string) $request->modifier === 'rush',
                        'when' => $request->created_at?->diffForHumans() ?? '',
                        'url' => $this->requestUrl($request),
                    ];
                })
                ->all();

            return [
                'items' => $items,
                'summary' => number_format($weekCount) . ' new this week',
                'all_url' => $this->requestIndexUrl(),
            ];
        } catch (Throwable) {
            return [
                'items' => [],
                'summary' => 'Request activity unavailable',
                'all_url' => null,
            ];
        }
    }

    protected function recentSignups(): array
    {
        try {
            $weekCount = (clone $this->athleteQuery())
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            $items = $this->athleteQuery()
                ->with(['roles:id,name', 'billingInformation'])
                ->latest('created_at')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(function (User $user): array {
                    return [
                        'id' => (int) $user->getKey(),
                        'name' => $this->userName($user),
                        'initials' => $this->userInitials($user),
                        'sport' => $this->sportLabel((string) ($user->sport ?? '')),
                        'plan' => $this->planLabel($user),
                        'when' => $user->created_at?->diffForHumans() ?? '',
                        'url' => $this->userUrl($user),
                    ];
                })
                ->all();

            return [
                'items' => $items,
                'summary' => number_format($weekCount) . ' new this week',
                'all_url' => $this->userIndexUrl(),
            ];
        } catch (Throwable) {
            return [
                'items' => [],
                'summary' => 'Signup activity unavailable',
                'all_url' => null,
            ];
        }
    }

    protected function recentPayments(): array
    {
        if (! class_exists(PaymentTransaction::class) || ! Schema::hasTable('payment_transactions')) {
            return [
                'items' => [],
                'summary' => 'No payment data yet',
                'all_url' => null,
            ];
        }

        try {
            $weekQuery = $this->successfulPaymentQuery()
                ->where(function (Builder $query): void {
                    $query
                        ->where('paid_at', '>=', now()->subDays(7))
                        ->orWhere(function (Builder $fallback): void {
                            $fallback
                                ->whereNull('paid_at')
                                ->where('created_at', '>=', now()->subDays(7));
                        });
                });

            $weekCount = (clone $weekQuery)->count();
            $weekAmount = (int) (clone $weekQuery)->sum('amount_cents');

            $items = $this->successfulPaymentQuery()
                ->with('user:id,first_name,last_name,email')
                ->latest('paid_at')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(function (PaymentTransaction $payment): array {
                    $user = $payment->user;
                    $source = trim((string) $payment->source_name);

                    if ($source === '') {
                        $source = trim((string) $payment->plan_key) !== ''
                            ? Str::headline((string) $payment->plan_key)
                            : 'PLYRCARD payment';
                    }

                    $provider = trim((string) $payment->payment_provider);

                    return [
                        'id' => (int) $payment->getKey(),
                        'player' => $this->userName($user),
                        'initials' => $this->userInitials($user),
                        'amount' => $this->money((int) $payment->amount_cents),
                        'source' => $source,
                        'provider' => $provider !== '' ? Str::headline($provider) : 'Payment',
                        'when' => ($payment->paid_at ?: $payment->created_at)?->diffForHumans() ?? '',
                        'url' => $user ? $this->userUrl($user) : null,
                    ];
                })
                ->all();

            $summary = $weekCount > 0
                ? $this->money($weekAmount) . ' from ' . number_format($weekCount) . ' payment' . ($weekCount === 1 ? '' : 's') . ' this week'
                : 'No successful payments this week';

            return [
                'items' => $items,
                'summary' => $summary,
                'all_url' => null,
            ];
        } catch (Throwable) {
            return [
                'items' => [],
                'summary' => 'Payment activity unavailable',
                'all_url' => null,
            ];
        }
    }

    protected function successfulPaymentQuery(): Builder
    {
        return PaymentTransaction::query()
            ->where('amount_cents', '>', 0)
            ->whereIn('status', [
                'paid',
                'succeeded',
                'success',
                'completed',
                'complete',
            ]);
    }

    protected function athleteQuery(): Builder
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', $this->athleteRoleNames))
                    ->orWhere(function (Builder $legacy): void {
                        $legacy
                            ->whereDoesntHave('roles')
                            ->whereNotNull('sport')
                            ->where('sport', '<>', '');
                    });
            })
            ->whereDoesntHave(
                'roles',
                fn (Builder $roles): Builder => $roles->whereIn('name', $this->operatorRoleNames)
            );
    }

    protected function planLabel(User $user): string
    {
        $roles = $user->roles
            ->pluck('name')
            ->filter()
            ->map(fn ($role): string => trim((string) $role));

        $has = fn (string $name): bool => $roles->contains(
            fn (string $role): bool => strcasecmp($role, $name) === 0
        );

        if ($has('Amplify')) {
            return $has('My Journey') ? 'My Journey + Amplify' : 'Amplify';
        }

        if ($has('Jumpstart')) {
            return $has('My Journey') ? 'My Journey + Jumpstart' : 'Jumpstart';
        }

        if ($has('My Journey')) {
            return 'My Journey';
        }

        if ($has('Free')) {
            return 'Free PLYR';
        }

        return $roles->first() ?: 'Athlete';
    }

    protected function requestStatusTone(string $status): string
    {
        return match (Str::lower(trim($status))) {
            'completed' => 'success',
            'in_progress', 'reviewed' => 'info',
            'pending_information' => 'warning',
            'submitted' => 'orange',
            default => 'neutral',
        };
    }

    protected function sportLabel(string $sport): string
    {
        $sport = trim($sport);

        return $sport === ''
            ? 'Sport not set'
            : Str::of($sport)->replace('_', ' ')->headline()->toString();
    }

    protected function userName(?User $user): string
    {
        if (! $user) {
            return 'Unknown player';
        }

        $name = trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));

        return $name !== '' ? $name : ((string) ($user->email ?? 'Player'));
    }

    protected function userInitials(?User $user): string
    {
        if (! $user) {
            return 'P';
        }

        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));
        $initials = Str::upper(Str::substr($first, 0, 1) . Str::substr($last, 0, 1));

        return $initials !== '' ? $initials : 'P';
    }

    protected function money(int $cents): string
    {
        $amount = $cents / 100;

        return '$' . (floor($amount) === $amount
            ? number_format($amount, 0)
            : number_format($amount, 2));
    }

    protected function requestUrl(CreditServiceRequest $request): ?string
    {
        try {
            return CreditServiceRequestResource::getUrl('edit', ['record' => $request]);
        } catch (Throwable) {
            return null;
        }
    }

    protected function requestIndexUrl(): ?string
    {
        try {
            return CreditServiceRequestResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }

    protected function userUrl(User $user): ?string
    {
        try {
            return UserResource::getUrl('view', ['record' => $user]);
        } catch (Throwable) {
            return null;
        }
    }

    protected function userIndexUrl(): ?string
    {
        try {
            return UserResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }
}
