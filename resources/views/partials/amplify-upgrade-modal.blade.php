@once
    <style>
        .plyr-amplify-modal[hidden],.plyr-amplify-state[hidden],.plyr-amplify-frame[hidden]{display:none!important}
        .plyr-amplify-modal{position:fixed;inset:0;z-index:100500;display:grid;place-items:center;padding:18px;background:rgba(2,6,23,.72);backdrop-filter:blur(7px)}
        .plyr-amplify-panel{width:min(760px,100%);max-height:min(900px,calc(100vh - 30px));display:flex;flex-direction:column;background:#fff;border:1px solid #e5e7eb;border-radius:22px;box-shadow:0 28px 80px rgba(0,0,0,.3);overflow:hidden;color:#101828}
        .plyr-amplify-head{display:flex;align-items:flex-start;gap:14px;padding:18px 20px;border-bottom:1px solid #eaecf0;background:linear-gradient(135deg,#fff7f3,#fff)}
        .plyr-amplify-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;background:#ffebe5;color:#ff5c35;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
        .plyr-amplify-title{margin:5px 0 0;font-size:23px;line-height:1.1;font-weight:900;letter-spacing:-.03em}
        .plyr-amplify-copy{margin:6px 0 0;color:#667085;font-size:12.5px;line-height:1.45}
        .plyr-amplify-close{margin-left:auto;width:38px;height:38px;display:grid;place-items:center;border:1px solid #e4e7ec;border-radius:11px;background:#fff;color:#344054;font-size:22px;cursor:pointer;flex:0 0 auto}
        .plyr-amplify-body{position:relative;min-height:390px;overflow:auto;background:#f8fafc}
        .plyr-amplify-frame{display:block;width:100%;min-height:650px;border:0;background:#fff}
        .plyr-amplify-state{min-height:390px;display:grid;place-items:center;padding:34px;text-align:center}
        .plyr-amplify-state-inner{max-width:420px}
        .plyr-amplify-spinner{width:38px;height:38px;margin:0 auto 16px;border-radius:50%;border:4px solid #fee4dc;border-top-color:#ff6338;animation:plyrAmplifySpin .8s linear infinite}
        .plyr-amplify-state strong{display:block;font-size:17px}.plyr-amplify-state span{display:block;margin-top:6px;color:#667085;font-size:13px;line-height:1.5}
        .plyr-amplify-success{color:#067647}.plyr-amplify-success-icon{width:52px;height:52px;margin:0 auto 14px;border-radius:50%;display:grid;place-items:center;background:#dcfae6;color:#067647;font-size:24px;font-weight:900}
        .plyr-amplify-error{color:#b42318}.plyr-billing-recovery{padding:22px!important;align-items:start!important}.plyr-billing-form{width:min(620px,100%);margin:0 auto;text-align:left}.plyr-billing-title strong{font-size:18px}.plyr-billing-title span{margin-top:5px!important}.plyr-billing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:18px}.plyr-billing-grid label{display:grid;gap:5px;font-size:12px;font-weight:800;color:#344054}.plyr-billing-grid label.full{grid-column:1/-1}.plyr-billing-grid em{font-weight:500;color:#98a2b3}.plyr-billing-grid input{width:100%;box-sizing:border-box;border:1px solid #d0d5dd;border-radius:10px;background:#fff;padding:11px 12px;color:#101828;font:inherit;outline:none}.plyr-billing-grid input:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.12)}.plyr-billing-error{margin-top:12px;padding:10px 12px;border-radius:9px;background:#fef3f2;color:#b42318;font-size:12px}.plyr-billing-form>.plyr-amplify-retry{display:block;margin:16px 0 0 auto}.plyr-amplify-retry{margin-top:16px;border:0;border-radius:10px;padding:10px 16px;background:#ff6338;color:#fff;font-weight:800;cursor:pointer}
        .plyr-amplify-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 18px;border-top:1px solid #eaecf0;background:#fff;color:#667085;font-size:11.5px}
        .plyr-amplify-foot strong{color:#344054}
        @keyframes plyrAmplifySpin{to{transform:rotate(360deg)}}
        @media(max-width:640px){.plyr-billing-grid{grid-template-columns:1fr}.plyr-billing-grid label.full{grid-column:auto}.plyr-amplify-modal{padding:0;place-items:stretch}.plyr-amplify-panel{width:100%;max-height:100vh;height:100vh;border-radius:0}.plyr-amplify-head{padding:14px 15px}.plyr-amplify-title{font-size:20px}.plyr-amplify-body{flex:1}.plyr-amplify-frame{min-height:760px}.plyr-amplify-foot{padding-bottom:max(11px,env(safe-area-inset-bottom))}}
    </style>

    <div
        class="plyr-amplify-modal"
        data-plyrcard-amplify-modal
        data-start-url="{{ route('billing.amplify.start') }}"
        data-status-url="{{ route('billing.amplify.status') }}"
        data-billing-url="{{ route('locker-room.billing.update') }}"
        hidden
    >
        <div class="plyr-amplify-panel" role="dialog" aria-modal="true" aria-labelledby="plyr-amplify-title">
            <header class="plyr-amplify-head">
                <div>
                    <span class="plyr-amplify-badge">Amplify</span>
                    <h2 class="plyr-amplify-title" id="plyr-amplify-title">Upgrade to Amplify</h2>
                    <p class="plyr-amplify-copy">Amplify is a one-time service extension of My Journey. If you are not subscribed yet, My Journey starts in the same checkout.</p>
                </div>
                <button class="plyr-amplify-close" type="button" data-plyrcard-amplify-close aria-label="Close">×</button>
            </header>

            <div class="plyr-amplify-body">
                <div class="plyr-amplify-state" data-plyrcard-amplify-loading>
                    <div class="plyr-amplify-state-inner"><div class="plyr-amplify-spinner"></div><strong>Preparing secure checkout</strong><span>Connecting this purchase to your billing profile…</span></div>
                </div>
                <div class="plyr-amplify-state" data-plyrcard-amplify-success hidden>
                    <div class="plyr-amplify-state-inner plyr-amplify-success"><div class="plyr-amplify-success-icon">✓</div><strong>Amplify is active</strong><span>Your account has been upgraded successfully.</span></div>
                </div>
                <div class="plyr-amplify-state" data-plyrcard-amplify-error hidden>
                    <div class="plyr-amplify-state-inner plyr-amplify-error"><strong>Checkout could not be prepared</strong><span data-plyrcard-amplify-error-copy>Please try again.</span><button class="plyr-amplify-retry" type="button" data-plyrcard-amplify-retry>Try Again</button></div>
                </div>

                <div class="plyr-amplify-state plyr-billing-recovery" data-plyrcard-amplify-billing hidden>
                    <form class="plyr-billing-form" data-plyrcard-amplify-billing-form>
                        <div class="plyr-billing-title"><strong>Complete billing information</strong><span>We need the payer details below to connect this service purchase to your account. Card details stay inside the secure checkout.</span></div>
                        <div class="plyr-billing-grid">
                            <label><span>Billing name</span><input name="billing_name" required></label>
                            <label><span>Billing email</span><input name="billing_email" type="email" required></label>
                            <label><span>Phone</span><input name="billing_phone"></label>
                            <label><span>Company <em>optional</em></span><input name="billing_company"></label>
                            <label class="full"><span>Address</span><input name="billing_address_1" required></label>
                            <label><span>City</span><input name="billing_city" required></label>
                            <label><span>State / Province</span><input name="billing_state" required></label>
                            <label><span>Postal code</span><input name="billing_postal_code" required></label>
                            <label><span>Country</span><input name="billing_country" value="US" required></label>
                        </div>
                        <div class="plyr-billing-error" data-plyrcard-amplify-billing-error hidden></div>
                        <button class="plyr-amplify-retry" type="submit">Save &amp; Continue</button>
                    </form>
                </div>
                <iframe
                    class="plyr-amplify-frame"
                    data-plyrcard-amplify-frame
                    src="about:blank"
                    scrolling="no"
                    id="plyrcard-amplify-checkout"
                    title="Amplify secure checkout"
                    data-cookie-consent="true"
                    data-cookie-consent-provider="auto"
                    hidden
                ></iframe>
            </div>
            <footer class="plyr-amplify-foot"><span data-plyrcard-amplify-status>Payment confirmation is checked automatically.</span><strong>Secure checkout</strong></footer>
        </div>
    </div>

    <script src="https://systems.plyrcard.com/js/form_embed.js" defer></script>
    <script>
        (() => {
            if (window.__plyrcardAmplifyCheckoutV1099) return;
            window.__plyrcardAmplifyCheckoutV1099 = true;

            const modal = document.querySelector('[data-plyrcard-amplify-modal]');
            if (!modal) return;

            const frame = modal.querySelector('[data-plyrcard-amplify-frame]');
            const panel = modal.querySelector('.plyr-amplify-panel');
            const foot = modal.querySelector('.plyr-amplify-foot');
            const loading = modal.querySelector('[data-plyrcard-amplify-loading]');
            const success = modal.querySelector('[data-plyrcard-amplify-success]');
            const error = modal.querySelector('[data-plyrcard-amplify-error]');
            const errorCopy = modal.querySelector('[data-plyrcard-amplify-error-copy]');
            const billing = modal.querySelector('[data-plyrcard-amplify-billing]');
            const billingForm = modal.querySelector('[data-plyrcard-amplify-billing-form]');
            const billingError = modal.querySelector('[data-plyrcard-amplify-billing-error]');
            const statusCopy = modal.querySelector('[data-plyrcard-amplify-status]');
            let pollTimer = null;
            let pollStartedAt = 0;
            let starting = false;

            const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '';
            const show = (target) => {
                [loading, success, error, billing, frame].forEach(el => { if (el) el.hidden = el !== target; });
                panel?.classList.toggle('is-checkout', target === frame);
                if (foot) foot.hidden = target === frame;
            };
            const stopPolling = () => { if (pollTimer) clearTimeout(pollTimer); pollTimer = null; };
            const close = () => { stopPolling(); modal.hidden = true; document.documentElement.style.overflow = ''; };

            async function jsonRequest(url, options = {}) {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(options.method && options.method !== 'GET' ? {'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json'} : {}),
                    },
                    ...options,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.success === false) { const error = new Error(data.message || 'Unable to continue.'); error.data = data; throw error; }
                return data;
            }

            async function poll() {
                if (modal.hidden) return;
                if (Date.now() - pollStartedAt > 12 * 60 * 1000) {
                    statusCopy.textContent = 'Checkout is still open. Complete payment to activate Amplify.';
                    return;
                }
                try {
                    const data = await jsonRequest(modal.dataset.statusUrl);
                    if (data.completed) {
                        stopPolling();
                        show(success);
                        statusCopy.textContent = 'Payment confirmed. Updating your PLYRCARD…';
                        window.dispatchEvent(new CustomEvent('plyrcard:amplify-upgraded', {detail: data}));
                        setTimeout(() => {
                            if (!document.querySelector('[data-lr-drawer]')) window.location.reload();
                        }, 900);
                        return;
                    }
                    statusCopy.textContent = data.message || 'Waiting for payment confirmation…';
                } catch (_) {
                    statusCopy.textContent = 'Still checking payment confirmation…';
                }
                pollTimer = setTimeout(poll, 2500);
            }

            async function start() {
                if (starting) return;
                starting = true;
                stopPolling();
                modal.hidden = false;
                document.documentElement.style.overflow = 'hidden';
                show(loading);
                statusCopy.textContent = 'Preparing secure checkout…';

                try {
                    const data = await jsonRequest(modal.dataset.startUrl, {method:'POST', body:'{}'});
                    if (data.completed) {
                        show(success);
                        statusCopy.textContent = data.message || 'Amplify is active.';
                        window.dispatchEvent(new CustomEvent('plyrcard:amplify-upgraded', {detail:data}));
                        setTimeout(() => { if (!document.querySelector('[data-lr-drawer]')) window.location.reload(); }, 700);
                        return;
                    }
                    if (!data.checkout_url) throw new Error(data.message || 'Secure checkout is unavailable.');
                    try {
                        const checkoutUrl = new URL(data.checkout_url, window.location.origin);
                        const pathParts = checkoutUrl.pathname.split('/').filter(Boolean);
                        const surveyId = pathParts[pathParts.length - 1] || '';
                        if (surveyId) frame.id = surveyId;
                    } catch (_) {}
                    frame.src = data.checkout_url;
                    show(frame);
                    pollStartedAt = Date.now();
                    statusCopy.textContent = data.message || (data.display_due_today ? `${data.display_due_today} due today.` : 'Complete checkout to continue.');
                    pollTimer = setTimeout(poll, 1800);
                } catch (e) {
                    if (['billing_profile_required', 'billing_contact_unavailable'].includes(e.data?.reason) || /billing information|billing profile|billing contact/i.test(String(e.message || ''))) {
                        const values = e.data?.billing || {};
                        Object.entries(values).forEach(([key, value]) => { const field = billingForm?.elements?.namedItem(key); if (field && value != null) field.value = value; });
                        if (billingForm?.elements?.billing_country && !billingForm.elements.billing_country.value) billingForm.elements.billing_country.value = 'US';
                        show(billing);
                        statusCopy.textContent = 'Save your billing information, then checkout will continue automatically.';
                    } else {
                        show(error);
                        errorCopy.textContent = e.message || 'Please try again.';
                        statusCopy.textContent = 'Checkout was not started.';
                    }
                } finally {
                    starting = false;
                }
            }


            billingForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                billingError.hidden = true;
                const submit = billingForm.querySelector('button[type="submit"]');
                submit.disabled = true;
                submit.textContent = 'Saving…';
                try {
                    const payload = Object.fromEntries(new FormData(billingForm).entries());
                    const response = await fetch(modal.dataset.billingUrl, {
                        method: 'POST', credentials: 'same-origin',
                        headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf(),'Content-Type':'application/json'},
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Billing information could not be saved.');
                    await start();
                } catch (error) {
                    billingError.textContent = error.message || 'Billing information could not be saved.';
                    billingError.hidden = false;
                } finally {
                    submit.disabled = false;
                    submit.textContent = 'Save & Continue';
                }
            });

            document.addEventListener('click', (event) => {
                const opener = event.target.closest('[data-plyrcard-amplify-open]');
                if (opener) { event.preventDefault(); start(); return; }
                if (event.target.closest('[data-plyrcard-amplify-close]')) { close(); return; }
                if (event.target.closest('[data-plyrcard-amplify-retry]')) { start(); return; }
                if (event.target === modal) close();
            });
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) close(); });
        })();
    </script>
@endonce