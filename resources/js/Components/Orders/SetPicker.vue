<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    sets: { type: Array, required: true },
    available: { type: Function, required: true },
    capacity: { type: Function, required: true },
});

const emit = defineEmits(['add']);

const search = ref('');

const results = computed(() => {
    const query = search.value.trim().toLowerCase();
    return props.sets.filter((set) => !query || set.name.toLowerCase().includes(query));
});
</script>

<template>
    <div>
        <input
            v-model="search"
            type="search"
            placeholder="Search set by name..."
            aria-label="Search set by name"
            class="block w-full rounded-md bg-white dark:bg-dark-card border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 placeholder-gray-500 shadow-sm focus:border-primary-400 focus:ring-primary-400"
        />

        <div class="mt-3 max-h-80 overflow-y-auto space-y-2">
            <div
                v-for="set in results"
                :key="set.id"
                class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-border rounded-lg"
            >
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 dark:text-gray-100 break-words">{{ set.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ set.items.length }} {{ set.items.length === 1 ? 'product' : 'products' }} | Available: {{ available(set) }}
                    </p>
                    <p v-if="available(set) > 0 && capacity(set) < 1" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        Stock is split between locations, so a full set can't be taken from one location per product.
                    </p>
                </div>
                <button
                    type="button"
                    class="shrink-0 min-h-[44px] px-4 py-2 bg-primary-400 text-white rounded-md hover:bg-primary-500 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="capacity(set) < 1"
                    :aria-label="`Add ${set.name}`"
                    @click="emit('add', set)"
                >
                    Add
                </button>
            </div>

            <p v-if="!results.length" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                No sets found
            </p>
        </div>
    </div>
</template>
