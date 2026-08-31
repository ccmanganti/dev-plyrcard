<x-filament-panels::page>
    <div class="pc-support-system-settings-v87">
        <section class="pc-support-system-settings-card-v87">
            <div class="pc-support-system-settings-icon-v87">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="9" cy="7" r="2" fill="currentColor"/><circle cx="15" cy="12" r="2" fill="currentColor"/><circle cx="11" cy="17" r="2" fill="currentColor"/></svg>
            </div>
            <div class="pc-support-system-settings-copy-v87">
                <span class="pc-support-system-settings-kicker-v87">Global system setting</span>
                <h2>Admin alert recipients</h2>
                <p>Manage the internal email list used across PLYRCARD for new support tickets, ticket follow-ups, and downgrade requests.</p>
            </div>
            <form wire:submit="save" class="pc-support-system-settings-form-v87">
                <label>
                    <span>Alert email addresses</span>
                    <textarea wire:model="adminAlertEmails" rows="7" placeholder="support@plyrcard.com&#10;admin@plyrcard.com" spellcheck="false"></textarea>
                    <small>Enter one email per line or separate addresses with commas. This remains a global admin setting; it is simply managed from Support Tickets.</small>
                </label>
                @error('adminAlertEmails')<div class="pc-support-system-settings-error-v87">{{ $message }}</div>@enderror
                <div class="pc-support-system-settings-actions-v87">
                    <a href="{{ \App\Filament\Resources\SupportTickets\SupportTicketResource::getUrl('index') }}">Back to Tickets</a>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Save Settings</span><span wire:loading wire:target="save">Saving…</span></button>
                </div>
            </form>
        </section>
    </div>
    <style>
        .pc-support-system-settings-v87{max-width:900px;margin:0 auto;padding:1rem 0 2rem}.pc-support-system-settings-card-v87{display:grid;grid-template-columns:auto 1fr;gap:1rem 1.1rem;padding:1.4rem;border:1px solid var(--gray-200,#e5e7eb);border-radius:1.1rem;background:var(--gray-50,#fff);box-shadow:0 16px 40px rgba(15,23,42,.06)}.dark .pc-support-system-settings-card-v87{background:#0f1218;border-color:#252b35}.pc-support-system-settings-icon-v87{width:3rem;height:3rem;border-radius:.9rem;background:rgba(255,99,56,.12);color:#ff6338;display:grid;place-items:center}.pc-support-system-settings-icon-v87 svg{width:1.4rem;height:1.4rem}.pc-support-system-settings-copy-v87 h2{margin:.15rem 0 .35rem;font-size:1.35rem;font-weight:850;letter-spacing:-.025em}.pc-support-system-settings-copy-v87 p{margin:0;color:#667085;line-height:1.55;max-width:680px}.dark .pc-support-system-settings-copy-v87 p{color:#98a2b3}.pc-support-system-settings-kicker-v87{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.12em;font-weight:850;color:#ff6338}.pc-support-system-settings-form-v87{grid-column:1/-1;margin-top:.35rem;display:grid;gap:.8rem}.pc-support-system-settings-form-v87 label{display:grid;gap:.45rem}.pc-support-system-settings-form-v87 label>span{font-weight:800;font-size:.83rem}.pc-support-system-settings-form-v87 textarea{width:100%;resize:vertical;border:1px solid #d0d5dd;border-radius:.85rem;padding:.85rem .95rem;background:#fff;color:#101828;outline:none;min-height:9rem;font:inherit}.dark .pc-support-system-settings-form-v87 textarea{background:#17191e;color:#f8fafc;border-color:#343943}.pc-support-system-settings-form-v87 textarea:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.12)}.pc-support-system-settings-form-v87 small{color:#667085;line-height:1.45}.dark .pc-support-system-settings-form-v87 small{color:#98a2b3}.pc-support-system-settings-error-v87{padding:.75rem .85rem;border-radius:.75rem;background:#fef3f2;color:#b42318;border:1px solid #fecdca;font-size:.82rem;font-weight:700}.pc-support-system-settings-actions-v87{display:flex;align-items:center;justify-content:flex-end;gap:.65rem}.pc-support-system-settings-actions-v87 a,.pc-support-system-settings-actions-v87 button{border-radius:.8rem;font-weight:850;padding:.75rem 1rem;min-width:9.5rem;text-align:center;text-decoration:none}.pc-support-system-settings-actions-v87 a{border:1px solid #d0d5dd;color:inherit;background:transparent}.dark .pc-support-system-settings-actions-v87 a{border-color:#343943}.pc-support-system-settings-actions-v87 button{border:0;background:#ff6338;color:#fff;cursor:pointer;box-shadow:0 10px 24px rgba(255,99,56,.2)}.pc-support-system-settings-actions-v87 button:disabled{opacity:.65;cursor:wait}@media(max-width:640px){.pc-support-system-settings-card-v87{grid-template-columns:1fr}.pc-support-system-settings-actions-v87{flex-direction:column-reverse}.pc-support-system-settings-actions-v87 a,.pc-support-system-settings-actions-v87 button{width:100%}}
    </style>
</x-filament-panels::page>
