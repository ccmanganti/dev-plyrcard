{{-- Player-facing Recruiting Center + PLYR Points modules.
     This partial intentionally contains no admin controls or admin links. --}}
<section class="lr-view" data-lr-view="schools">
    <div class="lr-section">
        <div class="lr-card">
            <h3 class="lr-card-title">Discover Schools</h3>
            <p class="lr-card-copy">Search recruiting programs available to your player account.</p>
            <div class="lr-field" style="margin-top:12px;">
                <input class="lr-input" type="search" data-lrm-school-search placeholder="Search school, conference, division, city or state">
            </div>
        </div>
        <div data-lrm-schools><div class="lr-detail-empty">Loading schools…</div></div>
    </div>
</section>

<section class="lr-view" data-lr-view="coaches">
    <div class="lr-section">
        <div class="lr-card">
            <h3 class="lr-card-title">Coaches</h3>
            <p class="lr-card-copy">Find coaches and start recruiting outreach from your player account.</p>
            <div class="lr-field" style="margin-top:12px;">
                <input class="lr-input" type="search" data-lrm-coach-search placeholder="Search coach, school, email or title">
            </div>
        </div>
        <div data-lrm-coaches><div class="lr-detail-empty">Loading coaches…</div></div>
    </div>
</section>

<section class="lr-view" data-lr-view="favorites">
    <div class="lr-section">
        <div class="lr-card"><h3 class="lr-card-title">Favorite Schools</h3><p class="lr-card-copy">Schools you starred in your recruiting workspace.</p></div>
        <div data-lrm-favorites><div class="lr-detail-empty">Loading favorites…</div></div>
    </div>
</section>

<section class="lr-view" data-lr-view="lists">
    <div class="lr-section">
        <form class="lr-card" data-lrm-list-form>
            <h3 class="lr-card-title">My Lists</h3>
            <p class="lr-card-copy">Create focused recruiting lists and organize target schools.</p>
            <div class="lr-form-grid" style="margin-top:12px;">
                <div class="lr-field"><label>List Name</label><input class="lr-input" name="name" maxlength="80" placeholder="e.g. Top Choices" required></div>
                <div class="lr-field"><label>Color</label><input class="lr-input" name="color" type="color" value="#ff6338"></div>
            </div>
            <div class="lr-actions"><button class="lr-btn lr-btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Create List</button></div>
        </form>
        <div data-lrm-lists><div class="lr-detail-empty">Loading lists…</div></div>
    </div>
</section>

<section class="lr-view" data-lr-view="inbox">
    <div class="lr-section">
        <div class="lr-card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div><h3 class="lr-card-title">Inbox</h3><p class="lr-card-copy">Coach conversations from your recruiting email.</p></div>
                <button class="lr-btn" type="button" data-lrm-inbox-refresh aria-label="Refresh inbox"><i class="fa-solid fa-rotate"></i></button>
            </div>
            <div class="lr-field" style="margin-top:12px;"><input class="lr-input" type="search" data-lrm-inbox-search placeholder="Search conversations"></div>
        </div>
        <div data-lrm-inbox-list><div class="lr-detail-empty">Loading conversations…</div></div>
        <div class="lr-card" data-lrm-thread hidden></div>
    </div>
</section>

<section class="lr-view" data-lr-view="compose">
    <form class="lr-form" data-lrm-compose-form>
        <div class="lr-form-section">
            <h4>Recruiting Email</h4>
            <p class="lr-card-copy">Send player outreach to an individual coach or a recruiting list.</p>
            <div class="lr-form-grid">
                <div class="lr-field"><label>Send To</label><select class="lr-select" name="target_mode" data-lrm-compose-mode><option value="coach">Coach</option><option value="list">My List</option></select></div>
                <div class="lr-field" data-lrm-coach-wrap><label>Coach</label><select class="lr-select" name="coach_id" data-lrm-compose-coach><option value="">Choose coach</option></select></div>
                <div class="lr-field" data-lrm-list-wrap hidden><label>List</label><select class="lr-select" name="list_key" data-lrm-compose-list><option value="">Choose list</option></select></div>
                <div class="lr-field is-full"><label>Template</label><select class="lr-select" data-lrm-template><option value="">Start from scratch</option></select></div>
                <div class="lr-field is-full"><label>Subject</label><input class="lr-input" name="subject" maxlength="250" required></div>
                <div class="lr-field is-full"><label>Message</label><textarea class="lr-textarea" name="body" rows="10" required></textarea></div>
            </div>
            <div class="lr-actions"><button class="lr-btn lr-btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Send Email</button></div>
        </div>
    </form>
</section>

<section class="lr-view" data-lr-view="points">
    <div class="lr-section">
        <div class="lr-hero">
            <span class="lr-eyebrow">PLYR Points</span>
            <h3><span data-lrm-points-balance>0</span> points available</h3>
            <p class="lr-muted">Purchase points, request services and track your point activity.</p>
        </div>
        <div data-lrm-packs></div>
        <div class="lr-card">
            <h3 class="lr-card-title">Services</h3>
            <p class="lr-card-copy">Use points on services enabled by the PLYRCARD team.</p>
            <div data-lrm-services><div class="lr-detail-empty">Loading services…</div></div>
        </div>
        <div class="lr-card"><h3 class="lr-card-title">My Requests</h3><div data-lrm-requests><div class="lr-detail-empty">Loading requests…</div></div></div>
        <div class="lr-card"><h3 class="lr-card-title">Point Activity</h3><div data-lrm-history><div class="lr-detail-empty">Loading point activity…</div></div></div>
    </div>
</section>

<script>
(function () {
    var drawer = document.getElementById('plyrcard-action-drawer');
    if (!drawer || drawer.dataset.lrmReady === '1') return;
    drawer.dataset.lrmReady = '1';

    var data = {schools:[], coaches:[], favorites:[], lists:[], conversations:[], templates:[], points:null};
    var loaded = {schools:false, coaches:false, favorites:false, lists:false, inbox:false, compose:false, points:false};
    var searchTimer = null;

    function q(selector) { return drawer.querySelector(selector); }
    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>'"]/g, function (ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch];
        });
    }
    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var input = document.querySelector('input[name="_token"]');
        return meta ? meta.content : (input ? input.value : '');
    }
    async function api(url, options) {
        options = options || {};
        var headers = Object.assign({'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf()}, options.headers || {});
        if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        var response = await fetch(url, Object.assign({credentials:'same-origin'}, options, {headers:headers}));
        var json = {};
        try { json = await response.json(); } catch (_) {}
        if (!response.ok || json.success === false) {
            var validation = json.errors ? Object.values(json.errors).flat().join(' ') : '';
            throw new Error(validation || json.message || ('Request failed (' + response.status + ')'));
        }
        return json;
    }
    function toast(message, error) {
        var el = q('[data-lr-toast]');
        if (!el) return;
        el.textContent = message || (error ? 'Something went wrong.' : 'Saved.');
        el.style.background = error ? '#991b1b' : '#111827';
        el.classList.add('is-showing');
        window.setTimeout(function () { el.classList.remove('is-showing'); }, 3200);
    }
    function busy(form, active, label) {
        var button = form && form.querySelector('button[type="submit"]');
        if (!button) return;
        if (active) {
            if (!button.dataset.lrmLabel) button.dataset.lrmLabel = button.innerHTML;
            button.disabled = true;
            button.textContent = label || 'Working…';
        } else {
            button.disabled = false;
            if (button.dataset.lrmLabel) button.innerHTML = button.dataset.lrmLabel;
        }
    }
    function money(cents) {
        return new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(Number(cents || 0) / 100);
    }
    function schoolCard(row) {
        var ref = row.id || row.reference || row.business_id || '';
        var favored = !!(row.is_favorite || row.favorite);
        var location = [row.city,row.state].filter(Boolean).join(', ');
        var meta = [row.division,row.conference,location].filter(Boolean).join(' · ');
        return '<article class="lr-card">' +
            '<div style="display:flex;gap:12px;justify-content:space-between;align-items:start;">' +
                '<div><h3 class="lr-card-title">' + esc(row.name || row.school_name || 'School') + '</h3><p class="lr-card-copy">' + esc(meta) + '</p></div>' +
                '<button class="lr-btn" type="button" data-lrm-favorite="' + esc(ref) + '" data-favorite="' + (favored ? '1':'0') + '" aria-label="Favorite school"><i class="fa-' + (favored ? 'solid':'regular') + ' fa-star"></i></button>' +
            '</div>' +
            (ref ? '<div class="lr-actions"><button class="lr-btn" type="button" data-lr-dashboard-school="' + esc(ref) + '">View School</button></div>' : '') +
        '</article>';
    }
    function coachCard(row) {
        var id = row.id || row.contact_id || row.ghl_contact_id || '';
        var school = row.school || row.school_name || '';
        return '<article class="lr-card"><div style="display:flex;gap:12px;justify-content:space-between;align-items:start;">' +
            '<div><h3 class="lr-card-title">' + esc(row.name || 'Coach') + '</h3><p class="lr-card-copy">' + esc(row.title || 'Coach') + (school ? ' · ' + esc(school) : '') + '</p>' + (row.email ? '<p class="lr-card-copy">' + esc(row.email) + '</p>' : '') + '</div>' +
            '<button class="lr-btn lr-btn-primary" type="button" data-lrm-compose-coach="' + esc(id) + '">Email</button></div></article>';
    }
    function renderSchools() {
        var holder=q('[data-lrm-schools]'); if(!holder)return;
        var term=String(q('[data-lrm-school-search]') ? q('[data-lrm-school-search]').value : '').toLowerCase().trim();
        var rows=(data.schools||[]).filter(function(row){return !term || JSON.stringify(row).toLowerCase().indexOf(term)!==-1;});
        holder.innerHTML=rows.length?rows.map(schoolCard).join(''):'<div class="lr-detail-empty">No schools matched your search.</div>';
    }
    function renderCoaches() {
        var holder=q('[data-lrm-coaches]'); if(!holder)return;
        var term=String(q('[data-lrm-coach-search]') ? q('[data-lrm-coach-search]').value : '').toLowerCase().trim();
        var rows=(data.coaches||[]).filter(function(row){return !term || JSON.stringify(row).toLowerCase().indexOf(term)!==-1;});
        holder.innerHTML=rows.length?rows.map(coachCard).join(''):'<div class="lr-detail-empty">No coaches matched your search.</div>';
    }
    function renderFavorites() {
        var holder=q('[data-lrm-favorites]');if(!holder)return;
        holder.innerHTML=(data.favorites||[]).length?data.favorites.map(schoolCard).join(''):'<div class="lr-detail-empty">You have not favorited any schools yet.</div>';
    }
    function renderLists() {
        var holder=q('[data-lrm-lists]');if(!holder)return;
        holder.innerHTML=(data.lists||[]).length?data.lists.map(function(row){
            var schools=Array.isArray(row.schools)?row.schools:[];
            var count=Number(row.school_count == null ? schools.length : row.school_count);
            return '<article class="lr-card"><div style="display:flex;align-items:start;justify-content:space-between;gap:10px;"><div><h3 class="lr-card-title"><span style="display:inline-block;width:9px;height:9px;border-radius:99px;background:' + esc(row.color||'#ff6338') + ';margin-right:6px;"></span>' + esc(row.name||'List') + '</h3><p class="lr-card-copy">' + count.toLocaleString() + ' schools</p></div><button class="lr-btn" type="button" data-lrm-list-delete="' + esc(row.key||'') + '"><i class="fa-solid fa-trash"></i></button></div>' +
                schools.slice(0,8).map(function(s){return '<div style="padding:8px 0;border-top:1px solid #eef2f6;font-size:12px;font-weight:700;">'+esc(s.name||'School')+'</div>';}).join('') +
            '</article>';
        }).join(''):'<div class="lr-detail-empty">No recruiting lists yet.</div>';
    }
    function renderInbox() {
        var holder=q('[data-lrm-inbox-list]');if(!holder)return;
        holder.innerHTML=(data.conversations||[]).length?data.conversations.map(function(row){
            var name=row.contact_name||row.name||'Coach';
            var school=row.school||row.company_name||'';
            var last=row.last_message||row.snippet||'';
            return '<button class="lr-card" style="width:100%;text-align:left;" type="button" data-lrm-conversation="'+esc(row.id||'')+'"><h3 class="lr-card-title">'+esc(name)+'</h3><p class="lr-card-copy">'+esc(school)+(last?' · '+esc(last):'')+'</p>'+(Number(row.unread_count||0)>0?'<span class="lr-chip">'+Number(row.unread_count)+' unread</span>':'')+'</button>';
        }).join(''):'<div class="lr-detail-empty">No conversations yet.</div>';
    }
    function renderCompose() {
        var coach=q('[data-lrm-compose-coach]'), list=q('[data-lrm-compose-list]'), template=q('[data-lrm-template]');
        if(coach){var cv=coach.value;coach.innerHTML='<option value="">Choose coach</option>'+(data.coaches||[]).filter(function(r){return !!r.email;}).map(function(r){return '<option value="'+esc(r.id||r.contact_id||r.ghl_contact_id||'')+'">'+esc(r.name||'Coach')+' — '+esc(r.school||r.school_name||'')+'</option>';}).join('');if(cv)coach.value=cv;}
        if(list){var lv=list.value;list.innerHTML='<option value="">Choose list</option>'+(data.lists||[]).map(function(r){return '<option value="'+esc(r.key||'')+'">'+esc(r.name||'List')+'</option>';}).join('');if(lv)list.value=lv;}
        if(template){var tv=template.value;template.innerHTML='<option value="">Start from scratch</option>'+(data.templates||[]).map(function(r){return '<option value="'+esc(r.id||'')+'">'+esc(r.name||'Template')+'</option>';}).join('');if(tv)template.value=tv;}
    }
    function renderPoints() {
        var p=data.points||{}, balance=Number(p.balance||0), balanceEl=q('[data-lrm-points-balance]');
        if(balanceEl)balanceEl.textContent=balance.toLocaleString();
        var packs=q('[data-lrm-packs]');
        if(packs){
            if(p.can_buy_points){
                var rows=Array.isArray(p.point_packs)?p.point_packs:[];
                packs.innerHTML='<div class="lr-card"><h3 class="lr-card-title">Buy Points</h3><div class="lr-plan-grid">'+rows.map(function(row){
                    return '<article class="lr-plan-card"><div class="lr-plan-name">'+esc(row.label)+'</div><div class="lr-plan-price">'+money(row.price_cents)+'</div><p class="lr-card-copy">'+Number(row.points||0).toLocaleString()+' PLYR Points</p><button class="lr-btn lr-btn-primary" type="button" data-lrm-point-pack="'+esc(row.key)+'">Buy Points</button></article>';
                }).join('')+'</div>'+(p.custom_purchase&&p.custom_purchase.enabled?'<div class="lr-field" style="margin-top:12px;"><label>Custom Points</label><div style="display:flex;gap:8px;"><input class="lr-input" type="number" data-lrm-custom-points min="'+Number(p.custom_purchase.min_points||1)+'" max="'+Number(p.custom_purchase.max_points||1000)+'" value="'+Number(p.custom_purchase.min_points||1)+'"><button class="lr-btn" type="button" data-lrm-custom-buy>Buy</button></div></div>':'')+'</div>';
            } else {
                packs.innerHTML='<div class="lr-card"><h3 class="lr-card-title">Buy Points</h3><p class="lr-card-copy">Point packs are available with an active My Journey membership.</p><div class="lr-actions"><button class="lr-btn lr-btn-primary" type="button" data-lr-nav="upgrade">Get My Journey</button></div></div>';
            }
        }
        var services=q('[data-lrm-services]');
        if(services)services.innerHTML=(p.catalog||[]).map(function(s){
            var enough=balance>=Number(s.points||0);
            return '<article style="padding:12px 0;border-top:1px solid #eef2f6;"><div style="display:flex;justify-content:space-between;gap:12px;"><div><strong>'+esc(s.name)+'</strong><div class="lr-card-copy">'+esc(s.description||'')+'</div></div><strong>'+Number(s.points||0)+' pts</strong></div>' +
                ((s.inclusions||[]).length?'<ul class="lr-plan-list">'+s.inclusions.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul>':'') +
                ((s.samples||[]).length?'<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin-top:10px;">'+s.samples.slice(0,6).map(function(sample){if(sample.type==='youtube')return '<a href="'+esc(sample.url||sample.embed_url||'#')+'" target="_blank" rel="noopener" style="aspect-ratio:1/1;border-radius:10px;background:#111827;color:#fff;display:grid;place-items:center;text-decoration:none;"><i class="fa-brands fa-youtube"></i></a>';if(sample.type==='image')return '<a href="'+esc(sample.url||'#')+'" target="_blank" rel="noopener" style="border-radius:10px;overflow:hidden;background:#f3f4f6;display:block;"><img src="'+esc(sample.url||'')+'" alt="Service sample" style="width:100%;height:auto;max-height:130px;object-fit:contain;display:block;"></a>';return '<a href="'+esc(sample.url||'#')+'" target="_blank" rel="noopener" class="lr-btn" style="min-height:60px;">View Sample</a>';}).join('')+'</div>':'') +
                '<div class="lr-actions"><button class="lr-btn lr-btn-primary" type="button" data-lrm-service="'+esc(s.key)+'" data-points="'+Number(s.points||0)+'" '+(enough?'':'disabled')+'>'+(enough?esc(s.cta||'Request Service'):'Not enough points')+'</button></div></article>';
        }).join('')||'<div class="lr-detail-empty">No point services are currently enabled.</div>';
        var requests=q('[data-lrm-requests]');
        if(requests)requests.innerHTML=(p.requests||[]).map(function(r){
            return '<div style="padding:10px 0;border-top:1px solid #eef2f6;"><div style="display:flex;justify-content:space-between;gap:10px;"><strong>'+esc(r.item_name||'Service Request')+'</strong><span class="lr-chip">'+esc(r.status_label||r.status||'Submitted')+'</span></div><div class="lr-card-copy">'+Number(r.points_spent||0)+' points'+(r.delivery_url?' · <a href="'+esc(r.delivery_url)+'" target="_blank" rel="noopener">Open delivery</a>':'')+'</div></div>';
        }).join('')||'<div class="lr-detail-empty">No service requests yet.</div>';
        var history=q('[data-lrm-history]');
        if(history)history.innerHTML=(p.transactions||[]).map(function(r){
            var points=Number(r.points||0);
            var date=r.created_at?new Date(r.created_at).toLocaleDateString():'';
            return '<div style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid #eef2f6;"><div><strong>'+esc(r.reason||r.type||'Point activity')+'</strong><div class="lr-card-copy">'+esc(date)+'</div></div><strong>'+(points>0?'+':'')+points+'</strong></div>';
        }).join('')||'<div class="lr-detail-empty">No point activity yet.</div>';
    }

    async function loadSchools(force){if(loaded.schools&&!force)return renderSchools();try{var j=await api(drawer.dataset.recruitingSchoolsUrl);data.schools=j.items||[];data.lists=j.lists||data.lists;loaded.schools=true;renderSchools();}catch(e){q('[data-lrm-schools]').innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>';}}
    async function loadCoaches(force){if(loaded.coaches&&!force)return renderCoaches();try{var j=await api(drawer.dataset.recruitingCoachesUrl);data.coaches=j.items||[];loaded.coaches=true;renderCoaches();}catch(e){q('[data-lrm-coaches]').innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>';}}
    async function loadFavorites(force){if(loaded.favorites&&!force)return renderFavorites();try{var j=await api(drawer.dataset.recruitingFavoritesUrl);data.favorites=j.items||[];data.lists=j.lists||data.lists;loaded.favorites=true;renderFavorites();}catch(e){q('[data-lrm-favorites]').innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>';}}
    async function loadLists(force){if(loaded.lists&&!force)return renderLists();try{var j=await api(drawer.dataset.recruitingListsUrl);data.lists=j.items||[];loaded.lists=true;renderLists();}catch(e){q('[data-lrm-lists]').innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>';}}
    async function loadInbox(force){if(loaded.inbox&&!force)return renderInbox();try{var term=encodeURIComponent(q('[data-lrm-inbox-search]')?q('[data-lrm-inbox-search]').value:'');var j=await api(drawer.dataset.recruitingConversationsUrl+'?q='+term+'&limit=50');data.conversations=j.conversations||[];loaded.inbox=true;renderInbox();}catch(e){q('[data-lrm-inbox-list]').innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>';}}
    async function loadCompose(force){if(loaded.compose&&!force)return renderCompose();try{var results=await Promise.all([loaded.coaches?Promise.resolve({items:data.coaches}):api(drawer.dataset.recruitingCoachesUrl),loaded.lists?Promise.resolve({items:data.lists}):api(drawer.dataset.recruitingListsUrl),api(drawer.dataset.recruitingTemplatesUrl)]);data.coaches=results[0].items||[];data.lists=results[1].items||[];data.templates=results[2].items||[];loaded.coaches=true;loaded.lists=true;loaded.compose=true;renderCompose();}catch(e){toast(e.message,true);}}
    async function loadPoints(force){if(loaded.points&&!force)return renderPoints();try{data.points=await api(drawer.dataset.pointsUrl);loaded.points=true;renderPoints();}catch(e){toast(e.message,true);}}

    async function openConversation(id) {
        var thread=q('[data-lrm-thread]');if(!thread)return;
        thread.hidden=false;thread.innerHTML='<div class="lr-detail-empty">Loading thread…</div>';
        try {
            var j=await api(drawer.dataset.recruitingConversationsUrl+'/'+encodeURIComponent(id)+'?limit=50');
            var convo=(data.conversations||[]).find(function(row){return String(row.id)===String(id);})||{};
            var messages=(j.messages||[]).map(function(m){
                var outgoing=String(m.direction||'').toLowerCase()==='outbound';
                return '<div style="padding:10px 12px;border-radius:12px;background:'+(outgoing?'#fff3ee':'#f8fafc')+';"><strong style="font-size:10px;text-transform:uppercase;">'+esc(m.direction||'message')+'</strong><div style="font-size:12px;margin-top:4px;">'+esc(m.body||m.message||m.text||'')+'</div></div>';
            }).join('');
            thread.innerHTML='<div style="display:flex;justify-content:space-between;align-items:start;gap:10px;"><div><h3 class="lr-card-title">'+esc(convo.contact_name||convo.name||'Conversation')+'</h3><p class="lr-card-copy">'+esc(convo.school||convo.company_name||'')+'</p></div><button class="lr-btn lr-btn-primary" type="button" data-lrm-reply="'+esc(id)+'">Reply</button></div><div style="display:grid;gap:8px;margin-top:12px;">'+(messages||'<div class="lr-detail-empty">No messages in this thread.</div>')+'</div>';
            thread.scrollIntoView({behavior:'auto',block:'start'});
        } catch(e) { thread.innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>'; }
    }

    function onView(view) {
        if(view==='schools')loadSchools(false);
        else if(view==='coaches')loadCoaches(false);
        else if(view==='favorites')loadFavorites(false);
        else if(view==='lists')loadLists(false);
        else if(view==='inbox')loadInbox(false);
        else if(view==='compose')loadCompose(false);
        else if(view==='points')loadPoints(false);
    }

    document.addEventListener('click',function(event){
        var nav=event.target.closest('[data-lr-nav]');
        if(nav&&drawer.contains(nav))window.setTimeout(function(){onView(nav.dataset.lrNav);},0);

        var favorite=event.target.closest('[data-lrm-favorite]');
        if(favorite&&drawer.contains(favorite)){event.preventDefault();event.stopPropagation();var want=favorite.dataset.favorite!=='1';api(drawer.dataset.dashboardSchoolFavoriteUrl,{method:'POST',body:{school_id:favorite.dataset.lrmFavorite,favorite:want}}).then(function(){loaded.schools=false;loaded.favorites=false;loadSchools(true);if(q('[data-lr-view="favorites"]').classList.contains('is-active'))loadFavorites(true);toast(want?'School favorited.':'Favorite removed.');}).catch(function(e){toast(e.message,true);});return;}

        var composeCoach=event.target.closest('[data-lrm-compose-coach]');
        if(composeCoach&&drawer.contains(composeCoach)&&composeCoach.tagName!=='SELECT'){event.preventDefault();var composeNav=drawer.querySelector('[data-lr-nav="compose"]');if(composeNav)composeNav.click();loadCompose(false).then(function(){var select=q('[data-lrm-compose-coach]');if(select)select.value=composeCoach.dataset.lrmComposeCoach;});return;}

        var deleteList=event.target.closest('[data-lrm-list-delete]');
        if(deleteList&&drawer.contains(deleteList)){event.preventDefault();if(!confirm('Delete this recruiting list?'))return;api(drawer.dataset.recruitingListsUrl+'/'+encodeURIComponent(deleteList.dataset.lrmListDelete),{method:'DELETE'}).then(function(j){data.lists=j.items||[];renderLists();toast('List deleted.');}).catch(function(e){toast(e.message,true);});return;}

        var conversation=event.target.closest('[data-lrm-conversation]');
        if(conversation&&drawer.contains(conversation)){event.preventDefault();openConversation(conversation.dataset.lrmConversation);return;}

        var reply=event.target.closest('[data-lrm-reply]');
        if(reply&&drawer.contains(reply)){event.preventDefault();var composeNav2=drawer.querySelector('[data-lr-nav="compose"]');if(composeNav2)composeNav2.click();loadCompose(false).then(function(){var form=q('[data-lrm-compose-form]');if(form)form.dataset.conversationId=reply.dataset.lrmReply;});return;}

        var refresh=event.target.closest('[data-lrm-inbox-refresh]');
        if(refresh&&drawer.contains(refresh)){event.preventDefault();loaded.inbox=false;loadInbox(true);return;}

        var pack=event.target.closest('[data-lrm-point-pack]');
        if(pack&&drawer.contains(pack)){event.preventDefault();document.dispatchEvent(new CustomEvent('plyrcard:locker-point-pack',{detail:{pack:pack.dataset.lrmPointPack}}));return;}

        var custom=event.target.closest('[data-lrm-custom-buy]');
        if(custom&&drawer.contains(custom)){event.preventDefault();var p=data.points||{},cfg=p.custom_purchase||{},input=q('[data-lrm-custom-points]'),amount=Math.round(Number(input?input.value:0)),min=Number(cfg.min_points||1),max=Number(cfg.max_points||1000);if(!Number.isFinite(amount)||amount<min||amount>max){toast('Choose between '+min+' and '+max+' points.',true);return;}document.dispatchEvent(new CustomEvent('plyrcard:locker-point-pack',{detail:{pack:'custom-'+amount}}));return;}

        var service=event.target.closest('[data-lrm-service]');
        if(service&&drawer.contains(service)){event.preventDefault();var p2=data.points||{},cost=Number(service.dataset.points||0);if(Number(p2.balance||0)<cost){toast('You do not have enough points.',true);return;}if(!confirm('Use '+cost+' points for this service?'))return;var token='locker-'+Date.now()+'-'+Math.random().toString(36).slice(2,8);api(drawer.dataset.creditUseUrl,{method:'POST',body:{request_token:token,confirm_spend:1,items:[{selected:1,item_key:service.dataset.lrmService,quantity:1,rush:0,notes:'Requested from Locker Room'}]}}).then(function(){loaded.points=false;loadPoints(true);toast('Service requested with PLYR Points.');}).catch(function(e){toast(e.message,true);});return;}
    },true);

    document.addEventListener('plyrcard:points-refresh',function(){loaded.points=false;loadPoints(true);});

    document.addEventListener('input',function(event){
        if(event.target.matches('[data-lrm-school-search]'))renderSchools();
        if(event.target.matches('[data-lrm-coach-search]'))renderCoaches();
        if(event.target.matches('[data-lrm-inbox-search]')){clearTimeout(searchTimer);searchTimer=setTimeout(function(){loaded.inbox=false;loadInbox(true);},350);}
    },true);

    var listForm=q('[data-lrm-list-form]');
    if(listForm)listForm.addEventListener('submit',async function(event){event.preventDefault();busy(listForm,true,'Creating…');try{var j=await api(drawer.dataset.recruitingListsUrl,{method:'POST',body:new FormData(listForm)});data.lists=j.items||[];loaded.lists=true;listForm.reset();var color=listForm.querySelector('[name="color"]');if(color)color.value='#ff6338';renderLists();toast('List created.');}catch(e){toast(e.message,true);}finally{busy(listForm,false);}});

    var mode=q('[data-lrm-compose-mode]');
    if(mode)mode.addEventListener('change',function(){var list=mode.value==='list';q('[data-lrm-coach-wrap]').hidden=list;q('[data-lrm-list-wrap]').hidden=!list;});

    var template=q('[data-lrm-template]');
    if(template)template.addEventListener('change',function(){var row=(data.templates||[]).find(function(t){return String(t.id)===String(template.value);});if(!row)return;var form=q('[data-lrm-compose-form]');if(!form)return;form.querySelector('[name="subject"]').value=row.subject||'';form.querySelector('[name="body"]').value=String(row.body_html||'').replace(/<br\s*\/?>/gi,'\n').replace(/<\/p>/gi,'\n\n').replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').trim();});

    var composeForm=q('[data-lrm-compose-form]');
    if(composeForm)composeForm.addEventListener('submit',async function(event){
        event.preventDefault();busy(composeForm,true,'Sending…');
        var fd=new FormData(composeForm);
        var body={target_mode:fd.get('target_mode'),coach_id:fd.get('coach_id'),list_key:fd.get('list_key'),subject:fd.get('subject'),body:fd.get('body')};
        if(composeForm.dataset.conversationId){body.target_mode='conversation';body.conversation_id=composeForm.dataset.conversationId;}
        try{var j=await api(drawer.dataset.recruitingEmailUrl,{method:'POST',body:body});toast(j.message||'Recruiting email sent.');composeForm.dataset.conversationId='';composeForm.reset();q('[data-lrm-list-wrap]').hidden=true;q('[data-lrm-coach-wrap]').hidden=false;loaded.inbox=false;}catch(e){toast(e.message,true);}finally{busy(composeForm,false);}
    });
})();
</script>
