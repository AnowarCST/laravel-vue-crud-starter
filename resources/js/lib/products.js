// Product options shared by the products page and the dashboard. They match
// the values the API accepts (see ProductIndexRequest and ProductRequest).

// Products with 1 to LOW_STOCK_THRESHOLD units are "low stock" (Product::LOW_STOCK_THRESHOLD).
export const LOW_STOCK_THRESHOLD = 5;

export const STATUSES = [
    { value: 'active', label: 'Active', badge: 'text-bg-success' },
    { value: 'draft', label: 'Draft', badge: 'text-bg-secondary' },
    { value: 'archived', label: 'Archived', badge: 'text-bg-dark' },
];

export const STOCK_LEVELS = [
    { value: 'in', label: 'In stock' },
    { value: 'low', label: `Low stock (1–${LOW_STOCK_THRESHOLD})` },
    { value: 'out', label: 'Out of stock' },
];

export const SORTS = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'name', label: 'Name (A–Z)' },
    { value: '-name', label: 'Name (Z–A)' },
    { value: 'price', label: 'Price (low to high)' },
    { value: '-price', label: 'Price (high to low)' },
    { value: 'stock', label: 'Stock (low to high)' },
    { value: '-stock', label: 'Stock (high to low)' },
];

export const DEFAULT_SORT = 'newest';

export function statusInfo(value) {
    return STATUSES.find((status) => status.value === value) || { value, label: value || '—', badge: 'text-bg-light' };
}

/** 'out', 'low' or 'in' for a stock count. */
export function stockLevel(stock) {
    const units = Number(stock) || 0;
    if (units <= 0) return 'out';
    return units <= LOW_STOCK_THRESHOLD ? 'low' : 'in';
}
