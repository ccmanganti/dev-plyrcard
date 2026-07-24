<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MyJourney extends Page
{
    protected string $view = 'filament.pages.my-journey';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';
    protected static ?string $navigationLabel = 'Upgrade';
    protected static ?string $title = 'Unlock MyJourney';
    protected static ?string $slug = 'my-journey';
    protected static ?int $navigationSort = 6;
    protected static string|UnitEnum|null $navigationGroup = null;

    protected static function isSuperadminNavigationUser(): bool
    {
        $user = auth()->user();

        return $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'hasRole')) {
            return 'NEW';
        }

        if ($user->hasRole('My Journey')) {
            return 'ACTIVE';
        }

        if ($user->hasRole('Plyr')) {
            return 'UPGRADE';
        }

        return 'NEW';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'hasRole')) {
            return 'danger';
        }

        if ($user->hasRole('My Journey')) {
            return 'success';
        }

        if ($user->hasRole('Plyr')) {
            return 'warning';
        }

        return 'danger';
    }

    public function getCurrentPlanKey(): string
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'hasRole')) {
            return 'free';
        }

        if ($user->hasRole('Amplify') || $user->hasRole('amplify')) {
            return 'amplify';
        }

        if ($user->hasRole('My Journey')) {
            return 'my_journey';
        }

        return 'free';
    }

    public function getHeroEyebrow(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'amplify' => 'Done-for-you recruiting package',
            'my_journey' => 'Manage your subscription',
            default => 'Unlock My Journey',
        };
    }

    public function getHeroTitle(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'amplify' => 'You Are On <span>Amplify</span>',
            'my_journey' => 'You Are On <span>My Journey</span>',
            default => 'Choose Your <span>Plan</span>',
        };
    }

    public function getHeroDescription(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'amplify' => 'You are on the complete done-for-you recruiting package with custom graphics, highlight reels, managed coach outreach, and hands-on support.',
            'my_journey' => 'You are on My Journey. Upgrade to Amplify anytime for monthly done-for-you content, outreach, and support.',
            default => 'Start free and upgrade when you are ready. My Journey unlocks your recruiting HQ, while Amplify adds done-for-you production and outreach.',
        };
    }

    public function getHeroBadgeLabel(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'amplify' => 'Done for you',
            'my_journey' => 'Most popular',
            default => 'Built for athletes',
        };
    }

    public function getPlans(): array
    {
        $currentPlan = $this->getCurrentPlanKey();

        return [
            [
                'key' => 'free',
                'name' => 'FREE',
                'price' => '$0',
                'suffix' => '/mo',
                // 'setup' => 'No credit card required',
                'tagline' => 'A simple PLYRSite with your quick info. Get started in minutes.',
                'accent' => 'gray',
                'popular' => false,
                'badge' => null,
                'button' => $currentPlan === 'free' ? 'CURRENT PLAN' : 'GO TO FREE',
                'button_href' => $currentPlan === 'free' ? '#' : url('/registration?utm_plan=free'),
                'button_style' => $currentPlan === 'free' ? 'disabled' : 'ghost',
                'icon' => 'user',
                'current' => $currentPlan === 'free',
                'features' => [
                    ['text' => 'Simple PLYRSite page', 'included' => true],
                    ['text' => 'Quick athlete info', 'included' => true],
                    ['text' => 'Bio & basic stats', 'included' => true],
                    ['text' => 'Email support', 'included' => true],
                    ['text' => 'Personalized domain', 'included' => false],
                    ['text' => 'Coach database access', 'included' => false],
                    ['text' => 'Coach engagement tracking', 'included' => false],
                ],
                'note' => 'Best for Athelete getting started and building presence in minutes.',
            ],

            [
                'key' => 'my_journey',
                'name' => 'MY JOURNEY',
                'price' => '$49',
                'suffix' => '/mo',
                // 'setup' => 'Monthly subscription · Cancel anytime',
                'tagline' => 'Your own recruiting HQ — domain, email, tracking, templates, and the coach database.',
                'accent' => 'orange',
                'popular' => true,
                'badge' => 'Most Popular',
                'button' => $currentPlan === 'my_journey' ? 'CURRENT PLAN' : 'GET MY JOURNEY',
                'button_href' => $currentPlan === 'my_journey' ? '#' : url('/registration?utm_plan=my-journey'),
                'button_style' => $currentPlan === 'my_journey' ? 'disabled' : 'orange',
                'icon' => 'bolt',
                'current' => $currentPlan === 'my_journey',
                'features' => [
                    ['text' => 'Everything in Free', 'included' => true],
                    ['text' => 'Your own personalized domain', 'included' => true],
                    ['text' => 'Your own email — sends from you, not a third party', 'included' => true],
                    ['text' => 'Coach engagement tracking tool', 'included' => true],
                    ['text' => 'Outreach templates', 'included' => true],
                    ['text' => 'Coach database access — weekly verifications', 'included' => true],
                    ['text' => '1-on-1 onboarding', 'included' => true],
                ],
                'note' => "Best for athletes ready to run their own outreach and track what's working.",
            ],

            [
                'key' => 'amplify',
                'name' => 'AMPLIFY',
                'price' => '$550',
                'suffix' => 'One time',
                // 'setup' => '$500 one-time setup fee · Covers graphics, production, and done-for-you setup',
                'tagline' => 'Paired with My Journey ($49/mo). Perfect right before your seasons',
                'accent' => 'gold',
                'popular' => true,
                'badge' => 'Done For You',
                'button' => $currentPlan === 'amplify' ? 'CURRENT PLAN' : 'AMPLIFY MY RECRUITING',
                'button_href' => $currentPlan === 'amplify' ? '#' : url('/registration?utm_plan=amplify'),
                'button_style' => $currentPlan === 'amplify' ? 'disabled' : 'gold',
                'icon' => 'crown',
                'current' => $currentPlan === 'amplify',
                'features' => [
                    ['text' => 'Everything in My Journey', 'included' => true],
                    ['text' => '4 Highlight Reels', 'included' => true],
                    ['text' => '4 Custom Graphics', 'included' => true],
                    ['text' => '4 Managed Coach Outreach sends', 'included' => true],
                    ['text' => '8 Hours of Support', 'included' => true],
                    ['text' => 'Full onboarding', 'included' => true],
                ],
                'note' => 'Best for atheletes heading into a new season or showcases who want the full push from our team.',
            ],
        ];
    }

    public function getAddons(): array
    {
        return [];
    }

    public function shouldShowAddons(): bool
    {
        return false;
    }

    public function getFooterHeadline(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'amplify' => 'Your recruiting package is fully amplified.',
            'my_journey' => 'You are one step away from done-for-you support.',
            default => 'No credit card required to start.',
        };
    }

    public function getFooterCopy(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'amplify' => 'Amplify includes My Journey plus monthly highlight reels, custom graphics, managed outreach, and hands-on support.',
            'my_journey' => 'Upgrade to Amplify anytime when you want custom graphics, highlight reels, and managed outreach handled for you.',
            default => 'Free gives you the basics. Upgrade to My Journey or Amplify whenever you are ready to unlock more recruiting tools.',
        };
    }

}