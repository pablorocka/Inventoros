<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const { hasPermission } = usePermissions();

const props = defineProps({
    sets: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');

const applySearch = () => {
    router.get(route('sets.index'), { search: search.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Sets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-semibold text-lg sm:text-xl text-gray-900 dark:text-gray-100 leading-tight truncate">
                    Sets
                </h2>
                <Link
                    v-if="hasPermission('create_sets')"
                    :href="route('sets.create')"
                    class="inline-flex items-center px-4 py-2 bg-primary-400 hover:bg-primary-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Set
                </Link>
            </div>
        </template>

        <div class="py-4 sm:py-12 bg-gray-50 dark:bg-dark-bg min-h-screen">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
                <form class="mb-6 flex gap-2" @submit.prevent="applySearch">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search sets..."
                        aria-label="Search sets"
                        class="block w-full rounded-md bg-white dark:bg-dark-card border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 placeholder-gray-500 shadow-sm focus:border-primary-400 focus:ring-primary-400"
                    />
                    <button
                        type="submit"
                        class="inline-flex items-center px-4 py-2 bg-primary-400 hover:bg-primary-500 rounded-md font-semibold text-xs text-white uppercase tracking-widest"
                    >
                        Search
                    </button>
                </form>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    <Link
                        v-for="set in sets.data"
                        :key="set.id"
                        :href="route('sets.show', set.id)"
                        class="block bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm rounded-lg p-4 sm:p-6 hover:shadow-lg transition-shadow"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 break-words min-w-0">{{ set.name }}</h3>
                            <span
                                class="shrink-0 px-2 py-1 text-xs font-semibold rounded-full"
                                :class="set.available > 0 ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300'"
                            >
                                {{ set.available }} available
                            </span>
                        </div>
                        <p v-if="set.description" class="mt-2 text-sm text-gray-500 dark:text-gray-400 break-words">{{ set.description }}</p>
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ set.items_count }} {{ set.items_count === 1 ? 'product' : 'products' }}
                        </p>
                    </Link>

                    <div
                        v-if="!sets.data.length"
                        class="col-span-full bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm rounded-lg p-12 text-center text-gray-500 dark:text-gray-400"
                    >
                        No sets found
                    </div>
                </div>

                <nav v-if="sets.last_page > 1" class="mt-6 flex flex-wrap justify-center gap-1" aria-label="Pagination">
                    <template v-for="link in sets.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-2 border rounded-md text-sm"
                            :class="link.active
                                ? 'bg-primary-400/20 border-primary-400 text-primary-400'
                                : 'bg-white dark:bg-dark-card border-gray-200 dark:border-dark-border text-gray-600 dark:text-gray-400'"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
