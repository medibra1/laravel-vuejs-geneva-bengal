<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import FinalizeOwnerDialog from '@/Components/Admin/FinalizeOwnerDialog.vue';
import { formatAmount, formatDate, paymentMethodLabels, statusSeverity, useDepositActions } from '@/Composables/useDepositActions';
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

const {
    copiedId,
    copyPaymentLink,
    markPaid,
    refund,
    finalize,
    submitFinalize,
    finalizeDialogVisible,
    ownerMode,
    finalizeForm,
} = useDepositActions();

// Filters — read from the URL once (spatie/laravel-query-builder's
// filter[...] shape) so a reload/shared link keeps the current view.
const initialParams = new URLSearchParams(window.location.search);
const filters = reactive({
    status: initialParams.get('filter[status]') ?? null,
    cat_id: initialParams.get('filter[cat_id]') ? Number(initialParams.get('filter[cat_id]')) : null,
    from: initialParams.get('filter[from]') ?? '',
    to: initialParams.get('filter[to]') ?? '',
});

// Set from the nav's "Liste d'attente" link (AdminLayout.vue) — kept as-is
// across filter changes/pagination below rather than as its own filter
// control, since it's a separate nav destination, not something the admin
// toggles from this page.
const isWaitingList = initialParams.get('filter[waiting_list]') === '1';
const pageTitle = isWaitingList ? "Liste d'attente" : 'Réservations';
const createLabel = isWaitingList ? 'Nouvelle inscription' : 'Nouvelle réservation';

function applyFilters(): void {
    router.get(
        route('admin.deposits.index'),
        {
            filter: {
                status: filters.status ?? undefined,
                cat_id: filters.cat_id ?? undefined,
                from: filters.from || undefined,
                to: filters.to || undefined,
                waiting_list: isWaitingList ? 1 : undefined,
            },
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function goToPage(pageNumber: number): void {
    router.get(
        route('admin.deposits.index'),
        {
            filter: {
                status: filters.status ?? undefined,
                cat_id: filters.cat_id ?? undefined,
                from: filters.from || undefined,
                to: filters.to || undefined,
                waiting_list: isWaitingList ? 1 : undefined,
            },
            page: pageNumber,
        },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">{{ pageTitle }}</h2>
                <Link :href="route('admin.deposits.create', isWaitingList ? { waiting_list: 1 } : {})">
                    <Button :label="createLabel" icon="pi pi-plus" />
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
                        <Column v-if="!isWaitingList" header="Chat">
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
                                        @click="markPaid(data, data.name)"
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
                                        @click="finalize(data, data.name)"
                                    />
                                    <Button
                                        v-if="isSuperAdmin && data.status === 'paid'"
                                        label="Rembourser"
                                        icon="pi pi-replay"
                                        severity="danger"
                                        size="small"
                                        text
                                        @click="refund(data, data.name)"
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

        <FinalizeOwnerDialog
            v-model:visible="finalizeDialogVisible"
            v-model:owner-mode="ownerMode"
            :owners="owners"
            :form="finalizeForm"
            @submit="submitFinalize()"
        />
    </AdminLayout>
</template>
