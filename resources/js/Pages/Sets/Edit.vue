<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SetForm from '@/Components/Sets/SetForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    set: Object,
    productOptions: Array,
});

const form = useForm({
    name: props.set.name,
    description: props.set.description || '',
    instructions: props.set.instructions || '',
    items: props.set.items.map((item) => ({ ...item })),
});

const submit = () => {
    form.put(route('sets.update', props.set.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Edit Set" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-semibold text-lg sm:text-xl text-gray-900 dark:text-gray-100 leading-tight truncate max-w-full">
                    Edit Set
                </h2>
                <Link
                    :href="route('sets.index')"
                    class="inline-flex items-center px-4 py-2 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-md font-semibold text-xs text-gray-600 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-100 dark:hover:bg-dark-bg/50"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Sets
                </Link>
            </div>
        </template>

        <div class="py-4 sm:py-12 bg-gray-50 dark:bg-dark-bg min-h-screen">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
                <SetForm
                    :form="form"
                    :product-options="productOptions"
                    submit-label="Save Changes"
                    :cancel-href="route('sets.show', set.id)"
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
