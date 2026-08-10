<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import RadioButton from 'primevue/radiobutton';
import Select from 'primevue/select';
import { manualPaymentMethodOptions } from '@/Composables/useDepositActions';
import type { OwnerCatOption, OwnerOption } from '@/types/models';

const props = defineProps<{
    cats: OwnerCatOption[];
    owners: OwnerOption[];
    defaultAmount: number;
}>();

const paymentMethodOptions = [{ label: 'À définir plus tard', value: null }, ...manualPaymentMethodOptions];

const ownerMode = ref<'none' | 'existing' | 'new'>('none');

// Two entry points set these, both via a plain query param (not a
// spatie/laravel-query-builder filter — this is a create-page context, not
// a filtered list): the "Liste d'attente" nav link (AdminLayout.vue,
// carried over from Deposits/Index.vue's own "Nouvelle inscription"
// button) and CatAdoptionPanel.vue's "Créer une réservation" link, which
// preselects the cat instead of leaving the admin to find it again in the
// dropdown.
const initialParams = new URLSearchParams(window.location.search);
const isWaitingListContext = initialParams.get('waiting_list') === '1';
const initialCatId = initialParams.get('cat_id') ? Number(initialParams.get('cat_id')) : null;

const form = useForm({
    cat_id: initialCatId,
    name: '',
    email: '',
    phone: '',
    amount: props.defaultAmount,
    payment_method: null as string | null,
    owner_id: null as number | null,
    new_owner: {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        city: '',
    },
});

// form.amount stays in centimes (what's stored and validated) — see the
// same pattern on the Cat form's price field.
const amountChf = computed({
    get: () => form.amount / 100,
    set: (value: number | null) => {
        form.amount = value !== null ? Math.round(value * 100) : 0;
    },
});

// Keeps the admin from retyping the same contact details twice when
// linking an existing owner — see CLAUDE.md. Backend-derived instead for a
// *new* owner (see resolveContact() in Admin\DepositController), so those
// fields aren't shown at all in that mode.
function fillContactFromSelectedOwner(): void {
    const owner = props.owners.find((candidate) => candidate.id === form.owner_id) ?? null;
    form.name = owner ? `${owner.first_name} ${owner.last_name}` : '';
    form.email = owner?.email ?? '';
    form.phone = owner?.phone ?? '';
}

watch(ownerMode, (mode) => {
    if (mode === 'existing') {
        fillContactFromSelectedOwner();
    } else {
        form.name = '';
        form.email = '';
        form.phone = '';
    }
});

watch(
    () => form.owner_id,
    () => {
        if (ownerMode.value === 'existing') fillContactFromSelectedOwner();
    },
);

const preselectedCat = props.cats.find((cat) => cat.id === initialCatId) ?? null;
const pageTitle = computed(() => {
    if (isWaitingListContext) return "Nouvelle inscription en liste d'attente";
    if (preselectedCat) return `Nouvelle réservation pour ${preselectedCat.name}`;

    return 'Nouvelle réservation';
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        owner_id: ownerMode.value === 'existing' ? data.owner_id : null,
        new_owner: ownerMode.value === 'new' ? data.new_owner : null,
    })).post(route('admin.deposits.store'));
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">{{ pageTitle }}</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Adoptant (optionnel)</h3>
                        <p class="mt-1 text-xs text-neutral-500">
                            Peut être laissé de côté pour l'instant et lié plus tard, à la finalisation de
                            l'adoption.
                        </p>

                        <div class="mt-4 flex gap-6">
                            <label class="flex items-center gap-2 text-sm">
                                <RadioButton v-model="ownerMode" value="none" />
                                À définir plus tard
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <RadioButton v-model="ownerMode" value="existing" />
                                Adoptant existant
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <RadioButton v-model="ownerMode" value="new" />
                                Nouvel adoptant
                            </label>
                        </div>

                        <div v-if="ownerMode === 'existing'" class="mt-4">
                            <Select
                                v-model="form.owner_id"
                                :options="owners"
                                :option-label="(owner: OwnerOption) => `${owner.first_name} ${owner.last_name} (${owner.email})`"
                                option-value="id"
                                placeholder="Choisir un adoptant"
                                class="w-full"
                            />
                            <InputError :message="form.errors.owner_id" />
                        </div>

                        <div v-else-if="ownerMode === 'new'" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="new_owner_first_name" value="Prénom" />
                                <InputText id="new_owner_first_name" v-model="form.new_owner.first_name" class="mt-1 w-full" />
                                <InputError :message="form.errors['new_owner.first_name']" />
                            </div>
                            <div>
                                <InputLabel for="new_owner_last_name" value="Nom" />
                                <InputText id="new_owner_last_name" v-model="form.new_owner.last_name" class="mt-1 w-full" />
                                <InputError :message="form.errors['new_owner.last_name']" />
                            </div>
                            <div>
                                <InputLabel for="new_owner_email" value="E-mail" />
                                <InputText id="new_owner_email" v-model="form.new_owner.email" type="email" class="mt-1 w-full" />
                                <InputError :message="form.errors['new_owner.email']" />
                            </div>
                            <div>
                                <InputLabel for="new_owner_phone" value="Téléphone" />
                                <InputText id="new_owner_phone" v-model="form.new_owner.phone" class="mt-1 w-full" />
                                <InputError :message="form.errors['new_owner.phone']" />
                            </div>
                            <div class="sm:col-span-2">
                                <InputLabel for="new_owner_city" value="Ville" />
                                <InputText id="new_owner_city" v-model="form.new_owner.city" class="mt-1 w-full" />
                                <InputError :message="form.errors['new_owner.city']" />
                            </div>
                        </div>
                    </div>

                    <div v-if="ownerMode !== 'new'" class="border-t border-gray-200 pt-6">
                        <h3 class="text-sm font-semibold text-neutral-900">Contact de la demande</h3>
                        <p v-if="ownerMode === 'existing'" class="mt-1 text-xs text-neutral-500">
                            Pré-rempli depuis l'adoptant sélectionné ci-dessus.
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3">
                            <div>
                                <InputLabel for="name" value="Nom" />
                                <InputText
                                    id="name"
                                    v-model="form.name"
                                    :readonly="ownerMode === 'existing'"
                                    class="mt-1 w-full"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="email" value="E-mail" />
                                <InputText
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    :readonly="ownerMode === 'existing'"
                                    class="mt-1 w-full"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div>
                                <InputLabel for="phone" value="Téléphone (optionnel)" />
                                <InputText
                                    id="phone"
                                    v-model="form.phone"
                                    :readonly="ownerMode === 'existing'"
                                    class="mt-1 w-full"
                                />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>
                    </div>
                    <p v-else class="border-t border-gray-200 pt-6 text-xs text-neutral-500">
                        Les coordonnées du nouvel adoptant ci-dessus serviront aussi de contact pour cette demande.
                    </p>

                    <div class="grid grid-cols-1 gap-6 border-t border-gray-200 pt-6 sm:grid-cols-2">
                        <div v-if="!isWaitingListContext">
                            <InputLabel for="cat_id" value="Chat réservé (optionnel)" />
                            <Select
                                id="cat_id"
                                v-model="form.cat_id"
                                :options="cats"
                                option-label="name"
                                option-value="id"
                                show-clear
                                placeholder="Liste d'attente"
                                class="mt-1 w-full"
                            />
                            <InputError :message="form.errors.cat_id" />
                        </div>

                        <div>
                            <InputLabel for="amount" value="Montant (CHF)" />
                            <InputNumber id="amount" v-model="amountChf" mode="currency" currency="CHF" locale="fr-CH"
                                class="mt-1 w-full" />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div>
                            <InputLabel for="payment_method" value="Méthode de paiement" />
                            <Select id="payment_method" v-model="form.payment_method" :options="paymentMethodOptions"
                                option-label="label" option-value="value" class="mt-1 w-full" />
                            <InputError :message="form.errors.payment_method" />
                            <p v-if="form.payment_method === null" class="mt-1 text-xs text-neutral-500">
                                À choisir plus tard, au moment de marquer le dépôt payé.
                            </p>
                            <p v-else class="mt-1 text-xs text-neutral-500">
                                À marquer "payé" manuellement une fois le paiement reçu.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Créer</PrimaryButton>
                        <Button label="Annuler" severity="secondary" text
                            @click="$inertia.get(route('admin.deposits.index'))" />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
