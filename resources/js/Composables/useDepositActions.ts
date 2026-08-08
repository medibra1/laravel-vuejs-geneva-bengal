import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

export interface FinalizeFormData {
    owner_id: number | null;
    new_owner: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
        city: string;
    };
}

export const paymentMethodLabels: Record<string, string> = {
    stripe: 'Stripe',
    cash: 'Espèces',
    bank_transfer: 'Virement',
    twint_manual: 'TWINT (manuel)',
};

export function statusSeverity(status: string): 'success' | 'warn' | 'danger' | 'secondary' {
    if (status === 'paid') return 'success';
    if (status === 'pending') return 'warn';
    if (status === 'failed') return 'danger';

    return 'secondary';
}

export function formatAmount(cents: number, currency: string): string {
    return new Intl.NumberFormat('fr-CH', { style: 'currency', currency }).format(cents / 100);
}

export function formatDate(date: string | null): string {
    if (!date) return '—';

    return new Intl.DateTimeFormat('fr-CH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date));
}

/**
 * Shared deposit actions (mark paid, refund, finalize, copy payment link) —
 * used by both Admin/Deposits/Index.vue and CatAdoptionPanel.vue so a
 * reservation can be driven either from the deposits list or straight from
 * the cat's own edit page.
 *
 * Each action takes a human-readable `label` for its confirm() dialog
 * instead of reading it off the deposit itself, since the two callers don't
 * share the same deposit shape (CatResource's embedded deposits are a
 * trimmed-down subset of Admin\DepositController::index()'s).
 */
export function useDepositActions() {
    const copiedId = ref<number | null>(null);

    async function copyPaymentLink(deposit: { id: number; payment_link_url: string | null }): Promise<void> {
        if (!deposit.payment_link_url) return;

        await navigator.clipboard.writeText(deposit.payment_link_url);
        copiedId.value = deposit.id;
        setTimeout(() => {
            if (copiedId.value === deposit.id) copiedId.value = null;
        }, 2000);
    }

    function markPaid(deposit: { id: number; payment_method: string }, label: string, onSuccess?: () => void): void {
        if (confirm(`Marquer l'acompte de ${label} comme payé (${paymentMethodLabels[deposit.payment_method]}) ?`)) {
            router.post(route('admin.deposits.mark-paid', deposit.id), {}, { preserveScroll: true, onSuccess });
        }
    }

    // No confirm() — unlike markPaid/refund/finalize, this doesn't change
    // anything by itself, it only asks Stripe and applies markPaid() when
    // Stripe agrees the checkout is actually paid. Safe to fire freely,
    // e.g. when a client says they paid but the webhook seems stuck.
    function verifyStripe(deposit: { id: number }, onSuccess?: () => void): void {
        router.post(route('admin.deposits.verify-stripe', deposit.id), {}, { preserveScroll: true, onSuccess });
    }

    function refund(deposit: { id: number; amount: number; currency: string }, label: string, onSuccess?: () => void): void {
        if (confirm(`Rembourser l'acompte de ${formatAmount(deposit.amount, deposit.currency)} de ${label} ?`)) {
            router.post(route('admin.deposits.refund', deposit.id), {}, { preserveScroll: true, onSuccess });
        }
    }

    // Finalizing skips the owner dialog entirely when the deposit already
    // has one (set back at creation, or already linked) — nothing left to
    // ask.
    const finalizeDialogVisible = ref(false);
    const finalizingDepositId = ref<number | null>(null);
    const ownerMode = ref<'existing' | 'new'>('existing');

    const finalizeForm = useForm<FinalizeFormData>({
        owner_id: null,
        new_owner: {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            city: '',
        },
    });

    function finalize(deposit: { id: number; owner_id?: number | null }, label: string, onSuccess?: () => void): void {
        if (deposit.owner_id) {
            if (confirm(`Finaliser l'adoption pour ${label} ?`)) {
                router.post(route('admin.deposits.finalize', deposit.id), {}, { preserveScroll: true, onSuccess });
            }
            return;
        }

        finalizingDepositId.value = deposit.id;
        ownerMode.value = 'existing';
        finalizeForm.reset();
        finalizeForm.clearErrors();
        finalizeDialogVisible.value = true;
    }

    function submitFinalize(onSuccess?: () => void): void {
        if (!finalizingDepositId.value) return;

        finalizeForm
            .transform((data) => (ownerMode.value === 'existing' ? { owner_id: data.owner_id } : { new_owner: data.new_owner }))
            .post(route('admin.deposits.finalize', finalizingDepositId.value), {
                preserveScroll: true,
                onSuccess: () => {
                    finalizeDialogVisible.value = false;
                    onSuccess?.();
                },
            });
    }

    return {
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
    };
}
