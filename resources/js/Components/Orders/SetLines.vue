<script setup>
import QuantityStepper from '@/Components/QuantityStepper.vue';
import { itemPrice, lineTotal, productOptions } from '@/utils/orderSets';

defineProps({
    lines: { type: Array, required: true },
    state: { type: Function, required: true },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['remove', 'update-quantity']);

const money = (value) => `$${Number(value).toFixed(2)}`;

const updateLocation = (line, value) => {
    line.location = value === 'mixed' || value === 'none' || value === '' ? value : Number(value);
};
</script>

<template>
    <div v-if="lines.length" class="space-y-3">
        <div
            v-for="(line, index) in lines"
            :key="line.key"
            class="p-3 sm:p-4 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-border rounded-lg"
        >
            <template v-for="current in [state(line)]" :key="line.key">
                <!-- Set header: the only place to change or remove anything -->
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-500 dark:text-primary-400">Set</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100 break-words">{{ line.set_name }}</p>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <QuantityStepper
                            :model-value="line.quantity"
                            :max="current.max"
                            :label="`Quantity of ${line.set_name}`"
                            @update:model-value="emit('update-quantity', line, $event)"
                        />
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ money(lineTotal(line, current.set, current.stock)) }}</span>
                        <button
                            type="button"
                            class="w-11 h-11 flex items-center justify-center text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition"
                            :aria-label="`Remove ${line.set_name}`"
                            @click="emit('remove', line)"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <p v-if="errors[`sets.${index}.quantity`]" class="mt-1 text-xs text-red-400">{{ errors[`sets.${index}.quantity`] }}</p>

                <!-- Saved line: its products and locations are fixed -->
                <ul v-if="line.existing" class="mt-3 divide-y divide-gray-200 dark:divide-dark-border">
                    <li v-for="component in line.components" :key="component.product_id" class="flex flex-wrap items-baseline justify-between gap-x-3 py-2">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-900 dark:text-gray-100 break-words">{{ component.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ component.location || component.sku }} | {{ component.per_set }} per set</p>
                        </div>
                        <span class="text-sm text-gray-700 dark:text-gray-200">{{ money(component.unit_price) }}</span>
                    </li>
                </ul>

                <template v-else-if="current.set">
                    <div class="mt-3">
                        <label :for="`set-location-${line.key}`" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Location</label>
                        <select
                            :id="`set-location-${line.key}`"
                            :value="line.location"
                            class="block w-full md:max-w-xs rounded-md bg-white dark:bg-dark-card border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-400 focus:ring-primary-400"
                            :disabled="current.mixed"
                            @change="updateLocation(line, $event.target.value)"
                        >
                            <option v-if="current.mixed" value="mixed">Mixed Locations</option>
                            <template v-else>
                                <option v-if="current.options.length > 1" value="" disabled>Select a location</option>
                                <option v-for="option in current.options" :key="option.id" :value="option.id">{{ option.name }}</option>
                            </template>
                        </select>
                        <p v-if="line.location === ''" class="mt-1 text-xs text-amber-600 dark:text-amber-400">Select a location to complete this set.</p>
                    </div>

                    <ul class="mt-3 divide-y divide-gray-200 dark:divide-dark-border">
                        <li v-for="item in current.set.items" :key="item.id" class="py-2">
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-gray-100 break-words">{{ item.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ item.quantity }} per set</p>
                                </div>
                                <span class="shrink-0 text-sm text-gray-700 dark:text-gray-200">{{ money(itemPrice(item, line, current.stock)) }}</span>
                            </div>

                            <!-- Mixed sets choose a location for each product -->
                            <div v-if="current.mixed" class="mt-2">
                                <label :for="`set-item-${line.key}-${item.id}`" class="sr-only">Location for {{ item.name }}</label>
                                <select
                                    :id="`set-item-${line.key}-${item.id}`"
                                    v-model="line.selections[item.id]"
                                    class="block w-full md:max-w-xs rounded-md bg-white dark:bg-dark-card border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-400 focus:ring-primary-400"
                                >
                                    <option :value="undefined" disabled>Select a location</option>
                                    <option
                                        v-for="product in productOptions(item, line.quantity, current.stock)"
                                        :key="product.id"
                                        :value="product.id"
                                    >
                                        {{ product.location_name }} (stock: {{ current.stock[product.id] }})
                                    </option>
                                </select>
                            </div>
                        </li>
                    </ul>

                    <p v-if="line.quantity >= current.max && current.mixed" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        To order more of this set, add it again with other locations.
                    </p>
                </template>
            </template>
        </div>
    </div>
</template>
