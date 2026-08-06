<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageBanner from '@/Components/PageBanner.vue';
import SectionHeading from '@/Components/SectionHeading.vue';

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
}>();

function formatDate(date: string | null): string {
    if (!date) return 'Date à confirmer';

    return new Intl.DateTimeFormat('fr-CH', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(date));
}
</script>

<template>
    <Head title="Portées prévues">
        <meta
            head-key="description"
            name="description"
            content="Les prochaines portées de chatons Bengal prévues chez Geneva Bengal, avec leurs parents."
        />
    </Head>

    <PublicLayout>
        <PageBanner script="Portées prévues" subtitle="Les prochaines naissances" />

        <section class="mx-auto max-w-5xl px-6 py-16 sm:py-24">
            <SectionHeading script="Portées prévues" title="Les prochaines naissances chez Geneva Bengal" center />

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
                            <p class="mt-1 text-xs font-semibold text-neutral-700">{{ parent?.name ?? 'À confirmer' }}</p>
                        </div>
                    </div>

                    <div class="flex-1">
                        <p class="text-brand-green font-heading text-sm font-semibold tracking-wide uppercase">
                            {{ formatDate(litter.expected_date) }}
                        </p>
                        <p class="mt-1 text-neutral-700">
                            <span v-if="litter.sire || litter.dam">
                                {{ litter.sire?.name ?? 'Père à confirmer' }} × {{ litter.dam?.name ?? 'Mère à confirmer' }}
                            </span>
                        </p>
                        <p v-if="litter.notes" class="mt-2 text-sm text-neutral-600">{{ litter.notes }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="mt-12 py-16 text-center">
                <h2 class="text-xl font-semibold text-neutral-900">Aucune portée prévue pour le moment</h2>
                <p class="mt-2 text-neutral-600">Revenez bientôt pour découvrir nos prochaines naissances.</p>
            </div>
        </section>
    </PublicLayout>
</template>
