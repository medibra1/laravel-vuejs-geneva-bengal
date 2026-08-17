<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import PhotoLightbox from '@/Components/PhotoLightbox.vue';
import ResponsiveImage from '@/Components/ResponsiveImage.vue';
import type { Gallery } from '@/types/models';

defineProps<{
    galleries: Gallery[];
    heroSlides: Gallery[];
}>();

const openIndex = ref<number | null>(null);
</script>

<template>
    <Head :title="$t('gallery.meta_title')">
        <meta
            head-key="description"
            name="description"
            :content="$t('gallery.meta_description')"
        />
    </Head>

    <PublicLayout>
        <PageBanner :script="$t('gallery.banner_script')" :subtitle="$t('gallery.banner_subtitle')" :slides="heroSlides" />

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <SectionHeading :script="$t('gallery.heading_script')" :title="$t('gallery.heading_title')" center />

            <div v-if="galleries.length" class="mt-12 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                <button
                    v-for="(item, index) in galleries"
                    :key="item.id"
                    type="button"
                    class="group aspect-square overflow-hidden rounded"
                    :aria-label="item.caption || $t('gallery.open_photo', { n: index + 1 })"
                    @click="openIndex = index"
                >
                    <ResponsiveImage
                        v-if="item.image_url"
                        :sm="item.image_sm_url"
                        :md="item.image_md_url"
                        :fallback="item.image_url"
                        :alt="item.caption ?? ''"
                        sizes="(min-width: 768px) 25vw, (min-width: 640px) 33vw, 50vw"
                        class="h-full w-full object-cover transition group-hover:scale-105"
                        :eager="index < 4"
                    />
                </button>
            </div>

            <div v-else class="mt-12 py-16 text-center">
                <h2 class="text-xl font-semibold text-neutral-900">{{ $t('gallery.empty_heading') }}</h2>
                <p class="mt-2 text-neutral-600">{{ $t('gallery.empty_body') }}</p>
            </div>
        </section>

        <PhotoLightbox
            v-model="openIndex"
            :photos="galleries.map((item) => ({ url: item.image_lg_url ?? item.image_url ?? '', caption: item.caption }))"
        />

        <section class="bg-brand-canvas border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading :script="$t('newsletter.section_script')" :title="$t('newsletter.section_title')" center />
                <div class="mt-6 flex justify-center">
                    <NewsletterForm class="w-full max-w-md" />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
