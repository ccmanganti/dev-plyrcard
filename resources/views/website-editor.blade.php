<style>
  /* wrap the editor so scaling doesn't break layout */
  .studio-zoom-wrap {
    width: 100%;
    height: 80vh;
    overflow: hidden;         /* prevents scrollbars caused by scaling */
    background: #0b0b0b;      /* optional */
  }

  #studio-editor {
    transform-origin: top left;
    transform: scale(0.75);   /* 👈 adjust: 0.75, 0.8, 0.9 */
    width: calc(100% / 0.75);
    height: calc(100vh / 0.75);
  }
</style>

<div class="studio-zoom-wrap">
  <div id="studio-editor"></div>
</div>
<script src="https://unpkg.com/@grapesjs/studio-sdk/dist/index.umd.js"></script>
<link rel="stylesheet" href="https://unpkg.com/@grapesjs/studio-sdk/dist/style.css"/>

<script>
@if($record)
const websiteId = '{{ $record->id }}';
const loadUrl = '{{ route("websites.load", ["id" => $record->id]) }}';
const saveUrl = '{{ route("websites.save", ["id" => $record->id]) }}';

GrapesJsStudioSDK.createStudioEditor({
    root: '#studio-editor',
    licenseKey: '6ef2ec113b364a9390ce6c0ded4d4cf872b1ad2a0ca244e5b41d5d2615eb1648',
    project: { type: 'web', id: websiteId },
    identity: { id: websiteId },
    assets: {
    storageType: 'self',
        // Upload to Laravel
        onUpload: async ({ files }) => {
            const body = new FormData();

            for (const file of files) {
                body.append('files[]', file);
            }

            const response = await fetch('{{ route("websites.assets.upload", ["id" => $record->id]) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body
            });

            if (!response.ok) throw new Error('Upload failed');

            // Must return [{ src: 'url' }]
            return await response.json();
        },

        // Delete from Laravel
        onDelete: async ({ assets }) => {
            await fetch('{{ route("websites.assets.delete", ["id" => $record->id]) }}', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(assets)
            });
        }
    },
    storage: {
        type: 'self',
        onLoad: async () => {
            const response = await fetch(loadUrl);
            if (!response.ok) throw new Error('Failed to load project');
            const { project } = await response.json();
            return { project };
        },
        onSave: async ({ project, editor }) => {
            // Collect HTML and CSS for all pages
            const pagesHtml = editor.Pages.getAll().map(page => {
                const component = page.getMainComponent();
                return {
                    html: editor.getHtml({ component }),
                    css: editor.getCss({ component }),
                };
            });

            // Prepare FormData
            const body = new FormData();
            body.append('project', JSON.stringify(project));
            body.append('pages_html', JSON.stringify(pagesHtml));

            // Don't set Content-Type, let fetch handle FormData
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body
            });

            if (!response.ok) throw new Error('Failed to save project');
            return await response.json();
        },
        autosaveChanges: 100,
        autosaveIntervalMs: 10000
    }
});
@endif
</script>