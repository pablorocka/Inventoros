<script setup>
import { ref } from 'vue';

defineProps({
    activeCount: { type: Number, default: 0 },
    label: { type: String, default: 'Filters' },
});

const open = ref(false);
const toggle = () => (open.value = !open.value);
</script>

<template>
    <div>
        <button
            type="button"
            @click="toggle"
            class="md:hidden w-full flex items-center justify-between gap-2 px-4 py-3 rounded-lg bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border text-sm font-semibold text-gray-700 dark:text-gray-200"
            :aria-expanded="open"
        >
            <span class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                {{ label }}
                <span
                    v-if="activeCount > 0"
                    class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-primary-400 text-white text-[11px] font-bold"
                >{{ activeCount }}</span>
            </span>
            <svg
                class="w-5 h-5 transition-transform duration-200"
                :class="{ 'rotate-180': open }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div :class="['md:block', open ? 'block mt-3' : 'hidden']">
            <slot />
        </div>
    </div>
</template>
