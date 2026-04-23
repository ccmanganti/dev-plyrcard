<x-filament-panels::page>
    <style>
        .fpw-wrap {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            background: #000;
        }

        .fpw-shell {
            width: 100%;
            max-width: 640px;
            background: #000;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .fpw-right {
            padding: 44px;
            background: #000;
            color: #e5e7eb;
        }

        .fpw-title {
            margin: 0 0 10px;
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

        .fpw-submit {
            width: 100%;
            margin-top: 8px;
            border: 0;
            border-radius: 18px;
            padding: 15px 18px;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            background: #ff6347;
            box-shadow: 0 14px 30px rgba(255, 99, 71, 0.28);
            transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease;
        }

        .fpw-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 36px rgba(255, 99, 71, 0.33);
        }

        .fpw-submit[disabled] {
            opacity: .7;
            cursor: not-allowed;
        }

        @media (max-width: 960px) {
            .fpw-right {
                padding: 28px 20px;
            }

            .fpw-title {
                font-size: 28px;
            }
        }
    </style>

    <div class="fpw-wrap">
        <div class="fpw-shell">
            <section class="fpw-right">
                <h1 class="fpw-title">Change your password</h1>

                <p class="fpw-subtitle">
                    Before entering the admin panel, please create a new secure password for your account.
                </p>

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