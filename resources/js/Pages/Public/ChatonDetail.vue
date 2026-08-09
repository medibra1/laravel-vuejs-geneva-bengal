<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import DepositForm from '@/Components/DepositForm.vue';
import PhotoLightbox from '@/Components/PhotoLightbox.vue';
import type { PageProps } from '@/types';
import type { Cat } from '@/types/models';

const props = defineProps<{
    cat: Cat;
    depositAmount: number;
}>();

const page = usePage<PageProps>();
const { t, locale } = useI18n();

const selectedPhotoIndex = ref(0);
const selectedPhoto = computed(() => props.cat.photos[selectedPhotoIndex.value] ?? props.cat.photos[0]);
const lightboxIndex = ref<number | null>(null);

function prevPhoto(): void {
    selectedPhotoIndex.value = (selectedPhotoIndex.value - 1 + props.cat.photos.length) % props.cat.photos.length;
}

function nextPhoto(): void {
    selectedPhotoIndex.value = (selectedPhotoIndex.value + 1) % props.cat.photos.length;
}

const depositAmountLabel = computed(() =>
    new Intl.NumberFormat(locale.value === 'fr' ? 'fr-CH' : 'en-CH', { style: 'currency', currency: 'CHF' }).format(props.depositAmount / 100),
);

const description = computed(() => props.cat.description[page.props.locale as 'fr' | 'en'] ?? '');

// Only "chaton" is for adoption — this route/resource are otherwise
// type-agnostic (also reached from Public/Reproducteurs.vue), so a "chat"
// or "reproducteur" here just skips the adoption-specific sections below
// rather than showing a status badge, deposit form or price that don't
// apply to a breeding cat.
const isKitten = computed(() => props.cat.type === 'chaton');

const metaTitle = computed(() =>
    isKitten.value
        ? t('catDetail.meta_title_kitten', { name: props.cat.name })
        : t('catDetail.meta_title_breeder', { name: props.cat.name }),
);

const speciesLabel = computed(() => t('catDetail.species_label', { color: props.cat.color?.name ?? '' }));

// Per-cat description, not a generic site-wide one — precisely the gap
// CLAUDE.md flags on the production site (same meta on every page, bad
// for indexing individual kitten listings).
const metaDescription = computed(() => {
    const plainText = description.value.replace(/\s+/g, ' ').trim();

    return plainText.length > 155 ? `${plainText.slice(0, 155)}…` : plainText;
});

function formatDate(date: string | null): string {
    if (!date) return t('common.not_available');

    return new Intl.DateTimeFormat(locale.value === 'fr' ? 'fr-CH' : 'en-CH', { day: 'numeric', month: 'long', year: 'numeric' }).format(
        new Date(date),
    );
}

function formatPrice(cents: number | null): string {
    if (!cents) return t('catDetail.price_on_request');

    return new Intl.NumberFormat(locale.value === 'fr' ? 'fr-CH' : 'en-CH', { style: 'currency', currency: 'CHF' }).format(cents / 100);
}
</script>

<template>
    <Head :title="metaTitle">
        <meta v-if="metaDescription" head-key="description" name="description" :content="metaDescription" />
    </Head>

    <PublicLayout>
        <PageBanner :script="isKitten ? $t('catDetail.banner_script_kitten') : $t('catDetail.banner_script_breeder')" :subtitle="cat.name" />

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <SectionHeading :script="isKitten ? $t('catDetail.heading_kitten') : $t('catDetail.heading_breeder')" />

            <div class="mt-12 grid grid-cols-1 gap-16 md:grid-cols-2">
                <div>
                    <div class="group relative aspect-square overflow-hidden rounded-2xl bg-gray-100">
                        <Transition name="photo-fade" mode="out-in">
                            <button
                                v-if="selectedPhoto"
                                :key="selectedPhoto.id"
                                type="button"
                                class="block h-full w-full cursor-zoom-in"
                                :aria-label="$t('catDetail.enlarge_photo')"
                                @click="lightboxIndex = selectedPhotoIndex"
                            >
                                <img :src="selectedPhoto.url" :alt="cat.name" class="h-full w-full object-cover" />
                            </button>
                        </Transition>
                        <div v-if="!selectedPhoto" class="flex h-full items-center justify-center text-gray-400">
                            {{ $t('common.no_photo') }}
                        </div>

                        <span
                            v-if="selectedPhoto"
                            class="pointer-events-none absolute top-3 right-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/80 text-brand-gray opacity-0 shadow transition group-hover:opacity-100"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <circle cx="11" cy="11" r="7" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                <line x1="11" y1="8" x2="11" y2="14" />
                                <line x1="8" y1="11" x2="14" y2="11" />
                            </svg>
                        </span>

                        <template v-if="cat.photos.length > 1">
                            <button
                                type="button"
                                class="absolute top-1/2 left-3 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/80 text-brand-gray opacity-0 shadow transition hover:bg-white group-hover:opacity-100"
                                :aria-label="$t('common.prev_photo')"
                                @click="prevPhoto"
                            >
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <polyline points="15 18 9 12 15 6" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="absolute top-1/2 right-3 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/80 text-brand-gray opacity-0 shadow transition hover:bg-white group-hover:opacity-100"
                                :aria-label="$t('common.next_photo')"
                                @click="nextPhoto"
                            >
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <polyline points="9 18 15 12 9 6" />
                                </svg>
                            </button>

                            <span class="pointer-events-none absolute bottom-3 right-3 rounded-full bg-black/60 px-2.5 py-1 text-xs font-medium text-white">
                                {{ selectedPhotoIndex + 1 }} / {{ cat.photos.length }}
                            </span>
                        </template>
                    </div>

                    <div v-if="cat.photos.length > 1" class="mt-3 flex gap-2">
                        <button
                            v-for="(photo, index) in cat.photos"
                            :key="photo.id"
                            type="button"
                            class="h-16 w-16 shrink-0 overflow-hidden rounded-lg ring-2 transition"
                            :class="index === selectedPhotoIndex ? 'ring-brand-green' : 'ring-transparent opacity-70 hover:opacity-100 hover:ring-gray-300'"
                            @click="selectedPhotoIndex = index"
                        >
                            <img :src="photo.url" :alt="`${cat.name} — photo ${index + 1}`" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <div>
                    <h3 class="font-heading text-brand-gray text-xl font-bold">
                        {{ isKitten ? $t('catDetail.label_kitten_name') : $t('catDetail.label_name') }} : {{ cat.name }}
                    </h3>
                    <p v-if="isKitten" class="text-brand-green mt-1 font-semibold uppercase tracking-wide">
                        {{ cat.status === 'disponible' ? $t('common.status_available') : $t('common.status_waiting') }}
                    </p>
                    <p class="text-brand-tan mt-1">{{ speciesLabel }}</p>

                    <p v-if="description" class="mt-4 text-neutral-600">{{ description }}</p>

                    <ul class="mt-6 space-y-2 text-sm text-neutral-700">
                        <li><strong>{{ $t('catDetail.label_sex') }}</strong> {{ cat.sex === 'male' ? $t('common.male') : $t('common.female') }}</li>
                        <li><strong>{{ $t('catDetail.label_birth_date') }}</strong> {{ formatDate(cat.birth_date) }}</li>
                        <li><strong>{{ $t('catDetail.label_eye_color') }}</strong> {{ cat.eye_color ?? $t('common.not_available') }}</li>
                        <li v-if="isKitten"><strong>{{ $t('catDetail.label_available_from') }}</strong> {{ formatDate(cat.available_at) }}</li>
                        <li><strong>{{ $t('catDetail.label_diet') }}</strong> {{ cat.diet ?? $t('common.not_available') }}</li>
                        <li v-if="isKitten"><strong>{{ $t('catDetail.label_litter_trained') }}</strong> {{ cat.litter_trained ? $t('common.yes') : $t('common.no') }}</li>
                    </ul>

                    <div v-if="isKitten" class="mt-8 flex flex-wrap items-start gap-4">
                        <Link :href="route('pages.contact', { chaton: cat.slug })" class="btn-outline-brand">
                            {{ $t('catDetail.adopt_me') }}
                        </Link>
                        <DepositForm v-if="cat.status === 'disponible'" :cat-id="cat.id" :amount-label="depositAmountLabel" />
                    </div>
                    <div v-else class="mt-8">
                        <Link :href="route('pages.contact')" class="btn-outline-brand">
                            {{ $t('catDetail.contact_about') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <PhotoLightbox v-model="lightboxIndex" :photos="cat.photos.map((photo) => ({ url: photo.url }))" />

        <section v-if="isKitten" class="bg-brand-canvas border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading :script="$t('catDetail.cta_script')" :title="$t('catDetail.cta_title')" center />
                <p class="mt-4 text-lg text-neutral-700">{{ $t('catDetail.price_label') }} <span class="font-semibold">{{ formatPrice(cat.price) }}</span></p>
                <Link :href="route('pages.contact', { chaton: cat.slug })" class="btn-outline-brand mt-6">
                    {{ $t('catDetail.cta_button', { name: cat.name }) }}
                </Link>
            </div>
        </section>

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading :script="$t('newsletter.section_script')" :title="$t('newsletter.section_title')" center />
                <div class="mt-6 flex justify-center">
                    <NewsletterForm class="w-full max-w-md" />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.photo-fade-enter-active,
.photo-fade-leave-active {
    transition: opacity 0.25s ease;
}
.photo-fade-enter-from,
.photo-fade-leave-to {
    opacity: 0;
}
</style>
