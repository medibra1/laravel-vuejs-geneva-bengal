<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps<{
    depositStatus: string;
}>();

// Purely cosmetic — which leg of the Stripe Checkout redirect brought the
// visitor back. The real answer to "did it work?" is depositStatus below,
// set from our own database (webhook-driven), never trusted from this
// query string alone. See CLAUDE.md.
const checkoutOutcome = new URLSearchParams(window.location.search).get('status');

const isPaid = computed(() => props.depositStatus === 'paid');
const isCancelled = computed(() => checkoutOutcome === 'cancelled' && props.depositStatus !== 'paid');
</script>

<template>
    <Head title="Votre acompte — Geneva Bengal" />

    <PublicLayout>
        <section class="mx-auto max-w-2xl px-6 py-24 text-center">
            <template v-if="isPaid">
                <i class="pi pi-check-circle text-5xl text-emerald-600" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">Merci, votre acompte a bien été reçu !</h1>
                <p class="mt-4 text-neutral-600">
                    Un e-mail de confirmation vient de vous être envoyé. Nous reviendrons vers vous très prochainement.
                </p>
            </template>

            <template v-else-if="isCancelled">
                <i class="pi pi-times-circle text-5xl text-neutral-400" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">Paiement non finalisé</h1>
                <p class="mt-4 text-neutral-600">
                    Vous avez annulé le paiement, aucun montant n'a été débité. Vous pouvez réessayer à tout moment
                    depuis la fiche du chaton.
                </p>
            </template>

            <template v-else>
                <i class="pi pi-hourglass text-5xl text-neutral-400" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">Nous traitons votre paiement</h1>
                <p class="mt-4 text-neutral-600">
                    Cela ne devrait prendre qu'un instant. Vous recevrez un e-mail de confirmation dès que le
                    paiement sera validé — inutile de rester sur cette page.
                </p>
            </template>

            <Link :href="route('cats.index')" class="mt-8 inline-flex items-center gap-2 text-emerald-700 hover:text-emerald-800">
                <i class="pi pi-arrow-left" />
                Retour aux chatons disponibles
            </Link>
        </section>
    </PublicLayout>
</template>
