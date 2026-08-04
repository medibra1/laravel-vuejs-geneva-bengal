<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import type { Owner, Paginated } from '@/types/models';

defineProps<{
    owners: Paginated<Owner>;
}>();

function goToPage(page: number): void {
    router.get(route('admin.owners.index'), { page }, { preserveState: true, preserveScroll: true });
}

function destroy(owner: Owner): void {
    if (confirm(`Supprimer ${owner.first_name} ${owner.last_name} ?`)) {
        router.delete(route('admin.owners.destroy', owner.id));
    }
}
</script>

<template>
    <Head title="Adoptants" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Adoptants</h2>
                <Link :href="route('admin.owners.create')">
                    <Button label="Nouvel adoptant" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="owners.data" data-key="id">
                        <Column header="Nom">
                            <template #body="{ data }">{{ data.first_name }} {{ data.last_name }}</template>
                        </Column>
                        <Column field="email" header="Email" />
                        <Column field="phone" header="Téléphone" />
                        <Column field="city" header="Ville" />
                        <Column header="Préférence d'adoption">
                            <template #body="{ data }">
                                <span v-if="data.desired_cat">{{ data.desired_cat.name }}</span>
                                <span v-else-if="data.desired_color">Couleur : {{ data.desired_color.name }}</span>
                                <span v-else class="text-neutral-400">—</span>
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.owners.edit', data.id)">
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

                    <div v-if="owners.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button
                            v-for="page in owners.last_page"
                            :key="page"
                            :label="String(page)"
                            :severity="page === owners.current_page ? 'primary' : 'secondary'"
                            size="small"
                            text
                            @click="goToPage(page)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
