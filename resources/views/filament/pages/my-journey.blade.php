<x-filament-panels::page>
    @php
        $plans = $this->getPlans();
        $addons = $this->getAddons();
        $pointPacks = collect($this->getPointPacks());
        $pointBalance = $this->getPointBalance();
        $hasJourney = $this->getCurrentPlanKey() === 'my_journey';
        $serviceCatalog = collect(app(\App\Services\CreditPointService::class)->catalog())->values();
        $pointWorth = $serviceCatalog->map(fn (array $service): array => array_merge($service, ['count_for_balance' => intdiv(max(0, $pointBalance), max(1, (int) ($service['points'] ?? 0)))]));
        $money = fn (int $cents) => '$' . number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
        $jumpstartPlan = collect($plans)->firstWhere('key', 'jumpstart');
        $amplifyPlan = collect($plans)->firstWhere('key', 'amplify');
        $pointHistory = collect();
        if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('credit_point_transactions')) {
            $historyRequests = \Illuminate\Support\Facades\Schema::hasTable('credit_service_requests')
                ? \App\Models\CreditServiceRequest::query()->where('user_id', auth()->id())->latest('id')->limit(50)->get()->keyBy(fn ($request) => (string) $request->getKey())
                : collect();
            $pointHistory = \App\Models\CreditPointTransaction::query()->where('user_id', auth()->id())->latest('id')->limit(12)->get()->map(function ($row) use ($historyRequests) {
                $meta = (array) ($row->meta ?? []);
                $sign = in_array($row->type, ['grant', 'refund', 'release'], true) ? 1 : (in_array($row->type, ['debit', 'hold'], true) ? -1 : ((int) ($meta['adjust_sign'] ?? 1) < 0 ? -1 : 1));
                $request = $row->source_type === 'service_request' ? $historyRequests->get((string) $row->source_id) : null;
                $status = match (true) {
                    $request !== null => $request->statusLabel(),
                    in_array($row->source_type, ['package_purchase', 'top_up_purchase'], true) => 'Added',
                    $row->type === 'refund' || $row->type === 'release' => 'Returned',
                    $row->type === 'adjust' => 'Adjusted',
                    $row->type === 'hold' => 'Reserved',
                    $row->type === 'debit' => 'Used',
                    default => ucfirst(str_replace('_', ' ', (string) $row->type)),
                };
                return [
                    'date' => optional($row->created_at)->format('M j, Y') ?: '—',
                    'item' => trim((string) ($row->reason ?: data_get($meta, 'item_name', 'PLYRCARD points activity'))),
                    'amount' => ($sign > 0 ? '+' : '−') . number_format((int) $row->points),
                    'positive' => $sign > 0,
                    'status' => $status,
                ];
            })->values();
        }
        function mjIcon($name) {
            return match ($name) {
                'user' => '<svg xmlns="http://www\.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.118a7.5 7.5 0 0 1 15 0A17.933 17.933 0 0 1 12 21.75a17.933 17.933 0 0 1-7.5-1.632Z" /></svg>',
                'bolt' => '<svg xmlns="http://www\.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z" /></svg>',
                'crown' => '<svg xmlns="http://www\.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M5 16h14l1 3H4l1-3Zm14-9 1 7H4l1-7 4 3 3-5 3 5 4-3Z" /></svg>',
                'sparkles' => '<svg xmlns="http://www\.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m9.813 15.904.782-2.347a1.5 1.5 0 0 1 .949-.949l2.347-.782-2.347-.782a1.5 1.5 0 0 1-.949-.949l-.782-2.347-.782 2.347a1.5 1.5 0 0 1-.949.949l-2.347.782 2.347.782a1.5 1.5 0 0 1 .949.949l.782 2.347ZM18.259 8.715 18 9.5l-.259-.785A1.125 1.125 0 0 0 17 8l-.785-.259L17 7.482c.337-.112.603-.378.715-.715L18 6l.259.767c.112.337.378.603.715.715L19.759 8 19 8.259a1.125 1.125 0 0 0-.741.456ZM16.5 20.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9A2.25 2.25 0 0 1 7.5 6.75h9A2.25 2.25 0 0 1 18.75 9v9a2.25 2.25 0 0 1-2.25 2.25Z" /></svg>',
                'photo' => '<svg xmlns="http://www\.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0L21.75 15.75m-16.5 4.5h13.5A2.25 2.25 0 0 0 21 18V6A2.25 2.25 0 0 0 18.75 3.75H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Zm10.5-11.25h.008v.008h-.008V9Z" /></svg>',
                'globe-alt' => '<svg xmlns="http://www\.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18m0 18c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m-8.25 9h16.5" /></svg>',
                default => '',
            };
        }
    @endphp
    <style>
        .mj-wrap {
            --mj-bg: #f7f8fb;
            --mj-panel: #ffffff;
            --mj-panel-2: #f3f5f8;
            --mj-text: #101828;
            --mj-heading: #0b1220;
            --mj-muted: #667085;
            --mj-soft: #98a2b3;
            --mj-border: rgba(15, 23, 42, .11);
            --mj-border-strong: rgba(15, 23, 42, .18);
            --mj-shadow: 0 18px 45px rgba(15, 23, 42, .08);
            --mj-shadow-soft: 0 10px 28px rgba(15, 23, 42, .06);
            --mj-orange: #ff6338;
            --mj-orange-dark: #e34e29;
            --mj-orange-soft: rgba(255, 99, 56, .10);
            --mj-blue: #0ea5e9;
            --mj-blue-soft: rgba(14, 165, 233, .10);
            --mj-gold: #d89b17;
            --mj-gold-soft: rgba(216, 155, 23, .13);
            --mj-green: #16a34a;
            --mj-green-soft: rgba(22, 163, 74, .10);
            color: var(--mj-text);
        }
        .dark .mj-wrap {
            --mj-bg: #050608;
            --mj-panel: #0b0d10;
            --mj-panel-2: #11141a;
            --mj-text: #f7f8fa;
            --mj-heading: #ffffff;
            --mj-muted: #a0a9b4;
            --mj-soft: #7f8896;
            --mj-border: rgba(255, 255, 255, .08);
            --mj-border-strong: rgba(255, 255, 255, .13);
            --mj-shadow: 0 18px 45px rgba(0, 0, 0, .28);
            --mj-shadow-soft: 0 10px 30px rgba(0, 0, 0, .22);
            --mj-orange-soft: rgba(255, 99, 56, .14);
            --mj-blue-soft: rgba(25, 167, 255, .13);
            --mj-gold: #f5c451;
            --mj-gold-soft: rgba(245, 196, 81, .15);
            --mj-green-soft: rgba(25, 207, 122, .12);
        }
        .mj-wrap,
        .mj-wrap * {
            box-sizing: border-box;
        }
        .mj-wrap {
            width: 100%;
        }
        .mj-hero {
            position: relative;
            overflow: hidden;
            min-height: 180px;
            border: 1px solid rgba(255, 99, 56, .18);
            border-radius: 22px;
            background:
                radial-gradient(circle at top right, rgba(255, 99, 56, .15), transparent 30%),
                linear-gradient(180deg, #ffffff 0%, #fff7f4 100%);
            box-shadow: var(--mj-shadow-soft);
            padding: 2rem;
        }
        .dark .mj-hero {
            border-color: rgba(255, 100, 61, .25);
            background:
                radial-gradient(circle at top right, rgba(255, 100, 61, .16), transparent 25%),
                linear-gradient(180deg, rgba(54, 14, 9, .95) 0%, rgba(28, 9, 9, .98) 100%);
            box-shadow: var(--mj-shadow);
        }
        .mj-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            color: var(--mj-orange);
            font-size: .72rem;
            font-weight: 750;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .mj-hero-title {
            margin-top: .8rem;
            max-width: 760px;
            color: var(--mj-heading);
            font-size: clamp(2rem, 4vw, 3.35rem);
            line-height: .98;
            font-weight: 850;
            letter-spacing: -.02em;
            text-transform: uppercase;
        }
        .mj-hero-title span {
            color: var(--mj-orange);
        }
        .mj-hero-text {
            margin-top: .85rem;
            max-width: 740px;
            color: var(--mj-muted);
            font-size: .96rem;
            line-height: 1.58;
        }
        .mj-hero-badge {
            position: absolute;
            right: 2rem;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .7rem;
            text-align: center;
            color: var(--mj-muted);
        }
        .mj-hero-badge-circle {
            width: 84px;
            height: 84px;
            border-radius: 999px;
            border: 2px solid rgba(255, 99, 56, .48);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--mj-orange);
            box-shadow: 0 0 0 10px rgba(255, 99, 56, .06);
            background: rgba(255, 255, 255, .62);
        }
        .dark .mj-hero-badge-circle {
            background: rgba(255, 255, 255, .03);
            border-color: rgba(255, 100, 61, .65);
        }
        .mj-hero-badge-circle svg {
            width: 32px;
            height: 32px;
        }
        .mj-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1.25rem;
        }
        .mj-card {
            position: relative;
            border-radius: 20px;
            padding: 1.25rem;
            background: var(--mj-panel);
            border: 1px solid var(--mj-border);
            box-shadow: var(--mj-shadow-soft);
            overflow: hidden;
            color: var(--mj-text);
        }
        .dark .mj-card {
            background: linear-gradient(180deg, #0a0c10 0%, #0a0b0f 100%);
            box-shadow: var(--mj-shadow);
        }
        .mj-card.current {
            box-shadow: 0 0 0 1px rgba(255, 99, 56, .12), var(--mj-shadow-soft);
        }
        .dark .mj-card.current {
            box-shadow: 0 0 0 1px rgba(255, 255, 255, .08), var(--mj-shadow);
        }
        .mj-card.orange {
            border-color: rgba(255, 99, 56, .48);
            background:
                radial-gradient(circle at top left, rgba(255, 99, 56, .09), transparent 30%),
                var(--mj-panel);
        }
        .dark .mj-card.orange {
            border-color: rgba(255, 100, 61, .7);
            background:
                radial-gradient(circle at top left, rgba(255, 100, 61, .10), transparent 30%),
                linear-gradient(180deg, #140c0d 0%, #0a0b10 100%);
        }
        .mj-card.blue {
            border-color: rgba(14, 165, 233, .34);
            background:
                radial-gradient(circle at top left, rgba(14, 165, 233, .08), transparent 30%),
                var(--mj-panel);
        }
        .dark .mj-card.blue {
            border-color: rgba(25, 167, 255, .35);
            background:
                radial-gradient(circle at top left, rgba(25, 167, 255, .10), transparent 30%),
                linear-gradient(180deg, #08111a 0%, #0a0f16 100%);
        }
        .mj-card.gold {
            border-color: rgba(216, 155, 23, .40);
            background:
                radial-gradient(circle at top left, rgba(216, 155, 23, .10), transparent 30%),
                var(--mj-panel);
        }
        .dark .mj-card.gold {
            border-color: rgba(245, 196, 81, .55);
            background:
                radial-gradient(circle at top left, rgba(245, 196, 81, .12), transparent 30%),
                linear-gradient(180deg, #171107 0%, #0a0b10 100%);
        }
        .mj-card.current::after {
            content: "Current";
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .11em;
            text-transform: uppercase;
            color: var(--mj-muted);
            padding: .35rem .65rem;
            border-radius: 999px;
            background: var(--mj-panel-2);
            border: 1px solid var(--mj-border);
        }
        .mj-addon-active {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .38rem .7rem;
            border-radius: 999px;
            background: rgba(18, 183, 106, .14);
            border: 1px solid rgba(18, 183, 106, .34);
            color: #12b76a;
            font-size: .72rem;
            line-height: 1;
            font-weight: 900;
            letter-spacing: .07em;
            text-transform: uppercase;
            z-index: 4;
        }
        .mj-addon-active::before {
            content: '';
            width: .48rem;
            height: .48rem;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 0 3px rgba(18, 183, 106, .12);
        }
        .mj-popular {
            position: absolute;
            top: -1px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--mj-orange);
            color: white;
            font-size: .68rem;
            font-weight: 850;
            padding: .32rem .85rem;
            border-radius: 999px;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .mj-plan-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            background: var(--mj-panel-2);
            color: var(--mj-muted);
            border: 1px solid var(--mj-border);
        }
        .mj-plan-icon svg {
            width: 20px;
            height: 20px;
        }
        .mj-card.orange .mj-plan-icon {
            background: var(--mj-orange-soft);
            color: var(--mj-orange);
            border-color: rgba(255, 99, 56, .20);
        }
        .mj-card.blue .mj-plan-icon {
            background: var(--mj-blue-soft);
            color: var(--mj-blue);
            border-color: rgba(14, 165, 233, .20);
        }
        .mj-card.gold .mj-plan-icon {
            background: var(--mj-gold-soft);
            color: var(--mj-gold);
            border-color: rgba(216, 155, 23, .22);
        }
        .mj-name {
            color: var(--mj-heading);
            font-size: 1.9rem;
            font-weight: 850;
            line-height: 1;
            letter-spacing: .015em;
            text-transform: uppercase;
        }
        .mj-price {
            display: flex;
            align-items: flex-end;
            gap: .22rem;
            margin-top: .75rem;
        }
        .mj-price strong {
            color: var(--mj-heading);
            font-size: 3rem;
            font-weight: 850;
            line-height: .9;
            letter-spacing: -.04em;
        }
        .mj-card.orange .mj-price strong { color: var(--mj-orange); }
        .mj-card.blue .mj-price strong { color: var(--mj-blue); }
        .mj-card.gold .mj-price strong { color: var(--mj-gold); }
        .mj-price span {
            color: var(--mj-muted);
            font-size: .92rem;
            padding-bottom: .28rem;
        }
        .mj-tagline {
            margin-top: .35rem;
            color: var(--mj-muted);
            font-size: .93rem;
            line-height: 1.45;
        }
        .mj-features {
            margin-top: 1.1rem;
            display: grid;
            gap: .72rem;
        }
        .mj-feature {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            font-size: .94rem;
            line-height: 1.45;
        }
        .mj-feature.included { color: var(--mj-text); }
        .mj-feature.excluded { color: var(--mj-soft); }
        .mj-feature svg {
            width: 16px;
            height: 16px;
            flex: 0 0 16px;
            margin-top: .18rem;
        }
        .mj-feature.included svg { color: var(--mj-green); }
        .mj-feature.excluded svg { color: var(--mj-soft); }
        .mj-note {
            margin-top: 1rem;
            border: 1px solid var(--mj-border);
            background: var(--mj-panel-2);
            border-radius: 14px;
            padding: .85rem .9rem;
            color: var(--mj-muted);
            font-size: .9rem;
            line-height: 1.45;
        }
        .mj-btn {
            margin-top: 1rem;
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            min-height: 46px;
            padding: .85rem 1rem;
            text-decoration: none;
            font-size: .88rem;
            font-weight: 800;
            letter-spacing: .065em;
            text-transform: uppercase;
            border: 1px solid transparent;
            transition: transform .18s ease, background .18s ease, border-color .18s ease, color .18s ease;
        }
        .mj-btn:hover {
            transform: translateY(-1px);
        }
        .mj-btn.disabled {
            background: var(--mj-panel-2);
            color: var(--mj-soft);
            pointer-events: none;
        }
        .mj-btn.orange {
            background: var(--mj-orange);
            color: white;
            box-shadow: 0 8px 22px rgba(255, 99, 56, .22);
        }
        .mj-btn.orange:hover {
            background: var(--mj-orange-dark);
        }
        .mj-btn.blue {
            background: var(--mj-blue-soft);
            border-color: rgba(14, 165, 233, .32);
            color: var(--mj-blue);
        }
        .mj-btn.blue:hover {
            background: rgba(14, 165, 233, .16);
        }
        .mj-btn.gold {
            background: var(--mj-gold-soft);
            border-color: rgba(216, 155, 23, .35);
            color: var(--mj-gold);
        }
        .mj-btn.gold:hover {
            background: rgba(216, 155, 23, .19);
        }
        .mj-btn.gold.is-disabled {
            background: var(--mj-gold-soft);
            border-color: rgba(216, 155, 23, .35);
            color: var(--mj-gold);
            opacity: .55;
            cursor: not-allowed;
            pointer-events: none;
            box-shadow: none;
            transform: none;
        }
        .mj-btn.ghost {
            background: transparent;
            border-color: var(--mj-border-strong);
            color: var(--mj-heading);
        }
        .mj-btn.ghost:hover {
            background: var(--mj-panel-2);
        }
        .mj-addon-wrap {
            margin-top: 1.4rem;
            border: 1px solid var(--mj-border);
            border-radius: 20px;
            background: var(--mj-panel);
            box-shadow: var(--mj-shadow-soft);
            overflow: hidden;
        }
        .dark .mj-addon-wrap {
            background: linear-gradient(180deg, #0a0c10 0%, #0a0b0f 100%);
            box-shadow: var(--mj-shadow);
        }
        .mj-addon-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            border-bottom: 1px solid var(--mj-border);
        }
        .mj-addon-icon,
        .mj-addon-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--mj-blue-soft);
            color: var(--mj-blue);
            border: 1px solid rgba(14, 165, 233, .18);
        }
        .mj-addon-icon {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            border-radius: 14px;
        }
        .mj-addon-icon svg { width: 20px; height: 20px; }
        .mj-addon-title {
            color: var(--mj-heading);
            font-size: 1.5rem;
            font-weight: 850;
            line-height: 1;
            text-transform: uppercase;
        }
        .mj-addon-subtitle {
            color: var(--mj-muted);
            margin-top: .35rem;
            font-size: .94rem;
            line-height: 1.5;
        }
        .mj-addon-list {
            padding: 1rem 1.5rem 1.5rem;
            display: grid;
            gap: .8rem;
        }
        .mj-addon-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border: 1px solid var(--mj-border);
            background: var(--mj-panel-2);
            border-radius: 14px;
            padding: 1rem 1.1rem;
        }
        .mj-addon-left {
            display: flex;
            align-items: center;
            gap: .9rem;
            min-width: 0;
        }
        .mj-addon-badge {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 12px;
        }
        .mj-addon-badge svg { width: 18px; height: 18px; }
        .mj-addon-name {
            font-size: 1rem;
            font-weight: 750;
            color: var(--mj-heading);
        }
        .mj-addon-desc {
            color: var(--mj-muted);
            font-size: .87rem;
            margin-top: .2rem;
        }
        .mj-addon-right {
            text-align: right;
            flex: 0 0 auto;
        }
        .mj-addon-price {
            color: var(--mj-blue);
            font-size: 1.6rem;
            font-weight: 850;
            line-height: 1;
        }
        .mj-addon-unit {
            margin-top: .18rem;
            color: var(--mj-muted);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .mj-footer-note {
            margin-top: 1rem;
            border: 1px solid rgba(22, 163, 74, .22);
            border-radius: 16px;
            background: var(--mj-green-soft);
            color: var(--mj-text);
            padding: 1rem 1.1rem;
        }
        .mj-footer-note strong {
            display: block;
            color: var(--mj-green);
            font-size: .98rem;
            font-weight: 850;
        }
        .mj-footer-note span {
            display: block;
            margin-top: .2rem;
            color: var(--mj-muted);
            font-size: .9rem;
        }
        @media (max-width: 760px) {
            .mj-grid {
                grid-template-columns: 1fr;
            }
            .mj-hero-badge {
                position: static;
                transform: none;
                margin-top: 1.25rem;
                align-items: flex-start;
                text-align: left;
            }
        }
        @media (max-width: 768px) {
            .mj-hero,
            .mj-card,
            .mj-addon-wrap {
                border-radius: 16px;
            }
            .mj-hero {
                padding: 1.4rem;
            }
            .mj-name {
                font-size: 1.65rem;
            }
            .mj-price strong {
                font-size: 2.55rem;
            }
            .mj-addon-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .mj-addon-right {
                text-align: left;
            }
        }
        .mj-points-overview { margin-top: 1.25rem; display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 1rem; }
        .mj-points-panel { border: 1px solid var(--mj-border); border-radius: 20px; background: var(--mj-panel); box-shadow: var(--mj-shadow-soft); padding: 1.25rem; }
        .dark .mj-points-panel { background: linear-gradient(180deg, #0a0c10 0%, #0a0b0f 100%); box-shadow: var(--mj-shadow); }
        .mj-points-kicker { color: var(--mj-muted); font-size: .7rem; font-weight: 850; letter-spacing: .1em; text-transform: uppercase; }
        .mj-points-balance { display: flex; align-items: flex-end; gap: .55rem; margin-top: .55rem; }
        .mj-points-balance strong { color: var(--mj-orange); font-size: clamp(3rem, 6vw, 4.7rem); line-height: .9; font-weight: 900; letter-spacing: -.055em; }
        .mj-points-balance span { padding-bottom: .42rem; color: var(--mj-muted); font-size: .88rem; font-weight: 700; }
        .mj-points-copy { margin-top: .8rem; padding: .8rem .9rem; border-radius: 12px; background: var(--mj-panel-2); color: var(--mj-muted); font-size: .86rem; line-height: 1.5; }
        .mj-points-actions { display: flex; gap: .55rem; flex-wrap: wrap; margin-top: .85rem; }
        .mj-points-actions .mj-btn { width: auto; min-width: 150px; margin-top: 0; }
        .mj-worth-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
        .mj-worth-head h2, .mj-point-packs h2 { margin: 0; color: var(--mj-heading); font-size: 1.15rem; font-weight: 850; }
        .mj-worth-head p, .mj-point-packs p { margin: .3rem 0 0; color: var(--mj-muted); font-size: .84rem; line-height: 1.5; }
        .mj-worth-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; margin-top: .9rem; }
        .mj-worth-item { padding: .8rem .85rem; border: 1px solid var(--mj-border); border-radius: 12px; background: var(--mj-panel-2); }
        .mj-worth-item strong { display: block; color: var(--mj-heading); font-size: .83rem; }
        .mj-worth-item span { display: block; margin-top: .22rem; color: var(--mj-muted); font-size: .73rem; line-height: 1.4; }
        .mj-point-packs { margin-top: 1.25rem; border: 1px solid var(--mj-border); border-radius: 20px; background: var(--mj-panel); box-shadow: var(--mj-shadow-soft); padding: 1.25rem; }
        .dark .mj-point-packs { background: linear-gradient(180deg, #0a0c10 0%, #0a0b0f 100%); box-shadow: var(--mj-shadow); }
        .mj-pack-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; margin-top: .9rem; }
        .mj-pack { display: grid; gap: .45rem; min-width: 0; padding: 1rem; border: 1px solid var(--mj-border); border-radius: 14px; background: var(--mj-panel-2); } .mj-pack.is-package{background:var(--mj-panel);border-color:var(--mj-border-strong)} .mj-pack.is-featured{background:var(--mj-orange-soft);border:2px solid var(--mj-orange)} .mj-pack-badge{display:inline-flex;width:max-content;padding:.2rem .5rem;border-radius:999px;background:var(--mj-orange);color:#fff;font-size:.62rem;font-weight:850;text-transform:uppercase;letter-spacing:.06em}
        .mj-pack strong { color: var(--mj-orange); font-size: 1.8rem; line-height: 1; }
        .mj-pack-price { color: var(--mj-heading); font-size: .92rem; font-weight: 800; }
        .mj-pack-note { color: var(--mj-muted); font-size: .74rem; line-height: 1.42; min-height: 2.1em; }
        .mj-pack .mj-btn { margin-top: .35rem; }
        .mj-point-gate { margin-top: .9rem; padding: .9rem 1rem; border-radius: 12px; border: 1px solid rgba(255, 99, 56, .18); background: var(--mj-orange-soft); color: var(--mj-muted); font-size: .82rem; line-height: 1.5; }
        .mj-point-history { margin-top:1.25rem; border:1px solid var(--mj-border); border-radius:20px; background:var(--mj-panel); box-shadow:var(--mj-shadow-soft); padding:1.25rem; }
        .mj-history-row { display:grid; grid-template-columns:7rem minmax(0,1fr) 7rem 8rem; gap:.75rem; align-items:center; padding:.72rem 0; border-bottom:1px solid var(--mj-border); font-size:.78rem; }
        .mj-history-row.is-head { color:var(--mj-muted); font-size:.65rem; font-weight:850; letter-spacing:.07em; text-transform:uppercase; }
        .mj-history-amount { font-weight:850; }.mj-history-amount.is-add { color:var(--mj-orange); }
        .mj-history-status { display:inline-flex; width:max-content; padding:.27rem .55rem; border-radius:999px; background:var(--mj-panel-2); font-size:.68rem; font-weight:800; }
        .mj-history-status[data-status*="Completed"],.mj-history-status[data-status="Added"]{background:rgba(16,185,129,.1);color:#15803d}.mj-history-status[data-status*="Progress"],.mj-history-status[data-status="Reviewed"],.mj-history-status[data-status="Submitted"]{background:rgba(255,99,56,.1);color:#c2410c}.mj-history-status[data-status="Declined"]{background:rgba(239,68,68,.1);color:#b42318}.mj-history-status[data-status="Returned"],.mj-history-status[data-status="Adjusted"]{background:rgba(59,130,246,.1);color:#1d4ed8}
        @media (max-width: 1180px) { .mj-pack-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } } @media (max-width: 980px) { .mj-points-overview { grid-template-columns: 1fr; } } @media (max-width: 640px) { .mj-pack-grid { grid-template-columns: 1fr; } .mj-history-row{grid-template-columns:5.5rem minmax(0,1fr) 4.5rem}.mj-history-row>div:last-child{grid-column:2/-1} }
        @media (max-width: 640px) { .mj-worth-grid { grid-template-columns: 1fr; } .mj-points-actions .mj-btn { width: 100%; } }
    </style>
    <div class="mj-wrap">
        <section class="mj-hero">
            <div class="mj-eyebrow">
                <span>🚀</span>
                <span>{{ $this->getHeroEyebrow() }}</span>
            </div>
            <h1 class="mj-hero-title">
                {!! $this->getHeroTitle() !!}
            </h1>
            <p class="mj-hero-text">
                {{ $this->getHeroDescription() }}
            </p>
            <div style="display:inline-flex;align-items:center;gap:.5rem;margin-top:1rem;padding:.58rem .78rem;border-radius:999px;background:var(--mj-panel);border:1px solid var(--mj-border);font-size:.78rem;font-weight:750;">
                <span style="color:var(--mj-orange);">●</span>
                <span>{{ number_format($pointBalance) }} points available</span>
                <span style="color:var(--mj-muted);font-weight:600;">· never expire</span>
            </div>
            <div class="mj-hero-badge">
                <div class="mj-hero-badge-circle">
                    {!! mjIcon('crown') !!}
                </div>
                <div>
                    <div style="font-weight: 800;">{{ $this->getHeroBadgeLabel() }}</div>
                </div>
            </div>
        </section>
        <section class="mj-points-overview">
            <div class="mj-points-panel">
                <div class="mj-points-kicker">Your points</div>
                <div class="mj-points-balance"><strong>{{ number_format($pointBalance) }}</strong><span>points available</span></div>
                <div class="mj-points-copy">Mix and match PLYRCARD services with your points. Your unused balance does not expire.</div>
                <div class="mj-points-actions">
                    <a href="{{ url('/admin/coach-database/services') }}" class="mj-btn orange">Use Points</a>
                    <a href="#buy-points" class="mj-btn ghost">Buy Points</a>
                </div>
            </div>
            <div class="mj-points-panel">
                <div class="mj-worth-head"><div><h2>What your points can get you</h2><p>Live values from the full PLYRCARD service catalog.</p></div></div>
                <div class="mj-worth-grid">
                    @foreach ($pointWorth as $service)
                        <div class="mj-worth-item"><strong>{{ $service['name'] }}</strong><span>{{ number_format((int) $service['points']) }} points each · current balance covers {{ number_format((int) $service['count_for_balance']) }}</span></div>
                    @endforeach
                </div>
            </div>
        </section>
        <section class="mj-grid">
            @foreach ($plans as $plan)
                <article class="mj-card {{ $plan['accent'] }} {{ $plan['current'] ? 'current' : '' }}">
                    @if ($plan['popular'])
                        <div class="mj-popular" @if (($plan['accent'] ?? '') === 'gold') style="background:#f5c451;color:#241701;" @endif>{{ $plan['badge'] ?? 'Most Popular' }}</div>
                    @endif
                    @if (($plan['active_addon'] ?? false))
                        <div class="mj-addon-active">Active</div>
                    @endif
                    <div class="mj-plan-icon">
                        {!! mjIcon($plan['icon']) !!}
                    </div>
                    <div class="mj-name">{{ $plan['name'] }}</div>
                    <div class="mj-price">
                        <strong>{{ $plan['price'] }}</strong>
                        <span>{{ $plan['suffix'] }}</span>
                    </div>
                    <div class="mj-tagline">{{ $plan['tagline'] }}</div>
                    @if (! empty($plan['setup']))
                        <div class="mj-note" style="margin-top:.85rem;">{{ $plan['setup'] }}</div>
                    @endif
                    <div class="mj-features">
                        @foreach ($plan['features'] as $feature)
                            <div class="mj-feature {{ $feature['included'] ? 'included' : 'excluded' }}">
                                @if ($feature['included'])
                                    <svg xmlns="http://www\.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                @else
                                    <svg xmlns="http://www\.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                @endif
                                <span>{{ $feature['text'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mj-note">
                        {{ $plan['note'] }}
                    </div>
                    <a
                        href="{{ $plan['button_href'] }}"
                        class="mj-btn {{ $plan['button_style'] }} {{ ($plan['button_disabled'] ?? false) ? 'is-disabled' : '' }}"
                        @if (($plan['opens_jumpstart_checkout'] ?? false) && ! ($plan['current'] ?? false) && ! ($plan['button_disabled'] ?? false))
                            data-plyrcard-jumpstart-open
                            role="button"
                        @endif
                        @if (($plan['opens_amplify_checkout'] ?? false) && ! ($plan['current'] ?? false) && ! ($plan['button_disabled'] ?? false))
                            data-plyrcard-amplify-open
                            role="button"
                        @endif
                        @if (($plan['opens_my_journey_checkout'] ?? false) && ! ($plan['current'] ?? false) && ! ($plan['button_disabled'] ?? false))
                            data-plyrcard-my-journey-open
                            role="button"
                        @endif
                        @if ($plan['requests_free_downgrade'] ?? false)
                            data-plyrcard-downgrade-free
                            role="button"
                        @endif
                        @if ($plan['button_disabled'] ?? false)
                            aria-disabled="true"
                            tabindex="-1"
                            onclick="return false;"
                        @endif
                    >
                        {{ $plan['button'] }}
                    </a>
                </article>
            @endforeach
        </section>
        <section class="mj-point-packs" id="buy-points">
            <div class="mj-worth-head"><div><h2>Buy points</h2><p>Points land after Stripe confirms payment and do not expire.</p></div></div>
            <div class="mj-pack-grid">
                @foreach ($pointPacks as $pack)
                    <article class="mj-pack">
                        <div class="mj-points-kicker">Point Pack</div>
                        <strong>{{ number_format((int) $pack['points']) }} PTS</strong>
                        <div class="mj-pack-price">for {{ $money((int) $pack['price_cents']) }}</div>
                        <div class="mj-pack-note">{{ $pack['note'] ?? 'Add points to your PLYRCARD balance.' }}</div>
                        @if ($hasJourney)
                            <button type="button" class="mj-btn ghost" data-plyrcard-point-pack-open="{{ $pack['key'] }}">Buy</button>
                        @else
                            <a href="#" class="mj-btn disabled" aria-disabled="true" onclick="return false;">My Journey required</a>
                        @endif
                    </article>
                @endforeach
                @if ($jumpstartPlan)
                    <article class="mj-pack is-package">
                        <div class="mj-points-kicker">Jumpstart</div>
                        <strong>{{ number_format((int) ($jumpstartPlan['points'] ?? 0)) }} PTS</strong>
                        <div class="mj-pack-price">{{ $jumpstartPlan['price'] }}</div>
                        <div class="mj-pack-note">One-time recruiting service extension. My Journey remains the base plan.</div>
                        <button type="button" class="mj-btn ghost" data-plyrcard-jumpstart-open>Add Jumpstart</button>
                    </article>
                @endif
                @if ($amplifyPlan)
                    <article class="mj-pack is-package is-featured">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem"><div class="mj-points-kicker" style="color:var(--mj-orange)">Amplify</div><span class="mj-pack-badge">Best Value</span></div>
                        <strong>{{ number_format((int) ($amplifyPlan['points'] ?? 0)) }} PTS</strong>
                        <div class="mj-pack-price">{{ $amplifyPlan['price'] }}</div>
                        <div class="mj-pack-note">Larger one-time done-for-you recruiting push. My Journey remains your subscription.</div>
                        <button type="button" class="mj-btn orange" data-plyrcard-amplify-open>Add Amplify</button>
                    </article>
                @endif
            </div>
        </section>
        <section class="mj-point-history">
            <div class="mj-worth-head"><div><h2>Points history</h2><p>Most recent first. Service requests keep their live fulfillment status.</p></div></div>
            <div style="margin-top:.75rem">
                <div class="mj-history-row is-head"><div>Date</div><div>Item</div><div>Points</div><div>Status</div></div>
                @forelse($pointHistory as $entry)
                    <div class="mj-history-row"><div style="color:var(--mj-muted)">{{ $entry['date'] }}</div><div style="font-weight:700">{{ $entry['item'] }}</div><div class="mj-history-amount {{ $entry['positive'] ? 'is-add' : '' }}">{{ $entry['amount'] }}</div><div><span class="mj-history-status" data-status="{{ $entry['status'] }}">{{ $entry['status'] }}</span></div></div>
                @empty
                    <div style="padding:1rem 0;color:var(--mj-muted);font-size:.82rem">No points activity yet.</div>
                @endforelse
            </div>
        </section>
        @if ($this->shouldShowAddons())
            <section class="mj-addon-wrap">
                <div class="mj-addon-header">
                    <div class="mj-addon-icon">
                        <svg xmlns="http://www\.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13 5.4 5M7 13l-1.5 3h12M10 21a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm8 0a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="mj-addon-title">A La Carte</div>
                        <div class="mj-addon-subtitle">
                            For My Journey Subscribers<br>
                            Order exactly what you need, when you need it. All add-ons available exclusively for My Journey subscribers.
                        </div>
                    </div>
                </div>
                <div class="mj-addon-list">
                    @foreach ($addons as $addon)
                        <a href="#" class="mj-addon-item" style="text-decoration:none;">
                            <div class="mj-addon-left">
                                <div class="mj-addon-badge">
                                    {!! mjIcon($addon['icon']) !!}
                                </div>
                                <div>
                                    <div class="mj-addon-name">{{ $addon['title'] }}</div>
                                    <div class="mj-addon-desc">{{ $addon['desc'] }}</div>
                                </div>
                            </div>
                            <div class="mj-addon-right">
                                <div class="mj-addon-price">{{ $addon['price'] }}</div>
                                <div class="mj-addon-unit">{{ $addon['unit'] }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
        <section class="mj-footer-note">
            <strong>{{ $this->getFooterHeadline() }}</strong>
            <span>{{ $this->getFooterCopy() }}</span>
        </section>
    </div>
    <div class="mj-downgrade-modal" data-mj-downgrade-modal hidden>
        <div class="mj-downgrade-card" role="dialog" aria-modal="true" aria-labelledby="mj-downgrade-title">
            <h3 id="mj-downgrade-title">Downgrade to Free?</h3>
            <p>This will submit a cancellation request for your active My Journey subscription. Your paid access stays active until the billing cancellation is confirmed, then your account can return to Free.</p>
            <div class="mj-downgrade-status" data-mj-downgrade-status hidden></div>
            <div class="mj-downgrade-actions">
                <button type="button" class="mj-btn ghost" data-mj-downgrade-close>Keep My Journey</button>
                <button type="button" class="mj-btn mj-downgrade-confirm-btn" data-mj-downgrade-confirm>Request Downgrade</button>
            </div>
        </div>
    </div>
    <style>
        .mj-downgrade-modal[hidden]{display:none!important}.mj-downgrade-modal{position:fixed;inset:0;z-index:100600;display:grid;place-items:center;padding:18px;background:rgba(2,6,23,.72);backdrop-filter:blur(7px)}
        .mj-downgrade-card{width:min(480px,100%);background:#fff;color:#101828;border-radius:18px;padding:1.25rem;border:1px solid #e5e7eb;box-shadow:0 28px 80px rgba(0,0,0,.3)}.mj-downgrade-card h3{margin:0;font-size:1.15rem}.mj-downgrade-card p{margin:.65rem 0 1rem;color:#667085;line-height:1.55;font-size:.9rem}.mj-downgrade-actions{display:flex;justify-content:flex-end;gap:.6rem;flex-wrap:wrap}.mj-downgrade-actions .mj-btn{width:auto;margin:0;cursor:pointer}.mj-downgrade-confirm-btn{background:#ff6338!important;border:1px solid #ff6338!important;color:#fff!important;box-shadow:0 8px 22px rgba(255,99,56,.24)!important;opacity:1!important;visibility:visible!important;min-width:190px}.mj-downgrade-confirm-btn:hover{background:#e9532d!important;border-color:#e9532d!important;color:#fff!important}.mj-downgrade-confirm-btn:disabled{background:#ff9a7d!important;border-color:#ff9a7d!important;color:#fff!important;opacity:.82!important;cursor:wait!important}.mj-downgrade-status{margin:.7rem 0;padding:.75rem;border-radius:10px;font-size:.85rem;line-height:1.45}.mj-downgrade-status.is-success{background:#ecfdf3;color:#067647}.mj-downgrade-status.is-error{background:#fef3f2;color:#b42318}
    </style>
    <script>
        (() => {
            const modal = document.querySelector('[data-mj-downgrade-modal]');
            if (!modal || modal.dataset.bound === '1') return;
            modal.dataset.bound = '1';
            const status = modal.querySelector('[data-mj-downgrade-status]');
            const confirm = modal.querySelector('[data-mj-downgrade-confirm]');
            const close = () => { modal.hidden = true; };
            document.addEventListener('click', async (event) => {
                if (event.target.closest('[data-plyrcard-downgrade-free]')) { event.preventDefault(); status.hidden = true; status.classList.remove('is-success','is-error'); confirm.hidden = false; modal.hidden = false; return; }
                if (event.target.closest('[data-mj-downgrade-close]')) { close(); return; }
                if (!event.target.closest('[data-mj-downgrade-confirm]')) return;
                confirm.disabled = true; confirm.textContent = 'Submitting...';
                try {
                    const response = await fetch(@json(route('billing.cancel-request')), {method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''}});
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(data.message || 'Unable to submit the downgrade request.');
                    status.classList.remove('is-error'); status.classList.add('is-success'); status.textContent = data.message || 'Your downgrade request was submitted.'; status.hidden = false;
                    confirm.hidden = true;
                } catch (error) { status.classList.remove('is-success'); status.classList.add('is-error'); status.textContent = error.message || 'Unable to submit the downgrade request.'; status.hidden = false; }
                finally { confirm.disabled = false; confirm.textContent = 'Request Downgrade'; }
            });
        })();
    </script>
    {{-- Native Stripe checkout for My Journey + point packages. --}}
</x-filament-panels::page>