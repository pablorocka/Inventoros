<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    payments: Array,
    summary: Object,
    byMethod: Array,
    users: Array,
    methods: Array,
    filters: Object,
});

const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const userId = ref(props.filters?.user_id || '');
const customer = ref(props.filters?.customer || '');
const method = ref(props.filters?.method || '');

const buildParams = () => {
    const params = {};
    if (dateFrom.value) params.date_from = dateFrom.value;
    if (dateTo.value) params.date_to = dateTo.value;
    if (userId.value) params.user_id = userId.value;
    if (customer.value) params.customer = customer.value;
    if (method.value) params.method = method.value;
    return params;
};

const applyFilters = () => {
    router.get(route('reports.payments'), buildParams(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    dateFrom.value = '';
    dateTo.value = '';
    userId.value = '';
    customer.value = '';
    method.value = '';
    applyFilters();
};

const exportExcel = () => {
    const qs = new URLSearchParams(buildParams()).toString();
    window.location.href = route('reports.payments.export') + (qs ? '?' + qs : '');
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
};

const methodLabel = (m) => {
    const labels = { cash: 'Cash', transfer: 'Transfer', card: 'Card', other: 'Other' };
    return labels[m] || m;
};

const getMethodBadge = (m) => {
    const classes = {
        cash: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
        transfer: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
        card: 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300',
    };
    return classes[m] || 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-300';
};
</script>

<template>
    <Head title="Order Payments Report" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-xl sm:text-2xl text-gray-900 dark:text-gray-100">Order Payments</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">All registered payments, for bank reconciliation</p>
                </div>
                <Link
                    :href="route('reports.index')"
                    class="px-4 py-2 bg-gray-200 dark:bg-dark-bg hover:bg-gray-300 dark:hover:bg-dark-bg/70 text-gray-700 dark:text-gray-300 font-medium rounded-lg transition"
                >
                    Back to Reports
                </Link>
            </div>
        </template>

        <div class="py-4 sm:py-12 bg-gray-50 dark:bg-dark-bg min-h-screen">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-lg shadow-sm p-6 mb-6">
                    <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Date From</label>
                            <input v-model="dateFrom" type="date" class="block w-full rounded-md bg-gray-50 dark:bg-dark-bg border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-400 focus:ring-primary-400" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Date To</label>
                            <input v-model="dateTo" type="date" class="block w-full rounded-md bg-gray-50 dark:bg-dark-bg border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-400 focus:ring-primary-400" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Registered By</label>
                            <select v-model="userId" class="block w-full rounded-md bg-gray-50 dark:bg-dark-bg border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-400 focus:ring-primary-400">
                                <option value="">All staff</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Method</label>
                            <select v-model="method" class="block w-full rounded-md bg-gray-50 dark:bg-dark-bg border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-400 focus:ring-primary-400">
                                <option value="">All</option>
                                <option v-for="m in methods" :key="m" :value="m">{{ methodLabel(m) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Customer</label>
                            <input v-model="customer" type="text" placeholder="Customer name" class="block w-full rounded-md bg-gray-50 dark:bg-dark-bg border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-400 focus:ring-primary-400" />
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white font-medium rounded-lg transition">Apply</button>
                            <button type="button" @click="clearFilters" class="px-4 py-2 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 font-medium rounded-lg transition">Clear</button>
                        </div>
                    </form>
                </div>

                <!-- Summary + Export -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Results</h3>
                    <button @click="exportExcel" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Export to Excel
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-lg shadow-sm p-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Total Payments</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ summary.total_payments }}</p>
                    </div>
                    <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-lg shadow-sm p-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Total Amount</p>
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ formatCurrency(summary.total_amount) }}</p>
                    </div>
                </div>

                <!-- By method breakdown -->
                <div v-if="byMethod.length > 0" class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">By Method</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div v-for="row in byMethod" :key="row.method" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-dark-bg rounded-lg">
                            <div>
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full capitalize" :class="getMethodBadge(row.method)">{{ methodLabel(row.method) }}</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ row.count }} payments</p>
                            </div>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(row.amount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-lg shadow-sm p-6">
                    <div class="overflow-x-auto responsive-table">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-dark-border">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order #</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Customer</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Method</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reference</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Registered By</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-dark-border">
                                <tr v-if="payments.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No payments for the selected filters.</td>
                                </tr>
                                <tr v-for="p in payments" :key="p.id">
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ p.paid_at }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ p.order_number || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ p.customer_name || '-' }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-green-600 dark:text-green-400">{{ formatCurrency(p.amount) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full capitalize" :class="getMethodBadge(p.method)">{{ methodLabel(p.method) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ p.reference || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ p.registered_by_name || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ p.notes || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
