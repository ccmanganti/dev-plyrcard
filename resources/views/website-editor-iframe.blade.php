<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <link rel="stylesheet" href="https://unpkg.com/@grapesjs/studio-sdk/dist/style.css"/>

  <style>
    html, body {
      height: 100%;
      margin: 0;
      background: #0b0b0b;
      overflow: hidden;
    }

    #studio-editor {
      height: 100vh;
      width: 100vw;
    }

    /* Keep scrollbars only inside the canvas */
/* html, body { overflow: hidden; } */


/* ===== Base layout (no iframe/page scrollbars) ===== */
html, body {
  height: 100%;
  margin: 0;
  background: #0b0b0b;
  overflow: hidden;
}

#studio-editor {
  height: 100vh;
  width: 100%;
}

/* ===== Scrollbars ONLY inside canvas viewport ===== */
.gjs-cv-canvas {
  overflow: auto !important;
  padding: 0 !important;            /* IMPORTANT: no padding here */
  box-sizing: border-box !important;
}

/* Center the frames INSIDE the canvas without flexing the canvas itself */
.gjs-cv-canvas__frames {
  width: 100% !important;
  min-width: 100% !important;
  box-sizing: border-box !important;

  /* put your spacing here instead
  /* padding: 24px 360px 24px 24px !important; */
}

/* The frame wrapper should be centered */
.gjs-frame-wrapper,
.gjs-cv-canvas__frame {
  margin-left: auto !important;
  margin-right: auto !important;
}
  </style>
</head>

<body>
  <div id="studio-editor"></div>
  <script src="https://unpkg.com/@grapesjs/studio-sdk/dist/index.umd.js"></script>

<script>
@if($record)
  const websiteId = '{{ $record->id }}';
  const loadUrl = '{{ route("websites.load", ["id" => $record->id]) }}';
  const saveUrl = '{{ route("websites.save", ["id" => $record->id]) }}';
  const uploadUrl = '{{ route("websites.assets.upload", ["id" => $record->id]) }}';
  const deleteUrl = '{{ route("websites.assets.delete", ["id" => $record->id]) }}';

  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  GrapesJsStudioSDK.createStudioEditor({
    root: '#studio-editor',
    licenseKey: '6ef2ec113b364a9390ce6c0ded4d4cf872b1ad2a0ca244e5b41d5d2615eb1648',
    project: { type: 'web', id: websiteId },
    identity: { id: websiteId },

onReady: (editor) => {
  // Update existing devices (or add if missing)
  const upsertDevice = (id, name, width) => {
    const d = editor.Devices.get(id);
    if (d) return d.set({ name, width });
    editor.Devices.add({ id, name, width });
  };

  const resetView = () => {
  editor.Canvas.setZoom(100);     // 0..100 only
  editor.Canvas.setCoords(0, 0);  // reset pan position
};

  upsertDevice('desktop', 'Desktop', '1440px');
  upsertDevice('tablet', 'Tablet', '768px');
  upsertDevice('mobile', 'Mobile', '375px');

  const parsePx = (val) => {
    const n = parseFloat(String(val || '').replace('px', ''));
    return Number.isFinite(n) ? n : null;
  };

  const getCanvasWidth = () => {
    // find a stable canvas container width
    const el =
      document.querySelector('.gjs-cv-canvas') ||
      document.querySelector('.gjs-canvas') ||
      document.querySelector('#studio-editor');
    return el?.clientWidth ? Math.max(320, el.clientWidth - 40) : null;
  };

  const fitToDevice = () => {
    const devId = editor.getDevice();
    const dev = devId ? editor.Devices.get(devId) : null;
    const devPx = parsePx(dev?.get?.('width'));
    const avail = getCanvasWidth();
    if (!devPx || !avail) return;

    let zoom = Math.floor((avail / devPx) * 100);
    zoom = Math.max(25, Math.min(100, zoom)); // clamp
    editor.Canvas.setZoom(zoom);
  };

  // default
  editor.setDevice('desktop');

  // fit now (delay ensures DOM exists)
  setTimeout(fitToDevice, 0);

  // refit when you switch desktop/tablet/mobile
  editor.on('device:select', () => setTimeout(fitToDevice, 0));

  // refit when iframe/browser resizes
  window.addEventListener('resize', () => setTimeout(fitToDevice, 50));
},

    assets: {
      storageType: 'self',

      onUpload: async ({ files }) => {
        const body = new FormData();
        for (const file of files) body.append('files[]', file);

        const response = await fetch(uploadUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrf },
          body,
          credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Upload failed');
        return await response.json();
      },

      onDelete: async ({ assets }) => {
        const response = await fetch(deleteUrl, {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
          },
          body: JSON.stringify(assets),
          credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Delete failed');
        return await response.json();
      },
    },

    storage: {
      type: 'self',

      onLoad: async () => {
        const response = await fetch(loadUrl, { credentials: 'same-origin' });
        if (!response.ok) throw new Error('Failed to load project');
        const { project } = await response.json();
        return { project };
      },

      onSave: async ({ project, editor }) => {
        const pagesHtml = editor.Pages.getAll().map(page => {
          const component = page.getMainComponent();
          return {
            html: editor.getHtml({ component }),
            css: editor.getCss({ component }),
          };
        });

        const body = new FormData();
        body.append('project', JSON.stringify(project));
        body.append('pages_html', JSON.stringify(pagesHtml));

        const response = await fetch(saveUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrf },
          body,
          credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Failed to save project');
        return await response.json();
      },

      autosaveChanges: 100,
      autosaveIntervalMs: 10000,
    },
  });

  const fitToDevice = () => {
  const devId = editor.getDevice();
  const dev = devId ? editor.Devices.get(devId) : null;
  const devPx = parsePx(dev?.get?.('width'));
  const avail = getCanvasWidth();
  if (!devPx || !avail) return;

  let zoom = Math.floor((avail / devPx) * 100);

  // allow zoom-in but cap it so tablet doesn't look crazy
  zoom = Math.max(25, Math.min(110, zoom)); // 110 max
  editor.Canvas.setZoom(zoom);
};
@endif

</script>
</body>
</html>