/** Six full-ERP visual templates. Selecting one restyles the shell + primary palette. */

export const DEFAULT_UI_TEMPLATE = 'midnight-gold';

export const UI_TEMPLATES = [
    {
        id: 'midnight-gold',
        name: 'Midnight Gold',
        tagline: 'Classy charcoal with champagne accents',
        dark: true,
        primary: '#C6A75E',
        preview: {
            bg: '#07080c',
            surface: '#15161c',
            accent: '#c6a75e',
            accentSoft: '#e2c98a',
            text: '#f3efe6',
            muted: '#9a958c',
        },
    },
    {
        id: 'harbor-navy',
        name: 'Harbor Navy',
        tagline: 'Deep navy with cool steel highlights',
        dark: true,
        primary: '#5B8DEF',
        preview: {
            bg: '#06101c',
            surface: '#0d1a2b',
            accent: '#5b8def',
            accentSoft: '#9bb8f5',
            text: '#e8eef8',
            muted: '#8a9bb0',
        },
    },
    {
        id: 'forest-ledger',
        name: 'Forest Ledger',
        tagline: 'Ink green with sage and brass notes',
        dark: true,
        primary: '#6FAF8E',
        preview: {
            bg: '#07110c',
            surface: '#0f1a14',
            accent: '#6faf8e',
            accentSoft: '#a7d4bb',
            text: '#e9f2ec',
            muted: '#8fa396',
        },
    },
    {
        id: 'ivory-studio',
        name: 'Ivory Studio',
        tagline: 'Bright workspace with deep ink contrast',
        dark: false,
        primary: '#2F4A6E',
        preview: {
            bg: '#f2f4f7',
            surface: '#ffffff',
            accent: '#2f4a6e',
            accentSoft: '#1c314d',
            text: '#152033',
            muted: '#6b7585',
        },
    },
    {
        id: 'terracotta-sand',
        name: 'Terracotta Sand',
        tagline: 'Warm sandstone with terracotta accents',
        dark: false,
        primary: '#C1622D',
        preview: {
            bg: '#faf6f0',
            surface: '#ffffff',
            accent: '#c1622d',
            accentSoft: '#e08a56',
            text: '#2b2320',
            muted: '#8a7d6e',
        },
    },
    {
        id: 'velvet-plum',
        name: 'Velvet Plum',
        tagline: 'Deep plum with rose-gold highlights',
        dark: true,
        primary: '#C97B98',
        preview: {
            bg: '#120a14',
            surface: '#1d1220',
            accent: '#c97b98',
            accentSoft: '#e3a8c0',
            text: '#f3e9ef',
            muted: '#9c8a96',
        },
    },
];

export function getUiTemplate(id) {
    return UI_TEMPLATES.find((t) => t.id === id) || UI_TEMPLATES[0];
}

export function isValidUiTemplate(id) {
    return UI_TEMPLATES.some((t) => t.id === id);
}
