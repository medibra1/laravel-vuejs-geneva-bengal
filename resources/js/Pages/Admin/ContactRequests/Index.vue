<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import type { ContactRequest, Paginated } from '@/types/models';

defineProps<{
    contactRequests: Paginated<ContactRequest>;
}>();

const statusOptions = [
    { label: 'Nouveau', value: 'new' },
    { label: 'Traité', value: 'processed' },
    { label: 'Archivé', value: 'archived' },
];

const reasonLabels: Record<string, string> = {
    adopt: 'Adoption',
    waiting_list: "Liste d'attente",
    question: 'Question',
};

function statusSeverity(status: string): 'info' | 'success' | 'secondary' {
    if (status === 'new') return 'info';
    if (status === 'processed') return 'success';

    return 'secondary';
}

function updateStatus(contactRequest: ContactRequest, status: string): void {
    router.put(route('admin.contact-requests.update', contactRequest.id), { status }, { preserveScroll: true });
}

function goToPage(page: number): void {
    router.get(route('admin.contact-requests.index'), { page }, { preserveState: true, preserveScroll: true });
}

function destroy(contactRequest: ContactRequest): void {
    if (confirm(`Supprimer la demande de ${contactRequest.name} ?`)) {
        router.delete(route('admin.contact-requests.destroy', contactRequest.id));
    }
}
</script>

<template>

    <Head title="Demandes de contact" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Demandes de contact</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="contactRequests.data" data-key="id">
                        <Column field="name" header="Nom" />
                        <Column field="email" header="E-mail" />
                        <Column header="Motif">
                            <template #body="{ data }">{{ reasonLabels[data.reason] }}</template>
                        </Column>
                        <Column header="Chat concerné">
                            <template #body="{ data }">{{ data.cat?.name ?? '—' }}</template>
                        </Column>
                        <Column field="message" header="Message">
                            <template #body="{ data }">
                                <span class="line-clamp-2 max-w-xs">{{ data.message }}</span>
                            </template>
                        </Column>
                        <Column header="Statut">
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                                    <Select :model-value="data.status" :options="statusOptions" option-label="label"
                                        option-value="value" class="w-40"
                                        @update:model-value="(value) => updateStatus(data, value)" />
                                </div>
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <Button icon="pi pi-trash" severity="danger" size="small" text @click="destroy(data)" />
                            </template>
                        </Column>
                    </DataTable>

                    <div v-if="contactRequests.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button v-for="page in contactRequests.last_page" :key="page" :label="String(page)"
                            :severity="page === contactRequests.current_page ? 'primary' : 'secondary'" size="small"
                            text @click="goToPage(page)" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
