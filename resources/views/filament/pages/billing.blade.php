<x-filament-panels::page>
    @php
        $billing = $this->billing;
        $brand = $billing->payment_brand ? strtoupper($billing->payment_brand) : 'Card';
        $plan = match ($billing->plan_key) {
            'amplify' => 'Amplify',
            'my-journey', 'my_journey' => 'My Journey',
            default => 'Free',
        };
    @endphp

    <style>
        .pc-billing{display:grid;gap:1rem}.pc-settings-tabs{display:flex;gap:.5rem;flex-wrap:wrap}.pc-settings-tab{border:1px solid #e5e7eb;border-radius:999px;padding:.55rem .85rem;font-size:.78rem;font-weight:800;background:#fff}.pc-settings-tab.is-active{background:#111827;color:#fff;border-color:#111827}.pc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}.pc-card{border:1px solid rgb(229 231 235);border-radius:1rem;padding:1rem;background:var(--fi-color-white,#fff)}html.dark .pc-card{border-color:rgba(255,255,255,.1);background:rgba(255,255,255,.035)}.pc-title{font-size:1rem;font-weight:800}.pc-muted{color:#6b7280;font-size:.82rem}.pc-payment{display:flex;align-items:center;gap:.8rem;margin-top:.8rem}.pc-payment-icon{width:2.6rem;height:2.6rem;border-radius:.8rem;display:grid;place-items:center;background:#fff1ec;color:#f05b34}.pc-payment strong{display:block}.pc-payment span{display:block;color:#6b7280;font-size:.78rem}.pc-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem;margin-top:1rem}.pc-field{display:grid;gap:.35rem}.pc-field.full{grid-column:1/-1}.pc-field label{font-size:.75rem;font-weight:700}.pc-field input{width:100%;border:1px solid #d1d5db;border-radius:.7rem;padding:.68rem .75rem;background:transparent}.pc-actions{display:flex;gap:.65rem;flex-wrap:wrap;margin-top:1rem}.pc-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:.7rem;padding:.65rem .9rem;font-size:.8rem;font-weight:800;border:1px solid #d1d5db}.pc-btn.primary{background:#f05b34;color:#fff;border-color:#f05b34}.pc-status-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem;margin-top:.8rem}.pc-stat{border:1px solid #e5e7eb;border-radius:.75rem;padding:.7rem}.pc-stat small{display:block;color:#6b7280}.pc-stat strong{display:block;margin-top:.2rem}.pc-table{width:100%;border-collapse:collapse;margin-top:.75rem;font-size:.78rem}.pc-table th,.pc-table td{text-align:left;padding:.55rem;border-bottom:1px solid #e5e7eb}@media(max-width:760px){.pc-grid,.pc-fields,.pc-status-grid{grid-template-columns:1fr}.pc-field.full{grid-column:auto}}
    </style>

    <div class="pc-billing">
        <div class="pc-settings-tabs" aria-label="Settings sections">
            <span class="pc-settings-tab is-active">Billing &amp; Payments</span>
        </div>

        <div class="pc-grid">
            <section class="pc-card">
                <div class="pc-title">Current Plan</div>
                <p class="pc-muted">Your PLYRCARD membership and billing status.</p>
                <div class="pc-status-grid">
                    <div class="pc-stat"><small>Plan</small><strong>{{ $plan }}</strong></div>
                    <div class="pc-stat"><small>Payment</small><strong>{{ str($billing->payment_status ?: 'not available')->replace('_',' ')->title() }}</strong></div>
                    <div class="pc-stat"><small>Subscription</small><strong>{{ str($billing->subscription_status ?: 'not available')->replace('_',' ')->title() }}</strong></div>
                    <div class="pc-stat"><small>Recurring</small><strong>{{ $billing->recurring_amount_cents ? '$'.number_format($billing->recurring_amount_cents / 100, 2).'/mo' : '—' }}</strong></div>
                </div>
            </section>

            <section class="pc-card">
                <div class="pc-title">Payment Method</div>
                @if ($billing->card_last_four)
                    <div class="pc-payment">
                        <div class="pc-payment-icon">💳</div>
                        <div>
                            <strong>{{ $brand }} ending in {{ $billing->card_last_four }}</strong>
                            <span>{{ $billing->card_expiration ? 'Expires '.$billing->card_expiration : 'Secure payment method on file' }}@if($billing->cardholder_name) · {{ $billing->cardholder_name }}@endif</span>
                        </div>
                    </div>
                @else
                    <p class="pc-muted" style="margin-top:.8rem">A saved payment method is not available yet.</p>
                @endif

                <div class="pc-actions">
                    @if ($this->paymentMethodUpdateUrl)
                        <a class="pc-btn primary" href="{{ $this->paymentMethodUpdateUrl }}">Update Payment Method</a>
                    @endif
                    <button class="pc-btn" type="button" wire:click="refreshPaymentMethod">Refresh Payment Info</button>
                </div>
                <p class="pc-muted" style="margin-top:.75rem">Only masked payment details are shown. Full card numbers and security codes are never stored in PLYRCARD.</p>
            </section>
        </div>

        <section class="pc-card">
            <div class="pc-title">Billing Information</div>
            <p class="pc-muted">This can be the parent, guardian, or other person responsible for payment. It does not change the athlete's PLYRCARD profile identity.</p>

            <form wire:submit="saveBilling">
                <div class="pc-fields">
                    <div class="pc-field"><label>Full Name</label><input wire:model="billing_name" required></div>
                    <div class="pc-field"><label>Billing Email</label><input type="email" wire:model="billing_email" required></div>
                    <div class="pc-field"><label>Phone</label><input wire:model="billing_phone"></div>
                    <div class="pc-field"><label>Company / Organization</label><input wire:model="billing_company"></div>
                    <div class="pc-field full"><label>Address Line 1</label><input wire:model="billing_address_1" required></div>
                    <div class="pc-field full"><label>Address Line 2</label><input wire:model="billing_address_2"></div>
                    <div class="pc-field"><label>City</label><input wire:model="billing_city" required></div>
                    <div class="pc-field"><label>State / Province</label><input wire:model="billing_state" required></div>
                    <div class="pc-field"><label>Postal Code</label><input wire:model="billing_postal_code" required></div>
                    <div class="pc-field"><label>Country</label><input wire:model="billing_country" required></div>
                </div>
                <div class="pc-actions"><button class="pc-btn primary" type="submit">Update Billing Information</button></div>
            </form>
        </section>

        <section class="pc-card">
            <div class="pc-title">Recent Payments</div>
            @if ($this->latestTransactions->isEmpty())
                <p class="pc-muted" style="margin-top:.75rem">No payment history is available yet.</p>
            @else
                <div style="overflow:auto"><table class="pc-table"><thead><tr><th>Date</th><th>Status</th><th>Amount</th><th>Method</th></tr></thead><tbody>
                    @foreach ($this->latestTransactions as $transaction)
                        <tr><td>{{ optional($transaction->paid_at ?: $transaction->ghl_created_at)->format('M j, Y') ?: '—' }}</td><td>{{ str($transaction->status ?: 'unknown')->replace('_',' ')->title() }}</td><td>{{ strtoupper($transaction->currency ?: 'USD') }} {{ number_format(($transaction->amount_cents ?? 0) / 100, 2) }}</td><td>{{ $transaction->card_brand ? strtoupper($transaction->card_brand).' •••• '.($transaction->card_last_four ?: '') : '—' }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </section>
    </div>
</x-filament-panels::page>