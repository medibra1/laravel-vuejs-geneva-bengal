<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import type { PageProps } from '@/types';
import type { Cat } from '@/types/models';

const props = defineProps<{
    cats: Cat[];
    colorId: number | null;
}>();

const page = usePage<PageProps>();
const activeColor = computed(() => page.props.colors.find((color) => color.id === props.colorId));
</script>

<template>
    <Head title="Chatons Bengal disponibles">
        <meta
            head-key="description"
            name="description"
            content="Découvrez nos chatons Bengal actuellement disponibles à l'adoption à Genève — mise à jour dès qu'un chaton est réservé."
        />
    </Head>

    <PublicLayout>
        <PageBanner script="Chatons Bengal" subtitle="Faits avec amour" />

        <section id="nos-chatons" class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <SectionHeading script="Chatons Bengal disponibles" title="Mis à jour à chaque fois qu'un chaton est nouvellement réservé" center />

            <div v-if="activeColor" class="mt-8 flex items-center justify-center gap-3 text-sm">
                <span class="text-brand-gray">
                    Filtré par couleur : <strong>{{ activeColor.name }}</strong>
                </span>
                <Link :href="route('cats.index')" class="text-brand-green font-semibold underline hover:no-underline">
                    Réinitialiser
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
                        <img v-if="cat.photos.length" :src="cat.photos[0].url" :alt="cat.name"
                            class="h-full w-full object-cover transition group-hover:scale-105" />
                        <div v-else class="flex h-full items-center justify-center text-gray-400">
                            Pas de photo
                        </div>
                    </div>
                    <div class="p-5">
                        <h3 class="font-heading text-brand-gray text-base font-bold uppercase tracking-wide">
                            {{ cat.name }} — {{ cat.status === 'disponible' ? 'Disponible' : 'En attente' }}
                        </h3>
                        <p class="text-brand-tan mt-1 text-sm">
                            Bengal {{ cat.color?.name }} {{ cat.sex === 'male' ? 'mâle' : 'femelle' }}
                        </p>
                        <span class="text-brand-green mt-3 inline-block text-sm font-semibold uppercase tracking-wide">
                            Lire plus →
                        </span>
                    </div>
                </Link>
            </div>

            <div v-else class="mt-12 py-16 text-center">
                <h2 class="text-xl font-semibold text-neutral-900">Aucun résultat</h2>
                <p class="mt-2 text-neutral-600">
                    <template v-if="activeColor">
                        Pas de chaton {{ activeColor.name }} disponible pour le moment.
                    </template>
                    <template v-else>
                        Pas de chatons disponibles pour le moment. La liste sera mise à jour dès que possible.
                    </template>
                </p>
            </div>
        </section>

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
