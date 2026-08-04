<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import type { Cat } from '@/types/models';

defineProps<{
    cats: Cat[];
}>();
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
        <section class="mx-auto max-w-7xl px-6 py-16">
            <div class="mb-10 text-center">
                <h1 class="text-3xl font-semibold text-neutral-900">
                    Chatons Bengal disponibles
                </h1>
                <p class="mt-2 text-neutral-600">
                    Mise à jour à chaque fois qu'un chaton est nouvellement réservé.
                </p>
            </div>

            <div v-if="cats.length" class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <Link v-for="cat in cats" :key="cat.id" :href="route('cats.show', cat.slug)"
                    class="group block overflow-hidden rounded-lg border border-gray-200 text-center transition hover:shadow-lg">
                    <div class="aspect-square overflow-hidden bg-gray-100">
                        <img v-if="cat.photos.length" :src="cat.photos[0].url" :alt="cat.name"
                            class="h-full w-full object-cover transition group-hover:scale-105" />
                        <div v-else class="flex h-full items-center justify-center text-gray-400">
                            Pas de photo
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-neutral-900">
                            {{ cat.name }} — {{ cat.status === 'disponible' ? 'Disponible' : 'En attente' }}
                        </h3>
                        <p class="mt-1 text-sm text-neutral-600">
                            {{ cat.color?.name }} bengal {{ cat.sex === 'male' ? 'boy' : 'girl' }}
                        </p>
                        <span class="mt-3 inline-block text-sm font-medium text-emerald-700">
                            Lire plus
                        </span>
                    </div>
                </Link>
            </div>

            <div v-else class="py-16 text-center">
                <h2 class="text-xl font-semibold text-neutral-900">Aucun résultat</h2>
                <p class="mt-2 text-neutral-600">
                    Pas de chatons disponibles pour le moment. La liste sera mise à jour dès que possible.
                </p>
            </div>
        </section>

        <section class="border-t border-gray-200 bg-gray-50 py-16">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <h2 class="text-2xl font-semibold text-neutral-900">Soyez les premiers au courant</h2>
                <p class="mt-2 text-neutral-600">
                    Abonnez-vous à notre infolettre pour être informé quand de nouveaux chatons sont disponibles.
                </p>
                <div class="mt-6 flex justify-center">
                    <NewsletterForm class="w-full max-w-md" />
                </div>
            </div>
        </section>
    </PublicLayout>

</template>
