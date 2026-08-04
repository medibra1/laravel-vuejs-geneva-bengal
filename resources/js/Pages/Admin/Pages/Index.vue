<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import type { CmsPage, Paginated } from '@/types/models';

defineProps<{
    pages: Paginated<CmsPage & { id: number; slug: string }>;
}>();

function goToPage(page: number): void {
    router.get(route('admin.pages.index'), { page }, { preserveState: true, preserveScroll: true });
}

function destroy(cmsPage: { id: number; slug: string }): void {
    if (confirm(`Supprimer la page "${cmsPage.slug}" ?`)) {
        router.delete(route('admin.pages.destroy', cmsPage.id));
    }
}
</script>

<template>
    <Head title="Pages" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Pages</h2>
                <Link :href="route('admin.pages.create')">
                    <Button label="Nouvelle page" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="pages.data" data-key="id">
                        <Column header="Titre">
                            <template #body="{ data }">{{ data.title.fr }}</template>
                        </Column>
                        <Column field="slug" header="Slug" />
                        <Column field="menu_group" header="Groupe de menu" />
                        <Column field="order" header="Ordre" />
                        <Column header="Statut">
                            <template #body="{ data }">
                                <Tag
                                    :value="data.is_published ? 'Publiée' : 'Brouillon'"
                                    :severity="data.is_published ? 'success' : 'secondary'"
                                />
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.pages.edit', data.id)">
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

                    <div v-if="pages.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button
                            v-for="page in pages.last_page"
                            :key="page"
                            :label="String(page)"
                            :severity="page === pages.current_page ? 'primary' : 'secondary'"
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
