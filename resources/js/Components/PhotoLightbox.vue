<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';

interface LightboxPhoto {
    url: string;
    caption?: string | null;
}

const props = defineProps<{
    photos: LightboxPhoto[];
    /** Open index, or null when closed — pass via v-model. */
    modelValue: number | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

function close(): void {
    emit('update:modelValue', null);
}

function prev(): void {
    if (props.modelValue === null) return;
    emit('update:modelValue', (props.modelValue - 1 + props.photos.length) % props.photos.length);
}

function next(): void {
    if (props.modelValue === null) return;
    emit('update:modelValue', (props.modelValue + 1) % props.photos.length);
}

function onKeydown(event: KeyboardEvent): void {
    if (props.modelValue === null) return;

    if (event.key === 'Escape') close();
    if (event.key === 'ArrowLeft') prev();
    if (event.key === 'ArrowRight') next();
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Transition name="lightbox-fade">
        <div
            v-if="modelValue !== null"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-6"
            @click.self="close"
        >
            <button
                type="button"
                class="absolute top-6 right-6 flex h-10 w-10 items-center justify-center text-white/80 hover:text-white"
                aria-label="Fermer"
                @click="close"
            >
                <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="6" y1="6" x2="18" y2="18" />
                    <line x1="18" y1="6" x2="6" y2="18" />
                </svg>
            </button>

            <button
                v-if="photos.length > 1"
                type="button"
                class="absolute left-2 flex h-12 w-12 items-center justify-center text-white/80 hover:text-white sm:left-6"
                aria-label="Photo précédente"
                @click="prev"
            >
                <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </button>
            <button
                v-if="photos.length > 1"
                type="button"
                class="absolute right-2 flex h-12 w-12 items-center justify-center text-white/80 hover:text-white sm:right-6"
                aria-label="Photo suivante"
                @click="next"
            >
                <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <polyline points="9 18 15 12 9 6" />
                </svg>
            </button>

            <figure class="max-h-full max-w-3xl">
                <img
                    v-if="photos[modelValue]"
                    :src="photos[modelValue]!.url"
                    :alt="photos[modelValue]?.caption ?? ''"
                    class="max-h-[80vh] w-full rounded object-contain"
                />
                <figcaption v-if="photos[modelValue]?.caption" class="mt-3 text-center text-sm text-white/70">
                    {{ photos[modelValue]?.caption }}
                </figcaption>
                <p v-if="photos.length > 1" class="mt-2 text-center text-xs tracking-wide text-white/50">
                    {{ modelValue + 1 }} / {{ photos.length }}
                </p>
            </figure>
        </div>
    </Transition>
</template>

<style scoped>
.lightbox-fade-enter-active,
.lightbox-fade-leave-active {
    transition: opacity 0.2s ease;
}
.lightbox-fade-enter-from,
.lightbox-fade-leave-to {
    opacity: 0;
}
</style>
