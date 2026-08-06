<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import type { Gallery } from '@/types/models';

const props = defineProps<{
    galleries: Gallery[];
}>();

const openIndex = ref<number | null>(null);

function openLightbox(index: number): void {
    openIndex.value = index;
}

function closeLightbox(): void {
    openIndex.value = null;
}

function prevImage(): void {
    if (openIndex.value === null) return;
    openIndex.value = (openIndex.value - 1 + props.galleries.length) % props.galleries.length;
}

function nextImage(): void {
    if (openIndex.value === null) return;
    openIndex.value = (openIndex.value + 1) % props.galleries.length;
}

function onKeydown(event: KeyboardEvent): void {
    if (openIndex.value === null) return;

    if (event.key === 'Escape') closeLightbox();
    if (event.key === 'ArrowLeft') prevImage();
    if (event.key === 'ArrowRight') nextImage();
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head title="Galerie photo">
        <meta
            head-key="description"
            name="description"
            content="Découvrez nos plus belles photos de chats et chatons Bengal en galerie."
        />
    </Head>

    <PublicLayout>
        <PageBanner script="Galerie photo" subtitle="Nos plus belles photos" />

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <SectionHeading script="Nos plus belles photos" title="Galerie mise à jour régulièrement" center />

            <div v-if="galleries.length" class="mt-12 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                <button
                    v-for="(item, index) in galleries"
                    :key="item.id"
                    type="button"
                    class="group aspect-square overflow-hidden rounded"
                    @click="openLightbox(index)"
                >
                    <img
                        v-if="item.image_url"
                        :src="item.image_url"
                        :alt="item.caption ?? ''"
                        class="h-full w-full object-cover transition group-hover:scale-105"
                    />
                </button>
            </div>

            <div v-else class="mt-12 py-16 text-center">
                <h2 class="text-xl font-semibold text-neutral-900">Aucune photo pour le moment</h2>
                <p class="mt-2 text-neutral-600">La galerie sera mise à jour prochainement.</p>
            </div>
        </section>

        <Transition name="lightbox-fade">
            <div
                v-if="openIndex !== null"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-6"
                @click.self="closeLightbox"
            >
                <button
                    type="button"
                    class="absolute top-6 right-6 flex h-10 w-10 items-center justify-center text-white/80 hover:text-white"
                    aria-label="Fermer"
                    @click="closeLightbox"
                >
                    <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="6" y1="6" x2="18" y2="18" />
                        <line x1="18" y1="6" x2="6" y2="18" />
                    </svg>
                </button>

                <button
                    v-if="galleries.length > 1"
                    type="button"
                    class="absolute left-2 flex h-12 w-12 items-center justify-center text-white/80 hover:text-white sm:left-6"
                    aria-label="Photo précédente"
                    @click="prevImage"
                >
                    <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <button
                    v-if="galleries.length > 1"
                    type="button"
                    class="absolute right-2 flex h-12 w-12 items-center justify-center text-white/80 hover:text-white sm:right-6"
                    aria-label="Photo suivante"
                    @click="nextImage"
                >
                    <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>

                <figure class="max-h-full max-w-3xl">
                    <img
                        v-if="galleries[openIndex]?.image_url"
                        :src="galleries[openIndex]!.image_url!"
                        :alt="galleries[openIndex]?.caption ?? ''"
                        class="max-h-[80vh] w-full rounded object-contain"
                    />
                    <figcaption v-if="galleries[openIndex]?.caption" class="mt-3 text-center text-sm text-white/70">
                        {{ galleries[openIndex]?.caption }}
                    </figcaption>
                </figure>
            </div>
        </Transition>

        <section class="bg-brand-canvas border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading script="Soyez les premiers au courant" title="Abonnez-vous à notre infolettre !" center />
                <div class="mt-6 flex justify-center">
                    <NewsletterForm class="w-full max-w-md" />
                </div>
            </div>
        </section>
    </PublicLayout>
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
