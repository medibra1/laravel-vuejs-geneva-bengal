<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import ConfirmPasswordModal from '@/Components/ConfirmPasswordModal.vue';
import FinalizeOwnerDialog from '@/Components/Admin/FinalizeOwnerDialog.vue';
import { useConfirmsPassword } from '@/Composables/useConfirmsPassword';
import { formatAmount, formatDate, manualPaymentMethodOptions, paymentMethodLabels, statusSeverity, useDepositActions } from '@/Composables/useDepositActions';
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
    cancel,
    finalize,
    submitFinalize,
    finalizeDialogVisible,
    ownerMode,
    finalizeForm,
    finalizeDirectly,
    submitFinalizeDirectly,
    finalizeDirectlyDialogVisible,
    finalizeDirectlyOwnerMode,
    finalizeDirectlyForm,
} = useDepositActions();

// "Finaliser sans dépôt" (admin.cats.finalize-directly) — super_admin only,
// independent of whether a deposit exists at all: covers a gift or an
// in-person sale handled fully off-system. See DepositPaymentProcessor::
// finalizeDirectly().
const canFinalizeDirectly = computed(() => isSuperAdmin.value && props.cat.status !== 'adopte');

// Same password-confirmation gate as Admin/Deposits/Index.vue's — refund,
// cancel, finalize and verify-stripe are behind password.confirm
// server-side too when reached from here.
const { confirmingPassword, form: confirmPasswordForm, confirmPassword, submitPassword: submitConfirmPassword } = useConfirmsPassword();

// A deposit created with "à définir plus tard" (payment_method null, see
// Admin/Deposits/Form.vue) needs the admin to pick one right here, at the
// moment of marking it paid — see MarkDepositPaidRequest and the same
// selector in Admin/Deposits/Index.vue.
const pendingMethodChoice = reactive<Record<number, string | null>>({});
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
                    <dd>{{ deposit.payment_method ? paymentMethodLabels[deposit.payment_method] : 'À définir' }}</dd>
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
                    v-if="deposit.payment_method !== 'stripe' && deposit.payment_method !== null && deposit.status === 'pending'"
                    label="Marquer payé"
                    icon="pi pi-check"
                    severity="success"
                    size="small"
                    @click="markPaid(deposit, ownerLabel)"
                />
                <div v-if="deposit.payment_method === null && deposit.status === 'pending'" class="flex items-center gap-2">
                    <Select
                        v-model="pendingMethodChoice[deposit.id]"
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
                        :disabled="!pendingMethodChoice[deposit.id]"
                        @click="markPaid(deposit, ownerLabel, pendingMethodChoice[deposit.id])"
                    />
                </div>
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
                    @click="confirmPassword(() => verifyStripe(deposit!))"
                />
                <Button
                    v-if="deposit.status === 'paid' && !deposit.finalized_at"
                    label="Finaliser l'adoption"
                    icon="pi pi-heart-fill"
                    severity="info"
                    size="small"
                    @click="confirmPassword(() => finalize({ id: deposit!.id, owner_id: deposit!.owner?.id ?? null }, ownerLabel))"
                />
                <Button
                    v-if="isSuperAdmin && deposit.status === 'paid'"
                    label="Rembourser"
                    icon="pi pi-replay"
                    severity="danger"
                    size="small"
                    @click="confirmPassword(() => refund(deposit!, ownerLabel))"
                />
                <Button
                    v-if="isSuperAdmin && deposit.status === 'paid'"
                    label="Annuler la réservation"
                    icon="pi pi-times-circle"
                    severity="danger"
                    size="small"
                    @click="confirmPassword(() => cancel(deposit!, ownerLabel))"
                />
            </div>
        </div>

        <div v-if="canFinalizeDirectly" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950">
            <p class="text-sm text-amber-800 dark:text-amber-200">
                Adoption gérée entièrement hors système (don, vente en personne) : crée l'adoption sans aucun paiement en ligne enregistré.
            </p>
            <Button
                label="Finaliser sans dépôt"
                icon="pi pi-heart"
                severity="warning"
                size="small"
                @click="confirmPassword(() => finalizeDirectly(cat))"
            />
        </div>

        <FinalizeOwnerDialog
            v-model:visible="finalizeDialogVisible"
            v-model:owner-mode="ownerMode"
            :owners="owners"
            :form="finalizeForm"
            @submit="submitFinalize()"
        />

        <FinalizeOwnerDialog
            v-model:visible="finalizeDirectlyDialogVisible"
            v-model:owner-mode="finalizeDirectlyOwnerMode"
            :owners="owners"
            :form="finalizeDirectlyForm"
            @submit="submitFinalizeDirectly()"
        />

        <ConfirmPasswordModal
            v-model:visible="confirmingPassword"
            :form="confirmPasswordForm"
            @submit="submitConfirmPassword()"
        />
    </div>
</template>
