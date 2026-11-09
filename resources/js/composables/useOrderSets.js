import { computed, watch } from 'vue';
import {
    availableSets,
    buildBaseStock,
    isLineComplete,
    lineCapacity,
    lineTotal,
    remainingStock,
    resolveLine,
    serializeLine,
} from '@/utils/orderSets';

/**
 * Shared set handling for the create and edit order forms.
 * form.sets holds the lines; form.items the individually added products.
 */
export function useOrderSets(form, props, heldStock = {}) {
    const setsById = computed(() => Object.fromEntries(props.sets.map((set) => [set.id, set])));
    const base = computed(() => buildBaseStock(props.products, props.sets, heldStock));

    const remaining = (skipLineKey = null) =>
        remainingStock(base.value, form.items, form.sets, setsById.value, skipLineKey);

    const available = (set) => availableSets(set, remaining());
    const capacity = (set) => lineCapacity(set, remaining());

    const addSet = (set) => {
        form.sets.push({
            key: `new-${Date.now()}-${form.sets.length}`,
            existing: false,
            product_set_id: set.id,
            set_name: set.name,
            quantity: 1,
            location: '',
            selections: {},
        });
    };

    const removeLine = (line) => {
        form.sets.splice(form.sets.indexOf(line), 1);
    };

    // The most sets a line can hold given the rest of the form
    const lineMax = (line) => {
        const stock = remaining(line.key);

        if (line.existing) {
            return Math.min(...line.components.map((c) => Math.floor((stock[c.product_id] ?? 0) / c.per_set)));
        }

        const set = setsById.value[line.product_set_id];
        return set ? lineCapacity(set, stock) : 0;
    };

    const setLineQuantity = (line, quantity) => {
        line.quantity = Math.max(1, Math.min(quantity, Math.max(lineMax(line), 1)));
    };

    const lineState = (line) => {
        const set = setsById.value[line.product_set_id];
        const stock = remaining(line.key);

        return {
            set,
            stock,
            max: lineMax(line),
            ...(line.existing || !set ? { options: [], mixed: false } : resolveLine(line, set, stock)),
        };
    };

    // Stock used by one line changes the choices of every other line
    const syncLines = () => {
        form.sets.filter((line) => !line.existing).forEach((line) => {
            const set = setsById.value[line.product_set_id];
            if (!set) return;

            const { location, selections } = resolveLine(line, set, remaining(line.key));

            if (location !== line.location) line.location = location;
            if (JSON.stringify(selections) !== JSON.stringify(line.selections)) line.selections = selections;
        });
    };

    watch(() => [form.items, form.sets], syncLines, { deep: true, immediate: true });

    const setsSubtotal = computed(() => form.sets.reduce((sum, line) =>
        sum + lineTotal(line, setsById.value[line.product_set_id], remaining(line.key)), 0));

    const incompleteLines = computed(() => form.sets.some((line) =>
        !isLineComplete(line, setsById.value[line.product_set_id])));

    const withSerializedSets = (data) => ({ ...data, sets: data.sets.map(serializeLine) });

    return {
        available,
        capacity,
        addSet,
        removeLine,
        lineState,
        setLineQuantity,
        setsSubtotal,
        incompleteLines,
        withSerializedSets,
    };
}
