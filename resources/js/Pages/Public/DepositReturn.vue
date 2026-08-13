<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
// Lost the atomic re-check in DepositPaymentProcessor::markPaid() — see
// CLAUDE.md: another deposit for the same cat was captured first, this
// one's PaymentIntent authorization was cancelled, never charged.
const isUnavailable = computed(() => props.depositStatus === 'unavailable');
const isCancelled = computed(() => checkoutOutcome === 'cancelled' && props.depositStatus !== 'paid' && !isUnavailable.value);

// The webhook that actually captures the payment (see CLAUDE.md) runs
// asynchronously, generally a couple of seconds after the visitor lands
// here — poll lightly for it instead of making them refresh by hand.
// Capped so an abandoned tab doesn't poll forever.
const POLL_INTERVAL_MS = 3500;
const MAX_POLL_ATTEMPTS = 20;
let pollTimer: ReturnType<typeof setInterval> | null = null;
let pollAttempts = 0;

// True once polling has given up without the status ever leaving "pending"
// — e.g. the Stripe webhook never reached the app (misconfigured endpoint,
// network issue) and the daily ReconcilePendingDeposits job hasn't run yet.
// Without this, the page silently stays on the "pending" branch forever,
// which reads as broken rather than "still working, just slower than
// usual". Reset on prop change so a fresh visit (new depositStatus) doesn't
// inherit a stale timeout from a previous poll cycle.
const pollTimedOut = ref(false);
const isTimedOut = computed(() => pollTimedOut.value && props.depositStatus === 'pending');

function stopPolling(): void {
    if (pollTimer === null) return;

    clearInterval(pollTimer);
    pollTimer = null;
}

onMounted(() => {
    if (props.depositStatus !== 'pending') return;

    pollTimer = setInterval(() => {
        if (pollAttempts >= MAX_POLL_ATTEMPTS) {
            stopPolling();
            pollTimedOut.value = true;
            return;
        }

        pollAttempts += 1;
        router.reload({ only: ['depositStatus'] });
    }, POLL_INTERVAL_MS);
});

// Catches every way the status can leave "pending" — paid, unavailable, or
// anything else — rather than only the two currently shown in the template.
watch(
    () => props.depositStatus,
    (status) => {
        if (status !== 'pending') stopPolling();
    },
);

onBeforeUnmount(stopPolling);
</script>

<template>
    <Head :title="$t('depositReturn.meta_title')" />

    <PublicLayout>
        <section class="mx-auto max-w-2xl px-6 py-24 text-center">
            <template v-if="isPaid">
                <i class="pi pi-check-circle text-5xl text-emerald-600" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">{{ $t('depositReturn.paid_heading') }}</h1>
                <p class="mt-4 text-neutral-600">
                    {{ $t('depositReturn.paid_body') }}
                </p>
            </template>

            <template v-else-if="isUnavailable">
                <i class="pi pi-times-circle text-5xl text-amber-500" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">{{ $t('depositReturn.unavailable_heading') }}</h1>
                <p class="mt-4 text-neutral-600">
                    {{ $t('depositReturn.unavailable_body') }}
                </p>
            </template>

            <template v-else-if="isCancelled">
                <i class="pi pi-times-circle text-5xl text-neutral-400" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">{{ $t('depositReturn.cancelled_heading') }}</h1>
                <p class="mt-4 text-neutral-600">
                    {{ $t('depositReturn.cancelled_body') }}
                </p>
            </template>

            <template v-else-if="isTimedOut">
                <i class="pi pi-clock text-5xl text-neutral-400" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">{{ $t('depositReturn.timeout_heading') }}</h1>
                <p class="mt-4 text-neutral-600">
                    {{ $t('depositReturn.timeout_body') }}
                </p>
            </template>

            <template v-else>
                <i class="pi pi-hourglass text-5xl text-neutral-400" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">{{ $t('depositReturn.pending_heading') }}</h1>
                <p class="mt-4 text-neutral-600">
                    {{ $t('depositReturn.pending_body') }}
                </p>
            </template>

            <Link :href="route('cats.index')" class="mt-8 inline-flex items-center gap-2 text-emerald-700 hover:text-emerald-800">
                <i class="pi pi-arrow-left" />
                {{ $t('depositReturn.back_link') }}
            </Link>
        </section>
    </PublicLayout>
</template>
