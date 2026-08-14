<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const props = defineProps<{
    catId?: number;
    amountLabel: string;
}>();

const page = usePage<PageProps>();
const honeypot = page.props.honeypot;
const open = ref(false);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    cat_id: props.catId ?? null,
    [honeypot.nameFieldName]: '',
    [honeypot.validFromFieldName]: honeypot.encryptedValidFrom,
});

// lang/{fr,en}.json's own translation of the sentence
// CheckoutHold::acquire() failing sends back (see
// Public\DepositController::store()) — __() translates it server-side
// per the active locale (see lang/*.json), so both variants are matched
// against here rather than just the English source string. Never
// displayed directly, exactly like the "already reserved" error below.
const isCheckoutInProgressError = computed(
    () =>
        form.errors.cat_id === 'Someone else is currently paying for this kitten. Please try again in a few minutes.'
        || form.errors.cat_id === 'Une autre personne est en train de payer pour ce chaton. Veuillez réessayer dans quelques minutes.',
);

function submit(): void {
    // A normal Inertia visit — the response renders Public/DepositPay.vue
    // directly, with the PaymentIntent's client_secret as a prop (see
    // CLAUDE.md), no full-page redirect involved.
    form.post(route('deposits.store'));
}
</script>

<template>
    <div>
        <button
            v-if="!open"
            type="button"
            class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-6 py-3 font-medium text-white hover:bg-emerald-800"
            @click="open = true"
        >
            {{ $t('deposit.reserve_button', { amount: amountLabel }) }}
        </button>

        <form v-else class="max-w-md space-y-4 rounded-lg border border-gray-200 p-6" @submit.prevent="submit">
            <div v-if="honeypot.enabled" style="display: none" aria-hidden="true">
                <input
                    :id="honeypot.nameFieldName"
                    v-model="(form as Record<string, string>)[honeypot.nameFieldName]"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                />
                <input
                    v-model="(form as Record<string, string>)[honeypot.validFromFieldName]"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>

            <h3 class="font-semibold text-neutral-900">{{ $t('deposit.reserve_button', { amount: amountLabel }) }}</h3>
            <p class="text-sm text-neutral-600">
                {{ $t('deposit.stripe_notice') }}
            </p>

            <!-- cat_id now has two distinct possible validation errors (see
                 CatIsAvailableForDeposit and CheckoutHold::acquire() in
                 Public\DepositController::store()) — matched against the
                 backend's own text (translated per locale via lang/*.json,
                 never displayed directly — see CLAUDE.md on the three i18n
                 layers) to pick the right friendly, vue-i18n message
                 instead. -->
            <p v-if="isCheckoutInProgressError" class="text-sm text-red-600">{{ $t('deposit.cat_checkout_in_progress') }}</p>
            <p v-else-if="form.errors.cat_id" class="text-sm text-red-600">{{ $t('deposit.cat_unavailable_error') }}</p>

            <div>
                <label for="deposit-name" class="block text-sm font-medium text-neutral-700">{{ $t('deposit.label_full_name') }}</label>
                <input
                    id="deposit-name"
                    v-model="form.name"
                    type="text"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="deposit-email" class="block text-sm font-medium text-neutral-700">{{ $t('deposit.label_email') }}</label>
                <input
                    id="deposit-email"
                    v-model="form.email"
                    type="email"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="deposit-phone" class="block text-sm font-medium text-neutral-700">
                    {{ $t('deposit.label_phone') }}
                </label>
                <input
                    id="deposit-phone"
                    v-model="form.phone"
                    type="tel"
                    class="mt-1 block w-full rounded-md border-gray-300"
                />
                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-emerald-700 px-6 py-2 font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                >
                    {{ $t('deposit.continue_button') }}
                </button>
                <button type="button" class="text-sm text-neutral-500 hover:text-neutral-700" @click="open = false">
                    {{ $t('deposit.cancel') }}
                </button>
            </div>
        </form>
    </div>
</template>
