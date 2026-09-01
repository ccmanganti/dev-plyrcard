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

        if ($user->hasRole('My Journey') || $user->hasRole('Amplify')) {
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

        if ($user->hasRole('My Journey') || $user->hasRole('Amplify')) {
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

        // Amplify is a one-time service entitlement, not a subscription tier.
        // The recurring plan remains My Journey while Amplify work is active.
        if (
            $user->hasRole('My Journey')
            || $user->hasRole('my journey')
            || $user->hasRole('Amplify')
            || $user->hasRole('amplify')
        ) {
            return 'my_journey';
        }

        return 'free';
    }

    public function hasAmplifyAccess(): bool
    {
        $user = Auth::user();

        return (bool) ($user && method_exists($user, 'hasRole')
            && ($user->hasRole('Amplify') || $user->hasRole('amplify')));
    }

    public function getHeroEyebrow(): string
    {
        return $this->getCurrentPlanKey() === 'my_journey'
            ? 'Manage your subscription'
            : 'Choose your plan';
    }

    public function getHeroTitle(): string
    {
        return $this->getCurrentPlanKey() === 'my_journey'
            ? 'You Are On <span>My Journey</span>'
            : 'Choose Your <span>Plan</span>';
    }

    public function getHeroDescription(): string
    {
        if ($this->getCurrentPlanKey() === 'my_journey') {
            return $this->hasAmplifyAccess()
                ? 'My Journey is active. Your Amplify one-time setup package is also active while our team completes your graphics, highlights, outreach setup, and onboarding.'
                : 'My Journey is active. Add Amplify anytime for the one-time done-for-you setup package.';
        }

        return 'Start free or unlock My Journey for your recruiting HQ, personalized domain, coach database, outreach tools, and tracking.';
    }

    public function getHeroBadgeLabel(): string
    {
        return $this->getCurrentPlanKey() === 'my_journey' ? 'Active plan' : 'Built for athletes';
    }

    public function getPlans(): array
    {
        $currentPlan = $this->getCurrentPlanKey();
        $amplifyActive = $this->hasAmplifyAccess();
        $hasMyJourney = $currentPlan === 'my_journey';

        return [
            [
                'key' => 'free',
                'name' => 'FREE',
                'price' => '$0',
                'suffix' => '/mo',
                'setup' => 'No credit card required',
                'tagline' => 'A simple PLYRSite with your quick info. Get started in minutes.',
                'accent' => 'gray',
                'popular' => false,
                'badge' => null,
                'button' => $currentPlan === 'free' ? 'CURRENT PLAN' : 'DOWNGRADE TO FREE',
                'button_href' => '#',
                'requests_free_downgrade' => $currentPlan !== 'free',
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
                'note' => 'Best for athletes who want a simple online presence before upgrading.',
            ],
            [
                'key' => 'my_journey',
                'name' => 'MY JOURNEY',
                'price' => '$49',
                'suffix' => '/mo',
                'setup' => 'Monthly subscription · Cancel anytime',
                'tagline' => 'Your own recruiting HQ — domain, email, tracking, templates, and the coach database.',
                'accent' => 'orange',
                'popular' => true,
                'badge' => 'Most Popular',
                'button' => $hasMyJourney ? 'CURRENT PLAN' : 'GET MY JOURNEY',
                'button_href' => '#',
                'opens_my_journey_checkout' => ! $hasMyJourney,
                'button_style' => $hasMyJourney ? 'disabled' : 'orange',
                'icon' => 'bolt',
                'current' => $hasMyJourney,
                'features' => [
                    ['text' => 'Everything in Free', 'included' => true],
                    ['text' => 'Your own personalized domain', 'included' => true],
                    ['text' => 'Your own email — sends from you, not a third party', 'included' => true],
                    ['text' => 'Coach engagement tracking tool', 'included' => true],
                    ['text' => 'Outreach templates', 'included' => true],
                    ['text' => 'Coach database access — weekly verifications', 'included' => true],
                    ['text' => '1-on-1 onboarding', 'included' => true],
                ],
                'note' => 'Your recurring recruiting workspace and subscription plan.',
            ],
            [
                'key' => 'amplify',
                'name' => 'AMPLIFY',
                'price' => '$500',
                'suffix' => 'one time',
                'setup' => 'One-time done-for-you setup package · My Journey membership required',
                'tagline' => 'A one-time production and recruiting setup package layered on top of My Journey.',
                'accent' => 'gold',
                'popular' => true,
                'badge' => 'Done For You',
                'button' => $hasMyJourney ? 'AMPLIFY MY RECRUITING' : 'MY JOURNEY REQUIRED',
                'button_href' => '#',
                'opens_my_journey_checkout' => ! $hasMyJourney,
                'opens_amplify_checkout' => $hasMyJourney && ! $amplifyActive,
                'button_style' => $hasMyJourney ? 'gold' : 'ghost',
                'button_disabled' => $amplifyActive,
                'icon' => 'crown',
                'current' => false,
                'active_addon' => $amplifyActive,
                'features' => [
                    ['text' => '4 Highlight Reels', 'included' => true],
                    ['text' => '4 Custom Graphics', 'included' => true],
                    ['text' => '4 Managed Coach Outreach sends', 'included' => true],
                    ['text' => '8 Hours of Support', 'included' => true],
                    ['text' => 'Full onboarding and account setup', 'included' => true],
                ],
                'note' => 'Amplify does not replace your plan. After our setup work is complete, your plan remains My Journey.',
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