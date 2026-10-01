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
            && $user->hasRole('Superadmin');
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
        // Only the My Journey role represents the recurring subscription.
        // Jumpstart and Amplify are one-time credit purchases and must not keep
        // the account on a paid subscription after My Journey is canceled.
        if ($user->hasRole('My Journey') || $user->hasRole('my journey')) {
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
    public function hasJumpstartAccess(): bool
    {
        $user = Auth::user();
        return (bool) ($user && method_exists($user, 'hasRole')
            && ($user->hasRole('Jumpstart') || $user->hasRole('jumpstart')));
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
            if ($this->hasAmplifyAccess()) {
                return 'My Journey is active. Your Amplify one-time service extension is also active while our team completes your graphics, highlights, outreach, and onboarding.';
            }
            if ($this->hasJumpstartAccess()) {
                return 'My Journey is active. Your Jumpstart one-time service extension is also active. You can still add Amplify later for the full done-for-you recruiting push.';
            }
            return 'My Journey is active. Add Jumpstart for a focused recruiting push or Amplify for the full done-for-you service.';
        }
        return 'Start free or unlock My Journey for your recruiting HQ, personalized domain, coach database, outreach tools, and tracking.';
    }
    public function getCreditBalance(): int
    {
        return (int) (Auth::user()?->points_available ?? 0);
    }
    public function getHeroBadgeLabel(): string
    {
        return $this->getCurrentPlanKey() === 'my_journey' ? 'Active plan' : 'Built for athletes';
    }
    public function getPlans(): array
    {
        $currentPlan = $this->getCurrentPlanKey();
        $amplifyActive = $this->hasAmplifyAccess();
        $jumpstartActive = $this->hasJumpstartAccess();
        $journeyCents = (int) config('plyrcard-registration.plans.my-journey.recurring_amount_cents', 4900);
        $jumpstartCents = (int) config('plyrcard-registration.plans.jumpstart.setup_fee_cents', 14900);
        $amplifyCents = (int) config('plyrcard-registration.plans.amplify.setup_fee_cents', 50000);
        $hasMyJourney = $currentPlan === 'my_journey';
        $jumpstartDue = $jumpstartCents + ($hasMyJourney ? 0 : $journeyCents);
        $amplifyDue = $amplifyCents + ($hasMyJourney ? 0 : $journeyCents);
        $money = static fn (int $cents): string => '$' . (floor($cents / 100) === ($cents / 100) ? number_format($cents / 100, 0) : number_format($cents / 100, 2));
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
                'price' => $money($journeyCents),
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
                'key' => 'jumpstart',
                'name' => 'JUMPSTART',
                'price' => $money($jumpstartCents),
                'suffix' => 'one time',
                'setup' => $hasMyJourney
                    ? $money($jumpstartCents) . ' Jumpstart service · My Journey stays active'
                    : $money($jumpstartCents) . ' Jumpstart + ' . $money($journeyCents) . ' first My Journey month',
                'tagline' => '100 pooled PLYRCARD credits to spend on the recruiting work you need most.',
                'accent' => 'blue',
                'popular' => false,
                'badge' => null,
                'button' => $jumpstartActive ? 'BUY 100 MORE CREDITS' : 'GET JUMPSTART',
                'button_href' => '#',
                'opens_jumpstart_checkout' => true,
                'button_style' => 'blue',
                'button_disabled' => false,
                'icon' => 'sparkles',
                'current' => false,
                'active_addon' => $jumpstartActive,
                'features' => [
                    ['text' => '100 pooled PLYRCARD credits', 'included' => true],
                    ['text' => 'Spend on graphics, reels, outreach, production hours, and supported add-ons', 'included' => true],
                    ['text' => 'Credits do not expire', 'included' => true],
                    ['text' => 'My Journey membership included / required', 'included' => true],
                ],
                'note' => $hasMyJourney
                    ? 'Jumpstart is a one-time service extension. Your existing My Journey subscription remains your base plan.'
                    : 'Jumpstart includes My Journey. Today is ' . $money($jumpstartDue) . '; My Journey then continues at ' . $money($journeyCents) . '/mo.',
            ],
            [
                'key' => 'amplify',
                'name' => 'AMPLIFY',
                'price' => $money($amplifyCents),
                'suffix' => 'one time',
                'setup' => $hasMyJourney
                    ? $money($amplifyCents) . ' Amplify service · My Journey stays active'
                    : $money($amplifyCents) . ' Amplify + ' . $money($journeyCents) . ' first My Journey month',
                'tagline' => '600 pooled PLYRCARD credits for a larger done-for-you recruiting push.',
                'accent' => 'gold',
                'popular' => true,
                'badge' => 'Done For You',
                'button' => $amplifyActive ? 'BUY 600 MORE CREDITS' : ($hasMyJourney ? 'BUY AMPLIFY CREDITS' : 'GET AMPLIFY'),
                'button_href' => '#',
                'opens_my_journey_checkout' => false,
                'opens_amplify_checkout' => true,
                'button_style' => 'gold',
                'button_disabled' => false,
                'icon' => 'crown',
                'current' => false,
                'active_addon' => $amplifyActive,
                'features' => [
                    ['text' => '600 pooled PLYRCARD credits', 'included' => true],
                    ['text' => 'Use credits across reels, graphics, outreach, production hours, and supported add-ons', 'included' => true],
                    ['text' => 'Credits do not expire', 'included' => true],
                    ['text' => 'Full onboarding and account setup', 'included' => true],
                ],
                'note' => 'Amplify is a one-time 600-credit purchase. It does not replace My Journey, and unused credits do not expire.',
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
            'amplify' => 'My Journey remains your subscription. Amplify is the one-time done-for-you service extension for highlights, graphics, managed outreach, and hands-on support.',
            'my_journey' => 'Keep My Journey as your recruiting workspace, then add Jumpstart or Amplify whenever you want done-for-you recruiting support.',
            default => 'Free gives you the basics. Upgrade to My Journey, add Jumpstart, or choose Amplify whenever you are ready for more recruiting support.',
        };
    }
}