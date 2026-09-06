<?php

namespace App\Http\Controllers\Erp\Documents;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\DesignCatalog;
use App\Services\DocumentRenderService;
use App\Services\TemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateController extends Controller
{
    public function __construct(private DocumentRenderService $renderer) {}

    public function categories()
    {
        return response()->json(
            collect(TemplateCatalog::CATEGORIES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()
        );
    }

    public function pagePresets()
    {
        return response()->json(
            collect(TemplateCatalog::PAGE_PRESETS)->map(fn ($size, $label) => [
                'label' => $label,
                'width_mm' => $size[0],
                'height_mm' => $size[1],
            ])->values()
        );
    }

    public function fields(Request $request)
    {
        $data = $request->validate(['category' => ['required', Rule::in(array_keys(TemplateCatalog::CATEGORIES))]]);

        return response()->json(
            collect(TemplateCatalog::FIELDS[$data['category']])->map(fn ($f) => ['key' => $f[0], 'label' => $f[1], 'locked' => $f[2]])->values()
        );
    }

    public function sampleData(Request $request)
    {
        $data = $request->validate(['category' => ['required', Rule::in(array_keys(TemplateCatalog::CATEGORIES))]]);

        return response()->json(TemplateCatalog::sampleData($data['category']));
    }

    /** Pre-built design options offered when creating a template, alongside the blank canvas. */
    public function designs(Request $request)
    {
        $data = $request->validate(['category' => ['required', Rule::in(array_keys(TemplateCatalog::CATEGORIES))]]);
        $category = $data['category'];
        [$width, $height] = DesignCatalog::pageSize($category);

        return response()->json([
            'page_width_mm' => $width,
            'page_height_mm' => $height,
            'designs' => collect(DesignCatalog::list($category))->map(function ($d) use ($category) {
                [$dw, $dh] = DesignCatalog::pageSize($category, $d['key']);

                return [
                    'key' => $d['key'],
                    'label' => $d['label'],
                    'page_width_mm' => $dw,
                    'page_height_mm' => $dh,
                    'preview_html' => DesignCatalog::previewHtml($category, $d['key']),
                ];
            })->values(),
        ]);
    }

    public function index(Request $request)
    {
        $query = Template::query();
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return response()->json($query->orderByDesc('is_default')->orderBy('name')->get());
    }

    public function show(Template $template)
    {
        return response()->json($template);
    }

    public function store(Request $request)
    {
        $category = $request->input('category');
        $allowedDesigns = array_merge(['blank'], array_keys(DesignCatalog::themesFor(is_string($category) ? $category : '')));

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(TemplateCatalog::CATEGORIES))],
            'name' => 'required|string|max:255',
            'design_key' => ['nullable', 'string', Rule::in($allowedDesigns)],
            'set_as_default' => 'nullable|boolean',
        ]);

        $category = $data['category'];
        $designKey = $data['design_key'] ?? 'blank';
        $makeDefault = ! empty($data['set_as_default'])
            || ! Template::where('category', $category)->where('is_default', true)->exists();

        if ($makeDefault) {
            Template::where('category', $category)->where('is_default', true)->update(['is_default' => false]);
        }

        if ($designKey === 'blank') {
            $starter = TemplateCatalog::starter($category);
            $template = Template::create([
                'category' => $category,
                'name' => $data['name'],
                'render_mode' => 'canvas',
                'is_default' => $makeDefault,
                'is_favorite' => false,
                'page_width_mm' => $starter['page_width_mm'],
                'page_height_mm' => $starter['page_height_mm'],
                'background_color' => $starter['background_color'],
                'elements' => $starter['elements'],
            ]);
        } else {
            [$width, $height] = DesignCatalog::pageSize($category, $designKey);
            $template = Template::create([
                'category' => $category,
                'name' => $data['name'],
                'render_mode' => 'html',
                'is_default' => $makeDefault,
                'is_favorite' => false,
                'page_width_mm' => $width,
                'page_height_mm' => $height,
                'background_color' => '#ffffff',
                'elements' => [],
                'raw_html' => DesignCatalog::seedHtml($category, $designKey),
            ]);
        }

        return response()->json($template, 201);
    }

    public function update(Request $request, Template $template)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'page_width_mm' => 'required|numeric|min:20|max:1000',
            'page_height_mm' => 'required|numeric|min:20|max:1000',
            'background_color' => 'nullable|string|max:20',
            'background_image_path' => 'nullable|string|max:500',
            'elements' => 'array',
            'elements.*.id' => 'required|string',
            'elements.*.type' => ['required', Rule::in(['text', 'rectangle', 'ellipse', 'line', 'qrcode', 'image'])],
            'elements.*.field_key' => 'nullable|string|max:100',
            'elements.*.locked' => 'boolean',
            'elements.*.hidden' => 'boolean',
            'elements.*.label_prefix' => 'nullable|string|max:255',
            'elements.*.content' => 'nullable|string',
            'elements.*.x' => 'numeric', 'elements.*.y' => 'numeric',
            'elements.*.width' => 'numeric', 'elements.*.height' => 'numeric',
            'elements.*.rotation' => 'numeric',
            'elements.*.font_family' => 'nullable|string|max:50',
            'elements.*.font_size' => 'numeric',
            'elements.*.bold' => 'boolean', 'elements.*.italic' => 'boolean',
            'elements.*.align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'elements.*.line_height' => 'numeric',
            'elements.*.text_color' => 'nullable|string|max:20',
            'elements.*.fill_color' => 'nullable|string|max:20',
            'elements.*.border_color' => 'nullable|string|max:20',
            'elements.*.border_width_mm' => 'numeric',
            'elements.*.border_radius_mm' => 'numeric',
            'elements.*.padding_mm' => 'numeric',
            'elements.*.opacity' => 'numeric|min:0|max:100',
            'elements.*.z_index' => 'integer',
            'elements.*.image_path' => 'nullable|string|max:500',
        ]);

        $template->update($data);

        return response()->json($template->fresh());
    }

    public function destroy(Template $template)
    {
        $wasDefault = $template->is_default;
        $category = $template->category;
        $template->delete();

        if ($wasDefault) {
            $next = Template::where('category', $category)->orderBy('id')->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return response()->json(['success' => true]);
    }

    public function duplicate(Template $template)
    {
        $copy = $template->replicate();
        $copy->name = $template->name.' (Copy)';
        $copy->is_default = false;
        $copy->is_favorite = false;
        $copy->save();

        return response()->json($copy, 201);
    }

    public function toggleFavorite(Template $template)
    {
        $template->update(['is_favorite' => ! $template->is_favorite]);

        return response()->json($template);
    }

    public function setDefault(Template $template)
    {
        return response()->json($this->renderer->setDefault($template));
    }

    public function downloadJson(Template $template): StreamedResponse
    {
        $payload = $template->only(['category', 'name', 'page_width_mm', 'page_height_mm', 'background_color', 'background_image_path', 'elements']);

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT);
        }, Str::slug($template->name).'.json', ['Content-Type' => 'application/json']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|max:2048']);

        $payload = json_decode($request->file('file')->get(), true);
        abort_if(! is_array($payload) || ! isset($payload['category'], $payload['elements']), 422, 'Invalid template file.');
        abort_unless(array_key_exists($payload['category'], TemplateCatalog::CATEGORIES), 422, 'Unknown template category in file.');

        $makeDefault = ! Template::where('category', $payload['category'])->where('is_default', true)->exists();

        $template = Template::create([
            'category' => $payload['category'],
            'name' => ($payload['name'] ?? 'Imported template').' (Imported)',
            'is_default' => $makeDefault,
            'is_favorite' => false,
            'page_width_mm' => $payload['page_width_mm'] ?? 210,
            'page_height_mm' => $payload['page_height_mm'] ?? 297,
            'background_color' => $payload['background_color'] ?? '#ffffff',
            'background_image_path' => $payload['background_image_path'] ?? null,
            'elements' => $payload['elements'],
        ]);

        return response()->json($template, 201);
    }

    public function uploadAsset(Request $request, Template $template)
    {
        $request->validate(['file' => 'required|image|max:4096']);

        $path = $request->file('file')->store("templates/{$template->id}", 'local');

        return response()->json(['path' => $path, 'url' => route('erp.api.documents.templates.asset', [$template, basename($path)])]);
    }

    public function asset(Template $template, string $filename)
    {
        $path = "templates/{$template->id}/{$filename}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function previewPdf(Template $template): StreamedResponse
    {
        $data = TemplateCatalog::sampleData($template->category);

        return $this->renderer->streamPdf($template->category, $data, Str::slug($template->name).'-preview.pdf', $template->id);
    }

    /**
     * "View Code" panel: the underlying markup for this template, with {{field}} tokens left
     * literal (canvas mode) or the raw stored HTML echoed back (html mode) — for copying out to
     * a designer, not for live preview (use previewPdf for that).
     */
    public function code(Template $template)
    {
        return response()->json([
            'render_mode' => $template->render_mode,
            'html' => $this->renderer->code($template),
            'raw_html' => $template->raw_html,
        ]);
    }

    /**
     * Import/paste code back in: switches this template to render_mode "html" and stores the
     * given markup as its raw_html, replacing the canvas/elements as the source of truth for
     * rendering. Accepts either a JSON { html } body or a multipart file upload.
     */
    public function updateCode(Request $request, Template $template)
    {
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|max:2048']);
            $html = $request->file('file')->get();
        } else {
            $data = $request->validate(['html' => 'required|string|max:2000000']);
            $html = $data['html'];
        }

        abort_if(trim((string) $html) === '', 422, 'HTML content is empty.');

        $template->update([
            'render_mode' => 'html',
            'raw_html' => $html,
        ]);

        return response()->json($template->fresh());
    }

    /** Toggle a template between canvas and html render modes without discarding either representation. */
    public function switchRenderMode(Request $request, Template $template)
    {
        $data = $request->validate(['render_mode' => ['required', Rule::in(['canvas', 'html'])]]);
        $template->update(['render_mode' => $data['render_mode']]);

        return response()->json($template->fresh());
    }
}
