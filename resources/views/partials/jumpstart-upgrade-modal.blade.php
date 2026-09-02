@once
<div class="plyr-amplify-modal" data-plyrcard-jumpstart-modal data-start-url="{{ route('billing.jumpstart.start') }}" data-status-url="{{ route('billing.jumpstart.status') }}" data-billing-url="{{ route('locker-room.billing.update') }}" hidden>
    <div class="plyr-amplify-backdrop" data-plyrcard-jumpstart-close></div>
    <div class="plyr-amplify-panel" role="dialog" aria-modal="true" aria-label="Jumpstart secure checkout">
        <button type="button" class="plyr-amplify-close" data-plyrcard-jumpstart-close aria-label="Close">&times;</button>
        <header class="plyr-amplify-head"><span class="plyr-amplify-kicker">JUMPSTART</span><h2>Add Jumpstart</h2><p>Jumpstart is a one-time service extension of My Journey. If you are not subscribed yet, My Journey starts in the same checkout.</p></header>
        <div class="plyr-amplify-body">
            <div class="plyr-amplify-state" data-plyrcard-jumpstart-loading><div class="plyr-amplify-state-inner"><strong>Preparing secure checkout…</strong></div></div>
            <div class="plyr-amplify-state" data-plyrcard-jumpstart-success hidden><div class="plyr-amplify-state-inner"><strong>Jumpstart confirmed</strong><span>Your one-time recruiting push is active.</span></div></div>
            <div class="plyr-amplify-state" data-plyrcard-jumpstart-error hidden><div class="plyr-amplify-state-inner plyr-amplify-error"><strong>Checkout could not be prepared</strong><span data-plyrcard-jumpstart-error-copy>Please try again.</span><button type="button" class="plyr-amplify-retry" data-plyrcard-jumpstart-retry>Try Again</button></div></div>

                <div class="plyr-amplify-state plyr-billing-recovery" data-plyrcard-jumpstart-billing hidden>
                    <form class="plyr-billing-form" data-plyrcard-jumpstart-billing-form>
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
                        <div class="plyr-billing-error" data-plyrcard-jumpstart-billing-error hidden></div>
                        <button class="plyr-amplify-retry" type="submit">Save &amp; Continue</button>
                    </form>
                </div>
            <iframe class="plyr-amplify-frame" data-plyrcard-jumpstart-frame src="about:blank" scrolling="no" id="plyrcard-jumpstart-checkout" title="Jumpstart secure checkout" data-cookie-consent="true" data-cookie-consent-provider="auto" hidden></iframe>
        </div>
        <footer class="plyr-amplify-foot"><span data-plyrcard-jumpstart-status>Payment confirmation is checked automatically.</span><strong>Secure checkout</strong></footer>
    </div>
</div>
<style>
.plyr-billing-recovery{padding:22px!important;align-items:start!important}.plyr-billing-form{width:min(620px,100%);margin:0 auto;text-align:left}.plyr-billing-title strong{font-size:18px}.plyr-billing-title span{margin-top:5px!important}.plyr-billing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:18px}.plyr-billing-grid label{display:grid;gap:5px;font-size:12px;font-weight:800;color:#344054}.plyr-billing-grid label.full{grid-column:1/-1}.plyr-billing-grid em{font-weight:500;color:#98a2b3}.plyr-billing-grid input{width:100%;box-sizing:border-box;border:1px solid #d0d5dd;border-radius:10px;background:#fff;padding:11px 12px;color:#101828;font:inherit;outline:none}.plyr-billing-grid input:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.12)}.plyr-billing-error{margin-top:12px;padding:10px 12px;border-radius:9px;background:#fef3f2;color:#b42318;font-size:12px}.plyr-billing-form>.plyr-amplify-retry{display:block;margin:16px 0 0 auto}@media(max-width:640px){.plyr-billing-grid{grid-template-columns:1fr}.plyr-billing-grid label.full{grid-column:auto}}
</style>
<script src="https://systems.plyrcard.com/js/form_embed.js" defer></script>
<script>
(()=>{
if(window.__plyrcardJumpstartCheckoutV1099)return;window.__plyrcardJumpstartCheckoutV1099=true;
const m=document.querySelector('[data-plyrcard-jumpstart-modal]');if(!m)return;
const f=m.querySelector('[data-plyrcard-jumpstart-frame]'),loading=m.querySelector('[data-plyrcard-jumpstart-loading]'),success=m.querySelector('[data-plyrcard-jumpstart-success]'),error=m.querySelector('[data-plyrcard-jumpstart-error]'),errorCopy=m.querySelector('[data-plyrcard-jumpstart-error-copy]'),billing=m.querySelector('[data-plyrcard-jumpstart-billing]'),billingForm=m.querySelector('[data-plyrcard-jumpstart-billing-form]'),billingError=m.querySelector('[data-plyrcard-jumpstart-billing-error]'),status=m.querySelector('[data-plyrcard-jumpstart-status]');
let timer=null,started=0,busy=false;
const csrf=()=>document.querySelector('meta[name="csrf-token"]')?.content||'';
const show=t=>{[loading,success,error,billing,f].forEach(x=>{if(x)x.hidden=x!==t})};
const req=async(url,opt={})=>{const r=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest',...(opt.method&&opt.method!=='GET'?{'X-CSRF-TOKEN':csrf(),'Content-Type':'application/json'}:{})},...opt});const d=await r.json().catch(()=>({}));if(!r.ok||d.success===false){const e=new Error(d.message||'Unable to continue.');e.data=d;throw e}return d};
const fillBilling=d=>{const values=d?.billing||{};Object.entries(values).forEach(([k,v])=>{const el=billingForm?.elements?.namedItem(k);if(el&&v!=null)el.value=v});if(billingForm?.elements?.billing_country&&!billingForm.elements.billing_country.value)billingForm.elements.billing_country.value='US'};
const stop=()=>{if(timer)clearTimeout(timer);timer=null};const close=()=>{stop();m.hidden=true;document.documentElement.style.overflow=''};
async function poll(){if(m.hidden)return;if(Date.now()-started>12*60*1000){status.textContent='Checkout is still open. Complete payment to activate Jumpstart.';return}try{const d=await req(m.dataset.statusUrl);if(d.completed){stop();show(success);status.textContent='Payment confirmed. Updating your PLYRCARD…';window.dispatchEvent(new CustomEvent('plyrcard:jumpstart-upgraded',{detail:d}));setTimeout(()=>{if(!document.querySelector('[data-lr-drawer]'))window.location.reload()},900);return}status.textContent=d.message||'Waiting for payment confirmation…'}catch(_){status.textContent='Still checking payment confirmation…'}timer=setTimeout(poll,2500)}
async function start(){if(busy)return;busy=true;stop();m.hidden=false;document.documentElement.style.overflow='hidden';show(loading);status.textContent='Preparing checkout…';try{const d=await req(m.dataset.startUrl,{method:'POST',body:'{}'});if(d.completed){show(success);status.textContent=d.message||'Jumpstart is active.';return}if(!d.checkout_url)throw new Error(d.message||'Checkout is unavailable.');try{const checkoutUrl=new URL(d.checkout_url,window.location.origin);const pathParts=checkoutUrl.pathname.split('/').filter(Boolean);const surveyId=pathParts[pathParts.length-1]||'';if(surveyId)f.id=surveyId}catch(_){}f.src=d.checkout_url;show(f);started=Date.now();status.textContent=d.message||(d.display_due_today?`${d.display_due_today} due today.`:'Complete checkout to continue.');timer=setTimeout(poll,1800)}catch(e){if(['billing_profile_required','billing_contact_unavailable'].includes(e.data?.reason)||/billing information|billing profile|billing contact/i.test(String(e.message||''))){fillBilling(e.data);show(billing);status.textContent='Save your billing information, then checkout will continue automatically.'}else{show(error);errorCopy.textContent=e.message||'Please try again.';status.textContent='Checkout was not started.'}}finally{busy=false}}
billingForm?.addEventListener('submit',async e=>{e.preventDefault();billingError.hidden=true;const submit=billingForm.querySelector('button[type="submit"]');submit.disabled=true;submit.textContent='Saving…';try{const payload=Object.fromEntries(new FormData(billingForm).entries());const r=await fetch(m.dataset.billingUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf(),'Content-Type':'application/json'},body:JSON.stringify(payload)});const d=await r.json().catch(()=>({}));if(!r.ok){throw new Error(d.message||Object.values(d.errors||{}).flat()[0]||'Billing information could not be saved.')}await start()}catch(err){billingError.textContent=err.message;billingError.hidden=false}finally{submit.disabled=false;submit.textContent='Save & Continue'}});
document.addEventListener('click',e=>{if(e.target.closest('[data-plyrcard-jumpstart-open]')){e.preventDefault();start();return}if(e.target.closest('[data-plyrcard-jumpstart-close]')){close();return}if(e.target.closest('[data-plyrcard-jumpstart-retry]'))start()});
})();
</script>
@endonce