/**
 * Full-ERP visual templates. Selecting one restyles the whole ERP: every template has a fixed
 * palette in CSS (erp-theme-school-green.css / erp-theme-palettes.css, rendered by
 * erp-theme-system.css), so only a colour the user picks by hand overrides it.
 * legacyPrimary = the colour older versions saved for the template; it still counts as
 * "no custom colour".
 */

export const DEFAULT_UI_TEMPLATE = 'school-green';

export const UI_TEMPLATES = [
    {
        id: 'school-green',
        name: 'School Green',
        tagline: 'Clean white workspace with trustworthy green brand',
        dark: false,
        primary: '#15803D',
        fixedPalette: true,
        preview: {
            bg: '#F8FAFC',
            surface: '#FFFFFF',
            accent: '#15803D',
            accentSoft: '#16A34A',
            text: '#0F172A',
            muted: '#475569',
        },
    },
    {
        id: 'royal-navy-gold',
        name: 'Royal Navy + Gold',
        tagline: 'Clean white with soft navy and antique-gold accents',
        dark: false,
        primary: '#233D7B',
        legacyPrimary: '#0F1B3D',
        fixedPalette: true,
        preview: {
            bg: '#F5F6F9',
            surface: '#FFFFFF',
            accent: '#233D7B',
            accentSoft: '#A8894E',
            text: '#141B2D',
            muted: '#4A5468',
        },
    },
    {
        id: 'midnight-gold',
        name: 'Midnight Gold',
        tagline: 'Graphite and warm charcoal with muted champagne',
        dark: true,
        primary: '#866C3E',
        legacyPrimary: '#C6A75E',
        fixedPalette: true,
        preview: {
            bg: '#0F0F11',
            surface: '#17171A',
            accent: '#CDB689',
            accentSoft: '#E4D3AE',
            text: '#E8E4DC',
            muted: '#ABA59B',
        },
    },
    {
        id: 'harbor-navy',
        name: 'Harbor Navy',
        tagline: 'Deep blue-grey with soft slate-blue highlights',
        dark: true,
        primary: '#3F69B0',
        legacyPrimary: '#5B8DEF',
        fixedPalette: true,
        preview: {
            bg: '#0E141C',
            surface: '#141C27',
            accent: '#8FB3E8',
            accentSoft: '#B4CBEF',
            text: '#E2E8F0',
            muted: '#A3AFBF',
        },
    },
    {
        id: 'forest-ledger',
        name: 'Forest Ledger',
        tagline: 'Charcoal-green with forest surfaces and sage notes',
        dark: true,
        primary: '#3F7A5E',
        legacyPrimary: '#6FAF8E',
        fixedPalette: true,
        preview: {
            bg: '#0F1311',
            surface: '#161C19',
            accent: '#9DBFA8',
            accentSoft: '#BBD5C3',
            text: '#E3E8E4',
            muted: '#A6B2AA',
        },
    },
    {
        id: 'ivory-studio',
        name: 'Ivory Studio',
        tagline: 'Warm ivory, crisp white cards and deep ink type',
        dark: false,
        primary: '#2F3342',
        legacyPrimary: '#2F4A6E',
        fixedPalette: true,
        preview: {
            bg: '#F7F5F0',
            surface: '#FFFFFF',
            accent: '#2F3342',
            accentSoft: '#9A8670',
            text: '#1F2128',
            muted: '#555761',
        },
    },
    {
        id: 'terracotta-sand',
        name: 'Terracotta Sand',
        tagline: 'Soft sand with muted terracotta and brown type',
        dark: false,
        primary: '#9A5335',
        legacyPrimary: '#C1622D',
        fixedPalette: true,
        preview: {
            bg: '#F7F2EA',
            surface: '#FFFCF8',
            accent: '#9A5335',
            accentSoft: '#B8935A',
            text: '#2E241E',
            muted: '#64564B',
        },
    },
    {
        id: 'velvet-plum',
        name: 'Velvet Plum',
        tagline: 'Aubergine-charcoal with muted plum and rose gold',
        dark: true,
        primary: '#865877',
        legacyPrimary: '#C97B98',
        fixedPalette: true,
        preview: {
            bg: '#130F14',
            surface: '#1B151C',
            accent: '#D9A9A0',
            accentSoft: '#E3C6D8',
            text: '#EDE5EC',
            muted: '#B2A5B1',
        },
    },
];

export function getUiTemplate(id) {
    return UI_TEMPLATES.find((t) => t.id === id) || UI_TEMPLATES[0];
}

export function isValidUiTemplate(id) {
    return UI_TEMPLATES.some((t) => t.id === id);
}

/** True when a saved colour is just the template's own (current or older) primary, not a custom pick. */
export function isTemplateDefaultColor(tpl, hex) {
    const c = String(hex || '').toLowerCase();
    return !c || c === String(tpl.primary || '').toLowerCase() || c === String(tpl.legacyPrimary || '').toLowerCase();
}
