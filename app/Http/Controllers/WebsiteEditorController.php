<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Website;
use Illuminate\Support\Facades\Storage;

class WebsiteEditorController extends Controller
{
        public function editor($id)
    {
        $website = Website::findOrFail($id);

        return view('website-editor-iframe', [
            'record' => $website,
        ]);
    }

    public function loadProject($id)
    {
        $website = Website::findOrFail($id);

        return response()->json([
            'project' => json_decode($website->project_json ?? '{}', true),
        ]);
    }


    public function saveProject(Request $request, $id)
    {
        $website = Website::findOrFail($id);

        // Get project JSON
        $projectJson = $request->input('project');

        // Get HTML/CSS from all pages
        $pagesHtmlJson = $request->input('pages_html');
        $pagesHtml = json_decode($pagesHtmlJson, true);

        // Combine HTML and CSS from all pages
        $combinedHtml = '';
        $combinedCss = '';

        foreach ($pagesHtml as $page) {
            $combinedHtml .= $page['html'] ?? '';
            $combinedCss .= $page['css'] ?? '';
        }

        // Save separately
        $website->project_json = $projectJson;
        $website->html = $combinedHtml; // combined HTML
        $website->css = $combinedCss;   // combined CSS
        $website->save();

        return response()->json(['status' => 'success']);
    }

    public function uploadAsset(Request $request, $id)
    {
        $files = $request->file('files');

        if (!$files) {
            return response()->json([], 200); // Must return array
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        $uploaded = [];

        foreach ($files as $file) {
            $path = $file->store("websites/{$id}/assets", 'public');

            $uploaded[] = [
                'src' => Storage::disk('public')->url($path),
            ];
        }

        // IMPORTANT: return array ONLY
        return response()->json($uploaded);
    }

    public function deleteAsset(Request $request, $id)
    {
        $assets = $request->all();

        foreach ($assets as $asset) {

            if (!isset($asset['src'])) continue;

            $url = $asset['src'];

            // Convert URL back to storage path
            $relativePath = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));

            Storage::disk('public')->delete($relativePath);
        }

        return response()->json(['success' => true]);
    }
}
