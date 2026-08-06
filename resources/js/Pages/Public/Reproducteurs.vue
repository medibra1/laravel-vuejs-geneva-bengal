<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import type { PageProps } from '@/types';
import type { Cat } from '@/types/models';

defineProps<{
    cats: Cat[];
}>();

const page = usePage<PageProps>();

function description(cat: Cat): string {
    return cat.description[page.props.locale as 'fr' | 'en'] ?? '';
}
</script>

<template>
    <Head title="Nos chats Bengal reproducteurs">
        <meta
            head-key="description"
            name="description"
            content="Découvrez nos chats Bengal reproducteurs, sélectionnés pour leur santé, leur tempérament et la beauté de leur robe."
        />
    </Head>

    <PublicLayout>
        <PageBanner script="Nos reproducteurs" subtitle="La base de nos portées" />

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <SectionHeading script="Nos chats Bengal reproducteurs" title="Sélectionnés pour leur santé et leur tempérament" center />

            <div v-if="cats.length" class="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="cat in cats"
                    :key="cat.id"
                    :href="route('cats.show', cat.slug)"
                    class="group block overflow-hidden rounded-2xl border border-gray-200 bg-white text-center shadow-sm transition hover:shadow-lg"
                >
                    <div class="aspect-square overflow-hidden bg-gray-100">
                        <img
                            v-if="cat.photos.length"
                            :src="cat.photos[0].url"
                            :alt="cat.name"
                            class="h-full w-full object-cover transition group-hover:scale-105"
                        />
                        <div v-else class="flex h-full items-center justify-center text-gray-400">Pas de photo</div>
                    </div>
                    <div class="p-5">
                        <h3 class="font-heading text-brand-gray text-base font-bold uppercase tracking-wide">
                            {{ cat.name }}
                        </h3>
                        <p class="text-brand-tan mt-1 text-sm">
                            Bengal {{ cat.color?.name }} {{ cat.sex === 'male' ? 'mâle' : 'femelle' }}
                        </p>
                        <p v-if="description(cat)" class="mt-3 line-clamp-3 text-sm text-neutral-600">
                            {{ description(cat) }}
                        </p>
                        <span class="text-brand-green mt-3 inline-block text-sm font-semibold uppercase tracking-wide">
                            En savoir plus →
                        </span>
                    </div>
                </Link>
            </div>

            <div v-else class="mt-12 py-16 text-center">
                <h2 class="text-xl font-semibold text-neutral-900">Aucun reproducteur pour le moment</h2>
                <p class="mt-2 text-neutral-600">Revenez bientôt pour découvrir nos chats reproducteurs.</p>
            </div>
        </section>
    </PublicLayout>
</template>
