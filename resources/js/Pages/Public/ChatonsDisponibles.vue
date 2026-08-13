<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import ResponsiveImage from '@/Components/ResponsiveImage.vue';
import type { PageProps } from '@/types';
import type { Cat, Gallery } from '@/types/models';

const props = defineProps<{
    cats: Cat[];
    activeColorSlug: string | null;
    heroSlides: Gallery[];
}>();

const page = usePage<PageProps>();
const activeColor = computed(() => page.props.colors.find((color) => color.slug === props.activeColorSlug));
</script>

<template>
    <Head :title="$t('catsList.meta_title')">
        <meta
            head-key="description"
            name="description"
            :content="$t('catsList.meta_description')"
        />
    </Head>

    <PublicLayout>
        <PageBanner :script="$t('catsList.banner_script')" :subtitle="$t('catsList.banner_subtitle')" :slides="heroSlides" />

        <section id="nos-chatons" class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <SectionHeading :script="$t('catsList.heading_script')" :title="$t('catsList.heading_title')" center />

            <div v-if="activeColor" class="mt-8 flex items-center justify-center gap-3 text-sm">
                <span class="text-brand-gray">
                    {{ $t('catsList.filtered_by_color') }} <strong>{{ activeColor.name }}</strong>
                </span>
                <Link :href="route('cats.index')" class="text-brand-green-contrast font-semibold underline hover:no-underline">
                    {{ $t('catsList.reset') }}
                </Link>
            </div>

            <div v-if="cats.length" class="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
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
                            sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw"
                            class="h-full w-full object-cover transition group-hover:scale-105"
                        />
                        <div v-else class="flex h-full items-center justify-center text-gray-400">
                            {{ $t('common.no_photo') }}
                        </div>
                    </div>
                    <div class="p-5">
                        <h3 class="font-heading text-brand-gray text-base font-bold uppercase tracking-wide">
                            {{ cat.name }} — {{ cat.status === 'disponible' ? $t('common.status_available') : $t('common.status_waiting') }}
                        </h3>
                        <p class="text-brand-tan-contrast mt-1 text-sm">
                            Bengal {{ cat.color?.name }} {{ cat.sex === 'male' ? $t('common.male_lower') : $t('common.female_lower') }}
                        </p>
                        <span class="text-brand-green-contrast mt-3 inline-block text-sm font-semibold uppercase tracking-wide">
                            {{ $t('common.read_more') }} →
                        </span>
                    </div>
                </Link>
            </div>

            <div v-else class="mt-12 py-16 text-center">
                <h2 class="text-xl font-semibold text-neutral-900">{{ $t('catsList.no_results_heading') }}</h2>
                <p class="mt-2 text-neutral-600">
                    <template v-if="activeColor">
                        {{ $t('catsList.no_results_color', { color: activeColor.name }) }}
                    </template>
                    <template v-else>
                        {{ $t('catsList.no_results_generic') }}
                    </template>
                </p>
            </div>
        </section>

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
