<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import type { Cat, Paginated } from '@/types/models';

defineProps<{
    cats: Paginated<Cat>;
}>();

function statusSeverity(status: string): 'success' | 'warn' | 'secondary' {
    if (status === 'disponible') return 'success';
    if (status === 'en_attente') return 'warn';

    return 'secondary';
}

function goToPage(page: number): void {
    router.get(route('admin.cats.index'), { page }, { preserveState: true, preserveScroll: true });
}

function destroy(cat: Cat): void {
    if (confirm(`Supprimer ${cat.name} ?`)) {
        router.delete(route('admin.cats.destroy', cat.id));
    }
}
</script>

<template>
    <Head title="Chats" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Chats</h2>
                <Link :href="route('admin.cats.create')">
                    <Button label="Nouveau chat" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="cats.data" data-key="id">
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
                        <Column field="name" header="Nom" />
                        <Column field="type" header="Type" />
                        <Column field="sex" header="Sexe" />
                        <Column header="Couleur">
                            <template #body="{ data }">{{ data.color?.name }}</template>
                        </Column>
                        <Column header="Statut">
                            <template #body="{ data }">
                                <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.cats.edit', data.id)">
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
