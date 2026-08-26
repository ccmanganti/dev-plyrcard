@php
    $lrLoggedIn = auth()->check();
    $lrUser = auth()->user();
    $lrInitialData = $lrLoggedIn && $lrUser
        ? app(\App\Services\LockerRoomDataService::class)->snapshot($lrUser)
        : null;

    $lrDataUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.data')
        ? route('locker-room.data')
        : null;
    $lrProfileUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.profile.update')
        ? route('locker-room.profile.update')
        : null;
    $lrProfileOptionsUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.profile.options')
        ? route('locker-room.profile.options')
        : null;
    $lrDashboardActivityUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.dashboard.activity')
        ? route('locker-room.dashboard.activity')
        : null;
    $lrDashboardSchoolUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.dashboard.school')
        ? route('locker-room.dashboard.school', ['school' => '__SCHOOL__'])
        : null;
    $lrScheduleStoreUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.schedule.store')
        ? route('locker-room.schedule.store')
        : null;
    $lrScheduleBaseUrl = url('/locker-room/schedule');
    $lrSettingsUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.settings.update')
        ? route('locker-room.settings.update')
        : null;
    $lrBillingUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.billing.update')
        ? route('locker-room.billing.update')
        : null;
    $lrReferralUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.referral.store')
        ? route('locker-room.referral.store')
        : null;
    $lrAdditionalServiceUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.additional-service.store')
        ? route('locker-room.additional-service.store')
        : null;
    $lrLoginUrl = \Illuminate\Support\Facades\Route::has('plyrcard.drawer-login')
        ? route('plyrcard.drawer-login')
        : url('/admin/login');
    $lrPasswordResetUrl = \Illuminate\Support\Facades\Route::has('locker-room.password-reset.request')
        ? route('locker-room.password-reset.request')
        : url('/admin/password-reset/request');
    $lrPasswordUpdateUrl = $lrLoggedIn && \Illuminate\Support\Facades\Route::has('locker-room.password.update')
        ? route('locker-room.password.update')
        : null;
    $lrMustChangePassword = $lrLoggedIn && $lrUser && (bool) (($lrUser->must_change_password ?? false) || session('plyrcard_show_password_overlay'));
    $lrLogoutUrl = url('/admin/logout');
    $lrSupportEmail = 'support@plyrcard.com';
    $lrSupportPhone = '+15718880852';
    $lrFacebookUrl = 'https://www.facebook.com/plyrcard';
    $lrMainShareUrl = rtrim((string) config('app.url', url('/')), '/');

    // v10.38: authenticated Locker Room is public-player-site only. The navigation
    // partial resolves ownership and passes $plyrShouldRenderPullup. Never allow a
    // direct include to make Locker Room appear inside Filament/Admin.
    $lrOnAdmin = request()->is('admin') || request()->is('admin/*');
    $lrOnRegistration = request()->is('registration') || request()->is('registration/*');
    $lrRenderAllowed = isset($plyrShouldRenderPullup)
        ? (bool) $plyrShouldRenderPullup
        : (! $lrLoggedIn && ! $lrOnAdmin && ! $lrOnRegistration);

    if ($lrOnAdmin || $lrOnRegistration) {
        $lrRenderAllowed = false;
    }
@endphp

@if($lrRenderAllowed)
<style>
    .lr-drawer, .lr-drawer * { box-sizing: border-box; }
    .lr-drawer { position: fixed; inset: 0; z-index: 2147483600; pointer-events: none; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; }
    .lr-drawer.is-open { pointer-events: auto; }
    .lr-scrim { position: absolute; inset: 0; background: rgba(15,23,42,.42); opacity: 0; transition: opacity .28s ease; backdrop-filter: blur(2px); }
    .lr-drawer.is-open .lr-scrim { opacity: 1; }
    .lr-panel { position: absolute; right: 0; bottom: 0; width: 50vw; min-width: 0; max-width: none; height: 100dvh; background: #fff; border-left: 1px solid #e5e7eb; box-shadow: -28px 0 70px rgba(15,23,42,.18); transform: translateY(102%); transition: transform .38s cubic-bezier(.22,.85,.28,1); display: grid; grid-template-rows: auto 1fr; overflow: hidden; }
    .lr-drawer.is-open .lr-panel { transform: translateY(0); }
    .lr-head { min-height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 22px; border-bottom: 1px solid #e5e7eb; background: rgba(255,255,255,.96); }
    .lr-head-main { min-width: 0; display: flex; align-items: center; gap: 12px; }
    .lr-back, .lr-close { width: 38px; height: 38px; border-radius: 11px; border: 1px solid #e5e7eb; background: #fff; color: #111827; display: inline-grid; place-items: center; cursor: pointer; }
    .lr-back[hidden] { display: none !important; }
    .lr-head h2 { margin: 0; font: 700 25px/1 Antonio, Inter, sans-serif; letter-spacing: -.02em; color: #111827; }
    .lr-head p { margin: 4px 0 0; color: #6b7280; font-size: 12px; }
    .lr-plan { display: inline-flex; align-items: center; border-radius: 999px; padding: 6px 10px; background: #fff3ee; color: #f05b34; font-size: 11px; font-weight: 800; white-space: nowrap; }
    .lr-body { overflow: auto; overscroll-behavior: contain; background: #f7f8fa; padding: 20px 22px calc(28px + env(safe-area-inset-bottom)); }
    .lr-view { display: none; animation: lrFade .16s ease; }
    .lr-view.is-active { display: block; }
    @keyframes lrFade { from { opacity:.45; transform: translateY(4px); } to { opacity:1; transform:none; } }
    .lr-menu-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; }
    .lr-menu-card { appearance: none; border: 1px solid #e5e7eb; background: #fff; color: #111827; border-radius: 16px; min-height: 88px; padding: 16px; display: flex; align-items: center; gap: 13px; text-align: left; cursor: pointer; box-shadow: 0 7px 20px rgba(15,23,42,.045); transition: transform .14s ease, border-color .14s ease, box-shadow .14s ease; text-decoration: none; }
    .lr-menu-card:hover { transform: translateY(-1px); border-color: rgba(255,92,53,.38); box-shadow: 0 12px 25px rgba(15,23,42,.07); }
    .lr-menu-card.is-disabled { opacity: .56; cursor: not-allowed; transform: none; }
    .lr-menu-icon { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 12px; display: grid; place-items: center; background: #fff1ec; color: #ff5c35; }
    .lr-menu-copy strong { display: block; font-size: 14px; line-height: 1.2; }
    .lr-menu-copy small { display: block; margin-top: 4px; color: #8a94a4; font-size: 11px; line-height: 1.3; }
    .lr-coming { display:inline-flex; margin-top:5px; padding:3px 6px; border-radius:999px; background:#eef2f7; color:#667085; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
    .lr-section { display: grid; gap: 14px; }
    .lr-hero { border: 1px solid #e5e7eb; background: #fff; border-radius: 18px; padding: 18px; box-shadow: 0 8px 24px rgba(15,23,42,.045); }
    .lr-eyebrow { color:#ff5c35; font-size:11px; font-weight:850; letter-spacing:.1em; text-transform:uppercase; }
    .lr-hero h3 { margin:6px 0 0; font:700 clamp(26px,3.6vw,38px)/1 Antonio, Inter, sans-serif; letter-spacing:-.03em; color:#101828; }
    .lr-muted { color:#667085; font-size:13px; line-height:1.5; }
    .lr-preparing { display:flex; gap:12px; align-items:flex-start; border:1px solid #fed7c8; background:#fff7f3; border-radius:16px; padding:14px 15px; }
    .lr-preparing i { color:#ff5c35; margin-top:2px; }
    .lr-preparing strong { display:block; font-size:13px; }
    .lr-preparing span { display:block; margin-top:3px; color:#7c5b50; font-size:12px; line-height:1.45; }
    .lr-stat-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
    .lr-stat { border:1px solid #e5e7eb; background:#fff; border-radius:15px; padding:14px; }
    .lr-stat-top { display:flex; justify-content:space-between; gap:8px; align-items:center; }
    .lr-stat-icon { width:34px; height:34px; border-radius:10px; display:grid; place-items:center; background:#fff1ec; color:#ff5c35; }
    .lr-stat-value { margin-top:14px; font-size:28px; font-weight:850; letter-spacing:-.04em; color:#111827; }
    .lr-stat-label { margin-top:2px; color:#667085; font-size:12px; }
    button.lr-stat { width:100%; text-align:left; cursor:pointer; font:inherit; transition:transform .14s ease,border-color .14s ease,box-shadow .14s ease; }
    button.lr-stat:hover { transform:translateY(-1px); border-color:#ffc3b1; box-shadow:0 9px 20px rgba(15,23,42,.065); }
    .lr-stat-open { margin-left:auto; color:#98a2b3; font-size:10px; }
    .lr-dashboard-detail { position:absolute; inset:76px 0 0; z-index:18; display:none; grid-template-rows:auto 1fr; background:#f7f8fa; border-top:1px solid #e5e7eb; }
    .lr-dashboard-detail.is-open { display:grid; }
    .lr-dashboard-detail-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e5e7eb; }
    .lr-dashboard-detail-head-main { min-width:0; display:flex; align-items:center; gap:10px; }
    .lr-dashboard-detail-head h3 { margin:0; color:#111827; font-size:15px; font-weight:850; }
    .lr-dashboard-detail-head p { margin:3px 0 0; color:#667085; font-size:11px; }
    .lr-dashboard-detail-body { overflow:auto; padding:16px 18px 26px; }
    .lr-activity-summary { display:flex; align-items:center; justify-content:space-between; gap:12px; border:1px solid #e5e7eb; background:#fff; border-radius:14px; padding:12px 14px; margin-bottom:11px; }
    .lr-activity-summary strong { font-size:20px; color:#111827; }
    .lr-activity-summary span { color:#667085; font-size:11px; }
    .lr-detail-kpis { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
    .lr-detail-kpi { border:1px solid #e5e7eb; background:#fff; border-radius:14px; padding:13px; min-width:0; }
    .lr-detail-kpi small { display:block; color:#667085; font-size:10px; font-weight:700; }
    .lr-detail-kpi strong { display:block; margin-top:4px; color:#111827; font-size:23px; line-height:1; letter-spacing:-.04em; }
    .lr-detail-kpi em { display:block; margin-top:5px; color:#98a2b3; font-size:9px; font-style:normal; line-height:1.3; }
    .lr-engagement-filters { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
    .lr-engagement-filter { border:1px solid #e5e7eb; background:#fff; border-radius:14px; padding:12px; text-align:center; cursor:pointer; transition:border-color .12s ease,box-shadow .12s ease,transform .12s ease; }
    .lr-engagement-filter:hover { transform:translateY(-1px); border-color:#ffc3b1; }
    .lr-engagement-filter.is-active { border-color:#ff6338; box-shadow:0 0 0 2px rgba(255,99,56,.1); }
    .lr-engagement-filter span { display:block; color:#667085; font-size:10px; font-weight:750; }
    .lr-engagement-filter strong { display:block; margin-top:5px; color:#111827; font-size:24px; line-height:1; }
    .lr-skeleton { position:relative; overflow:hidden; background:#edf0f4!important; color:transparent!important; border-color:#edf0f4!important; }
    .lr-skeleton::after { content:''; position:absolute; inset:0; transform:translateX(-100%); background:linear-gradient(90deg,transparent,rgba(255,255,255,.72),transparent); animation:lrShimmer 1s infinite; }
    @keyframes lrShimmer { to { transform:translateX(100%); } }
    .lr-activity-list { display:grid; gap:9px; }
    .lr-activity-row { width:100%; border:1px solid #e5e7eb; background:#fff; border-radius:14px; padding:12px; display:grid; grid-template-columns:38px minmax(0,1fr) auto; gap:10px; align-items:center; text-align:left; color:#111827; }
    .lr-activity-row.is-clickable, button.lr-activity-row { cursor:pointer; transition:border-color .14s ease, transform .14s ease, box-shadow .14s ease; }
    .lr-activity-row.is-clickable:hover, button.lr-activity-row:hover { border-color:#ffc3b1; transform:translateY(-1px); box-shadow:0 8px 18px rgba(15,23,42,.055); }
    .lr-activity-avatar { width:38px; height:38px; border-radius:11px; background:#fff1ec; color:#ff5c35; display:grid; place-items:center; font-size:12px; font-weight:900; }
    .lr-activity-copy { min-width:0; }
    .lr-activity-copy strong { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12px; }
    .lr-activity-copy small { display:block; margin-top:3px; color:#667085; font-size:10px; line-height:1.35; }
    .lr-school-link { border:0; background:#f8fafc; color:#344054; border-radius:9px; padding:7px 9px; font-size:10px; font-weight:800; cursor:pointer; max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .lr-school-link:hover { background:#fff1ec; color:#e94c28; }
    .lr-school-hero { display:grid; grid-template-columns:54px minmax(0,1fr); gap:12px; align-items:center; border:1px solid #e5e7eb; background:#fff; border-radius:15px; padding:14px; }
    .lr-school-logo { width:54px; height:54px; border-radius:12px; border:1px solid #e5e7eb; background:#fff; object-fit:contain; padding:5px; }
    .lr-school-logo-fallback { width:54px; height:54px; border-radius:12px; background:#fff1ec; color:#ff5c35; display:grid; place-items:center; font-weight:900; }
    .lr-school-meta h4 { margin:0; font-size:16px; }
    .lr-school-meta p { margin:4px 0 0; color:#667085; font-size:11px; }
    .lr-school-roster { display:grid; gap:8px; margin-top:12px; }
    .lr-school-coach { border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:10px 11px; }
    .lr-school-coach strong { display:block; font-size:12px; }
    .lr-school-coach span { display:block; margin-top:2px; color:#667085; font-size:10px; }
    .lr-detail-empty { border:1px dashed #d0d5dd; border-radius:14px; padding:18px; text-align:center; color:#667085; font-size:12px; background:#fff; }
    .lr-card { border:1px solid #e5e7eb; background:#fff; border-radius:16px; padding:15px; }
    .lr-card-title { margin:0; font-size:14px; font-weight:800; color:#111827; }
    .lr-card-copy { margin:5px 0 0; color:#667085; font-size:12px; line-height:1.45; }
    .lr-progress-track { height:8px; border-radius:999px; background:#edf0f4; overflow:hidden; margin-top:12px; }
    .lr-progress-fill { height:100%; border-radius:inherit; background:#ff5c35; width:0; transition:width .3s ease; }
    .lr-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
    .lr-btn { min-height:40px; border:1px solid #dfe3e8; border-radius:11px; background:#fff; color:#111827; padding:9px 13px; font-size:12px; font-weight:800; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:7px; }
    .lr-btn:hover { background:#f8fafc; }
    .lr-btn-primary { background:#ff5c35; border-color:#ff5c35; color:#fff; }
    .lr-btn-primary:hover { background:#e94c28; }
    .lr-btn-danger { color:#b42318; border-color:#fecaca; background:#fff7f7; }
    .lr-form { display:grid; gap:14px; }
    .lr-form-section { border:1px solid #e5e7eb; background:#fff; border-radius:16px; padding:15px; }
    .lr-form-section h4 { margin:0 0 12px; font-size:13px; font-weight:850; color:#111827; }
    .lr-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:11px; }
    .lr-field { display:grid; gap:6px; }
    .lr-field.is-full { grid-column:1/-1; }
    .lr-field label { color:#475467; font-size:11px; font-weight:800; }
    .lr-input, .lr-select, .lr-textarea { width:100%; min-height:42px; border:1px solid #dfe3e8; border-radius:10px; background:#fff; color:#111827; padding:9px 11px; font:500 13px/1.35 Inter, sans-serif; outline:none; }
    .lr-textarea { min-height:105px; resize:vertical; }
    .lr-input:focus, .lr-select:focus, .lr-textarea:focus { border-color:#ff8c6d; box-shadow:0 0 0 3px rgba(255,92,53,.1); }
    .lr-input[readonly] { background:#f8fafc; color:#667085; }
    .lr-tabs { display:flex; gap:7px; overflow:auto; padding-bottom:2px; margin-bottom:12px; }
    .lr-tab { flex:0 0 auto; border:1px solid #e5e7eb; border-radius:999px; background:#fff; color:#667085; padding:7px 10px; font-size:11px; font-weight:800; cursor:pointer; }
    .lr-tab.is-active { background:#fff1ec; border-color:#ffc8b7; color:#e94c28; }
    .lr-profile-pane { display:none; }
    .lr-profile-pane.is-active { display:block; }
    .lr-association-row { display:flex; flex-wrap:wrap; gap:7px; margin-top:9px; }
    .lr-chip { display:inline-flex; align-items:center; border-radius:999px; background:#f2f4f7; color:#475467; padding:6px 9px; font-size:10px; font-weight:750; }
    .lr-schedule-list { display:grid; gap:10px; }
    .lr-schedule-item { border:1px solid #e5e7eb; background:#fff; border-radius:15px; padding:14px; display:grid; grid-template-columns:auto minmax(0,1fr) auto; gap:12px; align-items:start; }
    .lr-datebox { width:54px; min-height:54px; border-radius:12px; background:#fff1ec; color:#e94c28; display:grid; place-items:center; text-align:center; padding:5px; }
    .lr-datebox strong { font-size:18px; line-height:1; }
    .lr-datebox span { margin-top:2px; font-size:9px; font-weight:850; text-transform:uppercase; }
    .lr-schedule-item h4 { margin:0; font-size:14px; }
    .lr-schedule-meta { margin-top:5px; color:#667085; font-size:11px; line-height:1.45; }
    .lr-schedule-tools { display:flex; gap:6px; }
    .lr-icon-btn { width:34px; height:34px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; display:grid; place-items:center; cursor:pointer; color:#475467; }
    .lr-toggle-row { display:flex; justify-content:space-between; gap:16px; align-items:center; padding:12px 0; border-top:1px solid #edf0f3; }
    .lr-toggle-row:first-child { border-top:0; padding-top:0; }
    .lr-toggle-copy strong { display:block; font-size:12px; }
    .lr-toggle-copy span { display:block; margin-top:3px; color:#7b8492; font-size:10px; line-height:1.35; }
    .lr-switch { position:relative; width:44px; height:25px; flex:0 0 44px; }
    .lr-switch input { position:absolute; opacity:0; pointer-events:none; }
    .lr-switch span { position:absolute; inset:0; border-radius:999px; background:#d7dce2; cursor:pointer; transition:.18s ease; }
    .lr-switch span:after { content:""; position:absolute; width:19px; height:19px; top:3px; left:3px; border-radius:50%; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.18); transition:.18s ease; }
    .lr-switch input:checked + span { background:#ff5c35; }
    .lr-switch input:checked + span:after { transform:translateX(19px); }
    .lr-choice-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; margin-top:10px; }
    .lr-choice { border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:12px; cursor:pointer; text-align:left; }
    .lr-choice.is-active { border-color:#ff9d80; background:#fff5f1; }
    .lr-plan-grid { display:grid; gap:11px; }
    .lr-plan-card { border:1px solid #e5e7eb; background:#fff; border-radius:16px; padding:16px; position:relative; }
    .lr-plan-card.is-current { border-color:#ffc1ae; box-shadow:0 0 0 2px rgba(255,92,53,.07); }
    .lr-plan-name { font:700 21px/1 Antonio, sans-serif; text-transform:uppercase; }
    .lr-plan-price { margin-top:7px; font-size:29px; font-weight:850; letter-spacing:-.04em; }
    .lr-plan-price small { font-size:11px; font-weight:700; color:#667085; letter-spacing:0; }
    .lr-plan-list { display:grid; gap:6px; margin:11px 0 0; padding:0; list-style:none; color:#475467; font-size:11px; }
    .lr-plan-list li:before { content:"✓"; color:#16a34a; font-weight:900; margin-right:7px; }
    .lr-embed { width:100%; height:calc(100dvh - 165px); min-height:620px; border:0; border-radius:14px; background:#fff; }
    .lr-gate { text-align:center; padding:30px 18px; }
    .lr-gate-icon { width:60px; height:60px; margin:0 auto 14px; border-radius:18px; display:grid; place-items:center; background:#fff1ec; color:#ff5c35; font-size:22px; }
    .lr-toast { position:absolute; right:20px; bottom:22px; z-index:4; max-width:min(360px,calc(100% - 40px)); border:1px solid #e5e7eb; background:#111827; color:#fff; border-radius:12px; padding:11px 13px; font-size:11px; line-height:1.4; box-shadow:0 16px 35px rgba(15,23,42,.2); opacity:0; transform:translateY(8px); pointer-events:none; transition:.18s ease; }
    .lr-toast.is-showing { opacity:1; transform:none; }
    .lr-drawer-tab { position:fixed; right:24px; bottom:0; z-index:2147483599; min-width:152px; height:43px; border:0; border-radius:14px 14px 0 0; background:#ff5c35; color:#fff; font:700 15px/1 Antonio, sans-serif; letter-spacing:.02em; cursor:pointer; box-shadow:0 -8px 24px rgba(15,23,42,.16); }
    html.lr-open, html.lr-open body { overflow:hidden !important; }
    @media(max-width:900px) {
        .lr-panel { width:100vw; min-width:0; max-width:none; border-left:0; }
        .lr-body { padding:16px 14px calc(22px + env(safe-area-inset-bottom)); }
        .lr-head { min-height:68px; padding:12px 14px; }
        .lr-menu-grid { grid-template-columns:1fr 1fr; gap:9px; }
        .lr-menu-card { min-height:76px; padding:12px; border-radius:14px; }
        .lr-form-grid, .lr-choice-grid { grid-template-columns:1fr; }
        .lr-stat-grid { grid-template-columns:1fr 1fr; }
        .lr-schedule-item { grid-template-columns:auto minmax(0,1fr); }
        .lr-schedule-tools { grid-column:2; }
        .lr-embed { height:calc(100dvh - 138px); min-height:520px; border-radius:0; }
        .lr-drawer-tab { right:12px; min-width:136px; }
    }
    @media(max-width:470px) {
        .lr-menu-grid { grid-template-columns:1fr; }
        .lr-stat-grid { grid-template-columns:1fr 1fr; }
        .lr-plan { display:none; }
    }

    /* v10.38: Get Started uses the same right-side 50% / full-height shell as Locker Room.
       The default .lr-panel geometry above now applies to both authenticated and guest states. */

    /* v10.37 Get Started launcher: restore the original 8-action structure. */
    .lr-guest-shell { display: grid; gap: 20px; width: 100%; }
    .lr-guest-group { display: grid; gap: 9px; }
    .lr-guest-group-title { color: #667085; font-size: 10px; font-weight: 900; letter-spacing: .09em; text-transform: uppercase; }
    .lr-guest-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 10px; }
    .lr-guest-card { min-width: 0; min-height: 92px; border: 1px solid #e2e6ec; border-radius: 14px; background: #fff; color: #101828; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 9px; padding: 13px 9px; text-decoration: none; text-align: center; cursor: pointer; box-shadow: 0 5px 16px rgba(15,23,42,.035); transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease, background .15s ease; }
    .lr-guest-card:hover { transform: translateY(-1px); border-color: #ffb29b; box-shadow: 0 10px 22px rgba(15,23,42,.065); }
    .lr-guest-icon { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: #f5f7fa; color: #101828; font-size: 14px; }
    .lr-guest-card strong { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; color: inherit; font-size: 11px; font-weight: 850; line-height: 1.2; }
    .lr-guest-card.is-accent { background: #ff5c35; border-color: #ff5c35; color: #fff; }
    .lr-guest-card.is-accent .lr-guest-icon { background: rgba(255,255,255,.16); color: #fff; }
    .lr-guest-card.is-accent:hover { background: #ed4e28; border-color: #ed4e28; }
    .lr-login-remember { display: flex; align-items: center; gap: 8px; color: #475467; font-size: 11px; font-weight: 700; }
    .lr-login-remember input { accent-color: #ff5c35; }
        @media (max-width: 900px) { .lr-guest-grid { grid-template-columns: repeat(2,minmax(0,1fr)); gap: 9px; } .lr-guest-card { min-height: 84px; } }
    @media (max-width: 350px) { .lr-guest-grid { grid-template-columns: 1fr; } }

    /* v10.35 conditional visibility + native Locker Room form controls. */
    #plyrcard-action-drawer.lr-drawer [hidden] { display: none !important; }
    .lr-select-wrap { position: relative; }
    .lr-select-wrap > .lr-select { appearance: none; -webkit-appearance: none; padding-right: 36px; cursor: pointer; }
    .lr-select-wrap > i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: 11px; pointer-events: none; }
    .lr-select:disabled { background: #f3f4f6; color: #98a2b3; cursor: not-allowed; }
    .lr-multi { position: relative; }
    .lr-multi-trigger { width: 100%; min-height: 42px; border: 1px solid #dfe3e8; border-radius: 10px; background: #fff; color: #111827; padding: 9px 11px; display: flex; align-items: center; justify-content: space-between; gap: 10px; text-align: left; font: 500 13px/1.35 Inter, sans-serif; cursor: pointer; }
    .lr-multi-trigger:focus { border-color: #ff8c6d; box-shadow: 0 0 0 3px rgba(255,92,53,.1); outline: none; }
    .lr-multi-trigger span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lr-multi-menu { position: absolute; z-index: 30; top: calc(100% + 6px); left: 0; right: 0; max-height: 240px; overflow: auto; border: 1px solid #dfe3e8; border-radius: 12px; background: #fff; padding: 6px; box-shadow: 0 18px 42px rgba(15,23,42,.16); }
    .lr-multi-option { width: 100%; display: flex; align-items: center; gap: 9px; padding: 9px 10px; border: 0; border-radius: 8px; background: transparent; color: #344054; text-align: left; font-size: 12px; font-weight: 650; cursor: pointer; }
    .lr-multi-option:hover, .lr-multi-option.is-selected { background: #fff3ee; color: #e94c28; }
    .lr-multi-check { width: 17px; height: 17px; border-radius: 5px; border: 1px solid #d0d5dd; display: grid; place-items: center; flex: 0 0 17px; font-size: 9px; color: transparent; }
    .lr-multi-option.is-selected .lr-multi-check { background: #ff5c35; border-color: #ff5c35; color: #fff; }
    .lr-form-subsection { margin-top: 13px; padding-top: 13px; border-top: 1px solid #eef0f3; }
    .lr-form-subsection h5 { margin: 0 0 10px; color: #344054; font-size: 11px; font-weight: 850; text-transform: uppercase; letter-spacing: .045em; }
    .lr-media-featured { display: grid; grid-template-columns: 112px minmax(0,1fr); gap: 14px; align-items: center; }
    .lr-media-featured-image { width: 112px; aspect-ratio: 4/5; border-radius: 13px; overflow: hidden; border: 1px solid #e5e7eb; background: #f2f4f7; display: grid; place-items: center; color: #98a2b3; }
    .lr-media-featured-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lr-media-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 9px; margin-top: 11px; }
    .lr-media-thumb { position: relative; aspect-ratio: 1; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; background: #f2f4f7; }
    .lr-media-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lr-media-remove { position: absolute; right: 6px; top: 6px; width: 27px; height: 27px; border: 0; border-radius: 999px; background: rgba(17,24,39,.82); color: #fff; display: grid; place-items: center; cursor: pointer; }
    .lr-upload-box { margin-top: 12px; border: 1px dashed #cfd5dd; border-radius: 13px; background: #fafbfc; padding: 14px; }
    .lr-upload-box input[type=file] { width: 100%; font-size: 12px; color: #475467; }
    .lr-media-add { aspect-ratio: 1; border: 1.5px dashed #c9d0d9; border-radius: 12px; background: #fbfcfd; color: #667085; display: grid; place-items: center; gap: 5px; padding: 10px; text-align: center; cursor: pointer; transition: .15s ease; }
    .lr-media-add:hover { border-color: #ff8c6d; background: #fff5f1; color: #e94c28; }
    .lr-media-add i { font-size: 18px; }
    .lr-media-add span { font-size: 10px; font-weight: 850; }
    .lr-media-pending-badge { position: absolute; left: 6px; bottom: 6px; border-radius: 999px; background: rgba(255,92,53,.92); color: #fff; padding: 3px 6px; font-size: 8px; font-weight: 900; letter-spacing: .035em; text-transform: uppercase; }
    .lr-upload-status { margin-top: 10px; min-height: 17px; color: #667085; font-size: 10px; line-height: 1.45; }
    .lr-upload-status.is-ready { color: #b54708; font-weight: 700; }
    .lr-upload-status.is-success { color: #067647; font-weight: 750; }
    .lr-upload-status.is-error { color: #b42318; font-weight: 750; }
    .lr-payment-card { display: grid; grid-template-columns: 48px minmax(0,1fr) auto; gap: 12px; align-items: center; border: 1px solid #e5e7eb; border-radius: 14px; background: linear-gradient(180deg,#fff,#fafafa); padding: 13px; }
    .lr-payment-icon { width: 48px; height: 34px; border-radius: 8px; display: grid; place-items: center; background: #111827; color: #fff; font-size: 15px; }
    .lr-payment-main strong { display: block; color: #101828; font-size: 13px; }
    .lr-payment-main span { display: block; margin-top: 3px; color: #667085; font-size: 11px; line-height: 1.4; }
    .lr-billing-meta { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 8px; margin-top: 10px; }
    .lr-billing-meta > div { border-radius: 11px; background: #f8fafc; padding: 10px; }
    .lr-billing-meta small { display: block; color: #98a2b3; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .045em; }
    .lr-billing-meta strong { display: block; margin-top: 4px; color: #344054; font-size: 11px; }

    /* Restore the original Locker Room pull-up tab style. */
    .lr-drawer-tab {
        position: fixed !important;
        right: 0 !important;
        bottom: 0 !important;
        z-index: 2147483602 !important;
        width: 210px !important;
        min-width: 210px !important;
        height: 60px !important;
        padding: 0 16px 0 48px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 9px !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: #ff5c35 !important;
        color: #fff !important;
        font: 900 21px/1 Antonio, Inter, sans-serif !important;
        letter-spacing: 0 !important;
        cursor: pointer !important;
        box-shadow: none !important;
        clip-path: polygon(36px 0, 100% 0, 100% 100%, 0 100%) !important;
    }
    .lr-drawer-tab i { font-size: 14px !important; transition: transform .25s ease !important; }
    .lr-drawer.is-open + .lr-drawer-tab { display: inline-flex !important; }
    .lr-drawer.is-open + .lr-drawer-tab i { transform: rotate(180deg) !important; }
    @media (min-width: 901px) {
        .lr-drawer.is-open + .lr-drawer-tab { right: 50vw !important; }
    }

    @media (max-width: 900px) {
        .lr-drawer-tab { width: 190px !important; min-width: 190px !important; height: 56px !important; padding-left: 42px !important; font-size: 19px !important; }
        .lr-media-grid { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .lr-billing-meta { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 520px) {
        .lr-media-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .lr-media-featured { grid-template-columns: 84px minmax(0,1fr); }
        .lr-media-featured-image { width: 84px; }
        .lr-payment-card { grid-template-columns: 42px minmax(0,1fr); }
        .lr-payment-card > .lr-chip { grid-column: 2; justify-self: start; }
        .lr-billing-meta { grid-template-columns: 1fr; }
    }

    /* v10.34 Locker Room visual stabilization.
       Keep every drawer child scoped and stretched so host-page CSS cannot
       collapse the application into a narrow centered column. */
    #plyrcard-action-drawer.lr-drawer .lr-panel {
        top: 0 !important;
        right: 0 !important;
        bottom: auto !important;
        width: 50vw !important;
        height: 100dvh !important;
        min-width: 0 !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        justify-items: stretch !important;
        align-items: stretch !important;
        background: #ffffff !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-panel > .lr-head,
    #plyrcard-action-drawer.lr-drawer .lr-panel > .lr-body {
        width: 100% !important;
        min-width: 0 !important;
        max-width: none !important;
        margin: 0 !important;
        justify-self: stretch !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-head {
        min-height: 78px !important;
        padding: 16px 24px !important;
        box-shadow: 0 1px 0 rgba(15,23,42,.03);
    }
    #plyrcard-action-drawer.lr-drawer .lr-head-main { min-width:0; }
    #plyrcard-action-drawer.lr-drawer .lr-body {
        padding: 0 !important;
        background: #f5f6f8 !important;
        scrollbar-width: thin;
        scrollbar-color: #c9ced6 transparent;
    }
    #plyrcard-action-drawer.lr-drawer .lr-body::-webkit-scrollbar { width: 8px; }
    #plyrcard-action-drawer.lr-drawer .lr-body::-webkit-scrollbar-track { background: transparent; }
    #plyrcard-action-drawer.lr-drawer .lr-body::-webkit-scrollbar-thumb { background: #c9ced6; border-radius: 999px; }
    #plyrcard-action-drawer.lr-drawer .lr-view {
        width: 100% !important;
        min-width: 0 !important;
        max-width: none !important;
        min-height: 100% !important;
        margin: 0 !important;
        padding: 24px 24px 92px !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-view > .lr-section,
    #plyrcard-action-drawer.lr-drawer .lr-section {
        width: 100% !important;
        min-width: 0 !important;
        max-width: none !important;
        margin: 0 !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-home-hero {
        width: 100% !important;
        display: grid !important;
        grid-template-columns: minmax(0,1fr) auto !important;
        align-items: center !important;
        gap: 24px !important;
        padding: 24px 26px !important;
        border-radius: 20px !important;
        border: 1px solid #e2e6eb !important;
        background:
            radial-gradient(circle at 92% 12%, rgba(255,92,53,.11), transparent 28%),
            linear-gradient(180deg,#ffffff 0%,#fbfbfc 100%) !important;
        box-shadow: 0 12px 30px rgba(15,23,42,.055) !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-home-hero-copy { min-width:0; }
    #plyrcard-action-drawer.lr-drawer .lr-home-hero h3 {
        font-size: clamp(34px,3vw,48px) !important;
        margin-top: 8px !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-home-hero .lr-muted {
        max-width: 560px;
        margin: 8px 0 0;
        font-size: 13px;
    }
    #plyrcard-action-drawer.lr-drawer .lr-home-hero-badge {
        width: 104px;
        min-height: 104px;
        border-radius: 22px;
        display: grid;
        place-items: center;
        align-content: center;
        gap: 8px;
        border: 1px solid #ffd2c3;
        background: #fff4ef;
        color: #ff5c35;
        text-align: center;
    }
    #plyrcard-action-drawer.lr-drawer .lr-home-hero-badge span {
        width: 38px; height: 38px; border-radius: 12px; display:grid; place-items:center;
        background:#ff5c35; color:#fff; font-size:15px;
    }
    #plyrcard-action-drawer.lr-drawer .lr-home-hero-badge small {
        color:#ad4e33; font-size:9px; font-weight:900; line-height:1.2; letter-spacing:.08em;
    }
    #plyrcard-action-drawer.lr-drawer .lr-preparing {
        width:100% !important;
        padding:15px 17px !important;
        border-radius:16px !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-home-section-head {
        display:flex; align-items:end; justify-content:space-between; gap:14px; padding:3px 2px 0;
    }
    #plyrcard-action-drawer.lr-drawer .lr-home-section-head strong { display:block; color:#111827; font-size:14px; font-weight:850; }
    #plyrcard-action-drawer.lr-drawer .lr-home-section-head span { display:block; margin-top:3px; color:#7b8492; font-size:11px; }
    #plyrcard-action-drawer.lr-drawer .lr-menu-grid {
        width:100% !important;
        grid-template-columns:repeat(2,minmax(0,1fr)) !important;
        gap:12px !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-menu-card {
        width:100% !important;
        min-width:0 !important;
        min-height:94px !important;
        padding:16px 17px !important;
        border-radius:16px !important;
        box-shadow:0 5px 16px rgba(15,23,42,.035) !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-menu-card:hover {
        border-color:#ffb59e !important;
        box-shadow:0 12px 24px rgba(15,23,42,.065) !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-menu-icon {
        width:44px !important; height:44px !important; flex-basis:44px !important; border-radius:13px !important;
    }
    #plyrcard-action-drawer.lr-drawer .lr-menu-copy { min-width:0; }
    #plyrcard-action-drawer.lr-drawer .lr-menu-copy strong { font-size:14px !important; }
    #plyrcard-action-drawer.lr-drawer .lr-menu-copy small { font-size:11px !important; line-height:1.35 !important; }

    @media (min-width: 901px) and (max-width: 1250px) {
        #plyrcard-action-drawer.lr-drawer .lr-view { padding:20px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-home-hero { padding:21px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-home-hero-badge { width:88px; min-height:88px; }
    }
    @media (max-width: 900px) {
        #plyrcard-action-drawer.lr-drawer .lr-panel { width:100vw !important; height:100dvh !important; border-left:0 !important; }
        #plyrcard-action-drawer.lr-drawer .lr-head { min-height:68px !important; padding:12px 14px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-view { padding:16px 14px calc(22px + env(safe-area-inset-bottom)) !important; }
        #plyrcard-action-drawer.lr-drawer .lr-home-hero { grid-template-columns:1fr !important; gap:14px !important; padding:18px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-home-hero-badge { display:none !important; }
        #plyrcard-action-drawer.lr-drawer .lr-home-hero h3 { font-size:34px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-menu-grid { grid-template-columns:repeat(2,minmax(0,1fr)) !important; gap:9px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-menu-card { min-height:84px !important; padding:13px !important; }
    }
    @media (max-width: 520px) {
        /* Keep Locker Room navigation compact on phones: two actions per row. */
        #plyrcard-action-drawer.lr-drawer .lr-menu-grid { grid-template-columns:repeat(2,minmax(0,1fr)) !important; gap:8px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-menu-card { min-height:88px !important; padding:11px 10px !important; gap:9px !important; align-items:flex-start !important; }
        #plyrcard-action-drawer.lr-drawer .lr-menu-icon { width:38px !important; height:38px !important; flex-basis:38px !important; border-radius:11px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-menu-copy strong { font-size:12.5px !important; line-height:1.2 !important; }
        #plyrcard-action-drawer.lr-drawer .lr-menu-copy small { font-size:10px !important; line-height:1.25 !important; margin-top:3px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-detail-kpis,
        #plyrcard-action-drawer.lr-drawer .lr-engagement-filters { grid-template-columns:repeat(3,minmax(0,1fr)) !important; gap:6px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-detail-kpi,
        #plyrcard-action-drawer.lr-drawer .lr-engagement-filter { padding:9px 6px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-detail-kpi strong,
        #plyrcard-action-drawer.lr-drawer .lr-engagement-filter strong { font-size:19px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-detail-kpi em { font-size:8px !important; }
        #plyrcard-action-drawer.lr-drawer .lr-home-section-head span { display:none; }
    }
</style>

<script type="application/json" id="plyrcard-locker-room-initial">@json($lrInitialData)</script>
<div id="plyrcard-action-drawer" class="lr-drawer" data-state="closed"
     data-url="{{ $lrDataUrl }}"
     data-profile-url="{{ $lrProfileUrl }}"
     data-profile-options-url="{{ $lrProfileOptionsUrl }}"
     data-dashboard-activity-url="{{ $lrDashboardActivityUrl }}"
     data-dashboard-school-url="{{ $lrDashboardSchoolUrl }}"
     data-schedule-url="{{ $lrScheduleStoreUrl }}"
     data-schedule-base-url="{{ $lrScheduleBaseUrl }}"
     data-settings-url="{{ $lrSettingsUrl }}"
     data-billing-url="{{ $lrBillingUrl }}"
     data-referral-url="{{ $lrReferralUrl }}"
     data-additional-service-url="{{ $lrAdditionalServiceUrl }}"
     data-login-url="{{ $lrLoginUrl }}"
     data-password-reset-url="{{ $lrPasswordResetUrl }}"
     data-password-update-url="{{ $lrPasswordUpdateUrl }}"
     data-main-share-url="{{ $lrMainShareUrl }}"
     data-force-password="{{ $lrMustChangePassword ? '1' : '0' }}"
     data-authenticated="{{ $lrLoggedIn ? '1' : '0' }}">
    <button type="button" class="lr-scrim" data-lr-close aria-label="Close Locker Room"></button>
    <section class="lr-panel" role="dialog" aria-modal="true" aria-label="Locker Room">
        <header class="lr-head">
            <div class="lr-head-main">
                <button type="button" class="lr-back" data-lr-back hidden aria-label="Back"><i class="fa-solid fa-chevron-left"></i></button>
                <div>
                    <h2 data-lr-title>{{ $lrLoggedIn ? 'Locker Room' : 'Get Started' }}</h2>
                    <p data-lr-subtitle>{{ $lrLoggedIn ? 'Your player workspace' : 'Everything you need to get started' }}</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:9px;">
                @if($lrLoggedIn)<span class="lr-plan" data-lr-plan>{{ data_get($lrInitialData, 'plan.label', 'Free') }}</span>@endif
                <button type="button" class="lr-close" data-lr-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </header>

        <div class="lr-body">
            @auth
                <section class="lr-view is-active" data-lr-view="home">
                    <div class="lr-section">
                        <div class="lr-hero lr-home-hero">
                            <div class="lr-home-hero-copy">
                                <span class="lr-eyebrow">Locker Room</span>
                                <h3>Hi, {{ $lrUser->first_name ?: 'Player' }}.</h3>
                                <p class="lr-muted">Your PLYRCARD workspace for profile updates, recruiting activity, schedules, settings and account tools.</p>
                            </div>
                            <div class="lr-home-hero-badge" aria-hidden="true">
                                <span><i class="fa-solid fa-bolt"></i></span>
                                <small>PLAYER<br>WORKSPACE</small>
                            </div>
                        </div>
                        <div class="lr-preparing" data-lr-preparing hidden><i class="fa-solid fa-wand-magic-sparkles"></i><div><strong>We are preparing your PLYRCARD.</strong><span>Complete your profile while our team gets your public PLYRCARD and recruiting workspace ready.</span></div></div>
                        <div class="lr-home-section-head">
                            <div><strong>Quick Access</strong><span>Manage your PLYRCARD without leaving this screen.</span></div>
                        </div>
                        <div class="lr-menu-grid">
                            <button class="lr-menu-card" type="button" data-lr-nav="dashboard" data-lr-premium="1"><span class="lr-menu-icon"><i class="fa-solid fa-chart-line"></i></span><span class="lr-menu-copy"><strong>Dashboard</strong><small>Recruiting stats and progress</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="profile"><span class="lr-menu-icon"><i class="fa-solid fa-user-pen"></i></span><span class="lr-menu-copy"><strong>Profile</strong><small>Quick athlete profile edit</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="schedule" data-lr-premium="1"><span class="lr-menu-icon"><i class="fa-solid fa-calendar-days"></i></span><span class="lr-menu-copy"><strong>Schedule</strong><small>Create, view and edit games</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="settings"><span class="lr-menu-icon"><i class="fa-solid fa-gear"></i></span><span class="lr-menu-copy"><strong>Settings</strong><small>Notifications and PLYRCARD display</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-visit-plyrcard><span class="lr-menu-icon"><i class="fa-solid fa-globe"></i></span><span class="lr-menu-copy"><strong>Visit My PLYRCARD</strong><small>Open your public player page</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="share"><span class="lr-menu-icon"><i class="fa-solid fa-share-nodes"></i></span><span class="lr-menu-copy"><strong>Share My PLYRCARD</strong><small>Copy or share your player link</small></span></button>
                            <button class="lr-menu-card is-disabled" type="button" disabled><span class="lr-menu-icon"><i class="fa-solid fa-shield-halved"></i></span><span class="lr-menu-copy"><strong>My Club</strong><span class="lr-coming">Coming Soon</span></span></button>
                            <button class="lr-menu-card is-disabled" type="button" disabled><span class="lr-menu-icon"><i class="fa-solid fa-people-group"></i></span><span class="lr-menu-copy"><strong>My Team</strong><span class="lr-coming">Coming Soon</span></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="upgrade"><span class="lr-menu-icon"><i class="fa-solid fa-arrow-trend-up"></i></span><span class="lr-menu-copy"><strong>Upgrade</strong><small>Plans and current pricing</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="services"><span class="lr-menu-icon"><i class="fa-solid fa-bag-shopping"></i></span><span class="lr-menu-copy"><strong>Additional Services</strong><small>Request extra PLYRCARD services</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="show"><span class="lr-menu-icon"><i class="fa-solid fa-podcast"></i></span><span class="lr-menu-copy"><strong>PLYRCARD Show</strong><small>Podcast and athlete stories</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="refer"><span class="lr-menu-icon"><i class="fa-solid fa-user-plus"></i></span><span class="lr-menu-copy"><strong>Refer a Friend</strong><small>Invite an athlete by email</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="support"><span class="lr-menu-icon"><i class="fa-solid fa-headset"></i></span><span class="lr-menu-copy"><strong>Support</strong><small>Send a support request</small></span></button>
                            <button class="lr-menu-card" type="button" data-lr-nav="book-call"><span class="lr-menu-icon"><i class="fa-solid fa-calendar-check"></i></span><span class="lr-menu-copy"><strong>Book a Call</strong><small>Schedule time with our team</small></span></button>
                            <form method="POST" action="{{ $lrLogoutUrl }}" style="display:contents;">@csrf<button class="lr-menu-card" type="submit"><span class="lr-menu-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="lr-menu-copy"><strong>Sign Out</strong><small>Securely end this session</small></span></button></form>
                        </div>
                    </div>
                </section>

                <section class="lr-view" data-lr-view="dashboard">
                    <div class="lr-section">
                        <div class="lr-preparing" data-lr-preparing hidden><i class="fa-solid fa-wand-magic-sparkles"></i><div><strong>We are preparing your PLYRCARD.</strong><span>Complete your profile while our team gets your public PLYRCARD and recruiting workspace ready.</span></div></div>
                        <div class="lr-card"><div style="display:flex;justify-content:space-between;gap:12px;align-items:end;"><div><h3 class="lr-card-title">Profile Completion</h3><p class="lr-card-copy">A stronger profile gives coaches more useful information.</p></div><strong data-lr-completion style="font-size:24px;">0%</strong></div><div class="lr-progress-track"><div class="lr-progress-fill" data-lr-progress></div></div><div class="lr-actions"><button class="lr-btn lr-btn-primary" type="button" data-lr-nav="profile">Complete My Profile</button></div></div>
                        <div class="lr-stat-grid">
                            <button class="lr-stat" type="button" data-lr-dashboard-metric="profile_views"><div class="lr-stat-top"><span class="lr-stat-icon"><i class="fa-solid fa-eye"></i></span><i class="fa-solid fa-chevron-right lr-stat-open"></i></div><div class="lr-stat-value" data-lr-stat="profile_views">0</div><div class="lr-stat-label">Profile Views</div></button>
                            <div class="lr-stat" aria-label="Favorites"><div class="lr-stat-top"><span class="lr-stat-icon"><i class="fa-solid fa-star"></i></span></div><div class="lr-stat-value" data-lr-stat="favorites">0</div><div class="lr-stat-label">Favorites</div></div>
                            <button class="lr-stat" type="button" data-lr-dashboard-metric="social_clicks"><div class="lr-stat-top"><span class="lr-stat-icon"><i class="fa-solid fa-envelope"></i></span><i class="fa-solid fa-chevron-right lr-stat-open"></i></div><div class="lr-stat-value" data-lr-stat="social_clicks">0</div><div class="lr-stat-label">Coach Engagement</div></button>
                            <div class="lr-stat" aria-label="Emails Sent"><div class="lr-stat-top"><span class="lr-stat-icon"><i class="fa-solid fa-chart-line"></i></span></div><div class="lr-stat-value" data-lr-stat="emails_sent">0</div><div class="lr-stat-label">Emails Sent</div></div>
                        </div>
                        <div class="lr-card" data-lr-next-schedule><h3 class="lr-card-title">Next Schedule</h3><p class="lr-card-copy">No upcoming game has been added yet.</p><div class="lr-actions"><button class="lr-btn" type="button" data-lr-nav="schedule">Open Schedule</button></div></div>
                    </div>
                </section>

                <section class="lr-view" data-lr-view="profile">
                    <form class="lr-form" data-lr-profile-form enctype="multipart/form-data">
                        <div class="lr-tabs" data-lr-profile-tabs>
                            <button type="button" class="lr-tab is-active" data-pane="basic">Basic</button>
                            <button type="button" class="lr-tab" data-pane="athlete">Athlete</button>
                            <button type="button" class="lr-tab" data-pane="bio">Bio</button>
                            <button type="button" class="lr-tab" data-pane="social">Social</button>
                            <button type="button" class="lr-tab" data-pane="people">People</button>
                            <button type="button" class="lr-tab" data-pane="media">Media</button>
                        </div>

                        <div class="lr-profile-pane is-active" data-lr-profile-pane="basic">
                            <div class="lr-form-section"><h4>Personal Information</h4><div class="lr-form-grid">
                                <div class="lr-field"><label>First Name</label><input class="lr-input" name="first_name" required></div>
                                <div class="lr-field"><label>Last Name</label><input class="lr-input" name="last_name" required></div>
                                <div class="lr-field"><label>PLYRCARD Login Email</label><input class="lr-input" name="email_display" readonly></div>
                                <div class="lr-field"><label>Personal Email</label><input class="lr-input" type="email" name="personal_email"></div>
                                <div class="lr-field"><label>Phone</label><input class="lr-input" name="phone" placeholder="555-555-5555"></div>
                            </div><div class="lr-form-subsection"><h5>Address</h5><div class="lr-form-grid">
                                <div class="lr-field is-full"><label>Street Address</label><input class="lr-input" name="street"></div>
                                <div class="lr-field"><label>City</label><input class="lr-input" name="city"></div>
                                <div class="lr-field"><label>State / Province</label><input class="lr-input" name="state"></div>
                                <div class="lr-field"><label>Country</label><input class="lr-input" name="country"></div>
                            </div></div></div>
                        </div>

                        <div class="lr-profile-pane" data-lr-profile-pane="athlete">
                            <div class="lr-form-section"><h4>Sport Details</h4><div class="lr-form-grid">
                                <div class="lr-field"><label>Sport</label><div class="lr-select-wrap"><select class="lr-select" name="sport" required data-lr-sport></select><i class="fa-solid fa-chevron-down"></i></div></div>
                                <div class="lr-field"><label>Position</label><div class="lr-multi" data-lr-position-picker><button class="lr-multi-trigger" type="button" data-lr-position-trigger><span>Select position</span><i class="fa-solid fa-chevron-down"></i></button><div class="lr-multi-menu" data-lr-position-menu hidden></div><div data-lr-position-hidden></div></div></div>
                                <div class="lr-field"><label>Roster Number</label><input class="lr-input" name="jersey_number"></div>
                                <div class="lr-field"><label>Graduation Year</label><input class="lr-input" type="number" min="2000" max="2100" name="year"></div>
                                <div class="lr-field"><label>Sex</label><div class="lr-select-wrap"><select class="lr-select" name="gender" data-lr-gender><option value="">Select sex</option><option value="male">Male</option><option value="female">Female</option></select><i class="fa-solid fa-chevron-down"></i></div></div>
                                <div class="lr-field"><label>Birth Date</label><input class="lr-input" type="date" name="birth"></div>
                                <div class="lr-field"><label>GPA</label><input class="lr-input" type="number" step="0.01" min="0" max="5" name="gpa"></div>
                                <div class="lr-field"><label>NCAA Field ID</label><input class="lr-input" name="ncaa_field_id"></div>
                            </div><div class="lr-form-subsection"><h5>Physical Stats</h5><div class="lr-form-grid">
                                <div class="lr-field"><label>Height</label><input class="lr-input" name="height" placeholder="6'2\""></div>
                                <div class="lr-field"><label>Weight</label><input class="lr-input" name="weight" placeholder="185 lbs"></div>
                                <div class="lr-field"><label>Dominant Foot</label><div class="lr-select-wrap"><select class="lr-select" name="dominant_foot"><option value="">Not set</option><option value="left">Left</option><option value="right">Right</option><option value="both">Both</option></select><i class="fa-solid fa-chevron-down"></i></div></div>
                                <div class="lr-field"><label>Max Speed</label><input class="lr-input" type="number" step="0.01" min="0" name="max_speed"></div>
                            </div></div><div class="lr-form-subsection"><h5>School & Team</h5><div class="lr-form-grid">
                                <div class="lr-field"><label>School</label><div class="lr-select-wrap"><select class="lr-select" name="school_id" data-lr-school><option value="">Loading schools...</option></select><i class="fa-solid fa-chevron-down"></i></div></div>
                                <div class="lr-field"><label>League</label><div class="lr-select-wrap"><select class="lr-select" name="league_id" data-lr-league><option value="">Select sport and sex first</option></select><i class="fa-solid fa-chevron-down"></i></div></div>
                                <div class="lr-field"><label>Club</label><div class="lr-select-wrap"><select class="lr-select" name="club_id" data-lr-club><option value="">Select league first</option></select><i class="fa-solid fa-chevron-down"></i></div></div>
                                <div class="lr-field"><label>Age Group</label><div class="lr-select-wrap"><select class="lr-select" name="team_name" data-lr-age-group><option value="">Select club first</option></select><i class="fa-solid fa-chevron-down"></i></div></div>
                            </div></div><div class="lr-form-subsection"><h5>Experience</h5><div class="lr-form-grid">
                                <div class="lr-field"><label>National Team</label><div class="lr-select-wrap"><select class="lr-select" name="national_team_id" data-lr-national-team><option value="">Loading national teams...</option></select><i class="fa-solid fa-chevron-down"></i></div></div>
                                <div class="lr-field"><label>National Team Period</label><input class="lr-input" name="national_team_period" placeholder="e.g. 2025-2026"></div>
                                <div class="lr-field"><label>Pro Club</label><input class="lr-input" name="pro_club_name" placeholder="Professional club name"></div>
                                <div class="lr-field"><label>Pro Club Logo</label><input class="lr-input" type="file" name="pro_club_logo" accept="image/*"></div>
                            </div><div data-lr-pro-club-preview style="margin-top:10px;"></div></div></div>
                        </div>

                        <div class="lr-profile-pane" data-lr-profile-pane="bio">
                            <div class="lr-form-section"><h4>Bio & Accolades</h4><div class="lr-form-grid">
                                <div class="lr-field is-full"><label>Player Bio</label><textarea class="lr-textarea" name="player_bio"></textarea></div>
                                <div class="lr-field is-full"><label>Academic Accolades</label><textarea class="lr-textarea" name="academic_accolades" placeholder="One accolade per line"></textarea></div>
                                <div class="lr-field is-full"><label>Sports Accolades</label><textarea class="lr-textarea" name="sports_accolades" placeholder="One accolade per line"></textarea></div>
                            </div></div>
                        </div>

                        <div class="lr-profile-pane" data-lr-profile-pane="social">
                            <div class="lr-form-section"><h4>Social & Highlights</h4><div data-lr-social-lock class="lr-preparing" hidden><i class="fa-solid fa-lock"></i><div><strong>Unlock Social & Video Links</strong><span>Upgrade to My Journey to add social links and video highlights to your PLYRCARD.</span></div></div><div class="lr-form-grid" data-lr-social-fields>
                                <div class="lr-field"><label>Instagram Handle</label><input class="lr-input" name="ig_handle" placeholder="yourhandle"></div>
                                <div class="lr-field"><label>X Handle</label><input class="lr-input" name="x_handle" placeholder="yourhandle"></div>
                                <div class="lr-field is-full"><label>YouTube URL</label><input class="lr-input" type="url" name="yt_url" placeholder="https://youtube.com/@yourchannel"></div>
                                <div class="lr-field is-full"><label>Featured Video URL</label><input class="lr-input" type="url" name="featured_video_url"></div>
                                <div class="lr-field is-full"><label>Featured Video URLs</label><textarea class="lr-textarea" name="featured_video_urls" placeholder="One URL per line"></textarea></div>
                            </div></div>
                        </div>

                        <div class="lr-profile-pane" data-lr-profile-pane="people">
                            <div class="lr-form-section"><h4>Parents / Guardians</h4><div class="lr-form-grid">
                                <div class="lr-field"><label>Primary Parent</label><input class="lr-input" name="parent"></div><div class="lr-field"><label>Primary Parent Email</label><input class="lr-input" type="email" name="parent_email"></div><div class="lr-field"><label>Primary Parent Phone</label><input class="lr-input" name="parent_phone"></div>
                                <div class="lr-field"><label>Secondary Parent</label><input class="lr-input" name="sec_parent"></div><div class="lr-field"><label>Secondary Parent Email</label><input class="lr-input" type="email" name="sec_parent_email"></div><div class="lr-field"><label>Secondary Parent Phone</label><input class="lr-input" name="sec_parent_phone"></div>
                            </div></div>
                            <div class="lr-form-section"><h4>Coaches</h4><div class="lr-form-grid">
                                <div class="lr-field"><label>Club Coach</label><input class="lr-input" name="club_coach"></div><div class="lr-field"><label>Club Coach Email</label><input class="lr-input" type="email" name="club_coach_email"></div><div class="lr-field"><label>Club Coach Phone</label><input class="lr-input" name="club_coach_phone"></div>
                                <div class="lr-field"><label>National Team Coach</label><input class="lr-input" name="natl_coach"></div><div class="lr-field"><label>National Team Coach Email</label><input class="lr-input" type="email" name="natl_coach_email"></div><div class="lr-field"><label>National Team Coach Phone</label><input class="lr-input" name="natl_coach_phone"></div>
                            </div></div>
                            <div class="lr-form-section"><h4>Trainers</h4><div class="lr-form-grid">
                                <div class="lr-field"><label>Technical Trainer</label><input class="lr-input" name="tech_trainer"></div><div class="lr-field"><label>Technical Trainer Email</label><input class="lr-input" type="email" name="tech_trainer_email"></div><div class="lr-field"><label>Technical Trainer Phone</label><input class="lr-input" name="tech_trainer_phone"></div>
                                <div class="lr-field"><label>Strength & Conditioning Trainer</label><input class="lr-input" name="snc_trainer"></div><div class="lr-field"><label>Trainer Email</label><input class="lr-input" type="email" name="snc_trainer_email"></div><div class="lr-field"><label>Trainer Phone</label><input class="lr-input" name="snc_trainer_phone"></div>
                            </div></div>
                        </div>

                        <div class="lr-profile-pane" data-lr-profile-pane="media">
                            <div class="lr-form-section"><h4>PLYRCARD Images</h4><div class="lr-media-featured" data-lr-profile-image-preview></div><p class="lr-card-copy" style="margin-top:10px;">Your processed PLYRCARD images are prepared by our team. Upload raw player photos below and we can use them when preparing your card.</p></div>
                            <div class="lr-form-section"><h4>Raw Player Images</h4><p class="lr-card-copy">Upload up to 20 player photos. New selections preview here before upload, and saved photos remain visible after saving.</p><div class="lr-media-grid" data-lr-raw-images></div><div data-lr-raw-existing-hidden></div><input type="file" name="raw_player_images_new[]" accept="image/*" multiple data-lr-raw-file-input hidden><div class="lr-upload-status" data-lr-raw-status>No images selected.</div></div>
                        </div>

                        <button class="lr-btn lr-btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Quick Profile</button>
                    </form>
                </section>

                <section class="lr-view" data-lr-view="schedule">
                    <div class="lr-section">
                        <div class="lr-preparing" data-lr-preparing hidden><i class="fa-solid fa-wand-magic-sparkles"></i><div><strong>We are preparing your PLYRCARD.</strong><span>You can still manage your schedule while we finish preparing the rest of your recruiting workspace.</span></div></div>
                        <div class="lr-card"><div style="display:flex;align-items:center;justify-content:space-between;gap:10px;"><div><h3 class="lr-card-title">My Schedule</h3><p class="lr-card-copy"><span data-lr-schedule-count>0</span> schedule items</p></div><button class="lr-btn lr-btn-primary" type="button" data-lr-new-schedule><i class="fa-solid fa-plus"></i> New</button></div></div>
                        <form class="lr-form-section" data-lr-schedule-form hidden><input type="hidden" name="schedule_id"><h4 data-lr-schedule-form-title>New Schedule</h4><div class="lr-form-grid">
                            <div class="lr-field"><label>Opponent</label><input class="lr-input" name="opponent" required></div>
                            <div class="lr-field"><label>Title</label><input class="lr-input" name="title" placeholder="Optional"></div>
                            <div class="lr-field"><label>Date</label><input class="lr-input" type="date" name="game_date" required></div>
                            <div class="lr-field"><label>Time</label><input class="lr-input" type="time" name="game_time"></div>
                            <div class="lr-field"><label>Status</label><select class="lr-select" name="status"><option value="upcoming">Upcoming</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="postponed">Postponed</option></select></div>
                            <div class="lr-field"><label>Home / Away</label><select class="lr-select" name="is_home"><option value="1">Home</option><option value="0">Away</option></select></div>
                            <div class="lr-field"><label>Location</label><input class="lr-input" name="location"></div>
                            <div class="lr-field"><label>Venue</label><input class="lr-input" name="venue"></div>
                            <div class="lr-field"><label>Result</label><input class="lr-input" name="result"></div>
                            <div class="lr-field"><label>Score</label><input class="lr-input" name="score"></div>
                            <div class="lr-field is-full"><label>Notes</label><textarea class="lr-textarea" name="notes"></textarea></div>
                        </div><div class="lr-actions"><button class="lr-btn lr-btn-primary" type="submit">Save Schedule</button><button class="lr-btn" type="button" data-lr-cancel-schedule>Cancel</button></div></form>
                        <div class="lr-schedule-list" data-lr-schedule-list></div>
                    </div>
                </section>

                <section class="lr-view" data-lr-view="settings">
                    <form class="lr-form" data-lr-settings-form>
                        <div class="lr-form-section"><h4>Notifications</h4><div data-lr-notifications></div></div>
                        <div class="lr-form-section"><h4>PLYRCARD Display</h4><div data-lr-website-settings><p class="lr-card-copy">Choose what appears in the contact section of your PLYRCARD.</p><div class="lr-choice-grid"><button type="button" class="lr-choice" data-lr-article="follow_me"><strong>Follow Me</strong><div class="lr-card-copy">Show the coach follow-up form.</div></button><button type="button" class="lr-choice" data-lr-article="calendar"><strong>Calendar</strong><div class="lr-card-copy">Show your booking calendar.</div></button></div><input type="hidden" name="article_section_type" data-lr-article-value></div><div class="lr-preparing" data-lr-website-lock hidden><i class="fa-solid fa-lock"></i><div><strong>My Journey feature</strong><span>Upgrade to control the contact section shown on your public PLYRCARD.</span></div></div></div>
                        <div class="lr-form-section"><h4>Billing &amp; Payments</h4><p class="lr-card-copy">Manage the person responsible for payment, your saved payment method, subscription status, and billing address.</p><div class="lr-actions" style="margin-top:12px;"><button class="lr-btn" type="button" data-lr-nav="billing"><i class="fa-solid fa-credit-card"></i> Open Billing &amp; Payments</button></div></div>
                        <button class="lr-btn lr-btn-primary" type="submit">Save Settings</button>
                    </form>
                </section>

                <section class="lr-view" data-lr-view="upgrade"><div class="lr-section"><div class="lr-hero"><span class="lr-eyebrow">Plans</span><h3>Build Your Recruiting Reach</h3><p class="lr-muted">Choose the level of support that fits where you are right now.</p></div><div class="lr-plan-grid" data-lr-plans></div></div></section>

                <section class="lr-view" data-lr-view="share"><div class="lr-section"><div class="lr-card" data-lr-share-card></div></div></section>

                <section class="lr-view" data-lr-view="services"><form class="lr-form" data-lr-service-form><div class="lr-form-section"><h4>Additional Services</h4><p class="lr-card-copy">Tell us what extra support you need.</p><div class="lr-form-grid" style="margin-top:12px;"><div class="lr-field"><label>Service</label><select class="lr-select" name="service_key" required><option value="upgraded_site_design">Upgraded Site Design</option><option value="starting_graphics_bundle">Starting Graphics Bundle</option><option value="individual_graphic">Individual Graphic</option><option value="domain">Domain</option><option value="custom">Custom / Other</option></select></div><div class="lr-field"><label>Service Name</label><input class="lr-input" name="service_name" placeholder="Optional custom name"></div><div class="lr-field"><label>Listed Price</label><input class="lr-input" name="listed_price" placeholder="Optional"></div><div class="lr-field is-full"><label>Notes</label><textarea class="lr-textarea" name="notes"></textarea></div></div></div><button class="lr-btn lr-btn-primary" type="submit">Send Request</button></form></section>

                <section class="lr-view" data-lr-view="show"><div class="lr-section"><div class="lr-hero"><span class="lr-eyebrow">PLYRCARD Show</span><h3>Stories, Recruiting & The Game</h3><p class="lr-muted">Explore PLYRCARD conversations and athlete stories. Opening the show is an intentional external action, not Locker Room navigation.</p><div class="lr-actions"><a class="lr-btn lr-btn-primary" href="/podcast">Open PLYRCARD Show</a></div></div></div></section>

                <section class="lr-view" data-lr-view="refer"><form class="lr-form" data-lr-referral-form><div class="lr-form-section"><h4>Refer a Friend</h4><p class="lr-card-copy">Invite another athlete to PLYRCARD. We only need their name and email.</p><div class="lr-form-grid" style="margin-top:12px;"><div class="lr-field"><label>Friend's Name</label><input class="lr-input" name="friend_name" required></div><div class="lr-field"><label>Friend's Email</label><input class="lr-input" type="email" name="friend_email" required></div><div class="lr-field is-full"><label>Message</label><textarea class="lr-textarea" name="message" placeholder="Optional message"></textarea></div></div></div><button class="lr-btn lr-btn-primary" type="submit">Send Invitation Email</button><div class="lr-upload-status" data-lr-referral-status>The invitation will be emailed directly to your friend.</div></form></section>

                <section class="lr-view" data-lr-view="support"><div class="lr-card" style="padding:0;overflow:hidden;"><div style="padding:14px 15px;border-bottom:1px solid #e5e7eb;"><h3 class="lr-card-title">Support</h3><p class="lr-card-copy">Tell us what you need help with.</p></div><div data-lr-support-embed></div></div></section>

                <section class="lr-view" data-lr-view="book-call"><div class="lr-card" style="padding:0;overflow:hidden;"><div style="padding:14px 15px;border-bottom:1px solid #e5e7eb;"><h3 class="lr-card-title">Book a Call</h3><p class="lr-card-copy">Choose a time that works for you. Booking stays inside Locker Room.</p></div><div data-lr-book-embed></div></div></section>

                <section class="lr-view" data-lr-view="billing">
                    <form class="lr-form" data-lr-billing-form>
                        <div class="lr-card" data-lr-billing-summary></div>
                        <div class="lr-form-section"><h4>Payment Method</h4><div data-lr-payment-method></div></div>
                        <div class="lr-form-section"><h4>Billing Contact</h4><div class="lr-form-grid"><div class="lr-field"><label>Full Name</label><input class="lr-input" name="billing_name" required></div><div class="lr-field"><label>Email</label><input class="lr-input" type="email" name="billing_email" required></div><div class="lr-field"><label>Phone</label><input class="lr-input" name="billing_phone"></div><div class="lr-field"><label>Company / Organization</label><input class="lr-input" name="billing_company"></div></div></div>
                        <div class="lr-form-section"><h4>Billing Address</h4><div class="lr-form-grid"><div class="lr-field is-full"><label>Address Line 1</label><input class="lr-input" name="billing_address_1" required></div><div class="lr-field is-full"><label>Address Line 2</label><input class="lr-input" name="billing_address_2"></div><div class="lr-field"><label>City</label><input class="lr-input" name="billing_city" required></div><div class="lr-field"><label>State / Province</label><input class="lr-input" name="billing_state" required></div><div class="lr-field"><label>Postal Code</label><input class="lr-input" name="billing_postal_code" required></div><div class="lr-field"><label>Country</label><input class="lr-input" name="billing_country" required></div></div></div>
                        <button class="lr-btn lr-btn-primary" type="submit">Save Billing Information</button>
                    </form>
                </section>

                <section class="lr-view" data-lr-view="password">
                    <form class="lr-form" data-lr-password-form>
                        <div class="lr-form-section">
                            <h4>Change Your Password</h4>
                            <p class="lr-card-copy">Create a secure password before continuing in your Locker Room.</p>
                            <div class="lr-form-grid" style="margin-top:12px;">
                                <div class="lr-field is-full"><label>New Password</label><input class="lr-input" type="password" name="password" minlength="8" autocomplete="new-password" required></div>
                                <div class="lr-field is-full"><label>Confirm Password</label><input class="lr-input" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></div>
                            </div>
                        </div>
                        <button class="lr-btn lr-btn-primary" type="submit">Save Password</button>
                    </form>
                </section>

                <section class="lr-view" data-lr-view="gate"><div class="lr-card lr-gate"><div class="lr-gate-icon"><i class="fa-solid fa-lock"></i></div><h3 class="lr-card-title" data-lr-gate-title>Available with My Journey</h3><p class="lr-card-copy">Your Free plan keeps Profile and Settings available. Upgrade to unlock recruiting stats, Schedule, and the full recruiting workspace.</p><div class="lr-actions" style="justify-content:center;"><button class="lr-btn" type="button" data-lr-back>Not now</button><button class="lr-btn lr-btn-primary" type="button" data-lr-nav="upgrade">See Plans</button></div></div></section>
            @else
                <section class="lr-view is-active" data-lr-view="guest-home">
                    <div class="lr-guest-shell">
                        <div class="lr-guest-group">
                            <div class="lr-guest-group-title">Contact</div>
                            <div class="lr-guest-grid">
                                <a class="lr-guest-card" href="mailto:{{ $lrSupportEmail }}"><span class="lr-guest-icon"><i class="fa-solid fa-envelope"></i></span><strong>Email Us</strong></a>
                                <a class="lr-guest-card" href="sms:{{ $lrSupportPhone }}"><span class="lr-guest-icon"><i class="fa-solid fa-comment-dots"></i></span><strong>Text Us</strong></a>
                                <a class="lr-guest-card" href="tel:{{ $lrSupportPhone }}"><span class="lr-guest-icon"><i class="fa-solid fa-phone"></i></span><strong>Call Us</strong></a>
                                <a class="lr-guest-card" href="{{ $lrFacebookUrl }}" target="_blank" rel="noopener"><span class="lr-guest-icon"><i class="fa-brands fa-facebook-messenger"></i></span><strong>Chat Us</strong></a>
                            </div>
                        </div>

                        <div class="lr-guest-group">
                            <div class="lr-guest-group-title">Start</div>
                            <div class="lr-guest-grid">
                                <button class="lr-guest-card" type="button" data-lr-nav="share-site"><span class="lr-guest-icon"><i class="fa-solid fa-share-nodes"></i></span><strong>Share</strong></button>
                                <button class="lr-guest-card" type="button" data-lr-nav="book-call"><span class="lr-guest-icon"><i class="fa-solid fa-calendar-check"></i></span><strong>Book Demo</strong></button>
                                <a class="lr-guest-card" href="/pricing"><span class="lr-guest-icon"><i class="fa-solid fa-user-plus"></i></span><strong>Register Now</strong></a>
                                <button class="lr-guest-card is-accent" type="button" data-lr-nav="login"><span class="lr-guest-icon"><i class="fa-solid fa-right-to-bracket"></i></span><strong>Login</strong></button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="lr-view" data-lr-view="share-site">
                    <div class="lr-form-section">
                        <h4>Share PLYRCARD</h4>
                        <p class="lr-card-copy">Share PLYRCARD with another athlete, parent, or coach.</p>
                        <div class="lr-field" style="margin-top:12px;"><label>PLYRCARD URL</label><input class="lr-input" value="{{ $lrMainShareUrl }}" readonly data-lr-guest-share-input></div>
                        <div class="lr-actions"><button class="lr-btn lr-btn-primary" type="button" data-lr-guest-share>Share</button><button class="lr-btn" type="button" data-lr-guest-copy>Copy Link</button></div>
                    </div>
                </section>

                <section class="lr-view" data-lr-view="login">
                    <form class="lr-form" data-lr-login-form>
                        <div class="lr-form-section">
                            <h4>Sign In</h4>
                            <p class="lr-card-copy">Sign in to open your Locker Room.</p>
                            <div class="lr-form-grid" style="margin-top:12px;">
                                <div class="lr-field is-full"><label>Email</label><input class="lr-input" type="email" name="email" autocomplete="email" required></div>
                                <div class="lr-field is-full"><label>Password</label><input class="lr-input" type="password" name="password" autocomplete="current-password" required></div>
                                <label class="lr-login-remember is-full"><input type="checkbox" name="remember" value="1"><span>Remember me</span></label>
                            </div>
                            <div class="lr-actions"><button class="lr-btn lr-btn-primary" type="submit">Sign In</button><button class="lr-btn" type="button" data-lr-nav="forgot-password">Forgot Password?</button></div>
                        </div>
                    </form>
                </section>
                <section class="lr-view" data-lr-view="forgot-password"><form class="lr-form" data-lr-password-reset-form><div class="lr-form-section"><h4>Reset Password</h4><p class="lr-card-copy">Enter your email and we’ll send password reset instructions.</p><div class="lr-field" style="margin-top:12px;"><label>Email</label><input class="lr-input" type="email" name="email" required></div></div><button class="lr-btn lr-btn-primary" type="submit">Send Reset Link</button></form></section>
                <section class="lr-view" data-lr-view="book-call"><div class="lr-card" style="padding:0;overflow:hidden;"><div style="padding:14px 15px;border-bottom:1px solid #e5e7eb;"><h3 class="lr-card-title">Book Demo</h3><p class="lr-card-copy">Choose a time to see how PLYRCARD works.</p></div><div data-lr-book-embed></div></div></section>
            @endauth
        </div>

        <aside class="lr-dashboard-detail" data-lr-dashboard-activity-panel aria-label="Dashboard activity detail">
            <div class="lr-dashboard-detail-head">
                <div class="lr-dashboard-detail-head-main">
                    <button type="button" class="lr-back" data-lr-dashboard-detail-close aria-label="Back to dashboard"><i class="fa-solid fa-chevron-left"></i></button>
                    <div><h3 data-lr-dashboard-detail-title>Recruiting Activity</h3><p data-lr-dashboard-detail-subtitle>Coaches connected to this activity.</p></div>
                </div>
                <button type="button" class="lr-close" data-lr-dashboard-detail-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="lr-dashboard-detail-body" data-lr-dashboard-detail-body><div class="lr-detail-empty">Choose a dashboard stat to view its activity.</div></div>
        </aside>

        <aside class="lr-dashboard-detail" data-lr-dashboard-school-panel aria-label="School detail">
            <div class="lr-dashboard-detail-head">
                <div class="lr-dashboard-detail-head-main">
                    <button type="button" class="lr-back" data-lr-dashboard-school-close aria-label="Back to activity"><i class="fa-solid fa-chevron-left"></i></button>
                    <div><h3>School Details</h3><p>Local school information and coach roster.</p></div>
                </div>
                <button type="button" class="lr-close" data-lr-dashboard-school-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="lr-dashboard-detail-body" data-lr-dashboard-school-body><div class="lr-detail-empty">Select a school from a coach activity row.</div></div>
        </aside>

        <div class="lr-toast" data-lr-toast></div>
    </section>
</div>
<button type="button" class="lr-drawer-tab" data-plyrcard-toggle-drawer aria-expanded="false"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i><span>{{ $lrLoggedIn ? 'Locker Room' : 'Get Started' }}</span></button>

@once
<script src="https://systems.plyrcard.com/js/form_embed.js" type="text/javascript"></script>
@endonce
<script>
(function () {
    const drawer = document.getElementById('plyrcard-action-drawer');
    if (!drawer || drawer.dataset.lrReady === '1') return;
    drawer.dataset.lrReady = '1';

    const authenticated = drawer.dataset.authenticated === '1';
    let forcePassword = authenticated && drawer.dataset.forcePassword === '1';
    let state = {};
    try {
        state = JSON.parse(document.getElementById('plyrcard-locker-room-initial')?.textContent || '{}') || {};
    } catch (_) { state = {}; }
    let currentView = authenticated ? (forcePassword ? 'password' : 'home') : 'guest-home';
    let history = [];
    let toastTimer = null;
    let profilePositionSelection = new Set();
    let pendingRawFiles = [];
    let pendingRawPreviewUrls = [];
    let rawUploadStatusMessage = '';
    let rawUploadStatusTone = '';
    let dashboardActivityState = null;
    let dashboardSchoolState = null;
    let dashboardActivityLoading = false;
    let dashboardSchoolLoading = false;
    let dashboardEngagementFilter = '';
    const dashboardMetricCache = new Map();
    const dashboardMetricPromises = new Map();
    const profileOptionCache = new Map();
    let profileOptionsLoadVersion = 0;
    const q = (s, r = drawer) => r.querySelector(s);
    const qa = (s, r = drawer) => Array.from(r.querySelectorAll(s));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '';
    const money = cents => new Intl.NumberFormat('en-US', {style:'currency', currency: state?.billing?.currency || 'USD'}).format((Number(cents || 0))/100);
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));

    const titles = {'guest-home':'Get Started','share-site':'Share PLYRCARD',home:'Locker Room',dashboard:'Dashboard',profile:'Quick Profile',schedule:'My Schedule',settings:'Settings',share:'Share My PLYRCARD',upgrade:'Upgrade',services:'Additional Services',show:'PLYRCARD Show',refer:'Refer a Friend',support:'Support','book-call': authenticated ? 'Book a Call' : 'Book Demo',billing:'Billing & Payments',password:'Change Password',gate:'My Journey','forgot-password':'Reset Password',login:'Sign In'};
    const subtitles = {'guest-home':'Everything you need to get started','share-site':'Share PLYRCARD with someone',home:'Your player workspace',dashboard:'Recruiting stats from your workspace',profile:'Edit your most important athlete details',schedule:'View, create and edit schedule items',settings:'Notifications and PLYRCARD preferences',share:'Your public player link',upgrade:'Current plans and pricing',services:'Request extra support',show:'Podcast and athlete stories',refer:'Invite an athlete by email',support:'Get help from our team','book-call': authenticated ? 'Schedule time with our team' : 'See how PLYRCARD works',billing:'Payment method, subscription and billing information',password:'Secure your Locker Room account',gate:'Upgrade to unlock this feature','forgot-password':'Recover access to your account',login:'Welcome back'};

    function showToast(message, error = false) {
        const el = q('[data-lr-toast]'); if (!el) return;
        el.textContent = message || (error ? 'Something went wrong.' : 'Saved.');
        el.style.background = error ? '#991b1b' : '#111827';
        el.classList.add('is-showing');
        clearTimeout(toastTimer); toastTimer = setTimeout(() => el.classList.remove('is-showing'), 3200);
    }

    function openDrawer() {
        drawer.classList.add('is-open'); drawer.dataset.state = 'open'; document.documentElement.classList.add('lr-open');
        document.querySelectorAll('[data-plyrcard-toggle-drawer]').forEach(el => el.setAttribute('aria-expanded','true'));
        if (forcePassword) setView('password', false);
        refreshData();
    }
    function closeDashboardSchool() {
        const panel = q('[data-lr-dashboard-school-panel]');
        if (panel) panel.classList.remove('is-open');
        dashboardSchoolState = null;
        dashboardSchoolLoading = false;
    }

    function closeDashboardActivity() {
        closeDashboardSchool();
        const panel = q('[data-lr-dashboard-activity-panel]');
        if (panel) panel.classList.remove('is-open');
        dashboardActivityState = null;
        dashboardActivityLoading = false;
    }

    function closeDrawer() { closeDashboardActivity(); drawer.classList.remove('is-open'); drawer.dataset.state = 'closed'; document.documentElement.classList.remove('lr-open'); document.querySelectorAll('[data-plyrcard-toggle-drawer]').forEach(el => el.setAttribute('aria-expanded','false')); }

    function isFree() { return state?.plan?.is_free === true; }
    function requiresPremium(view) { return ['dashboard','schedule'].includes(view); }
    function setView(view, push = true) {
        if (!view) return;
        closeDashboardActivity();
        if (forcePassword && authenticated && view !== 'password') view = 'password';
        if (authenticated && isFree() && requiresPremium(view)) {
            q('[data-lr-gate-title]').textContent = `${titles[view] || 'This feature'} is available with My Journey`;
            view = 'gate';
        }
        if (push && currentView !== view) history.push(currentView);
        currentView = view;
        qa('[data-lr-view]').forEach(el => el.classList.toggle('is-active', el.dataset.lrView === view));
        q('[data-lr-title]').textContent = titles[view] || 'Locker Room';
        q('[data-lr-subtitle]').textContent = subtitles[view] || '';
        const back = q('[data-lr-back].lr-back'); if (back) back.hidden = forcePassword || view === (authenticated ? 'home' : 'guest-home');
        q('.lr-body')?.scrollTo({top:0, behavior:'auto'});
        if (view === 'support') ensureSupportEmbed();
        if (view === 'book-call') ensureBookEmbed();
        render();
    }
    function goBack() { const target = history.pop() || (authenticated ? 'home' : 'guest-home'); setView(target, false); }

    async function request(url, options = {}) {
        const headers = Object.assign({'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf()}, options.headers || {});
        if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') { headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(options.body); }
        const response = await fetch(url, Object.assign({credentials:'same-origin'}, options, {headers}));
        let json = {}; try { json = await response.json(); } catch (_) {}
        if (!response.ok || json.success === false) {
            const validation = json.errors ? Object.values(json.errors).flat().join(' ') : '';
            throw new Error(validation || json.message || `Request failed (${response.status})`);
        }
        return json;
    }

    function lrInitials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return (parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || 'PC');
    }

    function metricRowSubtitle(row) {
        const bits = [];
        if (row.coach_title) bits.push(row.coach_title);
        if (row.coach_email) bits.push(row.coach_email);
        if (row.last_subject) bits.push(`Subject: ${row.last_subject}`);
        if (row.last_at_label) bits.push(row.last_at_label);
        return bits.join(' · ');
    }

    function renderDashboardActivityDetail() {
        const panel = q('[data-lr-dashboard-activity-panel]');
        const body = q('[data-lr-dashboard-detail-body]');
        const title = q('[data-lr-dashboard-detail-title]');
        const subtitle = q('[data-lr-dashboard-detail-subtitle]');
        if (!panel || !body) return;

        panel.classList.add('is-open');
        if (dashboardActivityLoading && !dashboardActivityState) {
            if (title) title.textContent = 'Recruiting Activity';
            if (subtitle) subtitle.textContent = 'Loading activity...';
            body.innerHTML = `<div class="lr-detail-kpis"><div class="lr-detail-kpi lr-skeleton">.</div><div class="lr-detail-kpi lr-skeleton">.</div><div class="lr-detail-kpi lr-skeleton">.</div></div><div class="lr-activity-list"><div class="lr-activity-row lr-skeleton" style="height:66px">.</div><div class="lr-activity-row lr-skeleton" style="height:66px">.</div><div class="lr-activity-row lr-skeleton" style="height:66px">.</div></div>`;
            return;
        }

        const data = dashboardActivityState || {};
        let rows = Array.isArray(data.rows) ? data.rows : [];
        const metric = data.metric || '';
        if (title) title.textContent = data.label || 'Recruiting Activity';
        if (subtitle) subtitle.textContent = metric === 'profile_views'
            ? 'Tracked profile views from your PLYRCARD activity.'
            : metric === 'social_clicks'
                ? 'How coaches are engaging with your social platforms.'
                : 'Identified coaches connected to this activity.';

        const note = data.note ? `<div class="lr-preparing" style="margin-bottom:11px;border-color:#e5e7eb;background:#fff;"><i class="fa-solid fa-circle-info" style="color:#667085"></i><div><span style="margin-top:0;color:#667085;">${esc(data.note)}</span></div></div>` : '';

        let summary = '';
        if (metric === 'profile_views') {
            summary = `<div class="lr-detail-kpis">
                <div class="lr-detail-kpi"><small>Total Views</small><strong>${Number(data.total || 0).toLocaleString()}</strong><em>Player website/profile views</em></div>
                <div class="lr-detail-kpi"><small>Unique Contacts</small><strong>${Number(data.identified_count || 0).toLocaleString()}</strong><em>Distinct coaches who viewed your profile</em></div>
                <div class="lr-detail-kpi"><small>Schools Reached</small><strong>${Number(data.schools_reached || 0).toLocaleString()}</strong><em>Schools represented by those viewers</em></div>
            </div>`;
        } else if (metric === 'social_clicks') {
            const pc = data.platform_counts || {};
            const filters = [['x','X (Twitter)'],['instagram','Instagram'],['youtube','YouTube']];
            summary = `<div class="lr-engagement-filters">${filters.map(([key,label]) => `<button type="button" class="lr-engagement-filter${dashboardEngagementFilter === key ? ' is-active' : ''}" data-lr-engagement-filter="${key}"><span>${label}</span><strong>${Number(pc[key] || 0).toLocaleString()}</strong></button>`).join('')}</div>`;
            if (dashboardEngagementFilter) {
                rows = rows.filter(row => Number((row.platform_counts || {})[dashboardEngagementFilter] || 0) > 0);
            }
        } else {
            summary = `<div class="lr-activity-summary"><div><span>Total activity</span><strong>${Number(data.total || 0).toLocaleString()}</strong></div><div style="text-align:right"><span>Identified coaches</span><strong>${Number(data.identified_count || rows.length || 0).toLocaleString()}</strong></div></div>`;
        }

        if (!rows.length) {
            body.innerHTML = summary + note + '<div class="lr-detail-empty">No identified coach rows are available for this view yet.</div>';
            return;
        }

        const html = rows.map(row => {
            const school = row.school || {};
            const reference = school.reference || school.id || school.name || '';
            const schoolName = school.name || '';
            const platformEntries = Object.entries(row.platform_counts || {}).filter(([,count]) => Number(count) > 0);
            const platforms = platformEntries.map(([platform,count]) => `${platform === 'x' ? 'X' : platform.charAt(0).toUpperCase()+platform.slice(1)} ${count}`).join(' · ');
            const count = metric === 'social_clicks' && dashboardEngagementFilter
                ? Number((row.platform_counts || {})[dashboardEngagementFilter] || 0)
                : Number(row.count || 0);
            const subtitleText = [row.coach_email, row.last_at_label, platforms].filter(Boolean).join(' · ');
            const tag = reference
                ? `<button type="button" class="lr-school-link" data-lr-dashboard-school="${esc(reference)}">${esc(schoolName || 'View School')} <i class="fa-solid fa-chevron-right"></i></button>`
                : `<span style="color:#98a2b3;font-size:10px;">${count.toLocaleString()} ${metric === 'social_clicks' ? 'click' : 'view'}${count === 1 ? '' : 's'}</span>`;
            return `<div class="lr-activity-row${reference ? ' is-clickable' : ''}" ${reference ? `data-lr-dashboard-school="${esc(reference)}" role="button" tabindex="0"` : ''}>
                <span class="lr-activity-avatar">${esc(lrInitials(row.coach_name))}</span>
                <span class="lr-activity-copy"><strong>${esc(row.coach_name || 'Coach')}</strong><small>${esc(schoolName || 'School not matched')}${subtitleText ? `<br>${esc(subtitleText)}` : ''}<br><b>${count.toLocaleString()}</b> ${metric === 'social_clicks' ? 'click' : 'tracked view'}${count === 1 ? '' : 's'}</small></span>
                ${tag}
            </div>`;
        }).join('');
        body.innerHTML = summary + note + `<div class="lr-activity-list">${html}</div>`;
    }

    async function fetchDashboardMetric(metric) {
        if (dashboardMetricCache.has(metric)) return dashboardMetricCache.get(metric);
        if (dashboardMetricPromises.has(metric)) return dashboardMetricPromises.get(metric);
        const promise = (async () => {
            const url = new URL(drawer.dataset.dashboardActivityUrl, window.location.origin);
            url.searchParams.set('metric', metric);
            const json = await request(url.toString());
            const data = json.data || {};
            dashboardMetricCache.set(metric, data);
            return data;
        })().finally(() => dashboardMetricPromises.delete(metric));
        dashboardMetricPromises.set(metric, promise);
        return promise;
    }

    function prefetchDashboardMetrics() {
        if (!authenticated || isFree() || !drawer.dataset.dashboardActivityUrl) return;
        const run = () => ['profile_views','social_clicks'].forEach(metric => fetchDashboardMetric(metric).catch(() => {}));
        if ('requestIdleCallback' in window) window.requestIdleCallback(run, {timeout:900});
        else setTimeout(run, 180);
    }

    async function openDashboardMetric(metric) {
        if (!authenticated || isFree() || !drawer.dataset.dashboardActivityUrl) return;
        closeDashboardSchool();
        dashboardEngagementFilter = '';
        dashboardActivityState = dashboardMetricCache.get(metric) || null;
        dashboardActivityLoading = !dashboardActivityState;
        renderDashboardActivityDetail();
        try {
            dashboardActivityState = await fetchDashboardMetric(metric);
        } catch (error) {
            dashboardActivityState = {metric,label:'Recruiting Activity', total:0, identified_count:0, rows:[], note:error.message};
        } finally {
            dashboardActivityLoading = false;
            renderDashboardActivityDetail();
        }
    }

    function renderDashboardSchoolDetail() {
        const panel = q('[data-lr-dashboard-school-panel]');
        const body = q('[data-lr-dashboard-school-body]');
        if (!panel || !body) return;
        panel.classList.add('is-open');
        if (dashboardSchoolLoading) {
            body.innerHTML = '<div class="lr-detail-empty"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading school...</div>';
            return;
        }
        const data = dashboardSchoolState || {};
        const school = data.school || null;
        const coaches = Array.isArray(data.coaches) ? data.coaches : [];
        if (!school) {
            body.innerHTML = '<div class="lr-detail-empty">School information could not be matched to the local school database.</div>';
            return;
        }
        const meta = [school.division, school.conference, [school.city, school.state].filter(Boolean).join(', ')].filter(Boolean).join(' · ');
        const logo = school.logo_url
            ? `<img class="lr-school-logo" src="${esc(school.logo_url)}" alt="${esc(school.name || 'School')} logo">`
            : `<span class="lr-school-logo-fallback">${esc(lrInitials(school.name))}</span>`;
        const roster = coaches.length
            ? coaches.map(coach => `<div class="lr-school-coach"><strong>${esc(coach.name || 'Coach')}</strong><span>${esc([coach.title, coach.email, coach.phone].filter(Boolean).join(' · ') || 'Coach')}</span></div>`).join('')
            : '<div class="lr-detail-empty">No local coach roster is currently attached to this school.</div>';
        body.innerHTML = `<div class="lr-school-hero">${logo}<div class="lr-school-meta"><h4>${esc(school.name || 'School')}</h4><p>${esc(meta || 'School information')}</p></div></div><div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px;"><h4 style="margin:0;font-size:13px;">Coach Roster</h4><span class="lr-chip">${coaches.length} coach${coaches.length === 1 ? '' : 'es'}</span></div><div class="lr-school-roster">${roster}</div>`;
    }

    async function openDashboardSchool(reference) {
        reference = String(reference || '').trim();
        if (!reference || !authenticated || isFree() || !drawer.dataset.dashboardSchoolUrl) return;
        dashboardSchoolLoading = true;
        dashboardSchoolState = null;
        renderDashboardSchoolDetail();
        try {
            const base = drawer.dataset.dashboardSchoolUrl;
            const url = base.replace('__SCHOOL__', encodeURIComponent(reference));
            const json = await request(url);
            dashboardSchoolState = json.data || {};
        } catch (error) {
            dashboardSchoolState = {school:null, coaches:[], error:error.message};
        } finally {
            dashboardSchoolLoading = false;
            renderDashboardSchoolDetail();
        }
    }

    async function refreshData() {
        if (!authenticated || !drawer.dataset.url) return;
        try {
            const json = await request(drawer.dataset.url);
            if (json.data) { state = json.data; render(); }
        } catch (_) { /* Keep the server-rendered snapshot; no disruptive loader. */ }
    }

    function fillForm(form, data) {
        if (!form || !data) return;
        Array.from(form.elements).forEach(el => {
            if (!el.name || el.name === '_token' || el.name === 'schedule_id' || el.type === 'file') return;
            const name = el.name.replace(/\[\]$/, '');
            if (!(name in data) && name !== 'email_display') return;
            const value = name === 'email_display' ? data.email : data[name];
            if (el.type === 'checkbox') el.checked = !!value;
            else if (el.tagName === 'SELECT' && el.multiple) Array.from(el.options).forEach(opt => opt.selected = (Array.isArray(value) ? value : []).includes(opt.value));
            else el.value = value ?? '';
        });
    }

    function render() {
        if (!authenticated || !state?.user) return;
        q('[data-lr-plan]') && (q('[data-lr-plan]').textContent = state.plan?.label || 'Free');
        qa('[data-lr-preparing]').forEach(el => el.hidden = !state.plan?.show_preparing);
        const completion = Number(state.dashboard?.profile_completion || state.user?.profile_completion || 0);
        q('[data-lr-completion]') && (q('[data-lr-completion]').textContent = `${completion}%`);
        q('[data-lr-progress]') && (q('[data-lr-progress]').style.width = `${Math.max(0,Math.min(100,completion))}%`);
        qa('[data-lr-stat]').forEach(el => el.textContent = new Intl.NumberFormat().format(Number(state.dashboard?.stats?.[el.dataset.lrStat] || 0)));

        const next = state.dashboard?.next_schedule;
        const nextBox = q('[data-lr-next-schedule]');
        if (nextBox) nextBox.innerHTML = next
            ? `<h3 class="lr-card-title">Next Schedule</h3><p class="lr-card-copy"><strong>${esc(next.opponent || next.title || 'Game')}</strong><br>${esc(next.date_label || '')}${next.time_label ? ` · ${esc(next.time_label)}` : ''}${next.venue || next.location ? `<br>${esc(next.venue || next.location)}` : ''}</p><div class="lr-actions"><button class="lr-btn" type="button" data-lr-nav="schedule">Open Schedule</button></div>`
            : `<h3 class="lr-card-title">Next Schedule</h3><p class="lr-card-copy">No upcoming game has been added yet.</p><div class="lr-actions"><button class="lr-btn" type="button" data-lr-nav="schedule">Open Schedule</button></div>`;

        renderProfile(); renderSchedule(); renderSettings(); renderPlans(); renderBilling(); renderShare();
        prefetchDashboardMetrics();
    }

    async function fetchProfileOptions(type, params = {}) {
        if (!drawer.dataset.profileOptionsUrl) return [];
        const query = new URLSearchParams({type, ...Object.fromEntries(Object.entries(params).filter(([,v]) => v !== null && v !== undefined && String(v) !== ''))});
        const key = query.toString();
        if (profileOptionCache.has(key)) return profileOptionCache.get(key);
        try {
            const json = await request(`${drawer.dataset.profileOptionsUrl}?${query.toString()}`);
            const rows = Array.isArray(json.options) ? json.options : [];
            profileOptionCache.set(key, rows);
            return rows;
        } catch (_) {
            return [];
        }
    }

    function populateSelect(select, rows, placeholder, value = '') {
        if (!select) return;
        const current = value === null || value === undefined ? '' : String(value);
        select.innerHTML = `<option value="">${esc(placeholder)}</option>` + (rows || []).map(row => `<option value="${esc(row.value)}">${esc(row.label)}</option>`).join('');
        select.value = current;
        if (current && select.value !== current) {
            const label = current;
            select.insertAdjacentHTML('beforeend', `<option value="${esc(current)}">${esc(label)}</option>`);
            select.value = current;
        }
    }

    async function loadClubOptions() {
        const user = state.user || {};
        const club = q('[data-lr-club]');
        const age = q('[data-lr-age-group]');
        if (!club || !age) return;
        const sport = q('[data-lr-sport]')?.value || '';
        const gender = q('[data-lr-gender]')?.value || '';
        const leagueId = q('[data-lr-league]')?.value || '';
        club.disabled = !leagueId;
        if (!leagueId) {
            populateSelect(club, [], 'Select league first', '');
            populateSelect(age, [], 'Select club first', '');
            age.disabled = true;
            return;
        }
        club.innerHTML = '<option value="">Loading clubs...</option>';
        const rows = await fetchProfileOptions('club', {sport, gender, league_id: leagueId});
        populateSelect(club, rows, 'Select club', user.club_id || '');
        club.disabled = false;
        const ageRows = Object.entries(user.age_group_options || {}).map(([value,label]) => ({value,label}));
        populateSelect(age, ageRows, 'Select age group', user.team_name || '');
        age.disabled = !club.value;
    }

    async function loadProfileAssociationOptions() {
        const version = ++profileOptionsLoadVersion;
        const user = state.user || {};
        const school = q('[data-lr-school]');
        const league = q('[data-lr-league]');
        const club = q('[data-lr-club]');
        const age = q('[data-lr-age-group]');
        const national = q('[data-lr-national-team]');
        if (!school || !league || !club || !age || !national) return;

        const sport = q('[data-lr-sport]')?.value || user.sport || '';
        const gender = q('[data-lr-gender]')?.value || user.gender || '';
        const [schools, nationalTeams] = await Promise.all([
            fetchProfileOptions('school'),
            fetchProfileOptions('national_team'),
        ]);
        if (version !== profileOptionsLoadVersion) return;
        populateSelect(school, schools, 'Select school', user.school_id || '');
        populateSelect(national, nationalTeams, 'Select national team', user.national_team_id || '');

        league.disabled = !(sport && gender);
        if (!(sport && gender)) {
            populateSelect(league, [], 'Select sport and sex first', '');
            populateSelect(club, [], 'Select league first', '');
            club.disabled = true;
            populateSelect(age, [], 'Select club first', '');
            age.disabled = true;
            return;
        }

        league.innerHTML = '<option value="">Loading leagues...</option>';
        const leagues = await fetchProfileOptions('league', {sport, gender});
        if (version !== profileOptionsLoadVersion) return;
        populateSelect(league, leagues, 'Select league', user.league_id || '');
        league.disabled = false;
        await loadClubOptions();
    }

    function updatePositionUi() {
        const user = state.user || {};
        const sport = q('[data-lr-sport]')?.value || user.sport || '';
        const options = Object.entries(user.position_options?.[sport] || {});
        const menu = q('[data-lr-position-menu]');
        const hidden = q('[data-lr-position-hidden]');
        const trigger = q('[data-lr-position-trigger] span');
        if (!menu || !hidden || !trigger) return;
        menu.innerHTML = options.length ? options.map(([key,label]) => `<button type="button" class="lr-multi-option ${profilePositionSelection.has(key) ? 'is-selected' : ''}" data-lr-position-option="${esc(key)}"><span class="lr-multi-check"><i class="fa-solid fa-check"></i></span><span>${esc(label)}</span></button>`).join('') : '<div class="lr-card-copy" style="padding:9px;">Choose a sport first.</div>';
        hidden.innerHTML = Array.from(profilePositionSelection).map(value => `<input type="hidden" name="position[]" value="${esc(value)}">`).join('');
        const labels = Array.from(profilePositionSelection).map(value => user.position_options?.[sport]?.[value] || value);
        trigger.textContent = labels.length ? labels.join(', ') : 'Select position';
    }

    function renderPositions(reset = true) {
        const user = state.user || {};
        if (reset) profilePositionSelection = new Set(Array.isArray(user.position) ? user.position : []);
        updatePositionUi();
    }

    function clearPendingRawPreviewUrls() {
        pendingRawPreviewUrls.forEach(url => { try { URL.revokeObjectURL(url); } catch (_) {} });
        pendingRawPreviewUrls = [];
    }

    function updateRawUploadStatus(existingCount = 0) {
        const status = q('[data-lr-raw-status]');
        if (!status) return;
        const pendingCount = pendingRawFiles.length;
        const total = existingCount + pendingCount;
        status.className = 'lr-upload-status';
        if (rawUploadStatusMessage) {
            status.textContent = rawUploadStatusMessage;
            if (rawUploadStatusTone) status.classList.add(`is-${rawUploadStatusTone}`);
            return;
        }
        if (pendingCount > 0) {
            status.textContent = `${pendingCount} new image${pendingCount === 1 ? '' : 's'} ready to upload · ${total}/20 total after saving.`;
            status.classList.add('is-ready');
        } else if (existingCount > 0) {
            status.textContent = `${existingCount} image${existingCount === 1 ? '' : 's'} uploaded and saved.`;
            status.classList.add('is-success');
        } else {
            status.textContent = 'No images uploaded yet. Select Add More to choose player photos.';
        }
    }

    function renderMedia() {
        const user = state.user || {};
        const featured = q('[data-lr-profile-image-preview]');
        if (featured) {
            const prepared = [
                ['PLYRCARD', user.plyrcard_image_url || user.player_image_url],
                ['Action', user.action_image_url],
                ['Vertical Hero', user.mobile_hero_image_url],
                ['National Team', user.national_team_image_url],
                ['YouTube', user.youtube_thumbnail_url],
            ].filter(([,url]) => !!url);
            featured.style.display = 'block';
            featured.innerHTML = prepared.length ? `<div class="lr-media-grid" style="margin-top:0;">${prepared.map(([label,url]) => `<div><div class="lr-media-thumb"><img src="${esc(url)}" alt="${esc(label)} image"></div><div class="lr-card-copy" style="margin-top:5px;text-align:center;">${esc(label)}</div></div>`).join('')}</div><div style="margin-top:10px;"><span class="lr-chip">Managed by PLYRCARD team</span></div>` : `<div class="lr-card-copy">Processed PLYRCARD images have not been added yet.</div><div style="margin-top:10px;"><span class="lr-chip">Managed by PLYRCARD team</span></div>`;
        }

        const raws = Array.isArray(user.raw_player_images) ? user.raw_player_images : [];
        const grid = q('[data-lr-raw-images]');
        const hidden = q('[data-lr-raw-existing-hidden]');
        clearPendingRawPreviewUrls();

        const savedTiles = raws.map((item,index) => `<div class="lr-media-thumb"><img src="${esc(item.url)}" alt="Uploaded raw player image"><button class="lr-media-remove" type="button" data-lr-remove-raw="${index}" aria-label="Remove uploaded image"><i class="fa-solid fa-xmark"></i></button></div>`);
        const pendingTiles = pendingRawFiles.map((file,index) => {
            const url = URL.createObjectURL(file);
            pendingRawPreviewUrls.push(url);
            return `<div class="lr-media-thumb"><img src="${esc(url)}" alt="Selected player image preview"><span class="lr-media-pending-badge">Ready</span><button class="lr-media-remove" type="button" data-lr-remove-pending-raw="${index}" aria-label="Remove selected image"><i class="fa-solid fa-xmark"></i></button></div>`;
        });
        const count = savedTiles.length + pendingTiles.length;
        const addTile = count < 20 ? `<button type="button" class="lr-media-add" data-lr-add-raw><i class="fa-solid fa-plus"></i><span>${count ? 'Add More' : 'Add Images'}</span></button>` : '';
        if (grid) grid.innerHTML = [...savedTiles, ...pendingTiles, addTile].filter(Boolean).join('');
        if (hidden) hidden.innerHTML = raws.map(item => `<input type="hidden" name="raw_player_images_existing[]" value="${esc(item.path)}">`).join('');
        updateRawUploadStatus(raws.length);

        const pro = q('[data-lr-pro-club-preview]');
        if (pro) pro.innerHTML = user.pro_club_logo_url ? `<div class="lr-chip"><i class="fa-solid fa-image"></i>&nbsp; Current pro club logo saved</div>` : '';
    }

    function renderProfile() {
        const user = state.user || {};
        const sport = q('[data-lr-sport]');
        if (sport) {
            const current = user.sport || '';
            sport.innerHTML = '<option value="">Select sport</option>' + Object.entries(user.sport_options || {}).map(([k,v]) => `<option value="${esc(k)}">${esc(v)}</option>`).join('');
            sport.value = current;
        }
        fillForm(q('[data-lr-profile-form]'), user);
        renderPositions(true);
        renderMedia();
        loadProfileAssociationOptions();
        const locked = !state.plan?.is_premium;
        const socialLock = q('[data-lr-social-lock]');
        const socialFields = q('[data-lr-social-fields]');
        if (socialLock) socialLock.hidden = !locked;
        if (socialFields) socialFields.style.display = locked ? 'none' : 'grid';
    }

    function renderSchedule() {
        const rows = state.schedule?.items || [];
        q('[data-lr-schedule-count]') && (q('[data-lr-schedule-count]').textContent = rows.length);
        const list = q('[data-lr-schedule-list]'); if (!list) return;
        if (!rows.length) { list.innerHTML = '<div class="lr-card"><p class="lr-card-copy">No schedule items yet. Add your first game or event.</p></div>'; return; }
        list.innerHTML = rows.map(item => {
            const d = item.game_date ? new Date(item.game_date + 'T00:00:00') : null;
            const mon = d ? d.toLocaleString('en-US',{month:'short'}) : 'TBD'; const day = d ? d.getDate() : '--';
            return `<article class="lr-schedule-item"><div class="lr-datebox"><strong>${esc(day)}</strong><span>${esc(mon)}</span></div><div><h4>${esc(item.opponent || item.title || 'Schedule')}</h4><div class="lr-schedule-meta">${esc(item.status || 'upcoming')} ${item.time_label ? `· ${esc(item.time_label)}` : ''}<br>${esc(item.venue || item.location || 'Location TBD')}${item.score ? `<br>Score: ${esc(item.score)}` : ''}</div></div><div class="lr-schedule-tools">${item.can_edit ? `<button class="lr-icon-btn" type="button" data-lr-edit-schedule="${item.id}" title="Edit"><i class="fa-solid fa-pen"></i></button><button class="lr-icon-btn" type="button" data-lr-delete-schedule="${item.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>` : '<span class="lr-chip">Team</span>'}</div></article>`;
        }).join('');
    }

    function renderSettings() {
        const box = q('[data-lr-notifications]'); if (!box) return;
        const settings = state.settings?.notifications || {};
        const defs = [['profile_views','Profile Views','Email me when a matched coach views my PLYRCARD.'],['instagram_clicks','Instagram Clicks','Email me when a matched coach clicks Instagram.'],['youtube_clicks','YouTube / Highlight Clicks','Email me when a matched coach clicks YouTube or highlights.'],['x_clicks','X Clicks','Email me when a matched coach clicks X.'],['email_opens','Email Opens','Notify me about tracked email opens.'],['coach_replies','Coach Replies','Notify me when a coach replies.'],['weekly_digest','Weekly Digest','Send a weekly recruiting activity digest.'],['product_news','Product News','Receive PLYRCARD product updates.']];
        box.innerHTML = defs.map(([key,title,copy]) => `<label class="lr-toggle-row"><span class="lr-toggle-copy"><strong>${title}</strong><span>${copy}</span></span><span class="lr-switch"><input type="checkbox" data-lr-setting="${key}" ${settings[key] ? 'checked' : ''}><span></span></span></label>`).join('');
        const premium = !!state.plan?.is_premium;
        q('[data-lr-website-settings]').hidden = !premium; q('[data-lr-website-lock]').hidden = premium;
        const article = state.settings?.website?.article_section_type || 'follow_me';
        q('[data-lr-article-value]').value = article;
        qa('[data-lr-article]').forEach(btn => btn.classList.toggle('is-active', btn.dataset.lrArticle === article));
    }

    function renderPlans() {
        const box = q('[data-lr-plans]'); if (!box) return;
        box.innerHTML = (state.plans || []).map(plan => `<article class="lr-plan-card ${plan.current?'is-current':''}"><div style="display:flex;justify-content:space-between;gap:10px;align-items:start;"><div><div class="lr-plan-name">${esc(plan.name)}</div><div class="lr-plan-price">${esc(plan.price)} <small>${esc(plan.suffix || '')}</small></div>${plan.due_today ? `<div class="lr-chip" style="margin-top:7px;">${esc(plan.due_today)}</div>` : ''}</div>${plan.current?'<span class="lr-chip">Current Plan</span>':''}</div><p class="lr-card-copy">${esc(plan.description)}</p><ul class="lr-plan-list">${(plan.features||[]).map(f=>`<li>${esc(f)}</li>`).join('')}</ul>${plan.current?'':`<div class="lr-actions"><a class="lr-btn lr-btn-primary" href="${esc(plan.action_url)}">${esc(plan.action_label || 'Choose Plan')}</a></div>`}</article>`).join('');
    }

    function renderBilling() {
        const billing = state.billing || {}, form = q('[data-lr-billing-form]');
        fillForm(form,billing);
        const summary = q('[data-lr-billing-summary]');
        const method = q('[data-lr-payment-method]');
        const statusLabel = value => value ? String(value).replace(/_/g,' ').replace(/\b\w/g, ch => ch.toUpperCase()) : 'Not available';
        const paidAt = billing.last_transaction_paid_at ? new Date(billing.last_transaction_paid_at) : null;
        const paidAtLabel = paidAt && !Number.isNaN(paidAt.getTime()) ? paidAt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : null;

        if (summary) {
            summary.innerHTML = `<h3 class="lr-card-title">${esc(state.plan?.label || 'Free')} Plan</h3><div class="lr-billing-meta"><div><small>Payment Status</small><strong>${esc(statusLabel(billing.payment_status))}</strong></div><div><small>Subscription</small><strong>${esc(statusLabel(billing.subscription_status))}</strong></div><div><small>Recurring</small><strong>${billing.recurring_amount_cents ? `${money(billing.recurring_amount_cents)}/mo` : '—'}</strong></div>${billing.setup_fee_cents ? `<div><small>Setup Fee</small><strong>${money(billing.setup_fee_cents)}</strong></div>` : ''}${billing.amount_paid_cents ? `<div><small>Total Recorded</small><strong>${money(billing.amount_paid_cents)}</strong></div>` : ''}${billing.amount_refunded_cents ? `<div><small>Refunded</small><strong>${money(billing.amount_refunded_cents)}</strong></div>` : ''}</div>`;
        }

        if (method) {
            const brand = billing.payment_brand ? String(billing.payment_brand).toUpperCase() : 'CARD';
            if (billing.card_last_four) {
                const detailBits = [];
                if (billing.card_expiration) detailBits.push(`Expires ${esc(billing.card_expiration)}`);
                if (billing.cardholder_name) detailBits.push(esc(billing.cardholder_name));
                const changeButton = billing.payment_method_update_url ? `<div class="lr-actions" style="margin-top:10px;"><a class="lr-btn lr-btn-primary" href="${esc(billing.payment_method_update_url)}">Change Payment Method</a><a class="lr-btn" href="${esc(billing.admin_billing_url || '/admin/billing')}">Open Settings</a></div>` : `<div class="lr-actions" style="margin-top:10px;"><a class="lr-btn" href="${esc(billing.admin_billing_url || '/admin/billing')}">Open Settings</a></div>`;
                method.innerHTML = `<div class="lr-payment-card"><div class="lr-payment-icon"><i class="fa-solid fa-credit-card"></i></div><div class="lr-payment-main"><strong>${esc(brand)} ending in ${esc(billing.card_last_four)}</strong><span>${detailBits.join(' · ') || 'Secure payment method on file'}</span></div></div>${changeButton}<div class="lr-billing-meta"><div><small>Last Payment</small><strong>${billing.last_transaction_amount_cents ? money(billing.last_transaction_amount_cents) : (billing.amount_paid_cents ? money(billing.amount_paid_cents) : '—')}</strong></div><div><small>Transaction Status</small><strong>${esc(statusLabel(billing.last_transaction_status || billing.payment_status))}</strong></div><div><small>Payment Date</small><strong>${esc(paidAtLabel || '—')}</strong></div></div><p class="lr-card-copy" style="margin-top:10px;">For security, PLYRCARD only shows limited card metadata. Full card numbers and security codes are never displayed or stored here.</p>`;
            } else {
                const changeButton = billing.payment_method_update_url ? `<div class="lr-actions" style="margin-top:10px;"><a class="lr-btn lr-btn-primary" href="${esc(billing.payment_method_update_url)}">Add / Change Payment Method</a><a class="lr-btn" href="${esc(billing.admin_billing_url || '/admin/billing')}">Open Settings</a></div>` : `<div class="lr-actions" style="margin-top:10px;"><a class="lr-btn" href="${esc(billing.admin_billing_url || '/admin/billing')}">Open Settings</a></div>`;
                method.innerHTML = `<div class="lr-preparing" style="border-color:#e5e7eb;background:#f8fafc;"><i class="fa-solid fa-shield-halved" style="color:#667085;"></i><div><strong>No saved payment method is available yet.</strong><span>Use the secure payment-method flow to add or replace the card used for future billing.</span></div></div>${changeButton}${billing.last_transaction_amount_cents ? `<div class="lr-billing-meta"><div><small>Last Payment</small><strong>${money(billing.last_transaction_amount_cents)}</strong></div><div><small>Transaction Status</small><strong>${esc(statusLabel(billing.last_transaction_status || billing.payment_status))}</strong></div><div><small>Payment Date</small><strong>${esc(paidAtLabel || '—')}</strong></div></div>` : ''}`;
            }
        }
    }

    function renderShare() {
        const box=q('[data-lr-share-card]'); if(!box) return;
        const url=state.website?.url || '';
        if(!url) { box.innerHTML='<div class="lr-preparing"><i class="fa-solid fa-wand-magic-sparkles"></i><div><strong>We are preparing your PLYRCARD.</strong><span>Complete your profile while our team prepares your public player link.</span></div></div><div class="lr-actions"><button class="lr-btn lr-btn-primary" type="button" data-lr-nav="profile">Complete My Profile</button></div>'; return; }
        const status=state.website?.is_published ? 'Your PLYRCARD is published and ready to share.' : 'Your link is reserved. Visitors will see the preparation screen until your PLYRCARD is published.';
        box.innerHTML=`<h3 class="lr-card-title">Your PLYRCARD Link</h3><p class="lr-card-copy">${esc(status)}</p><div class="lr-field" style="margin-top:12px;"><input class="lr-input" value="${esc(url)}" readonly data-lr-share-input></div><div class="lr-actions"><button class="lr-btn lr-btn-primary" type="button" data-lr-copy-link>Copy Link</button><button class="lr-btn" type="button" data-lr-native-share>Share</button></div>`;
    }

    function ensureSupportEmbed() {
        const holder = q('[data-lr-support-embed]'); if (!holder || holder.dataset.loaded === '1') return; holder.dataset.loaded='1';
        const url = state.integrations?.support_form_url || 'https://systems.plyrcard.com/widget/form/HDaBy0CDwdO7Fw54wi1K';
        holder.innerHTML = `<iframe class="lr-embed" src="${esc(url)}" id="inline-HDaBy0CDwdO7Fw54wi1K" data-form-id="HDaBy0CDwdO7Fw54wi1K" title="PLYRCARD Support"></iframe>`;
    }
    function ensureBookEmbed() {
        const holder = q('[data-lr-book-embed]'); if (!holder || holder.dataset.loaded === '1') return; holder.dataset.loaded='1';
        const url = state.integrations?.book_call_url || 'https://systems.plyrcard.com/widget/booking/SvuQy1svAyETQ5Q9px9l';
        // No allow-top-navigation: confirmation/redirect remains inside the Locker Room instead of taking over the parent page.
        holder.innerHTML = `<iframe class="lr-embed" src="${esc(url)}" sandbox="allow-forms allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-modals" title="Book a PLYRCARD Call"></iframe>`;
    }

    document.addEventListener('click', event => {
        const open = event.target.closest('[data-plyrcard-open-drawer],[data-plyrcard-toggle-drawer]'); if (open) { event.preventDefault(); if (open.matches('[data-plyrcard-toggle-drawer]') && drawer.classList.contains('is-open')) closeDrawer(); else openDrawer(); return; }
        if (event.target.closest('[data-lr-close]')) { event.preventDefault(); closeDrawer(); return; }
        const back = event.target.closest('[data-lr-back]'); if (back) { event.preventDefault(); goBack(); return; }
        const nav = event.target.closest('[data-lr-nav]'); if (nav && drawer.contains(nav)) { event.preventDefault(); setView(nav.dataset.lrNav); return; }
        const metric = event.target.closest('[data-lr-dashboard-metric]'); if (metric && drawer.contains(metric)) { event.preventDefault(); openDashboardMetric(metric.dataset.lrDashboardMetric); return; }
        const engagementFilter = event.target.closest('[data-lr-engagement-filter]'); if (engagementFilter && drawer.contains(engagementFilter)) { event.preventDefault(); dashboardEngagementFilter = dashboardEngagementFilter === engagementFilter.dataset.lrEngagementFilter ? '' : engagementFilter.dataset.lrEngagementFilter; renderDashboardActivityDetail(); return; }
        if (event.target.closest('[data-lr-dashboard-detail-close]')) { event.preventDefault(); closeDashboardActivity(); return; }
        if (event.target.closest('[data-lr-dashboard-school-close]')) { event.preventDefault(); closeDashboardSchool(); return; }
        const schoolDetail = event.target.closest('[data-lr-dashboard-school]'); if (schoolDetail && drawer.contains(schoolDetail)) { event.preventDefault(); event.stopPropagation(); openDashboardSchool(schoolDetail.dataset.lrDashboardSchool); return; }
        const tab = event.target.closest('[data-pane]'); if (tab && drawer.contains(tab)) { qa('[data-pane]').forEach(x=>x.classList.toggle('is-active',x===tab)); qa('[data-lr-profile-pane]').forEach(x=>x.classList.toggle('is-active',x.dataset.lrProfilePane===tab.dataset.pane)); return; }
        const positionTrigger = event.target.closest('[data-lr-position-trigger]'); if (positionTrigger && drawer.contains(positionTrigger)) { event.preventDefault(); const menu=q('[data-lr-position-menu]'); if(menu) menu.hidden=!menu.hidden; return; }
        const positionOption = event.target.closest('[data-lr-position-option]'); if (positionOption && drawer.contains(positionOption)) { event.preventDefault(); const value=positionOption.dataset.lrPositionOption; if(profilePositionSelection.has(value)) profilePositionSelection.delete(value); else profilePositionSelection.add(value); updatePositionUi(); return; }
        const addRaw = event.target.closest('[data-lr-add-raw]'); if (addRaw && drawer.contains(addRaw)) { event.preventDefault(); q('[data-lr-raw-file-input]')?.click(); return; }
        const removeRaw = event.target.closest('[data-lr-remove-raw]'); if (removeRaw && drawer.contains(removeRaw)) { event.preventDefault(); const index=Number(removeRaw.dataset.lrRemoveRaw); if(Array.isArray(state.user?.raw_player_images) && Number.isInteger(index)){ state.user.raw_player_images.splice(index,1); rawUploadStatusMessage='Image removed from this draft. Save Quick Profile to confirm.'; rawUploadStatusTone='ready'; renderMedia(); } return; }
        const removePendingRaw = event.target.closest('[data-lr-remove-pending-raw]'); if (removePendingRaw && drawer.contains(removePendingRaw)) { event.preventDefault(); const index=Number(removePendingRaw.dataset.lrRemovePendingRaw); if(Number.isInteger(index) && pendingRawFiles[index]){ pendingRawFiles.splice(index,1); rawUploadStatusMessage=''; rawUploadStatusTone=''; renderMedia(); } return; }
        const article = event.target.closest('[data-lr-article]'); if (article) { q('[data-lr-article-value]').value=article.dataset.lrArticle; qa('[data-lr-article]').forEach(x=>x.classList.toggle('is-active',x===article)); return; }
        const visit = event.target.closest('[data-lr-visit-plyrcard]'); if (visit) { event.preventDefault(); const url=state.website?.url; if(url){ window.open(url,'_blank','noopener'); } else { setView('share'); } return; }
        const copyLink = event.target.closest('[data-lr-copy-link]'); if (copyLink) { const url=state.website?.url || ''; if(url) navigator.clipboard?.writeText(url).then(()=>showToast('PLYRCARD link copied.')).catch(()=>showToast('Copy the link from the field above.')); return; }
        const nativeShare = event.target.closest('[data-lr-native-share]'); if(nativeShare){ const url=state.website?.url || ''; if(url && navigator.share){ navigator.share({title:'My PLYRCARD',url}).catch(()=>{}); } else if(url){ navigator.clipboard?.writeText(url); showToast('PLYRCARD link copied.'); } return; }
        const guestCopy = event.target.closest('[data-lr-guest-copy]'); if (guestCopy) { const url=drawer.dataset.mainShareUrl || ''; if(url) navigator.clipboard?.writeText(url).then(()=>showToast('PLYRCARD link copied.')).catch(()=>showToast('Copy the link from the field above.')); return; }
        const guestShare = event.target.closest('[data-lr-guest-share]'); if (guestShare) { const url=drawer.dataset.mainShareUrl || ''; if(url && navigator.share){ navigator.share({title:'PLYRCARD',url}).catch(()=>{}); } else if(url){ navigator.clipboard?.writeText(url); showToast('PLYRCARD link copied.'); } return; }
        const newSchedule = event.target.closest('[data-lr-new-schedule]'); if (newSchedule) { const form=q('[data-lr-schedule-form]'); form.reset(); form.querySelector('[name="schedule_id"]').value=''; q('[data-lr-schedule-form-title]').textContent='New Schedule'; form.hidden=false; form.scrollIntoView({behavior:'auto',block:'start'}); return; }
        const cancelSchedule = event.target.closest('[data-lr-cancel-schedule]'); if (cancelSchedule) { q('[data-lr-schedule-form]').hidden=true; return; }
        const edit = event.target.closest('[data-lr-edit-schedule]'); if (edit) { const item=(state.schedule?.items||[]).find(x=>String(x.id)===String(edit.dataset.lrEditSchedule)); if(!item)return; const form=q('[data-lr-schedule-form]'); fillForm(form,item); form.querySelector('[name="schedule_id"]').value=item.id; form.querySelector('[name="is_home"]').value=item.is_home?'1':'0'; q('[data-lr-schedule-form-title]').textContent='Edit Schedule'; form.hidden=false; form.scrollIntoView({behavior:'auto',block:'start'}); return; }
        const del = event.target.closest('[data-lr-delete-schedule]'); if (del) { if(!confirm('Remove this schedule item?')) return; request(`${drawer.dataset.scheduleBaseUrl}/${del.dataset.lrDeleteSchedule}`,{method:'DELETE'}).then(json=>{if(json.data)state=json.data; render(); showToast('Schedule removed.');}).catch(err=>showToast(err.message,true)); return; }
    }, true);

    q('[data-lr-sport]')?.addEventListener('change', () => { state.user.sport=q('[data-lr-sport]').value; state.user.position=[]; state.user.league_id=null; state.user.club_id=null; state.user.team_name=null; profilePositionSelection.clear(); renderPositions(false); profileOptionCache.clear(); loadProfileAssociationOptions(); });
    q('[data-lr-gender]')?.addEventListener('change', () => { state.user.gender=q('[data-lr-gender]').value; state.user.league_id=null; state.user.club_id=null; state.user.team_name=null; profileOptionCache.clear(); loadProfileAssociationOptions(); });
    q('[data-lr-league]')?.addEventListener('change', () => { state.user.league_id=q('[data-lr-league]').value || null; state.user.club_id=null; state.user.team_name=null; loadClubOptions(); });
    q('[data-lr-club]')?.addEventListener('change', () => { state.user.club_id=q('[data-lr-club]').value || null; state.user.team_name=null; const age=q('[data-lr-age-group]'); if(age){ age.disabled=!state.user.club_id; if(!state.user.club_id) age.value=''; } });

    q('[data-lr-raw-file-input]')?.addEventListener('change', event => {
        const input = event.currentTarget;
        const existingCount = Array.isArray(state.user?.raw_player_images) ? state.user.raw_player_images.length : 0;
        const available = Math.max(0, 20 - existingCount - pendingRawFiles.length);
        const selected = Array.from(input.files || []).filter(file => String(file.type || '').startsWith('image/'));
        if (available <= 0) { rawUploadStatusMessage='You already have 20 images selected or saved.'; rawUploadStatusTone='error'; input.value=''; renderMedia(); return; }
        const accepted = selected.slice(0, available);
        pendingRawFiles.push(...accepted);
        input.value = '';
        rawUploadStatusMessage = accepted.length < selected.length ? `Added ${accepted.length}. The 20-image limit has been reached.` : '';
        rawUploadStatusTone = accepted.length < selected.length ? 'ready' : '';
        renderMedia();
    });

    q('[data-lr-profile-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form=event.currentTarget;
        if (!profilePositionSelection.size) { showToast('Choose at least one position.', true); return; }
        const fd=new FormData(form); fd.delete('email_display'); fd.delete('raw_player_images_new[]');
        pendingRawFiles.forEach(file => fd.append('raw_player_images_new[]', file, file.name));
        const uploadingCount = pendingRawFiles.length;
        if (uploadingCount > 0) { rawUploadStatusMessage = `Uploading ${uploadingCount} image${uploadingCount === 1 ? '' : 's'}...`; rawUploadStatusTone = 'ready'; updateRawUploadStatus(Array.isArray(state.user?.raw_player_images) ? state.user.raw_player_images.length : 0); }
        try { const json=await request(drawer.dataset.profileUrl,{method:'POST',body:fd}); if(json.data)state=json.data; pendingRawFiles=[]; clearPendingRawPreviewUrls(); rawUploadStatusMessage=uploadingCount > 0 ? `${uploadingCount} image${uploadingCount === 1 ? '' : 's'} uploaded successfully.` : 'Profile saved successfully.'; rawUploadStatusTone='success'; const rawInput=form.querySelector('[name="raw_player_images_new[]"]'); if(rawInput) rawInput.value=''; const proLogo=form.querySelector('[name="pro_club_logo"]'); if(proLogo) proLogo.value=''; render(); showToast(uploadingCount > 0 ? 'Profile and player images saved.' : 'Profile saved.'); } catch(err){ rawUploadStatusMessage = uploadingCount > 0 ? 'Image upload failed. Your selected previews are still here so you can retry.' : ''; rawUploadStatusTone = uploadingCount > 0 ? 'error' : ''; renderMedia(); showToast(err.message,true); }
    });
    q('[data-lr-schedule-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form=event.currentTarget; const fd=new FormData(form); const id=fd.get('schedule_id'); fd.delete('schedule_id'); const url=id?`${drawer.dataset.scheduleBaseUrl}/${id}`:drawer.dataset.scheduleUrl; if(id)fd.append('_method','PUT');
        try { const json=await request(url,{method:'POST',body:fd}); if(json.data)state=json.data; form.hidden=true; render(); showToast(id?'Schedule updated.':'Schedule added.'); } catch(err){ showToast(err.message,true); }
    });
    q('[data-lr-settings-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const notifications={}; qa('[data-lr-setting]').forEach(el=>notifications[el.dataset.lrSetting]=el.checked); const body={notifications}; if(state.plan?.is_premium) body.article_section_type=q('[data-lr-article-value]').value;
        try { const json=await request(drawer.dataset.settingsUrl,{method:'POST',body}); if(json.data)state=json.data; render(); showToast('Settings saved.'); } catch(err){ showToast(err.message,true); }
    });
    q('[data-lr-billing-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const fd=new FormData(event.currentTarget);
        try { const json=await request(drawer.dataset.billingUrl,{method:'POST',body:fd}); if(json.data)state=json.data; render(); showToast('Billing information saved.'); } catch(err){ showToast(err.message,true); }
    });
    q('[data-lr-service-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form=event.currentTarget;
        try { await request(drawer.dataset.additionalServiceUrl,{method:'POST',body:new FormData(form)}); form.reset(); showToast('Service request sent.'); } catch(err){ showToast(err.message,true); }
    });
    q('[data-lr-referral-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form=event.currentTarget; const status=q('[data-lr-referral-status]'); const submit=form.querySelector('[type="submit"]');
        if(status){ status.textContent='Sending invitation email...'; status.className='lr-upload-status is-ready'; }
        if(submit) submit.disabled=true;
        try { const json=await request(drawer.dataset.referralUrl,{method:'POST',body:new FormData(form)}); form.reset(); if(status){ status.textContent=json.message || 'Invitation email sent.'; status.className='lr-upload-status is-success'; } showToast(json.message || 'Invitation email sent.'); } catch(err){ if(status){ status.textContent=err.message; status.className='lr-upload-status is-error'; } showToast(err.message,true); } finally { if(submit) submit.disabled=false; }
    });
    q('[data-lr-login-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form=event.currentTarget;
        try { const json=await request(drawer.dataset.loginUrl,{method:'POST',body:new FormData(form)}); window.location.href=json.redirect_url || window.location.href; } catch(err){ showToast(err.message,true); }
    });
    q('[data-lr-password-reset-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form=event.currentTarget;
        try { await request(drawer.dataset.passwordResetUrl,{method:'POST',body:new FormData(form)}); form.reset(); showToast('If a PLYRCARD account exists for that email, a password reset link has been sent.'); } catch(err){ showToast(err.message,true); }
    });
    q('[data-lr-password-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form=event.currentTarget;
        try {
            await request(drawer.dataset.passwordUpdateUrl,{method:'POST',body:new FormData(form)});
            form.reset(); forcePassword=false; drawer.dataset.forcePassword='0'; history=[]; setView('home',false); showToast('Password updated.');
        } catch(err){ showToast(err.message,true); }
    });

    document.addEventListener('click', event => { const picker=q('[data-lr-position-picker]'); const menu=q('[data-lr-position-menu]'); if(picker && menu && !picker.contains(event.target)) menu.hidden=true; });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !drawer.classList.contains('is-open')) return;
        if (q('[data-lr-dashboard-school-panel]')?.classList.contains('is-open')) { closeDashboardSchool(); return; }
        if (q('[data-lr-dashboard-activity-panel]')?.classList.contains('is-open')) { closeDashboardActivity(); return; }
        closeDrawer();
    });

    // Keep the existing public mobile navigation functional without any page-loader animation.
    const menuButton=document.getElementById('menu-btn'), mobileNav=document.getElementById('mobile-nav');
    if(menuButton && mobileNav && menuButton.dataset.lrBound!=='1') { menuButton.dataset.lrBound='1'; menuButton.addEventListener('click',()=>{const open=mobileNav.classList.toggle('open');menuButton.setAttribute('aria-expanded',open?'true':'false');}); }
    const header=document.getElementById('site-header'); if(header && header.dataset.lrScrollBound!=='1'){header.dataset.lrScrollBound='1'; const onScroll=()=>header.classList.toggle('scrolled',window.scrollY>14); onScroll(); window.addEventListener('scroll',onScroll,{passive:true});}

    if (forcePassword) setView('password', false);
    else render();
})();
</script>
@endif