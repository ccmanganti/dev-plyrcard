@php
    $settingsBillingService = app(\App\Services\BillingProfileService::class);
    $settingsStripe = app(\App\Services\StripeBillingService::class);
    try {
        $settingsSummary = $settingsStripe->billingSummary(auth()->user());
        $settingsBilling = $settingsSummary['billing'];
    } catch (\Throwable $e) {
        $settingsBilling = $settingsBillingService->get(auth()->user());
        $settingsSummary = [
            'cancel_at_period_end' => false,
            'current_period_end' => null,
            'history' => [],
            'points_available' => (int) (auth()->user()?->points_available ?? 0),
        ];
    }
    $settingsStripeConnected = filled($settingsBilling->stripe_customer_id);
    $settingsCancelAtPeriodEnd = (bool) ($settingsSummary['cancel_at_period_end'] ?? false);
    $settingsBrand = strtoupper((string) ($settingsBilling->payment_brand ?: 'CARD'));
    $settingsHistory = collect($settingsSummary['history'] ?? []);
    $settingsPeriodEnd = filled($settingsSummary['current_period_end'] ?? null) ? \Illuminate\Support\Carbon::parse($settingsSummary['current_period_end']) : null;
@endphp
<div class="rc-settings-card-v72" id="billing-payments" data-stripe-billing-settings
     data-summary-url="{{ route('billing.stripe.summary') }}"
     data-card-url="{{ route('billing.stripe.payment-method.setup') }}"
     data-card-complete-url="{{ route('billing.stripe.payment-method.complete') }}"
     data-cancel-url="{{ route('billing.cancel-request') }}"
     data-resume-url="{{ route('billing.stripe.resume') }}">
    <div class="rc-settings-head-v72">
        <div class="rc-settings-icon-v72">💳</div>
        <div>
            <h2 style="margin:0;">Billing &amp; Payments</h2>
            <p style="margin:.2rem 0 0;color:var(--rc-muted);">Manage your My Journey subscription, payment method, billing details, credit balance, and payment history.</p>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:.65rem;margin-bottom:1rem;">
        <div class="rc-card is-flat"><div class="rc-subtle">Stripe</div><strong>{{ $settingsStripeConnected ? 'Connected' : 'Not connected yet' }}</strong></div>
        <div class="rc-card is-flat"><div class="rc-subtle">Plan</div><strong>{{ str($settingsBilling->plan_key ?: 'free')->replace('-', ' ')->title() }}</strong></div>
        <div class="rc-card is-flat"><div class="rc-subtle">Subscription</div><strong>{{ $settingsCancelAtPeriodEnd ? 'Cancels at period end' : str($settingsBilling->subscription_status ?: 'not available')->replace('_', ' ')->title() }}</strong></div>
        <div class="rc-card is-flat"><div class="rc-subtle">Payment</div><strong>{{ str($settingsBilling->payment_status ?: 'not available')->replace('_', ' ')->title() }}</strong></div>
        <div class="rc-card is-flat"><div class="rc-subtle">Credits</div><strong>{{ number_format((int) ($settingsSummary['points_available'] ?? 0)) }}</strong><div class="rc-subtle">No expiration</div></div>
    </div>
    @if($settingsCancelAtPeriodEnd && $settingsPeriodEnd)
        <div style="margin:0 0 1rem;padding:.75rem .85rem;border:1px solid rgba(245,158,11,.3);border-radius:.75rem;background:rgba(245,158,11,.08);color:#92400e;font-size:.8rem;font-weight:650;">
            My Journey is scheduled to cancel on {{ $settingsPeriodEnd->format('M j, Y') }}. Your purchased credits will remain available and will not expire.
        </div>
    @endif
    <div class="rc-row" style="align-items:flex-start;">
        <div>
            <div class="rc-row-title">Payment Method</div>
            @if($settingsBilling->card_last_four)
                <p class="rc-subtle" style="margin:.25rem 0 0;">{{ $settingsBrand }} ending in {{ $settingsBilling->card_last_four }}{{ $settingsBilling->card_expiration ? ' · Expires '.$settingsBilling->card_expiration : '' }}</p>
            @else
                <p class="rc-subtle" style="margin:.25rem 0 0;">No Stripe payment method is saved yet.</p>
            @endif
        </div>
        <button class="rc-btn rc-btn-primary" type="button" data-stripe-card-update>{{ $settingsBilling->card_last_four ? 'Update Card' : 'Add Payment Method' }}</button>
    </div>
    <form method="POST" action="{{ route('locker-room.billing.update') }}" style="margin-top:1rem;">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;">
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Billing Name<input class="rc-input" style="width:100%;" name="billing_name" value="{{ old('billing_name', $settingsBilling->billing_name) }}" required></label>
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Billing Email<input class="rc-input" style="width:100%;" type="email" name="billing_email" value="{{ old('billing_email', $settingsBilling->billing_email) }}" required></label>
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Phone<input class="rc-input" style="width:100%;" name="billing_phone" value="{{ old('billing_phone', $settingsBilling->billing_phone) }}"></label>
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Company / Organization<input class="rc-input" style="width:100%;" name="billing_company" value="{{ old('billing_company', $settingsBilling->billing_company) }}"></label>
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;grid-column:1/-1;">Address Line 1<input class="rc-input" style="width:100%;" name="billing_address_1" value="{{ old('billing_address_1', $settingsBilling->billing_address_1) }}" required></label>
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;grid-column:1/-1;">Address Line 2<input class="rc-input" style="width:100%;" name="billing_address_2" value="{{ old('billing_address_2', $settingsBilling->billing_address_2) }}"></label>
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">City<input class="rc-input" style="width:100%;" name="billing_city" value="{{ old('billing_city', $settingsBilling->billing_city) }}" required></label>
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">State / Province<input class="rc-input" style="width:100%;" name="billing_state" value="{{ old('billing_state', $settingsBilling->billing_state) }}" required></label>
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Postal Code<input class="rc-input" style="width:100%;" name="billing_postal_code" value="{{ old('billing_postal_code', $settingsBilling->billing_postal_code) }}" required></label>
            <label style="display:grid;gap:.32rem;font-size:.76rem;font-weight:700;">Country<input class="rc-input" style="width:100%;" name="billing_country" value="{{ old('billing_country', $settingsBilling->billing_country ?: 'US') }}" required></label>
        </div>
        <div style="display:flex;align-items:center;gap:.65rem;flex-wrap:wrap;margin-top:1rem;">
            <button class="rc-btn rc-btn-primary" type="submit" data-rc-billing-save>Save Billing Information</button>
            @if(in_array(strtolower((string) $settingsBilling->subscription_status), ['active','trialing','trial','past_due'], true))
                @if($settingsCancelAtPeriodEnd)
                    <button class="rc-btn" type="button" data-stripe-resume-plan>Keep My Journey</button>
                @else
                    <button class="rc-btn" style="border-color:#fecaca;color:#b42318;background:#fff7f7;" type="button" data-stripe-cancel-plan>Cancel at Period End</button>
                @endif
            @endif
        </div>
    </form>
    <div style="margin-top:1.2rem;border-top:1px solid var(--rc-border);padding-top:1rem;">
        <div class="rc-row-title">Billing History</div>
        @if($settingsHistory->isEmpty())
            <p class="rc-subtle" style="margin:.35rem 0 0;">No Stripe payments have been recorded yet.</p>
        @else
            <div style="display:grid;gap:.45rem;margin-top:.65rem;">
                @foreach($settingsHistory as $row)
                    <div class="rc-row" style="padding:.65rem .75rem;">
                        <div><strong style="font-size:.8rem;">{{ $row['source_name'] ?: 'Stripe payment' }}</strong><div class="rc-subtle">{{ filled($row['paid_at']) ? \Illuminate\Support\Carbon::parse($row['paid_at'])->format('M j, Y g:i A') : 'Pending date' }} · {{ str($row['status'] ?: 'unknown')->replace('_',' ')->title() }}</div></div>
                        <strong>{{ strtoupper($row['currency'] ?: 'USD') }} {{ number_format(((int) $row['amount_cents']) / 100, 2) }}</strong>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <div data-stripe-card-panel hidden style="margin-top:1rem;padding:1rem;border:1px solid var(--rc-border);border-radius:.85rem;background:var(--rc-surface);">
        <div class="rc-row-title">Secure Card Update</div>
        <p class="rc-subtle">Card details go directly to Stripe and are never stored by PLYRCARD.</p>
        <div id="rc-stripe-card-element" style="margin-top:.75rem;"></div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:.8rem;"><button class="rc-btn" type="button" data-stripe-card-close>Cancel</button><button class="rc-btn rc-btn-primary" type="button" data-stripe-card-save>Save Card</button></div>
        <div class="rc-subtle" data-stripe-card-status style="margin-top:.5rem;"></div>
    </div>
</div>
<script src="https://js.stripe.com/v3/"></script>
<script data-navigate-once>
(() => {
    if (window.__plyrStripeBillingSettingsBound) return;
    window.__plyrStripeBillingSettingsBound = true;
    let stripe = null, elements = null, activeRoot = null, setupIntentId = null;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const req = async (url, method='POST', body={}) => {
        const response = await fetch(url, {
            method,
            credentials:'same-origin',
            headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},
            body:method==='GET'?undefined:JSON.stringify(body),
        });
        const data = await response.json().catch(()=>({}));
        if(!response.ok||data.success===false) throw new Error(data.message||'Request failed.');
        return data;
    };
    const rootFor = target => target?.closest?.('[data-stripe-billing-settings]') || document.querySelector('[data-stripe-billing-settings]');
    const cardStatus = root => root?.querySelector('[data-stripe-card-status]');
    const closeCardPanel = root => {
        const panel=root?.querySelector('[data-stripe-card-panel]');
        if(panel) panel.hidden=true;
        const mount=root?.querySelector('#rc-stripe-card-element');
        if(mount) mount.innerHTML='';
        stripe=null; elements=null; activeRoot=null; setupIntentId=null;
    };
    document.addEventListener('click', async event => {
        const updateButton=event.target.closest('[data-stripe-card-update]');
        if(updateButton){
            event.preventDefault();
            const root=rootFor(updateButton); if(!root)return;
            const panel=root.querySelector('[data-stripe-card-panel]');
            const status=cardStatus(root);
            panel.hidden=false;
            updateButton.disabled=true;
            if(status) status.textContent='Preparing secure card update…';
            try{
                const data=await req(root.dataset.cardUrl);
                if(!data.client_secret||!data.publishable_key) throw new Error(data.message||'Unable to prepare card update.');
                stripe=window.Stripe(data.publishable_key);
                elements=stripe.elements({clientSecret:data.client_secret});
                activeRoot=root;
                setupIntentId=null;
                const mount=root.querySelector('#rc-stripe-card-element');
                if(mount) mount.innerHTML='';
                elements.create('payment').mount(mount);
                if(status) status.textContent='Enter the card you want to use for future My Journey billing.';
                panel.scrollIntoView({behavior:'smooth',block:'nearest'});
            }catch(error){
                if(status) status.textContent=error.message||'Unable to prepare card update.';
            }finally{
                updateButton.disabled=false;
            }
            return;
        }
        const closeButton=event.target.closest('[data-stripe-card-close]');
        if(closeButton){
            event.preventDefault();
            closeCardPanel(rootFor(closeButton));
            return;
        }
        const saveButton=event.target.closest('[data-stripe-card-save]');
        if(saveButton){
            event.preventDefault();
            const root=rootFor(saveButton);
            const status=cardStatus(root);
            if(!root||!stripe||!elements||saveButton.disabled)return;
            saveButton.disabled=true;
            const oldLabel=saveButton.textContent;
            saveButton.textContent='Saving…';
            try{
                const result=await stripe.confirmSetup({elements,redirect:'if_required'});
                if(result.error) throw result.error;
                setupIntentId=result.setupIntent?.id||null;
                if(!setupIntentId) throw new Error('Stripe confirmed the card but did not return the SetupIntent ID.');
                if(status) status.textContent='Card confirmed. Updating your subscription…';
                await req(root.dataset.cardCompleteUrl,'POST',{setup_intent_id:setupIntentId});
                if(status) status.textContent='Payment method updated. Refreshing…';
                saveButton.textContent='Saved';
                setTimeout(()=>window.location.reload(),450);
            }catch(error){
                if(status) status.textContent=error.message||'Unable to save card.';
                saveButton.disabled=false;
                saveButton.textContent=oldLabel;
            }
            return;
        }
        const cancelButton=event.target.closest('[data-stripe-cancel-plan]');
        if(cancelButton){
            event.preventDefault();
            const root=rootFor(cancelButton); if(!root)return;
            if(!confirm('Cancel My Journey at the end of the current billing period? Your purchased credits will not expire.'))return;
            cancelButton.disabled=true;
            try{await req(root.dataset.cancelUrl);window.location.reload();}
            catch(error){alert(error.message);cancelButton.disabled=false;}
            return;
        }
        const resumeButton=event.target.closest('[data-stripe-resume-plan]');
        if(resumeButton){
            event.preventDefault();
            const root=rootFor(resumeButton); if(!root)return;
            resumeButton.disabled=true;
            try{await req(root.dataset.resumeUrl);window.location.reload();}
            catch(error){alert(error.message);resumeButton.disabled=false;}
        }
    });
})();
</script>