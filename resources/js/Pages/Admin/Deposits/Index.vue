<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import type { PageProps } from '@/types';
import type { Deposit, OwnerCatOption, OwnerOption, Paginated } from '@/types/models';

const props = defineProps<{
    deposits: Paginated<Deposit>;
    cats: OwnerCatOption[];
    owners: OwnerOption[];
}>();

const page = usePage<PageProps>();
const isSuperAdmin = computed(() => page.props.auth.roles.includes('super_admin'));

const statusOptions = [
    { label: 'En attente', value: 'pending' },
    { label: 'Payé', value: 'paid' },
    { label: 'Échoué', value: 'failed' },
    { label: 'Remboursé', value: 'refunded' },
    { label: 'Annulé', value: 'cancelled' },
];

const paymentMethodLabels: Record<string, string> = {
    stripe: 'Stripe',
    cash: 'Espèces',
    bank_transfer: 'Virement',
    twint_manual: 'TWINT (manuel)',
};

function statusSeverity(status: string): 'success' | 'warn' | 'danger' | 'secondary' {
    if (status === 'paid') return 'success';
    if (status === 'pending') return 'warn';
    if (status === 'failed') return 'danger';

    return 'secondary';
}

function formatAmount(cents: number, currency: string): string {
    return new Intl.NumberFormat('fr-CH', { style: 'currency', currency }).format(cents / 100);
}

function formatDate(date: string | null): string {
    if (!date) return '—';

    return new Intl.DateTimeFormat('fr-CH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date));
}

// Filters — read from the URL once (spatie/laravel-query-builder's
// filter[...] shape) so a reload/shared link keeps the current view.
const initialParams = new URLSearchParams(window.location.search);
const filters = reactive({
    status: initialParams.get('filter[status]') ?? null,
    cat_id: initialParams.get('filter[cat_id]') ? Number(initialParams.get('filter[cat_id]')) : null,
    from: initialParams.get('filter[from]') ?? '',
    to: initialParams.get('filter[to]') ?? '',
});

function applyFilters(): void {
    router.get(
        route('admin.deposits.index'),
        {
            filter: {
                status: filters.status ?? undefined,
                cat_id: filters.cat_id ?? undefined,
                from: filters.from || undefined,
                to: filters.to || undefined,
            },
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function goToPage(pageNumber: number): void {
    router.get(route('admin.deposits.index'), { page: pageNumber }, { preserveState: true, preserveScroll: true });
}

function refund(deposit: Deposit): void {
    if (confirm(`Rembourser l'acompte de ${formatAmount(deposit.amount, deposit.currency)} de ${deposit.name} ?`)) {
        router.post(route('admin.deposits.refund', deposit.id), {}, { preserveScroll: true });
    }
}

function markPaid(deposit: Deposit): void {
    if (confirm(`Marquer l'acompte de ${deposit.name} comme payé (${paymentMethodLabels[deposit.payment_method]}) ?`)) {
        router.post(route('admin.deposits.mark-paid', deposit.id), {}, { preserveScroll: true });
    }
}

const copiedId = ref<number | null>(null);

async function copyPaymentLink(deposit: Deposit): Promise<void> {
    if (!deposit.payment_link_url) return;

    await navigator.clipboard.writeText(deposit.payment_link_url);
    copiedId.value = deposit.id;
    setTimeout(() => {
        if (copiedId.value === deposit.id) copiedId.value = null;
    }, 2000);
}

// Finalizing: skips the dialog entirely when the deposit already has an
// owner (set back at creation) — nothing left to ask.
const finalizeDialogVisible = ref(false);
const finalizingDeposit = ref<Deposit | null>(null);
const ownerMode = ref<'existing' | 'new'>('existing');

const finalizeForm = useForm({
    owner_id: null as number | null,
    new_owner: {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        city: '',
    },
});

function finalize(deposit: Deposit): void {
    if (deposit.owner_id) {
        if (confirm(`Finaliser l'adoption pour ${deposit.name} ?`)) {
            router.post(route('admin.deposits.finalize', deposit.id), {}, { preserveScroll: true });
        }
        return;
    }

    finalizingDeposit.value = deposit;
    ownerMode.value = 'existing';
    finalizeForm.reset();
    finalizeForm.clearErrors();
    finalizeDialogVisible.value = true;
}

function submitFinalize(): void {
    if (!finalizingDeposit.value) return;

    finalizeForm
        .transform((data) => (ownerMode.value === 'existing' ? { owner_id: data.owner_id } : { new_owner: data.new_owner }))
        .post(route('admin.deposits.finalize', finalizingDeposit.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                finalizeDialogVisible.value = false;
            },
        });
}
</script>

<template>
    <Head title="Réservations" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Réservations</h2>
                <Link :href="route('admin.deposits.create')">
                    <Button label="Nouvelle réservation" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-end gap-4 bg-white dark:bg-neutral-800 p-4 shadow-sm sm:rounded-lg">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Statut</label>
                        <Select
                            v-model="filters.status"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            show-clear
                            placeholder="Tous les statuts"
                            class="w-48"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Chat</label>
                        <Select
                            v-model="filters.cat_id"
                            :options="cats"
                            option-label="name"
                            option-value="id"
                            show-clear
                            placeholder="Tous les chats"
                            class="w-48"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Du</label>
                        <input
                            v-model="filters.from"
                            type="date"
                            class="rounded-md border-gray-300 text-sm"
                            @change="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Au</label>
                        <input
                            v-model="filters.to"
                            type="date"
                            class="rounded-md border-gray-300 text-sm"
                            @change="applyFilters"
                        />
                    </div>
                </div>

                <div class="overflow-hidden bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="deposits.data" data-key="id">
                        <Column field="name" header="Nom" />
                        <Column header="Chat">
                            <template #body="{ data }">{{ data.cat?.name ?? "Liste d'attente" }}</template>
                        </Column>
                        <Column header="Adoptant">
                            <template #body="{ data }">
                                {{ data.owner ? `${data.owner.first_name} ${data.owner.last_name}` : '—' }}
                            </template>
                        </Column>
                        <Column header="Montant">
                            <template #body="{ data }">{{ formatAmount(data.amount, data.currency) }}</template>
                        </Column>
                        <Column header="Méthode">
                            <template #body="{ data }">{{ paymentMethodLabels[data.payment_method] }}</template>
                        </Column>
                        <Column header="Statut">
                            <template #body="{ data }">
                                <div class="flex items-center gap-1">
                                    <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                                    <Tag v-if="data.finalized_at" value="Finalisé" severity="info" />
                                </div>
                            </template>
                        </Column>
                        <Column header="Payé le">
                            <template #body="{ data }">{{ formatDate(data.paid_at) }}</template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="data.payment_method !== 'stripe' && data.status === 'pending'"
                                        label="Marquer payé"
                                        icon="pi pi-check"
                                        severity="success"
                                        size="small"
                                        text
                                        @click="markPaid(data)"
                                    />
                                    <Button
                                        v-if="data.payment_method === 'stripe' && data.status === 'pending' && data.payment_link_url"
                                        :label="copiedId === data.id ? 'Copié !' : 'Copier le lien'"
                                        icon="pi pi-link"
                                        severity="secondary"
                                        size="small"
                                        text
                                        @click="copyPaymentLink(data)"
                                    />
                                    <Button
                                        v-if="data.status === 'paid' && !data.finalized_at"
                                        label="Finaliser"
                                        icon="pi pi-heart-fill"
                                        severity="info"
                                        size="small"
                                        text
                                        @click="finalize(data)"
                                    />
                                    <Button
                                        v-if="isSuperAdmin && data.status === 'paid'"
                                        label="Rembourser"
                                        icon="pi pi-replay"
                                        severity="danger"
                                        size="small"
                                        text
                                        @click="refund(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>

                    <div v-if="deposits.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button
                            v-for="pageNumber in deposits.last_page"
                            :key="pageNumber"
                            :label="String(pageNumber)"
                            :severity="pageNumber === deposits.current_page ? 'primary' : 'secondary'"
                            size="small"
                            text
                            @click="goToPage(pageNumber)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="finalizeDialogVisible" header="Finaliser l'adoption" modal class="w-full max-w-lg">
            <p class="mb-4 text-sm text-neutral-500">
                Cette réservation n'a pas encore d'adoptant lié — choisissez-en un existant ou créez-le.
            </p>

            <div class="mb-4 flex gap-6">
                <label class="flex items-center gap-2 text-sm">
                    <RadioButton v-model="ownerMode" value="existing" />
                    Adoptant existant
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <RadioButton v-model="ownerMode" value="new" />
                    Nouvel adoptant
                </label>
            </div>

            <div v-if="ownerMode === 'existing'">
                <Select
                    v-model="finalizeForm.owner_id"
                    :options="owners"
                    :option-label="(owner: OwnerOption) => `${owner.first_name} ${owner.last_name} (${owner.email})`"
                    option-value="id"
                    placeholder="Choisir un adoptant"
                    class="w-full"
                />
                <p v-if="finalizeForm.errors.owner_id" class="mt-1 text-sm text-red-600">{{ finalizeForm.errors.owner_id }}</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Prénom</label>
                    <InputText v-model="finalizeForm.new_owner.first_name" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Nom</label>
                    <InputText v-model="finalizeForm.new_owner.last_name" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">E-mail</label>
                    <InputText v-model="finalizeForm.new_owner.email" type="email" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Téléphone</label>
                    <InputText v-model="finalizeForm.new_owner.phone" class="w-full" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Ville</label>
                    <InputText v-model="finalizeForm.new_owner.city" class="w-full" />
                </div>
            </div>

            <template #footer>
                <Button label="Annuler" severity="secondary" text @click="finalizeDialogVisible = false" />
                <Button label="Finaliser" :disabled="finalizeForm.processing" @click="submitFinalize" />
            </template>
        </Dialog>
    </AdminLayout>
</template>
