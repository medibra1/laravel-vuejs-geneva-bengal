<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import type { PageProps } from '@/types';
import type { Deposit, Paginated } from '@/types/models';

defineProps<{
    deposits: Paginated<Deposit>;
}>();

const page = usePage<PageProps>();
const isSuperAdmin = computed(() => page.props.auth.roles.includes('super_admin'));

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

function goToPage(pageNumber: number): void {
    router.get(route('admin.deposits.index'), { page: pageNumber }, { preserveState: true, preserveScroll: true });
}

function refund(deposit: Deposit): void {
    if (confirm(`Rembourser l'acompte de ${formatAmount(deposit.amount, deposit.currency)} de ${deposit.name} ?`)) {
        router.post(route('admin.deposits.refund', deposit.id), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Acomptes" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Acomptes</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="deposits.data" data-key="id">
                        <Column field="name" header="Nom" />
                        <Column field="email" header="E-mail" />
                        <Column header="Chat">
                            <template #body="{ data }">{{ data.cat?.name ?? "Liste d'attente" }}</template>
                        </Column>
                        <Column header="Montant">
                            <template #body="{ data }">{{ formatAmount(data.amount, data.currency) }}</template>
                        </Column>
                        <Column header="Statut">
                            <template #body="{ data }">
                                <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                            </template>
                        </Column>
                        <Column header="Payé le">
                            <template #body="{ data }">{{ formatDate(data.paid_at) }}</template>
                        </Column>
                        <Column v-if="isSuperAdmin" header="Actions">
                            <template #body="{ data }">
                                <Button
                                    v-if="data.status === 'paid'"
                                    label="Rembourser"
                                    icon="pi pi-replay"
                                    severity="danger"
                                    size="small"
                                    text
                                    @click="refund(data)"
                                />
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
    </AdminLayout>
</template>
