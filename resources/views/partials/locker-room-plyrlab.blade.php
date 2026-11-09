<style>
    #plyrcard-action-drawer .plab-shell{
        --plab-bg:#0c0d10;--plab-panel:#16181d;--plab-panel2:#1d2027;--plab-line:#2b2f37;
        --plab-fg:#f3f0ec;--plab-muted:#8e929b;--plab-coral:#ff6348;--plab-ice:#9fe3ff;
        --plab-gold:#f2bf3a;--plab-green:#34d27a;--plab-red:#ef4a52;
        background:
            radial-gradient(90% 55% at 50% -10%,rgba(255,99,72,.15),transparent 62%),
            linear-gradient(180deg,#111319,#0c0d10);
        color:var(--plab-fg);border:1px solid var(--plab-line);border-radius:16px;overflow:hidden;
        min-height:560px;font-family:Inter,"IBM Plex Sans",system-ui,sans-serif
    }
    #plyrcard-action-drawer .plab-shell *{box-sizing:border-box;min-width:0}
    #plyrcard-action-drawer .plab-top{
        padding:18px 20px 15px;border-bottom:1px solid var(--plab-line);
        display:flex;align-items:flex-end;justify-content:space-between;gap:14px;background:rgba(22,24,29,.94)
    }
    #plyrcard-action-drawer .plab-eyebrow{
        font-size:9px;font-weight:900;letter-spacing:.2em;text-transform:uppercase;color:var(--plab-coral)
    }
    #plyrcard-action-drawer .plab-title{
        margin:4px 0 0;font-family:Impact,"Arial Black",sans-serif;font-weight:400;
        text-transform:uppercase;letter-spacing:-.02em;font-size:30px;line-height:.95
    }
    #plyrcard-action-drawer .plab-title em{font-style:normal;color:var(--plab-coral)}
    #plyrcard-action-drawer .plab-status{
        text-align:right;font-size:9px;line-height:1.45;color:var(--plab-muted);text-transform:uppercase;letter-spacing:.08em
    }
    #plyrcard-action-drawer .plab-status b{color:var(--plab-fg)}
    #plyrcard-action-drawer .plab-loading{padding:70px 20px;text-align:center;color:var(--plab-muted);font-size:11px}
    #plyrcard-action-drawer .plab-loading i{display:block;font-size:24px;color:var(--plab-coral);margin-bottom:10px}

    #plyrcard-action-drawer .plab-lock{padding:20px;display:grid;gap:14px}
    #plyrcard-action-drawer .plab-lock-hero{
        border:1px solid rgba(255,99,72,.35);border-radius:14px;padding:18px;
        background:linear-gradient(135deg,rgba(255,99,72,.13),rgba(255,255,255,.025));
        display:grid;grid-template-columns:46px minmax(0,1fr);gap:13px;align-items:start
    }
    #plyrcard-action-drawer .plab-lock-icon{
        width:46px;height:46px;border-radius:12px;background:var(--plab-coral);color:#111;display:grid;place-items:center;font-size:18px
    }
    #plyrcard-action-drawer .plab-lock-hero h3{margin:0;font-size:20px;text-transform:uppercase;font-weight:900}
    #plyrcard-action-drawer .plab-lock-hero p{margin:5px 0 0;color:#cbc7c1;font-size:11px;line-height:1.55}
    #plyrcard-action-drawer .plab-section-title{
        margin:2px 0 8px;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.06em
    }
    #plyrcard-action-drawer .plab-trainers{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
    #plyrcard-action-drawer .plab-trainer{
        width:100%;border:1px solid var(--plab-line);background:var(--plab-panel);border-radius:12px;padding:11px;
        color:var(--plab-fg);text-align:left;cursor:pointer;display:grid;grid-template-columns:42px minmax(0,1fr);gap:9px
    }
    #plyrcard-action-drawer .plab-trainer.is-selected{border-color:var(--plab-coral);box-shadow:inset 0 0 0 1px rgba(255,99,72,.25)}
    #plyrcard-action-drawer .plab-avatar{
        width:42px;height:42px;border-radius:50%;overflow:hidden;background:#292d35;display:grid;place-items:center;
        color:var(--plab-coral);font-size:11px;font-weight:900;border:1px solid #343943
    }
    #plyrcard-action-drawer .plab-avatar img{width:100%;height:100%;object-fit:cover}
    #plyrcard-action-drawer .plab-trainer strong{display:block;font-size:11px}
    #plyrcard-action-drawer .plab-trainer small{display:block;color:var(--plab-muted);font-size:9px;margin-top:2px;line-height:1.4}
    #plyrcard-action-drawer .plab-specialties{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
    #plyrcard-action-drawer .plab-chip{
        display:inline-flex;align-items:center;border:1px solid #3b4049;border-radius:999px;padding:2px 7px;
        font-size:8px;color:#d8d4cf;background:#20232a
    }
    #plyrcard-action-drawer .plab-book-form{
        border:1px solid var(--plab-line);border-radius:12px;background:var(--plab-panel);padding:13px;display:grid;gap:9px
    }
    #plyrcard-action-drawer .plab-book-form label{display:grid;gap:4px;font-size:9px;font-weight:850;letter-spacing:.08em;text-transform:uppercase;color:var(--plab-muted)}
    #plyrcard-action-drawer .plab-in{
        width:100%;border:1px solid #3a3e47;border-radius:8px;background:var(--plab-panel2);color:var(--plab-fg);
        padding:9px 10px;font:inherit;font-size:11px
    }
    #plyrcard-action-drawer textarea.plab-in{resize:vertical;min-height:70px}
    #plyrcard-action-drawer .plab-btn{
        border:1px solid var(--plab-coral);border-radius:8px;background:var(--plab-coral);color:#111;
        padding:9px 12px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;cursor:pointer
    }
    #plyrcard-action-drawer .plab-btn.is-ghost{background:transparent;border-color:#3a3e47;color:var(--plab-fg)}
    #plyrcard-action-drawer .plab-btn:disabled{opacity:.55;cursor:wait}

    #plyrcard-action-drawer .plab-athlete-head{
        display:grid;grid-template-columns:48px minmax(0,1fr) auto;gap:11px;align-items:center;
        padding:12px 15px;border-bottom:1px solid var(--plab-line);background:#17191f
    }
    #plyrcard-action-drawer .plab-athlete-head .plab-avatar{width:48px;height:48px}
    #plyrcard-action-drawer .plab-athlete-name{
        font-family:"Arial Narrow",Impact,sans-serif;font-weight:900;font-size:20px;line-height:1;text-transform:uppercase
    }
    #plyrcard-action-drawer .plab-athlete-sub{margin-top:3px;font-size:9px;color:var(--plab-muted);text-transform:uppercase;letter-spacing:.08em}
    #plyrcard-action-drawer .plab-sync{display:grid;justify-items:end;gap:4px}
    #plyrcard-action-drawer .plab-sync small{font-size:8px;color:var(--plab-muted);text-align:right}
    #plyrcard-action-drawer .plab-nav{
        display:flex;overflow-x:auto;border-bottom:1px solid var(--plab-line);background:#13161b;scrollbar-width:none
    }
    #plyrcard-action-drawer .plab-nav::-webkit-scrollbar{display:none}
    #plyrcard-action-drawer .plab-tab{
        flex:1 0 auto;border:0;border-bottom:3px solid transparent;background:transparent;color:#d9d6d2;
        padding:10px 13px 8px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;cursor:pointer
    }
    #plyrcard-action-drawer .plab-tab.is-active{color:var(--plab-coral);border-bottom-color:var(--plab-coral);background:rgba(255,99,72,.06)}
    #plyrcard-action-drawer .plab-body{padding:14px;background:linear-gradient(180deg,rgba(36,40,60,.4),rgba(18,20,25,.92));min-height:420px}
    #plyrcard-action-drawer .plab-notice{
        border:1px solid #554d2a;background:rgba(242,191,58,.08);color:#e7dba8;border-radius:9px;padding:9px 11px;
        display:flex;gap:8px;align-items:flex-start;font-size:10px;line-height:1.45;margin-bottom:11px
    }
    #plyrcard-action-drawer .plab-notice i{color:var(--plab-gold);margin-top:2px}

    #plyrcard-action-drawer .plab-home{display:grid;grid-template-columns:185px minmax(0,1fr);gap:14px}
    #plyrcard-action-drawer .plab-card-wrap{width:165px;max-width:100%;margin:0 auto}
    #plyrcard-action-drawer .plab-card{
        --tier:var(--plab-coral);position:relative;aspect-ratio:230/330;width:100%;padding:3px;
        background:linear-gradient(160deg,var(--tier),#ffffff55 30%,var(--tier) 55%,#0008);
        clip-path:polygon(50% 0,60% 3.5%,100% 3.5%,100% 85%,50% 100%,0 85%,0 3.5%,40% 3.5%);
        filter:drop-shadow(0 12px 20px rgba(0,0,0,.5))
    }
    #plyrcard-action-drawer .plab-card-in{
        position:relative;width:100%;height:100%;overflow:hidden;color:#fff;
        clip-path:inherit;background:radial-gradient(100% 65% at 72% 10%,rgba(255,99,72,.45),transparent 60%),linear-gradient(170deg,#2b2f3a,#121318 75%)
    }
    #plyrcard-action-drawer .plab-card-photo{
        position:absolute;right:3%;top:7%;width:67%;height:49%;overflow:hidden;display:grid;place-items:end center;
        -webkit-mask-image:linear-gradient(180deg,#000 70%,transparent);mask-image:linear-gradient(180deg,#000 70%,transparent)
    }
    #plyrcard-action-drawer .plab-card-photo img{width:100%;height:100%;object-fit:cover;object-position:center top}
    #plyrcard-action-drawer .plab-card-photo span{font-size:55px;color:#60656f;margin:auto}
    #plyrcard-action-drawer .plab-card-ovr{position:absolute;left:9%;top:9%;font-size:35px;font-weight:950}
    #plyrcard-action-drawer .plab-card-pos{position:absolute;left:10%;top:23%;font-size:11px;font-weight:900;text-transform:uppercase}
    #plyrcard-action-drawer .plab-card-name{
        position:absolute;left:8%;right:8%;top:56%;text-align:center;font-size:15px;font-weight:950;text-transform:uppercase;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border-bottom:1px solid rgba(255,255,255,.22);padding-bottom:4px
    }
    #plyrcard-action-drawer .plab-card-stats{
        position:absolute;left:11%;right:11%;top:66%;display:grid;grid-template-columns:1fr 1fr;gap:4px 9px
    }
    #plyrcard-action-drawer .plab-card-stat{display:flex;justify-content:space-between;gap:4px;font-size:8px}
    #plyrcard-action-drawer .plab-card-stat b{font-size:10px}
    #plyrcard-action-drawer .plab-card-brand{
        position:absolute;left:0;right:0;bottom:7%;text-align:center;font-size:6px;font-weight:900;letter-spacing:.28em;color:#ddd
    }
    #plyrcard-action-drawer .plab-home-main{display:grid;gap:9px;align-content:start}
    #plyrcard-action-drawer .plab-welcome{
        border:1px solid rgba(255,99,72,.35);border-radius:12px;padding:13px;
        background:radial-gradient(90% 120% at 100% 0,rgba(255,99,72,.22),transparent 60%),var(--plab-panel)
    }
    #plyrcard-action-drawer .plab-welcome span{font-size:9px;color:#ff8a72;text-transform:uppercase;font-weight:900;letter-spacing:.14em}
    #plyrcard-action-drawer .plab-welcome h3{margin:4px 0 2px;font-size:22px;text-transform:uppercase}
    #plyrcard-action-drawer .plab-welcome p{margin:0;color:#cfcac3;font-size:10px;line-height:1.45}
    #plyrcard-action-drawer .plab-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
    #plyrcard-action-drawer .plab-info{
        border:1px solid var(--plab-line);border-radius:9px;background:var(--plab-panel);padding:9px
    }
    #plyrcard-action-drawer .plab-info small{display:block;color:var(--plab-muted);font-size:8px;text-transform:uppercase;letter-spacing:.1em}
    #plyrcard-action-drawer .plab-info strong{display:block;margin-top:3px;font-size:11px;line-height:1.3}
    #plyrcard-action-drawer .plab-trainerbar{
        border:1px solid var(--plab-line);border-left:3px solid var(--plab-coral);border-radius:9px;background:var(--plab-panel);padding:10px
    }
    #plyrcard-action-drawer .plab-trainerbar small{display:block;color:var(--plab-muted);font-size:8px;text-transform:uppercase;letter-spacing:.1em}
    #plyrcard-action-drawer .plab-trainerbar strong{display:block;font-size:11px;margin-top:2px}
    #plyrcard-action-drawer .plab-trainerbar span{font-size:9px;color:#cfcac3}

    #plyrcard-action-drawer .plab-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin-bottom:10px}
    #plyrcard-action-drawer .plab-kpi{border:1px solid var(--plab-line);border-radius:9px;background:rgba(0,0,0,.2);padding:9px}
    #plyrcard-action-drawer .plab-kpi small{display:block;color:var(--plab-muted);font-size:8px;text-transform:uppercase;letter-spacing:.08em}
    #plyrcard-action-drawer .plab-kpi b{display:block;font-size:21px;line-height:1.05;margin-top:3px;font-variant-numeric:tabular-nums}
    #plyrcard-action-drawer .plab-kpi em{display:block;font-style:normal;color:var(--plab-muted);font-size:8px;margin-top:2px}
    #plyrcard-action-drawer .plab-metrics-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
    #plyrcard-action-drawer .plab-box{border:1px solid var(--plab-line);border-radius:10px;background:rgba(0,0,0,.2);padding:11px}
    #plyrcard-action-drawer .plab-box h3{margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.05em}
    #plyrcard-action-drawer .plab-metric{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px;padding:8px 0;border-top:1px solid var(--plab-line)}
    #plyrcard-action-drawer .plab-metric:first-of-type{border-top:0}
    #plyrcard-action-drawer .plab-metric span{font-size:10px;color:#d7d3cd}
    #plyrcard-action-drawer .plab-metric b{font:900 13px ui-monospace,SFMono-Regular,monospace;color:var(--plab-ice);text-align:right}
    #plyrcard-action-drawer .plab-empty{
        border:1px dashed #3a3e47;border-radius:10px;padding:24px 14px;text-align:center;color:var(--plab-muted);
        font-size:10px;line-height:1.55;background:rgba(0,0,0,.16)
    }

    #plyrcard-action-drawer .plab-bodymap{display:grid;grid-template-columns:minmax(0,250px) minmax(0,1fr);gap:14px;align-items:start}
    #plyrcard-action-drawer .plab-figure{border:1px solid var(--plab-line);border-radius:10px;background:#111318;padding:10px}
    #plyrcard-action-drawer .plab-figure svg{display:block;width:100%;height:auto;max-height:360px}
    #plyrcard-action-drawer .plab-zone{fill:#30343d;stroke:#434852;stroke-width:1.5}
    #plyrcard-action-drawer .plab-zone.is-measured{fill:rgba(255,99,72,.72);stroke:#ff8a72}
    #plyrcard-action-drawer .plab-zone.is-balanced{fill:rgba(52,210,122,.7);stroke:#65e69d}
    #plyrcard-action-drawer .plab-zone.is-focus{fill:rgba(242,191,58,.78);stroke:#ffd25c}
    #plyrcard-action-drawer .plab-zone-list{display:grid;gap:7px}
    #plyrcard-action-drawer .plab-zone-row{border:1px solid var(--plab-line);border-radius:9px;background:var(--plab-panel);padding:9px}
    #plyrcard-action-drawer .plab-zone-row small{display:block;color:var(--plab-muted);font-size:8px;text-transform:uppercase;letter-spacing:.08em}
    #plyrcard-action-drawer .plab-zone-row strong{display:block;margin-top:2px;font-size:11px}
    #plyrcard-action-drawer .plab-zone-row span{display:block;margin-top:3px;color:#cfcac3;font-size:9px;line-height:1.4}

    #plyrcard-action-drawer .plab-d1-top{display:grid;grid-template-columns:105px minmax(0,1fr);gap:11px;margin-bottom:10px;align-items:center}
    #plyrcard-action-drawer .plab-d1-score{border:1px solid var(--plab-line);border-radius:10px;background:linear-gradient(160deg,rgba(255,99,72,.16),rgba(0,0,0,.25));padding:11px;text-align:center}
    #plyrcard-action-drawer .plab-d1-score small{display:block;color:var(--plab-muted);font-size:8px;text-transform:uppercase}
    #plyrcard-action-drawer .plab-d1-score b{display:block;font-size:35px;line-height:1}
    #plyrcard-action-drawer .plab-d1-score b em{font-style:normal;font-size:13px;color:var(--plab-muted)}
    #plyrcard-action-drawer .plab-d1-score span{display:block;color:var(--plab-coral);font-size:9px;font-weight:900;text-transform:uppercase;margin-top:3px}
    #plyrcard-action-drawer .plab-d1-copy h3{margin:0 0 4px;font-size:15px;text-transform:uppercase}
    #plyrcard-action-drawer .plab-d1-copy p{margin:0;color:var(--plab-muted);font-size:9px;line-height:1.5}
    #plyrcard-action-drawer .plab-d1rows{display:grid;gap:7px}
    #plyrcard-action-drawer .plab-d1row{border:1px solid var(--plab-line);border-radius:9px;background:rgba(0,0,0,.2);padding:9px}
    #plyrcard-action-drawer .plab-d1row-top{display:flex;justify-content:space-between;gap:8px;font-size:10px}
    #plyrcard-action-drawer .plab-d1row-top span{color:var(--plab-muted)}
    #plyrcard-action-drawer .plab-d1bar{position:relative;height:7px;border-radius:5px;background:#353943;margin:7px 0;overflow:visible}
    #plyrcard-action-drawer .plab-d1bar i{display:block;height:100%;border-radius:5px;background:linear-gradient(90deg,var(--plab-coral),var(--plab-gold))}
    #plyrcard-action-drawer .plab-d1bar em{position:absolute;left:83.33%;top:-3px;bottom:-3px;width:2px;background:#fff}
    #plyrcard-action-drawer .plab-d1values{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:9px;color:var(--plab-muted)}
    #plyrcard-action-drawer .plab-d1values b{color:var(--plab-fg)}
    #plyrcard-action-drawer .plab-d1values .target b{color:var(--plab-gold)}

    @media(max-width:760px){
        #plyrcard-action-drawer .plab-top{align-items:flex-start}
        #plyrcard-action-drawer .plab-status{display:none}
        #plyrcard-action-drawer .plab-trainers{grid-template-columns:1fr}
        #plyrcard-action-drawer .plab-home{grid-template-columns:118px minmax(0,1fr);gap:10px}
        #plyrcard-action-drawer .plab-card-wrap{width:112px}
        #plyrcard-action-drawer .plab-info-grid{grid-template-columns:1fr}
        #plyrcard-action-drawer .plab-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
        #plyrcard-action-drawer .plab-metrics-grid{grid-template-columns:1fr}
        #plyrcard-action-drawer .plab-bodymap{grid-template-columns:1fr}
        #plyrcard-action-drawer .plab-figure{max-width:260px;margin:0 auto;width:100%}
    }
    @media(max-width:460px){
        #plyrcard-action-drawer .plab-body{padding:10px}
        #plyrcard-action-drawer .plab-athlete-head{grid-template-columns:42px minmax(0,1fr)}
        #plyrcard-action-drawer .plab-athlete-head .plab-avatar{width:42px;height:42px}
        #plyrcard-action-drawer .plab-sync{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between}
        #plyrcard-action-drawer .plab-home{grid-template-columns:1fr}
        #plyrcard-action-drawer .plab-card-wrap{width:150px}
    }
</style>

<section class="lr-view" data-lr-view="plyrlab">
    <div class="lr-section">
        <div class="plab-shell" data-plab-root>
            <div class="plab-loading"><i class="fa-solid fa-flask"></i>Loading your PLYRLab…</div>
        </div>
    </div>
</section>


<style id="plyrlab-matchday-night">
    .plab-shell{
        --plab-cond:"Arial Narrow","Roboto Condensed",Arial,sans-serif;
        --plab-display:Impact,"Arial Black",sans-serif;
        --plab-mono:"IBM Plex Mono",ui-monospace,SFMono-Regular,Menlo,monospace;
        --plab-green:#34d27a;--plab-yellow:#f2bf3a;--plab-red:#ef4a52;
    }

    /* Match the supplied athlete-preview navigation exactly enough to survive
       pages where Font Awesome is not available: these icons are inline SVG. */
    .plab-nav{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr));overflow:visible!important}
    .plab-tab{
        display:flex!important;align-items:center;justify-content:center;gap:9px;
        min-height:58px;padding:12px 18px 10px!important;font:800 17px var(--plab-cond)!important;
        letter-spacing:.05em;text-transform:uppercase;white-space:nowrap
    }
    .plab-tab svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:0 0 auto}
    .plab-tab.is-active{background:rgba(255,99,72,.07)!important}
    .plab-tab-label{display:inline}

    /* Home = same Matchday Night hierarchy as the HTML reference. */
    .plab-match-home{display:grid;gap:14px}
    .plab-hero2{
        position:relative;overflow:hidden;display:grid;grid-template-columns:250px minmax(0,1fr);
        gap:24px;align-items:center;padding:18px 22px;border-radius:14px;
        border:1px solid rgba(255,99,72,.35);
        background:
            linear-gradient(115deg,rgba(255,255,255,.06) 0 18%,transparent 18% 40%,rgba(255,255,255,.03) 40% 52%,transparent 52%),
            radial-gradient(90% 120% at 100% 0%,rgba(255,99,72,.28),transparent 60%),
            linear-gradient(135deg,#15171c 0%,#23262e 55%,#17191e 100%);
    }
    .plab-hero-main{display:grid;gap:6px;justify-items:start;min-width:0}
    .plab-hero-k{font:800 14px var(--plab-cond);letter-spacing:.16em;text-transform:uppercase;color:#ff8a72}
    .plab-hero-name{margin:0;font:400 clamp(30px,4vw,48px)/.96 var(--plab-display);text-transform:uppercase;letter-spacing:.01em;overflow-wrap:anywhere}
    .plab-hero-sub{font-size:14px;color:#cfcac3}
    .plab-latest-chip{
        margin-top:7px;border:1px solid rgba(242,191,58,.55);background:rgba(242,191,58,.1);color:var(--plab-gold);
        font:800 15px var(--plab-cond);letter-spacing:.06em;text-transform:uppercase;padding:7px 12px;border-radius:5px;cursor:pointer
    }
    .plab-latest-chip:hover{background:rgba(242,191,58,.18)}
    .plab-home-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .plab-match-block{background:var(--plab-panel);border:1px solid var(--plab-line);border-radius:10px;padding:14px 16px;display:grid;gap:7px;align-content:start}
    .plab-match-block.is-button{cursor:pointer;color:inherit;text-align:left;width:100%;font:inherit}
    .plab-match-block.is-button:hover{border-color:var(--plab-coral)}
    .plab-block-k{font:800 13px var(--plab-cond);letter-spacing:.16em;text-transform:uppercase;color:var(--plab-coral)}
    .plab-block-t{font:800 24px/1.05 var(--plab-cond);text-transform:uppercase;letter-spacing:.02em}
    .plab-block-p{margin:0;font-size:13px;color:#cfcac3}
    .plab-measured-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:2px}
    .plab-measured-chip{border:1px solid var(--plab-line);border-radius:14px;padding:3px 9px;font-size:12px;color:#cfcac3}
    .plab-measured-chip b{font-family:var(--plab-mono);color:var(--plab-ice);font-weight:600}
    .plab-coachbar{border:1px solid var(--plab-line);border-left:3px solid var(--plab-coral);border-radius:8px;background:var(--plab-panel);padding:10px 14px;display:grid;gap:2px}
    .plab-coachbar strong{font:800 19px var(--plab-cond);text-transform:uppercase}.plab-coachbar small{color:var(--plab-muted);font-size:12px}


    .plab-connect{
        border:1px solid rgba(255,99,72,.55);border-radius:12px;background:
        radial-gradient(90% 130% at 100% 0%,rgba(255,99,72,.16),transparent 60%),var(--plab-panel);
        padding:14px 16px;margin-bottom:14px;display:grid;gap:10px
    }
    .plab-connect-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:start}
    .plab-connect-k{font:800 12px var(--plab-cond);letter-spacing:.16em;text-transform:uppercase;color:var(--plab-coral)}
    .plab-connect-title{margin:2px 0 3px;font:800 24px/1 var(--plab-cond);text-transform:uppercase}
    .plab-connect-copy{margin:0;color:#cfcac3;font-size:12px;line-height:1.5}
    .plab-connect-state{border:1px solid var(--plab-line);border-radius:999px;padding:5px 9px;font:800 10px var(--plab-cond);letter-spacing:.1em;text-transform:uppercase;color:var(--plab-gold);white-space:nowrap}
    .plab-connect-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:end}
    .plab-connect-form label{display:grid;gap:4px;font:800 9px var(--plab-cond);letter-spacing:.12em;text-transform:uppercase;color:var(--plab-muted)}
    .plab-connect-help{font-size:10px;color:var(--plab-muted);line-height:1.4}

    /* Exact PLYRCARD structure from the supplied reference, with stronger
       handling for long real-world names/profile labels. */
    .plab-card-wrap{width:230px!important;max-width:100%;margin:0 auto}
    .plab-pcard{
        --shape:polygon(0 7.5%,13% 5.5%,37% 3%,43% 0,50% 2.6%,57% 0,63% 3%,87% 5.5%,100% 7.5%,100% 85%,50% 100%,0 85%);
        container-type:inline-size;position:relative;width:100%;aspect-ratio:300/422;
        filter:drop-shadow(0 16px 26px rgba(0,0,0,.55))
    }
    .plab-pc-frame{position:absolute;inset:0;clip-path:var(--shape);background:linear-gradient(150deg,var(--f1) 0%,var(--f2) 28%,var(--f3) 52%,var(--f2) 74%,var(--f1) 100%);padding:3.4cqw}
    .plab-pc-in{position:relative;width:100%;height:100%;clip-path:var(--shape);overflow:hidden;color:#fff;background:linear-gradient(180deg,#1d2f52 0%,#182a47 55%,#0f1d33 100%);box-shadow:inset 0 0 0 1.2cqw rgba(10,20,40,.55)}
    .plab-pc-art{position:absolute;inset:0;background:radial-gradient(60% 45% at 62% 52%,rgba(255,150,70,.55),transparent 70%),radial-gradient(50% 40% at 18% 22%,rgba(140,120,255,.45),transparent 70%),repeating-linear-gradient(118deg,rgba(255,255,255,.07) 0 2cqw,transparent 2cqw 9cqw),linear-gradient(200deg,rgba(255,99,72,.35),transparent 55%);mix-blend-mode:screen}
    .plab-pc-photo{position:absolute;right:-2%;top:8%;width:72%;height:52%;display:grid;place-items:end center;-webkit-mask-image:linear-gradient(180deg,transparent 0,#000 14%,#000 64%,transparent 100%),linear-gradient(90deg,transparent 0,#000 34%);-webkit-mask-composite:source-in;mask-image:linear-gradient(180deg,transparent 0,#000 14%,#000 64%,transparent 100%),linear-gradient(90deg,transparent 0,#000 34%);mask-composite:intersect}
    .plab-pc-photo img{width:100%;height:100%;object-fit:cover;object-position:50% 15%}.plab-pc-photo svg{width:78%;height:auto;opacity:.55;align-self:end;justify-self:center}
    .plab-pc-top{position:absolute;left:9%;top:13%;display:grid;justify-items:start;gap:1.2cqw;z-index:2}
    .plab-pc-ovr{font:800 25cqw/.82 var(--plab-cond);letter-spacing:-.02em;text-shadow:0 .6cqw 2cqw rgba(0,0,0,.45);display:flex;align-items:flex-start;gap:1cqw}
    .plab-pc-ovr small{font-size:5.4cqw;letter-spacing:.06em;margin-top:2.4cqw;writing-mode:vertical-rl;transform:rotate(180deg);opacity:.85}
    .plab-pc-profile{font:800 8cqw/1 var(--plab-cond);letter-spacing:.02em;text-transform:uppercase;max-width:42cqw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-shadow:0 .5cqw 1.6cqw rgba(0,0,0,.5)}
    .plab-pc-crest{margin-top:1.4cqw;width:15cqw;height:17.5cqw;display:grid;place-content:center;justify-items:center;gap:.4cqw;clip-path:polygon(0 0,100% 0,100% 62%,50% 100%,0 62%);background:linear-gradient(160deg,var(--f1),var(--f3));color:#1a1405}
    .plab-pc-crest b{font:800 5.8cqw/1 var(--plab-cond)}.plab-pc-crest span{font:700 2.7cqw var(--plab-cond);letter-spacing:.12em;text-transform:uppercase;margin-top:-1cqw;padding-bottom:4cqw}
    .plab-pc-name{position:absolute;left:6%;right:6%;top:50.5%;z-index:2;display:grid;justify-items:end;text-align:right;line-height:.86;text-shadow:0 .6cqw 2cqw rgba(0,0,0,.55)}
    .plab-pc-first{font:400 7.4cqw var(--plab-display);text-transform:uppercase;margin-right:22%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
    .plab-pc-last{font:400 15cqw var(--plab-display);text-transform:uppercase;letter-spacing:-.02em;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:clip}
    .plab-pc-name.is-long .plab-pc-first{font-size:6.8cqw}.plab-pc-name.is-long .plab-pc-last{font-size:13cqw}
    .plab-pc-stats{position:absolute;left:12%;right:12%;top:65.5%;z-index:2;display:grid;grid-template-columns:1fr auto 1fr;gap:4cqw;border-top:.5cqw solid rgba(255,255,255,.35);padding-top:2.4cqw}
    .plab-pc-col{display:grid;gap:.6cqw}.plab-pc-col div{display:flex;justify-content:space-between;align-items:baseline;gap:2cqw;font-family:var(--plab-cond);line-height:1}
    .plab-pc-col b{font-weight:800;font-size:8.2cqw}.plab-pc-col span{font-weight:600;font-size:6.2cqw;letter-spacing:.02em;opacity:.92}.plab-pc-div{width:.5cqw;background:rgba(255,255,255,.3)}
    .plab-pc-brand{position:absolute;left:0;right:0;bottom:5.5%;text-align:center;font:400 4.2cqw var(--plab-display);letter-spacing:.22em;color:rgba(255,255,255,.9);z-index:2}
    .plab-pc-brand:before{content:"";display:block;width:26%;height:.4cqw;margin:0 auto 2cqw;background:rgba(255,255,255,.35)}

    /* Stats cards retain the reference's Rating / Measured / Benchmark layers. */
    .plab-ratings-legend{display:flex;flex-wrap:wrap;gap:6px 16px;font-size:12px;color:var(--plab-muted);margin-bottom:12px}
    .plab-ratings-legend i{display:inline-block;width:12px;height:12px;margin-right:6px;vertical-align:-1px}
    .plab-ratings-legend .r{clip-path:polygon(50% 0,100% 18%,100% 76%,50% 100%,0 76%,0 18%);background:linear-gradient(160deg,#3ce08a,#1a8f52)}
    .plab-ratings-legend .m{border:2px solid var(--plab-ice);border-radius:2px}.plab-ratings-legend .b{border:2px dashed var(--plab-gold);border-radius:5px}
    .plab-stat-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(285px,1fr));gap:12px}
    .plab-stat-card{background:var(--plab-panel);border:1px solid var(--plab-line);border-radius:12px;padding:14px;display:grid;gap:10px;align-content:start}
    .plab-stat-head{display:grid;grid-template-columns:auto minmax(0,1fr);gap:12px;align-items:center}
    .plab-rating-shield{width:58px;height:68px;display:grid;place-content:center;justify-items:center;line-height:1;gap:1px;clip-path:polygon(50% 0,100% 18%,100% 76%,50% 100%,0 76%,0 18%);font-family:var(--plab-cond);color:#07140d;background:linear-gradient(160deg,#3ce08a,#1a8f52)}
    .plab-rating-shield.is-mid{background:linear-gradient(160deg,#ffd25c,#b8871b);color:#1a1405}.plab-rating-shield.is-low{background:linear-gradient(160deg,#ff7076,#a8262d);color:#1a0506}.plab-rating-shield.is-empty{background:#3a3e47;color:#c9c4bd}
    .plab-rating-shield b{font-size:28px}.plab-rating-shield small{font-size:8px;font-weight:800;letter-spacing:.12em}
    .plab-stat-name{margin:0;font:800 26px/1 var(--plab-cond);text-transform:uppercase}.plab-stat-sub{display:block;color:var(--plab-muted);font-size:12px;margin-top:3px}
    .plab-measure{background:#0b0d11;border:1px solid #22303a;border-radius:10px;padding:10px 12px;display:grid;gap:3px}.plab-measure small{font:600 10px var(--plab-mono);letter-spacing:.18em;text-transform:uppercase;color:var(--plab-ice)}
    .plab-measure b{font:600 30px/1.05 var(--plab-mono)}.plab-measure b em{font-style:normal;font-size:13px;color:var(--plab-muted);margin-left:5px}.plab-benchmark{border-left:2px solid var(--plab-gold);padding-left:9px;font-size:12px;color:#d3cec7}

    /* Body map copied from the reference's front/back athlete figure logic. */
    .plab-body-map{display:grid;grid-template-columns:minmax(0,360px) minmax(0,1fr);gap:26px;align-items:start}
    .plab-body-figures{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .plab-body-svg{width:100%;height:auto;display:block}.plab-body-neutral>*{fill:#2a2e37;stroke:#3a3e47;stroke-width:1}
    .plab-body-zone{stroke:rgba(0,0,0,.55);stroke-width:1.2;cursor:pointer;transition:opacity .15s,filter .15s}.plab-body-zone:hover,.plab-body-zone:focus{outline:none;filter:brightness(1.18)}
    .plab-body-zone.z-g{fill:var(--plab-green)}.plab-body-zone.z-y{fill:var(--plab-yellow)}.plab-body-zone.z-r{fill:var(--plab-red)}.plab-body-zone.z-u{fill:#4a4f5a}
    .plab-body-zone.is-active{stroke:#fff;stroke-width:2.4}.plab-body-zone.is-weak{animation:plab-body-pulse 1.6s ease-in-out infinite}
    @keyframes plab-body-pulse{50%{opacity:.55}}
    .plab-body-labels text{fill:var(--plab-muted);font:700 12px var(--plab-cond);letter-spacing:.1em}.plab-body-labels .view{font-size:13px;fill:#d9d6d2}
    .plab-body-legend{display:flex;justify-content:center;flex-wrap:wrap;gap:6px 12px;margin-top:8px;color:var(--plab-muted);font-size:11px}
    .plab-body-legend span:before{content:"";display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px}.plab-body-legend .g:before{background:var(--plab-green)}.plab-body-legend .y:before{background:var(--plab-yellow)}.plab-body-legend .r:before{background:var(--plab-red)}.plab-body-legend .u:before{background:#4a4f5a}
    .plab-body-detail{min-width:0}.plab-body-boss{border:1px solid var(--plab-coral);border-radius:8px;background:rgba(255,99,72,.08);padding:12px 14px;margin-bottom:12px}
    .plab-body-boss b{display:block;font:800 26px var(--plab-cond);text-transform:uppercase}.plab-body-boss small{color:var(--plab-muted);font-size:12px}
    .plab-zone-detail{border:1px solid var(--plab-line);border-radius:10px;background:rgba(0,0,0,.2);padding:14px}.plab-zone-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
    .plab-zone-head small{font:800 11px var(--plab-cond);letter-spacing:.14em;text-transform:uppercase;color:var(--plab-muted)}.plab-zone-head h3{font:800 24px/1 var(--plab-cond);text-transform:uppercase;margin:3px 0 0}.plab-zone-rating{font:800 44px/1 var(--plab-cond)}
    .plab-zone-rating.z-g{color:var(--plab-green)}.plab-zone-rating.z-y{color:var(--plab-yellow)}.plab-zone-rating.z-r{color:var(--plab-red)}.plab-zone-rating.z-u{color:var(--plab-muted)}
    .plab-zone-measure{font:600 13px var(--plab-mono);color:var(--plab-ice);margin:10px 0 5px}.plab-zone-copy{margin:0;color:#cfcac3;font-size:13px}
    .plab-zone-list{display:grid;gap:4px;margin-top:12px}.plab-zone-row{display:grid!important;grid-template-columns:minmax(0,1fr) 90px 36px;gap:10px;align-items:center;background:none!important;border:1px solid transparent!important;border-radius:6px!important;padding:7px 8px!important;color:var(--plab-fg)!important;text-align:left;cursor:pointer}
    .plab-zone-row:hover,.plab-zone-row.is-active{border-color:var(--plab-line)!important;background:rgba(255,255,255,.03)!important}.plab-zone-row .bar{height:6px;background:#353943;border-radius:4px;overflow:hidden}.plab-zone-row .bar i{display:block;height:100%;border-radius:4px}.plab-zone-row .bar i.z-g{background:var(--plab-green)}.plab-zone-row .bar i.z-y{background:var(--plab-yellow)}.plab-zone-row .bar i.z-r{background:var(--plab-red)}.plab-zone-row .bar i.z-u{background:#4a4f5a}.plab-zone-row b{font:800 20px var(--plab-cond);text-align:right}
    .plab-body-flip{display:none;justify-content:center;gap:4px;margin-bottom:8px}.plab-body-flip button{border:1px solid var(--plab-line);background:transparent;color:var(--plab-fg);padding:6px 12px;border-radius:5px;font:800 12px var(--plab-cond);text-transform:uppercase;cursor:pointer}.plab-body-flip button[aria-pressed=true]{background:var(--plab-coral);color:#111;border-color:var(--plab-coral)}

    @media(max-width:760px){
        .plab-nav{position:fixed!important;left:0;right:0;bottom:0;top:auto!important;z-index:40;grid-template-columns:repeat(4,1fr);border-top:1px solid var(--plab-line)!important;border-bottom:0!important;padding:6px 4px 8px;background:#13161b!important}
        .plab-tab{min-height:52px!important;flex-direction:column!important;gap:2px!important;padding:6px 2px!important;font-size:12px!important;border-bottom:0!important;border-top:2px solid transparent!important}
        .plab-tab.is-active{border-top-color:var(--plab-coral)!important}.plab-tab svg{width:22px;height:22px}
        .plab-hero2{grid-template-columns:118px minmax(0,1fr);gap:14px;padding:14px}.plab-card-wrap{width:112px!important}
        .plab-hero-name{font-size:24px}.plab-hero-sub{font-size:12px}.plab-latest-chip{font-size:12px;padding:5px 8px}
        .plab-home-grid{grid-template-columns:1fr}.plab-stat-cards{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;margin:0 -12px;padding:0 12px 8px;scrollbar-width:none}.plab-stat-cards::-webkit-scrollbar{display:none}.plab-stat-card{flex:0 0 86%;scroll-snap-align:center}
        .plab-body-map{grid-template-columns:1fr}.plab-body-flip{display:flex}.plab-body-figures{grid-template-columns:1fr;max-width:230px;margin:0 auto}.plab-body-figures[data-side=front] .plab-body-svg[data-view=back],.plab-body-figures[data-side=back] .plab-body-svg[data-view=front]{display:none}
        .plab-zone-list{margin-top:10px}
        .plab-connect-head,.plab-connect-form{grid-template-columns:1fr}.plab-connect-state{justify-self:start}
    }
    @media(prefers-reduced-motion:reduce){.plab-body-zone.is-weak{animation:none;stroke:#fff;stroke-dasharray:4 3}}
</style>

<script>
(function () {
    var drawer=document.getElementById('plyrcard-action-drawer');
    if(!drawer||drawer.dataset.plyrLabReady==='1')return;
    drawer.dataset.plyrLabReady='1';

    var root=function(){return drawer.querySelector('[data-plab-root]')};
    var lab=null,loaded=false,loading=false,activeTab='home',selectedTrainerId=null,busy=false,bodySide='front',activeZone=null;

    function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]})}
    function csrf(){var m=document.querySelector('meta[name="csrf-token"]'),i=document.querySelector('input[name="_token"]');return m?m.content:(i?i.value:'')}
    function initials(name){return String(name||'').trim().split(/\s+/).filter(Boolean).slice(0,2).map(function(x){return x.charAt(0).toUpperCase()}).join('')||'PL'}
    function arr(v){return Array.isArray(v)?v:[]}
    function num(v){var n=Number(v);return v!==null&&v!==''&&Number.isFinite(n)?n:null}
    function val(v,dec,unit){var n=num(v);return n===null?'–':n.toFixed(dec)+(unit?' '+unit:'')}
    function fmtDate(v){if(!v)return'–';var d=new Date(v);return Number.isNaN(d.getTime())?String(v):d.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'})}
    function fmtDateTime(v){if(!v)return'Not scheduled';var d=new Date(v);return Number.isNaN(d.getTime())?String(v):d.toLocaleString(undefined,{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'})}
    function player(){return lab&&lab.player?lab.player:{}}
    function stats(){return lab&&lab.stats?lab.stats:{}}
    function jump(){return stats().jump||{}}
    function speed(){return stats().speed||{}}
    function booking(){return lab&&lab.booking?lab.booking:null}
    function trainer(){return booking()&&booking().trainer?booking().trainer:null}
    function positionText(){var p=player(),a=arr(p.position);return a.length?a.map(pretty).join(' / '):'Athlete'}
    function avatarHtml(person,cls){
        var u=person&&person.photo_url;
        return '<span class="'+(cls||'plab-avatar')+'">'+(u?'<img src="'+esc(u)+'" alt="" loading="lazy">':esc(initials(person&&person.name)))+'</span>';
    }
    function hasStats(){
        var j=jump(),s=speed();
        return [stats().overall_score,stats().pace_score,j.vertical_jump_cm,j.rsi,j.bounce_height_cm,j.ground_contact_ms,j.left_jump_cm,j.right_jump_cm,s.sprint_10m_seconds,s.sprint_20m_seconds,s.sprint_30m_seconds,s.top_speed_kph].some(function(v){return num(v)!==null});
    }
    async function api(url,options){
        options=options||{};var headers=Object.assign({'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf()},options.headers||{});
        if(options.body&&typeof options.body!=='string'){headers['Content-Type']='application/json';options.body=JSON.stringify(options.body)}
        var response=await fetch(url,Object.assign({credentials:'same-origin'},options,{headers:headers})),json={};
        try{json=await response.json()}catch(_){}
        if(!response.ok||json.success===false){var error=new Error(json.message||('Request failed ('+response.status+')'));error.payload=json;throw error}
        return json;
    }
    function toast(message,error){
        var el=drawer.querySelector('[data-lr-toast]');if(!el)return;
        el.textContent=message||'Saved.';el.style.background=error?'#991b1b':'#111827';el.classList.add('is-showing');
        setTimeout(function(){el.classList.remove('is-showing')},3200);
    }

    async function load(force){
        if((loaded&&!force)||loading)return;
        var box=root();if(!box)return;
        loading=true;
        if(!loaded)box.innerHTML='<div class="plab-loading"><i class="fa-solid fa-flask"></i>Loading your PLYRLab…</div>';
        try{
            var json=await api(drawer.dataset.plyrLabUrl);
            lab=json.data||{};loaded=true;
            if(!selectedTrainerId&&arr(lab.trainers).length)selectedTrainerId=String(lab.trainers[0].id);
            render();
        }catch(e){box.innerHTML='<div class="plab-loading"><i class="fa-solid fa-triangle-exclamation"></i>'+esc(e.message)+'</div>'}
        finally{loading=false}
    }

    function shell(content,status){
        var p=player();
        return '<div class="plab-top"><div><div class="plab-eyebrow">PLYRCARD · PERFORMANCE</div><h2 class="plab-title">THE <em>PLYR</em> LAB</h2></div><div class="plab-status">'+(status||('<b>'+esc(p.sport||'Performance')+'</b><br>Your personal testing dashboard'))+'</div></div>'+content;
    }

    function renderLocked(){
        var trainers=arr(lab.trainers),selected=trainers.find(function(t){return String(t.id)===String(selectedTrainerId)})||trainers[0]||null;
        var trainerCards=trainers.length?trainers.map(function(t){
            return '<button type="button" class="plab-trainer '+(selected&&String(selected.id)===String(t.id)?'is-selected':'')+'" data-plab-trainer="'+esc(t.id)+'">'+
                avatarHtml(t)+
                '<span><strong>'+esc(t.name)+'</strong><small>'+esc(t.headline||'PLYRLab Performance Trainer')+(t.location?' · '+esc(t.location):'')+'</small>'+
                '<span class="plab-specialties">'+arr(t.specialties).slice(0,4).map(function(x){return'<span class="plab-chip">'+esc(x)+'</span>'}).join('')+'</span></span></button>';
        }).join(''):'<div class="plab-empty" style="grid-column:1/-1">No PLYRLab trainers are available for booking yet.</div>';

        var form=selected?'<form class="plab-book-form" data-plab-book-form>'+
            '<div><div class="plab-section-title" style="margin:0">Book '+esc(selected.name)+'</div><div style="font-size:9px;color:var(--plab-muted);margin-top:3px">'+esc(selected.bio||('Choose a session time to unlock PLYRLab and connect your testing profile.'))+'</div></div>'+
            '<input type="hidden" name="trainer_id" value="'+esc(selected.id)+'">'+
            '<label>Session date & time<input class="plab-in" type="datetime-local" name="scheduled_for" required data-plab-datetime></label>'+
            '<label>Note for your trainer<textarea class="plab-in" name="notes" maxlength="1000" placeholder="What do you want to work on? Any testing goals?"></textarea></label>'+
            '<button class="plab-btn" type="submit" '+(busy?'disabled':'')+'><i class="fa-solid fa-calendar-check"></i> '+(busy?'Booking…':'Book Trainer & Unlock PLYRLab')+'</button>'+
            '<div style="font-size:8px;color:var(--plab-muted);line-height:1.45">Booking unlocks your PLYRLab. Your trainer creates your athlete profile in Plyomat, then gives you the Athlete ID to connect here.</div>'+
        '</form>':'';

        root().innerHTML=shell('<div class="plab-lock">'+
            '<div class="plab-lock-hero"><span class="plab-lock-icon"><i class="fa-solid fa-flask-vial"></i></span><div><h3>Your training, your numbers.</h3><p>'+esc((lab.access&&lab.access.message)||'Book a PLYRLab trainer to unlock your personal performance dashboard.')+' Your PLYRCARD profile supplies your basic athlete information automatically.</p></div></div>'+
            '<div><div class="plab-section-title">Choose a PLYRLab trainer</div><div class="plab-trainers">'+trainerCards+'</div></div>'+form+
        '</div>','<b>LOCKED</b><br>Book a trainer to activate');
        setDefaultDate();
    }

    function setDefaultDate(){
        var input=root()&&root().querySelector('[data-plab-datetime]');if(!input||input.value)return;
        var d=new Date(Date.now()+24*60*60*1000);d.setMinutes(0,0,0);
        var local=new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,16);
        input.value=local;input.min=new Date(Date.now()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,16);
    }



    function clamp(v,min,max){return Math.max(min,Math.min(max,v))}
    function legacyRating(v){
        var n=num(v);if(n===null)return null;
        return Math.round(40+clamp(n,0,100)*.59);
    }
    function pretty(v){
        var s=String(v==null?'':v).replace(/[_-]+/g,' ').replace(/\s+/g,' ').trim();
        return s.replace(/\b\w/g,function(ch){return ch.toUpperCase()});
    }
    function ratings(){return stats().ratings||{}}
    function rating(key){
        var r=ratings(),v=num(r[key]);
        if(v!==null)return Math.round(clamp(v,40,99));
        if(key==='overall')return legacyRating(stats().overall_score);
        if(key==='pace')return legacyRating(stats().pace_score);
        return null;
    }
    function metricRating(key){
        var v=num(ratings()[key]);return v===null?null:Math.round(clamp(v,40,99));
    }
    function ratingClass(v){return v===null?'is-empty':v>=75?'':v>=60?'is-mid':'is-low'}
    function toneKey(v){return v===null?'z-u':v>=75?'z-g':v>=60?'z-y':'z-r'}
    function teamInitials(v){
        return String(v||'').replace(/[^A-Za-z0-9\s]/g,' ').trim().split(/\s+/).filter(Boolean).slice(0,3).map(function(x){return x.charAt(0).toUpperCase()}).join('')||'PL';
    }
    function tierFor(v){return v===null?'NEW':v>=85?'ELITE':v>=75?'GOLD':v>=65?'SILVER':'BRONZE'}
    function frameFor(v){
        if(v===null)return['#9aa0a8','#5b616b','#33373e'];
        if(v>=75)return['#fff1c4','#e9b949','#a8771c'];
        if(v>=65)return['#f4f6f8','#b9c0c8','#6f7782'];
        return['#ffd9b8','#c98a55','#7c4a24'];
    }
    function profileText(){
        var profile=stats().jump_profile;
        if(profile)return pretty(profile);
        var ov=rating('overall');
        return ov===null?'Prospect':tierFor(ov);
    }
    function cardPositionText(){
        var pos=positionText();
        return pos&&pos!=='Athlete'?pos:profileText();
    }
    function shortDate(v){
        if(!v)return'';
        var d=new Date(v);return Number.isNaN(d.getTime())?String(v):d.toLocaleDateString(undefined,{month:'short',day:'numeric'});
    }



    function connectionPanel(){
        var b=booking();if(!b||b.plyomat_ready)return'';
        var state=String(b.status||'');

        if(state==='provisioning'||state==='pending'){
            return '<section class="plab-connect">'+
                '<div class="plab-connect-head"><div><span class="plab-connect-k">Plyomat setup</span><h3 class="plab-connect-title">Creating your athlete profile</h3>'+
                '<p class="plab-connect-copy">PLYRCard is creating your athlete directly in Plyomat using your booked trainer\'s API connection. You do not need to enter an Athlete ID unless an existing Plyomat profile is detected.</p></div>'+
                '<span class="plab-connect-state">Creating profile</span></div>'+
                '<div class="plab-connect-help">This uses your PLYRCard first name, last name, gender, height and weight when available, plus a PLYRCard external ID to help prevent duplicates.</div>'+
            '</section>';
        }

        if(state==='awaiting_integration'){
            return '<section class="plab-connect">'+
                '<div class="plab-connect-head"><div><span class="plab-connect-k">Plyomat connection</span><h3 class="plab-connect-title">Trainer API connection needs attention</h3>'+
                '<p class="plab-connect-copy">PLYRCard cannot create or read your Plyomat athlete until the trainer\'s API key is configured with Athlete Read + Athlete Write access.</p></div>'+
                '<span class="plab-connect-state">API attention</span></div>'+
            '</section>';
        }

        var t=trainer(),trainerName=t&&t.name?t.name:'your trainer';
        return '<section class="plab-connect">'+
            '<div class="plab-connect-head"><div><span class="plab-connect-k">Existing Plyomat profile</span><h3 class="plab-connect-title">Connect an existing Athlete ID</h3>'+
                '<p class="plab-connect-copy">Automatic creation could not complete or Plyomat may already have this athlete. Ask '+esc(trainerName)+' for the existing <b>Plyomat Athlete ID</b> and enter it below.</p></div>'+
                '<span class="plab-connect-state">Manual fallback</span></div>'+
            '<form class="plab-connect-form" data-plab-connect-form>'+
                '<label>Plyomat Athlete ID<input class="plab-in" name="athlete_id" type="text" maxlength="255" autocomplete="off" spellcheck="false" placeholder="Enter the existing Plyomat Athlete ID" required></label>'+
                '<button class="plab-btn" type="submit" '+(busy?'disabled':'')+'>'+(busy?'Verifying…':'Connect Profile')+'</button>'+
            '</form>'+
            '<div class="plab-connect-help">The ID is saved only after Plyomat confirms that your booked trainer can read that athlete record.</div>'+
        '</section>';
    }


    function cardHtml(){
        var p=player(),ov=rating('overall'),fr=frameFor(ov),parts=String(p.name||'PLAYER').trim().split(/\s+/),
            last=parts.length>1?parts.pop():parts[0],first=parts.length?parts.join(' '):'',
            nameLong=(String(first||'').length+String(last||'').length)>19,
            rows=[['PAC',rating('pace')],['JMP',metricRating('jump')],['BNC',metricRating('bounce')],['RSI',metricRating('rsi')],['QCK',metricRating('quick')],['BAL',metricRating('balance')]];
        return '<div class="plab-card-wrap"><div class="plab-pcard" style="--f1:'+fr[0]+';--f2:'+fr[1]+';--f3:'+fr[2]+'">'+
            '<div class="plab-pc-frame"><div class="plab-pc-in">'+
                '<div class="plab-pc-art" aria-hidden="true"></div>'+
                '<div class="plab-pc-photo">'+(p.photo_url?'<img src="'+esc(p.photo_url)+'" alt="">':'<svg viewBox="0 0 120 150" aria-hidden="true"><circle cx="60" cy="38" r="24" fill="currentColor"></circle><path d="M21 142c2-35 17-58 39-58s37 23 39 58H21z" fill="currentColor"></path><path d="M34 91c8-10 17-15 26-15s18 5 26 15l-8 10c-6-7-12-10-18-10s-12 3-18 10l-8-10z" fill="currentColor" opacity=".65"></path></svg>')+'</div>'+
                '<div class="plab-pc-top"><div class="plab-pc-ovr"><small>OVR</small>'+(ov===null?'–':ov)+'</div>'+
                    '<div class="plab-pc-profile">'+esc(cardPositionText())+'</div>'+
                    '<div class="plab-pc-crest"><b>'+esc(teamInitials(p.team))+'</b><span>'+esc(tierFor(ov))+'</span></div></div>'+
                '<div class="plab-pc-name '+(nameLong?'is-long':'')+'">'+(first?'<span class="plab-pc-first">'+esc(first)+'</span>':'')+'<span class="plab-pc-last">'+esc(last||'PLAYER')+'</span></div>'+
                '<div class="plab-pc-stats">'+
                    '<div class="plab-pc-col">'+rows.slice(0,3).map(function(x){return'<div><b>'+(x[1]===null?'–':x[1])+'</b><span>'+x[0]+'</span></div>'}).join('')+'</div>'+
                    '<i class="plab-pc-div"></i>'+
                    '<div class="plab-pc-col">'+rows.slice(3).map(function(x){return'<div><b>'+(x[1]===null?'–':x[1])+'</b><span>'+x[0]+'</span></div>'}).join('')+'</div>'+
                '</div><div class="plab-pc-brand">PLYRCARD</div>'+
            '</div></div></div></div>';
    }

    function notice(){
        var b=booking();if(!b||b.status==='active'||b.status==='awaiting_athlete_id')return'';
        var text=(lab.access&&lab.access.message)||'Your Plyomat connection needs attention.';
        if(b.status==='awaiting_integration')text='Your Athlete ID is saved, but your trainer\'s Plyomat Athlete Read connection needs attention.';
        return '<div class="plab-notice"><i class="fa-solid fa-circle-info"></i><span>'+esc(text)+'</span></div>';
    }



    function homeView(){
        var p=player(),b=booking(),t=trainer(),j=jump(),s=speed(),ov=rating('overall'),latest=stats().latest_test_at,
            zones=bodyZones(),rated=zones.filter(function(z){return z.rating!==null}).sort(function(a,b){return a.rating-b.rating}),weak=rated[0]||null;
        var measured=[
            ['Jump',val(j.vertical_jump_cm,1,'cm')],['Pop (RSI)',val(j.rsi,2,'')],['Bounce',val(j.bounce_height_cm,1,'cm')],
            ['Quick Feet',val(j.ground_contact_ms,0,'ms')],['10 m',val(s.sprint_10m_seconds,2,'s')],['Top Speed',val(s.top_speed_kph,1,'km/h')]
        ].filter(function(x){return x[1]!=='–'});
        return notice()+'<div class="plab-match-home">'+
            '<section class="plab-hero2"><div>'+cardHtml()+'</div><div class="plab-hero-main">'+
                '<span class="plab-hero-k">'+(ov===null?'NEW PLAYER':esc(profileText())+' · '+esc(tierFor(ov)))+'</span>'+
                '<h3 class="plab-hero-name">'+esc(p.name||'Player')+'</h3>'+
                '<div class="plab-hero-sub">'+esc(p.team||'Independent')+' · '+esc(p.sport||'Athlete')+' · '+esc(positionText())+'</div>'+
                (latest?'<button type="button" class="plab-latest-chip" data-plab-tab="stats">★ Latest results · '+esc(shortDate(latest))+' <span aria-hidden="true">›</span></button>':'')+
            '</div></section>'+
            '<div class="plab-home-grid">'+
                '<div class="plab-match-block"><span class="plab-block-k">Last combine'+(latest?' · '+esc(shortDate(latest)):'')+'</span>'+
                    '<b class="plab-block-t">'+(measured.length?'Your latest numbers':'No tests yet')+'</b>'+
                    '<p class="plab-block-p">'+(measured.length?'Exact Plyomat measurements from your latest available assessment.':'Your first jump or speed test will fill this in.')+'</p>'+
                    (measured.length?'<div class="plab-measured-row">'+measured.map(function(x){return'<span class="plab-measured-chip">'+esc(x[0])+' <b>'+esc(x[1])+'</b></span>'}).join('')+'</div>':'')+
                    (measured.length?'<button type="button" class="plab-latest-chip" style="justify-self:start;margin-top:3px" data-plab-tab="stats">See my stats</button>':'')+
                '</div>'+
                '<button type="button" class="plab-match-block is-button" data-plab-tab="body"><span class="plab-block-k">Next level-up</span>'+
                    '<b class="plab-block-t">'+(weak?esc(weak.name)+' · '+weak.rating:'Get tested')+'</b>'+
                    '<p class="plab-block-p">'+(weak?'<span style="font-family:var(--plab-mono);color:var(--plab-ice)">'+esc(weak.measure)+'</span>. Open the body map to see the zone.':'Your body map lights up after your first test.')+'</p>'+
                '</button>'+
            '</div>'+
            '<div class="plab-coachbar"><span class="plab-block-k">Your PLYRLab trainer</span><strong>'+esc(t?t.name:'Trainer pending')+'</strong><small>'+(b?esc(fmtDateTime(b.scheduled_for)):'')+(t&&t.headline?' · '+esc(t.headline):'')+'</small></div>'+
        '</div>';
    }

    function info(label,value){return'<div class="plab-info"><small>'+esc(label)+'</small><strong>'+esc(value)+'</strong></div>'}

    function metric(label,value){return'<div class="plab-metric"><span>'+esc(label)+'</span><b>'+esc(value)+'</b></div>'}

    function statCard(label,key,measured,unit,benchmark){
        var r=metricRating(key),display=measured==='–'?'–':measured;
        return '<article class="plab-stat-card"><div class="plab-stat-head">'+
            '<span class="plab-rating-shield '+ratingClass(r)+'"><b>'+(r===null?'–':r)+'</b><small>RATING</small></span>'+
            '<div><h3 class="plab-stat-name">'+esc(label)+'</h3><span class="plab-stat-sub">Normalized vs your PLYRLab cohort</span></div></div>'+
            '<div class="plab-measure"><small>Measured · exact</small><b>'+esc(display)+(display==='–'||!unit?'':' <em>'+esc(unit)+'</em>')+'</b></div>'+
            (benchmark?'<div class="plab-benchmark">Benchmark · '+esc(benchmark)+'</div>':'')+
        '</article>';
    }
    function statsView(){
        var st=stats(),j=jump(),s=speed(),sex=String(player().gender||'').toLowerCase()==='female'?'women':'men',bench=D1[sex],ov=rating('overall'),pace=rating('pace');
        if(!hasStats())return notice()+'<div class="plab-empty"><b style="display:block;color:var(--plab-fg);font-size:13px;text-transform:uppercase;margin-bottom:5px">No Plyomat results yet</b>Your trainer is booked and PLYRLab is unlocked. Once a jump or speed assessment reaches your connected Plyomat profile, it will appear here.</div>';
        var cards=[
            statCard('Vertical Jump','jump',val(j.vertical_jump_cm,1,''),'cm',bench.svj+' cm D1 target'),
            statCard('Reactive Strength','rsi',val(j.rsi,2,''),'','RSI is shown as an exact measured value'),
            statCard('Bounce','bounce',val(j.bounce_height_cm,1,''),'cm','Higher is better'),
            statCard('Quick Feet','quick',val(j.ground_contact_ms,0,''),'ms','Lower ground contact is better'),
            statCard('Balance','balance',val(j.balance_gap_percent,1,''),'%','Lower left/right gap is better'),
            statCard('10 m Sprint','t10',val(s.sprint_10m_seconds,2,''),'s',bench.t10.toFixed(2)+' s D1 target'),
            statCard('30 m Sprint','t30',val(s.sprint_30m_seconds,2,''),'s',bench.t30.toFixed(2)+' s D1 target'),
            statCard('Top Speed','vmax',val(s.top_speed_kph,1,''),'km/h',bench.vmax.toFixed(1)+' km/h D1 target')
        ];
        return notice()+'<div class="plab-ratings-legend"><span><i class="r"></i>Rating · normalized 40–99</span><span><i class="m"></i>Measured · exact Plyomat value</span><span><i class="b"></i>Benchmark · D1 reference</span></div>'+
            '<div class="plab-kpis">'+
                kpi('Overall',ov===null?'–':ov,'Normalized rating')+
                kpi('Pace',pace===null?'–':pace,'Normalized rating')+
                kpi('Jump profile',st.jump_profile||'–','Latest classification')+
                kpi('Last test',fmtDate(st.latest_test_at),'Plyomat')+
            '</div><div class="plab-stat-cards">'+cards.join('')+'</div>';
    }

    function kpi(label,value,sub){return'<div class="plab-kpi"><small>'+esc(label)+'</small><b>'+esc(value)+'</b><em>'+esc(sub)+'</em></div>'}


    function bodyZones(){
        var j=jump(),s=speed(),jumpR=metricRating('jump'),bounceR=metricRating('bounce'),quickR=metricRating('quick'),
            rsiR=metricRating('rsi'),t10R=metricRating('t10'),vmaxR=metricRating('vmax'),paceR=rating('pace');
        return [
            {id:'hip',view:'front',part:'hip',side:null,name:'Hip drive',rating:t10R!==null?t10R:paceR,measure:'10 m '+val(s.sprint_10m_seconds,2,'s'),copy:'Acceleration and knee drive through the first steps.'},
            {id:'quadR',view:'front',part:'thigh',side:'R',name:'Explosive power · Right',rating:jumpR,measure:'Right '+val(j.right_jump_cm,1,'cm'),copy:'Right-side contribution to your explosive jump profile.'},
            {id:'quadL',view:'front',part:'thigh',side:'L',name:'Explosive power · Left',rating:jumpR,measure:'Left '+val(j.left_jump_cm,1,'cm'),copy:'Left-side contribution to your explosive jump profile.'},
            {id:'shinR',view:'front',part:'shin',side:'R',name:'Quick feet · Right',rating:quickR,measure:'Ground contact '+val(j.ground_contact_ms,0,'ms'),copy:'How quickly you get off the ground during reactive work.'},
            {id:'shinL',view:'front',part:'shin',side:'L',name:'Quick feet · Left',rating:quickR,measure:'Ground contact '+val(j.ground_contact_ms,0,'ms'),copy:'How quickly you get off the ground during reactive work.'},
            {id:'gluteR',view:'back',part:'glute',side:'R',name:'Glutes & power · Right',rating:bounceR!==null?bounceR:jumpR,measure:'Bounce '+val(j.bounce_height_cm,1,'cm'),copy:'Posterior-chain contribution to repeated power.'},
            {id:'gluteL',view:'back',part:'glute',side:'L',name:'Glutes & power · Left',rating:bounceR!==null?bounceR:jumpR,measure:'Bounce '+val(j.bounce_height_cm,1,'cm'),copy:'Posterior-chain contribution to repeated power.'},
            {id:'hamR',view:'back',part:'thigh',side:'R',name:'Hamstrings & speed · Right',rating:vmaxR!==null?vmaxR:paceR,measure:'Top speed '+val(s.top_speed_kph,1,'km/h'),copy:'Your engine as you reach top running speed.'},
            {id:'hamL',view:'back',part:'thigh',side:'L',name:'Hamstrings & speed · Left',rating:vmaxR!==null?vmaxR:paceR,measure:'Top speed '+val(s.top_speed_kph,1,'km/h'),copy:'Your engine as you reach top running speed.'},
            {id:'calfR',view:'back',part:'calf',side:'R',name:'Calves & reactivity · Right',rating:rsiR,measure:'RSI '+val(j.rsi,2,''),copy:'The spring in your step. Higher RSI means more reactive output.'},
            {id:'calfL',view:'back',part:'calf',side:'L',name:'Calves & reactivity · Left',rating:rsiR,measure:'RSI '+val(j.rsi,2,''),copy:'The spring in your step. Higher RSI means more reactive output.'}
        ];
    }
    function bodyShape(view,zones,weak,selected){
        var LEG={
            thigh:'M62 250 C60 290 66 330 74 352 L96 352 C99 330 101 292 100 262 Z',
            shin:'M74 362 C70 390 74 420 79 440 L93 440 C96 420 98 390 96 362 Z',
            calf:'M72 362 C64 384 70 416 79 440 L93 440 C98 414 101 386 96 362 Z',
            glute:'M60 214 L100 214 L100 262 Q80 282 57 260 Z'
        };
        var viewerLeft=view==='front'?'R':'L';
        var paths=zones.filter(function(z){return z.view===view}).map(function(z){
            var mirror=z.side&&z.side!==viewerLeft,d=z.part==='hip'?'M64 226 L136 226 L140 252 Q100 268 60 252 Z':LEG[z.part],
                cls='plab-body-zone '+toneKey(z.rating)+(weak&&weak.id===z.id?' is-weak':'')+(selected&&selected.id===z.id?' is-active':'');
            return '<path class="'+cls+'" d="'+d+'"'+(mirror?' transform="translate(200 0) scale(-1 1)"':'')+' data-plab-zone="'+esc(z.id)+'" tabindex="0" role="button" aria-label="'+esc(z.name)+': '+(z.rating===null?'not rated':z.rating)+'"></path>';
        }).join('');
        var neutral='<g class="plab-body-neutral">'+
            '<ellipse cx="100" cy="44" rx="23" ry="27"></ellipse><rect x="92" y="68" width="16" height="12" rx="4"></rect>'+
            '<path d="M56 92 Q56 80 70 78 L130 78 Q144 80 144 92 L138 200 Q136 222 130 226 L70 226 Q64 222 62 200 Z"></path>'+
            '<path d="M56 92 Q44 96 42 112 L30 200 Q28 240 32 286 L44 288 Q46 244 50 206 L60 128 Z"></path>'+
            '<path d="M56 92 Q44 96 42 112 L30 200 Q28 240 32 286 L44 288 Q46 244 50 206 L60 128 Z" transform="translate(200 0) scale(-1 1)"></path>'+
            '<circle cx="38" cy="296" r="7"></circle><circle cx="162" cy="296" r="7"></circle>'+
            '<path d="M77 442 L95 442 L99 458 L72 458 Z"></path><path d="M77 442 L95 442 L99 458 L72 458 Z" transform="translate(200 0) scale(-1 1)"></path>'+
        '</g>';
        var left=viewerLeft,right=viewerLeft==='L'?'R':'L';
        return '<svg class="plab-body-svg" data-view="'+view+'" viewBox="0 0 200 506" role="group" aria-label="'+(view==='front'?'Front':'Back')+' athlete performance map">'+
            neutral+paths+
            '<g class="plab-body-labels"><text x="86" y="476" text-anchor="middle">'+left+'</text><text x="114" y="476" text-anchor="middle">'+right+'</text><text class="view" x="100" y="498" text-anchor="middle">'+(view==='front'?'FRONT':'BACK')+'</text></g>'+
        '</svg>';
    }
    function bodyView(){
        var zones=bodyZones(),rated=zones.filter(function(z){return z.rating!==null}).sort(function(a,b){return a.rating-b.rating}),
            weak=rated.length?rated[0]:null,selected=zones.find(function(z){return z.id===activeZone})||weak||zones[0],
            list=zones.slice().sort(function(a,b){return (a.rating===null?999:a.rating)-(b.rating===null?999:b.rating)});
        return notice()+'<div class="plab-body-map">'+
            '<div>'+
                '<div class="plab-body-flip" role="group" aria-label="Body view"><button type="button" data-plab-bview="front" aria-pressed="'+(bodySide==='front')+'">Front</button><button type="button" data-plab-bview="back" aria-pressed="'+(bodySide==='back')+'">Back</button></div>'+
                '<div class="plab-body-figures" data-side="'+bodySide+'">'+bodyShape('front',zones,weak,selected)+bodyShape('back',zones,weak,selected)+'</div>'+
                '<div class="plab-body-legend"><span class="g">75+ strength</span><span class="y">60–74 solid</span><span class="r">Under 60 work on it</span><span class="u">Needs a test</span></div>'+
            '</div>'+
            '<div class="plab-body-detail">'+
                (weak?'<div class="plab-body-boss"><span class="plab-block-k">Weak spot · your next level-up</span><b>'+esc(weak.name)+'</b><small>Rated '+weak.rating+'. Train it and watch this zone move at your next test.</small></div>':'')+
                '<div class="plab-zone-detail"><div class="plab-zone-head"><div><small>'+(selected.rating===null?'Not tested':selected.rating>=75?'Strength':selected.rating>=60?'Solid':'Work on it')+' · estimate</small><h3>'+esc(selected.name)+'</h3></div><b class="plab-zone-rating '+toneKey(selected.rating)+'">'+(selected.rating===null?'–':selected.rating)+'</b></div>'+
                    '<p class="plab-zone-measure">'+esc(selected.measure)+'</p><p class="plab-zone-copy">'+esc(selected.copy)+'</p>'+
                '</div>'+
                '<div class="plab-zone-list">'+list.map(function(z){
                    var width=z.rating===null?0:Math.max(4,(z.rating-40)/59*100);
                    return '<button type="button" class="plab-zone-row '+(selected.id===z.id?'is-active':'')+'" data-plab-zone="'+esc(z.id)+'"><span>'+esc(z.name)+'</span><span class="bar"><i class="'+toneKey(z.rating)+'" style="width:'+width+'%"></i></span><b class="'+toneKey(z.rating)+'">'+(z.rating===null?'–':z.rating)+'</b></button>';
                }).join('')+'</div>'+
                '<p style="margin:10px 0 0;color:var(--plab-muted);font-size:11px">Zones are a training guide built from the available Plyomat jump and speed tests, not a medical assessment.</p>'+
            '</div>'+
        '</div>';
    }


    var D1={
        men:{svj:36,t10:1.52,t20:2.76,t30:3.91,vmax:31.7},
        women:{svj:28,t10:2.00,t20:3.39,t30:4.71,vmax:26.9}
    };
    var D1M=[
        {k:'svj',n:'Vertical jump',u:'cm',dp:1,low:false},
        {k:'t10',n:'10 m sprint',u:'s',dp:2,low:true},
        {k:'t20',n:'20 m sprint',u:'s',dp:2,low:true},
        {k:'t30',n:'30 m sprint',u:'s',dp:2,low:true},
        {k:'vmax',n:'Top speed',u:'km/h',dp:1,low:false}
    ];
    var D1T=[[0,'Rookie'],[60,'Rising'],[75,'Varsity'],[85,'College ready'],[95,'D1 level']];
    function d1View(){
        var p=player(),j=jump(),s=speed(),sex=String(p.gender||'').toLowerCase()==='female'?'women':'men',bench=D1[sex];
        var vals={svj:num(j.vertical_jump_cm),t10:num(s.sprint_10m_seconds),t20:num(s.sprint_20m_seconds),t30:num(s.sprint_30m_seconds),vmax:num(s.top_speed_kph)};
        var rows=D1M.map(function(m){var mine=vals[m.k],target=bench[m.k],pct=mine===null?null:Math.min(120,Math.round((m.low?target/mine:mine/target)*100));return Object.assign({},m,{mine:mine,target:target,pct:pct})});
        var have=rows.filter(function(r){return r.pct!==null}),score=have.length?Math.round(have.reduce(function(t,r){return t+Math.min(110,r.pct)},0)/have.length):null,tier=D1T[0];
        D1T.forEach(function(x){if(score!==null&&score>=x[0])tier=x});
        if(!have.length)return notice()+'<div class="plab-empty">After your first jump or speed test, your D1 comparison will appear here.</div>';
        return notice()+'<div class="plab-d1-top"><div class="plab-d1-score"><small>D1 Score</small><b>'+(score===null?'–':score)+'<em>%</em></b><span>'+esc(tier[1])+'</span></div>'+
            '<div class="plab-d1-copy"><h3>Compared with D1 '+(sex==='women'?'women':'men')+'</h3><p>This comparison uses the benchmark model supplied with the PLYRLab reference. Your player gender is pulled from your PLYRCARD profile.</p></div></div>'+
            '<div class="plab-d1rows">'+rows.map(function(r){
                var width=r.pct===null?0:Math.min(100,r.pct/120*100),you=r.mine===null?'Not tested':r.mine.toFixed(r.dp)+' '+r.u,target=r.target.toFixed(r.dp)+' '+r.u;
                return '<div class="plab-d1row"><div class="plab-d1row-top"><b>'+esc(r.n)+'</b><span>'+(r.pct===null?'Not tested yet':r.pct+'% of D1')+'</span></div>'+
                    '<div class="plab-d1bar"><i style="width:'+width+'%"></i><em></em></div>'+
                    '<div class="plab-d1values"><span>You <b>'+esc(you)+'</b></span><span class="target">D1 target <b>'+esc(target)+'</b></span></div></div>';
            }).join('')+'</div>';
    }

    function renderUnlocked(){
        var p=player(),b=booking(),status=b?b.status_label:'Unlocked';
        var tabContent=activeTab==='stats'?statsView():activeTab==='body'?bodyView():activeTab==='d1'?d1View():homeView();
        var syncControl=b&&b.plyomat_ready
            ? '<div class="plab-sync"><button type="button" class="plab-btn is-ghost" data-plab-refresh '+(busy?'disabled':'')+'><i class="fa-solid fa-rotate"></i> '+(busy?'Syncing…':'Refresh Stats')+'</button><small>'+(b.last_synced_at?'Last sync '+esc(fmtDateTime(b.last_synced_at)):esc(status))+'</small></div>'
            : '<div class="plab-sync"><small>'+(b&&b.status==='provisioning'?'Creating your Plyomat athlete profile…':b&&b.status==='awaiting_integration'?'Trainer API connection needs attention':'Manual Athlete ID available if an existing profile already exists')+'</small></div>';
        root().innerHTML=shell(
            '<div class="plab-athlete-head">'+
                avatarHtml(p)+
                '<div><div class="plab-athlete-name">'+esc(p.name||'Player')+'</div><div class="plab-athlete-sub">'+esc(p.team||'Independent')+' · '+esc(p.sport||'Athlete')+'</div></div>'+
                syncControl+
            '</div>'+
            '<nav class="plab-nav" aria-label="PLYRLab player views">'+
                tabButton('home','Home')+tabButton('stats','Stats')+tabButton('body','Body Map')+tabButton('d1','vs D1')+
            '</nav>'+
            '<div class="plab-body">'+connectionPanel()+tabContent+'</div>',
            '<b>'+esc(status)+'</b><br>'+esc((b&&b.trainer&&b.trainer.name)||'PLYRLab')
        );
    }


    var PLAB_ICONS={
        home:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11l9-7 9 7v9h-6v-6H9v6H3z"></path></svg>',
        stats:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"></path></svg>',
        body:'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="2.5"></circle><path d="M12 8v7m0 0l-3 6m3-6l3 6M6 10h12"></path></svg>',
        d1:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4 6.7 19.4l1.2-6L3.4 9.3l6-.7z"></path></svg>'
    };
    function tabButton(k,label){
        return '<button type="button" class="plab-tab '+(activeTab===k?'is-active':'')+'" data-plab-tab="'+k+'" aria-selected="'+(activeTab===k)+'">'+(PLAB_ICONS[k]||'')+'<span class="plab-tab-label">'+label+'</span></button>';
    }


    function render(){
        if(!root()||!lab)return;
        if(lab.access&&lab.access.unlocked)renderUnlocked();else renderLocked();
    }

    async function submitBooking(form){
        if(busy)return;
        var fd=new FormData(form),payload={trainer_id:Number(fd.get('trainer_id')),scheduled_for:fd.get('scheduled_for'),notes:String(fd.get('notes')||'')};
        busy=true;render();
        try{
            var json=await api(drawer.dataset.plyrLabBookUrl,{method:'POST',body:payload});
            lab=json.data||lab;loaded=true;activeTab='home';toast(json.message||'Trainer booked.');render();
        }catch(e){if(e.payload&&e.payload.data){lab=e.payload.data;loaded=true}toast(e.message,true);render()}
        finally{busy=false;render()}
    }

    async function connectAthlete(form){
        if(busy)return;
        var fd=new FormData(form),athleteId=String(fd.get('athlete_id')||'').trim();
        if(!athleteId){toast('Enter the Plyomat Athlete ID provided by your trainer.',true);return}
        if(!drawer.dataset.plyrLabConnectUrl){toast('The Plyomat connection endpoint is unavailable.',true);return}
        busy=true;render();
        try{
            var json=await api(drawer.dataset.plyrLabConnectUrl,{method:'POST',body:{athlete_id:athleteId}});
            lab=json.data||lab;loaded=true;activeTab='home';toast(json.message||'Plyomat profile connected.');
        }catch(e){
            if(e.payload&&e.payload.data){lab=e.payload.data;loaded=true}
            toast(e.message,true);
        }finally{busy=false;render()}
    }

    async function refreshStats(){
        if(busy)return;busy=true;render();
        try{var json=await api(drawer.dataset.plyrLabRefreshUrl,{method:'POST',body:{}});lab=json.data||lab;loaded=true;toast(json.message||'Stats refreshed.')}
        catch(e){if(e.payload&&e.payload.data){lab=e.payload.data;loaded=true}toast(e.message,true)}
        finally{busy=false;render()}
    }

    document.addEventListener('plyrcard:locker-view',function(event){if(event.detail&&event.detail.view==='plyrlab')load(false)});
    document.addEventListener('click',function(event){
        var nav=event.target.closest('[data-lr-nav="plyrlab"]');if(nav&&drawer.contains(nav))setTimeout(function(){load(false)},0);
        var t=event.target.closest('[data-plab-trainer]');if(t&&drawer.contains(t)){selectedTrainerId=String(t.dataset.plabTrainer||'');render();return}
        var tab=event.target.closest('[data-plab-tab]');if(tab&&drawer.contains(tab)){activeTab=tab.dataset.plabTab||'home';render();return}
        var bv=event.target.closest('[data-plab-bview]');if(bv&&drawer.contains(bv)){bodySide=bv.dataset.plabBview||'front';render();return}
        var bz=event.target.closest('[data-plab-zone]');if(bz&&drawer.contains(bz)){activeZone=bz.dataset.plabZone||null;render();return}
        var refresh=event.target.closest('[data-plab-refresh]');if(refresh&&drawer.contains(refresh)){refreshStats();return}
    },true);
    document.addEventListener('submit',function(event){
        var connectForm=event.target.closest('[data-plab-connect-form]');if(connectForm&&drawer.contains(connectForm)){event.preventDefault();connectAthlete(connectForm);return}
        var form=event.target.closest('[data-plab-book-form]');if(form&&drawer.contains(form)){event.preventDefault();submitBooking(form)}
    },true);
})();
</script>
