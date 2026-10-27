@php
    $stripeUpgradeRoutes = [
        'my-journey' => [
            'start' => route('billing.my-journey.start'),
            'status' => route('billing.my-journey.status'),
            'title' => 'My Journey',
        ],
        'jumpstart' => [
            'start' => route('billing.jumpstart.start'),
            'status' => route('billing.jumpstart.status'),
            'title' => 'Jumpstart',
        ],
        'amplify' => [
            'start' => route('billing.amplify.start'),
            'status' => route('billing.amplify.status'),
            'title' => 'Amplify',
        ],
    ];

    foreach ((array) config('plyrcard-points.point_packs', config('plyrcard-point-packs.packs', [])) as $packKey => $pack) {
        if (! ($pack['enabled'] ?? true)) {
            continue;
        }

        $points = max(0, (int) ($pack['points'] ?? 0));

        if ($points <= 0) {
            continue;
        }

        $stripeUpgradeRoutes['point-pack-' . $packKey] = [
            'start' => route('billing.point-packs.start', ['pack' => $packKey]),
            'status' => route('billing.point-packs.status', ['pack' => $packKey]),
            'title' => number_format($points) . ' Point Pack',
            'points' => $points,
        ];
    }

    // Custom point amounts are dynamic, so these URL templates are resolved in JS.
    $stripePointPackStartTemplate = route('billing.point-packs.start', ['pack' => '__PACK__']);
    $stripePointPackStatusTemplate = route('billing.point-packs.status', ['pack' => '__PACK__']);
@endphp

<style>
    .plyr-stripe-modal[hidden],
    .plyr-purchase-thank-you[hidden] {
        display: none !important;
    }

    .plyr-stripe-modal {
        position: fixed;
        inset: 0;
        z-index: 2147483600;
        display: grid;
        place-items: center;
        padding: 20px;
    }

    .plyr-stripe-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .64);
        backdrop-filter: blur(5px);
    }

    .plyr-stripe-card {
        position: relative;
        width: min(560px, 100%);
        max-height: 92vh;
        overflow: auto;
        background: #fff;
        border-radius: 20px;
        border: 1px solid rgba(15, 23, 42, .12);
        box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
        padding: 22px;
        color: #101828;
    }

    .dark .plyr-stripe-card {
        background: #0b0d10;
        color: #fff;
        border-color: rgba(255, 255, 255, .12);
    }

    .plyr-stripe-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }

    .plyr-stripe-head h3 {
        margin: 4px 0 0;
        font-size: 24px;
        line-height: 1.15;
    }

    .plyr-stripe-kicker {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: #ff6338;
    }

    .plyr-stripe-close {
        border: 1px solid #e5e7eb;
        background: transparent;
        border-radius: 10px;
        width: 38px;
        height: 38px;
        cursor: pointer;
        color: inherit;
        flex: 0 0 auto;
    }

    .plyr-stripe-summary {
        padding: 12px 14px;
        border-radius: 12px;
        background: #f8fafc;
        margin-bottom: 14px;
        color: #475467;
        font-size: 13px;
        line-height: 1.45;
    }

    .dark .plyr-stripe-summary {
        background: #15181e;
        color: #cbd5e1;
    }

    .plyr-stripe-element {
        padding: 14px;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #fff;
    }

    .plyr-stripe-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 16px;
    }

    .plyr-stripe-btn {
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        padding: 10px 15px;
        background: #fff;
        color: #101828;
        font-weight: 750;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
    }

    .plyr-stripe-btn.primary {
        background: #ff6338;
        border-color: #ff6338;
        color: #fff;
    }

    .plyr-stripe-btn:disabled {
        opacity: .7;
        cursor: wait;
    }

    .plyr-stripe-status {
        margin-top: 12px;
        font-size: 12px;
        line-height: 1.5;
        color: #667085;
    }

    .plyr-stripe-status.error {
        color: #b42318;
    }

    .plyr-stripe-status.success {
        color: #067647;
    }

    .plyr-stripe-loading {
        padding: 28px;
        text-align: center;
        color: #667085;
    }

    .plyr-purchase-thank-you {
        position: fixed;
        inset: 0;
        z-index: 2147483646;
        display: grid;
        place-items: center;
        padding: 24px;
        background: rgba(248, 250, 252, .96);
        backdrop-filter: blur(8px);
    }

    .plyr-purchase-thank-you-card {
        width: min(560px, 100%);
        text-align: center;
        padding: 42px 30px;
        border: 1px solid rgba(22, 163, 74, .22);
        border-radius: 24px;
        background: #fff;
        box-shadow: 0 28px 90px rgba(15, 23, 42, .18);
    }

    .plyr-purchase-thank-you-icon {
        width: 72px;
        height: 72px;
        margin: 0 auto 18px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: #dcfce7;
        color: #15803d;
        font-size: 34px;
        font-weight: 900;
    }

    .plyr-purchase-thank-you-kicker {
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: #15803d;
    }

    .plyr-purchase-thank-you h2 {
        margin: 8px 0 10px;
        font-size: 34px;
        line-height: 1.08;
        color: #111827;
    }

    .plyr-purchase-thank-you p {
        margin: 0 auto;
        max-width: 430px;
        color: #667085;
        font-size: 15px;
        line-height: 1.65;
    }

    .plyr-purchase-thank-you-wait {
        margin-top: 18px;
        font-size: 12px;
        font-weight: 700;
        color: #98a2b3;
    }

    .dark .plyr-purchase-thank-you {
        background: rgba(8, 10, 13, .96);
    }

    .dark .plyr-purchase-thank-you-card {
        background: #111318;
        border-color: rgba(74, 222, 128, .2);
    }

    .dark .plyr-purchase-thank-you h2 {
        color: #fff;
    }

    .dark .plyr-purchase-thank-you p {
        color: #cbd5e1;
    }

    .plyr-point-purchase-toast[hidden] {
        display: none !important;
    }

    .plyr-point-purchase-toast {
        position: fixed;
        right: 1.5rem;
        bottom: 6.5rem;
        z-index: 2147483645;
        width: min(420px, calc(100vw - 2rem));
        display: flex;
        align-items: flex-start;
        gap: .85rem;
        padding: 1rem 1.05rem;
        border: 1px solid rgba(22, 163, 74, .24);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 22px 60px rgba(15, 23, 42, .18);
        color: #101828;
    }

    .dark .plyr-point-purchase-toast {
        background: #111318;
        border-color: rgba(74, 222, 128, .24);
        color: #fff;
    }

    .plyr-point-purchase-toast-icon {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        display: grid;
        place-items: center;
        border-radius: 999px;
        background: #dcfce7;
        color: #15803d;
        font-size: 18px;
        font-weight: 900;
    }

    .plyr-point-purchase-toast-copy {
        min-width: 0;
        flex: 1;
    }

    .plyr-point-purchase-toast-copy strong {
        display: block;
        font-size: .92rem;
        line-height: 1.25;
        color: inherit;
    }

    .plyr-point-purchase-toast-copy span {
        display: block;
        margin-top: .22rem;
        font-size: .8rem;
        line-height: 1.45;
        color: #667085;
    }

    .dark .plyr-point-purchase-toast-copy span {
        color: #cbd5e1;
    }

    .plyr-point-purchase-toast-close {
        border: 0;
        background: transparent;
        color: #98a2b3;
        cursor: pointer;
        padding: .15rem;
        font-size: 1.1rem;
        line-height: 1;
    }

    @media (max-width: 640px) {
        .plyr-point-purchase-toast {
            right: 1rem;
            bottom: 5.75rem;
        }
    }

    .plyr-checkout-spinner {
        width: .9rem;
        height: .9rem;
        border: 2px solid currentColor;
        border-right-color: transparent;
        border-radius: 999px;
        display: inline-block;
        animation: plyr-checkout-spin .65s linear infinite;
        flex: 0 0 auto;
    }

    [data-plyrcard-checkout-busy="1"] {
        cursor: wait !important;
        opacity: .82 !important;
        pointer-events: none !important;
    }

    @keyframes plyr-checkout-spin {
        to { transform: rotate(360deg); }
    }
</style>

<div class="plyr-purchase-thank-you" data-plyr-purchase-thank-you hidden role="status" aria-live="assertive">
    <div class="plyr-purchase-thank-you-card">
        <div class="plyr-purchase-thank-you-icon" aria-hidden="true">✓</div>
        <div class="plyr-purchase-thank-you-kicker">Payment complete</div>
        <h2>Thank you!</h2>
        <p data-plyr-purchase-thank-you-copy></p>
        <div class="plyr-purchase-thank-you-wait">Updating your PLYRCARD account…</div>
    </div>
</div>

<div class="plyr-point-purchase-toast" data-plyr-point-purchase-toast hidden role="status" aria-live="polite">
    <div class="plyr-point-purchase-toast-icon" aria-hidden="true">✓</div>
    <div class="plyr-point-purchase-toast-copy">
        <strong data-plyr-point-purchase-toast-title>PLYR Points added successfully</strong>
        <span data-plyr-point-purchase-toast-body>Your updated balance is ready to use.</span>
    </div>
    <button type="button" class="plyr-point-purchase-toast-close" data-plyr-point-purchase-toast-close aria-label="Dismiss notification">×</button>
</div>

<div class="plyr-stripe-modal" id="plyr-stripe-upgrade-modal" hidden>
    <div class="plyr-stripe-backdrop" data-stripe-close></div>
    <div class="plyr-stripe-card" role="dialog" aria-modal="true" aria-labelledby="plyr-stripe-title">
        <div class="plyr-stripe-head">
            <div>
                <div class="plyr-stripe-kicker">Secure Stripe checkout</div>
                <h3 id="plyr-stripe-title">Checkout</h3>
            </div>
            <button class="plyr-stripe-close" type="button" data-stripe-close aria-label="Close checkout">×</button>
        </div>

        <div class="plyr-stripe-summary" data-stripe-summary>Preparing checkout…</div>
        <div class="plyr-stripe-loading" data-stripe-loading>Connecting securely to Stripe…</div>

        <div data-stripe-payment-shell hidden>
            <div class="plyr-stripe-element" id="plyr-stripe-payment-element"></div>
            <div class="plyr-stripe-actions">
                <button class="plyr-stripe-btn" type="button" data-stripe-close>Cancel</button>
                <button class="plyr-stripe-btn primary" type="button" data-stripe-confirm>Pay securely</button>
            </div>
        </div>

        <div class="plyr-stripe-status" data-stripe-status></div>
    </div>
</div>

<script>
(() => {
    const routes = @json($stripeUpgradeRoutes);
    const pointPackStartTemplate = @json($stripePointPackStartTemplate);
    const pointPackStatusTemplate = @json($stripePointPackStatusTemplate);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());

    const dynamicPointPackRoute = (type) => {
        const prefix = 'point-pack-';
        const rawType = String(type || '');

        if (!rawType.startsWith(prefix)) {
            return null;
        }

        const pack = rawType.slice(prefix.length);
        if (!pack) {
            return null;
        }

        let title = 'PLYR Points';
        let points = null;

        if (pack.startsWith('custom-')) {
            const parsed = Number(pack.slice('custom-'.length));
            if (Number.isFinite(parsed) && parsed > 0) {
                points = Math.round(parsed);
                title = `${points.toLocaleString()} Custom Points`;
            }
        }

        return {
            start: pointPackStartTemplate.replace('__PACK__', encodeURIComponent(pack)),
            status: pointPackStatusTemplate.replace('__PACK__', encodeURIComponent(pack)),
            title,
            points,
        };
    };

    const manager = {
        version: 'v22-unified',
        routes,
        csrf,
        activeType: null,
        activeTrigger: null,
        stripe: null,
        elements: null,
        confirming: false,
        statusTimer: null,

        modal() {
            return document.getElementById('plyr-stripe-upgrade-modal');
        },

        q(selector) {
            return this.modal()?.querySelector(selector) || null;
        },

        thankYou() {
            return document.querySelector('[data-plyr-purchase-thank-you]');
        },

        route(type) {
            return this.routes[type] || dynamicPointPackRoute(type);
        },

        setTriggerBusy(trigger, busy, label = 'Preparing…') {
            if (!trigger) return;

            if (busy) {
                if (!trigger.dataset.plyrcardOriginalHtml) {
                    trigger.dataset.plyrcardOriginalHtml = trigger.innerHTML;
                }

                if (!trigger.dataset.plyrcardOriginalWidth) {
                    const width = Math.ceil(trigger.getBoundingClientRect().width);
                    if (width > 0) {
                        trigger.dataset.plyrcardOriginalWidth = String(width);
                        trigger.style.minWidth = `${width}px`;
                    }
                }

                trigger.dataset.plyrcardCheckoutBusy = '1';
                trigger.setAttribute('aria-busy', 'true');
                if ('disabled' in trigger) trigger.disabled = true;
                trigger.innerHTML = `<span class="plyr-checkout-spinner" aria-hidden="true"></span><span>${label}</span>`;
                return;
            }

            if (trigger.dataset.plyrcardOriginalHtml) {
                trigger.innerHTML = trigger.dataset.plyrcardOriginalHtml;
            }

            delete trigger.dataset.plyrcardCheckoutBusy;
            trigger.removeAttribute('aria-busy');
            if ('disabled' in trigger) trigger.disabled = false;

            if (trigger.dataset.plyrcardOriginalWidth) {
                trigger.style.minWidth = '';
                delete trigger.dataset.plyrcardOriginalWidth;
            }
        },

        setConfirmBusy(busy, label = 'Processing…') {
            this.confirming = busy;
            const button = this.q('[data-stripe-confirm]');

            if (button) {
                button.disabled = busy;
                button.innerHTML = busy
                    ? `<span class="plyr-checkout-spinner" aria-hidden="true"></span><span>${label}</span>`
                    : 'Pay securely';
            }

            this.modal()?.querySelectorAll('[data-stripe-close]').forEach((element) => {
                element.disabled = busy;
            });
        },

        setStatus(text, tone = '') {
            const element = this.q('[data-stripe-status]');
            if (!element) return;
            element.textContent = text || '';
            element.className = 'plyr-stripe-status ' + tone;
        },

        cardHint(card) {
            if (!card || !card.last_four) return '';
            const brand = String(card.brand || 'card').toUpperCase();
            const expiration = String(card.expiration || '').trim();
            return `${brand} •••• ${card.last_four}${expiration ? ` · expires ${expiration}` : ''}`;
        },

        resetPaymentShell() {
            try {
                this.elements = null;
                const mount = this.q('#plyr-stripe-payment-element');
                if (mount) mount.innerHTML = '';
            } catch (_) {}

            const shell = this.q('[data-stripe-payment-shell]');
            if (shell) shell.hidden = true;
        },

        resetState() {
            clearTimeout(this.statusTimer);
            this.statusTimer = null;
            this.resetPaymentShell();
            this.stripe = null;
            this.activeType = null;
            this.setConfirmBusy(false);
            this.setStatus('');
        },

        close() {
            if (this.confirming) return;
            const modal = this.modal();
            if (modal) modal.hidden = true;
            this.setTriggerBusy(this.activeTrigger, false);
            this.activeTrigger = null;
            this.resetState();
            document.documentElement.style.overflow = '';
        },

        async request(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    ...(options.headers || {}),
                },
                ...options,
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Unable to prepare checkout.');
            }

            return data;
        },

        ensureStripe() {
            if (window.Stripe) return Promise.resolve(window.Stripe);
            if (window.__plyrcardStripeLoader) return window.__plyrcardStripeLoader;

            window.__plyrcardStripeLoader = new Promise((resolve, reject) => {
                let script = document.querySelector('script[data-plyrcard-stripe-js]')
                    || document.querySelector('script[src="https://js.stripe.com/v3/"]');

                const done = () => window.Stripe
                    ? resolve(window.Stripe)
                    : reject(new Error('Stripe could not be loaded.'));

                if (script) {
                    script.addEventListener('load', done, { once: true });
                    script.addEventListener('error', () => reject(new Error('Stripe could not be loaded.')), { once: true });
                    if (window.Stripe) resolve(window.Stripe);
                    return;
                }

                script = document.createElement('script');
                script.src = 'https://js.stripe.com/v3/';
                script.async = true;
                script.dataset.plyrcardStripeJs = '1';
                script.addEventListener('load', done, { once: true });
                script.addEventListener('error', () => reject(new Error('Stripe could not be loaded.')), { once: true });
                document.head.appendChild(script);
            });

            return window.__plyrcardStripeLoader;
        },

        prepareModal(route, message = 'Preparing checkout…') {
            const modal = this.modal();
            if (!modal) return null;

            modal.hidden = false;
            document.documentElement.style.overflow = 'hidden';
            this.resetPaymentShell();

            const title = this.q('#plyr-stripe-title');
            if (title) title.textContent = route?.title || 'Checkout';

            const summary = this.q('[data-stripe-summary]');
            if (summary) summary.textContent = message;

            const loading = this.q('[data-stripe-loading]');
            if (loading) loading.hidden = false;

            this.setStatus('');
            this.setConfirmBusy(false);
            return modal;
        },

        showError(error, route) {
            this.prepareModal(route, route ? `${route.title} checkout` : 'Checkout');
            const loading = this.q('[data-stripe-loading]');
            if (loading) loading.hidden = true;
            this.setStatus(error?.message || 'Checkout could not be prepared.', 'error');
            this.setTriggerBusy(this.activeTrigger, false);
        },

        rememberPurchaseSuccess(data = {}, route = null, type = '') {
            if (!String(type || '').startsWith('point-pack-')) return;

            const points = Number(data.points ?? route?.points ?? 0);
            const balance = Number(data.points_available ?? 0);
            const payload = {
                title: 'PLYR Points added successfully',
                body: points > 0
                    ? `${points.toLocaleString()} point${points === 1 ? '' : 's'} were added to your account.${balance >= 0 ? ` New balance: ${balance.toLocaleString()} points.` : ''}`
                    : 'Your point purchase was successful and your updated balance is ready to use.',
                savedAt: Date.now(),
            };

            try {
                sessionStorage.setItem('plyrcard:last-point-purchase-success', JSON.stringify(payload));
            } catch (_) {}
        },

        restorePurchaseSuccess() {
            let payload = null;

            try {
                const raw = sessionStorage.getItem('plyrcard:last-point-purchase-success');
                if (raw) {
                    payload = JSON.parse(raw);
                    sessionStorage.removeItem('plyrcard:last-point-purchase-success');
                }
            } catch (_) {}

            if (!payload || (payload.savedAt && Date.now() - Number(payload.savedAt) > 120000)) return;

            const toast = document.querySelector('[data-plyr-point-purchase-toast]');
            if (!toast) return;

            const title = toast.querySelector('[data-plyr-point-purchase-toast-title]');
            const body = toast.querySelector('[data-plyr-point-purchase-toast-body]');
            if (title) title.textContent = payload.title || 'PLYR Points added successfully';
            if (body) body.textContent = payload.body || 'Your updated balance is ready to use.';

            toast.hidden = false;
            clearTimeout(window.__plyrcardPointPurchaseToastTimer);
            window.__plyrcardPointPurchaseToastTimer = setTimeout(() => {
                toast.hidden = true;
            }, 8000);
        },

        showThankYou(message, data = {}, route = null, type = '') {
            this.rememberPurchaseSuccess(data, route, type);

            const overlay = this.thankYou();
            if (!overlay) {
                window.location.reload();
                return;
            }

            const copy = overlay.querySelector('[data-plyr-purchase-thank-you-copy]');
            if (copy) copy.textContent = message;

            const modal = this.modal();
            if (modal) modal.hidden = true;

            overlay.hidden = false;
            document.documentElement.style.overflow = 'hidden';
            setTimeout(() => window.location.reload(), 2200);
        },

        async mountPaymentElement(data, route) {
            const hint = this.cardHint(data.saved_card);
            this.prepareModal(
                route,
                hint
                    ? `Saved payment method: ${hint}. Enter another card below.`
                    : (data.message || 'Enter your payment details below.'),
            );

            await this.ensureStripe();
            this.stripe = window.Stripe(data.publishable_key);
            this.resetPaymentShell();

            this.elements = this.stripe.elements({
                clientSecret: data.client_secret,
                appearance: {
                    theme: document.documentElement.classList.contains('dark') ? 'night' : 'stripe',
                    variables: {
                        colorPrimary: '#ff6338',
                        borderRadius: '10px',
                    },
                },
            });

            this.elements.create('payment', { layout: 'tabs' }).mount('#plyr-stripe-payment-element');

            const loading = this.q('[data-stripe-loading]');
            if (loading) loading.hidden = true;

            const shell = this.q('[data-stripe-payment-shell]');
            if (shell) shell.hidden = false;

            this.setConfirmBusy(false);
            this.setTriggerBusy(this.activeTrigger, false);
        },

        async poll() {
            if (!this.activeType) return;
            const route = this.route(this.activeType);
            if (!route) return;

            try {
                const data = await this.request(route.status, { method: 'GET' });

                if (data.completed) {
                    clearTimeout(this.statusTimer);
                    this.statusTimer = null;
                    this.setTriggerBusy(this.activeTrigger, true, 'Complete');
                    const purchase = String(this.activeType).startsWith('point-pack-')
                        || ['jumpstart', 'amplify'].includes(this.activeType);
                    this.showThankYou(
                        purchase
                            ? `Your ${route.title} purchase is complete. Your updated points are now available.`
                            : `Your ${route.title} is complete and your account has been updated.`,
                        data,
                        route,
                        this.activeType,
                    );
                    return;
                }
            } catch (_) {}

            this.setTriggerBusy(this.activeTrigger, true, 'Finalizing…');
            this.statusTimer = setTimeout(() => this.poll(), 1200);
        },

        async useSavedPaymentMethod(data, route) {
            await this.ensureStripe();
            this.stripe = window.Stripe(data.publishable_key);
            this.setTriggerBusy(this.activeTrigger, true, 'Processing…');

            try {
                const result = await this.stripe.confirmCardPayment(data.client_secret);
                if (result.error) throw result.error;
                this.setTriggerBusy(this.activeTrigger, true, 'Confirming…');
                this.poll();
            } catch (error) {
                await this.mountPaymentElement(data, route);
                this.setStatus(
                    (error?.message || 'The saved payment method could not be used automatically.')
                    + ' Please confirm another payment method.',
                    'error',
                );
            }
        },

        async open(type, trigger) {
            if (trigger?.dataset.plyrcardCheckoutBusy === '1' || this.confirming) return;

            const route = this.route(type);
            if (!route) return;

            // Only one checkout start can be active at a time. This prevents the
            // duplicate POST race that previously produced Stripe idempotency 409s.
            if (this.activeType && this.activeType !== type) {
                this.close();
            }

            this.activeType = type;
            this.activeTrigger = trigger;
            clearTimeout(this.statusTimer);
            this.statusTimer = null;
            this.setTriggerBusy(trigger, true, 'Preparing…');

            try {
                const data = await this.request(route.start, {
                    method: 'POST',
                    body: '{}',
                });

                if (data.completed) {
                    this.setTriggerBusy(trigger, true, 'Complete');
                    const purchase = String(type).startsWith('point-pack-')
                        || ['jumpstart', 'amplify'].includes(type);
                    this.showThankYou(
                        purchase
                            ? `Your ${route.title} purchase is complete. Your points have been added successfully.`
                            : `Your ${route.title} is complete and your account has been updated.`,
                        data,
                        route,
                        type,
                    );
                    return;
                }

                if (!data.client_secret || !data.publishable_key) {
                    throw new Error(data.message || 'Stripe did not return a payment session.');
                }

                if (data.saved_payment_method) {
                    await this.useSavedPaymentMethod(data, route);
                    return;
                }

                await this.mountPaymentElement(data, route);
            } catch (error) {
                this.showError(error, route);
            }
        },

        async confirm() {
            if (!this.stripe || !this.elements || this.confirming) return;

            this.setConfirmBusy(true, 'Processing…');
            this.setStatus('');

            try {
                const submitted = await this.elements.submit();
                if (submitted?.error) throw submitted.error;

                const result = await this.stripe.confirmPayment({
                    elements: this.elements,
                    confirmParams: { return_url: window.location.href },
                    redirect: 'if_required',
                });

                if (result.error) throw result.error;

                const modal = this.modal();
                if (modal) modal.hidden = true;
                this.setTriggerBusy(this.activeTrigger, true, 'Confirming…');
                this.poll();
            } catch (error) {
                this.setConfirmBusy(false);
                this.setStatus(error?.message || 'Payment could not be completed.', 'error');
            }
        },
    };

    window.PlyrcardStripeCheckout = manager;
    manager.restorePurchaseSuccess();

    document.querySelector('[data-plyr-point-purchase-toast-close]')?.addEventListener('click', () => {
        const toast = document.querySelector('[data-plyr-point-purchase-toast]');
        if (toast) toast.hidden = true;
        clearTimeout(window.__plyrcardPointPurchaseToastTimer);
    });

    if (!window.__plyrcardStripeCheckoutDelegatedV22) {
        window.__plyrcardStripeCheckoutDelegatedV22 = true;

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest?.(
                '[data-plyrcard-my-journey-open],'
                + '[data-plyrcard-jumpstart-open],'
                + '[data-plyrcard-amplify-open],'
                + '[data-plyrcard-point-pack-open]'
            );

            if (trigger) {
                event.preventDefault();
                event.stopImmediatePropagation();

                const pack = trigger.getAttribute('data-plyrcard-point-pack-open');
                const type = pack
                    ? `point-pack-${pack}`
                    : trigger.hasAttribute('data-plyrcard-jumpstart-open')
                        ? 'jumpstart'
                        : trigger.hasAttribute('data-plyrcard-amplify-open')
                            ? 'amplify'
                            : 'my-journey';

                window.PlyrcardStripeCheckout?.open(type, trigger);
                return;
            }

            if (event.target.closest?.('[data-stripe-close]')) {
                event.preventDefault();
                window.PlyrcardStripeCheckout?.close();
                return;
            }

            if (event.target.closest?.('[data-stripe-confirm]')) {
                event.preventDefault();
                window.PlyrcardStripeCheckout?.confirm();
            }
        }, true);
    }
})();
</script>