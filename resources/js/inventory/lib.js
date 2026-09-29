/*
 * Pure helpers for the storekeeper barcode-friendly item lookup (resources/js/inventory/scan.js keeps
 * the Alpine/DOM glue; this file stays dependency-free so it can be unit tested with node --test).
 */

/**
 * Resolve a scanned/typed code against the item list for one stock form. Matches the SKU first
 * (case-insensitive, exact -- a barcode scanner reproduces the SKU exactly), then falls back to an
 * exact match on the visible label ("Star Lager (bottle)") for someone typing the name by hand.
 * Never a partial/`includes` match: the caller only calls this once, on Enter, against the FULL
 * string, so a fast burst of scanner keystrokes lands as one lookup instead of a half-typed search.
 */
export function matchItem(items, code) {
    const needle = String(code ?? '').trim().toLowerCase();
    if (needle === '' || !Array.isArray(items)) return null;
    return items.find((i) => String(i.sku ?? '').toLowerCase() === needle)
        ?? items.find((i) => String(i.label ?? '').toLowerCase() === needle)
        ?? null;
}

/**
 * First row index in [0, rows) with no item chosen yet, or null when every visible row is already
 * taken (the caller then grows the row list and asks again).
 */
export function firstEmptyRow(rows, valueOf) {
    for (let i = 0; i < rows; i++) {
        const v = valueOf(i);
        if (v === null || v === '' || v === undefined) return i;
    }
    return null;
}
