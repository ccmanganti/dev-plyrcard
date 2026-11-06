{{-- Player-facing Recruiting Center + PLYR Points.
     Mirrors the Admin Recruiting Center interaction model without exposing admin controls. --}}
<style>
    #plyrcard-action-drawer .lrm-toolbar{display:grid;gap:10px;padding:14px;border:1px solid #e5e7eb;border-radius:16px;background:#fff;box-shadow:0 10px 26px rgba(15,23,42,.05)}
    #plyrcard-action-drawer .lrm-toolbar-top{display:flex;align-items:flex-end;justify-content:space-between;gap:12px}
    #plyrcard-action-drawer .lrm-toolbar-top h3{margin:0;font-size:20px;line-height:1.1;font-weight:900;letter-spacing:-.03em}
    #plyrcard-action-drawer .lrm-toolbar-top p{margin:5px 0 0;color:#667085;font-size:12px;line-height:1.4}
    #plyrcard-action-drawer .lrm-search-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}
    #plyrcard-action-drawer .lrm-filter-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr)) auto;gap:8px;align-items:center}
    #plyrcard-action-drawer .lrm-segment{display:inline-flex;gap:3px;padding:3px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc}
    #plyrcard-action-drawer .lrm-segment button{width:34px;height:32px;border:0;border-radius:8px;background:transparent;color:#667085;cursor:pointer}
    #plyrcard-action-drawer .lrm-segment button.is-active{background:#fff;color:#f0522b;box-shadow:0 4px 12px rgba(15,23,42,.08)}
    #plyrcard-action-drawer .lrm-school-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    #plyrcard-action-drawer .lrm-school-card{position:relative;border:1px solid #e5e7eb;border-radius:14px;background:#fff;padding:12px;min-width:0;box-shadow:0 8px 20px rgba(15,23,42,.04)}
    #plyrcard-action-drawer .lrm-school-card:hover{border-color:rgba(240,82,43,.35);box-shadow:0 13px 28px rgba(15,23,42,.07)}
    #plyrcard-action-drawer .lrm-school-main{width:100%;border:0;background:transparent;padding:0;text-align:left;cursor:pointer;display:grid;grid-template-columns:44px minmax(0,1fr);gap:9px;align-items:center}
    #plyrcard-action-drawer .lrm-school-logo{width:44px;height:44px;border-radius:11px;background:#f3f4f6;display:grid;place-items:center;overflow:hidden;font-size:11px;font-weight:900;color:#475467}
    #plyrcard-action-drawer .lrm-school-logo img{width:100%;height:100%;object-fit:contain;padding:5px;background:#fff}
    #plyrcard-action-drawer .lrm-school-copy{min-width:0}
    #plyrcard-action-drawer .lrm-school-copy strong{display:block;color:#101828;font-size:12px;font-weight:850;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #plyrcard-action-drawer .lrm-school-copy small{display:block;margin-top:3px;color:#667085;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #plyrcard-action-drawer .lrm-school-foot{display:flex;align-items:center;justify-content:space-between;gap:7px;margin-top:10px;padding-top:9px;border-top:1px solid #eef2f6}
    #plyrcard-action-drawer .lrm-pill{display:inline-flex;align-items:center;min-height:24px;padding:0 8px;border-radius:999px;background:#f8fafc;color:#475467;font-size:9px;font-weight:800}
    #plyrcard-action-drawer .lrm-icon-btn{width:30px;height:30px;border:1px solid #e5e7eb;border-radius:9px;background:#fff;color:#667085;display:grid;place-items:center;cursor:pointer}
    #plyrcard-action-drawer .lrm-icon-btn.is-active{color:#f0522b;border-color:rgba(240,82,43,.35);background:#fff5f1}
    #plyrcard-action-drawer .lrm-school-list{display:grid;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;background:#fff}
    #plyrcard-action-drawer .lrm-school-list-row{display:grid;grid-template-columns:minmax(160px,1.35fr) minmax(110px,1fr) minmax(110px,1fr) 78px 38px;gap:10px;align-items:center;padding:10px 12px;border-top:1px solid #eef2f6}
    #plyrcard-action-drawer .lrm-school-list-row:first-child{border-top:0}
    #plyrcard-action-drawer .lrm-school-list-row>span{min-width:0;font-size:10px;color:#667085;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    #plyrcard-action-drawer .lrm-list-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
    #plyrcard-action-drawer .lrm-list-card{border:1px solid #e5e7eb;border-radius:16px;background:#fff;padding:13px;box-shadow:0 8px 20px rgba(15,23,42,.04);min-width:0}
    #plyrcard-action-drawer .lrm-list-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
    #plyrcard-action-drawer .lrm-list-title{display:flex;align-items:center;gap:8px;min-width:0}
    #plyrcard-action-drawer .lrm-list-dot{width:9px;height:9px;border-radius:999px;flex:0 0 auto}
    #plyrcard-action-drawer .lrm-list-title strong{font-size:13px;font-weight:850;color:#101828;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #plyrcard-action-drawer .lrm-school-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;max-height:116px;overflow:auto}
    #plyrcard-action-drawer .lrm-school-chip{border:1px solid #e5e7eb;border-radius:9px;background:#f8fafc;padding:5px 7px;display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:750;color:#344054;cursor:pointer;max-width:100%}
    #plyrcard-action-drawer .lrm-school-chip span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    #plyrcard-action-drawer .lrm-list-add-panel{margin-top:9px;padding:9px;border:1px dashed #d0d5dd;border-radius:11px;background:#fafafa}
    #plyrcard-action-drawer .lrm-list-search-results{display:grid;gap:5px;margin-top:7px;max-height:150px;overflow:auto}
    #plyrcard-action-drawer .lrm-list-result{width:100%;border:0;border-radius:9px;background:#fff;padding:8px;display:flex;align-items:center;justify-content:space-between;gap:8px;text-align:left;cursor:pointer}
    #plyrcard-action-drawer .lrm-inbox-shell{display:block}
    #plyrcard-action-drawer .lrm-nested-window{display:none}
    #plyrcard-action-drawer .lrm-nested-window.is-active{display:block}
    #plyrcard-action-drawer .lrm-nested-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}
    #plyrcard-action-drawer .lrm-nested-back{display:inline-flex;align-items:center;gap:6px;border:0;background:transparent;color:#344054;font-size:11px;font-weight:850;padding:5px 0;cursor:pointer}
    #plyrcard-action-drawer .lrm-nav-tiles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
    #plyrcard-action-drawer .lrm-nav-tile{border:1px solid #e5e7eb;border-radius:15px;background:#fff;padding:13px;text-align:left;cursor:pointer;min-height:92px;display:flex;flex-direction:column;justify-content:space-between;gap:8px;box-shadow:0 6px 16px rgba(15,23,42,.035)}
    #plyrcard-action-drawer .lrm-nav-tile:hover{border-color:rgba(240,82,43,.32);background:#fffaf8}
    #plyrcard-action-drawer .lrm-nav-tile strong{font-size:12px;color:#101828}
    #plyrcard-action-drawer .lrm-nav-tile small{font-size:10px;color:#667085;line-height:1.4}
    #plyrcard-action-drawer .lrm-nav-tile .value{font-size:20px;font-weight:950;color:#c2410c}
    #plyrcard-action-drawer .lrm-point-action-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
    #plyrcard-action-drawer .lrm-point-action-icon{width:34px;height:34px;border-radius:10px;background:#fff1eb;color:#c2410c;display:grid;place-items:center;font-size:13px}
    #plyrcard-action-drawer .lrm-point-action-arrow{color:#98a2b3;font-size:10px}
    #plyrcard-action-drawer .lrm-collapsible-list.is-minimized>*:nth-child(n+4){display:none}
    #plyrcard-action-drawer .lrm-activity-toggle{border:0;background:transparent;color:#f0522b;font-size:10px;font-weight:850;cursor:pointer}
    #plyrcard-action-drawer .lrm-inbox-list{display:grid;gap:0;max-height:58vh;overflow:auto;border:1px solid #e5e7eb;border-radius:15px;background:#fff}
    #plyrcard-action-drawer .lrm-inbox-view.is-maximized .lrm-inbox-list{max-height:76vh}
    #plyrcard-action-drawer .lrm-conversation{width:100%;border:0;border-bottom:1px solid #eef2f6;background:#fff;padding:11px 12px;text-align:left;cursor:pointer;display:grid;grid-template-columns:38px minmax(0,1fr) auto;gap:9px;align-items:start}
    #plyrcard-action-drawer .lrm-conversation:last-child{border-bottom:0}
    #plyrcard-action-drawer .lrm-conversation:hover{background:#fff8f5}
    #plyrcard-action-drawer .lrm-conversation.is-active{background:#fff3ed;box-shadow:inset 3px 0 #f0522b}
    #plyrcard-action-drawer .lrm-thread-logo{width:38px;height:38px;border:1px solid #e5e7eb;border-radius:999px;background:#fff;display:grid;place-items:center;overflow:hidden;color:#475467;font-size:10px;font-weight:900}
    #plyrcard-action-drawer .lrm-thread-logo img{width:100%;height:100%;object-fit:contain;padding:4px}
    #plyrcard-action-drawer .lrm-thread-main{min-width:0}
    #plyrcard-action-drawer .lrm-thread-name{font-size:11px;font-weight:900;color:#101828;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #plyrcard-action-drawer .lrm-thread-school{font-size:9px;color:#667085;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
    #plyrcard-action-drawer .lrm-thread-preview{font-size:10px;color:#667085;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:5px}
    #plyrcard-action-drawer .lrm-thread-side{display:grid;justify-items:end;gap:6px;min-width:52px}
    #plyrcard-action-drawer .lrm-thread-date{font-size:8px;color:#98a2b3;white-space:nowrap}
    #plyrcard-action-drawer .lrm-unread-dot{width:7px;height:7px;border-radius:999px;background:#f0522b}
    #plyrcard-action-drawer .lrm-thread{height:min(68vh,680px);min-height:420px;max-height:none;overflow:hidden;padding:0!important;position:relative;display:flex;flex-direction:column;background:#fff;isolation:isolate}
    #plyrcard-action-drawer .lrm-inbox-view.is-maximized .lrm-thread{height:calc(100vh - 210px);min-height:560px;max-height:820px}
    #plyrcard-action-drawer .lrm-thread-header{position:relative;z-index:3;flex:0 0 auto;background:#fff;border-bottom:1px solid #eef2f6;margin:0;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px}
    #plyrcard-action-drawer .lrm-thread-contact{display:flex;align-items:center;gap:8px;min-width:0}
    #plyrcard-action-drawer .lrm-thread-contact-copy{min-width:0}
    #plyrcard-action-drawer .lrm-thread-contact-copy strong{display:block;font-size:12px;color:#101828;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #plyrcard-action-drawer .lrm-thread-contact-copy span{display:block;font-size:9px;color:#667085;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #plyrcard-action-drawer .lrm-message-scroll{position:relative;z-index:1;flex:1 1 auto;min-height:0;overflow-y:auto;overflow-x:hidden;padding:0 14px 14px;overscroll-behavior:contain}
    #plyrcard-action-drawer .lrm-load-older{display:flex;justify-content:center;position:sticky;top:0;z-index:2;padding:9px 0 7px;background:linear-gradient(#fff 78%,rgba(255,255,255,0))}
    #plyrcard-action-drawer .lrm-reply-composer{position:relative;z-index:4;flex:0 0 auto;margin:0;padding:10px 14px 12px;background:#fff;border-top:1px solid #e5e7eb}
    #plyrcard-action-drawer .lrm-reply-box{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px;align-items:end;padding:8px;border:1px solid #d0d5dd;border-radius:13px;background:#fff;box-shadow:0 -8px 24px rgba(15,23,42,.06)}
    #plyrcard-action-drawer .lrm-reply-box textarea{border:0!important;box-shadow:none!important;resize:none;min-height:42px;max-height:110px;padding:7px!important;background:transparent!important}
    #plyrcard-action-drawer .lrm-inbox-action-row{display:flex;align-items:center;gap:6px}
    #plyrcard-action-drawer .lrm-inbox-toggle{min-width:34px;height:34px;border:1px solid #e5e7eb;border-radius:9px;background:#fff;color:#667085;cursor:pointer}
    #plyrcard-action-drawer .lrm-message{display:grid;grid-template-columns:28px minmax(0,1fr);gap:8px;margin:10px 0}
    #plyrcard-action-drawer .lrm-message.is-out{grid-template-columns:minmax(0,1fr) 28px}
    #plyrcard-action-drawer .lrm-message-avatar{width:28px;height:28px;border-radius:999px;background:#f2f4f7;display:grid;place-items:center;font-size:9px;font-weight:900}
    #plyrcard-action-drawer .lrm-message.is-out .lrm-message-avatar{grid-column:2;background:#fff0eb;color:#c2410c}
    #plyrcard-action-drawer .lrm-message-body{min-width:0}
    #plyrcard-action-drawer .lrm-message.is-out .lrm-message-body{grid-column:1;grid-row:1}
    #plyrcard-action-drawer .lrm-msg-meta{display:flex;justify-content:space-between;gap:8px;margin-bottom:4px;color:#98a2b3;font-size:9px}
    #plyrcard-action-drawer .lrm-email-body{border:1px solid #eaecf0;border-radius:12px;background:#fff;padding:11px;overflow:hidden;max-width:100%;min-width:0;font-size:11px;line-height:1.5;color:#344054;word-break:normal;overflow-wrap:anywhere}
    #plyrcard-action-drawer .lrm-email-body,
    #plyrcard-action-drawer .lrm-email-body *{box-sizing:border-box!important;max-width:100%!important;min-width:0!important;overflow-wrap:anywhere!important;word-break:break-word!important}
    #plyrcard-action-drawer .lrm-email-body div,
    #plyrcard-action-drawer .lrm-email-body p,
    #plyrcard-action-drawer .lrm-email-body span,
    #plyrcard-action-drawer .lrm-email-body a,
    #plyrcard-action-drawer .lrm-email-body blockquote{width:auto!important;white-space:normal!important}
    #plyrcard-action-drawer .lrm-email-body img{width:auto!important;height:auto!important;max-width:100%!important;object-fit:contain}
    #plyrcard-action-drawer .lrm-email-body table{width:100%!important;min-width:0!important;max-width:100%!important;table-layout:fixed!important;border-collapse:collapse!important}
    #plyrcard-action-drawer .lrm-email-body tbody,
    #plyrcard-action-drawer .lrm-email-body thead,
    #plyrcard-action-drawer .lrm-email-body tr,
    #plyrcard-action-drawer .lrm-email-body td,
    #plyrcard-action-drawer .lrm-email-body th{max-width:100%!important;min-width:0!important;width:auto!important;white-space:normal!important;overflow-wrap:anywhere!important}
    #plyrcard-action-drawer .lrm-email-body pre,
    #plyrcard-action-drawer .lrm-email-body code{white-space:pre-wrap!important;overflow-wrap:anywhere!important;word-break:break-word!important}
    #plyrcard-action-drawer .lrm-email-body iframe,
    #plyrcard-action-drawer .lrm-email-body video{width:100%!important;max-width:100%!important;height:auto!important}
    #plyrcard-action-drawer .lrm-email-body [style*="position: fixed"],
    #plyrcard-action-drawer .lrm-email-body [style*="position:fixed"]{position:static!important}
    #plyrcard-action-drawer .lrm-compose-recipient{display:grid;gap:8px;padding:11px;border:1px solid #e5e7eb;border-radius:13px;background:#f8fafc}
    #plyrcard-action-drawer .lrm-compose-school-results{display:grid;gap:5px;max-height:180px;overflow:auto;margin-top:6px}
    #plyrcard-action-drawer .lrm-compose-school-result{width:100%;border:1px solid #e5e7eb;border-radius:9px;background:#fff;padding:8px;text-align:left;cursor:pointer}
    #plyrcard-action-drawer .lrm-recipient-tabs{display:flex;gap:5px;flex-wrap:wrap}
    #plyrcard-action-drawer .lrm-recipient-tab{border:1px solid #e5e7eb;border-radius:999px;background:#fff;color:#475467;padding:6px 10px;font-size:10px;font-weight:800;cursor:pointer}
    #plyrcard-action-drawer .lrm-recipient-tab.is-active{background:#f0522b;border-color:#f0522b;color:#fff}
    #plyrcard-action-drawer .lrm-points-overview{display:grid;grid-template-columns:minmax(0,1fr);gap:11px}
    #plyrcard-action-drawer .lrm-points-balance{border:1px solid #f1d4c8;border-radius:18px;background:linear-gradient(135deg,#fff7f2,#fff);padding:17px;box-shadow:0 10px 26px rgba(240,82,43,.06)}
    #plyrcard-action-drawer .lrm-balance-number{display:flex;align-items:flex-end;gap:7px;margin:14px 0}
    #plyrcard-action-drawer .lrm-balance-number strong{font-size:46px;line-height:.85;letter-spacing:-.06em;color:#c2410c;font-weight:950}
    #plyrcard-action-drawer .lrm-balance-number span{font-size:10px;font-weight:800;color:#667085}
    #plyrcard-action-drawer .lrm-service-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    #plyrcard-action-drawer .lrm-service-card{border:1px solid #e5e7eb;border-radius:15px;background:#fff;padding:12px;display:flex;flex-direction:column;min-width:0;box-shadow:0 6px 16px rgba(15,23,42,.035)}
    #plyrcard-action-drawer .lrm-service-head{display:flex;justify-content:space-between;gap:8px;align-items:start}
    #plyrcard-action-drawer .lrm-service-head strong{font-size:11px}
    #plyrcard-action-drawer .lrm-service-price{font-size:16px;font-weight:950;color:#c2410c;white-space:nowrap}
    #plyrcard-action-drawer .lrm-sample-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;margin-top:8px}
    #plyrcard-action-drawer .lrm-sample-grid a{border-radius:8px;overflow:hidden;background:#fff;border:1px solid #e5e7eb;min-height:54px;display:grid;place-items:center;text-decoration:none}
    #plyrcard-action-drawer .lrm-sample-grid img{width:100%;height:70px;object-fit:contain;display:block}
    #plyrcard-action-drawer .lrm-pack-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
    #plyrcard-action-drawer .lrm-pack{border:1px solid #e5e7eb;border-radius:15px;background:#fff;padding:12px;display:flex;flex-direction:column;gap:6px;box-shadow:0 6px 16px rgba(15,23,42,.035)}
    #plyrcard-action-drawer .lrm-pack .points{font-size:22px;font-weight:950;color:#c2410c}
    #plyrcard-action-drawer .lrm-history-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    @media(max-width:1200px){
        #plyrcard-action-drawer .lrm-school-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        #plyrcard-action-drawer .lrm-pack-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media(max-width:760px){
        #plyrcard-action-drawer .lrm-school-grid,#plyrcard-action-drawer .lrm-list-grid,#plyrcard-action-drawer .lrm-service-grid,#plyrcard-action-drawer .lrm-pack-grid,#plyrcard-action-drawer .lrm-history-grid,#plyrcard-action-drawer .lrm-points-overview,#plyrcard-action-drawer .lrm-inbox-shell{grid-template-columns:1fr}
        #plyrcard-action-drawer .lrm-filter-row{grid-template-columns:1fr 1fr}
        #plyrcard-action-drawer .lrm-nav-tiles{grid-template-columns:1fr 1fr}
        #plyrcard-action-drawer .lrm-school-list-row{grid-template-columns:minmax(130px,1fr) 80px 38px}
        #plyrcard-action-drawer .lrm-school-list-row .lrm-hide-mobile{display:none}
        #plyrcard-action-drawer .lrm-thread{height:70vh;min-height:430px;max-height:720px}
        #plyrcard-action-drawer .lrm-inbox-view.is-maximized .lrm-thread{height:calc(100vh - 175px);min-height:520px;max-height:none}
        #plyrcard-action-drawer .lrm-reply-composer{padding:9px 10px 10px}
        #plyrcard-action-drawer .lrm-message-scroll{padding:0 10px 10px}
    }
</style>

<section class="lr-view" data-lr-view="schools">
    <div class="lr-section">
        <div class="lrm-toolbar">
            <div class="lrm-toolbar-top">
                <div><h3>Discover Schools</h3><p>Search, filter, favorite and open a school without leaving Locker Room.</p></div>
                <div class="lrm-segment" aria-label="School view">
                    <button type="button" class="is-active" data-lrm-school-view="grid" aria-label="Grid view"><i class="fa-solid fa-table-cells-large"></i></button>
                    <button type="button" data-lrm-school-view="list" aria-label="List view"><i class="fa-solid fa-list"></i></button>
                </div>
            </div>
            <div class="lrm-search-row">
                <input class="lr-input" type="search" data-lrm-school-search placeholder="Search schools, coaches, conferences...">
                <button class="lr-btn" type="button" data-lrm-schools-refresh aria-label="Refresh schools"><i class="fa-solid fa-rotate"></i></button>
            </div>
            <div class="lrm-filter-row">
                <select class="lr-select" data-lrm-division-filter><option value="">All Divisions</option></select>
                <select class="lr-select" data-lrm-state-filter><option value="">All States</option></select>
                <select class="lr-select" data-lrm-conference-filter><option value="">All Conferences</option></select>
                <span class="lrm-pill" data-lrm-school-count>0 schools</span>
            </div>
        </div>
        <div data-lrm-schools><div class="lr-detail-empty">Loading schools…</div></div>
    </div>
</section>

<section class="lr-view" data-lr-view="favorites">
    <div class="lr-section">
        <div class="lrm-toolbar">
            <div class="lrm-toolbar-top"><div><h3>Favorite Schools</h3><p>Your starred schools, using the same school drawer as Discover.</p></div><span class="lrm-pill" data-lrm-favorite-count>0 schools</span></div>
        </div>
        <div data-lrm-favorites><div class="lr-detail-empty">Loading favorites…</div></div>
    </div>
</section>

<section class="lr-view" data-lr-view="lists">
    <div class="lr-section">
        <div class="lrm-toolbar">
            <div class="lrm-toolbar-top"><div><h3>My Lists</h3><p>Organize schools into Dream Schools, On the Radar, conference groups or your own lists.</p></div></div>
            <form data-lrm-list-form>
                <div class="lrm-search-row">
                    <input class="lr-input" name="name" maxlength="80" placeholder="New list name" required>
                    <button class="lr-btn lr-btn-primary" type="submit"><i class="fa-solid fa-plus"></i> New List</button>
                </div>
                <input type="hidden" name="color" value="#ff6338">
            </form>
        </div>
        <div data-lrm-lists><div class="lr-detail-empty">Loading lists…</div></div>
    </div>
</section>

<section class="lr-view lrm-inbox-view" data-lr-view="inbox" data-lrm-inbox-root>
    <div class="lr-section">
        <div class="lrm-nested-window is-active" data-lrm-inbox-window="list">
            <div class="lrm-toolbar">
                <div class="lrm-toolbar-top">
                    <div><h3>Inbox</h3><p>Coach email conversations, using the same compact pattern as Recruiting Center.</p></div>
                    <div class="lrm-inbox-action-row">
                        <button class="lrm-inbox-toggle" type="button" data-lrm-inbox-size title="Maximize inbox" aria-label="Maximize inbox"><i class="fa-solid fa-expand"></i></button>
                        <button class="lrm-inbox-toggle" type="button" data-lrm-inbox-refresh title="Refresh inbox" aria-label="Refresh inbox"><i class="fa-solid fa-rotate"></i></button>
                    </div>
                </div>
                <input class="lr-input" type="search" data-lrm-inbox-search placeholder="Search conversations...">
            </div>
            <div class="lrm-inbox-list" data-lrm-inbox-list><div class="lr-detail-empty">Loading conversations…</div></div>
        </div>
        <div class="lrm-nested-window" data-lrm-inbox-window="thread">
            <div class="lrm-nested-head">
                <button type="button" class="lrm-nested-back" data-lrm-inbox-back><i class="fa-solid fa-chevron-left"></i> Conversations</button>
                <button class="lrm-inbox-toggle" type="button" data-lrm-inbox-size title="Maximize inbox" aria-label="Maximize inbox"><i class="fa-solid fa-expand"></i></button>
            </div>
            <div class="lr-card lrm-thread" data-lrm-thread><div class="lr-detail-empty">Choose a conversation to read the email thread.</div></div>
        </div>
    </div>
</section>

<section class="lr-view" data-lr-view="compose">
    <form class="lr-form" data-lrm-compose-form>
        <div class="lr-form-section">
            <h4>Compose Email</h4>
            <p class="lr-card-copy">Choose one school, then send to the Head Coach Only or All Coaches at that school.</p>
            <input type="hidden" name="target_mode" value="school">
            <input type="hidden" name="school_id" data-lrm-compose-school-id>
            <input type="hidden" name="head_coach_only" value="1" data-lrm-head-only>
            <div class="lrm-compose-recipient">
                <div data-lrm-selected-school><span class="lr-card-copy">No school selected.</span></div>
                <div class="lrm-recipient-tabs">
                    <button type="button" class="lrm-recipient-tab is-active" data-lrm-recipient-mode="head">Head Coach Only</button>
                    <button type="button" class="lrm-recipient-tab" data-lrm-recipient-mode="all">All Coaches</button>
                </div>
                <div class="lr-field">
                    <label>Search School</label>
                    <input class="lr-input" type="search" data-lrm-compose-school-search placeholder="Search for a school...">
                    <div class="lrm-compose-school-results" data-lrm-compose-school-results></div>
                </div>
            </div>
            <div class="lr-form-grid" style="margin-top:10px;">
                <div class="lr-field is-full"><label>Template</label><select class="lr-select" data-lrm-template><option value="">Start from scratch</option></select></div>
                <div class="lr-field is-full"><label>Subject</label><input class="lr-input" name="subject" maxlength="250" required></div>
                <div class="lr-field is-full"><label>Message</label><textarea class="lr-textarea" name="body" rows="11" required></textarea></div>
            </div>
            <div class="lr-actions"><button class="lr-btn lr-btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> <span data-lrm-send-label>Choose a school</span></button></div>
        </div>
    </form>
</section>

<section class="lr-view" data-lr-view="points">
    <div class="lr-section">
        <div class="lrm-nested-window is-active" data-lrm-points-window="home">
            <div class="lrm-toolbar">
                <div class="lrm-toolbar-top"><div><h3>PLYR Points</h3><p>Your recruiting credit wallet: spend, reload, and track every point.</p></div><span class="lrm-pill"><i class="fa-solid fa-coins" style="margin-right:5px"></i> PLYR WALLET</span></div>
            </div>
            <div class="lrm-points-balance">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px"><span class="lr-eyebrow">Available Balance</span><span class="lrm-pill">Ready to use</span></div>
                <div class="lrm-balance-number"><strong data-lrm-points-balance>0</strong><span>PLYR Points</span></div>
                <p class="lr-card-copy">Spend points on recruiting services or add more when you need them.</p>
            </div>
            <div class="lrm-nav-tiles">
                <button type="button" class="lrm-nav-tile" data-lrm-points-open="services"><span class="lrm-point-action-top"><span class="lrm-point-action-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></span><i class="fa-solid fa-chevron-right lrm-point-action-arrow"></i></span><span><strong>Use Points</strong><small>Recruiting graphics, editing, outreach and enabled services.</small></span></button>
                <button type="button" class="lrm-nav-tile" data-lrm-points-open="buy"><span class="lrm-point-action-top"><span class="lrm-point-action-icon"><i class="fa-solid fa-plus"></i></span><i class="fa-solid fa-chevron-right lrm-point-action-arrow"></i></span><span><strong>Buy Points</strong><small>Reload with a point pack or choose a custom amount.</small></span></button>
                <button type="button" class="lrm-nav-tile" data-lrm-points-open="requests"><span class="lrm-point-action-top"><span class="lrm-point-action-icon"><i class="fa-solid fa-list-check"></i></span><span class="value" data-lrm-request-count>0</span></span><span><strong>My Requests</strong><small>Track current work, status updates and delivered files.</small></span></button>
                <button type="button" class="lrm-nav-tile" data-lrm-points-open="activity"><span class="lrm-point-action-top"><span class="lrm-point-action-icon"><i class="fa-solid fa-clock-rotate-left"></i></span><span class="value" data-lrm-activity-count>0</span></span><span><strong>Point Tracking</strong><small>See where every point was earned, purchased, used or returned.</small></span></button>
            </div>
            <div class="lr-card" style="margin-top:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <div><h3 class="lr-card-title">Recent Point Activity</h3><p class="lr-card-copy">A minimized preview of your credit ledger.</p></div>
                    <button type="button" class="lrm-activity-toggle" data-lrm-activity-preview-toggle>Maximize</button>
                </div>
                <div class="lrm-collapsible-list is-minimized" data-lrm-activity-preview><div class="lr-detail-empty">Loading point activity…</div></div>
            </div>
        </div>

        <div class="lrm-nested-window" data-lrm-points-window="services">
            <div class="lrm-nested-head"><button type="button" class="lrm-nested-back" data-lrm-points-back><i class="fa-solid fa-chevron-left"></i> PLYR Points</button></div>
            <div class="lr-card"><h3 class="lr-card-title">Use PLYR Points</h3><p class="lr-card-copy">Choose an enabled recruiting service.</p><div class="lrm-service-grid" data-lrm-services></div></div>
        </div>

        <div class="lrm-nested-window" data-lrm-points-window="buy">
            <div class="lrm-nested-head"><button type="button" class="lrm-nested-back" data-lrm-points-back><i class="fa-solid fa-chevron-left"></i> PLYR Points</button></div>
            <div data-lrm-packs></div>
        </div>

        <div class="lrm-nested-window" data-lrm-points-window="requests">
            <div class="lrm-nested-head"><button type="button" class="lrm-nested-back" data-lrm-points-back><i class="fa-solid fa-chevron-left"></i> PLYR Points</button></div>
            <div class="lr-card"><h3 class="lr-card-title">My Requests</h3><p class="lr-card-copy">Service status, spent points, returned points and delivery details.</p><div data-lrm-requests></div></div>
        </div>

        <div class="lrm-nested-window" data-lrm-points-window="activity">
            <div class="lrm-nested-head"><button type="button" class="lrm-nested-back" data-lrm-points-back><i class="fa-solid fa-chevron-left"></i> PLYR Points</button></div>
            <div class="lr-card"><h3 class="lr-card-title">Credit Points Tracking</h3><p class="lr-card-copy">Complete point ledger including purchases, referral rewards, debits, refunds, returns and adjustments.</p><div data-lrm-history></div></div>
        </div>
    </div>
</section>

<script>
(function () {
    var drawer=document.getElementById('plyrcard-action-drawer');
    if(!drawer||drawer.dataset.lrmReady==='1')return;
    drawer.dataset.lrmReady='1';

    var data={schools:[],favorites:[],lists:[],conversations:[],templates:[],points:null,pointHistory:[]};
    var loaded={schools:false,favorites:false,lists:false,inbox:false,compose:false,points:false};
    var schoolView='grid', activeConversation='', selectedComposeSchool=null, inboxTimer=null, inboxMaximized=false;
    var threadState={conversation:null,messages:[],cursor:null,hasMore:false,loadingOlder:false};

    function q(s){return drawer.querySelector(s)}
    function qa(s){return Array.from(drawer.querySelectorAll(s))}
    function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]})}
    function csrf(){var m=document.querySelector('meta[name="csrf-token"]'),i=document.querySelector('input[name="_token"]');return m?m.content:(i?i.value:'')}
    async function api(url,options){
        options=options||{};
        var headers=Object.assign({'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf()},options.headers||{});
        if(options.body&&!(options.body instanceof FormData)&&typeof options.body!=='string'){headers['Content-Type']='application/json';options.body=JSON.stringify(options.body)}
        var response=await fetch(url,Object.assign({credentials:'same-origin'},options,{headers:headers})),json={};
        try{json=await response.json()}catch(_){}
        if(!response.ok||json.success===false){var validation=json.errors?Object.values(json.errors).flat().join(' '):'';throw new Error(validation||json.message||json.error||('Request failed ('+response.status+')'))}
        return json;
    }
    function toast(message,error){
        var el=q('[data-lr-toast]');if(!el)return;
        el.textContent=message||(error?'Something went wrong.':'Saved.');
        el.style.background=error?'#991b1b':'#111827';el.classList.add('is-showing');
        setTimeout(function(){el.classList.remove('is-showing')},3200);
    }
    function busy(form,on,label){var b=form&&form.querySelector('button[type="submit"]');if(!b)return;if(on){if(!b.dataset.old)b.dataset.old=b.innerHTML;b.disabled=true;b.textContent=label||'Working…'}else{b.disabled=false;if(b.dataset.old)b.innerHTML=b.dataset.old}}
    function money(cents){return new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(Number(cents||0)/100)}
    function initials(name){return String(name||'').trim().split(/\s+/).filter(Boolean).slice(0,2).map(function(x){return x.charAt(0).toUpperCase()}).join('')||'S'}
    function schoolRef(row){return String(row.id||row.school_id||row.reference||row.business_id||'')}
    function schoolLogo(row){var url=row.logo_url||row.school_logo_url||row.business_logo_url||'';return url?'<img src="'+esc(url)+'" alt="" loading="lazy" referrerpolicy="no-referrer">':esc(initials(row.name||row.school_name||'School'))}
    function schoolCard(row){
        var ref=schoolRef(row),fav=!!(row.is_favorite||row.favorite),count=Number(row.coach_count||row.coaches_count||(row.coaches||[]).length||0);
        return '<article class="lrm-school-card"><div style="display:flex;gap:7px;align-items:start;"><button class="lrm-school-main" type="button" data-lr-dashboard-school="'+esc(ref)+'"><span class="lrm-school-logo">'+schoolLogo(row)+'</span><span class="lrm-school-copy"><strong>'+esc(row.name||'School')+'</strong><small>'+esc(row.conference||'Conference unavailable')+'</small></span></button><button class="lrm-icon-btn '+(fav?'is-active':'')+'" type="button" data-lrm-favorite="'+esc(ref)+'" data-favorite="'+(fav?'1':'0')+'" aria-label="Favorite school"><i class="fa-'+(fav?'solid':'regular')+' fa-star"></i></button></div><div class="lrm-school-foot"><span class="lrm-pill">'+esc(row.division||'Unlisted')+'</span><span style="font-size:9px;color:#667085;font-weight:800">'+count.toLocaleString()+' '+(count===1?'coach':'coaches')+'</span></div></article>';
    }
    function filteredSchools(){
        var term=String(q('[data-lrm-school-search]')?.value||'').toLowerCase().trim();
        var division=String(q('[data-lrm-division-filter]')?.value||''),state=String(q('[data-lrm-state-filter]')?.value||''),conference=String(q('[data-lrm-conference-filter]')?.value||'');
        return (data.schools||[]).filter(function(row){
            if(division&&String(row.division||'')!==division)return false;
            if(state&&String(row.state||'')!==state)return false;
            if(conference&&String(row.conference||'')!==conference)return false;
            if(!term)return true;
            var coaches=Array.isArray(row.coaches)?row.coaches:[];
            return [row.name,row.conference,row.division,row.city,row.state].concat(coaches.flatMap(function(c){return[c.name,c.email,c.title]})).join(' ').toLowerCase().includes(term);
        });
    }
    function optionValues(key){
        return Array.from(new Set((data.schools||[]).map(function(row){return String(row[key]||'').trim()}).filter(Boolean))).sort(function(a,b){return a.localeCompare(b)});
    }
    function fillSelect(selector,label,key){
        var el=q(selector);if(!el)return;var old=el.value;
        el.innerHTML='<option value="">'+label+'</option>'+optionValues(key).map(function(v){return '<option value="'+esc(v)+'">'+esc(v)+'</option>'}).join('');
        if(old&&Array.from(el.options).some(function(o){return o.value===old}))el.value=old;
    }
    function renderSchools(){
        var holder=q('[data-lrm-schools]');if(!holder)return;
        fillSelect('[data-lrm-division-filter]','All Divisions','division');
        fillSelect('[data-lrm-state-filter]','All States','state');
        fillSelect('[data-lrm-conference-filter]','All Conferences','conference');
        var rows=filteredSchools(),count=q('[data-lrm-school-count]');if(count)count.textContent=rows.length.toLocaleString()+' schools';
        qa('[data-lrm-school-view]').forEach(function(b){b.classList.toggle('is-active',b.dataset.lrmSchoolView===schoolView)});
        if(!rows.length){holder.innerHTML='<div class="lr-detail-empty">No schools matched your filters.</div>';return}
        if(schoolView==='list'){
            holder.innerHTML='<div class="lrm-school-list">'+rows.map(function(row){
                var ref=schoolRef(row),head=row.head_coach||{},headName=row.head_coach_name||head.name||'—',headEmail=row.head_coach_email||head.email||'—',fav=!!row.is_favorite;
                return '<div class="lrm-school-list-row"><button class="lrm-school-main" style="grid-template-columns:34px minmax(0,1fr)" type="button" data-lr-dashboard-school="'+esc(ref)+'"><span class="lrm-school-logo" style="width:34px;height:34px">'+schoolLogo(row)+'</span><span class="lrm-school-copy"><strong>'+esc(row.name||'School')+'</strong><small>'+esc(row.conference||'')+'</small></span></button><span class="lrm-hide-mobile">'+esc(headName)+'</span><span class="lrm-hide-mobile">'+esc(headEmail)+'</span><span>'+esc(row.division||'—')+'</span><button class="lrm-icon-btn '+(fav?'is-active':'')+'" type="button" data-lrm-favorite="'+esc(ref)+'" data-favorite="'+(fav?'1':'0')+'"><i class="fa-'+(fav?'solid':'regular')+' fa-star"></i></button></div>';
            }).join('')+'</div>';
        }else holder.innerHTML='<div class="lrm-school-grid">'+rows.map(schoolCard).join('')+'</div>';
    }
    function renderFavorites(){
        var holder=q('[data-lrm-favorites]');if(!holder)return;var rows=data.favorites||[],count=q('[data-lrm-favorite-count]');if(count)count.textContent=rows.length.toLocaleString()+' schools';
        holder.innerHTML=rows.length?'<div class="lrm-school-grid">'+rows.map(schoolCard).join('')+'</div>':'<div class="lr-detail-empty">You have not favorited any schools yet.</div>';
    }
    function listSchools(row){return Array.isArray(row.schools)?row.schools:[]}
    function renderLists(){
        var holder=q('[data-lrm-lists]');if(!holder)return;
        var rows=data.lists||[];
        holder.innerHTML=rows.length?'<div class="lrm-list-grid">'+rows.map(function(row){
            var schools=listSchools(row),key=String(row.key||''),count=Number(row.schools_count==null?schools.length:row.schools_count);
            return '<article class="lrm-list-card" data-lrm-list-card="'+esc(key)+'"><div class="lrm-list-head"><div class="lrm-list-title"><span class="lrm-list-dot" style="background:'+esc(row.color||'#ff6338')+'"></span><div><strong>'+esc(row.label||row.name||'List')+'</strong><div class="lr-card-copy">'+count+' '+(count===1?'school':'schools')+'</div></div></div><div style="display:flex;gap:5px"><button class="lrm-icon-btn" type="button" data-lrm-list-add-toggle="'+esc(key)+'" title="Add schools"><i class="fa-solid fa-plus"></i></button><button class="lrm-icon-btn" type="button" data-lrm-list-delete="'+esc(key)+'" title="Delete list"><i class="fa-solid fa-trash"></i></button></div></div><div class="lrm-school-chips">'+(schools.length?schools.map(function(s){return '<button type="button" class="lrm-school-chip" data-lr-dashboard-school="'+esc(s.id||'')+'"><span>'+esc(s.name||'School')+'</span></button>'}).join(''):'<span class="lr-card-copy">No schools in this list yet.</span>')+'</div><div class="lrm-list-add-panel" data-lrm-list-add-panel="'+esc(key)+'" hidden><input class="lr-input" type="search" data-lrm-list-school-search="'+esc(key)+'" placeholder="Search schools to add..."><div class="lrm-list-search-results" data-lrm-list-results="'+esc(key)+'"></div></div></article>';
        }).join('')+'</div>':'<div class="lr-detail-empty">No recruiting lists yet.</div>';
    }
    function renderListSearch(key){
        var input=q('[data-lrm-list-school-search="'+CSS.escape(key)+'"]'),holder=q('[data-lrm-list-results="'+CSS.escape(key)+'"]');if(!input||!holder)return;
        var term=String(input.value||'').trim().toLowerCase(),list=(data.lists||[]).find(function(r){return String(r.key||'')===key}),current=new Set(listSchools(list||{}).map(function(s){return String(s.id||'')}));
        if(term.length<2){holder.innerHTML='';return}
        var rows=(data.schools||[]).filter(function(s){return !current.has(String(s.id||''))&&String(s.name||'').toLowerCase().includes(term)}).slice(0,10);
        holder.innerHTML=rows.length?rows.map(function(s){return '<button type="button" class="lrm-list-result" data-lrm-add-school="'+esc(s.id||'')+'" data-list-key="'+esc(key)+'"><span>'+esc(s.name||'School')+'</span><i class="fa-solid fa-plus"></i></button>'}).join(''):'<div class="lr-card-copy">No matching schools.</div>';
    }
    function inboxDate(value){
        var d=new Date(value||'');if(Number.isNaN(d.getTime()))return '';
        var today=new Date(),same=today.toDateString()===d.toDateString();
        return same?d.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'}):d.toLocaleDateString('en-US',{month:'short',day:'numeric'});
    }
    function renderInbox(){
        var holder=q('[data-lrm-inbox-list]');if(!holder)return;
        var rows=data.conversations||[];
        holder.innerHTML=rows.length?rows.map(function(row){
            var active=String(row.id||'')===activeConversation,logo=row.school_logo_url||'',school=row.school||row.title||'Coach',preview=row.preview||row.last_message||'No message preview available.';
            return '<button type="button" class="lrm-conversation '+(active?'is-active':'')+'" data-lrm-conversation="'+esc(row.id||'')+'"><span class="lrm-thread-logo">'+(logo?'<img src="'+esc(logo)+'" alt="" loading="lazy">':esc(initials(row.contact_name||'Coach')))+'</span><span class="lrm-thread-main"><span class="lrm-thread-name">'+esc(row.contact_name||'Coach')+'</span><span class="lrm-thread-school">'+esc(school)+'</span><span class="lrm-thread-preview">'+esc(preview)+'</span></span><span class="lrm-thread-side"><span class="lrm-thread-date">'+esc(inboxDate(row.last_message_at))+'</span>'+(Number(row.unread_count||0)>0?'<span class="lrm-unread-dot" title="'+Number(row.unread_count)+' unread"></span>':'')+'</span></button>';
        }).join(''):'<div class="lr-detail-empty">No conversations yet.</div>';
    }
    function safeEmailHtml(value){return String(value||'<p><em>No message body.</em></p>')}
    function messageDate(value){var d=new Date(value||'');return Number.isNaN(d.getTime())?'':d.toLocaleString('en-US',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'})}
    function setInboxMaximized(value){
        inboxMaximized=!!value;
        var root=q('[data-lrm-inbox-root]');if(root)root.classList.toggle('is-maximized',inboxMaximized);
        qa('[data-lrm-inbox-size]').forEach(function(btn){btn.title=inboxMaximized?'Minimize inbox':'Maximize inbox';btn.setAttribute('aria-label',btn.title);btn.innerHTML='<i class="fa-solid fa-'+(inboxMaximized?'compress':'expand')+'"></i>'});
    }
    function renderThread(){
        var thread=q('[data-lrm-thread]');if(!thread)return;
        var convo=threadState.conversation||{},messages=threadState.messages||[],title=convo.contact_name||'Conversation',school=convo.school||convo.title||convo.email||'',logo=convo.school_logo_url||'';
        var latest=messages.length?messages[messages.length-1]:{},subject=String(latest.subject||'').trim();
        if(subject&&!/^re:/i.test(subject))subject='Re: '+subject;
        if(!subject)subject='Re: PLYRCARD Recruiting';
        thread.innerHTML='<div class="lrm-thread-header"><div class="lrm-thread-contact"><span class="lrm-thread-logo">'+(logo?'<img src="'+esc(logo)+'" alt="">':esc(initials(title)))+'</span><span class="lrm-thread-contact-copy"><strong>'+esc(title)+'</strong><span>'+esc(school)+'</span></span></div><span class="lrm-pill">'+messages.length+' loaded</span></div><div class="lrm-message-scroll" data-lrm-message-scroll>'+(threadState.hasMore?'<div class="lrm-load-older"><button class="lr-btn" type="button" data-lrm-load-older '+(threadState.loadingOlder?'disabled':'')+'>'+(threadState.loadingOlder?'Loading 10 older emails…':'Load 10 older emails')+'</button></div>':'')+'<div data-lrm-message-stack></div></div><form class="lrm-reply-composer" data-lrm-reply-form><input type="hidden" name="subject" value="'+esc(subject)+'"><div class="lrm-reply-box"><textarea class="lr-textarea" name="body" rows="2" maxlength="100000" placeholder="Reply to '+esc(title)+'..." required></textarea><button class="lr-btn lr-btn-primary" type="submit" aria-label="Send reply"><i class="fa-solid fa-paper-plane"></i></button></div></form>';
        var stack=q('[data-lrm-message-stack]');
        messages.forEach(function(m){
            var direction=String(m.direction||'').toLowerCase(),out=direction.includes('out')||['sent','send','outgoing','out'].includes(direction);
            var article=document.createElement('article');article.className='lrm-message'+(out?' is-out':'');
            var avatar=document.createElement('span');avatar.className='lrm-message-avatar';avatar.textContent=out?'YOU':initials(title);
            var body=document.createElement('div');body.className='lrm-message-body';
            var meta=document.createElement('div');meta.className='lrm-msg-meta';meta.innerHTML='<span><strong>'+esc(out?'You':title)+'</strong></span><span>'+esc(messageDate(m.created_at))+'</span>';
            var email=document.createElement('div');email.className='lrm-email-body';email.innerHTML=safeEmailHtml(m.rendered_html);
            body.appendChild(meta);body.appendChild(email);
            if(out){article.appendChild(body);article.appendChild(avatar)}else{article.appendChild(avatar);article.appendChild(body)}
            stack.appendChild(article);
        });
        if(!messages.length)stack.innerHTML='<div class="lr-detail-empty">No email messages in this conversation.</div>';
    }
    async function loadOlderMessages(){
        if(!activeConversation||threadState.loadingOlder||!threadState.hasMore||!threadState.cursor)return;
        var before=q('[data-lrm-message-scroll]'),oldHeight=before?before.scrollHeight:0,oldTop=before?before.scrollTop:0;
        threadState.loadingOlder=true;renderThread();
        try{
            var url=drawer.dataset.recruitingConversationsUrl+'/'+encodeURIComponent(activeConversation)+'?limit=10&last_message_id='+encodeURIComponent(threadState.cursor);
            var j=await api(url),older=Array.isArray(j.messages)?j.messages:[],seen=new Set((threadState.messages||[]).map(function(m){return String(m.id||'')}));
            threadState.messages=older.filter(function(m){return !seen.has(String(m.id||''))}).concat(threadState.messages||[]);
            threadState.cursor=j.last_message_id||threadState.cursor;threadState.hasMore=!!j.has_more;
        }catch(e){toast(e.message,true)}
        finally{
            threadState.loadingOlder=false;renderThread();
            requestAnimationFrame(function(){var stream=q('[data-lrm-message-scroll]');if(stream)stream.scrollTop=Math.max(0,stream.scrollHeight-oldHeight+oldTop)});
        }
    }
    async function submitThreadReply(form){
        if(!activeConversation)return;
        var fd=new FormData(form),body=String(fd.get('body')||'').trim(),subject=String(fd.get('subject')||'').trim();
        if(!body)return;
        busy(form,true,'Sending…');
        try{
            var j=await api(drawer.dataset.recruitingEmailUrl,{method:'POST',body:{target_mode:'conversation',conversation_id:activeConversation,subject:subject||'Re: PLYRCARD Recruiting',body:body}});
            toast(j.message||'Reply sent.');form.reset();
            await openConversation(activeConversation,true);
            loaded.inbox=false;loadInbox(true);
        }catch(e){toast(e.message,true)}
        finally{busy(form,false)}
    }
    function renderCompose(){
        var template=q('[data-lrm-template]');if(template){var old=template.value;template.innerHTML='<option value="">Start from scratch</option>'+(data.templates||[]).map(function(r){return '<option value="'+esc(r.id||'')+'">'+esc(r.name||'Template')+'</option>'}).join('');if(old)template.value=old}
        renderComposeSchool();
        renderComposeSchoolResults();
    }
    function renderComposeSchool(){
        var box=q('[data-lrm-selected-school]'),id=q('[data-lrm-compose-school-id]'),label=q('[data-lrm-send-label]');
        if(!box||!id)return;
        if(!selectedComposeSchool){box.innerHTML='<span class="lr-card-copy">No school selected.</span>';id.value='';if(label)label.textContent='Choose a school';return}
        var count=Number(selectedComposeSchool.coach_count||selectedComposeSchool.coaches_count||(selectedComposeSchool.coaches||[]).length||0);
        box.innerHTML='<div style="display:flex;align-items:center;gap:8px"><span class="lrm-school-logo" style="width:36px;height:36px">'+schoolLogo(selectedComposeSchool)+'</span><div><strong style="font-size:12px">'+esc(selectedComposeSchool.name||'School')+'</strong><div class="lr-card-copy">'+count+' '+(count===1?'coach':'coaches')+'</div></div><button type="button" class="lrm-icon-btn" data-lrm-clear-compose-school style="margin-left:auto"><i class="fa-solid fa-xmark"></i></button></div>';
        id.value=schoolRef(selectedComposeSchool);if(label)label.textContent='Send Email';
    }
    function renderComposeSchoolResults(){
        var holder=q('[data-lrm-compose-school-results]'),input=q('[data-lrm-compose-school-search]');if(!holder||!input)return;
        var term=String(input.value||'').trim().toLowerCase();
        if(!term){holder.innerHTML='';return}
        var rows=(data.schools||[]).filter(function(s){return [s.name,s.conference,s.division,s.city,s.state].join(' ').toLowerCase().includes(term)}).slice(0,15);
        holder.innerHTML=rows.length?rows.map(function(s){return '<button type="button" class="lrm-compose-school-result" data-lrm-choose-school="'+esc(s.id||'')+'"><strong style="font-size:11px">'+esc(s.name||'School')+'</strong><div class="lr-card-copy">'+esc([s.conference,s.division].filter(Boolean).join(' · '))+' · '+Number(s.coach_count||s.coaches_count||(s.coaches||[]).length||0)+' coaches</div></button>'}).join(''):'<div class="lr-card-copy">No schools found.</div>';
    }
    function setPointsWindow(name){
        qa('[data-lrm-points-window]').forEach(function(el){el.classList.toggle('is-active',el.dataset.lrmPointsWindow===name)});
    }
    function pointActivityRow(entry){
        var points=Number(entry.points||0),positive=entry.positive===true||points>0;
        return '<div style="padding:10px 0;border-top:1px solid #eef2f6;display:flex;justify-content:space-between;gap:10px;align-items:start"><div style="min-width:0"><div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap"><strong style="font-size:10px">'+esc(entry.item||'PLYR Points activity')+'</strong><span class="lrm-pill">'+esc(entry.status||entry.type||'Activity')+'</span></div><div class="lr-card-copy">'+esc(entry.date_time||entry.date||'')+(entry.source?' · '+esc(entry.source):'')+'</div></div><strong style="font-size:12px;color:'+(positive?'#15803d':'#b42318')+';white-space:nowrap">'+esc(entry.amount||(points>0?'+':'')+points)+'</strong></div>';
    }
    function renderPoints(){
        var p=data.points||{},history=Array.isArray(data.pointHistory)?data.pointHistory:[],balance=Number(p.balance||0),balanceEl=q('[data-lrm-points-balance]');
        if(balanceEl)balanceEl.textContent=balance.toLocaleString();
        var requestCount=q('[data-lrm-request-count]');if(requestCount)requestCount.textContent=Number((p.requests||[]).length).toLocaleString();
        var activityCount=q('[data-lrm-activity-count]');if(activityCount)activityCount.textContent=history.length.toLocaleString();

        var services=q('[data-lrm-services]');
        if(services)services.innerHTML=(p.catalog||[]).map(function(s){
            var enough=balance>=Number(s.points||0),samples=Array.isArray(s.samples)?s.samples:[];
            return '<article class="lrm-service-card"><div class="lrm-service-head"><strong>'+esc(s.name||'Service')+'</strong><span class="lrm-service-price">'+Number(s.points||0)+' <small>pts</small></span></div><p class="lr-card-copy" style="margin-top:5px;flex:1">'+esc(s.description||'')+'</p>'+(samples.length?'<div class="lrm-sample-grid">'+samples.slice(0,6).map(function(sample){if(sample.type==='image')return '<a href="'+esc(sample.url||'#')+'" target="_blank" rel="noopener"><img src="'+esc(sample.url||'')+'" alt="Service sample"></a>';if(sample.type==='youtube')return '<a href="'+esc(sample.url||sample.embed_url||'#')+'" target="_blank" rel="noopener"><i class="fa-brands fa-youtube"></i></a>';return '<a href="'+esc(sample.url||'#')+'" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>'}).join('')+'</div>':'')+'<button class="lr-btn lr-btn-primary" style="margin-top:8px;width:100%" type="button" data-lrm-service="'+esc(s.key)+'" data-points="'+Number(s.points||0)+'" '+(enough?'':'disabled')+'>'+(enough?esc(s.cta||'Request Service'):'Not enough points')+'</button></article>';
        }).join('')||'<div class="lr-detail-empty">No point services are currently enabled.</div>';

        var packs=q('[data-lrm-packs]');
        if(packs){
            if(p.can_buy_points){
                var rows=Array.isArray(p.point_packs)?p.point_packs:[];
                packs.innerHTML='<div class="lr-card"><h3 class="lr-card-title">Buy More Points</h3><p class="lr-card-copy">Recharge your balance without leaving Locker Room.</p><div class="lrm-pack-grid" style="margin-top:10px">'+rows.map(function(row){return '<article class="lrm-pack"><span class="lr-eyebrow">Point Pack</span><span class="points">'+Number(row.points||0).toLocaleString()+'</span><strong>'+money(row.price_cents)+'</strong><span class="lr-card-copy">'+esc(row.note||'Add points to your current balance.')+'</span><button class="lr-btn lr-btn-primary" type="button" data-lrm-point-pack="'+esc(row.key)+'">Buy Points</button></article>'}).join('')+'</div>'+(p.custom_purchase&&p.custom_purchase.enabled?'<div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:end;margin-top:10px;padding:10px;border:1px dashed #d0d5dd;border-radius:12px"><div><label style="font-size:10px;font-weight:850">CUSTOM POINTS</label><input class="lr-input" type="number" data-lrm-custom-points min="'+Number(p.custom_purchase.min_points||1)+'" max="'+Number(p.custom_purchase.max_points||1000)+'" value="'+Number(p.custom_purchase.min_points||1)+'"></div><button class="lr-btn" type="button" data-lrm-custom-buy>Buy Custom</button></div>':'')+'</div>';
            }else packs.innerHTML='<div class="lr-card"><h3 class="lr-card-title">Buy More Points</h3><p class="lr-card-copy">Point packs are available with My Journey.</p><div class="lr-actions"><button class="lr-btn lr-btn-primary" type="button" data-lr-nav="upgrade">Get My Journey</button></div></div>';
        }

        var requests=q('[data-lrm-requests]');
        if(requests)requests.innerHTML=(p.requests||[]).map(function(r){
            var returned=Number(r.credits_returned||0);
            return '<div style="padding:10px 0;border-top:1px solid #eef2f6"><div style="display:flex;justify-content:space-between;gap:8px"><strong style="font-size:10px">'+esc(r.item_name||'Service Request')+'</strong><span class="lrm-pill">'+esc(r.status_label||r.status||'Submitted')+'</span></div><div class="lr-card-copy">'+Number(r.points_spent||0)+' points spent'+(returned>0?' · '+returned+' returned':'')+(r.delivery_url?' · <a href="'+esc(r.delivery_url)+'" target="_blank" rel="noopener">Open delivery</a>':'')+'</div>'+(r.delivery_notes?'<div class="lr-card-copy" style="margin-top:4px">'+esc(r.delivery_notes)+'</div>':'')+'</div>';
        }).join('')||'<div class="lr-detail-empty">No service requests yet.</div>';

        var historyHolder=q('[data-lrm-history]');
        if(historyHolder)historyHolder.innerHTML=history.length?history.map(pointActivityRow).join(''):'<div class="lr-detail-empty">No point activity yet.</div>';
        var preview=q('[data-lrm-activity-preview]');
        if(preview)preview.innerHTML=history.length?history.slice(0,8).map(pointActivityRow).join(''):'<div class="lr-detail-empty">No point activity yet.</div>';
    }

    async function loadSchools(force){if(loaded.schools&&!force){renderSchools();return}try{var j=await api(drawer.dataset.recruitingSchoolsUrl+'?per_page=60');data.schools=j.items||[];data.lists=j.lists||data.lists;loaded.schools=true;renderSchools()}catch(e){q('[data-lrm-schools]').innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>'}}
    async function loadFavorites(force){if(loaded.favorites&&!force){renderFavorites();return}try{var j=await api(drawer.dataset.recruitingFavoritesUrl+'?per_page=60');data.favorites=j.items||[];loaded.favorites=true;renderFavorites()}catch(e){q('[data-lrm-favorites]').innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>'}}
    async function loadLists(force){if(loaded.lists&&!force){renderLists();return}try{var j=await api(drawer.dataset.recruitingListsUrl);data.lists=j.items||[];loaded.lists=true;if(!loaded.schools)await loadSchools(false);renderLists()}catch(e){q('[data-lrm-lists]').innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>'}}
    async function loadInbox(force){if(loaded.inbox&&!force){renderInbox();return}try{var term=encodeURIComponent(q('[data-lrm-inbox-search]')?.value||''),url=drawer.dataset.recruitingConversationsUrl+'?q='+term+'&limit=100'+(force?'&refresh=1':'');var j=await api(url);data.conversations=j.conversations||[];loaded.inbox=true;renderInbox()}catch(e){q('[data-lrm-inbox-list]').innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>'}}
    async function loadCompose(force){if(loaded.compose&&!force){renderCompose();return}try{if(!loaded.schools)await loadSchools(false);var t=await api(drawer.dataset.recruitingTemplatesUrl);data.templates=t.items||[];loaded.compose=true;renderCompose()}catch(e){toast(e.message,true)}}
    async function loadPoints(force){if(loaded.points&&!force){renderPoints();return}try{var results=await Promise.all([api(drawer.dataset.pointsUrl),drawer.dataset.pointsHistoryUrl?api(drawer.dataset.pointsHistoryUrl):Promise.resolve({entries:[]})]);data.points=results[0];data.pointHistory=results[1].entries||[];if(results[1].balance!=null)data.points.balance=results[1].balance;loaded.points=true;renderPoints()}catch(e){toast(e.message,true)}}

    function setInboxWindow(name){
        qa('[data-lrm-inbox-window]').forEach(function(el){el.classList.toggle('is-active',el.dataset.lrmInboxWindow===name)});
    }
    async function openConversation(id,preserveWindow){
        activeConversation=String(id||'');renderInbox();if(!preserveWindow)setInboxWindow('thread');
        var thread=q('[data-lrm-thread]');if(thread)thread.innerHTML='<div class="lr-detail-empty">Loading latest emails…</div>';
        try{
            var j=await api(drawer.dataset.recruitingConversationsUrl+'/'+encodeURIComponent(id)+'?limit=10'),convo=(data.conversations||[]).find(function(r){return String(r.id||'')===String(id)})||{id:id};
            threadState={conversation:convo,messages:Array.isArray(j.messages)?j.messages:[],cursor:j.last_message_id||null,hasMore:!!j.has_more,loadingOlder:false};
            renderThread();
            requestAnimationFrame(function(){var stream=q('[data-lrm-message-scroll]');if(stream)stream.scrollTop=stream.scrollHeight});
        }catch(e){if(thread)thread.innerHTML='<div class="lr-detail-empty">'+esc(e.message)+'</div>'}
    }
    function onView(view){
        if(view==='schools')loadSchools(false);
        else if(view==='favorites')loadFavorites(false);
        else if(view==='lists')loadLists(false);
        else if(view==='inbox'){setInboxWindow('list');setInboxMaximized(false);loadInbox(false);}
        else if(view==='compose')loadCompose(false);
        else if(view==='points'){setPointsWindow('home');loadPoints(false);}
    }

    document.addEventListener('click',function(event){
        var nav=event.target.closest('[data-lr-nav]');if(nav&&drawer.contains(nav))setTimeout(function(){onView(nav.dataset.lrNav)},0);
        var inboxBack=event.target.closest('[data-lrm-inbox-back]');if(inboxBack&&drawer.contains(inboxBack)){setInboxWindow('list');return}
        var inboxSize=event.target.closest('[data-lrm-inbox-size]');if(inboxSize&&drawer.contains(inboxSize)){setInboxMaximized(!inboxMaximized);return}
        var loadOlder=event.target.closest('[data-lrm-load-older]');if(loadOlder&&drawer.contains(loadOlder)){loadOlderMessages();return}
        var pointsOpen=event.target.closest('[data-lrm-points-open]');if(pointsOpen&&drawer.contains(pointsOpen)){setPointsWindow(pointsOpen.dataset.lrmPointsOpen);return}
        var pointsBack=event.target.closest('[data-lrm-points-back]');if(pointsBack&&drawer.contains(pointsBack)){setPointsWindow('home');return}
        var activityToggle=event.target.closest('[data-lrm-activity-preview-toggle]');if(activityToggle&&drawer.contains(activityToggle)){var preview=q('[data-lrm-activity-preview]'),minimized=preview?.classList.toggle('is-minimized');activityToggle.textContent=minimized?'Maximize':'Minimize';return}
        var view=event.target.closest('[data-lrm-school-view]');if(view&&drawer.contains(view)){schoolView=view.dataset.lrmSchoolView==='list'?'list':'grid';renderSchools();return}
        var refreshSchools=event.target.closest('[data-lrm-schools-refresh]');if(refreshSchools&&drawer.contains(refreshSchools)){loaded.schools=false;loadSchools(true);return}
        var fav=event.target.closest('[data-lrm-favorite]');if(fav&&drawer.contains(fav)){event.preventDefault();event.stopPropagation();var want=fav.dataset.favorite!=='1';api(drawer.dataset.dashboardSchoolFavoriteUrl,{method:'POST',body:{school_id:fav.dataset.lrmFavorite,favorite:want}}).then(function(){loaded.schools=false;loaded.favorites=false;loadSchools(true);if(q('[data-lr-view="favorites"]')?.classList.contains('is-active'))loadFavorites(true);toast(want?'School favorited.':'Favorite removed.')}).catch(function(e){toast(e.message,true)});return}
        var addToggle=event.target.closest('[data-lrm-list-add-toggle]');if(addToggle&&drawer.contains(addToggle)){var key=addToggle.dataset.lrmListAddToggle,panel=q('[data-lrm-list-add-panel="'+CSS.escape(key)+'"]');if(panel){panel.hidden=!panel.hidden;if(!panel.hidden)q('[data-lrm-list-school-search="'+CSS.escape(key)+'"]')?.focus()}return}
        var addSchool=event.target.closest('[data-lrm-add-school]');if(addSchool&&drawer.contains(addSchool)){event.preventDefault();var key2=addSchool.dataset.listKey,id=addSchool.dataset.lrmAddSchool;api(drawer.dataset.dashboardSchoolListUrl,{method:'POST',body:{school_id:id,list_key:key2,in_list:true}}).then(function(){loaded.lists=false;loadLists(true);toast('School added to list.')}).catch(function(e){toast(e.message,true)});return}
        var del=event.target.closest('[data-lrm-list-delete]');if(del&&drawer.contains(del)){event.preventDefault();if(!confirm('Delete this recruiting list?'))return;api(drawer.dataset.recruitingListsUrl+'/'+encodeURIComponent(del.dataset.lrmListDelete),{method:'DELETE'}).then(function(j){data.lists=j.items||[];loaded.lists=true;renderLists();toast('List deleted.')}).catch(function(e){toast(e.message,true)});return}
        var convo=event.target.closest('[data-lrm-conversation]');if(convo&&drawer.contains(convo)){event.preventDefault();openConversation(convo.dataset.lrmConversation);return}
        var refresh=event.target.closest('[data-lrm-inbox-refresh]');if(refresh&&drawer.contains(refresh)){loaded.inbox=false;loadInbox(true);return}
        var choose=event.target.closest('[data-lrm-choose-school]');if(choose&&drawer.contains(choose)){selectedComposeSchool=(data.schools||[]).find(function(s){return String(s.id||'')===String(choose.dataset.lrmChooseSchool)})||null;q('[data-lrm-compose-school-search]').value='';renderComposeSchool();renderComposeSchoolResults();return}
        var clear=event.target.closest('[data-lrm-clear-compose-school]');if(clear&&drawer.contains(clear)){selectedComposeSchool=null;renderComposeSchool();return}
        var recipientMode=event.target.closest('[data-lrm-recipient-mode]');if(recipientMode&&drawer.contains(recipientMode)){var head=recipientMode.dataset.lrmRecipientMode!=='all';q('[data-lrm-head-only]').value=head?'1':'0';qa('[data-lrm-recipient-mode]').forEach(function(b){b.classList.toggle('is-active',b===recipientMode)});return}
        var pack=event.target.closest('[data-lrm-point-pack]');if(pack&&drawer.contains(pack)){document.dispatchEvent(new CustomEvent('plyrcard:locker-point-pack',{detail:{pack:pack.dataset.lrmPointPack}}));return}
        var custom=event.target.closest('[data-lrm-custom-buy]');if(custom&&drawer.contains(custom)){var p=data.points||{},cfg=p.custom_purchase||{},input=q('[data-lrm-custom-points]'),amount=Math.round(Number(input?.value||0)),min=Number(cfg.min_points||1),max=Number(cfg.max_points||1000);if(!Number.isFinite(amount)||amount<min||amount>max){toast('Choose between '+min+' and '+max+' points.',true);return}document.dispatchEvent(new CustomEvent('plyrcard:locker-point-pack',{detail:{pack:'custom-'+amount}}));return}
        var service=event.target.closest('[data-lrm-service]');if(service&&drawer.contains(service)){var p2=data.points||{},cost=Number(service.dataset.points||0);if(Number(p2.balance||0)<cost){toast('You do not have enough points.',true);return}if(!confirm('Use '+cost+' points for this service?'))return;var token='locker-'+Date.now()+'-'+Math.random().toString(36).slice(2,8);api(drawer.dataset.creditUseUrl,{method:'POST',body:{request_token:token,confirm_spend:1,items:[{selected:1,item_key:service.dataset.lrmService,quantity:1,rush:0,notes:'Requested from Locker Room'}]}}).then(function(){loaded.points=false;loadPoints(true);toast('Service requested with PLYR Points.')}).catch(function(e){toast(e.message,true)});return}
    },true);

    document.addEventListener('input',function(event){
        if(event.target.matches('[data-lrm-school-search]'))renderSchools();
        if(event.target.matches('[data-lrm-list-school-search]'))renderListSearch(event.target.dataset.lrmListSchoolSearch);
        if(event.target.matches('[data-lrm-compose-school-search]'))renderComposeSchoolResults();
        if(event.target.matches('[data-lrm-inbox-search]')){clearTimeout(inboxTimer);inboxTimer=setTimeout(function(){loaded.inbox=false;loadInbox(false)},350)}
    },true);
    document.addEventListener('change',function(event){if(event.target.matches('[data-lrm-division-filter],[data-lrm-state-filter],[data-lrm-conference-filter]'))renderSchools()},true);
    document.addEventListener('plyrcard:points-refresh',function(){loaded.points=false;loadPoints(true)});

    document.addEventListener('submit',function(event){
        var replyForm=event.target.closest('[data-lrm-reply-form]');
        if(replyForm&&drawer.contains(replyForm)){event.preventDefault();submitThreadReply(replyForm);}
    },true);

    var listForm=q('[data-lrm-list-form]');
    if(listForm)listForm.addEventListener('submit',async function(event){event.preventDefault();busy(listForm,true,'Creating…');try{var j=await api(drawer.dataset.recruitingListsUrl,{method:'POST',body:new FormData(listForm)});data.lists=j.items||[];loaded.lists=true;listForm.reset();listForm.querySelector('[name="color"]').value='#ff6338';renderLists();toast('List created.')}catch(e){toast(e.message,true)}finally{busy(listForm,false)}});

    var template=q('[data-lrm-template]');
    if(template)template.addEventListener('change',function(){var row=(data.templates||[]).find(function(t){return String(t.id||'')===String(template.value)});if(!row)return;var form=q('[data-lrm-compose-form]');form.querySelector('[name="subject"]').value=row.subject||'';form.querySelector('[name="body"]').value=String(row.body_html||'').replace(/<br\s*\/?>/gi,'\n').replace(/<\/p>/gi,'\n\n').replace(/<[^>]*>/g,'').replace(/&nbsp;/g,' ').trim()});

    var compose=q('[data-lrm-compose-form]');
    if(compose)compose.addEventListener('submit',async function(event){
        event.preventDefault();var fd=new FormData(compose),body;
        if(compose.dataset.conversationId){body={target_mode:'conversation',conversation_id:compose.dataset.conversationId,subject:fd.get('subject'),body:fd.get('body')}}
        else{
            if(!selectedComposeSchool){toast('Choose a school before sending.',true);return}
            body={target_mode:'school',school_id:fd.get('school_id'),head_coach_only:fd.get('head_coach_only')==='1',subject:fd.get('subject'),body:fd.get('body')}
        }
        busy(compose,true,'Sending…');
        try{var j=await api(drawer.dataset.recruitingEmailUrl,{method:'POST',body:body});toast(j.message||'Recruiting email sent.');compose.dataset.conversationId='';compose.querySelector('[name="subject"]').value='';compose.querySelector('[name="body"]').value='';loaded.inbox=false}
        catch(e){toast(e.message,true)}finally{busy(compose,false)}
    });
})();
</script>
