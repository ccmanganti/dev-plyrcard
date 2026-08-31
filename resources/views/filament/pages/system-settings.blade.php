<x-filament-panels::page>
    <div class="pc-system-settings-v86">
        <section class="pc-system-settings-card-v86">
            <div class="pc-system-settings-icon-v86">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="9" cy="7" r="2" fill="currentColor"/><circle cx="15" cy="12" r="2" fill="currentColor"/><circle cx="11" cy="17" r="2" fill="currentColor"/></svg>
            </div>
            <div class="pc-system-settings-copy-v86">
                <span class="pc-system-settings-kicker-v86">Global administration</span>
                <h2>Admin alert recipients</h2>
                <p>This global list receives operational alerts from PLYRCARD, including new support tickets, client ticket follow-ups, and downgrade requests.</p>
            </div>

            <form wire:submit="save" class="pc-system-settings-form-v86">
                <label>
                    <span>Alert email addresses</span>
                    <textarea
                        wire:model="adminAlertEmails"
                        rows="7"
                        placeholder="support@plyrcard.com&#10;admin@plyrcard.com"
                        spellcheck="false"
                    ></textarea>
                    <small>Enter one email per line, or separate addresses with commas. This setting is global and is only accessible to admins.</small>
                </label>

                @error('adminAlertEmails')
                    <div class="pc-system-settings-error-v86">{{ $message }}</div>
                @enderror

                <div class="pc-system-settings-actions-v86">
                    <button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Save Settings</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </form>
        </section>
    </div>

    <style>
        .pc-system-settings-v86{max-width:900px;margin:0 auto;padding:1rem 0 2rem}.pc-system-settings-card-v86{display:grid;grid-template-columns:auto 1fr;gap:1rem 1.1rem;padding:1.4rem;border:1px solid var(--gray-200,#e5e7eb);border-radius:1.1rem;background:var(--gray-50,#fff);box-shadow:0 16px 40px rgba(15,23,42,.06)}.dark .pc-system-settings-card-v86{background:#0f1218;border-color:#252b35}.pc-system-settings-icon-v86{width:3rem;height:3rem;border-radius:.9rem;background:rgba(255,99,56,.12);color:#ff6338;display:grid;place-items:center}.pc-system-settings-icon-v86 svg{width:1.4rem;height:1.4rem}.pc-system-settings-copy-v86 h2{margin:.15rem 0 .35rem;font-size:1.35rem;font-weight:850;letter-spacing:-.025em}.pc-system-settings-copy-v86 p{margin:0;color:#667085;line-height:1.55;max-width:680px}.dark .pc-system-settings-copy-v86 p{color:#98a2b3}.pc-system-settings-kicker-v86{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.12em;font-weight:850;color:#ff6338}.pc-system-settings-form-v86{grid-column:1/-1;margin-top:.35rem;display:grid;gap:.8rem}.pc-system-settings-form-v86 label{display:grid;gap:.45rem}.pc-system-settings-form-v86 label>span{font-weight:800;font-size:.83rem}.pc-system-settings-form-v86 textarea{width:100%;resize:vertical;border:1px solid #d0d5dd;border-radius:.85rem;padding:.85rem .95rem;background:#fff;color:#101828;outline:none;min-height:9rem;font:inherit}.dark .pc-system-settings-form-v86 textarea{background:#17191e;color:#f8fafc;border-color:#343943}.pc-system-settings-form-v86 textarea:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.12)}.pc-system-settings-form-v86 small{color:#667085;line-height:1.45}.dark .pc-system-settings-form-v86 small{color:#98a2b3}.pc-system-settings-error-v86{padding:.75rem .85rem;border-radius:.75rem;background:#fef3f2;color:#b42318;border:1px solid #fecdca;font-size:.82rem;font-weight:700}.pc-system-settings-actions-v86{display:flex;justify-content:flex-end}.pc-system-settings-actions-v86 button{border:0;border-radius:.8rem;background:#ff6338;color:#fff;font-weight:850;padding:.75rem 1rem;min-width:9.5rem;cursor:pointer;box-shadow:0 10px 24px rgba(255,99,56,.2)}.pc-system-settings-actions-v86 button:disabled{opacity:.65;cursor:wait}@media(max-width:640px){.pc-system-settings-card-v86{grid-template-columns:1fr}.pc-system-settings-icon-v86{width:2.7rem;height:2.7rem}.pc-system-settings-actions-v86 button{width:100%}}
    </style>
</x-filament-panels::page>
