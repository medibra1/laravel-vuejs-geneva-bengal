<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import DepositForm from '@/Components/DepositForm.vue';
import type { PageProps } from '@/types';
import type { Cat } from '@/types/models';

const props = defineProps<{
    cat: Cat;
    depositAmount: number;
}>();

const page = usePage<PageProps>();

const selectedPhotoIndex = ref(0);
const selectedPhoto = computed(() => props.cat.photos[selectedPhotoIndex.value] ?? props.cat.photos[0]);

const depositAmountLabel = computed(() =>
    new Intl.NumberFormat('fr-CH', { style: 'currency', currency: 'CHF' }).format(props.depositAmount / 100),
);

const description = computed(() => props.cat.description[page.props.locale as 'fr' | 'en'] ?? '');

// Only "chaton" is for adoption — this route/resource are otherwise
// type-agnostic (also reached from Public/Reproducteurs.vue), so a "chat"
// or "reproducteur" here just skips the adoption-specific sections below
// rather than showing a status badge, deposit form or price that don't
// apply to a breeding cat.
const isKitten = computed(() => props.cat.type === 'chaton');

// Per-cat description, not a generic site-wide one — precisely the gap
// CLAUDE.md flags on the production site (same meta on every page, bad
// for indexing individual kitten listings).
const metaDescription = computed(() => {
    const plainText = description.value.replace(/\s+/g, ' ').trim();

    return plainText.length > 155 ? `${plainText.slice(0, 155)}…` : plainText;
});

function formatDate(date: string | null): string {
    if (!date) return '—';

    return new Intl.DateTimeFormat('fr-CH', { day: 'numeric', month: 'long', year: 'numeric' }).format(
        new Date(date),
    );
}

function formatPrice(cents: number | null): string {
    if (!cents) return 'Nous contacter';

    return new Intl.NumberFormat('fr-CH', { style: 'currency', currency: 'CHF' }).format(cents / 100);
}
</script>

<template>
    <Head :title="isKitten ? `${cat.name} — Chaton Bengal disponible` : `${cat.name} — Chat Bengal reproducteur`">
        <meta v-if="metaDescription" head-key="description" name="description" :content="metaDescription" />
    </Head>

    <PublicLayout>
        <PageBanner :script="isKitten ? 'Chaton Bengal' : 'Chat Bengal'" :subtitle="cat.name" />

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <SectionHeading :script="isKitten ? 'À propos de ce chaton bengal à vendre' : 'À propos de ce chat Bengal reproducteur'" />

            <div class="mt-12 grid grid-cols-1 gap-16 md:grid-cols-2">
                <div>
                    <div class="aspect-square overflow-hidden rounded-2xl bg-gray-100">
                        <img v-if="selectedPhoto" :src="selectedPhoto.url" :alt="cat.name"
                            class="h-full w-full object-cover" />
                        <div v-else class="flex h-full items-center justify-center text-gray-400">
                            Pas de photo
                        </div>
                    </div>

                    <div v-if="cat.photos.length > 1" class="mt-3 flex gap-2">
                        <button
                            v-for="(photo, index) in cat.photos"
                            :key="photo.id"
                            type="button"
                            class="h-16 w-16 overflow-hidden rounded-md ring-2 transition"
                            :class="index === selectedPhotoIndex ? 'ring-brand-green' : 'ring-transparent hover:ring-gray-300'"
                            @click="selectedPhotoIndex = index"
                        >
                            <img :src="photo.url" :alt="`${cat.name} — photo ${index + 1}`" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <div>
                    <h3 class="font-heading text-brand-gray text-xl font-bold">
                        {{ isKitten ? 'Nom du chaton' : 'Nom' }} : {{ cat.name }}
                    </h3>
                    <p v-if="isKitten" class="text-brand-green mt-1 font-semibold uppercase tracking-wide">
                        {{ cat.status === 'disponible' ? 'Disponible' : 'En attente' }}
                    </p>
                    <p class="text-brand-tan mt-1">{{ cat.color?.name }} Bengal cat</p>

                    <p v-if="description" class="mt-4 text-neutral-600">{{ description }}</p>

                    <ul class="mt-6 space-y-2 text-sm text-neutral-700">
                        <li><strong>Sexe :</strong> {{ cat.sex === 'male' ? 'Mâle' : 'Femelle' }}</li>
                        <li><strong>Date de naissance :</strong> {{ formatDate(cat.birth_date) }}</li>
                        <li><strong>Couleur des yeux :</strong> {{ cat.eye_color ?? '—' }}</li>
                        <li v-if="isKitten"><strong>Disponible à partir de :</strong> {{ formatDate(cat.available_at) }}</li>
                        <li><strong>Régime :</strong> {{ cat.diet ?? '—' }}</li>
                        <li v-if="isKitten"><strong>Formé à la litière :</strong> {{ cat.litter_trained ? 'Oui' : 'Non' }}</li>
                    </ul>

                    <div v-if="isKitten" class="mt-8 flex flex-wrap items-start gap-4">
                        <Link :href="route('pages.contact', { chaton: cat.slug })" class="btn-outline-brand">
                            Adopte moi
                        </Link>
                        <DepositForm v-if="cat.status === 'disponible'" :cat-id="cat.id" :amount-label="depositAmountLabel" />
                    </div>
                    <div v-else class="mt-8">
                        <Link :href="route('pages.contact')" class="btn-outline-brand">
                            Nous contacter à son sujet
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="isKitten" class="bg-brand-canvas border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading script="Chaton bengal à vendre" title="Voulez-vous adopter un chaton bengal ?" center />
                <p class="mt-4 text-lg text-neutral-700">Prix : <span class="font-semibold">{{ formatPrice(cat.price) }}</span></p>
                <Link :href="route('pages.contact', { chaton: cat.slug })" class="btn-outline-brand mt-6">
                    Faire une demande pour {{ cat.name }}
                </Link>
            </div>
        </section>

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading script="Soyez les premiers au courant" title="Abonnez-vous à notre infolettre !" center />
                <div class="mt-6 flex justify-center">
                    <NewsletterForm class="w-full max-w-md" />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
