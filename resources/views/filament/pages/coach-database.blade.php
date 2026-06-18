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
            grid-template-columns: minmax(250px, 320px) minmax(0, 1fr) minmax(280px, 360px);
            gap: 1rem;
            align-items: start;
        }

        .rc-campaign-panel {
            border: 1px solid var(--rc-border);
            border-radius: 1.1rem;
            background: linear-gradient(180deg, rgba(255,255,255,.055), rgba(255,255,255,.02));
            box-shadow: var(--rc-shadow);
            overflow: hidden;
        }

        .rc-campaign-panel-header {
            padding: 1rem 1rem .75rem;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
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
        }

        .rc-template-item,
        .rc-picker-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: .9rem;
            padding: .65rem;
            background: rgba(255,255,255,.026);
            transition: border-color .15s ease, background .15s ease, transform .15s ease;
        }

        .rc-template-item:hover,
        .rc-picker-row:hover {
            border-color: rgba(255, 91, 50, .42);
            background: rgba(255, 91, 50, .055);
        }

        .rc-template-item.is-selected {
            border-color: rgba(255, 91, 50, .75);
            background: rgba(255, 91, 50, .1);
        }

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
            gap: .8rem;
            padding: 1rem;
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
            <div class="rc-grid rc-stats">
                <div class="rc-card is-flat"><div class="rc-stat-number">{{ number_format($stats['total_schools'] ?? $loadedSchoolsCount) }}</div><div class="rc-subtle">Schools</div></div>
                <div class="rc-card is-flat"><div class="rc-stat-number">{{ number_format($stats['total_coaches'] ?? $loadedContactsCount) }}</div><div class="rc-subtle">Coaches</div></div>
                <div class="rc-card is-flat"><div class="rc-stat-number">{{ number_format($stats['saved_schools'] ?? 0) }}</div><div class="rc-subtle">Saved schools</div></div>
                <div class="rc-card is-flat"><div class="rc-stat-number">{{ number_format($stats['favorite_schools'] ?? 0) }}</div><div class="rc-subtle">Favorite schools</div></div>
                <div class="rc-card is-flat"><div class="rc-stat-number">{{ number_format($stats['saved_coaches'] ?? 0) }}</div><div class="rc-subtle">Saved coaches</div></div>
                <div class="rc-card is-flat"><div class="rc-stat-number">{{ number_format($stats['favorite_coaches'] ?? 0) }}</div><div class="rc-subtle">Favorite coaches</div></div>
            </div>
            <div class="rc-card">
                <div class="rc-section-title">Recently loaded schools</div>
                @include('filament.partials.coach-database-school-grid', ['schools' => $this->dashboardSchools])
            </div>
        @endif

        @if($section === 'schools')
            <div class="rc-card rc-toolbar is-flat">
                <input class="rc-input" placeholder="Search schools" wire:model.live.debounce.400ms="search" />
                <select class="rc-select" wire:model.live="divisionFilter"><option value="">All divisions</option>@foreach($this->divisions as $division)<option value="{{ $division }}">{{ $division }}</option>@endforeach</select>
                <select class="rc-select" wire:model.live="conferenceFilter"><option value="">All conferences</option>@foreach($this->conferences as $conference)<option value="{{ $conference }}">{{ $conference }}</option>@endforeach</select>
            </div>

            <div class="rc-card">
                @include('filament.partials.coach-database-school-grid', ['schools' => $this->filteredSchools])
                @if($this->canLoadMoreSchools)<div style="margin-top:1rem"><button class="rc-btn" wire:click="loadMoreSchools">Load more</button></div>@endif
            </div>
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
            <div class="rc-chat" wire:poll.12s.visible="pollConversationUpdates">
                <div class="rc-card">
                    <div class="rc-toolbar" style="margin-bottom:.75rem">
                        <input class="rc-input" placeholder="Search conversations" wire:model.live.debounce.500ms="conversationSearch" />
                        <button class="rc-btn rc-btn-primary" type="button" wire:click="startNewConversation">Start new</button>
                    </div>
                    @if($showNewConversationComposer)
                        <div class="rc-card is-flat" style="margin-bottom:.75rem">
                            <div class="rc-section-title">Choose a coach</div>
                            <input class="rc-input" style="width:100%;margin-bottom:.5rem" placeholder="Search coaches by name, school, or email" wire:model.live.debounce.300ms="newConversationCoachSearch" />
                            <div class="rc-mini-list">
                                @forelse($this->newConversationCoachResults as $coach)
                                    <button type="button" class="rc-row rc-thread-button" wire:click="selectCoachForNewConversation('{{ $coach['id'] }}')">
                                        <span><strong>{{ $coach['name'] }}</strong><br><span class="rc-subtle">{{ $coach['school'] ?? 'School unavailable' }} · {{ $coach['email'] }}</span></span>
                                    </button>
                                @empty
                                    <div class="rc-subtle">No coaches with email found.</div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                    @forelse($conversations as $conversation)
                        <button type="button" class="rc-row rc-thread-button" wire:click="selectConversation('{{ $conversation['id'] }}')" wire:loading.attr="disabled" wire:target="selectConversation('{{ $conversation['id'] }}')">
                            <span><strong>{{ $conversation['contact_name'] }}</strong><br><span class="rc-subtle">{{ $conversation['last_message'] }}</span></span>
                            <span class="rc-pill">{{ $conversation['status'] }}</span>
                        </button>
                    @empty
                        <div class="rc-subtle">No conversations found.</div>
                    @endforelse
                </div>
                <div class="rc-card">
                    <div class="rc-thread">
                        @forelse($messages as $message)
                            <div class="rc-message {{ str_contains(strtolower($message['direction'] ?? ''), 'out') ? 'out' : '' }}">
                                @if($message['subject'])<strong>{{ $message['subject'] }}</strong><br>@endif
                                <div>{!! $message['body'] !!}</div>
                                <div class="rc-subtle">{{ $message['status'] }} {{ $message['created_at'] }}</div>
                            </div>
                        @empty
                            <div class="rc-subtle">Select a conversation to view emails.</div>
                        @endforelse
                        @if($hasMoreMessages)
                            <div style="margin-top:.75rem"><button class="rc-btn" type="button" wire:click="loadConversationMessages" wire:loading.attr="disabled" wire:target="loadConversationMessages"><span wire:loading.remove wire:target="loadConversationMessages">Load older emails</span><span wire:loading.flex wire:target="loadConversationMessages" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Loading</span></button></div>
                        @endif
                    </div>
                    @if($selectedConversationId || $selectedCoachId)
                        @include('filament.partials.email-composer')
                    @endif
                </div>
            </div>
        @endif

        @if($section === 'campaigns')
            <div class="rc-campaign-shell">
                <div class="rc-campaign-panel">
                    <div class="rc-campaign-panel-header">
                        <div>
                            <div class="rc-section-title">Emails</div>
                            <div class="rc-subtle">{{ count($templates) }} emails</div>
                        </div>
                        <button class="rc-btn" type="button" wire:click="loadTemplates" wire:loading.attr="disabled" wire:target="loadTemplates">
                            <span wire:loading.remove wire:target="loadTemplates">Refresh</span>
                            <span wire:loading.flex wire:target="loadTemplates" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Loading</span>
                        </button>
                    </div>

                    <div wire:loading.flex wire:target="loadTemplates" class="rc-campaign-loading">
                        <span class="rc-spinner-mini"></span>
                        <span>Loading emails...</span>
                    </div>

                    <div class="rc-template-list" wire:loading.class="opacity-60" wire:target="loadTemplates,previewTemplate,useTemplate">
                        @forelse($templates as $template)
                            @php($templateId = (string) ($template['id'] ?? ''))
                            <div class="rc-template-item {{ $campaignTemplateId === $templateId || $previewTemplateId === $templateId ? 'is-selected' : '' }}" wire:key="template-{{ $templateId }}">
                                <div class="rc-template-icon">{{ strtoupper(substr((string) ($template['name'] ?? 'T'), 0, 1)) }}</div>
                                <button type="button" class="rc-template-main" wire:click="previewTemplate(@js($templateId))" wire:loading.attr="disabled" wire:target="previewTemplate,useTemplate">
                                    <strong>{{ $template['name'] ?? 'Untitled Template' }}</strong>
                                    <span>{{ $template['subject'] ?? $template['subjectLine'] ?? 'Ready to edit' }}</span>
                                </button>
                                <button type="button" class="rc-btn rc-btn-primary" wire:click="useTemplate(@js($templateId))" wire:loading.attr="disabled" wire:target="useTemplate">
                                    <span wire:loading.remove wire:target="useTemplate">Use</span>
                                    <span wire:loading.flex wire:target="useTemplate" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span></span>
                                </button>
                            </div>
                        @empty
                            <div class="rc-empty"><strong>No emails found.</strong><span>Try Refresh.</span></div>
                        @endforelse
                    </div>
                </div>

                <div class="rc-campaign-panel">
                    <div class="rc-campaign-panel-header">
                        <div>
                            <div class="rc-section-title">Write email</div>
                            <div class="rc-subtle">Edit the email before sending.</div>
                        </div>
                        @if($this->previewTemplate)
                            <button class="rc-btn" type="button" wire:click="useTemplate(@js((string) ($this->previewTemplate['id'] ?? '')))" wire:loading.attr="disabled" wire:target="useTemplate">
                                <span wire:loading.remove wire:target="useTemplate">Use selected</span>
                                <span wire:loading.flex wire:target="useTemplate" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Applying</span>
                            </button>
                        @endif
                    </div>

                    <div class="rc-campaign-compose">
                        @if($campaignTemplateId)
                            <span class="rc-template-pill">Using: {{ $campaignName ?: 'Campaign draft' }}</span>
                        @else
                            <div class="rc-campaign-helper"><strong>Choose an email</strong><br>Select one on the left, then edit it here.</div>
                        @endif

                        <input class="rc-input" style="width:100%" placeholder="Campaign name" wire:model.live="campaignName" />
                        <input class="rc-input" style="width:100%" placeholder="Subject line" wire:model.live="campaignSubject" />
                        <input class="rc-input" style="width:100%" placeholder="Preview text" wire:model.live="campaignPreviewText" />

                        <div>
                            <div class="rc-subtle" style="margin-bottom:.45rem">Insert variables</div>
                            <div class="rc-campaign-toolbar">
                                <button class="rc-token-chip" type="button" wire:click="insertCampaignVariable(@js('{'.'{coach_name}'.'}'))">Coach name</button>
                                <button class="rc-token-chip" type="button" wire:click="insertCampaignVariable(@js('{'.'{first_name}'.'}'))">First name</button>
                                <button class="rc-token-chip" type="button" wire:click="insertCampaignVariable(@js('{'.'{school}'.'}'))">School</button>
                                <button class="rc-token-chip" type="button" wire:click="insertCampaignVariable(@js('{'.'{email}'.'}'))">Email</button>
                            </div>
                        </div>

                        <div>
                            <div class="rc-subtle" style="margin-bottom:.45rem">Email editor</div>
                            <div wire:key="campaign-live-editor-shell-{{ $campaignTemplateId ?: 'draft' }}-{{ md5($campaignOriginalHtml) }}">
                                <div
                                    class="rc-template-live-editor-shell"
                                    wire:ignore
                                    x-data="{
                                        timer: null,
                                        initialHtml: @js($this->campaignEditablePreview),
                                        loadEditor() {
                                            this.$refs.frame.srcdoc = this.initialHtml;
                                        },
                                        receiveEdit(event) {
                                            if (! event.data || event.data.type !== 'plyr-campaign-html') return;
                                            clearTimeout(this.timer);
                                            this.timer = setTimeout(() => this.$wire.set('campaignBody', event.data.html, false), 180);
                                        },
                                        sendCommand(command) {
                                            this.$refs.frame.contentWindow?.postMessage({ type: 'plyr-campaign-command', command: command }, '*');
                                        }
                                    }"
                                    x-init="loadEditor(); window.addEventListener('message', receiveEdit)"
                                >
                                    <div class="rc-visual-editor-toolbar">
                                        <button class="rc-visual-tool" type="button" title="Bold" @click="sendCommand('bold')">B</button>
                                        <button class="rc-visual-tool" type="button" title="Italic" @click="sendCommand('italic')"><em>I</em></button>
                                        <button class="rc-visual-tool" type="button" title="Underline" @click="sendCommand('underline')"><u>U</u></button>
                                        <button class="rc-visual-tool" type="button" title="Bullet list" @click="sendCommand('insertUnorderedList')">• List</button>
                                        <button class="rc-visual-tool" type="button" title="Numbered list" @click="sendCommand('insertOrderedList')">1. List</button>
                                        <button class="rc-visual-tool" type="button" title="Link" @click="sendCommand('createLink')">Link</button>
                                    </div>
                                    <div class="rc-template-live-editor-wrap">
                                        <iframe
                                            x-ref="frame"
                                            class="rc-template-live-editor"
                                            sandbox="allow-same-origin allow-scripts allow-popups allow-popups-to-escape-sandbox"
                                        ></iframe>
                                    </div>
                                </div>
                            </div>
                            <div class="rc-subtle" style="margin-top:.4rem">Click inside the email to edit text. Click an image to change it.</div>
                        </div>

                    </div>
                </div>

                <div class="rc-campaign-panel">
                    <div class="rc-campaign-panel-header">
                        <div>
                            <div class="rc-section-title">Review + send</div>
                            <div class="rc-subtle">Choose recipients.</div>
                        </div>
                    </div>

                    <div class="rc-target-card">
                        <div class="rc-recipient-stat">
                            <strong>{{ $campaignSubject !== '' ? 'Ready' : 'Draft' }}</strong>
                            <span class="rc-subtle">Edit the email on the left, then choose who receives it.</span>
                        </div>

                        <div class="rc-subtle">Recipients</div>
                        <select class="rc-select" style="width:100%" wire:model.live="campaignTargetMode">
                            <option value="coaches">Selected coaches</option>
                            <option value="list">A saved list</option>
                            <option value="school">A school</option>
                            <option value="all">All loaded coaches</option>
                        </select>

                        @if($campaignTargetMode === 'coaches')
                            <input class="rc-input" style="width:100%" placeholder="Search coaches" wire:model.live.debounce.300ms="campaignCoachSearch" />
                            <div class="rc-picker-list" style="max-height:17rem;padding:0">
                                @foreach($this->campaignCoachResults as $coach)
                                    <label class="rc-picker-row" wire:key="campaign-coach-{{ $coach['id'] }}">
                                        <input type="checkbox" value="{{ $coach['id'] }}" wire:model.live="campaignCoachIds" />
                                        <span><strong>{{ $coach['name'] }}</strong><br><small>{{ $coach['school'] ?? 'School unavailable' }} · {{ $coach['email'] }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif($campaignTargetMode === 'list')
                            <select class="rc-select" style="width:100%" wire:model.live="campaignListKey">
                                <option value="">Choose list</option>
                                @foreach($lists as $list)
                                    <option value="{{ $list['key'] }}">{{ $list['label'] }} · {{ number_format($list['coaches_count'] ?? 0) }} coaches</option>
                                @endforeach
                            </select>
                        @elseif($campaignTargetMode === 'school')
                            <select class="rc-select" style="width:100%" wire:model.live="campaignSchoolId">
                                <option value="">Choose school</option>
                                @foreach($this->filteredSchools as $school)
                                    <option value="{{ $school['id'] }}">{{ $school['name'] }} · {{ number_format($school['coach_count'] ?? 0) }} coaches</option>
                                @endforeach
                            </select>
                        @else
                            <div class="rc-empty is-small"><strong>{{ number_format($this->campaignRecipientCount) }} loaded coaches with email</strong><span>Sends to every loaded coach with an email.</span></div>
                        @endif

                        <div class="rc-recipient-stat">
                            <strong>{{ number_format($this->campaignRecipientCount) }}</strong>
                            <span class="rc-subtle">recipients selected</span>
                        </div>

                        <div class="rc-grid" style="grid-template-columns:1fr;gap:.5rem">
                            @if($campaignTemplateId)
                                <button class="rc-btn" type="button" wire:click="clearCampaignTemplate">Clear template</button>
                            @endif

                            <button class="rc-btn rc-btn-primary" type="button" wire:click="sendCampaign" wire:loading.attr="disabled" wire:target="sendCampaign">
                                <span wire:loading.remove wire:target="sendCampaign">Send campaign</span>
                                <span wire:loading.flex wire:target="sendCampaign" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Sending</span>
                            </button>
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