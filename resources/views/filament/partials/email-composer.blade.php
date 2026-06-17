<div style="margin-top:1rem" class="rc-grid">
    <div class="rc-top">
        <div>
            <div class="rc-section-title" style="margin-bottom:.25rem">Compose email</div>
            @if($this->selectedCoach)
                <div class="rc-subtle">To {{ $this->selectedCoach['name'] ?? 'Coach' }} @if($this->selectedCoach['email'] ?? null) · {{ $this->selectedCoach['email'] }} @endif</div>
            @elseif($selectedConversationId)
                <div class="rc-subtle">Replying in selected conversation</div>
            @endif
        </div>
        @if($selectedCoachId)<button class="rc-btn" type="button" wire:click="closeComposer">Close</button>@endif
    </div>
    <input class="rc-input" placeholder="Subject" wire:model="emailSubject" />
    <textarea class="rc-textarea" rows="5" placeholder="Write email" wire:model="emailBody"></textarea>
    <div class="rc-toolbar">
        <button class="rc-btn rc-btn-primary" type="button" wire:click="sendEmail" wire:loading.attr="disabled" wire:target="sendEmail">
            <span wire:loading.remove wire:target="sendEmail">Send email</span>
            <span wire:loading.flex wire:target="sendEmail" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Sending</span>
        </button>
    </div>
</div>