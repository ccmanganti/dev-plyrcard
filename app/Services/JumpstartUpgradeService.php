<?php
namespace App\Services;
use App\Models\User;
class JumpstartUpgradeService
{
    public function __construct(protected StripeBillingService $stripe) {}
    public function start(User $user): array
    {
        try {
            return $this->stripe->startUpgrade($user, 'jumpstart');
        } catch (\Throwable $exception) {
            report($exception);
            return ['success' => false, 'completed' => false, 'message' => $exception->getMessage()];
        }
    }
    public function status(User $user): array
    {
        return $this->stripe->upgradeStatus($user, 'jumpstart');
    }
}