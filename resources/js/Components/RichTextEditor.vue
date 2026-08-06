<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import InputText from 'primevue/inputtext';
import InputError from '@/Components/InputError.vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        error?: string;
    }>(),
    { placeholder: '', error: undefined },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        // StarterKit bundles the Link extension itself (since tiptap 3) —
        // registering @tiptap/extension-link separately on top of it
        // triggered a "Duplicate extension names: ['link']" warning.
        StarterKit.configure({
            link: { openOnClick: false, autolink: false },
        }),
        // Drag-handle resizing is built into the extension itself (tiptap
        // 3) — width/height end up as real attributes on the <img>, which
        // config/purifier.php's 'cms' profile already allows through.
        Image.configure({
            resize: {
                enabled: true,
                directions: ['bottom-right', 'bottom-left', 'top-right', 'top-left'],
                minWidth: 60,
                alwaysPreserveAspectRatio: true,
            },
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    onUpdate: ({ editor: instance }) => {
        emit('update:modelValue', instance.getHTML());
    },
});

// The form can reset/reload the page's data (e.g. after a failed submit
// with server-transformed values) without the editor instance itself
// being recreated — keep it in sync, but only when the value actually
// diverged from what TipTap already holds, otherwise every keystroke
// would trigger a redundant setContent() and reset the cursor position.
watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && value !== editor.value.getHTML()) {
            editor.value.commands.setContent(value, { emitUpdate: false });
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

// For RichTextEditor.spec.ts only: driving real ProseMirror commands
// (e.g. selecting text before toggling a mark) needs the actual editor
// instance — there's no other way to reach it from outside the component.
defineExpose({ editor });

function toggleBold(): void {
    editor.value?.chain().focus().toggleBold().run();
}

function toggleItalic(): void {
    editor.value?.chain().focus().toggleItalic().run();
}

function toggleHeading(level: 2 | 3): void {
    editor.value?.chain().focus().toggleHeading({ level }).run();
}

function toggleBulletList(): void {
    editor.value?.chain().focus().toggleBulletList().run();
}

function toggleOrderedList(): void {
    editor.value?.chain().focus().toggleOrderedList().run();
}

const linkPopover = ref();
const linkUrl = ref('');

function openLinkPopover(event: Event): void {
    linkUrl.value = editor.value?.getAttributes('link').href ?? '';
    linkPopover.value?.toggle(event);
}

function applyLink(): void {
    if (linkUrl.value) {
        editor.value?.chain().focus().extendMarkRange('link').setLink({ href: linkUrl.value }).run();
    } else {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
    }
    linkPopover.value?.hide();
}

const imageInput = ref<HTMLInputElement>();
const uploadingImage = ref(false);
const uploadError = ref('');

function pickImage(): void {
    imageInput.value?.click();
}

function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function onImageSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;

    uploadingImage.value = true;
    uploadError.value = '';

    try {
        const body = new FormData();
        body.append('image', file);

        const response = await fetch(route('admin.media.upload'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': readXsrfToken(),
            },
            body,
        });

        if (!response.ok) {
            throw new Error(`Upload failed with status ${response.status}`);
        }

        const { url } = (await response.json()) as { url: string };
        editor.value?.chain().focus().setImage({ src: url }).run();
    } catch {
        uploadError.value = "L'image n'a pas pu être envoyée.";
    } finally {
        uploadingImage.value = false;
    }
}
</script>

<template>
    <div class="rounded-md border border-gray-300 dark:border-neutral-600">
        <div v-if="editor" class="flex flex-wrap gap-1 border-b border-gray-300 p-1 dark:border-neutral-600">
            <Button
                type="button"
                label="G"
                text
                size="small"
                class="font-bold"
                :severity="editor.isActive('bold') ? 'primary' : 'secondary'"
                aria-label="Gras"
                @click="toggleBold"
            />
            <Button
                type="button"
                label="I"
                text
                size="small"
                class="italic"
                :severity="editor.isActive('italic') ? 'primary' : 'secondary'"
                aria-label="Italique"
                @click="toggleItalic"
            />
            <Button
                type="button"
                label="H2"
                text
                size="small"
                :severity="editor.isActive('heading', { level: 2 }) ? 'primary' : 'secondary'"
                aria-label="Titre 2"
                @click="toggleHeading(2)"
            />
            <Button
                type="button"
                label="H3"
                text
                size="small"
                :severity="editor.isActive('heading', { level: 3 }) ? 'primary' : 'secondary'"
                aria-label="Titre 3"
                @click="toggleHeading(3)"
            />
            <Button
                type="button"
                icon="pi pi-list"
                text
                size="small"
                :severity="editor.isActive('bulletList') ? 'primary' : 'secondary'"
                aria-label="Liste à puces"
                @click="toggleBulletList"
            />
            <Button
                type="button"
                icon="pi pi-sort-numeric-down"
                text
                size="small"
                :severity="editor.isActive('orderedList') ? 'primary' : 'secondary'"
                aria-label="Liste numérotée"
                @click="toggleOrderedList"
            />
            <Button
                type="button"
                icon="pi pi-link"
                text
                size="small"
                :severity="editor.isActive('link') ? 'primary' : 'secondary'"
                aria-label="Lien"
                @click="openLinkPopover"
            />
            <Button
                type="button"
                icon="pi pi-image"
                text
                size="small"
                severity="secondary"
                :loading="uploadingImage"
                aria-label="Insérer une image"
                @click="pickImage"
            />
            <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="onImageSelected" />

            <Popover ref="linkPopover">
                <div class="flex items-center gap-2 p-1">
                    <InputText v-model="linkUrl" placeholder="https://…" class="w-64" @keydown.enter.prevent="applyLink" />
                    <Button type="button" label="Appliquer" size="small" @click="applyLink" />
                </div>
            </Popover>
        </div>

        <EditorContent
            class="max-w-none px-3 py-2 text-sm text-neutral-900 dark:text-white [&_.ProseMirror]:min-h-[10rem] [&_.ProseMirror]:outline-none [&_.ProseMirror_a]:text-emerald-600 [&_.ProseMirror_a]:underline [&_.ProseMirror_h2]:mt-3 [&_.ProseMirror_h2]:text-lg [&_.ProseMirror_h2]:font-semibold [&_.ProseMirror_h3]:mt-2 [&_.ProseMirror_h3]:text-base [&_.ProseMirror_h3]:font-semibold [&_.ProseMirror_img]:max-w-full [&_.ProseMirror_img]:rounded [&_.ProseMirror_ol]:list-decimal [&_.ProseMirror_ol]:pl-5 [&_.ProseMirror_p]:my-2 [&_.ProseMirror_ul]:list-disc [&_.ProseMirror_ul]:pl-5 [&_.ProseMirror_p.is-editor-empty:first-child::before]:pointer-events-none [&_.ProseMirror_p.is-editor-empty:first-child::before]:float-left [&_.ProseMirror_p.is-editor-empty:first-child::before]:h-0 [&_.ProseMirror_p.is-editor-empty:first-child::before]:text-neutral-400 [&_.ProseMirror_p.is-editor-empty:first-child::before]:content-[attr(data-placeholder)]"
            :editor="editor"
        />
    </div>

    <InputError :message="error || uploadError" />
</template>

<style scoped>
/* The resize handles are plain DOM nodes tiptap injects itself (not
   through Vue's template), so scoped styles need :deep() to reach them.
   The library only sets position:absolute inline — no size/color/cursor —
   so without this they're invisible 0×0 elements. */
:deep([data-resize-handle]) {
    width: 0.65rem;
    height: 0.65rem;
    border-radius: 9999px;
    background-color: var(--p-emerald-600, #059669);
    border: 2px solid white;
    opacity: 0;
    transition: opacity 100ms;
}

/* :active (not just wrapper :hover) keeps the handle visible for the
   whole drag even once the pointer moves past the wrapper's bounds while
   enlarging the image — :hover alone would fade it mid-drag. */
:deep([data-resize-wrapper]:hover [data-resize-handle]),
:deep([data-resize-handle]:active) {
    opacity: 1;
}

:deep([data-resize-handle='top-left']) {
    cursor: nwse-resize;
    transform: translate(-50%, -50%);
}

:deep([data-resize-handle='top-right']) {
    cursor: nesw-resize;
    transform: translate(50%, -50%);
}

:deep([data-resize-handle='bottom-left']) {
    cursor: nesw-resize;
    transform: translate(-50%, 50%);
}

:deep([data-resize-handle='bottom-right']) {
    cursor: nwse-resize;
    transform: translate(50%, 50%);
}
</style>
