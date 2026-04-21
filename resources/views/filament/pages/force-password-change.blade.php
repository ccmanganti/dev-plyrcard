<x-filament-panels::page>
    <style>
        .fpw-wrap {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .fpw-shell {
            width: 100%;
            max-width: 1100px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            background: #0f172a;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .fpw-left {
            position: relative;
            padding: 48px;
            color: #fff;
            background:
                radial-gradient(circle at top left, rgba(255,255,255,.10), transparent 35%),
                radial-gradient(circle at bottom right, rgba(255,255,255,.08), transparent 35%),
                linear-gradient(135deg, #ff6347 0%, #ff6347 45%, #ff6347 100%);
        }

        .fpw-badge-icon {
            width: 64px;
            height: 64px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: rgba(255,255,255,.14);
            backdrop-filter: blur(8px);
            margin-bottom: 24px;
        }

        .fpw-left h2 {
            margin: 0;
            font-size: 36px;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .fpw-left p {
            margin-top: 16px;
            max-width: 540px;
            font-size: 15px;
            line-height: 1.75;
            color: rgba(255,255,255,.90);
        }

        .fpw-feature-list {
            margin-top: 36px;
            display: grid;
            gap: 14px;
        }

        .fpw-feature {
            display: flex;
            gap: 12px;
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255,255,255,.10);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.08);
        }

        .fpw-feature-icon {
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.12);
        }

        .fpw-feature-title {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }

        .fpw-feature-text {
            margin: 0;
            font-size: 13px;
            line-height: 1.6;
            color: rgba(255,255,255,.82);
        }

        .fpw-right {
            padding: 44px;
            background:
                linear-gradient(180deg, #111827 0%, #0f172a 100%);
            color: #e5e7eb;
        }

        .fpw-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            color: #ff6347;
            background: rgba(249, 115, 22, 0.12);
            border: 1px solid rgba(249, 115, 22, 0.24);
            border-radius: 999px;
        }

        .fpw-title {
            margin: 18px 0 10px;
            font-size: 34px;
            line-height: 1.15;
            font-weight: 800;
            color: #f8fafc;
            letter-spacing: -0.02em;
        }

        .fpw-subtitle {
            margin: 0 0 24px;
            color: #94a3b8;
            font-size: 15px;
            line-height: 1.7;
        }

        .fpw-note {
            display: flex;
            gap: 14px;
            padding: 18px;
            margin-bottom: 24px;
            border-radius: 18px;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.08);
        }

        .fpw-note strong {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: #f8fafc;
        }

        .fpw-note ul {
            margin: 8px 0 0;
            padding-left: 18px;
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.7;
        }

        .fpw-form {
            display: grid;
            gap: 18px;
        }

        .fpw-field {
            display: grid;
            gap: 8px;
        }

        .fpw-label {
            display: block;
            margin-bottom: 2px;
            font-size: 14px;
            font-weight: 700;
            color: #e5e7eb !important;
        }

        .fpw-input-wrap {
            position: relative;
        }

        .fpw-input,
        .fpw-input:focus,
        .fpw-input:active,
        .fpw-input:hover,
        .fpw-input:visited {
            width: 100%;
            height: 52px;
            padding: 0 48px 0 16px;
            border: 1px solid #334155;
            border-radius: 16px;
            outline: none;
            font-size: 14px;
            color: #000 !important;
            background: #fff !important;
            -webkit-text-fill-color: #000 !important;
            caret-color: #000 !important;
            box-sizing: border-box;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .fpw-input:focus {
            border-color: #fb923c;
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15);
        }

        .fpw-input::placeholder {
            color: #64748b !important;
            -webkit-text-fill-color: #64748b !important;
            opacity: 1 !important;
        }

        .fpw-input:-webkit-autofill,
        .fpw-input:-webkit-autofill:hover,
        .fpw-input:-webkit-autofill:focus,
        .fpw-input:-webkit-autofill:active {
            -webkit-text-fill-color: #000 !important;
            caret-color: #000 !important;
            box-shadow: 0 0 0 1000px #fff inset !important;
            -webkit-box-shadow: 0 0 0 1000px #fff inset !important;
            transition: background-color 9999s ease-in-out 0s;
        }

        .fpw-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            border-radius: 10px;
            padding: 0;
        }

        .fpw-toggle:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .fpw-toggle:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.16);
        }

        .fpw-toggle svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
        }

        .fpw-error {
            font-size: 13px;
            color: #fca5a5;
            margin-top: 2px;
        }

        .fpw-bottom-note {
            display: flex;
            gap: 12px;
            padding: 16px 18px;
            margin-top: 6px;
            border-radius: 18px;
            background: rgba(249, 115, 22, 0.10);
            border: 1px solid rgba(249, 115, 22, 0.22);
            color: #ff6347;
            font-size: 14px;
            line-height: 1.7;
        }

        .fpw-submit {
            width: 100%;
            margin-top: 4px;
            border: 0;
            border-radius: 18px;
            padding: 15px 18px;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #ff6347 0%, #ff6347 55%, #ff6347 100%);
            box-shadow: 0 14px 30px rgba(234, 88, 12, 0.28);
            transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease;
        }

        .fpw-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 36px rgba(234, 88, 12, 0.33);
        }

        .fpw-submit[disabled] {
            opacity: .7;
            cursor: not-allowed;
        }

        .fpw-icon {
            width: 22px;
            height: 22px;
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }

        @media (max-width: 960px) {
            .fpw-shell {
                grid-template-columns: 1fr;
            }

            .fpw-left {
                padding: 32px 24px;
            }

            .fpw-right {
                padding: 28px 20px;
            }

            .fpw-title {
                font-size: 28px;
            }

            .fpw-left h2 {
                font-size: 28px;
            }
        }
    </style>

    <div class="fpw-wrap">
        <div class="fpw-shell">
            <section class="fpw-left">
                <div class="fpw-badge-icon">
                    <svg class="fpw-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                        <rect x="5" y="11" width="14" height="9" rx="2"></rect>
                        <path d="M8 11V8a4 4 0 1 1 8 0v3"></path>
                    </svg>
                </div>

                <h2>Secure your account before continuing</h2>

                <p>
                    For your protection, you need to update your password before accessing the admin panel.
                    This is a one-time setup and only takes a moment.
                </p>

                <div class="fpw-feature-list">
                    <div class="fpw-feature">
                        <div class="fpw-feature-icon">
                            <svg class="fpw-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <path d="M9 12l2 2 4-4"></path>
                                <path d="M12 3l7 4v5c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9V7l7-4z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="fpw-feature-title">Protect your account</p>
                            <p class="fpw-feature-text">Choose a strong password that only you know.</p>
                        </div>
                    </div>

                    <div class="fpw-feature">
                        <div class="fpw-feature-icon">
                            <svg class="fpw-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <path d="M6 10V7a6 6 0 1 1 12 0v3"></path>
                                <rect x="4" y="10" width="16" height="10" rx="2"></rect>
                            </svg>
                        </div>
                        <div>
                            <p class="fpw-feature-title">One-time security step</p>
                            <p class="fpw-feature-text">After this, you’ll be taken straight into the dashboard.</p>
                        </div>
                    </div>

                    <div class="fpw-feature">
                        <div class="fpw-feature-icon">
                            <svg class="fpw-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <path d="M12 3l1.9 5.8H20l-4.9 3.5L17 18l-5-3.6L7 18l1.9-5.7L4 8.8h6.1L12 3z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="fpw-feature-title">Better first-use experience</p>
                            <p class="fpw-feature-text">Once done, you can continue with onboarding and setup.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="fpw-right">
                <div class="fpw-tag">
                    <svg class="fpw-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    </svg>
                    Security step required
                </div>

                <h1 class="fpw-title">Change your password</h1>

                <p class="fpw-subtitle">
                    Before entering the admin panel, please create a new secure password for your account.
                </p>

                <div class="fpw-note">
                    <div>
                        <svg class="fpw-icon" style="color:#22c55e" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                            <path d="M9 12l2 2 4-4"></path>
                            <path d="M12 3l7 4v5c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9V7l7-4z"></path>
                        </svg>
                    </div>

                    <div>
                        <strong>Password tips</strong>
                        <ul>
                            <li>Use at least 8 characters</li>
                            <li>Include uppercase, lowercase, and a number</li>
                            <li>Avoid names, birthdays, and easy patterns</li>
                        </ul>
                    </div>
                </div>

                <form wire:submit="save" class="fpw-form">
                    <div class="fpw-field">
                        <label for="password" class="fpw-label">New Password</label>
                        <div class="fpw-input-wrap">
                            <input
                                id="password"
                                type="password"
                                wire:model="password"
                                class="fpw-input"
                                placeholder="Enter your new password"
                                autocomplete="new-password"
                            >
                            <button
                                type="button"
                                class="fpw-toggle"
                                aria-label="Show password"
                                data-toggle-password="password"
                            >
                                <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke-width="1.9">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg data-eye-closed viewBox="0 0 24 24" fill="none" stroke-width="1.9" style="display:none;">
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.58"></path>
                                    <path d="M9.88 5.09A10.94 10.94 0 0 1 12 5c6.5 0 10 7 10 7a17.46 17.46 0 0 1-4.23 5.17"></path>
                                    <path d="M6.61 6.61C3.73 8.57 2 12 2 12a17.56 17.56 0 0 0 7.39 5.39"></path>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <div class="fpw-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="fpw-field">
                        <label for="password_confirmation" class="fpw-label">Confirm Password</label>
                        <div class="fpw-input-wrap">
                            <input
                                id="password_confirmation"
                                type="password"
                                wire:model="password_confirmation"
                                class="fpw-input"
                                placeholder="Re-enter your new password"
                                autocomplete="new-password"
                            >
                            <button
                                type="button"
                                class="fpw-toggle"
                                aria-label="Show password"
                                data-toggle-password="password_confirmation"
                            >
                                <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke-width="1.9">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg data-eye-closed viewBox="0 0 24 24" fill="none" stroke-width="1.9" style="display:none;">
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.58"></path>
                                    <path d="M9.88 5.09A10.94 10.94 0 0 1 12 5c6.5 0 10 7 10 7a17.46 17.46 0 0 1-4.23 5.17"></path>
                                    <path d="M6.61 6.61C3.73 8.57 2 12 2 12a17.56 17.56 0 0 0 7.39 5.39"></path>
                                </svg>
                            </button>
                        </div>
                        @error('password_confirmation')
                            <div class="fpw-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="fpw-bottom-note">
                        <svg class="fpw-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4"></path>
                            <path d="M12 8h.01"></path>
                        </svg>
                        <div>
                            After saving your new password, you’ll be redirected to the dashboard automatically.
                        </div>
                    </div>

                    <button type="submit" class="fpw-submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Update password and continue</span>
                        <span wire:loading wire:target="save">Updating password...</span>
                    </button>
                </form>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const inputId = button.getAttribute('data-toggle-password');
                    const input = document.getElementById(inputId);

                    if (!input) return;

                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';

                    const openIcon = button.querySelector('[data-eye-open]');
                    const closedIcon = button.querySelector('[data-eye-closed]');

                    if (openIcon && closedIcon) {
                        openIcon.style.display = isPassword ? 'none' : '';
                        closedIcon.style.display = isPassword ? '' : 'none';
                    }

                    button.setAttribute(
                        'aria-label',
                        isPassword ? 'Hide password' : 'Show password'
                    );
                });
            });
        });
    </script>
</x-filament-panels::page>