<script setup>
import QuantityStepper from '@/Components/QuantityStepper.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    productOptions: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

const emit = defineEmits(['submit']);

const search = ref('');
const open = ref(false);
const highlighted = ref(0);
const combobox = ref(null);

// A saved item can point at any instance of the product, so every instance id leads to its option
const optionById = computed(() => Object.fromEntries(props.productOptions.flatMap((option) =>
    option.instance_ids.map((id) => [id, option])
)));

const results = computed(() => {
    const selected = new Set(props.form.items.map((item) => optionById.value[item.product_id]?.id));
    const query = search.value.trim().toLowerCase();

    return props.productOptions
        .filter((option) => !selected.has(option.id))
        .filter((option) => !query || option.name.toLowerCase().includes(query));
});

const addProduct = (option) => {
    props.form.items.push({ product_id: option.id, name: option.name, quantity: 1 });
    search.value = '';
    open.value = false;
    highlighted.value = 0;
};

const removeItem = (index) => {
    props.form.items.splice(index, 1);
};

const onSearchInput = () => {
    open.value = true;
    highlighted.value = 0;
};

const onKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;
        highlighted.value = Math.min(highlighted.value + 1, results.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
    } else if (event.key === 'Enter') {
        // Never submit the whole form from the search box
        event.preventDefault();
        if (open.value && results.value[highlighted.value]) {
            addProduct(results.value[highlighted.value]);
        }
    } else if (event.key === 'Escape') {
        open.value = false;
    }
};

const onClickOutside = (event) => {
    if (combobox.value && !combobox.value.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <form class="grid grid-cols-1 lg:grid-cols-3 gap-6" @submit.prevent="emit('submit')">
        <div class="lg:col-span-2 space-y-6">
            <!-- Set Information -->
            <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Set Information</h3>

                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-600 dark:text-gray-300">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            maxlength="255"
                            required
                            class="mt-1 block w-full rounded-md bg-gray-50 dark:bg-dark-bg border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 placeholder-gray-500 shadow-sm focus:border-primary-400 focus:ring-primary-400"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-400">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-600 dark:text-gray-300">
                            Short Description
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="2"
                            maxlength="500"
                            class="mt-1 block w-full rounded-md bg-gray-50 dark:bg-dark-bg border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 placeholder-gray-500 shadow-sm focus:border-primary-400 focus:ring-primary-400"
                        ></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-sm text-red-400">{{ form.errors.description }}</p>
                    </div>
                </div>
            </div>

            <!-- Products -->
            <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Products <span class="text-red-500">*</span>
                </h3>

                <div ref="combobox" class="relative mb-4">
                    <label for="set-product-search" class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">
                        Add a product
                    </label>
                    <input
                        id="set-product-search"
                        v-model="search"
                        type="text"
                        role="combobox"
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="set-product-results"
                        :aria-expanded="open ? 'true' : 'false'"
                        placeholder="Search product by name..."
                        class="block w-full rounded-md bg-gray-50 dark:bg-dark-bg border-gray-200 dark:border-dark-border text-gray-900 dark:text-gray-100 placeholder-gray-500 shadow-sm focus:border-primary-400 focus:ring-primary-400"
                        @focus="open = true"
                        @input="onSearchInput"
                        @keydown="onKeydown"
                    />

                    <ul
                        v-if="open"
                        id="set-product-results"
                        role="listbox"
                        class="absolute z-50 mt-1 w-full max-h-64 overflow-y-auto bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-md shadow-lg"
                    >
                        <li
                            v-for="(option, index) in results"
                            :key="option.id"
                            role="option"
                            :aria-selected="index === highlighted ? 'true' : 'false'"
                            class="px-3 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-dark-bg transition"
                            :class="{ 'bg-gray-100 dark:bg-dark-bg': index === highlighted }"
                            @click="addProduct(option)"
                        >
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 break-words">{{ option.name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ option.locations.join(' · ') || 'No location' }} | Total stock: {{ option.stock }}
                            </div>
                        </li>
                        <li v-if="!results.length" class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">
                            No products found
                        </li>
                    </ul>
                </div>

                <p v-if="form.errors.items" class="mb-3 text-sm text-red-400">{{ form.errors.items }}</p>

                <div v-if="form.items.length" class="space-y-3">
                    <div
                        v-for="(item, index) in form.items"
                        :key="item.product_id ?? `missing-${index}`"
                        class="flex flex-col gap-3 p-3 sm:p-4 bg-gray-50 dark:bg-dark-bg border border-gray-200 dark:border-dark-border rounded-lg sm:flex-row sm:items-center"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 dark:text-gray-100 break-words">{{ item.name }}</p>
                            <p v-if="optionById[item.product_id]" class="text-xs text-gray-500 dark:text-gray-400">
                                {{ optionById[item.product_id].locations.join(' · ') || 'No location' }} | Total stock: {{ optionById[item.product_id].stock }}
                            </p>
                            <p v-else class="text-xs text-red-400">No longer available. Remove it from the set.</p>
                            <p v-if="form.errors[`items.${index}.product_id`]" class="mt-1 text-xs text-red-400">
                                {{ form.errors[`items.${index}.product_id`] }}
                            </p>
                            <p v-if="form.errors[`items.${index}.quantity`]" class="mt-1 text-xs text-red-400">
                                {{ form.errors[`items.${index}.quantity`] }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <QuantityStepper v-model="item.quantity" :max="1000" :label="`Quantity of ${item.name}`" />
                            <button
                                type="button"
                                class="w-11 h-11 flex items-center justify-center text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition"
                                :aria-label="`Remove ${item.name}`"
                                @click="removeItem(index)"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                    No products yet. Search above to add the first one.
                </p>
            </div>

            <!-- Instructions -->
            <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Instructions</h3>
                <RichTextEditor v-model="form.instructions" id="instructions" label="Instructions" />
                <p v-if="form.errors.instructions" class="mt-1 text-sm text-red-400">{{ form.errors.instructions }}</p>
            </div>
        </div>

        <!-- Actions -->
        <div>
            <div class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-sm sm:rounded-lg p-4 sm:p-6 lg:sticky lg:top-6">
                <div class="flex flex-col gap-3">
                    <button
                        type="submit"
                        class="w-full px-4 py-3 bg-primary-400 text-white rounded-lg font-semibold hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 transition disabled:opacity-50"
                        :disabled="form.processing || !form.items.length"
                    >
                        {{ submitLabel }}
                    </button>
                    <Link
                        :href="cancelHref"
                        class="w-full px-4 py-3 bg-gray-100 dark:bg-dark-bg text-gray-700 dark:text-gray-300 rounded-lg font-semibold text-center hover:bg-gray-200 dark:hover:bg-dark-bg/80 border border-gray-200 dark:border-dark-border transition"
                    >
                        Cancel
                    </Link>
                </div>
            </div>
        </div>
    </form>
</template>
