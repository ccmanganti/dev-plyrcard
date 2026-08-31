@php
    $supportTicketUser = auth()->user();
    $supportTicketEmail = old('email', $supportTicketUser?->email ?? $supportTicketUser?->personal_email ?? '');
    $supportTicketCategories = \App\Models\SupportTicket::categories();
@endphp

<section
    class="rc-native-support-v84"
    x-data="{
        busy: false,
        notice: '',
        noticeType: '',
        async submitTicket(event) {
            if (this.busy) return;
            this.busy = true;
            this.notice = '';
            this.noticeType = '';

            try {
                const response = await fetch(event.currentTarget.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(event.currentTarget),
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok || data.success === false) {
                    const errors = data?.errors ? Object.values(data.errors).flat() : [];
                    throw new Error(errors[0] || data.message || 'We could not submit your support ticket.');
                }

                this.notice = `${data.message || 'Support ticket submitted.'}${data.ticket_number ? ' Ticket: ' + data.ticket_number : ''}`;
                this.noticeType = 'success';
                this.$refs.message.value = '';
                this.$refs.category.value = '';
            } catch (error) {
                this.notice = error?.message || 'We could not submit your support ticket. Please try again.';
                this.noticeType = 'error';
            } finally {
                this.busy = false;
            }
        }
    }"
>
    <form action="{{ route('support.tickets.store') }}" method="POST" x-on:submit.prevent="submitTicket($event)">
        @csrf
        <input type="hidden" name="source" value="coach_database">

        <div class="rc-native-support-grid-v84">
            <label class="rc-native-support-field-v84">
                <span>Email</span>
                <input type="email" name="email" value="{{ $supportTicketEmail }}" placeholder="you@example.com" autocomplete="email" required>
                <small>We use this email so our team can identify and reply to your request.</small>
            </label>

            <label class="rc-native-support-field-v84">
                <span>What do you need help with?</span>
                <select name="category" x-ref="category" required>
                    <option value="" selected disabled>Select a concern</option>
                    @foreach($supportTicketCategories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="rc-native-support-field-v84 rc-native-support-message-v84">
            <span>Message</span>
            <textarea name="message" x-ref="message" rows="8" maxlength="5000" minlength="10" placeholder="Tell us what happened, what you expected, and anything else that can help us understand the request." required></textarea>
            <small>Please include useful details such as the page or feature you were using.</small>
        </label>

        <div class="rc-native-support-status-v84" x-cloak x-show="notice" x-bind:class="noticeType === 'success' ? 'is-success' : 'is-error'" x-text="notice"></div>

        <div class="rc-native-support-actions-v84">
            <button type="submit" class="rc-btn rc-btn-primary" x-bind:disabled="busy">
                <span x-show="!busy">Submit Ticket</span>
                <span x-cloak x-show="busy">Submitting…</span>
            </button>
        </div>
    </form>
</section>

<style>
    .rc-native-support-v84{padding:1.25rem 1.35rem 1.4rem}.rc-native-support-grid-v84{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.rc-native-support-field-v84{display:grid;gap:.45rem}.rc-native-support-field-v84>span{font-size:.82rem;font-weight:800;color:var(--rc-text,#101828)}.rc-native-support-field-v84 input,.rc-native-support-field-v84 select,.rc-native-support-field-v84 textarea{width:100%;border:1px solid var(--rc-border,#d0d5dd);background:var(--rc-surface,#fff);color:var(--rc-text,#101828);border-radius:.8rem;padding:.78rem .9rem;font:inherit;outline:none;transition:border-color .15s ease,box-shadow .15s ease}.rc-native-support-field-v84 input:focus,.rc-native-support-field-v84 select:focus,.rc-native-support-field-v84 textarea:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.12)}.rc-native-support-field-v84 textarea{resize:vertical;min-height:10rem}.rc-native-support-field-v84 small{color:var(--rc-muted,#667085);font-size:.74rem;line-height:1.45}.rc-native-support-message-v84{margin-top:1rem}.rc-native-support-actions-v84{display:flex;justify-content:flex-end;margin-top:1rem}.rc-native-support-status-v84{margin-top:1rem;padding:.8rem .9rem;border-radius:.8rem;font-size:.82rem;font-weight:700;line-height:1.45}.rc-native-support-status-v84.is-success{background:#ecfdf3;border:1px solid #abefc6;color:#067647}.rc-native-support-status-v84.is-error{background:#fef3f2;border:1px solid #fecdca;color:#b42318}.rc-native-support-v84 button[disabled]{opacity:.65;cursor:wait}@media(max-width:720px){.rc-native-support-grid-v84{grid-template-columns:1fr}.rc-native-support-v84{padding:1rem}}
</style>
