<?php

namespace App\Filament\Pages;

use App\Models\BillingInformation;
use App\Models\PaymentTransaction;
use App\Services\BillingProfileService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class Billing extends Page
{
    protected string $view = 'filament.pages.billing';

    protected static ?string $slug = 'billing';
    protected static ?string $navigationLabel = 'Billing';
    protected static ?string $title = 'Billing';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|UnitEnum|null $navigationGroup = 'Account';
    protected static ?int $navigationSort = 95;

    public ?string $billing_name = null;
    public ?string $billing_email = null;
    public ?string $billing_phone = null;
    public ?string $billing_company = null;
    public ?string $billing_address_1 = null;
    public ?string $billing_address_2 = null;
    public ?string $billing_city = null;
    public ?string $billing_state = null;
    public ?string $billing_postal_code = null;
    public ?string $billing_country = null;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(BillingProfileService $billingService): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $this->fill($billingService->formData($user));
    }

    public function saveBilling(BillingProfileService $billingService): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $billingService->update($user, $this->billingInput());

        Notification::make()
            ->title('Billing information updated')
            ->body('Your billing contact and address have been saved.')
            ->success()
            ->send();
    }

    public function getBillingProperty(): BillingInformation
    {
        return app(BillingProfileService::class)->get(auth()->user());
    }

    public function getPaymentMethodUpdateUrlProperty(): ?string
    {
        return app(BillingProfileService::class)
            ->paymentMethodUpdateUrl(auth()->user(), $this->billing);
    }

    public function getLatestTransactionsProperty()
    {
        try {
            return PaymentTransaction::query()
                ->where('user_id', auth()->id())
                ->latest('paid_at')
                ->latest('id')
                ->limit(10)
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    protected function billingInput(): array
    {
        return [
            'billing_name' => $this->billing_name,
            'billing_email' => $this->billing_email,
            'billing_phone' => $this->billing_phone,
            'billing_company' => $this->billing_company,
            'billing_address_1' => $this->billing_address_1,
            'billing_address_2' => $this->billing_address_2,
            'billing_city' => $this->billing_city,
            'billing_state' => $this->billing_state,
            'billing_postal_code' => $this->billing_postal_code,
            'billing_country' => $this->billing_country,
        ];
    }
}
