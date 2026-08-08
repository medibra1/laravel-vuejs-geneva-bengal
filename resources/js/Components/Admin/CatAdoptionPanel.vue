<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import FinalizeOwnerDialog from '@/Components/Admin/FinalizeOwnerDialog.vue';
import { formatAmount, formatDate, paymentMethodLabels, statusSeverity, useDepositActions } from '@/Composables/useDepositActions';
import type { PageProps } from '@/types';
import type { Cat, OwnerOption } from '@/types/models';

const props = defineProps<{
    cat: Cat;
    owners: OwnerOption[];
}>();

const page = usePage<PageProps>();
const isSuperAdmin = computed(() => page.props.auth.roles.includes('super_admin'));

// The most recent deposit is the one that matters here — an older
// cancelled/expired one (see CLAUDE.md: a released pending deposit isn't
// deleted) shouldn't shadow it. cat.deposits comes back latest() first,
// see AdoptionCatController::edit().
const deposit = computed(() => props.cat.deposits?.[0] ?? null);

const ownerLabel = computed(() => (deposit.value?.owner ? `${deposit.value.owner.first_name} ${deposit.value.owner.last_name}` : props.cat.name));

const {
    copiedId,
    copyPaymentLink,
    markPaid,
    verifyStripe,
    refund,
    finalize,
    submitFinalize,
    finalizeDialogVisible,
    ownerMode,
    finalizeForm,
} = useDepositActions();
</script>

<template>
    <div>
        <div v-if="!deposit" class="rounded-md border border-dashed border-gray-300 p-6 text-center dark:border-neutral-700">
            <p class="text-sm text-neutral-500">Aucune réservation pour {{ cat.name }} pour l'instant.</p>
            <Link :href="route('admin.deposits.create', { cat_id: cat.id })" class="mt-4 inline-block">
                <Button label="Créer une réservation" icon="pi pi-plus" size="small" />
            </Link>
        </div>

        <div v-else class="space-y-4 rounded-md border border-gray-200 p-6 dark:border-neutral-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <Tag :value="deposit.status" :severity="statusSeverity(deposit.status)" />
                    <Tag v-if="deposit.finalized_at" value="Finalisé" severity="info" />
                </div>
                <span class="text-lg font-semibold">{{ formatAmount(deposit.amount, deposit.currency) }}</span>
            </div>

            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-neutral-500">Méthode</dt>
                    <dd>{{ paymentMethodLabels[deposit.payment_method] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Payé le</dt>
                    <dd>{{ formatDate(deposit.paid_at) }}</dd>
                </div>
                <div v-if="deposit.owner" class="sm:col-span-2">
                    <dt class="text-neutral-500">Adoptant</dt>
                    <dd>
                        {{ deposit.owner.first_name }} {{ deposit.owner.last_name }} — {{ deposit.owner.email }}
                        <span v-if="deposit.owner.phone"> — {{ deposit.owner.phone }}</span>
                    </dd>
                </div>
                <div v-if="deposit.finalized_at" class="sm:col-span-2">
                    <dt class="text-neutral-500">Adoption finalisée le</dt>
                    <dd>{{ formatDate(deposit.finalized_at) }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-neutral-700">
                <Button
                    v-if="deposit.payment_method !== 'stripe' && deposit.status === 'pending'"
                    label="Marquer payé"
                    icon="pi pi-check"
                    severity="success"
                    size="small"
                    @click="markPaid(deposit, ownerLabel)"
                />
                <Button
                    v-if="deposit.payment_method === 'stripe' && deposit.status === 'pending' && deposit.payment_link_url"
                    :label="copiedId === deposit.id ? 'Copié !' : 'Copier le lien'"
                    icon="pi pi-link"
                    severity="secondary"
                    size="small"
                    @click="copyPaymentLink(deposit)"
                />
                <Button
                    v-if="deposit.payment_method === 'stripe' && deposit.status === 'pending'"
                    label="Vérifier sur Stripe"
                    icon="pi pi-refresh"
                    severity="info"
                    size="small"
                    @click="verifyStripe(deposit)"
                />
                <Button
                    v-if="deposit.status === 'paid' && !deposit.finalized_at"
                    label="Finaliser l'adoption"
                    icon="pi pi-heart-fill"
                    severity="info"
                    size="small"
                    @click="finalize({ id: deposit.id, owner_id: deposit.owner?.id ?? null }, ownerLabel)"
                />
                <Button
                    v-if="isSuperAdmin && deposit.status === 'paid'"
                    label="Rembourser"
                    icon="pi pi-replay"
                    severity="danger"
                    size="small"
                    @click="refund(deposit, ownerLabel)"
                />
            </div>
        </div>

        <FinalizeOwnerDialog
            v-model:visible="finalizeDialogVisible"
            v-model:owner-mode="ownerMode"
            :owners="owners"
            :form="finalizeForm"
            @submit="submitFinalize()"
        />
    </div>
</template>
