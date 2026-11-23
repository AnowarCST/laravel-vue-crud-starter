const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });
const currencyFormatter = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' });

function toDate(value) {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value) {
    const date = toDate(value);
    return date ? dateFormatter.format(date) : '—';
}

export function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '—';
    const number = Number(value);
    return Number.isNaN(number) ? String(value) : currencyFormatter.format(number);
}

export function truncate(text, length = 60) {
    if (!text) return '';
    return text.length > length ? `${text.slice(0, length)}…` : text;
}

/**
 * Same-origin URL for a product photo (CSP img-src 'self').
 * - uploads/<random>.<ext>  -> /storage/uploads/... (public disk, via upload API)
 * - images/...              -> /images/...           (legacy seeded demo images)
 * Anything else (absolute/external URLs, traversal) is not rendered.
 */
export function storageUrl(path) {
    if (!path || typeof path !== 'string' || path.includes('..')) return null;
    if (/^uploads\/[A-Za-z0-9]+\.(jpe?g|png|webp)$/i.test(path)) return `/storage/${path}`;
    if (/^images\/[\w./-]+$/.test(path)) return `/${path}`;
    return null;
}
