<script setup>
import { ref } from 'vue';

const tabs = [
    { key: 'products', label: 'Products' },
    { key: 'sets', label: 'Sets' },
];

const active = ref('products');

const onKeydown = (event) => {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        active.value = active.value === 'products' ? 'sets' : 'products';
        event.currentTarget.querySelector(`[data-tab="${active.value}"]`)?.focus();
    }
};
</script>

<template>
    <div class="mb-6">
        <div class="grid grid-cols-2 border-b border-gray-200 dark:border-dark-border" role="tablist" aria-label="Add to order" @keydown="onKeydown">
            <button
                v-for="tab in tabs"
                :id="`order-tab-${tab.key}`"
                :key="tab.key"
                :data-tab="tab.key"
                type="button"
                role="tab"
                :aria-selected="active === tab.key ? 'true' : 'false'"
                :aria-controls="`order-panel-${tab.key}`"
                :tabindex="active === tab.key ? 0 : -1"
                class="-mb-px min-h-[44px] px-4 py-2 border-b-2 text-sm font-semibold transition"
                :class="active === tab.key
                    ? 'border-primary-400 text-primary-500 dark:text-primary-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                @click="active = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <div
            v-for="tab in tabs"
            v-show="active === tab.key"
            :id="`order-panel-${tab.key}`"
            :key="tab.key"
            role="tabpanel"
            :aria-labelledby="`order-tab-${tab.key}`"
            class="pt-4"
        >
            <slot :name="tab.key" />
        </div>
    </div>
</template>
