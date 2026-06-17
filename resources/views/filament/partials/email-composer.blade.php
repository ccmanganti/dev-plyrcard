<div
    class="rc-composer"
    x-data="{
        body: @js($emailBody),
        sync() {
            this.body = this.$refs.editor.innerHTML;
            this.$wire.set('emailBody', this.body, false);
        },
        command(name, value = null) {
            this.$refs.editor.focus();
            document.execCommand(name, false, value);
            this.sync();
        },
        promptLink() {
            const url = window.prompt('Paste link URL');
            if (! url) return;
            this.command('createLink', url);
        },
        send() {
            this.sync();
            this.$wire.sendEmail();
        },
        initEditor() {
            this.$refs.editor.innerHTML = this.body || '';
            this.$watch('body', value => {
                if (document.activeElement !== this.$refs.editor && this.$refs.editor.innerHTML !== value) {
                    this.$refs.editor.innerHTML = value || '';
                }
            });
        }
    }"
    x-init="initEditor()"
>
    <div class="rc-section-title">Compose email</div>
    <input class="rc-input" style="width:100%" placeholder="Subject" wire:model="emailSubject" />

    <div class="rc-rich-toolbar" aria-label="Email formatting">
        <button type="button" class="rc-btn rc-format-btn" @click="command('bold')"><strong>B</strong></button>
        <button type="button" class="rc-btn rc-format-btn" @click="command('italic')"><em>I</em></button>
        <button type="button" class="rc-btn rc-format-btn" @click="command('underline')"><u>U</u></button>
        <button type="button" class="rc-btn rc-format-btn" @click="command('insertUnorderedList')">List</button>
        <button type="button" class="rc-btn rc-format-btn" @click="promptLink()">Link</button>
        <button type="button" class="rc-btn rc-format-btn" @click="command('removeFormat')">Clear</button>
    </div>

    <div
        class="rc-rich-editor"
        contenteditable="true"
        wire:ignore
        x-ref="editor"
        @input.debounce.200ms="sync()"
        @blur="sync()"
        data-placeholder="Write your email…"
    ></div>

    <textarea class="rc-sr-only" wire:model="emailBody" aria-hidden="true" tabindex="-1"></textarea>

    <div class="rc-toolbar">
        <button class="rc-btn rc-btn-primary" type="button" @click="send()" wire:loading.attr="disabled" wire:target="sendEmail">
            <span wire:loading.remove wire:target="sendEmail">Send email</span>
            <span wire:loading.flex wire:target="sendEmail" style="align-items:center;gap:.35rem"><span class="rc-spinner-mini"></span> Sending</span>
        </button>
        <button class="rc-btn" type="button" wire:click="closeComposer">Cancel</button>
    </div>
</div>