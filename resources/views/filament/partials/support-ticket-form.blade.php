@php
    $supportTicketUser = auth()->user();
    $supportTicketCategories = \App\Models\SupportTicket::categories();
    $supportTicketStatuses = \App\Models\SupportTicket::statuses();
    $supportTicketsPayload = $supportTicketUser
        ? \App\Models\SupportTicket::query()
            ->where('user_id', $supportTicketUser->getKey())
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->map(fn ($ticket) => [
                'id' => $ticket->getKey(),
                'ticket_number' => $ticket->ticket_number,
                'category' => $ticket->category,
                'category_label' => $ticket->categoryLabel(),
                'status' => $ticket->status,
                'status_label' => $ticket->statusLabel(),
                'priority' => $ticket->priority,
                'message' => $ticket->message,
                'conversation' => is_array($ticket->conversation) ? $ticket->conversation : [],
                'created_at' => optional($ticket->created_at)->toIso8601String(),
                'updated_at' => optional($ticket->updated_at)->toIso8601String(),
            ])
            ->values()
            ->all()
        : [];
@endphp

<section
    class="rc-support-v86"
    x-data="{
        busy: false,
        followBusy: null,
        notice: '',
        noticeType: '',
        tickets: @js($supportTicketsPayload),
        openTicket: null,
        followups: {},
        formatDate(value) {
            if (!value) return '';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return '';
            return date.toLocaleString('en-US', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
        },
        statusClass(status) {
            if (['resolved','closed'].includes(status)) return 'is-resolved';
            if (status === 'in_progress') return 'is-progress';
            if (status === 'waiting_on_user') return 'is-waiting';
            return 'is-open';
        },
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
                this.tickets = data.tickets || this.tickets;
                this.notice = `${data.message || 'Support ticket submitted.'}${data.ticket_number ? ' Ticket: ' + data.ticket_number : ''}`;
                this.noticeType = 'success';
                this.$refs.message.value = '';
                this.$refs.category.value = '';
                if (data.ticket_number) this.openTicket = data.ticket_number;
            } catch (error) {
                this.notice = error?.message || 'We could not submit your support ticket. Please try again.';
                this.noticeType = 'error';
            } finally {
                this.busy = false;
            }
        },
        async followUp(ticket) {
            const message = String(this.followups[ticket.id] || '').trim();
            if (!message || this.followBusy) return;
            this.followBusy = ticket.id;
            try {
                const token = document.querySelector('meta[name=csrf-token]')?.content || '';
                const response = await fetch(`{{ url('/support/tickets') }}/${ticket.id}/follow-up`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ message }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.success === false) {
                    const errors = data?.errors ? Object.values(data.errors).flat() : [];
                    throw new Error(errors[0] || data.message || 'We could not add your follow-up.');
                }
                this.tickets = data.tickets || this.tickets;
                this.followups[ticket.id] = '';
                this.notice = data.message || 'Follow-up added.';
                this.noticeType = 'success';
            } catch (error) {
                this.notice = error?.message || 'We could not add your follow-up. Please try again.';
                this.noticeType = 'error';
            } finally {
                this.followBusy = null;
            }
        }
    }"
>
    <div class="rc-support-hero-v86">
        <div class="rc-support-hero-icon-v86" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M5 5.5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-8l-4.5 3v-3H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 10h8M8 13.5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div>
            <span class="rc-support-kicker-v86">PLYRCARD Support</span>
            <h2>How can we help?</h2>
            <p>Choose the area you need help with and tell us what happened. Your account is attached to the ticket automatically.</p>
        </div>
    </div>

    <form class="rc-support-form-v86" action="{{ route('support.tickets.store') }}" method="POST" x-on:submit.prevent="submitTicket($event)">
        @csrf
        <input type="hidden" name="source" value="coach_database">

        <label class="rc-support-field-v86">
            <span>Concern</span>
            <select name="category" x-ref="category" required>
                <option value="" selected disabled>Select the area you need help with</option>
                @foreach($supportTicketCategories as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="rc-support-field-v86">
            <span>Message</span>
            <textarea name="message" x-ref="message" rows="6" maxlength="5000" minlength="10" placeholder="Describe the issue, what you expected to happen, and any useful details that can help our team review it." required></textarea>
            <small>Include the page, feature, or action you were using when relevant.</small>
        </label>

        <div class="rc-support-submit-row-v86">
            <div class="rc-support-notice-v86" x-cloak x-show="notice" x-bind:class="noticeType === 'success' ? 'is-success' : 'is-error'" x-text="notice"></div>
            <button type="submit" x-bind:disabled="busy">
                <span x-show="!busy">Submit Ticket</span>
                <span x-cloak x-show="busy">Submitting…</span>
            </button>
        </div>
    </form>

    <div class="rc-support-divider-v86"></div>

    <div class="rc-support-history-head-v86">
        <div>
            <span class="rc-support-kicker-v86">Your Requests</span>
            <h3>Track support tickets</h3>
            <p>Check status updates and add a follow-up without opening a new ticket.</p>
        </div>
        <span class="rc-support-count-v86" x-text="tickets.length + (tickets.length === 1 ? ' ticket' : ' tickets')"></span>
    </div>

    <div class="rc-support-empty-v86" x-cloak x-show="tickets.length === 0">
        <strong>No support tickets yet</strong>
        <span>Your submitted requests will appear here.</span>
    </div>

    <div class="rc-support-ticket-list-v86" x-show="tickets.length > 0">
        <template x-for="ticket in tickets" :key="ticket.id">
            <article class="rc-support-ticket-v86" :class="openTicket === ticket.ticket_number ? 'is-open' : ''">
                <button type="button" class="rc-support-ticket-summary-v86" x-on:click="openTicket = openTicket === ticket.ticket_number ? null : ticket.ticket_number">
                    <div class="rc-support-ticket-main-v86">
                        <div class="rc-support-ticket-top-v86">
                            <strong x-text="ticket.category_label"></strong>
                            <span class="rc-support-status-pill-v86" :class="statusClass(ticket.status)" x-text="ticket.status_label"></span>
                        </div>
                        <div class="rc-support-ticket-meta-v86">
                            <span x-text="ticket.ticket_number"></span>
                            <span>•</span>
                            <span x-text="'Updated ' + formatDate(ticket.updated_at)"></span>
                        </div>
                    </div>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" :class="openTicket === ticket.ticket_number ? 'is-rotated' : ''"><path d="m8 10 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                <div class="rc-support-ticket-body-v86" x-cloak x-show="openTicket === ticket.ticket_number" x-transition.opacity>
                    <div class="rc-support-thread-v86">
                        <template x-for="(entry, index) in ticket.conversation" :key="index">
                            <div class="rc-support-thread-item-v86">
                                <div class="rc-support-thread-label-v86">
                                    <strong x-text="entry.sender_name || 'You'"></strong>
                                    <span x-text="formatDate(entry.created_at)"></span>
                                </div>
                                <p x-text="entry.message"></p>
                            </div>
                        </template>
                    </div>

                    <div class="rc-support-followup-v86">
                        <label>
                            <span>Follow up on this ticket</span>
                            <textarea x-model="followups[ticket.id]" rows="3" maxlength="5000" placeholder="Add more details or ask for an update…"></textarea>
                        </label>
                        <button type="button" x-on:click="followUp(ticket)" :disabled="followBusy === ticket.id || !String(followups[ticket.id] || '').trim()">
                            <span x-show="followBusy !== ticket.id">Send Follow-up</span>
                            <span x-cloak x-show="followBusy === ticket.id">Sending…</span>
                        </button>
                    </div>
                </div>
            </article>
        </template>
    </div>
</section>

<style>
.rc-support-v86{max-width:980px;margin:0 auto;padding:1.1rem 0 2rem}.rc-support-hero-v86{display:flex;gap:1rem;align-items:flex-start;padding:1.2rem 1.25rem;border:1px solid var(--rc-border,#e5e7eb);border-radius:1rem 1rem 0 0;background:var(--rc-surface,#fff)}.rc-support-hero-icon-v86{width:2.85rem;height:2.85rem;border-radius:.85rem;background:rgba(255,99,56,.12);color:#ff6338;display:grid;place-items:center;flex:0 0 auto}.rc-support-hero-icon-v86 svg{width:1.4rem;height:1.4rem}.rc-support-kicker-v86{display:block;color:#ff6338;font-size:.7rem;line-height:1;text-transform:uppercase;letter-spacing:.12em;font-weight:850}.rc-support-hero-v86 h2,.rc-support-history-head-v86 h3{margin:.28rem 0 .25rem;color:var(--rc-text,#101828);font-size:1.25rem;letter-spacing:-.025em;font-weight:850}.rc-support-hero-v86 p,.rc-support-history-head-v86 p{margin:0;color:var(--rc-muted,#667085);font-size:.84rem;line-height:1.5}.rc-support-form-v86{padding:1.2rem 1.25rem 1.25rem;border:1px solid var(--rc-border,#e5e7eb);border-top:0;border-radius:0 0 1rem 1rem;background:var(--rc-surface,#fff);display:grid;gap:1rem}.rc-support-field-v86{display:grid;gap:.42rem}.rc-support-field-v86>span,.rc-support-followup-v86 label>span{font-size:.8rem;font-weight:800;color:var(--rc-text,#101828)}.rc-support-field-v86 select,.rc-support-field-v86 textarea,.rc-support-followup-v86 textarea{width:100%;border:1px solid var(--rc-border,#d0d5dd);background:var(--rc-surface,#fff);color:var(--rc-text,#101828);border-radius:.78rem;padding:.76rem .88rem;font:inherit;outline:none;transition:border-color .15s ease,box-shadow .15s ease}.rc-support-field-v86 textarea{min-height:9rem;resize:vertical}.rc-support-field-v86 select:focus,.rc-support-field-v86 textarea:focus,.rc-support-followup-v86 textarea:focus{border-color:#ff6338;box-shadow:0 0 0 3px rgba(255,99,56,.12)}.rc-support-field-v86 small{color:var(--rc-muted,#667085);font-size:.72rem}.rc-support-submit-row-v86{display:flex;align-items:center;justify-content:flex-end;gap:.8rem;min-height:2.5rem}.rc-support-submit-row-v86>button,.rc-support-followup-v86>button{border:0;border-radius:.75rem;background:#ff6338;color:#fff;font-size:.78rem;font-weight:850;padding:.72rem 1rem;cursor:pointer;box-shadow:0 8px 20px rgba(255,99,56,.16)}.rc-support-submit-row-v86 button:disabled,.rc-support-followup-v86 button:disabled{opacity:.55;cursor:wait}.rc-support-notice-v86{margin-right:auto;padding:.66rem .78rem;border-radius:.7rem;font-size:.76rem;font-weight:700}.rc-support-notice-v86.is-success{background:#ecfdf3;color:#067647;border:1px solid #abefc6}.rc-support-notice-v86.is-error{background:#fef3f2;color:#b42318;border:1px solid #fecdca}.rc-support-divider-v86{height:1px;background:var(--rc-border,#e5e7eb);margin:1.5rem 0}.rc-support-history-head-v86{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;margin-bottom:.8rem}.rc-support-count-v86{padding:.35rem .58rem;border-radius:999px;background:rgba(255,99,56,.1);color:#ff6338;font-size:.72rem;font-weight:800;white-space:nowrap}.rc-support-empty-v86{padding:2rem 1rem;border:1px dashed var(--rc-border,#d0d5dd);border-radius:.95rem;text-align:center;display:grid;gap:.3rem;color:var(--rc-muted,#667085)}.rc-support-empty-v86 strong{color:var(--rc-text,#101828)}.rc-support-ticket-list-v86{display:grid;gap:.65rem}.rc-support-ticket-v86{border:1px solid var(--rc-border,#e5e7eb);border-radius:.9rem;background:var(--rc-surface,#fff);overflow:hidden;transition:border-color .15s ease,box-shadow .15s ease}.rc-support-ticket-v86.is-open{border-color:rgba(255,99,56,.5);box-shadow:0 10px 30px rgba(15,23,42,.06)}.rc-support-ticket-summary-v86{width:100%;border:0;background:transparent;color:inherit;padding:.9rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;text-align:left;cursor:pointer}.rc-support-ticket-summary-v86>svg{width:1.1rem;height:1.1rem;color:var(--rc-muted,#667085);transition:transform .15s ease}.rc-support-ticket-summary-v86>svg.is-rotated{transform:rotate(180deg)}.rc-support-ticket-main-v86{min-width:0;flex:1}.rc-support-ticket-top-v86{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.rc-support-ticket-top-v86 strong{font-size:.86rem;color:var(--rc-text,#101828)}.rc-support-ticket-meta-v86{display:flex;gap:.35rem;align-items:center;margin-top:.28rem;color:var(--rc-muted,#667085);font-size:.7rem;flex-wrap:wrap}.rc-support-status-pill-v86{display:inline-flex;align-items:center;padding:.22rem .48rem;border-radius:999px;font-size:.65rem;font-weight:850}.rc-support-status-pill-v86.is-open{background:#fef3f2;color:#b42318}.rc-support-status-pill-v86.is-progress{background:#fff7ed;color:#c2410c}.rc-support-status-pill-v86.is-waiting{background:#eff8ff;color:#175cd3}.rc-support-status-pill-v86.is-resolved{background:#ecfdf3;color:#067647}.rc-support-ticket-body-v86{padding:0 1rem 1rem;border-top:1px solid var(--rc-border,#eef2f6)}.rc-support-thread-v86{display:grid;gap:.65rem;padding:.9rem 0}.rc-support-thread-item-v86{padding:.78rem .85rem;border-radius:.78rem;background:var(--rc-soft,#f8fafc);border:1px solid var(--rc-border,#eef2f6)}.rc-support-thread-label-v86{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.35rem;font-size:.7rem}.rc-support-thread-label-v86 strong{color:var(--rc-text,#101828)}.rc-support-thread-label-v86 span{color:var(--rc-muted,#667085)}.rc-support-thread-item-v86 p{margin:0;color:var(--rc-text,#101828);font-size:.79rem;line-height:1.55;white-space:pre-wrap}.rc-support-followup-v86{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.7rem;align-items:end;padding-top:.8rem;border-top:1px solid var(--rc-border,#eef2f6)}.rc-support-followup-v86 label{display:grid;gap:.4rem}.rc-support-followup-v86 textarea{resize:vertical;min-height:4.9rem}.rc-support-followup-v86>button{min-height:2.65rem;margin-bottom:0}.dark .rc-support-hero-v86,.dark .rc-support-form-v86,.dark .rc-support-ticket-v86{background:#0e1015;border-color:#282d36}.dark .rc-support-field-v86 select,.dark .rc-support-field-v86 textarea,.dark .rc-support-followup-v86 textarea{background:#17191e;border-color:#343943;color:#f8fafc}.dark .rc-support-thread-item-v86{background:#15181e;border-color:#272c35}.dark .rc-support-divider-v86,.dark .rc-support-ticket-body-v86,.dark .rc-support-followup-v86{border-color:#282d36}.dark .rc-support-hero-v86 h2,.dark .rc-support-history-head-v86 h3,.dark .rc-support-field-v86>span,.dark .rc-support-followup-v86 label>span,.dark .rc-support-ticket-top-v86 strong,.dark .rc-support-thread-label-v86 strong,.dark .rc-support-thread-item-v86 p,.dark .rc-support-empty-v86 strong{color:#f8fafc}@media(max-width:700px){.rc-support-v86{padding-top:.4rem}.rc-support-hero-v86,.rc-support-form-v86{padding:1rem}.rc-support-history-head-v86{align-items:flex-start;flex-direction:column}.rc-support-submit-row-v86{align-items:stretch;flex-direction:column}.rc-support-submit-row-v86>button{width:100%}.rc-support-notice-v86{margin-right:0}.rc-support-followup-v86{grid-template-columns:1fr}.rc-support-followup-v86>button{width:100%}.rc-support-thread-label-v86{align-items:flex-start;flex-direction:column;gap:.2rem}}
</style>