<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import PhotoLightbox from '@/Components/PhotoLightbox.vue';
import type { Gallery } from '@/types/models';

defineProps<{
    galleries: Gallery[];
}>();

const openIndex = ref<number | null>(null);
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
                    @click="openIndex = index"
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

        <PhotoLightbox
            v-model="openIndex"
            :photos="galleries.map((item) => ({ url: item.image_url ?? '', caption: item.caption }))"
        />

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
