<?php
namespace App\Services;
use App\Models\BillingInformation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
class BillingProfileService
{
    public function __construct(protected StripeBillingService $stripe) {}
    public function rules(): array
    {
        return [
            'billing_name'=>['required','string','max:255'],'billing_email'=>['required','email','max:255'],'billing_phone'=>['nullable','string','max:255'],
            'billing_company'=>['nullable','string','max:255'],'billing_address_1'=>['required','string','max:255'],'billing_address_2'=>['nullable','string','max:255'],
            'billing_city'=>['required','string','max:255'],'billing_state'=>['required','string','max:255'],'billing_postal_code'=>['required','string','max:40'],'billing_country'=>['required','string','max:255'],
        ];
    }
    public function requiredProfileFields(): array
    {
        return ['billing_name','billing_email','billing_address_1','billing_city','billing_state','billing_postal_code','billing_country'];
    }
    public function missingRequiredFields(BillingInformation $billing): array
    {
        return collect($this->requiredProfileFields())->filter(fn (string $field): bool => blank($billing->{$field}))->values()->all();
    }
    public function isComplete(BillingInformation $billing): bool { return $this->missingRequiredFields($billing) === []; }
    public function requirementPayload(User $user, ?BillingInformation $billing = null): array
    {
        $billing ??= $this->get($user);
        return [
            'profile_complete'=>$this->isComplete($billing),
            'missing_fields'=>$this->missingRequiredFields($billing),
            'billing'=>Arr::only($billing->toArray(), $this->billingFields()),
        ];
    }
    public function update(User $user, array $input): BillingInformation
    {
        $data = Validator::make($input, $this->rules())->validate();
        $billing = BillingInformation::query()->updateOrCreate(['user_id'=>$user->getKey()], $data);
        if (filled($billing->stripe_customer_id)) {
            try { $this->stripe->updateCustomerProfile($user, $billing); } catch (\Throwable $e) { report($e); }
        }
        return $billing->fresh();
    }
    public function get(User $user): BillingInformation
    {
        return BillingInformation::query()->firstOrCreate(['user_id'=>$user->getKey()], [
            'billing_name'=>trim((string)(($user->first_name ?? '').' '.($user->last_name ?? ''))),'billing_email'=>$user->email,'billing_phone'=>$user->phone,
            'billing_address_1'=>$user->street,'billing_city'=>$user->city,'billing_state'=>$user->state,'billing_country'=>$user->country ?: 'US','currency'=>'USD',
        ]);
    }
    public function formData(User $user): array { return Arr::only($this->get($user)->toArray(), $this->billingFields()); }
    public function refreshPaymentIdentity(User $user): BillingInformation { return $this->stripe->refreshBilling($user); }
    public function paymentMethodUpdateUrl(User $user, ?BillingInformation $billing = null): ?string { return null; }
    protected function billingFields(): array
    {
        return ['billing_name','billing_email','billing_phone','billing_company','billing_address_1','billing_address_2','billing_city','billing_state','billing_postal_code','billing_country'];
    }
}