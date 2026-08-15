<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { loadStripe } from '@stripe/stripe-js';
import type { Stripe, StripeElements, StripePaymentElement } from '@stripe/stripe-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps<{
    catId: number | null;
    catName: string | null;
    catSlug: string | null;
    amount: number;
    currency: string;
    stripePublishableKey: string;
    name: string;
    email: string;
    phone: string | null;
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
// True once confirm-intent has rejected the payment outright (the cat was
// taken by someone else in the meantime) — distinct from errorMessage,
// which covers a Stripe-side decline: this one means there's nothing left
// to retry, Stripe was never even asked.
const catUnavailable = ref(false);

let stripe: Stripe | null = null;
let elements: StripeElements | null = null;
let paymentElement: StripePaymentElement | null = null;
// Set once confirmIntent() returns — the PaymentIntent this checkout is
// actually paying for. Nothing is submitted to Stripe before this exists.
let paymentIntentId: string | null = null;

const formattedAmount = computed(() =>
    new Intl.NumberFormat(locale.value === 'fr' ? 'fr-CH' : 'en-CH', { style: 'currency', currency: props.currency }).format(props.amount / 100),
);

// Where "back to the kitten" points when the cat turns out to be
// unavailable — the cat's own page if this checkout was for one, the
// general list for a waiting-list checkout.
const backHref = computed(() => (props.catSlug ? route('cats.show', props.catSlug) : route('cats.index')));

onMounted(async () => {
    const stripeInstance = await loadStripe(props.stripePublishableKey);
    const container = paymentElementContainer.value;

    if (!stripeInstance || !container) {
        loadError.value = true;
        return;
    }

    stripe = stripeInstance;
    // Deferred mode — no clientSecret yet, since no PaymentIntent exists
    // until the visitor actually clicks "Pay" (see confirmIntent() call in
    // submitPayment() below, and CLAUDE.md: several visitors can each
    // fill in their card for the same cat without disturbing each other).
    // paymentMethodTypes must be listed explicitly here, mirroring
    // StripeGateway::createPaymentIntent()'s own payment_method_types —
    // without a real PaymentIntent yet to read them from, Stripe otherwise
    // falls back to the account's default methods (e.g. Amazon Pay)
    // instead of the card/twint pair this checkout actually supports.
    elements = stripe.elements({
        mode: 'payment',
        amount: props.amount,
        currency: props.currency.toLowerCase(),
        paymentMethodTypes: ['card', 'twint'],
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
});

async function confirmIntent(): Promise<string | null> {
    const response = await fetch(route('deposits.confirm-intent'), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            cat_id: props.catId,
            name: props.name,
            email: props.email,
            phone: props.phone,
        }),
    });

    if (!response.ok) return null;

    const data: { paymentIntentId: string; clientSecret: string } = await response.json();
    paymentIntentId = data.paymentIntentId;

    return data.clientSecret;
}

async function submitPayment(): Promise<void> {
    const stripeInstance = stripe;
    const elementsInstance = elements;

    if (!stripeInstance || !elementsInstance || processing.value) return;

    processing.value = true;
    errorMessage.value = null;
    catUnavailable.value = false;

    // Required by Stripe before any asynchronous work in deferred mode —
    // validates/collects the Payment Element's current fields synchronously
    // on the click. Skipping this makes confirmPayment() below throw
    // "elements.submit() must be called before stripe.confirmPayment()".
    const { error: submitError } = await elementsInstance.submit();

    if (submitError) {
        errorMessage.value = submitError.message ?? t('depositPay.generic_error');
        processing.value = false;
        return;
    }

    // Only created now — this is the moment the visitor actually commits
    // to paying (see CLAUDE.md). Re-checks availability server-side before
    // ever creating anything Stripe-side.
    const clientSecret = await confirmIntent();

    if (clientSecret === null) {
        catUnavailable.value = true;
        processing.value = false;
        return;
    }

    // Same destination either way: if Stripe needs an actual browser
    // redirect (TWINT, 3D Secure), it lands here directly; if it doesn't,
    // the code below sends the visitor here itself once confirmPayment()
    // resolves. DepositReturn.vue reads the deposit's real status from the
    // database, never from this query string — see CLAUDE.md.
    const returnUrl = route('deposits.return', { paymentIntentId, status: 'success' });

    // redirect: 'if_required' — a plain card payment with no extra
    // authentication confirms right here without ever leaving the page.
    // TWINT and 3D Secure both need a real browser redirect regardless of
    // this setting (see CLAUDE.md); when that happens, the browser
    // navigates to return_url and the code below never runs.
    const { error } = await stripeInstance.confirmPayment({
        elements: elementsInstance,
        clientSecret,
        confirmParams: { return_url: returnUrl },
        redirect: 'if_required',
    });

    if (error) {
        // Declined card, expired card, etc. — shown inline so the visitor
        // can fix the Payment Element's fields and retry without
        // restarting the whole deposit form.
        errorMessage.value = error.message ?? t('depositPay.generic_error');
        processing.value = false;
        return;
    }

    // Authorized (not yet captured — see CLAUDE.md, the actual capture
    // happens later via the webhook), and no redirect was needed.
    router.visit(returnUrl);
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

            <div class="mt-8 rounded-lg border border-gray-200 p-6">
                <div v-if="loadError" class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $t('depositPay.load_error') }}
                </div>

                <div v-else-if="catUnavailable" class="text-center">
                    <p class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {{ $t('depositPay.cat_unavailable') }}
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
                        <a :href="backHref" class="text-sm text-neutral-500 hover:text-neutral-700">
                            {{ $t('depositPay.cancel_link') }}
                        </a>
                    </div>
                </template>
            </div>
        </section>
    </PublicLayout>
</template>
