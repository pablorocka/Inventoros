<script setup>
const props = defineProps({
    modelValue: { type: Number, required: true },
    min: { type: Number, default: 1 },
    max: { type: Number, default: null },
    label: { type: String, default: 'Quantity' },
});

const emit = defineEmits(['update:modelValue']);

const clamp = (value) => {
    const number = Number.isNaN(value) ? props.min : value;
    const upper = props.max === null ? number : Math.min(number, Math.max(props.max, props.min));
    return Math.max(props.min, upper);
};

const update = (value) => emit('update:modelValue', clamp(value));

const onInput = (event) => {
    const value = clamp(parseInt(event.target.value, 10));
    event.target.value = value;
    emit('update:modelValue', value);
};
</script>

<template>
    <div class="inline-flex items-stretch rounded-md border border-gray-200 dark:border-dark-border overflow-hidden" role="group" :aria-label="label">
        <button
            type="button"
            class="w-11 h-11 flex items-center justify-center bg-white dark:bg-dark-card text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-bg/50 disabled:opacity-40 disabled:cursor-not-allowed transition"
            :disabled="modelValue <= min"
            :aria-label="`Decrease ${label}`"
            @click="update(modelValue - 1)"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
            </svg>
        </button>
        <input
            :value="modelValue"
            type="number"
            inputmode="numeric"
            :min="min"
            :max="max ?? undefined"
            :aria-label="label"
            class="w-14 h-11 border-0 border-x border-gray-200 dark:border-dark-border bg-white dark:bg-dark-card text-center text-gray-900 dark:text-gray-100 focus:ring-primary-400 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            @change="onInput"
        />
        <button
            type="button"
            class="w-11 h-11 flex items-center justify-center bg-white dark:bg-dark-card text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-bg/50 disabled:opacity-40 disabled:cursor-not-allowed transition"
            :disabled="max !== null && modelValue >= max"
            :aria-label="`Increase ${label}`"
            @click="update(modelValue + 1)"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>
    </div>
</template>
