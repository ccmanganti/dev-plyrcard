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



        /* v80 PLYRCard recruiting dashboard redesign: Filament light/dark aware */
        .rc-home-dashboard {
            display: grid;
            gap: 1.35rem;
            color: #0f172a;
            padding: .15rem 0 1rem;
        }
        .dark .rc-home-dashboard { color: #f8fafc; }
        .rc-home-topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .55rem;
        }
        .rc-home-topbar h1 {
            margin: 0;
            font-size: clamp(1.35rem, 2.7vw, 2rem);
            line-height: 1.1;
            letter-spacing: -.04em;
            font-weight: 900;
            color: #0f172a;
        }
        .dark .rc-home-topbar h1 { color: #fff; }
        .rc-home-topbar p,
        .rc-home-panel-head p {
            margin: .28rem 0 0;
            color: #7c8799;
            font-size: .86rem;
        }
        .dark .rc-home-topbar p,
        .dark .rc-home-panel-head p { color: #94a3b8; }
        .rc-home-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .7rem;
            flex-wrap: wrap;
        }
        .rc-home-search {
            min-width: min(28rem, 48vw);
            height: 2.65rem;
            display: flex;
            align-items: center;
            gap: .55rem;
            border: 1px solid #e5e7eb;
            border-radius: .85rem;
            background: rgba(255,255,255,.92);
            color: #94a3b8;
            padding: 0 .75rem;
            box-shadow: 0 8px 24px rgba(15,23,42,.08);
        }
        .dark .rc-home-search {
            border-color: rgba(148,163,184,.18);
            background: rgba(17,24,39,.82);
            box-shadow: none;
        }
        .rc-home-search input {
            border: 0;
            outline: 0;
            box-shadow: none !important;
            background: transparent;
            min-width: 0;
            flex: 1;
            font-size: .84rem;
            color: inherit;
        }
        .rc-home-search kbd {
            border: 1px solid #e5e7eb;
            border-radius: .45rem;
            padding: .08rem .36rem;
            color: #94a3b8;
            font-size: .7rem;
            font-weight: 700;
        }
        .dark .rc-home-search kbd { border-color: rgba(148,163,184,.2); }
        .rc-home-icon-btn,
        .rc-home-new-email,
        .rc-home-panel-head a,
        .rc-home-outline-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            border-radius: .85rem;
            min-height: 2.45rem;
            padding: .55rem .85rem;
            background: #fff;
            color: #0f172a;
            font-size: .82rem;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(15,23,42,.06);
        }
        .dark .rc-home-icon-btn,
        .dark .rc-home-panel-head a,
        .dark .rc-home-outline-btn {
            border-color: rgba(148,163,184,.18);
            background: rgba(17,24,39,.72);
            color: #e5e7eb;
            box-shadow: none;
        }
        .rc-home-new-email {
            background: #ff6338;
            border-color: #ff6338;
            color: #fff;
            box-shadow: 0 12px 24px rgba(255,99,56,.25);
        }
        .rc-home-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
        }
        .rc-home-stat-card,
        .rc-home-panel {
            border: 1px solid #e7eaf0;
            background: rgba(255,255,255,.96);
            border-radius: 1.1rem;
            box-shadow: 0 8px 22px rgba(15,23,42,.07);
        }
        .dark .rc-home-stat-card,
        .dark .rc-home-panel {
            border-color: rgba(148,163,184,.16);
            background: rgba(24,29,39,.88);
            box-shadow: none;
        }
        .rc-home-stat-card {
            min-height: 7.6rem;
            padding: 1rem;
            display: grid;
            grid-template-columns: 2.65rem minmax(0,1fr);
            align-content: start;
            gap: .55rem .8rem;
        }
        .rc-home-stat-icon,
        .rc-home-activity-icon {
            width: 2.65rem;
            height: 2.65rem;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            font-weight: 900;
            line-height: 1;
            flex: 0 0 auto;
        }
        .rc-home-stat-card.is-coral .rc-home-stat-icon { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-home-stat-card.is-blue .rc-home-stat-icon { background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-home-stat-card.is-gold .rc-home-stat-icon { background: rgba(245,158,11,.14); color: #f59e0b; }
        .rc-home-stat-card.is-green .rc-home-stat-icon { background: rgba(16,185,129,.13); color: #10b981; }
        .rc-home-stat-card.is-indigo .rc-home-stat-icon { background: rgba(96,165,250,.14); color: #60a5fa; }
        .rc-home-stat-copy { min-width: 0; }
        .rc-home-stat-label { color: #7c8799; font-size: .78rem; font-weight: 800; }
        .dark .rc-home-stat-label { color: #94a3b8; }
        .rc-home-stat-value { color: #0f172a; font-size: 1.45rem; line-height: 1; font-weight: 950; letter-spacing: -.04em; margin-top: .15rem; }
        .dark .rc-home-stat-value { color: #fff; }
        .rc-home-progress {
            grid-column: 1 / -1;
            height: .42rem;
            background: #eef1f6;
            border-radius: 999px;
            overflow: hidden;
            margin-top: .2rem;
        }
        .dark .rc-home-progress { background: rgba(148,163,184,.16); }
        .rc-home-progress span { display: block; height: 100%; border-radius: inherit; background: #ff6338; }
        .rc-home-stat-sub {
            grid-column: 1 / -1;
            color: #7c8799;
            font-size: .76rem;
        }
        .rc-home-stat-card.is-blue .rc-home-stat-sub,
        .rc-home-stat-card.is-green .rc-home-stat-sub { color: #059669; font-weight: 800; }
        .dark .rc-home-stat-sub { color: #94a3b8; }
        .dark .rc-home-stat-card.is-blue .rc-home-stat-sub,
        .dark .rc-home-stat-card.is-green .rc-home-stat-sub { color: #34d399; }
        .rc-home-main-grid,
        .rc-home-lower-grid {
            display: grid;
            grid-template-columns: minmax(0,1fr) minmax(320px,.82fr);
            gap: 1rem;
        }
        .rc-home-panel { padding: 1.2rem; }
        .rc-home-panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .rc-home-panel-head h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1rem;
            line-height: 1.15;
            font-weight: 900;
            letter-spacing: -.025em;
        }
        .dark .rc-home-panel-head h2 { color: #fff; }
        .rc-progress-layout {
            display: grid;
            grid-template-columns: 12.5rem minmax(0,1fr);
            gap: 1.35rem;
            align-items: center;
        }
        .rc-readiness-ring {
            width: 9.7rem;
            height: 9.7rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: conic-gradient(#ff6338 calc(var(--ready) * 1%), #edf0f5 0);
            margin-inline: auto;
            position: relative;
        }
        .dark .rc-readiness-ring { background: conic-gradient(#ff6f51 calc(var(--ready) * 1%), rgba(148,163,184,.18) 0); }
        .rc-readiness-ring:before {
            content: "";
            position: absolute;
            inset: 1rem;
            border-radius: inherit;
            background: #fff;
        }
        .dark .rc-readiness-ring:before { background: #181d27; }
        .rc-readiness-ring div { position: relative; display: grid; justify-items: center; gap: .25rem; text-align: center; }
        .rc-readiness-ring strong { font-size: 1.75rem; line-height: 1; color: #0f172a; font-weight: 950; }
        .dark .rc-readiness-ring strong { color: #fff; }
        .rc-readiness-ring span { color: #7c8799; font-size: .75rem; }
        .rc-check-list { display: grid; gap: .78rem; }
        .rc-check-row { display: grid; grid-template-columns: 1.35rem minmax(0,1fr); gap: .65rem; align-items: start; }
        .rc-check-dot { width: 1.05rem; height: 1.05rem; border-radius: 999px; border: 2px solid #94a3b8; display: grid; place-items: center; color: #10b981; font-size: .72rem; font-weight: 950; }
        .rc-check-row.is-done .rc-check-dot { border-color: #10b981; }
        .rc-check-row strong { display: block; color: #0f172a; font-size: .86rem; line-height: 1.2; }
        .dark .rc-check-row strong { color: #fff; }
        .rc-check-row small { display: block; color: #7c8799; font-size: .78rem; margin-top: .15rem; }
        .rc-home-outline-btn { width: 100%; margin-top: .25rem; }
        .rc-home-activity-list { display: grid; gap: .78rem; max-height: 20rem; overflow: auto; padding-right: .25rem; }
        .rc-home-activity { display: grid; grid-template-columns: 2.35rem minmax(0,1fr) auto; gap: .75rem; align-items: center; text-decoration: none; color: inherit; }
        .rc-home-activity-icon { width: 2.05rem; height: 2.05rem; font-size: .8rem; background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-home-activity-icon.is-coral { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-home-activity-icon.is-green { background: rgba(16,185,129,.13); color: #10b981; }
        .rc-home-activity-copy { min-width: 0; display: grid; gap: .12rem; }
        .rc-home-activity-copy strong { color: #0f172a; font-size: .84rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dark .rc-home-activity-copy strong { color: #fff; }
        .rc-home-activity-copy small { color: #7c8799; font-size: .76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rc-home-activity-time { color: #94a3b8; font-size: .74rem; white-space: nowrap; }
        .rc-radar-panel { grid-column: 1; }
        .rc-radar-schools { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .85rem; }
        .rc-radar-card {
            border: 1px solid #e7eaf0;
            background: #fff;
            border-radius: .9rem;
            overflow: hidden;
            padding: 0 0 .8rem;
            text-align: left;
            display: grid;
            gap: .28rem;
            color: #0f172a;
        }
        .dark .rc-radar-card { border-color: rgba(148,163,184,.16); background: rgba(17,24,39,.72); color: #fff; }
        .rc-radar-logo { height: 5.25rem; display: grid; place-items: center; background: #f3f4f6; color: #0f172a; font-weight: 950; font-size: 1.15rem; }
        .dark .rc-radar-logo { background: #fff; color: #111827; }
        .rc-radar-card strong, .rc-radar-card small, .rc-radar-card em { margin-inline: .8rem; }
        .rc-radar-card strong { font-size: .84rem; line-height: 1.15; }
        .rc-radar-card small { color: #7c8799; font-size: .73rem; }
        .rc-radar-card em { width: max-content; border-radius: 999px; background: rgba(16,185,129,.12); color: #059669; padding: .22rem .48rem; font-size: .7rem; font-style: normal; font-weight: 900; margin-top: .25rem; }
        .rc-interested-list { display: grid; gap: .85rem; }
        .rc-interested-row { display: grid; grid-template-columns: 1.1rem 2.35rem minmax(0,1fr) auto; gap: .75rem; align-items: center; border: 0; background: transparent; text-align: left; color: inherit; padding: 0; }
        .rc-interested-rank { color: #94a3b8; font-weight: 900; font-size: .82rem; }
        .rc-interested-logo { width: 2.35rem; height: 2.35rem; border-radius: .55rem; display: grid; place-items: center; background: #fff; color: #111827; border: 1px solid #e5e7eb; font-size: .72rem; font-weight: 950; }
        .rc-interested-row strong { display: block; color: #0f172a; font-size: .84rem; line-height: 1.2; }
        .dark .rc-interested-row strong { color: #fff; }
        .rc-interested-row small { color: #7c8799; font-size: .73rem; }
        .rc-interested-row b { color: #ff6338; font-weight: 950; }
        .rc-home-empty { color: #7c8799; font-size: .86rem; padding: 1rem; }
        .dark .rc-home-empty { color: #94a3b8; }
        @media (max-width: 1180px) {
            .rc-home-stats { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .rc-home-main-grid, .rc-home-lower-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .rc-home-topbar { display: grid; }
            .rc-home-search { min-width: 0; width: 100%; }
            .rc-home-actions { justify-content: stretch; }
            .rc-home-stats, .rc-radar-schools { grid-template-columns: 1fr; }
            .rc-progress-layout { grid-template-columns: 1fr; }
        }



        /* FINAL v90 light recruiting dashboard */
        .rc-home-dashboard-v2 {
            display: grid;
            gap: 1.25rem;
            padding: .3rem 0 2rem;
            color: #101827;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
        }

        .rc-home-dashboard-v2 * { box-sizing: border-box; }

        .rc-home-header-v2 {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.1rem;
        }

        .rc-home-header-v2 h1 {
            margin: 0;
            color: #101827;
            font-size: clamp(1.45rem, 2vw, 1.85rem);
            line-height: 1.05;
            font-weight: 850;
            letter-spacing: -.035em;
        }

        .rc-home-header-v2 p {
            margin: .5rem 0 0;
            color: #7d8798;
            font-size: .88rem;
        }

        .rc-home-actions-v2 {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: .72rem;
        }

        .rc-home-search-v2 {
            width: min(28rem, 42vw);
            height: 2.75rem;
            display: flex;
            align-items: center;
            gap: .55rem;
            border: 1px solid #e8ebf0;
            border-radius: .82rem;
            background: rgba(255,255,255,.94);
            padding: 0 .72rem;
            color: #9aa4b5;
            box-shadow: 0 8px 20px rgba(15,23,42,.07);
        }

        .rc-home-search-v2 svg {
            width: 1.05rem;
            height: 1.05rem;
            flex: 0 0 auto;
        }

        .rc-home-search-v2 input {
            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
            color: #475569;
            min-width: 0;
            flex: 1;
            font-size: .84rem;
        }

        .rc-home-search-v2 kbd {
            border: 1px solid #e5e7eb;
            border-radius: .42rem;
            color: #94a3b8;
            font-size: .68rem;
            font-weight: 650;
            padding: .08rem .35rem;
        }

        .rc-home-new-email-v2 {
            height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            border: 0;
            border-radius: .85rem;
            background: #ff6338;
            color: #fff !important;
            padding: 0 1.15rem;
            font-size: .88rem;
            font-weight: 750;
            text-decoration: none;
            box-shadow: 0 12px 22px rgba(255,99,56,.24);
        }

        .rc-home-new-email-v2 span {
            font-size: 1.15rem;
            line-height: 1;
            font-weight: 500;
        }

        .rc-home-stats-v2 {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
        }

        .rc-home-stat-v2,
        .rc-home-panel-v2 {
            border: 1px solid #e8ebf0;
            background: rgba(255,255,255,.96);
            border-radius: 1.05rem;
            box-shadow: 0 8px 22px rgba(15,23,42,.07);
        }

        .rc-home-stat-v2 {
            min-height: 7.75rem;
            padding: 1.05rem;
            display: grid;
            grid-template-columns: 2.65rem minmax(0,1fr);
            gap: .45rem .85rem;
            align-content: start;
        }

        .rc-home-stat-icon-v2 {
            width: 2.65rem;
            height: 2.65rem;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            flex: 0 0 auto;
        }

        .rc-home-stat-icon-v2 svg {
            width: 1.22rem;
            height: 1.22rem;
        }

        .rc-home-stat-v2.is-coral .rc-home-stat-icon-v2 { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-home-stat-v2.is-blue .rc-home-stat-icon-v2 { background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-home-stat-v2.is-gold .rc-home-stat-icon-v2 { background: rgba(245,158,11,.14); color: #f59e0b; }
        .rc-home-stat-v2.is-green .rc-home-stat-icon-v2 { background: rgba(16,185,129,.13); color: #10b981; }
        .rc-home-stat-v2.is-indigo .rc-home-stat-icon-v2 { background: rgba(96,165,250,.14); color: #60a5fa; }

        .rc-home-stat-copy-v2 {
            min-width: 0;
            align-self: center;
        }

        .rc-home-stat-label-v2 {
            color: #7d8798;
            font-size: .78rem;
            line-height: 1.1;
            font-weight: 750;
        }

        .rc-home-stat-value-v2 {
            margin-top: .18rem;
            color: #0f172a;
            font-size: 1.65rem;
            line-height: .95;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .rc-home-progress-v2 {
            grid-column: 1 / -1;
            height: .42rem;
            border-radius: 999px;
            background: #edf0f5;
            overflow: hidden;
            margin-top: .15rem;
        }

        .rc-home-progress-v2 span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #ff6338;
        }

        .rc-home-stat-sub-v2 {
            grid-column: 1 / -1;
            color: #7d8798;
            font-size: .76rem;
            line-height: 1.25;
        }

        .rc-home-stat-v2.is-blue .rc-home-stat-sub-v2,
        .rc-home-stat-v2.is-green .rc-home-stat-sub-v2 {
            color: #059669;
            font-weight: 750;
        }

        .rc-home-grid-v2,
        .rc-home-lower-grid-v2 {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, .82fr);
            gap: 1rem;
        }

        .rc-home-panel-v2 { padding: 1.2rem; }

        .rc-home-panel-head-v2 {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.05rem;
        }

        .rc-home-panel-head-v2 h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1rem;
            line-height: 1.15;
            font-weight: 850;
            letter-spacing: -.02em;
        }

        .rc-home-panel-head-v2 p {
            margin: .35rem 0 0;
            color: #7d8798;
            font-size: .78rem;
        }

        .rc-home-panel-head-v2 a,
        .rc-home-panel-head-v2 span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e8ebf0;
            border-radius: .75rem;
            min-height: 2.15rem;
            padding: .45rem .75rem;
            background: #fff;
            color: #0f172a;
            font-size: .78rem;
            font-weight: 700;
            text-decoration: none;
        }

        .rc-home-progress-layout-v2 {
            display: grid;
            grid-template-columns: 12rem minmax(0,1fr);
            gap: 1.35rem;
            align-items: center;
        }

        .rc-readiness-ring-v2 {
            width: 9.7rem;
            height: 9.7rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            margin-inline: auto;
            background: conic-gradient(#ff6338 calc(var(--ready) * 1%), #edf0f5 0);
            position: relative;
        }

        .rc-readiness-ring-v2:before {
            content: "";
            position: absolute;
            inset: 1rem;
            border-radius: inherit;
            background: #fff;
        }

        .rc-readiness-ring-v2 div {
            position: relative;
            display: grid;
            justify-items: center;
            gap: .25rem;
            text-align: center;
        }

        .rc-readiness-ring-v2 strong {
            color: #0f172a;
            font-size: 1.75rem;
            line-height: 1;
            font-weight: 900;
        }

        .rc-readiness-ring-v2 span {
            color: #7d8798;
            font-size: .75rem;
        }

        .rc-check-list-v2 { display: grid; gap: .78rem; }

        .rc-check-row-v2 {
            display: grid;
            grid-template-columns: 1.35rem minmax(0,1fr);
            align-items: start;
            gap: .65rem;
        }

        .rc-check-dot-v2 {
            width: 1.05rem;
            height: 1.05rem;
            border-radius: 999px;
            border: 2px solid #94a3b8;
            display: grid;
            place-items: center;
            color: #10b981;
            font-size: .72rem;
            line-height: 1;
            font-weight: 900;
        }

        .rc-check-row-v2.is-done .rc-check-dot-v2 { border-color: #10b981; }

        .rc-check-row-v2 strong {
            display: block;
            color: #0f172a;
            font-size: .86rem;
            line-height: 1.15;
            font-weight: 780;
        }

        .rc-check-row-v2 small {
            display: block;
            color: #7d8798;
            font-size: .78rem;
            margin-top: .15rem;
        }

        .rc-home-outline-btn-v2 {
            width: 100%;
            min-height: 2.35rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e8ebf0;
            border-radius: .72rem;
            background: #fff;
            color: #0f172a;
            font-size: .78rem;
            font-weight: 720;
            text-decoration: none;
            margin-top: .25rem;
        }

        .rc-home-activity-list-v2 {
            display: grid;
            gap: .82rem;
            max-height: 20rem;
            overflow: auto;
            padding-right: .25rem;
        }

        .rc-home-activity-v2 {
            display: grid;
            grid-template-columns: 2.35rem minmax(0,1fr) auto;
            gap: .75rem;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }

        .rc-home-activity-icon-v2 {
            width: 2.05rem;
            height: 2.05rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-size: .78rem;
            font-weight: 800;
        }

        .rc-home-activity-icon-v2.is-blue { background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-home-activity-icon-v2.is-coral { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-home-activity-icon-v2.is-gold { background: rgba(245,158,11,.14); color: #f59e0b; }
        .rc-home-activity-icon-v2.is-green { background: rgba(16,185,129,.13); color: #10b981; }
        .rc-home-activity-icon-v2.is-purple { background: rgba(139,92,246,.13); color: #8b5cf6; }

        .rc-home-activity-copy-v2 { display: grid; gap: .12rem; min-width: 0; }

        .rc-home-activity-copy-v2 strong {
            color: #0f172a;
            font-size: .84rem;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 800;
        }

        .rc-home-activity-copy-v2 small {
            color: #7d8798;
            font-size: .76rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-home-activity-time-v2 {
            color: #94a3b8;
            font-size: .74rem;
            white-space: nowrap;
        }

        .rc-radar-panel-v2 { grid-column: 1; }

        .rc-radar-schools-v2 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: .85rem;
        }

        .rc-radar-card-v2 {
            border: 1px solid #e8ebf0;
            background: #fff;
            border-radius: .9rem;
            overflow: hidden;
            padding: 0 0 .8rem;
            text-align: left;
            display: grid;
            gap: .28rem;
            color: #0f172a;
            cursor: pointer;
        }

        .rc-radar-logo-v2 {
            height: 5.25rem;
            display: grid;
            place-items: center;
            background: #f3f4f6;
            color: #0f172a;
            font-weight: 900;
            font-size: 1.15rem;
        }

        .rc-radar-card-v2 strong,
        .rc-radar-card-v2 small,
        .rc-radar-card-v2 em { margin-inline: .8rem; }

        .rc-radar-card-v2 strong {
            color: #0f172a;
            font-size: .84rem;
            line-height: 1.15;
            font-weight: 800;
        }

        .rc-radar-card-v2 small { color: #7d8798; font-size: .73rem; }

        .rc-radar-card-v2 em {
            width: max-content;
            border-radius: 999px;
            background: rgba(16,185,129,.12);
            color: #059669;
            padding: .22rem .48rem;
            font-size: .7rem;
            font-style: normal;
            font-weight: 850;
            margin-top: .25rem;
        }

        .rc-home-dots-v2 {
            display: flex;
            justify-content: center;
            gap: .32rem;
            margin-top: .8rem;
        }

        .rc-home-dots-v2 span {
            width: .35rem;
            height: .35rem;
            border-radius: 999px;
            background: #d9dde5;
        }

        .rc-home-dots-v2 span:first-child {
            width: .75rem;
            background: #ff6338;
        }

        .rc-interested-list-v2 { display: grid; gap: .85rem; }

        .rc-interested-row-v2 {
            display: grid;
            grid-template-columns: 1.1rem 2.35rem minmax(0,1fr) auto;
            align-items: center;
            gap: .75rem;
            border: 0;
            background: transparent;
            text-align: left;
            color: inherit;
            padding: 0;
            cursor: pointer;
        }

        .rc-interested-rank-v2 {
            color: #94a3b8;
            font-weight: 850;
            font-size: .82rem;
        }

        .rc-interested-logo-v2 {
            width: 2.35rem;
            height: 2.35rem;
            border-radius: .55rem;
            display: grid;
            place-items: center;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #111827;
            font-size: .72rem;
            font-weight: 850;
        }

        .rc-interested-row-v2 strong {
            display: block;
            color: #0f172a;
            font-size: .84rem;
            line-height: 1.2;
            font-weight: 800;
        }

        .rc-interested-row-v2 small { color: #7d8798; font-size: .73rem; }
        .rc-interested-row-v2 b { color: #ff6338; font-weight: 900; }
        .rc-home-empty-v2 { color: #7d8798; font-size: .86rem; padding: 1rem; }

        .dark .rc-home-dashboard-v2 { color: #f8fafc; }

        .dark .rc-home-header-v2 h1,
        .dark .rc-home-panel-head-v2 h2,
        .dark .rc-home-stat-value-v2,
        .dark .rc-check-row-v2 strong,
        .dark .rc-readiness-ring-v2 strong,
        .dark .rc-home-activity-copy-v2 strong,
        .dark .rc-interested-row-v2 strong,
        .dark .rc-radar-card-v2 strong { color: #fff; }

        .dark .rc-home-stat-v2,
        .dark .rc-home-panel-v2,
        .dark .rc-home-search-v2,
        .dark .rc-home-panel-head-v2 a,
        .dark .rc-home-outline-btn-v2 {
            border-color: rgba(148,163,184,.16);
            background: rgba(24,29,39,.88);
            box-shadow: none;
            color: #e5e7eb;
        }

        .dark .rc-readiness-ring-v2:before { background: #181d27; }

        .dark .rc-radar-card-v2 {
            border-color: rgba(148,163,184,.16);
            background: rgba(17,24,39,.72);
        }

        .dark .rc-radar-logo-v2 {
            background: #fff;
            color: #111827;
        }

        @media (max-width: 1180px) {
            .rc-home-stats-v2 { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .rc-home-grid-v2,
            .rc-home-lower-grid-v2 { grid-template-columns: 1fr; }
        }

        @media (max-width: 760px) {
            .rc-home-header-v2 { display: grid; }
            .rc-home-actions-v2 { justify-content: stretch; }
            .rc-home-search-v2 { width: 100%; }
            .rc-home-stats-v2,
            .rc-radar-schools-v2 { grid-template-columns: 1fr; }
            .rc-home-progress-layout-v2 { grid-template-columns: 1fr; }
        }


        /* Dashboard functional-card + detail page fixes */
        .rc-home-header-v2 {
            grid-template-columns: minmax(26rem, 1fr) minmax(34rem, auto) !important;
            align-items: start !important;
        }

        .rc-home-actions-v2 {
            display: grid !important;
            grid-template-columns: minmax(21rem, 27rem) auto !important;
            align-items: center !important;
            justify-content: end !important;
            gap: .75rem !important;
            width: auto !important;
        }

        .rc-home-search-v2 {
            width: 100% !important;
            min-width: 0 !important;
        }

        .rc-home-new-email-v2 {
            width: auto !important;
            min-width: 7.6rem !important;
            max-width: 8.8rem !important;
            padding-inline: 1rem !important;
            white-space: nowrap !important;
        }

        .rc-home-stat-v2 {
            border: 1px solid #e8ebf0;
            text-align: left;
            color: inherit;
        }

        button.rc-home-stat-v2 {
            cursor: default;
            appearance: none;
        }

        .rc-home-stat-v2.is-clickable {
            cursor: pointer;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .rc-home-stat-v2.is-clickable:hover {
            border-color: #ff6338 !important;
            box-shadow: 0 0 0 3px rgba(255, 99, 56, .12), 0 12px 28px rgba(15, 23, 42, .08) !important;
            transform: translateY(-1px);
        }

        .rc-detail-page-v2 {
            display: grid;
            gap: 1rem;
            color: #101827;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
        }

        .rc-detail-header-v2 {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(22rem, 28rem);
            gap: 1rem;
            align-items: start;
        }

        .rc-detail-header-v2 h1 {
            margin: 0;
            color: #0f172a;
            font-size: 1.8rem;
            line-height: 1.1;
            font-weight: 850;
            letter-spacing: -.035em;
        }

        .rc-detail-header-v2 p {
            margin: .45rem 0 0;
            color: #7d8798;
            font-size: .95rem;
        }

        .rc-detail-search-v2 {
            height: 2.8rem;
            display: flex;
            align-items: center;
            gap: .55rem;
            border: 1px solid #e8ebf0;
            border-radius: .85rem;
            background: #fff;
            padding: 0 .8rem;
            color: #94a3b8;
            box-shadow: 0 8px 20px rgba(15,23,42,.06);
        }

        .rc-detail-search-v2 svg { width: 1rem; height: 1rem; flex: 0 0 auto; }
        .rc-detail-search-v2 input {
            min-width: 0;
            flex: 1;
            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
            font-size: .85rem;
            color: #475569;
        }

        .rc-detail-stats-v2 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .rc-detail-stat-v2,
        .rc-detail-table-v2 {
            border: 1px solid #e8ebf0;
            background: rgba(255,255,255,.96);
            border-radius: 1.05rem;
            box-shadow: 0 8px 22px rgba(15,23,42,.06);
        }

        .rc-detail-stat-v2 {
            min-height: 7.5rem;
            padding: 1.05rem;
            display: grid;
            grid-template-columns: 3rem minmax(0,1fr);
            gap: .8rem;
            align-items: start;
        }

        .rc-detail-stat-v2 > span {
            width: 3rem;
            height: 3rem;
            border-radius: .85rem;
            display: grid;
            place-items: center;
            font-weight: 850;
        }

        .rc-detail-stat-v2 small {
            display: block;
            color: #64748b;
            font-size: .84rem;
            font-weight: 700;
        }

        .rc-detail-stat-v2 strong {
            display: block;
            margin-top: .2rem;
            color: #0f172a;
            font-size: 2rem;
            line-height: 1;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .rc-detail-stat-v2 em {
            display: block;
            margin-top: .55rem;
            color: #7d8798;
            font-size: .82rem;
            font-style: normal;
        }

        .rc-detail-stat-v2.is-blue > span { background: rgba(59,130,246,.13); color: #3b82f6; }
        .rc-detail-stat-v2.is-coral > span { background: rgba(255,99,56,.13); color: #ff6338; }
        .rc-detail-stat-v2.is-purple > span { background: rgba(139,92,246,.13); color: #8b5cf6; }
        .rc-detail-stat-v2.is-neutral > span { background: #eceef3; color: #111827; }
        .rc-detail-stat-v2.is-pink > span { background: rgba(236,72,153,.14); color: #ec4899; }
        .rc-detail-stat-v2.is-red > span { background: rgba(239,68,68,.13); color: #ef4444; }

        .rc-detail-table-v2 { overflow: hidden; }
        .rc-detail-table-v2 header {
            min-height: 3.55rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0 1.15rem;
            border-bottom: 1px solid #edf0f5;
        }

        .rc-detail-table-v2 h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 850;
        }

        .rc-detail-table-v2 header span {
            color: #10b981;
            font-size: .78rem;
            font-weight: 750;
        }

        .rc-detail-rows-v2 { display: grid; }
        .rc-detail-row-v2 {
            width: 100%;
            min-height: 4.35rem;
            display: grid;
            grid-template-columns: 2rem 2.45rem minmax(0, 1fr) auto 3rem 4.75rem 1rem;
            align-items: center;
            gap: .75rem;
            border: 0;
            border-bottom: 1px solid #f0f2f6;
            background: transparent;
            padding: .65rem 1.15rem;
            text-align: left;
            color: inherit;
            cursor: pointer;
        }

        .rc-detail-row-v2:hover { background: #fafafa; }
        .rc-detail-row-v2:last-child { border-bottom: 0; }
        .rc-detail-rank-v2 { color: #94a3b8; font-size: .8rem; font-weight: 800; }
        .rc-detail-avatar-v2,
        .rc-detail-platform-icon-v2 {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: .7rem;
            display: grid;
            place-items: center;
            background: #f1f3f7;
            color: #111827;
            font-size: .86rem;
            font-weight: 800;
            overflow: hidden;
        }

        .rc-detail-avatar-v2 img { width: 100%; height: 100%; object-fit: contain; }
        .rc-detail-platform-icon-v2.is-red { background: rgba(239,68,68,.12); color: #ef4444; }
        .rc-detail-platform-icon-v2.is-pink { background: rgba(236,72,153,.14); color: #ec4899; }
        .rc-detail-platform-icon-v2.is-neutral { background: #eceef3; color: #111827; }

        .rc-detail-person-v2 { min-width: 0; display: grid; gap: .15rem; }
        .rc-detail-person-v2 strong {
            color: #0f172a;
            font-size: .88rem;
            line-height: 1.2;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-detail-person-v2 strong em {
            margin-left: .35rem;
            border-radius: .35rem;
            background: rgba(255,99,56,.13);
            color: #ff6338;
            padding: .08rem .28rem;
            font-size: .62rem;
            font-style: normal;
            font-weight: 800;
            vertical-align: middle;
        }

        .rc-detail-person-v2 small {
            color: #7d8798;
            font-size: .78rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rc-detail-pill-v2 {
            border-radius: 999px;
            background: rgba(255,99,56,.12);
            color: #ff6338;
            padding: .28rem .55rem;
            font-size: .72rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .rc-detail-pill-v2.is-pink { background: rgba(236,72,153,.14); color: #ec4899; }
        .rc-detail-pill-v2.is-red { background: rgba(239,68,68,.13); color: #ef4444; }
        .rc-detail-pill-v2.is-neutral { background: #eceef3; color: #111827; }

        .rc-detail-count-v2 { display: grid; justify-items: center; color: #7d8798; }
        .rc-detail-count-v2 b { color: #ff6338; font-size: 1.1rem; line-height: 1; font-weight: 900; }
        .rc-detail-count-v2 small { font-size: .68rem; }
        .rc-detail-time-v2 { color: #94a3b8; font-size: .78rem; white-space: nowrap; }
        .rc-detail-chevron-v2 { color: #94a3b8; font-size: 1.35rem; }

        @media (max-width: 1180px) {
            .rc-home-header-v2,
            .rc-detail-header-v2 { grid-template-columns: 1fr !important; }
            .rc-home-actions-v2 { justify-content: stretch !important; grid-template-columns: 1fr auto !important; }
            .rc-detail-stats-v2 { grid-template-columns: 1fr; }
            .rc-detail-row-v2 { grid-template-columns: 2.35rem minmax(0, 1fr) auto; }
            .rc-detail-rank-v2, .rc-detail-time-v2, .rc-detail-chevron-v2 { display: none; }
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
                $dashboardTopSchools = collect($this->dashboardTopEngagedSchools ?? [])->take(5)->values()->all();
                $dashboardRecentActivity = collect($this->dashboardRecentActivity ?? [])->values()->all();

                $authUser = auth()->user();
                $athleteName = trim((string) ($authUser?->name ?? 'Alex Johnson'));
                $firstName = trim(strtok($athleteName ?: 'Alex', ' '));

                $savedSchools = (int) ($dashboardMetrics['saved_schools'] ?? 0);
                $favoriteSchools = (int) ($dashboardMetrics['favorite_schools'] ?? $savedSchools);

                // GHL tracking custom-field metrics. These keys are produced by the updated
                // GoHighLevelService / CoachDatabaseService tracking flow.
                $trackedWebsiteViews = (int) ($dashboardMetrics['view_profile_website'] ?? $dashboardMetrics['website_clicks'] ?? 0);
                $trackedInstagramViews = (int) ($dashboardMetrics['view_profile_instagram'] ?? $dashboardMetrics['instagram_clicks'] ?? 0);
                $trackedYoutubeViews = (int) ($dashboardMetrics['view_profile_youtube'] ?? $dashboardMetrics['youtube_clicks'] ?? 0);
                $trackedXViews = (int) ($dashboardMetrics['view_profile_x'] ?? $dashboardMetrics['x_clicks'] ?? $dashboardMetrics['twitter_clicks'] ?? 0);
                $trackedEmailLinkViews = (int) ($dashboardMetrics['view_profile_email_link'] ?? 0);
                $trackedProfileTotal = (int) ($dashboardMetrics['view_profile_total'] ?? 0);
                $profileViews = $trackedProfileTotal > 0
                    ? $trackedProfileTotal
                    : max(0, (int) ($dashboardMetrics['profile_views'] ?? 0));

                $emailSentCount = (int) ($dashboardMetrics['email_sent_count'] ?? $dashboardMetrics['emails_sent'] ?? 0);
                $emailOpenCount = (int) ($dashboardMetrics['email_open_count'] ?? 0);
                $emailClickCount = (int) ($dashboardMetrics['email_click_count'] ?? 0);
                $emailsSent = $emailSentCount;

                $coachReplies = (int) ($dashboardMetrics['coach_replies'] ?? 0);
                $engagedSchools = (int) ($dashboardMetrics['engaged_schools'] ?? count($dashboardTopSchools));
                $coachEngagementTotal = $trackedWebsiteViews
                    + $trackedInstagramViews
                    + $trackedYoutubeViews
                    + $trackedXViews
                    + $trackedEmailLinkViews
                    + $emailClickCount
                    + $emailOpenCount
                    + $coachReplies;

                $readinessScore = min(100, max(12, 45 + min(25, $savedSchools * 4) + min(15, $favoriteSchools * 3) + min(10, $emailsSent) + min(10, $coachReplies * 2)));
                $profileCompletion = min(100, max(40, $readinessScore + 5));

                $quickStats = [
                    [
                        'label' => 'Profile Completion',
                        'value' => $profileCompletion . '%',
                        'sub' => 'Keep it up!',
                        'icon' => 'cap',
                        'tone' => 'coral',
                        'progress' => $profileCompletion,
                    ],
                    [
                        'label' => 'Profile Views',
                        'value' => number_format($profileViews),
                        'sub' => 'GHL view_profile_total',
                        'icon' => 'eye',
                        'tone' => 'blue',
                        'target' => 'profile-views',
                    ],
                    [
                        'label' => 'Favorites',
                        'value' => number_format($favoriteSchools),
                        'sub' => 'Schools saved',
                        'icon' => 'star',
                        'tone' => 'gold',
                        'target' => 'favorites',
                    ],
                    [
                        'label' => 'Coach Engagement',
                        'value' => number_format($coachEngagementTotal),
                        'sub' => 'Tracked GHL opens, clicks, replies',
                        'icon' => 'mail',
                        'tone' => 'green',
                        'target' => 'coach-engagement',
                    ],
                    [
                        'label' => 'Emails Sent',
                        'value' => number_format($emailsSent),
                        'sub' => 'GHL email_sent_count',
                        'icon' => 'chart',
                        'tone' => 'indigo',
                        'target' => 'emails-sent',
                    ],
                ];

                $progressItems = [
                    ['label' => 'PLYR Profile', 'state' => 'Complete', 'done' => true],
                    ['label' => 'Social Media Platforms', 'state' => 'Complete', 'done' => true],
                    ['label' => 'Highlights', 'state' => $profileViews > 0 ? 'Complete' : 'In progress', 'done' => $profileViews > 0],
                    ['label' => 'Coach Outreach', 'state' => $emailsSent > 0 ? 'Complete' : 'In progress', 'done' => $emailsSent > 0],
                    ['label' => 'My List', 'state' => $savedSchools > 0 ? 'In Progress' : 'Not started', 'done' => false],
                    ['label' => 'Academics / GPA', 'state' => 'Not started', 'done' => false],
                ];

                $radarSchools = collect($dashboardTopSchools)->take(4)->values()->all();

                if (empty($radarSchools)) {
                    $radarSchools = collect($this->filteredSchools ?? [])->take(4)->values()->all();
                }

                $dashboardActivityRows = collect($dashboardRecentActivity)->map(function ($activity) {
                    $activityType = strtolower((string) ($activity['type'] ?? $activity['title'] ?? $activity['copy'] ?? 'activity'));
                    $tone = 'blue';
                    $icon = '◉';

                    if (str_contains($activityType, 'reply')) {
                        $tone = 'green';
                        $icon = '↩';
                    } elseif (str_contains($activityType, 'email')) {
                        $tone = 'coral';
                        $icon = '✉';
                    } elseif (str_contains($activityType, 'favorite')) {
                        $tone = 'gold';
                        $icon = '☆';
                    } elseif (str_contains($activityType, 'smart')) {
                        $tone = 'purple';
                        $icon = '⊞';
                    }

                    $time = $activity['time'] ?? null;

                    return [
                        'title' => (string) ($activity['title'] ?? 'Recruiting activity'),
                        'copy' => trim(strip_tags((string) ($activity['copy'] ?? 'Recruiting update'))) ?: 'Recruiting update',
                        'url' => $activity['url'] ?? '#',
                        'tone' => $tone,
                        'icon' => $icon,
                        'time_label' => $time ? \Illuminate\Support\Carbon::parse($time)->diffForHumans(null, true) . ' ago' : 'Recent',
                    ];
                })->values();

                if ($dashboardActivityRows->isEmpty()) {
                    $dashboardActivityRows = collect([
                        ['title' => 'Coach Sarah Mitchell viewed your profile', 'copy' => 'Virginia Commonwealth University', 'time_label' => '2h ago', 'tone' => 'blue', 'icon' => '◉', 'url' => '#'],
                        ['title' => 'Email opened by Coach James Carter', 'copy' => 'University of South Carolina', 'time_label' => '1d ago', 'tone' => 'coral', 'icon' => '✉', 'url' => '#'],
                        ['title' => 'Added to favorites', 'copy' => 'James Madison University', 'time_label' => '2d ago', 'tone' => 'gold', 'icon' => '☆', 'url' => '#'],
                        ['title' => 'New reply from Coach Mike Brown', 'copy' => 'Clemson University', 'time_label' => '3d ago', 'tone' => 'green', 'icon' => '↩', 'url' => '#'],
                        ['title' => 'Added to smart list', 'copy' => 'ACC Schools', 'time_label' => '4d ago', 'tone' => 'purple', 'icon' => '⊞', 'url' => '#'],
                    ]);
                }


                $radarSchoolRows = collect($radarSchools)->map(function ($school) {
                    $schoolName = (string) ($school['name'] ?? 'School');
                    $schoolConference = (string) ($school['conference'] ?? $school['league'] ?? 'Conference');
                    $match = max(80, min(99, (int) ($school['lead_score'] ?? $school['engagement_score'] ?? 88)));
                    $initials = collect(explode(' ', $schoolName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');

                    return [
                        'id' => $school['id'] ?? $school['business_id'] ?? $schoolName,
                        'name' => $schoolName,
                        'conference' => $schoolConference,
                        'match' => $match,
                        'initials' => strtoupper($initials ?: 'PC'),
                    ];
                })->values();

                if ($radarSchoolRows->isEmpty()) {
                    $radarSchoolRows = collect([
                        ['id' => 'Virginia Commonwealth', 'name' => 'Virginia Commonwealth', 'conference' => 'Atlantic 10 Conference', 'match' => 94, 'initials' => 'VCU'],
                        ['id' => 'James Madison University', 'name' => 'James Madison University', 'conference' => 'Sun Belt Conference', 'match' => 91, 'initials' => 'JMU'],
                        ['id' => 'Duke University', 'name' => 'Duke University', 'conference' => 'ACC Conference', 'match' => 89, 'initials' => 'DU'],
                        ['id' => 'Wake Forest University', 'name' => 'Wake Forest University', 'conference' => 'ACC Conference', 'match' => 86, 'initials' => 'WF'],
                    ]);
                }

                $interestedSchoolRows = collect($dashboardTopSchools)->take(4)->values()->map(function ($school, $rank) {
                    $schoolName = (string) ($school['name'] ?? 'School');
                    $views = (int) (($school['profile_views'] ?? 0) + ($school['highlight_views'] ?? 0) + ($school['link_clicks'] ?? 0));
                    $score = max($views, (int) ($school['lead_score'] ?? $school['engagement_score'] ?? 0));
                    $initials = collect(explode(' ', $schoolName))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');

                    return [
                        'rank' => $rank + 1,
                        'name' => $schoolName,
                        'score' => $score,
                        'initials' => strtoupper($initials ?: 'S'),
                    ];
                })->values();

                if ($interestedSchoolRows->isEmpty()) {
                    $interestedSchoolRows = collect([
                        ['rank' => 1, 'name' => 'Virginia Commonwealth', 'score' => 14, 'initials' => 'VCU'],
                        ['rank' => 2, 'name' => 'University of Maryland', 'score' => 9, 'initials' => 'M'],
                        ['rank' => 3, 'name' => 'Florida State', 'score' => 7, 'initials' => 'FS'],
                        ['rank' => 4, 'name' => 'Indiana University', 'score' => 6, 'initials' => 'IU'],
                    ]);
                }
            @endphp

            <div class="rc-home-dashboard-v2">
                <div class="rc-home-header-v2">
                    <div>
                        <h1>Welcome back, {{ $firstName ?: 'Alex' }} <span aria-hidden="true">👋</span></h1>
                        <p>Here's what's happening with your recruiting journey.</p>
                    </div>

                    <form class="rc-home-actions-v2" wire:submit.prevent="$set('section', 'schools')">
                        <div class="rc-home-search-v2">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <input type="search" placeholder="Search schools, coaches, conferences..." wire:model.live.debounce.350ms="search">
                            <kbd>Enter</kbd>
                        </div>

                        <a class="rc-home-new-email-v2" href="{{ \App\Filament\Pages\CoachDatabaseComposeEmail::getUrl() }}">
                            <span>+</span>
                            New Email
                        </a>
                    </form>
                </div>

                <div class="rc-home-stats-v2">
                    @foreach($quickStats as $stat)
                        @if(! empty($stat['target']))
                            <button
                                type="button"
                                class="rc-home-stat-v2 is-{{ $stat['tone'] }} is-clickable"
                                wire:click="$set('section', @js($stat['target']))"
                            >
                        @else
                            <button
                                type="button"
                                class="rc-home-stat-v2 is-{{ $stat['tone'] }}"
                            >
                        @endif
                            <div class="rc-home-stat-icon-v2">
                                @switch($stat['icon'])
                                    @case('cap')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M3 8.5 12 4l9 4.5-9 4.5L3 8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="M7 11v4.2c0 1.6 2.2 3 5 3s5-1.4 5-3V11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        @break
                                    @case('eye')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8"/>
                                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                                        </svg>
                                        @break
                                    @case('star')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m12 3 2.7 5.5 6 .9-4.35 4.2 1.05 6-5.4-2.85-5.4 2.85 1.05-6L3.3 9.4l6-.9L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                        </svg>
                                        @break
                                    @case('mail')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 6h16v12H4V6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        @break
                                    @default
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M7 17 17 7M9 7h8v8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                @endswitch
                            </div>

                            <div class="rc-home-stat-copy-v2">
                                <div class="rc-home-stat-label-v2">{{ $stat['label'] }}</div>
                                <div class="rc-home-stat-value-v2">{{ $stat['value'] }}</div>
                            </div>

                            @if(isset($stat['progress']))
                                <div class="rc-home-progress-v2">
                                    <span style="width: {{ (int) $stat['progress'] }}%"></span>
                                </div>
                            @endif

                            <div class="rc-home-stat-sub-v2">{{ $stat['sub'] }}</div>
                        </button>
                    @endforeach
                </div>

                <div class="rc-home-grid-v2">
                    <section class="rc-home-panel-v2 rc-home-progress-panel-v2">
                        <div class="rc-home-panel-head-v2">
                            <h2>Recruiting Progress</h2>
                        </div>

                        <div class="rc-home-progress-layout-v2">
                            <div class="rc-readiness-ring-v2" style="--ready: {{ $readinessScore }};">
                                <div>
                                    <strong>{{ $readinessScore }}%</strong>
                                    <span>Recruiting Readiness</span>
                                </div>
                            </div>

                            <div class="rc-check-list-v2">
                                @foreach($progressItems as $item)
                                    <div class="rc-check-row-v2 {{ $item['done'] ? 'is-done' : '' }}">
                                        <span class="rc-check-dot-v2">
                                            @if($item['done']) ✓ @endif
                                        </span>
                                        <span>
                                            <strong>{{ $item['label'] }}</strong>
                                            <small>{{ $item['state'] }}</small>
                                        </span>
                                    </div>
                                @endforeach

                                <a class="rc-home-outline-btn-v2" href="#">View Full Checklist</a>
                            </div>
                        </div>
                    </section>

                    <section class="rc-home-panel-v2">
                        <div class="rc-home-panel-head-v2">
                            <h2>Recent Activity</h2>
                            <a href="#">View All</a>
                        </div>

                        <div class="rc-home-activity-list-v2">
                            @foreach($dashboardActivityRows as $activityRow)
                                <a class="rc-home-activity-v2" href="{{ $activityRow['url'] ?? '#' }}">
                                    <span class="rc-home-activity-icon-v2 is-{{ $activityRow['tone'] ?? 'blue' }}">{{ $activityRow['icon'] ?? '◉' }}</span>

                                    <span class="rc-home-activity-copy-v2">
                                        <strong>{{ $activityRow['title'] ?? 'Recruiting activity' }}</strong>
                                        <small>{{ $activityRow['copy'] ?? 'Recruiting update' }}</small>
                                    </span>

                                    <span class="rc-home-activity-time-v2">{{ $activityRow['time_label'] ?? 'Recent' }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                </div>

                <div class="rc-home-lower-grid-v2">
                    <section class="rc-home-panel-v2 rc-radar-panel-v2">
                        <div class="rc-home-panel-head-v2">
                            <div>
                                <h2>On The Radar</h2>
                                <p>Based on your profile and preferences</p>
                            </div>
                            <a href="#">View All</a>
                        </div>

                        <div class="rc-radar-schools-v2">
                            @foreach($radarSchoolRows as $radarSchool)
                                <button type="button" class="rc-radar-card-v2" wire:click="openSchoolDashboardModal(@js($radarSchool['id']))">
                                    <span class="rc-radar-logo-v2">{{ $radarSchool['initials'] }}</span>
                                    <strong>{{ $radarSchool['name'] }}</strong>
                                    <small>{{ $radarSchool['conference'] }}</small>
                                    <em>{{ $radarSchool['match'] }}% Match</em>
                                </button>
                            @endforeach
                        </div>

                        <div class="rc-home-dots-v2">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </section>

                    <section class="rc-home-panel-v2">
                        <div class="rc-home-panel-head-v2">
                            <h2>Schools Most Interested</h2>
                            <span>Last 30 days</span>
                        </div>

                        <div class="rc-interested-list-v2">
                            @foreach($interestedSchoolRows as $interestedSchool)
                                <button type="button" class="rc-interested-row-v2" wire:click="openDashboardEngagedSchool({{ (int) ($interestedSchool['rank'] - 1) }})">
                                    <span class="rc-interested-rank-v2">{{ $interestedSchool['rank'] }}</span>
                                    <span class="rc-interested-logo-v2">{{ $interestedSchool['initials'] }}</span>
                                    <span>
                                        <strong>{{ $interestedSchool['name'] }}</strong>
                                        <small>Profile views</small>
                                    </span>
                                    <b>{{ $interestedSchool['score'] }}</b>
                                </button>
                            @endforeach
                        </div>

                        <a class="rc-home-outline-btn-v2" href="#">View Full Analytics</a>
                    </section>
                </div>
            </div>
        @endif

        @if($section === 'profile-views')
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $dashboardTopSchools = collect($this->dashboardTopEngagedSchools ?? [])->values();
                $dashboardRecentActivity = collect($this->dashboardRecentActivity ?? [])->values();

                $profileViewsTotal = (int) ($dashboardMetrics['view_profile_total'] ?? $dashboardMetrics['profile_views'] ?? 0);
                $websiteViews = (int) ($dashboardMetrics['view_profile_website'] ?? $dashboardMetrics['website_clicks'] ?? 0);
                $instagramViews = (int) ($dashboardMetrics['view_profile_instagram'] ?? $dashboardMetrics['instagram_clicks'] ?? 0);
                $youtubeViews = (int) ($dashboardMetrics['view_profile_youtube'] ?? $dashboardMetrics['youtube_clicks'] ?? 0);
                $xViews = (int) ($dashboardMetrics['view_profile_x'] ?? $dashboardMetrics['x_clicks'] ?? $dashboardMetrics['twitter_clicks'] ?? 0);
                $emailLinkViews = (int) ($dashboardMetrics['view_profile_email_link'] ?? 0);
                $profilePrograms = max(0, (int) ($dashboardMetrics['engaged_schools'] ?? $dashboardTopSchools->count()));

                $profileBreakdownRows = collect([
                    ['title' => 'Website profile link', 'copy' => 'GHL custom field: view_profile_website', 'views' => $websiteViews, 'type' => 'Website', 'initials' => 'W', 'time_label' => 'Synced'],
                    ['title' => 'Instagram profile link', 'copy' => 'GHL custom field: view_profile_instagram', 'views' => $instagramViews, 'type' => 'Instagram', 'initials' => 'IG', 'time_label' => 'Synced'],
                    ['title' => 'YouTube highlight link', 'copy' => 'GHL custom field: view_profile_youtube', 'views' => $youtubeViews, 'type' => 'YouTube', 'initials' => 'YT', 'time_label' => 'Synced'],
                    ['title' => 'X profile link', 'copy' => 'GHL custom field: view_profile_x', 'views' => $xViews, 'type' => 'X', 'initials' => 'X', 'time_label' => 'Synced'],
                    ['title' => 'Email profile link', 'copy' => 'GHL custom field: view_profile_email_link', 'views' => $emailLinkViews, 'type' => 'Email Link', 'initials' => 'EM', 'time_label' => 'Synced'],
                ])->filter(fn (array $row): bool => (int) ($row['views'] ?? 0) > 0)->values();

                $activityProfileRows = $dashboardRecentActivity
                    ->filter(fn ($activity) => str_contains(strtolower((string) ($activity['type'] ?? $activity['title'] ?? $activity['copy'] ?? '')), 'view'))
                    ->take(8)
                    ->values()
                    ->map(function ($activity, $index) {
                        $title = (string) ($activity['title'] ?? 'Coach viewed profile');
                        $initials = collect(explode(' ', $title))->filter()->map(fn ($part) => substr((string) $part, 0, 1))->take(2)->implode('');
                        $time = $activity['time'] ?? null;

                        return [
                            'title' => $title,
                            'copy' => trim(strip_tags((string) ($activity['copy'] ?? 'Tracked GHL profile activity'))) ?: 'Tracked GHL profile activity',
                            'views' => (int) ($activity['views'] ?? $activity['count'] ?? 1),
                            'type' => (string) ($activity['platform'] ?? $activity['source'] ?? 'Profile'),
                            'logo' => $activity['logo'] ?? null,
                            'initials' => strtoupper($initials ?: 'PV'),
                            'time_label' => $time ? \Illuminate\Support\Carbon::parse($time)->diffForHumans(null, true) . ' ago' : 'Recent',
                        ];
                    });

                $profileViewRows = $activityProfileRows->merge($profileBreakdownRows)->values()->map(function ($row, $index) {
                    return array_merge($row, ['rank' => $index + 1]);
                });
            @endphp

            <div class="rc-detail-page-v2">
                <div class="rc-detail-header-v2">
                    <div>
                        <h1>Profile Views</h1>
                        <p>GHL-backed profile views from tracked website, Instagram, YouTube, X, and email links.</p>
                    </div>
                    <form class="rc-detail-search-v2" wire:submit.prevent="$set('section', 'schools')">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <input type="search" placeholder="Search schools, coaches, conferences..." wire:model.live.debounce.350ms="search">
                    </form>
                </div>

                <div class="rc-detail-stats-v2">
                    <div class="rc-detail-stat-v2 is-blue"><span>◎</span><div><small>Total Views</small><strong>{{ number_format($profileViewsTotal) }}</strong><em>GHL field: view_profile_total</em></div></div>
                    <div class="rc-detail-stat-v2 is-coral"><span>⌁</span><div><small>Website + Email</small><strong>{{ number_format($websiteViews + $emailLinkViews) }}</strong><em>Website profile and email links</em></div></div>
                    <div class="rc-detail-stat-v2 is-purple"><span>▥</span><div><small>Social Clicks</small><strong>{{ number_format($instagramViews + $youtubeViews + $xViews) }}</strong><em>Instagram, YouTube, and X</em></div></div>
                </div>

                <section class="rc-detail-table-v2">
                    <header><h2>GHL Profile View Breakdown</h2><span>● Synced from GHL</span></header>
                    <div class="rc-detail-rows-v2">
                        @forelse($profileViewRows as $profileRow)
                            <button type="button" class="rc-detail-row-v2">
                                <span class="rc-detail-rank-v2">#{{ $profileRow['rank'] }}</span>
                                <span class="rc-detail-avatar-v2">
                                    @if(! empty($profileRow['logo']))
                                        <img src="{{ $profileRow['logo'] }}" alt="{{ $profileRow['title'] }}">
                                    @else
                                        {{ $profileRow['initials'] }}
                                    @endif
                                </span>
                                <span class="rc-detail-person-v2"><strong>{{ $profileRow['title'] }}</strong><small>{{ $profileRow['copy'] }}</small></span>
                                <span class="rc-detail-pill-v2">{{ $profileRow['type'] }}</span>
                                <span class="rc-detail-count-v2"><b>{{ $profileRow['views'] }}</b><small>{{ \Illuminate\Support\Str::plural('view', $profileRow['views']) }}</small></span>
                                <span class="rc-detail-time-v2">{{ $profileRow['time_label'] }}</span>
                                <span class="rc-detail-chevron-v2">›</span>
                            </button>
                        @empty
                            <div class="rc-home-empty-v2">Profile view activity will appear here after coaches click tracked GHL links.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif

        @if($section === 'coach-engagement')
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $dashboardRecentActivity = collect($this->dashboardRecentActivity ?? [])->values();

                $websiteClicks = (int) ($dashboardMetrics['view_profile_website'] ?? $dashboardMetrics['website_clicks'] ?? 0);
                $xClicks = (int) ($dashboardMetrics['view_profile_x'] ?? $dashboardMetrics['x_clicks'] ?? $dashboardMetrics['twitter_clicks'] ?? 0);
                $igClicks = (int) ($dashboardMetrics['view_profile_instagram'] ?? $dashboardMetrics['instagram_clicks'] ?? 0);
                $ytClicks = (int) ($dashboardMetrics['view_profile_youtube'] ?? $dashboardMetrics['youtube_clicks'] ?? 0);
                $emailLinkClicks = (int) ($dashboardMetrics['view_profile_email_link'] ?? 0);
                $emailClicks = (int) ($dashboardMetrics['email_click_count'] ?? 0);
                $emailOpens = (int) ($dashboardMetrics['email_open_count'] ?? 0);
                $coachReplies = (int) ($dashboardMetrics['coach_replies'] ?? 0);

                $coachEngagementRows = collect([
                    ['title' => 'Website profile clicks', 'copy' => 'GHL custom field: view_profile_website', 'platform' => 'Website', 'platform_class' => 'is-blue', 'platform_icon' => '⌁', 'clicks' => $websiteClicks, 'time_label' => 'Synced'],
                    ['title' => 'Instagram clicks', 'copy' => 'GHL custom field: view_profile_instagram', 'platform' => 'Instagram', 'platform_class' => 'is-pink', 'platform_icon' => '◎', 'clicks' => $igClicks, 'time_label' => 'Synced'],
                    ['title' => 'YouTube clicks', 'copy' => 'GHL custom field: view_profile_youtube', 'platform' => 'YouTube', 'platform_class' => 'is-red', 'platform_icon' => '▶', 'clicks' => $ytClicks, 'time_label' => 'Synced'],
                    ['title' => 'X clicks', 'copy' => 'GHL custom field: view_profile_x', 'platform' => 'X', 'platform_class' => 'is-neutral', 'platform_icon' => '𝕏', 'clicks' => $xClicks, 'time_label' => 'Synced'],
                    ['title' => 'Email profile-link clicks', 'copy' => 'GHL custom field: view_profile_email_link', 'platform' => 'Email Link', 'platform_class' => 'is-coral', 'platform_icon' => '✉', 'clicks' => $emailLinkClicks, 'time_label' => 'Synced'],
                    ['title' => 'Email clicks', 'copy' => 'GHL custom field: email_click_count', 'platform' => 'Email Click', 'platform_class' => 'is-coral', 'platform_icon' => '↗', 'clicks' => $emailClicks, 'time_label' => 'Synced'],
                    ['title' => 'Email opens', 'copy' => 'GHL custom field: email_open_count', 'platform' => 'Email Open', 'platform_class' => 'is-green', 'platform_icon' => '◉', 'clicks' => $emailOpens, 'time_label' => 'Synced'],
                    ['title' => 'Coach replies', 'copy' => 'GHL conversation replies / existing coach reply metric', 'platform' => 'Reply', 'platform_class' => 'is-purple', 'platform_icon' => '↩', 'clicks' => $coachReplies, 'time_label' => 'Synced'],
                ])->filter(fn (array $row): bool => (int) ($row['clicks'] ?? 0) > 0)->values();

                if ($coachEngagementRows->isEmpty()) {
                    $coachEngagementRows = $dashboardRecentActivity->take(8)->map(function ($row, $index) {
                        $platform = (string) ($row['platform'] ?? ($index % 3 === 0 ? 'Instagram' : ($index % 3 === 1 ? 'YouTube' : 'X')));
                        $platformLower = strtolower($platform);
                        $platformClass = str_contains($platformLower, 'you') ? 'is-red' : (str_contains($platformLower, 'instagram') ? 'is-pink' : 'is-neutral');
                        $platformIcon = str_contains($platformLower, 'you') ? '▶' : (str_contains($platformLower, 'instagram') ? '◎' : '𝕏');
                        $time = $row['time'] ?? null;

                        return [
                            'title' => (string) ($row['title'] ?? 'Tracked coach engagement'),
                            'copy' => trim(strip_tags((string) ($row['copy'] ?? 'GHL activity'))) ?: 'GHL activity',
                            'platform' => $platform,
                            'platform_class' => $platformClass,
                            'platform_icon' => $platformIcon,
                            'clicks' => (int) ($row['clicks'] ?? $row['count'] ?? 1),
                            'time_label' => $time ? \Illuminate\Support\Carbon::parse($time)->diffForHumans(null, true) . ' ago' : 'Recent',
                        ];
                    })->values();
                }
            @endphp

            <div class="rc-detail-page-v2">
                <div class="rc-detail-header-v2">
                    <div>
                        <h1>Coach Engagement</h1>
                        <p>GHL-backed tracking for social clicks, email opens, email clicks, and replies.</p>
                    </div>
                    <form class="rc-detail-search-v2" wire:submit.prevent="$set('section', 'schools')">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <input type="search" placeholder="Search schools, coaches, conferences..." wire:model.live.debounce.350ms="search">
                    </form>
                </div>

                <div class="rc-detail-stats-v2">
                    <div class="rc-detail-stat-v2 is-neutral"><span>𝕏</span><div><small>X</small><strong>{{ number_format($xClicks) }}</strong><em>view_profile_x</em></div></div>
                    <div class="rc-detail-stat-v2 is-pink"><span>◎</span><div><small>Instagram</small><strong>{{ number_format($igClicks) }}</strong><em>view_profile_instagram</em></div></div>
                    <div class="rc-detail-stat-v2 is-red"><span>▶</span><div><small>YouTube</small><strong>{{ number_format($ytClicks) }}</strong><em>view_profile_youtube</em></div></div>
                </div>

                <section class="rc-detail-table-v2">
                    <header><h2>GHL Engagement Breakdown</h2><span>● Synced from GHL</span></header>
                    <div class="rc-detail-rows-v2">
                        @forelse($coachEngagementRows as $engagementRow)
                            <button type="button" class="rc-detail-row-v2">
                                <span class="rc-detail-platform-icon-v2 {{ $engagementRow['platform_class'] }}">{{ $engagementRow['platform_icon'] }}</span>
                                <span class="rc-detail-person-v2"><strong>{{ $engagementRow['title'] }}</strong><small>{{ $engagementRow['copy'] }}</small></span>
                                <span class="rc-detail-pill-v2 {{ $engagementRow['platform_class'] }}">{{ $engagementRow['platform'] }}</span>
                                <span class="rc-detail-count-v2"><b>{{ $engagementRow['clicks'] }}</b><small>{{ \Illuminate\Support\Str::plural('event', $engagementRow['clicks']) }}</small></span>
                                <span class="rc-detail-time-v2">{{ $engagementRow['time_label'] }}</span>
                                <span class="rc-detail-chevron-v2">›</span>
                            </button>
                        @empty
                            <div class="rc-home-empty-v2">Coach engagement will appear here after coaches click tracked links or open emails.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif

        @if($section === 'emails-sent')
            @php
                $dashboardMetrics = $this->dashboardMetrics;
                $dashboardRecentActivity = collect($this->dashboardRecentActivity ?? [])->values();

                $emailSentCount = (int) ($dashboardMetrics['email_sent_count'] ?? $dashboardMetrics['emails_sent'] ?? 0);
                $emailOpenCount = (int) ($dashboardMetrics['email_open_count'] ?? 0);
                $emailClickCount = (int) ($dashboardMetrics['email_click_count'] ?? 0);
                $emailProfileLinkCount = (int) ($dashboardMetrics['view_profile_email_link'] ?? 0);

                $emailRows = $dashboardRecentActivity
                    ->filter(fn ($activity) => str_contains(strtolower((string) ($activity['type'] ?? $activity['title'] ?? $activity['copy'] ?? '')), 'email'))
                    ->take(12)
                    ->values()
                    ->map(function ($row, $index) {
                        $time = $row['time'] ?? null;

                        return [
                            'rank' => $index + 1,
                            'title' => (string) ($row['title'] ?? 'Email activity'),
                            'copy' => trim(strip_tags((string) ($row['copy'] ?? 'Tracked GHL email event'))) ?: 'Tracked GHL email event',
                            'type' => (string) ($row['type'] ?? 'Email'),
                            'count' => (int) ($row['count'] ?? $row['clicks'] ?? 1),
                            'time_label' => $time ? \Illuminate\Support\Carbon::parse($time)->diffForHumans(null, true) . ' ago' : 'Recent',
                        ];
                    });

                if ($emailRows->isEmpty()) {
                    $emailRows = collect([
                        ['rank' => 1, 'title' => 'Emails sent', 'copy' => 'GHL custom field: email_sent_count', 'type' => 'Sent', 'count' => $emailSentCount, 'time_label' => 'Synced'],
                        ['rank' => 2, 'title' => 'Emails opened', 'copy' => 'GHL custom field: email_open_count', 'type' => 'Open', 'count' => $emailOpenCount, 'time_label' => 'Synced'],
                        ['rank' => 3, 'title' => 'Email links clicked', 'copy' => 'GHL custom field: email_click_count', 'type' => 'Click', 'count' => $emailClickCount, 'time_label' => 'Synced'],
                        ['rank' => 4, 'title' => 'Email profile links clicked', 'copy' => 'GHL custom field: view_profile_email_link', 'type' => 'Profile Link', 'count' => $emailProfileLinkCount, 'time_label' => 'Synced'],
                    ])->filter(fn (array $row): bool => (int) ($row['count'] ?? 0) > 0)->values()->map(function ($row, $index) {
                        $row['rank'] = $index + 1;
                        return $row;
                    });
                }
            @endphp

            <div class="rc-detail-page-v2">
                <div class="rc-detail-header-v2">
                    <div>
                        <h1>Emails Sent</h1>
                        <p>GHL-backed email sending, open, and click tracking from Coach Database emails.</p>
                    </div>
                    <form class="rc-detail-search-v2" wire:submit.prevent="$set('section', 'schools')">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <input type="search" placeholder="Search schools, coaches, conferences..." wire:model.live.debounce.350ms="search">
                    </form>
                </div>

                <div class="rc-detail-stats-v2">
                    <div class="rc-detail-stat-v2 is-coral"><span>✉</span><div><small>Sent</small><strong>{{ number_format($emailSentCount) }}</strong><em>email_sent_count</em></div></div>
                    <div class="rc-detail-stat-v2 is-blue"><span>◉</span><div><small>Opened</small><strong>{{ number_format($emailOpenCount) }}</strong><em>email_open_count</em></div></div>
                    <div class="rc-detail-stat-v2 is-green"><span>↗</span><div><small>Clicked</small><strong>{{ number_format($emailClickCount) }}</strong><em>email_click_count</em></div></div>
                </div>

                <section class="rc-detail-table-v2">
                    <header><h2>GHL Email Tracking</h2><span>● Synced from GHL</span></header>
                    <div class="rc-detail-rows-v2">
                        @forelse($emailRows as $emailRow)
                            <button type="button" class="rc-detail-row-v2">
                                <span class="rc-detail-rank-v2">#{{ $emailRow['rank'] }}</span>
                                <span class="rc-detail-avatar-v2">✉</span>
                                <span class="rc-detail-person-v2"><strong>{{ $emailRow['title'] }}</strong><small>{{ $emailRow['copy'] }}</small></span>
                                <span class="rc-detail-pill-v2">{{ $emailRow['type'] }}</span>
                                <span class="rc-detail-count-v2"><b>{{ $emailRow['count'] }}</b><small>{{ \Illuminate\Support\Str::plural('event', $emailRow['count']) }}</small></span>
                                <span class="rc-detail-time-v2">{{ $emailRow['time_label'] }}</span>
                                <span class="rc-detail-chevron-v2">›</span>
                            </button>
                        @empty
                            <div class="rc-home-empty-v2">Email tracking will appear here after Coach Database emails are sent and opened/clicked.</div>
                        @endforelse
                    </div>
                </section>
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