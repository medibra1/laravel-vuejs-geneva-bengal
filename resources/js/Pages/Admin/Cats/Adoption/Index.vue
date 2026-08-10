<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useTableQuery } from '@/Composables/useTableQuery';
import type { Cat, Color, Paginated } from '@/types/models';

defineProps<{
    cats: Paginated<Cat>;
    colors: Color[];
}>();

const typeLabels: Record<string, string> = {
    chaton: 'Chaton',
    chat: 'Chat',
};

const statusOptions = [
    { label: 'Disponible', value: 'disponible' },
    { label: 'En attente', value: 'en_attente' },
    { label: 'Adopté', value: 'adopte' },
];

function statusSeverity(status: string): 'success' | 'warn' | 'secondary' {
    if (status === 'disponible') return 'success';
    if (status === 'en_attente') return 'warn';

    return 'secondary';
}

const { filters, sortField, sortOrder, applyFilters, applyFiltersDebounced, onSort, goToPage } = useTableQuery({
    routeName: 'admin.cats.adoption.index',
    filterDefaults: { search: null as string | null, color_id: null as number | null, status: null as string | null },
    numericFilterKeys: ['color_id'],
});

function destroy(cat: Cat): void {
    if (confirm(`Supprimer ${cat.name} ?`)) {
        router.delete(route('admin.cats.adoption.destroy', cat.id));
    }
}
</script>

<template>
    <Head title="Chatons (adoption)" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Chatons (adoption)</h2>
                <Link :href="route('admin.cats.adoption.create')">
                    <Button label="Nouveau chaton" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-end gap-4 bg-white dark:bg-neutral-800 p-4 shadow-sm sm:rounded-lg">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Recherche</label>
                        <InputText
                            v-model="filters.search"
                            placeholder="Nom, couleur des yeux…"
                            class="w-56"
                            @input="applyFiltersDebounced"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Couleur</label>
                        <Select
                            v-model="filters.color_id"
                            :options="colors"
                            option-label="name"
                            option-value="id"
                            show-clear
                            placeholder="Toutes les couleurs"
                            class="w-48"
                            @update:model-value="applyFilters"
                        />
                    </div>
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
                </div>

                <div class="overflow-hidden bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg">
                    <DataTable
                        :value="cats.data"
                        data-key="id"
                        lazy
                        :sort-field="sortField"
                        :sort-order="sortOrder"
                        @sort="onSort"
                    >
                        <Column header="Photo">
                            <template #body="{ data }">
                                <img
                                    v-if="data.photos.length"
                                    :src="data.photos[0].url"
                                    :alt="data.name"
                                    class="h-12 w-12 rounded object-cover"
                                />
                                <div v-else class="h-12 w-12 rounded bg-gray-100" />
                            </template>
                        </Column>
                        <Column field="name" header="Nom" sortable />
                        <Column header="Type">
                            <template #body="{ data }">{{ typeLabels[data.type] ?? data.type }}</template>
                        </Column>
                        <Column field="sex" header="Sexe" />
                        <Column header="Couleur">
                            <template #body="{ data }">{{ data.color?.name }}</template>
                        </Column>
                        <Column field="price" header="Prix" sortable>
                            <template #body="{ data }">{{ data.price !== null ? `${data.price / 100} CHF` : '—' }}</template>
                        </Column>
                        <Column header="Statut">
                            <template #body="{ data }">
                                <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.cats.adoption.edit', data.id)">
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

                    <div v-if="cats.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button
                            v-for="page in cats.last_page"
                            :key="page"
                            :label="String(page)"
                            :severity="page === cats.current_page ? 'primary' : 'secondary'"
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
