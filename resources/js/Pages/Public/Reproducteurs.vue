<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import ResponsiveImage from '@/Components/ResponsiveImage.vue';
import type { PageProps } from '@/types';
import type { Cat, Gallery } from '@/types/models';

defineProps<{
    cats: Cat[];
    heroSlides: Gallery[];
}>();

const page = usePage<PageProps>();

function description(cat: Cat): string {
    return cat.description[page.props.locale as 'fr' | 'en'] ?? '';
}
</script>

<template>
    <Head :title="$t('breeders.meta_title')">
        <meta
            head-key="description"
            name="description"
            :content="$t('breeders.meta_description')"
        />
    </Head>

    <PublicLayout>
        <PageBanner :script="$t('breeders.banner_script')" :subtitle="$t('breeders.banner_subtitle')" :slides="heroSlides" />

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <SectionHeading :script="$t('breeders.heading_script')" :title="$t('breeders.heading_title')" center />

            <div v-if="cats.length" class="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="cat in cats"
                    :key="cat.id"
                    :href="route('cats.show', cat.slug)"
                    class="group block overflow-hidden rounded-2xl border border-gray-200 bg-white text-center shadow-sm transition hover:shadow-lg"
                >
                    <div class="aspect-square overflow-hidden bg-gray-100">
                        <ResponsiveImage
                            v-if="cat.photos.length"
                            :sm="cat.photos[0].sm_url"
                            :md="cat.photos[0].md_url"
                            :fallback="cat.photos[0].url"
                            :alt="cat.name"
                            sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                            class="h-full w-full object-cover transition group-hover:scale-105"
                        />
                        <div v-else class="flex h-full items-center justify-center text-gray-400">{{ $t('common.no_photo') }}</div>
                    </div>
                    <div class="p-5">
                        <h3 class="font-heading text-brand-gray text-base font-bold uppercase tracking-wide">
                            {{ cat.name }}
                        </h3>
                        <p class="text-brand-tan mt-1 text-sm">
                            Bengal {{ cat.color?.name }} {{ cat.sex === 'male' ? $t('common.male_lower') : $t('common.female_lower') }}
                        </p>
                        <p v-if="description(cat)" class="mt-3 line-clamp-3 text-sm text-neutral-600">
                            {{ description(cat) }}
                        </p>
                        <span class="text-brand-green mt-3 inline-block text-sm font-semibold uppercase tracking-wide">
                            {{ $t('common.learn_more') }} →
                        </span>
                    </div>
                </Link>
            </div>

            <div v-else class="mt-12 py-16 text-center">
                <h2 class="text-xl font-semibold text-neutral-900">{{ $t('breeders.empty_heading') }}</h2>
                <p class="mt-2 text-neutral-600">{{ $t('breeders.empty_body') }}</p>
            </div>
        </section>
    </PublicLayout>
</template>
