<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { loadStripe } from '@stripe/stripe-js';
import type { Stripe, StripeElements, StripePaymentElement } from '@stripe/stripe-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps<{
    depositId: number;
    clientSecret: string;
    stripePublishableKey: string;
    catName: string | null;
    amount: number;
    currency: string;
}>();

const { t, locale } = useI18n();

const paymentElementContainer = ref<HTMLDivElement | null>(null);
const elementReady = ref(false);
const loadError = ref(false);
const processing = ref(false);
const errorMessage = ref<string | null>(null);

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
// see CLAUDE.md.
const returnUrl = computed(() => route('deposits.return', { deposit: props.depositId, status: 'success' }));
const cancelHref = computed(() => route('deposits.return', { deposit: props.depositId, status: 'cancelled' }));

onMounted(async () => {
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
});

async function submitPayment(): Promise<void> {
    const stripeInstance = stripe;
    const elementsInstance = elements;

    if (!stripeInstance || !elementsInstance || processing.value) return;

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
        // restarting the whole deposit form.
        errorMessage.value = error.message ?? t('depositPay.generic_error');
        processing.value = false;
        return;
    }

    // Authorized (not yet captured — see CLAUDE.md, the actual capture
    // happens later via the webhook), and no redirect was needed.
    router.visit(returnUrl.value);
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
                        <Link :href="cancelHref" class="text-sm text-neutral-500 hover:text-neutral-700">
                            {{ $t('depositPay.cancel_link') }}
                        </Link>
                    </div>
                </template>
            </div>
        </section>
    </PublicLayout>
</template>
