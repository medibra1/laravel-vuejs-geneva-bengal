<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import type { Gallery } from '@/types/models';

interface LitterParent {
    id: number;
    slug: string;
    name: string;
    color: string | null;
    photo_url: string | null;
}

interface PublicLitter {
    id: number;
    expected_date: string | null;
    notes: string | null;
    sire: LitterParent | null;
    dam: LitterParent | null;
}

defineProps<{
    litters: PublicLitter[];
    heroSlides: Gallery[];
}>();

const { t, locale } = useI18n();

function formatDate(date: string | null): string {
    if (!date) return t('litters.date_tbc');

    return new Intl.DateTimeFormat(locale.value === 'fr' ? 'fr-CH' : 'en-CH', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(date));
}
</script>

<template>
    <Head :title="$t('litters.meta_title')">
        <meta
            head-key="description"
            name="description"
            :content="$t('litters.meta_description')"
        />
    </Head>

    <PublicLayout>
        <PageBanner :script="$t('litters.banner_script')" :subtitle="$t('litters.banner_subtitle')" :slides="heroSlides" />

        <section class="mx-auto max-w-5xl px-6 py-16 sm:py-24">
            <SectionHeading :script="$t('litters.heading_script')" :title="$t('litters.heading_title')" center />

            <div v-if="litters.length" class="mt-12 flex flex-col gap-6">
                <div
                    v-for="litter in litters"
                    :key="litter.id"
                    class="flex flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center"
                >
                    <div class="flex shrink-0 items-center justify-center gap-3">
                        <div v-for="parent in [litter.sire, litter.dam]" :key="parent?.id ?? Math.random()" class="text-center">
                            <div class="mx-auto h-20 w-20 overflow-hidden rounded-full bg-gray-100">
                                <img v-if="parent?.photo_url" :src="parent.photo_url" :alt="parent.name" class="h-full w-full object-cover" />
                                <div v-else class="flex h-full items-center justify-center text-xs text-gray-400">?</div>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-neutral-700">{{ parent?.name ?? $t('litters.parent_tbc') }}</p>
                        </div>
                    </div>

                    <div class="flex-1">
                        <p class="text-brand-green-contrast font-heading text-sm font-semibold tracking-wide uppercase">
                            {{ formatDate(litter.expected_date) }}
                        </p>
                        <p class="mt-1 text-neutral-700">
                            <span v-if="litter.sire || litter.dam">
                                {{ litter.sire?.name ?? $t('litters.sire_tbc') }} × {{ litter.dam?.name ?? $t('litters.dam_tbc') }}
                            </span>
                        </p>
                        <p v-if="litter.notes" class="mt-2 text-sm text-neutral-600">{{ litter.notes }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="mt-12 py-16 text-center">
                <h2 class="text-xl font-semibold text-neutral-900">{{ $t('litters.empty_heading') }}</h2>
                <p class="mt-2 text-neutral-600">{{ $t('litters.empty_body') }}</p>
            </div>
        </section>
    </PublicLayout>
</template>
