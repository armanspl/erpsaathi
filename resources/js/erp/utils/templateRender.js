// Shared between the Template Builder's list-page thumbnails and the full canvas editor —
// both need to turn a template's elements[] into positioned boxes at some px-per-mm scale.

/** CSS px per mm at 96dpi (browser default). */
export const PX_PER_MM = 96 / 25.4; // ≈ 3.7795275591

/** Point size of 1 mm (72pt = 1 inch). */
export const PT_PER_MM = 72 / 25.4; // ≈ 2.8346456693

/**
 * Replace {{tokens}} using sample data. Runs until stable so nested tokens
 * inside sample values (e.g. certificate_body) also resolve.
 */
export function substituteTokens(content, sample) {
    if (!content) return '';
    let out = String(content);
    for (let i = 0; i < 5; i++) {
        const next = out.replace(/\{\{(\w+)\}\}/g, (_, key) => {
            const val = sample?.[key];
            return val == null ? '' : String(val);
        });
        if (next === out) break;
        out = next;
    }
    return out;
}

export function contentLooksLikeHtml(value) {
    return /<\/?[a-z][\s\S]*>/i.test(String(value || ''));
}

export function elementBoxStyle(el, scale) {
    return {
        position: 'absolute',
        left: `${el.x * scale}px`,
        top: `${el.y * scale}px`,
        width: `${el.width * scale}px`,
        height: `${el.height * scale}px`,
        transform: `rotate(${el.rotation || 0}deg)`,
        transformOrigin: 'center center',
        opacity: (el.opacity ?? 100) / 100,
        boxSizing: 'border-box',
        padding: `${(el.padding_mm || 0) * scale}px`,
        backgroundColor: el.fill_color || (el.type === 'ellipse' || el.type === 'rectangle' ? 'transparent' : undefined),
        border: el.border_color && el.border_width_mm ? `${el.border_width_mm * scale}px solid ${el.border_color}` : undefined,
        borderRadius: el.type === 'ellipse' ? '50%' : el.border_radius_mm ? `${el.border_radius_mm * scale}px` : undefined,
        borderTop: el.type === 'line' ? `${(el.border_width_mm || 0.3) * scale}px solid ${el.border_color || '#1e293b'}` : undefined,
        overflow: 'hidden',
    };
}

/**
 * font_size is stored in points. Geometry uses mm→px via `scale`.
 * Convert pt → mm → px: (pt / PT_PER_MM) * scale.
 */
export function textStyle(el, scale) {
    const fontPt = Number(el.font_size) || 10;
    const fontPx = (fontPt / PT_PER_MM) * scale;

    return {
        fontFamily: el.font_family || 'Helvetica, Arial, sans-serif',
        fontSize: `${fontPx}px`,
        fontWeight: el.bold ? '700' : '400',
        fontStyle: el.italic ? 'italic' : 'normal',
        textAlign: el.align || 'left',
        lineHeight: el.line_height || 1.25,
        color: el.text_color || '#1e293b',
        overflow: 'hidden',
        whiteSpace: 'pre-wrap',
        wordBreak: 'break-word',
        width: '100%',
        height: '100%',
        boxSizing: 'border-box',
    };
}

export function assetUrl(templateId, path) {
    if (!path) return null;
    if (/^(https?:|data:)/.test(path)) return path;
    const filename = path.split('/').pop();
    return `/erp/api/documents/templates/${templateId}/assets/${filename}`;
}

export function pageStyle(template, scale) {
    const bg = assetUrl(template.id, template.background_image_path);
    return {
        position: 'relative',
        width: `${template.page_width_mm * scale}px`,
        height: `${template.page_height_mm * scale}px`,
        backgroundColor: template.background_color || '#ffffff',
        backgroundImage: bg ? `url('${bg}')` : undefined,
        backgroundSize: 'cover',
        overflow: 'hidden',
        margin: '0 auto',
    };
}
