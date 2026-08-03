<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import type { Litter, Paginated } from '@/types/models';

defineProps<{
    litters: Paginated<Litter>;
}>();

function goToPage(page: number): void {
    router.get(route('admin.litters.index'), { page }, { preserveState: true, preserveScroll: true });
}

function destroy(litter: Litter): void {
    if (confirm('Supprimer cette portée ?')) {
        router.delete(route('admin.litters.destroy', litter.id));
    }
}
</script>

<template>
    <Head title="Portées" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Portées</h2>
                <Link :href="route('admin.litters.create')">
                    <Button label="Nouvelle portée" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="litters.data" data-key="id">
                        <Column header="Père (sire)">
                            <template #body="{ data }">{{ data.sire?.name ?? '—' }}</template>
                        </Column>
                        <Column header="Mère (dam)">
                            <template #body="{ data }">{{ data.dam?.name ?? '—' }}</template>
                        </Column>
                        <Column field="expected_date" header="Date prévue" />
                        <Column header="Chatons">
                            <template #body="{ data }">{{ data.kittens_count ?? 0 }}</template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.litters.edit', data.id)">
                                        <Button icon="pi pi-pencil" severity="secondary" size="small" text />
                                    </Link>
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        size="small"
                                        text
                                        @click="destroy(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>

                    <div v-if="litters.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button
                            v-for="page in litters.last_page"
                            :key="page"
                            :label="String(page)"
                            :severity="page === litters.current_page ? 'primary' : 'secondary'"
                            size="small"
                            text
                            @click="goToPage(page)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
