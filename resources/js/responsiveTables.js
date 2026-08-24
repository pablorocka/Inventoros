/**
 * Responsive tables.
 *
 * On phones, any <table> inside an element carrying the `.responsive-table`
 * class is collapsed into stacked "label: value" rows by app.css. The labels
 * come from `data-label` attributes, which this module copies from the table
 * header at runtime — so existing tables become mobile-friendly without
 * touching their markup.
 *
 * Cells that have no matching header (checkbox / action columns) and cells
 * that span several columns (empty states) are deliberately left unlabelled.
 */

const clean = (text) => text.replace(/\s+/g, ' ').trim();

function labelTable(table) {
    const headerRow = table.querySelector('thead tr:last-of-type');
    if (!headerRow) return;

    const headers = Array.from(headerRow.children).map((th) => clean(th.textContent));

    table.querySelectorAll('tbody tr').forEach((row) => {
        let column = 0;

        Array.from(row.children).forEach((cell) => {
            const span = cell.colSpan || 1;
            const label = span > 1 ? '' : headers[column] || '';

            if (label) {
                cell.setAttribute('data-label', label);
            } else {
                cell.removeAttribute('data-label');
            }

            column += span;
        });
    });
}

export function labelResponsiveTables(root = document) {
    if (!root || typeof root.querySelectorAll !== 'function') return;
    root.querySelectorAll('.responsive-table table').forEach(labelTable);
}

let queued = false;

function schedule() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => {
        queued = false;
        labelResponsiveTables();
    });
}

export function initResponsiveTables() {
    if (typeof document === 'undefined') return;

    schedule();

    // Inertia swaps the page body without a full reload, and Vue re-renders
    // rows on filter/pagination changes, so watch for new nodes.
    // Attributes are not observed, which keeps our own writes from looping.
    const observer = new MutationObserver(schedule);
    observer.observe(document.body, { childList: true, subtree: true });
}
