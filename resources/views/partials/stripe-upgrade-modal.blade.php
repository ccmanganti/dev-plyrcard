@php
    $stripeUpgradeRoutes = [
        'my-journey' => ['start' => route('billing.my-journey.start'), 'status' => route('billing.my-journey.status'), 'title' => 'My Journey'],
        'jumpstart' => ['start' => route('billing.jumpstart.start'), 'status' => route('billing.jumpstart.status'), 'title' => 'Jumpstart'],
        'amplify' => ['start' => route('billing.amplify.start'), 'status' => route('billing.amplify.status'), 'title' => 'Amplify'],
    ];
@endphp
<style>
    .plyr-stripe-modal[hidden]{display:none!important}.plyr-stripe-modal{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:20px}.plyr-stripe-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.58);backdrop-filter:blur(4px)}.plyr-stripe-card{position:relative;width:min(560px,100%);max-height:92vh;overflow:auto;background:#fff;border-radius:20px;border:1px solid rgba(15,23,42,.12);box-shadow:0 28px 80px rgba(15,23,42,.28);padding:22px}.dark .plyr-stripe-card{background:#0b0d10;color:#fff;border-color:rgba(255,255,255,.12)}.plyr-stripe-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}.plyr-stripe-head h3{margin:4px 0 0;font-size:24px}.plyr-stripe-kicker{font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#ff6338}.plyr-stripe-close{border:1px solid #e5e7eb;background:transparent;border-radius:10px;width:38px;height:38px;cursor:pointer;color:inherit}.plyr-stripe-summary{padding:12px 14px;border-radius:12px;background:#f8fafc;margin-bottom:14px;color:#475467;font-size:13px}.dark .plyr-stripe-summary{background:#15181e;color:#cbd5e1}.plyr-stripe-element{padding:14px;border:1px solid #e5e7eb;border-radius:14px;background:#fff}.plyr-stripe-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:16px}.plyr-stripe-btn{border:1px solid #d0d5dd;border-radius:10px;padding:10px 15px;background:#fff;font-weight:750;cursor:pointer}.plyr-stripe-btn.primary{background:#ff6338;border-color:#ff6338;color:#fff}.plyr-stripe-status{margin-top:12px;font-size:12px;color:#667085}.plyr-stripe-status.error{color:#b42318}.plyr-stripe-status.success{color:#067647}.plyr-stripe-loading{padding:28px;text-align:center;color:#667085}
</style>
<div class="plyr-stripe-modal" id="plyr-stripe-upgrade-modal" hidden>
    <div class="plyr-stripe-backdrop" data-stripe-close></div>
    <div class="plyr-stripe-card" role="dialog" aria-modal="true" aria-labelledby="plyr-stripe-title">
        <div class="plyr-stripe-head"><div><div class="plyr-stripe-kicker">Secure Stripe checkout</div><h3 id="plyr-stripe-title">Upgrade</h3></div><button class="plyr-stripe-close" type="button" data-stripe-close>×</button></div>
        <div class="plyr-stripe-summary" data-stripe-summary>Preparing checkout…</div>
        <div class="plyr-stripe-loading" data-stripe-loading>Connecting securely to Stripe…</div>
        <div data-stripe-payment-shell hidden><div class="plyr-stripe-element" id="plyr-stripe-payment-element"></div><div class="plyr-stripe-actions"><button class="plyr-stripe-btn" type="button" data-stripe-close>Cancel</button><button class="plyr-stripe-btn primary" type="button" data-stripe-confirm>Pay securely</button></div></div>
        <div class="plyr-stripe-status" data-stripe-status></div>
    </div>
</div>
<script src="https://js.stripe.com/v3/"></script>
<script>
(() => {
    const routes = @json($stripeUpgradeRoutes);
    const modal = document.getElementById('plyr-stripe-upgrade-modal');
    if (!modal || modal.dataset.ready === '1') return;
    modal.dataset.ready = '1';
    let stripe = null, elements = null, activeType = null, statusTimer = null, confirming = false;
    const q = s => modal.querySelector(s);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const setStatus = (text, tone='') => { const el=q('[data-stripe-status]'); el.textContent=text||''; el.className='plyr-stripe-status '+tone; };
    const cardHint = card => {
        if(!card || !card.last_four) return '';
        const brand=String(card.brand||'card').toUpperCase();
        const expiration=String(card.expiration||'').trim();
        return `${brand} •••• ${card.last_four}${expiration ? ` · expires ${expiration}` : ''}`;
    };
    const setConfirmBusy = (busy, label='Pay securely') => {
        confirming = busy;
        const btn=q('[data-stripe-confirm]');
        if (btn) { btn.disabled=busy; btn.textContent=busy ? label : 'Pay securely'; }
        modal.querySelectorAll('[data-stripe-close]').forEach(el => el.disabled=busy);
    };
    const resetPaymentShell = () => {
        elements=null;
        const mount=q('#plyr-stripe-payment-element');
        if (mount) mount.innerHTML='';
        q('[data-stripe-payment-shell]').hidden=true;
    };
    const close = () => {
        if (confirming) return;
        modal.hidden=true;
        clearTimeout(statusTimer);
        statusTimer=null;
        resetPaymentShell();
        stripe=null;
        activeType=null;
        setStatus('');
        setConfirmBusy(false);
    };
    modal.querySelectorAll('[data-stripe-close]').forEach(el => el.addEventListener('click', close));
    async function request(url, options={}) {
        const res=await fetch(url,{credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,...(options.headers||{})},...options});
        const data=await res.json().catch(()=>({}));
        if(!res.ok||data.success===false) throw new Error(data.message||'Unable to prepare checkout.');
        return data;
    }
    async function poll() {
        if(!activeType) return;
        try {
            const data=await request(routes[activeType].status);
            if(data.completed){
                clearTimeout(statusTimer);
                statusTimer=null;
                setStatus(data.message||'Payment confirmed. Updating your account…','success');
                setConfirmBusy(true,'Confirmed');
                setTimeout(()=>window.location.reload(),450);
                return;
            }
            setStatus(data.message||'Payment received. Finishing your account update…','success');
        } catch(e) {
            setStatus('Payment was submitted. Still confirming your account…','success');
        }
        statusTimer=setTimeout(poll,1200);
    }
    async function mountPaymentElement(data) {
        resetPaymentShell();
        elements=stripe.elements({clientSecret:data.client_secret,appearance:{theme:document.documentElement.classList.contains('dark')?'night':'stripe'}});
        elements.create('payment').mount('#plyr-stripe-payment-element');
        q('[data-stripe-loading]').hidden=true;
        q('[data-stripe-payment-shell]').hidden=false;
        setConfirmBusy(false);
    }
    async function confirmSavedCard(data) {
        setConfirmBusy(true,'Confirming saved card…');
        q('[data-stripe-loading]').hidden=false;
        q('[data-stripe-payment-shell]').hidden=true;
        const hint=cardHint(data.saved_card);
        setStatus(hint ? `Using saved ${hint}…` : 'Using your saved Stripe payment method…');
        try {
            const result=await stripe.confirmCardPayment(data.client_secret);
            if(result.error) throw result.error;
            q('[data-stripe-loading]').hidden=true;
            setStatus('Payment submitted. Confirming your account…','success');
            poll();
            return true;
        } catch(e) {
            setStatus((e.message||'The saved card could not be used automatically.')+' You can enter another payment method below.','error');
            await mountPaymentElement(data);
            return false;
        }
    }
    async function open(type) {
        if (confirming) return;
        activeType=type;
        const route=routes[type];
        if(!route)return;
        clearTimeout(statusTimer);
        statusTimer=null;
        modal.hidden=false;
        resetPaymentShell();
        setConfirmBusy(true,'Preparing…');
        q('#plyr-stripe-title').textContent=route.title;
        q('[data-stripe-loading]').hidden=false;
        setStatus('');
        q('[data-stripe-summary]').textContent='Preparing '+route.title+' checkout…';
        try {
            const data=await request(route.start,{method:'POST',body:'{}'});
            if(data.completed){
                q('[data-stripe-loading]').hidden=true;
                setStatus(data.message||route.title+' is active.','success');
                setConfirmBusy(true,'Confirmed');
                setTimeout(()=>window.location.reload(),450);
                return;
            }
            if(!data.client_secret||!data.publishable_key) throw new Error(data.message||'Stripe did not return a payment session.');
            stripe=window.Stripe(data.publishable_key);
            const savedHint=cardHint(data.saved_card);
            q('[data-stripe-summary]').textContent=savedHint
                ? `Saved payment method: ${savedHint}. ${data.message||'Confirm your payment below.'}`
                : (data.message||'Confirm your payment below.');
            if(data.saved_payment_method){
                await confirmSavedCard(data);
                return;
            }
            await mountPaymentElement(data);
        } catch(e){
            q('[data-stripe-loading]').hidden=true;
            setConfirmBusy(false);
            setStatus(e.message||'Checkout could not be prepared.','error');
        }
    }
    q('[data-stripe-confirm]').addEventListener('click', async () => {
        if(!stripe||!elements||confirming)return;
        setConfirmBusy(true,'Processing…');
        setStatus('');
        try {
            const result=await stripe.confirmPayment({elements,redirect:'if_required'});
            if(result.error) throw result.error;
            setStatus('Payment submitted. Confirming your account…','success');
            poll();
        } catch(e){
            setConfirmBusy(false);
            setStatus(e.message||'Payment could not be completed.','error');
        }
    });
    document.addEventListener('click', e => {
        const a=e.target.closest('[data-plyrcard-my-journey-open],[data-plyrcard-jumpstart-open],[data-plyrcard-amplify-open]');
        if(!a)return;
        e.preventDefault();
        open(a.hasAttribute('data-plyrcard-jumpstart-open')?'jumpstart':a.hasAttribute('data-plyrcard-amplify-open')?'amplify':'my-journey');
    });
})();
</script>