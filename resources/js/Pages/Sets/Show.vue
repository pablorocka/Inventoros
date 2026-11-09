<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';

const { hasPermission } = usePermissions();

const props = defineProps({
    set: Object,
});

const deleteSet = () => {
    if (confirm(`Are you sure you want to delete "${props.set.name}"?`)) {
        router.delete(route('sets.destroy', props.set.id));
    }
};
</script>

<template>
    <Head :title="set.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-semibold text-lg sm:text-xl text-gray-900 dark:text-gray-100 leading-tight truncate max-w-full">
                    {{ set.name }}
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
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Products -->
                        <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm sm:rounded-lg p-4 sm:p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Products</h3>

                            <div class="space-y-3">
                                <div
                                    v-for="item in set.items"
                                    :key="item.id"
                                    class="p-3 sm:p-4 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-border rounded-lg"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="font-medium text-gray-900 dark:text-gray-100 break-words min-w-0">{{ item.name }}</p>
                                        <span class="shrink-0 text-sm font-semibold text-gray-700 dark:text-gray-200">× {{ item.quantity }}</span>
                                    </div>
                                    <ul class="mt-2 space-y-1">
                                        <li
                                            v-for="product in item.products"
                                            :key="product.id"
                                            class="flex flex-wrap justify-between gap-x-3 text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            <span>{{ product.location_name }}: stock {{ product.stock }}</span>
                                            <span>${{ parseFloat(product.price).toFixed(2) }}</span>
                                        </li>
                                        <li v-if="!item.products.length" class="text-sm text-red-400">No longer available</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Instructions -->
                        <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm sm:rounded-lg p-4 sm:p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Instructions</h3>
                            <!-- Sanitized on save: App\Support\HtmlSanitizer -->
                            <div v-if="set.instructions" class="rich-text text-gray-700 dark:text-gray-200 break-words" v-html="set.instructions"></div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No instructions.</p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm sm:rounded-lg p-4 sm:p-6">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Sets available</p>
                            <p class="text-3xl font-bold text-primary-400">{{ set.available }}</p>
                            <p v-if="set.description" class="mt-4 text-sm text-gray-600 dark:text-gray-300 break-words">{{ set.description }}</p>
                        </div>

                        <div
                            v-if="hasPermission('edit_sets') || hasPermission('delete_sets')"
                            class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm sm:rounded-lg p-4 sm:p-6 flex flex-col gap-3"
                        >
                            <Link
                                v-if="hasPermission('edit_sets')"
                                :href="route('sets.edit', set.id)"
                                class="w-full px-4 py-3 bg-primary-400 text-white rounded-lg font-semibold text-center hover:bg-primary-500 transition"
                            >
                                Edit Set
                            </Link>
                            <button
                                v-if="hasPermission('delete_sets')"
                                type="button"
                                class="w-full px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg font-semibold hover:bg-red-100 dark:hover:bg-red-900/30 transition"
                                @click="deleteSet"
                            >
                                Delete Set
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
