<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
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

function submit(): void {
    // Inertia::location() replies with a 409 carrying the Stripe Checkout
    // URL — useForm().post() follows it with a full-page browser visit
    // automatically, same as any other Inertia redirect.
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
            Réserver avec un acompte de {{ amountLabel }}
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

            <h3 class="font-semibold text-neutral-900">Réserver avec un acompte de {{ amountLabel }}</h3>
            <p class="text-sm text-neutral-600">
                Vous allez être redirigé vers notre partenaire de paiement Stripe (carte ou TWINT).
            </p>

            <p v-if="form.errors.cat_id" class="text-sm text-red-600">{{ form.errors.cat_id }}</p>

            <div>
                <label for="deposit-name" class="block text-sm font-medium text-neutral-700">Nom complet</label>
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
                <label for="deposit-email" class="block text-sm font-medium text-neutral-700">E-mail</label>
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
                    Téléphone (facultatif)
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
                    Continuer vers le paiement
                </button>
                <button type="button" class="text-sm text-neutral-500 hover:text-neutral-700" @click="open = false">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</template>
