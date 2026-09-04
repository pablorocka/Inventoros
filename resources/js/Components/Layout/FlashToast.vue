<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success');
let hideTimer = null;

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const show = (msg, kind) => {
    message.value = msg;
    type.value = kind;
    visible.value = true;
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => { visible.value = false; }, kind === 'error' ? 8000 : 5000);
};

// Error takes priority if a redirect somehow carries both.
watch(flashSuccess, (val) => { if (val) show(val, 'success'); });
watch(flashError, (val) => { if (val) show(val, 'error'); }, { immediate: true });

const dismiss = () => {
    visible.value = false;
    clearTimeout(hideTimer);
};
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            class="fixed top-4 right-4 z-[100] w-[calc(100%-2rem)] sm:w-auto sm:max-w-sm"
            role="alert"
        >
            <div
                :class="[
                    'flex items-start gap-3 rounded-lg shadow-lg border px-4 py-3',
                    type === 'error'
                        ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-700 dark:text-red-300'
                        : 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300'
                ]"
            >
                <svg v-if="type === 'error'" class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <svg v-else class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-medium flex-1">{{ message }}</p>
                <button @click="dismiss" class="flex-shrink-0 text-current opacity-60 hover:opacity-100" aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </Transition>
</template>
