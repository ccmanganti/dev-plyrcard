<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ProfilePlanInfo
{
    public function __construct(
        protected User $user,
    ) {}

    public static function for(?User $user): ?self
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('roles', 'nationalTeam', 'websites');

        return new self($user);
    }

    public function user(): User
    {
        return $this->user;
    }

    public function website()
    {
        return $this->user->websites()->first();
    }

    public function isFreeTrialActive(): bool
    {
        if (! method_exists($this->user, 'hasRole')) {
            return false;
        }

        if (! $this->user->hasRole('Free')) {
            return false;
        }

        if (! $this->user->created_at) {
            return false;
        }

        return $this->user->created_at->copy()->addDays(7)->isFuture();
    }

    public function getFreeTrialEndsAt(): ?Carbon
    {
        if (! $this->user->created_at) {
            return null;
        }

        return $this->user->created_at->copy()->addDays(7);
    }

    public function getFreeTrialDaysLeft(): int
    {
        if (! $this->isFreeTrialActive()) {
            return 0;
        }

        $endsAt = $this->getFreeTrialEndsAt();

        if (! $endsAt) {
            return 0;
        }

        return max(1, now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false));
    }

    public function hasPremiumAccess(): bool
    {
        return $this->isFreeTrialActive()
            || in_array($this->getCurrentPlanKey(), ['plyr', 'my_journey'], true);
    }

    public function getCurrentPlanKey(): string
    {
        if (! method_exists($this->user, 'hasRole')) {
            return 'free';
        }

        if ($this->user->hasRole('My Journey')) {
            return 'my_journey';
        }

        if ($this->user->hasRole('Plyr')) {
            return 'plyr';
        }

        return 'free';
    }

    public function getPlanName(): string
    {
        if ($this->isFreeTrialActive()) {
            return 'FREE TRIAL';
        }

        return match ($this->getCurrentPlanKey()) {
            'my_journey' => 'MY JOURNEY',
            'plyr' => 'PLYR',
            default => 'FREE',
        };
    }

    public function getPlanHeadline(): string
    {
        if ($this->isFreeTrialActive()) {
            return "YOU'RE ON FREE TRIAL";
        }

        return match ($this->getCurrentPlanKey()) {
            'my_journey' => "YOU'RE ON MY JOURNEY",
            'plyr' => "YOU'RE ON PLYR",
            default => "YOU'RE ON FREE",
        };
    }

    public function getPlanDescription(): string
    {
        if ($this->isFreeTrialActive()) {
            return 'Your free trial is active. You currently have access to all tabs and premium features during the 7-day trial window.';
        }

        return match ($this->getCurrentPlanKey()) {
            'my_journey' => 'Everything is unlocked on My Journey. Your PLYRCard is fully equipped for the next level.',
            'plyr' => 'Social links and YouTube features are unlocked. Move to My Journey for the most premium experience.',
            default => 'Upgrade to unlock Social links, YouTube Highlights, Featured Videos, and more premium tools.',
        };
    }

    public function canUpgradePlan(): bool
    {
        return $this->getCurrentPlanKey() !== 'my_journey';
    }

    public function getUpgradeButtonLabel(): string
    {
        if ($this->isFreeTrialActive()) {
            return 'Choose a Plan';
        }

        return match ($this->getCurrentPlanKey()) {
            'plyr' => 'Go to My Journey',
            default => 'Upgrade Now',
        };
    }

    public function getUpgradeUrl(): string
    {
        return url('/admin/my-journey');
    }

    public function shouldShowBookDemoButton(): bool
    {
        return $this->getCurrentPlanKey() !== 'my_journey';
    }

    public function getBookDemoUrl(): string
    {
        return url('/demo');
    }

    public function getPlanTheme(): string
    {
        if ($this->isFreeTrialActive()) {
            return 'warning';
        }

        return $this->getCurrentPlanKey() === 'my_journey'
            ? 'success'
            : 'warning';
    }

    public function getProfileImageUrl(): ?string
    {
        $image = $this->user->player_image ?: $this->user->plyrcard_image;

        if (blank($image)) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return Storage::url($image);
    }
}