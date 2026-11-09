<script setup>
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, default: null },
    label: { type: String, default: 'Text' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({
            blockquote: false,
            code: false,
            codeBlock: false,
            heading: false,
            horizontalRule: false,
            strike: false,
        }),
    ],
    editorProps: {
        attributes: {
            ...(props.id ? { id: props.id } : {}),
            'aria-label': props.label,
            'aria-multiline': 'true',
            role: 'textbox',
            class: 'rich-text min-h-[10rem] px-3 py-2 text-gray-900 dark:text-gray-100 focus:outline-none',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML());
    },
});

watch(() => props.modelValue, (value) => {
    if (editor.value && value !== editor.value.getHTML() && !(value === '' && editor.value.isEmpty)) {
        editor.value.commands.setContent(value || '', false);
    }
});

const tools = [
    { name: 'bold', label: 'Bold', run: (chain) => chain.toggleBold(), glyph: 'B', class: 'font-bold' },
    { name: 'italic', label: 'Italic', run: (chain) => chain.toggleItalic(), glyph: 'I', class: 'italic font-serif' },
    { name: 'bulletList', label: 'Bulleted list', run: (chain) => chain.toggleBulletList(), icon: 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01' },
    { name: 'orderedList', label: 'Numbered list', run: (chain) => chain.toggleOrderedList(), icon: 'M10 6h10M10 12h10M10 18h10M4 5l1.5-1v5M3.5 14.5c.5-1 2.5-1 2.5.5 0 1-2.5 2-2.5 4H6' },
];
</script>

<template>
    <div class="rounded-md border border-gray-200 dark:border-dark-border bg-gray-50 dark:bg-dark-bg shadow-sm overflow-hidden focus-within:border-primary-400 focus-within:ring-1 focus-within:ring-primary-400">
        <div class="flex flex-wrap gap-1 p-1 border-b border-gray-200 dark:border-dark-border bg-white dark:bg-dark-card" role="toolbar" :aria-label="`${label} formatting`">
            <button
                v-for="tool in tools"
                :key="tool.name"
                type="button"
                class="w-11 h-11 flex items-center justify-center rounded-md text-base text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-bg/50 transition"
                :class="[tool.class, editor?.isActive(tool.name) ? 'bg-primary-400/20 text-primary-500 dark:text-primary-400' : '']"
                :title="tool.label"
                :aria-label="tool.label"
                :aria-pressed="editor?.isActive(tool.name) ? 'true' : 'false'"
                @mousedown.prevent
                @click="tool.run(editor.chain().focus()).run()"
            >
                <svg v-if="tool.icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tool.icon" />
                </svg>
                <span v-else>{{ tool.glyph }}</span>
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
