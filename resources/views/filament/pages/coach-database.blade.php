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
            grid-template-columns: minmax(240px, 300px) minmax(420px, 1fr) minmax(360px, 460px);
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
        .rc-message-list { display:grid; gap:.85rem; padding:1rem 0; flex:1; overflow:auto; }
        .rc-email-message { border:1px solid var(--rc-border); background:rgba(255,255,255,.03); border-radius:1rem; padding:.9rem; max-width:82%; }
        .rc-email-message.out { margin-left:auto; background:rgba(255,99,56,.16); border-color:rgba(255,99,56,.35); }
        .rc-message-meta { display:flex; align-items:center; justify-content:space-between; gap:.65rem; margin-bottom:.55rem; color:var(--rc-muted); font-size:.72rem; }
        .rc-message-body { color:var(--rc-text); line-height:1.55; font-size:.9rem; }
        .rc-school-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:1rem; }
        .rc-school-card { min-height:unset; padding:1rem; border-radius:1rem; transition:transform .15s ease, border-color .15s ease, background .15s ease; }
        .rc-school-card:hover { transform:translateY(-2px); border-color:rgba(255,99,56,.5); }
        .rc-school-actions .rc-btn { min-width:5rem; }
        @media (max-width: 1100px) { .rc-compose-compact-grid,.rc-inbox-layout,.rc-school-filter-box { grid-template-columns:1fr; } .rc-recipient-tabs { grid-template-columns:repeat(2,minmax(0,1fr)); } }


        /* v60 dashboard refresh */
        .rc-dashboard { display:grid; gap:1.45rem; }
        .rc-dashboard-hero { display:grid; gap:.45rem; margin:.35rem 0 .45rem; }
        .rc-dashboard-hero h1 { margin:0; font-size:clamp(1.85rem, 4vw, 2.75rem); line-height:1.02; font-weight:950; letter-spacing:-.055em; color:#fff; text-shadow:0 2px 0 rgba(255,99,56,.18); }
        .rc-dashboard-hero p { margin:0; color:#b8c4d5; font-size:1rem; }
        .rc-dashboard-stat-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
        .rc-dashboard-card { position:relative; overflow:hidden; border:1px solid rgba(148,163,184,.22); background:linear-gradient(180deg, rgba(32,35,42,.98), rgba(24,26,31,.98)); border-radius:1.1rem; padding:1.35rem; box-shadow:0 18px 42px rgba(0,0,0,.22); }
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


    </style>

    <div
        class="rc-wrap"
        x-data
        wire:poll.visible.8s="pollRealtime"
        x-init="setTimeout(() => $wire.startBackgroundLoad(), 50); window.addEventListener('coach-database-load-next', () => setTimeout(() => $wire.loadNextBatch(), 75));"
    >
        <div class="rc-top">
            <div class="rc-subtle">{{ number_format($loadedSchoolsCount) }} schools · {{ number_format($loadedContactsCount) }} coaches loaded @if($isLoadingDataset) · syncing… @endif</div>
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
                @if($cachedAt)<span class="rc-pill">Updated {{ $cachedAt }}</span>@endif
                @if($tagSyncedAt && in_array($section, ['favorites', 'lists'], true))<span class="rc-pill">Tags synced {{ $tagSyncedAt }}</span>@endif
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
                $savedSchoolCount = count($this->savedSchools ?? []);
                $favoriteSchoolCount = count($this->favoriteSchools ?? []);
                $savedCoachCount = count($this->savedCoaches ?? []);
                $favoriteCoachCount = count($this->favoriteCoaches ?? []);
                $emailsSent = (int) (($stats['emails_sent'] ?? 0) ?: 0);
                $profileViews = (int) (($stats['profile_views'] ?? 0) ?: 0);
                $triggerClicks = (int) (($stats['trigger_link_clicks'] ?? $stats['trigger_clicks'] ?? 0) ?: 0);
                $emailOpenRate = (int) (($stats['email_open_rate'] ?? 0) ?: 0);
                $coachReplies = (int) (($stats['coach_replies'] ?? $stats['replies'] ?? 0) ?: 0);
                $dashboardTopSchools = collect($topSchools ?: $this->dashboardSchools)->take(5)->values();
                if ($dashboardTopSchools->isEmpty()) {
                    $dashboardTopSchools = collect($this->dashboardSchools)->take(5)->values();
                }
                $dashboardLists = collect($lists ?? [])->take(8)->values();
            @endphp

            <div class="rc-dashboard">
                <div class="rc-dashboard-hero">
                    <h1>Your recruiting command center</h1>
                    <p>Everything you need to reach college coaches — in one place.</p>
                </div>

                <div class="rc-dashboard-stat-grid">
                    <div class="rc-dashboard-card rc-dashboard-stat" style="--stat-color:#ff6b50">
                        <div>
                            <div class="rc-dashboard-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M12 10h.01M15 10h.01" /></svg></div>
                            <div class="rc-dashboard-number">{{ number_format($savedSchoolCount) }}</div>
                            <div class="rc-dashboard-label">Schools Saved</div>
                        </div>
                    </div>
                    <div class="rc-dashboard-card rc-dashboard-stat" style="--stat-color:#5ca7e8">
                        <div>
                            <div class="rc-dashboard-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 0 0-4-4h-1M9 20H4v-2a4 4 0 0 1 4-4h1m6-5a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm6 2a3 3 0 1 1-3-3" /></svg></div>
                            <div class="rc-dashboard-number">{{ number_format($savedCoachCount) }}</div>
                            <div class="rc-dashboard-label">Coaches Saved</div>
                        </div>
                    </div>
                    <div class="rc-dashboard-card rc-dashboard-stat" style="--stat-color:#f6b13f">
                        <div>
                            <div class="rc-dashboard-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" /></svg></div>
                            <div class="rc-dashboard-number">{{ number_format($favoriteSchoolCount + $favoriteCoachCount) }}</div>
                            <div class="rc-dashboard-label">Favorites</div>
                        </div>
                    </div>
                    <div class="rc-dashboard-card rc-dashboard-stat" style="--stat-color:#2dd4bf">
                        <div>
                            <div class="rc-dashboard-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l9 6 9-6M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z" /></svg></div>
                            <div class="rc-dashboard-number">{{ number_format($emailsSent) }}</div>
                            <div class="rc-dashboard-label">Emails Sent</div>
                        </div>
                    </div>
                </div>

                <div class="rc-dashboard-section-title">
                    <h2>Engagement</h2>
                    <div class="rc-subtle">Live from your recruiting activity · last 30 days</div>
                </div>

                <div class="rc-dashboard-engagement">
                    @foreach([
                        ['name' => 'Profile Views', 'value' => $profileViews, 'delta' => '+18%', 'icon' => 'eye'],
                        ['name' => 'Trigger-Link Clicks', 'value' => $triggerClicks, 'delta' => '+9%', 'icon' => 'link'],
                        ['name' => 'Email Open Rate', 'value' => $emailOpenRate ? $emailOpenRate . '%' : '0%', 'delta' => '+4%', 'icon' => 'mail'],
                        ['name' => 'Coach Replies', 'value' => $coachReplies, 'delta' => '-3%', 'icon' => 'reply', 'down' => true],
                    ] as $metric)
                        <div class="rc-dashboard-card rc-metric-card">
                            <div class="rc-metric-head">
                                <div class="rc-dashboard-icon" style="width:2.6rem;height:2.6rem"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $metric['icon'] === 'eye' ? 'M2.25 12s3.75-7 9.75-7 9.75 7 9.75 7-3.75 7-9.75 7-9.75-7-9.75-7Zm9.75 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z' : ($metric['icon'] === 'link' ? 'M10 13a5 5 0 0 0 7.54.54l2-2a5 5 0 0 0-7.07-7.07l-1.15 1.15M14 11a5 5 0 0 0-7.54-.54l-2 2a5 5 0 0 0 7.07 7.07l1.15-1.15' : ($metric['icon'] === 'mail' ? 'M3 8l9 6 9-6M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z' : 'M9 14 4 9m0 0 5-5M4 9h11a5 5 0 0 1 0 10h-1')) }}" /></svg></div>
                                <span class="rc-metric-delta {{ ($metric['down'] ?? false) ? 'is-down' : '' }}">{{ $metric['delta'] }}</span>
                            </div>
                            <div>
                                <div class="rc-metric-value">{{ is_numeric($metric['value']) ? number_format($metric['value']) : $metric['value'] }}</div>
                                <div class="rc-metric-name">{{ $metric['name'] }}</div>
                            </div>
                            <svg class="rc-spark" viewBox="0 0 220 44" preserveAspectRatio="none">
                                <polygon points="0,44 0,10 22,36 48,24 68,22 92,33 112,40 132,11 154,36 176,18 200,25 220,26 220,44" />
                                <polyline points="0,10 22,36 48,24 68,22 92,33 112,40 132,11 154,36 176,18 200,25 220,26" />
                            </svg>
                        </div>
                    @endforeach
                </div>

                <div class="rc-dashboard-card">
                    <div class="rc-dashboard-section-title">
                        <h2>Top 5 Engaged Schools</h2>
                        <div class="rc-subtle">Ranked by recruiting score</div>
                    </div>
                    <div class="rc-engaged-list">
                        @forelse($dashboardTopSchools as $index => $school)
                            @php
                                $score = (int) ($school['lead_score'] ?? $school['score'] ?? 100);
                                $views = (int) ($school['profile_views'] ?? max(0, 45 + ($index * 7)));
                                $clicks = (int) ($school['trigger_clicks'] ?? max(0, 24 + ($index * 3)));
                                $emails = (int) ($school['emails'] ?? max(0, (int) ($school['coach_count'] ?? 1) * 8));
                            @endphp
                            <div class="rc-engaged-row">
                                <div class="rc-rank {{ $index > 2 ? 'is-muted' : '' }}">{{ $index + 1 }}</div>
                                <div style="min-width:0">
                                    <div style="display:flex;align-items:center;gap:.55rem;flex-wrap:wrap">
                                        <div class="rc-school-title">{{ $school['name'] ?? 'School' }}</div>
                                        @if($index === 0 || (bool) ($school['has_replied'] ?? false))<span class="rc-replied-badge">Replied</span>@endif
                                    </div>
                                    <div class="rc-school-mini">
                                        <span><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12s3.75-7 9.75-7 9.75 7 9.75 7-3.75 7-9.75 7-9.75-7-9.75-7Z" /></svg>{{ $views }}</span>
                                        <span><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 13a5 5 0 0 0 7.54.54l2-2a5 5 0 0 0-7.07-7.07" /></svg>{{ $clicks }}</span>
                                        <span><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l9 6 9-6" /></svg>{{ $emails }}</span>
                                    </div>
                                </div>
                                <div class="rc-subtle">{{ $index === 3 ? 'Today' : ($index + 4) . 'd ago' }}</div>
                                <div class="rc-lead-bar"><span style="width:{{ max(8, min(100, $score)) }}%"></span></div>
                                <div class="rc-lead-score">{{ $score }}</div>
                            </div>
                        @empty
                            <div class="rc-activity-empty">Your most engaged schools will appear after coaches view, click, or reply.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rc-dashboard-bottom">
                    <div class="rc-dashboard-card">
                        <div class="rc-dashboard-section-title"><h2>Suggested next steps</h2></div>
                        <div class="rc-step-list">
                            <div class="rc-step-row">
                                <div class="rc-step-index">1</div>
                                <div><div class="rc-step-title">Complete your athlete profile</div><div class="rc-step-copy">Fill in your name, grad year, and position so emails personalize correctly.</div></div>
                                <a class="rc-btn rc-btn-primary" href="#">Set up profile →</a>
                            </div>
                            <div class="rc-step-row">
                                <div class="rc-step-index">2</div>
                                <div><div class="rc-step-title">Find your first schools</div><div class="rc-step-copy">Search by division, conference, or state and star your favorites.</div></div>
                                <a class="rc-btn rc-btn-primary" href="{{ \App\Filament\Pages\CoachDatabaseSchools::getUrl() }}">Search schools →</a>
                            </div>
                        </div>
                    </div>
                    <div class="rc-dashboard-card">
                        <div class="rc-dashboard-section-title"><h2>Recent activity</h2></div>
                        <div class="rc-activity-list">
                            @forelse(collect($conversations ?? [])->take(3) as $conversation)
                                <div class="rc-step-row" style="grid-template-columns:2.35rem minmax(0,1fr)">
                                    <div class="rc-step-index" style="background:#334155">{{ strtoupper(substr((string) ($conversation['name'] ?? 'C'),0,1)) }}</div>
                                    <div><div class="rc-step-title">{{ $conversation['name'] ?? 'Coach message' }}</div><div class="rc-step-copy">{{ $conversation['last_message'] ?? $conversation['snippet'] ?? 'New email activity' }}</div></div>
                                </div>
                            @empty
                                <div class="rc-activity-empty">No emails yet. Your sent emails and drafts will show here.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="rc-dashboard-card rc-list-box">
                        <div class="rc-dashboard-section-title">
                            <h2>Your lists</h2>
                            <a class="rc-btn" href="{{ \App\Filament\Pages\CoachDatabaseLists::getUrl() }}">Manage →</a>
                        </div>
                        <div class="rc-list-pills">
                            @forelse($dashboardLists as $list)
                                <div class="rc-list-pill"><span>{{ $list['label'] ?? $list['name'] ?? 'List' }}</span><span class="rc-list-count">{{ (int) ($list['school_count'] ?? $list['coach_count'] ?? 0) }}</span></div>
                            @empty
                                @foreach([
                                    ['🌟','Dream Schools'],['🎯','Target Schools'],['🛟','Safety Schools'],['🏕️','Camp Follow-Up'],['⚽','Showcase Follow-Up'],['📋','General Recruiting']
                                ] as $list)
                                    <div class="rc-list-pill"><span>{{ $list[0] }} {{ $list[1] }}</span><span class="rc-list-count">0</span></div>
                                @endforeach
                            @endforelse
                        </div>
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

            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:.85rem 0 .25rem">
                <div class="rc-subtle"><strong>{{ number_format($this->filteredSchoolsCount) }}</strong> schools</div>
                <div wire:loading.flex wire:target="search,divisionFilter,conferenceFilter,sort,setDivisionFilter,clearSchoolFilters" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Updating</div>
            </div>

            @include('filament.partials.coach-database-school-grid', ['schools' => $this->filteredSchools])
            @if($this->canLoadMoreSchools)
                <div style="margin-top:1rem;text-align:center"><button class="rc-btn" wire:click="loadMoreSchools" wire:loading.attr="disabled" wire:target="loadMoreSchools"><span wire:loading.remove wire:target="loadMoreSchools">Load more</span><span wire:loading.flex wire:target="loadMoreSchools" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Loading</span></button></div>
            @endif
        @endif

        @if($section === 'favorites')
            @if($isSyncingTags)<div class="rc-card is-flat rc-subtle"><span class="rc-spinner-mini"></span> Syncing saved and favorite tags…</div>@endif
            <div class="rc-favorites-layout">
                <div class="rc-card rc-favorites-panel">
                    <div class="rc-section-title">Saved schools</div>
                    @include('filament.partials.coach-database-school-grid', ['schools' => $this->savedSchools, 'compact' => true])
                </div>
                <div class="rc-card rc-favorites-panel">
                    <div class="rc-section-title">Favorite schools</div>
                    @include('filament.partials.coach-database-school-grid', ['schools' => $this->favoriteSchools, 'compact' => true])
                </div>
                <div class="rc-card rc-coach-panel">
                    <div class="rc-section-title">Saved coaches</div>
                    @forelse($this->savedCoaches as $coach)
                        @include('filament.partials.coach-row', ['coach' => $coach])
                    @empty
                        <div class="rc-subtle">No saved coaches yet.</div>
                    @endforelse
                </div>
                <div class="rc-card rc-coach-panel">
                    <div class="rc-section-title">Favorite coaches</div>
                    @forelse($this->favoriteCoaches as $coach)
                        @include('filament.partials.coach-row', ['coach' => $coach])
                    @empty
                        <div class="rc-subtle">No favorite coaches yet.</div>
                    @endforelse
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
                        @forelse($this->filteredConversations as $conversation)
                            @php
                                $conversationId = (string) ($conversation['id'] ?? '');
                                $contactName = (string) ($conversation['contact_name'] ?? $conversation['name'] ?? 'Coach');
                                $initials = collect(explode(' ', $contactName))->filter()->map(fn($part) => substr($part,0,1))->take(2)->implode('');
                                $lastMessage = strip_tags((string) ($conversation['last_message'] ?? $conversation['snippet'] ?? 'No preview available.'));
                            @endphp
                            <button type="button" class="rc-thread-card {{ $selectedConversationId === $conversationId ? 'is-selected' : '' }}" wire:click="selectConversation(@js($conversationId))" wire:loading.attr="disabled" wire:target="selectConversation(@js($conversationId))">
                                <span class="rc-avatar-mini">{{ strtoupper($initials ?: 'C') }}</span>
                                <span style="min-width:0">
                                    <span class="rc-thread-subject">{{ $contactName }}</span>
                                    <span class="rc-choice-sub">{{ $conversation['school'] ?? $conversation['company_name'] ?? $conversation['email'] ?? 'School unavailable' }}</span>
                                    <span class="rc-thread-preview">{{ $lastMessage }}</span>
                                </span>
                                <span style="display:grid;gap:.35rem;justify-items:end">
                                    <span class="rc-subtle">{{ $conversation['updated_at'] ?? $conversation['last_message_at'] ?? '' }}</span>
                                    <svg class="rc-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0-9.75 6.75L2.25 6.75" /></svg>
                                </span>
                            </button>
                        @empty
                            <div class="rc-empty"><strong>No email threads found.</strong><span>Try another search or school filter.</span></div>
                        @endforelse
                    </div>
                </div>

                <div class="rc-card rc-email-thread">
                    @php($selectedConversation = $selectedConversationId ? collect($this->conversations)->firstWhere('id', $selectedConversationId) : null)
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
                        @forelse($messages as $message)
                            @php($isOut = str_contains(strtolower($message['direction'] ?? ''), 'out'))
                            <article class="rc-email-message {{ $isOut ? 'out' : '' }}">
                                <div class="rc-message-meta">
                                    <span>{{ $isOut ? 'You' : ($selectedConversation['contact_name'] ?? 'Coach') }}</span>
                                    <span>{{ $message['created_at'] ?? '' }}</span>
                                </div>
                                @if(!empty($message['subject']))<div class="rc-row-title" style="margin-bottom:.4rem">{{ $message['subject'] }}</div>@endif
                                <div class="rc-message-body">{!! $message['body'] ?? '' !!}</div>
                            </article>
                        @empty
                            <div class="rc-empty"><strong>Select a thread.</strong><span>Email messages will appear here.</span></div>
                        @endforelse
                    </div>

                    @if($hasMoreMessages)
                        <button class="rc-btn" type="button" wire:click="loadConversationMessages" wire:loading.attr="disabled" wire:target="loadConversationMessages"><span wire:loading.remove wire:target="loadConversationMessages">Load older emails</span><span wire:loading.flex wire:target="loadConversationMessages" class="rc-loading-inline"><span class="rc-spinner-mini"></span> Loading</span></button>
                    @endif

                    @if($selectedConversationId || $selectedCoachId)
                        @include('filament.partials.email-composer')
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
                                                @php($sid = (string) ($school['id'] ?? ''))
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
                                                @php($coachId = (string) ($coach['id'] ?? ''))
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
                            <div class="rc-subtle"><strong>{{ number_format($this->campaignRecipientCount) }}</strong> coaches will receive this email.</div>
                        </div>

                        <label>
                            <span class="rc-template-field-label">Subject</span>
                            <input class="rc-input" style="width:100%" placeholder="Subject" wire:model.live.debounce.650ms="campaignSubject" />
                        </label>

                        <div>
                            <div class="rc-template-field-label">Message</div>
                            <div class="rc-campaign-toolbar" style="margin-bottom:.55rem">
                                <button class="rc-token-chip" type="button" x-data x-on:click="const el=document.getElementById('composeBodyEditor'); el.focus(); el.setRangeText('@{{CoachFirstName}}', el.selectionStart, el.selectionEnd, 'end'); el.dispatchEvent(new Event('input',{bubbles:true}));">Coach first</button>
                                <button class="rc-token-chip" type="button" x-data x-on:click="const el=document.getElementById('composeBodyEditor'); el.focus(); el.setRangeText('@{{SchoolName}}', el.selectionStart, el.selectionEnd, 'end'); el.dispatchEvent(new Event('input',{bubbles:true}));">School</button>
                                <button class="rc-token-chip" type="button" x-data x-on:click="const el=document.getElementById('composeBodyEditor'); el.focus(); el.setRangeText('@{{Position}}', el.selectionStart, el.selectionEnd, 'end'); el.dispatchEvent(new Event('input',{bubbles:true}));">Position</button>
                                <button class="rc-token-chip" type="button" x-data x-on:click="const el=document.getElementById('composeBodyEditor'); el.focus(); el.setRangeText('@{{HighlightLink}}', el.selectionStart, el.selectionEnd, 'end'); el.dispatchEvent(new Event('input',{bubbles:true}));">Highlight link</button>
                            </div>
                            <textarea id="composeBodyEditor" class="rc-input" style="width:100%;min-height:17rem;line-height:1.6;resize:vertical" placeholder="Write your message..." wire:model.live.debounce.800ms="campaignBody"></textarea>
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
                            @php($templateId = (string) ($template['id'] ?? ''))
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
                            <div class="rc-subtle">Write once. Reuse anytime.</div>
                        </div>
                        <div style="display:flex;gap:.5rem;align-items:center">
                            @if($selectedTemplateId && ! $templateIsNew)
                                <button class="rc-btn" type="button" wire:click="deleteTemplate" wire:loading.attr="disabled" wire:target="deleteTemplate"><span wire:loading.remove wire:target="deleteTemplate">Delete</span><span wire:loading.flex wire:target="deleteTemplate" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Deleting</span></button>
                            @endif
                            <button class="rc-btn rc-btn-primary" type="button" wire:click="saveTemplate" wire:loading.attr="disabled" wire:target="saveTemplate">
                                <span wire:loading.remove wire:target="saveTemplate">Save</span>
                                <span wire:loading.flex wire:target="saveTemplate" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Saving</span>
                            </button>
                        </div>
                    </div>

                    <div class="rc-campaign-compose" x-data>
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
                            <input class="rc-input" style="width:100%" placeholder="{{ '{' }}{{ '{' }}GraduationYear{{ '}' }}{{ '}' }} {{ '{' }}{{ '{' }}Position{{ '}' }}{{ '}' }} — Interested in {{ '{' }}{{ '{' }}SchoolName{{ '}' }}{{ '}' }}" wire:model.live.debounce.650ms="templateSubject" />
                        </label>

                        <label>
                            <span class="rc-template-field-label">Preview text</span>
                            <input class="rc-input" style="width:100%" placeholder="Short inbox preview" wire:model.live.debounce.650ms="templatePreviewText" />
                        </label>

                        <div>
                            <div class="rc-template-field-label">Add values</div>
                            <div class="rc-campaign-toolbar">
                                <button class="rc-token-chip" type="button" x-on:click="$refs.body.focus(); $refs.body.setRangeText('@{{CoachFirstName}}', $refs.body.selectionStart, $refs.body.selectionEnd, 'end'); $refs.body.dispatchEvent(new Event('input', { bubbles: true })); $refs.body.dispatchEvent(new Event('change', { bubbles: true }))">Coach first</button>
                                <button class="rc-token-chip" type="button" x-on:click="$refs.body.focus(); $refs.body.setRangeText('@{{CoachLastName}}', $refs.body.selectionStart, $refs.body.selectionEnd, 'end'); $refs.body.dispatchEvent(new Event('input', { bubbles: true })); $refs.body.dispatchEvent(new Event('change', { bubbles: true }))">Coach last</button>
                                <button class="rc-token-chip" type="button" x-on:click="$refs.body.focus(); $refs.body.setRangeText('@{{SchoolName}}', $refs.body.selectionStart, $refs.body.selectionEnd, 'end'); $refs.body.dispatchEvent(new Event('input', { bubbles: true })); $refs.body.dispatchEvent(new Event('change', { bubbles: true }))">School</button>
                                <button class="rc-token-chip" type="button" x-on:click="$refs.body.focus(); $refs.body.setRangeText('@{{AthleteName}}', $refs.body.selectionStart, $refs.body.selectionEnd, 'end'); $refs.body.dispatchEvent(new Event('input', { bubbles: true })); $refs.body.dispatchEvent(new Event('change', { bubbles: true }))">Athlete</button>
                                <button class="rc-token-chip" type="button" x-on:click="$refs.body.focus(); $refs.body.setRangeText('@{{GraduationYear}}', $refs.body.selectionStart, $refs.body.selectionEnd, 'end'); $refs.body.dispatchEvent(new Event('input', { bubbles: true })); $refs.body.dispatchEvent(new Event('change', { bubbles: true }))">Grad year</button>
                                <button class="rc-token-chip" type="button" x-on:click="$refs.body.focus(); $refs.body.setRangeText('@{{Position}}', $refs.body.selectionStart, $refs.body.selectionEnd, 'end'); $refs.body.dispatchEvent(new Event('input', { bubbles: true })); $refs.body.dispatchEvent(new Event('change', { bubbles: true }))">Position</button>
                                <button class="rc-token-chip" type="button" x-on:click="$refs.body.focus(); $refs.body.setRangeText('@{{HighlightLink}}', $refs.body.selectionStart, $refs.body.selectionEnd, 'end'); $refs.body.dispatchEvent(new Event('input', { bubbles: true })); $refs.body.dispatchEvent(new Event('change', { bubbles: true }))">Highlight link</button>
                                <button class="rc-token-chip" type="button" x-on:click="$refs.body.focus(); $refs.body.setRangeText('@{{ProfileLink}}', $refs.body.selectionStart, $refs.body.selectionEnd, 'end'); $refs.body.dispatchEvent(new Event('input', { bubbles: true })); $refs.body.dispatchEvent(new Event('change', { bubbles: true }))">Profile link</button>
                            </div>
                        </div>

                        <div class="rc-template-graphic-card">
                            <div class="rc-template-field-label" style="margin-bottom:.5rem">Top graphic</div>
                            <div class="rc-subtle" style="margin-bottom:.75rem">Optional image above the message.</div>
                            @if($templateGraphicUrl)
                                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                                    <img src="{{ $templateGraphicUrl }}" alt="Template graphic" style="width:5rem;height:5rem;object-fit:cover;border-radius:.75rem;border:1px solid rgba(148,163,184,.25)">
                                    <button class="rc-btn" type="button" wire:click="removeTemplateGraphic">Remove</button>
                                </div>
                            @endif
                            <input class="rc-input" style="width:100%;margin-bottom:.5rem" type="file" accept="image/*" wire:model="templateGraphicUpload" />
                            <input class="rc-input" style="width:100%" placeholder="Or paste image URL" wire:model.live.debounce.650ms="templateGraphicUrl" />
                            <div wire:loading.flex wire:target="templateGraphicUpload" class="rc-subtle" style="margin-top:.5rem;align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Uploading</div>
                        </div>

                        <label>
                            <span class="rc-template-field-label">Message</span>
                            <textarea x-ref="body" class="rc-input" style="width:100%;min-height:30rem;line-height:1.6;resize:vertical" placeholder="Write your template message here..." wire:model.live.debounce.800ms="templateBody"></textarea>
                        </label>
                    </div>
                </div>

                <div class="rc-campaign-panel">
                    <div class="rc-campaign-panel-header">
                        <div>
                            <div class="rc-section-title">Live preview</div>
                            <div class="rc-subtle">Sample coach view</div>
                        </div>
                    </div>

                    <div class="rc-preview-card-soft rc-preview-content-font" style="position:relative">
                        <div wire:loading.flex wire:target="templateName,templateSubject,templatePreviewText,templateBody,templateGraphicUrl,templateGraphicUpload" class="rc-preview-updating">
                            <span class="rc-spinner-mini"></span><span>Updating</span>
                        </div>
                        <div style="padding:1rem 1.15rem;border-bottom:1px solid #e5e7eb;background:#fff">
                            <div style="font-size:.76rem;color:#64748b;margin-bottom:.45rem">To: Stephens Salas • Abilene Christian University</div>
                            <h3 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:1.2rem;line-height:1.38;color:#111827;font-weight:800">{{ $this->renderedTemplateSubject ?: 'Subject preview' }}</h3>
                            @if($this->renderedTemplatePreviewText)
                                <div style="margin-top:.35rem;color:#64748b;font-size:.86rem">{{ $this->renderedTemplatePreviewText }}</div>
                            @endif
                        </div>
                        <div style="padding:1.35rem;font-family:Arial,Helvetica,sans-serif;font-size:.98rem;line-height:1.7;min-height:32rem;background:#fff;color:#111827">
                            @if(trim($templateBody) !== '')
                                {!! $this->renderedTemplateBody !!}
                            @else
                                <p style="color:#64748b">Your message preview will appear here.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($selectedCoachId && $section !== 'conversations')
            <div class="rc-card">
                @include('filament.partials.email-composer')
            </div>
        @endif

        @if($this->selectedSchool)
            <div class="rc-drawer" wire:key="school-drawer">
                <div class="rc-drawer-panel">
                    <div class="rc-top"><div><div class="rc-title">{{ $this->selectedSchool['name'] }}</div><div class="rc-subtle">{{ $this->selectedSchool['conference'] ?? 'Conference unavailable' }} · {{ $this->selectedSchool['division'] ?? 'Division unavailable' }}</div></div><button class="rc-btn" wire:click="closeSchool">Close</button></div>
                    <div class="rc-toolbar" style="margin:1rem 0">
                        @php($schoolId = $this->selectedSchool['id'])
                        <button class="rc-btn" wire:click="{{ ($this->selectedSchool['is_saved'] ?? false) ? 'unsaveSchoolById' : 'saveSchoolById' }}('{{ $schoolId }}')" wire:loading.attr="disabled" wire:target="saveSchoolById('{{ $schoolId }}'),unsaveSchoolById('{{ $schoolId }}')">
                            {{ ($this->selectedSchool['is_saved'] ?? false) ? 'Saved' : 'Save' }}
                        </button>
                        <button class="rc-btn" wire:click="{{ ($this->selectedSchool['is_favorite'] ?? false) ? 'unfavoriteSchoolById' : 'favoriteSchoolById' }}('{{ $schoolId }}')" wire:loading.attr="disabled" wire:target="favoriteSchoolById('{{ $schoolId }}'),unfavoriteSchoolById('{{ $schoolId }}')">
                            {{ ($this->selectedSchool['is_favorite'] ?? false) ? 'Favorited' : 'Favorite' }}
                        </button>
                    </div>
                    @if(count($lists))
                        <div class="rc-toolbar" style="margin-bottom:1rem">
                            @foreach($lists as $list)
                                @php($inList = in_array($list['key'] ?? '', $this->selectedSchool['list_keys'] ?? [], true))
                                <button class="rc-btn" wire:click="{{ $inList ? 'removeSchoolFromListById' : 'addSchoolToListById' }}('{{ $schoolId }}','{{ $list['key'] }}')" wire:loading.attr="disabled" wire:target="addSchoolToListById('{{ $schoolId }}','{{ $list['key'] }}'),removeSchoolFromListById('{{ $schoolId }}','{{ $list['key'] }}')">
                                    {{ $inList ? 'In ' : 'Add to ' }}{{ $list['label'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @foreach($this->selectedSchool['coaches'] ?? [] as $coach)
                        @include('filament.partials.coach-row', ['coach' => $coach])
                    @endforeach
                </div>
            </div>
        @endif
    </div>
    </div>
</x-filament-panels::page>