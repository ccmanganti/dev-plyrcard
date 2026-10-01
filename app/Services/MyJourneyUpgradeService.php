<?php
namespace App\Services;
use App\Models\User;
class MyJourneyUpgradeService
{
    public function __construct(protected StripeBillingService $stripe) {}
    public function start(User $user): array
    {
        try {
            $result = $this->stripe->startUpgrade($user, 'my-journey');
            return array_merge(['error' => false], $result);
        } catch (\Throwable $exception) {
            report($exception);
            return ['error' => true, 'completed' => false, 'message' => $exception->getMessage()];
        }
    }
    public function status(User $user): array
    {
        return $this->stripe->upgradeStatus($user, 'my-journey');
    }
}