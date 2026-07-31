<x-filament-panels::page>
    <style>
        html,
        body,
        .fi-body,
        .fi-layout,
        .fi-main-ctn,
        .fi-main,
        .fi-page,
        .fi-page-content {
            width: 100% !important;
            min-width: 0 !important;
            max-width: none !important;
            height: 100% !important;
            min-height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }

        .fi-sidebar,
        .fi-topbar,
        .fi-header,
        header.fi-header,
        .fi-page-header,
        .fi-page-heading,
        .fi-breadcrumbs,
        .plyr-sidebar-footer,
        .plyr-sidebar-brand-wrap {
            display: none !important;
        }

        .fi-main-ctn,
        .fi-main,
        .fi-page,
        .fi-page-content {
            min-height: 100dvh !important;
        }

        .fi-main-ctn {
            background:
                radial-gradient(circle at 15% 12%, rgba(255, 99, 56, .13), transparent 26rem),
                radial-gradient(circle at 88% 90%, rgba(255, 99, 56, .07), transparent 24rem),
                #f8fafc !important;
        }

        .dark .fi-main-ctn {
            background:
                radial-gradient(circle at 15% 12%, rgba(255, 99, 56, .14), transparent 26rem),
                radial-gradient(circle at 88% 90%, rgba(255, 99, 56, .07), transparent 24rem),
                #020617 !important;
        }

        .fpw-screen {
            width: 100%;
            height: 100dvh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1rem, 3vw, 2rem);
            overflow: hidden;
        }

        .fpw-card {
            width: min(27.5rem, 100%);
            border: 1px solid rgba(226, 232, 240, .92);
            border-radius: 1.35rem;
            background: rgba(255, 255, 255, .97);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .13);
            overflow: hidden;
        }

        .dark .fpw-card {
            border-color: rgba(148, 163, 184, .16);
            background: rgba(15, 23, 42, .96);
            box-shadow: 0 28px 80px rgba(0, 0, 0, .36);
        }

        .fpw-content {
            padding: clamp(1.4rem, 4vw, 2rem);
        }

        .fpw-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.45rem;
        }

        .fpw-brand-name {
            color: #0f172a;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .dark .fpw-brand-name {
            color: #f8fafc;
        }

        .fpw-icon {
            width: 2.75rem;
            height: 2.75rem;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: .9rem;
            background: rgba(255, 99, 56, .12);
            color: #ff6338;
        }

        .fpw-icon svg {
            width: 1.3rem;
            height: 1.3rem;
        }

        .fpw-title {
            margin: 0;
            color: #0f172a;
            font-size: clamp(1.55rem, 4vw, 1.85rem);
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -.035em;
        }

        .dark .fpw-title {
            color: #f8fafc;
        }

        .fpw-copy {
            margin: .55rem 0 1.5rem;
            color: #64748b;
            font-size: .9rem;
            line-height: 1.55;
        }

        .dark .fpw-copy {
            color: #94a3b8;
        }

        .fpw-form {
            display: grid;
            gap: 1rem;
        }

        .fpw-label {
            display: block;
            margin-bottom: .42rem;
            color: #334155;
            font-size: .82rem;
            font-weight: 700;
        }

        .dark .fpw-label {
            color: #e2e8f0;
        }

        .fpw-error {
            margin: .38rem 0 0;
            color: #dc2626;
            font-size: .76rem;
            line-height: 1.4;
        }

        .fpw-help {
            display: flex;
            align-items: flex-start;
            gap: .5rem;
            margin: 0;
            padding: .75rem .8rem;
            border-radius: .8rem;
            background: #f8fafc;
            color: #64748b;
            font-size: .75rem;
            line-height: 1.45;
        }

        .dark .fpw-help {
            background: rgba(148, 163, 184, .08);
            color: #94a3b8;
        }

        .fpw-help svg {
            width: 1rem;
            height: 1rem;
            flex: 0 0 auto;
            margin-top: .06rem;
            color: #ff6338;
        }

        .fpw-submit {
            width: 100%;
            min-height: 2.9rem;
            margin-top: .1rem;
            border-radius: .82rem !important;
        }

        @media (max-height: 690px) {
            .fpw-content {
                padding: 1.2rem 1.35rem;
            }

            .fpw-brand {
                margin-bottom: .9rem;
            }

            .fpw-copy {
                margin-bottom: 1rem;
            }

            .fpw-form {
                gap: .75rem;
            }
        }
    </style>

    <div class="fpw-screen">
        <section class="fpw-card" aria-labelledby="fpw-title">
            <div class="fpw-content">
                <div class="fpw-brand">
                    <span class="fpw-brand-name">PLYRCARD</span>

                    <span class="fpw-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                            <path d="M6.5 10h11A1.5 1.5 0 0 1 19 11.5v7A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-7A1.5 1.5 0 0 1 6.5 10Z" stroke="currentColor" stroke-width="1.9"/>
                        </svg>
                    </span>
                </div>

                <h1 id="fpw-title" class="fpw-title">Set your password</h1>
                <p class="fpw-copy">
                    Add a secure password to finish setting up your account and continue to your dashboard.
                </p>

                <form wire:submit="save" class="fpw-form">
                    <div>
                        <label for="password" class="fpw-label">Password</label>
                        <x-filament::input.wrapper :valid="! $errors->has('password')">
                            <x-filament::input
                                id="password"
                                type="password"
                                wire:model="password"
                                autocomplete="new-password"
                                placeholder="Enter your password"
                                autofocus
                            />
                        </x-filament::input.wrapper>
                        @error('password')
                            <p class="fpw-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="fpw-label">Confirm password</label>
                        <x-filament::input.wrapper :valid="! $errors->has('password_confirmation')">
                            <x-filament::input
                                id="password_confirmation"
                                type="password"
                                wire:model="password_confirmation"
                                autocomplete="new-password"
                                placeholder="Enter it again"
                            />
                        </x-filament::input.wrapper>
                        @error('password_confirmation')
                            <p class="fpw-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="fpw-help">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M12 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M12 8h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                        <span>Use at least 8 characters with uppercase and lowercase letters and at least one number.</span>
                    </p>

                    <x-filament::button
                        type="submit"
                        size="lg"
                        class="fpw-submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                    >
                        <span wire:loading.remove wire:target="save">Save password and continue</span>
                        <span wire:loading.inline-flex wire:target="save" style="align-items:center;gap:.45rem;">
                            <x-filament::loading-indicator style="width:1rem;height:1rem;" />
                            Saving password
                        </span>
                    </x-filament::button>
                </form>
            </div>
        </section>
    </div>
</x-filament-panels::page>