<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const emit = defineEmits(['open-menu']);
const { hasPermission } = usePermissions();

const isActive = (patterns) => patterns.some((p) => route().current(p));

const items = computed(() => {
    const all = [
        {
            key: 'dashboard',
            label: 'Home',
            href: route('dashboard'),
            match: ['dashboard'],
            show: true,
            icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        },
        {
            key: 'products',
            label: 'Inventory',
            href: route('products.index'),
            match: ['products.*'],
            show: hasPermission('view_products'),
            icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        },
        {
            key: 'orders',
            label: 'Orders',
            href: route('orders.index'),
            match: ['orders.*'],
            show: hasPermission('view_orders'),
            icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        },
        {
            key: 'reports',
            label: 'Reports',
            href: route('reports.index'),
            match: ['reports.*'],
            show: hasPermission('view_reports'),
            icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        },
    ];
    return all.filter((i) => i.show).slice(0, 4);
});
</script>

<template>
    <nav
        class="mobile-bottom-nav lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-dark-card/95 backdrop-blur border-t border-gray-200 dark:border-dark-border"
        aria-label="Mobile navigation"
    >
        <div class="flex items-stretch justify-around">
            <Link
                v-for="item in items"
                :key="item.key"
                :href="item.href"
                class="flex flex-col items-center justify-center flex-1 min-w-0 gap-0.5 py-2 px-1 transition-colors"
                :class="isActive(item.match)
                    ? 'text-primary-500 dark:text-primary-400'
                    : 'text-gray-500 dark:text-gray-400 active:text-primary-500'"
            >
                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                </svg>
                <span class="text-[10px] font-medium leading-none truncate w-full text-center">{{ item.label }}</span>
            </Link>

            <button
                type="button"
                @click="emit('open-menu')"
                class="flex flex-col items-center justify-center flex-1 min-w-0 gap-0.5 py-2 px-1 text-gray-500 dark:text-gray-400 active:text-primary-500 transition-colors"
            >
                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <span class="text-[10px] font-medium leading-none">Menu</span>
            </button>
        </div>
    </nav>
</template>

<style scoped>
.mobile-bottom-nav {
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
