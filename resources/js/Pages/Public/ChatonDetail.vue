<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
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

// Per-cat description, not a generic site-wide one — precisely the gap
// CLAUDE.md flags on the production site (same meta on every page, bad
// for indexing individual kitten listings).
const metaDescription = computed(() => {
    const description = props.cat.description[page.props.locale as 'fr' | 'en'] ?? '';
    const plainText = description.replace(/\s+/g, ' ').trim();

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

    <Head :title="`${cat.name} — Chaton Bengal disponible`">
        <meta v-if="metaDescription" head-key="description" name="description" :content="metaDescription" />
    </Head>

    <PublicLayout>
        <section class="mx-auto max-w-7xl px-6 py-16">
            <h1 class="mb-10 text-3xl font-semibold text-neutral-900">
                À propos de ce chaton bengal à vendre
            </h1>

            <div class="grid grid-cols-1 gap-16 md:grid-cols-2">
                <div>
                    <div class="aspect-square overflow-hidden rounded-lg bg-gray-100">
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
                            :class="index === selectedPhotoIndex ? 'ring-emerald-600' : 'ring-transparent hover:ring-gray-300'"
                            @click="selectedPhotoIndex = index"
                        >
                            <img :src="photo.url" :alt="`${cat.name} — photo ${index + 1}`" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <div>
                    <h2 class="text-xl font-bold text-neutral-900">Nom du chaton : {{ cat.name }}</h2>
                    <p class="mt-1 font-medium text-emerald-700">
                        {{ cat.status === 'disponible' ? 'Disponible' : 'En attente' }}
                    </p>
                    <h3 class="mt-1 text-neutral-600">{{ cat.color?.name }} Bengal cat</h3>

                    <ul class="mt-6 space-y-2 text-sm">
                        <li><strong>Sexe :</strong> {{ cat.sex === 'male' ? 'Mâle' : 'Femelle' }}</li>
                        <li><strong>Date de naissance :</strong> {{ formatDate(cat.birth_date) }}</li>
                        <li><strong>Couleur des yeux :</strong> {{ cat.eye_color ?? '—' }}</li>
                        <li><strong>Disponible à partir de :</strong> {{ formatDate(cat.available_at) }}</li>
                        <li><strong>Régime :</strong> {{ cat.diet ?? '—' }}</li>
                        <li><strong>Formé à la litière :</strong> {{ cat.litter_trained ? 'Oui' : 'Non' }}</li>
                        <li><strong>Castré/Stérilisé :</strong> {{ cat.neutered ? 'Oui' : 'Non' }}</li>
                    </ul>

                    <div class="mt-8 flex flex-wrap items-start gap-4">
                        <Link :href="route('pages.contact', { chaton: cat.slug })"
                            class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-6 py-3 font-medium text-white hover:bg-emerald-800">
                            Adopte moi
                        </Link>
                        <DepositForm v-if="cat.status === 'disponible'" :cat-id="cat.id" :amount-label="depositAmountLabel" />
                    </div>
                </div>
            </div>
        </section>

        <hr class="mx-auto max-w-7xl border-gray-200" />

        <section class="mx-auto max-w-7xl px-6 py-16">
            <h2 class="text-2xl font-semibold text-neutral-900">Chaton bengal à vendre</h2>
            <p class="mt-4">Voulez-vous adopter un chaton bengal ? <strong>Nous contacter !</strong></p>
            <p class="mt-1 text-lg">Prix : <span class="font-semibold">{{ formatPrice(cat.price) }}</span></p>
            <div class="mt-6">
                <Link :href="route('pages.contact', { chaton: cat.slug })"
                    class="inline-flex items-center gap-2 rounded-md border border-emerald-700 px-6 py-3 font-medium text-emerald-700 hover:bg-emerald-50">
                    Faire une demande pour {{ cat.name }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
