<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import ConfirmPasswordModal from '@/Components/ConfirmPasswordModal.vue';
import FinalizeOwnerDialog from '@/Components/Admin/FinalizeOwnerDialog.vue';
import { useConfirmsPassword } from '@/Composables/useConfirmsPassword';
import { formatAmount, formatDate, manualPaymentMethodOptions, paymentMethodLabels, statusSeverity, useDepositActions } from '@/Composables/useDepositActions';
import type { PageProps } from '@/types';
import type { Deposit, OwnerCatOption, OwnerOption, Paginated } from '@/types/models';

const props = defineProps<{
    deposits: Paginated<Deposit>;
    cats: OwnerCatOption[];
    owners: OwnerOption[];
    reservableCats: OwnerCatOption[];
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

// A deposit created with "à définir plus tard" (payment_method null, see
// Admin/Deposits/Form.vue) needs the admin to pick one right here, at the
// moment of marking it paid — see MarkDepositPaidRequest. Keyed by deposit
// id since several such rows can be mid-choice on the page at once.
const pendingMethodChoice = reactive<Record<number, string | null>>({});

const {
    copiedId,
    copyPaymentLink,
    markPaid,
    verifyStripe,
    refund,
    cancel,
    finalize,
    submitFinalize,
    finalizeDialogVisible,
    ownerMode,
    finalizeForm,
} = useDepositActions();

// Refund, cancel, finalize and verify-stripe all touch money or
// ownership and sit behind password.confirm server-side (see
// routes/admin.php) — this is the client-side half, see
// useConfirmsPassword.ts.
const { confirmingPassword, form: confirmPasswordForm, confirmPassword, submitPassword: submitConfirmPassword } = useConfirmsPassword();

// Turns a waiting-list entry into a reservation for a specific kitten —
// see Admin\DepositController::assignCat(). Local to this page (unlike the
// composable's actions above): not something CatAdoptionPanel.vue needs,
// since a cat's own edit page already knows which cat it is.
const assignCatDialogVisible = ref(false);
const assigningDepositId = ref<number | null>(null);
const assignCatForm = useForm({ cat_id: null as number | null });

function openAssignCat(deposit: Deposit): void {
    assigningDepositId.value = deposit.id;
    assignCatForm.reset();
    assignCatForm.clearErrors();
    assignCatDialogVisible.value = true;
}

function submitAssignCat(): void {
    if (!assigningDepositId.value) return;

    assignCatForm.post(route('admin.deposits.assign-cat', assigningDepositId.value), {
        preserveScroll: true,
        onSuccess: () => {
            assignCatDialogVisible.value = false;
        },
    });
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
                            <template #body="{ data }">{{ data.payment_method ? paymentMethodLabels[data.payment_method] : 'À définir' }}</template>
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
                                        v-if="!data.cat && data.status === 'pending'"
                                        label="Assigner un chat"
                                        icon="pi pi-tag"
                                        severity="contrast"
                                        size="small"
                                        text
                                        @click="openAssignCat(data)"
                                    />
                                    <Button
                                        v-if="data.payment_method !== 'stripe' && data.payment_method !== null && data.status === 'pending'"
                                        label="Marquer payé"
                                        icon="pi pi-check"
                                        severity="success"
                                        size="small"
                                        text
                                        @click="markPaid(data, data.name)"
                                    />
                                    <div
                                        v-if="data.payment_method === null && data.status === 'pending'"
                                        class="flex items-center gap-2"
                                    >
                                        <Select
                                            v-model="pendingMethodChoice[data.id]"
                                            :options="manualPaymentMethodOptions"
                                            option-label="label"
                                            option-value="value"
                                            placeholder="Choisir la méthode"
                                            class="w-44"
                                            size="small"
                                        />
                                        <Button
                                            label="Marquer payé"
                                            icon="pi pi-check"
                                            severity="success"
                                            size="small"
                                            text
                                            :disabled="!pendingMethodChoice[data.id]"
                                            @click="markPaid(data, data.name, pendingMethodChoice[data.id])"
                                        />
                                    </div>
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
                                        v-if="data.payment_method === 'stripe' && data.status === 'pending'"
                                        label="Vérifier sur Stripe"
                                        icon="pi pi-refresh"
                                        severity="info"
                                        size="small"
                                        text
                                        @click="confirmPassword(() => verifyStripe(data))"
                                    />
                                    <Button
                                        v-if="data.status === 'paid' && !data.finalized_at"
                                        label="Finaliser"
                                        icon="pi pi-heart-fill"
                                        severity="info"
                                        size="small"
                                        text
                                        @click="confirmPassword(() => finalize(data, data.name))"
                                    />
                                    <Button
                                        v-if="isSuperAdmin && data.status === 'paid'"
                                        label="Rembourser"
                                        icon="pi pi-replay"
                                        severity="danger"
                                        size="small"
                                        text
                                        @click="confirmPassword(() => refund(data, data.name))"
                                    />
                                    <Button
                                        v-if="isSuperAdmin && data.status === 'paid'"
                                        label="Annuler la réservation"
                                        icon="pi pi-times-circle"
                                        severity="danger"
                                        size="small"
                                        text
                                        @click="confirmPassword(() => cancel(data, data.name))"
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

        <Dialog v-model:visible="assignCatDialogVisible" header="Assigner un chat" modal class="w-full max-w-md">
            <p class="mb-4 text-sm text-neutral-500">
                Cette entrée passera en réservation pour le chat choisi, qui sera mis en attente.
            </p>

            <Select
                v-model="assignCatForm.cat_id"
                :options="reservableCats"
                option-label="name"
                option-value="id"
                placeholder="Choisir un chat"
                class="w-full"
            />
            <p v-if="assignCatForm.errors.cat_id" class="mt-1 text-sm text-red-600">{{ assignCatForm.errors.cat_id }}</p>

            <template #footer>
                <Button label="Annuler" severity="secondary" text @click="assignCatDialogVisible = false" />
                <Button label="Assigner" :disabled="assignCatForm.processing || !assignCatForm.cat_id" @click="submitAssignCat()" />
            </template>
        </Dialog>

        <ConfirmPasswordModal
            v-model:visible="confirmingPassword"
            :form="confirmPasswordForm"
            @submit="submitConfirmPassword()"
        />
    </AdminLayout>
</template>
