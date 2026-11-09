// Stock and location rules for sets on the order forms. No Vue here, so the
// rules can be read (and run) on their own.

const locationKey = (product) => product.location_id ?? 'none';

/**
 * Stock a form can use per product id: current stock plus what this order
 * already holds (only relevant when editing an order).
 */
export function buildBaseStock(products, sets, heldStock = {}) {
    const base = {};

    products.forEach((product) => { base[product.id] = product.stock; });
    sets.forEach((set) => set.items.forEach((item) => item.products.forEach((product) => {
        base[product.id] = product.stock;
    })));
    Object.entries(heldStock).forEach(([id, held]) => { base[id] = (base[id] ?? 0) + held; });

    return base;
}

/**
 * Stock left per product after everything on the form, optionally ignoring one
 * set line so it can be measured against the stock it is allowed to use.
 */
export function remainingStock(base, items, lines, setsById, skipLineKey = null) {
    const remaining = { ...base };
    const take = (id, quantity) => { remaining[id] = (remaining[id] ?? 0) - quantity; };

    items.forEach((item) => {
        if (item.product_id) take(item.product_id, Number(item.quantity) || 0);
    });

    lines.forEach((line) => {
        if (line.key === skipLineKey) return;

        if (line.existing) {
            line.components.forEach((c) => take(c.product_id, c.per_set * line.quantity));
            return;
        }

        setsById[line.product_set_id]?.items.forEach((item) => {
            const productId = line.selections[item.id];
            if (productId) take(productId, item.quantity * line.quantity);
        });
    });

    return remaining;
}

const fits = (remaining, productId, quantity) => (remaining[productId] ?? 0) >= quantity;

/**
 * Sets the combined stock of every location can build: the figure shown to the user.
 */
export function availableSets(set, remaining) {
    if (!set.items.length) return 0;

    return Math.min(...set.items.map((item) => {
        const total = item.products.reduce((sum, p) => sum + Math.max(remaining[p.id] ?? 0, 0), 0);
        return Math.floor(total / item.quantity);
    }));
}

/**
 * Sets one order line can hold. A line takes each product from a single location,
 * so this is what the best stocked instance of each product allows. Anything above
 * this has to go on another line with a different location.
 */
export function lineCapacity(set, remaining) {
    if (!set.items.length) return 0;

    return Math.min(...set.items.map((item) => {
        const best = item.products.reduce((max, p) => Math.max(max, remaining[p.id] ?? 0), 0);
        return Math.floor(best / item.quantity);
    }));
}

/**
 * Locations able to supply the whole line by themselves. Empty means "Mixed Locations".
 */
export function locationOptions(set, quantity, remaining) {
    const locations = new Map();

    set.items.forEach((item) => item.products.forEach((product) => {
        locations.set(locationKey(product), product.location_name);
    }));

    return [...locations.entries()]
        .filter(([key]) => set.items.every((item) => item.products.some((product) =>
            locationKey(product) === key && fits(remaining, product.id, item.quantity * quantity)
        )))
        .map(([id, name]) => ({ id, name }))
        .sort((a, b) => a.name.localeCompare(b.name));
}

/**
 * Instances of one product able to supply a mixed line by themselves.
 */
export function productOptions(item, quantity, remaining) {
    return item.products.filter((product) => fits(remaining, product.id, item.quantity * quantity));
}

/**
 * Bring a new set line back in line with current stock: drop choices that no
 * longer fit and take the only possible one. Returns the corrected fields.
 */
export function resolveLine(line, set, remaining) {
    const options = locationOptions(set, line.quantity, remaining);
    const selections = {};
    let location = line.location;

    if (options.length) {
        if (!options.some((option) => option.id === location)) {
            location = options.length === 1 ? options[0].id : '';
        }

        if (location !== '') {
            set.items.forEach((item) => {
                const product = item.products.find((p) =>
                    locationKey(p) === location && fits(remaining, p.id, item.quantity * line.quantity)
                );
                selections[item.id] = product.id;
            });
        }
    } else {
        location = 'mixed';

        set.items.forEach((item) => {
            const candidates = productOptions(item, line.quantity, remaining);
            const current = candidates.find((p) => p.id === line.selections[item.id]);

            if (current) {
                selections[item.id] = current.id;
            } else if (candidates.length === 1) {
                selections[item.id] = candidates[0].id;
            }
        });
    }

    return { location, selections, options, mixed: !options.length };
}

/**
 * Price of one set item on a line: the selected instance, or the first with stock until one is chosen.
 */
export function itemPrice(item, line, remaining) {
    const selected = item.products.find((p) => p.id === line.selections[item.id]);
    const fallback = item.products.find((p) => (remaining[p.id] ?? 0) > 0) ?? item.products[0];

    return (selected ?? fallback)?.price ?? 0;
}

/**
 * Total of a line, for one set multiplied by the set quantity.
 */
export function lineTotal(line, set, remaining) {
    if (line.existing) {
        return line.components.reduce((sum, c) => sum + c.per_set * c.unit_price * line.quantity, 0);
    }

    return (set?.items ?? []).reduce((sum, item) =>
        sum + item.quantity * itemPrice(item, line, remaining) * line.quantity, 0);
}

/**
 * A line is complete once every product in it has a location.
 */
export function isLineComplete(line, set) {
    if (line.existing) return true;

    return Boolean(set) && set.items.every((item) => Boolean(line.selections[item.id]));
}

/**
 * Rebuild existing set lines from the items of a saved order.
 */
export function linesFromOrderItems(orderItems) {
    const lines = new Map();

    orderItems.filter((item) => item.metadata?.set).forEach((item) => {
        const info = item.metadata.set;

        if (!lines.has(info.line_id)) {
            lines.set(info.line_id, {
                key: info.line_id,
                existing: true,
                line_id: info.line_id,
                product_set_id: info.product_set_id,
                set_name: info.name,
                quantity: info.quantity,
                components: [],
            });
        }

        lines.get(info.line_id).components.push({
            product_id: item.product_id,
            name: item.product_name,
            sku: item.sku,
            location: item.metadata.location,
            per_set: info.per_set,
            unit_price: parseFloat(item.unit_price),
        });
    });

    return [...lines.values()];
}

/**
 * The fields of a set line the server needs.
 */
export function serializeLine(line) {
    if (line.existing) {
        return { line_id: line.line_id, quantity: line.quantity };
    }

    return {
        product_set_id: line.product_set_id,
        quantity: line.quantity,
        selections: Object.entries(line.selections).map(([setItemId, productId]) => ({
            set_item_id: Number(setItemId),
            product_id: productId,
        })),
    };
}
