// Runtime "primary brand color" theming. Tailwind v4 exposes @theme colors as real CSS
// custom properties, so overriding --color-primary-* on :root re-colors every `primary-*`
// utility class already compiled into the bundle — no rebuild needed.

const SHADES = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];
const SHADE_LIGHTNESS = { 50: 97, 100: 94, 200: 87, 300: 78, 400: 67, 500: 56, 600: 47, 700: 39, 800: 32, 900: 26, 950: 17 };

export const DEFAULT_THEME_COLOR = '#6366f1'; // Indigo — matches the built-in default palette

export const PRESET_COLORS = [
    { name: 'Indigo', hex: '#6366f1' },
    { name: 'Blue', hex: '#2563eb' },
    { name: 'Sky', hex: '#0284c7' },
    { name: 'Teal', hex: '#0d9488' },
    { name: 'Emerald', hex: '#059669' },
    { name: 'Violet', hex: '#7c3aed' },
    { name: 'Purple', hex: '#9333ea' },
    { name: 'Pink', hex: '#db2777' },
    { name: 'Rose', hex: '#e11d48' },
    { name: 'Orange', hex: '#ea580c' },
    { name: 'Amber', hex: '#d97706' },
    { name: 'Slate', hex: '#475569' },
];

function hexToHsl(hex) {
    const clean = hex.replace('#', '');
    const bigint = parseInt(clean.length === 3 ? clean.replace(/./g, (c) => c + c) : clean, 16);
    const r = ((bigint >> 16) & 255) / 255;
    const g = ((bigint >> 8) & 255) / 255;
    const b = (bigint & 255) / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0;
    const l = (max + min) / 2;
    const d = max - min;
    const s = d === 0 ? 0 : d / (1 - Math.abs(2 * l - 1));
    if (d !== 0) {
        switch (max) {
            case r: h = ((g - b) / d) % 6; break;
            case g: h = (b - r) / d + 2; break;
            default: h = (r - g) / d + 4;
        }
        h *= 60;
        if (h < 0) h += 360;
    }
    return { h, s, l };
}

function hslToHex(h, s, l) {
    const c = (1 - Math.abs(2 * l - 1)) * s;
    const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
    const m = l - c / 2;
    let [r, g, b] = [0, 0, 0];
    if (h < 60) [r, g, b] = [c, x, 0];
    else if (h < 120) [r, g, b] = [x, c, 0];
    else if (h < 180) [r, g, b] = [0, c, x];
    else if (h < 240) [r, g, b] = [0, x, c];
    else if (h < 300) [r, g, b] = [x, 0, c];
    else [r, g, b] = [c, 0, x];
    const toHex = (v) =>
        Math.round((v + m) * 255)
            .toString(16)
            .padStart(2, '0');
    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

export function isValidHex(hex) {
    return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hex);
}

/** Derive a full 50–950 Tailwind-style shade ramp from a single base hex. */
export function generateShades(hex) {
    const { h, s } = hexToHsl(hex);
    const sat = Math.min(Math.max(s, 0.25), 0.78);
    const shades = {};
    for (const shade of SHADES) {
        shades[shade] = hslToHex(h, sat, SHADE_LIGHTNESS[shade] / 100);
    }
    return shades;
}

function setShadeVars(shades) {
    const root = document.documentElement;
    for (const shade of SHADES) root.style.setProperty(`--color-primary-${shade}`, shades[shade]);
}

export function applyTheme(hex) {
    if (!isValidHex(hex)) return;
    setShadeVars(generateShades(hex));
    localStorage.setItem('erp_theme_color', hex);
}

export function loadStoredTheme() {
    const saved = localStorage.getItem('erp_theme_color');
    if (saved && isValidHex(saved)) applyTheme(saved);
    return saved && isValidHex(saved) ? saved : DEFAULT_THEME_COLOR;
}

export function resetTheme() {
    localStorage.removeItem('erp_theme_color');
    const root = document.documentElement;
    for (const shade of SHADES) root.style.removeProperty(`--color-primary-${shade}`);
    return DEFAULT_THEME_COLOR;
}
