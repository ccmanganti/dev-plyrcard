<x-filament-panels::page>
    <div class="rc-livewire-root">
    <style>
        :root {
            --rc-accent: #ff6338;
            --rc-accent-soft: rgba(255, 99, 56, .11);
            --rc-border: rgb(229 231 235);
            --rc-muted: rgb(107 114 128);
            --rc-surface: #ffffff;
            --rc-soft: rgb(249 250 251);
            --rc-text: rgb(17 24 39);
        }

        .dark {
            --rc-border: rgb(63 63 70);
            --rc-muted: rgb(161 161 170);
            --rc-surface: rgb(24 24 27);
            --rc-soft: rgb(39 39 42);
            --rc-text: rgb(244 244 245);
        }

        [x-cloak] { display: none !important; }

        .rc-wrap {
            display: grid;
            gap: 1rem;
            color: var(--rc-text);
        }

        .rc-subtle {
            color: var(--rc-muted);
            font-size: .8125rem;
            line-height: 1.35;
        }

        .rc-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .875rem;
        }

        .rc-title {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -.02em;
            line-height: 1.2;
        }

        .rc-grid {
            display: grid;
            gap: .875rem;
        }

        .rc-stats {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .rc-card {
            border: 1px solid var(--rc-border);
            background: var(--rc-surface);
            border-radius: .875rem;
            padding: .875rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .rc-card.is-flat {
            box-shadow: none;
        }

        .rc-stat-number {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -.025em;
            line-height: 1;
        }

        .rc-toolbar {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .rc-input,
        .rc-select,
        .rc-textarea {
            width: auto;
            border: 1px solid var(--rc-border);
            border-radius: .625rem;
            background: var(--rc-surface);
            color: var(--rc-text);
            padding: .5rem .65rem;
            font-size: .8125rem;
            min-height: 2.125rem;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .rc-input:focus,
        .rc-select:focus,
        .rc-textarea:focus {
            border-color: var(--rc-accent);
            box-shadow: 0 0 0 3px var(--rc-accent-soft);
        }

        .rc-textarea {
            width: 100%;
            line-height: 1.45;
        }

        .rc-rich-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .375rem;
            margin-top: .5rem;
        }

        .rc-rich-editor {
            width: 100%;
            min-height: 12rem;
            border: 1px solid var(--rc-border);
            border-radius: .75rem;
            background: var(--rc-surface);
            color: var(--rc-text);
            padding: .75rem;
            font-size: .875rem;
            line-height: 1.55;
            outline: none;
            margin-top: .5rem;
        }

        .rc-rich-editor:focus {
            border-color: var(--rc-accent);
            box-shadow: 0 0 0 3px var(--rc-accent-soft);
        }

        .rc-rich-editor:empty:before {
            content: attr(data-placeholder);
            color: var(--rc-muted);
        }

        .rc-mini-list {
            display: grid;
            gap: .5rem;
            max-height: 22rem;
            overflow: auto;
        }

        .rc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            border: 1px solid var(--rc-border);
            border-radius: .625rem;
            padding: .475rem .7rem;
            min-height: 2.125rem;
            font-size: .7875rem;
            font-weight: 650;
            background: var(--rc-surface);
            color: var(--rc-text);
            transition: background .15s ease, border-color .15s ease, transform .15s ease;
        }

        .rc-btn:hover {
            background: var(--rc-soft);
        }

        .rc-btn:active {
            transform: translateY(1px);
        }

        .rc-btn-primary {
            background: var(--rc-accent);
            border-color: var(--rc-accent);
            color: white;
        }

        .rc-btn-primary:hover {
            background: #f0522b;
            border-color: #f0522b;
        }

        .rc-btn[disabled] {
            opacity: .55;
            cursor: not-allowed;
        }

        .rc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .875rem;
            padding: .7rem 0;
            border-top: 1px solid var(--rc-border);
        }

        .rc-row:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .rc-row:last-child {
            padding-bottom: 0;
        }

        .rc-row-title {
            font-weight: 650;
            font-size: .875rem;
        }



        .rc-coach-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .75rem;
            align-items: center;
            padding: .72rem .78rem;
            border: 1px solid var(--rc-border);
            background: var(--rc-surface);
            border-radius: .78rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .035);
        }

        .rc-card > .rc-coach-row + .rc-coach-row,
        .rc-drawer-panel > .rc-coach-row + .rc-coach-row {
            margin-top: .5rem;
        }

        .rc-coach-main {
            display: grid;
            grid-template-columns: 2.35rem minmax(0, 1fr);
            gap: .75rem;
            align-items: center;
            min-width: 0;
        }

        .rc-coach-avatar {
            width: 2.35rem;
            height: 2.35rem;
            border-radius: .75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--rc-accent-soft);
            color: var(--rc-accent);
            font-weight: 800;
            font-size: .9rem;
        }

        .rc-coach-copy {
            min-width: 0;
            display: grid;
            gap: .28rem;
        }

        .rc-coach-heading {
            display: flex;
            align-items: center;
            gap: .5rem;
            min-width: 0;
        }

        .rc-coach-heading h3 {
            margin: 0;
            font-size: .93rem;
            line-height: 1.25;
            font-weight: 750;
            letter-spacing: -.015em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rc-coach-badges {
            display: inline-flex;
            gap: .3rem;
            flex-wrap: wrap;
            flex: 0 0 auto;
        }

        .rc-coach-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem .6rem;
            color: var(--rc-muted);
            font-size: .78rem;
            line-height: 1.35;
        }

        .rc-coach-meta span {
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rc-coach-meta span:not(:last-child)::after {
            content: "";
            display: inline-block;
            width: .22rem;
            height: .22rem;
            margin-left: .6rem;
            border-radius: 999px;
            background: currentColor;
            opacity: .45;
            vertical-align: middle;
        }

        .rc-coach-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: .4rem;
            flex-wrap: nowrap;
            max-width: 14rem;
        }

        .rc-coach-list-actions {
            display: none;
        }

        .rc-action-menu {
            position: relative;
            display: inline-flex;
            flex: 0 0 auto;
        }

        .rc-action-trigger {
            font-weight: 900;
            letter-spacing: .08em;
        }


        .rc-school-list-picker {
            position: relative;
            display: inline-flex;
            flex: 0 0 auto;
        }

        .rc-school-list-trigger {
            min-width: 4.25rem;
            padding-inline: .55rem;
            gap: .3rem;
        }

        .rc-school-list-menu {
            position: absolute;
            z-index: 45;
            right: 0;
            bottom: calc(100% + .35rem);
            width: max-content;
            min-width: 8.5rem;
            max-width: 12rem;
            max-height: 10.5rem;
            overflow: auto;
            border: 1px solid var(--rc-border);
            border-radius: .65rem;
            background: var(--rc-surface);
            box-shadow: 0 12px 28px rgba(15, 23, 42, .16);
            padding: .25rem;
        }

        .rc-school-list-option {
            width: 100%;
            border: 0;
            background: transparent;
            color: var(--rc-text);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            text-align: left;
            padding: .38rem .48rem;
            border-radius: .45rem;
            font-size: .72rem;
            font-weight: 700;
            line-height: 1.2;
            cursor: pointer;
        }

        .rc-school-list-option:hover,
        .rc-school-list-option.is-active {
            background: var(--rc-accent-soft);
            color: var(--rc-accent);
        }

        .rc-school-list-check {
            font-size: .72rem;
            font-weight: 900;
        }

        .rc-school-list-empty {
            color: var(--rc-muted);
            font-size: .72rem;
            padding: .38rem .48rem;
        }

        .rc-menu-panel {
            position: absolute;
            z-index: 40;
            top: calc(100% + .45rem);
            right: 0;
            width: min(17rem, 80vw);
            max-height: 22rem;
            overflow: auto;
            border: 1px solid var(--rc-border);
            border-radius: .85rem;
            background: var(--rc-surface);
            box-shadow: 0 18px 45px rgba(15, 23, 42, .18);
            padding: .35rem;
        }

        .rc-menu-item {
            width: 100%;
            border: 0;
            background: transparent;
            color: var(--rc-text);
            padding: .58rem .65rem;
            border-radius: .58rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .65rem;
            text-align: left;
            font-size: .78rem;
            font-weight: 650;
            cursor: pointer;
        }

        .rc-menu-item:hover {
            background: var(--rc-soft);
        }

        .rc-menu-label {
            padding: .65rem .65rem .32rem;
            color: var(--rc-muted);
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .rc-btn-compact {
            min-height: 1.95rem;
            padding: .36rem .55rem;
            font-size: .72rem;
        }


        .rc-pill {
            display: inline-flex;
            align-items: center;
            max-width: max-content;
            border: 1px solid transparent;
            border-radius: 999px;
            background: var(--rc-soft);
            padding: .18rem .5rem;
            font-size: .6875rem;
            font-weight: 650;
            color: var(--rc-muted);
            line-height: 1.2;
        }

        .rc-pill-accent {
            background: var(--rc-accent-soft);
            color: var(--rc-accent);
        }

        .rc-progress {
            height: .38rem;
            background: var(--rc-soft);
            border-radius: 999px;
            overflow: hidden;
        }

        .rc-progress span {
            display: block;
            height: 100%;
            background: var(--rc-accent);
            transition: width .35s ease;
        }

        .rc-pulse {
            animation: rcFade .35s ease-in;
        }

        @keyframes rcFade {
            from { opacity: .2; transform: translateY(4px); }
            to { opacity: 1; transform: none; }
        }

        .rc-chat {
            display: grid;
            grid-template-columns: minmax(250px, 340px) minmax(0, 1fr);
            gap: .875rem;
            align-items: start;
        }

        .rc-thread {
            max-height: 620px;
            overflow: auto;
            padding: .25rem;
        }

        .rc-message {
            max-width: 82%;
            border: 1px solid var(--rc-border);
            border-radius: .875rem;
            padding: .65rem .75rem;
            margin: .45rem 0;
            background: var(--rc-soft);
            font-size: .8375rem;
            line-height: 1.45;
        }

        .rc-message.out {
            margin-left: auto;
            background: var(--rc-accent-soft);
            border-color: rgba(255, 99, 56, .25);
        }

        .rc-thread-button {
            width: 100%;
            text-align: left;
            background: transparent;
            border-left: 0;
            border-right: 0;
            border-bottom: 0;
            border-radius: 0;
        }

        .rc-thread-button:hover {
            background: var(--rc-soft);
            margin-left: -.5rem;
            margin-right: -.5rem;
            padding-left: .5rem;
            padding-right: .5rem;
            width: calc(100% + 1rem);
        }

        .rc-drawer {
            position: fixed;
            inset: 0;
            z-index: 50;
            background: rgba(15, 23, 42, .28);
            display: flex;
            justify-content: flex-end;
            backdrop-filter: blur(2px);
        }

        .rc-drawer-panel {
            width: min(560px, 100%);
            height: 100%;
            background: var(--rc-surface);
            padding: 1rem;
            overflow: auto;
            box-shadow: -20px 0 40px rgba(15, 23, 42, .16);
        }

        .rc-empty {
            border: 1px dashed var(--rc-border);
            border-radius: .875rem;
            padding: 1rem;
            color: var(--rc-muted);
            font-size: .875rem;
            display: grid;
            gap: .2rem;
        }

        .rc-school-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(15.5rem, 1fr));
            gap: .65rem;
        }

        .rc-school-view-toggle {
            display:inline-flex;
            gap:.25rem;
            padding:.22rem;
            border:1px solid var(--rc-border);
            border-radius:.75rem;
            background:var(--rc-surface);
        }

        .rc-school-view-toggle .rc-btn {
            min-height:1.9rem;
            padding:.32rem .5rem;
            border-radius:.55rem;
            border-color:transparent;
        }

        .rc-school-view-toggle .rc-btn.is-active {
            border-color:rgba(255,99,56,.28);
            background:var(--rc-accent-soft);
            color:var(--rc-accent);
        }

        .rc-school-list-table {
            display:grid;
            gap:.4rem;
        }

        .rc-school-list-head,
        .rc-school-list-row {
            display:grid;
            grid-template-columns:minmax(13rem,2fr) 5.5rem minmax(7rem,1fr) minmax(7rem,1fr) 4rem 4rem 9rem;
            gap:.65rem;
            align-items:center;
        }

        .rc-school-list-head {
            color:var(--rc-muted);
            font-size:.68rem;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.05em;
            padding:.2rem .75rem;
        }

        .rc-school-list-row {
            border:1px solid var(--rc-border);
            border-radius:.78rem;
            background:var(--rc-surface);
            padding:.58rem .75rem;
            box-shadow:0 1px 2px rgba(15,23,42,.035);
        }

        .rc-school-list-row:hover {
            border-color:rgba(255,99,56,.35);
            background:var(--rc-soft);
        }

        .rc-school-list-name {
            border:0;
            background:transparent;
            color:var(--rc-text);
            text-align:left;
            cursor:pointer;
            display:flex;
            align-items:center;
            gap:.55rem;
            min-width:0;
            font-weight:750;
            font-size:.82rem;
        }

        .rc-school-list-logo {
            width:2rem;
            height:2rem;
            border-radius:.55rem;
            object-fit:contain;
            background:var(--rc-soft);
            flex:0 0 auto;
        }

        .rc-school-list-actions {
            display:flex;
            justify-content:flex-end;
            align-items:center;
            gap:.35rem;
        }

        .rc-school-list-picker { position:relative; display:inline-flex; }
        .rc-school-list-trigger { min-height:1.95rem; padding:.35rem .55rem; font-size:.72rem; white-space:nowrap; }
        .rc-school-list-menu { position:absolute; z-index:60; right:0; bottom:calc(100% + .35rem); width:10.5rem; max-height:12rem; overflow:auto; padding:.25rem; border:1px solid var(--rc-border); border-radius:.7rem; background:var(--rc-surface); box-shadow:0 16px 35px rgba(15,23,42,.16); }
        .rc-school-list-option { width:100%; border:0; border-radius:.5rem; background:transparent; color:var(--rc-text); display:flex; align-items:center; justify-content:space-between; gap:.45rem; padding:.42rem .5rem; font-size:.72rem; font-weight:700; text-align:left; }
        .rc-school-list-option:hover, .rc-school-list-option.is-active { background:var(--rc-accent-soft); color:var(--rc-accent); }
        .rc-school-list-empty { padding:.45rem .5rem; color:var(--rc-muted); font-size:.72rem; }
        .rc-school-list-check { font-size:.72rem; font-weight:900; }

        @media (max-width: 980px) {
            .rc-school-list-head { display:none; }
            .rc-school-list-row { grid-template-columns:1fr auto; gap:.45rem; }
            .rc-school-list-row > :nth-child(n+2):nth-child(-n+6) { display:none; }
        }


        .rc-school-card {
            border: 1px solid var(--rc-border);
            background: var(--rc-surface);
            border-radius: .78rem;
            padding: .75rem;
            min-height: 8.65rem;
            display: flex;
            flex-direction: column;
            gap: .5rem;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .rc-school-card:hover {
            border-color: rgba(255, 99, 56, .55);
            box-shadow: 0 8px 20px rgba(15, 23, 42, .08);
            transform: translateY(-1px);
        }

        .rc-school-topline,
        .rc-school-actions,
        .rc-school-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .rc-school-card h3 {
            margin: 0;
            font-size: .88rem;
            line-height: 1.2;
            font-weight: 750;
            letter-spacing: -.015em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rc-school-conference {
            margin: -.2rem 0 0;
            color: var(--rc-muted);
            font-size: .73rem;
            line-height: 1.25;
            min-height: 1.8rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rc-school-meta {
            margin-top: auto;
            color: var(--rc-muted);
            font-size: .72rem;
            line-height: 1.25;
        }

        .rc-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: var(--rc-accent-soft);
            color: var(--rc-accent);
            padding: .16rem .46rem;
            font-size: .68rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .rc-icon-button {
            width: 1.85rem;
            height: 1.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--rc-border);
            border-radius: .55rem;
            color: var(--rc-muted);
            background: var(--rc-surface);
            font-size: .9rem;
        }

        .rc-icon-button.is-active {
            color: var(--rc-accent);
            border-color: rgba(255, 99, 56, .35);
            background: var(--rc-accent-soft);
        }

        .rc-spinner-mini {
            width: .8rem;
            height: .8rem;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 999px;
            animation: rcSpin .65s linear infinite;
        }

        @keyframes rcSpin {
            to { transform: rotate(360deg); }
        }

        .rc-section-title {
            font-size: .82rem;
            font-weight: 700;
            color: var(--rc-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: .65rem;
        }

        .rc-list-button {
            width: 100%;
            text-align: left;
            justify-content: space-between;
        }

        .rc-favorites-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: .85rem;
            align-items: start;
        }

        .rc-favorites-panel .rc-school-grid {
            grid-template-columns: 1fr;
        }

        .rc-favorites-panel .rc-school-card {
            min-height: 8rem;
            gap: .45rem;
        }

        .rc-school-flags {
            min-height: 1.35rem;
            gap: .3rem;
        }

        .rc-favorites-panel .rc-school-actions {
            justify-content: flex-start;
        }

        .rc-favorites-panel .rc-school-actions .rc-btn-primary {
            min-width: 7.5rem;
        }

        .rc-coach-panel {
            min-height: 5.25rem;
        }


        .rc-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }

        .rc-format-btn {
            min-width: 2.15rem;
            padding-inline: .55rem;
        }

        .rc-rich-toolbar {
            gap: .35rem;
            flex-wrap: wrap;
        }

        .rc-rich-editor {
            min-height: 11rem;
            line-height: 1.55;
        }

        .rc-rich-editor p {
            margin: 0 0 .75rem;
        }

        .rc-rich-editor ul,
        .rc-rich-editor ol {
            margin: .5rem 0 .75rem 1.25rem;
            padding: 0;
        }

        .rc-school-grid.is-compact {
            display: grid;
            grid-template-columns: 1fr;
            gap: .5rem;
        }

        .rc-school-grid.is-compact .rc-school-card {
            min-height: 0;
            padding: .7rem;
            gap: .35rem;
            border-radius: .75rem;
        }

        .rc-school-grid.is-compact .rc-school-topline {
            margin-bottom: .1rem;
        }

        .rc-school-grid.is-compact .rc-school-card h3 {
            font-size: .88rem;
            line-height: 1.2;
            margin: 0;
        }

        .rc-school-grid.is-compact .rc-school-conference {
            font-size: .72rem;
            -webkit-line-clamp: 1;
            margin: 0;
        }

        .rc-school-grid.is-compact .rc-school-meta {
            font-size: .7rem;
            margin-top: .15rem;
        }

        .rc-school-grid.is-compact .rc-school-flags {
            min-height: 0;
            margin-top: .15rem;
        }

        .rc-school-grid.is-compact .rc-school-actions {
            margin-top: .2rem;
        }

        .rc-school-grid.is-compact .rc-btn-primary {
            min-height: 2.1rem;
            padding: .45rem .65rem;
        }

        .rc-list-button {
            min-height: 2.35rem;
            padding: .45rem .65rem;
            font-size: .78rem;
        }


        .rc-campaign-shell {
            display: grid;
            grid-template-columns: minmax(240px, 320px) minmax(520px, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .rc-campaign-panel {
            position: relative;
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: 1.35rem;
            background: linear-gradient(180deg, rgba(255,255,255,.055), rgba(255,255,255,.026));
            box-shadow: 0 18px 48px rgba(0,0,0,.22);
            overflow: hidden;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .rc-campaign-panel:hover {
            border-color: rgba(255, 91, 50, .22);
        }

        .rc-campaign-panel-header {
            padding: 1.05rem 1rem .85rem;
            border-bottom: 1px solid rgba(148, 163, 184, .12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
        }

        .rc-template-list,
        .rc-picker-list {
            display: grid;
            gap: .55rem;
            max-height: 38rem;
            overflow: auto;
            padding: .85rem;
            scroll-behavior: smooth;
        }

        .rc-template-item,
        .rc-picker-row {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            border: 1px solid rgba(148, 163, 184, .14);
            border-radius: 1rem;
            padding: .78rem;
            background: rgba(255,255,255,.032);
            transition: border-color .18s ease, background .18s ease, transform .18s ease, box-shadow .18s ease;
        }

        .rc-template-item:hover,
        .rc-picker-row:hover {
            border-color: rgba(255, 91, 50, .42);
            background: rgba(255, 91, 50, .055);
            transform: translateY(-1px);
            box-shadow: 0 12px 30px rgba(0,0,0,.18);
        }

        .rc-template-item.is-selected {
            border-color: rgba(255, 91, 50, .9);
            background: linear-gradient(135deg, rgba(255, 91, 50, .95), rgba(255, 91, 50, .72));
            box-shadow: 0 16px 34px rgba(255, 91, 50, .16), 0 10px 28px rgba(0,0,0,.18);
        }
        .rc-template-item.is-selected .rc-template-main strong,
        .rc-template-item.is-selected .rc-template-main span,
        .rc-template-item.is-selected .rc-template-icon { color:#fff; }
        .rc-template-item.is-selected .rc-template-icon { background:rgba(255,255,255,.16); }

        .rc-template-icon {
            width: 2.15rem;
            height: 2.15rem;
            border-radius: .75rem;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            background: rgba(255, 91, 50, .13);
            color: var(--rc-accent);
            font-weight: 800;
        }

        .rc-template-main {
            min-width: 0;
            flex: 1;
            display: grid;
            gap: .15rem;
            text-align: left;
        }

        .rc-template-main strong,
        .rc-picker-row strong {
            color: var(--rc-text);
            font-size: .88rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-template-main span,
        .rc-picker-row small {
            color: var(--rc-muted);
            font-size: .75rem;
            line-height: 1.35;
        }

        .rc-picker-row {
            justify-content: flex-start;
            cursor: pointer;
        }

        .rc-picker-row input {
            accent-color: var(--rc-accent);
        }

        .rc-campaign-fields {
            display: grid;
            gap: .7rem;
            padding: 1rem;
        }


        .rc-campaign-compose {
            display: grid;
            gap: .82rem;
            padding: 1rem;
        }
        .rc-template-field-label {
            display:block;
            margin-bottom:.4rem;
            color:rgba(203,213,225,.78);
            font-size:.7rem;
            text-transform:uppercase;
            letter-spacing:.06em;
            font-weight:800;
        }
        .rc-template-graphic-card {
            padding:.85rem;
            border:1px solid rgba(148,163,184,.16);
            border-radius:1rem;
            background:rgba(255,255,255,.024);
        }

        .rc-rich-editor-shell {
            border: 1px solid rgba(148,163,184,.2);
            border-radius: 1.1rem;
            overflow: hidden;
            background: rgba(2,6,23,.32);
        }

        .rc-rich-editor-toolbar {
            display:flex;
            flex-wrap:wrap;
            gap:.38rem;
            padding:.65rem;
            border-bottom:1px solid rgba(148,163,184,.14);
            background:rgba(255,255,255,.025);
        }

        .rc-rich-tool {
            min-width:2.15rem;
            height:2.15rem;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:.35rem;
            border:1px solid rgba(148,163,184,.18);
            border-radius:.65rem;
            background:rgba(255,255,255,.035);
            color:#e5e7eb;
            font-size:.78rem;
            font-weight:800;
            transition:.15s ease;
        }

        .rc-rich-tool:hover {
            border-color:rgba(255,91,50,.42);
            color:#fff;
            background:rgba(255,91,50,.11);
        }

        .rc-rich-editor {
            min-height:34rem;
            padding:1.1rem;
            color:var(--rc-text);
            background:rgba(2,6,23,.18);
            font-family:Arial,Helvetica,sans-serif;
            font-size:.98rem;
            line-height:1.7;
            outline:none;
        }

        .rc-rich-editor:empty:before {
            content: attr(data-placeholder);
            color:rgba(148,163,184,.72);
        }

        .rc-rich-editor img {
            width:100%;
            max-width:100%;
            height:auto;
            border-radius:.75rem;
            margin:.7rem 0;
        }

        .rc-rich-editor a.rc-email-button {
            display:inline-block;
            margin:.75rem 0;
            padding:.7rem 1rem;
            border-radius:.75rem;
            background:#ff5b32;
            color:#fff !important;
            font-weight:800;
            text-decoration:none;
        }


        .rc-quill-editor {
            min-height: 34rem;
            background: #ffffff;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
        }

        .rc-rich-editor-shell .ql-toolbar.ql-snow {
            border: 0;
            border-bottom: 1px solid rgba(148,163,184,.18);
            background: rgba(255,255,255,.96);
            padding: .65rem .75rem;
            border-radius: 1.1rem 1.1rem 0 0;
        }

        .rc-rich-editor-shell .ql-container.ql-snow {
            border: 0;
            min-height: 34rem;
            background: #ffffff;
            border-radius: 0 0 1.1rem 1.1rem;
            font-size: 1rem;
        }

        .rc-rich-editor-shell .ql-editor {
            min-height: 34rem;
            padding: 1.35rem;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.7;
        }

        .rc-rich-editor-shell .ql-editor.ql-blank::before {
            color: #94a3b8;
            font-style: normal;
            left: 1.35rem;
            right: 1.35rem;
        }

        .rc-rich-editor-shell .ql-editor img {
            width: 100%;
            max-width: 100%;
            border-radius: .75rem;
            margin: .65rem 0;
        }

        .rc-rich-editor-shell .ql-editor .rc-email-button {
            display: inline-block;
            margin: .75rem 0;
            padding: .7rem 1rem;
            border-radius: .75rem;
            background: #ff5b32;
            color: #fff !important;
            font-weight: 800;
            text-decoration: none;
        }

        .rc-preview-modal-backdrop {
            position:fixed;
            inset:0;
            z-index:70;
            background:rgba(0,0,0,.72);
            backdrop-filter:blur(9px);
            display:grid;
            place-items:center;
            padding:1.5rem;
        }

        .rc-preview-modal {
            width:min(820px,96vw);
            max-height:88vh;
            overflow:auto;
            border-radius:1.35rem;
            border:1px solid rgba(148,163,184,.22);
            background:#fff;
            color:#111827;
            box-shadow:0 30px 90px rgba(0,0,0,.45);
        }

        .rc-preview-modal-head {
            position:sticky;
            top:0;
            z-index:2;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:1rem;
            padding:1rem 1.15rem;
            border-bottom:1px solid #e5e7eb;
            background:#fff;
        }

        .rc-preview-modal-body {
            padding:1.35rem;
            font-family:Arial,Helvetica,sans-serif;
            line-height:1.7;
        }

        .rc-campaign-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
            align-items: center;
        }

        .rc-token-chip {
            border: 1px solid rgba(255, 91, 50, .24);
            background: rgba(255, 91, 50, .08);
            color: #ffb19d;
            border-radius: 999px;
            padding: .35rem .55rem;
            font-size: .72rem;
            font-weight: 800;
        }

        .rc-campaign-editor {
            width: 100%;
            min-height: 17rem;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .95rem;
            background: rgba(2, 6, 23, .32);
            color: var(--rc-text);
            padding: .9rem;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: .88rem;
            line-height: 1.55;
            resize: vertical;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .rc-campaign-editor:focus {
            outline: none;
            border-color: rgba(255, 91, 50, .62);
            box-shadow: 0 0 0 3px rgba(255, 91, 50, .12);
        }

        .rc-mini-preview-card {
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: 1rem;
            background: rgba(255,255,255,.026);
            padding: .75rem;
        }

        .rc-campaign-loading {
            border: 1px dashed rgba(148, 163, 184, .28);
            border-radius: .9rem;
            padding: 1rem;
            color: var(--rc-muted);
            display: flex;
            gap: .55rem;
            align-items: center;
            justify-content: center;
        }

        .rc-campaign-preview-wrap {
            padding: 1rem;
            display: grid;
            gap: .75rem;
        }

        .rc-email-preview {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 1rem;
            overflow: hidden;
            background: #fff;
            color: #111827;
            box-shadow: 0 18px 45px rgba(0,0,0,.18);
        }

        .rc-email-subject {
            padding: .8rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 800;
            color: #111827;
            background: #f9fafb;
            display: grid;
            gap: .2rem;
        }

        .rc-email-subject small {
            color: #6b7280;
            font-weight: 500;
        }

        .rc-preview-frame {
            width: 100%;
            min-height: 30rem;
            border: 0;
            display: block;
            background: #fff;
        }

        .rc-template-saving-overlay,
        .rc-template-loading-overlay {
            position: absolute;
            inset: 0;
            z-index: 5;
            display: grid;
            place-items: center;
            background: rgba(3, 7, 18, .28);
            backdrop-filter: blur(1.5px);
            color: var(--rc-text);
            font-weight: 800;
        }

        .rc-template-loading-card {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            border: 1px solid rgba(255, 91, 50, .22);
            border-radius: 999px;
            padding: .48rem .72rem;
            background: rgba(15, 23, 42, .9);
            box-shadow: 0 12px 32px rgba(0,0,0,.24);
            font-size:.82rem;
        }
        .rc-preview-updating {
            position:absolute;
            top:.85rem;
            right:.85rem;
            z-index:4;
            display:inline-flex;
            align-items:center;
            gap:.45rem;
            border:1px solid rgba(148,163,184,.22);
            background:rgba(255,255,255,.92);
            color:#111827;
            border-radius:999px;
            padding:.42rem .65rem;
            font-size:.78rem;
            font-weight:800;
            box-shadow:0 12px 28px rgba(15,23,42,.16);
        }

        .rc-skeleton {
            position: relative;
            overflow: hidden;
            border-radius: .75rem;
            background: rgba(148, 163, 184, .11);
        }

        .rc-skeleton::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.14), transparent);
            animation: rc-shimmer 1.15s infinite;
        }

        @keyframes rc-shimmer { 100% { transform: translateX(100%); } }

        .rc-preview-card-soft {
            background: #f8fafc;
            color: #111827;
            border-radius: 1.25rem;
            overflow: hidden;
            border: 1px solid rgba(148,163,184,.18);
            box-shadow: 0 18px 50px rgba(0,0,0,.20);
        }

        .rc-preview-content-font {
            font-family: Arial, Helvetica, sans-serif;
            letter-spacing: normal;
        }

        .rc-email-body-fallback {
            padding: 1rem;
            min-height: 18rem;
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        .rc-email-body-fallback img {
            width: 100%;
            max-width: 100%;
            height: auto;
        }

        .rc-target-card {
            display: grid;
            gap: .7rem;
            padding: 1rem;
        }

        .rc-recipient-stat {
            border: 1px solid rgba(255, 91, 50, .28);
            border-radius: .9rem;
            padding: .85rem;
            background: rgba(255, 91, 50, .08);
        }

        .rc-recipient-stat strong {
            color: var(--rc-text);
            font-size: 1.6rem;
            display: block;
            line-height: 1;
        }

        .rc-template-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            width: fit-content;
            border-radius: 999px;
            padding: .35rem .55rem;
            font-size: .72rem;
            color: #fed7aa;
            background: rgba(255, 91, 50, .14);
            border: 1px solid rgba(255, 91, 50, .24);
        }

        .rc-empty.is-small {
            min-height: 0;
            padding: .75rem;
            align-items: flex-start;
            text-align: left;
        }


    
        .rc-visual-editor-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            align-items: center;
            padding: .65rem;
            border: 1px solid rgba(255,255,255,.14);
            border-bottom: 0;
            border-radius: 1rem 1rem 0 0;
            background: rgba(15, 23, 42, .8);
        }

        .rc-visual-tool {
            height: 2rem;
            min-width: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .6rem;
            background: rgba(255,255,255,.055);
            color: var(--rc-text);
            font-size: .78rem;
            font-weight: 900;
        }

        .rc-visual-tool:hover { border-color: rgba(255,91,50,.55); color:#fed7aa; }

        .rc-template-live-editor-wrap{
            border:1px solid rgba(255,255,255,.14);
            border-radius:0 0 1rem 1rem;
            overflow:hidden;
            background:#fff;
            min-height:46rem;
        }
        .rc-template-live-editor{display:block;width:100%;height:52rem;border:0;background:#fff}
    .rc-design-editor {
            display:grid;
            gap:.75rem;
            max-height:28rem;
            overflow:auto;
            padding:.85rem;
            border:1px solid rgba(148,163,184,.22);
            border-radius:1rem;
            background:rgba(15,23,42,.38);
        }
        .rc-design-edit-row {
            display:grid;
            gap:.4rem;
        }
        .rc-design-edit-row label {
            color:#cbd5e1;
            font-size:.78rem;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.04em;
        }
        .rc-design-textarea {
            min-height:4.75rem;
            resize:vertical;
            line-height:1.45;
        }
        .rc-image-mini-preview {
            width:5rem;
            height:5rem;
            border-radius:.75rem;
            overflow:hidden;
            border:1px solid rgba(148,163,184,.22);
            background:#fff;
        }
        .rc-image-mini-preview img {
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
        }

        @media (max-width: 1180px) {
            .rc-school-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .rc-campaign-shell { grid-template-columns: minmax(240px, 320px) minmax(0, 1fr); }
            .rc-campaign-shell > .rc-campaign-panel:last-child { grid-column: 1 / -1; }
        }

        @media (max-width: 900px) {
            .rc-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .rc-chat { grid-template-columns: 1fr; }
            .rc-top { display: grid; }
            .rc-school-grid { grid-template-columns: 1fr; }
            .rc-favorites-layout { grid-template-columns: 1fr; }
            .rc-input, .rc-select { width: 100%; }
            .rc-coach-row { grid-template-columns: 1fr; align-items: stretch; }
            .rc-coach-actions { justify-content: flex-start; max-width: none; }
            .rc-menu-panel { right: auto; left: 0; }
            .rc-campaign-shell { grid-template-columns: 1fr; }
            .rc-campaign-shell > .rc-campaign-panel:last-child { grid-column: auto; }
            .rc-preview-frame { min-height: 24rem; }
        }

        @media (max-width: 640px) {
            .rc-coach-main { grid-template-columns: 2rem minmax(0, 1fr); }
            .rc-coach-avatar { width: 2rem; height: 2rem; border-radius: .65rem; }
            .rc-coach-heading { display: grid; gap: .35rem; }
            .rc-coach-heading h3 { white-space: normal; }
            .rc-coach-actions .rc-btn-primary { flex: 1 1 auto; }
        }


        /* v58 compact controls + inbox thread polish */
        .rc-page-heading { display:grid; gap:.35rem; margin: .25rem 0 1.25rem; }
        .rc-page-heading h1 { margin:0; font-size: clamp(1.55rem, 3vw, 2.15rem); line-height:1.05; font-weight:850; letter-spacing:-.04em; color:var(--rc-text); }
        .rc-search-hero { display:flex; align-items:center; gap:.7rem; border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1rem; padding:.58rem .7rem; box-shadow: 0 10px 26px rgba(0,0,0,.10); }
        .rc-search-hero svg { width:1.2rem; height:1.2rem; color:var(--rc-muted); flex:0 0 auto; }
        .rc-search-hero input { flex:1; border:0 !important; background:transparent !important; box-shadow:none !important; min-height:2.35rem; font-size:.95rem; }
        .rc-school-filter-box { display:grid; grid-template-columns: minmax(0,1fr) minmax(220px,.75fr) minmax(180px,.5fr); gap:1rem; align-items:end; margin-top:.85rem; padding:1rem; border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1rem; }
        .rc-filter-label { display:block; font-size:.72rem; letter-spacing:.09em; text-transform:uppercase; color:#9fb0c5; font-weight:750; margin-bottom:.55rem; }
        .rc-chip-row { display:flex; flex-wrap:wrap; gap:.45rem; }
        .rc-filter-chip { border:1px solid var(--rc-border); background:var(--rc-soft); color:#cbd5e1; border-radius:999px; padding:.52rem .78rem; font-size:.78rem; font-weight:700; transition:.15s ease; }
        .rc-filter-chip:hover, .rc-filter-chip.is-active { border-color:var(--rc-accent); color:#fff; background:var(--rc-accent-soft); }
        .rc-compose-compact-grid { display:grid; grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr); gap:1rem; }
        .rc-compose-summary { display:grid; gap:.65rem; }
        .rc-recipient-tabs { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.5rem; }
        .rc-recipient-tabs .rc-btn { padding:.62rem .55rem; font-size:.75rem; }
        .rc-compact-panel { border:1px solid var(--rc-border); background:rgba(24,24,27,.65); border-radius:.9rem; padding:.8rem; }
        .rc-details { border:1px solid var(--rc-border); border-radius:.85rem; background:var(--rc-surface); overflow:hidden; }
        .rc-details summary { list-style:none; cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.78rem .9rem; font-weight:800; }
        .rc-details summary::-webkit-details-marker { display:none; }
        .rc-details-body { border-top:1px solid var(--rc-border); padding:.8rem; }
        .rc-choice-list { display:grid; gap:.42rem; max-height:15rem; overflow:auto; padding-right:.2rem; }
        .rc-choice-row { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:.65rem; border:1px solid transparent; background:rgba(255,255,255,.025); border-radius:.75rem; padding:.6rem .7rem; text-align:left; transition:.15s ease; }
        .rc-choice-row:hover, .rc-choice-row.is-selected { border-color:rgba(255,99,56,.55); background:rgba(255,99,56,.10); }
        .rc-choice-title { font-weight:800; font-size:.86rem; color:var(--rc-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rc-choice-sub { color:var(--rc-muted); font-size:.74rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rc-icon-sm { width:1rem; height:1rem; }
        .rc-loading-inline { display:inline-flex; align-items:center; gap:.35rem; color:var(--rc-muted); font-size:.75rem; }
        .rc-inbox-layout { display:grid; grid-template-columns:minmax(320px,.42fr) minmax(0,1fr); gap:1rem; min-height:36rem; }
        .rc-inbox-list { display:grid; gap:.5rem; max-height:38rem; overflow:auto; padding-right:.25rem; }
        .rc-thread-card { width:100%; text-align:left; border:1px solid transparent; background:rgba(255,255,255,.025); border-radius:.9rem; padding:.72rem; display:grid; grid-template-columns:2.3rem minmax(0,1fr) auto; gap:.7rem; transition:.16s ease; }
        .rc-thread-card:hover, .rc-thread-card.is-selected { border-color:rgba(255,99,56,.55); background:rgba(255,99,56,.12); }
        .rc-avatar-mini { display:flex; align-items:center; justify-content:center; width:2.25rem; height:2.25rem; border-radius:.75rem; background:var(--rc-accent); color:white; font-weight:900; font-size:.8rem; }
        .rc-thread-subject { color:var(--rc-text); font-weight:850; font-size:.88rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rc-thread-preview { color:var(--rc-muted); font-size:.76rem; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .rc-email-thread { min-height:34rem; display:flex; flex-direction:column; }
        .rc-email-thread-head { display:flex; justify-content:space-between; gap:1rem; align-items:center; padding-bottom:.9rem; border-bottom:1px solid var(--rc-border); }
        .rc-message-list { display:grid; gap:1rem; padding:1rem 0; flex:1; overflow:auto; }
        .rc-email-message { border:1px solid var(--rc-border); background:var(--rc-surface); border-radius:1rem; padding:0; max-width:100%; overflow:hidden; box-shadow:0 12px 30px rgba(15,23,42,.06); }
        .rc-email-message.out { margin-left:0; background:var(--rc-surface); border-color:rgba(255,99,56,.32); }
        .rc-email-message.out .rc-email-format-head { border-left:4px solid var(--rc-accent); }
        .rc-email-format-head { display:grid; gap:.35rem; padding:.9rem 1rem; border-bottom:1px solid var(--rc-border); background:var(--rc-soft); }
        .rc-email-format-line { display:flex; gap:.45rem; align-items:baseline; min-width:0; color:var(--rc-muted); font-size:.75rem; line-height:1.35; }
        .rc-email-format-line strong { color:var(--rc-text); font-size:.76rem; min-width:3.1rem; }
        .rc-email-format-subject { color:var(--rc-text); font-weight:850; font-size:.95rem; line-height:1.35; margin-top:.15rem; }
        .rc-message-meta { display:flex; align-items:center; justify-content:space-between; gap:.65rem; margin-bottom:.55rem; color:var(--rc-muted); font-size:.72rem; }
        .rc-message-body { color:#111827; background:#fff; line-height:1.65; font-size:.94rem; padding:1.05rem; overflow:auto; }
        .rc-message-body img { max-width:100%; height:auto; border-radius:.75rem; display:block; margin:.75rem 0; }
        .rc-message-body table { max-width:100%; border-collapse:collapse; }
        .rc-message-body a { color:#2563eb; text-decoration:underline; }
        .rc-message-attachments { display:grid; gap:.55rem; padding:0 1.05rem 1.05rem; background:#fff; }
        .rc-message-attachment-image { max-width:100%; border-radius:.85rem; border:1px solid #e5e7eb; background:#fff; }
        .rc-message-attachment-link { display:inline-flex; align-items:center; gap:.4rem; width:max-content; max-width:100%; border:1px solid #e5e7eb; border-radius:.7rem; padding:.45rem .65rem; color:#2563eb; background:#f8fafc; font-size:.82rem; font-weight:750; text-decoration:none; }
        .rc-school-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:1rem; }
        .rc-school-card { min-height:unset; padding:1rem; border-radius:1rem; transition:transform .15s ease, border-color .15s ease, background .15s ease; }
        .rc-school-card:hover { transform:translateY(-2px); border-color:rgba(255,99,56,.5); }
        .rc-school-actions .rc-btn { min-width:5rem; }

        /* v62 compose/template quick actions */
        .rc-mini-action { display:inline-flex; align-items:center; gap:.35rem; padding:.45rem .7rem; border-radius:.7rem; border:1px solid rgba(148,163,184,.18); background:rgba(255,255,255,.045); color:#fff; font-weight:800; font-size:.82rem; transition:all .14s ease; }
        .rc-mini-action:hover { border-color:rgba(255,99,56,.55); color:#ff7a5c; transform:translateY(-1px); }
        .rc-rich-toolbar { display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.55rem; }
        .rc-rich-toolbar button { min-width:2.1rem; justify-content:center; }
        .rc-search-slim { margin:.75rem 0 1rem; }
        .rc-school-modal-actions { display:flex; flex-wrap:wrap; gap:.55rem; margin:1rem 0; }

        @media (max-width: 1100px) { .rc-compose-compact-grid,.rc-inbox-layout,.rc-school-filter-box { grid-template-columns:1fr; } .rc-recipient-tabs { grid-template-columns:repeat(2,minmax(0,1fr)); } }


        /* v60 dashboard refresh */
        .rc-dashboard { display:grid; gap:1.45rem; }
        .rc-dashboard-hero { display:grid; gap:.45rem; margin:.35rem 0 .45rem; }
        .rc-dashboard-hero h1 { margin:0; font-size:clamp(1.85rem, 4vw, 2.75rem); line-height:1.02; font-weight:950; letter-spacing:-.055em; color:#fff; text-shadow:0 2px 0 rgba(255,99,56,.18); }
        .rc-dashboard-hero p { margin:0; color:#b8c4d5; font-size:1rem; }
        .rc-dashboard-stat-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
        .rc-dashboard-card { position:relative; overflow:hidden; border:1px solid rgba(148,163,184,.22); background:linear-gradient(180deg, rgba(32,35,42,.98), rgba(24,26,31,.98)); border-radius:1.1rem; padding:1.35rem; box-shadow:0 18px 42px rgba(0,0,0,.22); }
        .rc-dashboard-card.is-centered { text-align:center; display:grid; place-items:center; min-height:10.2rem; }
        .rc-dashboard-card.is-centered .rc-dashboard-icon { margin-inline:auto; }
        .rc-dashboard-card.is-centered .rc-dashboard-number { letter-spacing:-.055em; }
        .rc-dashboard-stat { min-height:11.25rem; display:flex; flex-direction:column; justify-content:space-between; }
        .rc-dashboard-stat:before { content:""; position:absolute; left:0; top:.55rem; bottom:.55rem; width:.33rem; border-radius:999px; background:var(--stat-color, var(--rc-accent)); }
        .rc-dashboard-icon { display:inline-flex; width:3.05rem; height:3.05rem; align-items:center; justify-content:center; border-radius:.9rem; color:var(--stat-color, var(--rc-accent)); background:color-mix(in srgb, var(--stat-color, var(--rc-accent)) 18%, transparent); }
        .rc-dashboard-icon svg { width:1.35rem; height:1.35rem; }
        .rc-dashboard-number { margin-top:1.05rem; font-size:2.7rem; line-height:.9; font-weight:950; letter-spacing:-.075em; color:#fff; }
        .rc-dashboard-label { margin-top:.55rem; color:#aab7c8; font-size:.92rem; }
        .rc-dashboard-engagement { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
        .rc-metric-card { min-height:12.5rem; display:grid; gap:.85rem; }
        .rc-metric-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
        .rc-metric-delta { display:inline-flex; align-items:center; gap:.25rem; border-radius:999px; padding:.22rem .48rem; font-size:.75rem; font-weight:850; background:rgba(20,184,166,.13); color:#2dd4bf; }
        .rc-metric-delta.is-down { background:rgba(248,113,113,.14); color:#fb7185; }
        .rc-metric-value { font-size:2.15rem; line-height:1; font-weight:950; letter-spacing:-.06em; color:#030712; }
        .dark .rc-metric-value { color:#fff; }
        .rc-metric-name { color:#aab7c8; font-size:.86rem; }
        .rc-spark { width:100%; height:2.25rem; margin-top:auto; color:var(--rc-accent); }
        .rc-spark polyline { fill:none; stroke:currentColor; stroke-width:4; stroke-linecap:round; stroke-linejoin:round; }
        .rc-spark polygon { fill:rgba(255,99,56,.14); }
        .rc-dashboard-section-title { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin:.15rem 0 .55rem; }
        .rc-dashboard-section-title h2 { margin:0; font-size:1.35rem; line-height:1.1; font-weight:950; letter-spacing:-.04em; color:#fff; }
        .rc-dashboard-wide { display:grid; grid-template-columns:minmax(0,1fr); gap:1rem; }
        .rc-engaged-list { display:grid; gap:.65rem; }
        .rc-engaged-row { display:grid; grid-template-columns:2.6rem minmax(0,1fr) auto 10rem 3.5rem; align-items:center; gap:1rem; border-radius:1rem; background:rgba(15,18,24,.42); padding:.95rem 1rem; }
        .rc-rank { width:2.35rem; height:2.35rem; border-radius:.75rem; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#ff6338,#ff9885); color:white; font-weight:950; }
        .rc-rank.is-muted { background:#94a3b8; }
        .rc-school-title { font-size:1rem; font-weight:900; color:#fff; line-height:1.2; }
        .rc-school-mini { display:flex; flex-wrap:wrap; align-items:center; gap:.65rem; margin-top:.32rem; color:#9fb0c5; font-size:.78rem; }
        .rc-school-mini span { display:inline-flex; align-items:center; gap:.24rem; }
        .rc-school-mini svg { width:.9rem; height:.9rem; }
        .rc-replied-badge { display:inline-flex; border-radius:.38rem; padding:.18rem .42rem; background:rgba(20,184,166,.16); color:#2dd4bf; font-size:.66rem; font-weight:900; letter-spacing:.03em; text-transform:uppercase; }
        .rc-lead-bar { height:.58rem; border-radius:999px; background:rgba(255,255,255,.08); overflow:hidden; }
        .rc-lead-bar span { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#ff6338,#ff8d78); }
        .rc-lead-score { color:#fff; font-weight:950; font-size:1.05rem; text-align:right; }
        .rc-dashboard-bottom { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,.88fr); gap:1rem; }
        .rc-step-list, .rc-activity-list, .rc-list-pills { display:grid; gap:.8rem; }
        .rc-step-row { display:grid; grid-template-columns:2.35rem minmax(0,1fr) auto; gap:.8rem; align-items:center; border-radius:1rem; background:rgba(15,18,24,.36); padding:1rem; }
        .rc-step-index { width:2.15rem; height:2.15rem; display:flex; align-items:center; justify-content:center; border-radius:999px; color:white; background:var(--rc-accent); font-weight:950; }
        .rc-step-title { color:#fff; font-weight:900; }
        .rc-step-copy { color:#aab7c8; font-size:.86rem; margin-top:.22rem; }
        .rc-activity-empty { color:#aab7c8; font-size:.95rem; padding:1rem 0; }
        .rc-list-box { grid-column:1 / -1; }
        .rc-list-pills { grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:.8rem; }
        .rc-list-pill { display:flex; align-items:center; justify-content:space-between; gap:.75rem; border:1px solid rgba(148,163,184,.2); border-radius:.85rem; padding:.85rem 1rem; background:rgba(15,18,24,.28); color:#fff; font-weight:800; }
        .rc-list-count { min-width:1.55rem; height:1.25rem; border-radius:999px; background:var(--rc-accent); color:white; display:inline-flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:950; padding:0 .4rem; }
        @media (max-width:1180px) { .rc-dashboard-stat-grid,.rc-dashboard-engagement { grid-template-columns:repeat(2,minmax(0,1fr)); } .rc-dashboard-bottom { grid-template-columns:1fr; } .rc-engaged-row { grid-template-columns:2.6rem minmax(0,1fr) auto; } .rc-lead-bar,.rc-lead-score { display:none; } }
        @media (max-width:640px) { .rc-dashboard-stat-grid,.rc-dashboard-engagement { grid-template-columns:1fr; } .rc-step-row { grid-template-columns:2.35rem minmax(0,1fr); } .rc-step-row .rc-btn { grid-column:2; justify-self:start; } }



        /* v61 dashboard polish: compact text, png icons, scrollable activity, school slider */
        .rc-dashboard { gap: 1.25rem; }
        .rc-dashboard-hero h1 { max-width: 58rem; }
        .rc-dashboard-hero p { max-width: 62rem; color:#a9b6c8; }
        .rc-dashboard-stat-grid { grid-template-columns: repeat(4, minmax(0,1fr)); }
        .rc-dashboard-stat { min-height: 9.65rem; }
        .rc-dashboard-icon { border-radius: 1rem; }
        .rc-dashboard-icon img.rc-png-icon { width:1.35rem; height:1.35rem; display:block; object-fit:contain; }
        .rc-dashboard-number { font-size:2.55rem; }
        .rc-dashboard-label { font-size:.86rem; line-height:1.15; }
        .rc-dashboard-subline { margin-top:.28rem; color:#7f8da2; font-size:.72rem; line-height:1.25; min-height:1.8em; max-width:15rem; }
        .rc-metric-card { min-height:11.4rem; padding:1.15rem; }
        .rc-metric-value { font-size:2rem; }
        .rc-metric-name { font-size:.83rem; line-height:1.15; }
        .rc-metric-caption { color:#7f8da2; font-size:.72rem; line-height:1.25; margin-top:.28rem; max-width:14rem; }
        .rc-spark { height:2rem; opacity:.95; }
        .rc-engaged-row { cursor:pointer; transition:transform .15s ease, background .15s ease, border-color .15s ease; border:1px solid transparent; }
        .rc-engaged-row:hover { transform:translateY(-1px); border-color:rgba(255,99,56,.35); background:rgba(255,99,56,.075); }
        .rc-activity-list { max-height:24rem; overflow:auto; padding-right:.35rem; }
        .rc-activity-card { display:grid; grid-template-columns:2.2rem minmax(0,1fr) auto; gap:.72rem; align-items:start; border-radius:1rem; background:rgba(15,18,24,.36); padding:.85rem; }
        .rc-activity-copy { color:#aab7c8; font-size:.78rem; line-height:1.35; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
        .rc-activity-meta { color:#718096; font-size:.7rem; margin-top:.25rem; }
        .rc-activity-view { min-height:1.85rem; padding:.25rem .5rem; font-size:.7rem; }
        .rc-drawer { justify-content:center; align-items:center; background:rgba(0,0,0,.68); backdrop-filter:blur(8px); }
        .rc-drawer-panel { width:min(760px,92vw); height:min(82vh,760px); border:1px solid rgba(148,163,184,.22); border-radius:1.25rem; background:linear-gradient(180deg, rgb(31 34 41), rgb(24 26 31)); box-shadow:0 30px 90px rgba(0,0,0,.45); padding:1.35rem; }
        .rc-school-slide-head { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:1rem; align-items:start; }
        .rc-school-score-ring { width:4.15rem; height:4.15rem; border-radius:999px; display:grid; place-items:center; border:.42rem solid var(--rc-accent); color:#fff; font-weight:950; font-size:1.1rem; }
        .rc-school-detail-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; margin:1rem 0; }
        .rc-school-detail-stat { border-radius:.9rem; background:rgba(15,18,24,.42); padding:.85rem; }
        .rc-school-detail-stat strong { display:block; color:#fff; font-size:1.2rem; }
        .rc-school-detail-stat span { color:#9fb0c5; font-size:.72rem; }
        .rc-school-coach-list { display:grid; gap:.7rem; max-height:20rem; overflow:auto; padding-right:.2rem; }
        @media (max-width:1180px) { .rc-dashboard-stat-grid,.rc-dashboard-engagement { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:640px) { .rc-dashboard-stat-grid,.rc-dashboard-engagement,.rc-school-detail-grid { grid-template-columns:1fr; } .rc-activity-card { grid-template-columns:2.2rem minmax(0,1fr); } .rc-activity-view { grid-column:2; justify-self:start; } }
        /* v62 upper dashboard stat alignment fix */
        .rc-dashboard-hero { margin-top: .15rem; }
        .rc-dashboard-hero h1 { margin-bottom: .15rem; }
        .rc-dashboard-stat-grid { align-items: stretch; }
        .rc-dashboard-card.rc-dashboard-stat.is-centered {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 8.8rem;
            padding: 1.05rem 1rem 1rem;
            text-align: center;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered > div {
            width: 100%;
            display: grid;
            justify-items: center;
            align-content: center;
            gap: .32rem;
        }
        .rc-dashboard-card.rc-dashboard-stat:before {
            top: .6rem;
            bottom: .6rem;
            width: .28rem;
        }
        .rc-dashboard-icon {
            width: 2.7rem;
            height: 2.7rem;
            border-radius: .85rem;
            margin: 0 auto .18rem;
        }
        .rc-dashboard-icon img.rc-png-icon {
            width: 1.18rem;
            height: 1.18rem;
        }
        .rc-dashboard-number {
            margin: .08rem 0 0;
            font-size: clamp(2rem, 3.4vw, 2.45rem);
            line-height: .92;
            letter-spacing: -.055em;
        }
        .rc-dashboard-label {
            margin: 0;
            font-size: .82rem;
            line-height: 1.05;
            color: #d9e5f5;
        }
        .rc-dashboard-subline {
            margin: .05rem auto 0;
            min-height: 0;
            max-width: 11.5rem;
            font-size: .66rem;
            line-height: 1.18;
            color: #8190a5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .rc-dashboard-section-title { margin-top: .35rem; }
        .rc-dashboard-section-title .rc-subtle { max-width: 34rem; text-align: right; line-height: 1.25; }

        /* v63 stat layout: top-left content, no stat subtext */
        .rc-dashboard-stat-grid { align-items: stretch; }
        .rc-dashboard-card.rc-dashboard-stat.is-centered {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            text-align: left !important;
            min-height: 7.85rem !important;
            padding: 1.05rem 1.15rem 1rem 1.35rem !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered > div {
            width: 100% !important;
            display: grid !important;
            justify-items: start !important;
            align-content: start !important;
            gap: .28rem !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered .rc-dashboard-icon {
            margin: 0 0 .6rem 0 !important;
            width: 2.55rem !important;
            height: 2.55rem !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered .rc-dashboard-number {
            margin: 0 !important;
            font-size: clamp(2.05rem, 3vw, 2.45rem) !important;
            line-height: .9 !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered .rc-dashboard-label {
            margin: .1rem 0 0 !important;
            font-size: .9rem !important;
            line-height: 1.05 !important;
            color: #e7eef9 !important;
        }
        .rc-dashboard-subline, .rc-metric-caption { display: none !important; }
        .rc-metric-card { min-height: 8.6rem; }
        .rc-metric-card .rc-metric-head { margin-bottom: .7rem; }

        /* v64 engagement metrics: keep icons top-left, never centered */
        .rc-dashboard-engagement .rc-dashboard-card.rc-metric-card {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            justify-content: flex-start !important;
            text-align: left !important;
            min-height: 8.75rem !important;
            padding: 1.05rem 1.15rem !important;
        }
        .rc-dashboard-engagement .rc-metric-head {
            display: flex !important;
            justify-content: flex-start !important;
            align-items: flex-start !important;
            margin: 0 0 .72rem 0 !important;
            width: 100% !important;
        }
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon {
            margin: 0 !important;
            width: 2.5rem !important;
            height: 2.5rem !important;
        }
        .rc-dashboard-engagement .rc-metric-card > div:nth-child(2) {
            margin: 0 !important;
            text-align: left !important;
        }
        .rc-dashboard-engagement .rc-metric-value {
            margin: 0 !important;
            font-size: 2.2rem !important;
            line-height: .92 !important;
        }
        .rc-dashboard-engagement .rc-metric-name {
            margin-top: .22rem !important;
            color: #e7eef9 !important;
        }
        .rc-dashboard-engagement .rc-spark {
            margin-top: auto !important;
        }


        /* v65 dashboard final alignment + readable activity */
        .rc-load-status { display:flex; align-items:center; gap:.45rem; color:#b7c5d9; font-size:.88rem; font-weight:760; letter-spacing:.01em; }
        .rc-load-status-icon { color:var(--rc-accent); font-weight:950; font-size:1.15rem; line-height:1; }
        .rc-top .rc-pill { font-size:.76rem; padding:.48rem .68rem; color:#dbeafe; }
        .rc-dashboard-engagement .rc-dashboard-card.rc-metric-card,
        .rc-dashboard-engagement .rc-metric-card {
            display:grid !important;
            grid-template-rows:auto auto 1fr !important;
            align-items:start !important;
            justify-items:start !important;
            align-content:start !important;
            justify-content:stretch !important;
            place-items:start stretch !important;
            text-align:left !important;
            min-height:9.2rem !important;
        }
        .rc-dashboard-engagement .rc-metric-head,
        .rc-dashboard-engagement .rc-metric-head * { align-self:start !important; justify-self:start !important; }
        .rc-dashboard-engagement .rc-metric-head { width:100% !important; display:block !important; margin:0 0 .58rem 0 !important; }
        .rc-dashboard-engagement .rc-dashboard-icon,
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon { margin:0 !important; display:inline-flex !important; }
        .rc-dashboard-engagement .rc-metric-card > div:nth-child(2) { align-self:start !important; justify-self:start !important; width:100% !important; }
        .rc-dashboard-engagement .rc-spark { align-self:end !important; justify-self:stretch !important; width:100% !important; margin-top:.55rem !important; }

        /* v66 dashboard stat alignment: all stat icons/content stay top-left */
        .rc-dashboard-card,
        .rc-dashboard-card.rc-dashboard-stat,
        .rc-dashboard-card.rc-metric-card {
            position: relative;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered,
        .rc-dashboard-card.rc-metric-card,
        .rc-dashboard-engagement .rc-dashboard-card.rc-metric-card {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            text-align: left !important;
            gap: .72rem !important;
        }
        .rc-dashboard-card.rc-dashboard-stat.is-centered > div {
            align-items: flex-start !important;
            justify-content: flex-start !important;
            text-align: left !important;
            width: 100% !important;
        }
        .rc-dashboard-icon {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex: 0 0 auto !important;
            margin: 0 !important;
        }
        .rc-dashboard-icon .rc-png-icon,
        .rc-png-icon {
            width: 1.05rem !important;
            height: 1.05rem !important;
            display: block !important;
            object-fit: contain !important;
            object-position: center center !important;
            margin: auto !important;
        }
        .rc-dashboard-number,
        .rc-metric-value {
            margin-top: .2rem !important;
            text-align: left !important;
        }
        .rc-dashboard-label,
        .rc-metric-name {
            text-align: left !important;
            margin-top: -.18rem !important;
        }
        .rc-dashboard-engagement .rc-metric-head {
            display: flex !important;
            width: auto !important;
            margin: 0 !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
        }
        .rc-dashboard-engagement .rc-metric-card > div:nth-child(2) {
            width: 100% !important;
            align-self: flex-start !important;
        }
        .rc-dashboard-engagement .rc-spark {
            width: 100% !important;
            margin-top: auto !important;
            align-self: stretch !important;
        }
        .rc-engaged-row { cursor: pointer; }

        .rc-activity-card.has-asset .rc-activity-copy { -webkit-line-clamp:2; }
        .rc-activity-asset { display:inline-flex; align-items:center; gap:.28rem; margin-top:.35rem; width:max-content; max-width:100%; border:1px solid rgba(148,163,184,.18); border-radius:999px; padding:.2rem .48rem; color:#dbeafe; background:rgba(59,130,246,.12); font-size:.68rem; font-weight:850; }

        /* v68 console-safe top school clicks + locked icon alignment */
        .rc-dashboard-stat-grid .rc-dashboard-card,
        .rc-dashboard-engagement .rc-dashboard-card {
            align-items:flex-start !important;
            justify-content:flex-start !important;
            place-items:start !important;
            text-align:left !important;
        }
        .rc-dashboard-stat-grid .rc-dashboard-card > div,
        .rc-dashboard-engagement .rc-dashboard-card > div {
            align-self:flex-start !important;
            justify-self:flex-start !important;
            text-align:left !important;
        }
        .rc-dashboard-icon {
            display:inline-grid !important;
            place-items:center !important;
            align-self:flex-start !important;
            justify-self:flex-start !important;
            overflow:hidden !important;
            line-height:1 !important;
        }
        .rc-dashboard-icon img.rc-png-icon,
        img.rc-png-icon {
            width:1rem !important;
            height:1rem !important;
            object-fit:contain !important;
            object-position:50% 50% !important;
            display:block !important;
            margin:0 !important;
            transform:none !important;
        }
        .rc-dashboard-engagement .rc-metric-head {
            align-self:flex-start !important;
            justify-self:flex-start !important;
            margin:0 0 .72rem 0 !important;
        }


        /* v69 centered PNG stat icons: content stays top-left, icon artwork stays centered inside its badge */
        .rc-dashboard-card .rc-dashboard-icon,
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon {
            display:flex !important;
            align-items:center !important;
            justify-content:center !important;
            padding:0 !important;
            line-height:1 !important;
            text-align:center !important;
        }
        .rc-dashboard-card .rc-dashboard-icon img.rc-png-icon,
        .rc-dashboard-engagement .rc-dashboard-icon img.rc-png-icon,
        .rc-png-icon {
            width:1.06rem !important;
            height:1.06rem !important;
            display:block !important;
            object-fit:contain !important;
            object-position:center center !important;
            margin:0 !important;
            padding:0 !important;
            transform:none !important;
            position:static !important;
            inset:auto !important;
        }
        .rc-dashboard-engagement .rc-metric-head {
            display:flex !important;
            align-items:flex-start !important;
            justify-content:flex-start !important;
        }



        /* FINAL CLEAN DASHBOARD ICON RULES: one badge size, one artwork size, centered artwork */
        .rc-dashboard-stat-grid .rc-dashboard-icon,
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon {
            width: 3rem !important;
            height: 3rem !important;
            min-width: 3rem !important;
            min-height: 3rem !important;
            max-width: 3rem !important;
            max-height: 3rem !important;
            border-radius: 1rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
            line-height: 0 !important;
            box-sizing: border-box !important;
            flex: 0 0 3rem !important;
        }

        .rc-dashboard-stat-grid .rc-dashboard-icon > img.rc-png-icon,
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon > img.rc-png-icon {
            width: 1.42rem !important;
            height: 1.42rem !important;
            min-width: 1.42rem !important;
            min-height: 1.42rem !important;
            max-width: 1.42rem !important;
            max-height: 1.42rem !important;
            display: block !important;
            object-fit: contain !important;
            object-position: 50% 50% !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            position: static !important;
            inset: auto !important;
            transform: none !important;
            translate: none !important;
            vertical-align: middle !important;
        }

        .rc-dashboard-engagement .rc-metric-card {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            text-align: left !important;
        }

        .rc-dashboard-engagement .rc-metric-head {
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            max-width: none !important;
            max-height: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            align-self: flex-start !important;
            justify-self: flex-start !important;
            margin: 0 0 .78rem 0 !important;
            padding: 0 !important;
            line-height: 0 !important;
        }

        /* FINAL OVERRIDE: truly center engagement icons inside their colored badges */
        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon {
            position: relative !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .rc-dashboard-engagement .rc-metric-head .rc-dashboard-icon > img.rc-png-icon {
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            margin: 0 !important;
            display: block !important;
            object-fit: contain !important;
            object-position: 50% 50% !important;
        }

        /* Top engaged school dialog */
        .rc-school-modal-backdrop {
            justify-content: center !important;
            align-items: center !important;
            padding: 1.25rem !important;
            background: rgba(0,0,0,.72) !important;
            backdrop-filter: blur(10px) !important;
        }

        .rc-school-modal-panel {
            position: relative !important;
            width: min(720px, 92vw) !important;
            height: min(82vh, 760px) !important;
            max-height: 82vh !important;
            overflow: auto !important;
            border-radius: 1.35rem !important;
            border: 1px solid rgba(148,163,184,.20) !important;
            background: linear-gradient(180deg, #20232b 0%, #1b1d23 100%) !important;
            box-shadow: 0 28px 90px rgba(0,0,0,.55) !important;
            padding: 1.55rem !important;
            color: #f8fafc !important;
        }

        .rc-school-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 2.35rem;
            height: 2.35rem;
            border: 1px solid rgba(148,163,184,.16);
            border-radius: .85rem;
            background: rgba(15,18,24,.46);
            color: #9ca3af;
            font-size: 1.6rem;
            line-height: 1;
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: .15s ease;
            z-index: 3;
        }

        .rc-school-modal-close:hover {
            color: #fff;
            border-color: rgba(255,99,56,.35);
            background: rgba(255,99,56,.12);
        }

        .rc-school-modal-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1.25rem;
            align-items: start;
            padding-right: 3.25rem;
        }

        .rc-school-modal-main h2 {
            margin: .65rem 0 .35rem;
            font-size: clamp(1.45rem, 3vw, 1.95rem);
            line-height: 1.05;
            letter-spacing: -.035em;
            font-weight: 950;
            color: #fff;
        }

        .rc-school-division-pill {
            display: inline-flex;
            width: max-content;
            border-radius: .55rem;
            background: rgba(245,158,11,.20);
            color: #fbbf24;
            padding: .22rem .48rem;
            font-size: .72rem;
            font-weight: 950;
            letter-spacing: .035em;
        }

        .rc-school-modal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            color: #9fb0c5;
            font-size: .92rem;
            line-height: 1.35;
        }

        .rc-school-score-wrap {
            display: grid;
            justify-items: center;
            gap: .28rem;
            padding-top: .25rem;
        }

        .rc-school-score-ring {
            width: 4.55rem !important;
            height: 4.55rem !important;
            border-radius: 999px !important;
            display: grid !important;
            place-items: center !important;
            border: .42rem solid #ff6b50 !important;
            color: #fff !important;
            font-weight: 950 !important;
            font-size: 1.35rem !important;
            line-height: 1 !important;
            box-shadow: 0 0 0 .22rem rgba(255,99,56,.10), inset 0 0 0 1px rgba(255,255,255,.10) !important;
        }

        .rc-school-score-label {
            color: #ff6b50;
            font-size: .78rem;
            font-weight: 950;
            letter-spacing: .04em;
        }

        .rc-school-modal-actions {
            display: flex !important;
            align-items: center;
            flex-wrap: wrap;
            gap: .6rem;
            margin: 1.25rem 0 0 !important;
        }

        .rc-school-action {
            border: 1px solid rgba(148,163,184,.18);
            background: rgba(15,18,24,.36);
            color: #f8fafc;
            border-radius: .8rem;
            min-height: 2.65rem;
            padding: .62rem .9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            font-size: .86rem;
            font-weight: 850;
            transition: .15s ease;
        }

        .rc-school-action:hover {
            border-color: rgba(255,99,56,.40);
            background: rgba(255,99,56,.10);
        }

        .rc-school-action-primary {
            background: #ff6b50;
            border-color: #ff6b50;
            color: #fff;
        }

        .rc-school-action-primary:hover {
            background: #ff5837;
            border-color: #ff5837;
        }

        .rc-school-modal-rule {
            height: 1px;
            margin: 1.35rem 0 1.2rem;
            background: rgba(148,163,184,.18);
        }

        .rc-school-modal-section {
            display: grid;
            gap: .78rem;
            margin-top: 1.25rem;
        }

        .rc-school-section-title {
            color: #fff;
            font-size: 1rem;
            line-height: 1.2;
            font-weight: 950;
            letter-spacing: -.02em;
        }

        .rc-school-modal-coaches {
            display: grid;
            gap: .65rem;
            max-height: 19rem;
            overflow: auto;
            padding-right: .15rem;
        }

        .rc-school-coach-card {
            display: grid;
            grid-template-columns: 2.75rem minmax(0, 1fr) auto;
            align-items: center;
            gap: .8rem;
            border-radius: .95rem;
            background: rgba(15,18,24,.28);
            border: 1px solid rgba(148,163,184,.08);
            padding: .78rem;
        }

        .rc-school-coach-avatar {
            width: 2.5rem;
            height: 2.5rem;
            display: grid;
            place-items: center;
            border-radius: .75rem;
            background: #ff6b50;
            color: #fff;
            font-size: .78rem;
            font-weight: 950;
        }

        .rc-school-coach-info {
            display: grid;
            gap: .12rem;
            min-width: 0;
        }

        .rc-school-coach-info strong {
            color: #fff;
            font-size: .92rem;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-school-coach-info span {
            color: #aab7c8;
            font-size: .78rem;
            line-height: 1.25;
        }

        .rc-school-coach-info a {
            color: #4ea3ff;
            font-size: .8rem;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-school-copy-btn {
            width: 2.15rem;
            height: 2.15rem;
            display: grid;
            place-items: center;
            border: 1px solid rgba(148,163,184,.16);
            border-radius: .65rem;
            background: rgba(15,18,24,.32);
            color: #9fb0c5;
        }

        .rc-school-copy-btn:hover {
            color: #fff;
            border-color: rgba(255,99,56,.35);
        }

        .rc-school-stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .rc-school-stat-card {
            display: grid;
            grid-template-columns: 2.35rem minmax(0, 1fr);
            grid-template-rows: auto auto;
            column-gap: .7rem;
            align-items: center;
            border-radius: .95rem;
            background: rgba(15,18,24,.28);
            border: 1px solid rgba(148,163,184,.08);
            padding: .78rem;
        }

        .rc-school-stat-card span {
            grid-row: 1 / span 2;
            width: 2.15rem;
            height: 2.15rem;
            display: grid;
            place-items: center;
            border-radius: .7rem;
            background: rgba(255,99,56,.14);
            color: #ff6b50;
            font-weight: 950;
            line-height: 1;
        }

        .rc-school-stat-card strong {
            color: #fff;
            font-size: 1.35rem;
            line-height: 1;
            font-weight: 950;
        }

        .rc-school-stat-card small {
            color: #9fb0c5;
            font-size: .78rem;
            line-height: 1.2;
        }

        @media (max-width: 680px) {
            .rc-school-modal-panel {
                width: min(94vw, 720px) !important;
                height: min(86vh, 760px) !important;
                padding: 1rem !important;
            }

            .rc-school-modal-hero {
                grid-template-columns: 1fr;
                padding-right: 2.75rem;
            }

            .rc-school-score-wrap {
                justify-items: start;
            }

            .rc-school-stat-grid {
                grid-template-columns: 1fr;
            }

            .rc-school-coach-card {
                grid-template-columns: 2.75rem minmax(0, 1fr);
            }

            .rc-school-copy-btn {
                grid-column: 2;
                justify-self: start;
            }
        }

    </style>

    @php
        $formatRecruitingTimestamp = function ($value) {
            if (blank($value)) {
                return null;
            }

            try {
                return \Carbon\Carbon::parse($value)->timezone(config('app.timezone', 'UTC'))->format('M j, Y \a\t g:i A');
            } catch (\Throwable $exception) {
                return is_string($value) ? $value : null;
            }
        };

        $formattedCachedAt = $formatRecruitingTimestamp($cachedAt ?? null);
        $formattedTagSyncedAt = $formatRecruitingTimestamp($tagSyncedAt ?? null);
    @endphp

    <div
        class="rc-wrap"
        x-data
        wire:poll.visible.8s="pollRealtime"
        x-init="setTimeout(() => $wire.startBackgroundLoad(), 50); window.addEventListener('coach-database-load-next', () => setTimeout(() => $wire.loadNextBatch(), 75));"
    >
        <div class="rc-top">
            <div class="rc-load-status"><span class="rc-load-status-icon">⌁</span><span>{{ number_format($loadedSchoolsCount) }} schools</span><span>·</span><span>{{ number_format($loadedContactsCount) }} coaches</span>@if($isLoadingDataset)<span>·</span><span>syncing…</span>@endif</div>
            <div class="rc-toolbar">
                <button class="rc-btn" type="button" wire:click="refreshData" wire:loading.attr="disabled" wire:target="refreshData">
                    <span wire:loading.remove wire:target="refreshData">Refresh data</span>
                    <span wire:loading.flex wire:target="refreshData" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Refreshing</span>
                </button>
                @if(in_array($section, ['favorites', 'lists'], true))
                    <button class="rc-btn" type="button" wire:click="syncLatestContactTags" wire:loading.attr="disabled" wire:target="syncLatestContactTags,syncTagsIfStale">
                        <span wire:loading.remove wire:target="syncLatestContactTags,syncTagsIfStale">Sync saved/list tags</span>
                        <span wire:loading.flex wire:target="syncLatestContactTags,syncTagsIfStale" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Syncing</span>
                    </button>
                @endif
                @if($formattedCachedAt)<span class="rc-pill">Updated {{ $formattedCachedAt }}</span>@endif
                @if($formattedTagSyncedAt && in_array($section, ['favorites', 'lists'], true))<span class="rc-pill">Tags synced {{ $formattedTagSyncedAt }}</span>@endif
            </div>
        </div>

        @if($isLoadingDataset)
            <div class="rc-card is-flat">
                <div class="rc-subtle" style="margin-bottom:.5rem">Loading schools and coaches in the background. You can keep using the page.</div>
                <div class="rc-progress"><span style="width:{{ $remoteTotalSchools ? min(100, round(($loadedSchoolsCount / max(1,$remoteTotalSchools))*100)) : min(96, $loadedPages * 8) }}%"></span></div>
            </div>
        @endif

        @if($reason || $error)
            <div class="rc-card"><strong>{{ $reason ?: $error }}</strong></div>
        @endif

        @if($section === 'dashboard')
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $dashboardTopSchools = collect($this->dashboardTopEngagedSchools)->take(5)->values()->all();
                $dashboardLists = collect($lists ?? [])->take(8)->values()->all();
                $dashboardRecommendations = collect($this->dashboardRecommendations)->values()->all();
                $dashboardRecentActivity = collect($this->dashboardRecentActivity)->values()->all();
                $sparks = $dashboardMetrics['sparks'] ?? [];
                $sparkPoints = function ($key) use ($sparks) {
                    $values = collect($sparks[$key] ?? [1,2,1,3,2,2,3])->map(fn ($v) => (int) $v)->values();
                    if ($values->isEmpty()) { $values = collect([1,2,1,3,2,2,3]); }
                    if ((int) $values->sum() === 0) { $values = collect([0,1,0,2,1,3,1]); }
                    $max = max(1, (int) $values->max());
                    return $values->values()->map(function ($value, $i) use ($values, $max) {
                        $x = $values->count() <= 1 ? 0 : round(($i / max(1, $values->count() - 1)) * 220, 2);
                        $y = round(38 - (($value / $max) * 26), 2);
                        return $x . ',' . $y;
                    })->implode(' ');
                };
                $dashboardStats = [
                    ['name' => 'Schools Saved', 'value' => $dashboardMetrics['saved_schools'] ?? 0, 'icon' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAA+klEQVR4nO3Y0Q7CIBBEUdb4/7+MTxpNgGo7O2vCPW9NDLDDlhpaAwAAAAAA2ExUTt5776+FRJSs5VYxaWufxY+eXUoCmBVbEYI9gKMi3SHY3rszhTnOBUsHnN1VRzekB3BUxNEuZ4eQ2mKrxY8K//X3CmkdcKaYVZFZnZASwJWddIcgbaur73vWWCuyDlAv2HU4SgLI2i1HCLJXYLQY5cmdNf796gAzERHKQ0s93pPsDHjfjaxvdsYc8s9g9v939fjSAFyXGsp5yi5E/kXaITjzze457wS27wACqF5Ate0DsB+CVdffM9t3AAFUL6Da9gEAAAAAAABgNw9U/Xg0K1OcwgAAAABJRU5ErkJggg==', 'color' => '#ff6b50'],
                    ['name' => 'Favorite Schools', 'value' => $dashboardMetrics['favorite_schools'] ?? 0, 'icon' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAABDUlEQVR4nO2YQQ7DIAwEocr/v0wvQYoqtaLEyzpi5lxqs15jQikAAAAAAACwntZac8Z/OYNnwCpAr77TBTjAFdjd+500DnAJkkYAFxYBsti/lGQOcAizXIBM1S8lmQMcLBVgpPqrHbK9A+rMomx93Km1/r2fKQfMBFIzm9PtjbjdcLcYt88ApxsiYocmv8oNkaKHToEVboiOET4GlSIo/ltasaiWUIoqvQiFHFLittr+JigVIKIF1JMFB7gTcCMTINK6yjbY3gGHM/h1xG33LP45310fVZKgv6o5stFv6xUiLXNAPRn9rTqfjvwMmH6pOdc97iJ0TTj6W8D9+jSEMslHCAAAAAAAAAAA6XkDiTVoQnfOMe0AAAAASUVORK5CYII=', 'color' => '#f6b13f'],
                    ['name' => 'Engaged Schools', 'value' => $dashboardMetrics['engaged_schools'] ?? 0, 'icon' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAA/UlEQVR4nO3aQQ7CMBBD0V/uf+ewIRKLqrBobFfxu0A0nhloo0JVVVVVVVWAMcZQnvdSHvaLungIC2Br40N9bsQEOAqfIgJwsgfg7D6YA3AXDwET8O04jkN9pi2AhO6DKYCU4iFsBRzkASR1H4ImwPEDCOIA0roPIEv9ruLvnpSYFfjHijWRBJA4+tPyAFJHf3rECqz8h1gaQPLoT8sCSB/96RErsNKSAJ7SfRA+CJ25Ckr1aNwVcB2c0H0InAD1W2FcAGpRAWxzKXq2/1tciCSKCMDVfQgIwFk89FbYOwHu7kPACrjZAkjovpzrM5gr26+AVFr3q6qqqqo29gZjQmAtbz8YKwAAAABJRU5ErkJggg==', 'color' => '#5ca7e8'],
                    ['name' => 'Total Emails Sent', 'value' => $dashboardMetrics['emails_sent'] ?? 0, 'icon' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAA2UlEQVR4nO3XQQ7DMAhEUah6/yu7KzZRajkxDIry36ZSF3aGguuYAQAAAAAAAADew7MWGmOMrLVWufv2838yHqQjfNa+2wXoCp+1f0oHPNnrC/BVbXTnwFKMl6wDroZRnS3SEVgNpTxYS0Yg2v0sSHx3NhKz4LM1d5R2wGzuj0FWwlcoPwTd3f+FW/k1K8Obif4F7rRvdfAgPQRXQ6nCmwnvAWHWDcrgoe0mGGGPn2qtV+Hu8GZFI9D9hnjF61+GKMDuAp3zm7F/Sgd0FaG7+AAAAAAAAADwOD/TLVRCK+Pf4QAAAABJRU5ErkJggg==', 'color' => '#2dd4bf'],
                ];
                $engagementMetrics = [
                    ['key' => 'profile_views', 'name' => 'Profile Views', 'value' => $dashboardMetrics['profile_views'] ?? 0, 'icon' => 'data:image/png;base64,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'],
                    ['key' => 'link_clicks', 'name' => 'Link Clicks', 'value' => $dashboardMetrics['link_clicks'] ?? ($dashboardMetrics['trigger_link_clicks'] ?? 0), 'icon' => 'data:image/png;base64,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'],
                    ['key' => 'email_open_rate', 'name' => 'Email Open Rate', 'value' => (($dashboardMetrics['email_open_rate'] ?? 0) ?: 0) . '%', 'icon' => 'data:image/png;base64,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'],
                    ['key' => 'coach_replies', 'name' => 'Coach Replies', 'value' => $dashboardMetrics['coach_replies'] ?? 0, 'icon' => 'data:image/png;base64,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'],
                ];
            @endphp

            <div class="rc-dashboard">
                <div class="rc-dashboard-hero">
                    <h1>Your recruiting command center</h1>
                    <p>Track saved schools, engagement, email performance, link activity, and recent actions.</p>
                </div>

                <div class="rc-dashboard-stat-grid">
                    @foreach($dashboardStats as $stat)
                        <div class="rc-dashboard-card rc-dashboard-stat is-centered" style="--stat-color:{{ $stat['color'] }}">
                            <div>
                                <div class="rc-dashboard-icon"><img class="rc-png-icon" src="{{ $stat['icon'] }}" alt="" /></div>
                                <div class="rc-dashboard-number">{{ number_format((int) ($stat['value'] ?? 0)) }}</div>
                                <div class="rc-dashboard-label">{{ $stat['name'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="rc-dashboard-section-title">
                    <h2>Engagement</h2>
                    
                </div>

                <div class="rc-dashboard-engagement">
                    @foreach($engagementMetrics as $metric)
                        <div class="rc-dashboard-card rc-metric-card">
                            <div class="rc-metric-head"><div class="rc-dashboard-icon"><img class="rc-png-icon" src="{{ $metric['icon'] }}" alt="" /></div></div>
                            <div><div class="rc-metric-value">{{ is_numeric($metric['value']) ? number_format((int) $metric['value']) : $metric['value'] }}</div><div class="rc-metric-name">{{ $metric['name'] }}</div></div>
                            <svg class="rc-spark" viewBox="0 0 220 44" preserveAspectRatio="none"><polygon points="0,44 {{ $sparkPoints($metric['key']) }} 220,44" /><polyline points="{{ $sparkPoints($metric['key']) }}" /></svg>
                        </div>
                    @endforeach
                </div>

                <div class="rc-dashboard-card">
                    <div class="rc-dashboard-section-title"><h2>Top 5 Engaged Schools</h2><div class="rc-subtle">Ranked by replies, reply volume, and link activity</div></div>
                    <div class="rc-engaged-list">
                        <?php $engagedSchools = array_values(is_array($dashboardTopSchools ?? null) ? $dashboardTopSchools : []); ?>
                        <?php if (empty($engagedSchools)): ?>
                            <div class="rc-activity-empty">Your most engaged schools will appear after coaches reply, view your profile, or click recruiting links.</div>
                        <?php else: ?>
                            <?php foreach ($engagedSchools as $engagedIndex => $school): ?>
                                <?php
                                    $engagedSchoolId = (string) ($school['id'] ?? $school['business_id'] ?? '');
                                    if ($engagedSchoolId === '' && ! empty($school['name'])) { $engagedSchoolId = md5(strtolower(trim((string) $school['name']))); }
                                    $score = (int) ($school['lead_score'] ?? $school['engagement_score'] ?? 0);
                                    $views = (int) (($school['profile_views'] ?? 0) + ($school['highlight_views'] ?? 0));
                                    $clicks = (int) ($school['link_clicks'] ?? $school['trigger_link_clicks'] ?? $school['trigger_clicks'] ?? 0);
                                    $replies = (int) ($school['replies'] ?? $school['coach_replies'] ?? 0);
                                    $bar = max(8, min(100, $score));
                                ?>
                                <?php if ($engagedSchoolId !== ''): ?>
                                    <button type="button" class="rc-engaged-row" wire:click="openDashboardEngagedSchool({{ (int) $engagedIndex }})" wire:loading.attr="disabled" wire:target="openDashboardEngagedSchool">
                                <?php else: ?>
                                    <div class="rc-engaged-row" role="group" aria-label="Engaged school">
                                <?php endif; ?>
                                        <div class="rc-rank {{ $engagedIndex > 2 ? 'is-muted' : '' }}">{{ $engagedIndex + 1 }}</div>
                                        <div style="min-width:0;text-align:left">
                                            <div style="display:flex;align-items:center;gap:.55rem;flex-wrap:wrap">
                                                <div class="rc-school-title">{{ $school['name'] ?? 'School' }}</div>
                                                <?php if ($replies > 0): ?>
                                                    <span class="rc-replied-badge">{{ $replies }} {{ $replies === 1 ? 'reply' : 'replies' }}</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="rc-school-mini">
                                                <span>Views {{ $views }}</span>
                                                <span>Clicks {{ $clicks }}</span>
                                                <span>Replies {{ $replies }}</span>
                                                <span>{{ $school['coach_count'] ?? 0 }} coaches</span>
                                            </div>
                                        </div>
                                        <div class="rc-lead-bar"><span style="width:{{ $bar }}%"></span></div>
                                        <div class="rc-lead-score">{{ $score }}</div>
                                <?php if ($engagedSchoolId !== ''): ?>
                                    </button>
                                <?php else: ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rc-dashboard-bottom">
                    <div class="rc-dashboard-card">
                        <div class="rc-dashboard-section-title"><h2>Suggested next steps</h2></div>
                        <div class="rc-step-list">
                            <?php $recommendationRows = is_array($dashboardRecommendations ?? null) ? array_values($dashboardRecommendations) : collect($dashboardRecommendations ?? [])->values()->all(); ?>
                            <?php foreach ($recommendationRows as $recommendationIndex => $recommendationStep): ?>
                                <div class="rc-step-row"><div class="rc-step-index"><?php echo e($recommendationIndex + 1); ?></div><div><div class="rc-step-title"><?php echo e($recommendationStep['title'] ?? 'Next step'); ?></div><div class="rc-step-copy"><?php echo e($recommendationStep['copy'] ?? ''); ?></div></div><a class="rc-btn rc-btn-primary" href="<?php echo e($recommendationStep['url'] ?? '#'); ?>"><?php echo e($recommendationStep['label'] ?? 'Open'); ?> →</a></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="rc-dashboard-card">
                        <div class="rc-dashboard-section-title"><h2>Recent activity</h2></div>
                        <div class="rc-activity-list">
                            <?php $recentActivityRows = is_array($dashboardRecentActivity ?? null) ? $dashboardRecentActivity : []; ?>
                            <?php if (empty($recentActivityRows)): ?>
                                <div class="rc-activity-empty">No activity yet. Sent emails, replies, saved schools, and list actions will show here.</div>
                            <?php else: ?>
                                <?php foreach ($recentActivityRows as $recentActivityRow): ?>
                                    <?php
                                        $activityUrl = $recentActivityRow['url'] ?? \App\Filament\Pages\CoachDatabaseConversations::getUrl();
                                        $activityCopy = trim(strip_tags((string) ($recentActivityRow['copy'] ?? '')));
                                        $activityTypeRaw = strtolower((string) ($recentActivityRow['type'] ?? 'activity'));
                                        $activityType = strtoupper(substr($activityTypeRaw, 0, 1));
                                        $activityTitle = $recentActivityRow['title'] ?? 'Activity';
                                        $activityTime = $recentActivityRow['time'] ?? null;
                                        $activityHasImage = (bool) ($recentActivityRow['has_image'] ?? false) || preg_match('/\.(png|jpe?g|gif|webp)(\?|$)/i', $activityCopy);
                                        $activityHasFile = (bool) ($recentActivityRow['has_file'] ?? false) || (! $activityHasImage && preg_match('/\.(pdf|docx?|xlsx?|pptx?|zip)(\?|$)/i', $activityCopy));
                                        $activityAssetLabel = $activityHasImage ? 'Image attached' : ($activityHasFile ? 'File attached' : '');
                                        if ($activityHasImage || $activityHasFile) {
                                            $activityCopy = trim(preg_replace('/https?:\/\/\S+/i', '', $activityCopy));
                                            if ($activityCopy === '') { $activityCopy = 'Conversation includes an attachment.'; }
                                        }
                                    ?>
                                    <div class="rc-activity-card<?php echo ($activityHasImage || $activityHasFile) ? ' has-asset' : ''; ?>">
                                        <div class="rc-step-index" style="background:#334155"><?php echo e($activityType); ?></div>
                                        <div>
                                            <div class="rc-step-title"><?php echo e($activityTitle); ?></div>
                                            <div class="rc-activity-copy"><?php echo e($activityCopy); ?></div>
                                            <?php if ($activityAssetLabel !== ''): ?><div class="rc-activity-asset"><?php echo $activityHasImage ? '▧' : '▣'; ?> <?php echo e($activityAssetLabel); ?></div><?php endif; ?>
                                            <div class="rc-activity-meta"><?php echo e($activityTime ? \Illuminate\Support\Carbon::parse($activityTime)->diffForHumans() : 'Recent'); ?></div>
                                        </div>
                                        <a class="rc-btn rc-activity-view" href="<?php echo e($activityUrl); ?>">View</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="rc-dashboard-card rc-list-box">
                    <div class="rc-dashboard-section-title"><h2>Your lists</h2><a class="rc-btn" href="{{ \App\Filament\Pages\CoachDatabaseLists::getUrl() }}">Manage →</a></div>
                    <div class="rc-list-pills">
                        @forelse($dashboardLists as $list)
                            <a class="rc-list-pill" href="{{ \App\Filament\Pages\CoachDatabaseLists::getUrl() }}"><span>{{ $list['label'] ?? 'List' }}</span><span class="rc-list-count">{{ $list['schools_count'] ?? 0 }}</span></a>
                        @empty
                            <div class="rc-activity-empty">Create a recruiting list to organize target schools.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        @if($section === 'schools')
            <div class="rc-page-heading">
                <h1>Find schools</h1>
                <div class="rc-subtle">Search {{ number_format($loadedSchoolsCount) }} women’s soccer programs by name, coach, division, or conference.</div>
            </div>

            <div class="rc-search-hero">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                <input class="rc-input" placeholder="Search school, coach, or conference..." wire:model.live.debounce.350ms="search" />
                <button class="rc-btn" type="button" wire:click="clearSchoolFilters" wire:loading.attr="disabled" wire:target="clearSchoolFilters">
                    <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18l-7 8v5l-4 3v-8L3 4Z" /></svg>
                    Reset
                </button>
            </div>

            <div class="rc-school-filter-box">
                <div>
                    <span class="rc-filter-label">Division</span>
                    <div class="rc-chip-row">
                        @foreach(['NCAA D-I','NCAA D-II','NCAA D-III','NAIA','NJCAA'] as $division)
                            <button type="button" class="rc-filter-chip {{ $divisionFilter === $division ? 'is-active' : '' }}" wire:click="setDivisionFilter(@js($division))">{{ $division }}</button>
                        @endforeach
                    </div>
                </div>
                <label>
                    <span class="rc-filter-label">Conference</span>
                    <select class="rc-select" style="width:100%" wire:model.live="conferenceFilter">
                        <option value="">All conferences</option>
                        @foreach($this->conferences as $conference)
                            <option value="{{ $conference }}">{{ $conference }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="rc-filter-label">Sort</span>
                    <select class="rc-select" style="width:100%" wire:model.live="sort">
                        <option value="name">School A–Z</option>
                        <option value="coach_count">Most coaches</option>
                    </select>
                </label>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:.85rem 0 .25rem;flex-wrap:wrap">
                <div class="rc-subtle"><strong>{{ number_format($this->filteredSchoolsCount) }}</strong> schools</div>
                <div style="display:flex;align-items:center;gap:.55rem;flex-wrap:wrap">
                    <div wire:loading.flex wire:target="search,divisionFilter,conferenceFilter,sort,setDivisionFilter,clearSchoolFilters,setSchoolViewMode" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Updating</div>
                    <div class="rc-school-view-toggle" aria-label="School view">
                        <button type="button" class="rc-btn {{ $schoolViewMode === 'grid' ? 'is-active' : '' }}" wire:click="setSchoolViewMode('grid')" aria-label="Grid view">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        </button>
                        <button type="button" class="rc-btn {{ $schoolViewMode === 'list' ? 'is-active' : '' }}" wire:click="setSchoolViewMode('list')" aria-label="List view">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            @include('filament.partials.coach-database-school-grid', ['schools' => $this->filteredSchools, 'viewMode' => $schoolViewMode])
            @if($this->canLoadMoreSchools)
                <div style="margin-top:1rem;text-align:center"><button class="rc-btn" wire:click="loadMoreSchools" wire:loading.attr="disabled" wire:target="loadMoreSchools"><span wire:loading.remove wire:target="loadMoreSchools">Load more</span><span wire:loading.flex wire:target="loadMoreSchools" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Loading</span></button></div>
            @endif
        @endif

        @if($section === 'favorites')
            @if($isSyncingTags)<div class="rc-card is-flat rc-subtle"><span class="rc-spinner-mini"></span> Syncing saved and favorite tags…</div>@endif
            <div class="rc-favorites-layout">
                <div class="rc-card rc-favorites-panel">
                    <div class="rc-section-title">Saved schools</div>
                    <input class="rc-input rc-search-slim" style="width:100%" placeholder="Search saved schools" wire:model.live.debounce.350ms="favoriteSchoolSearch" />
                    @include('filament.partials.coach-database-school-grid', ['schools' => $this->savedSchools, 'compact' => true])
                </div>
                <div class="rc-card rc-favorites-panel">
                    <div class="rc-section-title">Favorite schools</div>
                    <input class="rc-input rc-search-slim" style="width:100%" placeholder="Search favorite schools" wire:model.live.debounce.350ms="favoriteSchoolSearch" />
                    @include('filament.partials.coach-database-school-grid', ['schools' => $this->favoriteSchools, 'compact' => true])
                </div>
            </div>
        @endif

        @if($section === 'lists')
            <div class="rc-grid" style="grid-template-columns:minmax(220px,300px) minmax(0,1fr)">
                <div class="rc-card rc-grid">
                    <div>
                        <div class="rc-section-title">Create list</div>
                        <div class="rc-toolbar">
                            <input class="rc-input" style="flex:1" placeholder="Dream D1 schools" wire:model="newListName" />
                            <button class="rc-btn rc-btn-primary" wire:click="createCustomList" wire:loading.attr="disabled" wire:target="createCustomList">
                                <span wire:loading.remove wire:target="createCustomList">Create</span>
                                <span wire:loading.flex wire:target="createCustomList" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Creating</span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <div class="rc-section-title">Lists</div>
                        <div class="rc-grid" style="gap:.45rem">
                            @forelse($lists as $list)
                                <button type="button" class="rc-btn rc-list-button {{ $selectedListKey === ($list['key'] ?? '') ? 'rc-btn-primary' : '' }}" wire:click="selectList('{{ $list['key'] }}')">
                                    <span>{{ $list['label'] }}</span>
                                    <span>{{ number_format($list['schools_count'] ?? 0) }} schools</span>
                                </button>
                            @empty
                                <div class="rc-subtle">No lists found yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="rc-card">
                    @if($this->selectedList)
                        <div class="rc-top" style="margin-bottom:.875rem">
                            <div>
                                <div class="rc-section-title" style="margin-bottom:.25rem">{{ $this->selectedList['label'] }}</div>
                                <div class="rc-subtle">{{ number_format(count($this->selectedListSchools)) }} schools · {{ number_format(count($this->selectedListCoaches)) }} coaches</div>
                            </div>
                            <button class="rc-btn" type="button" wire:click="clearSelectedList">Clear</button>
                        </div>
                        <input class="rc-input rc-search-slim" style="width:100%" placeholder="Search schools in this list" wire:model.live.debounce.350ms="listSchoolSearch" />
                        @include('filament.partials.coach-database-school-grid', ['schools' => $this->selectedListSchools, 'compact' => true])
                        <div class="rc-section-title" style="margin-top:1.25rem">Coaches in this list</div>
                        @forelse($this->selectedListCoaches as $coach)
                            @include('filament.partials.coach-row', ['coach' => $coach])
                        @empty
                            <div class="rc-subtle">Add a school or coach to this list to see it here.</div>
                        @endforelse
                    @else
                        <div class="rc-subtle">Choose a list to view its schools and coaches. You can add schools or coaches from school cards, coach rows, and the school drawer.</div>
                    @endif
                </div>
            </div>
        @endif

        @if($section === 'coaches')
            <div class="rc-card rc-toolbar is-flat"><input class="rc-input" placeholder="Search coaches" wire:model.live.debounce.400ms="coachSearch" /></div>
            <div class="rc-card">
                @forelse($this->filteredCoaches as $coach)
                    @include('filament.partials.coach-row', ['coach' => $coach])
                @empty
                    <div class="rc-subtle">Coaches are still loading. Schools appear first, then coaches are added as each school syncs.</div>
                @endforelse
                @if($this->canLoadMoreCoaches)<div style="margin-top:1rem"><button class="rc-btn" wire:click="loadMoreCoaches">Load more</button></div>@endif
            </div>
        @endif

        @if($section === 'conversations')
            <div class="rc-page-heading">
                <h1>Inbox</h1>
                <div class="rc-subtle">Email threads from coaches. Filter by school when you need a focused view.</div>
            </div>

            <div class="rc-inbox-layout" wire:poll.12s.visible="pollConversationUpdates">
                <div class="rc-card">
                    <div class="rc-toolbar" style="margin-bottom:.75rem;display:grid;grid-template-columns:1fr;gap:.55rem">
                        <input class="rc-input" style="width:100%" placeholder="Search inbox" wire:model.live.debounce.500ms="conversationSearch" />
                        <select class="rc-select" style="width:100%" wire:model.live="conversationSchoolFilter">
                            <option value="">All schools</option>
                            @foreach($this->conversationSchoolOptions as $schoolName)
                                <option value="{{ $schoolName }}">{{ $schoolName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div wire:loading.flex wire:target="loadConversations,pollConversationUpdates,conversationSearch,conversationSchoolFilter" class="rc-loading-inline" style="margin-bottom:.65rem"><span class="rc-spinner-mini"></span> Updating inbox</div>

                    <div class="rc-inbox-list">
                        <?php $inboxConversations = collect($this->filteredConversations ?? [])->values()->all(); ?>
                        <?php if (empty($inboxConversations)): ?>
                            <div class="rc-empty"><strong>No email threads found.</strong><span>Try another search or school filter.</span></div>
                        <?php else: ?>
                            <?php foreach ($inboxConversations as $inboxConversation): ?>
                                <?php
                                    $inboxConversationId = (string) ($inboxConversation['id'] ?? '');
                                    $inboxContactName = (string) ($inboxConversation['contact_name'] ?? $inboxConversation['name'] ?? 'Coach');
                                    $inboxInitials = collect(explode(' ', $inboxContactName))->filter()->map(fn($part) => substr((string) $part, 0, 1))->take(2)->implode('');
                                    $inboxLastMessage = strip_tags((string) ($inboxConversation['last_message'] ?? $inboxConversation['snippet'] ?? 'No preview available.'));
                                    $inboxSchoolLine = (string) ($inboxConversation['school'] ?? $inboxConversation['company_name'] ?? $inboxConversation['email'] ?? 'School unavailable');
                                    $inboxUpdatedAt = (string) ($inboxConversation['updated_at'] ?? $inboxConversation['last_message_at'] ?? '');
                                ?>
                                <button type="button" class="rc-thread-card <?php echo $selectedConversationId === $inboxConversationId ? 'is-selected' : ''; ?>" wire:click="selectConversation(<?php echo \Illuminate\Support\Js::from($inboxConversationId); ?>)" wire:loading.attr="disabled" wire:target="selectConversation(<?php echo \Illuminate\Support\Js::from($inboxConversationId); ?>)">
                                    <span class="rc-avatar-mini"><?php echo e(strtoupper($inboxInitials ?: 'C')); ?></span>
                                    <span style="min-width:0">
                                        <span class="rc-thread-subject"><?php echo e($inboxContactName); ?></span>
                                        <span class="rc-choice-sub"><?php echo e($inboxSchoolLine); ?></span>
                                        <span class="rc-thread-preview"><?php echo e($inboxLastMessage); ?></span>
                                    </span>
                                    <span style="display:grid;gap:.35rem;justify-items:end">
                                        <span class="rc-subtle"><?php echo e($inboxUpdatedAt); ?></span>
                                        <span class="rc-mail-dot">✉</span>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rc-card rc-email-thread">
                    <?php $selectedConversation = $selectedConversationId ? collect($this->conversations)->firstWhere('id', $selectedConversationId) : null; ?>
                    <div class="rc-email-thread-head">
                        <div>
                            <div class="rc-title">{{ $selectedConversation['contact_name'] ?? $selectedConversation['name'] ?? 'Email thread' }}</div>
                            <div class="rc-subtle">{{ $selectedConversation['school'] ?? $selectedConversation['company_name'] ?? $selectedConversation['email'] ?? 'Select a thread to view emails' }}</div>
                        </div>
                        @if($selectedConversationId)
                            <button class="rc-btn" type="button" wire:click="loadConversationMessages" wire:loading.attr="disabled" wire:target="loadConversationMessages"><span wire:loading.remove wire:target="loadConversationMessages">Refresh</span><span wire:loading.flex wire:target="loadConversationMessages" class="rc-loading-inline"><span class="rc-spinner-mini"></span></span></button>
                        @endif
                    </div>

                    <div class="rc-message-list">
                        <?php $threadMessages = is_array($messages ?? null) ? $messages : []; ?>
                        <?php if (empty($threadMessages)): ?>
                            <div class="rc-empty"><strong>Select a thread.</strong><span>Email messages will appear here.</span></div>
                        <?php else: ?>
                            <?php foreach ($threadMessages as $message): ?>
                                <?php
                                    $message = is_array($message) ? $message : [];
                                    $isOut = str_contains(strtolower((string) ($message['direction'] ?? '')), 'out');
                                    $fromLabel = $isOut ? 'You' : ($message['from_name'] ?? $selectedConversation['contact_name'] ?? 'Coach');
                                    $toLabel = $message['to'] ?? ($isOut ? ($selectedConversation['contact_name'] ?? 'Coach') : 'You');
                                    if (is_array($toLabel)) {
                                        $toLabel = collect($toLabel)->map(function ($item) {
                                            if (is_array($item)) {
                                                return $item['email'] ?? $item['name'] ?? $item['address'] ?? '';
                                            }
                                            return is_scalar($item) ? (string) $item : '';
                                        })->filter()->implode(', ');
                                    }
                                    $messageBody = (string) ($message['body'] ?? '');
                                    $messageAttachments = collect($message['attachments'] ?? [])->filter(fn ($attachment) => is_array($attachment) && filled($attachment['url'] ?? null));
                                ?>
                                <article class="rc-email-message <?php echo $isOut ? 'out' : ''; ?>">
                                    <div class="rc-email-format-head">
                                        <?php if (! empty($message['subject'])): ?>
                                            <div class="rc-email-format-subject"><?php echo e((string) $message['subject']); ?></div>
                                        <?php endif; ?>
                                        <div class="rc-email-format-line"><strong>From</strong><span><?php echo e((string) ($fromLabel ?: ($isOut ? 'You' : 'Coach'))); ?></span></div>
                                        <div class="rc-email-format-line"><strong>To</strong><span><?php echo e((string) ($toLabel ?: 'Recipient')); ?></span></div>
                                        <?php if (! empty($message['created_at'])): ?>
                                            <div class="rc-email-format-line"><strong>Date</strong><span><?php echo e((string) $message['created_at']); ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rc-message-body"><?php echo $messageBody !== '' ? $messageBody : '<p style="color:#64748b;margin:0">No message body.</p>'; ?></div>
                                    <?php if ($messageAttachments->isNotEmpty()): ?>
                                        <div class="rc-message-attachments">
                                            <?php foreach ($messageAttachments as $attachment): ?>
                                                <?php
                                                    $attachmentUrl = (string) ($attachment['url'] ?? '');
                                                    $attachmentName = (string) ($attachment['name'] ?? 'Attachment');
                                                    $attachmentType = strtolower((string) ($attachment['mime_type'] ?? $attachment['type'] ?? ''));
                                                    $isImageAttachment = str_starts_with($attachmentType, 'image/') || preg_match('/\.(png|jpe?g|gif|webp|svg)(\?|$)/i', $attachmentUrl);
                                                ?>
                                                <?php if ($isImageAttachment): ?>
                                                    <img class="rc-message-attachment-image" src="<?php echo e($attachmentUrl); ?>" alt="<?php echo e($attachmentName); ?>">
                                                <?php else: ?>
                                                    <a class="rc-message-attachment-link" href="<?php echo e($attachmentUrl); ?>" target="_blank" rel="noopener">Open <?php echo e($attachmentName); ?></a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    @if($hasMoreMessages)
                        <button class="rc-btn" type="button" wire:click="loadConversationMessages" wire:loading.attr="disabled" wire:target="loadConversationMessages"><span wire:loading.remove wire:target="loadConversationMessages">Load older emails</span><span wire:loading.flex wire:target="loadConversationMessages" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Loading</span></button>
                    @endif

                    @if($selectedConversationId || $selectedCoachId)
                        <?php $composerCoach = $this->selectedCoach; ?>
                        <div class="rc-native-email-composer" x-data="plyrNativeEditorBase('emailBody')" x-init="mount()" wire:key="native-email-composer-{{ $selectedCoachId ?: $selectedConversationId ?: 'new' }}">
                            <div class="rc-card is-flat" style="display:grid;gap:.75rem;margin-top:.75rem">
                                <div class="rc-top">
                                    <div>
                                        <div class="rc-row-title">Email {{ $composerCoach['name'] ?? ($selectedConversation['contact_name'] ?? 'coach') }}</div>
                                        <div class="rc-subtle">Built-in PLYRCard editor. No external editor account required.</div>
                                    </div>
                                    <button class="rc-btn" type="button" wire:click="closeComposer">Close</button>
                                </div>

                                <label style="display:grid;gap:.35rem">
                                    <span class="rc-section-title" style="margin:0">Subject</span>
                                    <input class="rc-input" style="width:100%" type="text" wire:model.live.debounce.500ms="emailSubject" placeholder="Subject">
                                </label>

                                <div class="rc-rich-editor-shell rc-native-editor-shell">
                                    <div class="rc-rich-editor-toolbar" role="toolbar" aria-label="Email message toolbar">
                                        <button class="rc-rich-tool" type="button" @click="command('undo')">↶</button>
                                        <button class="rc-rich-tool" type="button" @click="command('redo')">↷</button>
                                        <button class="rc-rich-tool" type="button" @click="block('p')">P</button>
                                        <button class="rc-rich-tool" type="button" @click="block('h2')">H2</button>
                                        <button class="rc-rich-tool" type="button" @click="command('bold')"><strong>B</strong></button>
                                        <button class="rc-rich-tool" type="button" @click="command('italic')"><em>I</em></button>
                                        <button class="rc-rich-tool" type="button" @click="command('underline')"><u>U</u></button>
                                        <button class="rc-rich-tool" type="button" @click="command('insertUnorderedList')">• List</button>
                                        <button class="rc-rich-tool" type="button" @click="command('insertOrderedList')">1. List</button>
                                        <button class="rc-rich-tool" type="button" @click="addLink()">Link</button>
                                        <button class="rc-rich-tool" type="button" @click="openImageUpload()">Image</button>
                                        <button class="rc-rich-tool" type="button" @click="addButton()">Button</button>
                                        <button class="rc-rich-tool" type="button" @click="addTable()">Table</button>
                                        <button class="rc-rich-tool" type="button" @click="command('removeFormat')">Clear</button>
                                    </div>
                                    <div class="rc-rich-editor-toolbar rc-merge-toolbar" aria-label="Merge values">
                                        <button class="rc-token-chip" type="button" @click="insertMerge('CoachFirstName')">Coach first</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('CoachLastName')">Coach last</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('CoachName')">Coach full</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('SchoolName')">School</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('CoachTitle')">Coach title</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('AthleteName')">Athlete</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('GraduationYear')">Grad year</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('Position')">Position</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('HighlightLink')">Highlight link</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('ProfileLink')">Profile link</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('AthleteEmail')">Email</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('AthletePhone')">Phone</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('InstagramLink')">Instagram</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('TwitterLink')">X</button>
                                                                                                                        <button class="rc-token-chip" type="button" @click="insertMerge('YoutubeLink')">YouTube</button>
                                    </div>
                                    <input x-ref="imageUpload" type="file" accept="image/*" multiple style="display:none" x-on:change="uploadInlineImages($event)">
                                    
                                    <div
                                        x-cloak
                                        x-show="activePanel"
                                        x-transition.opacity
                                        @keydown.escape.window="closeEditorPanel()"
                                        @click.self="closeEditorPanel()"
                                        style="position:fixed;inset:0;z-index:90;display:grid;place-items:center;padding:1rem;background:rgba(2,6,23,.62);backdrop-filter:blur(5px);"
                                    >
                                        <div style="width:min(26rem,94vw);border:1px solid rgba(148,163,184,.22);border-radius:1.1rem;background:var(--rc-surface);box-shadow:0 24px 80px rgba(0,0,0,.38);overflow:hidden;">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid var(--rc-border);">
                                                <strong x-text="activePanel === 'button' ? 'Insert button' : 'Insert link'" style="font-size:.92rem"></strong>
                                                <button type="button" class="rc-icon-button" @click="closeEditorPanel()" aria-label="Close">×</button>
                                            </div>
                                            <div x-show="activePanel === 'link'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Link text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Link text" x-model="panelLinkLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelLinkUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyLinkPanel()">Insert link</button></div>
                                            </div>
                                            <div x-show="activePanel === 'button'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Button text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Button text" x-model="panelButtonLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelButtonUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyButtonPanel()">Insert button</button></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-cloak x-show="editorNotice" class="rc-subtle" style="padding:.55rem .65rem;border-top:1px solid rgba(148,163,184,.14);color:#fed7aa" x-text="editorNotice"></div>
                                    <div x-show="uploadingImages" class="rc-loading-inline" style="padding:.5rem .65rem"><span class="rc-spinner-mini"></span> Uploading image</div>
                                    <div
                                        x-ref="editor"
                                        class="rc-rich-editor rc-native-editor"
                                        contenteditable="true"
                                        data-placeholder="Write your message..."
                                        data-initial-body="{{ base64_encode($emailBody ?? '') }}"
                                        @input="queueSync()"
                                        @blur="syncNow()"
                                    ></div>
                                </div>

                                <div class="rc-toolbar" style="justify-content:flex-end">
                                    <button class="rc-btn" type="button" wire:click="closeComposer">Cancel</button>
                                    <button class="rc-btn rc-btn-primary" type="button" wire:click="sendEmail" wire:loading.attr="disabled" wire:target="sendEmail">
                                        <span wire:loading.remove wire:target="sendEmail">Send email</span>
                                        <span wire:loading.flex wire:target="sendEmail" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Sending</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif


        @if($section === 'compose')
            <div class="rc-page-heading">
                <h1>Compose email</h1>
                <div class="rc-subtle">Choose recipients, start from a template, preview, then send.</div>
            </div>

            <div class="rc-compose-compact-grid">
                <div class="rc-campaign-panel">
                    <div class="rc-campaign-panel-header">
                        <div>
                            <div class="rc-section-title">Build email</div>
                            <div class="rc-subtle">Recipient tools are tucked into compact dropdowns.</div>
                        </div>
                        <button class="rc-btn rc-btn-primary" type="button" wire:click="sendComposedEmail" wire:loading.attr="disabled" wire:target="sendComposedEmail">
                            <span wire:loading.remove wire:target="sendComposedEmail">Send</span>
                            <span wire:loading.flex wire:target="sendComposedEmail" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Sending</span>
                        </button>
                    </div>

                    <div class="rc-campaign-compose">
                        <div class="rc-recipient-tabs">
                            <button type="button" class="rc-btn {{ $campaignTargetMode === 'list' ? 'rc-btn-primary' : '' }}" wire:click="$set('campaignTargetMode','list')">
                                <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" /></svg> List
                            </button>
                            <button type="button" class="rc-btn {{ $campaignTargetMode === 'school' ? 'rc-btn-primary' : '' }}" wire:click="$set('campaignTargetMode','school')">
                                <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3 3 8l9 5 9-5-9-5Zm0 10v8m-7-4 7 4 7-4" /></svg> School
                            </button>
                            <button type="button" class="rc-btn {{ $campaignTargetMode === 'coaches' ? 'rc-btn-primary' : '' }}" wire:click="$set('campaignTargetMode','coaches')">
                                <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19a6 6 0 0 0-12 0m12 0a6 6 0 0 1 6-6m-12-4a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm9 2a3 3 0 1 0 0-6" /></svg> Coaches
                            </button>
                            <button type="button" class="rc-btn {{ $campaignTargetMode === 'all' ? 'rc-btn-primary' : '' }}" wire:click="$set('campaignTargetMode','all')">
                                <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M5 7v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7" /></svg> All
                            </button>
                        </div>

                        <div class="rc-compose-summary">
                            @if($campaignTargetMode === 'list')
                                <details class="rc-details" open>
                                    <summary><span>Saved list</span><span class="rc-pill">{{ $this->composeSelectedList['label'] ?? 'Choose' }}</span></summary>
                                    <div class="rc-details-body">
                                        <select class="rc-select" style="width:100%" wire:model.live="campaignListKey">
                                            <option value="">Select a list</option>
                                            @foreach($lists as $list)
                                                <option value="{{ $list['key'] ?? '' }}">{{ $list['label'] ?? 'List' }} ({{ number_format($list['coaches_count'] ?? $list['count'] ?? 0) }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </details>
                            @endif

                            @if($campaignTargetMode === 'school')
                                <details class="rc-details" open>
                                    <summary><span>School</span><span class="rc-pill">{{ collect($this->composeSchoolOptions)->firstWhere('id', $campaignSchoolId)['name'] ?? 'Choose' }}</span></summary>
                                    <div class="rc-details-body">
                                        <input class="rc-input" style="width:100%;margin-bottom:.55rem" placeholder="Search schools" wire:model.live.debounce.300ms="composeSchoolSearch" />
                                        <div wire:loading.flex wire:target="composeSchoolSearch,selectComposeSchool" class="rc-loading-inline" style="margin-bottom:.5rem"><span class="rc-spinner-mini"></span> Loading schools</div>
                                        <div class="rc-choice-list">
                                            @forelse($this->composeSchoolResults as $school)
                                                <?php $sid = (string) ($school['id'] ?? ''); ?>
                                                <button type="button" class="rc-choice-row {{ $campaignSchoolId === $sid ? 'is-selected' : '' }}" wire:click="selectComposeSchool(@js($sid))" wire:loading.attr="disabled" wire:target="selectComposeSchool(@js($sid))">
                                                    <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3 3 8l9 5 9-5-9-5Z" /></svg>
                                                    <span style="min-width:0"><span class="rc-choice-title">{{ $school['name'] ?? 'School' }}</span><span class="rc-choice-sub">{{ $school['conference'] ?? 'Conference unavailable' }} · {{ number_format($school['coach_count'] ?? 0) }} coaches</span></span>
                                                    @if($campaignSchoolId === $sid)<span class="rc-pill rc-pill-accent">Selected</span>@endif
                                                </button>
                                            @empty
                                                <div class="rc-subtle">No schools found.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </details>
                            @endif

                            @if($campaignTargetMode === 'coaches')
                                <details class="rc-details" open>
                                    <summary><span>Coaches</span><span class="rc-pill">{{ count($campaignCoachIds) }} selected</span></summary>
                                    <div class="rc-details-body">
                                        <input class="rc-input" style="width:100%;margin-bottom:.55rem" placeholder="Search coaches" wire:model.live.debounce.300ms="campaignCoachSearch" />
                                        <div wire:loading.flex wire:target="campaignCoachSearch" class="rc-loading-inline" style="margin-bottom:.5rem"><span class="rc-spinner-mini"></span> Searching</div>
                                        <div class="rc-choice-list">
                                            @forelse($this->campaignCoachResults as $coach)
                                                <?php $coachId = (string) ($coach['id'] ?? ''); ?>
                                                <label class="rc-choice-row {{ in_array($coachId, $campaignCoachIds, true) ? 'is-selected' : '' }}">
                                                    <input type="checkbox" value="{{ $coachId }}" wire:model.live="campaignCoachIds" />
                                                    <span style="min-width:0"><span class="rc-choice-title">{{ $coach['name'] ?? 'Coach' }}</span><span class="rc-choice-sub">{{ $coach['school'] ?? 'School' }} · {{ $coach['email'] ?? '' }}</span></span>
                                                    <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75" /></svg>
                                                </label>
                                            @empty
                                                <div class="rc-subtle">No coaches found.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </details>
                            @endif

                            @if($campaignTargetMode === 'all')
                                <div class="rc-compact-panel"><strong>All loaded coaches</strong><div class="rc-subtle">Every loaded coach with an email will receive this message.</div></div>
                            @endif
                        </div>

                        <div class="rc-compact-panel">
                            <div class="rc-template-field-label">Start from template</div>
                            <select class="rc-select" style="width:100%;margin-top:.45rem" wire:model.live="campaignTemplateId" wire:change="useTemplateForCompose($event.target.value)">
                                <option value="">No template</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template['id'] ?? '' }}">{{ $template['name'] ?? 'Template' }}</option>
                                @endforeach
                            </select>
                            <div wire:loading.flex wire:target="useTemplateForCompose,campaignTemplateId" class="rc-loading-inline" style="margin-top:.5rem"><span class="rc-spinner-mini"></span> Loading template</div>
                        </div>

                        <div class="rc-compact-panel">
                            <div class="rc-template-field-label">Email image</div>
                            @if($composeGraphicUrl)
                                <div style="display:flex;align-items:center;gap:.65rem;margin:.55rem 0">
                                    <img src="{{ $composeGraphicUrl }}" alt="Email image" style="width:4.5rem;height:4.5rem;object-fit:cover;border-radius:.75rem;border:1px solid rgba(148,163,184,.25)">
                                    <button class="rc-btn" type="button" wire:click="removeComposeGraphic">Remove</button>
                                </div>
                            @endif
                            <input class="rc-input" style="width:100%;margin-bottom:.45rem" type="file" accept="image/*" wire:model="composeGraphicUpload" />
                            <input class="rc-input" style="width:100%" placeholder="Paste image URL" wire:model.live.debounce.650ms="composeGraphicUrl" />
                            <div wire:loading.flex wire:target="composeGraphicUpload,composeGraphicUrl" class="rc-loading-inline" style="margin-top:.5rem"><span class="rc-spinner-mini"></span> Updating image</div>
                        </div>

                        <div class="rc-compact-panel">
                            <div class="rc-subtle"><strong>{{ number_format($this->campaignRecipientCount) }}</strong> coaches will receive this email.</div>
                        </div>

                        <label>
                            <span class="rc-template-field-label">Subject</span>
                            <input class="rc-input" style="width:100%" placeholder="Subject" wire:model.live.debounce.650ms="campaignSubject" />
                        </label>

                        <div>
                            <div class="rc-template-field-label">Message</div>
                            <div
                                class="rc-rich-editor-shell rc-native-editor-shell"
                                wire:ignore
                                x-data="plyrCampaignBodyEditor()"
                                x-init="mount()"
                            >
                                <div class="rc-rich-editor-toolbar" role="toolbar" aria-label="Campaign message toolbar">
                                    <button class="rc-rich-tool" type="button" title="Undo" @click="command('undo')">↶</button>
                                    <button class="rc-rich-tool" type="button" title="Redo" @click="command('redo')">↷</button>
                                    <button class="rc-rich-tool" type="button" title="Paragraph" @click="block('p')">P</button>
                                    <button class="rc-rich-tool" type="button" title="Heading" @click="block('h2')">H2</button>
                                    <button class="rc-rich-tool" type="button" title="Bold" @click="command('bold')"><strong>B</strong></button>
                                    <button class="rc-rich-tool" type="button" title="Italic" @click="command('italic')"><em>I</em></button>
                                    <button class="rc-rich-tool" type="button" title="Underline" @click="command('underline')"><u>U</u></button>
                                    <button class="rc-rich-tool" type="button" title="Bullet list" @click="command('insertUnorderedList')">• List</button>
                                    <button class="rc-rich-tool" type="button" title="Numbered list" @click="command('insertOrderedList')">1. List</button>
                                    <button class="rc-rich-tool" type="button" title="Add link" @click="addLink()">Link</button>
                                    <button class="rc-rich-tool" type="button" title="Upload image" @click="openImageUpload()">Image</button>
                                    <button class="rc-rich-tool" type="button" title="Add button" @click="addButton()">Button</button>
                                    <button class="rc-rich-tool" type="button" title="Clear formatting" @click="command('removeFormat')">Clear</button>
                                </div>
                                <div class="rc-rich-editor-toolbar rc-merge-toolbar" aria-label="Campaign merge values">
                                    <span class="rc-subtle" style="align-self:center">Insert value:</span>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('CoachFirstName')">Coach first</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('CoachLastName')">Coach last</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('CoachName')">Coach full</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('SchoolName')">School</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('CoachTitle')">Coach title</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('Position')">Position</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('HighlightLink')">Highlight link</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('ProfileLink')">Profile link</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('AthleteEmail')">Email</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('AthletePhone')">Phone</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('InstagramLink')">Instagram</button>
                                    <button class="rc-token-chip" type="button" @click="insertMerge('TwitterLink')">X</button>
                                                                                                            <button class="rc-token-chip" type="button" @click="insertMerge('YoutubeLink')">YouTube</button>
                                </div>
                                <input x-ref="imageUpload" type="file" accept="image/*" multiple style="display:none" x-on:change="uploadInlineImages($event)">
                                    
                                    <div
                                        x-cloak
                                        x-show="activePanel"
                                        x-transition.opacity
                                        @keydown.escape.window="closeEditorPanel()"
                                        @click.self="closeEditorPanel()"
                                        style="position:fixed;inset:0;z-index:90;display:grid;place-items:center;padding:1rem;background:rgba(2,6,23,.62);backdrop-filter:blur(5px);"
                                    >
                                        <div style="width:min(26rem,94vw);border:1px solid rgba(148,163,184,.22);border-radius:1.1rem;background:var(--rc-surface);box-shadow:0 24px 80px rgba(0,0,0,.38);overflow:hidden;">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid var(--rc-border);">
                                                <strong x-text="activePanel === 'button' ? 'Insert button' : 'Insert link'" style="font-size:.92rem"></strong>
                                                <button type="button" class="rc-icon-button" @click="closeEditorPanel()" aria-label="Close">×</button>
                                            </div>
                                            <div x-show="activePanel === 'link'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Link text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Link text" x-model="panelLinkLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelLinkUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyLinkPanel()">Insert link</button></div>
                                            </div>
                                            <div x-show="activePanel === 'button'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Button text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Button text" x-model="panelButtonLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelButtonUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyButtonPanel()">Insert button</button></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-cloak x-show="editorNotice" class="rc-subtle" style="padding:.55rem .65rem;border-top:1px solid rgba(148,163,184,.14);color:#fed7aa" x-text="editorNotice"></div>
                                <div x-show="uploadingImages" class="rc-loading-inline" style="padding:.5rem .65rem"><span class="rc-spinner-mini"></span> Uploading image</div>
                                <div
                                    x-ref="editor"
                                    class="rc-rich-editor rc-native-editor"
                                    contenteditable="true"
                                    data-plyr-native-editor="campaign-body"
                                    data-placeholder="Write your message..."
                                    data-initial-body="{{ base64_encode($campaignBody ?? '') }}"
                                    x-on:input="queueSync()"
                                    x-on:blur="syncNow()"
                                ></div>
                            </div>
                            <input
                                x-ref="campaignBodyHidden"
                                type="hidden"
                                data-plyr-native-editor-hidden="campaign-body"
                                wire:model.live.debounce.800ms="campaignBody"
                            />
                        </div>
                    </div>
                </div>

                <div class="rc-campaign-panel">
                    <div class="rc-campaign-panel-header">
                        <div>
                            <div class="rc-section-title">Preview</div>
                            <div class="rc-subtle">Personalized for a sample coach.</div>
                        </div>
                    </div>
                    <div wire:loading.flex wire:target="campaignSubject,campaignBody,campaignTemplateId,campaignListKey,campaignSchoolId,campaignCoachIds,campaignCoachSearch,composeSchoolSearch" class="rc-loading-inline" style="margin-bottom:.65rem"><span class="rc-spinner-mini"></span> Updating preview</div>
                    <div class="rc-preview-card-soft" style="overflow:hidden">
                        <div style="padding:1rem 1.15rem;border-bottom:1px solid #e5e7eb;background:#fff">
                            <div style="font-size:.76rem;color:#64748b;margin-bottom:.45rem">To: {{ $this->composePreviewCoach['name'] ?? 'Coach' }} · {{ $this->composePreviewCoach['school'] ?? 'School' }}</div>
                            <h3 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:1.2rem;line-height:1.38;color:#111827;font-weight:800">{{ $this->composeRenderedSubject }}</h3>
                        </div>
                        <div style="padding:1.35rem;font-family:Arial,Helvetica,sans-serif;font-size:.98rem;line-height:1.7;min-height:32rem;background:#fff;color:#111827">
                            {!! $this->composeRenderedBody !!}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($section === 'campaigns')
            <div class="rc-campaign-shell">
                <div class="rc-campaign-panel">
                    <div class="rc-campaign-panel-header">
                        <div>
                            <div class="rc-section-title">Email templates</div>
                            <div class="rc-subtle">{{ count($templates) }} saved</div>
                        </div>
                        <div style="display:flex;gap:.5rem;align-items:center">
                            <button class="rc-btn" type="button" wire:click="loadTemplates" wire:loading.attr="disabled" wire:target="loadTemplates">
                                <span wire:loading.remove wire:target="loadTemplates">Refresh</span>
                                <span wire:loading.flex wire:target="loadTemplates" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span></span>
                            </button>
                            <button class="rc-btn rc-btn-primary" type="button" wire:click="newTemplate">New</button>
                        </div>
                    </div>

                    <div class="rc-template-list" wire:loading.class="opacity-60" wire:target="loadTemplates,selectTemplate">
                        @forelse($templates as $template)
                            <?php $templateId = (string) ($template['id'] ?? ''); ?>
                            <button type="button" class="rc-template-item {{ $selectedTemplateId === $templateId ? 'is-selected' : '' }}" wire:key="email-template-{{ $templateId }}" wire:click="selectTemplate(@js($templateId))" wire:loading.attr="disabled" wire:target="selectTemplate(@js($templateId))">
                                <span class="rc-template-icon">
                                    <span wire:loading.remove wire:target="selectTemplate(@js($templateId))">{{ strtoupper(substr((string) ($template['name'] ?? 'T'), 0, 1)) }}</span>
                                    <span wire:loading.flex wire:target="selectTemplate(@js($templateId))" style="align-items:center"><span class="rc-spinner-mini"></span></span>
                                </span>
                                <span class="rc-template-main">
                                    <strong>{{ $template['name'] ?? 'Untitled Template' }}</strong>
                                    <span>{{ $template['subjectLine'] ?? $template['subject'] ?? 'No subject yet' }}</span>
                                </span>
                            </button>
                        @empty
                            <div class="rc-empty"><strong>No templates yet.</strong><span>Create your first email template.</span></div>
                        @endforelse
                    </div>
                </div>

                <div class="rc-campaign-panel">
                    <div class="rc-campaign-panel-header">
                        <div>
                            <div class="rc-section-title">Edit template</div>
                            <div class="rc-subtle">Write the email body directly with formatting.</div>
                        </div>
                        <div style="display:flex;gap:.5rem;align-items:center">
                            @if($selectedTemplateId && ! $templateIsNew)
                                <button class="rc-btn" type="button" wire:click="deleteTemplate" wire:loading.attr="disabled" wire:target="deleteTemplate"><span wire:loading.remove wire:target="deleteTemplate">Delete</span><span wire:loading.flex wire:target="deleteTemplate" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Deleting</span></button>
                            @endif
                            <button
                                class="rc-btn"
                                type="button"
                                x-data
                                x-on:click="document.dispatchEvent(new CustomEvent('rc-open-template-preview'))"
                            >
                                Preview
                            </button>
                            <button class="rc-btn rc-btn-primary" type="button" wire:click="saveTemplate" wire:loading.attr="disabled" wire:target="saveTemplate">
                                <span wire:loading.remove wire:target="saveTemplate">Save</span>
                                <span wire:loading.flex wire:target="saveTemplate" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Saving</span>
                            </button>
                        </div>
                    </div>

                    <div
                        class="rc-campaign-compose"
                        wire:key="template-rich-editor-{{ $selectedTemplateId ?: ($templateIsNew ? 'new' : 'blank') }}"
                        x-data="plyrTemplateEditor()"
                        x-init="mount()"
                        @keydown.escape.window="showPreview = false"
                    >
                        @if($templateIsNew)
                            <span class="rc-template-pill">New template</span>
                        @elseif($templateName !== '')
                            <span class="rc-template-pill">Editing: {{ $templateName }}</span>
                        @endif

                        <label>
                            <span class="rc-template-field-label">Template name</span>
                            <input class="rc-input" style="width:100%" placeholder="Intro Email" wire:model.live.debounce.650ms="templateName" />
                        </label>

                        <label>
                            <span class="rc-template-field-label">Subject line</span>
                            <div style="display:flex;gap:.45rem;align-items:center">
                                <input x-ref="subject" class="rc-input" style="width:100%;min-width:0" placeholder="@{{GraduationYear}} @{{Position}} — Interested in @{{SchoolName}}" wire:model.live.debounce.650ms="templateSubject" />
                                <select class="rc-select" style="width:10.5rem;flex:0 0 10.5rem" @change="insertFieldMerge('subject', $event)"><option value="">Insert value</option><option value="CoachFirstName">Coach first</option><option value="CoachLastName">Coach last</option><option value="CoachName">Coach full</option><option value="SchoolName">School</option><option value="CoachTitle">Coach title</option><option value="AthleteName">Athlete</option><option value="GraduationYear">Grad year</option><option value="Position">Position</option><option value="ClubTeam">Club team</option><option value="GPA">GPA</option><option value="ProfileLink">Profile link</option><option value="HighlightLink">Highlight link</option><option value="AthleteEmail">Email</option><option value="AthletePhone">Phone</option><option value="InstagramLink">Instagram</option><option value="TwitterLink">X</option><option value="YoutubeLink">YouTube</option><option value="__custom__">Custom value...</option></select>
                            </div>
                        </label>

                        <label>
                            <span class="rc-template-field-label">Preview text</span>
                            <div style="display:flex;gap:.45rem;align-items:center">
                                <input x-ref="preview" class="rc-input" style="width:100%;min-width:0" placeholder="Short inbox preview" wire:model.live.debounce.650ms="templatePreviewText" />
                                <select class="rc-select" style="width:10.5rem;flex:0 0 10.5rem" @change="insertFieldMerge('preview', $event)"><option value="">Insert value</option><option value="CoachFirstName">Coach first</option><option value="CoachLastName">Coach last</option><option value="CoachName">Coach full</option><option value="SchoolName">School</option><option value="CoachTitle">Coach title</option><option value="AthleteName">Athlete</option><option value="GraduationYear">Grad year</option><option value="Position">Position</option><option value="ClubTeam">Club team</option><option value="GPA">GPA</option><option value="ProfileLink">Profile link</option><option value="HighlightLink">Highlight link</option><option value="AthleteEmail">Email</option><option value="AthletePhone">Phone</option><option value="InstagramLink">Instagram</option><option value="TwitterLink">X</option><option value="YoutubeLink">YouTube</option><option value="__custom__">Custom value...</option></select>
                            </div>
                        </label>

                        <div>
                            <div class="rc-template-field-label">Message</div>
                            <div class="rc-rich-editor-shell rc-native-editor-shell" wire:ignore>
                                <div class="rc-rich-editor-toolbar" role="toolbar" aria-label="Template editor toolbar">
                                    <button class="rc-rich-tool" type="button" title="Undo" @click="command('undo')">↶</button>
                                    <button class="rc-rich-tool" type="button" title="Redo" @click="command('redo')">↷</button>
                                    <button class="rc-rich-tool" type="button" title="Paragraph" @click="block('p')">P</button>
                                    <button class="rc-rich-tool" type="button" title="Heading" @click="block('h2')">H2</button>
                                    <button class="rc-rich-tool" type="button" title="Bold" @click="command('bold')"><strong>B</strong></button>
                                    <button class="rc-rich-tool" type="button" title="Italic" @click="command('italic')"><em>I</em></button>
                                    <button class="rc-rich-tool" type="button" title="Underline" @click="command('underline')"><u>U</u></button>
                                    <button class="rc-rich-tool" type="button" title="Bullet list" @click="command('insertUnorderedList')">• List</button>
                                    <button class="rc-rich-tool" type="button" title="Numbered list" @click="command('insertOrderedList')">1. List</button>
                                    <button class="rc-rich-tool" type="button" title="Add link" @click="addLink()">Link</button>
                                    <button class="rc-rich-tool" type="button" title="Upload image" @click="openImageUpload()">Image</button>
                                    <button class="rc-rich-tool" type="button" title="Add button" @click="addButton()">Button</button>
                                    <button class="rc-rich-tool" type="button" title="Add simple table" @click="addTable()">Table</button>
                                    <button class="rc-rich-tool" type="button" title="Clear formatting" @click="command('removeFormat')">Clear</button>
                                </div>
                                <div class="rc-rich-editor-toolbar rc-merge-toolbar" aria-label="Merge values">
                                    <span class="rc-subtle" style="align-self:center">Insert value:</span>
                                    <select class="rc-select" style="width:min(18rem,100%)" @change="insertMergeFromSelect($event)"><option value="">Insert value</option><option value="CoachFirstName">Coach first</option><option value="CoachLastName">Coach last</option><option value="CoachName">Coach full</option><option value="SchoolName">School</option><option value="CoachTitle">Coach title</option><option value="AthleteName">Athlete</option><option value="GraduationYear">Grad year</option><option value="Position">Position</option><option value="ClubTeam">Club team</option><option value="GPA">GPA</option><option value="ProfileLink">Profile link</option><option value="HighlightLink">Highlight link</option><option value="AthleteEmail">Email</option><option value="AthletePhone">Phone</option><option value="InstagramLink">Instagram</option><option value="TwitterLink">X</option><option value="YoutubeLink">YouTube</option><option value="__custom__">Custom value...</option></select>
                                </div>
                                <input x-ref="imageUpload" type="file" accept="image/*" multiple style="display:none" x-on:change="uploadInlineImages($event)">
                                    
                                    <div
                                        x-cloak
                                        x-show="activePanel"
                                        x-transition.opacity
                                        @keydown.escape.window="closeEditorPanel()"
                                        @click.self="closeEditorPanel()"
                                        style="position:fixed;inset:0;z-index:90;display:grid;place-items:center;padding:1rem;background:rgba(2,6,23,.62);backdrop-filter:blur(5px);"
                                    >
                                        <div style="width:min(26rem,94vw);border:1px solid rgba(148,163,184,.22);border-radius:1.1rem;background:var(--rc-surface);box-shadow:0 24px 80px rgba(0,0,0,.38);overflow:hidden;">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid var(--rc-border);">
                                                <strong x-text="activePanel === 'button' ? 'Insert button' : 'Insert link'" style="font-size:.92rem"></strong>
                                                <button type="button" class="rc-icon-button" @click="closeEditorPanel()" aria-label="Close">×</button>
                                            </div>
                                            <div x-show="activePanel === 'link'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Link text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Link text" x-model="panelLinkLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelLinkUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyLinkPanel()">Insert link</button></div>
                                            </div>
                                            <div x-show="activePanel === 'button'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Button text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Button text" x-model="panelButtonLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelButtonUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyButtonPanel()">Insert button</button></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-cloak x-show="editorNotice" class="rc-subtle" style="padding:.55rem .65rem;border-top:1px solid rgba(148,163,184,.14);color:#fed7aa" x-text="editorNotice"></div>
                                <div x-show="uploadingImages" class="rc-loading-inline" style="padding:.5rem .65rem"><span class="rc-spinner-mini"></span> Uploading image</div>
                                <div
                                    x-ref="editor"
                                    class="rc-rich-editor rc-native-editor"
                                    contenteditable="true"
                                    data-placeholder="Write your email template here..."
                                    data-initial-body="{{ base64_encode($templateBody ?? '') }}"
                                    x-on:input="queueSync()"
                                    x-on:blur="syncNow()"
                                ></div>
                            </div>
                            <input x-ref="imageUpload" type="file" accept="image/*" multiple class="sr-only" x-on:change="uploadInlineImages($event)">
                                
                                    <div
                                        x-cloak
                                        x-show="activePanel"
                                        x-transition.opacity
                                        @keydown.escape.window="closeEditorPanel()"
                                        @click.self="closeEditorPanel()"
                                        style="position:fixed;inset:0;z-index:90;display:grid;place-items:center;padding:1rem;background:rgba(2,6,23,.62);backdrop-filter:blur(5px);"
                                    >
                                        <div style="width:min(26rem,94vw);border:1px solid rgba(148,163,184,.22);border-radius:1.1rem;background:var(--rc-surface);box-shadow:0 24px 80px rgba(0,0,0,.38);overflow:hidden;">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid var(--rc-border);">
                                                <strong x-text="activePanel === 'button' ? 'Insert button' : 'Insert link'" style="font-size:.92rem"></strong>
                                                <button type="button" class="rc-icon-button" @click="closeEditorPanel()" aria-label="Close">×</button>
                                            </div>
                                            <div x-show="activePanel === 'link'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Link text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Link text" x-model="panelLinkLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelLinkUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyLinkPanel()">Insert link</button></div>
                                            </div>
                                            <div x-show="activePanel === 'button'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Button text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Button text" x-model="panelButtonLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelButtonUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyButtonPanel()">Insert button</button></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-cloak x-show="editorNotice" class="rc-subtle" style="padding:.55rem .65rem;border-top:1px solid rgba(148,163,184,.14);color:#fed7aa" x-text="editorNotice"></div>
                            <input x-ref="hidden" type="hidden" data-plyr-native-editor-hidden="template-body" wire:model.live.debounce.900ms="templateBody" />
                            <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-top:.65rem">
                                <span class="rc-subtle" x-show="!uploadingImages">Built-in PLYRCard editor. No external editor account or API key required.</span>
                                <span class="rc-subtle" x-show="uploadingImages" style="display:inline-flex;align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Uploading image…</span>
                            </div>
                        </div>

                        <div class="rc-preview-modal-backdrop" x-cloak x-show="showPreview" x-transition.opacity>
                            <div class="rc-preview-modal" @click.outside="showPreview = false">
                                <div class="rc-preview-modal-head">
                                    <div>
                                        <div style="font-size:.78rem;color:#64748b;margin-bottom:.25rem">To: Stephens Salas • Abilene Christian University</div>
                                        <h3 style="margin:0;font-size:1.2rem;line-height:1.35;font-weight:800" x-text="previewSubject()"></h3>
                                    </div>
                                    <button type="button" class="rc-btn" @click="showPreview = false">Close</button>
                                </div>
                                <div class="rc-preview-modal-body">
                                    <template x-if="previewGraphic()">
                                        <img :src="previewGraphic()" alt="Email graphic" style="max-width:100%;border-radius:1rem;margin-bottom:1rem">
                                    </template>
                                    <div x-html="previewHtml()"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($selectedCoachId && $section !== 'conversations')
            <div class="rc-card">
                <?php $composerCoach = $this->selectedCoach; ?>
                        <div class="rc-native-email-composer" x-data="plyrNativeEditorBase('emailBody')" x-init="mount()" wire:key="native-email-composer-{{ $selectedCoachId ?: $selectedConversationId ?: 'new' }}">
                            <div class="rc-card is-flat" style="display:grid;gap:.75rem;margin-top:.75rem">
                                <div class="rc-top">
                                    <div>
                                        <div class="rc-row-title">Email {{ $composerCoach['name'] ?? ($selectedConversation['contact_name'] ?? 'coach') }}</div>
                                        <div class="rc-subtle">Built-in PLYRCard editor. No external editor account required.</div>
                                    </div>
                                    <button class="rc-btn" type="button" wire:click="closeComposer">Close</button>
                                </div>

                                <label style="display:grid;gap:.35rem">
                                    <span class="rc-section-title" style="margin:0">Subject</span>
                                    <input class="rc-input" style="width:100%" type="text" wire:model.live.debounce.500ms="emailSubject" placeholder="Subject">
                                </label>

                                <div class="rc-rich-editor-shell rc-native-editor-shell">
                                    <div class="rc-rich-editor-toolbar" role="toolbar" aria-label="Email message toolbar">
                                        <button class="rc-rich-tool" type="button" @click="command('undo')">↶</button>
                                        <button class="rc-rich-tool" type="button" @click="command('redo')">↷</button>
                                        <button class="rc-rich-tool" type="button" @click="block('p')">P</button>
                                        <button class="rc-rich-tool" type="button" @click="block('h2')">H2</button>
                                        <button class="rc-rich-tool" type="button" @click="command('bold')"><strong>B</strong></button>
                                        <button class="rc-rich-tool" type="button" @click="command('italic')"><em>I</em></button>
                                        <button class="rc-rich-tool" type="button" @click="command('underline')"><u>U</u></button>
                                        <button class="rc-rich-tool" type="button" @click="command('insertUnorderedList')">• List</button>
                                        <button class="rc-rich-tool" type="button" @click="command('insertOrderedList')">1. List</button>
                                        <button class="rc-rich-tool" type="button" @click="addLink()">Link</button>
                                        <button class="rc-rich-tool" type="button" @click="openImageUpload()">Image</button>
                                        <button class="rc-rich-tool" type="button" @click="addButton()">Button</button>
                                        <button class="rc-rich-tool" type="button" @click="addTable()">Table</button>
                                        <button class="rc-rich-tool" type="button" @click="command('removeFormat')">Clear</button>
                                    </div>
                                    <div class="rc-rich-editor-toolbar rc-merge-toolbar" aria-label="Merge values">
                                        <button class="rc-token-chip" type="button" @click="insertMerge('CoachFirstName')">Coach first</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('CoachLastName')">Coach last</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('CoachName')">Coach full</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('SchoolName')">School</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('CoachTitle')">Coach title</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('AthleteName')">Athlete</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('GraduationYear')">Grad year</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('Position')">Position</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('HighlightLink')">Highlight link</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('ProfileLink')">Profile link</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('AthleteEmail')">Email</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('AthletePhone')">Phone</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('InstagramLink')">Instagram</button>
                                        <button class="rc-token-chip" type="button" @click="insertMerge('TwitterLink')">X</button>
                                                                                                                        <button class="rc-token-chip" type="button" @click="insertMerge('YoutubeLink')">YouTube</button>
                                    </div>
                                    <input x-ref="imageUpload" type="file" accept="image/*" multiple style="display:none" x-on:change="uploadInlineImages($event)">
                                    
                                    <div
                                        x-cloak
                                        x-show="activePanel"
                                        x-transition.opacity
                                        @keydown.escape.window="closeEditorPanel()"
                                        @click.self="closeEditorPanel()"
                                        style="position:fixed;inset:0;z-index:90;display:grid;place-items:center;padding:1rem;background:rgba(2,6,23,.62);backdrop-filter:blur(5px);"
                                    >
                                        <div style="width:min(26rem,94vw);border:1px solid rgba(148,163,184,.22);border-radius:1.1rem;background:var(--rc-surface);box-shadow:0 24px 80px rgba(0,0,0,.38);overflow:hidden;">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.85rem 1rem;border-bottom:1px solid var(--rc-border);">
                                                <strong x-text="activePanel === 'button' ? 'Insert button' : 'Insert link'" style="font-size:.92rem"></strong>
                                                <button type="button" class="rc-icon-button" @click="closeEditorPanel()" aria-label="Close">×</button>
                                            </div>
                                            <div x-show="activePanel === 'link'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Link text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Link text" x-model="panelLinkLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelLinkUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyLinkPanel()">Insert link</button></div>
                                            </div>
                                            <div x-show="activePanel === 'button'" style="display:grid;gap:.65rem;padding:1rem;">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">Button text</label>
                                                <input class="rc-input" style="width:100%" placeholder="Button text" x-model="panelButtonLabel">
                                                <label class="rc-subtle" style="font-weight:800;color:var(--rc-text)">URL or merge value</label>
                                                <input class="rc-input" style="width:100%" placeholder="@{{ProfileLink}} or https://..." x-model="panelButtonUrl">
                                                <div class="rc-toolbar" style="justify-content:flex-end;margin-top:.25rem"><button type="button" class="rc-btn" @click="closeEditorPanel()">Cancel</button><button type="button" class="rc-btn rc-btn-primary" @click="applyButtonPanel()">Insert button</button></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-cloak x-show="editorNotice" class="rc-subtle" style="padding:.55rem .65rem;border-top:1px solid rgba(148,163,184,.14);color:#fed7aa" x-text="editorNotice"></div>
                                    <div x-show="uploadingImages" class="rc-loading-inline" style="padding:.5rem .65rem"><span class="rc-spinner-mini"></span> Uploading image</div>
                                    <div
                                        x-ref="editor"
                                        class="rc-rich-editor rc-native-editor"
                                        contenteditable="true"
                                        data-placeholder="Write your message..."
                                        data-initial-body="{{ base64_encode($emailBody ?? '') }}"
                                        @input="queueSync()"
                                        @blur="syncNow()"
                                    ></div>
                                </div>

                                <div class="rc-toolbar" style="justify-content:flex-end">
                                    <button class="rc-btn" type="button" wire:click="closeComposer">Cancel</button>
                                    <button class="rc-btn rc-btn-primary" type="button" wire:click="sendEmail" wire:loading.attr="disabled" wire:target="sendEmail">
                                        <span wire:loading.remove wire:target="sendEmail">Send email</span>
                                        <span wire:loading.flex wire:target="sendEmail" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Sending</span>
                                    </button>
                                </div>
                            </div>
                        </div>
            </div>
        @endif

        @if($this->selectedSchool)
            @php
                $slideSchool = $this->selectedSchool;
                $slideSchoolId = (string) ($slideSchool['id'] ?? '');
                $slideSchoolName = (string) ($slideSchool['name'] ?? 'School');
                $slideDivision = (string) ($slideSchool['division'] ?? 'Division');
                $slideConference = (string) ($slideSchool['conference'] ?? 'Conference unavailable');
                $slideLocation = trim((string) (($slideSchool['city'] ?? '') . ((!empty($slideSchool['city']) && !empty($slideSchool['state'])) ? ', ' : '') . ($slideSchool['state'] ?? '')));
                $slideCoaches = collect($slideSchool['coaches'] ?? [])->values();
                $slideReplies = (int) ($slideSchool['replies'] ?? $slideSchool['coach_replies'] ?? 0);
                $slideClicks = (int) ($slideSchool['link_clicks'] ?? $slideSchool['trigger_link_clicks'] ?? $slideSchool['trigger_clicks'] ?? 0);
                $slideViews = (int) (($slideSchool['profile_views'] ?? 0) + ($slideSchool['highlight_views'] ?? 0));
                $slideEmails = (int) ($slideSchool['emails_sent'] ?? $slideSchool['sent_emails'] ?? $slideSchool['email_count'] ?? 0);
                $slideTexts = (int) ($slideSchool['texts_sent'] ?? $slideSchool['sms_count'] ?? 0);
                $slideScore = (int) ($slideSchool['lead_score'] ?? $slideSchool['engagement_score'] ?? max(0, ($slideReplies * 20) + ($slideClicks * 6) + ($slideViews * 2)));
                $slidePlayers = (int) ($slideSchool['players_count'] ?? $slideSchool['roster_count'] ?? $slideSchool['players'] ?? 0);
                $slideUpperclass = (int) ($slideSchool['upperclass_count'] ?? $slideSchool['upperclass'] ?? 0);
                $slideUnderclass = (int) ($slideSchool['underclass_count'] ?? $slideSchool['underclass'] ?? 0);
                $slideHasRoster = $slidePlayers > 0 || $slideUpperclass > 0 || $slideUnderclass > 0;
                if (! $slideHasRoster) {
                    $slidePlayers = $slidePlayers ?: $slideCoaches->count();
                    $slideUpperclass = (int) floor($slidePlayers * .35);
                    $slideUnderclass = max(0, $slidePlayers - $slideUpperclass);
                }
            @endphp

            <div class="rc-drawer rc-school-modal-backdrop" wire:key="school-drawer" wire:click.self="closeSchool">
                <div class="rc-drawer-panel rc-school-modal-panel" role="dialog" aria-modal="true" aria-label="{{ $slideSchoolName }} details">
                    <button class="rc-school-modal-close" type="button" wire:click="closeSchool" aria-label="Close school details">×</button>

                    <div class="rc-school-modal-hero">
                        <div class="rc-school-modal-main">
                            <span class="rc-school-division-pill">{{ $slideDivision }}</span>
                            <h2>{{ $slideSchoolName }}</h2>
                            <div class="rc-school-modal-meta">
                                <span>◎ {{ $slideConference }}</span>
                                @if($slideLocation !== '')
                                    <span>· {{ $slideLocation }}</span>
                                @endif
                            </div>

                            <div class="rc-school-modal-actions">
                                @if($slideSchool['is_favorite'] ?? false)
                                    <button class="rc-school-action rc-school-action-primary" type="button" wire:click="unfavoriteSchoolById({{ \Illuminate\Support\Js::from($slideSchoolId) }})" wire:loading.attr="disabled" wire:target="unfavoriteSchoolById">
                                        ♡ Favorited
                                    </button>
                                @else
                                    <button class="rc-school-action rc-school-action-primary" type="button" wire:click="favoriteSchoolById({{ \Illuminate\Support\Js::from($slideSchoolId) }})" wire:loading.attr="disabled" wire:target="favoriteSchoolById">
                                        ♡ Favorite
                                    </button>
                                @endif

                                @if($slideSchool['is_saved'] ?? false)
                                    <button class="rc-school-action" type="button" wire:click="unsaveSchoolById({{ \Illuminate\Support\Js::from($slideSchoolId) }})" wire:loading.attr="disabled" wire:target="unsaveSchoolById">
                                        ✓ Saved
                                    </button>
                                @else
                                    <button class="rc-school-action" type="button" wire:click="saveSchoolById({{ \Illuminate\Support\Js::from($slideSchoolId) }})" wire:loading.attr="disabled" wire:target="saveSchoolById">
                                        + Add to list
                                    </button>
                                @endif

                                <button class="rc-school-action" type="button" wire:click="composeEmailSchool({{ \Illuminate\Support\Js::from($slideSchoolId) }})">
                                    ✉ Email coaches
                                </button>
                            </div>
                        </div>

                        <div class="rc-school-score-wrap">
                            <div class="rc-school-score-ring">{{ max(0, min(100, $slideScore)) }}</div>
                            <div class="rc-school-score-label">{{ $slideScore >= 70 ? 'HOT' : ($slideScore >= 35 ? 'WARM' : 'NEW') }}</div>
                        </div>
                    </div>

                    <div class="rc-school-modal-rule"></div>

                    <section class="rc-school-modal-section">
                        <div class="rc-school-section-title">Coaching staff ({{ number_format($slideCoaches->count()) }})</div>
                        <div class="rc-school-coach-list rc-school-modal-coaches">
                            @forelse($slideCoaches as $coach)
                                @php
                                    $coachName = (string) ($coach['name'] ?? trim(($coach['first_name'] ?? '') . ' ' . ($coach['last_name'] ?? '')) ?: 'Coach');
                                    $coachTitle = (string) ($coach['title'] ?? $coach['position'] ?? 'Coach');
                                    $coachEmail = (string) ($coach['email'] ?? '');
                                    $coachInitials = collect(explode(' ', $coachName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
                                @endphp
                                <div class="rc-school-coach-card">
                                    <div class="rc-school-coach-avatar">{{ strtoupper($coachInitials ?: 'C') }}</div>
                                    <div class="rc-school-coach-info">
                                        <strong>{{ $coachName }}</strong>
                                        <span>{{ $coachTitle }}</span>
                                        @if($coachEmail !== '')
                                            <a href="mailto:{{ $coachEmail }}">{{ $coachEmail }}</a>
                                        @endif
                                    </div>
                                    @if($coachEmail !== '')
                                        <button class="rc-school-copy-btn" type="button" x-on:click="navigator.clipboard?.writeText(@js($coachEmail))" title="Copy email">▣</button>
                                    @endif
                                </div>
                            @empty
                                <div class="rc-empty">No coaches loaded for this school yet.</div>
                            @endforelse
                        </div>
                    </section>

                    <section class="rc-school-modal-section">
                        <div class="rc-school-section-title">Roster & stats</div>
                        <div class="rc-school-stat-grid">
                            <div class="rc-school-stat-card"><span>♙</span><strong>{{ number_format($slidePlayers) }}</strong><small>Players</small></div>
                            <div class="rc-school-stat-card"><span>◉</span><strong>{{ number_format($slideUpperclass) }}</strong><small>Upperclass</small></div>
                            <div class="rc-school-stat-card"><span>#</span><strong>{{ number_format($slideUnderclass) }}</strong><small>Underclass</small></div>
                        </div>
                    </section>

                    <section class="rc-school-modal-section">
                        <div class="rc-school-section-title">Communications</div>
                        <div class="rc-school-stat-grid">
                            <div class="rc-school-stat-card"><span>✉</span><strong>{{ number_format($slideEmails) }}</strong><small>Emails</small></div>
                            <div class="rc-school-stat-card"><span>↗</span><strong>{{ number_format($slideClicks) }}</strong><small>Clicks</small></div>
                            <div class="rc-school-stat-card"><span>↩</span><strong>{{ number_format($slideReplies) }}</strong><small>Replies</small></div>
                        </div>
                    </section>
                </div>
            </div>
        @endif
    </div>
    </div>

    <script>
        window.plyrNativeEditorBase = function (modelName, initialBody = '') {
            return {
                syncTimer: null,
                mounted: false,
                uploadingImages: false,
                editorNotice: '',
                activePanel: '',
                panelLinkLabel: '',
                panelLinkUrl: '',
                panelButtonLabel: '',
                panelButtonUrl: '',
                mount() {
                    if (this.mounted) return;
                    this.mounted = true;
                    this.$nextTick(() => this.bootEditor());
                },
                bootEditor() {
                    if (!this.$refs.editor) return;
                    const html = this.decodeInitialBody(initialBody || this.$refs.editor.dataset.initialBody || '');
                    if (html && this.$refs.editor.innerHTML.trim() === '') this.$refs.editor.innerHTML = html;
                    this.syncNow();
                },
                decodeInitialBody(initial) {
                    if (!initial) return '';
                    try { return decodeURIComponent(escape(window.atob(initial))); }
                    catch (error) { try { return window.atob(initial); } catch (_) { return ''; } }
                },
                queueSync() {
                    clearTimeout(this.syncTimer);
                    this.syncTimer = setTimeout(() => this.syncNow(), 250);
                },
                syncNow() {
                    if (!this.$refs.editor) return;
                    const html = this.$refs.editor.innerHTML || '';
                    if (modelName && this.$wire) this.$wire.set(modelName, html, true);
                },
                focusEditor() { this.$refs.editor?.focus(); },
                command(name, value = null) {
                    this.focusEditor();
                    document.execCommand(name, false, value);
                    this.syncNow();
                },
                block(tag) {
                    const safeTag = ['p', 'h1', 'h2', 'h3', 'blockquote'].includes(tag) ? tag : 'p';
                    this.command('formatBlock', safeTag);
                },
                escapeHtml(value) {
                    return String(value || '')
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                },
                cleanUrl(url) { return String(url || '').trim().replace(/["<>]/g, ''); },
                showNotice(message) {
                    this.editorNotice = String(message || '');
                    if (this.editorNotice) setTimeout(() => { this.editorNotice = ''; }, 4500);
                },
                closeEditorPanel() {
                    this.activePanel = '';
                },
                openLinkPanel() {
                    const selection = String(window.getSelection?.() || '').trim();
                    this.panelLinkLabel = selection || 'Profile link';
                    this.panelLinkUrl = this.mergeToken('ProfileLink');
                    this.activePanel = 'link';
                },
                applyLinkPanel() {
                    const url = this.cleanUrl(this.panelLinkUrl || this.mergeToken('ProfileLink'));
                    const label = String(this.panelLinkLabel || 'Profile link').trim();
                    if (!url || !label) return;
                    this.insertHtml('<a href="' + this.escapeHtml(url) + '" target="_blank">' + this.escapeHtml(label) + '</a>');
                    this.closeEditorPanel();
                },
                openButtonPanel() {
                    this.panelButtonLabel = 'View profile';
                    this.panelButtonUrl = this.mergeToken('ProfileLink');
                    this.activePanel = 'button';
                },
                applyButtonPanel() {
                    const url = this.cleanUrl(this.panelButtonUrl || this.mergeToken('ProfileLink'));
                    const label = String(this.panelButtonLabel || 'View profile').trim();
                    if (!url || !label) return;
                    this.insertHtml('<p><a class="rc-email-button" href="' + this.escapeHtml(url) + '" target="_blank" style="display:block;width:100%;box-sizing:border-box;text-align:center;">' + this.escapeHtml(label) + '</a></p>');
                    this.closeEditorPanel();
                },
                mergeToken(name) { return '{' + '{' + String(name || '').trim() + '}' + '}'; },
                insertHtml(html) {
                    this.focusEditor();
                    document.execCommand('insertHTML', false, html);
                    this.syncNow();
                },
                insertMerge(name) { this.insertHtml(this.escapeHtml(this.mergeToken(name))); },
                addLink() {
                    this.openLinkPanel();
                },
                addImage() {
                    this.showNotice('Use the Image button to upload an image inside the app.');
                },
                openImageUpload() {
                    if (!this.$refs.imageUpload) {
                        this.addImage();
                        return;
                    }
                    this.$refs.imageUpload.value = '';
                    this.$refs.imageUpload.click();
                },
                uploadInlineImages(event) {
                    const files = Array.from(event.target.files || []);
                    if (!files.length) return;

                    this.uploadingImages = true;
                    const uploadNext = (index = 0) => {
                        if (index >= files.length) {
                            this.uploadingImages = false;
                            event.target.value = '';
                            this.syncNow();
                            return;
                        }

                        const file = files[index];
                        this.$wire.upload('templateInlineImageUpload', file, () => {
                            this.$wire.call('uploadTemplateEditorImage').then((result) => {
                                if (result && result.success && result.url) {
                                    this.insertImage(result.url);
                                } else {
                                    this.showNotice((result && result.error) ? result.error : 'Image upload failed.');
                                }
                                uploadNext(index + 1);
                            }).catch((error) => {
                                console.error(error);
                                this.showNotice('Image upload failed.');
                                uploadNext(index + 1);
                            });
                        }, () => {
                            this.showNotice('Image upload failed.');
                            uploadNext(index + 1);
                        });
                    };

                    uploadNext();
                },
                insertImage(url) {
                    const clean = this.cleanUrl(url);
                    if (!clean) return;
                    this.insertHtml('<p><img src="' + this.escapeHtml(clean) + '" alt="Email image" style="width:100%;max-width:100%;height:auto;display:block;border-radius:12px;" /></p>');
                },
                addTable() {
                    this.insertHtml('<table style="width:100%;border-collapse:collapse;margin:12px 0;"><tr><td style="border:1px solid #e5e7eb;padding:8px;">Label</td><td style="border:1px solid #e5e7eb;padding:8px;">Value</td></tr><tr><td style="border:1px solid #e5e7eb;padding:8px;">School</td><td style="border:1px solid #e5e7eb;padding:8px;">' + this.escapeHtml(this.mergeToken('SchoolName')) + '</td></tr></table>');
                },
                addButton() {
                    this.openButtonPanel();
                }
            };
        };

        window.plyrCampaignBodyEditor = function () {
            return window.plyrNativeEditorBase('campaignBody');
        };

        window.plyrTemplateEditor = function () {
            return {
                showPreview: false,
                mounted: false,
                syncTimer: null,
                uploadingImages: false,
                editorNotice: '',
                activePanel: '',
                panelLinkLabel: '',
                panelLinkUrl: '',
                panelButtonLabel: '',
                panelButtonUrl: '',
                mount() {
                    if (this.mounted) return;
                    this.mounted = true;

                    this.$nextTick(() => this.bootEditor());

                    document.addEventListener('rc-open-template-preview', () => {
                        this.syncNow();
                        this.showPreview = true;
                    });

                    window.addEventListener('rc-template-editor-refresh', (event) => {
                        const encoded = event.detail?.body || '';
                        const html = this.decodeBodyValue(encoded);
                        if (this.$refs.editor) {
                            this.$refs.editor.innerHTML = html;
                            this.syncNow();
                        }
                    });
                },
                bootEditor() {
                    if (!this.$refs.editor) return;

                    const html = this.decodeInitialBody();
                    if (html && this.$refs.editor.innerHTML.trim() === '') {
                        this.$refs.editor.innerHTML = html;
                    }

                    this.syncNow();
                },
                decodeBodyValue(initial) {
                    if (!initial) return '';
                    try {
                        return decodeURIComponent(escape(window.atob(initial)));
                    } catch (error) {
                        try { return window.atob(initial); } catch (_) { return ''; }
                    }
                },
                decodeInitialBody() {
                    const initial = this.$refs.editor?.dataset?.initialBody || '';
                    return this.decodeBodyValue(initial);
                },
                queueSync() {
                    clearTimeout(this.syncTimer);
                    this.syncTimer = setTimeout(() => this.syncNow(), 250);
                },
                syncNow() {
                    if (!this.$refs.hidden || !this.$refs.editor) return;

                    const html = this.$refs.editor.innerHTML || '';
                    this.$refs.hidden.value = html;
                    this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                },
                focusEditor() {
                    this.$refs.editor?.focus();
                },
                command(name, value = null) {
                    this.focusEditor();
                    document.execCommand(name, false, value);
                    this.syncNow();
                },
                block(tag) {
                    const safeTag = ['p', 'h1', 'h2', 'h3', 'blockquote'].includes(tag) ? tag : 'p';
                    this.command('formatBlock', safeTag);
                },
                mergeToken(name) {
                    return '{' + '{' + String(name || '').trim() + '}' + '}';
                },
                insertHtml(html) {
                    this.focusEditor();
                    document.execCommand('insertHTML', false, html);
                    this.syncNow();
                },
                insertMerge(name) {
                    const token = this.mergeToken(name);
                    if (!token) return;
                    this.insertHtml(this.escapeHtml(token));
                },
                mergeTokenFromSelect(event) {
                    const select = event?.target;
                    const value = String(select?.value || '').trim();
                    if (select) select.value = '';
                    if (!value) return '';
                    if (value === '__custom__') {
                        this.showNotice('Inserted a custom value placeholder. Replace "your_value" with the exact field key.');
                        return this.mergeToken('custom_values.your_value');
                    }
                    return this.mergeToken(value);
                },
                insertMergeFromSelect(event) {
                    const token = this.mergeTokenFromSelect(event);
                    if (!token) return;
                    this.insertHtml(this.escapeHtml(token));
                },
                insertFieldMerge(refName, event) {
                    const token = this.mergeTokenFromSelect(event);
                    if (!token) return;
                    const input = this.$refs?.[refName];
                    if (!input) return;
                    input.focus();
                    const start = input.selectionStart ?? input.value.length;
                    const end = input.selectionEnd ?? input.value.length;
                    input.setRangeText(token, start, end, 'end');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                },
                mergeTokenFromSelect(event) {
                    const select = event?.target;
                    const value = String(select?.value || '').trim();
                    if (select) select.value = '';
                    if (!value) return '';
                    if (value === '__custom__') {
                        this.showNotice('Inserted a custom value placeholder. Replace "your_value" with the exact field key.');
                        return this.mergeToken('custom_values.your_value');
                    }
                    return this.mergeToken(value);
                },
                insertMergeFromSelect(event) {
                    const token = this.mergeTokenFromSelect(event);
                    if (!token) return;
                    this.insertHtml(this.escapeHtml(token));
                },
                insertFieldMerge(refName, event) {
                    const token = this.mergeTokenFromSelect(event);
                    if (!token) return;
                    const input = this.$refs?.[refName];
                    if (!input) return;
                    input.focus();
                    const start = input.selectionStart ?? input.value.length;
                    const end = input.selectionEnd ?? input.value.length;
                    input.setRangeText(token, start, end, 'end');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                },
                cleanUrl(url) {
                    return String(url || '').trim().replace(/["<>]/g, '');
                },
                showNotice(message) {
                    this.editorNotice = String(message || '');
                    if (this.editorNotice) setTimeout(() => { this.editorNotice = ''; }, 4500);
                },
                closeEditorPanel() {
                    this.activePanel = '';
                },
                openLinkPanel() {
                    const selection = String(window.getSelection?.() || '').trim();
                    this.panelLinkLabel = selection || 'Profile link';
                    this.panelLinkUrl = this.mergeToken('ProfileLink');
                    this.activePanel = 'link';
                },
                applyLinkPanel() {
                    const url = this.cleanUrl(this.panelLinkUrl || this.mergeToken('ProfileLink'));
                    const label = String(this.panelLinkLabel || 'Profile link').trim();
                    if (!url || !label) return;
                    this.insertHtml('<a href="' + this.escapeHtml(url) + '" target="_blank">' + this.escapeHtml(label) + '</a>');
                    this.closeEditorPanel();
                },
                openButtonPanel() {
                    this.panelButtonLabel = 'View profile';
                    this.panelButtonUrl = this.mergeToken('ProfileLink');
                    this.activePanel = 'button';
                },
                applyButtonPanel() {
                    const url = this.cleanUrl(this.panelButtonUrl || this.mergeToken('ProfileLink'));
                    const label = String(this.panelButtonLabel || 'View profile').trim();
                    if (!url || !label) return;
                    this.insertHtml('<p><a class="rc-email-button" href="' + this.escapeHtml(url) + '" target="_blank" style="display:block;width:100%;box-sizing:border-box;text-align:center;">' + this.escapeHtml(label) + '</a></p>');
                    this.closeEditorPanel();
                },
                escapeHtml(value) {
                    return String(value || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                },
                openImageUpload() {
                    if (!this.$refs.imageUpload) return;
                    this.$refs.imageUpload.value = '';
                    this.$refs.imageUpload.click();
                },
                uploadInlineImages(event) {
                    const files = Array.from(event.target.files || []);
                    if (!files.length) return;

                    this.uploadingImages = true;
                    const uploadNext = (index = 0) => {
                        if (index >= files.length) {
                            this.uploadingImages = false;
                            event.target.value = '';
                            this.syncNow();
                            return;
                        }

                        const file = files[index];
                        this.$wire.upload('templateInlineImageUpload', file, () => {
                            this.$wire.call('uploadTemplateEditorImage').then((result) => {
                                if (result && result.success && result.url) {
                                    this.insertImage(result.url);
                                } else {
                                    this.showNotice((result && result.error) ? result.error : 'Image upload failed.');
                                }
                                uploadNext(index + 1);
                            }).catch((error) => {
                                console.error(error);
                                this.showNotice('Image upload failed.');
                                uploadNext(index + 1);
                            });
                        }, () => {
                            this.showNotice('Image upload failed.');
                            uploadNext(index + 1);
                        });
                    };

                    uploadNext();
                },
                insertImage(url) {
                    const clean = this.cleanUrl(url);
                    if (!clean) return;
                    this.insertHtml('<p><img src="' + this.escapeHtml(clean) + '" style="width:100%;max-width:100%;height:auto;display:block;" alt="" /></p>');
                },
                addLink() {
                    this.openLinkPanel();
                },
                addButton() {
                    this.openButtonPanel();
                },
                addTable() {
                    this.insertHtml('<table style="width:100%;border-collapse:collapse;margin:12px 0;"><tr><td style="border:1px solid #e5e7eb;padding:8px;">Label</td><td style="border:1px solid #e5e7eb;padding:8px;">Value</td></tr><tr><td style="border:1px solid #e5e7eb;padding:8px;">School</td><td style="border:1px solid #e5e7eb;padding:8px;">' + this.escapeHtml(this.mergeToken('SchoolName')) + '</td></tr></table>');
                },
                previewSubject() {
                    return this.$refs.subject?.value || 'Subject preview';
                },
                previewGraphic() {
                    return '';
                },
                previewHtml() {
                    return this.$refs.editor ? this.$refs.editor.innerHTML : '';
                }
            };
        };
    </script>
</x-filament-panels::page>