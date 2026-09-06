/** INR formatting — use ASCII-safe helpers so currency never depends on source-file encoding. */

export function money(value, digits = 0) {
    const n = Number(value);
    if (!Number.isFinite(n)) return '0';
    return n.toLocaleString('en-IN', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
}

/** Prefixed with the Indian rupee sign (U+20B9). */
export function inr(value, digits = 0) {
    return `\u20B9${money(value, digits)}`;
}

export function dash() {
    return '\u2014';
}

export function enDash() {
    return '\u2013';
}

export function middot() {
    return '\u00B7';
}

export function ellipsis() {
    return '\u2026';
}
