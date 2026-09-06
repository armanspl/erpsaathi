<?php

namespace App\Services;

use App\Models\Template;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders any Template Builder layout to PDF using a real (or sample) data map.
 * Modules never build their own HTML — they only supply {{placeholder}} values.
 */
class DocumentRenderService
{
    public function resolveTemplate(string $category, ?int $templateId = null): Template
    {
        abort_unless(array_key_exists($category, TemplateCatalog::CATEGORIES), 422, "Unknown document category [{$category}].");

        if ($templateId) {
            $template = Template::where('category', $category)->findOrFail($templateId);

            return $template;
        }

        $template = Template::where('category', $category)->where('is_default', true)->first()
            ?? Template::where('category', $category)->orderBy('id')->first();

        if (! $template) {
            $template = $this->ensureDefaultTemplate($category);
        }

        return $template;
    }

    /** Creates a starter template and marks it default when none exist for the category. */
    public function ensureDefaultTemplate(string $category): Template
    {
        // Prefer Classic HTML design when available so Exam Schedule / Report Card downloads
        // match the Template Builder layouts out of the box.
        if (View::exists("documents.templates.classic.{$category}")) {
            [$width, $height] = DesignCatalog::pageSize($category);

            return Template::create([
                'category' => $category,
                'name' => TemplateCatalog::CATEGORIES[$category].' — Default',
                'render_mode' => 'html',
                'is_default' => true,
                'is_favorite' => false,
                'page_width_mm' => $width,
                'page_height_mm' => $height,
                'background_color' => '#ffffff',
                'elements' => [],
                'raw_html' => DesignCatalog::seedHtml($category, 'classic'),
            ]);
        }

        $starter = TemplateCatalog::starter($category);

        return Template::create([
            'category' => $category,
            'name' => TemplateCatalog::CATEGORIES[$category].' — Default',
            'is_default' => true,
            'is_favorite' => false,
            'page_width_mm' => $starter['page_width_mm'],
            'page_height_mm' => $starter['page_height_mm'],
            'background_color' => $starter['background_color'],
            'elements' => $starter['elements'],
        ]);
    }

    public function setDefault(Template $template): Template
    {
        Template::where('category', $template->category)->where('is_default', true)->update(['is_default' => false]);
        $template->update(['is_default' => true]);

        return $template->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $preserveTokens  When true, {{field}} placeholders are left literal instead of
     *                                substituted — used by code() to produce copyable markup that
     *                                still shows which parts are dynamic.
     */
    public function html(Template $template, array $data, bool $preserveTokens = false): string
    {
        if ($template->render_mode === 'html' && $template->raw_html) {
            $body = $this->substituteTokens($template->raw_html, $data, $preserveTokens);

            return Str::contains(strtolower($body), '<html') ? $body : $this->wrapRawHtmlFragment($template, $body);
        }

        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $substitute = fn (?string $content) => $this->substituteTokens($content, $data, $preserveTokens);

        $bgImage = '';
        if ($template->background_image_path) {
            $bgUrl = str_starts_with($template->background_image_path, 'http') || str_starts_with($template->background_image_path, 'data:')
                ? $template->background_image_path
                : $this->assetDataUri($template->background_image_path);
            if ($bgUrl) {
                $bgImage = 'background-image: url(\''.$bgUrl.'\'); background-size: cover;';
            }
        }

        $elementsHtml = collect($template->elements ?? [])
            ->filter(fn ($el) => empty($el['hidden']))
            ->sortBy('z_index')
            ->map(fn ($el) => $this->renderElement($el, $substitute, $escape, $data))
            ->implode('');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            @page { margin: 0; }
            body { margin: 0; font-family: DejaVu Sans, sans-serif; }
            .page { position: relative; width: '.$template->page_width_mm.'mm; height: '.$template->page_height_mm.'mm; background-color: '.$escape($template->background_color ?: '#ffffff').'; '.$bgImage.' overflow: hidden; }
            .el { position: absolute; box-sizing: border-box; }
        </style></head><body>
            <div class="page">'.$elementsHtml.'</div>
        </body></html>';
    }

    /** @param  array<string, mixed>  $data */
    public function pdfBinary(string $category, array $data, ?int $templateId = null): string
    {
        $template = $this->resolveTemplate($category, $templateId);
        $html = $this->html($template, $data);
        $dompdf = $this->makeDompdf($template, $html);

        return $dompdf->output();
    }

    /**
     * Stack cards two-per-page on A4 (each half ≈ A5) for office printing.
     * Compact overrides shrink spacing so a full admit card fits without overflowing the other half.
     *
     * @param  list<array<string, mixed>>  $dataList
     */
    public function pdfBinaryTwoUpA4(string $category, array $dataList, ?int $templateId = null): string
    {
        $template = $this->resolveTemplate($category, $templateId);
        $halves = [];
        foreach ($dataList as $data) {
            $full = $this->html($template, $data);
            if (preg_match('/<body[^>]*>(.*)<\/body>/is', $full, $m)) {
                $halves[] = $m[1];
            } else {
                $halves[] = $full;
            }
        }

        $sheets = '';
        $count = count($halves);
        for ($i = 0; $i < $count; $i += 2) {
            $top = $halves[$i];
            $bottom = $halves[$i + 1] ?? '';
            $sheets .= '<div class="sheet">'
                .'<div class="half half-top">'.$top.'</div>'
                .'<div class="cut"><span>CUT</span></div>'
                .'<div class="half half-bottom">'.($bottom !== '' ? $bottom : '&nbsp;').'</div>'
                .'</div>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            @page { size: 210mm 297mm; margin: 0; }
            * { box-sizing: border-box; }
            body { margin: 0; padding: 0; font-family: DejaVu Sans, Helvetica, Arial, sans-serif; }
            .sheet { width: 210mm; height: 297mm; page-break-after: always; overflow: hidden; }
            .sheet:last-child { page-break-after: auto; }
            .half {
                height: 146mm; overflow: hidden; padding: 1mm 2.5mm 0.5mm;
                page-break-inside: avoid;
            }
            .cut {
                height: 5mm; border: none; margin: 0; padding: 0;
                text-align: center; vertical-align: middle;
                border-top: 0.35mm dashed #94a3b8;
                border-bottom: 0.35mm dashed #94a3b8;
                background: #f8fafc;
            }
            .cut span {
                display: inline-block; font-size: 5.5pt; color: #94a3b8;
                letter-spacing: 0.4pt; text-transform: uppercase; line-height: 5mm;
            }

            /* Compact overrides so each card fits in half an A4 */
            .half .doc { width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .half .hdr { padding: 1.6mm 3mm 1.2mm !important; }
            .half .hdr .school-name { font-size: 10pt !important; line-height: 1.1 !important; }
            .half .hdr .line2 { font-size: 5.5pt !important; margin-top: 0.5mm !important; line-height: 1.15 !important; }
            .half .hdr .line3 { font-size: 5pt !important; margin-top: 0.3mm !important; line-height: 1.1 !important; }
            .half .body { padding: 0 1.5mm 0.8mm !important; }
            .half .title-bar { padding: 1mm 0 0.7mm !important; }
            .half .title-bar .t { font-size: 8pt !important; }
            .half .sec-bar { font-size: 6pt !important; padding: 0.7mm 1.5mm !important; }
            .half .sec-gap { margin-top: 1mm !important; }
            .half .details-box { padding: 1mm 1.4mm !important; }
            .half .details-table td { padding: 0.28mm 0 !important; font-size: 6.4pt !important; line-height: 1.15 !important; }
            .half .details-table .lbl { width: 24mm !important; }
            .half .photo-cell { width: 17mm !important; padding-left: 1.2mm !important; }
            .half .photo-box, .half .photo-box img { width: 14mm !important; height: 17mm !important; }
            .half .sig-hint { font-size: 4.8pt !important; margin-top: 0.5mm !important; }
            .half .sig-hint .ln { width: 9mm !important; }
            .half table.sched th, .half table.sched td {
                padding: 0.45mm 0.8mm !important; font-size: 5.6pt !important; line-height: 1.1 !important;
            }
            .half table.sched th { font-size: 5.2pt !important; }
            .half table.sched td.dt { width: auto !important; }
            .half table.sched td.day { width: auto !important; }
            .half table.sched td.sit { width: auto !important; }
            .half .notes-block { padding: 0.6mm 0.2mm !important; font-size: 5.3pt !important; line-height: 1.2 !important; max-height: 22mm; overflow: hidden; }
            .half .notes-block ol, .half .notes-block ul { padding-left: 2.8mm !important; }
            .half .notes-block li, .half .notes-block p { margin: 0 0 0.3mm !important; }
            .half .sig-wrap { margin-top: 1.5mm !important; }
            .half .sig-img-slot { height: 6.5mm !important; margin-bottom: 0.4mm !important; }
            .half .sig-img { max-height: 6mm !important; max-width: 24mm !important; }
            .half .sig-label { min-width: 26mm !important; padding-top: 0.4mm !important; font-size: 5.2pt !important; }
        </style></head><body>'.$sheets.'</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    /** @param  array<string, mixed>  $data */
    public function streamPdf(string $category, array $data, string $filename, ?int $templateId = null): StreamedResponse
    {
        $template = $this->resolveTemplate($category, $templateId);
        $html = $this->html($template, $data);
        $filename = Str::endsWith(strtolower($filename), '.pdf') ? $filename : $filename.'.pdf';

        return response()->streamDownload(function () use ($template, $html) {
            echo $this->makeDompdf($template, $html)->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * @param  list<array<string, mixed>>  $dataList
     */
    public function streamPdfTwoUpA4(string $category, array $dataList, string $filename, ?int $templateId = null): StreamedResponse
    {
        $filename = Str::endsWith(strtolower($filename), '.pdf') ? $filename : $filename.'.pdf';
        $binary = $this->pdfBinaryTwoUpA4($category, $dataList, $templateId);

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * One full-size card per page (same layout as single download) for batch printing.
     *
     * @param  list<array<string, mixed>>  $dataList
     */
    public function pdfBinaryStacked(string $category, array $dataList, ?int $templateId = null): string
    {
        abort_if($dataList === [], 422, 'No documents to render.');

        $template = $this->resolveTemplate($category, $templateId);
        $w = (float) $template->page_width_mm;
        $h = (float) $template->page_height_mm;
        $pages = [];
        $sharedStyles = '';

        foreach ($dataList as $data) {
            $full = $this->html($template, $data);
            if ($sharedStyles === '' && preg_match('/<style[^>]*>(.*?)<\/style>/is', $full, $sm)) {
                $sharedStyles = $sm[1];
            }
            if (preg_match('/<body[^>]*>(.*)<\/body>/is', $full, $m)) {
                $pages[] = $m[1];
            } else {
                $pages[] = $full;
            }
        }

        $sheets = '';
        $last = count($pages) - 1;
        foreach ($pages as $i => $body) {
            $break = $i < $last ? 'page-break-after: always;' : '';
            $sheets .= '<div class="sheet" style="'.$break.'">'.$body.'</div>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            @page { size: '.$w.'mm '.$h.'mm; margin: 0; }
            * { box-sizing: border-box; }
            body { margin: 0; padding: 0; }
            .sheet { width: '.$w.'mm; height: '.$h.'mm; overflow: hidden; }
            '.$sharedStyles.'
        </style></head><body>'.$sheets.'</body></html>';

        return $this->makeDompdf($template, $html)->output();
    }

    /**
     * @param  list<array<string, mixed>>  $dataList
     */
    public function streamPdfStacked(string $category, array $dataList, string $filename, ?int $templateId = null): StreamedResponse
    {
        $filename = Str::endsWith(strtolower($filename), '.pdf') ? $filename : $filename.'.pdf';
        $binary = $this->pdfBinaryStacked($category, $dataList, $templateId);

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Raw markup for a template — the Template Builder's "View Code" panel. Tokens are left as
     * literal {{field}} placeholders rather than filled with sample values, so a designer can see
     * exactly which parts are dynamic. Works for both canvas templates (renders the absolute-
     * positioned inline-style HTML the canvas produces) and html-mode templates (echoes the
     * stored raw_html back).
     */
    public function code(Template $template): string
    {
        return $this->html($template, [], true);
    }

    private function substituteTokens(?string $content, array $data, bool $preserveTokens): string
    {
        $out = $content ?? '';
        if ($preserveTokens) {
            return $out;
        }
        for ($i = 0; $i < 5; $i++) {
            $next = preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($data) {
                $val = $data[$m[1]] ?? '';

                return is_scalar($val) || $val === null ? (string) $val : '';
            }, $out);
            if ($next === $out) {
                break;
            }
            $out = $next;
        }

        return $out;
    }

    /** Wraps a raw-HTML template's body fragment in a minimal document shell (@page sizing only). */
  private function wrapRawHtmlFragment(Template $template, string $body): string
{
    $width = (float) $template->page_width_mm;
    $height = (float) $template->page_height_mm;

    // Equal left and right margins. Admit cards use tighter margins so one card stays on one page.
    $marginMm = $width <= 100 ? 1.5 : ($width <= 160 ? 2 : 10);
    if ($template->category === 'admit_card' && $width >= 200) {
        $marginMm = 8;
    } elseif ($template->category === 'admit_card') {
        $marginMm = 4;
    }
    if ($template->category === 'exam_schedule') {
        $marginMm = 6;
    }

    // Actual printable/content width.
    $contentWidthMm = max(1, $width - ($marginMm * 2));

    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <style>
        @page {
            size: '.$width.'mm '.$height.'mm;
            margin: '.$marginMm.'mm;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box !important;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        }

        .doc {
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
        }

        .tc-page,
.bc-page {
    width: '.$contentWidthMm.'mm !important;
    max-width: '.$contentWidthMm.'mm !important;
    margin: 0 auto !important;
    padding: 0 !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
}


        
      .tc-topline-row,
.tc-logo-row,
.tc-titlebar,
.tc-refrow-row,
.tc-list,
.tc-footer-row,

.bc-topline-row,
.bc-logo-row,
.bc-titlebar,
.bc-refrow-row,
.bc-body-row,
.bc-grid,
.bc-footer-row {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}
    </style>
</head>

<body>
    '.$body.'
</body>
</html>';
}
    private function makeDompdf(Template $template, string $html): Dompdf
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper([0, 0, $this->mmToPt((float) $template->page_width_mm), $this->mmToPt((float) $template->page_height_mm)]);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf;
    }

    /** @param  array<string, mixed>  $data */
    private function renderElement(array $el, callable $substitute, callable $escape, array $data): string
    {
        $style = sprintf(
            'left: %smm; top: %smm; width: %smm; height: %smm; transform: rotate(%sdeg); opacity: %s; padding: %smm;',
            $el['x'], $el['y'], $el['width'], $el['height'], $el['rotation'] ?? 0, ($el['opacity'] ?? 100) / 100, $el['padding_mm'] ?? 0
        );
        if (! empty($el['fill_color'])) {
            $style .= 'background-color: '.$escape($el['fill_color']).';';
        }
        if (! empty($el['border_color']) && ! empty($el['border_width_mm'])) {
            $style .= 'border: '.$el['border_width_mm'].'mm solid '.$escape($el['border_color']).';';
        }
        if (! empty($el['border_radius_mm'])) {
            $style .= 'border-radius: '.$el['border_radius_mm'].'mm;';
        }

        return match ($el['type']) {
            'rectangle', 'ellipse' => '<div class="el" style="'.$style.($el['type'] === 'ellipse' ? 'border-radius: 50%;' : '').'"></div>',
            'line' => '<div class="el" style="'.$style.'border-top: '.($el['border_width_mm'] ?: 0.3).'mm solid '.$escape($el['border_color'] ?: '#1e293b').';"></div>',
            'qrcode' => '<div class="el" style="'.$style.'"><img src="'.$this->qrDataUri($substitute($el['content'] ?: 'https://example.com')).'" style="width:100%;height:100%;" /></div>',
            'image' => $this->renderImageElement($el, $style, $substitute, $data),
            default => '<div class="el" style="'.$style.'overflow: hidden; white-space: pre-wrap; word-wrap: break-word; font-family: '.$escape($el['font_family'] ?: 'DejaVu Sans').'; font-size: '.($el['font_size'] ?: 10).'pt; font-weight: '.(! empty($el['bold']) ? 'bold' : 'normal').'; font-style: '.(! empty($el['italic']) ? 'italic' : 'normal').'; text-align: '.($el['align'] ?: 'left').'; line-height: '.($el['line_height'] ?: 1.25).'; color: '.$escape($el['text_color'] ?: '#1e293b').';">'
                .$escape(($el['label_prefix'] ?? '').$substitute($el['content'] ?? '')).'</div>',
        };
    }

    /** @param  array<string, mixed>  $data */
    private function renderImageElement(array $el, string $style, callable $substitute, array $data): string
    {
        $path = $el['image_path'] ?? null;
        $fieldKey = $el['field_key'] ?? null;
        $dynamic = $fieldKey && ! empty($data[$fieldKey]) ? (string) $data[$fieldKey] : null;

        foreach ([$path, $dynamic] as $candidate) {
            if (! $candidate) {
                continue;
            }
            if (str_starts_with($candidate, 'data:') || str_starts_with($candidate, 'http')) {
                return '<div class="el" style="'.$style.'"><img src="'.$candidate.'" style="width:100%;height:100%;object-fit:cover;" /></div>';
            }
            if (Storage::disk('local')->exists($candidate)) {
                return '<div class="el" style="'.$style.'"><img src="'.$this->assetDataUri($candidate).'" style="width:100%;height:100%;object-fit:cover;" /></div>';
            }
            if (Storage::disk('public')->exists($candidate)) {
                $contents = Storage::disk('public')->get($candidate);
                $mime = Storage::disk('public')->mimeType($candidate) ?: 'image/png';

                return '<div class="el" style="'.$style.'"><img src="data:'.$mime.';base64,'.base64_encode($contents).'" style="width:100%;height:100%;object-fit:cover;" /></div>';
            }
        }

        $label = $dynamic ?: ($fieldKey ? ucwords(str_replace('_', ' ', $fieldKey)) : 'Image');

        return '<div class="el" style="'.$style.'border: 0.3mm dashed #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 8pt; color: #94a3b8;">'.htmlspecialchars((string) $label).'</div>';
    }

    private function assetDataUri(string $path): ?string
    {
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }
        $contents = Storage::disk('local')->get($path);
        $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function qrDataUri(string $data): string
    {
        $result = (new Builder())->build(data: $data ?: 'https://example.com', size: 300, margin: 4);

        return $result->getDataUri();
    }

    private function mmToPt(float $mm): float
    {
        return $mm * 2.8346456693;
    }
}
