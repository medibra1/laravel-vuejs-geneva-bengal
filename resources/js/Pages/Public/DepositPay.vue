<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { loadStripe } from '@stripe/stripe-js';
import type { Stripe, StripeElements, StripePaymentElement } from '@stripe/stripe-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps<{
    paymentIntentId: string;
    clientSecret: string;
    stripePublishableKey: string;
    catName: string | null;
    catSlug: string | null;
    amount: number;
    currency: string;
    // Null for a waiting-list checkout — no CheckoutHold is ever acquired
    // for one (no single cat's payment slot to protect, see
    // Public\DepositController::store()), so there's nothing to ping or
    // count down for.
    hardExpiresAt: string | null;
}>();

const { t, locale } = useI18n();

function csrfToken(): string {
    return document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

const paymentElementContainer = ref<HTMLDivElement | null>(null);
const elementReady = ref(false);
const loadError = ref(false);
const processing = ref(false);
const errorMessage = ref<string | null>(null);
const cancelling = ref(false);
// Set either by a failed touch ping or by the local countdown reaching
// zero — whichever notices first (see CLAUDE.md: the countdown can beat
// the next scheduled ping to the punch). Once true, the payment button
// stays disabled regardless of elementReady/processing.
const sessionExpired = ref(false);

let stripe: Stripe | null = null;
let elements: StripeElements | null = null;
let paymentElement: StripePaymentElement | null = null;

const formattedAmount = computed(() =>
    new Intl.NumberFormat(locale.value === 'fr' ? 'fr-CH' : 'en-CH', { style: 'currency', currency: props.currency }).format(props.amount / 100),
);

// Same destination either way — see submitPayment(): if Stripe needs an
// actual browser redirect (TWINT, 3D Secure), it lands here directly; if
// it doesn't, submitPayment() sends the visitor here itself once
// confirmPayment() resolves. DepositReturn.vue (unchanged) reads the
// deposit's real status from the database, never from this query string —
// see CLAUDE.md. Keyed on the PaymentIntent id, not a deposit id: no
// Deposit row exists yet at this point (see Public\DepositController).
const returnUrl = computed(() => route('deposits.return', { paymentIntentId: props.paymentIntentId, status: 'success' }));
const cancelledReturnUrl = computed(() => route('deposits.return', { paymentIntentId: props.paymentIntentId, status: 'cancelled' }));

// Where "back to the kitten" points once the session has expired — the
// cat's own page if this checkout was for one, the general list for a
// waiting-list checkout.
const backHref = computed(() => (props.catSlug ? route('cats.show', props.catSlug) : route('cats.index')));

// --- Hard-expiry countdown --------------------------------------------
// Reflects hard_expires_at only, never the sliding expires_at — see
// CLAUDE.md: a counter that jumped back up on every successful ping would
// be confusing. The visitor sees one fixed budget running out, not the
// renewal mechanism underneath it. Recomputed from the fixed
// hard_expires_at timestamp on every tick (not decremented in place) so a
// backgrounded tab that throttles setInterval doesn't drift — the next
// tick, whenever it actually runs, still reflects the real remaining time.
const COUNTDOWN_WARNING_SECONDS = 120;

const hardExpiresAtMs = props.hardExpiresAt ? new Date(props.hardExpiresAt).getTime() : null;
const secondsRemaining = ref<number | null>(hardExpiresAtMs ? Math.max(0, Math.round((hardExpiresAtMs - Date.now()) / 1000)) : null);
let countdownTimer: ReturnType<typeof setInterval> | null = null;

const countdownLabel = computed(() => {
    if (secondsRemaining.value === null) return null;

    const minutes = Math.floor(secondsRemaining.value / 60);
    const seconds = secondsRemaining.value % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

const countdownIsPressing = computed(() => secondsRemaining.value !== null && secondsRemaining.value <= COUNTDOWN_WARNING_SECONDS);

function stopCountdown(): void {
    if (countdownTimer === null) return;

    clearInterval(countdownTimer);
    countdownTimer = null;
}

// --- Checkout hold ping --------------------------------------------------
// Keeps CheckoutHold::extend()'s sliding expires_at from lapsing while
// this page stays open — see CLAUDE.md. Runs independently of the
// countdown above: the countdown only ever counts down locally, it never
// extends anything server-side by itself.
const PING_INTERVAL_MS = 60_000;
let pingTimer: ReturnType<typeof setInterval> | null = null;

function stopPing(): void {
    if (pingTimer === null) return;

    clearInterval(pingTimer);
    pingTimer = null;
}

async function pingHold(): Promise<void> {
    try {
        const response = await fetch(route('deposits.hold.touch'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ payment_intent_id: props.paymentIntentId }),
        });
        const data: { ok: boolean } = await response.json();

        if (!data.ok) expireSession();
    } catch {
        // A network blip on the ping itself isn't treated as an expiry —
        // only an explicit server "no longer holds this" (ok: false) or
        // the local countdown reaching zero does that. The next ping (or
        // the countdown) gets another chance.
    }
}

function expireSession(): void {
    if (sessionExpired.value) return;

    sessionExpired.value = true;
    stopCountdown();
    stopPing();
}

onMounted(async () => {
    if (props.hardExpiresAt !== null) {
        countdownTimer = setInterval(() => {
            const remaining = Math.max(0, Math.round((hardExpiresAtMs! - Date.now()) / 1000));
            secondsRemaining.value = remaining;

            if (remaining <= 0) expireSession();
        }, 1000);

        pingTimer = setInterval(pingHold, PING_INTERVAL_MS);
    }

    const stripeInstance = await loadStripe(props.stripePublishableKey);
    const container = paymentElementContainer.value;

    if (!stripeInstance || !container) {
        loadError.value = true;
        return;
    }

    stripe = stripeInstance;
    elements = stripe.elements({
        clientSecret: props.clientSecret,
        locale: locale.value === 'fr' ? 'fr' : 'en',
        appearance: {
            theme: 'stripe',
            variables: {
                colorPrimary: '#43b853',
                borderRadius: '8px',
            },
        },
    });

    paymentElement = elements.create('payment');
    paymentElement.mount(container);
    paymentElement.on('ready', () => {
        elementReady.value = true;
    });
});

onBeforeUnmount(() => {
    paymentElement?.destroy();
    stopCountdown();
    stopPing();
});

async function submitPayment(): Promise<void> {
    const stripeInstance = stripe;
    const elementsInstance = elements;

    if (!stripeInstance || !elementsInstance || processing.value || sessionExpired.value) return;

    processing.value = true;
    errorMessage.value = null;

    // redirect: 'if_required' — a plain card payment with no extra
    // authentication confirms right here without ever leaving the page.
    // TWINT and 3D Secure both need a real browser redirect regardless of
    // this setting (see CLAUDE.md); when that happens, the browser
    // navigates to return_url and the code below never runs.
    const { error } = await stripeInstance.confirmPayment({
        elements: elementsInstance,
        confirmParams: { return_url: returnUrl.value },
        redirect: 'if_required',
    });

    if (error) {
        // Declined card, expired card, etc. — shown inline so the visitor
        // can fix the Payment Element's fields and retry without
        // restarting the whole deposit form. The checkout hold is *not*
        // released here (see CLAUDE.md): it's still the same visitor
        // trying to pay, releasing it would let someone else take the
        // slot while they re-enter their card — and the ping keeps
        // running, uninterrupted by this branch.
        errorMessage.value = error.message ?? t('depositPay.generic_error');
        processing.value = false;
        return;
    }

    // Authorized (not yet captured — see CLAUDE.md, the actual capture
    // happens later via the webhook), and no redirect was needed.
    router.visit(returnUrl.value);
}

// The visitor explicitly gives up — releases the payment slot immediately
// (rather than waiting out the sliding TTL) so the cat is reservable again
// for someone else right away, then navigates to the cancelled status
// page. Fires the release request without blocking the navigation on it:
// the visitor's intent to leave shouldn't wait on this network call.
function cancelCheckout(): void {
    if (cancelling.value) return;

    cancelling.value = true;
    stopCountdown();
    stopPing();

    void fetch(route('deposits.hold.release'), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ payment_intent_id: props.paymentIntentId }),
    });

    router.visit(cancelledReturnUrl.value);
}
</script>

<template>
    <Head :title="$t('depositPay.meta_title')" />

    <PublicLayout>
        <section class="mx-auto max-w-lg px-6 py-16">
            <h1 class="text-center text-2xl font-semibold text-neutral-900">{{ $t('depositPay.heading') }}</h1>
            <p class="mt-3 text-center text-neutral-600">
                <template v-if="catName">{{ $t('depositPay.summary_for_cat', { name: catName, amount: formattedAmount }) }}</template>
                <template v-else>{{ $t('depositPay.summary_waiting_list', { amount: formattedAmount }) }}</template>
            </p>

            <p
                v-if="countdownLabel && !sessionExpired"
                class="mt-4 text-center text-sm font-medium"
                :class="countdownIsPressing ? 'text-red-600' : 'text-neutral-500'"
            >
                {{ $t('depositPay.countdown_label', { time: countdownLabel }) }}
            </p>

            <div class="mt-8 rounded-lg border border-gray-200 p-6">
                <div v-if="loadError" class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $t('depositPay.load_error') }}
                </div>

                <div v-else-if="sessionExpired" class="text-center">
                    <p class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {{ $t('depositPay.session_expired') }}
                    </p>
                    <a :href="backHref" class="mt-4 inline-flex items-center gap-2 text-sm text-emerald-700 hover:text-emerald-800">
                        {{ $t('depositPay.back_to_cat_link') }}
                    </a>
                </div>

                <template v-else>
                    <div v-if="errorMessage" class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ errorMessage }}
                    </div>

                    <div ref="paymentElementContainer" />

                    <p v-if="!elementReady" class="mt-4 text-center text-sm text-neutral-500">
                        {{ $t('depositPay.loading') }}
                    </p>

                    <div class="mt-6 flex items-center gap-3">
                        <button
                            type="button"
                            :disabled="!elementReady || processing"
                            class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-6 py-2 font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                            @click="submitPayment"
                        >
                            {{ processing ? $t('depositPay.paying_button') : $t('depositPay.pay_button') }}
                        </button>
                        <button
                            type="button"
                            :disabled="cancelling"
                            class="text-sm text-neutral-500 hover:text-neutral-700 disabled:opacity-60"
                            @click="cancelCheckout"
                        >
                            {{ $t('depositPay.cancel_link') }}
                        </button>
                    </div>
                </template>
            </div>
        </section>
    </PublicLayout>
</template>
