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

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
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

        if ($user->hasRole('My Journey')) {
            return 'my_journey';
        }

        if ($user->hasRole('Plyr')) {
            return 'plyr';
        }

        return 'free';
    }

public function getHeroEyebrow(): string
{
    return match ($this->getCurrentPlanKey()) {
        'my_journey' => 'Manage your subscription',
        'plyr' => 'Upgrade or downgrade',
        default => 'Unlock MyJourney',
    };
}

public function getHeroTitle(): string
{
    return match ($this->getCurrentPlanKey()) {
        'my_journey' => 'You Are On <span>My Journey</span>',
        'plyr' => 'Manage Your <span>Plan</span>',
        default => 'Choose Your <span>Path</span>',
    };
}

public function getHeroDescription(): string
{
    return match ($this->getCurrentPlanKey()) {
        'my_journey' => 'You are already on the highest plan. You can stay on My Journey or downgrade to a lower plan anytime.',
        'plyr' => 'You are currently on Plyr. Move up to My Journey or downgrade to Free depending on what fits you best.',
        default => 'From a simple online presence to a full recruiting-ready website — choose the plan that fits your journey.',
    };
}

public function getHeroBadgeLabel(): string
{
    return match ($this->getCurrentPlanKey()) {
        'my_journey' => 'Highest plan',
        'plyr' => 'Flexible options',
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
            'suffix' => '/month',
            'tagline' => 'Get your info online',
            'accent' => 'gray',
            'popular' => false,
            'button' => match ($currentPlan) {
                'free' => 'CURRENT PLAN',
                'plyr', 'my_journey' => 'DOWNGRADE TO FREE',
                default => 'START FREE',
            },
            'button_href' => match ($currentPlan) {
                'plyr', 'my_journey' => url('/checkout/free'),
                default => '#',
            },
            'button_style' => $currentPlan === 'free' ? 'disabled' : 'ghost',
            'icon' => 'user',
            'current' => $currentPlan === 'free',
            'features' => [
                ['text' => 'Basic profile site with athlete info', 'included' => true],
                ['text' => 'Basic templates', 'included' => true],
                ['text' => 'PLYR Card graphic not included', 'included' => false],
                ['text' => 'Personal domain', 'included' => false],
                ['text' => 'Professional email', 'included' => false],
                ['text' => 'Member status & perks', 'included' => false],
                ['text' => 'Graphics ordering', 'included' => false],
            ],
            'note' => 'Best for athletes who want a simple online presence without design assets.',
        ],

        [
            'key' => 'plyr',
            'name' => 'PLYR',
            'price' => '$10.99',
            'suffix' => '/month',
            'tagline' => 'Level up your branded web presence',
            'accent' => 'orange',
            'popular' => true,
            'button' => match ($currentPlan) {
                'plyr' => 'CURRENT PLAN',
                'my_journey' => 'DOWNGRADE TO PLYR',
                default => 'GET PLYR',
            },
            'button_href' => match ($currentPlan) {
                'free', 'my_journey' => url('https://systems.plyrcard.com/widget/survey/rY9lpkKJxgH844GoXuYf?plan=rookie-plus'),
                default => '#',
            },
            'button_style' => $currentPlan === 'plyr' ? 'disabled' : 'orange',
            'icon' => 'bolt',
            'current' => $currentPlan === 'plyr',
            'features' => [
                ['text' => 'Personal athlete website', 'included' => true],
                ['text' => 'Your own domain included', 'included' => true],
                ['text' => 'Professional email tied to domain', 'included' => true],
                ['text' => 'Member status & member-only perks', 'included' => true],
                ['text' => 'Order graphics at member rate: $35/graphic', 'included' => true],
                ['text' => 'Included in select PLYR Card promotions', 'included' => true],
                ['text' => 'Graphics not included in plan', 'included' => false],
            ],
            'note' => 'Best for athletes who want a professional look and access to graphics & promotions.',
        ],

        [
            'key' => 'my_journey',
            'name' => 'MY JOURNEY',
            'price' => '$45',
            'suffix' => '/month',
            'tagline' => 'Recruiting-ready, always on',
            'accent' => 'blue',
            'popular' => false,
            'button' => $currentPlan === 'my_journey'
                ? 'CURRENT PLAN'
                : 'START MY JOURNEY',
            'button_href' => $currentPlan === 'my_journey'
                ? '#'
                : url('https://systems.plyrcard.com/widget/survey/82L4a2pfvspbMYWeD0zo?plan=my-journey'),
            'button_style' => $currentPlan === 'my_journey' ? 'disabled' : 'blue',
            'icon' => 'crown',
            'current' => $currentPlan === 'my_journey',
            'features' => [
                ['text' => 'Athlete website on your own domain', 'included' => true],
                ['text' => 'Ongoing monthly service & updates', 'included' => true],
                ['text' => 'Maintained & upgraded web presence', 'included' => true],
                ['text' => 'À la carte graphics available', 'included' => true],
                ['text' => 'Graphics not included in plan', 'included' => false],
            ],
            'note' => 'Best for athletes who want a maintained website and will order graphics as needed.',
        ],
    ];
}

    public function getAddons(): array
    {
        return [
            [
                'title' => 'Upgraded Site Design',
                'desc' => 'A full redesign of your athlete website',
                'price' => '$150',
                'unit' => 'ONE-TIME',
                'icon' => 'sparkles',
            ],
            [
                'title' => 'Starting Graphics Bundle',
                'desc' => 'Starting graphic • Showcase graphic • Thank You graphic',
                'price' => '$70',
                'unit' => 'BUNDLE',
                'icon' => 'photo',
            ],
            [
                'title' => 'Individual Graphic',
                'desc' => 'Single custom athlete graphic',
                'price' => '$35',
                'unit' => 'EACH',
                'icon' => 'photo',
            ],
            [
                'title' => 'Domain',
                'desc' => 'Custom domain registration for your athlete site',
                'price' => '$45',
                'unit' => '/YEAR',
                'icon' => 'globe-alt',
            ],
        ];
    }

    public function shouldShowAddons(): bool
    {
        return $this->getCurrentPlanKey() === 'my_journey';
    }

    public function getFooterHeadline(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'my_journey' => 'You are fully unlocked.',
            'plyr' => 'You are one step away from the full experience.',
            default => 'No hidden fees. No surprises.',
        };
    }

    public function getFooterCopy(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'my_journey' => 'Your website experience is already on the highest plan. Add-ons can be ordered whenever you need them.',
            'plyr' => 'Upgrade to My Journey any time for the most premium managed experience.',
            default => 'Cancel anytime. Graphics and add-ons are ordered separately and billed once.',
        };
    }
}